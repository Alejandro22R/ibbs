<?php
/**
 * IBBS — Foro / Dudas por materia (endpoint), usado desde la pestaña
 * "Foro" de modulo_aula.php. Requiere la tabla `foro_mensajes`.
 *
 * get_mensajes es de solo lectura (GET, sin CSRF). post_mensaje
 * modifica datos: exige sesión + permiso de ver la materia + token
 * CSRF — como este endpoint recibe el cuerpo como JSON crudo (no un
 * POST de formulario), el token viaja dentro de ese mismo JSON en vez
 * de $_POST, así que se valida a mano con csrf_verify().
 */

ob_start();
error_reporting(0);
require_once __DIR__.'/../config/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
ob_clean();

if (empty($_SESSION['loggedin'])) {
    echo json_encode(['error' => 'Sesión expirada.']); exit;
}

$con = db();
if (!$con) { echo json_encode(['error' => 'Error de conexión a la base de datos.']); exit; }

$uid  = (int)($_SESSION['user_id'] ?? 0);
$_rol = $_SESSION['rol'] ?? 'profesor';
$usuarioNombre = $_SESSION['usuario'] ?? 'Usuario';

$materia_id = (int)($_GET['materia_id'] ?? 0);
if (!$materia_id) { echo json_encode(['error' => 'Materia no válida']); exit; }
if (!materia_puede_ver($con, $uid, $_rol, $materia_id)) { echo json_encode(['error' => 'Sin permiso sobre esta materia.']); exit; }

$action = $_GET['action'] ?? '';

/* ════ OBTENER MENSAJES (solo lectura) ═══════════════════════════ */
if ($action === 'get_mensajes') {
    $st = mysqli_prepare($con, "SELECT id,materia_id,usuario_nombre,rol,mensaje,respuesta_a,fecha FROM foro_mensajes WHERE materia_id=? ORDER BY fecha ASC");
    mysqli_stmt_bind_param($st, 'i', $materia_id);
    mysqli_stmt_execute($st);
    $r = mysqli_stmt_get_result($st);
    $mensajes = []; while ($f = mysqli_fetch_assoc($r)) $mensajes[] = $f;
    echo json_encode($mensajes); exit;
}

/* ════ PUBLICAR MENSAJE ════════════════════════════════════════════ */
if ($action === 'post_mensaje' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true) ?: [];

    if (!csrf_verify($data['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'error' => 'Token de seguridad inválido. Recarga la página.']); exit;
    }

    $mensaje = trim($data['mensaje'] ?? '');
    $respuestaA = !empty($data['respuesta_a']) ? (int)$data['respuesta_a'] : null;
    if ($mensaje === '') { echo json_encode(['success' => false, 'error' => 'Mensaje vacío']); exit; }
    if (mb_strlen($mensaje) > 2000) { echo json_encode(['success' => false, 'error' => 'El mensaje es demasiado largo (máx. 2000 caracteres).']); exit; }

    // Si responde a otro mensaje, que sea uno real de esta misma materia.
    if ($respuestaA) {
        $stR = mysqli_prepare($con, "SELECT id FROM foro_mensajes WHERE id=? AND materia_id=? LIMIT 1");
        mysqli_stmt_bind_param($stR, 'ii', $respuestaA, $materia_id);
        mysqli_stmt_execute($stR);
        if (!mysqli_fetch_row(mysqli_stmt_get_result($stR))) $respuestaA = null;
    }

    $st = mysqli_prepare($con, "INSERT INTO foro_mensajes(materia_id,usuario_nombre,rol,mensaje,respuesta_a) VALUES(?,?,?,?,?)");
    mysqli_stmt_bind_param($st, 'isssi', $materia_id, $usuarioNombre, $_rol, $mensaje, $respuestaA);
    if (!mysqli_stmt_execute($st)) { echo json_encode(['success' => false, 'error' => 'No se pudo publicar el mensaje.']); exit; }

    log_audit($con, $uid, 'FORO_MENSAJE', "materia=$materia_id");
    $resumen = mb_strlen($mensaje) > 80 ? mb_substr($mensaje, 0, 80).'…' : $mensaje;
    notificar_materia($con, $materia_id, 'foro', "Nuevo mensaje en el foro de $usuarioNombre", $resumen, $uid);
    echo json_encode(['success' => true]); exit;
}

echo json_encode(['error' => 'Acción no válida']);
