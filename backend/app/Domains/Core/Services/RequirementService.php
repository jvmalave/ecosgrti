<?php

namespace App\Domains\Core\Services;


use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;
use App\Domains\Core\Exceptions\SequentialityViolationException;
use App\Domains\Core\Models\Requirement;
use App\Domains\Core\Models\ScheduleEstimation;
use Illuminate\Support\Facades\DB;
use App\Domains\Audit\Services\AuditService;
use App\Domains\Workflow\Services\PhaseTransitionService;
use App\Domains\Workflow\Services\ProgressCalculationService;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;





class RequirementService
{

  /**
   * Inyectamos el servicio de Auditoría Forense
   */
  public function __construct(
    private readonly AuditService $auditService,
    private readonly PhaseTransitionService $phaseTransitionService,
    private readonly ProgressCalculationService $progressService
  ) {}


  /**
   * El orden inmutable de la ingeniería institucional (Camino de Hierro)
   */
  private const PHASE_ORDER = [
    'ATF',
    'DISENO',
    'CONSTRUCCION',
    'PRUEBAS',
    'CERTIFICACION',
    'IMPLEMENTACION'
  ];

  /**
   * Ejecuta la lógica de negocio para registrar un Requerimiento (Momento 1)
   * * @param array $validatedData Datos limpios del Request
   * @param UploadedFile $itRequestDoc Archivo PDF de Solicitud TI
   * @param UploadedFile $needsSpreadsheet Archivo de Matriz de Necesidades
   * @return array Datos del requerimiento creado
   */
  public function createRequirement(array $validatedData, UploadedFile $itRequestDoc, UploadedFile $needsSpreadsheet): array
  {
    // Envolvemos todo en una transacción ACID para garantizar la integridad
    return DB::transaction(function () use ($validatedData, $itRequestDoc, $needsSpreadsheet) {

      // 1. Almacenamiento seguro de archivos binarios
      $itDocPath = $itRequestDoc->store('requirements/it_docs');
      $needsDocPath = $needsSpreadsheet->store('requirements/needs_docs');

      // 2. Captura del Grafo Organizacional y el ID Real del Consultor
      $snapshot = DB::table('security.functional_consultants as fc')
        ->join('catalogs.requesting_units as ru', 'fc.requesting_unit_id', '=', 'ru.id')
        ->join('catalogs.systems as sys', 'ru.system_id', '=', 'sys.id')
        ->join('catalogs.societies as soc', 'sys.society_id', '=', 'soc.id')
        // Buscamos por el person_id que nos envió el frontend
        ->where('fc.person_id', $validatedData['functional_consultant_id'])
        ->select(
          'fc.id as real_consultant_id',
          'ru.name as unit_name',
          'sys.name as system_name',
          'soc.name as society_name'
        )
        ->first();

      $requirementId = Str::uuid()->toString();

      // 3. Persistencia del Requerimiento Principal
      DB::table('core.requirements')->insert([
        'id' => $requirementId,
        'rrti' => $validatedData['rrti'],
        'requirement_type' => $validatedData['requirement_type'],
        'management_type' => $validatedData['management_type'],
        'creation_date' => $validatedData['creation_date'],
        'description' => $validatedData['description'],
        'status' => 'RC',
        'is_locked' => false,

        // === Usamos el ID real que acabamos de extraer ===
        'functional_consultant_id' => $snapshot->real_consultant_id,

        'it_request_doc_path' => $itDocPath,
        'needs_spreadsheet_path' => $needsDocPath,

        'snapshot_society_name' => $snapshot->society_name,
        'snapshot_system_name' => $snapshot->system_name,
        'snapshot_unit_name' => $snapshot->unit_name,

        'created_at' => now(),
        'updated_at' => now(),
      ]);

      // 4. Registro Forense (Audit Trail)
      DB::table('audit.audit_logs')->insert([
        'id' => Str::uuid()->toString(),
        'user_id' => auth()->id(), // ID del usuario autenticado (Coordinador)
        'action' => 'CREATE_REQUIREMENT',
        'description' => "Creación del requerimiento RRTI: {$validatedData['rrti']}",
        'ip_address' => request()->ip(),
        'user_agent' => request()->userAgent(),
        // Agrupamos todos los datos relevantes en el payload JSON
        'payload' => json_encode([
          'entity' => 'core.requirements',
          'entity_id' => $requirementId,
          'rrti' => $validatedData['rrti'],
          'requirement_type' => $validatedData['requirement_type'],
          'functional_consultant_id' => $snapshot->real_consultant_id,
          'snapshot_unit' => $snapshot->unit_name,
          'snapshot_system' => $snapshot->system_name,
          'snapshot_society' => $snapshot->society_name
        ]),
        'created_at' => now(),
        'updated_at' => now(), // Añadido según tu esquema
      ]);
      // 5. Mapeo e inserción en la tabla pivote de Consultores CSPE
      $cspePivotData = collect($validatedData['cspe_consultants'])->map(function ($cspeId) use ($requirementId) {
        return [
          'id' => Str::uuid()->toString(),
          'requirement_id' => $requirementId,
          'cspe_consultant_id' => $cspeId,
          'created_at' => now(),
          'updated_at' => now(),
        ];
      })->toArray();

      DB::table('core.cspe_consultant_requirement')->insert($cspePivotData);

      $this->phaseTransitionService->recordTransition(
        $requirementId,
        'RC',
        (string)auth()->id(),
        'Creación inicial del requerimiento'
      );

      // 4. Cálculo y persistencia del progreso inicial (basado en matriz 'RC')
      $requirementModel = Requirement::find($requirementId);
      $initialProgress = $this->progressService->calculateGlobalProgress($requirementModel);
      DB::table('core.requirements')->where('id', $requirementId)->update(['progress_percentage' => $initialProgress]);


      //  5. Higienización de Caché (CRÍTICO PARA ANGULAR)
      Redis::del("req_detail_v2_{$requirementId}");
      Redis::del("req_detail_{$requirementId}");
      Redis::incr('dashboard_version');

      // // Retornamos el resultado al controlador
      // return [
      //   'id' => $requirementId,
      //   'rrti' => $validatedData['rrti']
      // ];


      // 5. HIGIENIZACIÓN DE CACHÉ (Acelera la reactividad en el Dashboard Angular)
            // Invalidamos las claves relacionadas a las listas generales para que se refresquen instantáneamente
            // Redis::incr('dashboard_version');
            // if (Cache::supportsTags()) {
            //     Cache::tags(['dashboard'])->flush();
            // }

            return ['id' => $requirementId, 'rrti' => $validatedData['rrti']];
    });
  }
  /**
   * Obtiene el detalle completo del requerimiento usando caché en Redis (Fast Path)
   */


  public function getFullDetail(string $id): ?array
  {
    // 
    $cacheKey = "req_detail_v2_{$id}";

    // 1. Intentar desde Redis
    $cachedData = Redis::get($cacheKey);
    if ($cachedData) {
      return [
        'source' => 'cache',
        'data'   => json_decode($cachedData, true)
      ];
    }

    // 2. Cache Miss: Ir a la Base de Datos
    $requirement = Requirement::with([
      'cspeConsultants',
      'functionalConsultant.person'
    ])->find($id);

    if (!$requirement) {
      return null;
    }

    // 3. Guardar en Redis (TTL de 5 minutos)
    Redis::setex($cacheKey, 300, json_encode($requirement));

    return [
      'source' => 'database',
      'data'   => $requirement
    ];
  }

  /** Ejecuta la lógica de negocio para actualizar un Requerimiento 
   * * @param string $id UUID del requerimiento a actualizar
   * @param array $data Datos limpios del Request
   * @param array $files Archivos enviados en el Request
   * @param string $userId ID del usuario que realiza la actualización (para auditoría)
   * @return Requirement Requerimiento actualizado
   * @throws Exception Si el requerimiento no existe o si la fase de planificación está cerrada.
   */

  public function updateRequirement(string $id, array $data, array $files, string $userId): Requirement
  {
    return DB::transaction(function () use ($id, $data, $files, $userId) {

      $requirement = Requirement::find($id);

      if (!$requirement) {
        throw new Exception('Requerimiento no encontrado.', 404);
      }

      // RN-01: Validación de Inmutabilidad
      if ($requirement->is_locked || $requirement->status !== 'RC') {
        throw new Exception('Operación denegada. La fase de planificación está cerrada.', 422);
      }

      // Auditoría (Tomamos foto del antes)
      $oldData = $requirement->toArray();

      // ------------------------------------------------
      //Regeneración del Snapshot y Consultor
      // ------------------------------------------------
      Log::info('Data cruda desde Angular:', $data);

      if (isset($data['persona_id'])) {

        Log::info('¡SÍ llegó persona_id!: ' . $data['persona_id']);

        $newHierarchy = DB::table('security.functional_consultants as fc')
          ->join('catalogs.requesting_units as ru', 'fc.requesting_unit_id', '=', 'ru.id')
          ->join('catalogs.systems as sys', 'ru.system_id', '=', 'sys.id')
          ->join('catalogs.societies as soc', 'sys.society_id', '=', 'soc.id')
          ->where('fc.person_id', $data['persona_id'])
          ->select(
            'fc.id as functional_consultant_id',
            'soc.name as society_name',
            'sys.name as system_name',
            'ru.name as unit_name'
          )
          ->first();

        if ($newHierarchy) {
          Log::info('¡Jerarquía ENCONTRADA en BD!', (array)$newHierarchy);

          $data['functional_consultant_id'] = $newHierarchy->functional_consultant_id;
          $data['snapshot_society_name']    = $newHierarchy->society_name;
          $data['snapshot_system_name']     = $newHierarchy->system_name;
          $data['snapshot_unit_name']       = $newHierarchy->unit_name;
        } else {
          Log::error('¡ALERTA! La jerarquía devolvió NULL para el persona_id: ' . $data['persona_id']);
        }
      } else {
        Log::warning('¡ALERTA! Angular NO envió el campo persona_id.');
      }

      $baseData = collect($data)->except(['cspe_consultants', 'persona_id'])->toArray();
      $requirement->update($baseData);



      // Sincronizar Consultores CSPE (Muchos a Muchos)
      if (isset($data['cspe_consultants'])) {
        // Suponiendo que la relación en tu modelo se llama 'cspeConsultants'
        $requirement->cspeConsultants()->sync($data['cspe_consultants']);
      }

      // Procesamiento de Archivos Adjuntos (Si enviaron archivos nuevos)
      if (isset($files['it_request_doc'])) {
        $this->replaceDocument($requirement, 'it_request_doc', $files['it_request_doc']);
      }
      if (isset($files['needs_spreadsheet'])) {
        $this->replaceDocument($requirement, 'needs_spreadsheet', $files['needs_spreadsheet']);
      }

      // Auditoría (Tomamos foto del después)
      Log::info("Audit CU-007: Requerimiento {$id} actualizado.", [
        'user_id' => $userId,
        'deltas'  => [
          'antes'   => $oldData,
          'despues' => $requirement->fresh()->toArray()
        ]
      ]);


      // ------------------------------------------
      // INVALIDACIÓN EXACTA DE CACHÉ
      // ------------------------------------------
      // 1. Destruimos la caché de la vista Show/Edit (La V2 y la normal por si acaso)
      Redis::del("req_detail_v2_{$id}");
      Redis::del("req_detail_{$id}");

      // 2. Destruimos la caché del Dashboard (Tabla principal)
      //Redis::del("list_active_requirements");

      Redis::incr('dashboard_version');
      // ----------------------------------------------------------------------

      return $requirement;
    });
  }

  /**
   * Método Auxiliar Privado para reemplazar documentos físicos y en Base de Datos
   */
  protected function replaceDocument(Requirement $requirement, string $fileType, $file): void
  {
    // 1. Mapeamos el tipo de archivo recibido con el nombre de la columna en la BD
    $columnName = $fileType === 'it_request_doc'
      ? 'it_request_doc_path'
      : 'needs_spreadsheet_path';

    // 2. Si ya existía un archivo previo, lo eliminamos físicamente del servidor para ahorrar espacio
    if ($requirement->$columnName && Storage::disk('public')->exists($requirement->$columnName)) {
      Storage::disk('public')->delete($requirement->$columnName);
    }

    // 3. Guardamos el nuevo archivo en la carpeta 'requirements/ID_DEL_REQUERIMIENTO'
    $path = $file->store("requirements/{$requirement->id}", 'public');

    // 4. Actualizamos la columna en el modelo y guardamos
    $requirement->update([
      $columnName => $path
    ]);
  }

  /**
   * Motor de validación de secuencialidad lógica (Cascada estricta).
   */
  // private function validateChronologicalSequence(array $phasesData, Carbon $requirementCreatedAt): void
  // {
  //   // Reordenamos el array de entrada para que coincida con el Camino de Hierro
  //   $sortedPhases = [];
  //   foreach (self::PHASE_ORDER as $expectedPhase) {
  //     foreach ($phasesData as $inputPhase) {
  //       if ($inputPhase['phase_name'] === $expectedPhase) {
  //         $sortedPhases[] = $inputPhase;
  //         break;
  //       }
  //     }
  //   }

  //   $previousStartDate = null;

  //   foreach ($sortedPhases as $index => $phase) {
  //     $currentStartDate = Carbon::parse($phase['start_date'])->startOfDay();

  //     // RN-Fecha Mínima de Inicio: La primera fase no puede ser antes de la creación del requerimiento
  //     if ($index === 0 && $currentStartDate->isBefore($requirementCreatedAt->startOfDay())) {
  //       throw new SequentialityViolationException(
  //         "La fecha de inicio de la fase ATF no puede ser anterior a la creación del requerimiento."
  //       );
  //     }

  //     // RN-Secuencialidad del Cronograma: Una fase posterior no puede iniciar antes que la anterior
  //     if ($previousStartDate !== null && $currentStartDate->isBefore($previousStartDate)) {
  //       throw new SequentialityViolationException(
  //         "Ruptura de secuencia detectada: La fase {$phase['phase_name']} inicia antes que su predecesora."
  //       );
  //     }

  //     $previousStartDate = $currentStartDate;
  //   }
  // }


  private function validateChronologicalSequence(array $phasesData, Carbon $requirementCreatedAt): void
{
    // Reordenamos el array de entrada para que coincida con el Camino de Hierro
    $sortedPhases = [];
    foreach (self::PHASE_ORDER as $expectedPhase) {
        foreach ($phasesData as $inputPhase) {
            if ($inputPhase['phase_name'] === $expectedPhase) {
                $sortedPhases[] = $inputPhase;
                break;
            }
        }
    }

    $previousStartDate = null;

    foreach ($sortedPhases as $index => $phase) {
        $currentStartDate = Carbon::parse($phase['start_date'])->startOfDay();
        $currentEndDate   = Carbon::parse($phase['end_date'])->startOfDay();

        $dateInput = $currentStartDate->format('Y-m-d');
        $dateCreation = $requirementCreatedAt->format('Y-m-d');

        // 1. RN-Integridad Interna: La fecha de fin no puede ser anterior a la de inicio
        if ($currentEndDate->isBefore($currentStartDate)) {
            throw new SequentialityViolationException(
                "Error en {$phase['phase_name']}: La fecha de fin no puede ser anterior a la fecha de inicio."
            );
        }

        // 2. RN-Fecha Mínima de Inicio: La primera fase no puede ser antes de la creación del requerimiento
        // if ($index === 0 && $currentStartDate->isBefore($requirementCreatedAt->startOfDay())) {
        //   // Usamos abort(422) para que Laravel genere un JSON estandarizado que Angular entiende
        //     abort(422, "No puede tener una fecha anterior a la fecha de creación del requerimiento.");
        // }

        if ($index === 0 && $dateInput < $dateCreation) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'fecha_inicio' => "La fecha de inicio ($dateInput) no puede ser anterior a la fecha de creación del requerimiento ($dateCreation)."
            ]);
        }

        // 3. RN-Solapamiento Permitido: Una fase posterior no puede iniciar antes que el inicio de su predecesora
        // if ($previousStartDate !== null && $currentStartDate->isBefore($previousStartDate)) {
        //     abort(422, "Ruptura de secuencia: La fase {$phase['phase_name']} no puede iniciar antes de la fecha de inicio de su predecesora.");
        // }

        if ($previousStartDate !== null && $currentStartDate->isBefore($previousStartDate)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'secuencia' => "Ruptura de secuencia: La fase {$phase['phase_name']} no puede iniciar antes de la fecha de inicio de su predecesora."
            ]);
        }

        // Para la siguiente iteración, solo nos interesa comparar contra la fecha de inicio
        $previousStartDate = $currentStartDate;
    }
}

  /**
   * Aplica el borrado lógico del requerimiento.
   * * @param string $requirementId
   * @param string $userId
   * @param string $justification
   * @return void
   */
  /**
   * Aplica el borrado lógico del requerimiento, audita la acción y limpia la caché.
   *
   * @param string $requirementId
   * @param string $userId
   * @param string $justification
   * @return void
   */

  public function softDeleteRequirement(string $requirementId, string $userId, string $justification): void
  {
    DB::transaction(function () use ($requirementId, $userId, $justification) {
      $requirement = Requirement::findOrFail($requirementId);
      $oldValues = $requirement->toArray();

      // RN-Tipo de Eliminación - Borrado Lógico
      $requirement->delete();

      // RN-06 Auditoría de Operación Crítica
      $this->auditService->logModelChange(
        action: 'DELETE',
        description: "Borrado lógico del requerimiento RRTI: {$oldValues['rrti']} - Motivo: {$justification}",
        payload: [
          'tableName' => 'core.requirements',
          'recordId'  => $requirementId,
          'justification' => $justification,
          'oldValues' => $oldValues,
          'newValues' => [
            'deleted_at' => now()->toDateTimeString()
          ]
        ],
        userId: $userId
      );

      // RN-Higienización: Eliminamos el registro detallado de la RAM
      Cache::forget("req_detail_{$requirementId}");

      // // RN-Higienización: Eliminamos todas las listas paginadas del Dashboard de forma inteligente
      // if (Cache::supportsTags()) {
      //   Cache::tags(['dashboard_requirements'])->flush();
      // }

      Cache::forget("req_detail_v2_{$requirementId}");
      Cache::forget("req_detail_{$requirementId}");

      // Invalida todo el Dashboard al instante
      Redis::incr('dashboard_version');
    });
  }

  /**
   * Persiste o actualiza el cronograma de las 6 fases del "Camino de Hierro" sin alterar el ciclo de vida ni aplicar candados.
   * * @param string $requirementId UUID del requerimiento.
   * @param array $phasesData Matriz con fechas y horas estimadas de las fases.
   * @return ScheduleEstimation
   * @throws InvalidArgumentException Si el requerimiento ya está bloqueado por un Hard Gate.
   */

  /**
   * Persiste o actualiza el cronograma de las 6 fases (Borrador) sin alterar el ciclo de vida.
   */
  public function saveEstimationDraft(string $requirementId, array $phasesData, string $userId): ScheduleEstimation
  {
    // 1. Buscamos el requerimiento
    $requirement = Requirement::findOrFail($requirementId);

    // 2. RN-Inmutabilidad: Si el Hard Gate ya fue accionado, impedimos cambios.
    if ($requirement->is_locked) {
      throw new InvalidArgumentException("Operación denegada: El requerimiento se encuentra inmutable debido a un Hard Gate activo.");
    }

    // 3. Ejecución de Reglas de Negocio en Memoria (Validación de Cascada Estricta)
    $this->validateChronologicalSequence($phasesData, Carbon::parse($requirement->created_at));

    // 4. Transacción ACID
    return DB::transaction(function () use ($requirementId, $phasesData, $userId) {

      $estimation = ScheduleEstimation::updateOrCreate(
        ['requirement_id' => $requirementId],
        ['created_by' => $userId, 'updated_at' => now()]
      );

      // Sincronización transaccional de las subfases
      foreach ($phasesData as $phase) {
        $estimation->estimatedPhases()->updateOrCreate(
          ['phase_name' => $phase['phase_name']], // Clave de búsqueda
          [
            'start_date' => $phase['start_date'],
            'end_date'   => $phase['end_date'],
            'estimated_hours' => $phase['estimated_hours'],
          ]
        );
      }

      return $estimation;
    });
  }

  /**
   * Obtiene los detalles del requerimiento y su estimación de cronograma.
   * * @param string $requirementId UUID del requerimiento.
   * @return array Matriz con los datos formateados para hidratar la vista.
   * @throws \Illuminate\Database\Eloquent\ModelNotFoundException Si el UUID no existe.
   */
  public function getEstimationDetails(string $requirementId): array
  {
    // Eager Loading: Traemos el requerimiento y de una vez sus fases estimadas
    $requirement = Requirement::with(['scheduleEstimation.estimatedPhases'])
      ->findOrFail($requirementId);

    return [
      'id'         => $requirement->id,
      'rrti'       => $requirement->rrti,
      'status'     => $requirement->status,
      'is_locked'  => $requirement->is_locked,
      'estimation' => $requirement->scheduleEstimation // null si no han guardado un borrador aún
    ];
  }

    // (Conserva intactos validateChronologicalSequence, softDeleteRequirement y closePlanningPhase)

  /**
   *  Cerrar la fase de Planificación de manera irreversible, cambiar el estado y registrar la firma forense.
   * * @param string $requirementId UUID del requerimiento.
   * @param string $userId UUID del usuario/consultor que ejecuta la acción.
   * @param string $justification Razón técnica o institucional del cierre de fase.
   * @return Requirement
   * @throws InvalidArgumentException Si no posee estimación previa o si ya está cerrado.
   */
  // public function closePlanningPhase(string $requirementId, string $userId, string $justification): Requirement
  // {
  //   return DB::transaction(function () use ($requirementId, $userId, $justification) {
  //     // Obtenemos el requerimiento con bloqueo para actualización (Pessimistic Locking si fuera necesario)
  //     $requirement = Requirement::findOrFail($requirementId);

  //     if ($requirement->is_locked) {
  //       throw new InvalidArgumentException("Operación inválida: La fase de planificación para este requerimiento ya fue cerrada.");
  //     }

  //     // Regla de Negocio: No se puede cerrar la fase si no existe un borrador de estimación guardado previamente.
  //     if (!$requirement->scheduleEstimation()->exists()) {
  //       throw new InvalidArgumentException("Operación denegada: Debe guardar un borrador de la estimación antes de cerrar la fase.");
  //     }

  //     $this->transitionPhase($requirementId, 'ES-R', $userId, $justification);

  //     return Requirement::find($requirementId);

  // $oldStatus = $requirement->status;
  // $newStatus = 'ES-R'; // Estimacion Realizada

  // // Guardamos el estado anterior para la bitácora de auditoría
  // $oldValues = [
  //   'status' => $oldStatus,
  //   'is_locked' => false
  // ];

  // // Ejecutamos la actualización atómica del Hard Gate
  // $requirement->update([
  //   'is_locked' => true,
  //   'status'    => $newStatus
  // ]);

  // // Registramos en el esquema `audit` la trazabilidad exacta del cierre (Firma de Cierre)
  // $this->auditService->logModelChange(
  //   action: 'CLOSE_PLANNING_PHASE',
  //   description: 'Cierre de la fase planificacion (Estimacion Realizada)',
  //   payload: [
  //     'tableName' => 'core.requirements',
  //     'recordId'  => $requirementId,
  //     'oldValues' => $oldValues,
  //     'newValues' => [
  //       'status' => $newStatus,
  //       'is_locked' => true,
  //       'justification' => $justification
  //     ]
  //   ],
  //   userId: $userId
  // );

  //return $requirement;
  //   });
  // }


  //     public function closePlanningPhase(string $requirementId, string $userId, string $justification): Requirement
  // {
  //     return DB::transaction(function () use ($requirementId, $userId, $justification) {
  //         $requirement = Requirement::findOrFail($requirementId);

  //         if ($requirement->is_locked) {
  //             throw new InvalidArgumentException("El requerimiento ya se encuentra sellado.");
  //         }

  //         // 1. Cambio de Estado Maestro
  //         $requirement->update([
  //             'is_locked' => true,
  //             'status'    => 'ES-R'
  //         ]);

  //         // 2. Registro Inmutable (Historial de Fases)
  //         $this->phaseTransitionService->recordTransition($requirementId, 'ES-R', $userId, $justification);

  //         // 3. Auditoría Forense (Asegura el registro en audit.audit_logs)
  //         $this->auditService->logModelChange(
  //             action: 'CLOSE_PLANNING_PHASE',
  //             description: "Cierre de fase de planificación - RRTI: {$requirement->rrti}",
  //             payload: [
  //                 'status' => 'ES-R',
  //                 'justification' => $justification
  //             ],
  //             userId: $userId,
  //             targetId: $requirementId
  //         );

  //         // 4. Recálculo y Persistencia del Progreso (Paso crítico que faltaba)
  //         $newProgress = $this->progressService->calculateGlobalProgress($requirement);
  //         $requirement->update(['progress_percentage' => $newProgress]);

  //         return $requirement;
  //     });
  // }


  // /backend/app/Domains/Core/Services/RequirementService.php

  public function closePlanningPhase(string $requirementId, string $userId, string $justification): Requirement
  {
    return DB::transaction(function () use ($requirementId, $userId, $justification) {
      $requirement = Requirement::findOrFail($requirementId);

      if ($requirement->is_locked) {
        throw new InvalidArgumentException("Operación inválida: La fase de planificación ya fue cerrada.");
      }

      // 1. Cambio de Estado y Bloqueo (Asignación directa para mayor seguridad)
      $requirement->is_locked = true;
      $requirement->status = 'ES-R';
      $requirement->save();

      // 2. Registro Inmutable (Historial de Fases)
      $this->phaseTransitionService->recordTransition($requirementId, 'ES-R', $userId, $justification);

      // 3. Recálculo y Persistencia del Progreso
      // Refrescamos el modelo para asegurar que el motor lea el estado más reciente
      $newProgress = $this->progressService->calculateGlobalProgress($requirement->fresh());

      // Uso de forceFill para forzar la actualización ignorando el array $fillable
      $requirement->forceFill(['progress_percentage' => $newProgress])->save();

      // 4. Auditoría Forense
      $this->auditService->logModelChange(
        action: 'CLOSE_PLANNING_PHASE',
        description: "Cierre de fase de planificación - RRTI: {$requirement->rrti}",
        payload: [
          'status' => 'ES-R',
          'is_locked' => true,
          'progress_percentage' => $newProgress,
          'justification' => $justification
        ],
        userId: $userId,
        targetId: $requirementId
      );

      // 5. Higienización de Caché (CRÍTICO PARA ANGULAR)
      Redis::del("req_detail_v2_{$requirementId}");
      Redis::del("req_detail_{$requirementId}");
      Redis::incr('dashboard_version');

      return $requirement->fresh();
    });
  }

  /**
   * Método para realizar transiciones de fase atómicas y auditable
   */
  public function transitionPhase(string $requirementId, string $newStatusCode, string $userId, string $remarks = ''): void
  {
    DB::transaction(function () use ($requirementId, $newStatusCode, $userId, $remarks) {
      $requirement = Requirement::findOrFail($requirementId);

      // 1. Registro Inmutable en el historial (WATCH Standard)
      $this->phaseTransitionService->recordTransition($requirementId, $newStatusCode, $userId);

      // 2. Actualización del Estado Maestro en core.requirements
      $requirement->update([
        'status' => $newStatusCode,
        'updated_at' => now()
      ]);

      // 3. Recálculo del Progreso basado en la Matriz de Ponderación
      $newProgress = $this->progressService->calculateGlobalProgress($requirement);

      // 4. Persistencia del nuevo Progreso
      $requirement->update(['progress_percentage' => $newProgress]);

      // 5. Auditoría Forense
      $this->auditService->logModelChange(
        action: 'PHASE_CHANGE',
        description: "Transición a fase: {$newStatusCode}",
        payload: ['new_status' => $newStatusCode, 'progress' => $newProgress],
        userId: $userId,
        targetId: $requirementId
      );
    });
  }
}
