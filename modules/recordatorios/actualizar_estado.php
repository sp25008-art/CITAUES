
<?php
session_start();

require_once __DIR__ . "/../../config/conexion.php";
require_once __DIR__ . "/../../auth/verificar_sesion.php";

header("Content-Type: application/json; charset=utf-8");

try {
    verificarSesion();

    if ($_SESSION["rol"] !== "ADMINISTRADOR") {
        throw new Exception("Solo el administrador puede actualizar recordatorios.");
    }

    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        throw new Exception("Método no permitido.");
    }

    $datos = json_decode(file_get_contents("php://input"), true);
    if (!is_array($datos)) {
        $datos = $_POST;
    }

    $id = (int)($datos["id_recordatorio"] ?? 0);
    $estado = $datos["estado"] ?? "";

    if ($id <= 0 || !in_array($estado, ["PENDIENTE", "ENVIADO", "ERROR"], true)) {
        throw new Exception("Datos inválidos.");
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

    if ($stmt->affected_rows === 0) {
        $stmt->close();
        throw new Exception("No se encontró el recordatorio o no hubo cambios.");
    }

    $stmt->close();

    echo json_encode([
        "success" => true,
        "mensaje" => "Estado del recordatorio actualizado."
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "mensaje" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
