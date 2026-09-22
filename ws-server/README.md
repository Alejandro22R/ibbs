# IBBS — Servidor de WebSocket

Proceso Node.js aparte, pensado para correr en el VPS (no en el mismo
Apache/XAMPP donde vive el PHP). Da tiempo real de verdad al foro/chat
y a las notificaciones — sin este proceso corriendo, el sistema entero
sigue funcionando exactamente igual que siempre (SSE + polling, ver
`config/ws_config.php` del lado PHP). Esto es una mejora opcional, no
un reemplazo obligatorio.

## Qué hace (y qué NO hace)

- **No** toca MySQL ni las sesiones de PHP. No sabe nada de alumnos,
  materias ni notas.
- Solo sabe: "este socket se autenticó con un token válido, pertenece
  al usuario X, y tiene permiso de estar en estos canales" — esa lista
  de canales la decidió PHP de antemano (ya validó permisos reales con
  `materia_puede_ver()`), firmándola en el token.
- Cuando PHP guarda algo importante (un mensaje de foro, una
  notificación), además de guardarlo en MySQL como siempre, le avisa a
  este proceso por un POST interno (`/broadcast`) para que lo reenvíe
  a quien esté conectado — así el navegador no tiene que esperar el
  próximo sondeo.

## Instalación en el VPS

Requiere Node.js 18+ (`node -v` para comprobar).

```bash
cd ws-server
npm install
cp .env.example .env
nano .env   # completar WS_SECRET (openssl rand -hex 32) y WS_PORT
node server.js   # prueba manual — Ctrl+C para parar
```

Si ves `[ibbs-ws] escuchando en :8081 (...)`, está funcionando. Probá
desde el mismo VPS:

```bash
curl http://127.0.0.1:8081/health
# {"ok":true,"canales":0,"usuarios":0}
```

### Dejarlo corriendo siempre (systemd)

Ver `ibbs-ws.service.example` — instrucciones de instalación adentro
del propio archivo. Después de instalarlo:

```bash
sudo systemctl status ibbs-ws     # tiene que decir "active (running)"
journalctl -u ibbs-ws -f          # logs en vivo
```

### Exponerlo con HTTPS (wss://) detrás de nginx

El proceso Node solo debe escuchar en `127.0.0.1` — nunca exponerlo
directo a Internet sin TLS. Ver `nginx-ws.conf.example` para el
`location /ws { ... }` que hay que agregar a tu `server{}` de nginx (o
crear un subdominio dedicado, ej. `ws.tudominio.com`, con su propio
certificado de Let's Encrypt).

## Conectar el lado PHP

En el servidor donde corre Apache/PHP (puede ser el mismo VPS u otro),
definí estas variables de entorno — mismo mecanismo que ya usa
`IBBS_DB_HOST` etc. (ver README.md de la raíz del proyecto, sección
"Configuración de la base de datos"). Por ejemplo, en el
`VirtualHost` de Apache:

```apache
SetEnv IBBS_WS_URL "wss://ws.tudominio.com/ws"
SetEnv IBBS_WS_INTERNAL_URL "http://127.0.0.1:8081"
SetEnv IBBS_WS_SECRET "el-mismo-valor-que-WS_SECRET-en-ws-server/.env"
```

O en `.htaccess` / `php.ini` con `putenv()`, lo que ya uses para las
variables de BD. **`IBBS_WS_SECRET` tiene que ser idéntico** al
`WS_SECRET` del `.env` de `ws-server/` — son el mismo secreto
compartido entre los dos procesos.

Si `IBBS_WS_URL` o `IBBS_WS_SECRET` no están definidas, `ws_enabled()`
devuelve `false` y el sistema no intenta usar WebSocket para nada — se
queda con SSE/polling, como en un XAMPP normal. Podés activarlo cuando
quieras sin tocar el resto del código.

## Verificar que quedó conectado de punta a punta

1. Con el VPS + nginx + systemd andando y las variables de entorno de
   PHP puestas, abrí el foro de una materia (`modulo_aula.php` →
   pestaña Foro, o el chat de `portal_alumno.php`/`portal_docente.php`).
2. Abrí la consola del navegador (F12) — no debería haber errores de
   WebSocket. `IbbsRT.connected` en la consola tiene que dar `true`.
3. Mandá un mensaje desde otra pestaña/usuario — tiene que aparecer
   casi al instante, sin esperar el refresco de 5s del polling.
4. `journalctl -u ibbs-ws -f` en el VPS debería mostrar la conexión
   entrando y los `/broadcast` llegando cuando alguien escribe.

## Escalar más adelante

Este servidor guarda el estado (quién está en qué canal) en memoria de
un solo proceso — para un instituto no hace falta más. Si algún día
hace falta correr varias instancias detrás de un balanceador, hay que
sumar un backend compartido (Redis pub/sub) para que los `/broadcast`
lleguen a todas las instancias; no hace falta hoy.
