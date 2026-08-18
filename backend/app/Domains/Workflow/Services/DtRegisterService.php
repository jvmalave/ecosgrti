<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Services;

use App\Domains\Audit\Services\AuditService;
use App\Domains\Core\Dictionaries\CacheKeyDictionary;
use App\Domains\Workflow\Models\DtRegister;
use App\Domains\Workflow\Models\DtRole;
use App\Domains\Workflow\Services\PhaseTransitionService;
use App\Domains\Workflow\Services\ProgressCalculationService;
use App\Domains\Workflow\Traits\ManagesPhaseRegisters; 
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;


class DtRegisterService 
{
    
    use ManagesPhaseRegisters;

    public function __construct(
        protected readonly AuditService $auditService,
        protected readonly PhaseTransitionService $phaseTransitionService,
        protected readonly ProgressCalculationService $progressService
    ) {
      
    }

    // =====================================================================
    // 1. CONTRATO CON EL TRAIT MANAGESPHASEREGISTERS
    // =====================================================================

    protected function getRegisterModel(): string 
    {
        return DtRegister::class;
    }

    protected function getParentForeignKey(): string 
    {
        return 'role_id'; 
    }

    protected function getPhaseInitCode(): string 
    {
        return 'DT-I';
    }

    protected function getCacheKeyPrefix(): string 
    {
        return 'dt_roles';
    }

    /**
     * Devuelve la clase del modelo Padre (El componente al que pertenece la bitácora)
     */
    protected function getComponentModel(): string 
    {
        return DtRole::class; 
    }

    /**
     * Devuelve el nombre de la columna en el modelo Padre que lo enlaza con el Requerimiento
     */
    protected function getRequirementColumn(): string 
    {
        return 'requirement_id'; // En workflow.dt_roles, la columna es requirement_id
    }

    /**
     * Cuenta cuántos registros de bitácora DT existen en total para un requerimiento.
     */
    protected function countRegistersInRequirement(string $requirementId): int 
    {
        return DB::table('workflow.dt_registers')
            ->join('workflow.dt_roles', 'workflow.dt_registers.role_id', '=', 'workflow.dt_roles.id')
            ->where('workflow.dt_roles.requirement_id', $requirementId)
            ->count();
    }

    // =====================================================================
    // 2. MÉTODOS ESPECÍFICOS DE LA BITÁCORA DE DT
    // =====================================================================

    /**
     * CONSULTA DE REGISTROS DE BITÁCORA DE DISEÑO TÉCNICO POR ROL (CACHÉ REDIS)
     */
    public function getRegistersByRole(string $roleId): array
    {
        $cacheKey = CacheKeyDictionary::componentRegisters($roleId, 'dt');
        
        return Cache::remember($cacheKey, 600, function () use ($roleId) {
            return DtRegister::where('role_id', $roleId)
                ->orderBy('date', 'desc')
                ->get()
                ->toArray();
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
            
            // Purgas estandarizadas mediante el Diccionario
            Cache::forget(CacheKeyDictionary::componentRegisters($register->role_id, 'dt'));

            return $register;
        });
    }

    /**
     * ELIMINAR REGISTRO DE DISEÑO TÉCNICO
     */
    public function deleteRegister(DtRegister $register): void
    {
        DB::transaction(function () use ($register) {
            $roleId = $register->role_id;
            $registerId = $register->id;
            $requirementId = $register->role->requirement_id;
            
            $register->delete(); 
            
            $this->auditService->logModelChange(
                'DELETE_PHYSICAL_DT_REG',
                'Eliminación física de registro DT ' . $register->title . ' del rol: ' . $register->role->name,
                ['record_id' => $registerId],
                auth()->id(),
                $requirementId
            );
            
            // Evicción de Caché usando el Diccionario Centralizado
            Cache::forget(CacheKeyDictionary::componentRegisters($roleId, 'dt'));
            Cache::forget(CacheKeyDictionary::phaseComponentsList($requirementId, 'dt'));
        });
    }
}