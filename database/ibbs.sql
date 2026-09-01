-- IBBS Backup — 2026-09-01 03:53:56
-- Exportado por: Enmanuel

SET FOREIGN_KEY_CHECKS=0;

-- ── Tabla: alumnos ──
DROP TABLE IF EXISTS `alumnos`;
CREATE TABLE `alumnos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(80) NOT NULL,
  `apellido` varchar(80) NOT NULL,
  `cedula` varchar(20) NOT NULL,
  `correo` varchar(120) NOT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `ciudad` varchar(80) DEFAULT NULL,
  `foto` varchar(255) DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `creado_en` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `cedula` (`cedula`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `alumnos_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `alumnos` VALUES('1','Enmanuel','rodriguez','32316408','coraspedavid606@gmail.com','04120872957','Bolivar',NULL,NULL,'1','2026-03-04 18:48:09');
INSERT INTO `alumnos` VALUES('2','Jorge','rojas','13595357','enma@gmail.com','04249440764','Bolivar',NULL,NULL,'1','2026-03-04 18:49:34');
INSERT INTO `alumnos` VALUES('3','oscar','garcia','27255357','garciaoscarantonio22@gmail.com','04249440764','Bolivar',NULL,NULL,'1','2026-03-04 22:43:01');

-- ── Tabla: asistencias ──
DROP TABLE IF EXISTS `asistencias`;
CREATE TABLE `asistencias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `materia_id` int(11) NOT NULL,
  `alumno_id` int(11) DEFAULT NULL,
  `docente_id` int(11) DEFAULT NULL,
  `tipo` enum('alumno','docente') DEFAULT 'alumno',
  `fecha` date NOT NULL,
  `estado` enum('presente','ausente','tardanza','justificado') DEFAULT 'presente',
  `observacion` text DEFAULT NULL,
  `registrado_por` int(11) DEFAULT NULL,
  `creado_en` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `materia_id` (`materia_id`),
  KEY `alumno_id` (`alumno_id`),
  KEY `docente_id` (`docente_id`),
  CONSTRAINT `asistencias_ibfk_1` FOREIGN KEY (`materia_id`) REFERENCES `materias` (`id`) ON DELETE CASCADE,
  CONSTRAINT `asistencias_ibfk_2` FOREIGN KEY (`alumno_id`) REFERENCES `alumnos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `asistencias_ibfk_3` FOREIGN KEY (`docente_id`) REFERENCES `docentes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `asistencias` VALUES('1','3','3',NULL,'alumno','2026-05-21','ausente','','2','2026-05-21 06:48:29');
INSERT INTO `asistencias` VALUES('2','3','1',NULL,'alumno','2026-05-21','presente','','2','2026-05-21 06:48:29');
INSERT INTO `asistencias` VALUES('3','3','3',NULL,'alumno','2026-08-11','ausente','','2','2026-07-31 20:54:00');
INSERT INTO `asistencias` VALUES('4','3','1',NULL,'alumno','2026-08-11','presente','','2','2026-07-31 20:54:00');
INSERT INTO `asistencias` VALUES('5','3','3',NULL,'alumno','2026-08-19','presente','','2','2026-07-31 20:54:07');
INSERT INTO `asistencias` VALUES('6','3','1',NULL,'alumno','2026-08-19','presente','','2','2026-07-31 20:54:07');

-- ── Tabla: audit_log ──
DROP TABLE IF EXISTS `audit_log`;
CREATE TABLE `audit_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) DEFAULT NULL,
  `accion` varchar(100) DEFAULT NULL,
  `detalle` text DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `creado_en` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `audit_log` VALUES('7','2','HISTORIAL_DELETE','Eliminados: 10 registros','::1','2026-04-16 05:45:16');
INSERT INTO `audit_log` VALUES('8','2','USUARIO_DELETE','ID=6','::1','2026-04-27 19:11:22');

-- ── Tabla: aula_actividades ──
DROP TABLE IF EXISTS `aula_actividades`;
CREATE TABLE `aula_actividades` (
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

-- ── Tabla: aula_anuncios ──
DROP TABLE IF EXISTS `aula_anuncios`;
CREATE TABLE `aula_anuncios` (
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

-- ── Tabla: aula_calificaciones ──
DROP TABLE IF EXISTS `aula_calificaciones`;
CREATE TABLE `aula_calificaciones` (
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

-- ── Tabla: aula_materiales ──
DROP TABLE IF EXISTS `aula_materiales`;
CREATE TABLE `aula_materiales` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `materia_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL COMMENT 'quién subió el archivo',
  `titulo` varchar(150) NOT NULL,
  `descripcion` varchar(500) DEFAULT NULL,
  `archivo` varchar(255) NOT NULL COMMENT 'ruta relativa dentro de uploads/materiales/, nombre generado (no confiar en el original)',
  `archivo_nombre` varchar(255) NOT NULL COMMENT 'nombre original del archivo, solo para mostrar/descargar',
  `archivo_tipo` varchar(10) NOT NULL COMMENT 'extensión validada en el servidor',
  `tamano_bytes` int(10) unsigned NOT NULL DEFAULT 0,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `materia_id` (`materia_id`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `aula_materiales_ibfk_1` FOREIGN KEY (`materia_id`) REFERENCES `materias` (`id`) ON DELETE CASCADE,
  CONSTRAINT `aula_materiales_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Tabla: clases_grabadas ──
DROP TABLE IF EXISTS `clases_grabadas`;
CREATE TABLE `clases_grabadas` (
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

-- ── Tabla: docentes ──
DROP TABLE IF EXISTS `docentes`;
CREATE TABLE `docentes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(80) NOT NULL,
  `apellido` varchar(80) NOT NULL,
  `cedula` varchar(20) NOT NULL,
  `correo` varchar(120) NOT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `especialidad` varchar(100) DEFAULT NULL,
  `ciudad` varchar(80) DEFAULT NULL,
  `foto` varchar(255) DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `creado_en` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `cedula` (`cedula`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `docentes_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `docentes` VALUES('1','Jorge','rodriguez','13595357','enma@gmail.com','04249340248','licenciado en teologia','Bolivar',NULL,NULL,'1','2026-03-04 18:49:50');
INSERT INTO `docentes` VALUES('3','Enmanuel','rodriguez','32316408','coraspedavid606@gmail.com','04120872957','xxxx','Bolivar',NULL,NULL,'1','2026-03-07 23:09:21');
INSERT INTO `docentes` VALUES('4','intreoduccion biblica','rojas','135953578','coraspedavid606@gmail.com','04249440764','licenciado en teologia','Bolivar',NULL,NULL,'1','2026-03-11 06:27:54');

-- ── Tabla: materia_alumno ──
DROP TABLE IF EXISTS `materia_alumno`;
CREATE TABLE `materia_alumno` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `materia_id` int(11) NOT NULL,
  `alumno_id` int(11) NOT NULL,
  `nota_final` decimal(4,1) DEFAULT NULL,
  `nota_fecha` date DEFAULT NULL,
  `nota_registrada_por` int(11) DEFAULT NULL,
  `nota_actualizada_en` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_ma` (`materia_id`,`alumno_id`),
  KEY `alumno_id` (`alumno_id`),
  CONSTRAINT `materia_alumno_ibfk_1` FOREIGN KEY (`materia_id`) REFERENCES `materias` (`id`) ON DELETE CASCADE,
  CONSTRAINT `materia_alumno_ibfk_2` FOREIGN KEY (`alumno_id`) REFERENCES `alumnos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `materia_alumno` VALUES('1','3','1','20.0','2026-04-21','2','2026-04-21 11:36:59');
INSERT INTO `materia_alumno` VALUES('2','3','3','15.0','2026-08-01','2','2026-07-31 20:55:06');
INSERT INTO `materia_alumno` VALUES('3','4','1',NULL,NULL,NULL,NULL);

-- ── Tabla: materia_docente ──
DROP TABLE IF EXISTS `materia_docente`;
CREATE TABLE `materia_docente` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `materia_id` int(11) NOT NULL,
  `docente_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_md` (`materia_id`,`docente_id`),
  KEY `docente_id` (`docente_id`),
  CONSTRAINT `materia_docente_ibfk_1` FOREIGN KEY (`materia_id`) REFERENCES `materias` (`id`) ON DELETE CASCADE,
  CONSTRAINT `materia_docente_ibfk_2` FOREIGN KEY (`docente_id`) REFERENCES `docentes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Tabla: materias ──
DROP TABLE IF EXISTS `materias`;
CREATE TABLE `materias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `codigo` varchar(20) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `dias` varchar(100) DEFAULT NULL,
  `hora_inicio` time DEFAULT NULL,
  `hora_fin` time DEFAULT NULL,
  `estado` enum('pendiente','en_curso','culminada') DEFAULT 'en_curso',
  `activo` tinyint(1) DEFAULT 1,
  `creado_en` datetime DEFAULT current_timestamp(),
  `periodo_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `codigo` (`codigo`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `materias` VALUES('3','hermeneutica','1','','Sábado','20:59:00','22:59:00','en_curso','1','2026-03-04 20:59:50',NULL);
INSERT INTO `materias` VALUES('4','intreoduccion biblica','3','','Sábado','14:49:00','15:49:00','en_curso','1','2026-03-04 22:49:52',NULL);
INSERT INTO `materias` VALUES('6','123','17','','',NULL,NULL,'en_curso','1','2026-04-16 10:43:09',NULL);
INSERT INTO `materias` VALUES('7','123','888','','Martes','11:34:00',NULL,'culminada','1','2026-06-18 10:34:41',NULL);

-- ── Tabla: notificaciones ──
DROP TABLE IF EXISTS `notificaciones`;
CREATE TABLE `notificaciones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tipo` enum('reprobado','asistencia','sistema','info') DEFAULT 'info',
  `titulo` varchar(200) NOT NULL,
  `mensaje` text DEFAULT NULL,
  `para_rol` varchar(20) DEFAULT 'admin',
  `usuario_id` int(11) DEFAULT NULL,
  `leida` tinyint(1) DEFAULT 0,
  `creado_en` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `notificaciones_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `notificaciones` VALUES('1','asistencia','Ausencias críticas • ID:3•3','<strong>oscar garcia</strong> tiene <strong>100% de ausencias</strong> en <strong>hermeneutica</strong>.','admin',NULL,'1','2026-06-18 09:29:53');

-- ── Tabla: periodos ──
DROP TABLE IF EXISTS `periodos`;
CREATE TABLE `periodos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `anio` year(4) NOT NULL,
  `descripcion` varchar(200) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `creado_en` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `periodos` VALUES('1','2025 - I Semestre','2025','Primer semestre académico 2025','1','2026-03-10 21:38:47');
INSERT INTO `periodos` VALUES('2','2025 - II Semestre','2025','Segundo semestre académico 2025','1','2026-03-10 21:38:47');
INSERT INTO `periodos` VALUES('3','2026 - I Semestre','2026','Primer semestre académico 2026','1','2026-03-10 21:38:47');

-- ── Tabla: usuarios ──
DROP TABLE IF EXISTS `usuarios`;
CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario` varchar(60) NOT NULL,
  `correo` varchar(120) NOT NULL,
  `cedula` varchar(20) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `rol` enum('superadmin','admin','profesor','alumno') DEFAULT 'alumno',
  `activo` tinyint(1) DEFAULT 1,
  `foto` varchar(255) DEFAULT NULL,
  `preg1` varchar(200) DEFAULT NULL,
  `resp1_hash` varchar(255) DEFAULT NULL,
  `preg2` varchar(200) DEFAULT NULL,
  `resp2_hash` varchar(255) DEFAULT NULL,
  `creado_en` datetime DEFAULT current_timestamp(),
  `delete_pin` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `usuario` (`usuario`),
  UNIQUE KEY `correo` (`correo`),
  UNIQUE KEY `cedula` (`cedula`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `usuarios` VALUES('2','Enmanuel','coraspedavid@gmail.com','32316408','$2y$10$7oPIixRxZlAxczCPMLxDq.5qJDguX32geF2877jwQy0w4/uWRXvVq','superadmin','1','uploads/fotos/usuario_2_1777839823.jpg','¿En qué ciudad naciste?','$2y$10$MS118RY6ZLdXDICHIV1MLuegKEzPW8Egp5If/Z49NImFJ5Oo4onTS','¿Cuál es el apellido de tu madre?','$2y$10$8hLXqqi2roYg/Ii0A8VJh.B2Wzy66rirdd4tEndzOUww5wL7Ijj62','2026-03-04 18:44:45','$2y$10$S6ISC67AHLBJglbJfq9DReaGV3gapYTSjLCO.TJSUyGArwd7Ax3IG');
INSERT INTO `usuarios` VALUES('3','oscar','garciaoscarantonio22@gmail.com','','$2y$10$JASAeA95uh96ymThPuraP.teCge./.S8OuX8WP4pF7NmX2taWWc.e','profesor','1',NULL,NULL,NULL,NULL,NULL,'2026-03-04 22:47:21',NULL);
INSERT INTO `usuarios` VALUES('5','jonas','enmanuel@gmail.com','12345678','$2y$10$x.2i.uN5Hv.bj6c.RivQNeIWb9LjkbejxdcpWTY8xsMXx6IRNsruK','alumno','1',NULL,'¿Cuál es el nombre de tu primera mascota?','$2y$10$LZUFxpl2oBqpcyZFHInx6eya/z57F6F4pNtrYpHoJsVb4N2tLZlkS','¿En qué ciudad naciste?','$2y$10$u1cb/yHl5HFCa5fFU.aIVuzYrUhxOC/ewFnMDGuEa275c1/hmvSUO','2026-04-13 17:07:56',NULL);

SET FOREIGN_KEY_CHECKS=1;
