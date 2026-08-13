<?php

namespace App\Domains\Catalogs\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Catalogs\Services\OrgStructureService;
use App\Domains\Catalogs\Http\Requests\StoreSocietyRequest;
use App\Domains\Catalogs\Http\Requests\StoreSystemRequest;
use App\Domains\Catalogs\Http\Requests\StoreRequestingUnitRequest;
use App\Domains\Catalogs\Http\Requests\UpdateSocietyRequest;
use App\Domains\Catalogs\Http\Requests\UpdateSystemRequest;
use App\Domains\Catalogs\Http\Requests\UpdateRequestingUnitRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrgStructureController extends Controller
{
    public function __construct(
        protected OrgStructureService $orgService
    ) {}

    /**
     * GET /api/v1/catalogs/org-structure/tree
     * Retorna la estructura jerárquica completa respaldada en Redis.
     */
    public function tree(): JsonResponse
    {
        $treeData = $this->orgService->getFullTree();

        return response()->json([
            'status' => 'success',
            'data' => $treeData
        ], 200);
    }

    /**
     * POST /api/v1/catalogs/societies
     */public function storeSociety(StoreSocietyRequest $request): JsonResponse
    {
        // Delegamos la creación al servicio para garantizar la transacción y auditoría
        $society = $this->orgService->createSociety($request->validated());

        return response()->json([
            'message' => 'Sociedad registrada exitosamente.',
            'data' => $society
        ], 201);
    }

    /**
     * POST /api/v1/catalogs/systems
     */
    public function storeSystem(StoreSystemRequest $request): JsonResponse
    {
        $system = $this->orgService->createSystem($request->validated());

        return response()->json([
            'message' => 'Sistema registrado exitosamente.',
            'data' => $system
        ], 201);
    }

    /**
     * POST /api/v1/catalogs/requesting-units
     */
    public function storeRequestingUnit(StoreRequestingUnitRequest $request): JsonResponse
    {
        $unit = $this->orgService->createRequestingUnit($request->validated());

        return response()->json([
            'message' => 'Unidad Solicitante registrada exitosamente.',
            'data' => $unit
        ], 201);
    }
    

    /**
     * PATCH /api/v1/catalogs/org-structure/{nodeType}/{id}/status
     * Maneja el borrado lógico/reactivación aplicando reglas de integridad restrictiva (FS-02 y FS-03).
     */
    public function updateStatus(Request $request, string $nodeType, string $id): JsonResponse
    {
        $request->validate([
            'is_active' => ['required', 'boolean']
        ]);

        $this->orgService->toggleNodeStatus($nodeType, $id, $request->boolean('is_active'));

        return response()->json([
            'message' => 'Estatus del nodo actualizado correctamente.'
        ], 200);
    }

    /**
     * PUT /api/v1/catalogs/org-structure/societies/{id}
     */
    public function updateSociety(UpdateSocietyRequest $request, string $id): JsonResponse
    {
        $this->orgService->updateSociety($id, $request->validated());

        return response()->json([
            'message' => 'Sociedad actualizada exitosamente.'
        ], 200);
    }

    /**
     * PUT /api/v1/catalogs/org-structure/systems/{id}
     */
    public function updateSystem(UpdateSystemRequest $request, string $id): JsonResponse
    {
        $this->orgService->updateSystem($id, $request->validated());

        return response()->json([
            'message' => 'Sistema actualizado exitosamente.'
        ], 200);
    }

    /**
     * PUT /api/v1/catalogs/org-structure/requesting-units/{id}
     */
    public function updateRequestingUnit(UpdateRequestingUnitRequest $request, string $id): JsonResponse
    {
        $this->orgService->updateRequestingUnit($id, $request->validated());

        return response()->json([
            'message' => 'Unidad Solicitante actualizada exitosamente.'
        ], 200);
    }
}