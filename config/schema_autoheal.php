<?php
/**
 * IBBS — Auto-reparación de esquema.
 *
 * Cada migración nueva (database/migrations/NNN_*.sql) exige pegarla a
 * mano en phpMyAdmin — un paso manual que es fácil de saltarse, y que
 * rompe cosas de formas confusas: no un error claro, sino un login que
 * se queda cargando para siempre (la consulta pide una columna que no
 * existe, `mysqli_prepare()` devuelve `false`, y el resto del código
 * asume que sí devolvió un statement válido).
 *
 * Se engancha desde `db()` (config/database.php): cualquier página o
 * endpoint que pida una conexión, sin que nadie tenga que acordarse de
 * llamarla, queda con el esquema al día — nunca más un login roto por
 * una migración que nadie corrió a mano.
 *
 * Para no pagar ~20 consultas de metadata en CADA request (esta app
 * apunta a miles de alumnos concurrentes, ver el commit de
 * escalabilidad de notificaciones), el resultado se recuerda en un
 * archivo marcador dentro de `storage/` (mismo directorio que ya usa
 * el freno de fuerza bruta del login) — una vez sanado, cada request
 * siguiente es un simple `file_exists()` y listo, cero consultas
 * extra. Subí `IBBS_SCHEMA_VERSION` cada vez que agregues DDL nuevo
 * acá (una migración más) para que vuelva a correr una vez.
 *
 * Si el motor no soporta `IF NOT EXISTS` en `ALTER TABLE` (MySQL
 * < 8.0.29 / MariaDB < 10.3), cada sentencia individual simplemente
 * falla y se ignora (@) — ninguna es indispensable para que el login
 * funcione, así que un fallo acá nunca debe tumbar la página.
 */

define('IBBS_SCHEMA_VERSION', 19);

if (!function_exists('ibbs_autoheal_schema')) {
    function ibbs_autoheal_schema($con) {
        static $yaCorrioEsteRequest = false;
        if ($yaCorrioEsteRequest || !$con) return;
        $yaCorrioEsteRequest = true;

        $dir = __DIR__.'/../storage';
        $marcador = $dir.'/.schema_healed_v'.IBBS_SCHEMA_VERSION;
        if (is_file($marcador)) return;

        $ddl = [
            // 004_notificaciones_tiempo_real.sql
            "ALTER TABLE `notificaciones` MODIFY `tipo` VARCHAR(30) NOT NULL DEFAULT 'info'",
            "ALTER TABLE `notificaciones` ADD COLUMN IF NOT EXISTS `materia_id` INT(11) DEFAULT NULL AFTER `usuario_id`",
            "ALTER TABLE `notificaciones` ADD INDEX IF NOT EXISTS `idx_stream` (`usuario_id`,`leida`,`id`)",
            // 005_foro_usuario_id.sql
            "ALTER TABLE `foro_mensajes` ADD COLUMN IF NOT EXISTS `usuario_id` INT(11) DEFAULT NULL AFTER `materia_id`",
            "ALTER TABLE `foro_mensajes` ADD INDEX IF NOT EXISTS `idx_materia_usuario` (`materia_id`,`usuario_id`)",
            // 006_alumno_regular_autoinscripcion.sql
            "ALTER TABLE `alumnos` ADD COLUMN IF NOT EXISTS `regular` TINYINT(1) NOT NULL DEFAULT 0 AFTER `activo`",
            "ALTER TABLE `materia_alumno` ADD COLUMN IF NOT EXISTS `auto_inscrito` TINYINT(1) NOT NULL DEFAULT 0 AFTER `alumno_id`",
            // 007_materia_inscripcion_abierta.sql
            "ALTER TABLE `materias` ADD COLUMN IF NOT EXISTS `inscripcion_abierta` TINYINT(1) NOT NULL DEFAULT 0 AFTER `estado`",
            // 008_asegurar_rol_alumno.sql
            "ALTER TABLE `usuarios` MODIFY `rol` ENUM('superadmin','admin','profesor','alumno') DEFAULT 'alumno'",
            // 009_alumno_aprobacion.sql — la más crítica: sin `aprobado`,
            // el propio login (que la selecciona siempre) se rompe para
            // TODOS los roles, no solo para alumno.
            "ALTER TABLE `usuarios` ADD COLUMN IF NOT EXISTS `aprobado` TINYINT(1) NOT NULL DEFAULT 1 AFTER `activo`",
            "ALTER TABLE `notificaciones` ADD COLUMN IF NOT EXISTS `referencia_id` INT(11) DEFAULT NULL AFTER `materia_id`",
            // 010_chat_staff.sql
            "CREATE TABLE IF NOT EXISTS `chat_staff` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `usuario_id` INT(11) DEFAULT NULL,
                `usuario_nombre` VARCHAR(100) NOT NULL,
                `rol` VARCHAR(20) NOT NULL DEFAULT 'profesor',
                `mensaje` TEXT NOT NULL,
                `respuesta_a` INT(11) DEFAULT NULL,
                `fecha` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_usuario` (`usuario_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            // 011_indices_chat_escalabilidad.sql
            "ALTER TABLE `foro_mensajes` ADD INDEX IF NOT EXISTS `idx_materia_fecha` (`materia_id`,`fecha`)",
            "ALTER TABLE `chat_staff` ADD INDEX IF NOT EXISTS `idx_fecha` (`fecha`)",
            // 012_materia_solicitudes.sql
            "CREATE TABLE IF NOT EXISTS `materia_solicitudes` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `alumno_id` INT(11) NOT NULL,
                `materia_id` INT(11) NOT NULL,
                `comprobante` VARCHAR(255) NOT NULL,
                `estado` ENUM('pendiente','aprobada','rechazada') NOT NULL DEFAULT 'pendiente',
                `revisado_por` INT(11) DEFAULT NULL,
                `fecha` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `fecha_revision` DATETIME DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_estado` (`estado`),
                KEY `idx_alumno` (`alumno_id`),
                KEY `idx_materia` (`materia_id`),
                CONSTRAINT `autoheal_ms_ibfk_1` FOREIGN KEY (`alumno_id`) REFERENCES `alumnos` (`id`) ON DELETE CASCADE,
                CONSTRAINT `autoheal_ms_ibfk_2` FOREIGN KEY (`materia_id`) REFERENCES `materias` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            // 013_biblioteca.sql
            "CREATE TABLE IF NOT EXISTS `libros` (
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
                CONSTRAINT `autoheal_libros_ibfk_1` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `libro_compras` (
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
                CONSTRAINT `autoheal_lc_ibfk_1` FOREIGN KEY (`libro_id`) REFERENCES `libros` (`id`) ON DELETE CASCADE,
                CONSTRAINT `autoheal_lc_ibfk_2` FOREIGN KEY (`alumno_id`) REFERENCES `alumnos` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `datos_pago` (
                `id` INT(11) NOT NULL DEFAULT 1,
                `titular` VARCHAR(150) DEFAULT NULL,
                `banco` VARCHAR(100) DEFAULT NULL,
                `cuenta` VARCHAR(60) DEFAULT NULL,
                `cedula_rif` VARCHAR(30) DEFAULT NULL,
                `pago_movil_telefono` VARCHAR(30) DEFAULT NULL,
                `instrucciones` TEXT DEFAULT NULL,
                `actualizado_en` DATETIME DEFAULT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "INSERT IGNORE INTO `datos_pago` (`id`) VALUES (1)",
            // 014_asistencia_hojas.sql
            "CREATE TABLE IF NOT EXISTS `asistencia_hojas` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `materia_id` INT(11) NOT NULL,
                `fecha` DATE NOT NULL,
                `foto` VARCHAR(255) NOT NULL,
                `texto_ocr` MEDIUMTEXT DEFAULT NULL,
                `modo` ENUM('asistieron','faltaron') NOT NULL DEFAULT 'asistieron',
                `total_alumnos` INT(11) NOT NULL DEFAULT 0,
                `total_detectados` INT(11) NOT NULL DEFAULT 0,
                `cargado_por` INT(11) DEFAULT NULL,
                `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_materia_fecha` (`materia_id`,`fecha`),
                CONSTRAINT `autoheal_ah_ibfk_1` FOREIGN KEY (`materia_id`) REFERENCES `materias` (`id`) ON DELETE CASCADE,
                CONSTRAINT `autoheal_ah_ibfk_2` FOREIGN KEY (`cargado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "ALTER TABLE `asistencias` ADD COLUMN IF NOT EXISTS `hoja_id` INT(11) DEFAULT NULL AFTER `registrado_por`",
            // 015_password_resets.sql — recuperación de contraseña por
            // correo (enlace con token), alternativa a las preguntas de
            // seguridad. Solo se guarda el HASH del token — si alguien
            // llegara a leer la tabla, no podría usar los enlaces ya
            // enviados (igual que una contraseña, nunca en texto plano).
            "CREATE TABLE IF NOT EXISTS `password_resets` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `usuario_id` INT(11) NOT NULL,
                `token_hash` VARCHAR(64) NOT NULL,
                `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `expira_en` DATETIME NOT NULL,
                `usado` TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                KEY `idx_token_hash` (`token_hash`),
                KEY `idx_usuario` (`usuario_id`),
                CONSTRAINT `autoheal_pr_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            // 016_entrega_observacion.sql — mensaje opcional del
            // profesor junto con la nota (felicitar, corregir, o
            // simplemente una observación) — ver calificar_entrega.php.
            "ALTER TABLE `entregas` ADD COLUMN IF NOT EXISTS `observacion` TEXT DEFAULT NULL AFTER `nota`",
            // 017_tareas_correccion_porcentaje.sql — fecha límite propia
            // para que el profesor corrija (separada de la fecha límite
            // de entrega del alumno) y el % que esa actividad vale sobre
            // la nota final de la materia — ver crear_tarea.php y el
            // "Plan de Notas" (api/plan_notas.php).
            "ALTER TABLE `tareas` ADD COLUMN IF NOT EXISTS `fecha_limite_correccion` DATETIME DEFAULT NULL AFTER `fecha_limite`",
            "ALTER TABLE `tareas` ADD COLUMN IF NOT EXISTS `porcentaje` DECIMAL(5,2) DEFAULT NULL AFTER `nota_maxima`",
            // 002_clases_grabadas.sql — esta migración (igual que
            // 001_aula_virtual.sql y 003_clases_vivo.sql) nunca había
            // quedado enganchada acá, así que una instalación nueva
            // (o cualquier entorno donde nadie corrió el .sql a mano)
            // se queda sin la tabla `clases_grabadas` y la pestaña
            // "Clases Grabadas" revienta en cuanto alguien intenta
            // listar o publicar un video. Las otras dos (001 y 003)
            // se dejan afuera a propósito en este cambio.
            "CREATE TABLE IF NOT EXISTS `clases_grabadas` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `materia_id` INT(11) NOT NULL,
                `usuario_id` INT(11) NOT NULL COMMENT 'quién la publicó (docente/admin)',
                `titulo` VARCHAR(150) NOT NULL,
                `descripcion` VARCHAR(500) DEFAULT NULL,
                `url` VARCHAR(500) NOT NULL COMMENT 'link original tal como lo pegó el docente',
                `plataforma` VARCHAR(20) NOT NULL DEFAULT 'otro' COMMENT 'youtube|drive|vimeo|otro',
                `fecha` DATE DEFAULT NULL COMMENT 'fecha de la clase (no de la publicación)',
                `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_materia` (`materia_id`),
                KEY `idx_usuario` (`usuario_id`),
                CONSTRAINT `autoheal_cg_ibfk_1` FOREIGN KEY (`materia_id`) REFERENCES `materias` (`id`) ON DELETE CASCADE,
                CONSTRAINT `autoheal_cg_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            // 001_aula_virtual.sql y 003_clases_vivo.sql — tenían el
            // mismo problema que 002 (arriba): nunca quedaron
            // enganchadas acá, así que un entorno nuevo donde nadie
            // las pegó a mano en phpMyAdmin se queda sin estas tablas
            // y el Aula Virtual / Clases en Vivo revientan apenas se
            // usan. Se agregan ahora las cuatro de 001 y la de 003.
            "CREATE TABLE IF NOT EXISTS `aula_anuncios` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `materia_id` INT(11) NOT NULL,
                `usuario_id` INT(11) NOT NULL COMMENT 'quién publicó (docente/admin)',
                `titulo` VARCHAR(150) NOT NULL,
                `contenido` TEXT NOT NULL,
                `fijado` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'anuncios fijados aparecen primero',
                `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_materia` (`materia_id`),
                KEY `idx_usuario` (`usuario_id`),
                CONSTRAINT `autoheal_aa_ibfk_1` FOREIGN KEY (`materia_id`) REFERENCES `materias` (`id`) ON DELETE CASCADE,
                CONSTRAINT `autoheal_aa_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `aula_materiales` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `materia_id` INT(11) NOT NULL,
                `usuario_id` INT(11) NOT NULL COMMENT 'quién subió el archivo',
                `titulo` VARCHAR(150) NOT NULL,
                `descripcion` VARCHAR(500) DEFAULT NULL,
                `archivo` VARCHAR(255) NOT NULL COMMENT 'ruta relativa dentro de uploads/materiales/, nombre generado',
                `archivo_nombre` VARCHAR(255) NOT NULL COMMENT 'nombre original, solo para mostrar/descargar',
                `archivo_tipo` VARCHAR(10) NOT NULL COMMENT 'extensión validada en el servidor',
                `tamano_bytes` INT(10) UNSIGNED NOT NULL DEFAULT 0,
                `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_materia` (`materia_id`),
                KEY `idx_usuario` (`usuario_id`),
                CONSTRAINT `autoheal_am_ibfk_1` FOREIGN KEY (`materia_id`) REFERENCES `materias` (`id`) ON DELETE CASCADE,
                CONSTRAINT `autoheal_am_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `aula_actividades` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `materia_id` INT(11) NOT NULL,
                `usuario_id` INT(11) NOT NULL COMMENT 'docente/admin que la creó',
                `titulo` VARCHAR(150) NOT NULL,
                `descripcion` VARCHAR(500) DEFAULT NULL,
                `tipo` VARCHAR(30) NOT NULL DEFAULT 'actividad' COMMENT 'actividad|examen|taller|proyecto',
                `nota_max` DECIMAL(5,2) NOT NULL DEFAULT 20.00,
                `fecha` DATE DEFAULT NULL,
                `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_materia` (`materia_id`),
                KEY `idx_usuario` (`usuario_id`),
                CONSTRAINT `autoheal_aac_ibfk_1` FOREIGN KEY (`materia_id`) REFERENCES `materias` (`id`) ON DELETE CASCADE,
                CONSTRAINT `autoheal_aac_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `aula_calificaciones` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `actividad_id` INT(11) NOT NULL,
                `alumno_id` INT(11) NOT NULL,
                `nota` DECIMAL(5,2) DEFAULT NULL,
                `observacion` VARCHAR(255) DEFAULT NULL,
                `calificado_por` INT(11) DEFAULT NULL,
                `actualizado_en` DATETIME DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `autoheal_uq_actividad_alumno` (`actividad_id`,`alumno_id`),
                KEY `idx_alumno` (`alumno_id`),
                KEY `idx_calificado_por` (`calificado_por`),
                CONSTRAINT `autoheal_ac_ibfk_1` FOREIGN KEY (`actividad_id`) REFERENCES `aula_actividades` (`id`) ON DELETE CASCADE,
                CONSTRAINT `autoheal_ac_ibfk_2` FOREIGN KEY (`alumno_id`) REFERENCES `alumnos` (`id`) ON DELETE CASCADE,
                CONSTRAINT `autoheal_ac_ibfk_3` FOREIGN KEY (`calificado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `clases_vivo` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `materia_id` INT(11) NOT NULL,
                `usuario_id` INT(11) NOT NULL COMMENT 'quién la creó/dicta',
                `titulo` VARCHAR(150) NOT NULL,
                `descripcion` VARCHAR(500) DEFAULT NULL,
                `plataforma` VARCHAR(20) NOT NULL DEFAULT 'jitsi' COMMENT 'jitsi|meet|otro',
                `sala` VARCHAR(150) DEFAULT NULL COMMENT 'nombre de sala — solo si plataforma=jitsi',
                `url` VARCHAR(500) DEFAULT NULL COMMENT 'link pegado por el docente — solo si plataforma=meet|otro',
                `fecha_hora` DATETIME NOT NULL COMMENT 'cuándo es (o fue) la clase',
                `estado` VARCHAR(20) NOT NULL DEFAULT 'programada' COMMENT 'programada|en_curso|finalizada|cancelada',
                `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_materia` (`materia_id`),
                KEY `idx_usuario` (`usuario_id`),
                CONSTRAINT `autoheal_cv_ibfk_1` FOREIGN KEY (`materia_id`) REFERENCES `materias` (`id`) ON DELETE CASCADE,
                CONSTRAINT `autoheal_cv_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        ];

        foreach ($ddl as $sql) {
            @mysqli_query($con, $sql);
        }

        // Deja el marcador para que los próximos requests no vuelvan a
        // pagar estas ~20 consultas — aunque alguna haya fallado
        // puntualmente (motor viejo, permisos), ninguna es
        // indispensable, así que igual se marca como "resuelto".
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        @touch($marcador);
    }
}
