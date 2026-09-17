<?php
require_once __DIR__.'/config/bootstrap.php';

if (empty($_SESSION['loggedin']) || $_SESSION['rol'] !== 'docente') {
    header('Location: login.php');
    exit;
}

$con = db();
$user_id = $_SESSION['user_id'];
$nombre = trim($_POST['nombre'] ?? '');
$apellido = trim($_POST['apellido'] ?? '');
$email = trim($_POST['email'] ?? '');
$telefono = trim($_POST['telefono'] ?? '');

$foto_nombre = null;

// Procesar subida de imagen si existe
if (isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = __DIR__.'/uploads/perfiles/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
    
    $file_ext = strtolower(pathinfo($_FILES['foto_perfil']['name'], PATHINFO_EXTENSION));
    $foto_nombre = uniqid('prof_') . '.' . $file_ext;
    move_uploaded_file($_FILES['foto_perfil']['tmp_name'], $upload_dir . $foto_nombre);
}

// Actualizar base de datos
if ($foto_nombre) {
    $stmt = mysqli_prepare($con, "UPDATE docentes SET nombre=?, apellido=?, email=?, telefono=?, foto_perfil=? WHERE usuario_id=?");
    mysqli_stmt_bind_param($stmt, "sssssi", $nombre, $apellido, $email, $telefono, $foto_nombre, $user_id);
} else {
    $stmt = mysqli_prepare($con, "UPDATE docentes SET nombre=?, apellido=?, email=?, telefono=? WHERE usuario_id=?");
    mysqli_stmt_bind_param($stmt, "ssssi", $nombre, $apellido, $email, $telefono, $user_id);
}
mysqli_stmt_execute($stmt);

// Actualizar nombre en sesión
if (!empty($nombre)) {
    $_SESSION['usuario'] = $nombre;
}

header('Location: portal_docente.php?msg=perfil_actualizado');
exit;