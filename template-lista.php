<?php
if (!defined('ABSPATH')) { exit; }

$nombre_sesion = isset($usuario_salud['nombre']) ? $usuario_salud['nombre'] : '';
?>
<div class="usuarios-salud-container">

    <!-- Contenedor del formulario de consulta (paso A) -->
    <div id="vista-consulta" class="ac-card-wrapper">
        <!-- Badge de sesión (arriba a la izquierda) -->
        <div id="sesion-timer" class="sesion-timer">
            <div class="sesion-timer-icon">🕐</div>
            <div class="sesion-timer-texto">
                <div class="sesion-timer-tiempo" id="sesion-timer-tiempo">01:59:43</div>
                <div class="sesion-timer-aviso">La sesión se cerrará automáticamente cuando finalice el tiempo.</div>
            </div>
        </div>

        <div class="ac-card">
            <h2 class="ac-card-titulo">Consulta de Afiliados a la<br>Unidad de Salud</h2>
            <p class="ac-card-subtitulo">
                Ingresa el número de identificación correspondiente&nbsp; para poder hacer la consulta:
            </p>
            <div class="ac-input-wrapper">
                <input type="text" id="input-identificacion" class="ac-input" placeholder=" " autocomplete="off" inputmode="numeric">
            </div>
            <button type="button" id="btn-consultar" class="ac-btn ac-btn-primary">CONSULTAR</button>
            <button type="button" id="btn-salir-consulta" class="ac-btn ac-btn-outline">SALIR</button>
        </div>
    </div>

    <!-- Contenedor de resultados (paso B) -->
    <div id="vista-resultados" class="ac-resultados-wrapper" style="display:none;">
        <!-- Timer de sesión -->
        <div id="sesion-timer-2" class="sesion-timer">
            <div class="sesion-timer-icon">🕐</div>
            <div class="sesion-timer-texto">
                <div class="sesion-timer-tiempo" id="sesion-timer-tiempo-2">01:59:43</div>
                <div class="sesion-timer-aviso">La sesión se cerrará automáticamente cuando finalice el tiempo.</div>
            </div>
        </div>

        <p class="ac-resultados-aviso">
            Si desea descargar el certificado de afiliación, seleccione de la siguiente lista el afiliado,
            luego el tipo de certificado y presione el botón de generar.
            <br>
            <a href="#" class="ac-link">Ver instrucciones</a>
        </p>

        <!-- Tabla de resultados -->
        <div class="ac-tabla-wrapper">
            <div class="ac-tabla-titulo">RESULTADOS DE LA CONSULTA</div>
            <table class="ac-tabla">
                <thead>
                    <tr>
                        <th>SELECCIONAR</th>
                        <th>TIPO DE AFILIACIÓN</th>
                        <th>IDENTIFICACIÓN</th>
                        <th>NOMBRE COMPLETO</th>
                        <th>EDAD</th>
                        <th>FECHA DE<br>AFILIACIÓN</th>
                        <th>NIVEL<br>SALARIAL</th>
                        <th>ESTADO DE AFILIACIÓN</th>
                        <th>PRESTADORA DE<br>SALUD AFILIADO</th>
                    </tr>
                </thead>
                <tbody id="ac-tabla-body">
                    <!-- Se llena dinámicamente -->
                </tbody>
            </table>
        </div>

        <!-- Sección de certificado -->
        <div class="ac-certificado-wrapper">
            <div class="ac-certificado-col">
                <h3 class="ac-certificado-titulo">Tipo De Certificado</h3>
                <p class="ac-certificado-subtitulo">Tipo de certificado que desea generar</p>
                <div class="ac-certificado-opciones">
                    <label class="ac-radio-pill">
                        <input type="radio" name="tipo_certificado" value="INDIVIDUAL">
                        <span class="ac-radio-circulo"></span>
                        <span class="ac-radio-texto">INDIVIDUAL</span>
                    </label>
                    <label class="ac-radio-pill">
                        <input type="radio" name="tipo_certificado" value="GRUPO FAMILIAR">
                        <span class="ac-radio-circulo"></span>
                        <span class="ac-radio-texto">GRUPO FAMILIAR</span>
                    </label>
                </div>
            </div>

            <div class="ac-certificado-col">
                <h3 class="ac-certificado-titulo">El Certificado Se Genera Para:</h3>
                <p class="ac-certificado-subtitulo">[Opcional] Indique la razón por la que va a generar el certificado</p>
                <div class="ac-certificado-genera">
                    <input type="text" id="certificado-genera" class="ac-input ac-input-genera" placeholder="A quien va dirigido el certificado">
                    <button type="button" id="btn-descargar" class="ac-btn ac-btn-primary ac-btn-descargar" disabled>DESCARGAR</button>
                </div>
            </div>
        </div>

        <!-- Botones inferiores -->
        <div class="ac-botones-inferiores">
            <button type="button" id="btn-salir-resultados" class="ac-btn ac-btn-outline">SALIR</button>
            <button type="button" id="btn-nueva-consulta" class="ac-btn ac-btn-primary">REALIZAR UNA NUEVA CONSULTA</button>
        </div>
    </div>

</div>