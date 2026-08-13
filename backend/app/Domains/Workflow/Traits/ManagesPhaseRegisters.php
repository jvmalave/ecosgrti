<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Traits;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Model;
use App\Domains\Core\Models\Requirement; // Ajusta según tu namespace de modelos Core

trait ManagesPhaseRegisters
{
    /**
     * CONTRATO DEL TRAIT
     */
    abstract protected function getRegisterModel(): string;
    abstract protected function getParentForeignKey(): string;
    
    
    /**
     * CREAR REGISTRO O ACTIVIDAD (E INICIAR FASE GLOBAL)
     */
    public function storeRegister(string $parentId, array $data, string $requirementId): Model
    {
        $registerModel = $this->getRegisterModel();
        $foreignKey = $this->getParentForeignKey();

        return DB::transaction(function () use ($registerModel, $foreignKey, $parentId, $data, $requirementId) {
            
            // Inserción Atómica del Registro con Autoría
            $data[$foreignKey] = $parentId;
            $data['created_by'] = auth()->id();
            $data['updated_by'] = auth()->id();
            $register = $registerModel::create($data);

            // Trazabilidad Forense
            $this->auditService->logModelChange(
                'CREATE_PHASE_REGISTER',
                "Creación de evidencia técnica en la subfase " . $this->getPhaseInitCode(),
                ['record_id' => $register->id],
                auth()->id()
            );

            // Disparador Transaccional de Estado Global
            $totalRegisters = $this->countRegistersInRequirement($requirementId);

            if ($totalRegisters === 1) {
                $requirement = Requirement::lockForUpdate()->findOrFail($requirementId);
                
                // Si el requerimiento no está en el estado de inicio de la fase
                if ($requirement->status !== $this->getPhaseInitCode()) {
                    
                    $calculatedProgress = $this->progressService->calculateGlobalProgress($requirement);
                    
                    $requirement->update([
                        'status' => $this->getPhaseInitCode(),
                        'progress_percentage' => $calculatedProgress,
                        'updated_at' => now()
                    ]);
                    
                    $this->phaseTransitionService->recordTransition(
                        $requirementId,
                        $this->getPhaseInitCode(),
                        (string) auth()->id(),
                        'Apertura automática de la subfase al crear el primer registro técnico.'
                    );
                    
                    Cache::put("req_{$requirementId}_progress", $calculatedProgress, now()->addDays(1));
                    
                    // 🟢 REACTIVIDAD DASHBOARD: Avisamos que el status y progreso cambiaron
                    \Illuminate\Support\Facades\Redis::incr('dashboard_version');
                }
            }

            // Invalida Cachés (Bitácora y Botón Cerrar)
            Cache::forget($this->getCacheKeyPrefix() . "_registers_{$parentId}");
            // Invalida caché de componentes padres para que active el botón de Cerrar
            Cache::forget("req_{$requirementId}_" . $this->getCacheKeyPrefix() . "_roles_meta");

            return $register;
        });
    }

    /**
     * ACTUALIZAR REGISTRO O ACTIVIDAD EN LA BITÁCORA
     */
    public function updateRegister(string $registerId, array $data, string $parentId): Model
    {
        $registerModel = $this->getRegisterModel();

        return DB::transaction(function () use ($registerModel, $registerId, $data, $parentId) {
            $this->validateParentIsNotClosed($parentId);

            $register = $registerModel::findOrFail($registerId);
            
            // Actualización de autoría
            $data['updated_by'] = auth()->id();
            $register->update($data);

            $this->auditService->logModelChange(
                'UPDATE_PHASE_REGISTER',
                "Actualización de bitácora técnica",
                ['record_id' => $register->id],
                auth()->id()
            );

            Cache::forget($this->getCacheKeyPrefix() . "_registers_{$parentId}");

            return $register;
        });
    }

    /**
     * ELIMINAR REGISTRO O ACTIVIDAD (SOFT DELETE CON AUTORÍA)
     */
    public function deleteRegister(string $registerId, string $parentId): void
    {
        $registerModel = $this->getRegisterModel();
        // Necesitamos el modelo padre para extraer el requirement_id en la limpieza de caché
        $parentModel = $this->getComponentModel(); 

        DB::transaction(function () use ($registerModel, $parentModel, $registerId, $parentId) {
            $this->validateParentIsNotClosed($parentId);

            $register = $registerModel::findOrFail($registerId);
            $parent = $parentModel::findOrFail($parentId);
            $requirementId = $parent->requirement_id;

            //
            $register->deleted_by = auth()->id();
            $register->save(); 
            $register->delete(); // Ahora el trait de SoftDeletes se ejecutará limpiamente

            $this->auditService->logModelChange(
                'DELETE_PHASE_REGISTER',
                "Eliminación lógica de bitácora",
                ['record_id' => $registerId],
                auth()->id()
            );

            // Limpia ambas cachés
            Cache::forget($this->getCacheKeyPrefix() . "_registers_{$parentId}");
            Cache::forget("req_{$requirementId}_" . $this->getCacheKeyPrefix() . "_roles_meta");
        });
    }

    /**
     * HARD GATE INTERNO
     */
    protected function validateParentIsNotClosed(string $parentId): void
    {
        $parentModel = $this->getComponentModel();
        $parent = $parentModel::findOrFail($parentId);

        if ($parent->status === 'CLOSED') {
            abort(403, 'Operación denegada. El componente se encuentra cerrado y su bitácora es inmutable.');
        }
    }

    abstract protected function countRegistersInRequirement(string $requirementId): int;
}