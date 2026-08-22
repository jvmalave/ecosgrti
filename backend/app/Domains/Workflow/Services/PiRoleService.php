<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Services;

use App\Domains\Workflow\Models\PiRole;
use App\Domains\Workflow\Services\Base\AbstractPhaseComponentService;
use App\Domains\Core\Dictionaries\CacheKeyDictionary;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Model;


class PiRoleService extends AbstractPhaseComponentService
{
    /**
     * Define el modelo que representa el componente en esta fase.
     */
    protected function getComponentModel(): string {
        return PiRole::class;
    }

    /**
     * Define el prefijo utilizado para operaciones de fallback en caché.
     */
    protected function getCacheKeyPrefix(): string {
        return 'pi_roles';
    }

    /**
     * Define el código de estado maestro que se aplicará al cerrar toda la fase.
     */
    protected function getPhaseCode(): string {
        return 'PI-C'; 
    }

    /**
     * Define el código inicial o prefijo de la fase.
     */
    protected function getPhaseInitCode(): string {
        return 'PI-I'; 
    }

    /**
     * Define las fases predecesoras de esta fase.
     */
    protected function getRequiredPredecessorPhases(): array {
        return ['ATF-C', 'DT-C', 'COR-C']; 
    }

    // =========================================================================
    // MÉTODO DE INICIALIZACIÓN UNIFICADO (SIMETRÍA CON COR Y COE)
    // =========================================================================
    
    /**
     * Inicializa y promueve de forma idempotente los roles desde COR hacia PI
     */
    public function initializeRoles(string $requirementId): array
    {
        // 1. HARD GATE: Validar precondición de acceso (Roles cerrados en COR)
        $closedCorRolesCount = DB::table('workflow.cor_roles')
            ->where('requirement_id', $requirementId)
            ->where('status', 'CLOSED')
            ->count();

        if ($closedCorRolesCount === 0) {
            abort(403, 'Acceso denegado: El requerimiento no posee roles cerrados en la fase de Construcción (COR).');
        }

        $userId = auth()->id();

        // 2. PROMOCIÓN AUTOMÁTICA E IDEMPOTENTE
        DB::statement("
            INSERT INTO workflow.pi_roles (
                id, requirement_id, requirement_role_id, status, is_approved, created_at, updated_at, created_by 
            )
            SELECT 
                gen_random_uuid(), requirement_id, requirement_role_id, 'IN_PROGRESS', false, NOW(), NOW(), ?          
            FROM workflow.cor_roles
            WHERE requirement_id = ? AND status = 'CLOSED' 
            ON CONFLICT (requirement_id, requirement_role_id) DO NOTHING
        ", [$userId, $requirementId]);

        // 3. RECUPERACIÓN CON DICCIONARIO DE CACHÉ ESTÁNDAR
        $cacheKey = CacheKeyDictionary::phaseComponentsList($requirementId, $this->getPhaseInitCode());

        $rolesDataset = Cache::remember($cacheKey, 600, function () use ($requirementId) {
            return PiRole::where('workflow.pi_roles.requirement_id', $requirementId)
                ->join('workflow.requirements_roles as rr', 'workflow.pi_roles.requirement_role_id', '=', 'rr.id')
                ->select(
                    'workflow.pi_roles.id',
                    'workflow.pi_roles.requirement_id',
                    'workflow.pi_roles.requirement_role_id',
                    'workflow.pi_roles.status',
                    'workflow.pi_roles.is_approved',
                    'rr.role_name as name' 
                )
                ->withCount('registers') 
                ->orderBy('rr.role_name', 'asc')
                ->get()
                ->toArray();
        });

        return [
            'requirement_id' => $requirementId,
            'roles_list'     => $rolesDataset
        ];
    }

    /**
     * HOOK POLIMÓRFICO: Reglas de negocio específicas para Pruebas Integrales.
     */
    protected function validateStatusTransition(Model $component, string $newStatus): void
    {
        if ($newStatus === 'CLOSED') {
            if (!$component->is_approved) {
                abort(422, 'Validación fallida: El rol no puede ser cerrado porque no cuenta con un acta de aprobación funcional registrada.');
            }
        }
    }
}