@extends('reporting::layouts.master')

@section('title', 'Reporte de Prueba de Conexión')

@section('content')
    <div class="text-center mt-20">
        <h2 style="color: #28a745;">¡Motor DomPDF Conectado Exitosamente!</h2>
        <p>Si estás leyendo este documento, la arquitectura del dominio Reporting en ECOSGRTI está lista para procesar
            indicadores de gestión.</p>
    </div>

    <table style="width: 100%; margin-top: 30px; border-collapse: collapse; border: 1px solid #ddd;">
        <thead style="background-color: #f8f9fa;">
            <tr>
                <th style="padding: 8px; border: 1px solid #ddd; text-align: left;">Dato</th>
                <th style="padding: 8px; border: 1px solid #ddd; text-align: left;">Valor de Sistema</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="padding: 8px; border: 1px solid #ddd;">Versión de PHP</td>
                <td style="padding: 8px; border: 1px solid #ddd;">{{ phpversion() }}</td>
            </tr>
            <tr>
                <td style="padding: 8px; border: 1px solid #ddd;">Zona Horaria</td>
                <td style="padding: 8px; border: 1px solid #ddd;">{{ config('app.timezone') }}</td>
            </tr>
        </tbody>
    </table>
@endsection
