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
    notas_envio: '📝',
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

  // Qué vista de este portal (portal_docente.php / portal_alumno.php —
  // ambos son SPA con <div id="view-X">) abrir al hacer clic en una
  // notificación, según su tipo. Antes la campana solo mostraba texto:
  // había que salir a buscar a mano el módulo que la notificación
  // mencionaba (ej. "ya podés autoinscribirte" no llevaba a Materias).
  // Antes de navegar, chequeamos que la vista exista en este portal —
  // docente y alumno no tienen las mismas vistas, y este archivo lo
  // comparten los dos.
  function vistaParaTipo(tipo) {
    const esDocente = !!document.getElementById('view-entregas');
    const mapa = {
      solicitud_aprobada: 'materias',
      solicitud_rechazada: 'materias',
      solicitud_materia_aprobada: 'materias',
      solicitud_materia_rechazada: 'materias',
      solicitud_libro_activada: 'biblioteca',
      solicitud_libro_rechazada: 'biblioteca',
      notas_envio: 'plan-notas',
      tarea: esDocente ? 'entregas' : 'tareas',
      calificacion: esDocente ? 'entregas' : 'notas',
      reprobado: esDocente ? 'asistencia' : 'notas',
      asistencia: esDocente ? 'asistencia' : null,
    };
    return mapa[tipo] || null;
  }

  // tipos que son "algo pasó en el aula de esta materia" — si la
  // notificación trae materia_id, usamos el helper que ya existe en
  // cada portal (irAlAulaMateria) para caer directo en la pestaña
  // correcta del Aula Virtual, en vez de solo abrir Materias.
  const AULA_TAB_POR_TIPO = { foro: 'foro', clase_vivo: 'vivo', grabacion: 'grabaciones', material: 'materiales' };

  function navegarPorNotificacion(tipo, materiaId) {
    const tabAula = AULA_TAB_POR_TIPO[tipo];
    if (tabAula && materiaId && typeof window.irAlAulaMateria === 'function') {
      window.irAlAulaMateria(materiaId, tabAula);
      return true;
    }
    const vista = vistaParaTipo(tipo);
    if (vista && typeof window.switchView === 'function' && document.getElementById('view-' + vista)) {
      window.switchView(vista);
      return true;
    }
    return false;
  }

  window._ibbsNotifIr = function _ibbsNotifIr(id, tipo, materiaId) {
    const navego = navegarPorNotificacion(tipo, materiaId || null);
    if (navego) {
      const drop = document.getElementById('notifDrop');
      if (drop) drop.style.display = 'none';
    }
  };

  async function renderDrop() {
    const box = document.getElementById('notifDropList');
    if (!box) return;
    box.innerHTML = '<div style="text-align:center;padding:1.2rem;color:#7a8c72;font-size:.8rem;">Cargando…</div>';
    const d = await postAjax('notif_list');
    if (!d || !d.ok || !d.data.length) {
      box.innerHTML = '<div style="text-align:center;padding:1.2rem;color:#7a8c72;font-size:.8rem;">Sin notificaciones pendientes.</div>';
      return;
    }
    box.innerHTML = d.data.map((n) => {
      const seNavega = !!(AULA_TAB_POR_TIPO[n.tipo] || vistaParaTipo(n.tipo));
      return `
      <div id="nd${n.id}" style="padding:.6rem .5rem;border-bottom:1px solid #e0d8c8;${seNavega ? 'cursor:pointer;' : ''}" ${seNavega ? `onclick="_ibbsNotifIr(${n.id},'${n.tipo}',${n.materia_id ?? 'null'})"` : ''}>
        <div style="font-size:.62rem;text-transform:uppercase;letter-spacing:.5px;color:#7a8c72;margin-bottom:.15rem;">${NOTIF_ICONS[n.tipo] || 'ℹ️'} ${h(n.titulo || '')}</div>
        <div style="font-size:.8rem;color:#1a4d2e;line-height:1.4;">${h(n.mensaje || '')}</div>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:.35rem;gap:.6rem;">
          <span style="font-size:.66rem;color:#7a8c72;">${n.creado_en ? n.creado_en.substring(0, 16) : ''}</span>
          <span>
            <button onclick="event.stopPropagation();_ibbsNotifLeer(${n.id})" style="background:none;border:none;color:#16a34a;font-size:.7rem;cursor:pointer;">Marcar leída</button>
            <button onclick="event.stopPropagation();_ibbsNotifArchivar(${n.id})" style="background:none;border:none;color:#7a8c72;font-size:.7rem;cursor:pointer;margin-left:.4rem;">Archivar</button>
          </span>
        </div>
      </div>`;
    }).join('');
  }

  // Panel completo de notificaciones (vista "Notificaciones" en pantalla
  // grande, en la barra lateral de portal_docente.php/portal_alumno.php)
  // — mismo contenido y acciones que el drop chico de la campana, pero
  // con espacio para leerlo cómodo. Usa su propio prefijo de id
  // ("ndf" en vez de "nd") para no chocar con las filas del drop si
  // ambos llegaran a tener markup en la página a la vez.
  window.renderNotifFull = async function renderNotifFull(containerId) {
    const box = document.getElementById(containerId);
    if (!box) return;
    box.innerHTML = '<div style="text-align:center;padding:2rem;color:#7a8c72;">Cargando…</div>';
    const d = await postAjax('notif_list');
    if (!d || !d.ok || !d.data.length) {
      box.innerHTML = '<div style="text-align:center;padding:2rem;color:#7a8c72;font-size:.85rem;">No tenés notificaciones pendientes.</div>';
      return;
    }
    box.innerHTML = d.data.map((n) => {
      const seNavega = !!(AULA_TAB_POR_TIPO[n.tipo] || vistaParaTipo(n.tipo));
      return `
      <div id="ndf${n.id}" style="display:flex;gap:1rem;padding:1rem 1.2rem;border-bottom:1px solid #e0d8c8;${seNavega ? 'cursor:pointer;' : ''}" ${seNavega ? `onclick="_ibbsNotifIr(${n.id},'${n.tipo}',${n.materia_id ?? 'null'})"` : ''}>
        <div style="font-size:1.4rem;flex-shrink:0;line-height:1;">${NOTIF_ICONS[n.tipo] || 'ℹ️'}</div>
        <div style="flex:1;min-width:0;">
          <div style="font-size:.74rem;text-transform:uppercase;letter-spacing:.5px;color:#7a8c72;margin-bottom:.25rem;font-weight:700;">${h(n.titulo || '')}</div>
          <div style="font-size:.92rem;color:#1a4d2e;line-height:1.5;">${h(n.mensaje || '')}</div>
          <div style="font-size:.74rem;color:#7a8c72;margin-top:.4rem;">${n.creado_en ? n.creado_en.substring(0, 16) : ''}</div>
        </div>
        <div style="display:flex;gap:.5rem;flex-shrink:0;align-items:flex-start;">
          <button onclick="event.stopPropagation();_ibbsNotifLeerFull(${n.id})" style="background:none;border:1px solid #16a34a;color:#16a34a;border-radius:6px;padding:.3rem .7rem;font-size:.76rem;cursor:pointer;">Marcar leída</button>
          <button onclick="event.stopPropagation();_ibbsNotifArchivarFull(${n.id})" style="background:none;border:1px solid #7a8c72;color:#7a8c72;border-radius:6px;padding:.3rem .7rem;font-size:.76rem;cursor:pointer;">Archivar</button>
        </div>
      </div>`;
    }).join('');
  };

  window._ibbsNotifLeerFull = async function _ibbsNotifLeerFull(id) {
    await postAjax('notif_leer', { id });
    document.getElementById('ndf' + id)?.remove();
    setBadge(unread - 1);
  };

  window._ibbsNotifArchivarFull = async function _ibbsNotifArchivarFull(id) {
    await postAjax('notif_archivar', { id });
    document.getElementById('ndf' + id)?.remove();
    setBadge(unread - 1);
  };

  window.marcarTodasLeidasFull = async function marcarTodasLeidasFull(containerId) {
    await postAjax('notif_leer', { id: 0 });
    setBadge(0);
    await renderNotifFull(containerId);
  };

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

  window._ibbsNotifArchivar = async function _ibbsNotifArchivar(id) {
    await postAjax('notif_archivar', { id });
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
