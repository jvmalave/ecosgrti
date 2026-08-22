<?php

namespace App\Domains\Workflow\Http\Controllers;

use App\Http\Controllers\Controller;

use Illuminate\Http\JsonResponse;
use App\Domains\Workflow\Services\PiPhaseService;
use Exception;
use Illuminate\Support\Facades\Log;

class PiPhaseController extends Controller
{
    public function __construct(
        private readonly PiPhaseService $piPhaseService
    ) {}

    /**
     * GET /workflow/requirements/{id}/pi/roles-init
     * CU-040: Acceder a Gestión de Pruebas Integrales
     */
    public function initializeRoles(string $id): JsonResponse
    {
        try {
            $userId = (string) auth()->id();
            
            // Invocamos el servicio transaccional
            $data = $this->piPhaseService->initializePiRoles($id, $userId);

            return response()->json($data, 200);

        } catch (Exception $e) {
            $statusCode = $e->getCode() === 403 ? 403 : 500;
            
            if ($statusCode === 500) {
                Log::error("Error inicializando roles PI para Req {$id}: " . $e->getMessage());
            }

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], $statusCode);
        }
    }
}