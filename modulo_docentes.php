<?php
$page_title = 'Docentes';
$page_sub   = 'Registro y gestión del personal docente';
$active_link = 'docentes';
include __DIR__.'/layout/head.php';

// Acceso admin o superadmin
if(!in_array($_rol,['superadmin','admin'])){
    echo '<script>window.location="index.php";</script>'; exit;
}

// Obtener materias activas para el modal de asignación
$con = db();
$todas_materias = [];
$res_mat = mysqli_query($con, "SELECT id, nombre FROM materias WHERE activo = 1");
if($res_mat) while($row = mysqli_fetch_assoc($res_mat)) $todas_materias[] = $row;
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.2rem;flex-wrap:wrap;gap:.6rem;">
  <div style="display:flex;gap:.6rem;flex-wrap:wrap;">
    <a href="api/export_plantilla.php?tipo=docentes" target="_blank" class="btn btn-secondary" style="display:flex;align-items:center;gap:.4rem;font-size:.82rem;">&#128424; Exportar PDF</a>
    <button class="btn btn-secondary" onclick="IbbsExport.table('#tblD','docentes-ibbs')" title="Descargar esta lista como Excel/CSV">
      <i class="bx bx-download"></i> Excel
    </button>
    <button class="btn btn-secondary" onclick="IbbsTour.replay(window.IBBS_TOUR_DOCENTES)" title="Ver el tutorial de esta página otra vez">
      <i class="bx bx-play-circle"></i> Tutorial
    </button>
  </div>
  <button class="btn btn-primary" onclick="openModal('mCD')" data-tour="doc-nuevo">
    <svg viewBox="0 0 24 24" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
    Nuevo Docente
  </button>
</div>

<div class="card" data-tour="doc-tabla">
  <div class="card-head">
    <h3>Historial de Docentes</h3>
    <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
      <input type="text" id="fDocNombre" data-only="letters" placeholder="Nombre o apellido…" oninput="filtrarDocentes()"
        style="padding:.55rem .9rem;border:1.5px solid var(--border);border-radius:8px;font-size:.84rem;outline:none;background:var(--paper);width:180px;">
      <input type="text" id="fDocCedula" data-only="cedula" placeholder="Cédula…" oninput="filtrarDocentes()"
        style="padding:.55rem .9rem;border:1.5px solid var(--border);border-radius:8px;font-size:.84rem;outline:none;background:var(--paper);width:140px;">
    </div>
  </div>
  <div id="barraLoteDoc" style="display:none;align-items:center;gap:.5rem;flex-wrap:wrap;background:var(--cream);border-bottom:1px solid var(--border);padding:.6rem 1rem;">
    <span id="loteDocCount" style="font-size:.82rem;font-weight:700;color:var(--ink2);">0 seleccionados</span>
    <button class="btn btn-sm btn-danger" onclick="eliminarDocentesSeleccionados()">🗑 Eliminar seleccionados</button>
    <button class="btn btn-sm btn-secondary" onclick="limpiarSeleccionDocentes()" style="margin-left:auto;">Cancelar selección</button>
  </div>
  <div class="tbl-wrap">
    <table id="tblD">
      <thead><tr>
        <th style="width:34px;text-align:center;"><input type="checkbox" id="chkAllDoc" onchange="toggleAllDocentes(this)" title="Seleccionar todo"></th>
        <th>Cédula</th><th>Nombre</th><th>Correo</th><th>Ciudad</th><th>Especialidad</th><th>Materias</th><th>Estado</th><th>Acciones</th></tr></thead>
      <tbody id="tbodyD"><tr class="empty-row"><td colspan="9"><span class="spin"></span></td></tr></tbody>
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
        <hr class="divider">
        <p style="font-size:.75rem;font-weight:600;text-transform:uppercase;letter-spacing:1px;color:var(--muted);margin-bottom:.8rem;">
          Acceso al sistema (foro, aula virtual, tareas…)
        </p>
        <div class="form-grid" style="margin-bottom:1rem;">
          <div class="field"><label>Usuario de acceso *</label><input name="usuario_login" data-only="username" placeholder="ej. crodriguez" autocomplete="off"></div>
          <div class="field">
            <label>Contraseña inicial *</label>
            <input type="text" name="password_inicial" id="dNewPwd" placeholder="mín. 8 car., mayús., minús., nº" autocomplete="off">
          </div>
        </div>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
          <button type="button" class="btn btn-secondary btn-sm" onclick="generarPasswordDocente()">🎲 Generar contraseña</button>
          <span style="font-size:.72rem;color:var(--muted);">Pasásela al docente — él puede cambiarla después desde su perfil.</span>
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

<!-- MODAL ASIGNAR MATERIA -->
<div class="modal-backdrop" id="mAMD">
  <div class="modal">
    <div class="modal-head"><h3>Asignar Materia a Docente</h3>
      <button class="modal-close" onclick="closeModal('mAMD')"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
    </div>
    <div class="modal-body">
      <form id="fAMD" onsubmit="asignarMateriaSubmit(event)">
        <input type="hidden" id="amd_docente_id" name="docente_id">
        <div class="form-grid" style="margin-bottom:1rem;">
          <div class="field" style="grid-column: span 2;">
            <label>Docente Seleccionado</label>
            <input type="text" id="amd_docente_nombre" readonly disabled style="background:var(--cream); cursor:not-allowed; border: 1.5px solid var(--border);">
          </div>
          <div class="field" style="grid-column: span 2;">
            <label>Materia a Vincular *</label>
            <select name="materia_id" id="amd_materia_id" required style="width: 100%; padding: .65rem; border: 1.5px solid var(--border); border-radius: 8px; font-size: .9rem; background: var(--paper); outline: none;">
              <option value="">-- Elija una materia --</option>
              <?php foreach($todas_materias as $mat): ?>
                <option value="<?= $mat['id'] ?>"><?= htmlspecialchars($mat['nombre']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div style="display:flex;justify-content:flex-end;gap:.6rem;">
          <button type="button" class="btn btn-secondary" onclick="closeModal('mAMD')">Cancelar</button>
          <button type="submit" class="btn btn-primary">Vincular Materia</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.addEventListener('ibbs:ready', () => loadDocentes());

async function loadDocentes(){
  console.log('[IBBS] Calling docente_list...'); 
  const d = await ajax('docente_list'); 
  console.log('[IBBS] docente_list response:', d); 
  
  limpiarSeleccionDocentes();
  if(!d?.ok){
      document.getElementById('tbodyD').innerHTML='<tr class="empty-row"><td colspan="9">'+( d?.msg||'Error al conectar')+'</td></tr>';
      return;
  }

  const tb = document.getElementById('tbodyD');
  if(!d.data.length){
      tb.innerHTML='<tr class="empty-row"><td colspan="9">Sin docentes.</td></tr>';
      return;
  }

  tb.innerHTML = d.data.map(r => `<tr>
    <td style="text-align:center;"><input type="checkbox" class="chkDoc" value="${r.id}" onchange="actualizarBarraLoteDocentes()"></td>
    <td><strong>${r.cedula}</strong></td>
    <td>${r.apellido}, ${r.nombre}</td>
    <td style="font-size:.82rem;">${r.correo}</td>
    <td style="font-size:.82rem;">${r.ciudad||'—'}</td>
    <td style="font-size:.82rem;color:var(--muted);">${r.especialidad||'—'}</td>
    <td><span class="badge b-profesor">${r.nm}</span></td>
    <td><span class="badge ${r.activo=='1'?'b-activo':'b-inactivo'}">${r.activo=='1'?'Activo':'Inactivo'}</span></td>
    <td class="td-actions">
      <button class="btn btn-sm btn-secondary" onclick="abrirModalAsignar(${r.id}, '${(r.nombre+' '+r.apellido).replace(/'/g,"\\'")}')" style="background:var(--ink);color:var(--lime);">Asignar</button>
      <button class="btn btn-sm btn-secondary" onclick="verPerfil(${r.id})">Perfil</button>
      <button class="btn btn-sm btn-primary" onclick="editDoc(${r.id})">Editar</button>
      <a class="btn btn-sm btn-secondary" href="api/export_constancia.php?tipo=trabajo&docente_id=${r.id}" target="_blank" title="Constancia de Trabajo">📄 Trabajo</a>
      <button class="btn btn-sm btn-danger" onclick="delDoc(${r.id},'${(r.nombre+' '+r.apellido).replace(/'/g,"\\'")}')">Eliminar</button>
    </td></tr>`).join('');
}

function generarPasswordDocente(){
  const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789!@#$';
  let pwd = '';
  for(let i=0;i<10;i++) pwd += chars[Math.floor(Math.random()*chars.length)];
  document.getElementById('dNewPwd').value = pwd;
  document.getElementById('dNewPwd').type = 'text';
}

async function createDoc(e){
  e.preventDefault();
  if(!validarForm([
    {name:'nombre',   label:'Nombre',   tipo:'texto', min:2},
    {name:'apellido', label:'Apellido', tipo:'texto', min:2},
    {name:'cedula',   label:'Cédula',   tipo:'cedula'},
    {name:'correo',   label:'Correo',   tipo:'email'},
  ])) return;
  const usuarioLogin = document.querySelector('#fCD [name="usuario_login"]').value.trim();
  const pwdInicial = document.getElementById('dNewPwd').value;
  if(!usuarioLogin){ Ibbs.error('Ponle un usuario de acceso al docente.'); return; }
  if(!pwdInicial){ Ibbs.error('Escribí una contraseña inicial o generá una.'); return; }

  const fd = new FormData(e.target);
  fd.append('action','docente_create');
  const _csrf = document.querySelector('meta[name="csrf-token"]');
  fd.append('csrf_token', _csrf ? _csrf.content : '');
  const r = await fetch('api/ajax.php',{method:'POST',body:fd});
  const d = await r.json();

  if(d.ok){
      closeModal('mCD');
      const nombreCompleto = e.target.querySelector('[name="nombre"]').value+' '+e.target.querySelector('[name="apellido"]').value;
      await Ibbs.confirm({
        title:'Docente creado',
        text:`Pasale estos datos a <b>${h(nombreCompleto)}</b> para que pueda ingresar:<br><br>
              Usuario: <code>${h(usuarioLogin)}</code><br>
              Contraseña: <code>${h(pwdInicial)}</code><br><br>
              Puede cambiarla luego desde su perfil.`,
        confirm:'Listo',
      });
      e.target.reset();
      loadDocentes();
  } else {
      Ibbs.error(d.msg);
  }
}

function h(s){ const d=document.createElement('div'); d.textContent=String(s??''); return d.innerHTML; }

async function editDoc(id){
  const d = await ajax('docente_get',{id}); 
  if(!d?.ok){toast(d?.msg||'Err','err');return;}
  const r = d.data;
  document.getElementById('eDId').value=id;
  document.getElementById('eDN').value=r.nombre; 
  document.getElementById('eDA').value=r.apellido;
  document.getElementById('eDC').value=r.cedula; 
  document.getElementById('eDM').value=r.correo;
  document.getElementById('eDT').value=r.telefono||''; 
  document.getElementById('eDE').value=r.especialidad||'';
  document.getElementById('eDCi').value=r.ciudad||''; 
  document.getElementById('eDAct').value=r.activo;
  openModal('mED');
}

async function saveEdit(){
  const d = await ajax('docente_update',{
      id:document.getElementById('eDId').value,
      nombre:document.getElementById('eDN').value,
      apellido:document.getElementById('eDA').value,
      cedula:document.getElementById('eDC').value,
      correo:document.getElementById('eDM').value,
      telefono:document.getElementById('eDT').value,
      especialidad:document.getElementById('eDE').value,
      ciudad:document.getElementById('eDCi').value,
      activo:document.getElementById('eDAct').value
  });
  if(d?.ok){
      toast(d.msg);
      closeModal('mED');
      loadDocentes();
  } else {
      toast(d?.msg||'Err','err');
  }
}

async function verPerfil(id){
  const d = await ajax('docente_get',{id}); 
  if(!d?.ok){toast(d?.msg,'err');return;}
  const r = d.data; 
  const ini = (r.nombre||'?')[0].toUpperCase();
  const asist = r.asistencias||{}; 
  const tot = Object.values(asist).reduce((a,b)=>a+b,0);
  
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

function filterTable(t,q){
    document.querySelectorAll('#'+t+' tbody tr:not(.empty-row)').forEach(r=>r.style.display=r.textContent.toLowerCase().includes(q.toLowerCase())?'':'none');
}

function filtrarDocentes() {
  const qN = (document.getElementById('fDocNombre')?.value||'').toLowerCase();
  const qC = (document.getElementById('fDocCedula')?.value||'').toLowerCase();
  document.querySelectorAll('#tblD tbody tr:not(.empty-row)').forEach(tr => {
    const matchN = !qN || tr.textContent.toLowerCase().includes(qN);
    const matchC = !qC || tr.textContent.toLowerCase().includes(qC);
    tr.style.display = (matchN && matchC) ? '' : 'none';
  });
}

// --- LÓGICA: ASIGNAR MATERIAS ---
function abrirModalAsignar(id, nombre) {
  document.getElementById('amd_docente_id').value = id;
  document.getElementById('amd_docente_nombre').value = nombre;
  document.getElementById('fAMD').reset();
  openModal('mAMD');
}

function toggleAllDocentes(cb){
  document.querySelectorAll('.chkDoc').forEach(c => c.checked = cb.checked);
  actualizarBarraLoteDocentes();
}
function actualizarBarraLoteDocentes(){
  const n = document.querySelectorAll('.chkDoc:checked').length;
  const total = document.querySelectorAll('.chkDoc').length;
  const chkAll = document.getElementById('chkAllDoc');
  chkAll.checked = total>0 && n===total;
  chkAll.indeterminate = n>0 && n<total;
  document.getElementById('barraLoteDoc').style.display = n ? 'flex' : 'none';
  if(n) document.getElementById('loteDocCount').textContent = n + (n===1?' seleccionado':' seleccionados');
}
function limpiarSeleccionDocentes(){
  document.querySelectorAll('.chkDoc').forEach(c => c.checked = false);
  const chkAll = document.getElementById('chkAllDoc');
  if(chkAll){ chkAll.checked = false; chkAll.indeterminate = false; }
  const barra = document.getElementById('barraLoteDoc');
  if(barra) barra.style.display = 'none';
}
async function eliminarDocentesSeleccionados(){
  const ids = Array.from(document.querySelectorAll('.chkDoc:checked')).map(c => c.value);
  if(!ids.length) return;
  const rr = await Ibbs.confirm({title:'¿Eliminar docentes?', text:`Se eliminarán ${ids.length} docente(s). Esta acción es irreversible.`, confirm:'Sí, eliminar', danger:true});
  if(!rr.isConfirmed) return;
  const d = await ajax('docente_eliminar_lote', {ids: ids.join(',')});
  if(d?.ok){ toast(d.msg); loadDocentes(); } else toast(d?.msg||'Error', 'err');
}

// ── Tutorial guiado de esta página ──────────────────────────────
window.IBBS_TOUR_DOCENTES = {
  storageKey: 'ibbs_tour_docentes_v1',
  steps: [
    { selector: '[data-tour="doc-nuevo"]', title: 'Registrar un docente', text: 'Carga el docente y le creás de una su cuenta de acceso (foro, aula virtual, tareas) con una contraseña inicial.' },
    { selector: '[data-tour="doc-tabla"]', title: 'Gestionar docentes', text: 'Asigná materias, mirá el perfil con sus materias y asistencias, generá la Constancia de Trabajo o eliminá. Marcando varios (o todos con la casilla del encabezado) podés eliminar en lote.' },
  ],
  auto: true,
};
document.addEventListener('ibbs:ready', () => IbbsTour.start(window.IBBS_TOUR_DOCENTES));

async function asignarMateriaSubmit(e) {
  e.preventDefault();
  const form = e.target;
  const btn = form.querySelector('button[type="submit"]');
  const btnText = btn.innerText;
  
  btn.disabled = true;
  btn.innerText = 'Vinculando...';
  
  try {
    const formData = new FormData(form);
    const _csrf = document.querySelector('meta[name="csrf-token"]');
    formData.append('csrf_token', _csrf ? _csrf.content : '');
    const res = await fetch('asignar_materia.php', { method: 'POST', body: formData });
    const data = await res.json();
    
    if (data.ok) {
      toast('Materia asignada correctamente.');
      closeModal('mAMD');
      loadDocentes(); // Recargamos para actualizar el contador de materias en la tabla
    } else {
      Ibbs.error(data.msg || 'Error al asignar materia');
    }
  } catch (error) {
    Ibbs.error('Error de conexión con el servidor.');
  } finally {
    btn.disabled = false;
    btn.innerText = btnText;
  }
}
</script>

<?php include __DIR__.'/layout/foot.php'; ?>