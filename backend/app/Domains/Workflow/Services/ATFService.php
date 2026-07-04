<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Services;

use App\Domains\Core\Models\Requirement;
use App\Domains\Workflow\Models\AtfAgreement;
use App\Domains\Workflow\Exceptions\PhaseRequirementNotMetException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Cache;

class ATFService
{
    public function __construct(
        private readonly ProgressCalculationService $progressService
    ) {}

    /**
     * RN-Validación: La fase PL debe estar cerrada.
     */
    public function ensureATFPhaseIsAccessible(Requirement $requirement): void
    {
        if ($requirement->status !== 'PL_CLOSED' && !str_starts_with($requirement->status, 'ATF')) {
            throw new PhaseRequirementNotMetException(
                "La fase de Planificación debe estar cerrada formalmente antes de gestionar Acuerdos ATF."
            );
        }
        
        if ($requirement->is_locked) {
            throw new PhaseRequirementNotMetException(
                "El requerimiento se encuentra sellado y es de solo lectura."
            );
        }
    }

    /**
     * Orquesta la creación del acuerdo, la transición de estado y el progreso global.
     */
    public function createAgreement(Requirement $requirement, array $validatedData, string $userId): AtfAgreement
    {
        // 1. Gatekeeper de seguridad
        $this->ensureATFPhaseIsAccessible($requirement);

        // 2. Ejecutar transacción atómica de negocio
        $agreement = DB::transaction(function () use ($requirement, $validatedData, $userId) {
            
            // A. Guardar el Acuerdo
            $newAgreement = AtfAgreement::create([
                'requirement_id'        => $requirement->id,
                'agreement_date'        => $validatedData['agreement_date'],
                'description'           => $validatedData['description'],
                'registered_by_user_id' => $userId,
            ]);

            // B. Transición de Estado a 'ATF-I' (Si es el primer acuerdo)
            $isFirstAgreement = AtfAgreement::where('requirement_id', $requirement->id)->count() === 1;
            
            if ($isFirstAgreement && $requirement->status === 'PL_CLOSED') {
                $requirement->update(['status' => 'ATF-I']);
                
                $requirement->phaseHistories()->create([
                    'phase_status_code'   => 'ATF-I',
                    'transitioned_at'     => now(),
                    'executed_by_user_id' => $userId,
                    'remarks'             => 'Inicio automático de ATF tras primer acuerdo.'
                ]);
            }

            // C. Recálculo Polimórfico Síncrono (CU-008)
            $newProgress = $this->progressService->calculateGlobalProgress($requirement);
            $requirement->update(['progress_percentage' => $newProgress]);

            return $newAgreement;
        });

        // 3. Purgar caché de Redis POST-Transacción (Evita fallos si la BD hace rollback)
        Redis::del("req_{$requirement->id}_progress");
        Cache::tags(['dashboard'])->flush();

        return $agreement;
    }
}