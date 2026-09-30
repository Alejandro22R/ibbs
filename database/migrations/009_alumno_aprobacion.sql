-- ═══════════════════════════════════════════════════════════════
-- IBBS — Migración: aprobación de alumnos autoregistrados
-- ═══════════════════════════════════════════════════════════════
-- Cómo aplicar: pegar este archivo completo en phpMyAdmin → pestaña
-- SQL de la base `ibbs` (o `mysql -u root ibbs < 009_alumno_aprobacion.sql`).
-- Es seguro volver a correrlo.
--
-- `usuarios.aprobado` (nuevo, 1 por defecto — así ninguna cuenta ya
-- existente ni ninguna creada por el staff queda bloqueada). Solo el
-- autoregistro público de alumnos (login.php → reg_finish) lo pone en
-- 0 explícitamente: esa cuenta no puede iniciar sesión hasta que un
-- superadmin/admin la apruebe (acción alumno_aprobar en api/ajax.php).
--
-- `notificaciones.referencia_id` (nuevo, opcional): para que una
-- notificación de "solicitud de ingreso" pueda cargar el botón
-- Aceptar/Rechazar sabiendo a qué usuario_id se refiere, sin tener que
-- adivinarlo del texto del título (como se hacía antes con
-- 'reprobado'/'asistencia', ver notif_generar en api/ajax.php).
-- ═══════════════════════════════════════════════════════════════

ALTER TABLE `usuarios`
  ADD COLUMN IF NOT EXISTS `aprobado` TINYINT(1) NOT NULL DEFAULT 1 AFTER `activo`;

ALTER TABLE `notificaciones`
  ADD COLUMN IF NOT EXISTS `referencia_id` INT(11) DEFAULT NULL AFTER `materia_id`;
