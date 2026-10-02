<?php
require_once __DIR__.'/config/bootstrap.php';

// Validamos correctamente la sesión. Autorizamos a docente, profesor, admin y superadmin
if (empty($_SESSION['loggedin']) || !in_array($_SESSION['rol'], ['docente', 'profesor', 'superadmin', 'admin'])) {
    header('Location: login.php');
    exit;
}

$con = db();
$user_id = $_SESSION['user_id'];
$rol_badge = strtoupper($_SESSION['rol'] === 'superadmin' || $_SESSION['rol'] === 'admin' ? 'ADMINISTRADOR' : 'PROFESOR');
$rol_class = strtolower($_SESSION['rol'] === 'superadmin' || $_SESSION['rol'] === 'admin' ? 'superadmin' : 'profesor');

$stmt = mysqli_prepare($con, "SELECT * FROM usuarios WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$usuario_db = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

$nombre_docente = $usuario_db['usuario'];
$inicial = strtoupper(substr($nombre_docente, 0, 1));
$foto_perfil = !empty($usuario_db['foto']) ? $usuario_db['foto'] : null;

$alumnos_totales = mysqli_fetch_row(mysqli_query($con, "SELECT COUNT(*) FROM alumnos WHERE activo=1"))[0] ?? 0;
$docentes_totales = mysqli_fetch_row(mysqli_query($con, "SELECT COUNT(*) FROM docentes WHERE activo=1"))[0] ?? 0;
$materias_totales = mysqli_fetch_row(mysqli_query($con, "SELECT COUNT(*) FROM materias WHERE activo=1"))[0] ?? 0;
$asistencias_totales = mysqli_fetch_row(mysqli_query($con, "SELECT COUNT(*) FROM asistencias"))[0] ?? 0;

$res_cal = mysqli_query($con, "SELECT
    SUM(CASE WHEN nota_final >= 15 THEN 1 ELSE 0 END) as aprobados,
    SUM(CASE WHEN nota_final < 15 THEN 1 ELSE 0 END) as reprobados,
    SUM(CASE WHEN nota_final IS NULL THEN 1 ELSE 0 END) as sin_nota
    FROM materia_alumno");
$calificaciones = mysqli_fetch_assoc($res_cal);
$cal_aprobados = $calificaciones['aprobados'] ?: 0;
$cal_reprobados = $calificaciones['reprobados'] ?: 0;
$cal_sin_nota = $calificaciones['sin_nota'] ?: 0;

$res_mat = mysqli_query($con, "SELECT estado, COUNT(*) as total FROM materias GROUP BY estado");
$estado_materias = ['en_curso' => 0, 'pendiente' => 0, 'culminada' => 0];
if($res_mat) { while($row = mysqli_fetch_assoc($res_mat)) { $estado_materias[$row['estado']] = $row['total']; } }

$res_asi = mysqli_query($con, "SELECT estado, COUNT(*) as total FROM asistencias GROUP BY estado");
$estado_asistencias = ['presente' => 0, 'ausente' => 0, 'tardanza' => 0, 'justificado' => 0];
if($res_asi) { while($row = mysqli_fetch_assoc($res_asi)) { $estado_asistencias[$row['estado']] = $row['total']; } }

$docente_id = 0;
$stmt_doc = mysqli_prepare($con, "SELECT id FROM docentes WHERE cedula = ? OR usuario_id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt_doc, "si", $usuario_db['cedula'], $user_id);
mysqli_stmt_execute($stmt_doc);
$docente_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_doc));
if ($docente_data) $docente_id = $docente_data['id'];

// Obtener materias (El superadmin ve todas, el profesor solo las suyas)
if (in_array($_SESSION['rol'], ['superadmin', 'admin'])) {
    $query_materias = "SELECT * FROM materias WHERE activo = 1";
    $stmt_m = mysqli_prepare($con, $query_materias);
} else {
    $query_materias = "SELECT m.* FROM materias m JOIN materia_docente md ON m.id = md.materia_id WHERE md.docente_id = ? AND m.activo = 1";
    $stmt_m = mysqli_prepare($con, $query_materias);
    mysqli_stmt_bind_param($stmt_m, "i", $docente_id);
}
mysqli_stmt_execute($stmt_m);
$res_materias = mysqli_stmt_get_result($stmt_m);
$materias = [];
while($row = mysqli_fetch_assoc($res_materias)) $materias[] = $row;

// Token de WebSocket (opcional — ver config/ws_config.php) con un
// canal por cada materia que este profesor/admin puede tener abierta
// en el chat, para que el foro se actualice al instante si hay un VPS
// con ws-server/ configurado.
$ws_token = ws_enabled() ? ws_token_for_materias($con, $user_id, $_SESSION['rol'], $_SESSION['usuario'], array_column($materias, 'id')) : null;

// Obtener entregas
if (in_array($_SESSION['rol'], ['superadmin', 'admin'])) {
    $query_entregas = "SELECT e.*, t.titulo as tarea_titulo, t.nota_maxima, t.fecha_limite_correccion, a.nombre as alumno_nombre, a.apellido as alumno_apellido, m.nombre as materia_nombre
                     FROM entregas e JOIN tareas t ON e.tarea_id = t.id JOIN materias m ON t.materia_id = m.id JOIN alumnos a ON e.alumno_id = a.id
                     ORDER BY e.fecha_entrega DESC";
    $stmt_e = mysqli_prepare($con, $query_entregas);
} else {
    $query_entregas = "SELECT e.*, t.titulo as tarea_titulo, t.nota_maxima, t.fecha_limite_correccion, a.nombre as alumno_nombre, a.apellido as alumno_apellido, m.nombre as materia_nombre
                     FROM entregas e JOIN tareas t ON e.tarea_id = t.id JOIN materias m ON t.materia_id = m.id JOIN alumnos a ON e.alumno_id = a.id
                     JOIN materia_docente md ON m.id = md.materia_id WHERE md.docente_id = ? ORDER BY e.fecha_entrega DESC";
    $stmt_e = mysqli_prepare($con, $query_entregas);
    mysqli_stmt_bind_param($stmt_e, "i", $docente_id);
}
mysqli_stmt_execute($stmt_e);
$res_entregas = mysqli_stmt_get_result($stmt_e);
$entregas = []; $por_calificar_count = 0;
while($row = mysqli_fetch_assoc($res_entregas)) {
    $entregas[] = $row;
    if ($row['nota'] === null) $por_calificar_count++;
}

$todos_docentes = []; $todas_materias = [];
if (in_array($_SESSION['rol'], ['superadmin', 'admin'])) {
    $res_all_doc = mysqli_query($con, "SELECT d.*, u.usuario FROM docentes d LEFT JOIN usuarios u ON d.usuario_id = u.id WHERE d.activo = 1");
    if($res_all_doc) while($row = mysqli_fetch_assoc($res_all_doc)) $todos_docentes[] = $row;
    $res_all_mat = mysqli_query($con, "SELECT * FROM materias WHERE activo = 1");
    if($res_all_mat) while($row = mysqli_fetch_assoc($res_all_mat)) $todas_materias[] = $row;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?=htmlspecialchars(csrf_token())?>">
    <!-- Aplicar tema ANTES del primer pintado — evita el parpadeo claro→oscuro -->
    <script>
      (function(){
        if(localStorage.getItem('ibbs_theme')==='dark'){
          document.documentElement.setAttribute('data-theme','dark');
          document.documentElement.style.background='#162b1f';
        }
      })();
    </script>
    <?php if ($ws_token): ?>
    <meta name="ibbs-ws-url" content="<?=htmlspecialchars(ws_public_url())?>">
    <meta name="ibbs-ws-token" content="<?=htmlspecialchars($ws_token)?>">
    <?php endif; ?>
    <title>Portal Docente | IBBS</title>
    
    <!-- CSS Maestro del Sistema IBBS -->
    <link rel="stylesheet" href="assets/ibbs.css">
    
    <!-- Dependencias externas -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- SweetAlert2 — reemplaza los alert()/confirm() nativos del navegador -->
    <script src="assets/libs/sweetalert2.all.min.js"></script>
    <script src="assets/ibbs-alerts.js"></script>
    <script src="assets/ibbs-tour.js"></script>
    <script src="assets/ibbs-password-toggle.js"></script>
    <script src="assets/ibbs-shortcuts.js"></script>
    <script src="assets/ibbs-notif-bell.js"></script>
    
    <style>
        /* Animaciones para SPA */
        .view-section { display: none; animation: fadeIn 0.3s cubic-bezier(0.22, 1, 0.36, 1); }
        .view-section.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        
        /* Layouts de grilla específicos */
        .grid-cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1.2rem; }
        
        /* Banner Dashboard */
        .banner-dash {
            background: var(--ink); color: #fff; padding: 2rem 2.5rem; 
            border-radius: 14px; position: relative; overflow: hidden;
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 1.6rem; box-shadow: 0 10px 30px rgba(26,77,46,.15);
        }
        .banner-dash::before {
            content: ''; position: absolute; inset: 0; pointer-events: none;
            background-image: radial-gradient(rgba(57,255,20,.05) 1px, transparent 1px);
            background-size: 20px 20px;
        }
        
        /* Chat Foros V2 */
        .chat-layout { height: calc(100vh - 160px); display: flex; overflow: hidden; padding: 0; }
        .chat-sidebar { width: 260px; border-right: 1px solid var(--border); background: var(--paper); display: flex; flex-direction: column; }
        .chat-main { flex: 1; display: flex; flex-direction: column; background: var(--cream); position: relative; }
        .chat-item { padding: 1rem; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: .7rem; cursor: pointer; transition: background .2s; }
        .chat-item:hover { background: var(--cream); }
        .chat-item.active-chat { background: var(--paper); border-left: 3px solid var(--lime2); box-shadow: inset 0 2px 4px rgba(0,0,0,.02); }
        .msg-bubble { max-width: 75%; padding: .8rem 1rem; border-radius: 14px; font-size: .88rem; box-shadow: 0 2px 6px rgba(0,0,0,.04); position: relative; }
        .msg-mine { background: var(--ink); color: #fff; border-bottom-right-radius: 4px; }
        .msg-other { background: var(--paper); border: 1px solid var(--border); color: var(--ink); border-bottom-left-radius: 4px; }
        
        /* Alertas Premium */
        .alert-box {
            background: rgba(217,119,6,.08); border-left: 4px solid var(--amber);
            padding: 1rem 1.2rem; border-radius: 8px; margin-bottom: 1.5rem; display: flex; gap: .8rem; align-items:flex-start;
        }
        .alert-box i { color: var(--amber); font-size: 1.2rem; margin-top: 2px; }
        
        /* Ajuste de foto de perfil */
        .user-ava img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; }

        /* ── Asistencia por Foto (OCR) — igual que modulo_asistencias.php,
           portado acá para que el docente lo tenga dentro de su propio
           portal en vez de tener que entrar al panel de administración ── */
        .pl-row { transition:background .12s; }
        .pl-row td { vertical-align:middle; }
        .pl-row.pl-presente td:first-child { border-left:3px solid #22c55e; }
        .pl-row.pl-ausente   td:first-child { border-left:3px solid #ef4444; }
        .pl-row.pl-tardanza  td:first-child { border-left:3px solid #f59e0b; }
        .pl-row.pl-justificado td:first-child { border-left:3px solid #6366f1; }
        .pl-ava {
            width:32px;height:32px;border-radius:50%;
            overflow:hidden;display:flex;align-items:center;justify-content:center;
            flex-shrink:0;font-size:.82rem;font-weight:700;
            background:var(--ink);color:var(--lime);font-family:'Nunito',sans-serif;
        }
        .pl-ava img { width:100%;height:100%;object-fit:cover;border-radius:50%;display:block; }
        .mark-btn {
            padding:.22rem .55rem;border-radius:5px;border:1px solid var(--border);
            background:transparent;cursor:pointer;font-size:.7rem;font-weight:700;
            font-family:'Nunito',sans-serif;color:var(--muted);text-transform:uppercase;
            letter-spacing:.3px;transition:all .12s;
        }
        .mark-btn:hover { background:var(--cream); }
        .mark-btn.mk-p { background:#22c55e;color:#fff;border-color:#22c55e; }
        .mark-btn.mk-a { background:#ef4444;color:#fff;border-color:#ef4444; }
        .mark-btn.mk-t { background:#f59e0b;color:#fff;border-color:#f59e0b; }
        .mark-btn.mk-j { background:#6366f1;color:#fff;border-color:#6366f1; }
        .obs-input {
            width:100%;padding:.3rem .55rem;border:1px solid var(--border);border-radius:6px;
            font-size:.76rem;font-family:'Nunito',sans-serif;background:var(--cream);color:var(--ink);outline:none;
        }
        .obs-input:focus { border-color:var(--lime2);box-shadow:0 0 0 2px rgba(46,204,16,.1); }
        .pct-bar { height:5px;background:var(--border);border-radius:3px;margin-top:3px;overflow:hidden; }
        .pct-fill { height:100%;border-radius:3px;transition:width .4s; }
        .ocr-info-box {
            display:flex;gap:.7rem;align-items:flex-start;
            background:#eff6ff;border:1.5px solid #bfdbfe;color:#1e3a8a;
            border-radius:10px;padding:.85rem 1rem;margin-bottom:1rem;font-size:.82rem;line-height:1.5;
        }
        .ocr-radio { display:flex;align-items:center;gap:.4rem;font-size:.82rem;color:var(--ink);cursor:pointer;font-weight:500; }
        .ocr-detect-yes { color:#15803d;font-weight:700;font-size:.76rem; }
        .ocr-detect-no  { color:#b45309;font-weight:700;font-size:.76rem; }
        .ocr-hoja-card { display:flex;gap:.8rem;align-items:center;padding:.7rem .9rem;border-bottom:1px solid var(--border); }
        .ocr-hoja-card:last-child{ border-bottom:none; }
        .ocr-hoja-thumb { width:48px;height:48px;border-radius:8px;object-fit:cover;border:1px solid var(--border);flex-shrink:0;cursor:pointer; }
        html[data-theme="dark"] .ocr-info-box { background:rgba(37,99,235,.12); border-color:#1e3a5f; color:#bfdbfe; }

        /* ── Modo oscuro — ajustes propios de este portal ── */
        html[data-theme="dark"] .msg-mine { background: #1a4d2e; color: #eafbe8; }
        html[data-theme="dark"] .alert-box { background: rgba(217,119,6,.14); }

        /* ── Móvil — este portal no tenía ningún ajuste propio;
           el rail de íconos y las grillas ya heredan lo de
           assets/ibbs.css, pero estos dos bloques son a medida
           de este archivo y quedaban apretados en un teléfono ── */
        @media(max-width:768px) {
            .banner-dash {
                flex-direction: column; align-items: flex-start; gap: 1rem;
                padding: 1.4rem 1.2rem;
            }
            .banner-dash h3 { font-size: 1.35rem !important; }
            .banner-dash > div:last-child { width: 100%; }
            .banner-dash > div:last-child .btn { flex: 1; text-align: center; }

            .chat-layout { flex-direction: column; height: calc(100vh - 130px); }
            .chat-sidebar {
                width: 100%; max-height: 130px;
                border-right: none; border-bottom: 1px solid var(--border);
            }
        }
    </style>
</head>
<body>

    <!-- 1. SIDEBAR -->
    <aside id="sb">
        <a href="#" class="sb-brand">
            <div class="sb-logo">
                <img src="assets/logo.jpg" alt="Logo" style="width:100%;height:100%;object-fit:cover;border-radius:9px;opacity:0.9;">
            </div>
            <div class="sb-wordmark">
                <strong>IBBS</strong>
                <small>Sistema Académico</small>
            </div>
        </a>
        
        <nav class="sb-nav">
            <button data-tour="nav-dashboard" onclick="switchView('dashboard', this)" class="sb-link act">
                <i class="fas fa-th-large"></i> <span class="sb-lbl">Inicio</span>
            </button>

            <div class="sb-section">Mi Gestión</div>

            <button data-tour="nav-materias" onclick="switchView('materias', this)" class="sb-link">
                <i class="fas fa-book"></i> <span class="sb-lbl">Mis Cursos</span>
            </button>
            <button data-tour="nav-aula" onclick="switchView('aula', this)" class="sb-link">
                <i class="fas fa-desktop"></i> <span class="sb-lbl">Aula Virtual</span>
            </button>
            <button data-tour="nav-asistencia" onclick="switchView('asistencia', this); ocrCargarHojas();" class="sb-link">
                <i class="fas fa-user-check"></i> <span class="sb-lbl">Asistencia</span>
            </button>
            <button data-tour="nav-entregas" onclick="switchView('entregas', this)" class="sb-link">
                <i class="fas fa-pencil-alt"></i> <span class="sb-lbl">Cargar Notas</span>
            </button>
            <button data-tour="nav-plan-notas" onclick="switchView('plan-notas', this)" class="sb-link">
                <i class="fas fa-table-list"></i> <span class="sb-lbl">Plan de Notas</span>
            </button>
            <button data-tour="nav-chat" onclick="switchView('chat', this)" class="sb-link">
                <i class="fas fa-comments"></i> <span class="sb-lbl">Foros de Clase</span>
            </button>
            <button data-tour="nav-chat-staff" onclick="switchView('chat-staff', this)" class="sb-link">
                <i class="fas fa-bullhorn"></i> <span class="sb-lbl">Chat del Staff</span>
            </button>
            <button data-tour="nav-biblioteca" onclick="switchView('biblioteca', this); cargarMisLibrosDocente();" class="sb-link">
                <i class="fas fa-book"></i> <span class="sb-lbl">Biblioteca</span>
            </button>

            <?php if(in_array($_SESSION['rol'], ['superadmin', 'admin'])): ?>
            <div class="sb-section" style="margin-top: .5rem;">Administración</div>
            <button data-tour="nav-admin-docentes" onclick="switchView('admin-docentes', this)" class="sb-link">
                <i class="fas fa-users-cog"></i> <span class="sb-lbl">Asignar Materias</span>
            </button>
            <?php endif; ?>
        </nav>

        <div class="sb-bottom">
            <button type="button" data-tour="nav-replay" data-palette-skip="1" onclick="IbbsTour.replay(window.IBBS_TOUR_DOCENTE)" class="ibbstour-replay sb-link">
                <i class="fas fa-circle-question"></i> <span class="sb-lbl">Ver Tutorial</span>
            </button>
            <button data-tour="nav-perfil" onclick="switchView('perfil', this)" class="sb-link">
                <i class="fas fa-user-circle"></i> <span class="sb-lbl">Mi Perfil</span>
            </button>
            <a href="cerrar_sesion.php" class="sb-link">
                <i class="fas fa-sign-out-alt"></i> <span class="sb-lbl">Salir</span>
            </a>
        </div>
    </aside>

    <!-- 2. MAIN CONTENT -->
    <main id="main">
        
        <!-- TOPBAR -->
        <div class="topbar">
            <div>
                <h2 class="page-title">Panel de Control <em>Resumen de gestión docente</em></h2>
            </div>
            <div style="display: flex; align-items: center; gap: 1rem;">
                <button type="button" class="ibbs-cmdk-btn" onclick="IbbsShortcuts.openPalette()" title="Buscar sección (Ctrl+K)">
                    <i class="fas fa-search"></i> <kbd>Ctrl K</kbd>
                </button>
                <button id="themeToggle" onclick="toggleTheme()" title="Modo claro / oscuro" aria-label="Cambiar tema">
                    <svg class="t-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                    <svg class="t-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><line x1="12" y1="2" x2="12" y2="4"/><line x1="12" y1="20" x2="12" y2="22"/><line x1="4.93" y1="4.93" x2="6.34" y2="6.34"/><line x1="17.66" y1="17.66" x2="19.07" y2="19.07"/><line x1="2" y1="12" x2="4" y2="12"/><line x1="20" y1="12" x2="22" y2="12"/><line x1="4.93" y1="19.07" x2="6.34" y2="17.66"/><line x1="17.66" y1="6.34" x2="19.07" y2="4.93"/></svg>
                </button>
                <div style="position: relative;">
                    <button id="notifBell" onclick="toggleNotifDrop()" title="Notificaciones" style="position:relative;background:none;border:1.5px solid var(--border);border-radius:9px;padding:.5rem .7rem;cursor:pointer;display:flex;align-items:center;color:var(--ink);">
                        <i class="fas fa-bell"></i>
                        <span id="notifCount" style="display:none;position:absolute;top:-5px;right:-5px;background:#ef4444;color:#fff;border-radius:50%;width:17px;height:17px;font-size:.55rem;font-weight:700;display:flex;align-items:center;justify-content:center;line-height:1;"></span>
                    </button>
                    <div id="notifDrop" style="display:none;position:absolute;top:calc(100% + 8px);right:0;width:320px;max-height:420px;overflow-y:auto;background:var(--paper,#fdfaf4);border:1.5px solid var(--border,#e0d8c8);border-radius:12px;box-shadow:0 12px 30px rgba(0,0,0,.14);z-index:200;">
                        <div style="padding:.7rem .9rem;border-bottom:1px solid var(--border,#e0d8c8);display:flex;justify-content:space-between;align-items:center;">
                            <strong style="font-size:.85rem;">Notificaciones</strong>
                            <button onclick="marcarTodasLeidasDrop()" style="background:none;border:none;color:#16a34a;font-size:.72rem;cursor:pointer;">Marcar todas leídas</button>
                        </div>
                        <div id="notifDropList" style="padding:.4rem;"></div>
                    </div>
                </div>
                <div class="user-pill">
                    <div class="user-ava">
                        <?php if($foto_perfil): ?>
                            <img src="<?= htmlspecialchars($foto_perfil) ?>" alt="Foto">
                        <?php else: ?>
                            <?= $inicial ?>
                        <?php endif; ?>
                    </div>
                    <span class="user-name"><?= htmlspecialchars($nombre_docente) ?></span>
                    <span class="user-rol <?= $rol_class ?>"><?= $rol_badge ?></span>
                </div>
            </div>
        </div>

        <!-- ============================================== -->
        <!-- VISTA: DASHBOARD                               -->
        <!-- ============================================== -->
        <div id="view-dashboard" class="view-section active">
            
            <div class="banner-dash">
                <div style="position: relative; z-index: 1;">
                    <h3 style="font-family: 'Playfair Display', serif; font-size: 1.8rem; margin-bottom: .2rem;">
                        Bienvenido, <span style="color: var(--lime); font-style: italic; font-weight: 300;"><?= htmlspecialchars($nombre_docente) ?></span>
                    </h3>
                    <p style="font-size: .85rem; color: rgba(255,255,255,.6); letter-spacing: .5px;">IBBS — Gestión académica completa · <?= date('l, d \d\e F \d\e Y') ?></p>
                </div>
                <div style="position: relative; z-index: 1; display: flex; gap: .8rem;">
                    <button class="btn btn-primary" style="background: rgba(57,255,20,.1); border: 1px solid var(--lime); color: var(--lime); box-shadow: none;">Asistencia</button>
                    <button onclick="document.querySelectorAll('.sb-link')[3].click()" class="btn btn-primary">Cargar Notas</button>
                </div>
            </div>

            <div class="stats">
                <div class="scard c1">
                    <div class="scard-ico"><i class="fas fa-users"></i></div>
                    <div><div class="scard-val"><?= $alumnos_totales ?></div><div class="scard-key">Alumnos</div></div>
                </div>
                <div class="scard c2">
                    <div class="scard-ico"><i class="fas fa-chalkboard-teacher"></i></div>
                    <div><div class="scard-val"><?= $docentes_totales ?></div><div class="scard-key">Docentes</div></div>
                </div>
                <div class="scard c3">
                    <div class="scard-ico"><i class="fas fa-book-open"></i></div>
                    <div><div class="scard-val"><?= $materias_totales ?></div><div class="scard-key">Materias</div></div>
                </div>
                <div class="scard c4">
                    <div class="scard-ico"><i class="fas fa-calendar-check"></i></div>
                    <div><div class="scard-val"><?= $asistencias_totales ?></div><div class="scard-key">Asistencias</div></div>
                </div>
            </div>

            <div class="form-grid g3" style="margin-bottom: 1.5rem;">
                <div class="card">
                    <div class="card-head">
                        <h3><i class="fas fa-chart-pie"></i> Calificaciones</h3>
                    </div>
                    <div class="card-body" style="text-align: center; display: flex; flex-direction: column; align-items: center;">
                        <canvas id="chartCalificaciones" style="max-width: 170px; margin-bottom: 1rem;"></canvas>
                        <div style="display: flex; gap: 1rem; font-size: .75rem; color: var(--muted); font-weight: 600;">
                            <span><span style="color:var(--green);">●</span> Aprob.</span>
                            <span><span style="color:var(--red);">●</span> Reprob.</span>
                            <span><span style="color:#cbd5e1;">●</span> S/Nota</span>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-head">
                        <h3><i class="fas fa-tasks"></i> Estado Materias</h3>
                    </div>
                    <div class="card-body" style="text-align: center; display: flex; flex-direction: column; align-items: center;">
                        <canvas id="chartMaterias" style="max-width: 170px; margin-bottom: 1rem;"></canvas>
                        <div style="display: flex; gap: 1rem; font-size: .75rem; color: var(--muted); font-weight: 600;">
                            <span><span style="color:var(--blue);">●</span> En curso</span>
                            <span><span style="color:var(--amber);">●</span> Pend.</span>
                            <span><span style="color:var(--green);">●</span> Culm.</span>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-head">
                        <h3><i class="fas fa-user-check"></i> Asistencias</h3>
                    </div>
                    <div class="card-body" style="text-align: center; display: flex; flex-direction: column; align-items: center;">
                        <canvas id="chartAsistencias" style="max-width: 170px; margin-bottom: 1rem;"></canvas>
                        <div style="display: flex; gap: 1rem; font-size: .75rem; color: var(--muted); font-weight: 600; flex-wrap: wrap; justify-content: center;">
                            <span><span style="color:var(--green);">●</span> Pres.</span>
                            <span><span style="color:var(--red);">●</span> Aus.</span>
                            <span><span style="color:var(--amber);">●</span> Tard.</span>
                            <span><span style="color:var(--blue);">●</span> Justif.</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================== -->
        <!-- VISTA: ENTREGAS Y CARGA DE NOTAS               -->
        <!-- ============================================== -->
        <div id="view-entregas" class="view-section">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <div>
                    <h2 style="font-family:'Playfair Display',serif; font-size:1.6rem; color:var(--ink);">Cargar Notas / Entregas</h2>
                    <p style="font-size:.85rem; color:var(--muted);">Revisa las tareas enviadas, asigna calificaciones y sube nuevo material.</p>
                </div>
                <button onclick="openModal('modal-nueva-tarea')" class="btn btn-primary"><i class="fas fa-plus"></i> Subir Actividad</button>
            </div>
            
            <?php if($docente_id === 0 && !in_array($_SESSION['rol'], ['superadmin','admin'])): ?>
            <div class="alert-box">
                <i class="fas fa-exclamation-triangle"></i>
                <div>
                    <strong style="color:var(--amber); font-size:.9rem; display:block; margin-bottom:.2rem;">Perfil de Docente Incompleto</strong>
                    <p style="font-size:.8rem; color:var(--muted);">No tienes un perfil en la tabla de docentes vinculado a ti. Solicita al administrador que te registre con tu cédula (<b><?= htmlspecialchars($usuario_db['cedula']) ?></b>) para vincular correctamente las entregas.</p>
                </div>
            </div>
            <?php endif; ?>

            <!-- Pestañas de Filtro para las tareas -->
            <div class="tabs-nav" style="margin-bottom: 1.5rem;">
                <button class="tab-btn active" onclick="filtrarEntregas('todas', this)">Todas</button>
                <button class="tab-btn" onclick="filtrarEntregas('pendientes', this)">Pendientes (<span id="pendientesCountBadge" style="color:var(--amber); font-weight:bold;"><?= $por_calificar_count ?></span>)</button>
                <button class="tab-btn" onclick="filtrarEntregas('calificadas', this)">Calificadas</button>
            </div>

            <div class="grid-cards" id="contenedor-entregas">
                <?php foreach($entregas as $e):
                    $tieneNota = $e['nota'] !== null;
                    $aprobada  = $tieneNota && (float)$e['nota'] >= 15;
                    $vencidaSinCalificar = !$tieneNota && !empty($e['fecha_limite_correccion']) && strtotime($e['fecha_limite_correccion']) < time();
                ?>
                    <!-- Tarjeta con data-estado para facilitar el filtro -->
                    <div class="card entrega-card" id="entrega-card-<?= $e['id'] ?>" data-estado="<?= $tieneNota ? 'calificada' : 'pendiente' ?>" data-alumno="<?= htmlspecialchars($e['alumno_nombre'].' '.$e['alumno_apellido'], ENT_QUOTES) ?>" data-tarea="<?= htmlspecialchars($e['tarea_titulo'], ENT_QUOTES) ?>" data-nota="<?= $tieneNota ? htmlspecialchars($e['nota']) : '' ?>" data-aprobada="<?= $aprobada ? '1' : '0' ?>" data-archivo="<?= $e['archivo'] ? htmlspecialchars('uploads/entregas/'.$e['archivo']) : '' ?>" data-obs="<?= htmlspecialchars($e['observacion'] ?? '', ENT_QUOTES) ?>" style="border-left: 4px solid <?= !$tieneNota ? 'var(--amber)' : ($aprobada ? 'var(--lime2)' : 'var(--red)') ?>;<?= $tieneNota ? ' opacity: 0.9;' : '' ?>">
                        <div class="card-body" style="display: flex; flex-direction: column; height: 100%;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: .8rem;">
                                <span class="badge" style="background:var(--cream); color:var(--muted); border:1px solid var(--border);"><?= htmlspecialchars($e['materia_nombre']) ?></span>
                                <span class="badge entrega-badge-estado <?= !$tieneNota ? 'b-tardanza' : ($aprobada ? 'b-presente' : 'b-ausente') ?>">
                                    <?php if(!$tieneNota): ?>
                                        <i class="fas fa-clock"></i> Por Calificar
                                    <?php else: ?>
                                        <i class="fas <?= $aprobada ? 'fa-check' : 'fa-xmark' ?>"></i> Nota: <?= htmlspecialchars($e['nota']) ?> / 20 · <?= $aprobada ? 'Aprobado' : 'Reprobado' ?>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <?php if($vencidaSinCalificar): ?>
                            <div style="background: rgba(220,38,38,.08); border: 1px solid var(--red); border-radius: 8px; padding: .5rem .7rem; font-size: .75rem; color: var(--red); font-weight: 700; margin-bottom: .8rem;">
                                <i class="fas fa-triangle-exclamation"></i> Venció tu fecha límite de corrección — calificala cuanto antes.
                            </div>
                            <?php endif; ?>

                            <h3 style="font-family:'Playfair Display',serif; font-size:1.1rem; color:var(--ink); margin-bottom:.3rem;"><?= htmlspecialchars($e['tarea_titulo']) ?></h3>
                            <p style="font-size:.8rem; color:var(--muted); margin-bottom: 1rem;">
                                <i class="fas fa-user-graduate"></i> Alumno: <strong style="color:var(--ink);"><?= htmlspecialchars($e['alumno_nombre'] . ' ' . $e['alumno_apellido']) ?></strong>
                            </p>

                            <?php if($e['texto_respuesta']): ?>
                            <div style="background: var(--cream); padding: .8rem; border-radius: 8px; font-size: .8rem; color: var(--ink); font-style: italic; margin-bottom: 1rem; border: 1px solid var(--border);">
                                "<?= nl2br(htmlspecialchars($e['texto_respuesta'])) ?>"
                            </div>
                            <?php endif; ?>

                            <div class="entrega-obs-wrap" style="<?= empty($e['observacion']) ? 'display:none;' : '' ?>margin-bottom: 1rem;">
                                <div class="entrega-obs-box" style="background: <?= $aprobada ? 'rgba(46,204,16,.08)' : 'rgba(217,119,6,.08)' ?>; border: 1px solid var(--border); border-radius: 8px; padding: .7rem .8rem; font-size: .78rem; color: var(--ink);">
                                    <strong style="display:block;margin-bottom:.2rem;"><i class="fas fa-comment-dots"></i> Tu mensaje al alumno:</strong>
                                    <span class="entrega-obs-text"><?= nl2br(htmlspecialchars($e['observacion'] ?? '')) ?></span>
                                </div>
                            </div>

                            <div style="margin-top: auto; margin-bottom: 1rem; display: flex; flex-direction: column; gap: .5rem;">
                                <p style="font-size: .75rem; color: var(--muted); font-weight: 600;"><i class="far fa-calendar-check"></i> Entregado: <?= date('d M Y, h:i A', strtotime($e['fecha_entrega'])) ?></p>
                                <?php if($e['archivo']): ?>
                                <a href="uploads/entregas/<?= htmlspecialchars($e['archivo']) ?>" target="_blank" class="btn btn-secondary btn-sm" style="width: max-content;">
                                    <i class="fas fa-download"></i> Descargar Adjunto
                                </a>
                                <?php endif; ?>
                            </div>

                            <button class="btn entrega-btn-calificar <?= !$tieneNota ? 'btn-primary' : 'btn-secondary' ?>" style="width: 100%; justify-content: center;"
                                    onclick="openModalCalificar(<?= $e['id'] ?>, '<?= htmlspecialchars($e['alumno_nombre'].' '.$e['alumno_apellido'], ENT_QUOTES) ?>', '<?= htmlspecialchars($e['tarea_titulo'], ENT_QUOTES) ?>', <?= $e['nota'] === null ? 'null' : $e['nota'] ?>, '<?= htmlspecialchars($e['observacion'] ?? '', ENT_QUOTES) ?>')">
                                <i class="fas fa-star"></i> <?= !$tieneNota ? 'Asignar Calificación' : 'Modificar Calificación' ?>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <?php if(empty($entregas)): ?>
                <div style="grid-column: 1 / -1; padding: 4rem 1rem; text-align: center; border: 2px dashed var(--border); border-radius: 14px;">
                    <i class="fas fa-clipboard-list" style="font-size: 3rem; color: var(--border); margin-bottom: 1rem;"></i>
                    <p style="color: var(--muted); font-size: .9rem;">No hay entregas registradas aún.</p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Pestaña "Calificadas": lista por alumno en vez de una
                 tarjeta grande por cada entrega — mucho menos espacio
                 cuando un alumno ya tiene varias notas cargadas. -->
            <div id="calificadas-agrupadas" style="display:none;"></div>
        </div>

        <!-- ============================================== -->
        <!-- VISTA: PLAN DE NOTAS                           -->
        <!-- ============================================== -->
        <div id="view-plan-notas" class="view-section">
            <div style="margin-bottom:1.5rem;padding-bottom:1rem;border-bottom:1px solid var(--border);">
                <h2 style="font-family:'Playfair Display',serif; font-size:1.6rem; color:var(--ink);">Plan de Notas</h2>
                <p style="font-size:.85rem;color:var(--muted);margin-top:.2rem;">Todas las actividades de una materia con su % y la nota de cada alumno — el promedio ponderado se calcula solo.</p>
            </div>

            <div class="field" style="max-width:420px;margin-bottom:1.2rem;">
                <label>Materia</label>
                <select id="pnMateria" onchange="cargarPlanNotas()">
                    <option value="">— Seleccionar materia —</option>
                    <?php foreach($materias as $m): ?>
                    <option value="<?=$m['id']?>"><?=htmlspecialchars(($m['codigo']??'').' · '.$m['nombre'])?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="card">
                <div class="tbl-wrap">
                    <div id="pnContenedor" style="padding:2rem;text-align:center;color:var(--muted);">Elegí una materia para ver su plan de notas.</div>
                </div>
            </div>
        </div>

        <!-- ============================================== -->
        <!-- VISTA: ASISTENCIA POR FOTO                     -->
        <!-- ============================================== -->
        <div id="view-asistencia" class="view-section">
            <div style="margin-bottom:1.5rem;padding-bottom:1rem;border-bottom:1px solid var(--border);">
                <h2 style="font-family:'Playfair Display',serif; font-size:1.6rem; color:var(--ink);">Asistencia por Foto</h2>
                <p style="font-size:.85rem;color:var(--muted);margin-top:.2rem;">Subí la foto de la hoja de asistencia en papel de tu clase y el sistema te ayuda a pasarla a la tabla digital.</p>
            </div>

            <div class="ocr-info-box">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;margin-top:1px;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                <div>El sistema lee la foto automáticamente y precarga la tabla de abajo — pero como leer letra manuscrita nunca es 100% exacto, <strong>siempre revisá y corregí</strong> antes de guardar. Nada se guarda hasta que apretás "Guardar asistencia".</div>
            </div>

            <div class="card" style="margin-bottom:1rem;">
                <div class="card-body" style="padding:1rem 1.2rem;">
                    <div style="display:grid;grid-template-columns:2fr 1fr;gap:.8rem;margin-bottom:.8rem;">
                        <div class="field" style="margin:0;">
                            <label>Materia</label>
                            <select id="ocrMateria" onchange="ocrCambiarMateria()">
                                <option value="">— Seleccionar materia —</option>
                                <?php foreach($materias as $m): ?>
                                <option value="<?=$m['id']?>"><?=htmlspecialchars(($m['codigo']??'').' · '.$m['nombre'])?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field" style="margin:0;">
                            <label>Fecha</label>
                            <input type="date" id="ocrFecha" value="<?=date('Y-m-d')?>">
                        </div>
                    </div>
                    <div class="field" style="margin:0 0 .8rem;">
                        <label>¿Qué marca la hoja?</label>
                        <div style="display:flex;gap:1.2rem;flex-wrap:wrap;">
                            <label class="ocr-radio"><input type="radio" name="ocrModo" value="asistieron" checked> La hoja marca quiénes <strong>asistieron</strong></label>
                            <label class="ocr-radio"><input type="radio" name="ocrModo" value="faltaron"> La hoja marca quiénes <strong>faltaron</strong></label>
                        </div>
                    </div>
                    <div class="field" style="margin:0;">
                        <label>Foto de la hoja</label>
                        <input type="file" id="ocrFoto" accept="image/png,image/jpeg,image/webp" capture="environment" onchange="ocrPreviewFoto()">
                        <div id="ocrFotoPreviewWrap" style="display:none;margin-top:.6rem;">
                            <img id="ocrFotoPreview" style="max-width:220px;max-height:220px;border-radius:10px;border:1.5px solid var(--border);">
                        </div>
                    </div>
                    <div style="margin-top:1rem;display:flex;gap:.6rem;align-items:center;flex-wrap:wrap;">
                        <button class="btn btn-primary" id="btnOcrProcesar" onclick="ocrProcesarFoto()" disabled>
                            <i class="fas fa-camera"></i> Procesar foto
                        </button>
                        <div id="ocrProgressWrap" style="display:none;flex:1;min-width:180px;">
                            <div class="pct-bar"><div class="pct-fill" id="ocrProgressBar" style="width:0%;background:var(--lime2);"></div></div>
                            <div id="ocrProgressLbl" style="font-size:.72rem;color:var(--muted);margin-top:3px;">Leyendo imagen…</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Panel de revisión (aparece tras procesar) -->
            <div id="ocrReview" style="display:none;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.7rem;flex-wrap:wrap;gap:.6rem;">
                    <div>
                        <div style="font-weight:700;font-size:.9rem;color:var(--ink);">Revisá y corregí antes de guardar</div>
                        <div id="ocrReviewSummary" style="font-size:.78rem;color:var(--muted);margin-top:2px;"></div>
                    </div>
                    <button class="btn btn-secondary btn-sm" onclick="ocrVerTexto()">Ver texto detectado</button>
                </div>
                <div class="card">
                    <div class="tbl-wrap" style="padding:0;">
                        <table>
                            <thead><tr>
                                <th style="width:44px;"></th>
                                <th>Alumno</th>
                                <th style="text-align:center;width:110px;">¿En la hoja?</th>
                                <th style="text-align:center;width:200px;">Estado final</th>
                                <th style="width:160px;">Observación</th>
                            </tr></thead>
                            <tbody id="ocrBody"></tbody>
                        </table>
                    </div>
                </div>
                <div style="margin-top:1rem;">
                    <button class="btn btn-primary" id="btnOcrGuardar" onclick="ocrGuardar()">
                        <i class="fas fa-save"></i> Guardar asistencia
                    </button>
                </div>
            </div>

            <!-- Historial de hojas cargadas -->
            <div style="margin-top:1.6rem;">
                <div style="font-weight:700;font-size:.88rem;color:var(--ink);margin-bottom:.6rem;">Hojas cargadas recientemente</div>
                <div class="card"><div id="ocrHojasList" style="padding:1rem;"><span class="spin"></span></div></div>
            </div>
        </div>

        <!-- ============================================== -->
        <!-- VISTA: MATERIAS (Mis Cursos)                   -->
        <!-- ============================================== -->
        <div id="view-materias" class="view-section">
            <h2 style="font-family:'Playfair Display',serif; font-size:1.6rem; color:var(--ink); margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid var(--border);">Mis Cursos y Materias</h2>
            
            <?php if($docente_id === 0 && !in_array($_SESSION['rol'], ['superadmin','admin'])): ?>
            <div class="alert-box">
                <i class="fas fa-exclamation-triangle"></i>
                <div>
                    <strong style="color:var(--amber); font-size:.9rem; display:block; margin-bottom:.2rem;">Perfil Incompleto</strong>
                    <p style="font-size:.8rem; color:var(--muted);">El sistema no encuentra tus materias porque falta tu registro oficial en la tabla de <b>docentes</b> con la cédula <b><?= htmlspecialchars($usuario_db['cedula']) ?></b>.</p>
                </div>
            </div>
            <?php endif; ?>

            <div class="grid-cards">
                <?php foreach($materias as $m): ?>
                <div class="card" style="display: flex; flex-direction: column;">
                    <div style="height: 100px; background: var(--ink); display: flex; align-items: center; justify-content: center; position: relative; overflow: hidden;">
                        <i class="fas fa-chalkboard" style="font-size: 4rem; color: rgba(255,255,255,.05); position: absolute; transform: rotate(-10deg) scale(1.2); transition: transform 0.3s;"></i>
                    </div>
                    <div class="card-body" style="flex: 1; display: flex; flex-direction: column;">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: .4rem;">
                            <h3 style="font-weight: 700; color: var(--ink); font-size: 1.1rem; line-height: 1.2;"><?= htmlspecialchars($m['nombre']) ?></h3>
                            <span class="badge" style="background:var(--cream); border:1px solid var(--border); color:var(--muted);"><?= htmlspecialchars($m['codigo']) ?></span>
                        </div>
                        <p style="font-size: .8rem; color: var(--muted); margin-bottom: 1.5rem;"><i class="far fa-clock"></i> <?= htmlspecialchars($m['dias'] . ' · ' . substr($m['hora_inicio']??'00:00',0,5)) ?></p>
                        
                        <div style="margin-top: auto;">
                            <button onclick="irAlChatMateria(<?= $m['id'] ?>)" class="btn btn-secondary" style="width: 100%; justify-content: center;">Abrir Foro de Discusión</button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- ============================================== -->
        <!-- VISTA: BIBLIOTECA                              -->
        <!-- ============================================== -->
        <div id="view-biblioteca" class="view-section">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;padding-bottom:1rem;border-bottom:1px solid var(--border);flex-wrap:wrap;gap:.8rem;">
                <h2 style="font-family:'Playfair Display',serif;font-size:1.6rem;color:var(--ink);margin:0;">Mi Biblioteca</h2>
                <button onclick="abrirNuevoLibroDocente()" class="btn btn-primary"><i class="fas fa-plus"></i> Nuevo Libro</button>
            </div>
            <p style="font-size:.82rem;color:var(--muted);margin-bottom:1.2rem;">Los libros que subís acá aparecen en el catálogo que ven los alumnos en su portal. Solo vos podés editar o quitar los tuyos.</p>

            <div id="libDocenteEmpty" style="display:none;text-align:center;padding:3rem 1rem;color:var(--muted);">
                <i class="fas fa-book" style="font-size:2.2rem;opacity:.3;margin-bottom:.8rem;display:block;"></i>
                Todavía no subiste ningún libro.
            </div>
            <div class="grid-cards" id="libDocenteGrid">
                <div style="padding:2rem;color:var(--muted);"><span class="spin"></span></div>
            </div>
        </div>

        <!-- MODAL: NUEVO/EDITAR LIBRO -->
        <div id="modal-libro-docente" class="modal-backdrop">
            <div class="modal">
                <div class="modal-head">
                    <h3 id="libDocenteModalTitulo">Nuevo Libro</h3>
                    <button class="modal-close" onclick="closeModal('modal-libro-docente')"><i class="fas fa-times"></i></button>
                </div>
                <div class="modal-body">
                    <form id="form-libro-docente" onsubmit="guardarLibroDocente(event)">
                        <input type="hidden" id="libD-id">
                        <div class="form-grid" style="margin-bottom:1rem;">
                            <div class="field" style="grid-column:1/-1;"><label>Título *</label><input id="libD-titulo" placeholder="El Peregrino"></div>
                            <div class="field"><label>Autor</label><input id="libD-autor" placeholder="John Bunyan"></div>
                            <div class="field"><label>Categoría</label><input id="libD-categoria" placeholder="Teología, Historia…"></div>
                            <div class="field" style="grid-column:1/-1;"><label>Descripción</label><textarea id="libD-descripcion" rows="3"></textarea></div>
                            <div class="field"><label>Precio (0 = gratis)</label><input id="libD-precio" type="number" min="0" step="0.01" value="0"></div>
                            <div class="field"><label>Portada (imagen, opcional)</label><input type="file" id="libD-portada" accept="image/png,image/jpeg,image/webp"></div>
                            <div class="field" style="grid-column:1/-1;"><label>Archivo del libro (PDF o EPUB) <span id="libD-archivoReq">*</span></label><input type="file" id="libD-archivo" accept=".pdf,.epub"></div>
                        </div>
                    </form>
                </div>
                <div class="modal-foot">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('modal-libro-docente')">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="btnGuardarLibroDocente" onclick="document.getElementById('form-libro-docente').requestSubmit()">Publicar</button>
                </div>
            </div>
        </div>

        <!-- ============================================== -->
        <!-- VISTA: CHAT/FORO                               -->
        <!-- ============================================== -->
        <div id="view-chat" class="view-section">
            <h2 style="font-family:'Playfair Display',serif; font-size:1.6rem; color:var(--ink); margin-bottom: 1.5rem;">Foros Académicos</h2>
            
            <div class="card chat-layout">
                <!-- Lista de Foros -->
                <div class="chat-sidebar">
                    <div style="padding: 1.2rem; border-bottom: 1px solid var(--border); background: var(--paper);">
                        <h3 style="font-family:'Playfair Display',serif; font-size: 1rem; color: var(--ink);">Salas Activas</h3>
                    </div>
                    <div style="flex: 1; overflow-y: auto;">
                        <?php foreach($materias as $index => $m): ?>
                        <div class="chat-item materia-foro-<?= $m['id'] ?> <?= $index === 0 ? 'active-chat' : '' ?>" onclick="selectChatSubject(<?= $m['id'] ?>, '<?= htmlspecialchars($m['nombre'], ENT_QUOTES) ?>', this)">
                            <div style="width: 34px; height: 34px; border-radius: 50%; background: var(--ink); color: #fff; display: flex; align-items: center; justify-content: center; font-size: .75rem; font-weight: 700; flex-shrink: 0;">
                                <?= substr($m['nombre'], 0, 2) ?>
                            </div>
                            <h4 style="font-size: .85rem; font-weight: 600; color: var(--ink); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= htmlspecialchars($m['nombre']) ?></h4>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Área de Mensajes -->
                <div class="chat-main">
                    <div style="padding: 1.2rem; background: var(--paper); border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: .8rem; z-index: 10;">
                        <div style="width: 38px; height: 38px; border-radius: 50%; background: rgba(57,255,20,.15); color: var(--lime2); display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-users"></i>
                        </div>
                        <div>
                            <h3 id="chat-title" style="font-family:'Playfair Display',serif; font-size: 1.1rem; color: var(--ink); line-height: 1.1;"><?= !empty($materias) ? htmlspecialchars($materias[0]['nombre']) : 'Seleccione una materia' ?></h3>
                            <span style="font-size: .65rem; color: var(--muted); text-transform: uppercase; letter-spacing: 1px; font-weight: 700;">Foro Oficial</span>
                        </div>
                    </div>
                    
                    <div id="chat-messages-container" style="flex: 1; overflow-y: auto; padding: 1.5rem; display: flex; flex-direction: column; gap: 1rem;"></div>

                    <div style="padding: 1.2rem; background: var(--paper); border-top: 1px solid var(--border);">
                        <div id="reply-indicator" style="display: none; font-size: .75rem; color: var(--muted); background: var(--cream); padding: .4rem .8rem; border-radius: 6px; margin-bottom: .8rem; align-items: center; justify-content: space-between; border: 1px solid var(--border);">
                            <span><i class="fas fa-reply" style="margin-right: 4px;"></i> Respondiendo a <strong id="reply-to-name" style="color:var(--ink);"></strong></span>
                            <button type="button" onclick="cancelarRespuesta()" style="background:none; border:none; color:var(--red); cursor:pointer;"><i class="fas fa-times"></i></button>
                        </div>
                        <form onsubmit="sendChat(event)" style="display: flex; gap: .8rem; align-items: flex-end;">
                            <input type="hidden" id="chat-reply-to-id" name="respuesta_a" value="">
                            <input type="hidden" id="chat-materia-id" name="materia_id" value="<?= !empty($materias) ? $materias[0]['id'] : 0 ?>">
                            
                            <div class="field" style="flex: 1; margin: 0;">
                                <input type="text" id="chat-input-text" name="mensaje" placeholder="Escribe tu aporte en el foro..." autocomplete="off" style="border-radius: 20px; padding: .8rem 1.2rem;">
                            </div>
                            <button type="submit" class="btn btn-primary" style="height: 43px; width: 43px; padding: 0; display: flex; justify-content: center; align-items: center; border-radius: 50%;">
                                <i class="fas fa-paper-plane" style="margin-left: -2px;"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================== -->
        <!-- VISTA: AULA VIRTUAL                            -->
        <!-- ============================================== -->
        <div id="view-aula" class="view-section">
            <h2 style="font-family:'Playfair Display',serif; font-size:1.6rem; color:var(--ink); margin-bottom: .3rem; padding-bottom: 1rem; border-bottom: 1px solid var(--border);">Aula Virtual</h2>
            <p style="font-size:.85rem;color:var(--muted);margin:.8rem 0 1.2rem;">Anuncios, materiales, actividades, clases en vivo y grabadas de cada materia — todo en un solo lugar, sin tener que abrir otra pestaña.</p>

            <div class="field" style="max-width:460px;margin-bottom:1.5rem;">
                <label>Materia</label>
                <select id="aulaMateriaSel" onchange="cargarAulaMateria()">
                    <option value="">— Selecciona una materia —</option>
                    <?php foreach($materias as $m): ?>
                    <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['codigo'].' · '.$m['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php if(empty($materias)): ?>
            <div style="padding: 4rem 1rem; text-align: center; border: 2px dashed var(--border); border-radius: 14px;">
                <i class="fas fa-folder-open" style="font-size: 3rem; color: var(--border); margin-bottom: 1rem;"></i>
                <h3 style="font-family:'Playfair Display',serif; font-size: 1.2rem; color: var(--ink);">Sin aulas asignadas</h3>
                <p style="color: var(--muted); font-size: .9rem;">El administrador aún no te ha asignado materias.</p>
            </div>
            <?php else: ?>

            <div id="aulaEmpty" style="padding: 3rem 1rem; text-align: center; border: 2px dashed var(--border); border-radius: 14px; color: var(--muted);">
                Selecciona una materia arriba para ver y gestionar su aula.
            </div>

            <div id="aulaPanel" style="display:none;">
                <div class="tabs-nav" style="margin-bottom: 1.5rem;flex-wrap:wrap;">
                    <button class="tab-btn active" data-aulatab="anuncios" onclick="switchAulaTab('anuncios', this)">📢 Anuncios</button>
                    <button class="tab-btn" data-aulatab="materiales" onclick="switchAulaTab('materiales', this)">📎 Materiales</button>
                    <button class="tab-btn" data-aulatab="actividades" onclick="switchAulaTab('actividades', this)">📝 Actividades</button>
                    <button class="tab-btn" data-aulatab="vivo" onclick="switchAulaTab('vivo', this)">🔴 En Vivo</button>
                    <button class="tab-btn" data-aulatab="grabadas" onclick="switchAulaTab('grabadas', this)">🎬 Grabadas</button>
                </div>

                <!-- ANUNCIOS -->
                <div id="aulaTab-anuncios" class="aula-tabpane">
                    <div style="display:flex;justify-content:flex-end;margin-bottom:1rem;">
                        <button class="btn btn-primary btn-sm" onclick="abrirModalAnuncio()"><i class="fas fa-plus"></i> Nuevo anuncio</button>
                    </div>
                    <div id="aulaAnunciosList" class="grid-cards"><div style="text-align:center;padding:2rem;color:var(--muted);"><span class="spin"></span></div></div>
                </div>

                <!-- MATERIALES -->
                <div id="aulaTab-materiales" class="aula-tabpane" style="display:none;">
                    <div style="display:flex;justify-content:flex-end;margin-bottom:1rem;">
                        <button class="btn btn-primary btn-sm" onclick="abrirModalMaterial()"><i class="fas fa-upload"></i> Subir material</button>
                    </div>
                    <table class="tbl" style="width:100%;">
                        <thead><tr><th>Título</th><th>Tipo</th><th>Subido</th><th></th></tr></thead>
                        <tbody id="aulaMaterialesList"><tr><td colspan="4" style="text-align:center;padding:2rem;"><span class="spin"></span></td></tr></tbody>
                    </table>
                </div>

                <!-- ACTIVIDADES -->
                <div id="aulaTab-actividades" class="aula-tabpane" style="display:none;">
                    <p style="font-size:.78rem;color:var(--muted);margin-bottom:1rem;">Notas directas por actividad (examen, taller, quiz…) — distinto del Plan de Notas, que es para tareas con entrega de archivo.</p>
                    <div style="display:flex;justify-content:flex-end;margin-bottom:1rem;">
                        <button class="btn btn-primary btn-sm" onclick="abrirModalActividad()"><i class="fas fa-plus"></i> Nueva actividad</button>
                    </div>
                    <table class="tbl" style="width:100%;">
                        <thead><tr><th>Título</th><th>Tipo</th><th>Nota máx.</th><th>Fecha</th><th></th></tr></thead>
                        <tbody id="aulaActividadesList"><tr><td colspan="5" style="text-align:center;padding:2rem;"><span class="spin"></span></td></tr></tbody>
                    </table>
                </div>

                <!-- EN VIVO -->
                <div id="aulaTab-vivo" class="aula-tabpane" style="display:none;">
                    <div style="display:flex;justify-content:flex-end;margin-bottom:1rem;">
                        <button class="btn btn-primary btn-sm" onclick="abrirModalVivo()"><i class="fas fa-plus"></i> Nueva clase en vivo</button>
                    </div>
                    <div id="aulaVivoList" class="grid-cards"><div style="text-align:center;padding:2rem;color:var(--muted);"><span class="spin"></span></div></div>
                </div>

                <!-- GRABADAS -->
                <div id="aulaTab-grabadas" class="aula-tabpane" style="display:none;">
                    <div style="display:flex;justify-content:flex-end;margin-bottom:1rem;">
                        <button class="btn btn-primary btn-sm" onclick="abrirModalGrabada()"><i class="fas fa-plus"></i> Agregar clase grabada</button>
                    </div>
                    <div id="aulaGrabadasList" class="grid-cards"><div style="text-align:center;padding:2rem;color:var(--muted);"><span class="spin"></span></div></div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- ============================================== -->
        <!-- VISTA: CHAT DEL STAFF (admin ↔ docentes, sin materia) -->
        <!-- ============================================== -->
        <div id="view-chat-staff" class="view-section">
            <h2 style="font-family:'Playfair Display',serif; font-size:1.6rem; color:var(--ink); margin-bottom: .3rem; padding-bottom: 1rem; border-bottom: 1px solid var(--border);">Chat del Staff</h2>
            <p style="font-size:.85rem;color:var(--muted);margin:.8rem 0 1.2rem;">Avisos de dirección y preguntas directas entre administración y docentes — visible para todo el staff, sin necesidad de estar en una materia.</p>
            <div class="card" style="height:60vh;min-height:400px;display:flex;flex-direction:column;">
                <div id="staffchat-box" style="flex:1;overflow-y:auto;padding:1rem 1.2rem;display:flex;flex-direction:column;gap:.6rem;background:var(--paper);">
                    <div style="text-align:center;padding:2rem;color:var(--muted);"><span class="spin"></span> Cargando chat...</div>
                </div>
                <div id="staffchat-reply-indicator" style="display:none;padding:.5rem 1.2rem;background:var(--cream);border-top:1px solid var(--border);font-size:.8rem;color:var(--muted);align-items:center;justify-content:space-between;">
                    <span>Respondiendo a <strong id="staffchat-reply-name"></strong></span>
                    <button type="button" onclick="cancelReplyStaffChat()" style="background:none;border:none;cursor:pointer;color:var(--muted);font-size:1rem;">✕</button>
                </div>
                <form id="staffchat-form" style="display:flex;gap:.6rem;padding:1rem 1.2rem;border-top:1px solid var(--border);">
                    <input type="hidden" id="staffchat-respuesta-a" value="">
                    <input type="text" id="staffchat-input" placeholder="Escribe un mensaje para el staff…" autocomplete="off"
                        style="flex:1;padding:.7rem 1rem;border:1.5px solid var(--border);border-radius:10px;font-size:.9rem;outline:none;">
                    <button type="submit" class="btn btn-primary">Enviar</button>
                </form>
            </div>
        </div>

        <!-- ============================================== -->
        <!-- VISTA: PERFIL                                  -->
        <!-- ============================================== -->
        <div id="view-perfil" class="view-section">
            <h2 style="font-family:'Playfair Display',serif; font-size:1.6rem; color:var(--ink); margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid var(--border);">Configuración de Perfil</h2>

            <div class="card" style="max-width: 700px; margin: 0 auto 1.5rem;">
                <div class="card-body" style="padding: 1.5rem 2.5rem; display:flex; align-items:center; justify-content:space-between; gap:1rem; flex-wrap:wrap;">
                    <div>
                        <h3 style="font-size: 1rem; margin-bottom: .3rem;">Constancia de Trabajo</h3>
                        <p style="font-size: .82rem; color: var(--muted); margin:0;">Documento oficial que certifica que prestás servicios como docente en el instituto.</p>
                    </div>
                    <a href="api/export_constancia.php?tipo=trabajo" target="_blank" class="btn btn-secondary" style="white-space:nowrap;"><i class="fas fa-file-pdf"></i> Generar PDF</a>
                </div>
            </div>

            <div class="card" style="max-width: 700px; margin: 0 auto;">
                <div class="card-body" style="padding: 2.5rem;">
                    <!-- SECCIÓN: FOTO DE PERFIL -->
                    <div style="display: flex; flex-direction: column; align-items: center; margin-bottom: 2.5rem; border-bottom: 1px solid var(--border); padding-bottom: 2rem;">
                        <div style="width: 120px; height: 120px; border-radius: 50%; background: var(--cream); margin-bottom: 1.2rem; position: relative; overflow: hidden; border: 4px solid #fff; box-shadow: 0 8px 24px rgba(26,77,46,.12);">
                            <?php if($foto_perfil): ?>
                                <img id="avatar-preview" src="<?= htmlspecialchars($foto_perfil) ?>" style="width:100%;height:100%;object-fit:cover;">
                            <?php else: ?>
                                <div id="avatar-preview-fallback" style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:2.8rem;font-weight:bold;color:var(--muted);"><?= $inicial ?></div>
                            <?php endif; ?>
                        </div>
                        <label class="btn btn-secondary btn-sm" style="cursor: pointer; position: relative;">
                            <i class="fas fa-camera"></i> Cambiar Foto
                            <input type="file" id="perfil-foto-input" accept="image/*" style="opacity: 0; position: absolute; inset: 0; cursor: pointer;" onchange="subirFotoPerfilDocente(this)">
                        </label>
                        <span style="font-size: .7rem; color: var(--muted); margin-top: .6rem;">Soporta JPG, PNG, GIF o WebP. Máx 3MB.</span>
                    </div>

                    <!-- SECCIÓN: DATOS GENERALES -->
                    <form id="form-perfil-docente" onsubmit="guardarPerfilDocente(event)">
                        <div class="form-grid" style="margin-bottom: 1.5rem;">
                            <div class="field">
                                <label>Nombre de Usuario</label>
                                <input type="text" name="usuario" value="<?= htmlspecialchars($usuario_db['usuario'] ?? '') ?>" required>
                            </div>
                            <div class="field">
                                <label>Cédula (Solo lectura)</label>
                                <input type="text" value="<?= htmlspecialchars($usuario_db['cedula'] ?? '') ?>" readonly style="background: var(--cream); opacity: .8; border-color: transparent;">
                            </div>
                            <div class="field field-full">
                                <label>Correo Electrónico</label>
                                <input type="email" name="correo" value="<?= htmlspecialchars($usuario_db['correo'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="divider"></div>
                        <div style="display: flex; justify-content: flex-end;">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Guardar Cambios</button>
                        </div>
                    </form>

                    <!-- SECCIÓN: CAMBIAR CONTRASEÑA -->
                    <div class="divider" style="margin: 2rem 0;"></div>
                    <h3 style="font-size: 1.1rem; margin-bottom: 1.2rem;">Cambiar contraseña</h3>
                    <form id="form-pwd-docente" onsubmit="cambiarPwdDocente(event)">
                        <div class="form-grid" style="margin-bottom: 1.5rem;">
                            <div class="field">
                                <label>Contraseña actual</label>
                                <input type="password" id="dp-actual" placeholder="••••••••" required>
                            </div>
                            <div class="field"></div>
                            <div class="field">
                                <label>Nueva contraseña</label>
                                <input type="password" id="dp-nueva" placeholder="mín. 8 car., mayús., minús. y nº" required>
                            </div>
                            <div class="field">
                                <label>Repetir nueva</label>
                                <input type="password" id="dp-repetir" placeholder="repite" required>
                            </div>
                        </div>
                        <div style="display: flex; justify-content: flex-end;">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-key"></i> Actualizar contraseña</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- ============================================== -->
        <!-- VISTA: ASIGNAR MATERIAS (Solo Admins)          -->
        <!-- ============================================== -->
        <?php if(in_array($_SESSION['rol'], ['superadmin', 'admin'])): ?>
        <div id="view-admin-docentes" class="view-section">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <div>
                    <h2 style="font-family:'Playfair Display',serif; font-size:1.6rem; color:var(--ink);">Asignación de Materias</h2>
                    <p style="font-size:.85rem; color:var(--muted);">Vincula materias a los docentes registrados en el sistema.</p>
                </div>
                <button onclick="openModal('modal-asignar-materia')" class="btn btn-primary"><i class="fas fa-link"></i> Asignar Materia</button>
            </div>
            
            <div class="grid-cards">
                <?php foreach($todos_docentes as $doc): 
                    $mat_asignadas = [];
                    $stmt_ma = mysqli_prepare($con, "SELECT m.nombre FROM materias m JOIN materia_docente md ON m.id = md.materia_id WHERE md.docente_id = ?");
                    mysqli_stmt_bind_param($stmt_ma, "i", $doc['id']);
                    mysqli_stmt_execute($stmt_ma);
                    $res_ma = mysqli_stmt_get_result($stmt_ma);
                    while($rm = mysqli_fetch_assoc($res_ma)) { $mat_asignadas[] = $rm['nombre']; }
                ?>
                <div class="card" style="display: flex; flex-direction: column;">
                    <div class="card-body" style="flex: 1; display: flex; flex-direction: column;">
                        <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1.2rem;">
                            <div style="width: 48px; height: 48px; border-radius: 50%; background: rgba(26,77,46,.1); color: var(--ink); display: flex; align-items: center; justify-content: center; font-size: 1.2rem; font-weight: 700;">
                                <?= strtoupper(substr($doc['nombre'], 0, 1)) ?>
                            </div>
                            <div>
                                <h3 style="font-weight: 700; color: var(--ink); font-size: 1.05rem; line-height: 1.1;"><?= htmlspecialchars($doc['nombre'] . ' ' . $doc['apellido']) ?></h3>
                                <p style="font-size: .75rem; color: var(--muted);">C.I: <?= htmlspecialchars($doc['cedula']) ?></p>
                            </div>
                        </div>
                        
                        <div style="flex: 1;">
                            <div class="section-label">Materias Asignadas</div>
                            <?php if(!empty($mat_asignadas)): ?>
                                <ul style="list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: .3rem;">
                                <?php foreach($mat_asignadas as $ma): ?>
                                    <li style="font-size: .82rem; color: var(--ink); display: flex; align-items: center; gap: .4rem;">
                                        <i class="fas fa-check" style="color: var(--green); font-size: .7rem;"></i> <?= htmlspecialchars($ma) ?>
                                    </li>
                                <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <p style="font-size: .8rem; color: var(--amber); font-style: italic;"><i class="fas fa-exclamation-triangle"></i> Sin materias</p>
                            <?php endif; ?>
                        </div>
                        
                        <div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--border);">
                            <button onclick="openModalAsignarMateria(<?= $doc['id'] ?>)" class="btn btn-secondary" style="width: 100%; justify-content: center; font-size: .8rem;">Vincular Nueva Materia</button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    </main>

    <!-- 3. MODALES -->

    <!-- MODAL CALIFICAR ENTREGA -->
    <div id="modal-calificar" class="modal-backdrop">
        <div class="modal">
            <div class="modal-head">
                <h3>Asignar Calificación</h3>
                <button class="modal-close" onclick="closeModal('modal-calificar')">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
            <div class="modal-body">
                <form id="form-calificar" onsubmit="submitCalificacion(event)">
                    <input type="hidden" name="csrf_token" value="<?=htmlspecialchars(csrf_token())?>">
                    <input type="hidden" name="entrega_id" id="modal-cal-id">
                    
                    <div style="background: var(--cream); border: 1px solid var(--border); padding: 1rem; border-radius: 8px; margin-bottom: 1.2rem;">
                        <div class="section-label" style="margin-bottom: .2rem;">Alumno</div>
                        <p id="modal-cal-alumno" style="font-size: .95rem; font-weight: 700; color: var(--ink); margin-bottom: .8rem;"></p>
                        <div class="section-label" style="margin-bottom: .2rem;">Tarea</div>
                        <p id="modal-cal-tarea" style="font-size: .9rem; color: var(--ink);"></p>
                    </div>
                    
                    <div class="field" style="margin-bottom: .4rem;">
                        <label style="text-align: center; font-size: .8rem;">Nota Asignada (sobre 20 — mínimo 15 para aprobar)</label>
                        <input type="number" id="modal-cal-nota" name="nota" min="0" max="20" step="0.1" required style="font-size: 1.8rem; text-align: center; font-weight: 700; padding: 1rem;" oninput="actualizarEstadoCalificar()">
                    </div>
                    <p id="modal-cal-estado" style="text-align:center;font-size:.78rem;font-weight:700;margin-bottom:1.2rem;height:1.1em;"></p>

                    <div class="field" style="margin-bottom: .5rem;">
                        <label>Mensaje para el alumno (opcional)</label>
                        <textarea id="modal-cal-obs" name="observacion" rows="3" maxlength="1000" placeholder="Felicitalo, corregilo, o dejale una observación sobre su trabajo…"></textarea>
                    </div>
                    <div style="display:flex;gap:.4rem;flex-wrap:wrap;margin-bottom:1.5rem;">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="rellenarObsCalificar('¡Excelente trabajo! Seguí así.')">👏 Felicitar</button>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="rellenarObsCalificar('Buen trabajo, pero revisá nuevamente este tema antes del próximo examen.')">✏️ A corregir</button>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="rellenarObsCalificar('Entrega incompleta — faltan puntos por desarrollar.')">⚠️ Incompleto</button>
                    </div>

                    <div class="modal-foot" style="padding: 0; border: none; background: transparent;">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-calificar')">Cancelar</button>
                        <button type="submit" class="btn btn-primary" id="btn-guardar-calificacion">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL NUEVA TAREA / SUBIR ACTIVIDAD (AHORA CON ARCHIVOS MULTIMEDIA) -->
    <div id="modal-nueva-tarea" class="modal-backdrop">
        <div class="modal md">
            <div class="modal-head">
                <h3>Subir Actividad / Material</h3>
                <button class="modal-close" onclick="closeModal('modal-nueva-tarea')">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
            <div class="modal-body">
                <!-- Se agregó enctype="multipart/form-data" para permitir subida de archivos -->
                <form id="form-nueva-tarea" onsubmit="submitNuevaTarea(event)" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?=htmlspecialchars(csrf_token())?>">
                    <div class="form-grid" style="margin-bottom: 1.5rem;">
                        <div class="field field-full">
                            <label>Materia Asignada</label>
                            <select name="materia_id" required>
                                <?php foreach($materias as $m): ?>
                                    <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="field field-full">
                            <label>Título de la Actividad</label>
                            <input type="text" name="titulo" required placeholder="Ej. Ensayo sobre Historia o Material de Lectura...">
                        </div>
                        
                        <div class="field field-full">
                            <label>Archivo Adjunto (Opcional - PDF, Word, Excel, PPT, Zip, Img)</label>
                            <input type="file" name="archivo" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.zip,.rar" style="padding: .5rem; background: var(--paper); cursor: pointer;">
                        </div>

                        <div class="field field-full">
                            <label>Instrucciones / Descripción</label>
                            <textarea name="descripcion" rows="3" placeholder="Instrucciones para los alumnos..."></textarea>
                        </div>
                        <div class="field">
                            <label>Fecha Límite de Entrega (alumno)</label>
                            <input type="datetime-local" name="fecha_limite" id="nt-fecha-entrega" required onchange="validarFechasNuevaTarea()">
                        </div>
                        <div class="field">
                            <label>Fecha Límite de Corrección (vos)</label>
                            <input type="datetime-local" name="fecha_limite_correccion" id="nt-fecha-correccion" required onchange="validarFechasNuevaTarea()">
                        </div>
                        <p id="nt-fechas-error" style="display:none;grid-column:1/-1;margin:0;color:var(--red);font-size:.8rem;">La fecha de corrección debe ser posterior a la de entrega.</p>
                        <div class="field field-full">
                            <label>% de la nota final de la materia (opcional)</label>
                            <input type="number" name="porcentaje" min="0" max="100" step="1" placeholder="Ej. 20 — se usa en el Plan de Notas">
                        </div>
                        <input type="hidden" name="nota_maxima" value="20">
                    </div>
                    <div class="modal-foot" style="padding: 0; border: none; background: transparent;">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-nueva-tarea')">Cancelar</button>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-upload"></i> Publicar Actividad</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODALES DE AULA VIRTUAL (consolidada dentro del portal) -->
    <div id="modal-aula-anuncio" class="modal-backdrop">
        <div class="modal">
            <div class="modal-head">
                <h3 id="aulaAnuncioTitulo">Nuevo anuncio</h3>
                <button class="modal-close" onclick="closeModal('modal-aula-anuncio')"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
            </div>
            <div class="modal-body">
                <form onsubmit="guardarAulaAnuncio(event)">
                    <input type="hidden" id="aulaAnuncioId" value="">
                    <div class="field"><label>Título</label><input type="text" id="aulaAnuncioTituloInput" maxlength="150" required></div>
                    <div class="field"><label>Contenido</label><textarea id="aulaAnuncioContenido" rows="5" required></textarea></div>
                    <div class="field" style="flex-direction:row;align-items:center;gap:.5rem;">
                        <input type="checkbox" id="aulaAnuncioFijado" style="width:auto;"> <label style="margin:0;">Fijar arriba del muro</label>
                    </div>
                    <div class="modal-foot" style="padding:0;border:none;background:transparent;">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-aula-anuncio')">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Publicar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="modal-aula-material" class="modal-backdrop">
        <div class="modal">
            <div class="modal-head">
                <h3>Subir material</h3>
                <button class="modal-close" onclick="closeModal('modal-aula-material')"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
            </div>
            <div class="modal-body">
                <form onsubmit="guardarAulaMaterial(event)">
                    <div class="field"><label>Título</label><input type="text" id="aulaMaterialTitulo" maxlength="150" required></div>
                    <div class="field"><label>Descripción (opcional)</label><textarea id="aulaMaterialDescripcion" rows="3"></textarea></div>
                    <div class="field"><label>Archivo (PDF, Word, PowerPoint, Excel, TXT, CSV, ZIP o imagen — máx. 25MB)</label><input type="file" id="aulaMaterialArchivo" required></div>
                    <div class="modal-foot" style="padding:0;border:none;background:transparent;">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-aula-material')">Cancelar</button>
                        <button type="submit" class="btn btn-primary" id="btnAulaMaterialSubir">Subir</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="modal-aula-actividad" class="modal-backdrop">
        <div class="modal">
            <div class="modal-head">
                <h3>Nueva actividad</h3>
                <button class="modal-close" onclick="closeModal('modal-aula-actividad')"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
            </div>
            <div class="modal-body">
                <form onsubmit="guardarAulaActividad(event)">
                    <div class="field"><label>Título</label><input type="text" id="aulaActTitulo" maxlength="150" required></div>
                    <div class="field"><label>Descripción (opcional)</label><textarea id="aulaActDescripcion" rows="2"></textarea></div>
                    <div class="field"><label>Tipo</label>
                        <select id="aulaActTipo">
                            <option value="actividad">Actividad</option>
                            <option value="taller">Taller</option>
                            <option value="examen">Examen</option>
                            <option value="proyecto">Proyecto</option>
                        </select>
                    </div>
                    <div class="field"><label>Nota máxima</label><input type="number" id="aulaActNotaMax" value="20" min="1" max="100" step="0.5"></div>
                    <div class="field"><label>Fecha</label><input type="date" id="aulaActFecha"></div>
                    <div class="modal-foot" style="padding:0;border:none;background:transparent;">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-aula-actividad')">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="modal-aula-calificar-act" class="modal-backdrop">
        <div class="modal md">
            <div class="modal-head">
                <h3>Calificar: <span id="aulaCalActTitulo"></span></h3>
                <button class="modal-close" onclick="closeModal('modal-aula-calificar-act')"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="aulaCalActId">
                <table class="tbl" style="width:100%;">
                    <thead><tr><th>Alumno</th><th style="width:110px;">Nota</th><th>Observación</th></tr></thead>
                    <tbody id="aulaCalActBody"></tbody>
                </table>
                <div class="modal-foot" style="padding-top:1rem;border:none;background:transparent;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('modal-aula-calificar-act')">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="guardarAulaCalificarAct()">Guardar calificaciones</button>
                </div>
            </div>
        </div>
    </div>

    <div id="modal-aula-vivo" class="modal-backdrop">
        <div class="modal">
            <div class="modal-head">
                <h3>Nueva clase en vivo</h3>
                <button class="modal-close" onclick="closeModal('modal-aula-vivo')"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
            </div>
            <div class="modal-body">
                <form onsubmit="guardarAulaVivo(event)">
                    <div class="field"><label>Título</label><input type="text" id="aulaVivoTitulo" maxlength="150" required></div>
                    <div class="field"><label>Descripción (opcional)</label><textarea id="aulaVivoDescripcion" rows="2"></textarea></div>
                    <div class="field"><label>Fecha y hora</label><input type="datetime-local" id="aulaVivoFecha" required></div>
                    <div class="field"><label>Plataforma</label>
                        <select id="aulaVivoPlataforma" onchange="document.getElementById('aulaVivoUrlField').style.display = this.value==='jitsi' ? 'none' : 'flex';">
                            <option value="jitsi">Jitsi Meet (link automático, sin cuenta)</option>
                            <option value="meet">Google Meet</option>
                            <option value="otro">Otra plataforma</option>
                        </select>
                    </div>
                    <div class="field" id="aulaVivoUrlField" style="display:none;"><label>Link de la reunión</label><input type="url" id="aulaVivoUrl" placeholder="https://..."></div>
                    <div class="modal-foot" style="padding:0;border:none;background:transparent;">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-aula-vivo')">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="modal-aula-grabada" class="modal-backdrop">
        <div class="modal">
            <div class="modal-head">
                <h3>Agregar clase grabada</h3>
                <button class="modal-close" onclick="closeModal('modal-aula-grabada')"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
            </div>
            <div class="modal-body">
                <form onsubmit="guardarAulaGrabada(event)">
                    <div class="field"><label>Título</label><input type="text" id="aulaGrabTitulo" maxlength="150" required></div>
                    <div class="field"><label>Link del video (YouTube, Google Drive o Vimeo)</label><input type="url" id="aulaGrabUrl" placeholder="https://..." required></div>
                    <div class="field"><label>Descripción (opcional)</label><textarea id="aulaGrabDescripcion" rows="2"></textarea></div>
                    <div class="field"><label>Fecha de la clase</label><input type="date" id="aulaGrabFecha"></div>
                    <div class="modal-foot" style="padding:0;border:none;background:transparent;">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-aula-grabada')">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL ASIGNAR MATERIA -->
    <?php if(in_array($_SESSION['rol'], ['superadmin', 'admin'])): ?>
    <div id="modal-asignar-materia" class="modal-backdrop">
        <div class="modal">
            <div class="modal-head">
                <h3>Asignar Materia a Docente</h3>
                <button class="modal-close" onclick="closeModal('modal-asignar-materia')">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
            <div class="modal-body">
                <form id="form-asignar-materia" onsubmit="submitAsignarMateria(event)">
                    <input type="hidden" name="csrf_token" value="<?=htmlspecialchars(csrf_token())?>">
                    <div class="form-grid" style="margin-bottom: 1.5rem;">
                        <div class="field field-full">
                            <label>Seleccionar Docente</label>
                            <select name="docente_id" id="asignar_docente_id" required>
                                <option value="">— Elija un docente —</option>
                                <?php foreach($todos_docentes as $doc): ?>
                                    <option value="<?= $doc['id'] ?>"><?= htmlspecialchars($doc['nombre'] . ' ' . $doc['apellido']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field field-full">
                            <label>Seleccionar Materia</label>
                            <select name="materia_id" required>
                                <option value="">— Elija una materia —</option>
                                <?php foreach($todas_materias as $mat): ?>
                                    <option value="<?= $mat['id'] ?>"><?= htmlspecialchars($mat['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-foot" style="padding: 0; border: none; background: transparent;">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-asignar-materia')">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Vincular Materia</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- WebSocket en vivo (opcional — no-op si no hay VPS configurado) -->
    <script src="assets/ibbs-realtime.js"></script>

    <!-- 4. SCRIPTS LÓGICA FRONTEND -->
    <script>
        // Funciones básicas Modal
        function openModal(id) { document.getElementById(id).classList.add('open'); }
        function closeModal(id) { document.getElementById(id).classList.remove('open'); }

        // Gráficos Chart.js
        document.addEventListener("DOMContentLoaded", function() {
            const chartOptions = { cutout: '75%', plugins: { legend: { display: false }, tooltip: { enabled: true } }, animation: { animateScale: true } };
            
            new Chart(document.getElementById('chartCalificaciones').getContext('2d'), {
                type: 'doughnut',
                data: { labels: ['Aprobados', 'Reprobados', 'Sin nota'], datasets: [{ data: [<?= $cal_aprobados ?>, <?= $cal_reprobados ?>, <?= $cal_sin_nota ?>], backgroundColor: ['#16a34a', '#dc2626', '#cbd5e1'], borderWidth: 0, hoverOffset: 4 }] },
                options: chartOptions
            });

            new Chart(document.getElementById('chartMaterias').getContext('2d'), {
                type: 'doughnut',
                data: { labels: ['En curso', 'Pendiente', 'Culminada'], datasets: [{ data: [<?= $estado_materias['en_curso'] ?? 0 ?>, <?= $estado_materias['pendiente'] ?? 0 ?>, <?= $estado_materias['culminada'] ?? 0 ?>], backgroundColor: ['#2563eb', '#d97706', '#16a34a'], borderWidth: 0, hoverOffset: 4 }] },
                options: chartOptions
            });

            new Chart(document.getElementById('chartAsistencias').getContext('2d'), {
                type: 'doughnut',
                data: { labels: ['Presente', 'Ausente', 'Tardanza', 'Justificado'], datasets: [{ data: [<?= $estado_asistencias['presente'] ?? 0 ?>, <?= $estado_asistencias['ausente'] ?? 0 ?>, <?= $estado_asistencias['tardanza'] ?? 0 ?>, <?= $estado_asistencias['justificado'] ?? 0 ?>], backgroundColor: ['#16a34a', '#dc2626', '#d97706', '#2563eb'], borderWidth: 0, hoverOffset: 4 }] },
                options: chartOptions
            });
        });

        // Navegación (SPA)
        function switchView(viewId, btnElement = null) {
            document.querySelectorAll('.view-section').forEach(el => el.classList.remove('active'));
            document.getElementById('view-' + viewId).classList.add('active');
            
            if(btnElement) {
                document.querySelectorAll('.sb-link').forEach(btn => btn.classList.remove('act'));
                btnElement.classList.add('act');
            }
        }

        // Agrupa las entregas ya calificadas por alumno — un nombre por
        // fila en vez de una tarjeta grande por cada nota, y al hacer
        // clic se despliega la actividad, la nota y el archivo que
        // entregó (para bajarlo si hay alguna duda de qué mandó).
        function hCal(s) { const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML; }

        function renderCalificadasAgrupadas() {
            const cont = document.getElementById('calificadas-agrupadas');
            const cards = [...document.querySelectorAll('.entrega-card[data-estado="calificada"]')];
            if (!cards.length) {
                cont.innerHTML = '<div style="padding:3rem 1rem;text-align:center;border:2px dashed var(--border);border-radius:14px;"><i class="fas fa-filter" style="font-size:2.2rem;color:var(--border);margin-bottom:.8rem;display:block;"></i><p style="color:var(--muted);font-size:.9rem;">Todavía no calificaste ninguna entrega.</p></div>';
                return;
            }

            const porAlumno = {};
            cards.forEach(c => {
                const nombre = c.dataset.alumno;
                (porAlumno[nombre] = porAlumno[nombre] || []).push(c);
            });

            cont.innerHTML = Object.keys(porAlumno).sort().map((nombre, i) => {
                const items = porAlumno[nombre];
                const aprobadas = items.filter(c => c.dataset.aprobada === '1').length;
                const filas = items.map(c => {
                    const aprobada = c.dataset.aprobada === '1';
                    const archivo = c.dataset.archivo;
                    const obs = c.dataset.obs;
                    return `<div style="padding:.8rem 1rem;border-top:1px solid var(--border);display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap;">
                        <div style="flex:1;min-width:180px;">
                            <div style="font-weight:700;font-size:.88rem;color:var(--ink);">${hCal(c.dataset.tarea)}</div>
                            ${obs ? `<div style="font-size:.76rem;color:var(--muted);margin-top:.2rem;"><i class="fas fa-comment-dots"></i> ${hCal(obs)}</div>` : ''}
                        </div>
                        <div style="display:flex;align-items:center;gap:.6rem;flex-shrink:0;">
                            <span class="badge ${aprobada ? 'b-presente' : 'b-ausente'}">Nota: ${hCal(c.dataset.nota)} / 20 · ${aprobada ? 'Aprobado' : 'Reprobado'}</span>
                            ${archivo ? `<a href="${archivo}" target="_blank" class="btn btn-secondary btn-sm"><i class="fas fa-download"></i> Entrega</a>` : '<span style="font-size:.72rem;color:var(--muted);">Sin archivo</span>'}
                        </div>
                    </div>`;
                }).join('');

                return `<div class="card" style="margin-bottom:.8rem;overflow:hidden;">
                    <button type="button" onclick="this.nextElementSibling.style.display = this.nextElementSibling.style.display === 'none' ? 'block' : 'none'; this.querySelector('i.fa-chevron-down')?.classList.toggle('fa-rotate-180')" style="width:100%;background:none;border:none;cursor:pointer;padding:1rem 1.2rem;display:flex;justify-content:space-between;align-items:center;text-align:left;">
                        <span style="font-weight:700;color:var(--ink);"><i class="fas fa-user-graduate" style="margin-right:.5rem;color:var(--muted);"></i>${hCal(nombre)}</span>
                        <span style="display:flex;align-items:center;gap:.8rem;">
                            <span style="font-size:.76rem;color:var(--muted);">${items.length} actividad${items.length===1?'':'es'} · ${aprobadas}/${items.length} aprobadas</span>
                            <i class="fas fa-chevron-down" style="color:var(--muted);transition:transform .15s;"></i>
                        </span>
                    </button>
                    <div style="display:none;">${filas}</div>
                </div>`;
            }).join('');
        }

        // Script para filtrar Entregas
        function filtrarEntregas(tipo, btn) {
            // Actualizar botones de pestaña
            document.querySelectorAll('#view-entregas .tab-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            const grid = document.getElementById('contenedor-entregas');
            const agrupadas = document.getElementById('calificadas-agrupadas');

            if (tipo === 'calificadas') {
                grid.style.display = 'none';
                agrupadas.style.display = 'block';
                renderCalificadasAgrupadas();
                return;
            }
            grid.style.display = '';
            agrupadas.style.display = 'none';

            // Mostrar u ocultar tarjetas según el estado
            const cards = document.querySelectorAll('.entrega-card');
            let visibles = 0;

            cards.forEach(card => {
                const estado = card.getAttribute('data-estado');
                if(tipo === 'todas' || tipo === estado) {
                    card.style.display = 'flex';
                    visibles++;
                } else {
                    card.style.display = 'none';
                }
            });

            // Si no hay tareas visibles, mostrar estado vacío
            let emptyState = document.getElementById('entregas-vacio');
            if(visibles === 0 && cards.length > 0) {
                if(!emptyState) {
                    emptyState = document.createElement('div');
                    emptyState.id = 'entregas-vacio';
                    emptyState.style = 'grid-column: 1 / -1; padding: 4rem 1rem; text-align: center; border: 2px dashed var(--border); border-radius: 14px;';
                    emptyState.innerHTML = '<i class="fas fa-filter" style="font-size: 3rem; color: var(--border); margin-bottom: 1rem;"></i><p style="color: var(--muted); font-size: .9rem;">No hay tareas que coincidan con el filtro.</p>';
                    document.getElementById('contenedor-entregas').appendChild(emptyState);
                }
            } else if(emptyState) {
                emptyState.remove();
            }
        }

        // Foto de perfil: sube de verdad a api/upload_foto.php (antes el
        // formulario posteaba a api/ajax.php con una acción que no
        // existe — nunca guardaba nada, solo mostraba una vista previa
        // que se perdía al recargar).
        async function subirFotoPerfilDocente(input) {
            if (!input.files || !input.files[0]) return;
            const fd = new FormData();
            fd.append('foto', input.files[0]);
            fd.append('tipo', 'usuario');
            const _csrf = document.querySelector('meta[name="csrf-token"]');
            if (_csrf) fd.append('csrf_token', _csrf.content);
            try {
                const r = await fetch('api/upload_foto.php', { method: 'POST', body: fd });
                const d = await r.json();
                if (!d.ok) { Ibbs.error(d.msg || 'No se pudo subir la foto.'); return; }
                const fallback = document.getElementById('avatar-preview-fallback');
                if (fallback) fallback.remove();
                let preview = document.getElementById('avatar-preview');
                if (!preview) {
                    preview = document.createElement('img');
                    preview.id = 'avatar-preview';
                    preview.style = 'width:100%;height:100%;object-fit:cover;';
                    input.closest('.view-section').querySelector('div[style*="border-radius: 50%"]').appendChild(preview);
                }
                preview.src = d.foto + '?t=' + Date.now();
            } catch (e) { Ibbs.error('Error de conexión al subir la foto.'); }
        }

        async function guardarPerfilDocente(e) {
            e.preventDefault();
            const fd = new FormData(e.target);
            fd.append('action', 'perfil_update');
            const _csrf = document.querySelector('meta[name="csrf-token"]');
            if (_csrf) fd.append('csrf_token', _csrf.content);
            try {
                const r = await fetch('api/ajax.php', { method: 'POST', body: fd });
                const d = await r.json();
                if (d.ok) Ibbs.success('Perfil actualizado correctamente.');
                else Ibbs.error(d.msg || 'No se pudo actualizar el perfil.');
            } catch (err) { Ibbs.error('Error de conexión.'); }
        }

        function validarPasswordDocente(pwd) {
            if (pwd.length < 8) return 'La contraseña debe tener al menos 8 caracteres.';
            if (!/[A-Z]/.test(pwd)) return 'Debe contener al menos una mayúscula.';
            if (!/[a-z]/.test(pwd)) return 'Debe contener al menos una minúscula.';
            if (!/[0-9!@#$%^&*()_+\-=[\]{};':",./<>?|`~]/.test(pwd)) return 'Debe contener al menos un número o carácter especial.';
            return null;
        }

        async function cambiarPwdDocente(e) {
            e.preventDefault();
            const actual = document.getElementById('dp-actual').value;
            const nueva = document.getElementById('dp-nueva').value;
            const repetir = document.getElementById('dp-repetir').value;
            if (!actual || !nueva) { Ibbs.warn('Completá todos los campos.'); return; }
            const err = validarPasswordDocente(nueva);
            if (err) { Ibbs.warn(err); return; }
            if (nueva !== repetir) { Ibbs.warn('Las contraseñas nuevas no coinciden.'); return; }
            const fd = new FormData();
            fd.append('action', 'perfil_pwd');
            fd.append('actual', actual);
            fd.append('nueva', nueva);
            const _csrf = document.querySelector('meta[name="csrf-token"]');
            if (_csrf) fd.append('csrf_token', _csrf.content);
            try {
                const r = await fetch('api/ajax.php', { method: 'POST', body: fd });
                const d = await r.json();
                if (d.ok) {
                    Ibbs.success('Contraseña actualizada correctamente.');
                    e.target.reset();
                } else Ibbs.error(d.msg || 'No se pudo actualizar la contraseña.');
            } catch (err) { Ibbs.error('Error de conexión.'); }
        }

        function irAlChatMateria(materiaId) {
            switchView('chat');
            const chatTab = document.querySelector('.materia-foro-' + materiaId);
            if(chatTab) chatTab.click();
            // Activar botón del menú lateral visualmente
            const foroBtn = Array.from(document.querySelectorAll('.sb-link')).find(b => b.innerText.includes('Foros'));
            if(foroBtn) {
                document.querySelectorAll('.sb-link').forEach(btn => btn.classList.remove('act'));
                foroBtn.classList.add('act');
            }
        }

        // Lógica Formularios y Modales
        function openModalCalificar(id, alumno, tarea, notaActual, observacionActual) {
            document.getElementById('modal-cal-id').value = id;
            document.getElementById('modal-cal-alumno').innerText = alumno;
            document.getElementById('modal-cal-tarea').innerText = tarea;
            document.getElementById('modal-cal-nota').value = notaActual !== null ? notaActual : '';
            document.getElementById('modal-cal-obs').value = observacionActual || '';
            actualizarEstadoCalificar();
            openModal('modal-calificar');
        }

        // Nota mínima de 15/20 para aprobar — muestra el estado en vivo
        // mientras el profesor escribe, antes incluso de guardar.
        function actualizarEstadoCalificar() {
            const input = document.getElementById('modal-cal-nota');
            const lbl = document.getElementById('modal-cal-estado');
            const v = parseFloat(input.value);
            if (isNaN(v)) { lbl.textContent = ''; return; }
            if (v >= 15) { lbl.textContent = '✓ Aprobado'; lbl.style.color = 'var(--lime2)'; }
            else { lbl.textContent = '✗ Reprobado'; lbl.style.color = 'var(--red)'; }
        }
        function rellenarObsCalificar(texto) {
            document.getElementById('modal-cal-obs').value = texto;
        }

        function submitCalificacion(e) {
            e.preventDefault();
            const form = e.target; const btn = form.querySelector('button[type="submit"]');
            btn.disabled = true; btn.innerHTML = 'Guardando...';

            const fdCal = new FormData(form);
            const _csrfCal = document.querySelector('meta[name="csrf-token"]');
            fdCal.append('csrf_token', _csrfCal ? _csrfCal.content : '');
            fetch('calificar_entrega.php', { method: 'POST', body: fdCal })
            .then(res => res.json()).then(data => {
                if(data.ok) {
                    closeModal('modal-calificar');
                    Ibbs.success('Calificación guardada.');
                    actualizarTarjetaEntrega(data.data);
                } else { Ibbs.error(data.msg); }
            }).catch(err => { Ibbs.error("Error de servidor."); })
            .finally(() => { btn.disabled = false; btn.innerHTML = 'Guardar'; });
        }

        // Actualiza la tarjeta de la entrega en el momento, sin recargar
        // la página — antes un location.reload() mandaba al docente de
        // vuelta al Inicio, perdiendo la pestaña "Cargar Notas" en la que
        // estaba trabajando.
        function actualizarTarjetaEntrega(d) {
            const card = document.getElementById('entrega-card-' + d.entrega_id);
            if (!card) return;
            const eraPendiente = card.dataset.estado === 'pendiente';
            card.dataset.estado = 'calificada';
            card.dataset.nota = d.nota;
            card.dataset.aprobada = d.aprobado ? '1' : '0';
            card.dataset.obs = d.observacion || '';
            card.style.borderLeft = '4px solid ' + (d.aprobado ? 'var(--lime2)' : 'var(--red)');
            card.style.opacity = '0.9';

            const badge = card.querySelector('.entrega-badge-estado');
            badge.className = 'badge entrega-badge-estado ' + (d.aprobado ? 'b-presente' : 'b-ausente');
            badge.innerHTML = `<i class="fas ${d.aprobado ? 'fa-check' : 'fa-xmark'}"></i> Nota: ${d.nota} / 20 · ${d.aprobado ? 'Aprobado' : 'Reprobado'}`;

            const obsWrap = card.querySelector('.entrega-obs-wrap');
            const obsText = card.querySelector('.entrega-obs-text');
            if (d.observacion) {
                obsText.textContent = d.observacion;
                obsWrap.style.display = '';
                obsWrap.querySelector('.entrega-obs-box').style.background = d.aprobado ? 'rgba(46,204,16,.08)' : 'rgba(217,119,6,.08)';
            } else {
                obsWrap.style.display = 'none';
            }

            const btn = card.querySelector('.entrega-btn-calificar');
            btn.classList.remove('btn-primary'); btn.classList.add('btn-secondary');
            btn.innerHTML = '<i class="fas fa-star"></i> Modificar Calificación';
            btn.onclick = function () {
                openModalCalificar(d.entrega_id, card.dataset.alumno, card.dataset.tarea, d.nota, d.observacion || '');
            };

            if (eraPendiente) {
                const counter = document.getElementById('pendientesCountBadge');
                if (counter) counter.textContent = Math.max(0, (parseInt(counter.textContent, 10) || 0) - 1);
            }

            // Si está viendo la pestaña "Pendientes", esta tarjeta ya no
            // pertenece ahí — se re-aplica el filtro activo para que
            // desaparezca sin tener que recargar nada.
            const tabActivo = document.querySelector('#view-entregas .tab-btn.active');
            if (tabActivo) {
                const tipo = tabActivo.textContent.toLowerCase().includes('pendientes') ? 'pendientes'
                           : tabActivo.textContent.toLowerCase().includes('calificadas') ? 'calificadas' : 'todas';
                filtrarEntregas(tipo, tabActivo);
            }
        }

        // La corrección siempre tiene que quedar después de la entrega —
        // si no, no habría margen para que el profesor revise nada.
        function validarFechasNuevaTarea() {
            const ent = document.getElementById('nt-fecha-entrega').value;
            const cor = document.getElementById('nt-fecha-correccion').value;
            const err = document.getElementById('nt-fechas-error');
            const invalido = ent && cor && new Date(cor) <= new Date(ent);
            err.style.display = invalido ? 'block' : 'none';
            return !invalido;
        }

        function submitNuevaTarea(e) {
            e.preventDefault();
            if (!validarFechasNuevaTarea()) { Ibbs.warn('Revisá las fechas límite antes de publicar.'); return; }
            const form = e.target; const btn = form.querySelector('button[type="submit"]');
            btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Subiendo...';

            const fdTarea = new FormData(form);
            const _csrfTarea = document.querySelector('meta[name="csrf-token"]');
            fdTarea.append('csrf_token', _csrfTarea ? _csrfTarea.content : '');
            fetch('crear_tarea.php', { method: 'POST', body: fdTarea })
            .then(res => res.json()).then(async data => {
                if(data.ok) {
                    closeModal('modal-nueva-tarea');
                    form.reset();
                    await Ibbs.success('Actividad/Tarea publicada con éxito.');
                    location.reload();
                } else {
                    Ibbs.error(data.msg);
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-upload"></i> Publicar Actividad';
                }
            }).catch(err => {
                Ibbs.error("Error crítico al subir.");
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-upload"></i> Publicar Actividad';
            });
        }

        <?php if(in_array($_SESSION['rol'], ['superadmin', 'admin'])): ?>
        function openModalAsignarMateria(docenteId = '') {
            document.getElementById('form-asignar-materia').reset();
            if(docenteId) document.getElementById('asignar_docente_id').value = docenteId;
            openModal('modal-asignar-materia');
        }

        function submitAsignarMateria(e) {
            e.preventDefault(); 
            const form = e.target; const btn = form.querySelector('button[type="submit"]');
            btn.disabled = true; btn.innerHTML = '...';

            const fdAsig = new FormData(form);
            const _csrfAsig = document.querySelector('meta[name="csrf-token"]');
            fdAsig.append('csrf_token', _csrfAsig ? _csrfAsig.content : '');
            fetch('asignar_materia.php', { method: 'POST', body: fdAsig })
            .then(res => res.json()).then(async data => {
                if(data.ok) { closeModal('modal-asignar-materia'); await Ibbs.success("Materia vinculada con éxito."); location.reload(); }
                else { Ibbs.error(data.msg); btn.disabled = false; btn.innerHTML = 'Vincular Materia'; }
            }).catch(err => { Ibbs.error("Error de conexión."); btn.disabled = false; });
        }
        <?php endif; ?>

        let materiaActivaChatId = <?= !empty($materias) ? $materias[0]['id'] : 0 ?>;
        let ultimoIdMensaje = 0;
        let chatInterval = null;
        const MI_USUARIO_ID = <?= (int)$user_id ?>;
        function hChat(s) { const d = document.createElement('div'); d.textContent = String(s ?? ''); return d.innerHTML; }

        function prepararRespuesta(nombreUsuario, idMensaje) {
            document.getElementById('chat-reply-to-id').value = idMensaje;
            document.getElementById('reply-to-name').innerText = nombreUsuario;
            document.getElementById('reply-indicator').style.display = 'flex';
            document.getElementById('chat-input-text').focus();
        }

        function cancelarRespuesta() {
            document.getElementById('chat-reply-to-id').value = '';
            document.getElementById('reply-indicator').style.display = 'none';
        }

        function selectChatSubject(materiaId, nombreMateria, element) {
            materiaActivaChatId = materiaId;
            document.getElementById('chat-title').innerText = nombreMateria;
            document.getElementById('chat-materia-id').value = materiaId;
            cancelarRespuesta();

            document.querySelectorAll('.chat-item').forEach(el => el.classList.remove('active-chat'));
            if(element) element.classList.add('active-chat');

            document.getElementById('chat-messages-container').innerHTML = `<div style="display:flex;justify-content:center;margin:3rem 0;"><span class="spin" style="color:var(--ink);"></span></div>`;
            ultimoIdMensaje = 0;
            cargarMensajesForo();
        }

        function roleBadgeDocente(rol) {
            if (rol === 'profesor') return '<i class="fas fa-chalkboard-teacher" style="margin-left:4px;" title="Profesor"></i>';
            if (rol === 'admin' || rol === 'superadmin') return '<i class="fas fa-user-shield" style="margin-left:4px;" title="Administrador"></i>';
            return '';
        }

        function cargarMensajesForo() {
            if (materiaActivaChatId === 0) return;
            fetch(`api/foro.php?action=get_mensajes&materia_id=${materiaActivaChatId}`)
            .then(res => res.json())
            .then(mensajes => {
                if (mensajes && mensajes.error) { console.error("Error del servidor:", mensajes.error); return; }
                const chatBox = document.getElementById('chat-messages-container');
                if (!mensajes || mensajes.length === 0) {
                    chatBox.innerHTML = `<div style="display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; color: var(--muted); gap: .5rem;"><i class="far fa-comments" style="font-size: 2.5rem;"></i><p style="font-size: .8rem; font-weight: 700; text-transform: uppercase;">No hay mensajes aún.</p></div>`;
                    ultimoIdMensaje = 0;
                    return;
                }

                chatBox.innerHTML = '';
                mensajes.forEach(msg => {
                    let isMe = msg.usuario_id === MI_USUARIO_ID;
                    let replyHtml = '';
                    if (msg.respuesta_a_nombre) {
                        replyHtml = `<div style="font-size:.7rem; background:rgba(0,0,0,.1); padding:4px 8px; border-radius:4px; margin-bottom:6px; border-left:2px solid currentColor;"><i class="fas fa-reply"></i> a ${hChat(msg.respuesta_a_nombre)}</div>`;
                    }
                    const hora = new Date(msg.fecha).toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'});
                    const delBtn = msg.puede_borrar
                        ? `<button type="button" onclick="borrarMensajeForo(${msg.id})" style="background:none;border:none;color:var(--red);font-weight:700;cursor:pointer;text-decoration:underline;margin-left:.6rem;">Borrar</button>`
                        : '';

                    let html = '';
                    if (isMe) {
                        html = `
                        <div style="display: flex; flex-direction: column; align-items: flex-end; width: 100%;">
                            <span style="font-size: .65rem; color: var(--muted); margin-bottom: .2rem; font-weight: 700;">Tú</span>
                            <div class="msg-bubble msg-mine">
                                ${replyHtml}
                                <p>${hChat(msg.mensaje)}</p>
                                <div style="display: flex; justify-content: flex-end; align-items: center; gap: .4rem; margin-top: .4rem; font-size: .65rem; opacity: .7;">
                                    <span>${hora}</span> <i class="fas fa-check-double text-ibbs-lime"></i>${delBtn}
                                </div>
                            </div>
                        </div>`;
                    } else {
                        let isDocente = (msg.rol === 'superadmin' || msg.rol === 'profesor' || msg.rol === 'admin');
                        let nameColor = isDocente ? 'color: var(--ink);' : 'color: var(--muted);';
                        html = `
                        <div style="display: flex; flex-direction: column; align-items: flex-start; width: 100%;">
                            <span style="font-size: .65rem; ${nameColor} margin-bottom: .2rem; font-weight: 700;">${hChat(msg.usuario_nombre)} ${roleBadgeDocente(msg.rol)}</span>
                            <div class="msg-bubble msg-other">
                                ${replyHtml}
                                <p>${hChat(msg.mensaje)}</p>
                                <div style="display: flex; justify-content: space-between; align-items: center; gap: 1rem; margin-top: .4rem; font-size: .65rem;">
                                    <span style="color: var(--muted);">${hora}</span>
                                    <span>
                                        <button type="button" onclick="prepararRespuesta('${hChat(msg.usuario_nombre)}', ${msg.id})" style="background: none; border: none; color: var(--ink); font-weight: 700; cursor: pointer; text-decoration: underline;">Responder</button>${delBtn}
                                    </span>
                                </div>
                            </div>
                        </div>`;
                    }
                    chatBox.innerHTML += html;
                    ultimoIdMensaje = Math.max(ultimoIdMensaje, msg.id);
                });
                chatBox.scrollTop = chatBox.scrollHeight;
            }).catch(err => console.error(err));
        }

        async function borrarMensajeForo(id) {
            const rr = await Ibbs.confirm({title:'¿Borrar este mensaje?', text:'Esta acción no se puede deshacer.', confirm:'Sí, borrar', danger:true});
            if (!rr.isConfirmed) return;
            try {
                const _csrfMeta = document.querySelector('meta[name="csrf-token"]');
                const r = await fetch(`api/foro.php?action=delete_mensaje&materia_id=${materiaActivaChatId}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id, csrf_token: _csrfMeta ? _csrfMeta.content : '' })
                });
                const result = await r.json();
                if (result.success) cargarMensajesForo();
                else Ibbs.error(result.error || 'No se pudo borrar el mensaje.');
            } catch (e) { console.error(e); }
        }

        function sendChat(e) {
            e.preventDefault();
            const input = document.getElementById('chat-input-text');
            const mensaje = input.value.trim();
            if (mensaje === '') return;
            const respuesta_a = document.getElementById('chat-reply-to-id').value;
            input.value = ''; cancelarRespuesta();
            const _csrfMeta = document.querySelector('meta[name="csrf-token"]');
            fetch(`api/foro.php?action=post_mensaje&materia_id=${materiaActivaChatId}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ mensaje, respuesta_a, csrf_token: _csrfMeta ? _csrfMeta.content : '' })
            })
            .then(res => res.json())
            .then(data => { if (data.success) cargarMensajesForo(); else Ibbs.error(data.error || 'No se pudo enviar el mensaje.'); })
            .catch(err => console.error(err));
        }

        if(materiaActivaChatId > 0) cargarMensajesForo();
        if(chatInterval) clearInterval(chatInterval);
        chatInterval = setInterval(() => {
            const viewChat = document.getElementById('view-chat');
            if(viewChat.classList.contains('active') && materiaActivaChatId > 0) cargarMensajesForo();
        }, 5000);

        // Con WebSocket (VPS configurado) el chat se refresca casi al
        // instante en vez de esperar hasta 5s — el setInterval de
        // arriba queda como red de seguridad si el WebSocket se cae.
        if (window.IbbsRT && window.IbbsRT.hasWs) {
            window.IbbsRT.on('foro_mensaje', (data) => {
                if (data && parseInt(data.materia_id) === parseInt(materiaActivaChatId)) cargarMensajesForo();
            });
        }

        /* ══ CHAT DEL STAFF (admin ↔ docentes, sin materia) ══ */
        let lastCountStaffChat = -1;

        function hStaffChat(s) { const d = document.createElement('div'); d.textContent = String(s ?? ''); return d.innerHTML; }

        function roleBadgeStaffChat(rol) {
            if (rol === 'profesor') return '<span style="font-size:.65rem;margin-left:.4rem;background:#dbeafe;color:#1d4ed8;padding:1px 6px;border-radius:10px;font-weight:700;">Profesor</span>';
            if (rol === 'admin' || rol === 'superadmin') return '<span style="font-size:.65rem;margin-left:.4rem;background:#fee2e2;color:#991b1b;padding:1px 6px;border-radius:10px;font-weight:700;">Admin</span>';
            return '';
        }

        async function loadChatStaffPortal() {
            try {
                const r = await fetch('api/chat_general.php?action=get_mensajes');
                const mensajes = await r.json();
                if (mensajes.error) { console.error('Error del servidor:', mensajes.error); return; }
                if (mensajes.length === lastCountStaffChat) return;
                lastCountStaffChat = mensajes.length;
                renderChatStaffPortal(mensajes);
            } catch (e) { console.error('Error cargando chat del staff', e); }
        }

        function renderChatStaffPortal(mensajes) {
            const chatBox = document.getElementById('staffchat-box');
            chatBox.innerHTML = '';
            if (!mensajes.length) {
                chatBox.innerHTML = '<div style="text-align:center;padding:2rem;color:var(--muted);font-size:.9rem;">Sin mensajes todavía. Escribí el primero para el staff.</div>';
                return;
            }
            const hilos = {};
            mensajes.forEach(m => { if (m.respuesta_a === null) hilos[m.id] = { ...m, respuestas: [] }; });
            mensajes.forEach(m => { if (m.respuesta_a !== null && hilos[m.respuesta_a]) hilos[m.respuesta_a].respuestas.push(m); });
            for (const id in hilos) {
                const thread = hilos[id];
                chatBox.insertAdjacentHTML('beforeend', crearMensajeStaffChatHTML(thread, false));
                if (thread.respuestas.length > 0) {
                    const cont = document.createElement('div');
                    cont.style.cssText = "margin-left:2.5rem;margin-top:.5rem;padding-left:1rem;border-left:2px solid var(--border);display:flex;flex-direction:column;gap:.5rem;";
                    thread.respuestas.forEach(r => cont.insertAdjacentHTML('beforeend', crearMensajeStaffChatHTML(r, true)));
                    chatBox.appendChild(cont);
                }
            }
            chatBox.scrollTop = chatBox.scrollHeight;
        }

        function crearMensajeStaffChatHTML(msg, isReply) {
            const isMe = msg.usuario_id === <?= (int)$user_id ?>;
            const bg = isMe ? 'background:var(--bubble-mine-bg);border:1px solid var(--bubble-mine-border);' : 'background:var(--paper);border:1px solid var(--border);';
            const dateStr = new Date(msg.fecha).toLocaleString([], {month:'short', day:'numeric', hour:'2-digit', minute:'2-digit'});
            const replyBtn = !isReply ? `<button type="button" onclick="setReplyStaffChat(${msg.id}, '${hStaffChat(msg.usuario_nombre)}')" style="background:none;border:none;color:var(--ink);cursor:pointer;font-size:.8rem;margin-top:.4rem;padding:0;text-decoration:underline;">Responder</button>` : '';
            const delBtn = msg.puede_borrar ? `<button type="button" onclick="borrarMensajeStaffChat(${msg.id})" style="background:none;border:none;color:#dc2626;cursor:pointer;font-size:.8rem;margin-top:.4rem;margin-left:.8rem;padding:0;text-decoration:underline;">Borrar</button>` : '';
            return `
                <div style="padding:.8rem 1rem;border-radius:8px;${bg}">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.3rem;">
                        <strong style="font-size:.9rem;color:var(--ink);">${hStaffChat(msg.usuario_nombre)} ${roleBadgeStaffChat(msg.rol)}</strong>
                        <span style="font-size:.75rem;color:var(--muted);">${dateStr}</span>
                    </div>
                    <p style="margin:0;font-size:.9rem;color:var(--ink);white-space:pre-wrap;line-height:1.4;">${hStaffChat(msg.mensaje)}</p>
                    ${replyBtn}${delBtn}
                </div>`;
        }

        window.setReplyStaffChat = function(id, nombre) {
            document.getElementById('staffchat-respuesta-a').value = id;
            document.getElementById('staffchat-reply-name').textContent = nombre;
            document.getElementById('staffchat-reply-indicator').style.display = 'flex';
            document.getElementById('staffchat-input').focus();
        };
        window.cancelReplyStaffChat = function() {
            document.getElementById('staffchat-respuesta-a').value = '';
            document.getElementById('staffchat-reply-indicator').style.display = 'none';
        };

        async function borrarMensajeStaffChat(id) {
            const rr = await Ibbs.confirm({title:'¿Borrar este mensaje?', text:'Esta acción no se puede deshacer.', confirm:'Sí, borrar', danger:true});
            if (!rr.isConfirmed) return;
            try {
                const _csrf = document.querySelector('meta[name="csrf-token"]');
                const r = await fetch('api/chat_general.php?action=delete_mensaje', {
                    method: 'POST', headers: {'Content-Type':'application/json'},
                    body: JSON.stringify({ id, csrf_token: _csrf ? _csrf.content : '' })
                });
                const result = await r.json();
                if (result.success) { lastCountStaffChat = -1; loadChatStaffPortal(); }
                else Ibbs.error(result.error || 'No se pudo borrar el mensaje.');
            } catch (e) { console.error(e); }
        }

        document.getElementById('staffchat-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const input = document.getElementById('staffchat-input');
            const mensaje = input.value.trim();
            const respuesta_a = document.getElementById('staffchat-respuesta-a').value;
            if (!mensaje) return;
            input.value = '';
            cancelReplyStaffChat();
            try {
                const _csrf = document.querySelector('meta[name="csrf-token"]');
                const r = await fetch('api/chat_general.php?action=post_mensaje', {
                    method: 'POST', headers: {'Content-Type':'application/json'},
                    body: JSON.stringify({ mensaje, respuesta_a, csrf_token: _csrf ? _csrf.content : '' })
                });
                const result = await r.json();
                if (result.success) { lastCountStaffChat = -1; loadChatStaffPortal(); }
                else Ibbs.error(result.error || 'No se pudo enviar el mensaje.');
            } catch (e) { console.error(e); }
        });

        loadChatStaffPortal();
        setInterval(() => {
            const v = document.getElementById('view-chat-staff');
            if (v && v.classList.contains('active')) loadChatStaffPortal();
        }, 5000);
        if (window.IbbsRT && window.IbbsRT.hasWs) {
            window.IbbsRT.on('chat_staff_mensaje', () => { lastCountStaffChat = -1; loadChatStaffPortal(); });
        }

        // ── BIBLIOTECA (vista embebida — antes abría modulo_biblioteca.php
        // en otra pestaña con el tema de administración, perdiendo el
        // estilo propio del portal del docente) ──────────────────────
        function hLibD(s) { const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML; }

        async function cargarMisLibrosDocente() {
            const grid = document.getElementById('libDocenteGrid');
            const empty = document.getElementById('libDocenteEmpty');
            grid.innerHTML = '<div style="padding:2rem;color:var(--muted);"><span class="spin"></span></div>';
            empty.style.display = 'none';
            let d;
            try {
                const fd = new FormData(); fd.append('action', 'libro_list');
                const m = document.querySelector('meta[name="csrf-token"]'); fd.append('csrf_token', m ? m.content : '');
                const r = await fetch('api/biblioteca.php', {method:'POST', body:fd});
                d = await r.json();
            } catch (e) { grid.innerHTML = '<div style="padding:1rem;color:var(--red);">Error de conexión.</div>'; return; }
            if (!d?.ok) { grid.innerHTML = `<div style="padding:1rem;color:var(--red);">${hLibD(d?.msg||'Error al cargar.')}</div>`; return; }
            if (!d.data.length) { grid.innerHTML = ''; empty.style.display = 'block'; return; }
            grid.innerHTML = d.data.map(r => `
                <div class="card" style="display:flex;flex-direction:column;">
                    <div style="height:100px;background:var(--ink);display:flex;align-items:center;justify-content:center;overflow:hidden;">
                        ${r.portada ? `<img src="${r.portada}" style="width:100%;height:100%;object-fit:cover;">` : `<i class="fas fa-book" style="font-size:2.2rem;color:rgba(255,255,255,.2);"></i>`}
                    </div>
                    <div class="card-body" style="flex:1;display:flex;flex-direction:column;">
                        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:.4rem;">
                            <h3 style="font-weight:700;color:var(--ink);font-size:1rem;line-height:1.2;">${hLibD(r.titulo)}</h3>
                            <span class="badge ${r.activo=='1'?'b-activo':'b-inactivo'}">${r.activo=='1'?'Visible':'Oculto'}</span>
                        </div>
                        <p style="font-size:.8rem;color:var(--muted);margin-bottom:.3rem;">${hLibD(r.autor||'—')}</p>
                        <p style="font-size:.85rem;font-weight:700;color:var(--ink);margin-bottom:1rem;">${parseFloat(r.precio)>0?'$'+parseFloat(r.precio).toFixed(2):'Gratis'}</p>
                        <div style="margin-top:auto;display:flex;gap:.4rem;flex-wrap:wrap;">
                            <button onclick="window.open('api/biblioteca.php?action=descargar&id=${r.id}','_blank')" class="btn btn-secondary" style="flex:1;font-size:.76rem;">Vista previa</button>
                            <button onclick="editarLibroDocente(${r.id})" class="btn btn-secondary" style="flex:1;font-size:.76rem;">Editar</button>
                        </div>
                        <div style="margin-top:.4rem;display:flex;gap:.4rem;">
                            <button onclick="toggleLibroDocente(${r.id})" class="btn btn-secondary" style="flex:1;font-size:.76rem;">${r.activo=='1'?'Ocultar':'Mostrar'}</button>
                            <button onclick="eliminarLibroDocente(${r.id},'${hLibD(r.titulo).replace(/'/g,"\\'")}')" class="btn" style="flex:1;font-size:.76rem;background:#fee2e2;color:var(--red);">Eliminar</button>
                        </div>
                    </div>
                </div>`).join('');
        }

        function abrirNuevoLibroDocente() {
            document.getElementById('form-libro-docente').reset();
            document.getElementById('libD-id').value = '';
            document.getElementById('libDocenteModalTitulo').textContent = 'Nuevo Libro';
            document.getElementById('libD-archivoReq').textContent = '*';
            openModal('modal-libro-docente');
        }
        async function editarLibroDocente(id) {
            const fd = new FormData(); fd.append('action', 'libro_get'); fd.append('id', id);
            const m = document.querySelector('meta[name="csrf-token"]'); fd.append('csrf_token', m ? m.content : '');
            const r = await fetch('api/biblioteca.php', {method:'POST', body:fd});
            const d = await r.json();
            if (!d.ok) { Ibbs.error(d.msg || 'No se pudo cargar el libro.'); return; }
            const lib = d.data;
            document.getElementById('libD-id').value = lib.id;
            document.getElementById('libD-titulo').value = lib.titulo;
            document.getElementById('libD-autor').value = lib.autor || '';
            document.getElementById('libD-categoria').value = lib.categoria || '';
            document.getElementById('libD-descripcion').value = lib.descripcion || '';
            document.getElementById('libD-precio').value = lib.precio;
            document.getElementById('libD-archivoReq').textContent = '(ya cargado — subí uno nuevo solo si querés reemplazarlo)';
            document.getElementById('libDocenteModalTitulo').textContent = 'Editar Libro';
            openModal('modal-libro-docente');
        }
        async function guardarLibroDocente(e) {
            e.preventDefault();
            const id = document.getElementById('libD-id').value;
            const archivo = document.getElementById('libD-archivo').files[0];
            if (!id && !archivo) { Ibbs.warn('Subí el archivo del libro (PDF o EPUB).'); return; }
            const fd = new FormData();
            fd.append('action', id ? 'libro_update' : 'libro_create');
            if (id) fd.append('id', id);
            fd.append('titulo', document.getElementById('libD-titulo').value);
            fd.append('autor', document.getElementById('libD-autor').value);
            fd.append('categoria', document.getElementById('libD-categoria').value);
            fd.append('descripcion', document.getElementById('libD-descripcion').value);
            fd.append('precio', document.getElementById('libD-precio').value);
            const portada = document.getElementById('libD-portada').files[0];
            if (portada) fd.append('portada', portada);
            if (archivo) fd.append('archivo', archivo);
            const m = document.querySelector('meta[name="csrf-token"]'); fd.append('csrf_token', m ? m.content : '');
            const btn = document.getElementById('btnGuardarLibroDocente');
            btn.disabled = true; btn.textContent = 'Guardando…';
            try {
                const r = await fetch('api/biblioteca.php', {method:'POST', body:fd});
                const d = await r.json();
                if (d.ok) { Ibbs.success(d.msg); closeModal('modal-libro-docente'); cargarMisLibrosDocente(); }
                else Ibbs.error(d.msg || 'Error');
            } catch (err) { Ibbs.error('Error de conexión.'); }
            btn.disabled = false; btn.textContent = 'Publicar';
        }
        async function toggleLibroDocente(id) {
            const fd = new FormData(); fd.append('action', 'libro_toggle'); fd.append('id', id);
            const m = document.querySelector('meta[name="csrf-token"]'); fd.append('csrf_token', m ? m.content : '');
            const r = await fetch('api/biblioteca.php', {method:'POST', body:fd});
            const d = await r.json();
            if (d.ok) { Ibbs.success(d.msg); cargarMisLibrosDocente(); } else Ibbs.error(d.msg || 'Error');
        }
        async function eliminarLibroDocente(id, titulo) {
            const rr = await Ibbs.confirm({title:'¿Eliminar libro?', text:`Se eliminará "<b>${titulo}</b>" y su archivo. Esta acción es irreversible.`, confirm:'Sí, eliminar', danger:true});
            if (!rr.isConfirmed) return;
            const fd = new FormData(); fd.append('action', 'libro_delete'); fd.append('id', id);
            const m = document.querySelector('meta[name="csrf-token"]'); fd.append('csrf_token', m ? m.content : '');
            const r = await fetch('api/biblioteca.php', {method:'POST', body:fd});
            const d = await r.json();
            if (d.ok) { Ibbs.success(d.msg); cargarMisLibrosDocente(); } else Ibbs.error(d.msg || 'Error');
        }

        // ── ASISTENCIA POR FOTO (OCR) — mismo motor que
        // modulo_asistencias.php, portado acá para que el docente lo
        // tenga dentro de su propio portal (api/asistencia_ocr.php ya
        // soporta el rol profesor, scoped a sus propias materias) ──
        const EST = ['presente','ausente','tardanza','justificado'];
        const EST_SHORT = {presente:'Pres',ausente:'Aus',tardanza:'Tard',justificado:'Just'};
        const EST_MK    = {presente:'mk-p',ausente:'mk-a',tardanza:'mk-t',justificado:'mk-j'};

        function avatarHtml(a) {
            const ini = ((a.nombre||' ')[0]).toUpperCase();
            if (a.foto) return `<div class="pl-ava"><img src="${a.foto}" alt="${ini}" onerror="this.parentElement.innerHTML='${ini}'"></div>`;
            const colors = ['#1a4d2e','#1e5c36','#256035','#2d7a45','#166534'];
            const ci = (a.cedula||ini).charCodeAt(0) % colors.length;
            return `<div class="pl-ava" style="background:${colors[ci]};">${ini}</div>`;
        }

        function ocrCambiarMateria() {
            document.getElementById('ocrReview').style.display = 'none';
            document.getElementById('btnOcrProcesar').disabled = !(document.getElementById('ocrMateria').value && _ocrFotoFinal);
        }

        let _ocrRoster = [];
        let _ocrTextoCrudo = '';
        let _tesseractCargado = false;
        let _ocrFotoFinal = null;

        function ocrRedimensionarImagen(file, maxDim=1800, calidad=0.85) {
            return new Promise((resolve) => {
                const img = new Image();
                const url = URL.createObjectURL(file);
                img.onload = () => {
                    let {width, height} = img;
                    if (width > maxDim || height > maxDim) {
                        const ratio = Math.min(maxDim/width, maxDim/height);
                        width = Math.round(width*ratio); height = Math.round(height*ratio);
                    }
                    const canvas = document.createElement('canvas');
                    canvas.width = width; canvas.height = height;
                    canvas.getContext('2d').drawImage(img, 0, 0, width, height);
                    canvas.toBlob(blob => {
                        URL.revokeObjectURL(url);
                        resolve(blob ? new File([blob], 'hoja.jpg', {type:'image/jpeg'}) : file);
                    }, 'image/jpeg', calidad);
                };
                img.onerror = () => { URL.revokeObjectURL(url); resolve(file); };
                img.src = url;
            });
        }

        async function ocrPreviewFoto() {
            const input = document.getElementById('ocrFoto');
            const wrap = document.getElementById('ocrFotoPreviewWrap');
            document.getElementById('ocrReview').style.display = 'none';
            if (!input.files.length) { wrap.style.display = 'none'; _ocrFotoFinal = null; document.getElementById('btnOcrProcesar').disabled = true; return; }
            document.getElementById('btnOcrProcesar').disabled = true;
            _ocrFotoFinal = await ocrRedimensionarImagen(input.files[0]);
            document.getElementById('ocrFotoPreview').src = URL.createObjectURL(_ocrFotoFinal);
            wrap.style.display = 'block';
            document.getElementById('btnOcrProcesar').disabled = !document.getElementById('ocrMateria').value;
        }

        async function _ocrCargarTesseract() {
            if (_tesseractCargado) return;
            await new Promise((resolve, reject) => {
                const s = document.createElement('script');
                s.src = 'https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js';
                s.onload = resolve; s.onerror = () => reject(new Error('No se pudo cargar el lector de imágenes (revisá tu conexión a internet).'));
                document.head.appendChild(s);
            });
            _tesseractCargado = true;
        }

        function ocrNormalizar(s) {
            return (s||'').toString().toLowerCase()
                .normalize('NFD').replace(/[̀-ͯ]/g,'')
                .replace(/[^a-z0-9\s]/g,' ')
                .replace(/\s+/g,' ').trim();
        }
        function ocrLevenshtein(a,b){
            const m=a.length, n=b.length;
            if(!m) return n; if(!n) return m;
            const dp=[]; for(let i=0;i<=m;i++) dp.push([i,...new Array(n).fill(0)]);
            for(let j=0;j<=n;j++) dp[0][j]=j;
            for(let i=1;i<=m;i++) for(let j=1;j<=n;j++)
                dp[i][j] = a[i-1]===b[j-1] ? dp[i-1][j-1] : 1+Math.min(dp[i-1][j-1],dp[i-1][j],dp[i][j-1]);
            return dp[m][n];
        }
        function ocrAlumnoDetectado(alumno, textoNorm, digitosTexto) {
            const cedula = (alumno.cedula||'').replace(/\D/g,'');
            if (cedula && cedula.length>=6 && digitosTexto.includes(cedula)) return true;
            const tokens = textoNorm.split(' ').filter(t=>t.length>=3);
            const revisar = (palabra) => {
                if (!palabra || palabra.length<3) return false;
                const tolerancia = palabra.length>=7 ? 2 : (palabra.length>=4 ? 1 : 0);
                return tokens.some(t => t===palabra || ocrLevenshtein(t,palabra)<=tolerancia);
            };
            const apellidoOk = ocrNormalizar(alumno.apellido).split(' ').some(revisar);
            const nombreOk   = ocrNormalizar(alumno.nombre).split(' ').some(revisar);
            return apellidoOk || nombreOk;
        }

        async function ocrProcesarFoto() {
            const mid = document.getElementById('ocrMateria').value;
            const file = _ocrFotoFinal;
            if (!mid) { Ibbs.warn('Elegí una materia primero.'); return; }
            if (!file) { Ibbs.warn('Adjuntá la foto de la hoja.'); return; }

            const btn = document.getElementById('btnOcrProcesar');
            const progWrap = document.getElementById('ocrProgressWrap');
            const progBar  = document.getElementById('ocrProgressBar');
            const progLbl  = document.getElementById('ocrProgressLbl');
            btn.disabled = true; progWrap.style.display = 'block'; progBar.style.width = '0%';
            progLbl.textContent = 'Cargando el lector de imágenes…';

            try {
                await _ocrCargarTesseract();

                const fdRoster = new FormData();
                fdRoster.append('action', 'materia_get'); fdRoster.append('id', mid);
                const mRoster = document.querySelector('meta[name="csrf-token"]'); fdRoster.append('csrf_token', mRoster ? mRoster.content : '');
                const rRoster = await fetch('api/ajax.php', {method:'POST', body:fdRoster});
                const md = await rRoster.json();
                _ocrRoster = md?.data?.alumnos || [];
                if (!_ocrRoster.length) { Ibbs.warn('Esta materia no tiene alumnos inscritos.'); btn.disabled=false; progWrap.style.display='none'; return; }

                progLbl.textContent = 'Leyendo la imagen (puede tardar unos segundos)…';
                const { data: { text } } = await Tesseract.recognize(file, 'spa', {
                    logger: m => {
                        if (m.status === 'recognizing text') {
                            const pct = Math.round((m.progress||0)*100);
                            progBar.style.width = pct+'%';
                            progLbl.textContent = `Leyendo la imagen… ${pct}%`;
                        }
                    }
                });
                _ocrTextoCrudo = text || '';

                const textoNorm = ocrNormalizar(_ocrTextoCrudo);
                const digitosTexto = (_ocrTextoCrudo.match(/\d+/g)||[]).join('');
                const modo = document.querySelector('input[name="ocrModo"]:checked').value;

                const filas = _ocrRoster.map(a => {
                    const detectado = ocrAlumnoDetectado(a, textoNorm, digitosTexto);
                    const estado = modo === 'asistieron' ? (detectado ? 'presente' : 'ausente') : (detectado ? 'ausente' : 'presente');
                    return { ...a, detectado, estado, obs: '' };
                });

                ocrRenderReview(filas);
                document.getElementById('ocrReview').style.display = 'block';
                document.getElementById('ocrReview').scrollIntoView({behavior:'smooth', block:'start'});
            } catch (e) {
                console.error(e);
                Ibbs.error(e.message || 'No se pudo leer la imagen. Probá con una foto más clara y bien iluminada.');
            }
            btn.disabled = false; progWrap.style.display = 'none';
        }

        let _ocrFilas = [];
        function ocrRenderReview(filas) {
            _ocrFilas = filas;
            const detectados = filas.filter(f=>f.detectado).length;
            document.getElementById('ocrReviewSummary').innerHTML =
                `${filas.length} alumnos inscritos · <strong>${detectados}</strong> encontrados en la foto · revisá cada fila antes de guardar`;
            const tb = document.getElementById('ocrBody');
            tb.innerHTML = filas.map((a, i) => `
                <tr class="pl-row pl-${a.estado}" id="ocrrow-${i}">
                    <td style="padding:.55rem .7rem;width:44px;">${avatarHtml(a)}</td>
                    <td style="padding:.55rem .8rem;">
                        <div style="font-weight:700;font-size:.85rem;">${a.apellido||''}, ${a.nombre||''}</div>
                        <div style="font-size:.72rem;color:var(--muted);">CI: ${a.cedula||'—'}</div>
                    </td>
                    <td style="text-align:center;padding:.55rem .5rem;">
                        <span class="${a.detectado?'ocr-detect-yes':'ocr-detect-no'}">${a.detectado?'✓ Sí':'— No'}</span>
                    </td>
                    <td style="text-align:center;padding:.55rem .5rem;">
                        <div style="display:flex;gap:.2rem;justify-content:center;">
                            ${EST.map(e=>`<button class="mark-btn ${a.estado===e?EST_MK[e]:''}" onclick="ocrSetEstado(${i},'${e}')">${EST_SHORT[e]}</button>`).join('')}
                        </div>
                    </td>
                    <td style="padding:.55rem .7rem;">
                        <input class="obs-input" value="${a.obs}" placeholder="Observación…" oninput="_ocrFilas[${i}].obs=this.value">
                    </td>
                </tr>`).join('');
        }
        function ocrSetEstado(i, estado) {
            _ocrFilas[i].estado = estado;
            const row = document.getElementById('ocrrow-'+i);
            row.className = `pl-row pl-${estado}`;
            row.querySelectorAll('.mark-btn').forEach((btn, j) => {
                const e = EST[j];
                btn.className = `mark-btn ${estado===e ? EST_MK[e] : ''}`;
            });
        }
        function ocrVerTexto() {
            Swal.fire({
                title: 'Texto detectado en la foto',
                html: `<pre style="text-align:left;white-space:pre-wrap;max-height:320px;overflow:auto;font-size:.78rem;background:#f5f0e8;padding:.8rem;border-radius:8px;">${(_ocrTextoCrudo||'(vacío)').replace(/[&<>]/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;'}[c]))}</pre>`,
                width: 560,
                confirmButtonText: 'Cerrar',
                background: '#f5f0e8', color: '#1a4d2e',
                customClass: { popup:'ibbs-swal', title:'ibbs-swal-title', confirmButton:'ibbs-swal-btn-ok' },
                buttonsStyling: false,
            });
        }

        async function ocrGuardar() {
            const mid = document.getElementById('ocrMateria').value;
            const fecha = document.getElementById('ocrFecha').value;
            const modo = document.querySelector('input[name="ocrModo"]:checked').value;
            const file = _ocrFotoFinal;
            if (!mid || !fecha || !file || !_ocrFilas.length) { Ibbs.warn('Procesá una foto primero.'); return; }

            const btn = document.getElementById('btnOcrGuardar');
            btn.disabled = true; btn.innerHTML = '<span class="spin"></span> Guardando…';
            try {
                const fd = new FormData();
                fd.append('action', 'hoja_guardar');
                fd.append('materia_id', mid);
                fd.append('fecha', fecha);
                fd.append('modo', modo);
                fd.append('foto', file);
                fd.append('texto_ocr', _ocrTextoCrudo);
                fd.append('registros', JSON.stringify(_ocrFilas.map(a => ({persona_id:a.id, estado:a.estado, observacion:a.obs||'', detectado:a.detectado}))));
                const m = document.querySelector('meta[name="csrf-token"]');
                fd.append('csrf_token', m ? m.content : '');
                const r = await fetch('api/asistencia_ocr.php', {method:'POST', body:fd});
                const d = await r.json();
                if (d.ok) {
                    await Ibbs.success(d.msg);
                    document.getElementById('ocrReview').style.display = 'none';
                    document.getElementById('ocrFoto').value = '';
                    document.getElementById('ocrFotoPreviewWrap').style.display = 'none';
                    _ocrFotoFinal = null;
                    document.getElementById('btnOcrProcesar').disabled = true;
                    ocrCargarHojas();
                } else {
                    Ibbs.error(d.msg || 'No se pudo guardar.');
                }
            } catch (e) { console.error(e); Ibbs.error('Error de conexión.'); }
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-save"></i> Guardar asistencia';
        }

        async function ocrCargarHojas() {
            const el = document.getElementById('ocrHojasList');
            const mid = document.getElementById('ocrMateria') ? (document.getElementById('ocrMateria').value || 0) : 0;
            const fd = new FormData(); fd.append('action', 'hoja_list'); fd.append('materia_id', mid);
            const m = document.querySelector('meta[name="csrf-token"]'); fd.append('csrf_token', m ? m.content : '');
            const r = await fetch('api/asistencia_ocr.php', {method:'POST', body:fd});
            const d = await r.json();
            if (!d.ok || !d.data.length) { el.innerHTML = '<div style="text-align:center;color:var(--muted);font-size:.82rem;padding:.5rem;">Todavía no se cargó ninguna hoja.</div>'; return; }
            el.innerHTML = d.data.map(h => `
                <div class="ocr-hoja-card">
                    <img class="ocr-hoja-thumb" src="${h.foto}" onclick="window.open('${h.foto}','_blank')">
                    <div style="flex:1;min-width:0;">
                        <div style="font-weight:700;font-size:.83rem;">${h.materia_nombre} · ${h.fecha}</div>
                        <div style="font-size:.74rem;color:var(--muted);">${h.total_detectados}/${h.total_alumnos} detectados · subida por ${h.cargado_por_nombre||'—'} · ${(h.creado_en||'').substring(0,16)}</div>
                    </div>
                </div>`).join('');
        }

        // ── PLAN DE NOTAS — planilla de la materia: cada alumno, cada
        // actividad, su nota, y el promedio ponderado por el % de cada
        // una (api/plan_notas.php, acción "docente"). El alumno ve su
        // propio Plan de Notas con la misma fórmula de promedio, pero
        // una sola fila (la suya) en vez de la planilla completa. ──────
        function hPN(s) { const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML; }

        async function cargarPlanNotas() {
            const mid = document.getElementById('pnMateria').value;
            const cont = document.getElementById('pnContenedor');
            if (!mid) { cont.innerHTML = 'Elegí una materia para ver su plan de notas.'; return; }
            cont.innerHTML = '<span class="spin"></span>';
            const fd = new FormData(); fd.append('accion', 'docente'); fd.append('materia_id', mid);
            const m = document.querySelector('meta[name="csrf-token"]'); fd.append('csrf_token', m ? m.content : '');
            let d;
            try {
                const r = await fetch('api/plan_notas.php', { method: 'POST', body: fd });
                d = await r.json();
            } catch (e) { cont.innerHTML = '<p style="color:var(--red);padding:1rem;">Error de conexión.</p>'; return; }
            if (!d.ok) { cont.innerHTML = `<p style="color:var(--red);padding:1rem;">${hPN(d.msg || 'Error al cargar.')}</p>`; return; }

            const { tareas, alumnos, notas, stats } = d.data;
            if (!tareas.length) { cont.innerHTML = '<p style="padding:1rem;color:var(--muted);">Esta materia todavía no tiene actividades creadas.</p>'; return; }

            const totalAlumnos = alumnos.length;
            // Resumen de actividades — se ve siempre, haya o no alumnos
            // inscritos todavía, y aunque nadie haya entregado nada: así
            // el profesor ve de una que la actividad que mandó existe y
            // cuánto le falta por completarse.
            let resumen = '<div style="padding:1rem 1.2rem;border-bottom:1px solid var(--border);">';
            resumen += '<div style="font-weight:700;font-size:.85rem;color:var(--ink);margin-bottom:.6rem;">Tus actividades en esta materia</div>';
            resumen += '<div style="display:flex;flex-wrap:wrap;gap:.6rem;">';
            tareas.forEach(t => {
                const st = stats[t.id] || { entregados: 0, calificados: 0 };
                const pct = t.porcentaje !== null ? `${parseFloat(t.porcentaje)}%` : 'sin % asignado';
                const completo = totalAlumnos > 0 && st.calificados >= totalAlumnos;
                resumen += `<div style="padding:.6rem .8rem;border:1px solid var(--border);border-radius:8px;background:${completo ? 'rgba(46,204,16,.06)' : 'var(--cream)'};min-width:200px;">
                    <div style="font-weight:700;font-size:.82rem;color:var(--ink);">${hPN(t.titulo)}</div>
                    <div style="font-size:.72rem;color:var(--muted);margin-top:.2rem;">${pct} · ${st.entregados}/${totalAlumnos || '?'} entregaron · ${st.calificados}/${totalAlumnos || '?'} calificadas</div>
                </div>`;
            });
            resumen += '</div></div>';

            if (!alumnos.length) { cont.innerHTML = resumen + '<p style="padding:1rem;color:var(--muted);">Esta materia todavía no tiene alumnos inscritos.</p>'; return; }

            const sumaPorcentajes = tareas.reduce((s, t) => s + (parseFloat(t.porcentaje) || 0), 0);

            let html = resumen + '<div class="tbl-wrap"><table><thead><tr><th style="text-align:left;">Alumno</th>';
            tareas.forEach(t => {
                const pct = t.porcentaje !== null ? `${parseFloat(t.porcentaje)}%` : 'sin %';
                html += `<th style="text-align:center;min-width:110px;" title="${hPN(t.titulo)}">${hPN(t.titulo.length > 16 ? t.titulo.substring(0,16)+'…' : t.titulo)}<br><span style="font-weight:400;color:var(--muted);font-size:.68rem;">${pct}</span></th>`;
            });
            html += '<th style="text-align:center;">Promedio ponderado</th></tr></thead><tbody>';

            alumnos.forEach(a => {
                html += `<tr><td style="text-align:left;font-weight:700;">${hPN(a.apellido)}, ${hPN(a.nombre)}</td>`;
                let sumaPonderada = 0, pesoEvaluado = 0;
                tareas.forEach(t => {
                    const reg = (notas[a.id] && notas[a.id][t.id]) || null;
                    const nota = reg ? parseFloat(reg.nota) : null;
                    if (nota === null || isNaN(nota)) {
                        html += '<td style="text-align:center;color:var(--muted);">—</td>';
                    } else {
                        const color = nota >= 15 ? 'var(--lime2)' : 'var(--red)';
                        html += `<td style="text-align:center;font-weight:700;color:${color};">${nota}</td>`;
                        const peso = parseFloat(t.porcentaje) || 0;
                        sumaPonderada += nota * peso;
                        pesoEvaluado += peso;
                    }
                });
                const promedio = pesoEvaluado > 0 ? (sumaPonderada / pesoEvaluado).toFixed(1) : null;
                if (promedio === null) {
                    html += '<td style="text-align:center;color:var(--muted);">Sin notas aún</td>';
                } else {
                    const color = promedio >= 15 ? 'var(--lime2)' : 'var(--red)';
                    html += `<td style="text-align:center;font-weight:700;color:${color};">${promedio} / 20</td>`;
                }
                html += '</tr>';
            });
            html += '</tbody></table></div>';

            if (sumaPorcentajes !== 100 && sumaPorcentajes !== 0) {
                html = `<div style="padding:.7rem 1.2rem;background:rgba(217,119,6,.08);color:var(--amber);font-size:.78rem;font-weight:600;border-bottom:1px solid var(--border);"><i class="fas fa-triangle-exclamation"></i> Los % de las actividades suman ${sumaPorcentajes}% (debería sumar 100%) — el promedio de arriba se calcula igual, mejor pero conviene revisar los %.</div>` + html;
            }
            cont.innerHTML = html;
        }

        // ── Tutorial guiado (manual intuitivo del docente) ─────────
        window.IBBS_TOUR_DOCENTE = {
            storageKey: 'ibbs_tour_docente_v2',
            steps: [
                { selector: '[data-tour="nav-dashboard"]', title: '¡Bienvenido/a a IBBS!', text: 'Este es tu Inicio: un vistazo rápido a tus materias, las entregas de tus alumnos pendientes de revisar y los avisos de la institución. Vamos a recorrer juntos todo lo que podés hacer — cerrá esta tarjeta con la "×" para seguir.' },
                { selector: '[data-tour="nav-materias"]', title: 'Mis Cursos', text: 'Mirá las materias que tenés asignadas este período y la lista de alumnos inscritos en cada una, con su horario.' },
                { selector: '[data-tour="nav-aula"]', title: 'Aula Virtual', text: 'Subí el material de cada materia (documentos, presentaciones, videos, clases grabadas o en vivo) para que tus alumnos lo vean cuando quieran.' },
                { selector: '[data-tour="nav-asistencia"]', title: 'Asistencia por Foto', text: 'Subí la foto de la hoja de asistencia en papel de tu clase — el sistema la lee y precarga la tabla de presentes/ausentes, vos la revisás y confirmás.' },
                { selector: '[data-tour="nav-entregas"]', title: 'Cargar Notas', text: 'Revisá las tareas que entregaron tus alumnos, dejales una observación si hace falta y cargá la calificación — la ven reflejada al instante en su portal.' },
                { selector: '[data-tour="nav-plan-notas"]', title: 'Plan de Notas', text: 'La planilla completa de una materia: cada alumno, cada actividad con su %, y el promedio ponderado. Tus alumnos ven su propio Plan de Notas con el mismo promedio, calculado solo con sus actividades.' },
                { selector: '[data-tour="nav-chat"]', title: 'Foros de Clase', text: 'Respondé las dudas de tus alumnos sobre cada materia y compartí avisos importantes del curso.' },
                { selector: '[data-tour="nav-chat-staff"]', title: 'Chat del Staff', text: 'Canal privado para coordinar con administración y otros docentes de la institución, sin depender de ninguna materia en particular.' },
                { selector: '[data-tour="nav-biblioteca"]', title: 'Biblioteca', text: 'Subí tus propios libros, gratuitos o de pago, con portada y descripción — aparecen automáticamente en el catálogo que ven tus alumnos.' },
                <?php if (in_array($_SESSION['rol'], ['superadmin', 'admin'])): ?>
                { selector: '[data-tour="nav-admin-docentes"]', title: 'Asignar Materias', text: 'Como administrador, desde acá asignás qué materias imparte cada docente de la institución.' },
                <?php endif; ?>
                { selector: '#themeToggle', title: 'Modo claro / oscuro', text: 'Si preferís una vista con fondo oscuro (útil de noche o para cuidar la vista), tocá este interruptor. Tu elección se guarda y la vas a ver así la próxima vez que entres.' },
                { selector: '[data-tour="nav-perfil"]', title: 'Mi Perfil', text: 'Actualizá tu foto, tus datos personales y tu contraseña. Al escribir una contraseña vas a ver un ícono de ojo al lado del campo — tocalo si querés revisar lo que escribiste.' },
                { selector: '[data-tour="nav-replay"]', title: '¿Necesitás repasar esto?', text: 'Cuando quieras volver a ver este recorrido completo, tocá acá. ¡Éxitos con tus cursos!' }
            ]
        };
        document.addEventListener('DOMContentLoaded', function () {
            IbbsTour.start(Object.assign({ auto: true }, window.IBBS_TOUR_DOCENTE));
        });

        // ── Modo claro / oscuro ─────────────────────────────────────
        function applyTheme(dark) {
            if (dark) {
                document.documentElement.setAttribute('data-theme', 'dark');
            } else {
                document.documentElement.removeAttribute('data-theme');
                document.documentElement.style.background = '';
            }
        }
        function toggleTheme() {
            const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
            const newDark = !isDark;
            localStorage.setItem('ibbs_theme', newDark ? 'dark' : 'light');
            applyTheme(newDark);
        }
        (function(){ applyTheme(localStorage.getItem('ibbs_theme') === 'dark'); })();

        // ════════════════════════════════════════════════════════
        // AULA VIRTUAL — consolidada dentro del portal del docente.
        // Reutiliza tal cual el backend de siempre (api/aula.php,
        // api/clases_vivo.php, api/clases_grabadas.php — los mismos que
        // usa modulo_aula.php / modulo_vivo.php / modulo_grabaciones.php)
        // así que no hace falta ninguna lógica nueva del lado del
        // servidor: esto es solo una forma más directa de llegar a lo
        // mismo sin salir del portal.
        // ════════════════════════════════════════════════════════
        let _aulaMid = null;
        function hAula(s) { const d = document.createElement('div'); d.textContent = String(s ?? ''); return d.innerHTML; }
        function _aulaCsrf() { const m = document.querySelector('meta[name="csrf-token"]'); return m ? m.content : ''; }
        async function _aulaPost(endpoint, action, params) {
            const fd = new FormData();
            fd.append('action', action);
            fd.append('csrf_token', _aulaCsrf());
            if (params) Object.keys(params).forEach(k => { if (params[k] !== undefined && params[k] !== null) fd.append(k, params[k]); });
            try {
                const r = await fetch(endpoint, { method: 'POST', body: fd });
                return await r.json();
            } catch (e) { return { ok: false, msg: 'Error de conexión.' }; }
        }

        function cargarAulaMateria() {
            _aulaMid = document.getElementById('aulaMateriaSel').value || null;
            const empty = document.getElementById('aulaEmpty');
            const panel = document.getElementById('aulaPanel');
            if (!_aulaMid) { empty.style.display = 'block'; panel.style.display = 'none'; return; }
            empty.style.display = 'none';
            panel.style.display = 'block';
            const activo = document.querySelector('#aulaPanel .tab-btn.active')?.dataset.aulatab || 'anuncios';
            cargarAulaTab(activo);
        }

        function switchAulaTab(tab, btn) {
            document.querySelectorAll('#aulaPanel .tab-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            document.querySelectorAll('.aula-tabpane').forEach(p => p.style.display = 'none');
            document.getElementById('aulaTab-' + tab).style.display = 'block';
            cargarAulaTab(tab);
        }

        function cargarAulaTab(tab) {
            if (!_aulaMid) return;
            if (tab === 'anuncios') cargarAulaAnuncios();
            else if (tab === 'materiales') cargarAulaMateriales();
            else if (tab === 'actividades') cargarAulaActividades();
            else if (tab === 'vivo') cargarAulaVivo();
            else if (tab === 'grabadas') cargarAulaGrabadas();
        }

        // ── Anuncios ──────────────────────────────────────────
        async function cargarAulaAnuncios() {
            const box = document.getElementById('aulaAnunciosList');
            box.innerHTML = '<div style="text-align:center;padding:2rem;color:var(--muted);grid-column:1/-1;"><span class="spin"></span></div>';
            const d = await _aulaPost('api/aula.php', 'anuncio_list', { materia_id: _aulaMid });
            if (!d?.ok) { box.innerHTML = `<p style="color:var(--red);grid-column:1/-1;">${hAula(d?.msg || 'Error al cargar.')}</p>`; return; }
            if (!d.data.length) { box.innerHTML = '<p style="color:var(--muted);grid-column:1/-1;">Todavía no hay anuncios en esta materia.</p>'; return; }
            box.innerHTML = d.data.map(a => `
                <div class="card" style="${a.fijado == 1 ? 'border-left:4px solid var(--lime2);' : ''}">
                    <div class="card-body">
                        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:.5rem;">
                            <h4 style="font-family:'Playfair Display',serif;font-size:1.05rem;color:var(--ink);">${a.fijado == 1 ? '📌 ' : ''}${hAula(a.titulo)}</h4>
                            <button onclick="borrarAulaAnuncio(${a.id})" style="background:none;border:none;color:var(--red);cursor:pointer;font-size:.9rem;" title="Eliminar"><i class="fas fa-trash"></i></button>
                        </div>
                        <p style="font-size:.85rem;color:var(--ink);margin:.5rem 0;white-space:pre-wrap;">${hAula(a.contenido)}</p>
                        <p style="font-size:.72rem;color:var(--muted);">${hAula(a.autor)} · ${a.creado_en ? a.creado_en.substring(0,16) : ''}</p>
                    </div>
                </div>`).join('');
        }
        function abrirModalAnuncio() {
            document.getElementById('aulaAnuncioId').value = '';
            document.getElementById('aulaAnuncioTituloInput').value = '';
            document.getElementById('aulaAnuncioContenido').value = '';
            document.getElementById('aulaAnuncioFijado').checked = false;
            openModal('modal-aula-anuncio');
        }
        async function guardarAulaAnuncio(e) {
            e.preventDefault();
            const titulo = document.getElementById('aulaAnuncioTituloInput').value.trim();
            const contenido = document.getElementById('aulaAnuncioContenido').value.trim();
            const fijado = document.getElementById('aulaAnuncioFijado').checked ? 1 : 0;
            const d = await _aulaPost('api/aula.php', 'anuncio_create', { materia_id: _aulaMid, titulo, contenido, fijado });
            if (d?.ok) { Ibbs.success(d.msg); closeModal('modal-aula-anuncio'); cargarAulaAnuncios(); } else Ibbs.error(d?.msg || 'No se pudo publicar.');
        }
        async function borrarAulaAnuncio(id) {
            const r = await Ibbs.confirm({ title: '¿Eliminar este anuncio?', danger: true, confirm: 'Sí, eliminar' });
            if (!r.isConfirmed) return;
            const d = await _aulaPost('api/aula.php', 'anuncio_delete', { id, materia_id: _aulaMid });
            if (d?.ok) cargarAulaAnuncios(); else Ibbs.error(d?.msg || 'Error al eliminar.');
        }

        // ── Materiales ────────────────────────────────────────
        function _aulaFmtBytes(b) {
            b = parseInt(b) || 0;
            if (b < 1024) return b + ' B';
            if (b < 1024 * 1024) return (b / 1024).toFixed(1) + ' KB';
            return (b / (1024 * 1024)).toFixed(1) + ' MB';
        }
        async function cargarAulaMateriales() {
            const box = document.getElementById('aulaMaterialesList');
            box.innerHTML = '<tr><td colspan="4" style="text-align:center;padding:2rem;"><span class="spin"></span></td></tr>';
            const d = await _aulaPost('api/aula.php', 'material_list', { materia_id: _aulaMid });
            if (!d?.ok) { box.innerHTML = `<tr><td colspan="4" style="color:var(--red);">${hAula(d?.msg || 'Error al cargar.')}</td></tr>`; return; }
            if (!d.data.length) { box.innerHTML = '<tr class="empty-row"><td colspan="4">Todavía no hay materiales en esta materia.</td></tr>'; return; }
            box.innerHTML = d.data.map(m => `
                <tr>
                    <td><strong>${hAula(m.titulo)}</strong>${m.descripcion ? `<div style="font-size:.75rem;color:var(--muted);">${hAula(m.descripcion)}</div>` : ''}</td>
                    <td><span class="badge" style="background:var(--cream);border:1px solid var(--border);">${hAula((m.archivo_tipo||'').toUpperCase())}</span> <span style="font-size:.72rem;color:var(--muted);">${_aulaFmtBytes(m.tamano_bytes)}</span></td>
                    <td style="font-size:.78rem;color:var(--muted);">${hAula(m.autor)}<br>${m.creado_en ? m.creado_en.substring(0,16) : ''}</td>
                    <td style="white-space:nowrap;">
                        <a href="api/aula.php?action=material_download&id=${m.id}" class="btn btn-secondary btn-sm" title="Descargar"><i class="fas fa-download"></i></a>
                        <button onclick="borrarAulaMaterial(${m.id})" class="btn btn-secondary btn-sm" style="color:var(--red);" title="Eliminar"><i class="fas fa-trash"></i></button>
                    </td>
                </tr>`).join('');
        }
        function abrirModalMaterial() {
            document.getElementById('aulaMaterialTitulo').value = '';
            document.getElementById('aulaMaterialDescripcion').value = '';
            document.getElementById('aulaMaterialArchivo').value = '';
            openModal('modal-aula-material');
        }
        async function guardarAulaMaterial(e) {
            e.preventDefault();
            const archivo = document.getElementById('aulaMaterialArchivo').files[0];
            if (!archivo) { Ibbs.warn('Elegí un archivo.'); return; }
            const btn = document.getElementById('btnAulaMaterialSubir');
            btn.disabled = true; btn.textContent = 'Subiendo…';
            const fd = new FormData();
            fd.append('action', 'material_create');
            fd.append('csrf_token', _aulaCsrf());
            fd.append('materia_id', _aulaMid);
            fd.append('titulo', document.getElementById('aulaMaterialTitulo').value.trim());
            fd.append('descripcion', document.getElementById('aulaMaterialDescripcion').value.trim());
            fd.append('archivo', archivo);
            let d;
            try { const r = await fetch('api/aula.php', { method: 'POST', body: fd }); d = await r.json(); }
            catch (e2) { d = { ok: false, msg: 'Error de conexión.' }; }
            btn.disabled = false; btn.textContent = 'Subir';
            if (d?.ok) { Ibbs.success(d.msg); closeModal('modal-aula-material'); cargarAulaMateriales(); } else Ibbs.error(d?.msg || 'No se pudo subir.');
        }
        async function borrarAulaMaterial(id) {
            const r = await Ibbs.confirm({ title: '¿Eliminar este material?', text: 'También se borra el archivo del servidor.', danger: true, confirm: 'Sí, eliminar' });
            if (!r.isConfirmed) return;
            const d = await _aulaPost('api/aula.php', 'material_delete', { id, materia_id: _aulaMid });
            if (d?.ok) cargarAulaMateriales(); else Ibbs.error(d?.msg || 'Error al eliminar.');
        }

        // ── Actividades (nota directa, sin entrega de archivo) ──
        async function cargarAulaActividades() {
            const box = document.getElementById('aulaActividadesList');
            box.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:2rem;"><span class="spin"></span></td></tr>';
            const d = await _aulaPost('api/aula.php', 'actividad_list', { materia_id: _aulaMid });
            if (!d?.ok) { box.innerHTML = `<tr><td colspan="5" style="color:var(--red);">${hAula(d?.msg || 'Error al cargar.')}</td></tr>`; return; }
            if (!d.data.length) { box.innerHTML = '<tr class="empty-row"><td colspan="5">Todavía no hay actividades en esta materia.</td></tr>'; return; }
            box.innerHTML = d.data.map(a => `
                <tr>
                    <td><strong>${hAula(a.titulo)}</strong></td>
                    <td><span class="badge" style="background:var(--cream);border:1px solid var(--border);">${hAula(a.tipo)}</span></td>
                    <td>${parseFloat(a.nota_max).toFixed(1)}</td>
                    <td style="font-size:.8rem;color:var(--muted);">${a.fecha ? a.fecha.substring(0,10) : '—'}</td>
                    <td style="white-space:nowrap;">
                        <button onclick="abrirCalificarActividad(${a.id},'${hAula(a.titulo).replace(/'/g,"\\'")}')" class="btn btn-secondary btn-sm">Calificar</button>
                        <button onclick="borrarAulaActividad(${a.id})" class="btn btn-secondary btn-sm" style="color:var(--red);" title="Eliminar"><i class="fas fa-trash"></i></button>
                    </td>
                </tr>`).join('');
        }
        function abrirModalActividad() {
            document.getElementById('aulaActTitulo').value = '';
            document.getElementById('aulaActDescripcion').value = '';
            document.getElementById('aulaActTipo').value = 'actividad';
            document.getElementById('aulaActNotaMax').value = 20;
            document.getElementById('aulaActFecha').value = '';
            openModal('modal-aula-actividad');
        }
        async function guardarAulaActividad(e) {
            e.preventDefault();
            const d = await _aulaPost('api/aula.php', 'actividad_create', {
                materia_id: _aulaMid,
                titulo: document.getElementById('aulaActTitulo').value.trim(),
                descripcion: document.getElementById('aulaActDescripcion').value.trim(),
                tipo: document.getElementById('aulaActTipo').value,
                nota_max: document.getElementById('aulaActNotaMax').value,
                fecha: document.getElementById('aulaActFecha').value,
            });
            if (d?.ok) { Ibbs.success(d.msg); closeModal('modal-aula-actividad'); cargarAulaActividades(); } else Ibbs.error(d?.msg || 'No se pudo crear.');
        }
        async function borrarAulaActividad(id) {
            const r = await Ibbs.confirm({ title: '¿Eliminar esta actividad?', text: 'También se borran las calificaciones ya cargadas.', danger: true, confirm: 'Sí, eliminar' });
            if (!r.isConfirmed) return;
            const d = await _aulaPost('api/aula.php', 'actividad_delete', { id, materia_id: _aulaMid });
            if (d?.ok) cargarAulaActividades(); else Ibbs.error(d?.msg || 'Error al eliminar.');
        }
        async function abrirCalificarActividad(id, titulo) {
            document.getElementById('aulaCalActId').value = id;
            document.getElementById('aulaCalActTitulo').textContent = titulo;
            const body = document.getElementById('aulaCalActBody');
            body.innerHTML = '<tr><td colspan="3" style="text-align:center;padding:1.5rem;"><span class="spin"></span></td></tr>';
            openModal('modal-aula-calificar-act');
            const d = await _aulaPost('api/aula.php', 'actividad_calificaciones', { actividad_id: id, materia_id: _aulaMid });
            if (!d?.ok) { body.innerHTML = `<tr><td colspan="3" style="color:var(--red);">${hAula(d?.msg||'Error al cargar.')}</td></tr>`; return; }
            const notaMax = d.data.actividad.nota_max;
            if (!d.data.alumnos.length) { body.innerHTML = '<tr class="empty-row"><td colspan="3">Esta materia no tiene alumnos inscritos.</td></tr>'; return; }
            body.innerHTML = d.data.alumnos.map(al => `
                <tr data-alumno-id="${al.id}">
                    <td>${hAula(al.apellido)}, ${hAula(al.nombre)}</td>
                    <td><input type="number" class="aula-cal-nota" min="0" max="${notaMax}" step="0.1" value="${al.nota !== null ? al.nota : ''}" style="width:90px;"></td>
                    <td><input type="text" class="aula-cal-obs" value="${hAula(al.observacion||'')}" placeholder="Observación…" style="width:100%;"></td>
                </tr>`).join('');
        }
        async function guardarAulaCalificarAct() {
            const aid = document.getElementById('aulaCalActId').value;
            const filas = document.querySelectorAll('#aulaCalActBody tr[data-alumno-id]');
            const notas = [];
            filas.forEach(tr => {
                const alumno_id = tr.dataset.alumnoId;
                const nota = tr.querySelector('.aula-cal-nota').value;
                const observacion = tr.querySelector('.aula-cal-obs').value;
                notas.push({ alumno_id, nota: nota === '' ? null : nota, observacion });
            });
            const d = await _aulaPost('api/aula.php', 'actividad_calificar_bulk', { actividad_id: aid, materia_id: _aulaMid, notas: JSON.stringify(notas) });
            if (d?.ok) { Ibbs.success(d.msg); closeModal('modal-aula-calificar-act'); } else Ibbs.error(d?.msg || 'No se pudo guardar.');
        }

        // ── En Vivo (reutiliza api/clases_vivo.php, sin tocar su propio módulo) ──
        const AULA_VIVO_EST = { programada: ['Programada', '#6366f1'], en_curso: ['🔴 En curso', '#dc2626'], finalizada: ['Finalizada', '#6b7280'], cancelada: ['Cancelada', '#9ca3af'] };
        async function cargarAulaVivo() {
            const box = document.getElementById('aulaVivoList');
            box.innerHTML = '<div style="text-align:center;padding:2rem;color:var(--muted);grid-column:1/-1;"><span class="spin"></span></div>';
            const d = await _aulaPost('api/clases_vivo.php', 'vivo_list', { materia_id: _aulaMid });
            if (!d?.ok) { box.innerHTML = `<p style="color:var(--red);grid-column:1/-1;">${hAula(d?.msg || 'Error al cargar.')}</p>`; return; }
            if (!d.data.length) { box.innerHTML = '<p style="color:var(--muted);grid-column:1/-1;">Todavía no hay clases en vivo programadas.</p>'; return; }
            box.innerHTML = d.data.map(c => {
                const est = AULA_VIVO_EST[c.estado] || ['—', '#666'];
                return `<div class="card">
                    <div class="card-body">
                        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:.5rem;">
                            <h4 style="font-family:'Playfair Display',serif;font-size:1.05rem;color:var(--ink);">${hAula(c.titulo)}</h4>
                            <button onclick="borrarAulaVivo(${c.id})" style="background:none;border:none;color:var(--red);cursor:pointer;" title="Eliminar"><i class="fas fa-trash"></i></button>
                        </div>
                        ${c.descripcion ? `<p style="font-size:.82rem;color:var(--muted);">${hAula(c.descripcion)}</p>` : ''}
                        <p style="font-size:.78rem;color:var(--muted);margin:.4rem 0;">${c.fecha_hora ? c.fecha_hora.substring(0,16).replace('T',' ') : ''} · ${hAula(c.plataforma)}</p>
                        <span class="badge" style="background:${est[1]}22;color:${est[1]};border:1px solid ${est[1]};margin-bottom:.6rem;display:inline-block;">${est[0]}</span>
                        <div style="display:flex;gap:.4rem;flex-wrap:wrap;margin-top:.5rem;">
                            ${c.join_url ? `<a href="${hAula(c.join_url)}" target="_blank" class="btn btn-primary btn-sm">▶ Unirse</a>` : ''}
                            <select onchange="cambiarEstadoAulaVivo(${c.id}, this.value)" style="font-size:.78rem;padding:.3rem .5rem;">
                                ${Object.keys(AULA_VIVO_EST).map(k => `<option value="${k}" ${k===c.estado?'selected':''}>${AULA_VIVO_EST[k][0].replace('🔴 ','')}</option>`).join('')}
                            </select>
                        </div>
                    </div>
                </div>`;
            }).join('');
        }
        function abrirModalVivo() {
            document.getElementById('aulaVivoTitulo').value = '';
            document.getElementById('aulaVivoDescripcion').value = '';
            document.getElementById('aulaVivoFecha').value = '';
            document.getElementById('aulaVivoPlataforma').value = 'jitsi';
            document.getElementById('aulaVivoUrl').value = '';
            document.getElementById('aulaVivoUrlField').style.display = 'none';
            openModal('modal-aula-vivo');
        }
        async function guardarAulaVivo(e) {
            e.preventDefault();
            const d = await _aulaPost('api/clases_vivo.php', 'vivo_create', {
                materia_id: _aulaMid,
                titulo: document.getElementById('aulaVivoTitulo').value.trim(),
                descripcion: document.getElementById('aulaVivoDescripcion').value.trim(),
                plataforma: document.getElementById('aulaVivoPlataforma').value,
                fecha_hora: document.getElementById('aulaVivoFecha').value,
                url: document.getElementById('aulaVivoUrl').value.trim(),
            });
            if (d?.ok) { Ibbs.success(d.msg); closeModal('modal-aula-vivo'); cargarAulaVivo(); } else Ibbs.error(d?.msg || 'No se pudo crear.');
        }
        async function cambiarEstadoAulaVivo(id, estado) {
            const d = await _aulaPost('api/clases_vivo.php', 'vivo_set_estado', { id, materia_id: _aulaMid, estado });
            if (d?.ok) cargarAulaVivo(); else Ibbs.error(d?.msg || 'No se pudo actualizar.');
        }
        async function borrarAulaVivo(id) {
            const r = await Ibbs.confirm({ title: '¿Eliminar esta clase en vivo?', danger: true, confirm: 'Sí, eliminar' });
            if (!r.isConfirmed) return;
            const d = await _aulaPost('api/clases_vivo.php', 'vivo_delete', { id, materia_id: _aulaMid });
            if (d?.ok) cargarAulaVivo(); else Ibbs.error(d?.msg || 'Error al eliminar.');
        }

        // ── Grabadas (reutiliza api/clases_grabadas.php, sin tocar su propio módulo) ──
        async function cargarAulaGrabadas() {
            const box = document.getElementById('aulaGrabadasList');
            box.innerHTML = '<div style="text-align:center;padding:2rem;color:var(--muted);grid-column:1/-1;"><span class="spin"></span></div>';
            const d = await _aulaPost('api/clases_grabadas.php', 'clase_list', { materia_id: _aulaMid });
            if (!d?.ok) { box.innerHTML = `<p style="color:var(--red);grid-column:1/-1;">${hAula(d?.msg || 'Error al cargar.')}</p>`; return; }
            if (!d.data.length) { box.innerHTML = '<p style="color:var(--muted);grid-column:1/-1;">Todavía no hay clases grabadas en esta materia.</p>'; return; }
            box.innerHTML = d.data.map(c => `
                <div class="card">
                    <div class="card-body">
                        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:.5rem;">
                            <h4 style="font-family:'Playfair Display',serif;font-size:1.05rem;color:var(--ink);">${hAula(c.titulo)}</h4>
                            <button onclick="borrarAulaGrabada(${c.id})" style="background:none;border:none;color:var(--red);cursor:pointer;" title="Eliminar"><i class="fas fa-trash"></i></button>
                        </div>
                        ${c.descripcion ? `<p style="font-size:.82rem;color:var(--muted);">${hAula(c.descripcion)}</p>` : ''}
                        <p style="font-size:.78rem;color:var(--muted);margin:.4rem 0 .6rem;">${hAula(c.plataforma)} ${c.fecha ? '· ' + c.fecha.substring(0,10) : ''}</p>
                        <a href="${hAula(c.url)}" target="_blank" rel="noopener" class="btn btn-secondary btn-sm">🔗 Abrir video</a>
                    </div>
                </div>`).join('');
        }
        function abrirModalGrabada() {
            document.getElementById('aulaGrabTitulo').value = '';
            document.getElementById('aulaGrabUrl').value = '';
            document.getElementById('aulaGrabDescripcion').value = '';
            document.getElementById('aulaGrabFecha').value = '';
            openModal('modal-aula-grabada');
        }
        async function guardarAulaGrabada(e) {
            e.preventDefault();
            const d = await _aulaPost('api/clases_grabadas.php', 'clase_create', {
                materia_id: _aulaMid,
                titulo: document.getElementById('aulaGrabTitulo').value.trim(),
                url: document.getElementById('aulaGrabUrl').value.trim(),
                descripcion: document.getElementById('aulaGrabDescripcion').value.trim(),
                fecha: document.getElementById('aulaGrabFecha').value,
            });
            if (d?.ok) { Ibbs.success(d.msg); closeModal('modal-aula-grabada'); cargarAulaGrabadas(); } else Ibbs.error(d?.msg || 'No se pudo guardar.');
        }
        async function borrarAulaGrabada(id) {
            const r = await Ibbs.confirm({ title: '¿Eliminar esta clase grabada?', danger: true, confirm: 'Sí, eliminar' });
            if (!r.isConfirmed) return;
            const d = await _aulaPost('api/clases_grabadas.php', 'clase_delete', { id, materia_id: _aulaMid });
            if (d?.ok) cargarAulaGrabadas(); else Ibbs.error(d?.msg || 'Error al eliminar.');
        }
    </script>
</body>
</html>