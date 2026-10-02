/**
 * IBBS — Campana de notificaciones en tiempo real para los portales
 * autocontenidos (portal_docente.php / portal_alumno.php).
 *
 * El panel de administración ya tiene esto desde layout/foot.php
 * (campana + SSE), pero portal_docente.php y portal_alumno.php no
 * cargan ese layout compartido — son SPA aparte — así que docentes y
 * alumnos nunca recibían avisos en vivo: solo se enteraban de algo
 * nuevo si recargaban la página (pull). Este archivo reimplementa el
 * mismo mecanismo (api/notificaciones_stream.php por Server-Sent
 * Events) para que también tengan push.
 *
 * Requiere en la página: <meta name="csrf-token" content="...">, el
 * objeto global Ibbs (assets/ibbs-alerts.js) para el aviso emergente,
 * y los elementos #notifBell, #notifCount, #notifDrop, #notifDropList.
 */
(function () {
  'use strict';

  const NOTIF_ICONS = {
    anuncio: '📢', foro: '💬', tarea: '📋', calificacion: '✅',
    clase_vivo: '🔴', grabacion: '🎬', reprobado: '⚠️', asistencia: '⚠️',
    sistema: '⚙️', info: 'ℹ️', solicitud_alumno: '🧑‍🎓',
    solicitud_aprobada: '✅', solicitud_rechazada: '⛔',
    solicitud_materia: '🧾', solicitud_materia_aprobada: '✅', solicitud_materia_rechazada: '⛔',
    solicitud_libro: '📚', solicitud_libro_activada: '✅', solicitud_libro_rechazada: '⛔',
  };

  // Escapa antes de meter en innerHTML — titulo/mensaje son texto
  // libre (ej. el contenido de un mensaje de foro, o el nombre que
  // alguien puso al registrarse), nunca HTML de confianza.
  function h(s) {
    const d = document.createElement('div');
    d.textContent = String(s ?? '');
    return d.innerHTML;
  }

  function csrfToken() {
    const m = document.querySelector('meta[name="csrf-token"]');
    return m ? m.content : '';
  }

  async function postAjax(action, extra) {
    const fd = new FormData();
    fd.append('action', action);
    fd.append('csrf_token', csrfToken());
    if (extra) Object.keys(extra).forEach((k) => fd.append(k, extra[k]));
    try {
      const r = await fetch('api/ajax.php', { method: 'POST', body: fd });
      return await r.json();
    } catch (e) {
      return { ok: false };
    }
  }

  let unread = 0;
  let ultimoId = 0;
  let es = null;

  function setBadge(n) {
    unread = Math.max(0, n);
    const el = document.getElementById('notifCount');
    if (!el) return;
    if (unread > 0) {
      el.textContent = unread > 9 ? '9+' : String(unread);
      el.style.display = 'flex';
    } else {
      el.style.display = 'none';
    }
  }

  function recibida(n) {
    ultimoId = Math.max(ultimoId, parseInt(n.id, 10) || 0);
    setBadge(unread + 1);
    const icono = NOTIF_ICONS[n.tipo] || 'ℹ️';
    if (window.Ibbs && typeof window.Ibbs.toast === 'function') {
      window.Ibbs.toast((n.titulo || 'Nueva notificación'), icono);
    }
    const drop = document.getElementById('notifDrop');
    if (drop && drop.style.display === 'block') renderDrop();
  }

  function abrirStream() {
    if (typeof EventSource === 'undefined' || es) return;
    try {
      es = new EventSource('api/notificaciones_stream.php?since=' + ultimoId);
      es.onmessage = (ev) => {
        let n;
        try { n = JSON.parse(ev.data); } catch (e) { return; }
        recibida(n);
      };
      es.onerror = () => {}; // EventSource reintenta solo
    } catch (e) { /* silencioso */ }
  }

  function cerrarStream() {
    if (es) { es.close(); es = null; }
  }

  // Pestaña en segundo plano no necesita mantener la conexión abierta
  // (igual que ya hace layout/foot.php para el panel de administración).
  document.addEventListener('visibilitychange', () => {
    if (document.hidden) cerrarStream();
    else abrirStream();
  });

  async function renderDrop() {
    const box = document.getElementById('notifDropList');
    if (!box) return;
    box.innerHTML = '<div style="text-align:center;padding:1.2rem;color:#7a8c72;font-size:.8rem;">Cargando…</div>';
    const d = await postAjax('notif_list');
    if (!d || !d.ok || !d.data.length) {
      box.innerHTML = '<div style="text-align:center;padding:1.2rem;color:#7a8c72;font-size:.8rem;">Sin notificaciones pendientes.</div>';
      return;
    }
    box.innerHTML = d.data.map((n) => `
      <div id="nd${n.id}" style="padding:.6rem .5rem;border-bottom:1px solid #e0d8c8;">
        <div style="font-size:.62rem;text-transform:uppercase;letter-spacing:.5px;color:#7a8c72;margin-bottom:.15rem;">${NOTIF_ICONS[n.tipo] || 'ℹ️'} ${h(n.titulo || '')}</div>
        <div style="font-size:.8rem;color:#1a4d2e;line-height:1.4;">${h(n.mensaje || '')}</div>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:.35rem;">
          <span style="font-size:.66rem;color:#7a8c72;">${n.creado_en ? n.creado_en.substring(0, 16) : ''}</span>
          <button onclick="_ibbsNotifLeer(${n.id})" style="background:none;border:none;color:#16a34a;font-size:.7rem;cursor:pointer;">Marcar leída</button>
        </div>
      </div>`).join('');
  }

  window.toggleNotifDrop = async function toggleNotifDrop() {
    const drop = document.getElementById('notifDrop');
    if (!drop) return;
    const abierto = drop.style.display === 'block';
    drop.style.display = abierto ? 'none' : 'block';
    if (!abierto) await renderDrop();
  };

  window._ibbsNotifLeer = async function _ibbsNotifLeer(id) {
    await postAjax('notif_leer', { id });
    const row = document.getElementById('nd' + id);
    if (row) row.remove();
    setBadge(unread - 1);
  };

  window.marcarTodasLeidasDrop = async function marcarTodasLeidasDrop() {
    await postAjax('notif_leer', { id: 0 });
    setBadge(0);
    await renderDrop();
  };

  document.addEventListener('click', (e) => {
    const drop = document.getElementById('notifDrop');
    if (!drop || drop.style.display !== 'block') return;
    if (!drop.contains(e.target) && !e.target.closest('#notifBell')) drop.style.display = 'none';
  });

  async function init() {
    if (!document.getElementById('notifBell')) return;
    const d = await postAjax('notif_list');
    if (d && d.ok) {
      setBadge(d.count || 0);
      (d.data || []).forEach((n) => { ultimoId = Math.max(ultimoId, parseInt(n.id, 10) || 0); });
    }
    if (!document.hidden) abrirStream();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
