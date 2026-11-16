
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

$idPS = filter_var(
    $datos["id_profesional_servicio"] ?? null,
    FILTER_VALIDATE_INT
);

$fecha = trim($datos["fecha"] ?? "");
$inicio = trim($datos["hora_inicio"] ?? "");
$fin = trim($datos["hora_fin"] ?? "");

$cupo = filter_var(
    $datos["cupo_maximo"] ?? null,
    FILTER_VALIDATE_INT
);

if (
    !$idPS ||
    !$fecha || !$inicio || !$fin ||
    $cupo === false || $cupo === null || $cupo <= 0
) {
    responder(400, ["success" => false, "mensaje" => "Complete correctamente los campos obligatorios."]);
}

// Validar fecha y horas.
$fechaObj = DateTime::createFromFormat("!Y-m-d", $fecha);
$horaInicio = DateTime::createFromFormat("!H:i", $inicio);
$horaFin = DateTime::createFromFormat("!H:i", $fin);

if (
    !$fechaObj || $fechaObj->format("Y-m-d") !== $fecha ||
    !$horaInicio || $horaInicio->format("H:i") !== $inicio ||
    !$horaFin || $horaFin->format("H:i") !== $fin ||
    $horaFin <= $horaInicio ||
    $fecha < date("Y-m-d")
) {
    responder(400, ["success" => false, "mensaje" => "La fecha u horario no son válidos."]);
}

// Obtener asignación, profesional y servicio.
$sql = "SELECT
            ps.id_profesional_servicio,
            ps.id_usuario,
            ps.estado AS estado_asignacion,
            s.id_unidad,
            s.cupo_maximo AS cupo_servicio,
            s.modalidad,
            s.estado AS estado_servicio
        FROM PROFESIONAL_SERVICIO ps
        INNER JOIN SERVICIOS s
            ON ps.id_servicio = s.id_servicio
        WHERE ps.id_profesional_servicio = ?";

$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $idPS);
$stmt->execute();
$asignacion = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$asignacion ||
    $asignacion["estado_asignacion"] !== "ACTIVO" ||
    $asignacion["estado_servicio"] !== "ACTIVO") {
    responder(404, ["success" => false, "mensaje" => "La asignación o el servicio no están activos."]);
}

// Verificar permisos según el usuario que presta el servicio.
$rol = $_SESSION["rol"];

if ($rol === "PROFESIONAL") {

    exigirPermiso("disponibilidad.gestionar");

    if ((int)$asignacion["id_usuario"] !== (int)$_SESSION["id_usuario"]) {
        responder(403, ["success" => false, "mensaje" => "Solo puedes crear tus propias disponibilidades."]);
    }

} elseif ($rol === "COORDINADOR") {

    exigirPermiso("disponibilidad.gestionar_unidad");

    if ((int)$asignacion["id_unidad"] !== (int)$_SESSION["id_unidad"]) {
        responder(403, ["success" => false, "mensaje" => "El profesional no pertenece a tu unidad."]);
    }

} elseif ($rol !== "ADMINISTRADOR") {

    responder(403, ["success" => false, "mensaje" => "No tienes permiso para registrar disponibilidades."]);
}

// Validar capacidad.
if ($cupo > (int)$asignacion["cupo_servicio"] ||
    ($asignacion["modalidad"] === "INDIVIDUAL" && $cupo !== 1)) {
    responder(400, ["success" => false, "mensaje" => "El cupo supera la capacidad permitida por el servicio."]);
}

// Evitar solapamientos de horarios del mismo profesional.
$sqlCruce = "SELECT d.id_disponibilidad
             FROM DISPONIBILIDADES d
             INNER JOIN PROFESIONAL_SERVICIO ps
                ON d.id_profesional_servicio = ps.id_profesional_servicio
             WHERE ps.id_usuario = ?
               AND d.fecha = ?
               AND d.estado = 'ACTIVO'
               AND d.hora_inicio < ?
               AND d.hora_fin > ?
             LIMIT 1";

$stmt = $conexion->prepare($sqlCruce);
$stmt->bind_param(
    "isss",
    $asignacion["id_usuario"],
    $fecha,
    $fin,
    $inicio
);
$stmt->execute();

if ($stmt->get_result()->fetch_assoc()) {
    responder(409, ["success" => false, "mensaje" => "El profesional ya tiene una disponibilidad que se cruza con ese horario."]);
}
$stmt->close();

// Insertar disponibilidad.
$sqlInsertar = "INSERT INTO DISPONIBILIDADES
                (id_profesional_servicio, fecha,
                 hora_inicio, hora_fin, cupo_maximo, estado)
                VALUES (?, ?, ?, ?, ?, 'ACTIVO')";

$stmt = $conexion->prepare($sqlInsertar);
$stmt->bind_param(
    "isssi",
    $idPS,
    $fecha,
    $inicio,
    $fin,
    $cupo
);

if (!$stmt->execute()) {
    responder(500, ["success" => false, "mensaje" => "No fue posible registrar la disponibilidad."]);
}

responder(201, [
    "success" => true,
    "mensaje" => "Disponibilidad registrada correctamente.",
    "id_disponibilidad" => $conexion->insert_id
]);

?>
