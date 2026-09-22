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
            'created_at'     => time(),   // ← ⚠️ ESTA LÍNEA ES CRÍTICA
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

    public static function buscarUsuarioActivoPorIdentificacion($identificacion)
    {    
        $pg = UsuariosSaludDatabase::pgsql();
        if (!$pg) {
            return null;
        }

        $sql = "SELECT
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
                LIMIT 1";

        try {
            $stmt = $pg->prepare($sql);
            $stmt->execute(['identificacion' => $identificacion]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            return $user ?: null;
        } catch (PDOException $e) {
            error_log('UsuariosSaludOTP buscarUsuario: ' . $e->getMessage());
            return null;
        }
    }
    public static function salud_render_certificado_html($personas, $tipo_certificado, $dirigido)
    {
        if (!is_array($personas) || empty($personas)) {
            return '<p>No hay información del afiliado.</p>';
        }

        $fecha_generacion = date('d/m/Y H:i');
        $es_grupo         = strtoupper($tipo_certificado) === 'GRUPO FAMILIAR';
        $titulo_tipo      = $es_grupo ? 'GRUPO FAMILIAR' : 'INDIVIDUAL';

        ob_start();
?>
        <!DOCTYPE html>
        <html lang="es">

        <head>
            <meta charset="UTF-8">
            <style>
                @page {
                    margin: 40px 30px;
                }

                body {
                    font-family: DejaVu Sans, sans-serif;
                    font-size: 11px;
                    color: #2c3e50;
                }

                .header {
                    text-align: center;
                    border-bottom: 3px solid #1a3c6e;
                    padding-bottom: 12px;
                    margin-bottom: 22px;
                }

                .header h1 {
                    color: #1a3c6e;
                    margin: 0 0 4px 0;
                    font-size: 20px;
                    letter-spacing: 1px;
                }

                .header p {
                    margin: 2px 0;
                    color: #7f8c8d;
                    font-size: 11px;
                }

                .titulo {
                    text-align: center;
                    font-size: 15px;
                    font-weight: bold;
                    color: #1a3c6e;
                    margin: 18px 0 6px 0;
                }

                .subtitulo {
                    text-align: center;
                    font-size: 11px;
                    color: #7f8c8d;
                    margin-bottom: 18px;
                }

                .info-dirigido {
                    background: #f0f4f9;
                    border-left: 4px solid #1a3c6e;
                    padding: 8px 12px;
                    margin-bottom: 16px;
                }

                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-top: 8px;
                    font-size: 10px;
                }

                th {
                    background: #1a3c6e;
                    color: #fff;
                    padding: 7px 6px;
                    text-align: left;
                    border: 1px solid #1a3c6e;
                }

                td {
                    padding: 6px;
                    border: 1px solid #dcdde1;
                    vertical-align: top;
                }

                tr:nth-child(even) td {
                    background: #fafbfc;
                }

                .badge {
                    display: inline-block;
                    padding: 2px 8px;
                    border-radius: 10px;
                    font-size: 9px;
                    font-weight: bold;
                }

                .badge-cotizante {
                    background: #d4edda;
                    color: #155724;
                }

                .badge-benef {
                    background: #fff3cd;
                    color: #856404;
                }

                .firma {
                    margin-top: 50px;
                    text-align: center;
                    font-size: 10px;
                    color: #555;
                }

                .firma-linea {
                    width: 220px;
                    border-top: 1px solid #555;
                    margin: 0 auto 4px auto;
                }

                .footer {
                    margin-top: 40px;
                    padding-top: 12px;
                    border-top: 1px solid #dcdde1;
                    font-size: 9px;
                    color: #95a5a6;
                    text-align: center;
                    line-height: 1.5;
                }
            </style>
        </head>

        <body>

            <div class="header">
                <h1>UNIDAD DE SALUD</h1>
                <p>Sistema de Consulta de Afiliados</p>
            </div>

            <div class="titulo">CERTIFICADO DE AFILIACIÓN</div>
            <div class="subtitulo">Tipo: <?= htmlspecialchars($titulo_tipo, ENT_QUOTES, 'UTF-8') ?></div>

            <?php if ($dirigido !== ''): ?>
                <div class="info-dirigido">
                    <strong>Dirigido a:</strong> <?= htmlspecialchars($dirigido, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <table>
                <thead>
                    <tr>
                        <th style="width:12%;">Tipo</th>
                        <th style="width:15%;">Identificación</th>
                        <th style="width:27%;">Nombre completo</th>
                        <th style="width:7%;">Edad</th>
                        <th style="width:13%;">F. Afiliación</th>
                        <th style="width:8%;">Nivel</th>
                        <th style="width:18%;">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($personas as $p):
                        $tipo_af = strtoupper($p['tipo_afiliado'] ?? '');
                        $es_cot  = ($tipo_af === 'COTIZANTE');
                        $clase   = $es_cot ? 'badge-cotizante' : 'badge-benef';
                    ?>
                        <tr>
                            <td><span class="badge <?= $clase ?>"><?= htmlspecialchars($tipo_af ?: '—', ENT_QUOTES, 'UTF-8') ?></span></td>
                            <td><?= htmlspecialchars($p['numerodocumento'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($p['nombre_completo'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string)($p['edad'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($p['fecha_afiliacion_formateada'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($p['nivel_salarial'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($p['estado_descripcion'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="firma">
                <div class="firma-linea"></div>
                Unidad de Salud<br>
                Documento generado automáticamente
            </div>

            <div class="footer">
                Certificado generado el <?= htmlspecialchars($fecha_generacion, ENT_QUOTES, 'UTF-8') ?><br>
                Este documento es un certificado oficial de afiliación y no requiere firma manuscrita.
            </div>

        </body>

        </html>
<?php
        return ob_get_clean();
    }
}
