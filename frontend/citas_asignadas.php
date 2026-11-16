
<?php
require_once "../auth/permisos.php";
verificarSesion();

$rol = $_SESSION["rol"] ?? "";

if (!in_array($rol, ["PROFESIONAL", "COORDINADOR", "ADMINISTRADOR"], true)) {
    http_response_code(403);
    exit("No tienes permiso para acceder a esta sección.");
}

if ($rol === "PROFESIONAL") {
    exigirPermiso("citas.consultar_asignadas");
}

if ($rol === "COORDINADOR") {
    exigirPermiso("citas.consultar_unidad");
}

$nombre = $_SESSION["nombres"] ?? "Usuario";
$apellido = $_SESSION["apellidos"] ?? "";
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CITAUES | Citas asignadas</title>
    <link rel="stylesheet" href="css/estilos.css">

    <style>
        [hidden] {
            display: none !important;
        }

        .filtros-citas {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin: 20px 0;
        }

        .filtro-cita {
            border: 1px solid #ddd;
            padding: 10px 16px;
            border-radius: 8px;
            background: white;
            cursor: pointer;
        }

        .filtro-cita.activo {
            background: #166534;
            color: white;
        }

        .boton-confirmar-cita {
            background: #dcfce7;
            color: #166534;
            border: none;
            padding: 9px 12px;
            border-radius: 7px;
            cursor: pointer;
            font-weight: 600;
        }

        .boton-confirmar-cita:disabled {
            opacity: .5;
            cursor: not-allowed;
        }

        .estado-confirmada {
            color: #166534;
            font-weight: bold;
        }

        .estado-pendiente {
            color: #b45309;
            font-weight: bold;
        }

        .mensaje-citas {
            padding: 15px;
            margin: 15px 0;
        }
        .boton-asistencia {
    border: none;
    padding: 8px 10px;
    border-radius: 7px;
    cursor: pointer;
    font-weight: 600;
    margin: 3px;
}

.boton-asistencia.si {
    background: #dcfce7;
    color: #166534;
}

.boton-asistencia.no {
    background: #fee2e2;
    color: #991b1b;
}

.boton-asistencia:disabled {
    opacity: .5;
    cursor: not-allowed;
}

.estado-finalizada {
    color: #2563eb;
    font-weight: bold;
}
    </style>
</head>

<body class="dashboard-body" data-rol="<?= htmlspecialchars($rol) ?>">

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

            <a href="citas_asignadas.php" class="activo">
                ▤ Citas asignadas
            </a>

            <a href="historial.php">▤ Historial</a>
        </nav>

        <div class="sidebar-footer">
            <a href="../auth/logout.php">↪ Cerrar sesión</a>
        </div>

    </aside>

    <main class="contenido-dashboard">

        <header class="topbar">
            <div>
                <h2>Citas asignadas</h2>
                <p>Universidad de El Salvador</p>
            </div>

            <div class="usuario">
                <div class="avatar">
                    <?= htmlspecialchars(substr($nombre, 0, 1)) ?>
                </div>

                <div>
                    <strong>
                        <?= htmlspecialchars($nombre . " " . $apellido) ?>
                    </strong>
                    <small><?= htmlspecialchars($rol) ?></small>
                </div>
            </div>
        </header>

        <section class="bienvenida">
            <div>
                <h1>Gestión de citas</h1>
                <p>
                    Consulta y administra las solicitudes de citas
                    correspondientes a tu ámbito de responsabilidad.
                </p>
            </div>

            <div class="bienvenida-icono">📋</div>
        </section>

        <section class="mis-citas-contenedor">

            <div class="titulo-seccion">
                <div>
                    <h2>Reservaciones recibidas</h2>
                    <p>Consulta y confirma las solicitudes pendientes.</p>
                </div>

                <button type="button" class="enlace-nueva-cita"
                        id="actualizarCitas">
                    ↻ Actualizar
                </button>
            </div>

            <div class="filtros-citas">
                <button class="filtro-cita activo" data-filtro="TODAS">
                    Todas
                </button>

                <button class="filtro-cita" data-filtro="PENDIENTE">
                    Pendientes
                </button>

                <button class="filtro-cita" data-filtro="CONFIRMADA">
                    Confirmadas
                </button>

                <button class="filtro-cita" data-filtro="FINALIZADA">
                    Finalizadas
                </button>

                <button class="filtro-cita" data-filtro="CANCELADA">
                    Canceladas
                </button>
            </div>

            <div id="mensajeCarga" class="mensaje-citas">
                Cargando citas...
            </div>

            <div class="tabla-contenedor" id="contenedorTabla" hidden>
                <table class="tabla-citas">
                    <thead>
                        <tr>
                            <th>Estudiante</th>
                            <th>Servicio</th>
                            <th>Fecha</th>
                            <th>Horario</th>
                            <th>Estado</th>
                            <th>Motivo</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>

                    <tbody id="tablaCitasAsignadas"></tbody>
                </table>
            </div>

            <div id="mensajeSinCitas" class="sin-citas" hidden>
                <span>📅</span>
                <h3>No hay citas registradas</h3>
                <p>Las solicitudes aparecerán aquí cuando se registren.</p>
            </div>

        </section>

        <footer class="dashboard-footer">
            CITAUES © 2026 | Universidad de El Salvador
        </footer>

    </main>

    <script src="js/citas_asignadas.js"></script>

</body>
</html>
