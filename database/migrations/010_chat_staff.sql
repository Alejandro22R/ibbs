-- ═══════════════════════════════════════════════════════════════
-- IBBS — Migración: chat general entre administración y docentes
-- ═══════════════════════════════════════════════════════════════
-- Cómo aplicar: pegar este archivo completo en phpMyAdmin → pestaña
-- SQL de la base `ibbs` (o `mysql -u root ibbs < 010_chat_staff.sql`).
-- Es seguro volver a correrlo.
--
-- `chat_staff` es un foro sin materia — para que admin/superadmin y
-- profesores puedan hablar entre sí (avisos de dirección, preguntas al
-- staff) sin tener que entrar a una materia puntual. Mismo diseño que
-- `foro_mensajes` (ver 005_foro_usuario_id.sql), pero sin materia_id.
-- Nunca lo usa el rol alumno — eso lo exige api/chat_general.php.
-- ═══════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `chat_staff` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` INT(11) DEFAULT NULL,
  `usuario_nombre` VARCHAR(100) NOT NULL,
  `rol` VARCHAR(20) NOT NULL DEFAULT 'profesor',
  `mensaje` TEXT NOT NULL,
  `respuesta_a` INT(11) DEFAULT NULL,
  `fecha` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_usuario` (`usuario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
