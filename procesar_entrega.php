<?php
require_once __DIR__.'/config/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
if (empty($_SESSION['loggedin']) || $_SESSION['rol'] !== 'alumno') {
    echo json_encode(['ok' => false, 'msg' => 'No autorizado']);
    exit;
}
csrf_require_post();

$con = db();
$user_id = $_SESSION['user_id'];

$stmt = mysqli_prepare($con, "SELECT id FROM alumnos WHERE usuario_id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$alumno = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

// Fallback por si la relación de usuario_id es null (típico en pruebas manuales en BD)
if (!$alumno) {
    $usuario = $_SESSION['usuario'];
    $stmt2 = mysqli_prepare($con, "SELECT id FROM alumnos WHERE nombre = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt2, "s", $usuario);
    mysqli_stmt_execute($stmt2);
    $alumno = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt2));
    if (!$alumno) {
        echo json_encode(['ok' => false, 'msg' => 'No se encontró registro de alumno.']);
        exit;
    }
}

$alumno_id = $alumno['id'];
$tarea_id = (int)($_POST['tarea_id'] ?? 0);
$respuesta_texto = trim($_POST['respuesta_texto'] ?? '');
$archivo_nombre = null;

if (!$tarea_id) {
    echo json_encode(['ok' => false, 'msg' => 'ID de tarea inválido.']);
    exit;
}

// La tarea tiene que existir y el alumno tiene que estar inscrito en su
// materia — si no, no puede haber recibido esa tarea nunca.
$tInfo = mysqli_fetch_assoc(mysqli_query($con, "SELECT materia_id FROM tareas WHERE id=".(int)$tarea_id." LIMIT 1"));
if (!$tInfo) {
    echo json_encode(['ok' => false, 'msg' => 'La tarea no existe.']);
    exit;
}
$inscrito = mysqli_fetch_assoc(mysqli_query($con, "SELECT id FROM materia_alumno WHERE materia_id=".(int)$tInfo['materia_id']." AND alumno_id=".(int)$alumno_id." LIMIT 1"));
if (!$inscrito) {
    echo json_encode(['ok' => false, 'msg' => 'No estás inscrito en la materia de esta tarea.']);
    exit;
}

if (isset($_FILES['archivo_adjunto']) && $_FILES['archivo_adjunto']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = __DIR__.'/uploads/entregas/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
    
    $file_ext = strtolower(pathinfo($_FILES['archivo_adjunto']['name'], PATHINFO_EXTENSION));
    $allowed_exts = ['zip', 'rar', 'pdf', 'docx', 'doc', 'jpg', 'png'];
    
    if (!in_array($file_ext, $allowed_exts)) {
        echo json_encode(['ok' => false, 'msg' => 'Formato de archivo no permitido.']);
        exit;
    }
    
    $archivo_nombre = uniqid('entrega_') . '.' . $file_ext;
    if (!move_uploaded_file($_FILES['archivo_adjunto']['tmp_name'], $upload_dir . $archivo_nombre)) {
        echo json_encode(['ok' => false, 'msg' => 'Error al guardar el archivo en disco.']);
        exit;
    }
}

// Mantener el archivo anterior si no se subió uno nuevo
$stmt_check = mysqli_prepare($con, "SELECT archivo FROM entregas WHERE tarea_id = ? AND alumno_id = ?");
mysqli_stmt_bind_param($stmt_check, "ii", $tarea_id, $alumno_id);
mysqli_stmt_execute($stmt_check);
$existing = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_check));

if ($existing && !$archivo_nombre) {
    $archivo_nombre = $existing['archivo'];
}

$query = "INSERT INTO entregas (tarea_id, alumno_id, texto_respuesta, archivo, fecha_entrega) 
          VALUES (?, ?, ?, ?, NOW()) 
          ON DUPLICATE KEY UPDATE 
          texto_respuesta = VALUES(texto_respuesta), archivo = VALUES(archivo), fecha_entrega = NOW()";

$stmt = mysqli_prepare($con, $query);
mysqli_stmt_bind_param($stmt, "iiss", $tarea_id, $alumno_id, $respuesta_texto, $archivo_nombre);

if (mysqli_stmt_execute($stmt)) {
    log_audit($con, (int)$user_id, 'ENTREGA_SUBIR', "tarea=$tarea_id alumno=$alumno_id");
    echo json_encode(['ok' => true, 'msg' => 'Entrega subida y registrada con éxito.']);
} else {
    echo json_encode(['ok' => false, 'msg' => 'Error al guardar en la base de datos.']);
}