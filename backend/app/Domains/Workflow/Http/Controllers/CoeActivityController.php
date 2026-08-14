<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Workflow\Services\CoeDeliverableService;
use App\Domains\Workflow\Models\CoeDeliverable;
use App\Domains\Workflow\Models\CoeActivity;
use App\Domains\Workflow\Http\Requests\StoreCoeActivityRequest;
use App\Domains\Workflow\Http\Requests\UpdateCoeActivityRequest;
use Illuminate\Http\JsonResponse;

class CoeActivityController extends Controller
{
    public function __construct(
        private readonly CoeDeliverableService $coeDeliverableService
    ) {}

    /**
     * GET /workflow/coe/deliverables/{deliverable_id}/activities
     * 
     * CONSULTA DE LISTA DE ACTIVIDADES (EVIDENCIAS) POR ENTREGABLE
     */
    public function index(string $deliverableId): JsonResponse
    {
        $deliverable = CoeDeliverable::with('masterDeliverable')->findOrFail($deliverableId);
        
        // Recuperamos los registros activos ordenados por fecha de creación
        $activities = CoeActivity::where('coe_deliverable_id', $deliverableId)
                                ->orderBy('created_at', 'asc')
                                ->get();
        
        return response()->json([
            'id_req' => $deliverable->req_id,
            'nombre_entregable' => $deliverable->masterDeliverable->name ?? 'Entregable Sin Nombre',
            'estado_entregable' => $deliverable->status,
            'actividades' => $activities // Cambiamos el alias 'registros' a 'actividades'
        ], 200);
    }

    /**
     * POST /workflow/requirements/{id}/coe/deliverables/{deliverable_id}/activities
     * 
     * AGREGAR ACTIVIDAD A UN ENTREGABLE EN LA FASE COE
     */
  

    public function store(StoreCoeActivityRequest $request, string $id, string $deliverableId): JsonResponse
    {
        // Y le pasamos $id al servicio
        $activity = $this->coeDeliverableService->storeRegister($deliverableId, $request->validated(), $id);
        
        return response()->json($activity, 201);
    }

    /**
     * PUT /workflow/coe/activities/{activity_id}/deliverables/{deliverable_id}
     * 
     * ACTUALIZAR ACTIVIDAD DE UN ENTREGABLE EN LA FASE COE
     */
    public function update(UpdateCoeActivityRequest $request, string $activityId, string $deliverableId): JsonResponse
    {
        $activity = $this->coeDeliverableService->updateRegister($activityId, $request->validated(), $deliverableId);
        
        return response()->json($activity, 200);
    }

    /**
     * DELETE /workflow/coe/activities/{activity_id}/deliverables/{deliverable_id}
     * 
     * ELIMINAR ACTIVIDAD EN LA FASE COE (SOFT DELETE)
     */
    public function destroy(string $activityId, string $deliverableId): JsonResponse
    {
        $this->coeDeliverableService->deleteRegister($activityId, $deliverableId);
        
        return response()->json(['message' => 'Actividad técnica eliminada exitosamente.'], 200);
    }
}