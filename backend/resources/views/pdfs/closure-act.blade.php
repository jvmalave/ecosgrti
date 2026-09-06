<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Acta de Cierre de Requerimiento</title>
    <style>
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 11pt;
            line-height: 1.5;
            color: #333;
        }

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

        .mt-4 {
            margin-top: 20px;
        }

        /* Marca de Agua para la Fase 1 (Borrador) */
        .watermark {
            position: fixed;
            top: 35%;
            left: 10%;
            font-size: 65px;
            color: rgba(255, 0, 0, 0.15);
            transform: rotate(-45deg);
            z-index: -1;
            white-space: nowrap;
        }

        .section-title {
            font-weight: bold;
            text-transform: uppercase;
            border-bottom: 1px solid #000;
            margin-top: 20px;
            margin-bottom: 10px;
        }

        .firma-box {
            margin-top: 50px;
            text-align: center;
            width: 300px;
        }

        .firma-line {
            border-top: 1px solid #000;
            margin-bottom: 5px;
        }
    </style>
</head>

<body>

    <!-- Renderizado condicional de la marca de agua -->
    @if ($is_draft)
        <div class="watermark">BORRADOR - SIN VALIDEZ</div>
    @endif

    <h2 class="text-center">ACTA DE CIERRE DE REQUERIMIENTO</h2>
    <p class="text-center mb-2">
        <strong>Fecha de emisión:</strong> {{ $generated_at }}<br>
        <strong>Código / Número de Requerimiento:</strong> {{ $requirement->rrti }}
    </p>

    <div class="section-title">1. DATOS GENERALES</div>
    <ul>
        <li><strong>Consultor Funcional:</strong>
            {{ $requirement->functionalConsultant->person->first_name ?? '' }}
            {{ $requirement->functionalConsultant->person->last_name ?? '' }}
        </li>
        <li><strong>Unidad Solicitante:</strong> {{ $requirement->snapshot_unit_name ?? 'N/A' }}</li>
        <li><strong>Consultores CSPE:</strong>
            @forelse(collect($requirement->cspeConsultants ?? []) as $consultant)
                {{ $consultant->person->first_name ?? '' }} {{ $consultant->person->last_name ?? '' }}@if (!$loop->last)
                    ,
                @endif
                @empty
                    No asignado
                @endforelse
            </li>
            <li><strong>Fecha de inicio del requerimiento:</strong>
                {{ \Carbon\Carbon::parse($requirement->creation_date)->format('d/m/Y') }}</li>
            <li><strong>Fecha de finalización:</strong> {{ \Carbon\Carbon::parse($completion_date)->format('d/m/Y') }}</li>
        </ul>

        <div class="section-title">2. TIPOLOGÍA</div>
        <ul>
            <li><strong>Tipo de Requerimiento:</strong> {{ $requirement->requirement_type ?? 'N/A' }}</li>
            <li><strong>Tipo de Gestión:</strong> {{ $requirement->management_type ?? 'N/A' }}</li>
        </ul>

        <div class="section-title">3. DESCRIPCIÓN DEL REQUERIMIENTO</div>
        <p class="text-justify">{{ $requirement->description ?? 'Sin descripción detallada.' }}</p>

        <div class="section-title">4. ACUERDOS ALCANZADOS (ATF)</div>
        <p>Se listan acuerdos acordados con la unidad solicitante (Consultor Funcional):</p>
        <ul>
            @forelse(collect($requirement->atfAgreements ?? []) as $agreement)
                <li>Acuerdo {{ $loop->iteration }}: {{ $agreement->description ?? '' }}</li>
            @empty
                <li>No se registraron acuerdos específicos.</li>
            @endforelse
        </ul>

        <div class="section-title">5. COMPONENTES</div>

        <!-- Lógica para mostrar Roles solo si es 'Roles' o 'Mixto' -->
        @if (in_array($requirement->management_type, ['Roles', 'Mixto']))
            <p class="fw-bold mb-0">Roles Intervenidos</p>
            <ul>
                @forelse(collect($requirement->roles ?? []) as $rol)
                    <li>Rol {{ $loop->iteration }}: {{ $rol->role_name ?? 'N/A' }}.</li>
                @empty
                    <li>No se registraron roles.</li>
                @endforelse
            </ul>
        @endif

        <!-- Lógica para mostrar Entregables solo si es 'Entregables' o 'Mixto' -->
        @if (in_array($requirement->management_type, ['Entregables', 'Mixto']))
            <p class="fw-bold mb-0">Entregables</p>
            <ul>
                @forelse(collect($requirement->deliverables ?? []) as $deliverable)
                    <li>Entregable {{ $loop->iteration }}: {{ $deliverable->name ?? 'N/A' }}.</li>
                @empty
                    <li>No se registraron entregables.</li>
                @endforelse
            </ul>
        @endif

        <div class="section-title">6. GESTIÓN DEL REQUERIMIENTO</div>
        <p class="text-justify">

            <!-- Párrafo exclusivo para el flujo de Roles -->
            @if (in_array($requirement->management_type, ['Roles', 'Mixto']))
                Para cada rol intervenido, se completaron satisfactoriamente las etapas del ciclo de vida: <strong>Diseño
                    Técnico, Construcción, Pruebas Integrales, Certificación, Pase a Producción y Asignación al Usuario
                    final.</strong><br><br>
            @endif

            <!-- Párrafo exclusivo para el flujo de Entregables -->
            @if (in_array($requirement->management_type, ['Entregables', 'Mixto']))
                Para cada Entregable creado, se completaron satisfactoriamente las etapas del ciclo de vida:
                <strong>Construcción y Certificación.</strong>
            @endif
        </p>

        <div class="section-title">7. ACEPTACIÓN Y CIERRE</div>
        <p class="text-justify">
            Por medio de la presente acta, la Coordinación Seguridad Portales y Escritorios (CSPE) declara que ha
            <strong>CULMINADO Y ENTREGADO</strong> el requerimiento {{ $requirement->rrti }} a entera satisfacción de la
            Unidad Solicitante {{ $requirement->snapshot_unit_name ?? '' }}, dándose por <strong>CERRADO</strong>
            formalmente el caso en el Sistema de Gestión de Requerimientos TI.
        </p>

        <!-- FIRMAS -->
        <div class="firma-box">
            <div class="firma-line"></div>
            <strong>Coordinación Seguridad Portales y Escritorios (CSPE)</strong><br>
            Nombre: {{ auth()->user()->name ?? 'Coordinador CSPE' }}<br>
            Cargo: Coordinador
        </div>

    </body>

    </html>
