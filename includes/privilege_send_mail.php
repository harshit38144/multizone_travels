<?php
/**
 * Send HTML email for privilege portal (OTP). Same SMTP pattern as ajax/enquiry-submit.php.
 *
 * Credentials (in order):
 * 1. Env: MULTIZONE_SMTP_USER, MULTIZONE_SMTP_PASS, MULTIZONE_SMTP_FROM
 * 2. Local file: includes/mail_secrets.local.php (gitignored)
 */
require_once dirname(__DIR__) . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

function privilege_portal_send_mail(string $toAddress, string $subject, string $htmlBody, string $altBody): bool
{
    $smtpUser = getenv('MULTIZONE_SMTP_USER') ?: '';
    $smtpPass = getenv('MULTIZONE_SMTP_PASS') ?: '';
    $fromEmail = getenv('MULTIZONE_SMTP_FROM') ?: '';

    $localPath = __DIR__ . '/mail_secrets.local.php';
    if (is_readable($localPath)) {
        $cfg = require $localPath;
        if (is_array($cfg)) {
            if ($smtpUser === '' && !empty($cfg['smtp_user'])) {
                $smtpUser = trim((string) $cfg['smtp_user']);
            }
            if ($smtpPass === '' && !empty($cfg['smtp_pass'])) {
                $smtpPass = trim((string) $cfg['smtp_pass']);
            }
            if ($fromEmail === '' && !empty($cfg['smtp_from'])) {
                $fromEmail = trim((string) $cfg['smtp_from']);
            }
        }
    }

    if ($fromEmail === '') {
        $fromEmail = $smtpUser;
    }
    $fromName = 'Multizone Travels';

    if ($smtpUser === '' || $smtpPass === '' || $fromEmail === '') {
        @file_put_contents(
            dirname(__DIR__) . '/ajax/privilege_otp_mail_log.txt',
            date('c') . " SMTP not configured (set env or includes/mail_secrets.local.php)\n",
            FILE_APPEND
        );
        return false;
    }

    $mail = new PHPMailer(true);
    try {
        $mail->SMTPDebug = SMTP::DEBUG_OFF;
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = $smtpUser;
        $mail->Password = $smtpPass;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = 465;
        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($toAddress);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;
        $mail->AltBody = $altBody;
        $mail->send();
        return true;
    } catch (Exception $e) {
        @file_put_contents(
            dirname(__DIR__) . '/ajax/privilege_otp_mail_log.txt',
            date('c') . ' ' . $mail->ErrorInfo . "\n",
            FILE_APPEND
        );
        return false;
    }
}
