
<?php

require_once "permisos.php";

echo "<h2>Prueba de permisos CITAUES</h2>";

$permisos = [
    "servicios.consultar",
    "citas.solicitar",
    "disponibilidad.gestionar",
    "usuarios.gestionar"
];

echo "<p>Usuario: " .
     htmlspecialchars($_SESSION["nombres"]) .
     "</p>";

echo "<p>Rol: " .
     htmlspecialchars($_SESSION["rol"]) .
     "</p>";

foreach ($permisos as $permiso) {

    if (tienePermiso($permiso)) {
        echo "✅ $permiso - PERMITIDO<br>";
    } else {
        echo "❌ $permiso - DENEGADO<br>";
    }

}

?>
```

### ¿Qué debes probar?

Inicia sesión con cada usuario y abre:

`http://localhost/CITAUES/auth/prueba_permisos.php`

Estos son algunos resultados esperados:

| Permiso | Estudiante | Profesional | Coordinador | Administrador |
|---|:---:|:---:|:---:|:---:|
| servicios.consultar | Sí | Sí | Sí | Sí |
| citas.solicitar | Sí | No | No | Sí |
| disponibilidad.gestionar | No | Sí | No | Sí |
| usuarios.gestionar | No | No | No | Sí |

**Importante:** son permisos iniciales propuestos para el prototipo, no reglas institucionales definitivas.

Cuando pruebes los cuatro usuarios, dime si los resultados coinciden. Después eliminaremos el archivo de prueba y comenzaremos con el primer módulo real: **gestión de usuarios**.