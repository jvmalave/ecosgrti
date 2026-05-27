<?php

namespace App\Domains\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Core\Http\Requests\StoreRequirementRequest;
use App\Domains\Core\Services\RequirementService;
use Illuminate\Http\JsonResponse;

class RequirementController extends Controller
{
    protected RequirementService $requirementService;

    // Inyectamos nuestro servicio
    public function __construct(RequirementService $requirementService)
    {
        $this->requirementService = $requirementService;
    }

    /**
     * Endpoint para guardar un nuevo requerimiento (POST)
     */
    public function store(StoreRequirementRequest $request): JsonResponse
    {
        // 1. Extraemos los archivos del request
        $needsFile = $request->file('needs_spreadsheet');
        $itDocFile = $request->file('it_request_doc');

        // 2. Pasamos los datos validados y los archivos al Servicio
        $requirement = $this->requirementService->createRequirement(
            $request->validated(), 
            $needsFile, 
            $itDocFile
        );

        // 3. Retornamos la respuesta HTTP 201 (Created) a Angular
        return response()->json([
            'message' => 'Requerimiento creado exitosamente.',
            'data' => $requirement
        ], 201);
    }
}