<?php
$page_title  = 'Devocionales';
$page_sub    = 'Estudios y planes devocionales para alumnos y docentes';
$active_link = 'devocionales';
include __DIR__.'/layout/head.php';
if(!in_array($_rol,['superadmin','admin'])){
    echo '<script>window.location="index.php";</script>'; exit;
}
$con = db();
$materias_list = [];
$rm = mysqli_query($con, "SELECT id,nombre FROM materias WHERE activo=1 ORDER BY nombre");
while($f = mysqli_fetch_assoc($rm)) $materias_list[] = $f;
mysqli_close($con);
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.2rem;flex-wrap:wrap;gap:.6rem;">
  <p style="font-size:.84rem;color:var(--muted);max-width:640px;">Un estudio bíblico, plan devocional u otro material que llega como notificación ("Devocional de parte de...") a los alumnos y docentes a los que corresponda — toda la institución, o solo una materia puntual.</p>
  <button class="btn btn-primary" onclick="openModal('mNuevoDevo')"><i class="bx bx-plus"></i> Nuevo Devocional</button>
</div>

<div id="devoLista" style="display:flex;flex-direction:column;gap:1rem;">
  <p style="font-size:.85rem;color:var(--muted);font-style:italic;">Cargando…</p>
</div>

<!-- MODAL: NUEVO DEVOCIONAL -->
<div class="modal-backdrop" id="mNuevoDevo">
  <div class="modal">
    <div class="modal-head"><h3>Nuevo Devocional</h3>
      <button class="modal-close" onclick="closeModal('mNuevoDevo')"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
    </div>
    <div class="modal-body">
      <form id="formNuevoDevo" onsubmit="submitNuevoDevocional(event)" enctype="multipart/form-data">
        <div class="form-grid" style="margin-bottom:1rem;">
          <div class="field field-full">
            <label>Título *</label>
            <input type="text" name="titulo" placeholder="Ej. Estudio de la semana — Salmo 23" required>
          </div>
          <div class="field field-full">
            <label>Para quién</label>
            <select name="materia_id">
              <option value="">— Toda la institución (alumnos y docentes) —</option>
              <?php foreach($materias_list as $m): ?>
              <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['nombre']) ?> (solo esa materia)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field field-full">
            <label>Contenido (opcional si adjuntas un archivo)</label>
            <textarea name="contenido" rows="4" placeholder="Escribe el devocional acá, o deja esto vacío y adjunta un PDF/Word abajo…"></textarea>
          </div>
          <div class="field field-full">
            <label>Archivo adjunto (opcional — PDF, Word, PowerPoint o imagen, máx. 25MB)</label>
            <input type="file" name="archivo" accept=".pdf,.doc,.docx,.ppt,.pptx,image/png,image/jpeg,image/webp">
          </div>
        </div>
        <div style="display:flex;justify-content:flex-end;gap:.6rem;">
          <button type="button" class="btn btn-secondary" onclick="closeModal('mNuevoDevo')">Cancelar</button>
          <button type="submit" class="btn btn-primary" id="btnPublicarDevocional">Publicar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
const MI_USUARIO_ID_DEVO = <?= (int)($_SESSION['user_id'] ?? 0) ?>;
function hDevo(s) { const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML; }
function _devoIconoArchivo(nombre) {
    const ext = (nombre || '').split('.').pop().toLowerCase();
    if (['jpg','jpeg','png','webp'].includes(ext)) return 'bx-image';
    if (ext === 'pdf') return 'bxs-file-pdf';
    if (['doc','docx'].includes(ext)) return 'bxs-file-doc';
    if (['ppt','pptx'].includes(ext)) return 'bxs-slideshow';
    return 'bx-file';
}
async function cargarDevocionalesAdmin() {
    const cont = document.getElementById('devoLista');
    cont.innerHTML = '<p style="font-size:.85rem;color:var(--muted);font-style:italic;">Cargando…</p>';
    const d = await ajax('listar', {}, 'api/devocionales.php');
    if (!d || !d.ok || !d.data.length) { cont.innerHTML = '<p style="font-size:.85rem;color:var(--muted);font-style:italic;">Todavía no hay devocionales publicados.</p>'; return; }
    cont.innerHTML = d.data.map(dv => `
        <div class="card" style="padding:1.2rem 1.4rem;">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:.8rem;flex-wrap:wrap;margin-bottom:.5rem;">
                <div>
                    <h3 style="font-size:1rem;font-weight:700;color:var(--ink);margin:0;">${hDevo(dv.titulo)}</h3>
                    <p style="font-size:.76rem;color:var(--muted);margin:.2rem 0 0;">Por ${hDevo(dv.publicado_por_nombre)}${dv.materia_nombre ? ' · ' + hDevo(dv.materia_nombre) : ' · Para toda la institución'} · ${dv.creado_en ? dv.creado_en.substring(0,10) : ''}</p>
                </div>
                <button onclick="eliminarDevocionalAdmin(${dv.id})" style="background:none;border:none;color:var(--red);cursor:pointer;font-size:.9rem;" title="Eliminar"><i class="bx bx-trash"></i></button>
            </div>
            ${dv.contenido ? `<p style="font-size:.88rem;color:var(--ink);white-space:pre-line;margin-bottom:.6rem;">${hDevo(dv.contenido)}</p>` : ''}
            ${dv.archivo ? `<a href="${dv.archivo}" target="_blank" style="display:inline-flex;align-items:center;gap:.5rem;font-size:.78rem;font-weight:700;background:var(--cream);border:1px solid var(--border);padding:.5rem .8rem;border-radius:8px;text-decoration:none;color:var(--ink);"><i class="bx ${_devoIconoArchivo(dv.archivo_nombre)}"></i> ${hDevo(dv.archivo_nombre || 'Ver archivo')}</a>` : ''}
        </div>`).join('');
}

async function submitNuevoDevocional(e) {
    e.preventDefault();
    const form = e.target;
    const btn = document.getElementById('btnPublicarDevocional');
    btn.disabled = true; btn.textContent = 'Publicando…';
    const fd = new FormData(form);
    fd.append('action', 'crear');
    const m = document.querySelector('meta[name="csrf-token"]'); fd.append('csrf_token', m ? m.content : '');
    try {
        const r = await fetch('api/devocionales.php', { method: 'POST', body: fd });
        const d = await r.json();
        if (d.ok) {
            closeModal('mNuevoDevo');
            form.reset();
            toast(d.msg);
            cargarDevocionalesAdmin();
        } else Ibbs.error(d.msg || 'No se pudo publicar.');
    } catch (err) { console.error(err); Ibbs.error('Error de conexión.'); }
    btn.disabled = false; btn.textContent = 'Publicar';
}

async function eliminarDevocionalAdmin(id) {
    const r = await Ibbs.confirm({ title: 'Eliminar devocional', text: '¿Seguro que quieres eliminarlo? Esta acción no se puede deshacer.', danger: true, confirm: 'Sí, eliminar' });
    if (!r.isConfirmed) return;
    const d = await ajax('eliminar', { id }, 'api/devocionales.php');
    if (d && d.ok) { toast(d.msg); cargarDevocionalesAdmin(); } else Ibbs.error((d && d.msg) || 'No se pudo eliminar.');
}

document.addEventListener('ibbs:ready', () => cargarDevocionalesAdmin());
</script>

<?php include __DIR__.'/layout/foot.php'; ?>
