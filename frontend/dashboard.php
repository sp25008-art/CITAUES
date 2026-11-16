
<?php
require_once "../auth/verificar_sesion.php";

// Verificar que el usuario haya iniciado sesión
verificarSesion();

// Obtener datos del usuario autenticado
$nombres = $_SESSION["nombres"] ?? "";
$apellidos = $_SESSION["apellidos"] ?? "";
$rol = $_SESSION["rol"] ?? "";

$nombreCompleto = trim($nombres . " " . $apellidos);
$inicial = strtoupper(substr($nombres, 0, 1));
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CITAUES | Panel principal</title>
    <link rel="stylesheet" href="css/estilos.css">
</head>

<body class="dashboard-body">

    <!-- MENÚ LATERAL -->
    <aside class="sidebar">

        <div class="sidebar-logo">
            <div class="logo">C</div>
            <span>CITAUES</span>
        </div>

        <p class="menu-titulo">MENÚ PRINCIPAL</p>

        <nav class="menu">
            <a href="dashboard.php" class="activo">⌂ Inicio</a>
            <a href="servicios.php">▣ Servicios universitarios</a>

            <?php if ($rol === "ESTUDIANTE"): ?>
                <a href="horarios.php">▦ Reservar cita</a>
                <a href="mis-citas.php">◷ Mis citas</a>
            <?php endif; ?>

            <?php if (in_array($rol, ["PROFESIONAL", "COORDINADOR", "ADMINISTRADOR"], true)): ?>
                <a href="citas_asignadas.php">▤ Citas asignadas</a>
            <?php endif; ?>

            <a href="historial.php">▤ Historial</a>

             <a href="recordatorios.php">◷ Recordatorios</a>
        </nav>

        <div class="sidebar-footer">
            <a href="../auth/logout.php">↪ Cerrar sesión</a>
        </div>

    </aside>

    <!-- CONTENIDO PRINCIPAL -->
    <main class="contenido-dashboard">

        <header class="topbar">

            <div>
                <h2>Panel principal</h2>
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

        <!-- BIENVENIDA -->
        <section class="bienvenida">

            <div>
                <h1>
                    ¡Bienvenido a CITAUES,
                    <?= htmlspecialchars($nombres) ?>!
                </h1>

                <p>
                    Gestiona tus citas universitarias
                    de manera rápida y organizada.
                </p>
            </div>

            <div class="bienvenida-icono">📅</div>

        </section>

        <!-- RESUMEN -->
        <section class="resumen">

            <div class="tarjeta-resumen">
                <div class="icono-tarjeta azul">📅</div>

                <div>
                    <p>Citas programadas</p>
                    <h2>0</h2>
                </div>
            </div>

            <div class="tarjeta-resumen">
                <div class="icono-tarjeta verde">✓</div>

                <div>
                    <p>Citas completadas</p>
                    <h2>0</h2>
                </div>
            </div>

            <div class="tarjeta-resumen">
                <div class="icono-tarjeta naranja">◷</div>

                <div>
                    <p>Citas pendientes</p>
                    <h2>0</h2>
                </div>
            </div>

        </section>

        <!-- SERVICIOS -->
        <section class="seccion-servicios">

            <div class="titulo-seccion">

                <div>
                    <h2>Servicios universitarios</h2>
                    <p>Selecciona el servicio que necesitas.</p>
                </div>

                <a href="servicios.php">Ver todos</a>

            </div>

            <div class="servicios-grid">

                <article class="servicio-card">
                    <div class="servicio-icono">🏥</div>
                    <h3>Clínica universitaria</h3>
                    <p>Atención y orientación de salud.</p>
                    <a href="servicios.php">Consultar servicio →</a>
                </article>

                <article class="servicio-card">
                    <div class="servicio-icono">📚</div>
                    <h3>Asesoría académica</h3>
                    <p>Orientación y apoyo académico.</p>
                    <a href="servicios.php">Consultar servicio →</a>
                </article>

                <article class="servicio-card">
                    <div class="servicio-icono">🎓</div>
                    <h3>Bienestar universitario</h3>
                    <p>Servicios de apoyo estudiantil.</p>
                    <a href="servicios.php">Consultar servicio →</a>
                </article>

            </div>

        </section>

        <!-- PRÓXIMAS CITAS -->
        <section class="citas-recientes">

            <h2>Mis próximas citas</h2>

            <div class="sin-citas">

                <span>📅</span>

                <h3>No tienes citas programadas</h3>

                <p>
                    Cuando reserves una cita,
                    aparecerá aquí.
                </p>

                <a href="horarios.php" class="boton-reservar">
                    Reservar una cita
                </a>

            </div>

        </section>

        <!-- PIE DE PÁGINA -->
        <footer class="dashboard-footer">
            CITAUES © 2026 | Universidad de El Salvador
        </footer>

    </main>

</body>
</html>
