<?php

namespace App\Domains\Security\Services;

use App\Domains\Security\Models\CspeConsultant;
use Illuminate\Support\Carbon;

class CspeWorkloadService
{
  /**
     * Calcula la matriz de capacidad basada en un horizonte temporal dinámico.
     */
    public function getConsultantsWorkload(string $horizon = 'current_week', bool $includeBacklog = false)
    {
        // 1. Agregamos phaseHistories a la carga ansiosa para evaluar qué está realmente cerrado
        $consultants = CspeConsultant::with([
            'person', 
            'activeRequirements.scheduleEstimation.estimatedPhases', 
            'activeRequirements.cspeConsultants',
            'activeRequirements.phaseHistories' 
        ])->get();

        $startOfPeriod = Carbon::now()->startOfDay();
        
        switch ($horizon) {
            case '15_days':
                $endOfPeriod = Carbon::now()->addDays(15)->endOfDay();
                break;
            case '30_days':
                $endOfPeriod = Carbon::now()->addDays(30)->endOfDay();
                break;
            case '60_days':
                $endOfPeriod = Carbon::now()->addDays(60)->endOfDay();
                break;
            case 'current_week':
            default:
                $startOfPeriod = Carbon::now()->startOfWeek();
                $endOfPeriod = Carbon::now()->endOfWeek();
                break;
        }

        $workingDays = $startOfPeriod->diffInDaysFiltered(function (Carbon $date) {
            return $date->isWeekday();
        }, $endOfPeriod);
        
        $totalBaseHours = max(1, $workingDays * 8);

        $workloadData = $consultants->map(function ($consultant) use ($startOfPeriod, $endOfPeriod, $totalBaseHours, $includeBacklog) {
            $totalEstimatedHours = 0;
            $activeRequirementsCount = $consultant->activeRequirements->count();

            foreach ($consultant->activeRequirements as $requirement) {
                $totalConsultantsAssigned = $requirement->cspeConsultants->count();
                $hoursForPeriod = 0;
                
                // Extraemos un arreglo simple con todos los códigos del historial de este requerimiento
                $historyCodes = $requirement->phaseHistories->pluck('phase_status_code')->toArray();

                if ($requirement->scheduleEstimation && $requirement->scheduleEstimation->estimatedPhases) {
                    $hoursForPeriod = $requirement->scheduleEstimation->estimatedPhases
                        ->filter(function ($phase) use ($startOfPeriod, $endOfPeriod, $includeBacklog, $historyCodes) {
                            
                            // A. DEPURACIÓN: Si la fase ya se cerró en la realidad, la descartamos de inmediato
                            if ($this->isPhaseClosed($phase->phase_name, $historyCodes)) {
                                return false; 
                            }

                            // B. HORIZONTE: ¿La fase ocurre dentro de las fechas proyectadas?
                            $isWithinHorizon = $phase->start_date->lte($endOfPeriod) && $phase->end_date->gte($startOfPeriod);
                            
                            // C. ATRASO (BACKLOG): ¿La fase debió terminar antes de iniciar el periodo evaluado?
                            $isBacklog = $phase->end_date->lt($startOfPeriod);

                            if ($isWithinHorizon) {
                                return true;
                            }

                            if ($includeBacklog && $isBacklog) {
                                return true;
                            }

                            return false;
                        })
                        ->sum('estimated_hours');
                }
                
                if ($totalConsultantsAssigned > 0) {
                    $totalEstimatedHours += ($hoursForPeriod / $totalConsultantsAssigned);
                }
            }

            $utilizationPercentage = ($totalEstimatedHours / $totalBaseHours) * 100;

            return [
                'id'                     => $consultant->id,
                'full_name'              => $consultant->person ? $consultant->person->first_name . ' ' . $consultant->person->last_name : 'Sin Nombre',
                'active_requirements'    => $activeRequirementsCount,
                'assigned_hours'         => round($totalEstimatedHours, 2),
                'available_hours'        => round(max(0, $totalBaseHours - $totalEstimatedHours), 2),
                'utilization_percentage' => round($utilizationPercentage, 2),
                'is_overloaded'          => $totalEstimatedHours > $totalBaseHours,
                'base_hours'             => $totalBaseHours
            ];
        });

        return $workloadData->sortBy('utilization_percentage')->values();
    }

    /**
     * Diccionario para cruzar la planificación vs la realidad ejecutada.
     */
    private function isPhaseClosed(string $phaseName, array $historyCodes): bool
    {
        $mapping = [
            'ATF'            => ['ATF-C'],
            'DISENO'         => ['DT-C'],
            'CONSTRUCCION'   => ['COR-C', 'COE-C'],
            'PRUEBAS'        => ['PI-C'],
            'CERTIFICACION'  => ['CER-C', 'CEE-C'],
            'IMPLEMENTACION' => ['AU-C']
        ];

        $normalizedName = strtoupper(trim($phaseName));

        if (!isset($mapping[$normalizedName])) {
            return false;
        }

        // Verificamos si alguno de los códigos de cierre requeridos existe en el historial
        foreach ($mapping[$normalizedName] as $code) {
            if (in_array($code, $historyCodes)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Obtiene el histórico incluyendo el porcentaje de avance.
     */
    public function getConsultantHistory(string $consultantId)
    {
        $consultant = CspeConsultant::with([
            'person', 
            'allRequirements' => function ($query) {
                // AGREGADO: progress_percentage al select
                $query->select('core.requirements.id', 'rrti', 'description', 'status', 'completion_date', 'core.requirements.created_at', 'progress_percentage');
            }
        ])->findOrFail($consultantId);

        return [
            'consultant_name' => $consultant->person->first_name . ' ' . $consultant->person->last_name,
            'history'         => $consultant->allRequirements
        ];
    }
}