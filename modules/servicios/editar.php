
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
$nombre = trim($datos["nombre"] ?? "");
$descripcion = trim($datos["descripcion"] ?? "");

$duracion = filter_var($datos["duracion_minutos"] ?? null, FILTER_VALIDATE_INT);
$modalidad = strtoupper(trim($datos["modalidad"] ?? ""));
$metodoAsignacion = strtoupper(trim($datos["metodo_asignacion"] ?? ""));
$metodoConfirmacion = strtoupper(trim($datos["metodo_confirmacion"] ?? ""));
$cupo = filter_var($datos["cupo_maximo"] ?? null, FILTER_VALIDATE_INT);
$anticipacion = filter_var($datos["anticipacion_minima_horas"] ?? null, FILTER_VALIDATE_INT);
$limiteCancelacion = filter_var($datos["horas_limite_cancelacion"] ?? null, FILTER_VALIDATE_INT);

if (
    !$idServicio || $nombre === "" ||
    $duracion === false || $duracion === null || $duracion <= 0 ||
    $cupo === false || $cupo === null || $cupo <= 0 ||
    $anticipacion === false || $anticipacion === null || $anticipacion < 0 ||
    $limiteCancelacion === false || $limiteCancelacion === null || $limiteCancelacion < 0
) {
    responder(400, ["success" => false, "mensaje" => "Verifica los campos obligatorios."]);
}

if (!in_array($modalidad, ["INDIVIDUAL", "POR_CUPOS"], true) ||
    !in_array($metodoAsignacion, ["ELEGIR", "AUTOMATICA"], true) ||
    !in_array($metodoConfirmacion, ["AUTOMATICA", "MANUAL"], true)) {
    responder(400, ["success" => false, "mensaje" => "Configuración del servicio no válida."]);
}

if ($modalidad === "INDIVIDUAL" && $cupo !== 1) {
    responder(400, ["success" => false, "mensaje" => "Los servicios individuales deben tener cupo máximo de 1."]);
}

$rolUsuario = $_SESSION["rol"];

$sqlBuscar = "SELECT id_servicio, id_unidad FROM SERVICIOS WHERE id_servicio = ?";
$stmt = $conexion->prepare($sqlBuscar);
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

    $idUnidad = (int)$_SESSION["id_unidad"];

} elseif ($rolUsuario === "ADMINISTRADOR") {

    $idUnidad = filter_var($datos["id_unidad"] ?? $servicio["id_unidad"], FILTER_VALIDATE_INT);

    if (!$idUnidad || $idUnidad <= 0) {
        responder(400, ["success" => false, "mensaje" => "Unidad no válida."]);
    }

} else {
    responder(403, ["success" => false, "mensaje" => "No tienes permiso para editar servicios."]);
}

// Comprobar que la unidad exista.
$stmt = $conexion->prepare("SELECT id_unidad FROM UNIDADES WHERE id_unidad = ?");
$stmt->bind_param("i", $idUnidad);
$stmt->execute();

if (!$stmt->get_result()->fetch_assoc()) {
    responder(400, ["success" => false, "mensaje" => "La unidad no existe."]);
}
$stmt->close();

// Evitar nombres duplicados dentro de una unidad.
$stmt = $conexion->prepare(
    "SELECT id_servicio FROM SERVICIOS
     WHERE id_unidad = ? AND nombre = ? AND id_servicio <> ?"
);
$stmt->bind_param("isi", $idUnidad, $nombre, $idServicio);
$stmt->execute();

if ($stmt->get_result()->fetch_assoc()) {
    responder(409, ["success" => false, "mensaje" => "Ya existe otro servicio con ese nombre en la unidad."]);
}
$stmt->close();

$sql = "UPDATE SERVICIOS
        SET id_unidad = ?,
            nombre = ?,
            descripcion = ?,
            duracion_minutos = ?,
            modalidad = ?,
            metodo_asignacion = ?,
            metodo_confirmacion = ?,
            cupo_maximo = ?,
            anticipacion_minima_horas = ?,
            horas_limite_cancelacion = ?
        WHERE id_servicio = ?";

$stmt = $conexion->prepare($sql);

$stmt->bind_param(
    "ississsiiii",
    $idUnidad,
    $nombre,
    $descripcion,
    $duracion,
    $modalidad,
    $metodoAsignacion,
    $metodoConfirmacion,
    $cupo,
    $anticipacion,
    $limiteCancelacion,
    $idServicio
);

if (!$stmt->execute()) {
    responder(500, ["success" => false, "mensaje" => "No fue posible actualizar el servicio."]);
}

responder(200, [
    "success" => true,
    "mensaje" => "Servicio actualizado correctamente."
]);

?>
