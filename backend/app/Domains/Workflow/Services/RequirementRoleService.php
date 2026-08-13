<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Services;

use App\Domains\Workflow\Models\RequirementRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Domains\Audit\Services\AuditService;



class RequirementRoleService
{

public function __construct(
        private readonly AuditService $auditService
    ) {}
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
      $this->auditService->logModelChange(
          'CREATE_ROLE',
          'Creación de rol técnico: ' . ($validatedData['role_name'] ?? 'N/A'),
          $validatedData, // Payload
          $userId,
          $role->id       // Target ID
      );

      // 3. Saneamiento de caché local de componentes
      $cacheKey = $this->getCacheKey($requirementId);
      Cache::forget($cacheKey);

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
            $this->auditService->logModelChange(
            'UPDATE_ROLE',
            "Actualización del rol: {$role->id}",
            $deltas,        // Solo se envia los cambios, no todo el objeto
            $userId,
            $role->id       // target ID
        );
      }

      // 3. Saneamiento de caché local
      $cacheKey = $this->getCacheKey($role->requirement_id);
      Cache::forget($cacheKey);

      return $role;
    });
  }
  /**
 * Elimina un rol aplicando soft delete, auditoría forense y limpieza de caché.
 * 
 *
  @param string $id
 * @return bool
 * @throws \Exception
 */

public function deleteRole(string $id): bool
{
    return DB::connection('pgsql')->transaction(function () use ($id) {
        $role = RequirementRole::findOrFail($id);
        $roleData = $role->toArray();
        $requirementId = $role->requirement_id;

        $deleted = $role->delete();

        if ($deleted) {
            // Auditoría forense
            $this->auditService->logModelChange(
                'DELETE ROL', 
                'Eliminación del rol: ' . ($roleData['role_name'] ?? 'N/A'), 
                [
                    'record_id'      => $id,
                    'user_id'        => request()->user()?->id,
                    'old_values'     => $roleData,
                    'requirement_id' => $requirementId
                ]
            );

            
            $cacheKey = $this->getCacheKey($requirementId);
            Cache::forget($cacheKey);
        }

        return $deleted;
    });
}


protected function getCacheKey(string $requirementId): string 
  {
      return "roles_req_{$requirementId}";
  }

  public function getRolesByRequirement(string $requirementId)
  {
    $cacheKey = $this->getCacheKey($requirementId);

    // Usamos Cache::remember estándar (simplificado sin tags para evitar conflictos)
    return Cache::remember($cacheKey, 3600, function () use ($requirementId) {
      
      return RequirementRole::where('requirement_id', $requirementId)->get()->toArray();
    });
  }
}


