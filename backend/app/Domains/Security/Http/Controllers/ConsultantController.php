<?php

namespace App\Domains\Security\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use App\Domains\Security\Docs\ConsultantDocs;

class ConsultantController extends Controller implements ConsultantDocs
{
    public function getFunctionalConsultants(): JsonResponse
    {
        $consultants = DB::table('security.functional_consultants as fc')
            ->join('security.persons as p', 'fc.person_id', '=', 'p.id')
            ->select('p.id as persona_id', 'p.first_name', 'p.last_name')
            ->orderBy('p.first_name')
            ->get()
            ->map(function ($item) {
                return [
                    'persona_id' => $item->persona_id,
                    'full_name' => $item->first_name . ' ' . $item->last_name
                ];
            });

        return response()->json([
            'message' => 'Consultores funcionales recuperados',
            'data' => $consultants
        ]);
    }

    public function getCspeConsultants(): JsonResponse
    {
        $consultants = DB::table('security.cspe_consultants as cc')
            ->join('security.persons as p', 'cc.person_id', '=', 'p.id')
            ->select('cc.id as cspe_id', 'p.first_name', 'p.last_name')
            ->orderBy('p.first_name')
            ->get()
            ->map(function ($item) {
                return [
                    'cspe_id' => $item->cspe_id,
                    'full_name' => $item->first_name . ' ' . $item->last_name
                ];
            });

        return response()->json([
            'message' => 'Consultores CSPE recuperados',
            'data' => $consultants
        ]);
    }
}