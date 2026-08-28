<?php
$page_title  = 'Mi Perfil';
$page_sub    = 'Información personal y configuración de cuenta';
$active_link = 'perfil';
include __DIR__.'/layout/head.php';
$con = db();
$u = mysqli_fetch_assoc(mysqli_query($con,"SELECT * FROM usuarios WHERE id=$_uid"));
// Find linked record
$linked = null; $linked_tipo = '';
if($u['rol']==='profesor'||$u['rol']==='admin'||$u['rol']==='superadmin'){
    $d=mysqli_fetch_assoc(mysqli_query($con,"SELECT * FROM docentes WHERE usuario_id=$_uid OR cedula='".$u['cedula']."' LIMIT 1"));
    if($d){$linked=$d;$linked_tipo='docente';}
} else {
    $a=mysqli_fetch_assoc(mysqli_query($con,"SELECT * FROM alumnos WHERE usuario_id=$_uid OR cedula='".$u['cedula']."' LIMIT 1"));
    if($a){$linked=$a;$linked_tipo='alumno';}
}
mysqli_close($con);
?>

<div style="display:grid;grid-template-columns:300px 1fr;gap:1.4rem;align-items:start;">

  <!-- Tarjeta foto -->
  <div class="card" style="text-align:center;">
    <div class="card-body">
      <div id="fotoWrap" style="position:relative;display:inline-block;margin-bottom:1.2rem;">
        <?php $foto=$u['foto']?'../inicio/'.$u['foto']:null; ?>
        <div id="fotoCircle" style="width:110px;height:110px;border-radius:50%;overflow:hidden;background:var(--ink);display:flex;align-items:center;justify-content:center;margin:0 auto;border:3px solid var(--lime2);cursor:pointer;" onclick="document.getElementById('inputFoto').click()" title="Cambiar foto">
          <?php if($foto && file_exists(__DIR__.'/'.$u['foto'])): ?>
            <img id="fotoImg" src="<?=htmlspecialchars($u['foto'])?>" style="width:100%;height:100%;object-fit:cover;">
          <?php else: ?>
            <span id="fotoIni" style="font-family:'DM Serif Display',serif;font-size:2.5rem;color:var(--lime);"><?=strtoupper(mb_substr($u['usuario'],0,1))?></span>
            <img id="fotoImg" src="" style="width:100%;height:100%;object-fit:cover;display:none;">
          <?php endif; ?>
        </div>
        <div style="position:absolute;bottom:4px;right:4px;width:28px;height:28px;background:var(--lime2);border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer;pointer-events:none;">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="var(--ink)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
        </div>
      </div>
      <input type="file" id="inputFoto" accept="image/*" style="display:none;" onchange="subirFoto(this)">
      <div style="font-family:'DM Serif Display',serif;font-size:1.1rem;"><?=htmlspecialchars($u['usuario'])?></div>
      <div style="margin-top:.3rem;">
        <span class="badge <?= $u['rol']==='superadmin'?'b-superadmin':($u['rol']==='admin'?'b-admin':($u['rol']==='profesor'?'b-profesor':'b-alumno'))?>"><?=ucfirst($u['rol'])?></span>
      </div>
      <div style="font-size:.78rem;color:var(--muted);margin-top:.4rem;">CI: <?=htmlspecialchars($u['cedula'])?></div>
      <p style="font-size:.72rem;color:var(--muted);margin-top:.8rem;">Clic en la foto para cambiarla<br>JPG, PNG o WebP · máx. 3MB</p>
      <div style="margin-top:1rem;padding-top:.9rem;border-top:1px solid var(--border);">
        <a href="api/export_perfil.php" target="_blank"
           style="display:flex;align-items:center;justify-content:center;gap:.5rem;width:100%;padding:.65rem 0;background:var(--ink);color:var(--lime);border-radius:10px;font-size:.8rem;font-weight:700;text-decoration:none;letter-spacing:.3px;">
          🖨️ Exportar Perfil PDF
        </a>
      </div>
    </div>
  </div>

  <!-- Datos y seguridad -->
  <div style="display:flex;flex-direction:column;gap:1.2rem;">

    <!-- Datos personales -->
    <div class="card">
      <div class="card-head"><h3>Datos de cuenta</h3></div>
      <div class="card-body">
        <div class="form-grid">
          <div class="field"><label>Usuario</label><input id="pU" value="<?=htmlspecialchars($u['usuario'])?>"></div>
          <div class="field"><label>Correo</label><input id="pM" type="email" value="<?=htmlspecialchars($u['correo'])?>"></div>
          <div class="field"><label>Cédula</label><input id="pCed" value="<?=htmlspecialchars($u['cedula'])?>" readonly style="opacity:.6;cursor:not-allowed;"></div>
          <div class="field"><label>Rol actual</label><input value="<?=ucfirst($u['rol'])?>" readonly style="opacity:.6;cursor:not-allowed;"></div>
        </div>
        <div style="display:flex;justify-content:flex-end;margin-top:.5rem;">
          <button class="btn btn-primary" onclick="guardarDatos()">Guardar cambios</button>
        </div>
      </div>
    </div>

    <!-- Cambiar contraseña -->
    <div class="card">
      <div class="card-head"><h3>Cambiar contraseña</h3></div>
      <div class="card-body">
        <div class="form-grid">
          <div class="field"><label>Contraseña actual</label><input type="password" id="pPwdAct" placeholder="••••••••"></div>
          <div class="field"></div>
          <div class="field">
            <label>Nueva contraseña</label>
            <input type="password" id="pPwdN" placeholder="mín. 8 caracteres, mayús., minús. y nº" oninput="renderStrength(this.value,'pwdBar')">
            <div style="height:4px;border-radius:2px;background:#e5ede6;margin-top:4px;overflow:hidden;">
              <div id="pwdBar" style="height:100%;width:0;border-radius:2px;transition:all .3s;"></div>
            </div>
            <div id="pwdHint" style="font-size:.7rem;color:var(--muted);margin-top:3px;min-height:1rem;"></div>
          </div>
          <div class="field"><label>Repetir nueva</label><input type="password" id="pPwdR" placeholder="repite"></div>
        </div>
        <div style="display:flex;justify-content:flex-end;margin-top:.5rem;">
          <button class="btn btn-primary" onclick="cambiarPwd()">Actualizar contraseña</button>
        </div>
      </div>
    </div>

    <!-- Preguntas de seguridad -->
    <div class="card">
      <div class="card-head"><h3>Preguntas de seguridad</h3>
        <span style="font-size:.75rem;color:var(--muted);">Se usan para recuperar tu contraseña</span>
      </div>
      <div class="card-body">
        <div class="form-grid">
          <div class="field"><label>Pregunta 1</label>
            <select id="pPrg1">
              <?php $pregs=['¿Cuál es el nombre de tu primera mascota?','¿En qué ciudad naciste?','¿Cuál es el apellido de tu madre?','¿Cuál fue el nombre de tu primera escuela?','¿Cuál es tu comida favorita?','¿Cuál es el nombre de tu mejor amigo de infancia?'];
              foreach($pregs as $p): ?>
              <option <?=$u['preg1']===$p?'selected':''?>><?=htmlspecialchars($p)?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field"><label>Respuesta 1 (nueva)</label><input id="pR1" placeholder="Escribe para cambiar"></div>
          <div class="field"><label>Pregunta 2</label>
            <select id="pPrg2">
              <?php foreach($pregs as $p): ?>
              <option <?=$u['preg2']===$p?'selected':''?>><?=htmlspecialchars($p)?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field"><label>Respuesta 2 (nueva)</label><input id="pR2" placeholder="Escribe para cambiar"></div>
        </div>
        <div style="display:flex;justify-content:flex-end;margin-top:.5rem;">
          <button class="btn btn-primary" onclick="guardarPregs()">Guardar preguntas</button>
        </div>
      </div>
    </div>

  </div>
</div>

<script>
async function subirFoto(input) {
  if (!input.files[0]) return;
  const fd = new FormData();
  fd.append('foto', input.files[0]);
  fd.append('tipo', 'usuario');
  fd.append('id', '<?=$_uid?>');
  const r = await fetch('api/upload_foto.php',{method:'POST',body:fd});
  const d = await r.json();
  if (d.ok) {
    toast('Foto actualizada.');
    const img = document.getElementById('fotoImg');
    const ini = document.getElementById('fotoIni');
    img.src = d.foto + '?t=' + Date.now();
    img.style.display = 'block';
    if (ini) ini.style.display = 'none';
  } else toast(d.msg, 'err');
}

// ── Validación de contraseña segura ─────────────────────────
function validarPassword(pwd) {
  if (pwd.length < 8)         return 'La contraseña debe tener al menos 8 caracteres.';
  if (!/[A-Z]/.test(pwd))     return 'Debe contener al menos una letra mayúscula.';
  if (!/[a-z]/.test(pwd))     return 'Debe contener al menos una letra minúscula.';
  if (!/[0-9!@#$%^&*()_+\-=\[\]{};\':",.<>?/|\\`~]/.test(pwd))
                               return 'Debe contener al menos un número o carácter especial.';
  return null; // válida
}
function renderStrength(v, barId) {
  const b = document.getElementById(barId);
  if (!b) return;
  let s = 0;
  if (v.length >= 8) s++;
  if (/[A-Z]/.test(v)) s++;
  if (/[a-z]/.test(v)) s++;
  if (/[0-9]/.test(v)) s++;
  if (/[^a-zA-Z0-9]/.test(v)) s++;
  const w = [0,20,40,60,80,100][s];
  const colors = ['','#ef4444','#f59e0b','#f59e0b','#22c55e','#16a34a'];
  b.style.width = w + '%';
  b.style.background = colors[s] || '#ef4444';
}

async function guardarDatos() {
  if(!validarForm([
    {id:'pU', label:'Usuario', tipo:'texto', min:3},
    {id:'pM', label:'Correo',  tipo:'email'},
  ])) return;
  const d = await ajax('perfil_update',{
    usuario: document.getElementById('pU').value,
    correo:  document.getElementById('pM').value,
  });
  if (d?.ok) toast(d.msg); else Ibbs.error(d?.msg||'Error al guardar');
}
async function cambiarPwd() {
  const act = document.getElementById('pPwdAct').value;
  const n   = document.getElementById('pPwdN').value;
  const r   = document.getElementById('pPwdR').value;
  if (!act||!n) { toast('Completa los campos.','err'); return; }
  const pwdErr = validarPassword(n);
  if (pwdErr) { toast(pwdErr, 'err'); document.getElementById('pwdHint').textContent = pwdErr; document.getElementById('pwdHint').style.color='#dc2626'; return; }
  if (n!==r)  { toast('Las contraseñas no coinciden.','err'); return; }
  const d = await ajax('perfil_pwd',{actual:act,nueva:n});
  if (d?.ok) { toast(d.msg); document.getElementById('pPwdAct').value=''; document.getElementById('pPwdN').value=''; document.getElementById('pPwdR').value=''; }
  else toast(d?.msg||'Error','err');
}
async function guardarPregs() {
  const r1 = document.getElementById('pR1').value;
  const r2 = document.getElementById('pR2').value;
  if (!r1||!r2) { toast('Escribe ambas respuestas.','err'); return; }
  const d = await ajax('perfil_pregs',{
    preg1: document.getElementById('pPrg1').value,
    resp1: r1,
    preg2: document.getElementById('pPrg2').value,
    resp2: r2,
  });
  if (d?.ok) { toast(d.msg); document.getElementById('pR1').value=''; document.getElementById('pR2').value=''; }
  else toast(d?.msg||'Error','err');
}
</script>
<?php if(in_array($_rol,['superadmin','admin'])): ?>
<!-- ── PIN DE ELIMINACIÓN ─────────────────────────────────── -->
<div class="card" style="margin-top:1.2rem;border:1.5px solid var(--border);">
  <div class="card-head">
    <div style="display:flex;align-items:center;gap:.7rem;">
      <div style="width:34px;height:34px;border-radius:50%;background:#fee2e2;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
      </div>
      <div>
        <h3>PIN de eliminación</h3>
        <span style="font-size:.75rem;color:var(--muted);">Requerido para borrar alumnos, docentes, usuarios y resetear BD</span>
      </div>
    </div>
  </div>
  <div class="card-body">
    <div id="alertPinSync" style="background:#fef9c3;border:1px solid #fde047;border-radius:9px;padding:.7rem 1rem;font-size:.82rem;color:#854d0e;margin-bottom:1rem;display:flex;align-items:center;justify-content:space-between;gap:.8rem;flex-wrap:wrap;">
      <span>⚠ Si el modal de confirmación no acepta tu contraseña, haz clic en <strong>Sincronizar</strong> para vincular tu contraseña actual como PIN.</span>
      <button class="btn btn-sm btn-secondary" onclick="mostrarSync()" style="flex-shrink:0;">Sincronizar</button>
    </div>
    <div id="syncForm" style="display:none;background:var(--cream);border:1px solid var(--border);border-radius:9px;padding:1rem;margin-bottom:1rem;">
      <p style="font-size:.82rem;color:var(--muted);margin-bottom:.8rem;">Ingresa tu contraseña de acceso para sincronizarla como PIN de eliminación:</p>
      <div style="display:flex;gap:.6rem;align-items:flex-end;">
        <div class="field" style="flex:1;margin:0;">
          <input type="password" id="syncPwd" placeholder="Tu contraseña actual…">
        </div>
        <button class="btn btn-primary" onclick="syncPin()" style="padding:.72rem 1.2rem;flex-shrink:0;">Confirmar</button>
        <button class="btn btn-secondary" onclick="document.getElementById('syncForm').style.display='none'" style="flex-shrink:0;">Cancelar</button>
      </div>
      <div id="syncErr" style="color:#dc2626;font-size:.78rem;margin-top:.4rem;"></div>
    </div>
    <p style="font-size:.82rem;color:var(--muted);margin-bottom:1.1rem;line-height:1.6;">
      También puedes configurar un PIN personalizado diferente a tu contraseña:
    </p>
    <div id="errPin" style="background:#fee2e2;border:1px solid #fecaca;color:#dc2626;border-radius:8px;padding:.6rem .9rem;font-size:.82rem;margin-bottom:.8rem;display:none;"></div>
    <div id="okPin"  style="background:#dcfce7;border:1px solid #bbf7d0;color:#16a34a;border-radius:8px;padding:.6rem .9rem;font-size:.82rem;margin-bottom:.8rem;display:none;"></div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:.8rem;margin-bottom:1rem;">
      <div class="field">
        <label>Nuevo PIN <span style="font-weight:400;color:var(--muted);">(mín. 4 caracteres)</span></label>
        <input type="password" id="pinNuevo" placeholder="••••••" autocomplete="new-password">
      </div>
      <div class="field">
        <label>Confirmar PIN</label>
        <input type="password" id="pinConfirm" placeholder="••••••" autocomplete="new-password">
      </div>
    </div>
    <button class="btn btn-primary" style="width:auto;padding:.7rem 1.8rem;" onclick="guardarPin()">
      Guardar PIN
    </button>
  </div>
</div>
<?php endif; ?>

<script>
function mostrarSync() {
  document.getElementById('syncForm').style.display = 'block';
  setTimeout(()=>document.getElementById('syncPwd').focus(), 100);
}
async function syncPin() {
  const pwd = document.getElementById('syncPwd').value;
  const errEl = document.getElementById('syncErr');
  errEl.textContent = '';
  if (!pwd) { errEl.textContent = 'Ingresa tu contraseña.'; return; }
  const d = await ajax('sync_delete_pin', {pwd});
  if (d?.ok) {
    document.getElementById('syncForm').style.display = 'none';
    document.getElementById('alertPinSync').style.display = 'none';
    document.getElementById('okPin').textContent = d.msg;
    document.getElementById('okPin').style.display = 'block';
    document.getElementById('syncPwd').value = '';
  } else {
    errEl.textContent = d?.msg || 'Error';
  }
}
async function guardarPin() {
  const pin     = document.getElementById('pinNuevo').value;
  const confirm = document.getElementById('pinConfirm').value;
  const errEl   = document.getElementById('errPin');
  const okEl    = document.getElementById('okPin');
  errEl.style.display = 'none'; okEl.style.display = 'none';
  if (!pin) { errEl.textContent='Ingresa un PIN.'; errEl.style.display='block'; return; }
  if (pin !== confirm) { errEl.textContent='Los PINs no coinciden.'; errEl.style.display='block'; return; }
  const d = await ajax('set_delete_pin', {pin, confirm});
  if (d?.ok) {
    okEl.textContent = d.msg; okEl.style.display = 'block';
    document.getElementById('pinNuevo').value = '';
    document.getElementById('pinConfirm').value = '';
  } else {
    errEl.textContent = d?.msg || 'Error'; errEl.style.display = 'block';
  }
}
</script>

<?php include __DIR__.'/layout/foot.php'; ?>