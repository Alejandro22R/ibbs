<?php
/**
 * IBBS — Configuración de correo saliente.
 *
 * Usado por config/mailer.php para la recuperación de contraseña por
 * correo (login.php, acción rec_email_solicitar) y cualquier otro
 * aviso por email que se agregue más adelante.
 *
 * Igual que config/database.php con la base de datos: los valores
 * reales se leen de variables de entorno, nunca se escriben acá — así
 * ninguna contraseña real termina commiteada en el repositorio. La
 * forma más simple de definirlas es un archivo `.env` en la raíz del
 * proyecto (ver `.env.example`), que config/env_loader.php carga solo
 * si existe; nunca se sube a git (está en .gitignore).
 *
 * Variables soportadas (todas opcionales):
 *   IBBS_SMTP_HOST, IBBS_SMTP_PORT, IBBS_SMTP_SECURE,
 *   IBBS_SMTP_USER, IBBS_SMTP_PASS, IBBS_MAIL_FROM, IBBS_MAIL_FROM_NAME
 *
 * Si IBBS_SMTP_HOST queda vacío, config/mailer.php cae automáticamente
 * a la función mail() de PHP, que funciona sola si el servidor (el
 * VPS) ya tiene un MTA local configurado (Postfix/sendmail).
 */

return [
    'smtp_host'   => getenv('IBBS_SMTP_HOST') ?: '',             // ej. 'smtp.gmail.com'
    'smtp_port'   => (int)(getenv('IBBS_SMTP_PORT') ?: 587),     // 587 (STARTTLS) o 465 (TLS directo)
    'smtp_secure' => getenv('IBBS_SMTP_SECURE') ?: 'tls',        // 'tls' (puerto 587) o 'ssl' (puerto 465)
    'smtp_user'   => getenv('IBBS_SMTP_USER') ?: '',             // ej. 'notificaciones@tuinstituto.com'
    'smtp_pass'   => getenv('IBBS_SMTP_PASS') ?: '',             // contraseña de aplicación / API key

    // Remitente que ve quien recibe el correo (con SMTP o con mail()).
    'from_email'  => getenv('IBBS_MAIL_FROM') ?: 'no-responder@ibbs.local',
    'from_name'   => getenv('IBBS_MAIL_FROM_NAME') ?: 'IBBS — Instituto Bíblico Bautista del Sur',
];
