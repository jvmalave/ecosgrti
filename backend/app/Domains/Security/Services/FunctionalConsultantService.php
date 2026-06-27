<?php

namespace App\Domains\Security\Services;

use App\Domains\Security\Models\FunctionalConsultant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

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

    /**
     * Obtiene la lista completa de consultores funcionales activos con ambos IDs.
     */
    // public function getActiveConsultants()
    // {
    //     // 🟢 Llave V3: Forzamos a Redis a borrar lo viejo y guardar la nueva estructura con la jerarquía
    //     return Cache::remember('list_functional_consultants_v3', 3600, function () {
    //         return DB::table('security.functional_consultants as fc')
    //             ->join('security.persons as p', 'fc.person_id', '=', 'p.id')
    //             // 🟢 NUEVO: Unimos la jerarquía organizacional desde el esquema catalogs
    //             ->join('catalogs.requesting_units as ru', 'fc.requesting_unit_id', '=', 'ru.id')
    //             ->join('catalogs.systems as sys', 'ru.system_id', '=', 'sys.id')
    //             ->join('catalogs.societies as soc', 'sys.society_id', '=', 'soc.id')
    //             ->select(
    //                 'fc.id as id',                   
    //                 'p.id as persona_id',            
    //                 DB::raw("CONCAT(p.first_name, ' ', p.last_name) as full_name"),
    //                 // 🟢 Los nuevos campos que Angular está esperando
    //                 'soc.name as society_name',
    //                 'sys.name as system_name',
    //                 'ru.name as unit_name' // Le ponemos unit_name porque así lo declaramos en Angular
    //             )
    //             ->get();
    //     });
    // }

    public function getActiveConsultants()
    {
        // 🟢 MODO VERDAD ABSOLUTA: Sin caché, consulta directa a la BD
        return DB::table('security.functional_consultants as fc')
            ->join('security.persons as p', 'fc.person_id', '=', 'p.id')
            ->join('catalogs.requesting_units as ru', 'fc.requesting_unit_id', '=', 'ru.id')
            ->join('catalogs.systems as sys', 'ru.system_id', '=', 'sys.id')
            ->join('catalogs.societies as soc', 'sys.society_id', '=', 'soc.id')
            ->select(
                'fc.id as id',                   
                'p.id as persona_id',            
                DB::raw("CONCAT(p.first_name, ' ', p.last_name) as full_name"),
                'soc.name as society_name',
                'sys.name as system_name',
                'ru.name as unit_name'
            )
            ->get();
    }
}