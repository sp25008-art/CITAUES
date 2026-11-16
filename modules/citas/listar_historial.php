
<?php
header("Content-Type: application/json; charset=utf-8");

require_once __DIR__ . "/../../config/conexion.php";
require_once __DIR__ . "/../../auth/permisos.php";

verificarSesion();

$rol = $_SESSION["rol"];
$idUsuario = (int)$_SESSION["id_usuario"];
$idUnidad = (int)($_SESSION["id_unidad"] ?? 0);

function responderError($codigo, $mensaje) {
    http_response_code($codigo);
    echo json_encode([
        "exito" => false,
        "mensaje" => $mensaje
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // Permisos según el rol.
    if ($rol === "ESTUDIANTE") {
        exigirPermiso("citas.consultar_propias");
    } elseif ($rol === "PROFESIONAL") {
        exigirPermiso("citas.consultar_asignadas");
    } elseif ($rol === "COORDINADOR") {
        exigirPermiso("citas.consultar_unidad");
    } elseif ($rol !== "ADMINISTRADOR") {
        responderError(403, "No tienes permiso para consultar el historial.");
    }

    // Filtro opcional por cita.
    $idCita = null;

    if (isset($_GET["id_cita"]) && $_GET["id_cita"] !== "") {
        $idCita = filter_var($_GET["id_cita"], FILTER_VALIDATE_INT);

        if ($idCita === false || $idCita <= 0) {
            responderError(400, "El ID de cita no es válido.");
        }
    }

    $sql = "SELECT
                h.id_historial,
                h.id_cita,
                h.tipo_cambio,
                h.estado_anterior,
                h.estado_nuevo,
                h.id_disponibilidad_anterior,
                h.id_disponibilidad_nueva,
                h.fecha_cambio,
                h.observacion,

                CONCAT(u.nombres, ' ', u.apellidos) AS usuario,

                s.nombre AS servicio,
                c.motivo,
                c.estado AS estado_actual,
                c.hora_inicio,
                c.hora_fin,

                d_actual.fecha AS fecha_actual,
                d_anterior.fecha AS fecha_anterior,
                d_nueva.fecha AS fecha_nueva

            FROM HISTORIAL_CITAS h

            INNER JOIN CITAS c
                ON h.id_cita = c.id_cita

            INNER JOIN DISPONIBILIDADES d_actual
                ON c.id_disponibilidad = d_actual.id_disponibilidad

            INNER JOIN PROFESIONAL_SERVICIO ps
                ON d_actual.id_profesional_servicio = ps.id_profesional_servicio

            INNER JOIN SERVICIOS s
                ON ps.id_servicio = s.id_servicio

            INNER JOIN USUARIOS u
                ON h.id_usuario = u.id_usuario

            LEFT JOIN DISPONIBILIDADES d_anterior
                ON h.id_disponibilidad_anterior = d_anterior.id_disponibilidad

            LEFT JOIN DISPONIBILIDADES d_nueva
                ON h.id_disponibilidad_nueva = d_nueva.id_disponibilidad

            WHERE 1 = 1";

    $tipos = "";
    $parametros = [];

    if ($idCita !== null) {
        $sql .= " AND h.id_cita = ?";
        $tipos .= "i";
        $parametros[] = $idCita;
    }

    if ($rol === "ESTUDIANTE") {
        $sql .= " AND c.id_estudiante = ?";
        $tipos .= "i";
        $parametros[] = $idUsuario;

    } elseif ($rol === "PROFESIONAL") {
        $sql .= " AND ps.id_usuario = ?";
        $tipos .= "i";
        $parametros[] = $idUsuario;

    } elseif ($rol === "COORDINADOR") {
        $sql .= " AND s.id_unidad = ?";
        $tipos .= "i";
        $parametros[] = $idUnidad;
    }

    $sql .= " ORDER BY h.fecha_cambio DESC";

    $stmt = $conexion->prepare($sql);

    if (!$stmt) {
        responderError(500, "No se pudo preparar la consulta.");
    }

    if (!empty($parametros)) {
        $stmt->bind_param($tipos, ...$parametros);
    }

    if (!$stmt->execute()) {
        responderError(500, "No se pudo consultar el historial.");
    }

    $resultado = $stmt->get_result();
    $historial = [];

    while ($fila = $resultado->fetch_assoc()) {
        $historial[] = $fila;
    }

    echo json_encode([
        "exito" => true,
        "total" => count($historial),
        "historial" => $historial
    ], JSON_UNESCAPED_UNICODE);

    $stmt->close();
    $conexion->close();

} catch (Throwable $e) {
    responderError(400, $e->getMessage());
}
?>
