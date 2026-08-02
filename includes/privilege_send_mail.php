<?php
/**
 * Send HTML email for privilege portal (OTP). Same SMTP pattern as ajax/enquiry-submit.php.
 * Override with env: MULTIZONE_SMTP_USER, MULTIZONE_SMTP_PASS, MULTIZONE_SMTP_FROM.
 */
require_once dirname(__DIR__) . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

function privilege_portal_send_mail(string $toAddress, string $subject, string $htmlBody, string $altBody): bool
{
    $smtpUser = getenv('MULTIZONE_SMTP_USER') ?: 'cliffmediarnc@gmail.com';
    $smtpPass = getenv('MULTIZONE_SMTP_PASS') ?: 'rjur diwp ssla ujhl';
    $fromEmail = getenv('MULTIZONE_SMTP_FROM') ?: $smtpUser;
    $fromName = 'Multizone Travels';

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
