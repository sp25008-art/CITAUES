
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

if (!$idDisponibilidad) {
    responder(400, ["success" => false, "mensaje" => "ID de disponibilidad inválido."]);
}

// Consultar la disponibilidad y su propietario.
$sql = "SELECT
            d.id_disponibilidad,
            d.estado,
            ps.id_usuario,
            s.id_unidad
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
    responder(409, ["success" => false, "mensaje" => "La disponibilidad ya está inactiva."]);
}

// Comprobar permisos.
$rol = $_SESSION["rol"];

if ($rol === "PROFESIONAL") {

    exigirPermiso("disponibilidad.gestionar");

    if ((int)$disponibilidad["id_usuario"] !== (int)$_SESSION["id_usuario"]) {
        responder(403, ["success" => false, "mensaje" => "No puedes eliminar disponibilidades de otros profesionales."]);
    }

} elseif ($rol === "COORDINADOR") {

    exigirPermiso("disponibilidad.gestionar_unidad");

    if ((int)$disponibilidad["id_unidad"] !== (int)$_SESSION["id_unidad"]) {
        responder(403, ["success" => false, "mensaje" => "No puedes eliminar disponibilidades de otra unidad."]);
    }

} elseif ($rol !== "ADMINISTRADOR") {

    responder(403, ["success" => false, "mensaje" => "No tienes permiso para eliminar disponibilidades."]);
}

// No desactivar horarios con citas pendientes o confirmadas.
$sqlCitas = "SELECT c.id_cita
             FROM CITAS c
             WHERE c.id_disponibilidad = ?
               AND c.estado IN ('PENDIENTE', 'CONFIRMADA')
             LIMIT 1";

$stmt = $conexion->prepare($sqlCitas);
$stmt->bind_param("i", $idDisponibilidad);
$stmt->execute();

if ($stmt->get_result()->fetch_assoc()) {
    responder(409, ["success" => false, "mensaje" => "No puedes desactivar un horario que tiene citas pendientes o confirmadas."]);
}
$stmt->close();

// Desactivar sin borrar físicamente.
$sqlActualizar = "UPDATE DISPONIBILIDADES
                  SET estado = 'INACTIVO'
                  WHERE id_disponibilidad = ?";

$stmt = $conexion->prepare($sqlActualizar);
$stmt->bind_param("i", $idDisponibilidad);

if (!$stmt->execute()) {
    responder(500, ["success" => false, "mensaje" => "No fue posible desactivar la disponibilidad."]);
}

responder(200, [
    "success" => true,
    "mensaje" => "Disponibilidad desactivada correctamente."
]);

?>
