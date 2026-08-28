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
