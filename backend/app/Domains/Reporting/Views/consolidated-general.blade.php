@extends('reporting::layouts.master')

@section('title', 'Histórico Consolidado de Gestión')

@section('content')
    <style>
        .report-header {
            border-bottom: 2px solid #0056b3;
            margin-bottom: 20px;
            padding-bottom: 10px;
        }

        .report-title {
            font-size: 18px;
            color: #0056b3;
            font-weight: bold;
            text-align: center;
            text-transform: uppercase;
            margin: 0 0 10px 0;
        }

        .filter-summary {
            font-size: 12px;
            background-color: #f4f7f6;
            padding: 10px 12px;
            color: #495057;
            border-radius: 4px;
            border: 1px solid #dee2e6;
        }

        .section-title {
            font-size: 13px;
            font-weight: bold;
            color: #fff;
            background-color: #0056b3;
            padding: 8px;
            margin-top: 20px;
            margin-bottom: 0;
            text-transform: uppercase;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9px;
            margin-bottom: 25px;
        }

        .data-table th {
            background-color: #e9ecef;
            color: #333;
            padding: 8px 6px;
            text-align: center;
            border: 1px solid #ced4da;
        }

        .data-table td {
            padding: 8px 6px;
            border: 1px solid #dee2e6;
            vertical-align: middle;
        }

        .data-table tbody tr:nth-child(even) {
            background-color: #f8f9fa;
        }

        .col-rrti {
            width: 10%;
            font-family: monospace;
            font-weight: bold;
            text-align: center;
        }

        .col-desc {
            width: 35%;
        }

        .col-status {
            width: 9%;
            text-align: center;
        }

        .col-consultant {
            width: 16%;
            font-weight: bold;
        }

        .col-date {
            width: 10%;
            text-align: center;
        }

        .empty-row {
            text-align: center;
            font-style: italic;
            color: #6c757d;
            padding: 15px !important;
        }
    </style>

    <div class="report-header">
        <div class="report-title">Histórico Consolidado de Gestión CSPE</div>
        <div class="filter-summary">
            <strong>Período evaluado:</strong> <span style="color: #0056b3; font-weight: bold;">{{ $periodText }}</span>
        </div>
    </div>

    <!-- TABLA 1: REQUERIMIENTOS CERRADOS -->
    <div class="section-title">Requerimientos Cerrados ({{ count($data['cerrados']) }})</div>
    <table class="data-table">
        <thead>
            <tr>
                <th class="col-rrti">Número RRTI</th>
                <th class="col-desc">Descripción del Requerimiento</th>
                <th class="col-status">Estatus</th>
                <th class="col-consultant">Consultor(es) Asignado(s)</th>
                <th class="col-date">Inicio Atención</th>
                <th class="col-date">Fecha PAP</th>
                <th class="col-date">Fin Atención</th>
            </tr>
        </thead>
        <tbody>
            @forelse($data['cerrados'] as $req)
                <tr>
                    <td class="col-rrti">{{ $req->rrti }}</td>
                    <td class="col-desc">{{ $req->description }}</td>
                    <td class="col-status">Cerrado</td>
                    <td class="col-consultant">{{ $req->nombres_consultores }}</td>
                    <td class="col-date">
                        {{ $req->fecha_inicio_atencion ? \Carbon\Carbon::parse($req->fecha_inicio_atencion)->format('d/m/Y') : '---' }}
                    </td>
                    <td class="col-date">
                        {{ $req->fecha_pap ? \Carbon\Carbon::parse($req->fecha_pap)->format('d/m/Y') : '---' }}</td>
                    <td class="col-date">
                        {{ $req->completion_date ? \Carbon\Carbon::parse($req->completion_date)->format('d/m/Y') : '---' }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="empty-row">No hay requerimientos cerrados en el período seleccionado.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- TABLA 2: REQUERIMIENTOS EN PROCESO -->
    <div class="section-title" style="background-color: #17a2b8;">Requerimientos en Progreso
        ({{ count($data['en_proceso']) }})</div>
    <table class="data-table">
        <thead>
            <tr>
                <th class="col-rrti">Número RRTI</th>
                <th class="col-desc">Descripción del Requerimiento</th>
                <th class="col-status">Estatus</th>
                <th class="col-consultant">Consultor(es) Asignado(s)</th>
                <th class="col-date">Inicio Atención</th>
                <th class="col-date">Fecha PAP</th>
                <th class="col-date">Fin Atención</th>
            </tr>
        </thead>
        <tbody>
            @forelse($data['en_proceso'] as $req)
                <tr>
                    <td class="col-rrti">{{ $req->rrti }}</td>
                    <td class="col-desc">{{ $req->description }}</td>
                    <td class="col-status">En Progreso</td>
                    <td class="col-consultant">{{ $req->nombres_consultores }}</td>
                    <td class="col-date">
                        {{ $req->fecha_inicio_atencion ? \Carbon\Carbon::parse($req->fecha_inicio_atencion)->format('d/m/Y') : '---' }}
                    </td>
                    <td class="col-date">
                        {{ $req->fecha_pap ? \Carbon\Carbon::parse($req->fecha_pap)->format('d/m/Y') : '---' }}</td>
                    <td class="col-date">
                        {{ $req->completion_date ? \Carbon\Carbon::parse($req->completion_date)->format('d/m/Y') : '---' }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="empty-row">No hay requerimientos en proceso en el período seleccionado.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endsection
