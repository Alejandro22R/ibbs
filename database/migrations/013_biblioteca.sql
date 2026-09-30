-- ═══════════════════════════════════════════════════════════════
-- IBBS — Migración: Biblioteca (catálogo de libros + compras)
-- ═══════════════════════════════════════════════════════════════
-- Cómo aplicar: pegar este archivo completo en phpMyAdmin → pestaña
-- SQL de la base `ibbs` (o `mysql -u root ibbs < 013_biblioteca.sql`).
-- Es seguro volver a correrlo.
--
-- `libros`: catálogo. Lo cargan admin/superadmin o cualquier docente
-- (`creado_por` guarda quién, para que un profesor solo pueda
-- editar/borrar los suyos — admin/superadmin ve y gestiona todos).
-- `archivo` es PRIVADO (vive fuera de `uploads/` público, en
-- `uploads/libros_privados/`, bloqueado por su propio .htaccess) — se
-- sirve siempre a través de api/biblioteca.php?action=descargar, que
-- valida que quien lo pide lo haya comprado (o que el libro sea
-- gratis, o que sea admin/superadmin/el docente que lo subió).
-- `portada` sí es pública (uploads/libros/portadas/), como cualquier
-- otra imagen de catálogo.
--
-- `libro_compras`: igual patrón que `materia_solicitudes` (turno
-- anterior) — el alumno manda su solicitud de compra con el
-- comprobante de pago adjunto, queda 'pendiente', y un admin/superadmin
-- la revisa (viendo la imagen) antes de "activar" el libro en su
-- cuenta. Si el libro es gratis (precio=0) no hace falta comprobante:
-- la activación es automática.
--
-- `datos_pago`: una sola fila con los datos de pago de la institución
-- (banco, pago móvil, etc.) que se le muestran al alumno al momento de
-- comprar — hoy puede quedar vacía; el admin la completa cuando la
-- institución los tenga listos ("los datos que futuramente se darán").
-- ═══════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `libros` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `titulo` VARCHAR(200) NOT NULL,
  `autor` VARCHAR(150) DEFAULT NULL,
  `descripcion` TEXT DEFAULT NULL,
  `categoria` VARCHAR(80) DEFAULT NULL,
  `precio` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `portada` VARCHAR(255) DEFAULT NULL,
  `archivo` VARCHAR(255) NOT NULL,
  `archivo_nombre` VARCHAR(255) NOT NULL,
  `archivo_tipo` VARCHAR(10) NOT NULL,
  `creado_por` INT(11) DEFAULT NULL,
  `activo` TINYINT(1) NOT NULL DEFAULT 1,
  `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_creado_por` (`creado_por`),
  KEY `idx_activo` (`activo`),
  CONSTRAINT `libros_ibfk_1` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `libro_compras` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `libro_id` INT(11) NOT NULL,
  `alumno_id` INT(11) NOT NULL,
  `comprobante` VARCHAR(255) DEFAULT NULL,
  `precio_pagado` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `estado` ENUM('pendiente','activado','rechazado') NOT NULL DEFAULT 'pendiente',
  `revisado_por` INT(11) DEFAULT NULL,
  `fecha` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_revision` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_estado` (`estado`),
  KEY `idx_libro` (`libro_id`),
  KEY `idx_alumno` (`alumno_id`),
  CONSTRAINT `libro_compras_ibfk_1` FOREIGN KEY (`libro_id`) REFERENCES `libros` (`id`) ON DELETE CASCADE,
  CONSTRAINT `libro_compras_ibfk_2` FOREIGN KEY (`alumno_id`) REFERENCES `alumnos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `datos_pago` (
  `id` INT(11) NOT NULL DEFAULT 1,
  `titular` VARCHAR(150) DEFAULT NULL,
  `banco` VARCHAR(100) DEFAULT NULL,
  `cuenta` VARCHAR(60) DEFAULT NULL,
  `cedula_rif` VARCHAR(30) DEFAULT NULL,
  `pago_movil_telefono` VARCHAR(30) DEFAULT NULL,
  `instrucciones` TEXT DEFAULT NULL,
  `actualizado_en` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO `datos_pago` (`id`) VALUES (1);
