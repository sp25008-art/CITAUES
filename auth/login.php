
<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once "../config/conexion.php";

header("Content-Type: application/json; charset=utf-8");

function responder($exito, $mensaje, $datos = []) {
    echo json_encode(array_merge([
        "exito" => $exito,
        "mensaje" => $mensaje
    ], $datos), JSON_UNESCAPED_UNICODE);
    exit;
}

// Solo permitir POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    responder(false, "Método no permitido.");
}

// Recibir datos JSON o formulario
$contenido = file_get_contents("php://input");
$datos = json_decode($contenido, true);

if (is_array($datos)) {
    $correo = trim($datos["correo"] ?? "");
    $password = $datos["password"] ?? "";
} else {
    $correo = trim($_POST["correo"] ?? "");
    $password = $_POST["password"] ?? "";
}

// Validar campos
if ($correo === "" || $password === "") {
    http_response_code(400);
    responder(false, "Debe ingresar el correo y la contraseña.");
}

// Consultar usuario
$sql = "SELECT
            u.id_usuario,
            u.id_unidad,
            u.nombres,
            u.apellidos,
            u.correo,
            u.password,
            r.nombre AS rol
        FROM USUARIOS u
        INNER JOIN ROLES r ON u.id_rol = r.id_rol
        WHERE u.correo = ?
        AND u.estado = 'ACTIVO'
        LIMIT 1";

$consulta = $conexion->prepare($sql);

if (!$consulta) {
    http_response_code(500);
    responder(false, "Error al preparar la consulta.");
}

$consulta->bind_param("s", $correo);
$consulta->execute();

$resultado = $consulta->get_result();
$usuario = $resultado->fetch_assoc();

// Validar credenciales
if (!$usuario || !password_verify($password, $usuario["password"])) {
    http_response_code(401);
    $consulta->close();
    $conexion->close();
    responder(false, "Correo o contraseña incorrectos.");
}

// Crear sesión
session_regenerate_id(true);

$_SESSION["id_usuario"] = (int)$usuario["id_usuario"];
$_SESSION["id_unidad"] = $usuario["id_unidad"];
$_SESSION["nombres"] = $usuario["nombres"];
$_SESSION["apellidos"] = $usuario["apellidos"];
$_SESSION["correo"] = $usuario["correo"];
$_SESSION["rol"] = $usuario["rol"];

$nombre = $usuario["nombres"];
$rol = $usuario["rol"];

$consulta->close();
$conexion->close();

responder(true, "Inicio de sesión exitoso.", [
    "usuario" => [
        "nombres" => $nombre,
        "rol" => $rol
    ],
    "redireccion" => "../frontend/admin.php"
]);

?>
