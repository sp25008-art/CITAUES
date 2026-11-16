
<?php

header("Content-Type: application/json; charset=UTF-8");

require_once "../../config/conexion.php";
require_once "../../auth/permisos.php";

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

$idUsuario = filter_var(
    $datos["id_usuario"] ?? null,
    FILTER_VALIDATE_INT
);

$estado = strtoupper(trim($datos["estado"] ?? ""));

if (!$idUsuario || !in_array($estado, ["ACTIVO", "INACTIVO"], true)) {
    responder(400, [
        "success" => false,
        "mensaje" => "ID o estado no válido."
    ]);
}

$rol = $_SESSION["rol"];

// Buscar usuario objetivo.
$sqlBuscar = "SELECT id_usuario, id_rol, id_unidad
              FROM USUARIOS
              WHERE id_usuario = ?";

$stmt = $conexion->prepare($sqlBuscar);
$stmt->bind_param("i", $idUsuario);
$stmt->execute();
$objetivo = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$objetivo) {
    responder(404, [
        "success" => false,
        "mensaje" => "El usuario no existe."
    ]);
}

// Verificar permisos.
if ($rol === "COORDINADOR") {

    exigirPermiso("usuarios.cambiar_estado_unidad");

    if (
        (int)$objetivo["id_rol"] !== 2 ||
        (int)$objetivo["id_unidad"] !==
        (int)$_SESSION["id_unidad"]
    ) {
        responder(403, [
            "success" => false,
            "mensaje" => "No puedes modificar usuarios de otra unidad."
        ]);
    }

} elseif ($rol !== "ADMINISTRADOR") {

    responder(403, [
        "success" => false,
        "mensaje" => "No tienes permiso para cambiar estados."
    ]);
}

// Evitar que el administrador se desactive a sí mismo.
if (
    $idUsuario === (int)$_SESSION["id_usuario"] &&
    $estado === "INACTIVO"
) {
    responder(400, [
        "success" => false,
        "mensaje" => "No puedes desactivar tu propia cuenta."
    ]);
}

$sql = "UPDATE USUARIOS
        SET estado = ?
        WHERE id_usuario = ?";

$consulta = $conexion->prepare($sql);
$consulta->bind_param("si", $estado, $idUsuario);

if (!$consulta->execute()) {
    responder(500, [
        "success" => false,
        "mensaje" => "No fue posible cambiar el estado."
    ]);
}

responder(200, [
    "success" => true,
    "mensaje" => "Estado actualizado correctamente.",
    "nuevo_estado" => $estado
]);

?>
