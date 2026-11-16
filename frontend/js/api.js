
const API_BASE = "../modules";

async function consultarAPI(ruta, opciones = {}) {
    try {
        const respuesta = await fetch(`${API_BASE}/${ruta}`, {
            credentials: "same-origin",
            ...opciones
        });

        const datos = await respuesta.json();

        if (!respuesta.ok || datos.success === false) {
            throw new Error(
                datos.mensaje ||
                datos.message ||
                "Error al comunicarse con el servidor"
            );
        }

        return datos;

    } catch (error) {
        console.error("Error en CITAUIS:", error);
        throw error;
    }
}

