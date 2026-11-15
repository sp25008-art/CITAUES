
<?php
session_start();

require_once __DIR__ . "/../../config/conexion.php";
require_once __DIR__ . "/../../auth/permisos.php";

header("Content-Type: application/json; charset=utf-8");

try {
    exigirPermiso("*");

    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        throw new Exception("Método no permitido.");
    }

    $datos = json_decode(file_get_contents("php://input"), true);
    if (!is_array($datos)) {
        $datos = $_POST;
    }

    $idCita = (int)($datos["id_cita"] ?? 0);
    $fechaProgramada = $datos["fecha_programada"] ?? "";
    $medio = $datos["medio"] ?? "SISTEMA";

    if ($idCita <= 0 || !in_array($medio, ["CORREO", "SISTEMA"], true)) {
        throw new Exception("Datos inválidos.");
    }

    $fecha = DateTime::createFromFormat("Y-m-d H:i:s", $fechaProgramada);

    if (!$fecha || $fecha->format("Y-m-d H:i:s") !== $fechaProgramada) {
        throw new Exception("Formato de fecha inválido. Utiliza YYYY-MM-DD HH:MM:SS.");
    }

    $stmt = $conexion->prepare(
        "SELECT id_cita FROM CITAS WHERE id_cita = ?"
    );
    $stmt->bind_param("i", $idCita);
    $stmt->execute();
    $existe = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$existe) {
        throw new Exception("La cita indicada no existe.");
    }

    $sql = "INSERT INTO RECORDATORIOS
            (id_cita, fecha_programada, medio, estado)
            VALUES (?, ?, ?, 'PENDIENTE')";

    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("iss", $idCita, $fechaProgramada, $medio);

    if (!$stmt->execute()) {
        throw new Exception("No se pudo registrar el recordatorio.");
    }

    $idRecordatorio = $conexion->insert_id;
    $stmt->close();

    echo json_encode([
        "success" => true,
        "mensaje" => "Recordatorio programado correctamente.",
        "id_recordatorio" => $idRecordatorio
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "mensaje" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
