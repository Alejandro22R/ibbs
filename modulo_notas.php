<?php
$page_title  = 'Calificaciones';
$page_sub    = 'Nota final por materia · Escala 0–20 · Aprueba con 15';
$active_link = 'notas';
include __DIR__.'/layout/head.php';
// Solo admin, superadmin y profesor
if(!in_array($_rol,['superadmin','admin','profesor'])){
    echo '<script>window.location="index.php";</script>'; exit;
}

$esAdmin = in_array($_rol,['superadmin','admin']);
$con = db();
// Admin/superadmin ven todas las materias; un profesor solo las suyas
// (materias_asignadas ya centraliza esta regla — ver config/materia_permisos.php).
$materias = materias_asignadas($con, $_uid, $_rol);
$alumnos_list = [];
if($esAdmin){
    $ra = mysqli_query($con, "SELECT id,nombre,apellido,cedula FROM alumnos WHERE activo=1 ORDER BY apellido,nombre");
    while($f = mysqli_fetch_assoc($ra)) $alumnos_list[] = $f;
}
mysqli_close($con);
?>

<?php if($esAdmin): ?>
<!-- Un profesor solo necesita "Por Materia" (ve sus propias materias
     asignadas) — la carga histórica y la importación son trabajo
     administrativo de migración, no algo que un profesor haga. -->
<div style="display:flex;justify-content:flex-end;margin-bottom:.6rem;">
  <button class="btn btn-secondary" onclick="IbbsTour.replay(window.IBBS_TOUR_NOTAS)" title="Ver el tutorial de esta página otra vez">
    <i class="bx bx-play-circle"></i> Tutorial
  </button>
</div>
<div class="tabs-nav">
  <button class="tab-btn active" data-tab-group="notas" data-tab="materia" onclick="switchTab('notas','materia')" data-tour="notas-tab-materia">Por Materia</button>
  <button class="tab-btn" data-tab-group="notas" data-tab="alumno" onclick="switchTab('notas','alumno')" data-tour="notas-tab-alumno">Histórico por Alumno</button>
  <button class="tab-btn" data-tab-group="notas" data-tab="excel" onclick="switchTab('notas','excel')" data-tour="notas-tab-excel">Importar desde Excel/CSV</button>
</div>
<?php endif; ?>

<div class="tab-pane active" data-pane-group="notas" data-pane="materia">
<!-- Selector de materia -->
<div class="card" style="margin-bottom:1.4rem;">
  <div class="card-body">
    <div class="form-grid">
      <div class="field">
        <label>Seleccionar Materia</label>
        <select id="selMateria" onchange="loadTabla()">
          <option value="">— Elige una materia —</option>
          <?php foreach($materias as $m): ?>
          <option value="<?=$m['id']?>"><?=htmlspecialchars($m['codigo'].' · '.$m['nombre'])?><?=$m['estado']==='culminada'?' ✓':''?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field" style="flex-direction:row;align-items:flex-end;justify-content:flex-end;gap:.6rem;">
        <a id="btnPDF" class="btn btn-primary" style="display:none;" href="#" onclick="abrirPDF(event)">
          <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
          Exportar PDF
        </a>
      </div>
    </div>
  </div>
</div>

<!-- Resumen estadístico -->
<div id="areaResumen" style="display:none;margin-bottom:1.2rem;">
  <div class="stats">
    <div class="scard c1"><div class="scard-ico"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg></div><div><div class="scard-val" data-countup id="sTotal">—</div><div class="scard-key">Inscritos</div></div></div>
    <div class="scard c3"><div class="scard-ico"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 11 12 14 22 4"/></svg></div><div><div class="scard-val" data-countup id="sApro">—</div><div class="scard-key">Aprobados</div></div></div>
    <div class="scard c4" style="--after-bg:var(--red)"><div class="scard-ico"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg></div><div><div class="scard-val" data-countup id="sRepro">—</div><div class="scard-key">Reprobados</div></div></div>
    <div class="scard c2"><div class="scard-ico"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div><div><div class="scard-val" data-countup id="sSin">—</div><div class="scard-key">Sin nota</div></div></div>
    <div class="scard c1" style="--after-bg:#6366f1"><div class="scard-ico"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20V10"/><path d="M18 20V4"/><path d="M6 20v-4"/></svg></div><div><div class="scard-val" data-countup id="sProm">—</div><div class="scard-key">Promedio</div></div></div>
  </div>
  <!-- Barra de aprobación general de la materia -->
  <div style="margin-top:.9rem;background:var(--paper);border:1.5px solid var(--border);border-radius:10px;padding:.7rem 1rem;">
    <div style="display:flex;justify-content:space-between;font-size:.72rem;color:var(--muted);margin-bottom:.35rem;">
      <span>Aprobación de la materia</span>
      <span id="sPctApro">—</span>
    </div>
    <div style="height:8px;background:var(--cream);border-radius:4px;overflow:hidden;">
      <div id="pctAproBar" style="height:100%;width:0%;border-radius:4px;background:#16a34a;transition:width .4s;"></div>
    </div>
  </div>
</div>

<!-- Tabla de notas -->
<div id="areaTablaNota" style="display:none;">
  <div class="card">
    <div class="card-head" style="flex-wrap:wrap;gap:.6rem;">
      <div>
        <h3 id="tablaTitle">Calificaciones</h3>
        <span id="docenteChip" style="font-size:.82rem;color:var(--muted);"></span>
      </div>
      <input type="text" id="fBuscarAlumno" placeholder="Buscar por nombre o cédula…" oninput="filtrarTablaNotas()"
        style="padding:.5rem .8rem;border:1.5px solid var(--border);border-radius:8px;font-size:.83rem;outline:none;background:var(--cream);min-width:220px;">
    </div>
    <div class="tbl-wrap" id="tablaWrap"></div>
  </div>
</div>

<div id="areaEmpty" style="text-align:center;padding:4rem 1rem;color:var(--muted);">
  <svg width="52" height="52" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round" style="opacity:.3;margin-bottom:1rem;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
  <p style="font-family:'DM Serif Display',serif;font-size:1.3rem;margin-bottom:.4rem;">Selecciona una materia</p>
  <p style="font-size:.84rem;">Elige una materia para ver y cargar las calificaciones finales.</p>
</div>
</div><!-- /pane materia -->

<?php if($esAdmin): ?>
<!-- ═══════════════════════════════════════════════
  HISTÓRICO POR ALUMNO — cargar de una vez todas las notas de un
  alumno (materias en las que ya está, + agregar una histórica que
  todavía no tenga), para digitalizar récords en papel sin tener que
  ir materia por materia.
════════════════════════════════════════════════════ -->
<div class="tab-pane" data-pane-group="notas" data-pane="alumno">
  <div class="card" style="margin-bottom:1.4rem;">
    <div class="card-body">
      <div class="form-grid">
        <div class="field">
          <label>Seleccionar Alumno</label>
          <select id="selAlumnoHist" onchange="cargarHistoricoAlumno()">
            <option value="">— Elige un alumno —</option>
            <?php foreach($alumnos_list as $al): ?>
            <option value="<?=$al['id']?>"><?=htmlspecialchars($al['apellido'].', '.$al['nombre'].' — CI: '.$al['cedula'])?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
    </div>
  </div>

  <div id="histEmpty" style="text-align:center;padding:4rem 1rem;color:var(--muted);">
    <p style="font-family:'DM Serif Display',serif;font-size:1.3rem;margin-bottom:.4rem;">Selecciona un alumno</p>
    <p style="font-size:.84rem;">Vas a poder ver y cargar de una vez todas sus notas, materia por materia.</p>
  </div>

  <div id="histArea" style="display:none;">
    <div class="card" style="margin-bottom:1.1rem;">
      <div class="card-head" style="flex-wrap:wrap;gap:.6rem;">
        <h3>Agregar una materia histórica</h3>
        <span style="font-size:.78rem;color:var(--muted);">Si el alumno cursó algo que no aparece abajo, agregalo acá primero</span>
      </div>
      <div class="card-body">
        <div style="display:flex;gap:.6rem;flex-wrap:wrap;align-items:flex-end;">
          <div class="field" style="flex:1;min-width:220px;margin:0;">
            <label>Materia</label>
            <select id="selAgregarMateriaHist"><option value="">— Seleccionar —</option></select>
          </div>
          <button class="btn btn-primary" onclick="agregarMateriaHistorico()">+ Agregar al récord</button>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-head">
        <h3 id="histTitulo">Materias del alumno</h3>
        <button class="btn btn-primary" id="btnGuardarHist" onclick="guardarHistoricoLote()">Guardar todas las notas</button>
      </div>
      <div class="tbl-wrap">
        <table>
          <thead><tr><th>Código</th><th style="text-align:left;">Materia</th><th>Período</th><th style="text-align:center;">Nota (0-20)</th></tr></thead>
          <tbody id="tbHistorico"><tr class="empty-row"><td colspan="4">Elegí un alumno arriba.</td></tr></tbody>
        </table>
      </div>
    </div>
  </div>
</div><!-- /pane alumno -->

<!-- ═══════════════════════════════════════════════
  IMPORTAR DESDE EXCEL/CSV — para digitalizar muchos alumnos de una,
  en vez de uno por uno. Ver api/notas_importar.php.
════════════════════════════════════════════════════ -->
<div class="tab-pane" data-pane-group="notas" data-pane="excel">
  <div class="card" style="margin-bottom:1.2rem;background:linear-gradient(135deg,var(--lime2) 0%,#15803d 100%);border:none;">
    <div class="card-body" style="padding:1.2rem 1.4rem;">
      <div style="font-weight:800;color:#fff;font-size:.95rem;margin-bottom:.3rem;">¿Cómo funciona?</div>
      <ol style="font-size:.82rem;color:rgba(255,255,255,.92);line-height:1.7;margin:0;padding-left:1.2rem;">
        <li>Descargá la plantilla y llenala: <b>cédula</b> del alumno, <b>materia</b>, <b>período</b> (tiene que existir ya — creálo en Materias › Períodos) y <b>nota</b> (0-20).</li>
        <li>Subila acá y revisá la vista previa — te dice qué pasaría con cada fila antes de guardar nada.</li>
        <li>Si todo se ve bien, confirmá. Si una materia histórica no existe todavía, se crea sola; el alumno tiene que existir de antes.</li>
      </ol>
    </div>
  </div>

  <div class="card" style="margin-bottom:1.2rem;">
    <div class="card-body" style="display:flex;gap:.8rem;flex-wrap:wrap;align-items:center;">
      <a class="btn btn-secondary" href="api/notas_importar.php?action=plantilla">
        <i class="bx bx-download"></i> Descargar plantilla .csv
      </a>
      <input type="file" id="csvFile" accept=".csv" style="padding:.55rem .8rem;border:1.5px solid var(--border);border-radius:8px;font-size:.82rem;background:var(--cream);">
      <button class="btn btn-primary" onclick="previsualizarCSV()">Ver vista previa</button>
    </div>
  </div>

  <div id="csvPreviewArea" style="display:none;">
    <div class="card">
      <div class="card-head" style="flex-wrap:wrap;gap:.6rem;">
        <h3 id="csvResumenTitulo">Vista previa</h3>
        <button class="btn btn-primary" id="btnConfirmarCSV" onclick="confirmarCSV()">Confirmar e importar</button>
      </div>
      <div class="tbl-wrap">
        <table>
          <thead><tr><th>Fila</th><th>Cédula</th><th>Alumno</th><th>Materia</th><th>Período</th><th style="text-align:center;">Nota</th><th>Qué pasaría</th></tr></thead>
          <tbody id="tbCsvPreview"></tbody>
        </table>
      </div>
    </div>
  </div>
</div><!-- /pane excel -->
<?php endif; ?>

<!-- MODAL INGRESAR NOTA -->
<div class="modal-backdrop" id="mNota">
  <div class="modal" style="max-width:400px;">
    <div class="modal-head">
      <h3 id="mNotaTitulo">Nota Final</h3>
      <button class="modal-close" onclick="closeModal('mNota')"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="nAid"> <input type="hidden" id="nMid">

      <!-- Preview de nota con color dinámico -->
      <div style="text-align:center;margin-bottom:1.4rem;">
        <div id="notaPreview" style="font-family:'DM Serif Display',serif;font-size:4rem;line-height:1;transition:color .3s;color:var(--muted);">—</div>
        <div id="notaEstado" style="font-size:.78rem;font-weight:600;letter-spacing:1.5px;text-transform:uppercase;margin-top:.3rem;color:var(--muted);">SIN NOTA</div>
        <div style="font-size:.72rem;color:var(--muted);margin-top:.2rem;">Escala 0 – 20 · Aprueba con 15</div>
      </div>

      <div class="field" style="margin-bottom:1rem;">
        <label>Calificación Final</label>
        <input type="number" id="nCal" min="0" max="20" step="0.1" data-only="decimal" placeholder="0.0"
          style="font-size:1.8rem;padding:1rem;text-align:center;letter-spacing:2px;"
          oninput="previewNota(this.value)">
      </div>
      <div class="field" style="margin-bottom:1.4rem;">
        <label>Fecha de registro</label>
        <input type="date" id="nFecha" value="<?=date('Y-m-d')?>">
      </div>

      <!-- Barra visual 0-20 con umbral en 15 -->
      <div style="margin-bottom:1rem;">
        <div style="display:flex;justify-content:space-between;font-size:.68rem;color:var(--muted);margin-bottom:.3rem;"><span>0</span><span style="color:var(--amber);font-weight:600;">15 ←aprueba</span><span>20</span></div>
        <div style="height:8px;background:var(--cream);border-radius:4px;overflow:hidden;position:relative;">
          <div style="position:absolute;left:0;top:0;height:100%;width:75%;background:rgba(57,255,20,.15);border-right:2px dashed var(--lime2);"></div>
          <div id="notaBar" style="position:absolute;left:0;top:0;height:100%;width:0%;border-radius:4px;transition:width .3s,background .3s;background:var(--muted);"></div>
        </div>
      </div>
    </div>
    <div class="modal-foot">
      <button class="btn btn-danger btn-sm" id="btnBorrarNota" style="margin-right:auto;display:none;" onclick="borrarNota()">Borrar nota</button>
      <button class="btn btn-secondary" onclick="closeModal('mNota')">Cancelar</button>
      <button class="btn btn-primary" onclick="guardarNota()">Guardar Nota</button>
    </div>
  </div>
</div>

<script>
let _mid = null;

async function loadTabla() {
  _mid = document.getElementById('selMateria').value;
  const btnPDF  = document.getElementById('btnPDF');
  const areaR   = document.getElementById('areaResumen');
  const areaT   = document.getElementById('areaTablaNota');
  const areaE   = document.getElementById('areaEmpty');

  if (!_mid) {
    btnPDF.style.display = 'none';
    areaR.style.display  = 'none';
    areaT.style.display  = 'none';
    areaE.style.display  = 'block';
    return;
  }

  const d = await ajax('notas_tabla_materia', {materia_id: _mid});
  if (!d?.ok) { toast(d?.msg || 'Error', 'err'); return; }

  const {mat, docentes, alumnos, aprobados, reprobados, sin} = d.data;
  const conNota = alumnos.filter(a => a.nota_final !== null);
  const promedio = conNota.length
    ? conNota.reduce((s, a) => s + parseFloat(a.nota_final), 0) / conNota.length
    : null;
  const pctApro = conNota.length ? Math.round(aprobados / conNota.length * 100) : 0;

  // Resumen
  document.getElementById('sTotal').textContent  = alumnos.length;
  document.getElementById('sApro').textContent   = aprobados;
  document.getElementById('sRepro').textContent  = reprobados;
  document.getElementById('sSin').textContent    = sin;
  document.getElementById('sProm').textContent   = promedio !== null ? promedio.toFixed(1) : '—';
  document.getElementById('sPctApro').textContent = conNota.length ? `${pctApro}% (${aprobados} de ${conNota.length} evaluados)` : 'Sin notas registradas aún';
  const bar = document.getElementById('pctAproBar');
  bar.style.width = pctApro + '%';
  bar.style.background = pctApro >= 70 ? '#16a34a' : pctApro >= 40 ? '#ca8a04' : '#dc2626';

  document.getElementById('fBuscarAlumno').value = '';

  // Título
  document.getElementById('tablaTitle').textContent = mat.nombre + ' — ' + mat.codigo;
  document.getElementById('docenteChip').textContent = docentes.length
    ? 'Docente(s): ' + docentes.map(x => x.nombre + ' ' + x.apellido).join(', ')
    : '';

  btnPDF.style.display = 'inline-flex';
  areaR.style.display  = 'block';
  areaT.style.display  = 'block';
  areaE.style.display  = 'none';

  // Tabla
  let rows = '';
  if (!alumnos.length) {
    rows = '<tr class="empty-row"><td colspan="5">Sin alumnos inscritos en esta materia.</td></tr>';
  } else {
    alumnos.forEach((al, i) => {
      const nv   = al.nota_final !== null ? parseFloat(al.nota_final) : null;
      const ok   = nv !== null && nv >= 15;
      const cls  = nv === null ? 'color:var(--muted)' : nv >= 15 ? 'color:#16a34a' : nv >= 10 ? 'color:#ca8a04' : 'color:#dc2626';
      const badge = nv !== null
        ? `<span class="badge ${ok ? 'b-presente' : 'b-ausente'}">${ok ? 'Aprobado' : 'Reprobado'}</span>`
        : '<span style="font-size:.75rem;color:var(--muted);">Pendiente</span>';
      const fecha = al.nota_fecha ? `<span style="font-size:.75rem;color:var(--muted);">${al.nota_fecha}</span>` : '—';
      const registro = (nv !== null && al.nota_registrada_por_nombre)
        ? `<div style="font-size:.72rem;color:var(--muted);">Por ${h(al.nota_registrada_por_nombre)}${al.nota_actualizada_en ? ' · '+al.nota_actualizada_en.replace('T',' ').slice(0,16) : ''}</div>`
        : '';

      const inicial = (al.nombre || '?').charAt(0).toUpperCase();

      rows += `<tr>
        <td>${i+1}</td>
        <td style="text-align:left;">
          <div style="display:flex;align-items:center;gap:.6rem;">
            <div style="width:32px;height:32px;flex-shrink:0;border-radius:50%;background:var(--ink);color:var(--lime);display:flex;align-items:center;justify-content:center;font-family:'DM Serif Display',serif;font-size:.95rem;">${inicial}</div>
            <div><strong>${h(al.apellido)}</strong>, ${h(al.nombre)}</div>
          </div>
        </td>
        <td style="text-align:left;font-size:.8rem;color:var(--muted);">${h(al.cedula)}</td>
        <td style="text-align:center;">
          <button onclick="openNota(${al.id},${_mid},'${hAttr(al.nombre+' '+al.apellido)}',${nv !== null ? nv : 'null'})"
            style="background:none;border:1.5px ${nv !== null ? 'solid' : 'dashed'} ${nv !== null ? (ok ? '#bbf7d0' : '#fecaca') : 'var(--border)'};
                   border-radius:8px;padding:6px 16px;cursor:pointer;font-family:'DM Serif Display',serif;
                   font-size:1.3rem;line-height:1;${cls};min-width:70px;"
            title="Clic para editar">
            ${nv !== null ? nv.toFixed(1) : '—'}
          </button>
          ${registro}
        </td>
        <td style="text-align:center;">${badge}</td>
        <td style="text-align:center;">${fecha}</td>
      </tr>`;
    });
  }

  document.getElementById('tablaWrap').innerHTML = `
    <table>
      <thead><tr>
        <th style="width:30px;">#</th>
        <th style="text-align:left;">Alumno</th>
        <th style="text-align:left;width:100px;">Cédula</th>
        <th style="text-align:center;">Nota Final</th>
        <th style="text-align:center;">Estado</th>
        <th style="text-align:center;">Fecha</th>
      </tr></thead>
      <tbody>${rows}</tbody>
    </table>`;
}

function openNota(aid, mid, nombre, valActual) {
  document.getElementById('nAid').value = aid;
  document.getElementById('nMid').value = mid;
  document.getElementById('mNotaTitulo').textContent = nombre;
  const cal = document.getElementById('nCal');
  cal.value = valActual !== null && valActual !== 'null' ? valActual : '';
  previewNota(cal.value);
  document.getElementById('btnBorrarNota').style.display =
    (valActual !== null && valActual !== 'null') ? 'inline-flex' : 'none';
  openModal('mNota');
  setTimeout(() => cal.focus(), 120);
}

function previewNota(v) {
  const pv  = document.getElementById('notaPreview');
  const pe  = document.getElementById('notaEstado');
  const bar = document.getElementById('notaBar');
  const n   = parseFloat(String(v).replace(',','.'));
  if (v === '' || isNaN(n)) {
    pv.textContent  = '—';
    pv.style.color  = 'var(--muted)';
    pe.textContent  = 'SIN NOTA';
    pe.style.color  = 'var(--muted)';
    bar.style.width = '0%';
    return;
  }
  const pct = Math.min(n / 20 * 100, 100);
  bar.style.width = pct + '%';
  if (n >= 15) {
    pv.style.color  = '#16a34a';
    pe.textContent  = '✓ APROBADO';
    pe.style.color  = '#16a34a';
    bar.style.background = '#22c55e';
  } else if (n >= 10) {
    pv.style.color  = '#ca8a04';
    pe.textContent  = '⚠ REPROBADO';
    pe.style.color  = '#ca8a04';
    bar.style.background = '#f59e0b';
  } else {
    pv.style.color  = '#dc2626';
    pe.textContent  = '✗ REPROBADO';
    pe.style.color  = '#dc2626';
    bar.style.background = '#ef4444';
  }
  pv.textContent = n % 1 === 0 ? n + '.0' : n.toFixed(1);
}

async function guardarNota() {
  const rawVal = document.getElementById('nCal').value.replace(',','.');
  const cal = parseFloat(rawVal);
  if (isNaN(cal) || cal < 0 || cal > 20) {
    Ibbs.error('Ingresa una nota entre 0 y 20.'); return;
  }
  const d = await ajax('nota_guardar', {
    materia_id: document.getElementById('nMid').value,
    alumno_id:  document.getElementById('nAid').value,
    nota:       cal,
    fecha:      document.getElementById('nFecha').value,
  });
  if (d?.ok) { toast(d.msg); closeModal('mNota'); loadTabla(); }
  else toast(d?.msg || 'Error', 'err');
}

async function borrarNota() {
  const rr = await Ibbs.confirm({title:'¿Borrar nota?',text:'Esta acción eliminará la nota del alumno.',confirm:'Sí, borrar',danger:true});
  if(!rr.isConfirmed) return;
  const d = await ajax('nota_borrar', {
    materia_id: document.getElementById('nMid').value,
    alumno_id:  document.getElementById('nAid').value,
  });
  if (d?.ok) { toast(d.msg); closeModal('mNota'); loadTabla(); }
  else toast(d?.msg || 'Error', 'err');
}

function abrirPDF(e) {
  e.preventDefault();
  window.open('api/export_pdf.php?materia_id=' + _mid, '_blank');
}

function filtrarTablaNotas() {
  const q = document.getElementById('fBuscarAlumno').value.trim().toLowerCase();
  document.querySelectorAll('#tablaWrap tbody tr:not(.empty-row)').forEach(tr => {
    tr.style.display = !q || tr.textContent.toLowerCase().includes(q) ? '' : 'none';
  });
}

function h(s) { const d = document.createElement('div'); d.textContent = String(s ?? ''); return d.innerHTML; }
function hAttr(s) { return h(s).replace(/\\/g,'\\\\').replace(/'/g,"\\'").replace(/"/g,'&quot;'); }

<?php if($esAdmin): ?>
// ═══════════════════════════════════════════════════════════════
// HISTÓRICO POR ALUMNO
// ═══════════════════════════════════════════════════════════════
let _histAlumnoId = null;
let _histMaterias = []; // lo que ya se está mostrando en la tabla

async function cargarHistoricoAlumno() {
  _histAlumnoId = document.getElementById('selAlumnoHist').value;
  const empty = document.getElementById('histEmpty');
  const area  = document.getElementById('histArea');
  if (!_histAlumnoId) { empty.style.display = 'block'; area.style.display = 'none'; return; }
  empty.style.display = 'none';
  area.style.display  = 'block';
  document.getElementById('tbHistorico').innerHTML = '<tr class="empty-row"><td colspan="4"><span class="spin"></span></td></tr>';

  const [d, todas] = await Promise.all([
    ajax('alumno_materias_para_historico', {alumno_id: _histAlumnoId}),
    ajax('materia_list'),
  ]);
  if (!d?.ok) { toast(d?.msg || 'Error', 'err'); return; }
  _histMaterias = d.data;
  renderTablaHistorico();

  // Select de "agregar materia histórica" — solo las que el alumno
  // todavía NO tiene en su récord.
  if (todas?.ok) {
    const yaIds = new Set(_histMaterias.map(m => String(m.materia_id)));
    const sel = document.getElementById('selAgregarMateriaHist');
    sel.innerHTML = '<option value="">— Seleccionar —</option>' +
      todas.data.filter(m => !yaIds.has(String(m.id)))
        .map(m => `<option value="${m.id}">${h(m.codigo)} · ${h(m.nombre)}${m.periodo_nombre ? ' ('+h(m.periodo_nombre)+')' : ''}</option>`).join('');
  }
}

function renderTablaHistorico() {
  const al = document.getElementById('selAlumnoHist');
  document.getElementById('histTitulo').textContent = 'Materias de ' + (al.options[al.selectedIndex]?.textContent || '');
  const tb = document.getElementById('tbHistorico');
  if (!_histMaterias.length) {
    tb.innerHTML = '<tr class="empty-row"><td colspan="4">Este alumno todavía no tiene materias — agregá una arriba.</td></tr>';
    return;
  }
  tb.innerHTML = _histMaterias.map(m => `
    <tr>
      <td style="font-size:.8rem;color:var(--muted);">${h(m.codigo)}</td>
      <td style="text-align:left;"><strong>${h(m.nombre)}</strong></td>
      <td style="font-size:.82rem;color:var(--muted);">${h(m.periodo_nombre || 'Sin período')}</td>
      <td style="text-align:center;">
        <input type="number" class="hist-nota-input" data-materia-id="${m.materia_id}" min="0" max="20" step="0.1"
          value="${m.nota_final !== null ? parseFloat(m.nota_final) : ''}" placeholder="—"
          style="width:80px;text-align:center;padding:.4rem;">
      </td>
    </tr>`).join('');
}

async function agregarMateriaHistorico() {
  const mid = document.getElementById('selAgregarMateriaHist').value;
  if (!mid) { toast('Elegí una materia primero.', 'err'); return; }
  const d = await ajax('materia_inscribir_historico', {alumno_id: _histAlumnoId, materia_id: mid});
  if (d?.ok) { toast(d.msg); cargarHistoricoAlumno(); }
  else toast(d?.msg || 'Error', 'err');
}

async function guardarHistoricoLote() {
  const filas = [...document.querySelectorAll('.hist-nota-input')]
    .map(inp => ({materia_id: inp.dataset.materiaId, nota: inp.value}))
    .filter(f => f.nota !== '');
  if (!filas.length) { toast('No hay notas para guardar.', 'err'); return; }
  const btn = document.getElementById('btnGuardarHist');
  btn.disabled = true;
  const d = await ajax('nota_guardar_lote', {alumno_id: _histAlumnoId, filas: JSON.stringify(filas)});
  btn.disabled = false;
  if (d?.ok) {
    if (d.errores && d.errores.length) Ibbs.warn(d.msg + '<br><br>' + d.errores.join('<br>'));
    else toast(d.msg);
    cargarHistoricoAlumno();
  } else toast(d?.msg || 'Error', 'err');
}

// ═══════════════════════════════════════════════════════════════
// IMPORTAR DESDE EXCEL/CSV
// ═══════════════════════════════════════════════════════════════
function filaCsvHtml(f) {
  const colorMap = {ok: 'var(--muted)', error: '#dc2626'};
  const estadoTxt = f.estado === 'error'
    ? `<span style="color:#dc2626;">⚠ ${h(f.mensaje)}</span>`
    : `<span style="color:#16a34a;">✓ ${h(f.mensaje)}</span>`;
  return `<tr style="${f.estado==='error' ? 'background:rgba(220,38,38,.05);' : ''}">
    <td>${f.fila}</td>
    <td>${h(f.cedula)}</td>
    <td>${h(f.alumno_nombre || '—')}</td>
    <td>${h(f.materia)}${f.materia_nueva ? ' <span class="badge b-tardanza" style="font-size:.62rem;">nueva</span>' : ''}</td>
    <td>${h(f.periodo)}</td>
    <td style="text-align:center;">${h(f.nota)}</td>
    <td style="font-size:.8rem;">${estadoTxt}</td>
  </tr>`;
}

async function previsualizarCSV() {
  const file = document.getElementById('csvFile').files[0];
  if (!file) { toast('Elegí un archivo .csv primero.', 'err'); return; }
  const fd = new FormData();
  fd.append('action', 'preview');
  fd.append('csvfile', file);
  const _csrf = document.querySelector('meta[name="csrf-token"]');
  fd.append('csrf_token', _csrf ? _csrf.content : '');
  toast('Leyendo archivo…');
  const r = await fetch('api/notas_importar.php', {method: 'POST', body: fd});
  const d = await r.json();
  if (!d.ok) { toast(d.msg || 'Error al leer el archivo.', 'err'); return; }
  document.getElementById('csvPreviewArea').style.display = 'block';
  document.getElementById('csvResumenTitulo').textContent =
    `Vista previa — ${d.resumen.ok} fila(s) OK, ${d.resumen.error} con error, de ${d.resumen.total} total`;
  document.getElementById('tbCsvPreview').innerHTML = d.data.map(filaCsvHtml).join('');
  document.getElementById('btnConfirmarCSV').disabled = d.resumen.ok === 0;
}

async function confirmarCSV() {
  const file = document.getElementById('csvFile').files[0];
  if (!file) { toast('Volvé a elegir el archivo.', 'err'); return; }
  const rr = await Ibbs.confirm({title:'¿Confirmar importación?', text:'Esto va a crear materias históricas, inscribir alumnos y guardar notas según lo que viste en la vista previa. No se puede deshacer de una — revisá bien antes.', confirm:'Sí, importar'});
  if (!rr.isConfirmed) return;
  const fd = new FormData();
  fd.append('action', 'confirmar');
  fd.append('csvfile', file);
  const _csrf = document.querySelector('meta[name="csrf-token"]');
  fd.append('csrf_token', _csrf ? _csrf.content : '');
  toast('Importando…');
  const r = await fetch('api/notas_importar.php', {method: 'POST', body: fd});
  const d = await r.json();
  if (!d.ok) { toast(d.msg || 'Error al importar.', 'err'); return; }
  document.getElementById('tbCsvPreview').innerHTML = d.data.map(filaCsvHtml).join('');
  document.getElementById('csvResumenTitulo').textContent =
    `Importación terminada — ${d.resumen.ok} fila(s) cargada(s), ${d.resumen.error} con error`;
  document.getElementById('btnConfirmarCSV').disabled = true;
  Ibbs.success(`${d.resumen.ok} nota(s) importada(s) correctamente.`);
}

// ── Tutorial guiado de esta página ──────────────────────────────
window.IBBS_TOUR_NOTAS = {
  storageKey: 'ibbs_tour_notas_v1',
  steps: [
    { selector: '[data-tour="notas-tab-materia"]', title: 'Por Materia', text: 'El día a día: elegís una materia y cargás la nota final de cada alumno inscrito, uno por uno.' },
    { selector: '[data-tour="notas-tab-alumno"]', title: 'Histórico por Alumno — tu carpeta de notas en papel', text: '¿Tenés la carpeta de un alumno con 20 materias ya cursadas? Entrá acá, elegilo en el selector, y por cada materia de su carpeta: si no aparece en la lista, usá "+ Agregar al récord" para sumarla primero; después escribí la nota (0-20) en cada fila. Cuando termines todas, un solo clic en "Guardar todas las notas" las deja registradas en su expediente — no hace falta guardar una por una.' },
    { selector: '[data-tour="notas-tab-excel"]', title: 'Importar desde Excel/CSV — para muchos alumnos de una', text: 'Si lo que tenés son planillas viejas de Excel con muchos alumnos: 1) "Descargar plantilla .csv" te da el formato exacto (cédula, materia, período, nota). 2) Llenala con tus datos y subila con "Ver vista previa" — te muestra fila por fila qué va a pasar, SIN guardar nada todavía. 3) Si se ve bien, "Confirmar e importar" la deja toda cargada de una. El alumno tiene que existir ya en el sistema; si una materia histórica no existe, se crea sola.' },
  ],
  auto: true,
};
document.addEventListener('ibbs:ready', () => IbbsTour.start(window.IBBS_TOUR_NOTAS));
<?php endif; ?>
</script>
<?php include __DIR__.'/layout/foot.php'; ?>
