<?php
if (!defined('ABSPATH')) {
    exit;
}

// Includes base
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/Auth.php';
require_once __DIR__ . '/includes/OTP.php';
require_once __DIR__ . '/includes/Mailer.php';
require_once __DIR__ . '/includes/Historial.php';
require_once __DIR__ . '/includes/Recaptcha.php';

/**
 * Iniciar sesión PHP lo antes posible
 */
add_action('plugins_loaded', function () {
    if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
        @session_start();
    }
}, 1);

/**
 * Encolar assets según estado de sesión
 */
function unisalud_consulta_enqueue_scripts()
{
    global $post;
    if (!is_a($post, 'WP_Post') || !has_shortcode($post->post_content, 'unisalud_consulta')) {
        return;
    }

    $version  = time();
    $base_url = plugin_dir_url(__FILE__);
    $sesion   = UsuariosSaludAuth::validarSesion();

    // ============================================================
    // SweetAlert2 — común a las dos vistas
    // ============================================================
    wp_enqueue_style(
        'sweetalert2-css',
        'https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css',
        [],
        '11.14.5'
    );
    wp_enqueue_script(
        'sweetalert2-js',
        'https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js',
        [],
        '11.14.5',
        true
    );

    if ($sesion['valid']) {
        wp_enqueue_style(
            'usuarios-salud-css',
            $base_url . 'public/css/style-usuarios.css',
            [],
            $version
        );
        wp_enqueue_script(
            'usuarios-salud-js',
            $base_url . 'public/js/script-usuarios.js',
            ['jquery', 'sweetalert2-js'],
            $version,
            true
        );

        // ------------------------------------------------------------
        // Calcular tiempos de sesión
        // ------------------------------------------------------------
        $key      = UsuariosSaludAuth::SESSION_KEY;
        $duracion = UsuariosSaludAuth::DURACION_SEGUNDOS;

        $creada   = isset($_SESSION[$key]['created_at'])
            ? (int)$_SESSION[$key]['created_at']
            : time();

        $restante = max(0, $duracion - (time() - $creada));

        // ------------------------------------------------------------
        // UNA SOLA llamada a wp_localize_script con TODAS las variables
        // ------------------------------------------------------------
        wp_localize_script('usuarios-salud-js', 'usuarios_ajax', [
            'ajax_url'          => admin_url('admin-ajax.php'),
            'nonce'             => wp_create_nonce('unisalud_consulta_nonce'),
            'plugin_url'        => $base_url,
            'sesion_restante'   => $restante,
            'sesion_total'      => $duracion,
            'inactividad_total' => UsuariosSaludAuth::INACTIVIDAD_SEGUNDOS,
            'aviso_segundos'    => UsuariosSaludAuth::AVISO_SEGUNDOS,
        ]);
    } else {
        wp_enqueue_style(
            'usuarios-salud-otp-css',
            $base_url . 'public/css/style-otp.css',
            [],
            $version
        );
        wp_enqueue_script(
            'usuarios-salud-otp-js',
            $base_url . 'public/js/script-otp.js',
            ['jquery', 'sweetalert2-js'],
            $version,
            true
        );
        wp_localize_script('usuarios-salud-otp-js', 'usuarios_otp_ajax', [
            'ajax_url'           => admin_url('admin-ajax.php'),
            'nonce'              => wp_create_nonce('unisalud_consulta_nonce'),
            'redirect'           => get_permalink(),
            'recaptcha_site_key' => UsuariosSaludRecaptcha::SITE_KEY,
            'recaptcha_action'   => UsuariosSaludRecaptcha::ACTION,
        ]);
    }
}
add_action('wp_enqueue_scripts', 'unisalud_consulta_enqueue_scripts');

/**
 * Evitar caché de página en la URL del shortcode
 */
add_action('wp_enqueue_scripts', function () {
    global $post;
    if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'unisalud_consulta')) {
        nocache_headers();
        if (!defined('DONOTCACHEPAGE')) {
            define('DONOTCACHEPAGE', true);
        }
    }
}, 0);

/**
 * Shortcode principal
 */
function unisalud_consulta_shortcode()
{
    $sesion = UsuariosSaludAuth::validarSesion();

    ob_start();

    if ($sesion['valid']) {
        $usuario_salud = $sesion['usuario'];
        $template = __DIR__ . '/template-lista.php';
    } else {
        $template = __DIR__ . '/template-otp.php';
    }

    if (file_exists($template)) {
        include($template);
    } else {
        echo 'Error: No se encuentra ' . esc_html(basename($template));
    }

    return ob_get_clean();
}
add_shortcode('unisalud_consulta', 'unisalud_consulta_shortcode');

/**
 * Cargar handlers y registrar todas las acciones AJAX en 'init'
 */
add_action('init', function () {
    require_once __DIR__ . '/ajax/otp.php';
    require_once __DIR__ . '/ajax/personas.php';

    // ==== OTP ====
    add_action('wp_ajax_nopriv_salud_buscar_correos', 'ajax_salud_buscar_correos');
    add_action('wp_ajax_salud_buscar_correos',        'ajax_salud_buscar_correos');
    add_action('wp_ajax_nopriv_salud_enviar_otp',     'ajax_salud_enviar_otp');
    add_action('wp_ajax_salud_enviar_otp',            'ajax_salud_enviar_otp');
    add_action('wp_ajax_nopriv_salud_verificar_otp',  'ajax_salud_verificar_otp');
    add_action('wp_ajax_salud_verificar_otp',         'ajax_salud_verificar_otp');
    add_action('wp_ajax_nopriv_salud_reenviar_otp',   'ajax_salud_reenviar_otp');
    add_action('wp_ajax_salud_reenviar_otp',          'ajax_salud_reenviar_otp');
    add_action('wp_ajax_nopriv_salud_cerrar_sesion',  'ajax_salud_cerrar_sesion');
    add_action('wp_ajax_salud_cerrar_sesion',         'ajax_salud_cerrar_sesion');

    // ==== Nonce fresco ====
    add_action('wp_ajax_nopriv_salud_fresh_nonce', 'ajax_salud_fresh_nonce');
    add_action('wp_ajax_salud_fresh_nonce',        'ajax_salud_fresh_nonce');

    // ==== Debug ====
    add_action('wp_ajax_nopriv_salud_debug_estado', 'ajax_salud_debug_estado');
    add_action('wp_ajax_salud_debug_estado',        'ajax_salud_debug_estado');

    // ==== Session status ====
    add_action('wp_ajax_nopriv_salud_session_status', 'ajax_salud_session_status');
    add_action('wp_ajax_salud_session_status',        'ajax_salud_session_status');

    // ==== Session activity ping (NUEVO) ====
    add_action('wp_ajax_nopriv_salud_activity_ping', 'ajax_salud_activity_ping');
    add_action('wp_ajax_salud_activity_ping',        'ajax_salud_activity_ping');

    // ==== Personas ====
    add_action('wp_ajax_nopriv_get_unisalud_consulta',     'ajax_get_unisalud_consulta');
    add_action('wp_ajax_get_unisalud_consulta',            'ajax_get_unisalud_consulta');
    add_action('wp_ajax_nopriv_get_persona_detalle_salud', 'ajax_get_persona_detalle_salud');
    add_action('wp_ajax_get_persona_detalle_salud',        'ajax_get_persona_detalle_salud');

    add_action('wp_ajax_nopriv_salud_consultar_por_identificacion', 'ajax_salud_consultar_por_identificacion');
    add_action('wp_ajax_salud_consultar_por_identificacion',        'ajax_salud_consultar_por_identificacion');

    add_action('wp_ajax_nopriv_salud_get_historial', 'ajax_salud_get_historial');
    add_action('wp_ajax_salud_get_historial',        'ajax_salud_get_historial');

    add_action('wp_ajax_nopriv_salud_generar_pdf', 'ajax_salud_generar_pdf');
    add_action('wp_ajax_salud_generar_pdf',        'ajax_salud_generar_pdf');
}, 5);
