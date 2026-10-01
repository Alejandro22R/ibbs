-- ═══════════════════════════════════════════════════════════════
-- IBBS — Migración: mensaje del profesor junto con la calificación
-- ═══════════════════════════════════════════════════════════════
-- Cómo aplicar: pegar este archivo completo en phpMyAdmin → pestaña
-- SQL de la base `ibbs` (o `mysql -u root ibbs < 016_entrega_observacion.sql`).
-- Es seguro volver a correrlo. También se autoaplica solo — ver
-- config/schema_autoheal.php — así que en la práctica no hace falta
-- pegarla a mano.
--
-- `entregas.observacion` guarda el mensaje opcional que el profesor
-- escribe al calificar una entrega — para felicitar al alumno,
-- corregirlo, o dejar una observación — ver calificar_entrega.php y la
-- vista "Tareas" de portal_alumno.php, donde se le muestra junto a la
-- nota.
-- ═══════════════════════════════════════════════════════════════

ALTER TABLE `entregas`
  ADD COLUMN IF NOT EXISTS `observacion` TEXT DEFAULT NULL AFTER `nota`;
