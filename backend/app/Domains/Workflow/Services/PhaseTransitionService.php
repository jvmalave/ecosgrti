<?php

namespace App\Domains\Workflow\Services;

use App\Domains\Workflow\Models\RequirementPhaseHistory;
use Illuminate\Support\Str;

class PhaseTransitionService 
{
    /**
     * Registra un cambio de fase inmutable.
     * @param string $requirementId UUID del requerimiento.
     * @param string $statusCode Código de la fase (ej: 'ATF-C').
     * @param string $userId UUID del consultor responsable.
     */
    public function recordTransition(string $requirementId, string $statusCode, string $userId, ?string $remarks = null): void
{
    RequirementPhaseHistory::create([
        'id' => Str::uuid()->toString(),
        'requirement_id' => $requirementId,
        'phase_status_code' => $statusCode,
        'transitioned_at' => now(),
        'executed_by_user_id' => $userId,
        'remarks' => $remarks // Asegúrate de que este campo exista en tu migración
    ]);
}
}