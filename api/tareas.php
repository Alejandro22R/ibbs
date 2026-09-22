<?php
/**
 * IBBS — Tareas y entregas (endpoint), usado desde la pestaña
 * "Tareas" de modulo_aula.php. Requiere las tablas `tareas`/`entregas`.
 *
 * Todas las acciones exigen sesión iniciada. Las que modifican datos
 * exigen además el token CSRF y permiso de gestión sobre la materia
 * (materia_puede_gestionar, en config/materia_permisos.php) para
 * docente/admin, o ser el alumno dueño de la entrega.
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
$action = trim($_POST['action'] ?? $_GET['action'] ?? '');

if (!function_exists('json_fail')) {
    function json_fail($msg) { echo json_encode(['ok'=>false,'msg'=>$msg]); exit; }
}

/** id del alumno (tabla alumnos) dueño de la sesión actual, o 0 si no aplica. */
function tareas_alumno_id($con, $uid) {
    $st = mysqli_prepare($con, "SELECT id FROM alumnos WHERE usuario_id=? LIMIT 1");
    mysqli_stmt_bind_param($st, 'i', $uid);
    mysqli_stmt_execute($st);
    return (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($st))['id'] ?? 0);
}

/** Sube un archivo adjunto validando extensión + tipo MIME real. Devuelve el nombre generado o null. */
function tareas_subir_archivo($fileField, $folder) {
    if (!isset($_FILES[$fileField]) || $_FILES[$fileField]['error'] !== UPLOAD_ERR_OK) return null;
    $file = $_FILES[$fileField];
    if ($file['size'] > 25 * 1024 * 1024) json_fail('Archivo muy grande (máx. 25MB).');

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $tiposPermitidos = [
        'pdf'=>['application/pdf'], 'doc'=>['application/msword'],
        'docx'=>['application/vnd.openxmlformats-officedocument.wordprocessingml.document','application/zip'],
        'ppt'=>['application/vnd.ms-powerpoint'],
        'pptx'=>['application/vnd.openxmlformats-officedocument.presentationml.presentation','application/zip'],
        'xls'=>['application/vnd.ms-excel'],
        'xlsx'=>['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','application/zip'],
        'txt'=>['text/plain'], 'csv'=>['text/plain','text/csv'],
        'zip'=>['application/zip','application/x-zip-compressed'],
        'jpg'=>['image/jpeg'],'jpeg'=>['image/jpeg'],'png'=>['image/png'],'gif'=>['image/gif'],'webp'=>['image/webp'],
    ];
    if (!isset($tiposPermitidos[$ext])) json_fail('Tipo de archivo no permitido.');
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mime, $tiposPermitidos[$ext])) json_fail('El contenido del archivo no coincide con su extensión.');

    $dir = __DIR__."/../uploads/$folder/";
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $filename = bin2hex(random_bytes(8)).'_'.time().'.'.$ext;
    if (!move_uploaded_file($file['tmp_name'], $dir.$filename)) json_fail('No se pudo guardar el archivo.');
    return $filename;
}

/* ════ 1. LISTAR TAREAS ═══════════════════════════════════════════ */
if ($action === 'list') {
    $mid = (int)($_GET['materia_id'] ?? $_POST['materia_id'] ?? 0);
    if (!materia_puede_ver($con, $uid, $_rol, $mid)) json_fail('Sin permiso.');

    if (materia_puede_gestionar($con, $uid, $_rol, $mid)) {
        $st = mysqli_prepare($con, "SELECT * FROM tareas WHERE materia_id=? ORDER BY fecha_limite DESC");
        mysqli_stmt_bind_param($st, 'i', $mid);
    } else {
        $alumnoId = tareas_alumno_id($con, $uid);
        $st = mysqli_prepare($con, "SELECT t.*, e.id entrega_id, e.fecha_entrega, e.nota, e.observacion_docente
                                     FROM tareas t
                                     LEFT JOIN entregas e ON t.id=e.tarea_id AND e.alumno_id=?
                                     WHERE t.materia_id=? ORDER BY t.fecha_limite DESC");
        mysqli_stmt_bind_param($st, 'ii', $alumnoId, $mid);
    }
    mysqli_stmt_execute($st);
    $r = mysqli_stmt_get_result($st);
    $rows = []; while ($f = mysqli_fetch_assoc($r)) $rows[] = $f;
    echo json_encode(['ok'=>true,'data'=>$rows]); exit;
}

/* ════ 2. DOCENTE: CREAR TAREA ═════════════════════════════════════ */
if ($action === 'crear') {
    $mid = (int)($_POST['materia_id'] ?? 0);
    if (!materia_puede_gestionar($con, $uid, $_rol, $mid)) json_fail('Sin permiso.');

    $titulo      = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $fechaLimite = trim($_POST['fecha_limite'] ?? '') ?: date('Y-m-d H:i:s');
    $notaMax     = (float)($_POST['nota_maxima'] ?? 20);
    if ($titulo === '') json_fail('Ponle un título a la tarea.');
    if ($notaMax <= 0 || $notaMax > 1000) json_fail('La nota máxima no es válida.');

    $archivo = tareas_subir_archivo('archivo', 'tareas');

    $st = mysqli_prepare($con, "INSERT INTO tareas(materia_id,titulo,descripcion,archivo,fecha_limite,nota_maxima) VALUES(?,?,?,?,?,?)");
    mysqli_stmt_bind_param($st, 'issssd', $mid, $titulo, $descripcion, $archivo, $fechaLimite, $notaMax);
    if (!mysqli_stmt_execute($st)) json_fail('No se pudo crear la tarea.');
    log_audit($con, $uid, 'TAREA_CREATE', "materia=$mid");
    notificar_materia($con, $mid, 'tarea', "Nueva tarea: $titulo", $descripcion, $uid);
    echo json_encode(['ok'=>true,'msg'=>'Tarea creada exitosamente.']); exit;
}

/* ════ 3. ALUMNO: ENTREGAR TAREA ═══════════════════════════════════ */
if ($action === 'entregar') {
    if ($_rol !== 'alumno') json_fail('Solo un alumno puede entregar tareas.');
    $mid = (int)($_POST['materia_id'] ?? 0);
    if (!materia_puede_ver($con, $uid, $_rol, $mid)) json_fail('Sin permiso.');
    $alumnoId = tareas_alumno_id($con, $uid);
    if (!$alumnoId) json_fail('No se pudo identificar al alumno.');

    $tareaId = (int)($_POST['tarea_id'] ?? 0);
    $texto   = trim($_POST['texto_respuesta'] ?? '');

    // La tarea debe pertenecer a la materia sobre la que se validó el permiso.
    $stT = mysqli_prepare($con, "SELECT id FROM tareas WHERE id=? AND materia_id=? LIMIT 1");
    mysqli_stmt_bind_param($stT, 'ii', $tareaId, $mid);
    mysqli_stmt_execute($stT);
    if (!mysqli_fetch_row(mysqli_stmt_get_result($stT))) json_fail('Tarea no encontrada.');

    $archivo = tareas_subir_archivo('archivo', 'entregas');
    if (!$texto && !$archivo) json_fail('Debes escribir algo o subir un archivo.');

    $st = mysqli_prepare($con, "INSERT INTO entregas(tarea_id,alumno_id,texto_respuesta,archivo,fecha_entrega)
                                 VALUES(?,?,?,?,NOW())
                                 ON DUPLICATE KEY UPDATE texto_respuesta=VALUES(texto_respuesta), archivo=COALESCE(VALUES(archivo),archivo), fecha_entrega=NOW()");
    mysqli_stmt_bind_param($st, 'iiss', $tareaId, $alumnoId, $texto, $archivo);
    if (!mysqli_stmt_execute($st)) json_fail('No se pudo guardar la entrega.');
    log_audit($con, $uid, 'TAREA_ENTREGAR', "tarea=$tareaId");
    echo json_encode(['ok'=>true,'msg'=>'Tarea entregada correctamente.']); exit;
}

/* ════ 4. DOCENTE: VER ENTREGAS DE UNA TAREA ═══════════════════════ */
if ($action === 'ver_entregas') {
    $mid = (int)($_GET['materia_id'] ?? 0);
    if (!materia_puede_gestionar($con, $uid, $_rol, $mid)) json_fail('Sin permiso.');
    $tareaId = (int)($_GET['tarea_id'] ?? 0);

    $st = mysqli_prepare($con, "SELECT a.id alumno_id,a.nombre,a.apellido,a.cedula,
                                        e.id entrega_id,e.texto_respuesta,e.archivo,e.fecha_entrega,e.nota,e.observacion_docente
                                 FROM materia_alumno ma
                                 JOIN alumnos a ON ma.alumno_id=a.id
                                 LEFT JOIN entregas e ON e.alumno_id=a.id AND e.tarea_id=?
                                 WHERE ma.materia_id=? ORDER BY a.apellido ASC");
    mysqli_stmt_bind_param($st, 'ii', $tareaId, $mid);
    mysqli_stmt_execute($st);
    $r = mysqli_stmt_get_result($st);
    $rows = []; while ($f = mysqli_fetch_assoc($r)) $rows[] = $f;
    echo json_encode(['ok'=>true,'data'=>$rows]); exit;
}

/* ════ 5. DOCENTE: CALIFICAR ENTREGA ════════════════════════════════
 * El frontend no manda materia_id acá — se obtiene de la propia
 * entrega (vía tarea) para poder validar el permiso igual. */
if ($action === 'calificar') {
    $entregaId = (int)($_POST['entrega_id'] ?? 0);
    if (!$entregaId) json_fail('ID de entrega no válido.');

    $st = mysqli_prepare($con, "SELECT e.alumno_id, t.materia_id, t.titulo, a.usuario_id
                                 FROM entregas e
                                 JOIN tareas t ON t.id=e.tarea_id
                                 JOIN alumnos a ON a.id=e.alumno_id
                                 WHERE e.id=? LIMIT 1");
    mysqli_stmt_bind_param($st, 'i', $entregaId);
    mysqli_stmt_execute($st);
    $info = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
    if (!$info) json_fail('Entrega no encontrada.');
    if (!materia_puede_gestionar($con, $uid, $_rol, (int)$info['materia_id'])) json_fail('Sin permiso.');

    $notaRaw = $_POST['nota'] ?? '';
    $nota = $notaRaw === '' ? null : (float)$notaRaw;
    $obs  = mb_substr(trim($_POST['observacion'] ?? ''), 0, 255);

    $st2 = mysqli_prepare($con, "UPDATE entregas SET nota=?, observacion_docente=? WHERE id=?");
    mysqli_stmt_bind_param($st2, 'dsi', $nota, $obs, $entregaId);
    mysqli_stmt_execute($st2);
    log_audit($con, $uid, 'TAREA_CALIFICAR', "entrega=$entregaId");
    if ($info['usuario_id']) {
        notificar_usuario($con, (int)$info['usuario_id'], 'calificacion', "Tarea calificada: {$info['titulo']}", $nota !== null ? "Tu nota: $nota" : 'Se agregó una observación a tu entrega.', (int)$info['materia_id']);
    }
    echo json_encode(['ok'=>true,'msg'=>'Calificación guardada.']); exit;
}

echo json_encode(['ok'=>false,'msg'=>'Acción no reconocida.']);
