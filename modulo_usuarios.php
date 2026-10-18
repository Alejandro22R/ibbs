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
<div style="display:flex;justify-content:flex-end;gap:.6rem;margin-bottom:1.2rem;">
  <button class="btn btn-secondary" onclick="IbbsTour.replay(window.IBBS_TOUR_USUARIOS)" title="Ver el tutorial de esta página otra vez">
    <i class="bx bx-play-circle"></i> Tutorial
  </button>
  <button class="btn btn-primary" onclick="openModal('mCU')" data-tour="usu-nuevo">
    <svg viewBox="0 0 24 24" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
    Nuevo Usuario
  </button>
</div>

<div class="card" data-tour="usu-tabla">
  <div class="card-head">
    <h3>Usuarios del sistema</h3>
    <div style="display:flex;gap:.5rem;">
      <input type="text" id="fUsuNombre" data-only="letters" placeholder="Buscar por nombre…" oninput="filtrarUsuarios()"
        style="padding:.55rem .9rem;border:1.5px solid var(--border);border-radius:8px;font-size:.84rem;outline:none;background:var(--paper);width:190px;">
      <input type="text" id="fUsuCedula" data-only="cedula" placeholder="Buscar por ID…" oninput="filtrarUsuarios()"
        style="padding:.55rem .9rem;border:1.5px solid var(--border);border-radius:8px;font-size:.84rem;outline:none;background:var(--paper);width:180px;">
      <button type="button" class="btn btn-secondary btn-sm" onclick="exportarUsuariosExcel()" title="Descargar esta lista como Excel/CSV">
        <i class="bx bx-download"></i> Exportar
      </button>
    </div>
  </div>
  <div id="barraLoteUsu" style="display:none;align-items:center;gap:.5rem;flex-wrap:wrap;background:var(--cream);border-bottom:1px solid var(--border);padding:.6rem 1rem;">
    <span id="loteUsuCount" style="font-size:.82rem;font-weight:700;color:var(--ink2);">0 seleccionados</span>
    <button class="btn btn-sm btn-success" onclick="loteAccionUsuarios('activar')">✓ Activar</button>
    <button class="btn btn-sm btn-secondary" onclick="loteAccionUsuarios('desactivar')">⏸ Desactivar</button>
    <button class="btn btn-sm btn-danger" onclick="loteAccionUsuarios('eliminar')">🗑 Eliminar</button>
    <button class="btn btn-sm btn-secondary" onclick="limpiarSeleccionUsuarios()" style="margin-left:auto;">Cancelar selección</button>
  </div>
  <div class="tbl-wrap">
    <table id="tblU" class="table-stack">
      <thead>
        <tr>
          <th style="width:34px;text-align:center;"><input type="checkbox" id="chkAllUsu" onchange="toggleAllUsuarios(this)" title="Seleccionar todo"></th>
          <th style="text-align:left;">Usuario</th>
          <th style="text-align:left;">Correo</th>
          <th>Rol</th>
          <th>Estado</th>
          <th>Registrado</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody id="tbodyU">
        <tr class="empty-row"><td colspan="7"><span class="spin"></span></td></tr>
      </tbody>
    </table>
  </div>
  <div id="pagerU"></div>
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
          <div class="field"><label>ID <span class="field-ast-help"><button type="button" onclick="toggleIdHint(this)" aria-label="¿Qué es el ID?">*</button><span class="field-ast-bubble">Identificación personal: cédula, DNI, pasaporte u otro documento oficial válido en su país.</span></span></label><input name="cedula" data-only="cedula" placeholder="V-12345678"></div>
          <div class="field"><label>Correo *</label><input type="email" name="correo" placeholder="correo@gmail.com"></div>
          <div class="field"><label>Contraseña * <span style="font-weight:400;color:var(--muted);">(mín. 8 car., mayús., minús. y número o símbolo)</span></label><input type="password" name="password" autocomplete="new-password" oninput="pwdChecklist('nUPwdChecklist', this.value)">
            <div style="margin-top:.4rem;" id="nUPwdChecklist"></div>
          </div>
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
          <label>Nueva contraseña <span style="font-weight:400;color:var(--muted);">(mín. 8 car., mayús., minús. y número o símbolo)</span></label>
          <input type="password" id="eUPwd" placeholder="Nueva contraseña" autocomplete="new-password" oninput="pwdChecklist('eUPwdChecklist', this.value)">
          <div style="margin-top:.4rem;" id="eUPwdChecklist"></div>
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

// Paginador (20 filas por página) — ver assets/ibbs-paginate.js. Se
// crea recién la primera vez que se necesita, no acá arriba, porque
// IbbsPaginate se carga en layout/foot.php, DESPUÉS de este <script>.
let usuPager = null;
let _usuariosAll = [];

function filaUsuario(u){
  const fecha = u.creado_en ? u.creado_en.substring(0,10) : '—';
  const rolBadge = `<span class="badge ${ROL_BADGE[u.rol]||'b-profesor'}">${ROL_LABEL[u.rol]||u.rol}</span>`;
  const actBadge = `<span class="badge ${u.activo=='1'?'b-activo':'b-inactivo'}">${u.activo=='1'?'Activo':'Inactivo'}</span>`;
  return `<tr>
    <td style="text-align:center;"><input type="checkbox" class="chkUsu" value="${u.id}" onchange="actualizarBarraLoteUsuarios()"></td>
    <td data-label="Usuario" style="text-align:left;">
      <div style="display:flex;align-items:center;gap:.6rem;">
        <div style="width:32px;height:32px;border-radius:50%;background:var(--ink2);color:var(--lime);font-family:'DM Serif Display',serif;font-size:.9rem;display:flex;align-items:center;justify-content:center;flex-shrink:0;">${h(u.usuario[0].toUpperCase())}</div>
        <div><strong style="font-size:.88rem;">${h(u.usuario)}</strong></div>
      </div>
    </td>
    <td data-label="Correo" style="text-align:left;font-size:.82rem;">${h(u.correo)}</td>
    <td data-label="Rol" style="text-align:center;">${rolBadge}</td>
    <td data-label="Estado" style="text-align:center;">${actBadge}</td>
    <td data-label="Registrado" style="text-align:center;font-size:.78rem;color:var(--muted);">${fecha}</td>
    <td data-label="Acciones" class="td-actions">
      <button class="btn btn-icon btn-sm btn-primary" onclick="editarUsuario(${u.id},'${hAttr(u.usuario)}','${hAttr(u.correo)}','${u.rol}',${u.activo})" title="Editar"><i class="bx bx-edit"></i></button>
      <button class="btn btn-icon btn-sm ${u.activo=='1'?'btn-secondary':'btn-success'}" onclick="toggleUsuario(${u.id},this)" title="${u.activo=='1'?'Desactivar':'Activar'}"><i class="bx ${u.activo=='1'?'bx-pause':'bx-play'}"></i></button>
      <button class="btn btn-icon btn-sm btn-danger" onclick="eliminarUsuario(${u.id},'${hAttr(u.usuario)}')" ${IS_SUPER?'':'disabled title="Solo superadmin"'} title="Eliminar"><i class="bx bx-trash"></i></button>
    </td>
  </tr>`;
}

async function loadUsuarios() {
  const d = await ajax('usuario_list');
  const tb = document.getElementById('tbodyU');
  limpiarSeleccionUsuarios();
  if (!d?.ok) {
    tb.innerHTML = '<tr class="empty-row"><td colspan="7">' + (d?.msg || 'Error al cargar') + '</td></tr>';
    return;
  }
  _usuariosAll = d.data;
  if (!usuPager) {
    usuPager = IbbsPaginate.create('usuarios', { perPage: 20 });
    usuPager.mount(tb, document.getElementById('pagerU'));
    usuPager.configure({ rowHtml: filaUsuario, empty: '<tr class="empty-row"><td colspan="7">Sin usuarios registrados.</td></tr>' });
  }
  filtrarUsuarios();
}

// IbbsExport.table() lee las filas que están en el DOM — con el
// paginador eso ya no es "todos los usuarios", solo los 20 de la
// página visible. Se renderiza el listado completo un instante, se
// exporta, y se vuelve a pintar la página en la que estaba.
function exportarUsuariosExcel(){
  if (!_usuariosAll.length) { IbbsExport.table('#tblU','usuarios-ibbs'); return; }
  document.getElementById('tbodyU').innerHTML = _usuariosAll.map(filaUsuario).join('');
  IbbsExport.table('#tblU','usuarios-ibbs');
  if (usuPager) usuPager.refresh();
}

function toggleAllUsuarios(cb){
  document.querySelectorAll('.chkUsu').forEach(c => c.checked = cb.checked);
  actualizarBarraLoteUsuarios();
}

function actualizarBarraLoteUsuarios(){
  const n = document.querySelectorAll('.chkUsu:checked').length;
  const total = document.querySelectorAll('.chkUsu').length;
  const chkAll = document.getElementById('chkAllUsu');
  chkAll.checked = total>0 && n===total;
  chkAll.indeterminate = n>0 && n<total;
  document.getElementById('barraLoteUsu').style.display = n ? 'flex' : 'none';
  if(n) document.getElementById('loteUsuCount').textContent = n + (n===1?' seleccionado':' seleccionados');
}

function limpiarSeleccionUsuarios(){
  document.querySelectorAll('.chkUsu').forEach(c => c.checked = false);
  const chkAll = document.getElementById('chkAllUsu');
  if(chkAll){ chkAll.checked = false; chkAll.indeterminate = false; }
  const barra = document.getElementById('barraLoteUsu');
  if(barra) barra.style.display = 'none';
}

const LOTE_USU_TEXTOS = {
  activar:    {title:'¿Activar cuentas?', text:'Se activarán las cuentas seleccionadas.', confirm:'Sí, activar', danger:false},
  desactivar: {title:'¿Desactivar cuentas?', text:'Las personas seleccionadas no van a poder iniciar sesión.', confirm:'Sí, desactivar', danger:false},
  eliminar:   {title:'¿Eliminar cuentas?', text:'Esta acción no se puede deshacer. Tu propia cuenta nunca se elimina así.', confirm:'Sí, eliminar', danger:true},
};

async function loteAccionUsuarios(sub){
  const ids = Array.from(document.querySelectorAll('.chkUsu:checked')).map(c => c.value);
  if(!ids.length) return;
  const t = LOTE_USU_TEXTOS[sub];
  const rr = await Ibbs.confirm({title:t.title, text:t.text, confirm:t.confirm, danger:t.danger});
  if(!rr.isConfirmed) return;
  const d = await ajax('usuario_lote', {sub_accion: sub, ids: ids.join(',')});
  if(d?.ok){ toast(d.msg); loadUsuarios(); } else toast(d?.msg||'Error', 'err');
}

async function crearUsuario(e) {
  e.preventDefault();
  if (!validarForm([
    {name:'usuario',  label:'Usuario',    tipo:'texto',   min:3},
    {name:'cedula',   label:'ID',         tipo:'texto',   min:5},
    {name:'correo',   label:'Correo',     tipo:'email'},
    {name:'password', label:'Contraseña', tipo:'password',min:6},
  ])) return;
  const fd = new FormData(e.target); fd.append('action','usuario_create');
  const _csrf = document.querySelector('meta[name="csrf-token"]'); fd.append('csrf_token', _csrf?_csrf.content:'');
  const btn = e.target.querySelector('button[type="submit"]'); const btnTxt = btn.textContent;
  btn.disabled = true; btn.textContent = 'Guardando…';
  try {
    const r = await fetch('api/ajax.php', {method:'POST', body:fd});
    const d = await r.json();
    if (d.ok) { toast(d.msg); closeModal('mCU'); e.target.reset(); loadUsuarios(); }
    else toast(d.msg, 'err');
  } catch(err) { toast('Error de conexión.', 'err'); }
  btn.disabled = false; btn.textContent = btnTxt;
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
function hAttr(s) { return h(s).replace(/\\/g,'\\\\').replace(/'/g,"\\'").replace(/"/g,'&quot;'); }

function filtrarUsuarios() {
  if (!usuPager) return;
  const qN = (document.getElementById('fUsuNombre')?.value||'').toLowerCase();
  const qC = (document.getElementById('fUsuCedula')?.value||'').toLowerCase();
  const filtrados = _usuariosAll.filter(u => {
    const txt = `${u.usuario||''} ${u.correo||''} ${u.rol||''}`.toLowerCase();
    return (!qN || txt.includes(qN)) && (!qC || txt.includes(qC));
  });
  usuPager.setData(filtrados);
}

// ── Tutorial guiado de esta página ──────────────────────────────
window.IBBS_TOUR_USUARIOS = {
  storageKey: 'ibbs_tour_usuarios_v1',
  steps: [
    { selector: '[data-tour="usu-nuevo"]', title: 'Crear cuentas de acceso', text: 'Acá creas las cuentas con las que alumnos, docentes y otros administradores inician sesión — usuario, correo, contraseña y rol.' },
    { selector: '[data-tour="usu-tabla"]', title: 'Gestionar usuarios', text: 'Puedes activar, desactivar, editar o eliminar cada cuenta. Marcando varias (o todas con la casilla del encabezado) puedes activar, desactivar o eliminar en lote.' },
  ],
  auto: true,
};
document.addEventListener('ibbs:ready', () => IbbsTour.start(window.IBBS_TOUR_USUARIOS));
</script>
<?php include __DIR__.'/layout/foot.php'; ?>
