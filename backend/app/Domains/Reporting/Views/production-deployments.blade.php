@extends('reporting::layouts.master')

@section('title', 'Histórico de Pases a Producción')

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

        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9px;
            /* Fuente más pequeña por la densidad de datos */
            margin-top: 15px;
            table-layout: fixed;
        }

        .data-table th {
            background-color: #0056b3;
            color: #ffffff;
            font-weight: bold;
            padding: 6px;
            text-align: left;
            border: 1px solid #004494;
            text-transform: uppercase;
        }

        .data-table td {
            padding: 6px;
            border: 1px solid #dee2e6;
            color: #333333;
            vertical-align: top;
        }

        .data-table tbody tr.main-row:nth-child(even) {
            background-color: #f8f9fa;
        }

        .badge {
            display: inline-block;
            padding: 2px 5px;
            border-radius: 3px;
            font-weight: bold;
            font-size: 8.5px;
        }

        .badge-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .badge-warning {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }

        .badge-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .role-list {
            margin: 4px 0 0 0;
            padding-left: 12px;
        }

        .role-list li {
            margin-bottom: 2px;
        }

        .rollback-box {
            background-color: #fff5f5;
            border-left: 3px solid #dc3545;
            padding: 5px;
            margin-bottom: 4px;
            font-size: 8.5px;
        }

        .rollback-box strong {
            color: #dc3545;
        }
    </style>

    <div class="report-header">
        <div class="report-title">Histórico y Evolución de Pases a Producción</div>
        <div class="filter-summary">
            <strong>Parámetros de Medición:</strong>
            @if (!empty($filters['start_date']) && !empty($filters['end_date']))
                Período evaluado del {{ \Carbon\Carbon::parse($filters['start_date'])->format('d/m/Y') }} al
                {{ \Carbon\Carbon::parse($filters['end_date'])->format('d/m/Y') }}
            @else
                Histórico completo (Sin filtros de fecha)
            @endif

            @if (!empty($filters['rrti']))
                | <strong>RRTI:</strong> {{ $filters['rrti'] }}
            @endif

            | <strong>Total Registros:</strong> {{ count($deployments) }} requerimiento(s)
        </div>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 12%;">RRTI / Fecha PAP</th>
                <th style="width: 28%;">Descripción del Requerimiento</th>
                <th style="width: 25%;">Evolución de Roles Técnicos</th>
                <th style="width: 35%;">Historial de Rollbacks (Rechazos)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($deployments as $deployment)
                <tr class="main-row">
                    <!-- RRTI y Fecha -->
                    <td>
                        <strong style="font-size: 11px; color: #0056b3;">{{ $deployment['rrti'] }}</strong><br>
                        <span style="color: #6c757d; font-size: 8px;">Estado actual:
                            {{ $deployment['current_status'] }}</span><br><br>
                        <strong>Referencia PAP:</strong><br>
                        {{ $deployment['pap_c_date'] ? \Carbon\Carbon::parse($deployment['pap_c_date'])->format('d/m/Y H:i') : 'Sin cierre (PAP-C)' }}
                    </td>
                    <!-- Descripción -->
                    <td>
                        {{ Str::limit($deployment['description'], 150) }}
                    </td>
                    <!-- Roles -->
                    <td>
                        @if ($deployment['is_fully_deployed'])
                            <span class="badge badge-success">¡Despliegue Completo!
                                ({{ $deployment['roles_in_prod'] }}/{{ $deployment['total_roles'] }})
                            </span>
                        @else
                            <span class="badge badge-warning">En Proceso
                                ({{ $deployment['roles_in_prod'] }}/{{ $deployment['total_roles'] }} en Prod)</span>
                        @endif

                        @if (count($deployment['roles_details']) > 0)
                            <ul class="role-list">
                                @foreach ($deployment['roles_details'] as $role)
                                    <li style="margin-bottom: 8px;"> <!-- Mayor margen inferior entre roles -->
                                        <div>
                                            <strong>{{ $role['role_name'] }}:</strong>
                                            @if ($role['current_status'] === 'IN_PRODUCTION')
                                                <br><span style="color: #28a745; font-weight: bold;">Producción</span>
                                            @else
                                                <span style="color: #dc3545;">{{ $role['current_status'] }}</span>
                                            @endif
                                            <span style="color: #6c757d; font-size: 7.5px;">(Intentos:
                                                {{ $role['attempts'] }})</span>
                                        </div>

                                        @if ($role['order_number'])
                                            <div
                                                style="color: #0056b3; font-size: 8.5px; padding-left: 10px; margin-top: 2px;">
                                                <i class="fas fa-truck-fast"></i> Orden:
                                                <strong>{{ $role['order_number'] }}</strong>
                                                <span style="color: #6c757d;">
                                                    ({{ \Carbon\Carbon::parse($role['order_date'])->format('d/m/Y') }})
                                                </span>
                                            </div>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <div style="margin-top: 5px; font-style: italic; color: #777;">Sin roles técnicos definidos.
                            </div>
                        @endif
                    </td>
                    <!-- Rollbacks -->
                    <td>
                        @if (count($deployment['rollbacks']) > 0)
                            @foreach ($deployment['rollbacks'] as $rollback)
                                <div class="rollback-box">
                                    <strong>[Orden: {{ $rollback['order_number'] ?? 'N/A' }}]</strong>
                                    Rol: {{ $rollback['role_name'] ?? 'N/A' }} <br>

                                    <em>Motivo:</em> {{ $rollback['reason'] ?? 'Sin justificación registrada' }} <br>

                                    <div style="margin-top: 3px; font-size: 7.5px; color: #6c757d;">
                                        <i class="fas fa-user-times"></i> Rechazado por:
                                        {{ $rollback['rejected_by'] ?? 'Sistema' }}
                                        <span style="float: right;">
                                            {{ !empty($rollback['rejected_at']) ? \Carbon\Carbon::parse($rollback['rejected_at'])->format('d/m/Y H:i') : '' }}
                                        </span>
                                    </div>
                                </div>
                            @endforeach
                        @else
                            <span style="color: #28a745; font-style: italic;">
                                <i class="fas fa-check-circle"></i> Despliegue limpio, sin historial de rechazos.
                            </span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" style="text-align: center; padding: 30px; font-style: italic; color: #777;">
                        No se encontraron registros de pases a producción en el período y filtros evaluados.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endsection
