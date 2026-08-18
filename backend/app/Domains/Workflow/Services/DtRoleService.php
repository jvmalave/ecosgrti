<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Services;

use App\Domains\Workflow\Models\DtRole;
use App\Domains\Workflow\Models\RequirementRole;
use App\Domains\Workflow\Services\Base\AbstractPhaseComponentService;
use App\Domains\Core\Dictionaries\CacheKeyDictionary;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Model;

class DtRoleService extends AbstractPhaseComponentService
{
    // =====================================================================
    // 1. CONTRATO POLIMÓRFICO DE LA CLASE PADRE
    // =====================================================================

    protected function getComponentModel(): string 
    { 
        return DtRole::class; 
    }

    protected function getCacheKeyPrefix(): string 
    { 
        return 'dt_roles'; // Asegúrate de que coincida con tu convención
    }

    protected function getPhaseCode(): string 
    { 
        return 'DT-C'; 
    }

    protected function getPhaseInitCode(): string 
    { 
        return 'DT-I'; 
    }

    protected function getRequiredPredecessorPhases(): array {
        return ['ATF-C']; 
    }

    /**
     * Hook polimórfico: Validación específica antes de cerrar un rol de DT.
     */
    protected function validateStatusTransition(Model $component, string $newStatus): void
    {
        if ($newStatus === 'CLOSED') {
            $registersCount = $component->registers()->count();
            if ($registersCount === 0) {
                abort(422, 'No se puede cerrar un rol sin actividades de diseño documentadas.');
            }
        }
    }

    // =====================================================================
    // 2. LÓGICA EXCLUSIVA DE LA FASE DE DISEÑO TÉCNICO
    // =====================================================================

    /**
     * INICIALIZAR Y RECUPERAR ROLES DE DISEÑO TÉCNICO PARA UN REQUERIMIENTO
     * Este método es único de DT porque clona los roles desde ATF.
     */
    public function initializeRoles(string $requirementId): array
    {
        // VERIFICA LA EXISTENCIA DE ROLES EN ATF
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

        // Obtención de la lista a través del caché usando el Diccionario
        $cacheKey = CacheKeyDictionary::phaseComponentsList($requirementId, $this->getPhaseInitCode());
        
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
        $cacheKey = CacheKeyDictionary::phaseComponentsList($requirementId, $this->getPhaseInitCode());
        
        return Cache::remember($cacheKey, 600, function () use ($requirementId) {
            return DtRole::where('requirement_id', $requirementId)
                        ->withCount('registers')
                        ->get()
                        ->toArray();
        });
    }

    // =====================================================================
    // NOTA ARQUITECTÓNICA:
    // Toda la lógica de cierre individual, cierre global, cálculo de progreso, 
    // vanguardia, auditoría y limpieza de caché ahora se ejecuta desde la 
    // clase AbstractPhaseComponentService.
    // =====================================================================
}