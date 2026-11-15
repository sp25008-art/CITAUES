
document.addEventListener("DOMContentLoaded", () => {

    const contenedor = document.querySelector(".catalogo-grid");
    const buscador = document.getElementById("buscarServicio");
    const mensaje = document.getElementById("sinResultados");

    let servicios = [];

    // Iconos para los servicios
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
        descripcion.textContent = servicio.descripcion || "Sin descripción disponible.";

        const estado = document.createElement("span");
        estado.className = "estado-servicio";
        estado.textContent =
            servicio.estado === "ACTIVO" ? "Disponible" : "No disponible";

        const enlace = document.createElement("a");
        enlace.textContent = "Consultar horarios →";
        enlace.href = `horarios.html?id_servicio=${encodeURIComponent(servicio.id_servicio)}`;

        tarjeta.append(icono, titulo, descripcion, estado, enlace);

        return tarjeta;
    }

    // Mostrar servicios
    function mostrarServicios(lista) {

        contenedor.replaceChildren();

        lista.forEach(servicio => {
            contenedor.appendChild(crearTarjeta(servicio));
        });

        mensaje.style.display =
            lista.length === 0 ? "block" : "none";
    }

    // Consultar servicios en PHP
    async function cargarServicios() {

        try {

            mensaje.textContent = "Cargando servicios...";
            mensaje.style.display = "block";

            const respuesta = await consultarAPI(
                "servicios/listar.php"
            );

            if (!respuesta.success) {
                throw new Error("No se pudieron cargar los servicios.");
            }

            servicios = respuesta.servicios || [];

            mostrarServicios(servicios);

        } catch (error) {

            console.error(error);

            contenedor.replaceChildren();
            mensaje.textContent =
                "No fue posible cargar los servicios. Comprueba tu conexión.";

            mensaje.style.display = "block";
        }
    }

    // Buscador de servicios
    buscador.addEventListener("input", () => {

        const texto = buscador.value.toLowerCase().trim();

        const filtrados = servicios.filter(servicio =>
            servicio.nombre.toLowerCase().includes(texto) ||
            (servicio.descripcion || "").toLowerCase().includes(texto)
        );

        mostrarServicios(filtrados);
    });

    cargarServicios();

});

