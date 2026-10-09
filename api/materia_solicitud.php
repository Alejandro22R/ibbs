<?php
/**
 * IBBS — Solicitudes de autoinscripción con comprobante de pago.
 *
 * El alumno ya no queda inscrito al instante con materia_autoinscribir
 * (api/ajax.php) — ahora sube la captura del pago junto con su
 * solicitud acá, y un admin/superadmin la revisa (viendo la imagen)
 * antes de que la fila entre a materia_alumno. Mientras está
 * 'pendiente' el alumno no tiene acceso real a esa materia (aula,
 * foro, notas...) porque ninguna de esas consultas lee de
 * materia_solicitudes, solo de materia_alumno.
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

$uid    = (int)($_SESSION['user_id'] ?? 0);
$rol    = $_SESSION['rol'] ?? 'alumno';
$action = trim($_POST['action'] ?? '');

if ($action === 'crear') {
    if ($rol !== 'alumno') { echo json_encode(['ok'=>false,'msg'=>'Solo un alumno puede enviar esta solicitud.']); exit; }
    $mid = (int)($_POST['materia_id'] ?? 0);
    if (!$mid) { echo json_encode(['ok'=>false,'msg'=>'Materia requerida.']); exit; }

    $al = mysqli_fetch_assoc(mysqli_query($con, "SELECT id,regular,nombre,apellido FROM alumnos WHERE usuario_id=$uid LIMIT 1"));
    if (!$al) { echo json_encode(['ok'=>false,'msg'=>'No se encontró tu registro de alumno.']); exit; }
    if (!(int)$al['regular']) { echo json_encode(['ok'=>false,'msg'=>'Tu inscripción aún no fue marcada como regular. Contacta a la administración.']); exit; }
    $aid = (int)$al['id'];

    $mat = mysqli_fetch_assoc(mysqli_query($con, "SELECT id,nombre,estado,inscripcion_abierta FROM materias WHERE id=$mid AND activo=1 LIMIT 1"));
    if (!$mat) { echo json_encode(['ok'=>false,'msg'=>'Materia no disponible.']); exit; }
    if ($mat['estado'] === 'culminada') { echo json_encode(['ok'=>false,'msg'=>'Esta materia ya culminó y no admite inscripciones.']); exit; }
    if (!(int)$mat['inscripcion_abierta']) { echo json_encode(['ok'=>false,'msg'=>'La administración todavía no abrió la inscripción para esta materia.']); exit; }

    $ex = mysqli_fetch_assoc(mysqli_query($con, "SELECT id FROM materia_alumno WHERE materia_id=$mid AND alumno_id=$aid LIMIT 1"));
    if ($ex) { echo json_encode(['ok'=>false,'msg'=>'Ya estás inscrito en esta materia.']); exit; }
    $pend = mysqli_fetch_assoc(mysqli_query($con, "SELECT id FROM materia_solicitudes WHERE materia_id=$mid AND alumno_id=$aid AND estado='pendiente' LIMIT 1"));
    if ($pend) { echo json_encode(['ok'=>false,'msg'=>'Ya tenés una solicitud pendiente para esta materia. Esperá a que la administración la revise.']); exit; }

    if (!isset($_FILES['comprobante']) || $_FILES['comprobante']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['ok'=>false,'msg'=>'Adjuntá la captura del pago móvil o la transferencia para poder inscribirte.']); exit;
    }
    $file = $_FILES['comprobante'];
    $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowedExt = ['jpg','jpeg','png','webp'];
    if (!in_array($ext, $allowedExt)) { echo json_encode(['ok'=>false,'msg'=>'El comprobante debe ser una imagen (jpg, png o webp).']); exit; }
    if ($file['size'] > 5*1024*1024) { echo json_encode(['ok'=>false,'msg'=>'La imagen es muy grande (máx. 5MB).']); exit; }
    $imgInfo = @getimagesize($file['tmp_name']);
    $allowedMime = ['image/jpeg','image/png','image/webp'];
    if ($imgInfo === false || !in_array($imgInfo['mime'] ?? '', $allowedMime)) {
        echo json_encode(['ok'=>false,'msg'=>'El archivo no es una imagen válida.']); exit;
    }

    $dir = __DIR__.'/../uploads/comprobantes/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $fname = 'comp_'.$aid.'_'.$mid.'_'.time().'_'.bin2hex(random_bytes(8)).'.'.$ext;
    if (!move_uploaded_file($file['tmp_name'], $dir.$fname)) {
        echo json_encode(['ok'=>false,'msg'=>'Error al guardar el comprobante.']); exit;
    }
    $ruta = 'uploads/comprobantes/'.$fname;

    $st = mysqli_prepare($con, "INSERT INTO materia_solicitudes(alumno_id,materia_id,comprobante) VALUES(?,?,?)");
    mysqli_stmt_bind_param($st, 'iis', $aid, $mid, $ruta);
    if (!mysqli_stmt_execute($st)) {
        @unlink($dir.$fname);
        echo json_encode(['ok'=>false,'msg'=>'No se pudo registrar la solicitud.']); exit;
    }
    $solId = mysqli_insert_id($con);
    log_audit($con, $uid, 'MATERIA_SOLICITUD_CREAR', "materia=$mid alumno=$aid solicitud=$solId");

    $nombreCompleto = trim($al['nombre'].' '.$al['apellido']);
    $stN = mysqli_prepare($con, "INSERT INTO notificaciones(tipo,titulo,mensaje,para_rol,referencia_id) VALUES('solicitud_materia',?,?,'admin',?)");
    $tituloN  = "Nueva solicitud de inscripción: $nombreCompleto";
    $mensajeN = "$nombreCompleto quiere inscribirse en \"{$mat['nombre']}\" y adjuntó su comprobante de pago. Revisalo antes de aceptar o rechazar.";
    mysqli_stmt_bind_param($stN, 'ssi', $tituloN, $mensajeN, $solId);
    mysqli_stmt_execute($stN);
    $notifId = mysqli_insert_id($con);
    if (function_exists('ws_broadcast_user')) {
        $rAdmins = mysqli_query($con, "SELECT id FROM usuarios WHERE rol IN ('admin','superadmin') AND activo=1");
        while ($fAdmin = mysqli_fetch_assoc($rAdmins)) {
            ws_broadcast_user((int)$fAdmin['id'], 'notificacion', [
                'id' => $notifId, 'tipo' => 'solicitud_materia', 'titulo' => $tituloN,
                'mensaje' => $mensajeN, 'referencia_id' => $solId, 'creado_en' => date('Y-m-d H:i:s'),
            ]);
        }
    }
    echo json_encode(['ok'=>true,'msg'=>'¡Listo! Tu solicitud y comprobante quedaron enviados. La administración la revisará y te avisaremos apenas la acepten.']); exit;
}

if ($action === 'get') {
    if (!in_array($rol, ['superadmin','admin'])) { echo json_encode(['ok'=>false,'msg'=>'Sin permiso.']); exit; }
    $id = (int)($_POST['id'] ?? 0);
    $sol = mysqli_fetch_assoc(mysqli_query($con, "SELECT s.*, a.nombre alumno_nombre, a.apellido alumno_apellido, a.cedula alumno_cedula,
        m.nombre materia_nombre, m.codigo materia_codigo
        FROM materia_solicitudes s JOIN alumnos a ON a.id=s.alumno_id JOIN materias m ON m.id=s.materia_id
        WHERE s.id=$id LIMIT 1"));
    if (!$sol) { echo json_encode(['ok'=>false,'msg'=>'Solicitud no encontrada.']); exit; }
    echo json_encode(['ok'=>true,'data'=>$sol]); exit;
}

if ($action === 'list') {
    if (!in_array($rol, ['superadmin','admin'])) { echo json_encode(['ok'=>false,'msg'=>'Sin permiso.']); exit; }
    $estado = trim($_POST['estado'] ?? 'pendiente');
    $w = in_array($estado, ['pendiente','aprobada','rechazada']) ? "WHERE s.estado='".mysqli_real_escape_string($con,$estado)."'" : '';
    $r = mysqli_query($con, "SELECT s.*, a.nombre alumno_nombre, a.apellido alumno_apellido, a.cedula alumno_cedula,
        m.nombre materia_nombre, m.codigo materia_codigo
        FROM materia_solicitudes s
        JOIN alumnos a ON a.id=s.alumno_id
        JOIN materias m ON m.id=s.materia_id
        $w ORDER BY s.fecha DESC LIMIT 200");
    $rows = []; while ($f = mysqli_fetch_assoc($r)) $rows[] = $f;
    echo json_encode(['ok'=>true,'data'=>$rows]); exit;
}

if ($action === 'aprobar' || $action === 'rechazar') {
    if (!in_array($rol, ['superadmin','admin'])) { echo json_encode(['ok'=>false,'msg'=>'Sin permiso.']); exit; }
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) { echo json_encode(['ok'=>false,'msg'=>'Solicitud requerida.']); exit; }
    $sol = mysqli_fetch_assoc(mysqli_query($con, "SELECT s.*, a.nombre alumno_nombre, a.apellido alumno_apellido, a.usuario_id, m.nombre materia_nombre
        FROM materia_solicitudes s JOIN alumnos a ON a.id=s.alumno_id JOIN materias m ON m.id=s.materia_id
        WHERE s.id=$id LIMIT 1"));
    if (!$sol) { echo json_encode(['ok'=>false,'msg'=>'Solicitud no encontrada.']); exit; }
    if ($sol['estado'] !== 'pendiente') { echo json_encode(['ok'=>false,'msg'=>'Esta solicitud ya fue revisada.']); exit; }

    if ($action === 'aprobar') {
        $ex = mysqli_fetch_assoc(mysqli_query($con, "SELECT id FROM materia_alumno WHERE materia_id={$sol['materia_id']} AND alumno_id={$sol['alumno_id']} LIMIT 1"));
        if (!$ex) {
            $stI = mysqli_prepare($con, "INSERT INTO materia_alumno(materia_id,alumno_id,auto_inscrito) VALUES(?,?,1)");
            mysqli_stmt_bind_param($stI, 'ii', $sol['materia_id'], $sol['alumno_id']);
            mysqli_stmt_execute($stI);
        }
        mysqli_query($con, "UPDATE materia_solicitudes SET estado='aprobada',revisado_por=$uid,fecha_revision=NOW() WHERE id=$id");
        log_audit($con, $uid, 'MATERIA_SOLICITUD_APROBAR', "solicitud=$id materia={$sol['materia_id']} alumno={$sol['alumno_id']}");
        if ($sol['usuario_id']) {
            notificar_usuario($con, (int)$sol['usuario_id'], 'solicitud_materia_aprobada', '¡Tu inscripción fue aprobada!', "Quedaste inscrito(a) en \"{$sol['materia_nombre']}\". Ya podés ver su contenido.");
        }
        $msg = "Se aprobó la inscripción de {$sol['alumno_nombre']} {$sol['alumno_apellido']} en \"{$sol['materia_nombre']}\".";
    } else {
        mysqli_query($con, "UPDATE materia_solicitudes SET estado='rechazada',revisado_por=$uid,fecha_revision=NOW() WHERE id=$id");
        log_audit($con, $uid, 'MATERIA_SOLICITUD_RECHAZAR', "solicitud=$id materia={$sol['materia_id']} alumno={$sol['alumno_id']}");
        if ($sol['usuario_id']) {
            notificar_usuario($con, (int)$sol['usuario_id'], 'solicitud_materia_rechazada', 'Tu solicitud de inscripción fue rechazada', "No pudimos validar tu comprobante de pago para \"{$sol['materia_nombre']}\". Contactá a la administración.");
        }
        $msg = "Se rechazó la solicitud de {$sol['alumno_nombre']} {$sol['alumno_apellido']} para \"{$sol['materia_nombre']}\".";
    }
    mysqli_query($con, "UPDATE notificaciones SET leida=1 WHERE tipo='solicitud_materia' AND referencia_id=$id");
    echo json_encode(['ok'=>true,'msg'=>$msg]); exit;
}

echo json_encode(['ok'=>false,'msg'=>'Acción no reconocida.']); exit;
