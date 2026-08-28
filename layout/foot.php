
</main><!-- /#main -->

<!-- SweetAlert2 -->
<script src="assets/libs/sweetalert2.all.min.js"></script>

<script>
// ── Sidebar toggle ──────────────────────────────────────────
let _col = false;
function toggleSB() {
  _col = !_col;
  document.getElementById('sb').classList.toggle('col', _col);
  document.getElementById('main').classList.toggle('exp', _col);
  const t = document.getElementById('toggler');
  t.classList.toggle('col', _col);
  document.getElementById('togico').innerHTML = _col
    ? '<polyline points="9 18 15 12 9 6"/>'
    : '<polyline points="15 18 9 12 15 6"/>';
}

// ── Toast IBBS (éxito / info) ───────────────────────────────
function toast(msg, type='ok') {
  const el = document.getElementById('toast');
  document.getElementById('tmsg').textContent = msg;
  el.className = 'show ' + type;
  clearTimeout(el._t);
  el._t = setTimeout(() => el.className = '', 5000);
}

// ── SweetAlert2 personalizado al estilo IBBS ───────────────
const Ibbs = {
  // Confirmación de acción destructiva (Eliminar, etc.)
  confirm(opts = {}) {
    return Swal.fire({
      title:              opts.title  || '¿Estás seguro?',
      html:               opts.text   || '',
      icon:               opts.icon   || 'warning',
      showCancelButton:   true,
      confirmButtonText:  opts.confirm|| 'Sí, continuar',
      cancelButtonText:   opts.cancel || 'Cancelar',
      confirmButtonColor: opts.danger ? '#dc2626' : '#1a4d2e',
      cancelButtonColor:  'transparent',
      background:         '#f5f0e8',
      color:              '#1a4d2e',
      iconColor:          opts.danger ? '#dc2626' : '#f59e0b',
      customClass: {
        popup:         'ibbs-swal',
        title:         'ibbs-swal-title',
        confirmButton: opts.danger ? 'ibbs-swal-btn-danger' : 'ibbs-swal-btn-ok',
        cancelButton:  'ibbs-swal-btn-cancel',
      },
      buttonsStyling: false,
    });
  },

  // Error con detalles
  error(msg, title='Error') {
    return Swal.fire({
      title, html: msg, icon: 'error',
      confirmButtonText: 'Entendido',
      background: '#f5f0e8', color: '#1a4d2e',
      iconColor: '#dc2626',
      confirmButtonColor: '#dc2626',
      customClass: { popup:'ibbs-swal', title:'ibbs-swal-title', confirmButton:'ibbs-swal-btn-danger' },
      buttonsStyling: false,
    });
  },

  // Éxito
  success(msg, title='¡Listo!') {
    return Swal.fire({
      title, html: msg, icon: 'success',
      timer: 2200, timerProgressBar: true, showConfirmButton: false,
      background: '#f5f0e8', color: '#1a4d2e',
      iconColor: '#16a34a',
      customClass: { popup:'ibbs-swal', title:'ibbs-swal-title' },
      buttonsStyling: false,
    });
  },

  // Advertencia con confirmación (no-destructiva)
  warn(opts = {}) {
    return Swal.fire({
      title:              opts.title  || 'Confirmar acción',
      html:               opts.text   || '',
      icon:               'question',
      showCancelButton:   true,
      confirmButtonText:  opts.confirm|| 'Confirmar',
      cancelButtonText:   opts.cancel || 'Cancelar',
      background:         '#f5f0e8', color: '#1a4d2e',
      iconColor:          '#f59e0b',
      confirmButtonColor: '#1a4d2e',
      customClass: { popup:'ibbs-swal', title:'ibbs-swal-title', confirmButton:'ibbs-swal-btn-ok', cancelButton:'ibbs-swal-btn-cancel' },
      buttonsStyling: false,
    });
  }
};

// ── Validación de formularios JS (sin browser popup) ───────
// Retorna true si válido, false + resalta campos si inválido
function validarForm(campos) {
  // campos: [{id|name, label, tipo:'texto'|'email'|'cedula'|'password'|'numero', min, max, required}]
  let errores = [];
  campos.forEach(f => {
    const el = f.id ? document.getElementById(f.id) : document.querySelector(`[name="${f.name}"]`);
    if (!el) return;
    const v = el.value.trim();
    el.style.borderColor = '';

    if (f.required !== false && !v) {
      errores.push(`<b>${f.label}</b> es obligatorio.`);
      el.style.borderColor = '#dc2626';
      return;
    }
    if (!v) return; // opcional vacío → ok

    if (f.tipo === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v)) {
      errores.push(`<b>${f.label}</b>: correo inválido.`);
      el.style.borderColor = '#dc2626';
    }
    if (f.tipo === 'cedula' && !/^\d{5,12}$/.test(v.replace(/[VvEe\-]/g,''))) {
      errores.push(`<b>${f.label}</b>: solo números, entre 5 y 12 dígitos.`);
      el.style.borderColor = '#dc2626';
    }
    if (f.tipo === 'password' && v.length < (f.min||6)) {
      errores.push(`<b>${f.label}</b>: mínimo ${f.min||6} caracteres.`);
      el.style.borderColor = '#dc2626';
    }
    if (f.tipo === 'numero') {
      const n = parseFloat(v.replace(',','.'));
      if (isNaN(n)) { errores.push(`<b>${f.label}</b>: debe ser un número.`); el.style.borderColor='#dc2626'; }
      else if (f.min !== undefined && n < f.min) { errores.push(`<b>${f.label}</b>: mínimo ${f.min}.`); el.style.borderColor='#dc2626'; }
      else if (f.max !== undefined && n > f.max) { errores.push(`<b>${f.label}</b>: máximo ${f.max}.`); el.style.borderColor='#dc2626'; }
    }
    if (f.tipo === 'texto' && f.min && v.length < f.min) {
      errores.push(`<b>${f.label}</b>: mínimo ${f.min} caracteres.`);
      el.style.borderColor = '#dc2626';
    }
  });

  if (errores.length) {
    Ibbs.error(errores.join('<br>'), 'Completa los campos correctamente');
    return false;
  }
  return true;
}

// ── Sanitización de inputs (prevención XSS/injection) ──────
function sanitize(v) {
  if (v === null || v === undefined) return '';
  return String(v)
    .replace(/</g,'&lt;').replace(/>/g,'&gt;')
    .replace(/"/g,'&quot;').replace(/'/g,'&#039;')
    .trim();
}
// Escapa solo para mostrar en innerHTML
function h(s) {
  const d = document.createElement('div');
  d.textContent = String(s ?? '');
  return d.innerHTML;
}

// ── Modal helpers ───────────────────────────────────────────
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) {
  document.getElementById(id).classList.remove('open');
  // Reset border colors on close
  document.querySelectorAll('#'+id+' input, #'+id+' select, #'+id+' textarea').forEach(el => el.style.borderColor='');
}
document.querySelectorAll('.modal-backdrop').forEach(el => {
  el.addEventListener('click', e => { if(e.target===el) el.classList.remove('open'); });
});

// ── AJAX helper ─────────────────────────────────────────────
async function ajax(action, data={}) {
  const fd = new FormData();
  fd.append('action', action);
  // Sanitize all values before sending
  Object.keys(data).forEach(k => fd.append(k, data[k] ?? ''));
  try {
    const ctrl = new AbortController();
    const timer = setTimeout(() => ctrl.abort(), 12000);
    const r   = await fetch('php/ajax.php', {method:'POST', body:fd, signal:ctrl.signal});
    clearTimeout(timer);
    const raw = await r.text();
    try { return JSON.parse(raw); }
    catch(e) {
      const tmp = document.createElement('div'); tmp.innerHTML = raw;
      const msg = tmp.textContent.trim().replace(/\s+/g,' ').substring(0,200);
      toast('Error del servidor: ' + msg, 'err');
      document.querySelectorAll('.spin').forEach(s => {
        const td = s.closest('td'); if(td) td.textContent = '⚠ Error';
      });
      console.error('IBBS ajax error - raw response:', raw.substring(0,300));
      return null;
    }
  } catch(e) {
    toast(e.name==='AbortError' ? 'Tiempo agotado' : 'Sin respuesta del servidor', 'err');
    document.querySelectorAll('.spin').forEach(s => {
      const td = s.closest('td'); if(td) td.textContent = '⚠ Sin respuesta';
    });
    return null;
  }
}

function switchTab(group, id) {
  document.querySelectorAll('[data-tab-group="'+group+'"]').forEach(el => el.classList.remove('active'));
  document.querySelectorAll('[data-pane-group="'+group+'"]').forEach(el => el.classList.remove('active'));
  document.querySelector('[data-tab-group="'+group+'"][data-tab="'+id+'"]').classList.add('active');
  document.querySelector('[data-pane-group="'+group+'"][data-pane="'+id+'"]').classList.add('active');
}
function filterTable(tblId, q) {
  document.querySelectorAll('#'+tblId+' tbody tr:not(.empty-row)').forEach(r => {
    r.style.display = r.textContent.toLowerCase().includes(q.toLowerCase()) ? '' : 'none';
  });
}


// ── MOBILE SIDEBAR ──────────────────────────────────────────
function toggleMobileSB() {
  const sb = document.getElementById('sb');
  sb.classList.toggle('mobile-open');
}
function closeMobileSB() {
  document.getElementById('sb').classList.remove('mobile-open');
}
// Show hamburger on mobile
function checkMobileBtn() {
  const btn = document.getElementById('mobileMenuBtn');
  if (!btn) return;
  btn.style.display = window.innerWidth <= 768 ? 'flex' : 'none';
}
checkMobileBtn();
window.addEventListener('resize', checkMobileBtn);
// Close sidebar when clicking a link on mobile
document.querySelectorAll('#sb .sb-link').forEach(a => {
  a.addEventListener('click', () => { if(window.innerWidth<=768) closeMobileSB(); });
});

// ── DARK MODE ───────────────────────────────────────────────
function applyTheme(dark) {
  if (dark) {
    document.documentElement.setAttribute('data-theme', 'dark');
  } else {
    document.documentElement.removeAttribute('data-theme');
    document.documentElement.style.background = '';
  }
}
function toggleTheme() {
  const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
  const newDark = !isDark;
  localStorage.setItem('ibbs_theme', newDark ? 'dark' : 'light');
  applyTheme(newDark);
}
// Apply saved theme
(function(){ applyTheme(localStorage.getItem('ibbs_theme') === 'dark'); })();

// ── IBBS CONFIRM (¿Estás seguro?) ───────────────────────────
function ibbsConfirm(msg, onConfirm) {
  const modal = document.getElementById('ibbsConfirmModal');
  document.getElementById('ibbsConfirmMsg').textContent = msg;
  modal.style.display = 'flex';
  window._ibbsConfirmCb = onConfirm;
}
function ibbsConfirmYes() {
  document.getElementById('ibbsConfirmModal').style.display = 'none';
  if (typeof window._ibbsConfirmCb === 'function') window._ibbsConfirmCb();
  window._ibbsConfirmCb = null;
}
function ibbsConfirmCancel() {
  document.getElementById('ibbsConfirmModal').style.display = 'none';
  window._ibbsConfirmCb = null;
}
// Keep confirmAdmin alias only for reset BD (uses PIN modal separately)
function confirmAdmin(msg, onConfirm) { ibbsConfirm(msg, onConfirm); }

// ── INPUT VALIDATION UTILITY ─────────────────────────────────
// Attribute-based: data-only="letters|numbers|alphanumeric|phone|cedula"
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-only]').forEach(el => applyValidation(el));
});
// Also for dynamically added inputs (modals)
const _origShow = window.openModal;
function applyValidation(el) {
  const rule = el.getAttribute('data-only');
  if (!rule) return;
  el.addEventListener('keypress', e => {
    const ch = e.key;
    if (ch.length > 1) return; // allow ctrl keys
    const patterns = {
      letters:     /^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s\-\.]+$/,
      numbers:     /^[0-9]+$/,
      decimal:     /^[0-9.]$/,
      alphanumeric:/^[a-zA-Z0-9áéíóúÁÉÍÓÚñÑüÜ\s\-_]+$/,
      phone:       /^[0-9\-\+\s\(\)]+$/,
      cedula:      /^[0-9VvEe\-]+$/,
      code:        /^[a-zA-Z0-9\-\.]+$/,
      username:    /^[a-zA-Z0-9_\.\-]+$/,
    };
    const pat = patterns[rule];
    if (pat && !pat.test(ch)) e.preventDefault();
  });
  el.addEventListener('paste', e => {
    const rule2 = el.getAttribute('data-only');
    if (!rule2) return;
    const txt = (e.clipboardData || window.clipboardData).getData('text');
    const patterns2 = {
      letters:     /^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s\-\.]+$/,
      numbers:     /^[0-9]+$/,
      decimal:     /^[0-9.]+$/,
      alphanumeric:/^[a-zA-Z0-9áéíóúÁÉÍÓÚñÑüÜ\s\-_]+$/,
      phone:       /^[0-9\-\+\s\(\)]+$/,
      cedula:      /^[0-9VvEe\-]+$/,
      code:        /^[a-zA-Z0-9\-\.]+$/,
      username:    /^[a-zA-Z0-9_\.\-]+$/,
    };
    const pat2 = patterns2[rule2];
    if (pat2 && !pat2.test(txt)) { e.preventDefault(); toast('Solo se permiten '+rule2+' en este campo.','err'); }
  });
}
// Apply to newly opened modals
const _obsValidation = new MutationObserver(muts => {
  muts.forEach(m => m.addedNodes.forEach(n => {
    if (n.nodeType===1) n.querySelectorAll('[data-only]').forEach(applyValidation);
  }));
});
_obsValidation.observe(document.body, {childList:true, subtree:true});

document.addEventListener('keydown', e => {
  if (e.key === 'Escape') ibbsConfirmCancel();
  if (e.key === 'Enter' && document.getElementById('ibbsConfirmModal').style.display === 'flex') ibbsConfirmYes();
});

// ── ibbs:ready PRIMERO — los módulos necesitan esto para iniciar
// ── Dispatch ibbs:ready ─────────────────────────────────────
setTimeout(function() {
  try { document.dispatchEvent(new Event('ibbs:ready')); }
  catch(e) { console.error('ibbs:ready dispatch error:', e); }
}, 0);

// Campana de notificaciones — carga después, sin bloquear nada
setTimeout(async function(){
  try {
    const d = await ajax('notif_list');
    if(!d?.ok) return;
    const el = document.getElementById('notifCount');
    if(!el) return;
    const cnt = parseInt(d.count)||0;
    if(cnt>0){ el.textContent = cnt>9?'9+':cnt; el.style.display='flex'; }
    else { el.style.display='none'; }
  } catch(e) { /* silencioso */ }
}, 800);
</script>

<style>
/* ── SweetAlert2 IBBS theme ─────────────────────────────── */
.ibbs-swal {
  font-family: 'Inter', sans-serif !important;
  border-radius: 16px !important;
  border: 1.5px solid rgba(26,77,46,.1) !important;
  box-shadow: 0 20px 60px rgba(0,0,0,.2) !important;
}
.ibbs-swal-title {
  font-family: 'DM Serif Display', serif !important;
  font-size: 1.25rem !important;
  color: #1a4d2e !important;
  font-weight: 400 !important;
}
.ibbs-swal .swal2-html-container {
  font-size: .88rem !important;
  color: #4a5c4b !important;
  line-height: 1.6 !important;
}
.ibbs-swal .swal2-actions { gap: .6rem !important; }
.ibbs-swal-btn-ok {
  background: #1a4d2e !important; color: #39ff14 !important;
  border-radius: 10px !important; padding: .65rem 1.4rem !important;
  font-size: .88rem !important; font-weight: 600 !important;
  border: none !important; cursor: pointer !important;
  font-family: 'Inter', sans-serif !important;
}
.ibbs-swal-btn-danger {
  background: #dc2626 !important; color: #fff !important;
  border-radius: 10px !important; padding: .65rem 1.4rem !important;
  font-size: .88rem !important; font-weight: 600 !important;
  border: none !important; cursor: pointer !important;
  font-family: 'Inter', sans-serif !important;
}
.ibbs-swal-btn-cancel {
  background: transparent !important; color: #555 !important;
  border-radius: 10px !important; padding: .65rem 1.4rem !important;
  font-size: .88rem !important; font-weight: 500 !important;
  border: 1.5px solid #ddd !important; cursor: pointer !important;
  font-family: 'Inter', sans-serif !important;
}
.ibbs-swal-btn-ok:hover    { background: #1e5c36 !important; }
.ibbs-swal-btn-danger:hover{ background: #b91c1c !important; }
.ibbs-swal-btn-cancel:hover{ border-color: #aaa !important; }
.ibbs-swal .swal2-timer-progress-bar { background: #39ff14 !important; }
</style>

<!-- ══ SPOTLIGHT — BÚSQUEDA GLOBAL Ctrl+K ══════════════════ -->
<div id="ibbsConfirmModal" style="display:none;position:fixed;inset:0;z-index:9999;align-items:center;justify-content:center;padding:1rem;">
  <!-- Backdrop -->
  <div id="ibbsConfirmBd" onclick="ibbsConfirmCancel()" style="position:absolute;inset:0;background:rgba(0,0,0,.5);backdrop-filter:blur(6px);"></div>
  <!-- Card -->
  <div style="position:relative;background:var(--paper);border:1.5px solid var(--border);border-radius:20px;padding:0;width:380px;max-width:94vw;box-shadow:0 32px 80px rgba(0,0,0,.3);overflow:hidden;animation:confirmIn .3s cubic-bezier(.34,1.56,.64,1);">
    <!-- Top stripe -->
    <div style="height:4px;background:linear-gradient(90deg,#dc2626,#f97316);"></div>
    <div style="padding:1.8rem 1.8rem 1.4rem;">
      <!-- Icon + title -->
      <div style="display:flex;align-items:flex-start;gap:1rem;margin-bottom:1.2rem;">
        <div style="width:44px;height:44px;border-radius:12px;background:#fee2e2;display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:.1rem;">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
        </div>
        <div>
          <div style="font-family:'DM Serif Display',serif;font-size:1.15rem;color:var(--ink);margin-bottom:.3rem;">¿Estás seguro?</div>
          <div id="ibbsConfirmMsg" style="font-size:.84rem;color:var(--muted);line-height:1.55;"></div>
        </div>
      </div>
      <!-- Warning pill -->
      <div style="background:#fff7ed;border:1px solid #fed7aa;border-radius:10px;padding:.55rem .9rem;display:flex;align-items:center;gap:.5rem;margin-bottom:1.4rem;">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#ea580c" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        <span style="font-size:.76rem;color:#c2410c;font-weight:600;">Esta acción no se puede deshacer</span>
      </div>
      <!-- Buttons -->
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:.7rem;">
        <button onclick="ibbsConfirmCancel()" id="ibbsConfirmNo"
          style="padding:.8rem;border:1.5px solid var(--border);border-radius:11px;background:var(--cream);cursor:pointer;font-size:.88rem;font-weight:600;color:var(--muted);font-family:'Nunito',sans-serif;transition:all .2s;">
          No, cancelar
        </button>
        <button onclick="ibbsConfirmYes()" id="ibbsConfirmSi"
          style="padding:.8rem;border:none;border-radius:11px;background:#dc2626;color:#fff;cursor:pointer;font-size:.88rem;font-weight:700;font-family:'Nunito',sans-serif;transition:all .2s;box-shadow:0 4px 14px rgba(220,38,38,.3);">
          Sí, eliminar
        </button>
      </div>
    </div>
  </div>
</div>
<style>
@keyframes confirmIn { from{opacity:0;transform:scale(.9) translateY(20px)} to{opacity:1;transform:none} }
#ibbsConfirmNo:hover  { background:var(--paper);border-color:var(--muted);color:var(--ink); }
#ibbsConfirmSi:hover  { background:#b91c1c;box-shadow:0 6px 20px rgba(220,38,38,.4);transform:translateY(-1px); }
#ibbsConfirmSi:active { transform:translateY(0); }
html[data-theme="dark"] #ibbsConfirmBd { background:rgba(0,0,0,.7); }
html[data-theme="dark"] .warn-pill { background:#1c1009!important;border-color:#7c2d12!important; }
html[data-theme="dark"] #ibbsConfirmNo { background:var(--cream);border-color:var(--border); }
</style>

</body>
</html>
