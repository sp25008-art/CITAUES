
document.addEventListener("DOMContentLoaded", () => {

    const servicio = document.getElementById("servicio");
    const fecha = document.getElementById("fecha");
    const motivo = document.getElementById("motivo");

    const listaHorarios = document.getElementById("listaHorarios");
    const idDisponibilidad = document.getElementById("idDisponibilidad");
    const horaSeleccionada = document.getElementById("horaSeleccionada");

    const resumenServicio = document.getElementById("resumenServicio");
    const resumenFecha = document.getElementById("resumenFecha");
    const resumenHora = document.getElementById("resumenHora");

    const formulario = document.getElementById("formReserva");
    const botonConfirmar = document.getElementById("botonConfirmar");

    let horariosDisponibles = [];

    // Fecha local para evitar problemas de zona horaria.
    function obtenerFechaLocal() {

        const ahora = new Date();

        const anio = ahora.getFullYear();
        const mes = String(ahora.getMonth() + 1).padStart(2, "0");
        const dia = String(ahora.getDate()).padStart(2, "0");

        return `${anio}-${mes}-${dia}`;
    }

    fecha.min = obtenerFechaLocal();

    // Mostrar mensaje en el área de horarios.
    function mostrarMensaje(texto) {

        listaHorarios.replaceChildren();

        const mensaje = document.createElement("p");
        mensaje.className = "mensaje-horarios";
        mensaje.textContent = texto;

        listaHorarios.appendChild(mensaje);
    }

    // Limpiar selección.
    function limpiarSeleccion() {

        idDisponibilidad.value = "";
        horaSeleccionada.value = "";

        resumenHora.textContent = "No seleccionado";
    }

    // Cargar servicios desde MariaDB.
    async function cargarServicios() {

        try {

            const respuesta = await fetch(
                "../modules/servicios/listar.php",
                {
                    credentials: "same-origin"
                }
            );

            if (!respuesta.ok) {
                throw new Error("No se pudieron consultar los servicios.");
            }

            const resultado = await respuesta.json();

            if (!resultado.success) {
                throw new Error(resultado.mensaje);
            }

            servicio.replaceChildren();

            const opcionInicial = document.createElement("option");
            opcionInicial.value = "";
            opcionInicial.textContent = "Selecciona un servicio";

            servicio.appendChild(opcionInicial);

            resultado.servicios.forEach(item => {

                const opcion = document.createElement("option");

                opcion.value = item.id_servicio;
                opcion.textContent = item.nombre;

                servicio.appendChild(opcion);
            });

            // Recuperar servicio seleccionado desde el catálogo.
            const idInicial = window.ID_SERVICIO_INICIAL;

            if (
                idInicial &&
                resultado.servicios.some(
                    item => Number(item.id_servicio) === Number(idInicial)
                )
            ) {
                servicio.value = String(idInicial);

                resumenServicio.textContent =
                    servicio.options[servicio.selectedIndex].textContent;
            }

        } catch (error) {

            console.error(error);

            servicio.innerHTML =
                '<option value="">Error al cargar servicios</option>';

            mostrarMensaje("No fue posible cargar los servicios.");
        }
    }

    // Crear botones de horarios.
    function mostrarHorarios(lista) {

        listaHorarios.replaceChildren();

        limpiarSeleccion();

        if (lista.length === 0) {
            mostrarMensaje("No hay horarios disponibles para esta fecha.");
            return;
        }

        lista.forEach(horario => {

            const boton = document.createElement("button");

            boton.type = "button";
            boton.className = "horario-opcion";

            const inicio = horario.hora_inicio.substring(0, 5);
            const fin = horario.hora_fin.substring(0, 5);

            boton.textContent = `${inicio} - ${fin}`;

            boton.title =
                `Profesional: ${horario.profesional}. Cupos disponibles: ${horario.cupos_disponibles}`;

            boton.addEventListener("click", () => {

                document.querySelectorAll(".horario-opcion")
                    .forEach(elemento => {
                        elemento.classList.remove("seleccionado");
                    });

                boton.classList.add("seleccionado");

                idDisponibilidad.value =
                    horario.id_disponibilidad;

                horaSeleccionada.value =
                    horario.hora_inicio.substring(0, 5);

                resumenHora.textContent =
                    `${inicio} - ${fin} (${horario.profesional})`;
            });

            listaHorarios.appendChild(boton);
        });
    }

    // Consultar horarios reales.
    async function cargarHorarios() {

        limpiarSeleccion();

        if (!servicio.value || !fecha.value) {

            mostrarMensaje(
                "Primero selecciona un servicio y una fecha."
            );

            return;
        }

        mostrarMensaje("Consultando horarios disponibles...");

        try {

            const parametros = new URLSearchParams({
                id_servicio: servicio.value,
                fecha: fecha.value
            });

            const respuesta = await fetch(
                `../modules/disponibilidad/consultar_horarios.php?${parametros}`,
                {
                    credentials: "same-origin"
                }
            );

            const resultado = await respuesta.json();

            if (!respuesta.ok || !resultado.success) {
                throw new Error(
                    resultado.mensaje || "No se pudieron consultar los horarios."
                );
            }

            horariosDisponibles = resultado.horarios || [];

            mostrarHorarios(horariosDisponibles);

        } catch (error) {

            console.error(error);

            mostrarMensaje(
                error.message || "Error al consultar los horarios."
            );
        }
    }

    // Cuando cambia el servicio.
    servicio.addEventListener("change", () => {

        resumenServicio.textContent =
            servicio.value
                ? servicio.options[servicio.selectedIndex].textContent
                : "No seleccionado";

        cargarHorarios();
    });

    // Cuando cambia la fecha.
    fecha.addEventListener("change", () => {

        resumenFecha.textContent =
            fecha.value || "No seleccionada";

        cargarHorarios();
    });

    // Registrar reservación en MariaDB.
    formulario.addEventListener("submit", async event => {

        event.preventDefault();

        if (!servicio.value || !fecha.value || !motivo.value.trim()) {
            alert("Completa todos los campos.");
            return;
        }

        if (!idDisponibilidad.value || !horaSeleccionada.value) {
            alert("Debes seleccionar un horario disponible.");
            return;
        }

        botonConfirmar.disabled = true;
        botonConfirmar.textContent = "Registrando reservación...";

        try {

            const respuesta = await fetch(
                "../modules/citas/registrar_cita.php",
                {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json"
                    },
                    credentials: "same-origin",
                    body: JSON.stringify({
                        id_disponibilidad: Number(idDisponibilidad.value),
                        hora_inicio: horaSeleccionada.value,
                        motivo: motivo.value.trim()
                    })
                }
            );

            const resultado = await respuesta.json();

            if (!respuesta.ok || !resultado.success) {
                throw new Error(
                    resultado.mensaje || "No fue posible registrar la cita."
                );
            }

            alert(
                "¡Solicitud registrada correctamente!\n\n" +
                "Servicio: " + resultado.servicio +
                "\nFecha: " + resultado.fecha +
                "\nHorario: " +
                resultado.hora_inicio.substring(0, 5) +
                " - " +
                resultado.hora_fin.substring(0, 5) +
                "\nEstado: " + resultado.estado
            );

            formulario.reset();

            resumenServicio.textContent = "No seleccionado";
            resumenFecha.textContent = "No seleccionada";
            resumenHora.textContent = "No seleccionado";

            limpiarSeleccion();

            horariosDisponibles = [];

            mostrarMensaje(
                "Primero selecciona un servicio y una fecha."
            );

            // Actualizar horarios para reflejar los cupos restantes.
            fecha.min = obtenerFechaLocal();

        } catch (error) {

            console.error(error);

            alert(error.message);

            // Si otro usuario ocupó el horario, actualizar disponibilidad.
            await cargarHorarios();

        } finally {

            botonConfirmar.disabled = false;
            botonConfirmar.textContent = "Confirmar reservación";
        }
    });

    // Inicialización.
    cargarServicios();

});

