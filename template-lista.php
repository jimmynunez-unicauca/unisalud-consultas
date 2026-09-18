<?php
if (!defined('ABSPATH')) {
    exit;
}

$nombre_sesion = isset($usuario_salud['nombre']) ? $usuario_salud['nombre'] : '';
$email_sesion  = isset($usuario_salud['email'])  ? $usuario_salud['email']  : '';

// 🔑 Calcular segundos restantes reales desde Auth
$segundosRestantes = UsuariosSaludAuth::segundosRestantes();
?>

<script>
    // 🔑 Variable global que leerá script-usuarios.js
    var SEGUNDOS_RESTANTES = <?php echo (int) $segundosRestantes; ?>;
</script>

<!-- inicio formulario consultar prestador -->
<!-- ===== CONTADOR DE SESIÓN ===== -->
<div id="session-timer">
    <div class="timer-row">
        <span class="timer-icon">⏱️</span>
        <span class="timer-clock" id="timerClock">--:--:--</span>
    </div>
    <div class="timer-message">
        La sesión se cerrará automáticamente cuando finalice el tiempo.
    </div>
</div>

<div class="usuarios-consulta-afiliado-container">
    <div class="consulta-afiliado-card">        

        <!-- Paso 1: Buscar Identificacion -->
        <div id="consulta-afiliado-step1" >
            <div class="consulta-afiliado-header">
                <h2>Consulta de Afiliados a la Unidad de Salud</h2>
                <p>Ingresa el número de identificación correspondiente para poder hacer la consulta:</p>
            </div>

            <div class="consulta-afiliado-form-group">                
                <input type="number" class="consulta-afiliado-identificacion-input" id="campo-identificacion" max="9999999999">
                <div id="consulta-afiliado-correo-error" class="consulta-afiliado-error-msg">⚠️ Por favor ingresa una identificacion válido</div>
            </div>
            <button id="btn-consulta-afiliado" class="consulta-afiliado-btn-consultar">
                <span id="consulta-afiliado-btn-consultar-text">CONSULTAR</span>
                <span id="consulta-afiliado-btn-consultar-spinner" class="consulta-afiliado-spinner hidden"></span>
            </button>
        </div>                       
        
    </div>
</div>

<button id="btn-cerrar-sesion" class="consulta-afiliado-btn-salir">
    <span id="consulta-afiliado-btn-salir-text">SALIR</span>    
</button>
<!-- fin formulario consultar prestador -->


<!-- inicio tabla -->
<div class="usuarios-salud-container" hidden>
    <!-- Barra de sesión -->
    <div class="usuarios-sesion-bar">
        <div class="usuarios-sesion-info">
            <span class="usuarios-sesion-avatar">👤</span>
            <span>
                <strong><?php echo esc_html($nombre_sesion ?: 'Usuario'); ?></strong>
                <small><?php echo esc_html($email_sesion); ?></small>
            </span>
        </div>
        <button type="button" id="btn-cerrar-sesion" class="btn-cerrar-sesion">Cerrar sesión</button>
    </div>

    <!-- Filtros -->
    <div class="usuarios-filtros">
        <div class="filtros-row">
            <div class="filtro-item">
                <label>Buscar por nombres, apellidos o documento</label>
                <input type="text" id="filtro-nombre" class="filtro-input" placeholder="Escriba el texto a buscar...">
            </div>
            <div class="filtro-item">
                <label>Sexo</label>
                <select id="filtro-estado" class="filtro-select">
                    <option value="">Todos</option>
                    <option value="Masculino">Masculino</option>
                    <option value="Femenino">Femenino</option>
                </select>
            </div>
            <div class="filtro-item filtro-botones">
                <button id="btn-limpiar" class="btn">LIMPIAR</button>
            </div>
        </div>
    </div>

    <div id="resultados-container">
        <div id="usuarios-lista" class="usuarios-lista-principal">
            <div class="loading">Cargando personas...</div>
        </div>
    </div>

    <div id="paginacion-container" class="paginacion" style="display: none;"></div>
</div>

<!-- Modal de detalle -->
<div id="modal-persona" class="modal-persona-overlay" style="display:none;">
    <div class="modal-persona-content" role="dialog" aria-modal="true">
        <div class="modal-persona-header">
            <h2 id="modal-persona-titulo">Detalle de la persona</h2>
            <button type="button" class="modal-persona-close" id="modal-persona-close" aria-label="Cerrar">&times;</button>
        </div>
        <div class="modal-persona-body" id="modal-persona-body">
            <div class="loading">Cargando información...</div>
        </div>
    </div>
</div>
<!-- fin tabla -->