<?php
if (!defined('ABSPATH')) {
    exit;
}

$conexion_path = WP_PLUGIN_DIR . '/config-unicauca/conexion-postgresql.php';
if (file_exists($conexion_path)) {
    require_once($conexion_path);
} else {
    error_log('No se encontró conexion-postgresql.php en: ' . $conexion_path);
    die('Error: Archivo de conexión a PostgreSQL no encontrado.');
}

if (!class_exists('UsuariosSaludConsultas')) {

    class UsuariosSaludConsultas
    {
        private $conexion;

        public function __construct()
        {
            $this->conexion = ConexionPostgresql::conectar();
            if (!$this->conexion) {
                error_log('UsuariosSaludConsultas: Error de conexión a PostgreSQL');
            }
        }

        public function __destruct()
        {
            ConexionPostgresql::desconectar();
        }

        private function ejecutarConsulta($sql, $params = [])
        {
            if (!$this->conexion) {
                return [];
            }
            try {
                $stmt = $this->conexion->prepare($sql);
                $stmt->execute($params);
                return $stmt->fetchAll();
            } catch (PDOException $e) {
                error_log('Error en consulta PostgreSQL: ' . $e->getMessage());
                error_log('SQL: ' . $sql);
                return [];
            }
        }

        private function sqlCorreos()
        {
            return "
                LEFT JOIN LATERAL (
                    SELECT c.correoelectronico            AS correo_principal,
                           c.correoelectronicoalternativo AS correo_alterno
                    FROM cotizantes c
                    WHERE c.id_persona = p.id
                      AND (
                            (c.correoelectronico IS NOT NULL AND c.correoelectronico <> '')
                         OR (c.correoelectronicoalternativo IS NOT NULL AND c.correoelectronicoalternativo <> '')
                      )
                    ORDER BY c.id DESC
                    LIMIT 1
                ) co ON TRUE
            ";
        }

        public function getUsuariosFiltrados($nombre = '', $estado = '', $limit = 10, $offset = 0)
        {
            $sql = "SELECT
                        p.id,
                        p.numerodocumento,
                        p.tipoidentificacion,
                        p.primernombre,
                        p.segundonombre,
                        p.primerapellido,
                        p.segundoapellido,
                        p.fechanacimiento,
                        p.fechaexpedicion,
                        p.sexo,
                        p.tiposangre,
                        p.tiporh,
                        p.estadocivil,
                        p.escolaridad,
                        p.grupoetnico,
                        p.grupopoblacional,
                        p.orientacionsexual,
                        uc.telefono,
                        uc.celular,
                        uc.direccion,
                        uc.barrio,
                        uc.estrato,
                        uc.zona,
                        m.descripcion  AS municipio,
                        d.descripcion  AS departamento,
                        co.correo_principal,
                        co.correo_alterno
                    FROM personas p
                    LEFT JOIN LATERAL (
                        SELECT u.telefono, u.celular, u.direccion, u.barrio,
                               u.estrato, u.zona, u.id_municipio
                        FROM personas_ubicaciones pu
                        JOIN ubicaciones_contactos u
                             ON u.id = pu.id_ubicacion_contacto
                        WHERE pu.id_persona = p.id
                        ORDER BY pu.id DESC
                        LIMIT 1
                    ) uc ON TRUE
                    " . $this->sqlCorreos() . "
                    LEFT JOIN municipios    m ON m.id = COALESCE(uc.id_municipio, p.id_municipio)
                    LEFT JOIN departamentos d ON d.id = m.id_departamento
                    WHERE 1=1";
            $params = [];

            if (!empty($nombre)) {
                $sql .= " AND (p.primernombre ILIKE ?"
                      . " OR p.segundonombre ILIKE ?"
                      . " OR p.primerapellido ILIKE ?"
                      . " OR p.segundoapellido ILIKE ?"
                      . " OR p.numerodocumento ILIKE ?)";
                $like = "%{$nombre}%";
                for ($i = 0; $i < 5; $i++) {
                    $params[] = $like;
                }
            }

            if ($estado !== '') {
                $sql .= " AND UPPER(TRIM(p.sexo)) = UPPER(TRIM(?))";
                $params[] = $estado;
            }

            $sql .= " ORDER BY p.primerapellido ASC, p.primernombre ASC, p.id DESC
                      LIMIT ? OFFSET ?";
            $params[] = (int)$limit;
            $params[] = (int)$offset;

            $registros = $this->ejecutarConsulta($sql, $params);

            foreach ($registros as &$row) {
                $row['nombre_completo'] = trim(
                    ($row['primernombre']    ?? '') . ' ' .
                    ($row['segundonombre']   ?? '') . ' ' .
                    ($row['primerapellido']  ?? '') . ' ' .
                    ($row['segundoapellido'] ?? '')
                );

                if (!empty($row['fechanacimiento'])) {
                    $ts    = strtotime($row['fechanacimiento']);
                    $dias  = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
                    $meses = ['enero','febrero','marzo','abril','mayo','junio',
                              'julio','agosto','septiembre','octubre','noviembre','diciembre'];
                    $row['fecha_formateada'] =
                        $dias[date('w', $ts)] . ', ' .
                        date('d', $ts) . ' de ' .
                        $meses[date('n', $ts) - 1] . ' de ' .
                        date('Y', $ts);
                } else {
                    $row['fecha_formateada'] = '';
                }

                $ts = trim(($row['tiposangre'] ?? '') . ' ' . ($row['tiporh'] ?? ''));
                $row['tipo_sangre'] = ($ts !== '') ? $ts : 'No registrado';
                $row['sexo_texto']  = !empty($row['sexo']) ? $row['sexo'] : 'No registrado';

                $row['correo']         = $row['correo_principal'] ?? '';
                $row['correo_alterno'] = $row['correo_alterno']   ?? '';

                foreach ([
                    'telefono','celular','direccion','barrio','estrato','zona',
                    'municipio','departamento','correo','correo_alterno'
                ] as $k) {
                    $row[$k] = $row[$k] ?? '';
                }
            }

            return $registros;
        }

        public function getTotalUsuarios($nombre = '', $estado = '')
        {
            $sql = "SELECT COUNT(*) AS total FROM personas p WHERE 1=1";
            $params = [];

            if (!empty($nombre)) {
                $sql .= " AND (p.primernombre ILIKE ?"
                      . " OR p.segundonombre ILIKE ?"
                      . " OR p.primerapellido ILIKE ?"
                      . " OR p.segundoapellido ILIKE ?"
                      . " OR p.numerodocumento ILIKE ?)";
                $like = "%{$nombre}%";
                for ($i = 0; $i < 5; $i++) {
                    $params[] = $like;
                }
            }

            if ($estado !== '') {
                $sql .= " AND UPPER(TRIM(p.sexo)) = UPPER(TRIM(?))";
                $params[] = $estado;
            }

            $resultados = $this->ejecutarConsulta($sql, $params);
            return !empty($resultados) ? (int)$resultados[0]['total'] : 0;
        }

        public function getPersonaDetalle($id)
        {
            $id = (int)$id;
            if ($id <= 0) {
                return null;
            }

            $sql = "SELECT
                        p.id, p.numerodocumento, p.tipoidentificacion,
                        p.primernombre, p.segundonombre, p.primerapellido, p.segundoapellido,
                        p.fechanacimiento, p.fechaexpedicion, p.fechavencimiento,
                        p.sexo, p.tiposangre, p.tiporh, p.estadocivil, p.escolaridad,
                        p.grupoetnico, p.grupopoblacional, p.orientacionsexual,
                        uc.telefono, uc.celular, uc.direccion, uc.barrio, uc.estrato, uc.zona,
                        m.descripcion AS municipio,
                        d.descripcion AS departamento,
                        co.correo_principal,
                        co.correo_alterno
                    FROM personas p
                    LEFT JOIN LATERAL (
                        SELECT u.telefono, u.celular, u.direccion, u.barrio,
                               u.estrato, u.zona, u.id_municipio
                        FROM personas_ubicaciones pu
                        JOIN ubicaciones_contactos u
                             ON u.id = pu.id_ubicacion_contacto
                        WHERE pu.id_persona = p.id
                        ORDER BY pu.id DESC
                        LIMIT 1
                    ) uc ON TRUE
                    " . $this->sqlCorreos() . "
                    LEFT JOIN municipios    m ON m.id = COALESCE(uc.id_municipio, p.id_municipio)
                    LEFT JOIN departamentos d ON d.id = m.id_departamento
                    WHERE p.id = ?";
            $persona = $this->ejecutarConsulta($sql, [$id]);

            if (empty($persona)) {
                return null;
            }
            $detalle = $persona[0];

            $detalle['nombre_completo'] = trim(
                ($detalle['primernombre']    ?? '') . ' ' .
                ($detalle['segundonombre']   ?? '') . ' ' .
                ($detalle['primerapellido']  ?? '') . ' ' .
                ($detalle['segundoapellido'] ?? '')
            );

            if (!empty($detalle['fechanacimiento'])) {
                $ts    = strtotime($detalle['fechanacimiento']);
                $dias  = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
                $meses = ['enero','febrero','marzo','abril','mayo','junio',
                          'julio','agosto','septiembre','octubre','noviembre','diciembre'];
                $detalle['fecha_nacimiento_formateada'] =
                    $dias[date('w', $ts)] . ', ' . date('d', $ts) . ' de ' .
                    $meses[date('n', $ts) - 1] . ' de ' . date('Y', $ts);
            } else {
                $detalle['fecha_nacimiento_formateada'] = '';
            }

            $detalle['tipo_sangre'] = trim(($detalle['tiposangre'] ?? '') . ' ' . ($detalle['tiporh'] ?? ''));
            if ($detalle['tipo_sangre'] === '') {
                $detalle['tipo_sangre'] = 'No registrado';
            }

            $detalle['correo']         = $detalle['correo_principal'] ?? '';
            $detalle['correo_alterno'] = $detalle['correo_alterno']   ?? '';

            foreach ([
                'telefono','celular','direccion','barrio','estrato','zona',
                'municipio','departamento','correo','correo_alterno'
            ] as $k) {
                $detalle[$k] = $detalle[$k] ?? '';
            }

            $detalle['estados'] = $this->ejecutarConsulta(
                "SELECT pe.id, pe.fecha_inicio_estado, pe.fecha_fin,
                        pe.tipoafiliado, pe.tipo_estado,
                        e.descripcion AS estado_descripcion
                 FROM personas_estados pe
                 LEFT JOIN estados e ON e.id = pe.id_estado
                 WHERE pe.id_persona = ?
                 ORDER BY pe.fecha_inicio_estado DESC NULLS LAST, pe.id DESC",
                [$id]
            );

            $detalle['discapacidades'] = $this->ejecutarConsulta(
                "SELECT pd.id, pd.fecharegistro, d.descripcion, d.grado
                 FROM personas_discapacidades pd
                 LEFT JOIN discapacidades d ON d.id = pd.id_discapacidad
                 WHERE pd.id_persona = ?
                 ORDER BY pd.fecharegistro DESC NULLS LAST, pd.id DESC",
                [$id]
            );

            $detalle['afiliaciones'] = $this->ejecutarConsulta(
                "SELECT a.id, a.tipoafiliacion,
                        a.fecharadicacion, a.fechaingresosgsss, a.fechafinalizacion,
                        a.ibcacumulado, a.observacion, a.numeroradicacion,
                        a.nombrecontactoemergencia,
                        a.telefonocontactoemergencia,
                        a.celularcontactoemergencia,
                        a.direccioncontactoemergencia
                 FROM afiliaciones a
                 WHERE a.id_cotizante = ?
                 ORDER BY a.fecharadicacion DESC NULLS LAST, a.id DESC",
                [$id]
            );

            $detalle['convenios'] = [];
            if (!empty($detalle['numerodocumento'])) {
                $detalle['convenios'] = $this->ejecutarConsulta(
                    "SELECT pc.id, pc.numero_identificacion, pc.tipo_identificacion,
                            pc.fecha_nacimiento, pc.sexo,
                            pce.tipo_afiliado, pce.fecha_inicio_estado, pce.fecha_fin_estado,
                            epc.descripcion AS estado_convenio_descripcion
                     FROM personas_convenios pc
                     LEFT JOIN personas_convenios_estados pce
                            ON pce.id_persona_convenio = pc.id
                     LEFT JOIN estados_personas_convenios epc
                            ON epc.id = pce.id_estado_persona_convenio
                     WHERE pc.numero_identificacion = ?
                     ORDER BY pce.fecha_inicio_estado DESC NULLS LAST, pc.id DESC",
                    [$detalle['numerodocumento']]
                );
            }

            return $detalle;
        }
    }
}