<?php
$page_title  = 'Gráficos';
$page_sub    = 'Estadísticas visuales del sistema';
$active_link = 'graficos';
include __DIR__.'/layout/head.php';
// Acceso admin, superadmin o profesor
if(!in_array($_rol,['superadmin','admin','profesor'])){
    echo '<script>window.location="index.php";</script>'; exit;
}

?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.2rem;margin-bottom:1.2rem;">
  <!-- Registro alumnos -->
  <div class="card">
    <div class="card-head"><h3>Registro de Alumnos</h3><span style="font-size:.75rem;color:var(--muted);">Últimos 6 meses</span></div>
    <div class="card-body"><canvas id="chartAlumnos" height="220"></canvas></div>
  </div>
  <!-- Registro docentes -->
  <div class="card">
    <div class="card-head"><h3>Registro de Docentes</h3><span style="font-size:.75rem;color:var(--muted);">Últimos 6 meses</span></div>
    <div class="card-body"><canvas id="chartDocentes" height="220"></canvas></div>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.2rem;margin-bottom:1.2rem;">
  <!-- Estado materias -->
  <div class="card">
    <div class="card-head"><h3>Estado de Materias</h3><span style="font-size:.75rem;color:var(--muted);">Pendiente / En curso / Culminada</span></div>
    <div class="card-body" style="display:flex;justify-content:center;"><canvas id="chartMaterias" height="220" style="max-width:280px;"></canvas></div>
  </div>
  <!-- Asistencias general -->
  <div class="card">
    <div class="card-head"><h3>Resumen Asistencias</h3><span style="font-size:.75rem;color:var(--muted);">Por estado</span></div>
    <div class="card-body" style="display:flex;justify-content:center;"><canvas id="chartAsist" height="220" style="max-width:280px;"></canvas></div>
  </div>
</div>

<!-- Docentes con materias pendientes/culminadas -->
<div class="card">
  <div class="card-head"><h3>Docentes — Materias por Estado</h3></div>
  <div class="card-body"><canvas id="chartDocMat" height="160"></canvas></div>
</div>

<script>
document.addEventListener('ibbs:ready', async () => {

const LIME='#39ff14', INK='#1a4d2e', BLUE='#3b82f6', AMBER='#f59e0b', RED='#ef4444', GREEN='#22c55e', MUTED='#7a8c72';
Chart.defaults.font.family="'Nunito', sans-serif";
Chart.defaults.color='#7a8c72';

function meses(data){
  // Llenar últimos 6 meses aunque no haya datos
  const res=[]; const now=new Date();
  for(let i=5;i>=0;i--){const d=new Date(now.getFullYear(),now.getMonth()-i,1);const k=d.toISOString().substring(0,7);const found=data.find(x=>x.mes===k);res.push({mes:k.substring(5)+'/'+k.substring(0,4),cnt:found?parseInt(found.cnt):0});}
  return res;
}

(async()=>{
  const d=await ajax('dashboard_stats');
  if(!d?.ok) return;
  const {chart_alumnos,chart_docentes,mat_estado}=d.data;

  // Alumnos por mes
  const al=meses(chart_alumnos);
  new Chart(document.getElementById('chartAlumnos'),{type:'bar',data:{labels:al.map(x=>x.mes),datasets:[{label:'Alumnos registrados',data:al.map(x=>x.cnt),backgroundColor:'rgba(57,255,20,.2)',borderColor:LIME,borderWidth:2,borderRadius:6}]},options:{plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,ticks:{stepSize:1},grid:{color:'rgba(0,0,0,.04)'}},x:{grid:{display:false}}}}});

  // Docentes por mes
  const dc=meses(chart_docentes);
  new Chart(document.getElementById('chartDocentes'),{type:'line',data:{labels:dc.map(x=>x.mes),datasets:[{label:'Docentes registrados',data:dc.map(x=>x.cnt),borderColor:BLUE,backgroundColor:'rgba(59,130,246,.08)',fill:true,tension:.4,pointBackgroundColor:BLUE,pointRadius:5}]},options:{plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,ticks:{stepSize:1},grid:{color:'rgba(0,0,0,.04)'}},x:{grid:{display:false}}}}});

  // Estado materias
  const estados={pendiente:mat_estado.pendiente||0,en_curso:mat_estado.en_curso||0,culminada:mat_estado.culminada||0};
  new Chart(document.getElementById('chartMaterias'),{type:'doughnut',data:{labels:['Pendiente','En curso','Culminada'],datasets:[{data:[estados.pendiente,estados.en_curso,estados.culminada],backgroundColor:[RED,'rgba(59,130,246,.7)',GREEN],borderColor:['#fff','#fff','#fff'],borderWidth:3}]},options:{cutout:'60%',plugins:{legend:{position:'bottom',labels:{padding:16,usePointStyle:true}}}}});

  // Asistencias
  const ra=await ajax('asistencia_list',{});
  if(ra?.ok){
    const cnt={presente:0,ausente:0,tardanza:0,justificado:0};
    ra.data.forEach(x=>{if(cnt[x.estado]!==undefined)cnt[x.estado]++;});
    new Chart(document.getElementById('chartAsist'),{type:'doughnut',data:{labels:['Presente','Ausente','Tardanza','Justificado'],datasets:[{data:[cnt.presente,cnt.ausente,cnt.tardanza,cnt.justificado],backgroundColor:[GREEN,RED,AMBER,BLUE],borderColor:'#fff',borderWidth:3}]},options:{cutout:'60%',plugins:{legend:{position:'bottom',labels:{padding:14,usePointStyle:true}}}}});
  }

  // Docentes materias pendientes vs culminadas
  const rd=await ajax('docente_list');
  if(rd?.ok&&rd.data.length){
    const docs=rd.data.slice(0,10);// máx 10
    const labels=docs.map(d=>d.apellido+', '+d.nombre.split(' ')[0]);
    // Para cada docente contar materias por estado via ajax
    const pending=[],done=[];
    await Promise.all(docs.map(async(d,i)=>{
      const dm=await ajax('docente_get',{id:d.id});
      if(dm?.ok){
        pending[i]=(dm.data.materias||[]).filter(m=>m.estado!=='culminada').length;
        done[i]=(dm.data.materias||[]).filter(m=>m.estado==='culminada').length;
      } else { pending[i]=0; done[i]=0; }
    }));
    new Chart(document.getElementById('chartDocMat'),{type:'bar',data:{labels,datasets:[{label:'Pendiente/En curso',data:pending,backgroundColor:'rgba(59,130,246,.7)',borderRadius:4},{label:'Culminadas',data:done,backgroundColor:'rgba(34,197,94,.7)',borderRadius:4}]},options:{indexAxis:'y',plugins:{legend:{position:'top'}},scales:{x:{beginAtZero:true,stacked:false,grid:{color:'rgba(0,0,0,.04)'}},y:{grid:{display:false}}}}});
  }
})();
});
</script>
<?php include __DIR__.'/layout/foot.php'; ?>
