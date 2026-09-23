<?php
if (!defined('ABSPATH')) {
    exit;
}

// Helpers locales
$h = function ($v) {
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
};

$nombreCompleto = trim(
    ($persona['primernombre']    ?? '') . ' ' .
        ($persona['segundonombre']   ?? '') . ' ' .
        ($persona['primerapellido']  ?? '') . ' ' .
        ($persona['segundoapellido'] ?? '')
);

$tipoAfiliado = strtoupper($persona['tipo_afiliado'] ?? 'COTIZANTE');
$esGrupoFamiliar = ($tipoCertificado === 'GRUPO FAMILIAR');
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Certificado de Afiliación</title>
    <style>
        @page {
            margin: 2cm 1.5cm 2cm 1.5cm;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Helvetica', Arial, sans-serif;
            font-size: 11px;
            color: #1a1a3e;
            line-height: 1.4;
        }

        /* ---------- Encabezado ---------- */
        .header {
            width: 100%;
            margin-bottom: 20px;
            border-bottom: 2px solid #1a1a6e;
            padding-bottom: 12px;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
        }

        .header-table td {
            vertical-align: middle;
            border: none;
            padding: 0;
        }

        .header-logo {
            width: 90px;
        }

        .header-logo img {
            max-width: 90px;
            max-height: 70px;
        }

        .header-text {
            text-align: right;
        }

        .header-text .universidad {
            font-size: 14px;
            font-weight: bold;
            color: #1a1a6e;
            margin-bottom: 2px;
        }

        .header-text .unidad {
            font-size: 11px;
            color: #4a50a0;
            margin-bottom: 2px;
        }

        .header-text .nit {
            font-size: 9px;
            color: #6b6f8a;
        }

        /* ---------- Título ---------- */
        .titulo-wrapper {
            text-align: center;
            margin: 24px 0 20px;
        }

        .titulo {
            font-size: 18px;
            font-weight: bold;
            color: #1a1a6e;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            margin-bottom: 4px;
        }

        .subtitulo {
            font-size: 10px;
            color: #6b6f8a;
            letter-spacing: 2px;
            text-transform: uppercase;
        }

        /* ---------- Cuerpo ---------- */
        .intro {
            text-align: justify;
            margin-bottom: 18px;
            font-size: 11px;
            line-height: 1.7;
        }

        .intro strong {
            color: #1a1a6e;
        }

        /* ---------- Tabla de datos ---------- */
        .seccion-titulo {
            background: #4a50a0;
            color: #ffffff;
            padding: 6px 10px;
            font-size: 10.5px;
            font-weight: bold;
            letter-spacing: 1px;
            text-transform: uppercase;
            margin: 16px 0 0;
        }

        .tabla-datos {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }

        .tabla-datos tr td {
            padding: 6px 10px;
            border: 1px solid #d4d6e4;
            font-size: 10.5px;
            vertical-align: top;
        }

        .tabla-datos tr td.label {
            background: #f4f5fa;
            font-weight: bold;
            width: 35%;
            color: #4a50a0;
        }

        /* ---------- Tabla beneficiarios ---------- */
        .tabla-benef {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
        }

        .tabla-benef thead th {
            background: #4a50a0;
            color: #ffffff;
            padding: 7px 8px;
            border: 1px solid #3a3f80;
            font-size: 9.5px;
            font-weight: bold;
            text-align: left;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .tabla-benef tbody td {
            padding: 6px 8px;
            border: 1px solid #d4d6e4;
            font-size: 10px;
        }

        .tabla-benef tbody tr:nth-child(even) td {
            background: #f8f9fc;
        }

        /* ---------- Nota final ---------- */
        .nota {
            margin-top: 20px;
            padding: 10px 14px;
            background: #f8f9fc;
            border-left: 3px solid #4a50a0;
            font-size: 10px;
            line-height: 1.6;
            color: #3d4560;
        }

        .nota strong {
            color: #1a1a6e;
        }

        /* ---------- Firma ---------- */
        .firma-wrapper {
            margin-top: 50px;
            text-align: center;
        }

        .firma-linea {
            width: 280px;
            margin: 0 auto;
            border-top: 1px solid #1a1a3e;
            padding-top: 6px;
            font-size: 10px;
        }

        .firma-nombre {
            font-weight: bold;
            color: #1a1a6e;
            font-size: 11px;
        }

        .firma-cargo {
            color: #6b6f8a;
            font-size: 9.5px;
            margin-top: 2px;
        }

        /* ---------- Pie ---------- */
        .pie {
            margin-top: 40px;
            padding-top: 12px;
            border-top: 1px solid #d4d6e4;
            text-align: center;
            font-size: 8px;
            color: #9498b3;
            line-height: 1.5;
        }

        .pie .codigo {
            font-family: 'Courier New', monospace;
            font-size: 9px;
            color: #4a50a0;
            font-weight: bold;
            letter-spacing: 1px;
        }

        /* ---------- Marca de agua (opcional) ---------- */
        .watermark {
            position: fixed;
            top: 45%;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 90px;
            color: rgba(26, 26, 110, 0.05);
            font-weight: bold;
            letter-spacing: 8px;
            z-index: -1;
        }
    </style>
</head>

<body>

    <!-- Marca de agua -->
    <div class="watermark">UNICAUCA</div>

    <!-- ============ ENCABEZADO ============ -->
    <div class="header">
        <table class="header-table">
            <tr>
                <td class="header-logo">
                    <?php if (file_exists(ABSPATH . 'wp-content/plugins/my-plugin-unicauca/modules/unisalud-consulta/public/img/logo-unicauca.png')): ?>
                        <img src="<?php echo $h($logo_url); ?>" alt="Universidad del Cauca">
                    <?php endif; ?>
                </td>
                <td class="header-text">
                    <div class="universidad">UNIVERSIDAD DEL CAUCA</div>
                    <div class="unidad">Unidad de Salud - EPS Unisalud</div>
                    <div class="nit">NIT. 891.500.123-4 &nbsp;|&nbsp; Popayán - Cauca</div>
                </td>
            </tr>
        </table>
    </div>

    <!-- ============ TÍTULO ============ -->
    <div class="titulo-wrapper">
        <div class="titulo">Certificado de Afiliación</div>
        <div class="subtitulo">
            <?php echo $esGrupoFamiliar ? 'Grupo Familiar' : 'Individual'; ?>
        </div>
    </div>

    <!-- ============ INTRO ============ -->
    <div class="intro">
        La <strong>UNIDAD DE SALUD - EPS UNISALUD</strong> de la Universidad del Cauca certifica que
        <strong><?php echo $h($nombreCompleto); ?></strong>,
        identificado(a) con <strong><?php echo $h($persona['tipoidentificacion'] ?? 'CC'); ?></strong>
        N° <strong><?php echo $h($persona['numerodocumento'] ?? ''); ?></strong>,
        se encuentra registrado(a) en nuestra base de datos con la siguiente información:
    </div>

    <!-- ============ DATOS DEL COTIZANTE ============ -->
    <div class="seccion-titulo">Información del Afiliado</div>
    <table class="tabla-datos">
        <tr>
            <td class="label">Tipo de Afiliación</td>
            <td><?php echo $h($tipoAfiliado); ?></td>
        </tr>
        <tr>
            <td class="label">Documento de Identidad</td>
            <td><?php echo $h(($persona['tipoidentificacion'] ?? '') . ' ' . ($persona['numerodocumento'] ?? '')); ?></td>
        </tr>
        <tr>
            <td class="label">Nombre Completo</td>
            <td><?php echo $h($nombreCompleto); ?></td>
        </tr>
        <tr>
            <td class="label">Fecha de Nacimiento</td>
            <td><?php echo $h($persona['fecha_nacimiento_formateada'] ?? $persona['fechanacimiento'] ?? ''); ?></td>
        </tr>
        <tr>
            <td class="label">Edad</td>
            <td><?php echo $h($persona['edad'] ?? ''); ?> años</td>
        </tr>
        <tr>
            <td class="label">Fecha de Afiliación</td>
            <td><?php echo $h($persona['fecha_afiliacion_formateada'] ?? ''); ?></td>
        </tr>
        <tr>
            <td class="label">Nivel Salarial</td>
            <td><?php echo $h($persona['nivel_salarial'] ?? '—'); ?></td>
        </tr>
        <tr>
            <td class="label">Estado de Afiliación</td>
            <td><?php echo $h($persona['estado_descripcion'] ?? ''); ?></td>
        </tr>
    </table>

    <?php if ($esGrupoFamiliar && !empty($beneficiarios)): ?>
        <!-- ============ BENEFICIARIOS ============ -->
        <div class="seccion-titulo">Grupo Familiar (<?php echo count($beneficiarios); ?> beneficiarios)</div>
        <table class="tabla-benef">
            <thead>
                <tr>
                    <th style="width: 22%;">Documento</th>
                    <th style="width: 38%;">Nombre Completo</th>
                    <th style="width: 15%;">Parentesco</th>
                    <th style="width: 10%;">Edad</th>
                    <th style="width: 15%;">Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($beneficiarios as $b): ?>
                    <tr>
                        <td><?php echo $h(($b['tipoidentificacion'] ?? '') . ' ' . ($b['numerodocumento'] ?? '')); ?></td>
                        <td><?php echo $h($b['nombre_completo'] ?? ''); ?></td>
                        <td><?php echo $h($b['parentesco'] ?? '—'); ?></td>
                        <td><?php echo $h($b['edad'] ?? ''); ?></td>
                        <td><?php echo $h($b['estado_descripcion'] ?? ''); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <!-- ============ NOTA FINAL ============ -->
    <div class="nota">
        <strong>Nota:</strong> El presente certificado se expide a solicitud del interesado(a)
        <?php if (!empty($genera_para)): ?>
            para <strong><?php echo $h($genera_para); ?></strong>
            <?php endif; ?>,
            y tiene validez como documento probatorio del estado de afiliación a la
            <strong>EPS Unisalud</strong> de la Universidad del Cauca.
    </div>

    <!-- ============ FECHA Y FIRMA ============ -->
    <div class="firma-wrapper">
        <div style="margin-bottom: 50px; font-size: 10.5px; text-align: left;">
            Expedido en <?php echo $h($ciudad); ?>, el <?php echo $h($fecha_expedicion); ?>
        </div>
        <div class="firma-linea">
            <div class="firma-nombre">Dirección de Unisalud</div>
            <div class="firma-cargo">EPS Unisalud - Universidad del Cauca</div>
        </div>
    </div>

    <!-- ============ PIE ============ -->
    <div class="pie">
        <p>
            Este certificado puede ser verificado en el portal web de Unisalud ingresando el código:
            <span class="codigo"><?php echo $h($codigo_verif); ?></span>
        </p>
        <p>
            Documento generado automáticamente el <?php echo $h($fecha_expedicion); ?>.
            Universidad del Cauca - División TIC.
        </p>
    </div>

</body>

</html>