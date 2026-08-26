<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Services;

use App\Domains\Core\Models\Requirement;
use App\Domains\Audit\Services\AuditService;
use App\Domains\Core\Dictionaries\CacheKeyDictionary;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\UploadedFile;

class RequirementClosureService
{
    public function __construct(
        private readonly AuditService $auditService
    ) {}

    /**
     * Cierre y Sellado Absoluto
     */
    public function finalize(Requirement $requirement, array $validated, UploadedFile $notificationFile): array
    {
        return DB::transaction(function () use ($requirement, $validated, $notificationFile) {
            
            // Guardar el archivo de notificación (Soporte Físico)
            $notificationFile->store('closure_notifications', 'local');

            // Generar y Guardar el Acta Definitiva (is_draft = false)
           // $requirement->load(['requestingUnit.system.society', 'functionalConsultant.person']);

            $requirement->load([
                'functionalConsultant.unidad', 
                'functionalConsultant.person', // (Opcional) Si necesitas imprimir el nombre del consultor
                'functionalConsultant.sociedad', // (Opcional) Si necesitas el nombre de la empresa
                'cspeConsultants'
            ]);
            
            $data = [
                'requirement'       => $requirement,
                'notification_date' => $validated['notification_date'],
                'completion_date'   => $validated['completion_date'],
                'generated_at'      => now()->format('d/m/Y H:i:s'),
                'is_draft'          => false, 
            ];
            
            $pdf = Pdf::loadView('pdfs.closure-act', $data)->setPaper('letter', 'portrait');
            $actFileName = 'closure_acts/acta_cierre_' . ($requirement->rrti ?? 'S-N') . '_' . now()->timestamp . '.pdf';
            Storage::disk('local')->put($actFileName, $pdf->output());

            // Mutación Transaccional del Estatus
            $requirement->update([
                'phase_actual'    => 'CIERRE',
                'status'          => 'RF',
                'progreso_global' => 100.00
            ]);

            // Auditoría Trazabilidad 
            // Pasamos 'record_id' dentro del array payload para que el servicio lo mapee como 'target_id'
            $this->auditService->logModelChange(
                'FINALIZE_PROJECT',
                'Cierre histórico definitivo y generación de acta',
                [
                    'record_id'         => $requirement->id,
                    'acta_file_path'    => $actFileName,
                    'notification_date' => $validated['notification_date'],
                    'completion_date'   => $validated['completion_date']
                ]
            );

            // 5.Limpieza del Diccionario de Caché
            // Esto obligará a Angular (y al Backend) a buscar la versión en "Solo Lectura" (is_locked)
            Cache::forget(CacheKeyDictionary::requirementDetail($requirement->id));
            Cache::forget(CacheKeyDictionary::requirementProgress($requirement->id));
            Cache::forget(CacheKeyDictionary::requirementDashboardSummary($requirement->id));
            Cache::forget(CacheKeyDictionary::progressDashboardData($requirement->id));
            
            // Incrementamos la versión global para refrescar de golpe el Dashboard de todos los analistas
            Cache::increment(CacheKeyDictionary::globalDashboardVersion());

            return [
                'status'          => 'RF',
                'progreso_global' => 100.00
            ];
        });
    }
}