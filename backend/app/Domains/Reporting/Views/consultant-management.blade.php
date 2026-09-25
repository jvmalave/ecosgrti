@extends('reporting::layouts.master')

@section('title', 'Histórico de Gestión CSPE')

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
            font-size: 11px;
            background-color: #f4f7f6;
            padding: 8px 12px;
            color: #495057;
            border-radius: 4px;
        }

        .consultant-section {
            margin-bottom: 30px;
            page-break-inside: avoid;
        }

        .consultant-header {
            background-color: #e9ecef;
            border-left: 5px solid #0056b3;
            padding: 8px 12px;
            font-weight: bold;
            font-size: 13px;
            color: #333;
            margin-bottom: 10px;
        }

        .section-title {
            font-size: 13px;
            font-weight: bold;
            color: #fff;
            background-color: #0056b3;
            padding: 8px 12px;
            margin-top: 15px;
            margin-bottom: 5px;
            text-transform: uppercase;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9px;
            margin-bottom: 15px;
        }

        .data-table th {
            background-color: #e9ecef;
            color: #333;
            padding: 6px;
            text-align: center;
            border: 1px solid #ced4da;
        }

        .data-table td {
            padding: 6px;
            border: 1px solid #dee2e6;
            vertical-align: middle;
        }

        .data-table tbody tr:nth-child(even) {
            background-color: #f8f9fa;
        }

        .col-rrti {
            width: 12%;
            font-family: monospace;
            font-weight: bold;
            text-align: center;
        }

        .col-desc {
            width: 40%;
        }

        .col-status {
            width: 12%;
            text-align: center;
        }

        .col-date {
            width: 12%;
            text-align: center;
        }

        .empty-row {
            text-align: center;
            font-style: italic;
            color: #6c757d;
            padding: 10px !important;
        }
    </style>

    <div class="report-header">
        <div class="report-title">Histórico de Gestión Operativa CSPE</div>
        <div class="filter-summary">
            <strong>Período evaluado:</strong>
            @if (!empty($filters['start_date']) && !empty($filters['end_date']))
                {{ \Carbon\Carbon::parse($filters['start_date'])->format('d/m/Y') }} al
                {{ \Carbon\Carbon::parse($filters['end_date'])->format('d/m/Y') }}
            @else
                Histórico completo (Sin filtros de fecha aplicados)
            @endif
        </div>
    </div>

    @forelse($consultants as $consultant)
        <div class="consultant-section">
            <div class="consultant-header">
                Consultor CSPE: {{ $consultant['full_name'] }}
            </div>

            <!-- TABLA 1: REQUERIMIENTOS CERRADOS -->
            <div class="section-title">Requerimientos Cerrados ({{ count($consultant['cerrados']) }})</div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th class="col-rrti">Número RRTI</th>
                        <th class="col-desc">Descripción del Requerimiento</th>
                        <th class="col-status">Estatus</th>
                        <th class="col-date">Fecha Inicio<br>Atención</th>
                        <th class="col-date">Fecha<br>PAP</th>
                        <th class="col-date">Fecha Fin<br>Atención</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($consultant['cerrados'] as $req)
                        <tr>
                            <td class="col-rrti">{{ $req->rrti }}</td>
                            <td class="col-desc">{{ $req->description }}</td>
                            <td class="col-status"><strong>Cerrado</strong></td>
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
                            <td colspan="6" class="empty-row">No posee requerimientos cerrados en este período.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <!-- TABLA 2: REQUERIMIENTOS EN PROCESO -->
            <div class="section-title" style="background-color: #17a2b8;">Requerimientos en Progreso
                ({{ count($consultant['en_proceso']) }})
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th class="col-rrti">Número RRTI</th>
                        <th class="col-desc">Descripción del Requerimiento</th>
                        <th class="col-status">Estatus</th>
                        <th class="col-date">Fecha Inicio<br>Atención</th>
                        <th class="col-date">Fecha<br>PAP</th>
                        <th class="col-date">Fecha Fin<br>Atención</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($consultant['en_proceso'] as $req)
                        <tr>
                            <td class="col-rrti">{{ $req->rrti }}</td>
                            <td class="col-desc">{{ $req->description }}</td>
                            <td class="col-status"><strong>En Progreso</strong></td>
                            <td class="col-date">
                                {{ $req->fecha_inicio_atencion ? \Carbon\Carbon::parse($req->fecha_inicio_atencion)->format('d/m/Y') : '---' }}
                            </td>
                            <td class="col-date">
                                {{ $req->fecha_pap ? \Carbon\Carbon::parse($req->fecha_pap)->format('d/m/Y') : '---' }}
                            </td>
                            <td class="col-date">
                                {{ $req->completion_date ? \Carbon\Carbon::parse($req->completion_date)->format('d/m/Y') : '---' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="empty-row">No posee requerimientos en proceso en este período.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @empty
        <div style="text-align: center; padding: 40px; color: #666;">
            No se encontraron registros para los filtros seleccionados.
        </div>
    @endforelse
@endsection
