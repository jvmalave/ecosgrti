<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Workflow\Http\Requests\StoreDeliverableRequest;
use App\Domains\Workflow\Models\Deliverable;
use App\Domains\Workflow\Services\DeliverableService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeliverableController extends Controller
{
    public function __construct(
        private readonly DeliverableService $deliverableService
    ) {}

    /**
     * POST /workflow/requirements/{requirementId}/deliverables
     */
    public function store(StoreDeliverableRequest $request, string $requirementId): JsonResponse
    {
        // El StoreDeliverableRequest ya se encargó de verificar la regla de Unicidad
        $deliverable = $this->deliverableService->createDeliverable(
            $requirementId, 
            $request->validated(), 
            $request->user()?->id ?? 'system'
        );

        return response()->json([
            'message' => 'Registrado correctamente',
            'data'    => $deliverable
        ], 201);
    }

    /**
     * PUT /workflow/components/deliverables/{deliverableId}
     * Mantenemos la Inmutabilidad del Vínculo omitiendo requirementId
     */
    public function update(StoreDeliverableRequest $request, string $deliverableId): JsonResponse
    {
        $deliverable = Deliverable::findOrFail($deliverableId);

        // TODO: (US28) Integrar validación de Hard Gate (Fase cerrada)
        
        $updatedDeliverable = $this->deliverableService->updateDeliverable(
            $deliverable, 
            $request->validated(), 
            $request->user()?->id ?? 'system'
        );

        return response()->json([
            'message' => 'Entregable actualizado correctamente',
            'data'    => $updatedDeliverable
        ], 200);
    }
}