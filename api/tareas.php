<?php
// Desactivamos errores HTML para no corromper las respuestas JSON
error_reporting(0); 
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');
session_start();

// Conexión segura a la BD
$host = 'localhost';
$db   = 'ibbs';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['ok' => false, 'msg' => 'Error de conexión a DB']);
    exit;
}

// Variables base
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$materia_id = isset($_REQUEST['materia_id']) ? intval($_REQUEST['materia_id']) : 0;
$rol = $_SESSION['rol'] ?? 'alumno';
$usuario = $_SESSION['usuario'] ?? '';

if (!$action || !$materia_id) {
    echo json_encode(['ok' => false, 'msg' => 'Faltan parámetros']);
    exit;
}

// Lógica para obtener el ID del alumno real basado en la sesión (Vinculando por cédula)
$alumno_id = 0;
if (in_array($rol, ['alumno'])) {
    $stmt = $pdo->prepare("SELECT a.id FROM alumnos a JOIN usuarios u ON a.cedula = u.cedula WHERE u.usuario = ?");
    $stmt->execute([$usuario]);
    $alumno_id = $stmt->fetchColumn() ?: 0;
}

// Función auxiliar para subir archivos
function subirArchivo($fileField, $folder) {
    if (!isset($_FILES[$fileField]) || $_FILES[$fileField]['error'] !== UPLOAD_ERR_OK) return null;
    $dir = "../uploads/$folder/";
    if (!is_dir($dir)) mkdir($dir, 0777, true);
    $ext = pathinfo($_FILES[$fileField]['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '_' . time() . '.' . $ext;
    if (move_uploaded_file($_FILES[$fileField]['tmp_name'], $dir . $filename)) return $filename;
    return null;
}

try {
    // 1. LISTAR TAREAS
    if ($action == 'list') {
        if (in_array($rol, ['profesor', 'admin', 'superadmin'])) {
            $stmt = $pdo->prepare("SELECT * FROM tareas WHERE materia_id = ? ORDER BY fecha_limite DESC");
            $stmt->execute([$materia_id]);
            echo json_encode(['ok' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        } else {
            // El alumno ve las tareas y su propio estado de entrega
            $stmt = $pdo->prepare("SELECT t.*, e.id as entrega_id, e.fecha_entrega, e.nota, e.observacion_docente 
                                   FROM tareas t 
                                   LEFT JOIN entregas e ON t.id = e.tarea_id AND e.alumno_id = ? 
                                   WHERE t.materia_id = ? ORDER BY t.fecha_limite DESC");
            $stmt->execute([$alumno_id, $materia_id]);
            echo json_encode(['ok' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        }
        exit;
    }

    // 2. DOCENTE: CREAR TAREA
    if ($action == 'crear' && $_SERVER['REQUEST_METHOD'] == 'POST' && in_array($rol, ['profesor', 'admin', 'superadmin'])) {
        $titulo = $_POST['titulo'] ?? '';
        $descripcion = $_POST['descripcion'] ?? '';
        $fecha_limite = $_POST['fecha_limite'] ?? date('Y-m-d H:i:s');
        $nota_maxima = $_POST['nota_maxima'] ?? 20;
        
        $archivo = subirArchivo('archivo', 'tareas');

        $stmt = $pdo->prepare("INSERT INTO tareas (materia_id, titulo, descripcion, archivo, fecha_limite, nota_maxima) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$materia_id, $titulo, $descripcion, $archivo, $fecha_limite, $nota_maxima]);
        echo json_encode(['ok' => true, 'msg' => 'Tarea creada exitosamente']);
        exit;
    }

    // 3. ALUMNO: ENTREGAR TAREA
    if ($action == 'entregar' && $_SERVER['REQUEST_METHOD'] == 'POST' && in_array($rol, ['alumno'])) {
        if (!$alumno_id) { echo json_encode(['ok'=>false, 'msg'=>'No se pudo identificar al alumno.']); exit; }
        
        $tarea_id = intval($_POST['tarea_id'] ?? 0);
        $texto = trim($_POST['texto_respuesta'] ?? '');
        $archivo = subirArchivo('archivo', 'entregas');

        if (!$texto && !$archivo) {
            echo json_encode(['ok' => false, 'msg' => 'Debes escribir algo o subir un archivo.']); exit;
        }

        // Usamos INSERT ... ON DUPLICATE KEY UPDATE por si sube otra vez para sobreescribir
        $stmt = $pdo->prepare("
            INSERT INTO entregas (tarea_id, alumno_id, texto_respuesta, archivo, fecha_entrega) 
            VALUES (?, ?, ?, ?, NOW()) 
            ON DUPLICATE KEY UPDATE texto_respuesta = ?, archivo = COALESCE(?, archivo), fecha_entrega = NOW()
        ");
        $stmt->execute([$tarea_id, $alumno_id, $texto, $archivo, $texto, $archivo]);
        
        echo json_encode(['ok' => true, 'msg' => 'Tarea entregada correctamente']);
        exit;
    }

    // 4. DOCENTE: VER ENTREGAS DE UNA TAREA
    if ($action == 'ver_entregas' && in_array($rol, ['profesor', 'admin', 'superadmin'])) {
        $tarea_id = intval($_GET['tarea_id'] ?? 0);
        
        // Traemos a todos los alumnos de la materia y cruzamos con sus entregas si existen
        $stmt = $pdo->prepare("
            SELECT a.id as alumno_id, a.nombre, a.apellido, a.cedula, 
                   e.id as entrega_id, e.texto_respuesta, e.archivo, e.fecha_entrega, e.nota, e.observacion_docente
            FROM materia_alumno ma 
            JOIN alumnos a ON ma.alumno_id = a.id 
            LEFT JOIN entregas e ON e.alumno_id = a.id AND e.tarea_id = ?
            WHERE ma.materia_id = ?
            ORDER BY a.apellido ASC
        ");
        $stmt->execute([$tarea_id, $materia_id]);
        echo json_encode(['ok' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    // 5. DOCENTE: CALIFICAR ENTREGA
    if ($action == 'calificar' && $_SERVER['REQUEST_METHOD'] == 'POST' && in_array($rol, ['profesor', 'admin', 'superadmin'])) {
        $entrega_id = intval($_POST['entrega_id'] ?? 0);
        $nota = $_POST['nota'] !== '' ? floatval($_POST['nota']) : null;
        $obs = $_POST['observacion'] ?? '';
        
        if ($entrega_id) {
            $stmt = $pdo->prepare("UPDATE entregas SET nota = ?, observacion_docente = ? WHERE id = ?");
            $stmt->execute([$nota, $obs, $entrega_id]);
            echo json_encode(['ok' => true, 'msg' => 'Calificación guardada']);
        } else {
            echo json_encode(['ok' => false, 'msg' => 'ID de entrega no válido']);
        }
        exit;
    }

} catch (Exception $e) {
    echo json_encode(['ok' => false, 'msg' => 'Error SQL: ' . $e->getMessage()]);
}