<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/quotation_pdf.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_errno) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit;
}

$quotationId = (int) ($_GET['id'] ?? $_GET['quotation_id'] ?? 0);
$version = (int) ($_GET['version'] ?? 0);

if ($quotationId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Quotation id is required.']);
    exit;
}

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
