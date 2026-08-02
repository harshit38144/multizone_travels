<?php
require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

function mailJson($ok, $msg, $extra = [])
{
    echo json_encode(array_merge(['success' => (bool) $ok, 'message' => (string) $msg], $extra));
    exit;
}

$adminId = mailGetAdminId();
$account = mailGetAccount($conn, $adminId);
if (!$account) {
    mailJson(false, 'No mail account configured.');
}

$id = (int) ($_GET['id'] ?? 0);
$row = mailGetMessage($conn, $id, (int) $account['id']);
if (!$row) {
    mailJson(false, 'Message not found.');
}

$conn->query('UPDATE mail_messages SET is_read = 1 WHERE id = ' . $id);

mailReleaseSession();

$body = mailFetchMessageBody($conn, $account, $row);
if (!$body['ok']) {
    mailJson(false, $body['message']);
}

mailJson(true, 'OK', [
    'message' => [
        'id' => (int) $row['id'],
        'subject' => $row['subject'] ?? '',
        'from_email' => $row['from_email'] ?? '',
        'from_name' => $row['from_name'] ?? '',
        'body_text' => $body['body_text'] ?? '',
        'body_html' => $body['body_html'] ?? '',
        'sent_at' => $row['sent_at'] ?? '',
    ],
]);
