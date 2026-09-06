<?php

declare(strict_types=1);

namespace App\Domains\Reporting\Http\Controllers;

use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class ReportController extends Controller
{
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
}