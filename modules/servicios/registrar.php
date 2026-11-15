
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

$rolUsuario = $_SESSION["rol"];

if ($rolUsuario === "COORDINADOR") {
    exigirPermiso("servicios.gestionar_unidad");
    $idUnidad = (int)$_SESSION["id_unidad"];
} elseif ($rolUsuario === "ADMINISTRADOR") {
    $idUnidad = filter_var(
        $datos["id_unidad"] ?? null,
        FILTER_VALIDATE_INT
    );
} else {
    responder(403, ["success" => false, "mensaje" => "No tienes permiso para registrar servicios."]);
}

$nombre = trim($datos["nombre"] ?? "");
$descripcion = trim($datos["descripcion"] ?? "");

$duracion = filter_var(
    $datos["duracion_minutos"] ?? null,
    FILTER_VALIDATE_INT
);

$modalidad = strtoupper(trim($datos["modalidad"] ?? ""));
$metodoAsignacion = strtoupper(trim($datos["metodo_asignacion"] ?? ""));
$metodoConfirmacion = strtoupper(trim($datos["metodo_confirmacion"] ?? ""));

$cupo = filter_var(
    $datos["cupo_maximo"] ?? null,
    FILTER_VALIDATE_INT
);

$anticipacion = filter_var(
    $datos["anticipacion_minima_horas"] ?? null,
    FILTER_VALIDATE_INT
);

$limiteCancelacion = filter_var(
    $datos["horas_limite_cancelacion"] ?? null,
    FILTER_VALIDATE_INT
);

if (
    $nombre === "" ||
    $idUnidad === false || $idUnidad === null || $idUnidad <= 0 ||
    $duracion === false || $duracion === null || $duracion <= 0 ||
    $cupo === false || $cupo === null || $cupo <= 0 ||
    $anticipacion === false || $anticipacion === null || $anticipacion < 0 ||
    $limiteCancelacion === false || $limiteCancelacion === null || $limiteCancelacion < 0
) {
    responder(400, ["success" => false, "mensaje" => "Verifica los campos obligatorios y sus valores."]);
}

if (!in_array($modalidad, ["INDIVIDUAL", "POR_CUPOS"], true)) {
    responder(400, ["success" => false, "mensaje" => "Modalidad no válida."]);
}

if (!in_array($metodoAsignacion, ["ELEGIR", "AUTOMATICA"], true)) {
    responder(400, ["success" => false, "mensaje" => "Método de asignación no válido."]);
}

if (!in_array($metodoConfirmacion, ["AUTOMATICA", "MANUAL"], true)) {
    responder(400, ["success" => false, "mensaje" => "Método de confirmación no válido."]);
}

if ($modalidad === "INDIVIDUAL" && $cupo !== 1) {
    responder(400, ["success" => false, "mensaje" => "Los servicios individuales deben tener cupo máximo de 1."]);
}

// Verificar que exista la unidad.
$stmt = $conexion->prepare("SELECT id_unidad FROM UNIDADES WHERE id_unidad = ?");
$stmt->bind_param("i", $idUnidad);
$stmt->execute();

if (!$stmt->get_result()->fetch_assoc()) {
    responder(400, ["success" => false, "mensaje" => "La unidad no existe."]);
}
$stmt->close();

// Evitar servicios con el mismo nombre en una unidad.
$stmt = $conexion->prepare(
    "SELECT id_servicio FROM SERVICIOS WHERE id_unidad = ? AND nombre = ?"
);
$stmt->bind_param("is", $idUnidad, $nombre);
$stmt->execute();

if ($stmt->get_result()->fetch_assoc()) {
    responder(409, ["success" => false, "mensaje" => "Ya existe un servicio con ese nombre en la unidad."]);
}
$stmt->close();

$sql = "INSERT INTO SERVICIOS
        (id_unidad, nombre, descripcion, duracion_minutos,
         modalidad, metodo_asignacion, metodo_confirmacion,
         cupo_maximo, anticipacion_minima_horas,
         horas_limite_cancelacion, estado)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'ACTIVO')";

$stmt = $conexion->prepare($sql);

$stmt->bind_param(
    "ississsiii",
    $idUnidad,
    $nombre,
    $descripcion,
    $duracion,
    $modalidad,
    $metodoAsignacion,
    $metodoConfirmacion,
    $cupo,
    $anticipacion,
    $limiteCancelacion
);

if (!$stmt->execute()) {
    responder(500, ["success" => false, "mensaje" => "No fue posible registrar el servicio."]);
}

responder(201, [
    "success" => true,
    "mensaje" => "Servicio registrado correctamente.",
    "id_servicio" => $conexion->insert_id
]);

?>
