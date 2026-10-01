-- ═══════════════════════════════════════════════════════════════
-- IBBS — Migración: fecha límite de corrección y % por actividad
-- ═══════════════════════════════════════════════════════════════
-- Cómo aplicar: pegar este archivo completo en phpMyAdmin → pestaña
-- SQL de la base `ibbs` (o `mysql -u root ibbs < 017_tareas_correccion_porcentaje.sql`).
-- Es seguro volver a correrlo. También se autoaplica solo — ver
-- config/schema_autoheal.php — así que en la práctica no hace falta
-- pegarla a mano.
--
-- `tareas.fecha_limite_correccion`: el profesor tiene su propia fecha
-- límite para corregir, separada de la fecha límite de entrega del
-- alumno (`fecha_limite`) — tiene que ser posterior a esa, para dejarle
-- margen de corrección. Se valida en crear_tarea.php.
--
-- `tareas.porcentaje`: cuánto vale esa actividad sobre la nota final de
-- la materia (opcional) — lo usa el "Plan de Notas"
-- (api/plan_notas.php) para calcular el promedio ponderado que ve el
-- profesor; el alumno ve el % de cada actividad pero nunca la nota ni
-- el promedio ahí.
-- ═══════════════════════════════════════════════════════════════

ALTER TABLE `tareas`
  ADD COLUMN IF NOT EXISTS `fecha_limite_correccion` DATETIME DEFAULT NULL AFTER `fecha_limite`,
  ADD COLUMN IF NOT EXISTS `porcentaje` DECIMAL(5,2) DEFAULT NULL AFTER `nota_maxima`;
