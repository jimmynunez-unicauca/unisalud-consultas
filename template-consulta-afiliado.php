<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
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

        <!-- Paso 1: Correo -->
        <div id="consulta-afiliado-step1" >
            <div class="consulta-afiliado-header">
                <h2>Consulta de Afiliados a la Unidad de Salud</h2>
                <p>Ingresa el número de identificación correspondiente para poder hacer la consulta:</p>
            </div>

            <div class="consulta-afiliado-form-group">                
                <input type="number" class="consulta-afiliado-identificacion-input" id="consulta-afiliado-identificacion" max="9999999999">
                <div id="consulta-afiliado-correo-error" class="consulta-afiliado-error-msg">⚠️ Por favor ingresa una identificacion válido</div>
            </div>
            <button id="consulta-afiliado-btn-consultar" class="consulta-afiliado-btn-consultar">
                <span id="consulta-afiliado-btn-consultar-text">CONSULTAR</span>
                <span id="consulta-afiliado-btn-consultar-spinner" class="consulta-afiliado-spinner hidden"></span>
            </button>
        </div>                       
        
    </div>
</div>

<button id="consulta-afiliado-btn-salir" class="consulta-afiliado-btn-salir">
    <span id="consulta-afiliado-btn-salir-text">SALIR</span>    
</button>

<script>
    const SEGUNDOS_RESTANTES = <?= (int)$segundosRestantes ?>;
</script>
