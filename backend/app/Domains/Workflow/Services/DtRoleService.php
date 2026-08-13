<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Services;

use App\Domains\Workflow\Models\DtRole;
use App\Domains\Workflow\Models\RequirementRole;
use App\Domains\Audit\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use App\Domains\Workflow\Services\PhaseTransitionService;
use App\Domains\Core\Models\Requirement;
use App\Domains\Workflow\Services\ProgressCalculationService;


class DtRoleService
{
    public function __construct(
        private readonly AuditService $auditService,
        private readonly PhaseTransitionService $phaseTransitionService,
        private readonly ProgressCalculationService $progressService
    ) {}

    /**
     * INICIALIZAR Y RECUPERAR ROLES DE DISEÑO TÉCNICO PARA UN REQUERIMIENTO
     */
    public function initializeRoles(string $requirementId): array
    {

        // VERIFICA LA EXISTENCIA DE ROLES EN ATF (requiere al menos un rol en ATF para poder acceder a la subfase DT )
        $atfRoles = RequirementRole::where('requirement_id', $requirementId)->get();

        if ($atfRoles->isEmpty()) {
            abort(403, 'Acceso inhabilitado: Requiere al menos un Rol mapeado en ATF.');
        }

        // IMPORTACION/SINCRONIZACION DE ROLES DE ATF A DT (IDEMPOTENTE)
        foreach ($atfRoles as $role) {
            DtRole::firstOrCreate(
                ['requirement_role_id' => $role->id],
                [
                    'requirement_id' => $requirementId,
                    'name' => $role->role_name,
                    'status' => 'IN_PROGRESS'
                ]
            );
        }

        // AUDITORIA FORENCE DE INICIALIZACIÓN DE ROLES
        // $this->auditService->logModelChange(
        //     'ACCESS_DT_ROLES',
        //     'Sincronización y acceso a la subfase de Diseño Técnico',
        //     ['record_id' => $requirementId, 'action' => 'INIT_DT'],
        //     auth()->id()
        // );
        
        $cacheKey = "req_{$requirementId}_dt_roles_meta";
        $rolesList = Cache::remember($cacheKey, 600, function () use ($requirementId) {
            return DtRole::where('requirement_id', $requirementId)
                        ->withCount('registers')
                        ->get()
                        ->toArray();
        });

        return [
            'requirement_id' => $requirementId,
            'roles_list' => $rolesList
        ];
    }

    /**
     * RECUPERAR ROLES DE DISEÑO TÉCNICO CON CONTEO DE BITÁCORAS
     */
    public function getRoles(string $requirementId)
    {
        $cacheKey = "req_{$requirementId}_dt_roles_meta";
        
        return Cache::remember($cacheKey, 600, function () use ($requirementId) {
            return DtRole::where('requirement_id', $requirementId)
                        ->withCount('registers')
                        ->get()
                        ->toArray();
        });
    }

    /**
     * GESTIONAR CICLO DE VIDA DEL ROL DE DISEÑO TÉCNICO (CERRAR/ACTIVAR)
     */
    public function changeRoleStatus(string $roleId, string $action): DtRole
    {
        $role = DtRole::findOrFail($roleId);
        
        return DB::transaction(function () use ($role, $action) {
            if ($action === 'CLOSE') {
                // VALIDACION DE DOCUMENTACION MINIMA PARA CIERRE DE ROL
                $registersCount = $role->registers()->count();
                if ($registersCount === 0) {
                    abort(422, 'No se puede cerrar un rol sin actividades de diseño documentadas.');
                }
                
                $role->update(['status' => 'CLOSED']);
                
                $this->auditService->logModelChange(
                    'CLOSE_ROLE_DT',
                    'Cierre técnico de la fase DT del rol: ' . $role->name,
                    ['record_id' => $role->id, 'new_status' => 'CLOSED'],
                    auth()->id()
                );
                
            } elseif ($action === 'REOPEN') {
                // RESTRICCION: Solo se puede reabrir un rol si la subfase global DT-I o ATF-C está activa (no cerrada)
                $status = DB::table('core.requirements')->where('id', $role->requirement_id)->value('status');
                
                if ($status !== 'DT-I' && $status !== 'ATF-C') {
                    abort(403, 'Acceso denegado: La subfase global está cerrada o en etapa superior.');
                }
                
                $role->update(['status' => 'IN_PROGRESS']);
                
                $this->auditService->logModelChange(
                    'REOPEN_ROLE_DT',
                    'Reapertura técnica de la fase DT del rol: ' . $role->name,
                    ['record_id' => $role->id, 'new_status' => 'IN_PROGRESS'],
                    auth()->id()
                );
            }
            // INVALIDA CACHÉ DE ROLES DE DISEÑO TÉCNICO PARA EL REQUERIMIENTO
            Cache::forget("req_{$role->requirement_id}_dt_roles_meta");
            Redis::incr('dashboard_version');

            return $role;
        });
    }


    
    /**
     * CIERRE GLOBAL DE LA SUBFASE DE DISEÑO TÉCNICO (HARD GATE)
     */
    public function closeDtPhase(string $requirementId): array
    {
        // 1. BLOQUEO ESTRICTO TEMPRANO
        // Buscamos el requerimiento y verificamos su estado antes de abrir la transacción
        $requirement = Requirement::findOrFail($requirementId);
        
        if ($requirement->status !== 'DT-I') {
            abort(422, 'La fase de Diseño Técnico ya se encuentra cerrada o no está activa.');
        }
        
        return DB::transaction(function () use ($requirementId, $requirement) {

            $reqName = $requirement->rrti;

            // HARD GATE: Verificar que NO existan roles en proceso
            $openRolesCount = DtRole::where('requirement_id', $requirementId)
                                    ->where('status', '!=', 'CLOSED')
                                    ->count();

            if ($openRolesCount > 0) {
                abort(422, 'Validación fallida: Todos los roles técnicos deben estar en estado CERRADO para avanzar de fase.');
            }

            // RECÁLCULO DEL AVANCE GLOBAL
            $calculatedProgress = $this->progressService->calculateGlobalProgress($requirement);

            // ACTUALIZACIÓN DEL ESTADO MAESTRO Y PROGRESO
            $requirement->update([
                'status' => 'DT-C', 
                'progress_percentage' => $calculatedProgress,
                'updated_at' => now()
            ]);

            // REGISTRO HISTÓRICO TRANSACCIONAL
            $this->phaseTransitionService->recordTransition(
                $requirementId,
                'DT-C',
                (string) auth()->id(),
                'Cierre global exitoso de la fase DT del requerimiento: ' . $reqName
            );

            // AUDITORÍA FORENSE
            $this->auditService->logModelChange(
                'CLOSE_DT_PHASE',
                'Cierre de fase de DT del requerimiento: ' . $reqName,
                [
                    'record_id' => $requirementId, 
                    'new_status' => 'DT-C',
                    'progress_percentage' => $calculatedProgress
                ],
                auth()->id()
            );

            // LIMPIEZA DE CACHÉ
            Cache::forget("req_{$requirementId}_dt_roles_meta");
            
            // ACTUALIZA LA CACHÉ DE PROGRESO (Para que el modal de detalle muestre el 25%)
            Cache::put("req_{$requirementId}_progress", $calculatedProgress);
            
            // Obliga al Dashboard a consultar la BD de nuevo
            Redis::incr('dashboard_version');

            return [
                'success' => true,
                'message' => 'Fase de Diseño Técnico cerrada con éxito.',
                'progress_percentage' => $calculatedProgress
            ];
        });
    }
}