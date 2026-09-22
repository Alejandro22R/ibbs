<?php
require_once __DIR__.'/config/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

// Validamos la sesión y aseguramos que el profesor TAMBIÉN tenga permisos, no solo el admin.
if (empty($_SESSION['loggedin']) || !in_array($_SESSION['rol'], ['profesor', 'docente', 'superadmin', 'admin'])) {
    echo json_encode([
        'ok' => false,
        'msg' => 'No estás autorizado para crear tareas. Tu rol actual es: ' . ($_SESSION['rol'] ?? 'Ninguno')
    ]);
    exit;
}
csrf_require_post();

$con = db();
if (!$con) {
    echo json_encode(['ok' => false, 'msg' => 'Error de conexión a la base de datos.']);
    exit;
}

$uid = (int)($_SESSION['user_id'] ?? 0);
$rol = $_SESSION['rol'];

$materia_id   = (int)($_POST['materia_id'] ?? 0);
$titulo       = trim($_POST['titulo'] ?? '');
$descripcion  = trim($_POST['descripcion'] ?? '');
$fecha_limite = $_POST['fecha_limite'] ?? '';
$nota_maxima  = (float)($_POST['nota_maxima'] ?? 20);

// Validación de campos requeridos
if (!$materia_id || empty($titulo) || empty($fecha_limite)) {
    echo json_encode(['ok' => false, 'msg' => 'Por favor completa todos los campos obligatorios (Materia, Título y Fecha límite).']);
    exit;
}

// Un profesor solo puede publicar tareas en las materias donde está
// asignado — admin/superadmin puede en cualquiera.
if (!materia_puede_gestionar($con, $uid, $rol, $materia_id)) {
    echo json_encode(['ok' => false, 'msg' => 'No tenés permiso para publicar tareas en esta materia.']);
    exit;
}

$archivo_nombre = null;

if (isset($_FILES['archivo']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
    // Carpeta destino (se crea si no existe)
    $dir = __DIR__ . '/uploads/tareas/';
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    
    // Extensión del archivo y lista blanca por seguridad
    $ext = strtolower(pathinfo($_FILES['archivo']['name'], PATHINFO_EXTENSION));
    $permitidas = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'zip', 'rar', 'jpg', 'jpeg', 'png'];
    
    if (!in_array($ext, $permitidas)) {
        echo json_encode(['ok' => false, 'msg' => 'El formato del archivo adjunto no está permitido (usa PDF, Word, Excel o ZIP).']);
        exit;
    }
    
    // Generar un nombre único para evitar sobreescritura de archivos
    $archivo_nombre = uniqid('material_') . '_' . time() . '.' . $ext;
    
    // Mover el archivo subido a nuestra carpeta segura
    if (!move_uploaded_file($_FILES['archivo']['tmp_name'], $dir . $archivo_nombre)) {
        echo json_encode(['ok' => false, 'msg' => 'Hubo un problema al guardar el archivo en el servidor. Revisa los permisos de carpeta.']);
        exit;
    }
}

$stmt = mysqli_prepare($con, "INSERT INTO tareas (materia_id, titulo, descripcion, archivo, fecha_limite, nota_maxima) VALUES (?, ?, ?, ?, ?, ?)");
mysqli_stmt_bind_param($stmt, "issssd", $materia_id, $titulo, $descripcion, $archivo_nombre, $fecha_limite, $nota_maxima);

if (mysqli_stmt_execute($stmt)) {
    log_audit($con, $uid, 'TAREA_CREAR', "materia=$materia_id titulo=".mb_substr($titulo,0,80));
    notificar_materia($con, $materia_id, 'tarea', "Nueva tarea: $titulo", $descripcion, (int)($_SESSION['user_id'] ?? 0));
    echo json_encode(['ok' => true, 'msg' => 'Actividad publicada exitosamente con sus recursos.']);
} else {
    echo json_encode(['ok' => false, 'msg' => 'Error al registrar en la base de datos: ' . mysqli_error($con)]);
}