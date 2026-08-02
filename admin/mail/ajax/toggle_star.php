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
$account = mailGetAccount($conn, $adminId);
if (!$account) {
    mailJson(false, 'No mail account.');
}

$id = (int) ($_POST['id'] ?? 0);
$accId = (int) $account['id'];

$res = $conn->query("SELECT is_starred FROM mail_messages WHERE id = $id AND account_id = $accId LIMIT 1");
if (!$res || $res->num_rows === 0) {
    mailJson(false, 'Message not found.');
}

$row = $res->fetch_assoc();
$newStar = empty($row['is_starred']) ? 1 : 0;
$conn->query("UPDATE mail_messages SET is_starred = $newStar WHERE id = $id AND account_id = $accId");

mailJson(true, 'Updated.', ['starred' => (bool) $newStar]);
