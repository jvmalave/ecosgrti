<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Http\Controllers;

use App\Domains\Workflow\Http\Controllers\Base\AbstractPhaseController;
use App\Domains\Workflow\Services\CorRoleService;
use Illuminate\Http\JsonResponse;

class CorRoleController extends AbstractPhaseController
{
    public function __construct(CorRoleService $phaseService)
    {
        $this->phaseService = $phaseService;
    }

    /**
     * GET /workflow/requirements/{id}/cor/roles-init
     * 
     * GESTIONAR ROLES DE CONSTRUCCIÓN (COR) POR REQUERIMIENTO
     */
    public function index(string $requirementId): JsonResponse
    {
        $data = $this->phaseService->initializeRoles($requirementId);
        return response()->json($data, 200);
    }
    
    // Los métodos changeStatus() y closePhase() se heredan automáticamente de AbstractPhaseController
}