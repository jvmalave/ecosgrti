<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Services;

use App\Domains\Workflow\Models\Deliverable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class DeliverableService
{
    /**
     * FASE 2: Escritura y Unicidad (CU-023)
     */
    public function createDeliverable(string $requirementId, array $validatedData, string $userId): Deliverable
    {
        return DB::transaction(function () use ($requirementId, $validatedData, $userId) {
            // 1. Inserción Atómica
            $deliverable = Deliverable::create(array_merge(
                $validatedData,
                ['requirement_id' => $requirementId]
            ));

            // 2. Registro de Auditoría
            Log::channel('audit')->info('Acción: CREATE_COMP', [
                'Action'  => 'CREATE_COMP',
                'User'    => $userId,
                'Payload' => $validatedData
            ]);

            // 3. Saneamiento de caché local de componentes
            Redis::del("atf_components_{$requirementId}");

            return $deliverable;
        });
    }

    /**
     * FASE 4: Actualizar Registro (CU-025)
     */
    public function updateDeliverable(Deliverable $deliverable, array $validatedData, string $userId): Deliverable
    {
        return DB::transaction(function () use ($deliverable, $validatedData, $userId) {
            // Calculamos el delta para la auditoría
            $deltas = array_diff_assoc($validatedData, $deliverable->toArray());

            // 1. Actualización Atómica
            $deliverable->update($validatedData);

            // 2. Registro de Auditoría con Deltas (JSONB conceptual)
            if (!empty($deltas)) {
                Log::channel('audit')->info('Acción: UPDATE_COMP', [
                    'Action' => 'UPDATE_COMP',
                    'User'   => $userId,
                    'Deltas' => $deltas
                ]);
            }

            // 3. Saneamiento de caché local
            Redis::del("atf_components_{$deliverable->requirement_id}");

            return $deliverable;
        });
    }
}