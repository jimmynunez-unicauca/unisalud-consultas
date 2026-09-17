<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="usuarios-otp-container">
    <div class="otp-card">        

        <!-- Paso 1: Correo -->
        <div id="otp-step1" >
            <div class="otp-header">
                <h2>Acceso para Prestador</h2>
                <p>Ingresa el correo electrónico para recibir un código de verificación y continuar con el acceso.</p>
            </div>

            <div class="otp-form-group">                
                <input type="email" class="otp-correo-input" id="otp-correo" placeholder="Ingresa el Correo electrónico">
                <div id="otp-correo-error" class="otp-error-msg">⚠️ Por favor ingresa un correo válido</div>
            </div>
            <button id="otp-btn-buscar" class="otp-btn">
                <span id="otp-btn-buscar-text">ENVIAR</span>
                <span id="otp-btn-buscar-spinner" class="otp-spinner hidden"></span>
            </button>
        </div>                

        <!-- Paso 2: OTP -->
        <div id="otp-step2" class="hidden">
            <div class="otp-header">
                <h2>Acceso para Prestador</h2>
                <p>Ingrese el código de validación que fue enviado al correo para poder realizar la consulta de afiliación</p>
            </div>
            <div class="otp-form-group">
                <div class="otp-inputs-container" id="otp-inputs-container">
                    <input type="text" class="otp-digit-input" maxlength="1" inputmode="numeric" autocomplete="off" data-index="0">
                    <input type="text" class="otp-digit-input" maxlength="1" inputmode="numeric" autocomplete="off" data-index="1">
                    <input type="text" class="otp-digit-input" maxlength="1" inputmode="numeric" autocomplete="off" data-index="2">
                    <input type="text" class="otp-digit-input" maxlength="1" inputmode="numeric" autocomplete="off" data-index="3">
                    <input type="text" class="otp-digit-input" maxlength="1" inputmode="numeric" autocomplete="off" data-index="4">
                    <input type="text" class="otp-digit-input" maxlength="1" inputmode="numeric" autocomplete="off" data-index="5">
                </div>
                <input type="hidden" id="otp-codigo" value="">
            <div id="otp-codigo-error" class="otp-error-msg">⚠️ Código incorrecto, intenta nuevamente</div>
            <div class="otp-hint">
                <span class="otp-timer-value" id="otp-timer-value">2:00</span>
                <span id="otp-reenviar" class="otp-reenviar disabled">Reenviar código</span>                
            </div>
            <button id="otp-btn-verificar" class="otp-btn otp-btn-secondary">
                <span id="otp-btn-verificar-text">ENTRAR</span>
                <span id="otp-btn-verificar-spinner" class="otp-spinner hidden"></span>
            </button>            
        </div>

        <div id="otp-success" class="otp-success-msg">🎉 ¡Verificación exitosa!</div>
    </div>
</div>