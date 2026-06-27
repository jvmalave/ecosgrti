<?php

namespace App\Domains\Security\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Security\Services\FunctionalConsultantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class FunctionalConsultantController extends Controller
{
    protected FunctionalConsultantService $consultantService;

    // Inyectamos el servicio en el constructor
    public function __construct(FunctionalConsultantService $consultantService)
    {
        $this->consultantService = $consultantService;
    }

    /**
     * Recupera el grafo organizacional atómico basado en el ID de la persona.
     * * @param string $personaId (Cambiado de int a string para soportar UUIDs)
     */
    

    public function lookupOrganizacional(string $personaId): JsonResponse
    {
        // 1. Definimos la misma llave exacta que busca nuestro test y nuestro servicio
        $cacheKey = "grafo_persona_" . $personaId;

        // 2. Envolvemos tu excelente consulta DB::table dentro de la Caché (Redis)
        // Se guardará por 86400 segundos (24 horas)
        $data = Cache::remember($cacheKey, 86400, function () use ($personaId) {
            return DB::table('security.functional_consultants as fc')
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
        });

        // Si el consultor no tiene unidad asignada (o el ID no existe)
        if (!$data) {
            // Limpiamos la caché para no guardar un "null" inútilmente
            Cache::forget($cacheKey); 
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

    /**
     * Devuelve la lista de todos los consultores funcionales (Para llenar los <select>)
     */
    public function index(): JsonResponse
    {

        try {
            $consultants = $this->consultantService->getActiveConsultants();

            return response()->json([
                'message' => 'Consultores funcionales recuperados',
                'data' => $consultants
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al recuperar consultores',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
