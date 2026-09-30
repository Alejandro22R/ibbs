-- ═══════════════════════════════════════════════════════════════
-- IBBS — Migración: solicitudes de autoinscripción con comprobante de pago
-- ═══════════════════════════════════════════════════════════════
-- Cómo aplicar: pegar este archivo completo en phpMyAdmin → pestaña
-- SQL de la base `ibbs` (o `mysql -u root ibbs < 012_materia_solicitudes.sql`).
-- Es seguro volver a correrlo.
--
-- Hasta ahora `materia_autoinscribir` (api/ajax.php) inscribía al
-- alumno regular directo en `materia_alumno`, sin pasar por nadie. La
-- institución cobra por materia, así que ahora el alumno tiene que
-- adjuntar la captura del pago móvil/transferencia junto con su
-- solicitud, y un admin/superadmin la revisa (viendo la imagen) antes
-- de que quede inscrito de verdad — hasta entonces NO aparece en
-- `materia_alumno` (no tiene acceso al aula, notas, etc. de esa
-- materia todavía).
--
-- `comprobante` guarda la ruta relativa del archivo subido en
-- uploads/comprobantes/ (mismo patrón de nombre-de-archivo generado en
-- el servidor que ya usa api/upload_foto.php — nunca el nombre
-- original del cliente).
-- ═══════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `materia_solicitudes` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `alumno_id` INT(11) NOT NULL,
  `materia_id` INT(11) NOT NULL,
  `comprobante` VARCHAR(255) NOT NULL,
  `estado` ENUM('pendiente','aprobada','rechazada') NOT NULL DEFAULT 'pendiente',
  `revisado_por` INT(11) DEFAULT NULL,
  `fecha` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_revision` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_estado` (`estado`),
  KEY `idx_alumno` (`alumno_id`),
  KEY `idx_materia` (`materia_id`),
  CONSTRAINT `materia_solicitudes_ibfk_1` FOREIGN KEY (`alumno_id`) REFERENCES `alumnos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `materia_solicitudes_ibfk_2` FOREIGN KEY (`materia_id`) REFERENCES `materias` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
