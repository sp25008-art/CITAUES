
<?php

header("Content-Type: application/json; charset=UTF-8");

require_once "../../config/conexion.php";
require_once "../../auth/permisos.php";

function responder($codigo, $datos) {
    http_response_code($codigo);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit();
}

verificarSesion();

if ($_SESSION["rol"] !== "ESTUDIANTE") {
    responder(403, [
        "success" => false,
        "mensaje" => "Solo los estudiantes pueden consultar horarios para reservar."
    ]);
}

exigirPermiso("citas.solicitar");

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    responder(405, [
        "success" => false,
        "mensaje" => "Método no permitido."
    ]);
}

$idServicio = filter_input(INPUT_GET, "id_servicio", FILTER_VALIDATE_INT);
$fecha = trim($_GET["fecha"] ?? "");

$fechaObj = DateTime::createFromFormat("!Y-m-d", $fecha);

if (
    !$idServicio ||
    !$fechaObj ||
    $fechaObj->format("Y-m-d") !== $fecha ||
    $fecha < date("Y-m-d")
) {
    responder(400, [
        "success" => false,
        "mensaje" => "El servicio o la fecha no son válidos."
    ]);
}

// Consultar servicio.
$sqlServicio = "SELECT
                    id_servicio,
                    nombre,
                    duracion_minutos,
                    anticipacion_minima_horas,
                    estado
                FROM SERVICIOS
                WHERE id_servicio = ?
                LIMIT 1";

$stmt = $conexion->prepare($sqlServicio);
$stmt->bind_param("i", $idServicio);
$stmt->execute();

$servicio = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$servicio || $servicio["estado"] !== "ACTIVO") {
    responder(404, [
        "success" => false,
        "mensaje" => "El servicio no existe o no está activo."
    ]);
}

$duracion = (int)$servicio["duracion_minutos"];
$anticipacion = (int)$servicio["anticipacion_minima_horas"];

if ($duracion <= 0) {
    responder(500, [
        "success" => false,
        "mensaje" => "La duración del servicio no es válida."
    ]);
}

// Consultar disponibilidades activas.
$sql = "SELECT
            d.id_disponibilidad,
            d.fecha,
            d.hora_inicio,
            d.hora_fin,
            d.cupo_maximo,
            CONCAT(u.nombres, ' ', u.apellidos) AS profesional
        FROM DISPONIBILIDADES d
        INNER JOIN PROFESIONAL_SERVICIO ps
            ON d.id_profesional_servicio = ps.id_profesional_servicio
        INNER JOIN USUARIOS u
            ON ps.id_usuario = u.id_usuario
        WHERE ps.id_servicio = ?
          AND ps.estado = 'ACTIVO'
          AND d.estado = 'DISPONIBLE'
          AND s_dummy_placeholder = 1
          AND u.estado = 'ACTIVO'
          AND d.fecha = ?
        ORDER BY d.hora_inicio";

// Corregir consulta utilizando la tabla de servicios.
$sql = "SELECT
            d.id_disponibilidad,
            d.fecha,
            d.hora_inicio,
            d.hora_fin,
            d.cupo_maximo,
            CONCAT(u.nombres, ' ', u.apellidos) AS profesional
        FROM DISPONIBILIDADES d
        INNER JOIN PROFESIONAL_SERVICIO ps
            ON d.id_profesional_servicio = ps.id_profesional_servicio
        INNER JOIN USUARIOS u
            ON ps.id_usuario = u.id_usuario
        INNER JOIN SERVICIOS s
            ON ps.id_servicio = s.id_servicio
        WHERE ps.id_servicio = ?
          AND ps.estado = 'ACTIVO'
          AND d.estado = 'DISPONIBLE'
          AND s.estado = 'ACTIVO'
          AND u.estado = 'ACTIVO'
          AND d.fecha = ?
        ORDER BY d.hora_inicio";

$stmt = $conexion->prepare($sql);
$stmt->bind_param("is", $idServicio, $fecha);
$stmt->execute();

$resultado = $stmt->get_result();

$horarios = [];

$ahora = new DateTime();

$minimoPermitido = clone $ahora;
$minimoPermitido->modify("+{$anticipacion} hours");

$sqlOcupacion = "SELECT COUNT(*) AS ocupados
                 FROM CITAS
                 WHERE id_disponibilidad = ?
                   AND estado IN ('PENDIENTE', 'CONFIRMADA')
                   AND hora_inicio < ?
                   AND hora_fin > ?";

$stmtOcupacion = $conexion->prepare($sqlOcupacion);

while ($bloque = $resultado->fetch_assoc()) {

    $inicioBloque = new DateTime(
        $bloque["fecha"] . " " . $bloque["hora_inicio"]
    );

    $finBloque = new DateTime(
        $bloque["fecha"] . " " . $bloque["hora_fin"]
    );

    $inicio = clone $inicioBloque;

    while (true) {

        $fin = clone $inicio;
        $fin->modify("+{$duracion} minutes");

        if ($fin > $finBloque) {
            break;
        }

        if ($inicio >= $minimoPermitido) {

            $horaInicio = $inicio->format("H:i:s");
            $horaFin = $fin->format("H:i:s");

            $idDisponibilidad = (int)$bloque["id_disponibilidad"];

            $stmtOcupacion->bind_param(
                "iss",
                $idDisponibilidad,
                $horaFin,
                $horaInicio
            );

            $stmtOcupacion->execute();

            $ocupados = (int)$stmtOcupacion
                ->get_result()
                ->fetch_assoc()["ocupados"];

            $capacidad = (int)$bloque["cupo_maximo"];
            $disponibles = $capacidad - $ocupados;

            if ($disponibles > 0) {

                $horarios[] = [
                    "id_disponibilidad" => $idDisponibilidad,
                    "fecha" => $fecha,
                    "hora_inicio" => $horaInicio,
                    "hora_fin" => $horaFin,
                    "profesional" => $bloque["profesional"],
                    "cupo_maximo" => $capacidad,
                    "cupos_disponibles" => $disponibles
                ];
            }
        }

        $inicio->modify("+{$duracion} minutes");
    }
}

$stmt->close();
$stmtOcupacion->close();
$conexion->close();

responder(200, [
    "success" => true,
    "servicio" => $servicio["nombre"],
    "fecha" => $fecha,
    "duracion_minutos" => $duracion,
    "total" => count($horarios),
    "horarios" => $horarios
]);

?>
