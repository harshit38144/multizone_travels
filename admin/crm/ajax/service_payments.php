<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/quotation_db.php';
require_once __DIR__ . '/../includes/service_payments.php';

header('Content-Type: application/json; charset=utf-8');

function spJson($ok, $msg, $extra = [])
{
    echo json_encode(array_merge(['success' => (bool) $ok, 'message' => (string) $msg], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

spEnsureTable($conn);

$action = (string) ($_REQUEST['action'] ?? 'list');
$quotationId = (int) ($_REQUEST['quotation_id'] ?? 0);
$serviceUid = svSanitizeUid((string) ($_REQUEST['service_uid'] ?? ''));
if ($quotationId <= 0 || $serviceUid === '') {
    spJson(false, 'Invalid service.');
}

$qStmt = $conn->prepare('SELECT `id`, `lead_id` FROM `crm_quotations` WHERE `id` = ? LIMIT 1');
$qStmt->bind_param('i', $quotationId);
$qStmt->execute();
$qRes = $qStmt->get_result();
$quotation = $qRes ? $qRes->fetch_assoc() : null;
$qStmt->close();
if (!$quotation) {
    spJson(false, 'Quotation not found.');
}
$leadId = (int) ($quotation['lead_id'] ?? 0);

function spResponseList(mysqli $conn, int $quotationId, string $serviceUid): array
{
    return array_map('spPublicPayment', spListPayments($conn, $quotationId, $serviceUid));
}

if ($action === 'list') {
    spJson(true, 'OK', [
        'payments' => spResponseList($conn, $quotationId, $serviceUid),
        'methods' => spPaymentMethods(),
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    spJson(false, 'Invalid request method.');
}

if ($action === 'delete') {
    $row = spGetPayment($conn, (int) ($_POST['payment_id'] ?? 0));
    if (!$row || (int) $row['quotation_id'] !== $quotationId || (string) $row['service_uid'] !== $serviceUid) {
        spJson(false, 'Payment not found.');
    }
    $del = $conn->prepare('DELETE FROM `crm_service_payments` WHERE `id` = ? LIMIT 1');
    $pid = (int) $row['id'];
    $del->bind_param('i', $pid);
    $del->execute();
    $del->close();
    $amount = round((float) $row['amount'], 2);
    spAdjustStoredPaid($conn, $quotationId, $serviceUid, -$amount);
    spJson(true, 'Payment deleted.', [
        'amount' => $amount,
        'payments' => spResponseList($conn, $quotationId, $serviceUid),
    ]);
}

if ($action !== 'add') {
    spJson(false, 'Unknown action.');
}

$amount = round((float) str_replace(',', '', (string) ($_POST['amount'] ?? '0')), 2);
if ($amount <= 0) {
    spJson(false, 'Enter a payment amount greater than 0.');
}
if ($amount > 99999999) {
    spJson(false, 'Payment amount is too large.');
}

$date = trim((string) ($_POST['payment_date'] ?? ''));
$d = DateTime::createFromFormat('!Y-m-d', $date);
if (!$d || $d->format('Y-m-d') !== $date) {
    spJson(false, 'Enter a valid payment date.');
}
if ($date > date('Y-m-d', strtotime('+1 day'))) {
    spJson(false, 'Payment date cannot be in the future.');
}

$method = trim((string) ($_POST['method'] ?? ''));
if (!in_array($method, spPaymentMethods(), true)) {
    spJson(false, 'Choose a payment method.');
}
$reference = mb_substr(trim((string) ($_POST['reference'] ?? '')), 0, 120);
$notes = mb_substr(trim((string) ($_POST['notes'] ?? '')), 0, 500);
$serviceKey = substr((string) preg_replace('/[^A-Za-z0-9_\-]/', '', (string) ($_POST['service_key'] ?? '')), 0, 40);
$createdBy = mb_substr(trim((string) ($_SESSION['name'] ?? $_SESSION['username'] ?? $_SESSION['admin_name'] ?? '')), 0, 120);

$stmt = $conn->prepare(
    'INSERT INTO `crm_service_payments`
        (`quotation_id`, `lead_id`, `service_uid`, `service_key`, `amount`, `payment_date`, `method`, `reference`, `notes`, `created_by`)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);
if (!$stmt) {
    spJson(false, 'Could not save payment.');
}
$stmt->bind_param('iissdsssss', $quotationId, $leadId, $serviceUid, $serviceKey, $amount, $date, $method, $reference, $notes, $createdBy);
if (!$stmt->execute()) {
    $stmt->close();
    spJson(false, 'Could not save payment.');
}
$newId = (int) $stmt->insert_id;
$stmt->close();

spAdjustStoredPaid($conn, $quotationId, $serviceUid, $amount);

$saved = spGetPayment($conn, $newId);
spJson(true, 'Payment of ' . number_format(round($amount), 0) . ' recorded.', [
    'payment' => $saved ? spPublicPayment($saved) : null,
    'amount' => $amount,
    'payments' => spResponseList($conn, $quotationId, $serviceUid),
]);
