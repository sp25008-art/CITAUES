
<?php

header("Content-Type: application/json; charset=UTF-8");

require_once "../../config/conexion.php";
require_once "../../auth/permisos.php";

function responder($codigo, $datos) {
    http_response_code($codigo);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    responder(405, ["success" => false, "mensaje" => "Método no permitido."]);
}

$datos = json_decode(file_get_contents("php://input"), true);
if (!is_array($datos)) {
    $datos = $_POST;
}

$idServicio = filter_var($datos["id_servicio"] ?? null, FILTER_VALIDATE_INT);
$estado = strtoupper(trim($datos["estado"] ?? ""));

if (!$idServicio || !in_array($estado, ["ACTIVO", "INACTIVO"], true)) {
    responder(400, ["success" => false, "mensaje" => "Datos no válidos."]);
}

$rolUsuario = $_SESSION["rol"];

$stmt = $conexion->prepare(
    "SELECT id_servicio, id_unidad FROM SERVICIOS WHERE id_servicio = ?"
);
$stmt->bind_param("i", $idServicio);
$stmt->execute();
$servicio = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$servicio) {
    responder(404, ["success" => false, "mensaje" => "El servicio no existe."]);
}

if ($rolUsuario === "COORDINADOR") {

    exigirPermiso("servicios.gestionar_unidad");

    if ((int)$servicio["id_unidad"] !== (int)$_SESSION["id_unidad"]) {
        responder(403, ["success" => false, "mensaje" => "No puedes modificar servicios de otra unidad."]);
    }

} elseif ($rolUsuario !== "ADMINISTRADOR") {

    responder(403, ["success" => false, "mensaje" => "No tienes permiso para cambiar el estado."]);
}

$stmt = $conexion->prepare(
    "UPDATE SERVICIOS SET estado = ? WHERE id_servicio = ?"
);
$stmt->bind_param("si", $estado, $idServicio);

if (!$stmt->execute()) {
    responder(500, ["success" => false, "mensaje" => "No fue posible cambiar el estado."]);
}

responder(200, [
    "success" => true,
    "mensaje" => "Estado del servicio actualizado correctamente.",
    "nuevo_estado" => $estado
]);

?>
