<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/admin_ui_settings.php';

header('Content-Type: application/json; charset=utf-8');

function leadsColumnSettingsJson($success, $message, $extra = [])
{
    echo json_encode(array_merge([
        'success' => (bool) $success,
        'message' => (string) $message,
    ], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    leadsColumnSettingsJson(false, 'Invalid request method.');
}

$adminId = isset($_SESSION['id']) ? (int) $_SESSION['id'] : 0;
if ($adminId <= 0) {
    leadsColumnSettingsJson(false, 'Session expired. Please sign in again.');
}

$raw = $_POST['visibility'] ?? null;
if (is_string($raw)) {
    $decoded = json_decode($raw, true);
    $raw = is_array($decoded) ? $decoded : null;
}

if (!is_array($raw)) {
    leadsColumnSettingsJson(false, 'Invalid column visibility payload.');
}

$visibility = crmLeadsSaveColumnVisibility($conn, $adminId, $raw);
leadsColumnSettingsJson(true, 'Column settings saved.', ['visibility' => $visibility]);
