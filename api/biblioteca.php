<?php
/**
 * IBBS — Biblioteca: catálogo de libros + compras.
 *
 * Lo cargan admin/superadmin o cualquier docente (portal_docente.php);
 * lo compran los alumnos (portal_alumno.php) — comprobante de pago +
 * revisión admin, mismo patrón que api/materia_solicitud.php.
 *
 * El archivo del libro es contenido PAGO: nunca se sirve como link
 * directo. Vive en uploads/libros_privados/ (bloqueado por su propio
 * .htaccess, `Require all denied`) y solo sale por acá, acción
 * "descargar", después de validar que quien lo pide tiene derecho.
 */

ob_start();
error_reporting(0);
require_once __DIR__.'/../config/bootstrap.php';

$isDownload = ($_SERVER['REQUEST_METHOD'] === 'GET') && (($_GET['action'] ?? '') === 'descargar');

if (empty($_SESSION['loggedin'])) {
    if ($isDownload) { ob_end_clean(); http_response_code(403); die('Sesión expirada.'); }
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok'=>false,'msg'=>'Sesión expirada.']); exit;
}

if (!$isDownload) {
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    csrf_require_post();
}

$con = db();
if (!$con) {
    if ($isDownload) { ob_end_clean(); http_response_code(500); die('Error de conexión a la base de datos.'); }
    echo json_encode(['ok'=>false,'msg'=>'Error de conexión a la base de datos.']); exit;
}

$uid = (int)($_SESSION['user_id'] ?? 0);
$rol = $_SESSION['rol'] ?? 'alumno';

function json_fail($msg) { echo json_encode(['ok'=>false,'msg'=>$msg]); exit; }

/* ════ DESCARGA (GET, fuera del switch de acciones JSON) ══════════ */
if ($isDownload) {
    $id = (int)($_GET['id'] ?? 0);
    $libro = mysqli_fetch_assoc(mysqli_query($con, "SELECT * FROM libros WHERE id=$id LIMIT 1"));
    if (!$libro) { http_response_code(404); die('Libro no encontrado.'); }

    $puedeDescargar = false;
    if (in_array($rol, ['superadmin','admin'])) {
        $puedeDescargar = true;
    } elseif ($rol === 'profesor' && (int)$libro['creado_por'] === $uid) {
        $puedeDescargar = true;
    } elseif ($rol === 'alumno') {
        if ((float)$libro['precio'] <= 0) {
            $puedeDescargar = true;
        } else {
            $al = mysqli_fetch_assoc(mysqli_query($con, "SELECT id FROM alumnos WHERE usuario_id=$uid LIMIT 1"));
            $aid = $al ? (int)$al['id'] : 0;
            if ($aid) {
                $compra = mysqli_fetch_assoc(mysqli_query($con, "SELECT id FROM libro_compras WHERE libro_id=$id AND alumno_id=$aid AND estado='activado' LIMIT 1"));
                $puedeDescargar = (bool)$compra;
            }
        }
    }
    if (!$puedeDescargar) { http_response_code(403); die('No compraste este libro todavía.'); }

    $path = __DIR__.'/../'.$libro['archivo'];
    if (!file_exists($path)) { http_response_code(404); die('El archivo ya no está disponible.'); }

    log_audit($con, $uid, 'LIBRO_DESCARGAR', "libro=$id");
    ob_end_clean();
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="'.basename($libro['archivo_nombre']).'"');
    header('Content-Length: '.filesize($path));
    readfile($path);
    exit;
}

$action = trim($_POST['action'] ?? '');

/* ════ CATÁLOGO ════════════════════════════════════════════════ */
if ($action === 'libro_list') {
    if (in_array($rol, ['superadmin','admin'])) {
        $r = mysqli_query($con, "SELECT l.*, u.usuario creado_por_nombre FROM libros l LEFT JOIN usuarios u ON u.id=l.creado_por ORDER BY l.creado_en DESC");
        $rows = []; while ($f = mysqli_fetch_assoc($r)) $rows[] = $f;
        echo json_encode(['ok'=>true,'data'=>$rows]); exit;
    }
    if ($rol === 'profesor') {
        $r = mysqli_query($con, "SELECT * FROM libros WHERE creado_por=$uid ORDER BY creado_en DESC");
        $rows = []; while ($f = mysqli_fetch_assoc($r)) $rows[] = $f;
        echo json_encode(['ok'=>true,'data'=>$rows]); exit;
    }
    // Alumno: solo catálogo activo, con el estado de SU compra (si existe)
    $al = mysqli_fetch_assoc(mysqli_query($con, "SELECT id FROM alumnos WHERE usuario_id=$uid LIMIT 1"));
    $aid = $al ? (int)$al['id'] : 0;
    $r = mysqli_query($con, "SELECT l.id,l.titulo,l.autor,l.descripcion,l.categoria,l.precio,l.portada,l.creado_en,
        c.estado estado_compra
        FROM libros l
        LEFT JOIN libro_compras c ON c.libro_id=l.id AND c.alumno_id=$aid
        WHERE l.activo=1 ORDER BY l.creado_en DESC");
    $rows = []; while ($f = mysqli_fetch_assoc($r)) $rows[] = $f;
    echo json_encode(['ok'=>true,'data'=>$rows]); exit;
}

if ($action === 'mis_libros') {
    if ($rol !== 'alumno') { json_fail('Solo un alumno tiene "Mi Biblioteca".'); }
    $al = mysqli_fetch_assoc(mysqli_query($con, "SELECT id FROM alumnos WHERE usuario_id=$uid LIMIT 1"));
    $aid = $al ? (int)$al['id'] : 0;
    $r = mysqli_query($con, "SELECT l.id,l.titulo,l.autor,l.portada,c.fecha_revision
        FROM libro_compras c JOIN libros l ON l.id=c.libro_id
        WHERE c.alumno_id=$aid AND c.estado='activado' ORDER BY c.fecha_revision DESC");
    $rows = []; while ($f = mysqli_fetch_assoc($r)) $rows[] = $f;
    echo json_encode(['ok'=>true,'data'=>$rows]); exit;
}

if ($action === 'libro_get') {
    $id = (int)($_POST['id'] ?? 0);
    $libro = mysqli_fetch_assoc(mysqli_query($con, "SELECT * FROM libros WHERE id=$id LIMIT 1"));
    if (!$libro) json_fail('Libro no encontrado.');
    $puede = in_array($rol, ['superadmin','admin']) || ((int)$libro['creado_por'] === $uid);
    if (!$puede) json_fail('Sin permiso.');
    echo json_encode(['ok'=>true,'data'=>$libro]); exit;
}

if ($action === 'libro_create' || $action === 'libro_update') {
    if (!in_array($rol, ['superadmin','admin','profesor'])) json_fail('Sin permiso.');
    $id          = (int)($_POST['id'] ?? 0);
    $titulo      = trim($_POST['titulo'] ?? '');
    $autor       = trim($_POST['autor'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $categoria   = trim($_POST['categoria'] ?? '');
    $precio      = max(0, (float)str_replace(',', '.', $_POST['precio'] ?? '0'));
    if ($titulo === '') json_fail('Ponle un título al libro.');

    $libroExistente = null;
    if ($action === 'libro_update') {
        if (!$id) json_fail('Libro requerido.');
        $libroExistente = mysqli_fetch_assoc(mysqli_query($con, "SELECT * FROM libros WHERE id=$id LIMIT 1"));
        if (!$libroExistente) json_fail('Libro no encontrado.');
        $puede = in_array($rol, ['superadmin','admin']) || ((int)$libroExistente['creado_por'] === $uid);
        if (!$puede) json_fail('Sin permiso — solo podés editar los libros que vos mismo cargaste.');
    }

    // Portada (opcional, imagen pública)
    $rutaPortada = $libroExistente['portada'] ?? null;
    if (isset($_FILES['portada']) && $_FILES['portada']['error'] === UPLOAD_ERR_OK) {
        $f = $_FILES['portada'];
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        $allowedExt = ['jpg','jpeg','png','webp'];
        if (!in_array($ext, $allowedExt)) json_fail('La portada debe ser una imagen (jpg, png o webp).');
        if ($f['size'] > 3*1024*1024) json_fail('La portada es muy grande (máx. 3MB).');
        $img = @getimagesize($f['tmp_name']);
        $allowedMime = ['image/jpeg','image/png','image/webp'];
        if ($img === false || !in_array($img['mime'] ?? '', $allowedMime)) json_fail('La portada no es una imagen válida.');
        $dir = __DIR__.'/../uploads/libros/portadas/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $fname = 'portada_'.time().'_'.bin2hex(random_bytes(6)).'.'.$ext;
        if (!move_uploaded_file($f['tmp_name'], $dir.$fname)) json_fail('No se pudo guardar la portada.');
        if ($rutaPortada && file_exists(__DIR__.'/../'.$rutaPortada)) @unlink(__DIR__.'/../'.$rutaPortada);
        $rutaPortada = 'uploads/libros/portadas/'.$fname;
    }

    // Archivo del libro (obligatorio al crear; opcional al editar —solo si lo reemplazan)
    $rutaArchivo = $libroExistente['archivo'] ?? null;
    $nombreArchivo = $libroExistente['archivo_nombre'] ?? null;
    $tipoArchivo = $libroExistente['archivo_tipo'] ?? null;
    if (isset($_FILES['archivo']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
        $f = $_FILES['archivo'];
        if ($f['size'] > 40*1024*1024) json_fail('El archivo del libro es muy grande (máx. 40MB).');
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        $tiposPermitidos = [
            'pdf'  => ['application/pdf'],
            'epub' => ['application/epub+zip', 'application/zip'],
        ];
        if (!isset($tiposPermitidos[$ext])) json_fail('El libro debe ser un PDF o EPUB.');
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $f['tmp_name']);
        finfo_close($finfo);
        if (!in_array($mime, $tiposPermitidos[$ext])) json_fail('El contenido del archivo no coincide con su extensión.');
        $dir = __DIR__.'/../uploads/libros_privados/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $fname = 'libro_'.time().'_'.bin2hex(random_bytes(8)).'.'.$ext;
        if (!move_uploaded_file($f['tmp_name'], $dir.$fname)) json_fail('No se pudo guardar el archivo del libro.');
        if ($rutaArchivo && file_exists(__DIR__.'/../'.$rutaArchivo)) @unlink(__DIR__.'/../'.$rutaArchivo);
        $rutaArchivo   = 'uploads/libros_privados/'.$fname;
        $nombreArchivo = mb_substr(basename($f['name']), 0, 255);
        $tipoArchivo   = $ext;
    }
    if (!$rutaArchivo) json_fail('Subí el archivo del libro (PDF o EPUB).');

    if ($action === 'libro_create') {
        $st = mysqli_prepare($con, "INSERT INTO libros(titulo,autor,descripcion,categoria,precio,portada,archivo,archivo_nombre,archivo_tipo,creado_por) VALUES(?,?,?,?,?,?,?,?,?,?)");
        mysqli_stmt_bind_param($st, 'ssssdsssii', $titulo, $autor, $descripcion, $categoria, $precio, $rutaPortada, $rutaArchivo, $nombreArchivo, $tipoArchivo, $uid);
        if (!mysqli_stmt_execute($st)) json_fail('No se pudo guardar el libro: '.mysqli_error($con));
        log_audit($con, $uid, 'LIBRO_CREAR', "ID=".mysqli_insert_id($con)." titulo=$titulo");
        echo json_encode(['ok'=>true,'msg'=>'Libro publicado en la biblioteca.']); exit;
    } else {
        $st = mysqli_prepare($con, "UPDATE libros SET titulo=?,autor=?,descripcion=?,categoria=?,precio=?,portada=?,archivo=?,archivo_nombre=?,archivo_tipo=? WHERE id=?");
        mysqli_stmt_bind_param($st, 'ssssdsssii', $titulo, $autor, $descripcion, $categoria, $precio, $rutaPortada, $rutaArchivo, $nombreArchivo, $tipoArchivo, $id);
        mysqli_stmt_execute($st);
        log_audit($con, $uid, 'LIBRO_EDITAR', "ID=$id");
        echo json_encode(['ok'=>true,'msg'=>'Libro actualizado.']); exit;
    }
}

if ($action === 'libro_toggle') {
    $id = (int)($_POST['id'] ?? 0);
    $libro = mysqli_fetch_assoc(mysqli_query($con, "SELECT creado_por,activo FROM libros WHERE id=$id LIMIT 1"));
    if (!$libro) json_fail('Libro no encontrado.');
    $puede = in_array($rol, ['superadmin','admin']) || ((int)$libro['creado_por'] === $uid);
    if (!$puede) json_fail('Sin permiso.');
    $nuevo = $libro['activo'] ? 0 : 1;
    mysqli_query($con, "UPDATE libros SET activo=$nuevo WHERE id=$id");
    log_audit($con, $uid, 'LIBRO_TOGGLE', "ID=$id activo=$nuevo");
    echo json_encode(['ok'=>true,'msg'=>$nuevo?'Libro visible en el catálogo.':'Libro oculto del catálogo.']); exit;
}

if ($action === 'libro_delete') {
    $id = (int)($_POST['id'] ?? 0);
    $libro = mysqli_fetch_assoc(mysqli_query($con, "SELECT * FROM libros WHERE id=$id LIMIT 1"));
    if (!$libro) json_fail('Libro no encontrado.');
    $puede = in_array($rol, ['superadmin','admin']) || ((int)$libro['creado_por'] === $uid);
    if (!$puede) json_fail('Sin permiso.');
    mysqli_query($con, "DELETE FROM libros WHERE id=$id");
    if ($libro['portada'] && file_exists(__DIR__.'/../'.$libro['portada'])) @unlink(__DIR__.'/../'.$libro['portada']);
    if ($libro['archivo'] && file_exists(__DIR__.'/../'.$libro['archivo'])) @unlink(__DIR__.'/../'.$libro['archivo']);
    log_audit($con, $uid, 'LIBRO_ELIMINAR', "ID=$id titulo=".$libro['titulo']);
    echo json_encode(['ok'=>true,'msg'=>'Libro eliminado.']); exit;
}

/* ════ DATOS DE PAGO DE LA INSTITUCIÓN ══════════════════════════ */
if ($action === 'datos_pago_get') {
    $d = mysqli_fetch_assoc(mysqli_query($con, "SELECT * FROM datos_pago WHERE id=1 LIMIT 1"));
    echo json_encode(['ok'=>true,'data'=>$d ?: []]); exit;
}
if ($action === 'datos_pago_update') {
    if (!in_array($rol, ['superadmin','admin'])) json_fail('Sin permiso.');
    $titular = trim($_POST['titular'] ?? '');
    $banco   = trim($_POST['banco'] ?? '');
    $cuenta  = trim($_POST['cuenta'] ?? '');
    $ced     = trim($_POST['cedula_rif'] ?? '');
    $pm      = trim($_POST['pago_movil_telefono'] ?? '');
    $instr   = trim($_POST['instrucciones'] ?? '');
    $st = mysqli_prepare($con, "UPDATE datos_pago SET titular=?,banco=?,cuenta=?,cedula_rif=?,pago_movil_telefono=?,instrucciones=?,actualizado_en=NOW() WHERE id=1");
    mysqli_stmt_bind_param($st, 'ssssss', $titular, $banco, $cuenta, $ced, $pm, $instr);
    mysqli_stmt_execute($st);
    log_audit($con, $uid, 'DATOS_PAGO_ACTUALIZAR', '');
    echo json_encode(['ok'=>true,'msg'=>'Datos de pago actualizados.']); exit;
}

/* ════ COMPRAS ═══════════════════════════════════════════════════ */
if ($action === 'compra_crear') {
    if ($rol !== 'alumno') json_fail('Solo un alumno puede comprar un libro.');
    $lid = (int)($_POST['libro_id'] ?? 0);
    if (!$lid) json_fail('Libro requerido.');
    $al = mysqli_fetch_assoc(mysqli_query($con, "SELECT id FROM alumnos WHERE usuario_id=$uid LIMIT 1"));
    if (!$al) json_fail('No se encontró tu registro de alumno.');
    $aid = (int)$al['id'];
    $libro = mysqli_fetch_assoc(mysqli_query($con, "SELECT * FROM libros WHERE id=$lid AND activo=1 LIMIT 1"));
    if (!$libro) json_fail('Libro no disponible.');

    $ex = mysqli_fetch_assoc(mysqli_query($con, "SELECT id,estado FROM libro_compras WHERE libro_id=$lid AND alumno_id=$aid ORDER BY id DESC LIMIT 1"));
    if ($ex && $ex['estado'] !== 'rechazado') json_fail($ex['estado']==='activado' ? 'Ya tenés este libro activado.' : 'Ya tenés una solicitud pendiente para este libro.');

    $precio = (float)$libro['precio'];
    if ($precio <= 0) {
        // Libro gratis: se activa solo, sin comprobante ni revisión.
        $st = mysqli_prepare($con, "INSERT INTO libro_compras(libro_id,alumno_id,precio_pagado,estado,revisado_por,fecha_revision) VALUES(?,?,0,'activado',?,NOW())");
        mysqli_stmt_bind_param($st, 'iii', $lid, $aid, $uid);
        mysqli_stmt_execute($st);
        log_audit($con, $uid, 'LIBRO_ACTIVAR_GRATIS', "libro=$lid alumno=$aid");
        echo json_encode(['ok'=>true,'msg'=>'¡Listo! Este libro es gratuito y ya está en tu biblioteca.']); exit;
    }

    if (!isset($_FILES['comprobante']) || $_FILES['comprobante']['error'] !== UPLOAD_ERR_OK) {
        json_fail('Adjuntá la captura de tu pago móvil o transferencia para comprar este libro.');
    }
    $file = $_FILES['comprobante'];
    $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','webp'])) json_fail('El comprobante debe ser una imagen (jpg, png o webp).');
    if ($file['size'] > 5*1024*1024) json_fail('La imagen es muy grande (máx. 5MB).');
    $imgInfo = @getimagesize($file['tmp_name']);
    if ($imgInfo === false || !in_array($imgInfo['mime'] ?? '', ['image/jpeg','image/png','image/webp'])) json_fail('El archivo no es una imagen válida.');

    $dir = __DIR__.'/../uploads/comprobantes/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $fname = 'libro_'.$aid.'_'.$lid.'_'.time().'_'.random_int(1000,9999).'.'.$ext;
    if (!move_uploaded_file($file['tmp_name'], $dir.$fname)) json_fail('No se pudo guardar el comprobante.');
    $ruta = 'uploads/comprobantes/'.$fname;

    $st = mysqli_prepare($con, "INSERT INTO libro_compras(libro_id,alumno_id,comprobante,precio_pagado,estado) VALUES(?,?,?,?,'pendiente')");
    mysqli_stmt_bind_param($st, 'iisd', $lid, $aid, $ruta, $precio);
    if (!mysqli_stmt_execute($st)) { @unlink($dir.$fname); json_fail('No se pudo registrar la compra.'); }
    $compraId = mysqli_insert_id($con);
    log_audit($con, $uid, 'LIBRO_COMPRA_CREAR', "libro=$lid alumno=$aid compra=$compraId");

    $stN = mysqli_prepare($con, "INSERT INTO notificaciones(tipo,titulo,mensaje,para_rol,referencia_id) VALUES('solicitud_libro',?,?,'admin',?)");
    $tituloN  = "Nueva compra de libro: {$libro['titulo']}";
    $mensajeN = "Un alumno quiere comprar \"{$libro['titulo']}\" (Bs./USD {$precio}) y adjuntó su comprobante de pago. Revisalo antes de activarlo.";
    mysqli_stmt_bind_param($stN, 'ssi', $tituloN, $mensajeN, $compraId);
    mysqli_stmt_execute($stN);
    $notifId = mysqli_insert_id($con);
    if (function_exists('ws_broadcast_user')) {
        $rAdmins = mysqli_query($con, "SELECT id FROM usuarios WHERE rol IN ('admin','superadmin') AND activo=1");
        while ($fAdmin = mysqli_fetch_assoc($rAdmins)) {
            ws_broadcast_user((int)$fAdmin['id'], 'notificacion', [
                'id' => $notifId, 'tipo' => 'solicitud_libro', 'titulo' => $tituloN,
                'mensaje' => $mensajeN, 'referencia_id' => $compraId, 'creado_en' => date('Y-m-d H:i:s'),
            ]);
        }
    }
    echo json_encode(['ok'=>true,'msg'=>'¡Listo! Tu compra y comprobante quedaron enviados. Te avisaremos apenas la administración la active.']); exit;
}

if ($action === 'compra_get') {
    if (!in_array($rol, ['superadmin','admin'])) json_fail('Sin permiso.');
    $id = (int)($_POST['id'] ?? 0);
    $c = mysqli_fetch_assoc(mysqli_query($con, "SELECT c.*, l.titulo libro_titulo, a.nombre alumno_nombre, a.apellido alumno_apellido
        FROM libro_compras c JOIN libros l ON l.id=c.libro_id JOIN alumnos a ON a.id=c.alumno_id WHERE c.id=$id LIMIT 1"));
    if (!$c) json_fail('Compra no encontrada.');
    echo json_encode(['ok'=>true,'data'=>$c]); exit;
}

if ($action === 'compra_list') {
    if (!in_array($rol, ['superadmin','admin'])) json_fail('Sin permiso.');
    $estado = trim($_POST['estado'] ?? 'pendiente');
    $w = in_array($estado, ['pendiente','activado','rechazado']) ? "WHERE c.estado='".mysqli_real_escape_string($con,$estado)."'" : '';
    $r = mysqli_query($con, "SELECT c.*, l.titulo libro_titulo, a.nombre alumno_nombre, a.apellido alumno_apellido, a.cedula alumno_cedula
        FROM libro_compras c JOIN libros l ON l.id=c.libro_id JOIN alumnos a ON a.id=c.alumno_id
        $w ORDER BY c.fecha DESC LIMIT 200");
    $rows = []; while ($f = mysqli_fetch_assoc($r)) $rows[] = $f;
    echo json_encode(['ok'=>true,'data'=>$rows]); exit;
}

if ($action === 'compra_aprobar' || $action === 'compra_rechazar') {
    if (!in_array($rol, ['superadmin','admin'])) json_fail('Sin permiso.');
    $id = (int)($_POST['id'] ?? 0);
    $c = mysqli_fetch_assoc(mysqli_query($con, "SELECT c.*, l.titulo libro_titulo, a.usuario_id, a.nombre alumno_nombre, a.apellido alumno_apellido
        FROM libro_compras c JOIN libros l ON l.id=c.libro_id JOIN alumnos a ON a.id=c.alumno_id WHERE c.id=$id LIMIT 1"));
    if (!$c) json_fail('Compra no encontrada.');
    if ($c['estado'] !== 'pendiente') json_fail('Esta compra ya fue revisada.');

    if ($action === 'compra_aprobar') {
        mysqli_query($con, "UPDATE libro_compras SET estado='activado',revisado_por=$uid,fecha_revision=NOW() WHERE id=$id");
        log_audit($con, $uid, 'LIBRO_COMPRA_APROBAR', "compra=$id libro={$c['libro_id']} alumno={$c['alumno_id']}");
        if ($c['usuario_id']) {
            notificar_usuario($con, (int)$c['usuario_id'], 'solicitud_libro_activada', '¡Tu libro ya está activado!', "\"{$c['libro_titulo']}\" ya está disponible en tu biblioteca.");
        }
        $msg = "Se activó \"{$c['libro_titulo']}\" para {$c['alumno_nombre']} {$c['alumno_apellido']}.";
    } else {
        mysqli_query($con, "UPDATE libro_compras SET estado='rechazado',revisado_por=$uid,fecha_revision=NOW() WHERE id=$id");
        log_audit($con, $uid, 'LIBRO_COMPRA_RECHAZAR', "compra=$id libro={$c['libro_id']} alumno={$c['alumno_id']}");
        if ($c['usuario_id']) {
            notificar_usuario($con, (int)$c['usuario_id'], 'solicitud_libro_rechazada', 'Tu compra no pudo confirmarse', "No pudimos validar tu comprobante para \"{$c['libro_titulo']}\". Contactá a la administración.");
        }
        $msg = "Se rechazó la compra de \"{$c['libro_titulo']}\" para {$c['alumno_nombre']} {$c['alumno_apellido']}.";
    }
    mysqli_query($con, "UPDATE notificaciones SET leida=1 WHERE tipo='solicitud_libro' AND referencia_id=$id");
    echo json_encode(['ok'=>true,'msg'=>$msg]); exit;
}

echo json_encode(['ok'=>false,'msg'=>'Acción no reconocida.']); exit;
