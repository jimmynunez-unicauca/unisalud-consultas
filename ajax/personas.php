<?php
if (!defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__DIR__) . 'includes/Auth.php';

/**
 * Lista de personas filtradas. Requiere sesión activa.
 */
function ajax_get_unisalud_consulta()
{
    // Validar nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'unisalud_consulta_nonce')) {
        wp_send_json_error(['message' => 'Error de seguridad', 'code' => 'BAD_NONCE'], 403);
    }

    // Requerir sesión
    UsuariosSaludAuth::requerirSesionAjax();

    $nombre  = isset($_POST['nombre']) ? sanitize_text_field(wp_unslash($_POST['nombre'])) : '';
    $estado  = isset($_POST['estado']) ? sanitize_text_field(wp_unslash($_POST['estado'])) : '';
    $pagina  = isset($_POST['pagina']) ? max(1, intval($_POST['pagina'])) : 1;
    $por_pag = 10;
    $offset  = ($pagina - 1) * $por_pag;

    $consultas_path = plugin_dir_path(__DIR__) . 'consultas.php';
    if (!file_exists($consultas_path)) {
        wp_send_json_error('Archivo consultas.php no encontrado');
    }
    require_once $consultas_path;

    if (!class_exists('UsuariosSaludConsultas')) {
        wp_send_json_error('Clase UsuariosSaludConsultas no encontrada');
    }

    $obj          = new UsuariosSaludConsultas();
    $usuarios     = $obj->getUsuariosFiltrados($nombre, $estado, $por_pag, $offset);
    $total        = $obj->getTotalUsuarios($nombre, $estado);
    $total_pags   = ($total > 0) ? (int)ceil($total / $por_pag) : 1;

    wp_send_json_success([
        'usuarios'      => $usuarios,
        'total'         => $total,
        'pagina'        => $pagina,
        'total_paginas' => $total_pags,
    ]);
}
add_action('wp_ajax_nopriv_get_unisalud_consulta', 'ajax_get_unisalud_consulta');
add_action('wp_ajax_get_unisalud_consulta',        'ajax_get_unisalud_consulta');

/**
 * Detalle de persona. Requiere sesión activa.
 */
function ajax_get_persona_detalle_salud()
{
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'unisalud_consulta_nonce')) {
        wp_send_json_error(['message' => 'Error de seguridad', 'code' => 'BAD_NONCE'], 403);
    }

    UsuariosSaludAuth::requerirSesionAjax();

    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if ($id <= 0) {
        wp_send_json_error('ID inválido');
    }

    $consultas_path = plugin_dir_path(__DIR__) . 'consultas.php';
    if (!file_exists($consultas_path)) {
        wp_send_json_error('Archivo consultas.php no encontrado');
    }
    require_once $consultas_path;

    if (!class_exists('UsuariosSaludConsultas')) {
        wp_send_json_error('Clase UsuariosSaludConsultas no encontrada');
    }

    $obj     = new UsuariosSaludConsultas();
    $detalle = $obj->getPersonaDetalle($id);

    if ($detalle === null) {
        wp_send_json_error('Persona no encontrada');
    }

    wp_send_json_success($detalle);
}
add_action('wp_ajax_nopriv_get_persona_detalle_salud', 'ajax_get_persona_detalle_salud');
add_action('wp_ajax_get_persona_detalle_salud',        'ajax_get_persona_detalle_salud');

/**
 * Consulta una persona por número de identificación exacto.
 * Requiere sesión activa.
 */
function ajax_salud_consultar_por_identificacion()
{
    error_log('[CONSULTA] INICIO. POST=' . print_r($_POST, true));
    error_log('[CONSULTA] session_id=' . session_id());
    error_log('[CONSULTA] user_id=' . get_current_user_id());
    error_log('[CONSULTA] session_token=' . wp_get_session_token());

    if (!isset($_POST['nonce'])) {
        error_log('[CONSULTA] nonce NO ENVIADO');
        wp_send_json_error(['message' => 'Error de seguridad', 'code' => 'BAD_NONCE'], 403);
        return;
    }

    $nonce_recibido = sanitize_text_field(wp_unslash($_POST['nonce']));
    $verificacion   = wp_verify_nonce($nonce_recibido, 'usuarios_salud_nonce');

    error_log('[CONSULTA] nonce_recibido=' . $nonce_recibido);
    error_log('[CONSULTA] wp_verify_nonce devuelve=' . var_export($verificacion, true));

    if (!$verificacion) {
        error_log('[CONSULTA] NONCE INVALIDO');
        wp_send_json_error(['message' => 'Error de seguridad', 'code' => 'BAD_NONCE'], 403);
        return;
    }

    error_log('[CONSULTA] nonce OK, continuando...');

    UsuariosSaludAuth::requerirSesionAjax();

    $identificacion = isset($_POST['identificacion']) ? sanitize_text_field(wp_unslash($_POST['identificacion'])) : '';
    if ($identificacion === '') {
        wp_send_json_error(['message' => 'Debes ingresar un número de identificación']);
    }

    $consultas_path = plugin_dir_path(__DIR__) . 'consultas.php';
    if (!file_exists($consultas_path)) {
        wp_send_json_error('Archivo consultas.php no encontrado');
    }
    require_once $consultas_path;

    if (!class_exists('UsuariosSaludConsultas')) {
        wp_send_json_error('Clase UsuariosSaludConsultas no encontrada');
    }

    $obj   = new UsuariosSaludConsultas();
    $datos = $obj->getPersonaPorIdentificacion($identificacion);

    if ($datos === null) {
        wp_send_json_error(['message' => 'No se encontró un afiliado con ese número de identificación']);
    }

    error_log('[CONSULTA] OK, enviando respuesta');
    wp_send_json_success($datos);
}
add_action('wp_ajax_nopriv_salud_consultar_por_identificacion', 'ajax_salud_consultar_por_identificacion');
add_action('wp_ajax_salud_consultar_por_identificacion',        'ajax_salud_consultar_por_identificacion');
