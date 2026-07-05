// js/unspsc-autocomplete.js
// Buscador/autocompletar de códigos UNSPSC contra ajax/buscar_unspsc.php
function initUnspscAutocomplete(options) {
    const input = document.querySelector(options.inputSelector);
    const hidden = document.querySelector(options.hiddenCodeSelector);
    const results = document.querySelector(options.resultsSelector);
    if (!input || !hidden || !results) return;

    const searchUrl = options.searchUrl || '../ajax/buscar_unspsc.php';
    let debounceTimer = null;
    let currentRequest = null;

    function hideResults() {
        results.innerHTML = '';
        results.style.display = 'none';
    }

    function renderResults(items) {
        results.innerHTML = '';
        if (!items.length) {
            hideResults();
            return;
        }
        items.forEach(function (item) {
            const row = document.createElement('div');
            row.className = 'unspsc-suggestion';
            row.style.padding = '6px 10px';
            row.style.cursor = 'pointer';
            row.style.borderBottom = '1px solid #eee';
            row.style.fontSize = '13px';
            row.innerHTML = '<strong>' + item.codigo + '</strong> — ' + item.nombre;
            row.addEventListener('mousedown', function (e) {
                e.preventDefault();
                input.value = item.codigo + ' - ' + item.nombre;
                hidden.value = item.codigo;
                hideResults();
                if (typeof options.onSelect === 'function') {
                    options.onSelect(item);
                }
            });
            results.appendChild(row);
        });
        results.style.display = 'block';
    }

    input.addEventListener('input', function () {
        hidden.value = '';
        const q = input.value.trim();
        clearTimeout(debounceTimer);
        if (q.length < 2) {
            hideResults();
            return;
        }
        debounceTimer = setTimeout(function () {
            if (currentRequest) currentRequest.abort();
            const controller = new AbortController();
            currentRequest = controller;
            fetch(searchUrl + '?q=' + encodeURIComponent(q), { signal: controller.signal })
                .then(function (r) { return r.json(); })
                .then(renderResults)
                .catch(function () { /* petición cancelada o error de red, ignorar */ });
        }, 300);
    });

    document.addEventListener('click', function (e) {
        if (e.target !== input) hideResults();
    });
}
