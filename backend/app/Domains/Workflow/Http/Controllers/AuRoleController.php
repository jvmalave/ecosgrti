<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Workflow\Services\AuWorkflowService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Exception;

class AuRoleController extends Controller
{
    public function __construct(
        private readonly AuWorkflowService $auWorkflowService
    ) {}

    /**
     * Acceder a Gestión de Asignación de Usuarios (AU)
     */
    public function initRoles(string $requirementId): JsonResponse
    {
        $data = $this->auWorkflowService->initializeRoles($requirementId);

        return response()->json($data, 200);
    }

    /**
     * CU-065: Finalizar Fase Asignación de Usuarios
     */
    public function finalizeAu(string $requirementId): JsonResponse
    {
        try {
            // Toda la validación, base de datos, Redis y Auditoría ocurre aquí.
            $result = $this->auWorkflowService->closeGlobalPhase($requirementId);

            return response()->json([
                'status' => 'success',
                'message' => $result['message'],
                'data' => [
                    'req_id' => $requirementId,
                    'progreso_global' => $result['progress_percentage']
                ]
            ], 200);

        } catch (HttpException $e) {
            // Captura los abort() del servicio (Fallas de validación 422)
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], $e->getStatusCode());
            
        } catch (Exception $e) {
            // Captura cualquier falla inesperada
            return response()->json([
                'status' => 'error',
                'message' => 'Fallo crítico transaccional al intentar cerrar la fase.',
                'debug' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}