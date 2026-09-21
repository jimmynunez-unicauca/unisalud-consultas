jQuery(document).ready(function ($) {

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

    // ============================================================
    //  HELPERS
    // ============================================================
    function escapeHtml(text) {
        if (text === null || text === undefined) return '';
        var div = document.createElement('div');
        div.textContent = String(text);
        return div.innerHTML;
    }

    /**
     * Obtiene un nonce FRESCO del servidor.
     * Nunca usamos el nonce del HTML porque puede estar caducado.
     */
    function obtenerNonceFresco() {
        var deferred = $.Deferred();

        $.post(
            usuarios_ajax.ajax_url,
            { action: 'salud_fresh_nonce' }
        ).done(function (resp) {
            if (resp && resp.success && resp.data && resp.data.nonce) {
                nonceActual = resp.data.nonce;
                deferred.resolve(nonceActual);
            } else {
                // Fallback al que teníamos
                deferred.resolve(nonceActual);
            }
        }).fail(function () {
            // Fallback al que teníamos
            deferred.resolve(nonceActual);
        });

        return deferred.promise();
    }

    /**
     * Hace una petición AJAX garantizando nonce fresco.
     *   - Pide un nonce nuevo
     *   - Envía la petición con ese nonce
     */
    function ajaxConNonce(action, data, onSuccess, onError) {
        obtenerNonceFresco().always(function (nonce) {
            var payload = $.extend(
                { action: action, nonce: nonce },
                data || {}
            );
            $.ajax({
                url: usuarios_ajax.ajax_url,
                type: 'POST',
                dataType: 'json',
                data: payload,
                success: function (resp) {
                    if (resp && resp.success) {
                        if (onSuccess) onSuccess(resp.data);
                    } else {
                        var msg = (resp && resp.data && resp.data.message)
                            ? resp.data.message
                            : 'Error inesperado';
                        if (onError) onError({
                            message: msg,
                            code: resp && resp.data && resp.data.code
                        });
                    }
                },
                error: function (xhr) {
                    if (xhr && xhr.status === 401) {
                        window.location.reload();
                        return;
                    }
                    var msg = 'Error de conexión';
                    if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                        msg = xhr.responseJSON.data.message;
                    }
                    if (onError) onError({ message: msg });
                }
            });
        });
    }

    // ============================================================
    //  TIMER DE SESIÓN — sincronizado con el servidor
    // ============================================================
    var ultimaSync = Date.now();
    var SESSION_START_KEY = 'unisalud_session_start';

    function iniciarTimerSesion() {
        // 1) Tiempo inicial: lo que diga el servidor (recomendado)
        var restanteInicial = 0;
        if (typeof usuarios_ajax.sesion_restante !== 'undefined' && usuarios_ajax.sesion_restante > 0) {
            restanteInicial = parseInt(usuarios_ajax.sesion_restante, 10);
        } else {
            restanteInicial = 2 * 60 * 60; // fallback: 2 horas
        }

        var segundos = restanteInicial;
        actualizarTimer(segundos);

        // 2) Guardar cuándo fue la última sincronización
        var ultimaSync = Date.now();

        if (timerSesion) clearInterval(timerSesion);
        timerSesion = setInterval(function () {
            segundos--;

            // 3) Cada 60s preguntar al servidor cuánto queda realmente
            if (Date.now() - ultimaSync > 60000) {
                ultimaSync = Date.now();
                $.post(
                    usuarios_ajax.ajax_url,
                    { action: 'salud_session_status' }
                ).done(function (resp) {
                    if (resp && resp.success && resp.data && typeof resp.data.restante !== 'undefined') {
                        segundos = parseInt(resp.data.restante, 10);
                    }
                });
            }

            // 4) Cuando llegue a 0, recargar (el servidor ya habrá matado la sesión)
            if (segundos <= 0) {
                clearInterval(timerSesion);
                window.location.reload();
                return;
            }

            actualizarTimer(segundos);
        }, 1000);
    }

    function actualizarTimer(seg) {
        var h = Math.floor(seg / 3600);
        var m = Math.floor((seg % 3600) / 60);
        var s = seg % 60;
        var texto = String(h).padStart(2, '0') + ':' +
            String(m).padStart(2, '0') + ':' +
            String(s).padStart(2, '0');
        $('#sesion-timer-tiempo, #sesion-timer-tiempo-2').text(texto);
    }

    // ============================================================
    //  CONSULTA POR IDENTIFICACIÓN
    // ============================================================
    function realizarConsulta() {
        var identificacion = $.trim($inputIdent.val());

        if (identificacion === '') {
            $inputIdent.addClass('ac-input-error').focus();
            return;
        }

        $inputIdent.removeClass('ac-input-error');
        $btnConsultar.prop('disabled', true).text('Consultando...');

        ajaxConNonce(
            'salud_consultar_por_identificacion',
            { identificacion: identificacion },
            function (data) {
                renderResultado(data);
                $vistaConsulta.hide();
                $vistaResultados.show();
                $btnConsultar.prop('disabled', false).text('CONSULTAR');
                // ✅ Refrescar el historial en segundo plano
                cargarHistorial(1);
            },
            function (err) {
                $btnConsultar.prop('disabled', false).text('CONSULTAR');
                alert(err.message || 'No se encontró el afiliado');
            }
        );
    }

    function renderResultado(personas) {
        // Acepta tanto un array (cotizante + beneficiarios) como un solo objeto
        if (!Array.isArray(personas)) {
            personas = [personas];
        }

        if (personas.length === 0) {
            $tablaBody.html(
                '<tr><td colspan="9" style="text-align:center;padding:24px;color:#888;">' +
                'No se encontró información del afiliado' +
                '</td></tr>'
            );
            return;
        }

        var html = '';

        $.each(personas, function (i, p) {
            var tipoAfiliado = (p.tipo_afiliado || '').toUpperCase();
            var esCotizante = (tipoAfiliado === 'COTIZANTE');

            // Etiqueta secundaria para beneficiarios (parentesco)
            var etiquetaParentesco = '';
            if (!esCotizante && p.parentesco) {
                etiquetaParentesco = ' <small style="color:#6b6f8a;">(' + escapeHtml(p.parentesco) + ')</small>';
            }

            // El cotizante va con checkbox marcado; los beneficiarios también (por si en el futuro quieren seleccionar cuáles)
            var checked = 'checked';

            html += '<tr data-tipo="' + escapeHtml(tipoAfiliado) + '">'
                + '<td class="ac-td-checkbox">'
                + '<input type="checkbox" class="ac-checkbox-fila" ' + checked + '>'
                + '</td>'
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

    // ============================================================
    //  NUEVA CONSULTA / SALIR
    // ============================================================
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
        $btnSalir.prop('disabled', true).text('Saliendo...');
        $btnSalirRes.prop('disabled', true).text('Saliendo...');

        $.ajax({
            url: usuarios_ajax.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: { action: 'salud_cerrar_sesion' },
            complete: function (xhr) {
                console.log('logout status:', xhr.status);

                // Forzar recarga limpia (sin caché)
                var url = window.location.href.split('#')[0].split('?')[0];
                window.location.href = url + '?logout=' + Date.now();
            }
        });
    }

    // ============================================================
    //  HABILITAR BOTÓN DESCARGAR
    // ============================================================
    $(document).on('change', 'input[name="tipo_certificado"]', function () {
        $btnDescargar.prop('disabled', false);
    });

    // ============================================================
    //  EVENTOS
    // ============================================================
    $btnConsultar.on('click', realizarConsulta);

    $inputIdent.on('keypress', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            realizarConsulta();
        }
    });

    $btnSalir.on('click', cerrarSesion);
    $btnSalirRes.on('click', cerrarSesion);
    $btnNuevaConsulta.on('click', volverAConsulta);

    $btnDescargar.on('click', function (e) {
        e.preventDefault();
        alert('La generación de certificados se habilitará próximamente.');
    });

    // ============================================================
    //  HISTORIAL DE CONSULTAS
    // ============================================================
    function cargarHistorial(pagina) {
        pagina = pagina || 1;
        historialPagina = pagina;

        $historialLista.html('<div class="ac-historial-cargando">Cargando historial...</div>');

        ajaxConNonce('salud_get_historial', { pagina: pagina }, function (data) {
            renderHistorial(data);
        }, function (err) {
            $historialLista.html(
                '<div class="ac-historial-vacio">No se pudo cargar el historial: ' +
                escapeHtml(err.message || 'Error') + '</div>'
            );
            $historialPaginacion.hide();
        });
    }

    function renderHistorial(data) {
        var historial = data.historial || [];

        if (historial.length === 0) {
            $historialLista.html(
                '<div class="ac-historial-vacio">Aún no has realizado ninguna consulta.</div>'
            );
            $historialPaginacion.hide();
            return;
        }

        var html = '<div class="ac-historial-tabla-wrapper">';
        html += '<table class="ac-historial-tabla">';
        html += '<thead><tr>';
        html += '<th>FECHA</th>';
        html += '<th>IDENTIFICACIÓN</th>';
        html += '<th>NOMBRE</th>';
        html += '<th>TIPO</th>';
        html += '</tr></thead><tbody>';

        $.each(historial, function (i, h) {
            var tipo = (h.tipo_afiliado || '—').toUpperCase();
            var tipoClass = (tipo === 'BENEFICIARIO')
                ? 'ac-historial-tipo-benef'
                : 'ac-historial-tipo-cotiz';

            html += '<tr>';
            html += '<td>' + escapeHtml(h.fecha_formateada || '') + '</td>';
            html += '<td>' + escapeHtml(h.identificacion_consultada || '') + '</td>';
            html += '<td>' + escapeHtml(h.nombre_consultado || '—') + '</td>';
            html += '<td><span class="ac-historial-tipo ' + tipoClass + '">' +
                escapeHtml(tipo) + '</span></td>';
            html += '</tr>';
        });

        html += '</tbody></table></div>';
        $historialLista.html(html);

        // Paginación
        if (data.total_paginas > 1) {
            var pag = '<div class="ac-historial-pag">';
            pag += '<span class="ac-historial-total">Total: ' + data.total + ' consultas</span>';
            pag += '<span class="ac-historial-botones">';

            if (data.pagina > 1) {
                pag += '<a href="#" class="ac-hist-page" data-page="' + (data.pagina - 1) + '">‹ Anterior</a>';
            }

            pag += '<span class="ac-hist-current">Página ' + data.pagina + ' de ' + data.total_paginas + '</span>';

            if (data.pagina < data.total_paginas) {
                pag += '<a href="#" class="ac-hist-page" data-page="' + (data.pagina + 1) + '">Siguiente ›</a>';
            }

            pag += '</span></div>';
            $historialPaginacion.html(pag).show();
        } else {
            $historialPaginacion.hide();
        }
    }

    // Delegado: click en paginación del historial
    $(document).on('click', '.ac-hist-page', function (e) {
        e.preventDefault();
        var page = $(this).data('page');
        if (page) {
            cargarHistorial(page);
            $('html, body').animate({ scrollTop: $('.ac-historial-wrapper').offset().top - 80 }, 300);
        }
    });

    // ============================================================
    //  ARRANQUE
    // ============================================================
    // Pedir un nonce fresco al cargar (por si acaso) e iniciar timer.
    obtenerNonceFresco().always(function () {
        iniciarTimerSesion();
        $inputIdent.focus();
        cargarHistorial(1);
    });
});