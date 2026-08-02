<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/lead_save_from_payload.php';
require_once __DIR__ . '/../includes/lead_db.php';

header('Content-Type: application/json; charset=utf-8');

function duplicateLeadJson($success, $message, $extra = [])
{
    echo json_encode(array_merge([
        'success' => (bool) $success,
        'message' => (string) $message,
    ], $extra));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    duplicateLeadJson(false, 'Invalid request method.');
}

$leadId = (int) ($_POST['lead_id'] ?? 0);
if ($leadId <= 0) {
    duplicateLeadJson(false, 'Invalid lead.');
}

$row = crmLeadFetchById($conn, $leadId, false);

if (!$row) {
    duplicateLeadJson(false, 'Lead not found.');
}

$payload = [];
if (!empty($row['payload_json'])) {
    $decoded = json_decode((string) $row['payload_json'], true);
    if (is_array($decoded)) {
        $payload = $decoded;
    }
}

$payload['customer_name'] = (string) ($row['customer_name'] ?? '');
$payload['customer_phone'] = (string) ($row['customer_phone'] ?? '');
$payload['customer_email'] = (string) ($row['customer_email'] ?? '');
$payload['lead_source'] = (string) ($row['lead_source'] ?? '');
$payload['referred_by'] = (string) ($row['referred_by'] ?? '');
$payload['assign_to'] = (string) ($row['assign_to'] ?? '');
$payload['itinerary_total_nights'] = max(0, (int) ($row['itinerary_total_nights'] ?? 0));
$payload['itinerary_total_days'] = max(0, (int) ($row['itinerary_total_days'] ?? 0));

if (!empty($row['services'])) {
    $services = json_decode((string) $row['services'], true);
    if (is_array($services)) {
        $payload['services'] = $services;
    }
}

$name = trim($payload['customer_name']);
if ($name !== '' && stripos($name, '(Copy)') === false) {
    $payload['customer_name'] = $name . ' (Copy)';
}

$createdById = isset($_SESSION['id']) ? (int) $_SESSION['id'] : 0;
$createdByName = isset($_SESSION['name']) ? trim((string) $_SESSION['name']) : '';

$result = crmSaveLeadFromPayload($conn, $payload, [
    'lead_source' => (string) ($row['lead_source'] ?? ''),
    'referred_by' => (string) ($row['referred_by'] ?? ''),
    'assign_to' => (string) ($row['assign_to'] ?? ''),
    'created_by_id' => $createdById,
    'created_by_name' => $createdByName,
    'intake_submission_id' => 0,
]);

if (empty($result['success'])) {
    duplicateLeadJson(false, (string) ($result['message'] ?? 'Could not duplicate lead.'));
}

duplicateLeadJson(true, 'Lead duplicated successfully.', [
    'lead' => $result['lead'] ?? null,
]);
