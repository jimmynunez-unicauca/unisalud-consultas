<?php
if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/Database.php';

class UsuariosSaludAuth
{
    // ============================================================
    //  CONFIGURACIÓN DE TIEMPOS (AJUSTAR AQUÍ)
    // ============================================================
    const SESSION_KEY           = 'unisalud_consulta_auth';
    const DURACION_SEGUNDOS     = 7200;   // 2 horas  → sesión absoluta (NO cambiar)
    const INACTIVIDAD_SEGUNDOS  = 900;    // 15 min   → cierre por inactividad
    const AVISO_SEGUNDOS        = 60;     // 60 seg   → aviso previo antes de cerrar

    // ============================================================
    //  INICIAR SESIÓN
    // ============================================================
    public static function iniciarSesionSiNoExiste()
    {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            session_start();
        }
    }

    // ============================================================
    //  VALIDAR SESIÓN (absoluta + inactividad)
    // ============================================================
    public static function validarSesion()
    {
        self::iniciarSesionSiNoExiste();

        if (empty($_SESSION[self::SESSION_KEY]['id'])) {
            return ['valid' => false, 'message' => 'Sesión no iniciada', 'usuario' => null];
        }

        $sesion = $_SESSION[self::SESSION_KEY];
        $creada = isset($sesion['created_at'])    ? (int)$sesion['created_at']    : 0;
        $ultima = isset($sesion['last_activity']) ? (int)$sesion['last_activity'] : 0;

        // ── 1) Chequeo absoluto (2 horas) ──
        if ($creada === 0 || (time() - $creada) > self::DURACION_SEGUNDOS) {
            return ['valid' => false, 'message' => 'Sesión expirada (tiempo máximo)', 'usuario' => null];
        }

        // ── 2) Chequeo por inactividad ──
        if ($ultima === 0 || (time() - $ultima) > self::INACTIVIDAD_SEGUNDOS) {
            return ['valid' => false, 'message' => 'Sesión expirada (inactividad)', 'usuario' => null];
        }

        // ── 3) Verificar usuario en BD ──
        try {
            $db = UsuariosSaludDatabase::mysql();
            if (!$db) {
                return ['valid' => false, 'message' => 'Sin conexión MySQL', 'usuario' => null];
            }

            $stmt = $db->prepare(
                "SELECT id, nombre, email, identificacion
                 FROM usuarios_otp
                 WHERE id = ? AND otp_verified = 1 AND email_verified = 1
                 LIMIT 1"
            );
            $id = (int)$sesion['id'];
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $res  = $stmt->get_result();
            $user = $res ? $res->fetch_assoc() : null;
            $stmt->close();

            if (!$user) {
                return ['valid' => false, 'message' => 'Usuario no válido', 'usuario' => null];
            }

            return ['valid' => true, 'usuario' => $user, 'message' => 'OK'];
        } catch (Exception $e) {
            error_log('UsuariosSaludAuth error: ' . $e->getMessage());
            return ['valid' => false, 'message' => 'Error del servidor', 'usuario' => null];
        }
    }

    // ============================================================
    //  REQUERIR SESIÓN PARA AJAX
    // ============================================================
    public static function requerirSesionAjax()
    {
        $r = self::validarSesion();
        if (!$r['valid']) {
            wp_send_json_error(
                ['message' => $r['message'], 'code' => 'UNAUTHORIZED'],
                401
            );
        }
        return $r['usuario'];
    }

    // ============================================================
    //  CREAR SESIÓN (tras verificar OTP)
    // ============================================================
    public static function crearSesion($usuario)
    {
        self::iniciarSesionSiNoExiste();

        $_SESSION[self::SESSION_KEY] = [
            'id'             => (int)$usuario['id'],
            'nombre'         => $usuario['nombre']         ?? '',
            'email'          => $usuario['email']          ?? '',
            'identificacion' => $usuario['identificacion'] ?? '',
            'created_at'     => time(),
            'last_activity'  => time(),   // ← nuevo
        ];

        if (function_exists('session_regenerate_id')) {
            @session_regenerate_id(true);
        }
    }

    // ============================================================
    //  REGISTRAR ACTIVIDAD
    //  Solo actualiza last_activity si la sesión sigue válida.
    //  Se llama desde:
    //    - salud_activity_ping (JS con actividad real en el navegador)
    //    - acciones de negocio (consulta, PDF, historial)
    // ============================================================
    public static function registrarActividad()
    {
        self::iniciarSesionSiNoExiste();

        if (empty($_SESSION[self::SESSION_KEY]['id'])) {
            return false;
        }

        // Verificar que la sesión no haya expirado antes de renovar
        $sesion = $_SESSION[self::SESSION_KEY];
        $creada = isset($sesion['created_at'])    ? (int)$sesion['created_at']    : 0;
        $ultima = isset($sesion['last_activity']) ? (int)$sesion['last_activity'] : 0;

        // Si ya expiró por absoluto o por inactividad, NO renovar
        if ($creada === 0 || (time() - $creada) > self::DURACION_SEGUNDOS) {
            return false;
        }
        if ($ultima === 0 || (time() - $ultima) > self::INACTIVIDAD_SEGUNDOS) {
            return false;
        }

        $_SESSION[self::SESSION_KEY]['last_activity'] = time();
        return true;
    }

    // ============================================================
    //  TIEMPOS RESTANTES (para el frontend)
    // ============================================================
    public static function tiemposRestantes()
    {
        self::iniciarSesionSiNoExiste();

        if (empty($_SESSION[self::SESSION_KEY]['id'])) {
            return ['absoluto' => 0, 'inactividad' => 0];
        }

        $sesion = $_SESSION[self::SESSION_KEY];
        $creada = isset($sesion['created_at'])    ? (int)$sesion['created_at']    : time();
        $ultima = isset($sesion['last_activity']) ? (int)$sesion['last_activity'] : time();

        return [
            'absoluto'    => max(0, self::DURACION_SEGUNDOS    - (time() - $creada)),
            'inactividad' => max(0, self::INACTIVIDAD_SEGUNDOS - (time() - $ultima)),
        ];
    }

    // ============================================================
    //  CERRAR SESIÓN
    // ============================================================
    public static function cerrarSesion()
    {
        self::iniciarSesionSiNoExiste();

        $id    = $_SESSION[self::SESSION_KEY]['id']    ?? null;
        $email = $_SESSION[self::SESSION_KEY]['email'] ?? '';

        if ($id) {
            try {
                $db = UsuariosSaludDatabase::mysql();
                if ($db) {
                    $stmt = $db->prepare(
                        "UPDATE usuarios_otp
                         SET otp_code = NULL, otp_expires_at = NULL
                         WHERE id = ?"
                    );
                    $idInt = (int)$id;
                    $stmt->bind_param('i', $idInt);
                    $stmt->execute();
                    $stmt->close();
                }

                require_once __DIR__ . '/OTP.php';
                if (class_exists('UsuariosSaludOTP') && $email !== '') {
                    UsuariosSaludOTP::auditarPublico(
                        (int)$id,
                        $email,
                        'logout',
                        null,
                        1,
                        'Sesión cerrada por el usuario'
                    );
                }
            } catch (Exception $e) {
                error_log('UsuariosSaludAuth cerrarSesion: ' . $e->getMessage());
            }
        }

        $_SESSION = [];

        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"] ?: '/',
                $params["domain"] ?: '',
                $params["secure"] ?? false,
                $params["httponly"] ?? true
            );
            setcookie(session_name(), '', time() - 42000, '/');
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }
}
