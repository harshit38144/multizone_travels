<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/quotation_db.php';
require_once __DIR__ . '/../includes/traveller_documents.php';
require_once __DIR__ . '/../includes/traveller_ocr.php';

header('Content-Type: application/json; charset=utf-8');

function tsJson($ok, $msg, $extra = [])
{
    echo json_encode(array_merge(['success' => (bool) $ok, 'message' => (string) $msg], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    tsJson(false, 'Invalid request method.');
}

tdEnsureTable($conn);

$quotationId = (int) ($_POST['quotation_id'] ?? 0);
$travellerId = trim((string) ($_POST['traveller_id'] ?? ''));
$hint = strtolower(trim((string) ($_POST['hint'] ?? 'auto')));
if (!in_array($hint, ['auto', 'passport', 'aadhaar', 'pan', 'visa', 'driving_licence', 'voter_id', 'other'], true)) {
    $hint = 'auto';
}
if ($quotationId <= 0 || $travellerId === '') {
    tsJson(false, 'Invalid traveller.');
}

$qStmt = $conn->prepare('SELECT `id`, `lead_id` FROM `crm_quotations` WHERE `id` = ? LIMIT 1');
$qStmt->bind_param('i', $quotationId);
$qStmt->execute();
$qRes = $qStmt->get_result();
$quotation = $qRes ? $qRes->fetch_assoc() : null;
$qStmt->close();
if (!$quotation) {
    tsJson(false, 'Quotation not found.');
}
$leadId = (int) ($quotation['lead_id'] ?? 0);
$travellerKey = tdTravellerKey($quotationId, $travellerId);

@ini_set('memory_limit', '256M');
@set_time_limit(120);

$file = $_FILES['file'] ?? null;
if (!is_array($file)) {
    tsJson(false, 'Please choose a file to upload.');
}
$ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
if (!in_array($ext, ['jpg', 'jpeg', 'png', 'pdf'], true)) {
    tsJson(false, 'Only JPG, PNG or PDF files are allowed.');
}
$check = tdValidateUpload($file, 'other');
if (!$check['ok']) {
    tsJson(false, $check['message']);
}

// OCR runs on the original upload (before compression) for the best read quality.
$ocr = tocrExtract($file['tmp_name'], $check['mime'], $hint);
$result = !empty($ocr['ok']) ? $ocr['result'] : null;

$slot = 'other';
if ($result && $result['is_document']) {
    $slot = $result['document_kind']['slot'];
} elseif (in_array($hint, ['passport', 'aadhaar', 'pan', 'visa'], true)) {
    $slot = $hint;
}

$stored = tdStoreUpload($conn, $travellerKey, $quotationId, $leadId, $slot, $file, $check);
if (!$stored['ok']) {
    tsJson(false, $stored['message'] ?? 'Could not store the document.');
}

// Existing contact with the same document number / name → link instead of creating a duplicate.
$match = null;
if ($result && $leadId > 0 && crmQuotationLoadContactsLib($conn)) {
    $match = crmQuotationFindContactMatch($conn, $leadId, [
        'name' => (string) $result['fields']['name'],
        'passport_number' => (string) $result['fields']['passport_number'],
        'id_number' => (string) $result['fields']['id_number'],
    ]);
}

$extra = [
    'traveller_key' => $travellerKey,
    'document' => $stored['document'],
    'document_type' => $slot,
    'summary' => tdDocumentsSummary($conn, $travellerKey),
    'ocr' => [
        'ok' => (bool) $result,
        'error' => $result ? '' : (string) ($ocr['error'] ?? 'Could not read the document.'),
        'is_document' => $result ? (bool) $result['is_document'] : null,
        'document_label' => $result ? $result['document_kind']['label'] : '',
        'confidence' => $result ? $result['confidence'] : '',
        'fields' => $result ? $result['fields'] : new stdClass(),
        'filled' => $result ? $result['filled'] : [],
    ],
    'match' => $match,
];

if (!$result) {
    tsJson(true, 'Document saved, but the details could not be read automatically. Please fill them manually.', $extra);
}
if (!$result['is_document']) {
    tsJson(true, 'Document saved, but it does not look like an identity document. Please check and fill the details manually.', $extra);
}
if (!$result['filled']) {
    tsJson(true, 'Document saved, but no details could be read. Try a clearer scan or fill them manually.', $extra);
}
tsJson(true, $result['document_kind']['label'] . ' read successfully. Please verify the details before saving.', $extra);
