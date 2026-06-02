<?php

namespace App\Domains\Security\Services;

use App\Domains\Security\Models\FunctionalConsultant;
use Illuminate\Support\Facades\Cache;

class FunctionalConsultantService
{
    /**
     * Obtiene el grafo organizacional del consultor usando caché (RN-02)
     */
    public function getOrganizationalGraph(int $personaId)
    {
        $tiempoEnSegundos = 86400; // 24 horas

        return Cache::remember("grafo_persona_" . $personaId, $tiempoEnSegundos, function () use ($personaId) {
            return FunctionalConsultant::where('person_id', $personaId)
                ->with(['sociedad', 'sistema', 'unidad'])
                ->firstOrFail();
        });
    }
}