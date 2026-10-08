<?php
/**
 * IBBS — Pagos de mensualidad e inscripción (api/pagos.php).
 *
 * Mismo patrón ya probado en la Biblioteca para la compra de libros:
 * el admin registra un cobro pendiente, el alumno sube su comprobante
 * (pago móvil / transferencia) desde su portal, y el admin lo aprueba o
 * rechaza. Reusa los mismos "Datos de Pago" (tabla datos_pago) que ya
 * configura Biblioteca — es la misma cuenta/pago móvil del instituto
 * para todo, no hace falta cargarlos dos veces en dos lugares distintos.
 *
 * Un admin también puede marcar un cobro como pagado directo (efectivo
 * en el instituto, por ejemplo), sin pasar por la subida de comprobante.
 */

ob_start();
error_reporting(0);
require_once __DIR__.'/../config/bootstrap.php';

if (empty($_SESSION['loggedin'])) {
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok'=>false,'msg'=>'Sesión expirada.']); exit;
}

ob_clean();
header('Content-Type: application/json; charset=utf-8');
csrf_require_post();

$con = db();
if (!$con) { echo json_encode(['ok'=>false,'msg'=>'Error de conexión a la base de datos.']); exit; }

$uid = (int)($_SESSION['user_id'] ?? 0);
$rol = $_SESSION['rol'] ?? 'alumno';
$action = trim($_POST['action'] ?? '');

function json_fail_pg($msg) { echo json_encode(['ok'=>false,'msg'=>$msg]); exit; }

mysqli_query($con, "CREATE TABLE IF NOT EXISTS pagos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    alumno_id INT NOT NULL,
    tipo ENUM('mensualidad','inscripcion') NOT NULL,
    concepto VARCHAR(150) NOT NULL,
    monto DECIMAL(10,2) NOT NULL,
    comprobante VARCHAR(255) DEFAULT NULL,
    estado ENUM('pendiente','en_revision','pagado','rechazado') NOT NULL DEFAULT 'pendiente',
    creado_por INT DEFAULT NULL,
    revisado_por INT DEFAULT NULL,
    revisado_en DATETIME DEFAULT NULL,
    fecha_vencimiento DATE DEFAULT NULL,
    creado_en DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX(alumno_id), INDEX(estado)
) ENGINE=InnoDB");

function alumno_id_del_usuario($con, $uid) {
    $al = mysqli_fetch_assoc(mysqli_query($con, "SELECT id FROM alumnos WHERE usuario_id=$uid LIMIT 1"));
    return $al ? (int)$al['id'] : 0;
}

/* ════ ADMIN: registrar un cobro ═══════════════════════════════════ */
if ($action === 'pago_crear') {
    if (!in_array($rol, ['superadmin','admin'])) json_fail_pg('Sin permiso.');
    $aid = (int)($_POST['alumno_id'] ?? 0);
    $tipo = trim($_POST['tipo'] ?? '');
    $concepto = trim($_POST['concepto'] ?? '');
    $monto = (float)($_POST['monto'] ?? 0);
    $venc = trim($_POST['fecha_vencimiento'] ?? '') ?: null;
    if (!$aid) json_fail_pg('Elegí un alumno.');
    if (!in_array($tipo, ['mensualidad','inscripcion'])) json_fail_pg('Tipo inválido.');
    if (!$concepto) json_fail_pg('El concepto es requerido (ej. "Mensualidad Octubre 2026").');
    if ($monto <= 0) json_fail_pg('El monto debe ser mayor a 0.');
    $st = mysqli_prepare($con, "INSERT INTO pagos(alumno_id,tipo,concepto,monto,fecha_vencimiento,creado_por) VALUES(?,?,?,?,?,?)");
    mysqli_stmt_bind_param($st, 'issdsi', $aid, $tipo, $concepto, $monto, $venc, $uid);
    mysqli_stmt_execute($st);
    $pid = mysqli_insert_id($con);
    log_audit($con, $uid, 'PAGO_CREAR', "alumno=$aid tipo=$tipo monto=$monto");

    $stN = mysqli_prepare($con, "INSERT INTO notificaciones(tipo,titulo,mensaje,usuario_id,referencia_id) SELECT 'pago_pendiente',?,?,usuario_id,? FROM alumnos WHERE id=?");
    $tit = 'Nuevo cobro pendiente';
    $msg = "Tenés un cobro pendiente: \"$concepto\" por $monto.";
    mysqli_stmt_bind_param($stN, 'ssii', $tit, $msg, $pid, $aid);
    mysqli_stmt_execute($stN);

    echo json_encode(['ok'=>true,'msg'=>'Cobro registrado.','id'=>$pid]); exit;
}

/* ════ ADMIN: listar / filtrar ══════════════════════════════════════ */
if ($action === 'pago_list') {
    if (!in_array($rol, ['superadmin','admin'])) json_fail_pg('Sin permiso.');
    $tipo = trim($_POST['tipo'] ?? '');
    $estado = trim($_POST['estado'] ?? '');
    $aid = (int)($_POST['alumno_id'] ?? 0);
    $where = ['1=1'];
    if (in_array($tipo, ['mensualidad','inscripcion'])) $where[] = "p.tipo='".mysqli_real_escape_string($con,$tipo)."'";
    if (in_array($estado, ['pendiente','en_revision','pagado','rechazado'])) $where[] = "p.estado='".mysqli_real_escape_string($con,$estado)."'";
    if ($aid) $where[] = "p.alumno_id=$aid";
    $r = mysqli_query($con, "SELECT p.*, a.nombre alumno_nombre, a.apellido alumno_apellido, a.cedula alumno_cedula
        FROM pagos p JOIN alumnos a ON a.id=p.alumno_id
        WHERE ".implode(' AND ',$where)."
        ORDER BY FIELD(p.estado,'en_revision','pendiente','rechazado','pagado'), p.creado_en DESC");
    $rows = []; while ($f = mysqli_fetch_assoc($r)) $rows[] = $f;
    echo json_encode(['ok'=>true,'data'=>$rows]); exit;
}

/* ════ ADMIN: marcar pagado directo (efectivo, etc.) ════════════════ */
if ($action === 'pago_marcar_pagado') {
    if (!in_array($rol, ['superadmin','admin'])) json_fail_pg('Sin permiso.');
    $id = (int)($_POST['id'] ?? 0);
    mysqli_query($con, "UPDATE pagos SET estado='pagado',revisado_por=$uid,revisado_en=NOW() WHERE id=$id");
    log_audit($con, $uid, 'PAGO_MARCAR_PAGADO', "id=$id");
    echo json_encode(['ok'=>true,'msg'=>'Marcado como pagado.']); exit;
}

/* ════ ADMIN: aprobar / rechazar comprobante ════════════════════════ */
if ($action === 'pago_aprobar' || $action === 'pago_rechazar') {
    if (!in_array($rol, ['superadmin','admin'])) json_fail_pg('Sin permiso.');
    $id = (int)($_POST['id'] ?? 0);
    $nuevoEstado = $action === 'pago_aprobar' ? 'pagado' : 'rechazado';
    $r = mysqli_query($con, "SELECT alumno_id,concepto FROM pagos WHERE id=$id LIMIT 1");
    $p = mysqli_fetch_assoc($r);
    if (!$p) json_fail_pg('Pago no encontrado.');
    mysqli_query($con, "UPDATE pagos SET estado='$nuevoEstado',revisado_por=$uid,revisado_en=NOW() WHERE id=$id");
    log_audit($con, $uid, strtoupper($action), "id=$id");
    $stN = mysqli_prepare($con, "INSERT INTO notificaciones(tipo,titulo,mensaje,usuario_id,referencia_id) SELECT 'pago_revisado',?,?,usuario_id,? FROM alumnos WHERE id=?");
    $tit = $nuevoEstado === 'pagado' ? 'Pago confirmado' : 'Pago rechazado';
    $msg = $nuevoEstado === 'pagado' ? "Tu pago de \"{$p['concepto']}\" fue confirmado." : "Tu comprobante de \"{$p['concepto']}\" fue rechazado — subí uno nuevo.";
    mysqli_stmt_bind_param($stN, 'ssii', $tit, $msg, $id, $p['alumno_id']);
    mysqli_stmt_execute($stN);
    echo json_encode(['ok'=>true,'msg'=>$nuevoEstado === 'pagado' ? 'Pago confirmado.' : 'Pago rechazado.']); exit;
}

/* ════ ADMIN: acciones en lote (seleccionar todo / varios) ══════════
 * Mismo efecto que marcar_pagado/aprobar/rechazar/eliminar de a uno,
 * pero sobre varios cobros seleccionados a la vez. Una fila cuyo
 * estado ya no admite esa transición (p. ej. intentar "aprobar" algo
 * que ya no está en_revision) se cuenta como omitida en vez de romper
 * el lote entero.
 */
if ($action === 'pago_lote') {
    if (!in_array($rol, ['superadmin','admin'])) json_fail_pg('Sin permiso.');
    $sub = trim($_POST['sub_accion'] ?? '');
    if (!in_array($sub, ['marcar_pagado','aprobar','rechazar','eliminar'])) json_fail_pg('Acción inválida.');
    $ids = array_values(array_unique(array_filter(array_map('intval', explode(',', trim($_POST['ids'] ?? ''))))));
    if (!$ids) json_fail_pg('No hay cobros seleccionados.');

    $ok = 0; $omitidos = 0;
    foreach ($ids as $id) {
        $p = mysqli_fetch_assoc(mysqli_query($con, "SELECT * FROM pagos WHERE id=$id LIMIT 1"));
        if (!$p) { $omitidos++; continue; }

        if ($sub === 'marcar_pagado') {
            if ($p['estado'] !== 'pendiente') { $omitidos++; continue; }
            mysqli_query($con, "UPDATE pagos SET estado='pagado',revisado_por=$uid,revisado_en=NOW() WHERE id=$id");
            log_audit($con, $uid, 'PAGO_MARCAR_PAGADO', "id=$id (lote)");
            $ok++;
        } elseif ($sub === 'aprobar' || $sub === 'rechazar') {
            if ($p['estado'] !== 'en_revision') { $omitidos++; continue; }
            $nuevoEstado = $sub === 'aprobar' ? 'pagado' : 'rechazado';
            mysqli_query($con, "UPDATE pagos SET estado='$nuevoEstado',revisado_por=$uid,revisado_en=NOW() WHERE id=$id");
            log_audit($con, $uid, 'PAGO_'.strtoupper($sub), "id=$id (lote)");
            $stN = mysqli_prepare($con, "INSERT INTO notificaciones(tipo,titulo,mensaje,usuario_id,referencia_id) SELECT 'pago_revisado',?,?,usuario_id,? FROM alumnos WHERE id=?");
            $tit = $nuevoEstado === 'pagado' ? 'Pago confirmado' : 'Pago rechazado';
            $msg = $nuevoEstado === 'pagado' ? "Tu pago de \"{$p['concepto']}\" fue confirmado." : "Tu comprobante de \"{$p['concepto']}\" fue rechazado — subí uno nuevo.";
            mysqli_stmt_bind_param($stN, 'ssii', $tit, $msg, $id, $p['alumno_id']);
            mysqli_stmt_execute($stN);
            $ok++;
        } elseif ($sub === 'eliminar') {
            if ($p['comprobante'] && file_exists(__DIR__.'/../'.$p['comprobante'])) @unlink(__DIR__.'/../'.$p['comprobante']);
            mysqli_query($con, "DELETE FROM pagos WHERE id=$id");
            log_audit($con, $uid, 'PAGO_ELIMINAR', "id=$id (lote)");
            $ok++;
        }
    }
    $msg = "$ok cobro(s) actualizados." . ($omitidos ? " $omitidos omitido(s) por no aplicar la acción a su estado actual." : '');
    echo json_encode(['ok'=>true,'msg'=>$msg,'actualizados'=>$ok,'omitidos'=>$omitidos]); exit;
}

/* ════ ADMIN: eliminar un cobro (si se cargó mal) ═══════════════════ */
if ($action === 'pago_eliminar') {
    if (!in_array($rol, ['superadmin','admin'])) json_fail_pg('Sin permiso.');
    $id = (int)($_POST['id'] ?? 0);
    $p = mysqli_fetch_assoc(mysqli_query($con, "SELECT comprobante FROM pagos WHERE id=$id LIMIT 1"));
    mysqli_query($con, "DELETE FROM pagos WHERE id=$id");
    if ($p && $p['comprobante'] && file_exists(__DIR__.'/../'.$p['comprobante'])) @unlink(__DIR__.'/../'.$p['comprobante']);
    log_audit($con, $uid, 'PAGO_ELIMINAR', "id=$id");
    echo json_encode(['ok'=>true,'msg'=>'Eliminado.']); exit;
}

/* ════ ALUMNO: ver mis pagos ════════════════════════════════════════ */
if ($action === 'mis_pagos') {
    if ($rol !== 'alumno') json_fail_pg('Solo un alumno.');
    $aid = alumno_id_del_usuario($con, $uid);
    if (!$aid) json_fail_pg('No se encontró tu registro de alumno.');
    $r = mysqli_query($con, "SELECT * FROM pagos WHERE alumno_id=$aid ORDER BY FIELD(estado,'pendiente','rechazado','en_revision','pagado'), creado_en DESC");
    $rows = []; while ($f = mysqli_fetch_assoc($r)) $rows[] = $f;
    echo json_encode(['ok'=>true,'data'=>$rows]); exit;
}

/* ════ ALUMNO: subir comprobante de un pago pendiente ═══════════════ */
if ($action === 'pago_subir_comprobante') {
    if ($rol !== 'alumno') json_fail_pg('Solo un alumno.');
    $aid = alumno_id_del_usuario($con, $uid);
    if (!$aid) json_fail_pg('No se encontró tu registro de alumno.');
    $id = (int)($_POST['id'] ?? 0);
    $pago = mysqli_fetch_assoc(mysqli_query($con, "SELECT * FROM pagos WHERE id=$id AND alumno_id=$aid LIMIT 1"));
    if (!$pago) json_fail_pg('Pago no encontrado.');
    if (!in_array($pago['estado'], ['pendiente','rechazado'])) json_fail_pg('Este pago ya fue procesado.');

    if (!isset($_FILES['comprobante']) || $_FILES['comprobante']['error'] !== UPLOAD_ERR_OK) {
        json_fail_pg('Adjuntá la captura de tu pago móvil o transferencia.');
    }
    $file = $_FILES['comprobante'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','webp'])) json_fail_pg('El comprobante debe ser una imagen (jpg, png o webp).');
    if ($file['size'] > 5*1024*1024) json_fail_pg('La imagen es muy grande (máx. 5MB).');
    $imgInfo = @getimagesize($file['tmp_name']);
    if ($imgInfo === false || !in_array($imgInfo['mime'] ?? '', ['image/jpeg','image/png','image/webp'])) json_fail_pg('El archivo no es una imagen válida.');

    $dir = __DIR__.'/../uploads/comprobantes/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $fname = 'pago_'.$aid.'_'.$id.'_'.time().'_'.random_int(1000,9999).'.'.$ext;
    if (!move_uploaded_file($file['tmp_name'], $dir.$fname)) json_fail_pg('No se pudo guardar el comprobante.');
    $ruta = 'uploads/comprobantes/'.$fname;

    $st = mysqli_prepare($con, "UPDATE pagos SET comprobante=?,estado='en_revision' WHERE id=?");
    mysqli_stmt_bind_param($st, 'si', $ruta, $id);
    if (!mysqli_stmt_execute($st)) { @unlink($dir.$fname); json_fail_pg('No se pudo registrar el comprobante.'); }
    log_audit($con, $uid, 'PAGO_SUBIR_COMPROBANTE', "id=$id");

    $stN = mysqli_prepare($con, "INSERT INTO notificaciones(tipo,titulo,mensaje,para_rol,referencia_id) VALUES('pago_pendiente_revision',?,?,'admin',?)");
    $tit = 'Comprobante de pago recibido';
    $msg = "Un alumno subió un comprobante para \"{$pago['concepto']}\" — revisalo.";
    mysqli_stmt_bind_param($stN, 'ssi', $tit, $msg, $id);
    mysqli_stmt_execute($stN);

    echo json_encode(['ok'=>true,'msg'=>'¡Listo! Tu comprobante quedó enviado para revisión.']); exit;
}

json_fail_pg('Acción no reconocida.');
