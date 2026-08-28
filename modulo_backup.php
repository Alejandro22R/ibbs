<?php
$page_title  = 'Respaldo de Base de Datos';
$page_sub    = 'Exportar, importar y gestionar el respaldo del sistema';
$active_link = 'backup';
include __DIR__.'/layout/head.php';
// Acceso admin o superadmin
if(!in_array($_rol,['superadmin','admin'])){
    echo '<script>window.location="index.php";</script>'; exit;
}

?>

<!-- ═══════════════════════════════════════════════
  INFO CARDS
════════════════════════════════════════════════════ -->
<div id="dbInfoCards" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:.8rem;margin-bottom:1.4rem;"></div>

<!-- ═══════════════════════════════════════════════
  LAYOUT PRINCIPAL — 2 columnas
════════════════════════════════════════════════════ -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.1rem;margin-bottom:1.1rem;">

  <!-- EXPORTAR -->
  <div class="card">
    <div class="card-head" style="border-bottom:2px solid var(--lime2);">
      <h3 style="display:flex;align-items:center;gap:.5rem;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        Exportar
      </h3>
      <span style="font-size:.74rem;color:var(--muted);">Descargar copia de seguridad</span>
    </div>
    <div class="card-body">
      <p style="font-size:.84rem;color:var(--muted);line-height:1.7;margin-bottom:1.2rem;">
        Genera un archivo <code style="background:var(--cream);padding:.1rem .35rem;border-radius:4px;font-size:.8rem;">.sql</code> con toda la base de datos. Guárdalo en un lugar seguro.
      </p>
      <div style="display:flex;flex-direction:column;gap:.6rem;">
        <a href="api/backup.php?action=export" class="btn btn-primary" style="text-align:center;">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
          Descargar SQL completo
        </a>

      </div>
    </div>
  </div>

  <!-- IMPORTAR — solo superadmin -->
  <?php if($_rol==='superadmin'): ?>
  <div class="card">
    <div class="card-head" style="border-bottom:2px solid #f59e0b;">
      <h3 style="display:flex;align-items:center;gap:.5rem;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
        Importar / Restaurar
      </h3>
      <span style="font-size:.74rem;color:var(--muted);">Cargar desde archivo SQL</span>
    </div>
    <div class="card-body">
      <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:.75rem 1rem;margin-bottom:1rem;font-size:.81rem;color:#92400e;display:flex;gap:.5rem;align-items:flex-start;">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;margin-top:1px;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        <span><strong>Advertencia:</strong> Restaurar reemplazará todos los datos actuales. Descarga un respaldo primero.</span>
      </div>
      <div class="field" style="margin-bottom:1rem;">
        <label>Archivo .sql</label>
        <input type="file" id="sqlFile" accept=".sql"
          style="padding:.55rem .8rem;border:1.5px solid var(--border);border-radius:8px;font-size:.82rem;background:var(--cream);width:100%;font-family:'Nunito',sans-serif;cursor:pointer;">
      </div>
      <button class="btn btn-secondary" onclick="importarBD()">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
        Restaurar base de datos
      </button>
    </div>
  </div>
  <?php endif; ?>

</div>

<!-- ═══════════════════════════════════════════════
  ZONA DE PELIGRO
════════════════════════════════════════════════════ -->
<?php if(can('all')): ?>
<div class="card" style="border:1.5px solid rgba(220,38,38,.2);margin-bottom:1.1rem;">
  <div class="card-head" style="border-bottom:2px solid #dc2626;">
    <h3 style="color:#dc2626;display:flex;align-items:center;gap:.5rem;">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
      Zona de Peligro
    </h3>
    <span style="font-size:.74rem;color:var(--muted);">Acciones irreversibles — solo Superadmin</span>
  </div>
  <div class="card-body">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.8rem;">
      <div>
        <div style="font-weight:700;font-size:.88rem;margin-bottom:.25rem;">Reiniciar base de datos</div>
        <div style="font-size:.8rem;color:var(--muted);max-width:480px;line-height:1.6;">
          Elimina todos los datos de alumnos, docentes, inscripciones, notas y asistencias. Conserva el usuario administrador.
        </div>
      </div>
      <button class="btn btn-danger" id="btnResetBD" onclick="abrirResetModal()">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
        Reiniciar BD
      </button>
    </div>
  </div>
</div>
<?php endif; ?>

  <div class="tbl-wrap">
    <table>
      <thead>
        <tr>
          <th>Tabla</th>
          <th style="text-align:center;">Registros</th>
          <th>Descripción</th>
        </tr>
      </thead>
      <tbody id="tbTablas">
        <tr class="empty-row"><td colspan="3"><span class="spin"></span></td></tr>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal PIN para reset -->
<div id="resetPinModal" style="display:none;position:fixed;inset:0;z-index:9999;align-items:center;justify-content:center;padding:1rem;">
  <div onclick="document.getElementById('resetPinModal').style.display='none'" style="position:absolute;inset:0;background:rgba(0,0,0,.6);backdrop-filter:blur(6px);"></div>
  <div class="modal" style="position:relative;z-index:1;max-width:380px;">
    <div class="modal-head" style="border-bottom:2px solid #dc2626;">
      <h3 style="color:#dc2626;">Confirmar reinicio</h3>
      <button class="modal-close" onclick="document.getElementById('resetPinModal').style.display='none'">&#10005;</button>
    </div>
    <div class="modal-body">
      <p style="font-size:.84rem;color:var(--muted);margin-bottom:1rem;line-height:1.6;">
        Esta acción es <strong>irreversible</strong>. Ingresa tu contraseña de administrador para confirmar.
      </p>
      <div class="field">
        <label>Contraseña de administrador</label>
        <input id="resetPinInput" type="password" placeholder="Tu contraseña de acceso"
          style="width:100%;padding:.75rem 1rem;border:1.5px solid var(--border);border-radius:10px;font-family:'Nunito',sans-serif;font-size:.9rem;background:#fff;outline:none;">
      </div>
      <div id="resetPinErr" style="color:#dc2626;font-size:.78rem;min-height:1rem;"></div>
    </div>
    <div class="modal-foot">
      <button class="btn btn-secondary" onclick="document.getElementById('resetPinModal').style.display='none'">Cancelar</button>
      <button class="btn btn-danger" onclick="ejecutarReset()">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
        Reiniciar base de datos
      </button>
    </div>
  </div>
</div>

<script>
document.addEventListener('ibbs:ready', async () => {
  const d = await ajax('dashboard_stats');
  if (!d?.ok) return;
  const dd = d.data;
  const reg = (parseInt(dd.alumnos)||0) + (parseInt(dd.docentes)||0) +
              (parseInt(dd.materias)||0) + (parseInt(dd.asist)||0);

  document.getElementById('dbInfoCards').innerHTML = [
    {l:'Tablas',          v: Object.keys(tablaDescriptions).length, c:'var(--ink)'},
    {l:'Alumnos',         v: dd.alumnos,  c:'#1d4ed8'},
    {l:'Docentes',        v: dd.docentes, c:'#7c3aed'},
    {l:'Materias',        v: dd.materias, c:'#0f766e'},
    {l:'Asistencias',     v: dd.asist,    c:'#15803d'},
    {l:'Registros aprox.',v: reg,         c:'var(--muted)'},
  ].map(i=>`
    <div style="background:var(--paper);border:1px solid var(--border);border-radius:10px;padding:.85rem 1rem;">
      <div style="font-size:1.5rem;font-weight:800;color:${i.c};line-height:1;">${i.v}</div>
      <div style="font-size:.64rem;text-transform:uppercase;letter-spacing:.8px;color:var(--muted);margin-top:3px;">${i.l}</div>
    </div>`).join('');
});

async function importarBD() {
  const file = document.getElementById('sqlFile').files[0];
  if (!file) { toast('Selecciona un archivo .sql primero.','err'); return; }
  const ok = await new Promise(res => {
    ibbsConfirm('¿Importar base de datos? Esto reemplazará TODOS los datos actuales.', () => res(true));
    setTimeout(()=>res(false), 60000);
  });
  if (!ok) return;
  const fd = new FormData();
  fd.append('sqlfile', file);
  fd.append('action', 'import');
  toast('Restaurando… por favor espera.');
  try {
    const r = await fetch('api/backup.php',{method:'POST',body:fd});
    const d = await r.json();
    if (d.ok) toast(d.msg);
    else toast(d.msg||'Error al importar.','err');
  } catch(e) { toast('Error de conexión.','err'); }
}

function abrirResetModal() {
  document.getElementById('resetPinInput').value = '';
  document.getElementById('resetPinErr').textContent = '';
  document.getElementById('resetPinModal').style.display = 'flex';
  setTimeout(()=>document.getElementById('resetPinInput').focus(), 80);
}

async function ejecutarReset() {
  const pin = document.getElementById('resetPinInput').value;
  const errEl = document.getElementById('resetPinErr');
  errEl.textContent = '';
  if (!pin) { errEl.textContent = 'Ingresa tu contraseña.'; return; }
  const vd = await ajax('verify_admin_pin', {pin});
  if (!vd?.ok) {
    errEl.textContent = vd?.msg || 'Contraseña incorrecta.';
    document.getElementById('resetPinInput').value = '';
    document.getElementById('resetPinInput').focus();
    return;
  }
  document.getElementById('resetPinModal').style.display = 'none';
  const btn = document.getElementById('btnResetBD');
  if (btn) btn.disabled = true;
  const d = await ajax('reset_bd');
  if (d?.ok) toast(d.msg);
  else toast(d?.msg||'Error', 'err');
  if (btn) btn.disabled = false;
}

document.addEventListener('keydown', e => {
  if (e.key==='Enter' && document.getElementById('resetPinModal').style.display==='flex') ejecutarReset();
});
</script>
<?php include __DIR__.'/layout/foot.php'; ?>
