<?php

namespace App\Domains\Core\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use App\Domains\Core\Dictionaries\CacheKeyDictionary; 

class RequirementDashboardService
{
    public function getRequirements(string $status, int $limit, int $offset, ?string $searchTerm = null): array
    {
        $searchHash = $searchTerm ? md5(strtolower($searchTerm)) : 'all';
        
        // USO DEL DICCIONARIO: Obtenemos la versión global del dashboard
        $versionKey = CacheKeyDictionary::globalDashboardVersion();
        $version = Redis::get($versionKey) ?: 3; 
        
        $cacheKey = "req_v{$version}_{$status}_{$offset}_{$searchHash}";

        $cachedData = Redis::get($cacheKey);
        if ($cachedData) {
            return json_decode($cachedData, true); 
        }

        $query = DB::table('core.requirements as r')
            ->join('security.functional_consultants as fc', 'r.functional_consultant_id', '=', 'fc.id')
            ->join('security.persons as p', 'fc.person_id', '=', 'p.id')
            ->leftJoin('workflow.atf_agreements as aa', 'r.id', '=', 'aa.requirement_id')
            ->whereNull('r.deleted_at')
            ->select([
                'r.id', 'r.rrti', 'r.requirement_type', 'r.status',
                'r.creation_date', 'p.first_name', 'p.last_name',
                'r.snapshot_unit_name', 'r.management_type'
            ])
            ->selectRaw('COUNT(aa.id) > 0 as has_atf_agreements')
            ->selectRaw('(SELECT COUNT(*) FROM workflow.requirements_roles WHERE requirements_roles.requirement_id = r.id) as roles_count')
            ->selectRaw('(SELECT COUNT(*) FROM workflow.requirements_roles WHERE requirements_roles.requirement_id = r.id) > 0 as has_roles')
            ->selectRaw('(SELECT COUNT(*) FROM workflow.deliverables WHERE workflow.deliverables.requirement_id = r.id) as deliverables_count')
            ->selectRaw("(
                SELECT COUNT(dr.id) 
                FROM workflow.dt_roles dr 
                INNER JOIN workflow.requirements_roles rr ON dr.requirement_role_id = rr.id 
                WHERE rr.requirement_id = r.id AND dr.status = 'CLOSED'
            ) as dt_closed_roles_count")
            
            
            ->selectRaw("(
                SELECT string_agg(SPLIT_PART(ph.phase_status_code, '-', 1), ',') 
                FROM workflow.requirement_phase_history ph 
                WHERE ph.requirement_id = r.id AND ph.phase_status_code LIKE '%-C'
            ) as historical_frozen_string")
            
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
        if ($hasMore) { $results->pop(); }

        // TRANSFORMACIÓN
        $results->transform(function ($item) {
            $item->frozen_phases = [];
            
            if ($item->status === 'RC') {
                $item->frozen_phases = ['ATF', 'DT', 'COR', 'COE', 'PI', 'CER', 'CEE', 'PAP', 'AU'];
            } elseif (!empty($item->historical_frozen_string)) {
                $item->frozen_phases = array_values(array_unique(explode(',', $item->historical_frozen_string)));
            }

            unset($item->historical_frozen_string);
            return $item;
        });

        $response = [
            'data' => $results->values()->toArray(),
            'meta' => [
                'has_more' => $hasMore,
                'total_returned' => count($results),
                'offset' => $offset,
                'limit' => $limit
            ]
        ];

        Redis::setex($cacheKey, 300, json_encode($response));

        return $response;
    }
}