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
