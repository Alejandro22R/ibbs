-- ═══════════════════════════════════════════════════════════════
-- IBBS — Migración: índices para escalabilidad de los chats
-- ═══════════════════════════════════════════════════════════════
-- Cómo aplicar: pegar este archivo completo en phpMyAdmin → pestaña
-- SQL de la base `ibbs` (o `mysql -u root ibbs < 011_indices_chat_escalabilidad.sql`).
-- Es seguro volver a correrlo.
--
-- api/foro.php (chat por materia) y api/chat_general.php (chat de
-- staff, nuevo en esta misma tanda) ahora piden los últimos N
-- mensajes ordenados por fecha (`ORDER BY fecha ... LIMIT 500`) en vez
-- de traer la tabla entera — pero sin un índice que cubra ese ORDER
-- BY, MySQL igual tiene que ordenar todas las filas antes de cortar el
-- LIMIT. Con miles de mensajes acumulados en un solo hilo (staff) o en
-- una materia de varios años, eso vuelve a ser el mismo problema que
-- se quiso evitar. Estos índices lo resuelven.
-- ═══════════════════════════════════════════════════════════════

ALTER TABLE `foro_mensajes`
  ADD INDEX IF NOT EXISTS `idx_materia_fecha` (`materia_id`,`fecha`);

ALTER TABLE `chat_staff`
  ADD INDEX IF NOT EXISTS `idx_fecha` (`fecha`);
