<?php
session_start();
if (!empty($_SESSION['loggedin'])) { header('Location: index.php'); exit; }

require_once __DIR__.'/config/database.php';

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action'])) {
    ob_start(); error_reporting(0);
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['action'];

    // ── LOGIN ────────────────────────────────────────────────
    if ($action==='login') {
        $con = db();
        if (!$con) { echo json_encode(['ok'=>false,'msg'=>'Error de BD.']); exit; }
        $u = trim($_POST['usuario']??'');
        $p = trim($_POST['password']??'');
        $st = mysqli_prepare($con,"SELECT id,usuario,password_hash,rol,activo,foto FROM usuarios WHERE (usuario=? OR correo=? OR cedula=?) LIMIT 1");
        mysqli_stmt_bind_param($st,'sss',$u,$u,$u);
        mysqli_stmt_execute($st);
        $r = mysqli_stmt_get_result($st);
        $row = mysqli_fetch_assoc($r);
        if (!$row) { echo json_encode(['ok'=>false,'msg'=>'Usuario no encontrado.']); exit; }
        if (!$row['activo']) { echo json_encode(['ok'=>false,'msg'=>'Cuenta desactivada.']); exit; }
        if (!password_verify($p,$row['password_hash'])) { echo json_encode(['ok'=>false,'msg'=>'Contraseña incorrecta.']); exit; }
        $_SESSION['loggedin']  = true;
        $_SESSION['user_id']   = $row['id'];
        $_SESSION['usuario']   = $row['usuario'];
        $_SESSION['rol']       = $row['rol'];
        $_SESSION['foto']      = $row['foto'];
        echo json_encode(['ok'=>true,'msg'=>'Bienvenido.']); exit;
    }

    // ── REGISTRO PASO 1: validar datos básicos (sin guardar) ──
    if ($action==='reg_check') {
        $con = db();
        if (!$con) { echo json_encode(['ok'=>false,'msg'=>'Error de BD.']); exit; }
        $u    = trim($_POST['usuario']??'');
        $mail = trim($_POST['correo']??'');
        $ced  = trim($_POST['cedula']??'');
        $pwd  = trim($_POST['password']??'');
        $rep  = trim($_POST['repetir']??'');
        if (!$u||!$mail||!$ced||!$pwd) { echo json_encode(['ok'=>false,'msg'=>'Completa todos los campos.']); exit; }
        if (strlen($pwd)<6)            { echo json_encode(['ok'=>false,'msg'=>'La contraseña debe tener mínimo 6 caracteres.']); exit; }
        if ($pwd!==$rep)               { echo json_encode(['ok'=>false,'msg'=>'Las contraseñas no coinciden.']); exit; }
        $st = mysqli_prepare($con,"SELECT id FROM usuarios WHERE usuario=? OR correo=? OR cedula=?");
        mysqli_stmt_bind_param($st,'sss',$u,$mail,$ced);
        mysqli_stmt_execute($st); mysqli_stmt_store_result($st);
        if (mysqli_stmt_num_rows($st)>0) { echo json_encode(['ok'=>false,'msg'=>'Usuario, correo o cédula ya registrado.']); exit; }
        // Store in session temp
        $_SESSION['reg_tmp'] = ['u'=>$u,'mail'=>$mail,'ced'=>$ced,'pwd'=>$pwd];
        echo json_encode(['ok'=>true]); exit;
    }

    // ── REGISTRO PASO 2: preguntas de seguridad ───────────────
    if ($action==='reg_pregs') {
        if (empty($_SESSION['reg_tmp'])) { echo json_encode(['ok'=>false,'msg'=>'Sesión expirada. Empieza de nuevo.']); exit; }
        $p1 = trim($_POST['preg1']??'');
        $r1 = strtolower(trim($_POST['resp1']??''));
        $p2 = trim($_POST['preg2']??'');
        $r2 = strtolower(trim($_POST['resp2']??''));
        if (!$p1||!$r1||!$p2||!$r2) { echo json_encode(['ok'=>false,'msg'=>'Responde ambas preguntas.']); exit; }
        if ($p1===$p2) { echo json_encode(['ok'=>false,'msg'=>'Las dos preguntas deben ser diferentes.']); exit; }
        $_SESSION['reg_tmp']['p1'] = $p1;
        $_SESSION['reg_tmp']['r1'] = $r1;
        $_SESSION['reg_tmp']['p2'] = $p2;
        $_SESSION['reg_tmp']['r2'] = $r2;
        echo json_encode(['ok'=>true]); exit;
    }

    // ── REGISTRO PASO 3: confirmar contraseña y crear cuenta ──
    if ($action==='reg_finish') {
        if (empty($_SESSION['reg_tmp']['r1'])) { echo json_encode(['ok'=>false,'msg'=>'Sesión expirada. Empieza de nuevo.']); exit; }
        $tmp  = $_SESSION['reg_tmp'];
        $pwd2 = trim($_POST['password']??'');
        $rep2 = trim($_POST['repetir']??'');
        if ($pwd2 !== $tmp['pwd']) { echo json_encode(['ok'=>false,'msg'=>'La contraseña no coincide con la del paso 1.']); exit; }
        if ($pwd2 !== $rep2)       { echo json_encode(['ok'=>false,'msg'=>'Las contraseñas no coinciden.']); exit; }
        $con = db();
        if (!$con) { echo json_encode(['ok'=>false,'msg'=>'Error de BD.']); exit; }
        $hash = password_hash($pwd2, PASSWORD_BCRYPT);
        $rh1  = password_hash($tmp['r1'], PASSWORD_BCRYPT);
        $rh2  = password_hash($tmp['r2'], PASSWORD_BCRYPT);
        $st = mysqli_prepare($con,"INSERT INTO usuarios(usuario,correo,cedula,password_hash,rol,preg1,resp1_hash,preg2,resp2_hash) VALUES(?,?,?,?,'profesor',?,?,?,?)");
        mysqli_stmt_bind_param($st,'ssssssss',$tmp['u'],$tmp['mail'],$tmp['ced'],$hash,$tmp['p1'],$rh1,$tmp['p2'],$rh2);
        if (mysqli_stmt_execute($st)) {
            unset($_SESSION['reg_tmp']);
            echo json_encode(['ok'=>true,'msg'=>'¡Cuenta creada exitosamente! Ya puedes iniciar sesión.']);
        } else {
            echo json_encode(['ok'=>false,'msg'=>mysqli_error($con)]);
        }
        exit;
    }

    // ── RECUPERAR PASO 1: verificar cédula ───────────────────
    if ($action==='rec_cedula') {
        $con = db();
        $ced = trim($_POST['cedula']??'');
        $st = mysqli_prepare($con,"SELECT id,usuario,preg1,preg2 FROM usuarios WHERE cedula=? AND activo=1 LIMIT 1");
        mysqli_stmt_bind_param($st,'s',$ced); mysqli_stmt_execute($st);
        $r = mysqli_stmt_get_result($st); $row = mysqli_fetch_assoc($r);
        if (!$row) { echo json_encode(['ok'=>false,'msg'=>'Cédula no registrada.']); exit; }
        if (!$row['preg1']) { echo json_encode(['ok'=>false,'msg'=>'Esta cuenta no tiene preguntas de seguridad.']); exit; }
        echo json_encode(['ok'=>true,'data'=>['uid'=>$row['id'],'usuario'=>$row['usuario'],'preg1'=>$row['preg1'],'preg2'=>$row['preg2']]]);
        exit;
    }

    // ── RECUPERAR PASO 2: verificar respuestas ────────────────
    if ($action==='rec_verificar') {
        $con = db();
        $uid = (int)($_POST['uid']??0);
        $r1  = strtolower(trim($_POST['resp1']??''));
        $r2  = strtolower(trim($_POST['resp2']??''));
        $st = mysqli_prepare($con,"SELECT resp1_hash,resp2_hash FROM usuarios WHERE id=? LIMIT 1");
        mysqli_stmt_bind_param($st,'i',$uid); mysqli_stmt_execute($st);
        $res = mysqli_stmt_get_result($st); $row = mysqli_fetch_assoc($res);
        if (!$row) { echo json_encode(['ok'=>false,'msg'=>'Usuario no encontrado.']); exit; }
        if (!password_verify($r1,$row['resp1_hash']) || !password_verify($r2,$row['resp2_hash'])) {
            echo json_encode(['ok'=>false,'msg'=>'Una o más respuestas son incorrectas.']); exit;
        }
        $_SESSION['rec_uid'] = $uid;
        echo json_encode(['ok'=>true]); exit;
    }

    // ── RECUPERAR PASO 3: nueva contraseña ────────────────────
    if ($action==='rec_newpwd') {
        $con = db();
        $uid = (int)($_SESSION['rec_uid']??0);
        $pwd = trim($_POST['password']??'');
        $rep = trim($_POST['repetir']??'');
        if (!$uid)          { echo json_encode(['ok'=>false,'msg'=>'Sesión expirada.']); exit; }
        if(strlen($pwd)<8){echo json_encode(['ok'=>false,'msg'=>'La contraseña debe tener al menos 8 caracteres.']);exit;}
     if(!preg_match('/[A-Z]/',$pwd)){echo json_encode(['ok'=>false,'msg'=>'Debe contener al menos una mayúscula.']);exit;}
     if(!preg_match('/[a-z]/',$pwd)){echo json_encode(['ok'=>false,'msg'=>'Debe contener al menos una minúscula.']);exit;}
     if(!preg_match('/[0-9!@#$%^&*()\_+\-=\[\]{};\':",.<>?\/|`~]/',$pwd)){echo json_encode(['ok'=>false,'msg'=>'Debe contener al menos un número o carácter especial.']);exit;}
        if ($pwd!==$rep)    { echo json_encode(['ok'=>false,'msg'=>'Las contraseñas no coinciden.']); exit; }
        $hash = password_hash($pwd, PASSWORD_BCRYPT);
        $st = mysqli_prepare($con,"UPDATE usuarios SET password_hash=? WHERE id=?");
        mysqli_stmt_bind_param($st,'si',$hash,$uid); mysqli_stmt_execute($st);
        unset($_SESSION['rec_uid']);
        echo json_encode(['ok'=>true,'msg'=>'Contraseña actualizada correctamente.']); exit;
    }

    echo json_encode(['ok'=>false,'msg'=>'Acción no reconocida.']); exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>IBBS — Acceso</title>
<style>
/* Fuentes locales */
@font-face { font-family:'Nunito'; font-style:normal; font-weight:400; font-display:swap;
  src: url('assets/libs/fonts/nunito/nunito-latin-400-normal.woff2') format('woff2'); }
@font-face { font-family:'Nunito'; font-style:normal; font-weight:500; font-display:swap;
  src: url('assets/libs/fonts/nunito/nunito-latin-500-normal.woff2') format('woff2'); }
@font-face { font-family:'Nunito'; font-style:normal; font-weight:600; font-display:swap;
  src: url('assets/libs/fonts/nunito/nunito-latin-600-normal.woff2') format('woff2'); }
@font-face { font-family:'Nunito'; font-style:normal; font-weight:700; font-display:swap;
  src: url('assets/libs/fonts/nunito/nunito-latin-700-normal.woff2') format('woff2'); }
@font-face { font-family:'Playfair Display'; font-style:normal; font-weight:400; font-display:swap;
  src: url('assets/libs/fonts/playfair/playfair-display-latin-400-normal.woff2') format('woff2'); }
@font-face { font-family:'Playfair Display'; font-style:italic; font-weight:400; font-display:swap;
  src: url('assets/libs/fonts/playfair/playfair-display-latin-400-italic.woff2') format('woff2'); }
</style>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{min-height:100vh;display:flex;font-family:'Nunito',sans-serif;background:#1a4d2e;}

/* ── LEFT ─────────────────────────────────── */
.left{flex:1;position:relative;overflow:hidden;display:flex;flex-direction:column;align-items:center;justify-content:flex-end;min-height:100vh;}
.left-bg{position:absolute;inset:0;background:url('assets/grad.png') center/cover no-repeat;filter:brightness(.55) saturate(.8);}
.left-grad{position:absolute;inset:0;background:linear-gradient(to bottom,rgba(26,77,46,.1) 0%,rgba(26,77,46,.15) 40%,rgba(26,77,46,.85) 100%);}
.left-glow{position:absolute;inset:0;background:radial-gradient(ellipse at 30% 70%, rgba(57,255,20,.08) 0%, transparent 60%);}
.left-content{position:relative;z-index:2;padding:2.5rem 2.8rem;width:100%;}
.left-logo{display:flex;align-items:center;gap:1rem;margin-bottom:2rem;}
.left-logo img{width:84px;height:84px;border-radius:50%;object-fit:cover;border:3px solid rgba(57,255,20,.6);box-shadow:0 0 28px rgba(57,255,20,.3);}
.left-logo-text strong{font-family:'Playfair Display',serif;font-size:1.55rem;color:#fff;display:block;}
.left-logo-text small{font-size:.74rem;text-transform:uppercase;letter-spacing:2px;color:rgba(255,255,255,.55);}
.left-quote{font-family:'Playfair Display',serif;font-size:1.2rem;color:rgba(255,255,255,.9);line-height:1.75;font-style:italic;max-width:440px;margin-bottom:.7rem;}
.left-quote-ref{font-size:.72rem;text-transform:uppercase;letter-spacing:2px;color:rgba(57,255,20,.7);}

/* ── RIGHT ────────────────────────────────── */
.right{width:520px;min-height:100vh;background:#f5f0e8;display:flex;align-items:flex-start;justify-content:center;padding:0;flex-shrink:0;overflow-y:auto;}
.card{width:100%;padding:3rem 3.2rem;min-height:100vh;display:flex;flex-direction:column;justify-content:center;}
.logo-sm{display:flex;align-items:center;gap:.8rem;margin-bottom:2.2rem;}
.logo-sm img{width:46px;height:46px;border-radius:50%;object-fit:cover;}
.logo-sm strong{font-family:'Playfair Display',serif;font-size:1.2rem;color:#1a4d2e;}
.logo-sm small{display:block;font-size:.65rem;text-transform:uppercase;letter-spacing:1.5px;color:#888;}
h2{font-family:'Playfair Display',serif;font-size:2rem;margin-bottom:.3rem;color:#1a4d2e;}
.sub{font-size:.88rem;color:#777;margin-bottom:1.8rem;font-weight:400;}

/* ── Steps indicator ──────────────────────── */
.steps-wrap{display:flex;align-items:center;gap:0;margin-bottom:1.8rem;}
.step-item{display:flex;flex-direction:column;align-items:center;flex:1;position:relative;}
.step-item:not(:last-child)::after{content:'';position:absolute;top:14px;left:50%;width:100%;height:2px;background:#ddd;z-index:0;}
.step-item.done::after{background:#1a4d2e;}
.step-item.active::after{background:linear-gradient(90deg,#1a4d2e,#ddd);}
.step-circle{width:28px;height:28px;border-radius:50%;border:2px solid #ddd;background:#fff;display:flex;align-items:center;justify-content:center;font-size:.72rem;font-weight:700;color:#aaa;z-index:1;transition:all .3s;}
.step-item.done .step-circle{background:#1a4d2e;border-color:#1a4d2e;color:#39ff14;}
.step-item.active .step-circle{background:#1a4d2e;border-color:#1a4d2e;color:#39ff14;box-shadow:0 0 0 4px rgba(26,77,46,.15);}
.step-lbl{font-size:.6rem;text-transform:uppercase;letter-spacing:.6px;color:#aaa;margin-top:.3rem;text-align:center;font-weight:600;}
.step-item.active .step-lbl,.step-item.done .step-lbl{color:#1a4d2e;}

/* ── Form ─────────────────────────────────── */
.field{margin-bottom:1.1rem;}
.field label{display:block;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:#555;margin-bottom:.4rem;}
.field input,.field select{width:100%;padding:.8rem 1rem;border:1.5px solid #ddd;border-radius:10px;font-size:.92rem;font-family:'Nunito',sans-serif;background:#fff;outline:none;transition:border .2s,box-shadow .2s;color:#1a4d2e;}
.field input:focus,.field select:focus{border-color:#39ff14;box-shadow:0 0 0 3px rgba(57,255,20,.12);}
.field-row{display:grid;grid-template-columns:1fr 1fr;gap:.8rem;}
.btn{width:100%;padding:.95rem;border:none;border-radius:10px;font-size:.95rem;font-weight:700;cursor:pointer;font-family:'Nunito',sans-serif;transition:all .2s;margin-top:.5rem;letter-spacing:.2px;}
.btn-primary{background:#1a4d2e;color:#39ff14;}
.btn-primary:hover{background:#1e5c36;transform:translateY(-1px);box-shadow:0 4px 16px rgba(26,77,46,.25);}
.btn-primary:disabled{opacity:.6;cursor:not-allowed;transform:none;}
.btn-outline{background:transparent;color:#1a4d2e;border:1.5px solid #1a4d2e;margin-top:.6rem;}
.btn-outline:hover{background:#f0fdf4;}
.link-row{text-align:center;margin-top:1.2rem;font-size:.84rem;color:#888;}
.link-row a{color:#1a4d2e;font-weight:700;cursor:pointer;text-decoration:none;}
.link-row a:hover{text-decoration:underline;}
.err{background:#fee2e2;border:1px solid #fecaca;color:#dc2626;border-radius:8px;padding:.65rem 1rem;font-size:.84rem;margin-bottom:1rem;display:none;}
.ok{background:#dcfce7;border:1px solid #bbf7d0;color:#16a34a;border-radius:8px;padding:.65rem 1rem;font-size:.84rem;margin-bottom:1rem;display:none;}
.strength-bar{height:4px;border-radius:2px;background:#eee;margin-top:.35rem;overflow:hidden;}
.strength-fill{height:100%;width:0;border-radius:2px;transition:all .3s;}
.pane{display:none;}.pane.active{display:block;}
.info-box{background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:.75rem 1rem;margin-bottom:1.2rem;font-size:.82rem;color:#15803d;display:flex;gap:.6rem;align-items:flex-start;}
.info-box svg{flex-shrink:0;margin-top:1px;}
.divider{border:none;border-top:1px solid #e8e3d8;margin:1.4rem 0;}
/* Rec step dots compact */
.step-dots{display:flex;gap:.4rem;justify-content:center;margin-bottom:1.4rem;}
.step-dot{width:8px;height:8px;border-radius:50%;background:#ddd;transition:background .3s;}
.step-dot.active{background:#1a4d2e;}

@media(max-width:760px){.left{display:none;}.right{width:100%;}.card{padding:2rem 1.6rem;}}
</style>
</head>
<body>

<div class="left">
  <div class="left-bg"></div>
  <div class="left-grad"></div>
  <div class="left-glow"></div>
  <div class="left-content">
    <div class="left-logo">
      <img src="assets/logo.jpg" alt="IBBS">
      <div class="left-logo-text">
        <strong>Instituto Bíblico Bautista del Sur</strong>
        <small>Sistema Académico IBBS</small>
      </div>
    </div>
    <p class="left-quote">"Procura con diligencia presentarte a Dios aprobado, como obrero que no tiene de qué avergonzarse."</p>
    <p class="left-quote-ref">— 2 Timoteo 2:15</p>
  </div>
</div>

<div class="right">
  <div class="card">
    <div class="logo-sm">
      <img src="assets/logo.jpg" alt="IBBS">
      <div><strong>IBBS</strong><small>Sistema Académico</small></div>
    </div>

    <!-- ══ LOGIN ══════════════════════════════════════════ -->
    <div id="pLogin" class="pane active">
      <h2>Bienvenido</h2>
      <p class="sub">Inicia sesión en tu cuenta</p>
      <div id="errLogin" class="err"></div>
      <div class="field"><label>Usuario, correo o cédula</label>
        <input id="lUser" type="text" autocomplete="username" placeholder="tu usuario o cédula"></div>
      <div class="field"><label>Contraseña</label>
        <input id="lPwd" type="password" autocomplete="current-password" placeholder="••••••••"></div>
      <button class="btn btn-primary" id="btnLogin" onclick="doLogin()">Iniciar sesión</button>
      <div class="link-row">
        ¿No tienes cuenta? <a onclick="show('pReg1')">Regístrate</a> &nbsp;·&nbsp;
        <a onclick="show('pRec1')">¿Olvidaste tu contraseña?</a>
      </div>
    </div>

    <!-- ══ REGISTRO — PASO 1: Datos básicos ══════════════ -->
    <div id="pReg1" class="pane">
      <h2>Crear cuenta</h2>
      <p class="sub">Paso 1 de 3 — Datos de acceso</p>
      <div class="steps-wrap">
        <div class="step-item active"><div class="step-circle">1</div><div class="step-lbl">Datos</div></div>
        <div class="step-item"><div class="step-circle">2</div><div class="step-lbl">Seguridad</div></div>
        <div class="step-item"><div class="step-circle">3</div><div class="step-lbl">Confirmar</div></div>
      </div>
      <div id="errReg1" class="err"></div>
      <div class="field-row">
        <div class="field"><label>Usuario *</label><input id="rU" data-only="username" placeholder="ej. jperez" autocomplete="off"></div>
        <div class="field"><label>Cédula *</label><input id="rCed" data-only="cedula" placeholder="12345678" autocomplete="off"></div>
      </div>
      <div class="field"><label>Correo electrónico *</label><input id="rM" type="email" placeholder="correo@gmail.com"></div>
      <div class="field-row">
        <div class="field"><label>Contraseña *</label>
          <input id="rP" type="password" placeholder="mín. 8 car., mayús., minús., nº" oninput="strength(this.value)">
          <div class="strength-bar"><div id="sbar" class="strength-fill"></div></div>
        </div>
        <div class="field"><label>Repetir contraseña *</label><input id="rP2" type="password" placeholder="repite"></div>
      </div>
      <button class="btn btn-primary" id="btnReg1" onclick="doReg1()">Continuar →</button>
      <div class="link-row"><a onclick="show('pLogin')">← Volver al login</a></div>
    </div>

    <!-- ══ REGISTRO — PASO 2: Preguntas de seguridad ═════ -->
    <div id="pReg2" class="pane">
      <h2>Preguntas de seguridad</h2>
      <p class="sub">Paso 2 de 3 — Para recuperar tu cuenta</p>
      <div class="steps-wrap">
        <div class="step-item done"><div class="step-circle">✓</div><div class="step-lbl">Datos</div></div>
        <div class="step-item active"><div class="step-circle">2</div><div class="step-lbl">Seguridad</div></div>
        <div class="step-item"><div class="step-circle">3</div><div class="step-lbl">Confirmar</div></div>
      </div>
      <div class="info-box">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        Estas preguntas se usarán para verificar tu identidad si olvidas tu contraseña. Las dos preguntas deben ser diferentes.
      </div>
      <div id="errReg2" class="err"></div>
      <div class="field"><label>Pregunta 1</label>
        <select id="rPrg1">
          <option value="">— Elige una pregunta —</option>
          <option>¿Cuál es el nombre de tu primera mascota?</option>
          <option>¿En qué ciudad naciste?</option>
          <option>¿Cuál es el apellido de tu madre?</option>
          <option>¿Cuál fue el nombre de tu primera escuela?</option>
          <option>¿Cuál es tu comida favorita?</option>
          <option>¿Cuál es el nombre de tu mejor amigo de infancia?</option>
        </select>
      </div>
      <div class="field"><label>Respuesta 1 *</label><input id="rR1" placeholder="Tu respuesta" autocomplete="off"></div>
      <div class="divider"></div>
      <div class="field"><label>Pregunta 2</label>
        <select id="rPrg2">
          <option value="">— Elige una pregunta —</option>
          <option>¿Cuál es el nombre de tu primera mascota?</option>
          <option>¿En qué ciudad naciste?</option>
          <option>¿Cuál es el apellido de tu madre?</option>
          <option>¿Cuál fue el nombre de tu primera escuela?</option>
          <option>¿Cuál es tu comida favorita?</option>
          <option>¿Cuál es el nombre de tu mejor amigo de infancia?</option>
        </select>
      </div>
      <div class="field"><label>Respuesta 2 *</label><input id="rR2" placeholder="Tu respuesta" autocomplete="off"></div>
      <button class="btn btn-primary" id="btnReg2" onclick="doReg2()">Continuar →</button>
      <button class="btn btn-outline" onclick="show('pReg1')">← Atrás</button>
    </div>

    <!-- ══ REGISTRO — PASO 3: Confirmar contraseña ═══════ -->
    <div id="pReg3" class="pane">
      <h2>Confirmar contraseña</h2>
      <p class="sub">Paso 3 de 3 — Confirma y crea tu cuenta</p>
      <div class="steps-wrap">
        <div class="step-item done"><div class="step-circle">✓</div><div class="step-lbl">Datos</div></div>
        <div class="step-item done"><div class="step-circle">✓</div><div class="step-lbl">Seguridad</div></div>
        <div class="step-item active"><div class="step-circle">3</div><div class="step-lbl">Confirmar</div></div>
      </div>
      <div class="info-box">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
        Ingresa tu contraseña una última vez para confirmar la creación de tu cuenta.
      </div>
      <div id="errReg3" class="err"></div>
      <div id="okReg3" class="ok"></div>
      <div class="field"><label>Contraseña</label>
        <input id="rP3" type="password" placeholder="La misma del paso 1"></div>
      <div class="field"><label>Repetir contraseña</label>
        <input id="rP4" type="password" placeholder="Repite tu contraseña"></div>
      <button class="btn btn-primary" id="btnReg3" onclick="doReg3()">✓ Crear mi cuenta</button>
      <button class="btn btn-outline" onclick="show('pReg2')">← Atrás</button>
    </div>

    <!-- ══ RECUPERAR — Paso 1 ══════════════════════════════ -->
    <div id="pRec1" class="pane">
      <h2>Recuperar contraseña</h2>
      <p class="sub">Ingresa tu cédula para comenzar</p>
      <div class="step-dots"><div class="step-dot active"></div><div class="step-dot"></div><div class="step-dot"></div></div>
      <div id="errRec1" class="err"></div>
      <div class="field"><label>Cédula registrada</label><input id="recCed" placeholder="ej. 12345678"></div>
      <button class="btn btn-primary" onclick="doRec1()">Continuar →</button>
      <div class="link-row"><a onclick="show('pLogin')">← Volver al login</a></div>
    </div>

    <!-- ══ RECUPERAR — Paso 2 ══════════════════════════════ -->
    <div id="pRec2" class="pane">
      <h2>Verificar identidad</h2>
      <p class="sub">Responde tus preguntas de seguridad</p>
      <div class="step-dots"><div class="step-dot"></div><div class="step-dot active"></div><div class="step-dot"></div></div>
      <div id="errRec2" class="err"></div>
      <input type="hidden" id="recUid">
      <div class="field"><label id="lblPrg1">Pregunta 1</label><input id="recR1" placeholder="Tu respuesta"></div>
      <div class="field"><label id="lblPrg2">Pregunta 2</label><input id="recR2" placeholder="Tu respuesta"></div>
      <button class="btn btn-primary" onclick="doRec2()">Verificar →</button>
    </div>

    <!-- ══ RECUPERAR — Paso 3 ══════════════════════════════ -->
    <div id="pRec3" class="pane">
      <h2>Nueva contraseña</h2>
      <p class="sub">Elige una contraseña segura</p>
      <div class="step-dots"><div class="step-dot"></div><div class="step-dot"></div><div class="step-dot active"></div></div>
      <div id="errRec3" class="err"></div>
      <div id="okRec3" class="ok"></div>
      <div class="field"><label>Nueva contraseña</label><input id="recP" type="password" placeholder="mín. 6 caracteres"></div>
      <div class="field"><label>Repetir</label><input id="recP2" type="password" placeholder="repite"></div>
      <button class="btn btn-primary" onclick="doRec3()">Guardar contraseña</button>
    </div>

  </div>
</div>

<script>
function show(id){document.querySelectorAll('.pane').forEach(p=>p.classList.remove('active'));document.getElementById(id).classList.add('active');window.scrollTo(0,0);}
async function post(action,data){const fd=new FormData();fd.append('action',action);Object.keys(data).forEach(k=>fd.append(k,data[k]));const r=await fetch('login.php',{method:'POST',body:fd});return r.json();}
function setErr(id,msg){const el=document.getElementById(id);el.textContent=msg;el.style.display=msg?'block':'none';}
function setOk(id,msg){const el=document.getElementById(id);el.textContent=msg;el.style.display=msg?'block':'none';}
function applyLoginValidation(el) {
  const rule = el.getAttribute('data-only');
  if (!rule) return;
  const patterns = {
    username: /^[a-zA-Z0-9_\.\-]+$/,
    cedula:   /^[0-9VvEe\-]+$/,
    letters:  /^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s\-\.]+$/,
  };
  el.addEventListener('keypress', e => {
    if (e.key.length > 1) return;
    const pat = patterns[rule];
    if (pat && !pat.test(e.key)) e.preventDefault();
  });
}
document.querySelectorAll('[data-only]').forEach(applyLoginValidation);
function validarPassword(pwd) {
  if (pwd.length < 8)       return 'La contraseña debe tener al menos 8 caracteres.';
  if (!/[A-Z]/.test(pwd))   return 'Debe contener al menos una mayúscula.';
  if (!/[a-z]/.test(pwd))   return 'Debe contener al menos una minúscula.';
  if (!/[0-9!@#$%^&*()_+\-=[\]{};':",./<>?|`~]/.test(pwd))
                             return 'Debe contener al menos un número o carácter especial.';
  return null;
}
function strength(v){
  const b=document.getElementById('sbar');
  let s=0;
  if(v.length>=8)s++;
  if(/[A-Z]/.test(v))s++;
  if(/[a-z]/.test(v))s++;
  if(/[0-9]/.test(v))s++;
  if(/[^a-zA-Z0-9]/.test(v))s++;
  const w=[0,20,40,60,80,100][s];
  const col=['','#ef4444','#f59e0b','#f59e0b','#22c55e','#16a34a'][s];
  b.style.width=w+'%';
  b.style.background=col;
}

async function doLogin(){setErr('errLogin','');const btn=document.getElementById('btnLogin');btn.disabled=true;btn.textContent='Entrando…';const d=await post('login',{usuario:document.getElementById('lUser').value,password:document.getElementById('lPwd').value});if(d.ok){window.location='index.php';}else{setErr('errLogin',d.msg);btn.disabled=false;btn.textContent='Iniciar sesión';}}

async function doReg1(){setErr('errReg1','');const btn=document.getElementById('btnReg1');btn.disabled=true;btn.textContent='Verificando…';const pwdErr = validarPassword(document.getElementById('rP').value);
  if (pwdErr) { setErr('errReg1', pwdErr); btn.disabled=false; btn.textContent='Continuar →'; return; }
  const d=await post('reg_check',{usuario:document.getElementById('rU').value,cedula:document.getElementById('rCed').value,correo:document.getElementById('rM').value,password:document.getElementById('rP').value,repetir:document.getElementById('rP2').value});btn.disabled=false;btn.textContent='Continuar →';if(d.ok){show('pReg2');}else setErr('errReg1',d.msg);}

async function doReg2(){setErr('errReg2','');const p1=document.getElementById('rPrg1').value,r1=document.getElementById('rR1').value,p2=document.getElementById('rPrg2').value,r2=document.getElementById('rR2').value;if(!p1||!r1||!p2||!r2){setErr('errReg2','Responde ambas preguntas.');return;}if(p1===p2){setErr('errReg2','Las preguntas deben ser diferentes.');return;}const btn=document.getElementById('btnReg2');btn.disabled=true;btn.textContent='Guardando…';const d=await post('reg_pregs',{preg1:p1,resp1:r1,preg2:p2,resp2:r2});btn.disabled=false;btn.textContent='Continuar →';if(d.ok){show('pReg3');}else setErr('errReg2',d.msg);}

async function doReg3(){setErr('errReg3','');setOk('okReg3','');const btn=document.getElementById('btnReg3');btn.disabled=true;btn.textContent='Creando cuenta…';const d=await post('reg_finish',{password:document.getElementById('rP3').value,repetir:document.getElementById('rP4').value});btn.disabled=false;btn.textContent='✓ Crear mi cuenta';if(d.ok){setOk('okReg3',d.msg);setTimeout(()=>show('pLogin'),2500);}else setErr('errReg3',d.msg);}

async function doRec1(){setErr('errRec1','');const d=await post('rec_cedula',{cedula:document.getElementById('recCed').value});if(!d.ok){setErr('errRec1',d.msg);return;}document.getElementById('recUid').value=d.data.uid;document.getElementById('lblPrg1').textContent=d.data.preg1;document.getElementById('lblPrg2').textContent=d.data.preg2;show('pRec2');}

async function doRec2(){setErr('errRec2','');const d=await post('rec_verificar',{uid:document.getElementById('recUid').value,resp1:document.getElementById('recR1').value,resp2:document.getElementById('recR2').value});if(!d.ok){setErr('errRec2',d.msg);return;}show('pRec3');}

async function doRec3(){setErr('errRec3','');setOk('okRec3','');const d=await post('rec_newpwd',{password:document.getElementById('recP').value,repetir:document.getElementById('recP2').value});if(d.ok){setOk('okRec3',d.msg);setTimeout(()=>show('pLogin'),2500);}else setErr('errRec3',d.msg);}

document.addEventListener('keydown',e=>{if(e.key==='Enter'&&document.getElementById('pLogin').classList.contains('active'))doLogin();});
</script>
</body>
</html>
