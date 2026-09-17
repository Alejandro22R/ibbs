<?php
require_once __DIR__.'/config/bootstrap.php';
header('Content-Type: application/json');

if (empty($_SESSION['loggedin']) || $_SESSION['rol'] !== 'docente') {
    echo json_encode(['ok' => false, 'msg' => 'No autorizado']);
    exit;
}

$con = db();
$entrega_id = (int)($_POST['entrega_id'] ?? 0);
$nota = trim($_POST['nota'] ?? '');

if (!$entrega_id || $nota === '') {
    echo json_encode(['ok' => false, 'msg' => 'Faltan datos obligatorios para calificar.']);
    exit;
}

$nota_num = (float)$nota;

// Actualizar la nota de la entrega
$stmt = mysqli_prepare($con, "UPDATE entregas SET nota = ? WHERE id = ?");
mysqli_stmt_bind_param($stmt, "di", $nota_num, $entrega_id);

if (mysqli_stmt_execute($stmt)) {
    echo json_encode(['ok' => true, 'msg' => 'Calificación registrada correctamente.']);
} else {
    echo json_encode(['ok' => false, 'msg' => 'Error al registrar la calificación.']);
}