
<?php

require_once __DIR__ . "/verificar_sesion.php";

/*
|--------------------------------------------------------------------------
| Permisos iniciales por rol
|--------------------------------------------------------------------------
| Esta configuración es una propuesta para el prototipo.
| Los permisos definitivos deberán validarse con los interesados.
*/

$permisosPorRol = [

    "ESTUDIANTE" => [
        "servicios.consultar",
        "citas.solicitar",
        "citas.consultar_propias",
        "citas.cancelar_propias"
    ],

    "PROFESIONAL" => [
        "servicios.consultar",
        "disponibilidad.gestionar",
        "citas.consultar_asignadas",
        "citas.gestionar_asignadas",
        "asistencias.registrar"
    ],

    "COORDINADOR" => [
    "servicios.consultar",
    "servicios.gestionar_unidad",

    "usuarios.consultar_unidad",
    "usuarios.registrar_unidad",
    "usuarios.editar_unidad",
    "usuarios.cambiar_estado_unidad",

    "disponibilidad.consultar_unidad",
    "citas.consultar_unidad",
    "reportes.consultar_unidad",
    "disponibilidad.gestionar_unidad"
    ],

    "ADMINISTRADOR" => [
        "*"
    ]

];

/**
 * Comprueba si el usuario tiene un permiso específico.
 */
function tienePermiso(string $permiso): bool {

    global $permisosPorRol;

    verificarSesion();

    $rol = $_SESSION["rol"];

    if (!isset($permisosPorRol[$rol])) {
        return false;
    }

    if (in_array("*", $permisosPorRol[$rol], true)) {
        return true;
    }

    return in_array(
        $permiso,
        $permisosPorRol[$rol],
        true
    );
}

/**
 * Exige un permiso para continuar.
 */
function exigirPermiso(string $permiso): void {

    if (!tienePermiso($permiso)) {
        http_response_code(403);
        die("Acceso denegado. No tienes permiso para realizar esta operación.");
    }
}

?>
