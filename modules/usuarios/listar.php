
<?php

header("Content-Type: application/json; charset=UTF-8");

require_once "../../config/conexion.php";
require_once "../../auth/permisos.php";

// Verificar permiso de consulta
exigirPermiso("usuarios.consultar_unidad");

$rol = $_SESSION["rol"];

if ($rol === "ADMINISTRADOR") {

    // El administrador puede consultar todos los usuarios.
    $sql = "SELECT
                u.id_usuario,
                u.nombres,
                u.apellidos,
                u.correo,
                u.carnet,
                u.telefono,
                u.estado,
                u.id_unidad,
                un.nombre AS unidad,
                r.nombre AS rol
            FROM USUARIOS u
            INNER JOIN ROLES r
                ON u.id_rol = r.id_rol
            LEFT JOIN UNIDADES un
                ON u.id_unidad = un.id_unidad
            ORDER BY u.id_usuario";

    $consulta = $conexion->prepare($sql);

} elseif ($rol === "COORDINADOR") {

    // El coordinador solo consulta estudiantes
    // y profesionales de su propia unidad.
    $sql = "SELECT
                u.id_usuario,
                u.nombres,
                u.apellidos,
                u.correo,
                u.carnet,
                u.telefono,
                u.estado,
                u.id_unidad,
                un.nombre AS unidad,
                r.nombre AS rol
            FROM USUARIOS u
            INNER JOIN ROLES r
                ON u.id_rol = r.id_rol
            LEFT JOIN UNIDADES un
                ON u.id_unidad = un.id_unidad
            WHERE u.id_unidad = ?
              AND u.id_rol = 2
            ORDER BY u.id_usuario";

    $consulta = $conexion->prepare($sql);

    $idUnidad = (int) $_SESSION["id_unidad"];

    $consulta->bind_param("i", $idUnidad);

} else {

    http_response_code(403);

    echo json_encode([
        "success" => false,
        "mensaje" => "No tienes permiso para consultar usuarios."
    ]);

    exit();
}

if (!$consulta || !$consulta->execute()) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "mensaje" => "Error al consultar los usuarios."
    ]);

    exit();
}

$resultado = $consulta->get_result();

$usuarios = [];

while ($fila = $resultado->fetch_assoc()) {
    $usuarios[] = $fila;
}

echo json_encode([
    "success" => true,
    "total" => count($usuarios),
    "usuarios" => $usuarios
], JSON_UNESCAPED_UNICODE);

$consulta->close();
$conexion->close();

?>
