
// =====================================
// RESERVACIONES CITAUIS
// DEMOSTRACIÓN DEL FRONTEND
// =====================================

const servicio = document.getElementById("servicio");
const fecha = document.getElementById("fecha");
const motivo = document.getElementById("motivo");
const listaHorarios = document.getElementById("listaHorarios");
const horaSeleccionada = document.getElementById("horaSeleccionada");

const resumenServicio = document.getElementById("resumenServicio");
const resumenFecha = document.getElementById("resumenFecha");
const resumenHora = document.getElementById("resumenHora");

const formulario = document.getElementById("formReserva");

// Evitar seleccionar fechas anteriores a hoy
const hoy = new Date();
const fechaLocal = new Date(
    hoy.getTime() - hoy.getTimezoneOffset() * 60000
).toISOString().split("T")[0];

fecha.min = fechaLocal;

// Horarios de ejemplo para realizar pruebas
const horarios = [
    "08:00 AM",
    "09:00 AM",
    "10:00 AM",
    "11:00 AM",
    "01:00 PM",
    "02:00 PM",
    "03:00 PM",
    "04:00 PM"
];

// Reservaciones de demostración
// Este horario aparecerá ocupado para comprobar la validación.
const horariosOcupados = [
    "10:00 AM"
];

// Actualizar horarios
function mostrarHorarios() {

    listaHorarios.innerHTML = "";
    horaSeleccionada.value = "";
    resumenHora.textContent = "No seleccionado";

    if (servicio.value === "" || fecha.value === "") {

        listaHorarios.innerHTML =
            '<p class="mensaje-horarios">Primero selecciona un servicio y una fecha.</p>';

        return;
    }

    let disponibles = 0;

    horarios.forEach(function(hora) {

        const boton = document.createElement("button");

        boton.type = "button";
        boton.textContent = hora;
        boton.classList.add("horario-opcion");

        const ocupado = horariosOcupados.includes(hora);

        if (ocupado) {

            boton.disabled = true;
            boton.textContent = hora + " - Ocupado";

        } else {

            disponibles++;

            boton.addEventListener("click", function() {

                document.querySelectorAll(".horario-opcion")
                    .forEach(function(elemento) {
                        elemento.classList.remove("seleccionado");
                    });

                boton.classList.add("seleccionado");

                horaSeleccionada.value = hora;
                resumenHora.textContent = hora;

            });

        }

        listaHorarios.appendChild(boton);

    });

    if (disponibles === 0) {
        listaHorarios.innerHTML =
            '<p class="mensaje-horarios">No hay horarios disponibles.</p>';
    }
}

// Actualizar resumen
servicio.addEventListener("change", function() {

    resumenServicio.textContent =
        servicio.value || "No seleccionado";

    mostrarHorarios();

});

fecha.addEventListener("change", function() {

    resumenFecha.textContent =
        fecha.value || "No seleccionada";

    mostrarHorarios();

});

// Confirmar reservación
formulario.addEventListener("submit", function(event) {

    event.preventDefault();

    if (!servicio.value || !fecha.value || !motivo.value.trim()) {

        alert("Completa todos los campos.");
        return;

    }

    if (!horaSeleccionada.value) {

        alert("Debes seleccionar un horario disponible.");
        return;

    }

    const reservaciones = JSON.parse(
        localStorage.getItem("reservacionesCITAUIS") || "[]"
    );

    // Evitar duplicar el mismo horario para el mismo servicio
    const duplicada = reservaciones.some(function(cita) {

        return cita.servicio === servicio.value &&
               cita.fecha === fecha.value &&
               cita.hora === horaSeleccionada.value;

    });

    if (duplicada) {

        alert("Ya existe una reservación para ese horario.");
        return;

    }

    const nuevaCita = {

        servicio: servicio.value,
        fecha: fecha.value,
        hora: horaSeleccionada.value,
        motivo: motivo.value.trim(),
        estado: "Pendiente"

    };

    reservaciones.push(nuevaCita);

    localStorage.setItem(
        "reservacionesCITAUIS",
        JSON.stringify(reservaciones)
    );

    alert(
        "¡Reservación registrada correctamente!\n\n" +
        "Servicio: " + nuevaCita.servicio +
        "\nFecha: " + nuevaCita.fecha +
        "\nHora: " + nuevaCita.hora
    );

    formulario.reset();

    horaSeleccionada.value = "";
    resumenServicio.textContent = "No seleccionado";
    resumenFecha.textContent = "No seleccionada";
    resumenHora.textContent = "No seleccionado";

    listaHorarios.innerHTML =
        '<p class="mensaje-horarios">Primero selecciona un servicio y una fecha.</p>';

});
