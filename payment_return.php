<?php
session_start();

require_once __DIR__ . '/admin/config/database.php';
require_once __DIR__ . '/includes/payment_helpers.php';

payment_ensure_links_table($conn);

$gateway = 'phonepe';
$merchantOrderId = payment_request_merchant_order_id();
$paid = false;
$message = '';
$receipt = null;

// PayU posts back to surl/furl
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['txnid'])) {
    $gateway = 'payu';
    require_once __DIR__ . '/PayUCheckout.php';
    try {
        $pg = new PayUCheckout();
        $merchantOrderId = payment_sanitize_order_id((string) $_POST['txnid']);
        $hashOk = $pg->verifyReturnHash($_POST);
        $status = strtolower((string) ($_POST['status'] ?? ''));
        if ($hashOk && $status === 'success') {
            $paid = true;
            payment_mark_payment_link_paid($conn, $merchantOrderId);
            $message = 'Payment successful. Thank you!';
        } else {
            $message = $status === 'success'
                ? 'Payment response could not be verified.'
                : 'Payment was not completed.';
        }
        $receipt = payment_resolve_receipt_details($conn, $merchantOrderId, null);
    } catch (Throwable $e) {
        $message = 'Payment processing error.';
    }
} else {
    // PhonePe redirect (GET)
    if ($merchantOrderId !== '') {
        require_once __DIR__ . '/PhonePeCheckout.php';
        try {
            $pg = new PhonePeCheckout();
            $st = $pg->getOrderStatus($merchantOrderId);
            if (!empty($st['ok']) && is_array($st['json'])) {
                $label = payment_extract_order_status_label($st['json']);
                if (payment_order_status_is_completed($st['json'], $label)) {
                    $paid = true;
                    payment_mark_payment_link_paid($conn, $merchantOrderId);
                    $message = 'Payment successful. Thank you!';
                } else {
                    $message = 'Payment status: ' . ($label !== '' ? $label : 'pending or failed') . '.';
                }
                $receipt = payment_resolve_receipt_details($conn, $merchantOrderId, $st['json']);
            } else {
                $message = isset($st['error']) ? (string) $st['error'] : 'Could not verify payment status.';
            }
        } catch (Throwable $e) {
            $message = 'Could not verify payment with PhonePe.';
        }
    } else {
        $message = 'No payment reference received.';
    }
}

if ($receipt === null && $merchantOrderId !== '') {
    $receipt = payment_resolve_receipt_details($conn, $merchantOrderId, null);
}
if (is_array($receipt) && !empty($receipt['payment_gateway'])) {
    $gateway = payment_normalize_gateway((string) $receipt['payment_gateway']);
}

$amountDisplay = is_array($receipt) ? payment_format_inr_from_paisa((int) ($receipt['amount_paisa'] ?? 0)) : '—';
$name = is_array($receipt) ? (string) ($receipt['name'] ?? '') : '';
$email = is_array($receipt) ? (string) ($receipt['email'] ?? '') : '';
$gatewayLabel = payment_gateway_label($gateway);

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $paid ? 'Payment successful' : 'Payment status' ?> — Multizone Travels</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #f4f7fb; margin: 0; padding: 2rem 1rem; color: #334155; }
        .card { max-width: 520px; margin: 0 auto; background: #fff; border-radius: 12px; box-shadow: 0 2px 16px rgba(0,0,0,.08); overflow: hidden; }
        .head { padding: 1.25rem 1.5rem; color: #fff; background: <?= $paid ? 'linear-gradient(135deg,#059669,#10b981)' : 'linear-gradient(135deg,#b45309,#f59e0b)' ?>; }
        .body { padding: 1.5rem; }
        .row { display: flex; justify-content: space-between; gap: 1rem; padding: .45rem 0; border-bottom: 1px solid #eef2f7; font-size: .95rem; }
        .row:last-child { border-bottom: 0; }
        .muted { color: #64748b; }
        .badge { display: inline-block; padding: .2rem .55rem; border-radius: 999px; background: #eef2ff; color: #3730a3; font-size: .75rem; font-weight: 700; }
    </style>
</head>
<body>
    <div class="card">
        <div class="head">
            <h1 style="margin:0;font-size:1.35rem;"><?= $paid ? 'Payment successful' : 'Payment status' ?></h1>
            <p style="margin:.35rem 0 0;opacity:.92;font-size:.92rem;"><?= htmlspecialchars($message) ?></p>
        </div>
        <div class="body">
            <?php if (is_array($receipt)) { ?>
                <div class="row"><span class="muted">Gateway</span><span><span class="badge"><?= htmlspecialchars($gatewayLabel) ?></span></span></div>
                <?php if ($name !== '') { ?><div class="row"><span class="muted">Name</span><span><?= htmlspecialchars($name) ?></span></div><?php } ?>
                <?php if ($email !== '') { ?><div class="row"><span class="muted">Email</span><span><?= htmlspecialchars($email) ?></span></div><?php } ?>
                <div class="row"><span class="muted">Amount</span><strong><?= htmlspecialchars($amountDisplay) ?></strong></div>
                <?php if ($merchantOrderId !== '') { ?><div class="row"><span class="muted">Order ID</span><span style="font-size:.82rem;word-break:break-all;"><?= htmlspecialchars($merchantOrderId) ?></span></div><?php } ?>
            <?php } else { ?>
                <p class="muted" style="margin:0;">If you completed payment, we will confirm it shortly. You may close this page.</p>
            <?php } ?>
        </div>
    </div>
</body>
</html>
