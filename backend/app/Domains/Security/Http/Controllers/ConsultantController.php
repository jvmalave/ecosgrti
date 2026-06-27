<?php

namespace App\Domains\Security\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use App\Domains\Security\Docs\ConsultantDocs;
use Illuminate\Support\Facades\Cache;

class ConsultantController extends Controller implements ConsultantDocs
{


  public function getFunctionalConsultants(): JsonResponse
  {
    // 🟢 Reponemos la caché por 3600 segundos (1 hora) con la llave v4
    $consultants = Cache::remember('list_functional_consultants_v4', 3600, function () {
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
        ->get()
        ->toArray();
    });

    return response()->json([
      'message' => 'Consultores funcionales con jerarquía recuperados exitosamente',
      'data' => $consultants
    ]);
  }

  public function getCspeConsultants(): JsonResponse
  {
    // (Este lo dejamos intacto, aunque también podrías cachearlo en el futuro si lo deseas)
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
