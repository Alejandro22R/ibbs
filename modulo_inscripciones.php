<?php
$page_title  = 'Inscripciones';
$page_sub    = 'Ver en qué materias está inscrito cada alumno';
$active_link = 'inscripciones';
include __DIR__.'/layout/head.php';
// Acceso admin o superadmin
if(!in_array($_rol,['superadmin','admin'])){
    echo '<script>window.location="index.php";</script>'; exit;
}

$con = db();
$alumnos_list = [];
$r = mysqli_query($con,"SELECT id,nombre,apellido,cedula FROM alumnos WHERE activo=1 ORDER BY apellido,nombre");
while($f=mysqli_fetch_assoc($r)) $alumnos_list[]=$f;
mysqli_close($con);
?>

<div style="display:flex;justify-content:flex-end;margin-bottom:.6rem;">
  <button class="btn btn-secondary" onclick="IbbsTour.replay(window.IBBS_TOUR_INSCRIPCIONES)" title="Ver el tutorial de esta página otra vez">
    <i class="bx bx-play-circle"></i> Tutorial
  </button>
</div>
<div class="card" style="margin-bottom:1.4rem;" data-tour="insc-selector">
  <div class="card-body">
    <div class="field">
      <label>Seleccionar Alumno</label>
      <select id="selAlumno" onchange="cargarAlumno(this.value)">
        <option value="">— Elige un alumno —</option>
        <?php foreach($alumnos_list as $a): ?>
        <option value="<?=$a['id']?>"><?=htmlspecialchars($a['apellido'].', '.$a['nombre'])?> (<?=htmlspecialchars($a['cedula'])?>)</option>
        <?php endforeach; ?>
      </select>
      <p style="font-size:.78rem;color:var(--muted);margin-top:.6rem;">Los alumnos se inscriben solos desde su portal — acá solo ves en qué materias está cada uno. Para cargar una nota, ir a "Cargar Notas"; para exportar una constancia o planilla, ir a "Record Académico".</p>
    </div>
  </div>
</div>

<div id="emptyInsc" style="text-align:center;padding:4rem 1rem;color:var(--muted);">
  <svg width="52" height="52" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round" style="opacity:.3;margin-bottom:1rem;"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
  <p style="font-family:'DM Serif Display',serif;font-size:1.3rem;margin-bottom:.4rem;">Selecciona un alumno</p>
  <p style="font-size:.84rem;">Elige un alumno para ver en qué materias está inscrito.</p>
</div>

<div id="panelAlumno" style="display:none;">

  <!-- Header alumno -->
  <div id="alumnoHeader" style="background:linear-gradient(135deg,#1a4d2e 0%,#1e5c36 100%);border-radius:13px;padding:1.2rem 1.5rem;margin-bottom:1.3rem;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;box-shadow:var(--shadow-sm);">
    <div style="display:flex;align-items:center;gap:1rem;">
      <div id="alumnoAva" style="width:44px;height:44px;border-radius:50%;background:var(--lime2);color:var(--ink);font-family:'DM Serif Display',serif;font-size:1.3rem;display:flex;align-items:center;justify-content:center;font-weight:700;"></div>
      <div>
        <div id="alumnoNombre" style="font-family:'DM Serif Display',serif;font-size:1.1rem;color:#fff;"></div>
        <div id="alumnoCedula" style="font-size:.78rem;color:rgba(255,255,255,.45);margin-top:2px;"></div>
      </div>
    </div>
    <div id="alumnoStats" style="display:flex;gap:.8rem;flex-wrap:wrap;"></div>
  </div>

  <!-- Materias inscritas — de solo lectura: inscribir ya lo hace el
       alumno solo desde su portal, y la nota se carga desde "Cargar
       Notas". Acá solo se puede "Quitar" para corregir un error. -->
  <div class="card">
    <div class="card-head">
      <h3>Materias Inscritas</h3>
      <span id="badgeInscritas" class="badge b-alumno">0</span>
    </div>
    <div class="tbl-wrap">
      <table>
        <thead><tr>
          <th style="text-align:left;">Materia</th>
          <th style="text-align:center;width:90px;">Estado</th>
          <th style="width:60px;"></th>
        </tr></thead>
        <tbody id="tbInscritas"><tr class="empty-row"><td colspan="3">Sin materias inscritas.</td></tr></tbody>
      </table>
    </div>
  </div>
</div>

<script>
let _aid = null;
const MI_ROL = '<?=$_rol?>';

async function cargarAlumno(id) {
  if (!id) {
    document.getElementById('panelAlumno').style.display = 'none';
    document.getElementById('emptyInsc').style.display  = 'block';
    _aid = null; return;
  }
  _aid = id;
  const d = await ajax('inscripcion_alumno_materias', {alumno_id: id});
  if (!d?.ok) { toast(d?.msg || 'Error al cargar alumno', 'err'); return; }

  const {al, inscritas, disponibles} = d.data;
  const ini = (al.nombre || '?')[0].toUpperCase();

  document.getElementById('emptyInsc').style.display  = 'none';
  document.getElementById('panelAlumno').style.display = 'block';
  document.getElementById('alumnoAva').textContent     = ini;
  document.getElementById('alumnoNombre').textContent  = al.apellido + ', ' + al.nombre;
  document.getElementById('alumnoCedula').textContent  = 'CI: ' + al.cedula + (al.ciudad ? ' · ' + al.ciudad : '');

  document.getElementById('alumnoStats').innerHTML = `
    <div style="text-align:center;padding:.4rem .9rem;background:rgba(255,255,255,.06);border-radius:8px;">
      <div style="font-family:'DM Serif Display',serif;font-size:1.3rem;color:#fff;">${inscritas.length}</div>
      <div style="font-size:.62rem;text-transform:uppercase;letter-spacing:1px;color:rgba(255,255,255,.4);">Materias</div>
    </div>`;

  document.getElementById('badgeInscritas').textContent = inscritas.length;

  // Tabla materias inscritas (solo lectura + quitar)
  const tb = document.getElementById('tbInscritas');
  tb.innerHTML = !inscritas.length
    ? '<tr class="empty-row"><td colspan="3">Sin materias inscritas.</td></tr>'
    : inscritas.map(m => {
        const autoInsc = !!(m.auto_inscrito == 1);
        const puedeQuitar = !autoInsc || MI_ROL === 'superadmin';
        return `<tr>
          <td style="text-align:left;">
            <strong style="font-size:.72rem;color:var(--muted);">${h(m.codigo)}</strong>
            <div style="font-size:.86rem;">${h(m.nombre)}${autoInsc ? ' <span class="badge b-alumno" style="font-size:.6rem;vertical-align:middle;" title="El alumno se inscribió solo">Auto-inscrito</span>' : ''}</div>
            ${m.docentes ? `<div style="font-size:.7rem;color:var(--muted);">${h(m.docentes)}</div>` : ''}
            ${m.periodo_nombre ? `<div style="font-size:.68rem;color:var(--muted);">${h(m.periodo_nombre)}${m.periodo_anio?' · '+m.periodo_anio:''}</div>` : ''}
          </td>
          <td style="text-align:center;">
            <span class="badge b-presente" style="font-size:.68rem;">Inscrito</span>
          </td>
          <td style="text-align:center;">
            ${puedeQuitar
              ? `<button class="btn btn-sm btn-danger" onclick="desinscribir(${m.id},'${h(m.nombre)}')" style="font-size:.65rem;padding:3px 8px;">✕</button>`
              : `<span title="Auto-inscripción: solo un superadmin puede quitarla" style="color:var(--muted);font-size:.9rem;">🔒</span>`}
          </td>
        </tr>`;
      }).join('');
}

async function desinscribir(mid, nombre) {
  const rr = await Ibbs.confirm({title:'¿Quitar alumno?',text:`Se quitará de <b>${nombre}</b>. Si tiene nota cargada se perderá.`,confirm:'Sí, quitar',danger:true});
  if(!rr.isConfirmed) return;
  const d = await ajax('materia_remove_alumno', {materia_id: mid, alumno_id: _aid});
  if (d?.ok) { toast(d.msg); cargarAlumno(_aid); }
  else toast(d?.msg || 'Error', 'err');
}

function h(s) { const d = document.createElement('div'); d.textContent = String(s??''); return d.innerHTML; }

// ── Tutorial guiado de esta página ──────────────────────────────
window.IBBS_TOUR_INSCRIPCIONES = {
  storageKey: 'ibbs_tour_inscripciones_v1',
  steps: [
    { selector: '[data-tour="insc-selector"]', title: 'Inscripciones', text: 'Elegí un alumno para ver en qué materias está inscrito. Los alumnos se inscriben solos desde su portal — esta página es solo de consulta. Para cargar una nota, ir a "Cargar Notas"; para exportar una constancia o planilla, ir a "Record Académico".' },
  ],
  auto: true,
};
document.addEventListener('ibbs:ready', () => IbbsTour.start(window.IBBS_TOUR_INSCRIPCIONES));
</script>
<?php include __DIR__.'/layout/foot.php'; ?>
