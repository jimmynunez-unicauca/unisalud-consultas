<?php
if (!defined('ABSPATH')) {
    exit;
}

// Includes base
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/Auth.php';
require_once __DIR__ . '/includes/OTP.php';
require_once __DIR__ . '/includes/Mailer.php';

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
            ['jquery'],
            $version,
            true
        );
        wp_localize_script('usuarios-salud-js', 'usuarios_ajax', [
            'ajax_url'   => admin_url('admin-ajax.php'),
            'nonce'      => wp_create_nonce('unisalud_consulta_nonce'),
            'plugin_url' => $base_url,
            'redirect'   => get_permalink(), // 🔑 útil para recargas
        ]);
        //consulta de afiliados
        wp_enqueue_style(
            'usuarios-salud-consulta-afiliado-css',
            $base_url . 'public/css/style-consulta-afiliado.css',
            [],
            $version
        );
        wp_enqueue_script(
            'usuarios-salud-consulta-afiliado-js',
            $base_url . 'public/js/script-consulta-afiliado.js',
            ['jquery'],
            $version,
            true
        );
        wp_localize_script('usuarios-salud-consulta-afiliado-js', 'usuarios_consulta_afiliado_ajax', [
            'ajax_url'   => admin_url('admin-ajax.php'),
            'nonce'      => wp_create_nonce('usuarios_salud_nonce'),
            'redirect'   => get_permalink(),
        ]);
        //consulta de afiliados
        
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
            ['jquery'],
            $version,
            true
        );
        wp_localize_script('usuarios-salud-otp-js', 'usuarios_otp_ajax', [
            'ajax_url'   => admin_url('admin-ajax.php'),
            'nonce'      => wp_create_nonce('unisalud_consulta_nonce'),
            'redirect'   => get_permalink(),
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
 * Shortcode principal: decide qué plantilla mostrar
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

    // ==== Nonce fresco (evita caché de HTML) ====
    add_action('wp_ajax_nopriv_salud_fresh_nonce', 'ajax_salud_fresh_nonce');
    add_action('wp_ajax_salud_fresh_nonce',        'ajax_salud_fresh_nonce');

    // ==== Debug ====
    add_action('wp_ajax_nopriv_salud_debug_estado', 'ajax_salud_debug_estado');
    add_action('wp_ajax_salud_debug_estado',        'ajax_salud_debug_estado');

    // ==== Personas ====
    add_action('wp_ajax_nopriv_get_unisalud_consulta',        'ajax_get_unisalud_consulta');
    add_action('wp_ajax_get_unisalud_consulta',               'ajax_get_unisalud_consulta');
    add_action('wp_ajax_nopriv_get_persona_detalle_salud', 'ajax_get_persona_detalle_salud');
    add_action('wp_ajax_get_persona_detalle_salud',        'ajax_get_persona_detalle_salud');
}, 5);
