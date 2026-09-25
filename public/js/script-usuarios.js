jQuery(document).ready(function ($) {

    // ============================================================
    //  FALLBACK SweetAlert2
    // ============================================================
    if (typeof Swal === 'undefined') {
        console.warn('[SWAL] SweetAlert2 no disponible, usando fallback nativo');
        window.Swal = {
            fire: function (opts) {
                var msg = (opts && (opts.text || opts.title)) || '';
                if (opts && opts.showCancelButton) {
                    var ok = confirm(msg);
                    return Promise.resolve({
                        isConfirmed: ok,
                        isDismissed: !ok,
                        dismiss: ok ? 'confirm' : 'cancel'
                    });
                }
                alert(msg);
                return Promise.resolve({ isConfirmed: true, isDismissed: false, dismiss: 'confirm' });
            },
            close: function () { },
            showLoading: function () { },
            isVisible: function () { return false; },
            DismissReason: { cancel: 'cancel', backdrop: 'backdrop', esc: 'esc' }
        };
    }

    var SWAL_COLOR = '#1a1a6e';

    function swalExito(titulo, mensaje) {
        return Swal.fire({
            title: titulo || '¡Listo!',
            text: mensaje || '',
            icon: 'success',
            confirmButtonColor: SWAL_COLOR,
            confirmButtonText: 'Aceptar',
            timer: 3000,
            timerProgressBar: true,
        });
    }
    function swalError(titulo, mensaje) {
        return Swal.fire({
            title: titulo || 'Ups...',
            text: mensaje || '',
            icon: 'error',
            confirmButtonColor: SWAL_COLOR,
            confirmButtonText: 'Entendido',
        });
    }
    function swalAdvertencia(titulo, mensaje) {
        return Swal.fire({
            title: titulo || 'Atención',
            text: mensaje || '',
            icon: 'warning',
            confirmButtonColor: SWAL_COLOR,
            confirmButtonText: 'Entendido',
        });
    }
    function swalCargando(titulo) {
        return Swal.fire({
            title: titulo || 'Procesando...',
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: function () { Swal.showLoading(); }
        });
    }
    function swalCerrar() {
        if (typeof Swal !== 'undefined' && Swal.isVisible()) Swal.close();
    }

    // ============================================================
    //  REFERENCIAS
    // ============================================================
    var $vistaConsulta = $('#vista-consulta');
    var $vistaResultados = $('#vista-resultados');
    var $inputIdent = $('#input-identificacion');
    var $btnConsultar = $('#btn-consultar');
    var $btnSalir = $('#btn-salir-consulta');
    var $tablaBody = $('#ac-tabla-body');
    var $btnDescargar = $('#btn-descargar');
    var $btnNuevaConsulta = $('#btn-nueva-consulta');
    var $btnSalirRes = $('#btn-salir-resultados');
    var $historialLista = $('#ac-historial-lista');
    var $historialPaginacion = $('#ac-historial-paginacion');
    var historialPagina = 1;

    var timerSesion = null;
    var nonceActual = null;
    var inactividadSeg = parseInt(usuarios_ajax.inactividad_total || 1200, 10);
    var avisoSegundos = parseInt(usuarios_ajax.aviso_segundos || 60, 10);
    var absolutoRestante = 0;
    var inactividadRestante = inactividadSeg;

    // ============================================================
    //  HELPERS
    // ============================================================
    function escapeHtml(text) {
        if (text === null || text === undefined) return '';
        var div = document.createElement('div');
        div.textContent = String(text);
        return div.innerHTML;
    }

    function obtenerNonceFresco() {
        var deferred = $.Deferred();
        $.post(usuarios_ajax.ajax_url, { action: 'salud_fresh_nonce' })
            .done(function (resp) {
                if (resp && resp.success && resp.data && resp.data.nonce) {
                    nonceActual = resp.data.nonce;
                    deferred.resolve(nonceActual);
                } else {
                    deferred.resolve(nonceActual);
                }
            })
            .fail(function () { deferred.resolve(nonceActual); });
        return deferred.promise();
    }

    function ajaxConNonce(action, data, onSuccess, onError) {
        obtenerNonceFresco().always(function (nonce) {
            var payload = $.extend({ action: action, nonce: nonce }, data || {});

            var timeoutId = setTimeout(function () {
                swalCerrar();
                if (onError) onError({ message: 'La operación tardó demasiado. Intenta de nuevo.' });
            }, 60000);

            $.ajax({
                url: usuarios_ajax.ajax_url,
                type: 'POST',
                dataType: 'json',
                data: payload,
                timeout: 55000,
                success: function (resp) {
                    clearTimeout(timeoutId);
                    if (resp && resp.success) {
                        if (onSuccess) onSuccess(resp.data);
                    } else {
                        var msg = (resp && resp.data && resp.data.message) ? resp.data.message : 'Error inesperado';
                        if (onError) onError({ message: msg, code: resp && resp.data && resp.data.code });
                    }
                },
                error: function (xhr, status) {
                    clearTimeout(timeoutId);
                    if (xhr && xhr.status === 401) {
                        window.location.reload();
                        return;
                    }
                    var msg = 'Error de conexión';
                    if (status === 'timeout') msg = 'El servidor tardó demasiado en responder.';
                    else if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                        msg = xhr.responseJSON.data.message;
                    }
                    if (onError) onError({ message: msg });
                }
            });
        });
    }

    // ============================================================
    //  ACTIVIDAD DEL USUARIO
    // ============================================================
    var ultimoEnvioActividad = 0;
    var UMBRAL_ACTIVIDAD_MS = 30000;   // enviar ping máx 1 vez cada 30s
    var modalAvisoAbierto = false;

    function enviarPingActividad() {
        var ahora = Date.now();
        if (ahora - ultimoEnvioActividad < UMBRAL_ACTIVIDAD_MS) return;
        ultimoEnvioActividad = ahora;

        $.post(usuarios_ajax.ajax_url, {
            action: 'salud_activity_ping',
            nonce: nonceActual || ''
        }, function (resp) {
            if (resp && resp.success && resp.data) {
                absolutoRestante = parseInt(resp.data.absoluto_restante, 10) || absolutoRestante;
                inactividadRestante = parseInt(resp.data.inactividad_restante, 10) || inactividadRestante;
                actualizarTimerSesion(absolutoRestante);
            } else if (resp && resp.data && resp.data.code === 'UNAUTHORIZED') {
                window.location.reload();
            }
        }).fail(function (xhr) {
            if (xhr && xhr.status === 401) window.location.reload();
        });
    }

    function registrarActividadLocal() {
        // Si el modal de aviso está abierto, NO cuenta como actividad
        // (el usuario debe presionar "Continuar")
        if (modalAvisoAbierto) return;
        enviarPingActividad();
    }

    $(document).on('mousemove keydown click scroll touchstart', function () {
        registrarActividadLocal();
    });

    // ============================================================
    //  TIMER DE SESIÓN
    // ============================================================
    function actualizarTimerSesion(segAbsoluto) {
        var h = Math.floor(segAbsoluto / 3600);
        var m = Math.floor((segAbsoluto % 3600) / 60);
        var s = segAbsoluto % 60;
        var texto = String(h).padStart(2, '0') + ':' +
            String(m).padStart(2, '0') + ':' +
            String(s).padStart(2, '0');
        $('#sesion-timer-tiempo, #sesion-timer-tiempo-2').text(texto);
    }

    function iniciarTimerSesion() {
        var restanteInicial = 0;
        if (typeof usuarios_ajax.sesion_restante !== 'undefined' && usuarios_ajax.sesion_restante > 0) {
            restanteInicial = parseInt(usuarios_ajax.sesion_restante, 10);
        } else {
            restanteInicial = 2 * 60 * 60;
        }

        absolutoRestante = restanteInicial;
        inactividadRestante = inactividadSeg;
        actualizarTimerSesion(absolutoRestante);

        var ultimaSync = Date.now();
        var ultimoTick = Date.now();

        if (timerSesion) clearInterval(timerSesion);
        timerSesion = setInterval(function () {
            var ahora = Date.now();
            var delta = Math.floor((ahora - ultimoTick) / 1000);
            ultimoTick = ahora;

            absolutoRestante = Math.max(0, absolutoRestante - delta);
            inactividadRestante = Math.max(0, inactividadRestante - delta);

            // Sincronizar con servidor cada 60s
            if (ahora - ultimaSync > 60000) {
                ultimaSync = ahora;
                $.post(usuarios_ajax.ajax_url, { action: 'salud_session_status', nonce: nonceActual || '' })
                    .done(function (resp) {
                        if (resp && resp.success && resp.data) {
                            absolutoRestante = parseInt(resp.data.absoluto_restante, 10) || 0;
                            inactividadRestante = parseInt(resp.data.inactividad_restante, 10) || 0;
                        } else if (resp && resp.data && resp.data.code === 'UNAUTHORIZED') {
                            window.location.reload();
                        }
                    })
                    .fail(function (xhr) {
                        if (xhr && xhr.status === 401) window.location.reload();
                    });
            }

            // Si llega a 0 el absoluto → cerrar
            if (absolutoRestante <= 0) {
                clearInterval(timerSesion);
                swalAdvertencia('Sesión expirada', 'Tu sesión ha alcanzado el tiempo máximo. Serás redirigido al inicio.')
                    .then(function () { window.location.reload(); });
                return;
            }

            // Si la inactividad entra en la ventana de aviso → mostrar modal
            if (inactividadRestante <= avisoSegundos && !modalAvisoAbierto) {
                mostrarAvisoInactividad();
            }

            // Si la inactividad llega a 0 → cerrar
            if (inactividadRestante <= 0) {
                clearInterval(timerSesion);
                swalAdvertencia('Sesión cerrada por inactividad',
                    'Tu sesión se cerró por no registrar actividad. Serás redirigido al inicio.')
                    .then(function () { window.location.reload(); });
                return;
            }

            actualizarTimerSesion(absolutoRestante);
        }, 1000);
    }

    function mostrarAvisoInactividad() {
        modalAvisoAbierto = true;
        var restante = inactividadRestante;

        Swal.fire({
            title: '¿Sigues ahí?',
            html: 'Tu sesión se cerrará por inactividad en <strong>' + restante + ' segundos</strong>.<br>Presiona "Continuar" para extenderla.',
            icon: 'warning',
            allowOutsideClick: false,
            allowEscapeKey: false,
            showCancelButton: true,
            confirmButtonColor: SWAL_COLOR,
            cancelButtonColor: '#9498b3',
            confirmButtonText: 'Continuar sesión',
            cancelButtonText: 'Cerrar ahora',
            reverseButtons: true,
            timer: (restante * 1000) + 500,
            timerProgressBar: true,
            didOpen: function () {
                var intervalAviso = setInterval(function () {
                    var el = Swal.getHtmlContainer();
                    if (el) {
                        var t = Math.max(0, inactividadRestante);
                        el.innerHTML = 'Tu sesión se cerrará por inactividad en <strong>' + t + ' segundos</strong>.<br>Presiona "Continuar" para extenderla.';
                    }
                }, 1000);
                Swal.getPopup().addEventListener('swal:close', function () {
                    clearInterval(intervalAviso);
                });
            }
        }).then(function (result) {
            modalAvisoAbierto = false;

            if (result.isConfirmed) {
                // Renovar sesión
                ultimoEnvioActividad = 0;
                enviarPingActividad();
                swalExito('Sesión extendida', 'Puedes seguir trabajando.');
            } else {
                // Cerrar ahora (o expiró el timer del modal)
                cerrarSesionAutomatico();
            }
        });
    }

    function cerrarSesionAutomatico() {
        $.ajax({
            url: usuarios_ajax.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: { action: 'salud_cerrar_sesion' },
            complete: function () {
                var url = window.location.href.split('#')[0].split('?')[0];
                window.location.href = url + '?logout=' + Date.now();
            }
        });
    }

    // ============================================================
    //  CONSULTA
    // ============================================================
    function realizarConsulta(identificacionOverride) {
        var identificacion = identificacionOverride || $.trim($inputIdent.val());

        if (identificacion === '') {
            $inputIdent.addClass('ac-input-error').focus();
            swalAdvertencia('Campo vacío', 'Por favor ingresa un número de identificación.');
            return;
        }

        $inputIdent.removeClass('ac-input-error').val(identificacion);
        $btnConsultar.prop('disabled', true).text('Consultando...');
        swalCargando('Consultando afiliado...');

        ajaxConNonce(
            'salud_consultar_por_identificacion',
            { identificacion: identificacion },
            function (data) {
                swalCerrar();
                renderResultado(data);
                $vistaConsulta.hide();
                $vistaResultados.show();
                $btnConsultar.prop('disabled', false).text('CONSULTAR');
                cargarHistorial(1);
            },
            function (err) {
                swalCerrar();
                $btnConsultar.prop('disabled', false).text('CONSULTAR');
                swalError('No encontrado', err.message || 'No se encontró el afiliado con ese número de identificación.');
            }
        );
    }

    function renderResultado(personas) {
        if (!Array.isArray(personas)) personas = [personas];

        if (personas.length === 0) {
            $tablaBody.html('<tr><td colspan="9" style="text-align:center;padding:24px;color:#888;">No se encontró información del afiliado</td></tr>');
            return;
        }

        var html = '';
        $.each(personas, function (i, p) {
            var tipoAfiliado = (p.tipo_afiliado || '').toUpperCase();
            var esCotizante = (tipoAfiliado === 'COTIZANTE');
            var etiquetaParentesco = '';
            if (!esCotizante && p.parentesco) {
                etiquetaParentesco = ' <small style="color:#6b6f8a;">(' + escapeHtml(p.parentesco) + ')</small>';
            }

            html += '<tr data-tipo="' + escapeHtml(tipoAfiliado) + '">'
                + '<td class="ac-td-checkbox"><input type="checkbox" class="ac-checkbox-fila" checked></td>'
                + '<td>' + escapeHtml(tipoAfiliado) + etiquetaParentesco + '</td>'
                + '<td>' + escapeHtml(p.numerodocumento || '') + '</td>'
                + '<td>' + escapeHtml(p.nombre_completo || '') + '</td>'
                + '<td>' + escapeHtml(p.edad !== undefined && p.edad !== null ? p.edad : '') + '</td>'
                + '<td>' + escapeHtml(p.fecha_afiliacion_formateada || '') + '</td>'
                + '<td>' + escapeHtml(p.nivel_salarial || '') + '</td>'
                + '<td>' + escapeHtml(p.estado_descripcion || '') + '</td>'
                + '<td>' + escapeHtml(p.prestadora || 'NUEVA EPS') + '</td>'
                + '</tr>';
        });

        $tablaBody.html(html);
    }

    function volverAConsulta() {
        $vistaResultados.hide();
        $vistaConsulta.show();
        $inputIdent.val('').focus();
        $tablaBody.empty();
        $('input[name="tipo_certificado"]').prop('checked', false);
        $('#certificado-genera').val('');
        $btnDescargar.prop('disabled', true);
        cargarHistorial(1);
    }

    function cerrarSesion() {
        Swal.fire({
            title: '¿Cerrar sesión?',
            text: 'Se cerrará tu sesión actual y volverás a la pantalla de inicio.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: SWAL_COLOR,
            cancelButtonColor: '#9498b3',
            confirmButtonText: 'Sí, cerrar sesión',
            cancelButtonText: 'Cancelar',
            reverseButtons: true,
        }).then(function (result) {
            if (!result.isConfirmed) return;
            $btnSalir.prop('disabled', true).text('Saliendo...');
            $btnSalirRes.prop('disabled', true).text('Saliendo...');
            cerrarSesionAutomatico();
        });
    }

    // ============================================================
    //  PDF
    // ============================================================
    $(document).on('change', 'input[name="tipo_certificado"]', function () {
        $btnDescargar.prop('disabled', false);
    });

    function descargarCertificado(identificacionOverride, tipoOverride) {
        var identificacion = identificacionOverride || $.trim($inputIdent.val());
        if (identificacion === '') {
            identificacion = $('#ac-tabla-body tr').first().find('td').eq(2).text().trim();
        }
        if (identificacion === '') {
            swalAdvertencia('Sin identificación', 'No se pudo identificar el documento a certificar.');
            return;
        }

        var tipo = tipoOverride || $('input[name="tipo_certificado"]:checked').val();
        if (!tipo) {
            swalAdvertencia('Sin tipo', 'Selecciona un tipo de certificado (INDIVIDUAL o GRUPO FAMILIAR).');
            return;
        }

        var generaPara = $('#certificado-genera').val().trim();
        var $btnOrigen = $btnDescargar;
        var textoOriginal = $btnOrigen.text();
        var esBotonPrincipal = $btnOrigen.is(':visible');

        if (esBotonPrincipal) $btnOrigen.prop('disabled', true).text('Generando...');
        swalCargando('Generando certificado PDF...');

        ajaxConNonce(
            'salud_generar_pdf',
            { identificacion: identificacion, tipo_certificado: tipo, genera_para: generaPara },
            function (data) {
                try {
                    var binario = atob(data.archivo);
                    var bytes = new Uint8Array(binario.length);
                    for (var i = 0; i < binario.length; i++) bytes[i] = binario.charCodeAt(i);
                    var blob = new Blob([bytes], { type: 'application/pdf' });
                    var url = URL.createObjectURL(blob);
                    var a = document.createElement('a');
                    a.href = url;
                    a.download = data.nombre || 'certificado.pdf';
                    document.body.appendChild(a); a.click(); document.body.removeChild(a);
                    setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
                    swalCerrar();
                    swalExito('Certificado generado', 'El archivo se está descargando.');
                    if (esBotonPrincipal) $btnOrigen.prop('disabled', false).text(textoOriginal);
                } catch (e) {
                    swalCerrar();
                    swalError('Error al procesar', 'No se pudo procesar el PDF descargado.');
                    if (esBotonPrincipal) $btnOrigen.prop('disabled', false).text(textoOriginal);
                }
            },
            function (err) {
                swalCerrar();
                swalError('No se pudo generar', err.message || 'No se pudo generar el certificado.');
                if (esBotonPrincipal) $btnOrigen.prop('disabled', false).text(textoOriginal);
            }
        );
    }

    // ============================================================
    //  HISTORIAL
    // ============================================================
    function cargarHistorial(pagina) {
        pagina = pagina || 1;
        historialPagina = pagina;
        $historialLista.html('<div class="ac-historial-cargando">Cargando historial...</div>');

        ajaxConNonce('salud_get_historial', { pagina: pagina }, function (data) {
            renderHistorial(data);
        }, function (err) {
            $historialLista.html('<div class="ac-historial-vacio">No se pudo cargar el historial: ' + escapeHtml(err.message || 'Error') + '</div>');
            $historialPaginacion.hide();
        });
    }

    function renderHistorial(data) {
        var historial = data.historial || [];
        if (historial.length === 0) {
            $historialLista.html('<div class="ac-historial-vacio">Aún no has realizado ninguna consulta.</div>');
            $historialPaginacion.hide();
            return;
        }

        var html = '<div class="ac-historial-tabla-wrapper">';
        html += '<table class="ac-historial-tabla">';
        html += '<thead><tr class="ac-historial-titulo-fila"><th colspan="5">HISTORIAL</th></tr>';
        html += '<tr class="ac-historial-columnas">';
        html += '<th>FECHA DE CONSULTA</th><th>TIPO DE AFILIACIÓN</th><th>IDENTIFICACIÓN</th><th>NOMBRE COMPLETO</th><th>ACCIONES</th>';
        html += '</tr></thead><tbody>';

        $.each(historial, function (i, h) {
            var tipo = (h.tipo_afiliado || '—').toUpperCase();
            var identificacionEsc = escapeHtml(h.identificacion_consultada || '');

            html += '<tr>';
            html += '<td>' + escapeHtml(h.fecha_formateada || '') + '</td>';
            html += '<td>' + escapeHtml(tipo) + '</td>';
            html += '<td>' + identificacionEsc + '</td>';
            html += '<td>' + escapeHtml(h.nombre_consultado || '—') + '</td>';
            html += '<td class="ac-hist-acciones-cell"><div class="ac-hist-acciones">';
            html += '<button type="button" class="ac-hist-icon-btn ac-hist-btn-ver" data-identificacion="' + identificacionEsc + '" title="Ver"><i class="fa fa-eye"></i></button>';
            html += '<button type="button" class="ac-hist-icon-btn ac-hist-btn-pdf" data-identificacion="' + identificacionEsc + '" title="PDF"><i class="fa fa-download"></i></button>';
            html += '</div></td></tr>';
        });

        html += '</tbody></table></div>';
        $historialLista.html(html);

        if (data.total_paginas > 1) {
            var pag = '<div class="ac-historial-pag"><span class="ac-historial-total">Total: ' + data.total + ' consultas</span>';
            pag += '<span class="ac-historial-botones">';
            if (data.pagina > 1) pag += '<a href="#" class="ac-hist-page" data-page="' + (data.pagina - 1) + '">‹ Anterior</a>';
            pag += '<span class="ac-hist-current">Página ' + data.pagina + ' de ' + data.total_paginas + '</span>';
            if (data.pagina < data.total_paginas) pag += '<a href="#" class="ac-hist-page" data-page="' + (data.pagina + 1) + '">Siguiente ›</a>';
            pag += '</span></div>';
            $historialPaginacion.html(pag).show();
        } else {
            $historialPaginacion.hide();
        }
    }

    // ============================================================
    //  EVENTOS
    // ============================================================
    $btnConsultar.on('click', function () { realizarConsulta(); });
    $inputIdent.on('keypress', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); realizarConsulta(); }
    });

    $btnSalir.on('click', cerrarSesion);
    $btnSalirRes.on('click', cerrarSesion);
    $btnNuevaConsulta.on('click', volverAConsulta);

    $btnDescargar.on('click', function (e) {
        e.preventDefault();
        descargarCertificado();
    });

    $(document).on('click', '.ac-hist-page', function (e) {
        e.preventDefault();
        var page = $(this).data('page');
        if (page) {
            cargarHistorial(page);
            $('html, body').animate({ scrollTop: $('.ac-historial-wrapper').offset().top - 80 }, 300);
        }
    });

    $(document).on('click', '.ac-hist-btn-ver', function (e) {
        e.preventDefault(); e.stopPropagation();
        var ident = $(this).data('identificacion');
        if (!ident) { swalAdvertencia('Sin identificación', 'No hay identificación en este registro.'); return; }
        $inputIdent.val(ident);
        realizarConsulta(ident);
    });

    $(document).on('click', '.ac-hist-btn-pdf', function (e) {
        e.preventDefault(); e.stopPropagation();
        var ident = $(this).data('identificacion');
        if (!ident) { swalAdvertencia('Sin identificación', 'No hay identificación en este registro.'); return; }

        var $btn = $(this);
        var textoOriginal = $btn.html();

        Swal.fire({
            title: 'Tipo de certificado',
            text: '¿Qué tipo de certificado deseas generar?',
            icon: 'question',
            showCancelButton: true,
            showDenyButton: true,
            confirmButtonColor: SWAL_COLOR,
            denyButtonColor: '#4a50a0',
            cancelButtonColor: '#9498b3',
            confirmButtonText: 'INDIVIDUAL',
            denyButtonText: 'GRUPO FAMILIAR',
            cancelButtonText: 'Cancelar',
            reverseButtons: true,
        }).then(function (result) {
            if (result.dismiss === Swal.DismissReason.cancel ||
                result.dismiss === Swal.DismissReason.backdrop ||
                result.dismiss === Swal.DismissReason.esc) return;

            var tipo = result.isConfirmed ? 'INDIVIDUAL' : 'GRUPO FAMILIAR';
            $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');
            swalCargando('Generando certificado ' + tipo + '...');

            ajaxConNonce('salud_generar_pdf',
                { identificacion: ident, tipo_certificado: tipo, genera_para: '' },
                function (data) {
                    try {
                        var binario = atob(data.archivo);
                        var bytes = new Uint8Array(binario.length);
                        for (var i = 0; i < binario.length; i++) bytes[i] = binario.charCodeAt(i);
                        var blob = new Blob([bytes], { type: 'application/pdf' });
                        var url = URL.createObjectURL(blob);
                        var a = document.createElement('a');
                        a.href = url; a.download = data.nombre || 'certificado.pdf';
                        document.body.appendChild(a); a.click(); document.body.removeChild(a);
                        setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
                        swalCerrar();
                        swalExito('Certificado generado', 'El archivo se está descargando.');
                        $btn.prop('disabled', false).html(textoOriginal);
                    } catch (ex) {
                        swalCerrar();
                        swalError('Error al procesar', 'No se pudo procesar el PDF descargado.');
                        $btn.prop('disabled', false).html(textoOriginal);
                    }
                },
                function (err) {
                    swalCerrar();
                    swalError('No se pudo generar', err.message || 'No se pudo generar el certificado.');
                    $btn.prop('disabled', false).html(textoOriginal);
                }
            );
        });
    });

    // ============================================================
    //  ARRANQUE
    // ============================================================
    obtenerNonceFresco().always(function () {
        iniciarTimerSesion();
        $inputIdent.focus();
        cargarHistorial(1);
    });
});