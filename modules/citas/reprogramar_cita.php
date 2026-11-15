
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
$idNuevaDisponibilidad = filter_var(
    $datos["id_disponibilidad"] ?? null,
    FILTER_VALIDATE_INT
);
$nuevaHora = trim($datos["hora_inicio"] ?? "");

if (!$idCita || !$idNuevaDisponibilidad || !$nuevaHora) {
    responder(400, ["success" => false, "mensaje" => "Faltan datos para reprogramar la cita."]);
}

$horaObjeto = DateTime::createFromFormat("!H:i", $nuevaHora);

if (!$horaObjeto || $horaObjeto->format("H:i") !== $nuevaHora) {
    responder(400, ["success" => false, "mensaje" => "La hora seleccionada no es válida."]);
}

$rol = $_SESSION["rol"];

try {

    $conexion->begin_transaction();

    // Bloquear la cita original.
    $sqlCita = "SELECT
                    c.id_cita,
                    c.id_estudiante,
                    c.id_disponibilidad,
                    c.estado,
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
                WHERE c.id_cita = ?
                FOR UPDATE";

    $stmt = $conexion->prepare($sqlCita);
    $stmt->bind_param("i", $idCita);
    $stmt->execute();
    $cita = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$cita) {
        throw new Exception("La cita no existe.");
    }

    if (!in_array($cita["estado"], ["PENDIENTE", "CONFIRMADA"], true)) {
        throw new Exception("La cita no puede reprogramarse en su estado actual.");
    }

    // Verificar autorización.
    if ($rol === "ESTUDIANTE") {

        if ((int)$cita["id_estudiante"] !== (int)$_SESSION["id_usuario"]) {
            throw new Exception("Solo puedes reprogramar tus propias citas.");
        }

    } elseif ($rol === "PROFESIONAL") {

        if ((int)$cita["id_profesional"] !== (int)$_SESSION["id_usuario"]) {
            throw new Exception("No puedes reprogramar citas de otros profesionales.");
        }

    } elseif ($rol === "COORDINADOR") {

        if ((int)$cita["id_unidad"] !== (int)$_SESSION["id_unidad"]) {
            throw new Exception("La cita no pertenece a tu unidad.");
        }

    } elseif ($rol !== "ADMINISTRADOR") {

        throw new Exception("No tienes permiso para reprogramar citas.");
    }

    // Aplicar límite de cancelación también a la reprogramación.
    if ($rol !== "ADMINISTRADOR") {

        $fechaOriginal = new DateTime(
            $cita["fecha"] ?? date("Y-m-d") . " " . $cita["hora_inicio"]
        );

        // Obtener la fecha real de la cita original.
        $stmtFecha = $conexion->prepare(
            "SELECT fecha FROM DISPONIBILIDADES WHERE id_disponibilidad = ?"
        );
        $stmtFecha->bind_param("i", $cita["id_disponibilidad"]);
        $stmtFecha->execute();
        $fechaOriginalDato = $stmtFecha->get_result()->fetch_assoc();
        $stmtFecha->close();

        $fechaOriginal = new DateTime(
            $fechaOriginalDato["fecha"] . " " . $cita["hora_inicio"]
        );

        $horasRestantes = (
            $fechaOriginal->getTimestamp() - time()
        ) / 3600;

        if ($horasRestantes < (int)$cita["horas_limite_cancelacion"]) {
            throw new Exception("Se superó el límite permitido para reprogramar la cita.");
        }
    }

    // Bloquear nueva disponibilidad y obtener servicio.
    $sqlNueva = "SELECT
                    d.id_disponibilidad,
                    d.fecha,
                    d.hora_inicio,
                    d.hora_fin,
                    d.cupo_maximo,
                    d.estado AS estado_disponibilidad,
                    s.duracion_minutos,
                    s.metodo_confirmacion,
                    s.anticipacion_minima_horas,
                    s.estado AS estado_servicio,
                    s.id_unidad
                FROM DISPONIBILIDADES d
                INNER JOIN PROFESIONAL_SERVICIO ps
                    ON d.id_profesional_servicio = ps.id_profesional_servicio
                INNER JOIN SERVICIOS s
                    ON ps.id_servicio = s.id_servicio
                WHERE d.id_disponibilidad = ?
                FOR UPDATE";

    $stmt = $conexion->prepare($sqlNueva);
    $stmt->bind_param("i", $idNuevaDisponibilidad);
    $stmt->execute();
    $nueva = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (
        !$nueva ||
        $nueva["estado_disponibilidad"] !== "ACTIVO" ||
        $nueva["estado_servicio"] !== "ACTIVO"
    ) {
        throw new Exception("La nueva disponibilidad no está activa.");
    }

    $inicio = new DateTime($nueva["fecha"] . " " . $nuevaHora);
    $fin = clone $inicio;
    $fin->modify("+" . (int)$nueva["duracion_minutos"] . " minutes");

    $horaFin = $fin->format("H:i:s");

    if (
        $nuevaHora < $nueva["hora_inicio"] ||
        $horaFin > $nueva["hora_fin"]
    ) {
        throw new Exception("La nueva cita excede el horario disponible.");
    }

    $horasAnticipacion = (
        $inicio->getTimestamp() - time()
    ) / 3600;

    if ($horasAnticipacion < (int)$nueva["anticipacion_minima_horas"]) {
        throw new Exception("No se cumple la anticipación mínima.");
    }

    // Evitar solapamiento con otras citas del estudiante.
    $sqlDuplicada = "SELECT id_cita
                     FROM CITAS
                     WHERE id_estudiante = ?
                       AND id_cita <> ?
                       AND estado IN ('PENDIENTE', 'CONFIRMADA')
                       AND hora_inicio < ?
                       AND hora_fin > ?
                       AND id_disponibilidad IN (
                           SELECT id_disponibilidad
                           FROM DISPONIBILIDADES
                           WHERE fecha = ?
                       )
                     LIMIT 1";

    $stmt = $conexion->prepare($sqlDuplicada);
    $stmt->bind_param(
        "iisss",
        $cita["id_estudiante"],
        $idCita,
        $horaFin,
        $nuevaHora,
        $nueva["fecha"]
    );
    $stmt->execute();

    if ($stmt->get_result()->fetch_assoc()) {
        throw new Exception("El estudiante ya tiene otra cita en ese horario.");
    }
    $stmt->close();

    // Comprobar cupo.
    $sqlCupo = "SELECT COUNT(*) AS ocupados
                FROM CITAS
                WHERE id_disponibilidad = ?
                  AND id_cita <> ?
                  AND estado IN ('PENDIENTE', 'CONFIRMADA')
                  AND hora_inicio < ?
                  AND hora_fin > ?";

    $stmt = $conexion->prepare($sqlCupo);
    $stmt->bind_param(
        "iiss",
        $idNuevaDisponibilidad,
        $idCita,
        $horaFin,
        $nuevaHora
    );
    $stmt->execute();

    $ocupados = (int)$stmt->get_result()->fetch_assoc()["ocupados"];
    $stmt->close();

    if ($ocupados >= (int)$nueva["cupo_maximo"]) {
        throw new Exception("No hay cupos disponibles en el nuevo horario.");
    }

    $nuevoEstado = $nueva["metodo_confirmacion"] === "AUTOMATICA"
        ? "CONFIRMADA"
        : "PENDIENTE";

    $sqlActualizar = "UPDATE CITAS
                      SET id_disponibilidad = ?,
                          hora_inicio = ?,
                          hora_fin = ?,
                          estado = ?
                      WHERE id_cita = ?";

    $stmt = $conexion->prepare($sqlActualizar);
    $stmt->bind_param(
        "isssi",
        $idNuevaDisponibilidad,
        $nuevaHora,
        $horaFin,
        $nuevoEstado,
        $idCita
    );

    if (!$stmt->execute()) {
        throw new Exception("No fue posible reprogramar la cita.");
    }

    $conexion->commit();

    responder(200, [
        "success" => true,
        "mensaje" => "Cita reprogramada correctamente.",
        "nuevo_estado" => $nuevoEstado,
        "nueva_fecha" => $nueva["fecha"],
        "hora_inicio" => $nuevaHora,
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
