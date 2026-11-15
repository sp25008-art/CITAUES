
// =====================================
// MÓDULO MIS CITAS - CITAUIS
// =====================================

const tabla = document.getElementById("tablaMisCitas");
const mensajeSinCitas = document.getElementById("mensajeSinCitas");

function obtenerReservaciones() {
    return JSON.parse(
        localStorage.getItem("reservacionesCITAUIS") || "[]"
    );
}

function guardarReservaciones(reservaciones) {
    localStorage.setItem(
        "reservacionesCITAUIS",
        JSON.stringify(reservaciones)
    );
}

// Mostrar las reservaciones
function mostrarMisCitas() {

    const reservaciones = obtenerReservaciones();

    tabla.innerHTML = "";

    const activas = reservaciones
        .map((cita, indice) => ({ ...cita, indice }))
        .filter(cita => cita.estado !== "Cancelada");

    if (activas.length === 0) {

        mensajeSinCitas.style.display = "block";
        return;

    }

    mensajeSinCitas.style.display = "none";

    activas.forEach(function(cita) {

        const fila = document.createElement("tr");

        const estadoClase =
            cita.estado === "Cancelada"
                ? "estado-cancelada"
                : "estado-pendiente";

        fila.innerHTML = `
            <td>${escaparHTML(cita.servicio)}</td>
            <td>${escaparHTML(cita.fecha)}</td>
            <td>${escaparHTML(cita.hora)}</td>
            <td>
                <span class="estado-cita ${estadoClase}">
                    ${escaparHTML(cita.estado)}
                </span>
            </td>
            <td>
                <div class="acciones-cita">
                    <button class="boton-accion boton-reprogramar"
                        data-accion="reprogramar"
                        data-indice="${cita.indice}">
                        Reprogramar
                    </button>

                    <button class="boton-accion boton-cancelar"
                        data-accion="cancelar"
                        data-indice="${cita.indice}">
                        Cancelar
                    </button>
                </div>
            </td>
        `;

        tabla.appendChild(fila);

    });

}

// Evitar insertar contenido de texto como HTML
function escaparHTML(texto) {
    return String(texto).replace(/[&<>"']/g, function(caracter) {
        return {
            "&": "&amp;",
            "<": "&lt;",
            ">": "&gt;",
            '"': "&quot;",
            "'": "&#39;"
        }[caracter];
    });
}

// Cancelar o reprogramar
tabla.addEventListener("click", function(event) {

    const boton = event.target.closest("button[data-accion]");

    if (!boton) return;

    const indice = Number(boton.dataset.indice);
    const accion = boton.dataset.accion;

    const reservaciones = obtenerReservaciones();
    const cita = reservaciones[indice];

    if (!cita) return;

    if (accion === "cancelar") {

        const confirmar = confirm(
            "¿Estás seguro de cancelar esta cita?"
        );

        if (confirmar) {

            cita.estado = "Cancelada";
            guardarReservaciones(reservaciones);
            mostrarMisCitas();

            alert("La cita fue cancelada correctamente.");

        }

    }

    if (accion === "reprogramar") {

        const nuevaFecha = prompt(
            "Ingresa la nueva fecha (AAAA-MM-DD):",
            cita.fecha
        );

        if (nuevaFecha === null) return;

        if (!/^\d{4}-\d{2}-\d{2}$/.test(nuevaFecha)) {
            alert("Ingresa una fecha válida.");
            return;
        }

        const fechaComprobada = new Date(
            nuevaFecha + "T00:00:00"
        );

        if (
            Number.isNaN(fechaComprobada.getTime()) ||
            fechaComprobada.toISOString().slice(0, 10) !== nuevaFecha
        ) {
            alert("La fecha no es válida.");
            return;
        }

        const hoy = new Date();
        hoy.setHours(0, 0, 0, 0);

        if (fechaComprobada < hoy) {
            alert("No puedes seleccionar una fecha anterior a hoy.");
            return;
        }

        const nuevaHora = prompt(
            "Ingresa el nuevo horario (ejemplo: 08:00 AM):",
            cita.hora
        );

        if (nuevaHora === null) return;

        const horariosPermitidos = [
            "08:00 AM", "09:00 AM", "10:00 AM",
            "11:00 AM", "01:00 PM", "02:00 PM",
            "03:00 PM", "04:00 PM"
        ];

        if (!horariosPermitidos.includes(nuevaHora)) {
            alert("Selecciona uno de los horarios disponibles del sistema.");
            return;
        }

        const horarioOcupado = reservaciones.some(function(otra, i) {
            return i !== indice &&
                otra.estado !== "Cancelada" &&
                otra.servicio === cita.servicio &&
                otra.fecha === nuevaFecha &&
                otra.hora === nuevaHora;
        });

        if (horarioOcupado) {
            alert("Ese horario ya está ocupado. Selecciona otro.");
            return;
        }

        cita.fecha = nuevaFecha;
        cita.hora = nuevaHora;

        guardarReservaciones(reservaciones);
        mostrarMisCitas();

        alert("La cita fue reprogramada correctamente.");

    }

});

// Ejecutar al abrir la pantalla
mostrarMisCitas();
