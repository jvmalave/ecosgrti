<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Services;

use App\Domains\Workflow\Models\PiRole;
use App\Domains\Workflow\Models\PiRegister;
use App\Domains\Workflow\Traits\ManagesPhaseRegisters; 
use App\Domains\Audit\Services\AuditService;
use App\Domains\Workflow\Services\PhaseTransitionService;
use App\Domains\Workflow\Services\ProgressCalculationService;
use App\Domains\Core\Dictionaries\CacheKeyDictionary;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Exception;

class PiRegisterService
{
    // Habilitamos los-poderes transaccionales
    use ManagesPhaseRegisters;

    public function __construct(
        protected readonly AuditService $auditService,
        protected readonly PhaseTransitionService $phaseTransitionService,
        protected readonly ProgressCalculationService $progressService
    ) {}

    // =====================================================================
    // 1. CONTRATO CON EL TRAIT MANAGESPHASEREGISTERS
    // =====================================================================

    protected function getRegisterModel(): string 
    {
        return PiRegister::class;
    }

    protected function getParentForeignKey(): string 
    {
        return 'pi_role_id'; // Clave foránea en workflow.pi_registers
    }

    protected function getPhaseInitCode(): string 
    {
        return 'PI-I'; // El código con el que inicia Pruebas Integrales
    }

    protected function getCacheKeyPrefix(): string 
    {
        return 'pi_roles';
    }

    protected function getComponentModel(): string 
    {
        return PiRole::class; 
    }

    protected function getRequirementColumn(): string 
    {
        return 'requirement_id'; 
    }

    /**
     * Cuenta cuántos registros de bitácora PI existen en total para un requerimiento.
     */
    protected function countRegistersInRequirement(string $requirementId): int 
    {
        return DB::table('workflow.pi_registers')
            ->join('workflow.pi_roles', 'workflow.pi_registers.pi_role_id', '=', 'workflow.pi_roles.id')
            ->where('workflow.pi_roles.requirement_id', $requirementId)
            ->whereNull('workflow.pi_registers.deleted_at') 
            ->count();
    }

    // =====================================================================
    // 2. MÉTODOS ESPECÍFICOS DE LA BITÁCORA DE PI
    // =====================================================================

    /**
     * Devuelve el formato exacto (RawRegistersResponse) que espera el frontend polimórfico.
     * Combina la obtención del rol y sus registros.
     */
    public function getRegistersFormatted(string $roleId): array
    {
        // En un escenario real, si el front fuera 100% polimórfico con caché, usaríamos Redis aquí.
        // Mantenemos tu consulta Eloquent estructurada para retrocompatibilidad con la US32.
        $role = PiRole::with(['requirement', 'registers' => function ($query) {
            $query->orderBy('created_at', 'desc'); 
        }])
        ->join('workflow.requirements_roles as rr', 'workflow.pi_roles.requirement_role_id', '=', 'rr.id') 
        ->select('workflow.pi_roles.*', 'rr.role_name') 
        ->findOrFail($roleId); 

        return [
            'id_req' => $role->requirement_id, 
            'nombre_rol' => $role->role_name, 
            'estado_rol' => $role->status, 
            'registros' => $role->registers->toArray() 
        ];
    }

    /**
     * ACTUALIZAR REGISTRO PI CON REGLAS DE NEGOCIO ESPECÍFICAS
     */
    public function updateRegister(string $regId, string $roleId, array $data, string $userId): array 
    {
        $this->ensureRoleIsInProgress($roleId); // 
        $register = PiRegister::where('id', $regId)->where('pi_role_id', $roleId)->firstOrFail(); 

        DB::transaction(function () use ($register, $data, $userId) { 
            $register->update([
                'title'       => $data['title'], 
                'date'        => $data['date'], 
                'description' => $data['description'], 
                'updated_by'  => $userId, // RN-PI-37: Rastro de edición
            ]);

            $this->auditService->logModelChange(
                'UPDATE_PI_REG', 
                "Actualización de Bitácora PI", 
                [], 
                $userId, 
                $register->id 
            );

            // Purgas estandarizadas mediante el Diccionario
            Cache::forget(CacheKeyDictionary::componentRegisters($register->pi_role_id, 'pi'));
        });

        return $register->toArray(); // 
    }

    /**
     * ELIMINAR REGISTRO PI CON REGLAS DE NEGOCIO ESPECÍFICAS
     */
    public function deleteRegister(string $regId, string $roleId, string $userId): void 
    {
        $this->ensureRoleIsInProgress($roleId); // 
        $register = PiRegister::where('id', $regId)->where('pi_role_id', $roleId)->firstOrFail();  

        DB::transaction(function () use ($register, $userId) { // 
            // RN-PI-25: Integridad referencial (verificamos que no esté amarrado a un resultado) 
            $hasResults = DB::table('workflow.pi_test_results')->where('pi_register_id', $register->id)->exists();  
            if ($hasResults) { // 
                throw new Exception("El registro posee pruebas ejecutadas amarradas a usuarios. No puede ser eliminado.", 422); // 
            }

            $requirementId = $register->requirement_id; 

            $register->update(['deleted_by' => $userId]); 
            $register->delete(); 
            
            $this->auditService->logModelChange(
                'DELETE_PI_REG', 
                "Eliminación Lógica de Bitácora PI" . " ({$register->title})", 
                [], 
                $userId, 
                $register->id 
            );

            // Evicción de Caché
            Cache::forget(CacheKeyDictionary::componentRegisters($register->pi_role_id, 'pi'));
            Cache::forget(CacheKeyDictionary::phaseComponentsList($requirementId, 'pi'));
        });
    }

    /**
     * RN-PI-31: Gatekeeper de Ciclo de Vida
     */
    private function ensureRoleIsInProgress(string $roleId): void 
    {
        $status = DB::table('workflow.pi_roles')->where('id', $roleId)->value('status'); 
        if ($status !== 'IN_PROGRESS') { // [cite: 144]
            throw new Exception("El rol se encuentra cerrado. No se permiten modificaciones en la bitácora.", 403);
        }
    }

    // =====================================================================
    // NOTA ARQUITECTÓNICA:
    // Toda la lógica de creación atómica, cálculo de progreso, vanguardia, 
    // y auditoría de la fase PI ahora se ejecuta desde el Trait ManagesPhaseRegisters.
    // =====================================================================
}