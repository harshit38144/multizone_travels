<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/lead_db.php';

header('Content-Type: application/json; charset=utf-8');

function bulkDeleteLeadJson($success, $message, $extra = [])
{
    echo json_encode(array_merge([
        'success' => (bool) $success,
        'message' => (string) $message,
    ], $extra));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    bulkDeleteLeadJson(false, 'Invalid request method.');
}

$leadIds = $_POST['lead_ids'] ?? [];
if (!is_array($leadIds)) {
    $leadIds = [$leadIds];
}

$result = crmLeadBulkPermanentDelete($conn, $leadIds, true);

if (empty($result['success'])) {
    bulkDeleteLeadJson(false, (string) ($result['message'] ?? 'Could not delete leads.'));
}

bulkDeleteLeadJson(true, (string) $result['message'], [
    'deleted' => (int) ($result['deleted'] ?? 0),
    'remaining' => crmLeadsDeletedCount($conn),
]);
