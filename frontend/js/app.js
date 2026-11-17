

const formulario = document.getElementById("formLogin");

if (formulario) {
    formulario.addEventListener("submit", async function(event) {

        event.preventDefault();

        const correo = document.getElementById("correo").value.trim();
        const password = document.getElementById("password").value;
        const boton = formulario.querySelector("button");

        if (correo === "" || password === "") {
            alert("Debes completar todos los campos.");
            return;
        }

        boton.disabled = true;
        boton.textContent = "Ingresando...";

        try {
            const respuesta = await fetch("../auth/login.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json"
                },
                credentials: "same-origin",
                body: JSON.stringify({
                    correo: correo,
                    password: password
                })
            });

            const resultado = await respuesta.json();
            if (resultado.exito) {
    window.location.href = resultado.redireccion;
} else {
    alert(resultado.mensaje);
}
            

        } catch (error) {
            console.error(error);
            alert("No se pudo conectar con el servidor.");
        } finally {
            boton.disabled = false;
            boton.textContent = "Ingresar al sistema";
        }
    });
}
