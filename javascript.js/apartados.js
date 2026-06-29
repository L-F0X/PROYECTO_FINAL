// javascript.js

document.addEventListener("DOMContentLoaded", () => {
    
    // 1. Confirmación de eliminación con animación sutil
    const botonesEliminar = document.querySelectorAll(".btn-eliminar");
    botonesEliminar.forEach(boton => {
        boton.addEventListener("click", (e) => {
            const confirmacion = confirm("¿Está seguro de que desea eliminar este lote de requerimiento? Esta acción no se puede deshacer.");
            if (!confirmacion) {
                e.preventDefault(); // Cancela la redirección de eliminación
            }
        });
    });

    // 2. Validación e interacción del formulario de creación/edición
    const formulario = document.getElementById("formLote");
    if (formulario) {
        formulario.addEventListener("submit", (e) => {
            const nombreLote = document.getElementById("lote_nombre").value.trim();
            
            if (nombreLote.length < 5) {
                e.preventDefault();
                alert("El nombre del lote debe ser más descriptivo (mínimo 5 caracteres).");
                document.getElementById("lote_nombre").focus();
            } else {
                // Efecto visual de carga institucional antes de enviar
                const botonSubmit = formulario.querySelector("button[type='submit']");
                botonSubmit.innerHTML = "Procesando...";
                botonSubmit.style.backgroundColor = "#238276";
            }
        });
    }
});