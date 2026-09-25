<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #333;
            line-height: 1.6;
        }

        .container {
            width: 100%;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
        }

        .header {
            background-color: #d500f9;
            color: #ffffff;
            padding: 15px;
            text-align: center;
            border-radius: 8px 8px 0 0;
        }

        .content {
            padding: 20px;
        }

        .footer {
            font-size: 12px;
            color: #777;
            text-align: center;
            margin-top: 20px;
            border-top: 1px solid #e0e0e0;
            padding-top: 10px;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h2>Sistema de Gestión de Requerimientos TI</h2>
        </div>

        <div class="content">
            <p>Estimado equipo,</p>

            <p>Se notifica formalmente que el requerimiento <strong>{{ $requirement->rrti }}</strong> ha culminado su
                ciclo de vida y ha sido marcado como <strong>CERRADO</strong> en el sistema de manera definitiva e
                inmutable.</p>

            <p>Adjunto a este correo electrónico encontrará el <strong>Acta de Cierre de Requerimiento</strong> generada
                automáticamente por la plataforma, la cual detalla los roles, entregables y acuerdos alcanzados durante
                la atención del caso.</p>

            <p>Atentamente,<br>
                <strong>Coordinación Seguridad Portales y Escritorios (CSPE)</strong>
            </p>
        </div>

        <div class="footer">
            Este es un mensaje automático generado por ECOSGRTI. Por favor, no responda a este correo.
        </div>
    </div>
</body>

</html>
