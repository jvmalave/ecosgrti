@extends('reporting::layouts.master')

@section('title', 'Trazabilidad de Componente (Rol o Entregable)')

@section('content')
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 12px;
            color: #333;
        }

        .header {
            border-bottom: 2px solid #0056b3;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }

        .header h3 {
            margin: 0;
            color: #0056b3;
        }

        .meta-table {
            width: 100%;
            margin-bottom: 25px;
            border-collapse: collapse;
        }

        .meta-table td {
            padding: 5px;
            font-size: 11px;
            border-bottom: 1px solid #eee;
        }

        .meta-label {
            font-weight: bold;
            color: #555;
            width: 120px;
        }

        .phase-title {
            background-color: #f8f9fa;
            color: #1a237e;
            padding: 8px;
            margin-top: 20px;
            margin-bottom: 10px;
            border-left: 4px solid #aa00ff;
            font-weight: bold;
            font-size: 13px;
        }

        .timeline-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }

        .timeline-table th {
            background-color: #e9ecef;
            text-align: left;
            padding: 6px;
            font-size: 10px;
            color: #555;
        }

        .timeline-table td {
            padding: 8px;
            border-bottom: 1px solid #eee;
            vertical-align: top;
            font-size: 11px;
        }

        .details-text {
            color: #666;
            font-size: 10px;
            margin-top: 4px;
            display: block;
        }

        .support-text {
            color: #00838f;
            font-size: 10px;
            margin-top: 4px;
            display: block;
            font-style: italic;
        }
    </style>

    <body>
        <div class="header uppercase">
            <h3>Trazabilidad de Componente (Rol o Entregable)</h3>
        </div>

        <!-- Metadatos del Componente -->
        <table class="meta-table">
            <tr>
                <td class="meta-label">Componente:</td>
                <td><strong>{{ $data['component_name'] }}</strong></td>
                <td class="meta-label">RRTI:</td>
                <td>{{ $data['rrti'] }}</td>
            </tr>
            <tr>
                <td class="meta-label">Consultor Funcional:</td>
                <td>{{ $data['functional_consultant'] ?? 'Sin asignar' }}</td>
                <td class="meta-label">Estatus Req:</td>
                <td>
                    @if ($data['req_status'] == 'RF')
                        Cerrado
                    @else
                        {{ $data['req_status'] }}
                    @endif
                </td>
            </tr>
        </table>

        <!-- Línea de Tiempo Agrupada -->
        @if (count($data['groupedTimeline']) > 0)
            @foreach ($data['groupedTimeline'] as $group)
                <div class="phase-title">{{ $group['phase'] }}</div>

                <table class="timeline-table">
                    <thead>
                        <tr>
                            <th style="width: 15%;">Fecha</th>
                            <th style="width: 35%;">Acción</th>
                            <th style="width: 30%;">Detalles</th>
                            <th style="width: 20%;">Ejecutor</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($group['entries'] as $event)
                            <tr>
                                <!-- Formateo de fecha asumiendo ISO (Angular envia ISO string) -->
                                <td>{{ \Carbon\Carbon::parse($event['date'])->format('d/m/Y') }}</td>
                                <td>
                                    <strong>{{ $event['action'] }}</strong>
                                    {{-- @if (!empty($event['support_filename']))
                                        <span class="support-text"><img src="" alt="📎"> Soporte:
                                            {{ $event['support_filename'] }}</span>
                                    @endif --}}
                                </td>
                                <td><span class="details-text">{{ $event['details'] }}</span></td>
                                <td>{{ $event['actor'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endforeach
        @else
            <p style="text-align: center; color: #999; font-style: italic; margin-top: 50px;">
                No existen registros en la bitácora para este componente.
            </p>
        @endif

    </body>

@endsection
