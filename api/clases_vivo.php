<?php
/**
 * IBBS — Clases en Vivo (endpoint).
 * Videollamadas por materia: Jitsi Meet (sala generada por el propio
 * sistema, sin cuenta ni servidor propio) o un link externo pegado por
 * el docente (Google Meet, Zoom, etc.). Requiere
 * database/migrations/003_clases_vivo.sql.
 *
 * Todas las acciones exigen sesión iniciada. Las que modifican datos
 * exigen además el token CSRF y permiso de gestión sobre la materia
 * (materia_puede_gestionar, en config/materia_permisos.php).
 *
 * Nota de seguridad — el "candado" de una sala de Jitsi anónima es el
 * propio nombre de sala: no hay contraseña por defecto. Por eso
 * vivo_generar_sala() usa un sufijo aleatorio de 16 caracteres (64
 * bits) imposible de adivinar, y el link solo se entrega a quien pasa
 * materia_puede_ver() sobre esa materia. Quien reciba el link por
 * fuera del sistema (reenviado, etc.) también podrá entrar — eso es
 * una limitación inherente a Jitsi anónimo, no de esta app.
 */

ob_start();
error_reporting(0);
require_once __DIR__.'/../config/bootstrap.php';

if (empty($_SESSION['loggedin'])) {
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok'=>false,'msg'=>'Sesión expirada.']); exit;
}

ob_clean();
header('Content-Type: application/json; charset=utf-8');
csrf_require_post();

$con = db();
if (!$con) { echo json_encode(['ok'=>false,'msg'=>'Error de conexión a la base de datos.']); exit; }

$uid    = (int)($_SESSION['user_id'] ?? 0);
$_rol   = $_SESSION['rol'] ?? 'profesor';
$action = trim($_POST['action'] ?? '');

if (!function_exists('json_fail')) {
    function json_fail($msg) { echo json_encode(['ok'=>false,'msg'=>$msg]); exit; }
}

const VIVO_ESTADOS = ['programada','en_curso','finalizada','cancelada'];

/** Sala de Jitsi aleatoria e inadivinable — ver nota de seguridad arriba. */
function vivo_generar_sala($materia_id) {
    return 'ibbs-'.(int)$materia_id.'-'.bin2hex(random_bytes(8));
}
// url_es_valida() / url_host_es() viven en config/url_validacion.php —
// comparan el host EXACTO, nunca por substring (evita que
// "meet.google.com.evil.com" pase como si fuera Google Meet real).
function vivo_es_meet($url) {
    return url_host_es($url, ['meet.google.com']);
}
/** Link al que efectivamente se une el usuario, o null si la sesión quedó mal formada. */
function vivo_join_url($row) {
    if ($row['plataforma'] === 'jitsi' && !empty($row['sala'])) {
        return 'https://meet.jit.si/'.rawurlencode($row['sala']);
    }
    if (in_array($row['plataforma'], ['meet','otro'], true) && !empty($row['url'])) {
        return $row['url'];
    }
    return null;
}

/* ════ MIS MATERIAS (selector de entrada) ══════════════════════ */
if ($action === 'materias_mias') {
    echo json_encode(['ok'=>true,'data'=>materias_asignadas($con, $uid, $_rol)]); exit;
}

/* ════ INFO DE LA MATERIA ═══════════════════════════════════════ */
if ($action === 'materia_info') {
    $mid = (int)($_POST['materia_id'] ?? 0);
    if (!$mid) json_fail('Materia no especificada.');
    if (!materia_puede_ver($con, $uid, $_rol, $mid)) json_fail('Sin permiso sobre esta materia.');

    $st = mysqli_prepare($con, "SELECT id,nombre,codigo,estado FROM materias WHERE id=? LIMIT 1");
    mysqli_stmt_bind_param($st, 'i', $mid);
    mysqli_stmt_execute($st);
    $mat = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
    if (!$mat) json_fail('Materia no encontrada.');

    echo json_encode(['ok'=>true,'data'=>[
        'materia'    => $mat,
        'can_manage' => materia_puede_gestionar($con, $uid, $_rol, $mid),
    ]]); exit;
}

/* ════ LISTAR ════════════════════════════════════════════════════ */
if ($action === 'vivo_list') {
    $mid = (int)($_POST['materia_id'] ?? 0);
    if (!materia_puede_ver($con, $uid, $_rol, $mid)) json_fail('Sin permiso.');

    $st = mysqli_prepare($con, "SELECT c.id,c.titulo,c.descripcion,c.plataforma,c.sala,c.url,c.fecha_hora,c.estado,u.usuario autor
                                 FROM clases_vivo c JOIN usuarios u ON u.id=c.usuario_id
                                 WHERE c.materia_id=? ORDER BY c.fecha_hora DESC");
    mysqli_stmt_bind_param($st, 'i', $mid);
    mysqli_stmt_execute($st);
    $r = mysqli_stmt_get_result($st);
    $rows = [];
    while ($f = mysqli_fetch_assoc($r)) {
        $f['join_url'] = vivo_join_url($f);
        unset($f['sala']); // no hace falta exponer el nombre crudo de la sala, alcanza con join_url
        $rows[] = $f;
    }
    echo json_encode(['ok'=>true,'data'=>$rows]); exit;
}

/* ════ CREAR ═════════════════════════════════════════════════════ */
if ($action === 'vivo_create') {
    $mid = (int)($_POST['materia_id'] ?? 0);
    if (!materia_puede_gestionar($con, $uid, $_rol, $mid)) json_fail('Sin permiso.');

    $titulo      = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $plataforma  = trim($_POST['plataforma'] ?? 'jitsi');
    $fechaHora   = trim($_POST['fecha_hora'] ?? '');
    $urlPegada   = trim($_POST['url'] ?? '');

    if ($titulo === '') json_fail('Ponle un título a la clase.');
    if (mb_strlen($titulo) > 150) json_fail('El título es muy largo.');
    if (!in_array($plataforma, ['jitsi','meet','otro'], true)) $plataforma = 'jitsi';
    $fecha = DateTime::createFromFormat('Y-m-d\TH:i', $fechaHora) ?: DateTime::createFromFormat('Y-m-d H:i:s', $fechaHora);
    if (!$fecha) json_fail('Fecha y hora no válidas.');
    $fechaHoraSql = $fecha->format('Y-m-d H:i:s');

    $sala = null;
    $url  = null;
    if ($plataforma === 'jitsi') {
        $sala = vivo_generar_sala($mid);
    } elseif ($plataforma === 'meet') {
        if (!url_es_valida($urlPegada) || !vivo_es_meet($urlPegada)) {
            json_fail('Pega un link válido de Google Meet (meet.google.com).');
        }
        $url = $urlPegada;
    } else { // otro
        if (!url_es_valida($urlPegada)) json_fail('El link no es una URL válida (debe empezar con http:// o https://).');
        if (mb_strlen($urlPegada) > 500) json_fail('El link es demasiado largo.');
        $url = $urlPegada;
    }

    $st = mysqli_prepare($con, "INSERT INTO clases_vivo(materia_id,usuario_id,titulo,descripcion,plataforma,sala,url,fecha_hora,estado) VALUES(?,?,?,?,?,?,?,?,'programada')");
    mysqli_stmt_bind_param($st, 'iissssss', $mid, $uid, $titulo, $descripcion, $plataforma, $sala, $url, $fechaHoraSql);
    if (!mysqli_stmt_execute($st)) json_fail('No se pudo crear la clase en vivo.');
    log_audit($con, $uid, 'CLASE_VIVO_CREATE', "materia=$mid plataforma=$plataforma");
    echo json_encode(['ok'=>true,'msg'=>'Clase en vivo creada.']); exit;
}

/* ════ EDITAR (solo título/descripción/fecha — no la plataforma) ══ */
if ($action === 'vivo_update') {
    $id  = (int)($_POST['id'] ?? 0);
    $mid = (int)($_POST['materia_id'] ?? 0);
    if (!materia_puede_gestionar($con, $uid, $_rol, $mid)) json_fail('Sin permiso.');

    $titulo      = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $fechaHora   = trim($_POST['fecha_hora'] ?? '');
    if ($titulo === '') json_fail('Ponle un título a la clase.');
    $fecha = DateTime::createFromFormat('Y-m-d\TH:i', $fechaHora) ?: DateTime::createFromFormat('Y-m-d H:i:s', $fechaHora);
    if (!$fecha) json_fail('Fecha y hora no válidas.');
    $fechaHoraSql = $fecha->format('Y-m-d H:i:s');

    // El WHERE incluye materia_id: evita editar una clase de otra
    // materia aunque alguien adivine el id.
    $st = mysqli_prepare($con, "UPDATE clases_vivo SET titulo=?,descripcion=?,fecha_hora=? WHERE id=? AND materia_id=?");
    mysqli_stmt_bind_param($st, 'sssii', $titulo, $descripcion, $fechaHoraSql, $id, $mid);
    mysqli_stmt_execute($st);
    echo json_encode(['ok'=>true,'msg'=>'Clase actualizada.']); exit;
}

/* ════ CAMBIAR ESTADO (Iniciar / Finalizar / Cancelar) ═══════════ */
if ($action === 'vivo_set_estado') {
    $id     = (int)($_POST['id'] ?? 0);
    $mid    = (int)($_POST['materia_id'] ?? 0);
    $estado = trim($_POST['estado'] ?? '');
    if (!materia_puede_gestionar($con, $uid, $_rol, $mid)) json_fail('Sin permiso.');
    if (!in_array($estado, VIVO_ESTADOS, true)) json_fail('Estado no válido.');

    $st = mysqli_prepare($con, "UPDATE clases_vivo SET estado=? WHERE id=? AND materia_id=?");
    mysqli_stmt_bind_param($st, 'sii', $estado, $id, $mid);
    mysqli_stmt_execute($st);
    log_audit($con, $uid, 'CLASE_VIVO_ESTADO', "id=$id estado=$estado");
    echo json_encode(['ok'=>true,'msg'=>'Estado actualizado.']); exit;
}

/* ════ ELIMINAR ══════════════════════════════════════════════════ */
if ($action === 'vivo_delete') {
    $id  = (int)($_POST['id'] ?? 0);
    $mid = (int)($_POST['materia_id'] ?? 0);
    if (!materia_puede_gestionar($con, $uid, $_rol, $mid)) json_fail('Sin permiso.');
    $st = mysqli_prepare($con, "DELETE FROM clases_vivo WHERE id=? AND materia_id=?");
    mysqli_stmt_bind_param($st, 'ii', $id, $mid);
    mysqli_stmt_execute($st);
    log_audit($con, $uid, 'CLASE_VIVO_DELETE', "id=$id");
    echo json_encode(['ok'=>true,'msg'=>'Clase en vivo eliminada.']); exit;
}

echo json_encode(['ok'=>false,'msg'=>'Acción no reconocida.']);
