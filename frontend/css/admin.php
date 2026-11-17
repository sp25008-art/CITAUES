
<?php
require_once "../auth/verificar_sesion.php";
require_once "../config/conexion.php";

// Verificar sesión y permisos de administrador
verificarSesion();

if (($_SESSION["rol"] ?? "") !== "ADMINISTRADOR") {
    http_response_code(403);
    die("Acceso denegado. Esta sección es exclusiva del administrador.");
}

// Obtener nombre del administrador
$nombres = $_SESSION["nombres"] ?? "Administrador";
$apellidos = $_SESSION["apellidos"] ?? "";
$nombreCompleto = trim($nombres . " " . $apellidos);

// Consultar estadísticas de citas
$sqlResumen = "SELECT
    COUNT(*) AS total,
    SUM(estado = 'PENDIENTE') AS pendientes,
    SUM(estado = 'CONFIRMADA') AS confirmadas,
    SUM(estado = 'CANCELADA') AS canceladas,
    SUM(estado = 'FINALIZADA') AS finalizadas
    FROM citas";

$resultadoResumen = $conexion->query($sqlResumen);

if (!$resultadoResumen) {
    die("Error al consultar las estadísticas: " . $conexion->error);
}

$resumen = $resultadoResumen->fetch_assoc();

// Consultar todas las citas registradas

$sqlCitas = "SELECT
    c.id_cita,
    c.id_estudiante,
    c.id_disponibilidad,
    c.estado,
    c.motivo,
    c.hora_inicio,
    c.hora_fin,

    d.fecha,

    u.nombres,
    u.apellidos,

    s.id_servicio,
    s.nombre AS servicio,

    ps.id_usuario AS id_profesional,

    CONCAT(pro.nombres, ' ', pro.apellidos) AS profesional

    FROM CITAS c

    LEFT JOIN DISPONIBILIDADES d
        ON c.id_disponibilidad = d.id_disponibilidad

    LEFT JOIN USUARIOS u
        ON c.id_estudiante = u.id_usuario

    LEFT JOIN PROFESIONAL_SERVICIO ps
        ON d.id_profesional_servicio = ps.id_profesional_servicio

    LEFT JOIN SERVICIOS s
        ON ps.id_servicio = s.id_servicio

    LEFT JOIN USUARIOS pro
        ON ps.id_usuario = pro.id_usuario

    ORDER BY c.id_cita DESC";


$resultadoCitas = $conexion->query($sqlCitas);

if (!$resultadoCitas) {
    die("Error al consultar las citas: " . $conexion->error);
}

function escapar($valor) {
    return htmlspecialchars((string)($valor ?? ""), ENT_QUOTES, "UTF-8");
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CITAUES | Panel administrativo</title>

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
        }

        body {
            background: #f1f5fb;
            color: #123b61;
        }

        .sidebar {
            width: 240px;
            min-height: 100vh;
            background: #103e66;
            color: white;
            position: fixed;
            left: 0;
            top: 0;
            padding: 25px 18px;
        }

        .logo {
            font-size: 25px;
            font-weight: bold;
            margin-bottom: 45px;
        }

        .menu-titulo {
            font-size: 11px;
            color: #a8c5df;
            margin-bottom: 15px;
        }

        .menu a {
            display: block;
            color: white;
            text-decoration: none;
            padding: 13px;
            margin-bottom: 8px;
            border-radius: 7px;
            font-size: 14px;
        }

        .menu a.activo,
        .menu a:hover {
            background: #3185e8;
        }

        .cerrar {
            position: absolute;
            bottom: 25px;
            left: 18px;
            color: white;
            text-decoration: none;
        }

        .contenido {
            margin-left: 240px;
            padding: 30px;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            gap: 15px;
        }

        .topbar h1 {
            font-size: 25px;
        }

        .topbar p {
            color: #71849b;
            margin-top: 5px;
        }

        .usuario {
            font-weight: bold;
            text-align: right;
        }

        .bienvenida {
            background: #103e66;
            color: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 25px;
        }

        .bienvenida h2 {
            margin-bottom: 10px;
        }

        .bienvenida p {
            color: #dceafa;
        }

        .estadisticas {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 15px;
            margin-bottom: 30px;
        }

        .tarjeta {
            background: white;
            padding: 22px 18px;
            border-radius: 10px;
            box-shadow: 0 3px 12px #173c6010;
        }

        .tarjeta p {
            color: #71849b;
            font-size: 13px;
            margin-bottom: 10px;
        }

        .tarjeta h2 {
            font-size: 27px;
        }

        .tabla-contenedor {
            background: white;
            padding: 22px;
            border-radius: 12px;
            overflow-x: auto;
        }

        .tabla-contenedor h2 {
            margin-bottom: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 850px;
        }

        th {
            background: #103e66;
            color: white;
            padding: 13px;
            text-align: left;
            font-size: 13px;
        }

        td {
            padding: 13px;
            border-bottom: 1px solid #e7edf4;
            font-size: 13px;
        }

        tr:hover {
            background: #f5f8fc;
        }

        .estado {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
        }

        .PENDIENTE {
            background: #fff0d9;
            color: #b66a00;
        }

        .CONFIRMADA {
            background: #e0efff;
            color: #1764b0;
        }

        .CANCELADA {
            background: #ffe3e3;
            color: #c62828;
        }

        .FINALIZADA {
            background: #dcf5e9;
            color: #16834c;
        }

        .vacio {
            text-align: center;
            padding: 30px;
            color: #71849b;
        }

        @media (max-width: 1100px) {
            .estadisticas {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 700px) {
            .sidebar {
                width: 100%;
                min-height: auto;
                position: relative;
            }

            .cerrar {
                position: static;
                display: block;
                margin-top: 20px;
            }

            .contenido {
                margin-left: 0;
                padding: 18px;
            }

            .estadisticas {
                grid-template-columns: 1fr;
            }

            .topbar {
                align-items: flex-start;
                flex-direction: column;
            }
        }
    </style>
</head>

<body>

<aside class="sidebar">

    <div class="logo">🟢 CITAUES</div>

    <p class="menu-titulo">MENÚ ADMINISTRATIVO</p>

    <nav class="menu">
        <a href="admin.php" class="activo">⌂ Panel administrativo</a>
        <a href="#estadisticas">▦ Estadísticas</a>
        <a href="#citas">▤ Gestión de citas</a>
        <a href="historial.php">◷ Historial</a>
    </nav>

    <a class="cerrar" href="../auth/logout.php">↪ Cerrar sesión</a>

</aside>

<main class="contenido">

    <header class="topbar">
        <div>
            <h1>Panel administrativo</h1>
            <p>Universidad de El Salvador | CITAUES</p>
        </div>

        <div class="usuario">
            <?= escapar($nombreCompleto) ?>
            <br>
            <small>ADMINISTRADOR</small>
        </div>
    </header>

    <section class="bienvenida">
        <h2>¡Bienvenido al panel administrativo!</h2>
        <p>Desde aquí puedes supervisar las citas y consultar su estado.</p>
    </section>

    <section class="estadisticas" id="estadisticas">

        <div class="tarjeta">
            <p>Total de citas</p>
            <h2><?= (int)$resumen["total"] ?></h2>
        </div>

        <div class="tarjeta">
            <p>Citas pendientes</p>
            <h2><?= (int)$resumen["pendientes"] ?></h2>
        </div>

        <div class="tarjeta">
            <p>Citas confirmadas</p>
            <h2><?= (int)$resumen["confirmadas"] ?></h2>
        </div>

        <div class="tarjeta">
            <p>Citas canceladas</p>
            <h2><?= (int)$resumen["canceladas"] ?></h2>
        </div>

        <div class="tarjeta">
            <p>Citas finalizadas</p>
            <h2><?= (int)$resumen["finalizadas"] ?></h2>
        </div>

    </section>

    <section class="tabla-contenedor" id="citas">

        <h2>Gestión de citas registradas</h2>

        <table>
            <thead>
              <tr>
               <th>ID</th>
               <th>Estudiante</th>
               <th>Servicio</th>
               <th>Profesional</th>
               <th>Fecha</th>
               <th>Hora de inicio</th>
               <th>Hora de finalización</th>
               <th>Motivo</th>
               <th>Estado</th>
               <th>Acciones</th>
             </tr>  
            </thead>

            <tbody>

            <?php if ($resultadoCitas->num_rows > 0): ?>

                <?php while ($cita = $resultadoCitas->fetch_assoc()): ?>

                    <?php
                    $estado = $cita["estado"] ?? "";
                    $claseEstado = preg_replace('/[^A-Z_]/', '', $estado);
                    ?>

                   
<tr>
    <td><?= (int)$cita["id_cita"] ?></td>

    <td>
        <?= escapar(
            trim(($cita["nombres"] ?? "") . " " .
            ($cita["apellidos"] ?? ""))
        ) ?: "Estudiante #" . (int)$cita["id_estudiante"] ?>
    </td>

    <td>
        <?= escapar($cita["servicio"] ?? "Sin servicio") ?>
    </td>

    <td>
        <?= escapar($cita["profesional"] ?? "Sin asignar") ?>
    </td>

    <td><?= escapar($cita["fecha"] ?? "") ?></td>

    <td><?= escapar($cita["hora_inicio"] ?? "") ?></td>

    <td><?= escapar($cita["hora_fin"] ?? "") ?></td>

    <td><?= escapar($cita["motivo"] ?? "") ?></td>

    <td>
        <span class="estado <?= escapar($claseEstado) ?>">
            <?= escapar($estado) ?>
        </span>
    </td>

    <td class="acciones-admin">

    <?php if (in_array($estado, ["PENDIENTE", "CONFIRMADA"], true)): ?>

        <button
            type="button"
            class="btn-reprogramar"
            data-id="<?= (int)$cita["id_cita"] ?>">
            Reprogramar
        </button>

        <button
            type="button"
            class="btn-cancelar"
            data-id="<?= (int)$cita["id_cita"] ?>">
            Cancelar
        </button>

    <?php else: ?>

        <span>Sin acciones disponibles</span>

    <?php endif; ?>

</td>
</tr>


                <?php endwhile; ?>

            <?php else: ?>

                <tr>
                    <td colspan="10" class="vacio">
                        No hay citas registradas.
                    </td>
                </tr>

            <?php endif; ?>

            </tbody>
        </table>

    </section>


</main>

<!-- FUNCIONES DEL PANEL ADMINISTRATIVO -->
<script>
document.addEventListener("DOMContentLoaded", () => {

    // =====================================
    // CANCELAR CITAS
    // =====================================

    document.addEventListener("click", async (evento) => {

        const boton = evento.target.closest(".btn-cancelar");

        if (!boton) return;

        const idCita = boton.dataset.id;

        if (!confirm("¿Estás seguro de que deseas cancelar la cita #" + idCita + "?")) {
            return;
        }

        try {

            boton.disabled = true;
            boton.textContent = "Cancelando...";
             const respuesta = await fetch(
    "../modules/citas/cancelar_cita.php",
    {
        method: "POST",
        headers: {
            "Content-Type": "application/json"
        },
        body: JSON.stringify({
            id_cita: idCita
        })
    }
);
            const resultado = await respuesta.json();

            if (!respuesta.ok || !resultado.success) {
                throw new Error(resultado.mensaje || "No se pudo cancelar la cita.");
            }

            alert(resultado.mensaje);
            location.reload();

        } catch (error) {

            alert(error.message);
            boton.disabled = false;
            boton.textContent = "Cancelar";

        }

    });


    // =====================================
    // REPROGRAMAR CITAS
    // =====================================

    document.addEventListener("click", async (evento) => {

        const boton = evento.target.closest(".btn-reprogramar");

        if (!boton) return;

        const idCita = boton.dataset.id;

        try {

            boton.disabled = true;
            boton.textContent = "Consultando...";

            // Consultar disponibilidades
            const respuesta = await fetch(
    "../modules/disponibilidad/listar.php"
);

            const resultado = await respuesta.json();

            if (!respuesta.ok || !resultado.success) {
                throw new Error(resultado.mensaje || "No se pudieron consultar los horarios.");
            }

            // Mostrar únicamente disponibilidades disponibles y futuras
            const hoy = new Date().toLocaleDateString("en-CA");

            const disponibles = resultado.disponibilidades.filter(d =>
                d.estado === "DISPONIBLE" &&
                d.fecha >= hoy
            );

            if (disponibles.length === 0) {
                throw new Error("No hay disponibilidades futuras.");
            }

            const opciones = disponibles.map(d =>
                `ID ${d.id_disponibilidad} | ${d.servicio} | ${d.fecha} | ${d.hora_inicio} - ${d.hora_fin} | ${d.profesional}`
            ).join("\n");

            const seleccion = prompt(
                "DISPONIBILIDADES DISPONIBLES\n\n" +
                opciones +
                "\n\nEscribe el ID de la disponibilidad:"
            );

            if (seleccion === null) return;

            const disponibilidad = disponibles.find(
                d => String(d.id_disponibilidad) === seleccion.trim()
            );

            if (!disponibilidad) {
                throw new Error("El ID seleccionado no es válido.");
            }

            const hora = prompt(
                "Disponibilidad seleccionada: " +
                disponibilidad.fecha +
                "\nHorario permitido: " +
                disponibilidad.hora_inicio +
                " - " +
                disponibilidad.hora_fin +
                "\n\nEscribe la nueva hora de inicio (HH:MM):",
                disponibilidad.hora_inicio.substring(0, 5)
            );

            if (hora === null) return;

            // Enviar solicitud de reprogramación
            const respuestaCambio = await fetch(
                "../modules/citas/reprogramar_cita.php",
                {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json"
                    },
                    body: JSON.stringify({
                        id_cita: idCita,
                        id_disponibilidad: disponibilidad.id_disponibilidad,
                        hora_inicio: hora.trim()
                    })
                }
            );

            const cambio = await respuestaCambio.json();

            if (!respuestaCambio.ok || !cambio.success) {
                throw new Error(cambio.mensaje || "No se pudo reprogramar la cita.");
            }

            alert(cambio.mensaje);
            location.reload();

        } catch (error) {

            alert(error.message);

        } finally {

            boton.disabled = false;
            boton.textContent = "Reprogramar";

        }

    });

});
</script>

</body>
</html>

<?php
$conexion->close();
?>

