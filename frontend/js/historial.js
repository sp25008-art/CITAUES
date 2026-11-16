
document.addEventListener("DOMContentLoaded", () => {
    cargarHistorial();

    document.getElementById("actualizarHistorial")
        .addEventListener("click", cargarHistorial);

    document.getElementById("filtroTipo")
        .addEventListener("change", aplicarFiltro);
});

let movimientosHistorial = [];


// =====================================
// CONSULTAR HISTORIAL
// =====================================

async function cargarHistorial() {

    const carga = document.getElementById("mensajeCargaHistorial");
    const contenedor = document.getElementById("contenedorHistorial");
    const vacio = document.getElementById("historialVacio");

    carga.hidden = false;
    carga.textContent = "Cargando historial...";
    contenedor.hidden = true;
    vacio.hidden = true;

    try {

        const respuesta = await fetch(
            "../modules/citas/listar_historial.php",
            {
                credentials: "same-origin",
                cache: "no-store"
            }
        );

        const datos = await respuesta.json();

        if (!respuesta.ok || !datos.exito) {
            throw new Error(datos.mensaje || "No se pudo cargar el historial.");
        }

        movimientosHistorial = datos.historial || [];

        actualizarResumen();
        aplicarFiltro();

        carga.hidden = true;

        if (movimientosHistorial.length === 0) {
            vacio.hidden = false;
        } else {
            contenedor.hidden = false;
        }

    } catch (error) {

        console.error("Error al cargar historial:", error);

        carga.textContent = error.message;
        carga.hidden = false;
    }
}


// =====================================
// RESUMEN
// =====================================

function actualizarResumen() {

    document.getElementById("totalMovimientos").textContent =
        movimientosHistorial.length;

    document.getElementById("totalConfirmaciones").textContent =
        movimientosHistorial.filter(
            m => m.tipo_cambio === "CONFIRMACION"
        ).length;

    document.getElementById("totalCancelaciones").textContent =
        movimientosHistorial.filter(
            m => m.tipo_cambio === "CANCELACION"
        ).length;
}


// =====================================
// FILTROS
// =====================================

function aplicarFiltro() {

    const filtro = document.getElementById("filtroTipo").value;

    const filtrados = filtro === "TODOS"
        ? movimientosHistorial
        : movimientosHistorial.filter(
            m => m.tipo_cambio === filtro
        );

    mostrarMovimientos(filtrados);
}


// =====================================
// MOSTRAR MOVIMIENTOS
// =====================================

function mostrarMovimientos(movimientos) {

    const tabla = document.getElementById("tablaHistorial");
    const contenedor = document.getElementById("contenedorHistorial");
    const vacio = document.getElementById("historialVacio");

    tabla.innerHTML = "";

    if (movimientos.length === 0) {
        contenedor.hidden = true;
        vacio.hidden = false;

        vacio.querySelector("h3").textContent =
            movimientosHistorial.length === 0
                ? "No hay movimientos registrados"
                : "No hay movimientos con este filtro";

        vacio.querySelector("p").textContent =
            movimientosHistorial.length === 0
                ? "Cuando se realicen operaciones aparecerán aquí."
                : "Selecciona otro tipo de movimiento.";

        return;
    }

    vacio.hidden = true;
    contenedor.hidden = false;

    movimientos.forEach(movimiento => {

        const fila = document.createElement("tr");

        agregarCelda(fila, `#${movimiento.id_cita}`);
        agregarCelda(fila, movimiento.servicio);

        const celdaTipo = agregarCelda(
            fila,
            traducirMovimiento(movimiento.tipo_cambio)
        );
        celdaTipo.className = "tipo-evento";

        agregarCelda(
            fila,
            movimiento.estado_anterior || "—"
        );

        const celdaEstado = agregarCelda(
            fila,
            movimiento.estado_nuevo || "—"
        );
        celdaEstado.className = "estado-evento";

        agregarCelda(
            fila,
            obtenerDetalle(movimiento)
        );

        agregarCelda(fila, movimiento.usuario);

        agregarCelda(
            fila,
            formatearFechaHora(movimiento.fecha_cambio)
        );

        tabla.appendChild(fila);
    });
}


// =====================================
// FUNCIONES AUXILIARES
// =====================================

function agregarCelda(fila, valor) {

    const celda = document.createElement("td");
    celda.textContent = valor ?? "—";

    fila.appendChild(celda);

    return celda;
}


function traducirMovimiento(tipo) {

    const nombres = {
        SOLICITUD: "Solicitud",
        CONFIRMACION: "Confirmación",
        CANCELACION: "Cancelación",
        REPROGRAMACION: "Reprogramación"
    };

    return nombres[tipo] || tipo || "Sin especificar";
}


function obtenerDetalle(movimiento) {

    if (movimiento.tipo_cambio === "REPROGRAMACION") {

        const anterior = movimiento.fecha_anterior || "Sin fecha";
        const nueva = movimiento.fecha_nueva || "Sin fecha";

        return `Fecha anterior: ${anterior} | Nueva fecha: ${nueva}`;
    }

    return movimiento.observacion || movimiento.motivo || "Sin detalle";
}


function formatearFechaHora(fecha) {

    if (!fecha) return "—";

    const fechaNormalizada = fecha.replace(" ", "T");
    const valor = new Date(fechaNormalizada);

    if (Number.isNaN(valor.getTime())) {
        return fecha;
    }

    return valor.toLocaleString("es-SV", {
        dateStyle: "short",
        timeStyle: "short"
    });
}
