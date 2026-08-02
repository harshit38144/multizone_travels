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

@set_time_limit(300);

try {
    if (!class_exists(\Webklex\PHPIMAP\ClientManager::class)) {
        mailJson(false, 'IMAP library missing. Run: composer require webklex/php-imap');
    }

    $adminId = mailGetAdminId();
    $account = mailGetAccount($conn, $adminId);
    if (!$account || ($account['imap_status'] ?? '') !== 'active') {
        mailJson(false, 'Active IMAP account required. Enable IMAP in Email Configuration.');
    }

    mailReleaseSession();

    $syncAll = !empty($_POST['sync_all']) || (($_POST['mode'] ?? '') === 'all');
    $folder = trim($_POST['folder'] ?? 'INBOX');
    $limit = max(10, min(100, (int) ($_POST['limit'] ?? 30)));

    $result = $syncAll
        ? mailSyncAllFolders($conn, $account, $limit)
        : mailSyncFolder($conn, $account, $folder, $limit);
    mailJson($result['ok'], $result['message'], [
        'synced' => $result['synced'] ?? 0,
        'folders' => $result['folders'] ?? [],
    ]);
} catch (Throwable $e) {
    mailJson(false, 'Sync error: ' . $e->getMessage());
}
