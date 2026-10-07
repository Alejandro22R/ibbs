<?php
$page_title  = 'Inscripciones';
$page_sub    = 'Inscribir alumnos a materias · Cargar calificaciones · Exportar planilla';
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

<div class="card" style="margin-bottom:1.4rem;">
  <div class="card-body">
    <div class="field">
      <label>Seleccionar Alumno</label>
      <select id="selAlumno" onchange="cargarAlumno(this.value)">
        <option value="">— Elige un alumno —</option>
        <?php foreach($alumnos_list as $a): ?>
        <option value="<?=$a['id']?>"><?=htmlspecialchars($a['apellido'].', '.$a['nombre'])?> (<?=htmlspecialchars($a['cedula'])?>)</option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>
</div>

<div id="emptyInsc" style="text-align:center;padding:4rem 1rem;color:var(--muted);">
  <svg width="52" height="52" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round" style="opacity:.3;margin-bottom:1rem;"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
  <p style="font-family:'DM Serif Display',serif;font-size:1.3rem;margin-bottom:.4rem;">Selecciona un alumno</p>
  <p style="font-size:.84rem;">Elige un alumno para ver y gestionar sus materias e inscripciones.</p>
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

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.2rem;">

    <!-- Materias inscritas + notas -->
    <div class="card">
      <div class="card-head">
        <h3>Materias Inscritas</h3>
        <div style="display:flex;gap:.5rem;align-items:center;">
          <span id="badgeInscritas" class="badge b-alumno">0</span>
          <a href="modulo_notas.php" class="btn btn-sm btn-secondary" style="font-size:.72rem;" title="Cargar o corregir la nota final de este alumno">
            <i class="bx bx-edit-alt"></i> Cargar Notas
          </a>
          <a id="btnPlanilla" href="#" onclick="abrirPlanilla(event)" class="btn btn-sm btn-primary" style="display:none;font-size:.72rem;">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:12px;height:12px;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            PDF Planilla
          </a>
        </div>
      </div>
      <div class="tbl-wrap">
        <table>
          <thead><tr>
            <th style="text-align:left;">Materia</th>
            <th style="text-align:center;width:70px;">Nota</th>
            <th style="text-align:center;width:80px;">Resultado</th>
            <th style="width:50px;"></th>
          </tr></thead>
          <tbody id="tbInscritas"><tr class="empty-row"><td colspan="4">Sin materias inscritas.</td></tr></tbody>
        </table>
      </div>
    </div>

    <!-- Panel inscribir -->
    <div class="card">
      <div class="card-head"><h3>Inscribir en Materia</h3></div>
      <div class="card-body">
        <div class="field" style="margin-bottom:1rem;">
          <label>Materia disponible</label>
          <select id="selMateriaInsc">
            <option value="">— Seleccionar materia —</option>
          </select>
        </div>
        <button class="btn btn-primary" onclick="inscribir()" style="width:100%;justify-content:center;">
          <svg viewBox="0 0 24 24" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Inscribir Alumno
        </button>
        <p id="disponiblesInfo" style="font-size:.78rem;color:var(--muted);margin-top:.8rem;text-align:center;"></p>

        <hr class="divider">

        <p style="font-size:.73rem;font-weight:600;text-transform:uppercase;letter-spacing:1px;color:var(--muted);margin-bottom:.7rem;">Exportar planilla de materia</p>
        <div class="field" style="margin-bottom:.8rem;">
          <label style="font-size:.75rem;">Ver planilla completa de una materia (todos los alumnos)</label>
          <select id="selMateriaExport">
            <option value="">— Seleccionar materia —</option>
          </select>
        </div>
        <a id="btnExportPlanilla" href="#" target="_blank" class="btn btn-secondary" style="width:100%;justify-content:center;font-size:.82rem;">
          <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
          Exportar planilla PDF
        </a>
      </div>
    </div>

  </div>
</div>


<script>
let _aid = null;
const MI_ROL = '<?=$_rol?>';

// Cargar el select de exportar planilla — tiene que esperar a
// 'ibbs:ready': ajax() recién se define en layout/foot.php, que se
// incluye DESPUÉS de este <script> (mismo bug que tenía
// modulo_materias.php: antes esto tiraba "ajax is not defined" en
// cada carga de la página, sin excepción).
document.addEventListener('ibbs:ready', async () => {
  const d = await ajax('materia_list');
  if (!d?.ok) return;
  const sel = document.getElementById('selMateriaExport');
  sel.innerHTML = '<option value="">— Seleccionar materia —</option>' +
    d.data.map(m => `<option value="${m.id}">${h(m.codigo)} · ${h(m.nombre)}</option>`).join('');
  sel.onchange = () => {
    const btn = document.getElementById('btnExportPlanilla');
    btn.href = sel.value ? 'api/export_pdf.php?materia_id=' + sel.value : '#';
  };
});

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

  const conNota = inscritas.filter(m => m.nota_final !== null);
  const aprob   = conNota.filter(m => parseFloat(m.nota_final) >= 15).length;
  const repro   = conNota.filter(m => parseFloat(m.nota_final) <  15).length;

  document.getElementById('alumnoStats').innerHTML = [
    ['#fff', inscritas.length, 'Materias'],
    ['var(--lime)', aprob, 'Aprobadas'],
    ['#f87171', repro, 'Reprobadas'],
  ].map(([color, val, lbl]) => `
    <div style="text-align:center;padding:.4rem .9rem;background:rgba(255,255,255,.06);border-radius:8px;">
      <div style="font-family:'DM Serif Display',serif;font-size:1.3rem;color:${color};">${val}</div>
      <div style="font-size:.62rem;text-transform:uppercase;letter-spacing:1px;color:rgba(255,255,255,.4);">${lbl}</div>
    </div>`).join('');

  document.getElementById('badgeInscritas').textContent = inscritas.length;
  document.getElementById('btnPlanilla').style.display  = inscritas.length ? 'inline-flex' : 'none';

  // Tabla materias inscritas
  const tb = document.getElementById('tbInscritas');
  tb.innerHTML = !inscritas.length
    ? '<tr class="empty-row"><td colspan="4">Sin materias inscritas. Inscribe al alumno usando el panel de la derecha.</td></tr>'
    : inscritas.map(m => {
        const nv  = m.nota_final !== null ? parseFloat(m.nota_final) : null;
        const ok  = nv !== null && nv >= 15;
        const cls = nv === null ? 'color:var(--muted)' : nv >= 15 ? 'color:#16a34a' : nv >= 10 ? 'color:#ca8a04' : 'color:#dc2626';
        const autoInsc = !!(m.auto_inscrito == 1);
        const puedeQuitar = !autoInsc || MI_ROL === 'superadmin';
        return `<tr>
          <td style="text-align:left;">
            <strong style="font-size:.72rem;color:var(--muted);">${h(m.codigo)}</strong>
            <div style="font-size:.86rem;">${h(m.nombre)}${autoInsc ? ' <span class="badge b-alumno" style="font-size:.6rem;vertical-align:middle;" title="El alumno se inscribió solo">Auto-inscrito</span>' : ''}</div>
            ${m.docentes ? `<div style="font-size:.7rem;color:var(--muted);">${h(m.docentes)}</div>` : ''}
          </td>
          <td style="text-align:center;">
            <span style="display:inline-block;border:1.5px ${nv!==null?'solid':'dashed'} ${nv!==null?(ok?'#bbf7d0':'#fecaca'):'var(--border)'};
                   border-radius:8px;padding:4px 12px;font-family:'DM Serif Display',serif;
                   font-size:1.15rem;${cls};min-width:58px;" title="La nota se carga desde Cargar Notas">
              ${nv !== null ? nv.toFixed(1) : '—'}
            </span>
          </td>
          <td style="text-align:center;">
            ${nv !== null
              ? `<span class="badge ${ok?'b-presente':'b-ausente'}" style="font-size:.68rem;">${ok?'Aprobado':'Reprobado'}</span>`
              : '<span style="font-size:.7rem;color:var(--muted);">Pendiente</span>'}
          </td>
          <td style="text-align:center;">
            ${puedeQuitar
              ? `<button class="btn btn-sm btn-danger" onclick="desinscribir(${m.id},'${h(m.nombre)}')" style="font-size:.65rem;padding:3px 8px;">✕</button>`
              : `<span title="Auto-inscripción: solo un superadmin puede quitarla" style="color:var(--muted);font-size:.9rem;">🔒</span>`}
          </td>
        </tr>`;
      }).join('');

  // Select materias disponibles
  const sel = document.getElementById('selMateriaInsc');
  sel.innerHTML = '<option value="">— Seleccionar materia —</option>' +
    disponibles.map(m => `<option value="${m.id}">${h(m.codigo)} · ${h(m.nombre)}${m.docentes?' — '+h(m.docentes):''}</option>`).join('');
  document.getElementById('disponiblesInfo').textContent = disponibles.length
    ? `${disponibles.length} materia(s) disponible(s) para inscripción`
    : 'El alumno ya está inscrito en todas las materias activas.';
}

async function inscribir() {
  const mid = document.getElementById('selMateriaInsc').value;
  if (!mid) { Ibbs.error('Debes seleccionar una materia primero.'); return; }
  const d = await ajax('materia_add_alumno', {materia_id: mid, alumno_id: _aid});
  if (d?.ok) { toast(d.msg); cargarAlumno(_aid); }
  else toast(d?.msg || 'Error', 'err');
}

async function desinscribir(mid, nombre) {
  const rr = await Ibbs.confirm({title:'¿Quitar alumno?',text:`Se quitará de <b>${nombre}</b>. Si tiene nota cargada se perderá.`,confirm:'Sí, quitar',danger:true});
  if(!rr.isConfirmed) return;
  const d = await ajax('materia_remove_alumno', {materia_id: mid, alumno_id: _aid});
  if (d?.ok) { toast(d.msg); cargarAlumno(_aid); }
  else toast(d?.msg || 'Error', 'err');
}

function abrirPlanilla(e) {
  e.preventDefault();
  // Exporta la primera materia inscrita — si solo hay una
  const rows = document.querySelectorAll('#tbInscritas tr:not(.empty-row)');
  toast('Usa el selector de "Exportar planilla PDF" en el panel derecho para elegir qué materia exportar.');
}

function h(s) { const d = document.createElement('div'); d.textContent = String(s??''); return d.innerHTML; }
</script>
<?php include __DIR__.'/layout/foot.php'; ?>
