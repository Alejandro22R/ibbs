<?php
require_once __DIR__.'/config/bootstrap.php';
if (empty($_SESSION['loggedin'])) {
    echo json_encode(['ok' => false, 'mensajes' => []]);
    exit;
}

$con = db();
$materia_id = (int)($_GET['materia_id'] ?? 0);
$ultimo_id = (int)($_GET['ultimo_id'] ?? 0);

if (!$materia_id) {
    echo json_encode(['ok' => false, 'mensajes' => []]);
    exit;
}

$query = "SELECT f.*, r.usuario_nombre as respuesta_a_nombre 
          FROM foro_mensajes f 
          LEFT JOIN foro_mensajes r ON f.respuesta_a = r.id 
          WHERE f.materia_id = ? AND f.id > ? 
          ORDER BY f.id ASC";

$stmt = mysqli_prepare($con, $query);
mysqli_stmt_bind_param($stmt, "ii", $materia_id, $ultimo_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

$mensajes = [];
while ($row = mysqli_fetch_assoc($res)) {
    $row['hora'] = date('H:i', strtotime($row['fecha']));
    $row['mensaje'] = htmlspecialchars($row['mensaje'], ENT_QUOTES);
    $row['usuario_nombre'] = htmlspecialchars($row['usuario_nombre'], ENT_QUOTES);
    if ($row['respuesta_a_nombre']) {
        $row['respuesta_a_nombre'] = htmlspecialchars($row['respuesta_a_nombre'], ENT_QUOTES);
    }
    $mensajes[] = $row;
}

echo json_encode(['ok' => true, 'mensajes' => $mensajes]);