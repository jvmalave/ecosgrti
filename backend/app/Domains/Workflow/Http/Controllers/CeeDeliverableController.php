<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Workflow\Services\CeeDeliverableService;
use App\Domains\Workflow\Models\CeeDeliverable;
use App\Domains\Workflow\Http\Requests\StoreCeeTicketRequest;
use App\Domains\Workflow\Http\Requests\UpdateCeeTicketRequest;
use App\Domains\Workflow\Http\Requests\CeeResultRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class CeeDeliverableController extends Controller
{
    public function __construct(private readonly CeeDeliverableService $ceeDeliverableService) {}

    public function index(string $requirementId): JsonResponse
    {
        $this->ceeDeliverableService->initializeDeliverables($requirementId);

        $deliverables = CeeDeliverable::with(['deliverable', 'ticket'])
            ->where('requirement_id', $requirementId)
            ->get()->sortBy('deliverable.name')->values();

        return response()->json(['requirement_id' => $requirementId, 'deliverables' => $deliverables]);
    }

    public function storeTicket(StoreCeeTicketRequest $request, string $requirementId): JsonResponse
    {
        $ticket = $this->ceeDeliverableService->storeTicket(
            $requirementId, 
            $request->validated(), 
            $request->file('file'), 
            $request->validated('deliverable_ids')
        );
        return response()->json(['message' => 'Ticket generado con éxito.', 'ticket' => $ticket], 201);
    }

    public function updateTicket(UpdateCeeTicketRequest $request, string $ticketId): JsonResponse
    {
        $ticket = $this->ceeDeliverableService->updateTicket(
            $ticketId, 
            $request->validated(), 
            $request->file('file'), 
            $request->validated('deliverable_ids')
        );
        return response()->json(['message' => 'Ticket actualizado con éxito.', 'ticket' => $ticket]);
    }

    public function registerResult(CeeResultRequest $request, string $ticketId): JsonResponse
    {
        $evaluations = json_decode($request->validated('evaluations'), true);
        $ticket = $this->ceeDeliverableService->registerResult($ticketId, $request->validated(), $request->file('file'), $evaluations);
        return response()->json(['message' => 'Resultado documentado con éxito.', 'ticket' => $ticket]);
    }

    public function updateResult(CeeResultRequest $request, string $ticketId): JsonResponse
    {
        $evaluations = json_decode($request->validated('evaluations'), true);
        $ticket = $this->ceeDeliverableService->updateResultWithChallenge(
            $ticketId, $request->validated(), $request->file('file'), $evaluations, $request->validated('special_auth_token')
        );
        return response()->json(['message' => 'Dictamen alterado exitosamente.', 'ticket' => $ticket]);
    }

    public function closePhase(string $requirementId): JsonResponse
    {
        return response()->json($this->ceeDeliverableService->closeGlobalPhase($requirementId));
    }

    public function downloadRequestFile(string $ticketId)
    {
        $filePath = $this->ceeDeliverableService->getRequestFilePath($ticketId);
        
        return Storage::disk('local')->response($filePath);
    }

    public function downloadResultFile(string $ticketId)
    {
        $filePath = $this->ceeDeliverableService->getResultFilePath($ticketId);
        
        return Storage::disk('local')->response($filePath);
    }
}