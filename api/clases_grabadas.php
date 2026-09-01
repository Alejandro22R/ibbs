<?php
/**
 * IBBS — Clases Grabadas (endpoint).
 * Repositorio de links a videos (YouTube/Drive/Vimeo) por materia.
 * Requiere database/migrations/002_clases_grabadas.sql.
 *
 * Todas las acciones exigen sesión iniciada. Las que modifican datos
 * exigen además el token CSRF y permiso de gestión sobre la materia
 * (materia_puede_gestionar, en config/materia_permisos.php).
 *
 * Nota de seguridad: el link que pega el docente NUNCA se usa tal
 * cual como src de un <iframe>. Se valida que sea http/https, se
 * detecta la plataforma por su dominio, y se reconstruye una URL de
 * embed propia y conocida (clases_embed_url). Un link de un dominio
 * no reconocido simplemente no se embebe — el frontend lo muestra
 * como enlace externo (target=_blank rel=noopener).
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

/* ── Validación y embed seguro de links de video ────────────────
 * Solo YouTube, Google Drive y Vimeo generan un embed real; cualquier
 * otro dominio se guarda igual (por si el docente quiere compartir
 * otra plataforma) pero nunca se mete en un <iframe> — el frontend lo
 * ofrece como enlace externo. ─────────────────────────────────── */
function clases_url_valida($url) {
    if (!filter_var($url, FILTER_VALIDATE_URL)) return false;
    $scheme = strtolower(parse_url($url, PHP_URL_SCHEME) ?: '');
    return in_array($scheme, ['http', 'https'], true);
}
function clases_detectar_plataforma($url) {
    $host = strtolower(parse_url($url, PHP_URL_HOST) ?: '');
    if (str_contains($host, 'youtube.com') || str_contains($host, 'youtu.be')) return 'youtube';
    if (str_contains($host, 'drive.google.com')) return 'drive';
    if (str_contains($host, 'vimeo.com')) return 'vimeo';
    return 'otro';
}
// Devuelve una URL de embed propia y segura, o null si no se pudo
// reconocer el id del video (en ese caso el frontend cae a "abrir enlace").
function clases_embed_url($url, $plataforma) {
    if ($plataforma === 'youtube' && preg_match('/(?:youtube\.com\/(?:watch\?(?:.*&)?v=|embed\/|shorts\/)|youtu\.be\/)([A-Za-z0-9_-]{6,20})/', $url, $m)) {
        return 'https://www.youtube.com/embed/'.$m[1];
    }
    if ($plataforma === 'vimeo' && preg_match('/vimeo\.com\/(?:.*\/)?(\d{6,12})/', $url, $m)) {
        return 'https://player.vimeo.com/video/'.$m[1];
    }
    if ($plataforma === 'drive') {
        if (preg_match('/drive\.google\.com\/file\/d\/([A-Za-z0-9_-]+)/', $url, $m)) {
            return 'https://drive.google.com/file/d/'.$m[1].'/preview';
        }
        if (preg_match('/[?&]id=([A-Za-z0-9_-]+)/', $url, $m)) {
            return 'https://drive.google.com/file/d/'.$m[1].'/preview';
        }
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
if ($action === 'clase_list') {
    $mid = (int)($_POST['materia_id'] ?? 0);
    if (!materia_puede_ver($con, $uid, $_rol, $mid)) json_fail('Sin permiso.');

    $st = mysqli_prepare($con, "SELECT c.id,c.titulo,c.descripcion,c.url,c.plataforma,c.fecha,c.creado_en,u.usuario autor
                                 FROM clases_grabadas c JOIN usuarios u ON u.id=c.usuario_id
                                 WHERE c.materia_id=? ORDER BY (c.fecha IS NULL), c.fecha DESC, c.creado_en DESC");
    mysqli_stmt_bind_param($st, 'i', $mid);
    mysqli_stmt_execute($st);
    $r = mysqli_stmt_get_result($st);
    $rows = [];
    while ($f = mysqli_fetch_assoc($r)) {
        $f['embed_url'] = clases_embed_url($f['url'], $f['plataforma']);
        $rows[] = $f;
    }
    echo json_encode(['ok'=>true,'data'=>$rows]); exit;
}

/* ════ CREAR / EDITAR ════════════════════════════════════════════ */
if ($action === 'clase_create' || $action === 'clase_update') {
    $mid = (int)($_POST['materia_id'] ?? 0);
    if (!materia_puede_gestionar($con, $uid, $_rol, $mid)) json_fail('Sin permiso.');

    $titulo      = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $url         = trim($_POST['url'] ?? '');
    $fecha       = trim($_POST['fecha'] ?? '') ?: null;

    if ($titulo === '') json_fail('Ponle un título a la clase.');
    if (mb_strlen($titulo) > 150) json_fail('El título es muy largo.');
    if (!clases_url_valida($url)) json_fail('El link no es una URL válida (debe empezar con http:// o https://).');
    if (mb_strlen($url) > 500) json_fail('El link es demasiado largo.');

    $plataforma = clases_detectar_plataforma($url);

    if ($action === 'clase_create') {
        $st = mysqli_prepare($con, "INSERT INTO clases_grabadas(materia_id,usuario_id,titulo,descripcion,url,plataforma,fecha) VALUES(?,?,?,?,?,?,?)");
        mysqli_stmt_bind_param($st, 'iisssss', $mid, $uid, $titulo, $descripcion, $url, $plataforma, $fecha);
        if (!mysqli_stmt_execute($st)) json_fail('No se pudo guardar la clase.');
        log_audit($con, $uid, 'CLASE_GRABADA_CREATE', "materia=$mid");
        echo json_encode(['ok'=>true,'msg'=>'Clase grabada agregada.']); exit;
    } else {
        $id = (int)($_POST['id'] ?? 0);
        // El WHERE incluye materia_id: evita editar una clase de otra
        // materia aunque alguien adivine el id.
        $st = mysqli_prepare($con, "UPDATE clases_grabadas SET titulo=?,descripcion=?,url=?,plataforma=?,fecha=? WHERE id=? AND materia_id=?");
        mysqli_stmt_bind_param($st, 'sssssii', $titulo, $descripcion, $url, $plataforma, $fecha, $id, $mid);
        mysqli_stmt_execute($st);
        echo json_encode(['ok'=>true,'msg'=>'Clase actualizada.']); exit;
    }
}

/* ════ ELIMINAR ══════════════════════════════════════════════════ */
if ($action === 'clase_delete') {
    $id  = (int)($_POST['id'] ?? 0);
    $mid = (int)($_POST['materia_id'] ?? 0);
    if (!materia_puede_gestionar($con, $uid, $_rol, $mid)) json_fail('Sin permiso.');
    $st = mysqli_prepare($con, "DELETE FROM clases_grabadas WHERE id=? AND materia_id=?");
    mysqli_stmt_bind_param($st, 'ii', $id, $mid);
    mysqli_stmt_execute($st);
    log_audit($con, $uid, 'CLASE_GRABADA_DELETE', "id=$id");
    echo json_encode(['ok'=>true,'msg'=>'Clase eliminada.']); exit;
}

echo json_encode(['ok'=>false,'msg'=>'Acción no reconocida.']);
