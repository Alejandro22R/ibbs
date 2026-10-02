<?php
require_once __DIR__.'/config/bootstrap.php';
if (empty($_SESSION['loggedin']) || $_SESSION['rol'] !== 'alumno') {
    header('Location: login.php');
    exit;
}

$con = db();
$user_id = $_SESSION['user_id'];

// Obtener ID y datos del alumno (Cambiado a SELECT * para traer foto, email, etc)
$stmt = mysqli_prepare($con, "SELECT * FROM alumnos WHERE usuario_id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$alumno = mysqli_fetch_assoc($res);

if (!$alumno) {
    // Si no está el FK, buscar por el nombre (fallback para prueba)
    $stmt2 = mysqli_prepare($con, "SELECT * FROM alumnos WHERE nombre = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt2, "s", $_SESSION['usuario']);
    mysqli_stmt_execute($stmt2);
    $alumno = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt2));
}

$alumno_id = $alumno ? $alumno['id'] : 0;
$nombre_alumno = $alumno ? ($alumno['nombre'] . ' ' . $alumno['apellido']) : $_SESSION['usuario'];
$inicial = strtoupper(substr($nombre_alumno, 0, 1));

// Obtener materias del alumno
$query_materias = "SELECT m.*, d.nombre as doc_nombre, d.apellido as doc_apellido 
                   FROM materias m 
                   JOIN materia_alumno ma ON m.id = ma.materia_id 
                   LEFT JOIN materia_docente md ON m.id = md.materia_id
                   LEFT JOIN docentes d ON md.docente_id = d.id
                   WHERE ma.alumno_id = ? AND m.activo = 1";
$stmt_m = mysqli_prepare($con, $query_materias);
mysqli_stmt_bind_param($stmt_m, "i", $alumno_id);
mysqli_stmt_execute($stmt_m);
$res_materias = mysqli_stmt_get_result($stmt_m);
$materias = [];
while($row = mysqli_fetch_assoc($res_materias)) $materias[] = $row;

// Token de WebSocket (opcional — ver config/ws_config.php) con un
// canal por cada materia en la que este alumno puede tener el chat
// abierto, para que el foro se actualice al instante si hay un VPS
// con ws-server/ configurado.
$ws_token = ws_enabled() ? ws_token_for_materias($con, $user_id, $_SESSION['rol'], $_SESSION['usuario'], array_column($materias, 'id')) : null;

// Materias disponibles para autoinscripción (solo si el alumno es "regular")
$materias_disponibles = [];
if ($alumno && !empty($alumno['regular'])) {
    $ids_inscritas = array_column($materias, 'id');
    $excluir = count($ids_inscritas) ? implode(',', array_map('intval', $ids_inscritas)) : '0';
    $rd = mysqli_query($con, "SELECT id,nombre,codigo,estado FROM materias WHERE activo=1 AND estado!='culminada' AND inscripcion_abierta=1 AND id NOT IN ($excluir) ORDER BY nombre");
    while ($row = mysqli_fetch_assoc($rd)) $materias_disponibles[] = $row;
}

// Obtener tareas y entregas
$query_tareas = "SELECT t.*, m.nombre as materia_nombre, e.id as entrega_id, e.nota, e.observacion
                 FROM tareas t
                 JOIN materias m ON t.materia_id = m.id
                 JOIN materia_alumno ma ON m.id = ma.materia_id
                 LEFT JOIN entregas e ON t.id = e.tarea_id AND e.alumno_id = ?
                 WHERE ma.alumno_id = ?
                 ORDER BY t.fecha_limite ASC";
$stmt_t = mysqli_prepare($con, $query_tareas);
mysqli_stmt_bind_param($stmt_t, "ii", $alumno_id, $alumno_id);
mysqli_stmt_execute($stmt_t);
$res_tareas = mysqli_stmt_get_result($stmt_t);
$tareas = [];
$pendientes_count = 0;
while($row = mysqli_fetch_assoc($res_tareas)) {
    $tareas[] = $row;
    if (!$row['entrega_id']) $pendientes_count++;
}

// Obtener Notas Finales
$query_notas = "SELECT m.nombre, ma.nota_final, ma.nota_fecha 
                FROM materia_alumno ma 
                JOIN materias m ON ma.materia_id = m.id 
                WHERE ma.alumno_id = ? AND ma.nota_final IS NOT NULL";
$stmt_n = mysqli_prepare($con, $query_notas);
mysqli_stmt_bind_param($stmt_n, "i", $alumno_id);
mysqli_stmt_execute($stmt_n);
$res_notas = mysqli_stmt_get_result($stmt_n);
$notas = [];
$suma_notas = 0;
while($row = mysqli_fetch_assoc($res_notas)) {
    $notas[] = $row;
    $suma_notas += $row['nota_final'];
}
$promedio = count($notas) > 0 ? round($suma_notas / count($notas), 2) : 'N/A';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?=htmlspecialchars(csrf_token())?>">
    <?php if ($ws_token): ?>
    <meta name="ibbs-ws-url" content="<?=htmlspecialchars(ws_public_url())?>">
    <meta name="ibbs-ws-token" content="<?=htmlspecialchars($ws_token)?>">
    <?php endif; ?>
    <title>Portal del Alumno | IBBS</title>
    
    <!-- Google Fonts (Nunito y Playfair Display) -->
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;500;600;700&family=Playfair+Display:ital,wght@0,400;0,700;1,400&display=swap" rel="stylesheet">
    
    <!-- FontAwesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- Enlace a estilos globales IBBS (opcional si están en la misma carpeta) -->
    <link rel="stylesheet" href="assets/ibbs.css">

    <!-- SweetAlert2 — reemplaza los alert()/confirm() nativos del navegador -->
    <script src="assets/libs/sweetalert2.all.min.js"></script>
    <script src="assets/ibbs-alerts.js"></script>
    <script src="assets/ibbs-tour.js"></script>
    <script src="assets/ibbs-password-toggle.js"></script>
    <script src="assets/ibbs-shortcuts.js"></script>
    <script src="assets/ibbs-notif-bell.js"></script>

    <!-- Tailwind CSS con Configuración de Tema IBBS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        ibbs: {
                            ink: '#1a4d2e',
                            ink2: '#1e5c36',
                            cream: '#f5f0e8',
                            paper: '#fdfaf4',
                            lime: '#39ff14',
                            lime2: '#2ecc10',
                            muted: '#7a8c72',
                            border: '#e0d8c8',
                            green: '#16a34a',
                            red: '#dc2626',
                            amber: '#d97706',
                            blue: '#2563eb'
                        }
                    },
                    fontFamily: {
                        sans: ['Nunito', 'sans-serif'],
                        serif: ['Playfair Display', 'serif'],
                    }
                }
            }
        }
    </script>
    
    <style>
        body { font-family: 'Nunito', sans-serif; background-color: #f5f0e8; }
        
        /* Animaciones de vistas */
        .view-section { animation: fadeIn 0.3s ease-out forwards; }
        @keyframes fadeIn { 
            from { opacity: 0; transform: translateY(8px); } 
            to { opacity: 1; transform: translateY(0); } 
        }
        
        /* Custom scrollbar adaptado a IBBS */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #e0d8c8; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #7a8c72; }
        
        /* Botones del menú lateral */
        .nav-btn { border-left: 3px solid transparent; transition: all 0.2s; }
        .nav-btn.active { 
            background-color: rgba(57, 255, 20, 0.08); 
            color: #39ff14; 
            border-left-color: #39ff14;
        }

        /* Botón estilo IBBS Primary */
        .btn-ibbs {
            background-color: #1a4d2e;
            color: #39ff14;
            box-shadow: 0 2px 0 rgba(0,0,0,.15), inset 0 1px 0 rgba(255,255,255,.05);
            transition: all 0.2s;
        }
        .btn-ibbs:hover {
            background-color: #1e5c36;
            box-shadow: 0 4px 16px rgba(57,255,20,.2), 0 2px 0 rgba(0,0,0,.2);
            transform: translateY(-1px);
        }
        
        /* Burbujas de chat */
        .chat-bubble-me { background-color: #1a4d2e; color: #39ff14; border-bottom-right-radius: 4px; box-shadow: 0 2px 0 rgba(0,0,0,.1); }
        .chat-bubble-other { background-color: #fdfaf4; color: #1a4d2e; border-bottom-left-radius: 4px; border: 1px solid #e0d8c8; box-shadow: 0 1px 3px rgba(0,0,0,.03); }
        
        /* Sidebar transition para mobile */
        @media (max-width: 768px) {
            #sidebar { transition: transform 0.3s cubic-bezier(.22,1,.36,1); }
            .sidebar-open { transform: translateX(0) !important; }
        }

        /* Overlay patrón puntos */
        .bg-dots {
            background-image: radial-gradient(rgba(57,255,20,.05) 1px, transparent 1px);
            background-size: 20px 20px;
        }

        /* ══════════════════════════════════════════════════════════
           TOQUES VISUALES ADICIONALES — clases nuevas, no tocan nada
           de lo que ya existía; solo se agregan como class="..." extra
           en elementos puntuales para que se sientan más vivos.
        ══════════════════════════════════════════════════════════ */

        /* Orbes de luz difuminados — dan profundidad al banner de
           bienvenida sin tocar su fondo ni su padding actual. */
        .ibbs-glow-orb {
            position: absolute; border-radius: 50%; filter: blur(40px);
            pointer-events: none; opacity: .35;
            animation: ibbsFloat 8s ease-in-out infinite;
        }
        .ibbs-glow-orb.o1 { width: 180px; height: 180px; background: #39ff14; top: -60px; right: -40px; }
        .ibbs-glow-orb.o2 { width: 140px; height: 140px; background: #2563eb; bottom: -50px; right: 120px; animation-delay: -3s; }
        @keyframes ibbsFloat { 0%,100% { transform: translateY(0) scale(1); } 50% { transform: translateY(-14px) scale(1.06); } }

        /* Insignia con pulso — para números que piden atención (tareas
           pendientes) sin cambiar el badge que ya existe, solo le suma
           un anillo animado alrededor. */
        .ibbs-badge-pulse { position: relative; }
        .ibbs-badge-pulse::after {
            content: ''; position: absolute; inset: 0; border-radius: inherit;
            box-shadow: 0 0 0 0 rgba(57,255,20,.6);
            animation: ibbsPulseRing 2s ease-out infinite;
            pointer-events: none;
        }
        @keyframes ibbsPulseRing {
            0%   { box-shadow: 0 0 0 0 rgba(57,255,20,.55); }
            70%  { box-shadow: 0 0 0 8px rgba(57,255,20,0); }
            100% { box-shadow: 0 0 0 0 rgba(57,255,20,0); }
        }

        /* Confeti — festejo breve cuando algo sale bien (entregar una
           tarea, inscribirse en una materia). Puro CSS + un puñado de
           divs generados por JS, nada de librerías externas. */
        .ibbs-confetti-piece {
            position: fixed; top: -12px; z-index: 99999; pointer-events: none;
            border-radius: 2px; animation: ibbsConfettiFall linear forwards;
        }
        @keyframes ibbsConfettiFall {
            to { transform: translateY(105vh) rotate(540deg); opacity: .2; }
        }

        /* Brillo sutil de "cargando" — alternativa más agradable al
           spinner plano para tarjetas que todavía no tienen datos. */
        .ibbs-shimmer {
            background: linear-gradient(90deg, rgba(224,216,200,.35) 25%, rgba(224,216,200,.6) 37%, rgba(224,216,200,.35) 63%);
            background-size: 400% 100%;
            animation: ibbsShimmer 1.4s ease infinite;
        }
        @keyframes ibbsShimmer { 0% { background-position: 100% 50%; } 100% { background-position: 0 50%; } }
    </style>
</head>
<body class="flex h-screen overflow-hidden text-ibbs-ink bg-ibbs-cream selection:bg-ibbs-lime/30 selection:text-ibbs-ink font-sans">

    <!-- Sidebar -->
    <aside class="w-64 bg-ibbs-ink text-white flex flex-col h-full shadow-2xl flex-shrink-0 absolute md:relative z-30 transform -translate-x-full md:translate-x-0 transition-transform duration-300 border-r border-ibbs-lime/10 overflow-hidden" id="sidebar">
        <!-- Patrón de fondo -->
        <div class="absolute inset-0 bg-dots pointer-events-none"></div>

        <!-- Logo -->
        <div class="h-16 flex items-center px-6 border-b border-white/10 font-bold text-xl tracking-wider justify-between md:justify-start relative z-10">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-ibbs-lime flex items-center justify-center shadow-[0_0_14px_rgba(57,255,20,0.35)]">
                    <i class="fas fa-graduation-cap text-ibbs-ink text-sm"></i>
                </div>
                <span class="font-serif tracking-widest">IBBS<span class="text-ibbs-lime/80 text-xs tracking-normal block -mt-1 font-sans">Portal Alumno</span></span>
            </div>
            <button onclick="toggleSidebar()" class="md:hidden text-white/50 hover:text-white">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <!-- Perfil Usuario -->
        <div class="p-6 border-b border-white/10 relative z-10">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-ibbs-lime text-ibbs-ink flex items-center justify-center text-xl font-serif font-bold shadow-[0_0_15px_rgba(57,255,20,0.25)] overflow-hidden">
                    <?php if(!empty($alumno['foto'])): ?>
                        <img src="<?= htmlspecialchars($alumno['foto']) ?>" alt="Foto" class="w-full h-full object-cover">
                    <?php else: ?>
                        <?= htmlspecialchars($inicial) ?>
                    <?php endif; ?>
                </div>
                <div class="overflow-hidden">
                    <p class="text-sm font-bold truncate text-white"><?= htmlspecialchars($nombre_alumno) ?></p>
                    <p class="text-[10px] uppercase tracking-wider text-ibbs-lime2 font-semibold mt-0.5 bg-ibbs-lime2/10 inline-block px-2 py-0.5 rounded-full">Estudiante</p>
                </div>
            </div>
        </div>
        
        <!-- Navegación -->
        <nav class="flex-1 p-4 space-y-1.5 overflow-y-auto relative z-10">
            <p class="text-[10px] uppercase tracking-widest text-white/30 font-bold mb-3 px-3">Menú Principal</p>
            
            <button id="navBtnDashboard" data-tour="nav-dashboard" onclick="switchView('dashboard', this)" class="nav-btn active w-full flex items-center gap-3 px-4 py-2.5 rounded-lg text-white/60 hover:bg-white/10 hover:text-white">
                <i class="fas fa-home w-5 text-center"></i> <span class="font-medium text-sm">Inicio</span>
            </button>
            <button data-tour="nav-aula" onclick="switchView('aula', this)" class="nav-btn w-full flex items-center gap-3 px-4 py-2.5 rounded-lg text-white/60 hover:bg-white/10 hover:text-white">
                <i class="fas fa-desktop w-5 text-center"></i> <span class="font-medium text-sm">Aula Virtual</span>
            </button>
            <button id="navBtnMaterias" data-tour="nav-materias" onclick="switchView('materias', this)" class="nav-btn w-full flex items-center gap-3 px-4 py-2.5 rounded-lg text-white/60 hover:bg-white/10 hover:text-white">
                <i class="fas fa-book w-5 text-center"></i> <span class="font-medium text-sm">Mis Materias</span>
            </button>
            <button data-tour="nav-tareas" onclick="switchView('tareas', this)" class="nav-btn w-full flex items-center justify-between px-4 py-2.5 rounded-lg text-white/60 hover:bg-white/10 hover:text-white">
                <div class="flex items-center gap-3">
                    <i class="fas fa-tasks w-5 text-center"></i> <span class="font-medium text-sm">Tareas</span>
                </div>
                <?php if($pendientes_count > 0): ?>
                <span class="bg-ibbs-lime text-ibbs-ink text-[10px] font-bold px-2 py-0.5 rounded-full shadow-sm ibbs-badge-pulse"><?= $pendientes_count ?></span>
                <?php endif; ?>
            </button>
            <button data-tour="nav-chat" onclick="switchView('chat', this)" class="nav-btn w-full flex items-center gap-3 px-4 py-2.5 rounded-lg text-white/60 hover:bg-white/10 hover:text-white">
                <i class="fas fa-comments w-5 text-center"></i> <span class="font-medium text-sm">Foros de Clase</span>
            </button>
            <button data-tour="nav-notas" onclick="switchView('notas', this)" class="nav-btn w-full flex items-center gap-3 px-4 py-2.5 rounded-lg text-white/60 hover:bg-white/10 hover:text-white">
                <i class="fas fa-chart-line w-5 text-center"></i> <span class="font-medium text-sm">Calificaciones</span>
            </button>
            <button data-tour="nav-plan-notas" onclick="switchView('plan-notas', this); cargarPlanNotasAlumno();" class="nav-btn w-full flex items-center gap-3 px-4 py-2.5 rounded-lg text-white/60 hover:bg-white/10 hover:text-white">
                <i class="fas fa-list-check w-5 text-center"></i> <span class="font-medium text-sm">Plan de Notas</span>
            </button>
            <button data-tour="nav-constancias" onclick="switchView('constancias', this)" class="nav-btn w-full flex items-center gap-3 px-4 py-2.5 rounded-lg text-white/60 hover:bg-white/10 hover:text-white">
                <i class="fas fa-file-signature w-5 text-center"></i> <span class="font-medium text-sm">Constancias</span>
            </button>
            <button data-tour="nav-biblioteca" onclick="switchView('biblioteca', this); loadBiblioteca();" class="nav-btn w-full flex items-center gap-3 px-4 py-2.5 rounded-lg text-white/60 hover:bg-white/10 hover:text-white">
                <i class="fas fa-book w-5 text-center"></i> <span class="font-medium text-sm">Biblioteca</span>
            </button>

            <p class="text-[10px] uppercase tracking-widest text-white/30 font-bold mt-6 mb-3 px-3">Cuenta</p>
            <button id="navBtnPerfil" data-tour="nav-perfil" onclick="switchView('perfil', this)" class="nav-btn w-full flex items-center gap-3 px-4 py-2.5 rounded-lg text-white/60 hover:bg-white/10 hover:text-white">
                <i class="fas fa-user-cog w-5 text-center"></i> <span class="font-medium text-sm">Mi Perfil</span>
            </button>
            <button type="button" data-tour="nav-replay" data-palette-skip="1" onclick="IbbsTour.replay(window.IBBS_TOUR_ALUMNO)" class="ibbstour-replay nav-btn w-full flex items-center gap-3 px-4 py-2.5 rounded-lg text-white/60 hover:bg-white/10 hover:text-white">
                <i class="fas fa-circle-question w-5 text-center"></i> <span class="font-medium text-sm">Ver Tutorial</span>
            </button>
            <button type="button" data-palette-skip="1" onclick="IbbsShortcuts.openPalette()" class="nav-btn w-full flex items-center justify-between px-4 py-2.5 rounded-lg text-white/60 hover:bg-white/10 hover:text-white">
                <span class="flex items-center gap-3"><i class="fas fa-search w-5 text-center"></i> <span class="font-medium text-sm">Buscar</span></span>
                <kbd class="text-[10px] bg-white/10 px-1.5 py-0.5 rounded">Ctrl K</kbd>
            </button>
        </nav>

        <!-- Cerrar Sesión (Actualizado) -->
        <div class="p-4 border-t border-white/10 relative z-10">
            <a href="cerrar_sesion.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-white/50 hover:bg-ibbs-red/10 hover:text-ibbs-red transition-colors">
                <i class="fas fa-sign-out-alt w-5 text-center"></i> <span class="font-medium text-sm">Cerrar Sesión</span>
            </a>
        </div>
    </aside>

    <!-- Contenido Principal -->
    <main class="flex-1 flex flex-col h-full overflow-hidden relative">
        <!-- Overlay para mobile -->
        <div id="mobile-overlay" onclick="toggleSidebar()" class="fixed inset-0 bg-ibbs-ink/60 z-20 hidden md:hidden backdrop-blur-sm transition-opacity"></div>

        <!-- Header: antes solo existía en mobile; ahora queda visible
             siempre (también en escritorio) porque es el único lugar
             persistente, en todas las vistas, donde poner la campana
             de notificaciones — el resto del portal no tiene topbar
             propio como sí tiene portal_docente.php. -->
        <header class="h-16 bg-ibbs-paper border-b border-ibbs-border flex items-center justify-between px-4 z-10">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-lg bg-ibbs-lime flex items-center justify-center shadow-sm">
                    <i class="fas fa-graduation-cap text-ibbs-ink text-sm"></i>
                </div>
                <h1 class="font-serif font-bold text-lg text-ibbs-ink tracking-wide">IBBS</h1>
            </div>
            <div class="flex items-center gap-2">
                <div style="position: relative;">
                    <button id="notifBell" onclick="toggleNotifDrop()" title="Notificaciones" class="text-ibbs-muted hover:text-ibbs-ink focus:outline-none p-2 rounded-lg bg-ibbs-cream border border-ibbs-border" style="position:relative;">
                        <i class="fas fa-bell"></i>
                        <span id="notifCount" style="display:none;position:absolute;top:-4px;right:-4px;background:#dc2626;color:#fff;border-radius:50%;width:16px;height:16px;font-size:.55rem;font-weight:700;align-items:center;justify-content:center;line-height:1;"></span>
                    </button>
                    <div id="notifDrop" style="display:none;position:absolute;top:calc(100% + 8px);right:0;width:300px;max-height:420px;overflow-y:auto;background:#fdfaf4;border:1.5px solid #e0d8c8;border-radius:12px;box-shadow:0 12px 30px rgba(0,0,0,.14);z-index:200;">
                        <div style="padding:.7rem .9rem;border-bottom:1px solid #e0d8c8;display:flex;justify-content:space-between;align-items:center;">
                            <strong style="font-size:.85rem;">Notificaciones</strong>
                            <button onclick="marcarTodasLeidasDrop()" style="background:none;border:none;color:#16a34a;font-size:.72rem;cursor:pointer;">Marcar todas leídas</button>
                        </div>
                        <div id="notifDropList" style="padding:.4rem;"></div>
                    </div>
                </div>
                <button onclick="toggleSidebar()" class="md:hidden text-ibbs-muted hover:text-ibbs-ink focus:outline-none p-2 rounded-lg bg-ibbs-cream border border-ibbs-border">
                    <i class="fas fa-bars text-lg"></i>
                </button>
            </div>
        </header>

        <!-- Área desplazable de contenido -->
        <div class="flex-1 overflow-y-auto p-5 md:p-8">
            
            <!-- VISTA: DASHBOARD -->
            <div id="view-dashboard" class="view-section space-y-6 block">
                <!-- Banner Bienvenida -->
                <div class="bg-ibbs-ink rounded-[14px] p-7 text-white relative overflow-hidden">
                    <div class="absolute inset-0 bg-dots pointer-events-none"></div>
                    <div class="ibbs-glow-orb o1"></div>
                    <div class="ibbs-glow-orb o2"></div>
                    <div class="relative z-10">
                        <h2 class="text-2xl font-serif mb-1">Bienvenido, <em class="text-ibbs-lime not-italic"><?= htmlspecialchars(explode(' ', $nombre_alumno)[0]) ?></em> 👋</h2>
                        <p class="text-white/50 text-sm max-w-lg">Resumen general de tu actividad académica · <?= date('d M Y') ?></p>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                    <!-- Tarjeta Tareas -->
                    <div class="bg-ibbs-paper p-5 rounded-[14px] border border-ibbs-border flex items-center gap-4 cursor-pointer hover:-translate-y-1 hover:shadow-lg transition-all group" onclick="document.querySelector('#sidebar nav button:nth-child(4)').click()">
                        <div class="w-12 h-12 rounded-xl bg-ibbs-amber/10 text-ibbs-amber flex items-center justify-center text-xl group-hover:bg-ibbs-amber group-hover:text-white transition-colors">
                            <i class="fas fa-exclamation-circle"></i>
                        </div>
                        <div>
                            <h3 class="text-3xl font-serif text-ibbs-ink leading-none"><?= $pendientes_count ?></h3>
                            <p class="text-[10px] font-bold text-ibbs-muted uppercase tracking-wider mt-1">Tareas Pendientes</p>
                        </div>
                    </div>
                    <!-- Tarjeta Materias -->
                    <div class="bg-ibbs-paper p-5 rounded-[14px] border border-ibbs-border flex items-center gap-4 cursor-pointer hover:-translate-y-1 hover:shadow-lg transition-all group" onclick="document.querySelector('#sidebar nav button:nth-child(3)').click()">
                        <div class="w-12 h-12 rounded-xl bg-ibbs-blue/10 text-ibbs-blue flex items-center justify-center text-xl group-hover:bg-ibbs-blue group-hover:text-white transition-colors">
                            <i class="fas fa-book-open"></i>
                        </div>
                        <div>
                            <h3 class="text-3xl font-serif text-ibbs-ink leading-none"><?= count($materias) ?></h3>
                            <p class="text-[10px] font-bold text-ibbs-muted uppercase tracking-wider mt-1">Mis Materias</p>
                        </div>
                    </div>
                    <!-- Tarjeta Promedio -->
                    <div class="bg-ibbs-paper p-5 rounded-[14px] border border-ibbs-border flex items-center gap-4 cursor-pointer hover:-translate-y-1 hover:shadow-lg transition-all group" onclick="document.querySelector('#sidebar nav button:nth-child(6)').click()">
                        <div class="w-12 h-12 rounded-xl bg-ibbs-green/10 text-ibbs-green flex items-center justify-center text-xl group-hover:bg-ibbs-green group-hover:text-white transition-colors">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div>
                            <h3 class="text-3xl font-serif text-ibbs-ink leading-none"><?= $promedio ?></h3>
                            <p class="text-[10px] font-bold text-ibbs-muted uppercase tracking-wider mt-1">Promedio General</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- VISTA: MATERIAS -->
            <div id="view-materias" class="view-section hidden space-y-5">
                <div class="flex items-center justify-between pb-3 border-b border-ibbs-border">
                    <h2 class="text-2xl font-serif text-ibbs-ink">Mis Materias</h2>
                </div>

                <!-- AUTOINSCRIPCIÓN -->
                <div class="bg-ibbs-paper rounded-[14px] border border-ibbs-border p-5">
                    <h3 class="text-base font-bold text-ibbs-ink mb-1 flex items-center gap-2">
                        <i class="fas fa-user-check text-ibbs-lime2"></i> Autoinscripción
                    </h3>
                    <?php if (empty($alumno['regular'])): ?>
                    <p class="text-sm text-ibbs-muted">
                        Tu inscripción todavía no fue marcada como <strong>regular</strong> por la administración.
                        Una vez que lo esté, vas a poder inscribirte tú mismo(a) en las materias disponibles desde aquí.
                    </p>
                    <?php else: ?>
                        <p class="text-sm text-ibbs-muted mb-3">
                            Sos alumno(a) regular: podés inscribirte directamente. Una vez inscrito(a), solo la administración puede quitarte de la materia.
                        </p>
                        <?php if (empty($materias_disponibles)): ?>
                        <p class="text-sm text-ibbs-muted italic">No hay materias con inscripción abierta en este momento. La administración todavía no habilitó ninguna, o ya estás inscrito(a) en todas las disponibles.</p>
                        <?php else: ?>
                        <div class="bg-ibbs-cream border border-ibbs-border rounded-lg p-3 mb-3 text-xs text-ibbs-ink flex items-start gap-2">
                            <i class="fas fa-circle-info text-ibbs-blue mt-0.5"></i>
                            <div>Para inscribirte tenés que adjuntar la captura de tu pago móvil o transferencia a la institución. La administración la revisa antes de dejarte adentro de la materia.</div>
                        </div>
                        <div class="flex flex-col gap-3">
                            <select id="selAutoInsc" class="border border-ibbs-border rounded-lg px-3 py-2 text-sm bg-white">
                                <option value="">— Selecciona una materia —</option>
                                <?php foreach ($materias_disponibles as $md): ?>
                                <option value="<?= $md['id'] ?>"><?= htmlspecialchars($md['codigo'].' · '.$md['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="flex flex-col sm:flex-row gap-3">
                                <input type="file" id="fileComprobante" accept="image/png,image/jpeg,image/webp" class="flex-1 border border-ibbs-border rounded-lg px-3 py-2 text-xs bg-white file:mr-3 file:py-1 file:px-3 file:rounded-md file:border-0 file:bg-ibbs-ink file:text-white file:text-xs file:font-bold">
                                <button onclick="autoInscribirme()" id="btnAutoInsc" class="btn-ibbs px-5 py-2 rounded-lg text-sm font-bold whitespace-nowrap">Enviar solicitud</button>
                            </div>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
                    <?php foreach($materias as $m): ?>
                    <div class="bg-ibbs-paper rounded-[14px] border border-ibbs-border overflow-hidden hover:shadow-[0_4px_24px_rgba(0,0,0,0.06)] transition-shadow group flex flex-col">
                        <div class="h-28 bg-ibbs-ink flex items-center justify-center text-4xl text-ibbs-lime relative overflow-hidden">
                            <div class="absolute inset-0 bg-dots opacity-50 pointer-events-none"></div>
                            <i class="fas fa-laptop-code relative z-10 group-hover:scale-110 transition-transform duration-300"></i>
                        </div>
                        <div class="p-5 flex-1 flex flex-col">
                            <h3 class="text-lg font-bold text-ibbs-ink mb-1 leading-tight"><?= htmlspecialchars($m['nombre']) ?></h3>
                            <p class="text-sm text-ibbs-muted mb-5 flex items-center gap-2">
                                <i class="fas fa-chalkboard-teacher text-ibbs-lime2"></i> Prof. <?= htmlspecialchars($m['doc_nombre'] . ' ' . $m['doc_apellido']) ?>
                            </p>
                            <div class="flex gap-2 mt-auto mb-2">
                                <button onclick="document.querySelector('#sidebar nav button:nth-child(4)').click()" class="flex-1 bg-ibbs-cream text-ibbs-ink border border-ibbs-border py-2 rounded-lg text-xs font-bold hover:bg-ibbs-border transition-colors">Ver Tareas</button>
                                <button onclick="irAlChatMateria(<?= $m['id'] ?>)" class="flex-1 btn-ibbs py-2 rounded-lg text-xs font-bold text-center">Foro de Clase</button>
                            </div>
                            <div class="flex gap-2">
                                <a href="modulo_vivo.php?materia_id=<?= $m['id'] ?>" target="_blank" class="flex-1 bg-ibbs-cream text-ibbs-ink border border-ibbs-border py-2 rounded-lg text-xs font-bold hover:bg-ibbs-border transition-colors text-center">🔴 En Vivo</a>
                                <a href="modulo_grabaciones.php?materia_id=<?= $m['id'] ?>" target="_blank" class="flex-1 bg-ibbs-cream text-ibbs-ink border border-ibbs-border py-2 rounded-lg text-xs font-bold hover:bg-ibbs-border transition-colors text-center">🎬 Grabadas</a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php if(empty($materias)): ?>
                    <div class="col-span-full p-8 text-center bg-ibbs-paper rounded-[14px] border border-dashed border-ibbs-border">
                        <i class="fas fa-folder-open text-3xl text-ibbs-muted/50 mb-3 block"></i>
                        <p class="text-ibbs-muted text-sm">Aún no estás inscrito en ninguna materia.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- VISTA: TAREAS -->
            <div id="view-tareas" class="view-section hidden space-y-5">
                <div class="flex items-center justify-between pb-3 border-b border-ibbs-border">
                    <h2 class="text-2xl font-serif text-ibbs-ink">Tareas y Asignaciones</h2>
                </div>
                
                <div class="grid grid-cols-1 xl:grid-cols-2 gap-5">
                    <?php foreach($tareas as $t): ?>
                        <?php if(!$t['entrega_id']): ?>
                        <!-- Tarea Pendiente -->
                        <div class="bg-ibbs-paper p-5 rounded-[14px] border border-ibbs-border border-l-4 border-l-ibbs-amber flex flex-col justify-between hover:shadow-md transition-shadow">
                            <div>
                                <div class="flex justify-between items-start mb-3">
                                    <span class="text-[10px] font-bold px-2 py-1 rounded border border-ibbs-border bg-ibbs-cream text-ibbs-muted tracking-wider uppercase"><?= htmlspecialchars($t['materia_nombre']) ?></span>
                                    <span class="text-[10px] font-bold px-2 py-1 rounded bg-ibbs-amber/10 text-ibbs-amber tracking-wider uppercase flex items-center gap-1"><i class="fas fa-clock"></i> Pendiente</span>
                                </div>
                                <h3 class="text-lg font-serif font-bold text-ibbs-ink mb-2"><?= htmlspecialchars($t['titulo']) ?></h3>
                                <p class="text-sm text-ibbs-muted mb-5 line-clamp-3 leading-relaxed"><?= nl2br(htmlspecialchars($t['descripcion'])) ?></p>
                                
                                <div class="flex flex-col gap-2 mb-5">
                                    <p class="text-xs font-bold text-ibbs-amber flex items-center gap-2">
                                        <i class="far fa-calendar-alt"></i> Límite: <?= date('d M Y, h:i A', strtotime($t['fecha_limite'])) ?>
                                    </p>
                                    <?php if($t['archivo']): ?>
                                    <a href="uploads/tareas/<?= htmlspecialchars($t['archivo']) ?>" target="_blank" class="text-xs text-ibbs-ink hover:text-ibbs-lime2 font-bold flex items-center gap-1.5 inline-flex w-max transition-colors">
                                        <i class="fas fa-paperclip"></i> Descargar Adjunto
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <button onclick="openModalEntrega(<?= $t['id'] ?>, '<?= htmlspecialchars($t['titulo'], ENT_QUOTES) ?>')" class="w-full btn-ibbs py-2.5 rounded-lg flex items-center justify-center gap-2 text-sm">
                                <i class="fas fa-cloud-upload-alt"></i> Subir Entrega
                            </button>
                        </div>
                        <?php else: ?>
                        <!-- Tarea Entregada -->
                        <div class="bg-ibbs-paper p-5 rounded-[14px] border border-ibbs-border border-l-4 border-l-ibbs-green flex flex-col justify-between opacity-80 hover:opacity-100 transition-opacity">
                            <div>
                                <div class="flex justify-between items-start mb-3">
                                    <span class="text-[10px] font-bold px-2 py-1 rounded border border-ibbs-border bg-ibbs-cream text-ibbs-muted tracking-wider uppercase"><?= htmlspecialchars($t['materia_nombre']) ?></span>
                                    <span class="text-[10px] font-bold px-2 py-1 rounded bg-ibbs-green/10 text-ibbs-green tracking-wider uppercase flex items-center gap-1"><i class="fas fa-check-circle"></i> Entregada</span>
                                </div>
                                <h3 class="text-lg font-serif font-bold text-ibbs-ink mb-2"><?= htmlspecialchars($t['titulo']) ?></h3>
                                <p class="text-sm text-ibbs-muted mb-5 line-clamp-2"><?= htmlspecialchars($t['descripcion']) ?></p>
                            </div>
                            <div class="w-full bg-ibbs-cream text-ibbs-ink font-bold py-2.5 rounded-lg border border-ibbs-border flex justify-between px-5 items-center text-sm">
                                <span>Calificación:</span>
                                <span class="<?= $t['nota'] === null ? 'text-ibbs-muted' : ($t['nota'] >= 15 ? 'text-ibbs-green' : 'text-ibbs-red') ?>">
                                    <?= $t['nota'] !== null ? $t['nota'] . ' / 20' . ($t['nota'] >= 15 ? ' · Aprobado' : ' · Reprobado') : 'Aún sin calificar' ?>
                                </span>
                            </div>
                            <?php if($t['nota'] !== null && !empty($t['observacion'])): ?>
                            <div class="w-full mt-2 p-3 rounded-lg text-xs leading-relaxed <?= $t['nota'] >= 15 ? 'bg-ibbs-green/10 text-ibbs-ink' : 'bg-ibbs-amber/10 text-ibbs-ink' ?>">
                                <strong class="flex items-center gap-1.5 mb-1"><i class="fas <?= $t['nota'] >= 15 ? 'fa-comment-dots' : 'fa-circle-exclamation' ?>"></i> Mensaje del profesor:</strong>
                                <?= nl2br(htmlspecialchars($t['observacion'])) ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    
                    <?php if(empty($tareas)): ?>
                    <div class="col-span-full p-8 text-center bg-ibbs-paper rounded-[14px] border border-dashed border-ibbs-border">
                        <i class="fas fa-clipboard-check text-4xl text-ibbs-muted/40 mb-3 block"></i>
                        <p class="text-ibbs-muted text-sm">¡Genial! No tienes tareas asignadas por ahora.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- VISTA: CHAT/FORO -->
            <div id="view-chat" class="view-section hidden h-full max-h-[80vh]">
                <div class="flex h-full bg-ibbs-paper rounded-[14px] border border-ibbs-border overflow-hidden">
                    
                    <!-- Lista de Materias (Foros) -->
                    <div class="w-24 md:w-64 border-r border-ibbs-border flex flex-col bg-ibbs-cream flex-shrink-0">
                        <div class="p-4 border-b border-ibbs-border bg-ibbs-paper hidden md:block">
                            <h3 class="font-serif font-bold text-ibbs-ink text-sm">Foros Activos</h3>
                        </div>
                        <div class="flex-1 overflow-y-auto py-2">
                            <?php foreach($materias as $index => $m): ?>
                            <div class="materia-foro-<?= $m['id'] ?> p-3 md:px-4 md:py-3 border-b border-ibbs-border/50 cursor-pointer hover:bg-ibbs-paper flex items-center justify-center md:justify-start gap-3 transition-colors <?= $index === 0 ? 'bg-ibbs-paper border-l-4 border-l-ibbs-lime active-chat-tab' : 'border-l-4 border-l-transparent' ?>" onclick="selectChatSubject(<?= $m['id'] ?>, '<?= htmlspecialchars($m['nombre'], ENT_QUOTES) ?>', this)">
                                <div class="w-8 h-8 rounded-lg bg-ibbs-ink text-ibbs-lime flex items-center justify-center font-bold text-xs flex-shrink-0">
                                    <?= substr($m['nombre'], 0, 2) ?>
                                </div>
                                <h4 class="font-bold text-xs text-ibbs-ink hidden md:block truncate"><?= htmlspecialchars($m['nombre']) ?></h4>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Área de Mensajes -->
                    <div class="flex-1 flex flex-col relative bg-ibbs-cream">
                        <!-- Header del Chat -->
                        <div class="p-4 bg-ibbs-paper border-b border-ibbs-border flex justify-between items-center shadow-sm z-10">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-full bg-ibbs-lime/20 flex items-center justify-center text-ibbs-ink">
                                    <i class="fas fa-users text-sm"></i>
                                </div>
                                <div>
                                    <h3 id="chat-title" class="font-serif font-bold text-ibbs-ink text-sm"><?= !empty($materias) ? htmlspecialchars($materias[0]['nombre']) : 'Seleccione una materia' ?></h3>
                                    <span class="text-[10px] text-ibbs-green font-bold uppercase tracking-wider flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-ibbs-green"></span> En vivo</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Contenedor Mensajes -->
                        <div id="chat-messages-container" class="flex-1 overflow-y-auto p-4 md:p-6 space-y-4">
                            <!-- JS inyecta el contenido del chat aquí -->
                        </div>

                        <!-- Formulario de Chat -->
                        <div class="p-4 bg-ibbs-paper border-t border-ibbs-border">
                            <!-- Indicador de respuesta -->
                            <div id="reply-indicator" class="hidden text-[11px] text-ibbs-ink mb-2 bg-ibbs-cream px-3 py-1.5 rounded-md flex justify-between items-center border border-ibbs-border">
                                <span class="flex items-center gap-1.5"><i class="fas fa-reply text-ibbs-muted"></i> Respondiendo a <strong id="reply-to-name"></strong></span>
                                <button type="button" onclick="cancelarRespuesta()" class="text-ibbs-muted hover:text-ibbs-red transition-colors p-1"><i class="fas fa-times"></i></button>
                            </div>

                            <form onsubmit="sendChat(event)" class="flex items-end gap-2">
                                <input type="hidden" id="chat-reply-to-id" name="respuesta_a" value="">
                                <input type="hidden" id="chat-materia-id" name="materia_id" value="<?= !empty($materias) ? $materias[0]['id'] : 0 ?>">
                                
                                <div class="flex-1 bg-ibbs-cream rounded-xl border border-ibbs-border focus-within:border-ibbs-ink focus-within:ring-2 focus-within:ring-ibbs-ink/5 transition-all overflow-hidden flex items-center px-4">
                                    <input type="text" id="chat-input-text" name="mensaje" class="w-full bg-transparent border-none focus:ring-0 text-sm py-3 outline-none placeholder-ibbs-muted/70 text-ibbs-ink" placeholder="Escribe un mensaje en el foro..." autocomplete="off">
                                </div>
                                
                                <button type="submit" class="btn-ibbs rounded-xl w-11 h-11 flex items-center justify-center flex-shrink-0">
                                    <i class="fas fa-paper-plane"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- VISTA: NOTAS -->
            <div id="view-notas" class="view-section hidden space-y-5">
                <div class="flex items-center justify-between pb-3 border-b border-ibbs-border">
                    <h2 class="text-2xl font-serif text-ibbs-ink">Calificaciones Finales</h2>
                </div>
                
                <div class="bg-ibbs-paper rounded-[14px] border border-ibbs-border overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse text-sm">
                            <thead>
                                <tr class="bg-ibbs-cream text-ibbs-muted border-b border-ibbs-border">
                                    <th class="p-4 font-bold uppercase tracking-wider text-[11px]">Materia</th>
                                    <th class="p-4 font-bold uppercase tracking-wider text-[11px] hidden md:table-cell">Cierre</th>
                                    <th class="p-4 font-bold uppercase tracking-wider text-[11px] text-right">Nota Final</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-ibbs-border">
                                <?php foreach($notas as $n): ?>
                                <tr class="hover:bg-ibbs-cream/50 transition-colors">
                                    <td class="p-4 text-ibbs-ink font-bold">
                                        <div class="flex items-center gap-3">
                                            <div class="w-7 h-7 rounded bg-ibbs-ink/5 text-ibbs-ink flex items-center justify-center"><i class="fas fa-book text-xs"></i></div>
                                            <?= htmlspecialchars($n['nombre']) ?>
                                        </div>
                                    </td>
                                    <td class="p-4 text-ibbs-muted hidden md:table-cell"><?= date('d M Y', strtotime($n['nota_fecha'])) ?></td>
                                    <td class="p-4 text-right">
                                        <span class="inline-flex items-center justify-center px-3 py-1 rounded-full font-bold text-xs <?= $n['nota_final'] >= 15 ? 'bg-ibbs-green/10 text-ibbs-green' : 'bg-ibbs-red/10 text-ibbs-red' ?>">
                                            <?= $n['nota_final'] ?> / 20
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if(empty($notas)): ?>
                                <tr>
                                    <td colspan="3" class="p-10 text-center text-ibbs-muted">
                                        <i class="fas fa-clipboard-list text-3xl mb-2 text-ibbs-border block"></i>
                                        Aún no tienes calificaciones finales registradas.
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- VISTA: PLAN DE NOTAS -->
            <div id="view-plan-notas" class="view-section hidden space-y-5">
                <div class="flex items-center justify-between pb-3 border-b border-ibbs-border">
                    <h2 class="text-2xl font-serif text-ibbs-ink">Plan de Notas y Trabajos</h2>
                </div>
                <p class="text-sm text-ibbs-muted -mt-2">Todas las actividades de cada materia, con su fecha límite y cuánto vale — para que sepas qué te falta entregar. Las calificaciones las vas a ver en "Calificaciones" una vez que cierre el período.</p>

                <div class="space-y-3" id="planNotasAcordeon">
                    <?php foreach($materias as $m): ?>
                    <div class="bg-ibbs-paper rounded-[14px] border border-ibbs-border overflow-hidden">
                        <button type="button" onclick="togglePlanNotas(<?= $m['id'] ?>, this)" class="w-full flex items-center justify-between px-5 py-4 text-left font-bold text-ibbs-ink">
                            <span><?= htmlspecialchars(($m['codigo'] ?? '').' | '.$m['nombre']) ?></span>
                            <i class="fas fa-chevron-down text-ibbs-muted text-sm transition-transform"></i>
                        </button>
                        <div id="pnBody-<?= $m['id'] ?>" class="hidden px-5 pb-4">
                            <div class="text-center text-ibbs-muted text-sm py-4" id="pnLoad-<?= $m['id'] ?>"><span class="inline-block"><i class="fas fa-spinner fa-spin"></i></span></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php if(empty($materias)): ?>
                    <div class="p-8 text-center bg-ibbs-paper rounded-[14px] border border-dashed border-ibbs-border">
                        <p class="text-ibbs-muted text-sm">Todavía no estás inscrito en ninguna materia.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- VISTA: AULA VIRTUAL -->
            <div id="view-aula" class="view-section hidden space-y-5">
                <div class="flex items-center justify-between pb-3 border-b border-ibbs-border">
                    <h2 class="text-2xl font-serif text-ibbs-ink">Aula Virtual</h2>
                </div>
                <div class="bg-ibbs-ink rounded-[14px] p-8 text-white relative overflow-hidden text-center flex flex-col items-center justify-center min-h-[300px] shadow-lg">
                    <div class="absolute inset-0 bg-dots pointer-events-none opacity-50"></div>
                    <div class="w-24 h-24 bg-ibbs-lime text-ibbs-ink rounded-full flex items-center justify-center text-4xl mb-6 relative z-10 shadow-[0_0_25px_rgba(57,255,20,0.3)]">
                        <i class="fas fa-chalkboard-teacher"></i>
                    </div>
                    <h3 class="text-2xl md:text-3xl font-serif font-bold mb-4 relative z-10">Accede a tus clases interactivas</h3>
                    <p class="text-white/70 max-w-lg mb-8 relative z-10 text-sm md:text-base leading-relaxed">
                        Ingresa a la plataforma del Aula Virtual para participar en clases en vivo, consultar recursos didácticos, ver grabaciones y colaborar en tiempo real con docentes y compañeros.
                    </p>
                    <a href="modulo_aula.php" target="_blank" class="btn-ibbs px-8 py-3.5 rounded-xl font-bold flex items-center gap-3 relative z-10 hover:scale-105 transition-transform shadow-xl">
                        <i class="fas fa-external-link-alt"></i> Ingresar al Aula Virtual
                    </a>
                </div>
            </div>

            <!-- VISTA: CONSTANCIAS -->
            <div id="view-constancias" class="view-section hidden space-y-5">
                <div class="flex items-center justify-between pb-3 border-b border-ibbs-border">
                    <h2 class="text-2xl font-serif text-ibbs-ink">Trámites y Constancias</h2>
                </div>

                <div class="bg-ibbs-cream border border-ibbs-border rounded-[14px] p-5 text-sm text-ibbs-ink flex items-start gap-3">
                    <i class="fas fa-circle-info text-ibbs-blue mt-0.5"></i>
                    <div>Estas constancias son un trámite administrativo pago del instituto — no se descargan desde aquí. Acércate a la administración, realiza el pago correspondiente y te la entregarán impresa o en PDF.</div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Constancia de Estudio -->
                    <div class="bg-ibbs-paper p-8 rounded-[14px] border border-ibbs-border flex flex-col items-center text-center group">
                        <div class="w-20 h-20 rounded-2xl bg-ibbs-blue/10 text-ibbs-blue flex items-center justify-center text-3xl mb-5">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                        <h3 class="text-xl font-serif font-bold text-ibbs-ink mb-3">Constancia de Estudio</h3>
                        <p class="text-sm text-ibbs-muted mb-2 leading-relaxed">Documento oficial membretado que certifica tu inscripción y condición actual como alumno regular en nuestra institución.</p>
                        <p class="text-xs text-ibbs-muted mt-auto font-semibold uppercase tracking-wide"><i class="fas fa-lock mr-1"></i> Solicítala en administración</p>
                    </div>

                    <!-- Constancia de Notas -->
                    <div class="bg-ibbs-paper p-8 rounded-[14px] border border-ibbs-border flex flex-col items-center text-center group">
                        <div class="w-20 h-20 rounded-2xl bg-ibbs-green/10 text-ibbs-green flex items-center justify-center text-3xl mb-5">
                            <i class="fas fa-list-ol"></i>
                        </div>
                        <h3 class="text-xl font-serif font-bold text-ibbs-ink mb-3">Constancia de Notas</h3>
                        <p class="text-sm text-ibbs-muted mb-2 leading-relaxed">Reporte académico oficial con el desglose detallado de tus calificaciones finales aprobadas y tu promedio general.</p>
                        <p class="text-xs text-ibbs-muted mt-auto font-semibold uppercase tracking-wide"><i class="fas fa-lock mr-1"></i> Solicítala en administración</p>
                    </div>
                </div>
            </div>

            <!-- VISTA: BIBLIOTECA -->
            <div id="view-biblioteca" class="view-section hidden space-y-5">
                <div class="flex items-center justify-between pb-3 border-b border-ibbs-border">
                    <h2 class="text-2xl font-serif text-ibbs-ink">Biblioteca</h2>
                    <div class="flex gap-2">
                        <button id="btnBibCatalogo" onclick="mostrarBibTab('catalogo')" class="px-4 py-1.5 rounded-full text-xs font-bold bg-ibbs-ink text-white">Catálogo</button>
                        <button id="btnBibMios" onclick="mostrarBibTab('mios')" class="px-4 py-1.5 rounded-full text-xs font-bold bg-ibbs-cream text-ibbs-ink border border-ibbs-border">Mi Biblioteca</button>
                    </div>
                </div>

                <div id="bibTabCatalogo" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                    <p class="text-sm text-ibbs-muted italic col-span-full">Cargando catálogo…</p>
                </div>
                <div id="bibTabMios" class="hidden grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5"></div>
            </div>

            <!-- MODAL COMPRAR LIBRO -->
            <div id="modalComprarLibro" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
                <div class="bg-white rounded-2xl max-w-md w-full p-6 space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-serif font-bold text-ibbs-ink">Comprar libro</h3>
                        <button onclick="cerrarModalComprar()" class="text-ibbs-muted hover:text-ibbs-ink"><i class="fas fa-times"></i></button>
                    </div>
                    <p id="comprarLibroTitulo" class="text-sm font-bold"></p>
                    <div id="datosPagoBox" class="bg-ibbs-cream border border-ibbs-border rounded-lg p-3 text-xs text-ibbs-ink space-y-1"></div>
                    <div>
                        <label class="text-xs font-bold uppercase tracking-wide text-ibbs-muted">Captura del pago móvil o transferencia</label>
                        <input type="file" id="comprobanteLibroInput" accept="image/png,image/jpeg,image/webp" class="w-full mt-1 border border-ibbs-border rounded-lg px-3 py-2 text-xs bg-white file:mr-3 file:py-1 file:px-3 file:rounded-md file:border-0 file:bg-ibbs-ink file:text-white file:text-xs file:font-bold">
                    </div>
                    <input type="hidden" id="comprarLibroId">
                    <button onclick="confirmarCompraLibro()" id="btnConfirmarCompra" class="w-full btn-ibbs py-2.5 rounded-lg text-sm font-bold">Enviar solicitud de compra</button>
                </div>
            </div>

            <!-- VISTA: PERFIL -->
            <div id="view-perfil" class="view-section hidden space-y-5">
                <div class="flex items-center justify-between pb-3 border-b border-ibbs-border">
                    <h2 class="text-2xl font-serif text-ibbs-ink">Configuración de Perfil</h2>
                </div>

                <div class="bg-ibbs-paper rounded-[14px] border border-ibbs-border p-6 md:p-8">
                    <form action="actualizar_perfil.php" method="POST" enctype="multipart/form-data" class="max-w-2xl mx-auto space-y-6">
                        <input type="hidden" name="csrf_token" value="<?=htmlspecialchars(csrf_token())?>">

                        <!-- Foto de Perfil -->
                        <div class="flex flex-col items-center gap-4 mb-8">
                            <div class="relative group cursor-pointer">
                                <div class="w-32 h-32 rounded-full border-4 border-ibbs-cream overflow-hidden shadow-md bg-ibbs-ink flex items-center justify-center text-5xl font-serif text-ibbs-lime transition-transform group-hover:scale-105" id="avatar-preview-container">
                                    <?php if(!empty($alumno['foto'])): ?>
                                        <img src="<?= htmlspecialchars($alumno['foto']) ?>" alt="Foto" class="w-full h-full object-cover">
                                    <?php else: ?>
                                        <?= htmlspecialchars($inicial) ?>
                                    <?php endif; ?>
                                </div>
                                <label for="foto_upload" class="absolute inset-0 bg-ibbs-ink/70 rounded-full flex flex-col items-center justify-center text-white opacity-0 group-hover:opacity-100 transition-opacity cursor-pointer">
                                    <i class="fas fa-camera text-2xl mb-1"></i>
                                    <span class="text-[10px] font-bold uppercase tracking-wider">Cambiar</span>
                                </label>
                                <input type="file" id="foto_upload" name="foto" class="hidden" accept="image/*" onchange="previewAvatar(this)">
                            </div>
                            <div class="text-center">
                                <p class="text-sm font-bold text-ibbs-ink">Fotografía de perfil</p>
                                <p class="text-[10px] text-ibbs-muted uppercase tracking-wider mt-1">Formatos: JPG, PNG, GIF, WEBP. Max: 3MB</p>
                            </div>
                        </div>

                        <!-- Datos Personales -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-ibbs-muted mb-2">Nombres</label>
                                <input type="text" name="nombre" value="<?= htmlspecialchars($alumno['nombre'] ?? '') ?>" class="w-full bg-ibbs-cream border border-ibbs-border rounded-xl p-3 text-sm focus:bg-white focus:ring-2 focus:ring-ibbs-ink/10 focus:border-ibbs-ink outline-none transition-all" required>
                            </div>
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-ibbs-muted mb-2">Apellidos</label>
                                <input type="text" name="apellido" value="<?= htmlspecialchars($alumno['apellido'] ?? '') ?>" class="w-full bg-ibbs-cream border border-ibbs-border rounded-xl p-3 text-sm focus:bg-white focus:ring-2 focus:ring-ibbs-ink/10 focus:border-ibbs-ink outline-none transition-all" required>
                            </div>
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-ibbs-muted mb-2">Correo Electrónico</label>
                                <input type="email" name="correo" value="<?= htmlspecialchars($alumno['correo'] ?? '') ?>" class="w-full bg-ibbs-cream border border-ibbs-border rounded-xl p-3 text-sm focus:bg-white focus:ring-2 focus:ring-ibbs-ink/10 focus:border-ibbs-ink outline-none transition-all" placeholder="tucorreo@ejemplo.com">
                            </div>
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-ibbs-muted mb-2">Teléfono / Celular</label>
                                <input type="text" name="telefono" value="<?= htmlspecialchars($alumno['telefono'] ?? '') ?>" class="w-full bg-ibbs-cream border border-ibbs-border rounded-xl p-3 text-sm focus:bg-white focus:ring-2 focus:ring-ibbs-ink/10 focus:border-ibbs-ink outline-none transition-all" placeholder="+XX XXXXXXXX">
                            </div>
                        </div>

                        <div class="border-t border-ibbs-border pt-6 mt-8 flex justify-end">
                            <button type="submit" class="btn-ibbs px-6 py-3 rounded-xl font-bold flex items-center gap-2 shadow-lg">
                                <i class="fas fa-save"></i> Guardar Cambios
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </main>

    <!-- MODAL ENTREGA TAREA -->
    <div id="modal-entrega" class="fixed inset-0 bg-ibbs-ink/70 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4 opacity-0 transition-opacity duration-300">
        <div class="bg-ibbs-paper rounded-[16px] shadow-2xl w-full max-w-lg border border-ibbs-border overflow-hidden transform scale-95 transition-transform duration-300" id="modal-entrega-content">
            <!-- Header Modal -->
            <div class="px-6 py-4 border-b border-ibbs-border flex justify-between items-center bg-ibbs-cream">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-full bg-ibbs-lime text-ibbs-ink flex items-center justify-center">
                        <i class="fas fa-cloud-upload-alt text-sm"></i>
                    </div>
                    <h3 class="font-serif font-bold text-lg text-ibbs-ink" id="modal-tarea-titulo">Entregar Tarea</h3>
                </div>
                <button onclick="closeModalEntrega()" class="text-ibbs-muted hover:text-ibbs-ink hover:bg-ibbs-border rounded-lg w-7 h-7 flex items-center justify-center transition-colors">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <!-- Body Modal -->
            <form id="form-entrega" onsubmit="submitFormulario(event)" class="p-6 space-y-5" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?=htmlspecialchars(csrf_token())?>">
                <input type="hidden" name="tarea_id" id="modal-tarea-id">
                
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-ibbs-muted mb-2">Mensaje / Respuesta</label>
                    <textarea name="respuesta_texto" rows="4" class="w-full bg-ibbs-cream border border-ibbs-border rounded-xl p-3 text-sm focus:bg-white focus:ring-2 focus:ring-ibbs-ink/10 focus:border-ibbs-ink outline-none transition-all resize-none text-ibbs-ink placeholder-ibbs-muted/50" placeholder="Añade un comentario opcional para el docente..."></textarea>
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-ibbs-muted mb-2">Archivo Adjunto</label>
                    <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-ibbs-border border-dashed rounded-xl hover:border-ibbs-lime hover:bg-ibbs-lime/5 transition-all bg-ibbs-cream group">
                        <div class="space-y-2 text-center">
                            <i class="fas fa-file-upload text-3xl text-ibbs-muted/40 group-hover:text-ibbs-ink transition-colors mb-2"></i>
                            <div class="flex text-sm text-ibbs-ink justify-center">
                                <label for="file-upload" class="relative cursor-pointer rounded-md font-bold text-ibbs-ink hover:text-ibbs-lime2 focus-within:outline-none">
                                    <span>Seleccionar archivo</span>
                                    <input id="file-upload" name="archivo_adjunto" type="file" class="sr-only" onchange="updateFileName(this)">
                                </label>
                            </div>
                            <p class="text-[10px] text-ibbs-muted font-bold uppercase tracking-widest">ZIP, PDF, DOCX, JPG/PNG</p>
                        </div>
                    </div>
                    <div id="file-name-display" class="hidden mt-3 px-4 py-2 bg-ibbs-cream text-ibbs-ink rounded-lg text-sm font-bold flex items-center justify-between border border-ibbs-border">
                        <span class="flex items-center gap-2 truncate"><i class="fas fa-file-alt text-ibbs-muted"></i> <span id="file-name-text" class="truncate max-w-[250px]"></span></span>
                        <i class="fas fa-check-circle text-ibbs-green"></i>
                    </div>
                </div>

                <div class="pt-4 flex gap-3 justify-end border-t border-ibbs-border mt-6">
                    <button type="button" onclick="closeModalEntrega()" class="px-5 py-2.5 text-xs font-bold text-ibbs-muted bg-ibbs-paper border border-ibbs-border rounded-lg hover:bg-ibbs-cream transition-colors">Cancelar</button>
                    <button type="submit" class="btn-ibbs px-5 py-2.5 rounded-lg text-xs font-bold flex items-center gap-2">
                        <i class="fas fa-paper-plane"></i> Confirmar Entrega
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- WebSocket en vivo (opcional — no-op si no hay VPS configurado) -->
    <script src="assets/ibbs-realtime.js"></script>
    <script>
        // Mensaje de resultado tras actualizar_perfil.php (redirect con
        // ?msg=...) — antes quedaba mudo, el alumno no sabía si guardó.
        (function () {
            const params = new URLSearchParams(window.location.search);
            const msg = params.get('msg');
            window._ibbsTuvoMsg = !!msg;
            if (!msg) return;
            const textos = {
                perfil_actualizado: '✓ Perfil actualizado correctamente.',
                correo_duplicado: 'Ese correo ya está en uso por otro alumno.',
                faltan_campos: 'Completá nombre y apellido.',
                csrf_invalido: 'La sesión del formulario expiró — probá guardar de nuevo.',
                error: 'Ocurrió un error al actualizar el perfil.',
            };
            if (textos[msg]) { msg === 'perfil_actualizado' ? Ibbs.success(textos[msg]) : Ibbs.error(textos[msg]); }
            if (msg.startsWith('perfil_') || msg === 'correo_duplicado' || msg === 'faltan_campos') {
                const btn = document.getElementById('navBtnPerfil');
                if (btn) switchView('perfil', btn);
            }
            params.delete('msg');
            const nuevaUrl = window.location.pathname + (params.toString() ? '?' + params.toString() : '');
            window.history.replaceState({}, '', nuevaUrl);
        })();

        // ── Festejo breve (confeti) al entregar una tarea o inscribirte
        // en una materia — puro CSS + divs generados acá, sin librerías.
        function ibbsConfetti() {
            const colores = ['#39ff14', '#2ecc10', '#fdfaf4', '#2563eb', '#f59e0b'];
            const total = 26;
            for (let i = 0; i < total; i++) {
                const piece = document.createElement('div');
                piece.className = 'ibbs-confetti-piece';
                const size = 6 + Math.random() * 6;
                piece.style.width = size + 'px';
                piece.style.height = (size * 0.4) + 'px';
                piece.style.left = Math.random() * 100 + 'vw';
                piece.style.background = colores[i % colores.length];
                piece.style.animationDuration = (1.8 + Math.random() * 1.2) + 's';
                piece.style.transform = `rotate(${Math.random() * 360}deg)`;
                document.body.appendChild(piece);
                piece.addEventListener('animationend', () => piece.remove());
            }
        }

        // ── PLAN DE NOTAS — lista de actividades por materia, SIN
        // ninguna nota (eso solo lo ve el profesor en su propio Plan de
        // Notas — ver api/plan_notas.php). Acá solo importa saber qué
        // hay que entregar, para cuándo, y si ya se entregó o se venció.
        const _pnCargadas = {};
        function hPNAl(s) { const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML; }

        function cargarPlanNotasAlumno() { /* las materias ya están en el acordeón; cada una carga sus actividades al abrirse */ }

        function togglePlanNotas(materiaId, btn) {
            const body = document.getElementById('pnBody-' + materiaId);
            const icon = btn.querySelector('i');
            const abrir = body.classList.contains('hidden');
            body.classList.toggle('hidden');
            icon.style.transform = abrir ? 'rotate(180deg)' : 'rotate(0deg)';
            if (abrir && !_pnCargadas[materiaId]) {
                _pnCargadas[materiaId] = true;
                cargarActividadesMateria(materiaId);
            }
        }

        const ESTADO_LBL = {
            entregada: { txt: 'Entregada', cls: 'bg-ibbs-green/10 text-ibbs-green', icon: 'fa-check' },
            entregada_tarde: { txt: 'Entregada tarde', cls: 'bg-ibbs-amber/10 text-ibbs-amber', icon: 'fa-clock' },
            pendiente: { txt: 'Pendiente', cls: 'bg-ibbs-amber/10 text-ibbs-amber', icon: 'fa-hourglass-half' },
            vencida: { txt: 'Vencida — ya no se puede entregar', cls: 'bg-ibbs-red/10 text-ibbs-red', icon: 'fa-triangle-exclamation' },
        };

        async function cargarActividadesMateria(materiaId) {
            const body = document.getElementById('pnBody-' + materiaId);
            const fd = new FormData(); fd.append('accion', 'alumno'); fd.append('materia_id', materiaId);
            const m = document.querySelector('meta[name="csrf-token"]'); fd.append('csrf_token', m ? m.content : '');
            let d;
            try {
                const r = await fetch('api/plan_notas.php', { method: 'POST', body: fd });
                d = await r.json();
            } catch (e) { body.innerHTML = '<p class="text-sm text-ibbs-red">Error de conexión.</p>'; return; }
            if (!d.ok) { body.innerHTML = `<p class="text-sm text-ibbs-red">${hPNAl(d.msg || 'Error al cargar.')}</p>`; return; }

            const actividades = d.data.actividades;
            if (!actividades.length) { body.innerHTML = '<p class="text-sm text-ibbs-muted py-2">Todavía no hay actividades cargadas en esta materia.</p>'; return; }

            body.innerHTML = `<div class="overflow-x-auto"><table class="w-full text-left text-xs border-collapse">
                <thead><tr class="text-ibbs-muted border-b border-ibbs-border">
                    <th class="py-2 pr-2 font-bold uppercase tracking-wider">Actividad</th>
                    <th class="py-2 px-2 font-bold uppercase tracking-wider">Fecha límite</th>
                    <th class="py-2 px-2 font-bold uppercase tracking-wider text-center">%</th>
                    <th class="py-2 pl-2 font-bold uppercase tracking-wider text-right">Estado</th>
                </tr></thead>
                <tbody class="divide-y divide-ibbs-border">
                ${actividades.map(a => {
                    const e = ESTADO_LBL[a.estado] || ESTADO_LBL.pendiente;
                    const fecha = new Date(a.fecha_limite).toLocaleString('es-VE', {day:'2-digit', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit'});
                    return `<tr>
                        <td class="py-2.5 pr-2 font-bold text-ibbs-ink">${hPNAl(a.titulo)}</td>
                        <td class="py-2.5 px-2 text-ibbs-muted">${fecha}</td>
                        <td class="py-2.5 px-2 text-center text-ibbs-muted">${a.porcentaje !== null ? parseFloat(a.porcentaje) + '%' : '—'}</td>
                        <td class="py-2.5 pl-2 text-right"><span class="inline-flex items-center gap-1.5 px-2 py-1 rounded-full font-bold ${e.cls}"><i class="fas ${e.icon}"></i> ${e.txt}</span></td>
                    </tr>`;
                }).join('')}
                </tbody></table></div>`;
        }

        // Lógica de vistas y modal conservada pero con colores ajustados
        function switchView(viewId, btnElement = null) {
            document.querySelectorAll('.view-section').forEach(el => {
                el.classList.add('hidden');
                el.classList.remove('block');
            });
            document.getElementById('view-' + viewId).classList.remove('hidden');
            document.getElementById('view-' + viewId).classList.add('block');
            
            if(btnElement) {
                document.querySelectorAll('.nav-btn').forEach(btn => {
                    btn.classList.remove('active', 'text-white');
                    btn.classList.add('text-white/60');
                });
                btnElement.classList.add('active', 'text-white');
                btnElement.classList.remove('text-white/60');
            }
            if(window.innerWidth < 768) {
                document.getElementById('sidebar').classList.remove('sidebar-open');
                document.getElementById('mobile-overlay').classList.add('hidden');
            }
        }

        function irAlChatMateria(materiaId) {
            switchView('chat');
            const chatTab = document.querySelector('.materia-foro-' + materiaId);
            if(chatTab) chatTab.click();
        }

        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('mobile-overlay');
            sidebar.classList.toggle('sidebar-open');
            sidebar.classList.toggle('-translate-x-full');
            overlay.classList.toggle('hidden');
        }

        function openModalEntrega(id, titulo) {
            document.getElementById('modal-tarea-id').value = id;
            document.getElementById('modal-tarea-titulo').innerText = 'Entregar: ' + titulo;
            const modal = document.getElementById('modal-entrega');
            const content = document.getElementById('modal-entrega-content');
            
            document.getElementById('form-entrega').reset();
            document.getElementById('file-name-display').classList.add('hidden');
            
            modal.classList.remove('hidden');
            setTimeout(() => {
                modal.classList.remove('opacity-0');
                content.classList.remove('scale-95');
            }, 10);
        }

        function closeModalEntrega() {
            const modal = document.getElementById('modal-entrega');
            const content = document.getElementById('modal-entrega-content');
            modal.classList.add('opacity-0');
            content.classList.add('scale-95');
            setTimeout(() => { modal.classList.add('hidden'); }, 300);
        }

        function updateFileName(input) {
            const display = document.getElementById('file-name-display');
            const text = document.getElementById('file-name-text');
            if (input.files.length > 0) {
                text.innerText = input.files[0].name;
                display.classList.remove('hidden');
            } else {
                display.classList.add('hidden');
            }
        }

        function submitFormulario(e) {
            e.preventDefault(); 
            const form = e.target;
            const formData = new FormData(form);
            const _csrfEnt = document.querySelector('meta[name="csrf-token"]');
            formData.append('csrf_token', _csrfEnt ? _csrfEnt.content : '');
            const btn = form.querySelector('button[type="submit"]');

            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Procesando...';

            fetch('procesar_entrega.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if(data.ok) {
                    closeModalEntrega();
                    Ibbs.success('Entrega enviada correctamente.');
                    ibbsConfetti();
                    setTimeout(() => location.reload(), 1200);
                } else { Ibbs.error(data.msg); }
            })
            .catch(err => {
                Ibbs.error("Hubo un error al enviar la tarea.");
                console.error(err);
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-paper-plane mr-2"></i> Confirmar Entrega';
            });
        }

        // Preview para la subida de foto de perfil
        function previewAvatar(input) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    const container = document.getElementById('avatar-preview-container');
                    container.innerHTML = '<img src="' + e.target.result + '" class="w-full h-full object-cover">';
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        // CHAT LOGIC (Ajustado estéticamente)
        let materiaActivaChatId = <?= !empty($materias) ? $materias[0]['id'] : 0 ?>;
        let ultimoIdMensaje = 0;
        let chatInterval = null;
        const MI_USUARIO_ID = <?= (int)$user_id ?>;
        function hChat(s) { const d = document.createElement('div'); d.textContent = String(s ?? ''); return d.innerHTML; }

        async function autoInscribirme() {
            const sel = document.getElementById('selAutoInsc');
            const mid = sel ? sel.value : '';
            if (!mid) { Ibbs.warn('Selecciona una materia primero.'); return; }
            const fileInput = document.getElementById('fileComprobante');
            const file = fileInput && fileInput.files[0];
            if (!file) { Ibbs.warn('Adjuntá la captura de tu pago móvil o transferencia para poder inscribirte.'); return; }
            const btn = document.getElementById('btnAutoInsc');
            btn.disabled = true; btn.textContent = 'Enviando…';
            try {
                const _csrfMeta = document.querySelector('meta[name="csrf-token"]');
                const fd = new FormData();
                fd.append('action', 'crear');
                fd.append('materia_id', mid);
                fd.append('comprobante', file);
                fd.append('csrf_token', _csrfMeta ? _csrfMeta.content : '');
                const r = await fetch('api/materia_solicitud.php', { method: 'POST', body: fd });
                const d = await r.json();
                if (d.ok) {
                    ibbsConfetti();
                    await Ibbs.success(d.msg);
                    location.reload();
                } else {
                    Ibbs.error(d.msg || 'No se pudo enviar la solicitud.');
                    btn.disabled = false; btn.textContent = 'Enviar solicitud';
                }
            } catch (e) { console.error(e); Ibbs.error('Error de conexión.'); btn.disabled = false; btn.textContent = 'Enviar solicitud'; }
        }

        // ── BIBLIOTECA ────────────────────────────────────────────
        let _bibCatalogo = [];
        let _bibDatosPago = null;
        function hBib(s) { const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML; }
        function mostrarBibTab(tab) {
            document.getElementById('bibTabCatalogo').classList.toggle('hidden', tab !== 'catalogo');
            document.getElementById('bibTabMios').classList.toggle('hidden', tab !== 'mios');
            document.getElementById('btnBibCatalogo').className = 'px-4 py-1.5 rounded-full text-xs font-bold ' + (tab === 'catalogo' ? 'bg-ibbs-ink text-white' : 'bg-ibbs-cream text-ibbs-ink border border-ibbs-border');
            document.getElementById('btnBibMios').className = 'px-4 py-1.5 rounded-full text-xs font-bold ' + (tab === 'mios' ? 'bg-ibbs-ink text-white' : 'bg-ibbs-cream text-ibbs-ink border border-ibbs-border');
            if (tab === 'mios') cargarMisLibros();
        }
        async function loadBiblioteca() {
            const cont = document.getElementById('bibTabCatalogo');
            const r = await fetch('api/biblioteca.php', { method: 'POST', body: (() => { const fd = new FormData(); fd.append('action', 'libro_list'); const m = document.querySelector('meta[name="csrf-token"]'); fd.append('csrf_token', m ? m.content : ''); return fd; })() });
            const d = await r.json();
            if (!d.ok) { cont.innerHTML = `<p class="text-sm text-red-600 col-span-full">${hBib(d.msg||'Error al cargar la biblioteca.')}</p>`; return; }
            _bibCatalogo = d.data;
            if (!d.data.length) { cont.innerHTML = '<p class="text-sm text-ibbs-muted italic col-span-full">Todavía no hay libros cargados.</p>'; return; }
            cont.innerHTML = d.data.map(l => {
                const precioTxt = parseFloat(l.precio) > 0 ? '$' + parseFloat(l.precio).toFixed(2) : 'Gratis';
                let accion = '';
                if (l.estado_compra === 'activado') accion = `<a href="api/biblioteca.php?action=descargar&id=${l.id}" class="w-full btn-ibbs py-2 rounded-lg text-xs font-bold text-center block mt-3"><i class="fas fa-download mr-1"></i> Descargar</a>`;
                else if (l.estado_compra === 'pendiente') accion = `<p class="text-xs text-center mt-3 font-bold text-amber-600"><i class="fas fa-clock mr-1"></i> Solicitud en revisión</p>`;
                else accion = `<button onclick='abrirModalComprar(${l.id})' class="w-full btn-ibbs py-2 rounded-lg text-xs font-bold mt-3">${parseFloat(l.precio) > 0 ? 'Comprar' : 'Obtener gratis'}</button>`;
                return `<div class="bg-ibbs-paper rounded-[14px] border border-ibbs-border overflow-hidden flex flex-col">
                    <div class="h-40 bg-ibbs-ink flex items-center justify-center overflow-hidden">
                        ${l.portada ? `<img src="${l.portada}" class="w-full h-full object-cover">` : `<i class="fas fa-book text-4xl text-ibbs-lime"></i>`}
                    </div>
                    <div class="p-4 flex-1 flex flex-col">
                        <h3 class="font-bold text-ibbs-ink leading-tight mb-1">${hBib(l.titulo)}</h3>
                        <p class="text-xs text-ibbs-muted mb-2">${hBib(l.autor || 'Autor desconocido')}</p>
                        <p class="text-xs text-ibbs-muted mb-3 flex-1">${hBib((l.descripcion || '').substring(0, 90))}</p>
                        <p class="text-sm font-bold text-ibbs-ink">${precioTxt}</p>
                        ${accion}
                    </div>
                </div>`;
            }).join('');
        }
        async function cargarMisLibros() {
            const cont = document.getElementById('bibTabMios');
            cont.innerHTML = '<p class="text-sm text-ibbs-muted italic col-span-full">Cargando…</p>';
            const r = await fetch('api/biblioteca.php', { method: 'POST', body: (() => { const fd = new FormData(); fd.append('action', 'mis_libros'); const m = document.querySelector('meta[name="csrf-token"]'); fd.append('csrf_token', m ? m.content : ''); return fd; })() });
            const d = await r.json();
            if (!d.ok || !d.data.length) { cont.innerHTML = '<p class="text-sm text-ibbs-muted italic col-span-full">Todavía no tenés libros activados en tu biblioteca.</p>'; return; }
            cont.innerHTML = d.data.map(l => `<div class="bg-ibbs-paper rounded-[14px] border border-ibbs-border overflow-hidden flex flex-col">
                <div class="h-40 bg-ibbs-ink flex items-center justify-center overflow-hidden">
                    ${l.portada ? `<img src="${l.portada}" class="w-full h-full object-cover">` : `<i class="fas fa-book text-4xl text-ibbs-lime"></i>`}
                </div>
                <div class="p-4 flex-1 flex flex-col">
                    <h3 class="font-bold text-ibbs-ink leading-tight mb-1">${hBib(l.titulo)}</h3>
                    <p class="text-xs text-ibbs-muted mb-3">${hBib(l.autor || '')}</p>
                    <a href="api/biblioteca.php?action=descargar&id=${l.id}" class="w-full btn-ibbs py-2 rounded-lg text-xs font-bold text-center block mt-auto"><i class="fas fa-download mr-1"></i> Descargar</a>
                </div>
            </div>`).join('');
        }
        async function abrirModalComprar(id) {
            const libro = _bibCatalogo.find(l => l.id == id);
            if (!libro) return;
            document.getElementById('comprarLibroId').value = id;
            document.getElementById('comprarLibroTitulo').textContent = libro.titulo + (parseFloat(libro.precio) > 0 ? ' — $' + parseFloat(libro.precio).toFixed(2) : ' — Gratis');
            document.getElementById('comprobanteLibroInput').closest('div').style.display = parseFloat(libro.precio) > 0 ? '' : 'none';
            document.getElementById('btnConfirmarCompra').textContent = parseFloat(libro.precio) > 0 ? 'Enviar solicitud de compra' : 'Obtener libro gratis';

            if (!_bibDatosPago) {
                const r = await fetch('api/biblioteca.php', { method: 'POST', body: (() => { const fd = new FormData(); fd.append('action', 'datos_pago_get'); const m = document.querySelector('meta[name="csrf-token"]'); fd.append('csrf_token', m ? m.content : ''); return fd; })() });
                const d = await r.json();
                _bibDatosPago = d.ok ? d.data : {};
            }
            const p = _bibDatosPago || {};
            const box = document.getElementById('datosPagoBox');
            if (parseFloat(libro.precio) <= 0) {
                box.innerHTML = 'Este libro es gratuito — se activa apenas confirmes.';
            } else if (!p.titular && !p.pago_movil_telefono && !p.cuenta) {
                box.innerHTML = 'La administración todavía no cargó los datos de pago. Consultá directamente en el instituto cómo realizar el pago antes de enviar tu solicitud.';
            } else {
                box.innerHTML = [
                    p.titular ? `<div><strong>Titular:</strong> ${hBib(p.titular)}</div>` : '',
                    p.banco ? `<div><strong>Banco:</strong> ${hBib(p.banco)}</div>` : '',
                    p.cuenta ? `<div><strong>Cuenta:</strong> ${hBib(p.cuenta)}</div>` : '',
                    p.cedula_rif ? `<div><strong>CI/RIF:</strong> ${hBib(p.cedula_rif)}</div>` : '',
                    p.pago_movil_telefono ? `<div><strong>Pago Móvil:</strong> ${hBib(p.pago_movil_telefono)}</div>` : '',
                    p.instrucciones ? `<div class="mt-1 italic">${hBib(p.instrucciones)}</div>` : '',
                ].join('');
            }
            document.getElementById('modalComprarLibro').classList.remove('hidden');
        }
        function cerrarModalComprar() {
            document.getElementById('modalComprarLibro').classList.add('hidden');
            document.getElementById('comprobanteLibroInput').value = '';
        }
        async function confirmarCompraLibro() {
            const id = document.getElementById('comprarLibroId').value;
            const libro = _bibCatalogo.find(l => l.id == id);
            const file = document.getElementById('comprobanteLibroInput').files[0];
            if (libro && parseFloat(libro.precio) > 0 && !file) { Ibbs.warn('Adjuntá la captura de tu pago para poder enviar la solicitud.'); return; }
            const btn = document.getElementById('btnConfirmarCompra');
            btn.disabled = true; btn.textContent = 'Enviando…';
            try {
                const fd = new FormData();
                fd.append('action', 'compra_crear');
                fd.append('libro_id', id);
                if (file) fd.append('comprobante', file);
                const m = document.querySelector('meta[name="csrf-token"]');
                fd.append('csrf_token', m ? m.content : '');
                const r = await fetch('api/biblioteca.php', { method: 'POST', body: fd });
                const d = await r.json();
                if (d.ok) {
                    cerrarModalComprar();
                    await Ibbs.success(d.msg || 'Listo.');
                    loadBiblioteca();
                } else {
                    Ibbs.error(d.msg || 'No se pudo procesar la solicitud.');
                }
            } catch (e) { console.error(e); Ibbs.error('Error de conexión.'); }
            btn.disabled = false; btn.textContent = 'Enviar solicitud de compra';
        }

        <?php if (empty($materias)): ?>
        // Primer ingreso sin materias: lo primero que ve el alumno es la
        // pantalla para inscribirse, no el dashboard vacío — salvo que
        // haya vuelto de guardar su perfil (ese mensaje ya decidió la vista).
        if (!window._ibbsTuvoMsg) {
            switchView('materias', document.getElementById('navBtnMaterias'));
        }
        <?php endif; ?>

        function prepararRespuesta(nombreUsuario, idMensaje) {
            document.getElementById('chat-reply-to-id').value = idMensaje;
            document.getElementById('reply-to-name').innerText = nombreUsuario;
            document.getElementById('reply-indicator').classList.remove('hidden');
            document.getElementById('chat-input-text').focus();
        }

        function cancelarRespuesta() {
            document.getElementById('chat-reply-to-id').value = '';
            document.getElementById('reply-indicator').classList.add('hidden');
        }

        function selectChatSubject(materiaId, nombreMateria, element) {
            materiaActivaChatId = materiaId;
            document.getElementById('chat-title').innerText = nombreMateria;
            document.getElementById('chat-materia-id').value = materiaId;
            cancelarRespuesta();
            
            document.querySelectorAll('#view-chat .w-24 > div > div, #view-chat .md\\:w-64 > div > div').forEach(el => {
                el.classList.remove('bg-ibbs-paper', 'border-l-ibbs-lime', 'active-chat-tab');
                el.classList.add('border-l-transparent');
            });
            if(element) {
                element.classList.add('bg-ibbs-paper', 'border-l-ibbs-lime', 'active-chat-tab');
                element.classList.remove('border-l-transparent');
            }

            const chatBox = document.getElementById('chat-messages-container');
            chatBox.innerHTML = `
                <div class="flex justify-center my-10">
                    <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-ibbs-ink"></div>
                </div>
            `;
            
            ultimoIdMensaje = 0;
            cargarMensajesForo();
        }

        function roleBadgeChat(rol) {
            if (rol === 'profesor') return '<span class="text-[9px] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded bg-ibbs-blue/10 text-ibbs-blue ml-1">Profesor</span>';
            if (rol === 'admin' || rol === 'superadmin') return '<span class="text-[9px] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded bg-ibbs-red/10 text-ibbs-red ml-1">Admin</span>';
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
                    chatBox.innerHTML = `
                        <div class="flex flex-col items-center justify-center h-full text-ibbs-muted space-y-2 opacity-50">
                            <i class="far fa-comments text-4xl"></i>
                            <p class="text-xs font-bold uppercase tracking-wider">No hay mensajes aún.</p>
                        </div>
                    `;
                    ultimoIdMensaje = 0;
                    return;
                }

                chatBox.innerHTML = '';
                mensajes.forEach(msg => {
                    let isMe = msg.usuario_id === MI_USUARIO_ID;
                    let replyHtml = '';
                    if (msg.respuesta_a_nombre) {
                        let replyBg = isMe ? 'bg-black/20 border-ibbs-lime/50 text-white/80' : 'bg-ibbs-cream border-ibbs-border text-ibbs-muted';
                        replyHtml = `
                            <div class="text-[10px] ${replyBg} px-2.5 py-1 rounded mb-2 border-l-2 flex items-center gap-1.5">
                                <i class="fas fa-reply text-[9px]"></i> a ${hChat(msg.respuesta_a_nombre)}
                            </div>
                        `;
                    }
                    const hora = new Date(msg.fecha).toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'});
                    const delBtn = msg.puede_borrar
                        ? `<button type="button" onclick="borrarMensajeForo(${msg.id})" class="text-[10px] text-ibbs-red font-bold hover:underline opacity-0 group-hover:opacity-100 transition-opacity ml-3">Borrar</button>`
                        : '';

                    let html = '';
                    if (isMe) {
                        html = `
                        <div class="flex flex-col items-end mt-3 animate-fade-in w-full">
                            <span class="text-[10px] text-ibbs-muted mr-1 mb-1 font-bold">Tú</span>
                            <div class="chat-bubble-me max-w-[85%] md:max-w-[70%] rounded-2xl p-3 shadow-sm relative group">
                                ${replyHtml}
                                <p class="text-sm leading-relaxed">${hChat(msg.mensaje)}</p>
                                <div class="flex justify-end items-center mt-1.5 gap-2">
                                    <span class="text-[9px] text-ibbs-lime/70">${hora}</span>
                                    <i class="fas fa-check-double text-[9px] text-ibbs-lime"></i>
                                    ${delBtn}
                                </div>
                            </div>
                        </div>`;
                    } else {
                        html = `
                        <div class="flex flex-col items-start mt-3 animate-fade-in w-full">
                            <span class="text-[10px] text-ibbs-muted ml-1 mb-1 font-bold">${hChat(msg.usuario_nombre)} ${roleBadgeChat(msg.rol)}</span>
                            <div class="chat-bubble-other max-w-[85%] md:max-w-[70%] rounded-2xl p-3 shadow-sm relative group">
                                ${replyHtml}
                                <p class="text-sm leading-relaxed text-ibbs-ink">${hChat(msg.mensaje)}</p>
                                <div class="flex justify-between items-center mt-1.5 gap-4">
                                    <span class="text-[9px] text-ibbs-muted">${hora}</span>
                                    <span>
                                        <button type="button" onclick="prepararRespuesta('${hChat(msg.usuario_nombre)}', ${msg.id})" class="text-[10px] text-ibbs-ink font-bold hover:underline opacity-0 group-hover:opacity-100 transition-opacity">
                                            Responder
                                        </button>${delBtn}
                                    </span>
                                </div>
                            </div>
                        </div>`;
                    }
                    chatBox.innerHTML += html;
                    ultimoIdMensaje = Math.max(ultimoIdMensaje, msg.id);
                });
                chatBox.scrollTop = chatBox.scrollHeight;
            }).catch(err => console.error("Error:", err));
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
            input.value = '';
            cancelarRespuesta();
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
            if(!viewChat.classList.contains('hidden') && materiaActivaChatId > 0) {
                cargarMensajesForo();
            }
        }, 5000);

        // Con WebSocket (VPS configurado) el chat se refresca casi al
        // instante en vez de esperar hasta 5s — el setInterval de
        // arriba queda como red de seguridad si el WebSocket se cae.
        if (window.IbbsRT && window.IbbsRT.hasWs) {
            window.IbbsRT.on('foro_mensaje', (data) => {
                if (data && parseInt(data.materia_id) === parseInt(materiaActivaChatId)) cargarMensajesForo();
            });
        }

        // ── Tutorial guiado (manual intuitivo del alumno) ──────────
        window.IBBS_TOUR_ALUMNO = {
            storageKey: 'ibbs_tour_alumno_v2',
            steps: [
                { selector: '[data-tour="nav-dashboard"]', title: '¡Bienvenido/a a IBBS!', text: 'Este es tu Inicio: un resumen rápido de tus materias, tus tareas pendientes y los avisos importantes de la institución, cada vez que entrás. Vamos a recorrer juntos todo lo que podés hacer en el sistema — cerrá esta tarjeta con la "×" para pasar al siguiente punto.' },
                { selector: '[data-tour="nav-aula"]', title: 'Aula Virtual', text: 'Acá tus profesores suben el material de cada materia: documentos, presentaciones, videos y clases grabadas o en vivo. Podés descargarlo o verlo cuantas veces necesites para repasar.' },
                { selector: '[data-tour="nav-materias"]', title: 'Mis Materias', text: 'Mirá el detalle de cada materia en la que estás inscrito/a este período: el profesor a cargo, el horario y el estado de tu inscripción.' },
                { selector: '[data-tour="nav-tareas"]', title: 'Tareas', text: 'Subí tus trabajos y tareas antes de la fecha límite adjuntando el archivo pedido. El número en rojo te avisa cuántas tenés pendientes por entregar; una vez el profesor la revise, la nota aparece junto a la tarea.' },
                { selector: '[data-tour="nav-chat"]', title: 'Foros de Clase', text: 'Espacio de preguntas y respuestas por cada materia: escribile a tu profesor o a tus compañeros si tenés dudas sobre un tema de clase.' },
                { selector: '[data-tour="nav-notas"]', title: 'Calificaciones', text: 'Consultá tus notas por materia y por corte apenas el profesor las publique, sin tener que esperar el boletín oficial.' },
                { selector: '[data-tour="nav-plan-notas"]', title: 'Plan de Notas', text: 'Mirá todas las actividades de cada materia, su fecha límite y cuánto vale, para no perderte de nada — las notas en sí las ves en "Calificaciones".' },
                { selector: '[data-tour="nav-constancias"]', title: 'Constancias', text: 'Solicitá tus constancias de estudio, notas o récord académico, adjuntando el comprobante de pago cuando corresponda. Desde acá seguís el estado de tu trámite y descargás el documento cuando esté listo.' },
                { selector: '[data-tour="nav-biblioteca"]', title: 'Biblioteca', text: 'Explorá los libros que tus profesores fueron subiendo: los gratuitos se descargan directo, y en los de pago te vamos a pedir tu comprobante antes de darte acceso.' },
                { selector: '[data-tour="nav-perfil"]', title: 'Mi Perfil', text: 'Actualizá tu foto, tus datos personales y tu contraseña. Al escribir una contraseña en cualquier parte del sistema vas a ver un ícono de ojo al lado — tocalo si querés revisar lo que escribiste antes de guardar.' },
                { selector: '[data-tour="nav-replay"]', title: '¿Necesitás repasar esto?', text: 'Cuando quieras volver a ver este recorrido completo, tocá acá. ¡Éxitos en tus estudios!' }
            ]
        };
        document.addEventListener('DOMContentLoaded', function () {
            IbbsTour.start(Object.assign({ auto: true }, window.IBBS_TOUR_ALUMNO));
        });
    </script>
</body>
</html>