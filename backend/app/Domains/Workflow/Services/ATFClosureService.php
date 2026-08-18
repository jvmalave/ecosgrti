<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Services;

use App\Domains\Core\Models\Requirement;
use App\Domains\Workflow\Models\AtfAgreement;
use App\Domains\Workflow\Models\RequirementRole;
use App\Domains\Workflow\Models\Deliverable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use App\Domains\Core\Dictionaries\CacheKeyDictionary;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
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
     * FASE 1 - Verificación de Quórum, Idempotencia y Fases Predecesoras
     */
    public function checkClosureReadiness(string $requirementId): array
    {
        // 🚀 1. Verificación de Idempotencia (Bloqueo de doble ejecución)
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

        $reasons = [];

        // =====================================================================
        // Validación de fase previa (Estimación Realizada)
        // =====================================================================
        $hasEstimacionRealizada = DB::table('workflow.requirement_phase_history')
            ->where('requirement_id', $requirementId)
            ->where('phase_status_code', 'ES-R')
            ->exists();

        if (!$hasEstimacionRealizada) {
            $reasons[] = 'Validación fallida: No se puede cerrar esta fase porque la fase previa de Estimación (ES-R) aún está abierta o no se ha completado.';
        }

        // 2. Verificación de Quórum
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
     * FASE 2 - Cierre Atómico con Máquina de Estados (Orden Lógico y Vanguardia)
     */
    public function executeClosure(string $requirementId, string $userId): Requirement
    {
        $readiness = $this->checkClosureReadiness($requirementId);
        
        // 🚀 Bloqueo estricto del Endpoint si alguien intenta forzar la petición
        if ($readiness['already_closed']) {
            throw ValidationException::withMessages(['status' => $readiness['reasons']]);
        }

        if (!$readiness['ready']) {
            throw ValidationException::withMessages(['quorum' => $readiness['reasons']]);
        }

        return DB::transaction(function () use ($requirementId, $userId) {
            $requirement = Requirement::lockForUpdate()->findOrFail($requirementId);

            // =====================================================================
            // ORDEN LÓGICO DE TRANSACCIÓN Y VANGUARDIA
            // =====================================================================

            // Registro Histórico Transaccional
            $this->phaseTransitionService->recordTransition(
                $requirementId,
                'ATF-C',
                $userId,
                'Cierre global exitoso de la subfase ATF'
            );

            // Cálculo de Progreso
            $calculatedProgress = $this->progressService->calculateGlobalProgress($requirement);

            // Evaluación de Vanguardia
            $isVanguard = $this->phaseTransitionService->isVanguardStatus('ATF-C', $requirement->status);

            $updateData = [
                'progress_percentage' => $calculatedProgress,
                'updated_at' => now()
            ];

            // Solo actualizamos el status maestro si es vanguardia
            if ($isVanguard) {
                $updateData['status'] = 'ATF-C';
                $requirement->status = 'ATF-C'; // Memoria
            }

            // Actualización del Estado Maestro
            $requirement->update($updateData);

            //  Auditoría Forense centralizada
            $this->auditService->logModelChange(
                'ATF_PHASE_CLOSE', 
                'Cierre atómico de la Fase Análisis Técnico Funcional' . ($isVanguard ? ' (Nueva Vanguardia)' : ' (Proceso Paralelo)'), 
                [
                    'requirement_id' => $requirementId,
                    'user_id'        => $userId,
                    'record_id'      => $requirementId,
                    'new_status'     => $requirement->status,
                    'progress_reached' => $calculatedProgress,
                    'is_vanguard_update' => $isVanguard
                ],
                $userId,
                $requirementId
            );

            // =========================================================================
            // DESTRUCCIÓN DEL CACHÉ (EVICCIÓN ESTRICTA POR DICCIONARIO)
            // =========================================================================
            
            // Purgas específicas de la fase ATF
            Redis::del([
                CacheKeyDictionary::atfCache($requirementId),
                CacheKeyDictionary::atfComponents($requirementId)
            ]);
            Cache::forget(CacheKeyDictionary::atfCache($requirementId)); // Fallback

            // Purgas globales por cierre de fase (Reflejar nuevos progresos y estados)
            Redis::del(CacheKeyDictionary::requirementDetail($requirementId));
            Redis::del(CacheKeyDictionary::requirementProgress($requirementId));
            Redis::del(CacheKeyDictionary::requirementDashboardSummary($requirementId));
            Redis::del(CacheKeyDictionary::progressDashboardData($requirementId));

            // Reactividad global del Dashboard
            Redis::incr(CacheKeyDictionary::globalDashboardVersion());
            Cache::tags([CacheKeyDictionary::dashboardTag()])->flush();

            return $requirement;
        });
    }
}