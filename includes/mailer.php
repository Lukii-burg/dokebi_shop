<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

/**
 * Send an application email using PHPMailer.
 *
 * Configure via environment variables:
 *   SMTP_HOST, SMTP_PORT (default 587), SMTP_USER, SMTP_PASS,
 *   SMTP_FROM (email), SMTP_FROM_NAME, SMTP_ENCRYPTION (tls|ssl|starttls).
 */
function send_app_mail(string $to, string $subject, string $htmlBody, string $textBody = ''): array {
    $fileCfg = [];
    $cfgPath = __DIR__ . '/mail_config.php';
    if (file_exists($cfgPath)) {
        $fileCfg = include $cfgPath;
    }
    $cfg = [
        'host'        => getenv('SMTP_HOST') ?: '',
        'port'        => getenv('SMTP_PORT') ?: 587,
        'user'        => getenv('SMTP_USER') ?: '',
        'pass'        => getenv('SMTP_PASS') ?: '',
        'from'        => getenv('SMTP_FROM') ?: 'no-reply@localhost',
        'from_name'   => getenv('SMTP_FROM_NAME') ?: 'Dokebi Family',
        'encryption'  => strtolower(getenv('SMTP_ENCRYPTION') ?: 'tls'),
    ];
    if ($fileCfg) {
        $cfg = array_merge($cfg, $fileCfg);
    }

    $alt = $textBody !== '' ? $textBody : strip_tags($htmlBody);

    $mail = new PHPMailer(true);
    try {
        $mail->CharSet = 'UTF-8';
        if ($cfg['host']) {
            $mail->isSMTP();
            $mail->Host       = $cfg['host'];
            $mail->Port       = (int)$cfg['port'];
            $mail->SMTPAuth   = $cfg['user'] !== '';
            $mail->Username   = $cfg['user'];
            $mail->Password   = $cfg['pass'];
            if (in_array($cfg['encryption'], ['ssl','tls','starttls'], true)) {
                $mail->SMTPSecure = $cfg['encryption'] === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
            }
        } else {
            $mail->isMail(); // fallback to local mail transport
        }

        $mail->setFrom($cfg['from'], $cfg['from_name']);
        $mail->addAddress($to);
        $mail->Subject = $subject;
        $mail->isHTML(true);
        $mail->Body    = $htmlBody;
        $mail->AltBody = $alt;
        $mail->send();

        return ['sent' => true, 'error' => null];
    } catch (Exception $e) {
        return ['sent' => false, 'error' => $mail->ErrorInfo ?: $e->getMessage()];
    }
}
