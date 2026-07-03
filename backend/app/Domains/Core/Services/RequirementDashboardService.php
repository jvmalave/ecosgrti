<?php

namespace App\Domains\Core\Services;

use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Redis;


class RequirementDashboardService
{
    /**
     * Obtiene la lista de requerimientos usando Carga Híbrida (Redis + PostgreSQL)
     * Ahora con soporte para Búsqueda Reactiva.
     */

    public function getRequirements(string $status, int $limit, int $offset, ?string $searchTerm = null): array
    {
        $searchHash = $searchTerm ? md5(strtolower($searchTerm)) : 'all';
        
        $version = Redis::get('dashboard_version') ?: 1;
        
        // Bautizamos la llave incluyendo la versión (ej. req_v1_active_0_all)
        $cacheKey = "req_v{$version}_{$status}_{$offset}_{$searchHash}";

        // 2. Intento de Carga desde Redis
        $cachedData = Redis::get($cacheKey);
        if ($cachedData) {
            return json_decode($cachedData, true); 
        }

        // 3. Fallback a Base de Datos (Cache Miss)
        $query = DB::table('core.requirements as r')
            ->join('security.functional_consultants as fc', 'r.functional_consultant_id', '=', 'fc.id')
            ->join('security.persons as p', 'fc.person_id', '=', 'p.id')
            ->whereNull('r.deleted_at')
            ->select(
                'r.id', 
                'r.rrti', 
                'r.requirement_type', 
                'r.status',
                'r.creation_date', 
                'p.first_name', 
                'p.last_name',
                'r.snapshot_unit_name'
            );

        if ($status === 'active') {
            $query->where('r.status', '!=', 'FC'); 
        } else {
            $query->where('r.status', '=', 'FC'); 
        }

        if (!empty($searchTerm)) {
            $query->where('r.rrti', 'ILIKE', '%' . $searchTerm . '%');
        }

        $results = $query->orderBy('r.created_at', 'desc')
            ->offset($offset)
            ->limit($limit + 1)
            ->get();

        $hasMore = $results->count() > $limit;
        
        if ($hasMore) {
            $results->pop(); 
        }

        // 4. Estructurar la respuesta
        $response = [
            'data' => $results->values()->toArray(), // Protegemos el Array para Angular
            'meta' => [
                'has_more' => $hasMore,
                'total_returned' => count($results),
                'offset' => $offset,
                'limit' => $limit
            ]
        ];

        // 5. Guardar en Redis
        Redis::setex($cacheKey, 300, json_encode($response));

        return $response;
    }
}