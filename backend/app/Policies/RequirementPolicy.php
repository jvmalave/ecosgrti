<?php

namespace App\Policies;

use App\Domains\Security\Models\User;
use App\Domains\Core\Models\Requirement;

class RequirementPolicy
{
    /**
     * Determina si el usuario puede gestionar el requerimiento (avanzar fases, editar, cerrar).
     */
    public function manage(User $user, Requirement $requirement): bool
    {
        // 1. Extraemos los roles del usuario (que anoche nos aseguramos que fuera un array)
        $roles = $user->roles ?? [];
        
        // 2. Poder absoluto: Si es Coordinador o Administrador, siempre retorna TRUE
        if (in_array('Coord', $roles) || in_array('Admin', $roles) || in_array('admin', $roles)) {
            return true;
        }

        // 3. Control por Recursos (ABAC): Si es Consultor CSPE, verificamos si está asignado a este requerimiento
        // Buscamos en la relación si existe un registro donde el person_id coincida con el id del usuario
        return $requirement->cspeConsultants()->where('person_id', $user->id)->exists();
    }
}