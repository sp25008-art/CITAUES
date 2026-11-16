
<?php
require_once __DIR__ . "/../auth/permisos.php";

verificarSesion();

$rol = $_SESSION["rol"];
$nombre = $_SESSION["nombres"] ?? "Usuario";
$apellido = $_SESSION["apellidos"] ?? "";

if ($rol === "ESTUDIANTE") {
    exigirPermiso("citas.consultar_propias");
} elseif ($rol === "PROFESIONAL") {
    exigirPermiso("citas.consultar_asignadas");
} elseif ($rol === "COORDINADOR") {
    exigirPermiso("citas.consultar_unidad");
} elseif ($rol !== "ADMINISTRADOR") {
    http_response_code(403);
    exit("No tienes permiso para acceder al historial.");
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CITAUES | Historial</title>
    <link rel="stylesheet" href="css/estilos.css">

    <style>
        .filtros-historial {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 20px 0;
            flex-wrap: wrap;
        }

        .filtros-historial select {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 8px;
        }

        .detalle-cambio {
            font-size: 13px;
            color: #64748b;
        }

        .tipo-evento {
            font-weight: bold;
        }

        .estado-evento {
            font-weight: 600;
        }

        #mensajeCargaHistorial {
            padding: 18px;
        }

        [hidden] {
            display: none !important;
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

        <?php if ($rol === "ESTUDIANTE"): ?>
            <a href="horarios.php">▦ Reservar cita</a>
            <a href="mis-citas.php">◷ Mis citas</a>
        <?php endif; ?>

        <?php if (in_array($rol, ["PROFESIONAL", "COORDINADOR", "ADMINISTRADOR"], true)): ?>
            <a href="citas-asignadas.php">▤ Citas asignadas</a>
        <?php endif; ?>

        <a href="historial.php" class="activo">▤ Historial</a>
        <a href="recordatorios.php"> ◷ Recordatorios</a>  
    </nav>

    <div class="sidebar-footer">
        <a href="../auth/logout.php">↪ Cerrar sesión</a>
    </div>

</aside>

<main class="contenido-dashboard">

    <header class="topbar">
        <div>
            <h2>Historial de movimientos</h2>
            <p>Universidad de El Salvador</p>
        </div>

        <div class="usuario">
            <div class="avatar">
                <?= htmlspecialchars(substr($nombre, 0, 1)) ?>
            </div>
            <div>
                <strong><?= htmlspecialchars($nombre . " " . $apellido) ?></strong>
                <small><?= htmlspecialchars($rol) ?></small>
            </div>
        </div>
    </header>

    <section class="bienvenida">
        <div>
            <h1>Historial de citas</h1>
            <p>
                Consulta las solicitudes y los cambios realizados
                en las reservaciones de servicios universitarios.
            </p>
        </div>
        <div class="bienvenida-icono">📚</div>
    </section>

    <section class="resumen">

        <div class="tarjeta-resumen">
            <div class="icono-tarjeta azul">📋</div>
            <div>
                <p>Total de movimientos</p>
                <h2 id="totalMovimientos">0</h2>
            </div>
        </div>

        <div class="tarjeta-resumen">
            <div class="icono-tarjeta verde">✓</div>
            <div>
                <p>Confirmaciones</p>
                <h2 id="totalConfirmaciones">0</h2>
            </div>
        </div>

        <div class="tarjeta-resumen">
            <div class="icono-tarjeta naranja">◷</div>
            <div>
                <p>Cancelaciones</p>
                <h2 id="totalCancelaciones">0</h2>
            </div>
        </div>

    </section>

    <section class="historial-contenedor">

        <div class="titulo-seccion">
            <div>
                <h2>Registro de actividad</h2>
                <p>Detalle de las operaciones realizadas en las citas.</p>
            </div>

            <button type="button" class="enlace-nueva-cita" id="actualizarHistorial">
                ↻ Actualizar
            </button>
        </div>

        <div class="filtros-historial">
            <label for="filtroTipo">Filtrar por movimiento:</label>

            <select id="filtroTipo">
                <option value="TODOS">Todos</option>
                <option value="SOLICITUD">Solicitudes</option>
                <option value="CONFIRMACION">Confirmaciones</option>
                <option value="REPROGRAMACION">Reprogramaciones</option>
                <option value="CANCELACION">Cancelaciones</option>
                <option value="ASISTENCIA">Asistencias</option>
            </select>
        </div>

        <div id="mensajeCargaHistorial">
            Cargando historial...
        </div>

        <div class="tabla-contenedor" id="contenedorHistorial" hidden>
            <table class="tabla-citas">
                <thead>
                    <tr>
                        <th>Cita</th>
                        <th>Servicio</th>
                        <th>Movimiento</th>
                        <th>Estado anterior</th>
                        <th>Estado nuevo</th>
                        <th>Detalle</th>
                        <th>Realizado por</th>
                        <th>Fecha del cambio</th>
                    </tr>
                </thead>

                <tbody id="tablaHistorial"></tbody>
            </table>
        </div>

        <div id="historialVacio" class="sin-citas" hidden>
            <span>📋</span>
            <h3>No hay movimientos registrados</h3>
            <p>Cuando se realicen operaciones aparecerán aquí.</p>
        </div>

    </section>

    <footer class="dashboard-footer">
        CITAUES © 2026 | Universidad de El Salvador
    </footer>

</main>

<script src="js/historial.js"></script>

</body>
</html>
