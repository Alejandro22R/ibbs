/**
 * IBBS — Paleta de comandos (Ctrl/Cmd+K) y hoja de atajos ("?").
 *
 * Función "extra" opcional, pensada para quien usa el sistema todos
 * los días: Ctrl+K (o Cmd+K en Mac) abre un buscador para saltar a
 * cualquier sección del menú sin tocar el mouse. Los ítems se arman
 * leyendo el propio menú lateral de la página (.sb-link del panel de
 * administración, o .nav-btn de los portales), así que funciona en
 * cualquier página sin tener que mantener una lista aparte a mano.
 */
(function (window, document) {
    'use strict';

    function isTyping() {
        var el = document.activeElement;
        if (!el) return false;
        var tag = el.tagName;
        return tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || el.isContentEditable;
    }

    function detectIconLib() {
        var sample = document.querySelector('#sb .sb-link i, #sidebar .nav-btn i, nav .nav-btn i');
        if (sample && /(^|\s)bx(\s|$)|(^|\s)bx-/.test(sample.className)) return 'bx';
        return 'fas';
    }

    function collectItems() {
        var items = [];
        document.querySelectorAll('#sb .sb-link, #sidebar .nav-btn, nav .nav-btn').forEach(function (el) {
            if (el.dataset.paletteSkip) return;
            var lblEl = el.querySelector('.sb-lbl, span');
            var label = (lblEl ? lblEl.textContent : el.textContent).trim();
            if (!label) return;
            var icon = el.querySelector('i');
            items.push({ label: label, iconClass: icon ? icon.className : 'bx bx-chevron-right', run: function () { el.click(); } });
        });

        var lib = detectIconLib();
        if (typeof window.toggleTheme === 'function') {
            items.push({
                label: 'Cambiar a modo claro / oscuro',
                iconClass: lib === 'bx' ? 'bx bx-moon' : 'fas fa-moon',
                run: function () { window.toggleTheme(); }
            });
        }
        var tourCfg = window.IBBS_TOUR_ALUMNO || window.IBBS_TOUR_DOCENTE;
        if (window.IbbsTour && tourCfg) {
            items.push({
                label: 'Repasar el tutorial',
                iconClass: lib === 'bx' ? 'bx bx-help-circle' : 'fas fa-graduation-cap',
                run: function () { window.IbbsTour.replay(tourCfg); }
            });
        }
        return items;
    }

    // ── Paleta de comandos ──────────────────────────────────────
    var paletteEl = null;

    function openPalette() {
        if (paletteEl) return;
        var items = collectItems();
        var overlay = document.createElement('div');
        overlay.className = 'ibbs-pal-overlay';
        overlay.innerHTML =
            '<div class="ibbs-pal-box">' +
                '<div class="ibbs-pal-search"><i class="' + (detectIconLib() === 'bx' ? 'bx bx-search' : 'fas fa-search') + '"></i>' +
                '<input type="text" placeholder="Buscar una sección… (Esc para cerrar)" autocomplete="off"></div>' +
                '<div class="ibbs-pal-list"></div>' +
            '</div>';
        document.body.appendChild(overlay);
        paletteEl = overlay;

        var input = overlay.querySelector('input');
        var list = overlay.querySelector('.ibbs-pal-list');
        var filtered = items;
        var active = 0;

        function esc(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

        function render() {
            if (!filtered.length) { list.innerHTML = '<div class="ibbs-pal-empty">Sin resultados</div>'; return; }
            list.innerHTML = filtered.map(function (it, i) {
                return '<div class="ibbs-pal-item' + (i === active ? ' on' : '') + '" data-i="' + i + '">' +
                    '<i class="' + it.iconClass + '"></i><span>' + esc(it.label) + '</span></div>';
            }).join('');
        }
        function pick(i) {
            if (!filtered[i]) return;
            var run = filtered[i].run;
            closePalette();
            run();
        }
        input.addEventListener('input', function () {
            var q = input.value.toLowerCase().trim();
            filtered = q ? items.filter(function (it) { return it.label.toLowerCase().indexOf(q) !== -1; }) : items;
            active = 0;
            render();
        });
        list.addEventListener('click', function (e) {
            var row = e.target.closest('.ibbs-pal-item');
            if (row) pick(parseInt(row.dataset.i, 10));
        });
        overlay.addEventListener('mousedown', function (e) { if (e.target === overlay) closePalette(); });
        input.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowDown') { e.preventDefault(); active = Math.min(active + 1, filtered.length - 1); render(); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); active = Math.max(active - 1, 0); render(); }
            else if (e.key === 'Enter') { e.preventDefault(); pick(active); }
        });

        render();
        requestAnimationFrame(function () { overlay.classList.add('on'); input.focus(); });
    }
    function closePalette() {
        if (!paletteEl) return;
        paletteEl.classList.remove('on');
        var el = paletteEl; paletteEl = null;
        setTimeout(function () { if (el.parentNode) el.parentNode.removeChild(el); }, 150);
    }

    // ── Hoja de atajos ("?") ──────────────────────────────────────
    var sheetEl = null;
    function openSheet() {
        if (sheetEl) return;
        var overlay = document.createElement('div');
        overlay.className = 'ibbs-pal-overlay';
        overlay.innerHTML =
            '<div class="ibbs-pal-box ibbs-shortcuts-box">' +
                '<h3>Atajos de teclado</h3>' +
                '<div class="ibbs-shortcut-row"><span><kbd>Ctrl</kbd>/<kbd>Cmd</kbd> + <kbd>K</kbd></span><span>Buscar y saltar a cualquier sección</span></div>' +
                '<div class="ibbs-shortcut-row"><span><kbd>?</kbd></span><span>Mostrar esta ayuda</span></div>' +
                '<div class="ibbs-shortcut-row"><span><kbd>Esc</kbd></span><span>Cerrar lo que esté abierto</span></div>' +
                '<button type="button" class="btn btn-secondary ibbs-shortcuts-close" style="margin-top:1rem;width:100%;">Cerrar</button>' +
            '</div>';
        document.body.appendChild(overlay);
        sheetEl = overlay;
        overlay.addEventListener('mousedown', function (e) { if (e.target === overlay) closeSheet(); });
        overlay.querySelector('.ibbs-shortcuts-close').addEventListener('click', closeSheet);
        requestAnimationFrame(function () { overlay.classList.add('on'); });
    }
    function closeSheet() {
        if (!sheetEl) return;
        sheetEl.classList.remove('on');
        var el = sheetEl; sheetEl = null;
        setTimeout(function () { if (el.parentNode) el.parentNode.removeChild(el); }, 150);
    }

    document.addEventListener('keydown', function (e) {
        var mod = e.ctrlKey || e.metaKey;
        if (mod && (e.key === 'k' || e.key === 'K')) {
            e.preventDefault();
            if (paletteEl) closePalette(); else openPalette();
            return;
        }
        if (e.key === 'Escape') {
            if (paletteEl) closePalette();
            if (sheetEl) closeSheet();
            return;
        }
        if (e.key === '?' && !isTyping() && !paletteEl) {
            e.preventDefault();
            if (sheetEl) closeSheet(); else openSheet();
        }
    });

    window.IbbsShortcuts = { openPalette: openPalette, openSheet: openSheet };
})(window, document);
