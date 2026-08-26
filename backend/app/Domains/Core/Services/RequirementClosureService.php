<?php

namespace App\Domains\Core\Services;

use App\Domains\Core\Models\Requirement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\UploadedFile;

class RequirementClosureService
{
  /**
     * Genera el acta borrador en formato Base64 para previsualización forense.
     */
    public function generateDraftBase64(Requirement $requirement, array $data): string
    {
        // 1. Cargar el grafo organizacional exacto
        $requirement->load([
            'functionalConsultant.unidad',
            'functionalConsultant.sistema',
            'functionalConsultant.sociedad',
            'functionalConsultant.person'
        ]);

        // 2. Compilar el Acta Borrador
        $pdfData = [
            'requirement'       => $requirement,
            'notification_date' => $data['notification_date'],
            'completion_date'   => $data['completion_date'],
            'generated_at'      => now()->format('d/m/Y H:i:s'),
            'is_draft'          => true, // Activa la marca de agua
        ];

        $pdf = Pdf::loadView('pdfs.closure-act', $pdfData)->setPaper('letter', 'portrait');

        // 3. Retornar la cadena codificada lista para el frontend
        return base64_encode($pdf->output());
    }



    /**
     * Ejecuta la transacción atómica para el cierre histórico del requerimiento.
     */
    public function finalize(Requirement $requirement, array $data, ?UploadedFile $file): void
    {
        DB::transaction(function () use ($requirement, $data, $file) {
            
            // 1. Cargar el grafo organizacional
            $requirement->load([
                'functionalConsultant.unidad',
                'functionalConsultant.sistema',
                'functionalConsultant.sociedad',
                'functionalConsultant.person'
            ]);

            // 2. Compilar el Acta Definitiva
            $pdfData = [
                'requirement'       => $requirement,
                'notification_date' => $data['notification_date'],
                'completion_date'   => $data['completion_date'],
                'generated_at'      => now()->format('d/m/Y H:i:s'),
                'is_draft'          => false, 
            ];

            $pdf = Pdf::loadView('pdfs.closure-act', $pdfData)->setPaper('letter', 'portrait');
            
            // 3. Guardar el Acta Definitiva en el Storage PRIVADO
            $actPath = "private/requirements/{$requirement->id}/closure/acta_cierre_{$requirement->rrti}.pdf";
            Storage::disk('local')->put($actPath, $pdf->output());

            // 4. Guardar el Soporte Físico 
            $supportPath = null;
            if ($file) {
                $supportPath = $file->storeAs(
                    "private/requirements/{$requirement->id}/closure", 
                    "soporte_notificacion_{$requirement->rrti}." . $file->extension(),
                    'local'
                );
            }

            // 5. Persistencia Forense (Actualización en BD)
            $requirement->forceFill([
            'status'                      => 'RF',
            'progress_percentage'         => 100,
            'notification_date'           => $data['notification_date'],
            'completion_date'             => $data['completion_date'],
            'closure_act_path'            => $actPath,
            'notification_support_path'   => $supportPath,
            'conformity_declaration'      => true,
        ])->save();
            // 6. Despachar el evento (Notificaciones)
            event(new \App\Events\RequirementFinalizedEvent($requirement));
        });
    }
}