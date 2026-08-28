<?php
$page_title  = 'Historial de Actividad';
$page_sub    = 'Registro de inscripciones, notas, asistencias y cambios del sistema';
$active_link = 'historial';
include __DIR__.'/layout/head.php';
// Acceso solo superadmin
if($_rol !== 'superadmin'){
    echo '<script>window.location="index.php";</script>'; exit;
}

?>

<!-- ═══ SUMMARY CARDS ════════════════════════════════════ -->
<div id="resCards" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:.7rem;margin-bottom:1.3rem;"></div>

<!-- ═══ TOOLBAR ══════════════════════════════════════════ -->
<div class="card" style="margin-bottom:1rem;">
  <div class="card-body" style="padding:.8rem 1.1rem;">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.7rem;">

      <!-- Filtros -->
      <div style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center;">
        <select id="filtTipo" onchange="cargar()" class="ibbs-sel">
          <option value="">Todos los tipos</option>
          <option value="nota">Notas</option>
          <option value="inscripcion">Inscripciones</option>
          <option value="asistencia">Asistencias</option>
          <option value="usuario">Sistema / Usuarios</option>
        </select>
        <input id="filtFecha" type="date" onchange="cargar()" class="ibbs-sel">
        <button class="btn btn-secondary btn-sm" onclick="limpiarFiltros()">
          <i class='bx bx-x'></i> Limpiar
        </button>
      </div>

      <!-- Acciones sobre selección -->
      <div style="display:flex;gap:.5rem;align-items:center;">
        <span id="selCount" style="font-size:.78rem;color:var(--muted);display:none;"></span>
        <button id="btnBorrarSel" class="btn btn-danger btn-sm" onclick="borrarSeleccion()" style="display:none;">
          <i class='bx bx-trash'></i> Eliminar seleccionados
        </button>
        <button class="btn btn-secondary btn-sm" onclick="exportarCSV()">
          <i class='bx bx-download'></i> Exportar CSV
        </button>
        <span id="totalBadge" style="font-size:.78rem;color:var(--muted);"></span>
      </div>
    </div>
  </div>
</div>

<!-- ═══ DATA TABLE ════════════════════════════════════════ -->
<div class="card">
  <div class="card-head" style="display:flex;justify-content:space-between;align-items:center;padding:.9rem 1.1rem;">
    <div style="display:flex;align-items:center;gap:.8rem;">
      <h3>Actividad registrada</h3>
      <span id="rangoLbl" style="font-size:.73rem;color:var(--muted);"></span>
    </div>
    <div style="display:flex;align-items:center;gap:.6rem;">
      <span style="font-size:.76rem;color:var(--muted);">Buscar:</span>
      <input id="searchBox" type="text" placeholder="Filtrar resultados…" oninput="filtrarLocal(this.value)"
        style="padding:.4rem .75rem;border:1.5px solid var(--border);border-radius:7px;font-size:.8rem;font-family:'Nunito',sans-serif;background:var(--cream);color:var(--ink);outline:none;width:180px;">
    </div>
  </div>

  <div class="tbl-wrap" style="padding:0;">
    <table id="tblHist">
      <thead>
        <tr>
          <th style="width:36px;padding:.6rem .7rem;">
            <input type="checkbox" id="chkAll" onclick="toggleAll(this.checked)"
              style="width:14px;height:14px;cursor:pointer;accent-color:var(--ink);">
          </th>
          <th class="sortable" data-col="tipo" onclick="sortBy('tipo')" style="cursor:pointer;user-select:none;">
            Tipo <i class='bx bx-sort' id="sort-tipo" style="font-size:.8rem;color:var(--muted);"></i>
          </th>
          <th class="sortable" data-col="persona" onclick="sortBy('persona')" style="cursor:pointer;user-select:none;">
            Persona <i class='bx bx-sort' id="sort-persona" style="font-size:.8rem;color:var(--muted);"></i>
          </th>
          <th>Detalle</th>
          <th style="text-align:center;width:80px;">Valor</th>
          <th class="sortable" data-col="fecha" onclick="sortBy('fecha')" style="cursor:pointer;user-select:none;text-align:center;width:130px;">
            Fecha <i class='bx bx-sort-down' id="sort-fecha" style="font-size:.8rem;color:var(--ink);"></i>
          </th>
        </tr>
      </thead>
      <tbody id="tbBody">
        <tr class="empty-row"><td colspan="6"><span class="spin"></span></td></tr>
      </tbody>
    </table>
  </div>

  <!-- Footer: paginación + info -->
  <div style="display:flex;align-items:center;justify-content:space-between;padding:.7rem 1.1rem;border-top:1px solid var(--border);flex-wrap:wrap;gap:.5rem;">
    <div style="font-size:.77rem;color:var(--muted);" id="pageInfo"></div>
    <div id="pagBtns" style="display:flex;gap:.25rem;flex-wrap:wrap;"></div>
  </div>
</div>

<style>
.ibbs-sel {
  padding:.45rem .85rem;border:1.5px solid var(--border);border-radius:8px;
  font-size:.81rem;background:var(--cream);color:var(--ink);outline:none;
  font-family:'Nunito',sans-serif;cursor:pointer;
}
.tbl-row-sel td { background:rgba(26,77,46,.04) !important; }
#tblHist thead th { cursor:default; }
#tblHist thead th.sortable:hover { color:var(--ink); }
.tipo-badge {
  display:inline-flex;align-items:center;gap:.3rem;
  padding:.18rem .55rem;border-radius:4px;
  font-size:.68rem;font-weight:700;letter-spacing:.3px;text-transform:uppercase;
}
</style>

<script>
let _raw    = [];   // all data from server
let _vis    = [];   // visible after local filter
let _sel    = new Set(); // selected row indices (in _raw)
let _page   = 1;
const PER   = 30;
let _sortCol = 'fecha';
let _sortAsc = false;

const TIPO_STYLES = {
  nota:        {bg:'#f0fdf4', color:'#15803d', label:'Nota'},
  inscripcion: {bg:'#eff6ff', color:'#1d4ed8', label:'Inscripción'},
  asistencia:  {bg:'#fffbeb', color:'#d97706', label:'Asistencia'},
  usuario:     {bg:'#f5f5f5', color:'#6b7280', label:'Sistema'},
};

async function cargar() {
  _sel.clear(); updateSelUI();
  document.getElementById('tbBody').innerHTML =
    '<tr class="empty-row"><td colspan="6"><span class="spin"></span></td></tr>';
  const tipo  = document.getElementById('filtTipo').value;
  const fecha = document.getElementById('filtFecha').value;
  const d = await ajax('historial_actividad', {tipo, fecha});
  if (!d?.ok) return;
  _raw = d.data || [];
  renderCards(d.resumen || {});
  if (d.rango) document.getElementById('rangoLbl').textContent = d.rango;
  document.getElementById('totalBadge').textContent = `${_raw.length} registros`;
  document.getElementById('searchBox').value = '';
  applySort();
}

function limpiarFiltros() {
  document.getElementById('filtTipo').value  = '';
  document.getElementById('filtFecha').value = '';
  cargar();
}

function renderCards(r) {
  document.getElementById('resCards').innerHTML = [
    {l:'Notas',        v:r.notas||0,        c:'#15803d', bg:'#f0fdf4'},
    {l:'Inscripciones',v:r.inscripciones||0,c:'#1d4ed8', bg:'#eff6ff'},
    {l:'Asistencias',  v:r.asistencias||0,  c:'#d97706', bg:'#fffbeb'},
    {l:'Sistema',      v:r.usuarios||0,     c:'#6b7280', bg:'#f5f5f5'},
  ].map(i=>`
    <div style="background:${i.bg};border:1px solid var(--border);border-radius:10px;padding:.85rem 1rem;cursor:pointer;"
         onclick="document.getElementById('filtTipo').value='${i.l==='Notas'?'nota':i.l==='Inscripciones'?'inscripcion':i.l==='Asistencias'?'asistencia':'usuario'}';cargar()">
      <div style="font-size:1.5rem;font-weight:800;color:${i.c};line-height:1;">${i.v}</div>
      <div style="font-size:.63rem;text-transform:uppercase;letter-spacing:.8px;color:var(--muted);margin-top:3px;">${i.l}</div>
    </div>`).join('');
}

// ── Sort ──────────────────────────────────────────────────
function sortBy(col) {
  if (_sortCol === col) _sortAsc = !_sortAsc;
  else { _sortCol = col; _sortAsc = col !== 'fecha'; }
  document.querySelectorAll('[id^="sort-"]').forEach(el => {
    el.className = 'bx bx-sort';
    el.style.color = 'var(--muted)';
  });
  const ico = document.getElementById('sort-'+col);
  if (ico) {
    ico.className = _sortAsc ? 'bx bx-sort-up' : 'bx bx-sort-down';
    ico.style.color = 'var(--ink)';
  }
  applySort();
}

function applySort() {
  _vis = [..._raw].sort((a,b) => {
    let va = a[_sortCol]||'', vb = b[_sortCol]||'';
    const cmp = String(va).localeCompare(String(vb), 'es', {numeric:true});
    return _sortAsc ? cmp : -cmp;
  });
  _page = 1;
  render();
}

// ── Local filter ──────────────────────────────────────────
function filtrarLocal(q) {
  const ql = q.toLowerCase();
  _vis = !ql ? [..._raw] : _raw.filter(r =>
    [r.tipo,r.persona,r.detalle,r.valor,r.fecha].join(' ').toLowerCase().includes(ql)
  );
  _page = 1; render();
}

// ── Render table ──────────────────────────────────────────
function render() {
  const tb  = document.getElementById('tbBody');
  const pag = document.getElementById('pagBtns');
  const info= document.getElementById('pageInfo');
  const slice = _vis.slice((_page-1)*PER, _page*PER);
  document.getElementById('chkAll').checked = false;

  if (!slice.length) {
    tb.innerHTML = '<tr class="empty-row"><td colspan="6">Sin registros para los filtros aplicados.</td></tr>';
    pag.innerHTML = ''; info.textContent = ''; return;
  }

  tb.innerHTML = slice.map((r, si) => {
    const ri  = _raw.indexOf(r);
    const ts  = TIPO_STYLES[r.tipo] || {bg:'#f5f5f5',color:'#6b7280',label:r.tipo};
    const sel = _sel.has(ri);
    return `<tr class="${sel?'tbl-row-sel':''}" id="row-${ri}">
      <td style="padding:.5rem .7rem;">
        <input type="checkbox" class="row-chk" data-ri="${ri}"
          ${sel?'checked':''}
          onchange="toggleRow(${ri},this.checked)"
          style="width:14px;height:14px;cursor:pointer;accent-color:var(--ink);">
      </td>
      <td>
        <span class="tipo-badge" style="background:${ts.bg};color:${ts.color};">${ts.label}</span>
      </td>
      <td><span style="font-weight:600;font-size:.84rem;">${r.persona||'—'}</span></td>
      <td style="font-size:.8rem;color:var(--muted);max-width:240px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="${r.detalle||''}">${r.detalle||'—'}</td>
      <td style="text-align:center;">
        ${r.valor ? `<strong style="font-size:.82rem;color:${r.valor_color||'var(--ink)'};">${r.valor}</strong>` : '<span style="color:var(--muted);">—</span>'}
      </td>
      <td style="text-align:center;font-size:.78rem;color:var(--muted);white-space:nowrap;">${r.fecha||'—'}</td>
    </tr>`;
  }).join('');

  // Pagination
  const total = _vis.length;
  const tp    = Math.ceil(total/PER);
  const start = (_page-1)*PER+1;
  const end   = Math.min(_page*PER, total);
  info.textContent = `Mostrando ${start}–${end} de ${total}`;
  if (tp <= 1) { pag.innerHTML=''; return; }
  const pages = [];
  for(let p=1;p<=tp;p++) {
    if (p===1||p===tp||Math.abs(p-_page)<=2) pages.push(p);
    else if (pages[pages.length-1]!=='...') pages.push('...');
  }
  pag.innerHTML = pages.map(p => p==='...'
    ? `<span style="padding:.25rem .3rem;color:var(--muted);font-size:.76rem;">…</span>`
    : `<button onclick="goPage(${p})" style="padding:.25rem .6rem;border:1px solid ${p===_page?'var(--ink)':'var(--border)'};border-radius:5px;background:${p===_page?'var(--ink)':'transparent'};color:${p===_page?'var(--lime)':'var(--ink)'};cursor:pointer;font-size:.76rem;font-family:'Nunito',sans-serif;min-width:28px;">${p}</button>`
  ).join('');
}

function goPage(p){ _page=p; render(); document.getElementById('tblHist').scrollIntoView({behavior:'smooth',block:'start'}); }

// ── Selection ─────────────────────────────────────────────
function toggleRow(ri, checked) {
  checked ? _sel.add(ri) : _sel.delete(ri);
  const row = document.getElementById('row-'+ri);
  if (row) row.className = checked ? 'tbl-row-sel' : '';
  updateSelUI();
}

function toggleAll(checked) {
  const slice = _vis.slice((_page-1)*PER, _page*PER);
  slice.forEach(r => {
    const ri = _raw.indexOf(r);
    checked ? _sel.add(ri) : _sel.delete(ri);
    const row = document.getElementById('row-'+ri);
    if (row) row.className = checked ? 'tbl-row-sel' : '';
  });
  document.querySelectorAll('.row-chk').forEach(chk => chk.checked = checked);
  updateSelUI();
}

function updateSelUI() {
  const n = _sel.size;
  const countEl = document.getElementById('selCount');
  const btnEl   = document.getElementById('btnBorrarSel');
  if (n > 0) {
    countEl.textContent = `${n} seleccionado${n>1?'s':''}`;
    countEl.style.display = 'inline';
    btnEl.style.display   = 'inline-flex';
  } else {
    countEl.style.display = 'none';
    btnEl.style.display   = 'none';
  }
}

function borrarSeleccion() {
  const n = _sel.size;
  if (!n) return;
  ibbsConfirm(
    `¿Eliminar ${n} registro${n>1?'s':''} del historial? Esta acción no se puede deshacer.`,
    async () => {
      const ids = [..._sel].map(ri => _raw[ri]?.id).filter(Boolean);
      if (!ids.length) { toast('No hay IDs válidos para eliminar.','err'); return; }
      const d = await ajax('historial_delete', {ids: ids.join(',')});
      if (d?.ok) {
        toast(d.msg);
        _sel.clear();
        updateSelUI();
        cargar();
      } else toast(d?.msg||'Error al eliminar','err');
    }
  );
}

// ── Export CSV ────────────────────────────────────────────
function exportarCSV() {
  const data = _sel.size > 0 ? [..._sel].map(i => _raw[i]) : _vis;
  if (!data.length) { toast('No hay datos para exportar.','err'); return; }
  const headers = ['Tipo','Persona','Detalle','Valor','Fecha'];
  const rows = data.map(r => [
    r.tipo_label||r.tipo, r.persona||'', r.detalle||'', r.valor||'', r.fecha||''
  ].map(v => `"${String(v).replace(/"/g,'""')}"`).join(','));
  const csv = [headers.join(','), ...rows].join('\n');
  const blob = new Blob(['\ufeff'+csv], {type:'text/csv;charset=utf-8;'});
  const url  = URL.createObjectURL(blob);
  const a    = document.createElement('a');
  a.href = url; a.download = `historial_ibbs_${new Date().toISOString().slice(0,10)}.csv`;
  a.click(); URL.revokeObjectURL(url);
  toast(`${data.length} registros exportados a CSV.`);
}

document.addEventListener('ibbs:ready', () => cargar());
</script>

<?php include __DIR__.'/layout/foot.php'; ?>
