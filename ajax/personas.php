<?php
if (!defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__DIR__) . 'includes/Auth.php';
require_once plugin_dir_path(__DIR__) . 'includes/Historial.php';

/**
 * Lista de personas filtradas. Requiere sesión activa.
 */
function ajax_get_unisalud_consulta()
{
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'unisalud_consulta_nonce')) {
        wp_send_json_error(['message' => 'Error de seguridad', 'code' => 'BAD_NONCE'], 403);
    }

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

/**
 * Consulta una persona por número de identificación exacto.
 * Devuelve el cotizante + sus beneficiarios.
 * Guarda la consulta en el historial del usuario activo.
 */
function ajax_salud_consultar_por_identificacion()
{
    error_log('[CONSULTA] ===== INICIO =====');

    // 1) Validar nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'usuarios_salud_nonce')) {
        error_log('[CONSULTA] nonce inválido');
        wp_send_json_error(['message' => 'Error de seguridad', 'code' => 'BAD_NONCE'], 403);
        return;
    }

    // 2) Requerir sesión y obtener usuario
    $usuario = UsuariosSaludAuth::requerirSesionAjax();
    error_log('[CONSULTA] usuario=' . print_r($usuario, true));

    // 3) Validar identificación
    $identificacion = isset($_POST['identificacion'])
        ? sanitize_text_field(wp_unslash($_POST['identificacion']))
        : '';

    if ($identificacion === '') {
        wp_send_json_error(['message' => 'Debes ingresar un número de identificación']);
        return;
    }

    // 4) Cargar consultas
    $consultas_path = plugin_dir_path(__DIR__) . 'consultas.php';
    if (!file_exists($consultas_path)) {
        wp_send_json_error(['message' => 'Archivo consultas.php no encontrado']);
        return;
    }
    require_once $consultas_path;

    if (!class_exists('UsuariosSaludConsultas')) {
        wp_send_json_error(['message' => 'Clase UsuariosSaludConsultas no encontrada']);
        return;
    }

    // 5) Ejecutar consulta
    $obj   = new UsuariosSaludConsultas();
    $datos = $obj->getPersonaPorIdentificacion($identificacion);

    if ($datos === null || empty($datos)) {
        wp_send_json_error(['message' => 'No se encontró un afiliado con ese número de identificación']);
        return;
    }

    // 6) Guardar en historial ANTES de wp_send_json_success
    if (!empty($usuario['id'])) {
        $primer = $datos[0] ?? null;

        error_log('[CONSULTA] Guardando historial: usuario_id=' . $usuario['id']
            . ' email=' . ($usuario['email'] ?? '')
            . ' identificacion=' . $identificacion);

        $guardado = UsuariosSaludHistorial::guardar(
            (int)$usuario['id'],
            (string)($usuario['email'] ?? ''),
            $identificacion,
            $primer ? (string)($primer['nombre_completo'] ?? '') : '',
            $primer ? (string)($primer['tipo_afiliado']   ?? '') : '',
            ($primer && strtoupper($primer['tipo_afiliado'] ?? '') === 'BENEFICIARIO') ? 1 : 0
        );

        error_log('[CONSULTA] Resultado del guardado: ' . var_export($guardado, true));
    } else {
        error_log('[CONSULTA] ⚠️ usuario_id vacío, NO se guarda');
    }

    // 7) Responder
    wp_send_json_success($datos);
}

/**
 * Devuelve el historial de consultas del usuario actual.
 */
function ajax_salud_get_historial()
{
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'usuarios_salud_nonce')) {
        wp_send_json_error(['message' => 'Error de seguridad', 'code' => 'BAD_NONCE'], 403);
    }

    $usuario = UsuariosSaludAuth::requerirSesionAjax();

    $pagina     = isset($_POST['pagina']) ? max(1, intval($_POST['pagina'])) : 1;
    $porPagina  = 10;
    $offset     = ($pagina - 1) * $porPagina;

    $historial = UsuariosSaludHistorial::obtenerPorUsuario($usuario['id'], $porPagina, $offset);
    $total     = UsuariosSaludHistorial::contarPorUsuario($usuario['id']);

    foreach ($historial as &$row) {
        if (!empty($row['fecha_consulta'])) {
            $ts = strtotime($row['fecha_consulta']);
            $row['fecha_formateada'] = date('d/m/Y H:i', $ts);
        } else {
            $row['fecha_formateada'] = '';
        }
        $row['tipo_afiliado'] = strtoupper($row['tipo_afiliado'] ?? '');
    }
    unset($row);

    wp_send_json_success([
        'historial'     => $historial,
        'total'         => $total,
        'pagina'        => $pagina,
        'total_paginas' => ($total > 0) ? (int)ceil($total / $porPagina) : 1,
    ]);
}

// ⚠️ NO añadir add_action aquí. Se registran en shortcode.php