<?php
if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Mailer.php';

class UsuariosSaludOTP
{
    const DURACION_MINUTOS = 0.5;//30s

    /**
     * Busca usuario activo con rol Prestador en PostgreSQL
     */
    public static function buscarUsuarioActivoPorCorreo($correo)
    {
        $pg = UsuariosSaludDatabase::pgsql();
        if (!$pg) {
            return null;
        }

        $sql = "SELECT
                    u.id,
                    u.identificacion,
                    u.nombre_completo,
                    u.correo,
                    u.id_entidad,
                    e.razon_social,
                    r.nombre            AS nombre_rol,
                    ue_est.descripcion  AS estado
                FROM usuarios u
                INNER JOIN entidades e           ON u.id_entidad   = e.id
                INNER JOIN usuarios_roles ur     ON u.id           = ur.id_usuario
                INNER JOIN roles r               ON ur.id_rol      = r.id
                INNER JOIN usuario_estado ue     ON u.id           = ue.id_usuario
                INNER JOIN u_estados ue_est      ON ue.id_estado   = ue_est.id_estado
                WHERE ue_est.descripcion = 'Activo'
                  AND r.nombre = 'Prestador'
                  AND u.correo = :correo
                ORDER BY ue.id_estado_usuario DESC
                LIMIT 1";

        try {
            $stmt = $pg->prepare($sql);
            $stmt->execute(['correo' => $correo]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            return $user ?: null;
        } catch (PDOException $e) {
            error_log('UsuariosSaludOTP buscarUsuario: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Sincroniza el usuario con la tabla MySQL usuarios_otp (UPSERT)
     */
    public static function sincronizarUsuarioMySQL($user)
    {
        $db = UsuariosSaludDatabase::mysql();
        if (!$db) {
            return false;
        }

        $id     = (int)$user['id'];
        $nombre = (string)$user['nombre_completo'];
        $email  = (string)$user['correo'];
        $ident  = (string)$user['identificacion'];

        $sql = "INSERT INTO usuarios_otp (id, nombre, email, identificacion, otp_verified, email_verified)
                VALUES (?, ?, ?, ?, 0, 0)
                ON DUPLICATE KEY UPDATE
                    nombre = VALUES(nombre),
                    email = VALUES(email),
                    identificacion = VALUES(identificacion)";

        $stmt = $db->prepare($sql);
        if (!$stmt) {
            error_log('UsuariosSaludOTP sincronizar prepare: ' . $db->error);
            return false;
        }
        $stmt->bind_param('isss', $id, $nombre, $email, $ident);
        $ok = $stmt->execute();
        $stmt->close();
        return (bool)$ok;
    }

    /**
     * Registra una acción en el historial de auditoría
     */
    private static function auditar($usuario_id, $email, $accion, $otp_code = null, $resultado = 1, $detalle = null)
    {
        $db = UsuariosSaludDatabase::mysql();
        if (!$db) {
            return;
        }

        $ip         = $_SERVER['REMOTE_ADDR']     ?? null;
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;

        if ($user_agent !== null) {
            $user_agent = substr($user_agent, 0, 255);
        }
        if ($detalle !== null) {
            $detalle = substr($detalle, 0, 255);
        }

        $stmt = $db->prepare(
            "INSERT INTO usuarios_otp_historial
                (usuario_id, email, accion, ip, user_agent, otp_code, resultado, detalle)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        if (!$stmt) {
            error_log('UsuariosSaludOTP auditar prepare: ' . $db->error);
            return;
        }
        $stmt->bind_param(
            'isssssis',
            $usuario_id,
            $email,
            $accion,
            $ip,
            $user_agent,
            $otp_code,
            $resultado,
            $detalle
        );
        $stmt->execute();
        $stmt->close();
    }

    /**
     * Wrapper público para auditar desde fuera
     */
    public static function auditarPublico($id, $email, $accion, $otp = null, $resultado = 1, $detalle = null)
    {
        self::auditar($id, $email, $accion, $otp, $resultado, $detalle);
    }

    /**
     * Genera OTP, lo guarda y lo envía por correo
     */
    public static function generarYEnviar($user)
    {
        $db = UsuariosSaludDatabase::mysql();
        if (!$db) {
            return ['success' => false, 'message' => 'Error de conexión MySQL'];
        }

        $otp    = str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        $segundos = (int) round(self::DURACION_MINUTOS * 60);
        $expira   = date('Y-m-d H:i:s', time() + $segundos);
        $id     = (int)$user['id'];

        $stmt = $db->prepare(
            "UPDATE usuarios_otp
             SET otp_code = ?, otp_expires_at = ?, otp_verified = 0
             WHERE id = ?"
        );
        $stmt->bind_param('ssi', $otp, $expira, $id);
        $stmt->execute();
        $stmt->close();

        $enviado = UsuariosSaludMailer::enviarOTP(
            $user['correo'],
            $user['nombre_completo'],
            $otp
        );

        if (!$enviado) {
            self::auditar(
                $id,
                $user['correo'],
                'envio',
                $otp,
                0,
                'Fallo al enviar el correo'
            );
            return ['success' => false, 'message' => 'No se pudo enviar el correo. Intenta nuevamente.'];
        }

        self::auditar(
            $id,
            $user['correo'],
            'envio',
            $otp,
            1,
            'OTP enviado por correo'
        );

        return [
            'success'    => true,
            'message'    => 'Código enviado al correo ' . $user['correo'],
            'email'      => $user['correo'],
            'nombre'     => $user['nombre_completo'],
            'expires_at' => $expira,
            'duracion'   => self::DURACION_MINUTOS,
        ];
    }

    /**
     * Verifica OTP y marca como verificado
     */
    public static function verificar($email, $otp)
    {
        $db = UsuariosSaludDatabase::mysql();
        if (!$db) {
            return null;
        }

        $sql = "SELECT id, nombre, email, identificacion
                FROM usuarios_otp
                WHERE email = ?
                  AND otp_code = ?
                  AND otp_expires_at > NOW()
                  AND otp_verified = 0
                LIMIT 1";

        $stmt = $db->prepare($sql);
        $stmt->bind_param('ss', $email, $otp);
        $stmt->execute();
        $res  = $stmt->get_result();
        $user = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        if (!$user) {
            // Auditar intento fallido
            $stmt2 = $db->prepare("SELECT id FROM usuarios_otp WHERE email = ? LIMIT 1");
            $stmt2->bind_param('s', $email);
            $stmt2->execute();
            $tmp = $stmt2->get_result()->fetch_assoc();
            $stmt2->close();

            if ($tmp) {
                self::auditar(
                    (int)$tmp['id'],
                    $email,
                    'fallo',
                    $otp,
                    0,
                    'Código incorrecto o expirado'
                );
            }
            return null;
        }

        $stmt = $db->prepare(
            "UPDATE usuarios_otp
             SET otp_verified = 1, email_verified = 1
             WHERE id = ?"
        );
        $idInt = (int)$user['id'];
        $stmt->bind_param('i', $idInt);
        $stmt->execute();
        $stmt->close();

        self::auditar(
            $idInt,
            $user['email'],
            'verificacion',
            $otp,
            1,
            'OTP verificado correctamente'
        );

        return $user;
    }

    /**
     * Reenvía OTP (nuevo código, nueva expiración)
     */
    public static function reenviar($email)
    {
        $db = UsuariosSaludDatabase::mysql();
        if (!$db) {
            return null;
        }

        $stmt = $db->prepare(
            "SELECT id, nombre, email FROM usuarios_otp WHERE email = ? LIMIT 1"
        );
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $res  = $stmt->get_result();
        $user = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        if (!$user) {
            return null;
        }

        $otp    = str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        $segundos = (int) round(self::DURACION_MINUTOS * 60);
        $expira   = date('Y-m-d H:i:s', time() + $segundos);
        $id     = (int)$user['id'];

        $stmt = $db->prepare(
            "UPDATE usuarios_otp
             SET otp_code = ?, otp_expires_at = ?, otp_verified = 0
             WHERE id = ?"
        );
        $stmt->bind_param('ssi', $otp, $expira, $id);
        $stmt->execute();
        $stmt->close();

        $enviado = UsuariosSaludMailer::enviarOTP($user['email'], $user['nombre'], $otp);

        self::auditar(
            $id,
            $user['email'],
            'reenvio',
            $otp,
            $enviado ? 1 : 0,
            $enviado ? 'OTP reenviado correctamente' : 'Fallo al reenviar el correo'
        );

        return [
            'email'      => $user['email'],
            'expires_at' => $expira,
            'duracion'   => self::DURACION_MINUTOS,
        ];
    }
}