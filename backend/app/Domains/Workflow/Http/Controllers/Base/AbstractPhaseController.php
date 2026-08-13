<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Http\Controllers\Base;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Domains\Workflow\Services\Base\AbstractPhaseComponentService;

abstract class AbstractPhaseController extends Controller
{
    /**
     * El servicio inyectado por la clase hija (ej. CorRoleService o CoeDeliverableService)
     */
    protected AbstractPhaseComponentService $phaseService;

    /**
     * PATCH /workflow/{fase}/components/{id}/status
     * GESTIONAR CICLO DE VIDA DEL COMPONENTE
     */
    public function changeStatus(Request $request, string $componentId): JsonResponse
    {
        $request->validate([
            'new_status' => 'required|string|in:CLOSED,IN_PROGRESS'
        ]);

        $component = $this->phaseService->changeComponentStatus($componentId, $request->input('new_status'));
        
        return response()->json([
            'component_id' => $component->id,
            'new_status' => $component->status
        ], 200);
    }

    /**
     * POST /workflow/requirements/{id}/finalize-{fase}
     * CIERRE GLOBAL DE LA FASE
     */
    public function closePhase(string $requirementId): JsonResponse
    {
        $result = $this->phaseService->closeGlobalPhase($requirementId);
        
        return response()->json($result, 200);
    }
}