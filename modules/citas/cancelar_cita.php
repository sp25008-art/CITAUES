
<?php

header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . "/../../config/conexion.php";
require_once __DIR__ . "/../../auth/permisos.php";
require_once __DIR__ . "/funciones_historial.php";

date_default_timezone_set("America/El_Salvador");

function responder($codigo, $datos) {
    http_response_code($codigo);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit();
}

// 1. Verificar sesión.
verificarSesion();

$rol = $_SESSION["rol"];
$idUsuario = (int)$_SESSION["id_usuario"];

// 2. Validar método.
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    responder(405, [
        "success" => false,
        "mensaje" => "Método no permitido."
    ]);
}

// 3. Validar permisos generales.
if ($rol === "ESTUDIANTE") {
    exigirPermiso("citas.cancelar_propias");
} elseif ($rol === "PROFESIONAL") {
    exigirPermiso("citas.gestionar_asignadas");
} elseif ($rol === "COORDINADOR") {
    exigirPermiso("citas.consultar_unidad");
} elseif ($rol !== "ADMINISTRADOR") {
    responder(403, [
        "success" => false,
        "mensaje" => "No tienes permiso para cancelar citas."
    ]);
}

// 4. Obtener datos.
$datos = json_decode(file_get_contents("php://input"), true);

if (!is_array($datos)) {
    $datos = $_POST;
}

$idCita = filter_var(
    $datos["id_cita"] ?? null,
    FILTER_VALIDATE_INT
);

if (!$idCita || $idCita <= 0) {
    responder(400, [
        "success" => false,
        "mensaje" => "ID de cita inválido."
    ]);
}

$transaccionIniciada = false;

try {

    // 5. Iniciar transacción.
    $conexion->begin_transaction();
    $transaccionIniciada = true;

    // 6. Consultar y bloquear la cita.
    $sql = "SELECT
                c.id_cita,
                c.id_estudiante,
                c.id_disponibilidad,
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
            WHERE c.id_cita = ?
            FOR UPDATE";

    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("i", $idCita);
    $stmt->execute();

    $cita = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$cita) {
        throw new Exception("Cita no encontrada.");
    }

    // 7. Verificar estado de la cita.
    if (!in_array(
        $cita["estado"],
        ["PENDIENTE", "CONFIRMADA"],
        true
    )) {
        throw new Exception(
            "Esta cita no puede cancelarse porque su estado actual es " .
            $cita["estado"] . "."
        );
    }

    // Guardar estado anterior para el historial.
    $estadoAnterior = $cita["estado"];

    // 8. Verificar autorización según el rol.
    if ($rol === "ESTUDIANTE") {

        if ((int)$cita["id_estudiante"] !== $idUsuario) {
            throw new Exception(
                "Solo puedes cancelar tus propias citas."
            );
        }

    } elseif ($rol === "PROFESIONAL") {

        if ((int)$cita["id_profesional"] !== $idUsuario) {
            throw new Exception(
                "No puedes cancelar citas de otros profesionales."
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

    // El administrador conserva acceso general.

    // 9. Comprobar límite de cancelación.
    if ($rol !== "ADMINISTRADOR") {

        $fechaCita = new DateTime(
            $cita["fecha"] . " " . $cita["hora_inicio"]
        );

        $ahora = new DateTime();

        $horasRestantes = (
            $fechaCita->getTimestamp() -
            $ahora->getTimestamp()
        ) / 3600;

        $limiteCancelacion = (int)$cita["horas_limite_cancelacion"];

        if ($horasRestantes < $limiteCancelacion) {
            throw new Exception(
                "Se superó el límite permitido para cancelar esta cita. " .
                "Debes cancelar con al menos " .
                $limiteCancelacion . " horas de anticipación."
            );
        }
    }

    // 10. Actualizar estado de la cita.
    $sql = "UPDATE CITAS
            SET estado = 'CANCELADA'
            WHERE id_cita = ?
              AND estado IN ('PENDIENTE', 'CONFIRMADA')";

    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("i", $idCita);

    if (!$stmt->execute() || $stmt->affected_rows !== 1) {
        $stmt->close();

        throw new Exception(
            "No se pudo cancelar la cita."
        );
    }

    $stmt->close();

    // 11. Registrar movimiento en el historial.
    $idDisponibilidad = (int)$cita["id_disponibilidad"];

    registrarHistorial(
        $conexion,
        $idCita,
        $idUsuario,
        "CANCELACION",
        $estadoAnterior,
        "CANCELADA",
        $idDisponibilidad,
        $idDisponibilidad,
        "Cita cancelada por el usuario."
    );

    // 12. Confirmar transacción.
    $conexion->commit();
    $transaccionIniciada = false;

    responder(200, [
        "success" => true,
        "mensaje" => "Cita cancelada correctamente.",
        "id_cita" => $idCita,
        "estado_anterior" => $estadoAnterior,
        "estado_nuevo" => "CANCELADA"
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
