<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/quotation_db.php';
require_once __DIR__ . '/../includes/service_vouchers.php';

header('Content-Type: application/json; charset=utf-8');

function svJson($ok, $msg, $extra = [])
{
    echo json_encode(array_merge(['success' => (bool) $ok, 'message' => (string) $msg], $extra));
    exit;
}

svEnsureTable($conn);

$action = (string) ($_REQUEST['action'] ?? 'list');
$quotationId = (int) ($_REQUEST['quotation_id'] ?? 0);
$serviceUid = svSanitizeUid((string) ($_REQUEST['service_uid'] ?? ''));
if ($quotationId <= 0 || $serviceUid === '') {
    svJson(false, 'Invalid service.');
}

$qStmt = $conn->prepare('SELECT `id`, `lead_id` FROM `crm_quotations` WHERE `id` = ? LIMIT 1');
$qStmt->bind_param('i', $quotationId);
$qStmt->execute();
$qRes = $qStmt->get_result();
$quotation = $qRes ? $qRes->fetch_assoc() : null;
$qStmt->close();
if (!$quotation) {
    svJson(false, 'Quotation not found.');
}
$leadId = (int) ($quotation['lead_id'] ?? 0);

function svResponseList(mysqli $conn, int $quotationId, string $serviceUid): array
{
    return array_map('svPublicVoucher', svListVouchers($conn, $quotationId, $serviceUid));
}

if ($action === 'list') {
    svJson(true, 'OK', [
        'vouchers' => svResponseList($conn, $quotationId, $serviceUid),
        'max_files' => SV_MAX_FILES_PER_SERVICE,
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    svJson(false, 'Invalid request method.');
}

if ($action === 'delete') {
    $row = svGetVoucher($conn, (int) ($_POST['voucher_id'] ?? 0));
    if (!$row || (int) $row['quotation_id'] !== $quotationId || (string) $row['service_uid'] !== $serviceUid) {
        svJson(false, 'Voucher not found.');
    }
    svDeleteVoucherRow($conn, $row);
    $list = svResponseList($conn, $quotationId, $serviceUid);
    svJson(true, 'Voucher deleted.', ['vouchers' => $list, 'count' => count($list)]);
}

if ($action !== 'upload') {
    svJson(false, 'Unknown action.');
}

if (count(svListVouchers($conn, $quotationId, $serviceUid)) >= SV_MAX_FILES_PER_SERVICE) {
    svJson(false, 'You can attach up to ' . SV_MAX_FILES_PER_SERVICE . ' vouchers per service.');
}

@ini_set('memory_limit', '256M');

$file = $_FILES['file'] ?? null;
if (!is_array($file)) {
    svJson(false, 'Please choose a file to upload.');
}
// Same rules as traveller documents: JPG/PNG/WEBP/PDF, 4 MB, MIME + content checks.
$check = tdValidateUpload($file, 'other');
if (!$check['ok']) {
    svJson(false, $check['message']);
}

$dir = tdStorageDir();
if (!is_dir($dir) || !is_writable($dir)) {
    svJson(false, 'Upload folder is not writable.');
}
$destNoExt = $dir . '/voucher_' . date('YmdHis') . '_' . bin2hex(random_bytes(8));
$originalKb = (int) max(1, ceil(((int) $file['size']) / 1024));

if ($check['mime'] === 'application/pdf') {
    $result = tdCompressPdf($file['tmp_name'], $destNoExt);
    $outExt = 'pdf';
    $outMime = 'application/pdf';
} else {
    $result = tdCompressImage($file['tmp_name'], $check['mime'], $destNoExt);
    $outExt = $result['ext'] ?? 'webp';
    $outMime = $result['mime'] ?? 'image/webp';
}
if (!$result['ok']) {
    svJson(false, $result['message'] ?? 'Could not process the file.');
}

$fileRel = basename($result['path']);
$sizeKb = (int) max(1, ceil($result['bytes'] / 1024));
$displayName = tdSanitizeBaseName((string) $file['name']) . '.' . $outExt;
$serviceKey = substr((string) preg_replace('/[^A-Za-z0-9_\-]/', '', (string) ($_POST['service_key'] ?? '')), 0, 40);

$stmt = $conn->prepare(
    'INSERT INTO `crm_service_vouchers`
        (`quotation_id`, `lead_id`, `service_uid`, `service_key`, `file_name`, `file_path`, `mime_type`,
         `original_size_kb`, `file_size_kb`, `uploaded_at`)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
);
if (!$stmt) {
    tdDeleteFiles(['file_path' => $fileRel]);
    svJson(false, 'Could not save voucher.');
}
$stmt->bind_param(
    'iisssssii',
    $quotationId, $leadId, $serviceUid, $serviceKey, $displayName, $fileRel, $outMime, $originalKb, $sizeKb
);
if (!$stmt->execute()) {
    $stmt->close();
    tdDeleteFiles(['file_path' => $fileRel]);
    svJson(false, 'Could not save voucher.');
}
$newId = (int) $stmt->insert_id;
$stmt->close();

$saved = svGetVoucher($conn, $newId);
$list = svResponseList($conn, $quotationId, $serviceUid);
svJson(true, 'Voucher uploaded.', [
    'voucher' => $saved ? svPublicVoucher($saved) : null,
    'pdf_compressed' => $outMime === 'application/pdf' ? (bool) ($result['compressed'] ?? false) : null,
    'vouchers' => $list,
    'count' => count($list),
]);
