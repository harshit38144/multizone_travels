<?php
session_start();
if (($_SESSION['role'] ?? '') != '1') {
    header('location:index.php');
    exit;
}

require_once __DIR__ . '/connection.php';
require_once __DIR__ . '/../includes/payment_helpers.php';
require_once __DIR__ . '/includes/lead_contacts_db.php';

payment_ensure_links_table($conn);
lcEnsureContactTables($conn);

$gatewayOptions = payment_gateway_options();
$defaultGateway = payment_normalize_gateway((string) ($_GET['gateway'] ?? 'phonepe'));

$msg = '';
$msgType = 'success';
$generatedUrl = '';
$createdCustomer = null;
$values = [
    'name' => '',
    'email' => '',
    'mobile' => '',
    'remarks' => '',
    'amount' => '',
    'payment_gateway' => $defaultGateway,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values['name'] = trim((string) ($_POST['name'] ?? ''));
    $values['email'] = trim((string) ($_POST['email'] ?? ''));
    $values['mobile'] = trim((string) ($_POST['mobile'] ?? ''));
    $values['remarks'] = trim((string) ($_POST['remarks'] ?? ''));
    $values['amount'] = trim((string) ($_POST['amount'] ?? ''));
    $values['payment_gateway'] = payment_normalize_gateway((string) ($_POST['payment_gateway'] ?? $defaultGateway));

    if ($values['payment_gateway'] === 'payu') {
        require_once __DIR__ . '/../includes/PayUCheckout.php';
        try {
            $payuCheck = new PayUCheckout();
            if (!$payuCheck->isConfigured()) {
                $msg = 'PayU is not configured yet. Add merchant key and salt in includes/payu_config.php.';
                $msgType = 'danger';
            }
        } catch (Throwable $e) {
            $msg = 'PayU configuration file is missing.';
            $msgType = 'danger';
        }
    }

    if ($msg === '') {
        if ($values['name'] === '' || strlen($values['name']) > 120) {
            $msg = 'Please enter a valid customer name.';
            $msgType = 'danger';
        } elseif (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
            $msg = 'Please enter a valid email address.';
            $msgType = 'danger';
        } elseif (strlen($values['remarks']) > 500) {
            $msg = 'Remarks must be 500 characters or less.';
            $msgType = 'danger';
        } else {
            $mobileDigits = preg_replace('/\D/', '', $values['mobile']);
            if (strlen($mobileDigits) < 10 || strlen($mobileDigits) > 15) {
                $msg = 'Please enter a valid mobile number (10–15 digits).';
                $msgType = 'danger';
            } else {
                $amt = (float) str_replace([',', ' '], '', $values['amount']);
                if ($amt < 1 || $amt > 500000) {
                    $msg = 'Amount must be between ₹1 and ₹5,00,000.';
                    $msgType = 'danger';
                } else {
                    $paisa = (int) round($amt * 100);
                    $token = payment_generate_link_token();
                    $createdBy = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
                    $gatewayDb = $values['payment_gateway'];

                    $stmt = $conn->prepare(
                        'INSERT INTO payment_links
                        (link_token, customer_name, customer_email, customer_mobile, remarks, amount_paisa, payment_gateway, created_by)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                    );
                    if ($stmt) {
                        $remarksDb = $values['remarks'];
                        $stmt->bind_param(
                            'sssssisi',
                            $token,
                            $values['name'],
                            $values['email'],
                            $mobileDigits,
                            $remarksDb,
                            $paisa,
                            $gatewayDb,
                            $createdBy
                        );
                        if ($stmt->execute()) {
                            $generatedUrl = payment_link_url($token);
                            $createdCustomer = [
                                'name' => $values['name'],
                                'mobile' => $mobileDigits,
                                'amount_display' => number_format($amt, 2),
                                'gateway' => payment_gateway_label($gatewayDb),
                                'url' => $generatedUrl,
                                'whatsapp_url' => payment_whatsapp_send_url(
                                    $mobileDigits,
                                    payment_whatsapp_payment_link_message(
                                        $values['name'],
                                        number_format($amt, 2),
                                        $generatedUrl
                                    )
                                ),
                            ];
                            $msg = 'Payment link created successfully (' . payment_gateway_label($gatewayDb) . '). Share the link with your customer.';
                            $msgType = 'success';
                            $values = [
                                'name' => '',
                                'email' => '',
                                'mobile' => '',
                                'remarks' => '',
                                'amount' => '',
                                'payment_gateway' => $gatewayDb,
                            ];
                        } else {
                            $msg = 'Could not save payment link. Please try again.';
                            $msgType = 'danger';
                        }
                        $stmt->close();
                    } else {
                        $msg = 'Database error. Please try again.';
                        $msgType = 'danger';
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Create Payment Link</title>
    <?php include __DIR__ . '/includes/header-links.php'; ?>
    <?php include __DIR__ . '/includes/payment_links_assets.php'; ?>
</head>
<body class="hold-transition sidebar-mini layout-fixed page-bg" data-pay-contact-search-url="<?= htmlspecialchars(admin_url('ajax/search_contacts_for_payment.php'), ENT_QUOTES, 'UTF-8') ?>">
<div class="wrapper">
    <?php include __DIR__ . '/includes/top-header.php'; ?>
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0 text-dark"><i class="fas fa-plus-circle mr-2"></i> Create Payment Link</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="payment_links.php">Payment Links</a></li>
                            <li class="breadcrumb-item active">Create</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <section class="content">
            <div class="container-fluid">
                <?php if ($msg !== '') { ?>
                    <div class="alert alert-<?= htmlspecialchars($msgType) ?> alert-dismissible fade show">
                        <?= htmlspecialchars($msg) ?>
                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                    </div>
                <?php } ?>

                <?php if ($generatedUrl !== '') { ?>
                    <div class="card main-card mb-4">
                        <div class="card-header-pay">
                            <h5 class="mb-0"><i class="fas fa-check-circle mr-2"></i> Link ready to share</h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted mb-2">Customer opens this link and is redirected to <strong><?= htmlspecialchars($createdCustomer['gateway'] ?? payment_gateway_label($defaultGateway)) ?></strong> with the amount you set.</p>
                            <p class="alert alert-warning py-2 small mb-3"><strong>Important:</strong> Open payment links in the <strong>same browser tab</strong> (not a new tab).</p>
                            <div class="link-box mb-3" id="generatedLink"><?= htmlspecialchars($generatedUrl) ?></div>
                            <button type="button" class="btn btn-primary copy-btn" data-copy="<?= htmlspecialchars($generatedUrl) ?>">
                                <i class="fas fa-copy mr-1"></i> Copy link
                            </button>
                            <a href="<?= htmlspecialchars($generatedUrl) ?>" class="btn btn-outline-secondary ml-2" target="_blank" rel="noopener">
                                <i class="fas fa-external-link-alt mr-1"></i> Open link
                            </a>
                            <a href="payment_links.php" class="btn btn-outline-primary ml-2">
                                <i class="fas fa-list mr-1"></i> View all links
                            </a>
                            <?php if (is_array($createdCustomer)) { ?>
                            <button type="button" class="btn btn-success ml-2" data-toggle="modal" data-target="#whatsappSendModal">
                                <i class="fab fa-whatsapp mr-1"></i> Send on WhatsApp
                            </button>
                            <?php } ?>
                        </div>
                    </div>
                <?php } ?>

                <div class="row">
                    <div class="col-12">
                        <div class="card main-card">
                            <div class="card-header-pay">
                                <h5 class="mb-0"><i class="fas fa-link mr-2"></i> New payment link</h5>
                            </div>
                            <div class="card-body">
                                <form method="post" action="">
                                    <div class="row">
                                        <div class="col-12 form-group">
                                            <label class="d-block mb-2">Payment method <span class="text-danger">*</span></label>
                                            <div class="gateway-radio-group" role="radiogroup" aria-label="Payment method">
                                                <?php foreach ($gatewayOptions as $gKey => $gLabel) {
                                                    $isChecked = ($values['payment_gateway'] === $gKey);
                                                    $isPhonePe = ($gKey === 'phonepe');
                                                    $hint = $isPhonePe
                                                        ? 'UPI, cards & net banking'
                                                        : 'Cards, UPI & wallets via PayU';
                                                    $iconClass = $isPhonePe ? 'fa-mobile-alt' : 'fa-credit-card';
                                                    $cardClass = $isPhonePe ? 'gateway-radio-phonepe' : 'gateway-radio-payu';
                                                    ?>
                                                    <label class="gateway-radio-card <?= $cardClass ?><?= $isChecked ? ' is-selected' : '' ?>">
                                                        <input type="radio" name="payment_gateway" value="<?= htmlspecialchars($gKey) ?>"
                                                            <?= $isChecked ? 'checked' : '' ?> required>
                                                        <span class="gateway-radio-check"><i class="fas fa-check"></i></span>
                                                        <span class="gateway-radio-icon"><i class="fas <?= $iconClass ?>"></i></span>
                                                        <span class="gateway-radio-title"><?= htmlspecialchars($gLabel) ?></span>
                                                        <span class="gateway-radio-hint"><?= htmlspecialchars($hint) ?></span>
                                                    </label>
                                                <?php } ?>
                                            </div>
                                            <!-- <small class="text-muted d-block mt-2">PayU credentials: <code>includes/payu_config.php</code> · PhonePe: <code>includes/phonepe_config.php</code></small> -->
                                        </div>
                                        <div class="col-md-4 form-group">
                                            <label>Name <span class="text-danger">*</span></label>
                                            <div class="pay-contact-combobox">
                                                <input type="text" name="name" class="form-control js-pay-contact-lookup" required maxlength="120"
                                                    value="<?= htmlspecialchars($values['name']) ?>" placeholder="Customer full name" autocomplete="off">
                                                <div class="pay-contact-menu js-pay-contact-menu" style="display:none;"></div>
                                            </div>
                                        </div>
                                        <div class="col-md-4 form-group">
                                            <label>Email <span class="text-danger">*</span></label>
                                            <div class="pay-contact-combobox">
                                                <input type="email" name="email" class="form-control js-pay-contact-lookup" required maxlength="200"
                                                    value="<?= htmlspecialchars($values['email']) ?>" placeholder="customer@email.com" autocomplete="off">
                                                <div class="pay-contact-menu js-pay-contact-menu" style="display:none;"></div>
                                            </div>
                                        </div>
                                        <div class="col-md-4 form-group">
                                            <label>Mobile <span class="text-danger">*</span></label>
                                            <div class="pay-contact-combobox">
                                                <input type="tel" name="mobile" class="form-control js-pay-contact-lookup" required maxlength="15"
                                                    value="<?= htmlspecialchars($values['mobile']) ?>" placeholder="9876543210" autocomplete="off">
                                                <div class="pay-contact-menu js-pay-contact-menu" style="display:none;"></div>
                                            </div>
                                            <small class="text-muted">Suggestions from <a href="lead_contacts.php">Contacts</a></small>
                                        </div>
                                        <div class="col-md-4 form-group">
                                            <label>Remarks <small class="text-muted">(optional)</small></label>
                                            <textarea name="remarks" class="form-control" rows="3" maxlength="500"
                                                placeholder="Booking ref, package name, etc."><?= htmlspecialchars($values['remarks']) ?></textarea>
                                        </div>
                                        <div class="col-md-4 form-group">
                                            <label>Amount (₹) <span class="text-danger">*</span></label>
                                            <input type="text" name="amount" class="form-control" required inputmode="decimal"
                                                value="<?= htmlspecialchars($values['amount']) ?>" placeholder="e.g. 5000">
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-4">
                                            <button type="submit" class="btn btn-success btn-block">
                                                <i class="fas fa-link mr-1"></i> Create link
                                            </button>
                                        </div>
                                        <div class="col-md-4">
                                            <a href="payment_links.php" class="btn btn-outline-secondary btn-block">
                                                <i class="fas fa-arrow-left mr-1"></i> Back to list
                                            </a>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <?php if (is_array($createdCustomer)) { ?>
    <div class="modal fade" id="whatsappSendModal" tabindex="-1" role="dialog" aria-labelledby="whatsappSendModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="whatsappSendModalLabel">
                        <i class="fab fa-whatsapp mr-2"></i> Send on WhatsApp
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">Payment link created for <strong><?= htmlspecialchars($createdCustomer['name']) ?></strong>.</p>
                    <p class="mb-3">Send the link to mobile <strong>+<?= htmlspecialchars(payment_whatsapp_phone($createdCustomer['mobile'])) ?></strong>?</p>
                    <div class="link-box small mb-0"><?= htmlspecialchars($createdCustomer['url']) ?></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Not now</button>
                    <a href="<?= htmlspecialchars($createdCustomer['whatsapp_url']) ?>"
                        class="btn btn-success" target="_blank" rel="noopener noreferrer" id="btnWhatsappSend">
                        <i class="fab fa-whatsapp mr-1"></i> Send
                    </a>
                </div>
            </div>
        </div>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof jQuery !== 'undefined' && jQuery('#whatsappSendModal').length) {
            jQuery('#whatsappSendModal').modal('show');
        }
    });
    </script>
    <?php } ?>

    <?php include __DIR__ . '/includes/footer-links.php'; ?>
</div>
</body>
</html>
