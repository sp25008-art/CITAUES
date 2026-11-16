
<?php

header("Content-Type: application/json; charset=UTF-8");

require_once "../../config/conexion.php";
require_once "../../auth/permisos.php";

function responder($codigo, $datos) {
    http_response_code($codigo);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit();
}

// Solo se permiten solicitudes POST.
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    responder(405, [
        "success" => false,
        "mensaje" => "Método no permitido."
    ]);
}

// Comprobar permisos.
exigirPermiso("usuarios.registrar_unidad");

// Recibir datos JSON o mediante formulario.
$datos = json_decode(file_get_contents("php://input"), true);

if (!is_array($datos)) {
    $datos = $_POST;
}

// Obtener y limpiar los datos.
$nombres = trim($datos["nombres"] ?? "");
$apellidos = trim($datos["apellidos"] ?? "");
$correo = trim($datos["correo"] ?? "");
$carnet = trim($datos["carnet"] ?? "");
$password = $datos["password"] ?? "";
$telefono = trim($datos["telefono"] ?? "");

$idRol = filter_var(
    $datos["id_rol"] ?? null,
    FILTER_VALIDATE_INT
);

$idUnidad = filter_var(
    $datos["id_unidad"] ?? null,
    FILTER_VALIDATE_INT
);

// Validar campos obligatorios.
if (
    $nombres === "" ||
    $apellidos === "" ||
    $correo === "" ||
    $password === "" ||
    $idRol === false ||
    $idRol === null
) {
    responder(400, [
        "success" => false,
        "mensaje" => "Complete todos los campos obligatorios."
    ]);
}

// Validar correo.
if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    responder(400, [
        "success" => false,
        "mensaje" => "El correo electrónico no es válido."
    ]);
}

// Validar longitud de contraseña.
if (strlen($password) < 8) {
    responder(400, [
        "success" => false,
        "mensaje" => "La contraseña debe tener al menos 8 caracteres."
    ]);
}

// Comprobar que el rol exista.
$sqlRol = "SELECT id_rol FROM ROLES WHERE id_rol = ?";
$stmtRol = $conexion->prepare($sqlRol);
$stmtRol->bind_param("i", $idRol);
$stmtRol->execute();

if (!$stmtRol->get_result()->fetch_assoc()) {
    responder(400, [
        "success" => false,
        "mensaje" => "El rol seleccionado no existe."
    ]);
}

$stmtRol->close();

$rolUsuario = $_SESSION["rol"];

// Reglas para coordinadores.
if ($rolUsuario === "COORDINADOR") {

    // Solo pueden registrar profesionales.
    if ($idRol !== 2) {
        responder(403, [
            "success" => false,
            "mensaje" => "Solo puedes registrar profesionales."
        ]);
    }

    // Se asigna automáticamente su propia unidad.
    $idUnidad = (int) $_SESSION["id_unidad"];

} elseif ($rolUsuario === "ADMINISTRADOR") {

    // Estudiantes y administradores no pertenecen a una unidad.
    if ($idRol === 1 || $idRol === 4) {
        $idUnidad = null;
    }

    // Profesionales y coordinadores requieren una unidad.
    if (($idRol === 2 || $idRol === 3) &&
        ($idUnidad === false || $idUnidad === null || $idUnidad <= 0)) {

        responder(400, [
            "success" => false,
            "mensaje" => "Debe seleccionar una unidad para este usuario."
        ]);
    }

} else {
    responder(403, [
        "success" => false,
        "mensaje" => "No tienes permiso para registrar usuarios."
    ]);
}

// Comprobar que la unidad exista cuando corresponda.
if ($idUnidad !== null) {

    $sqlUnidad = "SELECT id_unidad FROM UNIDADES WHERE id_unidad = ?";
    $stmtUnidad = $conexion->prepare($sqlUnidad);
    $stmtUnidad->bind_param("i", $idUnidad);
    $stmtUnidad->execute();

    if (!$stmtUnidad->get_result()->fetch_assoc()) {
        responder(400, [
            "success" => false,
            "mensaje" => "La unidad seleccionada no existe."
        ]);
    }

    $stmtUnidad->close();
}

// Comprobar correo duplicado.
$sqlCorreo = "SELECT id_usuario FROM USUARIOS WHERE correo = ?";
$stmtCorreo = $conexion->prepare($sqlCorreo);
$stmtCorreo->bind_param("s", $correo);
$stmtCorreo->execute();

if ($stmtCorreo->get_result()->fetch_assoc()) {
    responder(409, [
        "success" => false,
        "mensaje" => "Ya existe un usuario con ese correo."
    ]);
}

$stmtCorreo->close();

// Comprobar carnet duplicado, si fue proporcionado.
if ($carnet !== "") {

    $sqlCarnet = "SELECT id_usuario FROM USUARIOS WHERE carnet = ?";
    $stmtCarnet = $conexion->prepare($sqlCarnet);
    $stmtCarnet->bind_param("s", $carnet);
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

// Generar hash seguro de la contraseña.
$passwordHash = password_hash($password, PASSWORD_DEFAULT);

// Insertar usuario.
$sql = "INSERT INTO USUARIOS
        (id_rol, id_unidad, nombres, apellidos,
         correo, carnet, password, telefono, estado)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'ACTIVO')";

$consulta = $conexion->prepare($sql);

$consulta->bind_param(
    "iissssss",
    $idRol,
    $idUnidad,
    $nombres,
    $apellidos,
    $correo,
    $carnet,
    $passwordHash,
    $telefono
);

if ($consulta->execute()) {

    responder(201, [
        "success" => true,
        "mensaje" => "Usuario registrado correctamente.",
        "id_usuario" => $conexion->insert_id
    ]);

} else {

    responder(500, [
        "success" => false,
        "mensaje" => "No fue posible registrar el usuario."
    ]);
}

?>
