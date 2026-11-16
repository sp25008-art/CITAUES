
<?php

header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . "/../../config/conexion.php";
require_once __DIR__ . "/../../auth/permisos.php";
require_once __DIR__ . "/funciones_historial.php";
verificarSesion();

function responder($codigo, $datos) {
    http_response_code($codigo);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit();
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

$rol = $_SESSION["rol"];
$idUsuario = (int)$_SESSION["id_usuario"];

if (!in_array($rol, [
    "PROFESIONAL",
    "COORDINADOR",
    "ADMINISTRADOR"
], true)) {
    responder(403, [
        "success" => false,
        "mensaje" => "No puedes confirmar citas."
    ]);
}

$transaccionIniciada = false;

try {

    $conexion->begin_transaction();
    $transaccionIniciada = true;

    // 1. Consultar y bloquear la cita.
    $sql = "SELECT
                c.id_cita,
                c.estado,
                c.id_disponibilidad,
                ps.id_usuario,
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

    // 2. Verificar estado.
    if ($cita["estado"] !== "PENDIENTE") {
        throw new Exception(
            "Solo se pueden confirmar citas pendientes."
        );
    }

    $idDisponibilidad = (int)$cita["id_disponibilidad"];

    // 3. Verificar permisos según el rol.
if ($rol === "PROFESIONAL") {

    exigirPermiso("citas.gestionar_asignadas");

    if ((int)$cita["id_usuario"] !== $idUsuario) {
        throw new Exception(
            "Esta cita no pertenece a tus servicios."
        );
    }

} elseif ($rol === "COORDINADOR") {

    exigirPermiso("citas.consultar_unidad");

    if (
        (int)$cita["id_unidad"] !==
        (int)$_SESSION["id_unidad"]
    ) {
        throw new Exception(
            "La cita no pertenece a tu unidad."
        );
    }

} elseif ($rol === "ADMINISTRADOR") {

    // El administrador puede confirmar cualquier cita.

}

    // 4. Actualizar el estado.
    $sql = "UPDATE CITAS
            SET estado = 'CONFIRMADA'
            WHERE id_cita = ?
              AND estado = 'PENDIENTE'";

    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("i", $idCita);

    if (!$stmt->execute() || $stmt->affected_rows !== 1) {
        $stmt->close();

        throw new Exception(
            "La cita cambió de estado o ya fue procesada."
        );
    }

    $stmt->close();

    // 5. Registrar el historial.
    registrarHistorial(
        $conexion,
        $idCita,
        $idUsuario,
        "CONFIRMACION",
        "PENDIENTE",
        "CONFIRMADA",
        $idDisponibilidad,
        $idDisponibilidad,
        "Cita confirmada correctamente."
    );

    // 6. Confirmar ambas operaciones.
    $conexion->commit();
    $transaccionIniciada = false;

    responder(200, [
        "success" => true,
        "mensaje" => "Cita confirmada correctamente.",
        "id_cita" => $idCita,
        "estado" => "CONFIRMADA"
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
