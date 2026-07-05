<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Services;

use App\Domains\Core\Models\Requirement;
use App\Domains\Workflow\Models\RequirementPhaseHistory;
use Illuminate\Support\Facades\Log;

class ProgressCalculationService
{
    /**
     * Calcula dinámicamente el progreso global basado en el historial inmutable.
     * Lee las matrices de configuración sin hardcodear valores.
     *
     * @param Requirement $requirement
     * @return float
     */
    public function calculateGlobalProgress(Requirement $requirement): float
    {
        // 1. Obtener la matriz desde el archivo de configuración
        $managementType = $requirement->management_type ?? 'Mixto';
        
        // Usamos el helper config() de Laravel. Si no existe la tipología, por defecto usa 'Mixto'
        $matrix = config("workflow_progress.matrices.{$managementType}", config('workflow_progress.matrices.Mixto'));

        // 2. Obtener los hitos (status codes) únicos alcanzados históricamente
        $achievedPhases = RequirementPhaseHistory::where('requirement_id', $requirement->id)
            ->pluck('phase_status_code')
            ->unique()
            ->toArray();

        // 3. Sumar el peso de las fases alcanzadas según la matriz
        $calculatedProgress = 0.0;
        foreach ($achievedPhases as $phaseCode) {
            if (isset($matrix[$phaseCode])) {
                $calculatedProgress += $matrix[$phaseCode];
            }
        }

        // 4. Regla Failsafe: Normalizar entre 0 y 100 para evitar desbordes aritméticos
        $normalizedProgress = max(0.0, min(100.0, $calculatedProgress));

        if ($calculatedProgress > 100.0) {
            Log::channel('audit')->warning("Desborde aritmético de progreso detectado en RRTI {$requirement->rrti}.", [
                'requirement_id' => $requirement->id,
                'calculated_raw' => $calculatedProgress,
                'normalized' => $normalizedProgress
            ]);
        }

        return (float) round($normalizedProgress, 2);
    }
}