<?php
/**
 * IBBS — Boletín de Calificaciones
 * Diseño institucional: encabezado con logo, datos del alumno,
 * tabla de notas, asistencias. Se descarga directo con print dialog.
 */
require_once __DIR__.'/../config/bootstrap.php';
if(empty($_SESSION['loggedin'])){ header('Location: ../login.php'); exit; }

$aid = (int)($_GET['alumno_id']??0);
if(!$aid) die('ID inválido');
$con = db();
if (!$con) die('Error de conexión a la base de datos.');

$a = mysqli_fetch_assoc(mysqli_query($con,"SELECT a.*, u.correo uc FROM alumnos a LEFT JOIN usuarios u ON u.id=a.usuario_id WHERE a.id=$aid LIMIT 1"));
if(!$a) die('Alumno no encontrado');

$materias=[];
$r=mysqli_query($con,"SELECT m.nombre mn,m.codigo,m.estado,m.dias,m.hora_inicio,m.hora_fin,ma.nota_final,ma.nota_fecha,
    GROUP_CONCAT(CONCAT(d.nombre,' ',d.apellido) SEPARATOR ', ') docentes
    FROM materia_alumno ma JOIN materias m ON m.id=ma.materia_id
    LEFT JOIN materia_docente md ON md.materia_id=m.id
    LEFT JOIN docentes d ON d.id=md.docente_id
    WHERE ma.alumno_id=$aid GROUP BY m.id,ma.nota_final,ma.nota_fecha ORDER BY m.nombre");
while($f=mysqli_fetch_assoc($r)) $materias[]=$f;

$asist=mysqli_fetch_assoc(mysqli_query($con,"SELECT SUM(estado='presente') p,SUM(estado='ausente') a,SUM(estado='tardanza') t,SUM(estado='justificado') j FROM asistencias WHERE alumno_id=$aid AND tipo='alumno'"));
$conNota=array_filter($materias,fn($m)=>$m['nota_final']!==null);
$aprobadas=array_filter($conNota,fn($m)=>$m['nota_final']>=15);
$reprobadas=array_filter($conNota,fn($m)=>$m['nota_final']<15);
$promedio=count($conNota)?array_sum(array_column(array_values($conNota),'nota_final'))/count($conNota):null;
$totalA=($asist['p']??0)+($asist['a']??0)+($asist['t']??0);
$pctA=$totalA?round($asist['p']/$totalA*100):0;
$nombre_c=htmlspecialchars($a['apellido'].', '.$a['nombre']);
$fecha=date('d/m/Y'); $hora=date('H:i');

// Logo base64 para que funcione offline en print
$logoPath=__DIR__.'/../assets/logo.jpg';
$logoB64=$logoPath&&file_exists($logoPath)?'data:image/jpeg;base64,'.base64_encode(file_get_contents($logoPath)):'';

$nc=fn($n)=>$n===null?'#666':($n>=15?'#1a6b2e':($n>=10?'#92400e':'#991b1b'));
$estLabel=['pendiente'=>'Pendiente','en_curso'=>'En curso','culminada'=>'Culminada'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Boletín — <?=$nombre_c?></title>
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:Arial,sans-serif;font-size:10.5pt;color:#1a1a1a;background:#fff;padding:20px;}

/* ── Encabezado institucional ── */
.hdr-outer{border:2px solid #ccc;border-radius:0;margin-bottom:0;}
.hdr-inner{display:flex;align-items:center;gap:14px;padding:10px 14px;background:#f5f5f5;border-bottom:1px solid #ccc;}
.logo-box{width:70px;height:70px;border:1.5px solid #aaa;border-radius:50%;overflow:hidden;background:#fff;flex-shrink:0;display:flex;align-items:center;justify-content:center;}
.logo-box img{width:100%;height:100%;object-fit:cover;}
.logo-ini{font-size:1.4rem;font-weight:900;color:#1a4d2e;}
.inst-block{flex:1;text-align:center;}
.inst-name{font-size:13pt;font-weight:bold;color:#1a1a1a;}
.inst-sub{font-size:9pt;color:#444;margin-top:2px;}
.doc-title-bar{background:#e8e8e8;border-bottom:1px solid #ccc;text-align:center;padding:7px 14px;font-size:10.5pt;font-weight:bold;}

/* ── Alumno header row ── */
.alumno-hdr{display:flex;align-items:center;gap:14px;padding:10px 14px;border-bottom:1px solid #ccc;background:#fff;}
.ava{width:64px;height:64px;border-radius:50%;overflow:hidden;border:2px solid #aaa;background:#1a4d2e;color:#fff;font-size:1.5rem;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.ava img{width:100%;height:100%;object-fit:cover;border-radius:50%;}
.alumno-info{flex:1;text-align:center;}
.alumno-nombre{font-size:12pt;font-weight:bold;}
.alumno-meta{font-size:9pt;color:#444;margin-top:2px;line-height:1.6;}
.alumno-sums{display:flex;gap:16px;justify-content:flex-end;align-items:center;}
.sum-item{text-align:center;border:1px solid #ccc;padding:5px 10px;background:#f9f9f9;min-width:56px;}
.sum-n{font-size:13pt;font-weight:bold;color:#1a4d2e;}
.sum-l{font-size:7.5pt;text-transform:uppercase;letter-spacing:.5px;color:#666;}

/* ── Sección label ── */
.sec-hdr{background:#e8e8e8;border:1px solid #ccc;border-bottom:none;padding:5px 10px;font-size:9pt;font-weight:bold;text-transform:uppercase;letter-spacing:.8px;text-align:center;margin-top:12px;}

/* ── Tabla de notas ── */
table{width:100%;border-collapse:collapse;font-size:9.5pt;}
.tbl-outer{border:1px solid #ccc;}
thead th{background:#1a4d2e;color:#fff;padding:6px 8px;text-align:left;font-size:8.5pt;text-transform:uppercase;letter-spacing:.5px;border-right:1px solid rgba(255,255,255,.2);}
thead th:last-child{border-right:none;}
tbody td{padding:5px 8px;border-bottom:1px solid #e0e0e0;border-right:1px solid #eeeeee;vertical-align:middle;}
tbody td:last-child{border-right:none;}
tbody tr:nth-child(even) td{background:#f7f7f7;}
tbody tr:last-child td{border-bottom:none;}
.nota-val{font-weight:bold;font-size:10.5pt;}
.badge{display:inline-block;padding:1px 6px;border-radius:3px;font-size:7.5pt;font-weight:bold;text-transform:uppercase;}
.b-ap{background:#d4edda;color:#1a6b2e;border:1px solid #b8dac3;}
.b-rp{background:#fde8e8;color:#991b1b;border:1px solid #f5c6c6;}
.b-ec{background:#dbeafe;color:#1d4ed8;border:1px solid #bfdbfe;}
.b-cu{background:#d4edda;color:#1a6b2e;border:1px solid #b8dac3;}
.b-pe{background:#fef9c3;color:#92400e;border:1px solid #fde68a;}

/* ── Fila resumen ── */
.summary-row td{background:#f0f4f0;font-weight:bold;font-size:8.5pt;text-align:center;border:1px solid #ccc;padding:5px 8px;text-transform:uppercase;letter-spacing:.4px;}
.summary-val{font-size:13pt;font-weight:bold;color:#1a4d2e;display:block;}

/* ── Asistencia ── */
.asist-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:0;border:1px solid #ccc;}
.asist-cell{text-align:center;padding:8px 6px;border-right:1px solid #ccc;}
.asist-cell:last-child{border-right:none;}
.asist-cell.hdr{background:#e8e8e8;font-size:8pt;font-weight:bold;text-transform:uppercase;letter-spacing:.4px;border-bottom:1px solid #ccc;}
.asist-n{font-size:14pt;font-weight:bold;color:#1a4d2e;}
.asist-l{font-size:7.5pt;color:#555;text-transform:uppercase;letter-spacing:.4px;margin-top:1px;}
.asist-bar-wrap{border:1px solid #ccc;margin-top:6px;}
.asist-bar-inner{display:flex;align-items:center;gap:8px;padding:5px 10px;}
.asist-bar{flex:1;height:8px;background:#e0e0e0;border-radius:4px;overflow:hidden;}
.asist-fill{height:100%;border-radius:4px;}

/* ── Promedio / pie ── */
.prom-box{border:1px solid #ccc;background:#1a4d2e;padding:10px 14px;display:flex;justify-content:space-between;align-items:center;margin-top:8px;flex-wrap:wrap;gap:8px;}
.prom-num{font-size:24pt;font-weight:bold;color:#fff;line-height:1;}
.prom-lbl{font-size:7.5pt;color:rgba(255,255,255,.55);text-transform:uppercase;letter-spacing:.6px;margin-top:2px;}
.prom-details{display:flex;gap:10px;}
.pd-item{text-align:center;background:rgba(255,255,255,.1);padding:5px 10px;border-radius:3px;}
.pd-n{font-size:12pt;font-weight:bold;color:#fff;}
.pd-l{font-size:7.5pt;color:rgba(255,255,255,.5);text-transform:uppercase;letter-spacing:.4px;}
.prom-status{font-size:9pt;font-weight:bold;text-align:right;color:rgba(255,255,255,.7);}

/* ── Footer ── */
.footer-bar{border:1px solid #ccc;border-top:none;background:#f5f5f5;padding:6px 14px;display:flex;justify-content:space-between;font-size:8pt;color:#666;margin-top:-1px;}

/* ── Botones (no imprime) ── */
.no-print{margin-bottom:16px;display:flex;gap:.6rem;align-items:center;}
.btn-dl{padding:7px 16px;background:#1a4d2e;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:.85rem;font-weight:700;font-family:Arial,sans-serif;}
.btn-dl:hover{background:#1e5c36;}
@media print{
  .no-print{display:none!important;}
  body{padding:10px;}
  @page{size:A4;margin:12mm;}
}
</style>
</head>
<body>

<div class="no-print">
  <button class="btn-dl" onclick="window.print()">Descargar PDF / Imprimir</button>
  <a href="../modulo_record.php" style="font-size:.82rem;color:#666;">&#8592; Volver al record</a>
</div>

<!-- ══ ENCABEZADO INSTITUCIONAL ══ -->
<div class="hdr-outer">
  <div class="hdr-inner">
    <div class="logo-box">
      <?php if($logoB64): ?><img src="<?=$logoB64?>" alt="Logo">
      <?php else: ?><div class="logo-ini">IB</div><?php endif; ?>
    </div>
    <div class="inst-block">
      <div class="inst-name">Instituto Bíblico Bautista del Sur</div>
      <div class="inst-sub">Sistema Académico IBBS — Boletín de Calificaciones<br>Ciudad Bolívar, Venezuela</div>
    </div>
  </div>
  <div class="doc-title-bar">Boletín de Calificaciones Generado: <?=$fecha?> <?=$hora?> (VEN)</div>

  <!-- ── Datos del alumno ── -->
  <div class="alumno-hdr">
    <div class="ava">
      <?php if($a['foto']&&file_exists(__DIR__.'/../'.$a['foto'])): ?>
        <img src="../<?=htmlspecialchars($a['foto'])?>" alt="">
      <?php else: ?><?=strtoupper(mb_substr($a['nombre'],0,1))?><?php endif; ?>
    </div>
    <div class="alumno-info">
      <div class="alumno-nombre"><?=$nombre_c?></div>
      <div class="alumno-meta">
        CI: <?=htmlspecialchars($a['cedula'])?> &nbsp;|&nbsp;
        <?=htmlspecialchars($a['correo']??$a['uc']??'—')?>
        <?=$a['telefono']?' &nbsp;|&nbsp; '.htmlspecialchars($a['telefono']):'';?>
        <?=$a['ciudad']?' &nbsp;|&nbsp; '.htmlspecialchars($a['ciudad']):'';?>
      </div>
    </div>
    <div class="alumno-sums">
      <div class="sum-item"><div class="sum-n"><?=count($materias)?></div><div class="sum-l">Materias</div></div>
      <div class="sum-item"><div class="sum-n" style="color:#1a6b2e;"><?=count($aprobadas)?></div><div class="sum-l">Aprobadas</div></div>
      <div class="sum-item"><div class="sum-n" style="color:#991b1b;"><?=count($reprobadas)?></div><div class="sum-l">Reprobadas</div></div>
      <?php if($promedio!==null): ?>
      <div class="sum-item"><div class="sum-n" style="color:<?=$nc($promedio)?>;"><?=number_format($promedio,1)?></div><div class="sum-l">Promedio</div></div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- ══ CALIFICACIONES ══ -->
<div class="sec-hdr">Calificaciones por Materia</div>
<div class="tbl-outer">
<table>
  <thead><tr>
    <th>Código</th><th>Materia</th><th>Docente(s)</th>
    <th style="text-align:center;">Estado</th>
    <th style="text-align:center;">Nota Final</th>
    <th style="text-align:center;">Resultado</th>
  </tr></thead>
  <tbody>
    <?php foreach($materias as $m):
      $nv=$m['nota_final']!==null?(float)$m['nota_final']:null;
      $eb=['pendiente'=>'b-pe','en_curso'=>'b-ec','culminada'=>'b-cu'][$m['estado']]??'b-pe';
    ?>
    <tr>
      <td style="font-family:monospace;font-size:8.5pt;color:#666;"><?=htmlspecialchars($m['codigo'])?></td>
      <td><strong><?=htmlspecialchars($m['mn'])?></strong></td>
      <td style="font-size:9pt;color:#555;"><?=htmlspecialchars($m['docentes']??'—')?></td>
      <td style="text-align:center;"><span class="badge <?=$eb?>"><?=$estLabel[$m['estado']]??$m['estado']?></span></td>
      <td style="text-align:center;"><span class="nota-val" style="color:<?=$nc($nv)?>"><?=$nv!==null?number_format($nv,1):'—'?></span></td>
      <td style="text-align:center;">
        <?php if($nv!==null): ?><span class="badge <?=$nv>=15?'b-ap':'b-rp'?>"><?=$nv>=15?'Aprobado':'Reprobado'?></span>
        <?php else: ?><span style="color:#999;font-size:8.5pt;">Pendiente</span><?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if(!$materias): ?>
    <tr><td colspan="6" style="text-align:center;padding:12px;color:#999;">Sin materias inscritas.</td></tr>
    <?php endif; ?>
  </tbody>
  <?php if(count($conNota)>0): ?>
  <tfoot>
    <tr class="summary-row">
      <td colspan="2">Total materias evaluadas: <?=count($conNota)?></td>
      <td>Aprobadas: <?=count($aprobadas)?></td>
      <td>Reprobadas: <?=count($reprobadas)?></td>
      <td>Pendientes: <?=count($materias)-count($conNota)?></td>
      <td><?=$promedio!==null?'Prom: '.number_format($promedio,2):''?></td>
    </tr>
  </tfoot>
  <?php endif; ?>
</table>
</div>

<!-- ══ ASISTENCIAS ══ -->
<?php if($totalA>0): ?>
<div class="sec-hdr" style="margin-top:10px;">Registro de Asistencias</div>
<div class="asist-grid">
  <div class="asist-cell hdr">Presentes</div>
  <div class="asist-cell hdr">Ausentes</div>
  <div class="asist-cell hdr">Tardanzas</div>
  <div class="asist-cell hdr">Justificados</div>
  <div class="asist-cell"><div class="asist-n" style="color:#1a6b2e;"><?=(int)($asist['p']??0)?></div><div class="asist-l"><?=round(($asist['p']??0)/$totalA*100)?>%</div></div>
  <div class="asist-cell"><div class="asist-n" style="color:#991b1b;"><?=(int)($asist['a']??0)?></div><div class="asist-l"><?=round(($asist['a']??0)/$totalA*100)?>%</div></div>
  <div class="asist-cell"><div class="asist-n" style="color:#92400e;"><?=(int)($asist['t']??0)?></div><div class="asist-l"><?=round(($asist['t']??0)/$totalA*100)?>%</div></div>
  <div class="asist-cell"><div class="asist-n" style="color:#4c1d95;"><?=(int)($asist['j']??0)?></div><div class="asist-l"><?=round(($asist['j']??0)/$totalA*100)?>%</div></div>
</div>
<div class="asist-bar-wrap">
  <div class="asist-bar-inner">
    <span style="font-size:8.5pt;color:#555;white-space:nowrap;min-width:80px;">Asistencia general</span>
    <div class="asist-bar"><div class="asist-fill" style="width:<?=$pctA?>%;background:<?=$pctA>=75?'#1a6b2e':($pctA>=50?'#d97706':'#dc2626')?>"></div></div>
    <strong style="font-size:9.5pt;min-width:36px;"><?=$pctA?>%</strong>
  </div>
</div>
<?php endif; ?>

<!-- ══ PROMEDIO FINAL ══ -->
<?php if($promedio!==null): ?>
<div class="prom-box">
  <div>
    <div class="prom-num"><?=number_format($promedio,2)?><span style="font-size:12pt;color:rgba(255,255,255,.4);">/20</span></div>
    <div class="prom-lbl">Promedio General</div>
  </div>
  <div class="prom-details">
    <div class="pd-item"><div class="pd-n"><?=count($aprobadas)?></div><div class="pd-l">Aprobadas</div></div>
    <div class="pd-item"><div class="pd-n"><?=count($reprobadas)?></div><div class="pd-l">Reprobadas</div></div>
    <div class="pd-item"><div class="pd-n"><?=count($materias)-count($conNota)?></div><div class="pd-l">Pendientes</div></div>
  </div>
  <div class="prom-status">
    Aprueba con nota &ge; 15/20<br>
    <?=$promedio>=15?'<span style="color:#6ee7b7;">&#10003; PROMEDIO APROBATORIO</span>':'<span style="color:#fca5a5;">&#10007; PROMEDIO REPROBATORIO</span>'?>
  </div>
</div>
<?php endif; ?>

<!-- ══ FOOTER ══ -->
<div class="footer-bar">
  <span>Sistema Académico IBBS — Instituto Bíblico Bautista del Sur</span>
  <span>Documento generado el <?=$fecha?> a las <?=$hora?> — Hora Venezuela</span>
</div>

</body>
</html>
