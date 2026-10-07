<?php
/**
 * IBBS — Constancia de Notas / Estudios (alumno) y Constancia de
 * Trabajo (docente) — PDF vía impresión.
 *
 * Constancia de Estudio y de Notas: trámite gratuito y de autoservicio
 * — el alumno la genera para sí mismo (?tipo=estudio|notas, sin
 * alumno_id) cuando quiera, sin pasar por administración. Admin y
 * superadmin también pueden emitirla para cualquier alumno pasando
 * alumno_id. Queda un registro en la auditoría de quién la emitió y
 * para quién.
 *
 * La Constancia de Notas solo incluye una materia si el administrador
 * la confirmó de alguna de las dos formas posibles — y además está
 * 100% calificada (ningún alumno de esa materia con nota_final en
 * NULL):
 *   (a) la marcó "culminada" (materia_set_estado, modulo_materias.php,
 *       exclusivo de admin/superadmin) — esto cubre las notas que el
 *       propio admin cargó directo en modulo_notas.php ("Cargar
 *       Notas"), la única pantalla desde donde se puede hacer eso; o
 *   (b) existe un envío de notas (api/notas_envio.php) con estado
 *       "aprobado" — el profesor las propuso en su Plan de Notas y el
 *       admin las revisó y aprobó desde Herramientas.
 * Una materia en curso, sin ninguna de las dos confirmaciones, o con
 * notas incompletas, nunca aparece — evita que el récord oficial
 * muestre una nota a medias o sin confirmar.
 *
 * tipo=trabajo es distinto: la genera el propio docente para sí mismo
 * (constancia de que presta servicios en la institución) — admin/
 * superadmin también puede emitirla para cualquier docente.
 */
require_once __DIR__.'/../config/bootstrap.php';
if (empty($_SESSION['loggedin'])) { header('Location: ../login.php'); exit; }

$con = db();
if (!$con) die('Error de conexión a la base de datos.');

$rol  = $_SESSION['rol'] ?? 'alumno';
$uid  = (int)($_SESSION['user_id'] ?? 0);
$tiposValidos = ['estudio', 'notas', 'trabajo'];
$tipo = in_array($_GET['tipo'] ?? '', $tiposValidos) ? $_GET['tipo'] : 'estudio';

if ($tipo === 'trabajo') {
    // ── Constancia de Trabajo (docente) ──────────────────────────
    if ($rol === 'profesor') {
        $doc = mysqli_fetch_assoc(mysqli_query($con, "SELECT id FROM docentes WHERE usuario_id=$uid LIMIT 1"));
        $did = $doc ? (int)$doc['id'] : 0;
    } elseif (in_array($rol, ['superadmin', 'admin'])) {
        $did = (int)($_GET['docente_id'] ?? 0);
    } else {
        die('No tenés permiso para generar este documento.');
    }
    if (!$did) die('Docente no encontrado.');
    $docente = mysqli_fetch_assoc(mysqli_query($con, "SELECT * FROM docentes WHERE id=$did LIMIT 1"));
    if (!$docente) die('Docente no encontrado.');
    log_audit($con, $uid, 'CONSTANCIA_TRABAJO_GENERAR', "docente=$did");
} else {
    // ── Constancia de Estudio / Notas (alumno) — autoservicio gratuito ──
    if ($rol === 'alumno') {
        $al  = mysqli_fetch_assoc(mysqli_query($con, "SELECT id FROM alumnos WHERE usuario_id=$uid LIMIT 1"));
        $aid = $al ? (int)$al['id'] : 0;
    } elseif (in_array($rol, ['superadmin', 'admin'])) {
        $aid = (int)($_GET['alumno_id'] ?? 0);
    } else {
        die('No tenés permiso para generar este documento.');
    }
    if (!$aid) die('Alumno no encontrado.');
    $alumno = mysqli_fetch_assoc(mysqli_query($con, "SELECT * FROM alumnos WHERE id=$aid LIMIT 1"));
    if (!$alumno) die('Alumno no encontrado.');
    log_audit($con, $uid, 'CONSTANCIA_'.strtoupper($tipo).'_GENERAR', "alumno=$aid");

    // Materias culminada O con un envío de notas aprobado, y 100%
    // calificadas (ningún alumno de esa materia con nota_final
    // pendiente) — ver la nota al principio del archivo.
    $materias = [];
    if ($tipo === 'notas') {
        $r = mysqli_query($con, "SELECT ma.nota_final,m.nombre mn,m.codigo mc
                                  FROM materia_alumno ma JOIN materias m ON m.id=ma.materia_id
                                  WHERE ma.alumno_id=$aid
                                    AND (
                                        m.estado='culminada'
                                        OR EXISTS (
                                            SELECT 1 FROM notas_envios ne
                                            WHERE ne.materia_id=ma.materia_id AND ne.estado='aprobado'
                                        )
                                    )
                                    AND NOT EXISTS (
                                        SELECT 1 FROM materia_alumno ma2
                                        WHERE ma2.materia_id=ma.materia_id AND ma2.nota_final IS NULL
                                    )
                                  ORDER BY m.nombre");
        while ($f = mysqli_fetch_assoc($r)) $materias[] = $f;
        if (!$materias) die('Todavía no tenés ninguna materia culminada con notas aprobadas por la administración para incluir en tu Constancia de Notas. Una vez que tu profesor cargue las notas y el administrador las apruebe, vas a poder generarla acá.');
    }
}

function ibbs_nota_a_letras($n) {
    $escala = ["CERO","UNO","DOS","TRES","CUATRO","CINCO","SEIS","SIETE","OCHO","NUEVE","DIEZ",
               "ONCE","DOCE","TRECE","CATORCE","QUINCE","DIECISÉIS","DIECISIETE","DIECIOCHO","DIECINUEVE","VEINTE"];
    if ($n === null) return 'PENDIENTE';
    $v = (int)round((float)$n);
    if ($v >= 0 && $v <= 20) return "$v {$escala[$v]}";
    return (string)$n;
}

$hoy       = new DateTime();
$meses     = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
$diaLetra  = (int)$hoy->format('j');
$mesLetra  = $meses[(int)$hoy->format('n') - 1];
$anioLetra = $hoy->format('Y');
$firma     = 'Director Académico';

if ($tipo === 'trabajo') {
    $codVerif   = "IBBS-CERT-D{$did}-{$anioLetra}-" . random_int(10000, 99999);
    $titulo     = 'Constancia de Trabajo';
    $nombreUp   = htmlspecialchars(mb_strtoupper($docente['apellido'].', '.$docente['nombre']));
    $cedulaDig  = preg_replace('/\D/', '', $docente['cedula'] ?? '');
    $cedulaFmt  = 'V-'.number_format((int)$cedulaDig, 0, ',', '.');
    $especialidadUp = htmlspecialchars(mb_strtoupper($docente['especialidad'] ?? 'Docente'));
    $desdeFecha = $docente['creado_en'] ? (new DateTime($docente['creado_en']))->format('d/m/Y') : '—';
    $cuerpoTexto = "Quien suscribe, Director De Registro Y Control De Actividades Académicas del <strong>Instituto Bíblico Bautista del Sur</strong>, hace constar por medio de la presente que el Ciudadano: <strong style=\"text-transform:uppercase;\">$nombreUp</strong>, titular de la cédula de identidad N°: <strong>$cedulaFmt</strong>, presta sus servicios como <strong>Docente</strong> en esta Casa de Estudios Teológicos desde el <strong>$desdeFecha</strong>, desempeñándose en el área de <strong>$especialidadUp</strong>, encontrándose activo a la fecha de expedición de la presente constancia.";
} else {
    $conNota    = array_filter($materias, fn($m) => $m['nota_final'] !== null);
    $aprobadas  = count(array_filter($conNota, fn($m) => (float)$m['nota_final'] >= 15));
    $registradas = count($materias);
    $promedio   = count($conNota) ? array_sum(array_map(fn($m) => (float)$m['nota_final'], $conNota)) / count($conNota) : null;

    $codVerif  = "IBBS-CERT-{$aid}-{$anioLetra}-" . random_int(10000, 99999);
    $titulo    = $tipo === 'notas' ? 'Constancia de Calificaciones y Rendimiento' : 'Constancia de Estudios';
    $nombreUp  = htmlspecialchars(mb_strtoupper($alumno['apellido'].', '.$alumno['nombre']));
    $cedulaDig = preg_replace('/\D/', '', $alumno['cedula'] ?? '');
    $cedulaFmt = 'V-'.number_format((int)$cedulaDig, 0, ',', '.');
    $ciudadUp  = htmlspecialchars(mb_strtoupper($alumno['ciudad'] ?? '—'));

    $cuerpoTexto = $tipo === 'estudio'
        ? "Quien suscribe, Director De Registro Y Control De Actividades Académicas del <strong>Instituto Bíblico Bautista del Sur</strong>, hace constar por medio de la presente que en los archivos de esta Casa de Estudios Teológicos reposa el Expediente del Ciudadano: <strong style=\"text-transform:uppercase;\">$nombreUp</strong>, titular de la cédula de identidad N°: <strong>$cedulaFmt</strong>, quien se encuentra cursando de forma activa y regular sus programas de formación bíblica y ministerial correspondientes."
        : "Quien suscribe, Director De Registro Y Control De Actividades Académicas del <strong>Instituto Bíblico Bautista del Sur</strong>, hace constar por medio de la presente que en los archivos de esta Casa de Estudios Teológicos reposa el Expediente de Estudios del Ciudadano: <strong style=\"text-transform:uppercase;\">$nombreUp</strong>, titular de la cédula de identidad N°: <strong>$cedulaFmt</strong>, habiendo cursado las unidades curriculares que a continuación se especifican:";
}
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
.document-title-container{text-align:center;margin-top:15pt;margin-bottom:15pt;}
.document-title{margin:0;font-size:13pt;font-weight:bold;text-transform:uppercase;letter-spacing:1px;}
.document-subtitle{margin:3pt 0 0;font-size:8pt;font-family:monospace;color:#000;}
.legal-paragraph{text-align:justify;font-size:10.5pt;text-indent:25pt;margin-bottom:12pt;}
.print-table{width:100%;border-collapse:collapse;margin-top:10pt;margin-bottom:12pt;}
.print-table th{background-color:#f5f5f5;color:#000;font-weight:bold;border:1px solid #000;padding:5pt 7pt;text-transform:uppercase;font-size:9pt;text-align:center;}
.print-table td{border:1px solid #000;padding:5pt 7pt;font-weight:bold;}
.observations-box{border:1px solid #000;padding:8pt;margin-top:10pt;font-size:9pt;line-height:1.4;}
.closing-paragraph{font-size:10pt;text-align:justify;margin-top:15pt;font-style:italic;}
.signatures-container{margin-top:60pt;display:flex;justify-content:space-between;page-break-inside:avoid;}
.signature-block{width:45%;text-align:center;}
.signature-line{border-top:1px solid #000;width:100%;margin-bottom:4pt;}
.signature-title{margin:0;font-size:9pt;font-weight:bold;text-transform:uppercase;}
.signature-sub{margin:2pt 0 0;font-size:8pt;color:#444;}
.footer-legal{margin-top:60pt;border-top:1.5px solid #000;padding-top:4pt;text-align:center;font-size:7.5pt;color:#000;page-break-inside:avoid;}
.no-print{margin-bottom:16px;display:flex;gap:.6rem;align-items:center;font-family:Arial,sans-serif;}
.btn-dl{padding:7px 16px;background:#1a4d2e;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:.85rem;font-weight:bold;font-family:Arial,sans-serif;}
.btn-dl:hover{background:#1e5c36;}
.btn-bk{padding:7px 12px;background:#fff;color:#1a4d2e;border:1px solid #1a4d2e;border-radius:6px;font-size:.82rem;text-decoration:none;font-family:Arial,sans-serif;}
@media print{
  .no-print{display:none!important;}
  body{padding:0;}
  @page{size:letter portrait;margin:15mm;}
}
</style>
</head>
<body>

<div class="no-print">
  <a class="btn-bk" href="../<?= $rol==='profesor' ? 'portal_docente.php' : ($rol==='alumno' ? 'portal_alumno.php' : 'modulo_herramientas.php') ?>">&#8592; Volver</a>
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
      <img src="../assets/logo.jpg" alt="IBBS" style="width:65pt;height:65pt;border-radius:50%;border:1px solid #000;">
    </td>
  </tr>
</table>

<!-- Título de Documento -->
<div class="document-title-container">
  <h2 class="document-title"><?= htmlspecialchars($titulo) ?></h2>
  <p class="document-subtitle">CÓDIGO DE VERIFICACIÓN INSTITUCIONAL: <?= htmlspecialchars($codVerif) ?></p>
</div>

<!-- Párrafo Legal -->
<p class="legal-paragraph"><?= $cuerpoTexto ?></p>

<?php if ($tipo === 'notas' && $materias): ?>
<table class="print-table">
  <thead>
    <tr>
      <th style="width:15%;">CÓDIGO</th>
      <th style="width:50%;text-align:left;">MATERIA / UNIDAD CURRICULAR</th>
      <th style="width:35%;">CALIFICACIÓN</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($materias as $m): ?>
    <tr>
      <td style="text-align:center;"><?= htmlspecialchars($m['mc'] ?: 'UC') ?></td>
      <td><?= htmlspecialchars(mb_strtoupper($m['mn'])) ?></td>
      <td style="text-align:center;"><?= htmlspecialchars(ibbs_nota_a_letras($m['nota_final'] !== null ? (float)$m['nota_final'] : null)) ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<div class="observations-box">
  <strong style="text-transform:uppercase;font-size:8.5pt;">Observaciones Reglamentarias:</strong>
  <table style="width:100%;font-size:8.5pt;margin-top:4pt;border-collapse:collapse;">
    <tr>
      <td style="width:60%;border:none;padding:2pt 0;vertical-align:top;">
        1.- La escala de calificaciones aplicable es del uno (1) al veinte (20).<br>
        2.- La calificación mínima aprobatoria requerida es de Quince (15) puntos.<br>
        3.- Procedencia del estudiante: <span style="text-transform:uppercase;font-weight:bold;"><?= $ciudadUp ?></span>
      </td>
      <td style="width:40%;border:none;padding:2pt 0;vertical-align:top;border-left:1px solid #000;padding-left:10pt;">
        <strong>Índice de Rendimiento Académico:</strong> <span style="font-weight:bold;"><?= $promedio !== null ? number_format($promedio, 2) : '0.00' ?></span><br>
        <strong>U.C. Registradas:</strong> <span style="font-weight:bold;"><?= $registradas ?></span><br>
        <strong>U.C. Aprobadas:</strong> <span style="font-weight:bold;"><?= $aprobadas ?></span>
      </td>
    </tr>
  </table>
</div>
<?php endif; ?>

<!-- Cierre formal -->
<p class="closing-paragraph">
  Constancia de carácter oficial y fidedigna que se expide a solicitud de la parte interesada, en Ciudad Bolívar a los <?= $diaLetra ?> días del mes de <?= $mesLetra ?> de <?= $anioLetra ?>.
</p>

<!-- Firmas oficiales de validación -->
<div class="signatures-container">
  <div class="signature-block">
    <div class="signature-line"></div>
    <p class="signature-title">Firma <?= $tipo === 'trabajo' ? 'del Docente' : 'del Estudiante' ?></p>
    <p class="signature-sub">Titular de la Cédula</p>
  </div>
  <div class="signature-block">
    <div class="signature-line"></div>
    <p class="signature-title"><?= htmlspecialchars($firma) ?></p>
    <p class="signature-sub">Dirección de Registro Académico y Sello Húmedo</p>
  </div>
</div>

<!-- Pie de página regulatoria -->
<div class="footer-legal">
  Instituto Bíblico Bautista del Sur &middot; Calle Igualdad, Casco Histórico, Ciudad Bolívar, Estado Bolívar, Venezuela &middot; Dirección de Registro y Control de Estudios.
</div>

</body>
</html>
