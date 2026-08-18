<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Services\Base;

use App\Domains\Audit\Services\AuditService;
use App\Domains\Workflow\Services\PhaseTransitionService;
use App\Domains\Workflow\Services\ProgressCalculationService;
use App\Domains\Core\Models\Requirement;
use App\Domains\Core\Dictionaries\CacheKeyDictionary; 
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis; 
use Illuminate\Database\Eloquent\Model;

abstract class AbstractPhaseComponentService
{
    public function __construct(
        protected readonly AuditService $auditService,
        protected readonly PhaseTransitionService $phaseTransitionService,
        protected readonly ProgressCalculationService $progressService
    ) {}

    abstract protected function getComponentModel(): string; 
    abstract protected function getCacheKeyPrefix(): string; 
    abstract protected function getPhaseCode(): string;      
    abstract protected function getPhaseInitCode(): string;  

    /**
     * GESTIONAR CICLO DE VIDA DEL COMPONENTE (CERRAR/REABRIR)
     */
    public function changeComponentStatus(string $componentId, string $newStatus): Model
    {
        $modelClass = $this->getComponentModel();
        $component = $modelClass::findOrFail($componentId);
        
        return DB::transaction(function () use ($component, $newStatus) {
            $previousStatus = $component->status;
            
            if ($newStatus === 'IN_PROGRESS') {
                $reqPhase = DB::table('core.requirements')->where('id', $component->req_id ?? $component->requirement_id)->value('status');
                if ($reqPhase === $this->getPhaseCode()) {
                    abort(403, 'Acceso denegado: La fase global ya se encuentra cerrada.');
                }
            }

            $this->validateStatusTransition($component, $newStatus);

            $component->update(['status' => $newStatus]);
            
            $this->auditService->logModelChange(
                'CHANGE_COMPONENT_STATUS',
                "Cambio de estado en componente de la fase " . $this->getPhaseInitCode(),
                [
                    'record_id' => $component->id, 
                    'previous_status' => $previousStatus,
                    'new_status' => $newStatus
                ],
                auth()->id()
            );
            
            $reqId = $component->req_id ?? $component->requirement_id;

            // =====================================================================
            // 🟢 DOBLE INVALIDACIÓN Y REACTIVIDAD DEL FRONTEND
            // =====================================================================
            
            $listKey = CacheKeyDictionary::phaseComponentsList($reqId, $this->getPhaseInitCode());
            Cache::forget($listKey);
            Redis::del($listKey);

            $fallbackKey = "req_{$reqId}_{$this->getCacheKeyPrefix()}_meta";
            Cache::forget($fallbackKey);
            Redis::del($fallbackKey);

            // 🟢 CRÍTICO: Disparar la reactividad en Angular para mover el rol de tabla
            Redis::incr(CacheKeyDictionary::globalDashboardVersion());

            return $component;
        });
    }

   protected function getRequiredPredecessorPhases(): array 
    {
        return []; 
    }

    /**
     * CIERRE GLOBAL DE LA FASE (HARD GATE)
     */
    public function closeGlobalPhase(string $requirementId): array
    {
        $modelClass = $this->getComponentModel();

        return DB::transaction(function () use ($requirementId, $modelClass) {
            
            // =====================================================================
            //  VALIDACION DE FASE PREVIAS CERRADAS 
            // =====================================================================
            $predecessors = $this->getRequiredPredecessorPhases();
            
            if (!empty($predecessors)) {
                $closedPhases = DB::table('workflow.requirement_phase_history')
                    ->where('requirement_id', $requirementId)
                    ->whereIn('phase_status_code', $predecessors)
                    ->pluck('phase_status_code')
                    ->toArray();

                $missing = array_diff($predecessors, $closedPhases);
                
                if (!empty($missing)) {
                    abort(422, 'Validación fallida: No se puede cerrar esta fase porque tienes fases previas aún están abiertas: ' . implode(', ', $missing));
                }
            }
            // =====================================================================
            // VALIDACIÓN DE COMPONENTES INTERNOS DE LA FASE ACTUAL
            // =====================================================================
            $reqColumn = $this->getRequirementColumn();
            
            $openComponents = $modelClass::where($reqColumn, $requirementId)
                  ->where('status', '!=', 'CLOSED')
                  ->count();

            if ($openComponents > 0) {
                abort(422, 'Validación fallida: Todos los componentes deben estar en estado CERRADO para avanzar.');
            }

            $requirement = Requirement::findOrFail($requirementId);
            
            $this->phaseTransitionService->recordTransition(
                $requirementId,
                $this->getPhaseCode(),
                (string) auth()->id(),
                'Cierre global exitoso de la subfase'
            );

            $calculatedProgress = $this->progressService->calculateGlobalProgress($requirement);

            $isVanguard = $this->phaseTransitionService->isVanguardStatus($this->getPhaseCode(), $requirement->status);

            $updateData = [
                'progress_percentage' => $calculatedProgress,
                'updated_at' => now()
            ];

            if ($isVanguard) {
                $updateData['status'] = $this->getPhaseCode();
                $requirement->status = $this->getPhaseCode(); 
            }

            $requirement->update($updateData);

            $this->auditService->logModelChange(
                'CLOSE_GLOBAL_PHASE',
                "Cierre global exitoso de la fase " . $this->getPhaseInitCode() . ($isVanguard ? " (Nueva Vanguardia)" : " (Proceso Paralelo)"),
                [
                    'requirement_id' => $requirementId,
                    'new_status' => $requirement->status, 
                    'progress_reached' => $calculatedProgress,
                    'is_vanguard_update' => $isVanguard
                ],
                (string) auth()->id(),
                $requirementId
            );
            // =========================================================================
            // DESTRUCCIÓN DEL CACHÉ (DOBLE INVALIDACIÓN)
            // =========================================================================
            $phasePrefix = $this->getPhaseInitCode(); 

            $keysToPurge = [
                CacheKeyDictionary::requirementProgress($requirementId),
                CacheKeyDictionary::phaseComponentsList($requirementId, $phasePrefix),
                "req_{$requirementId}_{$this->getCacheKeyPrefix()}_meta",
                CacheKeyDictionary::requirementDetail($requirementId),
                CacheKeyDictionary::requirementDashboardSummary($requirementId),
                CacheKeyDictionary::progressDashboardData($requirementId)
            ];

            // Purga en ambos drivers por seguridad
            foreach ($keysToPurge as $key) {
                Cache::forget($key);
                Redis::del($key);
            }
            
            Cache::put(
                CacheKeyDictionary::phaseFrozenFlag($requirementId, $phasePrefix), 
                true, 
                now()->addDays(1)
            );

            if (config('cache.default') === 'redis') {
                Cache::tags([CacheKeyDictionary::dashboardTag()])->flush();
            }
            Redis::incr(CacheKeyDictionary::globalDashboardVersion());

            return [
                'success' => true,
                'message' => 'Fase cerrada con éxito.',
                'progress_percentage' => $calculatedProgress
            ];
        });
    }

    protected function validateStatusTransition(Model $component, string $newStatus): void {}

    protected function getRequirementColumn(): string 
    {
        return 'requirement_id'; 
    }
}