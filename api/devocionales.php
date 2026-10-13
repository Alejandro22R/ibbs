<?php
/**
 * IBBS — Devocionales / estudios.
 *
 * Un profesor o administrador publica un estudio (texto y/o archivo
 * adjunto — PDF, Word, imagen) que llega como notificación a los
 * alumnos y docentes a los que les corresponde verlo. `materia_id`
 * NULL = para toda la institución; con valor = solo para los
 * inscritos/el equipo docente de esa materia puntual (un profesor solo
 * puede publicar en sus propias materias o de forma general).
 */

ob_start();
error_reporting(0);
require_once __DIR__.'/../config/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
ob_clean();

if (empty($_SESSION['loggedin'])) { echo json_encode(['ok'=>false,'msg'=>'Sesión expirada.']); exit; }
if (!csrf_verify($_POST['csrf_token'] ?? '')) { echo json_encode(['ok'=>false,'msg'=>'Token de seguridad inválido. Recarga la página.']); exit; }

$con = db();
if (!$con) { echo json_encode(['ok'=>false,'msg'=>'Error de conexión a la base de datos.']); exit; }

$uid    = (int)($_SESSION['user_id'] ?? 0);
$rol    = $_SESSION['rol'] ?? 'alumno';
$nombreUsuario = $_SESSION['usuario'] ?? 'Alguien';
$action = trim($_POST['action'] ?? '');

function json_fail_dv($msg) { echo json_encode(['ok'=>false,'msg'=>$msg]); exit; }

/* ════ PUBLICAR ══════════════════════════════════════════════════ */
if ($action === 'crear') {
    if (!in_array($rol, ['superadmin','admin','profesor'])) json_fail_dv('Solo un profesor o administrador puede publicar un devocional.');

    $titulo    = trim($_POST['titulo'] ?? '');
    $contenido = trim($_POST['contenido'] ?? '');
    $mid       = (int)($_POST['materia_id'] ?? 0) ?: null;
    if ($titulo === '') json_fail_dv('Ponle un título al devocional.');
    if ($contenido === '' && empty($_FILES['archivo']['name'])) json_fail_dv('Escribe algo o adjunta un archivo.');

    if ($mid) {
        if (!materia_puede_gestionar($con, $uid, $rol, $mid)) json_fail_dv('No tienes esa materia a tu cargo.');
    }

    $archivoRuta = null; $archivoNombre = null;
    if (!empty($_FILES['archivo']['name'])) {
        if ($_FILES['archivo']['error'] !== UPLOAD_ERR_OK) json_fail_dv('No se pudo recibir el archivo.');
        $file = $_FILES['archivo'];
        if ($file['size'] > 25 * 1024 * 1024) json_fail_dv('Archivo muy grande (máx. 25MB).');
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $tiposPermitidos = [
            'pdf'  => ['application/pdf'],
            'doc'  => ['application/msword'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
            'ppt'  => ['application/vnd.ms-powerpoint'],
            'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip'],
            'jpg'  => ['image/jpeg'], 'jpeg' => ['image/jpeg'],
            'png'  => ['image/png'], 'webp' => ['image/webp'],
        ];
        if (!isset($tiposPermitidos[$ext])) json_fail_dv('Tipo de archivo no permitido. Usa PDF, Word, PowerPoint o una imagen.');
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if (!in_array($mime, $tiposPermitidos[$ext])) json_fail_dv('El contenido del archivo no coincide con su extensión.');

        $dir = __DIR__.'/../uploads/devocionales/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $fname = 'dev_'.$uid.'_'.time().'_'.bin2hex(random_bytes(8)).'.'.$ext;
        if (!move_uploaded_file($file['tmp_name'], $dir.$fname)) json_fail_dv('No se pudo guardar el archivo.');
        $archivoRuta = 'uploads/devocionales/'.$fname;
        $archivoNombre = mb_substr(basename($file['name']), 0, 250);
    }

    // Nombre legible de quien publica (no el usuario de login) — se
    // guarda ya resuelto en la fila para no tener que volver a hacer
    // el JOIN con alumnos/docentes cada vez que se lista.
    $publicadorNombre = $nombreUsuario;
    if ($rol === 'profesor') {
        $d = mysqli_fetch_assoc(mysqli_query($con, "SELECT nombre,apellido FROM docentes WHERE usuario_id=$uid LIMIT 1"));
        if ($d) $publicadorNombre = trim($d['nombre'].' '.$d['apellido']);
    } elseif (in_array($rol, ['admin','superadmin'])) {
        $publicadorNombre = $nombreUsuario;
    }

    $st = mysqli_prepare($con, "INSERT INTO devocionales(titulo,contenido,archivo,archivo_nombre,materia_id,publicado_por,publicado_por_nombre) VALUES(?,?,?,?,?,?,?)");
    mysqli_stmt_bind_param($st, 'ssssiis', $titulo, $contenido, $archivoRuta, $archivoNombre, $mid, $uid, $publicadorNombre);
    if (!mysqli_stmt_execute($st)) {
        if ($archivoRuta && file_exists(__DIR__.'/../'.$archivoRuta)) @unlink(__DIR__.'/../'.$archivoRuta);
        json_fail_dv('No se pudo publicar el devocional.');
    }
    $devId = mysqli_insert_id($con);
    log_audit($con, $uid, 'DEVOCIONAL_CREAR', "id=$devId".($mid?" materia=$mid":' general'));

    // Audiencia: si es de una materia puntual, sus alumnos inscritos y
    // los docentes asignados (menos quien publica); si es general, toda
    // la institución (alumnos y docentes activos, menos quien publica).
    $titN = "Devocional de parte de $publicadorNombre";
    $msgN = $titulo;
    $destinatarios = [];
    if ($mid) {
        $r1 = mysqli_query($con, "SELECT u.id FROM usuarios u JOIN alumnos a ON a.usuario_id=u.id JOIN materia_alumno ma ON ma.alumno_id=a.id WHERE ma.materia_id=$mid AND u.activo=1 AND u.id!=$uid");
        while ($f = mysqli_fetch_assoc($r1)) $destinatarios[] = (int)$f['id'];
        $r2 = mysqli_query($con, "SELECT u.id FROM usuarios u JOIN docentes d ON d.usuario_id=u.id JOIN materia_docente md ON md.docente_id=d.id WHERE md.materia_id=$mid AND u.activo=1 AND u.id!=$uid");
        while ($f = mysqli_fetch_assoc($r2)) $destinatarios[] = (int)$f['id'];
    } else {
        $r1 = mysqli_query($con, "SELECT id FROM usuarios WHERE rol IN ('alumno','profesor') AND activo=1 AND id!=$uid");
        while ($f = mysqli_fetch_assoc($r1)) $destinatarios[] = (int)$f['id'];
    }
    $destinatarios = array_unique($destinatarios);
    foreach ($destinatarios as $destId) {
        notificar_usuario($con, $destId, 'devocional', $titN, $msgN, $mid);
    }

    echo json_encode(['ok'=>true,'msg'=>'¡Devocional publicado!','id'=>$devId]); exit;
}

/* ════ LISTAR (lo que le corresponde ver a este usuario) ════════════ */
if ($action === 'listar') {
    if (in_array($rol, ['superadmin','admin'])) {
        $r = mysqli_query($con, "SELECT d.*, m.nombre materia_nombre FROM devocionales d LEFT JOIN materias m ON m.id=d.materia_id ORDER BY d.creado_en DESC LIMIT 200");
    } elseif ($rol === 'profesor') {
        $r = mysqli_query($con, "SELECT d.*, m.nombre materia_nombre FROM devocionales d LEFT JOIN materias m ON m.id=d.materia_id
            WHERE d.materia_id IS NULL OR d.materia_id IN (SELECT md.materia_id FROM materia_docente md JOIN docentes doc ON doc.id=md.docente_id WHERE doc.usuario_id=$uid)
            ORDER BY d.creado_en DESC LIMIT 200");
    } elseif ($rol === 'alumno') {
        $r = mysqli_query($con, "SELECT d.*, m.nombre materia_nombre FROM devocionales d LEFT JOIN materias m ON m.id=d.materia_id
            WHERE d.materia_id IS NULL OR d.materia_id IN (SELECT ma.materia_id FROM materia_alumno ma JOIN alumnos al ON al.id=ma.alumno_id WHERE al.usuario_id=$uid)
            ORDER BY d.creado_en DESC LIMIT 200");
    } else {
        echo json_encode(['ok'=>true,'data'=>[]]); exit;
    }
    $rows = []; while ($f = mysqli_fetch_assoc($r)) $rows[] = $f;
    echo json_encode(['ok'=>true,'data'=>$rows]); exit;
}

/* ════ ELIMINAR (autor o admin/superadmin) ══════════════════════════ */
if ($action === 'eliminar') {
    $id = (int)($_POST['id'] ?? 0);
    $d = mysqli_fetch_assoc(mysqli_query($con, "SELECT publicado_por,archivo FROM devocionales WHERE id=$id LIMIT 1"));
    if (!$d) json_fail_dv('No encontrado.');
    $esAutor = (int)$d['publicado_por'] === $uid;
    $esAdmin = in_array($rol, ['superadmin','admin']);
    if (!$esAutor && !$esAdmin) json_fail_dv('No tienes permiso para borrar esto.');
    mysqli_query($con, "DELETE FROM devocionales WHERE id=$id");
    if ($d['archivo'] && file_exists(__DIR__.'/../'.$d['archivo'])) @unlink(__DIR__.'/../'.$d['archivo']);
    log_audit($con, $uid, 'DEVOCIONAL_ELIMINAR', "id=$id");
    echo json_encode(['ok'=>true,'msg'=>'Eliminado.']); exit;
}

json_fail_dv('Acción no reconocida.');
