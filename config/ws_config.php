<?php
/**
 * IBBS — Configuración del servidor de WebSocket (VPS aparte).
 *
 * Todo esto es OPCIONAL y aditivo: si no se definen las variables de
 * entorno, `ws_enabled()` devuelve false y el resto del sistema sigue
 * funcionando exactamente igual que antes (SSE + polling), como
 * cuando corre en un XAMPP sin VPS. Nada rompe si esto no está
 * configurado.
 *
 * Variables de entorno:
 *   IBBS_WS_URL           URL pública que usa el navegador para conectar
 *                         (ej. wss://ws.tudominio.com/ws). Sin esta,
 *                         el WebSocket queda desactivado.
 *   IBBS_WS_INTERNAL_URL  URL que usa PHP (server-to-server) para avisarle
 *                         al proceso Node que reenvíe un evento —
 *                         normalmente http://127.0.0.1:8081 si Node
 *                         corre en la misma máquina que Apache/PHP.
 *                         Por defecto: http://127.0.0.1:8081
 *   IBBS_WS_SECRET        Secreto compartido entre PHP y ws-server/
 *                         (HMAC de los tokens + autenticación del
 *                         endpoint interno /broadcast). Sin esto,
 *                         tampoco se activa nada.
 */

if (!function_exists('ws_public_url')) {
    function ws_public_url() {
        return trim(getenv('IBBS_WS_URL') ?: '');
    }
}

if (!function_exists('ws_internal_url')) {
    function ws_internal_url() {
        return trim(getenv('IBBS_WS_INTERNAL_URL') ?: 'http://127.0.0.1:8081');
    }
}

if (!function_exists('ws_secret')) {
    function ws_secret() {
        return getenv('IBBS_WS_SECRET') ?: '';
    }
}

if (!function_exists('ws_enabled')) {
    /** true solo si hay URL pública Y secreto — sin ambos no tiene sentido activar nada. */
    function ws_enabled() {
        return ws_public_url() !== '' && ws_secret() !== '';
    }
}
