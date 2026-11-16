
<?php

header("Content-Type: application/json; charset=UTF-8");

session_start();

require_once __DIR__ . "/../../config/conexion.php";
require_once __DIR__ . "/../../auth/permisos.php";
require_once __DIR__ . "/../citas/funciones_historial.php";

function responder($codigo, $datos) {
    http_response_code($codigo);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit();
}

$transaccionIniciada = false;

try {

    verificarSesion();
    exigirPermiso("asistencias.registrar");

    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        responder(405, [
            "success" => false,
            "mensaje" => "Método no permitido."
        ]);
    }

    $datos = json_decode(file_get_contents("php://input"), true);

    if (!is_array($datos)) {
        $datos = $_POST;
    }

    $idCita = filter_var(
        $datos["id_cita"] ?? null,
        FILTER_VALIDATE_INT
    );

    $estado = $datos["estado_asistencia"] ?? "";
    $observacion = trim($datos["observacion"] ?? "");

    if (
        !$idCita ||
        $idCita <= 0 ||
        !in_array($estado, ["ASISTIO", "NO_ASISTIO"], true)
    ) {
        responder(400, [
            "success" => false,
            "mensaje" => "Los datos proporcionados no son válidos."
        ]);
    }

    $rol = $_SESSION["rol"];
    $idUsuario = (int)$_SESSION["id_usuario"];

    if (!in_array($rol, ["PROFESIONAL", "ADMINISTRADOR"], true)) {
        responder(403, [
            "success" => false,
            "mensaje" => "No tienes permisos para registrar asistencias."
        ]);
    }

    $conexion->begin_transaction();
    $transaccionIniciada = true;

    // 1. Consultar y bloquear la cita.
    $sql = "SELECT
                c.id_cita,
                c.estado,
                ps.id_usuario AS id_profesional
            FROM CITAS c
            INNER JOIN DISPONIBILIDADES d
                ON c.id_disponibilidad = d.id_disponibilidad
            INNER JOIN PROFESIONAL_SERVICIO ps
                ON d.id_profesional_servicio = ps.id_profesional_servicio
            WHERE c.id_cita = ?
            FOR UPDATE";

    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("i", $idCita);
    $stmt->execute();

    $cita = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$cita) {
        throw new Exception("La cita no existe.");
    }

    // 2. Verificar permisos sobre la cita.
    $idProfesionalAsignado = (int)$cita["id_profesional"];

    if (
        $rol === "PROFESIONAL" &&
        $idProfesionalAsignado !== $idUsuario
    ) {
        throw new Exception(
            "No puedes registrar asistencia de esta cita."
        );
    }

    // 3. Solo se admiten citas confirmadas.
    if ($cita["estado"] !== "CONFIRMADA") {
        throw new Exception(
            "Solo se puede registrar asistencia de citas confirmadas."
        );
    }

    // 4. Registrar asistencia.
    $sql = "INSERT INTO ASISTENCIAS
            (
                id_cita,
                id_profesional,
                estado_asistencia,
                observacion
            )
            VALUES (?, ?, ?, ?)";

    $stmt = $conexion->prepare($sql);
    $stmt->bind_param(
        "iiss",
        $idCita,
        $idProfesionalAsignado,
        $estado,
        $observacion
    );

    if (!$stmt->execute()) {
        throw new Exception("No se pudo registrar la asistencia.");
    }

    $stmt->close();

    // 5. Finalizar la cita.
    $sql = "UPDATE CITAS
            SET estado = 'FINALIZADA'
            WHERE id_cita = ?
              AND estado = 'CONFIRMADA'";

    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("i", $idCita);

    if (!$stmt->execute() || $stmt->affected_rows !== 1) {
        $stmt->close();

        throw new Exception("No se pudo finalizar la cita.");
    }

    $stmt->close();

    // 6. Registrar el movimiento en el historial.
    $detalle = $estado === "ASISTIO"
        ? "Se registró la asistencia del estudiante."
        : "Se registró que el estudiante no asistió.";

    if ($observacion !== "") {
        $detalle .= " Observación: " . $observacion;
    }

    registrarHistorial(
        $conexion,
        $idCita,
        $idUsuario,
        "ASISTENCIA",
        "CONFIRMADA",
        "FINALIZADA",
        null,
        null,
        $detalle
    );

    // 7. Confirmar las tres operaciones.
    $conexion->commit();
    $transaccionIniciada = false;

    responder(200, [
        "success" => true,
        "mensaje" => "Asistencia registrada, cita finalizada e historial actualizado correctamente.",
        "id_cita" => $idCita,
        "estado_asistencia" => $estado,
        "estado_cita" => "FINALIZADA"
    ]);

} catch (Throwable $e) {

    if ($transaccionIniciada) {
        $conexion->rollback();
    }

    responder(400, [
        "success" => false,
        "mensaje" => $e->getMessage()
    ]);
}
?>
