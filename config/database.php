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

if (!function_exists('db')) {
    function db() {
        $host = getenv('IBBS_DB_HOST') ?: 'localhost';
        $user = getenv('IBBS_DB_USER') ?: 'root';
        $pass = getenv('IBBS_DB_PASS') ?: '';
        $name = getenv('IBBS_DB_NAME') ?: 'ibbs';

        $c = mysqli_connect($host, $user, $pass, $name);
        if (!$c) return false;
        mysqli_set_charset($c, 'utf8mb4');
        return $c;
    }
}
