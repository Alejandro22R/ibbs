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
