<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>@yield('title', 'Reporte ECOSGRTI')</title>
    <style>
        /* CSS Específico y seguro para DomPDF */
        @page {
            margin: 120px 50px 80px 50px;
        }

        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 12px;
            color: #333333;
        }

        /* Encabezado Fijo */
        header {
            position: fixed;
            top: -90px;
            left: 0px;
            right: 0px;
            height: 70px;
            border-bottom: 2px solid #0056b3;
            /* Azul CANTV aproximado */
            text-align: center;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
        }

        .header-table td {
            vertical-align: middle;
        }

        .header-logo {
            width: 20%;
            text-align: left;
        }

        .header-title {
            width: 60%;
            text-align: center;
        }

        .header-title h1 {
            margin: 0;
            font-size: 16px;
            color: #0056b3;
            text-transform: uppercase;
        }

        .header-title p {
            margin: 2px 0 0 0;
            font-size: 10px;
            color: #666;
        }

        .header-meta {
            width: 20%;
            text-align: right;
            font-size: 9px;
            color: #666;
        }

        /* Pie de Página Fijo */
        footer {
            position: fixed;
            bottom: -50px;
            left: 0px;
            right: 0px;
            height: 30px;
            border-top: 1px solid #ddd;
            text-align: center;
            font-size: 10px;
            color: #777;
            padding-top: 5px;
        }

        .page-number:after {
            content: counter(page);
        }

        /* Contenido Principal */
        main {
            margin-top: 10px;
        }

        /* Clases utilitarias globales */
        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        .bold {
            font-weight: bold;
        }

        .mt-20 {
            margin-top: 20px;
        }
    </style>
</head>

<body>

    <header>
        <table class="header-table">
            <tr>
                <td class="header-logo">
                    <!-- TODO: Habilitaremos la imagen de CANTV luego de probar el renderizado -->
                    <h2 style="margin:0; color:#0056b3;">CANTV</h2>
                </td>
                <td class="header-title">
                    <h1>Sistema de Gestión de Requerimientos TI</h1>
                    <p>Coordinación Seguridad Portales y Escritorios (CSPE)</p>
                </td>
                <td class="header-meta">
                    Fecha: {{ date('d/m/Y') }}<br>
                    Hora: {{ date('H:i') }}<br>
                    Usuario: {{ auth()->user()->name ?? 'Sistema' }}
                </td>
            </tr>
        </table>
    </header>

    <footer>
        <table style="width: 100%;">
            <tr>
                <td style="text-align: left;">ECOSGRTI - Uso Interno</td>
                <td style="text-align: right;">Página <span class="page-number"></span></td>
            </tr>
        </table>
    </footer>

    <main>
        <!-- Aquí se inyectará el contenido de cada reporte específico -->
        @yield('content')
    </main>

</body>

</html>
