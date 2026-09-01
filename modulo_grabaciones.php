<?php
$page_title = 'Clases Grabadas';
$page_sub   = 'Repositorio de videos por materia';
$active_link = 'grabaciones';
include __DIR__.'/layout/head.php';
// Acceso: admin, superadmin y profesor. El permiso fino (¿es EL docente
// de esta materia?) lo resuelve api/clases_grabadas.php en cada llamada.
if(!in_array($_rol,['superadmin','admin','profesor'])){
    echo '<script>window.location="index.php";</script>'; exit;
}
$materia_id = (int)($_GET['materia_id'] ?? 0);
?>

<?php if(in_array($_rol,['superadmin','admin'])): ?>
<a href="modulo_materias.php" style="display:inline-flex;align-items:center;gap:.4rem;font-size:.82rem;color:var(--muted);text-decoration:none;margin-bottom:1rem;">
  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
  Volver a materias
</a>
<?php endif; ?>

<!-- Selector de materia — se muestra cuando no llega ?materia_id= en la URL -->
<div class="card" id="areaSelector" style="display:<?=$materia_id?'none':'block'?>;margin-bottom:1.4rem;">
  <div class="card-body">
    <div class="field" style="max-width:420px;">
      <label>Selecciona una materia</label>
      <select id="selMateriaGrab" onchange="if(this.value) window.location='modulo_grabaciones.php?materia_id='+this.value;">
        <option value="">— Elige una materia —</option>
      </select>
    </div>
  </div>
</div>

<div id="areaCargando" style="display:<?=$materia_id?'block':'none'?>;text-align:center;padding:4rem 1rem;color:var(--muted);"><span class="spin"></span></div>

<div id="areaSinPermiso" style="display:none;text-align:center;padding:4rem 1rem;color:var(--muted);">
  <p style="font-family:'DM Serif Display',serif;font-size:1.3rem;margin-bottom:.4rem;">Sin acceso a esta materia</p>
  <p style="font-size:.84rem;">No tienes esta materia asignada.</p>
</div>

<div id="areaGrab" style="display:none;">

  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.6rem;margin-bottom:1.2rem;">
    <div>
      <h2 id="matTitulo" style="font-family:'DM Serif Display',serif;font-size:1.5rem;color:var(--ink);"></h2>
      <span id="matCodigo" style="font-size:.82rem;color:var(--muted);"></span>
    </div>
    <button class="btn btn-primary can-manage" style="display:none;" onclick="abrirClaseModal()">
      <svg viewBox="0 0 24 24" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      Agregar clase grabada
    </button>
  </div>

  <div id="listaClases" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:1.2rem;"></div>

</div>

<!-- MODAL CLASE GRABADA (crear/editar) -->
<div class="modal-backdrop" id="mClase">
  <div class="modal">
    <div class="modal-head"><h3 id="mClaseTitulo">Nueva clase grabada</h3>
      <button class="modal-close" onclick="closeModal('mClase')"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="clId">
      <div class="form-grid" style="margin-bottom:1rem;">
        <div class="field field-full"><label>Título *</label><input id="clTitulo" placeholder="Ej. Clase 3 — Introducción al tema"></div>
        <div class="field field-full">
          <label>Link del video * <span style="text-transform:none;font-weight:400;color:var(--muted);">(YouTube, Google Drive o Vimeo)</span></label>
          <input id="clUrl" type="url" placeholder="https://www.youtube.com/watch?v=…">
        </div>
        <div class="field field-full"><label>Descripción</label><textarea id="clDescripcion" rows="2"></textarea></div>
        <div class="field"><label>Fecha de la clase</label><input type="date" id="clFecha"></div>
      </div>
      <div style="display:flex;justify-content:flex-end;gap:.6rem;">
        <button type="button" class="btn btn-secondary" onclick="closeModal('mClase')">Cancelar</button>
        <button type="button" class="btn btn-primary" onclick="guardarClase()">Guardar</button>
      </div>
    </div>
  </div>
</div>

<script>
const MATERIA_ID = <?=$materia_id?>;
let CAN_MANAGE = false;

function h(s){ const d=document.createElement('div'); d.textContent=String(s??''); return d.innerHTML; }
function grabAjax(action, data={}) { return ajax(action, {...data, materia_id: MATERIA_ID}, 'api/clases_grabadas.php'); }

const PLATAFORMA_LBL = {youtube:'YouTube', drive:'Google Drive', vimeo:'Vimeo', otro:'Enlace externo'};

document.addEventListener('ibbs:ready', iniciarGrab);

async function cargarSelectorMaterias() {
  const d = await grabAjax('materias_mias');
  const sel = document.getElementById('selMateriaGrab');
  if (!d?.ok || !d.data.length) { sel.innerHTML = '<option value="">Sin materias asignadas</option>'; return; }
  sel.innerHTML = '<option value="">— Elige una materia —</option>' +
    d.data.map(m => `<option value="${m.id}" ${m.id==MATERIA_ID?'selected':''}>${h(m.codigo)} · ${h(m.nombre)}</option>`).join('');
}

async function iniciarGrab() {
  cargarSelectorMaterias();
  if (!MATERIA_ID) return;

  const d = await grabAjax('materia_info');
  document.getElementById('areaCargando').style.display = 'none';
  if (!d?.ok) { document.getElementById('areaSinPermiso').style.display = 'block'; return; }

  CAN_MANAGE = !!d.data.can_manage;
  document.getElementById('matTitulo').textContent = d.data.materia.nombre;
  document.getElementById('matCodigo').textContent = d.data.materia.codigo;
  document.querySelectorAll('.can-manage').forEach(el => el.style.display = CAN_MANAGE ? 'inline-flex' : 'none');
  document.getElementById('areaGrab').style.display = 'block';

  loadClases();
}

async function loadClases() {
  const d = await grabAjax('clase_list');
  const el = document.getElementById('listaClases');
  if (!d?.ok) { el.innerHTML = '<p style="color:var(--muted);grid-column:1/-1;text-align:center;padding:2rem;">'+h(d?.msg||'Error')+'</p>'; return; }
  if (!d.data.length) { el.innerHTML = '<p style="color:var(--muted);grid-column:1/-1;text-align:center;padding:2rem;">Sin clases grabadas todavía.</p>'; return; }

  el.innerHTML = d.data.map(c => {
    const player = c.embed_url
      ? `<div style="position:relative;padding-top:56.25%;background:#000;border-radius:10px 10px 0 0;overflow:hidden;">
           <iframe src="${h(c.embed_url)}" loading="lazy" referrerpolicy="strict-origin-when-cross-origin"
             allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
             allowfullscreen style="position:absolute;inset:0;width:100%;height:100%;border:0;"></iframe>
         </div>`
      : `<a href="${h(c.url)}" target="_blank" rel="noopener noreferrer"
           style="display:flex;align-items:center;justify-content:center;gap:.5rem;padding-top:30%;padding-bottom:30%;background:var(--cream);border-radius:10px 10px 0 0;text-decoration:none;color:var(--ink);font-weight:600;font-size:.88rem;">
           🔗 Abrir video (${h(PLATAFORMA_LBL[c.plataforma]||'enlace')}) — se abre en otra pestaña
         </a>`;

    return `<div class="card" style="padding:0;overflow:hidden;">
      ${player}
      <div class="card-body">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:.5rem;">
          <h3 style="font-family:'DM Serif Display',serif;font-size:1.05rem;">${h(c.titulo)}</h3>
          <span class="badge b-profesor" style="flex-shrink:0;">${h(PLATAFORMA_LBL[c.plataforma]||c.plataforma)}</span>
        </div>
        ${c.descripcion ? `<p style="font-size:.82rem;color:var(--muted);margin-top:.4rem;line-height:1.5;">${h(c.descripcion)}</p>` : ''}
        <div style="font-size:.74rem;color:var(--muted);margin-top:.6rem;">
          ${c.fecha ? h(c.fecha)+' · ' : ''}Subido por ${h(c.autor)}
        </div>
        ${CAN_MANAGE ? `
        <div class="td-actions" style="margin-top:.7rem;">
          <button class="btn btn-sm btn-secondary" onclick='editarClase(${JSON.stringify(c)})'>Editar</button>
          <button class="btn btn-sm btn-danger" onclick="eliminarClase(${c.id})">Eliminar</button>
        </div>` : ''}
      </div>
    </div>`;
  }).join('');
}

function abrirClaseModal() {
  document.getElementById('clId').value = '';
  document.getElementById('clTitulo').value = '';
  document.getElementById('clUrl').value = '';
  document.getElementById('clDescripcion').value = '';
  document.getElementById('clFecha').value = '';
  document.getElementById('mClaseTitulo').textContent = 'Nueva clase grabada';
  openModal('mClase');
}
function editarClase(c) {
  document.getElementById('clId').value = c.id;
  document.getElementById('clTitulo').value = c.titulo;
  document.getElementById('clUrl').value = c.url;
  document.getElementById('clDescripcion').value = c.descripcion || '';
  document.getElementById('clFecha').value = c.fecha || '';
  document.getElementById('mClaseTitulo').textContent = 'Editar clase grabada';
  openModal('mClase');
}
async function guardarClase() {
  const id = document.getElementById('clId').value;
  const titulo = document.getElementById('clTitulo').value.trim();
  const url = document.getElementById('clUrl').value.trim();
  if (!titulo) { Ibbs.error('Ponle un título a la clase.'); return; }
  if (!url) { Ibbs.error('Pega el link del video.'); return; }
  const d = await grabAjax(id ? 'clase_update' : 'clase_create', {
    id, titulo, url,
    descripcion: document.getElementById('clDescripcion').value.trim(),
    fecha: document.getElementById('clFecha').value,
  });
  if (d?.ok) { toast(d.msg); closeModal('mClase'); loadClases(); }
  else toast(d?.msg || 'Error', 'err');
}
function eliminarClase(id) {
  ibbsConfirm('¿Eliminar esta clase grabada?', async () => {
    const d = await grabAjax('clase_delete', {id});
    if (d?.ok) { toast(d.msg); loadClases(); } else Ibbs.error(d?.msg || 'Error');
  });
}
</script>

<?php include __DIR__.'/layout/foot.php'; ?>
