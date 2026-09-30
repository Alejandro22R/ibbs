<?php
/**
 * IBBS — Actualizar perfil del alumno (portal_alumno.php → Configuración
 * de Perfil). Análogo a actualizar_perfil_docente.php pero sobre la
 * tabla `alumnos`, con las columnas reales (correo, foto — el
 * formulario antes mandaba email/foto_perfil, que no existen).
 */
require_once __DIR__.'/config/bootstrap.php';

if (empty($_SESSION['loggedin']) || $_SESSION['rol'] !== 'alumno') {
    header('Location: login.php');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: portal_alumno.php');
    exit;
}
if (!csrf_verify($_POST['csrf_token'] ?? '')) {
    header('Location: portal_alumno.php?msg=csrf_invalido');
    exit;
}

$con = db();
if (!$con) { header('Location: portal_alumno.php?msg=error'); exit; }

$user_id = (int)$_SESSION['user_id'];
$al = mysqli_fetch_assoc(mysqli_query($con, "SELECT id FROM alumnos WHERE usuario_id=$user_id LIMIT 1"));
if (!$al) { header('Location: portal_alumno.php?msg=error'); exit; }
$alumno_id = (int)$al['id'];

$nombre   = trim($_POST['nombre'] ?? '');
$apellido = trim($_POST['apellido'] ?? '');
$correo   = trim($_POST['correo'] ?? '');
$telefono = trim($_POST['telefono'] ?? '');

if ($nombre === '' || $apellido === '') {
    header('Location: portal_alumno.php?msg=faltan_campos');
    exit;
}

// El correo no puede quedar duplicado con otro alumno.
if ($correo !== '') {
    $ck = mysqli_prepare($con, "SELECT id FROM alumnos WHERE correo=? AND id!=?");
    mysqli_stmt_bind_param($ck, 'si', $correo, $alumno_id);
    mysqli_stmt_execute($ck);
    mysqli_stmt_store_result($ck);
    if (mysqli_stmt_num_rows($ck) > 0) {
        header('Location: portal_alumno.php?msg=correo_duplicado');
        exit;
    }
}

// Foto de perfil (mismas validaciones que api/upload_foto.php: extensión
// + tipo MIME real, nunca confiar solo en el nombre del archivo).
$foto_ruta = null;
if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
    $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
    $permitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (in_array($ext, $permitidas) && $_FILES['foto']['size'] <= 3 * 1024 * 1024) {
        $imgInfo = @getimagesize($_FILES['foto']['tmp_name']);
        $mimePermitidos = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if ($imgInfo !== false && in_array($imgInfo['mime'] ?? '', $mimePermitidos)) {
            $dir = __DIR__.'/uploads/fotos/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $anterior = mysqli_fetch_assoc(mysqli_query($con, "SELECT foto FROM alumnos WHERE id=$alumno_id"))['foto'] ?? '';
            if ($anterior && file_exists(__DIR__.'/'.$anterior)) @unlink(__DIR__.'/'.$anterior);
            $fname = 'alumno_'.$alumno_id.'_'.time().'.'.$ext;
            if (move_uploaded_file($_FILES['foto']['tmp_name'], $dir.$fname)) {
                $foto_ruta = 'uploads/fotos/'.$fname;
            }
        }
    }
}

if ($foto_ruta) {
    $st = mysqli_prepare($con, "UPDATE alumnos SET nombre=?, apellido=?, correo=?, telefono=?, foto=? WHERE id=?");
    mysqli_stmt_bind_param($st, 'sssssi', $nombre, $apellido, $correo, $telefono, $foto_ruta, $alumno_id);
} else {
    $st = mysqli_prepare($con, "UPDATE alumnos SET nombre=?, apellido=?, correo=?, telefono=? WHERE id=?");
    mysqli_stmt_bind_param($st, 'ssssi', $nombre, $apellido, $correo, $telefono, $alumno_id);
}
mysqli_stmt_execute($st);

// El nombre mostrado en la sesión (header, sidebar) se actualiza al toque.
if ($nombre !== '') {
    $_SESSION['usuario'] = $nombre.' '.$apellido;
}

log_audit($con, $user_id, 'ALUMNO_PERFIL_ACTUALIZAR', "alumno=$alumno_id");

header('Location: portal_alumno.php?msg=perfil_actualizado');
exit;
