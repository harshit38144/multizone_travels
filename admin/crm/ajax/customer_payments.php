<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/quotation_db.php';
require_once __DIR__ . '/../includes/customer_payments.php';

header('Content-Type: application/json; charset=utf-8');

function cpJson($ok, $msg, $extra = [])
{
    echo json_encode(array_merge(['success' => (bool) $ok, 'message' => (string) $msg], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

cpEnsureTable($conn);

$action = (string) ($_REQUEST['action'] ?? 'list');
$quotationId = (int) ($_REQUEST['quotation_id'] ?? 0);
if ($quotationId <= 0) {
    cpJson(false, 'Invalid quotation.');
}

$qStmt = $conn->prepare('SELECT `id`, `lead_id`, `package_total`, `quotation_total`, `cost_sheet_json` FROM `crm_quotations` WHERE `id` = ? LIMIT 1');
$qStmt->bind_param('i', $quotationId);
$qStmt->execute();
$qRes = $qStmt->get_result();
$quotation = $qRes ? $qRes->fetch_assoc() : null;
$qStmt->close();
if (!$quotation) {
    cpJson(false, 'Quotation not found.');
}
$leadId = (int) ($quotation['lead_id'] ?? 0);

function cpResponse(mysqli $conn, int $quotationId, array $quotation): array
{
    return [
        'payments' => array_map('spPublicPayment', cpListPayments($conn, $quotationId)),
        'paid_total' => cpPaidTotal($conn, $quotationId),
        'package_total' => crmQuotationGrandTotal($quotation),
        'methods' => spPaymentMethods(),
    ];
}

if ($action === 'list') {
    cpJson(true, 'OK', cpResponse($conn, $quotationId, $quotation));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    cpJson(false, 'Invalid request method.');
}

if ($action === 'delete') {
    $row = cpGetPayment($conn, (int) ($_POST['payment_id'] ?? 0));
    if (!$row || (int) $row['quotation_id'] !== $quotationId) {
        cpJson(false, 'Payment not found.');
    }
    $del = $conn->prepare('DELETE FROM `crm_customer_payments` WHERE `id` = ? LIMIT 1');
    $pid = (int) $row['id'];
    $del->bind_param('i', $pid);
    $del->execute();
    $del->close();
    cpJson(true, 'Payment deleted.', cpResponse($conn, $quotationId, $quotation));
}

if ($action !== 'add') {
    cpJson(false, 'Unknown action.');
}

$amount = round((float) str_replace(',', '', (string) ($_POST['amount'] ?? '0')), 2);
if ($amount <= 0) {
    cpJson(false, 'Enter a payment amount greater than 0.');
}
if ($amount > 99999999) {
    cpJson(false, 'Payment amount is too large.');
}

$date = trim((string) ($_POST['payment_date'] ?? ''));
$d = DateTime::createFromFormat('!Y-m-d', $date);
if (!$d || $d->format('Y-m-d') !== $date) {
    cpJson(false, 'Enter a valid payment date.');
}
if ($date > date('Y-m-d', strtotime('+1 day'))) {
    cpJson(false, 'Payment date cannot be in the future.');
}

$method = trim((string) ($_POST['method'] ?? ''));
if (!in_array($method, spPaymentMethods(), true)) {
    cpJson(false, 'Choose a payment method.');
}
$reference = mb_substr(trim((string) ($_POST['reference'] ?? '')), 0, 120);
$notes = mb_substr(trim((string) ($_POST['notes'] ?? '')), 0, 500);
$createdBy = mb_substr(trim((string) ($_SESSION['name'] ?? '')), 0, 120);

$stmt = $conn->prepare(
    'INSERT INTO `crm_customer_payments`
        (`quotation_id`, `lead_id`, `amount`, `payment_date`, `method`, `reference`, `notes`, `created_by`)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
);
if (!$stmt) {
    cpJson(false, 'Could not save payment.');
}
$stmt->bind_param('iidsssss', $quotationId, $leadId, $amount, $date, $method, $reference, $notes, $createdBy);
if (!$stmt->execute()) {
    $stmt->close();
    cpJson(false, 'Could not save payment.');
}
$stmt->close();

cpJson(true, 'Payment of ' . number_format(round($amount), 0) . ' received.', cpResponse($conn, $quotationId, $quotation));
