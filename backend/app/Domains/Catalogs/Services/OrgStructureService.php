<?php

namespace App\Domains\Catalogs\Services;

use App\Domains\Catalogs\Models\Society;
use App\Domains\Catalogs\Models\System;
use App\Domains\Catalogs\Models\RequestingUnit;
use App\Domains\Security\Models\FunctionalConsultant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;


class OrgStructureService
{
    private const CACHE_KEY_TREE = 'catalogs_org_tree';
    private const CACHE_KEY_ACTIVE_UNITS = 'catalogs_active_units';
    private const CACHE_TTL_SECONDS = 86400; // 24 Horas

    /**
     * Obtiene el árbol jerárquico completo desde Redis Caché (o DB en caso de Cache Miss).
     */
    public function getFullTree(): array
    {
        return Cache::remember(self::CACHE_KEY_TREE, self::CACHE_TTL_SECONDS, function () {
            return [
                'societies' => Society::all()->toArray(),
                'systems' => System::all()->toArray(),
                'requesting_units' => RequestingUnit::all()->toArray(),
            ];
        });
    }
    /**
     * Alterna el estatus (is_active) de un nodo evaluando reglas de integridad restrictiva.
     *
     * @throws ValidationException
     */
    public function toggleNodeStatus(string $nodeType, string $id, bool $targetStatus): void
    {
        DB::transaction(function () use ($nodeType, $id, $targetStatus) {
            $adminId = Auth::id() ?? '00000000-0000-0000-0000-000000000000';

            switch (mb_strtolower($nodeType)) {
                case 'societies':
                case 'society':
                    $this->handleSocietyStatusChange($id, $targetStatus, $adminId);
                    break;

                case 'systems':
                case 'system':
                    $this->handleSystemStatusChange($id, $targetStatus, $adminId);
                    break;

                case 'requesting-units':
                case 'requesting_unit':
                case 'unit':
                    $this->handleUnitStatusChange($id, $targetStatus, $adminId);
                    break;

                default:
                    throw ValidationException::withMessages([
                        'node_type' => ['El tipo de nodo especificado no es válido.']
                    ]);
            }

            // Invalida la caché distribuida en Redis tras la mutación
            $this->flushOrgCache();
        });
    }
    
    //==========================================
    // MÉTODOS DE CREACIÓN (POST) - Nuevos
    // ==========================================

    public function createSociety(array $data): Society
    {
        return DB::transaction(function () use ($data) {
            $society = Society::create($data);
            
            $adminId = Auth::id() ?? '00000000-0000-0000-0000-000000000000';
            $this->logAudit($adminId, 'SOCIETY', $society->id, 'CREATE', $society->name);
            
            $this->flushOrgCache();
            return $society;
        });
    }

    public function createSystem(array $data): System
    {
        return DB::transaction(function () use ($data) {
            $system = System::create($data);
            
            $adminId = Auth::id() ?? '00000000-0000-0000-0000-000000000000';
            $this->logAudit($adminId, 'SYSTEM', $system->id, 'CREATE', $system->name);
            
            $this->flushOrgCache();
            return $system;
        });
    }

    public function createRequestingUnit(array $data): RequestingUnit
    {
        return DB::transaction(function () use ($data) {
            $unit = RequestingUnit::create($data);
            
            $adminId = Auth::id() ?? '00000000-0000-0000-0000-000000000000';
            $this->logAudit($adminId, 'REQUESTING_UNIT', $unit->id, 'CREATE', $unit->name);
            
            $this->flushOrgCache();
            return $unit;
        });
    }

    // ==========================================
    // MÉTODOS DE ACTUALIZACIÓN (PUT) - Refactorizados
    // ==========================================

    public function updateSociety(string $id, array $data): void
    {
        DB::transaction(function () use ($id, $data) {
            $society = Society::findOrFail($id);
            $society->update($data);
            
            $adminId = Auth::id() ?? '00000000-0000-0000-0000-000000000000';
            $this->logAudit($adminId, 'SOCIETY', $id, 'UPDATE', $society->name);
            
            $this->flushOrgCache();
        });
    }

    public function updateSystem(string $id, array $data): void
    {
        DB::transaction(function () use ($id, $data) {
            $system = System::findOrFail($id);
            $system->update($data);

            $adminId = Auth::id() ?? '00000000-0000-0000-0000-000000000000';
            $this->logAudit($adminId, 'SYSTEM', $id, 'UPDATE', $system->name);

            $this->flushOrgCache();
        });
    }

    public function updateRequestingUnit(string $id, array $data): void
    {
        DB::transaction(function () use ($id, $data) {
            $unit = RequestingUnit::findOrFail($id);
            $unit->update($data);

            $adminId = Auth::id() ?? '00000000-0000-0000-0000-000000000000';
            $this->logAudit($adminId, 'REQUESTING_UNIT', $id, 'UPDATE', $unit->name);

            $this->flushOrgCache();
        });
    }

    // ==========================================
    // MÉTODOS DE CAMBIO DE ESTATUS (PATCH) - Refactorizados
    // ==========================================

    private function handleSocietyStatusChange(string $id, bool $targetStatus, string $adminId): void
    {
        $society = Society::findOrFail($id);

        if (!$targetStatus) {
            $activeSystemsCount = System::where('society_id', $id)->where('is_active', true)->count();
            if ($activeSystemsCount > 0) {
                throw ValidationException::withMessages(['is_active' => ['No se puede inactivar la Sociedad porque posee Sistemas operativamente activos asociadas.']]);
            }
        }

        $society->update(['is_active' => $targetStatus]);
        // Pasamos el nombre al log
        $this->logAudit($adminId, 'SOCIETY', $id, $targetStatus ? 'REACTIVATE' : 'DEACTIVATE', $society->name);
    }

    private function handleSystemStatusChange(string $id, bool $targetStatus, string $adminId): void
    {
        $system = System::with('society')->findOrFail($id);

        if ($targetStatus) {
            if (!$system->society || !$system->society->is_active) {
                throw ValidationException::withMessages(['is_active' => ['No se puede reactivar el Sistema porque la Sociedad padre se encuentra inactiva.']]);
            }
        } else {
            $activeUnitsCount = RequestingUnit::where('system_id', $id)->where('is_active', true)->count();
            if ($activeUnitsCount > 0) {
                throw ValidationException::withMessages(['is_active' => ['No se puede inactivar el Sistema porque posee Unidades Solicitantes operativas asociadas.']]);
            }
        }

        $system->update(['is_active' => $targetStatus]);
        // Pasamos el nombre al log
        $this->logAudit($adminId, 'SYSTEM', $id, $targetStatus ? 'REACTIVATE' : 'DEACTIVATE', $system->name);
    }

    private function handleUnitStatusChange(string $id, bool $targetStatus, string $adminId): void
    {
        $unit = RequestingUnit::with('system.society')->findOrFail($id);

        if ($targetStatus) {
            if (!$unit->system || !$unit->system->is_active || !$unit->system->society || !$unit->system->society->is_active) {
                throw ValidationException::withMessages(['is_active' => ['No se puede reactivar la unidad porque un ancestro superior se encuentra inactivo.']]);
            }
        } else {
            $activeConsultantsCount = FunctionalConsultant::where('requesting_unit_id', $id)->count();
            if ($activeConsultantsCount > 0) {
                throw ValidationException::withMessages(['is_active' => ['No se puede inactivar la unidad porque posee consultores funcionales activos asignados.']]);
            }
        }

        $unit->update(['is_active' => $targetStatus]);
        // Pasamos el nombre al log
        $this->logAudit($adminId, 'REQUESTING_UNIT', $id, $targetStatus ? 'REACTIVATE' : 'DEACTIVATE', $unit->name);
    }

    // ==========================================
    // MÉTODO DE AUDITORÍA FORENSE 
    // ==========================================
    
    /**
     * Registra la traza inmutable de auditoría en la base de datos con descripción dinámica.
     */
    private function logAudit(string $adminId, string $nodeType, string $nodeId, string $action, string $nodeName): void
    {
        $auditService = app(\App\Domains\Audit\Services\AuditService::class);

        // Diccionario para traducir la acción a un lenguaje de negocio claro
        $actionTranslations = [
            'CREATE'     => 'Creación estructural',
            'UPDATE'     => 'Actualización estructural',
            'DEACTIVATE' => 'Inactivación lógica',
            'REACTIVATE' => 'Reactivación estructural'
        ];
        
        $actionDesc = $actionTranslations[$action] ?? 'Modificación estructural';
        
        // Construimos la descripción exacta solicitada: (Ej: Creación estructural en la SOCIETY: CANTV)
        $description = "{$actionDesc} en la " . strtoupper($nodeType) . ": " . $nodeName;

        $payload = [
            'node_type' => $nodeType,
            'record_id' => $nodeId,
            'node_name' => $nodeName, // Guardamos el nombre en el payload JSONB para facilitar búsquedas futuras
            'status'    => in_array($action, ['REACTIVATE', 'CREATE']) ? true : false,
        ];

        $auditService->logModelChange(
            $action,
            $description,
            $payload,
            $adminId,
            $nodeId
        );
    }

  
      // ==========================================
      // MÉTODO DE  INVALIDACIÓN DE CACHE 
      // ==========================================

    /**
     * Invalida las llaves de caché de catálogos en Redis
     */
    public function flushOrgCache(): void
    {
        Cache::forget(self::CACHE_KEY_TREE);
        Cache::forget(self::CACHE_KEY_ACTIVE_UNITS);
    }
}