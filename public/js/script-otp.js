jQuery(document).ready(function ($) {
    var cfg = window.usuarios_otp_ajax || {};
    var API = cfg.ajax_url;
    var NONCE = cfg.nonce;
    var REDIRECT = cfg.redirect;

    var OTP_DURATION = 2 * 60;

    var $step1 = $('#otp-step1');
    var $step2 = $('#otp-step2');
    var $mensaje = $('#otp-mensaje');
    var $correoInput = $('#otp-correo');
    var $correoError = $('#otp-correo-error');
    var $btnBuscar = $('#otp-btn-buscar');
    var $btnBuscarText = $('#otp-btn-buscar-text');
    var $btnBuscarSpinner = $('#otp-btn-buscar-spinner');
    var $btnVerificar = $('#otp-btn-verificar');
    var $btnVerificarText = $('#otp-btn-verificar-text');
    var $btnVerificarSpinner = $('#otp-btn-verificar-spinner');
    var $codigoError = $('#otp-codigo-error');
    var $reenviar = $('#otp-reenviar');
    var $success = $('#otp-success');
    var $timerVal = $('#otp-timer-value');
    var $correoDestino = $('#otp-correo-destino');

    // Las 6 casillas
    var $cajas = $('#otp-d1, #otp-d2, #otp-d3, #otp-d4, #otp-d5, #otp-d6');

    var currentEmail = '';
    var timerInterval = null;
    var tiempoRestante = OTP_DURATION;
    var otpExpirado = false;

    // ============================================================
    //  reCAPTCHA v3 — Obtener token
    // ============================================================
    function obtenerRecaptchaToken() {
        var deferred = $.Deferred();

        if (typeof grecaptcha === 'undefined') {
            console.warn('[RECAPTCHA] grecaptcha no está disponible');
            deferred.resolve('');
            return deferred.promise();
        }

        var siteKey = (window.usuarios_otp_ajax && usuarios_otp_ajax.recaptcha_site_key)
            ? usuarios_otp_ajax.recaptcha_site_key
            : '';
        var action = (window.usuarios_otp_ajax && usuarios_otp_ajax.recaptcha_action)
            ? usuarios_otp_ajax.recaptcha_action
            : 'salud_login';

        if (!siteKey) {
            console.warn('[RECAPTCHA] site key no configurada');
            deferred.resolve('');
            return deferred.promise();
        }

        grecaptcha.ready(function () {
            grecaptcha.execute(siteKey, { action: action })
                .then(function (token) {
                    deferred.resolve(token);
                })
                .catch(function (err) {
                    console.error('[RECAPTCHA] error al ejecutar:', err);
                    deferred.resolve('');
                });
        });

        return deferred.promise();
    }

    // ============================================================
    //  Helpers UI
    // ============================================================
    function toggleSpinner($btn, $text, $spinner, show) {
        if (show) {
            $text.css('visibility', 'hidden');
            $spinner.removeClass('hidden');
            $btn.prop('disabled', true);
        } else {
            $text.css('visibility', 'visible');
            $spinner.addClass('hidden');
            $btn.prop('disabled', false);
        }
    }

    function limpiarErrores() {
        $correoError.removeClass('show');
        $codigoError.removeClass('show');
        $correoInput.css('border-color', '');
        $cajas.css('border-color', '');
    }

    function mostrarErrorCorreo(msg) {
        $correoInput.css('border-color', '#c4243a');
        $correoError.text(msg).addClass('show');
    }

    function mostrarErrorOtp(msg) {
        $cajas.css('border-color', '#c4243a');
        $codigoError.text(msg).addClass('show');
    }

    function formatearTiempo(seg) {
        var m = Math.floor(seg / 60);
        var s = Math.floor(seg % 60);
        return String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
    }

    function obtenerCodigo() {
        var codigo = '';
        $cajas.each(function () {
            codigo += $(this).val() || '';
        });
        return codigo;
    }

    function limpiarCajas() {
        $cajas.val('');
        $('#otp-d1').trigger('focus');
    }

    // ============================================================
    //  Timer
    // ============================================================
    function iniciarTemporizador() {
        if (timerInterval) clearInterval(timerInterval);
        tiempoRestante = OTP_DURATION;
        otpExpirado = false;
        $timerVal.text(formatearTiempo(tiempoRestante)).removeClass('expirado');
        $btnVerificar.prop('disabled', false);

        timerInterval = setInterval(function () {
            tiempoRestante--;
            if (tiempoRestante <= 0) {
                clearInterval(timerInterval);
                timerInterval = null;
                $timerVal.text('00:00').addClass('expirado');
                otpExpirado = true;
                $btnVerificar.prop('disabled', true);
                mostrarErrorOtp('⏰ El código ha expirado. Solicita uno nuevo.');
                return;
            }
            $timerVal.text(formatearTiempo(tiempoRestante));
            if (tiempoRestante <= 30) {
                $timerVal.addClass('expirado');
            }
        }, 1000);
    }

    // ============================================================
    //  Manejo de las 6 cajas
    // ============================================================
    $cajas.on('input', function (e) {
        var $this = $(this);
        var val = $this.val().replace(/\D/g, '').slice(0, 1);
        $this.val(val);

        if (val.length === 1) {
            var index = $cajas.index(this);
            if (index < $cajas.length - 1) {
                $cajas.eq(index + 1).trigger('focus');
            }
        }
    });

    $cajas.on('keydown', function (e) {
        var $this = $(this);
        var index = $cajas.index(this);

        if (e.key === 'Backspace' && $this.val() === '' && index > 0) {
            e.preventDefault();
            $cajas.eq(index - 1).trigger('focus').val('');
        }

        if (e.key === 'ArrowLeft' && index > 0) {
            e.preventDefault();
            $cajas.eq(index - 1).trigger('focus');
        }
        if (e.key === 'ArrowRight' && index < $cajas.length - 1) {
            e.preventDefault();
            $cajas.eq(index + 1).trigger('focus');
        }

        if (e.key === 'Enter') {
            e.preventDefault();
            $btnVerificar.trigger('click');
        }
    });

    $cajas.on('paste', function (e) {
        e.preventDefault();
        var texto = (e.originalEvent.clipboardData || window.clipboardData).getData('text');
        texto = (texto || '').replace(/\D/g, '').slice(0, 6);
        if (texto.length === 0) return;

        $cajas.each(function (i) {
            $(this).val(texto[i] || '');
        });

        var siguiente = Math.min(texto.length, 5);
        $cajas.eq(siguiente).trigger('focus');
    });

    // ============================================================
    //  AJAX con reCAPTCHA
    // ============================================================
    function peticion(action, data, onSuccess, onError) {
        // 1) Obtener token de reCAPTCHA
        obtenerRecaptchaToken().always(function (recaptchaToken) {
            var payload = $.extend(
                { action: action, nonce: NONCE, recaptcha_token: recaptchaToken },
                data
            );

            $.ajax({
                url: API,
                type: 'POST',
                dataType: 'json',
                data: payload,
                success: function (resp) {
                    if (resp && resp.success) {
                        onSuccess && onSuccess(resp.data);
                    } else {
                        var msg = (resp && resp.data && resp.data.message)
                            ? resp.data.message
                            : 'Error inesperado';
                        onError && onError({ message: msg });
                    }
                },
                error: function (xhr) {
                    var msg = 'Error de conexión';
                    if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                        msg = xhr.responseJSON.data.message;
                    }
                    onError && onError({ message: msg });
                }
            });
        });
    }

    // ============================================================
    //  Paso 1: Buscar correo → enviar OTP
    // ============================================================
    $btnBuscar.on('click', function () {
        limpiarErrores();
        var correo = $.trim($correoInput.val());

        if (!correo) {
            mostrarErrorCorreo('⚠️ Por favor ingresa tu correo');
            return;
        }
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(correo)) {
            mostrarErrorCorreo('⚠️ Correo con formato inválido');
            return;
        }

        toggleSpinner($btnBuscar, $btnBuscarText, $btnBuscarSpinner, true);

        peticion('salud_buscar_correos', { correo: correo }, function (data) {
            currentEmail = data.correo;
            $correoDestino.text(data.correo);

            peticion('salud_enviar_otp', { correo: correo }, function () {
                $mensaje.addClass('show');
                $step1.addClass('hidden');
                $step2.removeClass('hidden');
                $('#otp-d1').trigger('focus');
                iniciarTemporizador();
                toggleSpinner($btnBuscar, $btnBuscarText, $btnBuscarSpinner, false);
            }, function (err) {
                mostrarErrorCorreo(err.message);
                toggleSpinner($btnBuscar, $btnBuscarText, $btnBuscarSpinner, false);
            });
        }, function (err) {
            mostrarErrorCorreo(err.message);
            toggleSpinner($btnBuscar, $btnBuscarText, $btnBuscarSpinner, false);
        });
    });

    $correoInput.on('keypress', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            $btnBuscar.trigger('click');
        }
    });

    // ============================================================
    //  Paso 2: Verificar OTP
    // ============================================================
    $btnVerificar.on('click', function () {
        if (otpExpirado) {
            mostrarErrorOtp('⏰ El código ha expirado. Solicita uno nuevo.');
            return;
        }

        limpiarErrores();
        var codigo = obtenerCodigo();

        if (codigo.length !== 6) {
            mostrarErrorOtp('⚠️ Debes ingresar los 6 dígitos');
            return;
        }

        toggleSpinner($btnVerificar, $btnVerificarText, $btnVerificarSpinner, true);

        peticion('salud_verificar_otp', { email: currentEmail, otp: codigo }, function (data) {
            if (timerInterval) clearInterval(timerInterval);
            $step2.addClass('hidden');
            $success.text('🎉 ¡Verificación exitosa! Bienvenido ' + data.nombre).addClass('show');
            toggleSpinner($btnVerificar, $btnVerificarText, $btnVerificarSpinner, false);

            setTimeout(function () {
                window.location.href = REDIRECT;
            }, 1200);
        }, function (err) {
            mostrarErrorOtp(err.message);
            limpiarCajas();
            toggleSpinner($btnVerificar, $btnVerificarText, $btnVerificarSpinner, false);
        });
    });

    // ============================================================
    //  Reenviar OTP
    // ============================================================
    $reenviar.on('click', function () {
        if (!currentEmail) {
            alert('Error: No hay correo registrado');
            return;
        }
        var original = $reenviar.text();
        $reenviar.text('⏳ Enviando...').css('cursor', 'default');

        peticion('salud_reenviar_otp', { email: currentEmail }, function () {
            $reenviar.text('✅ Reenviado');
            iniciarTemporizador();
            limpiarErrores();
            limpiarCajas();
            setTimeout(function () {
                $reenviar.text(original).css('cursor', 'pointer');
            }, 3000);
        }, function (err) {
            alert('Error: ' + err.message);
            $reenviar.text(original).css('cursor', 'pointer');
        });
    });

    // Foco inicial
    $correoInput.trigger('focus');
});