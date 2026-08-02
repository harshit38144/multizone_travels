<?php
declare(strict_types=1);

/**
 * Shared helpers for pay.php, pay_link.php, and admin payment links.
 */

function payment_load_config(): array
{
    static $cfg = null;
    if ($cfg === null) {
        $path = __DIR__ . '/phonepe_config.php';
        $cfg = is_readable($path) ? (require $path) : [];
        if (!is_array($cfg)) {
            $cfg = [];
        }
    }
    return $cfg;
}

/** @return array<string,string> */
function payment_gateway_options(): array
{
    return [
        'phonepe' => 'PhonePe',
        'payu' => 'PayU',
    ];
}

function payment_normalize_gateway(string $gateway): string
{
    $gateway = strtolower(trim($gateway));
    return array_key_exists($gateway, payment_gateway_options()) ? $gateway : 'phonepe';
}

function payment_gateway_label(string $gateway): string
{
    $options = payment_gateway_options();
    $gateway = payment_normalize_gateway($gateway);
    return $options[$gateway] ?? 'PhonePe';
}

function payment_order_prefix_for_gateway(string $gateway): string
{
    return payment_normalize_gateway($gateway) === 'payu' ? 'MZPAYU' : 'MZLINK';
}

function payment_load_payu_config(): array
{
    static $cfg = null;
    if ($cfg === null) {
        $path = __DIR__ . '/payu_config.php';
        $cfg = is_readable($path) ? (require $path) : [];
        if (!is_array($cfg)) {
            $cfg = [];
        }
    }
    return $cfg;
}

function payment_is_https_request(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    if (isset($_SERVER['SERVER_PORT']) && (string) $_SERVER['SERVER_PORT'] === '443') {
        return true;
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
        return true;
    }
    return false;
}

function payment_public_base_url(): string
{
    $cfg = payment_load_config();
    $configured = trim((string) ($cfg['public_site_url'] ?? ''));
    if ($configured !== '') {
        return rtrim($configured, '/');
    }

    $scheme = payment_is_https_request() ? 'https' : 'http';
    $host = isset($_SERVER['HTTP_HOST']) ? (string) $_SERVER['HTTP_HOST'] : 'localhost';
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/');
    $dir = rtrim(dirname($script), '/');
    if (strlen($dir) >= 6 && substr($dir, -6) === '/admin') {
        $dir = substr($dir, 0, -6);
    }
    if ($dir === '' || $dir === '.') {
        return $scheme . '://' . $host;
    }
    return $scheme . '://' . $host . $dir;
}

function payment_return_url(): string
{
    return payment_public_base_url() . '/payment_return.php';
}

/**
 * @return string|null Error message if redirect URL is invalid for current mode.
 */
function payment_validate_for_checkout(): ?string
{
    $cfg = payment_load_config();
    $mode = (string) ($cfg['mode'] ?? 'sandbox');
    $redirect = payment_return_url();

    if ($mode === 'production') {
        if (stripos($redirect, 'https://') !== 0) {
            return 'Production PhonePe requires HTTPS. Set public_site_url in includes/phonepe_config.php to your live https:// domain (must be whitelisted in PhonePe dashboard).';
        }
        $host = parse_url($redirect, PHP_URL_HOST);
        if ($host === 'localhost' || $host === '127.0.0.1') {
            return 'Production payments cannot use localhost. Set public_site_url to your live domain or use mode sandbox for local testing.';
        }
    }

    return null;
}

function payment_sanitize_order_id(string $raw): string
{
    $raw = preg_replace('/[^A-Za-z0-9_-]/', '', $raw) ?? '';
    if (strlen($raw) > 63) {
        $raw = substr($raw, 0, 63);
    }
    return $raw !== '' ? $raw : ('MZPAY' . bin2hex(random_bytes(8)));
}

function payment_generate_link_token(): string
{
    return bin2hex(random_bytes(16));
}

/**
 * Build safe metaInfo (PhonePe udf rules).
 *
 * @param array{name:string,email:string,mobile:string,remarks?:string} $customer
 * @return array<string,string>
 */
function payment_build_meta(array $customer, int $amountPaisa): array
{
    $mobileDigits = preg_replace('/\D/', '', $customer['mobile']);
    $meta = [
        'udf1' => substr($customer['name'], 0, 256),
        'udf2' => substr($customer['email'], 0, 256),
        'udf3' => substr($mobileDigits, 0, 256),
    ];
    $remarks = trim((string) ($customer['remarks'] ?? ''));
    if ($remarks !== '') {
        $meta['udf4'] = substr($remarks, 0, 256);
    }
    return $meta;
}

/**
 * @param array{name:string,email:string,mobile:string,remarks?:string} $customer
 * @return array{ok:bool, redirectUrl?:string, merchantOrderId?:string, error?:string, phonepeUrl?:string}
 */
function payment_start_phonepe(array $customer, int $amountPaisa, string $orderPrefix = 'MZPAY'): array
{
    require_once __DIR__ . '/PhonePeCheckout.php';

    $configError = payment_validate_for_checkout();
    if ($configError !== null) {
        return ['ok' => false, 'error' => $configError];
    }

    $merchantOrderId = payment_sanitize_order_id(
        $orderPrefix . '-' . gmdate('YmdHis') . '-' . bin2hex(random_bytes(3))
    );
    $redirectUrl = payment_return_url();
    $meta = payment_build_meta($customer, $amountPaisa);

    try {
        $pg = new PhonePeCheckout();
        $res = $pg->createPayment($merchantOrderId, $amountPaisa, $redirectUrl, $meta);
        if (!empty($res['ok']) && !empty($res['redirectUrl'])) {
            return [
                'ok' => true,
                'redirectUrl' => (string) $res['redirectUrl'],
                'phonepeUrl' => (string) $res['redirectUrl'],
                'merchantOrderId' => $merchantOrderId,
            ];
        }
        return ['ok' => false, 'error' => isset($res['error']) ? (string) $res['error'] : 'Could not start payment.'];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => 'Payment setup error. Please try again later.'];
    }
}

/**
 * @param array{name:string,email:string,mobile:string,remarks?:string} $customer
 * @return array{ok:bool, txnid?:string, formAction?:string, formFields?:array<string,string>, error?:string}
 */
function payment_start_payu(array $customer, int $amountPaisa, string $orderPrefix = 'MZPAYU'): array
{
    require_once __DIR__ . '/PayUCheckout.php';

    try {
        $pg = new PayUCheckout();
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => 'PayU is not configured.'];
    }

    if (!$pg->isConfigured()) {
        return ['ok' => false, 'error' => 'PayU merchant key and salt are missing. Update includes/payu_config.php.'];
    }

    $txnid = payment_sanitize_order_id(
        $orderPrefix . '-' . gmdate('YmdHis') . '-' . bin2hex(random_bytes(3))
    );
    $amountInr = $amountPaisa / 100;
    $productinfo = 'Multizone Travels Payment';
    $remarks = trim((string) ($customer['remarks'] ?? ''));
    if ($remarks !== '') {
        $productinfo = substr($remarks, 0, 100);
    }

    $returnUrl = payment_return_url();
    $fields = $pg->buildPaymentFields(
        $txnid,
        $amountInr,
        $customer,
        $productinfo,
        $returnUrl,
        $returnUrl
    );

    return [
        'ok' => true,
        'txnid' => $txnid,
        'merchantOrderId' => $txnid,
        'formAction' => $pg->paymentEndpoint(),
        'formFields' => $fields,
    ];
}

/**
 * @param array{name:string,email:string,mobile:string,remarks?:string} $customer
 * @return array{ok:bool, redirectUrl?:string, merchantOrderId?:string, error?:string, phonepeUrl?:string, formAction?:string, formFields?:array<string,string>, gateway?:string}
 */
function payment_start_for_gateway(string $gateway, array $customer, int $amountPaisa, string $orderPrefix = ''): array
{
    $gateway = payment_normalize_gateway($gateway);
    if ($orderPrefix === '') {
        $orderPrefix = payment_order_prefix_for_gateway($gateway);
    }

    if ($gateway === 'payu') {
        $res = payment_start_payu($customer, $amountPaisa, $orderPrefix);
        if (!empty($res['ok'])) {
            $res['gateway'] = 'payu';
        }
        return $res;
    }

    $res = payment_start_phonepe($customer, $amountPaisa, $orderPrefix);
    if (!empty($res['ok'])) {
        $res['gateway'] = 'phonepe';
    }
    return $res;
}

/**
 * Auto-submit HTML form to PayU hosted checkout.
 *
 * @param array<string,string> $fields
 * @return never
 */
function payment_redirect_to_payu(string $formAction, array $fields): void
{
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">';
    echo '<meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>Redirecting to PayU…</title>';
    echo '<style>body{font-family:system-ui,sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;background:#f4f7fb}';
    echo '.box{text-align:center;padding:2rem;max-width:420px}.spin{width:40px;height:40px;border:4px solid #e2e8f0;border-top-color:#0d9488;border-radius:50%;animation:r 1s linear infinite;margin:0 auto 1rem}';
    echo '@keyframes r{to{transform:rotate(360deg)}}p{color:#64748b;font-size:.95rem;line-height:1.5}</style></head><body>';
    echo '<div class="box"><div class="spin"></div><p>Redirecting to secure PayU checkout…</p></div>';
    echo '<form id="payuForm" method="post" action="' . htmlspecialchars($formAction, ENT_QUOTES, 'UTF-8') . '">';
    foreach ($fields as $name => $value) {
        echo '<input type="hidden" name="' . htmlspecialchars((string) $name, ENT_QUOTES, 'UTF-8') . '" value="'
            . htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') . '">';
    }
    echo '</form><script>document.getElementById("payuForm").submit();</script></body></html>';
    exit;
}

/**
 * Same-window redirect page (PhonePe rejects checkout opened in a new tab).
 *
 * @return never
 */
function payment_redirect_to_phonepe(string $phonepeUrl): void
{
    $safeUrl = htmlspecialchars($phonepeUrl, ENT_QUOTES, 'UTF-8');
    $jsUrl = json_encode($phonepeUrl, JSON_UNESCAPED_SLASHES);
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">';
    echo '<meta name="referrer" content="strict-origin-when-cross-origin">';
    echo '<meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>Redirecting to PhonePe…</title>';
    echo '<style>body{font-family:system-ui,sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;background:#f4f7fb}';
    echo '.box{text-align:center;padding:2rem;max-width:420px}.spin{width:40px;height:40px;border:4px solid #e2e8f0;border-top-color:#5f259f;border-radius:50%;animation:r 1s linear infinite;margin:0 auto 1rem}';
    echo '@keyframes r{to{transform:rotate(360deg)}}p{color:#64748b;font-size:.95rem;line-height:1.5}</style></head><body>';
    echo '<div class="box"><div class="spin"></div><p>Redirecting to secure PhonePe checkout…</p>';
    echo '<p><small>On mobile, choose <strong>Pay by any UPI app</strong>. On desktop, click <strong>Click to view QR</strong> if needed.</small></p>';
    echo '<p><a href="' . $safeUrl . '">Continue to payment</a></p></div>';
    echo '<script>setTimeout(function(){window.location.replace(' . $jsUrl . ');},400);</script>';
    echo '</body></html>';
    exit;
}

function payment_ensure_links_table(mysqli $conn): void
{
    $conn->query("CREATE TABLE IF NOT EXISTS `payment_links` (
        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `link_token` VARCHAR(64) NOT NULL,
        `customer_name` VARCHAR(120) NOT NULL,
        `customer_email` VARCHAR(200) NOT NULL,
        `customer_mobile` VARCHAR(20) NOT NULL,
        `remarks` VARCHAR(500) DEFAULT NULL,
        `amount_paisa` INT UNSIGNED NOT NULL,
        `payment_gateway` ENUM('phonepe','payu') NOT NULL DEFAULT 'phonepe',
        `status` ENUM('active','paid','cancelled') NOT NULL DEFAULT 'active',
        `merchant_order_id` VARCHAR(64) DEFAULT NULL,
        `created_by` INT UNSIGNED DEFAULT NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `paid_at` TIMESTAMP NULL DEFAULT NULL,
        UNIQUE KEY `uk_link_token` (`link_token`),
        KEY `idx_status` (`status`),
        KEY `idx_merchant_order_id` (`merchant_order_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $existing = [];
    $cols = $conn->query('SHOW COLUMNS FROM `payment_links`');
    if ($cols) {
        while ($col = $cols->fetch_assoc()) {
            $existing[strtolower((string) ($col['Field'] ?? ''))] = true;
        }
        $cols->free();
    }
    if (!isset($existing['merchant_order_id'])) {
        $conn->query(
            "ALTER TABLE `payment_links` ADD COLUMN `merchant_order_id` VARCHAR(64) DEFAULT NULL AFTER `status`"
        );
    }
    if (!isset($existing['paid_at'])) {
        $conn->query(
            "ALTER TABLE `payment_links` ADD COLUMN `paid_at` TIMESTAMP NULL DEFAULT NULL AFTER `created_at`"
        );
    }
    if (!isset($existing['payment_gateway'])) {
        $conn->query(
            "ALTER TABLE `payment_links` ADD COLUMN `payment_gateway` ENUM('phonepe','payu') NOT NULL DEFAULT 'phonepe' AFTER `amount_paisa`"
        );
    }
}

/** Read merchant order id from PhonePe redirect or session (many param names). */
function payment_request_merchant_order_id(): string
{
    $getKeys = ['merchantOrderId', 'merchant_order_id', 'merchantOrderID', 'orderId', 'order_id'];
    foreach ($getKeys as $key) {
        if (!empty($_GET[$key])) {
            $id = trim((string) $_GET[$key]);
            if ($id !== '') {
                return payment_sanitize_order_id($id);
            }
        }
    }
    if (!empty($_SESSION['phonepe_last_order'])) {
        return payment_sanitize_order_id((string) $_SESSION['phonepe_last_order']);
    }
    $ctx = $_SESSION['phonepe_checkout'] ?? null;
    if (is_array($ctx) && !empty($ctx['merchant_order_id'])) {
        return payment_sanitize_order_id((string) $ctx['merchant_order_id']);
    }
    return '';
}

function payment_extract_order_status_label(?array $json): string
{
    if (!is_array($json)) {
        return '';
    }
    foreach (['state', 'status', 'paymentState'] as $key) {
        if (!empty($json[$key]) && is_scalar($json[$key])) {
            return (string) $json[$key];
        }
    }
    foreach (['payload', 'data', 'paymentDetails'] as $nested) {
        if (!empty($json[$nested]) && is_array($json[$nested])) {
            $inner = payment_extract_order_status_label($json[$nested]);
            if ($inner !== '') {
                return $inner;
            }
        }
    }
    return '';
}

function payment_order_status_is_completed(?array $statusJson, string $statusLabel): bool
{
    $label = strtoupper(trim($statusLabel));
    $completed = ['COMPLETED', 'SUCCESS', 'PAYMENT_SUCCESS', 'PAID', 'CAPTURED'];
    if (in_array($label, $completed, true)) {
        return true;
    }
    if (!is_array($statusJson)) {
        return false;
    }
    $queue = [$statusJson];
    while ($queue !== []) {
        $node = array_shift($queue);
        if (!is_array($node)) {
            continue;
        }
        foreach (['state', 'status', 'paymentState', 'payResponseCode'] as $key) {
            if (!isset($node[$key]) || !is_scalar($node[$key])) {
                continue;
            }
            if (in_array(strtoupper(trim((string) $node[$key])), $completed, true)) {
                return true;
            }
        }
        foreach ($node as $value) {
            if (is_array($value)) {
                $queue[] = $value;
            }
        }
    }
    return false;
}

/**
 * Mark payment link row as paid after successful PhonePe payment.
 *
 * @return int Rows updated (0 or more)
 */
function payment_mark_payment_link_paid(mysqli $conn, string $merchantOrderId): int
{
    payment_ensure_links_table($conn);
    $updated = 0;

    $markById = static function (int $linkId) use ($conn): int {
        if ($linkId < 1) {
            return 0;
        }
        $stmt = $conn->prepare(
            "UPDATE payment_links SET status = 'paid', paid_at = NOW() WHERE id = ? AND status = 'active'"
        );
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param('i', $linkId);
        $stmt->execute();
        $n = $stmt->affected_rows;
        $stmt->close();
        return max(0, $n);
    };

    if (!empty($_SESSION['phonepe_pay_link_id'])) {
        $updated += $markById((int) $_SESSION['phonepe_pay_link_id']);
    }

    $ctx = $_SESSION['phonepe_checkout'] ?? null;
    if (is_array($ctx) && !empty($ctx['payment_link_id'])) {
        $updated += $markById((int) $ctx['payment_link_id']);
    }

    if ($merchantOrderId !== '' && (strpos($merchantOrderId, 'MZLINK') === 0 || strpos($merchantOrderId, 'MZPAYU') === 0)) {
        $stmt = $conn->prepare(
            "UPDATE payment_links SET status = 'paid', paid_at = NOW()
             WHERE merchant_order_id = ? AND status = 'active'"
        );
        if ($stmt) {
            $stmt->bind_param('s', $merchantOrderId);
            $stmt->execute();
            $updated += max(0, $stmt->affected_rows);
            $stmt->close();
        }
    }

    return $updated;
}

/**
 * Admin: verify PhonePe status and mark link paid if completed.
 *
 * @return array{ok:bool, message:string}
 */
function payment_sync_payment_link_row(mysqli $conn, int $linkId): array
{
    payment_ensure_links_table($conn);
    if ($linkId < 1) {
        return ['ok' => false, 'message' => 'Invalid link.'];
    }

    $stmt = $conn->prepare(
        'SELECT id, merchant_order_id, status, payment_gateway FROM payment_links WHERE id = ? LIMIT 1'
    );
    if (!$stmt) {
        return ['ok' => false, 'message' => 'Database error.'];
    }
    $stmt->bind_param('i', $linkId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        return ['ok' => false, 'message' => 'Payment link not found.'];
    }
    if (($row['status'] ?? '') === 'paid') {
        return ['ok' => true, 'message' => 'Already marked as paid.'];
    }
    if (($row['status'] ?? '') !== 'active') {
        return ['ok' => false, 'message' => 'Link is not active.'];
    }

    $orderId = trim((string) ($row['merchant_order_id'] ?? ''));
    if ($orderId === '') {
        return ['ok' => false, 'message' => 'No payment order on this link yet. Customer must open the link and start payment first.'];
    }

    $gateway = payment_normalize_gateway((string) ($row['payment_gateway'] ?? 'phonepe'));

    if ($gateway === 'payu') {
        require_once __DIR__ . '/PayUCheckout.php';
        try {
            $pg = new PayUCheckout();
            $st = $pg->verifyTransaction($orderId);
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => 'Could not check payment status with PayU.'];
        }

        if (empty($st['ok']) || !is_array($st['json'])) {
            $err = isset($st['error']) ? (string) $st['error'] : 'Status check failed.';
            return ['ok' => false, 'message' => $err];
        }

        if (!PayUCheckout::verifyStatusIsSuccess($st['json'], $orderId)) {
            $status = $st['json']['transaction_details'][$orderId]['status'] ?? 'not completed';
            return ['ok' => false, 'message' => 'PayU status: ' . (string) $status . '.'];
        }
    } else {
        require_once __DIR__ . '/PhonePeCheckout.php';
        try {
            $pg = new PhonePeCheckout();
            $st = $pg->getOrderStatus($orderId);
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => 'Could not check payment status with PhonePe.'];
        }

        if (empty($st['ok']) || !is_array($st['json'])) {
            $err = isset($st['error']) ? (string) $st['error'] : 'Status check failed.';
            return ['ok' => false, 'message' => $err];
        }

        $label = payment_extract_order_status_label($st['json']);
        if (!payment_order_status_is_completed($st['json'], $label)) {
            return ['ok' => false, 'message' => 'PhonePe status: ' . ($label !== '' ? $label : 'not completed') . '.'];
        }
    }

    $upd = $conn->prepare(
        "UPDATE payment_links SET status = 'paid', paid_at = NOW() WHERE id = ? AND status = 'active'"
    );
    if (!$upd) {
        return ['ok' => false, 'message' => 'Database error updating status.'];
    }
    $upd->bind_param('i', $linkId);
    $upd->execute();
    $n = $upd->affected_rows;
    $upd->close();

    if ($n < 1) {
        return ['ok' => false, 'message' => 'Status could not be updated.'];
    }

    return ['ok' => true, 'message' => 'Payment verified and marked as paid.'];
}

function payment_link_url(string $token): string
{
    // plink.php (no underscore) + slash before ? — WhatsApp Android linkifies the full URL reliably
    return payment_public_base_url() . '/plink.php/?t=' . rawurlencode($token);
}

/** Normalize payment URL for WhatsApp (slash before query string). */
function payment_link_url_for_whatsapp(string $url): string
{
    return (string) preg_replace('#(\.php)\?#', '$1/?', trim($url));
}

/** WhatsApp wa.me phone: digits only, India 10-digit → 91 prefix. */
function payment_whatsapp_phone(string $mobile): string
{
    $digits = preg_replace('/\D/', '', $mobile);
    if (strlen($digits) === 10) {
        return '91' . $digits;
    }
    if (strlen($digits) === 11 && $digits[0] === '0') {
        return '91' . substr($digits, 1);
    }
    return $digits;
}

function payment_whatsapp_payment_link_message(string $customerName, string $amountDisplay, string $paymentUrl): string
{
    $name = trim($customerName) !== '' ? trim($customerName) : 'Customer';
    $link = payment_link_url_for_whatsapp($paymentUrl);

    // URL on its own line at the top; plain ASCII only (₹ can break link detection on some devices)
    return $link . "\n\n"
        . "Hello {$name},\n\n"
        . "Please complete your payment using the link above.\n"
        . "Amount: Rs. {$amountDisplay}\n\n"
        . "Thank you,\nMultizone Travels";
}

function payment_whatsapp_send_url(string $mobile, string $message): string
{
    return 'https://wa.me/' . payment_whatsapp_phone($mobile) . '?text=' . rawurlencode($message);
}

/**
 * Persist customer/amount context for payment_return.php receipt display.
 *
 * @param array{name:string,email:string,mobile:string,remarks?:string} $customer
 */
function payment_store_checkout_context(
    array $customer,
    int $amountPaisa,
    string $merchantOrderId,
    string $source,
    ?int $paymentLinkId = null,
    string $gateway = 'phonepe'
): void {
    $_SESSION['phonepe_checkout'] = [
        'source' => $source === 'payment_link' ? 'payment_link' : 'pay',
        'payment_gateway' => payment_normalize_gateway($gateway),
        'name' => (string) $customer['name'],
        'email' => (string) $customer['email'],
        'mobile' => (string) $customer['mobile'],
        'remarks' => trim((string) ($customer['remarks'] ?? '')),
        'amount_paisa' => $amountPaisa,
        'merchant_order_id' => $merchantOrderId,
    ];
    if ($paymentLinkId !== null && $paymentLinkId > 0) {
        $_SESSION['phonepe_checkout']['payment_link_id'] = $paymentLinkId;
    }
}

/**
 * @return array<string,mixed>|null
 */
function payment_link_row_to_receipt(array $row, string $merchantOrderId): array
{
    return [
        'source' => 'payment_link',
        'payment_gateway' => payment_normalize_gateway((string) ($row['payment_gateway'] ?? 'phonepe')),
        'name' => (string) $row['customer_name'],
        'email' => (string) $row['customer_email'],
        'mobile' => (string) $row['customer_mobile'],
        'remarks' => trim((string) ($row['remarks'] ?? '')),
        'amount_paisa' => (int) $row['amount_paisa'],
        'merchant_order_id' => $merchantOrderId,
        'paid_at' => $row['paid_at'] ?? null,
    ];
}

/**
 * Resolve receipt fields for payment_return (session, DB, or PhonePe meta).
 *
 * @return array<string,mixed>|null
 */
function payment_resolve_receipt_details(mysqli $conn, string $merchantOrderId, ?array $statusJson = null): ?array
{
    $sessionCtx = $_SESSION['phonepe_checkout'] ?? null;
    if (is_array($sessionCtx) && ($sessionCtx['merchant_order_id'] ?? '') === $merchantOrderId) {
        return $sessionCtx;
    }

    payment_ensure_links_table($conn);
    $stmt = $conn->prepare(
        'SELECT customer_name, customer_email, customer_mobile, remarks, amount_paisa, paid_at, payment_gateway
         FROM payment_links WHERE merchant_order_id = ? LIMIT 1'
    );
    if ($stmt) {
        $stmt->bind_param('s', $merchantOrderId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row) {
            return payment_link_row_to_receipt($row, $merchantOrderId);
        }
    }

    if (!empty($_SESSION['phonepe_pay_link_id'])) {
        $linkId = (int) $_SESSION['phonepe_pay_link_id'];
        $stmt2 = $conn->prepare(
            'SELECT customer_name, customer_email, customer_mobile, remarks, amount_paisa, paid_at, payment_gateway
             FROM payment_links WHERE id = ? LIMIT 1'
        );
        if ($stmt2) {
            $stmt2->bind_param('i', $linkId);
            $stmt2->execute();
            $row2 = $stmt2->get_result()->fetch_assoc();
            $stmt2->close();
            if ($row2) {
                return payment_link_row_to_receipt($row2, $merchantOrderId);
            }
        }
    }

    if (is_array($statusJson)) {
        $meta = $statusJson['metaInfo'] ?? $statusJson['meta'] ?? null;
        if (!is_array($meta) && isset($statusJson['paymentDetails']['metaInfo'])) {
            $meta = $statusJson['paymentDetails']['metaInfo'];
        }
        if (is_array($meta)) {
            $name = trim((string) ($meta['udf1'] ?? ''));
            $email = trim((string) ($meta['udf2'] ?? ''));
            $mobile = trim((string) ($meta['udf3'] ?? ''));
            $remarks = trim((string) ($meta['udf4'] ?? ''));
            if ($name !== '' || $email !== '') {
                $source = (strpos($merchantOrderId, 'MZLINK') === 0 || strpos($merchantOrderId, 'MZPAYU') === 0) ? 'payment_link' : 'pay';
                $amountPaisa = 0;
                if (isset($statusJson['amount'])) {
                    $amountPaisa = (int) $statusJson['amount'];
                } elseif (isset($statusJson['paymentDetails']['amount'])) {
                    $amountPaisa = (int) $statusJson['paymentDetails']['amount'];
                }
                return [
                    'source' => $source,
                    'name' => $name,
                    'email' => $email,
                    'mobile' => $mobile,
                    'remarks' => $remarks,
                    'amount_paisa' => $amountPaisa,
                    'merchant_order_id' => $merchantOrderId,
                ];
            }
        }
    }

    return null;
}

function payment_format_inr_from_paisa(int $paisa): string
{
    if ($paisa < 1) {
        return '—';
    }
    return '₹' . number_format($paisa / 100, 2);
}

function payment_receipt_source_label(string $source): string
{
    return $source === 'payment_link' ? 'Payment link' : 'Pay online';
}
