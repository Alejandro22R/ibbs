/**
 * IBBS — Ícono de "ojo" para mostrar/ocultar contraseña.
 *
 * Envuelve automáticamente cada <input type="password"> de la página con
 * un botoncito que alterna entre password/text, sin tener que tocar cada
 * formulario a mano. Usa SVG inline (no depende de FontAwesome, ya que
 * login.php no lo carga). Estilos en assets/ibbs.css (.ibbs-pw-wrap / .ibbs-pw-eye).
 */
(function (window, document) {
    'use strict';

    var EYE = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
    var EYE_OFF = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a21.8 21.8 0 0 1 5.06-6.06M9.9 4.24A10.94 10.94 0 0 1 12 4c7 0 11 8 11 8a21.8 21.8 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';

    function wrap(input) {
        if (input.dataset.ibbsPwWrapped) return;
        if (!input.parentNode) return;
        input.dataset.ibbsPwWrapped = '1';

        var wrapper = document.createElement('div');
        wrapper.className = 'ibbs-pw-wrap';
        input.parentNode.insertBefore(wrapper, input);
        wrapper.appendChild(input);

        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'ibbs-pw-eye';
        btn.setAttribute('aria-label', 'Mostrar contraseña');
        btn.innerHTML = EYE;
        btn.addEventListener('click', function () {
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            btn.innerHTML = show ? EYE_OFF : EYE;
            btn.setAttribute('aria-label', show ? 'Ocultar contraseña' : 'Mostrar contraseña');
        });
        wrapper.appendChild(btn);
    }

    function scan(root) {
        (root || document).querySelectorAll('input[type="password"]').forEach(wrap);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { scan(document); });
    } else {
        scan(document);
    }

    window.IbbsPasswordToggle = { scan: scan };
})(window, document);
