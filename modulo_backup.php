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
  EXPLICACIÓN EN LENGUAJE SENCILLO
════════════════════════════════════════════════════ -->
<div class="card" style="margin-bottom:1.2rem;background:linear-gradient(135deg,var(--lime2) 0%,#15803d 100%);border:none;">
  <div class="card-body" style="padding:1.2rem 1.4rem;display:flex;align-items:center;gap:1rem;flex-wrap:wrap;">
    <div style="width:46px;height:46px;border-radius:50%;background:rgba(255,255,255,.18);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
      <i class="bx bx-shield-quarter" style="font-size:1.5rem;color:#fff;"></i>
    </div>
    <div style="flex:1;min-width:240px;">
      <div style="font-weight:800;color:#fff;font-size:.95rem;">¿Qué es un "respaldo"?</div>
      <div style="font-size:.82rem;color:rgba(255,255,255,.92);line-height:1.5;margin-top:.15rem;">
        Es una copia de <strong>todos</strong> los datos del sistema (alumnos, notas, materias, etc.) en un solo archivo. Si algo sale mal en la base de datos, ese archivo permite recuperar todo tal como estaba. <strong>Recomendación:</strong> descargá uno cada vez que hagas un cambio importante, y guardalo en un lugar aparte (correo, USB, Google Drive).
      </div>
    </div>
    <button class="btn" data-tour-start style="background:#fff;color:#15803d;font-weight:800;flex-shrink:0;" onclick="IbbsTour.replay(window.IBBS_TOUR_BACKUP)">
      <i class="bx bx-play-circle"></i> Ver tutorial de esta página
    </button>
  </div>
</div>

<!-- ═══════════════════════════════════════════════
  INFO CARDS
════════════════════════════════════════════════════ -->
<div id="dbInfoCards" data-tour="bk-resumen" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:.8rem;margin-bottom:1.4rem;">
  <div class="empty-row" style="grid-column:1/-1;"><span class="spin"></span></div>
</div>

<!-- ═══════════════════════════════════════════════
  LAYOUT PRINCIPAL — 2 columnas
════════════════════════════════════════════════════ -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.1rem;margin-bottom:1.1rem;">

  <!-- EXPORTAR -->
  <div class="card" data-tour="bk-exportar">
    <div class="card-head" style="border-bottom:2px solid var(--lime2);">
      <h3 style="display:flex;align-items:center;gap:.5rem;">
        <i class="bx bx-cloud-download" style="font-size:1.1rem;color:var(--lime2);"></i>
        Exportar
      </h3>
      <span style="font-size:.74rem;color:var(--muted);">Descargar copia de seguridad</span>
    </div>
    <div class="card-body">
      <p style="font-size:.84rem;color:var(--muted);line-height:1.7;margin-bottom:1rem;">
        Genera un archivo <code style="background:var(--cream);padding:.1rem .35rem;border-radius:4px;font-size:.8rem;">.sql</code> con toda la base de datos. Es un simple clic — no hace falta saber de informática.
      </p>
      <div style="display:flex;flex-direction:column;gap:.5rem;margin-bottom:1.2rem;">
        <div style="display:flex;align-items:center;gap:.6rem;font-size:.78rem;color:var(--ink);">
          <span style="width:20px;height:20px;border-radius:50%;background:var(--lime2);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:.68rem;flex-shrink:0;">1</span>
          Hacé clic en "Descargar SQL completo"
        </div>
        <div style="display:flex;align-items:center;gap:.6rem;font-size:.78rem;color:var(--ink);">
          <span style="width:20px;height:20px;border-radius:50%;background:var(--lime2);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:.68rem;flex-shrink:0;">2</span>
          Se descarga un archivo <code style="background:var(--cream);padding:.05rem .3rem;border-radius:3px;">.sql</code> a tu computadora
        </div>
        <div style="display:flex;align-items:center;gap:.6rem;font-size:.78rem;color:var(--ink);">
          <span style="width:20px;height:20px;border-radius:50%;background:var(--lime2);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:.68rem;flex-shrink:0;">3</span>
          Guardalo en un lugar seguro (correo, USB, Drive)
        </div>
      </div>
      <a href="api/backup.php?action=export" class="btn btn-primary" style="text-align:center;">
        <i class="bx bx-download"></i>
        Descargar SQL completo
      </a>
    </div>
  </div>

  <!-- IMPORTAR — solo superadmin -->
  <?php if($_rol==='superadmin'): ?>
  <div class="card" data-tour="bk-importar">
    <div class="card-head" style="border-bottom:2px solid #f59e0b;">
      <h3 style="display:flex;align-items:center;gap:.5rem;">
        <i class="bx bx-cloud-upload" style="font-size:1.1rem;color:#f59e0b;"></i>
        Importar / Restaurar
      </h3>
      <span style="font-size:.74rem;color:var(--muted);">Cargar desde archivo SQL</span>
    </div>
    <div class="card-body">
      <div class="warn-pill" style="margin-bottom:1rem;">
        <i class="bx bx-error" style="font-size:1rem;flex-shrink:0;margin-top:1px;"></i>
        <span><strong>Advertencia:</strong> Restaurar reemplazará todos los datos actuales por los del archivo. Descargá un respaldo primero, por si necesitás volver atrás.</span>
      </div>
      <div class="field" style="margin-bottom:1rem;">
        <label>Archivo .sql</label>
        <input type="file" id="sqlFile" accept=".sql"
          style="padding:.55rem .8rem;border:1.5px solid var(--border);border-radius:8px;font-size:.82rem;background:var(--cream);width:100%;font-family:'Nunito',sans-serif;cursor:pointer;">
      </div>
      <button class="btn btn-secondary" onclick="importarBD()">
        <i class="bx bx-upload"></i>
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
<div class="card" data-tour="bk-peligro" style="border:1.5px solid rgba(220,38,38,.3);margin-bottom:1.1rem;background:rgba(220,38,38,.02);">
  <div class="card-head" style="border-bottom:2px solid #dc2626;">
    <h3 style="color:#dc2626;display:flex;align-items:center;gap:.5rem;">
      <i class="bx bx-error-circle" style="font-size:1.1rem;"></i>
      Zona de Peligro
    </h3>
    <span style="font-size:.74rem;color:var(--muted);">Acciones irreversibles — solo Superadmin</span>
  </div>
  <div class="card-body">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.8rem;">
      <div>
        <div style="font-weight:700;font-size:.88rem;margin-bottom:.25rem;">Reiniciar base de datos</div>
        <div style="font-size:.8rem;color:var(--muted);max-width:480px;line-height:1.6;">
          Elimina <strong>todos</strong> los datos de alumnos, docentes, inscripciones, notas y asistencias. Conserva el usuario administrador. No se puede deshacer — usala solo si estás seguro(a).
        </div>
      </div>
      <button class="btn btn-danger" id="btnResetBD" onclick="abrirResetModal()">
        <i class="bx bx-trash"></i>
        Reiniciar BD
      </button>
    </div>
  </div>
</div>
<?php endif; ?>

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
        <i class="bx bx-trash"></i>
        Reiniciar base de datos
      </button>
    </div>
  </div>
</div>

<script>
document.addEventListener('ibbs:ready', async () => {
  const dStats = await ajax('dashboard_stats');
  if (!dStats?.ok) return;
  const dd = dStats.data;
  const reg = (parseInt(dd.alumnos)||0) + (parseInt(dd.docentes)||0) +
              (parseInt(dd.materias)||0) + (parseInt(dd.asist)||0);

  document.getElementById('dbInfoCards').innerHTML = [
    {l:'Alumnos',         v: dd.alumnos,  c:'#1d4ed8', bg:'#eff6ff', i:'bx-group'},
    {l:'Docentes',        v: dd.docentes, c:'#7c3aed', bg:'#f5f3ff', i:'bx-chalkboard'},
    {l:'Materias',        v: dd.materias, c:'#0f766e', bg:'#f0fdfa', i:'bx-book-open'},
    {l:'Asistencias',     v: dd.asist,    c:'#15803d', bg:'#f0fdf4', i:'bx-check-square'},
    {l:'Registros aprox.',v: reg,         c:'#92400e', bg:'#fffbeb', i:'bx-list-ul'},
  ].map(i=>`
    <div style="background:${i.bg};border:1px solid var(--border);border-radius:10px;padding:.85rem 1rem;display:flex;align-items:center;gap:.7rem;">
      <i class="bx ${i.i}" style="font-size:1.3rem;color:${i.c};flex-shrink:0;"></i>
      <div>
        <div data-countup style="font-size:1.4rem;font-weight:800;color:${i.c};line-height:1;">${i.v}</div>
        <div style="font-size:.62rem;text-transform:uppercase;letter-spacing:.7px;color:var(--muted);margin-top:3px;">${i.l}</div>
      </div>
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
  const _csrf = document.querySelector('meta[name="csrf-token"]');
  if (_csrf) fd.append('csrf_token', _csrf.content);
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

// ── Tutorial guiado de esta página ──────────────────────────────
window.IBBS_TOUR_BACKUP = {
  storageKey: 'ibbs_tour_backup_v1',
  steps: [
    { selector: '[data-tour="bk-resumen"]', title: 'Resumen rápido', text: 'Acá ves de un vistazo cuántos alumnos, docentes y materias hay registrados.' },
    { selector: '[data-tour="bk-exportar"]', title: 'Exportar (hacer un respaldo)', text: 'Un clic descarga un archivo con TODA la información del sistema. Hacelo seguido, sobre todo antes de cambios importantes, y guardá el archivo en un lugar aparte.' },
    <?php if($_rol==='superadmin'): ?>
    { selector: '[data-tour="bk-importar"]', title: 'Importar (restaurar)', text: 'Si algo sale mal, podés subir un archivo de respaldo acá para volver todo a como estaba. OJO: esto reemplaza los datos actuales — asegurate de tener un respaldo reciente antes de restaurar otro.' },
    <?php endif; ?>
    <?php if(can('all')): ?>
    { selector: '[data-tour="bk-peligro"]', title: 'Zona de Peligro', text: 'Acá solo hay una acción: borrar todo y empezar de cero. Es irreversible, por eso te pide tu contraseña antes de hacerlo. Usala solo si estás completamente seguro(a).' },
    <?php endif; ?>
  ],
  auto: true,
};
document.addEventListener('ibbs:ready', () => IbbsTour.start(window.IBBS_TOUR_BACKUP));
</script>
<?php include __DIR__.'/layout/foot.php'; ?>
