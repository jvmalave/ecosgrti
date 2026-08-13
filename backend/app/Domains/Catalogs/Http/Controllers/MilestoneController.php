<?php

namespace App\Domains\Catalogs\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Catalogs\Services\MilestoneService;
use App\Domains\Catalogs\Http\Requests\MilestoneRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

class MilestoneController extends Controller
{
    public function __construct(protected MilestoneService $milestoneService)
    {
    }

    // LISTAR HITOS TÉCNICOS
    public function index(Request $request): JsonResponse
    {
        $type = $request->query('management_type');
        $milestones = $this->milestoneService->getAllMilestones($type);

        return response()->json($milestones, 200);
    }

    // REGISTRAR UN HITO TÉCNICO
    public function store(MilestoneRequest $request): JsonResponse
    {
        $id = $this->milestoneService->createMilestone($request->validated());

        return response()->json([
            'message' => 'Hito técnico registrado exitosamente.',
            'id' => $id
        ], 201);
    }

    // ACTUALIZAR UN HITO TÉCNICO
    public function update(MilestoneRequest $request, string $id): JsonResponse
    {
        $updated = $this->milestoneService->updateMilestone($id, $request->validated());

        if (!$updated) {
            return response()->json(['message' => 'El hito técnico solicitado no fue encontrado.'], 404);
        }

        return response()->json(['message' => 'Hito técnico actualizado exitosamente.'], 200);
    }

    // ELIMINAR UN HITO TÉCNICO
    public function destroy(string $id): JsonResponse
    {
        try {
            $deleted = $this->milestoneService->deleteMilestone($id);

            if (!$deleted) {
                return response()->json(['message' => 'El hito técnico solicitado no fue encontrado.'], 404);
            }

            return response()->json(['message' => 'Hito técnico eliminado de forma permanente.'], 200);

        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}