-- ═══════════════════════════════════════════════════════════════
-- IBBS — Migración: tablas base de Tareas, Entregas y Foro
-- ═══════════════════════════════════════════════════════════════
-- Cómo aplicar: pegar este archivo completo en phpMyAdmin → pestaña
-- SQL de la base `ibbs` (o `mysql -u root ibbs < 019_tareas_entregas_foro_base.sql`).
-- Es seguro volver a correrlo. También se autoaplica solo — ver
-- config/schema_autoheal.php — así que en la práctica no hace falta
-- pegarla a mano.
--
-- `tareas`, `entregas` y `foro_mensajes` son de las funciones más
-- viejas del campus (anteriores al sistema de migraciones): alguien
-- las creó a mano hace tiempo directo en la base de datos de
-- producción, y nunca quedaron registradas en ningún .sql del
-- repositorio ni en el autoheal. Un entorno nuevo (clonar el repo +
-- base de datos vacía) se queda sin estas tres tablas, y api/tareas.php
-- y el Foro truenan con una pantalla en blanco (error fatal de PHP sin
-- mensaje, porque display_errors está apagado) apenas alguien intenta
-- listar o crear una tarea, o cargar el foro.
-- ═══════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `tareas` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `materia_id` INT(11) NOT NULL,
    `titulo` VARCHAR(200) NOT NULL,
    `descripcion` TEXT DEFAULT NULL,
    `archivo` VARCHAR(255) DEFAULT NULL,
    `fecha_limite` DATETIME NOT NULL,
    `nota_maxima` DECIMAL(5,2) DEFAULT 20.00,
    `creado_en` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_materia` (`materia_id`),
    CONSTRAINT `tareas_ibfk_1` FOREIGN KEY (`materia_id`) REFERENCES `materias` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `entregas` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `tarea_id` INT(11) NOT NULL,
    `alumno_id` INT(11) NOT NULL,
    `texto_respuesta` TEXT DEFAULT NULL,
    `archivo` VARCHAR(255) DEFAULT NULL,
    `fecha_entrega` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `nota` DECIMAL(5,2) DEFAULT NULL,
    `observacion_docente` TEXT DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unica_entrega` (`tarea_id`,`alumno_id`),
    KEY `idx_alumno` (`alumno_id`),
    CONSTRAINT `entregas_ibfk_1` FOREIGN KEY (`tarea_id`) REFERENCES `tareas` (`id`) ON DELETE CASCADE,
    CONSTRAINT `entregas_ibfk_2` FOREIGN KEY (`alumno_id`) REFERENCES `alumnos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `foro_mensajes` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `materia_id` INT(11) NOT NULL,
    `usuario_nombre` VARCHAR(100) NOT NULL,
    `rol` VARCHAR(20) NOT NULL DEFAULT 'alumno',
    `mensaje` TEXT NOT NULL,
    `respuesta_a` INT(11) DEFAULT NULL,
    `fecha` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_respuesta_a` (`respuesta_a`),
    CONSTRAINT `foro_mensajes_ibfk_1` FOREIGN KEY (`respuesta_a`) REFERENCES `foro_mensajes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
