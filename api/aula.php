<?php
/**
 * IBBS — Aula Virtual (endpoint).
 * Anuncios, materiales descargables y actividades/calificaciones por
 * materia. Requiere las tablas de database/migrations/001_aula_virtual.sql.
 *
 * Todas las acciones exigen sesión iniciada. Las que modifican datos
 * exigen además el token CSRF (ver config/csrf.php) y que el usuario
 * tenga permiso de gestión sobre la materia (materia_puede_gestionar,
 * en config/materia_permisos.php).
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

// Los permisos por materia (materia_puede_gestionar/materia_puede_ver)
// y "mis materias" viven en config/materia_permisos.php, compartidos
// con los demás módulos del campus.
if (!function_exists('json_fail')) {
    function json_fail($msg) { echo json_encode(['ok'=>false,'msg'=>$msg]); exit; }
}

/* ════ MIS MATERIAS (selector de entrada al aula) ═════════════ */
if ($action === 'materias_mias') {
    echo json_encode(['ok'=>true,'data'=>materias_asignadas($con, $uid, $_rol)]); exit;
}

/* ════ INFO DE LA MATERIA (para el encabezado del aula) ═══════ */
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
        'materia'     => $mat,
        'can_manage'  => materia_puede_gestionar($con, $uid, $_rol, $mid),
    ]]); exit;
}

/* ════ SECCIONES (semanas/unidades, estilo Moodle) ═══════════ */
if ($action === 'seccion_list') {
    $mid = (int)($_POST['materia_id'] ?? 0);
    if (!materia_puede_ver($con, $uid, $_rol, $mid)) json_fail('Sin permiso.');
    $soloVisibles = !materia_puede_gestionar($con, $uid, $_rol, $mid);
    $sql = "SELECT id,titulo,descripcion,orden,visible FROM aula_secciones WHERE materia_id=?".
           ($soloVisibles ? " AND visible=1" : "")." ORDER BY orden, id";
    $st = mysqli_prepare($con, $sql);
    mysqli_stmt_bind_param($st, 'i', $mid);
    mysqli_stmt_execute($st);
    $r = mysqli_stmt_get_result($st);
    $rows = []; while ($f = mysqli_fetch_assoc($r)) $rows[] = $f;
    echo json_encode(['ok'=>true,'data'=>$rows]); exit;
}

if ($action === 'seccion_create') {
    $mid = (int)($_POST['materia_id'] ?? 0);
    if (!materia_puede_gestionar($con, $uid, $_rol, $mid)) json_fail('Sin permiso.');
    $titulo = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    if ($titulo === '') json_fail('Ponle un título a la sección.');
    if (mb_strlen($titulo) > 150) json_fail('El título es muy largo.');

    $stMax = mysqli_prepare($con, "SELECT COALESCE(MAX(orden),-1)+1 sig FROM aula_secciones WHERE materia_id=?");
    mysqli_stmt_bind_param($stMax, 'i', $mid);
    mysqli_stmt_execute($stMax);
    $orden = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($stMax))['sig'] ?? 0);

    $st = mysqli_prepare($con, "INSERT INTO aula_secciones(materia_id,titulo,descripcion,orden) VALUES(?,?,?,?)");
    mysqli_stmt_bind_param($st, 'issi', $mid, $titulo, $descripcion, $orden);
    if (!mysqli_stmt_execute($st)) json_fail('No se pudo crear la sección.');
    log_audit($con, $uid, 'AULA_SECCION_CREATE', "materia=$mid");
    echo json_encode(['ok'=>true,'msg'=>'Sección creada.','data'=>['id'=>mysqli_insert_id($con)]]); exit;
}

if ($action === 'seccion_update') {
    $id  = (int)($_POST['id'] ?? 0);
    $mid = (int)($_POST['materia_id'] ?? 0);
    if (!materia_puede_gestionar($con, $uid, $_rol, $mid)) json_fail('Sin permiso.');
    $titulo = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $visible = !empty($_POST['visible']) ? 1 : 0;
    if ($titulo === '') json_fail('Ponle un título a la sección.');

    $st = mysqli_prepare($con, "UPDATE aula_secciones SET titulo=?,descripcion=?,visible=? WHERE id=? AND materia_id=?");
    mysqli_stmt_bind_param($st, 'ssiii', $titulo, $descripcion, $visible, $id, $mid);
    mysqli_stmt_execute($st);
    echo json_encode(['ok'=>true,'msg'=>'Sección actualizada.']); exit;
}

if ($action === 'seccion_delete') {
    $id  = (int)($_POST['id'] ?? 0);
    $mid = (int)($_POST['materia_id'] ?? 0);
    if (!materia_puede_gestionar($con, $uid, $_rol, $mid)) json_fail('Sin permiso.');

    // Desagrupa el contenido (nunca lo borra) antes de quitar la sección.
    foreach (['aula_anuncios','aula_materiales','aula_actividades'] as $tabla) {
        $stN = mysqli_prepare($con, "UPDATE `$tabla` SET seccion_id=NULL WHERE seccion_id=? AND materia_id=?");
        mysqli_stmt_bind_param($stN, 'ii', $id, $mid);
        mysqli_stmt_execute($stN);
    }
    $st = mysqli_prepare($con, "DELETE FROM aula_secciones WHERE id=? AND materia_id=?");
    mysqli_stmt_bind_param($st, 'ii', $id, $mid);
    mysqli_stmt_execute($st);
    log_audit($con, $uid, 'AULA_SECCION_DELETE', "id=$id");
    echo json_encode(['ok'=>true,'msg'=>'Sección eliminada. Su contenido se movió a General.']); exit;
}

if ($action === 'seccion_reorder') {
    $mid = (int)($_POST['materia_id'] ?? 0);
    if (!materia_puede_gestionar($con, $uid, $_rol, $mid)) json_fail('Sin permiso.');
    $orden = json_decode($_POST['orden'] ?? '[]', true);
    if (!is_array($orden)) json_fail('Datos inválidos.');

    $st = mysqli_prepare($con, "UPDATE aula_secciones SET orden=? WHERE id=? AND materia_id=?");
    foreach ($orden as $pos => $seccionId) {
        $seccionId = (int)$seccionId;
        if (!$seccionId) continue;
        mysqli_stmt_bind_param($st, 'iii', $pos, $seccionId, $mid);
        mysqli_stmt_execute($st);
    }
    echo json_encode(['ok'=>true,'msg'=>'Orden guardado.']); exit;
}

// Confirma que, si se manda seccion_id, esa sección exista y sea de la
// misma materia — evita colgar contenido de la sección de otra materia.
if (!function_exists('aula_seccion_valida')) {
    function aula_seccion_valida($con, $mid, $seccionId) {
        if (!$seccionId) return null;
        $st = mysqli_prepare($con, "SELECT id FROM aula_secciones WHERE id=? AND materia_id=? LIMIT 1");
        mysqli_stmt_bind_param($st, 'ii', $seccionId, $mid);
        mysqli_stmt_execute($st);
        return mysqli_fetch_assoc(mysqli_stmt_get_result($st)) ? $seccionId : null;
    }
}

// Mueve un anuncio/material/actividad existente a otra sección (o a
// "General" si seccion_id llega vacío) sin tener que reabrir todo su
// formulario de edición — usado por el selector de sección en cada
// tarjeta de contenido del aula.
if ($action === 'contenido_mover_seccion') {
    $mid = (int)($_POST['materia_id'] ?? 0);
    if (!materia_puede_gestionar($con, $uid, $_rol, $mid)) json_fail('Sin permiso.');
    $tipo = trim($_POST['tipo'] ?? '');
    $id   = (int)($_POST['id'] ?? 0);
    $tablas = ['anuncio'=>'aula_anuncios','material'=>'aula_materiales','actividad'=>'aula_actividades'];
    if (!isset($tablas[$tipo]) || !$id) json_fail('Contenido no válido.');
    $seccionId = aula_seccion_valida($con, $mid, (int)($_POST['seccion_id'] ?? 0));

    $tabla = $tablas[$tipo];
    $st = mysqli_prepare($con, "UPDATE `$tabla` SET seccion_id=? WHERE id=? AND materia_id=?");
    mysqli_stmt_bind_param($st, 'iii', $seccionId, $id, $mid);
    mysqli_stmt_execute($st);
    echo json_encode(['ok'=>true,'msg'=>'Movido.']); exit;
}

/* ════ ANUNCIOS ═════════════════════════════════════════════ */
if ($action === 'anuncio_list') {
    $mid = (int)($_POST['materia_id'] ?? 0);
    if (!materia_puede_ver($con, $uid, $_rol, $mid)) json_fail('Sin permiso.');
    $st = mysqli_prepare($con, "SELECT a.id,a.titulo,a.contenido,a.fijado,a.seccion_id,a.creado_en,u.usuario autor
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
    if (!materia_puede_gestionar($con, $uid, $_rol, $mid)) json_fail('Sin permiso.');
    $titulo    = trim($_POST['titulo'] ?? '');
    $contenido = trim($_POST['contenido'] ?? '');
    $fijado    = !empty($_POST['fijado']) ? 1 : 0;
    $seccionId = aula_seccion_valida($con, $mid, (int)($_POST['seccion_id'] ?? 0));
    if ($titulo === '' || $contenido === '') json_fail('Completa título y contenido.');
    if (mb_strlen($titulo) > 150) json_fail('El título es muy largo.');

    $st = mysqli_prepare($con, "INSERT INTO aula_anuncios(materia_id,usuario_id,titulo,contenido,fijado,seccion_id) VALUES(?,?,?,?,?,?)");
    mysqli_stmt_bind_param($st, 'iissii', $mid, $uid, $titulo, $contenido, $fijado, $seccionId);
    if (!mysqli_stmt_execute($st)) json_fail('No se pudo publicar el anuncio.');
    log_audit($con, $uid, 'AULA_ANUNCIO_CREATE', "materia=$mid");
    notificar_materia($con, $mid, 'anuncio', "Nuevo anuncio: $titulo", $contenido, $uid);
    echo json_encode(['ok'=>true,'msg'=>'Anuncio publicado.']); exit;
}

if ($action === 'anuncio_update') {
    $id = (int)($_POST['id'] ?? 0);
    $mid = (int)($_POST['materia_id'] ?? 0);
    if (!materia_puede_gestionar($con, $uid, $_rol, $mid)) json_fail('Sin permiso.');
    $titulo    = trim($_POST['titulo'] ?? '');
    $contenido = trim($_POST['contenido'] ?? '');
    $fijado    = !empty($_POST['fijado']) ? 1 : 0;
    $seccionId = aula_seccion_valida($con, $mid, (int)($_POST['seccion_id'] ?? 0));
    if ($titulo === '' || $contenido === '') json_fail('Completa título y contenido.');

    // El WHERE incluye materia_id: evita editar un anuncio de otra materia
    // aunque alguien adivine el id.
    $st = mysqli_prepare($con, "UPDATE aula_anuncios SET titulo=?, contenido=?, fijado=?, seccion_id=? WHERE id=? AND materia_id=?");
    mysqli_stmt_bind_param($st, 'ssiiii', $titulo, $contenido, $fijado, $seccionId, $id, $mid);
    mysqli_stmt_execute($st);
    echo json_encode(['ok'=>true,'msg'=>'Anuncio actualizado.']); exit;
}

if ($action === 'anuncio_delete') {
    $id  = (int)($_POST['id'] ?? 0);
    $mid = (int)($_POST['materia_id'] ?? 0);
    if (!materia_puede_gestionar($con, $uid, $_rol, $mid)) json_fail('Sin permiso.');
    $st = mysqli_prepare($con, "DELETE FROM aula_anuncios WHERE id=? AND materia_id=?");
    mysqli_stmt_bind_param($st, 'ii', $id, $mid);
    mysqli_stmt_execute($st);
    log_audit($con, $uid, 'AULA_ANUNCIO_DELETE', "id=$id");
    echo json_encode(['ok'=>true,'msg'=>'Anuncio eliminado.']); exit;
}

/* ════ MATERIALES ═══════════════════════════════════════════ */
if ($action === 'material_list') {
    $mid = (int)($_POST['materia_id'] ?? 0);
    if (!materia_puede_ver($con, $uid, $_rol, $mid)) json_fail('Sin permiso.');
    $st = mysqli_prepare($con, "SELECT m.id,m.titulo,m.descripcion,m.archivo_nombre,m.archivo_tipo,m.tamano_bytes,m.seccion_id,m.creado_en,u.usuario autor
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
    if (!materia_puede_gestionar($con, $uid, $_rol, $mid)) json_fail('Sin permiso.');
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

    $seccionId = aula_seccion_valida($con, $mid, (int)($_POST['seccion_id'] ?? 0));
    $tamano = (int)$file['size'];
    $st = mysqli_prepare($con, "INSERT INTO aula_materiales(materia_id,usuario_id,titulo,descripcion,archivo,archivo_nombre,archivo_tipo,tamano_bytes,seccion_id) VALUES(?,?,?,?,?,?,?,?,?)");
    mysqli_stmt_bind_param($st, 'iisssssii', $mid, $uid, $titulo, $descripcion, $ruta, $nombreOriginal, $ext, $tamano, $seccionId);
    if (!mysqli_stmt_execute($st)) { @unlink($dir.$fname); json_fail('No se pudo registrar el material.'); }
    log_audit($con, $uid, 'AULA_MATERIAL_CREATE', "materia=$mid archivo=$fname");
    notificar_materia($con, $mid, 'material', "Nuevo material: $titulo", $descripcion, $uid);
    echo json_encode(['ok'=>true,'msg'=>'Material subido.']); exit;
}

if ($action === 'material_delete') {
    $id  = (int)($_POST['id'] ?? 0);
    $mid = (int)($_POST['materia_id'] ?? 0);
    if (!materia_puede_gestionar($con, $uid, $_rol, $mid)) json_fail('Sin permiso.');
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
    if (!$row || !materia_puede_ver($con, $uid, $_rol, (int)$row['materia_id'])) {
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
    if (!materia_puede_ver($con, $uid, $_rol, $mid)) json_fail('Sin permiso.');
    $st = mysqli_prepare($con, "SELECT id,titulo,descripcion,tipo,nota_max,fecha,seccion_id,creado_en FROM aula_actividades WHERE materia_id=? ORDER BY (fecha IS NULL), fecha DESC, creado_en DESC");
    mysqli_stmt_bind_param($st, 'i', $mid);
    mysqli_stmt_execute($st);
    $r = mysqli_stmt_get_result($st);
    $rows = []; while ($f = mysqli_fetch_assoc($r)) $rows[] = $f;
    echo json_encode(['ok'=>true,'data'=>$rows]); exit;
}

if ($action === 'actividad_create' || $action === 'actividad_update') {
    $mid = (int)($_POST['materia_id'] ?? 0);
    if (!materia_puede_gestionar($con, $uid, $_rol, $mid)) json_fail('Sin permiso.');
    $titulo      = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $tipo        = trim($_POST['tipo'] ?? 'actividad');
    $notaMax     = (float)($_POST['nota_max'] ?? 20);
    $fecha       = trim($_POST['fecha'] ?? '') ?: null;
    $seccionId   = aula_seccion_valida($con, $mid, (int)($_POST['seccion_id'] ?? 0));
    if ($titulo === '') json_fail('Ponle un título a la actividad.');
    if ($notaMax <= 0 || $notaMax > 1000) json_fail('La nota máxima no es válida.');
    if (!in_array($tipo, ['actividad','examen','taller','proyecto'])) $tipo = 'actividad';

    if ($action === 'actividad_create') {
        $st = mysqli_prepare($con, "INSERT INTO aula_actividades(materia_id,usuario_id,titulo,descripcion,tipo,nota_max,fecha,seccion_id) VALUES(?,?,?,?,?,?,?,?)");
        mysqli_stmt_bind_param($st, 'iisssdsi', $mid, $uid, $titulo, $descripcion, $tipo, $notaMax, $fecha, $seccionId);
        if (!mysqli_stmt_execute($st)) json_fail('No se pudo crear la actividad.');
        log_audit($con, $uid, 'AULA_ACTIVIDAD_CREATE', "materia=$mid");
        echo json_encode(['ok'=>true,'msg'=>'Actividad creada.']); exit;
    } else {
        $id = (int)($_POST['id'] ?? 0);
        $st = mysqli_prepare($con, "UPDATE aula_actividades SET titulo=?,descripcion=?,tipo=?,nota_max=?,fecha=?,seccion_id=? WHERE id=? AND materia_id=?");
        mysqli_stmt_bind_param($st, 'sssdsiii', $titulo, $descripcion, $tipo, $notaMax, $fecha, $seccionId, $id, $mid);
        mysqli_stmt_execute($st);
        echo json_encode(['ok'=>true,'msg'=>'Actividad actualizada.']); exit;
    }
}

if ($action === 'actividad_delete') {
    $id  = (int)($_POST['id'] ?? 0);
    $mid = (int)($_POST['materia_id'] ?? 0);
    if (!materia_puede_gestionar($con, $uid, $_rol, $mid)) json_fail('Sin permiso.');
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
    if (!materia_puede_gestionar($con, $uid, $_rol, $mid)) json_fail('Sin permiso.');

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

// Para el alumno: sus propias actividades de la materia con su nota (si existe), en una sola llamada.
if ($action === 'actividad_mis_notas') {
    $mid = (int)($_POST['materia_id'] ?? 0);
    if (!materia_puede_ver($con, $uid, $_rol, $mid)) json_fail('Sin permiso.');

    $al = mysqli_fetch_assoc(mysqli_query($con, "SELECT id FROM alumnos WHERE usuario_id=$uid LIMIT 1"));
    if (!$al) json_fail('No se encontró el registro de alumno.');
    $alumnoId = (int)$al['id'];

    $st = mysqli_prepare($con, "SELECT act.id,act.titulo,act.tipo,act.nota_max,act.fecha,
                                        c.nota,c.observacion
                                 FROM aula_actividades act
                                 LEFT JOIN aula_calificaciones c ON c.actividad_id=act.id AND c.alumno_id=?
                                 WHERE act.materia_id=?
                                 ORDER BY act.creado_en DESC");
    mysqli_stmt_bind_param($st, 'ii', $alumnoId, $mid);
    mysqli_stmt_execute($st);
    $r = mysqli_stmt_get_result($st);
    $rows = []; while ($f = mysqli_fetch_assoc($r)) $rows[] = $f;
    echo json_encode(['ok'=>true,'data'=>$rows]); exit;
}

// Guarda todas las notas de una actividad de una vez (una fila por alumno).
if ($action === 'actividad_calificar_bulk') {
    $aid = (int)($_POST['actividad_id'] ?? 0);
    $mid = (int)($_POST['materia_id'] ?? 0);
    if (!materia_puede_gestionar($con, $uid, $_rol, $mid)) json_fail('Sin permiso.');

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
