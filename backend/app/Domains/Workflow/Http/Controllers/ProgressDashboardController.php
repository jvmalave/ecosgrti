<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Http\Controllers;

use App\Domains\Core\Models\Requirement;
use App\Domains\Workflow\Services\ProgressCalculationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Redis;

class ProgressDashboardController extends Controller
{
    public function __construct(
        private readonly ProgressCalculationService $progressService
    ) {}

    public function show(string $requirementId): JsonResponse
    {
        // 1. Instanciar requerimiento
        $requirement = Requirement::with(['phaseHistories'])->findOrFail($requirementId);

        // 2. Calcular progreso actualizado (SSOT desde Postgres)
        $progress = $this->progressService->calculateGlobalProgress($requirement);

        // 3. Actualizar caché de Redis para lectura rápida del frontend
        $cacheKey = "req_{$requirement->id}_progress";
        $data = [
            'current_progress' => $progress,
            'achieved_phases'  => $requirement->phaseHistories->pluck('phase_status_code')->unique()->toArray(),
            'updated_at'       => now()->toDateTimeString(),
        ];
        
        Redis::set($cacheKey, json_encode($data));

        return response()->json([
            'message' => 'Progreso calculado y sincronizado exitosamente.',
            'data'    => $data
        ]);
    }
}