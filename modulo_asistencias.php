<?php
$page_title  = 'Asistencias';
$page_sub    = 'Registro y control de asistencias';
$active_link = 'asistencias';
include __DIR__.'/layout/head.php';
// Acceso admin o superadmin
if(!in_array($_rol,['superadmin','admin'])){
    echo '<script>window.location="index.php";</script>'; exit;
}

$con = db();
$materias = [];
$r = mysqli_query($con,"SELECT id,nombre,codigo,estado FROM materias WHERE activo=1 ORDER BY nombre");
while($f = mysqli_fetch_assoc($r)) $materias[] = $f;
mysqli_close($con);
?>

<!-- ═══════════════════════════════════════════════════
  HEADER — Tabs + stats bar
═══════════════════════════════════════════════════════ -->
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.4rem;flex-wrap:wrap;gap:.8rem;">
  <div class="ibbs-tabs">
    <button class="ibbs-tab ibbs-tab-active" data-tab="rapida">Paso de lista</button>
    <button class="ibbs-tab" data-tab="ocr">📷 Registro por Foto</button>
    <button class="ibbs-tab" data-tab="individual">Registro individual</button>
    <button class="ibbs-tab" data-tab="resumen">Resumen</button>
    <button class="ibbs-tab" data-tab="historial">Historial</button>
  </div>
  <div id="statBar" style="display:flex;gap:.5rem;flex-wrap:wrap;"></div>
</div>

<!-- ═══════════════════════════════════════════════════
  TAB 1 — PASO DE LISTA
═══════════════════════════════════════════════════════ -->
<div id="tab-rapida" class="ibbs-tab-pane active">

  <!-- Controles -->
  <div class="card" style="margin-bottom:1rem;">
    <div class="card-body" style="padding:1rem 1.2rem;">
      <div style="display:grid;grid-template-columns:2fr 1fr auto;gap:.8rem;align-items:flex-end;">
        <div class="field" style="margin:0;">
          <label>Materia</label>
          <select id="plMateria" onchange="loadPasoLista()">
            <option value="">— Seleccionar materia —</option>
            <?php foreach($materias as $m):
              $badge = $m['estado']==='culminada'?' [Culminada]':($m['estado']==='pendiente'?' [Pendiente]':'');
            ?>
            <option value="<?=$m['id']?>"><?=htmlspecialchars($m['codigo'].' · '.$m['nombre'].$badge)?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field" style="margin:0;">
          <label>Fecha</label>
          <input type="date" id="plFecha" value="<?=date('Y-m-d')?>" onchange="loadPasoLista()">
        </div>
        <div style="display:flex;gap:.5rem;align-items:flex-end;padding-bottom:0;">
          <button class="btn btn-primary" id="btnGuardarPL" onclick="guardarPasoLista()" disabled>
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13"/><polyline points="7 3 7 8 15 8"/></svg>
            Guardar
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Estado vacío -->
  <div id="plEmpty" style="padding:3rem;text-align:center;color:var(--muted);">
    Selecciona una materia y fecha para comenzar el registro.
  </div>

  <!-- Panel activo -->
  <div id="plPanel" style="display:none;">

    <!-- Barra de info + acciones rápidas -->
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.9rem;flex-wrap:wrap;gap:.6rem;">
      <div>
        <div id="plTitle" style="font-weight:700;font-size:.95rem;color:var(--ink);"></div>
        <div id="plSummary" style="font-size:.78rem;color:var(--muted);margin-top:2px;"></div>
      </div>
      <div style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;">
        <!-- Estado rápido buttons -->
        <div style="display:flex;gap:.3rem;">
          <button class="ibbs-qbtn ibbs-qbtn-p" onclick="marcarTodos('presente')">Todos presentes</button>
          <button class="ibbs-qbtn ibbs-qbtn-a" onclick="marcarTodos('ausente')">Todos ausentes</button>
        </div>
        <div style="width:1px;height:20px;background:var(--border);"></div>
        <!-- Buscador -->
        <input type="text" id="plSearch" placeholder="Buscar alumno…"
          oninput="filtrarLista(this.value)"
          style="padding:.45rem .8rem;border:1.5px solid var(--border);border-radius:8px;font-size:.82rem;font-family:'Nunito',sans-serif;background:var(--cream);color:var(--ink);outline:none;width:180px;">
      </div>
    </div>

    <!-- Leyenda -->
    <div class="pl-leyenda">
      <span class="pl-ley" style="--c:#22c55e;">Presente</span>
      <span class="pl-ley" style="--c:#ef4444;">Ausente</span>
      <span class="pl-ley" style="--c:#f59e0b;">Tardanza</span>
      <span class="pl-ley" style="--c:#6366f1;">Justificado</span>
    </div>

    <!-- Tabla de alumnos -->
    <div class="card">
      <div class="tbl-wrap" style="padding:0;">
        <table id="plTable">
          <thead>
            <tr>
              <th style="width:44px;"></th>
              <th>Alumno</th>
              <th style="text-align:center;width:80px;">Estado</th>
              <th style="text-align:center;width:200px;">Marcar</th>
              <th style="width:180px;">Observación</th>
            </tr>
          </thead>
          <tbody id="plBody"></tbody>
        </table>
      </div>
    </div>

  </div>
</div>

<!-- ═══════════════════════════════════════════════════
  TAB — REGISTRO POR FOTO (OCR)
═══════════════════════════════════════════════════════ -->
<div id="tab-ocr" class="ibbs-tab-pane" style="display:none;">
  <div class="ocr-info-box">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;margin-top:1px;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
    <div>Subí la foto de la hoja de asistencia en papel de esa clase. El sistema la lee automáticamente y precarga la tabla de abajo — pero como leer letra manuscrita nunca es 100% exacto, <strong>siempre revisá y corregí</strong> antes de guardar. Nada se guarda hasta que apretás "Guardar asistencia".</div>
  </div>

  <div class="card" style="margin-bottom:1rem;">
    <div class="card-body" style="padding:1rem 1.2rem;">
      <div style="display:grid;grid-template-columns:2fr 1fr;gap:.8rem;margin-bottom:.8rem;">
        <div class="field" style="margin:0;">
          <label>Materia</label>
          <select id="ocrMateria" onchange="ocrCambiarMateria()">
            <option value="">— Seleccionar materia —</option>
            <?php foreach($materias as $m): ?>
            <option value="<?=$m['id']?>"><?=htmlspecialchars($m['codigo'].' · '.$m['nombre'])?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field" style="margin:0;">
          <label>Fecha</label>
          <input type="date" id="ocrFecha" value="<?=date('Y-m-d')?>">
        </div>
      </div>
      <div class="field" style="margin:0 0 .8rem;">
        <label>¿Qué marca la hoja?</label>
        <div style="display:flex;gap:1.2rem;flex-wrap:wrap;">
          <label class="ocr-radio"><input type="radio" name="ocrModo" value="asistieron" checked> La hoja marca quiénes <strong>asistieron</strong></label>
          <label class="ocr-radio"><input type="radio" name="ocrModo" value="faltaron"> La hoja marca quiénes <strong>faltaron</strong></label>
        </div>
      </div>
      <div class="field" style="margin:0;">
        <label>Foto de la hoja</label>
        <input type="file" id="ocrFoto" accept="image/png,image/jpeg,image/webp" capture="environment" onchange="ocrPreviewFoto()">
        <div id="ocrFotoPreviewWrap" style="display:none;margin-top:.6rem;">
          <img id="ocrFotoPreview" style="max-width:220px;max-height:220px;border-radius:10px;border:1.5px solid var(--border);">
        </div>
      </div>
      <div style="margin-top:1rem;display:flex;gap:.6rem;align-items:center;flex-wrap:wrap;">
        <button class="btn btn-primary" id="btnOcrProcesar" onclick="ocrProcesarFoto()" disabled>
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
          Procesar foto
        </button>
        <div id="ocrProgressWrap" style="display:none;flex:1;min-width:180px;">
          <div class="pct-bar"><div class="pct-fill" id="ocrProgressBar" style="width:0%;background:var(--lime2);"></div></div>
          <div id="ocrProgressLbl" style="font-size:.72rem;color:var(--muted);margin-top:3px;">Leyendo imagen…</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Panel de revisión (aparece tras procesar) -->
  <div id="ocrReview" style="display:none;">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.7rem;flex-wrap:wrap;gap:.6rem;">
      <div>
        <div style="font-weight:700;font-size:.9rem;color:var(--ink);">Revisá y corregí antes de guardar</div>
        <div id="ocrReviewSummary" style="font-size:.78rem;color:var(--muted);margin-top:2px;"></div>
      </div>
      <button class="btn btn-secondary btn-sm" onclick="ocrVerTexto()">Ver texto detectado</button>
    </div>
    <div class="card">
      <div class="tbl-wrap" style="padding:0;">
        <table>
          <thead><tr>
            <th style="width:44px;"></th>
            <th>Alumno</th>
            <th style="text-align:center;width:110px;">¿En la hoja?</th>
            <th style="text-align:center;width:200px;">Estado final</th>
            <th style="width:160px;">Observación</th>
          </tr></thead>
          <tbody id="ocrBody"></tbody>
        </table>
      </div>
    </div>
    <div style="margin-top:1rem;">
      <button class="btn btn-primary" id="btnOcrGuardar" onclick="ocrGuardar()">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13"/><polyline points="7 3 7 8 15 8"/></svg>
        Guardar asistencia
      </button>
    </div>
  </div>

  <!-- Historial de hojas cargadas -->
  <div style="margin-top:1.6rem;">
    <div style="font-weight:700;font-size:.88rem;color:var(--ink);margin-bottom:.6rem;">Hojas cargadas recientemente</div>
    <div class="card"><div id="ocrHojasList" style="padding:1rem;"><span class="spin"></span></div></div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════
  TAB 2 — INDIVIDUAL
═══════════════════════════════════════════════════════ -->
<div id="tab-individual" class="ibbs-tab-pane" style="display:none;">
  <div class="card" style="max-width:680px;">
    <div class="card-head"><h3>Registro individual de asistencia</h3></div>
    <div class="card-body">
      <div class="form-grid g2">
        <div class="field">
          <label>Materia *</label>
          <select id="rMateria" onchange="loadPersonas()">
            <option value="">— Seleccionar materia —</option>
            <?php foreach($materias as $m): ?>
            <option value="<?=$m['id']?>"><?=htmlspecialchars($m['codigo'].' · '.$m['nombre'])?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label>Tipo</label>
          <select id="rTipo" onchange="loadPersonas()">
            <option value="alumno">Alumno</option>
            <option value="docente">Docente</option>
          </select>
        </div>
        <div class="field">
          <label id="lblPersona">Alumno *</label>
          <select id="rPersona">
            <option value="">— Selecciona materia primero —</option>
          </select>
        </div>
        <div class="field">
          <label>Fecha *</label>
          <input type="date" id="rFecha" value="<?=date('Y-m-d')?>">
        </div>
        <div class="field">
          <label>Estado *</label>
          <select id="rEstado">
            <option value="presente">Presente</option>
            <option value="ausente">Ausente</option>
            <option value="tardanza">Tardanza</option>
            <option value="justificado">Justificado</option>
          </select>
        </div>
        <div class="field">
          <label>Observación</label>
          <input id="rObs" placeholder="Opcional">
        </div>
      </div>
      <div style="margin-top:1.1rem;display:flex;gap:.6rem;">
        <button class="btn btn-primary" onclick="registrarIndividual()">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
          Registrar asistencia
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════
  TAB 3 — RESUMEN
═══════════════════════════════════════════════════════ -->
<div id="tab-resumen" class="ibbs-tab-pane" style="display:none;">

  <!-- Filtros -->
  <div class="card" style="margin-bottom:1rem;">
    <div class="card-body" style="padding:.9rem 1.2rem;">
      <div style="display:flex;gap:.8rem;flex-wrap:wrap;align-items:flex-end;">
        <div class="field" style="margin:0;flex:1;min-width:200px;">
          <label>Materia</label>
          <select id="resMateria" onchange="loadResumen()">
            <option value="">— Todas las materias —</option>
            <?php foreach($materias as $m): ?>
            <option value="<?=$m['id']?>"><?=htmlspecialchars($m['nombre'])?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field" style="margin:0;">
          <label>Período</label>
          <select id="resPeriodo" onchange="loadResumen()">
            <option value="">Total histórico</option>
            <option value="mes">Este mes</option>
            <option value="semana">Esta semana</option>
          </select>
        </div>
      </div>
    </div>
  </div>

  <!-- Cards globales -->
  <div id="resCards" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:.7rem;margin-bottom:1.2rem;"></div>

  <!-- Tabla por alumno -->
  <div class="card">
    <div class="card-head"><h3>Detalle por alumno</h3></div>
    <div class="tbl-wrap">
      <table>
        <thead><tr>
          <th>Alumno</th>
          <th>Materia</th>
          <th style="text-align:center;">P</th>
          <th style="text-align:center;">A</th>
          <th style="text-align:center;">T</th>
          <th style="text-align:center;">J</th>
          <th style="text-align:center;width:120px;">Asistencia</th>
        </tr></thead>
        <tbody id="tbResumen">
          <tr class="empty-row"><td colspan="7">Selecciona una materia para ver el resumen.</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════
  TAB 4 — HISTORIAL
═══════════════════════════════════════════════════════ -->
<div id="tab-historial" class="ibbs-tab-pane" style="display:none;">
  <div class="card">
    <div class="card-head" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.6rem;">
      <h3>Historial de asistencias</h3>
      <div style="display:flex;gap:.4rem;flex-wrap:wrap;align-items:center;">
        <select id="filtHMateria" onchange="loadHistorial()" class="ibbs-filter-sel">
          <option value="0">Todas las materias</option>
          <?php foreach($materias as $m): ?>
          <option value="<?=$m['id']?>"><?=htmlspecialchars($m['nombre'])?></option>
          <?php endforeach; ?>
        </select>
        <select id="filtHTipo" onchange="loadHistorial()" class="ibbs-filter-sel">
          <option value="">Alumno / Docente</option>
          <option value="alumno">Alumnos</option>
          <option value="docente">Docentes</option>
        </select>
        <select id="filtHEstado" onchange="loadHistorial()" class="ibbs-filter-sel">
          <option value="">Todos los estados</option>
          <option value="presente">Presente</option>
          <option value="ausente">Ausente</option>
          <option value="tardanza">Tardanza</option>
          <option value="justificado">Justificado</option>
        </select>
        <input type="date" id="filtHFecha" onchange="loadHistorial()" class="ibbs-filter-sel">
        <button class="btn btn-secondary btn-sm" onclick="limpiarHist()">Limpiar</button>
      </div>
    </div>
    <div class="tbl-wrap">
      <table>
        <thead><tr>
          <th>Fecha</th>
          <th>Persona</th>
          <th>Cédula</th>
          <th>Materia</th>
          <th style="text-align:center;">Estado</th>
          <th>Observación</th>
          <th style="text-align:center;width:64px;"></th>
        </tr></thead>
        <tbody id="tbHist">
          <tr class="empty-row"><td colspan="7"><span class="spin"></span></td></tr>
        </tbody>
      </table>
    </div>
    <div id="histPag" style="display:flex;justify-content:center;gap:.3rem;padding:.8rem 1rem 0;flex-wrap:wrap;"></div>
  </div>
</div>

<!-- Modal editar -->
<div id="mEditAsist" class="modal-bg" style="display:none;">
  <div class="modal" style="max-width:380px;">
    <div class="modal-head">
      <h3>Editar registro</h3>
      <button class="modal-close" onclick="closeModal('mEditAsist')">&#10005;</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="eAId">
      <div class="field">
        <label>Estado</label>
        <select id="eAEstado">
          <option value="presente">Presente</option>
          <option value="ausente">Ausente</option>
          <option value="tardanza">Tardanza</option>
          <option value="justificado">Justificado</option>
        </select>
      </div>
      <div class="field">
        <label>Observación</label>
        <input id="eAObs" placeholder="Opcional">
      </div>
    </div>
    <div class="modal-foot">
      <button class="btn btn-secondary" onclick="closeModal('mEditAsist')">Cancelar</button>
      <button class="btn btn-primary" onclick="guardarEditAsist()">Guardar cambio</button>
    </div>
  </div>
</div>

<style>
/* ── Tabs uniformes ───────────────────────────────────────── */
.ibbs-tabs {
  display:flex;gap:.2rem;
  background:var(--cream);border:1px solid var(--border);
  border-radius:10px;padding:.22rem;
}
.ibbs-tab {
  padding:.5rem 1.05rem;border:none;border-radius:8px;
  cursor:pointer;font-size:.82rem;font-weight:600;
  font-family:'Nunito',sans-serif;
  background:transparent;color:var(--muted);
  transition:all .18s;white-space:nowrap;
}
.ibbs-tab:hover:not(.ibbs-tab-active){ color:var(--ink); }
.ibbs-tab-active {
  background:var(--ink);color:var(--lime);
  box-shadow:0 1px 4px rgba(0,0,0,.12);
}

/* ── Quick action buttons ─────────────────────────────────── */
.ibbs-qbtn {
  padding:.38rem .75rem;border-radius:7px;border:1px solid var(--border);
  font-size:.76rem;font-weight:600;cursor:pointer;
  font-family:'Nunito',sans-serif;background:var(--cream);
  color:var(--muted);transition:all .15s;
}
.ibbs-qbtn:hover { color:var(--ink); }
.ibbs-qbtn-p:hover { background:#f0fdf4;border-color:#86efac;color:#15803d; }
.ibbs-qbtn-a:hover { background:#fef2f2;border-color:#fca5a5;color:#dc2626; }

/* ── Leyenda ──────────────────────────────────────────────── */
.pl-leyenda {
  display:flex;gap:.9rem;flex-wrap:wrap;margin-bottom:.7rem;
}
.pl-ley {
  display:flex;align-items:center;gap:.35rem;
  font-size:.74rem;color:var(--muted);
}
.pl-ley::before {
  content:'';width:8px;height:8px;border-radius:50%;
  background:var(--c);flex-shrink:0;
}

/* ── Fila de alumno en paso de lista ─────────────────────── */
.pl-row {
  transition:background .12s;
}
.pl-row td { vertical-align:middle; }
.pl-row.pl-presente td:first-child { border-left:3px solid #22c55e; }
.pl-row.pl-ausente   td:first-child { border-left:3px solid #ef4444; }
.pl-row.pl-tardanza  td:first-child { border-left:3px solid #f59e0b; }
.pl-row.pl-justificado td:first-child { border-left:3px solid #6366f1; }

/* Avatar circular */
.pl-ava {
  width:32px;height:32px;border-radius:50%;
  overflow:hidden;display:flex;align-items:center;justify-content:center;
  flex-shrink:0;font-size:.82rem;font-weight:700;
  background:var(--ink);color:var(--lime);
  font-family:'Nunito',sans-serif;
}
.pl-ava img {
  width:100%;height:100%;object-fit:cover;
  border-radius:50%;display:block;
}

/* Estado badge en tabla */
.est-dot {
  display:inline-flex;align-items:center;gap:.35rem;
  font-size:.76rem;font-weight:700;
}
.est-dot::before {
  content:'';width:8px;height:8px;border-radius:50%;
  background:var(--dc);flex-shrink:0;
}

/* Botones de marcar en tabla — texto limpio sin emoji */
.mark-btn {
  padding:.22rem .55rem;border-radius:5px;
  border:1px solid var(--border);
  background:transparent;cursor:pointer;
  font-size:.7rem;font-weight:700;
  font-family:'Nunito',sans-serif;
  color:var(--muted);text-transform:uppercase;
  letter-spacing:.3px;transition:all .12s;
}
.mark-btn:hover { background:var(--cream); }
.mark-btn.mk-p { background:#22c55e;color:#fff;border-color:#22c55e; }
.mark-btn.mk-a { background:#ef4444;color:#fff;border-color:#ef4444; }
.mark-btn.mk-t { background:#f59e0b;color:#fff;border-color:#f59e0b; }
.mark-btn.mk-j { background:#6366f1;color:#fff;border-color:#6366f1; }

/* Input obs en tabla */
.obs-input {
  width:100%;padding:.3rem .55rem;
  border:1px solid var(--border);border-radius:6px;
  font-size:.76rem;font-family:'Nunito',sans-serif;
  background:var(--cream);color:var(--ink);outline:none;
}
.obs-input:focus { border-color:var(--lime2);box-shadow:0 0 0 2px rgba(46,204,16,.1); }

/* Barra % */
.pct-bar { height:5px;background:var(--border);border-radius:3px;margin-top:3px;overflow:hidden; }
.pct-fill { height:100%;border-radius:3px;transition:width .4s; }

/* Filter selects */
.ibbs-filter-sel {
  padding:.45rem .8rem;border:1.5px solid var(--border);
  border-radius:8px;font-size:.81rem;
  background:var(--cream);color:var(--ink);outline:none;
  font-family:'Nunito',sans-serif;
}

/* Stat bar chips */
.stat-chip {
  display:flex;align-items:center;gap:.35rem;
  padding:.3rem .7rem;background:var(--cream);
  border:1px solid var(--border);border-radius:20px;
  font-size:.75rem;font-weight:600;color:var(--muted);
}
.stat-chip strong { color:var(--ink); }

/* ── Registro por Foto (OCR) ──────────────────────────────── */
.ocr-info-box {
  display:flex;gap:.7rem;align-items:flex-start;
  background:#eff6ff;border:1.5px solid #bfdbfe;color:#1e3a8a;
  border-radius:10px;padding:.85rem 1rem;margin-bottom:1rem;
  font-size:.82rem;line-height:1.5;
}
.ocr-radio {
  display:flex;align-items:center;gap:.4rem;
  font-size:.82rem;color:var(--ink);cursor:pointer;font-weight:500;
}
.ocr-detect-yes { color:#15803d;font-weight:700;font-size:.76rem; }
.ocr-detect-no  { color:#b45309;font-weight:700;font-size:.76rem; }
.ocr-hoja-card {
  display:flex;gap:.8rem;align-items:center;padding:.7rem .9rem;
  border-bottom:1px solid var(--border);
}
.ocr-hoja-card:last-child{ border-bottom:none; }
.ocr-hoja-thumb {
  width:48px;height:48px;border-radius:8px;object-fit:cover;
  border:1px solid var(--border);flex-shrink:0;cursor:pointer;
}
</style>

<script>
// ════════════════════════════════════════════════════════
// TABS
// ════════════════════════════════════════════════════════
document.querySelectorAll('.ibbs-tab').forEach(btn => {
  btn.addEventListener('click', () => {
    const tab = btn.dataset.tab;
    document.querySelectorAll('.ibbs-tab').forEach(b => b.classList.remove('ibbs-tab-active'));
    document.querySelectorAll('.ibbs-tab-pane').forEach(p => p.style.display='none');
    btn.classList.add('ibbs-tab-active');
    document.getElementById('tab-'+tab).style.display = 'block';
    if (tab==='historial') loadHistorial();
    if (tab==='resumen')   loadResumen();
    if (tab==='ocr')       ocrCargarHojas();
  });
});

// ════════════════════════════════════════════════════════
// GLOBAL STATS (topbar chips)
// ════════════════════════════════════════════════════════
async function loadStatChips() {
  const d = await ajax('asistencia_resumen', {materia_id:0, periodo:''});
  if (!d?.ok) return;
  const g = d.global||{};
  const total = (g.presente||0)+(g.ausente||0)+(g.tardanza||0)+(g.justificado||0);
  const pct   = total ? Math.round(g.presente/total*100) : 0;
  const pctC  = pct>=80?'#15803d':(pct>=60?'#d97706':'#dc2626');
  document.getElementById('statBar').innerHTML = [
    {lbl:'Presentes', val:g.presente||0, c:'#22c55e'},
    {lbl:'Ausentes',  val:g.ausente||0,  c:'#ef4444'},
    {lbl:'Tardanzas', val:g.tardanza||0, c:'#f59e0b'},
    {lbl:'% Global',  val:pct+'%',       c:pctC},
  ].map(i=>`<div class="stat-chip"><span style="width:7px;height:7px;border-radius:50%;background:${i.c};"></span>${i.lbl}: <strong>${i.val}</strong></div>`).join('');
}

// ════════════════════════════════════════════════════════
// TAB 1 — PASO DE LISTA
// ════════════════════════════════════════════════════════
let _pl = [];
const EST = ['presente','ausente','tardanza','justificado'];
const EST_SHORT = {presente:'Pres',ausente:'Aus',tardanza:'Tard',justificado:'Just'};
const EST_COL   = {presente:'#22c55e',ausente:'#ef4444',tardanza:'#f59e0b',justificado:'#6366f1'};
const EST_MK    = {presente:'mk-p',ausente:'mk-a',tardanza:'mk-t',justificado:'mk-j'};

async function loadPasoLista() {
  const mid   = document.getElementById('plMateria').value;
  const fecha = document.getElementById('plFecha').value;
  const btnG  = document.getElementById('btnGuardarPL');

  if (!mid) {
    document.getElementById('plEmpty').style.display  = 'block';
    document.getElementById('plPanel').style.display  = 'none';
    btnG.disabled = true; return;
  }

  document.getElementById('plEmpty').style.display  = 'none';
  document.getElementById('plPanel').style.display  = 'block';
  document.getElementById('plBody').innerHTML = '<tr class="empty-row"><td colspan="5"><span class="spin"></span></td></tr>';
  btnG.disabled = true;

  const [md, exist] = await Promise.all([
    ajax('materia_get', {id: mid}),
    ajax('asistencia_list', {materia_id: mid, tipo:'alumno', fecha})
  ]);

  const matNombre = md?.data?.nombre || '';
  const alumnos   = md?.data?.alumnos || [];
  const existMap  = {};
  (exist?.data||[]).forEach(r => { if(r.alumno_id) existMap[r.alumno_id] = {estado:r.estado, obs:r.observacion||'', id:r.id}; });

  _pl = alumnos.map(a => ({
    ...a,
    estado: existMap[a.id]?.estado || 'presente',
    obs:    existMap[a.id]?.obs    || '',
    aid:    existMap[a.id]?.id     || null,
    visible: true,
  }));

  const fd = new Date(fecha+'T00:00:00');
  const fdFmt = fd.toLocaleDateString('es-VE',{weekday:'long',year:'numeric',month:'long',day:'numeric'});
  document.getElementById('plTitle').textContent = `${matNombre} · ${fdFmt}`;
  document.getElementById('plSearch').value = '';

  renderPasoLista();
  updateSummary();
  btnG.disabled = !alumnos.length;
}

function avatarHtml(a) {
  const ini = ((a.nombre||' ')[0]).toUpperCase();
  if (a.foto) {
    return `<div class="pl-ava"><img src="${a.foto}" alt="${ini}" onerror="this.parentElement.innerHTML='${ini}'"></div>`;
  }
  const colors = ['#1a4d2e','#1e5c36','#256035','#2d7a45','#166534'];
  const ci = (a.cedula||ini).charCodeAt(0) % colors.length;
  return `<div class="pl-ava" style="background:${colors[ci]};">${ini}</div>`;
}

function renderPasoLista(lista) {
  lista = lista !== undefined ? lista : _pl;
  const tb = document.getElementById('plBody');
  if (!lista.length) {
    tb.innerHTML = '<tr class="empty-row"><td colspan="5">' +
      (_pl.length ? 'No se encontraron resultados.' : 'Esta materia no tiene alumnos inscritos.') +
      '</td></tr>';
    return;
  }
  tb.innerHTML = lista.map((a, vi) => {
    const ri = _pl.indexOf(a);
    const col = EST_COL[a.estado];
    return `<tr class="pl-row pl-${a.estado}" id="plrow-${ri}">
      <td style="padding:.55rem .7rem;width:44px;">${avatarHtml(a)}</td>
      <td style="padding:.55rem .8rem;">
        <div style="font-weight:700;font-size:.85rem;">${a.apellido||''}, ${a.nombre||''}</div>
        <div style="font-size:.72rem;color:var(--muted);">CI: ${a.cedula||'—'}</div>
      </td>
      <td style="text-align:center;padding:.55rem .5rem;">
        <span class="est-dot" style="--dc:${col};">${a.estado}</span>
      </td>
      <td style="text-align:center;padding:.55rem .5rem;">
        <div style="display:flex;gap:.2rem;justify-content:center;">
          ${EST.map(e=>`<button class="mark-btn ${a.estado===e?EST_MK[e]:''}" onclick="setEstado(${ri},'${e}')">${EST_SHORT[e]}</button>`).join('')}
        </div>
      </td>
      <td style="padding:.55rem .7rem;">
        <input class="obs-input" value="${a.obs}" placeholder="Observación…"
          onchange="_pl[${ri}].obs=this.value" oninput="_pl[${ri}].obs=this.value">
      </td>
    </tr>`;
  }).join('');
}

function filtrarLista(q) {
  const ql = q.toLowerCase();
  const f = !ql ? _pl : _pl.filter(a =>
    `${a.nombre} ${a.apellido} ${a.cedula}`.toLowerCase().includes(ql)
  );
  renderPasoLista(f);
}

function setEstado(ri, estado) {
  _pl[ri].estado = estado;
  const row = document.getElementById('plrow-'+ri);
  if (!row) return;
  row.className = `pl-row pl-${estado}`;
  const col = EST_COL[estado];
  row.querySelector('.est-dot').style.setProperty('--dc', col);
  row.querySelector('.est-dot').textContent = estado;
  row.querySelectorAll('.mark-btn').forEach((btn, j) => {
    const e = EST[j];
    btn.className = `mark-btn ${estado===e ? EST_MK[e] : ''}`;
  });
  updateSummary();
}

function marcarTodos(estado) {
  _pl.forEach((_, ri) => setEstado(ri, estado));
}

function updateSummary() {
  const c = {presente:0,ausente:0,tardanza:0,justificado:0};
  _pl.forEach(a => c[a.estado] = (c[a.estado]||0)+1);
  const total = _pl.length;
  const pct   = total ? Math.round(c.presente/total*100) : 0;
  const pctC  = pct>=80?'#15803d':(pct>=60?'#d97706':'#dc2626');
  document.getElementById('plSummary').innerHTML =
    `${total} alumnos &nbsp;·&nbsp; ` +
    `<span style="color:#22c55e;font-weight:600;">${c.presente} presentes</span> · ` +
    `<span style="color:#ef4444;font-weight:600;">${c.ausente} ausentes</span> · ` +
    `<span style="color:#f59e0b;font-weight:600;">${c.tardanza} tardanzas</span> · ` +
    `<span style="color:#6366f1;font-weight:600;">${c.justificado} justificados</span> · ` +
    `<span style="color:${pctC};font-weight:700;">${pct}% asistencia</span>`;
}

async function guardarPasoLista() {
  const mid   = document.getElementById('plMateria').value;
  const fecha = document.getElementById('plFecha').value;
  if (!mid || !_pl.length) return;
  const btn = document.getElementById('btnGuardarPL');
  btn.disabled = true;
  btn.innerHTML = '<span class="spin"></span> Guardando…';
  // Un solo request para toda la materia — antes era un fetch() por
  // alumno (N round-trips secuenciales); con cursos numerosos eso
  // tardaba varios segundos y multiplicaba la carga del servidor.
  const registros = _pl.map(a => ({persona_id:a.id, estado:a.estado, observacion:a.obs||''}));
  const d = await ajax('asistencia_register_lote', {materia_id: mid, tipo:'alumno', fecha, registros: JSON.stringify(registros)});
  btn.disabled = false;
  btn.innerHTML = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13"/><polyline points="7 3 7 8 15 8"/></svg> Guardar`;
  toast(d?.ok ? d.msg : (d?.msg||'Error al guardar.'), d?.ok?'ok':'err');
  loadStatChips();
}

// ════════════════════════════════════════════════════════
// TAB 2 — INDIVIDUAL
// ════════════════════════════════════════════════════════
async function loadPersonas() {
  const mid  = document.getElementById('rMateria').value;
  const tipo = document.getElementById('rTipo').value;
  document.getElementById('lblPersona').textContent = tipo==='alumno' ? 'Alumno *' : 'Docente *';
  const sel = document.getElementById('rPersona');
  if (!mid) { sel.innerHTML='<option value="">— Selecciona materia primero —</option>'; return; }
  sel.innerHTML = '<option value="">Cargando…</option>';
  const md = await ajax('materia_get', {id: mid});
  const list = tipo==='alumno' ? (md?.data?.alumnos||[]) : (md?.data?.docentes||[]);
  sel.innerHTML = `<option value="">— Seleccionar ${tipo} —</option>` +
    list.map(p=>`<option value="${p.id}">${p.apellido||''}, ${p.nombre||''}</option>`).join('');
}

async function registrarIndividual() {
  const mid    = document.getElementById('rMateria').value;
  const tipo   = document.getElementById('rTipo').value;
  const pid    = document.getElementById('rPersona').value;
  const fecha  = document.getElementById('rFecha').value;
  const estado = document.getElementById('rEstado').value;
  const obs    = document.getElementById('rObs').value;
  if (!mid||!pid||!fecha) { toast('Completa materia, persona y fecha.','err'); return; }
  const d = await ajax('asistencia_register',{materia_id:mid,tipo,persona_id:pid,fecha,estado,observacion:obs});
  if (d?.ok) { toast(d.msg); document.getElementById('rObs').value=''; loadStatChips(); }
  else toast(d?.msg||'Error al registrar','err');
}

// ════════════════════════════════════════════════════════
// TAB 3 — RESUMEN
// ════════════════════════════════════════════════════════
async function loadResumen() {
  const mid     = document.getElementById('resMateria').value;
  const periodo = document.getElementById('resPeriodo').value;
  const d = await ajax('asistencia_resumen',{materia_id:mid||0, periodo});
  if (!d?.ok) return;
  const g = d.global||{};
  const total = (g.presente||0)+(g.ausente||0)+(g.tardanza||0)+(g.justificado||0);
  const pct   = total ? Math.round(g.presente/total*100) : 0;
  const pctC  = pct>=80?'#22c55e':(pct>=60?'#f59e0b':'#ef4444');
  document.getElementById('resCards').innerHTML = [
    {l:'Total registros',val:total,       c:'var(--ink)'},
    {l:'Presentes',      val:g.presente||0,c:'#22c55e'},
    {l:'Ausentes',       val:g.ausente||0, c:'#ef4444'},
    {l:'Tardanzas',      val:g.tardanza||0,c:'#f59e0b'},
    {l:'Justificados',   val:g.justificado||0,c:'#6366f1'},
    {l:'% Asistencia',   val:pct+'%',     c:pctC},
  ].map(i=>`
    <div style="background:var(--paper);border:1px solid var(--border);border-radius:10px;padding:.85rem 1rem;">
      <div style="font-size:1.55rem;font-weight:800;color:${i.c};line-height:1;">${i.val}</div>
      <div style="font-size:.64rem;text-transform:uppercase;letter-spacing:.8px;color:var(--muted);margin-top:3px;">${i.l}</div>
    </div>`).join('');
  const tb = document.getElementById('tbResumen');
  const rows = d.data||[];
  if (!rows.length) {
    tb.innerHTML='<tr class="empty-row"><td colspan="7">Sin registros. Selecciona una materia.</td></tr>';return;
  }
  tb.innerHTML = rows.map(r=>{
    const t=(r.presentes||0)+(r.ausentes||0)+(r.tardanzas||0)+(r.justificados||0);
    const p=t?Math.round(r.presentes/t*100):0;
    const pc=p>=80?'#22c55e':(p>=60?'#f59e0b':'#ef4444');
    return `<tr>
      <td><strong>${r.alumno}</strong></td>
      <td style="font-size:.8rem;color:var(--muted);">${r.materia}</td>
      <td style="text-align:center;font-weight:700;color:#22c55e;">${r.presentes||0}</td>
      <td style="text-align:center;font-weight:700;color:#ef4444;">${r.ausentes||0}</td>
      <td style="text-align:center;font-weight:700;color:#f59e0b;">${r.tardanzas||0}</td>
      <td style="text-align:center;font-weight:700;color:#6366f1;">${r.justificados||0}</td>
      <td style="min-width:100px;">
        <div style="font-size:.8rem;font-weight:700;color:${pc};">${p}%</div>
        <div class="pct-bar"><div class="pct-fill" style="width:${p}%;background:${pc};"></div></div>
      </td>
    </tr>`;
  }).join('');
}

// ════════════════════════════════════════════════════════
// TAB 4 — HISTORIAL
// ════════════════════════════════════════════════════════
let _hData=[], _hPage=1;
const H_PER=30;

function limpiarHist() {
  ['filtHFecha','filtHTipo','filtHEstado'].forEach(id=>document.getElementById(id).value='');
  document.getElementById('filtHMateria').value='0';
  loadHistorial();
}

async function loadHistorial() {
  _hPage=1;
  const mid    = document.getElementById('filtHMateria').value;
  const tipo   = document.getElementById('filtHTipo').value;
  const fecha  = document.getElementById('filtHFecha').value;
  const estado = document.getElementById('filtHEstado').value;
  document.getElementById('tbHist').innerHTML='<tr class="empty-row"><td colspan="7"><span class="spin"></span></td></tr>';
  const d = await ajax('asistencia_list',{materia_id:mid,tipo,fecha,estado});
  _hData = d?.data||[];
  renderHist();
}

function renderHist() {
  const tb  = document.getElementById('tbHist');
  const pag = document.getElementById('histPag');
  const slice = _hData.slice((_hPage-1)*H_PER, _hPage*H_PER);
  const bMap = {presente:'b-presente',ausente:'b-ausente',tardanza:'b-tardanza',justificado:'b-justificado'};
  if (!slice.length) {
    tb.innerHTML='<tr class="empty-row"><td colspan="7">Sin registros para los filtros aplicados.</td></tr>';
    pag.innerHTML='';return;
  }
  tb.innerHTML = slice.map(r=>`
    <tr>
      <td style="white-space:nowrap;font-size:.82rem;">${r.fecha||'—'}</td>
      <td><strong style="font-size:.84rem;">${r.persona||'—'}</strong></td>
      <td style="font-size:.78rem;color:var(--muted);">${r.cedula||'—'}</td>
      <td style="font-size:.8rem;">${r.materia||'—'}</td>
      <td style="text-align:center;"><span class="badge ${bMap[r.estado]||''}">${r.estado}</span></td>
      <td style="font-size:.78rem;color:var(--muted);">${r.observacion||'—'}</td>
      <td style="text-align:center;">
        <button class="btn btn-sm btn-secondary" onclick="abrirEdit(${r.id},'${r.estado}','${(r.observacion||'').replace(/'/g,'\\\'')}')">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
        </button>
      </td>
    </tr>`).join('');
  const tp = Math.ceil(_hData.length/H_PER);
  pag.innerHTML = tp<=1?'':Array.from({length:tp},(_,i)=>{const p=i+1;
    return `<button onclick="histPg(${p})" style="padding:.25rem .55rem;border:1px solid ${p===_hPage?'var(--ink)':'var(--border)'};border-radius:5px;background:${p===_hPage?'var(--ink)':'transparent'};color:${p===_hPage?'var(--lime)':'var(--ink)'};cursor:pointer;font-size:.75rem;font-family:'Nunito',sans-serif;">${p}</button>`;
  }).join('');
}

function histPg(p){_hPage=p;renderHist();}

function abrirEdit(id,estado,obs){
  document.getElementById('eAId').value=id;
  document.getElementById('eAEstado').value=estado;
  document.getElementById('eAObs').value=obs;
  openModal('mEditAsist');
}
async function guardarEditAsist(){
  const d=await ajax('asistencia_editar',{id:document.getElementById('eAId').value,estado:document.getElementById('eAEstado').value,observacion:document.getElementById('eAObs').value});
  if(d?.ok){toast(d.msg);closeModal('mEditAsist');loadHistorial();}
  else toast(d?.msg||'Error','err');
}

// ════════════════════════════════════════════════════════
// TAB — REGISTRO POR FOTO (OCR con Tesseract.js)
// ════════════════════════════════════════════════════════
// El OCR corre 100% en el navegador (nada de la imagen se manda a
// ningún servicio externo hasta que el propio docente/admin decide
// guardar) — Tesseract.js se carga on-demand la primera vez que se
// procesa una foto, así no pesa nada para quien nunca usa esta pestaña.
let _ocrRoster = [];
let _ocrTextoCrudo = '';
let _tesseractCargado = false;
let _ocrFotoFinal = null; // versión redimensionada — la misma que se lee con OCR y la que se sube

function ocrCambiarMateria() {
  document.getElementById('ocrReview').style.display = 'none';
  document.getElementById('btnOcrProcesar').disabled = !(document.getElementById('ocrMateria').value && _ocrFotoFinal);
}

// Las fotos que salen directo de la cámara de un celular pueden pesar
// varios MB a resolución completa — subir eso con una red móvil lenta
// y hacerle OCR en el navegador de un teléfono de gama baja es una
// receta para que "la página no funcione". Se reduce a un ancho
// razonable para leer texto (no hace falta más para OCR) antes de
// procesar o subir nada.
function ocrRedimensionarImagen(file, maxDim=1800, calidad=0.85) {
  return new Promise((resolve) => {
    const img = new Image();
    const url = URL.createObjectURL(file);
    img.onload = () => {
      let {width, height} = img;
      if (width > maxDim || height > maxDim) {
        const ratio = Math.min(maxDim/width, maxDim/height);
        width = Math.round(width*ratio); height = Math.round(height*ratio);
      }
      const canvas = document.createElement('canvas');
      canvas.width = width; canvas.height = height;
      canvas.getContext('2d').drawImage(img, 0, 0, width, height);
      canvas.toBlob(blob => {
        URL.revokeObjectURL(url);
        resolve(blob ? new File([blob], 'hoja.jpg', {type:'image/jpeg'}) : file);
      }, 'image/jpeg', calidad);
    };
    img.onerror = () => { URL.revokeObjectURL(url); resolve(file); };
    img.src = url;
  });
}

async function ocrPreviewFoto() {
  const input = document.getElementById('ocrFoto');
  const wrap = document.getElementById('ocrFotoPreviewWrap');
  document.getElementById('ocrReview').style.display = 'none';
  if (!input.files.length) { wrap.style.display = 'none'; _ocrFotoFinal = null; document.getElementById('btnOcrProcesar').disabled = true; return; }
  document.getElementById('btnOcrProcesar').disabled = true;
  _ocrFotoFinal = await ocrRedimensionarImagen(input.files[0]);
  document.getElementById('ocrFotoPreview').src = URL.createObjectURL(_ocrFotoFinal);
  wrap.style.display = 'block';
  document.getElementById('btnOcrProcesar').disabled = !document.getElementById('ocrMateria').value;
}

async function _ocrCargarTesseract() {
  if (_tesseractCargado) return;
  await new Promise((resolve, reject) => {
    const s = document.createElement('script');
    s.src = 'https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js';
    s.onload = resolve; s.onerror = () => reject(new Error('No se pudo cargar el lector de imágenes (revisá tu conexión a internet).'));
    document.head.appendChild(s);
  });
  _tesseractCargado = true;
}

// Normaliza (minúsculas, sin tildes, sin símbolos) para comparar texto OCR contra nombres.
function ocrNormalizar(s) {
  return (s||'').toString().toLowerCase()
    .normalize('NFD').replace(/[\u0300-\u036f]/g,'')
    .replace(/[^a-z0-9\s]/g,' ')
    .replace(/\s+/g,' ').trim();
}
function ocrLevenshtein(a,b){
  const m=a.length, n=b.length;
  if(!m) return n; if(!n) return m;
  const dp=[]; for(let i=0;i<=m;i++) dp.push([i,...new Array(n).fill(0)]);
  for(let j=0;j<=n;j++) dp[0][j]=j;
  for(let i=1;i<=m;i++) for(let j=1;j<=n;j++)
    dp[i][j] = a[i-1]===b[j-1] ? dp[i-1][j-1] : 1+Math.min(dp[i-1][j-1],dp[i-1][j],dp[i][j-1]);
  return dp[m][n];
}
// ¿Aparece este alumno mencionado en el texto que leyó el OCR?
// Prioriza cédula (OCR suele leer mejor dígitos impresos que letra
// manuscrita) y, si no, busca el apellido o el nombre como palabra
// suelta (con tolerancia a 1-2 letras mal leídas según el largo).
function ocrAlumnoDetectado(alumno, textoNorm, digitosTexto) {
  const cedula = (alumno.cedula||'').replace(/\D/g,'');
  if (cedula && cedula.length>=6 && digitosTexto.includes(cedula)) return true;
  const tokens = textoNorm.split(' ').filter(t=>t.length>=3);
  const revisar = (palabra) => {
    if (!palabra || palabra.length<3) return false;
    const tolerancia = palabra.length>=7 ? 2 : (palabra.length>=4 ? 1 : 0);
    return tokens.some(t => t===palabra || ocrLevenshtein(t,palabra)<=tolerancia);
  };
  const apellidoOk = ocrNormalizar(alumno.apellido).split(' ').some(revisar);
  const nombreOk   = ocrNormalizar(alumno.nombre).split(' ').some(revisar);
  return apellidoOk || nombreOk;
}

async function ocrProcesarFoto() {
  const mid = document.getElementById('ocrMateria').value;
  const file = _ocrFotoFinal;
  if (!mid) { Ibbs.warn('Elegí una materia primero.'); return; }
  if (!file) { Ibbs.warn('Adjuntá la foto de la hoja.'); return; }

  const btn = document.getElementById('btnOcrProcesar');
  const progWrap = document.getElementById('ocrProgressWrap');
  const progBar  = document.getElementById('ocrProgressBar');
  const progLbl  = document.getElementById('ocrProgressLbl');
  btn.disabled = true; progWrap.style.display = 'block'; progBar.style.width = '0%';
  progLbl.textContent = 'Cargando el lector de imágenes…';

  try {
    await _ocrCargarTesseract();

    const md = await ajax('materia_get', {id: mid});
    _ocrRoster = md?.data?.alumnos || [];
    if (!_ocrRoster.length) { Ibbs.warn('Esta materia no tiene alumnos inscritos.'); btn.disabled=false; progWrap.style.display='none'; return; }

    progLbl.textContent = 'Leyendo la imagen (puede tardar unos segundos)…';
    const { data: { text } } = await Tesseract.recognize(file, 'spa', {
      logger: m => {
        if (m.status === 'recognizing text') {
          const pct = Math.round((m.progress||0)*100);
          progBar.style.width = pct+'%';
          progLbl.textContent = `Leyendo la imagen… ${pct}%`;
        }
      }
    });
    _ocrTextoCrudo = text || '';

    const textoNorm = ocrNormalizar(_ocrTextoCrudo);
    const digitosTexto = (_ocrTextoCrudo.match(/\d+/g)||[]).join('');
    const modo = document.querySelector('input[name="ocrModo"]:checked').value;

    const filas = _ocrRoster.map(a => {
      const detectado = ocrAlumnoDetectado(a, textoNorm, digitosTexto);
      const estado = modo === 'asistieron' ? (detectado ? 'presente' : 'ausente') : (detectado ? 'ausente' : 'presente');
      return { ...a, detectado, estado, obs: '' };
    });

    ocrRenderReview(filas);
    document.getElementById('ocrReview').style.display = 'block';
    document.getElementById('ocrReview').scrollIntoView({behavior:'smooth', block:'start'});
  } catch (e) {
    console.error(e);
    Ibbs.error(e.message || 'No se pudo leer la imagen. Probá con una foto más clara y bien iluminada.');
  }
  btn.disabled = false; progWrap.style.display = 'none';
}

let _ocrFilas = [];
function ocrRenderReview(filas) {
  _ocrFilas = filas;
  const detectados = filas.filter(f=>f.detectado).length;
  document.getElementById('ocrReviewSummary').innerHTML =
    `${filas.length} alumnos inscritos · <strong>${detectados}</strong> encontrados en la foto · revisá cada fila antes de guardar`;
  const tb = document.getElementById('ocrBody');
  tb.innerHTML = filas.map((a, i) => `
    <tr class="pl-row pl-${a.estado}" id="ocrrow-${i}">
      <td style="padding:.55rem .7rem;width:44px;">${avatarHtml(a)}</td>
      <td style="padding:.55rem .8rem;">
        <div style="font-weight:700;font-size:.85rem;">${a.apellido||''}, ${a.nombre||''}</div>
        <div style="font-size:.72rem;color:var(--muted);">CI: ${a.cedula||'—'}</div>
      </td>
      <td style="text-align:center;padding:.55rem .5rem;">
        <span class="${a.detectado?'ocr-detect-yes':'ocr-detect-no'}">${a.detectado?'✓ Sí':'— No'}</span>
      </td>
      <td style="text-align:center;padding:.55rem .5rem;">
        <div style="display:flex;gap:.2rem;justify-content:center;">
          ${EST.map(e=>`<button class="mark-btn ${a.estado===e?EST_MK[e]:''}" onclick="ocrSetEstado(${i},'${e}')">${EST_SHORT[e]}</button>`).join('')}
        </div>
      </td>
      <td style="padding:.55rem .7rem;">
        <input class="obs-input" value="${a.obs}" placeholder="Observación…" oninput="_ocrFilas[${i}].obs=this.value">
      </td>
    </tr>`).join('');
}
function ocrSetEstado(i, estado) {
  _ocrFilas[i].estado = estado;
  const row = document.getElementById('ocrrow-'+i);
  row.className = `pl-row pl-${estado}`;
  row.querySelectorAll('.mark-btn').forEach((btn, j) => {
    const e = EST[j];
    btn.className = `mark-btn ${estado===e ? EST_MK[e] : ''}`;
  });
}
function ocrVerTexto() {
  Swal.fire({
    title: 'Texto detectado en la foto',
    html: `<pre style="text-align:left;white-space:pre-wrap;max-height:320px;overflow:auto;font-size:.78rem;background:#f5f0e8;padding:.8rem;border-radius:8px;">${(_ocrTextoCrudo||'(vacío)').replace(/[&<>]/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;'}[c]))}</pre>`,
    width: 560,
    confirmButtonText: 'Cerrar',
    background: '#f5f0e8', color: '#1a4d2e',
    customClass: { popup:'ibbs-swal', title:'ibbs-swal-title', confirmButton:'ibbs-swal-btn-ok' },
    buttonsStyling: false,
  });
}

async function ocrGuardar() {
  const mid = document.getElementById('ocrMateria').value;
  const fecha = document.getElementById('ocrFecha').value;
  const modo = document.querySelector('input[name="ocrModo"]:checked').value;
  const file = _ocrFotoFinal;
  if (!mid || !fecha || !file || !_ocrFilas.length) { Ibbs.warn('Procesá una foto primero.'); return; }

  const btn = document.getElementById('btnOcrGuardar');
  btn.disabled = true; btn.innerHTML = '<span class="spin"></span> Guardando…';
  try {
    const fd = new FormData();
    fd.append('action', 'hoja_guardar');
    fd.append('materia_id', mid);
    fd.append('fecha', fecha);
    fd.append('modo', modo);
    fd.append('foto', file);
    fd.append('texto_ocr', _ocrTextoCrudo);
    fd.append('registros', JSON.stringify(_ocrFilas.map(a => ({persona_id:a.id, estado:a.estado, observacion:a.obs||'', detectado:a.detectado}))));
    const m = document.querySelector('meta[name="csrf-token"]');
    fd.append('csrf_token', m ? m.content : '');
    const r = await fetch('api/asistencia_ocr.php', {method:'POST', body:fd});
    const d = await r.json();
    if (d.ok) {
      await Ibbs.success(d.msg);
      document.getElementById('ocrReview').style.display = 'none';
      document.getElementById('ocrFoto').value = '';
      document.getElementById('ocrFotoPreviewWrap').style.display = 'none';
      _ocrFotoFinal = null;
      document.getElementById('btnOcrProcesar').disabled = true;
      loadStatChips();
      ocrCargarHojas();
    } else {
      Ibbs.error(d.msg || 'No se pudo guardar.');
    }
  } catch (e) { console.error(e); Ibbs.error('Error de conexión.'); }
  btn.disabled = false;
  btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13"/><polyline points="7 3 7 8 15 8"/></svg> Guardar asistencia';
}

async function ocrCargarHojas() {
  const el = document.getElementById('ocrHojasList');
  const mid = document.getElementById('ocrMateria').value || 0;
  const r = await fetch('api/asistencia_ocr.php', {method:'POST', body:(()=>{const fd=new FormData();fd.append('action','hoja_list');fd.append('materia_id',mid);const m=document.querySelector('meta[name="csrf-token"]');fd.append('csrf_token',m?m.content:'');return fd;})()});
  const d = await r.json();
  if (!d.ok || !d.data.length) { el.innerHTML = '<div style="text-align:center;color:var(--muted);font-size:.82rem;padding:.5rem;">Todavía no se cargó ninguna hoja.</div>'; return; }
  el.innerHTML = d.data.map(h => `
    <div class="ocr-hoja-card">
      <img class="ocr-hoja-thumb" src="${h.foto}" onclick="window.open('${h.foto}','_blank')">
      <div style="flex:1;min-width:0;">
        <div style="font-weight:700;font-size:.83rem;">${h.materia_nombre} · ${h.fecha}</div>
        <div style="font-size:.74rem;color:var(--muted);">${h.total_detectados}/${h.total_alumnos} detectados · subida por ${h.cargado_por_nombre||'—'} · ${(h.creado_en||'').substring(0,16)}</div>
      </div>
    </div>`).join('');
}

document.addEventListener('ibbs:ready',()=>{ loadStatChips(); });
</script>
<?php include __DIR__.'/layout/foot.php'; ?>
