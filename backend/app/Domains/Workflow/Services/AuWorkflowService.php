<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Services;

use App\Domains\Core\Dictionaries\CacheKeyDictionary;
use App\Domains\Workflow\Models\AuRole;
use App\Domains\Workflow\Models\AuTicket;
use App\Domains\Workflow\Services\Base\AbstractPhaseComponentService;
use App\Domains\Workflow\Traits\ManagesCertificationTickets;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AuWorkflowService extends AbstractPhaseComponentService
{
    // Inyecta el motor polimórfico, pero renombra sus métodos transaccionales
    // para poder envolver con la lógica de planillas individuales.
    use ManagesCertificationTickets {
        storeTicket as protected traitStoreTicket;
        updateTicket as protected traitUpdateTicket;
    }

    // ==========================================
    // MÉTODOS ABSTRACTOS DE LA CLASE BASE
    // ==========================================
    protected function getComponentModel(): string
    {
        return AuRole::class;
    }

    protected function getCacheKeyPrefix(): string
    {
        return 'au_roles';
    }

    protected function getPhaseCode(): string
    {
        return 'AU-C';
    }

    protected function getPhaseInitCode(): string
    {
        return 'AU-I';
    }

    // Hard Gate de inicio: Exige Pase a Producción (PAP-C) esté cerrado
    protected function getRequiredPredecessorPhases(): array
    {
        return ['PAP-C'];
    }

    // ==========================================
    // MÉTODOS ABSTRACTOS DEL TRAIT
    // ==========================================
    protected function getTicketModel(): string
    {
        return AuTicket::class;
    }

    protected function getTicketPrefix(): string
    {
        return 'AU';
    }

    // El número de ticket de CSAL es introducido manualmente, no es autogenerado
    protected function generatesTicketNumberAutomatically(): bool
    {
        return false;
    }

    // ==========================================
    // SOBRESCRITURA POLIMÓRFICA PARA AU 
    // ==========================================
    protected function getTicketIdentifierColumn(): string
    {
        return 'ticket_number';
    }

    protected function getTicketForeignKey(): string
    {
        return 'ticket_id';
    }

    protected function getDateColumn(): string
    {
        return 'request_date';
    }

    // Estatus de los Tickets
    protected function getTicketInProgressStatus(): string
    {
        return 'TKT_IN_PROGRESS';
    }

    protected function getTicketClosedStatus(): string
    {
        return 'TKT_CLOSED';
    }

    // Estatus de los Roles
    protected function getComponentPendingStatus(): string
    {
        return 'PENDING_AU';
    }

    protected function getComponentInProgressStatus(): string
    {
        return 'IN_PROGRESS';
    }

    protected function getComponentCertifiedStatus(): string
    {
        return 'ASSIGNED'; 
    }

    // El candado de oro: Solo se cierra la fase si TODOS los roles están ASSIGNED
    protected function getCompletedStatusCode(): string
    {
        return 'ASSIGNED';
    }

    // Configuración de Auditoría y Rechazos
    protected function getRejectionReasonColumn(): string
    {
        return 'rejection_reason';
    }

    protected function getAuditActionCreate(): string
    {
        return 'CREATE_AU_TICKET';
    }

    protected function getAuditActionUpdate(): string
    {
        return 'UPDATE_AU_TICKET';
    }

    protected function getAuditActionRegisterResult(): string
    {
        return 'REGISTER_AU_RESULT';
    }

    // ==========================================
    // LÓGICA ESPECÍFICA AU Y SOBRESCRITURAS
    // ==========================================

    /**
     * Inicializar y Migrar Roles desde PAP hacia AU de forma idempotente.
     */
    public function initializeRoles(string $requirementId): array
    {
        $papRolesQuery = DB::table('workflow.pap_roles')
            ->where('requirement_id', $requirementId)
            ->where('status', 'IN_PRODUCTION');

        if ($papRolesQuery->count() === 0) {
            abort(403, 'Acceso denegado: El requerimiento no posee roles desplegados en producción listos para asignación.');
        }

        $productiveRoles = $papRolesQuery->get();
        $auRoleModel = $this->getComponentModel();

        foreach ($productiveRoles as $papRole) {
            $auRoleModel::firstOrCreate(
                [
                    'requirement_id' => $requirementId,
                    'requirement_role_id' => $papRole->requirement_role_id,
                ],
                [
                    'status' => $this->getComponentPendingStatus(),
                    'created_by' => auth()->id(),
                ]
            );
        }

        $roles = $auRoleModel::with('requirementRole', 'ticket')
            ->where('requirement_id', $requirementId)
            ->orderBy('created_at', 'asc')
            ->get();

        return [
            'requirement_id' => $requirementId,
            'roles' => $roles
        ];
    }

    /**
     * Dualidad de Soportes Digitales (Sobrescritura del trait)
     */
    public function storeTicket(string $requirementId, array $data, UploadedFile $file, array $componentIds): \Illuminate\Database\Eloquent\Model
    {
        return DB::transaction(function () use ($requirementId, $data, $file, $componentIds) {

            $ticket = $this->traitStoreTicket($requirementId, $data, $file, $componentIds);

            $planillas = $data['planillas'] ?? [];

            if (count($planillas) !== count($componentIds)) {
                abort(422, 'Validación fallida: La cantidad de planillas individuales debe coincidir exactamente con la cantidad de roles seleccionados.');
            }

            $componentModel = $this->getComponentModel();

            foreach ($componentIds as $roleId) {
                if (!isset($planillas[$roleId])) {
                    abort(422, "Validación fallida: No se recibió la planilla correspondiente para el rol seleccionado.");
                }

                $planillaFile = $planillas[$roleId];
                $path = $planillaFile->store("{$this->getTicketPrefix()}_planillas/{$requirementId}/{$ticket->id}", 'local');

                $componentModel::where('id', $roleId)->update([
                    'planilla_path' => $path
                ]);
            }

            // 🟢 Invocamos la limpieza de caché al final
            $this->flushDashboardCaches($requirementId);

            return $ticket;
        });
    }

    /**
     * INTERVENCIÓN DEL POLIMORFISMO:
     * Al sobrescribir el método de purga del trait original, 
     * garantizamos que CUALQUIER acción del Trait (como registerResult)
     * detone la reactividad de nuestro Dashboard.
     */
    protected function purgeCertificationCache(string $requirementId): void
    {
        // Limpia la caché propia del trait
        $listKey = CacheKeyDictionary::phaseComponentsList($requirementId, $this->getPhaseInitCode());
        Cache::forget($listKey);
        
        // Ejecuta la invalidación estricta de Dashboard
        $this->flushDashboardCaches($requirementId);
    }

    /**
     * Helper centralizado para limpiar las cachés del Dashboard
     */
    private function flushDashboardCaches(string $requirementId): void
    {
        Cache::forget(CacheKeyDictionary::requirementDetail($requirementId));
        Cache::forget(CacheKeyDictionary::requirementProgress($requirementId));
        Cache::forget(CacheKeyDictionary::requirementDashboardSummary($requirementId));
        Cache::forget(CacheKeyDictionary::progressDashboardData($requirementId));

        if (Cache::supportsTags()) {
            Cache::tags([CacheKeyDictionary::dashboardTag()])->flush();
        }

        Cache::forget(CacheKeyDictionary::globalDashboardVersion());
    }

    /**
     * CU-065: SOBRESCRITURA PARA AÑADIR LA VALIDACIÓN EXCEPCIONAL DE TICKETS
     * Valida que no haya tickets en proceso ni sin soporte documental (RN-AU-27)
     * antes de permitir que la clase abstracta evalúe los roles y cierre la fase.
     */
    public function closeGlobalPhase(string $requirementId): array
    {
        $ticketModel = $this->getTicketModel();
        
        $countInvalidTickets = $ticketModel::where($this->getRequirementColumn(), $requirementId)
            ->where(function ($query) {
                $query->where('status', $this->getTicketInProgressStatus())
                      ->orWhereNull('file_path');
            })
            ->count();

        if ($countInvalidTickets > 0) {
            abort(422, "Validación fallida: Existen {$countInvalidTickets} ticket(s) de CSAL en proceso o sin soporte documental general adjunto.");
        }

        // Si los tickets están perfectos, se delega el resto de la validación pesada 
        // (Roles, Predecesores, Progreso, Auditoría y Redis) a la clase Abstracta.
        return parent::closeGlobalPhase($requirementId);
    }




    // NOTA DE LIMPIEZA:
    // Al no declararlo en esta clase, Laravel automáticamente invoca el `registerResult` 
    // nativo del Trait `ManagesCertificationTickets`, utilizando las variables que 
    // definimos arriba (como $this->getComponentCertifiedStatus() para retornar 'ASSIGNED').
}