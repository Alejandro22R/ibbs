<?php
ob_start(); error_reporting(0);
require_once __DIR__.'/../config/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
if (empty($_SESSION['loggedin'])) { echo json_encode(['ok'=>false,'msg'=>'Sesión expirada.']); exit; }
if (!csrf_verify($_POST['csrf_token'] ?? '')) { echo json_encode(['ok'=>false,'msg'=>'Token de seguridad inválido. Recarga la página e intenta de nuevo.']); exit; }

$uid  = (int)($_SESSION['user_id']??0);
$tipo = trim($_POST['tipo']??'usuario'); // usuario | docente | alumno
$rid  = (int)($_POST['id']??$uid);

// Validar permisos: solo superadmin/admin pueden cambiar foto de otros
$rol = $_SESSION['rol']??'alumno';
if ($tipo!=='usuario' && !in_array($rol,['superadmin','admin'])) {
    if ($rid != $uid) { echo json_encode(['ok'=>false,'msg'=>'Sin permiso.']); exit; }
}

if (!isset($_FILES['foto']) || $_FILES['foto']['error']!==UPLOAD_ERR_OK) {
    echo json_encode(['ok'=>false,'msg'=>'No se recibió ningún archivo.']); exit;
}

$file = $_FILES['foto'];
$ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$allowed = ['jpg','jpeg','png','gif','webp'];
if (!in_array($ext, $allowed)) { echo json_encode(['ok'=>false,'msg'=>'Solo imágenes (jpg, png, gif, webp).']); exit; }
if ($file['size'] > 3*1024*1024) { echo json_encode(['ok'=>false,'msg'=>'Imagen muy grande (máx. 3MB).']); exit; }

// Verifica que el contenido sea realmente una imagen (no solo la extensión) —
// evita que se suba un .php disfrazado con extensión .jpg, por ejemplo.
$imgInfo = @getimagesize($file['tmp_name']);
$allowedMime = ['image/jpeg','image/png','image/gif','image/webp'];
if ($imgInfo === false || !in_array($imgInfo['mime'] ?? '', $allowedMime)) {
    echo json_encode(['ok'=>false,'msg'=>'El archivo no es una imagen válida.']); exit;
}

// Ensure directory exists
$dir = __DIR__.'/../uploads/fotos/';
if (!is_dir($dir)) mkdir($dir, 0755, true);

// Delete old photo
$con = db();
if (!$con) { echo json_encode(['ok'=>false,'msg'=>'Error de conexión a la base de datos.']); exit; }

$tabla = $tipo==='docente' ? 'docentes' : ($tipo==='alumno' ? 'alumnos' : 'usuarios');
$old = mysqli_fetch_assoc(mysqli_query($con,"SELECT foto FROM $tabla WHERE id=$rid"))['foto']??'';
if ($old && file_exists(__DIR__.'/../'.$old)) @unlink(__DIR__.'/../'.$old);

$fname = $tipo.'_'.$rid.'_'.time().'.'.$ext;
$dest  = $dir.$fname;
if (!move_uploaded_file($file['tmp_name'], $dest)) {
    echo json_encode(['ok'=>false,'msg'=>'Error al guardar la imagen.']); exit;
}

$ruta = 'uploads/fotos/'.$fname;
$st = mysqli_prepare($con,"UPDATE $tabla SET foto=? WHERE id=?");
mysqli_stmt_bind_param($st,'si',$ruta,$rid); mysqli_stmt_execute($st);

// If updating own user photo, refresh session
if ($tipo==='usuario' && $rid===$uid) {
    $_SESSION['foto'] = $ruta;
}

echo json_encode(['ok'=>true,'msg'=>'Foto actualizada.','foto'=>$ruta]);
