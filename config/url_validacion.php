<?php
/**
 * IBBS — Validación de URLs externas pegadas por el usuario (links de
 * video, videollamadas...).
 *
 * Regla de oro: para decidir si un link "es de tal plataforma" siempre
 * se compara el host EXACTO (parse_url + in_array estricto), nunca por
 * coincidencia de substring — "meet.google.com.evil.com" o
 * "fakeyoutube.com" contienen el nombre del dominio real como texto,
 * pero no son ese dominio.
 */

if (!function_exists('url_es_valida')) {
    /** true si $url es una URL bien formada con esquema http o https. */
    function url_es_valida($url) {
        if (!filter_var($url, FILTER_VALIDATE_URL)) return false;
        $scheme = strtolower(parse_url($url, PHP_URL_SCHEME) ?: '');
        return in_array($scheme, ['http', 'https'], true);
    }
}

if (!function_exists('url_host')) {
    function url_host($url) {
        return strtolower(parse_url($url, PHP_URL_HOST) ?: '');
    }
}

if (!function_exists('url_host_es')) {
    /** true si el host de $url coincide EXACTAMENTE con alguno de $dominios. */
    function url_host_es($url, array $dominios) {
        return in_array(url_host($url), $dominios, true);
    }
}
