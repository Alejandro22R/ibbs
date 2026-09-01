<?php
/**
 * IBBS — Punto único de arranque para páginas y endpoints.
 * Reemplaza el viejo `session_start();` suelto: deja la sesión
 * configurada de forma segura, carga los helpers de CSRF, el freno
 * de fuerza bruta y la conexión a base de datos.
 */

// No mostrar detalles de errores/rutas del servidor al usuario final;
// que queden en el log del servidor en su lugar.
ini_set('display_errors', '0');
ini_set('log_errors', '1');

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/rate_limit.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/audit.php';
require_once __DIR__ . '/materia_permisos.php';
require_once __DIR__ . '/url_validacion.php';
