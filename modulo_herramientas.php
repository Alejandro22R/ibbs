<?php
$page_title  = 'Herramientas';
$page_sub    = 'Notificaciones · Certificados · Historial';
$active_link = 'herramientas';
include __DIR__.'/layout/head.php';
// Acceso admin o superadmin
if(!in_array($_rol,['superadmin','admin'])){
    echo '<script>window.location="index.php";</script>'; exit;
}

$con = db();
// Pre-fetch alumnos para los selects de certificados e importaciones
$lista_alumnos=[];
$ra=mysqli_query($con,"SELECT id,nombre,apellido,cedula,ciudad FROM alumnos WHERE activo=1 ORDER BY apellido,nombre");
while($f=mysqli_fetch_assoc($ra)) $lista_alumnos[]=$f;
mysqli_close($con);
?>

<!-- Sub-tabs nav -->
<div style="display:flex;gap:.5rem;margin-bottom:1.4rem;background:var(--paper);border:1.5px solid var(--border);border-radius:12px;padding:.35rem;">
  <?php
  $tabs=[
    ['notif','', 'Notificaciones'],
    ['cert', '', 'Certificados'],
    ['audit','', 'Historial de acciones'],
  ];
  foreach($tabs as [$id,$ico,$lbl]):
  ?>
  <button class="tool-tab <?=$id==='notif'?'active':''?>" data-tab="<?=$id?>" onclick="switchTool('<?=$id?>')">
    <?=$lbl?>
  </button>
  <?php endforeach; ?>
</div>

<style>
.tool-tab{flex:1;padding:.6rem .8rem;border:none;background:transparent;border-radius:8px;font-size:.83rem;font-weight:600;color:var(--muted);cursor:pointer;transition:all .2s;font-family:'Inter',sans-serif;}
.tool-tab.active{background:var(--ink);color:var(--lime);}
.tool-pane{display:none;}.tool-pane.active{display:block;}
.cal-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:3px;}
.cal-head{text-align:center;font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:var(--muted);padding:.4rem 0;}
.cal-day{min-height:80px;border-radius:8px;padding:.4rem .5rem;background:var(--paper);border:1.5px solid var(--border);font-size:.75rem;transition:background .15s;}
.cal-day.today{border-color:var(--lime2);background:#f0fff0;}
.cal-day.other-month{opacity:.3;}
.cal-day .dn{font-weight:700;font-size:.8rem;margin-bottom:.2rem;}
.cal-event{font-size:.62rem;background:var(--ink);color:var(--lime);border-radius:4px;padding:.15rem .35rem;margin-bottom:.15rem;line-height:1.3;cursor:pointer;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.cal-event.e-blue{background:#1d4ed8;color:#fff;}
.cal-event.e-amber{background:#b45309;color:#fff;}
.notif-item{display:flex;gap:.9rem;padding:.9rem 1rem;border-bottom:1px solid var(--border);align-items:flex-start;}
.notif-item:last-child{border-bottom:none;}
.notif-item.unread{background:#fffdf5;}
.notif-ico{width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:1rem;}
.notif-ico.reprobado{background:#fee2e2;}
.notif-ico.asistencia{background:#fef9c3;}
.notif-ico.info{background:#dbeafe;}
.notif-body{flex:1;}
.notif-msg{font-size:.83rem;color:var(--ink);line-height:1.5;}
.notif-time{font-size:.7rem;color:var(--muted);margin-top:.2rem;}
</style>

<!-- ── NOTIFICACIONES ── -->
<div id="pane-notif" class="tool-pane active">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;flex-wrap:wrap;gap:.6rem;">
    <div>
      <h3 style="font-family:'Playfair Display',serif;font-size:1.1rem;">Alertas del sistema</h3>
      <p style="font-size:.78rem;color:var(--muted);">Reprobados y ausencias críticas (+25%)</p>
    </div>
    <div style="display:flex;gap:.6rem;">
      <?php if(can('edit')): ?>
      <button class="btn btn-primary" onclick="generarNotifs()">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
        Actualizar alertas
      </button>
      <?php endif; ?>
      <button class="btn btn-secondary" onclick="marcarTodasLeidas()">Marcar leídas</button>
    </div>
  </div>
  <div class="card">
    <div id="notifList" style="min-height:120px;">
    </div>
  </div>
</div>

<!-- ── CERTIFICADOS PDF ── -->
<div id="pane-cert" class="tool-pane">
  <div style="display:grid;grid-template-columns:340px 1fr;gap:1.4rem;align-items:start;">
    <div class="card">
      <div class="card-head"><h3>Generar certificado</h3></div>
      <div class="card-body">
        <div class="field" style="margin-bottom:.6rem;">
          <label>Buscar alumno</label>
          <div style="display:flex;gap:.5rem;margin-bottom:.5rem;">
            <input type="text" id="fCertNombre" placeholder="Nombre o apellido…" oninput="filtrarCertAlumno()"
              style="flex:1;padding:.6rem .8rem;border:1.5px solid var(--border);border-radius:8px;font-size:.83rem;outline:none;background:var(--paper);">
            <input type="text" id="fCertCedula" placeholder="Cédula…" oninput="filtrarCertAlumno()"
              style="width:130px;padding:.6rem .8rem;border:1.5px solid var(--border);border-radius:8px;font-size:.83rem;outline:none;background:var(--paper);">
          </div>
          <select id="certAlumno" onchange="previewCert(this.value)" size="5"
            style="width:100%;border:1.5px solid var(--border);border-radius:8px;font-size:.83rem;padding:.3rem;background:var(--paper);outline:none;">
            <option value="">— Elige un alumno —</option>
            <?php foreach($lista_alumnos as $a): ?>
            <option value="<?=$a['id']?>"
              data-nombre="<?=htmlspecialchars(strtolower($a['nombre'].' '.$a['apellido']))?>"
              data-cedula="<?=htmlspecialchars($a['cedula'])?>"
              data-ciudad="<?=htmlspecialchars($a['ciudad']??'')?>">
              <?=htmlspecialchars($a['apellido'].', '.$a['nombre'])?> — CI: <?=htmlspecialchars($a['cedula'])?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field" style="margin-bottom:1rem;">
          <label>Tipo de documento</label>
          <select id="certTipo">
            <option value="notas">Constancia de Notas</option>
            <option value="estudio">Constancia de Estudios</option>
          </select>
        </div>
        <div class="field" style="margin-bottom:1.2rem;">
          <label>Firma / Cargo</label>
          <input id="certFirma" placeholder="Ej: Director Académico" value="Director Académico">
        </div>
        <button class="btn btn-primary" style="width:100%;" onclick="generarCert()" id="btnCert" disabled>
          <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
          Descargar PDF / Imprimir
        </button>
      </div>
    </div>
    <!-- Preview -->
    <div class="card">
      <div class="card-head"><h3>Vista previa (Interactiva)</h3></div>
      <div class="card-body" id="certPreview" style="min-height:300px;display:flex;align-items:center;justify-content:center;color:var(--muted);font-size:.85rem;background:#fcfcfc;">
        Selecciona un alumno para previsualizar
      </div>
    </div>
  </div>
</div>

<!-- ── HISTORIAL DE ACCIONES ── -->
<div id="pane-audit" class="tool-pane">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;flex-wrap:wrap;gap:.6rem;">
    <div>
      <h3 style="font-family:'Playfair Display',serif;font-size:1.1rem;">Historial de acciones</h3>
      <p style="font-size:.78rem;color:var(--muted);">Registro de actividad del sistema (últimas 100 acciones)</p>
    </div>
    <button class="btn btn-secondary" onclick="loadAudit()">
      <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
      Actualizar
    </button>
  </div>
  <div class="card">
    <div class="tbl-wrap">
      <table>
        <thead><tr>
          <th style="text-align:left;">Usuario</th>
          <th style="text-align:left;">Acción</th>
          <th style="text-align:left;">Detalle</th>
          <th style="text-align:center;">IP</th>
          <th style="text-align:center;">Fecha</th>
        </tr></thead>
        <tbody id="tbAudit">
          <tr class="empty-row"><td colspan="5"><span class="spin"></span></td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
// ── TAB SWITCHING ────────────────────────────────────────────
function switchTool(id) {
  document.querySelectorAll('.tool-tab').forEach(t=>t.classList.toggle('active',t.dataset.tab===id));
  document.querySelectorAll('.tool-pane').forEach(p=>p.classList.toggle('active',p.id==='pane-'+id));
  if(id==='notif') loadNotifs();
  if(id==='audit') loadAudit();
}

// Conversor numérico a texto formal (Ej: "17 DIECISIETE")
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

// Filtrar Alumnos del buscador para certificados
function filtrarCertAlumno() {
  const qNombre = (document.getElementById('fCertNombre')?.value || '').toLowerCase();
  const qCedula = (document.getElementById('fCertCedula')?.value || '').toLowerCase();
  const select  = document.getElementById('certAlumno');
  const options = select.options;

  for (let i = 1; i < options.length; i++) {
    const opt = options[i];
    const txt = opt.getAttribute('data-nombre').toLowerCase();
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

// ── NOTIFICACIONES ───────────────────────────────────────────
async function loadNotifs() {
  const el = document.getElementById('notifList');
  if(!el) return;
  el.innerHTML = '<div style="text-align:center;padding:2.5rem;color:var(--muted);"><span class="spin"></span></div>';
  let d = null;
  try {
    const fd = new FormData(); fd.append('action','notif_list');
    const r = await fetch('php/ajax.php', {method:'POST', body:fd});
    const txt = await r.text();
    d = JSON.parse(txt);
  } catch(e) {
    el.innerHTML = '<div style="padding:1.5rem;color:#dc2626;font-size:.85rem;">Error: ' + e.message + '</div>';
    return;
  }
  if (!d?.ok || !d.data.length) {
    el.innerHTML = `<div style="text-align:center;padding:2.5rem;color:var(--muted);">
      <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round" style="opacity:.3;margin-bottom:.8rem;"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
      <div style="font-family:'Playfair Display',serif;">Sin alertas pendientes</div>
      <div style="font-size:.78rem;margin-top:.3rem;">Presiona "Actualizar alertas" para escanear reprobados y ausencias.</div>
    </div>`;
    return;
  }
  const ico = {
    reprobado: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>',
    asistencia: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
    sistema:    '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#6366f1" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14M4.93 4.93a10 10 0 0 0 0 14.14"/></svg>',
    info:       '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>',
  };
  el.innerHTML = d.data.map(n=>`
    <div class="notif-item ${n.leida=='0'?'unread':''}" id="ni${n.id}">
      <div class="notif-ico ${n.tipo}">${ico[n.tipo]||'🔔'}</div>
      <div class="notif-body">
        <div class="notif-msg">${n.mensaje}</div>
        <div class="notif-time">${n.creado_en?.substring(0,16)||''}</div>
      </div>
      <div style="display:flex;gap:.4rem;align-items:flex-start;flex-shrink:0;">
        ${n.leida=='0'?`<button class="btn btn-sm btn-secondary" onclick="leerNotif(${n.id})">Leída</button>`:''}
        <button class="btn btn-sm btn-danger" onclick="borrarNotif(${n.id})">✕</button>
      </div>
    </div>`).join('');
}
async function generarNotifs() {
  const d = await ajax('notif_generar');
  toast(d?.msg||'Hecho');
  loadNotifs();
}
async function leerNotif(id) {
  await ajax('notif_leer',{id});
  document.getElementById('ni'+id)?.classList.remove('unread');
  document.querySelector(`#ni${id} .btn-secondary`)?.remove();
}
async function marcarTodasLeidas() {
  await ajax('notif_leer',{id:0});
  loadNotifs();
}
async function borrarNotif(id) {
  await ajax('notif_borrar',{id});
  document.getElementById('ni'+id)?.remove();
}

// ── CERTIFICADOS PDF ─────────────────────────────────────────
let certData=null;
async function previewCert(id){
  document.getElementById('btnCert').disabled=!id;
  if(!id){document.getElementById('certPreview').innerHTML='Selecciona un alumno';certData=null;return;}
  document.getElementById('certPreview').innerHTML='<span class="spin"></span>';
  const d=await ajax('cert_datos',{alumno_id:id});
  if(!d?.ok){document.getElementById('certPreview').innerHTML=d?.msg||'Error';return;}
  certData=d.data;
  const {alumno,materias,fecha}=d.data;
  
  // Encontrar el option para extraer metadata adicional
  const selObj = document.getElementById('certAlumno');
  const actOpt = selObj.options[selObj.selectedIndex];
  const ciudad = actOpt ? actOpt.getAttribute('data-ciudad') : 'Ciudad Bolívar';
  certData.alumno.ciudad = ciudad || 'Ciudad Bolívar';

  const conNota=materias.filter(m=>m.nota_final!==null);
  const aprobadas=conNota.filter(m=>parseFloat(m.nota_final)>=15).length;
  const promedio=conNota.length?(conNota.reduce((s,m)=>s+parseFloat(m.nota_final),0)/conNota.length).toFixed(1):null;
  document.getElementById('certPreview').innerHTML=`
    <div style="border:1px solid #ddd;border-radius:12px;padding:1.5rem;font-family:serif;max-width:520px;margin:0 auto;background:#fff;box-shadow:0 4px 12px rgba(0,0,0,0.03);">
      <div style="text-align:center;margin-bottom:1rem;">
        <img src="assets/logo.jpg" style="width:55px;height:55px;border-radius:50%;object-fit:cover;margin-bottom:.5rem;" onerror="this.src='https://placehold.co/100x100/1a4d2e/ffffff?text=IBBS'">
        <div style="font-size:.65rem;text-transform:uppercase;letter-spacing:1px;color:#333;font-weight:bold;">Instituto Bíblico Bautista del Sur</div>
        <hr style="margin:.7rem 0;border-color:#eee;">
        <div style="font-size:1rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:#000;">${document.getElementById('certTipo').options[document.getElementById('certTipo').selectedIndex].text}</div>
      </div>
      <p style="font-size:.8rem;line-height:1.7;text-align:justify;color:#111;text-indent:15pt;">
        El <strong>Instituto Bíblico Bautista del Sur</strong> hace constar que el ciudadano(a) 
        <strong>${alumno.nombre.toUpperCase()} ${alumno.apellido.toUpperCase()}</strong>, titular de la C.I. N° <strong>V-${parseInt(alumno.cedula).toLocaleString('es-VE')}</strong>,
        ${document.getElementById('certTipo').value==='estudio'
          ? `es estudiante activo(a) de esta institución en el período de formación teológica actual. Se expide a solicitud de la parte interesada.`
          : `presenta el siguiente récord oficial de calificaciones acreditadas en su expediente:`}
      </p>
      ${document.getElementById('certTipo').value==='notas'&&materias.length?`
      <table style="width:100%;border-collapse:collapse;font-size:.75rem;margin:1rem 0;border:1px solid #000;">
        <thead><tr style="background:#f5f5f5;">
          <th style="text-align:left;padding:.4rem .6rem;border:1px solid #000;font-size:.7rem;">Materia</th>
          <th style="text-align:center;padding:.4rem .6rem;border:1px solid #000;width:35%;font-size:.7rem;">Calificación</th>
        </tr></thead>
        <tbody>${materias.map(m=>{
          const nv=m.nota_final!==null?parseFloat(m.nota_final):null;
          return `<tr><td style="padding:.35rem .6rem;border:1px solid #000;font-weight:bold;">${m.mn.toUpperCase()}</td>
          <td style="text-align:center;padding:.35rem .6rem;border:1px solid #000;font-weight:bold;">${notaALetras(nv)}</td></tr>`;
        }).join('')}</tbody>
      </table>
      <p style="font-size:.78rem;text-align:right;margin-top:5px;"><strong>Promedio general: ${promedio || '—'}</strong></p>
      `:''}
      <div style="margin-top:1.2rem;font-size:.7rem;color:#444;text-align:right;font-style:italic;">Emitido en Ciudad Bolívar, el ${fecha}</div>
      <div style="margin-top:1.5rem;border-top:1px solid #222;padding-top:.5rem;font-size:.7rem;text-align:center;color:#111;font-weight:bold;" id="prevFirma">
        ${document.getElementById('certFirma').value||'Director Académico'}<br>Firma Autorizada y Sello Húmedo
      </div>
    </div>`;
}

document.getElementById('certFirma').addEventListener('input',()=>{
  const el=document.getElementById('prevFirma');
  if(el) el.innerHTML=`___________________________<br>${document.getElementById('certFirma').value||'Director Académico'}<br>Firma Autorizada y Sello Húmedo`;
});
document.getElementById('certTipo').addEventListener('change',()=>{
  const id=document.getElementById('certAlumno').value;
  if(id) previewCert(id);
});

async function generarCert() {
  if(!certData){toast('Selecciona un alumno.','err');return;}
  const {alumno,materias,fecha}=certData;
  const tipo=document.getElementById('certTipo').value;
  const firma=document.getElementById('certFirma').value||'Director Académico';
  const conNota=materias.filter(m=>m.nota_final!==null);
  
  const promedio=conNota.length?(conNota.reduce((s,m)=>s+parseFloat(m.nota_final),0)/conNota.length).toFixed(2):"0.00";
  const aprobadas=conNota.filter(m=>parseFloat(m.nota_final)>=15).length;
  const registradas=materias.length;

  const hoy = new Date();
  const mesesArr = ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"];
  const diaLetra = hoy.getDate();
  const mesLetra = mesesArr[hoy.getMonth()];
  const anioLetra = hoy.getFullYear();

  // Código de validación único para auditoría e integridad
  const codVerif = `IBBS-CERT-${alumno.id}-${hoy.getFullYear()}-${Math.floor(10000 + Math.random() * 90000)}`;

  // Maquetación de filas académicas reglamentarias con nota a letras
  const filasNotas=tipo==='notas'?materias.map(m=>{
    const nv=m.nota_final!==null?parseFloat(m.nota_final):null;
    const notaFmt = notaALetras(nv);
    return `<tr>
      <td style="text-align:center; font-weight:bold; border: 1px solid #000000; padding: 5pt 7pt;">${m.codigo || 'UC'}</td>
      <td style="font-weight:bold; border: 1px solid #000000; padding: 5pt 7pt;">${m.mn.toUpperCase()}</td>
      <td style="text-align:center; font-weight:bold; border: 1px solid #000000; padding: 5pt 7pt;">${notaFmt}</td>
    </tr>`;
  }).join(''):[];

  const titulo=tipo==='notas'?'Constancia de Calificaciones y Rendimiento':'Constancia de Estudios';
  
  // Párrafo introductorio oficial con jerga jurídica
  const cuerpoTexto=tipo==='estudio'
    ? `Quien suscribe, Director De Registro Y Control De Actividades Académicas del <strong>Instituto Bíblico Bautista del Sur</strong>, hace constar por medio de la presente que en los archivos de esta Casa de Estudios Teológicos reposa el Expediente del Ciudadano: <strong style="text-transform: uppercase;">${alumno.apellido}, ${alumno.nombre}</strong>, titular de la cédula de identidad N°: <strong>V-${parseInt(alumno.cedula).toLocaleString('es-VE')}</strong>, quien se encuentra cursando de forma activa y regular sus programas de formación bíblica y ministerial correspondientes.`
    : `Quien suscribe, Director De Registro Y Control De Actividades Académicas del <strong>Instituto Bíblico Bautista del Sur</strong>, hace constar por medio de la presente que en los archivos de esta Casa de Estudios Teológicos reposa el Expediente de Estudios del Ciudadano: <strong style="text-transform: uppercase;">${alumno.apellido}, ${alumno.nombre}</strong>, titular de la cédula de identidad N°: <strong>V-${parseInt(alumno.cedula).toLocaleString('es-VE')}</strong>, habiendo cursado las unidades curriculares que a continuación se especifican:`;

  const html=`<!DOCTYPE html>
  <html>
  <head>
    <meta charset="UTF-8">
    <title>${titulo.toUpperCase()}</title>
    <style>
      *{margin:0;padding:0;box-sizing:border-box;}
      body{
        font-family:'Times New Roman', Times, Georgia, serif !important;
        font-size:11pt !important;
        color:#000000 !important;
        background:#ffffff !important;
        padding:15mm 15mm 15mm 15mm;
        line-height:1.5;
      }
      .membrete-table{
        width:100%;
        border-collapse:collapse;
        border-bottom:1.5px solid #000000;
        padding-bottom:8pt;
        margin-bottom:12pt;
      }
      .membrete-text{
        text-align:left;
        line-height:1.3;
      }
      .membrete-title-gov{
        margin:0;
        font-size:9.5pt;
        font-weight:bold;
        text-transform:uppercase;
        letter-spacing:0.5px;
      }
      .membrete-title-inst{
        margin:2pt 0;
        font-size:11pt;
        font-weight:bold;
        text-transform:uppercase;
      }
      .document-title-container{
        text-align:center;
        margin-top:15pt;
        margin-bottom:15pt;
      }
      .document-title{
        margin:0;
        font-size:13pt;
        font-weight:bold;
        text-transform:uppercase;
        letter-spacing:1px;
      }
      .document-subtitle{
        margin:3pt 0 0;
        font-size:8pt;
        font-family:monospace;
        color:#000000;
      }
      .legal-paragraph{
        text-align:justify;
        font-size:10.5pt;
        text-indent:25pt;
        margin-bottom:12pt;
      }
      .print-table{
        width:100%;
        border-collapse:collapse;
        margin-top:10pt;
        margin-bottom:12pt;
      }
      .print-table th{
        background-color:#f5f5f5 !important;
        color:#000000 !important;
        font-weight:bold !important;
        border:1px solid #000000 !important;
        padding:5pt 7pt !important;
        text-transform:uppercase !important;
        font-size:9pt !important;
        text-align:center !important;
      }
      .observations-box{
        border:1px solid #000000 !important;
        padding:8pt !important;
        margin-top:10pt;
        font-size:9pt !important;
        line-height:1.4;
      }
      .closing-paragraph{
        font-size:10pt;
        text-align:justify;
        margin-top:15pt;
        font-style:italic;
      }
      .signatures-container{
        margin-top:60pt;
        display:flex;
        justify-content:space-between;
        page-break-inside:avoid;
      }
      .signature-block{
        width:45%;
        text-align:center;
      }
      .signature-line{
        border-top:1px solid #000000;
        width:100%;
        margin-bottom:4pt;
      }
      .signature-title{
        margin:0;
        font-size:9pt;
        font-weight:bold;
        text-transform:uppercase;
      }
      .signature-sub{
        margin:2pt 0 0;
        font-size:8pt;
        color:#444;
      }
      .footer-legal{
        margin-top:60pt;
        border-top:1.5px solid #000000;
        padding-top:4pt;
        text-align:center;
        font-size:7.5pt;
        color:#000;
        page-break-inside:avoid;
      }
      @media print{
        body{
          padding:0;
        }
        @page{
          size:letter portrait;
          margin:15mm;
        }
      }
    </style>
  </head>
  <body>
    <!-- Membrete institucional oficial -->
    <table class="membrete-table">
      <tr>
        <td style="vertical-align:top;" class="membrete-text">
          <h4 class="membrete-title-gov">Gobierno Eclesiástico Autónomo</h4>
          <h4 class="membrete-title-gov" style="margin:2pt 0;">Asociación de Iglesias Bautistas de Venezuela</h4>
          <h3 class="membrete-title-inst">Instituto Bíblico Bautista del Sur</h3>
          <p style="margin:2pt 0 0; font-size:8.5pt; color:#222;">Dirección de Registro y Control de Actividades Académicas</p>
          <p style="margin:1pt 0 0; font-size:8.5pt; color:#222; font-style:italic;">Ciudad Bolívar, Estado Bolívar</p>
        </td>
        <td style="text-align:right; vertical-align:top; width:80pt;">
          <img src="assets/logo.jpg" alt="IBBS" style="width:65pt; height:65pt; border-radius:50%; border:1px solid #000000;" onerror="this.src='https://placehold.co/100x100/1a4d2e/ffffff?text=IBBS'">
        </td>
      </tr>
    </table>

    <!-- Título de Documento -->
    <div class="document-title-container">
      <h2 class="document-title">${titulo}</h2>
      <p class="document-subtitle">CÓDIGO DE VERIFICACIÓN INSTITUCIONAL: ${codVerif}</p>
    </div>

    <!-- Párrafo Legal -->
    <p class="legal-paragraph">${cuerpoTexto}</p>

    <!-- Tabla de Calificaciones (Si aplica) -->
    ${tipo==='notas' && materias.length ? `
    <table class="print-table">
      <thead>
        <tr>
          <th style="width:15%;">CÓDIGO</th>
          <th style="width:50%; text-align:left;">MATERIA / UNIDAD CURRICULAR</th>
          <th style="width:35%;">CALIFICACIÓN</th>
        </tr>
      </thead>
      <tbody>
        ${filasNotas}
      </tbody>
    </table>

    <!-- Bloque de observaciones oficiales -->
    <div class="observations-box">
      <strong style="text-transform:uppercase; font-size:8.5pt;">Observaciones Reglamentarias:</strong>
      <table style="width:100%; font-size:8.5pt; margin-top:4pt; border-collapse:collapse;">
        <tr>
          <td style="width:60%; border:none; padding:2pt 0; vertical-align:top;">
            1.- La escala de calificaciones aplicable es del uno (1) al veinte (20).<br>
            2.- La calificación mínima aprobatoria requerida es de Quince (15) puntos.<br>
            3.- Procedencia del estudiante: <span style="text-transform:uppercase; font-weight:bold;">${alumno.ciudad.toUpperCase()}</span>
          </td>
          <td style="width:40%; border:none; padding:2pt 0; vertical-align:top; border-left:1px solid #000000; padding-left:10pt;">
            <strong>Índice de Rendimiento Académico:</strong> <span style="font-weight:bold;">${promedio}</span><br>
            <strong>U.C. Registradas:</strong> <span style="font-weight:bold;">${registradas}</span><br>
            <strong>U.C. Aprobadas:</strong> <span style="font-weight:bold;">${aprobadas}</span>
          </td>
        </tr>
      </table>
    </div>
    ` : ''}

    <!-- Cierre formal -->
    <p class="closing-paragraph">
      Constancia de carácter oficial y fidedigna que se expide a solicitud de la parte interesada, en Ciudad Bolívar a los ${diaLetra} días del mes de ${mesLetra} de ${anioLetra}.
    </p>

    <!-- Firmas oficiales de validación -->
    <div class="signatures-container">
      <div class="signature-block">
        <div class="signature-line"></div>
        <p class="signature-title">Firma del Estudiante</p>
        <p class="signature-sub">Titular de la Cédula</p>
      </div>
      <div class="signature-block">
        <div class="signature-line"></div>
        <p class="signature-title">${firma}</p>
        <p class="signature-sub">Dirección de Registro Académico y Sello Húmedo</p>
      </div>
    </div>

    <!-- Pie de página regulatoria -->
    <div class="footer-legal">
      Instituto Bíblico Bautista del Sur · Calle Igualdad, Casco Histórico, Ciudad Bolívar, Estado Bolívar, Venezuela · Dirección de Registro y Control de Estudios.
    </div>
  </body>
  </html>`;

  // Abrir ventana limpia de impresión
  const w=window.open('','_blank','width=800,height=900');
  w.document.write(html);
  w.document.close();
  setTimeout(()=>w.print(),600);
}

// Load notifications - fallback triggers
document.addEventListener('ibbs:ready', ()=>loadNotifs());
window.addEventListener('load', ()=>{ 
  setTimeout(()=>{
    const el = document.getElementById('notifList');
    if(el && el.querySelector('.spin')) loadNotifs();
  }, 500);
});

async function loadAudit() {
  const tb = document.getElementById('tbAudit');
  if (!tb) return;
  tb.innerHTML = '<tr class="empty-row"><td colspan="5"><span class="spin"></span></td></tr>';
  let d;
  try { d = await ajax('audit_list'); } catch(e){ d = null; }
  if (!d?.ok) {
    tb.innerHTML = '<tr class="empty-row"><td colspan="5" style="color:var(--muted);">Sin registros de actividad aún. Las acciones de eliminación y reset quedan registradas aquí.</td></tr>';
    return;
  }
  if (!d.data?.length) {
    tb.innerHTML = '<tr class="empty-row"><td colspan="5" style="color:var(--muted);">Sin registros todavía.</td></tr>';
    return;
  }
  const colors = {RESET:'#dc2626', LOGIN:'#16a34a', DELETE:'#f59e0b', CREATE:'#3b82f6', UPDATE:'#6366f1', RESET_BD:'#dc2626'};
  tb.innerHTML = d.data.map(r => {
    const colorKey = Object.keys(colors).find(k => (r.accion||'').includes(k));
    const clr = colorKey ? colors[colorKey] : '#6b7280';
    return `<tr>
      <td style="text-align:left;font-size:.83rem;"><strong>${r.usuario||'—'}</strong></td>
      <td style="text-align:left;">
        <span style="font-size:.71rem;background:${clr}18;color:${clr};border-radius:20px;padding:.2rem .75rem;font-weight:700;white-space:nowrap;">${r.accion||'—'}</span>
      </td>
      <td style="text-align:left;font-size:.79rem;color:var(--muted);max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="${r.detalle||''}">${r.detalle||'—'}</td>
      <td style="text-align:center;font-size:.73rem;color:var(--muted);font-family:monospace;">${r.ip||'—'}</td>
      <td style="text-align:center;font-size:.73rem;color:var(--muted);">${(r.creado_en||'').substring(0,16)}</td>
    </tr>`;
  }).join('');
}
</script>
<?php include __DIR__.'/layout/foot.php'; ?>