
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
    responder(405, [
        "success" => false,
        "mensaje" => "Método no permitido."
    ]);
}

exigirPermiso("citas.solicitar");

$datos = json_decode(file_get_contents("php://input"), true);
if (!is_array($datos)) {
    $datos = $_POST;
}

$idDisponibilidad = filter_var(
    $datos["id_disponibilidad"] ?? null,
    FILTER_VALIDATE_INT
);

$horaInicio = trim($datos["hora_inicio"] ?? "");
$motivo = trim($datos["motivo"] ?? "");

if (!$idDisponibilidad || $horaInicio === "") {
    responder(400, [
        "success" => false,
        "mensaje" => "Debe seleccionar una disponibilidad y una hora."
    ]);
}

$horaObjeto = DateTime::createFromFormat("!H:i", $horaInicio);

if (!$horaObjeto || $horaObjeto->format("H:i") !== $horaInicio) {
    responder(400, [
        "success" => false,
        "mensaje" => "La hora seleccionada no es válida."
    ]);
}

$idEstudiante = (int)$_SESSION["id_usuario"];

try {

    $conexion->begin_transaction();

    // Consultar y bloquear la disponibilidad.
    $sql = "SELECT
                d.id_disponibilidad,
                d.fecha,
                d.hora_inicio,
                d.hora_fin,
                d.cupo_maximo,
                d.estado AS estado_disponibilidad,
                s.id_servicio,
                s.nombre AS servicio,
                s.duracion_minutos,
                s.metodo_confirmacion,
                s.anticipacion_minima_horas,
                s.estado AS estado_servicio
            FROM DISPONIBILIDADES d
            INNER JOIN PROFESIONAL_SERVICIO ps
                ON d.id_profesional_servicio = ps.id_profesional_servicio
            INNER JOIN SERVICIOS s
                ON ps.id_servicio = s.id_servicio
            WHERE d.id_disponibilidad = ?
            FOR UPDATE";

    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("i", $idDisponibilidad);
    $stmt->execute();

    $disponibilidad = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (
        !$disponibilidad ||
        $disponibilidad["estado_disponibilidad"] !== "ACTIVO" ||
        $disponibilidad["estado_servicio"] !== "ACTIVO"
    ) {
        throw new Exception("La disponibilidad o el servicio no están disponibles.");
    }

    $fecha = $disponibilidad["fecha"];
    $duracion = (int)$disponibilidad["duracion_minutos"];

    // Calcular hora final según duración del servicio.
    $inicio = new DateTime("$fecha $horaInicio");
    $fin = clone $inicio;
    $fin->modify("+$duracion minutes");

    $horaFin = $fin->format("H:i:s");

    // Comprobar que la cita cabe en el horario disponible.
    if (
        $horaInicio < $disponibilidad["hora_inicio"] ||
        $horaFin > $disponibilidad["hora_fin"]
    ) {
        throw new Exception("La cita excede el horario disponible.");
    }

    // Comprobar anticipación mínima.
    $ahora = new DateTime();

    $horasDisponibles = ($inicio->getTimestamp() - $ahora->getTimestamp()) / 3600;

    if ($horasDisponibles < (int)$disponibilidad["anticipacion_minima_horas"]) {
        throw new Exception("La cita no cumple con la anticipación mínima requerida.");
    }

    // Evitar reservas superpuestas del mismo estudiante.
    $sqlDuplicada = "SELECT id_cita
                     FROM CITAS
                     WHERE id_estudiante = ?
                       AND fecha = ?
                       AND estado IN ('PENDIENTE', 'CONFIRMADA')
                       AND hora_inicio < ?
                       AND hora_fin > ?
                     LIMIT 1";

    $stmt = $conexion->prepare($sqlDuplicada);
    $stmt->bind_param(
        "isss",
        $idEstudiante,
        $fecha,
        $horaFin,
        $horaInicio
    );
    $stmt->execute();

    if ($stmt->get_result()->fetch_assoc()) {
        throw new Exception("Ya tienes otra cita reservada en ese horario.");
    }
    $stmt->close();

    // Comprobar capacidad de la disponibilidad.
    $sqlCupo = "SELECT COUNT(*) AS ocupados
                FROM CITAS
                WHERE id_disponibilidad = ?
                  AND estado IN ('PENDIENTE', 'CONFIRMADA')
                  AND hora_inicio < ?
                  AND hora_fin > ?";

    $stmt = $conexion->prepare($sqlCupo);
    $stmt->bind_param(
        "iss",
        $idDisponibilidad,
        $horaFin,
        $horaInicio
    );
    $stmt->execute();

    $ocupados = (int)$stmt->get_result()->fetch_assoc()["ocupados"];
    $stmt->close();

    if ($ocupados >= (int)$disponibilidad["cupo_maximo"]) {
        throw new Exception("No hay cupos disponibles para ese horario.");
    }

    // Determinar el estado inicial.
    $estado = $disponibilidad["metodo_confirmacion"] === "AUTOMATICA"
        ? "CONFIRMADA"
        : "PENDIENTE";

    // Registrar cita.
    $sqlInsertar = "INSERT INTO CITAS
                    (id_estudiante, id_disponibilidad,
                     fecha_solicitud, estado, motivo,
                     hora_inicio, hora_fin)
                    VALUES (?, ?, NOW(), ?, ?, ?, ?)";

    $stmt = $conexion->prepare($sqlInsertar);
    $stmt->bind_param(
        "iissss",
        $idEstudiante,
        $idDisponibilidad,
        $estado,
        $motivo,
        $horaInicio,
        $horaFin
    );

    if (!$stmt->execute()) {
        throw new Exception("No fue posible registrar la cita.");
    }

    $idCita = $conexion->insert_id;
    $stmt->close();

    $conexion->commit();

    responder(201, [
        "success" => true,
        "mensaje" => "Solicitud de cita registrada correctamente.",
        "id_cita" => $idCita,
        "estado" => $estado,
        "hora_inicio" => $horaInicio,
        "hora_fin" => $horaFin
    ]);

} catch (Throwable $e) {

    $conexion->rollback();

    $codigo = $e instanceof Exception ? 400 : 500;

    responder($codigo, [
        "success" => false,
        "mensaje" => $e->getMessage()
    ]);
}

?>
