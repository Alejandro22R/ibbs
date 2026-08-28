<?php
/**
 * IBBS — Reporte institucional con diseño UBV-style
 * Descarga directa via window.print() — sin nueva pestaña
 */
session_start();
if(empty($_SESSION['loggedin'])){ header('Location: ../login.php'); exit; }
require_once __DIR__.'/../config/database.php';
function esc($c,$v){return mysqli_real_escape_string($c,$v);}

$con     = db();
if (!$con) die('Error de conexión a la base de datos.');
$tipo    = trim($_GET['tipo']??'alumnos');
$reporte = trim($_GET['reporte']??'');
$fecha   = date('d/m/Y');
$hora    = date('H:i');
$usuario = htmlspecialchars($_SESSION['usuario']??'Sistema');

$titulo=''; $headers=[]; $rows=[];
switch($tipo){
  case 'alumnos':
    $titulo='Listado de Alumnos';
    $reporte=$reporte?:'Reporte General de Alumnos';
    $headers=['N°','Apellido, Nombre','Cédula','Correo','Ciudad','Estado'];
    $r=mysqli_query($con,"SELECT nombre,apellido,cedula,correo,ciudad,activo FROM alumnos WHERE activo=1 ORDER BY apellido,nombre");
    $i=1; while($f=mysqli_fetch_assoc($r)) $rows[]=[$i++,htmlspecialchars($f['apellido'].', '.$f['nombre']),htmlspecialchars($f['cedula']),htmlspecialchars($f['correo']),htmlspecialchars($f['ciudad']??'—'),$f['activo']?'Activo':'Inactivo'];
    break;
  case 'docentes':
    $titulo='Listado de Docentes';
    $reporte=$reporte?:'Reporte General de Docentes';
    $headers=['N°','Apellido, Nombre','Cédula','Especialidad','Correo','Estado'];
    $r=mysqli_query($con,"SELECT nombre,apellido,cedula,especialidad,correo,activo FROM docentes WHERE activo=1 ORDER BY apellido,nombre");
    $i=1; while($f=mysqli_fetch_assoc($r)) $rows[]=[$i++,htmlspecialchars($f['apellido'].', '.$f['nombre']),htmlspecialchars($f['cedula']),htmlspecialchars($f['especialidad']??'—'),htmlspecialchars($f['correo']),$f['activo']?'Activo':'Inactivo'];
    break;
  case 'materias':
    $titulo='Listado de Materias';
    $reporte=$reporte?:'Reporte General de Materias';
    $headers=['N°','Materia','Código','Días','Horario','Estado'];
    $r=mysqli_query($con,"SELECT nombre,codigo,dias,hora_inicio,hora_fin,estado FROM materias WHERE activo=1 ORDER BY nombre");
    $i=1; while($f=mysqli_fetch_assoc($r)){
      $h=($f['hora_inicio']&&$f['hora_fin'])?substr($f['hora_inicio'],0,5).'–'.substr($f['hora_fin'],0,5):'—';
      $rows[]=[$i++,htmlspecialchars($f['nombre']),htmlspecialchars($f['codigo']),htmlspecialchars($f['dias']??'—'),$h,ucfirst(str_replace('_',' ',$f['estado']))];
    }
    break;
  case 'asistencias':
    $titulo='Registro de Asistencias';
    $reporte=$reporte?:'Reporte de Asistencias';
    $fd=esc($con,$_GET['fecha']??date('Y-m-d'));
    $headers=['N°','Alumno','Materia','Fecha','Estado','Observación'];
    $r=mysqli_query($con,"SELECT a.fecha,a.estado,a.observacion,CONCAT(al.apellido,', ',al.nombre) alumno,m.nombre materia FROM asistencias a JOIN alumnos al ON al.id=a.alumno_id JOIN materias m ON m.id=a.materia_id WHERE a.tipo='alumno' AND a.fecha='$fd' ORDER BY m.nombre,al.apellido");
    $i=1; while($f=mysqli_fetch_assoc($r)) $rows[]=[$i++,htmlspecialchars($f['alumno']),htmlspecialchars($f['materia']),date('d/m/Y',strtotime($f['fecha'])),ucfirst($f['estado']),htmlspecialchars($f['observacion']??'')];
    break;
  default: die('Tipo inválido.');
}
$total = count($rows);

// Logo inline para impresión offline
$logoPath=__DIR__.'/../assets/logo.jpg';
$logoB64=$logoPath&&file_exists($logoPath)?'data:image/jpeg;base64,'.base64_encode(file_get_contents($logoPath)):'';

function estadoBadge($val){
  $v=strtolower($val);
  if(str_contains($v,'activo')||str_contains($v,'presente')||str_contains($v,'culminada'))return 'b-ap';
  if(str_contains($v,'inactivo')||str_contains($v,'ausente')||str_contains($v,'reprobado'))return 'b-rp';
  if(str_contains($v,'en curso'))return 'b-ec';
  if(str_contains($v,'pendiente'))return 'b-pe';
  if(str_contains($v,'tardanza'))return 'b-td';
  if(str_contains($v,'justificado'))return 'b-jt';
  return '';
}
$lastCol = count($headers)-1;
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title><?=$titulo?> — IBBS</title>
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:Arial,sans-serif;font-size:10pt;color:#1a1a1a;background:#fff;padding:20px;}

/* ── Header institucional ── */
.hdr-outer{border:2px solid #bbb;margin-bottom:12px;}
.hdr-top{display:flex;align-items:center;gap:14px;padding:10px 14px;background:#f5f5f5;border-bottom:1px solid #bbb;}
.logo-box{width:64px;height:64px;border:1.5px solid #aaa;border-radius:50%;overflow:hidden;background:#fff;flex-shrink:0;display:flex;align-items:center;justify-content:center;}
.logo-box img{width:100%;height:100%;object-fit:cover;}
.logo-ini{font-size:1.3rem;font-weight:900;color:#1a4d2e;}
.inst-center{flex:1;text-align:center;}
.inst-name{font-size:13pt;font-weight:bold;}
.inst-sub{font-size:8.5pt;color:#444;margin-top:3px;line-height:1.5;}
.doc-bar{background:#1a4d2e;padding:7px 14px;text-align:center;color:#fff;font-size:10pt;font-weight:bold;}

/* ── Meta datos ── */
.meta-box{border:1px solid #bbb;border-top:none;background:#fafafa;padding:8px 14px;display:flex;gap:20px;font-size:9pt;}
.meta-item{display:flex;gap:6px;}
.meta-lbl{font-weight:bold;color:#333;}
.meta-val{color:#555;}

/* ── Tabla ── */
table{width:100%;border-collapse:collapse;font-size:9.5pt;margin-bottom:0;}
.tbl-outer{border:1px solid #bbb;}
thead th{background:#1a4d2e;color:#fff;padding:7px 9px;text-align:left;font-size:8.5pt;font-weight:bold;text-transform:uppercase;letter-spacing:.5px;border-right:1px solid rgba(255,255,255,.15);}
thead th:last-child{border-right:none;}
tbody td{padding:5px 9px;border-bottom:1px solid #e0e0e0;border-right:1px solid #eee;vertical-align:middle;}
tbody td:last-child{border-right:none;}
tbody tr:nth-child(even) td{background:#f5f5f5;}
tbody tr:last-child td{border-bottom:none;}
.badge{display:inline-block;padding:1px 6px;border-radius:2px;font-size:7.5pt;font-weight:bold;text-transform:uppercase;letter-spacing:.3px;}
.b-ap{background:#d4edda;color:#1a6b2e;border:1px solid #b8dac3;}
.b-rp{background:#fde8e8;color:#991b1b;border:1px solid #f5c6c6;}
.b-ec{background:#dbeafe;color:#1d4ed8;border:1px solid #bfdbfe;}
.b-pe{background:#fef9c3;color:#92400e;border:1px solid #fde68a;}
.b-td{background:#ffedd5;color:#9a3412;border:1px solid #fed7aa;}
.b-jt{background:#ede9fe;color:#5b21b6;border:1px solid #ddd6fe;}

/* ── Total row ── */
.total-row td{background:#e8e8e8;font-weight:bold;font-size:8.5pt;border:1px solid #bbb;padding:5px 9px;text-align:right;}

/* ── Firma ── */
.firma-section{display:flex;justify-content:flex-end;margin-top:24px;margin-bottom:16px;}
.firma-box{text-align:center;min-width:220px;}
.firma-line{border-top:1px solid #333;margin-bottom:4px;}
.firma-label{font-size:9pt;font-weight:bold;}
.firma-sub{font-size:8pt;color:#555;margin-top:2px;}

/* ── Footer ── */
.footer-bar{border:1px solid #bbb;background:#f5f5f5;padding:6px 14px;display:flex;justify-content:space-between;font-size:7.5pt;color:#555;}

/* ── Botones no-print ── */
.no-print{margin-bottom:14px;display:flex;gap:.6rem;align-items:center;}
.btn-dl{padding:7px 16px;background:#1a4d2e;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:.85rem;font-weight:bold;font-family:Arial,sans-serif;}
.btn-dl:hover{background:#1e5c36;}
.btn-bk{padding:7px 12px;background:#fff;color:#1a4d2e;border:1px solid #1a4d2e;border-radius:6px;font-size:.82rem;text-decoration:none;font-family:Arial,sans-serif;}

@media print{
  .no-print{display:none!important;}
  @page{size:A4;margin:12mm;}
  body{padding:8px;}
}
</style>
</head>
<body>

<div class="no-print">
  <a class="btn-bk" href="../modulo_<?=$tipo==='asistencias'?'asistencias':($tipo==='docentes'?'docentes':($tipo==='materias'?'materias':'alumnos'))?>.php">&#8592; Volver</a>
  <button class="btn-dl" onclick="window.print()">Descargar PDF / Imprimir</button>
</div>

<!-- HEADER -->
<div class="hdr-outer">
  <div class="hdr-top">
    <div class="logo-box">
      <?php if($logoB64): ?><img src="<?=$logoB64?>" alt="Logo">
      <?php else: ?><div class="logo-ini">IB</div><?php endif; ?>
    </div>
    <div class="inst-center">
      <div class="inst-name">Instituto Bíblico Bautista del Sur</div>
      <div class="inst-sub">Sistema Académico IBBS<br>Ciudad Bolívar, Venezuela</div>
    </div>
  </div>
  <div class="doc-bar"><?=$titulo?> — Generado: <?=$fecha?> <?=$hora?> (VEN)</div>
</div>

<!-- META -->
<div class="meta-box">
  <div class="meta-item"><span class="meta-lbl">Reporte:</span><span class="meta-val"><?=htmlspecialchars($reporte)?></span></div>
  <div class="meta-item"><span class="meta-lbl">Responsable:</span><span class="meta-val"><?=$usuario?></span></div>
  <div class="meta-item"><span class="meta-lbl">Total registros:</span><span class="meta-val"><?=$total?></span></div>
</div>

<!-- TABLE -->
<div class="tbl-outer" style="margin-top:10px;">
<table>
  <thead>
    <tr>
      <?php foreach($headers as $h): ?>
      <th><?=htmlspecialchars($h)?></th>
      <?php endforeach; ?>
    </tr>
  </thead>
  <tbody>
    <?php if($rows): ?>
      <?php foreach($rows as $row): ?>
      <tr>
        <?php foreach($row as $ci=>$cell):
          $cls=($ci===$lastCol&&$ci>0)?estadoBadge($cell):'';
        ?>
        <td><?=$cls?'<span class="badge '.$cls.'">'.htmlspecialchars($cell).'</span>':htmlspecialchars($cell)?></td>
        <?php endforeach; ?>
      </tr>
      <?php endforeach; ?>
    <?php else: ?>
      <tr><td colspan="<?=count($headers)?>" style="text-align:center;padding:14px;color:#999;">Sin registros para mostrar.</td></tr>
    <?php endif; ?>
  </tbody>
  <tfoot>
    <tr class="total-row">
      <td colspan="<?=count($headers)?>">Total: <?=$total?> registros</td>
    </tr>
  </tfoot>
</table>
</div>

<!-- FIRMA -->
<div class="firma-section">
  <div class="firma-box">
    <div class="firma-line"></div>
    <div class="firma-label">Firma de La Directora Encargada</div>
    <div class="firma-sub">Instituto Bíblico Bautista del Sur</div>
  </div>
</div>

<!-- FOOTER -->
<div class="footer-bar">
  <span>Instituto Bíblico Bautista del Sur — Sistema Académico IBBS — 1997–<?=date('Y')?></span>
  <span>Documento generado el <?=$fecha?> a las <?=$hora?></span>
</div>

</body>
</html>
