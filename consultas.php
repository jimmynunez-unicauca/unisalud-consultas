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
                    $dias  = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
                    $meses = [
                        'enero',
                        'febrero',
                        'marzo',
                        'abril',
                        'mayo',
                        'junio',
                        'julio',
                        'agosto',
                        'septiembre',
                        'octubre',
                        'noviembre',
                        'diciembre'
                    ];
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

                foreach (
                    [
                        'telefono',
                        'celular',
                        'direccion',
                        'barrio',
                        'estrato',
                        'zona',
                        'municipio',
                        'departamento',
                        'correo',
                        'correo_alterno'
                    ] as $k
                ) {
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

            // ============================================================
            // 1) Datos personales + ubicación + correo
            // ============================================================
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
                $dias  = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
                $meses = [
                    'enero',
                    'febrero',
                    'marzo',
                    'abril',
                    'mayo',
                    'junio',
                    'julio',
                    'agosto',
                    'septiembre',
                    'octubre',
                    'noviembre',
                    'diciembre'
                ];
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

            foreach (
                [
                    'telefono',
                    'celular',
                    'direccion',
                    'barrio',
                    'estrato',
                    'zona',
                    'municipio',
                    'departamento',
                    'correo',
                    'correo_alterno'
                ] as $k
            ) {
                $detalle[$k] = $detalle[$k] ?? '';
            }

            // ============================================================
            // 2) Estados
            // ============================================================
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

            // ============================================================
            // 3) Discapacidades
            // ============================================================
            $detalle['discapacidades'] = $this->ejecutarConsulta(
                "SELECT pd.id, pd.fecharegistro, d.descripcion, d.grado
                 FROM personas_discapacidades pd
                 LEFT JOIN discapacidades d ON d.id = pd.id_discapacidad
                 WHERE pd.id_persona = ?
                 ORDER BY pd.fecharegistro DESC NULLS LAST, pd.id DESC",
                [$id]
            );

            // ============================================================
            // 4) Afiliaciones + nivel salarial + relación laboral
            // ============================================================
            $detalle['afiliaciones'] = $this->ejecutarConsulta(
                "SELECT
                    a.id, a.tipoafiliacion,
                    a.fecharadicacion, a.fechaingresosgsss, a.fechafinalizacion,
                    a.ibcacumulado, a.observacion, a.numeroradicacion,
                    a.nombrecontactoemergencia,
                    a.telefonocontactoemergencia,
                    a.celularcontactoemergencia,
                    a.direccioncontactoemergencia,
                    -- Nivel salarial
                    ns.id              AS nivel_salarial_id,
                    ns.descripcion     AS nivel_salarial_descripcion,
                    ns.nivel           AS nivel_salarial_nivel,
                    ns.rango_inicial   AS nivel_salarial_rango_inicial,
                    ns.rango_final     AS nivel_salarial_rango_final,
                    -- Relación laboral
                    rl.id              AS rl_id,
                    rl.cargoactual,
                    rl.dedicacion,
                    rl.extension,
                    rl.telefono        AS rl_telefono,
                    rl.fechaingresounicauca,
                    rl.fechavencimientocontrato,
                    rl.numeroradicacion AS rl_numeroradicacion,
                    rl.provisional,
                    rl.tipovinculacion,
                    -- Dependencia / Sede / Pensión
                    dep.descripcion    AS dependencia,
                    sed.descripcion    AS sede,
                    pen.numeroresolucion AS pension_resolucion,
                    pen.fecharesolucion  AS pension_fecha
                 FROM afiliaciones a
                 LEFT JOIN niveles_salariales  ns  ON ns.id  = a.id_nivel_salarial
                 LEFT JOIN relaciones_laborales rl ON rl.id  = a.id_relacion_laboral
                 LEFT JOIN dependencias         dep ON dep.id = rl.id_dependencia
                 LEFT JOIN sedes                sed ON sed.id = rl.id_sede
                 LEFT JOIN pensiones            pen ON pen.id = rl.id_pension
                 WHERE a.id_cotizante = ?
                 ORDER BY a.fecharadicacion DESC NULLS LAST, a.id DESC",
                [$id]
            );

            // ============================================================
            // 5) Convenios
            // ============================================================
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

            // ============================================================
            // 6) Beneficiarios
            // ============================================================
            $detalle['beneficiarios'] = $this->ejecutarConsulta(
                "SELECT
                    b.id              AS beneficiario_id,
                    b.beneficiarioasociado,
                    b.ubicacioncontactoigual,
                    b.upcadicional,
                    p.id              AS persona_id,
                    p.numerodocumento,
                    p.tipoidentificacion,
                    p.primernombre,
                    p.segundonombre,
                    p.primerapellido,
                    p.segundoapellido,
                    p.fechanacimiento,
                    p.sexo,
                    p.tiposangre,
                    p.tiporh,
                    p.estadocivil,
                    p.escolaridad,
                    cb.parentescobeneficiario,
                    cb.numeroradicacion,
                    m.descripcion     AS municipio,
                    d.descripcion     AS departamento
                 FROM cotizantes c
                 INNER JOIN cotizantes_beneficiarios cb ON cb.id_cotizante  = c.id
                 INNER JOIN beneficiarios b             ON b.id             = cb.id_beneficiario
                 INNER JOIN personas p                  ON p.id             = b.id_persona
                 LEFT JOIN municipios    m              ON m.id             = p.id_municipio
                 LEFT JOIN departamentos d              ON d.id             = m.id_departamento
                 WHERE c.id_persona = ?
                 ORDER BY cb.parentescobeneficiario, p.primerapellido, p.primernombre",
                [$id]
            );

            foreach ($detalle['beneficiarios'] as &$b) {
                $b['nombre_completo'] = trim(
                    ($b['primernombre']    ?? '') . ' ' .
                        ($b['segundonombre']   ?? '') . ' ' .
                        ($b['primerapellido']  ?? '') . ' ' .
                        ($b['segundoapellido'] ?? '')
                );

                if (!empty($b['fechanacimiento'])) {
                    $ts    = strtotime($b['fechanacimiento']);
                    $dias  = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
                    $meses = [
                        'enero',
                        'febrero',
                        'marzo',
                        'abril',
                        'mayo',
                        'junio',
                        'julio',
                        'agosto',
                        'septiembre',
                        'octubre',
                        'noviembre',
                        'diciembre'
                    ];
                    $b['fecha_nacimiento_formateada'] =
                        $dias[date('w', $ts)] . ', ' . date('d', $ts) . ' de ' .
                        $meses[date('n', $ts) - 1] . ' de ' . date('Y', $ts);
                } else {
                    $b['fecha_nacimiento_formateada'] = '';
                }

                $tsB = trim(($b['tiposangre'] ?? '') . ' ' . ($b['tiporh'] ?? ''));
                $b['tipo_sangre'] = ($tsB !== '') ? $tsB : 'No registrado';
                $b['sexo_texto']  = !empty($b['sexo']) ? $b['sexo'] : 'No registrado';

                foreach (
                    [
                        'parentescobeneficiario',
                        'numeroradicacion',
                        'municipio',
                        'departamento',
                        'upcadicional'
                    ] as $k
                ) {
                    $b[$k] = $b[$k] ?? '';
                }
            }
            unset($b);

            // ============================================================
            // 7) Información laboral más reciente (aplanada)
            // ============================================================
            $detalle['info_laboral'] = null;
            foreach ($detalle['afiliaciones'] as $af) {
                if (!empty($af['rl_id'])) {
                    $detalle['info_laboral'] = [
                        'cargoactual'             => $af['cargoactual'],
                        'dedicacion'              => $af['dedicacion'],
                        'extension'               => $af['extension'],
                        'telefono'                => $af['rl_telefono'],
                        'fechaingresounicauca'    => $af['fechaingresounicauca'],
                        'fechavencimientocontrato' => $af['fechavencimientocontrato'],
                        'numeroradicacion'        => $af['rl_numeroradicacion'],
                        'provisional'             => $af['provisional'],
                        'tipovinculacion'         => $af['tipovinculacion'],
                        'dependencia'             => $af['dependencia'],
                        'sede'                    => $af['sede'],
                        'pension_resolucion'      => $af['pension_resolucion'],
                        'pension_fecha'           => $af['pension_fecha'],
                    ];
                    break; // el primero ya está ordenado por fecha DESC
                }
            }

            // ============================================================
            // 8) Nivel salarial más reciente (aplanado)
            // ============================================================
            $detalle['nivel_salarial'] = null;
            foreach ($detalle['afiliaciones'] as $af) {
                if (!empty($af['nivel_salarial_id'])) {
                    $detalle['nivel_salarial'] = [
                        'id'            => $af['nivel_salarial_id'],
                        'descripcion'   => $af['nivel_salarial_descripcion'],
                        'nivel'         => $af['nivel_salarial_nivel'],
                        'rango_inicial' => $af['nivel_salarial_rango_inicial'],
                        'rango_final'   => $af['nivel_salarial_rango_final'],
                    ];
                    break;
                }
            }

            return $detalle;
        }

        /**
         * Busca una persona por número de identificación exacto.
         * Devuelve un array con el cotizante (primero) y sus beneficiarios.
         */
        public function getPersonaPorIdentificacion($identificacion)
        {
            $identificacion = trim($identificacion);
            if ($identificacion === '') {
                return null;
            }

            // ============================================================
            // 1) Datos base de la persona
            // ============================================================
            $sqlPersona = "SELECT
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
                WHERE p.numerodocumento = ?
                LIMIT 1";

            $personas = $this->ejecutarConsulta($sqlPersona, [$identificacion]);
            if (empty($personas)) {
                return null;
            }
            $persona = $personas[0];
            $personaId = (int)$persona['id'];

            // ============================================================
            // 2) ¿Es cotizante? Si sí, traemos sus beneficiarios
            // ============================================================
            $sqlEsCotizante = "SELECT id FROM cotizantes WHERE id_persona = ? LIMIT 1";
            $esCotizante    = $this->ejecutarConsulta($sqlEsCotizante, [$personaId]);

            $beneficiarios = [];
            if (!empty($esCotizante)) {
                $cotizanteId = (int)$esCotizante[0]['id'];

                $sqlBenef = "SELECT
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
                    WHERE c.id = ?
                    ORDER BY cb.parentescobeneficiario, p.primerapellido, p.primernombre";

                $beneficiarios = $this->ejecutarConsulta($sqlBenef, [$cotizanteId]);
            }

            // ============================================================
            // 3) Formatear todo y armar el array final
            // ============================================================
            $resultado = [];

            // --- Cotizante (o la persona consultada, sea cotizante o no) ---
            $filaCot = $this->formatearFilaResultado($persona, 'COTIZANTE');
            $resultado[] = $filaCot;

            // --- Beneficiarios ---
            foreach ($beneficiarios as $b) {
                // Heredar el nivel salarial del cotizante (los beneficiarios no tienen uno propio)
                $b['nivel_salarial_desc']   = $persona['nivel_salarial_desc']   ?? null;
                $b['nivel_salarial_codigo'] = $persona['nivel_salarial_codigo'] ?? null;

                $filaBen = $this->formatearFilaResultado($b, 'BENEFICIARIO');
                $filaBen['parentesco'] = $b['parentescobeneficiario'] ?? '';
                $resultado[] = $filaBen;
            }

            return $resultado;
        }

        /**
         * Helper: convierte una fila cruda en el formato que espera el frontend.
         */
        private function formatearFilaResultado(array $row, $tipoAfiliadoDefault = 'COTIZANTE')
        {
            $nombreCompleto = trim(
                ($row['primernombre']    ?? '') . ' ' .
                    ($row['segundonombre']   ?? '') . ' ' .
                    ($row['primerapellido']  ?? '') . ' ' .
                    ($row['segundoapellido'] ?? '')
            );

            // Edad calculada
            $edad = null;
            if (!empty($row['fechanacimiento'])) {
                try {
                    $fn = new DateTime($row['fechanacimiento']);
                    $hoy = new DateTime();
                    $edad = $hoy->diff($fn)->y;
                } catch (Exception $e) {
                    $edad = null;
                }
            }

            // Fecha de afiliación formateada dd/mm/YYYY
            $fechaAfiliacion = '';
            if (!empty($row['fecha_afiliacion'])) {
                $fechaAfiliacion = date('d/m/Y', strtotime($row['fecha_afiliacion']));
            }

            // Tipo de afiliado (viene del estado o del default)
            $tipoAfiliado = $row['tipoafiliado'] ?? '';
            if ($tipoAfiliado === '' || $tipoAfiliado === null) {
                $tipoAfiliado = $tipoAfiliadoDefault;
            }
            $tipoAfiliado = strtoupper($tipoAfiliado);

            // Estado
            $estado = $row['estado_descripcion'] ?? '';
            if ($estado === '' && !empty($row['tipo_estado'])) {
                $estado = $row['tipo_estado'];
            }
            if ($estado === '') {
                $estado = 'Sin estado registrado';
            }

            // Nivel salarial: primero la descripción, si no el código, si no "—"
            $nivel = $row['nivel_salarial_desc'] ?? '';
            if ($nivel === '' || $nivel === null) {
                $nivel = $row['nivel_salarial_codigo'] ?? '';
            }
            if ($nivel === '' || $nivel === null) {
                $nivel = '—';
            }

            return [
                'id'                         => (int)($row['id'] ?? 0),
                'tipo_afiliado'              => $tipoAfiliado,
                'tipoidentificacion'         => $row['tipoidentificacion'] ?? '',
                'numerodocumento'            => $row['numerodocumento'] ?? '',
                'nombre_completo'            => $nombreCompleto,
                'edad'                       => $edad !== null ? $edad : '',
                'fecha_afiliacion_formateada' => $fechaAfiliacion,
                'nivel_salarial'             => $nivel,
                'estado_descripcion'         => $estado,
                'parentesco'                 => $row['parentescobeneficiario'] ?? '',
                'prestadora'                 => 'NUEVA EPS',
            ];
        }
    }
}
