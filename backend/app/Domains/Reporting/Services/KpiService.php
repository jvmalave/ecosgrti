<?php

namespace App\Domains\Reporting\Services;

use App\Domains\Reporting\Repositories\KpiReadRepository;
use Carbon\Carbon;

class KpiService
{
    public function __construct(
        protected KpiReadRepository $kpiRepo
    ) {}

    /**
     * Calcula la Tasa de Entrega a Tiempo (OTD) usando CQRS (Solo lectura).
     */
    public function calculateOTD($startDate = null, $endDate = null)
    {
        // 1. Extraer datos reales (Cuándo se cerró AU-C en el sistema)
        $auHistories = $this->kpiRepo->getClosedAuPhases($startDate, $endDate);
        
        if ($auHistories->isEmpty()) {
            return $this->emptyOtdResult();
        }

        $requirementIds = $auHistories->pluck('requirement_id')->unique()->toArray();

        // 2. Extraer datos planificados (Cuándo se estimó que terminaría la Implementación)
        $estimations = $this->kpiRepo->getImplementationEstimations($requirementIds);

        $onTimeCount = 0;
        $lateCount = 0;
        $evaluatedCount = 0;

        // 3. Cruce en memoria (Data Hydration ultra rápido)
        foreach ($auHistories as $history) {
            $reqId = $history->requirement_id;
            
            $estimation = $estimations->firstWhere('requirement_id', $reqId);

            if (!$estimation || !$estimation->end_date) {
                continue; // Excluir de la estadística si no tiene estimación formal
            }

            $plannedEndDate = Carbon::parse($estimation->end_date)->startOfDay();
            $actualEndDate  = Carbon::parse($history->transitioned_at)->startOfDay();

            // 4. Comparación Matemática de Fechas
            if ($actualEndDate->lte($plannedEndDate)) {
                $onTimeCount++;
            } else {
                $lateCount++;
            }
            
            $evaluatedCount++;
        }

        $otdPercentage = $evaluatedCount > 0 
            ? round(($onTimeCount / $evaluatedCount) * 100, 2) 
            : 0;

        return [
            'otd_percentage'  => $otdPercentage,
            'on_time_count'   => $onTimeCount,
            'late_count'      => $lateCount,
            'total_evaluated' => $evaluatedCount
        ];
    }

    private function emptyOtdResult()
    {
        return [
            'otd_percentage'  => 0,
            'on_time_count'   => 0,
            'late_count'      => 0,
            'total_evaluated' => 0
        ];
    }

    /**
     * Calcula la Desviación de Cronograma cruzando IMPLEMENTACION vs (PAP-I y AU-C).
     */
    public function calculateScheduleDeviation($startDate = null, $endDate = null)
    {
        // 1. Población base: Requerimientos que cerraron Asignación a Usuario (AU-C)
        $auHistories = $this->kpiRepo->getClosedAuPhases($startDate, $endDate);

        if ($auHistories->isEmpty()) {
            return $this->emptyDeviationResult();
        }

        $requirementIds = $auHistories->pluck('requirement_id')->unique()->toArray();

        // 2. Extraer Planificación (start_date y end_date de IMPLEMENTACION)
        $estimations = $this->kpiRepo->getImplementationEstimations($requirementIds);

        // 3. Extraer Inicios Reales (transitioned_at de PAP-I)
        $papHistories = $this->kpiRepo->getStartedPapPhases($requirementIds);

        $totalDeviationDays = 0;
        $delayedCount = 0;
        $aheadCount = 0;
        $onTrackCount = 0;
        $evaluatedCount = 0;

        foreach ($auHistories as $auHistory) {
            $reqId = $auHistory->requirement_id;

            $estimation = $estimations->firstWhere('requirement_id', $reqId);
            $papHistory = $papHistories->firstWhere('requirement_id', $reqId);

            // Validamos que el requerimiento tenga los 3 puntos de datos para poder medir
            if (!$estimation || !$estimation->start_date || !$estimation->end_date || !$papHistory) {
                continue;
            }

            // Duración Planificada
            $plannedStart = Carbon::parse($estimation->start_date)->startOfDay();
            $plannedEnd   = Carbon::parse($estimation->end_date)->startOfDay();
            $plannedDuration = $plannedStart->diffInDays($plannedEnd);

            // Duración Real
            $actualStart = Carbon::parse($papHistory->transitioned_at)->startOfDay();
            $actualEnd   = Carbon::parse($auHistory->transitioned_at)->startOfDay();
            $actualDuration = $actualStart->diffInDays($actualEnd);

            // Cálculo de Desviación en Días (Real vs Planificado)
            // Positivo = Tomó más días (Desviación negativa para el negocio / Atraso)
            // Negativo = Tomó menos días (Eficiencia / Adelanto)
            $deviation = $actualDuration - $plannedDuration;
            $totalDeviationDays += $deviation;

            if ($deviation > 0) {
                $delayedCount++;
            } elseif ($deviation < 0) {
                $aheadCount++;
            } else {
                $onTrackCount++;
            }

            $evaluatedCount++;
        }

        $averageDeviation = $evaluatedCount > 0 
            ? round($totalDeviationDays / $evaluatedCount, 2) 
            : 0;

        return [
            'average_deviation_days' => $averageDeviation,
            'delayed_count'          => $delayedCount,
            'ahead_count'            => $aheadCount,
            'on_track_count'         => $onTrackCount,
            'total_evaluated'        => $evaluatedCount
        ];
    }

    private function emptyDeviationResult()
    {
        return [
            'average_deviation_days' => 0,
            'delayed_count'          => 0,
            'ahead_count'            => 0,
            'on_track_count'         => 0,
            'total_evaluated'        => 0
        ];
    }


    /**
     * Calcula la Desviación de Cronograma y genera Alertas Tempranas.
     */
    /**
     * Calcula la Desviación de Cronograma (KPI Gerencial) y genera Alertas Tempranas.
     */
    public function calculateScheduleDeviationAndAlerts()
    {
        $estimations = $this->kpiRepo->getActiveEstimations();
        
        if ($estimations->isEmpty()) {
            return ['deviation_stats' => [], 'alerts' => []];
        }

        $requirementIds = $estimations->pluck('requirement_id')->unique()->toArray();
        $histories = $this->kpiRepo->getHistoriesByRequirementIds($requirementIds);

        $phaseMap = [
            'DISENO'         => ['start' => 'DT-I',  'end' => 'DT-C'],
            'CONSTRUCCION'   => ['start' => 'CO-I',  'end' => 'CO-C'],
            'PRUEBAS'        => ['start' => 'PI-I',  'end' => 'PI-C'],
            'CERTIFICACION'  => ['start' => 'CER-I', 'end' => 'CER-C'],
            'IMPLEMENTACION' => ['start' => 'PAP-I', 'end' => 'AU-C'],
        ];

        $today = Carbon::now()->startOfDay();
        $alerts = [];
        
        // Acumuladores multidimensionales para el KPI
        $totalDeviationDays = 0;
        $evaluatedPhasesCount = 0;
        $delayedPhasesCount = 0;
        $onTimePhasesCount = 0;
        $phaseStats = []; // Agrupación estadística por nombre de fase

        $estimationsGrouped = $estimations->groupBy('requirement_id');

        foreach ($estimationsGrouped as $reqId => $reqEstimations) {
            $reqHistories = $histories->where('requirement_id', $reqId);
            $rrti = $reqEstimations->first()->rrti;

            foreach ($reqEstimations as $est) {
                $phaseName = $est->phase_name;
                
                if (!isset($phaseMap[$phaseName])) continue;

                $startCode = $phaseMap[$phaseName]['start'];
                $endCode   = $phaseMap[$phaseName]['end'];

                $plannedStart = Carbon::parse($est->start_date)->startOfDay();
                $plannedEnd   = Carbon::parse($est->end_date)->startOfDay();

                $actualStartHistory = $reqHistories->firstWhere('phase_status_code', $startCode);
                $actualEndHistory   = $reqHistories->firstWhere('phase_status_code', $endCode);

                // --- 1. LÓGICA DE ALERTAS TEMPRANAS ---
                if (!$actualStartHistory && $today->gt($plannedStart)) {
                    $daysLate = $plannedStart->diffInDays($today);
                    $alerts[] = [
                        'rrti'        => $rrti,
                        'phase'       => $phaseName,
                        'type'        => 'START_DELAY',
                        'message'     => "Atraso de {$daysLate} días en el inicio. Debió comenzar el {$plannedStart->format('d-m-Y')} (Hito: {$startCode}).",
                        'days_late'   => $daysLate
                    ];
                }

                if ($actualStartHistory && !$actualEndHistory && $today->gt($plannedEnd)) {
                    $daysLate = $plannedEnd->diffInDays($today);
                    $alerts[] = [
                        'rrti'        => $rrti,
                        'phase'       => $phaseName,
                        'type'        => 'END_DELAY',
                        'message'     => "Atraso de {$daysLate} días en el cierre. Debió finalizar el {$plannedEnd->format('d-m-Y')} (Hito: {$endCode}).",
                        'days_late'   => $daysLate
                    ];
                }

                // --- 2. LÓGICA DE ESTADÍSTICA DEL KPI (Desviación Consolidada) ---
                if ($actualEndHistory) {
                    $actualEnd = Carbon::parse($actualEndHistory->transitioned_at)->startOfDay();
                    $evaluatedPhasesCount++;

                    // Inicializamos el contador para la fase si no existe
                    if (!isset($phaseStats[$phaseName])) {
                        $phaseStats[$phaseName] = [
                            'total_evaluated' => 0,
                            'delayed_count'   => 0,
                            'on_time_count'   => 0,
                            'total_delay_days'=> 0
                        ];
                    }

                    $phaseStats[$phaseName]['total_evaluated']++;

                    if ($actualEnd->gt($plannedEnd)) {
                        $delay = $plannedEnd->diffInDays($actualEnd);
                        $totalDeviationDays += $delay;
                        $delayedPhasesCount++;
                        
                        $phaseStats[$phaseName]['delayed_count']++;
                        $phaseStats[$phaseName]['total_delay_days'] += $delay;
                    } else {
                        $onTimePhasesCount++;
                        $phaseStats[$phaseName]['on_time_count']++;
                    }
                }
            }
        }

        // Procesamiento final de promedios para el KPI
        $averageDeviation = $evaluatedPhasesCount > 0 ? round($totalDeviationDays / $evaluatedPhasesCount, 2) : 0;
        
        $detailedPhaseStats = [];
        foreach ($phaseStats as $phase => $stats) {
            $detailedPhaseStats[$phase] = [
                'total_evaluated' => $stats['total_evaluated'],
                'on_time_count'   => $stats['on_time_count'],
                'delayed_count'   => $stats['delayed_count'],
                'average_delay'   => $stats['delayed_count'] > 0 
                                    ? round($stats['total_delay_days'] / $stats['delayed_count'], 1) 
                                    : 0
            ];
        }

        return [
            'success' => true,
            'data' => [
                'deviation_stats' => [
                    'global_average_delay_days' => $averageDeviation,
                    'total_phases_evaluated'    => $evaluatedPhasesCount,
                    'phases_on_time'            => $onTimePhasesCount,
                    'phases_delayed'            => $delayedPhasesCount,
                    'breakdown_by_phase'        => $detailedPhaseStats
                ],
                'active_alerts' => collect($alerts)->sortByDesc('days_late')->values()->all()
            ]
        ];
    }

    /**
     * Calcula las Métricas de Envejecimiento (Aging) para requerimientos activos[cite: 1].
     */
    public function calculateAgingMetrics()
    {
        $openRequirements = $this->kpiRepo->getOpenRequirementsForAging();
        
        if ($openRequirements->isEmpty()) {
            return ['success' => true, 'data' => []];
        }

        $requirementIds = $openRequirements->pluck('id')->toArray();
        $histories = $this->kpiRepo->getHistoriesByRequirementIds($requirementIds);

        $today = Carbon::now()->startOfDay();

        // Inicializamos los "buckets" para agrupar los requerimientos por antigüedad
        $agingBuckets = [
            '0_15_days'    => 0,
            '16_30_days'   => 0,
            '31_60_days'   => 0,
            'over_60_days' => 0
        ];

        $criticalAgingList = []; // Requerimientos con más de 30 días abiertos
        $totalGlobalDays = 0;
        $totalPhaseDays = 0;

        foreach ($openRequirements as $req) {
            // 1. Cálculo de Envejecimiento Global
            $creationDate = Carbon::parse($req->creation_date)->startOfDay();
            $globalAge = $creationDate->diffInDays($today); // Días desde que se creó
            $totalGlobalDays += $globalAge;

            // Agrupación en Buckets basada en el envejecimiento global
            if ($globalAge <= 15) {
                $agingBuckets['0_15_days']++;
            } elseif ($globalAge <= 30) {
                $agingBuckets['16_30_days']++;
            } elseif ($globalAge <= 60) {
                $agingBuckets['31_60_days']++;
            } else {
                $agingBuckets['over_60_days']++;
            }

            // 2. Cálculo de Envejecimiento en Fase Actual
            // Obtenemos el último movimiento registrado en el historial para este requerimiento
            $lastHistory = $histories->where('requirement_id', $req->id)
                                    ->sortByDesc('transitioned_at')
                                    ->first();

            $phaseAge = 0;
            $currentPhaseCode = 'Iniciando / Sin Fase';

            if ($lastHistory) {
                $transitionDate = Carbon::parse($lastHistory->transitioned_at)->startOfDay();
                $phaseAge = $transitionDate->diffInDays($today); // Días en su estatus actual
                $currentPhaseCode = $lastHistory->phase_status_code;
            } else {
                // Si no tiene historial, el tiempo en fase es igual a su tiempo global
                $phaseAge = $globalAge; 
            }

            $totalPhaseDays += $phaseAge;

            // 3. Extracción de Casos Críticos (Monitoreo)
            if ($globalAge >= 30) {
                $criticalAgingList[] = [
                    'rrti'                  => $req->rrti,
                    'global_days_open'      => $globalAge,
                    'current_phase'         => $currentPhaseCode,
                    'days_in_current_phase' => $phaseAge,
                    'is_stuck'              => $phaseAge >= 15 // Bandera si lleva más de 15 días sin moverse
                ];
            }
        }

        $totalOpen = $openRequirements->count();

        return [
            'success' => true,
            'data' => [
                'summary' => [
                    'total_open_requirements' => $totalOpen,
                    'average_global_age'      => $totalOpen > 0 ? round($totalGlobalDays / $totalOpen, 1) : 0,
                    'average_phase_age'       => $totalOpen > 0 ? round($totalPhaseDays / $totalOpen, 1) : 0,
                ],
                'aging_buckets' => $agingBuckets,
                'critical_requirements' => collect($criticalAgingList)->sortByDesc('global_days_open')->values()->all()
            ]
        ];
    }

    
}