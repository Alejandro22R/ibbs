<?php
if(!isset($page_title)) $page_title='IBBS';
if(!isset($active_link)) $active_link='';
require_once __DIR__.'/../config/bootstrap.php';
if(empty($_SESSION['loggedin'])){header('Location: login.php');exit;}
$_u   = $_SESSION['usuario']??'Usuario';
$_rol = $_SESSION['rol']??'profesor';
$_uid = (int)($_SESSION['user_id']??0);
$_ini = strtoupper(mb_substr($_u,0,1));
$_foto= $_SESSION['foto']??null;

// ── Permisos por rol ─────────────────────────────────────────
function can($perm){
    $r = $_SESSION['rol']??'profesor';
    $perms = [
        'superadmin' => ['all'],
        'admin'      => ['view_all','edit','create','delete_data','graficos','backup_export'],
        'profesor'   => ['view_all','edit','graficos'],
    ];
    $rp = $perms[$r] ?? [];
    if(in_array('all',$rp)) return true;
    return in_array($perm,$rp);
}
?><!DOCTYPE html>
<html lang="es-VE">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<meta name="csrf-token" content="<?=htmlspecialchars(csrf_token())?>">
<title><?=htmlspecialchars($page_title)?> — IBBS</title>
<link rel="stylesheet" href="assets/ibbs.css">
<link rel="stylesheet" href="assets/libs/boxicons/boxicons.min.css">
<script src="assets/libs/chart.umd.min.js"></script>
<!-- Apply theme BEFORE paint - zero flash -->
<script>
  (function(){
    if(localStorage.getItem('ibbs_theme')==='dark'){
      document.documentElement.setAttribute('data-theme','dark');
      document.documentElement.style.background='#0f1612';
    }
  })();
</script>
<style>
@media(max-width:640px){ .search-hint-text { display:none; } }
</style>
</head>
<body>
<a href="#main" class="skip-link">Saltar al contenido principal</a>
<div id="toast"><span class="tdot"></span><span id="tmsg"></span></div>
<!-- Overlay para cerrar sidebar en móvil -->
<div id="sb-overlay" onclick="closeMobileSB()"></div>
<nav id="sb" role="navigation" aria-label="Menú principal">
  <a href="index.php" class="sb-brand">
    <div class="sb-logo" style="background:none;border-radius:50%;overflow:hidden;width:38px;height:38px;flex-shrink:0;">
      <img src="assets/logo.jpg" alt="IBBS" style="width:100%;height:100%;object-fit:cover;">
    </div>
    <div class="sb-wordmark"><strong>IBBS</strong><small>Sistema Académico</small></div>
  </a>
  <ul class="sb-nav">
    <li><a href="index.php" class="sb-link <?=$active_link==='inicio'?'act':''?>"><i class="bx bx-grid-alt"></i><span class="sb-lbl">Inicio</span></a></li>

    <?php if(in_array($_rol,['superadmin','admin'])): ?>
    <div class="sb-section">Académico</div>
    <li><a href="modulo_materias.php" class="sb-link <?=$active_link==='materias'?'act':''?>"><i class="bx bx-book-open"></i><span class="sb-lbl">Materias</span></a></li>
    <li><a href="modulo_docentes.php" class="sb-link <?=$active_link==='docentes'?'act':''?>"><i class="bx bx-chalkboard"></i><span class="sb-lbl">Docentes</span></a></li>
    <li><a href="modulo_alumnos.php" class="sb-link <?=$active_link==='alumnos'?'act':''?>"><i class="bx bx-group"></i><span class="sb-lbl">Alumnos</span></a></li>
    <li><a href="modulo_inscripciones.php" class="sb-link <?=$active_link==='inscripciones'?'act':''?>"><i class="bx bx-user-plus"></i><span class="sb-lbl">Inscripciones</span></a></li>
    <li><a href="modulo_asistencias.php" class="sb-link <?=$active_link==='asistencias'?'act':''?>"><i class="bx bx-check-square"></i><span class="sb-lbl">Asistencias</span></a></li>
    <?php endif; ?>

    <div class="sb-section">Calificaciones</div>
    <?php if(in_array($_rol,['superadmin','admin'])): ?>
    <li><a href="modulo_notas.php" class="sb-link <?=$active_link==='notas'?'act':''?>"><i class="bx bx-edit-alt"></i><span class="sb-lbl">Cargar Notas</span></a></li>
    <?php elseif($_rol==='profesor'): ?>
    <li><a href="modulo_notas.php" class="sb-link <?=$active_link==='notas'?'act':''?>"><i class="bx bx-edit-alt"></i><span class="sb-lbl">Cargar Notas</span></a></li>
    <?php endif; ?>
    <?php if(in_array($_rol,['superadmin','admin','profesor'])): ?>
    <li><a href="modulo_aula.php" class="sb-link <?=$active_link==='aula'?'act':''?>"><i class="bx bx-chalkboard"></i><span class="sb-lbl">Aula Virtual</span></a></li>
    <li><a href="modulo_grabaciones.php" class="sb-link <?=$active_link==='grabaciones'?'act':''?>"><i class="bx bx-video"></i><span class="sb-lbl">Clases Grabadas</span></a></li>
    <li><a href="modulo_vivo.php" class="sb-link <?=$active_link==='vivo'?'act':''?>"><i class="bx bx-broadcast"></i><span class="sb-lbl">Clases en Vivo</span></a></li>
    <?php endif; ?>
    <li><a href="modulo_record.php" class="sb-link <?=$active_link==='record'?'act':''?>"><i class="bx bx-bar-chart-alt-2"></i><span class="sb-lbl">Record Académico</span></a></li>

    <?php if(in_array($_rol,['superadmin','admin'])): ?>
    <div class="sb-section">Reportes</div>
    <li><a href="modulo_buscar.php" class="sb-link <?=$active_link==='buscar'?'act':''?>"><i class="bx bx-search-alt-2"></i><span class="sb-lbl">Buscar Personal</span></a></li>
    <?php endif; ?>

    <div class="sb-section">Herramientas</div>
    <?php if(in_array($_rol,['superadmin','admin'])): ?>
    <li><a href="modulo_herramientas.php" class="sb-link <?=$active_link==='herramientas'?'act':''?>"><i class="bx bx-bell"></i><span class="sb-lbl">Herramientas</span></a></li>
    <?php endif; ?>
    <li><a href="modulo_visor_pdf.php" class="sb-link <?=$active_link==='visor_pdf'?'act':''?>"><i class="bx bx-file"></i><span class="sb-lbl">Visor PDF</span></a></li>

    <?php if(in_array($_rol,['superadmin','admin'])): ?>
    <div class="sb-section">Sistema</div>
    <?php if($_rol==='superadmin'): ?>
    <li><a href="modulo_usuarios.php" class="sb-link <?=$active_link==='usuarios'?'act':''?>"><i class="bx bx-shield-quarter"></i><span class="sb-lbl">Usuarios</span></a></li>
    <li><a href="modulo_historial.php" class="sb-link <?=$active_link==='historial'?'act':''?>"><i class="bx bx-history"></i><span class="sb-lbl">Historial</span></a></li>
    <?php endif; ?>
    <li><a href="modulo_backup.php" class="sb-link <?=$active_link==='backup'?'act':''?>"><i class="bx bx-data"></i><span class="sb-lbl">Respaldo BD</span></a></li>
    <?php endif; ?>

    <li><a href="assets/ibbs_ayuda.pdf" target="_blank" class="sb-link"><i class="bx bx-help-circle"></i><span class="sb-lbl">Ayuda</span></a></li>
  </ul>
  <div class="sb-bottom">
    <a href="modulo_perfil.php" class="sb-action" style="text-decoration:none;">
      <?php if($_foto && file_exists(__DIR__.'/'.$_foto)): ?>
        <img src="<?=htmlspecialchars($_foto)?>" style="width:24px;height:24px;border-radius:50%;object-fit:cover;flex-shrink:0;">
      <?php else: ?>
        <i class="bx bx-user-circle"></i>
      <?php endif; ?>
      <span class="sb-lbl">Mi Perfil</span>
    </a>
    <form action="cerrar_sesion.php" method="post">
      <button type="submit" class="sb-action"><i class="bx bx-log-out"></i><span class="sb-lbl">Salir</span></button>
    </form>
  </div>
</nav>
<button id="toggler" onclick="toggleSB()"><svg id="togico" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg></button>
<main id="main">
<div class="topbar">
  <div><h1 class="page-title"><?=htmlspecialchars($page_title)?><em><?=htmlspecialchars($page_sub??'')?></em></h1></div>
  <div style="display:flex;align-items:center;gap:.6rem;">
    <!-- Hamburger — solo visible en móvil -->
    <button id="mobileMenuBtn" onclick="toggleMobileSB()" title="Menú"
      style="display:none;background:none;border:1.5px solid var(--border);border-radius:9px;padding:.45rem .55rem;cursor:pointer;color:var(--ink);align-items:center;justify-content:center;">
      <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
        <line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/>
      </svg>
    </button>

    <!-- Dark mode pill toggle -->
    <button id="themeToggle" onclick="toggleTheme()" title="Modo claro / oscuro" aria-label="Cambiar tema">
      <!-- luna (visible en modo claro) -->
      <svg class="t-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
      <!-- sol (visible en modo oscuro) -->
      <svg class="t-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><line x1="12" y1="2" x2="12" y2="4"/><line x1="12" y1="20" x2="12" y2="22"/><line x1="4.93" y1="4.93" x2="6.34" y2="6.34"/><line x1="17.66" y1="17.66" x2="19.07" y2="19.07"/><line x1="2" y1="12" x2="4" y2="12"/><line x1="20" y1="12" x2="22" y2="12"/><line x1="4.93" y1="19.07" x2="6.34" y2="17.66"/><line x1="17.66" y1="6.34" x2="19.07" y2="4.93"/></svg>
    </button>
    <!-- Notification bell -->
    <button id="notifBell" onclick="window.location='modulo_herramientas.php'" title="Notificaciones"
      style="position:relative;background:none;border:1.5px solid var(--border);border-radius:9px;padding:.45rem .6rem;cursor:pointer;display:flex;align-items:center;color:var(--ink);">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
      <span id="notifCount" style="display:none;position:absolute;top:-5px;right:-5px;background:#ef4444;color:#fff;border-radius:50%;width:17px;height:17px;font-size:.55rem;font-weight:700;display:flex;align-items:center;justify-content:center;line-height:1;"></span>
    </button>
  </div>
  <div class="user-pill">
    <?php if($_foto && file_exists(__DIR__.'/'.$_foto)): ?>
      <img src="<?=htmlspecialchars($_foto)?>" style="width:30px;height:30px;border-radius:50%;object-fit:cover;">
    <?php else: ?>
      <div class="user-ava"><?=$_ini?></div>
    <?php endif; ?>
    <span class="user-name"><?=htmlspecialchars($_u)?></span>
    <span class="user-rol <?=$_rol?>"><?=ucfirst($_rol)?></span>
  </div>
</div>
