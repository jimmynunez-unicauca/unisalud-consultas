<?php
if (!defined('ABSPATH')) {
    exit;
}

use Dompdf\Dompdf;
use Dompdf\Options;

class UsuariosSaludPDF
{
    /**
     * Genera el PDF del certificado.
     *
     * @param array  $persona          Datos del cotizante (o de la persona consultada)
     * @param array  $beneficiarios    Beneficiarios (vacío si es INDIVIDUAL)
     * @param string $tipoCertificado  'INDIVIDUAL' | 'GRUPO FAMILIAR'
     * @param string $generaPara       Texto opcional "a quién va dirigido"
     * @return string|false            Bytes del PDF o false si falla
     */
    public static function generarCertificado($persona, $beneficiarios, $tipoCertificado, $generaPara = '')
    {
        $autoload = dirname(__DIR__) . '/vendor/autoload.php';
        if (!file_exists($autoload)) {
            error_log('[PDF] No existe vendor/autoload.php en ' . $autoload);
            return false;
        }
        require_once $autoload;

        if (!class_exists('Dompdf\\Dompdf')) {
            error_log('[PDF] Dompdf no disponible');
            return false;
        }

        try {
            $options = new Options();
            $options->set('isRemoteEnabled', true);      // para cargar el logo por URL
            $options->set('isHtml5ParserEnabled', true);
            $options->set('defaultFont', 'Helvetica');
            $options->set('chroot', ABSPATH);            // permitir imágenes locales

            $dompdf = new Dompdf($options);

            // Renderizar la plantilla
            $html = self::renderPlantilla($persona, $beneficiarios, $tipoCertificado, $generaPara);

            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

            return $dompdf->output();
        } catch (Exception $e) {
            error_log('[PDF] Error al generar: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Carga la plantilla HTML y la rellena con los datos.
     */
    private static function renderPlantilla($persona, $beneficiarios, $tipoCertificado, $generaPara)
    {
        // Variables disponibles en la plantilla
        $datos = [
            'persona'          => $persona,
            'beneficiarios'    => $beneficiarios,
            'tipo_certificado' => $tipoCertificado,
            'genera_para'      => $generaPara,
            'fecha_expedicion' => self::fechaEnEspanol(),
            'ciudad'           => 'Popayán',
            'logo_url'         => self::urlLogo(),
            'codigo_verif'     => self::codigoVerificacion($persona),
        ];

        // Extraer para que estén accesibles en la plantilla
        extract($datos);

        ob_start();
        include __DIR__ . '/../templates/certificado-pdf.php';
        return ob_get_clean();
    }

    /**
     * Fecha actual en español con formato largo.
     */
    private static function fechaEnEspanol()
    {
        $dias  = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
        $meses = [
            'enero',
            'febrero',
            'marzo',
            'abril',
            'mayo',
            'junio',
            'julio',
            'agosto',
            'septiembre',
            'octubre',
            'noviembre',
            'diciembre'
        ];

        $ts = time();
        return $dias[date('w', $ts)] . ', ' . date('d', $ts) . ' de ' .
            $meses[date('n', $ts) - 1] . ' de ' . date('Y', $ts);
    }

    /**
     * URL del logo (ajústalo a la ruta real de tu plugin).
     */
    private static function urlLogo()
    {
        return WP_PLUGIN_URL . '/my-plugin-unicauca/modules/unisalud-consulta/public/img/logo-unicauca.png';
    }

    /**
     * Genera un código de verificación único por certificado.
     */
    private static function codigoVerificacion($persona)
    {
        $base = ($persona['numerodocumento'] ?? '') . '|' . date('Ymd') . '|' . wp_salt('auth');
        return strtoupper(substr(md5($base), 0, 12));
    }
}
