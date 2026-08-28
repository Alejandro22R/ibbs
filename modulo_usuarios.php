<?php
$page_title  = 'Usuarios';
$page_sub    = 'Cuentas de acceso al sistema';
$active_link = 'usuarios';
include __DIR__.'/layout/head.php';
// Acceso solo superadmin
if($_rol !== 'superadmin'){
    echo '<script>window.location="index.php";</script>'; exit;
}

?>
<div style="display:flex;justify-content:flex-end;margin-bottom:1.2rem;">
  <button class="btn btn-primary" onclick="openModal('mCU')">
    <svg viewBox="0 0 24 24" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
    Nuevo Usuario
  </button>
</div>

<div class="card">
  <div class="card-head">
    <h3>Usuarios del sistema</h3>
    <div style="display:flex;gap:.5rem;">
      <input type="text" id="fUsuNombre" data-only="letters" placeholder="Buscar por nombre…" oninput="filtrarUsuarios()"
        style="padding:.55rem .9rem;border:1.5px solid var(--border);border-radius:8px;font-size:.84rem;outline:none;background:var(--paper);width:190px;">
      <input type="text" id="fUsuCedula" data-only="cedula" placeholder="Buscar por cédula…" oninput="filtrarUsuarios()"
        style="padding:.55rem .9rem;border:1.5px solid var(--border);border-radius:8px;font-size:.84rem;outline:none;background:var(--paper);width:180px;">
    </div>
  </div>
  <div class="tbl-wrap">
    <table id="tblU">
      <thead>
        <tr>
          <th style="text-align:left;">Usuario</th>
          <th style="text-align:left;">Correo</th>
          <th>Rol</th>
          <th>Estado</th>
          <th>Registrado</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody id="tbodyU">
        <tr class="empty-row"><td colspan="6"><span class="spin"></span></td></tr>
      </tbody>
    </table>
  </div>
</div>

<!-- MODAL CREAR -->
<div class="modal-backdrop" id="mCU">
  <div class="modal" style="max-width:460px;">
    <div class="modal-head"><h3>Nuevo Usuario</h3>
      <button class="modal-close" onclick="closeModal('mCU')"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
    </div>
    <div class="modal-body">
      <form id="fCU" onsubmit="crearUsuario(event)">
        <div class="form-grid" style="margin-bottom:1rem;">
          <div class="field"><label>Nombre de usuario *</label><input name="usuario" data-only="username" placeholder="ej. prof_martinez" autocomplete="off"></div>
          <div class="field"><label>Correo *</label><input type="email" name="correo" placeholder="correo@gmail.com"></div>
          <div class="field"><label>Contraseña * <span style="font-weight:400;color:var(--muted);">(mín. 6 caracteres)</span></label><input type="password" name="password" autocomplete="new-password"></div>
          <div class="field"><label>Rol</label>
            <select name="rol">
              
              <option value="profesor">Profesor</option>
              <option value="admin">Admin</option>
              <?php if($_rol==='superadmin'): ?><option value="superadmin">Superadmin</option><?php endif; ?>
            </select>
          </div>
        </div>
        <div style="display:flex;justify-content:flex-end;gap:.6rem;">
          <button type="button" class="btn btn-secondary" onclick="closeModal('mCU')">Cancelar</button>
          <button type="submit" class="btn btn-primary">Crear usuario</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- MODAL EDITAR / RESET PWD -->
<div class="modal-backdrop" id="mEU">
  <div class="modal" style="max-width:480px;">
    <div class="modal-head"><h3 id="eUTitulo">Editar Usuario</h3>
      <button class="modal-close" onclick="closeModal('mEU')"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="eUID">
      <div class="tabs-nav">
        <button class="tab-btn active" data-tab-group="eU" data-tab="info" onclick="switchTab('eU','info')">Info</button>
        <button class="tab-btn" data-tab-group="eU" data-tab="pwd"  onclick="switchTab('eU','pwd')">Contraseña</button>
      </div>

      <!-- Tab Info -->
      <div class="tab-pane active" data-pane-group="eU" data-pane="info">
        <div class="form-grid" style="margin-bottom:1rem;">
          <div class="field"><label>Usuario</label><input id="eUN" autocomplete="off"></div>
          <div class="field"><label>Correo</label><input id="eUM" type="email"></div>
          <div class="field"><label>Rol</label>
            <select id="eUR">
              
              <option value="profesor">Profesor</option>
              <option value="admin">Admin</option>
              <?php if($_rol==='superadmin'): ?><option value="superadmin">Superadmin</option><?php endif; ?>
            </select>
          </div>
          <div class="field"><label>Estado</label>
            <select id="eUA">
              <option value="1">Activo</option>
              <option value="0">Inactivo</option>
            </select>
          </div>
        </div>
        <div style="display:flex;justify-content:flex-end;gap:.6rem;">
          <button class="btn btn-secondary" onclick="closeModal('mEU')">Cancelar</button>
          <button class="btn btn-primary" onclick="guardarEdicion()">Guardar</button>
        </div>
      </div>

      <!-- Tab Contraseña -->
      <div class="tab-pane" data-pane-group="eU" data-pane="pwd">
        <div class="field" style="margin-bottom:1rem;">
          <label>Nueva contraseña <span style="font-weight:400;color:var(--muted);">(mín. 6 caracteres)</span></label>
          <input type="password" id="eUPwd" placeholder="Nueva contraseña" autocomplete="new-password">
        </div>
        <div class="field" style="margin-bottom:1.2rem;">
          <label>Confirmar contraseña</label>
          <input type="password" id="eUPwd2" placeholder="Repetir contraseña">
        </div>
        <button class="btn btn-primary" onclick="resetPwd()">Actualizar contraseña</button>
      </div>
    </div>
  </div>
</div>

<script>
const IS_SUPER = <?=($_rol==='superadmin'?'true':'false')?>;
const ROL_BADGE = {superadmin:'b-superadmin',admin:'b-admin',profesor:'b-profesor'};
const ROL_LABEL = {superadmin:'Superadmin',admin:'Admin',profesor:'Profesor'};

document.addEventListener('ibbs:ready', () => loadUsuarios());

async function loadUsuarios() {
  const d = await ajax('usuario_list');
  const tb = document.getElementById('tbodyU');
  if (!d?.ok) {
    tb.innerHTML = '<tr class="empty-row"><td colspan="6">' + (d?.msg || 'Error al cargar') + '</td></tr>';
    return;
  }
  if (!d.data.length) {
    tb.innerHTML = '<tr class="empty-row"><td colspan="6">Sin usuarios registrados.</td></tr>';
    return;
  }
  tb.innerHTML = d.data.map(u => {
    const fecha = u.creado_en ? u.creado_en.substring(0,10) : '—';
    const rolBadge = `<span class="badge ${ROL_BADGE[u.rol]||'b-profesor'}">${ROL_LABEL[u.rol]||u.rol}</span>`;
    const actBadge = `<span class="badge ${u.activo=='1'?'b-activo':'b-inactivo'}">${u.activo=='1'?'Activo':'Inactivo'}</span>`;
    return `<tr>
      <td style="text-align:left;">
        <div style="display:flex;align-items:center;gap:.6rem;">
          <div style="width:32px;height:32px;border-radius:50%;background:var(--ink2);color:var(--lime);font-family:'DM Serif Display',serif;font-size:.9rem;display:flex;align-items:center;justify-content:center;flex-shrink:0;">${h(u.usuario[0].toUpperCase())}</div>
          <div><strong style="font-size:.88rem;">${h(u.usuario)}</strong></div>
        </div>
      </td>
      <td style="text-align:left;font-size:.82rem;">${h(u.correo)}</td>
      <td style="text-align:center;">${rolBadge}</td>
      <td style="text-align:center;">${actBadge}</td>
      <td style="text-align:center;font-size:.78rem;color:var(--muted);">${fecha}</td>
      <td class="td-actions">
        <button class="btn btn-sm btn-primary" onclick="editarUsuario(${u.id},'${h(u.usuario)}','${h(u.correo)}','${u.rol}',${u.activo})">Editar</button>
        <button class="btn btn-sm ${u.activo=='1'?'btn-secondary':'btn-success'}" onclick="toggleUsuario(${u.id},this)">${u.activo=='1'?'Desactivar':'Activar'}</button>
        <button class="btn btn-sm btn-danger" onclick="eliminarUsuario(${u.id},'${h(u.usuario)}')" ${IS_SUPER?'':'disabled title="Solo superadmin"'}>Eliminar</button>
      </td>
    </tr>`;
  }).join('');
}

async function crearUsuario(e) {
  e.preventDefault();
  if (!validarForm([
    {name:'usuario',  label:'Usuario',    tipo:'texto',   min:3},
    {name:'correo',   label:'Correo',     tipo:'email'},
    {name:'password', label:'Contraseña', tipo:'password',min:6},
  ])) return;
  const fd = new FormData(e.target); fd.append('action','usuario_create');
  const r = await fetch('php/ajax.php', {method:'POST', body:fd});
  const d = await r.json();
  if (d.ok) { toast(d.msg); closeModal('mCU'); e.target.reset(); loadUsuarios(); }
  else toast(d.msg, 'err');
}

function editarUsuario(id, usr, mail, rol, activo) {
  document.getElementById('eUID').value = id;
  document.getElementById('eUN').value  = usr;
  document.getElementById('eUM').value  = mail;
  document.getElementById('eUR').value  = rol;
  document.getElementById('eUA').value  = activo;
  document.getElementById('eUPwd').value  = '';
  document.getElementById('eUPwd2').value = '';
  document.getElementById('eUTitulo').textContent = 'Editar: ' + usr;
  switchTab('eU','info');
  openModal('mEU');
}

async function guardarEdicion() {
  const d = await ajax('usuario_update', {
    id:      document.getElementById('eUID').value,
    usuario: document.getElementById('eUN').value,
    correo:  document.getElementById('eUM').value,
    rol:     document.getElementById('eUR').value,
    activo:  document.getElementById('eUA').value,
  });
  if (d?.ok) { toast(d.msg); closeModal('mEU'); loadUsuarios(); }
  else toast(d?.msg || 'Error', 'err');
}

async function resetPwd() {
  const p1 = document.getElementById('eUPwd').value;
  const p2 = document.getElementById('eUPwd2').value;
  if (!p1) { toast('Ingresa la contraseña.', 'err'); return; }
  if (p1 !== p2) { toast('Las contraseñas no coinciden.', 'err'); return; }
  const d = await ajax('usuario_reset_pwd', {id: document.getElementById('eUID').value, password: p1});
  if (d?.ok) { toast(d.msg); closeModal('mEU'); }
  else toast(d?.msg || 'Error', 'err');
}

async function toggleUsuario(id, btn) {
  const d = await ajax('usuario_toggle', {id});
  if (d?.ok) { toast(d.msg); loadUsuarios(); }
  else toast(d?.msg || 'Error', 'err');
}

async function eliminarUsuario(id, nombre) {
  ibbsConfirm(`¿Eliminar al usuario "${nombre}"? Esta acción no se puede deshacer.`, async ()=>{

  const d = await ajax('usuario_delete', {id
  });
  if (d?.ok) { toast(d.msg); loadUsuarios(); }
  else toast(d?.msg || 'Error', 'err');
  });
}

function h(s) { const d = document.createElement('div'); d.textContent = String(s ?? ''); return d.innerHTML; }

function filtrarUsuarios() {
  const qN = (document.getElementById('fUsuNombre')?.value||'').toLowerCase();
  const qC = (document.getElementById('fUsuCedula')?.value||'').toLowerCase();
  document.querySelectorAll('#tblU tbody tr:not(.empty-row)').forEach(tr => {
    const txt  = tr.textContent.toLowerCase();
    const celd = tr.querySelector('td:nth-child(1)')?.textContent.toLowerCase()||'';
    const mailcol = tr.querySelector('td:nth-child(2)')?.textContent.toLowerCase()||'';
    const matchN = !qN || txt.includes(qN);
    const matchC = !qC || txt.includes(qC);
    tr.style.display = (matchN && matchC) ? '' : 'none';
  });
}
</script>
<?php include __DIR__.'/layout/foot.php'; ?>
