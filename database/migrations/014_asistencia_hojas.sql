-- ═══════════════════════════════════════════════════════════════
-- IBBS — Migración: hojas de asistencia escaneadas (OCR)
-- ═══════════════════════════════════════════════════════════════
-- Cómo aplicar: pegar este archivo completo en phpMyAdmin → pestaña
-- SQL de la base `ibbs` (o `mysql -u root ibbs < 014_asistencia_hojas.sql`).
-- Es seguro volver a correrlo. También se autoaplica solo — ver
-- config/schema_autoheal.php — así que en la práctica no hace falta
-- pegarla a mano.
--
-- `asistencia_hojas` guarda, por cada foto de la hoja de asistencia
-- física que un docente/admin sube y procesa con OCR (Tesseract.js,
-- corre en el navegador — ver api/asistencia_ocr.php y la pestaña
-- "Registro por Foto" de modulo_asistencias.php), la imagen original
-- (evidencia, por si hay que revisar un reclamo) y el texto crudo que
-- el OCR leyó (auditoría — para entender por qué el sistema marcó lo
-- que marcó). El OCR es una AYUDA para precargar la tabla de
-- asistencia, nunca la fuente de verdad final: quien sube la foto
-- siempre revisa y corrige la tabla antes de guardar, y lo que
-- realmente se guarda en `asistencias` es esa tabla ya confirmada.
-- ═══════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `asistencia_hojas` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `materia_id` INT(11) NOT NULL,
  `fecha` DATE NOT NULL,
  `foto` VARCHAR(255) NOT NULL,
  `texto_ocr` MEDIUMTEXT DEFAULT NULL,
  `modo` ENUM('asistieron','faltaron') NOT NULL DEFAULT 'asistieron',
  `total_alumnos` INT(11) NOT NULL DEFAULT 0,
  `total_detectados` INT(11) NOT NULL DEFAULT 0,
  `cargado_por` INT(11) DEFAULT NULL,
  `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_materia_fecha` (`materia_id`,`fecha`),
  CONSTRAINT `asistencia_hojas_ibfk_1` FOREIGN KEY (`materia_id`) REFERENCES `materias` (`id`) ON DELETE CASCADE,
  CONSTRAINT `asistencia_hojas_ibfk_2` FOREIGN KEY (`cargado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `asistencias`
  ADD COLUMN IF NOT EXISTS `hoja_id` INT(11) DEFAULT NULL AFTER `registrado_por`;
