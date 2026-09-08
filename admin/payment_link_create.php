<?php
session_start();
if (($_SESSION['role'] ?? '') != '1') {
    if (
        (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || (isset($_POST['ajax']) && (string) $_POST['ajax'] === '1')
        || (isset($_GET['embed']) && (string) $_GET['embed'] === '1' && $_SERVER['REQUEST_METHOD'] === 'POST')
    ) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Unauthorized. Please log in again.']);
        exit;
    }
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
$isEmbed = isset($_GET['embed']) && (string) $_GET['embed'] === '1';
$isAjax = (
    (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || (isset($_POST['ajax']) && (string) $_POST['ajax'] === '1')
);

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

function payment_link_create_json(array $payload): void
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

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

    if ($isAjax) {
        if ($msgType === 'success' && $generatedUrl !== '' && is_array($createdCustomer)) {
            payment_link_create_json([
                'success' => true,
                'message' => $msg,
                'url' => $generatedUrl,
                'customer' => $createdCustomer,
            ]);
        }
        payment_link_create_json([
            'success' => false,
            'message' => $msg !== '' ? $msg : 'Could not create payment link.',
        ]);
    }
}

if ($isEmbed) {
    header('Content-Type: text/html; charset=UTF-8');
    $formAction = 'payment_link_create.php';
    $formInModal = true;
    ?>
    <div class="pay-link-create-embed"
        data-pay-contact-search-url="<?= htmlspecialchars(function_exists('admin_url') ? admin_url('ajax/search_contacts_for_payment.php') : 'ajax/search_contacts_for_payment.php', ENT_QUOTES, 'UTF-8') ?>">
        <div class="js-pay-link-create-alert"></div>
        <div class="js-pay-link-create-success" style="display:none;"></div>
        <div class="js-pay-link-create-form-wrap">
            <?php include __DIR__ . '/includes/payment_link_create_form.php'; ?>
        </div>
    </div>
    <?php
    exit;
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
<body class="hold-transition sidebar-mini layout-fixed page-bg" data-pay-contact-search-url="<?= htmlspecialchars(function_exists('admin_url') ? admin_url('ajax/search_contacts_for_payment.php') : 'ajax/search_contacts_for_payment.php', ENT_QUOTES, 'UTF-8') ?>">
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
                        <div class="card main-card overflow-hidden">
                            <div class="modal-header" style="background:#c41e20;color:#fff;border:0;border-radius:12px 12px 0 0;padding:0.95rem 1.25rem;">
                                <div class="pay-link-modal-hd d-flex align-items-center justify-content-between w-100">
                                    <h5 class="modal-title mb-0" style="font-size:1.08rem;font-weight:700;">
                                        <i class="fas fa-link mr-2"></i>New Payment Link
                                    </h5>
                                    <div class="pay-link-modal-sub d-none d-md-block" style="font-size:0.82rem;opacity:0.92;">
                                        Create and share a payment link with your customer
                                    </div>
                                </div>
                            </div>
                            <div class="card-body" style="background:#f7f8fa;">
                                <?php
                                $formAction = '';
                                $formInModal = false;
                                include __DIR__ . '/includes/payment_link_create_form.php';
                                ?>
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
