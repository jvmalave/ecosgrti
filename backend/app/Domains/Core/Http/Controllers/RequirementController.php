<?php

namespace App\Domains\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use App\Domains\Core\Http\Requests\StoreRequirementRequest; // <-- Se importa el Form Request para validación de creación
use App\Domains\Core\Services\RequirementService; // <-- Se importa el Service para creación de requerimientos
use App\Domains\Core\Docs\RequirementDocs; // <-- Se importa la interfaz de documentación para Swagger/OpenAPI
use App\Domains\Core\Services\RequirementDashboardService; // <-- Se importa el Service para lectura de dashboard

class RequirementController extends Controller implements RequirementDocs
{
    protected RequirementService $requirementService;
    protected RequirementDashboardService $dashboardService; 

    /**
     * Inyección estricta de Servicios del Dominio (CQRS)
     */
    public function __construct(
        RequirementService $requirementService,
        RequirementDashboardService $dashboardService
    ) {
        $this->requirementService = $requirementService;
        $this->dashboardService = $dashboardService;
    }

    /**
     * US05: Dashboard con Carga Híbrida (Redis + PostgreSQL)
     */
    public function index(Request $request): JsonResponse
    {
        // Captura de parámetros de paginación y filtros
        $status = $request->query('status', 'active');
        $limit = (int) $request->query('limit', 10);
        $offset = (int) $request->query('offset', 0);
        $search = $request->query('search'); // Capturamos la búsqueda de la URL

        // Validaciones básicas de seguridad para la paginación
        if ($limit > 50) $limit = 50; 
        if ($offset < 0) $offset = 0;

        // Delegamos la lectura de alto rendimiento al DashboardService
        $data = $this->dashboardService->getRequirements($status, $limit, $offset, $search); 

        return response()->json($data, 200);
    }

    /**
     * US04: El controlador ahora solo Orquesta la entrada y salida
     */
    public function store(StoreRequirementRequest $request): JsonResponse
    {
        try {
            // Delegamos toda la lógica de negocio al Service Layer
            $result = $this->requirementService->createRequirement(
                $request->validated(),
                $request->file('it_request_doc'),
                $request->file('needs_spreadsheet')
            );

            return response()->json([
                'message' => 'Requerimiento creado exitosamente.',
                'data' => $result
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error crítico en RequirementController@store (RRTI: ' . $request->rrti . '): ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Ocurrió un error interno al registrar el requerimiento.',
                'error' => config('app.debug') ? $e->getMessage() : 'Error de servidor.'
            ], 500);
        }
    }
}