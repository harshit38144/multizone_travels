<?php
session_start();
if (($_SESSION['role'] ?? '') != '1') {
    header('location:index.php');
    exit;
}

require_once __DIR__ . '/connection.php';
require_once __DIR__ . '/../includes/payment_helpers.php';

payment_ensure_links_table($conn);

$msg = '';
$msgType = 'success';
if (isset($_SESSION['msg'])) {
    $msg = (string) $_SESSION['msg'];
    $msgType = isset($_SESSION['msg_type']) ? (string) $_SESSION['msg_type'] : 'success';
    unset($_SESSION['msg'], $_SESSION['msg_type']);
}

if (isset($_GET['cancel_id'])) {
    $cancelId = (int) $_GET['cancel_id'];
    if ($cancelId > 0) {
        $conn->query("UPDATE payment_links SET status = 'cancelled' WHERE id = $cancelId AND status = 'active'");
        $_SESSION['msg'] = 'Payment link cancelled.';
        $_SESSION['msg_type'] = 'info';
    }
    header('Location: payment_links.php');
    exit;
}

if (isset($_GET['sync_id'])) {
    $syncId = (int) $_GET['sync_id'];
    $sync = payment_sync_payment_link_row($conn, $syncId);
    $_SESSION['msg'] = $sync['message'];
    $_SESSION['msg_type'] = !empty($sync['ok']) ? 'success' : 'warning';
    header('Location: payment_links.php');
    exit;
}

$links = [];
$res = $conn->query(
    'SELECT id, link_token, customer_name, customer_email, customer_mobile, remarks, amount_paisa,
            payment_gateway, status, merchant_order_id, created_at, paid_at
     FROM payment_links ORDER BY id DESC LIMIT 100'
);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $links[] = $row;
    }
}

$activeCount = 0;
$paidCount = 0;
foreach ($links as $link) {
    if ($link['status'] === 'active') {
        $activeCount++;
    } elseif ($link['status'] === 'paid') {
        $paidCount++;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Payment Links</title>
    <?php include __DIR__ . '/includes/header-links.php'; ?>
    <?php include __DIR__ . '/includes/payment_links_assets.php'; ?>
</head>
<body class="hold-transition sidebar-mini layout-fixed page-bg">
<div class="wrapper">
    <?php include __DIR__ . '/includes/top-header.php'; ?>
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0 text-dark"><i class="fas fa-list mr-2"></i> Payment Links</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Payment Links</li>
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

                <div class="card main-card">
                    <div class="card-header-pay d-flex justify-content-between align-items-center flex-wrap">
                        <h5 class="mb-0"><i class="fas fa-link mr-2"></i> All payment links</h5>
                        <a href="payment_link_create.php" class="btn-add-pay">
                            <i class="fas fa-plus mr-1"></i> Create payment link
                        </a>
                    </div>
                    <div class="card-body border-bottom bg-light py-2">
                        <small class="text-muted mr-3"><strong><?= count($links) ?></strong> total (last 100)</small>
                        <small class="text-primary mr-3"><strong><?= $activeCount ?></strong> active</small>
                        <small class="text-success"><strong><?= $paidCount ?></strong> paid</small>
                    </div>
                    <div class="card-body table-responsive p-0">
                        <table class="table table-hover mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th>Customer</th>
                                    <th>Gateway</th>
                                    <th>Mobile</th>
                                    <th>Remarks</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Paid</th>
                                    <th class="text-nowrap">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($links)) { ?>
                                    <tr>
                                        <td colspan="9" class="text-center text-muted py-5">
                                            No payment links yet.
                                            <a href="payment_link_create.php" class="d-block mt-2">Create your first link</a>
                                        </td>
                                    </tr>
                                <?php } else { ?>
                                    <?php foreach ($links as $link) {
                                        $url = payment_link_url((string) $link['link_token']);
                                        $amt = number_format(((int) $link['amount_paisa']) / 100, 2);
                                        $st = (string) $link['status'];
                                        $badge = $st === 'paid' ? 'badge-paid' : ($st === 'active' ? 'badge-active' : 'badge-cancelled');
                                        $remarks = trim((string) ($link['remarks'] ?? ''));
                                        $paidAt = !empty($link['paid_at']) ? date('d M Y, H:i', strtotime($link['paid_at'])) : '—';
                                        $gateway = payment_normalize_gateway((string) ($link['payment_gateway'] ?? 'phonepe'));
                                        $gatewayClass = $gateway === 'payu' ? 'badge-gateway-payu' : 'badge-gateway-phonepe';
                                        ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($link['customer_name']) ?></strong><br>
                                                <small class="text-muted"><?= htmlspecialchars($link['customer_email']) ?></small>
                                            </td>
                                            <td><span class="badge <?= $gatewayClass ?>"><?= htmlspecialchars(payment_gateway_label($gateway)) ?></span></td>
                                            <td><small><?= htmlspecialchars($link['customer_mobile']) ?></small></td>
                                            <td><small class="text-muted"><?= $remarks !== '' ? htmlspecialchars($remarks) : '—' ?></small></td>
                                            <td>₹<?= $amt ?></td>
                                            <td><span class="badge <?= $badge ?>"><?= htmlspecialchars(ucfirst($st)) ?></span></td>
                                            <td><small><?= htmlspecialchars(date('d M Y, H:i', strtotime($link['created_at']))) ?></small></td>
                                            <td><small><?= htmlspecialchars($paidAt) ?></small></td>
                                            <td class="text-nowrap">
                                                <?php if ($st === 'active') { ?>
                                                    <button type="button" class="btn btn-sm btn-outline-primary copy-btn" data-copy="<?= htmlspecialchars($url) ?>" title="Copy link">
                                                        <i class="fas fa-copy"></i>
                                                    </button>
                                                    <a href="<?= htmlspecialchars($url) ?>" class="btn btn-sm btn-outline-secondary" title="Open (same tab)">
                                                        <i class="fas fa-external-link-alt"></i>
                                                    </a>
                                                    <?php if (!empty($link['merchant_order_id'])) { ?>
                                                    <a href="payment_links.php?sync_id=<?= (int) $link['id'] ?>" class="btn btn-sm btn-outline-success" title="Sync paid status from <?= htmlspecialchars(payment_gateway_label($gateway)) ?>"
                                                        onclick="return confirm('Check PhonePe and mark as paid if payment completed?');">
                                                        <i class="fas fa-sync-alt"></i>
                                                    </a>
                                                    <?php } ?>
                                                    <a href="payment_links.php?cancel_id=<?= (int) $link['id'] ?>" class="btn btn-sm btn-outline-danger" title="Cancel"
                                                        onclick="return confirm('Cancel this payment link?');">
                                                        <i class="fas fa-times"></i>
                                                    </a>
                                                <?php } else { ?>
                                                    <span class="text-muted">—</span>
                                                <?php } ?>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <?php include __DIR__ . '/includes/footer-links.php'; ?>
</div>
</body>
</html>
