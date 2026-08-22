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
     */
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
     */
    public function store(StoreDtRegisterRequest $request, string $requirementId, string $roleId): JsonResponse
    {
        DtRole::findOrFail($roleId); // Validación de existencia en BD 
        
        // 🟢 Se adapta al llamado polimórfico: storeRegister(string $parentId, array $data, string $requirementId)
        $register = $this->dtRegisterService->storeRegister($roleId, $request->validated(), $requirementId);
        
        return response()->json($register, 201);
    }

    /**
     * PUT /workflow/dt/registers/{reg_id}
     */
    public function update(UpdateDtRegisterRequest $request, string $registerId, string $roleId): JsonResponse
    {
        $register = DtRegister::findOrFail($registerId);
        $updatedRegister = $this->dtRegisterService->updateRegister($register, $request->validated());
        
        return response()->json($updatedRegister, 200);
    }

    /**
     * DELETE /workflow/dt/registers/{reg_id}
     */
    public function destroy(string $registerId, string $roleId): JsonResponse
    {
        $register = DtRegister::findOrFail($registerId);
        $this->dtRegisterService->deleteRegister($register);
        
        return response()->json(['message' => 'Registro técnico eliminado exitosamente.'], 200);
    }
}