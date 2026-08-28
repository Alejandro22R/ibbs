<?php
ob_start();
error_reporting(0);
session_start();
if (empty($_SESSION['loggedin'])) { header('Location: ../login.php'); exit; }

// Solo superadmin o admin
$rol = $_SESSION['rol'] ?? 'alumno';
if (!in_array($rol, ['superadmin','admin'])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok'=>false,'msg'=>'Sin permiso.']); exit;
}

$action = trim($_GET['action'] ?? $_POST['action'] ?? '');
$con = mysqli_connect("localhost","root","","ibbs");
if (!$con) {
    if ($action === 'export') die("Error de conexión a BD");
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok'=>false,'msg'=>'Error BD: '.mysqli_connect_error()]); exit;
}
mysqli_set_charset($con,"utf8mb4");

// ── EXPORTAR SQL ──────────────────────────────────────────────
if ($action === 'export') {
    // Get actual tables from DB (avoid hardcoded list that may be wrong)
    $tables = [];
    $tr = mysqli_query($con, "SHOW TABLES");
    while ($row = mysqli_fetch_row($tr)) $tables[] = $row[0];

    $sql  = "-- IBBS Backup — " . date('Y-m-d H:i:s') . "\n";
    $sql .= "-- Exportado por: " . ($_SESSION['usuario'] ?? 'admin') . "\n\n";
    $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

    foreach ($tables as $tbl) {
        $cr = mysqli_fetch_row(mysqli_query($con, "SHOW CREATE TABLE `$tbl`"));
        if (!$cr) continue;
        $sql .= "-- ── Tabla: $tbl ──\n";
        $sql .= "DROP TABLE IF EXISTS `$tbl`;\n";
        $sql .= $cr[1] . ";\n\n";
        $r = mysqli_query($con, "SELECT * FROM `$tbl`");
        if ($r && mysqli_num_rows($r) > 0) {
            while ($row = mysqli_fetch_assoc($r)) {
                $vals = array_map(function($v) use ($con) {
                    return $v === null ? 'NULL' : "'" . mysqli_real_escape_string($con, $v) . "'";
                }, array_values($row));
                $sql .= "INSERT INTO `$tbl` VALUES(" . implode(',', $vals) . ");\n";
            }
            $sql .= "\n";
        }
    }
    $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

    $fname = "ibbs_backup_" . date('Ymd_His') . ".sql";
    ob_clean(); // Clear any output before headers
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $fname . '"');
    header('Content-Length: ' . strlen($sql));
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    echo $sql;
    exit;
}

// ── IMPORTAR SQL ──────────────────────────────────────────────
if ($action === 'import' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');

    if (!isset($_FILES['sqlfile']) || $_FILES['sqlfile']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['ok'=>false,'msg'=>'No se recibió el archivo. Error: '.($_FILES['sqlfile']['error']??'desconocido')]);
        exit;
    }
    $ext = strtolower(pathinfo($_FILES['sqlfile']['name'], PATHINFO_EXTENSION));
    if ($ext !== 'sql') { echo json_encode(['ok'=>false,'msg'=>'Solo se permiten archivos .sql.']); exit; }

    $content = file_get_contents($_FILES['sqlfile']['tmp_name']);
    if (!$content || strlen(trim($content)) < 10) {
        echo json_encode(['ok'=>false,'msg'=>'El archivo está vacío o no es válido.']); exit;
    }

    // Split SQL into statements properly (handles \r\n, multiple lines, etc.)
    mysqli_query($con, "SET FOREIGN_KEY_CHECKS=0");
    mysqli_query($con, "SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO'");

    $errors = 0; $executed = 0;
    $delimiter = ';';
    $stmt = '';
    $lines = preg_split("/\r?\n/", $content);

    foreach ($lines as $line) {
        $line = rtrim($line);
        // Skip comments and empty lines
        if (empty($line) || strpos($line, '--') === 0 || strpos($line, '/*') === 0) continue;

        $stmt .= $line . "\n";

        // Check if statement ends
        if (substr(rtrim($line), -1) === $delimiter) {
            $stmt = trim($stmt);
            if (!empty($stmt) && $stmt !== ';') {
                if (!mysqli_query($con, $stmt)) {
                    $errors++;
                } else {
                    $executed++;
                }
            }
            $stmt = '';
        }
    }
    // Execute any remaining statement
    if (!empty(trim($stmt)) && trim($stmt) !== ';') {
        if (mysqli_query($con, trim($stmt))) $executed++;
        else $errors++;
    }

    mysqli_query($con, "SET FOREIGN_KEY_CHECKS=1");

    if ($errors === 0) {
        echo json_encode(['ok'=>true, 'msg'=>"Restauración exitosa. $executed sentencias ejecutadas."]);
    } else {
        echo json_encode(['ok'=>true, 'msg'=>"Completado con $errors advertencias ($executed ejecutadas). Puede ser normal si el SQL tiene DROP TABLE."]);
    }
    exit;
}

ob_clean();
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok'=>false,'msg'=>'Acción no reconocida.']);
