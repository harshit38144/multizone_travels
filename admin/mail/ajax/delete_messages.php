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

$ids = $_POST['ids'] ?? [];
if (!is_array($ids) || empty($ids)) {
    mailJson(false, 'No messages selected.');
}

$accId = (int) $account['id'];
$deleted = 0;
foreach ($ids as $rawId) {
    $id = (int) $rawId;
    if ($id > 0) {
        $conn->query("DELETE FROM mail_messages WHERE id = $id AND account_id = $accId");
        if ($conn->affected_rows > 0) {
            $deleted++;
        }
    }
}

mailJson(true, "Deleted $deleted message(s).", ['deleted' => $deleted]);
