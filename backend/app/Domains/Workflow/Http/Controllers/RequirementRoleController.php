<?php

namespace App\Domains\Workflow\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Workflow\Http\Requests\StoreRequirementRoleRequest;
use App\Domains\Workflow\Models\RequirementRole;
use App\Domains\Workflow\Services\RequirementRoleService;
use Illuminate\Http\JsonResponse;

class RequirementRoleController extends Controller
{
    public function __construct(
        private readonly RequirementRoleService $roleService
    ) {}

    /**
     * POST /api/requirements/{requirementId}/roles
     */
    public function store(StoreRequirementRoleRequest $request, string $requirementId): JsonResponse
    {
        // El framework Laravel inyectará el Requirement si usamos Route Model Binding,
        // pero validamos directamente aquí para mantener el flujo simple.
        $role = $this->roleService->createRole(
            $requirementId, 
            $request->validated(), 
            $request->user()?->id ?? 'system'
        );

        return response()->json([
            'message' => 'Registrado correctamente',
            'data' => $role
        ], 201);
    }


    /**
     * GET /api/workflow/requirements/{requirementId}/components-data
     */
    public function index(string $requirementId): JsonResponse
    {
        // Delegamos la consulta a la capa de servicios
        $roles = $this->roleService->getRolesByRequirement($requirementId);

        return response()->json([
            'message' => 'Roles obtenidos correctamente',
            'data' => $roles
        ], 200);
    }

    /**
     * PUT /api/roles/{roleId}
     */
    public function update(StoreRequirementRoleRequest $request, string $roleId): JsonResponse
    {
        $role = RequirementRole::with('requirement')->findOrFail($roleId);

        // Gatekeeper Estricto: Validación de inmutabilidad de la fase (Fase 4 del diagrama)
        // Bloqueamos la edición si la fase global ATF o DT ya cerraron formalmente.
        $blockedPhases = ['ATF_CLOSED', 'DT_CLOSED', 'CO_CLOSED', 'PI_CLOSED', 'CER_CLOSED', 'PAP_CLOSED'];
        if (in_array($role->requirement->current_phase, $blockedPhases)) {
            return response()->json([
                'message' => 'Acción denegada. La fase técnica del requerimiento se encuentra cerrada y es inmutable.'
            ], 403);
        }

        $updatedRole = $this->roleService->updateRole(
            $role, 
            $request->validated(), 
            $request->user()?->id ?? 'system'
        );

        return response()->json([
            'message' => 'Rol actualizado correctamente',
            'data' => $updatedRole
        ], 200);
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $this->roleService->deleteRole($id);
            return response()->json(['message' => 'Rol eliminado correctamente'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al procesar la solicitud'], 500);
        }
    }
}