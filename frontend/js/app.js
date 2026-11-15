const formulario = document.getElementById("formLogin");

formulario.addEventListener("submit", function(event) {

    event.preventDefault();

    const correo = document.getElementById("correo").value.trim();
    const password = document.getElementById("password").value.trim();

    if (correo === "" || password === "") {
        alert("Debes completar todos los campos.");
        return;
    }

    // Navegar al panel principal
    window.location.href = "dashboard.html";

});
