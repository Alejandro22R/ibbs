/**
 * IBBS — Paginador reusable para tablas armadas con tbody.innerHTML.
 *
 * Antes cada módulo con una lista larga (Materias, Alumnos...) tiraba
 * TODAS las filas de una, y la única forma de verlas era con scroll
 * infinito. Esto corta el array ya cargado en páginas de N filas sin
 * tocar cómo cada módulo pide los datos al servidor — solo cambia
 * cómo se renderizan.
 *
 * Uso:
 *   const pager = IbbsPaginate.create('materias', { perPage: 20 });
 *   pager.mount(document.getElementById('tbodyM'), document.getElementById('pagerM'));
 *   pager.configure({ rowHtml: fila => `<tr>...</tr>`, empty: '<tr class="empty-row">...</tr>' });
 *   pager.setData(listaCompleta); // renderiza la página 1 y el paginador
 */
(function () {
  const registry = {};

  function create(name, { perPage = 20 } = {}) {
    const state = { data: [], page: 1, perPage, renderRow: () => '', emptyHtml: '', tbodyEl: null, pagerEl: null };
    registry[name] = state;

    function totalPages() {
      return Math.max(1, Math.ceil(state.data.length / state.perPage));
    }

    function renderPager() {
      const pagerEl = state.pagerEl;
      if (!pagerEl) return;
      const tp = totalPages();
      if (tp <= 1) { pagerEl.innerHTML = ''; return; }
      const maxBtns = 5;
      let startP = Math.max(1, state.page - 2);
      let endP = Math.min(tp, startP + maxBtns - 1);
      startP = Math.max(1, endP - maxBtns + 1);
      let btns = `<button type="button" class="ibbs-pg-btn" ${state.page === 1 ? 'disabled' : ''} onclick="IbbsPaginate.go('${name}',${state.page - 1})" aria-label="Página anterior"><i class="bx bx-chevron-left"></i></button>`;
      for (let i = startP; i <= endP; i++) {
        btns += `<button type="button" class="ibbs-pg-btn ${i === state.page ? 'active' : ''}" onclick="IbbsPaginate.go('${name}',${i})">${i}</button>`;
      }
      btns += `<button type="button" class="ibbs-pg-btn" ${state.page === tp ? 'disabled' : ''} onclick="IbbsPaginate.go('${name}',${state.page + 1})" aria-label="Página siguiente"><i class="bx bx-chevron-right"></i></button>`;
      const from = state.data.length ? (state.page - 1) * state.perPage + 1 : 0;
      const to = Math.min(state.page * state.perPage, state.data.length);
      pagerEl.innerHTML = `<div class="ibbs-pager-info">${from}–${to} de ${state.data.length}</div><div class="ibbs-pager-btns">${btns}</div>`;
    }

    function renderPage() {
      const tp = totalPages();
      state.page = Math.min(Math.max(1, state.page), tp);
      const start = (state.page - 1) * state.perPage;
      const rows = state.data.slice(start, start + state.perPage);
      if (state.tbodyEl) state.tbodyEl.innerHTML = rows.length ? rows.map(state.renderRow).join('') : state.emptyHtml;
      renderPager();
    }
    state._renderPage = renderPage;

    return {
      mount(tbodyEl, pagerEl) { state.tbodyEl = tbodyEl; state.pagerEl = pagerEl; },
      configure({ rowHtml, empty }) { state.renderRow = rowHtml; if (empty !== undefined) state.emptyHtml = empty; },
      setData(rows) { state.data = rows || []; state.page = 1; renderPage(); },
      refresh() { renderPage(); },
      get data() { return state.data; },
    };
  }

  window.IbbsPaginate = {
    create,
    go(name, p) {
      const state = registry[name];
      if (!state) return;
      state.page = p;
      state._renderPage();
    },
  };
})();
