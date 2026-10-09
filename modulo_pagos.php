<?php
$page_title  = 'Pagos';
$page_sub    = 'Mensualidades e inscripciones';
$active_link = 'pagos';
include __DIR__.'/layout/head.php';
if(!in_array($_rol,['superadmin','admin'])){
    echo '<script>window.location="index.php";</script>'; exit;
}
$con = db();
$alumnos_list = [];
$ra = mysqli_query($con, "SELECT id,nombre,apellido,cedula FROM alumnos WHERE activo=1 ORDER BY apellido,nombre");
while($f = mysqli_fetch_assoc($ra)) $alumnos_list[] = $f;
mysqli_close($con);
?>

<div class="card" style="margin-bottom:1.2rem;background:linear-gradient(135deg,var(--lime2) 0%,#15803d 100%);border:none;">
  <div class="card-body" style="padding:1.2rem 1.4rem;display:flex;align-items:center;gap:1rem;flex-wrap:wrap;">
    <div style="width:46px;height:46px;border-radius:50%;background:rgba(255,255,255,.18);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
      <i class="bx bx-dollar-circle" style="font-size:1.5rem;color:#fff;"></i>
    </div>
    <div style="flex:1;min-width:240px;">
      <div style="font-weight:800;color:#fff;font-size:.95rem;">¿Cómo funciona?</div>
      <div style="font-size:.82rem;color:rgba(255,255,255,.92);line-height:1.5;margin-top:.15rem;">
        Registrás un cobro (mensualidad o inscripción) para un alumno. El alumno lo ve en su portal y sube su comprobante de pago, o vos lo marcás "Pagado" directo si te pagó en efectivo. Los datos bancarios/pago móvil que ve el alumno son los mismos que ya configuraste en Biblioteca › Datos de Pago.
      </div>
    </div>
    <div style="display:flex;flex-direction:column;gap:.5rem;flex-shrink:0;">
      <a href="modulo_biblioteca.php" class="btn" style="background:#fff;color:#15803d;font-weight:800;" onclick="sessionStorage.setItem('ibbs_bib_tab','pago')">
        <i class="bx bx-credit-card"></i> Ver Datos de Pago
      </a>
      <button class="btn" style="background:rgba(255,255,255,.18);color:#fff;font-weight:700;" onclick="IbbsTour.replay(window.IBBS_TOUR_PAGOS)">
        <i class="bx bx-play-circle"></i> Ver tutorial
      </button>
    </div>
  </div>
</div>

<div id="pagosInfoCards" data-tour="pagos-resumen" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:.8rem;margin-bottom:1.4rem;">
  <div class="empty-row" style="grid-column:1/-1;"><span class="spin"></span></div>
</div>

<div class="card">
  <div class="card-head" style="flex-wrap:wrap;gap:.6rem;">
    <h3>Cobros registrados</h3>
    <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
      <select id="fTipo" onchange="loadPagos()" style="padding:.5rem .8rem;border:1.5px solid var(--border);border-radius:8px;font-size:.82rem;">
        <option value="">Todos los tipos</option>
        <option value="mensualidad">Mensualidad</option>
        <option value="inscripcion">Inscripción</option>
      </select>
      <select id="fEstado" onchange="loadPagos()" style="padding:.5rem .8rem;border:1.5px solid var(--border);border-radius:8px;font-size:.82rem;">
        <option value="">Todos los estados</option>
        <option value="pendiente">Pendiente</option>
        <option value="en_revision">En revisión</option>
        <option value="pagado">Pagado</option>
        <option value="rechazado">Rechazado</option>
      </select>
      <button class="btn btn-secondary" onclick="IbbsExport.table('#tablaPagos','historial-pagos')" title="Descargar la planilla completa (según los filtros de arriba) en Excel/CSV">
        <i class="bx bx-download"></i> Excel
      </button>
      <button class="btn btn-secondary" onclick="exportarPagosPDF()" title="Genera un reporte en PDF con el membrete institucional, con los filtros aplicados">
        <i class="bx bx-file-pdf"></i> PDF
      </button>
      <button class="btn btn-primary" onclick="openModal('mCrearPago')" data-tour="pagos-registrar">
        <i class="bx bx-plus"></i> Registrar cobro
      </button>
    </div>
  </div>
  <div id="barraLotePagos" style="display:none;align-items:center;gap:.5rem;flex-wrap:wrap;background:var(--cream);border-bottom:1px solid var(--border);padding:.6rem 1rem;" data-tour="pagos-lote">
    <span id="loteCount" style="font-size:.82rem;font-weight:700;color:var(--ink2);">0 seleccionados</span>
    <button class="btn btn-sm btn-success" onclick="loteAccionPagos('marcar_pagado')" title="Marca como pagados los seleccionados que estén en estado Pendiente">✓ Marcar pagados</button>
    <button class="btn btn-sm btn-success" onclick="loteAccionPagos('aprobar')" title="Aprueba el comprobante de los seleccionados que estén En revisión">✓ Aprobar</button>
    <button class="btn btn-sm btn-danger" onclick="loteAccionPagos('rechazar')" title="Rechaza el comprobante de los seleccionados que estén En revisión">✕ Rechazar</button>
    <button class="btn btn-sm btn-danger" onclick="loteAccionPagos('eliminar')" title="Elimina los cobros seleccionados">🗑 Eliminar</button>
    <button class="btn btn-sm btn-secondary" onclick="limpiarSeleccionPagos()" style="margin-left:auto;">Cancelar selección</button>
  </div>
  <div class="tbl-wrap">
    <table id="tablaPagos">
      <thead><tr>
        <th style="width:34px;text-align:center;"><input type="checkbox" id="chkAllPagos" onchange="toggleAllPagos(this)" title="Seleccionar todo"></th>
        <th style="text-align:left;">Alumno</th><th>Tipo</th><th style="text-align:left;">Concepto</th><th>Monto</th><th>Comprobante</th><th>Estado</th><th>Fecha</th><th>Acciones</th></tr></thead>
      <tbody id="tbodyPagos"><tr class="empty-row"><td colspan="9"><span class="spin"></span></td></tr></tbody>
    </table>
  </div>
</div>

<!-- MODAL: registrar cobro -->
<div class="modal-backdrop" id="mCrearPago">
  <div class="modal">
    <div class="modal-head"><h3>Registrar cobro</h3>
      <button class="modal-close" onclick="closeModal('mCrearPago')"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
    </div>
    <div class="modal-body">
      <form id="fCrearPago" onsubmit="submitCrearPago(event)">
        <div class="form-grid" style="margin-bottom:1rem;">
          <div class="field field-full">
            <label>Alumno *</label>
            <select name="alumno_id" required>
              <option value="">— Seleccionar —</option>
              <?php foreach($alumnos_list as $al): ?>
              <option value="<?=$al['id']?>"><?=htmlspecialchars($al['apellido'].', '.$al['nombre'].' — CI: '.$al['cedula'])?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field"><label>Tipo *</label>
            <select name="tipo" required>
              <option value="mensualidad">Mensualidad</option>
              <option value="inscripcion">Inscripción</option>
            </select>
          </div>
          <div class="field"><label>Monto *</label><input name="monto" type="number" min="0.01" step="0.01" required placeholder="50.00"></div>
          <div class="field field-full"><label>Concepto *</label><input name="concepto" required placeholder="Mensualidad Octubre 2026"></div>
          <div class="field field-full"><label>Vence (opcional)</label><input name="fecha_vencimiento" type="date"></div>
        </div>
        <div style="display:flex;justify-content:flex-end;gap:.6rem;">
          <button type="button" class="btn btn-secondary" onclick="closeModal('mCrearPago')">Cancelar</button>
          <button type="submit" class="btn btn-primary">Registrar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- MODAL: ver comprobante -->
<div class="modal-backdrop" id="mVerComprobantePago">
  <div class="modal" style="max-width:500px;">
    <div class="modal-head"><h3>Comprobante de pago</h3>
      <button class="modal-close" onclick="closeModal('mVerComprobantePago')"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
    </div>
    <div class="modal-body" style="text-align:center;">
      <img id="imgComprobantePago" src="" style="max-width:100%;border-radius:10px;border:1.5px solid var(--border);">
    </div>
  </div>
</div>

<script>
document.addEventListener('ibbs:ready', () => { loadPagos(); loadPagosResumen(); });

async function loadPagosResumen(){
  const d = await ajax('pago_resumen', {}, 'api/pagos.php');
  if(!d?.ok) return;
  const r = d.data;
  const pendientes = r.pendiente.cnt + r.en_revision.cnt;
  const pagados = r.pagado.cnt;
  const totalPendiente = r.pendiente.total + r.en_revision.total;
  const totalCobrado = r.pagado.total;
  document.getElementById('pagosInfoCards').innerHTML = [
    {l:'Cobros pendientes', v: pendientes, c:'#d97706', bg:'#fffbeb', i:'bx-time-five'},
    {l:'Cobros pagados', v: pagados, c:'#16a34a', bg:'#f0fdf4', i:'bx-check-circle'},
    {l:'Monto pendiente', v: '$'+totalPendiente.toFixed(2), c:'#d97706', bg:'#fffbeb', i:'bx-wallet'},
    {l:'Monto cobrado', v: '$'+totalCobrado.toFixed(2), c:'#16a34a', bg:'#f0fdf4', i:'bx-trending-up'},
  ].map(i=>`
    <div style="background:${i.bg};border:1px solid var(--border);border-radius:10px;padding:.85rem 1rem;display:flex;align-items:center;gap:.7rem;">
      <i class="bx ${i.i}" style="font-size:1.3rem;color:${i.c};flex-shrink:0;"></i>
      <div>
        <div style="font-size:1.4rem;font-weight:800;color:${i.c};line-height:1;">${i.v}</div>
        <div style="font-size:.62rem;text-transform:uppercase;letter-spacing:.7px;color:var(--muted);margin-top:3px;">${i.l}</div>
      </div>
    </div>`).join('');
}

function badgePago(estado){
  const m = {pendiente:'b-tardanza', en_revision:'b-profesor', pagado:'b-activo', rechazado:'b-inactivo'};
  const l = {pendiente:'Pendiente', en_revision:'En revisión', pagado:'Pagado', rechazado:'Rechazado'};
  return `<span class="badge ${m[estado]||'b-tardanza'}">${l[estado]||estado}</span>`;
}

async function loadPagos(){
  const tipo = document.getElementById('fTipo').value;
  const estado = document.getElementById('fEstado').value;
  const d = await ajax('pago_list', {tipo, estado}, 'api/pagos.php');
  const tb = document.getElementById('tbodyPagos');
  limpiarSeleccionPagos();
  if(!d?.ok){ tb.innerHTML = `<tr class="empty-row"><td colspan="9">${d?.msg||'Error'}</td></tr>`; return; }
  if(!d.data.length){ tb.innerHTML = '<tr class="empty-row"><td colspan="9">Sin cobros registrados todavía.</td></tr>'; return; }
  tb.innerHTML = d.data.map(p => `<tr>
    <td style="text-align:center;"><input type="checkbox" class="chkPago" value="${p.id}" onchange="actualizarBarraLotePagos()"></td>
    <td style="text-align:left;">${h(p.alumno_apellido)}, ${h(p.alumno_nombre)}<br><span style="font-size:.72rem;color:var(--muted);">CI: ${h(p.alumno_cedula)}</span></td>
    <td style="text-align:center;">${p.tipo==='mensualidad'?'Mensualidad':'Inscripción'}</td>
    <td style="text-align:left;">${h(p.concepto)}</td>
    <td style="text-align:center;">$${parseFloat(p.monto).toFixed(2)}</td>
    <td style="text-align:center;">${p.comprobante?`<button class="btn btn-sm btn-secondary" onclick="verComprobantePago('${p.comprobante}')">🧾 Ver</button>`:'—'}</td>
    <td style="text-align:center;">${badgePago(p.estado)}</td>
    <td style="text-align:center;font-size:.78rem;">${(p.creado_en||'').substring(0,10)}</td>
    <td class="td-actions">
      ${p.estado==='pendiente'?`<button class="btn btn-sm btn-success" onclick="marcarPagado(${p.id})">✓ Marcar pagado</button>`:''}
      ${p.estado==='en_revision'?`
        <button class="btn btn-sm btn-success" onclick="aprobarPago(${p.id})">✓ Aprobar</button>
        <button class="btn btn-sm btn-danger" onclick="rechazarPago(${p.id})">✕ Rechazar</button>
      `:''}
      ${p.estado!=='pagado'?`<button class="btn btn-sm btn-danger" onclick="eliminarPago(${p.id})" title="Eliminar">🗑</button>`:''}
    </td></tr>`).join('');
}

function toggleAllPagos(cb){
  document.querySelectorAll('.chkPago').forEach(c => c.checked = cb.checked);
  actualizarBarraLotePagos();
}

function actualizarBarraLotePagos(){
  const n = document.querySelectorAll('.chkPago:checked').length;
  const total = document.querySelectorAll('.chkPago').length;
  document.getElementById('chkAllPagos').checked = total>0 && n===total;
  document.getElementById('chkAllPagos').indeterminate = n>0 && n<total;
  document.getElementById('barraLotePagos').style.display = n ? 'flex' : 'none';
  if(n) document.getElementById('loteCount').textContent = n + (n===1?' seleccionado':' seleccionados');
}

function limpiarSeleccionPagos(){
  document.querySelectorAll('.chkPago').forEach(c => c.checked = false);
  const chkAll = document.getElementById('chkAllPagos');
  if(chkAll){ chkAll.checked = false; chkAll.indeterminate = false; }
  document.getElementById('barraLotePagos').style.display = 'none';
}

const LOTE_TEXTOS = {
  marcar_pagado: {title:'¿Marcar como pagados?', text:'Se marcarán como pagados los seleccionados que estén Pendientes (el resto se omite).', confirm:'Sí, marcar pagados', danger:false},
  aprobar:       {title:'¿Aprobar comprobantes?', text:'Se aprobarán los seleccionados que estén En revisión (el resto se omite).', confirm:'Sí, aprobar', danger:false},
  rechazar:      {title:'¿Rechazar comprobantes?', text:'Se rechazarán los seleccionados que estén En revisión — el alumno deberá subir uno nuevo.', confirm:'Sí, rechazar', danger:true},
  eliminar:      {title:'¿Eliminar cobros?', text:'Esta acción no se puede deshacer.', confirm:'Sí, eliminar', danger:true},
};

function exportarPagosPDF(){
  const p = new URLSearchParams({
    tipo: 'pagos',
    tipo_pago: document.getElementById('fTipo').value || '',
    estado: document.getElementById('fEstado').value || '',
  });
  window.open('api/export_lista_pdf.php?' + p.toString(), '_blank');
}

async function loteAccionPagos(sub){
  const ids = Array.from(document.querySelectorAll('.chkPago:checked')).map(c => c.value);
  if(!ids.length) return;
  const t = LOTE_TEXTOS[sub];
  const rr = await Ibbs.confirm({title:t.title, text:t.text, confirm:t.confirm, danger:t.danger});
  if(!rr.isConfirmed) return;
  const d = await ajax('pago_lote', {sub_accion: sub, ids: ids.join(',')}, 'api/pagos.php');
  if(d?.ok){ toast(d.msg); loadPagos(); loadPagosResumen(); }
  else toast(d?.msg||'Error', 'err');
}

function verComprobantePago(ruta){
  document.getElementById('imgComprobantePago').src = ruta;
  openModal('mVerComprobantePago');
}

async function submitCrearPago(e){
  e.preventDefault();
  const fd = new FormData(e.target);
  const d = await ajax('pago_crear', Object.fromEntries(fd), 'api/pagos.php');
  if(d?.ok){ toast(d.msg); closeModal('mCrearPago'); e.target.reset(); loadPagos(); loadPagosResumen(); }
  else toast(d?.msg||'Error', 'err');
}

async function marcarPagado(id){
  const rr = await Ibbs.confirm({title:'¿Marcar como pagado?', text:'Usalo cuando el alumno ya pagó en efectivo o por otro medio sin comprobante digital.', confirm:'Sí, marcar pagado'});
  if(!rr.isConfirmed) return;
  const d = await ajax('pago_marcar_pagado', {id}, 'api/pagos.php');
  if(d?.ok){ toast(d.msg); loadPagos(); loadPagosResumen(); } else toast(d?.msg||'Error', 'err');
}
async function aprobarPago(id){
  const d = await ajax('pago_aprobar', {id}, 'api/pagos.php');
  if(d?.ok){ toast(d.msg); loadPagos(); loadPagosResumen(); } else toast(d?.msg||'Error', 'err');
}
async function rechazarPago(id){
  const rr = await Ibbs.confirm({title:'¿Rechazar este comprobante?', text:'El alumno va a tener que subir uno nuevo.', confirm:'Sí, rechazar', danger:true});
  if(!rr.isConfirmed) return;
  const d = await ajax('pago_rechazar', {id}, 'api/pagos.php');
  if(d?.ok){ toast(d.msg); loadPagos(); loadPagosResumen(); } else toast(d?.msg||'Error', 'err');
}
async function eliminarPago(id){
  const rr = await Ibbs.confirm({title:'¿Eliminar este cobro?', text:'Esta acción no se puede deshacer.', confirm:'Sí, eliminar', danger:true});
  if(!rr.isConfirmed) return;
  const d = await ajax('pago_eliminar', {id}, 'api/pagos.php');
  if(d?.ok){ toast(d.msg); loadPagos(); loadPagosResumen(); } else toast(d?.msg||'Error', 'err');
}
function h(s){ const d=document.createElement('div'); d.textContent=s??''; return d.innerHTML; }

// ── Tutorial guiado de esta página ──────────────────────────────
window.IBBS_TOUR_PAGOS = {
  storageKey: 'ibbs_tour_pagos_v1',
  steps: [
    { selector: '[data-tour="pagos-resumen"]', title: 'Resumen rápido', text: 'De un vistazo: cuántos cobros están pendientes, cuántos ya se pagaron, y los montos totales.' },
    { selector: '[data-tour="pagos-registrar"]', title: 'Registrar un cobro', text: 'Elegís el alumno, si es mensualidad o inscripción, el monto y el concepto (ej. "Mensualidad Octubre 2026"). El alumno lo va a ver en su portal apenas lo registrés.' },
    { selector: '#chkAllPagos', title: 'Seleccionar todo', text: 'Marcá la casilla de un cobro, o esta de arriba para marcarlos todos, y aparece una barra para aprobar, marcar pagado o eliminar varios a la vez — ya no hace falta uno por uno.' },
  ],
  auto: true,
};
document.addEventListener('ibbs:ready', () => IbbsTour.start(window.IBBS_TOUR_PAGOS));
</script>
<?php include __DIR__.'/layout/foot.php'; ?>
