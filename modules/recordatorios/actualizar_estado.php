
<?php
session_start();

require_once __DIR__ . "/../../config/conexion.php";
require_once __DIR__ . "/../../auth/verificar_sesion.php";

header("Content-Type: application/json; charset=utf-8");

function responder($codigo, $datos) {
    http_response_code($codigo);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    verificarSesion();

    if ($_SESSION["rol"] !== "ADMINISTRADOR") {
        responder(403, [
            "success" => false,
            "mensaje" => "Solo el administrador puede actualizar recordatorios."
        ]);
    }

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

    $id = (int)($datos["id_recordatorio"] ?? 0);
    $estado = $datos["estado"] ?? "";

    if (
        $id <= 0 ||
        !in_array($estado, ["PENDIENTE", "ENVIADO", "ERROR"], true)
    ) {
        throw new Exception("Datos inválidos.");
    }

    // Verificar que el recordatorio exista y que su cita
    // todavía permita gestionar el recordatorio.
    $sql = "SELECT
                r.id_recordatorio,
                c.estado AS estado_cita
            FROM RECORDATORIOS r
            INNER JOIN CITAS c ON r.id_cita = c.id_cita
            WHERE r.id_recordatorio = ?";

    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();

    $recordatorio = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$recordatorio) {
        throw new Exception("El recordatorio no existe.");
    }

    if (!in_array($recordatorio["estado_cita"], ["PENDIENTE", "CONFIRMADA"], true)) {
        throw new Exception("No puedes actualizar recordatorios de citas finalizadas o canceladas.");
    }

    if ($estado === "ENVIADO") {
        $sql = "UPDATE RECORDATORIOS
                SET estado = ?, fecha_envio = NOW()
                WHERE id_recordatorio = ?";
    } else {
        $sql = "UPDATE RECORDATORIOS
                SET estado = ?, fecha_envio = NULL
                WHERE id_recordatorio = ?";
    }

    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("si", $estado, $id);

    if (!$stmt->execute()) {
        throw new Exception("No se pudo actualizar el recordatorio.");
    }

    $stmt->close();

    responder(200, [
        "success" => true,
        "mensaje" => "Estado del recordatorio actualizado correctamente."
    ]);

} catch (Throwable $e) {
    responder(400, [
        "success" => false,
        "mensaje" => $e->getMessage()
    ]);
}
