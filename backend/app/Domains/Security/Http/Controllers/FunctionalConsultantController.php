<?php

namespace App\Domains\Security\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Security\Services\FunctionalConsultantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class FunctionalConsultantController extends Controller
{
    protected FunctionalConsultantService $consultantService;

    // Inyectamos el servicio en el constructor
    public function __construct(FunctionalConsultantService $consultantService)
    {
        $this->consultantService = $consultantService;
    }

    /**
     * Endpoint para el autocompletado atómico (GET)
     */
    // public function lookupOrganizacional(int $personaId): JsonResponse
    // {
    //     // 1. Delegamos la lógica al Servicio
    //     $datos = $this->consultantService->getOrganizationalGraph($personaId);

    //     // 2. Retornamos la respuesta HTTP
    //     return response()->json($datos);
    // }


    /**
     * Recupera el grafo organizacional atómico basado en el ID de la persona.
     * * @param string $personaId (Cambiado de int a string para soportar UUIDs)
     */
    public function lookupOrganizacional(string $personaId): JsonResponse
    {
        // Navegamos por las relaciones normalizadas usando JOINs a través de los esquemas
        $data = DB::table('security.functional_consultants as fc')
            ->join('catalogs.requesting_units as ru', 'fc.requesting_unit_id', '=', 'ru.id')
            ->join('catalogs.systems as sys', 'ru.system_id', '=', 'sys.id')
            ->join('catalogs.societies as soc', 'sys.society_id', '=', 'soc.id')
            ->where('fc.person_id', $personaId)
            ->select(
                'fc.person_id as persona_id',
                'fc.id as functional_consultant_id',
                'ru.name as requesting_unit_name',
                'sys.name as system_name',
                'soc.name as society_name'
            )
            ->first();

        // Si el consultor no tiene unidad asignada (o el ID no existe)
        if (!$data) {
            return response()->json([
                'message' => 'No se pudo resolver la jerarquía de este consultor.',
                'data' => null
            ], 404);
        }

        return response()->json([
            'message' => 'Grafo organizacional recuperado exitosamente',
            'data' => $data
        ]);
    }
}
