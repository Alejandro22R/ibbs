<?php
/**
 * IBBS — Aula Virtual (endpoint).
 * Anuncios, materiales descargables y actividades/calificaciones por
 * materia. Requiere las tablas de database/migrations/001_aula_virtual.sql.
 *
 * Todas las acciones exigen sesión iniciada. Las que modifican datos
 * exigen además el token CSRF (ver config/csrf.php) y que el usuario
 * tenga permiso de gestión sobre la materia (aula_puede_gestionar).
 */

ob_start();
error_reporting(0);
require_once __DIR__.'/../config/bootstrap.php';

$isDownload = ($_SERVER['REQUEST_METHOD'] === 'GET') && (($_GET['action'] ?? '') === 'material_download');

if (empty($_SESSION['loggedin'])) {
    if ($isDownload) { ob_end_clean(); http_response_code(403); die('Sesión expirada.'); }
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok'=>false,'msg'=>'Sesión expirada.']); exit;
}

if (!$isDownload) {
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    csrf_require_post(); // no exige nada en GET; en POST corta si el token no coincide
}

$con = db();
if (!$con) {
    if ($isDownload) { ob_end_clean(); http_response_code(500); die('Error de conexión a la base de datos.'); }
    echo json_encode(['ok'=>false,'msg'=>'Error de conexión a la base de datos.']); exit;
}

$uid  = (int)($_SESSION['user_id'] ?? 0);
$_rol = $_SESSION['rol'] ?? 'profesor';
$action = $isDownload ? 'material_download' : trim($_POST['action'] ?? '');

/* ── Permisos ──────────────────────────────────────────────────
 * aula_puede_gestionar: crear/editar/borrar contenido del aula.
 * aula_puede_ver: además de lo anterior, solo lectura para quien
 * esté inscrito en la materia — el rol 'alumno' todavía no tiene
 * sesión propia en el sistema (ver módulo "Portal del alumno"),
 * así que esta rama queda lista para cuando se active.
 * ──────────────────────────────────────────────────────────── */
function aula_puede_gestionar($con, $uid, $rol, $materia_id) {
    if (in_array($rol, ['superadmin','admin'])) return true;
    if ($rol !== 'profesor') return false;
    $st = mysqli_prepare($con, "SELECT 1 FROM materia_docente md JOIN docentes d ON d.id=md.docente_id WHERE md.materia_id=? AND d.usuario_id=? LIMIT 1");
    mysqli_stmt_bind_param($st, 'ii', $materia_id, $uid);
    mysqli_stmt_execute($st);
    $r = mysqli_stmt_get_result($st);
    return (bool) mysqli_fetch_row($r);
}
function aula_puede_ver($con, $uid, $rol, $materia_id) {
    if (aula_puede_gestionar($con, $uid, $rol, $materia_id)) return true;
    if ($rol === 'alumno') {
        $st = mysqli_prepare($con, "SELECT 1 FROM materia_alumno ma JOIN alumnos a ON a.id=ma.alumno_id WHERE ma.materia_id=? AND a.usuario_id=? LIMIT 1");
        mysqli_stmt_bind_param($st, 'ii', $materia_id, $uid);
        mysqli_stmt_execute($st);
        $r = mysqli_stmt_get_result($st);
        return (bool) mysqli_fetch_row($r);
    }
    return false;
}
function json_fail($msg) { echo json_encode(['ok'=>false,'msg'=>$msg]); exit; }

/* ════ MIS MATERIAS (selector de entrada al aula) ═════════════
 * Admin/superadmin ven todas; profesor solo las suyas — así el
 * selector nunca ofrece una materia sobre la que después no podría
 * hacer nada. */
if ($action === 'materias_mias') {
    if (in_array($_rol, ['superadmin','admin'])) {
        $r = mysqli_query($con, "SELECT id,nombre,codigo,estado FROM materias WHERE activo=1 ORDER BY nombre");
    } elseif ($_rol === 'profesor') {
        $st = mysqli_prepare($con, "SELECT m.id,m.nombre,m.codigo,m.estado
                                     FROM materias m
                                     JOIN materia_docente md ON md.materia_id=m.id
                                     JOIN docentes d ON d.id=md.docente_id
                                     WHERE d.usuario_id=? AND m.activo=1 ORDER BY m.nombre");
        mysqli_stmt_bind_param($st, 'i', $uid);
        mysqli_stmt_execute($st);
        $r = mysqli_stmt_get_result($st);
    } else {
        json_fail('Sin permiso.');
    }
    $rows = []; while ($f = mysqli_fetch_assoc($r)) $rows[] = $f;
    echo json_encode(['ok'=>true,'data'=>$rows]); exit;
}

/* ════ INFO DE LA MATERIA (para el encabezado del aula) ═══════ */
if ($action === 'materia_info') {
    $mid = (int)($_POST['materia_id'] ?? 0);
    if (!$mid) json_fail('Materia no especificada.');
    if (!aula_puede_ver($con, $uid, $_rol, $mid)) json_fail('Sin permiso sobre esta materia.');

    $st = mysqli_prepare($con, "SELECT id,nombre,codigo,estado FROM materias WHERE id=? LIMIT 1");
    mysqli_stmt_bind_param($st, 'i', $mid);
    mysqli_stmt_execute($st);
    $mat = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
    if (!$mat) json_fail('Materia no encontrada.');

    echo json_encode(['ok'=>true,'data'=>[
        'materia'     => $mat,
        'can_manage'  => aula_puede_gestionar($con, $uid, $_rol, $mid),
    ]]); exit;
}

/* ════ ANUNCIOS ═════════════════════════════════════════════ */
if ($action === 'anuncio_list') {
    $mid = (int)($_POST['materia_id'] ?? 0);
    if (!aula_puede_ver($con, $uid, $_rol, $mid)) json_fail('Sin permiso.');
    $st = mysqli_prepare($con, "SELECT a.id,a.titulo,a.contenido,a.fijado,a.creado_en,u.usuario autor
                                 FROM aula_anuncios a JOIN usuarios u ON u.id=a.usuario_id
                                 WHERE a.materia_id=? ORDER BY a.fijado DESC, a.creado_en DESC");
    mysqli_stmt_bind_param($st, 'i', $mid);
    mysqli_stmt_execute($st);
    $r = mysqli_stmt_get_result($st);
    $rows = []; while ($f = mysqli_fetch_assoc($r)) $rows[] = $f;
    echo json_encode(['ok'=>true,'data'=>$rows]); exit;
}

if ($action === 'anuncio_create') {
    $mid = (int)($_POST['materia_id'] ?? 0);
    if (!aula_puede_gestionar($con, $uid, $_rol, $mid)) json_fail('Sin permiso.');
    $titulo    = trim($_POST['titulo'] ?? '');
    $contenido = trim($_POST['contenido'] ?? '');
    $fijado    = !empty($_POST['fijado']) ? 1 : 0;
    if ($titulo === '' || $contenido === '') json_fail('Completa título y contenido.');
    if (mb_strlen($titulo) > 150) json_fail('El título es muy largo.');

    $st = mysqli_prepare($con, "INSERT INTO aula_anuncios(materia_id,usuario_id,titulo,contenido,fijado) VALUES(?,?,?,?,?)");
    mysqli_stmt_bind_param($st, 'iissi', $mid, $uid, $titulo, $contenido, $fijado);
    if (!mysqli_stmt_execute($st)) json_fail('No se pudo publicar el anuncio.');
    log_audit($con, $uid, 'AULA_ANUNCIO_CREATE', "materia=$mid");
    echo json_encode(['ok'=>true,'msg'=>'Anuncio publicado.']); exit;
}

if ($action === 'anuncio_update') {
    $id = (int)($_POST['id'] ?? 0);
    $mid = (int)($_POST['materia_id'] ?? 0);
    if (!aula_puede_gestionar($con, $uid, $_rol, $mid)) json_fail('Sin permiso.');
    $titulo    = trim($_POST['titulo'] ?? '');
    $contenido = trim($_POST['contenido'] ?? '');
    $fijado    = !empty($_POST['fijado']) ? 1 : 0;
    if ($titulo === '' || $contenido === '') json_fail('Completa título y contenido.');

    // El WHERE incluye materia_id: evita editar un anuncio de otra materia
    // aunque alguien adivine el id.
    $st = mysqli_prepare($con, "UPDATE aula_anuncios SET titulo=?, contenido=?, fijado=? WHERE id=? AND materia_id=?");
    mysqli_stmt_bind_param($st, 'ssiii', $titulo, $contenido, $fijado, $id, $mid);
    mysqli_stmt_execute($st);
    echo json_encode(['ok'=>true,'msg'=>'Anuncio actualizado.']); exit;
}

if ($action === 'anuncio_delete') {
    $id  = (int)($_POST['id'] ?? 0);
    $mid = (int)($_POST['materia_id'] ?? 0);
    if (!aula_puede_gestionar($con, $uid, $_rol, $mid)) json_fail('Sin permiso.');
    $st = mysqli_prepare($con, "DELETE FROM aula_anuncios WHERE id=? AND materia_id=?");
    mysqli_stmt_bind_param($st, 'ii', $id, $mid);
    mysqli_stmt_execute($st);
    log_audit($con, $uid, 'AULA_ANUNCIO_DELETE', "id=$id");
    echo json_encode(['ok'=>true,'msg'=>'Anuncio eliminado.']); exit;
}

/* ════ MATERIALES ═══════════════════════════════════════════ */
if ($action === 'material_list') {
    $mid = (int)($_POST['materia_id'] ?? 0);
    if (!aula_puede_ver($con, $uid, $_rol, $mid)) json_fail('Sin permiso.');
    $st = mysqli_prepare($con, "SELECT m.id,m.titulo,m.descripcion,m.archivo_nombre,m.archivo_tipo,m.tamano_bytes,m.creado_en,u.usuario autor
                                 FROM aula_materiales m JOIN usuarios u ON u.id=m.usuario_id
                                 WHERE m.materia_id=? ORDER BY m.creado_en DESC");
    mysqli_stmt_bind_param($st, 'i', $mid);
    mysqli_stmt_execute($st);
    $r = mysqli_stmt_get_result($st);
    $rows = []; while ($f = mysqli_fetch_assoc($r)) $rows[] = $f;
    echo json_encode(['ok'=>true,'data'=>$rows]); exit;
}

if ($action === 'material_create') {
    $mid = (int)($_POST['materia_id'] ?? 0);
    if (!aula_puede_gestionar($con, $uid, $_rol, $mid)) json_fail('Sin permiso.');
    $titulo      = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    if ($titulo === '') json_fail('Ponle un título al material.');

    if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
        json_fail('No se recibió ningún archivo.');
    }
    $file = $_FILES['archivo'];
    if ($file['size'] > 25 * 1024 * 1024) json_fail('Archivo muy grande (máx. 25MB).');

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    // Extensión + MIME real deben coincidir con la lista blanca — evita
    // subir un ejecutable disfrazado con extensión de documento.
    $tiposPermitidos = [
        'pdf'  => ['application/pdf'],
        'doc'  => ['application/msword'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        'ppt'  => ['application/vnd.ms-powerpoint'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip'],
        'xls'  => ['application/vnd.ms-excel'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
        'txt'  => ['text/plain'],
        'csv'  => ['text/plain', 'text/csv'],
        'zip'  => ['application/zip', 'application/x-zip-compressed'],
        'jpg'  => ['image/jpeg'], 'jpeg' => ['image/jpeg'],
        'png'  => ['image/png'], 'gif' => ['image/gif'], 'webp' => ['image/webp'],
    ];
    if (!isset($tiposPermitidos[$ext])) {
        json_fail('Tipo de archivo no permitido. Usa PDF, Word, PowerPoint, Excel, TXT, CSV, ZIP o una imagen.');
    }
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mime, $tiposPermitidos[$ext])) {
        json_fail('El contenido del archivo no coincide con su extensión.');
    }

    $dir = __DIR__.'/../uploads/materiales/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    // Nombre generado en el servidor — nunca se usa el nombre original
    // como ruta, así se evita cualquier intento de path traversal.
    $fname = 'mat_'.$mid.'_'.time().'_'.bin2hex(random_bytes(6)).'.'.$ext;
    if (!move_uploaded_file($file['tmp_name'], $dir.$fname)) {
        json_fail('No se pudo guardar el archivo.');
    }
    $ruta = 'uploads/materiales/'.$fname;
    $nombreOriginal = mb_substr(basename($file['name']), 0, 255);

    $tamano = (int)$file['size'];
    $st = mysqli_prepare($con, "INSERT INTO aula_materiales(materia_id,usuario_id,titulo,descripcion,archivo,archivo_nombre,archivo_tipo,tamano_bytes) VALUES(?,?,?,?,?,?,?,?)");
    mysqli_stmt_bind_param($st, 'iisssssi', $mid, $uid, $titulo, $descripcion, $ruta, $nombreOriginal, $ext, $tamano);
    if (!mysqli_stmt_execute($st)) { @unlink($dir.$fname); json_fail('No se pudo registrar el material.'); }
    log_audit($con, $uid, 'AULA_MATERIAL_CREATE', "materia=$mid archivo=$fname");
    echo json_encode(['ok'=>true,'msg'=>'Material subido.']); exit;
}

if ($action === 'material_delete') {
    $id  = (int)($_POST['id'] ?? 0);
    $mid = (int)($_POST['materia_id'] ?? 0);
    if (!aula_puede_gestionar($con, $uid, $_rol, $mid)) json_fail('Sin permiso.');
    $st = mysqli_prepare($con, "SELECT archivo FROM aula_materiales WHERE id=? AND materia_id=?");
    mysqli_stmt_bind_param($st, 'ii', $id, $mid);
    mysqli_stmt_execute($st);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
    if (!$row) json_fail('Material no encontrado.');

    $st2 = mysqli_prepare($con, "DELETE FROM aula_materiales WHERE id=? AND materia_id=?");
    mysqli_stmt_bind_param($st2, 'ii', $id, $mid);
    mysqli_stmt_execute($st2);
    $ruta = __DIR__.'/../'.$row['archivo'];
    if (is_file($ruta)) @unlink($ruta);
    log_audit($con, $uid, 'AULA_MATERIAL_DELETE', "id=$id");
    echo json_encode(['ok'=>true,'msg'=>'Material eliminado.']); exit;
}

if ($action === 'material_download') {
    $id = (int)($_GET['id'] ?? 0);
    $st = mysqli_prepare($con, "SELECT materia_id,archivo,archivo_nombre FROM aula_materiales WHERE id=? LIMIT 1");
    mysqli_stmt_bind_param($st, 'i', $id);
    mysqli_stmt_execute($st);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
    if (!$row || !aula_puede_ver($con, $uid, $_rol, (int)$row['materia_id'])) {
        ob_end_clean(); http_response_code(403); die('Sin permiso.');
    }
    $ruta = __DIR__.'/../'.$row['archivo'];
    // Confirma que la ruta final sigue dentro de uploads/materiales/
    // (defensa adicional, aunque el nombre siempre lo genera el servidor).
    $base = realpath(__DIR__.'/../uploads/materiales');
    $real = realpath($ruta);
    if (!$real || !$base || strpos($real, $base) !== 0 || !is_file($real)) {
        ob_end_clean(); http_response_code(404); die('Archivo no encontrado.');
    }
    ob_end_clean();
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="'.addslashes($row['archivo_nombre']).'"');
    header('Content-Length: '.filesize($real));
    header('X-Content-Type-Options: nosniff');
    readfile($real);
    exit;
}

/* ════ ACTIVIDADES Y CALIFICACIONES ═════════════════════════ */
if ($action === 'actividad_list') {
    $mid = (int)($_POST['materia_id'] ?? 0);
    if (!aula_puede_ver($con, $uid, $_rol, $mid)) json_fail('Sin permiso.');
    $st = mysqli_prepare($con, "SELECT id,titulo,descripcion,tipo,nota_max,fecha,creado_en FROM aula_actividades WHERE materia_id=? ORDER BY (fecha IS NULL), fecha DESC, creado_en DESC");
    mysqli_stmt_bind_param($st, 'i', $mid);
    mysqli_stmt_execute($st);
    $r = mysqli_stmt_get_result($st);
    $rows = []; while ($f = mysqli_fetch_assoc($r)) $rows[] = $f;
    echo json_encode(['ok'=>true,'data'=>$rows]); exit;
}

if ($action === 'actividad_create' || $action === 'actividad_update') {
    $mid = (int)($_POST['materia_id'] ?? 0);
    if (!aula_puede_gestionar($con, $uid, $_rol, $mid)) json_fail('Sin permiso.');
    $titulo      = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $tipo        = trim($_POST['tipo'] ?? 'actividad');
    $notaMax     = (float)($_POST['nota_max'] ?? 20);
    $fecha       = trim($_POST['fecha'] ?? '') ?: null;
    if ($titulo === '') json_fail('Ponle un título a la actividad.');
    if ($notaMax <= 0 || $notaMax > 1000) json_fail('La nota máxima no es válida.');
    if (!in_array($tipo, ['actividad','examen','taller','proyecto'])) $tipo = 'actividad';

    if ($action === 'actividad_create') {
        $st = mysqli_prepare($con, "INSERT INTO aula_actividades(materia_id,usuario_id,titulo,descripcion,tipo,nota_max,fecha) VALUES(?,?,?,?,?,?,?)");
        mysqli_stmt_bind_param($st, 'iisssds', $mid, $uid, $titulo, $descripcion, $tipo, $notaMax, $fecha);
        if (!mysqli_stmt_execute($st)) json_fail('No se pudo crear la actividad.');
        log_audit($con, $uid, 'AULA_ACTIVIDAD_CREATE', "materia=$mid");
        echo json_encode(['ok'=>true,'msg'=>'Actividad creada.']); exit;
    } else {
        $id = (int)($_POST['id'] ?? 0);
        $st = mysqli_prepare($con, "UPDATE aula_actividades SET titulo=?,descripcion=?,tipo=?,nota_max=?,fecha=? WHERE id=? AND materia_id=?");
        mysqli_stmt_bind_param($st, 'sssdsii', $titulo, $descripcion, $tipo, $notaMax, $fecha, $id, $mid);
        mysqli_stmt_execute($st);
        echo json_encode(['ok'=>true,'msg'=>'Actividad actualizada.']); exit;
    }
}

if ($action === 'actividad_delete') {
    $id  = (int)($_POST['id'] ?? 0);
    $mid = (int)($_POST['materia_id'] ?? 0);
    if (!aula_puede_gestionar($con, $uid, $_rol, $mid)) json_fail('Sin permiso.');
    $st = mysqli_prepare($con, "DELETE FROM aula_actividades WHERE id=? AND materia_id=?");
    mysqli_stmt_bind_param($st, 'ii', $id, $mid);
    mysqli_stmt_execute($st);
    log_audit($con, $uid, 'AULA_ACTIVIDAD_DELETE', "id=$id");
    echo json_encode(['ok'=>true,'msg'=>'Actividad eliminada (y sus calificaciones).']); exit;
}

// Lista los alumnos inscritos en la materia con su nota (si existe) en esta actividad.
if ($action === 'actividad_calificaciones') {
    $aid = (int)($_POST['actividad_id'] ?? 0);
    $mid = (int)($_POST['materia_id'] ?? 0);
    if (!aula_puede_gestionar($con, $uid, $_rol, $mid)) json_fail('Sin permiso.');

    // Verifica que la actividad realmente pertenezca a esa materia.
    $stA = mysqli_prepare($con, "SELECT id,titulo,nota_max FROM aula_actividades WHERE id=? AND materia_id=? LIMIT 1");
    mysqli_stmt_bind_param($stA, 'ii', $aid, $mid);
    mysqli_stmt_execute($stA);
    $act = mysqli_fetch_assoc(mysqli_stmt_get_result($stA));
    if (!$act) json_fail('Actividad no encontrada.');

    $st = mysqli_prepare($con, "SELECT a.id,a.nombre,a.apellido,a.cedula,c.nota,c.observacion
                                 FROM alumnos a
                                 JOIN materia_alumno ma ON ma.alumno_id=a.id AND ma.materia_id=?
                                 LEFT JOIN aula_calificaciones c ON c.actividad_id=? AND c.alumno_id=a.id
                                 ORDER BY a.apellido, a.nombre");
    mysqli_stmt_bind_param($st, 'ii', $mid, $aid);
    mysqli_stmt_execute($st);
    $r = mysqli_stmt_get_result($st);
    $rows = []; while ($f = mysqli_fetch_assoc($r)) $rows[] = $f;
    echo json_encode(['ok'=>true,'data'=>['actividad'=>$act,'alumnos'=>$rows]]); exit;
}

// Guarda todas las notas de una actividad de una vez (una fila por alumno).
if ($action === 'actividad_calificar_bulk') {
    $aid = (int)($_POST['actividad_id'] ?? 0);
    $mid = (int)($_POST['materia_id'] ?? 0);
    if (!aula_puede_gestionar($con, $uid, $_rol, $mid)) json_fail('Sin permiso.');

    $stA = mysqli_prepare($con, "SELECT nota_max FROM aula_actividades WHERE id=? AND materia_id=? LIMIT 1");
    mysqli_stmt_bind_param($stA, 'ii', $aid, $mid);
    mysqli_stmt_execute($stA);
    $act = mysqli_fetch_assoc(mysqli_stmt_get_result($stA));
    if (!$act) json_fail('Actividad no encontrada.');
    $notaMax = (float)$act['nota_max'];

    $notas = json_decode($_POST['notas'] ?? '[]', true);
    if (!is_array($notas)) json_fail('Datos inválidos.');

    $stUp = mysqli_prepare($con, "INSERT INTO aula_calificaciones(actividad_id,alumno_id,nota,observacion,calificado_por,actualizado_en)
                                   VALUES(?,?,?,?,?,NOW())
                                   ON DUPLICATE KEY UPDATE nota=VALUES(nota), observacion=VALUES(observacion), calificado_por=VALUES(calificado_por), actualizado_en=NOW()");
    // Solo se califica a alumnos realmente inscritos en la materia —
    // evita que un id manipulado en el POST escriba en otra materia.
    $stCheck = mysqli_prepare($con, "SELECT 1 FROM materia_alumno WHERE materia_id=? AND alumno_id=? LIMIT 1");

    $guardadas = 0;
    foreach ($notas as $item) {
        $alumnoId = (int)($item['alumno_id'] ?? 0);
        if (!$alumnoId) continue;
        mysqli_stmt_bind_param($stCheck, 'ii', $mid, $alumnoId);
        mysqli_stmt_execute($stCheck);
        if (!mysqli_fetch_row(mysqli_stmt_get_result($stCheck))) continue;

        $notaRaw = $item['nota'] ?? null;
        $nota = ($notaRaw === null || $notaRaw === '') ? null : (float)$notaRaw;
        if ($nota !== null && ($nota < 0 || $nota > $notaMax)) continue; // fuera de rango: se ignora esa fila
        $obs = mb_substr(trim((string)($item['observacion'] ?? '')), 0, 255);

        mysqli_stmt_bind_param($stUp, 'iidsi', $aid, $alumnoId, $nota, $obs, $uid);
        if (mysqli_stmt_execute($stUp)) $guardadas++;
    }
    log_audit($con, $uid, 'AULA_CALIFICAR', "actividad=$aid guardadas=$guardadas");
    echo json_encode(['ok'=>true,'msg'=>"Calificaciones guardadas ($guardadas alumno(s))."]); exit;
}

if (!$isDownload) {
    echo json_encode(['ok'=>false,'msg'=>'Acción no reconocida.']);
}
