<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/lead_intake_db.php';

header('Content-Type: application/json; charset=utf-8');

function delIntakeJson($ok, $msg)
{
    echo json_encode(['success' => (bool) $ok, 'message' => (string) $msg]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    delIntakeJson(false, 'Invalid request.');
}

crmEnsureLeadIntakeTables($conn);

$submissionId = (int) ($_POST['submission_id'] ?? 0);
if ($submissionId <= 0) {
    delIntakeJson(false, 'Invalid submission.');
}

$stmt = $conn->prepare("SELECT `intake_request_id` FROM `crm_lead_intake_submissions` WHERE `id` = ? LIMIT 1");
if (!$stmt) {
    delIntakeJson(false, 'Could not load submission.');
}
$stmt->bind_param('i', $submissionId);
$stmt->execute();
$res = $stmt->get_result();
$row = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$row) {
    delIntakeJson(false, 'Submission not found.');
}

$requestId = (int) ($row['intake_request_id'] ?? 0);

$del = $conn->prepare("DELETE FROM `crm_lead_intake_submissions` WHERE `id` = ?");
if (!$del) {
    delIntakeJson(false, 'Could not delete submission.');
}
$del->bind_param('i', $submissionId);
if (!$del->execute()) {
    $del->close();
    delIntakeJson(false, 'Could not delete submission.');
}
$del->close();

// Remove the originating link request if it has no remaining submissions.
if ($requestId > 0) {
    $check = $conn->prepare("SELECT COUNT(*) AS cnt FROM `crm_lead_intake_submissions` WHERE `intake_request_id` = ?");
    if ($check) {
        $check->bind_param('i', $requestId);
        $check->execute();
        $cRes = $check->get_result();
        $remaining = $cRes ? (int) ($cRes->fetch_assoc()['cnt'] ?? 0) : 0;
        $check->close();
        if ($remaining === 0) {
            $delReq = $conn->prepare("DELETE FROM `crm_lead_intake_requests` WHERE `id` = ?");
            if ($delReq) {
                $delReq->bind_param('i', $requestId);
                $delReq->execute();
                $delReq->close();
            }
        }
    }
}

delIntakeJson(true, 'Submission deleted.');
