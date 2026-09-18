<?php
if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/Database.php';

class UsuariosSaludAuth
{
    const SESSION_KEY       = 'unisalud_consulta_auth';
    const DURACION_SEGUNDOS = 3600;//1h    2 horas 7200

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

                // Auditar logout
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

        unset($_SESSION[self::SESSION_KEY]);
    }


    /**
     * Devuelve segundos restantes de la sesión actual
     */
    public static function segundosRestantes()
    {
        self::iniciarSesionSiNoExiste();

        if (empty($_SESSION[self::SESSION_KEY]['created_at'])) {
            return 0;
        }

        $transcurrido = time() - (int)$_SESSION[self::SESSION_KEY]['created_at'];
        return max(0, self::DURACION_SEGUNDOS - $transcurrido);
    }

    /**
     * Alias para leer los datos de la sesión
     */
    public static function datosSesion()
    {
        self::iniciarSesionSiNoExiste();
        return $_SESSION[self::SESSION_KEY] ?? null;
    }


    public static function buscarIdentificacionAfiliado($identificacionAfiliado)
    {
        self::iniciarSesionSiNoExiste();

        try {
            $db = UsuariosSaludDatabase::pgsql();
            if (!$db) {
                return ['valid' => false, 'message' => 'Sin conexión', 'usuario' => null];
            }
            // ============================================================
            // 1) Datos base de la persona
            // ============================================================
            $stmt = $db->prepare(
                "SELECT
                    p.id,
                    p.tipoidentificacion,
                    p.numerodocumento,
                    p.primernombre,
                    p.segundonombre,
                    p.primerapellido,
                    p.segundoapellido,
                    p.fechanacimiento,
                    p.sexo,
                    p.tiposangre,
                    p.tiporh,
                    -- Último estado
                    ult.tipoafiliado,
                    ult.tipo_estado,
                    ult.fecha_inicio_estado AS fecha_afiliacion,
                    ult.estado_descripcion,
                    -- Última afiliación
                    af.tipoafiliacion,
                    af.fecharadicacion,
                    ns.descripcion          AS nivel_salarial_desc,
                    ns.nivel                AS nivel_salarial_codigo
                FROM personas p
                LEFT JOIN LATERAL (
                    SELECT pe.tipoafiliado, pe.tipo_estado,
                           pe.fecha_inicio_estado,
                           e.descripcion AS estado_descripcion
                    FROM personas_estados pe
                    LEFT JOIN estados e ON e.id = pe.id_estado
                    WHERE pe.id_persona = p.id
                    ORDER BY pe.id DESC
                    LIMIT 1
                ) ult ON TRUE
                LEFT JOIN LATERAL (
                    SELECT a.id, a.tipoafiliacion, a.fecharadicacion, a.id_nivel_salarial
                    FROM afiliaciones a
                    WHERE a.id_cotizante = p.id
                    ORDER BY a.fecharadicacion DESC NULLS LAST, a.id DESC
                    LIMIT 1
                ) af ON TRUE
                LEFT JOIN niveles_salariales ns ON ns.id = af.id_nivel_salarial
                WHERE p.numerodocumento = :identificacion
                LIMIT 1"
            );

            if (!$stmt) {
                return ['valid' => false, 'message' => 'Error al preparar', 'usuario' => null];
            }

            $stmt->execute(['identificacion' => $identificacionAfiliado]);
            $persona = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // VALIDACIÓN CRÍTICA
            if (!$persona) {
                return [
                    'valid'   => false,
                    'message' => 'Afiliado no encontrado',
                    'usuario' => null
                ];
            }

            $idPersona = $persona['id'];        

            // ============================================================
            // 2) ¿Es cotizante? Si sí, traemos sus beneficiarios
            // ============================================================            
            $stmt = $db->prepare("SELECT id FROM cotizantes WHERE id_persona = :idPersona LIMIT 1");
            $stmt->execute(['idPersona' => $idPersona]);
            $esCotizante = $stmt->fetch(PDO::FETCH_ASSOC); 

            $beneficiarios = [];
            if ($esCotizante && !empty($esCotizante['id'])) {       
                $stmt = $db->prepare("SELECT
                        p.id,
                        p.tipoidentificacion,
                        p.numerodocumento,
                        p.primernombre,
                        p.segundonombre,
                        p.primerapellido,
                        p.segundoapellido,
                        p.fechanacimiento,
                        p.sexo,
                        p.tiposangre,
                        p.tiporh,
                        cb.parentescobeneficiario,
                        cb.numeroradicacion,
                        -- Último estado del beneficiario
                        ult.tipoafiliado,
                        ult.tipo_estado,
                        ult.fecha_inicio_estado AS fecha_afiliacion,
                        ult.estado_descripcion
                    FROM cotizantes c
                    INNER JOIN cotizantes_beneficiarios cb
                            ON cb.id_cotizante = c.id
                    INNER JOIN beneficiarios b
                            ON b.id = cb.id_beneficiario
                    INNER JOIN personas p
                            ON p.id = b.id_persona
                    LEFT JOIN LATERAL (
                        SELECT pe.tipoafiliado, pe.tipo_estado,
                               pe.fecha_inicio_estado,
                               e.descripcion AS estado_descripcion
                        FROM personas_estados pe
                        LEFT JOIN estados e ON e.id = pe.id_estado
                        WHERE pe.id_persona = p.id
                        ORDER BY pe.id DESC
                        LIMIT 1
                    ) ult ON TRUE
                    WHERE c.id = :id
                    ORDER BY cb.parentescobeneficiario, p.primerapellido, p.primernombre");
                $stmt->execute(['id' => $esCotizante['id']]);
                $beneficiarios = $stmt->fetchAll(PDO::FETCH_ASSOC);                  
            }
            // ============================================================
            // 3) Formatear todo y armar el array final
            // ============================================================
            $resultado = [];            
            $resultado[] = $persona;
            $resultado[] = $beneficiarios;
            
            return [
                'valid'   => true,
                'usuario' => $resultado,
                'message' => 'OK'
            ];

        } catch (Throwable $e) {
            error_log('UsuariosSaludAuth error: ' . $e->getMessage()
                . ' @ ' . $e->getFile() . ':' . $e->getLine());

            return [
                'valid'   => false,
                'message' => 'Error del servidor',
                'usuario' => null
            ];
        }
    }    


}