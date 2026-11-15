
<?php

function registrarHistorial(
    mysqli $conexion,
    int $idCita,
    int $idUsuario,
    string $tipoCambio,
    ?string $estadoAnterior = null,
    ?string $estadoNuevo = null,
    ?int $disponibilidadAnterior = null,
    ?int $disponibilidadNueva = null,
    ?string $observacion = null
): void {

    $sql = "INSERT INTO HISTORIAL_CITAS (
                id_cita,
                id_usuario,
                tipo_cambio,
                estado_anterior,
                estado_nuevo,
                id_disponibilidad_anterior,
                id_disponibilidad_nueva,
                observacion
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conexion->prepare($sql);

    if (!$stmt) {
        throw new Exception("Error preparando historial: " . $conexion->error);
    }

    $stmt->bind_param(
        "iisssiis",
        $idCita,
        $idUsuario,
        $tipoCambio,
        $estadoAnterior,
        $estadoNuevo,
        $disponibilidadAnterior,
        $disponibilidadNueva,
        $observacion
    );

    if (!$stmt->execute()) {
        throw new Exception("Error registrando historial: " . $stmt->error);
    }

    $stmt->close();
}
