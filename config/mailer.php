<?php
/**
 * IBBS — Envío de correo saliente, sin depender de ninguna librería
 * externa (no hay composer/vendor en este proyecto — ver README).
 *
 * ibbs_send_mail() es el único punto de entrada: arma un correo HTML
 * simple y lo manda por SMTP si config/mail_config.php tiene un host
 * configurado, o por la función mail() de PHP si no. Cualquier error
 * de envío queda registrado en el log del servidor — nunca debe tumbar
 * la página que lo llama (una recuperación de contraseña no debe
 * fallar con un error 500 solo porque el correo no salió).
 */

if (!function_exists('ibbs_mail_config')) {
    function ibbs_mail_config() {
        static $cfg = null;
        if ($cfg === null) {
            $cfg = @include __DIR__ . '/mail_config.php';
            if (!is_array($cfg)) $cfg = [];
        }
        return $cfg;
    }
}

if (!function_exists('ibbs_send_mail')) {
    /**
     * @param string $to        correo del destinatario
     * @param string $subject   asunto (texto plano)
     * @param string $htmlBody  cuerpo en HTML
     * @return bool             true si el servidor de correo aceptó el mensaje
     */
    function ibbs_send_mail($to, $subject, $htmlBody) {
        $cfg = ibbs_mail_config();
        try {
            if (!empty($cfg['smtp_host'])) {
                return ibbs_smtp_send($cfg, $to, $subject, $htmlBody);
            }
            return ibbs_mail_send_native($cfg, $to, $subject, $htmlBody);
        } catch (\Throwable $e) {
            error_log('IBBS mailer error: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('ibbs_mail_send_native')) {
    function ibbs_mail_send_native($cfg, $to, $subject, $htmlBody) {
        $fromEmail = $cfg['from_email'] ?? 'no-responder@ibbs.local';
        $fromName  = $cfg['from_name'] ?? 'IBBS';
        $headers   = "MIME-Version: 1.0\r\n"
                   . "Content-Type: text/html; charset=UTF-8\r\n"
                   . "From: {$fromName} <{$fromEmail}>\r\n";
        $subjectEnc = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        return @mail($to, $subjectEnc, $htmlBody, $headers);
    }
}

if (!function_exists('ibbs_smtp_send')) {
    /**
     * Cliente SMTP mínimo (EHLO/STARTTLS/AUTH LOGIN/DATA) por socket
     * plano — sin dependencias, para no necesitar composer en un
     * proyecto que nunca lo tuvo. Soporta STARTTLS (puerto 587,
     * smtp_secure='tls') y TLS directo (puerto 465, smtp_secure='ssl').
     */
    function ibbs_smtp_send($cfg, $to, $subject, $htmlBody) {
        $host   = $cfg['smtp_host'];
        $port   = (int)($cfg['smtp_port'] ?? 587);
        $secure = $cfg['smtp_secure'] ?? 'tls';
        $user   = $cfg['smtp_user'] ?? '';
        $pass   = $cfg['smtp_pass'] ?? '';
        $fromEmail = $cfg['from_email'] ?? $user;
        $fromName  = $cfg['from_name'] ?? 'IBBS';

        $transport = $secure === 'ssl' ? 'ssl://' : '';
        $sock = @stream_socket_client("{$transport}{$host}:{$port}", $errno, $errstr, 12);
        if (!$sock) { error_log("IBBS SMTP: no se pudo conectar a $host:$port — $errstr"); return false; }
        stream_set_timeout($sock, 12);

        $read = function () use ($sock) {
            $data = '';
            while (($line = fgets($sock, 515)) !== false) {
                $data .= $line;
                if (isset($line[3]) && $line[3] === ' ') break;
            }
            return $data;
        };
        $write = function ($cmd) use ($sock) { fwrite($sock, $cmd . "\r\n"); };
        $expect = function ($code) use ($read) {
            $resp = $read();
            return $resp !== '' && substr($resp, 0, 3) === (string)$code;
        };

        if (!$expect(220)) { fclose($sock); return false; }
        $write('EHLO ibbs.local'); if (!$expect(250)) { fclose($sock); return false; }

        if ($secure === 'tls') {
            $write('STARTTLS');
            if (!$expect(220)) { fclose($sock); return false; }
            if (!@stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) { fclose($sock); return false; }
            $write('EHLO ibbs.local'); if (!$expect(250)) { fclose($sock); return false; }
        }

        if ($user !== '') {
            $write('AUTH LOGIN'); if (!$expect(334)) { fclose($sock); return false; }
            $write(base64_encode($user)); if (!$expect(334)) { fclose($sock); return false; }
            $write(base64_encode($pass)); if (!$expect(235)) { fclose($sock); return false; }
        }

        $write('MAIL FROM:<' . $fromEmail . '>'); if (!$expect(250)) { fclose($sock); return false; }
        $write('RCPT TO:<' . $to . '>'); if (!$expect(250)) { fclose($sock); return false; }
        $write('DATA'); if (!$expect(354)) { fclose($sock); return false; }

        $subjectEnc = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $body = "From: {$fromName} <{$fromEmail}>\r\n"
              . "To: <{$to}>\r\n"
              . "Subject: {$subjectEnc}\r\n"
              . "MIME-Version: 1.0\r\n"
              . "Content-Type: text/html; charset=UTF-8\r\n"
              . "\r\n"
              . str_replace("\n.", "\n..", $htmlBody) // "dot stuffing" — una línea que empieza con "." termina el mensaje SMTP antes de tiempo
              . "\r\n.";
        $write($body);
        $ok = $expect(250);
        $write('QUIT');
        fclose($sock);
        return $ok;
    }
}
