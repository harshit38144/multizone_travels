<?php
require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

function mailJson($ok, $msg, $extra = [])
{
    echo json_encode(array_merge(['success' => (bool) $ok, 'message' => (string) $msg], $extra));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    mailJson(false, 'Invalid request.');
}

$adminId = mailGetAdminId();
$senderId = (int) ($_POST['sender_id'] ?? 0);

$toAddr = trim($_POST['to'] ?? '');
$subj = trim($_POST['subject'] ?? '');
$body = (string) ($_POST['body'] ?? '');
$cc = trim($_POST['cc'] ?? '');
$bcc = trim($_POST['bcc'] ?? '');
$replyTo = trim($_POST['reply_to'] ?? '');

if ($toAddr === '' || $subj === '') {
    mailJson(false, 'To and Subject are required.');
}

mailSeedSmtpMasterFromLegacy($conn);

$resolved = mailResolveSendSmtp($conn, $senderId, $adminId);
if (!$resolved || empty($resolved['config']['host'])) {
    mailJson(false, 'No SMTP configuration found. Select a sender or configure Email Master.');
}

$smtpCfg = $resolved['config'];
$account = mailGetAccount($conn, $adminId);

if ($replyTo === '') {
    $replyTo = (string) ($smtpCfg['from_email'] ?? '');
}

require_once dirname(__DIR__, 3) . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

$mail = new PHPMailer(true);
try {
    mailApplySmtp($mail, $smtpCfg);
    $mail->addAddress($toAddr);
    if ($replyTo !== '') {
        $mail->addReplyTo($replyTo);
    }
    if ($cc !== '') {
        foreach (array_map('trim', explode(',', $cc)) as $addr) {
            if ($addr !== '') {
                $mail->addCC($addr);
            }
        }
    }
    if ($bcc !== '') {
        foreach (array_map('trim', explode(',', $bcc)) as $addr) {
            if ($addr !== '') {
                $mail->addBCC($addr);
            }
        }
    }
    if (!empty($_FILES['attachment']['tmp_name']) && is_uploaded_file($_FILES['attachment']['tmp_name'])) {
        $mail->addAttachment($_FILES['attachment']['tmp_name'], $_FILES['attachment']['name'] ?? 'attachment');
    }
    $mail->isHTML(true);
    $mail->Subject = $subj;
    $mail->Body = $body !== '' ? $body : '<p></p>';
    $mail->AltBody = strip_tags($body);
    $mail->send();
} catch (MailException $e) {
    mailJson(false, $mail->ErrorInfo ?: $e->getMessage());
}

if ($account) {
    $stmt = $conn->prepare(
        'INSERT INTO mail_messages
        (account_id, folder, message_uid, subject, from_email, from_name, to_json, body_html, is_read, sent_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())'
    );
    if ($stmt) {
        $folder = 'Sent';
        $uid = 'local_' . time() . '_' . mt_rand(1000, 9999);
        $fromEmail = $smtpCfg['from_email'];
        $fromName = $smtpCfg['from_name'];
        $toJson = json_encode([['email' => $toAddr]]);
        $accId = (int) $account['id'];
        $stmt->bind_param('isssssss', $accId, $folder, $uid, $subj, $fromEmail, $fromName, $toJson, $body);
        $stmt->execute();
        $stmt->close();
    }
}

mailJson(true, 'Email sent successfully.');
