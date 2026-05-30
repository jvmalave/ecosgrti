<?php

namespace App\Domains\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Core\Http\Requests\StoreRequirementRequest;
use App\Domains\Core\Services\RequirementService;
use App\Domains\Core\Docs\RequirementDocs;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class RequirementController extends Controller implements RequirementDocs
{
    protected RequirementService $requirementService;

    /**
     * Inyección estricta del Servicio del Dominio
     */
    public function __construct(RequirementService $requirementService)
    {
        $this->requirementService = $requirementService;
    }

    public function index(): JsonResponse
    {
        return response()->json(['message' => 'Dashboard Data', 'data' => []]);
    }

    /**
     * US04: El controlador ahora solo Orquesta la entrada y salida
     */
    public function store(StoreRequirementRequest $request): JsonResponse
    {
        try {
            // Delegamos toda la lógica de negocio al Service Layer
            $result = $this->requirementService->createRequirement(
                $request->validated(), // Pasamos solo la data que ya pasó las reglas de validación
                $request->file('it_request_doc'),
                $request->file('needs_spreadsheet')
            );

            return response()->json([
                'message' => 'Requerimiento creado exitosamente.',
                'data' => $result
            ], 
            201);

        } catch (\Exception $e) {
            Log::error('Error crítico en RequirementController@store (RRTI: ' . $request->rrti . '): ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Ocurrió un error interno al registrar el requerimiento.',
                'error' => config('app.debug') ? $e->getMessage() : 'Error de servidor.'
            ], 500);
        }
    }
}