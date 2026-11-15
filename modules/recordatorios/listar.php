
<?php
session_start();

require_once __DIR__ . "/../../config/conexion.php";
require_once __DIR__ . "/../../auth/verificar_sesion.php";

header("Content-Type: application/json; charset=utf-8");

try {
    verificarSesion();

    $rol = $_SESSION["rol"];
    $idUsuario = (int)$_SESSION["id_usuario"];

    $sql = "SELECT
                r.id_recordatorio,
                r.id_cita,
                r.fecha_programada,
                r.fecha_envio,
                r.medio,
                r.estado,
                c.estado AS estado_cita,
                c.fecha_solicitud,
                c.hora_inicio,
                c.hora_fin,
                d.fecha,
                s.nombre AS servicio,
                u.nombres AS estudiante,
                u.apellidos AS apellido_estudiante
            FROM RECORDATORIOS r
            INNER JOIN CITAS c ON r.id_cita = c.id_cita
            INNER JOIN DISPONIBILIDADES d
                ON c.id_disponibilidad = d.id_disponibilidad
            INNER JOIN PROFESIONAL_SERVICIO ps
                ON d.id_profesional_servicio = ps.id_profesional_servicio
            INNER JOIN SERVICIOS s ON ps.id_servicio = s.id_servicio
            INNER JOIN USUARIOS u ON c.id_estudiante = u.id_usuario";

    if ($rol === "ESTUDIANTE") {
        $sql .= " WHERE c.id_estudiante = ?";
    } elseif ($rol === "PROFESIONAL") {
        $sql .= " WHERE ps.id_usuario = ?";
    } elseif (!in_array($rol, ["ADMINISTRADOR", "COORDINADOR"], true)) {
        throw new Exception("No tienes permisos para consultar recordatorios.");
    }

    $sql .= " ORDER BY r.fecha_programada DESC";

    $stmt = $conexion->prepare($sql);

    if ($rol === "ESTUDIANTE" || $rol === "PROFESIONAL") {
        $stmt->bind_param("i", $idUsuario);
    }

    $stmt->execute();
    $resultado = $stmt->get_result();

    $recordatorios = [];

    while ($fila = $resultado->fetch_assoc()) {
        $recordatorios[] = $fila;
    }

    echo json_encode([
        "success" => true,
        "total" => count($recordatorios),
        "recordatorios" => $recordatorios
    ], JSON_UNESCAPED_UNICODE);

    $stmt->close();

} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "mensaje" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
