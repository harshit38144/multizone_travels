<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/quotation_db.php';
require_once __DIR__ . '/../includes/traveller_documents.php';

header('Content-Type: application/json; charset=utf-8');

function tdJson($ok, $msg, $extra = [])
{
    echo json_encode(array_merge(['success' => (bool) $ok, 'message' => (string) $msg], $extra));
    exit;
}

tdEnsureTable($conn);

$action = (string) ($_REQUEST['action'] ?? 'list');
$quotationId = (int) ($_REQUEST['quotation_id'] ?? 0);
$travellerId = trim((string) ($_REQUEST['traveller_id'] ?? ''));
if ($quotationId <= 0 || $travellerId === '') {
    tdJson(false, 'Invalid traveller.');
}

$qStmt = $conn->prepare('SELECT `id`, `lead_id` FROM `crm_quotations` WHERE `id` = ? LIMIT 1');
$qStmt->bind_param('i', $quotationId);
$qStmt->execute();
$qRes = $qStmt->get_result();
$quotation = $qRes ? $qRes->fetch_assoc() : null;
$qStmt->close();
if (!$quotation) {
    tdJson(false, 'Quotation not found.');
}
$leadId = (int) ($quotation['lead_id'] ?? 0);
$travellerKey = tdTravellerKey($quotationId, $travellerId);

function tdResponseDocs(mysqli $conn, string $key): array
{
    return array_map('tdPublicDocument', tdListDocuments($conn, $key));
}

if ($action === 'list') {
    tdJson(true, 'OK', [
        'traveller_key' => $travellerKey,
        'types' => tdDocumentTypes(),
        'documents' => tdResponseDocs($conn, $travellerKey),
        'summary' => tdDocumentsSummary($conn, $travellerKey),
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    tdJson(false, 'Invalid request method.');
}

$type = (string) ($_POST['document_type'] ?? '');
$types = tdDocumentTypes();
if (!isset($types[$type])) {
    tdJson(false, 'Invalid document type.');
}

if ($action === 'delete') {
    $existing = tdGetDocument($conn, $travellerKey, $type);
    if ($existing) {
        tdDeleteDocumentRow($conn, $existing);
    }
    tdJson(true, $types[$type]['label'] . ' deleted.', [
        'documents' => tdResponseDocs($conn, $travellerKey),
        'summary' => tdDocumentsSummary($conn, $travellerKey),
    ]);
}

if ($action !== 'upload') {
    tdJson(false, 'Unknown action.');
}

// Decoding a 4 MB photo (e.g. 6000×4000) in GD needs ~100 MB.
@ini_set('memory_limit', '256M');

$file = $_FILES['file'] ?? null;
if (!is_array($file)) {
    tdJson(false, 'Please choose a file to upload.');
}
$check = tdValidateUpload($file, $type);
if (!$check['ok']) {
    tdJson(false, $check['message']);
}

$stored = tdStoreUpload($conn, $travellerKey, $quotationId, $leadId, $type, $file, $check);
if (!$stored['ok']) {
    tdJson(false, $stored['message'] ?? 'Could not save document.');
}

tdJson(true, $types[$type]['label'] . ' uploaded.', [
    'document' => $stored['document'],
    'pdf_compressed' => $stored['pdf_compressed'],
    'documents' => tdResponseDocs($conn, $travellerKey),
    'summary' => tdDocumentsSummary($conn, $travellerKey),
]);
