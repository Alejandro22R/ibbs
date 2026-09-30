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

define('IBBS_SCHEMA_VERSION', 13);

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
