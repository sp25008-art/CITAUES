
document.addEventListener("DOMContentLoaded", () => {

    const contenedor = document.querySelector(".catalogo-grid");
    const buscador = document.getElementById("buscarServicio");
    const mensaje = document.getElementById("sinResultados");

    let servicios = [];

    // Iconos según el nombre del servicio
    function obtenerIcono(nombre) {
        const texto = nombre.toLowerCase();

        if (texto.includes("clínica") || texto.includes("salud")) {
            return "🏥";
        }

        if (texto.includes("académica")) {
            return "📚";
        }

        if (texto.includes("bienestar")) {
            return "🎓";
        }

        if (texto.includes("biblioteca")) {
            return "📖";
        }

        if (texto.includes("soporte")) {
            return "💻";
        }

        return "🏢";
    }

    // Crear tarjeta de servicio
    function crearTarjeta(servicio) {

        const tarjeta = document.createElement("article");
        tarjeta.className = "servicio-card";

        const icono = document.createElement("div");
        icono.className = "servicio-icono";
        icono.textContent = obtenerIcono(servicio.nombre);

        const titulo = document.createElement("h3");
        titulo.textContent = servicio.nombre;

        const descripcion = document.createElement("p");
        descripcion.textContent =
            servicio.descripcion || "Sin descripción disponible.";

        const unidad = document.createElement("p");
        unidad.className = "unidad-servicio";
        unidad.textContent = "Unidad: " + servicio.unidad;

        const duracion = document.createElement("p");
        duracion.className = "duracion-servicio";
        duracion.textContent =
            "Duración: " + servicio.duracion_minutos + " minutos";

        const estado = document.createElement("span");
        estado.className = "estado-servicio";
        estado.textContent =
            servicio.estado === "ACTIVO" ? "Disponible" : "No disponible";

        const enlace = document.createElement("a");
        enlace.textContent = "Consultar horarios →";
        enlace.href =
            `horarios.php?id_servicio=${encodeURIComponent(servicio.id_servicio)}`;

        tarjeta.append(
            icono,
            titulo,
            descripcion,
            unidad,
            duracion,
            estado,
            enlace
        );

        return tarjeta;
    }

    // Mostrar servicios
    function mostrarServicios(lista) {

        contenedor.replaceChildren();

        lista.forEach(servicio => {
            contenedor.appendChild(crearTarjeta(servicio));
        });

        mensaje.style.display = lista.length === 0 ? "block" : "none";

        if (lista.length === 0) {
            mensaje.textContent = "No se encontraron servicios.";
        }
    }

    // Consultar servicios en PHP
    async function cargarServicios() {

        try {

            mensaje.textContent = "Cargando servicios...";
            mensaje.style.display = "block";

            const respuesta = await fetch("../modules/servicios/listar.php", {
                method: "GET",
                credentials: "same-origin"
            });

            if (!respuesta.ok) {
                throw new Error("Error HTTP: " + respuesta.status);
            }

            const resultado = await respuesta.json();

            if (!resultado.success) {
                throw new Error(
                    resultado.mensaje || "No se pudieron cargar los servicios."
                );
            }

            servicios = resultado.servicios || [];

            mostrarServicios(servicios);

        } catch (error) {

            console.error("Error al cargar servicios:", error);

            contenedor.replaceChildren();

            mensaje.textContent =
                "No fue posible cargar los servicios. Comprueba tu conexión.";

            mensaje.style.display = "block";
        }
    }

    // Buscar servicios por nombre o descripción
    if (buscador) {
        buscador.addEventListener("input", () => {

            const texto = buscador.value.toLowerCase().trim();

            const filtrados = servicios.filter(servicio =>
                servicio.nombre.toLowerCase().includes(texto) ||
                (servicio.descripcion || "").toLowerCase().includes(texto) ||
                (servicio.unidad || "").toLowerCase().includes(texto)
            );

            mostrarServicios(filtrados);
        });
    }

    cargarServicios();

});

