jQuery(document).ready(function ($) {
    var filtroNombre = $('#filtro-nombre');
    var filtroEstado = $('#filtro-estado');
    var btnLimpiar = $('#btn-limpiar');
    var usuariosLista = $('#usuarios-lista');
    var paginacionContainer = $('#paginacion-container');
    var paginaActual = 1;
    var timeoutBusqueda;

    var modalOverlay = $('#modal-persona');
    var modalBody = $('#modal-persona-body');
    var modalTitulo = $('#modal-persona-titulo');

    // --------------------------------------------------
    // Helpers
    // --------------------------------------------------
    function escapeHtml(text) {
        if (text === null || text === undefined) return '';
        var div = document.createElement('div');
        div.textContent = String(text);
        return div.innerHTML;
    }

    function mostrarError401(xhr) {
        if (xhr && xhr.status === 401) {
            window.location.reload();
            return true;
        }
        return false;
    }

    // --------------------------------------------------
    // Carga de lista
    // --------------------------------------------------
    function cargarUsuarios() {
        var nombre = filtroNombre.val();
        var estado = filtroEstado.val();

        usuariosLista.html('<div class="loading">Cargando personas...</div>');

        $.ajax({
            url: usuarios_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'get_unisalud_consulta',
                nonce: usuarios_ajax.nonce,
                nombre: nombre,
                estado: estado,
                pagina: paginaActual
            },
            success: function (response) {
                if (response.success) {
                    mostrarUsuarios(response.data.usuarios);
                    mostrarPaginacion(response.data.pagina, response.data.total_paginas, response.data.total);
                } else {
                    var msg = (response.data && response.data.message) ? response.data.message : response.data;
                    var code = (response.data && response.data.code) ? response.data.code : '';
                    if (code === 'UNAUTHORIZED' || code === 'BAD_NONCE') {
                        window.location.reload();
                        return;
                    }
                    usuariosLista.html('<div class="error">Error: ' + msg + '</div>');
                    paginacionContainer.hide();
                }
            },
            error: function (xhr, status, err) {
                if (mostrarError401(xhr)) return;

                var mensaje = 'Error al cargar las personas';
                if (xhr && xhr.responseJSON && xhr.responseJSON.data) {
                    if (typeof xhr.responseJSON.data === 'string') {
                        mensaje = xhr.responseJSON.data;
                    } else if (xhr.responseJSON.data.message) {
                        mensaje = xhr.responseJSON.data.message;
                    }
                }
                console.error('AJAX Error:', {
                    status: xhr.status,
                    statusText: xhr.statusText,
                    response: xhr.responseJSON || xhr.responseText,
                    jqStatus: status,
                    jqError: err
                });
                usuariosLista.html('<div class="error">Error ' + xhr.status + ': ' + mensaje + '</div>');
                paginacionContainer.hide();
            }
        });
    }

    function mostrarUsuarios(usuarios) {
        if (usuarios.length === 0) {
            usuariosLista.html('<div class="no-usuarios">No se encontraron personas</div>');
            return;
        }

        var html = '';
        $.each(usuarios, function (i, p) {
            var iniciales = ((p.primernombre ? p.primernombre.charAt(0) : '') +
                (p.primerapellido ? p.primerapellido.charAt(0) : ''));
            if (!iniciales) iniciales = 'P';

            var contacto = '';
            if (p.correo) contacto += '<span>📧 ' + escapeHtml(p.correo) + '</span>';
            if (p.correo_alterno) contacto += '<span>📧 ' + escapeHtml(p.correo_alterno) + ' <em>(alt.)</em></span>';
            if (p.telefono) contacto += '<span>📞 ' + escapeHtml(p.telefono) + '</span>';
            if (p.celular) contacto += '<span>📱 ' + escapeHtml(p.celular) + '</span>';
            if (p.direccion) contacto += '<span>🏠 ' + escapeHtml(p.direccion) + '</span>';
            if (p.municipio) {
                var ubic = escapeHtml(p.municipio);
                if (p.departamento) ubic += ', ' + escapeHtml(p.departamento);
                contacto += '<span>📍 ' + ubic + '</span>';
            }

            html += `
                <div class="usuario-card">
                    <div class="usuario-avatar">${escapeHtml(iniciales)}</div>
                    <div class="usuario-info">
                        <h3 class="usuario-nombre">${escapeHtml(p.nombre_completo || 'Sin nombre')}</h3>
                        <p class="usuario-correo">🆔 ${escapeHtml(p.tipoidentificacion || '')} ${escapeHtml(p.numerodocumento || '')}</p>

                        <div class="usuario-detalles">
                            <span>⚧ ${escapeHtml(p.sexo_texto || 'No registrado')}</span>
                            <span>🩸 ${escapeHtml(p.tipo_sangre || 'No registrado')}</span>
                            <span>🎂 ${escapeHtml(p.fecha_formateada || '')}</span>
                        </div>

                        ${contacto ? `<div class="usuario-contacto">${contacto}</div>` : ''}

                        <div class="usuario-acciones">
                            <button type="button" class="btn-ver-mas" data-id="${p.id}">Ver más ▸</button>
                        </div>
                    </div>
                </div>
            `;
        });

        usuariosLista.html(html);
    }

    function mostrarPaginacion(paginaActual, totalPaginas, total) {
        if (totalPaginas <= 1) {
            paginacionContainer.hide();
            return;
        }

        var html = '<div class="paginacion-container">' +
            '<div class="total-documentos">Total: ' + total + ' personas</div>' +
            '<div class="paginacion-botones">';

        if (paginaActual > 1) {
            html += `<a href="#" class="page-link" data-page="1">« Primero</a>`;
            html += `<a href="#" class="page-link" data-page="${paginaActual - 1}">‹ Anterior</a>`;
        }

        var inicio = Math.max(1, paginaActual - 2);
        var fin = Math.min(totalPaginas, paginaActual + 2);

        if (inicio > 1) html += '<span class="page-dots">...</span>';
        for (var i = inicio; i <= fin; i++) {
            html += `<a href="#" class="page-link ${i === paginaActual ? 'active' : ''}" data-page="${i}">${i}</a>`;
        }
        if (fin < totalPaginas) html += '<span class="page-dots">...</span>';

        if (paginaActual < totalPaginas) {
            html += `<a href="#" class="page-link" data-page="${paginaActual + 1}">Siguiente ›</a>`;
            html += `<a href="#" class="page-link" data-page="${totalPaginas}">Último »</a>`;
        }

        html += '</div></div>';
        paginacionContainer.html(html).show();
    }

    // --------------------------------------------------
    // Modal de detalle
    // --------------------------------------------------
    function abrirModal(id) {
        modalOverlay.css('display', 'flex');
        $('body').css('overflow', 'hidden');
        modalBody.html('<div class="loading">Cargando información...</div>');

        $.ajax({
            url: usuarios_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'get_persona_detalle_salud',
                nonce: usuarios_ajax.nonce,
                id: id
            },
            success: function (response) {
                if (response.success) {
                    renderDetalle(response.data);
                } else {
                    modalBody.html('<div class="error">Error: ' + (response.data.message || response.data) + '</div>');
                }
            },
            error: function (xhr) {
                if (mostrarError401(xhr)) return;
                modalBody.html('<div class="error">Error al cargar el detalle (' + xhr.status + ')</div>');
            }
        });
    }

    function cerrarModal() {
        modalOverlay.css('display', 'none');
        $('body').css('overflow', '');
        modalBody.html('');
    }

    function renderDetalle(p) {
        modalTitulo.text(p.nombre_completo || 'Detalle');

        var html = '';

        // ----- Datos personales -----
        html += seccion('📋 Datos personales', [
            ['Tipo identificación', p.tipoidentificacion],
            ['Número documento', p.numerodocumento],
            ['Nombres', (p.primernombre || '') + ' ' + (p.segundonombre || '')],
            ['Apellidos', (p.primerapellido || '') + ' ' + (p.segundoapellido || '')],
            ['Fecha nacimiento', p.fecha_nacimiento_formateada],
            ['Fecha expedición', p.fechaexpedicion],
            ['Fecha vencimiento', p.fechavencimiento],
            ['Sexo', p.sexo],
            ['Tipo sangre', p.tipo_sangre],
            ['Estado civil', p.estadocivil],
            ['Escolaridad', p.escolaridad],
            ['Grupo étnico', p.grupoetnico],
            ['Grupo poblacional', p.grupopoblacional],
            ['Orientación sexual', p.orientacionsexual]
        ]);

        // ----- Contacto y ubicación -----
        html += seccion('📞 Contacto y ubicación', [
            ['Correo electrónico', p.correo],
            ['Correo alternativo', p.correo_alterno],
            ['Teléfono', p.telefono],
            ['Celular', p.celular],
            ['Dirección', p.direccion],
            ['Barrio', p.barrio],
            ['Estrato', p.estrato],
            ['Zona', p.zona],
            ['Municipio', p.municipio],
            ['Departamento', p.departamento]
        ]);

        // ----- Información laboral -----
        if (p.info_laboral) {
            var il = p.info_laboral;
            html += seccion('💼 Información laboral', [
                ['Cargo actual', il.cargoactual],
                ['Tipo de vinculación', il.tipovinculacion],
                ['Dedicación', il.dedicacion],
                ['Provisional', il.provisional],
                ['Dependencia', il.dependencia],
                ['Sede', il.sede],
                ['Extensión', il.extension],
                ['Teléfono laboral', il.telefono],
                ['Fecha ingreso UNICAUCA', il.fechaingresounicauca],
                ['Vencimiento contrato', il.fechavencimientocontrato],
                ['N° radicación', il.numeroradicacion],
                ['Resolución pensión', il.pension_resolucion],
                ['Fecha resolución pensión', il.pension_fecha]
            ]);
        }

        // ----- Nivel salarial -----
        if (p.nivel_salarial) {
            var ns = p.nivel_salarial;
            var rangoIni = (ns.rango_inicial !== null && ns.rango_inicial !== undefined)
                ? '$ ' + Number(ns.rango_inicial).toLocaleString('es-CO')
                : '';
            var rangoFin = (ns.rango_final !== null && ns.rango_final !== undefined)
                ? '$ ' + Number(ns.rango_final).toLocaleString('es-CO')
                : '';
            var rango = '';
            if (rangoIni && rangoFin) {
                rango = rangoIni + '  –  ' + rangoFin;
            } else if (rangoIni) {
                rango = rangoIni;
            }

            html += seccion('💰 Nivel salarial', [
                ['Descripción', ns.descripcion],
                ['Nivel', ns.nivel],
                ['Rango salarial', rango]
            ]);
        }

        // ----- Estados -----
        if (p.estados && p.estados.length > 0) {
            html += '<div class="modal-seccion">';
            html += '<h3>📌 Estados registrados</h3>';
            html += '<table class="modal-tabla"><thead><tr>' +
                '<th>Estado</th><th>Tipo afiliado</th><th>Inicio</th><th>Fin</th>' +
                '</tr></thead><tbody>';
            $.each(p.estados, function (i, e) {
                html += '<tr>' +
                    '<td>' + escapeHtml(e.estado_descripcion || e.tipo_estado || '—') + '</td>' +
                    '<td>' + escapeHtml(e.tipoafiliado || '—') + '</td>' +
                    '<td>' + escapeHtml(e.fecha_inicio_estado || '—') + '</td>' +
                    '<td>' + escapeHtml(e.fecha_fin || '—') + '</td>' +
                    '</tr>';
            });
            html += '</tbody></table></div>';
        }

        // ----- Discapacidades -----
        if (p.discapacidades && p.discapacidades.length > 0) {
            html += '<div class="modal-seccion">';
            html += '<h3>♿ Discapacidades</h3>';
            html += '<table class="modal-tabla"><thead><tr>' +
                '<th>Descripción</th><th>Grado</th><th>Fecha registro</th>' +
                '</tr></thead><tbody>';
            $.each(p.discapacidades, function (i, d) {
                html += '<tr>' +
                    '<td>' + escapeHtml(d.descripcion || '—') + '</td>' +
                    '<td>' + escapeHtml(d.grado || '—') + '</td>' +
                    '<td>' + escapeHtml(d.fecharegistro || '—') + '</td>' +
                    '</tr>';
            });
            html += '</tbody></table></div>';
        }

        // ----- Afiliaciones -----
        if (p.afiliaciones && p.afiliaciones.length > 0) {
            html += '<div class="modal-seccion">';
            html += '<h3>🏥 Afiliaciones</h3>';
            $.each(p.afiliaciones, function (i, a) {
                html += '<div class="modal-bloque">';
                html += '<div class="modal-bloque-titulo">Afiliación #' + escapeHtml(a.id) + '</div>';
                html += '<div class="modal-grid">';
                html += campo('Tipo afiliación', a.tipoafiliacion);
                html += campo('Radicación', a.fecharadicacion);
                html += campo('Ingreso SGSSS', a.fechaingresosgsss);
                html += campo('Finalización', a.fechafinalizacion);
                html += campo('IBC acumulado', a.ibcacumulado);
                html += campo('N° radicación', a.numeroradicacion);
                html += campo('Observación', a.observacion);
                html += campo('Nivel salarial', a.nivel_salarial_descripcion);
                html += '</div>';
                if (a.nombrecontactoemergencia || a.telefonocontactoemergencia || a.celularcontactoemergencia || a.direccioncontactoemergencia) {
                    html += '<div class="modal-subtitulo">Contacto de emergencia</div>';
                    html += '<div class="modal-grid">';
                    html += campo('Nombre', a.nombrecontactoemergencia);
                    html += campo('Teléfono', a.telefonocontactoemergencia);
                    html += campo('Celular', a.celularcontactoemergencia);
                    html += campo('Dirección', a.direccioncontactoemergencia);
                    html += '</div>';
                }
                html += '</div>';
            });
            html += '</div>';
        }

        // ----- Convenios -----
        if (p.convenios && p.convenios.length > 0) {
            html += '<div class="modal-seccion">';
            html += '<h3>📄 Convenios</h3>';
            html += '<table class="modal-tabla"><thead><tr>' +
                '<th>Tipo afiliado</th><th>Estado</th><th>Inicio</th><th>Fin</th>' +
                '</tr></thead><tbody>';
            $.each(p.convenios, function (i, c) {
                html += '<tr>' +
                    '<td>' + escapeHtml(c.tipo_afiliado || '—') + '</td>' +
                    '<td>' + escapeHtml(c.estado_convenio_descripcion || '—') + '</td>' +
                    '<td>' + escapeHtml(c.fecha_inicio_estado || '—') + '</td>' +
                    '<td>' + escapeHtml(c.fecha_fin_estado || '—') + '</td>' +
                    '</tr>';
            });
            html += '</tbody></table></div>';
        }

        // ----- Beneficiarios -----
        if (p.beneficiarios && p.beneficiarios.length > 0) {
            html += '<div class="modal-seccion">';
            html += '<h3>👨‍👩‍👧 Beneficiarios (' + p.beneficiarios.length + ')</h3>';
            html += '<table class="modal-tabla"><thead><tr>' +
                '<th>Nombre</th><th>Documento</th><th>Parentesco</th>' +
                '<th>F. Nacimiento</th><th>Sexo</th><th>Sangre</th>' +
                '<th>Radicación</th>' +
                '</tr></thead><tbody>';

            $.each(p.beneficiarios, function (i, b) {
                html += '<tr>' +
                    '<td>' + escapeHtml(b.nombre_completo || '—') + '</td>' +
                    '<td>' + escapeHtml((b.tipoidentificacion || '') + ' ' + (b.numerodocumento || '')) + '</td>' +
                    '<td>' + escapeHtml(b.parentescobeneficiario || '—') + '</td>' +
                    '<td>' + escapeHtml(b.fecha_nacimiento_formateada || b.fechanacimiento || '—') + '</td>' +
                    '<td>' + escapeHtml(b.sexo_texto || '—') + '</td>' +
                    '<td>' + escapeHtml(b.tipo_sangre || '—') + '</td>' +
                    '<td>' + escapeHtml(b.numeroradicacion || '—') + '</td>' +
                    '</tr>';
            });

            html += '</tbody></table></div>';
        }

        if (html === '') {
            html = '<div class="no-usuarios">Sin información adicional registrada</div>';
        }

        modalBody.html(html);
    }

    function seccion(titulo, campos) {
        var html = '<div class="modal-seccion">';
        html += '<h3>' + titulo + '</h3>';
        html += '<div class="modal-grid">';
        $.each(campos, function (i, c) {
            html += campo(c[0], c[1]);
        });
        html += '</div></div>';
        return html;
    }

    function campo(etiqueta, valor) {
        var v = (valor === null || valor === undefined || valor === '') ? '—' : valor;
        return '<div class="modal-campo">' +
            '<span class="modal-campo-label">' + escapeHtml(etiqueta) + '</span>' +
            '<span class="modal-campo-valor">' + escapeHtml(String(v)) + '</span>' +
            '</div>';
    }

    // --------------------------------------------------
    // Eventos
    // --------------------------------------------------
    btnLimpiar.on('click', function (e) {
        e.preventDefault();
        filtroNombre.val('');
        filtroEstado.val('');
        paginaActual = 1;
        cargarUsuarios();
    });

    filtroEstado.on('change', function () {
        paginaActual = 1;
        cargarUsuarios();
    });

    filtroNombre.on('keyup', function () {
        clearTimeout(timeoutBusqueda);
        timeoutBusqueda = setTimeout(function () {
            paginaActual = 1;
            cargarUsuarios();
        }, 500);
    });

    $(document).on('click', '.page-link', function (e) {
        e.preventDefault();
        var page = $(this).data('page');
        if (page && page !== paginaActual) {
            paginaActual = page;
            cargarUsuarios();
            $('html, body').animate({ scrollTop: $('#usuarios-lista').offset().top - 100 }, 300);
        }
    });

    $(document).on('click', '.btn-ver-mas', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var id = $(this).data('id');
        if (id) {
            abrirModal(id);
        }
    });

    $(document).on('click', '#modal-persona-close', function () {
        cerrarModal();
    });
    $(document).on('click', '#modal-persona', function (e) {
        if (e.target === this) {
            cerrarModal();
        }
    });
    $(document).on('keydown', function (e) {
        if (e.key === 'Escape' && modalOverlay.is(':visible')) {
            cerrarModal();
        }
    });

    // Cerrar sesión
    $(document).on('click', '#btn-cerrar-sesion', function (e) {
        e.preventDefault();
        if (!confirm('¿Cerrar sesión?')) return;

        $.ajax({
            url: usuarios_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'salud_cerrar_sesion',
                nonce: usuarios_ajax.nonce
            },
            complete: function () {
                window.location.reload();
            }
        });
    });

    // Manejo global de 401
    $(document).ajaxError(function (event, xhr) {
        if (xhr && xhr.status === 401) {
            window.location.reload();
        }
    });

    // --------------------------------------------------
    // Inicialización: pedir nonce FRESCO antes de cargar
    // Esto evita el 400 por caché de HTML con nonce viejo
    // --------------------------------------------------
    jQuery.post(
        usuarios_ajax.ajax_url,
        { action: 'salud_fresh_nonce' },
        function (resp) {
            if (resp && resp.success && resp.data && resp.data.nonce) {
                usuarios_ajax.nonce = resp.data.nonce;
            }
            cargarUsuarios();
        }
    ).fail(function () {
        cargarUsuarios();
    });
});