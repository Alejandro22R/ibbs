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
