@extends('reporting::layouts.master')

@section('title', 'Bitácora de Auditoría')

@section('content')
    <style>
        .audit-header {
            margin-bottom: 12px;
            border-bottom: 1px solid #0056b3;
            padding-bottom: 5px;
        }

        .audit-title {
            font-size: 14px;
            color: #0056b3;
            font-weight: bold;
            text-align: center;
            text-transform: uppercase;
            margin: 0 0 6px 0;
        }

        .filter-summary {
            font-size: 9px;
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            padding: 5px 8px;
            color: #495057;
        }

        .audit-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8.5px;
            margin-top: 10px;
            table-layout: fixed;
            /* OBLIGA a DomPDF a respetar estrictamente los anchos */
        }

        .audit-table th {
            background-color: #0056b3;
            color: #ffffff;
            font-weight: bold;
            padding: 5px 4px;
            text-align: left;
            border: 1px solid #004494;
        }

        .audit-table td {
            padding: 4px;
            border: 1px solid #dee2e6;
            vertical-align: top;
            color: #333333;
            word-wrap: break-word;
            /* Permite cortar palabras largas si es estrictamente necesario */
        }

        .audit-table tbody tr:nth-child(even) {
            background-color: #fdfdfd;
        }

        .code-box {
            font-family: monospace;
            font-size: 7px;
            /* Reducido ligeramente para acomodar la indentación */
            color: #555555;
            white-space: pre-wrap;
            /* Respeta los saltos de línea generados por PRETTY_PRINT */
        }
    </style>

    <div class="audit-header">
        <div class="audit-title">Bitácora de Auditoría y Trazabilidad Transaccional</div>
        <div class="filter-summary">
            <strong>Filtros aplicados:</strong>
            Período: {{ \Carbon\Carbon::parse($filters['start_date'])->format('d/m/Y') }} al
            {{ \Carbon\Carbon::parse($filters['end_date'])->format('d/m/Y') }}
            @if (!empty($filters['action']))
                | <strong>Acción:</strong> {{ $filters['action'] }}
            @endif
            @if (!empty($filters['rrti']))
                | <strong>RRTI:</strong> {{ $filters['rrti'] }}
            @endif
            | <strong>Total registros:</strong> {{ $logs->count() }}
        </div>
    </div>

    <table class="audit-table">
        <thead>
            <tr>
                <!-- Distribución matemática balanceada (Total: 100%) -->
                <th style="width: 10%;">Fecha / Hora</th>
                <th style="width: 12%;">Usuario</th>
                <th style="width: 13%;">Acción</th>
                <th style="width: 25%;">Descripción</th>
                <th style="width: 15%;">Target ID</th>
                <th style="width: 25%;">Payload</th>
            </tr>
        </thead>
        <tbody>
            @forelse($logs as $log)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($log->created_at)->format('d/m/Y H:i:s') }}</td>
                    <td>
                        {{ $log->user->person->first_name ?? ($log->user->name ?? 'Sistema') }}
                        {{ $log->user->person->last_name ?? '' }}
                    </td>
                    <td><strong>{{ $log->action }}</strong></td>
                    <td>{{ $log->description ?? 'N/A' }}</td>
                    <td style="font-family: monospace; font-size: 7.5px;">{{ $log->target_id ?? 'N/A' }}</td>
                    <td class="code-box">
                        @if (!empty($log->payload))
                            <!-- PRETTY_PRINT formatea el JSON con saltos de línea para que quepa en la columna -->
                            {{ json_encode($log->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}
                        @else
                            <span style="color: #999;">Vacío</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" style="text-align: center; padding: 20px; font-style: italic; color: #777;">
                        No se encontraron eventos registrados para el rango y parámetros especificados.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endsection
