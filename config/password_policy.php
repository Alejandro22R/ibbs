<?php
/**
 * IBBS — Política de contraseñas, compartida por todo lo que cree o
 * cambie una contraseña (registro público, admin creando un docente,
 * "cambiar mi contraseña", recuperar contraseña). Antes vivía duplicada
 * en login.php y modulo_perfil.php (cada uno con su propia copia en JS
 * y ninguna copia en PHP para el lado del servidor de creación de
 * docentes) — ahora hay una sola fuente de verdad del lado del server.
 */

if (!function_exists('ibbs_validar_password')) {
    function ibbs_validar_password($pwd) {
        if (strlen($pwd) < 8) return 'La contraseña debe tener al menos 8 caracteres.';
        if (!preg_match('/[A-Z]/', $pwd)) return 'Debe contener al menos una mayúscula.';
        if (!preg_match('/[a-z]/', $pwd)) return 'Debe contener al menos una minúscula.';
        if (!preg_match('/[0-9!@#$%^&*()\_+\-=\[\]{};\':",.<>?\/|`~]/', $pwd)) return 'Debe contener al menos un número o carácter especial.';
        return null;
    }
}
