<?php
/**
 * IBBS — Plan de Notas (actividades + % de cada una, por materia).
 *
 * Dos vistas sobre los mismos datos (tareas + entregas de una materia),
 * cada una con lo que le corresponde a ese rol:
 *
 * - accion=docente (profesor/admin/superadmin): la planilla completa —
 *   cada alumno, cada actividad, la nota que sacó y el promedio
 *   ponderado por los % que el profesor le puso a cada una. Es la
 *   única vista que muestra números de nota.
 *
 * - accion=alumno: la misma lista de actividades de su propia materia
 *   (fecha, título, % que vale) pero sin ninguna nota ni promedio —
 *   solo si ya entregó, falta, o si la fecha límite ya pasó. Si se le
 *   mostrara la nota o el promedio acá, el alumno podría armarse su
 *   propia constancia de notas sin pagarla en administración (ver
 *   "Constancias, Boletín y Récord Académico" en el README).
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
$accion = trim($_POST['accion'] ?? '');
$materia_id = (int)($_POST['materia_id'] ?? 0);

if (!$materia_id) { echo json_encode(['ok'=>false,'msg'=>'Falta la materia.']); exit; }

if ($accion === 'docente') {
    if (!in_array($rol, ['superadmin','admin','profesor'])) { echo json_encode(['ok'=>false,'msg'=>'Sin permiso.']); exit; }
    if (!materia_puede_gestionar($con, $uid, $rol, $materia_id)) { echo json_encode(['ok'=>false,'msg'=>'No tenés permiso sobre esta materia.']); exit; }

    $tareas = [];
    $rt = mysqli_query($con, "SELECT id, titulo, fecha_limite, fecha_limite_correccion, porcentaje, nota_maxima
                               FROM tareas WHERE materia_id=$materia_id ORDER BY fecha_limite ASC");
    while ($f = mysqli_fetch_assoc($rt)) $tareas[] = $f;

    $alumnos = [];
    $ra = mysqli_query($con, "SELECT a.id, a.nombre, a.apellido, a.cedula
                               FROM alumnos a JOIN materia_alumno ma ON ma.alumno_id=a.id
                               WHERE ma.materia_id=$materia_id ORDER BY a.apellido, a.nombre");
    while ($f = mysqli_fetch_assoc($ra)) $alumnos[] = $f;

    // notas[alumno_id][tarea_id] = {nota, observacion}
    // stats[tarea_id] = {entregados, calificados} — para que el profesor
    // vea, aunque todavía nadie haya entregado, que la actividad existe
    // y cuánto le falta por completarse (antes solo aparecía algo acá
    // una vez que había al menos una entrega calificada).
    $notas = [];
    $stats = [];
    foreach ($tareas as $t) $stats[$t['id']] = ['entregados' => 0, 'calificados' => 0];
    if ($tareas && $alumnos) {
        $rn = mysqli_query($con, "SELECT e.alumno_id, e.tarea_id, e.nota, e.observacion
                                   FROM entregas e JOIN tareas t ON t.id=e.tarea_id
                                   WHERE t.materia_id=$materia_id");
        while ($f = mysqli_fetch_assoc($rn)) {
            $notas[$f['alumno_id']][$f['tarea_id']] = ['nota' => $f['nota'], 'observacion' => $f['observacion']];
            $stats[$f['tarea_id']]['entregados']++;
            if ($f['nota'] !== null) $stats[$f['tarea_id']]['calificados']++;
        }
    }

    echo json_encode(['ok'=>true, 'data'=>['tareas'=>$tareas, 'alumnos'=>$alumnos, 'notas'=>$notas, 'stats'=>$stats]]);
    exit;
}

if ($accion === 'alumno') {
    if ($rol !== 'alumno') { echo json_encode(['ok'=>false,'msg'=>'Sin permiso.']); exit; }
    $al = mysqli_fetch_assoc(mysqli_query($con, "SELECT id FROM alumnos WHERE usuario_id=$uid LIMIT 1"));
    if (!$al) { echo json_encode(['ok'=>false,'msg'=>'No se encontró tu ficha de alumno.']); exit; }
    $alumno_id = (int)$al['id'];

    $inscrito = mysqli_fetch_assoc(mysqli_query($con, "SELECT id FROM materia_alumno WHERE materia_id=$materia_id AND alumno_id=$alumno_id LIMIT 1"));
    if (!$inscrito) { echo json_encode(['ok'=>false,'msg'=>'No estás inscrito en esta materia.']); exit; }

    $rt = mysqli_query($con, "SELECT t.id, t.titulo, t.fecha_limite, t.porcentaje, e.id as entrega_id, e.fecha_entrega
                               FROM tareas t
                               LEFT JOIN entregas e ON e.tarea_id=t.id AND e.alumno_id=$alumno_id
                               WHERE t.materia_id=$materia_id ORDER BY t.fecha_limite ASC");
    $actividades = [];
    $now = time();
    while ($f = mysqli_fetch_assoc($rt)) {
        if ($f['entrega_id']) {
            $estado = (strtotime($f['fecha_entrega']) > strtotime($f['fecha_limite'])) ? 'entregada_tarde' : 'entregada';
        } else {
            $estado = (strtotime($f['fecha_limite']) < $now) ? 'vencida' : 'pendiente';
        }
        $actividades[] = [
            'id' => $f['id'], 'titulo' => $f['titulo'], 'fecha_limite' => $f['fecha_limite'],
            'porcentaje' => $f['porcentaje'], 'estado' => $estado,
        ];
    }
    // Nunca se incluye nota ni promedio acá — ver el comentario del encabezado.
    echo json_encode(['ok'=>true, 'data'=>['actividades'=>$actividades]]);
    exit;
}

echo json_encode(['ok'=>false,'msg'=>'Acción no reconocida.']); exit;
