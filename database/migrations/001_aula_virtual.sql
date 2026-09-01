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
