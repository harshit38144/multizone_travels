<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/lead_intake_db.php';
require_once __DIR__ . '/../includes/lead_save_from_payload.php';

header('Content-Type: application/json; charset=utf-8');

function apprJson($ok, $msg, $extra = [])
{
    echo json_encode(array_merge(['success' => (bool) $ok, 'message' => (string) $msg], $extra));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apprJson(false, 'Invalid request.');
}

crmEnsureLeadIntakeTables($conn);

$submissionId = (int) ($_POST['submission_id'] ?? 0);
if ($submissionId <= 0) {
    apprJson(false, 'Invalid submission.');
}

$stmt = $conn->prepare("SELECT s.*, r.lead_source, r.referred_by, r.assign_to, r.admin_id, r.admin_name
    FROM `crm_lead_intake_submissions` s
    INNER JOIN `crm_lead_intake_requests` r ON r.id = s.intake_request_id
    WHERE s.id = ? LIMIT 1");
if (!$stmt) {
    apprJson(false, 'Could not load submission.');
}
$stmt->bind_param('i', $submissionId);
$stmt->execute();
$res = $stmt->get_result();
$row = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$row) {
    apprJson(false, 'Submission not found.');
}
if ((string) ($row['status'] ?? '') !== 'pending') {
    apprJson(false, 'This submission was already processed.');
}

$payload = json_decode((string) ($row['payload_json'] ?? '{}'), true);
if (!is_array($payload)) {
    $payload = [];
}

$payload['lead_source'] = (string) ($row['lead_source'] ?? '');
$payload['referred_by'] = (string) ($row['referred_by'] ?? '');

$postAssignTo = trim((string) ($_POST['assign_to'] ?? ''));
if ($postAssignTo === '__self__') {
    $postAssignTo = trim((string) ($_SESSION['name'] ?? ''));
    if ($postAssignTo === '') {
        $postAssignTo = trim((string) ($row['admin_name'] ?? ''));
    }
}

if ($postAssignTo !== '') {
    $assignTo = $postAssignTo;
} else {
    $assignTo = trim((string) ($row['assign_to'] ?? ''));
    if ($assignTo === '') {
        $assignTo = trim((string) ($row['admin_name'] ?? ''));
    }
    if ($assignTo === '') {
        $assignTo = trim((string) ($_SESSION['name'] ?? ''));
    }
}

if ($assignTo === '') {
    apprJson(false, 'Please select assignee.');
}

$payload['assign_to'] = $assignTo;
$payload['services'] = crmInferPayloadServices($payload);

$reviewerId = isset($_SESSION['id']) ? (int) $_SESSION['id'] : 0;
$reviewerName = trim((string) ($_SESSION['name'] ?? ''));

$result = crmSaveLeadFromPayload($conn, $payload, [
    'lead_source' => $payload['lead_source'],
    'referred_by' => $payload['referred_by'],
    'assign_to' => $payload['assign_to'],
    'created_by_id' => $reviewerId,
    'created_by_name' => $reviewerName !== '' ? $reviewerName : (string) ($row['admin_name'] ?? ''),
    'intake_submission_id' => $submissionId,
]);

if (!$result['success']) {
    apprJson(false, $result['message']);
}

$leadId = (int) ($result['lead']['id'] ?? 0);
$requestId = (int) ($row['intake_request_id'] ?? 0);

$upd = $conn->prepare("UPDATE `crm_lead_intake_submissions` SET `status` = 'approved', `lead_id` = ?, `reviewed_by_id` = ?, `reviewed_by_name` = ?, `reviewed_at` = NOW() WHERE `id` = ?");
if ($upd) {
    $upd->bind_param('iisi', $leadId, $reviewerId, $reviewerName, $submissionId);
    $upd->execute();
    $upd->close();
}

$updReq = $conn->prepare("UPDATE `crm_lead_intake_requests` SET `status` = 'approved' WHERE `id` = ?");
if ($updReq) {
    $updReq->bind_param('i', $requestId);
    $updReq->execute();
    $updReq->close();
}

apprJson(true, 'Lead approved and added to CRM.', ['lead' => $result['lead']]);
