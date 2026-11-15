
<?php
session_start();

require_once __DIR__ . "/../../config/conexion.php";
require_once __DIR__ . "/../../auth/permisos.php";

header("Content-Type: application/json; charset=utf-8");

try {
    exigirPermiso("asistencias.registrar");

    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        throw new Exception("Método no permitido.");
    }

    $datos = json_decode(file_get_contents("php://input"), true);
    if (!is_array($datos)) {
        $datos = $_POST;
    }

    $idCita = (int)($datos["id_cita"] ?? 0);
    $estado = $datos["estado_asistencia"] ?? "";
    $observacion = trim($datos["observacion"] ?? "");

    if ($idCita <= 0 || !in_array($estado, ["ASISTIO", "NO_ASISTIO"], true)) {
        throw new Exception("Datos inválidos.");
    }

    $conexion->begin_transaction();

    $sql = "SELECT c.id_cita, c.estado, ps.id_usuario AS id_profesional
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

    $rol = $_SESSION["rol"];
    $idUsuario = (int)$_SESSION["id_usuario"];

    if (
        $rol === "PROFESIONAL" &&
        (int)$cita["id_profesional"] !== $idUsuario
    ) {
        throw new Exception("No puedes registrar asistencia de esta cita.");
    }

    if (!in_array($rol, ["PROFESIONAL", "ADMINISTRADOR"], true)) {
        throw new Exception("No tienes permisos para registrar asistencias.");
    }

    if ($cita["estado"] !== "CONFIRMADA") {
        throw new Exception("Solo se puede registrar asistencia de citas confirmadas.");
    }

    $sql = "INSERT INTO ASISTENCIAS
            (id_cita, id_profesional, estado_asistencia, observacion)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                estado_asistencia = VALUES(estado_asistencia),
                observacion = VALUES(observacion),
                id_profesional = VALUES(id_profesional)";

    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("iiss", $idCita, $idUsuario, $estado, $observacion);

    if (!$stmt->execute()) {
        throw new Exception("No se pudo registrar la asistencia.");
    }

    $stmt->close();
    $conexion->commit();

    echo json_encode([
        "success" => true,
        "mensaje" => "Asistencia registrada correctamente."
    ]);

} catch (Throwable $e) {
    if (isset($conexion) && $conexion->errno === 0) {
        // La transacción se revierte en caso de error.
    }

    if (isset($conexion)) {
        $conexion->rollback();
    }

    http_response_code(400);
    echo json_encode([
        "success" => false,
        "mensaje" => $e->getMessage()
    ]);
}
