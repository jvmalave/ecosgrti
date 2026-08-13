<?php

namespace App\Domains\Catalogs\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Domains\Catalogs\Http\Requests\PublishMatrixVersionRequest;
use App\Domains\Catalogs\Services\ProgressMatrixService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ProgressMatrixController extends Controller
{
    public function __construct(protected ProgressMatrixService $matrixService)
    {
    }


    /**
     * Endpoint GET para recuperar la matriz activa actual.
     */
    // RECUPERAR LA MATRIZ ACTIVA
    // public function getActiveMatrix(Request $request): JsonResponse
    // {
    //     // 1. Validar que el parámetro 'type' venga en la Query String
    //     $request->validate([
    //         'type' => 'required|string|in:ROLES,ENTREGABLES,MIXTO'
    //     ]);

    //     $managementType = $request->query('type');

    //     // 2. Delegar la obtención al Service Layer (que maneja Redis/BD)
    //     $activeMatrix = $this->matrixService->getActiveMatrix($managementType);

    //     if (!$activeMatrix) {
    //         return response()->json([
    //             'message' => "No se encontró una matriz activa para el tipo de gestión: {$managementType}"
    //         ], 404);
    //     }

    //     // 3. Retornar el payload 200 OK requerido por Angular
    //     return response()->json([
    //         'matrix_id' => $activeMatrix->id,
    //         'version_number' => $activeMatrix->version_number,
    //         'management_type' => $activeMatrix->management_type,
    //         'milestones' => $activeMatrix->milestones // Asume que la relación del modelo mapea correctamente los campos name y weight
    //     ], 200);
    // }


    public function getActiveMatrix(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'required|string|in:ROLES,ENTREGABLES,MIXTO'
        ]);

        $managementType = $request->query('type');
        $activeMatrix = $this->matrixService->getActiveMatrix($managementType);

        if (!$activeMatrix) {
            // 🚀 SOLUCIÓN: Si no hay versión publicada, buscamos en el catálogo 
            // maestro de hitos para que el frontend pueda inicializar la V1.
            $baseMilestones = DB::table('catalogs.milestones')
                ->where('management_type', $managementType)
                ->get();

            if ($baseMilestones->isEmpty()) {
                return response()->json([
                    'message' => "El catálogo maestro de hitos para {$managementType} está vacío. Registre los hitos primero."
                ], 404);
            }

            // Construimos un borrador (V0) con los hitos en 0%
            $draftMilestones = $baseMilestones->map(function ($item) {
                return [
                    'milestone_id' => $item->id,
                    'name'         => $item->name,
                    'weight'       => 0.00
                ];
            });

            return response()->json([
                'matrix_id'       => null,
                'version_number'  => 0,
                'management_type' => $managementType,
                'milestones'      => $draftMilestones
            ], 200); // 👈 Retornamos 200 OK para que Angular levante el formulario
        }

        // Mapeo normal si la matriz ya existe (V1, V2, etc.)
        $milestones = $activeMatrix->milestones->map(function ($pivot) {
            return [
                'milestone_id' => $pivot->milestone_id,
                // Asumiendo que existe la relación 'milestone' en tu modelo pivot
                'name'         => $pivot->milestone->name ?? 'Hito Técnico', 
                'weight'       => (float) $pivot->weight_percentage
            ];
        });

        return response()->json([
            'matrix_id'       => $activeMatrix->id,
            'version_number'  => $activeMatrix->version_number,
            'management_type' => $activeMatrix->management_type,
            'milestones'      => $milestones
        ], 200);
    }


    /**
     * Endpoint POST para publicar una nueva versión de la matriz.
     */
    public function publish(PublishMatrixVersionRequest $request): JsonResponse
    {
        // El FormRequest ya validó matemáticamente el 100% exacto y la autorización
        $validated = $request->validated();

        // Delegación estricta al Service Layer
        $newMatrix = $this->matrixService->publishNewVersion(
            $validated['management_type'],
            $validated['milestones']
        );

        return response()->json([
            'message' => "Nueva versión de matriz {$validated['management_type']} publicada exitosamente",
            'data' => [
                'matrix_id' => $newMatrix->id,
                'version_number' => $newMatrix->version_number
            ]
        ], 201);
    }
}