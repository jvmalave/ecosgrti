<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Services;

use App\Domains\Core\Models\Requirement;
use App\Domains\Workflow\Exceptions\PhaseRequirementNotMetException;
use App\Domains\Workflow\Models\AtfAgreement;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use App\Domains\Audit\Services\AuditService;
use App\Domains\Workflow\Services\PhaseTransitionService;
use App\Domains\Core\Dictionaries\CacheKeyDictionary; 

class ATFService
{
    public function __construct(
        private readonly ProgressCalculationService $progressService,
        private readonly AuditService $auditService,
        private readonly PhaseTransitionService $phaseTransitionService 
    ) {}

    /**
     * RN-Validación: La fase PL debe estar cerrada.
     */
    public function ensureATFPhaseIsAccessible(Requirement $requirement): void
    {
        $validStatuses = ['ES-R', 'PL_CLOSED'];
        $isValidStatus = in_array($requirement->status, $validStatuses) || str_starts_with($requirement->status, 'ATF');

        if (!$isValidStatus) {
            throw new PhaseRequirementNotMetException(
                "La fase de Planificación debe estar cerrada formalmente antes de gestionar Acuerdos ATF."
            );
        }
    }

    
    /**
     * Orquesta la creación del acuerdo, la transición de estado y el progreso global.
     */
    public function createAgreement(Requirement $requirement, array $validatedData, string $userId): AtfAgreement
    {
        // 1. Gatekeeper de seguridad
        $this->ensureATFPhaseIsAccessible($requirement);

        // 2. Ejecutar transacción atómica de negocio
        $agreement = DB::transaction(function () use ($requirement, $validatedData, $userId) {
            
            // A. Guardar el Acuerdo
            $newAgreement = AtfAgreement::create([
                'requirement_id'        => $requirement->id,
                'agreement_date'        => $validatedData['agreement_date'],
                'description'           => $validatedData['description'],
                'registered_by_user_id' => $userId,
            ]);

            // B. Transición de Estado a 'ATF-I' (Disparador Transaccional Vanguardia)
            $hasInitHistory = DB::table('workflow.requirement_phase_history')
                ->where('requirement_id', $requirement->id)
                ->where('phase_status_code', 'ATF-I')
                ->exists();

            $newProgress = $requirement->progress_percentage;

            if (!$hasInitHistory) {
                // 1. Registro Histórico Transaccional
                $this->phaseTransitionService->recordTransition(
                    $requirement->id,
                    'ATF-I',
                    $userId,
                    'Inicio automático de ATF tras primer acuerdo.'
                );

                // 2. Cálculo de Progreso
                $newProgress = $this->progressService->calculateGlobalProgress($requirement);

                // 3. Evaluación de Vanguardia
                $isVanguard = $this->phaseTransitionService->isVanguardStatus('ATF-I', $requirement->status);

                $updateData = [
                    'progress_percentage' => $newProgress,
                    'updated_at' => now()
                ];

                if ($isVanguard) {
                    $updateData['status'] = 'ATF-I';
                    $requirement->status = 'ATF-I';
                }

                $requirement->update($updateData);

                // 4. Auditoría de Apertura de Fase
                $this->auditService->logModelChange(
                    'INITIATE_GLOBAL_PHASE',
                    "Apertura global de la fase ATF" . ($isVanguard ? " (Nueva Vanguardia)" : " (Proceso Paralelo)"),
                    [
                        'requirement_id' => $requirement->id,
                        'new_status'     => $requirement->status,
                        'progress_reached' => $newProgress,
                        'is_vanguard_update' => $isVanguard
                    ],
                    $userId,
                    $requirement->id
                );
            }

            // Rastro de Auditoría del Acuerdo
            $this->auditService->logModelChange(
                'CREATE_ATF_AGREEMENT',
                "Se registró un nuevo acuerdo ATF para el requerimiento: {$requirement->rrti}",
                [
                    'requirement_id' => $requirement->id, 
                    'agreement_id'   => $newAgreement->id, 
                    'data'           => $validatedData,
                    'new_progress'   => $newProgress
                ],
                $userId,
                $requirement->id 
            );

            return $newAgreement;
        });

        // =========================================================================
        // 3. DESTRUCCIÓN DEL CACHÉ (EVICCIÓN ESTRICTA POR DICCIONARIO)
        // =========================================================================
        Redis::del(CacheKeyDictionary::requirementDetail($requirement->id));
        Redis::del(CacheKeyDictionary::requirementProgress($requirement->id));
        
        // Actualiza el dashboard global y la etiqueta de caché
        Redis::incr(CacheKeyDictionary::globalDashboardVersion()); 
        Cache::tags([CacheKeyDictionary::dashboardTag()])->flush();

        return $agreement;
    }

    public function getAgreements(string $requirementId): array
    {
        return DB::table('workflow.atf_agreements')
            ->where('requirement_id', $requirementId)
            ->whereNull('deleted_at')
            ->orderBy('created_at', 'desc')
            ->select(
                'id', 
                'description', 
                'agreement_date', 
                'created_at'
            )
            ->get()
            ->toArray();
    }

    public function updateAgreement(string $agreementId, array $data): void
    {
        DB::transaction(function () use ($agreementId, $data) {
            DB::table('workflow.atf_agreements')
                ->where('id', $agreementId)
                ->update([
                    'description'    => $data['description'],
                    'agreement_date' => $data['agreement_date'],
                    'updated_at'     => now()
                ]);

            $this->auditService->logModelChange(
                'UPDATE_ATF_AGREEMENT',
                "Se actualizó la información del acuerdo ATF: {$agreementId}",
                ['agreement_id' => $agreementId, 'deltas' => $data],
                Auth::id(),
                $agreementId
                
            );
        });
    }

    public function deleteAgreement(string $agreementId): void
    {
        DB::transaction(function () use ($agreementId) {
            DB::table('workflow.atf_agreements')
                ->where('id', $agreementId)
                ->update([
                    'deleted_at' => now(),
                    'updated_at' => now() 
                ]);
                
        $this->auditService->logModelChange(
            'DELETE_ATF_AGREEMENT',
            "Se eliminó el acuerdo ATF: {$agreementId}",
            ['agreement_id' => $agreementId],
            Auth::id(),
            $agreementId
        );
    });
    }

    private function logAudit(string $action, string $description, array $payload = []): void
    {
        DB::table('audit.audit_logs')->insert([
            'id'          => Str::uuid(),
            'user_id'     => Auth::id(),
            'action'      => $action,
            'description' => $description,
            'ip_address'  => request()->ip(),
            'user_agent'  => request()->userAgent(),
            'payload'     => json_encode($payload),
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

  public function updateManagementType(string $requirementId, string $nuevoTipoGestion): array
  {
      return DB::transaction(function () use ($requirementId, $nuevoTipoGestion) {
          
          $requirement = Requirement::findOrFail($requirementId);
          
          $tipoAnterior = $requirement->management_type; 

          if ($this->hasAdvancedProgress($requirementId)) { 
              abort(403, 'No se puede cambiar el tipo de gestión porque ya existen subfases avanzadas.'); 
          }

          $tipoNormalizado = ucfirst(strtolower($nuevoTipoGestion));

          $requirement->management_type = $tipoNormalizado;

          $nuevoProgreso = $this->progressService->calculateGlobalProgress($requirement);
          
          $requirement->progress_percentage = $nuevoProgreso; 

          $requirement->save();

          $this->auditService->logModelChange(
              'UPDATE_MANAGEMENT_TYPE',
              "Se cambió el tipo de gestión del requerimiento {$requirementId} de {$tipoAnterior} a {$nuevoTipoGestion}",
              [
                  'requirement_id' => $requirementId,
                  'old_type'       => $tipoAnterior,
                  'new_type'       => $nuevoTipoGestion,
                  'new_progress'   => $nuevoProgreso
              ],
              Auth::id(),
              $requirementId
          );

            Redis::del("req_{$requirementId}_progress"); 
            Redis::del("req_detail_v2_{$requirementId}"); 
            Cache::tags(['dashboard'])->flush(); 

          return [
              'tipo_gestion'    => $tipoNormalizado,
              'progreso_global' => $nuevoProgreso
          ];
      });
  }

    private function hasAdvancedProgress(string $requirementId): bool
    {
        return false; 
    }
}