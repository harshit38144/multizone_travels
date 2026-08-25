<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/lead_intake_db.php';
require_once __DIR__ . '/../includes/lead_intake_fields.php';

header('Content-Type: application/json; charset=utf-8');

function intakeLinkJson($ok, $msg, $extra = [])
{
    echo json_encode(array_merge(['success' => (bool) $ok, 'message' => (string) $msg], $extra));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    intakeLinkJson(false, 'Invalid request method.');
}

if (!crmEnsureLeadIntakeTables($conn)) {
    intakeLinkJson(false, 'Could not prepare intake tables.');
}

$fieldsRaw = $_POST['fields'] ?? [];
if (!is_array($fieldsRaw)) {
    $fieldsRaw = [];
}
$enabledFields = crmLeadIntakeNormalizeFields($fieldsRaw);
if (count($enabledFields) === 0) {
    $enabledFields = crmLeadIntakeSendLinkDefaultFields();
}

$recipientName = trim($_POST['recipient_name'] ?? '');
$recipientPhone = trim($_POST['recipient_phone'] ?? '');
$recipientEmail = trim($_POST['recipient_email'] ?? '');
$note = trim($_POST['note_to_customer'] ?? '');

$adminId = isset($_SESSION['id']) ? (int) $_SESSION['id'] : 0;
$adminName = trim((string) ($_SESSION['name'] ?? ''));
$assignTo = $adminName !== '' ? $adminName : 'Admin';
$leadSource = 'Customer Form Link';
$referredBy = '';

$fieldConfigJson = json_encode($enabledFields, JSON_UNESCAPED_UNICODE);
if ($fieldConfigJson === false) {
    $fieldConfigJson = '[]';
}

$token = crmGenerateIntakeToken($conn);
$sql = "INSERT INTO `crm_lead_intake_requests`
    (`token`, `admin_id`, `admin_name`, `recipient_name`, `recipient_phone`, `recipient_email`,
     `lead_source`, `referred_by`, `assign_to`, `field_config`, `note_to_customer`, `status`)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'sent')";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    intakeLinkJson(false, 'Could not create link. ' . $conn->error);
}

$stmt->bind_param(
    'sisssssssss',
    $token,
    $adminId,
    $adminName,
    $recipientName,
    $recipientPhone,
    $recipientEmail,
    $leadSource,
    $referredBy,
    $assignTo,
    $fieldConfigJson,
    $note
);

if (!$stmt->execute()) {
    $stmt->close();
    intakeLinkJson(false, 'Could not create link. ' . $conn->error);
}
$requestId = (int) $stmt->insert_id;
$stmt->close();

$url = crmBuildIntakePublicUrl($token);

intakeLinkJson(true, 'Customer form link created.', [
    'request_id' => $requestId,
    'token' => $token,
    'url' => $url,
]);
