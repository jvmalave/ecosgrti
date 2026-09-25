@extends('reporting::layouts.master')

@section('title', 'Resumen Ejecutivo Integral')

@section('content')
    <style>
        .kpi-container {
            width: 100%;
            margin-bottom: 20px;
            border-collapse: collapse;
        }

        .kpi-card {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-top: 3px solid #0056b3;
            padding: 10px;
            text-align: center;
            width: 25%;
        }

        .kpi-value {
            font-size: 18px;
            font-weight: bold;
            color: #0056b3;
            margin-top: 5px;
        }

        .kpi-label {
            font-size: 9px;
            color: #6c757d;
            text-transform: uppercase;
            font-weight: bold;
        }

        .section-title {
            font-size: 12px;
            color: #0056b3;
            font-weight: bold;
            border-bottom: 1px solid #0056b3;
            padding-bottom: 4px;
            margin-top: 30px;
            margin-bottom: 10px;
            text-transform: uppercase;
        }

        .table-summary {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
            margin-bottom: 15px;
        }

        .table-summary th {
            background-color: #0056b3;
            color: #ffffff;
            padding: 6px;
            text-align: left;
            border: 1px solid #004494;
        }

        .table-summary td {
            padding: 6px;
            border: 1px solid #dee2e6;
            color: #333333;
            vertical-align: middle;
        }

        .bar-container {
            background-color: #e9ecef;
            border-radius: 3px;
            width: 100%;
            height: 12px;
            overflow: hidden;
        }

        .bar-fill {
            background-color: #0056b3;
            height: 12px;
            border-radius: 3px;
        }

        .alert-row td {
            background-color: #fef2f2;
            color: #991b1b !important;
            font-weight: bold;
        }

        .chart-box {
            border: 1px solid #dee2e6;
            padding: 4px;
            background: #fff;
            border-radius: 4px;
            text-align: center;
            margin-bottom: 10px;
        }
    </style>

    <div style="text-align: center; margin-bottom: 15px;">
        <h3 style="color: #0056b3; margin: 0; font-size: 14px; text-transform: uppercase;">Resumen Ejecutivo Integral del
            Portafolio</h3>
        <span style="font-size: 9px; color: #6c757d;">
            @if (!empty($filters['start_date']) && !empty($filters['end_date']))
                Período: {{ $filters['start_date'] }} al {{ $filters['end_date'] }}
            @else
                Período: Histórico Global Acumulado
            @endif
        </span>
    </div>

    <!-- Tarjetas KPI Operativas -->
    <table class="kpi-container">
        <tr>
            <td class="kpi-card">
                <div class="kpi-label">Total Requerimientos</div>
                <div class="kpi-value">{{ $metrics['total_requirements'] }}</div>
            </td>
            <td style="width: 2%;"></td>
            <td class="kpi-card">
                <div class="kpi-label">Completados / Cerrados</div>
                <div class="kpi-value" style="color: #28a745;">{{ $metrics['completed_count'] }}</div>
            </td>
            <td style="width: 2%;"></td>
            <td class="kpi-card">
                <div class="kpi-label">En Proceso / Activos</div>
                <div class="kpi-value" style="color: #ffc107;">{{ $metrics['in_progress_count'] }}</div>
            </td>
            <td style="width: 2%;"></td>
            <td class="kpi-card">
                <div class="kpi-label">Índice de Eficiencia</div>
                <div class="kpi-value">{{ $metrics['efficiency_rate'] }}%</div>
            </td>
        </tr>
    </table>

    <div class="section-title">Distribución por Tipo de Requerimiento</div>
    <table class="table-summary">
        <thead>
            <tr>
                <th style="width: 30%;">Tipo de Requerimiento</th>
                <th style="width: 50%;">Proporción Gráfica</th>
                <th style="width: 20%; text-align: center;">Cantidad</th>
            </tr>
        </thead>
        <tbody>
            @php $totalReqs = $metrics['total_requirements'] > 0 ? $metrics['total_requirements'] : 1; @endphp
            @forelse($metrics['by_type'] as $type => $count)
                @php $percentage = round(($count / $totalReqs) * 100); @endphp
                <tr>
                    <td><strong>{{ $type }}</strong></td>
                    <td>
                        <div class="bar-container">
                            <div class="bar-fill" style="width: {{ $percentage }}%;"></div>
                        </div>
                    </td>
                    <td style="text-align: center; font-weight: bold;">{{ $count }} <span
                            style="font-size: 8px; color: #6c757d;">({{ $percentage }}%)</span></td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" style="text-align: center; font-style: italic; color: #777;">Sin registros para el
                        período.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="section-title">Distribución por Estatus Actual</div>
    <table class="table-summary">
        <thead>
            <tr>
                <th style="width: 45%;">Código de Estatus</th>
                <th style="width: 35%;">Proporción Gráfica</th>
                <th style="width: 20%; text-align: center;">Total Requerimientos</th>
            </tr>
        </thead>
        <tbody>
            @php
                $i = 0;
                $totalReqs = $metrics['total_requirements'] > 0 ? $metrics['total_requirements'] : 1;
            @endphp
            @forelse($metrics['by_status'] as $status => $count)
                @php
                    $percentage = round(($count / $totalReqs) * 100);
                    $currentColor = $assignedColors[$i] ?? '#0056b3';
                    $i++;
                @endphp
                <tr>
                    <td>
                        <span
                            style="display: inline-block; width: 10px; height: 10px; background-color: {{ $currentColor }}; margin-right: 5px; border-radius: 2px;"></span>
                        <strong style="color: #333;">{{ $status }}</strong>
                    </td>
                    <td>
                        <div class="bar-container">
                            <div class="bar-fill"
                                style="width: {{ $percentage }}%; background-color: {{ $currentColor }};"></div>
                        </div>
                    </td>
                    <td style="text-align: center; font-weight: bold;">
                        {{ $count }} <span style="font-size: 8px; color: #6c757d;">({{ $percentage }}%)</span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" style="text-align: center; font-style: italic; color: #777;">Sin registros para el
                        período.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- Gráficas Operativas -->
    <table style="width: 100%; margin-top: 25px; border-collapse: collapse;">
        <tr>
            <td style="width: 40%; vertical-align: top; padding-right: 5px;">
                <div class="chart-box">
                    <img src="{{ $chartPieBase64 }}" style="width: 100%; max-height: 180px;"
                        alt="Gráfica Circular Eficiencia">
                </div>
            </td>
            <td style="width: 60%; vertical-align: top; padding-left: 5px;">
                <div class="chart-box">
                    <img src="{{ $chartBarBase64 }}" style="width: 100%; max-height: 180px;"
                        alt="Gráfica Ejecutiva de Estatus">
                </div>
            </td>
        </tr>
    </table>

    <div style="page-break-before: always;"></div>

    <!-- SECCIÓN INTELIGENCIA DE NEGOCIOS (SPRINT 3) -->
    <div class="section-title">Inteligencia de Negocios y Rendimiento Operativo</div>

    <table style="width: 100%; border-collapse: collapse;">
        <tr>
            <!-- Columna Izquierda: OTD y Desviación -->
            <td style="width: 50%; vertical-align: top; padding-right: 10px;">
                <div class="chart-box">
                    <img src="{{ $chartOtdBase64 }}" style="width: 100%; max-height: 160px;" alt="Gráfico OTD">
                    <div style="font-size: 10px; margin-top: 5px;">
                        <strong>Eficiencia (OTD):</strong> {{ $otd['otd_percentage'] ?? 0 }}% |
                        <strong>Desviación:</strong> {{ $deviation['deviation_stats']['global_average_delay_days'] ?? 0 }}
                        días
                    </div>
                </div>

                <table class="table-summary">
                    <thead>
                        <tr>
                            <th style="width: 50%;">Desglose por Fase</th>
                            <th style="width: 25%; text-align: center;">A Tiempo</th>
                            <th style="width: 25%; text-align: center;">Atrasado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @if (isset($deviation['deviation_stats']['breakdown_by_phase']))
                            @forelse($deviation['deviation_stats']['breakdown_by_phase'] as $phase => $stats)
                                <tr>
                                    <td><strong>{{ $phase }}</strong></td>
                                    <td style="text-align: center;">{{ $stats['on_time_count'] }}</td>
                                    <td
                                        style="text-align: center; color: {{ $stats['delayed_count'] > 0 ? '#dc3545' : '#333' }};">
                                        {{ $stats['delayed_count'] }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" style="text-align: center;">Sin fases evaluadas.</td>
                                </tr>
                            @endforelse
                        @else
                            <tr>
                                <td colspan="3" style="text-align: center;">Sin datos de desviación.</td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </td>

            <!-- Columna Derecha: Aging y Alertas Críticas -->
            <td style="width: 50%; vertical-align: top; padding-left: 10px;">
                <div class="chart-box">
                    <img src="{{ $chartAgingBase64 }}" style="width: 100%; max-height: 160px;" alt="Gráfico Aging">
                    <div style="font-size: 10px; margin-top: 5px;">
                        <strong>Requerimientos Activos:</strong> {{ $aging['summary']['total_open_requirements'] ?? 0 }} |
                        <strong>Edad Media:</strong> {{ $aging['summary']['average_global_age'] ?? 0 }} días
                    </div>
                </div>

                <table class="table-summary">
                    <thead>
                        <tr>
                            <th style="width: 30%;">RRTI (+30 Días)</th>
                            <th style="width: 45%;">Fase Actual</th>
                            <th style="width: 25%; text-align: center;">Días Activo</th>
                        </tr>
                    </thead>
                    <tbody>
                        @if (isset($aging['critical_requirements']))
                            @forelse(array_slice($aging['critical_requirements'], 0, 7) as $critical)
                                <tr class="{{ $critical['is_stuck'] ? 'alert-row' : '' }}">
                                    <td>{{ $critical['rrti'] }}</td>
                                    <td style="font-size: 9px;">{{ $critical['current_phase'] }}</td>
                                    <td style="text-align: center; font-weight: bold;">{{ $critical['global_days_open'] }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" style="text-align: center;">Sin requerimientos críticos.</td>
                                </tr>
                            @endforelse
                        @else
                            <tr>
                                <td colspan="3" style="text-align: center;">Sin datos de envejecimiento.</td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </td>
        </tr>
    </table>
@endsection
