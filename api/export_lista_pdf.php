<?php
/**
 * IBBS — Reportes en PDF (vía impresión) de listados de datos:
 * Asistencias y Pagos. Mismo membrete institucional (logo, tipografía
 * y estructura) que api/export_constancia.php, para que un reporte
 * tenga la misma cara formal que una constancia — pero acá la tabla
 * es la planilla filtrada completa, no el expediente de una sola
 * persona.
 *
 * ?tipo=asistencias  (+ materia_id, tipo_persona, fecha, estado — los
 *                      mismos filtros de la pestaña Historial)
 * ?tipo=pagos        (+ tipo_pago, estado, alumno_id)
 *
 * Solo admin/superadmin (son los únicos con acceso a esos módulos).
 */
require_once __DIR__.'/../config/bootstrap.php';
if (empty($_SESSION['loggedin'])) { header('Location: ../login.php'); exit; }

$con = db();
if (!$con) die('Error de conexión a la base de datos.');

$rol = $_SESSION['rol'] ?? 'alumno';
$uid = (int)($_SESSION['user_id'] ?? 0);
if (!in_array($rol, ['superadmin', 'admin'])) die('No tenés permiso para generar este reporte.');

$tipo = in_array($_GET['tipo'] ?? '', ['asistencias', 'pagos']) ? $_GET['tipo'] : '';
if (!$tipo) die('Reporte inválido.');

$hoy       = new DateTime();
$meses     = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
$diaLetra  = (int)$hoy->format('j');
$mesLetra  = $meses[(int)$hoy->format('n') - 1];
$anioLetra = $hoy->format('Y');
$codVerif  = "IBBS-REP-".strtoupper(substr($tipo,0,3))."-{$anioLetra}-" . random_int(10000, 99999);

$cols = []; $filas = []; $resumen = []; $filtrosTxt = [];

if ($tipo === 'asistencias') {
    $mid    = (int)($_GET['materia_id'] ?? 0);
    $tp     = trim($_GET['tipo_persona'] ?? '');
    $fecha  = trim($_GET['fecha'] ?? '');
    $estado = trim($_GET['estado'] ?? '');
    $w = [];
    if ($mid) $w[] = "a.materia_id=$mid";
    if (in_array($tp, ['alumno','docente'], true)) $w[] = "a.tipo='".mysqli_real_escape_string($con,$tp)."'";
    if ($fecha) $w[] = "a.fecha='".mysqli_real_escape_string($con,$fecha)."'";
    if (in_array($estado, ['presente','ausente','tardanza','justificado'], true)) $w[] = "a.estado='".mysqli_real_escape_string($con,$estado)."'";
    $wq = $w ? 'WHERE '.implode(' AND ',$w) : '';
    $r = mysqli_query($con, "SELECT a.fecha,a.estado,a.observacion,m.nombre materia,
        COALESCE(CONCAT(al.apellido,', ',al.nombre),CONCAT(d.apellido,', ',d.nombre)) persona,
        COALESCE(al.cedula,d.cedula) cedula
        FROM asistencias a LEFT JOIN materias m ON m.id=a.materia_id
        LEFT JOIN alumnos al ON al.id=a.alumno_id LEFT JOIN docentes d ON d.id=a.docente_id
        $wq ORDER BY a.fecha DESC LIMIT 2000");
    $cont = ['presente'=>0,'ausente'=>0,'tardanza'=>0,'justificado'=>0];
    while ($f = mysqli_fetch_assoc($r)) { $filas[] = $f; $cont[$f['estado']] = ($cont[$f['estado']]??0)+1; }
    $total = count($filas);
    $pct = $total ? round($cont['presente']/$total*100) : 0;

    $titulo = 'Reporte de Asistencias';
    $cols = ['FECHA','PERSONA','CÉDULA','MATERIA','ESTADO','OBSERVACIÓN'];
    if ($mid) { $mn = mysqli_fetch_assoc(mysqli_query($con,"SELECT nombre FROM materias WHERE id=$mid")); if($mn) $filtrosTxt[] = 'Materia: '.$mn['nombre']; }
    if ($fecha) $filtrosTxt[] = 'Fecha: '.$fecha;
    if ($estado) $filtrosTxt[] = 'Estado: '.ucfirst($estado);
    if ($tp) $filtrosTxt[] = 'Tipo: '.ucfirst($tp);
    $resumen = [
        'Total de registros' => $total,
        'Presentes' => $cont['presente'], 'Ausentes' => $cont['ausente'],
        'Tardanzas' => $cont['tardanza'], 'Justificados' => $cont['justificado'],
        '% de asistencia' => $pct.'%',
    ];
    log_audit($con, $uid, 'REPORTE_ASISTENCIAS_PDF', "filtros=".implode('|',$filtrosTxt));
} else {
    $tpago  = trim($_GET['tipo_pago'] ?? '');
    $estado = trim($_GET['estado'] ?? '');
    $aid    = (int)($_GET['alumno_id'] ?? 0);
    mysqli_query($con, "CREATE TABLE IF NOT EXISTS pagos (
        id INT AUTO_INCREMENT PRIMARY KEY, alumno_id INT NOT NULL,
        tipo ENUM('mensualidad','inscripcion') NOT NULL, concepto VARCHAR(150) NOT NULL,
        monto DECIMAL(10,2) NOT NULL, comprobante VARCHAR(255) DEFAULT NULL,
        estado ENUM('pendiente','en_revision','pagado','rechazado') NOT NULL DEFAULT 'pendiente',
        creado_por INT DEFAULT NULL, revisado_por INT DEFAULT NULL, revisado_en DATETIME DEFAULT NULL,
        fecha_vencimiento DATE DEFAULT NULL, creado_en DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX(alumno_id), INDEX(estado)) ENGINE=InnoDB");
    $w = ['1=1'];
    if (in_array($tpago, ['mensualidad','inscripcion'], true)) $w[] = "p.tipo='".mysqli_real_escape_string($con,$tpago)."'";
    if (in_array($estado, ['pendiente','en_revision','pagado','rechazado'], true)) $w[] = "p.estado='".mysqli_real_escape_string($con,$estado)."'";
    if ($aid) $w[] = "p.alumno_id=$aid";
    $r = mysqli_query($con, "SELECT p.*, a.nombre an, a.apellido aa, a.cedula ac FROM pagos p JOIN alumnos a ON a.id=p.alumno_id
        WHERE ".implode(' AND ',$w)." ORDER BY p.creado_en DESC LIMIT 2000");
    $cont = ['pendiente'=>0,'en_revision'=>0,'pagado'=>0,'rechazado'=>0]; $montoCobrado = 0; $montoPendiente = 0;
    while ($f = mysqli_fetch_assoc($r)) {
        $filas[] = $f; $cont[$f['estado']] = ($cont[$f['estado']]??0)+1;
        if ($f['estado']==='pagado') $montoCobrado += (float)$f['monto'];
        if (in_array($f['estado'],['pendiente','en_revision'])) $montoPendiente += (float)$f['monto'];
    }
    $titulo = 'Reporte de Cobros y Pagos';
    $cols = ['ALUMNO','CÉDULA','TIPO','CONCEPTO','MONTO','ESTADO','FECHA'];
    if ($tpago) $filtrosTxt[] = 'Tipo: '.($tpago==='mensualidad'?'Mensualidad':'Inscripción');
    if ($estado) $filtrosTxt[] = 'Estado: '.ucfirst(str_replace('_',' ',$estado));
    if ($aid) { $an = mysqli_fetch_assoc(mysqli_query($con,"SELECT nombre,apellido FROM alumnos WHERE id=$aid")); if($an) $filtrosTxt[] = 'Alumno: '.$an['apellido'].', '.$an['nombre']; }
    $resumen = [
        'Total de cobros' => count($filas),
        'Pendientes' => $cont['pendiente'], 'En revisión' => $cont['en_revision'],
        'Pagados' => $cont['pagado'], 'Rechazados' => $cont['rechazado'],
        'Monto cobrado' => '$'.number_format($montoCobrado,2), 'Monto pendiente' => '$'.number_format($montoPendiente,2),
    ];
    log_audit($con, $uid, 'REPORTE_PAGOS_PDF', "filtros=".implode('|',$filtrosTxt));
}

$logoPath = __DIR__.'/../assets/logo.jpg';
$logoB64  = $logoPath && file_exists($logoPath) ? 'data:image/jpeg;base64,'.base64_encode(file_get_contents($logoPath)) : '';
$estLabel = ['presente'=>'Presente','ausente'=>'Ausente','tardanza'=>'Tardanza','justificado'=>'Justificado',
             'pendiente'=>'Pendiente','en_revision'=>'En revisión','pagado'=>'Pagado','rechazado'=>'Rechazado'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars(mb_strtoupper($titulo)) ?></title>
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{
  font-family:'Times New Roman', Times, Georgia, serif;
  font-size:11pt;color:#000;background:#fff;
  padding:15mm;line-height:1.5;
}
.membrete-table{width:100%;border-collapse:collapse;border-bottom:1.5px solid #000;padding-bottom:8pt;margin-bottom:12pt;}
.membrete-text{text-align:left;line-height:1.3;}
.membrete-title-gov{margin:0;font-size:9.5pt;font-weight:bold;text-transform:uppercase;letter-spacing:.5px;}
.membrete-title-inst{margin:2pt 0;font-size:11pt;font-weight:bold;text-transform:uppercase;}
.document-title-container{text-align:center;margin-top:15pt;margin-bottom:10pt;}
.document-title{margin:0;font-size:13pt;font-weight:bold;text-transform:uppercase;letter-spacing:1px;}
.document-subtitle{margin:3pt 0 0;font-size:8pt;font-family:monospace;color:#000;}
.filtros-line{text-align:center;font-size:8.5pt;color:#333;margin-bottom:10pt;font-style:italic;}
.print-table{width:100%;border-collapse:collapse;margin-top:6pt;margin-bottom:12pt;}
.print-table th{background-color:#f5f5f5;color:#000;font-weight:bold;border:1px solid #000;padding:4pt 6pt;text-transform:uppercase;font-size:8pt;text-align:center;}
.print-table td{border:1px solid #000;padding:4pt 6pt;font-size:9pt;}
.observations-box{border:1px solid #000;padding:8pt;margin-top:10pt;font-size:9pt;line-height:1.6;}
.observations-box strong{display:inline-block;min-width:140pt;}
.closing-paragraph{font-size:9.5pt;text-align:justify;margin-top:15pt;font-style:italic;}
.footer-legal{margin-top:40pt;border-top:1.5px solid #000;padding-top:4pt;text-align:center;font-size:7.5pt;color:#000;page-break-inside:avoid;}
.no-print{margin-bottom:16px;display:flex;gap:.6rem;align-items:center;font-family:Arial,sans-serif;}
.btn-dl{padding:7px 16px;background:#1a4d2e;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:.85rem;font-weight:bold;font-family:Arial,sans-serif;}
.btn-dl:hover{background:#1e5c36;}
.btn-bk{padding:7px 12px;background:#fff;color:#1a4d2e;border:1px solid #1a4d2e;border-radius:6px;font-size:.82rem;text-decoration:none;font-family:Arial,sans-serif;}
@media print{
  .no-print{display:none!important;}
  body{padding:0;}
  .print-table{page-break-inside:auto;}
  .print-table tr{page-break-inside:avoid;page-break-after:auto;}
  @page{size:letter <?= $tipo==='pagos' ? 'landscape' : 'portrait' ?>;margin:15mm;}
}
</style>
</head>
<body>

<div class="no-print">
  <a class="btn-bk" href="<?= $tipo==='asistencias' ? 'javascript:history.back()' : 'javascript:history.back()' ?>">&#8592; Volver</a>
  <button class="btn-dl" onclick="window.print()">Descargar PDF / Imprimir</button>
</div>

<!-- Membrete institucional oficial -->
<table class="membrete-table">
  <tr>
    <td style="vertical-align:top;" class="membrete-text">
      <h4 class="membrete-title-gov">Gobierno Eclesiástico Autónomo</h4>
      <h4 class="membrete-title-gov" style="margin:2pt 0;">Asociación de Iglesias Bautistas de Venezuela</h4>
      <h3 class="membrete-title-inst">Instituto Bíblico Bautista del Sur</h3>
      <p style="margin:2pt 0 0;font-size:8.5pt;color:#222;">Dirección de Registro y Control de Actividades Académicas</p>
      <p style="margin:1pt 0 0;font-size:8.5pt;color:#222;font-style:italic;">Ciudad Bolívar, Estado Bolívar</p>
    </td>
    <td style="text-align:right;vertical-align:top;width:80pt;">
      <?php if($logoB64): ?><img src="<?=$logoB64?>" alt="IBBS" style="width:65pt;height:65pt;border-radius:50%;border:1px solid #000;"><?php endif; ?>
    </td>
  </tr>
</table>

<div class="document-title-container">
  <h2 class="document-title"><?= htmlspecialchars($titulo) ?></h2>
  <p class="document-subtitle">CÓDIGO DE VERIFICACIÓN INSTITUCIONAL: <?= htmlspecialchars($codVerif) ?></p>
</div>

<?php if ($filtrosTxt): ?>
<p class="filtros-line">Filtros aplicados: <?= htmlspecialchars(implode(' · ', $filtrosTxt)) ?></p>
<?php endif; ?>

<table class="print-table">
  <thead><tr><?php foreach($cols as $c): ?><th><?=htmlspecialchars($c)?></th><?php endforeach; ?></tr></thead>
  <tbody>
  <?php if (!$filas): ?>
    <tr><td colspan="<?=count($cols)?>" style="text-align:center;font-style:italic;">Sin registros para los filtros aplicados.</td></tr>
  <?php elseif ($tipo === 'asistencias'): foreach ($filas as $f): ?>
    <tr>
      <td style="text-align:center;white-space:nowrap;"><?=htmlspecialchars($f['fecha'])?></td>
      <td><?=htmlspecialchars($f['persona']??'—')?></td>
      <td style="text-align:center;"><?=htmlspecialchars($f['cedula']??'—')?></td>
      <td><?=htmlspecialchars($f['materia']??'—')?></td>
      <td style="text-align:center;"><?=htmlspecialchars($estLabel[$f['estado']]??$f['estado'])?></td>
      <td><?=htmlspecialchars($f['observacion']??'')?></td>
    </tr>
  <?php endforeach; else: foreach ($filas as $f): ?>
    <tr>
      <td><?=htmlspecialchars($f['aa'].', '.$f['an'])?></td>
      <td style="text-align:center;"><?=htmlspecialchars($f['ac'])?></td>
      <td style="text-align:center;"><?=$f['tipo']==='mensualidad'?'Mensualidad':'Inscripción'?></td>
      <td><?=htmlspecialchars($f['concepto'])?></td>
      <td style="text-align:right;">$<?=number_format((float)$f['monto'],2)?></td>
      <td style="text-align:center;"><?=htmlspecialchars($estLabel[$f['estado']]??$f['estado'])?></td>
      <td style="text-align:center;white-space:nowrap;"><?=htmlspecialchars(substr($f['creado_en'],0,10))?></td>
    </tr>
  <?php endforeach; endif; ?>
  </tbody>
</table>

<div class="observations-box">
  <strong style="display:block;text-transform:uppercase;font-size:8.5pt;margin-bottom:4pt;">Resumen:</strong>
  <?php foreach($resumen as $k=>$v): ?>
    <div><strong><?=htmlspecialchars($k)?>:</strong> <?=htmlspecialchars((string)$v)?></div>
  <?php endforeach; ?>
</div>

<p class="closing-paragraph">
  Reporte generado por la Dirección de Registro y Control de Actividades Académicas del Instituto Bíblico Bautista del Sur, en Ciudad Bolívar a los <?= $diaLetra ?> días del mes de <?= $mesLetra ?> de <?= $anioLetra ?>.
</p>

<div class="footer-legal">
  Instituto Bíblico Bautista del Sur &middot; Calle Igualdad, Casco Histórico, Ciudad Bolívar, Estado Bolívar, Venezuela &middot; Dirección de Registro y Control de Estudios.
</div>

</body>
</html>
