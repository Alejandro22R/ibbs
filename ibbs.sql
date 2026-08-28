-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 28-08-2026 a las 01:49:00
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `ibbs`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `alumnos`
--

CREATE TABLE `alumnos` (
  `id` int(11) NOT NULL,
  `nombre` varchar(80) NOT NULL,
  `apellido` varchar(80) NOT NULL,
  `cedula` varchar(20) NOT NULL,
  `correo` varchar(120) NOT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `ciudad` varchar(80) DEFAULT NULL,
  `foto` varchar(255) DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `creado_en` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `alumnos`
--

INSERT INTO `alumnos` (`id`, `nombre`, `apellido`, `cedula`, `correo`, `telefono`, `ciudad`, `foto`, `usuario_id`, `activo`, `creado_en`) VALUES
(1, 'Enmanuel', 'rodriguez', '32316408', 'coraspedavid606@gmail.com', '04120872957', 'Bolivar', NULL, NULL, 1, '2026-03-04 18:48:09'),
(2, 'Jorge', 'rojas', '13595357', 'enma@gmail.com', '04249440764', 'Bolivar', NULL, NULL, 1, '2026-03-04 18:49:34'),
(3, 'oscar', 'garcia', '27255357', 'garciaoscarantonio22@gmail.com', '04249440764', 'Bolivar', NULL, NULL, 1, '2026-03-04 22:43:01');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `asistencias`
--

CREATE TABLE `asistencias` (
  `id` int(11) NOT NULL,
  `materia_id` int(11) NOT NULL,
  `alumno_id` int(11) DEFAULT NULL,
  `docente_id` int(11) DEFAULT NULL,
  `tipo` enum('alumno','docente') DEFAULT 'alumno',
  `fecha` date NOT NULL,
  `estado` enum('presente','ausente','tardanza','justificado') DEFAULT 'presente',
  `observacion` text DEFAULT NULL,
  `registrado_por` int(11) DEFAULT NULL,
  `creado_en` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `asistencias`
--

INSERT INTO `asistencias` (`id`, `materia_id`, `alumno_id`, `docente_id`, `tipo`, `fecha`, `estado`, `observacion`, `registrado_por`, `creado_en`) VALUES
(1, 3, 3, NULL, 'alumno', '2026-05-21', 'ausente', '', 2, '2026-05-21 06:48:29'),
(2, 3, 1, NULL, 'alumno', '2026-05-21', 'presente', '', 2, '2026-05-21 06:48:29'),
(3, 3, 3, NULL, 'alumno', '2026-08-11', 'ausente', '', 2, '2026-07-31 20:54:00'),
(4, 3, 1, NULL, 'alumno', '2026-08-11', 'presente', '', 2, '2026-07-31 20:54:00'),
(5, 3, 3, NULL, 'alumno', '2026-08-19', 'presente', '', 2, '2026-07-31 20:54:07'),
(6, 3, 1, NULL, 'alumno', '2026-08-19', 'presente', '', 2, '2026-07-31 20:54:07');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `audit_log`
--

CREATE TABLE `audit_log` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `accion` varchar(100) DEFAULT NULL,
  `detalle` text DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `creado_en` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `audit_log`
--

INSERT INTO `audit_log` (`id`, `usuario_id`, `accion`, `detalle`, `ip`, `creado_en`) VALUES
(7, 2, 'HISTORIAL_DELETE', 'Eliminados: 10 registros', '::1', '2026-04-16 05:45:16'),
(8, 2, 'USUARIO_DELETE', 'ID=6', '::1', '2026-04-27 19:11:22');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `docentes`
--

CREATE TABLE `docentes` (
  `id` int(11) NOT NULL,
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
  `creado_en` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `docentes`
--

INSERT INTO `docentes` (`id`, `nombre`, `apellido`, `cedula`, `correo`, `telefono`, `especialidad`, `ciudad`, `foto`, `usuario_id`, `activo`, `creado_en`) VALUES
(1, 'Jorge', 'rodriguez', '13595357', 'enma@gmail.com', '04249340248', 'licenciado en teologia', 'Bolivar', NULL, NULL, 1, '2026-03-04 18:49:50'),
(3, 'Enmanuel', 'rodriguez', '32316408', 'coraspedavid606@gmail.com', '04120872957', 'xxxx', 'Bolivar', NULL, NULL, 1, '2026-03-07 23:09:21'),
(4, 'intreoduccion biblica', 'rojas', '135953578', 'coraspedavid606@gmail.com', '04249440764', 'licenciado en teologia', 'Bolivar', NULL, NULL, 1, '2026-03-11 06:27:54');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `materias`
--

CREATE TABLE `materias` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `codigo` varchar(20) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `dias` varchar(100) DEFAULT NULL,
  `hora_inicio` time DEFAULT NULL,
  `hora_fin` time DEFAULT NULL,
  `estado` enum('pendiente','en_curso','culminada') DEFAULT 'en_curso',
  `activo` tinyint(1) DEFAULT 1,
  `creado_en` datetime DEFAULT current_timestamp(),
  `periodo_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `materias`
--

INSERT INTO `materias` (`id`, `nombre`, `codigo`, `descripcion`, `dias`, `hora_inicio`, `hora_fin`, `estado`, `activo`, `creado_en`, `periodo_id`) VALUES
(3, 'hermeneutica', '1', '', 'Sábado', '20:59:00', '22:59:00', 'en_curso', 1, '2026-03-04 20:59:50', NULL),
(4, 'intreoduccion biblica', '3', '', 'Sábado', '14:49:00', '15:49:00', 'en_curso', 1, '2026-03-04 22:49:52', NULL),
(6, '123', '17', '', '', NULL, NULL, 'en_curso', 1, '2026-04-16 10:43:09', NULL),
(7, '123', '888', '', 'Martes', '11:34:00', NULL, 'culminada', 1, '2026-06-18 10:34:41', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `materia_alumno`
--

CREATE TABLE `materia_alumno` (
  `id` int(11) NOT NULL,
  `materia_id` int(11) NOT NULL,
  `alumno_id` int(11) NOT NULL,
  `nota_final` decimal(4,1) DEFAULT NULL,
  `nota_fecha` date DEFAULT NULL,
  `nota_registrada_por` int(11) DEFAULT NULL,
  `nota_actualizada_en` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `materia_alumno`
--

INSERT INTO `materia_alumno` (`id`, `materia_id`, `alumno_id`, `nota_final`, `nota_fecha`, `nota_registrada_por`, `nota_actualizada_en`) VALUES
(1, 3, 1, 20.0, '2026-04-21', 2, '2026-04-21 11:36:59'),
(2, 3, 3, 15.0, '2026-08-01', 2, '2026-07-31 20:55:06'),
(3, 4, 1, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `materia_docente`
--

CREATE TABLE `materia_docente` (
  `id` int(11) NOT NULL,
  `materia_id` int(11) NOT NULL,
  `docente_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `notificaciones`
--

CREATE TABLE `notificaciones` (
  `id` int(11) NOT NULL,
  `tipo` enum('reprobado','asistencia','sistema','info') DEFAULT 'info',
  `titulo` varchar(200) NOT NULL,
  `mensaje` text DEFAULT NULL,
  `para_rol` varchar(20) DEFAULT 'admin',
  `usuario_id` int(11) DEFAULT NULL,
  `leida` tinyint(1) DEFAULT 0,
  `creado_en` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `notificaciones`
--

INSERT INTO `notificaciones` (`id`, `tipo`, `titulo`, `mensaje`, `para_rol`, `usuario_id`, `leida`, `creado_en`) VALUES
(1, 'asistencia', 'Ausencias críticas • ID:3•3', '<strong>oscar garcia</strong> tiene <strong>100% de ausencias</strong> en <strong>hermeneutica</strong>.', 'admin', NULL, 1, '2026-06-18 09:29:53');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `periodos`
--

CREATE TABLE `periodos` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `anio` year(4) NOT NULL,
  `descripcion` varchar(200) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `creado_en` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `periodos`
--

INSERT INTO `periodos` (`id`, `nombre`, `anio`, `descripcion`, `activo`, `creado_en`) VALUES
(1, '2025 - I Semestre', '2025', 'Primer semestre académico 2025', 1, '2026-03-10 21:38:47'),
(2, '2025 - II Semestre', '2025', 'Segundo semestre académico 2025', 1, '2026-03-10 21:38:47'),
(3, '2026 - I Semestre', '2026', 'Primer semestre académico 2026', 1, '2026-03-10 21:38:47');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
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
  `delete_pin` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `usuario`, `correo`, `cedula`, `password_hash`, `rol`, `activo`, `foto`, `preg1`, `resp1_hash`, `preg2`, `resp2_hash`, `creado_en`, `delete_pin`) VALUES
(2, 'Enmanuel', 'coraspedavid@gmail.com', '32316408', '$2y$10$7oPIixRxZlAxczCPMLxDq.5qJDguX32geF2877jwQy0w4/uWRXvVq', 'superadmin', 1, 'uploads/fotos/usuario_2_1777839823.jpg', '¿En qué ciudad naciste?', '$2y$10$MS118RY6ZLdXDICHIV1MLuegKEzPW8Egp5If/Z49NImFJ5Oo4onTS', '¿Cuál es el apellido de tu madre?', '$2y$10$8hLXqqi2roYg/Ii0A8VJh.B2Wzy66rirdd4tEndzOUww5wL7Ijj62', '2026-03-04 18:44:45', '$2y$10$S6ISC67AHLBJglbJfq9DReaGV3gapYTSjLCO.TJSUyGArwd7Ax3IG'),
(3, 'oscar', 'garciaoscarantonio22@gmail.com', '', '$2y$10$/LjmVwTVv2Gu2QYdK2I/1Oseg3sFUtR2fn5sPYP8yrAae01fjC.iq', 'admin', 1, NULL, NULL, NULL, NULL, NULL, '2026-03-04 22:47:21', NULL),
(5, 'jonas', 'enmanuel@gmail.com', '12345678', '$2y$10$x.2i.uN5Hv.bj6c.RivQNeIWb9LjkbejxdcpWTY8xsMXx6IRNsruK', 'alumno', 1, NULL, '¿Cuál es el nombre de tu primera mascota?', '$2y$10$LZUFxpl2oBqpcyZFHInx6eya/z57F6F4pNtrYpHoJsVb4N2tLZlkS', '¿En qué ciudad naciste?', '$2y$10$u1cb/yHl5HFCa5fFU.aIVuzYrUhxOC/ewFnMDGuEa275c1/hmvSUO', '2026-04-13 17:07:56', NULL);

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `alumnos`
--
ALTER TABLE `alumnos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `cedula` (`cedula`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Indices de la tabla `asistencias`
--
ALTER TABLE `asistencias`
  ADD PRIMARY KEY (`id`),
  ADD KEY `materia_id` (`materia_id`),
  ADD KEY `alumno_id` (`alumno_id`),
  ADD KEY `docente_id` (`docente_id`);

--
-- Indices de la tabla `audit_log`
--
ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `docentes`
--
ALTER TABLE `docentes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `cedula` (`cedula`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Indices de la tabla `materias`
--
ALTER TABLE `materias`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `codigo` (`codigo`);

--
-- Indices de la tabla `materia_alumno`
--
ALTER TABLE `materia_alumno`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_ma` (`materia_id`,`alumno_id`),
  ADD KEY `alumno_id` (`alumno_id`);

--
-- Indices de la tabla `materia_docente`
--
ALTER TABLE `materia_docente`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_md` (`materia_id`,`docente_id`),
  ADD KEY `docente_id` (`docente_id`);

--
-- Indices de la tabla `notificaciones`
--
ALTER TABLE `notificaciones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Indices de la tabla `periodos`
--
ALTER TABLE `periodos`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `usuario` (`usuario`),
  ADD UNIQUE KEY `correo` (`correo`),
  ADD UNIQUE KEY `cedula` (`cedula`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `alumnos`
--
ALTER TABLE `alumnos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de la tabla `asistencias`
--
ALTER TABLE `asistencias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de la tabla `docentes`
--
ALTER TABLE `docentes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `materias`
--
ALTER TABLE `materias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de la tabla `materia_alumno`
--
ALTER TABLE `materia_alumno`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `materia_docente`
--
ALTER TABLE `materia_docente`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `notificaciones`
--
ALTER TABLE `notificaciones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `periodos`
--
ALTER TABLE `periodos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `alumnos`
--
ALTER TABLE `alumnos`
  ADD CONSTRAINT `alumnos_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `asistencias`
--
ALTER TABLE `asistencias`
  ADD CONSTRAINT `asistencias_ibfk_1` FOREIGN KEY (`materia_id`) REFERENCES `materias` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `asistencias_ibfk_2` FOREIGN KEY (`alumno_id`) REFERENCES `alumnos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `asistencias_ibfk_3` FOREIGN KEY (`docente_id`) REFERENCES `docentes` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `docentes`
--
ALTER TABLE `docentes`
  ADD CONSTRAINT `docentes_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `materia_alumno`
--
ALTER TABLE `materia_alumno`
  ADD CONSTRAINT `materia_alumno_ibfk_1` FOREIGN KEY (`materia_id`) REFERENCES `materias` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `materia_alumno_ibfk_2` FOREIGN KEY (`alumno_id`) REFERENCES `alumnos` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `materia_docente`
--
ALTER TABLE `materia_docente`
  ADD CONSTRAINT `materia_docente_ibfk_1` FOREIGN KEY (`materia_id`) REFERENCES `materias` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `materia_docente_ibfk_2` FOREIGN KEY (`docente_id`) REFERENCES `docentes` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `notificaciones`
--
ALTER TABLE `notificaciones`
  ADD CONSTRAINT `notificaciones_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
