<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Services;

use App\Domains\Workflow\Models\Deliverable;
use App\Domains\Audit\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;


class DeliverableService
{
    public function __construct(
        private readonly AuditService $auditService
    ) {}

    /**
     * Obtiene entregables con caché (CU-022)
     */
    

    public function getDeliverablesByRequirement(string $requirementId): array
    {
        $cacheKey = $this->getCacheKey($requirementId);

        // Utilizamos ->toArray() para asegurar que guardamos un array plano y no un objeto Eloquent
        return Cache::remember($cacheKey, 3600, function () use ($requirementId) {
            return Deliverable::where('requirement_id', $requirementId)->get()->toArray();
        });
    }

    /**
     * Registra un nuevo entregable con auditoría y limpieza de caché (CU-023)
     */
    public function createDeliverable(string $requirementId, array $validatedData, string $userId): Deliverable
    {
        return DB::transaction(function () use ($requirementId, $validatedData, $userId) {
            $deliverable = Deliverable::create(array_merge(
                $validatedData,
                ['requirement_id' => $requirementId]
            ));

            $this->auditService->logModelChange(
                'CREATE_DELIVERABLE',
                'Creación de entregable: ' . ($validatedData['name'] ?? 'N/A'),
                $validatedData,
                $userId,
                $deliverable->id
            );

            $this->invalidateCache($requirementId);

            return $deliverable;
        });
    }

    /**
     * Actualiza un entregable con auditoría (CU-025)
     */
    public function updateDeliverable(Deliverable $deliverable, array $validatedData, string $userId): Deliverable
    {
        return DB::transaction(function () use ($deliverable, $validatedData, $userId) {
            $oldValues = $deliverable->toArray();
            $deliverable->update($validatedData);
            
            $this->auditService->logModelChange(
                'UPDATE_DELIVERABLE',
                "Actualización del entregable: {$deliverable->id}",
                ['old' => $oldValues, 'new' => $validatedData],
                $userId,
                $deliverable->id
            );

            $this->invalidateCache($deliverable->requirement_id);

            return $deliverable;
        });
    }

    public function deleteDeliverable(string $id): bool
    {
        return DB::connection('pgsql')->transaction(function () use ($id) {
            $deliverable = Deliverable::findOrFail($id);
            $data = $deliverable->toArray();
            $requirementId = $deliverable->requirement_id;

            $deleted = $deliverable->delete(); // Soft delete automático

            if ($deleted) {
                $this->auditService->logModelChange(
                    'DELETE DELIVERABLE', 
                    'Eliminación del entregable: ' . $data['name'], 
                    [
                        'record_id'      => $id,
                        'user_id'        => request()->user()?->id,
                        'old_values'     => $data,
                        'requirement_id' => $requirementId
                    ]
                );
                // Limpieza de caché específica del requerimiento
                $cacheKey = $this->getCacheKey($requirementId);
                Cache::forget($cacheKey);
            }
            return $deleted;
        });
    }

    protected function getCacheKey(string $requirementId): string 
    {
        return "deliverables_req_{$requirementId}";
    }

    protected function invalidateCache(string $requirementId): void
    {
        Cache::forget($this->getCacheKey($requirementId));
    }
}