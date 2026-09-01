<?php
$page_title = 'Aula Virtual';
$page_sub   = 'Anuncios, materiales y actividades de la materia';
$active_link = 'materias';
include __DIR__.'/layout/head.php';
// Acceso: admin, superadmin y profesor. El permiso fino (¿es EL docente
// de esta materia?) lo resuelve api/aula.php en cada llamada.
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
      <select id="selMateriaAula" onchange="if(this.value) window.location='modulo_aula.php?materia_id='+this.value;">
        <option value="">— Elige una materia —</option>
      </select>
    </div>
  </div>
</div>

<div id="areaCargando" style="display:<?=$materia_id?'block':'none'?>;text-align:center;padding:4rem 1rem;color:var(--muted);"><span class="spin"></span></div>

<div id="areaSinPermiso" style="display:none;text-align:center;padding:4rem 1rem;color:var(--muted);">
  <p style="font-family:'DM Serif Display',serif;font-size:1.3rem;margin-bottom:.4rem;">Sin acceso a esta aula</p>
  <p style="font-size:.84rem;">No tienes esta materia asignada.</p>
</div>

<div id="areaAula" style="display:none;">

  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.6rem;margin-bottom:1.2rem;">
    <div>
      <h2 id="matTitulo" style="font-family:'DM Serif Display',serif;font-size:1.5rem;color:var(--ink);"></h2>
      <span id="matCodigo" style="font-size:.82rem;color:var(--muted);"></span>
    </div>
  </div>

  <div class="tabs-nav">
    <button class="tab-btn active" data-tab-group="aula" data-tab="anuncios" onclick="switchTab('aula','anuncios')">📢 Anuncios</button>
    <button class="tab-btn" data-tab-group="aula" data-tab="materiales" onclick="switchTab('aula','materiales')">📁 Materiales</button>
    <button class="tab-btn" data-tab-group="aula" data-tab="actividades" onclick="switchTab('aula','actividades')">📝 Actividades</button>
  </div>

  <!-- ═══ TAB: ANUNCIOS ═══ -->
  <div class="tab-pane active" data-pane-group="aula" data-pane="anuncios">
    <div style="display:flex;justify-content:flex-end;margin-bottom:1rem;">
      <button class="btn btn-primary can-manage" style="display:none;" onclick="abrirAnuncioModal()">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Nuevo anuncio
      </button>
    </div>
    <div id="listaAnuncios"><div style="text-align:center;padding:2rem;color:var(--muted);"><span class="spin"></span></div></div>
  </div>

  <!-- ═══ TAB: MATERIALES ═══ -->
  <div class="tab-pane" data-pane-group="aula" data-pane="materiales">
    <div style="display:flex;justify-content:flex-end;margin-bottom:1rem;">
      <button class="btn btn-primary can-manage" style="display:none;" onclick="openModal('mMaterial')">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Subir material
      </button>
    </div>
    <div class="card">
      <div class="tbl-wrap">
        <table>
          <thead><tr><th>Título</th><th>Tipo</th><th>Tamaño</th><th>Subido por</th><th>Fecha</th><th>Acciones</th></tr></thead>
          <tbody id="tbodyMateriales"><tr class="empty-row"><td colspan="6"><span class="spin"></span></td></tr></tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- ═══ TAB: ACTIVIDADES ═══ -->
  <div class="tab-pane" data-pane-group="aula" data-pane="actividades">
    <div style="display:flex;justify-content:flex-end;margin-bottom:1rem;">
      <button class="btn btn-primary can-manage" style="display:none;" onclick="abrirActividadModal()">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Nueva actividad
      </button>
    </div>
    <div class="card">
      <div class="tbl-wrap">
        <table>
          <thead><tr><th>Título</th><th>Tipo</th><th>Nota máx.</th><th>Fecha</th><th>Acciones</th></tr></thead>
          <tbody id="tbodyActividades"><tr class="empty-row"><td colspan="5"><span class="spin"></span></td></tr></tbody>
        </table>
      </div>
    </div>
  </div>

</div>

<!-- MODAL ANUNCIO (crear/editar) -->
<div class="modal-backdrop" id="mAnuncio">
  <div class="modal">
    <div class="modal-head"><h3 id="mAnuncioTitulo">Nuevo anuncio</h3>
      <button class="modal-close" onclick="closeModal('mAnuncio')"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="anId">
      <div class="form-grid" style="margin-bottom:1rem;">
        <div class="field field-full"><label>Título *</label><input id="anTitulo" placeholder="Ej. Cambio de horario para el sábado"></div>
        <div class="field field-full"><label>Contenido *</label><textarea id="anContenido" rows="5" placeholder="Escribe el anuncio para tus alumnos…"></textarea></div>
        <div class="field field-full">
          <label style="display:flex;align-items:center;gap:.4rem;cursor:pointer;text-transform:none;opacity:1;letter-spacing:0;">
            <input type="checkbox" id="anFijado" style="width:auto;accent-color:var(--lime2);"> Fijar arriba del muro
          </label>
        </div>
      </div>
      <div style="display:flex;justify-content:flex-end;gap:.6rem;">
        <button type="button" class="btn btn-secondary" onclick="closeModal('mAnuncio')">Cancelar</button>
        <button type="button" class="btn btn-primary" onclick="guardarAnuncio()">Publicar</button>
      </div>
    </div>
  </div>
</div>

<!-- MODAL MATERIAL (subir) -->
<div class="modal-backdrop" id="mMaterial">
  <div class="modal">
    <div class="modal-head"><h3>Subir material</h3>
      <button class="modal-close" onclick="closeModal('mMaterial')"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
    </div>
    <div class="modal-body">
      <form id="fMaterial" onsubmit="subirMaterial(event)">
        <div class="form-grid" style="margin-bottom:1rem;">
          <div class="field field-full"><label>Título *</label><input name="titulo" placeholder="Ej. Guía de estudio — Unidad 1" required></div>
          <div class="field field-full"><label>Descripción</label><textarea name="descripcion" rows="2"></textarea></div>
          <div class="field field-full">
            <label>Archivo * <span style="text-transform:none;font-weight:400;color:var(--muted);">(PDF, Word, PowerPoint, Excel, TXT, CSV, ZIP o imagen — máx. 25MB)</span></label>
            <input type="file" name="archivo" id="materialArchivo" required
              accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.txt,.csv,.zip,.jpg,.jpeg,.png,.gif,.webp">
          </div>
        </div>
        <div style="display:flex;justify-content:flex-end;gap:.6rem;">
          <button type="button" class="btn btn-secondary" onclick="closeModal('mMaterial')">Cancelar</button>
          <button type="submit" class="btn btn-primary" id="btnSubirMaterial">Subir</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- MODAL ACTIVIDAD (crear/editar) -->
<div class="modal-backdrop" id="mActividad">
  <div class="modal">
    <div class="modal-head"><h3 id="mActividadTitulo">Nueva actividad</h3>
      <button class="modal-close" onclick="closeModal('mActividad')"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="acId">
      <div class="form-grid" style="margin-bottom:1rem;">
        <div class="field field-full"><label>Título *</label><input id="acTitulo" placeholder="Ej. Taller 1 — Análisis de texto"></div>
        <div class="field field-full"><label>Descripción</label><textarea id="acDescripcion" rows="2"></textarea></div>
        <div class="field"><label>Tipo</label>
          <select id="acTipo">
            <option value="actividad">Actividad</option>
            <option value="taller">Taller</option>
            <option value="examen">Examen</option>
            <option value="proyecto">Proyecto</option>
          </select>
        </div>
        <div class="field"><label>Nota máxima</label><input type="number" id="acNotaMax" data-only="decimal" value="20" min="1" step="0.5"></div>
        <div class="field"><label>Fecha</label><input type="date" id="acFecha"></div>
      </div>
      <div style="display:flex;justify-content:flex-end;gap:.6rem;">
        <button type="button" class="btn btn-secondary" onclick="closeModal('mActividad')">Cancelar</button>
        <button type="button" class="btn btn-primary" onclick="guardarActividad()">Guardar</button>
      </div>
    </div>
  </div>
</div>

<!-- MODAL CALIFICAR ACTIVIDAD -->
<div class="modal-backdrop" id="mCalificar">
  <div class="modal md">
    <div class="modal-head"><h3 id="mCalificarTitulo">Calificar</h3>
      <button class="modal-close" onclick="closeModal('mCalificar')"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="calActividadId">
      <div class="tbl-wrap">
        <table>
          <thead><tr><th style="text-align:left;">Alumno</th><th style="text-align:left;">Cédula</th><th style="width:110px;">Nota</th><th>Observación</th></tr></thead>
          <tbody id="tbodyCalificar"><tr class="empty-row"><td colspan="4"><span class="spin"></span></td></tr></tbody>
        </table>
      </div>
    </div>
    <div class="modal-foot">
      <button class="btn btn-secondary" onclick="closeModal('mCalificar')">Cancelar</button>
      <button class="btn btn-primary" onclick="guardarCalificaciones()">Guardar calificaciones</button>
    </div>
  </div>
</div>

<script>
const MATERIA_ID = <?=$materia_id?>;
let CAN_MANAGE = false;
let ACT_NOTA_MAX = 20;

function h(s){ const d=document.createElement('div'); d.textContent=String(s??''); return d.innerHTML; }
function fmtBytes(n){
  n = parseInt(n)||0;
  if (n < 1024) return n+' B';
  if (n < 1024*1024) return (n/1024).toFixed(1)+' KB';
  return (n/1024/1024).toFixed(1)+' MB';
}
function fmtFecha(s){
  if(!s) return '—';
  return String(s).substring(0,16).replace('T',' ');
}
function aulaAjax(action, data={}) { return ajax(action, {...data, materia_id: MATERIA_ID}, 'api/aula.php'); }

document.addEventListener('ibbs:ready', iniciarAula);

async function cargarSelectorMaterias() {
  const d = await aulaAjax('materias_mias');
  const sel = document.getElementById('selMateriaAula');
  if (!d?.ok || !d.data.length) {
    sel.innerHTML = '<option value="">Sin materias asignadas</option>';
    return;
  }
  sel.innerHTML = '<option value="">— Elige una materia —</option>' +
    d.data.map(m => `<option value="${m.id}" ${m.id==MATERIA_ID?'selected':''}>${h(m.codigo)} · ${h(m.nombre)}</option>`).join('');
}

async function iniciarAula() {
  cargarSelectorMaterias();
  if (!MATERIA_ID) return; // se queda mostrando solo el selector

  const d = await aulaAjax('materia_info');
  document.getElementById('areaCargando').style.display = 'none';
  if (!d?.ok) {
    document.getElementById('areaSinPermiso').style.display = 'block';
    return;
  }
  CAN_MANAGE = !!d.data.can_manage;
  document.getElementById('matTitulo').textContent = d.data.materia.nombre;
  document.getElementById('matCodigo').textContent = d.data.materia.codigo;
  document.querySelectorAll('.can-manage').forEach(el => el.style.display = CAN_MANAGE ? 'inline-flex' : 'none');
  document.getElementById('areaAula').style.display = 'block';

  loadAnuncios();
  loadMateriales();
  loadActividades();
}

/* ══ ANUNCIOS ══ */
async function loadAnuncios() {
  const d = await aulaAjax('anuncio_list');
  const el = document.getElementById('listaAnuncios');
  if (!d?.ok) { el.innerHTML = '<p style="color:var(--muted);text-align:center;padding:2rem;">'+h(d?.msg||'Error')+'</p>'; return; }
  if (!d.data.length) { el.innerHTML = '<p style="color:var(--muted);text-align:center;padding:2rem;">Sin anuncios todavía.</p>'; return; }
  el.innerHTML = d.data.map(a => `
    <div class="card" style="margin-bottom:.9rem;${a.fijado==1?'border-color:var(--lime2);':''}">
      <div class="card-body">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:.6rem;">
          <div>
            <h3 style="font-family:'DM Serif Display',serif;font-size:1.1rem;margin-bottom:.2rem;">
              ${a.fijado==1?'📌 ':''}${h(a.titulo)}
            </h3>
            <span style="font-size:.74rem;color:var(--muted);">${h(a.autor)} · ${fmtFecha(a.creado_en)}</span>
          </div>
          ${CAN_MANAGE ? `
          <div class="td-actions" style="flex-shrink:0;">
            <button class="btn btn-sm btn-secondary" onclick='editarAnuncio(${JSON.stringify(a)})'>Editar</button>
            <button class="btn btn-sm btn-danger" onclick="eliminarAnuncio(${a.id})">Eliminar</button>
          </div>` : ''}
        </div>
        <p style="margin-top:.7rem;font-size:.88rem;color:var(--ink);white-space:pre-wrap;line-height:1.6;">${h(a.contenido)}</p>
      </div>
    </div>`).join('');
}

function abrirAnuncioModal() {
  document.getElementById('anId').value = '';
  document.getElementById('anTitulo').value = '';
  document.getElementById('anContenido').value = '';
  document.getElementById('anFijado').checked = false;
  document.getElementById('mAnuncioTitulo').textContent = 'Nuevo anuncio';
  openModal('mAnuncio');
}
function editarAnuncio(a) {
  document.getElementById('anId').value = a.id;
  document.getElementById('anTitulo').value = a.titulo;
  document.getElementById('anContenido').value = a.contenido;
  document.getElementById('anFijado').checked = a.fijado == 1;
  document.getElementById('mAnuncioTitulo').textContent = 'Editar anuncio';
  openModal('mAnuncio');
}
async function guardarAnuncio() {
  const id = document.getElementById('anId').value;
  const titulo = document.getElementById('anTitulo').value.trim();
  const contenido = document.getElementById('anContenido').value.trim();
  const fijado = document.getElementById('anFijado').checked ? 1 : 0;
  if (!titulo || !contenido) { Ibbs.error('Completa título y contenido.'); return; }
  const d = await aulaAjax(id ? 'anuncio_update' : 'anuncio_create', {id, titulo, contenido, fijado});
  if (d?.ok) { toast(d.msg); closeModal('mAnuncio'); loadAnuncios(); }
  else toast(d?.msg || 'Error', 'err');
}
function eliminarAnuncio(id) {
  ibbsConfirm('¿Eliminar este anuncio?', async () => {
    const d = await aulaAjax('anuncio_delete', {id});
    if (d?.ok) { toast(d.msg); loadAnuncios(); } else Ibbs.error(d?.msg || 'Error');
  });
}

/* ══ MATERIALES ══ */
async function loadMateriales() {
  const d = await aulaAjax('material_list');
  const tb = document.getElementById('tbodyMateriales');
  if (!d?.ok) { tb.innerHTML = '<tr class="empty-row"><td colspan="6">'+h(d?.msg||'Error')+'</td></tr>'; return; }
  if (!d.data.length) { tb.innerHTML = '<tr class="empty-row"><td colspan="6">Sin materiales todavía.</td></tr>'; return; }
  tb.innerHTML = d.data.map(m => `<tr>
    <td style="text-align:left;"><strong>${h(m.titulo)}</strong>${m.descripcion?`<br><span style="font-size:.76rem;color:var(--muted);">${h(m.descripcion)}</span>`:''}</td>
    <td><span class="badge b-profesor">${h((m.archivo_tipo||'').toUpperCase())}</span></td>
    <td>${fmtBytes(m.tamano_bytes)}</td>
    <td style="font-size:.8rem;color:var(--muted);">${h(m.autor)}</td>
    <td style="font-size:.8rem;color:var(--muted);">${fmtFecha(m.creado_en)}</td>
    <td class="td-actions">
      <a class="btn btn-sm btn-primary" href="api/aula.php?action=material_download&id=${m.id}">Descargar</a>
      ${CAN_MANAGE ? `<button class="btn btn-sm btn-danger" onclick="eliminarMaterial(${m.id})">Eliminar</button>` : ''}
    </td></tr>`).join('');
}
async function subirMaterial(e) {
  e.preventDefault();
  const btn = document.getElementById('btnSubirMaterial');
  btn.disabled = true; btn.textContent = 'Subiendo…';
  const fd = new FormData(e.target);
  fd.append('action', 'material_create');
  fd.append('materia_id', MATERIA_ID);
  const _csrf = document.querySelector('meta[name="csrf-token"]');
  if (_csrf) fd.append('csrf_token', _csrf.content);
  try {
    const r = await fetch('api/aula.php', {method:'POST', body:fd});
    const d = await r.json();
    if (d.ok) { toast(d.msg); closeModal('mMaterial'); e.target.reset(); loadMateriales(); }
    else toast(d.msg || 'Error', 'err');
  } catch(err) { toast('Error de conexión.', 'err'); }
  btn.disabled = false; btn.textContent = 'Subir';
}
function eliminarMaterial(id) {
  ibbsConfirm('¿Eliminar este material? Se borrará el archivo del servidor.', async () => {
    const d = await aulaAjax('material_delete', {id});
    if (d?.ok) { toast(d.msg); loadMateriales(); } else Ibbs.error(d?.msg || 'Error');
  });
}

/* ══ ACTIVIDADES ══ */
async function loadActividades() {
  const d = await aulaAjax('actividad_list');
  const tb = document.getElementById('tbodyActividades');
  if (!d?.ok) { tb.innerHTML = '<tr class="empty-row"><td colspan="5">'+h(d?.msg||'Error')+'</td></tr>'; return; }
  if (!d.data.length) { tb.innerHTML = '<tr class="empty-row"><td colspan="5">Sin actividades todavía.</td></tr>'; return; }
  tb.innerHTML = d.data.map(a => `<tr>
    <td style="text-align:left;"><strong>${h(a.titulo)}</strong>${a.descripcion?`<br><span style="font-size:.76rem;color:var(--muted);">${h(a.descripcion)}</span>`:''}</td>
    <td><span class="badge b-tardanza">${h(a.tipo)}</span></td>
    <td>${parseFloat(a.nota_max).toFixed(1)}</td>
    <td style="font-size:.8rem;color:var(--muted);">${a.fecha||'—'}</td>
    <td class="td-actions">
      ${CAN_MANAGE ? `
      <button class="btn btn-sm btn-success" onclick="abrirCalificar(${a.id},'${h(a.titulo).replace(/'/g,"\\'")}',${a.nota_max})">Calificar</button>
      <button class="btn btn-sm btn-secondary" onclick='editarActividad(${JSON.stringify(a)})'>Editar</button>
      <button class="btn btn-sm btn-danger" onclick="eliminarActividad(${a.id})">Eliminar</button>` : ''}
    </td></tr>`).join('');
}
function abrirActividadModal() {
  document.getElementById('acId').value = '';
  document.getElementById('acTitulo').value = '';
  document.getElementById('acDescripcion').value = '';
  document.getElementById('acTipo').value = 'actividad';
  document.getElementById('acNotaMax').value = 20;
  document.getElementById('acFecha').value = '';
  document.getElementById('mActividadTitulo').textContent = 'Nueva actividad';
  openModal('mActividad');
}
function editarActividad(a) {
  document.getElementById('acId').value = a.id;
  document.getElementById('acTitulo').value = a.titulo;
  document.getElementById('acDescripcion').value = a.descripcion || '';
  document.getElementById('acTipo').value = a.tipo;
  document.getElementById('acNotaMax').value = a.nota_max;
  document.getElementById('acFecha').value = a.fecha || '';
  document.getElementById('mActividadTitulo').textContent = 'Editar actividad';
  openModal('mActividad');
}
async function guardarActividad() {
  const id = document.getElementById('acId').value;
  const titulo = document.getElementById('acTitulo').value.trim();
  if (!titulo) { Ibbs.error('Ponle un título a la actividad.'); return; }
  const d = await aulaAjax(id ? 'actividad_update' : 'actividad_create', {
    id,
    titulo,
    descripcion: document.getElementById('acDescripcion').value.trim(),
    tipo: document.getElementById('acTipo').value,
    nota_max: document.getElementById('acNotaMax').value || 20,
    fecha: document.getElementById('acFecha').value,
  });
  if (d?.ok) { toast(d.msg); closeModal('mActividad'); loadActividades(); }
  else toast(d?.msg || 'Error', 'err');
}
function eliminarActividad(id) {
  ibbsConfirm('¿Eliminar esta actividad? Se borrarán también sus calificaciones.', async () => {
    const d = await aulaAjax('actividad_delete', {id});
    if (d?.ok) { toast(d.msg); loadActividades(); } else Ibbs.error(d?.msg || 'Error');
  });
}

/* ══ CALIFICAR ══ */
async function abrirCalificar(actividadId, titulo, notaMax) {
  document.getElementById('calActividadId').value = actividadId;
  document.getElementById('mCalificarTitulo').textContent = 'Calificar — ' + titulo + ' (máx. ' + parseFloat(notaMax).toFixed(1) + ')';
  ACT_NOTA_MAX = parseFloat(notaMax) || 20;
  const tb = document.getElementById('tbodyCalificar');
  tb.innerHTML = '<tr class="empty-row"><td colspan="4"><span class="spin"></span></td></tr>';
  openModal('mCalificar');
  const d = await aulaAjax('actividad_calificaciones', {actividad_id: actividadId});
  if (!d?.ok) { tb.innerHTML = '<tr class="empty-row"><td colspan="4">'+h(d?.msg||'Error')+'</td></tr>'; return; }
  if (!d.data.alumnos.length) { tb.innerHTML = '<tr class="empty-row"><td colspan="4">No hay alumnos inscritos en esta materia.</td></tr>'; return; }
  tb.innerHTML = d.data.alumnos.map(a => `<tr>
    <td style="text-align:left;">${h(a.apellido)}, ${h(a.nombre)}</td>
    <td style="text-align:left;font-size:.8rem;color:var(--muted);">${h(a.cedula)}</td>
    <td><input type="number" class="calNota" data-alumno="${a.id}" data-only="decimal" min="0" max="${ACT_NOTA_MAX}" step="0.1"
        value="${a.nota !== null ? a.nota : ''}" style="width:80px;text-align:center;"></td>
    <td><input type="text" class="calObs" data-alumno="${a.id}" value="${h(a.observacion||'')}" placeholder="Opcional" style="width:100%;"></td>
  </tr>`).join('');
}
async function guardarCalificaciones() {
  const actividadId = document.getElementById('calActividadId').value;
  const inputs = [...document.querySelectorAll('#tbodyCalificar .calNota')];

  // Valida todas las notas antes de armar el payload — si alguna está
  // fuera de rango se marca y se corta, sin enviar nada al servidor.
  for (const inp of inputs) {
    inp.style.borderColor = '';
    const v = inp.value.trim().replace(',', '.');
    if (v !== '' && (isNaN(v) || parseFloat(v) < 0 || parseFloat(v) > ACT_NOTA_MAX)) {
      Ibbs.error('Hay una nota fuera de rango (0 a ' + ACT_NOTA_MAX + ').');
      inp.style.borderColor = '#dc2626';
      inp.focus();
      return;
    }
  }

  const notas = inputs.map(inp => {
    const alumnoId = inp.dataset.alumno;
    const obs = document.querySelector('#tbodyCalificar .calObs[data-alumno="'+alumnoId+'"]')?.value || '';
    const val = inp.value.trim().replace(',', '.');
    return { alumno_id: alumnoId, nota: val === '' ? null : parseFloat(val), observacion: obs };
  });

  const d = await aulaAjax('actividad_calificar_bulk', {actividad_id: actividadId, notas: JSON.stringify(notas)});
  if (d?.ok) { toast(d.msg); closeModal('mCalificar'); }
  else toast(d?.msg || 'Error', 'err');
}
</script>

<?php include __DIR__.'/layout/foot.php'; ?>
