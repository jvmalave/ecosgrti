<?php

namespace App\Domains\Catalogs\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class MilestoneService
{
    // OBTIENE LA LISTA DE HITOS TÉCNICOS FILTRADOS OPCIONALMENTE POR TIPO DE GESTIÓN
    public function getAllMilestones(?string $managementType): Collection
    {
        $query = DB::table('catalogs.milestones');

        if ($managementType) {
            $query->where('management_type', $managementType);
        }

        return $query->orderBy('phase_code', 'asc')->get();
    }

    // REGISTRA UN NUEVO HITO TÉCNICO EN EL CATÁLOGO MAESTRO
    public function createMilestone(array $data): string
    {
        $id = (string) Str::uuid();

        DB::table('catalogs.milestones')->insert(array_merge($data, [
            'id' => $id,
            'created_at' => now(),
            'updated_at' => now(),
        ]));

        return $id;
    }

    // ACTUALIZA UN HITO TÉCNICO EN EL CATÁLOGO MAESTRO
    public function updateMilestone(string $id, array $data): bool
    {
        $updated = DB::table('catalogs.milestones')
            ->where('id', $id)
            ->update(array_merge($data, [
                'updated_at' => now(),
            ]));

        return $updated > 0;
    }

    // ELIMINA UN HITO TÉCNICO EN EL CATÁLOGO MAESTRO
    public function deleteMilestone(string $id): bool
    {
        // RN-Integridad: Prevenir borrado si el hito está vinculado a matrices de progreso
        $isReferenced = DB::table('catalogs.progress_matrix_milestones')
            ->where('milestone_id', $id)
            ->exists();

        if ($isReferenced) {
            throw new InvalidArgumentException(
                'Infracción de Integridad: No es posible eliminar el hito técnico porque se encuentra vinculado a una matriz de progreso operativa.'
            );
        }

        $deleted = DB::table('catalogs.milestones')->where('id', $id)->delete();

        return $deleted > 0;
    }
}