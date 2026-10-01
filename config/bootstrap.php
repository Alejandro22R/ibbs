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

// Si existe un .env en la raíz del proyecto (nunca se commitea — ver
// .gitignore), lo carga antes que nada para que esas variables ya
// estén disponibles para database.php y mailer.php.
require_once __DIR__ . '/env_loader.php';

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/rate_limit.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/audit.php';
require_once __DIR__ . '/materia_permisos.php';
require_once __DIR__ . '/asistencia_helpers.php';
require_once __DIR__ . '/url_validacion.php';
require_once __DIR__ . '/notificaciones.php';
require_once __DIR__ . '/password_policy.php';
require_once __DIR__ . '/ws_config.php';
require_once __DIR__ . '/ws_token.php';
require_once __DIR__ . '/ws_broadcast.php';
require_once __DIR__ . '/mailer.php';
