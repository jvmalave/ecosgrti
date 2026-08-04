<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Workflow\Services\DtRegisterService;
use App\Domains\Workflow\Models\DtRole;
use App\Domains\Workflow\Models\DtRegister;
use App\Domains\Workflow\Http\Requests\StoreDtRegisterRequest;
use App\Domains\Workflow\Http\Requests\UpdateDtRegisterRequest;
use Illuminate\Http\JsonResponse;

class DtRegisterController extends Controller
{
    public function __construct(
        private readonly DtRegisterService $dtRegisterService
    ) {}

    /**
     * GET /workflow/dt/roles/{role_id}/registers
     * 
     * CONSULTA DE LISTA DE REGISTROS DE DISEÑO TÉCNICO POR ROL DEL REQUERIMIENTO
     * */
    public function index(string $roleId): JsonResponse
    {
        $role = DtRole::findOrFail($roleId);
        $registers = $this->dtRegisterService->getRegistersByRole($roleId);
        
        return response()->json([
            'id_req' => $role->requirement_id,
            'nombre_rol' => $role->name,
            'estado_rol' => $role->status,
            'registros' => $registers
        ], 200);
    }

    /**
     * POST /workflow/requirements/{id}/dt/roles/{role_id}/registers
     * 
     * AGREGAR REGISTRO DE DISEÑO TÉCNICO A ROL DEL REQUERIMIENTO
     */
    public function store(StoreDtRegisterRequest $request, string $requirementId, string $roleId): JsonResponse
    {
        $role = DtRole::findOrFail($roleId);
        $register = $this->dtRegisterService->storeRegister($role, $request->validated());
        
        return response()->json($register, 201);
    }

    /**
     * PUT /workflow/dt/registers/{reg_id}
     * 
     * ACTUALIZAR REGISTRO DE DISEÑO TÉCNICO DE ROL DEL REQUERIMIENTO
     */
    public function update(UpdateDtRegisterRequest $request, string $registerId): JsonResponse
    {
        $register = DtRegister::findOrFail($registerId);
        $updatedRegister = $this->dtRegisterService->updateRegister($register, $request->validated());
        
        return response()->json($updatedRegister, 200);
    }

    /**
     * DELETE /workflow/dt/registers/{reg_id}
     * 
     * ELIMINAR REGISTRO DE DISEÑO TÉCNICO DE ROL DEL REQUERIMIENTO
     */
    public function destroy(string $registerId): JsonResponse
    {
        $register = DtRegister::findOrFail($registerId);
        $this->dtRegisterService->deleteRegister($register);
        
        return response()->json(['message' => 'Registro técnico eliminado exitosamente.'], 200);
    }
}