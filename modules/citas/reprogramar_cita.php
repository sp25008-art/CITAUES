
<?php

header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . "/../../config/conexion.php";
require_once __DIR__ . "/../../auth/permisos.php";
require_once __DIR__ . "/funciones_historial.php";

date_default_timezone_set("America/El_Salvador");

/**
 * Devuelve una respuesta JSON y finaliza la ejecución.
 */
function responder($codigo, $datos) {
    http_response_code($codigo);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit();
}

// 1. Verificar sesión sin redireccionar, porque es una petición AJAX.
if (
    !isset($_SESSION["id_usuario"]) ||
    !isset($_SESSION["rol"])
) {
    responder(401, [
        "success" => false,
        "mensaje" => "Debes iniciar sesión para realizar esta operación."
    ]);
}

$rol = $_SESSION["rol"];
$idUsuario = (int)$_SESSION["id_usuario"];

// 2. Verificar permisos según el rol.
if ($rol === "ESTUDIANTE") {
    exigirPermiso("citas.consultar_propias");

} elseif ($rol === "PROFESIONAL") {
    exigirPermiso("citas.gestionar_asignadas");

} elseif ($rol === "COORDINADOR") {
    exigirPermiso("citas.consultar_unidad");

} elseif ($rol !== "ADMINISTRADOR") {
    responder(403, [
        "success" => false,
        "mensaje" => "No tienes permiso para reprogramar citas."
    ]);
}

// 3. Validar método HTTP.
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    responder(405, [
        "success" => false,
        "mensaje" => "Método no permitido."
    ]);
}

// 4. Obtener datos enviados.
$datos = json_decode(file_get_contents("php://input"), true);

if (!is_array($datos)) {
    $datos = $_POST;
}

$idCita = filter_var(
    $datos["id_cita"] ?? null,
    FILTER_VALIDATE_INT
);

$idNuevaDisponibilidad = filter_var(
    $datos["id_disponibilidad"] ?? null,
    FILTER_VALIDATE_INT
);

$nuevaHora = trim($datos["hora_inicio"] ?? "");

if (
    !$idCita || $idCita <= 0 ||
    !$idNuevaDisponibilidad || $idNuevaDisponibilidad <= 0 ||
    $nuevaHora === ""
) {
    responder(400, [
        "success" => false,
        "mensaje" => "Faltan datos para reprogramar la cita."
    ]);
}

// 5. Validar formato de hora.
$horaObjeto = DateTime::createFromFormat("!H:i", $nuevaHora);

if (
    !$horaObjeto ||
    $horaObjeto->format("H:i") !== $nuevaHora
) {
    responder(400, [
        "success" => false,
        "mensaje" => "La hora seleccionada no es válida."
    ]);
}

$horaInicio = $horaObjeto->format("H:i:s");

$transaccionIniciada = false;

try {

    // 6. Iniciar transacción.
    $conexion->begin_transaction();
    $transaccionIniciada = true;

    // 7. Consultar y bloquear la cita original.
    $sqlCita = "SELECT
                    c.id_cita,
                    c.id_estudiante,
                    c.id_disponibilidad,
                    c.estado,
                    c.hora_inicio,
                    s.horas_limite_cancelacion,
                    ps.id_usuario AS id_profesional,
                    ps.id_servicio AS id_servicio_original,
                    s.id_unidad,
                    d.fecha
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

    // 8. Guardar información original.
    $estadoAnterior = $cita["estado"];
    $idDisponibilidadAnterior = (int)$cita["id_disponibilidad"];

    if (!in_array(
        $estadoAnterior,
        ["PENDIENTE", "CONFIRMADA"],
        true
    )) {
        throw new Exception(
            "La cita no puede reprogramarse en su estado actual."
        );
    }

    // 9. Verificar autorización sobre la cita original.
    if ($rol === "ESTUDIANTE") {

        if ((int)$cita["id_estudiante"] !== $idUsuario) {
            throw new Exception(
                "Solo puedes reprogramar tus propias citas."
            );
        }

    } elseif ($rol === "PROFESIONAL") {

        if ((int)$cita["id_profesional"] !== $idUsuario) {
            throw new Exception(
                "No puedes reprogramar citas de otros profesionales."
            );
        }

    } elseif ($rol === "COORDINADOR") {

        if (
            (int)$cita["id_unidad"] !==
            (int)$_SESSION["id_unidad"]
        ) {
            throw new Exception(
                "La cita no pertenece a tu unidad."
            );
        }
    }

    // 10. Comprobar límite de reprogramación.
    if ($rol !== "ADMINISTRADOR") {

        $fechaOriginal = new DateTime(
            $cita["fecha"] . " " . $cita["hora_inicio"]
        );

        $ahora = new DateTime();

        $horasRestantes = (
            $fechaOriginal->getTimestamp() -
            $ahora->getTimestamp()
        ) / 3600;

        if (
            $horasRestantes <
            (int)$cita["horas_limite_cancelacion"]
        ) {
            throw new Exception(
                "Se superó el límite permitido para reprogramar la cita."
            );
        }
    }

    // 11. Consultar y bloquear nueva disponibilidad.
    $sqlNueva = "SELECT
                    d.id_disponibilidad,
                    d.fecha,
                    d.hora_inicio,
                    d.hora_fin,
                    d.cupo_maximo,
                    d.estado AS estado_disponibilidad,
                    ps.id_usuario AS id_profesional_nuevo,
                    ps.id_servicio AS id_servicio_nuevo,
                    ps.estado AS estado_asignacion,
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

    // 12. Verificar que la nueva disponibilidad esté activa.
    if (
        !$nueva ||
        $nueva["estado_disponibilidad"] !== "DISPONIBLE" ||
        $nueva["estado_servicio"] !== "ACTIVO" ||
        $nueva["estado_asignacion"] !== "ACTIVO"
    ) {
        throw new Exception(
            "La nueva disponibilidad, servicio o asignación no están disponibles."
        );
    }

    // 13. Impedir cambiar de servicio.
    if (
        (int)$cita["id_servicio_original"] !==
        (int)$nueva["id_servicio_nuevo"]
    ) {
        throw new Exception(
            "Solo puedes reprogramar la cita dentro del mismo servicio."
        );
    }

    // 14. Comprobar autorización sobre nueva disponibilidad.
    if ($rol === "PROFESIONAL") {

        if (
            (int)$nueva["id_profesional_nuevo"] !== $idUsuario
        ) {
            throw new Exception(
                "No puedes asignar la cita a la disponibilidad de otro profesional."
            );
        }

    } elseif ($rol === "COORDINADOR") {

        if (
            (int)$nueva["id_unidad"] !==
            (int)$_SESSION["id_unidad"]
        ) {
            throw new Exception(
                "La nueva disponibilidad no pertenece a tu unidad."
            );
        }
    }

    // 15. Calcular nuevo horario.
    $inicio = new DateTime(
        $nueva["fecha"] . " " . $horaInicio
    );

    $fin = clone $inicio;

    $duracion = (int)$nueva["duracion_minutos"];

    if ($duracion <= 0) {
        throw new Exception(
            "La duración del servicio no es válida."
        );
    }

    $fin->modify("+" . $duracion . " minutes");

    $horaFin = $fin->format("H:i:s");

    // 16. Comprobar que la cita cabe en el horario disponible.
    if (
        $horaInicio < $nueva["hora_inicio"] ||
        $horaFin > $nueva["hora_fin"]
    ) {
        throw new Exception(
            "La nueva cita excede el horario disponible."
        );
    }

    // 17. Comprobar intervalo permitido.
    $inicioDisponibilidad = new DateTime(
        $nueva["fecha"] . " " . $nueva["hora_inicio"]
    );

    $segundosDesdeInicio =
        $inicio->getTimestamp() -
        $inicioDisponibilidad->getTimestamp();

    if (
        $segundosDesdeInicio < 0 ||
        $segundosDesdeInicio % ($duracion * 60) !== 0
    ) {
        throw new Exception(
            "La hora seleccionada no corresponde a un intervalo permitido."
        );
    }

    // 18. Comprobar anticipación mínima.
    $ahora = new DateTime();

    $horasAnticipacion = (
        $inicio->getTimestamp() -
        $ahora->getTimestamp()
    ) / 3600;

    if (
        $horasAnticipacion <
        (int)$nueva["anticipacion_minima_horas"]
    ) {
        throw new Exception(
            "No se cumple la anticipación mínima para reservar este horario."
        );
    }

    // 19. Evitar citas superpuestas del estudiante.
    $sqlDuplicada = "SELECT c.id_cita
                     FROM CITAS c
                     INNER JOIN DISPONIBILIDADES d
                        ON c.id_disponibilidad = d.id_disponibilidad
                     WHERE c.id_estudiante = ?
                       AND c.id_cita <> ?
                       AND d.fecha = ?
                       AND c.estado IN ('PENDIENTE', 'CONFIRMADA')
                       AND c.hora_inicio < ?
                       AND c.hora_fin > ?
                     LIMIT 1";

    $stmt = $conexion->prepare($sqlDuplicada);

    $stmt->bind_param(
        "iisss",
        $cita["id_estudiante"],
        $idCita,
        $nueva["fecha"],
        $horaFin,
        $horaInicio
    );

    $stmt->execute();

    $duplicada = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($duplicada) {
        throw new Exception(
            "El estudiante ya tiene otra cita en ese horario."
        );
    }

    // 20. Comprobar capacidad.
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
        $horaInicio
    );

    $stmt->execute();

    $ocupados = (int)$stmt->get_result()->fetch_assoc()["ocupados"];
    $stmt->close();

    if ($ocupados >= (int)$nueva["cupo_maximo"]) {
        throw new Exception(
            "No hay cupos disponibles en el nuevo horario."
        );
    }

    // 21. Determinar nuevo estado.
    $nuevoEstado = $nueva["metodo_confirmacion"] === "AUTOMATICA"
        ? "CONFIRMADA"
        : "PENDIENTE";

    // 22. Actualizar cita.
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
        $horaInicio,
        $horaFin,
        $nuevoEstado,
        $idCita
    );

    if (!$stmt->execute()) {
        $stmt->close();

        throw new Exception(
            "No fue posible reprogramar la cita."
        );
    }

    $stmt->close();

    // 23. Registrar movimiento en el historial.
    registrarHistorial(
        $conexion,
        $idCita,
        $idUsuario,
        "REPROGRAMACION",
        $estadoAnterior,
        $nuevoEstado,
        $idDisponibilidadAnterior,
        $idNuevaDisponibilidad,
        "Cita reprogramada para el " .
        $nueva["fecha"] . " a las " . $horaInicio
    );

    // 24. Confirmar transacción.
    $conexion->commit();
    $transaccionIniciada = false;

    responder(200, [
        "success" => true,
        "mensaje" => "Cita reprogramada correctamente.",
        "id_cita" => $idCita,
        "estado_anterior" => $estadoAnterior,
        "nuevo_estado" => $nuevoEstado,
        "disponibilidad_anterior" => $idDisponibilidadAnterior,
        "nueva_disponibilidad" => $idNuevaDisponibilidad,
        "nueva_fecha" => $nueva["fecha"],
        "hora_inicio" => $horaInicio,
        "hora_fin" => $horaFin
    ]);

} catch (Throwable $e) {

    if ($transaccionIniciada) {
        $conexion->rollback();
    }

    $codigo = $e instanceof mysqli_sql_exception ? 500 : 400;

    responder($codigo, [
        "success" => false,
        "mensaje" => $e->getMessage()
    ]);
}

?>
