
<?php

session_start();

require_once "../config/conexion.php";

// Verificar que el formulario haya enviado los datos
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    die("Acceso no permitido.");
}

// Obtener los datos enviados
$correo = trim($_POST["correo"] ?? "");
$password = $_POST["password"] ?? "";

// Validar campos vacíos
if (empty($correo) || empty($password)) {
    die("Debe ingresar el correo y la contraseña.");
}

// Buscar al usuario y su rol
$sql = "SELECT u.id_usuario, u.id_unidad,
       u.nombres, u.apellidos,
       u.correo, u.password,
       r.nombre AS rol
        FROM USUARIOS u
        INNER JOIN ROLES r ON u.id_rol = r.id_rol
        WHERE u.correo = ? AND u.estado = 'ACTIVO'
        LIMIT 1";

$consulta = $conexion->prepare($sql);
$consulta->bind_param("s", $correo);
$consulta->execute();

$resultado = $consulta->get_result();
$usuario = $resultado->fetch_assoc();

// Verificar usuario y contraseña
if ($usuario && password_verify($password, $usuario["password"])) {

    // Guardar información en la sesión
    session_regenerate_id(true);

    $_SESSION["id_usuario"] = $usuario["id_usuario"];
    $_SESSION["id_unidad"] = $usuario["id_unidad"];
    $_SESSION["nombres"] = $usuario["nombres"];
    $_SESSION["apellidos"] = $usuario["apellidos"];
    $_SESSION["correo"] = $usuario["correo"];
    $_SESSION["rol"] = $usuario["rol"];

    echo "Inicio de sesión exitoso. Bienvenido "
         . htmlspecialchars($usuario["nombres"])
         . ". Rol: "
         . htmlspecialchars($usuario["rol"]);

} else {
    echo "Correo o contraseña incorrectos.";
}

$consulta->close();
$conexion->close();

?>
