<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Traits;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis; 
use Illuminate\Database\Eloquent\Model;
use App\Domains\Core\Models\Requirement;
use App\Domains\Core\Dictionaries\CacheKeyDictionary;

trait ManagesPhaseRegisters
{
    abstract protected function getRegisterModel(): string;
    abstract protected function getParentForeignKey(): string;
    abstract protected function getRequirementColumn(): string; 
    abstract protected function getCacheKeyPrefix(): string;
    abstract protected function getComponentModel(): string;
    abstract protected function countRegistersInRequirement(string $requirementId): int;
    abstract protected function getPhaseInitCode(): string;
    
    public function storeRegister(string $parentId, array $data, string $requirementId): Model
    {
        $registerModel = $this->getRegisterModel();
        $foreignKey = $this->getParentForeignKey();

        return DB::transaction(function () use ($registerModel, $foreignKey, $parentId, $data, $requirementId) {
            
            $data[$foreignKey] = $parentId;
            $data['created_by'] = auth()->id();
            $data['updated_by'] = auth()->id();
            $register = $registerModel::create($data);

            $this->auditService->logModelChange(
                'CREATE_PHASE_REGISTER',
                "Creación de evidencia técnica en la subfase " . $this->getPhaseInitCode(),
                ['record_id' => $register->id],
                auth()->id()
            );

            $totalRegisters = $this->countRegistersInRequirement($requirementId);

            if ($totalRegisters === 1) {
                $requirement = Requirement::lockForUpdate()->findOrFail($requirementId);
                
                $hasInitHistory = DB::table('workflow.requirement_phase_history')
                    ->where('requirement_id', $requirementId)
                    ->where('phase_status_code', $this->getPhaseInitCode())
                    ->exists();
                
                if (!$hasInitHistory) {
                    $this->phaseTransitionService->recordTransition(
                        $requirementId,
                        $this->getPhaseInitCode(),
                        (string) auth()->id(),
                        'Apertura automática de la subfase al crear el primer registro técnico.'
                    );
                    
                    $calculatedProgress = $this->progressService->calculateGlobalProgress($requirement);
                    $isVanguard = $this->phaseTransitionService->isVanguardStatus($this->getPhaseInitCode(), $requirement->status);

                    $updateData = [
                        'progress_percentage' => $calculatedProgress,
                        'updated_at' => now()
                    ];

                    if ($isVanguard) {
                        $updateData['status'] = $this->getPhaseInitCode();
                        $requirement->status = $this->getPhaseInitCode(); 
                    }

                    $requirement->update($updateData);

                    $this->auditService->logModelChange(
                        'INITIATE_GLOBAL_PHASE',
                        "Apertura global de la fase " . $this->getPhaseInitCode() . ($isVanguard ? " (Nueva Vanguardia)" : " (Proceso Paralelo)"),
                        [
                            'requirement_id' => $requirementId,
                            'new_status' => $requirement->status, 
                            'progress_reached' => $calculatedProgress,
                            'is_vanguard_update' => $isVanguard
                        ],
                        (string) auth()->id(),
                        $requirementId
                    );
                    
                    Cache::put(CacheKeyDictionary::requirementProgress($requirementId), $calculatedProgress, now()->addDays(1));
                    Redis::incr(CacheKeyDictionary::globalDashboardVersion());
                }
            }

            // DOBLE INVALIDACIÓN Y REACTIVIDAD
            $regKey = CacheKeyDictionary::componentRegisters($parentId, $this->getCacheKeyPrefix());
            $listKey = CacheKeyDictionary::phaseComponentsList($requirementId, $this->getCacheKeyPrefix());
            
            Cache::forget($regKey); Redis::del($regKey);
            Cache::forget($listKey); Redis::del($listKey);

            // Incrementamos versión para que la UI se entere del nuevo registro
            Redis::incr(CacheKeyDictionary::globalDashboardVersion());

            return $register;
        });
    }

    public function updateRegister(string $registerId, array $data, string $parentId): Model
    {
        $registerModel = $this->getRegisterModel();

        return DB::transaction(function () use ($registerModel, $registerId, $data, $parentId) {
            $this->validateParentIsNotClosed($parentId);

            $register = $registerModel::findOrFail($registerId);
            $data['updated_by'] = auth()->id();
            $register->update($data);

            $this->auditService->logModelChange(
                'UPDATE_PHASE_REGISTER',
                "Actualización de bitácora técnica",
                ['record_id' => $register->id],
                auth()->id()
            );

            // 🟢 DOBLE INVALIDACIÓN
            $regKey = CacheKeyDictionary::componentRegisters($parentId, $this->getCacheKeyPrefix());
            Cache::forget($regKey); Redis::del($regKey);
            
            Redis::incr(CacheKeyDictionary::globalDashboardVersion());

            return $register;
        });
    }

    public function deleteRegister(string $registerId, string $parentId): void
    {
        $registerModel = $this->getRegisterModel();
        $parentModel = $this->getComponentModel(); 

        DB::transaction(function () use ($registerModel, $parentModel, $registerId, $parentId) {
            $this->validateParentIsNotClosed($parentId);

            $register = $registerModel::findOrFail($registerId);
            $parent = $parentModel::findOrFail($parentId);
            
            $reqColumn = $this->getRequirementColumn();
            $requirementId = (string) $parent->{$reqColumn};

            $register->deleted_by = auth()->id();
            $register->save(); 
            $register->delete(); 

            $this->auditService->logModelChange(
                'DELETE_PHASE_REGISTER',
                "Eliminación lógica de bitácora",
                ['record_id' => $registerId],
                auth()->id()
            );

            // 🟢 DOBLE INVALIDACIÓN
            $regKey = CacheKeyDictionary::componentRegisters($parentId, $this->getCacheKeyPrefix());
            $listKey = CacheKeyDictionary::phaseComponentsList($requirementId, $this->getCacheKeyPrefix());
            
            Cache::forget($regKey); Redis::del($regKey);
            Cache::forget($listKey); Redis::del($listKey);

            Redis::incr(CacheKeyDictionary::globalDashboardVersion());
        });
    }

    protected function validateParentIsNotClosed(string $parentId): void
    {
        $parentModel = $this->getComponentModel();
        $parent = $parentModel::findOrFail($parentId);

        if ($parent->status === 'CLOSED') {
            abort(403, 'Operación denegada. El componente se encuentra cerrado y su bitácora es inmutable.');
        }
    }
}