<?php
/**
 * IBBS — Perfil Profesional PDF
 * api/export_perfil.php        → perfil propio
 * api/export_perfil.php?uid=X  → perfil ajeno (solo admin/superadmin)
 */
session_start();
if (empty($_SESSION['loggedin'])) { header('Location: ../login.php'); exit; }

require_once __DIR__.'/../config/database.php';

$con    = db();
if (!$con) die('Error de conexión a la base de datos.');
$my_uid = (int)($_SESSION['user_id'] ?? 0);
$my_rol = $_SESSION['rol'] ?? 'alumno';

$target_uid = (isset($_GET['uid']) && in_array($my_rol,['superadmin','admin']))
    ? (int)$_GET['uid'] : $my_uid;

// ── Usuario ──────────────────────────────────────────────────────
$u = mysqli_fetch_assoc(mysqli_query($con,"SELECT * FROM usuarios WHERE id=$target_uid LIMIT 1"));
if(!$u) die('Usuario no encontrado.');

$fecha  = date('d/m/Y');
$hora   = date('H:i');
$creado = $u['creado_en'] ? date('d/m/Y', strtotime($u['creado_en'])) : '—';

// ── Perfil vinculado ─────────────────────────────────────────────
$perfil = null; $ptipo = '';
if(in_array($u['rol'],['superadmin','admin','profesor'])){
    $d = mysqli_fetch_assoc(mysqli_query($con,
        "SELECT * FROM docentes WHERE usuario_id=$target_uid
         OR cedula='".mysqli_real_escape_string($con,$u['cedula'])."' LIMIT 1"));
    if($d){ $perfil=$d; $ptipo='docente'; }
} else {
    $a = mysqli_fetch_assoc(mysqli_query($con,
        "SELECT * FROM alumnos WHERE usuario_id=$target_uid
         OR cedula='".mysqli_real_escape_string($con,$u['cedula'])."' LIMIT 1"));
    if($a){ $perfil=$a; $ptipo='alumno'; }
}

// ── Materias del alumno ──────────────────────────────────────────
$materias = [];
if($ptipo==='docente' && $perfil){
    $r = mysqli_query($con,"SELECT m.nombre,m.codigo,m.estado,m.dias,m.hora_inicio,m.hora_fin
         FROM materias m JOIN materia_docente md ON md.materia_id=m.id
         WHERE md.docente_id={$perfil['id']} AND m.activo=1 ORDER BY m.nombre");
    while($f=mysqli_fetch_assoc($r)) $materias[]=$f;
}
if($ptipo==='alumno' && $perfil){
    $r = mysqli_query($con,"SELECT m.nombre,m.codigo,m.estado,ma.nota_final
         FROM materias m JOIN materia_alumno ma ON ma.materia_id=m.id
         WHERE ma.alumno_id={$perfil['id']} AND m.activo=1 ORDER BY m.nombre");
    while($f=mysqli_fetch_assoc($r)) $materias[]=$f;
}

// ── Asistencias ──────────────────────────────────────────────────
$asist = null;
if($ptipo==='alumno' && $perfil){
    $asist = mysqli_fetch_assoc(mysqli_query($con,
        "SELECT SUM(estado='presente') p, SUM(estado='ausente') a,
                SUM(estado='tardanza') t, SUM(estado='justificado') j
         FROM asistencias WHERE alumno_id={$perfil['id']} AND tipo='alumno'"));
}

// ── Stats notas propias ──────────────────────────────────────────
$sn = null;
if($ptipo==='alumno' && $perfil){
    $sn = mysqli_fetch_assoc(mysqli_query($con,
        "SELECT COUNT(*) total,
                SUM(nota_final IS NOT NULL) con_nota,
                SUM(nota_final>=15) aprobadas,
                SUM(nota_final<15 AND nota_final IS NOT NULL) reprobadas,
                AVG(nota_final) promedio
         FROM materia_alumno WHERE alumno_id={$perfil['id']}"));
}

// ── RANKING GENERAL DE ALUMNOS ───────────────────────────────────
// Calcula promedio de cada alumno con al menos 1 nota, ordena de mayor a menor
$ranking      = [];
$posicion     = null;
$top5         = false;
$totalAlumnos = 0;
$medallaTexto = '';
$medallaIcon  = '';

if($ptipo==='alumno' && $perfil){
    $rq = mysqli_query($con,
        "SELECT al.id, CONCAT(al.apellido,', ',al.nombre) nombre,
                AVG(ma.nota_final) promedio,
                SUM(ma.nota_final>=15) aprobadas,
                COUNT(ma.nota_final) con_nota
         FROM alumnos al
         JOIN materia_alumno ma ON ma.alumno_id=al.id
         WHERE al.activo=1 AND ma.nota_final IS NOT NULL
         GROUP BY al.id
         HAVING con_nota > 0
         ORDER BY promedio DESC");

    $pos = 1;
    while($rf=mysqli_fetch_assoc($rq)){
        $ranking[] = $rf;
        if($rf['id'] == $perfil['id']){
            $posicion = $pos;
        }
        $pos++;
    }
    $totalAlumnos = count($ranking);

    if($posicion !== null){
        $top5 = $posicion <= 5;
        if($posicion === 1)      { $medallaIcon='🥇'; $medallaTexto='Mejor Promedio de la Clase'; }
        elseif($posicion === 2)  { $medallaIcon='🥈'; $medallaTexto='2° Mejor Promedio'; }
        elseif($posicion === 3)  { $medallaIcon='🥉'; $medallaTexto='3° Mejor Promedio'; }
        elseif($top5)            { $medallaIcon='⭐'; $medallaTexto='Top 5 de la Clase'; }
    }
}

// ── Top 5 para tabla de honor ────────────────────────────────────
$top5list = array_slice($ranking, 0, 5);

// ── Foto ─────────────────────────────────────────────────────────
// Logo base64 para impresión offline
$logoPath = __DIR__.'/../assets/logo.jpg';
$logoB64  = ($logoPath && file_exists($logoPath))
    ? 'data:image/jpeg;base64,'.base64_encode(file_get_contents($logoPath))
    : '';

$fotoSrc = null;
if($perfil && !empty($perfil['foto'])){
    $fp = __DIR__.'/../'.$perfil['foto'];
    if(file_exists($fp)) $fotoSrc = '../'.$perfil['foto'];
}
if(!$fotoSrc && !empty($u['foto'])){
    $fp = __DIR__.'/../'.$u['foto'];
    if(file_exists($fp)) $fotoSrc = '../'.$u['foto'];
}

// ── Labels ───────────────────────────────────────────────────────
$roles = [
    'superadmin' => 'Director / Administrador Principal',
    'admin'      => 'Administrador del Sistema',
    'profesor'   => 'Docente Instructor',
    'alumno'     => 'Estudiante',
];
$cargo    = $roles[$u['rol']] ?? ucfirst($u['rol']);
$inicial  = strtoupper(mb_substr($perfil ? ($perfil['nombre']??$u['usuario']) : $u['usuario'], 0, 1));
$nombre_c = $perfil
    ? htmlspecialchars(($perfil['apellido']??'').', '.($perfil['nombre']??''))
    : htmlspecialchars($u['usuario']);

// Promedio del alumno actual
$miPromedio = ($sn && $sn['promedio'] !== null) ? (float)$sn['promedio'] : null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Perfil Profesional — <?=$nombre_c?></title>
<style>
/* ── Base ─────────────────────────────────────────── */
*{margin:0;padding:0;box-sizing:border-box;}
html,body{
    width:210mm;min-height:297mm;
    font-family:'Segoe UI',Arial,sans-serif;
    font-size:11.5px;color:#1a4d2e;background:#fff;
}

/* ── Header verde IBBS ────────────────────────────── */
.hdr{
    width:100%;height:150px;
    background:linear-gradient(135deg,#1a4d2e 0%,#1e5c36 55%,#1e5c38 100%);
    clip-path:ellipse(110% 100% at 50% 0%);
    display:flex;align-items:flex-start;
    justify-content:space-between;
    padding:20px 40px 0;
}
.hdr-inst{ color:rgba(255,255,255,.9);line-height:1.9; }
.hdr-inst strong{
    font-size:1rem;text-transform:uppercase;
    letter-spacing:.8px;color:#39ff14;
}
.hdr-inst span{ font-size:.72rem;color:rgba(255,255,255,.6); }
.hdr-right{
    text-align:right;color:rgba(255,255,255,.65);
    font-size:.7rem;line-height:1.9;
}

/* ── Hero: foto + datos ───────────────────────────── */
.hero{
    display:flex;align-items:flex-end;
    gap:22px;padding:0 40px;
    margin-top:-62px;margin-bottom:22px;
}

/* FOTO — contenedor fijo que no desborda */
.avatar-outer{
    width:110px;height:110px;flex-shrink:0;
    border-radius:50%;
    border:4px solid #39ff14;
    background:#1a4d2e;
    overflow:hidden;           /* CLIP de la imagen */
    display:flex;align-items:center;justify-content:center;
    box-shadow:0 6px 24px rgba(26,77,46,.45);
    position:relative;
}
.avatar-outer img{
    position:absolute;inset:0;
    width:100%;height:100%;
    object-fit:cover;          /* Encuadra la foto */
    border-radius:50%;
}
.avatar-ini{
    font-size:2.8rem;font-weight:900;
    color:#39ff14;line-height:1;
    pointer-events:none;
}

/* Medalla superpuesta sobre la foto */
.medalla-badge{
    position:absolute;bottom:-2px;right:-2px;
    width:30px;height:30px;border-radius:50%;
    background:#1a4d2e;border:2px solid #39ff14;
    display:flex;align-items:center;justify-content:center;
    font-size:1rem;z-index:2;
}

.hero-info{ padding-bottom:10px;flex:1;min-width:0; }
.hero-name{
    font-size:1.4rem;font-weight:800;
    color:#1a4d2e;line-height:1.2;
}
.hero-cargo{ font-size:.84rem;color:#2e7d32;margin-top:3px;font-weight:600; }
.hero-rol-badge{
    display:inline-block;margin-top:7px;
    padding:4px 14px;border-radius:20px;
    background:#1a4d2e;color:#39ff14;
    font-size:.7rem;font-weight:800;
    letter-spacing:.8px;text-transform:uppercase;
}
.hero-meta{
    font-size:.73rem;color:#555;
    margin-top:7px;line-height:2;
}
.hero-meta strong{ color:#1a4d2e; }

/* ── Top 1 banner ─────────────────────────────────── */
.top1-banner{
    margin:0 40px 18px;
    background:linear-gradient(135deg,#1a4d2e,#1e5c38);
    border-radius:12px;padding:14px 20px;
    display:flex;align-items:center;gap:14px;
    border-left:5px solid #39ff14;
}
.top1-icon{ font-size:2rem;flex-shrink:0; }
.top1-text strong{
    display:block;font-size:1rem;
    color:#39ff14;font-weight:800;
}
.top1-text span{ font-size:.76rem;color:rgba(255,255,255,.7); }
.top1-pos{
    margin-left:auto;text-align:center;flex-shrink:0;
}
.top1-pos .num{
    font-size:2rem;font-weight:900;
    color:#39ff14;line-height:1;
}
.top1-pos .lbl{ font-size:.6rem;color:rgba(255,255,255,.5);text-transform:uppercase;letter-spacing:.6px; }

/* ── Sección label ────────────────────────────────── */
.sec-lbl{
    font-size:.65rem;text-transform:uppercase;
    letter-spacing:1.3px;font-weight:800;color:#1a4d2e;
    display:flex;align-items:center;gap:8px;
    margin:0 40px 10px;
}
.sec-lbl::after{
    content:'';flex:1;height:1.5px;
    background:linear-gradient(90deg,#39ff14,#f0fdf4);
}

/* ── Cards de datos ───────────────────────────────── */
.cards-grid{
    display:flex;gap:10px;
    padding:0 40px;margin-bottom:20px;flex-wrap:wrap;
}
.info-card{
    flex:1;min-width:130px;
    background:#f5f0e8;
    border:1px solid #d4e8c4;border-radius:10px;
    padding:12px 14px;
}
.ic-lbl{
    font-size:.6rem;text-transform:uppercase;
    letter-spacing:.9px;color:#6b8f71;margin-bottom:4px;
}
.ic-val{
    font-size:.83rem;font-weight:700;
    color:#1a4d2e;word-break:break-word;
}

/* ── Pills de estadísticas ────────────────────────── */
.stats-row{
    display:flex;gap:10px;
    padding:0 40px;margin-bottom:18px;flex-wrap:wrap;
}
.st-pill{
    flex:1;min-width:80px;
    background:#fff;border:1.5px solid #d4e8c4;
    border-radius:12px;padding:12px 10px;text-align:center;
}
.st-num{ font-size:1.7rem;font-weight:900;line-height:1; }
.st-lbl{ font-size:.6rem;text-transform:uppercase;letter-spacing:.8px;color:#6b8f71;margin-top:4px; }

/* ── Posición en clase ────────────────────────────── */
.rank-box{
    margin:0 40px 18px;
    background:#f0fdf4;border:1.5px solid #bbf7d0;
    border-radius:12px;padding:14px 20px;
    display:flex;align-items:center;gap:16px;flex-wrap:wrap;
}
.rank-pos{
    text-align:center;min-width:70px;
}
.rank-pos .rn{
    font-size:2.4rem;font-weight:900;
    color:#1a4d2e;line-height:1;
}
.rank-pos .rl{
    font-size:.6rem;text-transform:uppercase;
    letter-spacing:.7px;color:#6b8f71;
}
.rank-bar-wrap{ flex:1;min-width:120px; }
.rank-bar-lbl{ font-size:.7rem;color:#555;margin-bottom:4px; }
.rank-bar{
    height:10px;background:#d4e8c4;
    border-radius:5px;overflow:hidden;margin-bottom:3px;
}
.rank-fill{ height:100%;border-radius:5px;background:linear-gradient(90deg,#39ff14,#22c55e); }
.rank-bar-sub{ font-size:.65rem;color:#6b8f71; }

/* ── Tabla materias / honor ───────────────────────── */
.tbl-wrap{ padding:0 40px;margin-bottom:16px; }
table{ width:100%;border-collapse:collapse; }
thead tr{ background:linear-gradient(90deg,#1a4d2e,#1e5c38); }
thead th{
    color:#39ff14;font-size:.65rem;font-weight:700;
    text-transform:uppercase;letter-spacing:.5px;
    padding:9px 10px;text-align:left;
    border:1px solid #1e5c38;
}
tbody tr:nth-child(even) td{ background:#f5f0e8; }
tbody tr:nth-child(odd)  td{ background:#fff; }
tbody td{
    padding:6px 10px;font-size:.74rem;
    color:#1a4d2e;vertical-align:middle;
    border:1px solid #d4e8c4;
}
.badge{
    display:inline-block;padding:2px 7px;
    border-radius:20px;font-size:.62rem;font-weight:700;
    text-transform:uppercase;
}
.b-ec{background:#dcfce7;color:#15803d;}
.b-cu{background:#bbf7d0;color:#166534;}
.b-pe{background:#fef9c3;color:#854d0e;}
.b-ap{background:#dcfce7;color:#15803d;}
.b-rp{background:#fee2e2;color:#991b1b;}

/* ── Podio (fila resaltada) ───────────────────────── */
.mi-fila td{ background:#f0fdf4!important;font-weight:700; }
.mi-fila td:first-child{ color:#15803d; }

/* ── Barra asistencia ─────────────────────────────── */
.asist-wrap{ padding:0 40px;margin-bottom:18px; }
.asist-bar-row{ display:flex;align-items:center;gap:10px;margin-bottom:8px; }
.asist-bar{ flex:1;height:11px;background:#d4e8c4;border-radius:6px;overflow:hidden; }
.asist-fill{ height:100%;border-radius:6px; }
.asist-pills{ display:flex;gap:8px;flex-wrap:wrap; }
.ap-pill{
    background:#f5f0e8;border:1px solid #d4e8c4;
    border-radius:8px;padding:6px 12px;text-align:center;min-width:58px;
}
.ap-pill .n{ font-size:1rem;font-weight:800; }
.ap-pill .l{ font-size:.6rem;text-transform:uppercase;letter-spacing:.6px;color:#6b8f71; }

/* ── Firma ────────────────────────────────────────── */
.firma-sec{ display:flex;justify-content:flex-end;padding:22px 40px 0; }
.firma-box{ text-align:center;min-width:240px; }
.firma-line{ border-top:1.5px solid #1a4d2e;margin-bottom:5px; }
.firma-label{ font-size:.74rem;color:#1a4d2e;font-weight:700; }
.firma-sub{ font-size:.65rem;color:#6b8f71;margin-top:2px; }

/* ── Footer wave ──────────────────────────────────── */
.footer-wave{ position:fixed;bottom:0;left:0;width:100%; }
.fw-bar{ width:100%;height:10px;background:linear-gradient(90deg,#1a4d2e,#39ff14,#1a4d2e); }
.fw-body{
    width:100%;height:50px;
    background:linear-gradient(135deg,#1a4d2e,#1e5c36);
    clip-path:ellipse(110% 100% at 50% 100%);
    display:flex;align-items:center;justify-content:center;
}
.fw-txt{ color:rgba(255,255,255,.7);font-size:.65rem;letter-spacing:.4px;margin-top:10px; }

/* ── Botones no-print ─────────────────────────────── */
.no-print{
    position:fixed;top:12px;right:16px;
    z-index:999;display:flex;gap:8px;
}
.btn-print{
    padding:8px 18px;background:#1a4d2e;color:#39ff14;
    border:none;border-radius:8px;cursor:pointer;
    font-size:.82rem;font-weight:700;
    font-family:'Segoe UI',sans-serif;
    box-shadow:0 2px 10px rgba(26,77,46,.4);
}
.btn-print:hover{ background:#1e5c36; }
.btn-back{
    padding:8px 14px;background:#fff;color:#1a4d2e;
    border:1.5px solid #1a4d2e;border-radius:8px;
    font-size:.82rem;font-weight:600;text-decoration:none;
    display:flex;align-items:center;
}

@media print{
    .no-print{ display:none!important; }
    .footer-wave{ position:fixed;bottom:0; }
    @page{ margin:0;size:A4; }
    body{ margin:0; }
}
</style>
</head>
<body>

<!-- Botones -->
<div class="no-print">
    <a class="btn-back" href="../modulo_perfil.php">&#8592; Volver</a>
    <button class="btn-print" onclick="window.print()">&#128424; Imprimir / PDF</button>
</div>

<!-- ══ HEADER ══════════════════════════════════════════════════ -->
<div class="hdr">
    <div class="hdr-inst">
        <strong>Instituto Bíblico Bautista del Sur</strong><br>
        <span>Sistema Académico IBBS &middot; Documento Oficial</span>
    </div>
    <div class="hdr-right">
        Fecha: <?=$fecha?> <?=$hora?><br>
        Emitido por: <?=htmlspecialchars($_SESSION['usuario']??'Sistema')?>
    </div>
</div>

<!-- ══ HERO ════════════════════════════════════════════════════ -->
<div class="hero">
    <div class="avatar-outer">
        <?php if($fotoSrc): ?>
            <img src="<?=$fotoSrc?>" alt="Foto de perfil">
        <?php else: ?>
            <div class="avatar-ini"><?=$inicial?></div>
        <?php endif; ?>
        <?php if($medallaIcon): ?>
            <div class="medalla-badge"><?=$medallaIcon?></div>
        <?php endif; ?>
    </div>

    <div class="hero-info">
        <div class="hero-name"><?=$nombre_c?></div>
        <div class="hero-cargo"><?=$cargo?></div>
        <span class="hero-rol-badge"><?=ucfirst($u['rol'])?></span>
        <div class="hero-meta">
            Usuario: <strong><?=htmlspecialchars($u['usuario'])?></strong>
            &nbsp;&middot;&nbsp; CI: <strong><?=htmlspecialchars($u['cedula'])?></strong>
            &nbsp;&middot;&nbsp; Miembro desde: <strong><?=$creado?></strong>
        </div>
    </div>
</div>

<!-- ══ BANNER TOP (si está entre los 5 mejores) ═══════════════ -->
<?php if($top5 && $posicion !== null && $miPromedio !== null): ?>
<div class="top1-banner">
    <div class="top1-icon"><?=$medallaIcon?></div>
    <div class="top1-text">
        <strong><?=$medallaTexto?></strong>
        <span>Con un promedio de <?=number_format($miPromedio,2)?>/20 — entre los mejores <?=$totalAlumnos > 0 ? $totalAlumnos : '—'?> estudiantes</span>
    </div>
    <div class="top1-pos">
        <div class="num">#<?=$posicion?></div>
        <div class="lbl">Posición</div>
    </div>
</div>
<?php endif; ?>

<!-- ══ DATOS DE CONTACTO ═══════════════════════════════════════ -->
<div class="sec-lbl">Datos de contacto</div>
<div class="cards-grid">
    <div class="info-card">
        <div class="ic-lbl">Correo electrónico</div>
        <div class="ic-val"><?=htmlspecialchars($u['correo'])?></div>
    </div>
    <?php if($perfil): ?>
    <div class="info-card">
        <div class="ic-lbl">Teléfono</div>
        <div class="ic-val"><?=htmlspecialchars($perfil['telefono']??'—')?></div>
    </div>
    <div class="info-card">
        <div class="ic-lbl">Ciudad</div>
        <div class="ic-val"><?=htmlspecialchars($perfil['ciudad']??'—')?></div>
    </div>
    <?php if($ptipo==='docente'): ?>
    <div class="info-card">
        <div class="ic-lbl">Especialidad</div>
        <div class="ic-val"><?=htmlspecialchars($perfil['especialidad']??'—')?></div>
    </div>
    <?php endif; ?>
    <?php endif; ?>
    <div class="info-card">
        <div class="ic-lbl">Estado de cuenta</div>
        <div class="ic-val" style="color:<?=$u['activo']?'#15803d':'#991b1b'?>;">
            <?=$u['activo']?'&#10004; Activo':'&#10008; Inactivo'?>
        </div>
    </div>
</div>

<!-- ══ ESTADÍSTICAS ACADÉMICAS (alumno) ═══════════════════════ -->
<?php if($ptipo==='alumno' && $sn): ?>
<div class="sec-lbl">Estadísticas académicas</div>
<div class="stats-row">
    <div class="st-pill">
        <div class="st-num" style="color:#1a4d2e;"><?=(int)$sn['total']?></div>
        <div class="st-lbl">Materias</div>
    </div>
    <div class="st-pill">
        <div class="st-num" style="color:#15803d;"><?=(int)$sn['aprobadas']?></div>
        <div class="st-lbl">Aprobadas</div>
    </div>
    <div class="st-pill">
        <div class="st-num" style="color:#991b1b;"><?=(int)$sn['reprobadas']?></div>
        <div class="st-lbl">Reprobadas</div>
    </div>
    <?php $prom=$sn['promedio']!==null?(float)$sn['promedio']:null;
          $pc=$prom===null?'#6b8f71':($prom>=15?'#15803d':($prom>=10?'#854d0e':'#991b1b')); ?>
    <div class="st-pill">
        <div class="st-num" style="color:<?=$pc?>;"><?=$prom!==null?number_format($prom,1):'—'?></div>
        <div class="st-lbl">Promedio</div>
    </div>
    <?php if($posicion!==null&&$totalAlumnos>0): ?>
    <div class="st-pill" style="border-color:<?=$top5?'#39ff14':'#d4e8c4'?>;<?=$top5?'background:#f0fdf4;':''?>">
        <div class="st-num" style="color:<?=$top5?'#15803d':'#1a4d2e'?>;">#<?=$posicion?></div>
        <div class="st-lbl">Ranking</div>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- ══ POSICIÓN EN CLASE ══════════════════════════════════════ -->
<?php if($posicion!==null&&$totalAlumnos>0): ?>
<div class="sec-lbl">Posición en el ranking general</div>
<div class="rank-box">
    <div class="rank-pos">
        <div class="rn" style="color:<?=$posicion===1?'#39ff14':($posicion<=3?'#15803d':'#1a4d2e')?>;">
            #<?=$posicion?>
        </div>
        <div class="rl">de <?=$totalAlumnos?></div>
    </div>
    <div class="rank-bar-wrap">
        <?php
        $pctRank = $totalAlumnos > 1
            ? round((($totalAlumnos - $posicion) / ($totalAlumnos - 1)) * 100)
            : 100;
        $barColor = $posicion===1?'#39ff14':($posicion<=3?'#22c55e':($posicion<=5?'#4ade80':'#86efac'));
        ?>
        <div class="rank-bar-lbl">
            Percentil <?=$pctRank?>
            <?php if($top5): ?>
            &nbsp;<span style="color:#15803d;font-weight:800;"><?=$medallaIcon?> <?=$medallaTexto?></span>
            <?php endif; ?>
        </div>
        <div class="rank-bar">
            <div class="rank-fill" style="width:<?=$pctRank?>%;background:<?=$barColor?>;"></div>
        </div>
        <div class="rank-bar-sub">Supera al <?=$pctRank?>% de los estudiantes con notas registradas</div>
    </div>
    <?php if($miPromedio!==null): ?>
    <div style="text-align:center;min-width:70px;">
        <div style="font-size:1.5rem;font-weight:900;color:#15803d;line-height:1;"><?=number_format($miPromedio,2)?></div>
        <div style="font-size:.6rem;text-transform:uppercase;letter-spacing:.6px;color:#6b8f71;margin-top:3px;">Promedio</div>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- ══ TABLA DE HONOR — Top 5 ════════════════════════════════ -->
<?php if($ptipo==='alumno' && count($top5list)>0): ?>
<div class="sec-lbl">Tabla de honor &mdash; Top <?=count($top5list)?></div>
<div class="tbl-wrap"><table>
    <thead>
        <tr>
            <th style="text-align:center;width:40px;">Pos.</th>
            <th>Estudiante</th>
            <th style="text-align:center;">Promedio</th>
            <th style="text-align:center;">Aprobadas</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach($top5list as $ri=>$rw):
            $esMi = ($perfil && $rw['id']==$perfil['id']);
            $icons = ['🥇','🥈','🥉','⭐','⭐'];
            $ic = $icons[$ri] ?? '';
            $rprom = (float)$rw['promedio'];
            $rpc = $rprom>=15?'#15803d':($rprom>=10?'#854d0e':'#991b1b');
        ?>
        <tr <?=$esMi?'class="mi-fila"':''?>>
            <td style="text-align:center;font-size:1rem;"><?=$ic?> <?=$ri+1?></td>
            <td>
                <?=htmlspecialchars($rw['nombre'])?>
                <?php if($esMi): ?>
                <span style="background:#dcfce7;color:#15803d;font-size:.6rem;font-weight:800;padding:1px 6px;border-radius:10px;margin-left:4px;text-transform:uppercase;letter-spacing:.4px;">Tú</span>
                <?php endif; ?>
            </td>
            <td style="text-align:center;font-weight:800;color:<?=$rpc?>;"><?=number_format($rprom,2)?></td>
            <td style="text-align:center;"><?=(int)$rw['aprobadas']?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table></div>
<?php endif; ?>

<!-- ══ MATERIAS INSCRITAS ════════════════════════════════════ -->
<?php if($ptipo==='alumno' && $materias): ?>
<div class="sec-lbl">Materias inscritas (<?=count($materias)?>)</div>
<div class="tbl-wrap"><table>
    <thead>
        <tr>
            <th>N°</th><th>Materia</th><th>Código</th><th>Estado</th>
            <th style="text-align:center;">Nota</th>
            <th style="text-align:center;">Resultado</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach($materias as $i=>$m):
            $eb=['pendiente'=>'b-pe','en_curso'=>'b-ec','culminada'=>'b-cu'][$m['estado']]??'b-pe';
            $nv=$m['nota_final']!==null?(float)$m['nota_final']:null;
            $nc=$nv===null?'#6b8f71':($nv>=15?'#15803d':($nv>=10?'#854d0e':'#991b1b'));
        ?>
        <tr>
            <td style="text-align:center;font-weight:700;color:#1a4d2e;"><?=$i+1?></td>
            <td><strong><?=htmlspecialchars($m['nombre'])?></strong></td>
            <td style="font-family:monospace;color:#6b8f71;"><?=htmlspecialchars($m['codigo'])?></td>
            <td><span class="badge <?=$eb?>"><?=ucfirst(str_replace('_',' ',$m['estado']))?></span></td>
            <td style="text-align:center;font-weight:800;color:<?=$nc?>;font-size:.88rem;"><?=$nv!==null?number_format($nv,1):'—'?></td>
            <td style="text-align:center;">
                <?php if($nv!==null): ?>
                    <?php $rbadge=$nv>=15?'b-ap':'b-rp'; $rlabel=$nv>=15?'Aprobado':'Reprobado'; ?>
                    <span class="badge <?=$rbadge?>"><?=$rlabel?></span>
                <?php else: ?>
                    <span style="color:#6b8f71;">Pendiente</span>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table></div>

<?php elseif($ptipo==='docente' && $materias): ?>
<!-- ══ MATERIAS DOCENTE ════════════════════════════════════ -->
<div class="sec-lbl">Materias asignadas (<?=count($materias)?>)</div>
<div class="tbl-wrap"><table>
    <thead>
        <tr><th>N°</th><th>Materia</th><th>Código</th><th>Días</th><th>Horario</th><th>Estado</th></tr>
    </thead>
    <tbody>
        <?php foreach($materias as $i=>$m):
            $eb=['pendiente'=>'b-pe','en_curso'=>'b-ec','culminada'=>'b-cu'][$m['estado']]??'b-pe';
            $h=($m['hora_inicio']&&$m['hora_fin'])?substr($m['hora_inicio'],0,5).'–'.substr($m['hora_fin'],0,5):'—';
        ?>
        <tr>
            <td style="text-align:center;font-weight:700;"><?=$i+1?></td>
            <td><strong><?=htmlspecialchars($m['nombre'])?></strong></td>
            <td style="font-family:monospace;color:#6b8f71;"><?=htmlspecialchars($m['codigo'])?></td>
            <td><?=htmlspecialchars($m['dias']??'—')?></td>
            <td><?=$h?></td>
            <td><span class="badge <?=$eb?>"><?=ucfirst(str_replace('_',' ',$m['estado']))?></span></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table></div>
<?php endif; ?>

<!-- ══ ASISTENCIAS ══════════════════════════════════════════ -->
<?php if($ptipo==='alumno' && $asist):
$tA=($asist['p']??0)+($asist['a']??0)+($asist['t']??0)+($asist['j']??0);
$pA=$tA?round($asist['p']/$tA*100):0;
$bC=$pA>=75?'#15803d':($pA>=50?'#854d0e':'#991b1b');
?>
<div class="sec-lbl">Registro de asistencias</div>
<div class="asist-wrap">
    <div class="asist-bar-row">
        <span style="font-size:.72rem;color:#555;white-space:nowrap;">Asistencia general</span>
        <div class="asist-bar">
            <div class="asist-fill" style="width:<?=$pA?>%;background:<?=$bC?>;"></div>
        </div>
        <strong style="font-size:.84rem;color:<?=$bC?>;min-width:34px;"><?=$pA?>%</strong>
    </div>
    <div class="asist-pills">
        <div class="ap-pill"><div class="n" style="color:#15803d;"><?=(int)($asist['p']??0)?></div><div class="l">Presentes</div></div>
        <div class="ap-pill"><div class="n" style="color:#991b1b;"><?=(int)($asist['a']??0)?></div><div class="l">Ausentes</div></div>
        <div class="ap-pill"><div class="n" style="color:#854d0e;"><?=(int)($asist['t']??0)?></div><div class="l">Tardanzas</div></div>
        <div class="ap-pill"><div class="n" style="color:#6d28d9;"><?=(int)($asist['j']??0)?></div><div class="l">Justificados</div></div>
    </div>
</div>
<?php endif; ?>

<!-- ══ FIRMA ════════════════════════════════════════════════ -->
<div class="firma-sec">
    <div class="firma-box">
        <div class="firma-line"></div>
        <div class="firma-label">Firma de La Directora Encargada</div>
        <div class="firma-sub">Instituto Bíblico Bautista del Sur</div>
    </div>
</div>

<!-- ══ FOOTER WAVE ════════════════════════════════════════ -->
<div class="footer-wave">
    <div class="fw-bar"></div>
    <div class="fw-body">
        <div class="fw-txt">Instituto Bíblico Bautista del Sur &middot; 1997–<?=date('Y')?> &middot; Sistema Académico IBBS</div>
    </div>
</div>

</body>
</html>
