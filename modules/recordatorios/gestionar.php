
<?php
session_start();

header("Content-Type: application/json; charset=utf-8");

require_once __DIR__ . "/../../config/conexion.php";
require_once __DIR__ . "/../../auth/verificar_sesion.php";

function responder($codigo, $datos) {
    http_response_code($codigo);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    verificarSesion();

    $rol = $_SESSION["rol"];
    $idUsuario = (int)$_SESSION["id_usuario"];
    $idUnidad = (int)($_SESSION["id_unidad"] ?? 0);

    if (!in_array($rol, [
        "ESTUDIANTE", "PROFESIONAL", "COORDINADOR", "ADMINISTRADOR"
    ], true)) {
        responder(403, [
            "success" => false,
            "mensaje" => "No tienes permiso para gestionar recordatorios."
        ]);
    }

    // Filtros según el rol.
    $filtro = "";
    $parametro = 0;

    if ($rol === "ESTUDIANTE") {
        $filtro = " AND c.id_estudiante = ?";
        $parametro = $idUsuario;
    } elseif ($rol === "PROFESIONAL") {
        $filtro = " AND ps.id_usuario = ?";
        $parametro = $idUsuario;
    } elseif ($rol === "COORDINADOR") {
        $filtro = " AND s.id_unidad = ?";
        $parametro = $idUnidad;
    }

    // =====================================
    // GET: LISTAR RECORDATORIOS Y CITAS
    // =====================================

    if ($_SERVER["REQUEST_METHOD"] === "GET") {

        // Recordatorios registrados.
        $sql = "SELECT
                    r.id_recordatorio,
                    r.id_cita,
                    r.fecha_programada,
                    r.fecha_envio,
                    r.medio,
                    r.estado,
                    c.estado AS estado_cita,
                    c.motivo,
                    d.fecha,
                    c.hora_inicio,
                    c.hora_fin,
                    s.nombre AS servicio,
                    CONCAT(e.nombres, ' ', e.apellidos) AS estudiante,
                    CONCAT(p.nombres, ' ', p.apellidos) AS profesional,
                    s.id_unidad
                FROM RECORDATORIOS r
                INNER JOIN CITAS c ON r.id_cita = c.id_cita
                INNER JOIN DISPONIBILIDADES d
                    ON c.id_disponibilidad = d.id_disponibilidad
                INNER JOIN PROFESIONAL_SERVICIO ps
                    ON d.id_profesional_servicio = ps.id_profesional_servicio
                INNER JOIN SERVICIOS s ON ps.id_servicio = s.id_servicio
                INNER JOIN USUARIOS e ON c.id_estudiante = e.id_usuario
                INNER JOIN USUARIOS p ON ps.id_usuario = p.id_usuario
                WHERE 1=1 $filtro
                ORDER BY r.fecha_programada DESC";

        $stmt = $conexion->prepare($sql);

        if ($filtro !== "") {
            $stmt->bind_param("i", $parametro);
        }

        $stmt->execute();
        $resultado = $stmt->get_result();

        $recordatorios = [];

        while ($fila = $resultado->fetch_assoc()) {
            $recordatorios[] = $fila;
        }

        $stmt->close();

        // Citas que permiten programar recordatorios.
        $sql = "SELECT
                    c.id_cita,
                    c.estado,
                    d.fecha,
                    c.hora_inicio,
                    c.hora_fin,
                    s.nombre AS servicio,
                    CONCAT(p.nombres, ' ', p.apellidos) AS profesional
                FROM CITAS c
                INNER JOIN DISPONIBILIDADES d
                    ON c.id_disponibilidad = d.id_disponibilidad
                INNER JOIN PROFESIONAL_SERVICIO ps
                    ON d.id_profesional_servicio = ps.id_profesional_servicio
                INNER JOIN SERVICIOS s ON ps.id_servicio = s.id_servicio
                INNER JOIN USUARIOS p ON ps.id_usuario = p.id_usuario
                WHERE c.estado IN ('PENDIENTE', 'CONFIRMADA')
                $filtro
                ORDER BY d.fecha, c.hora_inicio";

        $stmt = $conexion->prepare($sql);

        if ($filtro !== "") {
            $stmt->bind_param("i", $parametro);
        }

        $stmt->execute();
        $resultado = $stmt->get_result();

        $citasDisponibles = [];

        while ($fila = $resultado->fetch_assoc()) {
            $citasDisponibles[] = $fila;
        }

        $stmt->close();

        responder(200, [
            "success" => true,
            "total" => count($recordatorios),
            "recordatorios" => $recordatorios,
            "citas_disponibles" => $citasDisponibles
        ]);
    }

    // =====================================
    // POST: PROGRAMAR RECORDATORIO
    // =====================================

    if ($_SERVER["REQUEST_METHOD"] === "POST") {

        $datos = json_decode(file_get_contents("php://input"), true);

        if (!is_array($datos)) {
            $datos = $_POST;
        }

        $idCita = filter_var(
            $datos["id_cita"] ?? null,
            FILTER_VALIDATE_INT
        );

        $fechaProgramada = trim($datos["fecha_programada"] ?? "");
        $medio = $datos["medio"] ?? "";

        if (!$idCita || $idCita <= 0) {
            throw new Exception("Debes seleccionar una cita válida.");
        }

        if (!in_array($medio, ["CORREO", "SISTEMA"], true)) {
            throw new Exception("El medio seleccionado no es válido.");
        }

        $fecha = DateTime::createFromFormat(
            "Y-m-d\TH:i",
            $fechaProgramada
        );

        if (
            !$fecha ||
            $fecha->format("Y-m-d\TH:i") !== $fechaProgramada
        ) {
            throw new Exception("La fecha programada no es válida.");
        }

        if ($fecha <= new DateTime()) {
            throw new Exception("El recordatorio debe programarse para una fecha futura.");
        }

        // Verificar cita y permisos.
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
                INNER JOIN SERVICIOS s ON ps.id_servicio = s.id_servicio
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
            throw new Exception("Solo puedes programar recordatorios para citas pendientes o confirmadas.");
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

        $fechaSQL = $fecha->format("Y-m-d H:i:s");

        // Evitar duplicados.
        $sql = "SELECT id_recordatorio
                FROM RECORDATORIOS
                WHERE id_cita = ?
                  AND fecha_programada = ?
                  AND medio = ?
                LIMIT 1";

        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("iss", $idCita, $fechaSQL, $medio);
        $stmt->execute();

        $duplicado = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($duplicado) {
            throw new Exception("Ya existe un recordatorio con esa fecha y medio para esta cita.");
        }

        $sql = "INSERT INTO RECORDATORIOS
                    (id_cita, fecha_programada, medio, estado)
                VALUES (?, ?, ?, 'PENDIENTE')";

        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("iss", $idCita, $fechaSQL, $medio);

        if (!$stmt->execute()) {
            throw new Exception("No se pudo programar el recordatorio.");
        }

        $idRecordatorio = $conexion->insert_id;
        $stmt->close();

        responder(201, [
            "success" => true,
            "mensaje" => "Recordatorio programado correctamente.",
            "id_recordatorio" => $idRecordatorio
        ]);
    }

    responder(405, [
        "success" => false,
        "mensaje" => "Método no permitido."
    ]);

} catch (Throwable $e) {
    responder(400, [
        "success" => false,
        "mensaje" => $e->getMessage()
    ]);
}
