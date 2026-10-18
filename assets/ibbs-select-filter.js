/**
 * IBBS — Buscador sobre selectores largos (data-filtrable).
 *
 * Algunos <select> listan TODAS las materias del instituto (70+) en
 * una sola lista plana — desplazarse por un <select> nativo tan largo
 * es tedioso y propenso a error de clic para alguien con poca
 * destreza con el mouse. Esto agrega un campo de texto justo encima
 * de cualquier <select data-filtrable="placeholder…"> que, al
 * escribir, oculta las opciones que no coincidan — sin tocar en nada
 * el <select> en sí (mismo id, value, onchange de siempre), así que
 * no hace falta cambiar el resto del código que ya lo usa.
 */
(function () {
    'use strict';

    function attachFilter(select) {
        if (select.dataset.filtrado) return;
        select.dataset.filtrado = '1';

        var wrap = document.createElement('div');
        select.parentNode.insertBefore(wrap, select);
        wrap.appendChild(select);

        var input = document.createElement('input');
        input.type = 'text';
        input.placeholder = select.dataset.filtrable || 'Buscar…';
        input.autocomplete = 'off';
        input.className = 'ibbs-select-filter-input';
        input.style.cssText = 'width:100%;margin-bottom:.35rem;padding:.55rem .7rem;border:1.5px solid var(--border);border-radius:8px;font-size:.82rem;box-sizing:border-box;background:var(--paper);color:var(--ink);';
        wrap.insertBefore(input, select);

        var originalOptions = Array.prototype.slice.call(select.options);

        input.addEventListener('input', function () {
            var q = input.value.trim().toLowerCase();
            originalOptions.forEach(function (opt, i) {
                var esPlaceholder = i === 0; // la primera opción ("— Seleccionar —", "Todas las materias"…) siempre visible, tenga o no value=""
                opt.hidden = !esPlaceholder && !!q && opt.textContent.toLowerCase().indexOf(q) === -1;
            });
        });
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); select.focus(); }
        });
    }

    function scan(root) {
        if (!root || !root.querySelectorAll) return;
        root.querySelectorAll('select[data-filtrable]').forEach(attachFilter);
    }

    document.addEventListener('DOMContentLoaded', function () { scan(document); });

    // Algunos selects se llenan de opciones después (fetch) — igual
    // conviene engancharlos apenas aparecen en el DOM.
    if (window.MutationObserver) {
        new MutationObserver(function (mutations) {
            mutations.forEach(function (m) {
                m.addedNodes && m.addedNodes.forEach(function (n) {
                    if (n.nodeType === 1) scan(n);
                });
            });
        }).observe(document.documentElement, { childList: true, subtree: true });
    }
})();
