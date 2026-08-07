<?php
/**
 * Faro — mailer. Usa SMTP se configurato (FARO_SMTP_HOST), altrimenti mail() di PHP.
 * Predisposto: basta compilare le chiavi SMTP nel .env per passare a SMTP, zero codice.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';

/** Subject RFC 2047 (UTF-8) per non rompere gli accenti. */
function faro_mime_subject(string $s): string
{
    return '=?UTF-8?B?' . base64_encode($s) . '?=';
}

/** Invia una mail HTML. Ritorna [bool ok, string info]. */
function faro_send_mail(string $to, string $subject, string $html): array
{
    if (FARO_SMTP_HOST !== '') {
        return faro_smtp_send($to, $subject, $html);
    }
    // Fallback: mail() di PHP.
    $headers  = 'From: Faro <' . FARO_MAIL_FROM . ">\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=utf-8\r\n";
    $ok = @mail($to, faro_mime_subject($subject), $html, $headers, '-f' . FARO_MAIL_FROM);
    return [$ok, $ok ? 'inviata con mail()' : 'mail() ha restituito false'];
}

/** Client SMTP minimale (STARTTLS + AUTH LOGIN). */
function faro_smtp_send(string $to, string $subject, string $html): array
{
    $host = FARO_SMTP_HOST;
    $port = FARO_SMTP_PORT;
    $secure = FARO_SMTP_SECURE;
    $remote = ($secure === 'ssl' ? 'ssl://' : '') . $host;

    $fp = @fsockopen($remote, $port, $errno, $errstr, 15);
    if (!$fp) {
        return [false, "connessione SMTP fallita: $errstr ($errno)"];
    }
    $read = function () use ($fp): string {
        $data = '';
        while (($line = fgets($fp, 515)) !== false) {
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        return $data;
    };
    $cmd = function (string $c) use ($fp, $read): string {
        fwrite($fp, $c . "\r\n");
        return $read();
    };

    $ehlo = 'EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'faro.local');
    $read();
    $cmd($ehlo);
    if ($secure === 'tls') {
        $r = $cmd('STARTTLS');
        if ((int) $r !== 220) { fclose($fp); return [false, "STARTTLS rifiutato: $r"]; }
        stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        $cmd($ehlo);
    }
    if (FARO_SMTP_USER !== '') {
        $cmd('AUTH LOGIN');
        $cmd(base64_encode(FARO_SMTP_USER));
        $r = $cmd(base64_encode(FARO_SMTP_PASS));
        if ((int) $r !== 235) { fclose($fp); return [false, "AUTH fallita: $r"]; }
    }
    $cmd('MAIL FROM:<' . FARO_MAIL_FROM . '>');
    $cmd('RCPT TO:<' . $to . '>');
    $r = $cmd('DATA');
    if ((int) $r !== 354) { fclose($fp); return [false, "DATA rifiutato: $r"]; }

    $body  = 'From: Faro <' . FARO_MAIL_FROM . ">\r\n";
    $body .= 'To: <' . $to . ">\r\n";
    $body .= 'Subject: ' . faro_mime_subject($subject) . "\r\n";
    $body .= "MIME-Version: 1.0\r\n";
    $body .= "Content-Type: text/html; charset=utf-8\r\n\r\n";
    $body .= str_replace("\n.", "\n..", $html) . "\r\n.";
    $r = $cmd($body);
    $cmd('QUIT');
    fclose($fp);
    return [(int) $r === 250, "SMTP: $r"];
}
