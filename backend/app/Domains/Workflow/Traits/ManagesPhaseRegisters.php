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
            
            // 1. Inserción Atómica del Registro con Autoría
            $data[$foreignKey] = $parentId;
            $data['created_by'] = auth()->id();
            $data['updated_by'] = auth()->id();
            $register = $registerModel::create($data);

            // 2. Trazabilidad Forense
            $this->auditService->logModelChange(
                'CREATE_PHASE_REGISTER',
                "Creación de evidencia técnica en la subfase " . $this->getPhaseInitCode(),
                ['record_id' => $register->id],
                auth()->id()
            );

            // 3. Disparador Transaccional de Estado Global
            $totalRegisters = $this->countRegistersInRequirement($requirementId);

            if ($totalRegisters === 1) {
                $requirement = Requirement::lockForUpdate()->findOrFail($requirementId);
                
                // Si el requerimiento no está en el estado de inicio de la fase (ej. no está en 'COR-I')
                if ($requirement->status !== $this->getPhaseInitCode()) {
                    
                    // Calculamos el avance global (Cálculo de avance ponderado)
                    $calculatedProgress = $this->progressService->calculateGlobalProgress($requirement);
                    
                    // Transición de Estado Maestro
                    $requirement->update([
                        'status' => $this->getPhaseInitCode(),
                        'progress_percentage' => $calculatedProgress,
                        'updated_at' => now()
                    ]);
                    
                    // 🟢 Registro Histórico Transaccional (Trazabilidad asegurada)
                    $this->phaseTransitionService->recordTransition(
                        $requirementId,
                        $this->getPhaseInitCode(),
                        (string) auth()->id(),
                        'Apertura automática de la subfase al crear el primer registro técnico.'
                    );
                    
                    Cache::put("req_{$requirementId}_progress", $calculatedProgress, now()->addDays(1));
                }
            }

            // 4. Invalidación de Caché
            Cache::forget($this->getCacheKeyPrefix() . "_registers_{$parentId}");

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

        DB::transaction(function () use ($registerModel, $registerId, $parentId) {
            $this->validateParentIsNotClosed($parentId);

            $register = $registerModel::findOrFail($registerId);
            
            // Reforzamos el Soft Delete estampando quién eliminó el registro antes de ocultarlo
            $register->update(['deleted_by' => auth()->id()]);
            $register->delete();

            $this->auditService->logModelChange(
                'DELETE_PHASE_REGISTER',
                "Eliminación lógica de bitácora",
                ['record_id' => $registerId],
                auth()->id()
            );

            Cache::forget($this->getCacheKeyPrefix() . "_registers_{$parentId}");
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