<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/lead_intake_db.php';

header('Content-Type: application/json; charset=utf-8');

function rejJson($ok, $msg)
{
    echo json_encode(['success' => (bool) $ok, 'message' => (string) $msg]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    rejJson(false, 'Invalid request.');
}

crmEnsureLeadIntakeTables($conn);

$submissionId = (int) ($_POST['submission_id'] ?? 0);
$note = trim($_POST['review_note'] ?? '');
if ($submissionId <= 0) {
    rejJson(false, 'Invalid submission.');
}

$stmt = $conn->prepare("SELECT s.status, s.intake_request_id FROM `crm_lead_intake_submissions` s WHERE s.id = ? LIMIT 1");
if (!$stmt) {
    rejJson(false, 'Could not load submission.');
}
$stmt->bind_param('i', $submissionId);
$stmt->execute();
$res = $stmt->get_result();
$row = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$row) {
    rejJson(false, 'Submission not found.');
}
if ((string) ($row['status'] ?? '') !== 'pending') {
    rejJson(false, 'This submission was already processed.');
}

$requestId = (int) ($row['intake_request_id'] ?? 0);
$reviewerId = isset($_SESSION['id']) ? (int) $_SESSION['id'] : null;
$reviewerName = trim((string) ($_SESSION['name'] ?? ''));

$upd = $conn->prepare("UPDATE `crm_lead_intake_submissions` SET `status` = 'rejected', `review_note` = ?, `reviewed_by_id` = ?, `reviewed_by_name` = ?, `reviewed_at` = NOW() WHERE `id` = ?");
if (!$upd) {
    rejJson(false, 'Could not reject.');
}
$upd->bind_param('sisi', $note, $reviewerId, $reviewerName, $submissionId);
if (!$upd->execute()) {
    $upd->close();
    rejJson(false, 'Could not reject.');
}
$upd->close();

$updReq = $conn->prepare("UPDATE `crm_lead_intake_requests` SET `status` = 'rejected' WHERE `id` = ?");
if ($updReq) {
    $updReq->bind_param('i', $requestId);
    $updReq->execute();
    $updReq->close();
}

rejJson(true, 'Submission rejected.');
