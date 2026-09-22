<?php
/**
 * IBBS — Avisar al servidor de WebSocket (ws-server/) que reenvíe un
 * evento a los clientes conectados.
 *
 * Esto es "fire and forget": si el VPS/proceso Node está caído, lento,
 * o directamente no está configurado (ws_enabled() === false), estas
 * funciones no deben poder tumbar ni retrasar el request de PHP que
 * las llama — por eso el timeout es cortísimo (300ms) y cualquier
 * error se traga en silencio. El dato real ya quedó guardado en MySQL
 * (notificaciones, foro_mensajes, etc.) antes de llamar a esto; el
 * WebSocket es solo un aviso para no tener que esperar al próximo
 * sondeo de SSE/polling.
 */

require_once __DIR__.'/ws_config.php';

if (!function_exists('ws_broadcast_raw')) {
    function ws_broadcast_raw(array $payload) {
        if (!ws_enabled()) return;
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $ctx = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/json\r\n"
                           . "X-IBBS-WS-Secret: ".ws_secret()."\r\n",
                'content' => $body,
                'timeout' => 0.3, // segundos — nunca bloquear la respuesta al usuario por esto
                'ignore_errors' => true,
            ],
        ]);
        // @ a propósito: un VPS caído no debe generar warnings en el log de cada request.
        @file_get_contents(rtrim(ws_internal_url(), '/').'/broadcast', false, $ctx);
    }
}

if (!function_exists('ws_broadcast_channel')) {
    /** Reenvía a todos los clientes suscritos a un canal (ej. "materia:12"). */
    function ws_broadcast_channel($channel, $event, $data) {
        ws_broadcast_raw(['channel' => $channel, 'event' => $event, 'data' => $data]);
    }
}

if (!function_exists('ws_broadcast_user')) {
    /** Reenvía solo a las conexiones autenticadas como este usuario_id. */
    function ws_broadcast_user($usuario_id, $event, $data) {
        if (!$usuario_id) return;
        ws_broadcast_raw(['uid' => (int)$usuario_id, 'event' => $event, 'data' => $data]);
    }
}
