<?php
/**
 * IBBS — Chat general del staff (admin/superadmin/profesor), sin
 * materia. Para avisos de dirección o preguntas directas al staff,
 * sin tener que entrar a una materia puntual. Requiere la tabla
 * `chat_staff` (database/migrations/010_chat_staff.sql).
 *
 * Mismo patrón que api/foro.php: get_mensajes de solo lectura,
 * post_mensaje/delete_mensaje exigen CSRF (viaja en el JSON del body,
 * no en $_POST). El alumno nunca tiene acceso a este canal.
 */

ob_start();
error_reporting(0);
require_once __DIR__.'/../config/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
ob_clean();

if (empty($_SESSION['loggedin'])) {
    echo json_encode(['error' => 'Sesión expirada.']); exit;
}

$uid  = (int)($_SESSION['user_id'] ?? 0);
$_rol = $_SESSION['rol'] ?? 'profesor';
$usuarioNombre = $_SESSION['usuario'] ?? 'Usuario';

if (!in_array($_rol, ['superadmin', 'admin', 'profesor'])) {
    echo json_encode(['error' => 'Este chat es solo para administración y docentes.']); exit;
}

$con = db();
if (!$con) { echo json_encode(['error' => 'Error de conexión a la base de datos.']); exit; }

$action = $_GET['action'] ?? '';

/* ════ OBTENER MENSAJES (solo lectura) ═══════════════════════════ */
if ($action === 'get_mensajes') {
    // DESC + LIMIT trae los últimos 500 (no los primeros 500 — con ASC
    // el hilo se hubiera quedado congelado en los mensajes más viejos
    // apenas la tabla pasara de 500 filas); se reordena ascendente acá.
    $st = mysqli_prepare($con, "SELECT id,usuario_id,usuario_nombre,rol,mensaje,respuesta_a,fecha FROM chat_staff ORDER BY fecha DESC LIMIT 500");
    mysqli_stmt_execute($st);
    $r = mysqli_stmt_get_result($st);
    // Moderación: admin/superadmin siempre; un profesor solo su propio mensaje.
    $puedeModerar = in_array($_rol, ['superadmin', 'admin']);
    $mensajes = [];
    while ($f = mysqli_fetch_assoc($r)) {
        $f['usuario_id'] = $f['usuario_id'] !== null ? (int)$f['usuario_id'] : null;
        $f['puede_borrar'] = $puedeModerar || ($f['usuario_id'] !== null && $f['usuario_id'] === $uid);
        $mensajes[] = $f;
    }
    $mensajes = array_reverse($mensajes);
    echo json_encode($mensajes); exit;
}

/* ════ PUBLICAR MENSAJE ═══════════════════════════════════════════ */
if ($action === 'post_mensaje' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true) ?: [];

    if (!csrf_verify($data['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'error' => 'Token de seguridad inválido. Recarga la página.']); exit;
    }

    $mensaje = trim($data['mensaje'] ?? '');
    $respuestaA = !empty($data['respuesta_a']) ? (int)$data['respuesta_a'] : null;
    if ($mensaje === '') { echo json_encode(['success' => false, 'error' => 'Mensaje vacío']); exit; }
    if (mb_strlen($mensaje) > 2000) { echo json_encode(['success' => false, 'error' => 'El mensaje es demasiado largo (máx. 2000 caracteres).']); exit; }

    if ($respuestaA) {
        $stR = mysqli_prepare($con, "SELECT id FROM chat_staff WHERE id=? LIMIT 1");
        mysqli_stmt_bind_param($stR, 'i', $respuestaA);
        mysqli_stmt_execute($stR);
        if (!mysqli_fetch_row(mysqli_stmt_get_result($stR))) $respuestaA = null;
    }

    $st = mysqli_prepare($con, "INSERT INTO chat_staff(usuario_id,usuario_nombre,rol,mensaje,respuesta_a) VALUES(?,?,?,?,?)");
    mysqli_stmt_bind_param($st, 'isssi', $uid, $usuarioNombre, $_rol, $mensaje, $respuestaA);
    if (!mysqli_stmt_execute($st)) { echo json_encode(['success' => false, 'error' => 'No se pudo publicar el mensaje.']); exit; }

    $nuevoId = mysqli_insert_id($con);
    log_audit($con, $uid, 'CHAT_STAFF_MENSAJE', "id=$nuevoId");

    // Notifica al resto del staff (admin/superadmin y profesores),
    // salvo a quien lo escribió.
    $resumen = mb_strlen($mensaje) > 80 ? mb_substr($mensaje, 0, 80).'…' : $mensaje;
    $rDest = mysqli_query($con, "SELECT id FROM usuarios WHERE rol IN ('superadmin','admin','profesor') AND activo=1 AND id!=$uid");
    while ($fDest = mysqli_fetch_assoc($rDest)) {
        notificar_usuario($con, (int)$fDest['id'], 'chat_staff', "$usuarioNombre en el chat del staff", $resumen);
    }
    ws_broadcast_channel('staff', 'chat_staff_mensaje', ['id' => $nuevoId]);

    echo json_encode(['success' => true, 'id' => $nuevoId]); exit;
}

/* ════ BORRAR MENSAJE ═══════════════════════════════════════════ */
if ($action === 'delete_mensaje' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true) ?: [];

    if (!csrf_verify($data['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'error' => 'Token de seguridad inválido. Recarga la página.']); exit;
    }

    $msgId = (int)($data['id'] ?? 0);
    if (!$msgId) { echo json_encode(['success' => false, 'error' => 'Mensaje no válido']); exit; }

    $st = mysqli_prepare($con, "SELECT id,usuario_id FROM chat_staff WHERE id=? LIMIT 1");
    mysqli_stmt_bind_param($st, 'i', $msgId);
    mysqli_stmt_execute($st);
    $fila = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
    if (!$fila) { echo json_encode(['success' => false, 'error' => 'Mensaje no encontrado']); exit; }

    $esAutor  = $fila['usuario_id'] !== null && (int)$fila['usuario_id'] === $uid;
    $esModera = in_array($_rol, ['superadmin', 'admin']);
    if (!$esAutor && !$esModera) {
        echo json_encode(['success' => false, 'error' => 'No tenés permiso para borrar este mensaje.']); exit;
    }

    $stD = mysqli_prepare($con, "DELETE FROM chat_staff WHERE id=?");
    mysqli_stmt_bind_param($stD, 'i', $msgId);
    if (!mysqli_stmt_execute($stD)) { echo json_encode(['success' => false, 'error' => 'No se pudo borrar el mensaje.']); exit; }

    log_audit($con, $uid, 'CHAT_STAFF_BORRAR', "msg=$msgId".($esAutor?'':' (moderación)'));
    ws_broadcast_channel('staff', 'chat_staff_mensaje', ['id' => $msgId]);
    echo json_encode(['success' => true]); exit;
}

echo json_encode(['error' => 'Acción no válida']);
