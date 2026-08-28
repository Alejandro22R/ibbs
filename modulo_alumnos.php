<?php
$page_title = 'Alumnos';
$page_sub   = 'Registro y gestión de estudiantes';
$active_link = 'alumnos';
include __DIR__.'/layout/head.php';
// Acceso admin o superadmin
if(!in_array($_rol,['superadmin','admin'])){
    echo '<script>window.location="index.php";</script>'; exit;
}

?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.2rem;">
  <a href="api/export_plantilla.php?tipo=alumnos" target="_blank" class="btn btn-secondary" style="display:flex;align-items:center;gap:.4rem;font-size:.82rem;">&#128424; Exportar PDF</a>
  <button class="btn btn-primary" onclick="openModal('mCA')"><svg viewBox="0 0 24 24" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> Nuevo Alumno</button>
</div>
<div class="card">
  <div class="card-head">
    <h3>Registro de Alumnos</h3>
    <div style="display:flex;gap:.6rem;flex-wrap:wrap;">
      <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
        <input type="text" id="fAlumNombre" data-only="letters" placeholder="Nombre o apellido…" oninput="filtrarAlumnos()"
          style="padding:.55rem .9rem;border:1.5px solid var(--border);border-radius:8px;font-size:.84rem;outline:none;background:var(--paper);width:180px;">
        <input type="text" id="fAlumCedula" data-only="cedula" placeholder="Cédula…" oninput="filtrarAlumnos()"
          style="padding:.55rem .9rem;border:1.5px solid var(--border);border-radius:8px;font-size:.84rem;outline:none;background:var(--paper);width:140px;">
        <input type="text" id="filtCiudad" data-only="letters" placeholder="Ciudad…" oninput="loadAlumnos()"
          style="padding:.55rem .9rem;border:1.5px solid var(--border);border-radius:8px;font-size:.84rem;outline:none;background:var(--paper);width:130px;">
      </div>
    </div>
  </div>
  <div class="tbl-wrap">
    <table id="tblA">
      <thead><tr><th>Cédula</th><th>Nombre</th><th>Correo</th><th>Ciudad</th><th>Materias</th><th>Estado</th><th>Acciones</th></tr></thead>
      <tbody id="tbodyA"><tr class="empty-row"><td colspan="7"><span class="spin"></span></td></tr></tbody>
    </table>
  </div>
</div>

<!-- MODAL CREAR -->
<div class="modal-backdrop" id="mCA">
  <div class="modal">
    <div class="modal-head"><h3>Nuevo Alumno</h3>
      <button class="modal-close" onclick="closeModal('mCA')"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
    </div>
    <div class="modal-body">
      <form id="fCA" onsubmit="createAlumno(event)">
        <div class="form-grid" style="margin-bottom:1rem;">
          <div class="field"><label>Nombre *</label><input name="nombre" data-only="letters" placeholder="María"></div>
          <div class="field"><label>Apellido *</label><input name="apellido" data-only="letters" placeholder="López"></div>
          <div class="field"><label>Cédula *</label><input name="cedula" data-only="cedula" placeholder="V-23456789"></div>
          <div class="field"><label>Gmail *</label><input type="email" name="correo" placeholder="alumno@gmail.com"></div>
          <div class="field"><label>Teléfono</label><input name="telefono" data-only="phone" placeholder="0412-7654321"></div>
          <div class="field"><label>Ciudad</label><input name="ciudad" data-only="letters" placeholder="Caracas, Maracaibo…"></div>
        </div>
        <div style="display:flex;justify-content:flex-end;gap:.6rem;">
          <button type="button" class="btn btn-secondary" onclick="closeModal('mCA')">Cancelar</button>
          <button type="submit" class="btn btn-primary">Registrar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- MODAL EDITAR -->
<div class="modal-backdrop" id="mEA">
  <div class="modal">
    <div class="modal-head"><h3>Editar Alumno</h3>
      <button class="modal-close" onclick="closeModal('mEA')"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="eAId">
      <div class="form-grid" style="margin-bottom:1rem;">
        <div class="field"><label>Nombre</label><input id="eAN"></div>
        <div class="field"><label>Apellido</label><input id="eAA"></div>
        <div class="field"><label>Cédula</label><input id="eAC"></div>
        <div class="field"><label>Correo</label><input id="eAM" type="email"></div>
        <div class="field"><label>Teléfono</label><input id="eAT"></div>
        <div class="field"><label>Ciudad</label><input id="eACi" data-only="letters" placeholder="Escribe la ciudad"></div>
        <div class="field"><label>Estado</label><select id="eAAct"><option value="1">Activo</option><option value="0">Inactivo</option></select></div>
      </div>
      <div style="display:flex;justify-content:flex-end;gap:.6rem;">
        <button class="btn btn-secondary" onclick="closeModal('mEA')">Cancelar</button>
        <button class="btn btn-primary" onclick="saveEditA()">Guardar</button>
      </div>
    </div>
  </div>
</div>

<!-- MODAL PERFIL -->
<div class="modal-backdrop" id="mPA">
  <div class="modal md">
    <div class="modal-head"><h3>Perfil del Alumno</h3>
      <button class="modal-close" onclick="closeModal('mPA')"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
    </div>
    <div class="modal-body" id="perfilA"></div>
  </div>
</div>

<script>
document.addEventListener('ibbs:ready', () => loadAlumnos());
async function loadAlumnos(){
  const ciudad=document.getElementById('filtCiudad').value.trim();
  console.log('[IBBS] Calling alumno_list...'); const d=await ajax('alumno_list',{ciudad}); console.log('[IBBS] alumno_list response:', d); if(!d?.ok){ document.getElementById('tbodyA').innerHTML='<tr class="empty-row"><td colspan="7">'+( d?.msg||'Error al conectar')+'</td></tr>'; return; }
  const tb=document.getElementById('tbodyA');
  if(!d.data.length){tb.innerHTML='<tr class="empty-row"><td colspan="7">Sin alumnos.</td></tr>';return;}
  tb.innerHTML=d.data.map(r=>`<tr>
    <td><strong>${r.cedula}</strong></td>
    <td>${r.apellido}, ${r.nombre}</td>
    <td style="font-size:.82rem;">${r.correo}</td>
    <td style="font-size:.82rem;">${r.ciudad||'—'}</td>
    <td><span class="badge b-alumno">${r.nm}</span></td>
    <td><span class="badge ${r.activo=='1'?'b-activo':'b-inactivo'}">${r.activo=='1'?'Activo':'Inactivo'}</span></td>
    <td class="td-actions">
      <button class="btn btn-sm btn-secondary" onclick="verPerfil(${r.id})">Perfil</button>
      <button class="btn btn-sm btn-primary" onclick="editA(${r.id})">Editar</button>
      <button class="btn btn-sm btn-danger" onclick="delA(${r.id},'${(r.nombre+' '+r.apellido).replace(/'/g,"\\'")}')">Eliminar</button>
    </td></tr>`).join('');
}
async function createAlumno(e){
  e.preventDefault(); const fd=new FormData(e.target); fd.append('action','alumno_create');
  const r=await fetch('api/ajax.php',{method:'POST',body:fd}); const d=await r.json();
  if(d.ok){toast(d.msg);closeModal('mCA');e.target.reset();loadAlumnos();}else Ibbs.error(d.msg);
}
async function editA(id){
  const d=await ajax('alumno_get',{id}); if(!d?.ok){toast(d?.msg,'err');return;}
  const r=d.data;
  document.getElementById('eAId').value=id;
  document.getElementById('eAN').value=r.nombre; document.getElementById('eAA').value=r.apellido;
  document.getElementById('eAC').value=r.cedula; document.getElementById('eAM').value=r.correo;
  document.getElementById('eAT').value=r.telefono||''; document.getElementById('eACi').value=r.ciudad||'';
  document.getElementById('eAAct').value=r.activo;
  openModal('mEA');
}
async function saveEditA(){
  const d=await ajax('alumno_update',{id:document.getElementById('eAId').value,nombre:document.getElementById('eAN').value,apellido:document.getElementById('eAA').value,cedula:document.getElementById('eAC').value,correo:document.getElementById('eAM').value,telefono:document.getElementById('eAT').value,ciudad:document.getElementById('eACi').value,activo:document.getElementById('eAAct').value});
  if(d?.ok){toast(d.msg);closeModal('mEA');loadAlumnos();}else toast(d?.msg||'Err','err');
}
async function verPerfil(id){
  const d=await ajax('alumno_get',{id}); if(!d?.ok){toast(d?.msg,'err');return;}
  const r=d.data; const ini=(r.nombre||'?')[0].toUpperCase();
  const asist=r.asistencias||{}; const tot=Object.values(asist).reduce((a,b)=>a+b,0);
  const notas=r.notas||[];
  const prom=notas.length?(notas.reduce((a,n)=>a+parseFloat(n.calificacion),0)/notas.length).toFixed(1):'—';
  document.getElementById('perfilA').innerHTML=`
    <div class="profile-card">
      <div class="profile-ava">${ini}</div>
      <div class="profile-name">${r.nombre} ${r.apellido}</div>
      <div class="profile-meta">Cédula: ${r.cedula} · ${r.correo}</div>
      ${r.telefono?`<div class="profile-meta">Tel: ${r.telefono}</div>`:''}
      ${r.ciudad?`<div class="profile-meta">📍 ${r.ciudad}</div>`:''}
      <div class="profile-chips" style="margin-top:.8rem;"><span class="profile-chip lime">Alumno</span><span class="profile-chip ${r.activo?'lime':''}">${r.activo?'Activo':'Inactivo'}</span></div>
    </div>
    <div class="stats" style="margin-bottom:1.5rem;">
      <div class="scard c1"><div class="scard-ico"><svg viewBox="0 0 24 24" fill="none" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg></div><div><div class="scard-val">${r.materias.length}</div><div class="scard-key">Materias</div></div></div>
      <div class="scard c3"><div class="scard-ico"><svg viewBox="0 0 24 24" fill="none" stroke-width="2"><polyline points="9 11 12 14 22 4"/></svg></div><div><div class="scard-val">${tot}</div><div class="scard-key">Asistencias</div></div></div>
      <div class="scard c2"><div class="scard-ico"><svg viewBox="0 0 24 24" fill="none" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16"/><polyline points="14 2 14 8 20 8"/></svg></div><div><div class="scard-val">${prom}</div><div class="scard-key">Promedio</div></div></div>
    </div>
    <p style="font-size:.75rem;font-weight:600;text-transform:uppercase;letter-spacing:1px;color:var(--muted);margin-bottom:.6rem;">Materias</p>
    ${r.materias.length?r.materias.map(m=>`<span class="badge b-alumno" style="margin:.2rem;">${m.codigo} · ${m.nombre}</span>`).join(''):'<em style="color:var(--muted);font-size:.82rem;">Sin materias.</em>'}
    <hr class="divider">
    <p style="font-size:.75rem;font-weight:600;text-transform:uppercase;letter-spacing:1px;color:var(--muted);margin-bottom:.6rem;">Asistencias</p>
    <div style="display:flex;gap:.5rem;flex-wrap:wrap;">${Object.entries({presente:'b-presente',ausente:'b-ausente',tardanza:'b-tardanza',justificado:'b-justificado'}).map(([k,c])=>`<span class="badge ${c}">${k}: ${asist[k]||0}</span>`).join('')}</div>`;
  openModal('mPA');
}
async function delA(id,n){
  ibbsConfirm(`¿Eliminar al alumno "${n}"? Esta acción es irreversible.`, async ()=>{
    const d=await ajax('alumno_delete',{id});
    if(d?.ok){toast(d.msg);loadAlumnos();}else Ibbs.error(d?.msg||'Error');
  });
}
function filterTable(t,q){document.querySelectorAll('#'+t+' tbody tr:not(.empty-row)').forEach(r=>r.style.display=r.textContent.toLowerCase().includes(q.toLowerCase())?'':'none');}

function filtrarAlumnos() {
  const qN = (document.getElementById('fAlumNombre')?.value||'').toLowerCase();
  const qC = (document.getElementById('fAlumCedula')?.value||'').toLowerCase();
  document.querySelectorAll('#tblA tbody tr:not(.empty-row)').forEach(tr => {
    const matchN = !qN || tr.textContent.toLowerCase().includes(qN);
    const matchC = !qC || tr.textContent.toLowerCase().includes(qC);
    tr.style.display = (matchN && matchC) ? '' : 'none';
  });
}
</script>
<?php include __DIR__.'/layout/foot.php'; ?>
