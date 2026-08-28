<?php
$page_title='Panel de Control';
$page_sub='Resumen general del sistema académico IBBS';
$active_link='inicio';
include __DIR__.'/layout/head.php';
// Check if system needs setup
$_con_ob = db();
$_cnt_alumnos = (int)(mysqli_fetch_assoc(mysqli_query($_con_ob,"SELECT COUNT(*) c FROM alumnos"))['c']??0);
$_cnt_materias = (int)(mysqli_fetch_assoc(mysqli_query($_con_ob,"SELECT COUNT(*) c FROM materias"))['c']??0);
$_cnt_docentes = (int)(mysqli_fetch_assoc(mysqli_query($_con_ob,"SELECT COUNT(*) c FROM docentes"))['c']??0);
$_needs_setup = ($_cnt_alumnos===0 && $_cnt_materias<=2 && $_cnt_docentes===0 && can('edit'));
mysqli_close($_con_ob);
?>

<?php if($_needs_setup): ?>
<!-- ══ ONBOARDING WIZARD ══ -->
<div id="onboardingPanel" style="background:linear-gradient(135deg,#1a4d2e 0%,#1e5c36 100%);border-radius:16px;padding:1.8rem;margin-bottom:1.6rem;color:#fff;">
  <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:1.4rem;flex-wrap:wrap;gap:.8rem;">
    <div>
      <div style="font-family:'DM Serif Display',serif;font-size:1.3rem;margin-bottom:.3rem;">👋 ¡Bienvenido a IBBS!</div>
      <div style="font-size:.83rem;color:rgba(255,255,255,.6);">El sistema está casi listo. Sigue estos 3 pasos para comenzar.</div>
    </div>
    <button onclick="document.getElementById('onboardingPanel').style.display='none'" style="background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.15);border-radius:8px;padding:.35rem .7rem;color:rgba(255,255,255,.5);cursor:pointer;font-size:.75rem;">Cerrar</button>
  </div>
  <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:.8rem;">
    <?php $step_style='background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.12);border-radius:12px;padding:1.1rem;'; ?>
    <a href="modulo_materias.php" style="<?=$step_style?>text-decoration:none;display:block;transition:all .2s;" onmouseover="this.style.background='rgba(255,255,255,.12)'" onmouseout="this.style.background='rgba(255,255,255,.07)'">
      <div style="font-size:1.5rem;margin-bottom:.5rem;">📚</div>
      <div style="font-weight:700;color:#fff;margin-bottom:.3rem;">1. Crear materia</div>
      <div style="font-size:.75rem;color:rgba(255,255,255,.5);">Agrega las materias del período académico actual.</div>
      <div style="margin-top:.8rem;font-size:.72rem;color:#39ff14;"><?=$_cnt_materias?> <?=$_cnt_materias===1?'materia':'materias'?> registradas →</div>
    </a>
    <a href="modulo_docentes.php" style="<?=$step_style?>text-decoration:none;display:block;transition:all .2s;" onmouseover="this.style.background='rgba(255,255,255,.12)'" onmouseout="this.style.background='rgba(255,255,255,.07)'">
      <div style="font-size:1.5rem;margin-bottom:.5rem;">🎓</div>
      <div style="font-weight:700;color:#fff;margin-bottom:.3rem;">2. Añadir docentes</div>
      <div style="font-size:.75rem;color:rgba(255,255,255,.5);">Registra los docentes y asígnalos a sus materias.</div>
      <div style="margin-top:.8rem;font-size:.72rem;color:#39ff14;"><?=$_cnt_docentes?> <?=$_cnt_docentes===1?'docente':'docentes'?> registrados →</div>
    </a>
    <a href="modulo_alumnos.php" style="<?=$step_style?>text-decoration:none;display:block;transition:all .2s;" onmouseover="this.style.background='rgba(255,255,255,.12)'" onmouseout="this.style.background='rgba(255,255,255,.07)'">
      <div style="font-size:1.5rem;margin-bottom:.5rem;">👤</div>
      <div style="font-weight:700;color:#fff;margin-bottom:.3rem;">3. Inscribir alumnos</div>
      <div style="font-size:.75rem;color:rgba(255,255,255,.5);">Registra alumnos e inscríbelos en sus materias.</div>
      <div style="margin-top:.8rem;font-size:.72rem;color:#39ff14;"><?=$_cnt_alumnos?> <?=$_cnt_alumnos===1?'alumno':'alumnos'?> registrados →</div>
    </a>
  </div>
  <style>
  @media(max-width:600px){ #onboardingPanel .obgrid { grid-template-columns:1fr!important; } }
  </style>
</div>
<?php endif; ?>

<?php
?>

<!-- Banner bienvenida -->
<div style="background:var(--ink);border-radius:14px;padding:1.8rem 2rem;margin-bottom:1.4rem;position:relative;overflow:hidden;">
  <div style="position:absolute;inset:0;background-image:radial-gradient(rgba(57,255,20,.04) 1px,transparent 1px);background-size:20px 20px;pointer-events:none;"></div>
  <div style="position:relative;z-index:1;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;">
    <div>
      <h3 style="font-family:'DM Serif Display',serif;font-size:1.4rem;color:#fff;margin-bottom:.3rem;">Bienvenido, <em style="color:var(--lime);font-style:italic;"><?=htmlspecialchars($_SESSION['usuario']??'')?></em></h3>
      <p style="font-size:.82rem;color:rgba(255,255,255,.4);">IBBS — Gestión académica completa · <?=date('l, d \d\e F \d\e Y')?></p>
    </div>
    <div style="display:flex;gap:.7rem;flex-wrap:wrap;">
      <a href="modulo_asistencias.php" class="btn btn-primary">Asistencia</a>
      <?php if(can('edit')): ?>
      <a href="modulo_notas.php" class="btn" style="background:rgba(255,255,255,.08);color:rgba(255,255,255,.7);border:1px solid rgba(255,255,255,.12);">Cargar Notas</a>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Stat cards -->
<div class="stats" id="ds" style="margin-bottom:1.4rem;">
  <div class="scard c1" style="position:relative;cursor:pointer;" onclick="location.href='modulo_alumnos.php'">
    <div class="scard-ico"><i class="bx bx-group" style="font-size:1.4rem;color:inherit;"></i></div>
    <div><div class="scard-val" id="sv0">0</div><div class="scard-key">Alumnos</div></div>
    <div id="sv0t" style="position:absolute;top:.6rem;right:.8rem;font-size:.65rem;color:var(--muted);display:none;"></div>
  </div>
  <div class="scard c2" style="position:relative;cursor:pointer;" onclick="location.href='modulo_docentes.php'">
    <div class="scard-ico"><i class="bx bx-chalkboard" style="font-size:1.4rem;color:inherit;"></i></div>
    <div><div class="scard-val" id="sv1">0</div><div class="scard-key">Docentes</div></div>
  </div>
  <div class="scard c3" style="position:relative;cursor:pointer;" onclick="location.href='modulo_materias.php'">
    <div class="scard-ico"><i class="bx bx-book-open" style="font-size:1.4rem;color:inherit;"></i></div>
    <div><div class="scard-val" id="sv2">0</div><div class="scard-key">Materias</div></div>
  </div>
  <div class="scard c4" style="position:relative;cursor:pointer;" onclick="location.href='modulo_asistencias.php'">
    <div class="scard-ico"><i class="bx bx-check-square" style="font-size:1.4rem;color:inherit;"></i></div>
    <div><div class="scard-val" id="sv3">0</div><div class="scard-key">Asistencias</div></div>
  </div>
</div>

<!-- Gráficos en el dashboard -->
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:1.2rem;margin-bottom:1.2rem;" class="chart-grid">

  <!-- Distribución notas: aprobados/reprobados/pendientes -->
  <div class="card">
    <div class="card-head">
      <h3>Calificaciones</h3>
      <span style="font-size:.75rem;color:var(--muted);">Resultados globales</span>
    </div>
    <div class="card-body" style="display:flex;justify-content:center;align-items:center;min-height:180px;">
      <canvas id="chartNotas" style="max-width:200px;max-height:200px;"></canvas>
    </div>
  </div>

  <!-- Estado materias -->
  <div class="card">
    <div class="card-head">
      <h3>Estado Materias</h3>
      <span style="font-size:.75rem;color:var(--muted);">Pendiente / En curso / Culminada</span>
    </div>
    <div class="card-body" style="display:flex;justify-content:center;align-items:center;min-height:180px;">
      <canvas id="chartMat" style="max-width:200px;max-height:200px;"></canvas>
    </div>
  </div>

  <!-- Asistencias -->
  <div class="card">
    <div class="card-head">
      <h3>Asistencias</h3>
      <span style="font-size:.75rem;color:var(--muted);">Por estado</span>
    </div>
    <div class="card-body" style="display:flex;justify-content:center;align-items:center;min-height:180px;">
      <canvas id="chartAsist" style="max-width:200px;max-height:200px;"></canvas>
    </div>
  </div>

</div>

<!-- Registro alumnos y docentes (línea de tiempo) -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.2rem;margin-bottom:1.2rem;" class="chart-grid-2">
  <div class="card">
    <div class="card-head">
      <h3>Nuevos Alumnos</h3>
      <span style="font-size:.75rem;color:var(--muted);">Últimos 6 meses</span>
    </div>
    <div class="card-body"><canvas id="chartAlum" height="180"></canvas></div>
  </div>
  <div class="card">
    <div class="card-head">
      <h3>Nuevos Docentes</h3>
      <span style="font-size:.75rem;color:var(--muted);">Últimos 6 meses</span>
    </div>
    <div class="card-body"><canvas id="chartDoc" height="180"></canvas></div>
  </div>
</div>


<script>
const LIME='#39ff14', INK='#1a4d2e', BLUE='#3b82f6', AMBER='#f59e0b', RED='#ef4444', GREEN='#22c55e', MUTED='rgba(0,0,0,.15)';
Chart.defaults.font.family = "'Nunito','Inter',sans-serif";
Chart.defaults.color = '#7a8c72';
const PIE_OPTS = { responsive:true, plugins:{ legend:{ position:'bottom', labels:{ boxWidth:10, padding:10, font:{size:11} } } } };

function countUp(el, target) {
  let n=0; const step=Math.ceil(target/30);
  const t=setInterval(()=>{ n=Math.min(n+step,target); el.textContent=n; if(n>=target) clearInterval(t); },35);
}
function fillMonths(data) {
  const res=[]; const now=new Date();
  for(let i=5;i>=0;i--){
    const d=new Date(now.getFullYear(),now.getMonth()-i,1);
    const k=d.toISOString().substring(0,7);
    const found=data.find(x=>x.mes===k);
    res.push({lbl:['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'][d.getMonth()],cnt:found?parseInt(found.cnt):0});
  }
  return res;
}

document.addEventListener('ibbs:ready', async () => {
  const d = await ajax('dashboard_stats');
  if (!d?.ok) return;
  const {alumnos,docentes,materias,asist,chart_alumnos,chart_docentes,mat_estado,aprobados,reprobados,sin_nota} = d.data;

  // Stat cards with count-up
  countUp(document.getElementById('sv0'), parseInt(alumnos));
  countUp(document.getElementById('sv1'), parseInt(docentes));
  countUp(document.getElementById('sv2'), parseInt(materias));
  countUp(document.getElementById('sv3'), parseInt(asist));

  // Chart: Notas donut
  new Chart(document.getElementById('chartNotas'), {
    type:'doughnut',
    data:{ labels:['Aprobados','Reprobados','Sin nota'],
      datasets:[{ data:[parseInt(aprobados),parseInt(reprobados),parseInt(sin_nota)],
        backgroundColor:['#22c55e','#ef4444','#e2e8f0'], borderWidth:0, hoverOffset:6 }] },
    options:{ ...PIE_OPTS, cutout:'70%' }
  });

  // Chart: Estado materias donut
  const me = mat_estado||{};
  new Chart(document.getElementById('chartMat'), {
    type:'doughnut',
    data:{ labels:['En curso','Pendiente','Culminada'],
      datasets:[{ data:[parseInt(me.en_curso||0),parseInt(me.pendiente||0),parseInt(me.culminada||0)],
        backgroundColor:['#3b82f6','#f59e0b','#22c55e'], borderWidth:0, hoverOffset:6 }] },
    options:{ ...PIE_OPTS, cutout:'70%' }
  });

  // Chart: Asistencias donut — need separate call
  const da = await ajax('asistencia_resumen_global');
  if (da?.ok) {
    new Chart(document.getElementById('chartAsist'), {
      type:'doughnut',
      data:{ labels:['Presente','Ausente','Tardanza','Justificado'],
        datasets:[{ data:[parseInt(da.data.presente||0),parseInt(da.data.ausente||0),parseInt(da.data.tardanza||0),parseInt(da.data.justificado||0)],
          backgroundColor:['#22c55e','#ef4444','#f59e0b','#3b82f6'], borderWidth:0, hoverOffset:6 }] },
      options:{ ...PIE_OPTS, cutout:'70%' }
    });
  }

  // Chart: Alumnos por mes (línea)
  const mA = fillMonths(chart_alumnos||[]);
  new Chart(document.getElementById('chartAlum'), {
    type:'line',
    data:{ labels:mA.map(x=>x.lbl), datasets:[{
      label:'Alumnos', data:mA.map(x=>x.cnt),
      borderColor:'#39ff14', backgroundColor:'rgba(57,255,20,.08)',
      borderWidth:2, pointRadius:4, pointBackgroundColor:'#39ff14', fill:true, tension:.4
    }]},
    options:{ responsive:true, plugins:{legend:{display:false}}, scales:{
      x:{grid:{color:'rgba(0,0,0,.04)'}},
      y:{beginAtZero:true, ticks:{stepSize:1}, grid:{color:'rgba(0,0,0,.04)'}}
    }}
  });

  // Chart: Docentes por mes
  const mD = fillMonths(chart_docentes||[]);
  new Chart(document.getElementById('chartDoc'), {
    type:'line',
    data:{ labels:mD.map(x=>x.lbl), datasets:[{
      label:'Docentes', data:mD.map(x=>x.cnt),
      borderColor:'#3b82f6', backgroundColor:'rgba(59,130,246,.08)',
      borderWidth:2, pointRadius:4, pointBackgroundColor:'#3b82f6', fill:true, tension:.4
    }]},
    options:{ responsive:true, plugins:{legend:{display:false}}, scales:{
      x:{grid:{color:'rgba(0,0,0,.04)'}},
      y:{beginAtZero:true, ticks:{stepSize:1}, grid:{color:'rgba(0,0,0,.04)'}}
    }}
  });

});
</script>
<style>
@media(max-width:600px){
  .chart-grid, .chart-grid-2 { grid-template-columns: 1fr !important; }
  .welcome-card { padding: 1.2rem !important; }
  .welcome-links { flex-direction: column; align-items: flex-start; gap: .5rem !important; }
}
</style>
<?php include __DIR__.'/layout/foot.php'; ?>
