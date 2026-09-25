<?php

declare(strict_types=1);

namespace App\Domains\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Core\Http\Requests\GenerateClosureActRequest;
use App\Domains\Core\Models\Requirement;
use App\Domains\Core\Services\RequirementClosureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;


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
        // El servicio ejecuta la transacción y nos devuelve el resultado con las fechas y la ruta
        $result = $this->closureService->finalize(
            $requirement,
            $request->validated(),
            $request->file('notification_file')
        );

        return response()->json([
            'status'              => 'success',
            'progress_percentage' => $result['progress_percentage'],
            'message'             => 'Cierre Histórico Absoluto ejecutado con éxito. El requerimiento es ahora inmutable.',
            'data'                => $result 
        ], 200);
  }

  /**
     * Descarga el soporte físico de notificación desde el disco privado.
     */
    public function downloadSupport(Requirement $requirement)
    {
        if (!$requirement->notification_support_path || !Storage::disk('local')->exists($requirement->notification_support_path)) {
            return response()->json([
                'message' => 'El soporte físico no fue encontrado en el servidor.'
            ], 404);
        }

        return Storage::disk('local')->download($requirement->notification_support_path);
    }

} 
