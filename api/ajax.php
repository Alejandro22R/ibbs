<?php
// IBBS v5 — ajax.php
ob_start(); // captura cualquier output/warning de PHP antes del JSON
error_reporting(0); // silencia notices/warnings que corromperian el JSON
session_start();
if (empty($_SESSION['loggedin'])) {
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok'=>false,'msg'=>'Sesión expirada.']); exit;
}
ob_clean(); // limpia cualquier output previo
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__.'/../config/database.php';
$con = db();
if (!$con) { echo json_encode(['ok'=>false,'msg'=>'Error de conexión a la base de datos.']); exit; }

$action = trim($_POST['action'] ?? '');
$uid    = (int)($_SESSION['user_id'] ?? 0);
$_rol   = $_SESSION['rol'] ?? 'profesor';
function esc($c,$v){ return mysqli_real_escape_string($c,$v); }
function can($perm){
    $r  = $_SESSION['rol']??'profesor';
    $mp = ['superadmin'=>['all'],'admin'=>['view_all','edit','create','delete_data','graficos','backup_export'],'profesor'=>['view_all','edit','graficos']];
    $rp = $mp[$r]??[];
    if(in_array('all',$rp)) return true;
    return in_array($perm,$rp);
}

// ── Audit log helper ────────────────────────────────────────
function log_audit($con,$uid,$accion,$detalle=''){
    static $created=false;
    if(!$created){ mysqli_query($con,"CREATE TABLE IF NOT EXISTS audit_log (id INT AUTO_INCREMENT PRIMARY KEY,usuario_id INT,accion VARCHAR(100),detalle TEXT,ip VARCHAR(45),creado_en DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB"); $created=true; }
    $ip = $_SERVER['REMOTE_ADDR']??'';
    $st=mysqli_prepare($con,"INSERT INTO audit_log(usuario_id,accion,detalle,ip) VALUES(?,?,?,?)");
    if($st){ mysqli_stmt_bind_param($st,'isss',$uid,$accion,$detalle,$ip); mysqli_stmt_execute($st); }
}


// ════ GLOBAL SEARCH ══════════════════════════════════════════
if($action==='global_search'){
    $q = trim($_POST['q']??'');
    if(strlen($q)<2){echo json_encode(['ok'=>true,'data'=>['alumnos'=>[],'docentes'=>[],'materias'=>[]]]);exit;}
    $qe = '%'.esc($con,$q).'%';
    $alumnos=$docentes=$materias=[];
    $r=mysqli_query($con,"SELECT id,nombre,apellido,cedula,ciudad FROM alumnos WHERE activo=1 AND (nombre LIKE '$qe' OR apellido LIKE '$qe' OR cedula LIKE '$qe' OR ciudad LIKE '$qe') LIMIT 8");
    while($f=mysqli_fetch_assoc($r)) $alumnos[]=$f;
    $r=mysqli_query($con,"SELECT id,nombre,apellido,cedula,especialidad FROM docentes WHERE activo=1 AND (nombre LIKE '$qe' OR apellido LIKE '$qe' OR cedula LIKE '$qe') LIMIT 6");
    while($f=mysqli_fetch_assoc($r)) $docentes[]=$f;
    $r=mysqli_query($con,"SELECT id,nombre,codigo,estado FROM materias WHERE activo=1 AND (nombre LIKE '$qe' OR codigo LIKE '$qe') LIMIT 6");
    while($f=mysqli_fetch_assoc($r)) $materias[]=$f;
    echo json_encode(['ok'=>true,'data'=>compact('alumnos','docentes','materias')]); exit;
}

// ════ PERÍODOS ════════════════════════════════════════════════
if($action==='periodo_list'){
    mysqli_query($con,"CREATE TABLE IF NOT EXISTS periodos (id INT AUTO_INCREMENT PRIMARY KEY, nombre VARCHAR(100) NOT NULL, anio YEAR NOT NULL, descripcion VARCHAR(200) DEFAULT NULL, activo TINYINT(1) DEFAULT 1, creado_en DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB");
    mysqli_query($con,"ALTER TABLE materias ADD COLUMN IF NOT EXISTS periodo_id INT DEFAULT NULL");
    $data=[];
    $r=mysqli_query($con,"SELECT * FROM periodos ORDER BY anio DESC, id DESC");
    while($f=mysqli_fetch_assoc($r)) $data[]=$f;
    echo json_encode(['ok'=>true,'data'=>$data]); exit;
}
if($action==='periodo_create'){
    if(!in_array($_rol,['superadmin','admin'])){echo json_encode(['ok'=>false,'msg'=>'Sin permiso.']);exit;}
    mysqli_query($con,"CREATE TABLE IF NOT EXISTS periodos (id INT AUTO_INCREMENT PRIMARY KEY, nombre VARCHAR(100) NOT NULL, anio YEAR NOT NULL, descripcion VARCHAR(200) DEFAULT NULL, activo TINYINT(1) DEFAULT 1, creado_en DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB");
    $nom=trim($_POST['nombre']??''); $anio=(int)($_POST['anio']??date('Y')); $desc=trim($_POST['descripcion']??'');
    if(!$nom){echo json_encode(['ok'=>false,'msg'=>'Nombre requerido.']);exit;}
    $st=mysqli_prepare($con,"INSERT INTO periodos(nombre,anio,descripcion) VALUES(?,?,?)");
    mysqli_stmt_bind_param($st,'sis',$nom,$anio,$desc); mysqli_stmt_execute($st);
    echo json_encode(['ok'=>true,'msg'=>'Período creado.']); exit;
}
if($action==='periodo_toggle'){
    if(!in_array($_rol,['superadmin','admin'])){echo json_encode(['ok'=>false,'msg'=>'Sin permiso.']);exit;}
    $id=(int)($_POST['id']??0);
    mysqli_query($con,"UPDATE periodos SET activo=1-activo WHERE id=$id");
    echo json_encode(['ok'=>true,'msg'=>'Actualizado.']); exit;
}
if($action==='periodo_delete'){
    if(!in_array($_rol,['superadmin','admin'])){echo json_encode(['ok'=>false,'msg'=>'Sin permiso.']);exit;}
    $id=(int)($_POST['id']??0);
    mysqli_query($con,"UPDATE materias SET periodo_id=NULL WHERE periodo_id=$id");
    mysqli_query($con,"DELETE FROM periodos WHERE id=$id");
    echo json_encode(['ok'=>true,'msg'=>'Período eliminado.']); exit;
}

// ════ DASHBOARD ════════════════════════════════════════════════
if($action==='dashboard_stats'){
    $al =(int)mysqli_fetch_assoc(mysqli_query($con,"SELECT COUNT(*) t FROM alumnos  WHERE activo=1"))['t'];
    $do =(int)mysqli_fetch_assoc(mysqli_query($con,"SELECT COUNT(*) t FROM docentes WHERE activo=1"))['t'];
    $ma =(int)mysqli_fetch_assoc(mysqli_query($con,"SELECT COUNT(*) t FROM materias WHERE activo=1"))['t'];
    $as =(int)mysqli_fetch_assoc(mysqli_query($con,"SELECT COUNT(*) t FROM asistencias"))['t'];
    $r=mysqli_query($con,"SELECT DATE_FORMAT(creado_en,'%Y-%m') mes,COUNT(*) cnt FROM alumnos WHERE creado_en>=DATE_SUB(NOW(),INTERVAL 6 MONTH) GROUP BY mes ORDER BY mes");
    $ca=[]; while($f=mysqli_fetch_assoc($r)) $ca[]=$f;
    $r=mysqli_query($con,"SELECT DATE_FORMAT(creado_en,'%Y-%m') mes,COUNT(*) cnt FROM docentes WHERE creado_en>=DATE_SUB(NOW(),INTERVAL 6 MONTH) GROUP BY mes ORDER BY mes");
    $cd=[]; while($f=mysqli_fetch_assoc($r)) $cd[]=$f;
    $r=mysqli_query($con,"SELECT estado,COUNT(*) cnt FROM materias GROUP BY estado");
    $me=[]; while($f=mysqli_fetch_assoc($r)) $me[$f['estado']]=(int)$f['cnt'];
    $apr=(int)mysqli_fetch_assoc(mysqli_query($con,"SELECT COUNT(*) t FROM materia_alumno WHERE nota_final IS NOT NULL AND nota_final>=15"))['t'];
    $rep=(int)mysqli_fetch_assoc(mysqli_query($con,"SELECT COUNT(*) t FROM materia_alumno WHERE nota_final IS NOT NULL AND nota_final<15"))['t'];
    $sn =(int)mysqli_fetch_assoc(mysqli_query($con,"SELECT COUNT(*) t FROM materia_alumno WHERE nota_final IS NULL"))['t'];
    echo json_encode(['ok'=>true,'data'=>['alumnos'=>$al,'docentes'=>$do,'materias'=>$ma,'asist'=>$as,
        'chart_alumnos'=>$ca,'chart_docentes'=>$cd,'mat_estado'=>$me,'aprobados'=>$apr,'reprobados'=>$rep,'sin_nota'=>$sn]]);
    exit;
}

// ════ MATERIAS ═════════════════════════════════════════════════
if($action==='materia_list'){
    $r=mysqli_query($con,"
        SELECT m.id,m.nombre,m.codigo,m.dias,m.hora_inicio,m.hora_fin,m.estado,m.activo,
               COUNT(DISTINCT md.docente_id) nd,
               COUNT(DISTINCT ma.alumno_id) na,
               SUM(CASE WHEN ma.nota_final IS NOT NULL THEN 1 ELSE 0 END) notas_cargadas
        FROM materias m
        LEFT JOIN materia_docente md ON md.materia_id=m.id
        LEFT JOIN materia_alumno  ma ON ma.materia_id=m.id
        GROUP BY m.id ORDER BY m.nombre");
    $rows=[]; while($f=mysqli_fetch_assoc($r)) $rows[]=$f;
    echo json_encode(['ok'=>true,'data'=>$rows]); exit;
}
if($action==='materia_create'){
    $n=trim($_POST['nombre']??''); $c=trim($_POST['codigo']??'');
    $d=trim($_POST['descripcion']??''); $dias=trim($_POST['dias']??'');
    $hi=trim($_POST['hora_inicio']??'')?:null; $hf=trim($_POST['hora_fin']??'')?:null;
    if(!$n||!$c){echo json_encode(['ok'=>false,'msg'=>'Nombre y código requeridos.']);exit;}
    $st=mysqli_prepare($con,"SELECT id FROM materias WHERE codigo=?");
    mysqli_stmt_bind_param($st,'s',$c); mysqli_stmt_execute($st); mysqli_stmt_store_result($st);
    if(mysqli_stmt_num_rows($st)){echo json_encode(['ok'=>false,'msg'=>'Código ya existe.']);exit;}
    mysqli_stmt_close($st);
    $st=mysqli_prepare($con,"INSERT INTO materias(nombre,codigo,descripcion,dias,hora_inicio,hora_fin) VALUES(?,?,?,?,?,?)");
    mysqli_stmt_bind_param($st,'ssssss',$n,$c,$d,$dias,$hi,$hf);
    if(mysqli_stmt_execute($st)){
        echo json_encode(['ok'=>true,'msg'=>'Materia creada.','id'=>mysqli_insert_id($con)]);
    } else {
        echo json_encode(['ok'=>false,'msg'=>mysqli_error($con)]);
    }
    exit;
}
if($action==='materia_update'){
    $id=(int)($_POST['id']??0);
    $n=trim($_POST['nombre']??''); $c=trim($_POST['codigo']??'');
    $d=trim($_POST['descripcion']??''); $dias=trim($_POST['dias']??'');
    $hi=trim($_POST['hora_inicio']??'')?:null; $hf=trim($_POST['hora_fin']??'')?:null;
    $st=mysqli_prepare($con,"UPDATE materias SET nombre=?,codigo=?,descripcion=?,dias=?,hora_inicio=?,hora_fin=? WHERE id=?");
    mysqli_stmt_bind_param($st,'ssssssi',$n,$c,$d,$dias,$hi,$hf,$id); mysqli_stmt_execute($st);
    echo json_encode(['ok'=>true,'msg'=>'Actualizada.']); exit;
}
if($action==='materia_set_estado'){
    $id=(int)($_POST['id']??0); $est=trim($_POST['estado']??'');
    $allowed=['pendiente','en_curso','culminada'];
    if(!in_array($est,$allowed)){echo json_encode(['ok'=>false,'msg'=>'Estado inválido.']);exit;}
    mysqli_query($con,"UPDATE materias SET estado='".esc($con,$est)."' WHERE id=$id");
    echo json_encode(['ok'=>true,'msg'=>'Estado actualizado.']); exit;
}
if($action==='materia_delete'){
    $id=(int)($_POST['id']??0);
    mysqli_query($con,"DELETE FROM materias WHERE id=$id");
    log_audit($con,$uid,'MATERIA_DELETE',"ID=$id");
    echo json_encode(['ok'=>true,'msg'=>'Eliminada.']); exit;
}
if($action==='materia_get'){
    $id=(int)($_POST['id']??0);
    $f=mysqli_fetch_assoc(mysqli_query($con,"SELECT * FROM materias WHERE id=$id"));
    if(!$f){echo json_encode(['ok'=>false,'msg'=>'No encontrada.']);exit;}
    $rd=mysqli_query($con,"SELECT d.id,d.nombre,d.apellido FROM docentes d JOIN materia_docente md ON md.docente_id=d.id WHERE md.materia_id=$id");
    $f['docentes']=[]; while($dd=mysqli_fetch_assoc($rd)) $f['docentes'][]=$dd;
    $ra=mysqli_query($con,"SELECT a.id,a.nombre,a.apellido,a.cedula,a.foto FROM alumnos a JOIN materia_alumno ma ON ma.alumno_id=a.id WHERE ma.materia_id=$id ORDER BY a.apellido,a.nombre");
    $f['alumnos']=[]; while($aa=mysqli_fetch_assoc($ra)) $f['alumnos'][]=$aa;
    echo json_encode(['ok'=>true,'data'=>$f]); exit;
}
if($action==='materia_add_docente'){
    $mid=(int)($_POST['materia_id']??0); $did=(int)($_POST['docente_id']??0);
    $st=mysqli_prepare($con,"SELECT id FROM materia_docente WHERE materia_id=? AND docente_id=?");
    mysqli_stmt_bind_param($st,'ii',$mid,$did); mysqli_stmt_execute($st); mysqli_stmt_store_result($st);
    if(mysqli_stmt_num_rows($st)){echo json_encode(['ok'=>false,'msg'=>'Ya está asignado.']);exit;}
    mysqli_stmt_close($st);
    // Choque horario
    $mat=mysqli_fetch_assoc(mysqli_query($con,"SELECT dias,hora_inicio,hora_fin FROM materias WHERE id=$mid"));
    if($mat['hora_inicio']&&$mat['hora_fin']&&$mat['dias']){
        $da=array_map('trim',explode(',',$mat['dias']));
        $r2=mysqli_query($con,"SELECT m2.nombre,m2.dias,m2.hora_inicio,m2.hora_fin FROM materias m2 JOIN materia_docente md2 ON md2.materia_id=m2.id WHERE md2.docente_id=$did AND m2.id!=$mid AND m2.hora_inicio IS NOT NULL");
        while($chq=mysqli_fetch_assoc($r2)){
            if(!$chq['hora_inicio']||!$chq['dias']) continue;
            $dc=array_map('trim',explode(',',$chq['dias']));
            if(!empty(array_intersect($da,$dc))&&$chq['hora_inicio']<$mat['hora_fin']&&$chq['hora_fin']>$mat['hora_inicio']){
                echo json_encode(['ok'=>false,'msg'=>"Choque de horario con: {$chq['nombre']}"]);exit;
            }
        }
    }
    $st=mysqli_prepare($con,"INSERT INTO materia_docente(materia_id,docente_id) VALUES(?,?)");
    mysqli_stmt_bind_param($st,'ii',$mid,$did); mysqli_stmt_execute($st);
    echo json_encode(['ok'=>true,'msg'=>'Docente asignado.']); exit;
}
if($action==='materia_remove_docente'){
    $mid=(int)($_POST['materia_id']??0); $did=(int)($_POST['docente_id']??0);
    mysqli_query($con,"DELETE FROM materia_docente WHERE materia_id=$mid AND docente_id=$did");
    echo json_encode(['ok'=>true,'msg'=>'Removido.']); exit;
}
if($action==='materia_add_alumno'){
    $mid=(int)($_POST['materia_id']??0); $aid=(int)($_POST['alumno_id']??0);
    $st=mysqli_prepare($con,"SELECT id FROM materia_alumno WHERE materia_id=? AND alumno_id=?");
    mysqli_stmt_bind_param($st,'ii',$mid,$aid); mysqli_stmt_execute($st); mysqli_stmt_store_result($st);
    if(mysqli_stmt_num_rows($st)){echo json_encode(['ok'=>false,'msg'=>'El alumno ya está inscrito.']);exit;}
    mysqli_stmt_close($st);
    $st=mysqli_prepare($con,"INSERT INTO materia_alumno(materia_id,alumno_id) VALUES(?,?)");
    mysqli_stmt_bind_param($st,'ii',$mid,$aid); mysqli_stmt_execute($st);
    echo json_encode(['ok'=>true,'msg'=>'Alumno inscrito correctamente.']); exit;
}
if($action==='materia_remove_alumno'){
    $mid=(int)($_POST['materia_id']??0); $aid=(int)($_POST['alumno_id']??0);
    mysqli_query($con,"DELETE FROM materia_alumno WHERE materia_id=$mid AND alumno_id=$aid");
    echo json_encode(['ok'=>true,'msg'=>'Alumno removido de la materia.']); exit;
}

// ════ NOTAS ════════════════════════════════════════════════════
if($action==='nota_guardar'){
    $mid=(int)($_POST['materia_id']??0); $aid=(int)($_POST['alumno_id']??0);
    $nota_raw=str_replace(',','.',trim($_POST['nota']??''));
    $cal=is_numeric($nota_raw)?(float)$nota_raw:-1;
    $fecha=trim($_POST['fecha']??date('Y-m-d'));
    if(!$mid){echo json_encode(['ok'=>false,'msg'=>'Falta materia_id.']);exit;}
    if(!$aid){echo json_encode(['ok'=>false,'msg'=>'Falta alumno_id.']);exit;}
    if($cal<0||$cal>20){echo json_encode(['ok'=>false,'msg'=>'Nota debe estar entre 0 y 20. Recibido: '.$nota_raw]);exit;}
    // Upsert
    $ex=mysqli_fetch_assoc(mysqli_query($con,"SELECT id FROM materia_alumno WHERE materia_id=$mid AND alumno_id=$aid LIMIT 1"));
    if($ex){
        $res=mysqli_query($con,"UPDATE materia_alumno SET nota_final=$cal,nota_fecha='".esc($con,$fecha)."',nota_registrada_por=$uid,nota_actualizada_en=NOW() WHERE materia_id=$mid AND alumno_id=$aid");
    } else {
        $res=mysqli_query($con,"INSERT INTO materia_alumno(materia_id,alumno_id,nota_final,nota_fecha,nota_registrada_por,nota_actualizada_en) VALUES($mid,$aid,$cal,'".esc($con,$fecha)."',$uid,NOW())");
    }
    if(!$res){echo json_encode(['ok'=>false,'msg'=>'BD error: '.mysqli_error($con)]);exit;}
    $estado=$cal>=15?'Aprobado':'Reprobado';
    echo json_encode(['ok'=>true,'msg'=>"Nota $cal guardada. $estado."]); exit;
}
if($action==='nota_borrar'){
    $mid=(int)($_POST['materia_id']??0); $aid=(int)($_POST['alumno_id']??0);
    mysqli_query($con,"UPDATE materia_alumno SET nota_final=NULL,nota_fecha=NULL,nota_registrada_por=NULL,nota_actualizada_en=NULL WHERE materia_id=$mid AND alumno_id=$aid");
    echo json_encode(['ok'=>true,'msg'=>'Nota borrada.']); exit;
}
if($action==='notas_tabla_materia'){
    $mid=(int)($_POST['materia_id']??0);
    if(!$mid){echo json_encode(['ok'=>false,'msg'=>'Materia requerida.']);exit;}
    $mat=mysqli_fetch_assoc(mysqli_query($con,"SELECT * FROM materias WHERE id=$mid"));
    $docs=[]; $rd=mysqli_query($con,"SELECT d.nombre,d.apellido FROM docentes d JOIN materia_docente md ON md.docente_id=d.id WHERE md.materia_id=$mid");
    while($f=mysqli_fetch_assoc($rd)) $docs[]=$f;
    $alumnos=[]; $ra=mysqli_query($con,"SELECT a.id,a.nombre,a.apellido,a.cedula,a.ciudad,ma.nota_final,ma.nota_fecha FROM alumnos a JOIN materia_alumno ma ON ma.alumno_id=a.id WHERE ma.materia_id=$mid ORDER BY a.apellido,a.nombre");
    while($f=mysqli_fetch_assoc($ra)) $alumnos[]=$f;
    $apr=0; foreach($alumnos as $_a){ if($_a['nota_final']!==null&&(float)$_a['nota_final']>=15) $apr++; }
    $rep=0; foreach($alumnos as $_a){ if($_a['nota_final']!==null&&(float)$_a['nota_final']<15) $rep++; }
    $sn=0; foreach($alumnos as $_a){ if($_a['nota_final']===null) $sn++; }
    echo json_encode(['ok'=>true,'data'=>['mat'=>$mat,'docentes'=>$docs,'alumnos'=>$alumnos,'aprobados'=>$apr,'reprobados'=>$rep,'sin'=>$sn]]);
    exit;
}

// ════ INSCRIPCIONES ════════════════════════════════════════════
if($action==='inscripcion_alumno_materias'){
    $aid=(int)($_POST['alumno_id']??0);
    if(!$aid){echo json_encode(['ok'=>false,'msg'=>'ID de alumno requerido.']);exit;}
    $al=mysqli_fetch_assoc(mysqli_query($con,"SELECT id,nombre,apellido,cedula,correo,telefono,ciudad,foto,activo FROM alumnos WHERE id=$aid LIMIT 1"));
    if(!$al){echo json_encode(['ok'=>false,'msg'=>'Alumno no encontrado.']);exit;}

    // Materias en las que YA está inscrito
    $inscritas=[];
    $ri=mysqli_query($con,"
        SELECT m.id,m.nombre,m.codigo,m.estado,ma.nota_final,ma.nota_fecha,
               GROUP_CONCAT(DISTINCT CONCAT(d.nombre,' ',d.apellido) SEPARATOR ', ') docentes
        FROM materias m
        JOIN materia_alumno ma ON ma.materia_id=m.id AND ma.alumno_id=$aid
        LEFT JOIN materia_docente md ON md.materia_id=m.id
        LEFT JOIN docentes d ON d.id=md.docente_id
        GROUP BY m.id,ma.nota_final,ma.nota_fecha
        ORDER BY m.nombre");
    while($f=mysqli_fetch_assoc($ri)) $inscritas[]=$f;

    // Materias disponibles (no inscrito aún)
    $ids_inscritas=array_column($inscritas,'id');
    $excluir=count($ids_inscritas)?implode(',',$ids_inscritas):'0';
    $disponibles=[];
    $rd=mysqli_query($con,"
        SELECT m.id,m.nombre,m.codigo,m.dias,m.hora_inicio,m.hora_fin,m.estado,
               GROUP_CONCAT(DISTINCT CONCAT(d.nombre,' ',d.apellido) SEPARATOR ', ') docentes
        FROM materias m
        LEFT JOIN materia_docente md ON md.materia_id=m.id
        LEFT JOIN docentes d ON d.id=md.docente_id
        WHERE m.activo=1 AND m.id NOT IN ($excluir)
        GROUP BY m.id ORDER BY m.nombre");
    while($f=mysqli_fetch_assoc($rd)) $disponibles[]=$f;

    echo json_encode(['ok'=>true,'data'=>['al'=>$al,'inscritas'=>$inscritas,'disponibles'=>$disponibles]]);
    exit;
}

// ════ DOCENTES ══════════════════════════════════════════════════
if($action==='docente_create'){
    $n=trim($_POST['nombre']??''); $a=trim($_POST['apellido']??''); $c=trim($_POST['cedula']??'');
    $m=trim($_POST['correo']??''); $t=trim($_POST['telefono']??''); $e=trim($_POST['especialidad']??''); $ci=trim($_POST['ciudad']??'');
    if(!$n||!$a||!$c||!$m){echo json_encode(['ok'=>false,'msg'=>'Faltan campos requeridos.']);exit;}
    $st=mysqli_prepare($con,"SELECT id FROM docentes WHERE cedula=?"); mysqli_stmt_bind_param($st,'s',$c); mysqli_stmt_execute($st); mysqli_stmt_store_result($st);
    if(mysqli_stmt_num_rows($st)){echo json_encode(['ok'=>false,'msg'=>'Cédula ya registrada.']);exit;} mysqli_stmt_close($st);
    $st=mysqli_prepare($con,"INSERT INTO docentes(nombre,apellido,cedula,correo,telefono,especialidad,ciudad) VALUES(?,?,?,?,?,?,?)");
    mysqli_stmt_bind_param($st,'sssssss',$n,$a,$c,$m,$t,$e,$ci); mysqli_stmt_execute($st);
    echo json_encode(['ok'=>true,'msg'=>'Docente registrado.','id'=>mysqli_insert_id($con)]); exit;
}
if($action==='docente_update'){
    $id=(int)($_POST['id']??0); $n=trim($_POST['nombre']??''); $a=trim($_POST['apellido']??'');
    $c=trim($_POST['cedula']??''); $m=trim($_POST['correo']??''); $t=trim($_POST['telefono']??'');
    $e=trim($_POST['especialidad']??''); $ci=trim($_POST['ciudad']??''); $ac=(int)($_POST['activo']??1);
    // Check cedula/correo not used by another docente
    $ck=mysqli_prepare($con,"SELECT id FROM docentes WHERE (cedula=? OR correo=?) AND id!=?");
    mysqli_stmt_bind_param($ck,'ssi',$c,$m,$id); mysqli_stmt_execute($ck); mysqli_stmt_store_result($ck);
    if(mysqli_stmt_num_rows($ck)>0){echo json_encode(['ok'=>false,'msg'=>'Cédula o correo ya registrado en otro docente.']);exit;}
    mysqli_stmt_close($ck);
    $st=mysqli_prepare($con,"UPDATE docentes SET nombre=?,apellido=?,cedula=?,correo=?,telefono=?,especialidad=?,ciudad=?,activo=? WHERE id=?");
    mysqli_stmt_bind_param($st,'sssssssii',$n,$a,$c,$m,$t,$e,$ci,$ac,$id); mysqli_stmt_execute($st);
    echo json_encode(['ok'=>true,'msg'=>'Actualizado.']); exit;
}
if($action==='docente_delete'){
    $id=(int)($_POST['id']??0); mysqli_query($con,"DELETE FROM docentes WHERE id=$id");
    log_audit($con,$uid,'DOCENTE_DELETE',"ID=$id");
    echo json_encode(['ok'=>true,'msg'=>'Eliminado.']); exit;
}
if($action==='docente_list'){
    $r=mysqli_query($con,"SELECT d.*,COUNT(DISTINCT md.materia_id) nm FROM docentes d LEFT JOIN materia_docente md ON md.docente_id=d.id GROUP BY d.id ORDER BY d.apellido,d.nombre");
    $rows=[]; while($f=mysqli_fetch_assoc($r)) $rows[]=$f;
    echo json_encode(['ok'=>true,'data'=>$rows]); exit;
}
if($action==='docente_get'){
    $id=(int)($_POST['id']??0);
    $f=mysqli_fetch_assoc(mysqli_query($con,"SELECT * FROM docentes WHERE id=$id"));
    if(!$f){echo json_encode(['ok'=>false,'msg'=>'No encontrado.']);exit;}
    $rm=mysqli_query($con,"SELECT m.id,m.nombre,m.codigo,m.dias,m.hora_inicio,m.hora_fin,m.estado FROM materias m JOIN materia_docente md ON md.materia_id=m.id WHERE md.docente_id=$id");
    $f['materias']=[]; while($m=mysqli_fetch_assoc($rm)) $f['materias'][]=$m;
    $ra=mysqli_query($con,"SELECT estado,COUNT(*) cnt FROM asistencias WHERE docente_id=$id AND tipo='docente' GROUP BY estado");
    $f['asistencias']=[]; while($a=mysqli_fetch_assoc($ra)) $f['asistencias'][$a['estado']]=(int)$a['cnt'];
    echo json_encode(['ok'=>true,'data'=>$f]); exit;
}
if($action==='docente_all_simple'){
    $r=mysqli_query($con,"SELECT id,nombre,apellido,cedula FROM docentes WHERE activo=1 ORDER BY apellido,nombre");
    $rows=[]; while($f=mysqli_fetch_assoc($r)) $rows[]=$f;
    echo json_encode(['ok'=>true,'data'=>$rows]); exit;
}

// ════ ALUMNOS ═══════════════════════════════════════════════════
if($action==='alumno_create'){
    $n=trim($_POST['nombre']??''); $a=trim($_POST['apellido']??''); $c=trim($_POST['cedula']??'');
    $m=trim($_POST['correo']??''); $t=trim($_POST['telefono']??''); $ci=trim($_POST['ciudad']??'');
    if(!$n||!$a||!$c||!$m){echo json_encode(['ok'=>false,'msg'=>'Faltan campos requeridos.']);exit;}
    $st=mysqli_prepare($con,"SELECT id FROM alumnos WHERE cedula=?"); mysqli_stmt_bind_param($st,'s',$c); mysqli_stmt_execute($st); mysqli_stmt_store_result($st);
    if(mysqli_stmt_num_rows($st)){echo json_encode(['ok'=>false,'msg'=>'Cédula ya registrada.']);exit;} mysqli_stmt_close($st);
    $st=mysqli_prepare($con,"INSERT INTO alumnos(nombre,apellido,cedula,correo,telefono,ciudad) VALUES(?,?,?,?,?,?)");
    mysqli_stmt_bind_param($st,'ssssss',$n,$a,$c,$m,$t,$ci); mysqli_stmt_execute($st);
    echo json_encode(['ok'=>true,'msg'=>'Alumno registrado.','id'=>mysqli_insert_id($con)]); exit;
}
if($action==='alumno_update'){
    $id=(int)($_POST['id']??0); $n=trim($_POST['nombre']??''); $a=trim($_POST['apellido']??'');
    $c=trim($_POST['cedula']??''); $m=trim($_POST['correo']??''); $t=trim($_POST['telefono']??'');
    $ci=trim($_POST['ciudad']??''); $ac=(int)($_POST['activo']??1);
    // Check cedula/correo not used by another alumno
    $ck=mysqli_prepare($con,"SELECT id FROM alumnos WHERE (cedula=? OR correo=?) AND id!=?");
    mysqli_stmt_bind_param($ck,'ssi',$c,$m,$id); mysqli_stmt_execute($ck); mysqli_stmt_store_result($ck);
    if(mysqli_stmt_num_rows($ck)>0){echo json_encode(['ok'=>false,'msg'=>'Cédula o correo ya registrado en otro alumno.']);exit;}
    mysqli_stmt_close($ck);
    $st=mysqli_prepare($con,"UPDATE alumnos SET nombre=?,apellido=?,cedula=?,correo=?,telefono=?,ciudad=?,activo=? WHERE id=?");
    mysqli_stmt_bind_param($st,'ssssssii',$n,$a,$c,$m,$t,$ci,$ac,$id); mysqli_stmt_execute($st);
    echo json_encode(['ok'=>true,'msg'=>'Alumno actualizado.']); exit;
}
if($action==='alumno_delete'){
    $id=(int)($_POST['id']??0); mysqli_query($con,"DELETE FROM alumnos WHERE id=$id");
    log_audit($con,$uid,'ALUMNO_DELETE',"ID=$id");
    echo json_encode(['ok'=>true,'msg'=>'Eliminado.']); exit;
}
if($action==='alumno_list'){
    $ciudad=trim($_POST['ciudad']??'');
    $w=$ciudad?" WHERE a.ciudad LIKE '%".esc($con,$ciudad)."%'":'';
    $r=mysqli_query($con,"SELECT a.*,COUNT(DISTINCT ma.materia_id) nm FROM alumnos a LEFT JOIN materia_alumno ma ON ma.alumno_id=a.id$w GROUP BY a.id ORDER BY a.apellido,a.nombre");
    $rows=[]; while($f=mysqli_fetch_assoc($r)) $rows[]=$f;
    echo json_encode(['ok'=>true,'data'=>$rows]); exit;
}
if($action==='alumno_get'){
    $id=(int)($_POST['id']??0);
    $f=mysqli_fetch_assoc(mysqli_query($con,"SELECT * FROM alumnos WHERE id=$id"));
    if(!$f){echo json_encode(['ok'=>false,'msg'=>'No encontrado.']);exit;}
    $rm=mysqli_query($con,"SELECT m.id,m.nombre,m.codigo,m.estado,ma.nota_final,ma.nota_fecha FROM materias m JOIN materia_alumno ma ON ma.materia_id=m.id WHERE ma.alumno_id=$id ORDER BY m.nombre");
    $f['materias']=[]; while($m=mysqli_fetch_assoc($rm)) $f['materias'][]=$m;
    $ra=mysqli_query($con,"SELECT estado,COUNT(*) cnt FROM asistencias WHERE alumno_id=$id AND tipo='alumno' GROUP BY estado");
    $f['asistencias']=[]; while($a=mysqli_fetch_assoc($ra)) $f['asistencias'][$a['estado']]=(int)$a['cnt'];
    echo json_encode(['ok'=>true,'data'=>$f]); exit;
}
if($action==='alumno_all_simple'){
    $r=mysqli_query($con,"SELECT id,nombre,apellido,cedula FROM alumnos WHERE activo=1 ORDER BY apellido,nombre");
    $rows=[]; while($f=mysqli_fetch_assoc($r)) $rows[]=$f;
    echo json_encode(['ok'=>true,'data'=>$rows]); exit;
}

// ════ RECORD ALUMNO ════════════════════════════════════════════
if($action==='record_alumno'){
    $aid=(int)($_POST['alumno_id']??0);
    $al=mysqli_fetch_assoc(mysqli_query($con,"SELECT id,nombre,apellido,cedula,correo,telefono,ciudad,foto,activo FROM alumnos WHERE id=$aid"));
    if(!$al){echo json_encode(['ok'=>false,'msg'=>'Alumno no encontrado.']);exit;}
    $rm=mysqli_query($con,"SELECT m.*,ma.nota_final,ma.nota_fecha FROM materias m JOIN materia_alumno ma ON ma.materia_id=m.id WHERE ma.alumno_id=$aid ORDER BY m.nombre");
    $mats=[];
    while($m=mysqli_fetch_assoc($rm)){
        $mid=$m['id'];
        $rd2=mysqli_query($con,"SELECT d.nombre,d.apellido FROM docentes d JOIN materia_docente md ON md.docente_id=d.id WHERE md.materia_id=$mid");
        $m['docentes']=[]; while($d=mysqli_fetch_assoc($rd2)) $m['docentes'][]=$d;
        $mats[]=$m;
    }
    $ra=mysqli_query($con,"SELECT estado,COUNT(*) cnt FROM asistencias WHERE alumno_id=$aid AND tipo='alumno' GROUP BY estado");
    $al['asistencias']=[]; while($a=mysqli_fetch_assoc($ra)) $al['asistencias'][$a['estado']]=(int)$a['cnt'];
    echo json_encode(['ok'=>true,'data'=>['alumno'=>$al,'materias'=>$mats]]); exit;
}

// ════ ASISTENCIAS ══════════════════════════════════════════════
if($action==='asistencia_register'){
    $mid=(int)($_POST['materia_id']??0); $tipo=trim($_POST['tipo']??'alumno');
    $pid=(int)($_POST['persona_id']??0); $fecha=trim($_POST['fecha']??'');
    $estado=trim($_POST['estado']??'presente'); $obs=trim($_POST['observacion']??'');
    if(!$mid||!$pid||!$fecha){echo json_encode(['ok'=>false,'msg'=>'Datos incompletos.']);exit;}
    if($tipo==='alumno'){
        $st=mysqli_prepare($con,"INSERT INTO asistencias(materia_id,alumno_id,tipo,fecha,estado,observacion,registrado_por) VALUES(?,?,'alumno',?,?,?,?)");
        mysqli_stmt_bind_param($st,'iisssi',$mid,$pid,$fecha,$estado,$obs,$uid);
    } else {
        $st=mysqli_prepare($con,"INSERT INTO asistencias(materia_id,docente_id,tipo,fecha,estado,observacion,registrado_por) VALUES(?,?,'docente',?,?,?,?)");
        mysqli_stmt_bind_param($st,'iisssi',$mid,$pid,$fecha,$estado,$obs,$uid);
    }
    if(mysqli_stmt_execute($st)){
        echo json_encode(['ok'=>true,'msg'=>'Asistencia registrada.']);
    } else {
        echo json_encode(['ok'=>false,'msg'=>mysqli_error($con)]);
    }
    exit;
}
if($action==='asistencia_list'){
    $mid=(int)($_POST['materia_id']??0); $tipo=trim($_POST['tipo']??''); $fecha=trim($_POST['fecha']??'');
    $w=[]; if($mid) $w[]="a.materia_id=$mid"; if($tipo) $w[]="a.tipo='".esc($con,$tipo)."'"; if($fecha) $w[]="a.fecha='".esc($con,$fecha)."'";
    $wq=$w?"WHERE ".implode(' AND ',$w):'';
    $r=mysqli_query($con,"SELECT a.*,m.nombre materia,COALESCE(CONCAT(al.nombre,' ',al.apellido),CONCAT(d.nombre,' ',d.apellido)) persona,COALESCE(al.cedula,d.cedula) cedula FROM asistencias a LEFT JOIN materias m ON m.id=a.materia_id LEFT JOIN alumnos al ON al.id=a.alumno_id LEFT JOIN docentes d ON d.id=a.docente_id $wq ORDER BY a.fecha DESC LIMIT 500");
    $rows=[]; while($f=mysqli_fetch_assoc($r)) $rows[]=$f;
    echo json_encode(['ok'=>true,'data'=>$rows]); exit;
}

// ════ BÚSQUEDA CÉDULA ══════════════════════════════════════════
if($action==='buscar_cedula'){
    $ced=trim($_POST['cedula']??''); if(!$ced){echo json_encode(['ok'=>false,'msg'=>'Ingresa una cédula.']);exit;}
    $ce=esc($con,$ced);
    $f=mysqli_fetch_assoc(mysqli_query($con,"SELECT * FROM alumnos WHERE cedula='$ce' LIMIT 1"));
    if($f){
        $aid=$f['id'];
        $rm=mysqli_query($con,"SELECT m.nombre,m.codigo,m.estado,ma.nota_final,ma.nota_fecha FROM materias m JOIN materia_alumno ma ON ma.materia_id=m.id WHERE ma.alumno_id=$aid ORDER BY m.nombre");
        $f['materias']=[]; while($m=mysqli_fetch_assoc($rm)) $f['materias'][]=$m;
        $ra=mysqli_query($con,"SELECT estado,COUNT(*) cnt FROM asistencias WHERE alumno_id=$aid AND tipo='alumno' GROUP BY estado");
        $f['asistencias']=[]; while($a=mysqli_fetch_assoc($ra)) $f['asistencias'][$a['estado']]=(int)$a['cnt'];
        echo json_encode(['ok'=>true,'tipo'=>'alumno','data'=>$f]); exit;
    }
    $f=mysqli_fetch_assoc(mysqli_query($con,"SELECT * FROM docentes WHERE cedula='$ce' LIMIT 1"));
    if($f){
        $did=$f['id'];
        $rm=mysqli_query($con,"SELECT m.nombre,m.codigo,m.dias,m.hora_inicio,m.hora_fin,m.estado FROM materias m JOIN materia_docente md ON md.materia_id=m.id WHERE md.docente_id=$did ORDER BY m.nombre");
        $f['materias']=[]; while($m=mysqli_fetch_assoc($rm)) $f['materias'][]=$m;
        $ra=mysqli_query($con,"SELECT estado,COUNT(*) cnt FROM asistencias WHERE docente_id=$did AND tipo='docente' GROUP BY estado");
        $f['asistencias']=[]; while($a=mysqli_fetch_assoc($ra)) $f['asistencias'][$a['estado']]=(int)$a['cnt'];
        echo json_encode(['ok'=>true,'tipo'=>'docente','data'=>$f]); exit;
    }
    echo json_encode(['ok'=>false,'msg'=>"Sin resultados para: $ced"]); exit;
}

// ════ USUARIOS ══════════════════════════════════════════════════
if($action==='usuario_list'){
    $r=mysqli_query($con,"SELECT id,usuario,correo,rol,activo,creado_en FROM usuarios ORDER BY creado_en DESC");
    $rows=[]; while($f=mysqli_fetch_assoc($r)) $rows[]=$f;
    echo json_encode(['ok'=>true,'data'=>$rows]); exit;
}
if($action==='usuario_create'){
    $usr=trim($_POST['usuario']??''); $mail=trim($_POST['correo']??'');
    $ced=trim($_POST['cedula']??'');
    $pwd=trim($_POST['password']??''); $rol=trim($_POST['rol']??'profesor');
    if(!$usr||!$mail||!$ced||!$pwd){echo json_encode(['ok'=>false,'msg'=>'Todos los campos son requeridos (incluyendo cédula).']);exit;}
    if(strlen($pwd)<6){echo json_encode(['ok'=>false,'msg'=>'La contraseña debe tener al menos 6 caracteres.']);exit;}
    if(!in_array($rol,['admin','profesor','superadmin'])) $rol='profesor';
    // Check duplicates: usuario, correo AND cedula
    $st=mysqli_prepare($con,"SELECT id FROM usuarios WHERE usuario=? OR correo=? OR cedula=?");
    mysqli_stmt_bind_param($st,'sss',$usr,$mail,$ced);
    mysqli_stmt_execute($st); mysqli_stmt_store_result($st);
    if(mysqli_stmt_num_rows($st)>0){echo json_encode(['ok'=>false,'msg'=>'Ya existe un usuario con ese nombre de usuario, correo o cédula.']);exit;}
    mysqli_stmt_close($st);
    $hash=password_hash($pwd,PASSWORD_BCRYPT); $ac=1;
    $st=mysqli_prepare($con,"INSERT INTO usuarios(usuario,correo,cedula,password_hash,rol,activo) VALUES(?,?,?,?,?,?)");
    mysqli_stmt_bind_param($st,'sssssi',$usr,$mail,$ced,$hash,$rol,$ac); mysqli_stmt_execute($st);
    echo json_encode(['ok'=>true,'msg'=>'Usuario creado.','id'=>mysqli_insert_id($con)]); exit;
}
if($action==='usuario_update'){
    $id=(int)($_POST['id']??0); $usr=trim($_POST['usuario']??''); $mail=trim($_POST['correo']??'');
    $rol=trim($_POST['rol']??'profesor'); $ac=(int)($_POST['activo']??1);
    $allowed_roles = $_rol==='superadmin' ? ['superadmin','admin','profesor'] : ['admin','profesor'];
    if(!in_array($rol,$allowed_roles)) $rol='profesor';
    // Check duplicates excluding current user
    $st=mysqli_prepare($con,"SELECT id FROM usuarios WHERE (usuario=? OR correo=?) AND id!=?");
    mysqli_stmt_bind_param($st,'ssi',$usr,$mail,$id); mysqli_stmt_execute($st); mysqli_stmt_store_result($st);
    if(mysqli_stmt_num_rows($st)>0){echo json_encode(['ok'=>false,'msg'=>'Ese usuario o correo ya está en uso por otra cuenta.']);exit;}
    mysqli_stmt_close($st);
    $st=mysqli_prepare($con,"UPDATE usuarios SET usuario=?,correo=?,rol=?,activo=? WHERE id=?");
    mysqli_stmt_bind_param($st,'sssii',$usr,$mail,$rol,$ac,$id); mysqli_stmt_execute($st);
    echo json_encode(['ok'=>true,'msg'=>'Usuario actualizado.']); exit;
}
if($action==='usuario_toggle'){
    $id=(int)($_POST['id']??0);
    if($id===$uid){echo json_encode(['ok'=>false,'msg'=>'No puedes desactivar tu propia cuenta.']);exit;}
    $cur=(int)mysqli_fetch_assoc(mysqli_query($con,"SELECT activo FROM usuarios WHERE id=$id"))['activo'];
    $nuevo=$cur?0:1;
    mysqli_query($con,"UPDATE usuarios SET activo=$nuevo WHERE id=$id");
    echo json_encode(['ok'=>true,'msg'=>$nuevo?'Activado.':'Desactivado.','activo'=>$nuevo]); exit;
}
if($action==='usuario_reset_pwd'){
    $id=(int)($_POST['id']??0); $pwd=trim($_POST['password']??'');
    if(strlen($pwd)<6){echo json_encode(['ok'=>false,'msg'=>'Mínimo 6 caracteres.']);exit;}
    $hash=password_hash($pwd,PASSWORD_BCRYPT);
    $st=mysqli_prepare($con,"UPDATE usuarios SET password_hash=? WHERE id=?");
    mysqli_stmt_bind_param($st,'si',$hash,$id); mysqli_stmt_execute($st);
    echo json_encode(['ok'=>true,'msg'=>'Contraseña actualizada.']); exit;
}
if($action==='usuario_delete'){
    $id=(int)($_POST['id']??0);
    if($id===$uid){echo json_encode(['ok'=>false,'msg'=>'No puedes eliminar tu propia cuenta.']);exit;}
    mysqli_query($con,"DELETE FROM usuarios WHERE id=$id");
    log_audit($con,$uid,'USUARIO_DELETE',"ID=$id");
    echo json_encode(['ok'=>true,'msg'=>'Usuario eliminado.']); exit;
}

// Test de versión (para verificar que es el archivo correcto)
if($action==='version_test'){
    echo json_encode(['ok'=>true,'version'=>'IBBS-v5-FINAL','php'=>phpversion(),'session'=>!empty($_SESSION['loggedin']),'uid'=>$uid]);
    exit;
}




// ════ NOTIFICACIONES ════════════════════════════════════════
if($action==='notif_list'){
    // Auto-create table if not exists (safe for users who haven't run the ALTER)
    mysqli_query($con,"CREATE TABLE IF NOT EXISTS notificaciones (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tipo ENUM('reprobado','asistencia','sistema','info') DEFAULT 'info',
        titulo VARCHAR(200) NOT NULL,
        mensaje TEXT,
        para_rol VARCHAR(20) DEFAULT 'admin',
        usuario_id INT DEFAULT NULL,
        leida TINYINT(1) DEFAULT 0,
        creado_en DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");
    $lista=[];
    $r=mysqli_query($con,"SELECT * FROM notificaciones WHERE (usuario_id=$uid OR usuario_id IS NULL) AND leida=0 ORDER BY creado_en DESC LIMIT 30");
    if($r) while($f=mysqli_fetch_assoc($r)) $lista[]=$f;
    echo json_encode(['ok'=>true,'data'=>$lista,'count'=>count($lista)]); exit;
}
if($action==='notif_generar'){
    if(!can('edit')){echo json_encode(['ok'=>false,'msg'=>'Sin permiso.']);exit;}
    // Ensure table exists
    mysqli_query($con,"CREATE TABLE IF NOT EXISTS notificaciones (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tipo ENUM('reprobado','asistencia','sistema','info') DEFAULT 'info',
        titulo VARCHAR(200) NOT NULL, mensaje TEXT,
        para_rol VARCHAR(20) DEFAULT 'admin', usuario_id INT DEFAULT NULL,
        leida TINYINT(1) DEFAULT 0, creado_en DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");
    $new=0;
    // 1. Alumnos reprobados (nota_final < 15) no notificados aún
    $r=mysqli_query($con,"SELECT a.nombre,a.apellido,m.nombre mn,ma.nota_final,ma.alumno_id,ma.materia_id
        FROM materia_alumno ma
        JOIN alumnos a ON a.id=ma.alumno_id
        JOIN materias m ON m.id=ma.materia_id
        WHERE ma.nota_final IS NOT NULL AND ma.nota_final < 15");
    while($row=mysqli_fetch_assoc($r)){
        $key="rep_{$row['alumno_id']}_{$row['materia_id']}";
        $ex=mysqli_fetch_assoc(mysqli_query($con,"SELECT id FROM notificaciones WHERE tipo='reprobado' AND titulo LIKE '%{$row['alumno_id']}%{$row['materia_id']}%' LIMIT 1"));
        if(!$ex){
            $titulo="Alumno reprobado • ID:{$row['alumno_id']}•{$row['materia_id']}";
            $msg="<strong>{$row['nombre']} {$row['apellido']}</strong> reprobó <strong>{$row['mn']}</strong> con nota <strong>{$row['nota_final']}</strong>.";
            $st=mysqli_prepare($con,"INSERT INTO notificaciones(tipo,titulo,mensaje,para_rol) VALUES('reprobado',?,?,'admin')");
            mysqli_stmt_bind_param($st,'ss',$titulo,$msg); mysqli_stmt_execute($st); $new++;
        }
    }
    // 2. Alumnos con >25% ausencias
    $r=mysqli_query($con,"SELECT alumno_id,materia_id,
        SUM(CASE WHEN estado='ausente' THEN 1 ELSE 0 END) aus,
        COUNT(*) tot FROM asistencias WHERE tipo='alumno' GROUP BY alumno_id,materia_id
        HAVING aus/tot > 0.25");
    while($row=mysqli_fetch_assoc($r)){
        $a=mysqli_fetch_assoc(mysqli_query($con,"SELECT nombre,apellido FROM alumnos WHERE id={$row['alumno_id']}"));
        $m=mysqli_fetch_assoc(mysqli_query($con,"SELECT nombre FROM materias WHERE id={$row['materia_id']}"));
        if(!$a||!$m) continue;
        $pct=round($row['aus']/$row['tot']*100);
        $ex=mysqli_fetch_assoc(mysqli_query($con,"SELECT id FROM notificaciones WHERE tipo='asistencia' AND titulo LIKE '%{$row['alumno_id']}%{$row['materia_id']}%' LIMIT 1"));
        if(!$ex){
            $titulo="Ausencias críticas • ID:{$row['alumno_id']}•{$row['materia_id']}";
            $msg="<strong>{$a['nombre']} {$a['apellido']}</strong> tiene <strong>{$pct}% de ausencias</strong> en <strong>{$m['nombre']}</strong>.";
            $st=mysqli_prepare($con,"INSERT INTO notificaciones(tipo,titulo,mensaje,para_rol) VALUES('asistencia',?,?,'admin')");
            mysqli_stmt_bind_param($st,'ss',$titulo,$msg); mysqli_stmt_execute($st); $new++;
        }
    }
    echo json_encode(['ok'=>true,'msg'=>"$new nuevas alertas generadas.",'new'=>$new]); exit;
}
if($action==='notif_leer'){
    mysqli_query($con,"CREATE TABLE IF NOT EXISTS notificaciones (id INT AUTO_INCREMENT PRIMARY KEY,tipo ENUM('reprobado','asistencia','sistema','info') DEFAULT 'info',titulo VARCHAR(200) NOT NULL,mensaje TEXT,para_rol VARCHAR(20) DEFAULT 'admin',usuario_id INT DEFAULT NULL,leida TINYINT(1) DEFAULT 0,creado_en DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB");
    $id=(int)($_POST['id']??0);
    if($id) mysqli_query($con,"UPDATE notificaciones SET leida=1 WHERE id=$id AND (usuario_id=$uid OR usuario_id IS NULL)");
    else    mysqli_query($con,"UPDATE notificaciones SET leida=1 WHERE (usuario_id=$uid OR usuario_id IS NULL)");
    echo json_encode(['ok'=>true]); exit;
}
if($action==='notif_borrar'){
    mysqli_query($con,"CREATE TABLE IF NOT EXISTS notificaciones (id INT AUTO_INCREMENT PRIMARY KEY,tipo ENUM('reprobado','asistencia','sistema','info') DEFAULT 'info',titulo VARCHAR(200) NOT NULL,mensaje TEXT,para_rol VARCHAR(20) DEFAULT 'admin',usuario_id INT DEFAULT NULL,leida TINYINT(1) DEFAULT 0,creado_en DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB");
    $id=(int)($_POST['id']??0);
    if(!can('edit')){echo json_encode(['ok'=>false,'msg'=>'Sin permiso.']);exit;}
    if($id) mysqli_query($con,"DELETE FROM notificaciones WHERE id=$id");
    echo json_encode(['ok'=>true]); exit;
}

// ════ CALENDARIO ════════════════════════════════════════════
if($action==='calendario_mes'){
    $y=(int)($_POST['year']??date('Y'));
    $m=(int)($_POST['month']??date('n'));
    // Get all active materias with days/times
    $r=mysqli_query($con,"SELECT m.id,m.nombre,m.codigo,m.dias,m.hora_inicio,m.hora_fin,m.estado,
        GROUP_CONCAT(CONCAT(d.nombre,' ',d.apellido) SEPARATOR ', ') docentes
        FROM materias m
        LEFT JOIN materia_docente md ON md.materia_id=m.id
        LEFT JOIN docentes d ON d.id=md.docente_id
        WHERE m.activo=1 AND m.estado IN ('en_curso','pendiente')
        GROUP BY m.id");
    $materias=[];
    while($f=mysqli_fetch_assoc($r)) $materias[]=$f;
    // Build events per day of month
    $days_in_month=cal_days_in_month(CAL_GREGORIAN,$m,$y);
    $events=[];
    $day_map=['lunes'=>1,'martes'=>2,'miércoles'=>3,'miercoles'=>3,'jueves'=>4,'viernes'=>5,'sábado'=>6,'sabado'=>6,'domingo'=>0];
    foreach($materias as $mat){
        if(!$mat['dias']) continue;
        $dias=array_map('strtolower',array_map('trim',explode(',',$mat['dias'])));
        for($d=1;$d<=$days_in_month;$d++){
            $date=sprintf('%04d-%02d-%02d',$y,$m,$d);
            $dow=date('N',strtotime($date)); // 1=Mon...7=Sun
            foreach($dias as $dia){
                $dnum=$day_map[$dia]??-1;
                if($dnum===$dow||($dnum===0&&$dow===7)){
                    $events[$d][]=['materia_id'=>$mat['id'],'nombre'=>$mat['nombre'],'codigo'=>$mat['codigo'],
                        'hora_inicio'=>$mat['hora_inicio'],'hora_fin'=>$mat['hora_fin'],'docentes'=>$mat['docentes']??''];
                }
            }
        }
    }
    echo json_encode(['ok'=>true,'data'=>['year'=>$y,'month'=>$m,'days_in_month'=>$days_in_month,
        'first_dow'=>(int)date('N',strtotime("$y-$m-01")),'events'=>$events]]); exit;
}

// ════ IMPORTAR ALUMNOS EXCEL ════════════════════════════════
if($action==='importar_alumnos'){
    if(!can('edit')){echo json_encode(['ok'=>false,'msg'=>'Sin permiso.']);exit;}
    $data=json_decode($_POST['data']??'[]',true);
    if(!$data||!is_array($data)){echo json_encode(['ok'=>false,'msg'=>'Sin datos.']);exit;}
    $ok=0;$skip=0;$err=[];
    foreach($data as $i=>$row){
        $nombre  = trim($row['nombre']??$row['Nombre']??'');
        $apellido= trim($row['apellido']??$row['Apellido']??'');
        $cedula  = trim($row['cedula']??$row['Cedula']??$row['CI']??$row['ci']??'');
        $correo  = trim($row['correo']??$row['Correo']??$row['email']??$row['Email']??'');
        $telefono= trim($row['telefono']??$row['Telefono']??'');
        $ciudad  = trim($row['ciudad']??$row['Ciudad']??'');
        if(!$nombre||!$apellido||!$cedula){$skip++;continue;}
        if(!$correo) $correo=$cedula.'@ibbs.edu.ve';
        // Check duplicate
        $ex=mysqli_fetch_assoc(mysqli_query($con,"SELECT id FROM alumnos WHERE cedula='".esc($con,$cedula)."' LIMIT 1"));
        if($ex){$skip++;continue;}
        $st=mysqli_prepare($con,"INSERT INTO alumnos(nombre,apellido,cedula,correo,telefono,ciudad) VALUES(?,?,?,?,?,?)");
        mysqli_stmt_bind_param($st,'ssssss',$nombre,$apellido,$cedula,$correo,$telefono,$ciudad);
        if(mysqli_stmt_execute($st)) $ok++; else {$err[]="Fila $i: ".mysqli_error($con);}
    }
    $msg="$ok alumno(s) importado(s).";
    if($skip) $msg.=" $skip omitido(s) (datos incompletos o cédula duplicada).";
    echo json_encode(['ok'=>true,'msg'=>$msg,'importados'=>$ok,'omitidos'=>$skip,'errores'=>$err]); exit;
}

// ════ CERTIFICADO PDF — datos para generar ══════════════════
if($action==='cert_datos'){
    $aid=(int)($_POST['alumno_id']??0);
    if(!$aid){echo json_encode(['ok'=>false,'msg'=>'Falta alumno.']);exit;}
    $a=mysqli_fetch_assoc(mysqli_query($con,"SELECT * FROM alumnos WHERE id=$aid"));
    if(!$a){echo json_encode(['ok'=>false,'msg'=>'Alumno no encontrado.']);exit;}
    $r=mysqli_query($con,"SELECT ma.*,m.nombre mn,m.codigo mc,m.estado me FROM materia_alumno ma JOIN materias m ON m.id=ma.materia_id WHERE ma.alumno_id=$aid ORDER BY m.nombre");
    $mats=[];while($f=mysqli_fetch_assoc($r))$mats[]=$f;
    echo json_encode(['ok'=>true,'data'=>['alumno'=>$a,'materias'=>$mats,'fecha'=>date('d/m/Y')]]); exit;
}



// ════ AUDIT LOG ══════════════════════════════════════════════
if($action==='audit_list'){
    if(!in_array($_rol,['superadmin','admin'])){echo json_encode(['ok'=>false,'msg'=>'Sin permiso.']);exit;}
    mysqli_query($con,"CREATE TABLE IF NOT EXISTS audit_log (id INT AUTO_INCREMENT PRIMARY KEY,usuario_id INT,accion VARCHAR(100),detalle TEXT,ip VARCHAR(45),creado_en DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB");
    $r=mysqli_query($con,"SELECT al.*,u.usuario FROM audit_log al LEFT JOIN usuarios u ON u.id=al.usuario_id ORDER BY al.creado_en DESC LIMIT 100");
    $rows=[]; while($f=mysqli_fetch_assoc($r)) $rows[]=$f;
    echo json_encode(['ok'=>true,'data'=>$rows]); exit;
}


// ════ DEBUG PIN (temp, solo superadmin) ═════════════════════
if($action==='debug_pin'){
    if($_rol!=='superadmin'){echo json_encode(['ok'=>false]);exit;}
    $pin = trim($_POST['pin']??'');
    $r = mysqli_query($con,"SELECT password_hash FROM usuarios WHERE id=$uid LIMIT 1");
    $row = mysqli_fetch_assoc($r);
    $hash = $row['password_hash']??'none';
    $match = $pin ? password_verify($pin,$hash) : null;
    echo json_encode([
        'ok'=>true,
        'hash_prefix'=>substr($hash,0,20).'...',
        'hash_algo'=> (substr($hash,0,4)==='$2y$'?'bcrypt':'other'),
        'pin_provided'=>(bool)$pin,
        'pin_matches'=>$match,
        'uid'=>$uid
    ]); exit;
}


// ════ SYNC DELETE PIN FROM PASSWORD ═════════════════════════
// Copies current password_hash to delete_pin so PIN = account password
if($action==='sync_delete_pin'){
    if(!in_array($_rol,['superadmin','admin'])){echo json_encode(['ok'=>false]);exit;}
    $col_check = mysqli_query($con,"SELECT COUNT(*) c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='usuarios' AND COLUMN_NAME='delete_pin'");
    $has_col = (int)(mysqli_fetch_assoc($col_check)['c']??0);
    if(!$has_col) mysqli_query($con,"ALTER TABLE usuarios ADD COLUMN delete_pin VARCHAR(255) DEFAULT NULL");
    // Verify current password first
    $pwd = $_POST['pwd']??'';
    $r = mysqli_query($con,"SELECT password_hash FROM usuarios WHERE id=$uid LIMIT 1");
    $row = mysqli_fetch_assoc($r);
    if(!password_verify($pwd,$row['password_hash'])){
        echo json_encode(['ok'=>false,'msg'=>'Contraseña incorrecta.']); exit;
    }
    // Set delete_pin = same hash
    $st=mysqli_prepare($con,"UPDATE usuarios SET delete_pin=password_hash WHERE id=?");
    mysqli_stmt_bind_param($st,'i',$uid); mysqli_stmt_execute($st);
    echo json_encode(['ok'=>true,'msg'=>'PIN configurado. Ahora usa tu contraseña de acceso para confirmar eliminaciones.']); exit;
}

// ════ VERIFY ADMIN PIN ══════════════════════════════════════
if($action==='verify_admin_pin'){
    if(!in_array($_rol,['superadmin','admin'])){
        echo json_encode(['ok'=>false,'msg'=>'Sin permiso para esta acción.']); exit;
    }
    $pin = $_POST['pin']??''; // NO trim — preserve exact input
    if($pin===''){echo json_encode(['ok'=>false,'msg'=>'Ingresa tu contraseña.']);exit;}

    // Try delete_pin first (if column exists and has value)
    $verified = false;
    $col_check = mysqli_query($con,"SELECT COUNT(*) c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='usuarios' AND COLUMN_NAME='delete_pin'");
    $has_col = (int)(mysqli_fetch_assoc($col_check)['c']??0);

    if($has_col){
        $r = mysqli_query($con,"SELECT delete_pin, password_hash FROM usuarios WHERE id=$uid LIMIT 1");
    } else {
        $r = mysqli_query($con,"SELECT NULL as delete_pin, password_hash FROM usuarios WHERE id=$uid LIMIT 1");
    }
    $row = mysqli_fetch_assoc($r);
    if(!$row){echo json_encode(['ok'=>false,'msg'=>'Usuario no encontrado.']);exit;}

    if(!empty($row['delete_pin'])){
        $verified = password_verify($pin, $row['delete_pin']);
        if(!$verified){
            echo json_encode(['ok'=>false,'msg'=>'PIN incorrecto.']); exit;
        }
    } else {
        $verified = password_verify($pin, $row['password_hash']);
        if(!$verified){
            // Try with trimmed version too (edge case)
            $verified = password_verify(trim($pin), $row['password_hash']);
        }
        if(!$verified){
            echo json_encode(['ok'=>false,'msg'=>'Contraseña incorrecta. Usa la contraseña con la que iniciaste sesión.']); exit;
        }
    }
    echo json_encode(['ok'=>true]); exit;
}

// ════ SET DELETE PIN (perfil) ════════════════════════════════
if($action==='set_delete_pin'){
    if(!in_array($_rol,['superadmin','admin'])){
        echo json_encode(['ok'=>false,'msg'=>'Sin permiso.']); exit;
    }
    $col_check2 = mysqli_query($con,"SELECT COUNT(*) c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='usuarios' AND COLUMN_NAME='delete_pin'");
    $has_col2 = (int)(mysqli_fetch_assoc($col_check2)['c']??0);
    if(!$has_col2) mysqli_query($con,"ALTER TABLE usuarios ADD COLUMN delete_pin VARCHAR(255) DEFAULT NULL");
    $pin     = trim($_POST['pin']??'');
    $confirm = trim($_POST['confirm']??'');
    if(strlen($pin)<4){echo json_encode(['ok'=>false,'msg'=>'El PIN debe tener al menos 4 caracteres.']);exit;}
    if($pin!==$confirm){echo json_encode(['ok'=>false,'msg'=>'Los PINs no coinciden.']);exit;}
    $hash = password_hash($pin, PASSWORD_BCRYPT);
    $st=mysqli_prepare($con,"UPDATE usuarios SET delete_pin=? WHERE id=?");
    mysqli_stmt_bind_param($st,'si',$hash,$uid);
    mysqli_stmt_execute($st);
    echo json_encode(['ok'=>true,'msg'=>'PIN de eliminación actualizado correctamente.']); exit;
}

// ════ RESET BD COMPLETA ══════════════════════════════════════
if($action==='reset_bd'){
    if($_rol!=='superadmin'){
        echo json_encode(['ok'=>false,'msg'=>'Solo el Superadmin puede resetear la base de datos.']); exit;
    }
    // Tables to truncate (preserving schema), keep current superadmin user
    mysqli_query($con,"SET FOREIGN_KEY_CHECKS=0");
    $tablas = ['audit_log','notificaciones','asistencias','materia_alumno','materia_docente','materias','docentes','alumnos'];
    foreach($tablas as $t){
        mysqli_query($con,"TRUNCATE TABLE `$t`");
    }
    // Delete non-superadmin users but keep superadmin
    mysqli_query($con,"DELETE FROM usuarios WHERE rol != 'superadmin'");
    mysqli_query($con,"SET FOREIGN_KEY_CHECKS=1");
    log_audit($con,$uid,'RESET_BD','Reset completo de base de datos');
    echo json_encode(['ok'=>true,'msg'=>'Base de datos reseteada. Solo el superadmin fue preservado.']); exit;
}

// ════ RESUMEN GLOBAL ASISTENCIAS (dashboard) ════════════════
if($action==='asistencia_resumen_global'){
    $r=mysqli_query($con,"SELECT estado,COUNT(*) cnt FROM asistencias GROUP BY estado");
    $data=[]; while($f=mysqli_fetch_assoc($r)) $data[$f['estado']]=(int)$f['cnt'];
    echo json_encode(['ok'=>true,'data'=>$data]); exit;
}

// ════ ACTIVIDAD RECIENTE (dashboard) ════════════════════════
if($action==='actividad_reciente'){
    $r=mysqli_query($con,"
        SELECT a.nombre alumno_nombre, a.apellido alumno_apellido,
               m.nombre materia_nombre,
               ma.nota_final, ma.nota_actualizada_en
        FROM materia_alumno ma
        JOIN alumnos a  ON a.id=ma.alumno_id
        JOIN materias m ON m.id=ma.materia_id
        ORDER BY COALESCE(ma.nota_actualizada_en,'1970-01-01') DESC, ma.id DESC
        LIMIT 10");
    $rows=[]; while($f=mysqli_fetch_assoc($r)) $rows[]=$f;
    echo json_encode(['ok'=>true,'data'=>$rows]); exit;
}

// ════ PERFIL ════════════════════════════════════════════════
if($action==='perfil_update'){
    $u=trim($_POST['usuario']??''); $m=trim($_POST['correo']??'');
    if(!$u||!$m){echo json_encode(['ok'=>false,'msg'=>'Campos requeridos.']);exit;}
    $st=mysqli_prepare($con,"UPDATE usuarios SET usuario=?,correo=? WHERE id=?");
    mysqli_stmt_bind_param($st,'ssi',$u,$m,$uid); mysqli_stmt_execute($st);
    $_SESSION['usuario']=$u;
    echo json_encode(['ok'=>true,'msg'=>'Datos actualizados.']); exit;
}
if($action==='perfil_pwd'){
    $act=trim($_POST['actual']??''); $nw=trim($_POST['nueva']??'');
    if(!$act||!$nw){echo json_encode(['ok'=>false,'msg'=>'Completa los campos.']);exit;}
    if(strlen($nw)<8){echo json_encode(['ok'=>false,'msg'=>'La contraseña debe tener al menos 8 caracteres.']);exit;}
    if(!preg_match('/[A-Z]/',$nw)){echo json_encode(['ok'=>false,'msg'=>'Debe contener al menos una mayúscula.']);exit;}
    if(!preg_match('/[a-z]/',$nw)){echo json_encode(['ok'=>false,'msg'=>'Debe contener al menos una minúscula.']);exit;}
    if(!preg_match('/[0-9!@#$%^&*()\_+\-=\[\]{};\':",.<>?\/|`~]/',$nw)){echo json_encode(['ok'=>false,'msg'=>'Debe contener al menos un número o carácter especial (#, @, 1, etc.)']);exit;}
    $r=mysqli_fetch_assoc(mysqli_query($con,"SELECT password_hash FROM usuarios WHERE id=$uid"));
    if(!password_verify($act,$r['password_hash'])){echo json_encode(['ok'=>false,'msg'=>'Contraseña actual incorrecta.']);exit;}
    $hash=password_hash($nw,PASSWORD_BCRYPT);
    $st=mysqli_prepare($con,"UPDATE usuarios SET password_hash=? WHERE id=?");
    mysqli_stmt_bind_param($st,'si',$hash,$uid); mysqli_stmt_execute($st);
    echo json_encode(['ok'=>true,'msg'=>'Contraseña actualizada.']); exit;
}
if($action==='perfil_pregs'){
    $p1=trim($_POST['preg1']??''); $r1=strtolower(trim($_POST['resp1']??''));
    $p2=trim($_POST['preg2']??''); $r2=strtolower(trim($_POST['resp2']??''));
    if(!$p1||!$r1||!$p2||!$r2){echo json_encode(['ok'=>false,'msg'=>'Completa todo.']);exit;}
    $h1=password_hash($r1,PASSWORD_BCRYPT); $h2=password_hash($r2,PASSWORD_BCRYPT);
    $st=mysqli_prepare($con,"UPDATE usuarios SET preg1=?,resp1_hash=?,preg2=?,resp2_hash=? WHERE id=?");
    mysqli_stmt_bind_param($st,'ssssi',$p1,$h1,$p2,$h2,$uid); mysqli_stmt_execute($st);
    echo json_encode(['ok'=>true,'msg'=>'Preguntas de seguridad guardadas.']); exit;
}

// ════ USUARIOS (con permisos) ════════════════════════════════
if($action==='usuario_delete'){
    if($_rol!=='superadmin'){echo json_encode(['ok'=>false,'msg'=>'Solo el superadmin puede eliminar usuarios.']);exit;}
    $id=(int)($_POST['id']??0);
    if($id===$uid){echo json_encode(['ok'=>false,'msg'=>'No puedes eliminar tu propia cuenta.']);exit;}
    mysqli_query($con,"DELETE FROM usuarios WHERE id=$id");
    log_audit($con,$uid,'USUARIO_DELETE',"ID=$id");
    echo json_encode(['ok'=>true,'msg'=>'Usuario eliminado.']); exit;
}
if($action==='usuario_set_rol'){
    if($_rol!=='superadmin'){echo json_encode(['ok'=>false,'msg'=>'Solo el superadmin puede cambiar roles.']);exit;}
    $id=(int)($_POST['id']??0); $rol=trim($_POST['rol']??'');
    if(!in_array($rol,['superadmin','admin','profesor'])){echo json_encode(['ok'=>false,'msg'=>'Rol inválido.']);exit;}
    if($id===$uid){echo json_encode(['ok'=>false,'msg'=>'No puedes cambiar tu propio rol.']);exit;}
    mysqli_query($con,"UPDATE usuarios SET rol='".esc($con,$rol)."' WHERE id=$id");
    echo json_encode(['ok'=>true,'msg'=>'Rol actualizado.']); exit;
}


// ════ HISTORIAL ACTIVIDAD ════════════════════════════════════
if($action==='historial_actividad'){
    $tipo  = trim($_POST['tipo']??'');
    $fecha = trim($_POST['fecha']??'');
    $data  = [];
    $resumen = ['notas'=>0,'inscripciones'=>0,'asistencias'=>0,'usuarios'=>0];

    // Notas
    if(!$tipo || $tipo==='nota'){
        $wh = $fecha ? "AND DATE(ma.nota_actualizada_en)='$fecha'" : '';
        $r=mysqli_query($con,"SELECT ma.id ma_id, CONCAT(al.apellido,', ',al.nombre) persona,
            CONCAT(m.nombre,' (',m.codigo,')') detalle,
            ma.nota_final valor, ma.nota_actualizada_en fecha
            FROM materia_alumno ma
            JOIN alumnos al ON al.id=ma.alumno_id
            JOIN materias m ON m.id=ma.materia_id
            WHERE ma.nota_final IS NOT NULL $wh
            ORDER BY ma.nota_actualizada_en DESC LIMIT 200");
        while($f=mysqli_fetch_assoc($r)){
            $nv=(float)$f['valor'];
            $data[]=['id'=>'nota_'.($f['ma_id']??0),'tipo'=>'nota','tipo_label'=>'Nota','persona'=>$f['persona'],'detalle'=>$f['detalle'],
                'valor'=>number_format($nv,1).'/20',
                'valor_color'=>$nv>=15?'#15803d':($nv>=10?'#d97706':'#dc2626'),
                'fecha'=>$f['fecha']?date('d/m/Y H:i',strtotime($f['fecha'])):'—'];
            $resumen['notas']++;
        }
    }
    // Inscripciones
    if(!$tipo || $tipo==='inscripcion'){
        $r=mysqli_query($con,"SELECT ma.id ma_id, CONCAT(al.apellido,', ',al.nombre) persona,
            CONCAT(m.nombre,' (',m.codigo,')') detalle
            FROM materia_alumno ma
            JOIN alumnos al ON al.id=ma.alumno_id
            JOIN materias m ON m.id=ma.materia_id
            ORDER BY ma.id DESC LIMIT 200");
        while($f=mysqli_fetch_assoc($r)){
            $data[]=['id'=>'inscripcion_'.($f['ma_id']??0),'tipo'=>'inscripcion','tipo_label'=>'Inscripción','persona'=>$f['persona'],
                'detalle'=>$f['detalle'],'valor'=>'Inscrito','valor_color'=>'#1d4ed8','fecha'=>'—'];
            $resumen['inscripciones']++;
        }
    }
    // Asistencias
    if(!$tipo || $tipo==='asistencia'){
        $wh = $fecha ? "AND a.fecha='$fecha'" : '';
        $r=mysqli_query($con,"SELECT a.id asist_id, a.estado, a.fecha,
            CONCAT(al.apellido,', ',al.nombre) persona, m.nombre detalle
            FROM asistencias a
            JOIN alumnos al ON al.id=a.alumno_id
            JOIN materias m ON m.id=a.materia_id
            WHERE a.tipo='alumno' $wh
            ORDER BY a.creado_en DESC LIMIT 200");
        $eL=['presente'=>'Presente','ausente'=>'Ausente','tardanza'=>'Tardanza','justificado'=>'Justificado'];
        $eC=['presente'=>'#15803d','ausente'=>'#dc2626','tardanza'=>'#d97706','justificado'=>'#6d28d9'];
        while($f=mysqli_fetch_assoc($r)){
            $data[]=['id'=>'asistencia_'.($f['asist_id']??0),'tipo'=>'asistencia','tipo_label'=>'Asistencia','persona'=>$f['persona'],
                'detalle'=>$f['detalle'],'valor'=>$eL[$f['estado']]??$f['estado'],
                'valor_color'=>$eC[$f['estado']]??'#666','fecha'=>date('d/m/Y',strtotime($f['fecha']))];
            $resumen['asistencias']++;
        }
    }
    // Audit log
    if(!$tipo || $tipo==='usuario'){
        $wh = $fecha ? "AND DATE(al2.creado_en)='$fecha'" : '';
        $r=mysqli_query($con,"SELECT al2.id audit_id, al2.accion, al2.detalle, al2.creado_en, u.usuario persona
            FROM audit_log al2
            LEFT JOIN usuarios u ON u.id=al2.usuario_id
            WHERE 1 $wh ORDER BY al2.creado_en DESC LIMIT 100");
        while($f=mysqli_fetch_assoc($r)){
            $data[]=['id'=>'usuario_'.($f['audit_id']??0),'tipo'=>'usuario','tipo_label'=>'Sistema','persona'=>$f['persona']??'Sistema',
                'detalle'=>$f['accion'].($f['detalle']?' — '.mb_substr($f['detalle'],0,60):''),
                'valor'=>null,'valor_color'=>'','fecha'=>date('d/m/Y H:i',strtotime($f['creado_en']))];
            $resumen['usuarios']++;
        }
    }
    usort($data,function($a,$b){ return strcmp($b['fecha'],$a['fecha']); });
    $rango='';
    if(count($data)){
        $primero=end($data)['fecha']; $ultimo=$data[0]['fecha'];
        $rango=$primero!==$ultimo?"Del $primero al $ultimo":$ultimo;
    }
    echo json_encode(['ok'=>true,'data'=>$data,'resumen'=>$resumen,'rango'=>$rango]);
    exit;
}


// ════ ASISTENCIA EDITAR ═══════════════════════════════════════
if($action==='asistencia_editar'){
    if(!can('edit')){echo json_encode(['ok'=>false,'msg'=>'Sin permiso.']);exit;}
    $id     = (int)($_POST['id']??0);
    $estado = esc($con,$_POST['estado']??'presente');
    $obs    = esc($con,$_POST['observacion']??'');
    $valid  = ['presente','ausente','tardanza','justificado'];
    if(!in_array($estado,$valid)){echo json_encode(['ok'=>false,'msg'=>'Estado inválido.']);exit;}
    mysqli_query($con,"UPDATE asistencias SET estado='$estado', observacion='$obs' WHERE id=$id");
    echo json_encode(['ok'=>true,'msg'=>'Asistencia actualizada.']);
    exit;
}

// ════ ASISTENCIA RESUMEN ══════════════════════════════════════
if($action==='asistencia_resumen'){
    $mid     = (int)($_POST['materia_id']??0);
    $periodo = trim($_POST['periodo']??'');
    $where_m = $mid ? "AND a.materia_id=$mid" : '';
    $where_f = '';
    if($periodo==='semana')     $where_f = "AND a.fecha >= DATE_SUB(CURDATE(),INTERVAL 7 DAY)";
    elseif($periodo==='mes')    $where_f = "AND a.fecha >= DATE_FORMAT(CURDATE(),'%Y-%m-01')";

    // Global counts
    $gr = mysqli_fetch_assoc(mysqli_query($con,
        "SELECT SUM(estado='presente') presente, SUM(estado='ausente') ausente,
                SUM(estado='tardanza') tardanza, SUM(estado='justificado') justificado
         FROM asistencias a WHERE tipo='alumno' $where_m $where_f"));

    // Per-alumno breakdown
    $data=[];
    $r=mysqli_query($con,
        "SELECT CONCAT(al.apellido,', ',al.nombre) alumno, m.nombre materia,
                SUM(a.estado='presente') presentes, SUM(a.estado='ausente') ausentes,
                SUM(a.estado='tardanza') tardanzas, SUM(a.estado='justificado') justificados
         FROM asistencias a
         JOIN alumnos al ON al.id=a.alumno_id
         JOIN materias m ON m.id=a.materia_id
         WHERE a.tipo='alumno' $where_m $where_f
         GROUP BY a.alumno_id, a.materia_id
         ORDER BY al.apellido, al.nombre");
    while($f=mysqli_fetch_assoc($r)) $data[]=$f;
    echo json_encode(['ok'=>true,'global'=>$gr,'data'=>$data]);
    exit;
}


// ════ DB TABLE INFO ═══════════════════════════════════════════
if($action==='db_table_info'){
    if(!can('all')){echo json_encode(['ok'=>false,'msg'=>'Sin permiso.']);exit;}
    $tables=['usuarios','alumnos','docentes','materias','materia_alumno','materia_docente','asistencias','notificaciones','periodos','audit_log'];
    $data=[];
    foreach($tables as $t){
        $r=mysqli_query($con,"SELECT COUNT(*) c FROM `$t`");
        if($r){
            $count=(int)mysqli_fetch_assoc($r)['c'];
            $data[]=['nombre'=>$t,'registros'=>$count];
        }
    }
    echo json_encode(['ok'=>true,'data'=>$data]);
    exit;
}


// ════ HISTORIAL DELETE ════════════════════════════════════════
if($action==='historial_delete'){
    if(!can('delete_data')&&!can('all')){echo json_encode(['ok'=>false,'msg'=>'Sin permiso.']);exit;}
    $ids_raw = trim($_POST['ids']??'');
    if(!$ids_raw){echo json_encode(['ok'=>false,'msg'=>'Sin IDs.']);exit;}
    // Parse the composite IDs — format: "tipo_dbid"
    $ids_arr = array_filter(array_map('trim', explode(',', $ids_raw)));
    $deleted = 0;
    foreach($ids_arr as $compound){
        $parts = explode('_', $compound, 2);
        if(count($parts)!==2) continue;
        [$tipo, $dbid] = $parts;
        $dbid = (int)$dbid;
        if(!$dbid) continue;
        switch($tipo){
            case 'nota':
                // Clear nota from materia_alumno (set null, don't delete inscription)
                mysqli_query($con,"UPDATE materia_alumno SET nota_final=NULL,nota_fecha=NULL,nota_registrada_por=NULL,nota_actualizada_en=NULL WHERE id=$dbid");
                $deleted++; break;
            case 'inscripcion':
                // Remove inscription entirely
                mysqli_query($con,"DELETE FROM materia_alumno WHERE id=$dbid");
                $deleted++; break;
            case 'asistencia':
                mysqli_query($con,"DELETE FROM asistencias WHERE id=$dbid");
                $deleted++; break;
            case 'usuario':
                mysqli_query($con,"DELETE FROM audit_log WHERE id=$dbid");
                $deleted++; break;
        }
    }
    log_audit($con,$uid,'HISTORIAL_DELETE',"Eliminados: $deleted registros");
    echo json_encode(['ok'=>true,'msg'=>"$deleted registro(s) eliminado(s) del historial."]);
    exit;
}

// Acción no reconocida
echo json_encode(['ok'=>false,'msg'=>"Accion '$action' no reconocida."]);
mysqli_close($con);
