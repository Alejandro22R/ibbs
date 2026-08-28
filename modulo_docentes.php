<?php
$page_title = 'Docentes';
$page_sub   = 'Registro y gestión del personal docente';
$active_link = 'docentes';
include __DIR__.'/layout/head.php';
// Acceso admin o superadmin
if(!in_array($_rol,['superadmin','admin'])){
    echo '<script>window.location="index.php";</script>'; exit;
}

?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.2rem;">
  <a href="api/export_plantilla.php?tipo=docentes" target="_blank" class="btn btn-secondary" style="display:flex;align-items:center;gap:.4rem;font-size:.82rem;">&#128424; Exportar PDF</a>
  <button class="btn btn-primary" onclick="openModal('mCD')">
    <svg viewBox="0 0 24 24" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
    Nuevo Docente
  </button>
</div>
<div class="card">
  <div class="card-head">
    <h3>Historial de Docentes</h3>
    <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
      <input type="text" id="fDocNombre" data-only="letters" placeholder="Nombre o apellido…" oninput="filtrarDocentes()"
        style="padding:.55rem .9rem;border:1.5px solid var(--border);border-radius:8px;font-size:.84rem;outline:none;background:var(--paper);width:180px;">
      <input type="text" id="fDocCedula" data-only="cedula" placeholder="Cédula…" oninput="filtrarDocentes()"
        style="padding:.55rem .9rem;border:1.5px solid var(--border);border-radius:8px;font-size:.84rem;outline:none;background:var(--paper);width:140px;">
    </div>
  </div>
  <div class="tbl-wrap">
    <table id="tblD">
      <thead><tr><th>Cédula</th><th>Nombre</th><th>Correo</th><th>Ciudad</th><th>Especialidad</th><th>Materias</th><th>Estado</th><th>Acciones</th></tr></thead>
      <tbody id="tbodyD"><tr class="empty-row"><td colspan="8"><span class="spin"></span></td></tr></tbody>
    </table>
  </div>
</div>

<!-- MODAL CREAR -->
<div class="modal-backdrop" id="mCD">
  <div class="modal">
    <div class="modal-head"><h3>Nuevo Docente</h3>
      <button class="modal-close" onclick="closeModal('mCD')"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
    </div>
    <div class="modal-body">
      <form id="fCD" onsubmit="createDoc(event)">
        <div class="form-grid" style="margin-bottom:1rem;">
          <div class="field"><label>Nombre *</label><input name="nombre" data-only="letters" placeholder="Carlos"></div>
          <div class="field"><label>Apellido *</label><input name="apellido" data-only="letters" placeholder="Rodríguez"></div>
          <div class="field"><label>Cédula *</label><input name="cedula" data-only="cedula" placeholder="V-12345678"></div>
          <div class="field"><label>Gmail *</label><input type="email" name="correo" placeholder="correo@gmail.com"></div>
          <div class="field"><label>Teléfono</label><input name="telefono" data-only="phone" placeholder="0414-1234567"></div>
          <div class="field"><label>Especialidad</label><input name="especialidad" data-only="letters" placeholder="Matemáticas"></div>
          <div class="field"><label>Ciudad</label><input name="ciudad" data-only="letters" placeholder="Caracas, Valencia…"></div>
        </div>
        <div style="display:flex;justify-content:flex-end;gap:.6rem;">
          <button type="button" class="btn btn-secondary" onclick="closeModal('mCD')">Cancelar</button>
          <button type="submit" class="btn btn-primary">Registrar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- MODAL EDITAR -->
<div class="modal-backdrop" id="mED">
  <div class="modal">
    <div class="modal-head"><h3>Editar Docente</h3>
      <button class="modal-close" onclick="closeModal('mED')"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="eDId">
      <div class="form-grid" style="margin-bottom:1rem;">
        <div class="field"><label>Nombre</label><input id="eDN"></div>
        <div class="field"><label>Apellido</label><input id="eDA"></div>
        <div class="field"><label>Cédula</label><input id="eDC"></div>
        <div class="field"><label>Correo</label><input id="eDM" type="email"></div>
        <div class="field"><label>Teléfono</label><input id="eDT"></div>
        <div class="field"><label>Especialidad</label><input id="eDE"></div>
        <div class="field"><label>Ciudad</label><input id="eDCi"></div>
        <div class="field"><label>Estado</label><select id="eDAct"><option value="1">Activo</option><option value="0">Inactivo</option></select></div>
      </div>
      <div style="display:flex;justify-content:flex-end;gap:.6rem;">
        <button class="btn btn-secondary" onclick="closeModal('mED')">Cancelar</button>
        <button class="btn btn-primary" onclick="saveEdit()">Guardar</button>
      </div>
    </div>
  </div>
</div>

<!-- MODAL PERFIL -->
<div class="modal-backdrop" id="mPD">
  <div class="modal md">
    <div class="modal-head"><h3>Perfil del Docente</h3>
      <button class="modal-close" onclick="closeModal('mPD')"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
    </div>
    <div class="modal-body" id="perfilD"></div>
  </div>
</div>

<script>
document.addEventListener('ibbs:ready', () => loadDocentes());
async function loadDocentes(){
  console.log('[IBBS] Calling docente_list...'); const d=await ajax('docente_list'); console.log('[IBBS] docente_list response:', d); if(!d?.ok){ document.getElementById('tbodyD').innerHTML='<tr class="empty-row"><td colspan="8">'+( d?.msg||'Error al conectar')+'</td></tr>'; return; }
  const tb=document.getElementById('tbodyD');
  if(!d.data.length){tb.innerHTML='<tr class="empty-row"><td colspan="8">Sin docentes.</td></tr>';return;}
  tb.innerHTML=d.data.map(r=>`<tr>
    <td><strong>${r.cedula}</strong></td>
    <td>${r.apellido}, ${r.nombre}</td>
    <td style="font-size:.82rem;">${r.correo}</td>
    <td style="font-size:.82rem;">${r.ciudad||'—'}</td>
    <td style="font-size:.82rem;color:var(--muted);">${r.especialidad||'—'}</td>
    <td><span class="badge b-profesor">${r.nm}</span></td>
    <td><span class="badge ${r.activo=='1'?'b-activo':'b-inactivo'}">${r.activo=='1'?'Activo':'Inactivo'}</span></td>
    <td class="td-actions">
      <button class="btn btn-sm btn-secondary" onclick="verPerfil(${r.id})">Perfil</button>
      <button class="btn btn-sm btn-primary" onclick="editDoc(${r.id})">Editar</button>
      <button class="btn btn-sm btn-danger" onclick="delDoc(${r.id},'${(r.nombre+' '+r.apellido).replace(/'/g,"\\'")}')">Eliminar</button>
    </td></tr>`).join('');
}
async function createDoc(e){
  e.preventDefault();
  if(!validarForm([
    {name:'nombre',   label:'Nombre',   tipo:'texto', min:2},
    {name:'apellido', label:'Apellido', tipo:'texto', min:2},
    {name:'cedula',   label:'Cédula',   tipo:'cedula'},
    {name:'correo',   label:'Correo',   tipo:'email'},
  ])) return;
  const fd=new FormData(e.target); fd.append('action','docente_create');
  const r=await fetch('api/ajax.php',{method:'POST',body:fd}); const d=await r.json();
  if(d.ok){toast(d.msg);closeModal('mCD');e.target.reset();loadDocentes();}else Ibbs.error(d.msg);
}
async function editDoc(id){
  const d=await ajax('docente_get',{id}); if(!d?.ok){toast(d?.msg||'Err','err');return;}
  const r=d.data;
  document.getElementById('eDId').value=id;
  document.getElementById('eDN').value=r.nombre; document.getElementById('eDA').value=r.apellido;
  document.getElementById('eDC').value=r.cedula; document.getElementById('eDM').value=r.correo;
  document.getElementById('eDT').value=r.telefono||''; document.getElementById('eDE').value=r.especialidad||'';
  document.getElementById('eDCi').value=r.ciudad||''; document.getElementById('eDAct').value=r.activo;
  openModal('mED');
}
async function saveEdit(){
  const d=await ajax('docente_update',{id:document.getElementById('eDId').value,nombre:document.getElementById('eDN').value,apellido:document.getElementById('eDA').value,cedula:document.getElementById('eDC').value,correo:document.getElementById('eDM').value,telefono:document.getElementById('eDT').value,especialidad:document.getElementById('eDE').value,ciudad:document.getElementById('eDCi').value,activo:document.getElementById('eDAct').value});
  if(d?.ok){toast(d.msg);closeModal('mED');loadDocentes();}else toast(d?.msg||'Err','err');
}
async function verPerfil(id){
  const d=await ajax('docente_get',{id}); if(!d?.ok){toast(d?.msg,'err');return;}
  const r=d.data; const ini=(r.nombre||'?')[0].toUpperCase();
  const asist=r.asistencias||{}; const tot=Object.values(asist).reduce((a,b)=>a+b,0);
  document.getElementById('perfilD').innerHTML=`
    <div class="profile-card">
      <div class="profile-ava">${ini}</div>
      <div class="profile-name">${r.nombre} ${r.apellido}</div>
      <div class="profile-meta">Cédula: ${r.cedula} · ${r.correo}</div>
      ${r.telefono?`<div class="profile-meta">Tel: ${r.telefono}</div>`:''}
      ${r.ciudad?`<div class="profile-meta">Ciudad: ${r.ciudad}</div>`:''}
      <div class="profile-chips" style="margin-top:.8rem;">
        <span class="profile-chip lime">Docente</span>
        ${r.especialidad?`<span class="profile-chip">${r.especialidad}</span>`:''}
        <span class="profile-chip ${r.activo?'lime':''}">${r.activo?'Activo':'Inactivo'}</span>
      </div>
    </div>
    <div class="form-grid" style="margin-bottom:1.5rem;">
      <div style="background:var(--cream);border-radius:12px;padding:1rem;text-align:center;"><div style="font-family:'DM Serif Display',serif;font-size:2rem;">${r.materias.length}</div><div style="font-size:.72rem;text-transform:uppercase;letter-spacing:1px;color:var(--muted);">Materias</div></div>
      <div style="background:var(--cream);border-radius:12px;padding:1rem;text-align:center;"><div style="font-family:'DM Serif Display',serif;font-size:2rem;">${tot}</div><div style="font-size:.72rem;text-transform:uppercase;letter-spacing:1px;color:var(--muted);">Asistencias</div></div>
    </div>
    <p style="font-size:.75rem;font-weight:600;text-transform:uppercase;letter-spacing:1px;color:var(--muted);margin-bottom:.6rem;">Materias</p>
    ${r.materias.length?r.materias.map(m=>`<div style="display:flex;justify-content:space-between;padding:.55rem .8rem;background:var(--cream);border-radius:8px;margin-bottom:.35rem;font-size:.85rem;"><span><strong>${m.codigo}</strong> · ${m.nombre}</span><span class="badge ${m.estado==='culminada'?'b-presente':'b-tardanza'}" style="font-size:.68rem;">${m.estado||'en_curso'}</span></div>`).join(''):'<em style="color:var(--muted);font-size:.84rem;">Sin materias.</em>'}
    <hr class="divider">
    <p style="font-size:.75rem;font-weight:600;text-transform:uppercase;letter-spacing:1px;color:var(--muted);margin-bottom:.6rem;">Asistencias</p>
    <div style="display:flex;gap:.5rem;flex-wrap:wrap;">${Object.entries({presente:'b-presente',ausente:'b-ausente',tardanza:'b-tardanza',justificado:'b-justificado'}).map(([k,c])=>`<span class="badge ${c}">${k}: ${asist[k]||0}</span>`).join('')}</div>`;
  openModal('mPD');
}
async function delDoc(id,n){
  ibbsConfirm(`¿Eliminar al docente "${n}"? Esta acción es irreversible.`, async ()=>{
    const d=await ajax('docente_delete',{id});
    if(d?.ok){toast(d.msg);loadDocentes();}else Ibbs.error(d?.msg||'Error');
  });
}
function filterTable(t,q){document.querySelectorAll('#'+t+' tbody tr:not(.empty-row)').forEach(r=>r.style.display=r.textContent.toLowerCase().includes(q.toLowerCase())?'':'none');}

function filtrarDocentes() {
  const qN = (document.getElementById('fDocNombre')?.value||'').toLowerCase();
  const qC = (document.getElementById('fDocCedula')?.value||'').toLowerCase();
  document.querySelectorAll('#tblD tbody tr:not(.empty-row)').forEach(tr => {
    const matchN = !qN || tr.textContent.toLowerCase().includes(qN);
    const matchC = !qC || tr.textContent.toLowerCase().includes(qC);
    tr.style.display = (matchN && matchC) ? '' : 'none';
  });
}
</script>
<?php include __DIR__.'/layout/foot.php'; ?>
