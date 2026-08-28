<?php
$page_title  = 'Calificaciones';
$page_sub    = 'Nota final por materia · Escala 0–20 · Aprueba con 15';
$active_link = 'notas';
include __DIR__.'/layout/head.php';
// Solo admin, superadmin y profesor
if(!in_array($_rol,['superadmin','admin','profesor'])){
    echo '<script>window.location="index.php";</script>'; exit;
}

$con = db();
$materias = [];
$r = mysqli_query($con,"SELECT id,nombre,codigo,estado FROM materias WHERE activo=1 ORDER BY nombre");
while($f=mysqli_fetch_assoc($r)) $materias[]=$f;
mysqli_close($con);
?>

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
    <div class="scard c1"><div class="scard-ico"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg></div><div><div class="scard-val" id="sTotal">—</div><div class="scard-key">Inscritos</div></div></div>
    <div class="scard c3"><div class="scard-ico"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 11 12 14 22 4"/></svg></div><div><div class="scard-val" id="sApro">—</div><div class="scard-key">Aprobados</div></div></div>
    <div class="scard c4" style="--after-bg:var(--red)"><div class="scard-ico"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg></div><div><div class="scard-val" id="sRepro">—</div><div class="scard-key">Reprobados</div></div></div>
    <div class="scard c2"><div class="scard-ico"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div><div><div class="scard-val" id="sSin">—</div><div class="scard-key">Sin nota</div></div></div>
  </div>
</div>

<!-- Tabla de notas -->
<div id="areaTablaNota" style="display:none;">
  <div class="card">
    <div class="card-head">
      <h3 id="tablaTitle">Calificaciones</h3>
      <span id="docenteChip" style="font-size:.82rem;color:var(--muted);"></span>
    </div>
    <div class="tbl-wrap" id="tablaWrap"></div>
  </div>
</div>

<div id="areaEmpty" style="text-align:center;padding:4rem 1rem;color:var(--muted);">
  <svg width="52" height="52" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round" style="opacity:.3;margin-bottom:1rem;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
  <p style="font-family:'DM Serif Display',serif;font-size:1.3rem;margin-bottom:.4rem;">Selecciona una materia</p>
  <p style="font-size:.84rem;">Elige una materia para ver y cargar las calificaciones finales.</p>
</div>

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

  // Resumen
  document.getElementById('sTotal').textContent  = alumnos.length;
  document.getElementById('sApro').textContent   = aprobados;
  document.getElementById('sRepro').textContent  = reprobados;
  document.getElementById('sSin').textContent    = sin;

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

      rows += `<tr>
        <td>${i+1}</td>
        <td style="text-align:left;"><strong>${h(al.apellido)}</strong>, ${h(al.nombre)}</td>
        <td style="text-align:left;font-size:.8rem;color:var(--muted);">${h(al.cedula)}</td>
        <td style="text-align:center;">
          <button onclick="openNota(${al.id},${_mid},'${h(al.nombre+' '+al.apellido)}',${nv !== null ? nv : 'null'})"
            style="background:none;border:1.5px ${nv !== null ? 'solid' : 'dashed'} ${nv !== null ? (ok ? '#bbf7d0' : '#fecaca') : 'var(--border)'};
                   border-radius:8px;padding:6px 16px;cursor:pointer;font-family:'DM Serif Display',serif;
                   font-size:1.3rem;line-height:1;${cls};min-width:70px;"
            title="Clic para editar">
            ${nv !== null ? nv.toFixed(1) : '—'}
          </button>
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
  window.open('php/export_pdf.php?materia_id=' + _mid, '_blank');
}

function h(s) { const d = document.createElement('div'); d.textContent = String(s ?? ''); return d.innerHTML; }
</script>
<?php include __DIR__.'/layout/foot.php'; ?>
