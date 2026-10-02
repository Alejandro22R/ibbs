/**
 * IBBS — Diálogos SweetAlert2 al estilo institucional.
 *
 * Versión standalone (sin depender de ajax()/toast() de layout/foot.php)
 * para páginas que arman su propio HTML como portal_alumno.php y
 * portal_docente.php — que hasta ahora usaban alert()/confirm() nativos
 * del navegador ("localhost dice..."), rompiendo la estética del resto
 * de la app. Requiere que SweetAlert2 ya esté cargado antes de este
 * script (assets/libs/sweetalert2.all.min.js).
 */
const Ibbs = {
  // Confirmación de acción (destructiva o no) — usar con `await` y
  // revisar `.isConfirmed` antes de proceder.
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

  // Error con detalle
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

  // Éxito — se cierra solo
  success(msg, title='¡Listo!') {
    return Swal.fire({
      title, html: msg, icon: 'success',
      timer: 2400, timerProgressBar: true, showConfirmButton: false,
      background: '#f5f0e8', color: '#1a4d2e',
      iconColor: '#16a34a',
      customClass: { popup:'ibbs-swal', title:'ibbs-swal-title' },
      buttonsStyling: false,
    });
  },

  // Advertencia/información simple, con un solo botón
  warn(msg, title='Atención') {
    return Swal.fire({
      title, html: msg, icon: 'warning',
      confirmButtonText: 'Entendido',
      background: '#f5f0e8', color: '#1a4d2e',
      iconColor: '#f59e0b',
      confirmButtonColor: '#1a4d2e',
      customClass: { popup:'ibbs-swal', title:'ibbs-swal-title', confirmButton:'ibbs-swal-btn-ok' },
      buttonsStyling: false,
    });
  },

  // Aviso pequeño en la esquina, se cierra solo — para cosas que no
  // interrumpen lo que el usuario está haciendo (ej. una notificación
  // que acaba de llegar), a diferencia de success()/warn()/error()
  // que son un modal centrado que hay que mirar.
  // `msg` va como texto plano (nunca HTML) a propósito: suele ser el
  // título de una notificación, texto libre que puede venir de lo que
  // otro usuario escribió (ej. un mensaje de foro) — jamás confiar en
  // eso como HTML. `icon` es solo un emoji de un mapa fijo, no dato
  // de usuario, así que ese sí puede ir en el html del ícono.
  toast(msg, icon='ℹ️') {
    return Swal.mixin({
      toast: true,
      position: 'top-end',
      showConfirmButton: false,
      timer: 4500,
      timerProgressBar: true,
      background: '#f5f0e8', color: '#1a4d2e',
      customClass: { popup: 'ibbs-swal' },
    }).fire({ text: icon + '  ' + msg });
  },
};

// Mismo look & feel de los botones/las burbujas que ya usa layout/foot.php
if (!document.getElementById('ibbs-swal-styles')) {
  const style = document.createElement('style');
  style.id = 'ibbs-swal-styles';
  style.textContent = `
    .ibbs-swal { font-family: 'Nunito', 'Inter', sans-serif !important; border-radius: 16px !important; border: 1.5px solid rgba(26,77,46,.1) !important; box-shadow: 0 20px 60px rgba(0,0,0,.2) !important; }
    .ibbs-swal-title { font-family: 'Playfair Display', 'DM Serif Display', serif !important; font-size: 1.25rem !important; color: #1a4d2e !important; font-weight: 400 !important; }
    .ibbs-swal .swal2-html-container { font-size: .88rem !important; color: #4a5c4b !important; line-height: 1.6 !important; }
    .ibbs-swal .swal2-actions { gap: .6rem !important; }
    .ibbs-swal-btn-ok, .ibbs-swal-btn-danger, .ibbs-swal-btn-cancel { padding: .65rem 1.4rem !important; border-radius: 10px !important; font-weight: 700 !important; font-size: .85rem !important; cursor: pointer !important; border: none !important; transition: all .15s !important; }
    .ibbs-swal-btn-ok { background: #1a4d2e !important; color: #39ff14 !important; }
    .ibbs-swal-btn-ok:hover { background: #1e5c36 !important; }
    .ibbs-swal-btn-danger { background: #dc2626 !important; color: #fff !important; }
    .ibbs-swal-btn-danger:hover { background: #b91c1c !important; }
    .ibbs-swal-btn-cancel { background: transparent !important; color: #7a8c72 !important; border: 1.5px solid #e0d8c8 !important; }
    .ibbs-swal-btn-cancel:hover { background: #f5f0e8 !important; }
  `;
  document.head.appendChild(style);
}
