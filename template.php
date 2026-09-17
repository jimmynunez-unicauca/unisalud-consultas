<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="usuarios-salud-container">
    <div class="usuarios-filtros">
        <div class="filtros-row">
            <div class="filtro-item">
                <label>Buscar por nombres, apellidos o documento</label>
                <input type="text" id="filtro-nombre" class="filtro-input" placeholder="Escriba el texto a buscar...">
            </div>
            <div class="filtro-item">
                <label>Sexo</label>
                <select id="filtro-estado" class="filtro-select">
                    <option value="">Todos</option>
                    <option value="Masculino">Masculino</option>
                    <option value="Femenino">Femenino</option>
                </select>
            </div>
            <div class="filtro-item filtro-botones">
                <button id="btn-limpiar" class="btn">LIMPIAR</button>
            </div>
        </div>
    </div>

    <div id="resultados-container">
        <div id="usuarios-lista" class="usuarios-lista-principal">
            <div class="loading">Cargando personas...</div>
        </div>
    </div>

    <div id="paginacion-container" class="paginacion" style="display: none;"></div>
    <!-- Modal de detalle -->
    <div id="modal-persona" class="modal-persona-overlay" style="display:none;">
        <div class="modal-persona-content" role="dialog" aria-modal="true">
            <div class="modal-persona-header">
                <h2 id="modal-persona-titulo">Detalle de la persona</h2>
                <button type="button" class="modal-persona-close" id="modal-persona-close" aria-label="Cerrar">&times;</button>
            </div>
            <div class="modal-persona-body" id="modal-persona-body">
                <div class="loading">Cargando información...</div>
            </div>
        </div>
    </div>
</div>