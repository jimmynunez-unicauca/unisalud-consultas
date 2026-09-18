<?php
if (!defined('ABSPATH')) {
    exit;
}

// Forzar inicio de sesión en el contexto AJAX
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

require_once plugin_dir_path(__DIR__) . 'includes/Database.php';
require_once plugin_dir_path(__DIR__) . 'includes/Auth.php';
require_once plugin_dir_path(__DIR__) . 'includes/OTP.php';

/**
 * Valida el nonce compartido
 */
function unisalud_consulta_check_nonce()
{
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'unisalud_consulta_nonce')) {
        wp_send_json_error(['message' => 'Error de seguridad', 'code' => 'BAD_NONCE'], 403);
    }
}

/**
 * Devuelve un nonce fresco (evita problemas con caché de HTML)
 */
function ajax_salud_fresh_nonce()
{
    error_log('[FRESH_NONCE] session_id=' . session_id());
    error_log('[FRESH_NONCE] user_id=' . get_current_user_id());
    error_log('[FRESH_NONCE] session_token=' . wp_get_session_token());

    $nonce = wp_create_nonce('usuarios_salud_nonce');
    error_log('[FRESH_NONCE] nonce creado=' . $nonce);

    wp_send_json_success(['nonce' => $nonce]);
}

/**
 * Buscar usuario activo por correo
 */
function ajax_salud_buscar_correos()
{
    unisalud_consulta_check_nonce();

    $correo = isset($_POST['correo']) ? sanitize_email(wp_unslash($_POST['correo'])) : '';
    if (empty($correo)) {
        wp_send_json_error(['message' => 'Por favor ingresa tu correo']);
    }

    $user = UsuariosSaludOTP::buscarUsuarioActivoPorCorreo($correo);
    if (!$user) {
        wp_send_json_error(['message' => "No se encontró un usuario activo con el correo '{$correo}'"]);
    }

    wp_send_json_success([
        'id'             => $user['id'],
        'nombre'         => $user['nombre_completo'],
        'identificacion' => $user['identificacion'],
        'correo'         => $user['correo'],
    ]);
}

/**
 * Enviar OTP (sincroniza y genera)
 */
function ajax_salud_enviar_otp()
{
    unisalud_consulta_check_nonce();

    $correo = isset($_POST['correo']) ? sanitize_email(wp_unslash($_POST['correo'])) : '';
    if (empty($correo)) {
        wp_send_json_error(['message' => 'Dato incompleto']);
    }

    $user = UsuariosSaludOTP::buscarUsuarioActivoPorCorreo($correo);
    if (!$user) {
        wp_send_json_error(['message' => 'Usuario no encontrado']);
    }

    UsuariosSaludOTP::sincronizarUsuarioMySQL($user);
    $resultado = UsuariosSaludOTP::generarYEnviar($user);

    if (empty($resultado['success'])) {
        wp_send_json_error(['message' => $resultado['message'] ?? 'Error al enviar el código']);
    }

    wp_send_json_success($resultado);
}

/**
 * Verificar OTP y crear sesión
 */
function ajax_salud_verificar_otp()
{
    unisalud_consulta_check_nonce();

    $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
    $otp   = isset($_POST['otp'])   ? trim(wp_unslash($_POST['otp']))              : '';

    if (empty($otp) || !preg_match('/^\d{6}$/', $otp)) {
        wp_send_json_error(['message' => 'El código debe tener 6 dígitos']);
    }
    if (empty($email)) {
        wp_send_json_error(['message' => 'Correo no especificado']);
    }

    $user = UsuariosSaludOTP::verificar($email, $otp);
    if (!$user) {
        wp_send_json_error(['message' => 'Código incorrecto o expirado']);
    }

    UsuariosSaludAuth::crearSesion([
        'id'             => $user['id'],
        'nombre'         => $user['nombre'],
        'email'          => $user['email'],
        'identificacion' => $user['identificacion'] ?? '',
    ]);

    wp_send_json_success([
        'nombre' => $user['nombre'],
        'email'  => $user['email'],
    ]);
}

/**
 * Reenviar OTP
 */
function ajax_salud_reenviar_otp()
{
    unisalud_consulta_check_nonce();

    $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
    if (empty($email)) {
        wp_send_json_error(['message' => 'Correo no especificado']);
    }

    $resultado = UsuariosSaludOTP::reenviar($email);
    if (!$resultado) {
        wp_send_json_error(['message' => 'Usuario no encontrado']);
    }

    wp_send_json_success($resultado);
}

/**
 * Cerrar sesión.
 * Sin validación de nonce a propósito: es un endpoint que destruye la propia
 * sesión del usuario. No hay riesgo CSRF real (forzar logout no da beneficio).
 */
function ajax_salud_cerrar_sesion()
{
    error_log('[LOGOUT] INICIO. POST=' . print_r($_POST, true));
    error_log('[LOGOUT] session_id antes=' . session_id());
    error_log('[LOGOUT] SESSION antes=' . print_r($_SESSION, true));

    UsuariosSaludAuth::cerrarSesion();

    error_log('[LOGOUT] session_id despues=' . session_id());
    error_log('[LOGOUT] SESSION despues=' . print_r($_SESSION, true));

    wp_send_json_success(['message' => 'Sesión cerrada']);
}
add_action('wp_ajax_nopriv_salud_cerrar_sesion', 'ajax_salud_cerrar_sesion');
add_action('wp_ajax_salud_cerrar_sesion',        'ajax_salud_cerrar_sesion');

/**
 * DEBUG: Ver estado de la sesión y conexiones
 */
function ajax_salud_debug_estado()
{
    $out = [
        'session_status'       => session_status(),
        'session_id'           => session_id(),
        'session_name'         => session_name(),
        'session_data'         => $_SESSION,
        'abspath_defined'      => defined('ABSPATH'),
        'wp_plugin_dir'        => defined('WP_PLUGIN_DIR') ? WP_PLUGIN_DIR : null,
        'mysql_class_exists'   => class_exists('Plugin\\ConfigUnicauca\\Conexion'),
        'pgsql_class_exists'   => class_exists('ConexionPostgresql'),
    ];

    if (class_exists('UsuariosSaludDatabase')) {
        $mysql = UsuariosSaludDatabase::mysql();
        $out['mysql_ok'] = ($mysql !== null);
        $pg = UsuariosSaludDatabase::pgsql();
        $out['pgsql_ok'] = ($pg !== null);
    }

    wp_send_json_success($out);
}
