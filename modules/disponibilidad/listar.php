
<?php

header("Content-Type: application/json; charset=UTF-8");

require_once "../../config/conexion.php";
require_once "../../auth/permisos.php";

verificarSesion();

$rol = $_SESSION["rol"];

$sql = "SELECT
            d.id_disponibilidad,
            d.id_profesional_servicio,
            d.fecha,
            d.hora_inicio,
            d.hora_fin,
            d.cupo_maximo,
            d.estado,
            u.id_usuario,
            CONCAT(u.nombres, ' ', u.apellidos) AS profesional,
            s.id_servicio,
            s.nombre AS servicio,
            un.nombre AS unidad
        FROM DISPONIBILIDADES d
        INNER JOIN PROFESIONAL_SERVICIO ps
            ON d.id_profesional_servicio = ps.id_profesional_servicio
        INNER JOIN USUARIOS u
            ON ps.id_usuario = u.id_usuario
        INNER JOIN SERVICIOS s
            ON ps.id_servicio = s.id_servicio
        INNER JOIN UNIDADES un
            ON s.id_unidad = un.id_unidad";

if ($rol === "PROFESIONAL") {
    $sql .= " WHERE ps.id_usuario = ?";
} elseif ($rol === "COORDINADOR") {
    $sql .= " WHERE s.id_unidad = ?";
} elseif ($rol === "ESTUDIANTE") {
    $sql .= " WHERE d.estado = 'DISPONIBLE'
              AND s.estado = 'ACTIVO'
              AND d.fecha >= CURDATE()";
}

$sql .= " ORDER BY d.fecha, d.hora_inicio";

$stmt = $conexion->prepare($sql);

if ($rol === "PROFESIONAL") {
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
        "mensaje" => "Error al consultar disponibilidades."
    ]);

    exit();
}

$resultado = $stmt->get_result();
$disponibilidades = [];

while ($fila = $resultado->fetch_assoc()) {
    $disponibilidades[] = $fila;
}

echo json_encode([
    "success" => true,
    "total" => count($disponibilidades),
    "disponibilidades" => $disponibilidades
], JSON_UNESCAPED_UNICODE);

$stmt->close();
$conexion->close();

?>


