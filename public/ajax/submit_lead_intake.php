<?php
date_default_timezone_set('Asia/Kolkata');

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../admin/config/database.php';
require_once __DIR__ . '/../../admin/crm/includes/lead_intake_db.php';
require_once __DIR__ . '/../../admin/crm/includes/lead_intake_fields.php';

function intakeJson($ok, $msg, $extra = [])
{
    echo json_encode(array_merge(['success' => (bool) $ok, 'message' => (string) $msg], $extra));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    intakeJson(false, 'Invalid request.');
}

crmEnsureLeadIntakeTables($conn);

$token = trim($_POST['token'] ?? '');
if ($token === '') {
    intakeJson(false, 'Invalid link.');
}

$stmt = $conn->prepare("SELECT * FROM `crm_lead_intake_requests` WHERE `token` = ? LIMIT 1");
if (!$stmt) {
    intakeJson(false, 'Could not verify link.');
}
$stmt->bind_param('s', $token);
$stmt->execute();
$res = $stmt->get_result();
$request = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$request) {
    intakeJson(false, 'Invalid or expired link.');
}

$status = (string) ($request['status'] ?? '');
if (in_array($status, ['approved', 'rejected', 'cancelled'], true)) {
    intakeJson(false, 'This link is no longer active.');
}
if ($status === 'submitted') {
    intakeJson(false, 'You have already submitted this form.');
}

$enabled = json_decode((string) ($request['field_config'] ?? '[]'), true);
if (!is_array($enabled)) {
    $enabled = [];
}

$customerName = trim($_POST['customer_name'] ?? '');
$customerPhone = trim($_POST['customer_phone'] ?? '');

if (crmLeadIntakeFieldEnabled($enabled, 'customer_name') && $customerName === '') {
    intakeJson(false, 'Please enter your name.');
}
if (crmLeadIntakeFieldEnabled($enabled, 'customer_phone') && $customerPhone === '') {
    intakeJson(false, 'Please enter your phone number.');
}

$services = crmInferPayloadServices($_POST);
if (count($services) === 0 && crmLeadIntakeFieldEnabled($enabled, 'services')) {
    intakeJson(false, 'Please select at least one service.');
}
if (count($services) === 0) {
    $services = crmLeadIntakeAutoServiceValues($enabled);
}
if (count($services) === 0) {
    intakeJson(false, 'No valid form fields configured for this link.');
}
$_POST['services'] = $services;

$payload = $_POST;
unset($payload['token']);
$payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE);
if ($payloadJson === false) {
    $payloadJson = '{}';
}

$requestId = (int) ($request['id'] ?? 0);
$ins = $conn->prepare("INSERT INTO `crm_lead_intake_submissions` (`intake_request_id`, `payload_json`, `status`) VALUES (?, ?, 'pending')");
if (!$ins) {
    intakeJson(false, 'Could not save submission.');
}
$ins->bind_param('is', $requestId, $payloadJson);
if (!$ins->execute()) {
    $ins->close();
    intakeJson(false, 'Could not save submission.');
}
$submissionId = (int) $conn->insert_id;
$ins->close();

$upd = $conn->prepare("UPDATE `crm_lead_intake_requests` SET `status` = 'submitted', `submitted_at` = NOW() WHERE `id` = ? AND `status` = 'sent'");
if ($upd) {
    $upd->bind_param('i', $requestId);
    $upd->execute();
    $upd->close();
}

intakeJson(true, 'Your details were submitted successfully. Our team will contact you soon.', [
    'redirect' => crmBuildIntakeThanksUrl($token),
    'submission_id' => $submissionId,
]);
