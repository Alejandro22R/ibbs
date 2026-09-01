-- ═══════════════════════════════════════════════════════════════
-- IBBS — Migración: Clases Grabadas (repositorio de videos)
-- ═══════════════════════════════════════════════════════════════
-- Cómo aplicar: pegar este archivo completo en phpMyAdmin → pestaña
-- SQL de la base `ibbs` (o `mysql -u root ibbs < 002_clases_grabadas.sql`).
-- Es seguro volver a correrlo: usa CREATE TABLE IF NOT EXISTS.
--
-- No guarda video: solo el link (YouTube, Google Drive o Vimeo) y sus
-- datos. El sistema detecta la plataforma y arma un embed seguro —
-- ver api/clases_grabadas.php → clases_embed_url().
-- ═══════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `clases_grabadas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `materia_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL COMMENT 'quién la publicó (docente/admin)',
  `titulo` varchar(150) NOT NULL,
  `descripcion` varchar(500) DEFAULT NULL,
  `url` varchar(500) NOT NULL COMMENT 'link original tal como lo pegó el docente',
  `plataforma` varchar(20) NOT NULL DEFAULT 'otro' COMMENT 'youtube|drive|vimeo|otro — detectado del link al guardar',
  `fecha` date DEFAULT NULL COMMENT 'fecha de la clase (no de la publicación)',
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `materia_id` (`materia_id`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `clases_grabadas_ibfk_1` FOREIGN KEY (`materia_id`) REFERENCES `materias` (`id`) ON DELETE CASCADE,
  CONSTRAINT `clases_grabadas_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
