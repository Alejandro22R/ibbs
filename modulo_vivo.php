<?php
$page_title = 'Clases en Vivo';
$page_sub   = 'Videollamadas por materia (Jitsi Meet o link externo)';
$active_link = 'vivo';
include __DIR__.'/layout/head.php';
// Acceso: admin, superadmin y profesor. El permiso fino (¿es EL docente
// de esta materia?) lo resuelve api/clases_vivo.php en cada llamada.
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
      <select id="selMateriaVivo" onchange="if(this.value) window.location='modulo_vivo.php?materia_id='+this.value;">
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

<div id="areaVivo" style="display:none;">

  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.6rem;margin-bottom:1.2rem;">
    <div>
      <h2 id="matTitulo" style="font-family:'DM Serif Display',serif;font-size:1.5rem;color:var(--ink);"></h2>
      <span id="matCodigo" style="font-size:.82rem;color:var(--muted);"></span>
    </div>
    <button class="btn btn-primary can-manage" style="display:none;" onclick="abrirVivoModal()">
      <svg viewBox="0 0 24 24" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      Nueva clase en vivo
    </button>
  </div>

  <div id="listaVivo" style="display:flex;flex-direction:column;gap:.9rem;"></div>

</div>

<!-- MODAL CLASE EN VIVO (crear/editar) -->
<div class="modal-backdrop" id="mVivo">
  <div class="modal">
    <div class="modal-head"><h3 id="mVivoTitulo">Nueva clase en vivo</h3>
      <button class="modal-close" onclick="closeModal('mVivo')"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="vvId">
      <div class="form-grid" style="margin-bottom:1rem;">
        <div class="field field-full"><label>Título *</label><input id="vvTitulo" placeholder="Ej. Clase en vivo — Repaso general"></div>
        <div class="field field-full"><label>Descripción</label><textarea id="vvDescripcion" rows="2"></textarea></div>
        <div class="field"><label>Fecha y hora *</label><input type="datetime-local" id="vvFechaHora"></div>
        <div class="field" id="fPlataforma"><label>Plataforma *</label>
          <select id="vvPlataforma" onchange="cambioPlataforma()">
            <option value="jitsi">Jitsi Meet — genera la sala automáticamente</option>
            <option value="meet">Google Meet — pego el link</option>
            <option value="otro">Otra plataforma — pego el link</option>
          </select>
        </div>
        <div class="field field-full" id="fUrlExterna" style="display:none;">
          <label id="lblUrlExterna">Link de la reunión *</label>
          <input id="vvUrl" type="url" placeholder="https://meet.google.com/xxx-xxxx-xxx">
        </div>
        <div class="field field-full" id="avisoJitsi" style="margin:0;">
          <div class="info-box" style="background:var(--cream);border:1px solid var(--border);border-radius:8px;padding:.7rem 1rem;font-size:.8rem;color:var(--muted);display:flex;gap:.5rem;align-items:flex-start;">
            <span>ℹ️</span>
            <span>El sistema genera un link único de Jitsi Meet (gratis, sin cuenta). Cualquiera con el link puede entrar — solo compartilo con tu materia.</span>
          </div>
        </div>
      </div>
      <div style="display:flex;justify-content:flex-end;gap:.6rem;">
        <button type="button" class="btn btn-secondary" onclick="closeModal('mVivo')">Cancelar</button>
        <button type="button" class="btn btn-primary" onclick="guardarVivo()">Guardar</button>
      </div>
    </div>
  </div>
</div>

<script>
const MATERIA_ID = <?=$materia_id?>;
let CAN_MANAGE = false;
let EDITANDO = false;

function h(s){ const d=document.createElement('div'); d.textContent=String(s??''); return d.innerHTML; }
function vivoAjax(action, data={}) { return ajax(action, {...data, materia_id: MATERIA_ID}, 'api/clases_vivo.php'); }

const PLATAFORMA_LBL = {jitsi:'Jitsi Meet', meet:'Google Meet', otro:'Otro'};
const ESTADO_LBL   = {programada:'Programada', en_curso:'🔴 En curso', finalizada:'Finalizada', cancelada:'Cancelada'};
const ESTADO_BADGE = {programada:'b-tardanza', en_curso:'b-presente', finalizada:'b-profesor', cancelada:'b-ausente'};

document.addEventListener('ibbs:ready', iniciarVivo);

async function cargarSelectorMaterias() {
  const d = await vivoAjax('materias_mias');
  const sel = document.getElementById('selMateriaVivo');
  if (!d?.ok || !d.data.length) { sel.innerHTML = '<option value="">Sin materias asignadas</option>'; return; }
  sel.innerHTML = '<option value="">— Elige una materia —</option>' +
    d.data.map(m => `<option value="${m.id}" ${m.id==MATERIA_ID?'selected':''}>${h(m.codigo)} · ${h(m.nombre)}</option>`).join('');
}

async function iniciarVivo() {
  cargarSelectorMaterias();
  if (!MATERIA_ID) return;

  const d = await vivoAjax('materia_info');
  document.getElementById('areaCargando').style.display = 'none';
  if (!d?.ok) { document.getElementById('areaSinPermiso').style.display = 'block'; return; }

  CAN_MANAGE = !!d.data.can_manage;
  document.getElementById('matTitulo').textContent = d.data.materia.nombre;
  document.getElementById('matCodigo').textContent = d.data.materia.codigo;
  document.querySelectorAll('.can-manage').forEach(el => el.style.display = CAN_MANAGE ? 'inline-flex' : 'none');
  document.getElementById('areaVivo').style.display = 'block';

  loadVivo();
}

function fmtFechaHora(s) {
  if (!s) return '—';
  const d = new Date(s.replace(' ', 'T'));
  if (isNaN(d)) return s;
  return d.toLocaleString('es-VE', {day:'2-digit', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit'});
}

async function loadVivo() {
  const d = await vivoAjax('vivo_list');
  const el = document.getElementById('listaVivo');
  if (!d?.ok) { el.innerHTML = '<p style="color:var(--muted);text-align:center;padding:2rem;">'+h(d?.msg||'Error')+'</p>'; return; }
  if (!d.data.length) { el.innerHTML = '<p style="color:var(--muted);text-align:center;padding:2rem;">Sin clases en vivo programadas todavía.</p>'; return; }

  el.innerHTML = d.data.map(v => {
    const joinBtn = v.join_url
      ? `<a class="btn btn-sm btn-primary" href="${h(v.join_url)}" target="_blank" rel="noopener noreferrer">▶ Unirse</a>`
      : `<span style="font-size:.76rem;color:var(--muted);">Sin link configurado</span>`;

    const accionesGestion = CAN_MANAGE ? `
      <select onchange="cambiarEstado(${v.id}, this.value)" style="width:auto;font-size:.76rem;padding:.35rem .5rem;">
        ${Object.keys(ESTADO_LBL).map(k => `<option value="${k}" ${k===v.estado?'selected':''}>${ESTADO_LBL[k]}</option>`).join('')}
      </select>
      <button class="btn btn-sm btn-secondary" onclick='editarVivo(${JSON.stringify(v)})'>Editar</button>
      <button class="btn btn-sm btn-danger" onclick="eliminarVivo(${v.id})">Eliminar</button>
    ` : '';

    return `<div class="card">
      <div class="card-body" style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:1rem;">
        <div style="flex:1;min-width:220px;">
          <div style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;margin-bottom:.3rem;">
            <h3 style="font-family:'DM Serif Display',serif;font-size:1.05rem;">${h(v.titulo)}</h3>
            <span class="badge ${ESTADO_BADGE[v.estado]||'b-profesor'}">${ESTADO_LBL[v.estado]||v.estado}</span>
            <span class="badge b-profesor">${h(PLATAFORMA_LBL[v.plataforma]||v.plataforma)}</span>
          </div>
          ${v.descripcion ? `<p style="font-size:.82rem;color:var(--muted);margin-bottom:.4rem;">${h(v.descripcion)}</p>` : ''}
          <div style="font-size:.78rem;color:var(--muted);">📅 ${fmtFechaHora(v.fecha_hora)} · Creada por ${h(v.autor)}</div>
        </div>
        <div style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;">
          ${joinBtn}
          ${accionesGestion}
        </div>
      </div>
    </div>`;
  }).join('');
}

function cambioPlataforma() {
  const p = document.getElementById('vvPlataforma').value;
  const esExterna = p !== 'jitsi';
  document.getElementById('fUrlExterna').style.display = esExterna ? 'block' : 'none';
  document.getElementById('avisoJitsi').style.display = p === 'jitsi' ? 'block' : 'none';
  document.getElementById('lblUrlExterna').textContent = p === 'meet' ? 'Link de Google Meet *' : 'Link de la reunión *';
}

function abrirVivoModal() {
  EDITANDO = false;
  document.getElementById('vvId').value = '';
  document.getElementById('vvTitulo').value = '';
  document.getElementById('vvDescripcion').value = '';
  document.getElementById('vvFechaHora').value = '';
  document.getElementById('vvUrl').value = '';
  document.getElementById('vvPlataforma').value = 'jitsi';
  document.getElementById('vvPlataforma').disabled = false;
  document.getElementById('fPlataforma').style.opacity = 1;
  cambioPlataforma();
  document.getElementById('mVivoTitulo').textContent = 'Nueva clase en vivo';
  openModal('mVivo');
}
function editarVivo(v) {
  EDITANDO = true;
  document.getElementById('vvId').value = v.id;
  document.getElementById('vvTitulo').value = v.titulo;
  document.getElementById('vvDescripcion').value = v.descripcion || '';
  // datetime-local espera "YYYY-MM-DDTHH:MM"
  document.getElementById('vvFechaHora').value = (v.fecha_hora || '').replace(' ', 'T').substring(0, 16);
  document.getElementById('vvPlataforma').value = v.plataforma;
  document.getElementById('vvPlataforma').disabled = true; // no se cambia de plataforma al editar
  document.getElementById('fPlataforma').style.opacity = .6;
  document.getElementById('vvUrl').value = v.join_url && v.plataforma !== 'jitsi' ? v.join_url : '';
  cambioPlataforma();
  document.getElementById('mVivoTitulo').textContent = 'Editar clase en vivo';
  openModal('mVivo');
}
async function guardarVivo() {
  const id = document.getElementById('vvId').value;
  const titulo = document.getElementById('vvTitulo').value.trim();
  const fechaHora = document.getElementById('vvFechaHora').value;
  if (!titulo) { Ibbs.error('Ponle un título a la clase.'); return; }
  if (!fechaHora) { Ibbs.error('Elegí fecha y hora.'); return; }

  const payload = {
    id, titulo, fecha_hora: fechaHora,
    descripcion: document.getElementById('vvDescripcion').value.trim(),
  };
  if (!EDITANDO) {
    payload.plataforma = document.getElementById('vvPlataforma').value;
    payload.url = document.getElementById('vvUrl').value.trim();
    if (payload.plataforma !== 'jitsi' && !payload.url) {
      Ibbs.error('Pega el link de la reunión.'); return;
    }
  }
  const d = await vivoAjax(EDITANDO ? 'vivo_update' : 'vivo_create', payload);
  if (d?.ok) { toast(d.msg); closeModal('mVivo'); loadVivo(); }
  else toast(d?.msg || 'Error', 'err');
}
async function cambiarEstado(id, estado) {
  const d = await vivoAjax('vivo_set_estado', {id, estado});
  if (d?.ok) { toast(d.msg); loadVivo(); } else { toast(d?.msg || 'Error', 'err'); loadVivo(); }
}
function eliminarVivo(id) {
  ibbsConfirm('¿Eliminar esta clase en vivo?', async () => {
    const d = await vivoAjax('vivo_delete', {id});
    if (d?.ok) { toast(d.msg); loadVivo(); } else Ibbs.error(d?.msg || 'Error');
  });
}
</script>

<?php include __DIR__.'/layout/foot.php'; ?>
