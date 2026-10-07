-- ═══════════════════════════════════════════════════════════════
-- IBBS — Migración: secciones/semanas del Aula Virtual (estilo Moodle)
-- ═══════════════════════════════════════════════════════════════
-- Cómo aplicar: pegar este archivo completo en phpMyAdmin → pestaña
-- SQL de la base `ibbs` (o `mysql -u root ibbs < 018_aula_secciones.sql`).
-- Es seguro volver a correrlo. También se autoaplica solo — ver
-- config/schema_autoheal.php — así que en la práctica no hace falta
-- pegarla a mano.
--
-- `aula_secciones`: el docente agrupa anuncios/materiales/actividades
-- en secciones tipo "Semana 1", "Unidad 2", igual que los "temas" de
-- Moodle. `orden` controla el orden en pantalla; `visible` permite
-- ocultarle una sección entera al alumno sin borrarla.
--
-- `seccion_id` (nullable) en `aula_anuncios`/`aula_materiales`/
-- `aula_actividades`: contenido sin sección cae en un bucket "General"
-- implícito en la UI. Sin FK formal (para poder re-correr este archivo
-- sin error de "constraint ya existe") — api/aula.php pone `seccion_id`
-- en NULL para todo el contenido de una sección antes de borrarla, así
-- que borrar una sección nunca borra su contenido, solo lo desagrupa.
-- ═══════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `aula_secciones` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `materia_id` INT(11) NOT NULL,
    `titulo` VARCHAR(150) NOT NULL,
    `descripcion` VARCHAR(500) DEFAULT NULL,
    `orden` INT(11) NOT NULL DEFAULT 0,
    `visible` TINYINT(1) NOT NULL DEFAULT 1,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_materia_orden` (`materia_id`,`orden`),
    CONSTRAINT `fk_asec_materia` FOREIGN KEY (`materia_id`) REFERENCES `materias` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `aula_anuncios`
  ADD COLUMN IF NOT EXISTS `seccion_id` INT(11) DEFAULT NULL AFTER `materia_id`,
  ADD INDEX IF NOT EXISTS `idx_seccion` (`seccion_id`);

ALTER TABLE `aula_materiales`
  ADD COLUMN IF NOT EXISTS `seccion_id` INT(11) DEFAULT NULL AFTER `materia_id`,
  ADD INDEX IF NOT EXISTS `idx_seccion` (`seccion_id`);

ALTER TABLE `aula_actividades`
  ADD COLUMN IF NOT EXISTS `seccion_id` INT(11) DEFAULT NULL AFTER `materia_id`,
  ADD INDEX IF NOT EXISTS `idx_seccion` (`seccion_id`);
