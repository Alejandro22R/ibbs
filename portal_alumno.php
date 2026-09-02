<?php
$page_title = 'Mi Portal';
$page_sub   = 'Resumen de mis materias y progreso';
$active_link = 'inicio';
include __DIR__.'/layout/head.php';

// Bloqueo de seguridad: Solo alumnos o administradores pueden ver este portal.
if($_rol !== 'alumno' && !in_array($_rol, ['superadmin','admin'])) {
    echo '<script>window.location="index.php";</script>'; 
    exit;
}

$_con_ob = db();
$uid = (int)($_SESSION['user_id'] ?? 0);

// Necesitamos encontrar el ID de ALUMNO real basándonos en la sesión del usuario logueado.
$q_alu = mysqli_query($_con_ob, "
    SELECT a.id, a.nombre, a.apellido, a.cedula 
    FROM alumnos a 
    JOIN usuarios u ON a.cedula = u.cedula 
    WHERE u.id = $uid LIMIT 1
");

$alumno_data = mysqli_fetch_assoc($q_alu);
$alumno_id = $alumno_data ? $alumno_data['id'] : 0;

// Si encontramos al alumno, buscamos sus materias asignadas.
$materias = [];
if ($alumno_id > 0) {
    $q_mat = mysqli_query($_con_ob, "
        SELECT m.id, m.nombre, m.codigo, m.dias, m.hora_inicio, m.hora_fin, m.estado, ma.nota_final 
        FROM materia_alumno ma 
        JOIN materias m ON ma.materia_id = m.id 
        WHERE ma.alumno_id = $alumno_id
        ORDER BY m.estado DESC, m.nombre ASC
    ");
    while($row = mysqli_fetch_assoc($q_mat)) {
        $materias[] = $row;
    }
}
mysqli_close($_con_ob);
?>

<!-- Banner bienvenida -->
<div style="background:var(--ink);border-radius:14px;padding:1.8rem 2rem;margin-bottom:1.4rem;position:relative;overflow:hidden;">
  <div style="position:absolute;inset:0;background-image:radial-gradient(rgba(57,255,20,.04) 1px,transparent 1px);background-size:20px 20px;pointer-events:none;"></div>
  <div style="position:relative;z-index:1;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;">
    <div>
      <h3 style="font-family:'DM Serif Display',serif;font-size:1.4rem;color:#fff;margin-bottom:.3rem;">
          Bienvenido a tu campus, <em style="color:var(--lime);font-style:italic;"><?=htmlspecialchars($_SESSION['usuario']??'')?></em>
      </h3>
      <p style="font-size:.82rem;color:rgba(255,255,255,.4);">IBBS — Perfil de Alumno · <?=date('l, d \d\e F \d\e Y')?></p>
    </div>
  </div>
</div>

<h3 style="font-family:'DM Serif Display',serif; font-size:1.4rem; color:var(--ink); margin-bottom: 1rem;">Mis Materias Inscritas</h3>

<?php if(!$alumno_id): ?>
    <div style="background:#fee2e2; border:1px solid #fecaca; color:#dc2626; padding:1.5rem; border-radius:10px; text-align:center;">
        <b>⚠️ Advertencia:</b> Tu usuario aún no está vinculado a una ficha de alumno en el sistema. 
        Por favor, contacta a la administración para que registren tu cédula correctamente.
    </div>
<?php elseif(empty($materias)): ?>
    <div style="background:#f1f5f9; border:1px dashed #cbd5e1; color:#64748b; padding:3rem; border-radius:10px; text-align:center;">
        No tienes materias asignadas para este período.
    </div>
<?php else: ?>
    
    <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
        <?php foreach($materias as $m): 
            $bg_color = $m['estado'] === 'culminada' ? 'background:#f8fafc; opacity: 0.8;' : 'background:#fff;';
            $badge_class = '';
            if($m['estado'] === 'en_curso') $badge_class = 'b-tardanza';
            if($m['estado'] === 'pendiente') $badge_class = 'b-ausente';
            if($m['estado'] === 'culminada') $badge_class = 'b-presente';
            $lbl_estado = str_replace('_', ' ', ucfirst($m['estado']));
        ?>
        <div class="card" style="display:flex; flex-direction:column; justify-content:space-between; <?=$bg_color?> border-top: 4px solid var(--primary); transition: transform 0.2s; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);">
            <div class="card-body" style="padding: 1.5rem;">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom: 1rem;">
                    <span style="font-size:.75rem; font-weight:bold; color:var(--muted); background:#f1f5f9; padding:.2rem .5rem; border-radius:4px;">Cód. <?=htmlspecialchars($m['codigo'])?></span>
                    <span class="badge <?=$badge_class?>"><?=$lbl_estado?></span>
                </div>
                
                <h4 style="font-family:'DM Serif Display',serif; font-size:1.3rem; margin-bottom:.5rem; color:var(--ink);">
                    <?=htmlspecialchars($m['nombre'])?>
                </h4>
                
                <p style="font-size:.85rem; color:#64748b; margin-bottom:.3rem; display:flex; align-items:center; gap:5px;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    <?=$m['dias'] ?: 'Sin día asignado'?>
                </p>
                
                <p style="font-size:.85rem; color:#64748b; margin-bottom:1.5rem; display:flex; align-items:center; gap:5px;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    <?= ($m['hora_inicio'] ? date('h:i A', strtotime($m['hora_inicio'])) : '--:--') ?> - 
                    <?= ($m['hora_fin'] ? date('h:i A', strtotime($m['hora_fin'])) : '--:--') ?>
                </p>
                
                <?php if($m['estado'] === 'culminada' && $m['nota_final'] !== null): ?>
                <div style="background:#f0fdf4; border:1px solid #bbf7d0; padding:.8rem; border-radius:8px; text-align:center; margin-bottom:1rem;">
                    <span style="font-size:.8rem; color:#166534; display:block;">Nota Final</span>
                    <strong style="font-size:1.5rem; color:#15803d;"><?=number_format($m['nota_final'], 1)?></strong>
                </div>
                <?php endif; ?>

            </div>
            
            <div style="padding: 1rem 1.5rem; border-top: 1px solid var(--border); background: #fafafa; border-radius: 0 0 10px 10px;">
                <a href="modulo_aula.php?materia_id=<?=$m['id']?>" class="btn btn-primary" style="display:block; text-align:center; text-decoration:none; width:100%;">
                    Entrar al Aula Virtual
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
<?php endif; ?>

<?php include __DIR__.'/layout/foot.php'; ?>