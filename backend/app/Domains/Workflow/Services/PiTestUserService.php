<?php

namespace App\Domains\Workflow\Services;

use Illuminate\Support\Facades\DB;
use App\Domains\Workflow\Models\PiTestUser;
use App\Domains\Audit\Services\AuditService;
use Exception;

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
        // 1. RN-PI-21: Validación de Duplicado Interno Estricto
        $existsInSameRole = PiTestUser::where('pi_role_id', $roleId)
            ->where('identifier', $identifier)
            ->exists();

        if ($existsInSameRole) {
            throw new Exception("El usuario {$identifier} ya está registrado en este rol.", 422);
        }

        // 2. Ejecutamos SIEMPRE la búsqueda de concurrencia para la auditoría
        $otherReqs = DB::table('workflow.pi_test_users as ptu')
            ->join('core.requirements as r', 'ptu.requirement_id', '=', 'r.id')
            ->where('ptu.identifier', $identifier)
            ->where('ptu.requirement_id', '!=', $requirementId)
            ->whereNotIn('r.status', ['RC', 'RF']) // Filtramos requerimientos cerrados
            ->whereNull('ptu.deleted_at')
            ->pluck('r.rrti')
            ->unique()
            ->toArray();

        // 3. RN-PI-22: Validación Informativa Externa (Two-Step Submission)
        if (!empty($otherReqs) && !$force) {
            // Lanzamos una excepción especial 409 Conflict si no viene forzado
            throw new \Illuminate\Http\Exceptions\HttpResponseException(response()->json([
                'requires_confirmation' => true,
                'reqs' => array_values($otherReqs),
                'message' => "El usuario se encuentra activo en otros requerimientos."
            ], 409));
        }

        // 4. RN-PI-26: Persistencia Atómica con Auditoría Dinámica
        $testUser = DB::transaction(function () use ($roleId, $requirementId, $identifier, $userId, $force, $otherReqs) {
            $newUser = PiTestUser::create([
                'pi_role_id' => $roleId,
                'requirement_id' => $requirementId,
                'identifier' => strtoupper($identifier)
            ]);

            // AUDITORÍA FORENSE DIFERENCIADA
            if ($force && !empty($otherReqs)) {
                // El usuario aceptó el SweetAlert y forzó la creación
                $reqsString = implode(', ', $otherReqs);
                
                $this->auditService->logModelChange(
                    action: 'SAVE_TEST_USER_OVERRIDE',
                    description: "Se forzó la asignación del usuario {$newUser->identifier} (Advertencia ignorada. Activo también en: {$reqsString})",
                    payload: [
                        'pi_role_id' => $roleId, 
                        'identifier' => $newUser->identifier,
                        'overridden_requirements' => $otherReqs // Guardamos el array exacto en el JSONB
                    ],
                    userId: $userId,
                    targetId: $newUser->id
                );
            } else {
                // Inserción regular sin conflictos
                $this->auditService->logModelChange(
                    action: 'SAVE_TEST_USER',
                    description: "Se agregó el usuario de prueba {$newUser->identifier}",
                    payload: ['pi_role_id' => $roleId, 'identifier' => $newUser->identifier],
                    userId: $userId,
                    targetId: $newUser->id
                );
            }

            return $newUser;
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