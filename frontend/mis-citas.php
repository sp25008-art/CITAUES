
<?php
require_once "../auth/verificar_sesion.php";
verificarSesion();

$nombre = $_SESSION["nombres"] ?? "Usuario";
$apellido = $_SESSION["apellidos"] ?? "";
$rol = $_SESSION["rol"] ?? "ESTUDIANTE";
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CITAUES | Mis citas</title>
    <link rel="stylesheet" href="css/estilos.css">

    <style>
        [hidden] {
            display: none !important;
        }

        .acciones-cita {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .boton-cancelar,
        .boton-reprogramar {
            border: none;
            border-radius: 7px;
            padding: 9px 12px;
            cursor: pointer;
            font-weight: 600;
        }

        .boton-cancelar {
            background: #fee2e2;
            color: #b91c1c;
        }

        .boton-reprogramar {
            background: #dcfce7;
            color: #166534;
        }

        .boton-cancelar:disabled,
        .boton-reprogramar:disabled {
            opacity: .5;
            cursor: not-allowed;
        }

        .modal-reprogramar {
            position: fixed;
            inset: 0;
            z-index: 1000;
            background: rgba(0,0,0,.55);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }

        .modal-contenido {
            background: white;
            color: #222;
            width: 100%;
            max-width: 520px;
            max-height: 90vh;
            overflow-y: auto;
            border-radius: 14px;
            padding: 24px;
            box-shadow: 0 10px 35px rgba(0,0,0,.2);
        }

        .modal-contenido h2 {
            margin-top: 0;
        }

        .campo-reprogramar {
            margin: 16px 0;
        }

        .campo-reprogramar label {
            display: block;
            margin-bottom: 7px;
            font-weight: 600;
        }

        .campo-reprogramar input,
        .campo-reprogramar select {
            width: 100%;
            padding: 11px;
            border: 1px solid #ccc;
            border-radius: 7px;
        }

        .lista-horarios {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 9px;
            margin-top: 12px;
        }

        .horario-opcion {
            padding: 11px;
            border: 1px solid #ccc;
            border-radius: 8px;
            background: #f8fafc;
            cursor: pointer;
        }

        .horario-opcion.seleccionado {
            background: #dcfce7;
            border: 2px solid #16a34a;
        }

        .modal-acciones {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }

        .modal-acciones button {
            border: none;
            padding: 11px 16px;
            border-radius: 7px;
            cursor: pointer;
        }

        .boton-confirmar {
            background: #166534;
            color: white;
        }

        .boton-cerrar {
            background: #e5e7eb;
            color: #222;
        }

        #mensajeReprogramacion {
            margin-top: 12px;
            font-size: 14px;
        }
    </style>
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
            <a href="horarios.php">▦ Reservar cita</a>
            <a href="mis-citas.php" class="activo">◷ Mis citas</a>
            <a href="historial.php">▤ Historial</a>
        </nav>

        <div class="sidebar-footer">
            <a href="../auth/logout.php">↪ Cerrar sesión</a>
        </div>
    </aside>

    <main class="contenido-dashboard">

        <header class="topbar">
            <div>
                <h2>Mis citas</h2>
                <p>Universidad de El Salvador</p>
            </div>

            <div class="usuario">
                <div class="avatar">
                    <?php echo htmlspecialchars(substr($nombre, 0, 1)); ?>
                </div>
                <div>
                    <strong>
                        <?php echo htmlspecialchars($nombre . " " . $apellido); ?>
                    </strong>
                    <small><?php echo htmlspecialchars($rol); ?></small>
                </div>
            </div>
        </header>

        <section class="bienvenida">
            <div>
                <h1>Administración de citas</h1>
                <p>Consulta el estado de tus citas universitarias registradas.</p>
            </div>
            <div class="bienvenida-icono">📋</div>
        </section>

        <section class="mis-citas-contenedor">

            <div class="titulo-seccion">
                <div>
                    <h2>Mis reservaciones</h2>
                    <p>Consulta el estado de tus citas registradas.</p>
                </div>

                <a href="servicios.php" class="enlace-nueva-cita">
                    + Nueva cita
                </a>
            </div>

            <div id="mensajeCarga" class="sin-citas">
                Cargando tus reservaciones...
            </div>

            <div class="tabla-contenedor" id="contenedorTabla" hidden>
                <table class="tabla-citas">
                    <thead>
                        <tr>
                            <th>Servicio</th>
                            <th>Fecha</th>
                            <th>Horario</th>
                            <th>Profesional</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>

                    <tbody id="tablaMisCitas"></tbody>
                </table>
            </div>

            <div id="mensajeSinCitas" class="sin-citas" hidden>
                <span>📅</span>
                <h3>No tienes citas registradas</h3>
                <p>Cuando realices una reservación aparecerá aquí.</p>

                <a href="servicios.php" class="boton-reservar">
                    Reservar una cita
                </a>
            </div>

        </section>

        <footer class="dashboard-footer">
            CITAUES © 2026 | Universidad de El Salvador
        </footer>

    </main>

    <!-- Ventana de reprogramación -->
    <div class="modal-reprogramar" id="modalReprogramar" hidden>
        <div class="modal-contenido">

            <h2>Reprogramar cita</h2>
            <p id="nombreServicioReprogramar"></p>

            <input type="hidden" id="idCitaReprogramar">
            <input type="hidden" id="idServicioReprogramar">
            <input type="hidden" id="idDisponibilidadNueva">
            <input type="hidden" id="horaNueva">

            <div class="campo-reprogramar">
                <label for="fechaNueva">Selecciona una nueva fecha</label>
                <input type="date" id="fechaNueva">
            </div>

            <div class="campo-reprogramar">
                <label>Horarios disponibles</label>
                <div id="listaHorarios" class="lista-horarios">
                    Selecciona una fecha para consultar horarios.
                </div>
            </div>

            <div id="mensajeReprogramacion"></div>

            <div class="modal-acciones">
                <button type="button" class="boton-cerrar"
                        id="cerrarModal">
                    Cerrar
                </button>

                <button type="button" class="boton-confirmar"
                        id="confirmarReprogramacion" disabled>
                    Confirmar reprogramación
                </button>
            </div>

        </div>
    </div>

    <script src="js/mis-citas.js"></script>

</body>
</html>

