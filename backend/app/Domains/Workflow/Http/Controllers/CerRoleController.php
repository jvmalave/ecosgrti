<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Workflow\Services\CerRoleService;
use App\Domains\Workflow\Models\CerRole;
use App\Domains\Workflow\Http\Requests\StoreCerTicketRequest;
use App\Domains\Workflow\Http\Requests\UpdateCerTicketRequest;
use App\Domains\Workflow\Http\Requests\CerResultRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class CerRoleController extends Controller
{
    public function __construct(private readonly CerRoleService $cerRoleService) {}

    public function index(string $requirementId): JsonResponse
    {
        $this->cerRoleService->initializeRoles($requirementId);
        
        $roles = CerRole::with(['requirementRole', 'ticket'])
            ->where('requirement_id', $requirementId)
            ->get()->sortBy('requirementRole.role_name')->values();

        return response()->json(['requirement_id' => $requirementId, 'roles' => $roles]);
    }
    public function storeTicket(StoreCerTicketRequest $request, string $requirementId): JsonResponse
    {
        $ticket = $this->cerRoleService->storeTicket(
            $requirementId, 
            $request->validated(), 
            $request->file('file'), 
            $request->validated('role_ids')
        );
        return response()->json(['message' => 'Ticket de certificación creado.', 'ticket' => $ticket], 201);
    }

    public function updateTicket(UpdateCerTicketRequest $request, string $ticketId): JsonResponse
    {
        $ticket = $this->cerRoleService->updateTicket(
            $ticketId, 
            $request->validated(), 
            $request->file('file'), 
            $request->validated('role_ids')
        );
        return response()->json(['message' => 'Ticket actualizado con éxito.', 'ticket' => $ticket]);
    }

    public function registerResult(CerResultRequest $request, string $ticketId): JsonResponse
    {
        $evaluations = json_decode($request->validated('evaluations'), true);
        $ticket = $this->cerRoleService->registerResult($ticketId, $request->validated(), $request->file('file'), $evaluations);
        return response()->json(['message' => 'Resultado de certificación registrado.', 'ticket' => $ticket]);
    }

    public function updateResult(CerResultRequest $request, string $ticketId): JsonResponse
    {
        $evaluations = json_decode($request->validated('evaluations'), true);
        $ticket = $this->cerRoleService->updateResultWithChallenge(
            $ticketId, $request->validated(), $request->file('file'), $evaluations, $request->validated('special_auth_token')
        );
        return response()->json(['message' => 'Dictamen alterado exitosamente.', 'ticket' => $ticket]);
    }

    public function downloadFile(string $ticketId)
    {
        // Delegamos la búsqueda y respuesta al servicio
        return $this->cerRoleService->getTicketFile($ticketId);
    }

    public function downloadRequestFile(string $ticketId)
    {
        $filePath = $this->cerRoleService->getRequestFilePath($ticketId); 
        
        return Storage::disk('local')->response($filePath);
    }

    public function closePhase(string $requirementId): JsonResponse
    {
        return response()->json($this->cerRoleService->closeGlobalPhase($requirementId));
    }
}