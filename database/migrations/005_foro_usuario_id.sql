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
