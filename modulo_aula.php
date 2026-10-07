<?php
$page_title = 'Aula Virtual';
$page_sub   = 'Contenido por secciones, tareas y foro de la materia';
$active_link = 'materias';
// Se calcula ANTES del include para que layout/head.php pueda armar el
// token de WebSocket ya con el canal de esta materia (ver $ws_materia_id).
$materia_id = (int)($_GET['materia_id'] ?? 0);
$ws_materia_id = $materia_id;
include __DIR__.'/layout/head.php';
// Acceso: admin, superadmin, profesor y alumno.
if(!in_array($_rol,['superadmin','admin','profesor','alumno'])){
    echo '<script>window.location="index.php";</script>'; exit;
}
?>

<style>
/* ── Aula Virtual: banner + nav lateral + secciones (estilo Moodle) ── */
.aula-banner{
  /* Verde sólido fijo (no usa --ink/--ink2: esas variables se invierten
     en modo oscuro y volvían el banner blanco-a-verde en vez de un
     verde uniforme). */
  background:linear-gradient(135deg, #123c25 0%, #1c6b3c 100%);
  border-radius:16px; padding:1.6rem 1.8rem;
  margin-bottom:1.3rem; position:relative; overflow:hidden;
  box-shadow:0 8px 28px rgba(0,0,0,.18);
}
.aula-banner::before{
  content:''; position:absolute; inset:0;
  background:radial-gradient(circle at 85% -30%, rgba(57,255,20,.22), transparent 55%);
}
.aula-banner h2{ font-family:'DM Serif Display',serif; font-size:1.7rem; color:#fff; position:relative; }
.aula-banner .meta{ color:rgba(255,255,255,.65); font-size:.82rem; margin-top:.3rem; position:relative; display:flex; gap:1rem; flex-wrap:wrap; }
.aula-banner .meta b{ color:var(--lime); }

.aula-shell{ display:flex; gap:1.4rem; align-items:flex-start; }
.aula-nav{
  flex:0 0 200px; display:flex; flex-direction:column; gap:.3rem;
  position:sticky; top:1rem;
}
.aula-nav-btn{
  display:flex; align-items:center; gap:.6rem; padding:.7rem .9rem; border-radius:10px;
  border:1.5px solid transparent; background:none; cursor:pointer; text-align:left;
  font-family:'Nunito',sans-serif; font-size:.87rem; font-weight:600; color:var(--muted);
  transition:all .18s;
}
.aula-nav-btn:hover{ background:var(--cream); color:var(--ink); }
.aula-nav-btn.active{ background:var(--paper); border-color:var(--lime2); color:var(--ink); box-shadow:0 2px 10px rgba(0,0,0,.05); }
.aula-nav-btn .ico{ font-size:1.05rem; }
.aula-main{ flex:1; min-width:0; }

.aula-section{
  background:var(--paper); border:1.5px solid var(--border); border-radius:14px;
  margin-bottom:1rem; overflow:hidden;
}
.aula-section-head{
  display:flex; align-items:center; gap:.7rem; padding:1rem 1.2rem; cursor:pointer;
  user-select:none;
}
.aula-section-head:hover{ background:rgba(0,0,0,.02); }
.aula-section-head .chev{ transition:transform .2s; color:var(--muted); flex-shrink:0; }
.aula-section.collapsed .chev{ transform:rotate(-90deg); }
.aula-section-head h3{ font-family:'DM Serif Display',serif; font-size:1.1rem; color:var(--ink); flex:1; }
.aula-section-head .count{ font-size:.74rem; color:var(--muted); background:var(--cream); padding:.2rem .6rem; border-radius:10px; }
.aula-section.hidden-sec .aula-section-head h3::after{ content:' (oculta)'; font-size:.7rem; color:var(--muted); font-family:'Nunito',sans-serif; }
.aula-section-body{ padding:0 1.2rem 1.1rem; }
.aula-section.collapsed .aula-section-body{ display:none; }
.aula-section-actions{ display:flex; gap:.3rem; flex-shrink:0; }
.aula-section-actions button{
  background:none; border:none; cursor:pointer; color:var(--muted); padding:.3rem; border-radius:6px;
}
.aula-section-actions button:hover{ background:var(--cream); color:var(--ink); }

.content-item{
  display:flex; gap:.8rem; padding:.85rem 0; border-top:1px solid var(--border);
}
.content-item:first-child{ border-top:none; }
.content-item .ci-ico{ font-size:1.3rem; flex-shrink:0; line-height:1; margin-top:.1rem; }
.content-item .ci-body{ flex:1; min-width:0; }
.content-item .ci-title{ font-weight:700; font-size:.92rem; color:var(--ink); }
.content-item .ci-sub{ font-size:.76rem; color:var(--muted); margin-top:.1rem; }
.content-item .ci-desc{ font-size:.85rem; color:var(--ink); margin-top:.4rem; white-space:pre-wrap; line-height:1.55; }
.content-item .ci-actions{ display:flex; gap:.4rem; flex-shrink:0; align-items:flex-start; flex-wrap:wrap; justify-content:flex-end; }
.seccion-mini-select{
  font-size:.72rem; padding:.3rem .4rem; border-radius:7px; border:1.5px solid var(--border);
  background:var(--paper); color:var(--muted); font-family:'Nunito',sans-serif;
}
.aula-empty{ text-align:center; padding:2.5rem 1rem; color:var(--muted); font-size:.85rem; }
.add-content-row{ display:flex; gap:.5rem; flex-wrap:wrap; margin-bottom:1.1rem; }

@media (max-width: 860px){
  .aula-shell{ flex-direction:column; }
  .aula-nav{ flex-direction:row; width:100%; position:static; overflow-x:auto; }
  .aula-nav-btn{ flex-shrink:0; }
}
</style>

<?php if(in_array($_rol,['superadmin','admin'])): ?>
<a href="modulo_materias.php" style="display:inline-flex;align-items:center;gap:.4rem;font-size:.82rem;color:var(--muted);text-decoration:none;margin-bottom:1rem;">
  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
  Volver a materias
</a>
<?php endif; ?>

<!-- Selector de materia -->
<div class="card" id="areaSelector" style="display:<?=$materia_id?'none':'block'?>;margin-bottom:1.4rem;">
  <div class="card-body">
    <div class="field" style="max-width:420px;">
      <label>Selecciona una materia</label>
      <select id="selMateriaAula" onchange="if(this.value) window.location='modulo_aula.php?materia_id='+this.value;">
        <option value="">— Elige una materia —</option>
      </select>
    </div>
  </div>
</div>

<div id="areaCargando" style="display:<?=$materia_id?'block':'none'?>;text-align:center;padding:4rem 1rem;color:var(--muted);"><span class="spin"></span></div>

<div id="areaSinPermiso" style="display:none;text-align:center;padding:4rem 1rem;color:var(--muted);">
  <p style="font-family:'DM Serif Display',serif;font-size:1.3rem;margin-bottom:.4rem;">Sin acceso a esta aula</p>
  <p style="font-size:.84rem;">No tienes esta materia asignada.</p>
</div>

<div id="areaAula" style="display:none;">

  <div class="aula-banner">
    <h2 id="matTitulo"></h2>
    <div class="meta">
      <span>Código: <b id="matCodigo"></b></span>
      <span id="matMetaSecciones"></span>
    </div>
  </div>

  <div class="aula-shell">
    <div class="aula-nav">
      <button class="aula-nav-btn active" data-tab-group="aula" data-tab="contenido" onclick="switchTab('aula','contenido')"><span class="ico">📚</span> Contenido</button>
      <button class="aula-nav-btn" data-tab-group="aula" data-tab="tareas" onclick="switchTab('aula','tareas')"><span class="ico">📋</span> Tareas</button>
      <button class="aula-nav-btn" data-tab-group="aula" data-tab="foro" onclick="switchTab('aula','foro')"><span class="ico">💬</span> Foro / Dudas</button>
    </div>

    <div class="aula-main">

      <!-- ═══ PANE: CONTENIDO (secciones estilo Moodle) ═══ -->
      <div class="tab-pane active" data-pane-group="aula" data-pane="contenido">
        <div class="add-content-row can-manage" style="display:none;">
          <button class="btn btn-secondary btn-sm" onclick="abrirSeccionModal()">+ Nueva sección</button>
          <button class="btn btn-primary btn-sm" onclick="abrirAnuncioModal()">📢 Anuncio</button>
          <button class="btn btn-primary btn-sm" onclick="openModal('mMaterial')">📁 Material</button>
          <button class="btn btn-primary btn-sm" onclick="abrirActividadModal()">📝 Actividad</button>
        </div>
        <div id="seccionesContainer"><div style="text-align:center;padding:2rem;color:var(--muted);"><span class="spin"></span></div></div>
      </div>

      <!-- ═══ PANE: TAREAS ═══ -->
      <div class="tab-pane" data-pane-group="aula" data-pane="tareas">
        <div style="display:flex;justify-content:flex-end;margin-bottom:1rem;">
          <button class="btn btn-primary can-manage" style="display:none;" onclick="abrirTareaModal()">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Crear Tarea
          </button>
        </div>
        <div id="listaTareas"><div style="text-align:center;padding:2rem;color:var(--muted);"><span class="spin"></span></div></div>
      </div>

      <!-- ═══ PANE: FORO / DUDAS ═══ -->
      <div class="tab-pane" data-pane-group="aula" data-pane="foro">
        <div class="card" style="display:flex;flex-direction:column;height:550px;overflow:hidden;border:1px solid var(--border);padding:0;">
          <!-- Caja de Mensajes -->
          <div id="chat-box" style="flex:1;overflow-y:auto;padding:1.5rem;background:var(--paper);display:flex;flex-direction:column;gap:1rem;">
            <div style="text-align:center;padding:2rem;color:var(--muted);"><span class="spin"></span> Cargando foro...</div>
          </div>
          <!-- Indicador de Respuesta -->
          <div id="reply-indicator" style="display:none;background:var(--bg);padding:.6rem 1.5rem;border-top:1px solid var(--border);border-bottom:1px solid var(--border);font-size:.85rem;justify-content:space-between;align-items:center;">
            <span>Respondiendo a: <strong id="reply-to-name" style="color:var(--ink);"></strong></span>
            <button onclick="cancelReply()" style="background:none;border:none;color:#dc2626;cursor:pointer;font-weight:bold;font-size:1.1rem;padding:0;">&times;</button>
          </div>
          <!-- Formulario -->
          <div style="padding:1rem 1.5rem;background:var(--paper);border-top:1px solid var(--border);">
            <form id="chat-form" style="display:flex;gap:.8rem;align-items:flex-end;margin:0;">
              <input type="hidden" id="respuesta_a" value="">
              <div style="flex:1;">
                <textarea id="mensaje-input" rows="2" style="width:100%;border:1px solid var(--border);border-radius:6px;padding:.6rem;font-family:inherit;resize:none;font-size:.9rem;outline:none;" placeholder="Escribe tu duda o respuesta aquí... (Enter para enviar)"></textarea>
              </div>
              <button type="submit" class="btn btn-primary" style="display:flex;align-items:center;gap:.4rem;height:42px;margin-bottom:2px;">
                Enviar
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
              </button>
            </form>
          </div>
        </div>
      </div>

    </div>
  </div>

</div>

<!-- ================= MODALES ================= -->

<!-- MODAL SECCIÓN (crear/editar) -->
<div class="modal-backdrop" id="mSeccion">
  <div class="modal">
    <div class="modal-head"><h3 id="mSeccionTitulo">Nueva sección</h3>
      <button class="modal-close" onclick="closeModal('mSeccion')"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="secId">
      <div class="form-grid" style="margin-bottom:1rem;">
        <div class="field field-full"><label>Título *</label><input id="secTitulo" placeholder="Ej. Semana 1 — Introducción"></div>
        <div class="field field-full"><label>Descripción</label><textarea id="secDescripcion" rows="2" placeholder="Opcional"></textarea></div>
        <div class="field field-full">
          <label style="display:flex;align-items:center;gap:.4rem;cursor:pointer;text-transform:none;opacity:1;letter-spacing:0;">
            <input type="checkbox" id="secVisible" checked style="width:auto;accent-color:var(--lime2);"> Visible para los alumnos
          </label>
        </div>
      </div>
      <div style="display:flex;justify-content:flex-end;gap:.6rem;">
        <button type="button" class="btn btn-secondary" onclick="closeModal('mSeccion')">Cancelar</button>
        <button type="button" class="btn btn-primary" onclick="guardarSeccion()">Guardar</button>
      </div>
    </div>
  </div>
</div>

<!-- MODAL ANUNCIO (crear/editar) -->
<div class="modal-backdrop" id="mAnuncio">
  <div class="modal">
    <div class="modal-head"><h3 id="mAnuncioTitulo">Nuevo anuncio</h3>
      <button class="modal-close" onclick="closeModal('mAnuncio')"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="anId">
      <div class="form-grid" style="margin-bottom:1rem;">
        <div class="field field-full"><label>Título *</label><input id="anTitulo" placeholder="Ej. Cambio de horario para el sábado"></div>
        <div class="field field-full"><label>Contenido *</label><textarea id="anContenido" rows="5" placeholder="Escribe el anuncio para tus alumnos…"></textarea></div>
        <div class="field"><label>Sección</label>
          <select id="anSeccion"><option value="">— General (sin sección) —</option></select>
        </div>
        <div class="field" style="justify-content:center;">
          <label style="display:flex;align-items:center;gap:.4rem;cursor:pointer;text-transform:none;opacity:1;letter-spacing:0;">
            <input type="checkbox" id="anFijado" style="width:auto;accent-color:var(--lime2);"> Fijar arriba del muro
          </label>
        </div>
      </div>
      <div style="display:flex;justify-content:flex-end;gap:.6rem;">
        <button type="button" class="btn btn-secondary" onclick="closeModal('mAnuncio')">Cancelar</button>
        <button type="button" class="btn btn-primary" onclick="guardarAnuncio()">Publicar</button>
      </div>
    </div>
  </div>
</div>

<!-- MODAL MATERIAL (subir) -->
<div class="modal-backdrop" id="mMaterial">
  <div class="modal">
    <div class="modal-head"><h3>Subir material</h3>
      <button class="modal-close" onclick="closeModal('mMaterial')"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
    </div>
    <div class="modal-body">
      <form id="fMaterial" onsubmit="subirMaterial(event)">
        <div class="form-grid" style="margin-bottom:1rem;">
          <div class="field field-full"><label>Título *</label><input name="titulo" placeholder="Ej. Guía de estudio — Unidad 1" required></div>
          <div class="field field-full"><label>Descripción</label><textarea name="descripcion" rows="2"></textarea></div>
          <div class="field field-full"><label>Sección</label>
            <select name="seccion_id" id="matSeccion"><option value="">— General (sin sección) —</option></select>
          </div>
          <div class="field field-full">
            <label>Archivo * <span style="text-transform:none;font-weight:400;color:var(--muted);">(PDF, Word, PowerPoint, Excel, TXT, CSV, ZIP o imagen — máx. 25MB)</span></label>
            <input type="file" name="archivo" id="materialArchivo" required
              accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.txt,.csv,.zip,.jpg,.jpeg,.png,.gif,.webp">
          </div>
        </div>
        <div style="display:flex;justify-content:flex-end;gap:.6rem;">
          <button type="button" class="btn btn-secondary" onclick="closeModal('mMaterial')">Cancelar</button>
          <button type="submit" class="btn btn-primary" id="btnSubirMaterial">Subir</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- MODAL ACTIVIDAD (crear/editar) -->
<div class="modal-backdrop" id="mActividad">
  <div class="modal">
    <div class="modal-head"><h3 id="mActividadTitulo">Nueva actividad</h3>
      <button class="modal-close" onclick="closeModal('mActividad')"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="acId">
      <div class="form-grid" style="margin-bottom:1rem;">
        <div class="field field-full"><label>Título *</label><input id="acTitulo" placeholder="Ej. Taller 1 — Análisis de texto"></div>
        <div class="field field-full"><label>Descripción</label><textarea id="acDescripcion" rows="2"></textarea></div>
        <div class="field"><label>Tipo</label>
          <select id="acTipo">
            <option value="actividad">Actividad</option>
            <option value="taller">Taller</option>
            <option value="examen">Examen</option>
            <option value="proyecto">Proyecto</option>
          </select>
        </div>
        <div class="field"><label>Sección</label>
          <select id="acSeccion"><option value="">— General (sin sección) —</option></select>
        </div>
        <div class="field"><label>Nota máxima</label><input type="number" id="acNotaMax" data-only="decimal" value="20" min="1" step="0.5"></div>
        <div class="field"><label>Fecha</label><input type="date" id="acFecha"></div>
      </div>
      <div style="display:flex;justify-content:flex-end;gap:.6rem;">
        <button type="button" class="btn btn-secondary" onclick="closeModal('mActividad')">Cancelar</button>
        <button type="button" class="btn btn-primary" onclick="guardarActividad()">Guardar</button>
      </div>
    </div>
  </div>
</div>

<!-- MODAL CALIFICAR ACTIVIDAD -->
<div class="modal-backdrop" id="mCalificar">
  <div class="modal md">
    <div class="modal-head"><h3 id="mCalificarTitulo">Calificar</h3>
      <button class="modal-close" onclick="closeModal('mCalificar')"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="calActividadId">
      <div class="tbl-wrap">
        <table>
          <thead><tr><th style="text-align:left;">Alumno</th><th style="text-align:left;">Cédula</th><th style="width:110px;">Nota</th><th>Observación</th></tr></thead>
          <tbody id="tbodyCalificar"><tr class="empty-row"><td colspan="4"><span class="spin"></span></td></tr></tbody>
        </table>
      </div>
    </div>
    <div class="modal-foot">
      <button class="btn btn-secondary" onclick="closeModal('mCalificar')">Cancelar</button>
      <button class="btn btn-primary" onclick="guardarCalificaciones()">Guardar calificaciones</button>
    </div>
  </div>
</div>

<!-- MODAL CREAR TAREA (Docente) -->
<div class="modal-backdrop" id="mTarea">
  <div class="modal">
    <div class="modal-head"><h3>Nueva Tarea</h3>
      <button class="modal-close" onclick="closeModal('mTarea')"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
    </div>
    <div class="modal-body">
      <form id="fTarea" onsubmit="guardarTarea(event)">
        <div class="form-grid" style="margin-bottom:1rem;">
          <div class="field field-full"><label>Título de la Tarea *</label><input name="titulo" required></div>
          <div class="field field-full"><label>Descripción / Instrucciones</label><textarea name="descripcion" rows="3"></textarea></div>
          <div class="field"><label>Fecha y Hora Límite *</label><input type="datetime-local" name="fecha_limite" required></div>
          <div class="field"><label>Nota Máxima</label><input type="number" name="nota_maxima" value="20" step="1"></div>
          <div class="field field-full"><label>Archivo adjunto (Opcional)</label><input type="file" name="archivo"></div>
        </div>
        <div style="display:flex;justify-content:flex-end;gap:.6rem;">
          <button type="button" class="btn btn-secondary" onclick="closeModal('mTarea')">Cancelar</button>
          <button type="submit" class="btn btn-primary" id="btnGuardarTarea">Publicar Tarea</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- MODAL SUBIR ENTREGA (Alumno) -->
<div class="modal-backdrop" id="mSubirEntrega">
  <div class="modal">
    <div class="modal-head"><h3 id="mSubirTitulo">Entregar Tarea</h3>
      <button class="modal-close" onclick="closeModal('mSubirEntrega')"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
    </div>
    <div class="modal-body">
      <form id="fEntrega" onsubmit="guardarEntrega(event)">
        <input type="hidden" name="tarea_id" id="entTareaId">
        <div class="form-grid" style="margin-bottom:1rem;">
          <div class="field field-full">
            <label>Tu respuesta escrita (Opcional si subes archivo)</label>
            <textarea name="texto_respuesta" rows="4" placeholder="Escribe aquí tu respuesta o comentarios..."></textarea>
          </div>
          <div class="field field-full">
            <label>Subir Archivo de Entrega (PDF, Word, imagen, etc.)</label>
            <input type="file" name="archivo">
          </div>
        </div>
        <div style="display:flex;justify-content:flex-end;gap:.6rem;">
          <button type="button" class="btn btn-secondary" onclick="closeModal('mSubirEntrega')">Cancelar</button>
          <button type="submit" class="btn btn-primary" id="btnSubirEntrega">Enviar Entrega</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- MODAL VER Y CALIFICAR ENTREGAS (Docente) -->
<div class="modal-backdrop" id="mVerEntregas">
  <div class="modal md">
    <div class="modal-head"><h3 id="mVerEntTitulo">Entregas de Alumnos</h3>
      <button class="modal-close" onclick="closeModal('mVerEntregas')"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
    </div>
    <div class="modal-body">
      <div class="tbl-wrap">
        <table>
          <thead><tr><th style="text-align:left;">Alumno</th><th>Estado</th><th>Archivo / Texto</th><th style="width:90px;">Nota</th><th>Observación</th><th></th></tr></thead>
          <tbody id="tbodyEntregas"><tr class="empty-row"><td colspan="6"><span class="spin"></span></td></tr></tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script>
const MATERIA_ID = <?=$materia_id?>;
let CAN_MANAGE = false;
let ACT_NOTA_MAX = 20;

// VARIABLES PARA EL FORO
const CURRENT_USER = "<?php echo addslashes($_SESSION['usuario'] ?? ''); ?>";
let lastMessageCount = -1;
let foroInterval = null;

// Estado del contenido por secciones (Aula Virtual estilo Moodle)
let SECCIONES = [];
let ANUNCIOS = [];
let MATERIALES = [];
let ACTIVIDADES = [];
let SECCIONES_COLAPSADAS = {}; // { seccion_key: true } — solo en memoria de esta sesión de página

function h(s){ const d=document.createElement('div'); d.textContent=String(s??''); return d.innerHTML; }
function fmtBytes(n){
  n = parseInt(n)||0;
  if (n < 1024) return n+' B';
  if (n < 1024*1024) return (n/1024).toFixed(1)+' KB';
  return (n/1024/1024).toFixed(1)+' MB';
}
function fmtFecha(s){
  if(!s) return '—';
  return String(s).substring(0,16).replace('T',' ');
}
function aulaAjax(action, data={}) { return ajax(action, {...data, materia_id: MATERIA_ID}, 'api/aula.php'); }

document.addEventListener('ibbs:ready', iniciarAula);

async function cargarSelectorMaterias() {
  const d = await aulaAjax('materias_mias');
  const sel = document.getElementById('selMateriaAula');
  if (!d?.ok || !d.data.length) {
    sel.innerHTML = '<option value="">Sin materias asignadas</option>';
    return;
  }
  sel.innerHTML = '<option value="">— Elige una materia —</option>' +
    d.data.map(m => `<option value="${m.id}" ${m.id==MATERIA_ID?'selected':''}>${h(m.codigo)} · ${h(m.nombre)}</option>`).join('');
}

async function iniciarAula() {
  cargarSelectorMaterias();
  if (!MATERIA_ID) return;

  const d = await aulaAjax('materia_info');
  document.getElementById('areaCargando').style.display = 'none';
  if (!d?.ok) {
    document.getElementById('areaSinPermiso').style.display = 'block';
    return;
  }
  CAN_MANAGE = !!d.data.can_manage;
  document.getElementById('matTitulo').textContent = d.data.materia.nombre;
  document.getElementById('matCodigo').textContent = d.data.materia.codigo;
  document.querySelectorAll('.can-manage').forEach(el => el.style.display = CAN_MANAGE ? '' : 'none');
  document.getElementById('areaAula').style.display = 'block';

  cargarContenido();
  loadTareas();

  // Iniciar el foro y el auto-refresco
  loadForo();
  if(foroInterval) clearInterval(foroInterval);
  foroInterval = setInterval(loadForo, 5000);

  // Con WebSocket (VPS configurado) el refresco es casi instantáneo en
  // vez de esperar hasta 5s — el setInterval de arriba se deja igual
  // como red de seguridad si el WebSocket se cae.
  if (window.IbbsRT && window.IbbsRT.hasWs) {
    window.IbbsRT.on('foro_mensaje', (data) => {
      if (!data || parseInt(data.materia_id) === parseInt(MATERIA_ID)) { lastMessageCount = -1; loadForo(); }
    });
  }
}

/* ══════════════ CONTENIDO POR SECCIONES (estilo Moodle) ══════════════ */

async function cargarContenido() {
  const [rSec, rAn, rMat, rAc] = await Promise.all([
    aulaAjax('seccion_list'),
    aulaAjax('anuncio_list'),
    aulaAjax('material_list'),
    aulaAjax('actividad_list'),
  ]);
  SECCIONES   = rSec?.ok ? rSec.data : [];
  ANUNCIOS    = rAn?.ok ? rAn.data : [];
  MATERIALES  = rMat?.ok ? rMat.data : [];
  ACTIVIDADES = rAc?.ok ? rAc.data : [];
  poblarSelectsSeccion();
  renderContenido();
}

function poblarSelectsSeccion() {
  const opciones = '<option value="">— General (sin sección) —</option>' +
    SECCIONES.map(s => `<option value="${s.id}">${h(s.titulo)}</option>`).join('');
  ['anSeccion','matSeccion','acSeccion'].forEach(idSel => {
    const sel = document.getElementById(idSel);
    const val = sel.value;
    sel.innerHTML = opciones;
    sel.value = val;
  });
  document.getElementById('matMetaSecciones').textContent =
    SECCIONES.length ? `${SECCIONES.length} sección(es)` : '';
}

function renderContenido() {
  const cont = document.getElementById('seccionesContainer');

  // IDs de sección que el alumno puede ver (el servidor ya filtra las
  // ocultas en seccion_list cuando no es docente/admin).
  const idsVisibles = new Set(SECCIONES.map(s => s.id));

  const items = [
    ...ANUNCIOS.map(a => ({tipo:'anuncio', seccion_id:a.seccion_id, fecha:a.creado_en, data:a})),
    ...MATERIALES.map(m => ({tipo:'material', seccion_id:m.seccion_id, fecha:m.creado_en, data:m})),
    ...ACTIVIDADES.map(a => ({tipo:'actividad', seccion_id:a.seccion_id, fecha:a.creado_en, data:a})),
  ];

  // Agrupa; descarta items de una sección oculta que el alumno no puede ver.
  const grupos = {}; // key: 'general' o id de sección
  const generalKey = 'general';
  grupos[generalKey] = [];
  SECCIONES.forEach(s => grupos[s.id] = []);

  items.forEach(it => {
    if (!it.seccion_id) { grupos[generalKey].push(it); return; }
    if (!idsVisibles.has(it.seccion_id)) {
      if (!CAN_MANAGE) return; // alumno: sección oculta, no se muestra
      // docente sin esa sección cargada (no debería pasar) — cae a general
      grupos[generalKey].push(it);
      return;
    }
    grupos[it.seccion_id].push(it);
  });

  Object.values(grupos).forEach(arr => arr.sort((a,b) => new Date(b.fecha) - new Date(a.fecha)));

  const bloques = [];
  bloques.push(renderSeccionCard({id:null, titulo:'General', visible:1}, grupos[generalKey]));
  SECCIONES.forEach(s => bloques.push(renderSeccionCard(s, grupos[s.id] || [])));

  cont.innerHTML = bloques.join('');
}

function renderSeccionCard(seccion, items) {
  const key = seccion.id ?? 'general';
  const colapsada = !!SECCIONES_COLAPSADAS[key];
  const oculta = seccion.id && !seccion.visible;
  const acciones = (CAN_MANAGE && seccion.id) ? `
    <div class="aula-section-actions" onclick="event.stopPropagation()">
      <button title="Editar sección" onclick='editarSeccion(${JSON.stringify(seccion)})'>✏️</button>
      <button title="Eliminar sección" onclick="eliminarSeccion(${seccion.id})">🗑️</button>
    </div>` : '';

  return `
    <div class="aula-section ${colapsada?'collapsed':''} ${oculta?'hidden-sec':''}" data-sec-key="${key}">
      <div class="aula-section-head" onclick="toggleSeccion('${key}')">
        <svg class="chev" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
        <h3>${h(seccion.titulo)}</h3>
        <span class="count">${items.length}</span>
        ${acciones}
      </div>
      <div class="aula-section-body">
        ${seccion.descripcion ? `<p style="font-size:.82rem;color:var(--muted);margin-bottom:.6rem;">${h(seccion.descripcion)}</p>` : ''}
        ${items.length ? items.map(it => renderContentItem(it)).join('') : '<div class="aula-empty">Sin contenido en esta sección.</div>'}
      </div>
    </div>`;
}

function toggleSeccion(key) {
  SECCIONES_COLAPSADAS[key] = !SECCIONES_COLAPSADAS[key];
  const el = document.querySelector(`.aula-section[data-sec-key="${key}"]`);
  if (el) el.classList.toggle('collapsed');
}

function renderContentItem(it) {
  if (it.tipo === 'anuncio') return renderItemAnuncio(it.data);
  if (it.tipo === 'material') return renderItemMaterial(it.data);
  return renderItemActividad(it.data);
}

function renderItemAnuncio(a) {
  const mover = CAN_MANAGE ? `<select class="seccion-mini-select" onchange="moverContenido('anuncio',${a.id},this.value)">${opcionesSeccionSelect(a.seccion_id)}</select>` : '';
  return `
    <div class="content-item">
      <div class="ci-ico">📢</div>
      <div class="ci-body">
        <div class="ci-title">${a.fijado==1?'📌 ':''}${h(a.titulo)}</div>
        <div class="ci-sub">Anuncio · ${h(a.autor)} · ${fmtFecha(a.creado_en)}</div>
        <div class="ci-desc">${h(a.contenido)}</div>
      </div>
      <div class="ci-actions">
        ${mover}
        ${CAN_MANAGE ? `
          <button class="btn btn-sm btn-secondary" onclick='editarAnuncio(${JSON.stringify(a)})'>Editar</button>
          <button class="btn btn-sm btn-danger" onclick="eliminarAnuncio(${a.id})">Eliminar</button>` : ''}
      </div>
    </div>`;
}

function renderItemMaterial(m) {
  const mover = CAN_MANAGE ? `<select class="seccion-mini-select" onchange="moverContenido('material',${m.id},this.value)">${opcionesSeccionSelect(m.seccion_id)}</select>` : '';
  return `
    <div class="content-item">
      <div class="ci-ico">📁</div>
      <div class="ci-body">
        <div class="ci-title">${h(m.titulo)}</div>
        <div class="ci-sub">Material · <span class="badge b-profesor">${h((m.archivo_tipo||'').toUpperCase())}</span> · ${fmtBytes(m.tamano_bytes)} · ${h(m.autor)} · ${fmtFecha(m.creado_en)}</div>
        ${m.descripcion ? `<div class="ci-desc">${h(m.descripcion)}</div>` : ''}
      </div>
      <div class="ci-actions">
        ${mover}
        <a class="btn btn-sm btn-primary" href="api/aula.php?action=material_download&id=${m.id}">Descargar</a>
        ${CAN_MANAGE ? `<button class="btn btn-sm btn-danger" onclick="eliminarMaterial(${m.id})">Eliminar</button>` : ''}
      </div>
    </div>`;
}

function renderItemActividad(a) {
  const mover = CAN_MANAGE ? `<select class="seccion-mini-select" onchange="moverContenido('actividad',${a.id},this.value)">${opcionesSeccionSelect(a.seccion_id)}</select>` : '';
  return `
    <div class="content-item">
      <div class="ci-ico">📝</div>
      <div class="ci-body">
        <div class="ci-title">${h(a.titulo)}</div>
        <div class="ci-sub">Actividad · <span class="badge b-tardanza">${h(a.tipo)}</span> · Nota máx. ${parseFloat(a.nota_max).toFixed(1)} · ${a.fecha||'Sin fecha'}</div>
        ${a.descripcion ? `<div class="ci-desc">${h(a.descripcion)}</div>` : ''}
      </div>
      <div class="ci-actions">
        ${mover}
        ${CAN_MANAGE ? `
          <button class="btn btn-sm btn-success" onclick="abrirCalificar(${a.id},'${h(a.titulo).replace(/'/g,"\\'")}',${a.nota_max})">Calificar</button>
          <button class="btn btn-sm btn-secondary" onclick='editarActividad(${JSON.stringify(a)})'>Editar</button>
          <button class="btn btn-sm btn-danger" onclick="eliminarActividad(${a.id})">Eliminar</button>` : ''}
      </div>
    </div>`;
}

function opcionesSeccionSelect(seccionActual) {
  const base = seccionActual ?? '';
  let out = `<option value="" ${base===''?'selected':''}>General</option>`;
  SECCIONES.forEach(s => { out += `<option value="${s.id}" ${s.id==base?'selected':''}>${h(s.titulo)}</option>`; });
  return out;
}

async function moverContenido(tipo, id, seccionId) {
  const d = await aulaAjax('contenido_mover_seccion', {tipo, id, seccion_id: seccionId});
  if (d?.ok) cargarContenido(); else toast(d?.msg || 'Error', 'err');
}

/* ══ SECCIONES: crear/editar/eliminar ══ */
function abrirSeccionModal() {
  document.getElementById('secId').value = '';
  document.getElementById('secTitulo').value = '';
  document.getElementById('secDescripcion').value = '';
  document.getElementById('secVisible').checked = true;
  document.getElementById('mSeccionTitulo').textContent = 'Nueva sección';
  openModal('mSeccion');
}
function editarSeccion(s) {
  document.getElementById('secId').value = s.id;
  document.getElementById('secTitulo').value = s.titulo;
  document.getElementById('secDescripcion').value = s.descripcion || '';
  document.getElementById('secVisible').checked = s.visible == 1;
  document.getElementById('mSeccionTitulo').textContent = 'Editar sección';
  openModal('mSeccion');
}
async function guardarSeccion() {
  const id = document.getElementById('secId').value;
  const titulo = document.getElementById('secTitulo').value.trim();
  const descripcion = document.getElementById('secDescripcion').value.trim();
  const visible = document.getElementById('secVisible').checked ? 1 : 0;
  if (!titulo) { Ibbs.error('Ponle un título a la sección.'); return; }
  const d = await aulaAjax(id ? 'seccion_update' : 'seccion_create', {id, titulo, descripcion, visible});
  if (d?.ok) { toast(d.msg); closeModal('mSeccion'); cargarContenido(); }
  else toast(d?.msg || 'Error', 'err');
}
function eliminarSeccion(id) {
  ibbsConfirm('¿Eliminar esta sección? Su contenido se moverá a General (no se borra).', async () => {
    const d = await aulaAjax('seccion_delete', {id});
    if (d?.ok) { toast(d.msg); cargarContenido(); } else Ibbs.error(d?.msg || 'Error');
  });
}

/* ══ ANUNCIOS ══ */
function abrirAnuncioModal() {
  document.getElementById('anId').value = '';
  document.getElementById('anTitulo').value = '';
  document.getElementById('anContenido').value = '';
  document.getElementById('anSeccion').value = '';
  document.getElementById('anFijado').checked = false;
  document.getElementById('mAnuncioTitulo').textContent = 'Nuevo anuncio';
  openModal('mAnuncio');
}
function editarAnuncio(a) {
  document.getElementById('anId').value = a.id;
  document.getElementById('anTitulo').value = a.titulo;
  document.getElementById('anContenido').value = a.contenido;
  document.getElementById('anSeccion').value = a.seccion_id || '';
  document.getElementById('anFijado').checked = a.fijado == 1;
  document.getElementById('mAnuncioTitulo').textContent = 'Editar anuncio';
  openModal('mAnuncio');
}
async function guardarAnuncio() {
  const id = document.getElementById('anId').value;
  const titulo = document.getElementById('anTitulo').value.trim();
  const contenido = document.getElementById('anContenido').value.trim();
  const seccion_id = document.getElementById('anSeccion').value;
  const fijado = document.getElementById('anFijado').checked ? 1 : 0;
  if (!titulo || !contenido) { Ibbs.error('Completa título y contenido.'); return; }
  const d = await aulaAjax(id ? 'anuncio_update' : 'anuncio_create', {id, titulo, contenido, fijado, seccion_id});
  if (d?.ok) { toast(d.msg); closeModal('mAnuncio'); cargarContenido(); }
  else toast(d?.msg || 'Error', 'err');
}
function eliminarAnuncio(id) {
  ibbsConfirm('¿Eliminar este anuncio?', async () => {
    const d = await aulaAjax('anuncio_delete', {id});
    if (d?.ok) { toast(d.msg); cargarContenido(); } else Ibbs.error(d?.msg || 'Error');
  });
}

/* ══ MATERIALES ══ */
async function subirMaterial(e) {
  e.preventDefault();
  const btn = document.getElementById('btnSubirMaterial');
  btn.disabled = true; btn.textContent = 'Subiendo…';
  const fd = new FormData(e.target);
  fd.append('action', 'material_create');
  fd.append('materia_id', MATERIA_ID);
  const _csrf = document.querySelector('meta[name="csrf-token"]');
  if (_csrf) fd.append('csrf_token', _csrf.content);
  try {
    const r = await fetch('api/aula.php', {method:'POST', body:fd});
    const d = await r.json();
    if (d.ok) { toast(d.msg); closeModal('mMaterial'); e.target.reset(); cargarContenido(); }
    else toast(d.msg || 'Error', 'err');
  } catch(err) { toast('Error de conexión.', 'err'); }
  btn.disabled = false; btn.textContent = 'Subir';
}
function eliminarMaterial(id) {
  ibbsConfirm('¿Eliminar este material? Se borrará el archivo del servidor.', async () => {
    const d = await aulaAjax('material_delete', {id});
    if (d?.ok) { toast(d.msg); cargarContenido(); } else Ibbs.error(d?.msg || 'Error');
  });
}

/* ══ ACTIVIDADES ══ */
function abrirActividadModal() {
  document.getElementById('acId').value = '';
  document.getElementById('acTitulo').value = '';
  document.getElementById('acDescripcion').value = '';
  document.getElementById('acTipo').value = 'actividad';
  document.getElementById('acSeccion').value = '';
  document.getElementById('acNotaMax').value = 20;
  document.getElementById('acFecha').value = '';
  document.getElementById('mActividadTitulo').textContent = 'Nueva actividad';
  openModal('mActividad');
}
function editarActividad(a) {
  document.getElementById('acId').value = a.id;
  document.getElementById('acTitulo').value = a.titulo;
  document.getElementById('acDescripcion').value = a.descripcion || '';
  document.getElementById('acTipo').value = a.tipo;
  document.getElementById('acSeccion').value = a.seccion_id || '';
  document.getElementById('acNotaMax').value = a.nota_max;
  document.getElementById('acFecha').value = a.fecha || '';
  document.getElementById('mActividadTitulo').textContent = 'Editar actividad';
  openModal('mActividad');
}
async function guardarActividad() {
  const id = document.getElementById('acId').value;
  const titulo = document.getElementById('acTitulo').value.trim();
  if (!titulo) { Ibbs.error('Ponle un título a la actividad.'); return; }
  const d = await aulaAjax(id ? 'actividad_update' : 'actividad_create', {
    id,
    titulo,
    descripcion: document.getElementById('acDescripcion').value.trim(),
    tipo: document.getElementById('acTipo').value,
    seccion_id: document.getElementById('acSeccion').value,
    nota_max: document.getElementById('acNotaMax').value || 20,
    fecha: document.getElementById('acFecha').value,
  });
  if (d?.ok) { toast(d.msg); closeModal('mActividad'); cargarContenido(); }
  else toast(d?.msg || 'Error', 'err');
}
function eliminarActividad(id) {
  ibbsConfirm('¿Eliminar esta actividad? Se borrarán también sus calificaciones.', async () => {
    const d = await aulaAjax('actividad_delete', {id});
    if (d?.ok) { toast(d.msg); cargarContenido(); } else Ibbs.error(d?.msg || 'Error');
  });
}

/* ══ CALIFICAR ══ */
async function abrirCalificar(actividadId, titulo, notaMax) {
  document.getElementById('calActividadId').value = actividadId;
  document.getElementById('mCalificarTitulo').textContent = 'Calificar — ' + titulo + ' (máx. ' + parseFloat(notaMax).toFixed(1) + ')';
  ACT_NOTA_MAX = parseFloat(notaMax) || 20;
  const tb = document.getElementById('tbodyCalificar');
  tb.innerHTML = '<tr class="empty-row"><td colspan="4"><span class="spin"></span></td></tr>';
  openModal('mCalificar');
  const d = await aulaAjax('actividad_calificaciones', {actividad_id: actividadId});
  if (!d?.ok) { tb.innerHTML = '<tr class="empty-row"><td colspan="4">'+h(d?.msg||'Error')+'</td></tr>'; return; }
  if (!d.data.alumnos.length) { tb.innerHTML = '<tr class="empty-row"><td colspan="4">No hay alumnos inscritos en esta materia.</td></tr>'; return; }
  tb.innerHTML = d.data.alumnos.map(a => `<tr>
    <td style="text-align:left;">${h(a.apellido)}, ${h(a.nombre)}</td>
    <td style="text-align:left;font-size:.8rem;color:var(--muted);">${h(a.cedula)}</td>
    <td><input type="number" class="calNota" data-alumno="${a.id}" data-only="decimal" min="0" max="${ACT_NOTA_MAX}" step="0.1"
        value="${a.nota !== null ? a.nota : ''}" style="width:80px;text-align:center;"></td>
    <td><input type="text" class="calObs" data-alumno="${a.id}" value="${h(a.observacion||'')}" placeholder="Opcional" style="width:100%;"></td>
  </tr>`).join('');
}
async function guardarCalificaciones() {
  const actividadId = document.getElementById('calActividadId').value;
  const inputs = [...document.querySelectorAll('#tbodyCalificar .calNota')];

  for (const inp of inputs) {
    inp.style.borderColor = '';
    const v = inp.value.trim().replace(',', '.');
    if (v !== '' && (isNaN(v) || parseFloat(v) < 0 || parseFloat(v) > ACT_NOTA_MAX)) {
      Ibbs.error('Hay una nota fuera de rango (0 a ' + ACT_NOTA_MAX + ').');
      inp.style.borderColor = '#dc2626';
      inp.focus();
      return;
    }
  }

  const notas = inputs.map(inp => {
    const alumnoId = inp.dataset.alumno;
    const obs = document.querySelector('#tbodyCalificar .calObs[data-alumno="'+alumnoId+'"]')?.value || '';
    const val = inp.value.trim().replace(',', '.');
    return { alumno_id: alumnoId, nota: val === '' ? null : parseFloat(val), observacion: obs };
  });

  const d = await aulaAjax('actividad_calificar_bulk', {actividad_id: actividadId, notas: JSON.stringify(notas)});
  if (d?.ok) { toast(d.msg); closeModal('mCalificar'); }
  else toast(d?.msg || 'Error', 'err');
}

/* ══ TAREAS Y ENTREGAS ══ */
async function loadTareas() {
  try {
    const r = await fetch(`api/tareas.php?action=list&materia_id=${MATERIA_ID}`);
    const text = await r.text();
    let d;
    try {
        d = JSON.parse(text);
    } catch(err) {
        document.getElementById('listaTareas').innerHTML = `<div style="background:#fee2e2;color:#dc2626;padding:1.5rem;border-radius:8px;margin:1rem 0;border:1px solid #f87171;"><b>⚠️ Error de conexión con api/tareas.php</b><br><br>El servidor no devolvió datos válidos.<br><b>Respuesta del servidor:</b><br><textarea style="width:100%;height:80px;margin-top:10px;font-size:12px;" readonly>${text}</textarea></div>`;
        return;
    }

    const el = document.getElementById('listaTareas');

    if (!d?.ok) { el.innerHTML = '<p class="text-center" style="color:var(--muted);padding:2rem;">Error cargando tareas.</p>'; return; }
    if (!d.data.length) { el.innerHTML = '<p class="text-center" style="color:var(--muted);padding:2rem;">No hay tareas asignadas.</p>'; return; }

    el.innerHTML = d.data.map(t => {
      let actionHtml = '';
      if (CAN_MANAGE) {
        actionHtml = `<button class="btn btn-sm btn-primary" onclick="abrirVerEntregas(${t.id}, '${h(t.titulo).replace(/'/g,"\\'")}')">🔍 Ver y Calificar Entregas</button>`;
      } else {
        if (t.entrega_id) {
          const notaText = t.nota !== null ? `<span style="color:#059669;font-weight:bold;">Calificada: ${t.nota}/${t.nota_maxima}</span>` : '<span style="color:#d97706;font-weight:bold;">Entregado - Esperando nota</span>';
          actionHtml = `<div style="display:flex;align-items:center;gap:1rem;">
                          <span style="font-size:.85rem;">${notaText}</span>
                          <button class="btn btn-sm btn-secondary" onclick="abrirSubirEntrega(${t.id}, '${h(t.titulo).replace(/'/g,"\\'")}')">🔄 Actualizar Entrega</button>
                        </div>`;
        } else {
          actionHtml = `<button class="btn btn-sm btn-primary" onclick="abrirSubirEntrega(${t.id}, '${h(t.titulo).replace(/'/g,"\\'")}')">📤 Entregar Tarea</button>`;
        }
      }

      const archivoHtml = t.archivo ? `<br><a href="uploads/tareas/${t.archivo}" target="_blank" style="font-size:.8rem;color:var(--primary);text-decoration:underline;">📎 Descargar adjunto de la tarea</a>` : '';

      return `
      <div class="card" style="margin-bottom:.9rem;border-left:4px solid var(--primary);">
        <div class="card-body">
          <div style="display:flex;justify-content:space-between;align-items:flex-start;">
            <div>
              <h3 style="font-size:1.1rem;margin-bottom:.2rem;">${h(t.titulo)}</h3>
              <p style="font-size:.85rem;color:var(--muted);margin-bottom:.5rem;">Vence: ${new Date(t.fecha_limite).toLocaleString()}</p>
              <p style="font-size:.9rem;color:var(--ink);white-space:pre-wrap;margin-bottom:.5rem;">${h(t.descripcion)}</p>
              ${archivoHtml}
            </div>
          </div>
          <div style="margin-top:1rem;border-top:1px solid var(--border);padding-top:.8rem;display:flex;justify-content:flex-end;">
            ${actionHtml}
          </div>
        </div>
      </div>`;
    }).join('');
  } catch(e) { console.error(e); }
}

function abrirTareaModal() {
  document.getElementById('fTarea').reset();
  openModal('mTarea');
}

async function guardarTarea(e) {
  e.preventDefault();
  const btn = document.getElementById('btnGuardarTarea');
  btn.disabled = true; btn.textContent = 'Guardando...';

  const fd = new FormData(e.target);
  fd.append('action', 'crear');
  fd.append('materia_id', MATERIA_ID);
  const _csrfT1 = document.querySelector('meta[name="csrf-token"]');
  if (_csrfT1) fd.append('csrf_token', _csrfT1.content);

  try {
    const r = await fetch('api/tareas.php', { method: 'POST', body: fd });
    const text = await r.text();
    const d = JSON.parse(text);
    if (d.ok) { toast(d.msg); closeModal('mTarea'); loadTareas(); }
    else Ibbs.error(d.msg);
  } catch(err) { console.log(err); Ibbs.error('Error al guardar la tarea. Revisa tu api/tareas.php'); }
  btn.disabled = false; btn.textContent = 'Publicar Tarea';
}

function abrirSubirEntrega(tarea_id, titulo) {
  document.getElementById('fEntrega').reset();
  document.getElementById('entTareaId').value = tarea_id;
  document.getElementById('mSubirTitulo').textContent = 'Entregar: ' + titulo;
  openModal('mSubirEntrega');
}

async function guardarEntrega(e) {
  e.preventDefault();
  const btn = document.getElementById('btnSubirEntrega');
  btn.disabled = true; btn.textContent = 'Subiendo...';

  const fd = new FormData(e.target);
  fd.append('action', 'entregar');
  fd.append('materia_id', MATERIA_ID);
  const _csrfT2 = document.querySelector('meta[name="csrf-token"]');
  if (_csrfT2) fd.append('csrf_token', _csrfT2.content);

  try {
    const r = await fetch('api/tareas.php', { method: 'POST', body: fd });
    const text = await r.text();
    const d = JSON.parse(text);
    if (d.ok) { toast(d.msg); closeModal('mSubirEntrega'); loadTareas(); }
    else Ibbs.error(d.msg);
  } catch(err) { Ibbs.error('Error subiendo la entrega.'); }
  btn.disabled = false; btn.textContent = 'Enviar Entrega';
}

async function abrirVerEntregas(tarea_id, titulo) {
  document.getElementById('mVerEntTitulo').textContent = 'Entregas: ' + titulo;
  const tb = document.getElementById('tbodyEntregas');
  tb.innerHTML = '<tr class="empty-row"><td colspan="6"><span class="spin"></span></td></tr>';
  openModal('mVerEntregas');

  try {
    const r = await fetch(`api/tareas.php?action=ver_entregas&materia_id=${MATERIA_ID}&tarea_id=${tarea_id}`);
    const text = await r.text();
    let d;
    try {
        d = JSON.parse(text);
    } catch(err) {
        tb.innerHTML = `<tr><td colspan="6" style="color:#dc2626;padding:1rem;">Error de servidor. Revisa la consola (F12).</td></tr>`;
        console.error("Respuesta cruda:", text);
        return;
    }

    if (!d?.ok) { tb.innerHTML = `<tr class="empty-row"><td colspan="6">${d.msg}</td></tr>`; return; }
    if (!d.data.length) { tb.innerHTML = '<tr class="empty-row"><td colspan="6">No hay alumnos inscritos en esta materia.</td></tr>'; return; }

    tb.innerHTML = d.data.map(e => {
      const estado = e.entrega_id ? '<span class="badge b-presente">Entregado</span>' : '<span class="badge b-ausente">No entregado</span>';

      let contenidoHtml = '—';
      if(e.entrega_id) {
          contenidoHtml = '';
          if(e.texto_respuesta) contenidoHtml += `<div style="font-size:.8rem;max-height:60px;overflow-y:auto;border:1px solid #eee;padding:4px;margin-bottom:4px;">${h(e.texto_respuesta)}</div>`;
          if(e.archivo) contenidoHtml += `<a href="uploads/entregas/${e.archivo}" target="_blank" style="font-size:.8rem;color:var(--primary);text-decoration:underline;">Descargar archivo</a>`;
      }

      return `<tr>
        <td style="text-align:left;font-weight:bold;">${h(e.apellido)}, ${h(e.nombre)}<br><span style="font-size:.7rem;font-weight:normal;color:var(--muted);">${e.cedula}</span></td>
        <td>${estado}</td>
        <td style="text-align:left;max-width:200px;">${contenidoHtml}</td>
        <td><input type="number" id="n_${e.entrega_id}" value="${e.nota!==null?e.nota:''}" style="width:60px;text-align:center;padding:4px;border:1px solid #ccc;border-radius:4px;" ${!e.entrega_id?'disabled':''}></td>
        <td><input type="text" id="o_${e.entrega_id}" value="${h(e.observacion_docente)}" placeholder="Opcional" style="width:100%;padding:4px;border:1px solid #ccc;border-radius:4px;" ${!e.entrega_id?'disabled':''}></td>
        <td>${e.entrega_id ? `<button class="btn btn-sm btn-success" onclick="calificarEntrega(${e.entrega_id})">Guardar</button>` : ''}</td>
      </tr>`;
    }).join('');
  } catch(err) { console.log(err); }
}

async function calificarEntrega(entrega_id) {
  const nota = document.getElementById(`n_${entrega_id}`).value;
  const obs = document.getElementById(`o_${entrega_id}`).value;

  const fd = new URLSearchParams();
  fd.append('action', 'calificar');
  const _csrfT3 = document.querySelector('meta[name="csrf-token"]');
  if (_csrfT3) fd.append('csrf_token', _csrfT3.content);
  fd.append('entrega_id', entrega_id);
  fd.append('nota', nota);
  fd.append('observacion', obs);

  try {
    const r = await fetch('api/tareas.php', { method: 'POST', body: fd });
    const text = await r.text();
    const d = JSON.parse(text);
    if(d.ok) toast('Calificación guardada'); else Ibbs.error(d.msg);
  } catch(e) { Ibbs.error('Error al guardar nota'); }
}

/* ══ FORO / DUDAS ══ */
async function loadForo() {
  if (!MATERIA_ID) return;
  try {
    const r = await fetch(`api/foro.php?action=get_mensajes&materia_id=${MATERIA_ID}`);
    const text = await r.text();

    try {
        const mensajes = JSON.parse(text);
        if (mensajes.error) {
            console.error("Error del servidor:", mensajes.error, mensajes.detalle);
            return;
        }
        if (mensajes.length !== lastMessageCount) {
          lastMessageCount = mensajes.length;
          renderMessages(mensajes);
        }
    } catch(err) {
        console.error("El servidor devolvió un error HTML en lugar de JSON:", text);
    }
  } catch (e) { console.error("Error cargando foro", e); }
}

function renderMessages(mensajes) {
  const chatBox = document.getElementById('chat-box');
  chatBox.innerHTML = '';

  if(mensajes.length === 0) {
    chatBox.innerHTML = '<div style="text-align:center;padding:2rem;color:var(--muted);font-size:.9rem;">No hay mensajes aún en esta materia. ¡Rompe el hielo!</div>';
    return;
  }

  const hilos = {};
  mensajes.forEach(m => {
    if (m.respuesta_a === null) hilos[m.id] = { ...m, respuestas: [] };
  });
  mensajes.forEach(m => {
    if (m.respuesta_a !== null && hilos[m.respuesta_a]) hilos[m.respuesta_a].respuestas.push(m);
  });

  for (const id in hilos) {
    const thread = hilos[id];
    chatBox.insertAdjacentHTML('beforeend', createMessageHTML(thread, false));

    if (thread.respuestas.length > 0) {
      const repliesContainer = document.createElement('div');
      repliesContainer.style.cssText = "margin-left:2.5rem; margin-top:.5rem; padding-left:1rem; border-left:2px solid var(--border); display:flex; flex-direction:column; gap:.5rem;";
      thread.respuestas.forEach(r => {
        repliesContainer.insertAdjacentHTML('beforeend', createMessageHTML(r, true));
      });
      chatBox.appendChild(repliesContainer);
    }
  }
  chatBox.scrollTop = chatBox.scrollHeight;
}

function createMessageHTML(msg, isReply) {
  const isMe = msg.usuario_nombre === CURRENT_USER;
  const bg = isMe ? 'background:var(--bubble-mine-bg); border:1px solid var(--bubble-mine-border);' : 'background:var(--paper); border:1px solid var(--border);';

  let badge = '';
  if (msg.rol === 'profesor') {
      badge = '<span class="badge b-tardanza" style="font-size:.65rem;margin-left:.4rem;">Profesor</span>';
  } else if (msg.rol === 'admin' || msg.rol === 'superadmin') {
      badge = '<span class="badge b-profesor" style="font-size:.65rem;margin-left:.4rem;">Admin</span>';
  } else if (msg.rol === 'alumno') {
      badge = '<span class="badge b-presente" style="font-size:.65rem;margin-left:.4rem;">Alumno</span>';
  }

  const dateStr = new Date(msg.fecha).toLocaleString([], {month:'short', day:'numeric', hour: '2-digit', minute:'2-digit'});
  const replyBtn = !isReply ? `<button type="button" onclick="setReply(${msg.id}, '${h(msg.usuario_nombre)}')" style="background:none;border:none;color:var(--primary);cursor:pointer;font-size:.8rem;margin-top:.4rem;padding:0;">Responder</button>` : '';
  const delBtn = msg.puede_borrar ? `<button type="button" onclick="borrarMensajeForo(${msg.id})" style="background:none;border:none;color:var(--red);cursor:pointer;font-size:.8rem;margin-top:.4rem;padding:0;margin-left:.8rem;">Borrar</button>` : '';

  return `
    <div style="padding:.8rem 1rem; border-radius:8px; ${bg}">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.3rem;">
            <strong style="font-size:.9rem;color:var(--ink);">${h(msg.usuario_nombre)} ${badge}</strong>
            <span style="font-size:.75rem;color:var(--muted);">${dateStr}</span>
        </div>
        <p style="margin:0;font-size:.9rem;color:var(--ink);white-space:pre-wrap;line-height:1.4;">${h(msg.mensaje)}</p>
        ${replyBtn}${delBtn}
    </div>
  `;
}

async function borrarMensajeForo(id) {
  const rr = await Ibbs.confirm({title:'¿Borrar mensaje?',text:'Esta acción no se puede deshacer.',confirm:'Sí, borrar',danger:true});
  if (!rr.isConfirmed) return;
  try {
    const _csrfMeta = document.querySelector('meta[name="csrf-token"]');
    const r = await fetch(`api/foro.php?action=delete_mensaje&materia_id=${MATERIA_ID}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id, csrf_token: _csrfMeta ? _csrfMeta.content : '' })
    });
    const result = await r.json();
    if (result.success) { lastMessageCount = -1; loadForo(); }
    else toast(result.error || 'No se pudo borrar el mensaje.', 'err');
  } catch (e) { toast('Error al borrar el mensaje.', 'err'); }
}

window.setReply = function(id, nombre) {
  document.getElementById('respuesta_a').value = id;
  document.getElementById('reply-to-name').textContent = nombre;
  document.getElementById('reply-indicator').style.display = 'flex';
  document.getElementById('mensaje-input').focus();
};

window.cancelReply = function() {
  document.getElementById('respuesta_a').value = '';
  document.getElementById('reply-indicator').style.display = 'none';
};

document.getElementById('chat-form').addEventListener('submit', async (e) => {
  e.preventDefault();
  const input = document.getElementById('mensaje-input');
  const mensaje = input.value.trim();
  const respuesta_a = document.getElementById('respuesta_a').value;

  if (!mensaje) return;

  input.value = '';
  cancelReply();

  try {
    const _csrfMeta = document.querySelector('meta[name="csrf-token"]');
    const r = await fetch(`api/foro.php?action=post_mensaje&materia_id=${MATERIA_ID}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ mensaje, respuesta_a, csrf_token: _csrfMeta ? _csrfMeta.content : '' })
    });
    const text = await r.text();

    try {
        const result = JSON.parse(text);
        if(result.success) loadForo();
        else console.error("Error del servidor:", result.error);
    } catch(err) {
        console.error("El servidor devolvió HTML en vez de JSON al enviar:", text);
    }
  } catch (error) { console.error("Error enviando:", error); }
});

document.getElementById('mensaje-input').addEventListener('keydown', function(e) {
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault();
    document.getElementById('chat-form').dispatchEvent(new Event('submit'));
  }
});

</script>

<?php include __DIR__.'/layout/foot.php'; ?>
