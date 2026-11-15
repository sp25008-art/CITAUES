
// =====================================
// HISTORIAL DE CITAS - CITAUIS
// =====================================

const tablaHistorial = document.getElementById("tablaHistorial");
const historialVacio = document.getElementById("historialVacio");
const filtroEstado = document.getElementById("filtroEstado");

// Obtener reservaciones guardadas
function obtenerHistorial() {

    return JSON.parse(
        localStorage.getItem("reservacionesCITAUIS") || "[]"
    );

}

// Evitar insertar contenido como HTML
function escaparTexto(texto) {

    return String(texto || "").replace(/[&<>"']/g, function(caracter) {

        return {
            "&": "&amp;",
            "<": "&lt;",
            ">": "&gt;",
            '"': "&quot;",
            "'": "&#39;"
        }[caracter];

    });

}

// Actualizar tarjetas de resumen
function actualizarResumen(citas) {

    document.getElementById("totalCitas").textContent =
        citas.length;

    document.getElementById("citasCompletadas").textContent =
        citas.filter(cita => cita.estado === "Completada").length;

    document.getElementById("citasCanceladas").textContent =
        citas.filter(cita => cita.estado === "Cancelada").length;

}

// Mostrar historial
function mostrarHistorial() {

    const citas = obtenerHistorial();

    actualizarResumen(citas);

    tablaHistorial.innerHTML = "";

    const filtro = filtroEstado.value;

    const citasFiltradas = citas.filter(function(cita) {

        if (filtro === "Todos") {
            return true;
        }

        return cita.estado === filtro;

    });

    if (citasFiltradas.length === 0) {

        historialVacio.style.display = "block";

        if (citas.length > 0) {
            historialVacio.querySelector("h3").textContent =
                "No hay citas con este estado";

            historialVacio.querySelector("p").textContent =
                "Prueba seleccionando otro filtro.";
        } else {
            historialVacio.querySelector("h3").textContent =
                "No hay reservaciones registradas";

            historialVacio.querySelector("p").textContent =
                "Cuando realices una cita aparecerá en este historial.";
        }

        return;

    }

    historialVacio.style.display = "none";

    citasFiltradas.forEach(function(cita, indice) {

        const fila = document.createElement("tr");

        let claseEstado = "estado-pendiente";

        if (cita.estado === "Cancelada") {
            claseEstado = "estado-cancelada";
        }

        if (cita.estado === "Completada") {
            claseEstado = "estado-completada";
        }

        fila.innerHTML = `
            <td>${indice + 1}</td>
            <td>${escaparTexto(cita.servicio)}</td>
            <td>${escaparTexto(cita.fecha)}</td>
            <td>${escaparTexto(cita.hora)}</td>
            <td>${escaparTexto(cita.motivo)}</td>
            <td>
                <span class="estado-cita ${claseEstado}">
                    ${escaparTexto(cita.estado || "Pendiente")}
                </span>
            </td>
        `;

        tablaHistorial.appendChild(fila);

    });

}

// Aplicar filtro
filtroEstado.addEventListener("change", mostrarHistorial);

// Cargar información al abrir
mostrarHistorial();
