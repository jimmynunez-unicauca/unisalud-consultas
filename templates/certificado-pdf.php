<?php
if (!defined('ABSPATH')) {
    exit;
}

$h = function ($v) {
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
};

// ------------------------------------------------------------------
// Datos preparados
// ------------------------------------------------------------------
$nombreCompleto = trim($persona['nombre_completo'] ?? '');
if ($nombreCompleto === '') {
    $nombreCompleto = 'AFILIADO';
}

$tipoAfiliado = strtoupper($persona['tipo_afiliado'] ?? 'COTIZANTE');

$estadoDesc = trim($persona['estado_descripcion'] ?? '');
if ($estadoDesc === '') {
    $estadoDesc = 'Activo - Afiliado';
}
if (stripos($estadoDesc, 'plan') === false) {
    $estadoDesc .= ', sin Plan Complementario';
}

$mapaTipos = [
    'CC'   => 'CÉDULA DE CIUDADANÍA',
    'TI'   => 'TARJETA DE IDENTIDAD',
    'RC'   => 'REGISTRO CIVIL',
    'CE'   => 'CÉDULA DE EXTRANJERÍA',
    'PA'   => 'PASAPORTE',
    'NIT'  => 'NIT',
    'NUIP' => 'NUIP',
];
$tipoIdentRaw = strtoupper($persona['tipoidentificacion'] ?? 'CC');
$tipoIdent    = $mapaTipos[$tipoIdentRaw] ?? $tipoIdentRaw;

$esGrupoFamiliar = ($tipoCertificado === 'GRUPO FAMILIAR');

// Variables que vienen desde PDF.php:
//   $logo_file, $iso_file, $iqnet_file
//   $font_opensans_regular, $font_opensans_bold, $font_opensans_italic
//   $font_titillium_regular, $font_titillium_bold
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Certificado de Afiliación</title>
    <style>
        /* ============================================================
           FUENTES — declaradas en CSS para que Dompdf las mapee
           ============================================================ */
        @font-face {
            font-family: 'Open Sans';
            font-style: normal;
            font-weight: normal;
            src: url('<?php echo $h($font_opensans_regular); ?>') format('truetype');
        }

        @font-face {
            font-family: 'Open Sans';
            font-style: normal;
            font-weight: bold;
            src: url('<?php echo $h($font_opensans_bold); ?>') format('truetype');
        }

        @font-face {
            font-family: 'Open Sans';
            font-style: italic;
            font-weight: normal;
            src: url('<?php echo $h($font_opensans_italic); ?>') format('truetype');
        }

        @font-face {
            font-family: 'Titillium Web';
            font-style: normal;
            font-weight: normal;
            src: url('<?php echo $h($font_titillium_regular); ?>') format('truetype');
        }

        @font-face {
            font-family: 'Titillium Web';
            font-style: normal;
            font-weight: bold;
            src: url('<?php echo $h($font_titillium_bold); ?>') format('truetype');
        }

        /* ============================================================
           PÁGINA
           ============================================================ */
        @page {
            size: A4 portrait;
            margin: 0;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Open Sans';
            font-size: 10.5px;
            color: #000066;
            line-height: 1.6;
        }

        /* ============================================================
           CONTENIDO
           ============================================================ */
        .contenido {
            padding: 1.5cm 2cm 0 2cm;
        }

        /* ============================================================
           ENCABEZADO
           ============================================================ */
        .header {
            width: 100%;
            margin-bottom: 30px;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
        }

        .header-table td {
            vertical-align: middle;
            padding: 0;
        }

        .header-logo {
            width: 110px;
            text-align: left;
        }

        .header-logo img {
            max-width: 110px;
            max-height: 110px;
        }

        .header-separador {
            width: 30px;
            text-align: center;
        }

        .header-separador .linea {
            display: inline-block;
            width: 2px;
            height: 55px;
            background: #000066;
            vertical-align: middle;
        }

        .header-texto {
            font-family: 'Titillium Web';
            color: #000066;
            text-align: left;
            padding-left: 20px;
            line-height: 1.3;
        }

        .header-texto .unidad {
            font-size: 14px;
            font-weight: normal;
            color: #000066;
            margin-bottom: 2px;
        }

        .header-texto .direccion {
            font-size: 14px;
            font-weight: bold;
            color: #000066;
        }

        /* ============================================================
           TÍTULOS Y CUERPO
           ============================================================ */
        .titulo-principal {
            text-align: center;
            font-size: 11px;
            font-weight: bold;
            color: #000000;
            margin: 40px 0 20px;
            line-height: 1.5;
            text-transform: uppercase;
        }

        .subtitulo-legal {
            text-align: left;
            font-size: 10.5px;
            color: #000000;
            margin-bottom: 26px;
            line-height: 1.6;
        }

        .hace-constar {
            text-align: center;
            font-size: 11px;
            font-weight: bold;
            color: #000000;
            margin: 22px 0 20px;
            text-transform: uppercase;
        }

        .cuerpo {
            text-align: left;
            font-size: 10.5px;
            line-height: 1.75;
            margin-bottom: 14px;
            color: #000000;
        }

        .cuerpo strong {
            font-weight: bold;
        }

        .nota-institucional {
            text-align: left;
            font-size: 10.5px;
            font-weight: bold;
            color: #000000;
            margin: 22px 0 14px;
            line-height: 1.6;
            text-transform: uppercase;
        }

        .nota-portal {
            text-align: left;
            font-size: 10.5px;
            color: #000000;
            margin: 14px 0;
            line-height: 1.6;
        }

        .genera-para {
            text-align: left;
            font-size: 10.5px;
            color: #000000;
            margin: 14px 0 8px;
            line-height: 1.6;
        }

        .genera-para strong {
            font-weight: bold;
        }

        /* ============================================================
           TABLA BENEFICIARIOS
           ============================================================ */
        .seccion-benef {
            margin-top: 20px;
            margin-bottom: 20px;
        }

        .seccion-benef-titulo {
            background: #eef0f8;
            color: #000066;
            padding: 8px 10px;
            font-size: 10px;
            font-weight: bold;
            text-align: center;
            text-transform: uppercase;
            border: 1px solid #d4d6e4;
            border-bottom: none;
        }

        .tabla-benef {
            width: 100%;
            border-collapse: collapse;
        }

        .tabla-benef thead th {
            background: #f4f5fa;
            color: #000066;
            padding: 6px 8px;
            border: 1px solid #d4d6e4;
            font-size: 9px;
            font-weight: bold;
            text-align: center;
            text-transform: uppercase;
        }

        .tabla-benef tbody td {
            padding: 6px 8px;
            border: 1px solid #d4d6e4;
            font-size: 9.5px;
            text-align: center;
            color: #000066;
        }

        .tabla-benef tbody tr:nth-child(even) td {
            background: #f8f9fc;
        }

        /* ============================================================
           FECHA Y FIRMA
           ============================================================ */
        .fecha-expedicion {
            text-align: left;
            font-size: 10.5px;
            color: #000000;
            margin: 24px 0 60px;
        }

        .firma-wrapper {
            text-align: center;
            margin-top: 40px;
            margin-bottom: 40px;
        }

        .firma-bloque {
            display: inline-block;
            text-align: center;
        }

        .firma-nombre {
            font-size: 11px;
            color: #000000;
            line-height: 1.1;
            margin-bottom: 0;
        }

        .firma-cargo {
            font-size: 10.5px;
            color: #000000;
            line-height: 1.1;
        }

        .firma-img {
            display: block;
            max-height: 60px;
            max-width: 180px;
            margin: 0 auto 2px auto;
        }

        /* ============================================================
           PIE DE PÁGINA FIJO
           ============================================================ */
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            height: 4.2cm;
            padding: 0.5cm 2cm 0cm 2cm;
            background: #ffffff;
        }

        .footer-tabla {
            width: 100%;
            border-collapse: collapse;
        }

        .footer-tabla>tbody>tr>td {
            vertical-align: middle;
            padding: 0 6px;
        }

        .footer-left {
            width: 30%;
            text-align: left;
        }

        .footer-left .acreditada-en {
            font-size: 12px;
            color: #000066;
            line-height: 1.1;
        }

        .footer-left .alta-calidad {
            font-size: 20px;
            font-weight: bold;
            color: #000066;
            line-height: 1.1;
            text-transform: uppercase;
        }

        .footer-left .resolucion {
            font-size: 8px;
            color: #000066;
            line-height: 1.1;
            margin-top: 3px;
        }

        .footer-center {
            width: 42%;
            text-align: center;
            font-size: 8.5px;
            color: #000066;
            line-height: 1.1;
        }

        .footer-right {
            width: 28%;
            text-align: right;
            /* quitamos white-space: nowrap para permitir el salto de línea interno */
        }

        /* Cada icono con su texto debajo */
        .icono-cert {
            display: inline-block;
            text-align: center;
            vertical-align: top;
            margin: 0 4px;
            width: 60px;
            /* ajusta según necesites */
        }

        .icono-cert img {
            display: block;
            max-height: 55px;
            max-width: 55px;
            margin: 0 auto 2px auto;
        }

        .icono-cert .icono-texto {
            display: block;
            font-size: 5px;
            line-height: 1.1;
            color: #00aae5;
            text-align: center;
        }
    </style>
</head>

<body>

    <!-- ============================================================
         CONTENIDO CON MÁRGENES
         ============================================================ -->
    <div class="contenido">

        <!-- ENCABEZADO -->
        <div class="header">
            <table class="header-table">
                <tr>
                    <td class="header-logo">
                        <img src="<?php echo $h($logo_file); ?>" alt="Universidad del Cauca">
                    </td>
                    <td class="header-separador">
                        <span class="linea"></span>
                    </td>
                    <td class="header-texto">
                        <div class="unidad">Unidad de Salud</div>
                        <div class="direccion">Dirección</div>
                    </td>
                </tr>
            </table>
        </div>

        <!-- TÍTULO -->
        <div class="titulo-principal">
            LA SECRETARIA DE DIRECCIÓN DE LA UNIDAD DE SALUD DE LA<br>
            UNIVERSIDAD DEL CAUCA, EN EJERCICIO DE SUS ATRIBUCIONES LEGALES.
        </div>

        <div class="subtitulo-legal">
            Esta constancia no es valida para vinculación laboral. Valida por 30 días calendario.
        </div>

        <div class="hace-constar">HACE CONSTAR</div>

        <!-- CUERPO -->
        <div class="cuerpo">
            Que el ciudadano <strong><?php echo $h(mb_strtoupper($nombreCompleto, 'UTF-8')); ?></strong>,
            identificado con <strong><?php echo $h($tipoIdent); ?></strong>
            número <strong><?php echo $h($persona['numerodocumento'] ?? ''); ?></strong>,
            se encuentra afiliado como <strong><?php echo $h($tipoAfiliado); ?></strong>
            a la Unidad de Salud de la Universidad del Cauca
            (<em><?php echo $h($estadoDesc); ?></em>),
            y tiene derecho a recibir los servicios médicos asistenciales y hospitalarios
            que presta la Unidad de Salud de la Universidad del Cauca y las unidades adscritas.
        </div>

        <div class="nota-institucional">
            LA UNIDAD DE SALUD DE LA UNIVERSIDAD DEL CAUCA, ES UNA ENTIDAD ADMINISTRADORA
            DE PLANES DE BENEFICIOS RECONOCIDA POR LA SUPERINTENDENCIA NACIONAL DE SALUD.
        </div>

        <div class="nota-portal">
            La presente certificación es generada a través del portal Web de la Unidad de Salud.
        </div>

        <?php if (!empty($genera_para)): ?>
            <div class="genera-para">
                <strong>El Certificado se Genera Para:</strong> <?php echo $h($genera_para); ?>
            </div>
        <?php endif; ?>

        <?php if ($esGrupoFamiliar && !empty($beneficiarios)): ?>
            <div class="seccion-benef">
                <div class="seccion-benef-titulo">Grupo Familiar</div>
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
                                <td><?php echo $h(mb_strtoupper($b['nombre_completo'] ?? '', 'UTF-8')); ?></td>
                                <td><?php echo $h($b['parentesco'] ?? '—'); ?></td>
                                <td><?php echo $h($b['edad'] ?? ''); ?></td>
                                <td><?php echo $h($b['estado_descripcion'] ?? ''); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <div class="fecha-expedicion">
            Dada en <?php echo $h($ciudad); ?> el <?php echo $h($fecha_expedicion_larga); ?>
        </div>

        <div class="firma-wrapper">
            <div class="firma-bloque">
                <img class="firma-img"
                    src="<?php echo $h($firma_file); ?>"
                    alt="Firma">
                <!-- <div class="firma-nombre">Carmen Zulma Velasco Cerón</div> -->
                <div class="firma-nombre">James Andrés Quira Gutiérrez</div>
                <div class="firma-cargo">
                    Secretaria de Dirección<br>
                    Unidad de Salud<br>
                    Universidad del Cauca
                </div>
            </div>
        </div>

    </div><!-- /.contenido -->

    <!-- ============================================================
         PIE DE PÁGINA FIJO
         ============================================================ -->
    <div class="footer">
        <table class="footer-tabla">
            <tr>
                <td class="footer-left">
                    <div class="acreditada-en">Acreditada en</div>
                    <div class="alta-calidad">ALTA CALIDAD</div>
                    <div class="resolucion">*Resolución 6218 de junio de 2019</div>
                </td>
                <td class="footer-center">
                    Calle 4 No. 3 -57 Centro<br>
                    Unidad de Salud<br>
                    Popayán - Cauca - Colombia<br>
                    Teléfono 602 8209900 Exts. 1600 - 1601<br>
                    dirunisalud@unicauca.edu.co &nbsp;&nbsp; unisalud@unicauca.edu.co<br>
                    www.unicauca.edu.co
                </td>
                <td class="footer-right">
                    <div class="icono-cert">
                        <img src="<?php echo $h($iso_file); ?>" alt="ISO 9001">
                        <span class="icono-texto">ISO 9001: 2015 SC-
                            <br>
                            CER000000</span>
                    </div>
                    <div class="icono-cert">
                        <img src="<?php echo $h($iqnet_file); ?>" alt="IQNET">
                        <span class="icono-texto">IQNet: CO- SC-
                            <br>
                            CER000000</span>
                    </div>
                </td>
            </tr>
        </table>
    </div>

</body>

</html>