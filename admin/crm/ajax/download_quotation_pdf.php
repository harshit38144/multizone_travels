<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/quotation_pdf.php';

if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_errno) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Database connection failed.');
}

$quotationId = (int) ($_GET['id'] ?? $_GET['quotation_id'] ?? 0);
$version = (int) ($_GET['version'] ?? 0);
$asDownload = !isset($_GET['download']) || (string) $_GET['download'] !== '0';
$wantJson = isset($_GET['status']) || (
    !empty($_SERVER['HTTP_ACCEPT']) && strpos((string) $_SERVER['HTTP_ACCEPT'], 'application/json') !== false
    && isset($_GET['check'])
);

if ($quotationId <= 0) {
    if ($wantJson || isset($_GET['check'])) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Quotation id is required.']);
        exit;
    }
    http_response_code(400);
    exit('Quotation id is required.');
}

if (isset($_GET['check']) || isset($_GET['status'])) {
    header('Content-Type: application/json; charset=utf-8');
    $status = crmQuotationPdfStatus($conn, $quotationId, $version);
    if (empty($status['ok'])) {
        echo json_encode([
            'success' => false,
            'message' => (string) ($status['message'] ?? 'Unable to check PDF status.'),
        ]);
        exit;
    }
    echo json_encode([
        'success' => true,
        'exists' => !empty($status['exists']),
        'fresh' => !empty($status['fresh']),
        'invalid_blank' => !empty($status['invalid_blank']),
        'byte_size' => (int) ($status['byte_size'] ?? 0),
        'version' => (int) ($status['version'] ?? $version),
        'file_name' => (string) ($status['file_name'] ?? ''),
        'generated_at' => (string) ($status['generated_at'] ?? ''),
        'chrome' => !empty($status['chrome']),
        'download_url' => 'crm/ajax/download_quotation_pdf.php?id=' . $quotationId
            . '&version=' . (int) ($status['version'] ?? $version)
            . '&download=1',
    ]);
    exit;
}

$status = crmQuotationPdfStatus($conn, $quotationId, $version);
if (empty($status['ok'])) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit((string) ($status['message'] ?? 'Quotation not found.'));
}

if (empty($status['exists']) || empty($status['fresh'])) {
    http_response_code(409);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => empty($status['exists'])
            ? 'PDF not found. Generate it first.'
            : 'Saved PDF is outdated. Regenerate before download.',
        'exists' => !empty($status['exists']),
        'fresh' => !empty($status['fresh']),
        'version' => (int) ($status['version'] ?? $version),
        'need_generate' => true,
        'chrome' => !empty($status['chrome']),
    ]);
    exit;
}

$version = (int) ($status['version'] ?? $version);
$pdf = crmQuotationPdfLoadRow($conn, $quotationId, $version);
if (!$pdf) {
    http_response_code(404);
    exit('PDF not found.');
}

$abs = crmQuotationPdfAbsolutePath((string) $pdf['file_path']);
if (!is_file($abs) || !crmQuotationPdfLooksContentful($abs)) {
    if (is_file($abs)) {
        @unlink($abs);
    }
    http_response_code(409);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => 'Saved PDF is missing or blank. Regenerate before download.',
        'need_generate' => true,
        'invalid_blank' => true,
    ]);
    exit;
}

$fileName = crmQuotationPdfSanitizeFileName((string) ($pdf['file_name'] ?? 'Quotation.pdf'));
$etag = '"qpdf' . $quotationId . '-v' . $version . '-' . filemtime($abs) . '-' . filesize($abs) . '"';

header_remove('Pragma');
header_remove('Expires');
header('Cache-Control: private, no-store, max-age=0');
header('ETag: ' . $etag);
header('X-Content-Type-Options: nosniff');
header('Content-Type: application/pdf');
header('Content-Length: ' . filesize($abs));
header(
    'Content-Disposition: ' . ($asDownload ? 'attachment' : 'inline')
    . '; filename="' . str_replace('"', '', $fileName) . '"'
);

if (trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
    http_response_code(304);
    exit;
}

readfile($abs);
exit;
