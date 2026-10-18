/**
 * IBBS — Indicador de "hay más abajo" en menús con scroll interno.
 *
 * El menú lateral (de los 3 portales) tiene más opciones de las que
 * caben en pantalla, y el resto solo se ve haciendo scroll DENTRO del
 * propio menú — sin esto, no había ningún indicio visual (sombra,
 * flecha, barra) de que hubiera más contenido abajo, así que alguien
 * sin experiencia asumía que la lista visible era todo el menú y
 * nunca descubría las opciones de más abajo (Mi Perfil, Devocionales,
 * Ver Tutorial, etc.)
 *
 * Uso: agregar data-scroll-hint a cualquier contenedor con
 * overflow-y:auto (opcionalmente data-scroll-hint-color="#rrggbb"
 * para que el degradado combine con el fondo real del contenedor —
 * por defecto usa un verde oscuro que combina con el sidebar de los 3
 * portales).
 */
(function () {
    'use strict';

    function ensureHint(container) {
        if (container.dataset.scrollHinted) return;
        container.dataset.scrollHinted = '1';

        var wrap = container.parentElement;
        if (!wrap) return;
        if (getComputedStyle(wrap).position === 'static') wrap.style.position = 'relative';

        // Un degradado a un color sólido solo se ve si ese color coincide
        // con el fondo real donde cae — en el sidebar oscuro del portal
        // del docente el verde por defecto quedaba casi idéntico al fondo
        // y el aviso no se notaba. Un degradado a negro semitransparente
        // sí oscurece visiblemente sobre cualquier fondo, claro u oscuro,
        // sin tener que adivinar el color exacto de cada contenedor.
        var color = container.dataset.scrollHintColor || 'rgba(0,0,0,.55)';
        var fade = document.createElement('div');
        fade.setAttribute('aria-hidden', 'true');
        fade.style.cssText =
            'position:absolute;left:0;right:6px;bottom:0;height:36px;pointer-events:none;' +
            'opacity:0;transition:opacity .2s ease;z-index:5;' +
            'background:linear-gradient(to bottom, transparent, ' + color + ');';
        wrap.appendChild(fade);

        function update() {
            var hayMasAbajo = container.scrollHeight - container.scrollTop - container.clientHeight > 4;
            fade.style.opacity = hayMasAbajo ? '1' : '0';
        }

        update();
        container.addEventListener('scroll', update, { passive: true });
        window.addEventListener('resize', update);
        if (window.MutationObserver) {
            new MutationObserver(update).observe(container, { childList: true, subtree: true });
        }
    }

    function scan(root) {
        if (!root || !root.querySelectorAll) return;
        root.querySelectorAll('[data-scroll-hint]').forEach(ensureHint);
    }

    document.addEventListener('DOMContentLoaded', function () { scan(document); });
})();
