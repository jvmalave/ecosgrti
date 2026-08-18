<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Http\Controllers;

use App\Domains\Workflow\Http\Controllers\Base\AbstractPhaseController;
use App\Domains\Workflow\Services\PiRoleService;
use Illuminate\Http\JsonResponse;

class PiRoleController extends AbstractPhaseController
{
    public function __construct(PiRoleService $piRoleService)
    {
        // Vincula el motor específico de Pruebas Integrales 
        // a la propiedad protegida de la clase abstracta padre.
        $this->phaseService = $piRoleService;
    }

    /**
     * GET /workflow/requirements/{id}/pi/roles-init
     * * GESTIONAR INICIALIZACIÓN DE ROLES EN PRUEBAS INTEGRALES (PI)
     */
    public function index(string $requirementId): JsonResponse
    {
        $data = $this->phaseService->initializeRoles($requirementId);
        return response()->json($data, 200);
    }
}