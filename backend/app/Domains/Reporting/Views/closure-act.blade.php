@extends('reporting::layouts.master')

@section('title', $isClosed ? 'Acta de Cierre' : 'Documento de Seguimiento')

@section('content')
    <style>
        .text-center {
            text-align: center;
        }

        .text-justify {
            text-align: justify;
        }

        .fw-bold {
            font-weight: bold;
        }

        .mb-2 {
            margin-bottom: 10px;
        }

        .mb-0 {
            margin-bottom: 0px;
        }

        ul {
            font-size: 11px;
            margin-top: 5px;
            color: #444;
        }

        p {
            font-size: 11px;
            color: #444;
        }

        .section-title {
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            border-bottom: 1px solid #0056b3;
            color: #0056b3;
            margin-top: 20px;
            margin-bottom: 10px;
            padding-bottom: 3px;
        }

        .firma-box {
            margin-top: 40px;
            text-align: center;
            width: 300px;
            margin-left: auto;
            margin-right: auto;
            font-size: 11px;
            color: #333;
        }

        .firma-line {
            border-top: 1px solid #333;
            margin-bottom: 5px;
        }

        .info-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
            margin-bottom: 20px;
        }

        .info-table td {
            padding: 6px;
            border: 1px solid #ddd;
        }

        .info-table .bg-gray {
            background-color: #f4f7f6;
            font-weight: bold;
            width: 20%;
        }

        .phase-block {
            background-color: #f9f9f9;
            border: 1px solid #eee;
            padding: 6px;
            margin-bottom: 6px;
        }

        .phase-title {
            font-size: 11px;
            color: #333;
            font-weight: bold;
        }

        .phase-date {
            float: right;
            color: #666;
            font-size: 10px;
        }

        .component-list {
            margin-top: 4px;
            margin-bottom: 0;
            padding-left: 20px;
            list-style-type: none;
        }

        .component-list li {
            margin-bottom: 3px;
            font-size: 11px;
            color: #444;
        }

        .component-date {
            color: #555;
            font-style: italic;
            font-size: 10px;
        }

        .watermark {
            position: fixed;
            top: 45%;
            left: 10%;
            font-size: 55px;
            color: rgba(255, 0, 0, 0.15);
            transform: rotate(-45deg);
            z-index: -1000;
            white-space: nowrap;
        }
    </style>

    @if (isset($is_draft) && $is_draft)
        <div class="watermark">BORRADOR - SIN VALIDEZ</div>
    @endif

    <h2 class="text-center" style="color: #0056b3; font-size: 16px; margin: 0;">
        {{ $isClosed ? 'ACTA DE ACEPTACIÓN Y CIERRE DE REQUERIMIENTO' : 'DOCUMENTO DE SEGUIMIENTO DE REQUERIMIENTO' }}
    </h2>
    <p class="text-center mb-2" style="font-size: 11px;">
        <strong>Código de Control RRTI:</strong> {{ $requirement->rrti }}
    </p>

    <!-- 1. DATOS GENERALES -->
    <div class="section-title">1. DATOS GENERALES</div>
    <table class="info-table">
        <tbody>
            <tr>
                <td class="bg-gray">Sociedad</td>
                <td style="width: 30%;">{{ $requirement->snapshot_society_name ?? 'N/A' }}</td>
                <td class="bg-gray">Fecha de Solicitud</td>
                <td style="width: 30%;">
                    {{ $requirement->creation_date ? \Carbon\Carbon::parse($requirement->creation_date)->format('d/m/Y') : 'N/A' }}
                </td>
            </tr>
            <tr>
                <td class="bg-gray">Sistema Afectado</td>
                <td>{{ $requirement->snapshot_system_name ?? 'N/A' }}</td>
                <td class="bg-gray">Fecha de Cierre</td>
                <td>{{ $isClosed ? \Carbon\Carbon::parse($completion_date)->format('d/m/Y') : 'N/A' }}</td>
            </tr>
            <tr>
                <td class="bg-gray">Unidad Solicitante</td>
                <td>{{ $requirement->snapshot_unit_name ?? 'N/A' }}</td>
                <td class="bg-gray">Tipo de Gestión</td>
                <td>{{ $requirement->management_type ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td class="bg-gray">Consultor Funcional</td>
                <td>
                    {{ $requirement->functionalConsultant->person->first_name ?? '' }}
                    {{ $requirement->functionalConsultant->person->last_name ?? '' }}
                </td>
                <td class="bg-gray">Consultor(es) CSPE</td>
                <td>
                    @forelse(collect($requirement->cspeConsultants ?? []) as $consultant)
                        {{ $consultant->person->first_name ?? '' }} {{ $consultant->person->last_name ?? '' }}@if (!$loop->last)
                            ,
                        @endif
                        @empty
                            No asignado
                        @endforelse
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- 2. TIPOLOGÍA -->
        <div class="section-title">2. TIPOLOGÍA</div>
        <ul>
            <li><strong>Tipo de Requerimiento:</strong> {{ $requirement->requirement_type ?? 'N/A' }}</li>
            <li><strong>Tipo de Gestión:</strong> {{ $requirement->management_type ?? 'N/A' }}</li>
        </ul>

        <!-- 3. DESCRIPCIÓN -->
        <div class="section-title">3. DESCRIPCIÓN DEL REQUERIMIENTO</div>
        <div
            style="font-size: 11px; color: #444; border: 1px solid #ddd; padding: 10px; background-color: #fafafa; margin-bottom: 10px; text-align: justify;">
            {{ $requirement->description ?? 'Sin descripción detallada.' }}
        </div>

        <!-- 4. ACUERDOS ALCANZADOS -->
        <div class="section-title">4. ACUERDOS ALCANZADOS (ATF)</div>
        @if (collect($requirement->atfAgreements ?? [])->isNotEmpty())
            <p class="mb-0">Se listan acuerdos acordados con la unidad solicitante (Consultor Funcional):</p>
            <ul>
                @foreach ($requirement->atfAgreements as $agreement)
                    <li>Acuerdo {{ $loop->iteration }}: {{ $agreement->description ?? '' }}</li>
                @endforeach
            </ul>
        @else
            <ul>
                <li>{{ $isClosed ? 'No se registraron acuerdos específicos.' : 'Aún no se han alcanzado acuerdos en este requerimiento.' }}
                </li>
            </ul>
        @endif

        <!-- 5. COMPONENTES INTERVENIDOS -->
        <div class="section-title">5. COMPONENTES INTERVENIDOS</div>
        @if (in_array($requirement->management_type, ['Roles', 'Mixto']))
            <p class="fw-bold mb-0">Roles Intervenidos</p>
            <ul>
                @forelse(collect($requirement->roles ?? []) as $rol)
                    <li>Rol {{ $loop->iteration }}: {{ $rol->role_name ?? ($rol->name ?? 'N/A') }} | Tipo de asignación:
                        {{ $rol->assignment_type ?? 'N/A' }}</li>
                @empty
                    <li>{{ $isClosed ? 'No se registraron roles.' : 'Aún no se han definido Roles para este requerimiento.' }}
                    </li>
                @endforelse
            </ul>
        @endif

        @if (in_array($requirement->management_type, ['Entregables', 'Mixto']))
            <p class="fw-bold mb-0">Entregables Desarrollados</p>
            <ul>
                @forelse(collect($requirement->deliverables ?? []) as $deliverable)
                    <li>Entregable {{ $loop->iteration }}: {{ $deliverable->name ?? 'N/A' }}</li>
                @empty
                    <li>{{ $isClosed ? 'No se registraron entregables.' : 'Aún no se han definido Entregables para este requerimiento.' }}
                    </li>
                @endforelse
            </ul>
        @endif

        <!-- 6. GESTIÓN Y TRAZABILIDAD -->
        <div class="section-title">6. GESTIÓN Y TRAZABILIDAD DEL REQUERIMIENTO</div>

        @php
            $generalPhases = ['RC', 'ES-R', 'ATF-C'];
            $rolePhases = ['DT-C', 'COR-C', 'PI-C', 'CER-C', 'PAP-C', 'AU-C'];
            $deliverablePhases = ['COE-C', 'CEE-C'];

            // Mapeo inteligente: Definimos en qué relación buscar el componente y qué estatus exigir
            $componentLogic = [
                'DT-C' => ['status' => 'CLOSED', 'label' => 'Diseñado', 'relation' => 'dtRoles'],
                'COR-C' => ['status' => 'CLOSED', 'label' => 'Construido', 'relation' => 'corRoles'],
                'PI-C' => ['status' => 'CLOSED', 'label' => 'Aprobado', 'relation' => 'piRoles'],
                'CER-C' => ['status' => 'CERTIFIED', 'label' => 'Certificado', 'relation' => 'cerRoles'],
                'PAP-C' => ['status' => 'IN_PRODUCTION', 'label' => 'En Producción', 'relation' => 'papRoles'],
                'AU-C' => ['status' => 'ASSIGNED', 'label' => 'Asignado', 'relation' => 'auRoles'],
                'COE-C' => ['status' => 'CLOSED', 'label' => 'Construido', 'relation' => 'coeDeliverables'],
                'CEE-C' => ['status' => 'CERTIFIED', 'label' => 'Certificado', 'relation' => 'ceeDeliverables'],
            ];
        @endphp

        <!-- Trazabilidad General -->
        <div style="margin-top: 10px;">
            @foreach ($generalPhases as $code)
                @if (isset($historiesByPhase[$code]) || !$isClosed)
                    <div class="phase-block">
                        <span class="phase-title">{{ $phaseDictionary[$code] ?? $code }}</span>
                        @if (isset($historiesByPhase[$code]))
                            <span
                                class="phase-date">{{ \Carbon\Carbon::parse($historiesByPhase[$code]->created_at)->format('d/m/Y') }}</span>
                        @endif
                    </div>
                @endif
            @endforeach
        </div>

        <!-- Trazabilidad de Roles -->
        @if (in_array($requirement->management_type, ['Roles', 'Mixto']))
            <h4 style="font-size: 11px; color: #0056b3; margin-top: 15px; margin-bottom: 5px;">Flujo de Roles</h4>
            @foreach ($rolePhases as $code)
                @if (isset($historiesByPhase[$code]) || !$isClosed)
                    <div class="phase-block">
                        <span class="phase-title">{{ $phaseDictionary[$code] ?? $code }}</span>
                        @if (isset($historiesByPhase[$code]))
                            <span
                                class="phase-date">{{ \Carbon\Carbon::parse($historiesByPhase[$code]->created_at)->format('d/m/Y') }}</span>
                        @endif

                        <ul class="component-list">
                            @forelse($requirement->roles as $baseRole)
                                @php
                                    $relationName = $componentLogic[$code]['relation'] ?? null;

                                    $phaseComponent = $relationName
                                        ? collect($requirement->$relationName)->first(function ($item) use ($baseRole) {
                                            return $item->requirement_role_id === $baseRole->id ||
                                                $item->role_id === $baseRole->id ||
                                                $item->name === $baseRole->role_name;
                                        })
                                        : null;

                                    $isCompleted =
                                        $phaseComponent && $phaseComponent->status === $componentLogic[$code]['status'];
                                @endphp

                                <li>{{ $baseRole->role_name ?? ($baseRole->name ?? 'N/A') }}
                                    @if ($isCompleted)
                                        <span class="component-date">({{ $componentLogic[$code]['label'] }}:
                                            {{ $phaseComponent->updated_at ? \Carbon\Carbon::parse($phaseComponent->updated_at)->format('d/m/Y') : 'N/A' }})</span>

                                        @if ($code === 'CER-C' && !empty($requirement->cerTicket))
                                            | Ticket CSAL: {{ $requirement->cerTicket->ticket_number ?? 'N/A' }}
                                        @elseif($code === 'PAP-C' && !empty($requirement->papOrder))
                                            | Orden de Transporte: {{ $requirement->papOrder->order_number ?? 'N/A' }}
                                        @elseif($code === 'AU-C' && !empty($requirement->auTicket))
                                            | Ticket CSAL: {{ $requirement->auTicket->ticket_number ?? 'N/A' }}
                                        @endif
                                    @endif
                                </li>
                            @empty
                                <li style="color: #777; font-style: italic;">Aún no se han definido Roles para este
                                    requerimiento.</li>
                            @endforelse
                        </ul>
                    </div>
                @endif
            @endforeach
        @endif

        <!-- Trazabilidad de Entregables -->
        @if (in_array($requirement->management_type, ['Entregables', 'Mixto']))
            <h4 style="font-size: 11px; color: #0056b3; margin-top: 15px; margin-bottom: 5px;">Flujo de Entregables</h4>
            @foreach ($deliverablePhases as $code)
                @if (isset($historiesByPhase[$code]) || !$isClosed)
                    <div class="phase-block">
                        <span class="phase-title">{{ $phaseDictionary[$code] ?? $code }}</span>
                        @if (isset($historiesByPhase[$code]))
                            <span
                                class="phase-date">{{ \Carbon\Carbon::parse($historiesByPhase[$code]->created_at)->format('d/m/Y') }}</span>
                        @endif

                        <ul class="component-list">
                            @forelse($requirement->deliverables as $baseDeliverable)
                                @php
                                    $relationName = $componentLogic[$code]['relation'] ?? null;

                                    $phaseComponent = $relationName
                                        ? collect($requirement->$relationName)->firstWhere(
                                            'deliverable_id',
                                            $baseDeliverable->id,
                                        )
                                        : null;

                                    $isCompleted =
                                        $phaseComponent && $phaseComponent->status === $componentLogic[$code]['status'];
                                @endphp

                                <li>{{ $baseDeliverable->name ?? 'N/A' }}
                                    @if ($isCompleted)
                                        <span class="component-date">({{ $componentLogic[$code]['label'] }}:
                                            {{ $phaseComponent->updated_at ? \Carbon\Carbon::parse($phaseComponent->updated_at)->format('d/m/Y') : 'N/A' }})</span>

                                        @if ($code === 'CEE-C' && !empty($requirement->ceeTicket))
                                            | Ticket CEE: {{ $requirement->ceeTicket->ticket_number ?? 'N/A' }}
                                        @endif
                                    @endif
                                </li>
                            @empty
                                <li style="color: #777; font-style: italic;">Aún no se han definido Entregables para este
                                    requerimiento.</li>
                            @endforelse
                        </ul>
                    </div>
                @endif
            @endforeach
        @endif

        @if ($isClosed)
            <!-- Notificaciones de Cierre integradas a la Trazabilidad -->
            <div class="phase-block"
                style="background-color: transparent; border: none; border-top: 1px dashed #ccc; padding-top: 10px; margin-top: 15px;">
                <div style="overflow: hidden; margin-bottom: 5px;">
                    <span class="phase-title">Notificación de cierre</span>
                    <span class="phase-date"
                        style="float: right;">{{ $requirement->notification_date ? \Carbon\Carbon::parse($requirement->notification_date)->format('d/m/Y') : 'N/A' }}</span>
                </div>
                <div style="overflow: hidden;">
                    <span class="phase-title">Fin de Atención</span>
                    <span class="phase-date"
                        style="float: right;">{{ \Carbon\Carbon::parse($completion_date)->format('d/m/Y') }}</span>
                </div>
            </div>

            <!-- 7. ACEPTACIÓN Y CIERRE -->
            <div class="section-title">7. DECLARACIÓN DE ACEPTACIÓN Y CIERRE</div>
            <p class="text-justify">
                Por medio de la presente acta, la Coordinación Seguridad Portales y Escritorios (CSPE) declara que ha
                <strong>CULMINADO Y ENTREGADO</strong> el requerimiento {{ $requirement->rrti }} a entera satisfacción de la
                Unidad Solicitante ({{ $requirement->snapshot_unit_name ?? 'N/A' }}), dándose por <strong>CERRADO</strong>
                formalmente el caso en el Sistema de Gestión de Requerimientos TI.
            </p>

            <!-- FIRMA ELECTRÓNICA -->
            <div class="firma-box">
                <div class="firma-line"></div>
                <strong>Coordinación Seguridad Portales y Escritorios (CSPE)</strong><br>
                Firma Electrónica Autorizada<br>
                <span style="font-size: 9px; color: #666;">
                    Usuario: {{ auth()->user()->name ?? 'Sistema' }} | Sello: {{ date('d/m/Y H:i:s') }}
                </span>
            </div>
        @endif
    @endsection
