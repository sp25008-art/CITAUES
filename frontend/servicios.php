
<?php
require_once "../auth/verificar_sesion.php";
verificarSesion();

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
    <title>CITAUES | Servicios universitarios</title>
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
            <a href="dashboard.php">⌂ Inicio</a>
            <a href="servicios.php" class="activo">▣ Servicios universitarios</a>
            <a href="horarios.php">▦ Reservar cita</a>
            <a href="mis-citas.php">◷ Mis citas</a>
            <a href="historial.php">▤ Historial</a>
        </nav>

        <div class="sidebar-footer">
            <a href="../auth/logout.php">↪ Cerrar sesión</a>
        </div>

    </aside>

    <!-- CONTENIDO PRINCIPAL -->
    <main class="contenido-dashboard">

        <header class="topbar">
            <div>
                <h2>Servicios universitarios</h2>
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

        <!-- PRESENTACIÓN -->
        <section class="bienvenida">
            <div>
                <h1>Servicios disponibles</h1>
                <p>
                    Encuentra el servicio universitario que necesitas
                    y solicita tu cita fácilmente.
                </p>
            </div>

            <div class="bienvenida-icono">🎓</div>
        </section>

        <!-- BUSCADOR -->
        <section class="buscador-servicios">
            <input
                type="text"
                id="buscarServicio"
                placeholder="Buscar un servicio universitario..."
            >
        </section>

        <!-- CATÁLOGO -->
        <section class="seccion-servicios">

            <div class="titulo-seccion">
                <div>
                    <h2>Catálogo de servicios</h2>
                    <p>Selecciona una de las opciones disponibles.</p>
                </div>
            </div>

            <div class="servicios-grid catalogo-grid">
                <!-- Las tarjetas se generan desde la base de datos -->
            </div>

            <p id="sinResultados" class="sin-resultados">
                Cargando servicios...
            </p>

        </section>

        <footer class="dashboard-footer">
            CITAUES © 2026 | Universidad de El Salvador
        </footer>

    </main>

    <script src="js/servicios.js"></script>

</body>
</html>
