<?php
$page_title  = 'Visor de PDF';
$page_sub    = 'Visualiza documentos PDF desde tu computadora — 100% offline';
$active_link = 'visor_pdf';
include __DIR__.'/layout/head.php';
?>

<div style="display:flex;gap:1rem;height:calc(100vh - 120px);min-height:500px;">

  <!-- Panel izquierdo: lista de PDFs subidos -->
  <div style="width:260px;flex-shrink:0;display:flex;flex-direction:column;gap:.8rem;">
    <div class="card" style="flex-shrink:0;">
      <div class="card-body" style="padding:.9rem;">
        <label for="inputPdf" style="display:flex;align-items:center;justify-content:center;gap:.5rem;width:100%;padding:.75rem;background:var(--ink);color:var(--lime);border-radius:10px;cursor:pointer;font-size:.84rem;font-weight:700;transition:background .2s;">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
          Abrir PDF
        </label>
        <input type="file" id="inputPdf" accept=".pdf" multiple style="display:none;" onchange="cargarPdfs(this)">
        <p style="font-size:.68rem;color:var(--muted);text-align:center;margin-top:.5rem;">Funciona 100% offline<br>Los archivos no se suben al servidor</p>
      </div>
    </div>

    <!-- Lista de PDFs abiertos -->
    <div class="card" style="flex:1;overflow:hidden;display:flex;flex-direction:column;">
      <div class="card-head" style="padding:.7rem 1rem;">
        <h3 style="font-size:.82rem;">Documentos abiertos</h3>
      </div>
      <div id="listaPdfs" style="flex:1;overflow-y:auto;padding:.4rem .5rem;">
        <div style="text-align:center;padding:2rem 1rem;color:var(--muted);font-size:.8rem;font-style:italic;">
          Abre un PDF para comenzar
        </div>
      </div>
      <div class="card-body" style="padding:.6rem .8rem;border-top:1px solid var(--border);">
        <button onclick="limpiarTodo()" style="width:100%;padding:.5rem;background:transparent;border:1px solid var(--border);border-radius:7px;color:var(--muted);font-size:.76rem;cursor:pointer;font-family:'Nunito',sans-serif;">
          Cerrar todos
        </button>
      </div>
    </div>
  </div>

  <!-- Panel derecho: visor -->
  <div style="flex:1;display:flex;flex-direction:column;gap:.6rem;min-width:0;">

    <!-- Toolbar -->
    <div class="card" style="flex-shrink:0;">
      <div class="card-body" style="padding:.6rem 1rem;display:flex;align-items:center;gap:.8rem;flex-wrap:wrap;">
        <button onclick="pagAnterior()" id="btnPrev" title="Página anterior"
          style="padding:.45rem .8rem;background:var(--cream);border:1px solid var(--border);border-radius:7px;cursor:pointer;font-size:.85rem;">◀</button>
        <span style="font-size:.84rem;white-space:nowrap;">
          Página <strong id="paginaActual">—</strong> / <span id="totalPaginas">—</span>
        </span>
        <button onclick="pagSiguiente()" id="btnNext" title="Página siguiente"
          style="padding:.45rem .8rem;background:var(--cream);border:1px solid var(--border);border-radius:7px;cursor:pointer;font-size:.85rem;">▶</button>
        <div style="width:1px;height:20px;background:var(--border);"></div>
        <button onclick="cambiarZoom(-0.25)" title="Alejar"
          style="padding:.45rem .7rem;background:var(--cream);border:1px solid var(--border);border-radius:7px;cursor:pointer;font-size:.85rem;">−</button>
        <span id="zoomLabel" style="font-size:.82rem;min-width:44px;text-align:center;">100%</span>
        <button onclick="cambiarZoom(0.25)" title="Acercar"
          style="padding:.45rem .7rem;background:var(--cream);border:1px solid var(--border);border-radius:7px;cursor:pointer;font-size:.85rem;">+</button>
        <button onclick="fitWidth()" title="Ajustar al ancho"
          style="padding:.45rem .8rem;background:var(--cream);border:1px solid var(--border);border-radius:7px;cursor:pointer;font-size:.76rem;">⟺ Ancho</button>
        <div style="flex:1;"></div>
        <span id="nombrePdf" style="font-size:.78rem;color:var(--muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:220px;"></span>
      </div>
    </div>

    <!-- Canvas area -->
    <div id="visorWrap" style="flex:1;background:#555;border-radius:12px;overflow:auto;display:flex;align-items:flex-start;justify-content:center;padding:1rem;">
      <div id="emptyState" style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;min-height:300px;color:rgba(255,255,255,.5);text-align:center;gap:1rem;width:100%;">
        <svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
        <div style="font-size:1rem;font-weight:600;">Abre un archivo PDF</div>
        <div style="font-size:.82rem;max-width:280px;">Haz clic en "Abrir PDF" para seleccionar un archivo desde tu computadora.<br>Funciona completamente offline.</div>
      </div>
      <div id="canvasContainer" style="display:none;flex-direction:column;gap:.5rem;align-items:center;"></div>
    </div>
  </div>
</div>

<!-- PDF.js CDN (pero con fallback offline usando blob) -->
<script>
// Load PDF.js from CDN (offline: replace src with local pdfjs if needed)
const script = document.createElement('script');
script.src = 'assets/libs/pdfjs/pdf.min.js';
script.onload = initPdfJs;
script.onerror = () => {
  document.getElementById('emptyState').innerHTML = `
    <div style="color:#fca5a5;text-align:center;padding:2rem;">
      <div style="font-size:1rem;font-weight:600;margin-bottom:.5rem;">⚠ Sin conexión a internet</div>
      <div style="font-size:.82rem;">Para uso 100% offline, descarga pdf.js y colócalo en:<br><code>assets/pdfjs/pdf.min.js</code></div>
    </div>`;
};
document.head.appendChild(script);

let pdfjsLib = null;
let pdfDocs = {};      // filename → pdfDoc
let pdfActivo = null;  // filename activo
let paginaActual = 1;
let zoomActual = 1.0;
let renderTask = null;

function initPdfJs() {
  pdfjsLib = window['pdfjs-dist/build/pdf'];
  pdfjsLib.GlobalWorkerOptions.workerSrc = 'assets/libs/pdfjs/pdf.worker.min.js';
}

function cargarPdfs(input) {
  const files = Array.from(input.files);
  files.forEach(file => {
    if (!file.name.endsWith('.pdf')) return;
    const reader = new FileReader();
    reader.onload = async (e) => {
      try {
        const data = new Uint8Array(e.target.result);
        const doc = await pdfjsLib.getDocument({ data }).promise;
        pdfDocs[file.name] = { doc, name: file.name, pages: doc.numPages };
        actualizarLista();
        seleccionarPdf(file.name);
      } catch(err) {
        toast('Error al abrir ' + file.name + ': ' + err.message, 'err');
      }
    };
    reader.readAsArrayBuffer(file);
  });
  input.value = ''; // reset so same file can be re-opened
}

function actualizarLista() {
  const lista = document.getElementById('listaPdfs');
  const nombres = Object.keys(pdfDocs);
  if (!nombres.length) {
    lista.innerHTML = '<div style="text-align:center;padding:1.5rem 1rem;color:var(--muted);font-size:.8rem;font-style:italic;">Abre un PDF para comenzar</div>';
    return;
  }
  lista.innerHTML = nombres.map(n => {
    const activo = n === pdfActivo;
    return `<div onclick="seleccionarPdf('${n.replace(/'/g,"\\'")}'')" style="display:flex;align-items:center;gap:.5rem;padding:.55rem .7rem;border-radius:8px;cursor:pointer;margin-bottom:.2rem;background:${activo?'rgba(57,255,20,.08)':'transparent'};border:1px solid ${activo?'rgba(57,255,20,.2)':'transparent'};transition:all .15s;">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="${activo?'var(--lime)':'var(--muted)'}" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
      <span style="flex:1;font-size:.76rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:${activo?'var(--lime)':'var(--ink)'};font-weight:${activo?700:400};" title="${n}">${n}</span>
      <span onclick="event.stopPropagation();cerrarPdf('${n.replace(/'/g,"\\'")}'')" style="color:var(--muted);font-size:.9rem;line-height:1;cursor:pointer;padding:.1rem .2rem;border-radius:4px;" title="Cerrar">×</span>
    </div>`;
  }).join('');
}

function cerrarPdf(nombre) {
  delete pdfDocs[nombre];
  if (pdfActivo === nombre) {
    const restantes = Object.keys(pdfDocs);
    if (restantes.length) seleccionarPdf(restantes[0]);
    else { pdfActivo=null; mostrarEmpty(); }
  }
  actualizarLista();
}

function limpiarTodo() {
  pdfDocs = {}; pdfActivo = null;
  actualizarLista(); mostrarEmpty();
}

function mostrarEmpty() {
  document.getElementById('emptyState').style.display='flex';
  document.getElementById('canvasContainer').style.display='none';
  document.getElementById('paginaActual').textContent='—';
  document.getElementById('totalPaginas').textContent='—';
  document.getElementById('nombrePdf').textContent='';
}

async function seleccionarPdf(nombre) {
  pdfActivo = nombre;
  paginaActual = 1;
  actualizarLista();
  document.getElementById('emptyState').style.display='none';
  document.getElementById('canvasContainer').style.display='flex';
  document.getElementById('nombrePdf').textContent = nombre;
  const info = pdfDocs[nombre];
  document.getElementById('totalPaginas').textContent = info.pages;
  await renderPagina();
}

async function renderPagina() {
  if (!pdfActivo || !pdfDocs[pdfActivo]) return;
  if (renderTask) { try { renderTask.cancel(); } catch(e){} }

  document.getElementById('paginaActual').textContent = paginaActual;
  const container = document.getElementById('canvasContainer');
  container.innerHTML = '<div style="color:rgba(255,255,255,.4);font-size:.84rem;padding:2rem;">Cargando página…</div>';

  const { doc } = pdfDocs[pdfActivo];
  const page = await doc.getPage(paginaActual);
  const viewport = page.getViewport({ scale: zoomActual });

  const canvas = document.createElement('canvas');
  canvas.width  = viewport.width;
  canvas.height = viewport.height;
  canvas.style.cssText = 'border-radius:4px;box-shadow:0 4px 24px rgba(0,0,0,.4);max-width:100%;';

  container.innerHTML = '';
  container.appendChild(canvas);

  const ctx = canvas.getContext('2d');
  renderTask = page.render({ canvasContext: ctx, viewport });
  await renderTask.promise;
  document.getElementById('zoomLabel').textContent = Math.round(zoomActual*100)+'%';
}

function pagAnterior()  { if(paginaActual>1){ paginaActual--; renderPagina(); } }
function pagSiguiente() { if(!pdfActivo) return; const info=pdfDocs[pdfActivo]; if(paginaActual<info.pages){ paginaActual++; renderPagina(); } }

function cambiarZoom(delta) {
  zoomActual = Math.min(4, Math.max(0.25, zoomActual + delta));
  renderPagina();
}

function fitWidth() {
  if (!pdfActivo) return;
  const wrap = document.getElementById('visorWrap');
  // Will render at scale 1 first then adjust
  zoomActual = 1.0;
  renderPagina().then(async () => {
    const canvas = document.querySelector('#canvasContainer canvas');
    if (!canvas) return;
    const wrapW = wrap.clientWidth - 40;
    zoomActual = Math.min(3, wrapW / canvas.width * zoomActual);
    renderPagina();
  });
}

// Keyboard navigation
document.addEventListener('keydown', e => {
  if (e.key === 'ArrowRight' || e.key === 'ArrowDown') pagSiguiente();
  if (e.key === 'ArrowLeft'  || e.key === 'ArrowUp')   pagAnterior();
  if (e.key === '+' || e.key === '=') cambiarZoom(0.25);
  if (e.key === '-') cambiarZoom(-0.25);
});
</script>

<?php include __DIR__.'/layout/foot.php'; ?>
