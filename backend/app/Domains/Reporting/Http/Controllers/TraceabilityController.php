<?php

namespace App\Domains\Reporting\Http\Controllers; // Asegúrate de que el namespace coincida con la ruta del error

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Domains\Reporting\Services\TraceabilityService;
use Barryvdh\DomPDF\Facade\Pdf;

class TraceabilityController extends Controller
{
    protected $traceabilityService;

    public function __construct(TraceabilityService $traceabilityService)
    {
        $this->traceabilityService = $traceabilityService;
    }

    
    public function searchComponent(Request $request)
    {
        $request->validate([
            'query' => 'required|string|min:3'
        ]);

        $term = $request->input('query');
        $results = $this->traceabilityService->getComponentTimeline($term);

        return response()->json([
            'success' => true,
            'data'    => $results
        ]);
    }

    public function downloadSupportFile(Request $request)
    {
        $request->validate(['file_path' => 'required|string']);
        $filePath = $request->input('file_path');
        $absolutePath = storage_path('app/private/' . ltrim($filePath, '/'));

        if (!file_exists($absolutePath)) {
            return response()->json([
                'success' => false,
                'message' => 'El archivo de soporte no se encuentra en el servidor.'
            ], 404);
        }
        return response()->file($absolutePath);
    }

    public function exportComponentPdf(Request $request)
    {
        // Recibimos el objeto exacto que Angular ya procesó y agrupó
        $data = $request->validate([
            'rrti'                  => 'required|string',
            'component_name'        => 'required|string',
            'functional_consultant' => 'nullable|string',
            'req_status'            => 'nullable|string',
            'groupedTimeline'       => 'required|array'
        ]);

        $pdf = Pdf::loadView('reporting::component_traceability', ['data' => $data]);
        
        // Configuramos la hoja
        $pdf->setPaper('A4', 'portrait');

        return $pdf->download("Expediente_{$data['component_name']}.pdf");
    }
}