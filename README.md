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
│   ├── backup.php, upload_foto.php, export_*.php
├── assets/                                   # CSS, imágenes, librerías de terceros (Chart.js, SweetAlert2, boxicons, fuentes)
├── uploads/
│   ├── fotos/                                # Fotos de perfil subidas por los usuarios (no versionadas)
│   └── materiales/                           # Archivos del Aula Virtual subidos por docentes (no versionados)
└── database/
    ├── ibbs.sql                              # Dump del esquema de base de datos
    └── migrations/                           # Cambios incrementales de esquema, uno por módulo nuevo
        ├── 001_aula_virtual.sql
        ├── 002_clases_grabadas.sql
        └── 003_clases_vivo.sql
```

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
