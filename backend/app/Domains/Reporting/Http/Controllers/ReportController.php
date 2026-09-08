<?php

declare(strict_types=1);

namespace App\Domains\Reporting\Http\Controllers;

use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Domains\Reporting\Services\ReportService;
use App\Domains\Reporting\Services\KpiService;

class ReportController extends Controller
{
public function __construct(
        private readonly ReportService $reportService,
        private readonly KpiService $kpiService
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

    public function generateConsultantManagement(Request $request): Response
      {
          $filters = $request->validate([
              'start_date'      => 'nullable|date',
              'end_date'        => 'nullable|date|after_or_equal:start_date',
              'rrti'            => 'nullable|string',
              'status_type'     => 'nullable|string',
              'consultant_id'   => 'nullable|string', 
              'consultant_name' => 'nullable|string', 
          ]);

          $consultantsData = $this->reportService->getConsultantManagement($filters);

          $pdf = Pdf::loadView('reporting::consultant-management', [
              'consultants'  => $consultantsData,
              'filters'      => $filters,
              'generated_at' => now()->format('d/m/Y H:i'),
              'user'         => auth()->user()
          ])->setPaper('letter', 'portrait');

          return $pdf->stream('consolidado_gestion_' . now()->timestamp . '.pdf');
    }

    /**
     * Retorna la lista de consultores CSPE para poblar los selects en el frontend.
     */
    public function getCspeConsultantsList(): JsonResponse
    {
        $consultants = $this->reportService->getCspeConsultantsDictionary();

        return response()->json($consultants);
    }

    public function generateProductionDeployments(Request $request): Response
    {
        $filters = $request->validate([
            'start_date' => 'nullable|date',
            'end_date'   => 'nullable|date|after_or_equal:start_date',
            'rrti'       => 'nullable|string',
        ]);

        $deploymentsData = $this->reportService->getProductionDeployments($filters);

        $pdf = Pdf::loadView('reporting::production-deployments', [
            'deployments'  => $deploymentsData,
            'filters'      => $filters,
            'generated_at' => now()->format('d/m/Y H:i'),
            'user'         => auth()->user()
        ])->setPaper('letter', 'landscape');

        return $pdf->stream('pases_produccion_' . now()->timestamp . '.pdf');
    }


    public function downloadOperationalSheet(Request $request)
    {
        $filters = $request->validate([
            'start_date' => 'nullable|date',
            'end_date'   => 'nullable|date|after_or_equal:start_date',
        ]);

        $data = $this->reportService->getOperationalSheet($filters);

        $filename = 'Sabana_Operativa_' . now()->timestamp . '.csv';

        $headers = [
            "Content-type"        => "text/csv; charset=UTF-8",
            "Content-Disposition" => "attachment; filename=$filename",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        $callback = function() use ($data) {
            $file = fopen('php://output', 'w');
            // Añadimos BOM para que Excel reconozca los acentos y caracteres especiales en UTF-8
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            // Cabeceras del CSV
            fputcsv($file, [
                'RRTI', 'Tipo', 'Descripción', 'Estatus Actual', '% Avance', 
                'Consultor CSPE', 'Consultor Funcional', 'Fecha Creación', 'Fecha Cierre',
                'Cierre DT', 'Cierre COR', 'Cierre COE', 'Cierre PI', 'Cierre CER', 'Cierre CEE', 'Cierre PAP', 'Cierre AU'
            ], ';'); // Usamos punto y coma (;) como delimitador estándar para regiones de habla hispana en Excel

            // Filas de datos
            foreach ($data as $row) {
                fputcsv($file, [
                    $row['rrti'],
                    $row['requirement_type'],
                    str_replace(["\r", "\n"], ' ', $row['description']), // Limpiamos saltos de línea en la descripción
                    $row['status'],
                    $row['progress_percentage'],
                    $row['cspe_consultant'],
                    $row['functional_consultant'],
                    $row['creation_date'],
                    $row['completion_date'],
                    $row['dt_close_date'],
                    $row['cor_close_date'],
                    $row['coe_close_date'],
                    $row['pi_close_date'],
                    $row['cer_close_date'],
                    $row['cee_close_date'],
                    $row['pap_close_date'],
                    $row['au_close_date'],
                ], ';');
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Genera un gráfico vía QuickChart y lo retorna en formato Base64 puro.
     * Evita que DomPDF realice peticiones HTTP externas que puedan colgar el servidor.
     */
    private function generateChartBase64(array $config, int $width = 400, int $height = 250): ?string
    {
        $url = 'https://quickchart.io/chart?c=' . urlencode(json_encode($config)) . '&w=' . $width . '&h=' . $height . '&bkg=white';
        
        $context = stream_context_create(['http' => ['timeout' => 5]]);
        $imageData = @file_get_contents($url, false, $context);

        if ($imageData) {
            return 'data:image/png;base64,' . base64_encode($imageData);
        }

        return null;
    }

    public function generateExecutiveSummary(Request $request)
    {
        $filters = $request->validate([
            'start_date' => 'nullable|date',
            'end_date'   => 'nullable|date|after_or_equal:start_date',
        ]);

        // 1. Extracción de métricas base operativas
        $metrics = $this->reportService->getOperationalExecutiveSummary($filters);

        // 2. Extracción de métricas de Inteligencia de Negocios (Sprint 3)
        $otdData = $this->kpiService->calculateOTD($filters['start_date'] ?? null, $filters['end_date'] ?? null);
        $deviationData = $this->kpiService->calculateScheduleDeviationAndAlerts();
        $agingData = $this->kpiService->calculateAgingMetrics();

        // 3. Preparación visual para Gráficos Operativos (Estatus y Volumen)
        $colorPalette = ['#0056b3', '#28a745','#17a2b8', '#ff5e07', '#dc3545', '#6c757d', '#6610f2', '#e83e8c'];
        $labels = array_keys($metrics['by_status']);
        $dataValues = array_values($metrics['by_status']);
        
        $shortLabels = [];
        $assignedColors = [];
        foreach ($labels as $index => $label) {
            $words = explode(' ', $label);
            $acronym = '';
            foreach ($words as $word) {
                if (strlen($word) > 2) { 
                    $acronym .= strtoupper(substr($word, 0, 1));
                }
            }
            $shortLabels[] = strlen($acronym) >= 2 ? $acronym : substr($label, 0, 6);
            $assignedColors[] = $colorPalette[$index % count($colorPalette)];
        }

        $barConfig = [
            'type' => 'bar',
            'data' => [
                'labels' => $shortLabels,
                'datasets' => [[
                    'label' => 'Requerimientos',
                    'data' => $dataValues,
                    'backgroundColor' => $assignedColors
                ]]
            ],
            'options' => [
                'plugins' => [
                    'legend' => ['display' => false],
                    'title' => ['display' => true, 'text' => 'Estatus de Requerimientos']
                ]
            ]
        ];

        $pieConfig = [
            'type' => 'doughnut',
            'data' => [
                'labels' => ['Completados / Cerrados', 'En Proceso / Activos'],
                'datasets' => [[
                    'data' => [$metrics['completed_count'], $metrics['in_progress_count']],
                    'backgroundColor' => ['#28a745', '#ffc107']
                ]]
            ],
            'options' => [
                'plugins' => [
                    'title' => ['display' => true, 'text' => 'Volumen de Gestión']
                ]
            ]
        ];

        // 4. Preparación visual para Gráficos de Inteligencia de Negocios
        $otdChartConfig = [
            'type' => 'doughnut',
            'data' => [
                'labels' => ['A Tiempo', 'Atrasado'],
                'datasets' => [[
                    'data' => [$otdData['on_time_count'], $otdData['late_count']],
                    'backgroundColor' => ['#10B981', '#EF4444']
                ]]
            ],
            'options' => [
                'plugins' => [
                    'title' => ['display' => true, 'text' => 'Tasa de Entrega a Tiempo (OTD)'],
                    'datalabels' => ['display' => true, 'color' => '#fff', 'font' => ['weight' => 'bold']]
                ]
            ]
        ];

        $buckets = $agingData['data']['aging_buckets'] ?? ['0_15_days' => 0, '16_30_days' => 0, '31_60_days' => 0, 'over_60_days' => 0];
        $agingChartConfig = [
            'type' => 'bar',
            'data' => [
                'labels' => ['0-15 Días', '16-30 Días', '31-60 Días', '+60 Días'],
                'datasets' => [[
                    'label' => 'Req. Activos',
                    'data' => array_values($buckets),
                    'backgroundColor' => ['#3B82F6', '#F59E0B', '#F97316', '#EF4444']
                ]]
            ],
            'options' => [
                'plugins' => [
                    'legend' => ['display' => false],
                    'title' => ['display' => true, 'text' => 'Envejecimiento (Aging)']
                ],
                'scales' => ['yAxes' => [['ticks' => ['beginAtZero' => true, 'stepSize' => 1]]]]
            ]
        ];

        // 5. Renderizado concurrente a Base64
        $chartBarBase64   = $this->generateChartBase64($barConfig, 450, 200);
        $chartPieBase64   = $this->generateChartBase64($pieConfig, 300, 200);
        $chartOtdBase64   = $this->generateChartBase64($otdChartConfig, 300, 200);
        $chartAgingBase64 = $this->generateChartBase64($agingChartConfig, 450, 200);

        // 6. Ensamblaje y entrega de la vista
        $pdf = Pdf::loadView('reporting::executive-summary', [
            // Data Cruda Operativa
            'metrics'          => $metrics,
            'filters'          => $filters,
            'assignedColors'   => $assignedColors,
            
            // Data Cruda KPIs
            'otd'              => $otdData,
            'deviation'        => $deviationData['data'] ?? [],
            'aging'            => $agingData['data'] ?? [],
            
            // Gráficos Renderizados (Base64)
            'chartBarBase64'   => $chartBarBase64,
            'chartPieBase64'   => $chartPieBase64,
            'chartOtdBase64'   => $chartOtdBase64,
            'chartAgingBase64' => $chartAgingBase64,
            
            'generated_at'     => now()->format('d/m/Y H:i'),
            'user'             => auth()->user()
        ])->setPaper('letter', 'portrait');

        return $pdf->stream('Resumen_Ejecutivo_Integral_' . now()->timestamp . '.pdf');
    }

    public function getOtdMetrics(Request $request)
    {
        $filters = $request->validate([
            'start_date' => 'nullable|date',
            'end_date'   => 'nullable|date|after_or_equal:start_date',
        ]);

        $otdData = $this->kpiService->calculateOTD(
            $filters['start_date'] ?? null, 
            $filters['end_date'] ?? null
        );

        return response()->json([
            'success' => true,
            'data'    => $otdData
        ]);
    }


    public function getDeviationAlerts(Request $request)
    {
        $result = $this->kpiService->calculateScheduleDeviationAndAlerts();
        return response()->json($result);
    }

    public function getAgingMetrics(Request $request)
    {
        $result = $this->kpiService->calculateAgingMetrics();
        return response()->json($result);
    }



}