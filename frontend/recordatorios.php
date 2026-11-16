
<?php
require_once __DIR__ . "/../auth/permisos.php";

verificarSesion();

$rol = $_SESSION["rol"];
$nombre = $_SESSION["nombres"] ?? "Usuario";
$apellido = $_SESSION["apellidos"] ?? "";

if (!in_array($rol, ["ESTUDIANTE", "PROFESIONAL", "COORDINADOR", "ADMINISTRADOR"], true)) {
    http_response_code(403);
    exit("No tienes permiso para acceder a esta sección.");
}

if ($rol === "ESTUDIANTE") {
    exigirPermiso("citas.consultar_propias");
} elseif ($rol === "PROFESIONAL") {
    exigirPermiso("citas.consultar_asignadas");
} elseif ($rol === "COORDINADOR") {
    exigirPermiso("citas.consultar_unidad");
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CITAUES | Recordatorios</title>
    <link rel="stylesheet" href="css/estilos.css">

    <style>
        [hidden] {
            display: none !important;
        }

        .form-recordatorio {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: end;
            margin: 20px 0;
        }

        .campo-recordatorio {
            display: flex;
            flex-direction: column;
            gap: 7px;
            min-width: 190px;
        }

        .campo-recordatorio input,
        .campo-recordatorio select {
            padding: 11px;
            border: 1px solid #ddd;
            border-radius: 8px;
        }

        .boton-programar {
            background: #166534;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 12px 18px;
            cursor: pointer;
            font-weight: 600;
        }

        .estado-recordatorio {
            font-weight: bold;
        }

        .estado-PENDIENTE {
            color: #b45309;
        }

        .estado-ENVIADO {
            color: #166534;
        }

        .estado-ERROR {
            color: #dc2626;
        }

        .mensaje-recordatorio {
            padding: 15px;
            margin: 12px 0;
        }

        .acciones-recordatorio {
            display: flex;
            gap: 7px;
            align-items: center;
            min-width: 190px;
        }

        .acciones-recordatorio select {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 6px;
        }

        .boton-guardar-estado {
            background: #2563eb;
            color: white;
            border: none;
            border-radius: 6px;
            padding: 9px 12px;
            cursor: pointer;
        }

        .boton-guardar-estado:hover {
            background: #1d4ed8;
        }
    </style>
</head>

<body class="dashboard-body" data-rol="<?= htmlspecialchars($rol, ENT_QUOTES, 'UTF-8') ?>">

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

        <a href="historial.php">▤ Historial</a>
        <a href="recordatorios.php" class="activo">◷ Recordatorios</a>
    </nav>

    <div class="sidebar-footer">
        <a href="../auth/logout.php">↪ Cerrar sesión</a>
    </div>

</aside>

<main class="contenido-dashboard">

    <header class="topbar">
        <div>
            <h2>Recordatorios</h2>
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
            <h1>Gestión de recordatorios</h1>
            <p>
                Programa y consulta avisos relacionados con las
                reservaciones de servicios universitarios.
            </p>
        </div>
        <div class="bienvenida-icono">🔔</div>
    </section>

    <section class="mis-citas-contenedor">

        <div class="titulo-seccion">
            <div>
                <h2>Programar recordatorio</h2>
                <p>Selecciona la cita, la fecha y el medio del aviso.</p>
            </div>
        </div>

        <form id="formRecordatorio" class="form-recordatorio">

            <div class="campo-recordatorio">
                <label for="idCita">Seleccionar cita</label>
                <select id="idCita" required>
                    <option value="">Cargando citas...</option>
                </select>
            </div>

            <div class="campo-recordatorio">
                <label for="fechaProgramada">Fecha y hora</label>
                <input
                    type="datetime-local"
                    id="fechaProgramada"
                    required>
            </div>

            <div class="campo-recordatorio">
                <label for="medioRecordatorio">Medio</label>
                <select id="medioRecordatorio" required>
                    <option value="SISTEMA">Sistema</option>
                    <option value="CORREO">Correo electrónico</option>
                </select>
            </div>

            <button type="submit" class="boton-programar">
                + Programar
            </button>

        </form>

        <div id="mensajeRecordatorio" class="mensaje-recordatorio" hidden></div>

    </section>

    <section class="mis-citas-contenedor">

        <div class="titulo-seccion">
            <div>
                <h2>Recordatorios registrados</h2>
                <p>Consulta los avisos programados y su estado.</p>
            </div>

            <button type="button" class="enlace-nueva-cita" id="actualizarRecordatorios">
                ↻ Actualizar
            </button>
        </div>

        <div id="cargandoRecordatorios" class="mensaje-recordatorio">
            Cargando recordatorios...
        </div>

        <div class="tabla-contenedor" id="contenedorRecordatorios" hidden>
            <table class="tabla-citas">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Cita</th>
                        <th>Estudiante</th>
                        <th>Servicio</th>
                        <th>Fecha de cita</th>
                        <th>Fecha programada</th>
                        <th>Medio</th>
                        <th>Estado</th>
                        <th>Fecha de envío</th>

                        <?php if ($rol === "ADMINISTRADOR"): ?>
                            <th>Acciones</th>
                        <?php endif; ?>
                    </tr>
                </thead>

                <tbody id="tablaRecordatorios"></tbody>
            </table>
        </div>

        <div id="sinRecordatorios" class="sin-citas" hidden>
            <span>🔔</span>
            <h3>No hay recordatorios registrados</h3>
            <p>Cuando programes un recordatorio aparecerá aquí.</p>
        </div>

    </section>

    <footer class="dashboard-footer">
        CITAUES © 2026 | Universidad de El Salvador
    </footer>

</main>

<script src="js/recordatorios.js"></script>

</body>
</html>
