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
        // 1. Modificar la llave de caché para que sea única por cada búsqueda
        // Usamos md5 para generar un sufijo corto y seguro, o 'all' si no hay búsqueda.
        $searchHash = $searchTerm ? md5(strtolower($searchTerm)) : 'all';
        $cacheKey = "req_{$status}_{$offset}_{$searchHash}";

        // 2. Intento de Carga desde Redis (Cache Hit)
        $cachedData = Redis::get($cacheKey);
        if ($cachedData) {
            return json_decode($cachedData, true); // Respuesta en milisegundos
        }

        // 3. Fallback a Base de Datos (Cache Miss)
        $query = DB::table('core.requirements as r')
            ->join('security.functional_consultants as fc', 'r.functional_consultant_id', '=', 'fc.id')
            ->join('security.persons as p', 'fc.person_id', '=', 'p.id')
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

        // Lógica de separación de contextos (Proceso vs Histórico)
        if ($status === 'active') {
            $query->where('r.status', '!=', 'FC'); // Lo que NO esté Finalizado/Cerrado
        } else {
            $query->where('r.status', '=', 'FC'); // Solo histórico
        }

        // -------------------------------------------------------------
        // Lógica del Buscador Reactivo
        // -------------------------------------------------------------
        if (!empty($searchTerm)) {
            $query->where('r.rrti', 'ILIKE', '%' . $searchTerm . '%');
        }

        // Ejecución con el truco "limit + 1" para calcular el "has_more" eficientemente
        $results = $query->orderBy('r.created_at', 'desc')
            ->offset($offset)
            ->limit($limit + 1)
            ->get();

        $hasMore = $results->count() > $limit;
        
        // Si trajimos el extra, lo sacamos de la lista final para enviar solo el límite solicitado
        if ($hasMore) {
            $results->pop(); 
        }

        // 4. Estructurar la respuesta
        $response = [
            'data' => $results,
            'meta' => [
                'has_more' => $hasMore,
                'total_returned' => $results->count(),
                'offset' => $offset,
                'limit' => $limit
            ]
        ];

        // 5. Blindar Redis: Guardar el dataset con un TTL de 300 segundos
        Redis::setex($cacheKey, 300, json_encode($response));

        return $response;
    }
}