
<?php
session_start();

require_once __DIR__ . "/../../config/conexion.php";
require_once __DIR__ . "/../../auth/verificar_sesion.php";

header("Content-Type: application/json; charset=utf-8");

try {
    verificarSesion();

    $rol = $_SESSION["rol"];
    $idUsuario = (int)$_SESSION["id_usuario"];

    $sql = "SELECT
                a.id_asistencia,
                a.id_cita,
                a.id_profesional,
                a.estado_asistencia,
                a.observacion,
                a.fecha_registro,
                c.id_estudiante,
                c.estado AS estado_cita,
                c.hora_inicio,
                c.hora_fin,
                d.fecha,
                s.nombre AS servicio,
                u.nombres AS estudiante_nombres,
                u.apellidos AS estudiante_apellidos,
                p.nombres AS profesional_nombres,
                p.apellidos AS profesional_apellidos
            FROM ASISTENCIAS a
            INNER JOIN CITAS c ON a.id_cita = c.id_cita
            INNER JOIN DISPONIBILIDADES d
                ON c.id_disponibilidad = d.id_disponibilidad
            INNER JOIN PROFESIONAL_SERVICIO ps
                ON d.id_profesional_servicio = ps.id_profesional_servicio
            INNER JOIN SERVICIOS s ON ps.id_servicio = s.id_servicio
            INNER JOIN USUARIOS u ON c.id_estudiante = u.id_usuario
            INNER JOIN USUARIOS p ON a.id_profesional = p.id_usuario";

    if ($rol === "PROFESIONAL") {
        $sql .= " WHERE a.id_profesional = ?";
    } elseif ($rol === "ESTUDIANTE") {
        $sql .= " WHERE c.id_estudiante = ?";
    } elseif (!in_array($rol, ["ADMINISTRADOR", "COORDINADOR"], true)) {
        throw new Exception("No tienes permisos para consultar asistencias.");
    }

    $sql .= " ORDER BY d.fecha DESC, c.hora_inicio DESC";

    $stmt = $conexion->prepare($sql);

    if ($rol === "PROFESIONAL" || $rol === "ESTUDIANTE") {
        $stmt->bind_param("i", $idUsuario);
    }

    $stmt->execute();
    $resultado = $stmt->get_result();

    $asistencias = [];

    while ($fila = $resultado->fetch_assoc()) {
        $asistencias[] = $fila;
    }

    echo json_encode([
        "success" => true,
        "total" => count($asistencias),
        "asistencias" => $asistencias
    ], JSON_UNESCAPED_UNICODE);

    $stmt->close();

} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "mensaje" => $e->getMessage()
    ]);
}
