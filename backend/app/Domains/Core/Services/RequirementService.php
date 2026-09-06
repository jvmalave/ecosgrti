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
use App\Domains\Core\Dictionaries\CacheKeyDictionary;
use InvalidArgumentException;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;

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
        return DB::transaction(function () use ($validatedData, $itRequestDoc, $needsSpreadsheet) {

            // Almacenamiento seguro de archivos binarios
            $itDocPath = $itRequestDoc->store('requirements/it_docs');
            $needsDocPath = $needsSpreadsheet->store('requirements/needs_docs');

            // Captura del Grafo Organizacional y el ID Real del Consultor
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

            // Búsqueda de la Matriz de Progreso Activa
            $searchType = rtrim(trim($validatedData['management_type']), 'sS') . '%';
            $activeMatrix = DB::table('catalogs.progress_matrices')
                ->where('is_active', true)
                ->where('management_type', 'ilike', $searchType)
                ->first();

            if (!$activeMatrix) {
                throw new Exception("Operación rechazada: No existe una matriz de progreso activa configurada para el tipo de gestión '{$validatedData['management_type']}'.");
            }

            $requirementId = Str::uuid()->toString();

            // Persistencia Inicial (Esqueleto del Requerimiento)
            DB::table('core.requirements')->insert([
                'id' => $requirementId,
                'rrti' => $validatedData['rrti'],
                'requirement_type' => $validatedData['requirement_type'],
                'management_type' => $validatedData['management_type'],
                'progress_matrix_id' => $activeMatrix->id, // Snapshot Inmutable inicial
                'creation_date' => $validatedData['creation_date'],
                'description' => $validatedData['description'],
                'status' => 'RC', // Nace en vanguardia RC (Recepción)
                'progress_percentage' => 0, // Se calculará dinámicamente en milisegundos
                'is_locked' => false,
                'functional_consultant_id' => $snapshot->real_consultant_id,
                'it_request_doc_path' => $itDocPath,
                'needs_spreadsheet_path' => $needsDocPath,
                'snapshot_society_name' => $snapshot->society_name,
                'snapshot_system_name' => $snapshot->system_name,
                'snapshot_unit_name' => $snapshot->unit_name,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Mapeo e inserción en la tabla pivote de Consultores CSPE
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

            // =====================================================================
            // ORDEN LÓGICO DE TRANSACCIÓN Y VANGUARDIA (Nacimiento)
            // =====================================================================
            
            // Registro Histórico Transaccional
            $this->phaseTransitionService->recordTransition(
                $requirementId,
                'RC',
                (string)auth()->id(),
                'Creación inicial del requerimiento'
            );

            // Cálculo y persistencia del progreso inicial (Lee el historial)
            $requirementModel = Requirement::find($requirementId);
            $initialProgress = $this->progressService->calculateGlobalProgress($requirementModel);
            
            // Actualización (RC es la vanguardia por defecto al nacer)
            $requirementModel->update(['progress_percentage' => $initialProgress]);

            // Registro Forense Centralizado
            $this->auditService->logModelChange(
                'CREATE_REQUIREMENT',
                "Creación del requerimiento RRTI: {$validatedData['rrti']}",
                [
                    'entity_id' => $requirementId,
                    'rrti' => $validatedData['rrti'],
                    'management_type' => $validatedData['management_type'],
                    'progress_matrix_id' => $activeMatrix->id,
                    'functional_consultant_id' => $snapshot->real_consultant_id,
                    'initial_progress' => $initialProgress
                ],
                (string) auth()->id(),
                $requirementId
            );

            // E. Higienización de Caché con el Diccionario
            Redis::del(CacheKeyDictionary::allRequirementDetailKeys($requirementId));
            Redis::del(CacheKeyDictionary::requirementProgress($requirementId));
            Redis::incr(CacheKeyDictionary::globalDashboardVersion());
            Cache::tags([CacheKeyDictionary::dashboardTag()])->flush();

            return ['id' => $requirementId, 'rrti' => $validatedData['rrti']];
        });
    }

    /**
     * Obtiene el detalle completo del requerimiento usando caché en Redis (Fast Path)
     */
    /**
     * Obtiene el detalle completo del requerimiento usando caché en Redis (Fast Path)
     */
    public function getFullDetail(string $id): ?array
    {
        $cacheKey = "req_detail_v2_{$id}";

        // Intentar desde Redis
        $cachedData = Redis::get($cacheKey);
        if ($cachedData) {
            return [
                'source' => 'cache',
                'data'   => json_decode($cachedData, true)
            ];
        }

        // Cache Miss: Ir a la Base de Datos con inyección de subconsultas para el Mapa
        $requirement = Requirement::with([
            'cspeConsultants',
            'functionalConsultant.person',
            'progressMatrix'
        ])
        ->select('core.requirements.*') // Seleccionamos todas las columnas base
        
        // INYECCIÓN 1: Fases Cerradas (Verdes en el mapa)
        ->selectRaw("(
            SELECT string_agg(SPLIT_PART(ph.phase_status_code, '-', 1), ',') 
            FROM workflow.requirement_phase_history ph 
            WHERE ph.requirement_id = core.requirements.id AND ph.phase_status_code LIKE '%-C'
        ) as historical_frozen_string")
        
        // INYECCIÓN 2: Fases Iniciadas/Activas (Azules en el mapa)
        ->selectRaw("(
            SELECT string_agg(SPLIT_PART(ph.phase_status_code, '-', 1), ',') 
            FROM workflow.requirement_phase_history ph 
            WHERE ph.requirement_id = core.requirements.id AND ph.phase_status_code LIKE '%-I'
        ) as historical_active_string")
        
        ->find($id);

        if (!$requirement) {
            return null;
        }

        // 3. TRANSFORMACIÓN PARA EL FRONTEND (Igual que en el Dashboard)
        $frozen = !empty($requirement->historical_frozen_string) ? array_unique(explode(',', $requirement->historical_frozen_string)) : [];
        $active = !empty($requirement->historical_active_string) ? array_unique(explode(',', $requirement->historical_active_string)) : [];
        
        // Asignamos las propiedades dinámicas que Angular espera
        $requirement->frozen_phases = array_values($frozen);
        $requirement->open_phases = array_values(array_diff($active, $frozen));
        
        // Limpiamos la basura temporal
        unset($requirement->historical_frozen_string);
        unset($requirement->historical_active_string);

        // 4. Guardar en Redis (TTL de 5 minutos)
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

            // Actualización de Matriz si cambia el Tipo de Gestión antes de ES-R
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
            Redis::del(CacheKeyDictionary::allRequirementDetailKeys($id));
            Redis::incr(CacheKeyDictionary::globalDashboardVersion());
            Cache::tags([CacheKeyDictionary::dashboardTag()])->flush();

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

            // ------------------------------------------
            // INVALIDACIÓN EXACTA DE CACHÉ
            // ------------------------------------------
            Redis::del(CacheKeyDictionary::allRequirementDetailKeys($id));
            Redis::incr(CacheKeyDictionary::globalDashboardVersion());
            Cache::tags([CacheKeyDictionary::dashboardTag()])->flush();
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
            // ------------------------------------------
            // INVALIDACIÓN EXACTA DE CACHÉ
            // ------------------------------------------
            Redis::del(CacheKeyDictionary::allRequirementDetailKeys($requirementId));
            Redis::incr(CacheKeyDictionary::globalDashboardVersion());
            Cache::tags([CacheKeyDictionary::dashboardTag()])->flush();
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

        $this->validateChronologicalSequence($phasesData, Carbon::parse($requirement->creation_date));

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

            // 🚀 1. Verificación de Idempotencia (Bloqueo de doble ejecución)
            if ($requirement->is_locked) {
                throw new InvalidArgumentException("Operación inválida: La fase de planificación ya fue cerrada.");
            }

            // =====================================================================
            // Validación de fase previa (Requerimiento Creado)
            // =====================================================================
            $hasRC = DB::table('workflow.requirement_phase_history')
                ->where('requirement_id', $requirementId)
                ->where('phase_status_code', 'RC')
                ->exists();

            if (!$hasRC && $requirement->status !== 'RC') {
                throw new InvalidArgumentException("Validación fallida: No se puede cerrar la planificación porque el requerimiento no cuenta con el estatus o hito inicial de Requerimiento Creado (RC).");
            }

            // =====================================================================
            // ORDEN LÓGICO DE TRANSACCIÓN Y VANGUARDIA
            // =====================================================================
            
            // Registro Histórico
            $this->phaseTransitionService->recordTransition($requirementId, 'ES-R', $userId, $justification);

            // Cálculo de Progreso
            $newProgress = $this->progressService->calculateGlobalProgress($requirement);

            // Evaluación de Vanguardia
            $isVanguard = $this->phaseTransitionService->isVanguardStatus('ES-R', $requirement->status);

            $updateData = [
                'is_locked' => true,
                'progress_percentage' => $newProgress,
                'updated_at' => now()
            ];

            if ($isVanguard) {
                $updateData['status'] = 'ES-R';
                $requirement->status = 'ES-R'; // Para memoria en auditoría
            }

            $requirement->update($updateData);

            // Auditoría Forense
            $this->auditService->logModelChange(
                action: 'CLOSE_PLANNING_PHASE',
                description: "Cierre de fase de planificación - RRTI: {$requirement->rrti}" . ($isVanguard ? " (Nueva Vanguardia)" : " (Proceso Paralelo)"),
                payload: [
                    'status' => $requirement->status, 
                    'is_locked' => true,
                    'progress_percentage' => $newProgress,
                    'is_vanguard_update' => $isVanguard,
                    'justification' => $justification
                ],
                userId: $userId,
                targetId: $requirementId
            );

            // =========================================================================
            // DESTRUCCIÓN DEL CACHÉ (DOBLE INVALIDACIÓN Y DICCIONARIO)
            // =========================================================================
            
            // Purgas de llaves específicas con doble driver
            $keysToPurge = [
                CacheKeyDictionary::requirementProgress($requirementId),
                CacheKeyDictionary::requirementDashboardSummary($requirementId),
                CacheKeyDictionary::progressDashboardData($requirementId)
            ];

            foreach ($keysToPurge as $key) {
                Cache::forget($key);
                Redis::del($key);
            }

            // Evicción masiva de detalles (se mantiene puramente en Redis si es un patrón de hash/wildcard)
            Redis::del(CacheKeyDictionary::allRequirementDetailKeys($requirementId));
            
            // Reactividad global del Dashboard garantizada
            Redis::incr(CacheKeyDictionary::globalDashboardVersion());
            
            if (config('cache.default') === 'redis') {
                Cache::tags([CacheKeyDictionary::dashboardTag()])->flush();
            }

            return $requirement->fresh();
        });
    }

    /**
     * Método para realizar transiciones de fase atómicas y auditable
     */
    /**
     * ⚠️ ATENCIÓN: MÉTODO DE SOPORTE ADMINISTRATIVO (BACKDOOR)
     * * Este método ignora intencionalmente todas las reglas de negocio (Hard Gates, 
     * validación de quórum, secuencialidad de componentes, etc.).
     * * Su propósito EXCLUSIVO es servir como herramienta de rescate para Super Administradores
     * en caso de que un requerimiento sufra inconsistencias de datos severas. Ejecuta una 
     * transición de fase forzada, recalcula el progreso (Vanguardia) y sella la acción 
     * con un rastro de auditoría forense explícito.
     *
     * @param string $requirementId ID del requerimiento a intervenir.
     * @param string $newStatusCode Código de fase destino al que se forzará el salto.
     * @param string $userId        ID del administrador de soporte que ejecuta la acción.
     * @param string $remarks       Justificación técnica obligatoria del rescate.
     */
    public function transitionPhase(string $requirementId, string $newStatusCode, string $userId, string $remarks = ''): void
    {
        DB::transaction(function () use ($requirementId, $newStatusCode, $userId, $remarks) {
            $requirement = Requirement::findOrFail($requirementId);

            // 1. Registro Histórico Forzado
            $this->phaseTransitionService->recordTransition($requirementId, $newStatusCode, $userId, $remarks);

            // 2. Cálculo de Progreso
            $newProgress = $this->progressService->calculateGlobalProgress($requirement);

            // 3. Evaluación de Vanguardia
            $isVanguard = $this->phaseTransitionService->isVanguardStatus($newStatusCode, $requirement->status);

            $updateData = [
                'progress_percentage' => $newProgress,
                'updated_at' => now()
            ];

            if ($isVanguard) {
                $updateData['status'] = $newStatusCode;
                $requirement->status = $newStatusCode;
            }

            $requirement->update($updateData);

            // 4. Auditoría Forense de Intervención Manual
            $this->auditService->logModelChange(
                action: 'ADMIN_FORCE_PHASE_CHANGE', // Etiqueta especial para monitoreo de seguridad
                description: "INTERVENCIÓN ADMINISTRATIVA: Transición forzada a fase {$newStatusCode}" . ($isVanguard ? " (Nueva Vanguardia)" : " (Proceso Paralelo)"),
                payload: [
                    'new_status' => $requirement->status,
                    'progress' => $newProgress,
                    'is_vanguard_update' => $isVanguard,
                    'admin_justification' => $remarks
                ],
                userId: $userId,
                targetId: $requirementId
            );

            // 5. Diccionario de Caché
            Redis::del(CacheKeyDictionary::allRequirementDetailKeys($requirementId));
            Redis::del(CacheKeyDictionary::requirementProgress($requirementId));
            Redis::incr(CacheKeyDictionary::globalDashboardVersion());
            Cache::tags([CacheKeyDictionary::dashboardTag()])->flush();
        });
    }
}