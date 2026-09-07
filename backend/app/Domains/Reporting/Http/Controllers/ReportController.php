<?php

declare(strict_types=1);

namespace App\Domains\Reporting\Http\Controllers;

use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use App\Domains\Reporting\Services\ReportService;

class ReportController extends Controller
{
public function __construct(
        private readonly ReportService $reportService
    ) {}


    /**
     * Genera un PDF de prueba para validar la conexión del motor y el membrete corporativo.
     */
    public function generateTestReport(): Response
    {
        // Cargamos la vista de prueba utilizando el alias registrado en el Service Provider
        $pdf = Pdf::loadView('reporting::test');

        // Renderizamos y enviamos el PDF como un flujo binario (stream)
        return $pdf->stream('reporte_prueba_ecosgrti.pdf');
    }

    public function generateMdmDirectory(): Response
    {
        // 1. Delegamos la extracción de datos al servicio
        $users = $this->reportService->getMdmDirectoryData();

        // 2. Diccionario de traducción visual para los roles
        $roleMap = [
            'admin'    => 'Administrador',
            'Coord'    => 'Coordinador CSPE',
            'ConsCSPE' => 'Consultor CSPE',
            'Gerente'  => 'Gerente',
            'Viewer'   => 'Viewer',
        ];

        // 3. Inyectamos la data y el diccionario en la vista Blade
        $pdf = Pdf::loadView('reporting::mdm-directory', compact('users', 'roleMap'));

        // 4. Retornamos el flujo binario
        return $pdf->stream('directorio_mdm_ecosgrti.pdf');
    }
    /**
     * Genera el Acta de Cierre en formato PDF para un requerimiento específico.
     * 
     * @param string $id Identificador UUID del requerimiento
     */
  public function generateClosureAct(string $id): Response
    {
        $requirement = $this->reportService->getRequirementClosureData($id);

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

        $historiesByPhase = $requirement->phaseHistories ? $requirement->phaseHistories->keyBy('phase_status_code') : collect();

        // Bandera maestra que define el tipo de documento
        $isClosed = ($requirement->status === 'RF');
        
        $is_draft = false;
        $generated_at = date('d/m/Y H:i');
        $completion_date = $requirement->completion_date ?? now();

        $pdf = Pdf::loadView('reporting::closure-act', compact(
            'requirement', 
            'isClosed',
            'is_draft', 
            'generated_at', 
            'completion_date',
            'phaseDictionary',
            'historiesByPhase'
        ));

        // Nombramos el archivo dinámicamente según su tipo
        $filenamePrefix = $isClosed ? 'acta_cierre' : 'seguimiento';
        return $pdf->stream("{$filenamePrefix}_{$requirement->rrti}.pdf");
    }

    public function generateClosureActByRrti(string $rrti): Response
    {
        // 1. Buscamos por RRTI en lugar de ID
        $requirement = $this->reportService->getRequirementClosureDataByRrti($rrti);

        // 2. Diccionario de Fases
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

        $historiesByPhase = $requirement->phaseHistories ? $requirement->phaseHistories->keyBy('phase_status_code') : collect();
        $isClosed = ($requirement->status === 'RF');
        $is_draft = false; 
        $generated_at = date('d/m/Y H:i');
        $completion_date = $requirement->completion_date ?? now();

        $pdf = Pdf::loadView('reporting::closure-act', compact(
            'requirement', 'isClosed', 'is_draft', 'generated_at', 
            'completion_date', 'phaseDictionary', 'historiesByPhase'
        ));

        $filenamePrefix = $isClosed ? 'acta_cierre' : 'seguimiento';
        return $pdf->stream("{$filenamePrefix}_{$requirement->rrti}.pdf");
    }

    public function generateAuditLog(Request $request): Response
    {
        $filters = $request->validate([
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after_or_equal:start_date',
            'user_id'    => 'nullable|uuid',
            'action'     => 'nullable|string',
            'rrti'       => 'nullable|string',
        ]);

        $logs = $this->reportService->getFilteredAuditLogs($filters);

        $pdf = Pdf::loadView('reporting::audit-log', [
            'logs'         => $logs,
            'filters'      => $filters,
            'generated_at' => now()->format('d/m/Y H:i'),
            'user'         => auth()->user(),
        ])->setPaper('letter', 'landscape');

        $timestamp = now()->timestamp;
        return $pdf->stream("bitacora_auditoria_{$timestamp}.pdf");
    }



}