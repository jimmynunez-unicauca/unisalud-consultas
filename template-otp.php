<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="usuarios-otp-container">
    <div class="otp-card">
        <!-- Paso 1: Correo -->
        <div id="otp-step1">
            <h2 class="otp-titulo">Acceso para Prestador</h2>
            <p class="otp-subtitulo">
                Ingresa el correo electrónico para recibir un código de verificación y continuar con el acceso.
            </p>
            <div class="otp-form-group">
                <input type="email" id="otp-correo" class="otp-input-lineal"
                    placeholder="Ingresa el Correo electrónico" autocomplete="email">
                <div id="otp-correo-error" class="otp-error-msg">⚠️ Por favor ingresa un correo válido</div>
            </div>
            <button id="otp-btn-buscar" class="otp-btn">
                <span id="otp-btn-buscar-text">ENVIAR</span>
                <span id="otp-btn-buscar-spinner" class="otp-spinner hidden"></span>
            </button>
        </div>

        <!-- Mensaje -->
        <div id="otp-mensaje" class="otp-message">
            <strong>✅ ¡Código enviado!</strong><br>
            Hemos enviado un código al correo
            <span id="otp-correo-destino" class="otp-email-highlight">usuario@email.com</span>.
        </div>

        <!-- Paso 2: OTP -->
        <div id="otp-step2" class="hidden">
            <h2 class="otp-titulo">Acceso para Prestador</h2>
            <p class="otp-subtitulo">
                Ingrese el código de validación que fue enviado al correo para poder realizar la consulta de afiliación
            </p>

            <div class="otp-cajas-wrapper">
                <input type="text" class="otp-caja" id="otp-d1" maxlength="1" inputmode="numeric" autocomplete="one-time-code">
                <input type="text" class="otp-caja" id="otp-d2" maxlength="1" inputmode="numeric">
                <input type="text" class="otp-caja" id="otp-d3" maxlength="1" inputmode="numeric">
                <input type="text" class="otp-caja" id="otp-d4" maxlength="1" inputmode="numeric">
                <input type="text" class="otp-caja" id="otp-d5" maxlength="1" inputmode="numeric">
                <input type="text" class="otp-caja" id="otp-d6" maxlength="1" inputmode="numeric">
            </div>

            <div id="otp-codigo-error" class="otp-error-msg">⚠️ Código incorrecto, intenta nuevamente</div>

            <!-- Timer + Reenviar -->
            <div class="otp-timer-linea">
                <span id="otp-timer-value">02:00</span>
                <span id="otp-reenviar" class="otp-reenviar-link">Reenviar código</span>
            </div>

            <button id="otp-btn-verificar" class="otp-btn">
                <span id="otp-btn-verificar-text">ENTRAR</span>
                <span id="otp-btn-verificar-spinner" class="otp-spinner hidden"></span>
            </button>
        </div>

        <div id="otp-success" class="otp-success-msg">🎉 ¡Verificación exitosa!</div>

        <!-- ✅ TEXTO LEGAL DE reCAPTCHA (obligatorio por Google) -->
        <p class="otp-legal">
            Este sitio está protegido por reCAPTCHA.
            <a href="https://policies.google.com/privacy" target="_blank" rel="noopener">Privacidad</a> y
            <a href="https://policies.google.com/terms" target="_blank" rel="noopener">Términos</a> de Google.
        </p>
    </div>
</div>

<!-- ✅ Script de reCAPTCHA v3 (invisible) -->
<script src="https://www.google.com/recaptcha/api.js?render=<?php echo esc_attr(UsuariosSaludRecaptcha::SITE_KEY); ?>" async defer></script>