<?php
/**
 * IBBS — Conexión centralizada a la base de datos.
 *
 * Antes esta misma función (con las credenciales repetidas) vivía
 * duplicada en 5 archivos distintos. Ahora vive en un solo lugar,
 * lo que facilita cambiar de entorno (producción, otro equipo, etc.)
 * sin tocar el código: basta con definir variables de entorno.
 *
 * Variables de entorno soportadas (todas opcionales — si no existen
 * se usan los valores por defecto de desarrollo local XAMPP/WAMP):
 *   IBBS_DB_HOST, IBBS_DB_USER, IBBS_DB_PASS, IBBS_DB_NAME
 */

// Restaura el comportamiento clásico de mysqli (devolver false en vez de
// lanzar una excepción) para que los `if (!$con)` de todo el proyecto
// sigan funcionando y no se filtren rutas/detalles internos en un error
// no controlado.
mysqli_report(MYSQLI_REPORT_OFF);

require_once __DIR__.'/schema_autoheal.php';

if (!function_exists('db')) {
    function db() {
        $host = getenv('IBBS_DB_HOST') ?: 'localhost';
        $user = getenv('IBBS_DB_USER') ?: 'root';
        $pass = getenv('IBBS_DB_PASS') ?: '';
        $name = getenv('IBBS_DB_NAME') ?: 'ibbs';

        $c = mysqli_connect($host, $user, $pass, $name);
        if (!$c) return false;
        mysqli_set_charset($c, 'utf8mb4');
        // Autorepara columnas/tablas de migraciones que nadie corrió a
        // mano (ver config/schema_autoheal.php) — corre una sola vez
        // por request, así que el login (y todo lo demás) nunca vuelve
        // a quedarse colgado por una migración pendiente.
        ibbs_autoheal_schema($c);
        return $c;
    }
}
