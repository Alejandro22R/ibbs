/**
 * IBBS — Tutorial guiado ("manual intuitivo").
 *
 * Motor genérico y sin dependencias para mostrar una serie de tarjetas
 * que van señalando, una por una, los botones del menú lateral y
 * explicando en un texto corto para qué sirve cada uno. Se usa igual
 * en portal_alumno.php y portal_docente.php, cada uno con su propio
 * arreglo de pasos (ver <script> al final de cada portal).
 *
 * Uso:
 *   IbbsTour.start({
 *     storageKey: 'ibbs_tour_alumno_v1',   // para no repetirlo cada ingreso
 *     steps: [
 *       { selector: '[data-tour="nav-dashboard"]', title: 'Inicio', text: '...' },
 *       ...
 *     ],
 *     auto: true // si true, no arranca si storageKey ya está en localStorage
 *   });
 *
 * El botón "×" de cada tarjeta AVANZA al siguiente paso (tal como se
 * pidió). Para salir del tutorial antes de terminar hay un enlace
 * aparte, "Saltar tutorial".
 */
(function (window, document) {
    'use strict';

    var state = null;

    function seen(key) {
        try { return localStorage.getItem(key) === '1'; } catch (e) { return false; }
    }
    function markSeen(key) {
        try { localStorage.setItem(key, '1'); } catch (e) { /* localStorage no disponible — no es crítico */ }
    }

    function buildDom() {
        var overlay = document.createElement('div');
        overlay.className = 'ibbstour-overlay';

        var highlight = document.createElement('div');
        highlight.className = 'ibbstour-highlight';
        highlight.style.display = 'none';

        var card = document.createElement('div');
        card.className = 'ibbstour-card';
        card.innerHTML =
            '<button type="button" class="ibbstour-close" aria-label="Cerrar y continuar">&times;</button>' +
            '<div class="ibbstour-step"></div>' +
            '<h3 class="ibbstour-title"></h3>' +
            '<p class="ibbstour-text"></p>' +
            '<div class="ibbstour-nav">' +
                '<button type="button" class="ibbstour-prev">&larr; Atrás</button>' +
                '<button type="button" class="ibbstour-next">Siguiente &rarr;</button>' +
            '</div>' +
            '<div class="ibbstour-foot">' +
                '<a href="#" class="ibbstour-skip">Saltar tutorial</a>' +
                '<span class="ibbstour-dots"></span>' +
            '</div>';

        overlay.appendChild(highlight);
        overlay.appendChild(card);
        document.body.appendChild(overlay);

        return { overlay: overlay, highlight: highlight, card: card };
    }

    function injectStyles() {
        if (document.getElementById('ibbstour-styles')) return;
        var css = ''
            + '.ibbstour-overlay{position:fixed;inset:0;z-index:99999;background:rgba(10,20,15,.62);'
            + 'backdrop-filter:blur(1px);opacity:0;transition:opacity .2s ease;pointer-events:none;}'
            + '.ibbstour-overlay.on{opacity:1;pointer-events:auto;}'
            + '.ibbstour-highlight{position:absolute;border-radius:12px;box-shadow:0 0 0 4px #39ff14,0 0 0 9999px rgba(10,20,15,.62);'
            + 'transition:top .25s ease,left .25s ease,width .25s ease,height .25s ease;pointer-events:none;}'
            + '.ibbstour-card{position:absolute;max-width:320px;background:#f5f0e8;color:#1a4d2e;border-radius:14px;'
            + 'padding:1.1rem 1.2rem 1rem;box-shadow:0 12px 32px rgba(0,0,0,.35);font-family:"Nunito",-apple-system,sans-serif;'
            + 'opacity:0;transform:translateY(6px);transition:opacity .2s ease,transform .2s ease,top .25s ease,left .25s ease;}'
            + '.ibbstour-card.on{opacity:1;transform:translateY(0);}'
            + '.ibbstour-close{position:absolute;top:.5rem;right:.6rem;width:28px;height:28px;border:none;border-radius:50%;'
            + 'background:rgba(26,77,46,.08);color:#1a4d2e;font-size:1.25rem;line-height:1;cursor:pointer;}'
            + '.ibbstour-close:hover{background:rgba(26,77,46,.16);}'
            + '.ibbstour-step{font-size:.7rem;font-weight:800;letter-spacing:.06em;text-transform:uppercase;color:#2ecc10;margin-bottom:.3rem;}'
            + '.ibbstour-title{font-family:"Playfair Display",serif;font-size:1.15rem;margin:0 0 .35rem;padding-right:1.6rem;color:#1a4d2e;}'
            + '.ibbstour-text{font-size:.85rem;line-height:1.45;color:#3a4a3f;margin:0 0 .8rem;}'
            + '.ibbstour-nav{display:flex;align-items:center;justify-content:space-between;gap:.6rem;margin-bottom:.7rem;}'
            + '.ibbstour-prev,.ibbstour-next{font-family:inherit;font-size:.8rem;font-weight:700;border-radius:8px;padding:.4rem .8rem;cursor:pointer;}'
            + '.ibbstour-prev{background:none;border:1.5px solid #cbd5c2;color:#3a4a3f;}'
            + '.ibbstour-prev:hover{background:rgba(26,77,46,.06);}'
            + '.ibbstour-next{background:#1a4d2e;border:1.5px solid #1a4d2e;color:#fff;margin-left:auto;}'
            + '.ibbstour-next:hover{background:#1e5c36;}'
            + '.ibbstour-foot{display:flex;align-items:center;justify-content:space-between;gap:.6rem;}'
            + '.ibbstour-skip{font-size:.74rem;color:#7a8c72;text-decoration:underline;cursor:pointer;}'
            + '.ibbstour-skip:hover{color:#dc2626;}'
            + '.ibbstour-dots{display:flex;gap:4px;}'
            + '.ibbstour-dots span{width:6px;height:6px;border-radius:50%;background:#e0d8c8;display:inline-block;}'
            + '.ibbstour-dots span.on{background:#2ecc10;}'
            + '.ibbstour-replay{cursor:pointer;}'
            + '@media (max-width:640px){.ibbstour-card{max-width:calc(100vw - 2.4rem);left:1.2rem !important;right:1.2rem !important;}}';
        var style = document.createElement('style');
        style.id = 'ibbstour-styles';
        style.textContent = css;
        document.head.appendChild(style);
    }

    function positionFor(target, card, highlight) {
        var vw = window.innerWidth, vh = window.innerHeight;
        if (!target) {
            highlight.style.display = 'none';
            card.style.top = (vh / 2 - 90) + 'px';
            card.style.left = Math.max(16, vw / 2 - 160) + 'px';
            return;
        }
        var r = target.getBoundingClientRect();
        if (r.width === 0 && r.height === 0) {
            highlight.style.display = 'none';
            card.style.top = (vh / 2 - 90) + 'px';
            card.style.left = Math.max(16, vw / 2 - 160) + 'px';
            return;
        }
        highlight.style.display = 'block';
        highlight.style.top = (r.top - 6) + 'px';
        highlight.style.left = (r.left - 6) + 'px';
        highlight.style.width = (r.width + 12) + 'px';
        highlight.style.height = (r.height + 12) + 'px';

        var cardW = 320, cardH = card.offsetHeight || 180;
        var top = r.top;
        var left = r.right + 18;
        if (left + cardW > vw - 16) {
            left = r.left;
            top = r.bottom + 14;
            if (top + cardH > vh - 16) top = Math.max(16, r.top - cardH - 14);
        }
        if (top + cardH > vh - 16) top = vh - cardH - 16;
        if (top < 16) top = 16;
        if (left < 16) left = 16;

        card.style.top = top + 'px';
        card.style.left = left + 'px';
    }

    function render() {
        if (!state) return;
        var s = state, step = s.steps[s.i];
        var target = step.selector ? document.querySelector(step.selector) : null;

        s.dom.card.querySelector('.ibbstour-step').textContent = 'Paso ' + (s.i + 1) + ' de ' + s.steps.length;
        s.dom.card.querySelector('.ibbstour-title').textContent = step.title || '';
        s.dom.card.querySelector('.ibbstour-text').textContent = step.text || '';

        var dots = s.dom.card.querySelector('.ibbstour-dots');
        dots.innerHTML = '';
        s.steps.forEach(function (_, idx) {
            var d = document.createElement('span');
            if (idx === s.i) d.className = 'on';
            dots.appendChild(d);
        });

        // Botones explícitos de Atrás/Siguiente — antes la ÚNICA forma
        // de avanzar era tocar la "×" chiquita de la tarjeta, y eso
        // solo se explicaba en el primer paso (no se repetía en los
        // demás); tampoco había forma de retroceder un paso si alguien
        // lo saltaba sin querer.
        var btnPrev = s.dom.card.querySelector('.ibbstour-prev');
        var btnNext = s.dom.card.querySelector('.ibbstour-next');
        btnPrev.style.visibility = s.i === 0 ? 'hidden' : 'visible';
        btnNext.textContent = (s.i === s.steps.length - 1 ? 'Finalizar' : 'Siguiente →');

        if (target && target.scrollIntoView) {
            target.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        }
        requestAnimationFrame(function () {
            positionFor(target, s.dom.card, s.dom.highlight);
        });
    }

    function next() {
        if (!state) return;
        state.i++;
        if (state.i >= state.steps.length) { finish(); return; }
        render();
    }

    function prev() {
        if (!state || state.i === 0) return;
        state.i--;
        render();
    }

    function finish() {
        if (!state) return;
        var s = state;
        markSeen(s.storageKey);
        s.dom.overlay.classList.remove('on');
        s.dom.card.classList.remove('on');
        setTimeout(function () {
            if (s.dom.overlay.parentNode) s.dom.overlay.parentNode.removeChild(s.dom.overlay);
        }, 220);
        window.removeEventListener('resize', onResize);
        state = null;
        // Avisa a quien esté esperando (p. ej. el modal "Acerca de") que el
        // tour ya terminó, para que no se muestren dos ventanas a la vez.
        window.dispatchEvent(new CustomEvent('ibbstour:finish'));
    }

    function onResize() {
        if (state) render();
    }

    function start(opts) {
        if (state) return; // ya hay uno corriendo
        // Antes solo se descartaban los pasos sin título — un paso
        // cuyo selector apunta a un botón que ni siquiera existe para
        // este usuario (p. ej. "Asignar Materias", solo visible para
        // admin/superadmin) igual se mostraba, flotando sin resaltar
        // nada, a un profesor normal. Se descarta también si el
        // elemento que señala no está en la página.
        var steps = (opts.steps || []).filter(function (st) {
            if (!st || !st.title) return false;
            if (st.selector && !document.querySelector(st.selector)) return false;
            return true;
        });
        if (!steps.length) return;
        if (opts.auto && seen(opts.storageKey)) return;

        injectStyles();
        var dom = buildDom();
        state = { steps: steps, i: 0, dom: dom, storageKey: opts.storageKey };

        dom.card.querySelector('.ibbstour-close').addEventListener('click', next);
        dom.card.querySelector('.ibbstour-next').addEventListener('click', next);
        dom.card.querySelector('.ibbstour-prev').addEventListener('click', prev);
        dom.card.querySelector('.ibbstour-skip').addEventListener('click', function (e) {
            e.preventDefault();
            finish();
        });
        window.addEventListener('resize', onResize);

        requestAnimationFrame(function () {
            dom.overlay.classList.add('on');
            dom.card.classList.add('on');
            render();
        });
    }

    function forceStart(opts) {
        finish();
        start(Object.assign({}, opts, { auto: false }));
    }

    window.IbbsTour = { start: start, replay: forceStart, hasSeen: seen };
})(window, document);
