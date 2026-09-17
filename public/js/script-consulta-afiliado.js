jQuery(document).ready(function ($) {
    var cfg = window.usuarios_otp_ajax || {};
    var API = cfg.ajax_url;
    var NONCE = cfg.nonce;
    var REDIRECT = cfg.redirect;

    var OTP_DURATION = 1 * 15; // 2 minutos en segundos

    var $step1 = $('#otp-step1');    
    var $mensaje = $('#otp-mensaje');
    var $correoInput = $('#otp-correo');
    var $correoError = $('#otp-correo-error');
    var $btnBuscar = $('#otp-btn-buscar');
    var $btnBuscarText = $('#otp-btn-buscar-text');
    var $btnBuscarSpinner = $('#otp-btn-buscar-spinner');
    var $codigoInput = $('#otp-codigo');
    var $codigoError = $('#otp-codigo-error');    
    var $timerVal = $('#otp-timer-value');    
    var $correoDestino = $('#otp-correo-destino');

    var timerInterval = null;
    var tiempoRestante = OTP_DURATION_CONSULTA_AFILIADO;
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
        

        timerInterval = setInterval(function () {
            tiempoRestante--;
            if (tiempoRestante <= 0) {
                clearInterval(timerInterval);
                timerInterval = null;
                $timerVal.text('0:00').addClass('expired');                                
                otpExpirado = true;
                    
                //mostrarError($codigoInput, $codigoError, '⏰ El código ha expirado. Solicita uno nuevo.');
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
            mostrarError($correoInput, $correoError, '⚠️ Por favor ingresa tu correo');
            return;
        }
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(correo)) {
            mostrarError($correoInput, $correoError, '⚠️ Correo con formato inválido');
            return;
        }

        toggleSpinner($btnBuscar, $btnBuscarText, $btnBuscarSpinner, true);

        peticion('salud_buscar_correos', { correo: correo }, function (data) {
            $correoDestino.text(data.correo);

            peticion('salud_enviar_otp', { correo: correo }, function () {
                $mensaje.addClass('show');
                $step1.addClass('hidden');              
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

        //temporizador
        // ============================================
        // CONTADOR DE SESIÓN (2 HORAS)
        // ============================================
        //let segundosRestantes = <?= (int)$segundosRestantes ?>;
        let segundosRestantes = SEGUNDOS_RESTANTES;

        const timerClock   = document.getElementById('timerClock');
        const sessionTimer = document.getElementById('session-timer');

        function formatearTiempo(seg) {
            const h = Math.floor(seg / 3600);
            const m = Math.floor((seg % 3600) / 60);
            const s = seg % 60;
            return String(h).padStart(2, '0') + ':' +
                   String(m).padStart(2, '0') + ':' +
                   String(s).padStart(2, '0');
        }

        function actualizarContador() {
            timerClock.textContent = formatearTiempo(segundosRestantes);

            // Advertencia cuando quedan 5 minutos o menos
            if (segundosRestantes <= 300) {
                sessionTimer.classList.add('warning');
            } else {
                sessionTimer.classList.remove('warning');
            }

            if (segundosRestantes <= 0) {
                clearInterval(intervalo);
                timerClock.textContent = '00:00:00';
                window.location.href = 'logout.php?expirada=1';
                return;
            }

            segundosRestantes--;
        }

        actualizarContador();
        const intervalo = setInterval(actualizarContador, 1000);

        // ============================================
        // ACCIÓN PROTEGIDA
        // ============================================
        function accionProtegida() {
            fetch('accion_protegida.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                    // ❌ Ya no mandamos X-Auth-Token
                },
                body: 'accion=test'
            })
            .then(r => r.json())
            .then(data => {
                if (!data.success) {
                    alert('❌ ' + data.message);
                    if (data.code === 'UNAUTHORIZED') {
                        window.location.href = 'formulario_smtp_otp.php';
                    }
                    return;
                }
                alert('✅ ' + data.message);
            });
        }
    //temporizador

});