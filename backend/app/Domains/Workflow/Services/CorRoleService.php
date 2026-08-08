<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Services;

use App\Domains\Workflow\Services\Base\AbstractPhaseComponentService;
use App\Domains\Workflow\Traits\ManagesPhaseRegisters;
use App\Domains\Workflow\Models\CorRole;
use App\Domains\Workflow\Models\CorRegister;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class CorRoleService extends AbstractPhaseComponentService
{
    use ManagesPhaseRegisters;

    // =========================================================================
    // IMPLEMENTACIÓN DEL CONTRATO DEL SERVICIO BASE
    // =========================================================================
    protected function getComponentModel(): string { return CorRole::class; }
    protected function getCacheKeyPrefix(): string { return 'cor_roles'; }
    protected function getPhaseCode(): string { return 'COR-C'; }
    protected function getPhaseInitCode(): string { return 'COR-I'; }

    // =========================================================================
    // IMPLEMENTACIÓN DEL CONTRATO DEL TRAIT DE BITÁCORAS
    // =========================================================================
    protected function getRegisterModel(): string { return CorRegister::class; }
    protected function getParentForeignKey(): string { return 'role_id'; }

    protected function countRegistersInRequirement(string $requirementId): int
    {
        return DB::table('workflow.cor_registers as cr')
            ->join('workflow.cor_roles as cro', 'cr.role_id', '=', 'cro.id')
            ->where('cro.requirement_id', $requirementId) // ERD: requirement_id
            ->whereNull('cr.deleted_at') // Excluye registros en papelera (Soft Deletes)
            ->whereNull('cro.deleted_at')
            ->count();
    }

    // =========================================================================
    // MÉTODOS ESPECÍFICOS DE LA FASE COR (US30)
    // =========================================================================
    
    /**
     * CU-034: Inicializar y sincronizar roles cerrados desde DT hacia COR
     */
    // public function initializeRoles(string $requirementId): array
    // {
    //     // 1. Verificación (Hard Gate): Deben existir roles cerrados en DT
    //     $dtClosedRoles = DB::table('workflow.dt_roles')
    //         ->where('requirement_id', $requirementId)
    //         ->where('status', 'CLOSED')
    //         ->whereNull('deleted_at')
    //         ->count();

    //     if ($dtClosedRoles === 0) {
    //         abort(403, 'Acceso denegado: El requerimiento no posee roles cerrados en Diseño Técnico.');
    //     }

    //     // 2. Transición Masiva e Idempotente (con inyección de autoría)
    //     $userId = auth()->id();
        
    //     DB::statement("
    //         INSERT INTO workflow.cor_roles (
    //             id, requirement_role_id, requirement_id, name, status, 
    //             created_by, updated_by, created_at, updated_at
    //         ) 
    //         SELECT 
    //             gen_random_uuid(), requirement_role_id, requirement_id, name, 'IN_PROGRESS', 
    //             ?, ?, NOW(), NOW() 
    //         FROM workflow.dt_roles 
    //         WHERE requirement_id = ? AND status = 'CLOSED' AND deleted_at IS NULL
    //         ON CONFLICT (requirement_role_id) DO NOTHING
    //     ", [$userId, $userId, $requirementId]);

    //     // 3. Recuperar datos con ordenamiento nativo
    //     $cacheKey = "req_{$requirementId}_cor_roles_meta";
        
    //     $rolesList = Cache::remember($cacheKey, 600, function () use ($requirementId) {
    //         return CorRole::where('requirement_id', $requirementId)
    //                     ->withCount('registers') // Esto asume que el método registers() en el modelo no cuenta los borrados
    //                     ->orderBy('name', 'asc')
    //                     ->get()
    //                     ->toArray();
    //     });

    //     return [
    //         'requirement_id' => $requirementId,
    //         'roles_list' => $rolesList
    //     ];
    // }

    public function initializeRoles(string $requirementId): array
    {
        // 1. Verificación (Hard Gate): Deben existir roles cerrados en DT
        $dtClosedRoles = DB::table('workflow.dt_roles')
            ->where('requirement_id', $requirementId)
            ->where('status', 'CLOSED')
            // Eliminamos la validación deleted_at porque dt_roles no posee esa columna
            ->count();

        if ($dtClosedRoles === 0) {
            abort(403, 'Acceso denegado: El requerimiento no posee roles cerrados en Diseño Técnico.');
        }

        $userId = auth()->id();
        
        // 2. Transición Masiva e Idempotente
        DB::statement("
            INSERT INTO workflow.cor_roles (
                id, requirement_role_id, requirement_id, name, status, 
                created_by, updated_by, created_at, updated_at
            ) 
            SELECT 
                gen_random_uuid(), requirement_role_id, requirement_id, name, 'IN_PROGRESS', 
                ?, ?, NOW(), NOW() 
            FROM workflow.dt_roles 
            WHERE requirement_id = ? AND status = 'CLOSED' 
            ON CONFLICT (requirement_role_id) DO NOTHING
        ", [$userId, $userId, $requirementId]); // Eliminamos "AND deleted_at IS NULL" de la consulta SQL

        // 3. Recuperar datos con ordenamiento nativo
        $cacheKey = "req_{$requirementId}_cor_roles_meta";
        
        $rolesList = Cache::remember($cacheKey, 600, function () use ($requirementId) {
            return CorRole::where('requirement_id', $requirementId)
                        ->withCount('registers')
                        ->orderBy('name', 'asc')
                        ->get()
                        ->toArray();
        });

        return [
            'requirement_id' => $requirementId,
            'roles_list' => $rolesList
        ];
    }

    // =========================================================================
    // VALIDACIONES POLIMÓRFICAS (HOOKS)
    // =========================================================================
    
    protected function validateStatusTransition(\Illuminate\Database\Eloquent\Model $component, string $newStatus): void
    {
        if ($newStatus === 'CLOSED') {
            $count = DB::table('workflow.cor_registers')
                ->where('role_id', $component->id)
                ->whereNull('deleted_at') // Ignorar los eliminados lógicamente
                ->count();

            if ($count === 0) {
                abort(422, 'Validación fallida: No se puede cerrar un rol sin actividades en su bitácora técnica.');
            }
        }
    }
}