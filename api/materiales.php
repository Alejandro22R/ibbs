<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

// Aquí debes incluir tu conexión a la BD. Ajusta la ruta si es diferente.
// Asumo que tienes un archivo de conexión que inicializa $conexion o $conn
require_once '../php/conexion.php'; 

// Seguridad Básica: Solo usuarios logueados
if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['rol'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

$action = $_GET['action'] ?? '';
$materia_id = (int)($_REQUEST['materia_id'] ?? 0);

if ($materia_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID de materia inválido']);
    exit;
}

if ($action === 'listar') {
    // Listar todos los materiales de esta materia
    try {
        // Ajusta la consulta según tu variable de conexión (mysqli o PDO)
        // Ejemplo usando MySQLi:
        $stmt = $conexion->prepare("SELECT id, titulo, archivo_ruta, fecha_subida FROM materiales WHERE materia_id = ? ORDER BY fecha_subida DESC");
        $stmt->bind_param("i", $materia_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $materiales = [];
        while ($row = $result->fetch_assoc()) {
            $materiales[] = $row;
        }
        
        echo json_encode(['success' => true, 'data' => $materiales]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Error al cargar materiales']);
    }
    exit;
}

if ($action === 'subir') {
    // Validar permisos (solo profesores o admins suben material)
    if (!in_array($_SESSION['rol'], ['superadmin', 'admin', 'profesor'])) {
        echo json_encode(['success' => false, 'error' => 'Permisos insuficientes para subir material']);
        exit;
    }

    $titulo = trim($_POST['titulo'] ?? '');
    
    if (empty($titulo) || !isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'error' => 'Datos incompletos o error al subir archivo']);
        exit;
    }

    $archivo = $_FILES['archivo'];
    
    // Doble validación de tamaño (Back-end) - 25MB
    if ($archivo['size'] > 25 * 1024 * 1024) {
        echo json_encode(['success' => false, 'error' => 'El archivo supera el límite de 25MB']);
        exit;
    }

    // Asegurarse que el directorio existe
    $upload_dir = '../uploads/materiales/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    // Generar un nombre único para evitar sobreescribir y evitar inyección de extensiones raras
    $ext = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
    $allowed_exts = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'zip', 'rar', 'txt', 'png', 'jpg', 'jpeg'];
    
    if (!in_array($ext, $allowed_exts)) {
        echo json_encode(['success' => false, 'error' => 'Formato de archivo no permitido']);
        exit;
    }

    $nuevo_nombre = 'mat_' . $materia_id . '_' . time() . '_' . rand(100, 999) . '.' . $ext;
    $ruta_final = $upload_dir . $nuevo_nombre;
    
    // Ruta pública para guardar en la BD (ruta relativa desde el front-end)
    $ruta_publica = 'uploads/materiales/' . $nuevo_nombre;

    if (move_uploaded_file($archivo['tmp_name'], $ruta_final)) {
        try {
            $stmt = $conexion->prepare("INSERT INTO materiales (materia_id, titulo, archivo_ruta) VALUES (?, ?, ?)");
            $stmt->bind_param("iss", $materia_id, $titulo, $ruta_publica);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true]);
            } else {
                unlink($ruta_final); // Borrar el archivo si falla la BD
                echo json_encode(['success' => false, 'error' => 'Error al guardar en la base de datos']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Error interno del servidor']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Error al mover el archivo al servidor']);
    }
    exit;
}

if ($action === 'eliminar') {
    // Validar permisos (solo profesores o admins eliminan material)
    if (!in_array($_SESSION['rol'], ['superadmin', 'admin', 'profesor'])) {
        echo json_encode(['success' => false, 'error' => 'Permisos insuficientes para eliminar material']);
        exit;
    }

    $material_id = (int)($_POST['material_id'] ?? 0);

    if ($material_id <= 0) {
        echo json_encode(['success' => false, 'error' => 'ID de material inválido']);
        exit;
    }

    try {
        // 1. Obtener la ruta del archivo físico para borrarlo del servidor
        $stmt = $conexion->prepare("SELECT archivo_ruta FROM materiales WHERE id = ? AND materia_id = ?");
        $stmt->bind_param("ii", $material_id, $materia_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $ruta_fisica = '../' . $row['archivo_ruta'];
            
            // Eliminar archivo físico si existe
            if (file_exists($ruta_fisica)) {
                unlink($ruta_fisica);
            }

            // 2. Eliminar el registro de la base de datos
            $stmt_del = $conexion->prepare("DELETE FROM materiales WHERE id = ?");
            $stmt_del->bind_param("i", $material_id);
            
            if ($stmt_del->execute()) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Error al eliminar el registro de la base de datos']);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Material no encontrado o no pertenece a esta materia']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Error interno del servidor al eliminar']);
    }
    exit;
}

echo json_encode(['success' => false, 'error' => 'Acción no válida']);