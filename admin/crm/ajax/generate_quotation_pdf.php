<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/quotation_pdf.php';

header('Content-Type: application/json; charset=utf-8');

function qpdfJson($success, $message, $extra = [])
{
    echo json_encode(array_merge(['success' => (bool) $success, 'message' => (string) $message], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    qpdfJson(false, 'Invalid request method.');
}

if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_errno) {
    qpdfJson(false, 'Database connection failed.');
}

$quotationId = (int) ($_POST['quotation_id'] ?? $_POST['id'] ?? 0);
$version = (int) ($_POST['version'] ?? 0);
$fileName = trim((string) ($_POST['file_name'] ?? $_POST['filename'] ?? 'Quotation.pdf'));
$html = (string) ($_POST['html'] ?? '');
$userId = (int) ($_SESSION['id'] ?? 0);

if ($quotationId <= 0) {
    qpdfJson(false, 'Save the quotation before generating a PDF.');
}

$uploadedBinary = null;
if (!empty($_FILES['pdf']) && is_array($_FILES['pdf'])) {
    $err = (int) ($_FILES['pdf']['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($err === UPLOAD_ERR_OK) {
        $tmp = (string) ($_FILES['pdf']['tmp_name'] ?? '');
        $size = (int) ($_FILES['pdf']['size'] ?? 0);
        if ($tmp !== '' && is_uploaded_file($tmp) && $size > 0 && $size <= CRM_QUOTATION_PDF_MAX_BYTES) {
            $uploadedBinary = (string) file_get_contents($tmp);
        }
    } elseif ($err !== UPLOAD_ERR_NO_FILE) {
        qpdfJson(false, 'PDF upload failed (error ' . $err . ').');
    }
}

// Optional base64 PDF fallback (for environments that strip multipart oddly).
if ($uploadedBinary === null) {
    $b64 = trim((string) ($_POST['pdf_base64'] ?? ''));
    if ($b64 !== '') {
        if (stripos($b64, 'base64,') !== false) {
            $b64 = substr($b64, stripos($b64, 'base64,') + 7);
        }
        $decoded = base64_decode($b64, true);
        if ($decoded !== false) {
            $uploadedBinary = $decoded;
        }
    }
}

if ($html === '' && $uploadedBinary === null) {
    qpdfJson(false, 'Print HTML or PDF content is required.');
}

// Cap HTML payload to protect memory.
if (strlen($html) > 12 * 1024 * 1024) {
    qpdfJson(false, 'Print HTML is too large.');
}

try {
    $result = crmQuotationPdfGenerateAndSave(
        $conn,
        $quotationId,
        $version,
        $html,
        $fileName,
        $uploadedBinary,
        $userId > 0 ? $userId : null
    );
} catch (Throwable $e) {
    qpdfJson(false, 'PDF generation error: ' . $e->getMessage());
}

if (empty($result['ok'])) {
    qpdfJson(false, (string) ($result['message'] ?? 'Could not generate PDF.'), [
        'need_client_pdf' => !empty($result['need_client_pdf']),
        'chrome' => !empty($result['chrome']),
        'version' => $version > 0 ? $version : null,
    ]);
}

qpdfJson(true, (string) ($result['message'] ?? 'PDF saved.'), [
    'quotation_id' => $quotationId,
    'version' => (int) ($result['version'] ?? $version),
    'file_name' => (string) ($result['file_name'] ?? $fileName),
    'bytes' => (int) ($result['bytes'] ?? 0),
    'method' => (string) ($result['method'] ?? ''),
    'exists' => true,
    'fresh' => true,
    'download_url' => 'crm/ajax/download_quotation_pdf.php?id=' . $quotationId
        . '&version=' . (int) ($result['version'] ?? $version)
        . '&download=1',
]);
