<?php

namespace App\Domains\Workflow\Services;

use Illuminate\Support\Facades\DB;
use App\Domains\Workflow\Models\PiTestUser;
use App\Domains\Audit\Services\AuditService;
use Exception;
use App\Domains\Core\Dictionaries\CacheKeyDictionary;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class PiTestUserService
{
    public function __construct(
        private readonly AuditService $auditService
    ) {}

    public function getTestUsersByRole(string $roleId): array
    {
        return PiTestUser::where('pi_role_id', $roleId)
            ->orderBy('created_at', 'desc')
            ->get()
            ->toArray();
    }

  public function storeTestUser(string $roleId, string $requirementId, string $identifier, bool $force, string $userId): array
    {
        $normalizedIdentifier = strtoupper($identifier);

        // 1. Buscamos el usuario incluyendo los eliminados lógicamente
        $existingUser = PiTestUser::withTrashed()
            ->where('pi_role_id', $roleId)
            ->where('identifier', $normalizedIdentifier)
            ->first();

        // Verificamos si existe y está activo antes de hacer nada
        if ($existingUser && !$existingUser->trashed()) {
            throw new \Exception("El usuario {$identifier} ya está registrado en este rol.", 422);
        }

        // 2. Búsqueda de concurrencia para la auditoría (Two-Step Submission)
        $otherReqs = DB::table('workflow.pi_test_users as ptu')
            ->join('core.requirements as r', 'ptu.requirement_id', '=', 'r.id')
            ->where('ptu.identifier', $normalizedIdentifier)
            ->where('ptu.requirement_id', '!=', $requirementId)
            ->whereNotIn('r.status', ['RC', 'RF'])
            ->whereNull('ptu.deleted_at')
            ->pluck('r.rrti')
            ->unique()
            ->toArray();

        // 3. RN-PI-22: Validación Informativa Externa
        if (!empty($otherReqs) && !$force) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(response()->json([
                'requires_confirmation' => true,
                'reqs' => array_values($otherReqs),
                'message' => "El usuario se encuentra activo en otros requerimientos."
            ], 409));
        }

        // 4. Persistencia Atómica
        $testUser = DB::transaction(function () use ($roleId, $requirementId, $normalizedIdentifier, $userId, $force, $otherReqs, $existingUser) {
            
            // 🟢 AQUÍ ESTABA EL ERROR: Ahora la restauración ocurre DENTRO de la transacción
            if ($existingUser) {
                $existingUser->restore(); // Lo revivimos de la papelera
                $existingUser->updated_by = $userId;
                $existingUser->save();
                $testUser = $existingUser;
            } else {
                // Si nunca ha existido, lo creamos
                $testUser = PiTestUser::create([
                    'pi_role_id' => $roleId,
                    'requirement_id' => $requirementId,
                    'identifier' => $normalizedIdentifier,
                    'created_by' => $userId,
                    'updated_by' => $userId
                ]);
            }

            // Auditoría forense
            if ($force && !empty($otherReqs)) {
                $reqsString = implode(', ', $otherReqs);
                $this->auditService->logModelChange(
                    action: 'SAVE_TEST_USER_OVERRIDE',
                    description: "Se forzó la asignación del usuario {$testUser->identifier} (Activo también en: {$reqsString})",
                    payload: ['pi_role_id' => $roleId, 'identifier' => $testUser->identifier, 'overridden_requirements' => $otherReqs],
                    userId: $userId,
                    targetId: $testUser->id
                );
            } else {
                $this->auditService->logModelChange(
                    action: 'SAVE_TEST_USER',
                    description: "Se agregó el usuario de prueba {$testUser->identifier}",
                    payload: ['pi_role_id' => $roleId, 'identifier' => $testUser->identifier],
                    userId: $userId,
                    targetId: $testUser->id
                );
            }

            // =====================================================================
            // 🟢 DOBLE INVALIDACIÓN Y REACTIVIDAD
            // (Asegúrate de que 'PI-I' coincida con lo que retorna getPhaseInitCode() en tu PiRoleService)
            // =====================================================================
            $listKey = CacheKeyDictionary::phaseComponentsList($requirementId, 'PI-I'); 
            Cache::forget($listKey); 
            Redis::del($listKey);
            Redis::incr(CacheKeyDictionary::globalDashboardVersion());

            return $testUser;
        });

        return $testUser->toArray();
    }

    public function deleteTestUser(string $userId, string $authUserId): void
    {
        $testUser = PiTestUser::findOrFail($userId);

        // RN-PI-25: Integridad Referencial
        $hasResults = DB::table('workflow.pi_test_results')
            ->where('test_user_id', $userId)
            ->exists();

        if ($hasResults) {
            throw new Exception("El usuario posee pruebas ejecutadas. No puede ser eliminado.", 422);
        }

        DB::transaction(function () use ($testUser, $authUserId) {
            $testUser->update(['deleted_by' => $authUserId]); 
            $testUser->delete();

            $this->auditService->logModelChange(
                action: 'DELETE_TEST_USER',
                description: "Se eliminó el usuario de prueba {$testUser->identifier}",
                payload: ['pi_role_id' => $testUser->pi_role_id],
                userId: $authUserId,
                targetId: $testUser->id
            );
        });
    }
}