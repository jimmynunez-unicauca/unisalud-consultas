<?php
if (!defined('ABSPATH')) {
    exit;
}

$nombre_sesion = isset($usuario_salud['nombre']) ? $usuario_salud['nombre'] : '';
$email_sesion  = isset($usuario_salud['email'])  ? $usuario_salud['email']  : '';

// 🔑 Calcular segundos restantes reales desde Auth
$segundosRestantes = UsuariosSaludAuth::segundosRestantes();
?>

<script>
    // 🔑 Variable global que leerá script-usuarios.js
    var SEGUNDOS_RESTANTES = <?php echo (int) $segundosRestantes; ?>;
</script>

<!-- inicio formulario consultar prestador -->
<!-- ===== CONTADOR DE SESIÓN ===== -->
<div id="session-timer">
    <div class="timer-row">
        <span class="timer-icon">⏱️</span>
        <span class="timer-clock" id="timerClock">--:--:--</span>
    </div>
    <div class="timer-message">
        La sesión se cerrará automáticamente cuando finalice el tiempo.
    </div>
</div>

<div class="usuarios-consulta-afiliado-container">
    <div class="consulta-afiliado-card">        

        <!-- Paso 1: Buscar Identificacion -->
        <div id="consulta-afiliado-step1" >
            <div class="consulta-afiliado-header">
                <h2>Consulta de Afiliados a la Unidad de Salud</h2>
                <p>Ingresa el número de identificación correspondiente para poder hacer la consulta:</p>
            </div>

            <div class="consulta-afiliado-form-group">                
                <input type="number" class="consulta-afiliado-identificacion-input" id="campo-identificacion" max="9999999999">
                <div id="consulta-afiliado-correo-error" class="consulta-afiliado-identificacion-msg-error" hidden>Por favor ingresa una identificacion válido</div>
            </div>
            <button id="btn-consulta-afiliado" class="consulta-afiliado-btn-consultar">
                <span id="consulta-afiliado-btn-consultar-text">CONSULTAR</span>
                <span id="consulta-afiliado-btn-consultar-spinner" class="consulta-afiliado-spinner hidden"></span>
            </button>
        </div>                       
        
    </div>
</div>

<button id="btn-cerrar-sesion" class="consulta-afiliado-btn-salir">
    <span id="consulta-afiliado-btn-salir-text">SALIR</span>    
</button>
<!-- fin formulario consultar prestador -->


<!-- inicio tabla -->
<div class="usuarios-tabla-afiliado-container" hidden>
    <!-- ============================================ -->
    <!-- PÁRRAFO DE INSTRUCCIONES (arriba de la tabla) -->
    <!-- ============================================ -->
    <div class="instrucciones-consulta">
        <p>
            Si desea descargar el certificado de afiliación, seleccione de la siguiente lista el afiliado,
            luego el tipo de certificado y presione el botón de generar.
        </p>
        <a href="#" class="ver-instrucciones">Ver instrucciones</a>
    </div>
    <!-- ============================================ -->
    <!-- TABLA DE RESULTADOS                          -->
    <!-- ============================================ -->
    <div class="tabla-afiliados-wrapper">
        <h3>RESULTADOS DE LA CONSULTA</h3>
        <table class="tabla-afiliados">
            <thead>
                <tr>
                    <th>SELECCIONAR</th>
                    <th>TIPO DE AFILIACIÓN</th>
                    <th>IDENTIFICACIÓN</th>
                    <th>NOMBRE COMPLETO</th>
                    <th>EDAD</th>
                    <th>FECHA DE AFILIACIÓN</th>
                    <th>NIVEL SALARIAL</th>
                    <th>ESTADO DE AFILIACIÓN</th>
                    <th>PRESTADORA DE SALUD AFILIADO</th>
                </tr>
            </thead>
            <tbody id="tabla-afiliados-body">
                <!-- Se llena con JS -->
            </tbody>
        </table>
    </div>

    <!-- ============================================ -->
    <!-- ACCIONES DEBAJO DE LA TABLA                  -->
    <!-- ============================================ -->
    <div class="acciones-consulta">

        <!-- Fila 1: Tipo de certificado + Generar para + Descargar -->
        <div class="acciones-fila-1">

            <!-- Tipo de Certificado -->
            <div class="bloque-tipo-certificado">
                <h4>Tipo De Certificado</h4>
                <p class="subtitulo">Tipo de certificado que desea generar</p>
                <div class="opciones-certificado">
                    <label class="opcion-radio">
                        <input type="radio" name="tipo-certificado" value="individual">
                        <span>INDIVIDUAL</span>
                    </label>
                    <label class="opcion-radio">
                        <input type="radio" name="tipo-certificado" value="grupal">
                        <span>GRUPO FAMILIAR</span>
                    </label>
                </div>
            </div>

            <!-- El Certificado Se Genera Para -->
            <div class="bloque-genera-para">
                <h4>El Certificado Se Genera Para:</h4>
                <p class="subtitulo">[Opcional] Indique la razón por la que va a generar el certificado</p>
                <input type="text" class="input-razon" placeholder="A quien va dirigido el certificado">
            </div>

            <!-- Botón Descargar -->
            <div class="bloque-descargar">
                <button class="btn-descargar">DESCARGAR</button>
            </div>

        </div>

        <!-- Fila 2: Salir + Realizar nueva consulta -->
        <div class="acciones-fila-2">
            <button id="btn-cerrar-sesion2" class="consulta-afiliado-btn-salir2">
                <span id="consulta-afiliado-btn-salir-text">SALIR</span> 
            </button>
            <button class="btn-nueva-consulta" id="btn-realizar-nueva-consulta">REALIZAR UNA NUEVA CONSULTA</button>
        </div>

    </div>

</div>
<!-- fin tabla -->