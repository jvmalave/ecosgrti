<?php

namespace App\Domains\Workflow\Services;

use Illuminate\Support\Facades\DB;
use App\Domains\Workflow\Models\PapRole;
use App\Domains\Workflow\Models\CerRole;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class PapRoleService
{
    /**
     * Valida y promueve los roles certificados a la fase PAP
     */
    public function initializePapRoles(string $requirementId)
    {
        // RN-PAP-2: Protección Middleware (Zero Trust)
        $hasCertifiedRoles = CerRole::where('requirement_id', $requirementId)
            ->where('status', 'CERTIFIED')
            ->exists();

        if (!$hasCertifiedRoles) {
            throw new AccessDeniedHttpException('Acceso denegado: El requerimiento no posee roles certificados para pasar a producción.');
        }

        // RN-PAP-3 y RN-PAP-7: Promoción Automática e Idempotente
        DB::transaction(function () use ($requirementId) {
            $certifiedRoles = CerRole::where('requirement_id', $requirementId)
                ->where('status', 'CERTIFIED')
                ->get();

            foreach ($certifiedRoles as $cerRole) {
                PapRole::firstOrCreate(
                    [
                        'requirement_id' => $requirementId,
                        'requirement_role_id' => $cerRole->requirement_role_id,
                    ],
                    [
                        'status' => 'PENDING_PAP'
                    ]
                );
            }
        });

        // RN-PAP-6: Aislamiento por Sesión e Integridad
        return PapRole::with(['requirementRole', 'order'])
            ->where('requirement_id', $requirementId)
            ->get();
    }
}