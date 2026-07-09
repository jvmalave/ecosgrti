<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Core\Models\Requirement;
use App\Domains\Workflow\Http\Requests\StoreAtfAgreementRequest;
use App\Domains\Workflow\Services\ATFService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ATFAgreementController extends Controller
{
    public function __construct(
        private readonly ATFService $atfService
    ) {}

    /**
     * Registrar Nuevo Acuerdo (CU-015)
     */
    public function store(StoreAtfAgreementRequest $request, string $requirementId): JsonResponse
    {
        // 1. Obtener la entidad raíz
        $requirement = Requirement::findOrFail($requirementId);

        // 2. Delegar toda la orquestación a la capa de servicios (Domain Logic)
        $agreement = $this->atfService->createAgreement(
            $requirement,
            $request->validated(),
            Auth::id() ?? 'system'
        );

        // 3. Retornar la respuesta HTTP
        return response()->json([
            'message' => 'Acuerdo registrado exitosamente y progreso actualizado.',
            'data'    => $agreement,
            'current_progress' => $requirement->progress_percentage
        ], 201);
    }

    public function index(string $requirementId)
    {
        $agreements = $this->atfService->getAgreements($requirementId);

        return response()->json([
            'data' => $agreements
        ], 200);
    }

    public function update(StoreAtfAgreementRequest $request, string $requirementId, string $agreementId)
    {
        // 1. Obtenemos los datos ya validados y limpios por el Form Request
        $data = $request->validated();

        // 2. Delegamos la lógica a la capa de Servicio
        $this->atfService->updateAgreement($agreementId, $data);

        // 3. Retornamos la respuesta HTTP
        return response()->json([
            'message' => 'El acuerdo técnico ha sido actualizado exitosamente.'
        ], 200);
    }

    /**
     * Elimina un acuerdo lógicamente (CU-014).
     */
    public function destroy(string $requirementId, string $agreementId)
    {
        $this->atfService->deleteAgreement($agreementId);

        return response()->json([
            'message' => 'El acuerdo técnico ha sido eliminado correctamente.'
        ], 200);
    }
}