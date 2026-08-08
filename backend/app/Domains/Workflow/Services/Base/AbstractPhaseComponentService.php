<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Services\Base;

use App\Domains\Audit\Services\AuditService;
use App\Domains\Workflow\Services\PhaseTransitionService;
use App\Domains\Workflow\Services\ProgressCalculationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Model;
use App\Domains\Core\Models\Requirement;

abstract class AbstractPhaseComponentService
{
    public function __construct(
        protected readonly AuditService $auditService,
        protected readonly PhaseTransitionService $phaseTransitionService,
        protected readonly ProgressCalculationService $progressService
    ) {}

    /**
     * Métodos abstractos que las clases hijas DEBEN implementar.
     * Esto define el "Contrato" de la fase.
     */
    abstract protected function getComponentModel(): string; // Ej: CorRole::class
    abstract protected function getCacheKeyPrefix(): string; // Ej: 'cor_roles_cache'
    abstract protected function getPhaseCode(): string;      // Ej: 'COR-C'
    abstract protected function getPhaseInitCode(): string;  // Ej: 'COR-I'

    /**
     * GESTIONAR CICLO DE VIDA DEL COMPONENTE (CERRAR/REABRIR)
     * Abstrae la lógica de cambio de estado de un Rol o Entregable.
     */
    public function changeComponentStatus(string $componentId, string $newStatus): Model
    {
        $modelClass = $this->getComponentModel();
        $component = $modelClass::findOrFail($componentId);
        
        return DB::transaction(function () use ($component, $newStatus) {
            $previousStatus = $component->status;
            
            // Validación genérica: No reabrir si la fase global ya avanzó
            if ($newStatus === 'IN_PROGRESS') {
                $reqPhase = DB::table('core.requirements')->where('id', $component->req_id ?? $component->requirement_id)->value('status');
                if ($reqPhase === $this->getPhaseCode()) {
                    abort(403, 'Acceso denegado: La fase global ya se encuentra cerrada.');
                }
            }

            $this->validateStatusTransition($component, $newStatus);

            $component->update(['status' => $newStatus]);
            
            // Auditoría genérica inyectando el modelo dinámico
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
            
            // Invalida la caché utilizando el prefijo dinámico
            $reqId = $component->req_id ?? $component->requirement_id;
            Cache::forget("req_{$reqId}_{$this->getCacheKeyPrefix()}_meta");

            return $component;
        });
    }

    /**
     * CIERRE GLOBAL DE LA FASE (HARD GATE)
     * Abstrae la validación de quórum, actualización de progreso y sellado.
     */
    public function closeGlobalPhase(string $requirementId): array
    {
        $modelClass = $this->getComponentModel();

        return DB::transaction(function () use ($requirementId, $modelClass) {
            // HARD GATE: Verificar que NO existan componentes en proceso
            $reqColumn = (new $modelClass)->getForeignKey(); // Detecta automáticamente si es req_id o requirement_id
            
            $openComponents = $modelClass::where($reqColumn, $requirementId)
                  ->where('status', '!=', 'CLOSED')
                  ->count();

            if ($openComponents > 0) {
                abort(422, 'Validación fallida: Todos los componentes deben estar en estado CERRADO para avanzar.');
            }

            $requirement = Requirement::findOrFail($requirementId);
            
            // Recálculo del Avance Global (Motor Polimórfico)
            $calculatedProgress = $this->progressService->calculateGlobalProgress($requirement);

            // Transición de Estado Maestro
            $requirement->update([
                'status' => $this->getPhaseCode(),
                'progress_percentage' => $calculatedProgress,
                'updated_at' => now()
            ]);

            // 🟢 Registro Histórico Transaccional
            $this->phaseTransitionService->recordTransition(
                $requirementId,
                $this->getPhaseCode(),
                (string) auth()->id(),
                'Cierre global exitoso de la subfase'
            );

            // 🟢 Limpieza de Caché y Sellado
            Cache::forget("req_{$requirementId}_{$this->getCacheKeyPrefix()}_meta");
            Cache::put("req_{$requirementId}_" . strtolower($this->getPhaseInitCode()) . "_congelado", true, now()->addDays(1));

            return [
                'success' => true,
                'message' => 'Fase cerrada con éxito.',
                'progress_percentage' => $calculatedProgress
            ];
        });
    }

    /**
     * Hook polimórfico para validaciones específicas antes del cambio de estado.
     * Las clases hijas (como CorRoleService) pueden sobrescribirlo.
     */
    protected function validateStatusTransition(Model $component, string $newStatus): void
    {
        // Por defecto no hace nada.
    }
}