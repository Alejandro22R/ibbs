-- ═══════════════════════════════════════════════════════════════
-- IBBS — Migración: Clases en Vivo (videollamadas)
-- ═══════════════════════════════════════════════════════════════
-- Cómo aplicar: pegar este archivo completo en phpMyAdmin → pestaña
-- SQL de la base `ibbs` (o `mysql -u root ibbs < 003_clases_vivo.sql`).
-- Es seguro volver a correrlo: usa CREATE TABLE IF NOT EXISTS.
--
-- Dos formas de sala:
--   · jitsi — el propio sistema genera un nombre de sala aleatorio e
--     inadivinable (columna `sala`) y arma el link https://meet.jit.si/…
--     No requiere cuenta ni servidor de video propio.
--   · meet/otro — el docente pega un link creado por fuera (Google
--     Meet, Zoom, etc.) en la columna `url`.
-- Ver api/clases_vivo.php → vivo_join_url() / vivo_generar_sala().
-- ═══════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `clases_vivo` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `materia_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL COMMENT 'quién la creó/dicta',
  `titulo` varchar(150) NOT NULL,
  `descripcion` varchar(500) DEFAULT NULL,
  `plataforma` varchar(20) NOT NULL DEFAULT 'jitsi' COMMENT 'jitsi|meet|otro',
  `sala` varchar(150) DEFAULT NULL COMMENT 'nombre de sala generado por el sistema — solo si plataforma=jitsi',
  `url` varchar(500) DEFAULT NULL COMMENT 'link pegado por el docente — solo si plataforma=meet|otro',
  `fecha_hora` datetime NOT NULL COMMENT 'cuándo es (o fue) la clase',
  `estado` varchar(20) NOT NULL DEFAULT 'programada' COMMENT 'programada|en_curso|finalizada|cancelada',
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `materia_id` (`materia_id`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `clases_vivo_ibfk_1` FOREIGN KEY (`materia_id`) REFERENCES `materias` (`id`) ON DELETE CASCADE,
  CONSTRAINT `clases_vivo_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
