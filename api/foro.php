<?php
// Desactivamos la impresión de errores HTML para que no rompa el JSON de Javascript
error_reporting(0); 
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');
session_start();

// Usamos la conexión directa que sabemos que funciona en tu base de datos
$host = 'localhost';
$db   = 'ibbs';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Error de conexión a DB', 'detalle' => $e->getMessage()]);
    exit;
}

$materia_id = isset($_GET['materia_id']) ? intval($_GET['materia_id']) : 0;
if ($materia_id === 0) {
    echo json_encode(['error' => 'Materia no válida']);
    exit;
}

// Obtenemos los datos de sesión, con valores por defecto por precaución
$usuario = $_SESSION['usuario'] ?? 'Usuario';
$rol = $_SESSION['rol'] ?? 'alumno';

if (isset($_GET['action'])) {
    
    // OBTENER MENSAJES
    if ($_GET['action'] == 'get_mensajes') {
        try {
            $stmt = $pdo->prepare("SELECT * FROM foro_mensajes WHERE materia_id = ? ORDER BY fecha ASC");
            $stmt->execute([$materia_id]);
            $mensajes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($mensajes);
        } catch(Exception $e) {
            echo json_encode(['error' => 'Error SQL', 'detalle' => $e->getMessage()]);
        }
        exit;
    }

    // PUBLICAR MENSAJE
    if ($_GET['action'] == 'post_mensaje' && $_SERVER['REQUEST_METHOD'] == 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $mensaje = trim($data['mensaje'] ?? '');
        $respuesta_a = !empty($data['respuesta_a']) ? intval($data['respuesta_a']) : null;

        if (!empty($mensaje)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO foro_mensajes (materia_id, usuario_nombre, rol, mensaje, respuesta_a) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([
                    $materia_id, 
                    $usuario, 
                    $rol, 
                    $mensaje, 
                    $respuesta_a
                ]);
                echo json_encode(['success' => true]);
            } catch(Exception $e) {
                echo json_encode(['success' => false, 'error' => 'Error SQL', 'detalle' => $e->getMessage()]);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Mensaje vacío']);
        }
        exit;
    }
}

echo json_encode(['error' => 'Acción no válida']);