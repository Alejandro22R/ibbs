/**
 * IBBS — Polish: microinteracciones del panel admin.
 *
 * Dos cosas, ninguna toca la lógica de los módulos:
 *   1. Efecto "ripple" (ondita) al hacer clic en cualquier .btn.
 *   2. Los números marcados con [data-countup] cuentan desde su
 *      último valor hasta el nuevo en vez de cambiar de golpe —
 *      funciona también con los que un módulo llena más tarde con
 *      fetch() (por textContent directo o por innerHTML completo),
 *      porque se mira todo el documento con un MutationObserver.
 *
 * A propósito NO se engancha solo o por la clase .scard-val: index.php
 * ya trae su propio countUp() para esas tarjetas — si este script
 * también las animara, las dos animaciones se pisarían la una a la
 * otra y el número termina trabado en 0 (pasó, se encontró probando).
 * Por eso el marcado es explícito: cada módulo que lo quiera le pone
 * el atributo data-countup a su propio div.
 *
 * Todo con early-return si el valor no es un número entero simple,
 * así que nunca puede romper un texto que no sea un conteo (ej. "15 /
 * 20", "—", "75%").
 */
(function () {
  'use strict';

  // ── 1. Ripple en botones ──────────────────────────────────────
  document.addEventListener('click', function (e) {
    var btn = e.target.closest ? e.target.closest('.btn') : null;
    if (!btn || btn.disabled) return;
    try {
      var rect = btn.getBoundingClientRect();
      var size = Math.max(rect.width, rect.height) * 1.4;
      var span = document.createElement('span');
      span.className = 'ibbs-ripple';
      span.style.width = span.style.height = size + 'px';
      span.style.left = (e.clientX - rect.left - size / 2) + 'px';
      span.style.top = (e.clientY - rect.top - size / 2) + 'px';
      btn.appendChild(span);
      span.addEventListener('animationend', function () { span.remove(); });
      // Si por lo que sea nunca dispara animationend (reduced-motion
      // deshabilita la animación), igual se limpia solo.
      setTimeout(function () { if (span.parentNode) span.remove(); }, 700);
    } catch (err) { /* puramente decorativo — nunca debe romper el clic real */ }
  }, true);

  // ── 2. Conteo animado de números ──────────────────────────────
  // Ojo con el orden real de eventos: el elemento suele arrancar en
  // "0" (placeholder del propio módulo) y el valor de verdad llega
  // después, de forma asíncrona (fetch). Por eso cada animación lleva
  // un número de generación: si llega un valor nuevo mientras la
  // animación anterior todavía está en marcha, la vieja se cancela a
  // sí misma en su próximo frame en vez de pisar el valor real que
  // acaba de llegar — si no, el contador podía quedarse trabado en 0.
  function animateCount(el, startVal, target) {
    if (startVal === target) { el.textContent = String(target); return; }
    var gen = (parseInt(el.dataset.cuGen || '0', 10) + 1);
    el.dataset.cuGen = String(gen);
    var startTime = null;
    var dur = 700;
    function step(ts) {
      if (el.dataset.cuGen !== String(gen)) return; // una animación más nueva tomó el control
      if (!startTime) startTime = ts;
      var p = Math.min((ts - startTime) / dur, 1);
      var eased = 1 - Math.pow(1 - p, 3);
      el.textContent = String(Math.round(startVal + (target - startVal) * eased));
      if (p < 1) requestAnimationFrame(step);
      else el.textContent = String(target);
    }
    requestAnimationFrame(step);
  }

  function maybeAnimate(el) {
    if (!el || !el.matches || !el.matches('[data-countup]')) return;
    var txt = (el.textContent || '').trim();
    if (!/^\d{1,7}$/.test(txt)) return; // solo enteros simples — nunca toca "15 / 20", "—", texto, etc.
    var target = parseInt(txt, 10);
    if (el.dataset.cuTarget === String(target)) return; // ya está animando hacia (o llegó a) este valor
    var startVal = el.dataset.cuTarget !== undefined ? parseInt(el.dataset.cuTarget, 10) : 0;
    el.dataset.cuTarget = String(target);
    animateCount(el, startVal, target);
  }

  function scan(root) {
    if (!root || !root.querySelectorAll) return;
    var nodes = root.querySelectorAll('[data-countup]');
    for (var i = 0; i < nodes.length; i++) maybeAnimate(nodes[i]);
    // El propio nodo insertado puede ser el valor (innerHTML directo).
    if (root.matches && root.matches('[data-countup]')) maybeAnimate(root);
  }

  function boot() {
    scan(document);
    if (!window.MutationObserver) return;
    var mo = new MutationObserver(function (mutations) {
      for (var i = 0; i < mutations.length; i++) {
        var m = mutations[i];
        if (m.type !== 'childList') continue;
        // Caso A: se insertó un elemento nuevo (ej. innerHTML de golpe).
        var added = m.addedNodes;
        for (var j = 0; j < added.length; j++) {
          if (added[j].nodeType === 1) scan(added[j]);
        }
        // Caso B: `el.textContent = n` sobre un elemento que YA existía
        // (ej. index.php hace document.getElementById('sv0').textContent
        // = total) — acá lo que cambia es el texto del propio target,
        // no se agrega un elemento hijo nuevo.
        if (m.target && m.target.nodeType === 1) maybeAnimate(m.target);
      }
    });
    mo.observe(document.body, { childList: true, subtree: true });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
  document.addEventListener('ibbs:ready', function () { scan(document); });
})();
