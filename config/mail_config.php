<?php
/**
 * IBBS — Configuración de correo saliente.
 *
 * Usado por config/mailer.php para la recuperación de contraseña por
 * correo (login.php, acción rec_email_solicitar) y cualquier otro
 * aviso por email que se agregue más adelante.
 *
 * Por defecto queda todo vacío: config/mailer.php cae automáticamente
 * a la función mail() de PHP, que funciona sola si el servidor (el
 * VPS) ya tiene un MTA local configurado (Postfix/sendmail) — lo
 * normal en la mayoría de hostings compartidos y VPS con panel
 * (cPanel, Plesk, etc.).
 *
 * Si el correo no llega (común en un VPS nuevo sin MTA propio, o si
 * cae a la carpeta de spam), completá estos datos con una cuenta SMTP
 * real — Gmail con una "contraseña de aplicación" (no la contraseña
 * normal de la cuenta), SendGrid, Mailgun, o el correo de tu propio
 * dominio — y el sistema va a usar SMTP automáticamente, sin tocar
 * ningún otro archivo del proyecto.
 */

return [
    // Dejar todo esto vacío para usar mail() de PHP.
    'smtp_host'   => '',            // ej. 'smtp.gmail.com'
    'smtp_port'   => 587,           // 587 (STARTTLS) o 465 (TLS directo)
    'smtp_secure' => 'tls',         // 'tls' (STARTTLS, puerto 587) o 'ssl' (TLS directo, puerto 465)
    'smtp_user'   => '',            // ej. 'notificaciones@tuinstituto.com'
    'smtp_pass'   => '',            // contraseña de aplicación / API key

    // Remitente que ve quien recibe el correo (con SMTP o con mail()).
    'from_email'  => 'no-responder@ibbs.local',
    'from_name'   => 'IBBS — Instituto Bíblico Bautista del Sur',
];
