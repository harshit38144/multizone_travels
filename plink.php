<?php
session_start();

require_once __DIR__ . '/admin/config/database.php';
require_once __DIR__ . '/includes/payment_helpers.php';

payment_ensure_links_table($conn);

$token = trim((string) ($_GET['t'] ?? ''));
if ($token === '' || !preg_match('/^[a-f0-9]{32}$/', $token)) {
    http_response_code(400);
    echo 'Invalid payment link.';
    exit;
}

$stmt = $conn->prepare(
    'SELECT id, customer_name, customer_email, customer_mobile, remarks, amount_paisa, status, payment_gateway, merchant_order_id
     FROM payment_links WHERE link_token = ? LIMIT 1'
);
if (!$stmt) {
    http_response_code(500);
    echo 'Service unavailable.';
    exit;
}
$stmt->bind_param('s', $token);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    http_response_code(404);
    echo 'Payment link not found.';
    exit;
}

$status = (string) ($row['status'] ?? '');
if ($status === 'paid') {
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>Already paid</title><style>body{font-family:system-ui,sans-serif;max-width:480px;margin:3rem auto;padding:0 1rem;color:#334155}';
    echo '.ok{background:#ecfdf5;border:1px solid #6ee7b7;border-radius:10px;padding:1.25rem}</style></head><body>';
    echo '<div class="ok"><h2>Payment already completed</h2><p>This link has already been used. Thank you.</p></div></body></html>';
    exit;
}
if ($status !== 'active') {
    http_response_code(410);
    echo 'This payment link is no longer active.';
    exit;
}

$gateway = payment_normalize_gateway((string) ($row['payment_gateway'] ?? 'phonepe'));
$customer = [
    'name' => (string) $row['customer_name'],
    'email' => (string) $row['customer_email'],
    'mobile' => (string) $row['customer_mobile'],
    'remarks' => trim((string) ($row['remarks'] ?? '')),
];
$amountPaisa = (int) $row['amount_paisa'];
$linkId = (int) $row['id'];

$start = payment_start_for_gateway($gateway, $customer, $amountPaisa);
if (empty($start['ok'])) {
    http_response_code(500);
    header('Content-Type: text/html; charset=UTF-8');
    $err = htmlspecialchars((string) ($start['error'] ?? 'Could not start payment.'), ENT_QUOTES, 'UTF-8');
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Payment error</title></head><body>';
    echo '<p>' . $err . '</p></body></html>';
    exit;
}

$merchantOrderId = (string) ($start['merchantOrderId'] ?? '');
if ($merchantOrderId !== '') {
    $upd = $conn->prepare('UPDATE payment_links SET merchant_order_id = ? WHERE id = ? AND status = \'active\'');
    if ($upd) {
        $upd->bind_param('si', $merchantOrderId, $linkId);
        $upd->execute();
        $upd->close();
    }
}

payment_store_checkout_context($customer, $amountPaisa, $merchantOrderId, 'payment_link', $linkId, $gateway);
$_SESSION['phonepe_pay_link_id'] = $linkId;
$_SESSION['phonepe_last_order'] = $merchantOrderId;

if ($gateway === 'payu' && !empty($start['formAction']) && !empty($start['formFields']) && is_array($start['formFields'])) {
    payment_redirect_to_payu((string) $start['formAction'], $start['formFields']);
}

if (!empty($start['redirectUrl'])) {
    payment_redirect_to_phonepe((string) $start['redirectUrl']);
}

http_response_code(500);
echo 'Could not redirect to payment gateway.';
