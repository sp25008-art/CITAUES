
<?php

header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . "/../../config/conexion.php";
require_once __DIR__ . "/../../auth/permisos.php";

date_default_timezone_set("America/El_Salvador");

function responder($codigo, $datos) {
    http_response_code($codigo);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit();
}

verificarSesion();

if ($_SESSION["rol"] !== "ESTUDIANTE") {
    responder(403, [
        "success" => false,
        "mensaje" => "Solo los estudiantes pueden solicitar citas."
    ]);
}

exigirPermiso("citas.solicitar");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    responder(405, [
        "success" => false,
        "mensaje" => "Método no permitido."
    ]);
}

// Recibir datos.
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

if (!$idDisponibilidad || $idDisponibilidad <= 0 || !$horaInicio || !$motivo) {
    responder(400, [
        "success" => false,
        "mensaje" => "Debe completar todos los campos."
    ]);
}

if (mb_strlen($motivo) > 5000) {
    responder(400, [
        "success" => false,
        "mensaje" => "El motivo es demasiado extenso."
    ]);
}

// Validar formato de hora.
$horaObj = DateTime::createFromFormat("!H:i", $horaInicio);

if (!$horaObj || $horaObj->format("H:i") !== $horaInicio) {
    responder(400, [
        "success" => false,
        "mensaje" => "La hora seleccionada no es válida."
    ]);
}

$horaInicio = $horaObj->format("H:i:s");

$idEstudiante = (int)$_SESSION["id_usuario"];

try {

    $conexion->begin_transaction();

    // 1. Consultar y bloquear disponibilidad.
    $sql = "SELECT
                d.id_disponibilidad,
                d.fecha,
                d.hora_inicio,
                d.hora_fin,
                d.cupo_maximo,
                d.estado AS estado_disponibilidad,
                ps.estado AS estado_asignacion,
                s.id_servicio,
                s.nombre AS servicio,
                s.duracion_minutos,
                s.anticipacion_minima_horas,
                s.estado AS estado_servicio,
                u.estado AS estado_profesional
            FROM DISPONIBILIDADES d
            INNER JOIN PROFESIONAL_SERVICIO ps
                ON d.id_profesional_servicio = ps.id_profesional_servicio
            INNER JOIN SERVICIOS s
                ON ps.id_servicio = s.id_servicio
            INNER JOIN USUARIOS u
                ON ps.id_usuario = u.id_usuario
            WHERE d.id_disponibilidad = ?
            FOR UPDATE";

    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("i", $idDisponibilidad);
    $stmt->execute();

    $disponibilidad = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (
        !$disponibilidad ||
        $disponibilidad["estado_disponibilidad"] !== "DISPONIBLE" ||
        $disponibilidad["estado_asignacion"] !== "ACTIVO" ||
        $disponibilidad["estado_servicio"] !== "ACTIVO" ||
        $disponibilidad["estado_profesional"] !== "ACTIVO"
    ) {
        throw new Exception(
            "La disponibilidad seleccionada ya no está disponible."
        );
    }

    // 2. Calcular hora final según duración del servicio.
    $inicio = new DateTime(
        $disponibilidad["fecha"] . " " . $horaInicio
    );

    $fin = clone $inicio;

    $duracion = (int)$disponibilidad["duracion_minutos"];

    if ($duracion <= 0) {
        throw new Exception("La duración del servicio no es válida.");
    }

    $fin->modify("+{$duracion} minutes");

    $horaFin = $fin->format("H:i:s");

    // 3. Validar que el horario esté dentro del bloque disponible.
    $horaBloqueInicio = $disponibilidad["hora_inicio"];
    $horaBloqueFin = $disponibilidad["hora_fin"];

    if (
        $horaInicio < $horaBloqueInicio ||
        $horaFin > $horaBloqueFin
    ) {
        throw new Exception(
            "El horario seleccionado está fuera del bloque disponible."
        );
    }

    // 4. Validar intervalo según duración.
    $inicioBloque = new DateTime(
        $disponibilidad["fecha"] . " " . $horaBloqueInicio
    );

    $diferencia = (
        $inicio->getTimestamp() - $inicioBloque->getTimestamp()
    ) / 60;

    if ($diferencia < 0 || $diferencia % $duracion !== 0) {
        throw new Exception(
            "El horario seleccionado no corresponde a un intervalo válido."
        );
    }

    // 5. Comprobar anticipación mínima.
    $ahora = new DateTime();

    $horasAnticipacion = (int)$disponibilidad["anticipacion_minima_horas"];

    $minimoPermitido = clone $ahora;
    $minimoPermitido->modify("+{$horasAnticipacion} hours");

    if ($inicio < $minimoPermitido) {
        throw new Exception(
            "Debes solicitar la cita con al menos " .
            "{$horasAnticipacion} horas de anticipación."
        );
    }

    // 6. Evitar reservas duplicadas del mismo estudiante.
    // Se comprueba el mismo servicio, fecha e intervalo de tiempo.
    $sqlDuplicada = "SELECT COUNT(*) AS total
                     FROM CITAS c
                     INNER JOIN DISPONIBILIDADES d
                        ON c.id_disponibilidad = d.id_disponibilidad
                     INNER JOIN PROFESIONAL_SERVICIO ps
                        ON d.id_profesional_servicio = ps.id_profesional_servicio
                     WHERE c.id_estudiante = ?
                       AND ps.id_servicio = ?
                       AND d.fecha = ?
                       AND c.estado IN ('PENDIENTE', 'CONFIRMADA')
                       AND c.hora_inicio < ?
                       AND c.hora_fin > ?";

    $stmtDuplicada = $conexion->prepare($sqlDuplicada);

    $stmtDuplicada->bind_param(
        "iisss",
        $idEstudiante,
        $disponibilidad["id_servicio"],
        $disponibilidad["fecha"],
        $horaFin,
        $horaInicio
    );

    $stmtDuplicada->execute();

    $totalDuplicadas = (int)$stmtDuplicada
        ->get_result()
        ->fetch_assoc()["total"];

    $stmtDuplicada->close();

    if ($totalDuplicadas > 0) {
        throw new Exception(
            "Ya tienes una reserva para este servicio en ese horario."
        );
    }

    // 7. Consultar cupos ocupados.
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

    $ocupados = (int)$stmt
        ->get_result()
        ->fetch_assoc()["ocupados"];

    $stmt->close();

    if ($ocupados >= (int)$disponibilidad["cupo_maximo"]) {
        throw new Exception(
            "Este horario ya no tiene cupos disponibles."
        );
    }

    // 8. Registrar la cita.
    $sqlInsertar = "INSERT INTO CITAS
                    (
                        id_estudiante,
                        id_disponibilidad,
                        estado,
                        motivo,
                        hora_inicio,
                        hora_fin
                    )
                    VALUES (?, ?, 'PENDIENTE', ?, ?, ?)";

    $stmt = $conexion->prepare($sqlInsertar);

    $stmt->bind_param(
        "iisss",
        $idEstudiante,
        $idDisponibilidad,
        $motivo,
        $horaInicio,
        $horaFin
    );

    if (!$stmt->execute()) {
        throw new Exception(
            "No fue posible registrar la cita."
        );
    }

    $idCita = $conexion->insert_id;
    $stmt->close();

    // 9. Registrar movimiento en el historial.
    $tipoCambio = "SOLICITUD";
    $estadoAnterior = null;
    $estadoNuevo = "PENDIENTE";
    $disponibilidadAnterior = null;
    $observacion = "Solicitud de cita registrada por el estudiante.";

    $sqlHistorial = "INSERT INTO HISTORIAL_CITAS
                     (
                         id_cita,
                         id_usuario,
                         tipo_cambio,
                         estado_anterior,
                         estado_nuevo,
                         id_disponibilidad_anterior,
                         id_disponibilidad_nueva,
                         observacion
                     )
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conexion->prepare($sqlHistorial);

    $stmt->bind_param(
        "iisssiis",
        $idCita,
        $idEstudiante,
        $tipoCambio,
        $estadoAnterior,
        $estadoNuevo,
        $disponibilidadAnterior,
        $idDisponibilidad,
        $observacion
    );

    if (!$stmt->execute()) {
        throw new Exception(
            "No fue posible registrar el historial de la cita."
        );
    }

    $stmt->close();

    // 10. Confirmar transacción.
    $conexion->commit();

    responder(201, [
        "success" => true,
        "mensaje" => "La solicitud de cita fue registrada correctamente.",
        "id_cita" => $idCita,
        "estado" => "PENDIENTE",
        "servicio" => $disponibilidad["servicio"],
        "fecha" => $disponibilidad["fecha"],
        "hora_inicio" => $horaInicio,
        "hora_fin" => $horaFin
    ]);

} catch (Throwable $e) {

    $conexion->rollback();

    responder(400, [
        "success" => false,
        "mensaje" => $e->getMessage()
    ]);
}

?>
