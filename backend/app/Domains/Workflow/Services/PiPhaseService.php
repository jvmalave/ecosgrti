<?php

namespace App\Domains\Workflow\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use App\Domains\Audit\Services\AuditService;
use Exception;

class PiPhaseService
{
    public function __construct(
        private readonly AuditService $auditService
    ) {}

    /**
     * Inicializa y promueve los roles hacia la fase PI (CU-040)
     */
    public function initializePiRoles(string $requirementId, string $userId): array
    {
        // ... (Tu código actual de initializePiRoles queda exactamente igual) ...
        
        // 1. HARD GATE (RN-PI-2): Validar precondición de acceso
        $closedCorRolesCount = DB::table('workflow.cor_roles')
            ->where('requirement_id', $requirementId)
            ->where('status', 'CLOSED')
            ->count();

        if ($closedCorRolesCount === 0) {
            throw new Exception("Acceso denegado: El requerimiento no posee roles cerrados en la fase de Construcción (COR).", 403);
        }

        // 2. PROMOCIÓN AUTOMÁTICA E IDEMPOTENTE (RN-PI-3 y RN-PI-8)
        DB::statement("
            INSERT INTO workflow.pi_roles (
                id, requirement_id, requirement_role_id, status, is_approved, created_at, updated_at, created_by 
            )
            SELECT 
                gen_random_uuid(), requirement_id, requirement_role_id, 'IN_PROGRESS', false, NOW(), NOW(), ?          
            FROM workflow.cor_roles
            WHERE requirement_id = ? AND status = 'CLOSED' 
            ON CONFLICT (requirement_id, requirement_role_id) DO NOTHING
        ", [
            $userId,        
            $requirementId  
        ]);

        // 3. RECUPERACIÓN AISLADA Y ORDENADA (RN-PI-5, RN-PI-7 y RN-PI-11)
        $rolesDataset = \App\Domains\Workflow\Models\PiRole::where('workflow.pi_roles.requirement_id', $requirementId)
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

        // 5. CACHÉ EN REDIS (Optimización de Memoria)
        $cacheKey = "req_{$requirementId}_pi_roles_meta";
        Redis::setex($cacheKey, 600, json_encode($rolesDataset));

        return [
            'requirement_id' => $requirementId,
            'roles_list'     => $rolesDataset
        ];
    }

}