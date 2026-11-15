
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

$sql = "SELECT
            c.id_cita,
            c.id_estudiante,
            c.estado,
            d.fecha,
            c.hora_inicio,
            s.horas_limite_cancelacion,
            ps.id_usuario AS id_profesional,
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

if (!in_array($cita["estado"], ["PENDIENTE", "CONFIRMADA"], true)) {
    responder(409, ["success" => false, "mensaje" => "La cita no se encuentra en un estado que permita cancelarla."]);
}

// Verificar autorización.
if ($rol === "ESTUDIANTE") {

    if ((int)$cita["id_estudiante"] !== (int)$_SESSION["id_usuario"]) {
        responder(403, ["success" => false, "mensaje" => "Solo puedes cancelar tus propias citas."]);
    }

} elseif ($rol === "PROFESIONAL") {

    if ((int)$cita["id_profesional"] !== (int)$_SESSION["id_usuario"]) {
        responder(403, ["success" => false, "mensaje" => "No puedes cancelar citas de otros profesionales."]);
    }

} elseif ($rol === "COORDINADOR") {

    if ((int)$cita["id_unidad"] !== (int)$_SESSION["id_unidad"]) {
        responder(403, ["success" => false, "mensaje" => "La cita no pertenece a tu unidad."]);
    }

} elseif ($rol !== "ADMINISTRADOR") {

    responder(403, ["success" => false, "mensaje" => "No tienes permiso para cancelar citas."]);
}

// Comprobar límite de cancelación.
if ($rol !== "ADMINISTRADOR") {

    $fechaCita = new DateTime($cita["fecha"] . " " . $cita["hora_inicio"]);
    $ahora = new DateTime();

    $horasRestantes = (
        $fechaCita->getTimestamp() - $ahora->getTimestamp()
    ) / 3600;

    if ($horasRestantes < (int)$cita["horas_limite_cancelacion"]) {
        responder(409, [
            "success" => false,
            "mensaje" => "Se superó el límite permitido para cancelar esta cita."
        ]);
    }
}

$stmt = $conexion->prepare(
    "UPDATE CITAS SET estado = 'CANCELADA'
     WHERE id_cita = ?
       AND estado IN ('PENDIENTE', 'CONFIRMADA')"
);

$stmt->bind_param("i", $idCita);
$stmt->execute();

if ($stmt->affected_rows !== 1) {
    responder(409, ["success" => false, "mensaje" => "No se pudo cancelar la cita."]);
}

responder(200, [
    "success" => true,
    "mensaje" => "Cita cancelada correctamente."
]);

?>
