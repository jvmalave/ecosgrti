<?php

namespace App\Domains\Security\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Security\Services\FunctionalConsultantService;
use Illuminate\Http\JsonResponse;

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
    public function lookupOrganizacional(int $personaId): JsonResponse
    {
        // 1. Delegamos la lógica al Servicio
        $datos = $this->consultantService->getOrganizationalGraph($personaId);

        // 2. Retornamos la respuesta HTTP
        return response()->json($datos);
    }
}