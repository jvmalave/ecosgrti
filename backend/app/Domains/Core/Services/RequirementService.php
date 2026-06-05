<?php

namespace App\Domains\Core\Services;


use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;
use App\Domains\Core\Exceptions\SequentialityViolationException;
use App\Domains\Core\Models\Requirement;
use App\Domains\Core\Models\ScheduleEstimation;
use Illuminate\Support\Facades\DB;
use App\Domains\Audit\Services\AuditService;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use Exception;

class RequirementService
{

  /**
   * Inyectamos el servicio de Auditoría Forense
   */
  public function __construct(
    private readonly AuditService $auditService
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
        'status' => 'PL',
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

      // Retornamos el resultado al controlador
      return [
        'id' => $requirementId,
        'rrti' => $validatedData['rrti']
      ];
    });
  }

  public function registerEstimation(string $requirementId, array $phasesData, string $userId): ScheduleEstimation
  {
    // 1. Buscamos el requerimiento (Falla automáticamente si no existe)
    $requirement = Requirement::findOrFail($requirementId);

    // Validamos que no esté bloqueado (Idempotencia)
    if ($requirement->is_locked) {
      throw new Exception("El requerimiento ya se encuentra planificado y bloqueado.");
    }

    // 2. Ejecución de Reglas de Negocio en Memoria (Ingeniería WATCH)
    $this->validateChronologicalSequence($phasesData, $requirement->created_at);

    // 3. Transacción ACID: O se guarda todo, o no se guarda nada
    return DB::transaction(function () use ($requirement, $phasesData, $userId) {

      // A. Crear la cabecera de la estimación
      $estimation = ScheduleEstimation::create([
        'requirement_id' => $requirement->id,
        'created_by' => $userId,
      ]);

      // B. Insertar el detalle de las 6 fases
      foreach ($phasesData as $phase) {
        $estimation->estimatedPhases()->create([
          'phase_name' => $phase['phase_name'],
          'start_date' => $phase['start_date'],
          'end_date' => $phase['end_date'],
          'estimated_hours' => $phase['estimated_hours'],
        ]);
      }

      // C. Aplicar Hard Gate (US17): Bloquear requerimiento y avanzar estado
      $requirement->is_locked = true;
      // $requirement->status = 'ATF'; // Descomentar si tienes un campo status directo
      $requirement->save();

      // D. Aquí el UserObserver/AuditObserver detectará el cambio de $requirement->is_locked
      // y escribirá automáticamente el Delta (Old vs New) en el esquema AUDIT_LOG.

      return $estimation;
    });
  }

  /**
   * Motor de validación de secuencialidad lógica (Cascada estricta).
   */
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

      // RN-Fecha Mínima de Inicio: La primera fase no puede ser antes de la creación del requerimiento
      if ($index === 0 && $currentStartDate->isBefore($requirementCreatedAt->startOfDay())) {
        throw new SequentialityViolationException(
          "La fecha de inicio de la fase ATF no puede ser anterior a la creación del requerimiento."
        );
      }

      // RN-Secuencialidad del Cronograma: Una fase posterior no puede iniciar antes que la anterior
      if ($previousStartDate !== null && $currentStartDate->isBefore($previousStartDate)) {
        throw new SequentialityViolationException(
          "Ruptura de secuencia detectada: La fase {$phase['phase_name']} inicia antes que su predecesora."
        );
      }

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
        description: 'Borrado lógico del requerimiento',
        payload: [
          'tableName' => 'core.requirements',
          'recordId'  => $requirementId,
          'oldValues' => $oldValues,
          'newValues' => [
            'deleted_at'    => now()->toDateTimeString(),
            'justification' => $justification
          ]
        ],
        userId: $userId
      );

      // RN-Higienización: Eliminamos el registro detallado de la RAM
      Cache::forget("req_detail_{$requirementId}");

      // RN-Higienización: Eliminamos todas las listas paginadas del Dashboard de forma inteligente
      if (Cache::supportsTags()) {
        Cache::tags(['dashboard_requirements'])->flush();
      }
    });
  }
}
