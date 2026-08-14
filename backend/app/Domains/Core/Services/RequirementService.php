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
                ->where('fc.person_id', $validatedData['functional_consultant_id'])
                ->select(
                    'fc.id as real_consultant_id',
                    'ru.name as unit_name',
                    'sys.name as system_name',
                    'soc.name as society_name'
                )
                ->first();

            // REGLA 1: Búsqueda de la Matriz de Progreso Activa filtrada por el tipo de gestión
            $searchType = rtrim(trim($validatedData['management_type']), 'sS') . '%';
            
            $activeMatrix = DB::table('catalogs.progress_matrices')
                ->where('is_active', true)
                ->where('management_type', 'ilike', $searchType)
                ->first();

            if (!$activeMatrix) {
                throw new Exception("Operación rechazada: No existe una matriz de progreso activa configurada para el tipo de gestión '{$validatedData['management_type']}'.");
            }

            $requirementId = Str::uuid()->toString();

            // 3. Persistencia del Requerimiento Principal
            DB::table('core.requirements')->insert([
                'id' => $requirementId,
                'rrti' => $validatedData['rrti'],
                'requirement_type' => $validatedData['requirement_type'],
                'management_type' => $validatedData['management_type'],
                'progress_matrix_id' => $activeMatrix->id, // Snapshot Inmutable inicial
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
                'user_id' => auth()->id(), 
                'action' => 'CREATE_REQUIREMENT',
                'description' => "Creación del requerimiento RRTI: {$validatedData['rrti']}",
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'payload' => json_encode([
                    'entity' => 'core.requirements',
                    'entity_id' => $requirementId,
                    'rrti' => $validatedData['rrti'],
                    'requirement_type' => $validatedData['requirement_type'],
                    'management_type' => $validatedData['management_type'],
                    'progress_matrix_id' => $activeMatrix->id,
                    'functional_consultant_id' => $snapshot->real_consultant_id,
                    'snapshot_unit' => $snapshot->unit_name,
                    'snapshot_system' => $snapshot->system_name,
                    'snapshot_society' => $snapshot->society_name
                ]),
                'created_at' => now(),
                'updated_at' => now(),
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

            // 6. Cálculo y persistencia del progreso inicial
            $requirementModel = Requirement::find($requirementId);
            $initialProgress = $this->progressService->calculateGlobalProgress($requirementModel);
            DB::table('core.requirements')->where('id', $requirementId)->update(['progress_percentage' => $initialProgress]);

            // 7. Higienización de Caché (CRÍTICO PARA ANGULAR)
            Redis::del("req_detail_v2_{$requirementId}");
            Redis::del("req_detail_{$requirementId}");
            Redis::incr('dashboard_version');

            return ['id' => $requirementId, 'rrti' => $validatedData['rrti']];
        });
    }

    /**
     * Obtiene el detalle completo del requerimiento usando caché en Redis (Fast Path)
     */
    public function getFullDetail(string $id): ?array
    {
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
            'functionalConsultant.person',
            'progressMatrix'
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

    /** 
     * Ejecuta la lógica de negocio para actualizar un Requerimiento 
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
            // Regeneración del Snapshot y Consultor
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

            // REGLA 2: Actualización de Matriz si cambia el Tipo de Gestión antes de ES-R
            if (isset($baseData['management_type']) && $baseData['management_type'] !== $requirement->management_type) {
                
                $searchType = rtrim(trim($baseData['management_type']), 'sS') . '%';

                $newActiveMatrix = DB::table('catalogs.progress_matrices')
                    ->where('is_active', true)
                    ->where('management_type', 'ilike', $searchType)
                    ->first();

                if (!$newActiveMatrix) {
                    throw new Exception("Operación rechazada: No existe una matriz activa para el tipo de gestión '{$baseData['management_type']}'.");
                }
                $baseData['progress_matrix_id'] = $newActiveMatrix->id;
            }
            $requirement->update($baseData);

            // Sincronizar Consultores CSPE (Muchos a Muchos)
            if (isset($data['cspe_consultants'])) {
                $requirement->cspeConsultants()->sync($data['cspe_consultants']);
            }

            // Procesamiento de Archivos Adjuntos (Si enviaron archivos nuevos)
            if (isset($files['it_request_doc'])) {
                $this->replaceDocument($requirement, 'it_request_doc', $files['it_request_doc']);
            }
            if (isset($files['needs_spreadsheet'])) {
                $this->replaceDocument($requirement, 'needs_spreadsheet', $files['needs_spreadsheet']);
            }

            
            // Auditoría Forense (Tomamos foto del después y persistimos en BD)
            $this->auditService->logModelChange(
                action: 'UPDATE_REQUIREMENT',
                description: "Actualización general del requerimiento RRTI: {$requirement->rrti}",
                payload: [
                    'deltas' => [
                        'antes'   => $oldData,
                        'despues' => $requirement->fresh()->toArray()
                    ]
                ],
                userId: $userId,
                targetId: $id
            );

            // ------------------------------------------
            // INVALIDACIÓN EXACTA DE CACHÉ
            // ------------------------------------------
            Redis::del("req_detail_v2_{$id}");
            Redis::del("req_detail_{$id}");
            Redis::incr('dashboard_version');

            return $requirement;
        });
    }

    /**
     * REGLA 3: Actualiza el tipo de gestión y enlaza una nueva matriz dinámica 
     * en el módulo de actualización de la fase ATF (antes de alcanzar el status ATF-C).
     *
     * @param string $id UUID del requerimiento
     * @param string $newManagementType Nuevo tipo de gestión (Roles, Deliverables, Mixed)
     * @param string $userId ID del usuario ejecutor
     * @return Requirement
     * @throws Exception
     */
    public function updateManagementTypeAtf(string $id, string $newManagementType, string $userId): Requirement
    {
        return DB::transaction(function () use ($id, $newManagementType, $userId) {
            $requirement = Requirement::findOrFail($id);

            // Validación de Hard Gate: Bloqueo de mutación si supera ATF-C u otra fase
            $restrictedStatuses = ['ATF-C', 'DT', 'CO', 'PI', 'CER', 'PAP', 'AU', 'FC'];
            if (in_array($requirement->status, $restrictedStatuses) || $requirement->is_locked) {
                throw new Exception('Hard Gate Activo: No es posible alterar el tipo de gestión ni la matriz de progreso habiendo superado los límites de fase permitidos.');
            }

            if ($requirement->management_type !== $newManagementType) {
            
            $searchType = rtrim(trim($newManagementType), 'sS') . '%';

            $newActiveMatrix = DB::table('catalogs.progress_matrices')
                ->where('is_active', true)
                ->where('management_type', 'ilike', $searchType)
                ->first();

            if (!$newActiveMatrix) {
                throw new Exception("Operación rechazada: No existe una matriz de progreso activa para el nuevo tipo de gestión '{$newManagementType}'.");
            }

            $oldData = $requirement->toArray();

            $requirement->update([
                'management_type' => $newManagementType,
                'progress_matrix_id' => $newActiveMatrix->id
            ]);

            // Recalculamos el progreso de forma reactiva con la nueva matriz vinculada
            $newProgress = $this->progressService->calculateGlobalProgress($requirement->fresh());
            $requirement->update(['progress_percentage' => $newProgress]);

            // Registro Forense en el esquema Audit
            $this->auditService->logModelChange(
                action: 'UPDATE_MANAGEMENT_TYPE_ATF',
                description: "Reasignación de tipo de gestión y matriz en Fase ATF a: {$newManagementType}",
                payload: [
                    'antes' => $oldData,
                    'despues' => $requirement->fresh()->toArray()
                ],
                userId: $userId,
                targetId: $id
            );

            // Invalidación de caché en Redis para la reactividad en el Dashboard Angular
            Redis::del("req_detail_v2_{$id}");
            Redis::del("req_detail_{$id}");
            Redis::incr('dashboard_version');
        }

            return $requirement;
        });
    }

    /**
     * Método Auxiliar Privado para reemplazar documentos físicos y en Base de Datos
     */
    protected function replaceDocument(Requirement $requirement, string $fileType, $file): void
    {
        $columnName = $fileType === 'it_request_doc'
            ? 'it_request_doc_path'
            : 'needs_spreadsheet_path';

        if ($requirement->$columnName && Storage::disk('public')->exists($requirement->$columnName)) {
            Storage::disk('public')->delete($requirement->$columnName);
        }

        $path = $file->store("requirements/{$requirement->id}", 'public');

        $requirement->update([
            $columnName => $path
        ]);
    }

    /**
     * Motor de validación de secuencialidad lógica (Cascada estricta).
     */
    private function validateChronologicalSequence(array $phasesData, Carbon $requirementCreatedAt): void
    {
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

            if ($currentEndDate->isBefore($currentStartDate)) {
                throw new SequentialityViolationException(
                    "Error en {$phase['phase_name']}: La fecha de fin no puede ser anterior a la fecha de inicio."
                );
            }

            if ($index === 0 && $dateInput < $dateCreation) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'fecha_inicio' => "La fecha de inicio ($dateInput) no puede ser anterior a la fecha de creación del requerimiento ($dateCreation)."
                ]);
            }

            if ($previousStartDate !== null && $currentStartDate->isBefore($previousStartDate)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'secuencia' => "Ruptura de secuencia: La fase {$phase['phase_name']} no puede iniciar antes de la fecha de inicio de su predecesora."
                ]);
            }

            $previousStartDate = $currentStartDate;
        }
    }

    /**
     * Aplica el borrado lógico del requerimiento, audita la acción y limpia la caché.
     */
    public function softDeleteRequirement(string $requirementId, string $userId, string $justification): void
    {
        DB::transaction(function () use ($requirementId, $userId, $justification) {
            $requirement = Requirement::findOrFail($requirementId);
            $oldValues = $requirement->toArray();

            $requirement->delete();

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

            Cache::forget("req_detail_{$requirementId}");
            Cache::forget("req_detail_v2_{$requirementId}");
            Redis::incr('dashboard_version');
        });
    }

    /**
     * Persiste o actualiza el cronograma de las 6 fases (Borrador) sin alterar el ciclo de vida.
     */
    public function saveEstimationDraft(string $requirementId, array $phasesData, string $userId): ScheduleEstimation
    {
        $requirement = Requirement::findOrFail($requirementId);

        if ($requirement->is_locked) {
            throw new InvalidArgumentException("Operación denegada: El requerimiento se encuentra inmutable debido a un Hard Gate activo.");
        }

        $this->validateChronologicalSequence($phasesData, Carbon::parse($requirement->created_at));

        return DB::transaction(function () use ($requirementId, $phasesData, $userId) {

            $estimation = ScheduleEstimation::updateOrCreate(
                ['requirement_id' => $requirementId],
                ['created_by' => $userId, 'updated_at' => now()]
            );

            foreach ($phasesData as $phase) {
                $estimation->estimatedPhases()->updateOrCreate(
                    ['phase_name' => $phase['phase_name']],
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
     */
    public function getEstimationDetails(string $requirementId): array
    {
        $requirement = Requirement::with(['scheduleEstimation.estimatedPhases'])
            ->findOrFail($requirementId);

        return [
            'id'         => $requirement->id,
            'rrti'       => $requirement->rrti,
            'status'     => $requirement->status,
            'is_locked'  => $requirement->is_locked,
            'estimation' => $requirement->scheduleEstimation 
        ];
    }

    /**
     * Cerrar la fase de Planificación de manera irreversible.
     */
    public function closePlanningPhase(string $requirementId, string $userId, string $justification): Requirement
    {
        return DB::transaction(function () use ($requirementId, $userId, $justification) {
            $requirement = Requirement::findOrFail($requirementId);

            if ($requirement->is_locked) {
                throw new InvalidArgumentException("Operación inválida: La fase de planificación ya fue cerrada.");
            }

            $requirement->is_locked = true;
            $requirement->status = 'ES-R';
            $requirement->save();

            $this->phaseTransitionService->recordTransition($requirementId, 'ES-R', $userId, $justification);

            $newProgress = $this->progressService->calculateGlobalProgress($requirement->fresh());

            $requirement->forceFill(['progress_percentage' => $newProgress])->save();

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

            $this->phaseTransitionService->recordTransition($requirementId, $newStatusCode, $userId);

            $requirement->update([
                'status' => $newStatusCode,
                'updated_at' => now()
            ]);

            $newProgress = $this->progressService->calculateGlobalProgress($requirement);

            $requirement->update(['progress_percentage' => $newProgress]);

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