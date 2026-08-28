<?php
$page_title = 'Record Académico';
$page_sub   = 'Historial completo de materias y calificaciones del alumno';
$active_link = 'record';
include __DIR__.'/layout/head.php';

// Conexión a la Base de Datos para poblar los alumnos
$con = db();
$alumnos_list = [];
$r = mysqli_query($con, "SELECT id, nombre, apellido, cedula, ciudad FROM alumnos WHERE activo=1 ORDER BY apellido, nombre");
while($f = mysqli_fetch_assoc($r)) {
    $alumnos_list[] = $f;
}
mysqli_close($con);
?>

<style>
:root {
  --primary-print: #1a4d2e;
  --secondary-print: #39ff14;
}

/* Ocultar el contenedor de impresión formal en la pantalla interactiva */
#printReport {
  display: none;
}

/* Mejoras estéticas en la UI de pantalla (Dashboard) */
.search-card {
  background: var(--paper);
  border: 1.5px solid var(--border);
  border-radius: 16px;
  padding: 1.5rem;
  margin-bottom: 1.5rem;
}

.indicator-box {
  background: rgba(255, 255, 255, 0.05);
  border-radius: 12px;
  padding: 0.8rem 1.2rem;
  text-align: center;
  flex: 1;
  min-width: 100px;
}

.profile-bar {
  background: var(--ink);
  border-radius: 16px;
  padding: 1.5rem;
  color: #fff;
  margin-bottom: 1.5rem;
}

.attendance-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 1rem;
}

@media (max-width: 768px) {
  .attendance-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

/* ═══════════════════════════════════════════════════
   ESTILOS DE IMPRESIÓN PROFESIONAL REGLAMENTARIA (PDF/PAPEL)
   ═══════════════════════════════════════════════════ */
@media print {
  /* Configurar tamaño de hoja vertical estándar con márgenes de oficina */
  @page {
    size: letter portrait;
    margin: 15mm 15mm 15mm 15mm;
  }

  /* Ocultar absolutamente todo el entorno web y menús de la aplicación */
  #sb, #sb-overlay, #toggler, .topbar, .search-card, .no-print, 
  .btn, button, footer, header, #toast, .modal-backdrop, .card-head {
    display: none !important;
  }

  /* Forzar cuerpo de documento totalmente plano, limpio y serio */
  body, #main, .main-content {
    background: #ffffff !important;
    color: #000000 !important;
    font-family: 'Times New Roman', Times, Georgia, serif !important;
    margin: 0 !important;
    padding: 0 !important;
    width: 100% !important;
    box-shadow: none !important;
  }

  /* Mostrar exclusivamente la plantilla formal del boletín ministerial */
  #printReport {
    display: block !important;
    font-size: 10.5pt !important;
    line-height: 1.5 !important;
    color: #000000 !important;
  }

  /* Ocultar el panel interactivo del sistema para que no se duplique en el papel */
  #panelRecord {
    display: none !important;
  }

  /* Estructura de Tabla Académica Reglamentaria (Igual a UPTeb) */
  .print-table {
    width: 100% !important;
    border-collapse: collapse !important;
    margin-top: 10pt !important;
    margin-bottom: 12pt !important;
  }

  .print-table th {
    background-color: #f5f5f5 !important;
    color: #000000 !important;
    font-weight: bold !important;
    border: 1px solid #000000 !important;
    padding: 5pt 7pt !important;
    text-transform: uppercase !important;
    font-size: 9pt !important;
    text-align: center !important;
  }

  .print-table td {
    border: 1px solid #000000 !important;
    padding: 5pt 7pt !important;
    font-size: 9.5pt !important;
    background: none !important;
  }

  .print-table tr {
    page-break-inside: avoid !important;
  }

  /* Estilos específicos para las observaciones y firmas institucionales */
  .observations-box {
    border: 1px solid #000000 !important;
    padding: 8pt !important;
    margin-top: 10pt !important;
    font-size: 9pt !important;
    line-height: 1.4 !important;
  }
}
</style>

<!-- ═══════════════════════════════════════════════════
  INTERFAZ DE PANTALLA (Para la Gestión de Alumnos)
═══════════════════════════════════════════════════════ -->
<div class="no-print" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.2rem;">
  <div>
    <h2 style="font-family:'DM Serif Display', serif; font-size: 1.8rem; color: var(--ink);"><?= $page_title ?></h2>
    <p style="font-size: .85rem; color: var(--muted);"><?= $page_sub ?></p>
  </div>
  <!-- Botón de acción para imprimir / guardar PDF oficial -->
  <button id="btnPrint" class="btn btn-primary" onclick="window.print()" disabled style="display:flex; align-items:center; gap:0.5rem; background-color: #1a4d2e; border-color: #1a4d2e; color: #39ff14;">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
    Descargar PDF / Imprimir Constancia
  </button>
</div>

<!-- Buscador Dinámico de Alumno -->
<div class="search-card no-print">
  <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem; margin-bottom:1rem;">
    <div class="field" style="margin:0;">
      <label>Buscar por Nombre o Apellido</label>
      <input type="text" id="searchNombre" placeholder="Escribe nombre o apellido..." oninput="filtrarAlumnosSelect()" style="padding:.6rem .9rem; border:1.5px solid var(--border); border-radius:8px; font-size:.85rem; width:100%; outline:none; background:var(--paper);">
    </div>
    <div class="field" style="margin:0;">
      <label>Buscar por Cédula</label>
      <input type="text" id="searchCedula" placeholder="Número de cédula..." oninput="filtrarAlumnosSelect()" style="padding:.6rem .9rem; border:1.5px solid var(--border); border-radius:8px; font-size:.85rem; width:100%; outline:none; background:var(--paper);">
    </div>
  </div>
  <div class="field" style="margin:0;">
    <label>Seleccionar Alumno</label>
    <select id="selectAlumno" onchange="cargarRecordAlumno(this.value)" style="width:100%; padding:.6rem .9rem; border:1.5px solid var(--border); border-radius:8px; font-size:.85rem; outline:none; background:var(--paper);">
      <option value="">— Seleccionar un Alumno —</option>
      <?php foreach($alumnos_list as $a): ?>
      <option value="<?= $a['id'] ?>" data-cedula="<?= htmlspecialchars($a['cedula']) ?>" data-ciudad="<?= htmlspecialchars($a['ciudad']??'') ?>">
        <?= htmlspecialchars($a['apellido'].', '.$a['nombre']) ?> — CI: <?= htmlspecialchars($a['cedula']) ?>
      </option>
      <?php endforeach; ?>
    </select>
  </div>
</div>

<!-- Estado Vacío -->
<div id="emptyRecord" class="no-print" style="text-align:center; padding:5rem 1rem; color:var(--muted); border: 1.5px dashed var(--border); border-radius: 16px;">
  <svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round" style="opacity:.4; margin-bottom:1rem;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
  <h3 style="font-family:'DM Serif Display', serif; font-size:1.3rem; margin-bottom:.3rem;">Consulta del Historial</h3>
  <p style="font-size:.84rem;">Elige o busca un alumno para generar y previsualizar su récord académico formal.</p>
</div>

<!-- Panel de Información en Pantalla (Dashboard interactivo para control interno) -->
<div id="panelRecord" class="no-print" style="display:none;">
  <!-- Header de Alumno Activo -->
  <div class="profile-bar">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
      <div style="display:flex; align-items:center; gap:1.2rem;">
        <div id="recAva" style="width:48px; height:48px; border-radius:50%; background:var(--lime); color:var(--ink); font-family:'DM Serif Display',serif; font-size:1.4rem; display:flex; align-items:center; justify-content:center; font-weight:700;"></div>
        <div>
          <h3 id="recAlumnoNombre" style="font-family:'DM Serif Display',serif; font-size:1.25rem; color:#fff; margin:0;"></h3>
          <p id="recAlumnoMeta" style="font-size:.82rem; color:rgba(255,255,255,.5); margin-top:2px;"></p>
        </div>
      </div>
      <div id="recStatsIndicators" style="display:flex; gap:1rem;"></div>
    </div>
  </div>

  <div style="display:grid; grid-template-columns: 2fr 1fr; gap:1.5rem; align-items:start;">
    <!-- Tabla de Calificaciones en Pantalla -->
    <div class="card">
      <div class="card-head">
        <h3>Calificaciones por Materia</h3>
      </div>
      <div class="tbl-wrap">
        <table>
          <thead>
            <tr>
              <th>Código</th>
              <th>Materia / Unidad Curricular</th>
              <th>Horario</th>
              <th style="text-align:center;">Estado</th>
              <th style="text-align:center;">Nota Final</th>
              <th style="text-align:center;">Resultado</th>
            </tr>
          </thead>
          <tbody id="tbRecMaterias"></tbody>
        </table>
      </div>
    </div>

    <!-- Resumen de Asistencias en Pantalla -->
    <div class="card">
      <div class="card-head">
        <h3>Registro de Asistencias</h3>
      </div>
      <div class="card-body">
        <div style="text-align:center; margin-bottom:1.5rem;">
          <div id="asistPercent" style="font-family:'DM Serif Display',serif; font-size:3rem; line-height:1; color:var(--ink);">0%</div>
          <div style="font-size:.7rem; color:var(--muted); text-transform:uppercase; letter-spacing:1px; margin-top:.3rem;">Asistencia General</div>
          <div style="height:6px; background:var(--cream); border-radius:3px; margin-top:.8rem; overflow:hidden;">
            <div id="asistBar" style="height:100%; width:0%; background:var(--ink); border-radius:3px; transition:width 0.4s;"></div>
          </div>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:.6rem;">
          <div style="background:var(--cream); padding:.6rem; border-radius:8px; text-align:center;">
            <div id="asistPresentes" style="font-weight:700; color:#22c55e;">0</div>
            <div style="font-size:.65rem; color:var(--muted);">Presentes</div>
          </div>
          <div style="background:var(--cream); padding:.6rem; border-radius:8px; text-align:center;">
            <div id="asistAusentes" style="font-weight:700; color:#ef4444;">0</div>
            <div style="font-size:.65rem; color:var(--muted);">Ausentes</div>
          </div>
          <div style="background:var(--cream); padding:.6rem; border-radius:8px; text-align:center;">
            <div id="asistTardanzas" style="font-weight:700; color:#f59e0b;">0</div>
            <div style="font-size:.65rem; color:var(--muted);">Tardanzas</div>
          </div>
          <div style="background:var(--cream); padding:.6rem; border-radius:8px; text-align:center;">
            <div id="asistJustificados" style="font-weight:700; color:#6366f1;">0</div>
            <div style="font-size:.65rem; color:var(--muted);">Justificados</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>


<!-- ═══════════════════════════════════════════════════
  VISTA DE IMPRESIÓN EXCLUSIVA (MEMBRETE OFICIAL REGLAMENTARIO PDF)
═══════════════════════════════════════════════════════ -->
<div id="printReport">
  <!-- Membrete Ministerial e Institucional Vertical -->
  <div style="display: flex; align-items: flex-start; justify-content: space-between; border-bottom: 1.5px solid #000000; padding-bottom: 8pt; margin-bottom: 12pt;">
    <div style="flex: 1; text-align: left; font-family: 'Times New Roman', Times, serif; line-height: 1.3;">
      <h4 style="margin: 0; font-size: 9.5pt; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px;">Gobierno Eclesiástico Autónomo</h4>
      <h4 style="margin: 2pt 0; font-size: 9.5pt; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px;">Asociación de Iglesias Bautistas de Venezuela</h4>
      <h3 style="margin: 2pt 0; font-size: 11pt; font-weight: bold; text-transform: uppercase; color: #000000;">Instituto Bíblico Bautista del Sur</h3>
      <p style="margin: 2pt 0 0; font-size: 8.5pt; color: #222;">Dirección de Registro y Control de Actividades Académicas</p>
      <p style="margin: 1pt 0 0; font-size: 8.5pt; color: #222; font-style: italic;">Ciudad Bolívar, Estado Bolívar</p>
    </div>
    <!-- Logo Oficial de la App (Extremo Superior Izquierdo en la UI, colocado a la derecha en la Constancia) -->
    <div style="text-align: right; padding-left: 10px;">
      <img src="assets/logo.jpg" alt="IBBS" style="width: 65pt; height: 65pt; border-radius: 50%; border: 1px solid #000000;" onerror="this.src='https://placehold.co/100x100/1a4d2e/ffffff?text=IBBS'">
    </div>
  </div>

  <!-- Título Formal del Documento Regulado -->
  <div style="text-align: center; margin-top: 10pt; margin-bottom: 15pt;">
    <h2 style="margin: 0; font-size: 13pt; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; font-family: 'Times New Roman', Times, serif;">
      Constancia de Calificaciones y Rendimiento
    </h2>
    <p style="margin: 3pt 0 0; font-size: 8pt; font-family: monospace; color: #000000;" id="printVerificationCode"></p>
  </div>

  <!-- Párrafo Legal Reglamentario (Estilo UPTeb) -->
  <p style="text-align: justify; font-size: 10pt; font-family: 'Times New Roman', Times, serif; line-height: 1.5; margin-bottom: 12pt; text-indent: 25pt;">
    Quien suscribe, Director De Registro Y Control De Actividades Académicas del <strong>Instituto Bíblico Bautista del Sur</strong>, hace constar por medio de la presente que en los archivos de esta Casa de Estudios Teológicos reposa el Expediente de Estudios del Ciudadano: <strong id="printStudentParagraphNombre" style="text-transform: uppercase;">GARCIA, OSCAR</strong>, titular de la cédula de identidad N°: <strong id="printStudentParagraphCedula">27.255.357</strong>, habiendo cursado las unidades curriculares que a continuación se especifican:
  </p>

  <!-- Tabla Académica Principal (Sin sombras, bordes de 1px negros) -->
  <table class="print-table">
    <thead>
      <tr>
        <th style="width: 12%;">Código</th>
        <th style="width: 48%; text-align: left;">Materia / Unidad Curricular</th>
        <th style="width: 25%; text-align: left;">Docente</th>
        <th style="width: 15%;">Calificación</th>
      </tr>
    </thead>
    <tbody id="printTbMaterias"></tbody>
  </table>

  <!-- Ficha de Observaciones Oficiales, Escalas y Créditos -->
  <div class="observations-box">
    <strong style="text-transform: uppercase; font-size: 8.5pt;">Observaciones Reglamentarias:</strong>
    <table style="width: 100%; font-size: 8.5pt; margin-top: 4pt; border-collapse: collapse;">
      <tr>
        <td style="width: 60%; border: none; padding: 2pt 0; vertical-align: top;">
          1.- La escala de calificaciones aplicable es del uno (1) al veinte (20).<br>
          2.- La calificación mínima aprobatoria requerida es de Quince (15) puntos.<br>
          3.- Procedencia del estudiante: <span id="printStudentCiudad" style="text-transform: uppercase; font-weight: bold;">BOLÍVAR</span>
        </td>
        <td style="width: 40%; border: none; padding: 2pt 0; vertical-align: top; border-left: 1px solid #000000; padding-left: 10pt;">
          <strong>Índice de Rendimiento Académico:</strong> <span id="printPromedioText" style="font-weight: bold;">0.0</span><br>
          <strong>U.C. Registradas:</strong> <span id="printTotalMateriasText" style="font-weight: bold;">0</span><br>
          <strong>U.C. Aprobadas:</strong> <span id="printAprobadasText" style="font-weight: bold; color: #000000;">0</span>
        </td>
      </tr>
    </table>
  </div>

  <!-- Mensaje de Cierre Oficial -->
  <p style="font-size: 9.5pt; text-align: justify; margin-top: 15pt; line-height: 1.5; font-style: italic; font-family: 'Times New Roman', Times, serif;">
    Constancia de carácter oficial y fidedigna que se expide a solicitud de la parte interesada, <span id="printFechaText" style="font-weight: bold;">en Ciudad Bolívar a los 18 días del mes de Junio de 2026</span>.
  </p>

  <!-- Firmas de Validación y Sello Institucional (Estilo UPTeb) -->
  <div style="margin-top: 50pt; display: flex; justify-content: space-between; page-break-inside: avoid; font-family: 'Times New Roman', Times, serif;">
    <div style="width: 40%; text-align: center;">
      <div style="border-top: 1px solid #000000; width: 100%; margin-bottom: 4pt;"></div>
      <p style="margin: 0; font-size: 9pt; font-weight: bold; text-transform: uppercase;">Firma del Estudiante</p>
      <p style="margin: 2pt 0 0; font-size: 8pt; color: #444;">Titular de la Cédula</p>
    </div>
    <div style="width: 45%; text-align: center;">
      <div style="border-top: 1px solid #000000; width: 100%; margin-bottom: 4pt;"></div>
      <p style="margin: 0; font-size: 9pt; font-weight: bold; text-transform: uppercase;">Dirección de Registro Académico</p>
      <p style="margin: 2pt 0 0; font-size: 8pt; color: #444;">Firma Autorizada y Sello Húmedo</p>
    </div>
  </div>

  <!-- Pie de Página Reglamentario Fijo en Impresión -->
  <div style="margin-top: 50pt; border-top: 1.5px solid #000000; padding-top: 4pt; text-align: center; font-size: 7.5pt; font-family: 'Times New Roman', Times, serif; color: #000; page-break-inside: avoid;">
    Instituto Bíblico Bautista del Sur · Calle Igualdad, Casco Histórico, Ciudad Bolívar, Estado Bolívar, Venezuela · Dirección de Registro y Control de Estudios.
  </div>
</div>


<!-- ═══════════════════════════════════════════════════
  LÓGICA JAVASCRIPT DINÁMICA
═══════════════════════════════════════════════════════ -->
<script>
let activeAlumnoId = null;

// Conversor numérico a formato de texto reglamentario (Ej: "17 DIECISIETE")
function notaALetras(num) {
  const escalaLetras = [
    "CERO", "UNO", "DOS", "TRES", "CUATRO", "CINCO", "SEIS", "SIETE", "OCHO", "NUEVE", "DIEZ",
    "ONCE", "DOCE", "TRECE", "CATORCE", "QUINCE", "DIECISÉIS", "DIECISIETE", "DIECIOCHO", "DIECINUEVE", "VEINTE"
  ];
  if (num === null || isNaN(num)) {
    return "PENDIENTE";
  }
  const valorEntero = Math.round(num);
  if (valorEntero >= 0 && valorEntero <= 20) {
    return `${valorEntero} ${escalaLetras[valorEntero]}`;
  }
  return `${num}`;
}

// Filtrar Alumnos del select dinámicamente con los campos de búsqueda
function filtrarAlumnosSelect() {
  const qNombre = (document.getElementById('searchNombre')?.value || '').toLowerCase();
  const qCedula = (document.getElementById('searchCedula')?.value || '').toLowerCase();
  const select  = document.getElementById('selectAlumno');
  const options = select.options;

  for (let i = 1; i < options.length; i++) {
    const opt = options[i];
    const txt = opt.text.toLowerCase();
    const cedula = opt.getAttribute('data-cedula').toLowerCase();

    const matchN = !qNombre || txt.includes(qNombre);
    const matchC = !qCedula || cedula.includes(qCedula);

    if (matchN && matchC) {
      opt.style.display = '';
    } else {
      opt.style.display = 'none';
    }
  }
}

// Cargar y Calcular todo el record académico del Alumno seleccionado
async function cargarRecordAlumno(id) {
  if (!id) {
    document.getElementById('panelRecord').style.display = 'none';
    document.getElementById('emptyRecord').style.display = 'block';
    document.getElementById('btnPrint').disabled = true;
    activeAlumnoId = null;
    return;
  }

  activeAlumnoId = id;
  // Activación de feedback visual de carga
  document.getElementById('emptyRecord').style.display = 'none';
  document.getElementById('panelRecord').style.display = 'block';
  document.getElementById('tbRecMaterias').innerHTML = '<tr class="empty-row"><td colspan="6"><span class="spin"></span> Cargando datos del alumno...</td></tr>';

  // Llamamos en paralelo a 'alumno_get' (para asistencias) y a 'inscripcion_alumno_materias' (para notas)
  const [resAl, resInsc] = await Promise.all([
    ajax('alumno_get', {id}),
    ajax('inscripcion_alumno_materias', {alumno_id: id})
  ]);

  if (!resAl?.ok || !resInsc?.ok) {
    toast('Error al sincronizar datos del alumno.', 'err');
    return;
  }

  const al = resAl.data;
  const { inscritas } = resInsc.data;
  const ini = (al.nombre || '?')[0].toUpperCase();

  // ═══════════════════════════════════════════════════
  // 1. POBLAR PANTALLA DE TRABAJO (DASHBOARD)
  // ═══════════════════════════════════════════════════
  document.getElementById('recAva').textContent = ini;
  document.getElementById('recAlumnoNombre').textContent = `${al.apellido}, ${al.nombre}`;
  document.getElementById('recAlumnoMeta').textContent = `CI: ${al.cedula} | Procedencia: ${al.ciudad || 'Bolívar, Venezuela'}`;

  // Calcular contadores académicos
  const conNota  = inscritas.filter(m => m.nota_final !== null);
  const aprobadas = conNota.filter(m => parseFloat(m.nota_final) >= 15).length;
  const reprobadas = conNota.filter(m => parseFloat(m.nota_final) < 15).length;
  const promedio = conNota.length ? (conNota.reduce((acc, m) => acc + parseFloat(m.nota_final), 0) / conNota.length).toFixed(2) : "0.00";
  const pendientes = inscritas.length - conNota.length;

  document.getElementById('recStatsIndicators').innerHTML = [
    ['#fff', inscritas.length, 'Materias'],
    ['#39ff14', aprobadas, 'Aprobadas'],
    ['#f87171', reprobadas, 'Reprobadas'],
    ['#ca8a04', pendientes, 'Pendientes']
  ].map(([color, val, lbl]) => `
    <div class="indicator-box">
      <div style="font-family:'DM Serif Display',serif; font-size:1.3rem; color:${color}; font-weight:bold;">${val}</div>
      <div style="font-size:.65rem; text-transform:uppercase; color:rgba(255,255,255,.45);">${lbl}</div>
    </div>
  `).join('');

  // Sincronizar tabla de materias en pantalla
  const tb = document.getElementById('tbRecMaterias');
  if (!inscritas.length) {
    tb.innerHTML = '<tr class="empty-row"><td colspan="6">El alumno no registra materias inscritas en este período.</td></tr>';
  } else {
    tb.innerHTML = inscritas.map(m => {
      const nv = m.nota_final !== null ? parseFloat(m.nota_final) : null;
      const ok = nv !== null && nv >= 15;
      const clsBadge = nv === null ? 'b-tardanza' : ok ? 'b-presente' : 'b-ausente';
      const labelBadge = nv === null ? 'Pendiente' : ok ? 'Aprobado' : 'Reprobado';
      const estadoMat = m.estado === 'culminada' ? '<span class="badge b-presente">Culminada</span>' : '<span class="badge b-tardanza">En Curso</span>';

      return `
        <tr>
          <td><strong style="font-size:.78rem; color:var(--muted);">${m.codigo}</strong></td>
          <td><div style="font-weight:700;">${m.nombre}</div></td>
          <td style="font-size:.8rem; color:var(--muted);">${m.horario || 'Sábado 20:59 - 22:59'}</td>
          <td style="text-align:center;">${estadoMat}</td>
          <td style="text-align:center; font-family:'DM Serif Display',serif; font-size:1.1rem; font-weight:bold;">${nv !== null ? nv.toFixed(1) : '—'}</td>
          <td style="text-align:center;"><span class="badge ${clsBadge}" style="font-size:.72rem;">${labelBadge}</span></td>
        </tr>
      `;
    }).join('');
  }

  // Sincronizar estadísticas de asistencia en pantalla
  const asist = al.asistencias || { presente: 0, ausente: 0, tardanza: 0, justificado: 0 };
  const totalAsist = Object.values(asist).reduce((a, b) => a + parseInt(b||0), 0);
  const pAsist = parseInt(asist.presente || 0);
  const pct = totalAsist ? Math.round((pAsist / totalAsist) * 100) : 0;

  document.getElementById('asistPercent').textContent = pct + '%';
  document.getElementById('asistBar').style.width = pct + '%';
  document.getElementById('asistPresentes').textContent = pAsist;
  document.getElementById('asistAusentes').textContent = asist.ausente || 0;
  document.getElementById('asistTardanzas').textContent = asist.tardanza || 0;
  document.getElementById('asistJustificados').textContent = asist.justificado || 0;

  // Habilitar botón de impresión
  document.getElementById('btnPrint').disabled = false;


  // ═══════════════════════════════════════════════════
  // 2. POBLAR VISTA DE IMPRESIÓN REGLAMENTARIA (ESTILO UPTEB)
  // ═══════════════════════════════════════════════════
  document.getElementById('printStudentParagraphNombre').textContent = `${al.apellido}, ${al.nombre}`;
  document.getElementById('printStudentParagraphCedula').textContent = `V-${parseInt(al.cedula).toLocaleString('es-VE')}`;
  document.getElementById('printStudentCiudad').textContent = al.ciudad || 'CIUDAD BOLÍVAR';
  
  // Sincronizar Ficha Técnica de Observaciones
  document.getElementById('printPromedioText').textContent = promedio;
  document.getElementById('printTotalMateriasText').textContent = inscritas.length;
  document.getElementById('printAprobadasText').textContent = aprobadas;

  // Fecha formal en español
  const hoy = new Date();
  const mesesArr = ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"];
  const diaLetra = hoy.getDate();
  const mesLetra = mesesArr[hoy.getMonth()];
  const anioLetra = hoy.getFullYear();

  document.getElementById('printFechaText').textContent = `en Ciudad Bolívar a los ${diaLetra} días del mes de ${mesLetra} de ${anioLetra}`;
  document.getElementById('printVerificationCode').textContent = `CÓDIGO DE VERIFICACIÓN INSTITUCIONAL: IBBS-REC-${id}-${hoy.getFullYear()}-${Math.floor(1000 + Math.random() * 9000)}`;

  // Sincronizar tabla académica de impresión (Bordes limpios de 1px)
  const printTb = document.getElementById('printTbMaterias');
  if (!inscritas.length) {
    printTb.innerHTML = '<tr><td colspan="4" style="text-align:center; font-style:italic;">No registra materias inscritas en el sistema.</td></tr>';
  } else {
    printTb.innerHTML = inscritas.map(m => {
      const nv = m.nota_final !== null ? parseFloat(m.nota_final) : null;
      const doc = m.docentes && m.docentes !== '—' ? m.docentes : 'POR ASIGNAR';
      const notaFmt = notaALetras(nv);
      
      return `
        <tr>
          <td style="text-align:center; font-weight:bold;">${m.codigo}</td>
          <td style="font-weight:bold;">${m.nombre.toUpperCase()}</td>
          <td style="text-transform:uppercase;">${doc}</td>
          <td style="text-align:center; font-weight:bold;">${notaFmt}</td>
        </tr>
      `;
    }).join('');
  }
}

// Iniciar cargando si hay un hash o parámetro en URL (Opcional, para enrutar rápido)
document.addEventListener('ibbs:ready', () => {
  filtrarAlumnosSelect();
});
</script>

<?php include __DIR__.'/layout/foot.php'; ?>