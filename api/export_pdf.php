<?php
// api/export_pdf.php — PDF tabla de notas con upload de logo
// Escala 0-20 · Aprueba con 15
require_once __DIR__.'/../config/bootstrap.php';
if (empty($_SESSION['loggedin'])) { header('Location: ../login.php'); exit; }

$mid = (int)($_GET['materia_id'] ?? 0);
if (!$mid) die('Materia no especificada.');

$con = db();
if (!$con) die('Error de conexión a la base de datos.');

$mat      = mysqli_fetch_assoc(mysqli_query($con,"SELECT * FROM materias WHERE id=$mid"));
if (!$mat) die('Materia no encontrada.');

$docentes = [];
$rd = mysqli_query($con,"SELECT d.nombre,d.apellido,d.cedula,d.especialidad FROM docentes d JOIN materia_docente md ON md.docente_id=d.id WHERE md.materia_id=$mid");
while($f=mysqli_fetch_assoc($rd)) $docentes[]=$f;

$alumnos = [];
$ra = mysqli_query($con,"SELECT a.nombre,a.apellido,a.cedula,a.ciudad,ma.nota_final,ma.nota_fecha FROM alumnos a JOIN materia_alumno ma ON ma.alumno_id=a.id WHERE ma.materia_id=$mid ORDER BY a.apellido,a.nombre");
while($f=mysqli_fetch_assoc($ra)) $alumnos[]=$f;

$aprobados  = count(array_filter($alumnos, fn($a) => $a['nota_final'] !== null && (float)$a['nota_final'] >= 15));
$reprobados = count(array_filter($alumnos, fn($a) => $a['nota_final'] !== null && (float)$a['nota_final'] < 15));
$sin_nota   = count(array_filter($alumnos, fn($a) => $a['nota_final'] === null));

$exportador  = $_SESSION['usuario'] ?? 'Admin';
$fecha_exp   = date('d/m/Y H:i');
$promedio    = 0;
$con_nota    = array_filter($alumnos, fn($a) => $a['nota_final'] !== null);
if (count($con_nota)) $promedio = round(array_sum(array_column(array_values($con_nota),'nota_final')) / count($con_nota), 1);

mysqli_close($con);
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Acta de Calificaciones — <?=htmlspecialchars($mat['nombre'])?></title>
<style>
@page { size: A4; margin: 1.8cm 1.5cm; }
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Segoe UI', Arial, sans-serif; font-size: 11px; color: #1a1a1a; background: #fff; }

/* ── NO-PRINT controls ──────────────────────────────────── */
.no-print {
  background: #1a4d2e; color: #fff; padding: .7rem 1.2rem;
  display: flex; justify-content: space-between; align-items: center;
  gap: 1rem; flex-wrap: wrap; margin-bottom: 1.2rem;
}
.no-print h2 { font-size: .9rem; letter-spacing: 2px; color: #39ff14; }
.no-print .controls { display: flex; gap: .7rem; align-items: center; flex-wrap: wrap; }
.no-print label { font-size: .78rem; color: rgba(255,255,255,.6); }
.no-print input[type=file] { font-size: .78rem; color: #fff; background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.15); border-radius: 6px; padding: 4px 8px; cursor: pointer; }
.btn-print { background: #39ff14; color: #1a4d2e; border: none; padding: .5rem 1.3rem; border-radius: 7px; font-weight: 700; font-size: .82rem; cursor: pointer; letter-spacing: .5px; }
.btn-print:hover { background: #2ecc10; }

/* ── Membrete ────────────────────────────────────────────── */
.header {
  display: flex; justify-content: space-between; align-items: center;
  padding-bottom: 1rem; margin-bottom: 1rem;
  border-bottom: 2.5px solid #1a4d2e;
  gap: 1rem;
}
.logo-area {
  display: flex; align-items: center; gap: .9rem;
  flex: 1;
}
.logo-placeholder {
  width: 64px; height: 64px;
  border: 2px dashed #c0b8a8; border-radius: 10px;
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  background: #f9f5ee; flex-shrink: 0; overflow: hidden;
  cursor: pointer; transition: border-color .2s;
}
.logo-placeholder:hover { border-color: #888; }
.logo-placeholder img { width: 100%; height: 100%; object-fit: contain; display: none; border-radius: 8px; }
.logo-placeholder .lbl { font-size: 8.5px; color: #aaa; text-align: center; line-height: 1.4; padding: 4px; }
.institution-info strong { font-size: 1.2rem; letter-spacing: 2px; font-weight: 900; color: #1a4d2e; display: block; }
.institution-info span { font-size: 9px; color: #888; letter-spacing: 1px; text-transform: uppercase; }
.doc-info { text-align: right; font-size: 9.5px; color: #666; line-height: 1.7; }
.doc-info strong { color: #1a4d2e; font-size: 10px; }

/* ── Materia info bar ────────────────────────────────────── */
.materia-bar {
  background: #f5f0e8; border-radius: 8px; padding: .8rem 1.1rem;
  margin-bottom: 1.2rem; display: flex; gap: 2rem; flex-wrap: wrap;
  border-left: 4px solid #1a4d2e;
}
.materia-bar .item strong { display: block; font-size: 8px; text-transform: uppercase; letter-spacing: 1.2px; color: #999; margin-bottom: 1px; }
.materia-bar .item span   { font-size: 11.5px; font-weight: 600; color: #1a4d2e; }

/* ── Resumen stats ────────────────────────────────────────── */
.stats-row { display: flex; gap: .7rem; margin-bottom: 1.2rem; }
.stat-box {
  flex: 1; text-align: center; padding: .6rem;
  background: #fdfaf4; border: 1px solid #e0d8c8; border-radius: 7px;
}
.stat-box .sv { font-size: 1.5rem; font-weight: 800; line-height: 1; }
.stat-box .sk { font-size: 8px; text-transform: uppercase; letter-spacing: 1px; color: #888; margin-top: 2px; }
.stat-box.green .sv { color: #16a34a; }
.stat-box.red   .sv { color: #dc2626; }
.stat-box.blue  .sv { color: #2563eb; }
.stat-box.ink   .sv { color: #1a4d2e; }

/* ── Tabla ────────────────────────────────────────────────── */
table { width: 100%; border-collapse: collapse; font-size: 10.5px; }
th {
  background: #1a4d2e; color: #39ff14;
  padding: 7px 10px; text-align: center;
  font-size: 9px; text-transform: uppercase; letter-spacing: .9px; font-weight: 700;
}
th.left { text-align: left; }
td { padding: 6.5px 10px; border-bottom: 1px solid #ece5d8; vertical-align: middle; text-align: center; }
td.left { text-align: left; }
tr:nth-child(even) td { background: #fdfaf4; }
tr:last-child td { border-bottom: 2px solid #1a4d2e; }
.nota { font-weight: 800; font-size: 12.5px; }
.nota.aprobado  { color: #16a34a; }
.nota.limite    { color: #ca8a04; }
.nota.reprobado { color: #dc2626; }
.nota.vacio     { color: #bbb; }
.badge { display: inline-block; padding: 2px 9px; border-radius: 20px; font-size: 8.5px; font-weight: 700; letter-spacing: .4px; }
.badge.ap { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
.badge.rp { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }
.badge.nd { background: #f3f4f6; color: #9ca3af; border: 1px solid #e5e7eb; }

/* ── Leyenda ──────────────────────────────────────────────── */
.leyenda { display: flex; gap: 1.5rem; margin: .8rem 0 1.2rem; font-size: 9px; color: #666; }
.leyenda span { display: flex; align-items: center; gap: 4px; }
.dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }

/* ── Firmas ───────────────────────────────────────────────── */
.firmas { display: flex; gap: 2.5rem; margin-top: 2.5rem; flex-wrap: wrap; }
.firma { min-width: 140px; flex: 1; }
.firma-line { border-top: 1px solid #555; padding-top: .4rem; font-size: 9px; color: #444; text-align: center; }

/* ── Footer ───────────────────────────────────────────────── */
.doc-footer {
  display: flex; justify-content: space-between;
  font-size: 8.5px; color: #aaa; border-top: 1px solid #e0d8c8;
  padding-top: .5rem; margin-top: 1rem;
}

@media print {
  .no-print { display: none !important; }
  body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
}
</style>
</head>
<body>

<!-- ══ BARRA DE CONTROLES (solo pantalla) ══════════════════ -->
<div class="no-print">
  <h2>IBBS — Vista Previa · Acta de Calificaciones</h2>
  <div class="controls">
    <div>
      <label>Logo institucional:</label><br>
      <input type="file" id="logoInput" accept="image/*" onchange="cargarLogo(this)">
    </div>
    <button class="btn-print" onclick="window.print()">🖨 Imprimir / Guardar PDF</button>
  </div>
</div>

<!-- ══ DOCUMENTO IMPRIMIBLE ════════════════════════════════ -->
<div class="header">
  <div class="logo-area">
    <!-- Logo: clic para subir -->
    <div class="logo-placeholder" id="logoBox" onclick="document.getElementById('logoInput').click()" title="Clic para agregar logo">
      <img id="logoImg" src="" alt="Logo">
      <div class="lbl" id="logoLbl">📷<br>Agregar<br>logo</div>
    </div>
    <div class="institution-info">
      <strong>IBBS</strong>
      <span>Sistema Académico</span>
      <div style="margin-top:.3rem;font-size:10px;font-weight:600;color:#333;">Acta Oficial de Calificaciones</div>
    </div>
  </div>
  <div class="doc-info">
    <strong><?=htmlspecialchars($mat['nombre'])?></strong><br>
    Código: <?=htmlspecialchars($mat['codigo'])?><br>
    <?php if($mat['dias']): ?>Días: <?=htmlspecialchars($mat['dias'])?><br><?php endif; ?>
    <?php if($mat['hora_inicio']): ?>Horario: <?=substr($mat['hora_inicio'],0,5)?><?=$mat['hora_fin']?' – '.substr($mat['hora_fin'],0,5):''?><br><?php endif; ?>
    Exportado por: <strong><?=htmlspecialchars($exportador)?></strong><br>
    Fecha: <?=$fecha_exp?>
  </div>
</div>

<!-- Barra info materia -->
<div class="materia-bar">
  <div class="item"><strong>Materia</strong><span><?=htmlspecialchars($mat['nombre'])?></span></div>
  <div class="item"><strong>Código</strong><span><?=htmlspecialchars($mat['codigo'])?></span></div>
  <?php if($docentes): ?>
  <div class="item"><strong>Docente(s)</strong><span><?=implode(' / ', array_map(fn($d)=>$d['nombre'].' '.$d['apellido'], $docentes))?></span></div>
  <?php endif; ?>
  <div class="item"><strong>Escala</strong><span>0 – 20 · Aprueba ≥ 15</span></div>
  <div class="item"><strong>Estado</strong><span><?=ucfirst(str_replace('_',' ',$mat['estado']??'en_curso'))?></span></div>
  <div class="item"><strong>Total inscritos</strong><span><?=count($alumnos)?></span></div>
</div>

<!-- Resumen estadístico -->
<div class="stats-row">
  <div class="stat-box ink"><div class="sv"><?=count($alumnos)?></div><div class="sk">Inscritos</div></div>
  <div class="stat-box green"><div class="sv"><?=$aprobados?></div><div class="sk">Aprobados</div></div>
  <div class="stat-box red"><div class="sv"><?=$reprobados?></div><div class="sk">Reprobados</div></div>
  <div class="stat-box blue"><div class="sv"><?=$promedio ?: '—'?></div><div class="sk">Promedio</div></div>
  <div class="stat-box"><div class="sv" style="color:#9ca3af;"><?=$sin_nota?></div><div class="sk">Sin nota</div></div>
</div>

<!-- Leyenda colores -->
<div class="leyenda">
  <span><span class="dot" style="background:#16a34a;"></span> Aprobado (≥ 15)</span>
  <span><span class="dot" style="background:#ca8a04;"></span> Reprobado entre 10 y 14</span>
  <span><span class="dot" style="background:#dc2626;"></span> Reprobado (< 10)</span>
  <span><span class="dot" style="background:#bbb;"></span> Sin nota registrada</span>
</div>

<!-- Tabla principal -->
<table>
  <thead>
    <tr>
      <th class="left" style="width:28px;">#</th>
      <th class="left" style="min-width:160px;">Apellido, Nombre</th>
      <th class="left" style="width:90px;">Cédula</th>
      <th class="left" style="width:80px;">Ciudad</th>
      <th style="width:70px;">Nota Final</th>
      <th style="width:80px;">Estado</th>
      <th style="width:70px;">Fecha</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($alumnos as $i => $al):
      $nv  = $al['nota_final'] !== null ? (float)$al['nota_final'] : null;
      $ncls = $nv === null ? 'vacio' : ($nv >= 15 ? 'aprobado' : ($nv >= 10 ? 'limite' : 'reprobado'));
      $bcls = $nv === null ? 'nd'    : ($nv >= 15 ? 'ap' : 'rp');
      $blbl = $nv === null ? 'Sin nota' : ($nv >= 15 ? 'Aprobado' : 'Reprobado');
    ?>
    <tr>
      <td><?=$i+1?></td>
      <td class="left"><strong><?=htmlspecialchars($al['apellido'])?></strong>, <?=htmlspecialchars($al['nombre'])?></td>
      <td class="left" style="font-size:9.5px;"><?=htmlspecialchars($al['cedula'])?></td>
      <td class="left" style="font-size:9px;color:#666;"><?=htmlspecialchars($al['ciudad']??'—')?></td>
      <td><span class="nota <?=$ncls?>"><?=$nv !== null ? number_format($nv, 1) : '—'?></span></td>
      <td><span class="badge <?=$bcls?>"><?=$blbl?></span></td>
      <td style="font-size:9px;color:#888;"><?=$al['nota_fecha'] ?? '—'?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($alumnos)): ?>
    <tr><td colspan="7" style="text-align:center;padding:1.5rem;color:#aaa;font-style:italic;">Sin alumnos inscritos.</td></tr>
    <?php endif; ?>
  </tbody>
</table>

<!-- Firmas -->
<div class="firmas">
  <?php foreach ($docentes as $d): ?>
  <div class="firma">
    <div class="firma-line">
      <strong><?=htmlspecialchars($d['nombre'].' '.$d['apellido'])?></strong><br>
      Docente<?=$d['especialidad']?' · '.$d['especialidad']:''?>
    </div>
  </div>
  <?php endforeach; ?>
  <div class="firma">
    <div class="firma-line">
      <strong><?=htmlspecialchars($exportador)?></strong><br>
      Administrador del Sistema
    </div>
  </div>
  <div class="firma">
    <div class="firma-line" style="color:#bbb;">
      ____________________________<br>
      Sello Institucional
    </div>
  </div>
</div>

<div class="doc-footer">
  <span>IBBS Sistema Académico · <?=$fecha_exp?></span>
  <span>Materia: <?=htmlspecialchars($mat['nombre'])?> · Código: <?=htmlspecialchars($mat['codigo'])?> · Total: <?=count($alumnos)?> alumnos</span>
</div>

<script>
// Preview del logo antes de imprimir
function cargarLogo(input) {
  if (!input.files || !input.files[0]) return;
  const reader = new FileReader();
  reader.onload = function(e) {
    const img = document.getElementById('logoImg');
    const lbl = document.getElementById('logoLbl');
    img.src = e.target.result;
    img.style.display = 'block';
    lbl.style.display = 'none';
    // Guardar en sessionStorage para persistir en vista previa
    try { sessionStorage.setItem('ibbs_logo', e.target.result); } catch(ex){}
  };
  reader.readAsDataURL(input.files[0]);
}

// Restaurar logo si ya fue cargado antes
(function() {
  try {
    const saved = sessionStorage.getItem('ibbs_logo');
    if (saved) {
      const img = document.getElementById('logoImg');
      img.src = saved;
      img.style.display = 'block';
      document.getElementById('logoLbl').style.display = 'none';
    }
  } catch(e) {}
})();
</script>
</body>
</html>
