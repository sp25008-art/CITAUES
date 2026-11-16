
<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Comprueba que el usuario haya iniciado sesión.
 */
function verificarSesion(): void {

    if (!isset($_SESSION["id_usuario"])) {
        header("Location: /CITAUES/index.php");
        exit();
    }
}

/**
 * Comprueba que el usuario tenga uno de los roles permitidos.
 */
function verificarRol(array $rolesPermitidos): void {

    verificarSesion();

    if (!in_array($_SESSION["rol"], $rolesPermitidos, true)) {
        http_response_code(403);
        die("Acceso denegado. No tienes permisos para acceder a esta sección.");
    }
}
?>

