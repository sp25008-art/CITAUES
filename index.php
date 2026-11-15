
<?php

require_once "conexion.php";

$sql = "SELECT id_rol, nombre FROM ROLES ORDER BY id_rol ASC";
$resultado = $conexion->query($sql);

if ($resultado) {
    echo "<h2>Roles registrados en CITAUES</h2>";

    while ($fila = $resultado->fetch_assoc()) {
        echo $fila["id_rol"] . " - " . $fila["nombre"] . "<br>";
    }
} else {
    echo "Error al consultar: " . $conexion->error;
}

$conexion->close();

?>
