<?php

declare(strict_types=1);

namespace App\Domains\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Core\Http\Requests\GenerateClosureActRequest;
use App\Domains\Core\Models\Requirement;
use App\Domains\Workflow\Services\RequirementClosureService;
use Illuminate\Http\JsonResponse;


class RequirementClosureController extends Controller
{
    public function __construct(
        private readonly RequirementClosureService $closureService
    ) {}

  public function generateDraft(GenerateClosureActRequest $request, Requirement $requirement): JsonResponse
    {
        // Delega la generación del Base64 al servicio de dominio
        $base64Pdf = $this->closureService->generateDraftBase64(
            $requirement, 
            $request->validated()
        );

        // Retornamos la respuesta HTTP
        return response()->json([
            'message'   => 'Acta borrador generada exitosamente.',
            'draft_pdf' => $base64Pdf
        ], 200);
    }
    
    
public function finalizeClosure(GenerateClosureActRequest $request, Requirement $requirement): JsonResponse
    {
        // El controlador solo delega la responsabilidad al servicio
        $this->closureService->finalize(
            $requirement, 
            $request->validated(), 
            $request->file('notification_file')
        );

        // Y retorna la respuesta
        return response()->json([
            'status'          => 'success',
            'progreso_global' => 100,
            'message'         => 'Cierre Histórico Absoluto ejecutado con éxito. El requerimiento es ahora inmutable.'
        ], 200);
    }
} 
