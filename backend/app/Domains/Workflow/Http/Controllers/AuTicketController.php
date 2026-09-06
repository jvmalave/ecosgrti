<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Workflow\Services\AuWorkflowService;
use App\Domains\Workflow\Http\Requests\StoreAuTicketRequest;
use Illuminate\Http\JsonResponse;
use App\Domains\Workflow\Http\Requests\RegisterAuResultRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;  

class AuTicketController extends Controller
{
    public function __construct(
        private readonly AuWorkflowService $auWorkflowService
    ) {}

    /**
     * Registrar nueva solicitud de Ticket y sube planillas.
     */
    public function store(StoreAuTicketRequest $request, string $requirementId): JsonResponse
    {
        
        // Extrae los datos validados incluye el array de planillas)
        $validatedData = $request->validated();
        
        // Delega toda la lógica transaccional, de archivos y auditoría al servicio
        $ticket = $this->auWorkflowService->storeTicket(
            $requirementId,
            $validatedData,
            $request->file('file'), // El PDF general
            $request->input('role_ids') // El array de UUIDs de los roles
        );

        return response()->json([
            'success' => true,
            'message' => 'Ticket de Asignación de Usuarios registrado exitosamente.',
            'data'    => $ticket
        ], 201);
    }

    public function registerResult(RegisterAuResultRequest $request, string $ticketId)
    {
        try {
            // Extraemos los datos validados
            $data = $request->except(['result_file', 'evaluations']);
            $evaluations = $request->input('evaluations');
            $file = $request->file('result_file');
            
            // Llama al método nativo del Trait ManagesCertificationTickets
            $ticket = $this->auWorkflowService->registerResult($ticketId, $data, $file, $evaluations);

            return response()->json([
                'status' => 'success',
                'message' => 'Dictamen procesado y roles bifurcados exitosamente.',
                'data' => $ticket
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al registrar resultados: ' . $e->getMessage()
            ], 500);
        }
    }

    public function downloadDocument(Request $request): StreamedResponse|JsonResponse
    {
        $path = $request->query('path');

        if (!$path || !Storage::disk('local')->exists($path)) {
            return response()->json([
                'status' => 'error',
                'message' => 'El documento no existe o no se encuentra disponible.'
            ], 404);
        }

        // Retorna el archivo para que el navegador lo pueda visualizar o descargar
        return Storage::disk('local')->download($path);
    }
    
}