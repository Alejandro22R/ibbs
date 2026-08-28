<?php
/**
 * IBBS — Sesiones seguras.
 * Configura las cookies de sesión ANTES de iniciarla (por eso este
 * archivo debe requerirse en lugar de llamar session_start() directo).
 */

if (session_status() === PHP_SESSION_NONE) {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ($_SERVER['SERVER_PORT'] ?? '') == 443
        || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

    ini_set('session.use_strict_mode', '1');

    session_set_cookie_params([
        'lifetime' => 0,        // expira al cerrar el navegador
        'path'     => '/',
        'domain'   => '',
        'secure'   => $https,   // solo por HTTPS cuando el sitio corre con HTTPS
        'httponly' => true,     // no accesible desde JavaScript
        'samesite' => 'Lax',    // mitiga CSRF cross-site básico
    ]);

    session_start();
}
