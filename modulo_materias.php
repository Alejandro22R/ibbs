<?php
$page_title = 'Materias';
$page_sub   = 'Gestión de materias, horarios y asignaciones';
$active_link = 'materias';
include __DIR__.'/layout/head.php';

// Acceso admin o superadmin
if(!in_array($_rol,['superadmin','admin'])){
    echo '<script>window.location="index.php";</script>'; exit;
}
?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.2rem;">
  <a href="api/export_plantilla.php?tipo=materias" target="_blank" class="btn btn-secondary" style="display:flex;align-items:center;gap:.4rem;font-size:.82rem;">&#128424; Exportar PDF</a>
  <button class="btn btn-primary" onclick="openModal('mCreateMateria')">
    <svg viewBox="0 0 24 24" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
    Nueva Materia
  </button>
</div>

<div class="card">
  <div class="card-head">
    <h3>Materias registradas</h3>
    <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
      <input type="text" id="fMatNombre" data-only="letters" placeholder="Nombre de materia…" oninput="filtrarMaterias()"
        style="padding:.55rem .9rem;border:1.5px solid var(--border);border-radius:8px;font-size:.84rem;outline:none;background:var(--paper);width:200px;">
      <input type="text" id="fMatCodigo" data-only="code" placeholder="Código (ej. MAT-101)…" oninput="filtrarMaterias()"
        style="padding:.55rem .9rem;border:1.5px solid var(--border);border-radius:8px;font-size:.84rem;outline:none;background:var(--paper);width:190px;">
    </div>
  </div>
  <div class="tbl-wrap">
    <table id="tblM">
      <thead><tr><th>Código</th><th>Materia</th><th>Horario</th><th>Estado</th><th>Inscripción</th><th>Docentes</th><th>Alumnos</th><th>Acciones</th></tr></thead>
      <tbody id="tbodyM"><tr class="empty-row"><td colspan="8"><span class="spin"></span></td></tr></tbody>
    </table>
  </div>
</div>

<!-- MODAL CREAR -->
<div class="modal-backdrop" id="mCreateMateria">
  <div class="modal">
    <div class="modal-head"><h3>Nueva Materia</h3>
      <button class="modal-close" onclick="closeModal('mCreateMateria')"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
    </div>
    <div class="modal-body">
      <form id="fCM" onsubmit="submitCreate(event)">
        <div class="form-grid" style="margin-bottom:1rem;">
          <div class="field"><label>Nombre *</label><input name="nombre" data-only="letters" placeholder="Matemáticas I" required></div>
          <div class="field"><label>Código *</label><input name="codigo" data-only="code" placeholder="MAT-101" required></div>
          
          <!-- SECTOR AÑADIDO: Asignación de docente desde la creación -->
          <div class="field field-full">
            <label>Docente Asignado</label>
            <select name="docente_id" id="selCreateD">
              <option value="">— Sin asignar —</option>
            </select>
          </div>
          
          <div class="field field-full"><label>Descripción</label><textarea name="descripcion" rows="2"></textarea></div>
          <div class="field field-full">
            <label>Días</label>
            <div style="display:flex;flex-wrap:wrap;gap:.5rem;margin-top:.3rem;">
              <?php foreach(['Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'] as $d): ?>
              <label style="display:flex;align-items:center;gap:.3rem;font-size:.83rem;cursor:pointer;text-transform:none;opacity:1;letter-spacing:0;"><input type="checkbox" class="cDia" value="<?=$d?>" style="width:auto;padding:0;border:none;background:none;accent-color:var(--lime2);"><?=$d?></label>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="field"><label>Hora inicio</label><input type="time" name="hora_inicio"></div>
          <div class="field"><label>Hora fin</label><input type="time" name="hora_fin"></div>
        </div>
        <div style="display:flex;justify-content:flex-end;gap:.6rem;">
          <button type="button" class="btn btn-secondary" onclick="closeModal('mCreateMateria')">Cancelar</button>
          <button type="submit" class="btn btn-primary">Crear</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- MODAL EDITAR -->
<div class="modal-backdrop" id="mEditMateria">
  <div class="modal md">
    <div class="modal-head"><h3 id="editMTitle">Editar Materia</h3>
      <button class="modal-close" onclick="closeModal('mEditMateria')"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="eMId">
      <div class="tabs-nav">
        <button class="tab-btn active" data-tab-group="eM" data-tab="info" onclick="switchTab('eM','info')">Info</button>
        <button class="tab-btn" data-tab-group="eM" data-tab="doc" onclick="switchTab('eM','doc')">Docentes</button>
        <button class="tab-btn" data-tab-group="eM" data-tab="alu" onclick="switchTab('eM','alu')">Alumnos</button>
        <button class="tab-btn" data-tab-group="eM" data-tab="cal" onclick="switchTab('eM','cal'); loadCalificacionesMateria();">Calificaciones</button>
      </div>
      
      <!-- Tab Info Básica -->
      <div class="tab-pane active" data-pane-group="eM" data-pane="info">
        <div class="form-grid" style="margin-bottom:1rem;">
          <div class="field"><label>Nombre</label><input id="eNN"></div>
          <div class="field"><label>Código</label><input id="eNC"></div>
          <div class="field field-full"><label>Descripción</label><textarea id="eND" rows="2"></textarea></div>
          <div class="field field-full"><label>Días</label>
            <div style="display:flex;flex-wrap:wrap;gap:.5rem;margin-top:.3rem;">
              <?php foreach(['Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'] as $d): ?>
              <label style="display:flex;align-items:center;gap:.3rem;font-size:.83rem;cursor:pointer;text-transform:none;opacity:1;letter-spacing:0;"><input type="checkbox" class="eDia" value="<?=$d?>" style="width:auto;padding:0;border:none;background:none;accent-color:var(--lime2);"><?=$d?></label>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="field"><label>Hora inicio</label><input type="time" id="eNHi"></div>
          <div class="field"><label>Hora fin</label><input type="time" id="eNHf"></div>
        </div>
        <button class="btn btn-primary" onclick="submitEdit()">Guardar cambios</button>
      </div>
      
      <!-- Tab Docentes (Estilo pulido) -->
      <div class="tab-pane" data-pane-group="eM" data-pane="doc">
        <div class="section-label" style="margin-top:.5rem;">AGREGAR DOCENTE</div>
        <div style="display:flex;gap:.7rem;margin-bottom:1.5rem;align-items:center;">
          <div class="field" style="flex:1;margin:0;">
            <select id="selAddD"><option value="">— Seleccionar —</option></select>
          </div>
          <button class="btn btn-primary" onclick="addDoc()">Agregar</button>
        </div>
        <div class="tbl-wrap">
          <table>
            <thead><tr><th>Docente</th><th style="width: 80px;"></th></tr></thead>
            <tbody id="tbMD"></tbody>
          </table>
        </div>
      </div>
      
      <!-- Tab Alumnos (Estilo pulido) -->
      <div class="tab-pane" data-pane-group="eM" data-pane="alu">
        <div class="section-label" style="margin-top:.5rem;">INSCRIBIR ALUMNO</div>
        <div style="display:flex;gap:.7rem;margin-bottom:1.5rem;align-items:center;">
          <div class="field" style="flex:1;margin:0;">
            <select id="selAddA"><option value="">— Seleccionar —</option></select>
          </div>
          <button class="btn btn-primary" onclick="addAlu()">Inscribir</button>
        </div>
        <div class="tbl-wrap">
          <table>
            <thead><tr><th>Alumno</th><th style="width: 80px;"></th></tr></thead>
            <tbody id="tbMA"></tbody>
          </table>
        </div>
      </div>

      <!-- Tab Calificaciones — resumen de solo lectura de esta materia.
           Para cargar o corregir una nota final, siempre desde "Cargar
           Notas" (modulo_notas.php) — así hay un solo lugar donde se
           puede editar, en vez de dos formularios distintos para lo
           mismo. -->
      <div class="tab-pane" data-pane-group="eM" data-pane="cal">
        <div id="calMateriaResumen" style="margin-bottom:1rem;"></div>
        <div class="section-label" style="margin-bottom:.5rem;display:flex;justify-content:space-between;align-items:center;gap:.6rem;flex-wrap:wrap;">
          <span>NOTA FINAL POR ALUMNO</span>
          <a href="modulo_notas.php" class="btn btn-sm btn-secondary" style="text-transform:none;letter-spacing:normal;font-weight:600;">
            <i class="bx bx-edit-alt"></i> Cargar / corregir en Cargar Notas
          </a>
        </div>
        <div class="tbl-wrap" style="margin-bottom:1.6rem;">
          <table>
            <thead><tr><th style="text-align:left;">Alumno</th><th style="text-align:center;">Nota final</th><th style="text-align:center;">Resultado</th></tr></thead>
            <tbody id="tbCalAlumnos"><tr class="empty-row"><td colspan="3"><span class="spin"></span></td></tr></tbody>
          </table>
        </div>
        <div class="section-label" style="margin-bottom:.5rem;">PLAN DE NOTAS (ACTIVIDADES DEL PROFESOR)</div>
        <div id="calMateriaPlan" style="color:var(--muted);font-size:.85rem;">Cargando…</div>
      </div>
    </div>
  </div>
</div>

<script>
let _mid=null;
const MI_ROL='<?=$_rol?>';
document.addEventListener('ibbs:ready', () => loadMaterias());

// Ojo: esto tiene que esperar a 'ibbs:ready' — ajax() recién se define
// en layout/foot.php, que se incluye DESPUÉS de este <script>. Antes
// esto corría de una (IIFE sin esperar nada) y tiraba "ajax is not
// defined" en cada carga de la página, sin excepción.
document.addEventListener('ibbs:ready', async () => {
  // Cargar docentes para el modal de Crear y el de Editar
  const d=await ajax('docente_all_simple');
  if(d?.ok) {
    const opts = '<option value="">— Seleccionar —</option>'+d.data.map(x=>`<option value="${x.id}">${x.apellido}, ${x.nombre} (${x.cedula})</option>`).join('');
    
    // Para el modal de Editar
    const selAddD = document.getElementById('selAddD');
    if(selAddD) selAddD.innerHTML = opts;
    
    // Para el modal de Crear Materia (recién incorporado)
    const selCreateD = document.getElementById('selCreateD');
    if(selCreateD) selCreateD.innerHTML = '<option value="">— Sin asignar —</option>'+d.data.map(x=>`<option value="${x.id}">${x.apellido}, ${x.nombre} (${x.cedula})</option>`).join('');
  }
  
  // Cargar alumnos para el modal de Editar
  const a=await ajax('alumno_all_simple'); 
  if(a?.ok) {
      document.getElementById('selAddA').innerHTML='<option value="">— Seleccionar —</option>'+a.data.map(x=>`<option value="${x.id}">${x.apellido}, ${x.nombre} (${x.cedula})</option>`).join('');
  }
});

function estadoBadge(e){ 
    const m={en_curso:'b-tardanza',pendiente:'b-ausente',culminada:'b-presente'}; 
    const l={en_curso:'En curso',pendiente:'Pendiente',culminada:'Culminada'}; 
    return `<span class="badge ${m[e]||'b-tardanza'}">${l[e]||e}</span>`; 
}

async function loadMaterias(){
  console.log('[IBBS] Calling materia_list...'); const d=await ajax('materia_list'); console.log('[IBBS] materia_list response:', d); if(!d?.ok){ document.getElementById('tbodyM').innerHTML='<tr class="empty-row"><td colspan="8">'+( d?.msg||'Error al conectar')+'</td></tr>'; return; }
  const tb=document.getElementById('tbodyM');
  if(!d.data.length){tb.innerHTML='<tr class="empty-row"><td colspan="8">Sin materias.</td></tr>';return;}
  tb.innerHTML=d.data.map(m=>`<tr>
    <td><strong>${m.codigo}</strong></td><td>${m.nombre}</td>
    <td style="font-size:.79rem;color:var(--muted);">${m.dias||'—'} ${m.hora_inicio?m.hora_inicio.substring(0,5):''}${m.hora_fin?'–'+m.hora_fin.substring(0,5):''}</td>
    <td>${estadoBadge(m.estado||'en_curso')}</td>
    <td>
      <button class="btn btn-sm ${m.inscripcion_abierta==1?'btn-success':'btn-secondary'}" onclick="toggleInscripcion(${m.id})" style="font-size:.7rem;" title="Alumnos regulares pueden autoinscribirse cuando está abierta">
        ${m.inscripcion_abierta==1?'🔓 Abierta':'🔒 Cerrada'}
      </button>
    </td>
    <td><span class="badge b-profesor">${m.nd}</span></td>
    <td><span class="badge b-alumno">${m.na}</span></td>
    <td class="td-actions">
      <a class="btn btn-sm btn-secondary" href="modulo_aula.php?materia_id=${m.id}" title="Aula Virtual">🎓 Aula</a>
      <a class="btn btn-sm btn-secondary" href="modulo_grabaciones.php?materia_id=${m.id}" title="Clases Grabadas">🎬 Grabadas</a>
      <a class="btn btn-sm btn-secondary" href="modulo_vivo.php?materia_id=${m.id}" title="Clases en Vivo">🔴 En Vivo</a>
      <button class="btn btn-sm btn-success" onclick="toggleEstado(${m.id},'${m.estado||'en_curso'}')" style="font-size:.72rem;">${m.estado==='culminada'?'↺ Reabrir':'✓ Culminar'}</button>
      <button class="btn btn-sm btn-primary" onclick="editM(${m.id})">Editar</button>
      <button class="btn btn-sm btn-danger" onclick="delM(${m.id},'${m.nombre.replace(/'/g,"\\'")}')">Eliminar</button>
    </td></tr>`).join('');
}

async function submitCreate(e){
  e.preventDefault();
  const fd=new FormData(e.target); fd.append('action','materia_create');
  fd.set('dias',[...document.querySelectorAll('.cDia:checked')].map(c=>c.value).join(','));
  const _csrf=document.querySelector('meta[name="csrf-token"]'); fd.append('csrf_token',_csrf?_csrf.content:'');
  const r=await fetch('api/ajax.php',{method:'POST',body:fd}); const d=await r.json();
  if(d.ok){toast(d.msg);closeModal('mCreateMateria');e.target.reset();document.querySelectorAll('.cDia').forEach(c=>c.checked=false);loadMaterias();}
  else toast(d.msg,'err');
}

async function editM(id){
  _mid=id; const d=await ajax('materia_get',{id}); if(!d?.ok){toast(d?.msg||'Err','err');return;}
  const m=d.data;
  document.getElementById('eMId').value=id; document.getElementById('editMTitle').textContent='Editar: '+m.nombre;
  document.getElementById('eNN').value=m.nombre; document.getElementById('eNC').value=m.codigo;
  document.getElementById('eND').value=m.descripcion||''; document.getElementById('eNHi').value=m.hora_inicio||''; document.getElementById('eNHf').value=m.hora_fin||'';
  const dias=(m.dias||'').split(','); document.querySelectorAll('.eDia').forEach(c=>c.checked=dias.includes(c.value));
  renderMD(m.docentes); renderMA(m.alumnos);
  switchTab('eM','info'); openModal('mEditMateria');
}

async function submitEdit(){
  const d=await ajax('materia_update',{id:_mid,nombre:document.getElementById('eNN').value,codigo:document.getElementById('eNC').value,descripcion:document.getElementById('eND').value,dias:[...document.querySelectorAll('.eDia:checked')].map(c=>c.value).join(','),hora_inicio:document.getElementById('eNHi').value,hora_fin:document.getElementById('eNHf').value});
  if(d?.ok){toast(d.msg);loadMaterias();}else toast(d?.msg||'Err','err');
}

async function toggleEstado(id,est){
  const next=est==='culminada'?'en_curso':'culminada';
  const lbl = {en_curso:'En curso',culminada:'Culminada',pendiente:'Pendiente'}[next];
  const rr = await Ibbs.warn({title:'Cambiar estado',text:`Cambiar a <b>${lbl}</b>?`,confirm:'Confirmar'});
  if(!rr.isConfirmed) return;
  const d=await ajax('materia_set_estado',{id,estado:next});
  if(d?.ok){toast(d.msg);loadMaterias();}else toast(d?.msg||'Err','err');
}

async function toggleInscripcion(id){
  const d=await ajax('materia_toggle_inscripcion',{id});
  if(d?.ok){toast(d.msg);loadMaterias();}else toast(d?.msg||'Err','err');
}

// ----------------------------------------------------
// Renderizado exacto de tablas (Docentes y Alumnos)
// ----------------------------------------------------
function renderMD(list){
  document.getElementById('tbMD').innerHTML=list.length
    ? list.map(d=>`<tr><td>${d.apellido||''} ${d.nombre}</td><td class="td-actions" style="justify-content:flex-end;"><button class="btn btn-sm btn-danger" onclick="rmDoc(${d.id},this)">Quitar</button></td></tr>`).join('')
    : '<tr class="empty-row"><td colspan="2">Sin docentes</td></tr>';
}

function renderMA(list){
  document.getElementById('tbMA').innerHTML=list.length
    ? list.map(a=>{
        const auto = !!(a.auto_inscrito==1);
        const puedeQuitar = !auto || MI_ROL==='superadmin';
        const accion = puedeQuitar
          ? `<button class="btn btn-sm btn-danger" onclick="rmAlu(${a.id},this)">Quitar</button>`
          : `<span title="Auto-inscripción: solo un superadmin puede quitarla" style="color:var(--muted);font-size:.85rem;">🔒</span>`;
        return `<tr><td>${a.apellido||''} ${a.nombre}${auto?' <span class="badge b-alumno" style="font-size:.6rem;vertical-align:middle;">Auto-inscrito</span>':''}</td><td class="td-actions" style="justify-content:flex-end;">${accion}</td></tr>`;
      }).join('')
    : '<tr class="empty-row"><td colspan="2">Sin alumnos</td></tr>';
}

async function addDoc(){
  const did=document.getElementById('selAddD').value; if(!did){Ibbs.error('Selecciona un docente de la lista.','Sin selección');return;}
  const d=await ajax('materia_add_docente',{materia_id:_mid,docente_id:did});
  if(d?.ok){toast(d.msg);const m=await ajax('materia_get',{id:_mid});if(m?.ok)renderMD(m.data.docentes);}else toast(d?.msg||'Err','err');
}
async function rmDoc(did,btn){btn.disabled=true;const d=await ajax('materia_remove_docente',{materia_id:_mid,docente_id:did});if(d?.ok){toast(d.msg);const m=await ajax('materia_get',{id:_mid});if(m?.ok)renderMD(m.data.docentes);}else{toast(d?.msg||'Err','err');btn.disabled=false;}}
async function addAlu(){
  const aid=document.getElementById('selAddA').value; if(!aid){toast('Selecciona alumno','err');return;}
  const d=await ajax('materia_add_alumno',{materia_id:_mid,alumno_id:aid});
  if(d?.ok){toast(d.msg);const m=await ajax('materia_get',{id:_mid});if(m?.ok)renderMA(m.data.alumnos);}else toast(d?.msg||'Err','err');
}
async function rmAlu(aid,btn){btn.disabled=true;const d=await ajax('materia_remove_alumno',{materia_id:_mid,alumno_id:aid});if(d?.ok){toast(d.msg);const m=await ajax('materia_get',{id:_mid});if(m?.ok)renderMA(m.data.alumnos);}else{toast(d?.msg||'Err','err');btn.disabled=false;}}
async function delM(id,n){
  ibbsConfirm(`¿Eliminar la materia "${n}"? Se borrarán todas sus inscripciones.`, async ()=>{
    const d=await ajax('materia_delete',{id});
    if(d?.ok){toast(d.msg);loadMaterias();}else Ibbs.error(d?.msg||'Error');
  });
}
function filterTable(tbl,q){document.querySelectorAll('#'+tbl+' tbody tr:not(.empty-row)').forEach(r=>r.style.display=r.textContent.toLowerCase().includes(q.toLowerCase())?'':'none');}

function filtrarMaterias() {
  const qN = (document.getElementById('fMatNombre')?.value||'').toLowerCase();
  const qC = (document.getElementById('fMatCodigo')?.value||'').toLowerCase();
  document.querySelectorAll('#tblM tbody tr:not(.empty-row)').forEach(tr => {
    const matchN = !qN || tr.textContent.toLowerCase().includes(qN);
    const matchC = !qC || tr.textContent.toLowerCase().includes(qC);
    tr.style.display = (matchN && matchC) ? '' : 'none';
  });
}

// ══════════════════════════════════════════════════════════════
// PESTAÑA "CALIFICACIONES" — todo lo que antes vivía repartido entre
// modulo_notas.php (nota final) y el Plan de Notas del propio profesor
// (api/plan_notas.php), ahora junto acá adentro de la misma materia:
// el administrador ve y gestiona (view + modify) sin tener que saltar
// de módulo en módulo.
// ══════════════════════════════════════════════════════════════
function hCalM(s) { const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML; }

async function loadCalificacionesMateria() {
  if (!_mid) return;
  await cargarNotasFinalesMateria();
  await cargarPlanNotasMateria();
}

async function cargarNotasFinalesMateria() {
  const resumen = document.getElementById('calMateriaResumen');
  const tbody = document.getElementById('tbCalAlumnos');
  resumen.innerHTML = '<span class="spin"></span>';
  const d = await ajax('notas_tabla_materia', { materia_id: _mid });
  if (!d?.ok) { resumen.innerHTML = `<p style="color:var(--red);">${hCalM(d?.msg || 'Error al cargar.')}</p>`; return; }

  const { alumnos, aprobados, reprobados, sin } = d.data;
  const conNota = alumnos.filter(a => a.nota_final !== null);
  const promedio = conNota.length ? (conNota.reduce((s, a) => s + parseFloat(a.nota_final), 0) / conNota.length).toFixed(1) : '—';

  resumen.innerHTML = `<div class="stats">
    <div class="scard c1"><div><div class="scard-val" data-countup>${alumnos.length}</div><div class="scard-key">Inscritos</div></div></div>
    <div class="scard c3"><div><div class="scard-val" data-countup>${aprobados}</div><div class="scard-key">Aprobados</div></div></div>
    <div class="scard c4"><div><div class="scard-val" data-countup>${reprobados}</div><div class="scard-key">Reprobados</div></div></div>
    <div class="scard c2"><div><div class="scard-val" data-countup>${sin}</div><div class="scard-key">Sin nota</div></div></div>
    <div class="scard c1"><div><div class="scard-val" data-countup>${promedio}</div><div class="scard-key">Promedio</div></div></div>
  </div>`;

  if (!alumnos.length) { tbody.innerHTML = '<tr class="empty-row"><td colspan="3">Esta materia todavía no tiene alumnos inscritos.</td></tr>'; return; }
  tbody.innerHTML = alumnos.map(a => {
    const tieneNota = a.nota_final !== null;
    const aprobada = tieneNota && parseFloat(a.nota_final) >= 15;
    return `<tr>
      <td style="text-align:left;font-weight:700;">${hCalM(a.apellido)}, ${hCalM(a.nombre)}</td>
      <td style="text-align:center;font-family:'DM Serif Display',serif;font-size:1.1rem;color:${tieneNota ? (aprobada?'#16a34a':'#dc2626') : 'var(--muted)'};">${tieneNota ? parseFloat(a.nota_final).toFixed(1) : '—'}</td>
      <td style="text-align:center;"><span class="badge ${!tieneNota ? '' : (aprobada?'b-presente':'b-ausente')}">${!tieneNota ? 'Sin nota' : (aprobada?'Aprobado':'Reprobado')}</span></td>
    </tr>`;
  }).join('');
}

// Plan de Notas (actividades del profesor) — mismo endpoint que ya usa
// portal_docente.php; el admin ya tiene permiso sobre cualquier materia.
async function cargarPlanNotasMateria() {
  const cont = document.getElementById('calMateriaPlan');
  cont.innerHTML = '<span class="spin"></span>';
  const fd = new FormData(); fd.append('accion', 'docente'); fd.append('materia_id', _mid);
  const m = document.querySelector('meta[name="csrf-token"]'); fd.append('csrf_token', m ? m.content : '');
  let d;
  try {
    const r = await fetch('api/plan_notas.php', { method: 'POST', body: fd });
    d = await r.json();
  } catch (e) { cont.innerHTML = '<p style="color:var(--red);">Error de conexión.</p>'; return; }
  if (!d.ok) { cont.innerHTML = `<p style="color:var(--red);">${hCalM(d.msg || 'Error al cargar.')}</p>`; return; }

  const { tareas, alumnos, notas, stats } = d.data;
  if (!tareas.length) { cont.innerHTML = '<p style="color:var(--muted);">El profesor todavía no cargó ninguna actividad en esta materia.</p>'; return; }

  const totalAlumnos = alumnos.length;
  let resumen = '<div style="display:flex;flex-wrap:wrap;gap:.6rem;margin-bottom:1rem;">';
  tareas.forEach(t => {
    const st = (stats && stats[t.id]) || { entregados: 0, calificados: 0 };
    const pct = t.porcentaje !== null ? `${parseFloat(t.porcentaje)}%` : 'sin % asignado';
    resumen += `<div style="padding:.6rem .8rem;border:1px solid var(--border);border-radius:8px;background:var(--cream);min-width:200px;">
      <div style="font-weight:700;font-size:.82rem;color:var(--ink);">${hCalM(t.titulo)}</div>
      <div style="font-size:.72rem;color:var(--muted);margin-top:.2rem;">${pct} · ${st.entregados}/${totalAlumnos||'?'} entregaron · ${st.calificados}/${totalAlumnos||'?'} calificadas</div>
    </div>`;
  });
  resumen += '</div>';

  if (!alumnos.length) { cont.innerHTML = resumen + '<p style="color:var(--muted);">Esta materia todavía no tiene alumnos inscritos.</p>'; return; }

  let tabla = '<div class="tbl-wrap"><table><thead><tr><th style="text-align:left;">Alumno</th>';
  tareas.forEach(t => {
    tabla += `<th style="text-align:center;min-width:100px;" title="${hCalM(t.titulo)}">${hCalM(t.titulo.length>14?t.titulo.substring(0,14)+'…':t.titulo)}</th>`;
  });
  tabla += '<th style="text-align:center;">Promedio</th></tr></thead><tbody>';
  alumnos.forEach(a => {
    tabla += `<tr><td style="text-align:left;font-weight:700;">${hCalM(a.apellido)}, ${hCalM(a.nombre)}</td>`;
    let sumaPonderada = 0, pesoEvaluado = 0;
    tareas.forEach(t => {
      const reg = (notas[a.id] && notas[a.id][t.id]) || null;
      const nota = reg ? parseFloat(reg.nota) : null;
      if (nota === null || isNaN(nota)) { tabla += '<td style="text-align:center;color:var(--muted);">—</td>'; }
      else {
        const color = nota >= 15 ? '#16a34a' : '#dc2626';
        tabla += `<td style="text-align:center;font-weight:700;color:${color};">${nota}</td>`;
        const peso = parseFloat(t.porcentaje) || 0;
        sumaPonderada += nota * peso; pesoEvaluado += peso;
      }
    });
    const promedio = pesoEvaluado > 0 ? (sumaPonderada / pesoEvaluado).toFixed(1) : null;
    tabla += promedio === null
      ? '<td style="text-align:center;color:var(--muted);">—</td>'
      : `<td style="text-align:center;font-weight:700;color:${promedio>=15?'#16a34a':'#dc2626'};">${promedio}</td>`;
    tabla += '</tr>';
  });
  tabla += '</tbody></table></div>';

  cont.innerHTML = resumen + tabla;
}
</script>

<?php include __DIR__.'/layout/foot.php'; ?>