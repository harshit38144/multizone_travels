<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/lead_db.php';

header('Content-Type: application/json; charset=utf-8');

function updateLeadJson($success, $message, $extra = [])
{
    echo json_encode(array_merge([
        'success' => (bool) $success,
        'message' => (string) $message,
    ], $extra));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    updateLeadJson(false, 'Invalid request method.');
}

$leadId = (int) ($_POST['lead_id'] ?? 0);
if ($leadId <= 0) {
    updateLeadJson(false, 'Invalid lead.');
}

$check = $conn->prepare('SELECT `id` FROM `crm_leads` WHERE `id` = ? AND ' . crmLeadActiveWhereSql() . ' LIMIT 1');
if (!$check) {
    updateLeadJson(false, 'Could not load lead.');
}
$check->bind_param('i', $leadId);
$check->execute();
$cRes = $check->get_result();
$exists = $cRes && $cRes->fetch_assoc();
$check->close();
if (!$exists) {
    updateLeadJson(false, 'Lead not found.');
}

$customerName = trim($_POST['customer_name'] ?? '');
$customerPhone = trim($_POST['customer_phone'] ?? '');
$customerEmail = trim($_POST['customer_email'] ?? '');
$leadSource = trim($_POST['lead_source'] ?? '');
$referredBy = trim($_POST['referred_by'] ?? '');
$assignTo = trim($_POST['assign_to'] ?? '');
$services = $_POST['services'] ?? [];
$itineraryTotalNights = max(0, (int) ($_POST['itinerary_total_nights'] ?? 0));
$itineraryTotalDays = max(0, (int) ($_POST['itinerary_total_days'] ?? 0));

if ($customerName === '') {
    updateLeadJson(false, 'Customer name is required.');
}
if ($customerPhone === '') {
    updateLeadJson(false, 'Customer phone is required.');
}
if ($assignTo === '') {
    updateLeadJson(false, 'Please select assignee.');
}

if (!is_array($services)) {
    $services = [];
}
$services = array_values(array_filter(array_map('trim', $services), function ($s) {
    return $s !== '';
}));

if (count($services) === 0) {
    updateLeadJson(false, 'Please select at least one service.');
}

$createdByName = isset($_SESSION['name']) ? trim((string) $_SESSION['name']) : '';
if ($assignTo === '__self__') {
    $assignTo = $createdByName !== '' ? $createdByName : 'To Self';
}

$payload = $_POST;
unset($payload['lead_id']);
$payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE);
if ($payloadJson === false) {
    $payloadJson = '{}';
}
$servicesJson = json_encode($services, JSON_UNESCAPED_UNICODE);
if ($servicesJson === false) {
    $servicesJson = '[]';
}

$sql = "UPDATE `crm_leads` SET
        `customer_name` = ?, `customer_phone` = ?, `customer_email` = ?, `lead_source` = ?, `referred_by` = ?,
        `assign_to` = ?, `services` = ?, `itinerary_total_nights` = ?, `itinerary_total_days` = ?, `payload_json` = ?
    WHERE `id` = ?";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    updateLeadJson(false, 'Could not prepare update statement. ' . $conn->error);
}

$stmt->bind_param(
    'sssssssiisi',
    $customerName,
    $customerPhone,
    $customerEmail,
    $leadSource,
    $referredBy,
    $assignTo,
    $servicesJson,
    $itineraryTotalNights,
    $itineraryTotalDays,
    $payloadJson,
    $leadId
);

if (!$stmt->execute()) {
    $stmt->close();
    updateLeadJson(false, 'Could not update lead. ' . $conn->error);
}
$stmt->close();

updateLeadJson(true, 'Lead updated successfully.', [
    'lead' => [
        'id' => $leadId,
        'customer_name' => $customerName,
    ],
]);
