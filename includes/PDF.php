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
            // =====================================================
            // Carpeta de cache de fuentes (en uploads, siempre writable)
            // =====================================================
            $upload_dir = wp_upload_dir();
            $cache_dir  = $upload_dir['basedir'] . '/dompdf-cache';
            if (!file_exists($cache_dir)) {
                wp_mkdir_p($cache_dir);
            }

            $options = new Options();
            $options->set('isRemoteEnabled', false);            // NO queremos fetch HTTP
            $options->set('isHtml5ParserEnabled', true);
            $options->set('defaultFont', 'Open Sans');
            $options->set('chroot', dirname(__DIR__, 4));       // /plugins/
            $options->set('fontDir', $cache_dir);
            $options->set('fontCache', $cache_dir);

            // Aumentar límites para fuentes grandes
            @ini_set('memory_limit', '256M');
            @set_time_limit(60);

            $dompdf = new Dompdf($options);

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
     * Convierte ruta absoluta del sistema de archivos a file:// URL.
     * Funciona en Windows (file:///C:/xampp/...) y Linux (file:///var/www/...).
     */
    private static function toFileUrl($absolute_path)
    {
        $path = str_replace('\\', '/', $absolute_path);

        // Codificar espacios y caracteres especiales por si acaso
        $segmentos = explode('/', $path);
        $segmentos = array_map('rawurlencode', $segmentos);
        $encoded   = implode('/', $segmentos);
        // Revertir la codificación de ':' para que quede "C:/" o "file:///"
        $encoded   = str_replace('%3A', ':', $encoded);

        if (preg_match('/^[a-zA-Z]:\//', $path)) {
            // Windows: file:///C:/ruta
            return 'file:///' . $encoded;
        }
        // Linux/Mac: file:///ruta (la ruta ya empieza con /)
        return 'file://' . $encoded;
    }

    /**
     * Verifica que un archivo exista y sea legible.
     */
    private static function archivoUsable($absolute_path)
    {
        return file_exists($absolute_path) && is_readable($absolute_path);
    }

    /**
     * Devuelve file:// URL o cadena vacía si el archivo no está disponible.
     */
    private static function fileUrlSiExiste($absolute_path)
    {
        return self::archivoUsable($absolute_path) ? self::toFileUrl($absolute_path) : '';
    }

    /**
     * Carga la plantilla HTML y la rellena con los datos.
     */
    private static function renderPlantilla($persona, $beneficiarios, $tipoCertificado, $generaPara)
    {
        $module_dir = dirname(__DIR__);
        $img_dir    = $module_dir . '/public/img/certificado/';
        $font_dir   = $module_dir . '/public/fonts/';

        // Imágenes
        $logo_file  = self::fileUrlSiExiste($img_dir . 'logo-unicauca.png');
        $iso_file   = self::fileUrlSiExiste($img_dir . 'iso-9001.png');   // JPG
        $iqnet_file = self::fileUrlSiExiste($img_dir . 'iqnet.png');      // JPG
        $firma_file = self::fileUrlSiExiste($img_dir . 'firma.png');      // JPG

        // Fuentes (file:// para que @font-face las lea del disco)
        $font_opensans_regular  = self::fileUrlSiExiste($font_dir . 'OpenSans-Regular.ttf');
        $font_opensans_bold     = self::fileUrlSiExiste($font_dir . 'OpenSans-Bold.ttf');
        $font_opensans_italic   = self::fileUrlSiExiste($font_dir . 'OpenSans-Italic.ttf');
        $font_titillium_regular = self::fileUrlSiExiste($font_dir . 'TitilliumWeb-Regular.ttf');
        $font_titillium_bold    = self::fileUrlSiExiste($font_dir . 'TitilliumWeb-Bold.ttf');

        $datos = [
            'persona'                => $persona,
            'beneficiarios'          => $beneficiarios,
            'tipo_certificado'       => $tipoCertificado,
            'genera_para'            => $generaPara,
            'fecha_expedicion'       => self::fechaEnEspanol(),
            'fecha_expedicion_larga' => self::fechaLargaEnEspanol(),
            'ciudad'                 => 'Popayán',

            // Imágenes
            'logo_file'              => $logo_file,
            'iso_file'               => $iso_file,
            'iqnet_file'             => $iqnet_file,
            'firma_file'             => $firma_file,
            // Fuentes
            'font_opensans_regular'  => $font_opensans_regular,
            'font_opensans_bold'     => $font_opensans_bold,
            'font_opensans_italic'   => $font_opensans_italic,
            'font_titillium_regular' => $font_titillium_regular,
            'font_titillium_bold'    => $font_titillium_bold,

            'codigo_verif'           => self::codigoVerificacion($persona),
        ];

        extract($datos);

        ob_start();
        include __DIR__ . '/../templates/certificado-pdf.php';
        return ob_get_clean();
    }

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

    private static function fechaLargaEnEspanol()
    {
        $meses = [
            1 => 'Enero',
            2 => 'Febrero',
            3 => 'Marzo',
            4 => 'Abril',
            5 => 'Mayo',
            6 => 'Junio',
            7 => 'Julio',
            8 => 'Agosto',
            9 => 'Septiembre',
            10 => 'Octubre',
            11 => 'Noviembre',
            12 => 'Diciembre',
        ];
        $mes = $meses[(int)date('n')] ?? date('F');
        return date('d') . ' de ' . $mes . ' de ' . date('Y');
    }

    private static function codigoVerificacion($persona)
    {
        $base = ($persona['numerodocumento'] ?? '') . '|' . date('Ymd') . '|' . wp_salt('auth');
        return strtoupper(substr(md5($base), 0, 12));
    }
}
