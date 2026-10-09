<?php
$page_title  = 'Chat del Staff';
$page_sub    = 'Comunicación directa entre administración y docentes — sin materia';
$active_link = 'chat_staff';
include __DIR__.'/layout/head.php';
// Acceso: admin, superadmin y profesor — nunca alumno.
if(!in_array($_rol,['superadmin','admin','profesor'])){
    echo '<script>window.location="index.php";</script>'; exit;
}
?>

<div class="card" style="height:calc(100vh - 220px);min-height:420px;display:flex;flex-direction:column;">
  <div class="card-head">
    <h3>💬 Chat del Staff</h3>
    <span style="font-size:.78rem;color:var(--muted);">Administración y docentes — visible para todo el staff</span>
  </div>
  <div id="chat-box" style="flex:1;overflow-y:auto;padding:1rem 1.2rem;display:flex;flex-direction:column;gap:.6rem;background:var(--paper);">
    <div style="text-align:center;padding:2rem;color:var(--muted);"><span class="spin"></span> Cargando chat...</div>
  </div>

  <div id="reply-indicator" style="display:none;padding:.5rem 1.2rem;background:var(--cream);border-top:1px solid var(--border);font-size:.8rem;color:var(--muted);align-items:center;justify-content:space-between;">
    <span>Respondiendo a <strong id="reply-to-name"></strong></span>
    <button type="button" onclick="cancelReplyStaff()" style="background:none;border:none;cursor:pointer;color:var(--muted);font-size:1rem;">✕</button>
  </div>

  <form id="chat-form-staff" style="display:flex;gap:.6rem;padding:1rem 1.2rem;border-top:1px solid var(--border);">
    <input type="hidden" id="respuesta_a_staff" value="">
    <input type="text" id="mensaje-input-staff" placeholder="Escribe un mensaje para el staff…" autocomplete="off"
      style="flex:1;padding:.7rem 1rem;border:1.5px solid var(--border);border-radius:10px;font-size:.9rem;outline:none;">
    <button type="submit" class="btn btn-primary">Enviar</button>
  </form>
</div>

<script>
const CURRENT_USER_STAFF = "<?php echo addslashes($_SESSION['usuario'] ?? ''); ?>";
const MI_USUARIO_ID_STAFF = <?= (int)$_uid ?>;
let lastCountStaff = -1;
let staffChatInterval = null;

function hStaff(s){ const d=document.createElement('div'); d.textContent=String(s??''); return d.innerHTML; }
function hAttrStaff(s){ return hStaff(s).replace(/"/g,'&quot;').replace(/'/g,'&#39;'); }

async function loadChatStaff() {
  try {
    const r = await fetch('api/chat_general.php?action=get_mensajes');
    const mensajes = await r.json();
    if (mensajes.error) { console.error('Error del servidor:', mensajes.error); return; }
    if (mensajes.length === lastCountStaff) return;
    lastCountStaff = mensajes.length;
    renderChatStaff(mensajes);
  } catch (e) { console.error('Error cargando chat del staff', e); }
}

function roleBadgeStaff(rol) {
  if (rol === 'profesor') return '<span class="badge b-tardanza" style="font-size:.65rem;margin-left:.4rem;">Profesor</span>';
  if (rol === 'admin' || rol === 'superadmin') return '<span class="badge b-profesor" style="font-size:.65rem;margin-left:.4rem;">Admin</span>';
  return '';
}

function renderChatStaff(mensajes) {
  const chatBox = document.getElementById('chat-box');
  chatBox.innerHTML = '';
  if (!mensajes.length) {
    chatBox.innerHTML = '<div style="text-align:center;padding:2rem;color:var(--muted);font-size:.9rem;">Sin mensajes todavía. Escribí el primero para el staff.</div>';
    return;
  }

  const hilos = {};
  mensajes.forEach(m => { if (m.respuesta_a === null) hilos[m.id] = { ...m, respuestas: [] }; });
  mensajes.forEach(m => { if (m.respuesta_a !== null && hilos[m.respuesta_a]) hilos[m.respuesta_a].respuestas.push(m); });

  for (const id in hilos) {
    const thread = hilos[id];
    chatBox.insertAdjacentHTML('beforeend', crearMensajeStaffHTML(thread, false));
    if (thread.respuestas.length > 0) {
      const cont = document.createElement('div');
      cont.style.cssText = "margin-left:2.5rem;margin-top:.5rem;padding-left:1rem;border-left:2px solid var(--border);display:flex;flex-direction:column;gap:.5rem;";
      thread.respuestas.forEach(r => cont.insertAdjacentHTML('beforeend', crearMensajeStaffHTML(r, true)));
      chatBox.appendChild(cont);
    }
  }
  chatBox.scrollTop = chatBox.scrollHeight;
}

function crearMensajeStaffHTML(msg, isReply) {
  const isMe = msg.usuario_id === MI_USUARIO_ID_STAFF;
  const bg = isMe ? 'background:var(--bubble-mine-bg);border:1px solid var(--bubble-mine-border);' : 'background:var(--paper);border:1px solid var(--border);';
  const dateStr = new Date(msg.fecha).toLocaleString([], {month:'short', day:'numeric', hour:'2-digit', minute:'2-digit'});
  const replyBtn = !isReply ? `<button type="button" onclick="setReplyStaff(${msg.id}, '${hAttrStaff(msg.usuario_nombre)}')" style="background:none;border:none;color:var(--primary);cursor:pointer;font-size:.8rem;margin-top:.4rem;padding:0;">Responder</button>` : '';
  const delBtn = msg.puede_borrar ? `<button type="button" onclick="borrarMensajeStaff(${msg.id})" style="background:none;border:none;color:var(--red);cursor:pointer;font-size:.8rem;margin-top:.4rem;margin-left:.8rem;padding:0;">Borrar</button>` : '';

  return `
    <div style="padding:.8rem 1rem;border-radius:8px;${bg}">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.3rem;">
            <strong style="font-size:.9rem;color:var(--ink);">${hStaff(msg.usuario_nombre)} ${roleBadgeStaff(msg.rol)}</strong>
            <span style="font-size:.75rem;color:var(--muted);">${dateStr}</span>
        </div>
        <p style="margin:0;font-size:.9rem;color:var(--ink);white-space:pre-wrap;line-height:1.4;">${hStaff(msg.mensaje)}</p>
        ${replyBtn}${delBtn}
    </div>`;
}

window.setReplyStaff = function(id, nombre) {
  document.getElementById('respuesta_a_staff').value = id;
  document.getElementById('reply-to-name').textContent = nombre;
  document.getElementById('reply-indicator').style.display = 'flex';
  document.getElementById('mensaje-input-staff').focus();
};
window.cancelReplyStaff = function() {
  document.getElementById('respuesta_a_staff').value = '';
  document.getElementById('reply-indicator').style.display = 'none';
};

async function borrarMensajeStaff(id) {
  const rr = await Ibbs.confirm({title:'¿Borrar mensaje?', text:'Esta acción no se puede deshacer.', confirm:'Sí, borrar', danger:true});
  if (!rr.isConfirmed) return;
  try {
    const _csrf = document.querySelector('meta[name="csrf-token"]');
    const r = await fetch('api/chat_general.php?action=delete_mensaje', {
      method: 'POST', headers: {'Content-Type':'application/json'},
      body: JSON.stringify({ id, csrf_token: _csrf ? _csrf.content : '' })
    });
    const result = await r.json();
    if (result.success) { lastCountStaff = -1; loadChatStaff(); }
    else toast(result.error || 'No se pudo borrar el mensaje.', 'err');
  } catch (e) { toast('Error al borrar el mensaje.', 'err'); }
}

document.getElementById('chat-form-staff').addEventListener('submit', async (e) => {
  e.preventDefault();
  const input = document.getElementById('mensaje-input-staff');
  const mensaje = input.value.trim();
  const respuesta_a = document.getElementById('respuesta_a_staff').value;
  if (!mensaje) return;
  input.value = '';
  cancelReplyStaff();
  try {
    const _csrf = document.querySelector('meta[name="csrf-token"]');
    const r = await fetch('api/chat_general.php?action=post_mensaje', {
      method: 'POST', headers: {'Content-Type':'application/json'},
      body: JSON.stringify({ mensaje, respuesta_a, csrf_token: _csrf ? _csrf.content : '' })
    });
    const result = await r.json();
    if (result.success) { lastCountStaff = -1; loadChatStaff(); }
    else toast(result.error || 'No se pudo enviar el mensaje.', 'err');
  } catch (e) { toast('Error al enviar el mensaje.', 'err'); }
});

document.addEventListener('ibbs:ready', () => {
  loadChatStaff();
  if (staffChatInterval) clearInterval(staffChatInterval);
  staffChatInterval = setInterval(loadChatStaff, 5000);

  if (window.IbbsRT && window.IbbsRT.hasWs) {
    window.IbbsRT.on('chat_staff_mensaje', () => { lastCountStaff = -1; loadChatStaff(); });
  }
});
</script>
<?php include __DIR__.'/layout/foot.php'; ?>
