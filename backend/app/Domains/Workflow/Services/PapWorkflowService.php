<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Services;

use App\Domains\Workflow\Services\Base\AbstractPhaseComponentService;
use App\Domains\Workflow\Traits\ManagesCertificationTickets;
use App\Domains\Workflow\Models\PapOrder;
use App\Domains\Workflow\Models\PapRole;

class PapWorkflowService extends AbstractPhaseComponentService
{
    // Inyecta el súper motor polimórfico ManagesCertificationTickets
    use ManagesCertificationTickets;

    // ==========================================
    // MÉTODOS ABSTRACTOS DE LA CLASE BASE
    // ==========================================
    protected function getComponentModel(): string { return PapRole::class; }
    protected function getCacheKeyPrefix(): string { return 'pap_roles'; }
    protected function getPhaseCode(): string { return 'PAP-C'; }
    protected function getPhaseInitCode(): string { return 'PAP-I'; }
    protected function getRequiredPredecessorPhases(): array { return ['CER-C']; } // Hard Gate

    // ==========================================
    // MÉTODOS ABSTRACTOS DEL TRAIT
    // ==========================================
    protected function getTicketModel(): string { return PapOrder::class; }
    protected function getTicketPrefix(): string { return 'PAP'; }
    protected function generatesTicketNumberAutomatically(): bool { return false; }

    // ==========================================
    //  SOBRESCRITURA POLIMÓRFICA PARA PAP 
    // ==========================================
    protected function getTicketIdentifierColumn(): string { return 'order_number'; }
    protected function getTicketForeignKey(): string { return 'order_id'; }
    protected function getDateColumn(): string { return 'date'; }
    protected function getTicketInProgressStatus(): string { return 'ORD_IN_PROGRESS'; }
    protected function getTicketClosedStatus(): string { return 'ORD_CLOSED'; }
    protected function getComponentPendingStatus(): string { return 'PENDING_PAP'; }
    protected function getComponentCertifiedStatus(): string { return 'IN_PRODUCTION'; }
    protected function getCompletedStatusCode(): string { return 'IN_PRODUCTION'; }
    protected function getRejectionReasonColumn(): string { return 'fail_reason'; }
    protected function getAuditActionCreate(): string { return 'CREATE_TRANSPORT_ORDER'; }
    protected function getAuditActionUpdate(): string { return 'UPDATE_TRANSPORT_ORDER'; }
    protected function getAuditActionRegisterResult(): string { return 'REGISTER_PAP_RESULT'; }

}