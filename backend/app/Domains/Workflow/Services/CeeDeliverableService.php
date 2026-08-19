<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Services;

use App\Domains\Workflow\Services\Base\AbstractPhaseComponentService;
use App\Domains\Workflow\Traits\ManagesCertificationTickets;
use App\Domains\Workflow\Models\CeeDeliverable;
use App\Domains\Workflow\Models\CeeTicket;
use Illuminate\Support\Facades\DB;
use Exception;

class CeeDeliverableService extends AbstractPhaseComponentService
{
    use ManagesCertificationTickets;

    protected function getComponentModel(): string { return CeeDeliverable::class; }
    protected function getTicketModel(): string { return CeeTicket::class; }
    protected function getPhaseCode(): string { return 'CEE-C'; }
    protected function getPhaseInitCode(): string { return 'CEE-I'; }
    protected function getTicketPrefix(): string { return 'CEE'; }
    protected function getCacheKeyPrefix(): string { return 'cee_deliverables'; }
    
    // 🟢 RN-CEE: El sistema autogenera el número de ticket internamente
    protected function generatesTicketNumberAutomatically(): bool { return true; }
    protected function getCompletedStatusCode(): string { return 'CERTIFIED'; }
    protected function getRequiredPredecessorPhases(): array { return ['COE-C']; }

    /**
     * CU-077: Promoción Automática e Idempotente de Entregables desde COE[cite: 5]
     */
    public function initializeDeliverables(string $requirementId): void
    {
        // RN-CEE-2: Protección y Candado Lógico[cite: 5]
        $closedInCoe = DB::table('workflow.coe_deliverables')
            ->where('req_id', $requirementId)
            ->where('status', 'CLOSED')
            ->count();

        if ($closedInCoe === 0) {
            throw new Exception("Acceso denegado: El requerimiento no posee entregables cerrados en Construcción.", 403);
        }

        // RN-CEE-3 y RN-CEE-6: Sincronización Incremental (INSERT INTO ... SELECT)[cite: 5]
        $sql = "
            INSERT INTO workflow.cee_deliverables (id, requirement_id, deliverable_id, status, created_at, updated_at)
            SELECT 
                gen_random_uuid(), 
                req_id, 
                deliverable_id, 
                'PENDING_CERTIFICATION', 
                NOW(), 
                NOW()
            FROM workflow.coe_deliverables
            WHERE req_id = ? AND status = 'CLOSED'
            ON CONFLICT (requirement_id, deliverable_id) DO NOTHING
        ";

        DB::statement($sql, [$requirementId]);
    }
}