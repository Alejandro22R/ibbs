<?php
/**
 * IBBS — Notificaciones en tiempo real vía Server-Sent Events.
 *
 * Por qué SSE y no WebSockets: corre sobre HTTP normal, sin puerto ni
 * proceso aparte — funciona tal cual en un XAMPP/Apache compartido.
 * El navegador (EventSource) se reconecta solo si la conexión se
 * corta, así que no hace falta manejar reconexión a mano.
 *
 * Por qué la conexión dura ~25s y no para siempre: en Apache con
 * mod_php (el modo típico de XAMPP) cada conexión abierta ocupa un
 * worker del servidor completo. Mantener miles de conexiones SSE
 * abiertas indefinidamente agotaría el pool de workers y tumbaría el
 * resto del sitio. En vez de eso, este endpoint sondea la base cada
 * ~1s durante una ventana acotada y, si aparece algo, lo entrega al
 * instante; si no, la conexión se cierra sola al final de la ventana
 * y el navegador abre una nueva — el efecto para el usuario es el
 * mismo (push casi instantáneo) pero cada worker se libera pronto.
 *
 * El cliente manda ?since=<id> con el último id que ya vio (layout/foot.php).
 */

ob_start();
error_reporting(0);
require_once __DIR__.'/../config/bootstrap.php';

if (empty($_SESSION['loggedin'])) {
    ob_end_clean();
    http_response_code(401);
    header('Content-Type: text/event-stream');
    echo "event: error\ndata: sesion_expirada\n\n";
    exit;
}

$uid  = (int)($_SESSION['user_id'] ?? 0);
$rol  = $_SESSION['rol'] ?? 'profesor';
// El navegador reenvía Last-Event-ID solo en cada reconexión automática
// (a partir del último "id:" que vio) — así no hace falta que el JS
// lleve la cuenta manualmente. ?since= es solo para la primera conexión.
$since = (int)($_SERVER['HTTP_LAST_EVENT_ID'] ?? $_GET['since'] ?? 0);

// Libera el lock del archivo de sesión ANTES de entrar al sondeo largo:
// si no, esta conexión bloquearía cualquier otra pestaña/pedido del
// mismo usuario mientras dura (PHP solo permite un script a la vez
// escribiendo la misma sesión).
session_write_close();

ob_end_clean();
header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache, no-store');
header('X-Accel-Buffering: no'); // por si algún día hay un proxy nginx delante
header('Connection: keep-alive');
// Le dice al navegador cuánto esperar antes de reconectar si la conexión se corta sola.
echo "retry: 2000\n\n";
if (ob_get_level() === 0) ob_start();
flush();

$con = db();
if (!$con) {
    echo "event: error\ndata: error_bd\n\n";
    flush();
    exit;
}

$roles = notif_roles_aceptados($rol);
$st = mysqli_prepare($con, "SELECT id,tipo,titulo,mensaje,materia_id,creado_en
                             FROM notificaciones
                             WHERE id > ? AND (usuario_id = ? OR (usuario_id IS NULL AND para_rol IN (?,?,?)))
                             ORDER BY id ASC LIMIT 20");

set_time_limit(0);
$inicio = time();
$duracionMax = 25; // segundos por conexión — ver nota arriba

while (time() - $inicio < $duracionMax) {
    if (connection_aborted()) break;

    mysqli_stmt_bind_param($st, 'iisss', $since, $uid, $roles[0], $roles[1], $roles[2]);
    mysqli_stmt_execute($st);
    $r = mysqli_stmt_get_result($st);
    while ($f = mysqli_fetch_assoc($r)) {
        $since = max($since, (int)$f['id']);
        echo 'id: '.$f['id']."\n";
        echo 'data: '.json_encode($f)."\n\n";
    }
    flush();

    // Late a modo de "sigo viva" para que proxies/navegador no den la
    // conexión por muerta durante los tramos sin novedades.
    echo ": ping\n\n";
    flush();

    sleep(1);
}

echo "event: reconnect\ndata: fin_de_ventana\n\n";
