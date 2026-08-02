<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/lead_uid.php';

header('Content-Type: application/json; charset=utf-8');

function leadJsonResponse($success, $message, $extra = [])
{
    echo json_encode(array_merge([
        'success' => (bool) $success,
        'message' => (string) $message,
    ], $extra));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    leadJsonResponse(false, 'Invalid request method.');
}

$createTableSql = "CREATE TABLE IF NOT EXISTS `crm_leads` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `lead_uid` VARCHAR(40) NOT NULL,
    `customer_name` VARCHAR(150) NOT NULL,
    `customer_phone` VARCHAR(30) NOT NULL,
    `customer_email` VARCHAR(190) DEFAULT NULL,
    `lead_source` VARCHAR(60) DEFAULT NULL,
    `referred_by` VARCHAR(150) DEFAULT NULL,
    `assign_to` VARCHAR(120) DEFAULT NULL,
    `services` TEXT,
    `itinerary_total_nights` INT DEFAULT 0,
    `itinerary_total_days` INT DEFAULT 0,
    `payload_json` LONGTEXT,
    `created_by_id` INT DEFAULT NULL,
    `created_by_name` VARCHAR(120) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_crm_lead_uid` (`lead_uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if (!$conn->query($createTableSql)) {
    leadJsonResponse(false, 'Could not prepare leads table. ' . $conn->error);
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
    leadJsonResponse(false, 'Customer name is required.');
}
if ($customerPhone === '') {
    leadJsonResponse(false, 'Customer phone is required.');
}
if ($assignTo === '') {
    leadJsonResponse(false, 'Please select assignee.');
}

if (!is_array($services)) {
    $services = [];
}
$services = array_values(array_filter(array_map('trim', $services), function ($s) {
    return $s !== '';
}));

if (count($services) === 0) {
    leadJsonResponse(false, 'Please select at least one service.');
}

$payload = $_POST;
$payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE);
if ($payloadJson === false) {
    $payloadJson = '{}';
}

$leadUid = generateLeadUid($conn);
$servicesJson = json_encode($services, JSON_UNESCAPED_UNICODE);
if ($servicesJson === false) {
    $servicesJson = '[]';
}

$createdById = isset($_SESSION['id']) ? (int) $_SESSION['id'] : null;
$createdByName = isset($_SESSION['name']) ? trim((string) $_SESSION['name']) : '';
if ($createdByName === '') {
    $createdByName = null;
}

if ($assignTo === '__self__') {
    $assignTo = $createdByName !== null ? $createdByName : 'To Self';
}

$sql = "INSERT INTO `crm_leads`
    (`lead_uid`, `customer_name`, `customer_phone`, `customer_email`, `lead_source`, `referred_by`, `assign_to`,
     `services`, `itinerary_total_nights`, `itinerary_total_days`, `payload_json`, `created_by_id`, `created_by_name`)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    leadJsonResponse(false, 'Could not prepare save statement. ' . $conn->error);
}

$stmt->bind_param(
    'ssssssssiisis',
    $leadUid,
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
    $createdById,
    $createdByName
);

if (!$stmt->execute()) {
    $stmt->close();
    leadJsonResponse(false, 'Could not save lead. ' . $conn->error);
}

$newId = (int) $stmt->insert_id;
$stmt->close();

leadJsonResponse(true, 'Lead saved successfully.', [
    'lead' => [
        'id' => $newId,
        'lead_uid' => $leadUid,
        'customer_name' => $customerName,
    ],
]);
