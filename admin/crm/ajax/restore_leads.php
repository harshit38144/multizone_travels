<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/lead_db.php';

header('Content-Type: application/json; charset=utf-8');

function restoreLeadJson($success, $message, $extra = [])
{
    echo json_encode(array_merge([
        'success' => (bool) $success,
        'message' => (string) $message,
    ], $extra));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    restoreLeadJson(false, 'Invalid request method.');
}

$leadIds = $_POST['lead_ids'] ?? [];
if (!is_array($leadIds)) {
    $leadIds = [$leadIds];
}

$result = crmLeadBulkRestore($conn, $leadIds);

if (empty($result['success'])) {
    restoreLeadJson(false, (string) ($result['message'] ?? 'Could not restore leads.'));
}

restoreLeadJson(true, (string) $result['message'], [
    'restored' => (int) ($result['restored'] ?? 0),
    'remaining' => crmLeadsDeletedCount($conn),
]);
