<?php

namespace App\Domains\Reporting\Repositories;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class KpiReadRepository
{
    /**
     * Obtiene el historial de los cierres reales de la fase AU-C.
     * KPI: OTD (On Time Delivery)
     */
    public function getClosedAuPhases($startDate = null, $endDate = null)
    {
        $query = DB::table('workflow.requirement_phase_history as rph')
            ->join('core.requirements as r', 'rph.requirement_id', '=', 'r.id')
            ->where('rph.phase_status_code', 'AU-C')
            ->whereNull('r.deleted_at')
            ->select('rph.requirement_id', DB::raw('MAX(rph.transitioned_at) as transitioned_at'))
            ->groupBy('rph.requirement_id');

        if ($startDate && $endDate) {
            $query->whereBetween('rph.transitioned_at', [
                Carbon::parse($startDate)->startOfDay(),
                Carbon::parse($endDate)->endOfDay()
            ]);
        }

        return $query->get();
    }

    /**
     * Obtiene las fechas planificadas (Inicio y Fin) cruzando con las estimaciones.
     * Blindaje: Se une con requirements para ignorar estimaciones de requerimientos borrados.
     */
    public function getImplementationEstimations(array $requirementIds)
    {
        return DB::table('core.estimated_phases as ep')
            ->join('core.schedule_estimations as se', 'ep.schedule_estimation_id', '=', 'se.id')
            ->join('core.requirements as r', 'se.requirement_id', '=', 'r.id')
            ->where('ep.phase_name', 'IMPLEMENTACION')
            ->whereIn('se.requirement_id', $requirementIds)
            ->whereNull('r.deleted_at')
            ->select('se.requirement_id', 'ep.start_date', 'ep.end_date')
            ->get();
    }

    /**
     * Obtiene el historial de los inicios reales de la fase PAP-I.
     */
    public function getStartedPapPhases(array $requirementIds)
    {
        return DB::table('workflow.requirement_phase_history as rph')
            ->join('core.requirements as r', 'rph.requirement_id', '=', 'r.id')
            ->where('rph.phase_status_code', 'PAP-I')
            ->whereNull('r.deleted_at')
            ->whereIn('rph.requirement_id', $requirementIds)
            ->select('rph.requirement_id', DB::raw('MAX(rph.transitioned_at) as transitioned_at'))
            ->groupBy('rph.requirement_id')
            ->get();
    }

    /**
     * Obtiene todas las fases estimadas de los requerimientos que no están cerrados.
     * KPI: Desviación de Cronograma y Alertas
     */
    public function getActiveEstimations()
    {
        return DB::table('core.estimated_phases as ep')
            ->join('core.schedule_estimations as se', 'ep.schedule_estimation_id', '=', 'se.id')
            ->join('core.requirements as r', 'se.requirement_id', '=', 'r.id')
            ->whereNull('r.deleted_at')
            ->whereNotIn('r.status', ['RF', 'AU-C', 'Cancelado', 'DET'])
            ->select('se.requirement_id', 'r.rrti', 'ep.phase_name', 'ep.start_date', 'ep.end_date')
            ->get();
    }

    /**
     * Obtiene el historial completo de estados para un grupo de requerimientos.
     * Blindaje: Previene duplicados por retrocesos (bucles de fase) tomando la última fecha.
     */
    public function getHistoriesByRequirementIds(array $requirementIds)
    {
        return DB::table('workflow.requirement_phase_history as rph')
            ->join('core.requirements as r', 'rph.requirement_id', '=', 'r.id')
            ->whereIn('rph.requirement_id', $requirementIds)
            ->whereNull('r.deleted_at')
            ->select('rph.requirement_id', 'rph.phase_status_code', DB::raw('MAX(rph.transitioned_at) as transitioned_at'))
            ->groupBy('rph.requirement_id', 'rph.phase_status_code')
            ->get();
    }

    /**
     * Obtiene todos los requerimientos que siguen en proceso (abiertos).
     * KPI: Envejecimiento (Aging)
     */
    public function getOpenRequirementsForAging()
    {
        return DB::table('core.requirements')
            ->whereNull('deleted_at')
            ->whereNotIn('status', ['RF', 'AU-C', 'Cancelado', 'DET'])
            ->select('id', 'rrti', 'status', 'creation_date')
            ->get();
    }
}