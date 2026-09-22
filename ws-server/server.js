/**
 * IBBS — Servidor de WebSocket (foro/chat y notificaciones en vivo).
 *
 * Corre APARTE de Apache/PHP, en el VPS. No toca MySQL ni sesiones de
 * PHP directamente:
 *
 *   1. El navegador se conecta acá por WebSocket y manda
 *      {type:'auth', token} — el token lo generó PHP
 *      (config/ws_token.php), firmado con el mismo WS_SECRET que este
 *      proceso, con la lista de "canales" (materia:<id>) a los que ese
 *      usuario ya tiene permiso real (materia_puede_ver ya se validó
 *      en PHP antes de firmar el token). Este servidor solo verifica
 *      la firma — nunca vuelve a preguntarle nada a PHP ni a la BD.
 *   2. Cuando algo pasa en el sistema (nuevo mensaje de foro, nueva
 *      notificación), PHP ya guardó el dato real en MySQL y le pega un
 *      POST interno a este proceso en /broadcast (autenticado con el
 *      mismo secreto, header X-IBBS-WS-Secret) pidiendo que avise a
 *      los clientes conectados a ese canal o a ese usuario_id.
 *   3. Este servidor NUNCA es la fuente de verdad de los datos — solo
 *      empuja un aviso para que el navegador refresque al instante en
 *      vez de esperar el próximo sondeo de SSE/polling. Si este
 *      proceso está caído, el sistema entero sigue funcionando igual
 *      que en un XAMPP sin VPS (ver config/ws_config.php del lado PHP).
 */

require('dotenv').config();

const http = require('http');
const crypto = require('crypto');
const { WebSocketServer } = require('ws');

const PORT = parseInt(process.env.WS_PORT || '8081', 10);
const SECRET = process.env.WS_SECRET || '';
const ALLOWED_ORIGIN = (process.env.WS_ALLOWED_ORIGIN || '').trim();

if (!SECRET) {
  console.error('[ibbs-ws] Falta WS_SECRET en el .env — el servidor no puede firmar/verificar nada sin esto.');
  process.exit(1);
}

// ── Verificación del token que manda el navegador (mismo formato que
//    config/ws_token.php: base64url(json) + "." + hmac_sha256_hex) ──
function verifyToken(token) {
  if (typeof token !== 'string' || !token.includes('.')) return null;
  const idx = token.lastIndexOf('.');
  const b64 = token.slice(0, idx);
  const sig = token.slice(idx + 1);
  const expected = crypto.createHmac('sha256', SECRET).update(b64).digest('hex');
  const sigBuf = Buffer.from(sig, 'hex');
  const expBuf = Buffer.from(expected, 'hex');
  if (sigBuf.length !== expBuf.length || !crypto.timingSafeEqual(sigBuf, expBuf)) return null;

  try {
    const json = Buffer.from(b64.replace(/-/g, '+').replace(/_/g, '/'), 'base64').toString('utf8');
    const payload = JSON.parse(json);
    if (!payload || typeof payload !== 'object') return null;
    if (!payload.exp || Date.now() / 1000 > payload.exp) return null;
    if (!payload.uid) return null;
    return payload;
  } catch (e) {
    return null;
  }
}

// ── Estado en memoria: quién está en qué canal / bajo qué usuario ──
const channelSubscribers = new Map(); // channel -> Set<ws>
const userSockets = new Map();        // uid -> Set<ws>

function joinSet(map, key, ws) {
  if (!map.has(key)) map.set(key, new Set());
  map.get(key).add(ws);
}
function leaveSet(map, key, ws) {
  const set = map.get(key);
  if (!set) return;
  set.delete(ws);
  if (set.size === 0) map.delete(key);
}
function cleanupSocket(ws) {
  if (ws._ibbsUid) leaveSet(userSockets, ws._ibbsUid, ws);
  (ws._ibbsCanales || []).forEach((ch) => leaveSet(channelSubscribers, ch, ws));
}

function sendEvent(ws, event, data) {
  if (ws.readyState !== ws.OPEN) return;
  try { ws.send(JSON.stringify({ type: 'event', event, data })); } catch (e) {}
}

// ── HTTP: solo sirve el endpoint interno /broadcast (llamado por PHP,
//    nunca directamente por el navegador) ──
const server = http.createServer((req, res) => {
  if (req.method === 'POST' && req.url === '/broadcast') {
    if (req.headers['x-ibbs-ws-secret'] !== SECRET) {
      res.writeHead(401, { 'Content-Type': 'application/json' });
      res.end(JSON.stringify({ ok: false, error: 'secreto inválido' }));
      return;
    }
    let body = '';
    req.on('data', (chunk) => { body += chunk; if (body.length > 1e6) req.destroy(); });
    req.on('end', () => {
      let payload;
      try { payload = JSON.parse(body || '{}'); } catch (e) {
        res.writeHead(400, { 'Content-Type': 'application/json' });
        res.end(JSON.stringify({ ok: false, error: 'JSON inválido' }));
        return;
      }
      const { channel, uid, event, data } = payload;
      let entregados = 0;
      if (channel && channelSubscribers.has(channel)) {
        channelSubscribers.get(channel).forEach((ws) => { sendEvent(ws, event, data); entregados++; });
      }
      if (uid && userSockets.has(uid)) {
        userSockets.get(uid).forEach((ws) => { sendEvent(ws, event, data); entregados++; });
      }
      res.writeHead(200, { 'Content-Type': 'application/json' });
      res.end(JSON.stringify({ ok: true, entregados }));
    });
    return;
  }

  if (req.method === 'GET' && req.url === '/health') {
    res.writeHead(200, { 'Content-Type': 'application/json' });
    res.end(JSON.stringify({ ok: true, canales: channelSubscribers.size, usuarios: userSockets.size }));
    return;
  }

  res.writeHead(404, { 'Content-Type': 'text/plain' });
  res.end('Not found');
});

// ── WebSocket ──
const wss = new WebSocketServer({
  server,
  path: '/ws',
  verifyClient: (info, done) => {
    if (!ALLOWED_ORIGIN) return done(true);
    done(info.origin === ALLOWED_ORIGIN);
  },
});

wss.on('connection', (ws) => {
  ws._ibbsUid = null;
  ws._ibbsCanales = [];
  ws.isAlive = true;
  ws.on('pong', () => { ws.isAlive = true; });

  ws.on('message', (raw) => {
    let msg;
    try { msg = JSON.parse(raw.toString()); } catch (e) { return; }
    if (!msg || typeof msg !== 'object') return;

    if (msg.type === 'auth') {
      const payload = verifyToken(msg.token);
      if (!payload) {
        ws.send(JSON.stringify({ type: 'error', message: 'token inválido o expirado' }));
        ws.close();
        return;
      }
      ws._ibbsUid = payload.uid;
      ws._ibbsCanales = Array.isArray(payload.canales) ? payload.canales : [];
      joinSet(userSockets, ws._ibbsUid, ws);
      ws._ibbsCanales.forEach((ch) => joinSet(channelSubscribers, ch, ws));
      ws.send(JSON.stringify({ type: 'event', event: 'auth_ok', data: { canales: ws._ibbsCanales } }));
    }
    // No hay más tipos de mensaje del cliente por ahora — todo lo que
    // el navegador necesita ya viene decidido en el token (canales
    // permitidos), así que no hace falta un "subscribe" aparte.
  });

  ws.on('close', () => cleanupSocket(ws));
  ws.on('error', () => cleanupSocket(ws));
});

// ── Heartbeat: cierra sockets zombis (clientes que se fueron sin
//    avisar — wifi cortado, notebook cerrada, etc.) para no acumular
//    conexiones muertas en un VPS que corre semanas sin reiniciar. ──
const HEARTBEAT_MS = 30000;
const heartbeat = setInterval(() => {
  wss.clients.forEach((ws) => {
    if (ws.isAlive === false) { cleanupSocket(ws); return ws.terminate(); }
    ws.isAlive = false;
    try { ws.ping(); } catch (e) {}
  });
}, HEARTBEAT_MS);

wss.on('close', () => clearInterval(heartbeat));

server.listen(PORT, () => {
  console.log(`[ibbs-ws] escuchando en :${PORT} (WebSocket en /ws, avisos internos en /broadcast)`);
});
