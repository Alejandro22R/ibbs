<?php
/**
 * IBBS — Envío y aprobación de notas finales (api/notas_envio.php).
 *
 * El profesor puede cargar la nota final de cada alumno en cualquier
 * momento, entregue o no las actividades (ver "Nota a registrar" en
 * el Plan de Notas de portal_docente.php) y mandarlas a revisión del
 * administrador con la acción "crear": se guarda una foto exacta del
 * Plan de Notas (tareas, alumnos, notas de cada actividad y la nota
 * final que el profesor propuso) en notas_envios.snapshot_json, y se
 * notifica a todo admin/superadmin.
 *
 * El administrador ve ese mismo Plan de Notas con "detalle", puede
 * ajustar la nota final de cualquier alumno y "aprobar" — eso es lo
 * que realmente escribe materia_alumno.nota_final (igual que ya hacía
 * nota_guardar en api/ajax.php) y recién ahí el alumno puede verla en
 * su Calificaciones Finales. También puede "rechazar" con un
 * comentario para que el profesor corrija y vuelva a enviar.
 */

ob_start(); error_reporting(0);
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

function json_fail_ne($msg) { echo json_encode(['ok'=>false,'msg'=>$msg]); exit; }

// Arma el mismo Plan de Notas que ve el profesor (api/plan_notas.php,
// acción "docente"): cada tarea, cada alumno inscrito y su nota en
// cada una.
function ibbs_plan_notas_snapshot($con, $materia_id) {
    $tareas = [];
    $rt = mysqli_query($con, "SELECT id, titulo, fecha_limite, porcentaje, nota_maxima
                               FROM tareas WHERE materia_id=$materia_id ORDER BY fecha_limite ASC");
    while ($f = mysqli_fetch_assoc($rt)) $tareas[] = $f;

    $alumnos = [];
    $ra = mysqli_query($con, "SELECT a.id, a.nombre, a.apellido, a.cedula
                               FROM alumnos a JOIN materia_alumno ma ON ma.alumno_id=a.id
                               WHERE ma.materia_id=$materia_id ORDER BY a.apellido, a.nombre");
    while ($f = mysqli_fetch_assoc($ra)) $alumnos[] = $f;

    $notas = [];
    if ($tareas && $alumnos) {
        $rn = mysqli_query($con, "SELECT e.alumno_id, e.tarea_id, e.nota, e.observacion
                                   FROM entregas e JOIN tareas t ON t.id=e.tarea_id
                                   WHERE t.materia_id=$materia_id");
        while ($f = mysqli_fetch_assoc($rn)) {
            $notas[$f['alumno_id']][$f['tarea_id']] = ['nota' => $f['nota'], 'observacion' => $f['observacion']];
        }
    }
    return ['tareas' => $tareas, 'alumnos' => $alumnos, 'notas' => $notas];
}

if ($action === 'crear') {
    $mid = (int)($_POST['materia_id'] ?? 0);
    if (!$mid) json_fail_ne('Falta la materia.');
    if (!materia_puede_gestionar($con, $uid, $rol, $mid)) json_fail_ne('No tenés permiso sobre esta materia.');

    $pendiente = mysqli_fetch_assoc(mysqli_query($con, "SELECT id FROM notas_envios WHERE materia_id=$mid AND estado='pendiente' LIMIT 1"));
    if ($pendiente) json_fail_ne('Ya enviaste las notas de esta materia y están pendientes de aprobación — esperá la revisión del administrador antes de volver a enviar.');

    $propuestasRaw = $_POST['propuestas'] ?? '';
    $propuestas = json_decode($propuestasRaw, true);
    if (!is_array($propuestas) || !$propuestas) json_fail_ne('Tenés que cargar una nota para cada alumno antes de enviar.');

    $alumnosMateria = [];
    $ra = mysqli_query($con, "SELECT alumno_id FROM materia_alumno WHERE materia_id=$mid");
    while ($f = mysqli_fetch_assoc($ra)) $alumnosMateria[] = (int)$f['alumno_id'];
    if (!$alumnosMateria) json_fail_ne('Esta materia todavía no tiene alumnos inscritos.');

    $limpio = [];
    foreach ($alumnosMateria as $aid) {
        if (!isset($propuestas[$aid]) || $propuestas[$aid] === '' || $propuestas[$aid] === null) {
            json_fail_ne('Falta cargar la nota de uno o más alumnos — tenés que registrar la nota de todos antes de enviar.');
        }
        $n = (float)$propuestas[$aid];
        if ($n < 0 || $n > 20) json_fail_ne('Las notas deben estar entre 0 y 20.');
        $limpio[$aid] = $n;
    }

    $snapshot = ibbs_plan_notas_snapshot($con, $mid);
    $snapshot['propuestas'] = $limpio;
    $snapshot['enviado_en'] = date('c');
    $json = json_encode($snapshot);

    $st = mysqli_prepare($con, "INSERT INTO notas_envios(materia_id,docente_usuario_id,snapshot_json) VALUES(?,?,?)");
    mysqli_stmt_bind_param($st, 'iis', $mid, $uid, $json);
    mysqli_stmt_execute($st);
    $envioId = mysqli_insert_id($con);

    $mat = mysqli_fetch_assoc(mysqli_query($con, "SELECT nombre FROM materias WHERE id=$mid"));
    $docNombre = $_SESSION['usuario'] ?? 'Un profesor';
    log_audit($con, $uid, 'NOTAS_ENVIO_CREAR', "materia=$mid envio=$envioId alumnos=".count($limpio));

    $titulo = "Notas enviadas para aprobación";
    $mensaje = "$docNombre envió el plan de notas de \"".($mat['nombre'] ?? '')."\" (".count($limpio)." alumno(s)) para tu revisión.";
    $stN = mysqli_prepare($con, "INSERT INTO notificaciones(tipo,titulo,mensaje,para_rol,materia_id) VALUES('notas_envio',?,?,'admin',?)");
    mysqli_stmt_bind_param($stN, 'ssi', $titulo, $mensaje, $mid);
    mysqli_stmt_execute($stN);

    echo json_encode(['ok'=>true,'msg'=>'Notas enviadas. El administrador las va a revisar antes de que tus alumnos puedan verlas.']); exit;
}

if ($action === 'estado_materia') {
    $mid = (int)($_POST['materia_id'] ?? 0);
    if (!$mid) json_fail_ne('Falta la materia.');
    if (!materia_puede_gestionar($con, $uid, $rol, $mid)) json_fail_ne('No tenés permiso sobre esta materia.');
    $envio = mysqli_fetch_assoc(mysqli_query($con, "SELECT id,estado,comentario_admin,creado_en,revisado_en
                                                     FROM notas_envios WHERE materia_id=$mid ORDER BY id DESC LIMIT 1"));
    echo json_encode(['ok'=>true,'data'=>$envio ?: null]); exit;
}

if ($action === 'pendientes') {
    if (!in_array($rol, ['superadmin','admin'])) json_fail_ne('Sin permiso.');
    $r = mysqli_query($con, "SELECT ne.id, ne.materia_id, ne.creado_en, m.nombre materia_nombre, m.codigo materia_codigo,
                                     u.usuario docente_usuario
                              FROM notas_envios ne
                              JOIN materias m ON m.id=ne.materia_id
                              JOIN usuarios u ON u.id=ne.docente_usuario_id
                              WHERE ne.estado='pendiente' ORDER BY ne.creado_en ASC");
    $rows = []; while ($f = mysqli_fetch_assoc($r)) $rows[] = $f;
    echo json_encode(['ok'=>true,'data'=>$rows]); exit;
}

if ($action === 'detalle') {
    if (!in_array($rol, ['superadmin','admin'])) json_fail_ne('Sin permiso.');
    $id = (int)($_POST['id'] ?? 0);
    $envio = mysqli_fetch_assoc(mysqli_query($con, "SELECT ne.*, m.nombre materia_nombre, u.usuario docente_usuario
                                                     FROM notas_envios ne
                                                     JOIN materias m ON m.id=ne.materia_id
                                                     JOIN usuarios u ON u.id=ne.docente_usuario_id
                                                     WHERE ne.id=$id LIMIT 1"));
    if (!$envio) json_fail_ne('Envío no encontrado.');
    $envio['snapshot'] = json_decode($envio['snapshot_json'], true);
    unset($envio['snapshot_json']);
    echo json_encode(['ok'=>true,'data'=>$envio]); exit;
}

if ($action === 'aprobar') {
    if (!in_array($rol, ['superadmin','admin'])) json_fail_ne('Sin permiso.');
    $id = (int)($_POST['id'] ?? 0);
    $notasRaw = $_POST['notas'] ?? '';
    $notas = json_decode($notasRaw, true);
    if (!is_array($notas) || !$notas) json_fail_ne('Faltan las notas a registrar.');

    $envio = mysqli_fetch_assoc(mysqli_query($con, "SELECT * FROM notas_envios WHERE id=$id LIMIT 1"));
    if (!$envio) json_fail_ne('Envío no encontrado.');
    if ($envio['estado'] !== 'pendiente') json_fail_ne('Este envío ya fue revisado.');
    $mid = (int)$envio['materia_id'];
    $fecha = date('Y-m-d');

    foreach ($notas as $alumnoId => $notaRaw) {
        $aid = (int)$alumnoId;
        $n = is_numeric($notaRaw) ? (float)$notaRaw : null;
        if ($n === null || $n < 0 || $n > 20) continue;
        $ex = mysqli_fetch_assoc(mysqli_query($con, "SELECT id FROM materia_alumno WHERE materia_id=$mid AND alumno_id=$aid LIMIT 1"));
        if ($ex) {
            mysqli_query($con, "UPDATE materia_alumno SET nota_final=$n,nota_fecha='$fecha',nota_registrada_por=$uid,nota_actualizada_en=NOW() WHERE materia_id=$mid AND alumno_id=$aid");
        } else {
            mysqli_query($con, "INSERT INTO materia_alumno(materia_id,alumno_id,nota_final,nota_fecha,nota_registrada_por,nota_actualizada_en) VALUES($mid,$aid,$n,'$fecha',$uid,NOW())");
        }
    }

    $comentario = mb_substr(trim($_POST['comentario'] ?? ''), 0, 500);
    $stU = mysqli_prepare($con, "UPDATE notas_envios SET estado='aprobado',comentario_admin=?,revisado_por=?,revisado_en=NOW() WHERE id=?");
    mysqli_stmt_bind_param($stU, 'sii', $comentario, $uid, $id);
    mysqli_stmt_execute($stU);
    log_audit($con, $uid, 'NOTAS_ENVIO_APROBAR', "envio=$id materia=$mid alumnos=".count($notas));

    $mat = mysqli_fetch_assoc(mysqli_query($con, "SELECT nombre FROM materias WHERE id=$mid"));
    notificar_usuario($con, (int)$envio['docente_usuario_id'], 'notas_envio', 'Notas aprobadas', "Tus notas de \"".($mat['nombre'] ?? '')."\" fueron aprobadas y ya están visibles para tus alumnos.", $mid);
    notificar_materia($con, $mid, 'notas_envio', 'Ya salieron tus notas', "Tu calificación final de \"".($mat['nombre'] ?? '')."\" ya está disponible en Calificaciones.", null);

    echo json_encode(['ok'=>true,'msg'=>'Notas aprobadas y publicadas.']); exit;
}

if ($action === 'rechazar') {
    if (!in_array($rol, ['superadmin','admin'])) json_fail_ne('Sin permiso.');
    $id = (int)($_POST['id'] ?? 0);
    $comentario = mb_substr(trim($_POST['comentario'] ?? ''), 0, 500);
    if ($comentario === '') json_fail_ne('Explicá por qué rechazás este envío, para que el profesor sepa qué corregir.');

    $envio = mysqli_fetch_assoc(mysqli_query($con, "SELECT * FROM notas_envios WHERE id=$id LIMIT 1"));
    if (!$envio) json_fail_ne('Envío no encontrado.');
    if ($envio['estado'] !== 'pendiente') json_fail_ne('Este envío ya fue revisado.');

    $stU = mysqli_prepare($con, "UPDATE notas_envios SET estado='rechazado',comentario_admin=?,revisado_por=?,revisado_en=NOW() WHERE id=?");
    mysqli_stmt_bind_param($stU, 'sii', $comentario, $uid, $id);
    mysqli_stmt_execute($stU);
    log_audit($con, $uid, 'NOTAS_ENVIO_RECHAZAR', "envio=$id materia={$envio['materia_id']}");

    $mat = mysqli_fetch_assoc(mysqli_query($con, "SELECT nombre FROM materias WHERE id=".(int)$envio['materia_id']));
    notificar_usuario($con, (int)$envio['docente_usuario_id'], 'notas_envio', 'Notas rechazadas', "Tu envío de notas de \"".($mat['nombre'] ?? '')."\" fue rechazado: $comentario", (int)$envio['materia_id']);

    echo json_encode(['ok'=>true,'msg'=>'Envío rechazado. Se notificó al profesor.']); exit;
}

echo json_encode(['ok'=>false,'msg'=>'Acción no reconocida.']); exit;
