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

$totalCount = count($links);
$activeCount = 0;
$paidCount = 0;
foreach ($links as $link) {
    if ($link['status'] === 'active') {
        $activeCount++;
    } elseif ($link['status'] === 'paid') {
        $paidCount++;
    }
}

$avatarColors = ['#c41e20', '#0d9488', '#2563eb', '#7c3aed', '#ea580c', '#0891b2'];
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
        <section class="content pay-links-page">
            <div class="container-fluid">
                <div class="pay-links-page-hd">
                    <div class="pay-links-page-hd-left">
                        <div class="pay-links-page-icon"><i class="fas fa-link"></i></div>
                        <div>
                            <h1 class="pay-links-page-title">Payment Links</h1>
                            <p class="pay-links-page-sub mb-0">Create and manage payment links for your customers</p>
                        </div>
                    </div>
                    <ol class="breadcrumb pay-links-breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item active">Payment Links</li>
                    </ol>
                </div>

                <?php if ($msg !== '') { ?>
                    <div class="alert alert-<?= htmlspecialchars($msgType) ?> alert-dismissible fade show">
                        <?= htmlspecialchars($msg) ?>
                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                    </div>
                <?php } ?>

                <div class="pay-links-stats">
                    <div class="pay-stat-card">
                        <div class="pay-stat-icon pay-stat-icon-total"><i class="far fa-file-alt"></i></div>
                        <div>
                            <div class="pay-stat-label">Total</div>
                            <div class="pay-stat-value"><?= (int) $totalCount ?></div>
                            <div class="pay-stat-hint">Payment links</div>
                        </div>
                    </div>
                    <div class="pay-stat-card pay-stat-card-active">
                        <div class="pay-stat-icon pay-stat-icon-active"><i class="fas fa-check"></i></div>
                        <div>
                            <div class="pay-stat-label">Active</div>
                            <div class="pay-stat-value"><?= (int) $activeCount ?></div>
                            <div class="pay-stat-hint">Currently active</div>
                        </div>
                    </div>
                    <div class="pay-stat-card">
                        <div class="pay-stat-icon pay-stat-icon-paid"><i class="far fa-credit-card"></i></div>
                        <div>
                            <div class="pay-stat-label">Paid</div>
                            <div class="pay-stat-value"><?= (int) $paidCount ?></div>
                            <div class="pay-stat-hint">Successfully paid</div>
                        </div>
                    </div>
                </div>

                <div class="pay-links-toolbar">
                    <div class="pay-links-search">
                        <i class="fas fa-search"></i>
                        <input type="search" id="payLinksSearch" class="form-control" placeholder="Search by customer name, email, remarks..." autocomplete="off">
                    </div>
                    <div class="dropdown pay-links-filter">
                        <button type="button" class="btn pay-links-filter-btn dropdown-toggle" id="payLinksStatusFilterBtn"
                            data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <i class="fas fa-filter mr-1"></i> <span id="payLinksStatusFilterLabel">All Status</span>
                        </button>
                        <div class="dropdown-menu dropdown-menu-right" aria-labelledby="payLinksStatusFilterBtn">
                            <a class="dropdown-item js-pay-status-filter active" href="#" data-status="all">All Status</a>
                            <a class="dropdown-item js-pay-status-filter" href="#" data-status="active">Active</a>
                            <a class="dropdown-item js-pay-status-filter" href="#" data-status="paid">Paid</a>
                            <a class="dropdown-item js-pay-status-filter" href="#" data-status="cancelled">Cancelled</a>
                        </div>
                    </div>
                    <a href="payment_link_create.php" class="btn pay-links-create-btn js-open-pay-link-create-modal">
                        <i class="fas fa-plus mr-1"></i> Create Payment Link
                    </a>
                </div>

                <div class="pay-links-table-card">
                    <div class="table-responsive">
                        <table class="table pay-links-table mb-0" id="payLinksTable">
                            <thead>
                                <tr>
                                    <th>Customer</th>
                                    <th>Gateway</th>
                                    <th>Mobile</th>
                                    <th>Remarks</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Paid</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($links)) { ?>
                                    <tr class="pay-links-empty-row">
                                        <td colspan="9" class="text-center text-muted py-5">
                                            No payment links yet.
                                            <a href="payment_link_create.php" class="d-block mt-2 js-open-pay-link-create-modal">Create your first link</a>
                                        </td>
                                    </tr>
                                <?php } else { ?>
                                    <?php foreach ($links as $index => $link) {
                                        $url = payment_link_url((string) $link['link_token']);
                                        $amt = number_format(((int) $link['amount_paisa']) / 100, 2);
                                        $st = (string) $link['status'];
                                        $remarks = trim((string) ($link['remarks'] ?? ''));
                                        $paidAt = !empty($link['paid_at']) ? date('d M Y, H:i', strtotime($link['paid_at'])) : '—';
                                        $gateway = payment_normalize_gateway((string) ($link['payment_gateway'] ?? 'phonepe'));
                                        $name = trim((string) ($link['customer_name'] ?? ''));
                                        $initial = $name !== '' ? strtoupper(substr($name, 0, 1)) : '?';
                                        $avatarColor = $avatarColors[$index % count($avatarColors)];
                                        $searchBlob = strtolower($name . ' ' . ($link['customer_email'] ?? '') . ' ' . ($link['customer_mobile'] ?? '') . ' ' . $remarks);
                                        $statusClass = $st === 'active' ? 'is-active' : ($st === 'paid' ? 'is-paid' : 'is-cancelled');
                                        ?>
                                        <tr class="pay-link-row" data-status="<?= htmlspecialchars($st) ?>" data-search="<?= htmlspecialchars($searchBlob) ?>">
                                            <td>
                                                <div class="pay-customer-cell">
                                                    <span class="pay-avatar" style="background:<?= htmlspecialchars($avatarColor) ?>"><?= htmlspecialchars($initial) ?></span>
                                                    <span class="pay-customer-meta">
                                                        <strong><?= htmlspecialchars($name !== '' ? $name : '—') ?></strong>
                                                        <small><?= htmlspecialchars((string) ($link['customer_email'] ?? '')) ?></small>
                                                    </span>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="pay-gateway-badge pay-gateway-<?= htmlspecialchars($gateway) ?>">
                                                    <?= htmlspecialchars(payment_gateway_label($gateway)) ?>
                                                </span>
                                            </td>
                                            <td><?= htmlspecialchars((string) ($link['customer_mobile'] ?? '')) ?></td>
                                            <td class="pay-remarks-cell"><?= $remarks !== '' ? htmlspecialchars($remarks) : '—' ?></td>
                                            <td class="pay-amount-cell">₹<?= $amt ?></td>
                                            <td>
                                                <span class="pay-status-pill <?= $statusClass ?>">
                                                    <span class="pay-status-dot"></span><?= htmlspecialchars(ucfirst($st)) ?>
                                                </span>
                                            </td>
                                            <td class="pay-date-cell"><?= htmlspecialchars(date('d M Y, H:i', strtotime($link['created_at']))) ?></td>
                                            <td class="pay-date-cell"><?= htmlspecialchars($paidAt) ?></td>
                                            <td class="text-center text-nowrap">
                                                <div class="pay-actions">
                                                    <a href="<?= htmlspecialchars($url) ?>" class="pay-action-btn" title="View / open link" target="_blank" rel="noopener">
                                                        <i class="far fa-eye"></i>
                                                    </a>
                                                    <button type="button" class="pay-action-btn copy-btn" data-copy="<?= htmlspecialchars($url) ?>" title="Copy link">
                                                        <i class="far fa-copy"></i>
                                                    </button>
                                                    <?php if ($st === 'active') { ?>
                                                        <?php if (!empty($link['merchant_order_id'])) { ?>
                                                            <a href="payment_links.php?sync_id=<?= (int) $link['id'] ?>" class="pay-action-btn" title="Sync paid status"
                                                                onclick="return confirm('Check gateway and mark as paid if payment completed?');">
                                                                <i class="fas fa-sync-alt"></i>
                                                            </a>
                                                        <?php } ?>
                                                        <a href="payment_links.php?cancel_id=<?= (int) $link['id'] ?>" class="pay-action-btn is-danger" title="Cancel link"
                                                            onclick="return confirm('Cancel this payment link?');">
                                                            <i class="far fa-trash-alt"></i>
                                                        </a>
                                                    <?php } ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                <?php } ?>
                                <tr class="pay-links-no-match" style="display:none;">
                                    <td colspan="9" class="text-center text-muted py-4">No payment links match your search.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="pay-links-table-foot">
                        <div class="pay-links-showing" id="payLinksShowing">
                            Showing <?= $totalCount > 0 ? '1' : '0' ?> to <?= (int) $totalCount ?> of <?= (int) $totalCount ?> payment links
                        </div>
                        <div class="pay-links-pager">
                            <button type="button" class="pay-page-btn" disabled aria-label="Previous">&lt;</button>
                            <button type="button" class="pay-page-btn is-active" aria-current="page">1</button>
                            <button type="button" class="pay-page-btn" disabled aria-label="Next">&gt;</button>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <?php include __DIR__ . '/includes/footer-links.php'; ?>

    <div class="modal fade" id="payLinkCreateModal" tabindex="-1" role="dialog"
        aria-labelledby="payLinkCreateModalLabel" aria-hidden="true" data-backdrop="static"
        data-pay-contact-search-url="<?= htmlspecialchars(function_exists('admin_url') ? admin_url('ajax/search_contacts_for_payment.php') : 'ajax/search_contacts_for_payment.php', ENT_QUOTES, 'UTF-8') ?>">
        <div class="modal-dialog modal-dialog-scrollable modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <div class="pay-link-modal-hd">
                        <h5 class="modal-title mb-0" id="payLinkCreateModalLabel">
                            <i class="fas fa-link mr-2"></i>New Payment Link
                        </h5>
                        <div class="pay-link-modal-sub">Create and share a payment link with your customer</div>
                    </div>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body" id="payLinkCreateModalBody">
                    <div class="pay-link-create-loading">
                        <i class="fas fa-spinner fa-spin"></i> Loading form…
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    (function ($) {
        var createFormUrl = 'payment_link_create.php?embed=1';
        var $modal = $('#payLinkCreateModal');
        var $body = $('#payLinkCreateModalBody');
        var createdInModal = false;
        var currentStatus = 'all';

        function escHtml(str) {
            return $('<div>').text(str == null ? '' : String(str)).html();
        }

        function showCreateLoading() {
            $body.html(
                '<div class="pay-link-create-loading">' +
                '<i class="fas fa-spinner fa-spin"></i> Loading form…' +
                '</div>'
            );
        }

        function renderCreateSuccess(res) {
            var customer = res.customer || {};
            var url = res.url || customer.url || '';
            var html = '';
            html += '<div class="alert alert-success">' + escHtml(res.message || 'Payment link created.') + '</div>';
            html += '<div class="link-box mb-3">' + escHtml(url) + '</div>';
            html += '<div class="d-flex flex-wrap" style="gap:0.5rem;">';
            html += '<button type="button" class="btn btn-primary copy-btn" data-copy="' + escHtml(url) + '">' +
                '<i class="fas fa-copy mr-1"></i> Copy link</button>';
            if (url) {
                html += '<a href="' + escHtml(url) + '" class="btn btn-outline-secondary" target="_blank" rel="noopener">' +
                    '<i class="fas fa-external-link-alt mr-1"></i> Open link</a>';
            }
            if (customer.whatsapp_url) {
                html += '<a href="' + escHtml(customer.whatsapp_url) + '" class="btn btn-success" target="_blank" rel="noopener noreferrer">' +
                    '<i class="fab fa-whatsapp mr-1"></i> Send on WhatsApp</a>';
            }
            html += '<button type="button" class="btn btn-outline-primary js-pay-link-create-another">' +
                '<i class="fas fa-plus mr-1"></i> Create another</button>';
            html += '<button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Close</button>';
            html += '</div>';
            $body.html(html);
        }

        function openCreateModal(e) {
            if (e) {
                e.preventDefault();
            }
            createdInModal = false;
            showCreateLoading();
            $modal.modal('show');
            $body.load(createFormUrl, function (response, status) {
                if (status !== 'success') {
                    $body.html('<div class="alert alert-danger mb-0">Could not load create form. Please try again.</div>');
                }
            });
        }

        function applyPayLinksFilters() {
            var q = String($('#payLinksSearch').val() || '').trim().toLowerCase();
            var visible = 0;
            var total = 0;
            $('#payLinksTable tbody tr.pay-link-row').each(function () {
                total += 1;
                var $row = $(this);
                var status = String($row.attr('data-status') || '');
                var blob = String($row.attr('data-search') || '');
                var statusOk = currentStatus === 'all' || status === currentStatus;
                var searchOk = !q || blob.indexOf(q) >= 0;
                var show = statusOk && searchOk;
                $row.toggle(show);
                if (show) {
                    visible += 1;
                }
            });
            $('.pay-links-no-match').toggle(total > 0 && visible === 0);
            var from = visible > 0 ? 1 : 0;
            $('#payLinksShowing').text('Showing ' + from + ' to ' + visible + ' of ' + visible + ' payment links');
        }

        $(document).on('click', '.js-open-pay-link-create-modal', openCreateModal);
        $(document).on('click', '.js-pay-link-create-another', function (e) {
            e.preventDefault();
            openCreateModal();
        });

        $(document).on('input', '#payLinksSearch', applyPayLinksFilters);

        $(document).on('click', '.js-pay-status-filter', function (e) {
            e.preventDefault();
            var status = String($(this).attr('data-status') || 'all');
            currentStatus = status;
            $('.js-pay-status-filter').removeClass('active');
            $(this).addClass('active');
            $('#payLinksStatusFilterLabel').text($(this).text());
            applyPayLinksFilters();
        });

        $(document).on('submit', '#payLinkCreateModal .js-pay-link-create-form', function (e) {
            e.preventDefault();
            var $form = $(this);
            var $submit = $form.find('.js-pay-link-create-submit');
            var $alert = $body.find('.js-pay-link-create-alert');
            var defaultHtml = $submit.html();

            $alert.empty();
            $submit.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Creating...');

            $.ajax({
                url: $form.attr('action') || 'payment_link_create.php',
                method: 'POST',
                data: $form.serialize() + '&ajax=1',
                dataType: 'json',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            }).done(function (res) {
                if (res && res.success) {
                    createdInModal = true;
                    renderCreateSuccess(res);
                    return;
                }
                $alert.html(
                    '<div class="alert alert-danger">' +
                    escHtml((res && res.message) || 'Could not create payment link.') +
                    '</div>'
                );
            }).fail(function () {
                $alert.html('<div class="alert alert-danger">Could not create payment link. Please try again.</div>');
            }).always(function () {
                $submit.prop('disabled', false).html(defaultHtml);
            });
        });

        $modal.on('hidden.bs.modal', function () {
            showCreateLoading();
            if (createdInModal) {
                createdInModal = false;
                window.location.reload();
            }
        });
    })(jQuery);
    </script>
</div>
</body>
</html>
