<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/quotation_db.php';

header('Content-Type: application/json; charset=utf-8');

function qConfirmJson($ok, $msg, $extra = [])
{
    echo json_encode(array_merge(['success' => (bool) $ok, 'message' => (string) $msg], $extra));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    qConfirmJson(false, 'Invalid request.');
}

crmEnsureQuotationTables($conn);

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    qConfirmJson(false, 'Invalid quotation.');
}

$stmt = $conn->prepare(
    'SELECT `id`, `quotation_uid`, `guest_name`, `mobile_no`, `tour_confirmed`, `tour_confirm_json`
     FROM `crm_quotations` WHERE `id` = ? LIMIT 1'
);
if (!$stmt) {
    qConfirmJson(false, 'Could not load quotation.');
}
$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result();
$row = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$row) {
    qConfirmJson(false, 'Quotation not found.');
}

$stored = json_decode((string) ($row['tour_confirm_json'] ?? ''), true);
$payload = crmQuotationNormalizeConfirmPayload(is_array($stored) ? $stored : []);
if ($payload['guest_name'] === '') {
    $payload['guest_name'] = (string) $row['guest_name'];
}
if ($payload['mobile_no'] === '') {
    $payload['mobile_no'] = (string) $row['mobile_no'];
}

qConfirmJson(true, 'OK', [
    'quotation' => [
        'id' => (int) $row['id'],
        'quotation_uid' => (string) $row['quotation_uid'],
        'guest_name' => (string) $row['guest_name'],
        'mobile_no' => (string) $row['mobile_no'],
        'tour_confirmed' => (int) ($row['tour_confirmed'] ?? 0),
    ],
    'confirm' => $payload,
    'services' => crmQuotationConfirmServiceMap(),
]);
