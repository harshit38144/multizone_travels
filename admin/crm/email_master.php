<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../mail/includes/mail_db.php';
require_once __DIR__ . '/../mail/includes/mail_service.php';

mailEnsureTables($conn);
mailSeedSmtpMasterFromLegacy($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string) ($_POST['action'] ?? ''));

    if ($action === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $label = trim((string) ($_POST['label'] ?? ''));
        $fromName = trim((string) ($_POST['from_name'] ?? ''));
        $fromEmail = trim((string) ($_POST['from_email'] ?? ''));
        $smtpHost = trim((string) ($_POST['smtp_host'] ?? ''));
        $smtpPort = max(1, (int) ($_POST['smtp_port'] ?? 587));
        $smtpEnc = trim((string) ($_POST['smtp_encryption'] ?? 'tls'));
        $smtpUser = trim((string) ($_POST['smtp_username'] ?? ''));
        $smtpPass = (string) ($_POST['smtp_password'] ?? '');
        $isActive = !empty($_POST['is_active']) ? 1 : 0;
        $displayOrder = max(0, (int) ($_POST['display_order'] ?? 0));

        if ($fromEmail === '' || $smtpHost === '') {
            $_SESSION['email_master_flash'] = 'From email and SMTP host are required.';
            $_SESSION['email_master_flash_type'] = 'danger';
        } else {
            $existing = $id > 0 ? mailGetSmtpMasterById($conn, $id) : null;
            $passEnc = $smtpPass !== ''
                ? mailEncrypt($smtpPass)
                : (string) ($existing['smtp_password_enc'] ?? '');

            if ($id > 0 && $existing) {
                $stmt = $conn->prepare(
                    'UPDATE mail_smtp_master SET label=?, from_name=?, from_email=?, smtp_host=?, smtp_port=?,
                     smtp_encryption=?, smtp_username=?, smtp_password_enc=?, is_active=?, display_order=?, updated_at=NOW()
                     WHERE id=?'
                );
                if ($stmt) {
                    $stmt->bind_param(
                        'ssssisssiii',
                        $label,
                        $fromName,
                        $fromEmail,
                        $smtpHost,
                        $smtpPort,
                        $smtpEnc,
                        $smtpUser,
                        $passEnc,
                        $isActive,
                        $displayOrder,
                        $id
                    );
                    $ok = $stmt->execute();
                    $stmt->close();
                } else {
                    $ok = false;
                }
            } else {
                if ($displayOrder <= 0) {
                    $displayOrder = mailNextSmtpMasterDisplayOrder($conn);
                }
                $stmt = $conn->prepare(
                    'INSERT INTO mail_smtp_master
                    (label, from_name, from_email, smtp_host, smtp_port, smtp_encryption, smtp_username, smtp_password_enc, is_active, display_order)
                    VALUES (?,?,?,?,?,?,?,?,?,?)'
                );
                if ($stmt) {
                    $stmt->bind_param(
                        'ssssisssii',
                        $label,
                        $fromName,
                        $fromEmail,
                        $smtpHost,
                        $smtpPort,
                        $smtpEnc,
                        $smtpUser,
                        $passEnc,
                        $isActive,
                        $displayOrder
                    );
                    $ok = $stmt->execute();
                    $stmt->close();
                } else {
                    $ok = false;
                }
            }

            if (!empty($ok)) {
                $_SESSION['email_master_flash'] = $id > 0 ? 'Email account updated.' : 'Email account added.';
                $_SESSION['email_master_flash_type'] = 'success';
            } else {
                $_SESSION['email_master_flash'] = $conn->errno === 1062
                    ? 'This sender email already exists in Email Master.'
                    : 'Could not save email account.';
                $_SESSION['email_master_flash_type'] = 'danger';
            }
        }
    } elseif ($action === 'toggle' && (int) ($_POST['id'] ?? 0) > 0) {
        $id = (int) $_POST['id'];
        $row = mailGetSmtpMasterById($conn, $id);
        if ($row) {
            $newStatus = empty($row['is_active']) ? 1 : 0;
            $stmt = $conn->prepare('UPDATE mail_smtp_master SET is_active = ?, updated_at = NOW() WHERE id = ?');
            if ($stmt) {
                $stmt->bind_param('ii', $newStatus, $id);
                $stmt->execute();
                $stmt->close();
            }
            $_SESSION['email_master_flash'] = $newStatus ? 'Email account activated.' : 'Email account deactivated.';
            $_SESSION['email_master_flash_type'] = 'success';
        }
    } elseif ($action === 'delete' && (int) ($_POST['id'] ?? 0) > 0) {
        $id = (int) $_POST['id'];
        $stmt = $conn->prepare('DELETE FROM mail_smtp_master WHERE id = ?');
        if ($stmt) {
            $stmt->bind_param('i', $id);
            $ok = $stmt->execute();
            $stmt->close();
            $_SESSION['email_master_flash'] = $ok ? 'Email account deleted.' : 'Could not delete email account.';
            $_SESSION['email_master_flash_type'] = $ok ? 'success' : 'danger';
        }
    } elseif ($action === 'test' && (int) ($_POST['id'] ?? 0) > 0) {
        $id = (int) $_POST['id'];
        $testTo = trim((string) ($_POST['test_email_to'] ?? ''));
        $row = mailGetSmtpMasterById($conn, $id);
        if (!$row) {
            $_SESSION['email_master_flash'] = 'Email account not found.';
            $_SESSION['email_master_flash_type'] = 'danger';
        } elseif ($testTo === '') {
            $_SESSION['email_master_flash'] = 'Enter a test email address.';
            $_SESSION['email_master_flash_type'] = 'danger';
        } else {
            $result = mailTestSmtp(mailSmtpConfigFromMaster($row), $testTo);
            $_SESSION['email_master_flash'] = $result['message'];
            $_SESSION['email_master_flash_type'] = !empty($result['ok']) ? 'success' : 'danger';
        }
    }

    header('Location: email_master.php');
    exit;
}

$perPage = (int) ($_GET['per_page'] ?? 10);
if (!in_array($perPage, [10, 25, 50], true)) {
    $perPage = 10;
}
$listPage = max(1, (int) ($_GET['page'] ?? 1));
$search = trim((string) ($_GET['q'] ?? ''));
if (mb_strlen($search) > 120) {
    $search = mb_substr($search, 0, 120);
}

$totalAccounts = mailCountSmtpMaster($conn, $search);
$totalPages = max(1, (int) ceil($totalAccounts / $perPage));
if ($listPage > $totalPages) {
    $listPage = $totalPages;
}
$offset = ($listPage - 1) * $perPage;

$accounts = mailListSmtpMasterPaginated($conn, $offset, $perPage, $search);
$allAccounts = mailListSmtpMaster($conn, false);

function emailMasterPageUrl(int $listPage, int $perPage = 10, string $search = ''): string
{
    $params = [];
    if ($listPage > 1) {
        $params['page'] = $listPage;
    }
    if ($perPage !== 10) {
        $params['per_page'] = $perPage;
    }
    if ($search !== '') {
        $params['q'] = $search;
    }
    $qs = http_build_query($params);

    return 'crm/email_master.php' . ($qs !== '' ? '?' . $qs : '');
}

$editId = (int) ($_GET['edit'] ?? 0);
$editRow = $editId > 0 ? mailGetSmtpMasterById($conn, $editId) : null;
$openAddModal = isset($_GET['add']);
$nextOrder = mailNextSmtpMasterDisplayOrder($conn);

$accountsForJs = [];
foreach ($allAccounts as $acc) {
    $accountsForJs[(int) $acc['id']] = [
        'id' => (int) $acc['id'],
        'label' => (string) ($acc['label'] ?? ''),
        'from_name' => (string) ($acc['from_name'] ?? ''),
        'from_email' => (string) ($acc['from_email'] ?? ''),
        'smtp_host' => (string) ($acc['smtp_host'] ?? ''),
        'smtp_port' => (int) ($acc['smtp_port'] ?? 587),
        'smtp_encryption' => (string) ($acc['smtp_encryption'] ?? 'tls'),
        'smtp_username' => (string) ($acc['smtp_username'] ?? ''),
        'is_active' => (int) ($acc['is_active'] ?? 0),
        'display_order' => (int) ($acc['display_order'] ?? 0),
    ];
}

$flashMsg = (string) ($_SESSION['email_master_flash'] ?? '');
$flashType = (string) ($_SESSION['email_master_flash_type'] ?? 'success');
unset($_SESSION['email_master_flash'], $_SESSION['email_master_flash_type']);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <base href="../">
    <title>Email Master</title>
    <?php include __DIR__ . '/../includes/header-links.php'; ?>
    <style>
        .crm-email-master .content-wrapper > .content { background: #f4f6f9; }
        .crm-email-master .page-title-row {
            display: flex; justify-content: space-between; align-items: flex-start;
            flex-wrap: wrap; gap: 0.75rem; margin-bottom: 1rem;
        }
        .crm-email-master .page-title { margin: 0; font-size: 1.75rem; font-weight: 700; color: #0f172a; }
        .crm-email-master .page-subtitle { margin: 0.35rem 0 0; color: #64748b; font-size: 0.92rem; }
        .crm-email-master .breadcrumbs { font-size: 0.875rem; color: #2563eb; }
        .crm-email-master .breadcrumbs a { color: #2563eb; }
        .crm-email-master .master-card {
            background: #fff; border: 1px solid #e2e8f0; border-radius: 8px;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06); overflow: hidden;
        }
        .crm-email-master .master-card-head {
            display: flex; justify-content: space-between; align-items: center;
            padding: 1rem 1.25rem; border-bottom: 1px solid #e9ecef; flex-wrap: wrap; gap: 0.75rem;
        }
        .crm-email-master .master-card-head h2 { margin: 0; font-size: 1rem; font-weight: 700; color: #334155; }
        .crm-email-master .btn-add-email {
            background: #2563eb; border-color: #2563eb; color: #fff; font-weight: 600;
        }
        .crm-email-master .btn-add-email:hover { background: #1d4ed8; border-color: #1d4ed8; color: #fff; }
        .crm-email-master .toolbar-row {
            padding: 1rem 1.25rem; border-bottom: 1px solid #e9ecef;
            display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: center;
        }
        .crm-email-master .toolbar-row .search-group {
            flex: 1 1 200px; max-width: 360px; display: flex;
        }
        .crm-email-master .toolbar-row .search-group .form-control {
            border-top-right-radius: 0; border-bottom-right-radius: 0;
        }
        .crm-email-master .toolbar-row .search-group .btn-search {
            background: #64748b; color: #fff; border: 1px solid #64748b;
            border-top-left-radius: 0; border-bottom-left-radius: 0;
        }
        .crm-email-master .toolbar-row .search-group .btn-search:hover {
            background: #475569; color: #fff;
        }
        .crm-email-master .table-wrap { overflow-x: auto; }
        .crm-email-master table.crm-email-table {
            width: 100%; margin: 0; border-collapse: collapse;
            font-size: 0.875rem; border: 1px solid #e2e8f0;
        }
        .crm-email-master table.crm-email-table thead th {
            background: #f8fafc; color: #64748b; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.04em;
            padding: 0.75rem 0.85rem; border-bottom: 1px solid #e2e8f0;
            border-right: 1px solid #e2e8f0; white-space: nowrap; vertical-align: middle;
            font-size: 0.72rem;
        }
        .crm-email-master table.crm-email-table thead th:last-child { border-right: none; }
        .crm-email-master table.crm-email-table tbody td {
            padding: 0.7rem 0.85rem; vertical-align: middle;
            border-top: 1px solid #e2e8f0; border-right: 1px solid #e2e8f0;
            color: #1e293b; line-height: 1.35;
        }
        .crm-email-master table.crm-email-table tbody td:last-child { border-right: none; }
        .crm-email-master table.crm-email-table tbody tr:nth-child(even) { background: #fafbfc; }
        .crm-email-master table.crm-email-table tbody tr:nth-child(odd) { background: #fff; }
        .crm-email-master table.crm-email-table tbody tr:hover { background: #f0fdf4; }
        .crm-email-master table.crm-email-table tbody td.col-serial {
            text-align: center; color: #64748b; font-weight: 600;
            font-variant-numeric: tabular-nums; width: 48px;
        }
        .crm-email-master .sender-cell {
            display: flex; align-items: center; gap: 0.65rem; min-width: 220px;
        }
        .crm-email-master .sender-avatar {
            width: 36px; height: 36px; border-radius: 50%;
            background: linear-gradient(135deg, #2563eb 0%, #3b82f6 100%);
            color: #fff; font-weight: 700; font-size: 0.85rem;
            display: inline-flex; align-items: center; justify-content: center;
            flex-shrink: 0; text-transform: uppercase;
        }
        .crm-email-master .sender-name { font-weight: 600; color: #0f172a; }
        .crm-email-master .sender-email { font-size: 0.8rem; color: #64748b; }
        .crm-email-master .sender-label {
            display: inline-block; margin-top: 0.15rem; font-size: 0.72rem;
            color: #64748b; background: #f1f5f9; border-radius: 4px; padding: 0.1rem 0.4rem;
        }
        .crm-email-master .smtp-host { font-weight: 600; color: #334155; }
        .crm-email-master .smtp-meta { font-size: 0.78rem; color: #94a3b8; }
        .crm-email-master .badge-status {
            display: inline-block; padding: 0.2rem 0.55rem; border-radius: 999px;
            font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.03em;
        }
        .crm-email-master .badge-status-active { background: #dcfce7; color: #166534; }
        .crm-email-master .badge-status-inactive { background: #fee2e2; color: #991b1b; }
        .crm-email-master .order-pill {
            display: inline-flex; align-items: center; justify-content: center;
            min-width: 28px; height: 28px; border-radius: 6px;
            background: #f1f5f9; color: #475569; font-weight: 700; font-size: 0.8rem;
        }
        .crm-email-master .action-btns {
            display: inline-flex; gap: 4px; align-items: center; flex-wrap: nowrap;
        }
        .crm-email-master .action-btns .btn-icon {
            width: 30px; height: 30px; padding: 0;
            display: inline-flex; align-items: center; justify-content: center;
            border-radius: 6px; border: 1px solid transparent; font-size: 0.78rem;
            transition: background-color 0.15s ease, border-color 0.15s ease, color 0.15s ease;
        }
        .crm-email-master .action-btns .btn-test {
            background: #eff6ff; border-color: #bfdbfe; color: #2563eb !important;
        }
        .crm-email-master .action-btns .btn-test:hover {
            background: #dbeafe; color: #1d4ed8 !important; border-color: #93c5fd;
        }
        .crm-email-master .action-btns .btn-edit {
            background: #f0fdf4; border-color: #bbf7d0; color: #16a34a !important;
        }
        .crm-email-master .action-btns .btn-edit:hover {
            background: #dcfce7; color: #15803d !important; border-color: #86efac;
        }
        .crm-email-master .action-btns .btn-toggle {
            background: #f8fafc; border-color: #e2e8f0; color: #64748b !important;
        }
        .crm-email-master .action-btns .btn-toggle:hover {
            background: #f1f5f9; color: #334155 !important; border-color: #cbd5e1;
        }
        .crm-email-master .action-btns .btn-del {
            background: #fef2f2; border-color: #fecaca; color: #dc2626 !important;
        }
        .crm-email-master .action-btns .btn-del:hover {
            background: #fee2e2; color: #b91c1c !important; border-color: #fca5a5;
        }
        .crm-email-master .pagination-bar {
            display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between;
            gap: 0.75rem; padding: 0.75rem 1.25rem; border-top: 1px solid #e2e8f0;
            background: #fafbfc; font-size: 0.875rem;
        }
        .crm-email-master .pagination-bar-tools {
            display: flex; flex-wrap: wrap; align-items: center; gap: 0.65rem;
        }
        .crm-email-master .pagination-bar .page-summary { color: #64748b; }
        .crm-email-master .pagination-bar .email-per-page-select {
            width: auto; min-width: 6.5rem; font-size: 0.78rem;
        }
        .crm-email-master .pagination-bar .page-link {
            min-width: 36px; text-align: center; color: #475569;
            background: #f8fafc; border-color: #e2e8f0; border-radius: 6px; font-weight: 600;
        }
        .crm-email-master .pagination-bar .page-link:hover {
            background: #eff6ff; border-color: #bfdbfe; color: #1d4ed8;
        }
        .crm-email-master .pagination-bar .page-item.active .page-link {
            background: #dbeafe; border-color: #93c5fd; color: #1d4ed8;
        }
        .crm-email-master .pagination-bar .page-item.disabled .page-link {
            color: #94a3b8; background: #f8fafc;
        }
        .crm-email-master .empty-state {
            padding: 2.5rem 1rem; text-align: center; color: #64748b;
        }
        #emailMasterFormModal .modal-header {
            border-bottom: 1px solid #e9ecef;
            padding: 0.85rem 1.15rem;
        }
        #emailMasterFormModal .modal-title { font-size: 1.05rem; font-weight: 700; }
        #emailMasterFormModal .modal-body { padding: 1rem 1.15rem 0.25rem; }
        #emailMasterFormModal .modal-footer {
            border-top: 1px solid #e9ecef;
            padding: 0.75rem 1.15rem;
        }
        #emailTestModal .modal-header {
            background: linear-gradient(125deg, #1e40af 0%, #2563eb 55%, #3b82f6 100%);
            color: #fff; border: none;
        }
        #emailTestModal .modal-header .close { color: #fff; text-shadow: none; opacity: 0.9; }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed page-bg crm-email-master">
<div class="wrapper">
    <?php include __DIR__ . '/../includes/top-header.php'; ?>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <div class="content-wrapper">
        <section class="content pt-3">
            <div class="container-fluid">
                <div class="page-title-row">
                    <div>
                        <h1 class="page-title">Email Master</h1>
                        <p class="page-subtitle">Manage multiple SMTP sender accounts. Active accounts appear in the sender dropdown when composing email.</p>
                    </div>
                    <nav class="breadcrumbs">
                        <a href="dashboard.php">Home</a> /
                        <a href="crm/office_settings.php">Settings</a> /
                        Email Master
                    </nav>
                </div>

                <?php if ($flashMsg !== '') { ?>
                    <div class="alert alert-<?= htmlspecialchars($flashType) ?> alert-dismissible fade show">
                        <?= htmlspecialchars($flashMsg) ?>
                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                    </div>
                <?php } ?>

                <div class="master-card">
                    <div class="master-card-head">
                        <h2>SMTP Email Accounts (<?= number_format($totalAccounts) ?>)</h2>
                        <button type="button" class="btn btn-sm btn-add-email" id="emailMasterAddBtn">
                            <i class="fas fa-plus mr-1"></i> Add Email Account
                        </button>
                    </div>

                    <div class="toolbar-row">
                        <form method="get" class="input-group search-group" id="emailMasterSearchForm">
                            <?php if ($perPage !== 10) { ?>
                                <input type="hidden" name="per_page" value="<?= (int) $perPage ?>">
                            <?php } ?>
                            <input type="search" class="form-control" name="q" value="<?= htmlspecialchars($search) ?>"
                                   placeholder="Search sender, email, SMTP host...">
                            <div class="input-group-append">
                                <button type="submit" class="btn btn-search"><i class="fas fa-search"></i></button>
                            </div>
                        </form>
                    </div>

                    <div class="table-wrap">
                        <table class="crm-email-table mb-0" id="emailMasterTable">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Sender</th>
                                    <th>SMTP</th>
                                    <th>Status</th>
                                    <th>Order</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!$accounts) { ?>
                                    <tr>
                                        <td colspan="6" class="empty-state">
                                            <?php if ($search !== '') { ?>
                                                No email accounts match your search.
                                            <?php } else { ?>
                                                No email accounts yet.
                                                <button type="button" class="btn btn-link p-0 align-baseline js-email-master-add">Add your first account</button>.
                                            <?php } ?>
                                        </td>
                                    </tr>
                                <?php } else { ?>
                                    <?php foreach ($accounts as $i => $acc) {
                                        $displayName = (string) ($acc['from_name'] ?: $acc['label'] ?: $acc['from_email']);
                                        $initial = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $displayName) ?: 'E', 0, 1));
                                        $serial = $offset + $i + 1;
                                    ?>
                                        <tr>
                                            <td class="col-serial"><?= (int) $serial ?></td>
                                            <td>
                                                <div class="sender-cell">
                                                    <span class="sender-avatar"><?= htmlspecialchars($initial) ?></span>
                                                    <div>
                                                        <div class="sender-name"><?= htmlspecialchars($displayName) ?></div>
                                                        <div class="sender-email"><?= htmlspecialchars((string) $acc['from_email']) ?></div>
                                                        <?php if (!empty($acc['label'])) { ?>
                                                            <span class="sender-label"><?= htmlspecialchars((string) $acc['label']) ?></span>
                                                        <?php } ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="smtp-host"><?= htmlspecialchars((string) $acc['smtp_host']) ?>:<?= (int) $acc['smtp_port'] ?></div>
                                                <div class="smtp-meta"><?= htmlspecialchars(strtoupper((string) $acc['smtp_encryption'])) ?></div>
                                            </td>
                                            <td>
                                                <?php if (!empty($acc['is_active'])) { ?>
                                                    <span class="badge-status badge-status-active">Active</span>
                                                <?php } else { ?>
                                                    <span class="badge-status badge-status-inactive">Inactive</span>
                                                <?php } ?>
                                            </td>
                                            <td><span class="order-pill"><?= (int) $acc['display_order'] ?></span></td>
                                            <td>
                                                <div class="action-btns">
                                                    <button type="button" class="btn-icon btn-test js-email-master-test" title="Send test email"
                                                            data-id="<?= (int) $acc['id'] ?>"
                                                            data-email="<?= htmlspecialchars((string) $acc['from_email'], ENT_QUOTES, 'UTF-8') ?>">
                                                        <i class="fas fa-paper-plane"></i>
                                                    </button>
                                                    <button type="button" class="btn-icon btn-edit js-email-master-edit" title="Edit"
                                                            data-id="<?= (int) $acc['id'] ?>">
                                                        <i class="fas fa-pencil-alt"></i>
                                                    </button>
                                                    <form method="post" class="d-inline">
                                                        <input type="hidden" name="action" value="toggle">
                                                        <input type="hidden" name="id" value="<?= (int) $acc['id'] ?>">
                                                        <button type="submit" class="btn-icon btn-toggle" title="<?= !empty($acc['is_active']) ? 'Deactivate' : 'Activate' ?>">
                                                            <i class="fas fa-<?= !empty($acc['is_active']) ? 'toggle-on' : 'toggle-off' ?>"></i>
                                                        </button>
                                                    </form>
                                                    <form method="post" class="d-inline" onsubmit="return confirm('Delete this email account?');">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="id" value="<?= (int) $acc['id'] ?>">
                                                        <button type="submit" class="btn-icon btn-del" title="Delete">
                                                            <i class="fas fa-trash-alt"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="pagination-bar">
                        <div class="page-summary">
                            <?php if ($totalAccounts > 0) { ?>
                                Showing <?= number_format($offset + 1) ?>–<?= number_format(min($offset + $perPage, $totalAccounts)) ?> of <?= number_format($totalAccounts) ?> accounts
                            <?php } else { ?>
                                No accounts to display
                            <?php } ?>
                        </div>
                        <div class="pagination-bar-tools">
                            <select class="form-control form-control-sm email-per-page-select" aria-label="Accounts per page">
                                <?php foreach ([10, 25, 50] as $ppOption) { ?>
                                    <option value="<?= (int) $ppOption ?>" <?= $perPage === $ppOption ? 'selected' : '' ?>><?= (int) $ppOption ?> / page</option>
                                <?php } ?>
                            </select>
                            <?php if ($totalAccounts > 0) { ?>
                                <nav aria-label="Email master pagination">
                                    <ul class="pagination pagination-sm mb-0">
                                        <li class="page-item <?= $listPage <= 1 ? 'disabled' : '' ?>">
                                            <a class="page-link" href="<?= htmlspecialchars(emailMasterPageUrl($listPage - 1, $perPage, $search), ENT_QUOTES, 'UTF-8') ?>">Prev</a>
                                        </li>
                                        <?php
                                        $startPage = max(1, $listPage - 2);
                                        $endPage = min($totalPages, $listPage + 2);
                                        for ($p = $startPage; $p <= $endPage; $p++) {
                                        ?>
                                            <li class="page-item <?= $p === $listPage ? 'active' : '' ?>">
                                                <a class="page-link" href="<?= htmlspecialchars(emailMasterPageUrl($p, $perPage, $search), ENT_QUOTES, 'UTF-8') ?>"><?= (int) $p ?></a>
                                            </li>
                                        <?php } ?>
                                        <li class="page-item <?= $listPage >= $totalPages ? 'disabled' : '' ?>">
                                            <a class="page-link" href="<?= htmlspecialchars(emailMasterPageUrl($listPage + 1, $perPage, $search), ENT_QUOTES, 'UTF-8') ?>">Next</a>
                                        </li>
                                    </ul>
                                </nav>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>

<div class="modal fade" id="emailTestModal" tabindex="-1" role="dialog" aria-labelledby="emailTestModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <form method="post" id="emailTestForm">
                <input type="hidden" name="action" value="test">
                <input type="hidden" name="id" id="emailTestId" value="0">
                <div class="modal-header">
                    <h5 class="modal-title mb-0" id="emailTestModalLabel"><i class="fas fa-paper-plane mr-1"></i> Send Test Email</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3">Send a test message using <strong id="emailTestFromLabel">this account</strong> to verify SMTP settings.</p>
                    <div class="form-group mb-0">
                        <label for="emailTestTo">Recipient email <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" name="test_email_to" id="emailTestTo" required placeholder="you@example.com">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane mr-1"></i> Send Test</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="emailMasterFormModal" tabindex="-1" role="dialog" aria-labelledby="emailMasterFormModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <form method="post" id="emailMasterForm">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" id="emId" value="0">
                <div class="modal-header">
                    <h5 class="modal-title" id="emailMasterFormModalLabel">Add Email Account</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Mail Provider (auto-fill SMTP)</label>
                        <select id="emProvider" class="form-control">
                            <option value="">— Select to auto-fill —</option>
                            <option value="zoho">Zoho Mail (multizonetravels.com)</option>
                            <option value="gmail">Gmail</option>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label>Label</label>
                            <input type="text" name="label" id="emLabel" class="form-control" placeholder="e.g. Sales, Support">
                        </div>
                        <div class="col-md-3 form-group">
                            <label>Display Order</label>
                            <input type="number" name="display_order" id="emDisplayOrder" class="form-control" min="0"
                                   value="<?= (int) $nextOrder ?>">
                        </div>
                        <div class="col-md-3 form-group d-flex align-items-end">
                            <div class="custom-control custom-checkbox mb-2">
                                <input type="checkbox" class="custom-control-input" id="emIsActive" name="is_active" value="1" checked>
                                <label class="custom-control-label" for="emIsActive">Active</label>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label>From Name</label>
                            <input type="text" name="from_name" id="emFromName" class="form-control">
                        </div>
                        <div class="col-md-6 form-group">
                            <label>From Email <span class="text-danger">*</span></label>
                            <input type="email" name="from_email" id="emFromEmail" class="form-control" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-5 form-group">
                            <label>SMTP Host <span class="text-danger">*</span></label>
                            <input type="text" name="smtp_host" id="emSmtpHost" class="form-control" required placeholder="smtp.gmail.com">
                        </div>
                        <div class="col-md-2 form-group">
                            <label>Port</label>
                            <input type="number" name="smtp_port" id="emSmtpPort" class="form-control" min="1" value="587">
                        </div>
                        <div class="col-md-2 form-group">
                            <label>Encryption</label>
                            <select name="smtp_encryption" id="emSmtpEncryption" class="form-control">
                                <option value="tls">TLS</option>
                                <option value="ssl">SSL</option>
                                <option value="none">None</option>
                            </select>
                        </div>
                        <div class="col-md-3 form-group">
                            <label>SMTP Username</label>
                            <input type="text" name="smtp_username" id="emSmtpUsername" class="form-control">
                        </div>
                    </div>
                    <div class="form-group mb-0">
                        <label id="emPasswordLabel">SMTP Password</label>
                        <input type="password" name="smtp_password" id="emSmtpPassword" class="form-control" autocomplete="new-password">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer-links.php'; ?>
<script>
window.EMAIL_MASTER_ACCOUNTS = <?= json_encode($accountsForJs, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '{}' ?>;
window.EMAIL_MASTER_NEXT_ORDER = <?= (int) $nextOrder ?>;
window.EMAIL_MASTER_OPEN_ADD = <?= $openAddModal ? 'true' : 'false' ?>;
window.EMAIL_MASTER_EDIT_ID = <?= (int) ($editRow['id'] ?? 0) ?>;
</script>
<script>
window.MAIL_PROVIDER_PRESETS = {
    zoho: { smtp_host: 'smtp.zoho.com', smtp_port: 587, smtp_encryption: 'tls' },
    gmail: { smtp_host: 'smtp.gmail.com', smtp_port: 587, smtp_encryption: 'tls' }
};
</script>
<script>
$(function () {
    var $modal = $('#emailMasterFormModal');
    var $form = $('#emailMasterForm');

    function applyProviderPreset(provider) {
        var preset = (window.MAIL_PROVIDER_PRESETS || {})[provider];
        if (!preset) {
            return;
        }
        $('#emSmtpHost').val(preset.smtp_host || '');
        $('#emSmtpPort').val(preset.smtp_port || 587);
        $('#emSmtpEncryption').val(preset.smtp_encryption || 'tls');
        var email = $('#emFromEmail').val().trim();
        if (email && !$('#emSmtpUsername').val()) {
            $('#emSmtpUsername').val(email);
        }
    }

    $('#emProvider').on('change', function () {
        applyProviderPreset($(this).val());
    });

    $('#emFromEmail').on('blur', function () {
        var email = $(this).val().trim();
        if (email && !$('#emSmtpUsername').val()) {
            $('#emSmtpUsername').val(email);
        }
    });

    function setPasswordHint(isEdit) {
        $('#emPasswordLabel').html(
            isEdit
                ? 'SMTP Password <span class="text-muted small">(leave blank to keep current)</span>'
                : 'SMTP Password'
        );
    }

    function resetEmailMasterForm() {
        $form[0].reset();
        $('#emId').val('0');
        $('#emDisplayOrder').val(window.EMAIL_MASTER_NEXT_ORDER || 1);
        $('#emSmtpPort').val('587');
        $('#emSmtpEncryption').val('tls');
        $('#emIsActive').prop('checked', true);
        $('#emSmtpPassword').val('');
        $('#emProvider').val('');
        $('#emailMasterFormModalLabel').text('Add Email Account');
        setPasswordHint(false);
    }

    function openAddModal() {
        resetEmailMasterForm();
        $modal.modal('show');
    }

    function openEditModal(id) {
        var row = (window.EMAIL_MASTER_ACCOUNTS || {})[String(id)] || (window.EMAIL_MASTER_ACCOUNTS || {})[id];
        if (!row) {
            return;
        }
        resetEmailMasterForm();
        $('#emailMasterFormModalLabel').text('Edit Email Account');
        setPasswordHint(true);
        $('#emId').val(row.id || 0);
        $('#emLabel').val(row.label || '');
        $('#emDisplayOrder').val(row.display_order || 1);
        $('#emIsActive').prop('checked', !!row.is_active);
        $('#emFromName').val(row.from_name || '');
        $('#emFromEmail').val(row.from_email || '');
        $('#emSmtpHost').val(row.smtp_host || '');
        $('#emSmtpPort').val(row.smtp_port || 587);
        $('#emSmtpEncryption').val(row.smtp_encryption || 'tls');
        $('#emSmtpUsername').val(row.smtp_username || '');
        $modal.modal('show');
    }

    $('#emailMasterAddBtn, .js-email-master-add').on('click', function (e) {
        e.preventDefault();
        openAddModal();
    });

    $(document).on('click', '.js-email-master-edit', function () {
        openEditModal($(this).data('id'));
    });

    $(document).on('click', '.js-email-master-test', function () {
        var id = $(this).data('id');
        var email = $(this).data('email') || 'this account';
        $('#emailTestId').val(id);
        $('#emailTestFromLabel').text(email);
        $('#emailTestTo').val('');
        $('#emailTestModal').modal('show');
    });

    $('.email-per-page-select').on('change', function () {
        var perPage = $(this).val();
        var params = new URLSearchParams(window.location.search);
        if (Number(perPage) === 10) {
            params.delete('per_page');
        } else {
            params.set('per_page', perPage);
        }
        params.delete('page');
        var qs = params.toString();
        window.location.href = 'crm/email_master.php' + (qs ? '?' + qs : '');
    });

    if (window.EMAIL_MASTER_OPEN_ADD) {
        openAddModal();
        if (window.history && window.history.replaceState) {
            window.history.replaceState({}, '', 'crm/email_master.php');
        }
    } else if (window.EMAIL_MASTER_EDIT_ID > 0) {
        openEditModal(window.EMAIL_MASTER_EDIT_ID);
        if (window.history && window.history.replaceState) {
            window.history.replaceState({}, '', 'crm/email_master.php');
        }
    }
});
</script>
</body>
</html>
