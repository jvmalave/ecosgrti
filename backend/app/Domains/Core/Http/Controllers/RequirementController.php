<?php

declare(strict_types=1);

namespace App\Domains\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use App\Domains\Core\Http\Requests\StoreRequirementRequest;
use App\Domains\Core\Services\RequirementService;
use App\Domains\Core\Docs\RequirementDocs;
use App\Domains\Core\Services\RequirementDashboardService;
use App\Domains\Core\Http\Requests\StoreEstimationRequest;
use App\Domains\Core\Exceptions\SequentialityViolationException;
use App\Domains\Security\Services\SpecialOperationService;
use App\Domains\Security\Http\Requests\ValidateSpecialKeyRequest;
use App\Domains\Core\Http\Requests\DestroyRequirementRequest;
use Exception;

class RequirementController extends Controller implements RequirementDocs
{
  /**
   * Inyección estricta de Servicios del Dominio (CQRS)
   * Utilizando la promoción de propiedades de PHP 8 para un código más limpio.
   */
  public function __construct(
    private readonly RequirementService $requirementService,
    private readonly RequirementDashboardService $dashboardService,
    private readonly SpecialOperationService $specialOperationService
  ) {}

  /**
   * US05: Dashboard con Carga Híbrida (Redis + PostgreSQL)
   */
  public function index(Request $request): JsonResponse
  {
    $status = $request->query('status', 'active');
    $limit = (int) $request->query('limit', '10');
    $offset = (int) $request->query('offset', '0');
    $search = $request->query('search');

    if ($limit > 50) $limit = 50;
    if ($offset < 0) $offset = 0;

    $data = $this->dashboardService->getRequirements($status, $limit, $offset, $search);

    return response()->json($data, 200);
  }

  /**
   * US04: El controlador ahora solo Orquesta la entrada y salida
   */
  public function store(StoreRequirementRequest $request): JsonResponse
  {
    try {
      $result = $this->requirementService->createRequirement(
        $request->validated(),
        $request->file('it_request_doc'),
        $request->file('needs_spreadsheet')
      );

      return response()->json([
        'message' => 'Requerimiento creado exitosamente.',
        'data' => $result
      ], 201);
    } catch (Exception $e) {
      Log::error('Error crítico en RequirementController@store (RRTI: ' . $request->rrti . '): ' . $e->getMessage());

      return response()->json([
        'message' => 'Ocurrió un error interno al registrar el requerimiento.',
        'error' => config('app.debug') ? $e->getMessage() : 'Error de servidor.'
      ], 500);
    }
  }

  /**
   * US23 - Registrar Estimación (Momento 2)
   */
  public function registerEstimation(StoreEstimationRequest $request, string $id): JsonResponse
  {
    try {
      // Casteo explícito a string para cumplir el Strict Typing
      $userId = (string) auth()->id();

      $estimation = $this->requirementService->registerEstimation(
        $id,
        $request->validated('phases'),
        $userId
      );

      return response()->json([
        'success' => true,
        'message' => 'Estimación registrada exitosamente. El requerimiento ha avanzado a la fase ATF.',
        'data' => $estimation->load('estimatedPhases')
      ], 201);
    } catch (SequentialityViolationException $e) {
      return response()->json([
        'success' => false,
        'message' => 'Error de coherencia cronológica.',
        'errors' => ['secuencia' => $e->getMessage()]
      ], 422);
    } catch (Exception $e) {
      return response()->json([
        'success' => false,
        'message' => 'No se pudo procesar la estimación.',
        'errors' => ['sistema' => $e->getMessage()]
      ], 500);
    }
  }

  /**
   * US24/CU-009 - Fase 1: Solicitar Ticket de Borrado Lógico
   */
  public function requestDeletionTicket(ValidateSpecialKeyRequest $request): JsonResponse
  {
    try {
      $userId = (string) auth()->id();
      $key = $request->validated('special_key');

      $ticketId = $this->specialOperationService->issueDeletionTicket($userId, $key);

      return response()->json([
        'success' => true,
        'message' => 'Autorización temporal concedida. Dispone de 2 minutos para confirmar la eliminación.',
        'data' => [
          'deletion_ticket' => $ticketId,
          'expires_in_seconds' => 120
        ]
      ], 200);
    } catch (Exception $e) {
      return response()->json([
        'success' => false,
        'message' => 'Acceso Denegado.',
        'errors' => ['auth' => $e->getMessage()]
      ], 401);
    }
  }

  /**
   * US24/CU-009 - Fase 2: Ejecutar Borrado Lógico
   */
  public function destroy(DestroyRequirementRequest $request, string $id): JsonResponse
  {
    try {
      $userId = (string) auth()->id();
      $ticketId = $request->validated('deletion_ticket');
      $justification = $request->validated('justification');

      // 1. Consumimos el ticket (Si falla, lanza excepción y aborta)
      $this->specialOperationService->consumeDeletionTicket($userId, $ticketId);

      // 2. Ejecutamos el borrado lógico transaccional
      $this->requirementService->softDeleteRequirement($id, $userId, $justification);

      return response()->json([
        'success' => true,
        'message' => 'El requerimiento ha sido eliminado del sistema de forma segura.',
      ], 200);
    } catch (Exception $e) {
      return response()->json([
        'success' => false,
        'message' => 'No se pudo eliminar el requerimiento.',
        'errors' => ['system' => $e->getMessage()]
      ], 422);
    }
  }
}
