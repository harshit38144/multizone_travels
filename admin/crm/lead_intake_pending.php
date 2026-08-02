<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/includes/lead_intake_db.php';
require_once __DIR__ . '/includes/lead_intake_fields.php';

crmEnsureLeadIntakeTables($conn);

$pendingRows = [];
$sql = "SELECT s.id AS submission_id, s.status AS submission_status, s.created_at AS submitted_at,
        s.payload_json, s.review_note,
        r.id AS request_id, r.token, r.recipient_name, r.recipient_phone, r.lead_source, r.assign_to,
        r.admin_name, r.note_to_customer
    FROM `crm_lead_intake_submissions` s
    INNER JOIN `crm_lead_intake_requests` r ON r.id = s.intake_request_id
    WHERE s.status = 'pending'
    ORDER BY s.id DESC";
$res = $conn->query($sql);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $payload = [];
        if (!empty($row['payload_json'])) {
            $decoded = json_decode((string) $row['payload_json'], true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }
        $services = [];
        if (!empty($payload['services']) && is_array($payload['services'])) {
            $services = $payload['services'];
        }
        $pendingRows[] = [
            'submission_id' => (int) ($row['submission_id'] ?? 0),
            'submitted_at' => (string) ($row['submitted_at'] ?? ''),
            'customer_name' => trim((string) ($payload['customer_name'] ?? ($row['recipient_name'] ?? ''))),
            'customer_phone' => trim((string) ($payload['customer_phone'] ?? ($row['recipient_phone'] ?? ''))),
            'customer_email' => trim((string) ($payload['customer_email'] ?? '')),
            'lead_source' => (string) ($row['lead_source'] ?? ''),
            'assign_to' => (string) ($row['assign_to'] ?? ''),
            'admin_name' => (string) ($row['admin_name'] ?? ''),
            'services' => $services,
            'payload' => $payload,
        ];
    }
}

$pendingCount = count($pendingRows);

function crmIntakePendingInitials(string $name): string
{
    $name = trim($name);
    if ($name === '') {
        return '?';
    }
    $parts = preg_split('/\s+/u', $name) ?: [];
    $initials = '';
    foreach (array_slice($parts, 0, 2) as $part) {
        $part = trim((string) $part);
        if ($part === '') {
            continue;
        }
        $initials .= mb_strtoupper(mb_substr($part, 0, 1));
    }

    return $initials !== '' ? $initials : '?';
}

function crmIntakePendingPhoneDisplay(string $phone): string
{
    $phone = trim($phone);
    if ($phone === '') {
        return '—';
    }
    $digits = preg_replace('/\D+/', '', $phone) ?? '';
    if (strlen($digits) === 12 && strpos($digits, '91') === 0) {
        return '+91 ' . substr($digits, 2, 5) . ' ' . substr($digits, 7);
    }
    if (strlen($digits) === 10) {
        return '+91 ' . substr($digits, 0, 5) . ' ' . substr($digits, 5);
    }

    return $phone;
}

$assignToUsers = [];
$assignToSelfName = trim((string) ($_SESSION['name'] ?? ''));
$usersTbl = $conn->query("SHOW TABLES LIKE 'users'");
if ($usersTbl && $usersTbl->num_rows > 0) {
    $uRes = $conn->query("SELECT `username`, `full_name` FROM `users` WHERE `is_deleted` = 0 ORDER BY `full_name` ASC, `username` ASC");
    if ($uRes) {
        while ($uRow = $uRes->fetch_assoc()) {
            $username = trim((string) ($uRow['username'] ?? ''));
            $fullName = trim((string) ($uRow['full_name'] ?? ''));
            if ($username === '' && $fullName === '') {
                continue;
            }
            $assignToUsers[] = [
                'username' => $username,
                'full_name' => $fullName,
            ];
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <base href="../">
    <title>Pending Lead Verification</title>
    <?php include __DIR__ . '/../includes/header-links.php'; ?>
    <link rel="stylesheet" href="plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="plugins/sweetalert2/sweetalert2.min.css">
    <style>
        .crm-intake-pending-ui {
            --ip-accent: #e53935;
            --ip-accent-soft: #ffebee;
            --ip-text: #1e293b;
            --ip-muted: #94a3b8;
            --ip-border: #e8edf3;
            --ip-bg: #f5f7fa;
        }

        .crm-intake-pending-ui .content-wrapper > .content {
            background: var(--ip-bg);
            padding: 1rem 0.75rem 1.25rem;
        }

        .crm-intake-pending-ui .content-wrapper > .content-header { display: none; }

        .crm-intake-pending-ui .ip-shell {
            background: #fff;
            border: 1px solid var(--ip-border);
            border-radius: 14px;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
            padding: 1.15rem 1.25rem 1.25rem;
        }

        .crm-intake-pending-ui .ip-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
            margin-bottom: 1.15rem;
        }

        .crm-intake-pending-ui .ip-head-left {
            display: flex;
            align-items: flex-start;
            gap: 0.85rem;
            min-width: 0;
        }

        .crm-intake-pending-ui .ip-head-icon {
            width: 46px;
            height: 46px;
            border-radius: 12px;
            background: var(--ip-accent-soft);
            color: var(--ip-accent);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.15rem;
            flex: 0 0 46px;
        }

        .crm-intake-pending-ui .ip-title {
            margin: 0 0 0.2rem;
            font-size: 1.2rem;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.3;
        }

        .crm-intake-pending-ui .ip-subtitle {
            margin: 0;
            color: #64748b;
            font-size: 0.9rem;
            line-height: 1.45;
        }

        .crm-intake-pending-ui .ip-back-btn {
            display: inline-flex;
            align-items: center;
            height: 38px;
            padding: 0 0.95rem;
            border-radius: 8px;
            border: 1px solid #d1d5db;
            background: #fff;
            color: #374151;
            font-weight: 600;
            font-size: 0.84rem;
            text-decoration: none !important;
            white-space: nowrap;
        }

        .crm-intake-pending-ui .ip-back-btn:hover {
            background: #f9fafb;
            color: #111827;
            border-color: #9ca3af;
        }

        .crm-intake-pending-ui .ip-empty {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            color: #1d4ed8;
            border-radius: 10px;
            padding: 0.9rem 1rem;
            margin: 0;
        }

        .crm-intake-pending-ui .dataTables_wrapper .row:first-child {
            align-items: center;
            margin-bottom: 0.85rem;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
        }

        .crm-intake-pending-ui .dataTables_wrapper .row:first-child > [class*="col-"]:first-child {
            flex: 0 0 auto;
            max-width: none;
            width: auto;
        }

        .crm-intake-pending-ui .dataTables_wrapper .row:first-child > [class*="col-"]:last-child {
            flex: 1 1 auto;
            max-width: none;
            width: auto;
            display: flex;
            justify-content: flex-end;
            margin-left: auto;
        }

        .crm-intake-pending-ui .dataTables_length {
            text-align: left;
        }

        .crm-intake-pending-ui .dataTables_length label {
            margin: 0;
            font-weight: 500;
            color: #64748b;
            font-size: 0.88rem;
        }

        .crm-intake-pending-ui .dataTables_length select {
            border: 1px solid #d1d5db;
            border-radius: 8px;
            height: 34px;
            padding: 0 0.45rem;
            margin: 0 0.35rem;
            color: #374151;
            background: #fff;
        }

        .crm-intake-pending-ui .dataTables_filter {
            text-align: right !important;
            float: none !important;
            width: 100%;
            display: flex;
            justify-content: flex-end;
        }

        .crm-intake-pending-ui .dataTables_filter label {
            margin: 0;
            font-weight: 500;
            width: auto;
            position: relative;
            display: block;
            margin-left: auto;
        }

        .crm-intake-pending-ui .dataTables_filter input {
            width: 280px !important;
            max-width: 100%;
            height: 38px;
            margin-left: 0 !important;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 0 0.9rem 0 2.35rem;
            background: #fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Ccircle cx='11' cy='11' r='8'/%3E%3Cpath d='m21 21-4.3-4.3'/%3E%3C/svg%3E") no-repeat 0.85rem 50%;
            color: #374151;
            font-size: 0.88rem;
        }

        .crm-intake-pending-ui .dataTables_filter input:focus {
            border-color: #fca5a5;
            box-shadow: 0 0 0 3px rgba(229, 57, 53, 0.12);
            outline: none;
        }

        .crm-intake-pending-ui table.ip-table {
            width: 100% !important;
            border-collapse: separate;
            border-spacing: 0 0.65rem;
            background: transparent;
        }

        .crm-intake-pending-ui table.ip-table thead th {
            background: transparent;
            border: none !important;
            color: #0f172a;
            font-weight: 700;
            font-size: 0.86rem;
            padding: 0.35rem 0.9rem 0.55rem;
            vertical-align: middle;
            white-space: nowrap;
        }

        .crm-intake-pending-ui table.ip-table tbody tr {
            background: transparent;
        }

        .crm-intake-pending-ui table.ip-table tbody td {
            background: #fff;
            border-top: 1px solid var(--ip-border) !important;
            border-bottom: 1px solid var(--ip-border) !important;
            border-left: none !important;
            border-right: none !important;
            padding: 0.95rem 0.9rem;
            vertical-align: middle;
            color: var(--ip-text);
        }

        .crm-intake-pending-ui table.ip-table tbody td:first-child {
            border-left: 1px solid var(--ip-border) !important;
            border-radius: 12px 0 0 12px;
        }

        .crm-intake-pending-ui table.ip-table tbody td:last-child {
            border-right: 1px solid var(--ip-border) !important;
            border-radius: 0 12px 12px 0;
        }

        .crm-intake-pending-ui table.ip-table tbody tr:hover td {
            background: #fcfcfd;
        }

        .crm-intake-pending-ui .ip-name-cell {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            min-width: 220px;
        }

        .crm-intake-pending-ui .ip-avatar {
            width: 42px;
            height: 42px;
            border-radius: 999px;
            background: var(--ip-accent-soft);
            color: var(--ip-accent);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.82rem;
            flex: 0 0 42px;
            letter-spacing: 0.02em;
        }

        .crm-intake-pending-ui .ip-name {
            margin: 0;
            font-weight: 700;
            color: #0f172a;
            text-transform: uppercase;
            font-size: 0.92rem;
            line-height: 1.25;
        }

        .crm-intake-pending-ui .ip-email {
            margin: 0.15rem 0 0;
            color: #94a3b8;
            font-size: 0.8rem;
            line-height: 1.3;
            word-break: break-word;
        }

        .crm-intake-pending-ui .ip-meta {
            display: inline-flex;
            align-items: center;
            gap: 0.65rem;
        }

        .crm-intake-pending-ui .ip-meta-icon {
            width: 34px;
            height: 34px;
            border-radius: 9px;
            background: var(--ip-accent-soft);
            color: var(--ip-accent);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 34px;
            font-size: 0.82rem;
        }

        .crm-intake-pending-ui .ip-date {
            margin: 0;
            font-weight: 700;
            color: #0f172a;
            font-size: 0.9rem;
            line-height: 1.25;
        }

        .crm-intake-pending-ui .ip-time {
            margin: 0.12rem 0 0;
            color: #94a3b8;
            font-size: 0.78rem;
            line-height: 1.25;
        }

        .crm-intake-pending-ui .ip-phone {
            margin: 0;
            font-weight: 600;
            color: #111827;
            font-size: 0.9rem;
        }

        .crm-intake-pending-ui .badge-pending {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            background: #fff3e0;
            color: #ef6c00;
            border: 1px solid #ffcc80;
            padding: 0.4rem 0.8rem;
            border-radius: 999px;
            font-weight: 700;
            font-size: 0.78rem;
            line-height: 1;
        }

        .crm-intake-pending-ui .ip-actions {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
        }

        .crm-intake-pending-ui .action-btn {
            width: 36px;
            height: 36px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid transparent;
            transition: transform 0.15s ease, box-shadow 0.15s ease, background-color 0.15s ease;
            text-decoration: none !important;
            font-size: 0.85rem;
        }

        .crm-intake-pending-ui .action-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.12);
        }

        .crm-intake-pending-ui .action-btn-approve {
            background: #e8f5e9;
            color: #2e7d32 !important;
            border-color: #c8e6c9;
        }

        .crm-intake-pending-ui .action-btn-edit {
            background: #fff;
            color: #2e7d32 !important;
            border-color: #c8e6c9;
        }

        .crm-intake-pending-ui .action-btn-delete {
            background: #fff;
            color: #e53935 !important;
            border-color: #ef9a9a;
        }

        .crm-intake-pending-ui .action-btn-more {
            background: #fff;
            color: #64748b !important;
            border-color: #e2e8f0;
        }

        .crm-intake-pending-ui .action-btn-more.dropdown-toggle::after {
            display: none;
        }

        .crm-intake-pending-ui .dataTables_info {
            color: #64748b;
            font-size: 0.84rem;
            padding-top: 0.85rem !important;
        }

        .crm-intake-pending-ui .dataTables_paginate {
            padding-top: 0.55rem !important;
        }

        .crm-intake-pending-ui .page-item .page-link {
            border-radius: 8px !important;
            margin: 0 0.15rem;
            border: 1px solid #e5e7eb;
            color: #475569;
            min-width: 34px;
            height: 34px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.84rem;
            background: #fff;
        }

        .crm-intake-pending-ui .page-item.active .page-link {
            background: var(--ip-accent);
            border-color: var(--ip-accent);
            color: #fff;
            box-shadow: none;
        }

        .crm-intake-pending-ui .page-item.disabled .page-link {
            color: #94a3b8;
            background: #f8fafc;
        }

        .crm-intake-pending-ui .page-item .page-link:hover {
            background: #f8fafc;
            color: #0f172a;
        }

        .crm-intake-pending-ui .page-item.active .page-link:hover {
            background: #c62828;
            color: #fff;
        }

        /* Approve modal — searchable assign combobox (like Destination on Create Lead) */
        .intake-assign-combobox {
            position: relative;
        }
        .intake-assign-field {
            display: flex;
            align-items: center;
            min-height: calc(2.25rem + 2px);
            padding: 0.25rem 0.5rem;
            border: 1px solid #ced4da;
            border-radius: 0.25rem;
            background: #fff;
            cursor: text;
        }
        .intake-assign-field:focus-within {
            border-color: #80bdff;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.15);
        }
        .intake-assign-search {
            flex: 1 1 auto;
            width: 100%;
            border: 0;
            outline: none;
            background: transparent;
            padding: 0.2rem 0.15rem;
            font-size: 1rem;
            color: #495057;
        }
        .intake-assign-menu {
            position: absolute;
            top: calc(100% + 2px);
            left: 0;
            right: 0;
            z-index: 1060;
            max-height: 220px;
            overflow-y: auto;
            background: #fff;
            border: 1px solid #ced4da;
            border-radius: 0.25rem;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.12);
        }
        .intake-assign-item {
            display: block;
            width: 100%;
            padding: 0.55rem 0.75rem;
            border: 0;
            background: transparent;
            color: #212529;
            text-align: left;
            cursor: pointer;
        }
        .intake-assign-item:hover,
        .intake-assign-item:focus,
        .intake-assign-item.is-active {
            background: #f1f3f5;
            outline: none;
        }
        .intake-assign-empty {
            padding: 0.65rem 0.75rem;
            color: #6c757d;
            font-size: 0.9rem;
        }

        /* Edit submission modal (loads crm/lead_add.php?embed=1) */
        #leadFormModal .lead-form-dialog {
            max-width: 1100px;
            width: calc(100% - 1.5rem);
            max-height: calc(100vh - 2rem);
            margin: 1rem auto;
            display: flex;
            align-items: stretch;
        }
        #leadFormModal .modal-content.lead-form-shell {
            border: none;
            border-radius: 12px;
            box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.18);
            display: flex;
            flex-direction: column;
            max-height: calc(100vh - 2rem);
            overflow: hidden;
            min-height: 0;
        }
        #leadFormModal .modal-header.lead-form-hd {
            flex: 0 0 auto;
            background: #e11d2e;
            color: #fff;
            border: none;
            padding: 0.95rem 1.25rem;
            border-radius: 12px 12px 0 0;
        }
        #leadFormModal .modal-header.lead-form-hd .modal-title { font-weight: 700; font-size: 1.15rem; display: inline-flex; align-items: center; gap: 0.55rem; }
        #leadFormModal .modal-header.lead-form-hd .close { color: #fff; text-shadow: none; opacity: 0.95; }
        #leadFormModal .modal-body.lead-form-bd {
            flex: 1 1 auto;
            min-height: 0;
            overflow-x: hidden;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
            overscroll-behavior: contain;
            padding: 1.1rem 1.25rem;
            background: #f5f6f8;
        }
        #leadFormModal .lead-form-loading { text-align: center; padding: 3rem 1rem; color: #6c757d; }
        #leadFormModal .lead-form-loading i { font-size: 2rem; margin-bottom: 0.75rem; color: #e11d2e; }
        #leadFormModal .lead-form-error { padding: 2rem; text-align: center; color: #dc3545; }

        @media (max-width: 767px) {
            .crm-intake-pending-ui .dataTables_wrapper .row:first-child > [class*="col-"]:last-child {
                width: 100%;
                margin-top: 0.65rem;
            }

            .crm-intake-pending-ui .dataTables_filter,
            .crm-intake-pending-ui .dataTables_filter label {
                width: 100%;
            }

            .crm-intake-pending-ui .dataTables_filter input {
                width: 100% !important;
            }

            .crm-intake-pending-ui .ip-shell {
                padding: 0.95rem;
            }
        }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper crm-intake-pending-ui">
    <?php include __DIR__ . '/../includes/top-header.php'; ?>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="content-wrapper">
        <?php include __DIR__ . '/../includes/page-header.php'; ?>
        <section class="content">
            <div class="container-fluid">
                <div class="ip-shell">
                    <div class="ip-head">
                        <div class="ip-head-left">
                            <div class="ip-head-icon" aria-hidden="true">
                                <i class="fas fa-shield-alt"></i>
                            </div>
                            <div>
                                <h1 class="ip-title">Pending Lead Verification (Link-Submitted Leads)</h1>
                                <p class="ip-subtitle">Review and verify leads submitted through the website inquiry form.</p>
                            </div>
                        </div>
                        <a href="crm/leads.php" class="ip-back-btn">
                            <i class="fas fa-arrow-left mr-1"></i> Back to Leads
                        </a>
                    </div>

                    <?php if ($pendingCount === 0) { ?>
                        <div class="ip-empty"><i class="fas fa-info-circle mr-1"></i> No pending submissions right now.</div>
                    <?php } else { ?>
                        <table id="intakePendingTable" class="table ip-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Submitted On</th>
                                    <th>Phone</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pendingRows as $item) {
                                    $submittedTs = $item['submitted_at'] !== '' ? strtotime($item['submitted_at']) : false;
                                    $dateFmt = $submittedTs ? date('d M Y', $submittedTs) : '—';
                                    $timeFmt = $submittedTs ? date('h:i A', $submittedTs) : '';
                                    $displayName = $item['customer_name'] !== '' ? $item['customer_name'] : 'Customer';
                                    $initials = crmIntakePendingInitials($displayName);
                                    $email = $item['customer_email'] !== '' ? $item['customer_email'] : '—';
                                    $phoneDisplay = crmIntakePendingPhoneDisplay((string) $item['customer_phone']);
                                    $sortDate = $submittedTs ? date('Y-m-d H:i:s', $submittedTs) : '';
                                    ?>
                                    <tr data-submission-id="<?= (int) $item['submission_id'] ?>">
                                        <td>
                                            <div class="ip-name-cell">
                                                <span class="ip-avatar"><?= htmlspecialchars($initials, ENT_QUOTES, 'UTF-8') ?></span>
                                                <div>
                                                    <p class="ip-name"><?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?></p>
                                                    <p class="ip-email"><?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?></p>
                                                </div>
                                            </div>
                                        </td>
                                        <td data-order="<?= htmlspecialchars($sortDate, ENT_QUOTES, 'UTF-8') ?>">
                                            <div class="ip-meta">
                                                <span class="ip-meta-icon"><i class="far fa-calendar"></i></span>
                                                <div>
                                                    <p class="ip-date"><?= htmlspecialchars($dateFmt, ENT_QUOTES, 'UTF-8') ?></p>
                                                    <?php if ($timeFmt !== '') { ?>
                                                        <p class="ip-time"><?= htmlspecialchars($timeFmt, ENT_QUOTES, 'UTF-8') ?></p>
                                                    <?php } ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="ip-meta">
                                                <span class="ip-meta-icon"><i class="fas fa-phone"></i></span>
                                                <p class="ip-phone mb-0"><?= htmlspecialchars($phoneDisplay, ENT_QUOTES, 'UTF-8') ?></p>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge badge-pending"><i class="far fa-clock"></i> Pending</span>
                                        </td>
                                        <td>
                                            <div class="ip-actions">
                                                <a href="javascript:void(0)" class="action-btn action-btn-approve js-intake-approve"
                                                    data-id="<?= (int) $item['submission_id'] ?>" title="Verify / Approve">
                                                    <i class="fas fa-check"></i>
                                                </a>
                                                <a href="javascript:void(0)" class="action-btn action-btn-edit js-intake-edit"
                                                    data-id="<?= (int) $item['submission_id'] ?>" title="Edit">
                                                    <i class="fas fa-pen"></i>
                                                </a>
                                                <a href="javascript:void(0)" class="action-btn action-btn-delete js-intake-delete"
                                                    data-id="<?= (int) $item['submission_id'] ?>" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                                <div class="dropdown d-inline-flex">
                                                    <a href="javascript:void(0)" class="action-btn action-btn-more dropdown-toggle"
                                                        data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title="More actions">
                                                        <i class="fas fa-ellipsis-v"></i>
                                                    </a>
                                                    <div class="dropdown-menu dropdown-menu-right">
                                                        <a href="javascript:void(0)" class="dropdown-item js-intake-approve" data-id="<?= (int) $item['submission_id'] ?>">
                                                            <i class="fas fa-check mr-2 text-success"></i> Approve
                                                        </a>
                                                        <a href="javascript:void(0)" class="dropdown-item js-intake-edit" data-id="<?= (int) $item['submission_id'] ?>">
                                                            <i class="fas fa-pen mr-2 text-success"></i> Edit
                                                        </a>
                                                        <div class="dropdown-divider"></div>
                                                        <a href="javascript:void(0)" class="dropdown-item text-danger js-intake-delete" data-id="<?= (int) $item['submission_id'] ?>">
                                                            <i class="fas fa-trash mr-2"></i> Delete
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    <?php } ?>
                </div>
            </div>
        </section>
    </div>

    <!-- Approve submission modal (searchable assign-to combobox) -->
    <div class="modal fade" id="intakeApproveModal" tabindex="-1" role="dialog" aria-labelledby="intakeApproveModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="intakeApproveModalLabel"><i class="fas fa-check-circle text-success mr-1"></i> Approve submission?</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2 text-muted">Assign this lead to:</p>
                    <div class="intake-assign-combobox js-intake-assign-combobox">
                        <div class="intake-assign-field js-intake-assign-field">
                            <input type="text" class="intake-assign-search js-intake-assign-search"
                                placeholder="Type to search assignee…" autocomplete="off" aria-label="Assign to">
                        </div>
                        <div class="intake-assign-menu js-intake-assign-menu" style="display:none;"></div>
                        <input type="hidden" class="js-intake-assign-value" value="">
                    </div>
                    <div class="text-danger small mt-2 js-intake-assign-error" style="display:none;">Please select an assignee.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success js-intake-approve-confirm"><i class="fas fa-check mr-1"></i> Approve</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit submission modal (loads the full Create Lead form) -->
    <div class="modal fade" id="leadFormModal" tabindex="-1" role="dialog" aria-labelledby="leadFormModalLabel" aria-hidden="true" data-backdrop="static">
        <div class="modal-dialog modal-dialog-centered modal-xl lead-form-dialog" role="document">
            <div class="modal-content lead-form-shell">
                <div class="modal-header lead-form-hd text-white">
                    <h5 class="modal-title mb-0" id="leadFormModalLabel"><i class="fas fa-edit mr-1"></i> Edit Submission</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body lead-form-bd" id="leadFormModalBody">
                    <div class="lead-form-loading">
                        <div><i class="fas fa-spinner fa-spin d-block"></i></div>
                        Loading form…
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>
<script>
    var intakeEditData = <?php
        $editMap = [];
        foreach ($pendingRows as $item) {
            $editMap[(int) $item['submission_id']] = [
                'assign_to' => $item['assign_to'],
                'admin_name' => $item['admin_name'],
                'lead_source' => $item['lead_source'],
                'payload' => $item['payload'],
            ];
        }
        echo json_encode($editMap, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
    ?>;
    var intakeAssignUsers = <?= json_encode($assignToUsers, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    var intakeAssignSelfName = <?= json_encode($assignToSelfName, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
</script>
<?php include __DIR__ . '/../includes/footer-links.php'; ?>
<script src="plugins/sweetalert2/sweetalert2.min.js"></script>
<script>
$(function () {
    var intakeTable = null;
    if ($('#intakePendingTable').length) {
        intakeTable = $('#intakePendingTable').DataTable({
            order: [],
            pageLength: 10,
            autoWidth: false,
            columnDefs: [
                { orderable: false, targets: [3, 4] }
            ],
            language: {
                search: '',
                searchPlaceholder: 'Search leads...',
                lengthMenu: 'Show _MENU_ entries',
                info: 'Showing _START_ to _END_ of _TOTAL_ entries',
                infoEmpty: 'Showing 0 to 0 of 0 entries',
                zeroRecords: 'No matching leads found',
                paginate: {
                    previous: 'Previous',
                    next: 'Next'
                }
            }
        });
    }

    function showIntakeAlert(type, title, text) {
        Swal.fire({
            icon: type,
            title: title,
            text: text || '',
            confirmButtonColor: type === 'error' ? '#dc3545' : '#007bff',
            confirmButtonText: 'OK'
        });
    }

    function showIntakeConfirm(options) {
        return Swal.fire({
            icon: options.icon || 'question',
            title: options.title || 'Please confirm',
            text: options.text || '',
            showCancelButton: true,
            confirmButtonColor: options.confirmColor || '#007bff',
            cancelButtonColor: '#6c757d',
            confirmButtonText: options.confirmText || 'Yes',
            cancelButtonText: options.cancelText || 'Cancel'
        });
    }

    // SweetAlert2 v9 uses result.value; v10+ uses result.isConfirmed (boolean confirm only)
    function intakeSwalConfirmed(result) {
        return !!(result && (result.value === true || result.isConfirmed));
    }

    function escapeHtml(text) {
        return String(text || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function buildAssigneeList() {
        var list = [{
            value: '__self__',
            label: 'To Self' + (intakeAssignSelfName ? ' (' + intakeAssignSelfName + ')' : '')
        }];
        (intakeAssignUsers || []).forEach(function (u) {
            var value = (u.username || u.full_name || '').trim();
            var label = (u.full_name || u.username || '').trim();
            if (!value || !label) {
                return;
            }
            if (u.full_name && u.username) {
                label += ' (' + u.username + ')';
            }
            list.push({ value: value, label: label });
        });
        return list;
    }

    var intakeAssigneeList = buildAssigneeList();

    function findAssigneeByValue(value) {
        var target = String(value || '');
        for (var i = 0; i < intakeAssigneeList.length; i++) {
            if (String(intakeAssigneeList[i].value) === target) {
                return intakeAssigneeList[i];
            }
        }
        return null;
    }

    function resolveDefaultAssign(submissionId) {
        var data = intakeEditData[submissionId] || {};
        var preferred = (data.assign_to || data.admin_name || '').trim();
        if (preferred && findAssigneeByValue(preferred)) {
            return preferred;
        }
        if (preferred) {
            for (var i = 0; i < intakeAssigneeList.length; i++) {
                if (intakeAssigneeList[i].label.indexOf(preferred) >= 0) {
                    return intakeAssigneeList[i].value;
                }
            }
        }
        return '__self__';
    }

    var $approveModal = $('#intakeApproveModal');
    var $approveSearch = $approveModal.find('.js-intake-assign-search');
    var $approveMenu = $approveModal.find('.js-intake-assign-menu');
    var $approveValue = $approveModal.find('.js-intake-assign-value');
    var $approveError = $approveModal.find('.js-intake-assign-error');
    var $approveField = $approveModal.find('.js-intake-assign-field');
    var approveDialogResolve = null;
    var approveDialogResult = null;

    function hideIntakeAssignMenu() {
        $approveMenu.hide().empty();
    }

    function setIntakeAssignee(option) {
        if (!option) {
            $approveValue.val('');
            $approveSearch.val('');
            return;
        }
        $approveValue.val(option.value);
        $approveSearch.val(option.label);
        $approveError.hide();
    }

    function renderIntakeAssignMenu(filterText) {
        var query = (filterText || '').trim().toLowerCase();
        var filtered = intakeAssigneeList.filter(function (item) {
            if (!query) {
                return true;
            }
            return item.label.toLowerCase().indexOf(query) >= 0
                || String(item.value).toLowerCase().indexOf(query) >= 0;
        });

        $approveMenu.empty();

        if (filtered.length === 0) {
            var emptyMsg = query
                ? 'No users found for "' + escapeHtml(query) + '"'
                : 'No users found';
            $approveMenu.append('<div class="intake-assign-empty">' + emptyMsg + '</div>');
        } else {
            filtered.forEach(function (item) {
                $approveMenu.append(
                    $('<button type="button" class="intake-assign-item"></button>')
                        .attr('data-value', item.value)
                        .text(item.label)
                );
            });
        }

        $approveMenu.show();
    }

    function showApproveDialog(submissionId) {
        var defaultAssign = resolveDefaultAssign(submissionId);
        var defaultOption = findAssigneeByValue(defaultAssign) || intakeAssigneeList[0] || null;

        return new Promise(function (resolve) {
            approveDialogResolve = resolve;
            approveDialogResult = null;
            hideIntakeAssignMenu();
            setIntakeAssignee(defaultOption);
            $approveError.hide();
            $approveModal.modal('show');
        });
    }

    $approveField.on('click mousedown', function (e) {
        e.preventDefault();
        $approveSearch.focus();
        renderIntakeAssignMenu($approveSearch.val());
    });

    $approveSearch.on('click', function () {
        renderIntakeAssignMenu($approveSearch.val());
    }).on('input', function () {
        $approveValue.val('');
        renderIntakeAssignMenu($approveSearch.val());
    }).on('blur', function () {
        window.setTimeout(function () {
            hideIntakeAssignMenu();
            var selected = findAssigneeByValue($approveValue.val());
            if (selected) {
                $approveSearch.val(selected.label);
            }
        }, 150);
    });

    $approveMenu.on('mousedown', '.intake-assign-item', function (e) {
        e.preventDefault();
        var value = $(this).data('value');
        var option = findAssigneeByValue(value);
        if (option) {
            setIntakeAssignee(option);
            hideIntakeAssignMenu();
        }
    });

    $approveModal.on('click', function (e) {
        if (!$(e.target).closest('.js-intake-assign-combobox').length) {
            hideIntakeAssignMenu();
        }
    });

    $approveModal.on('hidden.bs.modal', function () {
        hideIntakeAssignMenu();
        if (approveDialogResolve) {
            approveDialogResolve(approveDialogResult || { dismiss: true });
            approveDialogResolve = null;
            approveDialogResult = null;
        }
    });

    $approveModal.find('.js-intake-approve-confirm').on('click', function () {
        var assignTo = ($approveValue.val() || '').toString().trim();
        if (!assignTo) {
            var typed = ($approveSearch.val() || '').trim().toLowerCase();
            var match = intakeAssigneeList.find(function (item) {
                return item.label.toLowerCase() === typed || String(item.value).toLowerCase() === typed;
            });
            if (match) {
                assignTo = match.value;
                setIntakeAssignee(match);
            }
        }
        if (!assignTo) {
            $approveError.show();
            $approveSearch.focus();
            return;
        }
        approveDialogResult = { value: assignTo };
        $approveModal.modal('hide');
    });

    function postIntake(url, submissionId, extra, $row, successMessage) {
        var data = { submission_id: submissionId };
        if (extra) $.extend(data, extra);
        $.post(url, data, function (res) {
            if (res && res.success) {
                if (successMessage) {
                    var leadUrl = 'crm/leads.php';
                    Swal.fire({
                        icon: 'success',
                        title: 'Success',
                        text: (res && res.message) ? res.message : successMessage,
                        confirmButtonColor: '#28a745',
                        confirmButtonText: 'View Leads',
                        showCancelButton: true,
                        cancelButtonText: 'Stay here',
                        cancelButtonColor: '#6c757d'
                    }).then(function (swalResult) {
                        if (intakeSwalConfirmed(swalResult)) {
                            window.location.href = leadUrl;
                        }
                    });
                }
                if (intakeTable) {
                    intakeTable.row($row).remove().draw(false);
                    if (intakeTable.rows().count() === 0) {
                        setTimeout(function () { location.reload(); }, successMessage ? 1200 : 0);
                    }
                } else {
                    $row.fadeOut(300, function () { $(this).remove(); });
                    if ($('tr[data-submission-id]').length === 0) {
                        setTimeout(function () { location.reload(); }, successMessage ? 1200 : 0);
                    }
                }
            } else {
                showIntakeAlert('error', 'Action failed', (res && res.message) ? res.message : 'Something went wrong.');
            }
        }, 'json').fail(function () {
            showIntakeAlert('error', 'Request failed', 'Please try again.');
        });
    }

    $(document).on('click', '.js-intake-approve', function (e) {
        e.preventDefault();
        var id = $(this).data('id');
        var $row = $(this).closest('tr[data-submission-id]');
        showApproveDialog(id).then(function (result) {
            if (result.dismiss) {
                return;
            }
            var assignTo = (result.value || '').toString().trim();
            if (!assignTo) {
                showIntakeAlert('error', 'Required', 'Please select assignee.');
                return;
            }
            postIntake('crm/ajax/approve_intake.php', id, { assign_to: assignTo }, $row, 'Lead approved and added to CRM Leads.');
        });
    });

    $(document).on('click', '.js-intake-delete', function (e) {
        e.preventDefault();
        var id = $(this).data('id');
        var $row = $(this).closest('tr[data-submission-id]');
        showIntakeConfirm({
            icon: 'warning',
            title: 'Delete submission?',
            text: 'Delete this submission permanently? This cannot be undone.',
            confirmColor: '#dc3545',
            confirmText: 'Yes, delete'
        }).then(function (result) {
            if (intakeSwalConfirmed(result)) {
                postIntake('crm/ajax/delete_intake.php', id, null, $row, 'Submission deleted successfully.');
            }
        });
    });

    // ---- Edit: load the full Create Lead form, pre-filled with the submission ----
    var leadFormUrl = 'crm/lead_add.php?embed=1';
    var $editModal = $('#leadFormModal');
    var $editBody = $('#leadFormModalBody');
    var editLoadSeq = 0;

    function showEditLoading() {
        $editBody.html('<div class="lead-form-loading"><div><i class="fas fa-spinner fa-spin d-block"></i></div>Loading form…</div>');
    }

    function openEditForm(submissionId) {
        var data = intakeEditData[submissionId];
        if (!data) {
            showIntakeAlert('error', 'Could not load', 'Submission data is not available.');
            return;
        }

        var prefill = $.extend({}, data.payload || {}, {
            assign_to: data.assign_to || (data.payload ? data.payload.assign_to : '') || '',
            lead_source: data.lead_source || (data.payload ? data.payload.lead_source : '') || ''
        });

        var seq = ++editLoadSeq;
        showEditLoading();
        $editModal.modal('show');

        $editBody.load(leadFormUrl, function (response, status) {
            if (seq !== editLoadSeq) { return; }
            if (status === 'error' || !$.trim(response)) {
                $editBody.html('<div class="lead-form-error"><i class="fas fa-exclamation-triangle mr-2"></i>Could not load the edit form. Please try again.</div>');
                return;
            }
            var $form = $editBody.find('form.crm-lead-create-form').first();
            if (!$form.length) { return; }

            // Reconfigure the form for "edit submission" mode.
            $form.attr('data-save-url', 'crm/ajax/update_intake.php');
            $form.find('input[name="submission_id"]').remove();
            $form.prepend('<input type="hidden" name="submission_id" value="' + submissionId + '">');
            $form.attr('data-lead-prefill', JSON.stringify(prefill));

            // Relabel the submit button.
            if (typeof window.initLeadCreateForm === 'function') {
                window.initLeadCreateForm($form[0]);
            }
            $form.find('.js-lead-submit-btn').text('Save Changes');
        });
    }

    $(document).on('click', '.js-intake-edit', function (e) {
        e.preventDefault();
        openEditForm($(this).data('id'));
    });

    // The form triggers crm:lead-created on a successful save; reload to refresh the queue.
    $(document).on('crm:lead-created', function () {
        setTimeout(function () { location.reload(); }, 600);
    });
});
</script>
</body>
</html>
