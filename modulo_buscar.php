<?php
$page_title = 'Buscar Personal';
$page_sub   = 'Búsqueda por cédula — alumno o docente';
$active_link = 'buscar';
include __DIR__.'/layout/head.php';
// Acceso admin o superadmin
if(!in_array($_rol,['superadmin','admin'])){
    echo '<script>window.location="index.php";</script>'; exit;
}

?>

<div class="card" style="max-width:520px;margin-bottom:1.5rem;">
  <div class="card-body">
    <div style="display:flex;gap:.7rem;">
      <div class="field" style="flex:1;margin:0;">
        <label>Cédula (alumno o docente)</label>
        <input id="inputCedula" placeholder="Ej. V-12345678" onkeydown="if(event.key==='Enter')buscar()">
      </div>
      <button class="btn btn-primary" style="align-self:flex-end;" onclick="buscar()" id="btnBuscar">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        Buscar
      </button>
    </div>
  </div>
</div>

<div id="resultArea" style="display:none;"></div>

<div id="emptyState" style="text-align:center;padding:4rem 1rem;color:var(--muted);">
  <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom:1rem;opacity:.4;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
  <p style="font-family:'DM Serif Display',serif;font-size:1.4rem;margin-bottom:.4rem;">Busca por cédula</p>
  <p style="font-size:.84rem;">Ingresa la cédula de un alumno o docente para ver su perfil completo.</p>
</div>

<script>
async function buscar(){
  const ced=document.getElementById('inputCedula').value.trim();
  if(!ced){Ibbs.error('Ingresa una cédula para buscar.','Campo requerido');return;}
  const btn=document.getElementById('btnBuscar');
  btn.disabled=true;
  const d=await ajax('buscar_cedula',{cedula:ced});
  btn.disabled=false;
  const area=document.getElementById('resultArea');
  const empty=document.getElementById('emptyState');
  if(!d||!d.ok){area.style.display='none';empty.style.display='block';empty.innerHTML=`
    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom:1rem;opacity:.4;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
    <p style="font-family:'DM Serif Display',serif;font-size:1.4rem;margin-bottom:.4rem;">Sin resultados</p>
    <p style="font-size:.84rem;">${d?.msg||'No se encontró el registro.'}</p>
  `;return;}

  empty.style.display='none';
  area.style.display='block';
  const r=d.data;
  const tipo=d.tipo;
  const ini=(r.nombre||'?')[0].toUpperCase();
  const asist=r.asistencias||{};
  const totalAsist=Object.values(asist).reduce((a,b)=>a+b,0);

  let html=`
    <div class="profile-card" style="margin-bottom:1.5rem;">
      <div style="display:flex;align-items:flex-start;gap:1.2rem;flex-wrap:wrap;">
        <div class="profile-ava">${ini}</div>
        <div style="flex:1;">
          <div class="profile-name">${r.nombre} ${r.apellido}</div>
          <div class="profile-meta" style="margin-bottom:.3rem;">Cédula: <strong style="color:rgba(255,255,255,.7)">${r.cedula}</strong></div>
          <div class="profile-meta">📧 ${r.correo}${r.telefono?' · 📞 '+r.telefono:''}</div>
          <div class="profile-chips" style="margin-top:.8rem;">
            <span class="profile-chip lime">${tipo==='alumno'?'Alumno':'Docente'}</span>
            ${tipo==='alumno'&&r.ciudad_nombre?`<span class="profile-chip">${r.ciudad_nombre}</span>`:''}
            ${tipo==='docente'&&r.especialidad?`<span class="profile-chip">${r.especialidad}</span>`:''}
            <span class="profile-chip ${r.activo?'lime':''}">${r.activo?'Activo':'Inactivo'}</span>
          </div>
        </div>
      </div>
    </div>`;

  if(tipo==='alumno'){
    const notas=r.notas||[];
    const prom=notas.length?(notas.reduce((a,n)=>a+parseFloat(n.calificacion),0)/notas.length).toFixed(1):'—';
    const aprobadas=new Set(notas.filter(n=>parseFloat(n.calificacion)>=6).map(n=>n.materia));
    const reprobadas=new Set(notas.filter(n=>parseFloat(n.calificacion)<6).map(n=>n.materia));

    html+=`
    <div class="stats" style="margin-bottom:1.5rem;">
      <div class="scard c1"><div class="scard-ico"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg></div><div><div class="scard-val">${r.materias.length}</div><div class="scard-key">Materias</div></div></div>
      <div class="scard c3"><div class="scard-ico"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 11 12 14 22 4"/></svg></div><div><div class="scard-val">${totalAsist}</div><div class="scard-key">Asistencias</div></div></div>
      <div class="scard c2"><div class="scard-ico"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16"/><polyline points="14 2 14 8 20 8"/></svg></div><div><div class="scard-val">${prom}</div><div class="scard-key">Promedio</div></div></div>
      <div class="scard c4"><div class="scard-ico"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div><div><div class="scard-val">${aprobadas.size}</div><div class="scard-key">Aprobadas</div></div></div>
    </div>`;

    // Resumen asistencias
    html+=`<div class="card" style="margin-bottom:1.2rem;">
      <div class="card-head"><h3>Asistencias</h3></div>
      <div class="card-body" style="display:flex;gap:.6rem;flex-wrap:wrap;">
        ${Object.entries({presente:'b-presente',ausente:'b-ausente',tardanza:'b-tardanza',justificado:'b-justificado'})
          .map(([k,cls])=>`<span class="badge ${cls}" style="font-size:.78rem;padding:4px 12px;">${k.charAt(0).toUpperCase()+k.slice(1)}: <strong>${asist[k]||0}</strong></span>`).join('')}
      </div>
    </div>`;

    // Materias inscritas
    html+=`<div class="card" style="margin-bottom:1.2rem;">
      <div class="card-head"><h3>Materias Inscritas</h3></div>
      <div class="card-body">
        ${r.materias.length?r.materias.map(m=>`<span class="badge b-alumno" style="margin:.2rem;">${m.codigo} · ${m.nombre}</span>`).join(''):'<em style="color:var(--muted);font-size:.84rem;">Sin materias inscritas.</em>'}
      </div>
    </div>`;

    // Notas
    if(notas.length){
      html+=`<div class="card">
        <div class="card-head"><h3>Calificaciones</h3>
          <div style="display:flex;gap:.4rem;">
            <span class="badge b-presente">Aprobadas: ${aprobadas.size}</span>
            <span class="badge b-ausente">Reprobadas: ${reprobadas.size}</span>
          </div>
        </div>
        <div class="tbl-wrap"><table>
          <thead><tr><th>Materia</th><th>Tipo</th><th>Descripción</th><th>Nota</th><th>Estado</th><th>Fecha</th></tr></thead>
          <tbody>${notas.map(n=>{
            const nv=parseFloat(n.calificacion);
            const ok=nv>=6;
            const ncls=nv>=8?'color:#16a34a':nv>=6?'color:#ca8a04':'color:#dc2626';
            return `<tr>
              <td>${n.materia}</td>
              <td><span class="badge b-alumno" style="font-size:.68rem;">${n.tipo}</span></td>
              <td style="font-size:.82rem;color:var(--muted);">${n.descripcion||'—'}</td>
              <td><strong style="font-family:'DM Serif Display',serif;font-size:1.1rem;${ncls}">${nv.toFixed(1)}</strong></td>
              <td><span class="badge ${ok?'b-presente':'b-ausente'}">${ok?'Aprobado':'Reprobado'}</span></td>
              <td style="font-size:.8rem;">${n.fecha}</td>
            </tr>`;
          }).join('')}</tbody>
        </table></div>
      </div>`;
    }
  } else {
    // DOCENTE
    html+=`
    <div class="stats" style="margin-bottom:1.5rem;">
      <div class="scard c1"><div class="scard-ico"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/></svg></div><div><div class="scard-val">${r.materias.length}</div><div class="scard-key">Materias</div></div></div>
      <div class="scard c3"><div class="scard-ico"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 11 12 14 22 4"/></svg></div><div><div class="scard-val">${totalAsist}</div><div class="scard-key">Asistencias reg.</div></div></div>
    </div>
    <div class="card" style="margin-bottom:1.2rem;">
      <div class="card-head"><h3>Materias que dicta</h3></div>
      <div class="tbl-wrap"><table>
        <thead><tr><th>Código</th><th>Materia</th><th>Días</th><th>Horario</th></tr></thead>
        <tbody>${r.materias.length?r.materias.map(m=>`
          <tr>
            <td><strong>${m.codigo}</strong></td>
            <td>${m.nombre}</td>
            <td>${m.dias||'—'}</td>
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

  area.innerHTML=html;
}
</script>
<?php include __DIR__.'/layout/foot.php'; ?>
