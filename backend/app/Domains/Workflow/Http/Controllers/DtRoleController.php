<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Workflow\Services\DtRoleService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DtRoleController extends Controller
{
    public function __construct(
        private readonly DtRoleService $dtRoleService
    ) {}

    /**
     * GET /workflow/requirements/{id}/dt/roles-init
     * 
     * GESTIONAR ROLES DE DISEÑO TÉCNICO (DT) POR REQUERIMIENTO
     */
    public function index(string $requirementId): JsonResponse
    {
        $data = $this->dtRoleService->initializeRoles($requirementId);
        
        return response()->json($data, 200);
    }

    /**
     * PATCH /workflow/dt/roles/{role_id}/status
     * 
     * GESTIONAR CICLO DE VIDA DEL ROL DE DISEÑO TÉCNICO (CERRAR/ACTIVAR)
     */
    public function changeStatus(Request $request, string $roleId): JsonResponse
    {
        $request->validate([
            'action' => 'required|string|in:CLOSE,REOPEN'
        ]);

        $role = $this->dtRoleService->changeRoleStatus($roleId, $request->input('action'));
        
        return response()->json([
            'role_id' => $role->id,
            'new_status' => $role->status
        ], 200);
    }
}