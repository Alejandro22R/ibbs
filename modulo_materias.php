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
      <thead><tr><th>Código</th><th>Materia</th><th>Horario</th><th>Estado</th><th>Docentes</th><th>Alumnos</th><th>Acciones</th></tr></thead>
      <tbody id="tbodyM"><tr class="empty-row"><td colspan="7"><span class="spin"></span></td></tr></tbody>
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
          <div class="field"><label>Nombre *</label><input name="nombre" data-only="letters" placeholder="Matemáticas I"></div>
          <div class="field"><label>Código *</label><input name="codigo" data-only="code" placeholder="MAT-101"></div>
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
      </div>
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
      <div class="tab-pane" data-pane-group="eM" data-pane="doc">
        <div style="display:flex;gap:.7rem;margin-bottom:1rem;align-items:flex-end;">
          <div class="field" style="flex:1;margin:0;"><label>Agregar docente</label><select id="selAddD"><option value="">— Seleccionar —</option></select></div>
          <button class="btn btn-primary" onclick="addDoc()">Agregar</button>
        </div>
        <div class="tbl-wrap"><table><thead><tr><th>Docente</th><th></th></tr></thead><tbody id="tbMD"></tbody></table></div>
      </div>
      <div class="tab-pane" data-pane-group="eM" data-pane="alu">
        <div style="display:flex;gap:.7rem;margin-bottom:1rem;align-items:flex-end;">
          <div class="field" style="flex:1;margin:0;"><label>Inscribir alumno</label><select id="selAddA"><option value="">— Seleccionar —</option></select></div>
          <button class="btn btn-primary" onclick="addAlu()">Inscribir</button>
        </div>
        <div class="tbl-wrap"><table><thead><tr><th>Alumno</th><th></th></tr></thead><tbody id="tbMA"></tbody></table></div>
      </div>
    </div>
  </div>
</div>

<script>
let _mid=null;
document.addEventListener('ibbs:ready', () => loadMaterias());
(async()=>{
  const d=await ajax('docente_all_simple'); if(d?.ok) document.getElementById('selAddD').innerHTML='<option value="">— Seleccionar —</option>'+d.data.map(x=>`<option value="${x.id}">${x.apellido}, ${x.nombre} (${x.cedula})</option>`).join('');
  const a=await ajax('alumno_all_simple'); if(a?.ok) document.getElementById('selAddA').innerHTML='<option value="">— Seleccionar —</option>'+a.data.map(x=>`<option value="${x.id}">${x.apellido}, ${x.nombre} (${x.cedula})</option>`).join('');
})();

function estadoBadge(e){ const m={en_curso:'b-tardanza',pendiente:'b-ausente',culminada:'b-presente'}; const l={en_curso:'En curso',pendiente:'Pendiente',culminada:'Culminada'}; return `<span class="badge ${m[e]||'b-tardanza'}">${l[e]||e}</span>`; }

async function loadMaterias(){
  console.log('[IBBS] Calling materia_list...'); const d=await ajax('materia_list'); console.log('[IBBS] materia_list response:', d); if(!d?.ok){ document.getElementById('tbodyM').innerHTML='<tr class="empty-row"><td colspan="7">'+( d?.msg||'Error al conectar')+'</td></tr>'; return; }
  const tb=document.getElementById('tbodyM');
  if(!d.data.length){tb.innerHTML='<tr class="empty-row"><td colspan="7">Sin materias.</td></tr>';return;}
  tb.innerHTML=d.data.map(m=>`<tr>
    <td><strong>${m.codigo}</strong></td><td>${m.nombre}</td>
    <td style="font-size:.79rem;color:var(--muted);">${m.dias||'—'} ${m.hora_inicio?m.hora_inicio.substring(0,5):''}${m.hora_fin?'–'+m.hora_fin.substring(0,5):''}</td>
    <td>${estadoBadge(m.estado||'en_curso')}</td>
    <td><span class="badge b-profesor">${m.nd}</span></td>
    <td><span class="badge b-alumno">${m.na}</span></td>
    <td class="td-actions">
      <a class="btn btn-sm btn-secondary" href="modulo_aula.php?materia_id=${m.id}" title="Aula Virtual">🎓 Aula</a>
      <a class="btn btn-sm btn-secondary" href="modulo_grabaciones.php?materia_id=${m.id}" title="Clases Grabadas">🎬 Grabadas</a>
      <button class="btn btn-sm btn-success" onclick="toggleEstado(${m.id},'${m.estado||'en_curso'}')" style="font-size:.72rem;">${m.estado==='culminada'?'↺ Reabrir':'✓ Culminar'}</button>
      <button class="btn btn-sm btn-primary" onclick="editM(${m.id})">Editar</button>
      <button class="btn btn-sm btn-danger" onclick="delM(${m.id},'${m.nombre.replace(/'/g,"\\'")}')">Eliminar</button>
    </td></tr>`).join('');
}

async function submitCreate(e){
  e.preventDefault();
  const fd=new FormData(e.target); fd.append('action','materia_create');
  fd.set('dias',[...document.querySelectorAll('.cDia:checked')].map(c=>c.value).join(','));
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

function renderMD(list){
  document.getElementById('tbMD').innerHTML=list.length?list.map(d=>`<tr><td>${d.apellido||''} ${d.nombre}</td><td class="td-actions"><button class="btn btn-sm btn-danger" onclick="rmDoc(${d.id},this)">Quitar</button></td></tr>`).join(''):'<tr class="empty-row"><td colspan="2">Sin docentes</td></tr>';
}
function renderMA(list){
  document.getElementById('tbMA').innerHTML=list.length?list.map(a=>`<tr><td>${a.apellido||''} ${a.nombre}</td><td class="td-actions"><button class="btn btn-sm btn-danger" onclick="rmAlu(${a.id},this)">Quitar</button></td></tr>`).join(''):'<tr class="empty-row"><td colspan="2">Sin alumnos</td></tr>';
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
</script>

<?php include __DIR__.'/layout/foot.php'; ?>
