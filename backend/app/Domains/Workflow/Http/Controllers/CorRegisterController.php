<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Workflow\Services\CorRoleService;
use App\Domains\Workflow\Models\CorRole;
use App\Domains\Workflow\Models\CorRegister;
use App\Domains\Workflow\Http\Requests\StoreCorRegisterRequest;
use App\Domains\Workflow\Http\Requests\UpdateCorRegisterRequest;
use Illuminate\Http\JsonResponse;

class CorRegisterController extends Controller
{
    public function __construct(
        private readonly CorRoleService $corRoleService
    ) {}

    /**
     * GET /workflow/cor/roles/{role_id}/registers
     * 
     * CONSULTA DE LISTA DE REGISTROS DE CONSTRUCCIÓN POR ROL
     */
    public function index(string $roleId): JsonResponse
    {
        $role = CorRole::findOrFail($roleId);
        
        // Recuperamos los registros activos ordenados por fecha de creación
        $registers = CorRegister::where('role_id', $roleId)
                                ->orderBy('created_at', 'asc')
                                ->get();
        
        return response()->json([
            'id_req' => $role->requirement_id,
            'nombre_rol' => $role->name,
            'estado_rol' => $role->status,
            'registros' => $registers
        ], 200);
    }

    /**
     * POST /workflow/requirements/{id}/cor/roles/{role_id}/registers
     * 
     * AGREGAR REGISTRO A ROL EN LA FASE COR
     */
    public function store(StoreCorRegisterRequest $request, string $requirementId, string $roleId): JsonResponse
    {
        $register = $this->corRoleService->storeRegister($roleId, $request->validated(), $requirementId);
        
        return response()->json($register, 201);
    }

    /**
     * PUT /workflow/cor/registers/{reg_id}/roles/{role_id}
     * 
     * ACTUALIZAR REGISTRO DE ROL EN LA FASE COR
     */
    public function update(UpdateCorRegisterRequest $request, string $regId, string $roleId): JsonResponse
    {
        $register = $this->corRoleService->updateRegister($regId, $request->validated(), $roleId);
        
        return response()->json($register, 200);
    }

    /**
     * DELETE /workflow/cor/registers/{reg_id}/roles/{role_id}
     * 
     * ELIMINAR REGISTRO DE ROL EN LA FASE COR
     */
    
    public function destroy(string $regId, string $roleId): JsonResponse
    {
        $this->corRoleService->deleteRegister($regId, $roleId);
        
        return response()->json(['message' => 'Registro técnico eliminado exitosamente.'], 200);
    }
}