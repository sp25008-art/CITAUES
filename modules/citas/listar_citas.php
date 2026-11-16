
<?php

header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . "/../../config/conexion.php";
require_once __DIR__ . "/../../auth/permisos.php";

verificarSesion();

$rol = $_SESSION["rol"];

try {

    // Consultar citas y sus posibles registros de asistencia.
    $sql = "SELECT
                c.id_cita,
                c.id_estudiante,
                c.id_disponibilidad,
                c.fecha_solicitud,
                c.estado,
                c.motivo,
                c.observaciones,
                c.hora_inicio,
                c.hora_fin,
                d.fecha,
                s.id_servicio,
                s.nombre AS servicio,
                CONCAT(e.nombres, ' ', e.apellidos) AS estudiante,
                CONCAT(p.nombres, ' ', p.apellidos) AS profesional,
                un.nombre AS unidad,

                a.id_asistencia,
                a.estado_asistencia,
                a.observacion AS observacion_asistencia,
                a.fecha_registro AS fecha_registro_asistencia

            FROM CITAS c

            INNER JOIN DISPONIBILIDADES d
                ON c.id_disponibilidad = d.id_disponibilidad

            INNER JOIN PROFESIONAL_SERVICIO ps
                ON d.id_profesional_servicio = ps.id_profesional_servicio

            INNER JOIN SERVICIOS s
                ON ps.id_servicio = s.id_servicio

            INNER JOIN UNIDADES un
                ON s.id_unidad = un.id_unidad

            INNER JOIN USUARIOS e
                ON c.id_estudiante = e.id_usuario

            INNER JOIN USUARIOS p
                ON ps.id_usuario = p.id_usuario

            LEFT JOIN ASISTENCIAS a
                ON a.id_cita = c.id_cita";

    // Filtrar según el rol.
    if ($rol === "ESTUDIANTE") {

        exigirPermiso("citas.consultar_propias");
        $sql .= " WHERE c.id_estudiante = ?";

    } elseif ($rol === "PROFESIONAL") {

        exigirPermiso("citas.consultar_asignadas");
        $sql .= " WHERE ps.id_usuario = ?";

    } elseif ($rol === "COORDINADOR") {

        exigirPermiso("citas.consultar_unidad");
        $sql .= " WHERE s.id_unidad = ?";

    } elseif ($rol !== "ADMINISTRADOR") {

        responderError(403, "No tienes permiso para consultar citas.");
    }

    $sql .= " ORDER BY d.fecha DESC, c.hora_inicio DESC";

    $stmt = $conexion->prepare($sql);

    if (!$stmt) {
        throw new Exception("Error al preparar la consulta.");
    }

    // Asignar parámetro según el rol.
    if ($rol === "ESTUDIANTE" || $rol === "PROFESIONAL") {

        $idUsuario = (int)$_SESSION["id_usuario"];
        $stmt->bind_param("i", $idUsuario);

    } elseif ($rol === "COORDINADOR") {

        $idUnidad = (int)$_SESSION["id_unidad"];
        $stmt->bind_param("i", $idUnidad);
    }

    if (!$stmt->execute()) {
        throw new Exception("Error al consultar las citas.");
    }

    $resultado = $stmt->get_result();

    $citas = [];

    while ($fila = $resultado->fetch_assoc()) {

        // Identificar si ya existe una asistencia.
        $fila["tiene_asistencia"] =
            !empty($fila["id_asistencia"]);

        $citas[] = $fila;
    }

    echo json_encode([
        "success" => true,
        "total" => count($citas),
        "citas" => $citas
    ], JSON_UNESCAPED_UNICODE);

    $stmt->close();
    $conexion->close();

} catch (Throwable $e) {

    responderError(500, $e->getMessage());
}


function responderError($codigo, $mensaje) {

    http_response_code($codigo);

    echo json_encode([
        "success" => false,
        "mensaje" => $mensaje
    ], JSON_UNESCAPED_UNICODE);

    exit();
}
?>

