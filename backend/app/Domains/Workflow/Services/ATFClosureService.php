<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Services;

use App\Domains\Core\Models\Requirement;
use App\Domains\Workflow\Models\AtfAgreement;
use App\Domains\Workflow\Models\RequirementRole;
use App\Domains\Workflow\Models\Deliverable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Validation\ValidationException;


class ATFClosureService
{
    /**
     * T28.1: FASE 1 - Verificación de Quórum
     */
    public function checkClosureReadiness(string $requirementId): array
    {
        $reasons = [];

        $agreementsCount = AtfAgreement::where('requirement_id', $requirementId)->count();
        $rolesCount = RequirementRole::where('requirement_id', $requirementId)->count();
        $deliverablesCount = Deliverable::where('requirement_id', $requirementId)->count();

        if ($agreementsCount < 1) {
            $reasons[] = 'Se requiere al menos un (1) Acuerdo firmado.';
        }

        // Regla: Si gestión mixta, ambos. Si es Roles/Entregables, al menos uno del tipo.
        // Aquí asumimos validación general según tu diagrama.
        if ($rolesCount < 1 && $deliverablesCount < 1) {
            $reasons[] = 'Se requiere definir al menos un (1) componente técnico (Rol o Entregable).';
        }

        return [
            'ready'   => empty($reasons),
            'reasons' => $reasons,
        ];
    }

    /**
     * T28.2: FASE 2 - Cierre Atómico
     */
    public function executeClosure(string $requirementId, string $userId): Requirement
    {
        // 1. Gatekeeper: Validar quórum
        $readiness = $this->checkClosureReadiness($requirementId);
        if (!$readiness['ready']) {
            throw ValidationException::withMessages(['quorum' => $readiness['reasons']]);
        }

        return DB::transaction(function () use ($requirementId, $userId) {
            $requirement = Requirement::lockForUpdate()->findOrFail($requirementId);

            // 2. Transacción de Cierre
            $requirement->update([
                'phase_actual' => 'ATF_COMPLETED',
                'status_atf'   => 'CLOSED',
                'is_locked'    => true, 
            ]);

            // 3. Auditoría y Caché
            DB::table('audit.workflow_logs')->insert([
                'action' => 'ATF_PHASE_CLOSE', 'user_id' => $userId, 
                'requirement_id' => $requirementId, 'timestamp' => now()
            ]);

            Redis::del("atf_cache_{$requirementId}", "atf_components_{$requirementId}");

            return $requirement;
        });
    }
}