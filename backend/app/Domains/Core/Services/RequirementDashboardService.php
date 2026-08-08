<?php

namespace App\Domains\Core\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class RequirementDashboardService
{
    /**
     * Obtiene la lista de requerimientos usando Carga Híbrida (Redis + PostgreSQL)
     * Ahora con soporte para Búsqueda Reactiva y Hard-Gates (COR Enabled).
     */
    public function getRequirements(string $status, int $limit, int $offset, ?string $searchTerm = null): array
    {
        $searchHash = $searchTerm ? md5(strtolower($searchTerm)) : 'all';
        
        // ⚠️ IMPORTANTE: Subimos la versión de caché a 2 para forzar la invalidación 
        // y asegurar que el frontend reciba la nueva columna 'dt_closed_roles_count'
        $version = Redis::get('dashboard_version') ?: 2; 
        
        // Nombrar la llave incluyendo la versión (ej. req_v2_active_0_all)
        $cacheKey = "req_v{$version}_{$status}_{$offset}_{$searchHash}";

        // Intento de Carga desde Redis
        $cachedData = Redis::get($cacheKey);
        if ($cachedData) {
            return json_decode($cachedData, true); 
        }

        // Fallback a Base de Datos (Cache Miss)
        $query = DB::table('core.requirements as r')
            ->join('security.functional_consultants as fc', 'r.functional_consultant_id', '=', 'fc.id')
            ->join('security.persons as p', 'fc.person_id', '=', 'p.id')
            ->leftJoin('workflow.atf_agreements as aa', 'r.id', '=', 'aa.requirement_id')
            ->whereNull('r.deleted_at')
            
            // Seleccionar solo las columnas nativas como un arreglo estricto
            ->select([
                'r.id', 
                'r.rrti', 
                'r.requirement_type', 
                'r.status',
                'r.creation_date', 
                'p.first_name', 
                'p.last_name',
                'r.snapshot_unit_name',
                'r.management_type'
            ])
            
            // Inyectar los cálculos booleanos y subconsultas usando selectRaw de forma aislada
            ->selectRaw('COUNT(aa.id) > 0 as has_atf_agreements')
            ->selectRaw('(SELECT COUNT(*) FROM workflow.requirements_roles WHERE requirements_roles.requirement_id = r.id) as roles_count')
            ->selectRaw('(SELECT COUNT(*) FROM workflow.requirements_roles WHERE requirements_roles.requirement_id = r.id) > 0 as has_roles')
            
            // Subconsulta para contar exclusivamente los roles en estado 'CLOSED' de la fase de Diseño Técnico (DT)
            ->selectRaw("(
                SELECT COUNT(dr.id) 
                FROM workflow.dt_roles dr 
                INNER JOIN workflow.requirements_roles rr ON dr.requirement_role_id = rr.id 
                WHERE rr.requirement_id = r.id AND dr.status = 'CLOSED'
            ) as dt_closed_roles_count")
            
            ->groupBy('r.id', 'fc.id', 'p.id');

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

        // Estructurar la respuesta
        $response = [
            'data' => $results->values()->toArray(), // Protegemos el Array para Angular
            'meta' => [
                'has_more' => $hasMore,
                'total_returned' => count($results),
                'offset' => $offset,
                'limit' => $limit
            ]
        ];

        // Guardar en Redis
        Redis::setex($cacheKey, 300, json_encode($response));

        return $response;
    }
}