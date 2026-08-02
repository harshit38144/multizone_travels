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
if ($leadId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid lead.']);
    exit;
}

if (empty($_FILES['attachment']) || !is_array($_FILES['attachment'])) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded.']);
    exit;
}

$file = $_FILES['attachment'];
if (!empty($file['error'])) {
    echo json_encode(['success' => false, 'message' => 'Upload error.']);
    exit;
}

$check = $conn->prepare('SELECT `id` FROM `crm_leads` WHERE `id` = ? AND ' . crmLeadActiveWhereSql() . ' LIMIT 1');
if (!$check) {
    echo json_encode(['success' => false, 'message' => 'Could not load lead.']);
    exit;
}
$check->bind_param('i', $leadId);
$check->execute();
$cRes = $check->get_result();
$exists = $cRes && $cRes->fetch_assoc();
$check->close();
if (!$exists) {
    echo json_encode(['success' => false, 'message' => 'Lead not found.']);
    exit;
}

$maxSize = 10 * 1024 * 1024;
if ((int) ($file['size'] ?? 0) > $maxSize) {
    echo json_encode(['success' => false, 'message' => 'File must be under 10MB.']);
    exit;
}

$allowed = crmLeadAttachmentAllowedTypes();
$mime = crmLeadAttachmentDetectMime($file);
if (!isset($allowed[$mime])) {
    echo json_encode(['success' => false, 'message' => 'File type not allowed. Use PDF, image, DOC, XLS, TXT or ZIP.']);
    exit;
}

$originalName = trim((string) ($file['name'] ?? 'attachment'));
if ($originalName === '') {
    $originalName = 'attachment';
}
if (strlen($originalName) > 240) {
    $originalName = substr($originalName, -240);
}

$ext = $allowed[$mime];
$uploadDir = crmLeadAttachmentsUploadDir();
if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0775, true);
}

$storedName = 'lead_' . $leadId . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
$target = $uploadDir . $storedName;
$publicPath = crmLeadAttachmentsPublicPrefix() . $storedName;

if (!move_uploaded_file($file['tmp_name'], $target)) {
    echo json_encode(['success' => false, 'message' => 'Could not save uploaded file.']);
    exit;
}

crmEnsureLeadAttachmentsTable($conn);
$uploadedById = isset($_SESSION['id']) ? (int) $_SESSION['id'] : 0;
$uploadedByName = isset($_SESSION['name']) ? trim((string) $_SESSION['name']) : '';
if ($uploadedByName === '') {
    $uploadedByName = null;
}

$sql = 'INSERT INTO `crm_lead_attachments`
    (`lead_id`, `original_name`, `stored_name`, `file_path`, `mime_type`, `file_size`, `uploaded_by_id`, `uploaded_by_name`)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)';
$stmt = $conn->prepare($sql);
if (!$stmt) {
    @unlink($target);
    echo json_encode(['success' => false, 'message' => 'Could not save attachment record.']);
    exit;
}

$fileSize = (int) ($file['size'] ?? 0);
$stmt->bind_param('issssiis', $leadId, $originalName, $storedName, $publicPath, $mime, $fileSize, $uploadedById, $uploadedByName);
if (!$stmt->execute()) {
    $stmt->close();
    @unlink($target);
    echo json_encode(['success' => false, 'message' => 'Could not save attachment record.']);
    exit;
}

$newId = (int) $stmt->insert_id;
$stmt->close();

echo json_encode([
    'success' => true,
    'message' => 'Attachment uploaded.',
    'attachment' => [
        'id' => $newId,
        'original_name' => $originalName,
        'file_url' => $publicPath,
        'file_size' => $fileSize,
        'file_size_text' => crmLeadAttachmentFormatSize($fileSize),
    ],
], JSON_UNESCAPED_UNICODE);
