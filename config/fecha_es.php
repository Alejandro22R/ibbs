<?php
/**
 * IBBS — Fechas en español.
 *
 * date() de PHP con el formato 'M'/'F' (nombre/abreviatura del mes)
 * siempre devuelve el nombre en INGLÉS (Jan, Feb, Aug, Dec...),
 * porque no respeta el idioma del sistema — eso dejaba fechas como
 * "15 Jan 2026" sueltas en medio de una interfaz 100% en español.
 * Esta función hace lo mismo que date() pero traduce el mes.
 */

if (!function_exists('fecha_es')) {
    function fecha_es($formato, $timestamp = null) {
        if ($timestamp === null) $timestamp = time();
        $meses = [
            'Jan' => 'Ene', 'Feb' => 'Feb', 'Mar' => 'Mar', 'Apr' => 'Abr',
            'May' => 'May', 'Jun' => 'Jun', 'Jul' => 'Jul', 'Aug' => 'Ago',
            'Sep' => 'Sep', 'Oct' => 'Oct', 'Nov' => 'Nov', 'Dec' => 'Dic',
            'January' => 'enero', 'February' => 'febrero', 'March' => 'marzo',
            'April' => 'abril', 'June' => 'junio', 'July' => 'julio',
            'August' => 'agosto', 'September' => 'septiembre', 'October' => 'octubre',
            'November' => 'noviembre', 'December' => 'diciembre',
        ];
        return strtr(date($formato, $timestamp), $meses);
    }
}
