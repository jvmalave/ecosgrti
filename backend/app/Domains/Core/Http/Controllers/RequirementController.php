<?php

declare(strict_types=1);

namespace App\Domains\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use App\Domains\Core\Http\Requests\StoreRequirementRequest;
use App\Domains\Core\Http\Requests\UpdateRequirementRequest;
use App\Domains\Core\Services\RequirementService;
use App\Domains\Core\Docs\RequirementDocs;
use App\Domains\Core\Services\RequirementDashboardService;
use App\Domains\Core\Http\Requests\StoreEstimationRequest;
use App\Domains\Core\Exceptions\SequentialityViolationException;
use App\Domains\Security\Services\SpecialOperationService;
use App\Domains\Security\Http\Requests\ValidateSpecialKeyRequest;
use App\Domains\Core\Http\Requests\DestroyRequirementRequest;
use App\Domains\Core\Http\Requests\ClosePlanningRequest;
use Illuminate\Support\Facades\Auth;

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

  // US22 - Registrar Requerimiento (Momento 1)
  // ruta POST /core/requirements
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
   * Mostrar detalle completo del requerimiento.
   * ruta GET /core/requirements/{id}
   */
  public function show(string $id): JsonResponse
  {
    try {
      // Se lama  a servicio (Service-Layer) para que busque la data (y se maneje por Redis)
      $result = $this->requirementService->getFullDetail($id);

      if (!$result) {
        return response()->json([
          'status'  => 'error',
          'message' => 'Requerimiento no encontrado'
        ], 404);
      }

      return response()->json([
        'status' => 'success',
        'source' => $result['source'], // 'cache' o 'database'
        'data'   => $result['data']
      ], 200);
      } catch (Exception $e) {
            // Log para el servidor
            Log::error('Error en RequirementController@show: ' . $e->getMessage());
            
            // Devuelve el error real a Angular temporalmente
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 500);
        };
  }

  /**
   * Actualizar requerimiento
   * ruta PUT /core/requirements/{id}
   */
  public function update(UpdateRequirementRequest $request, string $id): JsonResponse
  {
    try {
      // Pasamos datos validados y los archivos separados
      $requirement = $this->requirementService->updateRequirement(
        $id,
        $request->validated(),
        $request->allFiles(),
        auth()->id()
      );

      return response()->json([
        'status'  => 'success',
        'message' => 'Requerimiento y adjuntos actualizados correctamente.',
        'data'    => $requirement
      ], 200);
    } catch (Exception $e) {
      $statusCode = in_array($e->getCode(), [404, 422]) ? $e->getCode() : 500;

      return response()->json([
        'status'  => 'error',
        'message' => $e->getMessage()
      ], $statusCode);
    }
  }

  /**
   * US23 - Registrar Estimación (Momento 2)
   * Guardar Borrador de Estimación
   */
  public function saveEstimationDraft(StoreEstimationRequest $request, string $id): JsonResponse
  {
    try {
      $userId = (string) auth()->id();

      $estimation = $this->requirementService->saveEstimationDraft(
        $id,
        $request->validated('phases'),
        $userId
      );

      return response()->json([
        'success' => true,
        'message' => 'Borrador de estimación guardado exitosamente.',
        'data' => $estimation->load('estimatedPhases')
      ], 200);

    } catch (SequentialityViolationException $e) {
      // 💡 Captura la excepción específica del Dominio de Estimaciones
      return response()->json([
        'success' => false,
        'message' => 'Error de coherencia cronológica.',
        'errors' => ['secuencia' => $e->getMessage()]
      ], 422);

    } catch (\InvalidArgumentException $e) {
      // 💡 Captura violaciones de fechas e inmutabilidad enviadas desde el servicio
      return response()->json([
        'success' => false,
        'message' => 'Error de coherencia cronológica.',
        'errors' => ['secuencia' => $e->getMessage(), 'estado' => $e->getMessage()]
      ], 422);

    } catch (Exception $e) {
      // Si la excepción del servicio contiene en su mensaje alguna regla de secuencia/fechas
      if (str_contains($e->getMessage(), 'Ruptura de secuencia') || str_contains($e->getMessage(), 'no puede ser anterior')) {
        return response()->json([
          'success' => false,
          'message' => 'Error de coherencia cronológica.',
          'errors' => ['secuencia' => $e->getMessage()]
        ], 422);
      }

      return response()->json([
        'success' => false,
        'message' => 'No se pudo procesar la estimación.',
        'errors' => ['sistema' => $e->getMessage()]
      ], 500);
    }
  }

  /**
   * Obtiene el estado actual del requerimiento y su estimación (si existe).
   * Delega la búsqueda de datos al RequirementService y formatea las excepciones.
   * * @param string $id UUID del requerimiento
   * @return JsonResponse
   */
  public function showEstimation(string $id): JsonResponse
  {
    try {
      // Delegamos la lógica al dominio
      $data = $this->requirementService->getEstimationDetails($id);

      return response()->json([
        'success' => true,
        'data'    => $data
      ], 200);
    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
      return response()->json([
        'success' => false,
        'message' => 'Requerimiento no encontrado en la base de datos.'
      ], 404);
    } catch (Exception $e) {
      // Captura de errores inesperados para evitar exponer la traza al cliente
      return response()->json([
        'success' => false,
        'message' => 'Error interno al obtener la estimación.',
        'error'   => config('app.debug') ? $e->getMessage() : 'Falla del servidor.'
      ], 500);
    }
  }

  /**
   * US24/CU-009 - Solicitar Ticket de Borrado Lógico
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
      // Se extrae el código de estado de la excepción (por defecto 400 si no trae uno válido)
      $statusCode = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 401;

      return response()->json([
        'success' => false,
        'message' => $e->getMessage(), 
        'errors' => ['auth' => $e->getMessage()]
      ], $statusCode);
    }
  }

  /**
   * US24/CU-009 - Ejecutar Borrado Lógico
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
      Log::error($e);
      $statusCode = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
      return response()->json([
        'success' => false,
        'message' => 'No se pudo eliminar el requerimiento.',
        'errors' => ['system' => $e->getMessage()]
      ], $statusCode);
    }
  }
  /* @param ClosePlanningRequest $request
     * @param string $id UUID del requerimiento
     * @return JsonResponse
     */
  public function closePlanning(ClosePlanningRequest $request, string $id): JsonResponse
  {
    try {
      $userId = Auth::id() ?? 'system-uuid-fallback'; // En producción, aseguramos el UUID del usuario
      $justification = $request->validated('justification');

      $requirement = $this->requirementService->closePlanningPhase(
        requirementId: $id,
        userId: $userId,
        justification: $justification
      );

      return response()->json([
        'success' => true,
        'message' => 'Fase de Planificación (PL) cerrada exitosamente. Requerimiento inmutable en esta etapa.',
        'data'    => $requirement
      ], 200);
    } catch (\InvalidArgumentException $e) {
      // POR QUÉ: Capturamos excepciones de lógica de negocio (ej. ya estaba cerrado)
      return response()->json([
        'success' => false,
        'message' => $e->getMessage()
      ], 422);
    } catch (Exception $e) {
      // Captura de errores inesperados (Base de datos, red, etc.)
      return response()->json([
        'success' => false,
        'message' => 'Error interno al intentar cerrar la fase.',
        'error'   => $e->getMessage()
      ], 500);
    }
  }

  /**
   * Configura el PIN de operaciones especiales por primera vez.
   */
  public function setupPin(Request $request): JsonResponse
  {
    // Validación de los datos entrantes
    $request->validate([
      'login_password' => 'required|string',
      'new_pin' => 'required|string|min:4|max:6', // Asumiendo un PIN de 4 a 6 caracteres
    ]);

    try {
      $userId = (string) auth()->id();
      
      $this->specialOperationService->setupSpecialPin(
        $userId,
        $request->login_password,
        $request->new_pin
      );

      return response()->json([
        'success' => true,
        'message' => 'Tu PIN de operaciones especiales ha sido configurado exitosamente. Ya puedes realizar operaciones críticas.',
      ], 200);

    } catch (Exception $e) {
      // Extraemos el código de estado de la excepción (por defecto 400 si no trae uno válido)
      $statusCode = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 400;

      return response()->json([
        'success' => false,
        'message' => $e->getMessage(),
      ], $statusCode);
    }
  }
}
