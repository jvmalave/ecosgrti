<?php

namespace App\Domains\Workflow\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use App\Domains\Workflow\Http\Requests\StorePapOrderRequest;
use App\Domains\Workflow\Http\Requests\StorePapResultRequest; 
use App\Domains\Workflow\Services\PapWorkflowService;
use App\Domains\Workflow\Services\PapOrderService;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Exception;
use Illuminate\Support\Facades\Storage;

class PapOrderController extends Controller
{
    public function __construct(
        private PapWorkflowService $papWorkflowService,
        private PapOrderService $papOrderService
    ) {}

    public function store(StorePapOrderRequest $request, string $requirementId): JsonResponse
    {
        $order = $this->papWorkflowService->storeTicket(
            $requirementId, 
            $request->validated(), 
            $request->file('file'), 
            $request->input('role_ids')
        );
        
        $req = \App\Domains\Core\Models\Requirement::find($requirementId);

        return response()->json([
            'message' => 'Orden de transporte registrada exitosamente.',
            'order_id' => $order->id,
            'phase_actual' => $req->status,
            'progress_percentage' => $req->progress_percentage
        ], 201);
    }

    public function registerResult(StorePapResultRequest $request, string $orderId): JsonResponse
    {
        $this->papWorkflowService->registerResult(
            $orderId, 
            $request->validated(), 
            $request->file('file'), 
            $request->input('evaluations')
        );

        return response()->json([
            'message' => 'Dictamen registrado exitosamente.'
        ], 200);
    }

    /**
     * Descarga el documento base de la Orden de Transporte
     */
    public function downloadOrderFile(string $orderId): StreamedResponse|JsonResponse
    {
        try {
            $fileDetails = $this->papOrderService->getOrderFileDetails($orderId);
            
            return Storage::disk('local')->download(
                $fileDetails['path'], 
                $fileDetails['filename']
            );
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }
    }

    /**
     * Descarga el acta del dictamen de la Orden de Transporte
     */
    public function downloadResultFile(string $orderId): StreamedResponse|JsonResponse
    {
        try {
            $fileDetails = $this->papOrderService->getResultFileDetails($orderId);
            
            return Storage::disk('local')->download(
                $fileDetails['path'], 
                $fileDetails['filename']
            );
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }
    }
}
