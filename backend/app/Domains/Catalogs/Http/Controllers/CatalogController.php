<?php

namespace App\Domains\Catalogs\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;

class CatalogController extends Controller
{
    public function getRequirementTypes(): JsonResponse
    {
        $types = DB::table('catalogs.requirement_types')
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return response()->json([
            'message' => 'Tipos de requerimiento recuperados exitosamente',
            'data' => $types
        ]);
    }

    public function getManagementTypes(): JsonResponse
    {
        $types = DB::table('catalogs.management_types')
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return response()->json([
            'message' => 'Tipos de gestión recuperados exitosamente',
            'data' => $types
        ]);
    }
}