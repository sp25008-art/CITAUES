
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

$idCita = filter_var($datos["id_cita"] ?? null, FILTER_VALIDATE_INT);

if (!$idCita) {
    responder(400, ["success" => false, "mensaje" => "ID de cita inválido."]);
}

$rol = $_SESSION["rol"];

if (!in_array($rol, ["PROFESIONAL", "COORDINADOR", "ADMINISTRADOR"], true)) {
    responder(403, ["success" => false, "mensaje" => "No puedes confirmar citas."]);
}

$sql = "SELECT
            c.estado,
            ps.id_usuario,
            s.id_unidad
        FROM CITAS c
        INNER JOIN DISPONIBILIDADES d
            ON c.id_disponibilidad = d.id_disponibilidad
        INNER JOIN PROFESIONAL_SERVICIO ps
            ON d.id_profesional_servicio = ps.id_profesional_servicio
        INNER JOIN SERVICIOS s
            ON ps.id_servicio = s.id_servicio
        WHERE c.id_cita = ?";

$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $idCita);
$stmt->execute();
$cita = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$cita) {
    responder(404, ["success" => false, "mensaje" => "Cita no encontrada."]);
}

if ($cita["estado"] !== "PENDIENTE") {
    responder(409, ["success" => false, "mensaje" => "Solo se pueden confirmar citas pendientes."]);
}

if ($rol === "PROFESIONAL") {

    if ((int)$cita["id_usuario"] !== (int)$_SESSION["id_usuario"]) {
        responder(403, ["success" => false, "mensaje" => "Esta cita no pertenece a tus servicios."]);
    }

} elseif ($rol === "COORDINADOR") {

    exigirPermiso("citas.consultar_unidad");

    if ((int)$cita["id_unidad"] !== (int)$_SESSION["id_unidad"]) {
        responder(403, ["success" => false, "mensaje" => "La cita no pertenece a tu unidad."]);
    }
}

$stmt = $conexion->prepare(
    "UPDATE CITAS SET estado = 'CONFIRMADA'
     WHERE id_cita = ? AND estado = 'PENDIENTE'"
);
$stmt->bind_param("i", $idCita);
$stmt->execute();

if ($stmt->affected_rows !== 1) {
    responder(409, ["success" => false, "mensaje" => "La cita cambió de estado o ya fue procesada."]);
}

responder(200, [
    "success" => true,
    "mensaje" => "Cita confirmada correctamente."
]);

?>
