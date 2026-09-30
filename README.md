# IBBS — Sistema Académico

Sistema de gestión académica del Instituto Bíblico Bautista del Sur: alumnos,
docentes, materias, inscripciones, asistencias, notas, aula virtual y
reportes en PDF.

PHP plano + MySQL (mysqli), sin framework. Pensado para correr en un entorno
tipo XAMPP/WAMP (Apache + PHP + MariaDB/MySQL) con la raíz del proyecto como
document root.

## Estructura del proyecto

```
├── index.php, login.php, cerrar_sesion.php   # Páginas de entrada (raíz del sitio)
├── modulo_*.php                              # Un módulo por funcionalidad (alumnos, notas, asistencias, aula, ...)
├── layout/                                   # Cabecera y pie de página compartidos (head.php / foot.php)
├── config/
│   ├── bootstrap.php                         # Punto único de arranque: sesión + CSRF + rate limit + BD + auditoría
│   ├── session.php                           # Cookies de sesión seguras (HttpOnly/SameSite/Secure)
│   ├── csrf.php                              # Token CSRF por sesión
│   ├── rate_limit.php                        # Freno a fuerza bruta en login
│   ├── audit.php                             # log_audit() — registro de auditoría compartido
│   └── database.php                          # Conexión a BD centralizada (función db())
├── api/                                      # Endpoints backend, uno por dominio de datos
│   ├── ajax.php                               #   alumnos, docentes, materias, notas, asistencias, usuarios, ...
│   ├── aula.php                               #   Aula Virtual: anuncios, materiales, actividades/calificaciones
│   ├── clases_grabadas.php                    #   Repositorio de videos (YouTube/Drive/Vimeo) por materia
│   ├── clases_vivo.php                        #   Videollamadas (Jitsi Meet / Google Meet / otro) por materia
│   ├── notificaciones_stream.php              #   Push en tiempo real (Server-Sent Events)
│   ├── foro.php, tareas.php                   #   Foro y tareas/entregas (usados desde modulo_aula.php)
│   ├── backup.php, upload_foto.php, export_*.php
├── config/notificaciones.php                 # notificar_usuario() / notificar_materia() — dispara el push
├── assets/                                   # CSS, imágenes, librerías de terceros (Chart.js, SweetAlert2, boxicons, fuentes)
├── uploads/
│   ├── fotos/                                # Fotos de perfil subidas por los usuarios (no versionadas)
│   └── materiales/                           # Archivos del Aula Virtual subidos por docentes (no versionados)
└── database/
    ├── ibbs.sql                              # Dump del esquema de base de datos
    └── migrations/                           # Cambios incrementales de esquema, uno por módulo nuevo
        ├── 001_aula_virtual.sql
        ├── 002_clases_grabadas.sql
        ├── 003_clases_vivo.sql
        ├── 004_notificaciones_tiempo_real.sql
        ├── 005_foro_usuario_id.sql
        ├── 006_alumno_regular_autoinscripcion.sql
        ├── 007_materia_inscripcion_abierta.sql
        └── 008_asegurar_rol_alumno.sql
```

Además de `modulo_*.php`, el portal reactivó los roles alumno/docente con
páginas propias: `portal_alumno.php` y `portal_docente.php` (con foro y
tareas/entregas — `guardar_foro_mensaje.php`, `obtener_mensajes_foro.php`,
`crear_tarea.php`, `procesar_entrega.php`, `calificar_entrega.php`,
`asignar_materia.php`).

`.htaccess` (raíz y `uploads/`) bloquea el acceso directo a `database/`,
a los `.sql` sueltos, y la ejecución de scripts subidos a `uploads/`.

## Configuración de la base de datos

Por defecto se conecta a un MySQL local (`localhost`, usuario `root`, sin
contraseña, base de datos `ibbs`) — el típico setup de XAMPP/WAMP para
desarrollo. Para usar otras credenciales (otro equipo, otro entorno) definí
estas variables de entorno antes de que corra el servidor, sin tocar código:

```
IBBS_DB_HOST=localhost
IBBS_DB_USER=root
IBBS_DB_PASS=
IBBS_DB_NAME=ibbs
```

Importá el esquema inicial con `database/ibbs.sql`, y después cada archivo
de `database/migrations/` en orden (son idempotentes: usan
`CREATE TABLE IF NOT EXISTS`, así que correrlos dos veces no rompe nada).

## Aula Virtual (por materia)

`modulo_aula.php?materia_id=X` — accesible desde el botón "🎓 Aula" en
Materias, o desde "Aula Virtual" en el menú (que primero muestra un
selector con las materias del usuario). Backend en `api/aula.php`.

- **Anuncios**: muro por materia, con opción de fijar arriba.
- **Materiales**: archivos descargables (PDF, Office, TXT, CSV, ZIP,
  imágenes — máx. 25MB), validados por extensión + tipo MIME real, servidos
  siempre a través de `api/aula.php?action=material_download` (nunca por
  URL directa) para poder verificar permisos en cada descarga.
- **Actividades**: evaluaciones propias del aula (no la nota final de
  `modulo_notas.php`) — cada una con su propia nota máxima y una
  calificación por alumno inscrito (tabla `aula_calificaciones`).

Permisos: superadmin/admin gestionan cualquier materia; un profesor solo
gestiona las materias donde está asignado en `materia_docente`. El código
ya deja preparada (pero inactiva) la rama de solo-lectura para un futuro
rol `alumno` — ver `materia_puede_ver()` en `config/materia_permisos.php`.

## Clases Grabadas (por materia)

`modulo_grabaciones.php?materia_id=X` — accesible desde el botón "🎬
Grabadas" en Materias, o desde "Clases Grabadas" en el menú. Backend en
`api/clases_grabadas.php`. Solo guarda el link (YouTube, Google Drive o
Vimeo); no hay servidor de video propio.

Seguridad del embed: el link que pega el docente **nunca** se usa tal
cual como `src` de un `<iframe>`. El backend valida que sea `http`/`https`,
detecta la plataforma por su dominio real (no por lo que diga la URL) y
reconstruye una URL de embed propia y conocida
(`youtube.com/embed/ID`, `player.vimeo.com/video/ID`,
`drive.google.com/file/d/ID/preview`). Un link de un dominio no
reconocido no se embebe — el frontend lo muestra como botón "abrir en
otra pestaña" (`target=_blank rel=noopener`), nunca en un iframe.

## Clases en Vivo (por materia)

`modulo_vivo.php?materia_id=X` — accesible desde el botón "🔴 En Vivo" en
Materias, o desde "Clases en Vivo" en el menú. Backend en
`api/clases_vivo.php`.

Dos formas de sala:
- **Jitsi Meet** (recomendado): el propio sistema genera un nombre de
  sala aleatorio de 64 bits (`vivo_generar_sala()`) y arma el link
  `https://meet.jit.si/…` — no requiere cuenta ni servidor de video
  propio. El "candado" de una sala anónima de Jitsi es el nombre de la
  sala en sí (no hay contraseña por defecto), por eso tiene que ser
  imposible de adivinar; quien reciba el link por fuera del sistema
  también podrá entrar — es una limitación de Jitsi anónimo, no de la
  app.
- **Google Meet / otro**: el docente pega un link creado por fuera
  (Google no deja crear reuniones por API sin OAuth, ni tampoco permite
  que Meet se embeba en un iframe de terceros). El backend valida que
  sea `http`/`https` y, si se eligió "Google Meet", que el host sea
  **exactamente** `meet.google.com` — nunca por coincidencia de texto
  (`url_host_es()` en `config/url_validacion.php`), para no aceptar un
  dominio como `meet.google.com.evil.com` como si fuera real.

El "Unirse" siempre abre en pestaña nueva (`target=_blank
rel=noopener`) — no hay embed de videollamada en vivo dentro del
sistema. El docente puede marcar el estado (Programada / En curso /
Finalizada / Cancelada) manualmente desde la lista.

## Notificaciones en tiempo real

Antes eran *pull*: la campana solo se revisaba una vez al cargar la
página. Ahora `layout/foot.php` abre además una conexión persistente a
`api/notificaciones_stream.php` (Server-Sent Events) apenas carga
cualquier página del sistema — nueva notificación → toast + contador
de la campana al instante, sin recargar.

Por qué SSE y no WebSockets: corre sobre HTTP normal, sin puerto ni
proceso aparte — funciona tal cual en un XAMPP/Apache compartido, que
es donde corre este proyecto. La contra de SSE bajo Apache+mod_php es
que cada conexión abierta ocupa un worker del servidor completo, así
que **cada conexión dura ~25s y se corta sola** — el navegador
(`EventSource`) reconecta automáticamente y de forma nativa recuerda
el último id recibido (`Last-Event-ID`), así que el efecto para el
usuario es push continuo aunque por debajo sean conexiones cortas
encadenadas.

Para que un módulo dispare una notificación, ni sabe que existe SSE —
solo llama a un helper de `config/notificaciones.php` (ya cargado por
el bootstrap) después de guardar lo que sea:

```php
notificar_materia($con, $materia_id, 'anuncio', "Nuevo anuncio: $titulo", $contenido, $uid);
// o para un solo destinatario:
notificar_usuario($con, $usuario_id, 'calificacion', "Tarea calificada", "Tu nota: $nota", $materia_id);
```

`notificar_materia()` le llega a todos los docentes y alumnos
inscritos en esa materia (excepto a quien disparó el evento).
Reutiliza la tabla `notificaciones` que ya existía — la migración 004
solo la extiende (tipo libre en vez de ENUM fijo, + `materia_id`).

Disparadores ya conectados: nuevo anuncio (Aula Virtual), clase en
vivo que arranca, nueva clase grabada, nuevo mensaje de foro, nueva
tarea, y tarea calificada.

### Hallazgos de seguridad al integrar este módulo

Al conectar los disparadores se encontraron y corrigieron varios
problemas en código agregado después de la última revisión de
seguridad:

- **`api/foro.php` y `api/tareas.php`** (enganchados a las pestañas
  Foro/Tareas de `modulo_aula.php`) tenían credenciales de BD
  hardcodeadas (bypaseando `config/database.php`) y ningún token CSRF
  — se reescribieron para usar el bootstrap, y `modulo_aula.php` ya
  manda el token en sus 5 llamadas que modifican datos.
- **`calificar_entrega.php`** exigía `$_SESSION['rol'] === 'docente'`,
  un valor que el sistema nunca asigna (el rol real es `'profesor'`)
  — ningún docente real podía calificar. Corregido.
- Bug pre-existente en `notif_list` (api/ajax.php): las notificaciones
  "para todos" (`usuario_id` NULL) no filtraban por rol — cualquier
  usuario logueado veía las alertas dirigidas a admin. Corregido.
- Se borraron `api/materiales.php` y `modulo_foro_materia.php` —
  código muerto sin ninguna referencia, y el primero además roto
  (ruta inexistente, variable de sesión que no usa el resto del
  sistema).

**Resuelto en un pase posterior — foro/chat unificado:**
`portal_alumno.php` y `portal_docente.php` tenían su propio chat de
foro (`guardar_foro_mensaje.php` / `obtener_mensajes_foro.php`), sin
CSRF y sin validar que el usuario tuviera permiso sobre la materia
(cualquiera logueado podía leer o escribir en el foro de cualquier
materia con solo cambiar el `materia_id`). Se eliminaron esos dos
archivos y ambos portales ahora consumen el mismo `api/foro.php` que ya
usa `modulo_aula.php` — con `<meta name="csrf-token">` agregado a las
dos páginas, `materia_puede_ver()` en cada request, y sanitizado del
mensaje en el cliente (antes se insertaba tal cual en el DOM). También
se sumó autoría real (columna `usuario_id`, migración
`005_foro_usuario_id.sql`) y un botón "Borrar": el autor puede borrar su
propio mensaje, y quien gestiona la materia (admin/superadmin siempre,
profesor solo si está asignado) puede moderar cualquiera — todo
revalidado en el backend, nunca solo ocultando el botón.

**Resuelto — permisos en Calificaciones:** `api/ajax.php`
(`nota_guardar`, `nota_borrar`, `notas_tabla_materia`) y
`api/export_pdf.php` no verificaban `materia_puede_gestionar()` — un
profesor podía cargar o borrar notas, y exportar el PDF, de una materia
que no le pertenece. El selector de materia de `modulo_notas.php`
también listaba todas las materias del sistema en vez de
`materias_asignadas()`. Además, la tabla de notas ahora muestra quién
registró cada nota y cuándo (columnas `nota_registrada_por` /
`nota_actualizada_en`, que ya existían pero no se mostraban).

**Pulido profesional — Calificaciones y Alumnos:** `modulo_notas.php`
suma buscador por nombre/cédula sobre la tabla ya cargada, una card de
promedio general y una barra de aprobación de la materia, y avatar con
inicial por alumno (mismo lenguaje visual que el resto del sistema).
`modulo_alumnos.php` suma columna/badge "Regular" y dos accesos
directos por fila (📄 Estudio / 📄 Notas) a `api/export_constancia.php`
— antes había que ir hasta Herramientas → Certificados para generar la
constancia de un alumno puntual.

**Asignación de materias — permisos y autoinscripción:**
`materia_add_docente`, `materia_remove_docente`, `materia_add_alumno`,
`materia_remove_alumno` y `cert_datos` (`api/ajax.php`) no verificaban
rol en absoluto — solo estaban "protegidas" porque las páginas que las
llaman (`modulo_materias.php`, `modulo_inscripciones.php`,
`modulo_herramientas.php`) están gateadas a admin/superadmin, pero
cualquier logueado podía llamarlas directo. Ahora exigen
admin/superadmin en el backend, no solo en el frontend.

Se suma **autoinscripción para alumnos "regulares"** (migración
`006_alumno_regular_autoinscripcion.sql`):
- `alumnos.regular` (0 por defecto) — el **superadmin** (no el admin)
  marca a un alumno como regular desde "Editar Alumno" en
  `modulo_alumnos.php`.
- Un alumno regular ve en su portal (`portal_alumno.php` → Mis
  Materias) las materias disponibles y puede inscribirse él mismo
  (`materia_autoinscribir`), sin depender de que el staff lo haga.
- Esa inscripción queda marcada (`materia_alumno.auto_inscrito=1`) y
  **no puede ser revocada por un admin ni por un profesor** —
  `materia_remove_alumno` ahora exige rol `superadmin` exacto para
  borrar una fila auto-inscrita; las asignadas por el staff siguen
  pudiendo quitarlas admin o superadmin, como antes. Tanto
  `modulo_inscripciones.php` como `modulo_materias.php` muestran un
  badge "Auto-inscrito" 🔒 en vez del botón "Quitar" cuando corresponde.

**Constancias de Estudio y de Notas — autoservicio para el alumno:**
`portal_alumno.php` enlazaba a `generar_constancia_estudio.php`, un
archivo que nunca existió. Se creó `api/export_constancia.php?tipo=estudio|notas`
con el mismo diseño oficial (membrete, párrafo legal, tabla de notas en
letras, firmas) que ya usaba el superadmin en la pestaña "Certificados"
de `modulo_herramientas.php` — pero como página propia, servida
directamente: un alumno la pide sin `alumno_id` (se resuelve solo su
propio registro) y un admin/superadmin puede seguir pidiéndola para
cualquiera con `&alumno_id=X`, igual que antes.

## WebSocket en vivo (`ws-server/`, opcional — necesita un VPS)

Todo lo de arriba (SSE, polling cada 5s) corre sobre HTTP normal
porque está pensado para un XAMPP/Apache compartido, sin un proceso
aparte. Con un VPS de por medio, se puede sumar un servidor de
WebSocket de verdad — **de forma aditiva**: si no está configurado, el
sistema sigue exactamente igual que antes (SSE + polling); nada se
rompe ni cambia de comportamiento por default.

- **`ws-server/`** — un proceso Node.js chico (sin dependencia de MySQL
  ni de las sesiones de PHP) que corre en el VPS. Ver
  `ws-server/README.md` para la instalación completa (systemd, nginx
  con `wss://`, variables de entorno) — es autocontenido y no requiere
  tocar nada más del VPS salvo abrir el proceso y el reverse proxy.
- **Autenticación sin compartir base de datos**: PHP firma un token
  (HMAC-SHA256, `config/ws_token.php`) con quién es el usuario y a qué
  canales tiene permiso de unirse — esos permisos ya se validaron con
  `materia_puede_ver()` de siempre, antes de firmar. El servidor Node
  solo verifica la firma; nunca vuelve a preguntarle nada a PHP ni a la
  BD por cada conexión.
- **PHP nunca depende de que el VPS esté vivo**: `config/ws_broadcast.php`
  le avisa a Node por un POST interno con timeout de 300ms — si el VPS
  está caído, lento o no configurado, el request de PHP sigue su curso
  normal (el dato real ya se guardó en MySQL antes de intentar avisar).
- **Activarlo**: definir `IBBS_WS_URL`, `IBBS_WS_INTERNAL_URL` e
  `IBBS_WS_SECRET` como variables de entorno del lado de PHP (mismo
  mecanismo que `IBBS_DB_HOST` etc.) — ver `ws-server/README.md` para
  el detalle completo. Sin esas tres, `ws_enabled()` da `false` y
  ningún código nuevo se activa.
- **Ya conectado**: foro/chat (`api/foro.php`, `modulo_aula.php`,
  `portal_alumno.php`, `portal_docente.php`) y la campana de
  notificaciones (`layout/foot.php`) — con WebSocket disponible, usan
  ese canal como principal (más rápido, no ocupa un worker de Apache
  por pestaña); sin él, seguimos con SSE/polling exactamente como
  antes. El polling de 5s del foro se deja además como red de
  seguridad aunque el WebSocket esté activo.
- Probado de punta a punta en este pase: servidor Node arriba, cliente
  conectándose y autenticándose con un token firmado igual que lo haría
  PHP, `/broadcast` entregando tanto a un canal de materia como
  directo a un usuario, y rechazo correcto de secreto/token inválidos.

## Alta de alumno nuevo (autoregistro + aprobación del administrador)

`login.php` ya tenía un registro público de 3 pasos (datos → preguntas
de seguridad → confirmar), pero el paso final tenía un bug serio: el
`INSERT` a `usuarios` traía **`rol='profesor'` fijo en el código**,
sin importar quién se registrara — cualquier visitante que se
registraba terminaba con una cuenta de **profesor**, nunca de alumno.
Se corrigió a `rol='alumno'` (que además ya era el `DEFAULT` de la
columna en la tabla) y ahora, al crear el usuario, también se crea su
ficha en `alumnos` (con los nuevos campos Nombre/Apellido del paso 1).

**Cambio de esta tanda — el autoregistro ya no entra "listo" solo:**
la cuenta y la clave las sigue creando el propio alumno (nadie del
staff tiene que hacerlo por él), pero ahora queda **pendiente de
aprobación** hasta que un admin/superadmin la revise:
- `usuarios.aprobado` (migración `009_alumno_aprobacion.sql`, default
  `1` para no afectar ninguna cuenta existente ni las que crea el
  staff) queda en `0` solo para el autoregistro de `login.php`, y
  `alumnos.regular` también nace en `0` (no puede autoinscribirse en
  materias hasta que lo aprueben).
- `login.php` (acción `login`) rechaza el inicio de sesión mientras
  `aprobado=0`, con un mensaje explicando que la cuenta está en
  revisión — no es un error, es la cuenta esperando al staff.
- Al completar el registro se crea una notificación `para_rol='admin'`
  con `tipo='solicitud_alumno'` y `referencia_id` = el `usuario_id`
  nuevo, y se empuja por WebSocket a todo admin/superadmin conectado
  (si hay VPS configurado) para que no dependan de refrescar la
  página.
- El panel de notificaciones (campanita, ver más abajo) le muestra a
  cualquier admin/superadmin dos botones **✓ Aceptar** / **✕
  Rechazar** directo sobre esa notificación — ninguno de los dos
  necesita ir a buscar al alumno en otro lado. `modulo_alumnos.php`
  también lista una columna **Solicitud** (Pendiente/Aprobado/
  Rechazado/Sin cuenta) con los mismos botones, para control manual
  de quién está adentro y quién no.
- **Aceptar** (`alumno_aprobar`): `usuarios.aprobado=1` +
  `alumnos.regular=1` (ya puede iniciar sesión y autoinscribirse) y le
  llega una notificación de que fue aprobado.
- **Rechazar** (`alumno_rechazar`): no se borra la cuenta ni la ficha
  (por si hay que revisar el caso después) — se pone
  `usuarios.activo=0`, que ya bloquea el login con el mensaje de
  "cuenta desactivada", y también se le notifica.

Flujo completo para un alumno nuevo:
1. Se registra en `login.php` (crea su propio usuario y clave) → queda
   con rol `alumno`, ficha en `alumnos` y **pendiente de aprobación**.
2. Un admin/superadmin lo acepta desde la campanita de notificaciones o
   desde `modulo_alumnos.php`.
3. Recién ahí puede iniciar sesión → `portal_alumno.php` lo lleva
   directo a **Mis Materias** en vez del dashboard vacío
   (`empty($materias)` fuerza esa vista al cargar) para que lo primero
   que vea sea el selector de materias disponibles.
4. Se inscribe él mismo (`materia_autoinscribir`) en cualquier materia
   activa que el superadmin/admin haya cargado — al confirmar, se le
   abre automáticamente su constancia de estudio
   (`api/export_constancia.php?tipo=estudio`).
5. Desde ahí, "Tareas y Asignaciones" (ya existente en el portal) muestra
   automáticamente las tareas que el profesor cargue para esa materia
   — no hizo falta un módulo nuevo: la consulta ya filtra por
   `materia_alumno.alumno_id`, así que en cuanto se inscribe empieza a
   ver contenido real.

**Resuelto — CSRF y permisos en Tareas y asignación docente:**
`crear_tarea.php`, `procesar_entrega.php`, `calificar_entrega.php` y
`asignar_materia.php` no mandaban ni exigían token CSRF, y tenían
huecos de permisos reales:
- `crear_tarea.php` dejaba a cualquier profesor publicar una tarea en
  **cualquier** materia, no solo en las suyas — ahora exige
  `materia_puede_gestionar()`.
- `procesar_entrega.php` no verificaba que el alumno estuviera inscrito
  en la materia de la tarea — ahora sí, antes de aceptar la entrega.
- `calificar_entrega.php` no verificaba que el profesor gestionara la
  materia de esa entrega — cualquier profesor podía calificar entregas
  ajenas. Ahora se resuelve la materia de la entrega y se valida.
- `asignar_materia.php` (asignar una materia a un docente) aceptaba el
  rol `profesor`/`docente` además de admin/superadmin — un profesor
  podía asignarse materias a sí mismo o a otros, salteándose a la
  administración por completo. Ahora exige admin/superadmin, que es lo
  único que la propia UI de `portal_docente.php` ya mostraba.

Los tres formularios afectados (`portal_alumno.php` → Tareas,
`portal_docente.php` → Calificar/Nueva Tarea/Asignar Materia) ahora
llevan `<input type="hidden" name="csrf_token">` con el token de la
sesión.

## Apertura de inscripción por materia (`materias.inscripcion_abierta`)

Migración `007_materia_inscripcion_abierta.sql`. Que una materia esté
`activo=1` y no `culminada` no significa que el superadmin quiera que
los alumnos se autoinscriban en ella — por eso la autoinscripción exige
además un interruptor aparte, exclusivo de admin/superadmin:
`modulo_materias.php` muestra un botón 🔓 Abierta / 🔒 Cerrada por cada
materia (acción `materia_toggle_inscripcion`). `materia_autoinscribir`
y la lista de "materias disponibles" del portal del alumno solo
muestran/permiten las que tienen `inscripcion_abierta=1` — asignar la
materia a mano desde el panel (`materia_add_alumno`) sigue funcionando
igual, sin depender de este interruptor. De paso, `materia_create`,
`materia_update`, `materia_set_estado` y `materia_delete` (que no
verificaban rol en absoluto) ahora exigen admin/superadmin también.

## Si el registro de alumnos "sigue entrando como profesor"

El bug de `login.php` (registro público creaba `rol='profesor'` fijo)
ya está corregido en el código — si después de actualizar seguís
viendo el problema, lo más probable es una de estas dos cosas, no un
bug nuevo:

1. **Estás entrando con una cuenta de prueba creada ANTES del fix.**
   Esa fila en `usuarios` ya quedó guardada con `rol='profesor'` y
   corregir el código no cambia datos ya existentes. Solución: registrate
   con un usuario/cédula/correo nuevo, o editá esa fila a mano (`UPDATE
   usuarios SET rol='alumno' WHERE usuario='...';`, o desde
   `modulo_usuarios.php` como superadmin).
2. **Tu base de datos es de una versión vieja de `ibbs.sql`** y la
   columna `usuarios.rol` es un `ENUM` que todavía no incluye
   `'alumno'` — en ese caso ningún registro público podría entrar bien
   como alumno sin importar el código PHP. La migración
   `008_asegurar_rol_alumno.sql` redefine ese `ENUM` con las 4 opciones
   que ya usa el resto del sistema.

Para no tener que aplicar los archivos uno por uno, `database/aplicar_todas_las_migraciones.sql`
junta las migraciones 001–008 en un solo archivo para pegar de una vez
en phpMyAdmin (pestaña SQL de la base `ibbs`) — es seguro correrlo
aunque ya hayas aplicado algunas antes.

## Alumno: Clases en Vivo y Grabadas — antes bloqueado, links rotos

Auditoría general de "qué le falta a cada módulo para funcionar" —
encontré tres enlaces que apuntaban a archivos que **nunca existieron**
(404 en producción, no solo en teoría):

- **`portal_docente.php` → "Salir"** apuntaba a `logout.php` — el
  script real siempre fue `cerrar_sesion.php`. Cualquier profesor/admin
  que usara ese botón se encontraba con un error en vez de cerrar
  sesión.
- **`portal_alumno.php` → "Ingresar al Aula Virtual"** apuntaba a
  `aula_virtual.php` — corregido a `modulo_aula.php` (el real).
- **`portal_alumno.php` → formulario de "Configuración de Perfil"**
  apuntaba a `actualizar_perfil.php`, que tampoco existía — un alumno
  no podía actualizar su perfil de ninguna forma. Se creó el archivo
  (análogo a `actualizar_perfil_docente.php` pero corrigiendo dos bugs
  más que tenía el formulario: mandaba `email`/`foto_perfil`, columnas
  que no existen en `alumnos` — son `correo`/`foto`) con CSRF, validación
  de tipo MIME real en la foto, y correo sin duplicar entre alumnos.

Además, **un alumno no podía entrar a Clases en Vivo ni a Clases
Grabadas de ninguna manera** — ambos módulos bloqueaban el rol
`alumno` directamente en la página (`in_array($_rol,[...])` sin
`'alumno'`), aunque el backend (`api/clases_vivo.php`,
`api/clases_grabadas.php`) ya estaba listo para servirle solo lectura
vía `materia_puede_ver()`. Se abrió el acceso en ambos módulos, se
extendió `materias_asignadas()` (usada por los tres selectores "elige
una materia") para incluir las materias del alumno vía
`materia_alumno`, y se agregaron accesos directos 🔴 En Vivo / 🎬
Grabadas en las tarjetas de "Mis Materias" de `portal_alumno.php` y en
`portal_docente.php`. Los botones de gestión (crear/editar/eliminar)
siguen ocultos para alumno — dependen de `can_manage`, que ya devuelve
`false` para ese rol.

**Deuda de diseño detectada, no tocada en este pase:** `api/tareas.php`
(usado por la pestaña "Tareas" de `modulo_aula.php`) y
`crear_tarea.php`/`procesar_entrega.php`/`calificar_entrega.php`
(usados por los portales) son dos implementaciones distintas sobre las
mismas tablas `tareas`/`entregas` — no hay pérdida de datos entre una y
otra, pero `api/tareas.php` es la más completa (valida el tipo MIME
real del archivo con `finfo`, no solo la extensión). Unificar ambas
portales sobre `api/tareas.php`, como ya se hizo con el foro, es buen
candidato para un próximo pase.

## Chat del Staff (administración ↔ docentes, sin materia)

`modulo_chat_general.php` + `api/chat_general.php` (migración
`010_chat_staff.sql`, tabla `chat_staff`). Es un foro/chat único,
global, para que admin/superadmin y profesores puedan hablar entre sí
(avisos de dirección, preguntas directas al staff) **sin depender de
estar dentro de una materia puntual** — el chat por materia
(`modulo_aula.php` → pestaña Foro, `api/foro.php`) sigue funcionando
exactamente igual que antes, sin cambios de comportamiento.

- Acceso: `superadmin`, `admin`, `profesor`. El rol `alumno` nunca lo
  ve — ni en el sidebar (`layout/head.php`) ni en el backend
  (`api/chat_general.php` corta con 403 lógico si el rol no matchea).
- Mismo diseño de moderación que el foro por materia: cualquiera borra
  su propio mensaje, admin/superadmin borran cualquiera.
- Accesible desde el sidebar (sección "Comunicación" → "Chat del
  Staff") para admin/superadmin, y desde una pestaña nueva dentro de
  `portal_docente.php` para profesores (no tienen sidebar de módulos
  sueltos, todo su portal es un solo archivo con vistas).
- Tiempo real: canal WebSocket `'staff'`, que ahora se suma
  automáticamente al token de **todo** usuario no-alumno
  (`ws_token_for_materias()` en `config/ws_token.php`, y el minteo de
  `layout/head.php`) — no hace falta tocar cada página para que reciba
  los mensajes en vivo; sin WebSocket configurado, cae a polling cada
  5s como el resto del chat.
- Escalabilidad: igual que el chat por materia, `get_mensajes` solo
  trae los últimos 500 mensajes (`ORDER BY fecha DESC LIMIT 500`,
  reordenados en PHP) en vez de la tabla entera, con un índice
  (`idx_fecha` / `idx_materia_fecha`, migración
  `011_indices_chat_escalabilidad.sql`) para que ese `ORDER BY` no
  tenga que barrer todas las filas antes de cortar el `LIMIT`.

**Bug corregido de paso:** la primera versión de `get_mensajes` en
`api/chat_general.php` traía los últimos 500 con `ORDER BY fecha ASC
LIMIT 500` — eso trae los 500 **más viejos**, no los más recientes; en
cuanto la tabla pasara de 500 filas el chat se hubiera quedado
congelado mostrando siempre el mismo historial antiguo y nunca los
mensajes nuevos. Se corrigió a `DESC LIMIT 500` + `array_reverse()` en
PHP (el mismo patrón que ya usaba `api/foro.php`, al que se le aplicó
el mismo límite por la misma razón de escalabilidad).

## Alta de docente nuevo: la crea el administrador, no el propio docente

A diferencia del alumno (que se autoregistra), un docente **nunca crea
su propia cuenta**. El flujo ahora es:

1. Admin/superadmin va a `modulo_docentes.php` → "Nuevo Docente" y,
   además de los datos de siempre, define el **usuario** y una
   **contraseña inicial** para esa persona (hay un botón "🎲 Generar
   contraseña" si no quiere inventar una).
2. `docente_create` (en `api/ajax.php`, ahora exige admin/superadmin —
   antes no verificaba rol en absoluto) valida la contraseña con la
   misma política que el resto del sistema
   (`config/password_policy.php` → `ibbs_validar_password()`), revisa
   que usuario/correo/cédula no estén repetidos, crea primero la fila
   en `usuarios` (`rol='profesor'`, `aprobado=1` — a un docente que
   crea el propio admin nunca hace falta aprobarlo) y recién si eso
   sale bien crea la fila en `docentes` enlazada por `usuario_id` (si
   falla, se revierte el `usuarios` ya insertado).
3. Al terminar, el modal muestra el usuario y la contraseña para que el
   admin se los entregue al docente por el canal que use normalmente
   (en persona, WhatsApp, etc.) — la contraseña no se guarda en
   ninguna otra parte ni se vuelve a mostrar después de cerrar ese
   modal.
4. El docente inicia sesión con esas credenciales y, desde su propio
   perfil (`portal_docente.php` → Configuración de Perfil → sección
   Contraseña, acción `perfil_pwd`), puede cambiarla cuando quiera.

De paso quedaron blindadas varias acciones que **no verificaban rol en
absoluto** (bastaba con estar logueado, con cualquier rol, para
llamarlas directo por `fetch`): `docente_create`, `docente_update`,
`docente_delete`, `docente_list`, `docente_get`, `alumno_create`,
`alumno_update`, `alumno_delete`, `alumno_list`, `alumno_get` — todas
ahora exigen `admin`/`superadmin` en el backend, no solo a nivel de
página. `docente_update` además ahora sincroniza `usuarios.activo`
cuando se activa/desactiva un docente (antes solo tocaba `docentes.activo`,
dejando la cuenta de login activa aunque el docente apareciera "inactivo").

`actualizar_perfil_docente.php` se eliminó: nunca funcionó (comparaba
`$_SESSION['rol']` contra `'docente'`, cuando el rol real siempre es
`'profesor'`, y usaba columnas `email`/`foto_perfil` que no existen —
son `correo`/`foto`). El formulario de perfil de `portal_docente.php`
se reescribió para usar las mismas acciones que ya funcionaban en el
resto del sistema (`perfil_update`, `perfil_pwd`, `api/upload_foto.php`).

## Notificaciones: panel para roles sin acceso a Herramientas

La campanita ya avisaba con un badge y un toast en vivo a cualquier
rol, pero al hacer click siempre mandaba a `modulo_herramientas.php` —
una página exclusiva de admin/superadmin. Para `profesor` y `alumno`
eso era un callejón sin salida (la propia página los redirigía de
vuelta a `index.php` sin mostrar nada). Ahora la campanita es distinta
según el rol (`layout/head.php` + `layout/foot.php`):
- **admin/superadmin**: sigue yendo directo a `modulo_herramientas.php`
  (ahí además están los botones Aceptar/Rechazar de solicitudes de
  alumnos, ver arriba).
- **profesor/alumno**: abre un panel desplegable propio (sin navegar a
  ningún lado) que lista sus notificaciones pendientes con "Marcar
  leída" — reusa las mismas acciones (`notif_list`, `notif_leer`) que
  ya existían, no hizo falta backend nuevo.

## Convenciones para módulos nuevos

Cada módulo del campus (aula, foro, tareas, clases grabadas/en vivo,
notificaciones...) sigue el mismo patrón para no chocar entre sí:

1. **Tablas**: prefijo por módulo (`aula_*`, `foro_*`, `tareas_*`,
   `clases_*`...) en un archivo nuevo dentro de `database/migrations/`
   (`CREATE TABLE IF NOT EXISTS`, mismo estilo que las tablas existentes:
   `ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci`,
   FOREIGN KEY con `ON DELETE CASCADE` hacia la materia/actividad dueña).
2. **Backend**: un archivo propio en `api/<modulo>.php` (no todo dentro de
   `api/ajax.php`) que empiece con:
   ```php
   require_once __DIR__.'/../config/bootstrap.php';
   // valida $_SESSION['loggedin'], csrf_require_post() en POST, permisos...
   ```
   Esto da automáticamente sesión segura, CSRF, freno de fuerza bruta,
   conexión a BD (`db()`) y auditoría (`log_audit()`).
3. **Frontend**: `modulo_<algo>.php` con
   `include __DIR__.'/layout/head.php';` al inicio y
   `include __DIR__.'/layout/foot.php';` al final. Para llamar al backend
   propio (no `api/ajax.php`) usá el tercer argumento de `ajax()`:
   ```js
   ajax('mi_accion', {datos}, 'api/mi_modulo.php');
   ```
   `ajax()` ya se encarga de mandar el token CSRF y mostrar errores.
4. **Permisos**: siempre verificar en el backend, nunca confiar solo en
   que el frontend oculte un botón. Si tu módulo cuelga de una materia
   (como aula y clases grabadas), usá directamente
   `materia_puede_gestionar($con,$uid,$rol,$materia_id)` /
   `materia_puede_ver(...)` / `materias_asignadas($con,$uid,$rol)` de
   `config/materia_permisos.php` (ya cargado por el bootstrap) — admin ve
   todo, profesor solo lo suyo, alumno —a futuro— solo lo que tiene
   inscrito. No dupliques esta lógica en cada módulo nuevo.
5. **Archivos subidos**: nunca confiar en la extensión sola — validar
   también el tipo MIME real (`finfo_file`), generar el nombre en el
   servidor (nunca usar el nombre del cliente como ruta), guardar bajo
   `uploads/<modulo>/` (ya protegida contra ejecución de scripts por
   `uploads/.htaccess`) y servir la descarga siempre a través de un
   endpoint PHP que revise permisos — nunca como link directo al archivo.
6. **Links externos pegados por el usuario** (video, videollamada, lo
   que sea): nunca decidir "es de tal dominio" por `str_contains()` — un
   texto como `meet.google.com.evil.com` o `fakeyoutube.com` contiene el
   nombre real como substring sin serlo. Usá `url_es_valida($url)` /
   `url_host_es($url, ['dominio.com'])` de `config/url_validacion.php`
   (ya cargado por el bootstrap), que comparan el host exacto. Y si el
   link se va a embeber en un `<iframe>`, nunca uses el `src` tal cual —
   reconstruí vos la URL de embed a partir de un id extraído (ver
   `clases_embed_url()` en `api/clases_grabadas.php`).
7. **Menú**: agregá el link en `layout/head.php` (`<ul class="sb-nav">`).
