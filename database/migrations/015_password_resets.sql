-- ═══════════════════════════════════════════════════════════════
-- IBBS — Migración: recuperación de contraseña por correo
-- ═══════════════════════════════════════════════════════════════
-- Cómo aplicar: pegar este archivo completo en phpMyAdmin → pestaña
-- SQL de la base `ibbs` (o `mysql -u root ibbs < 015_password_resets.sql`).
-- Es seguro volver a correrlo. También se autoaplica solo — ver
-- config/schema_autoheal.php — así que en la práctica no hace falta
-- pegarla a mano.
--
-- `password_resets` guarda, por cada solicitud de "olvidé mi
-- contraseña" hecha por correo (alternativa a las preguntas de
-- seguridad — ver login.php acción rec_email_solicitar), el HASH del
-- token que se mandó en el enlace (nunca el token en texto plano — si
-- alguien llegara a leer la tabla, no podría usar los enlaces ya
-- enviados, igual que una contraseña), cuándo vence y si ya se usó.
-- ═══════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` INT(11) NOT NULL,
  `token_hash` VARCHAR(64) NOT NULL,
  `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expira_en` DATETIME NOT NULL,
  `usado` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_token_hash` (`token_hash`),
  KEY `idx_usuario` (`usuario_id`),
  CONSTRAINT `password_resets_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
