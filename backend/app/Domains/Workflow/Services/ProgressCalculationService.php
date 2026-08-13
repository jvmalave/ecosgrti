<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Services;

use App\Domains\Core\Models\Requirement;
use App\Domains\Workflow\Models\RequirementPhaseHistory;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ProgressCalculationService
{
    /**
     * Calcula dinámicamente el progreso global basado en el historial inmutable.
     * Prioriza la matriz de progreso anclada (MDM Snapshot) y ofrece soporte 
     * retroactivo (Fallback Legacy) mediante el archivo de configuración.
     *
     * @param Requirement $requirement
     * @return float
     */
    public function calculateGlobalProgress(Requirement $requirement): float
    {
        // 1. Obtener los hitos (status codes) únicos alcanzados históricamente
        $achievedPhases = RequirementPhaseHistory::where('requirement_id', $requirement->id)
            ->pluck('phase_status_code')
            ->unique()
            ->toArray();

        $calculatedProgress = 0.0;

        // 2. Lógica de Enlace Inmutable (Snapshot MDM)
        if ($requirement->progress_matrix_id) {
            
            // Extracción de pesos haciendo JOIN con la tabla de hitos real
            $matrixWeights = DB::table('catalogs.progress_matrix_milestones as pmm')
                ->join('catalogs.milestones as m', 'pmm.milestone_id', '=', 'm.id')
                ->where('pmm.matrix_id', $requirement->progress_matrix_id)
                ->pluck('pmm.weight_percentage', 'm.status_code')
                ->toArray();

            foreach ($achievedPhases as $phaseCode) {
                if (isset($matrixWeights[$phaseCode])) {
                    $calculatedProgress += (float) $matrixWeights[$phaseCode];
                }
            }
            
        } else {
            // 3. Fallback Legacy: Soporte retroactivo para requerimientos previos a la US41
            $managementType = $requirement->management_type ?? 'Mixto';
            
            // Se utiliza el helper config() para leer las matrices originales
            $matrixConfig = config("workflow_progress.matrices.{$managementType}", config('workflow_progress.matrices.Mixto'));

            foreach ($achievedPhases as $phaseCode) {
                if (isset($matrixConfig[$phaseCode])) {
                    $calculatedProgress += (float) $matrixConfig[$phaseCode];
                }
            }
            
            // Registro silencioso para monitoreo de deuda técnica
            Log::info("Cálculo Legacy ejecutado para RRTI {$requirement->rrti}. No posee Snapshot MDM enlazado.");
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