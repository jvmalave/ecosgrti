<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Traits;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Illuminate\Database\Eloquent\Model;
use App\Domains\Core\Models\Requirement;
use App\Domains\Core\Dictionaries\CacheKeyDictionary;
use App\Domains\Security\Services\SpecialOperationService;

use Exception;


/**
 * @property \App\Domains\Audit\Services\AuditService $auditService
 * @property \App\Domains\Workflow\Services\PhaseTransitionService $phaseTransitionService
 * @property \App\Domains\Workflow\Services\ProgressCalculationService $progressService

*/

trait ManagesCertificationTickets
{
    abstract protected function getTicketModel(): string;
    abstract protected function getComponentModel(): string;
    abstract protected function getPhaseInitCode(): string; // Ej: 'CER-I' o 'CEE-I'
    abstract protected function getTicketPrefix(): string; // Ej: 'CER' o 'CEE'
    abstract protected function generatesTicketNumberAutomatically(): bool;


    /**
     * Registrar Solicitud de Ticket
     */
    public function storeTicket(string $requirementId, array $data, UploadedFile $file, array $componentIds): Model
    {
        $ticketModel = $this->getTicketModel();
        $componentModel = $this->getComponentModel();

        return DB::transaction(function () use ($ticketModel, $componentModel, $requirementId, $data, $file, $componentIds) {
            
            // =====================================================================
            // 1. Asignación o Generación de Número de Ticket (Adaptable CER/CEE)
            // =====================================================================
            if ($this->generatesTicketNumberAutomatically()) {
                // Para CEE: El sistema lo genera (Ej. CEE-000001)
                $ticketNumber = $this->generateTicketNumber();
            } else {
                // Para CER: Viene en el Request digitado por el usuario (CSAL)
                if (empty($data['ticket_number'])) {
                    throw new Exception("El número de ticket es obligatorio y debe ser provisto por el ente certificador.", 422);
                }
                $ticketNumber = $data['ticket_number'];
            }
            
            // 2. Almacenamiento del PDF
            $path = $file->store("{$this->getTicketPrefix()}_tickets/{$requirementId}", 'local');

            // 3. Creación del Ticket
            $ticket = $ticketModel::create([
                'requirement_id' => $requirementId,
                'ticket_number'  => $ticketNumber,
                'request_date'   => $data['request_date'],
                'file_path'      => $path,
                'status'         => 'TKT_IN_PROGRESS',
                'created_by'     => auth()->id(),
            ]);

            // 4. Actualización Masiva de Componentes (Promoción a IN_PROGRESS)
            $componentModel::whereIn('id', $componentIds)
                ->where('requirement_id', $requirementId)
                ->update([
                    'status'     => 'IN_PROGRESS',
                    'ticket_id'  => $ticket->id,
                    'updated_by' => auth()->id()
                ]);

            // 5. Disparador Transaccional Global (Avance automático a CER-I / CEE-I)
            $this->triggerPhaseInitiation($requirementId);

            // 6. Auditoría y Caché
            $this->auditService->logModelChange(
                'CREATE_CERTIFICATION_TICKET',
                "Creación de Ticket {$ticketNumber} en fase {$this->getPhaseInitCode()}",
                ['ticket_id' => $ticket->id, 'components_assigned' => $componentIds],
                (string) auth()->id(),
                $requirementId
            );

            $this->purgeCertificationCache($requirementId);

            return $ticket;
        });
    }

    /**
     * Actualizar Solicitud de Ticket (Sincronización en Cascada)
     */
    public function updateTicket(string $ticketId, array $data, ?UploadedFile $file, array $newComponentIds): Model
    {
        $ticketModel = $this->getTicketModel();
        $componentModel = $this->getComponentModel();

        return DB::transaction(function () use ($ticketModel, $componentModel, $ticketId, $data, $file, $newComponentIds) {
            $ticket = $ticketModel::findOrFail($ticketId);

            if ($ticket->status !== 'TKT_IN_PROGRESS') {
                throw new Exception("Solo se pueden modificar tickets en proceso.", 422);
            }

            // 1. Reemplazo opcional de archivo
            if ($file) {
                if (Storage::disk('local')->exists($ticket->file_path)) {
                    Storage::disk('local')->delete($ticket->file_path);
                }
                $ticket->file_path = $file->store("{$this->getTicketPrefix()}_tickets/{$ticket->requirement_id}", 'local');
            }

            $ticket->request_date = $data['request_date'] ?? $ticket->request_date;
            $ticket->updated_by = auth()->id();
            $ticket->save();

            // 2. Sincronización en Cascada de Componentes (RN-CER-22)
            // Liberar los que fueron desmarcados
            $componentModel::where('ticket_id', $ticketId)
                ->whereNotIn('id', $newComponentIds)
                ->update([
                    'status'    => 'PENDING_CERTIFICATION',
                    'ticket_id' => null,
                    'updated_by'=> auth()->id()
                ]);

            // Asociar los nuevos seleccionados
            $componentModel::whereIn('id', $newComponentIds)
                ->update([
                    'status'    => 'IN_PROGRESS',
                    'ticket_id' => $ticketId,
                    'updated_by'=> auth()->id()
                ]);

            $this->auditService->logModelChange('UPDATE_CERTIFICATION_TICKET', "Edición de Ticket {$ticket->ticket_number}", ['ticket_id' => $ticket->id], (string) auth()->id());
            $this->purgeCertificationCache((string) $ticket->requirement_id);

            return $ticket;
        });
    }

    /**
     * (Resultados): Registrar Dictamen y Bifurcar
     */
    public function registerResult(string $ticketId, array $data, UploadedFile $file, array $evaluations): Model
    {
        return $this->processResultTransaction($ticketId, $data, $file, $evaluations, false);
    }

    /**
     * (Resultados): Actualización Post-Cierre con Desafío de Seguridad
     */
    public function updateResultWithChallenge(string $ticketId, array $data, ?UploadedFile $file, array $evaluations, string $specialAuthToken): Model
    {
        app(SpecialOperationService::class)->consumeDeletionTicket((string) auth()->id(), $specialAuthToken);
        
        return $this->processResultTransaction($ticketId, $data, $file, $evaluations, true);
    }
    
    /**
     * Motor Privado de Procesamiento de Resultados (Atomicidad y Snapshots)
     */
    private function processResultTransaction(string $ticketId, array $data, ?UploadedFile $file, array $evaluations, bool $isException): Model
    {
        $ticketModel = $this->getTicketModel();
        $componentModel = $this->getComponentModel();

        return DB::transaction(function () use ($ticketModel, $componentModel, $ticketId, $data, $file, $evaluations, $isException) {
            $ticket = $ticketModel::findOrFail($ticketId);
            $requirementId = (string) $ticket->requirement_id;

            // SNAPSHOT PREVIO (Para auditoría de actualización excepcional)
            $prevSnapshot = $isException ? $componentModel::where('ticket_id', $ticketId)->get()->toArray() : null;

            // 1. Procesamiento de Evidencia de Resultado
            if ($file) {
                if ($ticket->result_file && Storage::disk('local')->exists($ticket->result_file)) {
                    Storage::disk('local')->delete($ticket->result_file);
                }
                $ticket->result_file = $file->store("{$this->getTicketPrefix()}_results/{$requirementId}", 'local');
            }

            // 2. Evaluación Granular por Componente (Bifurcación e Historial)
            $approvedCount = 0;
            $totalCount = count($evaluations);

            foreach ($evaluations as $eval) {
                $component = $componentModel::findOrFail($eval['id']);
                
                if ($eval['is_approved']) {
                    $component->update([
                        'status' => 'CERTIFIED', 
                        'rejection_reason' => null, // Limpiamos el motivo activo
                        'updated_by' => auth()->id()
                    ]);
                    $approvedCount++;
                } else {
                    // 🟢 NUEVO: Construimos el delta del historial de rechazos
                    $history = $component->rejection_history ?? [];
                    
                    $history[] = [
                        'ticket_number' => $ticket->ticket_number,
                        'reason'        => $eval['rejection_reason'],
                        'rejected_by'   => auth()->id(),
                        'rejected_at'   => now()->toDateTimeString(),
                    ];

                    $component->update([
                        'status'            => 'PENDING_CERTIFICATION', 
                        'rejection_reason'  => $eval['rejection_reason'], // Mantenemos el último para lectura rápida en frontend
                        'rejection_history' => $history, // Guardamos la colección completa inmutable
                        'updated_by'        => auth()->id()
                    ]);
                }
            }

            // 3. Categorización Automática
            $ticket->result_category = $this->calculateResultCategory($totalCount, $approvedCount);
            $ticket->status = 'TKT_CLOSED';
            $ticket->updated_by = auth()->id();
            $ticket->save();

            // 4. Auditoría
            if ($isException) {
                $newSnapshot = $componentModel::where('ticket_id', $ticketId)->get()->toArray();
                $this->auditService->logModelChange(
                    "UPDATE_CLOSED_{$this->getTicketPrefix()}_TICKET", 
                    "Intervención de Coordinador sobre resultado de ticket {$ticket->ticket_number}", 
                    ['prev_snapshot' => $prevSnapshot, 'new_snapshot' => $newSnapshot], 
                    (string) auth()->id()
                );
            } else {
                $this->auditService->logModelChange("REGISTER_{$this->getTicketPrefix()}_RESULT", "Dictamen registrado para ticket {$ticket->ticket_number}", [], (string) auth()->id());
            }

            $this->purgeCertificationCache($requirementId);

            return $ticket;
        });
    }

    /**
     * Métodos Auxiliares
     */
    private function calculateResultCategory(int $total, int $approved): string
    {
        if ($approved === $total) return 'TOTAL';
        if ($approved === 0) return 'RECHAZO_TOTAL';
        return 'PARCIAL';
    }

    private function generateTicketNumber(): string
    {
        $prefix = $this->getTicketPrefix();
        $count = $this->getTicketModel()::count() + 1;
        return sprintf('%s-%06d', $prefix, $count);
    }

    private function triggerPhaseInitiation(string $requirementId): void
    {
        $ticketCount = $this->getTicketModel()::where('requirement_id', $requirementId)->count();
        
        if ($ticketCount === 1) {
            $requirement = Requirement::lockForUpdate()->findOrFail($requirementId);
            $phaseCode = $this->getPhaseInitCode();
            
            $hasInitHistory = DB::table('workflow.requirement_phase_history')
                ->where('requirement_id', $requirementId)
                ->where('phase_status_code', $phaseCode)
                ->exists();

            if (!$hasInitHistory) {
                $this->phaseTransitionService->recordTransition($requirementId, $phaseCode, (string) auth()->id(), "Inicio de fase {$phaseCode} al crear ticket.");
                
                $calculatedProgress = $this->progressService->calculateGlobalProgress($requirement);
                $isVanguard = $this->phaseTransitionService->isVanguardStatus($phaseCode, $requirement->status);

                if ($isVanguard) {
                    $requirement->update(['status' => $phaseCode, 'progress_percentage' => $calculatedProgress]);
                } else {
                    $requirement->update(['progress_percentage' => $calculatedProgress]);
                }

                $this->auditService->logModelChange(
                    'INITIATE_GLOBAL_PHASE',
                    "Apertura global de la fase " . $phaseCode . ($isVanguard ? " (Nueva Vanguardia)" : " (Proceso Paralelo)"),
                    [
                        'requirement_id' => $requirementId,
                        'new_status' => $requirement->status, 
                        'progress_reached' => $calculatedProgress,
                        'is_vanguard_update' => $isVanguard
                    ],
                    (string) auth()->id(),
                    $requirementId
                );

                Cache::put(CacheKeyDictionary::requirementProgress($requirementId), $calculatedProgress, now()->addDays(1));

                $this->purgeCertificationCache($requirementId);
            }
        }
    }

    private function purgeCertificationCache(string $requirementId): void
    {
        // Purgamos la lista maestra de componentes del requerimiento para esta fase
        $listKey = CacheKeyDictionary::phaseComponentsList($requirementId, $this->getPhaseInitCode());
        Cache::forget($listKey);
        Redis::del($listKey);
        
        // Purgamos también cualquier lista específica de tickets que pudieses tener (Opcional según tu Dictionary)
        Redis::incr(CacheKeyDictionary::globalDashboardVersion());
    }
}