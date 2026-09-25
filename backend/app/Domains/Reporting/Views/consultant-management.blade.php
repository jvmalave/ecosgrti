@extends('reporting::layouts.master')

@section('title', 'Consolidado de Gestión CSPE')

@section('content')
    <style>
        .report-header {
            margin-bottom: 15px;
            border-bottom: 2px solid #0056b3;
            padding-bottom: 8px;
        }

        .report-title {
            font-size: 16px;
            color: #0056b3;
            font-weight: bold;
            text-align: center;
            text-transform: uppercase;
            margin: 0 0 8px 0;
        }

        .filter-summary {
            font-size: 10px;
            background-color: #f4f7f6;
            border: 1px solid #dee2e6;
            padding: 6px 10px;
            color: #495057;
            border-radius: 4px;
        }

        .stats-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
            margin-top: 15px;
            table-layout: fixed;
        }

        .stats-table th {
            background-color: #0056b3;
            color: #ffffff;
            font-weight: bold;
            padding: 8px 6px;
            text-align: center;
            border: 1px solid #004494;
            text-transform: uppercase;
        }

        .stats-table th.text-left {
            text-align: left;
        }

        .stats-table td {
            padding: 8px 6px;
            border: 1px solid #dee2e6;
            color: #333333;
            text-align: center;
            vertical-align: middle;
        }

        .stats-table td.text-left {
            text-align: left;
            font-weight: bold;
        }

        .stats-table tbody tr:nth-child(even) {
            background-color: #f8f9fa;
        }

        .highlight-badge {
            background-color: #e9ecef;
            padding: 3px 6px;
            border-radius: 4px;
            font-weight: bold;
            border: 1px solid #ced4da;
        }
    </style>

    <div class="report-header">
        <div class="report-title">Consolidado de Gestión Operativa por Consultor CSPE</div>
        <div class="filter-summary">
            <strong>Parámetros de Medición:</strong>
            @if (!empty($filters['start_date']) && !empty($filters['end_date']))
                Período evaluado del {{ \Carbon\Carbon::parse($filters['start_date'])->format('d/m/Y') }} al
                {{ \Carbon\Carbon::parse($filters['end_date'])->format('d/m/Y') }}
            @else
                Histórico completo (Sin filtros de fecha aplicados)
            @endif
            | <strong>Fuerza operativa evaluada:</strong> {{ count($consultants) }} Consultores
        </div>
    </div>

    <table class="stats-table">
        <thead>
            <tr>
                <th class="text-left" style="width: 35%;">Consultor CSPE</th>
                <th style="width: 15%;">Total Asignados</th>
                <th style="width: 15%;">En Proceso / Activos</th>
                <th style="width: 15%;">Completados (Cerrados)</th>
                <th style="width: 15%;">Detenidos / Cancelados</th>
            </tr>
        </thead>
        <tbody>
            @forelse($consultants as $consultant)
                <tr>
                    <td class="text-left">
                        {{ $consultant['first_name'] }} {{ $consultant['last_name'] }}
                    </td>

                    <!-- TOTAL ASIGNADOS -->
                    <td>
                        <span class="highlight-badge"
                            style="background-color: #cce5ff; color: #004085; border-color: #b8daff;">
                            {{ $consultant['total_asignados'] }}
                        </span>
                    </td>

                    <!-- EN PROCESO (CON DETALLES) -->
                    <td style="vertical-align: top; padding-top: 10px;">
                        <span class="highlight-badge"
                            style="background-color: #fff3cd; color: #856404; border-color: #ffeeba; margin-bottom: 8px; display: inline-block;">
                            {{ $consultant['req_en_proceso'] }}
                        </span>

                        @if (count($consultant['active_details']) > 0)
                            <div style="text-align: left; margin-top: 8px; font-size: 8.5px; line-height: 1.3;">
                                @foreach ($consultant['active_details'] as $req)
                                    <div
                                        style="margin-bottom: 3px; border-bottom: 1px dashed #e9ecef; padding-bottom: 2px;">
                                        <strong>{{ $req->rrti }}</strong> <br>
                                        <span style="color: #0056b3;">Avance:
                                            {{ number_format($req->progress_percentage, 0) }}%</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </td>

                    <!-- COMPLETADOS (CON DETALLES) -->
                    <td style="vertical-align: top; padding-top: 10px;">
                        <span class="highlight-badge"
                            style="background-color: #d4edda; color: #155724; border-color: #c3e6cb; margin-bottom: 8px; display: inline-block;">
                            {{ $consultant['req_completados'] }}
                        </span>

                        @if (count($consultant['completed_details']) > 0)
                            <div style="text-align: left; margin-top: 8px; font-size: 8px; line-height: 1.2;">
                                @foreach ($consultant['completed_details'] as $req)
                                    <span
                                        style="display: inline-block; background: #e2e3e5; padding: 2px 4px; border-radius: 3px; margin: 1px;">
                                        {{ $req->rrti }}
                                    </span>
                                @endforeach
                            </div>
                        @endif
                    </td>

                    <!-- DETENIDOS -->
                    <td>{{ $consultant['req_detenidos'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" style="text-align: center; padding: 25px; font-style: italic; color: #777;">
                        No se encontraron registros de asignación para los consultores CSPE en el período evaluado.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endsection
