<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Acta de Cierre - {{ $requirement->rrti ?? 'Borrador' }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 14px;
            line-height: 1.5;
            color: #333;
            position: relative;
        }

        .header {
            text-align: center;
            border-bottom: 2px solid #d500f9;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }

        .title {
            font-size: 18px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .section-title {
            font-size: 16px;
            font-weight: bold;
            background-color: #f4f4f4;
            padding: 5px;
            margin-top: 20px;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        .data-table th,
        .data-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }

        .data-table th {
            background-color: #f9f9f9;
            width: 30%;
        }

        /* Marca de agua para el borrador */
        .watermark {
            position: absolute;
            top: 30%;
            left: 10%;
            font-size: 80px;
            color: rgba(255, 0, 0, 0.1);
            transform: rotate(-45deg);
            z-index: -1;
            white-space: nowrap;
        }
    </style>
</head>

<body>

    @if ($is_draft)
        <div class="watermark">BORRADOR SIN VALIDEZ</div>
    @endif

    <div class="header">
        <div class="title">Acta de Cierre de Requerimiento TI</div>
        <div>Generada el: {{ $generated_at }}</div>
    </div>

    <div class="section-title">1. Datos del Requerimiento</div>
    <table class="data-table">
        <tr>
            <th>RRTI</th>
            <td>{{ $requirement->rrti ?? 'N/A' }}</td>
        </tr>
        <tr>
            <th>Nombre del Proyecto</th>
            <td>{{ $requirement->name ?? 'N/A' }}</td>
        </tr>
        <tr>
            <th>Fecha de Inicio</th>
            <td>{{ $requirement->creation_date ? \Carbon\Carbon::parse($requirement->creation_date)->format('d/m/Y') : 'N/A' }}
            </td>
        </tr>
    </table>

    <div class="section-title">2. Tiempos de Cierre</div>
    <table class="data-table">
        <tr>
            <th>Fecha de Notificación (CSPE)</th>
            <td>{{ \Carbon\Carbon::parse($notification_date)->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <th>Fecha Fin de Atención</th>
            <td>{{ \Carbon\Carbon::parse($completion_date)->format('d/m/Y') }}</td>
        </tr>
    </table>

    <div style="margin-top: 50px; text-align: center;">
        <p>___________________________________________________</p>
        <p><strong>Firma Electrónica Autorizada</strong></p>
        <p>Coordinador CSPE</p>
    </div>

</body>

</html>
