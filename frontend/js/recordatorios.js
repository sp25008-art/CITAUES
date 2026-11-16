
document.addEventListener("DOMContentLoaded", () => {

    cargarRecordatorios();

    document.getElementById("actualizarRecordatorios")
        .addEventListener("click", cargarRecordatorios);

    document.getElementById("formRecordatorio")
        .addEventListener("submit", programarRecordatorio);

});

const esAdministrador =
    document.body.dataset.rol === "ADMINISTRADOR";


// =====================================
// CONSULTAR RECORDATORIOS Y CITAS
// =====================================

async function cargarRecordatorios() {

    const carga = document.getElementById("cargandoRecordatorios");
    const contenedor = document.getElementById("contenedorRecordatorios");
    const vacio = document.getElementById("sinRecordatorios");
    const tabla = document.getElementById("tablaRecordatorios");

    carga.hidden = false;
    carga.textContent = "Cargando recordatorios...";
    contenedor.hidden = true;
    vacio.hidden = true;

    try {

        const respuesta = await fetch(
            "../modules/recordatorios/gestionar.php",
            {
                credentials: "same-origin",
                cache: "no-store"
            }
        );

        const datos = await respuesta.json();

        if (!respuesta.ok || !datos.success) {
            throw new Error(datos.mensaje || "No se pudieron consultar los recordatorios.");
        }

        cargarCitasDisponibles(datos.citas_disponibles || []);

        tabla.innerHTML = "";

        if (!datos.recordatorios || datos.recordatorios.length === 0) {
            carga.hidden = true;
            vacio.hidden = false;
            return;
        }

        datos.recordatorios.forEach(recordatorio => {

            const fila = document.createElement("tr");

            const valores = [
                recordatorio.id_recordatorio,
                `#${recordatorio.id_cita}`,
                recordatorio.estudiante,
                recordatorio.servicio,
                formatearFecha(recordatorio.fecha),
                formatearFechaHora(recordatorio.fecha_programada),
                recordatorio.medio,
                recordatorio.estado,
                formatearFechaHora(recordatorio.fecha_envio)
            ];

            valores.forEach((valor, indice) => {

                const celda = document.createElement("td");
                celda.textContent = valor || "—";

                if (indice === 7) {
                    celda.className =
                        `estado-recordatorio estado-${recordatorio.estado}`;
                }

                fila.appendChild(celda);
            });

            if (esAdministrador) {

                const celdaAcciones = document.createElement("td");
                const acciones = document.createElement("div");

                acciones.className = "acciones-recordatorio";

                const selector = document.createElement("select");

                ["PENDIENTE", "ENVIADO", "ERROR"].forEach(estado => {
                    const opcion = document.createElement("option");
                    opcion.value = estado;
                    opcion.textContent = estado;
                    selector.appendChild(opcion);
                });

                selector.value = recordatorio.estado;

                const boton = document.createElement("button");
                boton.type = "button";
                boton.textContent = "Guardar";
                boton.className = "boton-guardar-estado";

                boton.addEventListener("click", () => {
                    actualizarEstadoRecordatorio(
                        recordatorio.id_recordatorio,
                        selector.value
                    );
                });

                acciones.appendChild(selector);
                acciones.appendChild(boton);
                celdaAcciones.appendChild(acciones);
                fila.appendChild(celdaAcciones);
            }

            tabla.appendChild(fila);
        });

        carga.hidden = true;
        contenedor.hidden = false;

    } catch (error) {
        console.error(error);
        carga.textContent = error.message;
    }
}


// =====================================
// LLENAR DESPLEGABLE DE CITAS
// =====================================

function cargarCitasDisponibles(citas) {

    const selector = document.getElementById("idCita");

    selector.innerHTML = "";

    const inicial = document.createElement("option");
    inicial.value = "";
    inicial.textContent = citas.length
        ? "Selecciona una cita"
        : "No tienes citas disponibles";

    inicial.disabled = true;
    inicial.selected = true;

    selector.appendChild(inicial);

    citas.forEach(cita => {

        const opcion = document.createElement("option");
        opcion.value = cita.id_cita;

        opcion.textContent =
            `Cita #${cita.id_cita} | ${cita.servicio} | ` +
            `${formatearFecha(cita.fecha)} | ` +
            `${cita.hora_inicio} - ${cita.hora_fin} | ${cita.estado}`;

        selector.appendChild(opcion);
    });
}


// =====================================
// PROGRAMAR RECORDATORIO
// =====================================

async function programarRecordatorio(event) {

    event.preventDefault();

    const idCita = document.getElementById("idCita").value;
    const fechaProgramada = document.getElementById("fechaProgramada").value;
    const medio = document.getElementById("medioRecordatorio").value;

    const mensaje = document.getElementById("mensajeRecordatorio");

    mensaje.hidden = false;
    mensaje.textContent = "Programando recordatorio...";
    mensaje.style.color = "#333";

    try {

        const respuesta = await fetch(
            "../modules/recordatorios/gestionar.php",
            {
                method: "POST",
                credentials: "same-origin",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify({
                    id_cita: idCita,
                    fecha_programada: fechaProgramada,
                    medio: medio
                })
            }
        );

        const datos = await respuesta.json();

        if (!respuesta.ok || !datos.success) {
            throw new Error(datos.mensaje || "No se pudo programar el recordatorio.");
        }

        mensaje.textContent = datos.mensaje;
        mensaje.style.color = "#166534";

        document.getElementById("formRecordatorio").reset();

        await cargarRecordatorios();

    } catch (error) {

        mensaje.textContent = error.message;
        mensaje.style.color = "#dc2626";
    }
}


// =====================================
// ACTUALIZAR ESTADO
// =====================================

async function actualizarEstadoRecordatorio(idRecordatorio, estado) {

    if (!esAdministrador) {
        alert("No tienes permiso para actualizar recordatorios.");
        return;
    }

    if (!confirm(`¿Cambiar el recordatorio #${idRecordatorio} a ${estado}?`)) {
        return;
    }

    try {

        const respuesta = await fetch(
            "../modules/recordatorios/actualizar_estado.php",
            {
                method: "POST",
                credentials: "same-origin",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify({
                    id_recordatorio: idRecordatorio,
                    estado: estado
                })
            }
        );

        const datos = await respuesta.json();

        if (!respuesta.ok || !datos.success) {
            throw new Error(datos.mensaje || "No se pudo actualizar el recordatorio.");
        }

        alert(datos.mensaje);
        await cargarRecordatorios();

    } catch (error) {
        alert(error.message);
        console.error(error);
    }
}


// =====================================
// FORMATO DE FECHAS
// =====================================

function formatearFecha(fecha) {

    if (!fecha) return "—";

    const valor = new Date(`${fecha}T00:00:00`);

    if (Number.isNaN(valor.getTime())) return fecha;

    return valor.toLocaleDateString("es-SV");
}


function formatearFechaHora(fecha) {

    if (!fecha) return "—";

    const valor = new Date(fecha.replace(" ", "T"));

    if (Number.isNaN(valor.getTime())) return fecha;

    return valor.toLocaleString("es-SV", {
        dateStyle: "short",
        timeStyle: "short"
    });
}
