
// =====================================
// CITAUIS - CONEXIÓN CON EL BACKEND
// =====================================

// Dirección de los módulos PHP
const API_BASE = "../modules";

// Función general para comunicarse con PHP
async function consultarAPI(ruta, opciones = {}) {

    try {

        const respuesta = await fetch(
            `${API_BASE}/${ruta}`,
            {
                credentials: "same-origin",
                ...opciones
            }
        );

        const datos = await respuesta.json();

        if (!respuesta.ok) {
            throw new Error(
                datos.message || "Error al comunicarse con el servidor"
            );
        }

        return datos;

    } catch (error) {

        console.error("Error en CITAUIS:", error);

        throw error;
    }
}
