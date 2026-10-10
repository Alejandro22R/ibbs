<?php
/**
 * IBBS — Carga masiva de notas históricas desde un archivo CSV
 * (api/notas_importar.php).
 *
 * Pensado para cuando el instituto pasa años de notas en papel/Excel al
 * sistema: el admin descarga una plantilla (acción "plantilla"), la
 * llena con cedula/materia/periodo/nota, la sube acá. "preview" la lee
 * SIN escribir nada y devuelve, fila por fila, qué pasaría (ok/ya
 * existe/error) para que el admin revise antes de confirmar.
 * "confirmar" vuelve a leer el mismo archivo y esta vez sí escribe:
 * crea la materia histórica si hace falta (una por nombre+período,
 * reusada si varias filas la comparten), inscribe al alumno si no
 * estaba, y guarda la nota — mismo upsert que nota_guardar en
 * api/ajax.php.
 *
 * El alumno (por cédula) y el período (por nombre) tienen que existir
 * YA en el sistema — no se crean solos acá, para no generar alumnos o
 * períodos por un typo en el Excel. La materia histórica sí se crea
 * sola, porque es justamente lo que se está digitalizando.
 */

ob_start(); error_reporting(0);
require_once __DIR__.'/../config/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
ob_clean();

if (empty($_SESSION['loggedin'])) { echo json_encode(['ok'=>false,'msg'=>'Sesión expirada.']); exit; }

$uid = (int)($_SESSION['user_id'] ?? 0);
$rol = $_SESSION['rol'] ?? 'alumno';
$action = trim($_POST['action'] ?? $_GET['action'] ?? '');

if (!in_array($rol, ['superadmin','admin'])) { echo json_encode(['ok'=>false,'msg'=>'Sin permiso.']); exit; }

function esc_ni($c,$v){ return mysqli_real_escape_string($c,$v); }

// ── Plantilla descargable ───────────────────────────────────────
if ($action === 'plantilla') {
    ob_end_clean();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="plantilla_notas_historicas.csv"');
    echo "\xEF\xBB\xBF"; // BOM para que Excel abra los acentos bien
    $out = fopen('php://output', 'w');
    fputcsv($out, ['cedula', 'materia', 'periodo', 'nota']);
    fputcsv($out, ['12345678', 'Introducción Bíblica', '2023 - I Semestre', '17']);
    fputcsv($out, ['12345678', 'Hermenéutica', '2023 - I Semestre', '15.5']);
    fclose($out);
    exit;
}

if (!csrf_verify($_POST['csrf_token'] ?? '')) { echo json_encode(['ok'=>false,'msg'=>'Token de seguridad inválido. Recarga la página e intenta de nuevo.']); exit; }

$con = db();
if (!$con) { echo json_encode(['ok'=>false,'msg'=>'Error de conexión a la base de datos.']); exit; }
mysqli_query($con, "ALTER TABLE materias ADD COLUMN IF NOT EXISTS periodo_id INT DEFAULT NULL");
mysqli_query($con, "ALTER TABLE materia_alumno ADD COLUMN IF NOT EXISTS origen ENUM('cursado','historico') DEFAULT 'cursado'");

function parse_csv_rows($con) {
    if (empty($_FILES['csvfile']['tmp_name']) || !is_uploaded_file($_FILES['csvfile']['tmp_name'])) {
        return ['error' => 'Subí un archivo .csv primero.'];
    }
    $fh = fopen($_FILES['csvfile']['tmp_name'], 'r');
    if (!$fh) return ['error' => 'No se pudo leer el archivo.'];
    // Quitar BOM si lo trae (típico al exportar desde Excel)
    $bom = fread($fh, 3);
    if ($bom !== "\xEF\xBB\xBF") rewind($fh);
    $header = fgetcsv($fh);
    if (!$header) { fclose($fh); return ['error' => 'El archivo está vacío.']; }
    $header = array_map(fn($h) => strtolower(trim($h)), $header);
    $idx = array_flip($header);
    foreach (['cedula','materia','periodo','nota'] as $col) {
        if (!isset($idx[$col])) { fclose($fh); return ['error' => "Falta la columna '$col' en el archivo. Usá la plantilla."]; }
    }
    $rows = [];
    $n = 1;
    while (($r = fgetcsv($fh)) !== false) {
        $n++;
        if (count(array_filter($r, fn($v) => trim((string)$v) !== '')) === 0) continue; // fila vacía
        $rows[] = [
            'fila'    => $n,
            'cedula'  => trim($r[$idx['cedula']] ?? ''),
            'materia' => trim($r[$idx['materia']] ?? ''),
            'periodo' => trim($r[$idx['periodo']] ?? ''),
            'nota'    => trim(str_replace(',', '.', $r[$idx['nota']] ?? '')),
        ];
    }
    fclose($fh);
    return ['rows' => $rows];
}

// Resuelve una fila contra la base de datos: alumno por cédula, período
// por nombre, materia por (nombre normalizado + período). No escribe
// nada — $crear decide si además CREA la materia histórica que falte
// (modo confirmar) o solo informa que haría falta crearla (preview).
// $cacheMaterias evita crear la misma materia dos veces dentro de la
// misma carga cuando varias filas la comparten.
function resolver_fila($con, $uid, $fila, $crear, &$cacheMaterias) {
    $out = $fila;
    $out['estado'] = 'ok';
    $out['mensaje'] = '';

    if ($fila['cedula'] === '' || $fila['materia'] === '' || $fila['periodo'] === '' || $fila['nota'] === '') {
        $out['estado'] = 'error'; $out['mensaje'] = 'Faltan datos en la fila.'; return $out;
    }
    $cal = is_numeric($fila['nota']) ? (float)$fila['nota'] : null;
    if ($cal === null || $cal < 0 || $cal > 20) {
        $out['estado'] = 'error'; $out['mensaje'] = "Nota inválida: '{$fila['nota']}' (debe ser 0-20)."; return $out;
    }

    $al = mysqli_fetch_assoc(mysqli_query($con, "SELECT id,nombre,apellido FROM alumnos WHERE cedula='".esc_ni($con,$fila['cedula'])."' LIMIT 1"));
    if (!$al) { $out['estado'] = 'error'; $out['mensaje'] = 'Cédula no encontrada — creá primero al alumno en Alumnos.'; return $out; }
    $out['alumno_nombre'] = $al['apellido'].', '.$al['nombre'];
    $out['alumno_id'] = (int)$al['id'];

    $per = mysqli_fetch_assoc(mysqli_query($con, "SELECT id,nombre FROM periodos WHERE LOWER(nombre)=LOWER('".esc_ni($con,$fila['periodo'])."') LIMIT 1"));
    if (!$per) { $out['estado'] = 'error'; $out['mensaje'] = "Período '{$fila['periodo']}' no existe — creálo primero en Materias › Períodos."; return $out; }
    $out['periodo_id'] = (int)$per['id'];

    $cacheKey = strtolower(trim($fila['materia'])).'|'.$per['id'];
    if (isset($cacheMaterias[$cacheKey])) {
        $out['materia_id'] = $cacheMaterias[$cacheKey];
        $out['materia_nueva'] = false;
    } else {
        $mat = mysqli_fetch_assoc(mysqli_query($con, "SELECT id FROM materias WHERE LOWER(nombre)=LOWER('".esc_ni($con,$fila['materia'])."') AND periodo_id=".(int)$per['id']." LIMIT 1"));
        if ($mat) {
            $out['materia_id'] = (int)$mat['id'];
            $out['materia_nueva'] = false;
            $cacheMaterias[$cacheKey] = $out['materia_id'];
        } else if ($crear) {
            // "codigo" es varchar(20) — el prefijo + 8 hex del hash
            // entra siempre holgado, a diferencia de la versión anterior
            // (nombre + hash) que podía pasarse de 20 caracteres y hacer
            // fallar el INSERT en silencio (sql_mode STRICT_TRANS_TABLES
            // lo rechaza entero, no lo trunca).
            $codigo = 'H'.$per['id'].'-'.substr(md5($cacheKey),0,8);
            $st = mysqli_prepare($con, "INSERT INTO materias(nombre,codigo,descripcion,estado,activo,periodo_id) VALUES(?,?,?,'culminada',1,?)");
            $desc = 'Materia creada automáticamente al importar notas históricas.';
            mysqli_stmt_bind_param($st, 'sssi', $fila['materia'], $codigo, $desc, $per['id']);
            if (!mysqli_stmt_execute($st)) {
                $out['estado'] = 'error';
                $out['mensaje'] = 'No se pudo crear la materia histórica: '.mysqli_stmt_error($st);
                return $out;
            }
            $out['materia_id'] = mysqli_insert_id($con);
            $out['materia_nueva'] = true;
            $cacheMaterias[$cacheKey] = $out['materia_id'];
        } else {
            $out['materia_id'] = null;
            $out['materia_nueva'] = true; // se crearía al confirmar
        }
    }

    if ($crear && $out['materia_id']) {
        $ex = mysqli_fetch_assoc(mysqli_query($con, "SELECT id FROM materia_alumno WHERE materia_id={$out['materia_id']} AND alumno_id={$out['alumno_id']} LIMIT 1"));
        if ($ex) {
            $res = mysqli_query($con, "UPDATE materia_alumno SET nota_final=$cal,nota_fecha=CURDATE(),nota_registrada_por=$uid,nota_actualizada_en=NOW(),origen='historico' WHERE id=".$ex['id']);
            $out['mensaje'] = 'Nota actualizada.';
        } else {
            $res = mysqli_query($con, "INSERT INTO materia_alumno(materia_id,alumno_id,nota_final,nota_fecha,nota_registrada_por,nota_actualizada_en,origen) VALUES({$out['materia_id']},{$out['alumno_id']},$cal,CURDATE(),$uid,NOW(),'historico')");
            $out['mensaje'] = 'Alumno inscrito y nota cargada.';
        }
        if (!$res) {
            $out['estado'] = 'error';
            $out['mensaje'] = 'No se pudo guardar la nota: '.mysqli_error($con);
        }
    } else if (!$crear) {
        $out['mensaje'] = $out['materia_nueva'] ? 'Se creará la materia histórica y se cargará la nota.' : 'Se cargará la nota.';
    }

    return $out;
}

if ($action === 'preview' || $action === 'confirmar') {
    // La vista previa es de solo lectura (no escribe nada) y queda
    // disponible para admin y superadmin. Confirmar sí escribe notas
    // históricas, así que queda reservado al superadmin — mismo
    // criterio que nota_guardar_lote/materia_inscribir_historico en
    // api/ajax.php.
    if ($action === 'confirmar' && $rol !== 'superadmin') {
        echo json_encode(['ok'=>false,'msg'=>'Solo el superadmin puede confirmar la importación.']); exit;
    }
    $parsed = parse_csv_rows($con);
    if (isset($parsed['error'])) { echo json_encode(['ok'=>false,'msg'=>$parsed['error']]); exit; }
    $crear = ($action === 'confirmar');
    $cacheMaterias = [];
    $resultado = [];
    $okCount = 0; $errCount = 0;
    foreach ($parsed['rows'] as $fila) {
        $r = resolver_fila($con, $uid, $fila, $crear, $cacheMaterias);
        if ($r['estado'] === 'ok') $okCount++; else $errCount++;
        $resultado[] = $r;
    }
    if ($crear) {
        log_audit($con, $uid, 'NOTAS_IMPORTAR_CSV', "filas_ok=$okCount filas_error=$errCount");
    }
    echo json_encode(['ok'=>true, 'data'=>$resultado, 'resumen'=>['ok'=>$okCount,'error'=>$errCount,'total'=>count($resultado)]]);
    exit;
}

if ($action === 'matriz_confirmar') {
    // Importación masiva desde la "sábana" (matriz Excel: alumnos en
    // filas, materias en columnas, tal como ya la llevan en papel/
    // Excel) — a diferencia de "confirmar" (CSV, una fila por nota),
    // acá el navegador ya leyó el .xlsx con SheetJS y emparejó cada
    // fila de la planilla con un alumno existente (ver modulo_notas.php
    // > Importar desde Excel/CSV > Sábana Excel); esta acción solo
    // recibe {alumno_id, materia_nombre, nota} ya resueltos y hace el
    // mismo upsert histórico que nota_guardar_lote/resolver_fila,
    // reusando el mismo criterio de dedupe de materia por
    // (nombre+período). Solo superadmin, igual que "confirmar".
    if ($rol !== 'superadmin') { echo json_encode(['ok'=>false,'msg'=>'Solo el superadmin puede confirmar la importación.']); exit; }
    mysqli_query($con, "ALTER TABLE materia_alumno ADD COLUMN IF NOT EXISTS origen ENUM('cursado','historico') DEFAULT 'cursado'");

    $periodoId = (int)($_POST['periodo_id'] ?? 0);
    $per = $periodoId ? mysqli_fetch_assoc(mysqli_query($con, "SELECT id FROM periodos WHERE id=$periodoId LIMIT 1")) : null;
    if (!$per) { echo json_encode(['ok'=>false,'msg'=>'Período inválido.']); exit; }

    $filas = json_decode($_POST['filas'] ?? '[]', true);
    if (!is_array($filas) || !count($filas)) { echo json_encode(['ok'=>false,'msg'=>'Nada que importar.']); exit; }

    $cacheMaterias = [];
    $okCount = 0; $errCount = 0; $resultado = [];
    foreach ($filas as $fila) {
        $aid = (int)($fila['alumno_id'] ?? 0);
        $materiaNombre = trim((string)($fila['materia_nombre'] ?? ''));
        $notaRaw = str_replace(',', '.', trim((string)($fila['nota'] ?? '')));
        $out = ['alumno_id'=>$aid, 'materia_nombre'=>$materiaNombre, 'estado'=>'ok', 'mensaje'=>''];

        if (!$aid || $materiaNombre === '' || $notaRaw === '') {
            $out['estado']='error'; $out['mensaje']='Fila incompleta.'; $resultado[]=$out; $errCount++; continue;
        }
        $al = mysqli_fetch_assoc(mysqli_query($con, "SELECT id FROM alumnos WHERE id=$aid LIMIT 1"));
        if (!$al) {
            $out['estado']='error'; $out['mensaje']='Alumno no encontrado.'; $resultado[]=$out; $errCount++; continue;
        }
        $cal = is_numeric($notaRaw) ? (float)$notaRaw : null;
        if ($cal === null || $cal < 0 || $cal > 20) {
            $out['estado']='error'; $out['mensaje']="Nota inválida: '$notaRaw' (debe ser 0-20)."; $resultado[]=$out; $errCount++; continue;
        }

        $cacheKey = strtolower($materiaNombre).'|'.$periodoId;
        if (isset($cacheMaterias[$cacheKey])) {
            $mid = $cacheMaterias[$cacheKey];
        } else {
            $mat = mysqli_fetch_assoc(mysqli_query($con, "SELECT id FROM materias WHERE LOWER(nombre)=LOWER('".esc_ni($con,$materiaNombre)."') AND periodo_id=$periodoId LIMIT 1"));
            if ($mat) {
                $mid = (int)$mat['id'];
            } else {
                $codigo = 'H'.$periodoId.'-'.substr(md5($cacheKey),0,8);
                $st = mysqli_prepare($con, "INSERT INTO materias(nombre,codigo,descripcion,estado,activo,periodo_id) VALUES(?,?,?,'culminada',1,?)");
                $desc = 'Materia creada automáticamente al importar la sábana de notas históricas.';
                mysqli_stmt_bind_param($st, 'sssi', $materiaNombre, $codigo, $desc, $periodoId);
                if (!mysqli_stmt_execute($st)) {
                    $out['estado']='error'; $out['mensaje']='No se pudo crear la materia: '.mysqli_stmt_error($st);
                    $resultado[]=$out; $errCount++; continue;
                }
                $mid = mysqli_insert_id($con);
            }
            $cacheMaterias[$cacheKey] = $mid;
        }

        $ex = mysqli_fetch_assoc(mysqli_query($con, "SELECT id FROM materia_alumno WHERE materia_id=$mid AND alumno_id=$aid LIMIT 1"));
        if ($ex) {
            $res = mysqli_query($con, "UPDATE materia_alumno SET nota_final=$cal,nota_fecha=CURDATE(),nota_registrada_por=$uid,nota_actualizada_en=NOW(),origen='historico' WHERE id=".$ex['id']);
            $out['mensaje'] = 'Nota actualizada.';
        } else {
            $res = mysqli_query($con, "INSERT INTO materia_alumno(materia_id,alumno_id,nota_final,nota_fecha,nota_registrada_por,nota_actualizada_en,origen) VALUES($mid,$aid,$cal,CURDATE(),$uid,NOW(),'historico')");
            $out['mensaje'] = 'Alumno inscrito y nota cargada.';
        }
        if (!$res) {
            $out['estado']='error'; $out['mensaje']='No se pudo guardar la nota: '.mysqli_error($con);
            $resultado[]=$out; $errCount++; continue;
        }
        $resultado[] = $out; $okCount++;
    }

    log_audit($con, $uid, 'NOTAS_IMPORTAR_MATRIZ', "periodo=$periodoId filas_ok=$okCount filas_error=$errCount");
    echo json_encode(['ok'=>true, 'data'=>$resultado, 'resumen'=>['ok'=>$okCount,'error'=>$errCount,'total'=>count($resultado)]]);
    exit;
}

echo json_encode(['ok'=>false,'msg'=>'Acción no reconocida.']);
