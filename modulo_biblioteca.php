<?php
$page_title = 'Biblioteca';
$page_sub   = 'Catálogo de libros del instituto';
$active_link = 'biblioteca';
include __DIR__.'/layout/head.php';

if(!in_array($_rol,['superadmin','admin','profesor'])){
    echo '<script>window.location="index.php";</script>'; exit;
}
$esAdmin = in_array($_rol,['superadmin','admin']);
?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.2rem;flex-wrap:wrap;gap:.6rem;">
  <div class="tabs-nav" style="display:flex;gap:.4rem;flex-wrap:wrap;">
    <button class="btn btn-secondary tab-btn act" data-tab="catalogo" onclick="showTab('catalogo',this)">📚 <?=$esAdmin?'Catálogo':'Mis Libros'?></button>
    <?php if($esAdmin): ?>
    <button class="btn btn-secondary tab-btn" data-tab="compras" onclick="showTab('compras',this)">🧾 Solicitudes de Compra</button>
    <button class="btn btn-secondary tab-btn" data-tab="pago" onclick="showTab('pago',this)">💳 Datos de Pago</button>
    <?php endif; ?>
    <button class="btn btn-secondary" onclick="IbbsExport.table('#tblLibros','biblioteca-ibbs')" title="Descargar esta lista como Excel/CSV">
      <i class="bx bx-download"></i> Excel
    </button>
    <button class="btn btn-secondary" onclick="IbbsTour.replay(window.IBBS_TOUR_BIBLIOTECA)" title="Ver el tutorial de esta página otra vez">
      <i class="bx bx-play-circle"></i> Tutorial
    </button>
  </div>
  <button class="btn btn-primary" onclick="abrirNuevoLibro()" data-tour="bib-nuevo"><svg viewBox="0 0 24 24" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> Nuevo Libro</button>
</div>

<!-- TAB: CATÁLOGO -->
<div id="tab-catalogo" class="tab-pane">
  <div class="card" data-tour="bib-tabla">
    <div class="card-head"><h3><?=$esAdmin?'Todos los libros':'Los libros que subiste'?></h3></div>
    <div id="barraLoteLibros" style="display:none;align-items:center;gap:.5rem;flex-wrap:wrap;background:var(--cream);border-bottom:1px solid var(--border);padding:.6rem 1rem;">
      <span id="loteLibrosCount" style="font-size:.82rem;font-weight:700;color:var(--ink2);">0 seleccionados</span>
      <button class="btn btn-sm btn-danger" onclick="eliminarLibrosSeleccionados()">🗑 Eliminar seleccionados</button>
      <button class="btn btn-sm btn-secondary" onclick="limpiarSeleccionLibros()" style="margin-left:auto;">Cancelar selección</button>
    </div>
    <div class="tbl-wrap">
      <table id="tblLibros">
        <thead><tr>
          <th style="width:34px;text-align:center;"><input type="checkbox" id="chkAllLibros" onchange="toggleAllLibros(this)" title="Seleccionar todo"></th>
          <th></th><th>Título</th><th>Autor</th><th>Categoría</th><th>Precio</th><?php if($esAdmin):?><th>Subido por</th><?php endif;?><th>Estado</th><th>Acciones</th></tr></thead>
        <tbody id="tbodyLibros"><tr class="empty-row"><td colspan="9"><span class="spin"></span></td></tr></tbody>
      </table>
    </div>
  </div>
</div>

<?php if($esAdmin): ?>
<!-- TAB: SOLICITUDES DE COMPRA -->
<div id="tab-compras" class="tab-pane" style="display:none;">
  <div class="card">
    <div class="card-head">
      <h3>Solicitudes de Compra</h3>
      <select id="filtroEstadoCompra" onchange="loadCompras()" style="padding:.5rem .8rem;border:1.5px solid var(--border);border-radius:8px;font-size:.82rem;">
        <option value="pendiente">Pendientes</option>
        <option value="activado">Activadas</option>
        <option value="rechazado">Rechazadas</option>
      </select>
    </div>
    <div class="tbl-wrap">
      <table>
        <thead><tr><th>Alumno</th><th>Libro</th><th>Precio</th><th>Fecha</th><th>Comprobante</th><th>Estado</th><th>Acciones</th></tr></thead>
        <tbody id="tbodyCompras"><tr class="empty-row"><td colspan="7"><span class="spin"></span></td></tr></tbody>
      </table>
    </div>
  </div>
</div>

<!-- TAB: DATOS DE PAGO -->
<div id="tab-pago" class="tab-pane" style="display:none;">
  <div class="card" style="max-width:560px;">
    <div class="card-head"><h3>Datos de Pago de la Institución</h3></div>
    <div class="card-body">
      <p style="font-size:.82rem;color:var(--muted);margin-bottom:1rem;">Esto es lo que ve el alumno al momento de comprar un libro. Dejá en blanco lo que todavía no tengas listo.</p>
      <form id="formDatosPago" onsubmit="guardarDatosPago(event)">
        <div class="form-grid" style="margin-bottom:1rem;">
          <div class="field"><label>Titular de la cuenta</label><input name="titular" id="dpTitular"></div>
          <div class="field"><label>Banco</label><input name="banco" id="dpBanco"></div>
          <div class="field"><label>N° de cuenta</label><input name="cuenta" id="dpCuenta"></div>
          <div class="field"><label>Cédula / RIF</label><input name="cedula_rif" id="dpCedula"></div>
          <div class="field field-full"><label>Teléfono para Pago Móvil</label><input name="pago_movil_telefono" id="dpTelefono"></div>
          <div class="field field-full"><label>Instrucciones adicionales</label><textarea name="instrucciones" id="dpInstrucciones" rows="3"></textarea></div>
        </div>
        <button type="submit" class="btn btn-primary">Guardar Datos de Pago</button>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- MODAL NUEVO/EDITAR LIBRO -->
<div class="modal-backdrop" id="mLibro">
  <div class="modal">
    <div class="modal-head"><h3 id="mLibroTitulo">Nuevo Libro</h3>
      <button class="modal-close" onclick="closeModal('mLibro')"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
    </div>
    <div class="modal-body">
      <form id="fLibro" onsubmit="guardarLibro(event)">
        <input type="hidden" id="lId">
        <div class="form-grid" style="margin-bottom:1rem;">
          <div class="field field-full"><label>Título *</label><input name="titulo" id="lTitulo" placeholder="El Peregrino"></div>
          <div class="field"><label>Autor</label><input name="autor" id="lAutor" placeholder="John Bunyan"></div>
          <div class="field"><label>Categoría</label><input name="categoria" id="lCategoria" placeholder="Teología, Historia…"></div>
          <div class="field field-full"><label>Descripción</label><textarea name="descripcion" id="lDescripcion" rows="3"></textarea></div>
          <div class="field"><label>Precio (0 = gratis)</label><input name="precio" id="lPrecio" type="number" min="0" step="0.01" value="0"></div>
          <div class="field"><label>Portada (imagen, opcional)</label><input type="file" id="lPortada" accept="image/png,image/jpeg,image/webp"></div>
          <div class="field field-full"><label>Archivo del libro (PDF o EPUB) <span id="lArchivoReq">*</span></label><input type="file" id="lArchivo" accept=".pdf,.epub"></div>
        </div>
        <div style="display:flex;justify-content:flex-end;gap:.6rem;">
          <button type="button" class="btn btn-secondary" onclick="closeModal('mLibro')">Cancelar</button>
          <button type="submit" class="btn btn-primary" id="btnGuardarLibro">Publicar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<style>
.tab-btn.act{background:var(--ink);color:var(--lime);}
.libro-cover{width:36px;height:50px;object-fit:cover;border-radius:4px;background:var(--cream);}
</style>

<script>
function showTab(tab, btn){
  document.querySelectorAll('.tab-pane').forEach(p=>p.style.display='none');
  document.getElementById('tab-'+tab).style.display='block';
  document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('act'));
  btn.classList.add('act');
  if(tab==='compras') loadCompras();
  if(tab==='pago') loadDatosPago();
}

document.addEventListener('ibbs:ready', () => loadLibros());

async function loadLibros(){
  const d = await ajax('libro_list', {}, 'api/biblioteca.php');
  const tb = document.getElementById('tbodyLibros');
  limpiarSeleccionLibros();
  if(!d?.ok){ tb.innerHTML = `<tr class="empty-row"><td colspan="9">${d?.msg||'Error al conectar'}</td></tr>`; return; }
  if(!d.data.length){ tb.innerHTML = '<tr class="empty-row"><td colspan="9">Sin libros todavía.</td></tr>'; return; }
  tb.innerHTML = d.data.map(r => `<tr>
    <td style="text-align:center;"><input type="checkbox" class="chkLibro" value="${r.id}" onchange="actualizarBarraLoteLibros()"></td>
    <td>${r.portada?`<img class="libro-cover" src="${r.portada}">`:'📖'}</td>
    <td><strong>${h(r.titulo)}</strong></td>
    <td style="font-size:.82rem;">${h(r.autor||'—')}</td>
    <td style="font-size:.82rem;color:var(--muted);">${h(r.categoria||'—')}</td>
    <td>${parseFloat(r.precio)>0?'$'+parseFloat(r.precio).toFixed(2):'<span class="badge b-presente">Gratis</span>'}</td>
    <?php if($esAdmin):?><td style="font-size:.78rem;color:var(--muted);">${h(r.creado_por_nombre||'—')}</td><?php endif;?>
    <td><span class="badge ${r.activo=='1'?'b-activo':'b-inactivo'}">${r.activo=='1'?'Visible':'Oculto'}</span></td>
    <td class="td-actions">
      <button class="btn btn-sm btn-secondary" onclick="verComoDescarga(${r.id})">👁 Vista previa</button>
      <button class="btn btn-sm btn-primary" onclick="editarLibro(${r.id})">Editar</button>
      <button class="btn btn-sm btn-secondary" onclick="toggleLibro(${r.id})">${r.activo=='1'?'Ocultar':'Mostrar'}</button>
      <button class="btn btn-sm btn-danger" onclick="eliminarLibro(${r.id},'${hAttr(r.titulo)}')">Eliminar</button>
    </td></tr>`).join('');
}
function h(s){ const d=document.createElement('div'); d.textContent=s??''; return d.innerHTML; }
function hAttr(s){ return h(s).replace(/\\/g,'\\\\').replace(/'/g,"\\'").replace(/"/g,'&quot;'); }

function abrirNuevoLibro(){
  document.getElementById('fLibro').reset();
  document.getElementById('lId').value='';
  document.getElementById('mLibroTitulo').textContent='Nuevo Libro';
  document.getElementById('lArchivoReq').textContent='*';
  openModal('mLibro');
}
async function editarLibro(id){
  const d = await ajax('libro_get', {id}, 'api/biblioteca.php');
  if(!d?.ok){ toast(d?.msg,'err'); return; }
  const r = d.data;
  document.getElementById('lId').value = r.id;
  document.getElementById('lTitulo').value = r.titulo;
  document.getElementById('lAutor').value = r.autor||'';
  document.getElementById('lCategoria').value = r.categoria||'';
  document.getElementById('lDescripcion').value = r.descripcion||'';
  document.getElementById('lPrecio').value = r.precio;
  document.getElementById('lArchivoReq').textContent = '(ya cargado — subí uno nuevo solo si querés reemplazarlo)';
  document.getElementById('mLibroTitulo').textContent = 'Editar Libro';
  openModal('mLibro');
}
async function guardarLibro(e){
  e.preventDefault();
  const id = document.getElementById('lId').value;
  const fd = new FormData();
  fd.append('action', id ? 'libro_update' : 'libro_create');
  if(id) fd.append('id', id);
  fd.append('titulo', document.getElementById('lTitulo').value);
  fd.append('autor', document.getElementById('lAutor').value);
  fd.append('categoria', document.getElementById('lCategoria').value);
  fd.append('descripcion', document.getElementById('lDescripcion').value);
  fd.append('precio', document.getElementById('lPrecio').value);
  const portada = document.getElementById('lPortada').files[0];
  const archivo = document.getElementById('lArchivo').files[0];
  if(portada) fd.append('portada', portada);
  if(archivo) fd.append('archivo', archivo);
  if(!id && !archivo){ Ibbs.error('Subí el archivo del libro (PDF o EPUB).'); return; }
  const _csrf = document.querySelector('meta[name="csrf-token"]');
  fd.append('csrf_token', _csrf?_csrf.content:'');
  const btn = document.getElementById('btnGuardarLibro');
  btn.disabled = true; btn.textContent = 'Guardando…';
  try{
    const r = await fetch('api/biblioteca.php', {method:'POST', body:fd});
    const d = await r.json();
    if(d.ok){ toast(d.msg); closeModal('mLibro'); loadLibros(); }
    else Ibbs.error(d.msg||'Error');
  }catch(err){ Ibbs.error('Error de conexión.'); }
  btn.disabled = false; btn.textContent = 'Publicar';
}
function verComoDescarga(id){ window.open('api/biblioteca.php?action=descargar&id='+id, '_blank'); }
async function toggleLibro(id){
  const d = await ajax('libro_toggle', {id}, 'api/biblioteca.php');
  if(d?.ok){ toast(d.msg); loadLibros(); } else Ibbs.error(d?.msg||'Error');
}
async function eliminarLibro(id, titulo){
  ibbsConfirm(`¿Eliminar "${titulo}"? Esto borra también el archivo del libro. Es irreversible.`, async ()=>{
    const d = await ajax('libro_delete', {id}, 'api/biblioteca.php');
    if(d?.ok){ toast(d.msg); loadLibros(); } else Ibbs.error(d?.msg||'Error');
  });
}

function toggleAllLibros(cb){
  document.querySelectorAll('.chkLibro').forEach(c => c.checked = cb.checked);
  actualizarBarraLoteLibros();
}
function actualizarBarraLoteLibros(){
  const n = document.querySelectorAll('.chkLibro:checked').length;
  const total = document.querySelectorAll('.chkLibro').length;
  const chkAll = document.getElementById('chkAllLibros');
  chkAll.checked = total>0 && n===total;
  chkAll.indeterminate = n>0 && n<total;
  document.getElementById('barraLoteLibros').style.display = n ? 'flex' : 'none';
  if(n) document.getElementById('loteLibrosCount').textContent = n + (n===1?' seleccionado':' seleccionados');
}
function limpiarSeleccionLibros(){
  document.querySelectorAll('.chkLibro').forEach(c => c.checked = false);
  const chkAll = document.getElementById('chkAllLibros');
  if(chkAll){ chkAll.checked = false; chkAll.indeterminate = false; }
  const barra = document.getElementById('barraLoteLibros');
  if(barra) barra.style.display = 'none';
}
async function eliminarLibrosSeleccionados(){
  const ids = Array.from(document.querySelectorAll('.chkLibro:checked')).map(c => c.value);
  if(!ids.length) return;
  const rr = await Ibbs.confirm({title:'¿Eliminar libros?', text:`Se eliminarán ${ids.length} libro(s) y sus archivos. Esta acción es irreversible.`, confirm:'Sí, eliminar', danger:true});
  if(!rr.isConfirmed) return;
  const d = await ajax('libro_eliminar_lote', {ids: ids.join(',')}, 'api/biblioteca.php');
  if(d?.ok){ toast(d.msg); loadLibros(); } else toast(d?.msg||'Error', 'err');
}

// ── Tutorial guiado de esta página ──────────────────────────────
window.IBBS_TOUR_BIBLIOTECA = {
  storageKey: 'ibbs_tour_biblioteca_v1',
  steps: [
    { selector: '[data-tour="bib-nuevo"]', title: 'Subir un libro', text: 'Cargá el archivo (PDF o EPUB), portada y precio — si el precio es 0 queda gratis para quien lo descargue.' },
    { selector: '[data-tour="bib-tabla"]', title: 'Gestionar el catálogo', text: 'Editá, ocultá o eliminá cada libro. Marcando varios (o todos con la casilla del encabezado) podés eliminarlos de una.' },
  ],
  auto: true,
};
document.addEventListener('ibbs:ready', () => IbbsTour.start(window.IBBS_TOUR_BIBLIOTECA));

<?php if($esAdmin): ?>
async function loadCompras(){
  const estado = document.getElementById('filtroEstadoCompra').value;
  const d = await ajax('compra_list', {estado}, 'api/biblioteca.php');
  const tb = document.getElementById('tbodyCompras');
  if(!d?.ok){ tb.innerHTML = `<tr class="empty-row"><td colspan="7">${d?.msg||'Error'}</td></tr>`; return; }
  if(!d.data.length){ tb.innerHTML = '<tr class="empty-row"><td colspan="7">Sin solicitudes en este estado.</td></tr>'; return; }
  tb.innerHTML = d.data.map(c => `<tr>
    <td>${h(c.alumno_apellido)}, ${h(c.alumno_nombre)}<br><span style="font-size:.72rem;color:var(--muted);">CI: ${h(c.alumno_cedula)}</span></td>
    <td>${h(c.libro_titulo)}</td>
    <td>$${parseFloat(c.precio_pagado).toFixed(2)}</td>
    <td style="font-size:.78rem;">${(c.fecha||'').substring(0,16)}</td>
    <td>${c.comprobante?`<button class="btn btn-sm btn-secondary" onclick="window.open('${c.comprobante}','_blank')">🧾 Ver</button>`:'—'}</td>
    <td><span class="badge ${c.estado==='activado'?'b-activo':c.estado==='rechazado'?'b-inactivo':'b-tardanza'}">${c.estado}</span></td>
    <td class="td-actions">
      ${c.estado==='pendiente'?`
        <button class="btn btn-sm btn-success" onclick="aprobarCompra(${c.id})">✓ Activar</button>
        <button class="btn btn-sm btn-danger" onclick="rechazarCompra(${c.id})">✕ Rechazar</button>
      `:'—'}
    </td></tr>`).join('');
}
async function aprobarCompra(id){
  const d = await ajax('compra_aprobar', {id}, 'api/biblioteca.php');
  if(d?.ok){ toast(d.msg); loadCompras(); } else Ibbs.error(d?.msg||'Error');
}
async function rechazarCompra(id){
  ibbsConfirm('¿Rechazar esta compra? Revisá bien el comprobante antes de confirmar.', async ()=>{
    const d = await ajax('compra_rechazar', {id}, 'api/biblioteca.php');
    if(d?.ok){ toast(d.msg); loadCompras(); } else Ibbs.error(d?.msg||'Error');
  });
}
async function loadDatosPago(){
  const d = await ajax('datos_pago_get', {}, 'api/biblioteca.php');
  if(!d?.ok) return;
  const p = d.data||{};
  document.getElementById('dpTitular').value = p.titular||'';
  document.getElementById('dpBanco').value = p.banco||'';
  document.getElementById('dpCuenta').value = p.cuenta||'';
  document.getElementById('dpCedula').value = p.cedula_rif||'';
  document.getElementById('dpTelefono').value = p.pago_movil_telefono||'';
  document.getElementById('dpInstrucciones').value = p.instrucciones||'';
}
async function guardarDatosPago(e){
  e.preventDefault();
  const d = await ajax('datos_pago_update', {
    titular: document.getElementById('dpTitular').value,
    banco: document.getElementById('dpBanco').value,
    cuenta: document.getElementById('dpCuenta').value,
    cedula_rif: document.getElementById('dpCedula').value,
    pago_movil_telefono: document.getElementById('dpTelefono').value,
    instrucciones: document.getElementById('dpInstrucciones').value,
  }, 'api/biblioteca.php');
  if(d?.ok) toast(d.msg); else Ibbs.error(d?.msg||'Error');
}
<?php endif; ?>
</script>

<?php include __DIR__.'/layout/foot.php'; ?>
