<?php
/**
 * IBBS — Tokens firmados para autenticar la conexión WebSocket.
 *
 * El servidor Node (ws-server/) no tiene acceso a la base de datos ni
 * a las sesiones de PHP — así que en vez de eso, PHP arma un token
 * firmado (HMAC-SHA256 con el secreto de config/ws_config.php) cada
 * vez que renderiza una página, con quién es el usuario y a qué
 * "canales" tiene permiso de unirse (ya validado acá, con
 * materia_puede_ver()). Node solo verifica la firma — nunca vuelve a
 * preguntarle nada a PHP ni a MySQL por cada conexión.
 *
 * Formato del token: base64url(json) + "." + hmac_sha256(esa parte)
 * Expira solo (campo "exp"); no hay forma de revocarlo antes salvo
 * rotar IBBS_WS_SECRET — para esta app (un campus, no un banco) es
 * una desventaja aceptable a cambio de no depender de una base
 * compartida entre PHP y Node.
 */

require_once __DIR__.'/ws_config.php';

if (!function_exists('ws_token_mint')) {
    function ws_token_mint($uid, $rol, $usuario, array $canales = [], $ttlSegundos = 21600) {
        if (!ws_enabled()) return null;
        $payload = [
            'uid'     => (int)$uid,
            'rol'     => (string)$rol,
            'usuario' => (string)$usuario,
            'canales' => array_values(array_unique(array_map('strval', $canales))),
            'exp'     => time() + max(60, (int)$ttlSegundos),
        ];
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $b64  = rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
        $sig  = hash_hmac('sha256', $b64, ws_secret());
        return $b64.'.'.$sig;
    }
}

if (!function_exists('ws_token_for_materia')) {
    /**
     * Token de página para un módulo que muestra el foro/chat de UNA
     * materia puntual (modulo_aula.php). Solo incluye el canal de esa
     * materia si el usuario realmente tiene permiso de verla — igual
     * que ya exige api/foro.php en cada request.
     */
    function ws_token_for_materia($con, $uid, $rol, $usuario, $materia_id) {
        $canales = [];
        if ($materia_id && function_exists('materia_puede_ver') && materia_puede_ver($con, $uid, $rol, $materia_id)) {
            $canales[] = 'materia:'.(int)$materia_id;
        }
        return ws_token_mint($uid, $rol, $usuario, $canales);
    }
}

if (!function_exists('ws_token_for_materias')) {
    /**
     * Igual que ws_token_for_materia, pero para páginas cuyo chat
     * cambia de materia sin recargar la página (portal_alumno.php,
     * portal_docente.php — el selector de materias del chat es JS
     * puro). Se le pasa la lista de IDs que la propia página ya
     * resolvió como "las materias de este usuario" y cada una se
     * revalida igual con materia_puede_ver() antes de entrar al token.
     */
    function ws_token_for_materias($con, $uid, $rol, $usuario, array $materiaIds) {
        $canales = [];
        foreach ($materiaIds as $mid) {
            $mid = (int)$mid;
            if ($mid && function_exists('materia_puede_ver') && materia_puede_ver($con, $uid, $rol, $mid)) {
                $canales[] = 'materia:'.$mid;
            }
        }
        return ws_token_mint($uid, $rol, $usuario, $canales);
    }
}
