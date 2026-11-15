
<?php

header("Content-Type: application/json; charset=UTF-8");

require_once "../../config/conexion.php";
require_once "../../auth/permisos.php";

verificarSesion();

$rol = $_SESSION["rol"];

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
            un.nombre AS unidad
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
            ON ps.id_usuario = p.id_usuario";

if ($rol === "ESTUDIANTE") {
    $sql .= " WHERE c.id_estudiante = ?";
} elseif ($rol === "PROFESIONAL") {
    $sql .= " WHERE ps.id_usuario = ?";
} elseif ($rol === "COORDINADOR") {
    $sql .= " WHERE s.id_unidad = ?";
} elseif ($rol !== "ADMINISTRADOR") {
    http_response_code(403);
    echo json_encode([
        "success" => false,
        "mensaje" => "No tienes permiso para consultar citas."
    ]);
    exit();
}

$sql .= " ORDER BY d.fecha DESC, c.hora_inicio DESC";

$stmt = $conexion->prepare($sql);

if ($rol === "ESTUDIANTE" || $rol === "PROFESIONAL") {
    $idUsuario = (int)$_SESSION["id_usuario"];
    $stmt->bind_param("i", $idUsuario);
} elseif ($rol === "COORDINADOR") {
    $idUnidad = (int)$_SESSION["id_unidad"];
    $stmt->bind_param("i", $idUnidad);
}

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "mensaje" => "Error al consultar las citas."
    ]);
    exit();
}

$resultado = $stmt->get_result();
$citas = [];

while ($fila = $resultado->fetch_assoc()) {
    $citas[] = $fila;
}

echo json_encode([
    "success" => true,
    "total" => count($citas),
    "citas" => $citas
], JSON_UNESCAPED_UNICODE);

$stmt->close();
$conexion->close();

?>
