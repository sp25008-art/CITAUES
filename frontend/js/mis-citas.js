
document.addEventListener("DOMContentLoaded", () => {
    cargarMisCitas();

    document.getElementById("cerrarModal")
        ?.addEventListener("click", cerrarModal);

    document.getElementById("fechaNueva")
        ?.addEventListener("change", consultarHorarios);

    document.getElementById("confirmarReprogramacion")
        ?.addEventListener("click", confirmarReprogramacion);
});


// ============================================
// 1. CARGAR CITAS
// ============================================

async function cargarMisCitas() {

    const tabla = document.getElementById("tablaMisCitas");
    const contenedorTabla = document.getElementById("contenedorTabla");
    const mensajeCarga = document.getElementById("mensajeCarga");
    const mensajeSinCitas = document.getElementById("mensajeSinCitas");

    if (!tabla || !contenedorTabla || !mensajeCarga || !mensajeSinCitas) {
        console.error("Faltan elementos HTML en mis-citas.php");
        return;
    }

    mensajeCarga.hidden = false;
    mensajeCarga.textContent = "Cargando tus reservaciones...";

    try {

        const respuesta = await fetch("../modules/citas/listar_citas.php", {
            credentials: "same-origin",
            cache: "no-store"
        });

        const datos = await respuesta.json();

        if (!respuesta.ok || !datos.success) {
            throw new Error(datos.mensaje || "No se pudieron cargar las citas.");
        }

        tabla.innerHTML = "";

        if (!datos.citas || datos.citas.length === 0) {
            mensajeCarga.hidden = true;
            contenedorTabla.hidden = true;
            mensajeSinCitas.hidden = false;
            return;
        }

        datos.citas.forEach(cita => {

            const fila = document.createElement("tr");

            const fecha = new Date(cita.fecha + "T00:00:00")
                .toLocaleDateString("es-SV");

            const valores = [
                cita.servicio,
                fecha,
                `${cita.hora_inicio.substring(0, 5)} - ${cita.hora_fin.substring(0, 5)}`,
                cita.profesional,
                cita.estado
            ];

            valores.forEach(valor => {
                const celda = document.createElement("td");
                celda.textContent = valor ?? "";
                fila.appendChild(celda);
            });

            // Acciones
            const celdaAcciones = document.createElement("td");
            const acciones = document.createElement("div");
            acciones.className = "acciones-cita";

            if (["PENDIENTE", "CONFIRMADA"].includes(cita.estado)) {

                // Botón cancelar
                const botonCancelar = document.createElement("button");

                botonCancelar.textContent = "Cancelar";
                botonCancelar.type = "button";
                botonCancelar.className = "boton-cancelar";
                botonCancelar.dataset.idCita = cita.id_cita;

                acciones.appendChild(botonCancelar);

                // Botón reprogramar
                const botonReprogramar = document.createElement("button");

                botonReprogramar.textContent = "Reprogramar";
                botonReprogramar.type = "button";
                botonReprogramar.className = "boton-reprogramar";

                botonReprogramar.dataset.idCita = cita.id_cita;
                botonReprogramar.dataset.idServicio = cita.id_servicio;
                botonReprogramar.dataset.servicio = cita.servicio;

                acciones.appendChild(botonReprogramar);

            } else {

                acciones.textContent = "Sin acciones";
            }

            celdaAcciones.appendChild(acciones);
            fila.appendChild(celdaAcciones);

            tabla.appendChild(fila);
        });

        mensajeCarga.hidden = true;
        mensajeSinCitas.hidden = true;
        contenedorTabla.hidden = false;

        console.log("Citas cargadas:", datos.total);

    } catch (error) {

        console.error("Error al cargar las citas:", error);

        mensajeCarga.textContent = error.message;
        mensajeCarga.hidden = false;
        contenedorTabla.hidden = true;
        mensajeSinCitas.hidden = true;
    }
}


// ============================================
// 2. CANCELAR CITA
// ============================================

document.addEventListener("click", async function(event) {

    const boton = event.target.closest(".boton-cancelar");

    if (!boton) return;

    const idCita = boton.dataset.idCita;

    const confirmar = window.confirm(
        "¿Estás seguro de que deseas cancelar esta cita?"
    );

    if (!confirmar) return;

    boton.disabled = true;
    boton.textContent = "Cancelando...";

    try {

        const respuesta = await fetch("../modules/citas/cancelar_cita.php", {
            method: "POST",
            credentials: "same-origin",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify({
                id_cita: Number(idCita)
            })
        });

        const datos = await respuesta.json();

        if (!respuesta.ok || !datos.success) {
            throw new Error(datos.mensaje || "No se pudo cancelar la cita.");
        }

        alert(datos.mensaje);

        await cargarMisCitas();

    } catch (error) {

        alert(error.message);
        console.error("Error al cancelar:", error);

        boton.disabled = false;
        boton.textContent = "Cancelar";
    }
});


// ============================================
// 3. ABRIR VENTANA DE REPROGRAMACIÓN
// ============================================

document.addEventListener("click", function(event) {

    const boton = event.target.closest(".boton-reprogramar");

    if (!boton) return;

    const modal = document.getElementById("modalReprogramar");
    const fecha = document.getElementById("fechaNueva");

    document.getElementById("idCitaReprogramar").value =
        boton.dataset.idCita;

    document.getElementById("idServicioReprogramar").value =
        boton.dataset.idServicio;

    document.getElementById("nombreServicioReprogramar").textContent =
        "Servicio: " + boton.dataset.servicio;

    document.getElementById("idDisponibilidadNueva").value = "";
    document.getElementById("horaNueva").value = "";

    document.getElementById("listaHorarios").textContent =
        "Selecciona una fecha para consultar horarios.";

    document.getElementById("mensajeReprogramacion").textContent = "";

    document.getElementById("confirmarReprogramacion").disabled = true;

    const hoy = new Date();
    const fechaLocal = [
        hoy.getFullYear(),
        String(hoy.getMonth() + 1).padStart(2, "0"),
        String(hoy.getDate()).padStart(2, "0")
    ].join("-");

    fecha.min = fechaLocal;
    fecha.value = "";

    modal.hidden = false;
});


// ============================================
// 4. CERRAR VENTANA
// ============================================

function cerrarModal() {

    document.getElementById("modalReprogramar").hidden = true;

    document.getElementById("idDisponibilidadNueva").value = "";
    document.getElementById("horaNueva").value = "";

    document.getElementById("confirmarReprogramacion").disabled = true;
}


// ============================================
// 5. CONSULTAR NUEVOS HORARIOS
// ============================================

async function consultarHorarios() {

    const fecha = document.getElementById("fechaNueva").value;

    const idServicio =
        document.getElementById("idServicioReprogramar").value;

    const idCita =
        document.getElementById("idCitaReprogramar").value;

    const lista = document.getElementById("listaHorarios");
    const mensaje = document.getElementById("mensajeReprogramacion");

    const botonConfirmar =
        document.getElementById("confirmarReprogramacion");

    document.getElementById("idDisponibilidadNueva").value = "";
    document.getElementById("horaNueva").value = "";

    botonConfirmar.disabled = true;

    if (!fecha) {
        lista.textContent = "Selecciona una fecha.";
        return;
    }

    lista.textContent = "Consultando horarios disponibles...";
    mensaje.textContent = "";

    try {

        const parametros = new URLSearchParams({
            id_servicio: idServicio,
            fecha: fecha,
            excluir_cita: idCita
        });

        const respuesta = await fetch(
            "../modules/disponibilidad/consultar_horarios.php?" +
            parametros.toString(),
            {
                credentials: "same-origin",
                cache: "no-store"
            }
        );

        const datos = await respuesta.json();

        if (!respuesta.ok || !datos.success) {
            throw new Error(datos.mensaje || "No se pudieron consultar los horarios.");
        }

        lista.innerHTML = "";

        if (!datos.horarios || datos.horarios.length === 0) {
            lista.textContent =
                "No hay horarios disponibles para esta fecha.";
            return;
        }

        datos.horarios.forEach(horario => {

            const boton = document.createElement("button");

            boton.type = "button";
            boton.className = "horario-opcion";

            boton.textContent =
                horario.hora_inicio.substring(0, 5) +
                " - " +
                horario.hora_fin.substring(0, 5);

            boton.title = "Profesional: " + horario.profesional;

            boton.addEventListener("click", () => {

                document.querySelectorAll(".horario-opcion")
                    .forEach(elemento => {
                        elemento.classList.remove("seleccionado");
                    });

                boton.classList.add("seleccionado");

                document.getElementById("idDisponibilidadNueva").value =
                    horario.id_disponibilidad;

                document.getElementById("horaNueva").value =
                    horario.hora_inicio.substring(0, 5);

                mensaje.textContent =
                    "Horario seleccionado: " +
                    horario.hora_inicio.substring(0, 5) +
                    " con " + horario.profesional;

                botonConfirmar.disabled = false;
            });

            lista.appendChild(boton);
        });

    } catch (error) {

        console.error("Error consultando horarios:", error);

        lista.textContent = error.message;
    }
}


// ============================================
// 6. CONFIRMAR REPROGRAMACIÓN
// ============================================

async function confirmarReprogramacion() {

    const idCita =
        document.getElementById("idCitaReprogramar").value;

    const idDisponibilidad =
        document.getElementById("idDisponibilidadNueva").value;

    const horaInicio =
        document.getElementById("horaNueva").value;

    const boton =
        document.getElementById("confirmarReprogramacion");

    const mensaje =
        document.getElementById("mensajeReprogramacion");

    if (!idCita || !idDisponibilidad || !horaInicio) {
        mensaje.textContent = "Debes seleccionar un nuevo horario.";
        return;
    }

    const confirmar = window.confirm(
        "¿Confirmas que deseas reprogramar esta cita?"
    );

    if (!confirmar) return;

    boton.disabled = true;
    boton.textContent = "Procesando...";

    try {

        const respuesta = await fetch(
            "../modules/citas/reprogramar_cita.php",
            {
                method: "POST",
                credentials: "same-origin",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify({
                    id_cita: Number(idCita),
                    id_disponibilidad: Number(idDisponibilidad),
                    hora_inicio: horaInicio
                })
            }
        );

        const datos = await respuesta.json();

        if (!respuesta.ok || !datos.success) {
            throw new Error(
                datos.mensaje || "No se pudo reprogramar la cita."
            );
        }

        alert(datos.mensaje);

        cerrarModal();

        await cargarMisCitas();

    } catch (error) {

        console.error("Error al reprogramar:", error);

        mensaje.textContent = error.message;

    } finally {

        boton.textContent = "Confirmar reprogramación";

        if (!document.getElementById("modalReprogramar").hidden) {
            boton.disabled = false;
        }
    }
}

