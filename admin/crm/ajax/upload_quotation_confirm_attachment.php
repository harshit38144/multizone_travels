<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/quotation_db.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$quotationId = (int) ($_POST['quotation_id'] ?? 0);
if ($quotationId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid quotation.']);
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

$check = $conn->prepare('SELECT `id` FROM `crm_quotations` WHERE `id` = ? LIMIT 1');
if (!$check) {
    echo json_encode(['success' => false, 'message' => 'Could not load quotation.']);
    exit;
}
$check->bind_param('i', $quotationId);
$check->execute();
$cRes = $check->get_result();
$exists = $cRes && $cRes->fetch_assoc();
$check->close();
if (!$exists) {
    echo json_encode(['success' => false, 'message' => 'Quotation not found.']);
    exit;
}

$maxSize = 10 * 1024 * 1024;
if ((int) ($file['size'] ?? 0) > $maxSize) {
    echo json_encode(['success' => false, 'message' => 'File must be under 10MB.']);
    exit;
}

$originalName = trim((string) ($file['name'] ?? 'attachment'));
if ($originalName === '') {
    $originalName = 'attachment';
}
if (strlen($originalName) > 240) {
    $originalName = substr($originalName, -240);
}

$ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
$allowedExt = ['pdf', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'zip'];
if ($ext === '' || !in_array($ext, $allowedExt, true)) {
    echo json_encode(['success' => false, 'message' => 'File type not allowed. Use PDF, image, DOC, XLS, TXT or ZIP.']);
    exit;
}

$uploadDir = __DIR__ . '/../../uploads/quotation_confirm/';
if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0775, true);
}

$storedName = 'q_' . $quotationId . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
$target = $uploadDir . $storedName;
$publicPath = 'uploads/quotation_confirm/' . $storedName;

if (!move_uploaded_file($file['tmp_name'], $target)) {
    echo json_encode(['success' => false, 'message' => 'Could not save uploaded file.']);
    exit;
}

echo json_encode([
    'success' => true,
    'message' => 'Attachment uploaded.',
    'attachment' => [
        'original_name' => $originalName,
        'file_path' => $publicPath,
        'file_size' => (int) ($file['size'] ?? 0),
    ],
], JSON_UNESCAPED_UNICODE);
