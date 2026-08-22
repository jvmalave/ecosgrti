<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Services;

use App\Domains\Workflow\Services\Base\AbstractPhaseComponentService;
use App\Domains\Workflow\Traits\ManagesCertificationTickets;
use App\Domains\Workflow\Models\CerRole;
use App\Domains\Workflow\Models\CerTicket;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;


use Exception;

class CerRoleService extends AbstractPhaseComponentService
{
    use ManagesCertificationTickets;

    protected function getComponentModel(): string { return CerRole::class; }
    protected function getTicketModel(): string { return CerTicket::class; }
    protected function getPhaseCode(): string { return 'CER-C'; }
    protected function getPhaseInitCode(): string { return 'CER-I'; }
    protected function getTicketPrefix(): string { return 'CER'; }
    protected function getCacheKeyPrefix(): string { return 'cer_roles'; }
    
    // El número de ticket es provisto manualmente por el ente externo certificante
    protected function generatesTicketNumberAutomatically(): bool { return false; }
    protected function getCompletedStatusCode(): string { return 'CERTIFIED'; }
    protected function getRequiredPredecessorPhases(): array { return ['PI-C']; }

    /**
     * CU-047: Promoción Automática e Idempotente de Roles desde PI
     */
    public function initializeRoles(string $requirementId): void
    {
        // Protección y Candado Lógico
        $closedInPi = DB::table('workflow.pi_roles')
            ->where('requirement_id', $requirementId)
            ->where('status', 'CLOSED')
            ->count();

        if ($closedInPi === 0) {
            throw new Exception("Acceso denegado: El requerimiento no posee roles cerrados en Pruebas Integrales.", 403);
        }

        // Sincronización Incremental (INSERT INTO ... SELECT)
        $sql = "
            INSERT INTO workflow.cer_roles (id, requirement_id, requirement_role_id, status, created_at, updated_at)
            SELECT 
                gen_random_uuid(), 
                requirement_id, 
                requirement_role_id, 
                'PENDING_CERTIFICATION', 
                NOW(), 
                NOW()
            FROM workflow.pi_roles
            WHERE requirement_id = ? AND status = 'CLOSED'
            ON CONFLICT (requirement_id, requirement_role_id) DO NOTHING
        ";

        DB::statement($sql, [$requirementId]);
    }


    /**
     * Obtiene el archivo del dictamen (CER)
     */
    public function getTicketFile(string $ticketId)
    {
        
        $ticket = CerTicket::findOrFail($ticketId);

        $path = $ticket->file_path; 

        if (!$path || !Storage::disk('local')->exists($path)) {
            abort(404, 'El archivo del dictamen no se encuentra disponible en el servidor.');
        }

        return Storage::disk('local')->response($path);
    }

    /**
     * Obtiene la ruta física del documento de solicitud original (CER)
     */
    public function getRequestFilePath(string $ticketId): string
    {
        $ticket = CerTicket::findOrFail($ticketId); 
        
        if (!$ticket->file_path || !Storage::disk('local')->exists($ticket->file_path)) {
            abort(404, 'Documento de solicitud no encontrado en el servidor.');
        }
        
        return $ticket->file_path;
    }
}