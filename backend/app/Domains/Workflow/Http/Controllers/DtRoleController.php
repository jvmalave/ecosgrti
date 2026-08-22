<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Http\Controllers;

use App\Domains\Workflow\Http\Controllers\Base\AbstractPhaseController;
use App\Domains\Workflow\Services\DtRoleService;
use Illuminate\Http\JsonResponse;

class DtRoleController extends AbstractPhaseController
{
    public function __construct(DtRoleService $dtRoleService)
    {
        // Vincula el servicio de DT a la propiedad protegida de la clase abstracta padre
        $this->phaseService = $dtRoleService;
    }

    /**
     * GET /workflow/requirements/{id}/dt/roles-init
     * * GESTIONAR ROLES DE DISEÑO TÉCNICO (DT) POR REQUERIMIENTO
     * Este método permanece aquí por ser exclusivo del flujo de inicialización de DT.
     */
    public function index(string $requirementId): JsonResponse
    {
        $data = $this->phaseService->initializeRoles($requirementId);
        return response()->json($data, 200);
    }

    // =====================================================================
    // NOTA ARQUITECTÓNICA:
    // Al heredar de AbstractPhaseController, estas operaciones genéricas 
    // y atómicas fluyen de manera polimórfica hacia la clase base.
    // =====================================================================
}