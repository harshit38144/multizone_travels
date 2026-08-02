<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/lead_db.php';

header('Content-Type: application/json; charset=utf-8');

function updateLeadStageJson($success, $message, $extra = [])
{
    echo json_encode(array_merge([
        'success' => (bool) $success,
        'message' => (string) $message,
    ], $extra));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    updateLeadStageJson(false, 'Invalid request method.');
}

$leadId = (int) ($_POST['lead_id'] ?? 0);
$stage = (string) ($_POST['stage'] ?? '');

$result = crmLeadUpdateStage($conn, $leadId, $stage);
updateLeadStageJson(!empty($result['success']), (string) ($result['message'] ?? 'Update failed.'), $result);
