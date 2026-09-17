<?php
require_once __DIR__.'/config/bootstrap.php';
if (empty($_SESSION['loggedin'])) {
    echo json_encode(['ok' => false, 'msg' => 'No autorizado']);
    exit;
}

$con = db();
$materia_id = (int)($_POST['materia_id'] ?? 0);
$mensaje = trim($_POST['mensaje'] ?? '');
$respuesta_a = !empty($_POST['respuesta_a']) ? (int)$_POST['respuesta_a'] : null;

if (!$materia_id || !$mensaje) {
    echo json_encode(['ok' => false, 'msg' => 'Datos incompletos']);
    exit;
}

$usuario_nombre = $_SESSION['usuario'];
$rol = $_SESSION['rol'];

$stmt = mysqli_prepare($con, "INSERT INTO foro_mensajes (materia_id, usuario_nombre, rol, mensaje, respuesta_a) VALUES (?, ?, ?, ?, ?)");
mysqli_stmt_bind_param($stmt, "isssi", $materia_id, $usuario_nombre, $rol, $mensaje, $respuesta_a);

if (mysqli_stmt_execute($stmt)) {
    echo json_encode(['ok' => true, 'id' => mysqli_insert_id($con)]);
} else {
    echo json_encode(['ok' => false, 'msg' => 'Error al guardar el mensaje.']);
}