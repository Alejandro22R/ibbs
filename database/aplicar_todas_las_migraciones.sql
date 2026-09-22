-- ═══════════════════════════════════════════════════════════════
-- IBBS — TODAS las migraciones en un solo archivo (001 a 008)
-- ═══════════════════════════════════════════════════════════════
-- Generado para pegar de una sola vez en phpMyAdmin → pestaña SQL
-- de la base 'ibbs'. Es seguro correrlo aunque ya hayas aplicado
-- algunas de estas migraciones antes — todas usan
-- CREATE/ADD/MODIFY ... IF NOT EXISTS o redefiniciones idempotentes,
-- así que repetirlas no rompe nada.
--
-- Requiere haber importado antes database/ibbs.sql (el esquema base).
-- ═══════════════════════════════════════════════════════════════

-- ───────────────────────────────────────────────────────────────
-- Archivo: database/migrations/001_aula_virtual.sql
-- ───────────────────────────────────────────────────────────────
-- ═══════════════════════════════════════════════════════════════
-- IBBS — Migración: Aula Virtual por materia
-- ═══════════════════════════════════════════════════════════════
-- Cómo aplicar: pegar este archivo completo en phpMyAdmin → pestaña
-- SQL de la base `ibbs` (o `mysql -u root ibbs < 001_aula_virtual.sql`).
-- Es seguro volver a correrlo: usa CREATE TABLE IF NOT EXISTS.
--
-- Convención de nombres para módulos nuevos del campus: prefijo por
-- módulo (aula_*, clases_grabadas, clases_vivo, notif_*... y luego
-- foro_*, tareas_* para lo que construya el resto del equipo) — evita
-- choques de nombres entre tablas de distintos módulos.
--
-- Tablas:
--   aula_anuncios      — muro de anuncios del docente por materia
--   aula_materiales     — archivos descargables por materia
--   aula_actividades    — actividades evaluables (no la nota final)
--   aula_calificaciones — nota de cada alumno por actividad
-- ═══════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `aula_anuncios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `materia_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL COMMENT 'quién publicó (docente/admin)',
  `titulo` varchar(150) NOT NULL,
  `contenido` text NOT NULL,
  `fijado` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'anuncios fijados aparecen primero',
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `materia_id` (`materia_id`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `aula_anuncios_ibfk_1` FOREIGN KEY (`materia_id`) REFERENCES `materias` (`id`) ON DELETE CASCADE,
  CONSTRAINT `aula_anuncios_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `aula_materiales` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `materia_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL COMMENT 'quién subió el archivo',
  `titulo` varchar(150) NOT NULL,
  `descripcion` varchar(500) DEFAULT NULL,
  `archivo` varchar(255) NOT NULL COMMENT 'ruta relativa dentro de uploads/materiales/, nombre generado (no confiar en el original)',
  `archivo_nombre` varchar(255) NOT NULL COMMENT 'nombre original del archivo, solo para mostrar/descargar',
  `archivo_tipo` varchar(10) NOT NULL COMMENT 'extensión validada en el servidor',
  `tamano_bytes` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `materia_id` (`materia_id`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `aula_materiales_ibfk_1` FOREIGN KEY (`materia_id`) REFERENCES `materias` (`id`) ON DELETE CASCADE,
  CONSTRAINT `aula_materiales_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `aula_actividades` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `materia_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL COMMENT 'docente/admin que la creó',
  `titulo` varchar(150) NOT NULL,
  `descripcion` varchar(500) DEFAULT NULL,
  `tipo` varchar(30) NOT NULL DEFAULT 'actividad' COMMENT 'actividad|examen|taller|proyecto (texto libre)',
  `nota_max` decimal(5,2) NOT NULL DEFAULT 20.00,
  `fecha` date DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `materia_id` (`materia_id`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `aula_actividades_ibfk_1` FOREIGN KEY (`materia_id`) REFERENCES `materias` (`id`) ON DELETE CASCADE,
  CONSTRAINT `aula_actividades_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `aula_calificaciones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `actividad_id` int(11) NOT NULL,
  `alumno_id` int(11) NOT NULL,
  `nota` decimal(5,2) DEFAULT NULL,
  `observacion` varchar(255) DEFAULT NULL,
  `calificado_por` int(11) DEFAULT NULL,
  `actualizado_en` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_actividad_alumno` (`actividad_id`,`alumno_id`),
  KEY `alumno_id` (`alumno_id`),
  KEY `calificado_por` (`calificado_por`),
  CONSTRAINT `aula_calificaciones_ibfk_1` FOREIGN KEY (`actividad_id`) REFERENCES `aula_actividades` (`id`) ON DELETE CASCADE,
  CONSTRAINT `aula_calificaciones_ibfk_2` FOREIGN KEY (`alumno_id`) REFERENCES `alumnos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `aula_calificaciones_ibfk_3` FOREIGN KEY (`calificado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ───────────────────────────────────────────────────────────────
-- Archivo: database/migrations/002_clases_grabadas.sql
-- ───────────────────────────────────────────────────────────────
-- ═══════════════════════════════════════════════════════════════
-- IBBS — Migración: Clases Grabadas (repositorio de videos)
-- ═══════════════════════════════════════════════════════════════
-- Cómo aplicar: pegar este archivo completo en phpMyAdmin → pestaña
-- SQL de la base `ibbs` (o `mysql -u root ibbs < 002_clases_grabadas.sql`).
-- Es seguro volver a correrlo: usa CREATE TABLE IF NOT EXISTS.
--
-- No guarda video: solo el link (YouTube, Google Drive o Vimeo) y sus
-- datos. El sistema detecta la plataforma y arma un embed seguro —
-- ver api/clases_grabadas.php → clases_embed_url().
-- ═══════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `clases_grabadas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `materia_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL COMMENT 'quién la publicó (docente/admin)',
  `titulo` varchar(150) NOT NULL,
  `descripcion` varchar(500) DEFAULT NULL,
  `url` varchar(500) NOT NULL COMMENT 'link original tal como lo pegó el docente',
  `plataforma` varchar(20) NOT NULL DEFAULT 'otro' COMMENT 'youtube|drive|vimeo|otro — detectado del link al guardar',
  `fecha` date DEFAULT NULL COMMENT 'fecha de la clase (no de la publicación)',
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `materia_id` (`materia_id`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `clases_grabadas_ibfk_1` FOREIGN KEY (`materia_id`) REFERENCES `materias` (`id`) ON DELETE CASCADE,
  CONSTRAINT `clases_grabadas_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ───────────────────────────────────────────────────────────────
-- Archivo: database/migrations/003_clases_vivo.sql
-- ───────────────────────────────────────────────────────────────
-- ═══════════════════════════════════════════════════════════════
-- IBBS — Migración: Clases en Vivo (videollamadas)
-- ═══════════════════════════════════════════════════════════════
-- Cómo aplicar: pegar este archivo completo en phpMyAdmin → pestaña
-- SQL de la base `ibbs` (o `mysql -u root ibbs < 003_clases_vivo.sql`).
-- Es seguro volver a correrlo: usa CREATE TABLE IF NOT EXISTS.
--
-- Dos formas de sala:
--   · jitsi — el propio sistema genera un nombre de sala aleatorio e
--     inadivinable (columna `sala`) y arma el link https://meet.jit.si/…
--     No requiere cuenta ni servidor de video propio.
--   · meet/otro — el docente pega un link creado por fuera (Google
--     Meet, Zoom, etc.) en la columna `url`.
-- Ver api/clases_vivo.php → vivo_join_url() / vivo_generar_sala().
-- ═══════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `clases_vivo` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `materia_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL COMMENT 'quién la creó/dicta',
  `titulo` varchar(150) NOT NULL,
  `descripcion` varchar(500) DEFAULT NULL,
  `plataforma` varchar(20) NOT NULL DEFAULT 'jitsi' COMMENT 'jitsi|meet|otro',
  `sala` varchar(150) DEFAULT NULL COMMENT 'nombre de sala generado por el sistema — solo si plataforma=jitsi',
  `url` varchar(500) DEFAULT NULL COMMENT 'link pegado por el docente — solo si plataforma=meet|otro',
  `fecha_hora` datetime NOT NULL COMMENT 'cuándo es (o fue) la clase',
  `estado` varchar(20) NOT NULL DEFAULT 'programada' COMMENT 'programada|en_curso|finalizada|cancelada',
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `materia_id` (`materia_id`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `clases_vivo_ibfk_1` FOREIGN KEY (`materia_id`) REFERENCES `materias` (`id`) ON DELETE CASCADE,
  CONSTRAINT `clases_vivo_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ───────────────────────────────────────────────────────────────
-- Archivo: database/migrations/004_notificaciones_tiempo_real.sql
-- ───────────────────────────────────────────────────────────────
-- ═══════════════════════════════════════════════════════════════
-- IBBS — Migración: Notificaciones en tiempo real
-- ═══════════════════════════════════════════════════════════════
-- Cómo aplicar: pegar este archivo completo en phpMyAdmin → pestaña
-- SQL de la base `ibbs` (o `mysql -u root ibbs < 004_notificaciones_tiempo_real.sql`).
-- Es seguro volver a correrlo.
--
-- La tabla `notificaciones` ya existía (se usaba en modo "pull": el
-- usuario las veía recién al entrar). Esta migración la extiende para
-- que sirva también de cola para el push en tiempo real
-- (api/notificaciones_stream.php), sin romper lo que ya la usaba:
--
--   · `tipo` pasa de ENUM fijo a VARCHAR — cada módulo nuevo puede
--     tener su propio tipo (anuncio, foro, tarea, calificacion,
--     clase_vivo, grabacion...) sin otra migración por cada uno.
--   · `materia_id` (nuevo, opcional) — para poder armar un link
--     "ver" que lleve directo a la materia desde la notificación.
--   · índice para que el sondeo del stream (cada ~1s, por usuario)
--     sea barato.
-- ═══════════════════════════════════════════════════════════════

ALTER TABLE `notificaciones`
  MODIFY `tipo` VARCHAR(30) NOT NULL DEFAULT 'info';

ALTER TABLE `notificaciones`
  ADD COLUMN IF NOT EXISTS `materia_id` INT(11) DEFAULT NULL AFTER `usuario_id`;

ALTER TABLE `notificaciones`
  ADD INDEX IF NOT EXISTS `idx_stream` (`usuario_id`,`leida`,`id`);

-- ───────────────────────────────────────────────────────────────
-- Archivo: database/migrations/005_foro_usuario_id.sql
-- ───────────────────────────────────────────────────────────────
-- ═══════════════════════════════════════════════════════════════
-- IBBS — Migración: autoría real de mensajes del foro
-- ═══════════════════════════════════════════════════════════════
-- Cómo aplicar: pegar este archivo completo en phpMyAdmin → pestaña
-- SQL de la base `ibbs` (o `mysql -u root ibbs < 005_foro_usuario_id.sql`).
-- Es seguro volver a correrlo.
--
-- `foro_mensajes` solo guardaba `usuario_nombre` (texto libre) y `rol`
-- como snapshot al momento de publicar — suficiente para mostrar el
-- mensaje, pero no permite saber con certeza QUIÉN lo escribió (dos
-- personas con el mismo nombre, o alguien que después se renombra).
-- Sin eso no se puede permitir de forma segura "borrar mi propio
-- mensaje" ni moderación por materia.
--
-- `usuario_id` (nuevo, opcional) referencia a `usuarios.id`. Se deja
-- NULL en mensajes históricos — siguen mostrándose igual, solo que no
-- se pueden borrar por autoría (sí por moderación de quien gestiona la
-- materia). Todo mensaje nuevo lo completa `api/foro.php`.
-- ═══════════════════════════════════════════════════════════════

ALTER TABLE `foro_mensajes`
  ADD COLUMN IF NOT EXISTS `usuario_id` INT(11) DEFAULT NULL AFTER `materia_id`;

ALTER TABLE `foro_mensajes`
  ADD INDEX IF NOT EXISTS `idx_materia_usuario` (`materia_id`,`usuario_id`);

-- ───────────────────────────────────────────────────────────────
-- Archivo: database/migrations/006_alumno_regular_autoinscripcion.sql
-- ───────────────────────────────────────────────────────────────
-- ═══════════════════════════════════════════════════════════════
-- IBBS — Migración: alumno "regular" + autoinscripción
-- ═══════════════════════════════════════════════════════════════
-- Cómo aplicar: pegar este archivo completo en phpMyAdmin → pestaña
-- SQL de la base `ibbs` (o `mysql -u root ibbs < 006_alumno_regular_autoinscripcion.sql`).
-- Es seguro volver a correrlo.
--
-- `alumnos.regular` (nuevo, 0 por defecto): solo el superadmin puede
-- marcarlo (ver alumno_update en api/ajax.php). Un alumno "regular"
-- puede inscribirse él mismo en materias desde su portal, sin esperar
-- a que un admin lo haga por él.
--
-- `materia_alumno.auto_inscrito` (nuevo, 0 por defecto): marca las
-- inscripciones que hizo el propio alumno (vs. las que cargó un
-- admin/superadmin). Una vez auto-inscrito, la fila solo puede
-- borrarla el superadmin (ver materia_remove_alumno) — ni admin ni
-- profesor pueden revocarla.
-- ═══════════════════════════════════════════════════════════════

ALTER TABLE `alumnos`
  ADD COLUMN IF NOT EXISTS `regular` TINYINT(1) NOT NULL DEFAULT 0 AFTER `activo`;

ALTER TABLE `materia_alumno`
  ADD COLUMN IF NOT EXISTS `auto_inscrito` TINYINT(1) NOT NULL DEFAULT 0 AFTER `alumno_id`;

-- ───────────────────────────────────────────────────────────────
-- Archivo: database/migrations/007_materia_inscripcion_abierta.sql
-- ───────────────────────────────────────────────────────────────
-- ═══════════════════════════════════════════════════════════════
-- IBBS — Migración: apertura de inscripción por materia
-- ═══════════════════════════════════════════════════════════════
-- Cómo aplicar: pegar este archivo completo en phpMyAdmin → pestaña
-- SQL de la base `ibbs` (o `mysql -u root ibbs < 007_materia_inscripcion_abierta.sql`).
-- Es seguro volver a correrlo.
--
-- `materias.inscripcion_abierta` (nuevo, 0 por defecto): que una
-- materia esté activa y en curso no significa que el superadmin quiera
-- que los alumnos se autoinscriban en ella — esto es un interruptor
-- aparte que solo admin/superadmin puede prender/apagar
-- (materia_toggle_inscripcion en api/ajax.php). La autoinscripción
-- (materia_autoinscribir) y el listado de "materias disponibles" del
-- portal del alumno exigen que esté en 1, además de que el alumno sea
-- "regular" (ver 006_alumno_regular_autoinscripcion.sql). Asignar la
-- materia a mano desde el panel de administración (materia_add_alumno)
-- sigue funcionando igual, sin depender de este interruptor.
-- ═══════════════════════════════════════════════════════════════

ALTER TABLE `materias`
  ADD COLUMN IF NOT EXISTS `inscripcion_abierta` TINYINT(1) NOT NULL DEFAULT 0 AFTER `estado`;

-- ───────────────────────────────────────────────────────────────
-- Archivo: database/migrations/008_asegurar_rol_alumno.sql
-- ───────────────────────────────────────────────────────────────
-- ═══════════════════════════════════════════════════════════════
-- IBBS — Migración: asegurar que 'alumno' exista como rol válido
-- ═══════════════════════════════════════════════════════════════
-- Cómo aplicar: pegar este archivo completo en phpMyAdmin → pestaña
-- SQL de la base `ibbs` (o `mysql -u root ibbs < 008_asegurar_rol_alumno.sql`).
-- Es seguro volver a correrlo.
--
-- Si tu base de datos se creó a partir de una versión vieja de
-- database/ibbs.sql, la columna `usuarios.rol` puede ser un ENUM que
-- todavía no incluya 'alumno' — en ese caso, cualquier INSERT con
-- rol='alumno' fallaría (o, en modo no estricto, guardaría un valor
-- vacío en vez de 'alumno'), y el registro público de alumnos nunca
-- funcionaría bien sin importar qué diga el código PHP.
--
-- Este ALTER redefine el ENUM completo (con las 4 opciones que ya usa
-- el resto del sistema) y deja 'alumno' como valor por defecto.
-- Redefinirlo con las mismas opciones no rompe las cuentas existentes.
-- ═══════════════════════════════════════════════════════════════

ALTER TABLE `usuarios`
  MODIFY `rol` ENUM('superadmin','admin','profesor','alumno') DEFAULT 'alumno';

