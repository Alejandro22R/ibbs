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
        └── 004_notificaciones_tiempo_real.sql
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
`nota_actualizada_en`, que ya existían pero no se mostraban), y se
arregló el enlace roto "Constancia de Notas" de `portal_alumno.php`
(apuntaba a un archivo inexistente) para que use
`api/export_boletin.php`, ahora restringido a que un alumno solo pueda
descargar la suya propia.

**Pendiente, no corregido en este pase**: `crear_tarea.php`,
`procesar_entrega.php`, `calificar_entrega.php` y
`asignar_materia.php` (formularios de `portal_alumno.php` /
`portal_docente.php` fuera del foro) todavía no mandan ni exigen token
CSRF. Antes de llevar el campus a producción real conviene sumarles
`csrf_require_post()` en el backend y el token en sus fetch/formularios,
siguiendo el mismo patrón que ya usa el resto del sistema (y el que
acaba de sumar el foro).

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
