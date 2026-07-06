<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Services;

use App\Domains\Workflow\Models\RequirementRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class RequirementRoleService
{
    /**
     * FASE 2: Escritura y Unicidad (CU-019)
     */
    public function createRole(string $requirementId, array $validatedData, string $userId): RequirementRole
    {
        return DB::transaction(function () use ($requirementId, $validatedData, $userId) {
            // 1. Inserción (Asociación invisible ID)
            $role = RequirementRole::create(array_merge(
                $validatedData,
                ['requirement_id' => $requirementId]
            ));

            // 2. Registro de Auditoría
            Log::channel('audit')->info('Acción: CREATE_COMP', [
                'Action' => 'CREATE_COMP',
                'User' => $userId,
                'Payload' => $validatedData
            ]);

            // 3. Saneamiento de caché local de componentes
            Redis::del("atf_components_{$requirementId}");

            return $role;
        });
    }

    /**
     * FASE 4: Actualizar Registro (CU-021)
     */
    public function updateRole(RequirementRole $role, array $validatedData, string $userId): RequirementRole
    {
        return DB::transaction(function () use ($role, $validatedData, $userId) {
            // Calculamos el delta para la auditoría (qué cambió realmente)
            $deltas = array_diff_assoc($validatedData, $role->toArray());

            // 1. Actualización
            $role->update($validatedData);

            // 2. Registro de Auditoría con Deltas (JSONB conceptual)
            if (!empty($deltas)) {
                Log::channel('audit')->info('Acción: UPDATE_COMP', [
                    'Action' => 'UPDATE_COMP',
                    'User' => $userId,
                    'Deltas' => $deltas
                ]);
            }

            // 3. Saneamiento de caché local
            Redis::del("atf_components_{$role->requirement_id}");

            return $role;
        });
    }
}