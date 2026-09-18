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
 * Buscar Identificacion OTP
 */
function ajax_buscar_identificacion_afiliado()
{
    //UsuariosSaludAuth::requerirSesionAjax();
    var_dump("php usuarios identificacion: ".$_POST['identificacion']);
    $identificacion = isset($_POST['identificacion'])
    ? preg_replace('/[^0-9]/', '', wp_unslash($_POST['identificacion']))
    : '';
        
    if (empty($identificacion)) {
        wp_send_json_error(['message' => 'Identificacion no especificado']);
    }

    $resultado = UsuariosSaludAuth::buscarIdentificacionAfiliado($_POST['identificacion']);
    var_dump("php usuarios resultado: ".$resultado);
    if (!$resultado) {
        wp_send_json_error(['message' => 'Usuario no encontrado']);
    }

    wp_send_json_success($resultado);
}
add_action('wp_ajax_nopriv_buscar_identificacion_afiliado', 'ajax_buscar_identificacion_afiliado');
add_action('wp_ajax_buscar_identificacion_afiliado',        'ajax_buscar_identificacion_afiliado');
