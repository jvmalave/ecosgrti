<?php

namespace App\Domains\Security\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Security\Http\Requests\StoreUnifiedPersonRequest;
use App\Domains\Security\Http\Requests\UpdateUnifiedPersonRequest;
use App\Domains\Security\Services\UnifiedPersonService;
use App\Domains\Security\Http\Resources\UnifiedPersonResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UnifiedPersonController extends Controller
{
    protected UnifiedPersonService $unifiedPersonService;

    public function __construct(UnifiedPersonService $unifiedPersonService)
    {
        $this->unifiedPersonService = $unifiedPersonService;
    }

    /**
     * Almacena una nueva identidad (Código que ya habíamos acordado).
     */
    public function store(StoreUnifiedPersonRequest $request): JsonResponse
    {
        $validatedData = $request->validated();
        $person = $this->unifiedPersonService->createUnifiedIdentity($validatedData);
        $person->load(['user', 'functionalConsultant', 'cspeConsultant']);

        return response()->json([
            'success' => true,
            'message' => 'Identidad creada exitosamente',
            'data'    => new UnifiedPersonResource($person)
        ], 201);
    }

    /**
     * Orquesta la actualización de una identidad existente.
     */
    public function update(UpdateUnifiedPersonRequest $request, string $id): JsonResponse
    {
        // 1. Validamos los datos asegurando que se ignoró el ID actual
        $validatedData = $request->validated();

        // 2. Delegamos la transacción de actualización al servicio
        $person = $this->unifiedPersonService->updateUnifiedIdentity($id, $validatedData);

        // 3. Cargamos las relaciones dinámicas (Eager Loading) para el Resource
        $person->load(['user', 'functionalConsultant', 'cspeConsultant']);

        // 4. Retornamos la respuesta (Happy Path)
        return response()->json([
            'success' => true,
            'message' => 'Ficha Unificada actualizada exitosamente',
            'data'    => new UnifiedPersonResource($person)
        ], 200);
    }

    /**
     * Retorna el listado paginado de personas junto con sus perfiles asignados.
     * Corresponde a la petición GET /mdm/persons
     */
    public function index(): AnonymousResourceCollection
    {
        // 1. Obtenemos la colección paginada desde el servicio
        $identities = $this->unifiedPersonService->getAllUnifiedIdentities();

        // 2. Retornamos la colección a través del API Resource
        return UnifiedPersonResource::collection($identities);
    }

    /**
     * Extrae el detalle de una sola persona y sus perfiles asociados mediante su UUID.
     * Corresponde a la petición GET /mdm/persons/{id}
     */
    public function show(string $id): JsonResponse
    {
        // 1. Obtenemos la entidad única desde el servicio
        $person = $this->unifiedPersonService->getUnifiedIdentityById($id);

        // 2. Retornamos el recurso formateado
        return response()->json([
            'success' => true,
            'data' => new UnifiedPersonResource($person)
        ], 200);
    }
    
    /**
     * Ejecuta la inhabilitación temporal de la ficha unificada (Soft Delete).
     */
    public function destroy(string $id): JsonResponse
    {
        // 1. Delegamos la transacción de borrado lógico al servicio
        $this->unifiedPersonService->deleteUnifiedIdentity($id);

        // 2. Retornamos la respuesta HTTP 200 OK
        return response()->json([
            'success' => true,
            'message' => 'Ficha Unificada desactivada exitosamente'
        ], 200);
    }

    /**
     * Retorna el catálogo de unidades solicitantes para los selectores.
     *
     * @return JsonResponse
     */
    public function getRequestingUnits(): JsonResponse
    {
        $units = $this->unifiedPersonService->getRequestingUnits();

        return response()->json([
            'success' => true,
            'data' => $units
        ]);
    }
}
