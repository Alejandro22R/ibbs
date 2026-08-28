<?php
/**
 * IBBS — Protección CSRF.
 * Requiere que la sesión ya esté iniciada (config/session.php).
 * El token se expone al frontend vía <meta name="csrf-token"> y se
 * envía en cada petición que cambia estado (ver layout/foot.php → ajax()).
 */

if (!function_exists('csrf_token')) {
    function csrf_token() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('csrf_verify')) {
    function csrf_verify($token) {
        return !empty($_SESSION['csrf_token'])
            && is_string($token)
            && $token !== ''
            && hash_equals($_SESSION['csrf_token'], $token);
    }
}

// Endpoint helper: corta la ejecución con un JSON de error si el token
// enviado por POST no coincide. Usar en los endpoints de api/*.php que
// reciben POST (acciones que modifican datos).
if (!function_exists('csrf_require_post')) {
    function csrf_require_post() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
        if (!csrf_verify($_POST['csrf_token'] ?? '')) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'msg' => 'Token de seguridad inválido o expirado. Recarga la página e intenta de nuevo.']);
            exit;
        }
    }
}
