<?php

namespace App\Domains\Core\Services;

use App\Domains\Core\Models\Requirement;
use App\Domains\Audit\Services\AuditService;
use App\Domains\Core\Dictionaries\CacheKeyDictionary;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\UploadedFile;

class RequirementClosureService
{
    public function __construct(
        private readonly AuditService $auditService
    ) {}

    /**
     * Orquesta la carga relacional masiva y las variables del diccionario.
     */
    private function prepareClosureData(Requirement $requirement, array $data): array
    {
        $requirement->load([
            'functionalConsultant.person',
            'cspeConsultants.person',
            'atfAgreements',
            'roles',
            'deliverables',
            'phaseHistories' => fn($q) => $q->orderBy('created_at', 'asc'),
            'cerTicket', 'papOrder', 'auTicket', 'ceeTicket',
            'dtRoles', 'corRoles', 'piRoles', 'cerRoles', 'papRoles', 'auRoles',
            'coeDeliverables', 'ceeDeliverables'
        ]);

        // Asignación en memoria: Permite que la vista renderice las fechas del formulario
        $requirement->notification_date = $data['notification_date'];
        $requirement->completion_date = $data['completion_date'];

        $phaseDictionary = [
            'RC'    => 'Requerimiento Creado (Inicio)',
            'ES-R'  => 'Planificación Realizada',
            'ATF-C' => 'Análisis Técnico Funcional concluido',
            'DT-C'  => 'Diseño Técnico concluido',
            'COR-C' => 'Construcción Roles Concluida',
            'PI-C'  => 'Pruebas Integrales',
            'CER-C' => 'Certificación Roles',
            'PAP-C' => 'Pase a Producción',
            'AU-C'  => 'Asignación a Usuarios',
            'COE-C' => 'Construcción Entregables Concluida',
            'CEE-C' => 'Certificación Entregables Concluida',
        ];

        return [
            'requirement'      => $requirement,
            'completion_date'  => $data['completion_date'],
            'generated_at'     => now()->format('d/m/Y H:i'),
            'phaseDictionary'  => $phaseDictionary,
            'historiesByPhase' => $requirement->phaseHistories ? $requirement->phaseHistories->keyBy('phase_status_code') : collect(),
            'isClosed'         => true,
        ];
    }

    /**
     * Genera el acta borrador en formato Base64 para previsualización forense.
     */
    public function generateDraftBase64(Requirement $requirement, array $data): string
    {
        $pdfData = $this->prepareClosureData($requirement, $data);
        
        $pdfData['isClosed'] = true; 
        $pdfData['is_draft'] = true; 

        $pdf = Pdf::loadView('reporting::closure-act', $pdfData)->setPaper('letter', 'portrait');

        return base64_encode($pdf->output());
    }

    /**
     * Ejecuta la transacción atómica para el cierre histórico del requerimiento.
     */
    public function finalize(Requirement $requirement, array $data, ?UploadedFile $file): array
    {
        return DB::transaction(function () use ($requirement, $data, $file) {
            
            $pdfData = $this->prepareClosureData($requirement, $data);
            
            $pdfData['isClosed'] = true; 
            $pdfData['is_draft'] = false; 

            $pdf = Pdf::loadView('reporting::closure-act', $pdfData)->setPaper('letter', 'portrait');
            
            $actPath = "private/requirements/{$requirement->id}/closure/acta_cierre_{$requirement->rrti}.pdf";
            Storage::disk('local')->put($actPath, $pdf->output());

            $supportPath = null;
            if ($file) {
                $supportPath = $file->storeAs(
                    "private/requirements/{$requirement->id}/closure", 
                    "soporte_notificacion_{$requirement->rrti}." . $file->extension(),
                    'local'
                );
            }

            $requirement->forceFill([
                'status'                      => 'RF',
                'progress_percentage'         => 100,
                'notification_date'           => $data['notification_date'],
                'completion_date'             => $data['completion_date'],
                'closure_act_path'            => $actPath,
                'notification_support_path'   => $supportPath,
                'conformity_declaration'      => true,
            ])->save();

            // 1. Auditoría Trazabilidad
            $this->auditService->logModelChange(
                'FINALIZE_PROJECT',
                'Cierre histórico definitivo y generación de acta',
                [
                    'record_id'         => $requirement->id,
                    'acta_file_path'    => $actPath,
                    'notification_date' => $data['notification_date'],
                    'completion_date'   => $data['completion_date']
                ]
            );

            // 2. Limpieza del Diccionario de Caché
            Cache::forget(CacheKeyDictionary::requirementDetail($requirement->id));
            Cache::forget(CacheKeyDictionary::requirementProgress($requirement->id));
            Cache::forget(CacheKeyDictionary::requirementDashboardSummary($requirement->id));
            Cache::forget(CacheKeyDictionary::progressDashboardData($requirement->id));
            Cache::increment(CacheKeyDictionary::globalDashboardVersion());

            // 3. Despacho de Eventos
            event(new \App\Events\RequirementFinalizedEvent($requirement));

            return [
                'progress_percentage' => 100,
                'act_path' => $actPath
            ];
        });
    }
}