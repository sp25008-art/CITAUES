
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

$idDisponibilidad = filter_var(
    $datos["id_disponibilidad"] ?? null,
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
    !$idDisponibilidad || !$fecha || !$inicio || !$fin ||
    $cupo === false || $cupo === null || $cupo <= 0
) {
    responder(400, ["success" => false, "mensaje" => "Datos incompletos o inválidos."]);
}

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

// Obtener disponibilidad y datos de la asignación.
$sql = "SELECT
            d.id_disponibilidad,
            d.id_profesional_servicio,
            d.estado,
            ps.id_usuario,
            s.id_unidad,
            s.cupo_maximo AS cupo_servicio,
            s.modalidad
        FROM DISPONIBILIDADES d
        INNER JOIN PROFESIONAL_SERVICIO ps
            ON d.id_profesional_servicio = ps.id_profesional_servicio
        INNER JOIN SERVICIOS s
            ON ps.id_servicio = s.id_servicio
        WHERE d.id_disponibilidad = ?";

$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $idDisponibilidad);
$stmt->execute();
$disponibilidad = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$disponibilidad) {
    responder(404, ["success" => false, "mensaje" => "Disponibilidad no encontrada."]);
}

if ($disponibilidad["estado"] !== "ACTIVO") {
    responder(409, ["success" => false, "mensaje" => "No puedes editar una disponibilidad inactiva."]);
}

// Verificar permisos.
$rol = $_SESSION["rol"];

if ($rol === "PROFESIONAL") {

    exigirPermiso("disponibilidad.gestionar");

    if ((int)$disponibilidad["id_usuario"] !== (int)$_SESSION["id_usuario"]) {
        responder(403, ["success" => false, "mensaje" => "No puedes editar disponibilidades de otros profesionales."]);
    }

} elseif ($rol === "COORDINADOR") {

    exigirPermiso("disponibilidad.gestionar_unidad");

    if ((int)$disponibilidad["id_unidad"] !== (int)$_SESSION["id_unidad"]) {
        responder(403, ["success" => false, "mensaje" => "No puedes editar disponibilidades de otra unidad."]);
    }

} elseif ($rol !== "ADMINISTRADOR") {

    responder(403, ["success" => false, "mensaje" => "No tienes permiso para editar disponibilidades."]);
}

// Validar cupo.
if (
    $cupo > (int)$disponibilidad["cupo_servicio"] ||
    ($disponibilidad["modalidad"] === "INDIVIDUAL" && $cupo !== 1)
) {
    responder(400, ["success" => false, "mensaje" => "El cupo supera la capacidad del servicio."]);
}

// Evitar cruces con otros horarios del mismo profesional.
$sqlCruce = "SELECT d.id_disponibilidad
             FROM DISPONIBILIDADES d
             INNER JOIN PROFESIONAL_SERVICIO ps
                ON d.id_profesional_servicio = ps.id_profesional_servicio
             WHERE ps.id_usuario = ?
               AND d.fecha = ?
               AND d.estado = 'ACTIVO'
               AND d.id_disponibilidad <> ?
               AND d.hora_inicio < ?
               AND d.hora_fin > ?
             LIMIT 1";

$stmt = $conexion->prepare($sqlCruce);
$stmt->bind_param(
    "isiss",
    $disponibilidad["id_usuario"],
    $fecha,
    $idDisponibilidad,
    $fin,
    $inicio
);
$stmt->execute();

if ($stmt->get_result()->fetch_assoc()) {
    responder(409, ["success" => false, "mensaje" => "El nuevo horario se cruza con otra disponibilidad del profesional."]);
}
$stmt->close();

$sqlActualizar = "UPDATE DISPONIBILIDADES
                  SET fecha = ?,
                      hora_inicio = ?,
                      hora_fin = ?,
                      cupo_maximo = ?
                  WHERE id_disponibilidad = ?";

$stmt = $conexion->prepare($sqlActualizar);
$stmt->bind_param(
    "sssii",
    $fecha,
    $inicio,
    $fin,
    $cupo,
    $idDisponibilidad
);

if (!$stmt->execute()) {
    responder(500, ["success" => false, "mensaje" => "No fue posible actualizar la disponibilidad."]);
}

responder(200, [
    "success" => true,
    "mensaje" => "Disponibilidad actualizada correctamente."
]);

?>
