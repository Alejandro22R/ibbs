<?php
require_once __DIR__.'/config/bootstrap.php';
header('Content-Type: application/json');

// 'docente' nunca es el rol real que guarda el login (es 'profesor') —
// con el chequeo exacto anterior, ningún docente real podía calificar.
if (empty($_SESSION['loggedin']) || !in_array($_SESSION['rol'], ['profesor', 'docente', 'admin', 'superadmin'])) {
    echo json_encode(['ok' => false, 'msg' => 'No autorizado']);
    exit;
}
csrf_require_post();

$con = db();
$uid = (int)($_SESSION['user_id'] ?? 0);
$rol = $_SESSION['rol'];
$entrega_id = (int)($_POST['entrega_id'] ?? 0);
$nota = trim($_POST['nota'] ?? '');
$observacion = trim($_POST['observacion'] ?? '');

if (!$entrega_id || $nota === '') {
    echo json_encode(['ok' => false, 'msg' => 'Faltan datos obligatorios para calificar.']);
    exit;
}
if (!is_numeric($nota)) {
    echo json_encode(['ok' => false, 'msg' => 'La nota debe ser un número.']);
    exit;
}

$nota_num = (float)$nota;
// Escala fija de 0 a 20 — el mínimo para aprobar es 15, sin excepción.
if ($nota_num < 0 || $nota_num > 20) {
    echo json_encode(['ok' => false, 'msg' => 'La nota debe estar entre 0 y 20.']);
    exit;
}
if (mb_strlen($observacion) > 1000) {
    echo json_encode(['ok' => false, 'msg' => 'El mensaje es demasiado largo (máx. 1000 caracteres).']);
    exit;
}

// La entrega tiene que pertenecer a una tarea de una materia que este
// profesor gestiona (admin/superadmin gestiona cualquiera) — si no,
// cualquier profesor podría calificar entregas ajenas.
$eInfo = mysqli_fetch_assoc(mysqli_query($con, "SELECT t.materia_id FROM entregas e JOIN tareas t ON t.id=e.tarea_id WHERE e.id=".(int)$entrega_id." LIMIT 1"));
if (!$eInfo) {
    echo json_encode(['ok' => false, 'msg' => 'La entrega no existe.']);
    exit;
}
if (!materia_puede_gestionar($con, $uid, $rol, (int)$eInfo['materia_id'])) {
    echo json_encode(['ok' => false, 'msg' => 'No tenés permiso para calificar esta entrega.']);
    exit;
}

// Actualizar la nota (y el mensaje opcional) de la entrega
$obsParam = $observacion !== '' ? $observacion : null;
$stmt = mysqli_prepare($con, "UPDATE entregas SET nota = ?, observacion = ? WHERE id = ?");
mysqli_stmt_bind_param($stmt, "dsi", $nota_num, $obsParam, $entrega_id);

if (mysqli_stmt_execute($stmt)) {
    $aprobado = $nota_num >= 15;
    log_audit($con, $uid, 'ENTREGA_CALIFICAR', "entrega=$entrega_id nota=$nota_num aprobado=".($aprobado?'si':'no'));
    $stInfo = mysqli_prepare($con, "SELECT t.materia_id, t.titulo, a.usuario_id
                                     FROM entregas e JOIN tareas t ON t.id=e.tarea_id JOIN alumnos a ON a.id=e.alumno_id
                                     WHERE e.id=? LIMIT 1");
    mysqli_stmt_bind_param($stInfo, "i", $entrega_id);
    mysqli_stmt_execute($stInfo);
    $info = mysqli_fetch_assoc(mysqli_stmt_get_result($stInfo));
    if ($info && $info['usuario_id']) {
        $msgNotif = "Tu nota: $nota_num / 20 (" . ($aprobado ? 'Aprobado' : 'Reprobado') . ")";
        if ($observacion !== '') $msgNotif .= "\n\n" . $observacion;
        notificar_usuario($con, (int)$info['usuario_id'], 'calificacion', "Tarea calificada: {$info['titulo']}", $msgNotif, (int)$info['materia_id']);
    }
    echo json_encode(['ok' => true, 'msg' => 'Calificación registrada correctamente.', 'data' => [
        'entrega_id' => $entrega_id, 'nota' => $nota_num, 'aprobado' => $aprobado, 'observacion' => $observacion,
    ]]);
} else {
    echo json_encode(['ok' => false, 'msg' => 'Error al registrar la calificación.']);
}