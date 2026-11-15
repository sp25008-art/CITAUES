
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

$nombres = trim($datos["nombres"] ?? "");
$apellidos = trim($datos["apellidos"] ?? "");
$correo = trim($datos["correo"] ?? "");
$carnet = trim($datos["carnet"] ?? "");
$telefono = trim($datos["telefono"] ?? "");

if (!$idUsuario || $nombres === "" ||
    $apellidos === "" || $correo === "") {
    responder(400, [
        "success" => false,
        "mensaje" => "Faltan datos obligatorios o el ID no es válido."
    ]);
}

if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    responder(400, [
        "success" => false,
        "mensaje" => "El correo electrónico no es válido."
    ]);
}

$rol = $_SESSION["rol"];

// Buscar al usuario que se desea modificar.
$sqlBuscar = "SELECT id_usuario, id_rol, id_unidad
              FROM USUARIOS
              WHERE id_usuario = ?";

$stmt = $conexion->prepare($sqlBuscar);
$stmt->bind_param("i", $idUsuario);
$stmt->execute();
$usuarioObjetivo = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$usuarioObjetivo) {
    responder(404, [
        "success" => false,
        "mensaje" => "El usuario no existe."
    ]);
}

// Comprobar autorización sobre el usuario objetivo.
if ($rol === "COORDINADOR") {

    exigirPermiso("usuarios.editar_unidad");

    if (
        (int)$usuarioObjetivo["id_rol"] !== 2 ||
        (int)$usuarioObjetivo["id_unidad"] !==
        (int)$_SESSION["id_unidad"]
    ) {
        responder(403, [
            "success" => false,
            "mensaje" => "No puedes editar usuarios de otra unidad."
        ]);
    }

} elseif ($rol !== "ADMINISTRADOR") {

    responder(403, [
        "success" => false,
        "mensaje" => "No tienes permiso para editar usuarios."
    ]);
}

// Comprobar correo duplicado.
$sqlCorreo = "SELECT id_usuario
              FROM USUARIOS
              WHERE correo = ? AND id_usuario <> ?";

$stmtCorreo = $conexion->prepare($sqlCorreo);
$stmtCorreo->bind_param("si", $correo, $idUsuario);
$stmtCorreo->execute();

if ($stmtCorreo->get_result()->fetch_assoc()) {
    responder(409, [
        "success" => false,
        "mensaje" => "El correo ya pertenece a otro usuario."
    ]);
}
$stmtCorreo->close();

// Comprobar carnet duplicado.
if ($carnet !== "") {

    $sqlCarnet = "SELECT id_usuario
                  FROM USUARIOS
                  WHERE carnet = ? AND id_usuario <> ?";

    $stmtCarnet = $conexion->prepare($sqlCarnet);
    $stmtCarnet->bind_param("si", $carnet, $idUsuario);
    $stmtCarnet->execute();

    if ($stmtCarnet->get_result()->fetch_assoc()) {
        responder(409, [
            "success" => false,
            "mensaje" => "El carnet ya está registrado."
        ]);
    }

    $stmtCarnet->close();

} else {
    $carnet = null;
}

// Actualizar únicamente los datos personales.
// El rol y la unidad se administran por separado.
$sql = "UPDATE USUARIOS
        SET nombres = ?,
            apellidos = ?,
            correo = ?,
            carnet = ?,
            telefono = ?
        WHERE id_usuario = ?";

$consulta = $conexion->prepare($sql);
$consulta->bind_param(
    "sssssi",
    $nombres,
    $apellidos,
    $correo,
    $carnet,
    $telefono,
    $idUsuario
);

if (!$consulta->execute()) {
    responder(500, [
        "success" => false,
        "mensaje" => "No fue posible actualizar el usuario."
    ]);
}

responder(200, [
    "success" => true,
    "mensaje" => "Usuario actualizado correctamente."
]);

?>
