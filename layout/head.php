<?php
if(!isset($page_title)) $page_title='IBBS';
if(!isset($active_link)) $active_link='';
require_once __DIR__.'/../config/bootstrap.php';
if(empty($_SESSION['loggedin'])){header('Location: login.php');exit;}
$_uid = (int)($_SESSION['user_id']??0);
// La cuenta pudo haber sido eliminada o desactivada mientras la sesión
// seguía abierta en otra pestaña/dispositivo — sin este chequeo, cada
// página seguía confiando en los datos cacheados de la sesión y las
// consultas que dependen de la fila de usuarios terminaban con warnings
// (acceso a índices de null) en vez de mandar a la persona a login.
$_sesionCon = db();
$_usuarioVivo = $_sesionCon ? mysqli_fetch_assoc(mysqli_query($_sesionCon, "SELECT activo FROM usuarios WHERE id=$_uid LIMIT 1")) : null;
if (!$_usuarioVivo || (int)$_usuarioVivo['activo'] !== 1) {
    session_destroy();
    header('Location: login.php');
    exit;
}
$_u   = $_SESSION['usuario']??'Usuario';
$_rol = $_SESSION['rol']??'profesor';
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
<?php if (ws_enabled()):
    // Canales base: 'staff' para todo el mundo salvo alumno (chat
    // general admin↔docentes, modulo_chat_general.php) — así llega la
    // notificación en vivo desde cualquier página, no solo estando
    // adentro del chat. Si la página fijó $ws_materia_id ANTES de este
    // include (ej. modulo_aula.php con el foro de una materia
    // puntual), se suma también ese canal — solo si materia_puede_ver()
    // lo permite de verdad (ws_token_for_materia lo valida).
    $_wsCanalesBase = $_rol !== 'alumno' ? ['staff'] : [];
    if (!empty($ws_materia_id)) {
        $_wsCon = db();
        if ($_wsCon) {
            $_wsCanalesMateria = materia_puede_ver($_wsCon, $_uid, $_rol, (int)$ws_materia_id) ? ['materia:'.(int)$ws_materia_id] : [];
            mysqli_close($_wsCon);
        } else {
            $_wsCanalesMateria = [];
        }
        $_wsToken = ws_token_mint($_uid, $_rol, $_u, array_merge($_wsCanalesBase, $_wsCanalesMateria));
    } else {
        $_wsToken = ws_token_mint($_uid, $_rol, $_u, $_wsCanalesBase);
    }
?>
<meta name="ibbs-ws-url" content="<?=htmlspecialchars(ws_public_url())?>">
<meta name="ibbs-ws-token" content="<?=htmlspecialchars($_wsToken)?>">
<?php endif; ?>
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
    <li><a href="index.php" class="sb-link <?=$active_link==='inicio'?'act':''?>" data-tour="nav-inicio"><i class="bx bx-grid-alt"></i><span class="sb-lbl">Inicio</span></a></li>

    <?php if(in_array($_rol,['superadmin','admin'])): ?>
    <div class="sb-section">Académico</div>
    <li><a href="modulo_materias.php" class="sb-link <?=$active_link==='materias'?'act':''?>" data-tour="nav-materias"><i class="bx bx-book-open"></i><span class="sb-lbl">Materias</span></a></li>
    <li><a href="modulo_docentes.php" class="sb-link <?=$active_link==='docentes'?'act':''?>"><i class="bx bx-chalkboard"></i><span class="sb-lbl">Docentes</span></a></li>
    <li><a href="modulo_alumnos.php" class="sb-link <?=$active_link==='alumnos'?'act':''?>" data-tour="nav-alumnos"><i class="bx bx-group"></i><span class="sb-lbl">Alumnos</span></a></li>
    <li><a href="modulo_inscripciones.php" class="sb-link <?=$active_link==='inscripciones'?'act':''?>"><i class="bx bx-user-plus"></i><span class="sb-lbl">Inscripciones</span></a></li>
    <li><a href="modulo_asistencias.php" class="sb-link <?=$active_link==='asistencias'?'act':''?>" data-tour="nav-asistencias"><i class="bx bx-check-square"></i><span class="sb-lbl">Asistencias</span></a></li>
    <?php endif; ?>

    <div class="sb-section">Calificaciones</div>
    <?php if(in_array($_rol,['superadmin','admin'])): ?>
    <li><a href="modulo_notas.php" class="sb-link <?=$active_link==='notas'?'act':''?>" data-tour="nav-notas"><i class="bx bx-edit-alt"></i><span class="sb-lbl">Cargar Notas</span></a></li>
    <?php elseif($_rol==='profesor'): ?>
    <li><a href="modulo_notas.php" class="sb-link <?=$active_link==='notas'?'act':''?>" data-tour="nav-notas"><i class="bx bx-edit-alt"></i><span class="sb-lbl">Cargar Notas</span></a></li>
    <?php endif; ?>
    <?php if(in_array($_rol,['superadmin','admin','profesor'])): ?>
    <li><a href="modulo_aula.php" class="sb-link <?=$active_link==='aula'?'act':''?>" data-tour="nav-aula"><i class="bx bx-chalkboard"></i><span class="sb-lbl">Aula Virtual</span></a></li>
    <li><a href="modulo_grabaciones.php" class="sb-link <?=$active_link==='grabaciones'?'act':''?>"><i class="bx bx-video"></i><span class="sb-lbl">Clases Grabadas</span></a></li>
    <li><a href="modulo_vivo.php" class="sb-link <?=$active_link==='vivo'?'act':''?>"><i class="bx bx-broadcast"></i><span class="sb-lbl">Clases en Vivo</span></a></li>
    <?php endif; ?>
    <?php if(in_array($_rol,['superadmin','admin'])): ?>
    <li><a href="modulo_record.php" class="sb-link <?=$active_link==='record'?'act':''?>"><i class="bx bx-bar-chart-alt-2"></i><span class="sb-lbl">Record Académico</span></a></li>
    <?php endif; ?>
    <?php if(in_array($_rol,['superadmin','admin','profesor'])): ?>
    <li><a href="modulo_biblioteca.php" class="sb-link <?=$active_link==='biblioteca'?'act':''?>"><i class="bx bx-library"></i><span class="sb-lbl">Biblioteca</span></a></li>
    <?php endif; ?>
    <?php if(in_array($_rol,['superadmin','admin'])): ?>
    <li><a href="modulo_pagos.php" class="sb-link <?=$active_link==='pagos'?'act':''?>" data-tour="nav-pagos"><i class="bx bx-dollar-circle"></i><span class="sb-lbl">Pagos</span></a></li>
    <?php endif; ?>

    <?php if(in_array($_rol,['superadmin','admin','profesor'])): ?>
    <div class="sb-section">Comunicación</div>
    <li><a href="modulo_chat_general.php" class="sb-link <?=$active_link==='chat_staff'?'act':''?>"><i class="bx bx-conversation"></i><span class="sb-lbl">Chat del Staff</span></a></li>
    <?php endif; ?>

    <?php if(in_array($_rol,['superadmin','admin'])): ?>
    <div class="sb-section">Reportes</div>
    <li><a href="modulo_buscar.php" class="sb-link <?=$active_link==='buscar'?'act':''?>"><i class="bx bx-search-alt-2"></i><span class="sb-lbl">Buscar Personal</span></a></li>
    <?php endif; ?>

    <div class="sb-section">Herramientas</div>
    <?php if(in_array($_rol,['superadmin','admin'])): ?>
    <li><a href="modulo_herramientas.php" class="sb-link <?=$active_link==='herramientas'?'act':''?>" data-tour="nav-herramientas"><i class="bx bx-bell"></i><span class="sb-lbl">Herramientas</span></a></li>
    <?php endif; ?>
    <li><a href="modulo_visor_pdf.php" class="sb-link <?=$active_link==='visor_pdf'?'act':''?>"><i class="bx bx-file"></i><span class="sb-lbl">Visor PDF</span></a></li>

    <?php if(in_array($_rol,['superadmin','admin'])): ?>
    <div class="sb-section">Sistema</div>
    <?php if($_rol==='superadmin'): ?>
    <li><a href="modulo_usuarios.php" class="sb-link <?=$active_link==='usuarios'?'act':''?>"><i class="bx bx-shield-quarter"></i><span class="sb-lbl">Usuarios</span></a></li>
    <li><a href="modulo_historial.php" class="sb-link <?=$active_link==='historial'?'act':''?>"><i class="bx bx-history"></i><span class="sb-lbl">Historial</span></a></li>
    <?php endif; ?>
    <li><a href="modulo_backup.php" class="sb-link <?=$active_link==='backup'?'act':''?>" data-tour="nav-backup"><i class="bx bx-data"></i><span class="sb-lbl">Respaldo BD</span></a></li>
    <?php endif; ?>

    <li><a href="javascript:void(0)" onclick="IbbsTour.replay(window.IBBS_TOUR_SIDEBAR)" class="sb-link" data-tour="nav-tutorial"><i class="bx bx-play-circle"></i><span class="sb-lbl">Ver Tutorial</span></a></li>
    <li><a href="assets/ibbs_ayuda.pdf" target="_blank" class="sb-link"><i class="bx bx-help-circle"></i><span class="sb-lbl">Ayuda</span></a></li>
  </ul>
  <div class="sb-bottom">
    <a href="modulo_perfil.php" class="sb-action" style="text-decoration:none;" data-tour="nav-perfil">
      <?php if($_foto): ?>
        <img src="<?=htmlspecialchars($_foto)?>" style="width:24px;height:24px;border-radius:50%;object-fit:cover;flex-shrink:0;" onerror="this.style.display='none';this.nextElementSibling.style.display='inline-block';">
        <i class="bx bx-user-circle" style="display:none;"></i>
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

    <!-- Paleta de comandos: buscar y saltar a cualquier sección -->
    <button type="button" class="ibbs-cmdk-btn" onclick="IbbsShortcuts.openPalette()" title="Buscar sección (Ctrl+K)" data-tour="top-buscar">
      <i class="bx bx-search"></i><span class="search-hint-text">Buscar</span> <kbd>Ctrl K</kbd>
    </button>

    <!-- Dark mode pill toggle -->
    <button id="themeToggle" onclick="toggleTheme()" title="Modo claro / oscuro" aria-label="Cambiar tema" data-tour="top-tema">
      <!-- luna (visible en modo claro) -->
      <svg class="t-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
      <!-- sol (visible en modo oscuro) -->
      <svg class="t-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><line x1="12" y1="2" x2="12" y2="4"/><line x1="12" y1="20" x2="12" y2="22"/><line x1="4.93" y1="4.93" x2="6.34" y2="6.34"/><line x1="17.66" y1="17.66" x2="19.07" y2="19.07"/><line x1="2" y1="12" x2="4" y2="12"/><line x1="20" y1="12" x2="22" y2="12"/><line x1="4.93" y1="19.07" x2="6.34" y2="17.66"/><line x1="17.66" y1="6.34" x2="19.07" y2="4.93"/></svg>
    </button>
    <!-- Notification bell -->
    <div style="position:relative;">
      <button id="notifBell" data-tour="top-notif" onclick="<?= in_array($_rol,['superadmin','admin']) ? "window.location='modulo_herramientas.php'" : 'toggleNotifDrop()' ?>" title="Notificaciones"
        style="position:relative;background:none;border:1.5px solid var(--border);border-radius:9px;padding:.45rem .6rem;cursor:pointer;display:flex;align-items:center;color:var(--ink);">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
        <span id="notifCount" style="display:none;position:absolute;top:-5px;right:-5px;background:#ef4444;color:#fff;border-radius:50%;width:17px;height:17px;font-size:.55rem;font-weight:700;display:flex;align-items:center;justify-content:center;line-height:1;"></span>
      </button>
      <?php if(!in_array($_rol,['superadmin','admin'])): ?>
      <div id="notifDrop" style="display:none;position:absolute;top:calc(100% + 8px);right:0;width:320px;max-height:420px;overflow-y:auto;background:var(--paper);border:1.5px solid var(--border);border-radius:12px;box-shadow:0 12px 30px rgba(0,0,0,.14);z-index:200;">
        <div style="padding:.7rem .9rem;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;">
          <strong style="font-size:.85rem;">Notificaciones</strong>
          <button onclick="marcarTodasLeidasDrop()" style="background:none;border:none;color:var(--lime,#059669);font-size:.72rem;cursor:pointer;">Marcar todas leídas</button>
        </div>
        <div id="notifDropList" style="padding:.4rem;"></div>
      </div>
      <?php endif; ?>
    </div>
  </div>
  <div class="user-pill">
    <?php if($_foto): ?>
      <img src="<?=htmlspecialchars($_foto)?>" style="width:30px;height:30px;border-radius:50%;object-fit:cover;" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
      <div class="user-ava" style="display:none;"><?=$_ini?></div>
    <?php else: ?>
      <div class="user-ava"><?=$_ini?></div>
    <?php endif; ?>
    <span class="user-name"><?=htmlspecialchars($_u)?></span>
    <span class="user-rol <?=$_rol?>"><?=ucfirst($_rol)?></span>
  </div>
</div>
