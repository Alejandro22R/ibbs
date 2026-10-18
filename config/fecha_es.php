<?php
/**
 * IBBS — Fechas en español.
 *
 * date() de PHP con el formato 'M'/'F' (nombre/abreviatura del mes)
 * siempre devuelve el nombre en INGLÉS (Jan, Feb, Aug, Dec...),
 * porque no respeta el idioma del sistema — eso dejaba fechas como
 * "15 Jan 2026" sueltas en medio de una interfaz 100% en español.
 * Esta función hace lo mismo que date() pero traduce el mes y, cuando el
 * formato lo pide ('l' día completo, 'D' abreviado), también el día de
 * la semana — por ejemplo evita que se vea "Saturday, 17 de October de
 * 2026" en medio de una pantalla en español.
 */

if (!function_exists('fecha_es')) {
    function fecha_es($formato, $timestamp = null) {
        if ($timestamp === null) $timestamp = time();
        $texto = [
            // Meses — abreviatura (M) primero para que no choque con el nombre completo (F)
            'Jan' => 'Ene', 'Feb' => 'Feb', 'Mar' => 'Mar', 'Apr' => 'Abr',
            'May' => 'May', 'Jun' => 'Jun', 'Jul' => 'Jul', 'Aug' => 'Ago',
            'Sep' => 'Sep', 'Oct' => 'Oct', 'Nov' => 'Nov', 'Dec' => 'Dic',
            'January' => 'enero', 'February' => 'febrero', 'March' => 'marzo',
            'April' => 'abril', 'June' => 'junio', 'July' => 'julio',
            'August' => 'agosto', 'September' => 'septiembre', 'October' => 'octubre',
            'November' => 'noviembre', 'December' => 'diciembre',
            // Días de la semana — nombre completo (l) y abreviado (D)
            'Monday' => 'Lunes', 'Tuesday' => 'Martes', 'Wednesday' => 'Miércoles',
            'Thursday' => 'Jueves', 'Friday' => 'Viernes', 'Saturday' => 'Sábado',
            'Sunday' => 'Domingo',
            'Mon' => 'Lun', 'Tue' => 'Mar', 'Wed' => 'Mié', 'Thu' => 'Jue',
            'Fri' => 'Vie', 'Sat' => 'Sáb', 'Sun' => 'Dom',
        ];
        return strtr(date($formato, $timestamp), $texto);
    }
}
