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
    SUM(CASE WHEN nota_final >= 10 THEN 1 ELSE 0 END) as aprobados,
    SUM(CASE WHEN nota_final < 10 THEN 1 ELSE 0 END) as reprobados,
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

// Obtener entregas
if (in_array($_SESSION['rol'], ['superadmin', 'admin'])) {
    $query_entregas = "SELECT e.*, t.titulo as tarea_titulo, t.nota_maxima, a.nombre as alumno_nombre, a.apellido as alumno_apellido, m.nombre as materia_nombre 
                     FROM entregas e JOIN tareas t ON e.tarea_id = t.id JOIN materias m ON t.materia_id = m.id JOIN alumnos a ON e.alumno_id = a.id
                     ORDER BY e.fecha_entrega DESC";
    $stmt_e = mysqli_prepare($con, $query_entregas);
} else {
    $query_entregas = "SELECT e.*, t.titulo as tarea_titulo, t.nota_maxima, a.nombre as alumno_nombre, a.apellido as alumno_apellido, m.nombre as materia_nombre 
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
<html lang="es" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?=htmlspecialchars(csrf_token())?>">
    <title>Portal Docente | IBBS</title>
    
    <!-- CSS Maestro del Sistema IBBS -->
    <link rel="stylesheet" href="assets/ibbs.css">
    
    <!-- Dependencias externas -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
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
        .chat-item.active-chat { background: #fff; border-left: 3px solid var(--lime2); box-shadow: inset 0 2px 4px rgba(0,0,0,.02); }
        .msg-bubble { max-width: 75%; padding: .8rem 1rem; border-radius: 14px; font-size: .88rem; box-shadow: 0 2px 6px rgba(0,0,0,.04); position: relative; }
        .msg-mine { background: var(--ink); color: #fff; border-bottom-right-radius: 4px; }
        .msg-other { background: #fff; border: 1px solid var(--border); color: var(--ink); border-bottom-left-radius: 4px; }
        
        /* Alertas Premium */
        .alert-box {
            background: rgba(217,119,6,.08); border-left: 4px solid var(--amber);
            padding: 1rem 1.2rem; border-radius: 8px; margin-bottom: 1.5rem; display: flex; gap: .8rem; align-items:flex-start;
        }
        .alert-box i { color: var(--amber); font-size: 1.2rem; margin-top: 2px; }
        
        /* Ajuste de foto de perfil */
        .user-ava img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; }
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
            <button onclick="switchView('dashboard', this)" class="sb-link act">
                <i class="fas fa-th-large"></i> <span class="sb-lbl">Inicio</span>
            </button>
            
            <div class="sb-section">Mi Gestión</div>
            
            <button onclick="switchView('materias', this)" class="sb-link">
                <i class="fas fa-book"></i> <span class="sb-lbl">Mis Cursos</span>
            </button>
            <button onclick="switchView('aula', this)" class="sb-link">
                <i class="fas fa-desktop"></i> <span class="sb-lbl">Aula Virtual</span>
            </button>
            <button onclick="switchView('entregas', this)" class="sb-link">
                <i class="fas fa-pencil-alt"></i> <span class="sb-lbl">Cargar Notas</span>
            </button>
            <button onclick="switchView('chat', this)" class="sb-link">
                <i class="fas fa-comments"></i> <span class="sb-lbl">Foros de Clase</span>
            </button>

            <?php if(in_array($_SESSION['rol'], ['superadmin', 'admin'])): ?>
            <div class="sb-section" style="margin-top: .5rem;">Administración</div>
            <button onclick="switchView('admin-docentes', this)" class="sb-link">
                <i class="fas fa-users-cog"></i> <span class="sb-lbl">Asignar Materias</span>
            </button>
            <?php endif; ?>
        </nav>

        <div class="sb-bottom">
            <button onclick="switchView('perfil', this)" class="sb-link">
                <i class="fas fa-user-circle"></i> <span class="sb-lbl">Mi Perfil</span>
            </button>
            <a href="logout.php" class="sb-link">
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
                <button class="tab-btn" onclick="filtrarEntregas('pendientes', this)">Pendientes (<span style="color:var(--amber); font-weight:bold;"><?= $por_calificar_count ?></span>)</button>
                <button class="tab-btn" onclick="filtrarEntregas('calificadas', this)">Calificadas</button>
            </div>

            <div class="grid-cards" id="contenedor-entregas">
                <?php foreach($entregas as $e): ?>
                    <!-- Tarjeta con data-estado para facilitar el filtro -->
                    <div class="card entrega-card" data-estado="<?= $e['nota'] === null ? 'pendiente' : 'calificada' ?>" style="<?= $e['nota'] === null ? 'border-left: 4px solid var(--amber);' : 'border-left: 4px solid var(--lime2); opacity: 0.9;' ?>">
                        <div class="card-body" style="display: flex; flex-direction: column; height: 100%;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: .8rem;">
                                <span class="badge" style="background:var(--cream); color:var(--muted); border:1px solid var(--border);"><?= htmlspecialchars($e['materia_nombre']) ?></span>
                                <?php if($e['nota'] === null): ?>
                                    <span class="badge b-tardanza"><i class="fas fa-clock"></i> Por Calificar</span>
                                <?php else: ?>
                                    <span class="badge b-presente"><i class="fas fa-check"></i> Nota: <?= htmlspecialchars($e['nota']) ?> / <?= htmlspecialchars($e['nota_maxima']) ?></span>
                                <?php endif; ?>
                            </div>
                            
                            <h3 style="font-family:'Playfair Display',serif; font-size:1.1rem; color:var(--ink); margin-bottom:.3rem;"><?= htmlspecialchars($e['tarea_titulo']) ?></h3>
                            <p style="font-size:.8rem; color:var(--muted); margin-bottom: 1rem;">
                                <i class="fas fa-user-graduate"></i> Alumno: <strong style="color:var(--ink);"><?= htmlspecialchars($e['alumno_nombre'] . ' ' . $e['alumno_apellido']) ?></strong>
                            </p>
                            
                            <?php if($e['texto_respuesta']): ?>
                            <div style="background: var(--cream); padding: .8rem; border-radius: 8px; font-size: .8rem; color: var(--ink); font-style: italic; margin-bottom: 1rem; border: 1px solid var(--border);">
                                "<?= nl2br(htmlspecialchars($e['texto_respuesta'])) ?>"
                            </div>
                            <?php endif; ?>
                            
                            <div style="margin-top: auto; margin-bottom: 1rem; display: flex; flex-direction: column; gap: .5rem;">
                                <p style="font-size: .75rem; color: var(--muted); font-weight: 600;"><i class="far fa-calendar-check"></i> Entregado: <?= date('d M Y, h:i A', strtotime($e['fecha_entrega'])) ?></p>
                                <?php if($e['archivo']): ?>
                                <a href="uploads/entregas/<?= htmlspecialchars($e['archivo']) ?>" target="_blank" class="btn btn-secondary btn-sm" style="width: max-content;">
                                    <i class="fas fa-download"></i> Descargar Adjunto
                                </a>
                                <?php endif; ?>
                            </div>
                            
                            <button onclick="openModalCalificar(<?= $e['id'] ?>, '<?= htmlspecialchars($e['alumno_nombre'].' '.$e['alumno_apellido'], ENT_QUOTES) ?>', '<?= htmlspecialchars($e['tarea_titulo'], ENT_QUOTES) ?>', <?= $e['nota_maxima'] ?? 20 ?>, <?= $e['nota'] === null ? 'null' : $e['nota'] ?>)" 
                                    class="btn <?= $e['nota'] === null ? 'btn-primary' : 'btn-secondary' ?>" style="width: 100%; justify-content: center;">
                                <i class="fas fa-star"></i> <?= $e['nota'] === null ? 'Asignar Calificación' : 'Modificar Calificación' ?>
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
        <!-- VISTA: CHAT/FORO                               -->
        <!-- ============================================== -->
        <div id="view-chat" class="view-section">
            <h2 style="font-family:'Playfair Display',serif; font-size:1.6rem; color:var(--ink); margin-bottom: 1.5rem;">Foros Académicos</h2>
            
            <div class="card chat-layout">
                <!-- Lista de Foros -->
                <div class="chat-sidebar">
                    <div style="padding: 1.2rem; border-bottom: 1px solid var(--border); background: #fff;">
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
                    <div style="padding: 1.2rem; background: #fff; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: .8rem; z-index: 10;">
                        <div style="width: 38px; height: 38px; border-radius: 50%; background: rgba(57,255,20,.15); color: var(--lime2); display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-users"></i>
                        </div>
                        <div>
                            <h3 id="chat-title" style="font-family:'Playfair Display',serif; font-size: 1.1rem; color: var(--ink); line-height: 1.1;"><?= !empty($materias) ? htmlspecialchars($materias[0]['nombre']) : 'Seleccione una materia' ?></h3>
                            <span style="font-size: .65rem; color: var(--muted); text-transform: uppercase; letter-spacing: 1px; font-weight: 700;">Foro Oficial</span>
                        </div>
                    </div>
                    
                    <div id="chat-messages-container" style="flex: 1; overflow-y: auto; padding: 1.5rem; display: flex; flex-direction: column; gap: 1rem;"></div>

                    <div style="padding: 1.2rem; background: #fff; border-top: 1px solid var(--border);">
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
            <h2 style="font-family:'Playfair Display',serif; font-size:1.6rem; color:var(--ink); margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid var(--border);">Aula Virtual (Accesos)</h2>
            
            <div class="grid-cards">
                <?php foreach($materias as $m): ?>
                <div class="card">
                    <div class="card-body" style="text-align: center; padding: 2rem 1.5rem; display: flex; flex-direction: column; align-items: center;">
                        <div style="width: 60px; height: 60px; background: rgba(37,99,235,.1); color: var(--blue); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.6rem; margin-bottom: 1.2rem;">
                            <i class="fas fa-desktop"></i>
                        </div>
                        <h3 style="font-weight: 700; color: var(--ink); font-size: 1.15rem; margin-bottom: .4rem;"><?= htmlspecialchars($m['nombre']) ?></h3>
                        <span class="badge" style="background:var(--cream); border:1px solid var(--border); color:var(--muted); margin-bottom: 1.5rem;"><?= htmlspecialchars($m['codigo']) ?></span>
                        
                        <a href="modulo_aula.php?materia_id=<?= $m['id'] ?>" target="_blank" class="btn btn-primary" style="width: 100%; justify-content: center; margin-top: auto;">
                            <i class="fas fa-sign-in-alt"></i> Entrar al Aula
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <?php if(empty($materias)): ?>
                <div style="grid-column: 1 / -1; padding: 4rem 1rem; text-align: center; border: 2px dashed var(--border); border-radius: 14px;">
                    <i class="fas fa-folder-open" style="font-size: 3rem; color: var(--border); margin-bottom: 1rem;"></i>
                    <h3 style="font-family:'Playfair Display',serif; font-size: 1.2rem; color: var(--ink);">Sin aulas asignadas</h3>
                    <p style="color: var(--muted); font-size: .9rem;">El administrador aún no te ha asignado materias.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ============================================== -->
        <!-- VISTA: PERFIL                                  -->
        <!-- ============================================== -->
        <div id="view-perfil" class="view-section">
            <h2 style="font-family:'Playfair Display',serif; font-size:1.6rem; color:var(--ink); margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid var(--border);">Configuración de Perfil</h2>
            
            <div class="card" style="max-width: 700px; margin: 0 auto;">
                <div class="card-body" style="padding: 2.5rem;">
                    <form action="api/ajax.php" method="POST" enctype="multipart/form-data">
                        <!-- Permite actualizar perfil en backend genérico si es necesario, o tu archivo específico -->
                        <input type="hidden" name="action" value="actualizar_perfil_docente">
                        
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
                                <input type="file" name="foto" accept="image/*" style="opacity: 0; position: absolute; inset: 0; cursor: pointer;" onchange="previewAvatar(this)">
                            </label>
                            <span style="font-size: .7rem; color: var(--muted); margin-top: .6rem;">Soporta JPG y PNG. Máx 2MB.</span>
                        </div>

                        <!-- SECCIÓN: DATOS GENERALES -->
                        <div class="form-grid" style="margin-bottom: 1.5rem;">
                            <div class="field">
                                <label>Nombre de Usuario</label>
                                <input type="text" name="nombre" value="<?= htmlspecialchars($usuario_db['usuario'] ?? '') ?>" required>
                            </div>
                            <div class="field">
                                <label>Cédula (Solo lectura)</label>
                                <input type="text" value="<?= htmlspecialchars($usuario_db['cedula'] ?? '') ?>" readonly style="background: var(--cream); opacity: .8; border-color: transparent;">
                            </div>
                            <div class="field field-full">
                                <label>Correo Electrónico</label>
                                <input type="email" name="email" value="<?= htmlspecialchars($usuario_db['correo'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="divider"></div>
                        <div style="display: flex; justify-content: flex-end;">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Guardar Cambios</button>
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
                    <input type="hidden" name="entrega_id" id="modal-cal-id">
                    
                    <div style="background: var(--cream); border: 1px solid var(--border); padding: 1rem; border-radius: 8px; margin-bottom: 1.2rem;">
                        <div class="section-label" style="margin-bottom: .2rem;">Alumno</div>
                        <p id="modal-cal-alumno" style="font-size: .95rem; font-weight: 700; color: var(--ink); margin-bottom: .8rem;"></p>
                        <div class="section-label" style="margin-bottom: .2rem;">Tarea</div>
                        <p id="modal-cal-tarea" style="font-size: .9rem; color: var(--ink);"></p>
                    </div>
                    
                    <div class="field" style="margin-bottom: 1.5rem;">
                        <label style="text-align: center; font-size: .8rem;">Nota Asignada (Máx <span id="modal-cal-max">20</span>)</label>
                        <input type="number" id="modal-cal-nota" name="nota" min="0" step="0.1" required style="font-size: 1.8rem; text-align: center; font-weight: 700; padding: 1rem;">
                    </div>
                    
                    <div class="modal-foot" style="padding: 0; border: none; background: transparent;">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-calificar')">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar</button>
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
                            <input type="file" name="archivo" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.zip,.rar" style="padding: .5rem; background: #fff; cursor: pointer;">
                        </div>

                        <div class="field field-full">
                            <label>Instrucciones / Descripción</label>
                            <textarea name="descripcion" rows="3" placeholder="Instrucciones para los alumnos..."></textarea>
                        </div>
                        <div class="field">
                            <label>Fecha Límite (Entrega)</label>
                            <input type="datetime-local" name="fecha_limite" required>
                        </div>
                        <div class="field">
                            <label>Nota Máxima</label>
                            <input type="number" name="nota_maxima" value="20" min="1" step="1" required>
                        </div>
                    </div>
                    <div class="modal-foot" style="padding: 0; border: none; background: transparent;">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-nueva-tarea')">Cancelar</button>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-upload"></i> Publicar Actividad</button>
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

        // Script para filtrar Entregas
        function filtrarEntregas(tipo, btn) {
            // Actualizar botones de pestaña
            document.querySelectorAll('#view-entregas .tab-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            
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

        // Script para previsualizar foto de perfil
        function previewAvatar(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const fallback = document.getElementById('avatar-preview-fallback');
                    if (fallback) fallback.remove();
                    
                    let preview = document.getElementById('avatar-preview');
                    if (!preview) {
                        preview = document.createElement('img');
                        preview.id = 'avatar-preview';
                        preview.style = 'width:100%;height:100%;object-fit:cover;';
                        input.closest('.view-section').querySelector('div[style*="border-radius: 50%"]').appendChild(preview);
                    }
                    preview.src = e.target.result;
                }
                reader.readAsDataURL(input.files[0]);
            }
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
        function openModalCalificar(id, alumno, tarea, max, notaActual) {
            document.getElementById('modal-cal-id').value = id;
            document.getElementById('modal-cal-alumno').innerText = alumno;
            document.getElementById('modal-cal-tarea').innerText = tarea;
            document.getElementById('modal-cal-max').innerText = max;
            document.getElementById('modal-cal-nota').max = max;
            document.getElementById('modal-cal-nota').value = notaActual !== null ? notaActual : '';
            openModal('modal-calificar');
        }

        function submitCalificacion(e) {
            e.preventDefault(); 
            const form = e.target; const btn = form.querySelector('button[type="submit"]');
            btn.disabled = true; btn.innerHTML = 'Guardando...';

            fetch('calificar_entrega.php', { method: 'POST', body: new FormData(form) })
            .then(res => res.json()).then(data => {
                if(data.ok) { closeModal('modal-calificar'); setTimeout(() => location.reload(), 500); } 
                else { alert(data.msg); btn.disabled = false; btn.innerHTML = 'Guardar'; }
            }).catch(err => { alert("Error de servidor."); btn.disabled = false; btn.innerHTML = 'Guardar'; });
        }

        function submitNuevaTarea(e) {
            e.preventDefault(); 
            const form = e.target; const btn = form.querySelector('button[type="submit"]');
            btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Subiendo...';

            fetch('crear_tarea.php', { method: 'POST', body: new FormData(form) })
            .then(res => res.json()).then(data => {
                if(data.ok) { 
                    closeModal('modal-nueva-tarea'); 
                    alert("Actividad/Tarea publicada con éxito."); 
                    form.reset(); 
                    location.reload(); 
                } else { 
                    alert(data.msg); 
                    btn.disabled = false; 
                    btn.innerHTML = '<i class="fas fa-upload"></i> Publicar Actividad'; 
                }
            }).catch(err => { 
                alert("Error crítico al subir."); 
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

            fetch('asignar_materia.php', { method: 'POST', body: new FormData(form) })
            .then(res => res.json()).then(data => {
                if(data.ok) { closeModal('modal-asignar-materia'); alert("Materia vinculada con éxito."); location.reload(); } 
                else { alert(data.msg); btn.disabled = false; btn.innerHTML = 'Vincular Materia'; }
            }).catch(err => { alert("Error de conexión."); btn.disabled = false; });
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
            if (!confirm('¿Borrar este mensaje?')) return;
            try {
                const _csrfMeta = document.querySelector('meta[name="csrf-token"]');
                const r = await fetch(`api/foro.php?action=delete_mensaje&materia_id=${materiaActivaChatId}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id, csrf_token: _csrfMeta ? _csrfMeta.content : '' })
                });
                const result = await r.json();
                if (result.success) cargarMensajesForo();
                else alert(result.error || 'No se pudo borrar el mensaje.');
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
            .then(data => { if (data.success) cargarMensajesForo(); else alert(data.error || 'No se pudo enviar el mensaje.'); })
            .catch(err => console.error(err));
        }

        if(materiaActivaChatId > 0) cargarMensajesForo();
        if(chatInterval) clearInterval(chatInterval);
        chatInterval = setInterval(() => {
            const viewChat = document.getElementById('view-chat');
            if(viewChat.classList.contains('active') && materiaActivaChatId > 0) cargarMensajesForo();
        }, 5000); 
    </script>
</body>
</html>