<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="usuarios-otp-container">
    <div class="otp-card">
        <div class="otp-header">
            <h2>🔐 Verificación</h2>
            <p>Ingresa tu correo para continuar</p>
        </div>

        <!-- Paso 1: Correo -->
        <div id="otp-step1">
            <div class="otp-form-group">
                <label for="otp-correo">Correo Electrónico</label>
                <input type="email" id="otp-correo" placeholder="Ej: ejemplo@unicauca.edu.co" autocomplete="email">
                <div id="otp-correo-error" class="otp-error-msg">⚠️ Por favor ingresa un correo válido</div>
            </div>
            <button id="otp-btn-buscar" class="otp-btn">
                <span id="otp-btn-buscar-text">Buscar</span>
                <span id="otp-btn-buscar-spinner" class="otp-spinner hidden"></span>
            </button>
        </div>

        <!-- Mensaje -->
        <div id="otp-mensaje" class="otp-message">
            <strong>✅ ¡Código enviado!</strong><br>
            Hemos enviado un código al correo
            <span id="otp-correo-destino" class="otp-email-highlight">usuario@email.com</span>.
        </div>

        <!-- Temporizador -->
        <div id="otp-timer" class="otp-timer-container hidden">
            <div class="otp-timer-label">⏱️ Tiempo restante</div>
            <div class="otp-timer-value" id="otp-timer-value">2:00</div>
            <div id="otp-timer-expirado" class="otp-timer-expired hidden">⏰ Código expirado</div>
        </div>

        <!-- Paso 2: OTP -->
        <div id="otp-step2" class="hidden">
            <div class="otp-form-group">
                <label for="otp-codigo">Código OTP</label>
                <input type="text" id="otp-codigo" placeholder="Ingresa el código de 6 dígitos" maxlength="6" autocomplete="off" inputmode="numeric">
                <div id="otp-codigo-error" class="otp-error-msg">⚠️ Código incorrecto, intenta nuevamente</div>
            </div>
            <button id="otp-btn-verificar" class="otp-btn otp-btn-secondary">
                <span id="otp-btn-verificar-text">Verificar</span>
                <span id="otp-btn-verificar-spinner" class="otp-spinner hidden"></span>
            </button>
            <div class="otp-hint">
                ¿No recibiste el código? <span id="otp-reenviar">Reenviar</span>
            </div>
        </div>

        <div id="otp-success" class="otp-success-msg">🎉 ¡Verificación exitosa!</div>
    </div>
</div>