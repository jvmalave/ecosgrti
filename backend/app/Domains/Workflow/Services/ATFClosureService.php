<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Services;

use App\Domains\Core\Models\Requirement;
use App\Domains\Workflow\Models\AtfAgreement;
use App\Domains\Workflow\Models\RequirementRole;
use App\Domains\Workflow\Models\Deliverable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use App\Domains\Workflow\Services\ProgressCalculationService;
use App\Domains\Audit\Services\AuditService;
use App\Domains\Workflow\Services\PhaseTransitionService; 

class ATFClosureService
{
    public function __construct(
        private ProgressCalculationService $progressService,
        private AuditService $auditService,
        private PhaseTransitionService $phaseTransitionService
    ) {}

    /**
     * FASE 1 - Verificación de Quórum e Idempotencia
     */
    public function checkClosureReadiness(string $requirementId): array
    {
        // 🚀 1. NUEVO: Verificación de Idempotencia (Bloqueo de doble ejecución)
        $isAlreadyClosed = DB::table('workflow.requirement_phase_history')
            ->where('requirement_id', $requirementId)
            ->where('phase_status_code', 'ATF-C')
            ->exists();

        if ($isAlreadyClosed) {
            return [
                'ready'          => false,
                'already_closed' => true,
                'reasons'        => ['La fase Análisis Técnico Funcional ya fue cerrada y sellada previamente.']
            ];
        }

        // 2. Verificación de Quórum
        $reasons = [];
        $agreementsCount = AtfAgreement::where('requirement_id', $requirementId)->count();
        $rolesCount = RequirementRole::where('requirement_id', $requirementId)->count();
        $deliverablesCount = Deliverable::where('requirement_id', $requirementId)->count();

        if ($agreementsCount < 1) {
            $reasons[] = 'Se requiere al menos un (1) Acuerdo firmado.';
        }

        if ($rolesCount < 1 && $deliverablesCount < 1) {
            $reasons[] = 'Se requiere definir al menos un (1) componente técnico (Rol o Entregable).';
        }

        return [
            'ready'          => empty($reasons),
            'already_closed' => false,
            'reasons'        => $reasons,
        ];
    }

    /**
     * FASE 2 - Cierre Atómico con Máquina de Estados
     */
    public function executeClosure(string $requirementId, string $userId): Requirement
    {
        $readiness = $this->checkClosureReadiness($requirementId);
        
        // 🚀 NUEVO: Bloqueo estricto del Endpoint si alguien intenta forzar la petición
        if ($readiness['already_closed']) {
            throw ValidationException::withMessages(['status' => $readiness['reasons']]);
        }

        if (!$readiness['ready']) {
            throw ValidationException::withMessages(['quorum' => $readiness['reasons']]);
        }

        return DB::transaction(function () use ($requirementId, $userId) {
            $requirement = Requirement::lockForUpdate()->findOrFail($requirementId);

            // 1. Registro inmutable en el historial
            // DB::table('workflow.requirement_phase_history')->insert([
            //     'id'                  => (string) Str::uuid(),
            //     'requirement_id'      => $requirementId,
            //     'phase_status_code'   => 'ATF-C',
            //     'transitioned_at'     => now(),
            //     'executed_by_user_id' => $userId,
            //     'created_at'          => now(),
            //     'updated_at'          => now(),
            // ]);

            $this->phaseTransitionService->recordTransition(
                $requirementId,
                'ATF-C',
                (string) auth()->id(),
                'Cierre global exitoso de la subfase ATF'
            );

            // 2. Actualización del Estado Maestro
            $requirement->update(['status' => 'ATF-C']);

            // 3. Auditoría Forense centralizada
            $this->auditService->logModelChange(
                'ATF_PHASE_CLOSE', 
                'Cierre atómico de la Fase Análisis Técnico Funcional', 
                [
                    'requirement_id' => $requirementId,
                    'user_id'        => $userId,
                    'record_id'      => $requirementId
                ],
                $userId,
                $requirementId
            );

            // 4. Limpieza de Caché
            Redis::del(["atf_cache_{$requirementId}", "atf_components_{$requirementId}"]);

            // 5. Recálculo y percistencia del Avance Global

            $calculatedProgress = $this->progressService->calculateGlobalProgress($requirement);

            $requirement->update([
                'progress_percentage' => $calculatedProgress
            ]);

            return $requirement;
        });
    }
}