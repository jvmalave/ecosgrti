<?php

namespace App\Domains\Reporting\Repositories;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class KpiReadRepository
{
    /**
     * Obtiene el historial de los cierres reales de la fase AU-C.
     */
    public function getClosedAuPhases($startDate = null, $endDate = null)
    {
        $query = DB::table('workflow.requirement_phase_history') 
            ->where('phase_status_code', 'AU-C')
            ->select('requirement_id', 'transitioned_at');

        if ($startDate && $endDate) {
            $query->whereBetween('transitioned_at', [
                Carbon::parse($startDate)->startOfDay(),
                Carbon::parse($endDate)->endOfDay()
            ]);
        }

        return $query->get();
    }


    /**
     * Obtiene las fechas planificadas (Inicio y Fin) cruzando con las estimaciones.
     */
    public function getImplementationEstimations(array $requirementIds)
    {
        return DB::table('core.estimated_phases as ep')
            ->join('core.schedule_estimations as se', 'ep.schedule_estimation_id', '=', 'se.id')
            ->where('ep.phase_name', 'IMPLEMENTACION')
            ->whereIn('se.requirement_id', $requirementIds)
            ->select('se.requirement_id', 'ep.start_date', 'ep.end_date') // Agregamos start_date
            ->get();
    }

    /**
     * Obtiene el historial de los inicios reales de la fase PAP-I.
     */
    public function getStartedPapPhases(array $requirementIds)
    {
        return DB::table('workflow.requirement_phase_history')
            ->where('phase_status_code', 'PAP-I')
            ->whereIn('requirement_id', $requirementIds)
            ->select('requirement_id', 'transitioned_at')
            ->get();
    }

    /**
     * Obtiene todas las fases estimadas de los requerimientos que no están cerrados.
     */
    public function getActiveEstimations()
    {
        return DB::table('core.estimated_phases as ep')
            ->join('core.schedule_estimations as se', 'ep.schedule_estimation_id', '=', 'se.id')
            ->join('core.requirements as r', 'se.requirement_id', '=', 'r.id')
            ->whereNotIn('r.status', ['Cerrado', 'Requerimiento Finalizado'])
            ->select('se.requirement_id', 'r.rrti', 'ep.phase_name', 'ep.start_date', 'ep.end_date')
            ->get();
    }

    /**
     * Obtiene el historial completo de estados para un grupo de requerimientos.
     */
    public function getHistoriesByRequirementIds(array $requirementIds)
    {
        return DB::table('workflow.requirement_phase_history')
            ->whereIn('requirement_id', $requirementIds)
            ->select('requirement_id', 'phase_status_code', 'transitioned_at')
            ->get();
    }


    /**
     * Obtiene todos los requerimientos que siguen en proceso (abiertos).
     */
    public function getOpenRequirementsForAging()
    {
        return DB::table('core.requirements')
            ->whereNotIn('status', ['Cerrado', 'Requerimiento Finalizado', 'Cancelado'])
            ->select('id', 'rrti', 'status', 'creation_date')
            ->get();
    }
}