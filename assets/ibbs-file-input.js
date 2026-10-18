/**
 * IBBS — Botón de "elegir archivo" en español.
 *
 * El <input type="file"> nativo del navegador muestra su botón
 * ("Choose File" / "No file chosen") en el idioma configurado en el
 * navegador/sistema operativo, no en el idioma de la página — en un
 * navegador configurado en inglés eso quedaba suelto en medio de un
 * formulario cuidadosamente traducido al español. Esto envuelve cada
 * <input type="file"> simple con un botón propio ("Elegir archivo")
 * y el nombre del archivo elegido, siempre en español, sin cambiar en
 * nada el resto del formulario (mismo id/name/accept/required/onchange).
 *
 * No toca los inputs que YA tienen su propio control a medida (como
 * la foto de perfil circular, que es ella misma el área clickeable) —
 * esos llevan data-no-enhance.
 */
(function () {
    'use strict';

    function enhance(input) {
        if (input.dataset.ibbsFileEnhanced) return;
        input.dataset.ibbsFileEnhanced = '1';

        const wrap = document.createElement('span');
        wrap.style.cssText = 'display:inline-flex;align-items:center;gap:.6rem;flex-wrap:wrap;position:relative;';
        input.parentNode.insertBefore(wrap, input);
        wrap.appendChild(input);

        input.style.position = 'absolute';
        input.style.width = '1px';
        input.style.height = '1px';
        input.style.opacity = '0';
        input.style.overflow = 'hidden';
        input.style.pointerEvents = 'none';

        const btn = document.createElement('button');
        btn.type = 'button';
        btn.textContent = 'Elegir archivo';
        btn.style.cssText = 'padding:.45rem .9rem;background:var(--ink,#1a4d2e);color:#fff;border:none;border-radius:6px;font-size:.78rem;font-weight:700;cursor:pointer;flex-shrink:0;font-family:inherit;';
        btn.addEventListener('click', function () { input.click(); });

        const nombre = document.createElement('span');
        nombre.style.cssText = 'font-size:.78rem;color:var(--muted,#7a8c72);';
        nombre.textContent = 'Ningún archivo seleccionado';

        input.addEventListener('change', function () {
            nombre.textContent = (input.files && input.files.length) ? input.files[0].name : 'Ningún archivo seleccionado';
        });

        wrap.appendChild(btn);
        wrap.appendChild(nombre);
    }

    function scan(root) {
        if (!root || !root.querySelectorAll) return;
        root.querySelectorAll('input[type="file"]:not([data-no-enhance])').forEach(enhance);
    }

    document.addEventListener('DOMContentLoaded', function () { scan(document); });
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
