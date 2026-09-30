/**
 * IBBS — Cliente de WebSocket en vivo (opcional).
 *
 * Se activa SOLO si la página trae <meta name="ibbs-ws-url"> y
 * <meta name="ibbs-ws-token"> (los pone config/ws_token.php cuando el
 * VPS con ws-server/ está configurado — ver config/ws_config.php). Si
 * no están, `window.IbbsRT` queda como un objeto "vacío" que no hace
 * nada: el resto del código puede llamar a IbbsRT.on(...) sin
 * preguntar antes si el WebSocket existe, y en un XAMPP sin VPS
 * simplemente no pasa nada — el sistema sigue con SSE/polling como
 * siempre.
 *
 * Uso desde cualquier página:
 *   IbbsRT.on('foro_mensaje', (data) => { ...refrescar el chat... });
 *   IbbsRT.on('notificacion', (data) => { ...mostrar toast... });
 *   IbbsRT.hasWs   // true si esta página tiene WebSocket configurado
 */
(function (window, document) {
  'use strict';

  function noopClient() {
    return {
      hasWs: false,
      connected: false,
      on: function () {},
      off: function () {},
      close: function () {},
    };
  }

  var urlMeta = document.querySelector('meta[name="ibbs-ws-url"]');
  var tokenMeta = document.querySelector('meta[name="ibbs-ws-token"]');
  var wsUrl = urlMeta ? urlMeta.content.trim() : '';
  var wsToken = tokenMeta ? tokenMeta.content.trim() : '';

  if (!wsUrl || !wsToken || typeof WebSocket === 'undefined') {
    window.IbbsRT = noopClient();
    return;
  }

  var socket = null;
  var listeners = {};
  var reconnectDelayMs = 1000;
  var maxReconnectDelayMs = 20000;
  var closedByUser = false;
  var reconnectTimer = null;

  function emit(event, data) {
    (listeners[event] || []).forEach(function (fn) {
      try { fn(data); } catch (e) { console.error('[IbbsRT] listener error:', e); }
    });
  }

  function scheduleReconnect() {
    if (closedByUser) return;
    clearTimeout(reconnectTimer);
    reconnectTimer = setTimeout(connect, reconnectDelayMs);
    reconnectDelayMs = Math.min(reconnectDelayMs * 1.7, maxReconnectDelayMs);
  }

  function connect() {
    if (closedByUser) return;
    try {
      socket = new WebSocket(wsUrl);
    } catch (e) {
      scheduleReconnect();
      return;
    }

    socket.onopen = function () {
      reconnectDelayMs = 1000;
      try { socket.send(JSON.stringify({ type: 'auth', token: wsToken })); } catch (e) {}
      emit('open', null);
    };

    socket.onmessage = function (ev) {
      var msg;
      try { msg = JSON.parse(ev.data); } catch (e) { return; }
      if (!msg || typeof msg !== 'object') return;
      if (msg.type === 'event') emit(msg.event, msg.data);
      else if (msg.type === 'error') console.warn('[IbbsRT]', msg.message || 'error del servidor');
    };

    socket.onclose = function () {
      emit('close', null);
      scheduleReconnect();
    };

    socket.onerror = function () {
      try { socket.close(); } catch (e) {}
    };
  }

  // Pausar la conexión con la pestaña en segundo plano evita sostener
  // sockets abiertos de pestañas que nadie está mirando — igual que ya
  // hace layout/foot.php con el EventSource de SSE.
  document.addEventListener('visibilitychange', function () {
    if (document.hidden) {
      if (socket) { try { socket.close(); } catch (e) {} }
    } else if (!socket || socket.readyState === WebSocket.CLOSED) {
      connect();
    }
  });

  connect();

  window.IbbsRT = {
    hasWs: true,
    get connected() { return !!socket && socket.readyState === WebSocket.OPEN; },
    on: function (event, fn) {
      (listeners[event] = listeners[event] || []).push(fn);
    },
    off: function (event, fn) {
      if (!listeners[event]) return;
      listeners[event] = listeners[event].filter(function (f) { return f !== fn; });
    },
    close: function () {
      closedByUser = true;
      clearTimeout(reconnectTimer);
      if (socket) { try { socket.close(); } catch (e) {} }
    },
  };
})(window, document);
