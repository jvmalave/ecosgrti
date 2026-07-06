<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Workflow\Http\Requests\StoreRequirementRoleRequest;
use App\Domains\Workflow\Models\RequirementRole;
use App\Domains\Workflow\Services\RequirementRoleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RequirementRoleController extends Controller
{
    public function __construct(
        private readonly RequirementRoleService $roleService
    ) {}

    /**
     * POST /workflow/requirements/{requirementId}/roles
     */
    public function store(StoreRequirementRoleRequest $request, string $requirementId): JsonResponse
    {
        // El FormRequest ya validó la unicidad y sanitizó la entrada
        $role = $this->roleService->createRole(
            $requirementId, 
            $request->validated(), 
            $request->user()?->id ?? 'system' // Fallback por si no hay usuario autenticado en pruebas
        );

        return response()->json([
            'message' => 'Registrado correctamente', // Cumpliendo el AC del diagrama
            'data' => $role
        ], 201);
    }

    /**
     * PUT /workflow/components/roles/{roleId}
     * Nota: En la Fase 4 el requirement_id está omitido para preservar la Inmutabilidad del Vínculo
     */
    public function update(StoreRequirementRoleRequest $request, string $roleId): JsonResponse
    {
        $role = RequirementRole::findOrFail($roleId);

        // TODO: Aquí integraremos la validación del "Gatekeeper" de la Fase 4 (CU-026)
        // para asegurar que la fase no esté cerrada.

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
}