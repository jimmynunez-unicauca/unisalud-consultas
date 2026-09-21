<?php
if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/Database.php';

class UsuariosSaludHistorial
{
    /**
     * Guarda una consulta en el historial.
     */
    public static function guardar($usuarioId, $usuarioEmail, $identificacion, $nombreConsultado = '', $tipoAfiliado = '', $esBeneficiario = 0)
    {
        $db = UsuariosSaludDatabase::mysql();
        if (!$db) {
            error_log('[HISTORIAL] sin conexión MySQL');
            return false;
        }

        // ⚠️ Convertir null a strings vacíos para evitar errores con bind_param
        $ip = $_SERVER['REMOTE_ADDR']     ?? '';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        if ($ua !== '') {
            $ua = substr($ua, 0, 255);
        }

        $sql = "INSERT INTO historial_consultas_unisalud
                (usuario_id, usuario_email, identificacion_consultada,
                 nombre_consultado, tipo_afiliado, es_beneficiario, ip, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $db->prepare($sql);
        if (!$stmt) {
            error_log('[HISTORIAL] prepare ERROR: ' . $db->error);
            return false;
        }

        $idInt    = (int)$usuarioId;
        $esBenInt = (int)$esBeneficiario;

        $stmt->bind_param(
            'issssiss',
            $idInt,
            $usuarioEmail,
            $identificacion,
            $nombreConsultado,
            $tipoAfiliado,
            $esBenInt,
            $ip,
            $ua
        );

        $ok = $stmt->execute();

        if (!$ok) {
            error_log('[HISTORIAL] execute ERROR: ' . $stmt->error);
        } else {
            error_log('[HISTORIAL] ✅ Guardado ID=' . $stmt->insert_id);
        }

        $stmt->close();
        return (bool)$ok;
    }

    /**
     * Obtiene el historial de un usuario (solo el suyo).
     */
    public static function obtenerPorUsuario($usuarioId, $limit = 20, $offset = 0)
    {
        $db = UsuariosSaludDatabase::mysql();
        if (!$db) {
            return [];
        }

        $sql = "SELECT id, identificacion_consultada, nombre_consultado,
                       tipo_afiliado, es_beneficiario, fecha_consulta
                FROM historial_consultas_unisalud
                WHERE usuario_id = ?
                ORDER BY fecha_consulta DESC, id DESC
                LIMIT ? OFFSET ?";

        $stmt = $db->prepare($sql);
        if (!$stmt) {
            error_log('[HISTORIAL] prepare: ' . $db->error);
            return [];
        }

        $idInt  = (int)$usuarioId;
        $limInt = (int)$limit;
        $offInt = (int)$offset;

        $stmt->bind_param('iii', $idInt, $limInt, $offInt);
        $stmt->execute();
        $res  = $stmt->get_result();
        $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();

        return $rows;
    }

    /**
     * Cuenta el total del historial de un usuario.
     */
    public static function contarPorUsuario($usuarioId)
    {
        $db = UsuariosSaludDatabase::mysql();
        if (!$db) {
            return 0;
        }

        $stmt = $db->prepare(
            "SELECT COUNT(*) AS total FROM historial_consultas_unisalud WHERE usuario_id = ?"
        );
        $idInt = (int)$usuarioId;
        $stmt->bind_param('i', $idInt);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        return $row ? (int)$row['total'] : 0;
    }
}
