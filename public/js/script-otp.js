jQuery(document).ready(function ($) {
    var cfg = window.usuarios_otp_ajax || {};
    var API = cfg.ajax_url;
    var NONCE = cfg.nonce;
    var REDIRECT = cfg.redirect;        

    var OTP_DURATION = 1 * 30; // 2 minutos en segundos

    var $step1 = $('#otp-step1');
    var $step2 = $('#otp-step2');
    var $mensaje = $('#otp-mensaje');
    var $correoInput = $('#otp-correo');
    var $correoError = $('#otp-correo-error');
    var $btnBuscar = $('#otp-btn-buscar');
    var $btnBuscarText = $('#otp-btn-buscar-text');
    var $btnBuscarSpinner = $('#otp-btn-buscar-spinner');
    var $codigoInput = $('#otp-codigo');
    var $digitInputs = $('.otp-digit-input');
    var $inputsContainer = $('#otp-inputs-container');
    var $codigoError = $('#otp-codigo-error');
    var $btnVerificar = $('#otp-btn-verificar');
    var $btnVerificarText = $('#otp-btn-verificar-text');
    var $btnVerificarSpinner = $('#otp-btn-verificar-spinner');
    var $reenviar = $('#otp-reenviar');
    var $success = $('#otp-success');    
    var $timerVal = $('#otp-timer-value');    
    var $correoDestino = $('#otp-correo-destino');

    var currentEmail = '';
    var timerInterval = null;
    var tiempoRestante = OTP_DURATION;
    var otpExpirado = false;

    // --------------------------------------------------
    // Helpers UI
    // --------------------------------------------------
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
        $correoInput.css('border-color', '#e0e0e0');
        $codigoInput.css('border-color', '#e0e0e0');
        $mensaje.removeClass('error');
    }

    function mostrarError($input, $error, mensaje) {
        $input.css('border-color', '#f5576c');
        $error.text(mensaje).addClass('show');
    }

    function formatearTiempo(seg) {
        var m = Math.floor(seg / 60);
        var s = Math.floor(seg % 60);
        return String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
    }

    // --------------------------------------------------
    // Temporizador
    // --------------------------------------------------
    function iniciarTemporizador() {
        if (timerInterval) {
            clearInterval(timerInterval);
            timerInterval = null;
        }
        tiempoRestante = OTP_DURATION;
        otpExpirado = false;        
        $timerVal.removeClass('warning expired').text(formatearTiempo(tiempoRestante));        
        $timerVal.prop('disabled', false);
        $btnVerificar.prop('disabled', false);

        timerInterval = setInterval(function () {
            tiempoRestante--;
            if (tiempoRestante <= 0) {
                clearInterval(timerInterval);
                timerInterval = null;
                $timerVal.text('0:00').addClass('expired');                                
                otpExpirado = true;
                $btnVerificar.prop('disabled', true);
                $reenviar.removeClass('disabled');
                //mostrarError($codigoInput, $codigoError, 'El código ha expirado. Solicita uno nuevo.');
                return;
            }
            $timerVal.text(formatearTiempo(tiempoRestante));
            if (tiempoRestante <= 60) {
                $timerVal.addClass('warning');                
            } else {
                $timerVal.removeClass('warning');
                $timer.removeClass('warning');
            }
        }, 1000);
    }

    function detenerTemporizador() {
        if (timerInterval) {
            clearInterval(timerInterval);
            timerInterval = null;
        }        
    }

    // --------------------------------------------------
    // AJAX
    // --------------------------------------------------
    function peticion(action, data, onSuccess, onError) {                
        var payload = $.extend({ action: action, nonce: NONCE }, data);
        $.ajax({
            url: API,
            type: 'POST',
            dataType: 'json',
            data: payload,
            success: function (resp) {
                if (resp && resp.success) {
                    onSuccess && onSuccess(resp.data);
                } else {
                    var msg = resp && resp.data && resp.data.message ? resp.data.message : 'Error inesperado';
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
    }

    // --------------------------------------------------
    // Paso 1: buscar correo → enviar OTP automáticamente
    // --------------------------------------------------
    $btnBuscar.on('click', function () {
        limpiarErrores();
        var correo = $.trim($correoInput.val());

        if (!correo) {
            mostrarError($correoInput, $correoError, 'Por favor ingresa tu correo');
            return;
        }
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(correo)) {
            mostrarError($correoInput, $correoError, 'Correo con formato inválido');
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
                $digitInputs.eq(0).trigger('focus');

                // 🔑 Sincronizar duración real desde el servidor
                /*if (data.duracion) {
                    OTP_DURATION = parseInt(data.duracion, 10);
                }*/

                iniciarTemporizador();
                toggleSpinner($btnBuscar, $btnBuscarText, $btnBuscarSpinner, false);
            }, function (err) {
                mostrarError($correoInput, $correoError, err.message);
                toggleSpinner($btnBuscar, $btnBuscarText, $btnBuscarSpinner, false);
            });
        }, function (err) {
            mostrarError($correoInput, $correoError, err.message);
            toggleSpinner($btnBuscar, $btnBuscarText, $btnBuscarSpinner, false);
        });
    });

    $correoInput.on('keypress', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            $btnBuscar.trigger('click');
        }
    });

    // --------------------------------------------------
    // Paso 2: verificar OTP
    // --------------------------------------------------
    $btnVerificar.on('click', function () {
        if (otpExpirado) {
            mostrarError($codigoInput, $codigoError, 'El código ha expirado. Solicita uno nuevo.');
            return;
        }

        limpiarErrores();
        var codigo = obtenerCodigoOTP();   // 👈 antes era $codigoInput.val()

        if (!codigo) {
            mostrarError($codigoInput, $codigoError, 'Por favor ingresa el código OTP');
            marcarErrorInputsOTP();
            return;
        }
        if (!/^\d{6}$/.test(codigo)) {
            mostrarError($codigoInput, $codigoError, 'sEl código debe tener 6 dígitos numéricos');
            marcarErrorInputsOTP();
            return;
        }

        toggleSpinner($btnVerificar, $btnVerificarText, $btnVerificarSpinner, true);

        peticion('salud_verificar_otp', { email: currentEmail, otp: codigo }, function (data) {
            detenerTemporizador();
            $codigoInput.val('').css('border-color', '#4caf50');
            $digitInputs.val('').removeClass('filled').css('border-color', '#4caf50');
            $step2.addClass('hidden');            
            $success.text('🎉 ¡Verificación exitosa! Bienvenido ' + data.nombre).addClass('show');
            toggleSpinner($btnVerificar, $btnVerificarText, $btnVerificarSpinner, false);

            setTimeout(function () {
                window.location.href = REDIRECT;
            }, 1500);
        }, function (err) {
            mostrarError($codigoInput, $codigoError, err.message);
            marcarErrorInputsOTP();
            $codigoInput.val('').trigger('focus');
            toggleSpinner($btnVerificar, $btnVerificarText, $btnVerificarSpinner, false);
        });
    });

    $codigoInput.on('keypress', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            $btnVerificar.trigger('click');
        }
    });

    // --------------------------------------------------
    // Reenviar OTP
    // --------------------------------------------------
    $reenviar.on('click', function () {
        if (!currentEmail) {
            alert('Error: No hay correo registrado');
            return;
        }
        var original = $reenviar.text();
        $reenviar.text('⏳ Enviando...').css('cursor', 'default');

        peticion('salud_reenviar_otp', { email: currentEmail }, function () {
            $reenviar.text('✅ Reenviado');

            /*if (data && data.duracion) {
                OTP_DURATION = parseInt(data.duracion, 10);
            }*/

            iniciarTemporizador();
            limpiarErrores();
            limpiarInputsOTP();
            $btnVerificar.prop('disabled', false);
            setTimeout(function () {
                $reenviar.text(original).css('cursor', 'pointer');
            }, 3000);
        }, function (err) {
            alert('Error: ' + err.message);
            $reenviar.text(original).css('cursor', 'pointer');
        });
    });







    // --------------------------------------------------
    // Helpers para los 6 inputs OTP
    // --------------------------------------------------
    function obtenerCodigoOTP() {
        var codigo = '';
        $digitInputs.each(function () {
            codigo += $(this).val();
        });
        return codigo;
    }

    function setCodigoOTP(codigo) {
        codigo = (codigo || '').replace(/\D/g, '').slice(0, 6);
        $digitInputs.each(function (i) {
            $(this).val(codigo[i] || '');
            $(this).toggleClass('filled', !!codigo[i]);
        });
        $('#otp-codigo').val(codigo);
    }

    function limpiarInputsOTP() {
        $digitInputs.val('').removeClass('filled error');
        $('#otp-codigo').val('');
        $digitInputs.eq(0).trigger('focus');
    }

    function marcarErrorInputsOTP() {
        $digitInputs.addClass('error');
        setTimeout(function () {
            $digitInputs.removeClass('error');
        }, 1500);
    }

    // --------------------------------------------------
    // Manejo de los 6 inputs individuales
    // --------------------------------------------------
    $digitInputs.on('input', function (e) {
        var $this = $(this);
        var val = $this.val();

        // Solo permitir dígitos
        val = val.replace(/\D/g, '');

        // Si pegaron varios dígitos en un input, distribuirlos
        if (val.length > 1) {
            var index = parseInt($this.data('index'), 10);
            var chars = val.split('');
            for (var i = 0; i < chars.length && (index + i) < 6; i++) {
                $digitInputs.eq(index + i).val(chars[i]).addClass('filled');
            }
            var next = Math.min(index + chars.length, 5);
            $digitInputs.eq(next).trigger('focus');
        } else {
            $this.val(val);
            $this.toggleClass('filled', !!val);
            // Auto-avanzar al siguiente
            if (val && $this.data('index') < 5) {
                $digitInputs.eq($this.data('index') + 1).trigger('focus');
            }
        }

        $('#otp-codigo').val(obtenerCodigoOTP());
        $codigoError.removeClass('show');
        $digitInputs.removeClass('error');
    });

    $digitInputs.on('keydown', function (e) {
        var $this = $(this);
        var index = parseInt($this.data('index'), 10);

        // Retroceso: si está vacío, ir al anterior
        if (e.key === 'Backspace' && !$this.val() && index > 0) {
            e.preventDefault();
            $digitInputs.eq(index - 1).val('').removeClass('filled').trigger('focus');
            $('#otp-codigo').val(obtenerCodigoOTP());
        }

        // Flechas de navegación
        if (e.key === 'ArrowLeft' && index > 0) {
            e.preventDefault();
            $digitInputs.eq(index - 1).trigger('focus');
        }
        if (e.key === 'ArrowRight' && index < 5) {
            e.preventDefault();
            $digitInputs.eq(index + 1).trigger('focus');
        }

        // Enter → verificar
        if (e.key === 'Enter') {
            e.preventDefault();
            $btnVerificar.trigger('click');
        }
    });

    // Pegar código completo
    $digitInputs.on('paste', function (e) {
        e.preventDefault();
        var paste = (e.originalEvent.clipboardData || window.clipboardData).getData('text');
        paste = (paste || '').replace(/\D/g, '').slice(0, 6);
        if (paste) {
            setCodigoOTP(paste);
            var next = Math.min(paste.length, 5);
            $digitInputs.eq(next).trigger('focus');
            $('#otp-codigo').val(paste);
        }
    });

    // Foco al primer input al mostrarse el paso 2
    $digitInputs.eq(0).on('focus', function () {
        $(this).select();
    });


    // Foco inicial
    //$correoInput.trigger('focus');
});