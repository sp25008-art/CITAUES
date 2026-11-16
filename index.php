
<?php

require_once __DIR__ . "/auth/verificar_sesion.php";

// Si el usuario ya inició sesión, enviarlo al panel.
if (isset($_SESSION["id_usuario"])) {
    header("Location: frontend/dashboard.php");
    exit();
}

// Si no ha iniciado sesión, mostrar el login.
header("Location: frontend/index.html");
exit();

?>

