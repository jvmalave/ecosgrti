<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Services;

use App\Domains\Workflow\Models\DtRegister;
use App\Domains\Workflow\Models\DtRole;
use App\Domains\Workflow\Models\RequirementPhaseHistory;
use App\Domains\Core\Models\Requirement;
use App\Domains\Audit\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class DtRegisterService
{
    public function __construct(
        private readonly AuditService $auditService,
        private readonly ProgressCalculationService $progressService
    ) {}

    /**
     * CONSULTA DE REGISTROS DE BITÁCORA DE DISEÑO TÉCNICO POR ROL (CACHÉ REDIS)
     */
    public function getRegistersByRole(string $roleId)
    {
        $cacheKey = "dt_registers_cache_{$roleId}";
        
        return Cache::remember($cacheKey, 600, function () use ($roleId) {
            return DtRegister::where('role_id', $roleId)
                ->orderBy('date', 'desc')
                ->get()
                ->toArray();
        });
    }

    /**
     * AGREGAR REGISTRO DE DISEÑO TÉCNICO Y EVALUAR IMPACTO GLOBAL
     */
    public function storeRegister(DtRole $role, array $data): DtRegister
    {
        return DB::transaction(function () use ($role, $data) {
            $register = $role->registers()->create([
                'title' => $data['title'],
                'date' => $data['date'],
                'description' => $data['description'],
            ]);

            $this->auditService->logModelChange(
                'CREATE_DT_REGISTER',
                'Creación del registro DT:  ' . $data['title'] . '.del rol: ' . $role->name,
                ['record_id' => $register->id, 'title' => $data['title']],
                auth()->id()
            );

            // DISPARADOR DE ESTADO INICIAL: 
            // Si es el primer registro de diseño técnico, se marca la fase global como iniciada (DT-I) 
            //y se registra el hito en el historial.
            $totalRegisters = DB::table('workflow.dt_registers')
            ->join('workflow.dt_roles', 'dt_registers.role_id', '=', 'dt_roles.id')
            ->where('dt_roles.requirement_id', $role->requirement_id)
                ->count();

            if ($totalRegisters === 1) {
                $requirement = Requirement::findOrFail($role->requirement_id);
                
                // Sella el inicio de la fase
                $requirement->update(['status' => 'DT-I']);
                
                // Inserta el hito en el historial para que el algoritmo lo procese
                RequirementPhaseHistory::create([
                    'requirement_id' => $requirement->id,
                    'phase_status_code' => 'DT-I',
                    'executed_by_user_id' => auth()->id(),
                    'transitioned_at' => now(), //
                    'created_at' => now()
                ]);
                
                // Invoca el algoritmo de progreso polimórfico
                $calculatedProgress = $this->progressService->calculateGlobalProgress($requirement);
                
                $requirement->update([
                    'progress_percentage' => $calculatedProgress
                ]);

                // Propagación en caché para dashboards
                Cache::put("req_{$requirement->id}_progress", $calculatedProgress);
                
                $this->auditService->logModelChange(
                    'UPDATE_GLOBAL_PROGRESS',
                    'Avance global automatizado por inicio de fase DT',
                    ['record_id' => $requirement->id, 'new_progress' => $calculatedProgress],
                    auth()->id()
                );
            }

            Cache::forget("dt_registers_cache_{$role->id}");

            return $register;
        });
    }

    /**
     * ACTUALIZAR REGISTRO DE DISEÑO TÉCNICO
     */
    public function updateRegister(DtRegister $register, array $data): DtRegister
    {
        return DB::transaction(function () use ($register, $data) {
            $register->update([
                'title' => $data['title'] ?? $register->title,
                'date' => $data['date'] ?? $register->date,
                'description' => $data['description'] ?? $register->description,
            ]);

            $this->auditService->logModelChange(
                'UPDATE_DT_REG',
                'Actualización de registro DT: ' . $register->title . ' del rol: ' . $register->role->name,
                ['record_id' => $register->id, 'changes' => $register->getChanges()],
                auth()->id()
            );
            
            Cache::forget("dt_registers_cache_{$register->role_id}");

            return $register;
        });
    }

    /**
     * ELIMINAR REGISTRO DE DISEÑO TÉCNICO (Limpieza física controlada) Y ACTUALIZA CACHE DE ROLES.
     */
    public function deleteRegister(DtRegister $register): void
    {
        DB::transaction(function () use ($register) {
            $roleId = $register->role_id;
            $registerId = $register->id;
            
            $register->delete(); // Dispara ON DELETE CASCADE en PostgreSQL
            
            $this->auditService->logModelChange(
                'DELETE_PHYSICAL_DT_REG',
                'Eliminación física de registro DT ' . $register->title . ' del rol: ' . $register->role->name,
                ['record_id' => $registerId],
                auth()->id()
            );
            
            Cache::forget("dt_registers_cache_{$roleId}");
        });
    }
}