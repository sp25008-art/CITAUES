
<?php
require_once "../auth/verificar_sesion.php";
verificarSesion();

$nombres = $_SESSION["nombres"] ?? "";
$apellidos = $_SESSION["apellidos"] ?? "";
$rol = $_SESSION["rol"] ?? "";

$nombreCompleto = trim($nombres . " " . $apellidos);
$inicial = strtoupper(substr($nombres, 0, 1));

$idServicioInicial = filter_input(
    INPUT_GET,
    "id_servicio",
    FILTER_VALIDATE_INT
);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CITAUES | Reservar cita</title>
    <link rel="stylesheet" href="css/estilos.css">
</head>

<body class="dashboard-body">

    <aside class="sidebar">

        <div class="sidebar-logo">
            <div class="logo">C</div>
            <span>CITAUES</span>
        </div>

        <p class="menu-titulo">MENÚ PRINCIPAL</p>

        <nav class="menu">
            <a href="dashboard.php">⌂ Inicio</a>
            <a href="servicios.php">▣ Servicios universitarios</a>
            <a href="horarios.php" class="activo">▦ Reservar cita</a>
            <a href="mis-citas.php">◷ Mis citas</a>
            <a href="historial.php">▤ Historial</a>
        </nav>

        <div class="sidebar-footer">
            <a href="../auth/logout.php">↪ Cerrar sesión</a>
        </div>

    </aside>

    <main class="contenido-dashboard">

        <header class="topbar">
            <div>
                <h2>Reservar una cita</h2>
                <p>Universidad de El Salvador</p>
            </div>

            <div class="usuario">
                <div class="avatar">
                    <?= htmlspecialchars($inicial) ?>
                </div>

                <div>
                    <strong>
                        <?= htmlspecialchars($nombreCompleto) ?>
                    </strong>
                    <small>
                        <?= htmlspecialchars($rol) ?>
                    </small>
                </div>
            </div>
        </header>

        <section class="bienvenida">
            <div>
                <h1>Programa tu cita</h1>
                <p>
                    Selecciona el servicio, la fecha y el horario
                    que mejor se adapte a tus necesidades.
                </p>
            </div>

            <div class="bienvenida-icono">📅</div>
        </section>

        <section class="reserva-contenedor">

            <div class="reserva-titulo">
                <h2>Datos de la reservación</h2>
                <p>Completa los siguientes campos.</p>
            </div>

            <form id="formReserva">

                <div class="campo-reserva">
                    <label for="servicio">Servicio universitario</label>

                    <select id="servicio" required>
                        <option value="">Cargando servicios...</option>
                    </select>
                </div>

                <div class="campo-reserva">
                    <label for="fecha">Seleccionar fecha</label>

                    <input type="date" id="fecha" required>
                </div>

                <div class="campo-reserva">
                    <label for="motivo">Motivo de la cita</label>

                    <textarea
                        id="motivo"
                        rows="3"
                        maxlength="5000"
                        placeholder="Describe brevemente el motivo de tu cita..."
                        required
                    ></textarea>
                </div>

                <div class="seccion-horarios">
                    <h3>Horarios disponibles</h3>

                    <p>Selecciona un horario para continuar.</p>

                    <div id="listaHorarios" class="horarios-grid">
                        <p class="mensaje-horarios">
                            Primero selecciona un servicio y una fecha.
                        </p>
                    </div>
                </div>

                <input type="hidden" id="idDisponibilidad">
                <input type="hidden" id="horaSeleccionada">

                <div class="resumen-reserva">
                    <h3>Resumen de tu reservación</h3>

                    <p>
                        <strong>Servicio:</strong>
                        <span id="resumenServicio">No seleccionado</span>
                    </p>

                    <p>
                        <strong>Fecha:</strong>
                        <span id="resumenFecha">No seleccionada</span>
                    </p>

                    <p>
                        <strong>Horario:</strong>
                        <span id="resumenHora">No seleccionado</span>
                    </p>
                </div>

                <button type="submit" class="boton-confirmar" id="botonConfirmar">
                    Confirmar reservación
                </button>

            </form>

        </section>

        <footer class="dashboard-footer">
            CITAUES © 2026 | Universidad de El Salvador
        </footer>

    </main>

    <script>
        window.ID_SERVICIO_INICIAL = <?= json_encode($idServicioInicial ?: null) ?>;
    </script>

    <script src="js/reservas.js"></script>

</body>
</html>
