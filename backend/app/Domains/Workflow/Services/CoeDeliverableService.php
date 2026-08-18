<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Services;

use App\Domains\Workflow\Services\Base\AbstractPhaseComponentService;
use App\Domains\Workflow\Traits\ManagesPhaseRegisters;
use App\Domains\Workflow\Models\CoeDeliverable;
use App\Domains\Workflow\Models\CoeActivity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Domains\Core\Dictionaries\CacheKeyDictionary; 

class CoeDeliverableService extends AbstractPhaseComponentService
{
    use ManagesPhaseRegisters;

    // =========================================================================
    // IMPLEMENTACIÓN DEL CONTRATO DEL SERVICIO BASE
    // =========================================================================
    protected function getComponentModel(): string { return CoeDeliverable::class; }
    
    protected function getCacheKeyPrefix(): string { return 'coe_deliverables'; } 
    
    protected function getPhaseCode(): string { return 'COE-C'; }
    protected function getPhaseInitCode(): string { return 'COE-I'; }

    protected function getRequiredPredecessorPhases(): array {
        return ['ATF-C']; // COE corre paralelo a DT y COR, solo depende de ATF
    }


    // =========================================================================
    // IMPLEMENTACIÓN DEL CONTRATO DEL TRAIT DE BITÁCORAS
    // =========================================================================
    protected function getRegisterModel(): string { return CoeActivity::class; }
    protected function getParentForeignKey(): string { return 'coe_deliverable_id'; }

    protected function countRegistersInRequirement(string $requirementId): int
    {
        return DB::table('workflow.coe_activities as ca') 
            ->join('workflow.coe_deliverables as cd', 'ca.coe_deliverable_id', '=', 'cd.id') 
            ->where('cd.req_id', $requirementId) 
            ->whereNull('ca.deleted_at')
            ->whereNull('cd.deleted_at')
            ->count();
    }

    // =========================================================================
    // MÉTODOS ESPECÍFICOS DE LA FASE COE (US31)
    // =========================================================================
    
    /**
     * CU-071: Inicializar y sincronizar entregables desde la tabla maestra (ATF) hacia COE
     */
    public function initializeDeliverables(string $requirementId): array
    {
        // 1. Verificación (Hard Gate): RN-COE-02 - Deben existir entregables maestros
        $masterDeliverables = DB::table('workflow.deliverables')
            ->where('requirement_id', $requirementId)
            ->whereNull('deleted_at')
            ->count();

        if ($masterDeliverables === 0) {
            abort(403, 'Acceso denegado: El requerimiento no posee entregables maestros definidos en su alcance inicial.');
        }

        $userId = auth()->id();
        
        // 2. Transición Masiva e Idempotente (RN-COE-03 y RN-COE-07)
        // Se inyecta directamente desde workflow.deliverables
        DB::statement("
            INSERT INTO workflow.coe_deliverables (
                id, deliverable_id, req_id, status, 
                created_by, updated_by, created_at, updated_at
            ) 
            SELECT 
                gen_random_uuid(), id, requirement_id, 'IN_PROGRESS', 
                ?, ?, NOW(), NOW() 
            FROM workflow.deliverables 
            WHERE requirement_id = ? AND deleted_at IS NULL
            ON CONFLICT (req_id, deliverable_id) DO NOTHING
        ", [$userId, $userId, $requirementId]);

        // 3. Recuperar datos con ordenamiento nativo utilizando el DICCIONARIO (RN-COE-10)
        $cacheKey = CacheKeyDictionary::phaseComponentsList($requirementId, $this->getPhaseInitCode());
        
        $deliverablesList = Cache::remember($cacheKey, 600, function () use ($requirementId) {
            return CoeDeliverable::with('masterDeliverable:id,name') 
                        ->where('req_id', $requirementId)
                        ->withCount('registers') 
                        ->get()
                        ->sortBy(function($deliverable) {
                            return $deliverable->masterDeliverable->name ?? '';
                        })
                        ->values() 
                        ->toArray();
        });

        return [
            'requirement_id' => $requirementId,
            'roles_list' => $deliverablesList 
        ];
    }

    // =========================================================================
    // VALIDACIONES POLIMÓRFICAS (HOOKS)
    // =========================================================================
    
    /**
     * RN-COE-32: Validación de Quórum Documental
     */
    protected function validateStatusTransition(\Illuminate\Database\Eloquent\Model $component, string $newStatus): void
    {
        if ($newStatus === 'CLOSED') {
            $count = DB::table('workflow.coe_activities')
                ->where('coe_deliverable_id', $component->id)
                ->whereNull('deleted_at') // Ignorar los eliminados lógicamente
                ->count();

            if ($count === 0) {
                abort(422, 'Validación fallida: No se puede cerrar un entregable sin evidencias o actividades registradas.');
            }
        }
    }

    protected function getRequirementColumn(): string 
    {
        return 'req_id'; // Adaptación específica para COE
    }
}