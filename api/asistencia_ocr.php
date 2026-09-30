<?php
/**
 * IBBS — Registro de asistencia por foto (OCR).
 *
 * El OCR (Tesseract.js, corre en el navegador — ver modulo_asistencias.php)
 * es una AYUDA para precargar la tabla de asistencia a partir de la foto
 * de la hoja física, nunca la fuente de verdad: el docente/admin siempre
 * revisa y corrige esa tabla antes de mandarla acá. Lo único que hace
 * este endpoint es (a) guardar la foto + el texto crudo como evidencia/
 * auditoría en `asistencia_hojas`, y (b) grabar la tabla YA CONFIRMADA
 * en `asistencias` vía el mismo asistencia_upsert() que usa el resto
 * del módulo — así una hoja se puede volver a procesar sin duplicar
 * filas.
 */

ob_start();
error_reporting(0);
require_once __DIR__.'/../config/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
ob_clean();

if (empty($_SESSION['loggedin'])) { echo json_encode(['ok'=>false,'msg'=>'Sesión expirada.']); exit; }
if (!csrf_verify($_POST['csrf_token'] ?? '')) { echo json_encode(['ok'=>false,'msg'=>'Token de seguridad inválido. Recarga la página e intenta de nuevo.']); exit; }

$con = db();
if (!$con) { echo json_encode(['ok'=>false,'msg'=>'Error de conexión a la base de datos.']); exit; }

$uid = (int)($_SESSION['user_id'] ?? 0);
$rol = $_SESSION['rol'] ?? 'alumno';
$action = trim($_POST['action'] ?? '');

function json_fail_ocr($msg) { echo json_encode(['ok'=>false,'msg'=>$msg]); exit; }

if ($action === 'hoja_guardar') {
    $mid   = (int)($_POST['materia_id'] ?? 0);
    $fecha = trim($_POST['fecha'] ?? '');
    $modo  = ($_POST['modo'] ?? 'asistieron') === 'faltaron' ? 'faltaron' : 'asistieron';
    $textoOcr = trim($_POST['texto_ocr'] ?? '');
    $registros = json_decode($_POST['registros'] ?? '[]', true);

    if (!$mid || !$fecha) json_fail_ocr('Materia y fecha son obligatorias.');
    if (!is_array($registros) || !$registros) json_fail_ocr('No hay ningún alumno para registrar — procesá la foto primero.');
    if (!materia_puede_gestionar($con, $uid, $rol, $mid)) json_fail_ocr('No tenés permiso sobre esta materia.');

    if (!isset($_FILES['foto']) || $_FILES['foto']['error'] !== UPLOAD_ERR_OK) json_fail_ocr('Adjuntá la foto de la hoja de asistencia.');
    $file = $_FILES['foto'];
    $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowedExt = ['jpg','jpeg','png','webp'];
    if (!in_array($ext, $allowedExt)) json_fail_ocr('La foto debe ser una imagen (jpg, png o webp).');
    if ($file['size'] > 8*1024*1024) json_fail_ocr('La imagen es muy grande (máx. 8MB).');
    $imgInfo = @getimagesize($file['tmp_name']);
    $allowedMime = ['image/jpeg','image/png','image/webp'];
    if ($imgInfo === false || !in_array($imgInfo['mime'] ?? '', $allowedMime)) json_fail_ocr('El archivo no es una imagen válida.');

    $dir = __DIR__.'/../uploads/asistencias/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $fname = 'hoja_'.$mid.'_'.str_replace('-','',$fecha).'_'.time().'_'.bin2hex(random_bytes(5)).'.'.$ext;
    if (!move_uploaded_file($file['tmp_name'], $dir.$fname)) json_fail_ocr('No se pudo guardar la foto.');
    $ruta = 'uploads/asistencias/'.$fname;

    $totalAlumnos = count($registros);
    $totalDetectados = count(array_filter($registros, fn($r) => !empty($r['detectado'])));

    $st = mysqli_prepare($con, "INSERT INTO asistencia_hojas(materia_id,fecha,foto,texto_ocr,modo,total_alumnos,total_detectados,cargado_por) VALUES(?,?,?,?,?,?,?,?)");
    mysqli_stmt_bind_param($st, 'issssiii', $mid, $fecha, $ruta, $textoOcr, $modo, $totalAlumnos, $totalDetectados, $uid);
    if (!mysqli_stmt_execute($st)) { @unlink($dir.$fname); json_fail_ocr('No se pudo registrar la hoja: '.mysqli_error($con)); }
    $hojaId = mysqli_insert_id($con);

    $ok = 0; $err = 0;
    foreach ($registros as $r) {
        $pid = (int)($r['persona_id'] ?? 0);
        $estado = trim($r['estado'] ?? 'presente');
        if (!in_array($estado, ['presente','ausente','tardanza','justificado'], true)) $estado = 'presente';
        $obs = trim($r['observacion'] ?? '');
        if (!$pid) { $err++; continue; }
        asistencia_upsert($con, $mid, 'alumno', $pid, $fecha, $estado, $obs, $uid, $hojaId) ? $ok++ : $err++;
    }

    log_audit($con, $uid, 'ASISTENCIA_OCR', "materia=$mid fecha=$fecha hoja=$hojaId ok=$ok err=$err");
    echo json_encode(['ok'=>true,'msg'=>"Hoja procesada: $ok alumnos guardados".($err?", $err con error":'.'),'hoja_id'=>$hojaId,'guardados'=>$ok]); exit;
}

if ($action === 'hoja_list') {
    if (!in_array($rol, ['superadmin','admin','profesor'])) json_fail_ocr('Sin permiso.');
    $mid = (int)($_POST['materia_id'] ?? 0);
    if ($mid && !materia_puede_ver($con, $uid, $rol, $mid)) json_fail_ocr('Sin permiso sobre esta materia.');
    $w = $mid ? "WHERE h.materia_id=$mid" : ($rol === 'profesor' ? "WHERE h.materia_id IN (SELECT md.materia_id FROM materia_docente md JOIN docentes d ON d.id=md.docente_id WHERE d.usuario_id=$uid)" : "");
    $r = mysqli_query($con, "SELECT h.*, m.nombre materia_nombre, u.usuario cargado_por_nombre
        FROM asistencia_hojas h
        JOIN materias m ON m.id=h.materia_id
        LEFT JOIN usuarios u ON u.id=h.cargado_por
        $w ORDER BY h.creado_en DESC LIMIT 100");
    $rows = []; while ($f = mysqli_fetch_assoc($r)) $rows[] = $f;
    echo json_encode(['ok'=>true,'data'=>$rows]); exit;
}

if ($action === 'hoja_get') {
    if (!in_array($rol, ['superadmin','admin','profesor'])) json_fail_ocr('Sin permiso.');
    $id = (int)($_POST['id'] ?? 0);
    $h = mysqli_fetch_assoc(mysqli_query($con, "SELECT h.*, m.nombre materia_nombre FROM asistencia_hojas h JOIN materias m ON m.id=h.materia_id WHERE h.id=$id LIMIT 1"));
    if (!$h) json_fail_ocr('Hoja no encontrada.');
    if (!materia_puede_ver($con, $uid, $rol, (int)$h['materia_id'])) json_fail_ocr('Sin permiso sobre esta materia.');
    echo json_encode(['ok'=>true,'data'=>$h]); exit;
}

echo json_encode(['ok'=>false,'msg'=>'Acción no reconocida.']); exit;
