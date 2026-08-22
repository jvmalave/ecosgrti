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
      'remarks' => $remarks ?? '',
    ]);
  }

  /**
     * Define la jerarquía numérica del ciclo de vida para determinar la "Vanguardia".
     */
    public function getPhaseWeight(string $statusCode): int
    {
        $weights = [
            'RC'    => 10,
            'ES-R'  => 20,
            'ATF-I' => 30,
            'ATF-C' => 40,
            
            // FASE: Diseño / Construcción 
            'DT-I'  => 50,
            'DT-C'  => 55,
            'COE-I' => 60, // Entregable
            'COE-C' => 65, // Entregable
            'COR-I' => 70, // Roles
            'COR-C' => 75, // Roles
            
            // FASE: Pruebas / Certificación
            'PI-I'  => 80,
            'PI-C'  => 85,
            'CEE-I' => 90, // Entregable
            'CEE-C' => 95, // Entregable
            'CER-I' => 100, // Roles
            'CER-C' => 105, // Roles
            
            // FASE: Pase a Producción y Asignación de Usuario
            'PAP-I' => 110,
            'PAP-C' => 115,
            'AU-I'  => 120,
            'AU-C'  => 125,
            
            // Fin de Requerimiento (Cierre absoluto)
            'FR'    => 999, 
        ];

        return $weights[$statusCode] ?? 0;
    }

    /**
     * Evalúa si el nuevo estado representa un avance en el ciclo de vida
     * comparado con el estado actual del requerimiento.
     */
    public function isVanguardStatus(string $newStatus, ?string $currentStatus): bool
    {
        if (!$currentStatus) {
            return true;
        }
        
        return $this->getPhaseWeight($newStatus) > $this->getPhaseWeight($currentStatus);
    }
}
