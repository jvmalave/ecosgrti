<?php

declare(strict_types=1);

namespace App\Domains\Reporting\Services;

use App\Domains\Security\Models\User;
use Illuminate\Database\Eloquent\Collection;

class ReportService
{
    /**
     * Extrae el listado completo de identidades para el directorio MDM.
     * 
     * @return Collection<int, User>
     */
    public function getMdmDirectoryData(): Collection
    {
        // NOTA DE VERIFICACIÓN: Asumimos que existe la relación 'person' en el modelo User.
        return User::withTrashed()
            ->with(['person' => function ($query) {
                // Seleccionamos solo los campos necesarios de la tabla persons para optimizar memoria
                $query->select('id', 'first_name', 'last_name', 'email'); // Ajustar si la FK es diferente
            }])
            ->select('id', 'name', 'email', 'roles', 'deleted_at') // Importante: Si la relación usa un person_id, debe incluirse en este select
            ->orderBy('name', 'asc')
            ->get();
    }

    /**
     * Extrae toda la data relacional necesaria para emitir un Acta de Cierre.
     * Carga ansiosamente (Eager Loading) a los involucrados para evitar consultas N+1.
     * 
     * @param string $requirementId Identificador UUID del requerimiento
     */
    public function getRequirementClosureData(string $requirementId)
    {
        return \App\Domains\Core\Models\Requirement::with([
            'functionalConsultant.person',
            'cspeConsultants.person',
            'atfAgreements',
            'roles',         
            'deliverables',
            'phaseHistories' => function ($query) {
                $query->orderBy('created_at', 'asc');
            },
            'cerTicket', 'papOrder', 'auTicket', 'ceeTicket',
            
            // Colecciones específicas por fase para validar el estatus de cada componente
            'dtRoles', 'corRoles', 'piRoles', 'cerRoles', 'papRoles', 'auRoles',
            'coeDeliverables', 'ceeDeliverables'
        ])->findOrFail($requirementId);
    }

    public function getRequirementClosureDataByRrti(string $rrti)
    {
        return \App\Domains\Core\Models\Requirement::with([
            'functionalConsultant.person',
            'cspeConsultants.person',
            'atfAgreements',
            'roles',         
            'deliverables',
            'phaseHistories' => function ($query) {
                $query->orderBy('created_at', 'asc');
            },
            'cerTicket', 'papOrder', 'auTicket', 'ceeTicket',
            'dtRoles', 'corRoles', 'piRoles', 'cerRoles', 'papRoles', 'auRoles',
            'coeDeliverables', 'ceeDeliverables'
        ])->where('rrti', $rrti)->firstOrFail();
    }
}