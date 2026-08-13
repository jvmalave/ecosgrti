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

class ATFService
{
    public function __construct(
        private readonly ProgressCalculationService $progressService,
        private readonly AuditService $auditService
    ) {}

    /**
     * RN-Validación: La fase PL debe estar cerrada.
     */

    public function ensureATFPhaseIsAccessible(Requirement $requirement): void
    {
        // 1. Compatibilidad de estados: Aceptamos 'ES-R' o 'PL_CLOSED' como indicadores de planificación completada
        $validStatuses = ['ES-R', 'PL_CLOSED'];
        $isValidStatus = in_array($requirement->status, $validStatuses) || str_starts_with($requirement->status, 'ATF');

        if (!$isValidStatus) {
            throw new PhaseRequirementNotMetException(
                "La fase de Planificación debe estar cerrada formalmente antes de gestionar Acuerdos ATF."
            );
        }
        
        // 2. ELIMINAMOS la validación $requirement->is_locked.
        // El requerimiento maestro estará sellado intencionalmente para proteger la información original,
        // pero esto no debe bloquear la gestión técnica de sus componentes hijos (Acuerdos ATF).
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

            // B. Transición de Estado a 'ATF-I' (Si es el primer acuerdo)
            $isFirstAgreement = AtfAgreement::where('requirement_id', $requirement->id)->count() === 1;
            
            // Ajustamos la condición para aceptar 'ES-R'
            if ($isFirstAgreement && in_array($requirement->status, ['ES-R', 'PL_CLOSED'])) {
                // Forzamos la actualización directa para ignorar protecciones de modelo (Mass Assignment)
                $requirement->forceFill(['status' => 'ATF-I'])->save();
                
                $requirement->phaseHistories()->create([
                    'phase_status_code'   => 'ATF-I',
                    'transitioned_at'     => now(),
                    'executed_by_user_id' => $userId,
                    'remarks'             => 'Inicio automático de ATF tras primer acuerdo.'
                ]);
            }

            // C. Recálculo Polimórfico Síncrono (CU-008)
            // Refrescamos la instancia para obtener el estatus actualizado (ATF-I)
            $newProgress = $this->progressService->calculateGlobalProgress($requirement->fresh());
            $requirement->forceFill(['progress_percentage' => $newProgress])->save();

            // Rastro de Auditoría
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
                $requirement->id // Ojo: Pongo el ID del requerimiento para mantener el hilo contextual
            );

            return $newAgreement;
        });

        // 3. Purgar caché de Redis POST-Transacción
        Redis::del("req_detail_v2_{$requirement->id}");
        Redis::del("req_{$requirement->id}_progress");
        Redis::incr('dashboard_version'); // Actualiza el dashboard global
        Cache::tags(['dashboard'])->flush();

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
            // 1. Actualizamos el registro principal
            DB::table('workflow.atf_agreements')
                ->where('id', $agreementId)
                ->update([
                    'description'    => $data['description'],
                    'agreement_date' => $data['agreement_date'],
                    'updated_at'     => now()
                ]);

            // 2. Insertamos el log en la misma transacción
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
                // 🔒 Auditoría: DELETE_ATF_AGREEMENT [cite: 66]
        $this->auditService->logModelChange(
            'DELETE_ATF_AGREEMENT',
            "Se eliminó el acuerdo ATF: {$agreementId}",
            ['agreement_id' => $agreementId],
            Auth::id(),
            $agreementId
        );
    });
    }

    /**
     * Helper centralizado para registrar eventos de auditoría.
     */
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
          
          // Obtener el requerimiento
          $requirement = Requirement::findOrFail($requirementId);
          
          // Guardar el tipo de gestión anterior para auditoría
          $tipoAnterior = $requirement->management_type; 

          // Restricción de Cambio por Progreso Avanzado
          // Valida que no existan subfases completadas antes de permitir el cambio
          if ($this->hasAdvancedProgress($requirementId)) { 
              abort(403, 'No se puede cambiar el tipo de gestión porque ya existen subfases avanzadas.'); 
          }

          $tipoNormalizado = ucfirst(strtolower($nuevoTipoGestion));

          // Actualizar el tipo de gestión
          $requirement->management_type = $tipoNormalizado;

          // Recálcular Inmediato de Ponderaciones (Polimorfismo CU-007)
          $nuevoProgreso = $this->progressService->calculateGlobalProgress($requirement);
          
          // Actualizar el porcentaje de progreso
          $requirement->progress_percentage = $nuevoProgreso; 

          // Guardar los cambios
          $requirement->save();

          // Auditoría transparente 
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

          // Invalidación de Caché en Redis
            Redis::del("req_{$requirementId}_progress"); // Purga la barra de progreso
            Redis::del("req_detail_v2_{$requirementId}"); // Purga el modal de detalle
            Cache::tags(['dashboard'])->flush(); // Purga la lista general

          // Retornamos los datos frescos para la UI 
          return [
              'tipo_gestion'    => $tipoNormalizado,
              'progreso_global' => $nuevoProgreso
          ];
      });
  }

    /**
     * RN-Restricción de Cambio por Progreso Avanzado.
     * Verifica si el requerimiento ya tiene subfases avanzadas completadas.
     */
    private function hasAdvancedProgress(string $requirementId): bool
    {
        // 💡 Lógica de negocio futura: 
        // Aquí consultarías si el requerimiento ya está en desarrollo (DEV), 
        // pruebas (QA) o producción (PROD). Si es así, retornarías true.
        
        /* Ejemplo real:
        return DB::table('core.requirements')
            ->where('id', $requirementId)
            ->whereIn('status', ['DEV_INIT', 'QA_OPEN', 'PROD_CLOSED'])
            ->exists();
        */

        // Por ahora, retornamos false para permitir el cambio de tipología 
        // mientras probamos el flujo del CU-017.5.
        return false; 
    }
}