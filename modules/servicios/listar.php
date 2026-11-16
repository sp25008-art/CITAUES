
<?php

header("Content-Type: application/json; charset=UTF-8");

require_once "../../config/conexion.php";
require_once "../../auth/permisos.php";

verificarSesion();

$rol = $_SESSION["rol"];

$sql = "SELECT
            s.id_servicio,
            s.id_unidad,
            u.nombre AS unidad,
            s.nombre,
            s.descripcion,
            s.duracion_minutos,
            s.modalidad,
            s.metodo_asignacion,
            s.metodo_confirmacion,
            s.cupo_maximo,
            s.anticipacion_minima_horas,
            s.horas_limite_cancelacion,
            s.estado
        FROM SERVICIOS s
        INNER JOIN UNIDADES u
            ON s.id_unidad = u.id_unidad";

if ($rol === "COORDINADOR") {
    $sql .= " WHERE s.id_unidad = ?";
} elseif ($rol !== "ADMINISTRADOR") {
    $sql .= " WHERE s.estado = 'ACTIVO'";
}

$sql .= " ORDER BY s.id_servicio";

$consulta = $conexion->prepare($sql);

if ($rol === "COORDINADOR") {
    $idUnidad = (int)$_SESSION["id_unidad"];
    $consulta->bind_param("i", $idUnidad);
}

if (!$consulta->execute()) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "mensaje" => "Error al consultar los servicios."
    ]);
    exit();
}

$resultado = $consulta->get_result();
$servicios = [];

while ($fila = $resultado->fetch_assoc()) {
    $servicios[] = $fila;
}

echo json_encode([
    "success" => true,
    "total" => count($servicios),
    "servicios" => $servicios
], JSON_UNESCAPED_UNICODE);

$consulta->close();
$conexion->close();

?>
