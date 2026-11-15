
<?php

require_once "verificar_sesion.php";

// Comprobar que existe una sesión
verificarSesion();

echo "<h2>Sesión iniciada correctamente</h2>";

echo "Usuario: " . htmlspecialchars($_SESSION["nombres"]) . "<br>";

echo "Correo: " . htmlspecialchars($_SESSION["correo"]) . "<br>";

echo "Rol: " . htmlspecialchars($_SESSION["rol"]) . "<br>";

echo "<p>Unidad: " .
     ($_SESSION["id_unidad"] ?? "Sin unidad asignada") .
     "</p>";

echo '<br><a href="logout.php">Cerrar sesión</a>';

?>
