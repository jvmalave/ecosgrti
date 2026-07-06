<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Workflow\Services\ATFClosureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ATFClosureController extends Controller
{
    private ATFClosureService $closureService;

    /**
     * Inyección de dependencias del servicio de orquestación.
     */
    public function __construct(ATFClosureService $closureService)
    {
        $this->closureService = $closureService;
    }

    /**
     * GET /api/workflow/requirements/{id}/closure-readiness
     * Verifica en vivo si la fase ATF cumple el quórum para ser cerrada.
     *
     * @param string $id Identificador del requerimiento
     * @return JsonResponse
     */
    public function checkReadiness(string $id): JsonResponse
    {
        $readiness = $this->closureService->checkClosureReadiness($id);

        // Cumpliendo con el diagrama de secuencia: Retorna 422 si el quórum es insuficiente
        if (!$readiness['ready']) {
            return response()->json([
                'ready'   => false,
                'reasons' => $readiness['reasons']
            ], 422);
        }

        // Retorna 200 OK si está listo para cerrar
        return response()->json([
            'ready' => true
        ], 200);
    }


    /**
     * POST /api/workflow/requirements/{id}/close-atf
     * Ejecuta el cierre atómico de la fase ATF (Hard Gate).
     *
     * @param Request $request
     * @param string $id Identificador del requerimiento
     * @return JsonResponse
     */
    public function closePhase(Request $request, string $id): JsonResponse
    {
        // Delegamos la transacción y la auditoría al servicio.
        // Las excepciones de validación lanzadas allí serán formateadas automáticamente a 422 por Laravel.
        $this->closureService->executeClosure($id, (string) $request->user()->id);

        // Respuesta exacta estipulada en el diagrama de secuencia
        return response()->json([
            'status'  => 'ATF_COMPLETED',
            'message' => 'Fase ATF cerrada exitosamente'
        ], 200);
    }
}