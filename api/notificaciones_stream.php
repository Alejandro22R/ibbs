<?php
/**
 * IBBS — Notificaciones en tiempo real vía Server-Sent Events.
 *
 * Por qué SSE y no WebSockets: corre sobre HTTP normal, sin puerto ni
 * proceso aparte — funciona tal cual en un XAMPP/Apache compartido.
 * El navegador (EventSource) se reconecta solo si la conexión se
 * corta, así que no hace falta manejar reconexión a mano.
 *
 * Por qué la conexión dura ~40s y no para siempre: en Apache con
 * mod_php (el modo típico de XAMPP) cada conexión abierta ocupa un
 * worker del servidor completo. Mantener miles de conexiones SSE
 * abiertas indefinidamente agotaría el pool de workers y tumbaría el
 * resto del sitio. En vez de eso, este endpoint sondea la base cada
 * ~3s durante una ventana acotada y, si aparece algo, lo entrega al
 * instante; si no, la conexión se cierra sola al final de la ventana
 * y el navegador abre una nueva.
 *
 * Importante para dimensionar el servidor en producción: este
 * intervalo (3s) y la ventana (40s) bajan la carga de CPU/BD por
 * conexión, pero NO bajan cuántos procesos PHP están abiertos a la
 * vez — eso depende de cuántas pestañas están conectadas, no de cada
 * cuánto preguntan. El ahorro real de memoria viene de
 * layout/foot.php, que cierra la conexión cuando la pestaña pasa a
 * segundo plano (Page Visibility API) y la reabre al volver — así solo
 * las pestañas realmente activas ocupan un worker.
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
$duracionMax = 40;  // segundos por conexión — ver nota arriba
$intervalo   = 3;   // segundos entre sondeos a la BD — ver nota arriba

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

    sleep($intervalo);
}

echo "event: reconnect\ndata: fin_de_ventana\n\n";
