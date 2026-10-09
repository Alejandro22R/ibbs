<?php
/**
 * IBBS — Freno a fuerza bruta en login (sin tocar la base de datos).
 * Guarda intentos fallidos por IP+usuario en un archivo local.
 * Ventana: 15 minutos · Límite: 5 intentos fallidos.
 */

define('IBBS_THROTTLE_MAX', 5);
define('IBBS_THROTTLE_WINDOW', 900); // segundos

function _ibbs_throttle_file() {
    $dir = __DIR__ . '/../storage';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    return $dir . '/login_throttle.json';
}

function _ibbs_throttle_load() {
    $file = _ibbs_throttle_file();
    if (!is_file($file)) return [];
    $raw = @file_get_contents($file);
    $data = $raw ? json_decode($raw, true) : null;
    return is_array($data) ? $data : [];
}

function _ibbs_throttle_save($data) {
    @file_put_contents(_ibbs_throttle_file(), json_encode($data), LOCK_EX);
}

function _ibbs_throttle_key() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $u  = strtolower(trim($_POST['usuario'] ?? ''));
    return $ip . '|' . $u;
}

/** true si el usuario/IP debe ser bloqueado por demasiados intentos fallidos */
function login_throttle_blocked() {
    $data = _ibbs_throttle_load();
    $key  = _ibbs_throttle_key();
    $now  = time();
    $entry = $data[$key] ?? null;
    if (!$entry) return false;
    if ($now - $entry['first'] > IBBS_THROTTLE_WINDOW) return false; // ventana expirada
    return $entry['count'] >= IBBS_THROTTLE_MAX;
}

/** registra un intento fallido */
function login_throttle_fail() {
    $data = _ibbs_throttle_load();
    $key  = _ibbs_throttle_key();
    $now  = time();
    $entry = $data[$key] ?? null;
    if (!$entry || $now - $entry['first'] > IBBS_THROTTLE_WINDOW) {
        $entry = ['count' => 0, 'first' => $now];
    }
    $entry['count']++;
    $data[$key] = $entry;
    // limpieza best-effort de entradas viejas para no crecer sin límite
    foreach ($data as $k => $v) {
        if ($now - $v['first'] > IBBS_THROTTLE_WINDOW) unset($data[$k]);
    }
    _ibbs_throttle_save($data);
}

/** limpia el contador tras un login exitoso */
function login_throttle_reset() {
    $data = _ibbs_throttle_load();
    unset($data[_ibbs_throttle_key()]);
    _ibbs_throttle_save($data);
}

/**
 * Freno genérico por IP para acciones sensibles que no son el login en
 * sí (por ahora: pedir un enlace de recuperación por correo). Sin
 * esto, cualquiera podría hacer que el sistema mande decenas de
 * correos por minuto a la bandeja de otra persona con solo repetir el
 * formulario — molesto para la víctima y puede hacer que el dominio
 * termine marcado como spam.
 */
define('IBBS_RESET_THROTTLE_MAX', 4);
define('IBBS_RESET_THROTTLE_WINDOW', 900); // 15 minutos

function _ibbs_reset_throttle_file() {
    $dir = __DIR__ . '/../storage';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    return $dir . '/reset_email_throttle.json';
}
function _ibbs_reset_throttle_load() {
    $file = _ibbs_reset_throttle_file();
    if (!is_file($file)) return [];
    $raw = @file_get_contents($file);
    $data = $raw ? json_decode($raw, true) : null;
    return is_array($data) ? $data : [];
}
function _ibbs_reset_throttle_save($data) {
    @file_put_contents(_ibbs_reset_throttle_file(), json_encode($data), LOCK_EX);
}
function reset_email_throttle_blocked() {
    $data = _ibbs_reset_throttle_load();
    $key  = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $now  = time();
    $entry = $data[$key] ?? null;
    if (!$entry) return false;
    if ($now - $entry['first'] > IBBS_RESET_THROTTLE_WINDOW) return false;
    return $entry['count'] >= IBBS_RESET_THROTTLE_MAX;
}
function reset_email_throttle_hit() {
    $data = _ibbs_reset_throttle_load();
    $key  = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $now  = time();
    $entry = $data[$key] ?? null;
    if (!$entry || $now - $entry['first'] > IBBS_RESET_THROTTLE_WINDOW) {
        $entry = ['count' => 0, 'first' => $now];
    }
    $entry['count']++;
    $data[$key] = $entry;
    foreach ($data as $k => $v) {
        if ($now - $v['first'] > IBBS_RESET_THROTTLE_WINDOW) unset($data[$k]);
    }
    _ibbs_reset_throttle_save($data);
}

/**
 * Freno a la recuperación por preguntas de seguridad (login.php
 * rec_cedula/rec_verificar). A diferencia del login normal, acá no había
 * ningún límite: se podía probar cédula tras cédula (rec_cedula revela
 * las preguntas de seguridad de cualquier cédula válida) y, peor,
 * adivinar las dos respuestas de un usuario concreto sin límite de
 * intentos — el único freno era la lentitud de password_verify().
 *
 * rec_cedula se frena por IP (evita barrer cédulas). rec_verificar se
 * frena por usuario (uid) además de por IP, porque lo que importa
 * proteger es la cuenta objetivo, sin importar desde cuántas IPs
 * distintas venga el ataque.
 */
define('IBBS_REC_THROTTLE_MAX', 8);
define('IBBS_REC_THROTTLE_WINDOW', 900); // 15 minutos

function _ibbs_rec_throttle_file() {
    $dir = __DIR__ . '/../storage';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    return $dir . '/rec_throttle.json';
}
function _ibbs_rec_throttle_load() {
    $file = _ibbs_rec_throttle_file();
    if (!is_file($file)) return [];
    $raw = @file_get_contents($file);
    $data = $raw ? json_decode($raw, true) : null;
    return is_array($data) ? $data : [];
}
function _ibbs_rec_throttle_save($data) {
    @file_put_contents(_ibbs_rec_throttle_file(), json_encode($data), LOCK_EX);
}
function _ibbs_rec_throttle_blocked($key, $max) {
    $data = _ibbs_rec_throttle_load();
    $now  = time();
    $entry = $data[$key] ?? null;
    if (!$entry) return false;
    if ($now - $entry['first'] > IBBS_REC_THROTTLE_WINDOW) return false;
    return $entry['count'] >= $max;
}
function _ibbs_rec_throttle_hit($key) {
    $data = _ibbs_rec_throttle_load();
    $now  = time();
    $entry = $data[$key] ?? null;
    if (!$entry || $now - $entry['first'] > IBBS_REC_THROTTLE_WINDOW) {
        $entry = ['count' => 0, 'first' => $now];
    }
    $entry['count']++;
    $data[$key] = $entry;
    foreach ($data as $k => $v) {
        if ($now - $v['first'] > IBBS_REC_THROTTLE_WINDOW) unset($data[$k]);
    }
    _ibbs_rec_throttle_save($data);
}
function _ibbs_rec_throttle_reset($key) {
    $data = _ibbs_rec_throttle_load();
    unset($data[$key]);
    _ibbs_rec_throttle_save($data);
}

/** true si esta IP ya pidió demasiadas cédulas (rec_cedula) */
function rec_cedula_throttle_blocked() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    return _ibbs_rec_throttle_blocked('ced|'.$ip, IBBS_REC_THROTTLE_MAX);
}
function rec_cedula_throttle_hit() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    _ibbs_rec_throttle_hit('ced|'.$ip);
}

/** true si ya se agotaron los intentos de respuesta para este usuario o IP */
function rec_verificar_throttle_blocked($uid) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    return _ibbs_rec_throttle_blocked('uid|'.$uid, IBBS_THROTTLE_MAX)
        || _ibbs_rec_throttle_blocked('ip|'.$ip, IBBS_REC_THROTTLE_MAX);
}
function rec_verificar_throttle_fail($uid) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    _ibbs_rec_throttle_hit('uid|'.$uid);
    _ibbs_rec_throttle_hit('ip|'.$ip);
}
function rec_verificar_throttle_reset($uid) {
    _ibbs_rec_throttle_reset('uid|'.$uid);
}
