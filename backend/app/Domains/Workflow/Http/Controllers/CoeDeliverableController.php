<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Http\Controllers;

use App\Domains\Workflow\Http\Controllers\Base\AbstractPhaseController;
use App\Domains\Workflow\Services\CoeDeliverableService;
use Illuminate\Http\JsonResponse;

class CoeDeliverableController extends AbstractPhaseController
{
    public function __construct(CoeDeliverableService $phaseService)
    {
        $this->phaseService = $phaseService;
    }

    /**
     * GET /workflow/requirements/{id}/coe/deliverables-init
     * 
     * GESTIONAR INICIALIZACIÓN DE ENTREGABLES EN CONSTRUCCIÓN (COE)
     */
    public function index(string $requirementId): JsonResponse
    {
        // Invoca la sincronización desde ATF hacia COE y retorna la lista
        $data = $this->phaseService->initializeDeliverables($requirementId);
        return response()->json($data, 200);
    }
    
    // Los métodos changeStatus() y closePhase() se heredan automáticamente de AbstractPhaseController
}