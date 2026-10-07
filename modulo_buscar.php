<?php
$page_title = 'Buscar Personal';
$page_sub   = 'Alumno o docente — por nombre o cédula';
$active_link = 'buscar';
include __DIR__.'/layout/head.php';
// Acceso admin o superadmin
if(!in_array($_rol,['superadmin','admin'])){
    echo '<script>window.location="index.php";</script>'; exit;
}
?>

<div class="card" style="max-width:560px;margin-bottom:1.5rem;">
  <div class="card-body">
    <div style="display:flex;gap:.7rem;">
      <div class="field" style="flex:1;margin:0;">
        <label>Nombre, apellido o cédula</label>
        <input id="inputBusqueda" placeholder="Ej. María Pérez, o V-12345678" oninput="buscarEnVivo()" onkeydown="if(event.key==='Enter')buscarEnVivo(true)">
      </div>
      <button class="btn btn-primary" style="align-self:flex-end;" onclick="buscarEnVivo(true)" id="btnBuscar">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        Buscar
      </button>
    </div>
    <!-- Resultados de la búsqueda — aparece una lista si hay varias coincidencias -->
    <div id="listaResultados" style="display:none;margin-top:1rem;border-top:1px solid var(--border);padding-top:.8rem;max-height:280px;overflow-y:auto;"></div>
  </div>
</div>

<div id="resultArea" style="display:none;"></div>

<div id="emptyState" style="text-align:center;padding:4rem 1rem;color:var(--muted);">
  <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom:1rem;opacity:.4;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
  <p style="font-family:'DM Serif Display',serif;font-size:1.4rem;margin-bottom:.4rem;">Buscá por nombre o cédula</p>
  <p style="font-size:.84rem;">Escribí al menos 2 letras del nombre, apellido o la cédula de un alumno o docente.</p>
</div>

<!-- MODAL: actividades de una materia (Plan de Notas) para el alumno elegido -->
<div class="modal-backdrop" id="mPlanAlumno">
  <div class="modal">
    <div class="modal-head">
      <h3 id="mPlanAlumnoTitulo">Plan de Notas</h3>
      <button class="modal-close" onclick="closeModal('mPlanAlumno')"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
    </div>
    <div class="modal-body">
      <div id="mPlanAlumnoBody" style="padding:1rem;text-align:center;color:var(--muted);"><span class="spin"></span></div>
    </div>
  </div>
</div>

<script>
let _buscarTimer = null;
function buscarEnVivo(inmediato) {
  clearTimeout(_buscarTimer);
  const q = document.getElementById('inputBusqueda').value.trim();
  if (q.length < 2) { document.getElementById('listaResultados').style.display = 'none'; return; }
  _buscarTimer = setTimeout(() => buscarPersonas(q), inmediato ? 0 : 300);
}

async function buscarPersonas(q) {
  const btn = document.getElementById('btnBuscar');
  btn.disabled = true;
  const d = await ajax('buscar_personas', {q});
  btn.disabled = false;
  const lista = document.getElementById('listaResultados');
  if (!d?.ok || !d.data.length) {
    lista.style.display = 'block';
    lista.innerHTML = '<p style="font-size:.84rem;color:var(--muted);padding:.5rem;">Sin coincidencias.</p>';
    return;
  }
  lista.style.display = 'block';
  lista.innerHTML = d.data.map(p => `
    <div onclick="verPersona(${p.id},'${p.tipo}')" style="display:flex;align-items:center;gap:.7rem;padding:.6rem .5rem;border-radius:8px;cursor:pointer;transition:background .15s;" onmouseover="this.style.background='var(--cream)'" onmouseout="this.style.background='transparent'">
      <div style="width:34px;height:34px;border-radius:50%;background:#1a4d2e;color:#f5f0e8;display:flex;align-items:center;justify-content:center;font-weight:700;flex-shrink:0;">${h((p.nombre||'?')[0])}</div>
      <div style="flex:1;">
        <div style="font-weight:700;font-size:.86rem;color:var(--ink);">${h(p.apellido)}, ${h(p.nombre)}</div>
        <div style="font-size:.74rem;color:var(--muted);">CI: ${h(p.cedula)}</div>
      </div>
      <span class="badge ${p.tipo==='alumno'?'b-alumno':'b-profesor'}">${p.tipo==='alumno'?'Alumno':'Docente'}</span>
    </div>`).join('');
}

function h(s) { const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML; }

let _personaActual = null;

async function verPersona(id, tipo) {
  document.getElementById('listaResultados').style.display = 'none';
  const d = await ajax('persona_detalle', {id, tipo});
  const area = document.getElementById('resultArea');
  const empty = document.getElementById('emptyState');
  if (!d?.ok) {
    area.style.display = 'none'; empty.style.display = 'block';
    empty.innerHTML = `<p style="font-family:'DM Serif Display',serif;font-size:1.4rem;margin-bottom:.4rem;">Sin resultados</p><p style="font-size:.84rem;">${h(d?.msg || 'No se encontró el registro.')}</p>`;
    return;
  }
  empty.style.display = 'none';
  area.style.display = 'block';
  _personaActual = { id, tipo };

  const r = d.data;
  const ini = (r.nombre || '?')[0].toUpperCase();
  const asist = r.asistencias || {};
  const totalAsist = Object.values(asist).reduce((a, b) => a + b, 0);

  let html = `
    <div class="profile-card" style="margin-bottom:1.5rem;">
      <div style="display:flex;align-items:flex-start;gap:1.2rem;flex-wrap:wrap;">
        <div class="profile-ava">${h(ini)}</div>
        <div style="flex:1;">
          <div class="profile-name">${h(r.nombre)} ${h(r.apellido)}</div>
          <div class="profile-meta" style="margin-bottom:.3rem;">Cédula: <strong style="color:rgba(255,255,255,.7)">${h(r.cedula)}</strong></div>
          <div class="profile-meta">📧 ${h(r.correo)}${r.telefono ? ' · 📞 ' + h(r.telefono) : ''}</div>
          <div class="profile-chips" style="margin-top:.8rem;">
            <span class="profile-chip lime">${tipo === 'alumno' ? 'Alumno' : 'Docente'}</span>
            ${tipo === 'alumno' && r.ciudad ? `<span class="profile-chip">${h(r.ciudad)}</span>` : ''}
            ${tipo === 'docente' && r.especialidad ? `<span class="profile-chip">${h(r.especialidad)}</span>` : ''}
            <span class="profile-chip ${r.activo ? 'lime' : ''}">${r.activo ? 'Activo' : 'Inactivo'}</span>
          </div>
        </div>
      </div>
    </div>`;

  if (tipo === 'alumno') {
    const materias = r.materias || [];
    const conNota = materias.filter(m => m.nota_final !== null);
    const prom = conNota.length ? (conNota.reduce((a, m) => a + parseFloat(m.nota_final), 0) / conNota.length).toFixed(1) : '—';
    const aprobadas = conNota.filter(m => parseFloat(m.nota_final) >= 15).length;
    const reprobadas = conNota.filter(m => parseFloat(m.nota_final) < 15).length;

    html += `
    <div class="stats" style="margin-bottom:1.5rem;">
      <div class="scard c1"><div class="scard-ico"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg></div><div><div class="scard-val" data-countup>${materias.length}</div><div class="scard-key">Materias</div></div></div>
      <div class="scard c3"><div class="scard-ico"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 11 12 14 22 4"/></svg></div><div><div class="scard-val" data-countup>${totalAsist}</div><div class="scard-key">Asistencias</div></div></div>
      <div class="scard c2"><div class="scard-ico"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16"/><polyline points="14 2 14 8 20 8"/></svg></div><div><div class="scard-val" data-countup>${prom}</div><div class="scard-key">Promedio (0-20)</div></div></div>
      <div class="scard c4"><div class="scard-ico"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div><div><div class="scard-val" data-countup>${aprobadas}/${reprobadas}</div><div class="scard-key">Aprobadas/Reprobadas</div></div></div>
    </div>`;

    html += `<div class="card" style="margin-bottom:1.2rem;">
      <div class="card-head"><h3>Asistencias</h3></div>
      <div class="card-body" style="display:flex;gap:.6rem;flex-wrap:wrap;">
        ${Object.entries({presente:'b-presente',ausente:'b-ausente',tardanza:'b-tardanza',justificado:'b-justificado'})
          .map(([k,cls])=>`<span class="badge ${cls}" style="font-size:.78rem;padding:4px 12px;">${k.charAt(0).toUpperCase()+k.slice(1)}: <strong>${asist[k]||0}</strong></span>`).join('')}
      </div>
    </div>`;

    html += `<div class="card">
      <div class="card-head"><h3>Materias inscritas — cómo va en cada una</h3></div>
      <div class="tbl-wrap"><table>
        <thead><tr><th style="text-align:left;">Código</th><th style="text-align:left;">Materia</th><th>Estado</th><th>Nota final</th><th>Resultado</th><th></th></tr></thead>
        <tbody>${materias.length ? materias.map(m => {
          const tieneNota = m.nota_final !== null;
          const aprobada = tieneNota && parseFloat(m.nota_final) >= 15;
          return `<tr>
            <td style="text-align:left;"><strong>${h(m.codigo)}</strong></td>
            <td style="text-align:left;">${h(m.nombre)}</td>
            <td><span class="badge ${m.estado==='en_curso'?'b-presente':m.estado==='culminada'?'b-alumno':'b-tardanza'}">${h(m.estado||'—')}</span></td>
            <td style="font-family:'DM Serif Display',serif;font-size:1.05rem;color:${tieneNota ? (aprobada?'#16a34a':'#dc2626') : 'var(--muted)'};">${tieneNota ? parseFloat(m.nota_final).toFixed(1) : '—'}</td>
            <td><span class="badge ${!tieneNota ? '' : (aprobada?'b-presente':'b-ausente')}">${!tieneNota ? 'Sin nota' : (aprobada?'Aprobado':'Reprobado')}</span></td>
            <td><button class="btn btn-secondary btn-sm" onclick="verPlanAlumnoMateria(${m.id},'${h(m.nombre).replace(/'/g,"\\'")}')"><i class="bx bx-list-check"></i> Plan de Notas</button></td>
          </tr>`;
        }).join('') : '<tr class="empty-row"><td colspan="6">Sin materias inscritas.</td></tr>'}</tbody>
      </table></div>
    </div>`;
  } else {
    html += `
    <div class="stats" style="margin-bottom:1.5rem;">
      <div class="scard c1"><div class="scard-ico"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/></svg></div><div><div class="scard-val" data-countup>${r.materias.length}</div><div class="scard-key">Materias</div></div></div>
      <div class="scard c3"><div class="scard-ico"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 11 12 14 22 4"/></svg></div><div><div class="scard-val" data-countup>${totalAsist}</div><div class="scard-key">Asistencias reg.</div></div></div>
    </div>
    <div class="card" style="margin-bottom:1.2rem;">
      <div class="card-head"><h3>Materias que dicta</h3></div>
      <div class="tbl-wrap"><table>
        <thead><tr><th>Código</th><th>Materia</th><th>Días</th><th>Horario</th></tr></thead>
        <tbody>${r.materias.length?r.materias.map(m=>`
          <tr>
            <td><strong>${h(m.codigo)}</strong></td>
            <td>${h(m.nombre)}</td>
            <td>${h(m.dias||'—')}</td>
            <td style="font-size:.82rem;">${m.hora_inicio?m.hora_inicio.substring(0,5):'—'}${m.hora_fin?'–'+m.hora_fin.substring(0,5):''}</td>
          </tr>`).join(''):'<tr class="empty-row"><td colspan="4">Sin materias asignadas.</td></tr>'}
        </tbody>
      </table></div>
    </div>
    <div class="card">
      <div class="card-head"><h3>Registro de Asistencias</h3></div>
      <div class="card-body" style="display:flex;gap:.6rem;flex-wrap:wrap;">
        ${Object.entries({presente:'b-presente',ausente:'b-ausente',tardanza:'b-tardanza',justificado:'b-justificado'})
          .map(([k,cls])=>`<span class="badge ${cls}" style="font-size:.78rem;padding:4px 12px;">${k.charAt(0).toUpperCase()+k.slice(1)}: <strong>${asist[k]||0}</strong></span>`).join('')}
      </div>
    </div>`;
  }

  area.innerHTML = html;
}

// Desglose de actividades de una materia puntual, filtrado a este
// alumno — reutiliza el mismo Plan de Notas del docente (el admin ya
// tiene permiso sobre cualquier materia).
async function verPlanAlumnoMateria(materiaId, materiaNombre) {
  document.getElementById('mPlanAlumnoTitulo').textContent = 'Plan de Notas — ' + materiaNombre;
  const body = document.getElementById('mPlanAlumnoBody');
  body.innerHTML = '<span class="spin"></span>';
  openModal('mPlanAlumno');

  const fd = new FormData(); fd.append('accion', 'docente'); fd.append('materia_id', materiaId);
  const m = document.querySelector('meta[name="csrf-token"]'); fd.append('csrf_token', m ? m.content : '');
  let d;
  try {
    const r = await fetch('api/plan_notas.php', { method: 'POST', body: fd });
    d = await r.json();
  } catch (e) { body.innerHTML = '<p style="color:var(--red);">Error de conexión.</p>'; return; }
  if (!d.ok) { body.innerHTML = `<p style="color:var(--red);">${h(d.msg || 'Error al cargar.')}</p>`; return; }

  const { tareas, notas } = d.data;
  if (!tareas.length) { body.innerHTML = '<p style="color:var(--muted);">Esta materia todavía no tiene actividades creadas.</p>'; return; }
  const notasAlumno = (notas && notas[_personaActual.id]) || {};

  body.innerHTML = `<div class="tbl-wrap"><table>
    <thead><tr><th style="text-align:left;">Actividad</th><th style="text-align:center;">%</th><th style="text-align:center;">Nota</th><th>Estado</th></tr></thead>
    <tbody>${tareas.map(t => {
      const reg = notasAlumno[t.id] || null;
      const nota = reg && reg.nota !== null ? parseFloat(reg.nota) : null;
      const aprobada = nota !== null && nota >= 15;
      return `<tr>
        <td style="text-align:left;font-weight:700;">${h(t.titulo)}</td>
        <td style="text-align:center;color:var(--muted);">${t.porcentaje !== null ? parseFloat(t.porcentaje) + '%' : '—'}</td>
        <td style="text-align:center;font-family:'DM Serif Display',serif;color:${nota===null?'var(--muted)':(aprobada?'#16a34a':'#dc2626')};">${nota !== null ? nota.toFixed(1) : '—'}</td>
        <td><span class="badge ${nota===null?'b-tardanza':(aprobada?'b-presente':'b-ausente')}">${nota===null?'Sin calificar':(aprobada?'Aprobado':'Reprobado')}</span></td>
      </tr>`;
    }).join('')}</tbody>
  </table></div>`;
}
</script>
<?php include __DIR__.'/layout/foot.php'; ?>
