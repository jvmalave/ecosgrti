<?php

namespace App\Domains\Workflow\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Workflow\Models\Deliverable;
use App\Domains\Workflow\Services\DeliverableService; // Suponiendo que este servicio ya fue creado
use Illuminate\Http\JsonResponse;
use App\Domains\Workflow\Http\Requests\StoreDeliverableRequest;

class DeliverableController extends Controller
{
    public function __construct(
        private readonly DeliverableService $deliverableService
    ) {}

    /**
     * GET /api/workflow/requirements/{requirementId}/deliverables
     */
    
    public function index(string $requirementId): JsonResponse
    {
        // Ahora el servicio garantiza retornar un array
        $deliverables = $this->deliverableService->getDeliverablesByRequirement($requirementId);

        return response()->json([
            'message' => 'Entregables obtenidos correctamente',
            'data' => $deliverables
        ], 200);
    }

    /**
     * POST /api/workflow/requirements/{requirementId}/deliverables
     */
    public function store(StoreDeliverableRequest $request, string $requirementId): JsonResponse
    {
        // Nota: Implementar StoreDeliverableRequest aquí
        $deliverable = $this->deliverableService->createDeliverable(
            $requirementId, 
            $request->validated(), 
            $request->user()?->id ?? 'system'
        );

        return response()->json([
            'message' => 'Entregable registrado correctamente',
            'data' => $deliverable
        ], 201);
    }

    /**
     * PUT /api/deliverables/{deliverableId}
     */
    public function update(StoreDeliverableRequest $request, string $deliverableId): JsonResponse
    {
        $deliverable = Deliverable::with('requirement')->findOrFail($deliverableId);

        // Gatekeeper Estricto: Validación de inmutabilidad (Regla aplicada también en RequirementRoleController)
        $blockedPhases = ['ATF_CLOSED', 'DT_CLOSED', 'CO_CLOSED', 'PI_CLOSED', 'CER_CLOSED', 'PAP_CLOSED'];
        if (in_array($deliverable->requirement->current_phase, $blockedPhases)) {
            return response()->json([
                'message' => 'Acción denegada. La fase técnica del requerimiento se encuentra cerrada y es inmutable.'
            ], 403);
        }

        $updated = $this->deliverableService->updateDeliverable(
            $deliverable, 
            $request->validated(), 
            $request->user()?->id ?? 'system'
        );

        return response()->json([
            'message' => 'Entregable actualizado correctamente',
            'data' => $updated
        ], 200);
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $this->deliverableService->deleteDeliverable($id);
            return response()->json(['message' => 'Entregable eliminado correctamente'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al procesar la solicitud: ' . $e->getMessage()], 500);
        }
    }
}