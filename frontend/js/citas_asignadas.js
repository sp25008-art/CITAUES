
document.addEventListener("DOMContentLoaded", () => {

    cargarCitasAsignadas();

    document.getElementById("actualizarCitas")
        .addEventListener("click", cargarCitasAsignadas);

    // Filtros de citas
    document.querySelectorAll(".filtro-cita").forEach(boton => {

        boton.addEventListener("click", () => {

            document.querySelectorAll(".filtro-cita").forEach(b => {
                b.classList.remove("activo");
            });

            boton.classList.add("activo");

            aplicarFiltro(boton.dataset.filtro);
        });

    });

    // Acciones de las citas
    document.addEventListener("click", gestionarAcciones);

});


// ======================================
// APLICAR FILTROS
// ======================================

function aplicarFiltro(filtro) {

    document.querySelectorAll("#tablaCitasAsignadas tr").forEach(fila => {

        fila.hidden = filtro !== "TODAS" &&
            fila.dataset.estado !== filtro;

    });

}


// ======================================
// CONSULTAR CITAS
// ======================================

async function cargarCitasAsignadas() {

    const tabla = document.getElementById("tablaCitasAsignadas");
    const carga = document.getElementById("mensajeCarga");
    const contenedor = document.getElementById("contenedorTabla");
    const sinCitas = document.getElementById("mensajeSinCitas");

    carga.hidden = false;
    carga.textContent = "Cargando citas...";

    contenedor.hidden = true;
    sinCitas.hidden = true;

    try {

        const respuesta = await fetch(
            "../modules/citas/listar_citas.php",
            {
                credentials: "same-origin",
                cache: "no-store"
            }
        );

        const datos = await respuesta.json();

        if (!respuesta.ok || !datos.success) {
            throw new Error(
                datos.mensaje || "No se pudieron consultar las citas."
            );
        }

        tabla.innerHTML = "";

        if (!datos.citas || datos.citas.length === 0) {

            carga.hidden = true;
            sinCitas.hidden = false;
            return;

        }

        const rol = document.body.dataset.rol;

        datos.citas.forEach(cita => {

            const fila = document.createElement("tr");

            fila.dataset.estado = cita.estado;

            // Fecha
            const fecha = new Date(cita.fecha + "T00:00:00")
                .toLocaleDateString("es-SV");

            // Datos de la cita
            const valores = [
                cita.estudiante,
                cita.servicio,
                fecha,
                `${cita.hora_inicio.substring(0, 5)} - ${cita.hora_fin.substring(0, 5)}`,
                cita.estado,
                cita.motivo || "Sin motivo especificado"
            ];

            valores.forEach((valor, indice) => {

                const celda = document.createElement("td");

                celda.textContent = valor ?? "";

                if (indice === 4) {

                    celda.className =
                        cita.estado === "CONFIRMADA"
                            ? "estado-confirmada"
                            : cita.estado === "PENDIENTE"
                                ? "estado-pendiente"
                                : cita.estado === "FINALIZADA"
                                    ? "estado-finalizada"
                                    : "";

                }

                fila.appendChild(celda);

            });

            // ======================================
            // ACCIONES
            // ======================================

            const acciones = document.createElement("td");

            const tieneAsistencia =
                cita.tiene_asistencia === true ||
                cita.tiene_asistencia === 1 ||
                cita.tiene_asistencia === "1";

            if (tieneAsistencia) {

                // La asistencia ya fue registrada.
                const resultado =
                    cita.estado_asistencia === "ASISTIO"
                        ? "Asistió"
                        : "No asistió";

                acciones.textContent =
                    `Asistencia registrada: ${resultado}`;

            } else if (cita.estado === "PENDIENTE") {

                // Confirmar cita
                const boton = document.createElement("button");

                boton.type = "button";
                boton.className = "boton-confirmar-cita";
                boton.textContent = "Confirmar";
                boton.dataset.idCita = cita.id_cita;

                acciones.appendChild(boton);

            } else if (cita.estado === "CONFIRMADA") {

                // Solo profesional y administrador registran asistencia.
                if (rol === "PROFESIONAL" || rol === "ADMINISTRADOR") {

                    crearBotonAsistencia(
                        acciones,
                        cita.id_cita,
                        "ASISTIO",
                        "Asistió",
                        "si"
                    );

                    crearBotonAsistencia(
                        acciones,
                        cita.id_cita,
                        "NO_ASISTIO",
                        "No asistió",
                        "no"
                    );

                } else {

                    acciones.textContent = "Cita confirmada";

                }

            } else if (cita.estado === "FINALIZADA") {

                acciones.textContent = "Cita finalizada";

            } else {

                acciones.textContent = "Sin acciones";

            }

            fila.appendChild(acciones);
            tabla.appendChild(fila);

        });

        carga.hidden = true;
        contenedor.hidden = false;

        // Mantener el filtro seleccionado.
        const filtroActivo =
            document.querySelector(".filtro-cita.activo");

        if (filtroActivo) {
            aplicarFiltro(filtroActivo.dataset.filtro);
        }

        console.log("Citas recibidas:", datos.total);

    } catch (error) {

        console.error("Error al cargar citas:", error);

        carga.textContent = error.message;
        carga.hidden = false;

    }

}


// ======================================
// CREAR BOTONES DE ASISTENCIA
// ======================================

function crearBotonAsistencia(
    contenedor,
    idCita,
    estado,
    texto,
    clase
) {

    const boton = document.createElement("button");

    boton.type = "button";
    boton.className = `boton-asistencia ${clase}`;
    boton.textContent = texto;

    boton.dataset.idCita = idCita;
    boton.dataset.estadoAsistencia = estado;

    contenedor.appendChild(boton);

}


// ======================================
// GESTIONAR ACCIONES
// ======================================

async function gestionarAcciones(event) {

    const botonConfirmar = event.target.closest(
        ".boton-confirmar-cita"
    );

    const botonAsistencia = event.target.closest(
        ".boton-asistencia"
    );

    if (botonConfirmar) {

        await confirmarCita(botonConfirmar);

    } else if (botonAsistencia) {

        await registrarAsistencia(botonAsistencia);

    }

}


// ======================================
// CONFIRMAR CITA
// ======================================

async function confirmarCita(boton) {

    const idCita = Number(boton.dataset.idCita);

    if (!confirm("¿Deseas confirmar esta cita?")) {
        return;
    }

    boton.disabled = true;
    boton.textContent = "Procesando...";

    try {

        const respuesta = await fetch(
            "../modules/citas/confirmar_citas.php",
            {
                method: "POST",
                credentials: "same-origin",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify({
                    id_cita: idCita
                })
            }
        );

        const datos = await respuesta.json();

        if (!respuesta.ok || !datos.success) {
            throw new Error(
                datos.mensaje || "No se pudo confirmar la cita."
            );
        }

        alert(datos.mensaje);

        await cargarCitasAsignadas();

    } catch (error) {

        console.error("Error al confirmar:", error);

        alert(error.message);

        boton.disabled = false;
        boton.textContent = "Confirmar";

    }

}


// ======================================
// REGISTRAR ASISTENCIA
// ======================================

async function registrarAsistencia(boton) {

    const idCita = Number(boton.dataset.idCita);

    const estado = boton.dataset.estadoAsistencia;

    const mensaje = estado === "ASISTIO"
        ? "¿Confirmas que el estudiante asistió?"
        : "¿Confirmas que el estudiante no asistió?";

    if (!confirm(mensaje)) {
        return;
    }

    const observacion = prompt(
        "Escribe una observación (opcional):",
        ""
    );

    if (observacion === null) {
        return;
    }

    boton.disabled = true;
    boton.textContent = "Guardando...";

    try {

        const respuesta = await fetch(
            "../modules/asistencias/registrar.php",
            {
                method: "POST",
                credentials: "same-origin",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify({
                    id_cita: idCita,
                    estado_asistencia: estado,
                    observacion: observacion
                })
            }
        );

        const datos = await respuesta.json();

        if (!respuesta.ok || !datos.success) {
            throw new Error(
                datos.mensaje || "No se pudo registrar la asistencia."
            );
        }

        alert(datos.mensaje);

        await cargarCitasAsignadas();

    } catch (error) {

        console.error("Error al registrar asistencia:", error);

        alert(error.message);

        boton.disabled = false;

        boton.textContent =
            estado === "ASISTIO"
                ? "Asistió"
                : "No asistió";

    }

}
