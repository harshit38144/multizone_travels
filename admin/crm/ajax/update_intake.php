<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/lead_intake_db.php';
require_once __DIR__ . '/../includes/lead_intake_fields.php';

header('Content-Type: application/json; charset=utf-8');

function updIntakeJson($ok, $msg, $extra = [])
{
    echo json_encode(array_merge(['success' => (bool) $ok, 'message' => (string) $msg], $extra));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    updIntakeJson(false, 'Invalid request.');
}

crmEnsureLeadIntakeTables($conn);

$submissionId = (int) ($_POST['submission_id'] ?? 0);
if ($submissionId <= 0) {
    updIntakeJson(false, 'Invalid submission.');
}

$stmt = $conn->prepare("SELECT s.id, s.status, s.payload_json, s.intake_request_id
    FROM `crm_lead_intake_submissions` s WHERE s.id = ? LIMIT 1");
if (!$stmt) {
    updIntakeJson(false, 'Could not load submission.');
}
$stmt->bind_param('i', $submissionId);
$stmt->execute();
$res = $stmt->get_result();
$row = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$row) {
    updIntakeJson(false, 'Submission not found.');
}
if ((string) ($row['status'] ?? '') !== 'pending') {
    updIntakeJson(false, 'This submission was already processed.');
}

$requestId = (int) ($row['intake_request_id'] ?? 0);

// Build new payload from the full lead-form submission.
$payload = $_POST;
unset($payload['submission_id']);
unset($payload['token']);

// Normalise services (mirror the public submit handler).
$services = $payload['services'] ?? [];
if (!is_array($services)) {
    $services = $services !== '' ? [$services] : [];
}
$services = array_values(array_filter(array_map('trim', $services), function ($s) {
    return $s !== '';
}));
if (count($services) === 0) {
    $inferMap = [
        'tour_package' => ['tp_travel_date', 'tp_departure', 'tp_arrival', 'tp_destination', 'tp_budget', 'tp_notes', 'tp_tour_type'],
        'cruise' => ['cruise_embark_date', 'cruise_line', 'cruise_cabin'],
        'visa' => ['visa_country', 'visa_type'],
        'passport' => ['passport_service', 'passport_urgency'],
        'forex' => ['forex_currency', 'forex_amount'],
        'vehicle' => ['vehicle_type'],
    ];
    foreach ($inferMap as $svc => $keys) {
        foreach ($keys as $key) {
            if (!empty($payload[$key])) {
                $services[] = $svc;
                break;
            }
        }
    }
    $services = array_values(array_unique($services));
}
$payload['services'] = $services;

// Assignment / source.
$assignTo = trim((string) ($payload['assign_to'] ?? ''));
$leadSource = trim((string) ($payload['lead_source'] ?? ''));

$adminName = trim((string) ($_SESSION['name'] ?? ''));
if ($assignTo === '__self__') {
    $assignTo = $adminName !== '' ? $adminName : 'Admin';
}
$payload['assign_to'] = $assignTo;
$payload['lead_source'] = $leadSource;

$newPayloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE);
if ($newPayloadJson === false) {
    updIntakeJson(false, 'Could not encode payload.');
}

$upd = $conn->prepare("UPDATE `crm_lead_intake_submissions` SET `payload_json` = ? WHERE `id` = ?");
if (!$upd) {
    updIntakeJson(false, 'Could not update submission.');
}
$upd->bind_param('si', $newPayloadJson, $submissionId);
if (!$upd->execute()) {
    $upd->close();
    updIntakeJson(false, 'Could not update submission.');
}
$upd->close();

if ($requestId > 0) {
    $recipientName = trim((string) ($payload['customer_name'] ?? ''));
    $recipientPhone = trim((string) ($payload['customer_phone'] ?? ''));
    $recipientEmail = trim((string) ($payload['customer_email'] ?? ''));

    $updReq = $conn->prepare("UPDATE `crm_lead_intake_requests`
        SET `assign_to` = ?, `lead_source` = ?, `recipient_name` = ?, `recipient_phone` = ?, `recipient_email` = ?
        WHERE `id` = ?");
    if ($updReq) {
        $updReq->bind_param('sssssi', $assignTo, $leadSource, $recipientName, $recipientPhone, $recipientEmail, $requestId);
        $updReq->execute();
        $updReq->close();
    }
}

updIntakeJson(true, 'Submission updated successfully.');
