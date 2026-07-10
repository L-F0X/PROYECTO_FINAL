// javascript.js

// --- Sistema de notificaciones (toast) ---------------------------------
function showToast(message, type = "success", duration = 4500) {
    if (!message) return;
    let stack = document.querySelector(".toast-stack");
    if (!stack) {
        stack = document.createElement("div");
        stack.className = "toast-stack";
        document.body.appendChild(stack);
    }

    const icons = { success: "✓", error: "✗", info: "ℹ" };
    const toast = document.createElement("div");
    toast.className = "toast" + (type !== "success" ? " toast--" + type : "");
    toast.innerHTML =
        '<span class="toast-icon">' + (icons[type] || icons.success) + '</span>' +
        '<span class="toast-message"></span>' +
        '<button type="button" class="toast-close" aria-label="Cerrar">&times;</button>';
    toast.querySelector(".toast-message").textContent = message;
    stack.appendChild(toast);

    requestAnimationFrame(() => toast.classList.add("is-visible"));

    const remove = () => {
        toast.classList.add("is-leaving");
        toast.classList.remove("is-visible");
        setTimeout(() => toast.remove(), 350);
    };

    const timer = setTimeout(remove, duration);
    toast.querySelector(".toast-close").addEventListener("click", () => {
        clearTimeout(timer);
        remove();
    });
}
window.showToast = showToast;

// --- Modal de confirmación genérico -------------------------------------
function confirmAction({ title, message, confirmLabel = "Confirmar", danger = true }) {
    return new Promise((resolve) => {
        const overlay = document.createElement("div");
        overlay.className = "modal-overlay";
        overlay.innerHTML =
            '<div class="modal-box confirm-modal-box">' +
                '<h3></h3>' +
                '<p></p>' +
                '<div class="confirm-modal-actions">' +
                    '<button type="button" class="btn btn-secondary" data-action="cancel">Cancelar</button>' +
                    '<button type="button" class="btn" data-action="confirm"></button>' +
                '</div>' +
            '</div>';
        overlay.querySelector("h3").textContent = title;
        overlay.querySelector("p").textContent = message;
        const confirmBtn = overlay.querySelector('[data-action="confirm"]');
        confirmBtn.textContent = confirmLabel;
        confirmBtn.style.cssText = danger
            ? "background-color: #dc3545; color: #fff; border: none;"
            : "background-color: #39A900; color: #fff; border: none;";

        document.body.appendChild(overlay);
        requestAnimationFrame(() => overlay.classList.add("is-open"));

        const close = (result) => {
            overlay.classList.remove("is-open");
            setTimeout(() => overlay.remove(), 300);
            resolve(result);
        };

        overlay.querySelector('[data-action="cancel"]').addEventListener("click", () => close(false));
        overlay.addEventListener("click", (e) => {
            if (e.target === overlay) close(false);
        });
        confirmBtn.addEventListener("click", () => close(true));
    });
}

document.addEventListener("DOMContentLoaded", () => {

    // 0. Convertir los banners estáticos (.profile-alert) ya renderizados
    //    por PHP en notificaciones toast animadas, para no duplicar la
    //    lógica de mensajes que cada página ya calcula en el servidor.
    document.querySelectorAll(".profile-alert").forEach(banner => {
        const text = banner.textContent.trim().replace(/^[✓✗]\s*/, "");
        const type = banner.classList.contains("error") ? "error" : "success";
        const customDuration = parseInt(banner.dataset.toastDuration || "", 10);
        if (text) {
            showToast(text, type, isNaN(customDuration) ? undefined : customDuration);
        }
        banner.remove();
    });

    // 1. Confirmación de acciones con modal estilizado (no bloqueante), en vez
    //    del confirm() nativo del navegador. Delegado en document: los
    //    resultados de búsqueda en vivo reemplazan el HTML por fetch, así que
    //    los botones nuevos no tendrían listener propio si se enlazara
    //    directamente sobre cada nodo.
    //    - .btn-eliminar: caso original (eliminar lote), con textos por defecto.
    //    - .js-confirm-submit: genérico, personalizable vía data-confirm-*.
    document.addEventListener("click", (e) => {
        const boton = e.target.closest(".btn-eliminar, .js-confirm-submit");
        if (!boton) return;
        e.preventDefault();
        const form = boton.closest("form");
        confirmAction({
            title: boton.dataset.confirmTitle || "Eliminar lote",
            message: boton.dataset.confirmMessage || "¿Está seguro de que desea eliminar este lote de requerimiento? Esta acción no se puede deshacer.",
            confirmLabel: boton.dataset.confirmLabel || "Eliminar",
            danger: boton.dataset.confirmDanger !== "false"
        }).then(confirmado => {
            if (confirmado && form) {
                form.submit();
            }
        });
    });

    // 2. Confirmación al cerrar sesión
    document.querySelectorAll(".sidebar-link--logout, .btn-logout").forEach(enlace => {
        enlace.addEventListener("click", (e) => {
            e.preventDefault();
            const destino = enlace.getAttribute("href");
            confirmAction({
                title: "Cerrar sesión",
                message: "¿Seguro que desea cerrar sesión?",
                confirmLabel: "Cerrar sesión",
                danger: false
            }).then(confirmado => {
                if (confirmado) {
                    window.location.href = destino;
                }
            });
        });
    });

    // 3. Validación e interacción del formulario de creación/edición
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

    // Pone en mayúscula solo la primera letra de cada palabra (tras un espacio
    // o al inicio del texto), sin tocar el resto de letras que ya escribió el
    // usuario (para no pelear con mayúsculas intermedias válidas en apellidos).
    function capitalizarPrimeraLetra(valor) {
        return valor.replace(/(^|\s)(\p{L})/gu, (_, previo, letra) => previo + letra.toUpperCase());
    }

    // Nombre/Apellido del usuario: solo letras, y como máximo un solo espacio
    // (dos palabras, ej. "Juan Carlos") — no varias palabras ni espacios dobles.
    const nombreApellidoInputs = document.querySelectorAll('#p-nombre, #p-apellido, #nombre, #apellido');
    nombreApellidoInputs.forEach(input => {
        input.addEventListener("input", function() {
            let valor = this.value.replace(/[^a-zA-ZáéíóúÁÉÍÓÚüÜñÑ ]/g, '');
            valor = valor.replace(/ {2,}/g, ' ');
            const partes = valor.split(' ');
            if (partes.length > 2) {
                valor = partes[0] + ' ' + partes.slice(1).join('');
            }
            this.value = capitalizarPrimeraLetra(valor);
        });
    });

    // Persona de Contacto (proveedores): solo letras y espacios, sin límite de
    // palabras (puede ser un nombre completo de varias partes).
    const contactoInputs = document.querySelectorAll('#contacto, input[name="contacto"]');
    contactoInputs.forEach(input => {
        input.addEventListener("input", function() {
            let valor = this.value;
            const regex = /[^a-zA-ZáéíóúÁÉÍÓÚüÜñÑ\s]/g;
            if (regex.test(valor)) {
                valor = valor.replace(regex, '');
            }
            this.value = capitalizarPrimeraLetra(valor);
        });
    });

    // Evitar la inserción de caracteres erróneos en Unidad de Medida (solo letras y espacios).
    // #unidad_medida ya no aplica aquí: pasó a ser un <select> de opciones fijas (Fase 51).
    const unidadMedidaInputs = document.querySelectorAll('#modal-unidad');
    unidadMedidaInputs.forEach(input => {
        input.addEventListener("input", function() {
            const regex = /[^a-zA-ZáéíóúÁÉÍÓÚüÜñÑ\s]/g;
            if (regex.test(this.value)) {
                this.value = this.value.replace(regex, '');
            }
        });
    });

    // Evitar la inserción de caracteres erróneos en NIT, Teléfono y Documento (solo números).
    // Los campos de búsqueda de UNSPSC (#id_codigo_unspsc_busqueda, #codigo_unspsc_busqueda)
    // se excluyen a propósito: se puede buscar por nombre de producto (letras) o por
    // código (números), así que no deben restringirse a solo dígitos.
    const unspscInputs = document.querySelectorAll('#nit, input[name="nit"], #telefono, input[name="telefono"], #documento, input[name="documento"]');
    unspscInputs.forEach(input => {
        input.addEventListener("input", function() {
            const regex = /[^0-9]/g;
            if (regex.test(this.value)) {
                this.value = this.value.replace(regex, '');
            }
        });
    });

    // 4. Búsqueda en vivo sin recargar la página: cualquier formulario
    //    #form-busqueda + contenedor #resultados-busqueda hace fetch de la
    //    misma URL, extrae el fragmento actualizado y lo reemplaza en el DOM,
    //    en vez de recargar toda la página (form.submit()).
    initLiveSearch();
});

function initLiveSearch() {
    const form = document.getElementById("form-busqueda");
    const results = document.getElementById("resultados-busqueda");
    if (!form || !results) return;

    const baseUrl = form.getAttribute("action") || window.location.pathname;

    async function loadResults(targetUrl) {
        results.classList.add("is-loading");
        try {
            const response = await fetch(targetUrl, { headers: { "X-Requested-With": "fetch" } });
            const html = await response.text();
            const parsed = new DOMParser().parseFromString(html, "text/html");
            const newResults = parsed.getElementById("resultados-busqueda");
            if (newResults) {
                results.innerHTML = newResults.innerHTML;
                history.replaceState(null, "", targetUrl);
            } else {
                window.location.href = targetUrl;
            }
        } catch (e) {
            // Si falla el fetch (red, etc.), recurrir a la navegación normal
            window.location.href = targetUrl;
        } finally {
            results.classList.remove("is-loading");
        }
    }

    function refresh() {
        const params = new URLSearchParams(new FormData(form));
        loadResults(baseUrl + "?" + params.toString());
    }

    let timer;
    form.querySelectorAll('input[type="text"], input[type="search"]').forEach(input => {
        input.addEventListener("input", () => {
            clearTimeout(timer);
            timer = setTimeout(refresh, 350);
        });
    });
    form.querySelectorAll("select").forEach(select => {
        select.addEventListener("change", () => {
            clearTimeout(timer);
            refresh();
        });
    });
    form.querySelectorAll('input[type="date"]').forEach(input => {
        input.addEventListener("change", () => {
            clearTimeout(timer);
            refresh();
        });
    });
    form.addEventListener("submit", (e) => {
        e.preventDefault();
        clearTimeout(timer);
        refresh();
    });

    // Enlaces de "Limpiar" dentro del formulario: vacían los campos y
    // recargan los resultados sin navegar a una página nueva.
    form.querySelectorAll("a[href]").forEach(link => {
        link.addEventListener("click", (e) => {
            e.preventDefault();
            const target = link.getAttribute("href");
            form.querySelectorAll('input[type="text"], input[type="search"], input[type="date"]').forEach(i => { i.value = ""; });
            form.querySelectorAll("select").forEach(s => { s.selectedIndex = 0; });
            clearTimeout(timer);
            loadResults(target);
        });
    });
}