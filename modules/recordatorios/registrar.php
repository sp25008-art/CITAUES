
<?php
session_start();

require_once __DIR__ . "/../../config/conexion.php";
require_once __DIR__ . "/../../auth/verificar_sesion.php";

header("Content-Type: application/json; charset=utf-8");

function responder($codigo, $datos) {
    http_response_code($codigo);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit();
}

try {

    verificarSesion();

    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        responder(405, [
            "success" => false,
            "mensaje" => "Método no permitido."
        ]);
    }

    $rol = $_SESSION["rol"];
    $idUsuario = (int)$_SESSION["id_usuario"];
    $idUnidad = (int)($_SESSION["id_unidad"] ?? 0);

    if (!in_array($rol, [
        "ESTUDIANTE",
        "PROFESIONAL",
        "COORDINADOR",
        "ADMINISTRADOR"
    ], true)) {
        throw new Exception("No tienes permisos para programar recordatorios.");
    }

    $datos = json_decode(file_get_contents("php://input"), true);

    if (!is_array($datos)) {
        $datos = $_POST;
    }

    $idCita = filter_var(
        $datos["id_cita"] ?? null,
        FILTER_VALIDATE_INT
    );

    $fechaProgramada = trim($datos["fecha_programada"] ?? "");
    $medio = $datos["medio"] ?? "SISTEMA";

    if (!$idCita || $idCita <= 0) {
        throw new Exception("ID de cita inválido.");
    }

    if (!in_array($medio, ["CORREO", "SISTEMA"], true)) {
        throw new Exception("Medio de recordatorio inválido.");
    }

    // Aceptar formato de datetime-local y formato SQL.
    $fechaProgramada = str_replace("T", " ", $fechaProgramada);

    if (strlen($fechaProgramada) === 16) {
        $fechaProgramada .= ":00";
    }

    $fecha = DateTime::createFromFormat(
        "Y-m-d H:i:s",
        $fechaProgramada
    );

    if (
        !$fecha ||
        $fecha->format("Y-m-d H:i:s") !== $fechaProgramada
    ) {
        throw new Exception("Formato de fecha inválido.");
    }

    if ($fecha <= new DateTime()) {
        throw new Exception("La fecha debe ser futura.");
    }

    // Verificar que la cita exista y pertenezca al ámbito del usuario.
    $sql = "SELECT
                c.id_cita,
                c.estado,
                c.id_estudiante,
                ps.id_usuario AS id_profesional,
                s.id_unidad
            FROM CITAS c
            INNER JOIN DISPONIBILIDADES d
                ON c.id_disponibilidad = d.id_disponibilidad
            INNER JOIN PROFESIONAL_SERVICIO ps
                ON d.id_profesional_servicio = ps.id_profesional_servicio
            INNER JOIN SERVICIOS s
                ON ps.id_servicio = s.id_servicio
            WHERE c.id_cita = ?";

    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("i", $idCita);
    $stmt->execute();

    $cita = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$cita) {
        throw new Exception("La cita indicada no existe.");
    }

    if (!in_array($cita["estado"], ["PENDIENTE", "CONFIRMADA"], true)) {
        throw new Exception("Solo se permiten recordatorios para citas pendientes o confirmadas.");
    }

    if (
        $rol === "ESTUDIANTE" &&
        (int)$cita["id_estudiante"] !== $idUsuario
    ) {
        throw new Exception("Esta cita no te pertenece.");
    }

    if (
        $rol === "PROFESIONAL" &&
        (int)$cita["id_profesional"] !== $idUsuario
    ) {
        throw new Exception("Esta cita no pertenece a tus servicios.");
    }

    if (
        $rol === "COORDINADOR" &&
        (int)$cita["id_unidad"] !== $idUnidad
    ) {
        throw new Exception("Esta cita no pertenece a tu unidad.");
    }

    // Evitar duplicados para la misma cita, fecha y medio.
    $sql = "SELECT id_recordatorio
            FROM RECORDATORIOS
            WHERE id_cita = ?
              AND fecha_programada = ?
              AND medio = ?
            LIMIT 1";

    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("iss", $idCita, $fechaProgramada, $medio);
    $stmt->execute();

    $duplicado = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($duplicado) {
        throw new Exception("Ya existe un recordatorio con esa fecha y medio.");
    }

    // Registrar recordatorio.
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

    responder(201, [
        "success" => true,
        "mensaje" => "Recordatorio programado correctamente.",
        "id_recordatorio" => $idRecordatorio
    ]);

} catch (Throwable $e) {

    responder(400, [
        "success" => false,
        "mensaje" => $e->getMessage()
    ]);
}
?>
