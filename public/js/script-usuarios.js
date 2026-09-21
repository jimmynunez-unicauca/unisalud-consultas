jQuery(document).ready(function ($) {
    var cfg = window.usuarios_otp_ajax || {};
    var API = cfg.ajax_url;
    var NONCE = cfg.nonce;
    var REDIRECT = cfg.redirect;
    //inicio tiempo
    // ============================================
    // CONTADOR DE SESIÓN (viene desde PHP)
    // ============================================
    (function initSessionTimer() {
        var segundosRestantes = (typeof SEGUNDOS_RESTANTES !== 'undefined')
            ? parseInt(SEGUNDOS_RESTANTES, 10)
            : 0;

        var timerClock = document.getElementById('timerClock');
        var sessionTimer = document.getElementById('session-timer');

        if (!timerClock || !sessionTimer) return;

        if (segundosRestantes <= 0) {
            // Ya expiró al cargar → recargar para que PHP redirija
            window.location.reload();
            return;
        }

        function formatearTiempo(seg) {
            var h = Math.floor(seg / 3600);
            var m = Math.floor((seg % 3600) / 60);
            var s = seg % 60;
            return String(h).padStart(2, '0') + ':' +
                String(m).padStart(2, '0') + ':' +
                String(s).padStart(2, '0');
        }

        function actualizarContador() {
            timerClock.textContent = formatearTiempo(segundosRestantes);

            if (segundosRestantes <= 300) {
                sessionTimer.classList.add('warning');
            } else {
                sessionTimer.classList.remove('warning');
            }

            if (segundosRestantes <= 0) {
                clearInterval(intervalo);
                timerClock.textContent = '00:00:00';

                // 🔑 Cerrar sesión en servidor y recargar
                jQuery.post(usuarios_ajax.ajax_url, {
                    action: 'salud_cerrar_sesion',
                    nonce: usuarios_ajax.nonce
                }).always(function () {
                    window.location.reload();
                });
                return;
            }

            segundosRestantes--;
        }

        actualizarContador();
        var intervalo = setInterval(actualizarContador, 1000);
    })();
    //fin tiempo

    $('#btn-cerrar-sesion').on('click', function () {
        // 🔑 Cerrar sesión en servidor y recargar
        jQuery.post(usuarios_ajax.ajax_url, {
            action: 'salud_cerrar_sesion',
            nonce: usuarios_ajax.nonce
        }).always(function () {
            window.location.reload();
        });
    });

    $('#btn-cerrar-sesion2').on('click', function () {
        // 🔑 Cerrar sesión en servidor y recargar
        jQuery.post(usuarios_ajax.ajax_url, {
            action: 'salud_cerrar_sesion',
            nonce: usuarios_ajax.nonce
        }).always(function () {
            window.location.reload();
        });
    });

    var campoIdentificacion = $('#campo-identificacion');
    var btnConsultaAfiliado = $('#btn-consulta-afiliado');

    campoIdentificacion.on('keypress', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            btnConsultaAfiliado.trigger('click');
        }
    });

    //inicio buscar identificacion    
    $('#btn-consulta-afiliado').on('click', function () {        
        $.ajax({
            url: usuarios_ajax.ajax_url,
            type: 'POST',
            data: {
                action: "buscar_identificacion_afiliado",
                nonce: NONCE,
                identificacion: $('#campo-identificacion').val()
            },
            success: function (resp) {
                var formConsultaAfiliado = document.querySelector('.usuarios-consulta-afiliado-container');
                var tablaAfiliado = document.querySelector('.usuarios-tabla-afiliado-container');
                var btnSalirConsultaAfiliado = document.querySelector('.consulta-afiliado-btn-salir');
                var msgErrorIdentificacion = document.querySelector('#consulta-afiliado-correo-error');
                if (resp && resp.success && resp.data && resp.data.valid) {
                    pintarTablaAfiliados(resp.data.usuario);
                    formConsultaAfiliado.hidden = true;
                    tablaAfiliado.hidden = false;
                    btnSalirConsultaAfiliado.hidden = true;
                    msgErrorIdentificacion.hidden = true;
                } else {
                    msgErrorIdentificacion.hidden = false;
                    formConsultaAfiliado.hidden = false;
                    tablaAfiliado.hidden = true;
                    console.error("Error:", resp?.data?.message || 'Error inesperado');
                }
            },
            error: function (xhr) {
                console.error("Error de conexión:", xhr.responseJSON?.data?.message || 'Error de conexión');
            }
        });
    });
    //fin buscar identificacion
    //inicio tabla
    function pintarTablaAfiliados(usuario) {
        var $tbody = $('#tabla-afiliados-body');
        $tbody.empty();

        // usuario[0] = cotizante, usuario[1] = array de beneficiarios
        var cotizante = usuario[0];
        var beneficiarios = Array.isArray(usuario[1]) ? usuario[1] : [];

        // Unimos cotizante + beneficiarios en un solo array
        var filas = [cotizante, ...beneficiarios];

        filas.forEach(function (p) {
            var nombreCompleto = [
                p.primernombre,
                p.segundonombre,
                p.primerapellido,
                p.segundoapellido
            ].filter(Boolean).join(' ').trim();

            var edad = calcularEdad(p.fechanacimiento);
            var fechaAfiliacion = formatearFecha(p.fecha_afiliacion);

            var nivel = p.nivel_salarial_desc
                ? p.nivel_salarial_desc
                : (p.nivel_salarial_codigo || '—');

            var estado = p.estado_descripcion || p.tipo_estado || '—';

            var tipoAfiliacion = p.parentescobeneficiario
                ? 'BENEFICIARIO - ' + p.parentescobeneficiario
                : (p.tipoafiliado || 'COTIZANTE');

            var fila = `
            <tr>
                <td><input type="checkbox" class="chk-afiliado" data-id="${p.id}"></td>
                <td>${tipoAfiliacion}</td>
                <td>${p.tipoidentificacion || ''} ${p.numerodocumento || ''}</td>
                <td>${nombreCompleto}</td>
                <td>${edad}</td>
                <td>${fechaAfiliacion}</td>
                <td>${nivel}</td>
                <td>${estado}</td>
                <td>NUEVA EPS</td>
            </tr>
        `;
            $tbody.append(fila);
        });

        // Mostrar el contenedor quitando el atributo hidden
        $('.usuarios-consulta-afiliado-container').prop('hidden', false);
    }

    // Helpers
    function calcularEdad(fecha) {
        if (!fecha) return '';
        var fn = new Date(fecha);
        var hoy = new Date();
        var edad = hoy.getFullYear() - fn.getFullYear();
        var m = hoy.getMonth() - fn.getMonth();
        if (m < 0 || (m === 0 && hoy.getDate() < fn.getDate())) edad--;
        return edad;
    }

    function formatearFecha(fecha) {
        if (!fecha) return '';
        var f = new Date(fecha);
        var d = String(f.getDate()).padStart(2, '0');
        var m = String(f.getMonth() + 1).padStart(2, '0');
        var y = f.getFullYear();
        return `${d}/${m}/${y}`;
    }
    //fin tabla    


    $('#btn-realizar-nueva-consulta').on('click', function () {
        var formConsultaAfiliado = document.querySelector('.usuarios-consulta-afiliado-container');
        var tablaAfiliado = document.querySelector('.usuarios-tabla-afiliado-container');
        var btnSalirConsultaAfiliado = document.querySelector('.consulta-afiliado-btn-salir');
        var msgErrorIdentificacion = document.querySelector('.consulta-afiliado-identificacion-msg-error');
        var campoIdentificacionNuevaConsulta = document.querySelector('#campo-identificacion');

        formConsultaAfiliado.hidden = false;
        tablaAfiliado.hidden = true;
        btnSalirConsultaAfiliado.hidden = false;
        msgErrorIdentificacion.hidden = true;
        campoIdentificacionNuevaConsulta.value = '';
    });

});