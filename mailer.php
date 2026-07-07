<?php
// mailer.php - envío de correos vía SMTP (PHPMailer) usando mail_config.php
require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

/**
 * Envía un correo HTML. Devuelve true si se envió correctamente, false si falló
 * (el error se registra con error_log, nunca se lanza al llamador).
 */
function enviar_correo(string $destinatarioEmail, string $asunto, string $cuerpoHtml): bool {
    $config = require __DIR__ . '/mail_config.php';

    if (empty($config['username']) || empty($config['password'])) {
        error_log('enviar_correo: SMTP no configurado (username/password vacíos en mail_config.php).');
        return false;
    }

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = $config['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $config['username'];
        $mail->Password   = $config['password'];
        $mail->Port       = $config['port'];
        // Si el puerto está bloqueado por firewall/ISP, que falle rápido en vez
        // de colgarse hasta el max_execution_time de PHP (nos pasó con el 2525).
        $mail->Timeout    = 15;
        if ($config['encryption'] === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } elseif ($config['encryption'] === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        }
        $mail->CharSet = 'UTF-8';

        $mail->setFrom($config['from_email'], $config['from_name']);
        $mail->addAddress($destinatarioEmail);

        $mail->isHTML(true);
        $mail->Subject = $asunto;
        $mail->Body    = $cuerpoHtml;
        $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $cuerpoHtml));

        $mail->send();
        return true;
    } catch (PHPMailerException $e) {
        error_log('enviar_correo: fallo al enviar a ' . $destinatarioEmail . ': ' . $mail->ErrorInfo);
        return false;
    }
}
