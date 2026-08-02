<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/lead_db.php';
require_once __DIR__ . '/../includes/lead_attachments_db.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$leadId = (int) ($_POST['lead_id'] ?? 0);
$attachmentId = (int) ($_POST['attachment_id'] ?? 0);
if ($leadId <= 0 || $attachmentId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid attachment.']);
    exit;
}

crmEnsureLeadAttachmentsTable($conn);

$stmt = $conn->prepare(
    'SELECT `id`, `file_path`, `stored_name`
     FROM `crm_lead_attachments`
     WHERE `id` = ? AND `lead_id` = ?
     LIMIT 1'
);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Could not load attachment.']);
    exit;
}
$stmt->bind_param('ii', $attachmentId, $leadId);
$stmt->execute();
$res = $stmt->get_result();
$row = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$row) {
    echo json_encode(['success' => false, 'message' => 'Attachment not found.']);
    exit;
}

$del = $conn->prepare('DELETE FROM `crm_lead_attachments` WHERE `id` = ? AND `lead_id` = ? LIMIT 1');
if (!$del) {
    echo json_encode(['success' => false, 'message' => 'Could not delete attachment.']);
    exit;
}
$del->bind_param('ii', $attachmentId, $leadId);
$del->execute();
$deleted = $del->affected_rows > 0;
$del->close();

if (!$deleted) {
    echo json_encode(['success' => false, 'message' => 'Could not delete attachment.']);
    exit;
}

$storedName = trim((string) ($row['stored_name'] ?? ''));
if ($storedName !== '') {
    $filePath = crmLeadAttachmentsUploadDir() . basename($storedName);
    if (is_file($filePath)) {
        @unlink($filePath);
    }
}

echo json_encode(['success' => true, 'message' => 'Attachment deleted.'], JSON_UNESCAPED_UNICODE);
