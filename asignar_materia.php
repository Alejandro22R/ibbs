<?php
require_once __DIR__.'/config/bootstrap.php';
header('Content-Type: application/json');

// Asignar una materia a un docente es una decisión de administración —
// un profesor NO puede asignarse materias a sí mismo ni a otros
// docentes. El frontend (portal_docente.php) ya solo muestra este botón
// a admin/superadmin; el backend tiene que exigir lo mismo.
if (empty($_SESSION['loggedin']) || !in_array($_SESSION['rol'], ['superadmin', 'admin'])) {
    echo json_encode(['ok' => false, 'msg' => 'No autorizado']);
    exit;
}
csrf_require_post();

$con = db();
$uid = (int)($_SESSION['user_id'] ?? 0);
$docente_id = (int)($_POST['docente_id'] ?? 0);
$materia_id = (int)($_POST['materia_id'] ?? 0);

if (!$docente_id || !$materia_id) {
    echo json_encode(['ok' => false, 'msg' => 'Debe seleccionar un docente y una materia válida.']);
    exit;
}

// 1. Verificar que la relación no exista previamente (Para no duplicar)
$check = mysqli_prepare($con, "SELECT id FROM materia_docente WHERE docente_id = ? AND materia_id = ?");
mysqli_stmt_bind_param($check, "ii", $docente_id, $materia_id);
mysqli_stmt_execute($check);
$resultado = mysqli_stmt_get_result($check);

if (mysqli_num_rows($resultado) > 0) {
    echo json_encode(['ok' => false, 'msg' => 'Este docente ya tiene asignada esta materia.']);
    exit;
}

// 2. Insertar la nueva asignación
$stmt = mysqli_prepare($con, "INSERT INTO materia_docente (docente_id, materia_id) VALUES (?, ?)");
mysqli_stmt_bind_param($stmt, "ii", $docente_id, $materia_id);

if (mysqli_stmt_execute($stmt)) {
    log_audit($con, $uid, 'MATERIA_DOCENTE_ASIGNAR', "materia=$materia_id docente=$docente_id");
    echo json_encode(['ok' => true, 'msg' => 'Materia vinculada correctamente al docente.']);
} else {
    echo json_encode(['ok' => false, 'msg' => 'Error en la base de datos al realizar la asignación.']);
}