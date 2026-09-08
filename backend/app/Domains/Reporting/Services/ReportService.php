<?php

declare(strict_types=1);

namespace App\Domains\Reporting\Services;

use App\Domains\Security\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use App\Domains\Security\Models\CspeConsultant;
use App\Domains\Core\Models\Requirement;
use App\Domains\Audit\Models\AuditLog;

class ReportService
{
    /**
     * Extrae el listado completo de identidades para el directorio MDM.
     * 
     * @return Collection<int, User>
     */
    public function getMdmDirectoryData(): Collection
    {
        // NOTA DE VERIFICACIÓN: Asumimos que existe la relación 'person' en el modelo User.
        return User::withTrashed()
            ->with(['person' => function ($query) {
                // Seleccionamos solo los campos necesarios de la tabla persons para optimizar memoria
                $query->select('id', 'first_name', 'last_name', 'email'); // Ajustar si la FK es diferente
            }])
            ->select('id', 'name', 'email', 'roles', 'deleted_at') // Importante: Si la relación usa un person_id, debe incluirse en este select
            ->orderBy('name', 'asc')
            ->get();
    }

    /**
     * Extrae toda la data relacional necesaria para emitir un Acta de Cierre.
     * Carga ansiosamente (Eager Loading) a los involucrados para evitar consultas N+1.
     * 
     * @param string $requirementId Identificador UUID del requerimiento
     */
    public function getRequirementClosureData(string $requirementId)
    {
        return Requirement::with([
            'functionalConsultant.person',
            'cspeConsultants.person',
            'atfAgreements',
            'roles',         
            'deliverables',
            'phaseHistories' => function ($query) {
                $query->orderBy('created_at', 'asc');
            },
            'cerTicket', 'papOrder', 'auTicket', 'ceeTicket',
            
            // Colecciones específicas por fase para validar el estatus de cada componente
            'dtRoles', 'corRoles', 'piRoles', 'cerRoles', 'papRoles', 'auRoles',
            'coeDeliverables', 'ceeDeliverables'
        ])->findOrFail($requirementId);
    }

    /**
     * Extrae toda la data relacional necesaria para emitir un Acta de Cierre.
     * Carga ansiosamente (Eager Loading) a los involucrados para evitar consultas N+1.
     * 
     * @param string $rrti Código RRTI del requerimiento
     */

    public function getRequirementClosureDataByRrti(string $rrti)
    {
        return Requirement::with([
            'functionalConsultant.person',
            'cspeConsultants.person',
            'atfAgreements',
            'roles',         
            'deliverables',
            'phaseHistories' => function ($query) {
                $query->orderBy('created_at', 'asc');
            },
            'cerTicket', 'papOrder', 'auTicket', 'ceeTicket',
            'dtRoles', 'corRoles', 'piRoles', 'cerRoles', 'papRoles', 'auRoles',
            'coeDeliverables', 'ceeDeliverables'
        ])->where('rrti', $rrti)->firstOrFail();
    }
    /**
     * Consulta los registros de auditoría aplicando filtros dinámicos.
     */
    public function getFilteredAuditLogs(array $filters)
    {
        $requirementId = null;

        // Si se envió un código RRTI, resolvemos su UUID en memoria
        if (!empty($filters['rrti'])) {
            $cleanRrti = trim(str_replace('#', '', $filters['rrti']));
            $requirement = Requirement::where('rrti', $cleanRrti)->first();
            $requirementId = $requirement ? $requirement->id : '00000000-0000-0000-0000-000000000000';
        }

        return AuditLog::with(['user.person'])
            ->when(!empty($filters['start_date']), function ($q) use ($filters) {
                $q->whereDate('created_at', '>=', $filters['start_date']);
            })
            ->when(!empty($filters['end_date']), function ($q) use ($filters) {
                $q->whereDate('created_at', '<=', $filters['end_date']);
            })
            ->when(!empty($filters['user_id']), function ($q) use ($filters) {
                $q->where('user_id', $filters['user_id']);
            })
            ->when(!empty($filters['action']), function ($q) use ($filters) {
                $q->where('action', $filters['action']);
            })
            ->when($requirementId, function ($q) use ($requirementId) {
                // Filtro híbrido: busca en target_id o dentro del payload JSON
                $q->where(function ($sub) use ($requirementId) {
                    $sub->where('target_id', $requirementId)
                        ->orWhere('payload->record_id', $requirementId)
                        ->orWhere('payload->requirement_id', $requirementId);
                });
            })
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Genera el consolidado de gestión agrupado por consultor CSPE (DDD Compliant).
     */
    public function getConsultantManagement(array $filters)
    {
        // 1. Definimos los diccionarios de estado al inicio para usarlos en el Query Builder
        $activeStatuses = ['RC', 'ES-R', 'ATF-I', 'DT-I', 'COR-I', 'COE-I', 'PI-I', 'CER-I', 'CEE-I', 'PAP-I', 'AU-I', 'ATF-C', 'DT-C', 'COR-C', 'COE-C', 'PI-C', 'CER-C', 'CEE-C', 'PAP-C', 'AU-C'];
        $completedStatuses = ['RF'];
        $stoppedStatuses = ['DET', 'CAN'];

        // 2. Extraemos los requerimientos aplicando todos los filtros multidimensionales
        $requirements = DB::table('core.cspe_consultant_requirement as pivot')
            ->join('core.requirements as r', 'pivot.requirement_id', '=', 'r.id')
            ->select('pivot.cspe_consultant_id', 'r.rrti', 'r.status', 'r.progress_percentage')
            // Filtro por Fechas
            ->when(!empty($filters['start_date']), fn($q) => $q->whereDate('r.created_at', '>=', $filters['start_date']))
            ->when(!empty($filters['end_date']), fn($q) => $q->whereDate('r.created_at', '<=', $filters['end_date']))
            // Filtro por RRTI (Acepta formato con o sin '#')
            ->when(!empty($filters['rrti']), fn($q) => $q->where('r.rrti', 'like', '%' . trim(str_replace('#', '', $filters['rrti'])) . '%'))
            // Filtro por Estado Operativo
            ->when(!empty($filters['status_type']), function($q) use ($filters, $activeStatuses, $completedStatuses) {
                if ($filters['status_type'] === 'active') {
                    $q->whereIn('r.status', $activeStatuses);
                } elseif ($filters['status_type'] === 'completed') {
                    $q->whereIn('r.status', $completedStatuses);
                }
            })
            // Filtro por Nombre o Apellido del Consultor (Uniendo las tablas de identidad)
            ->when(!empty($filters['consultant_id']), fn($q) => $q->where('pivot.cspe_consultant_id', $filters['consultant_id']))
            ->get();

        // Si la consulta no arrojó resultados tras los filtros, retornamos una colección vacía de inmediato
        if ($requirements->isEmpty()) {
            return collect([]);
        }

        // 3. Extraemos los IDs únicos de los consultores resultantes
        $cspeConsultantIds = $requirements->pluck('cspe_consultant_id')->unique()->toArray();

        // 4. Dominio Security: Resolución de identidades
        $consultantsIdentity = CspeConsultant::with('person')
            ->whereIn('id', $cspeConsultantIds)
            ->get()
            ->keyBy('id');

        $result = [];
        $grouped = $requirements->groupBy('cspe_consultant_id');

        // 5. Agrupación y clasificación
        foreach ($grouped as $consultantId => $reqs) {
            $consultant = $consultantsIdentity->get($consultantId);
            $person = $consultant ? $consultant->person : null;

            $activeReqs = $reqs->filter(fn($r) => in_array($r->status, $activeStatuses))->values();
            $completedReqs = $reqs->filter(fn($r) => in_array($r->status, $completedStatuses))->values();
            $stoppedReqs = $reqs->filter(fn($r) => in_array($r->status, $stoppedStatuses))->values();

            $result[] = [
                'consultant_id'     => $consultantId,
                'first_name'        => $person ? $person->first_name : 'Consultor',
                'last_name'         => $person ? $person->last_name : 'Desconocido',
                'total_asignados'   => $reqs->count(),
                'req_en_proceso'    => $activeReqs->count(),
                'req_completados'   => $completedReqs->count(),
                'req_detenidos'     => $stoppedReqs->count(),
                'active_details'    => $activeReqs,
                'completed_details' => $completedReqs,
            ];
        }

        usort($result, fn($a, $b) => $b['total_asignados'] <=> $a['total_asignados']);

        return collect($result);
    }

    /**
     * Retorna el diccionario de consultores CSPE formateado para los selectores del frontend.
     */
    public function getCspeConsultantsDictionary(): \Illuminate\Support\Collection
    {
        return CspeConsultant::with('person')
            ->get()
            ->map(function ($consultant) {
                return [
                    'id'   => $consultant->id,
                    'name' => trim(($consultant->person->first_name ?? '') . ' ' . ($consultant->person->last_name ?? ''))
                ];
            });
    }

    
    /**
     * Genera el Histórico de Pases a Producción y evolución de roles.
     */
    public function getProductionDeployments(array $filters)
    {
        $coreData = $this->getDeploymentsCoreData($filters);
        $requirementIds = $coreData->keys()->toArray();

        if (empty($requirementIds)) {
            return collect([]);
        }

        $baseRolesFlat = DB::table('workflow.requirements_roles')
            ->whereIn('requirement_id', $requirementIds)
            ->whereNull('deleted_at')
            ->get();
            
        $baseRoles = $baseRolesFlat->groupBy('requirement_id');
        
        $papOrders = DB::table('workflow.pap_orders')
            ->whereIn('requirement_id', $requirementIds)->whereNull('deleted_at')->orderBy('date', 'desc')->get()->groupBy('requirement_id');
        
        $papRoles = DB::table('workflow.pap_roles')
            ->whereIn('requirement_id', $requirementIds)->whereNull('deleted_at')->orderBy('created_at', 'desc')->get()->groupBy('requirement_id');

        $result = [];

        foreach ($coreData as $reqId => $core) {
            $reqBaseRoles = $baseRoles->get($reqId, collect());
            $orders       = $papOrders->get($reqId, collect());
            $rolesLog     = $papRoles->get($reqId, collect());

            $referenceDate = $core->pap_c_date ?? ($orders->first()->date ?? null);

            if ($this->isExcludedByDateFilter($referenceDate, $filters)) {
                continue;
            }

            // Llamamos a la función con 3 parámetros, ya que ahora calcula en memoria
            $rolesDetails = $this->buildRolesProgress($reqBaseRoles, $rolesLog, $orders);
            $rolesInProdCount = collect($rolesDetails)->where('current_status', 'IN_PRODUCTION')->count();
            $totalRoles = $reqBaseRoles->count();

            $result[] = [
                'rrti'              => $core->rrti,
                'description'       => $core->description,
                'current_status'    => $core->status,
                'pap_c_date'        => $core->pap_c_date,
                'total_roles'       => $totalRoles,
                'roles_in_prod'     => $rolesInProdCount,
                'is_fully_deployed' => ($totalRoles > 0 && $totalRoles === $rolesInProdCount),
                'roles_details'     => $rolesDetails,
                'orders'            => $orders,
                'rollbacks'         => $this->extractDecodedRollbacks($rolesLog, $reqBaseRoles)
            ];
        }

        usort($result, function($a, $b) {
            return strtotime($b['pap_c_date'] ?? '1970-01-01') <=> strtotime($a['pap_c_date'] ?? '1970-01-01');
        });

        return collect($result);
    }

    /* ====================================================================
        MÉTODOS PRIVADOS DE SOPORTE (Refactorización)
       ==================================================================== */

    private function getDeploymentsCoreData(array $filters)
    {
        return DB::table('core.requirements as r')
            ->leftJoin('workflow.requirement_phase_history as rph', function($join) {
                $join->on('r.id', '=', 'rph.requirement_id')
                    ->where('rph.phase_status_code', '=', 'PAP-C');
            })
            ->select('r.id as requirement_id', 'r.rrti', 'r.description', 'r.status', 'rph.created_at as pap_c_date')
            ->whereIn('r.status', ['PAP-I', 'PAP-C', 'AU-I', 'AU-C', 'RF'])
            ->when(!empty($filters['rrti']), fn($q) => $q->where('r.rrti', 'like', '%' . trim(str_replace('#', '', $filters['rrti'])) . '%'))
            ->get()
            ->keyBy('requirement_id');
    }

    private function isExcludedByDateFilter(?string $referenceDate, array $filters): bool
      {
          if (!$referenceDate) return false;

          if (!empty($filters['start_date']) && \Carbon\Carbon::parse($referenceDate)->startOfDay() < \Carbon\Carbon::parse($filters['start_date'])->startOfDay()) {
              return true;
          }
          if (!empty($filters['end_date']) && \Carbon\Carbon::parse($referenceDate)->endOfDay() > \Carbon\Carbon::parse($filters['end_date'])->endOfDay()) {
              return true;
          }
          
          return false;
      }

    private function buildRolesProgress(
        \Illuminate\Support\Collection $reqBaseRoles, 
        \Illuminate\Support\Collection $rolesLog, 
        \Illuminate\Support\Collection $orders
    ): array {
        $details = [];
        foreach ($reqBaseRoles as $baseRole) {
            $roleHistory = $rolesLog->where('requirement_role_id', $baseRole->id);
            $lastAttempt = $roleHistory->first();
            
            $orderInfo = null;
            $orderDate = null;
            $attempts  = 0;
            
            if ($lastAttempt) {
                // Si existe registro en pap_roles, ya cuenta como el intento actual (1)
                $attempts = 1; 
                
                // Le sumamos todos los rechazos previos registrados en su historial inmutable
                if (!empty($lastAttempt->rejection_history)) {
                    $historyData = json_decode($lastAttempt->rejection_history, true);
                    if (is_array($historyData)) {
                        $attempts += count($historyData);
                    }
                }
                
                if ($lastAttempt->order_id) {
                    $order = $orders->firstWhere('id', $lastAttempt->order_id);
                    if ($order) {
                        $orderInfo = $order->order_number;
                        $orderDate = $order->date;
                    }
                }
            }
            
            $details[] = [
                'role_name'      => $baseRole->role_name,
                'current_status' => $lastAttempt->status ?? 'SIN PROCESAR',
                'attempts'       => $attempts,
                'order_number'   => $orderInfo,
                'order_date'     => $orderDate
            ];
        }
        return $details;
    }

    private function extractDecodedRollbacks(\Illuminate\Support\Collection $rolesLog, \Illuminate\Support\Collection $reqBaseRoles): \Illuminate\Support\Collection
    {
        $rollbacks = collect();
        foreach ($rolesLog as $roleLog) {
            if (!empty($roleLog->rejection_history)) {
                $history = json_decode($roleLog->rejection_history, true);
                if (is_array($history)) {
                    $bRole = $reqBaseRoles->firstWhere('id', $roleLog->requirement_role_id);
                    
                    $history = array_map(function($item) use ($bRole) {
                        return array_merge($item, [
                            'role_name'    => $bRole->role_name ?? 'Desconocido',
                            'order_number' => $item['order_number'] ?? 'N/A' 
                        ]);
                    }, $history);
                    
                    $rollbacks = $rollbacks->merge($history);
                }
            }
        }
        return $rollbacks->sortByDesc('rejected_at')->values(); 
    }

    /**
     * Genera la Sábana Operativa masiva a nivel de requerimiento y sus fechas de fases.
     */
    public function getOperationalSheet(array $filters)
    {
        // 1. Extracción base de requerimientos con sus relaciones directas (Consultor CSPE y Funcional)
        $requirements = DB::table('core.requirements as r')
            ->leftJoin('core.cspe_consultant_requirement as ccr', 'r.id', '=', 'ccr.requirement_id')
            ->leftJoin('security.cspe_consultants as cc', 'ccr.cspe_consultant_id', '=', 'cc.id')
            ->leftJoin('security.persons as cp', 'cc.person_id', '=', 'cp.id')
            ->leftJoin('security.functional_consultants as fc', 'r.functional_consultant_id', '=', 'fc.id')
            ->leftJoin('security.persons as fcp', 'fc.person_id', '=', 'fcp.id')
            ->select(
                'r.id',
                'r.rrti',
                'r.requirement_type',
                'r.description',
                'r.status',
                'r.progress_percentage',
                'r.creation_date',
                'r.completion_date',
                DB::raw("TRIM(CONCAT(cp.first_name, ' ', cp.last_name)) as cspe_consultant"),
                DB::raw("TRIM(CONCAT(fcp.first_name, ' ', fcp.last_name)) as functional_consultant")
            )
            ->when(!empty($filters['start_date']), fn($q) => $q->whereDate('r.creation_date', '>=', $filters['start_date']))
            ->when(!empty($filters['end_date']), fn($q) => $q->whereDate('r.creation_date', '<=', $filters['end_date']))
            ->get();

        if ($requirements->isEmpty()) {
            return collect([]);
        }

        $reqIds = $requirements->pluck('id')->toArray();

        // 2. Extraemos el histórico de fases para mapear las fechas de cierre (DT, COR, COE, PI, CER, CEE, PAP, AU)
        $phaseHistories = DB::table('workflow.requirement_phase_history')
            ->whereIn('requirement_id', $reqIds)
            ->select('requirement_id', 'phase_status_code', 'transitioned_at')
            ->get()
            ->groupBy('requirement_id');

        // 3. Cruzamos y estructuramos la información final en memoria sin horas
        $sheetData = $requirements->map(function ($req) use ($phaseHistories) {
            $history = $phaseHistories->get($req->id, collect());

            // Función auxiliar interna para limpiar la fecha (quita la hora si viene con timestamp)
            $cleanDate = function($timestamp) {
                if (empty($timestamp) || $timestamp === '-') return '-';
                return substr($timestamp, 0, 10); // Extrae solo 'YYYY-mm-dd'
            };

            return [
                'rrti'                  => $req->rrti,
                'requirement_type'      => $req->requirement_type,
                'description'           => $req->description,
                'status'                => $req->status,
                'progress_percentage'   => $req->progress_percentage . '%',
                'cspe_consultant'       => $req->cspe_consultant ?? 'No asignado',
                'functional_consultant' => $req->functional_consultant ?? 'No asignado',
                'creation_date'         => $cleanDate($req->creation_date),
                'completion_date'       => $req->completion_date ? $cleanDate($req->completion_date) : 'En Proceso',
                // Fechas de cierre de fases limpias sin hora
                'dt_close_date'         => $cleanDate(optional($history->firstWhere('phase_status_code', 'DT-C'))->transitioned_at),
                'cor_close_date'        => $cleanDate(optional($history->firstWhere('phase_status_code', 'COR-C'))->transitioned_at),
                'coe_close_date'        => $cleanDate(optional($history->firstWhere('phase_status_code', 'COE-C'))->transitioned_at),
                'pi_close_date'         => $cleanDate(optional($history->firstWhere('phase_status_code', 'PI-C'))->transitioned_at),
                'cer_close_date'        => $cleanDate(optional($history->firstWhere('phase_status_code', 'CER-C'))->transitioned_at),
                'cee_close_date'        => $cleanDate(optional($history->firstWhere('phase_status_code', 'CEE-C'))->transitioned_at),
                'pap_close_date'        => $cleanDate(optional($history->firstWhere('phase_status_code', 'PAP-C'))->transitioned_at),
                'au_close_date'         => $cleanDate(optional($history->firstWhere('phase_status_code', 'AU-C'))->transitioned_at),
            ];
        });

        return $sheetData;
    }

    /**
     * Genera los indicadores clave (KPIs) para el Resumen Ejecutivo.
     */
    public function getOperationalExecutiveSummary(array $filters): array
    {
        $query = DB::table('core.requirements')
            ->when(!empty($filters['start_date']), fn($q) => $q->whereDate('creation_date', '>=', $filters['start_date']))
            ->when(!empty($filters['end_date']), fn($q) => $q->whereDate('creation_date', '<=', $filters['end_date']));

        $total = (clone $query)->count();
        $completed = (clone $query)->whereIn('status', ['RF', 'AU-C'])->count();
        $inProgress = (clone $query)->whereNotIn('status', ['RF', 'AU-C'])->count();

        // Agrupación por tipo de requerimiento
        $byType = (clone $query)
            ->select('requirement_type', DB::raw('count(*) as total'))
            ->groupBy('requirement_type')
            ->pluck('total', 'requirement_type')
            ->toArray();

        // Agrupación por estatus actual
        $rawStatus = (clone $query)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->get();

        $byStatus = [];
        foreach($rawStatus as $item) {
            $label = $this->translateStatusCode($item->status);
            $byStatus[$label] = ($byStatus[$label] ?? 0) + $item->total;
        }

        return [
            'total_requirements' => $total,
            'completed_count'    => $completed,
            'in_progress_count'  => $inProgress,
            'efficiency_rate'    => $total > 0 ? round(($completed / $total) * 100, 1) : 0,
            'by_type'            => $byType,
            'by_status'          => $byStatus,
        ];
    }

    private function translateStatusCode(string $code): string
    {
        $translations = [
            'RC'     => 'Requerimiento Creado',
            'ES-R'   => 'Planificación Realizada',
            'ATF-OPEN'=> 'Planificación Realizada',
            'ATF-I'  => 'Análisis Técnico Funcional en Proceso',
            'ATF-C'  => 'Análisis Técnico Funcional Cerrado',
            'DT-I'   => 'Diseño Técnico en Proceso',
            'DT-C'   => 'Diseño Técnico Cerrado',
            'COR-I'  => 'Construcción de Roles en Proceso',
            'COR-C'  => 'Construcción de Roles Cerrado',
            'COE-I'  => 'Construcción de Entregables en Proceso',
            'COE-C'  => 'Construcción de Entregables Cerrado',
            'PI-I'   => 'Pruebas Integrales en Proceso',
            'PI-C'   => 'Pruebas Integrales Cerradas',
            'CER-I'  => 'Certificación en Proceso',
            'CER-C'  => 'Certificación Cerrada',
            'CEE-I'  => 'Certificacion de Uso en Proceso',
            'CEE-C'  => 'Certificacion de Uso Cerrada',
            'PAP-I'  => 'Pases a Producción en Proceso',
            'PAP-C'  => 'Pases a Producción Cerrado',
            'AU-I'   => 'Asignación a Usuarios en Proceso',
            'AU-C'   => 'Asgignación a Usuarios Cerrada',
            'RF'     => 'Requerimiento Finalizado',
        ];

        return $translations[$code] ?? $code;
    }
    
}
