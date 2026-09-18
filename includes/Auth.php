<?php
if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/Database.php';

class UsuariosSaludAuth
{
    const SESSION_KEY       = 'unisalud_consulta_auth';
    const DURACION_SEGUNDOS = 7200; // 2 horas

    /**
     * Asegura que la sesión PHP esté iniciada
     */
    public static function iniciarSesionSiNoExiste()
    {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            session_start();
        }
    }

    /**
     * Valida sesión activa y usuario en BD
     */
    public static function validarSesion()
    {
        self::iniciarSesionSiNoExiste();

        if (empty($_SESSION[self::SESSION_KEY]['id'])) {
            return ['valid' => false, 'message' => 'Sesión no iniciada', 'usuario' => null];
        }

        $sesion = $_SESSION[self::SESSION_KEY];
        $creada = isset($sesion['created_at']) ? (int)$sesion['created_at'] : 0;

        if ($creada === 0 || (time() - $creada) > self::DURACION_SEGUNDOS) {
            return ['valid' => false, 'message' => 'Sesión expirada', 'usuario' => null];
        }

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

    /**
     * Para endpoints AJAX: corta con 401 si no hay sesión válida
     */
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

    /**
     * Crea la sesión tras verificar OTP
     */
    public static function crearSesion($usuario)
    {
        self::iniciarSesionSiNoExiste();

        $_SESSION[self::SESSION_KEY] = [
            'id'             => (int)$usuario['id'],
            'nombre'         => $usuario['nombre']         ?? '',
            'email'          => $usuario['email']          ?? '',
            'identificacion' => $usuario['identificacion'] ?? '',
            'created_at'     => time(),
        ];

        if (function_exists('session_regenerate_id')) {
            @session_regenerate_id(true);
        }
    }

    /**
     * Cierra sesión y limpia OTP en BD (auditado)
     */
    public static function cerrarSesion()
    {
        self::iniciarSesionSiNoExiste();

        $id    = $_SESSION[self::SESSION_KEY]['id']    ?? null;
        $email = $_SESSION[self::SESSION_KEY]['email'] ?? '';

        // 1) Limpiar OTP en MySQL y auditar
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

        // 2) Vaciar sesión en memoria
        $_SESSION = [];

        // 3) Borrar cookies de sesión
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

        // 4) Destruir la sesión en el servidor
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }
}
