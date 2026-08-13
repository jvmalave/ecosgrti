<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Workflow\Services\ATFClosureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ATFClosureController extends Controller
{
    
    public function __construct(
        private readonly ATFClosureService $closureService
    ) {}


    // Metodo que verifica si el requerimiento esta listo para ser cerrado
    public function checkReadiness(string $id): JsonResponse
    {
        $readiness = $this->closureService->checkClosureReadiness($id);
        return response()->json($readiness, 200);
    }

    // Metodo que cierra la fase ATF
    public function closePhase(Request $request, string $id): JsonResponse
    {
        
        $userId = (string) $request->user()?->id;

        $this->closureService->executeClosure($id, $userId);

        return response()->json([
            'status'  => 'ATF-C',
            'message' => 'Fase ATF cerrada exitosamente'
        ], 200);
    }
}