<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/lead_db.php';

header('Content-Type: application/json; charset=utf-8');

function deleteLeadJson($success, $message, $extra = [])
{
    echo json_encode(array_merge([
        'success' => (bool) $success,
        'message' => (string) $message,
    ], $extra));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    deleteLeadJson(false, 'Invalid request method.');
}

$leadId = (int) ($_POST['lead_id'] ?? 0);
$mode = strtolower(trim((string) ($_POST['mode'] ?? 'soft')));

if ($leadId <= 0) {
    deleteLeadJson(false, 'Invalid lead.');
}

if (!in_array($mode, ['soft', 'permanent'], true)) {
    deleteLeadJson(false, 'Invalid delete mode.');
}

$deletedById = isset($_SESSION['id']) ? (int) $_SESSION['id'] : 0;
$deletedByName = isset($_SESSION['name']) ? trim((string) $_SESSION['name']) : '';

if ($mode === 'permanent') {
    if (!crmLeadFetchDeletedById($conn, $leadId)) {
        deleteLeadJson(false, 'Lead is not in trash.');
    }
    $result = crmLeadPermanentDelete($conn, $leadId);
} else {
    $result = crmLeadSoftDelete($conn, $leadId, $deletedById, $deletedByName);
}

if (empty($result['success'])) {
    deleteLeadJson(false, (string) ($result['message'] ?? 'Could not delete lead.'));
}

deleteLeadJson(true, (string) $result['message'], [
    'mode' => (string) ($result['mode'] ?? $mode),
    'lead_id' => $leadId,
]);
