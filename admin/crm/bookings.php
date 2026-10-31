<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/includes/confirmed_bookings.php';

$bookings = array_values(array_filter(cbList($conn), static function (array $row): bool {
    // Confirmed tours only (exclude cancelled).
    return (string) ($row['phase'] ?? '') !== 'cancelled';
}));
$ledgers = cbLedgers($conn, $bookings);
$destinations = [];
$customers = [];
$suppliers = [];
$tourTypes = [];
foreach ($bookings as $row) {
    if ($row['destination'] !== '' && !in_array($row['destination'], $destinations, true)) {
        $destinations[] = $row['destination'];
    }
    if ($row['guest'] !== '' && !in_array($row['guest'], $customers, true)) {
        $customers[] = $row['guest'];
    }
    foreach ($row['suppliers'] as $name) {
        if ($name !== '' && !in_array($name, $suppliers, true)) {
            $suppliers[] = $name;
        }
    }
    if ($row['tour_type'] !== '' && !in_array($row['tour_type'], $tourTypes, true)) {
        $tourTypes[] = $row['tour_type'];
    }
}
sort($destinations);
sort($customers);
sort($suppliers);
sort($tourTypes);
$leadStageOptions = crmLeadStageOptions();
$boot = json_encode([
    'bookings' => $bookings,
    'customer_payments' => $ledgers['customer'],
    'supplier_payments' => $ledgers['supplier'],
    'activity' => $ledgers['activity'],
    'methods' => spPaymentMethods(),
    'today' => date('Y-m-d'),
    'stages' => $leadStageOptions,
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <base href="../">
    <title>All Bookings</title>
    <?php include __DIR__ . '/../includes/header-links.php'; ?>
    <link rel="stylesheet" href="crm/assets/quotation_confirm_tour_page.css?v=1">
    <link rel="stylesheet" href="crm/assets/quotation_preview_modal.css?v=1">
    <style>
        .bk-page { background: #f4f7fb; }
        .bk-page .content-wrapper, .bk-page .content { background: #f4f7fb; }
        .bk-page .content-header { display: none; }
        .bk-page .content { padding: 1rem 1.1rem 1.4rem; }
        .bk-page button, .bk-page input, .bk-page select { font-family: inherit; }
        .bk-head, .bk-toolbar, .bk-foot { display: flex; justify-content: space-between; gap: 0.7rem; align-items: center; flex-wrap: wrap; }
        .bk-title { margin: 0; font-size: 1.45rem; font-weight: 800; color: #0f172a; }
        .bk-sub { margin: 0.2rem 0 0; color: #94a3b8; font-size: 0.86rem; max-width: 720px; }
        .bk-actions, .bk-toolbar { display: flex; gap: 0.4rem; flex-wrap: wrap; align-items: center; }
        .bk-btn, .bk-search, .bk-select, .bk-dates { height: 36px; border-radius: 10px; border: 1px solid #e6ebf2; background: #fff; color: #334155; font-size: 0.8rem; font-weight: 700; }
        .bk-btn { padding: 0 0.75rem; display: inline-flex; align-items: center; gap: 0.35rem; }
        .bk-btn-red { background: #e11d48; border-color: #e11d48; color: #fff; }
        .bk-dates { display: inline-flex; align-items: center; gap: 0.3rem; padding: 0 0.5rem; }
        .bk-dates input { border: 0; outline: none; width: 124px; font-weight: 600; }
        .bk-select { padding: 0 0.5rem; }
        .bk-search { display: flex; align-items: center; gap: 0.35rem; padding: 0 0.65rem; min-width: 220px; flex: 1; font-weight: 500; }
        .bk-search input { border: 0; outline: none; width: 100%; font-weight: 500; }
        .bk-reset { border: 0; background: transparent; color: #e11d48; font-weight: 800; height: 36px; }
        .bk-stats { display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap: 0.65rem; margin: 0.85rem 0; }
        .bk-stat { background: #fff; border-radius: 14px; padding: 0.75rem 0.8rem; box-shadow: 0 8px 24px rgba(15, 23, 42, 0.04); min-height: 92px; }
        .bk-stat .bk-ico { width: 34px; height: 34px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; color: #fff; margin-bottom: 0.35rem; }
        .bk-stat b { display: block; font-size: 1.25rem; color: #0f172a; line-height: 1.15; }
        .bk-stat span, .bk-stat small { display: block; }
        .bk-stat span { color: #64748b; font-size: 0.75rem; font-weight: 700; }
        .bk-stat small { color: #16a34a; font-weight: 800; }
        .bk-stat small.is-down { color: #e11d48; }
        .bk-grid { display: grid; grid-template-columns: 1.4fr 0.9fr 0.9fr; gap: 0.7rem; margin-bottom: 0.8rem; }
        .bk-card { background: #fff; border-radius: 16px; box-shadow: 0 8px 24px rgba(15, 23, 42, 0.04); padding: 0.85rem; }
        .bk-card h3 { margin: 0 0 0.6rem; font-size: 0.92rem; font-weight: 800; display: flex; justify-content: space-between; align-items: center; }
        .bk-card h3 button { border: 0; background: transparent; color: #e11d48; font-weight: 800; font-size: 0.75rem; }
        .bk-legend { display: flex; gap: 0.7rem; color: #64748b; font-size: 0.72rem; font-weight: 700; }
        .bk-legend i { display: inline-block; width: 8px; height: 8px; border-radius: 2px; margin-right: 0.25rem; }
        .bk-bars { display: flex; align-items: flex-end; gap: 0.45rem; height: 150px; }
        .bk-bar { flex: 1; display: flex; align-items: flex-end; justify-content: center; gap: 2px; height: 120px; position: relative; }
        .bk-bar em { position: absolute; top: -2px; font-style: normal; font-size: 0.65rem; color: #94a3b8; }
        .bk-bar span { display: block; width: 8px; border-radius: 4px 4px 0 0; background: #fb7185; min-height: 2px; }
        .bk-bar span.is-rev { background: #fecdd3; }
        .bk-bar label { position: absolute; bottom: -18px; font-size: 0.65rem; color: #94a3b8; }
        .bk-donut-wrap { display: flex; gap: 0.7rem; align-items: center; }
        .bk-donut { width: 112px; height: 112px; border-radius: 50%; display: grid; place-items: center; flex: 0 0 auto; }
        .bk-donut b { width: 68px; height: 68px; border-radius: 50%; background: #fff; display: grid; place-items: center; font-size: 0.95rem; }
        .bk-key { font-size: 0.75rem; color: #64748b; }
        .bk-key div { display: flex; justify-content: space-between; gap: 0.8rem; padding: 0.15rem 0; }
        .bk-key i { width: 8px; height: 8px; border-radius: 50%; display: inline-block; margin-right: 0.3rem; }
        .bk-mini { display: flex; gap: 0.5rem; align-items: center; padding: 0.4rem 0; border-bottom: 1px solid #f8fafc; }
        .bk-mini:last-child { border-bottom: 0; }
        .bk-photo { width: 42px; height: 36px; border-radius: 8px; background: linear-gradient(135deg, #fecdd3, #ffe4e6); color: #e11d48; display: inline-flex; align-items: center; justify-content: center; flex: 0 0 auto; }
        .bk-mini b, .bk-guest b, .bk-code b { display: block; color: #0f172a; }
        .bk-muted { color: #94a3b8; font-size: 0.72rem; }
        .bk-layout { display: grid; grid-template-columns: minmax(0, 1fr); gap: 0.75rem; align-items: start; }
        .bk-tabs { display: flex; gap: 0.15rem; overflow: auto; border-bottom: 1px solid #f1f5f9; margin-bottom: 0.7rem; }
        .bk-tabs button { border: 0; background: transparent; color: #64748b; font-weight: 800; font-size: 0.78rem; padding: 0.45rem 0.55rem; border-bottom: 2px solid transparent; white-space: nowrap; }
        .bk-tabs button.is-on { color: #e11d48; border-bottom-color: #e11d48; }
        .bk-table-wrap { overflow: auto; border: 1px solid #e5e7eb; border-radius: 10px; background: #fff; }
        table.bk-table {
            width: 100%;
            min-width: 980px;
            table-layout: fixed;
            border-collapse: collapse;
            font-size: 0.82rem;
            background: #fff;
        }
        .bk-table th {
            text-align: left;
            color: #6b7280;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            padding: 0.85rem 0.75rem;
            border-bottom: 1px solid #e5e7eb;
            white-space: nowrap;
            background: #fff;
            position: sticky;
            top: 0;
            z-index: 2;
        }
        .bk-table td {
            padding: 0.4rem 0.75rem;
            border-top: 1px solid #f3f4f6;
            vertical-align: middle;
            color: #334155;
            line-height: 1.35;
            overflow: hidden;
        }
        .bk-table tr.is-highlight td { background: #fff1f2; }
        .bk-table tr.is-highlight td:first-child { box-shadow: inset 3px 0 0 #e53935; }
        .bk-table .col-bk-id { width: 14%; }
        .bk-table .col-bk-guest { width: 18%; }
        .bk-table .col-bk-dest { width: 16%; }
        .bk-table .col-bk-date { width: 12%; text-align: center; }
        .bk-table .col-bk-svc { width: 12%; text-align: center; }
        .bk-table .col-bk-stage { width: 10%; text-align: center; overflow: visible; }
        .bk-table .col-bk-status { width: 10%; text-align: center; }
        .bk-table .col-bk-actions { width: 10%; text-align: center; overflow: visible; }
        .bk-table .lead-stage-select {
            font-weight: 700;
            padding: 0.35rem 0.75rem;
            border-radius: 999px;
            font-size: calc(0.68rem + 1px);
            border: 1px solid transparent;
            white-space: nowrap;
            display: inline-block;
            text-align: center;
            line-height: 1.25;
            text-transform: uppercase;
            letter-spacing: 0.02em;
            max-width: 100%;
            cursor: pointer;
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background-image: none;
        }
        .bk-table .lead-stage-select::-ms-expand { display: none; }
        .bk-table .lead-stage-select:focus {
            outline: none;
            box-shadow: 0 0 0 2px rgba(229, 57, 53, 0.16);
        }
        .bk-table .lead-stage-select.stage-new_lead {
            background-color: #e3f2fd;
            border-color: #bbdefb;
            color: #1e88e5;
        }
        .bk-table .lead-stage-select.stage-quoted {
            background-color: #fff3e0;
            border-color: #ffe0b2;
            color: #fb8c00;
        }
        .bk-table .lead-stage-select.stage-confirmed {
            background-color: #e8f5e9;
            border-color: #c8e6c9;
            color: #43a047;
        }
        .bk-table .lead-stage-select.stage-lost {
            background-color: #f5f5f5;
            border-color: #e0e0e0;
            color: #757575;
        }
        .bk-table .lead-stage-select[data-stage-auto="1"] { cursor: default; }
        .bk-id-cell {
            display: block;
            max-width: 100%;
            padding: 0;
            margin: 0;
            border: 0;
            background: transparent;
            text-align: left;
            cursor: pointer;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .bk-id-uid { font-weight: 700; color: #e53935; }
        .bk-id-cell:hover .bk-id-uid { color: #c62828; text-decoration: underline; }
        .bk-id-meta { color: #9ca3af; font-size: 0.72rem; font-weight: 400; }
        .bk-guest-wrap {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            min-width: 0;
            max-width: 100%;
        }
        .bk-guest-initials {
            flex: 0 0 auto;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: #e0f2fe;
            color: #0369a1;
            font-size: 0.68rem;
            font-weight: 700;
            letter-spacing: 0.02em;
        }
        .bk-guest-name {
            text-transform: capitalize;
            font-weight: 600;
            color: #0f172a;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            min-width: 0;
        }
        .bk-pax-badge {
            flex-shrink: 0;
            background: #ecfdf5;
            color: #047857;
            border-radius: 999px;
            padding: 0.1rem 0.4rem;
            font-size: 0.68rem;
            font-weight: 700;
        }
        .bk-dest-name { font-weight: 700; color: #0f172a; text-transform: uppercase; }
        .bk-dest-sub { color: #9ca3af; font-size: 0.72rem; }
        .bk-date-badge {
            display: inline-block;
            color: #e53935;
            font-weight: 700;
            white-space: nowrap;
        }
        .bk-date-badge i { margin-right: 0.2rem; color: #e53935; }
        .bk-svc-pills { display: inline-flex; flex-wrap: wrap; gap: 0.3rem; justify-content: center; }
        .bk-svc-pill {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 0.18rem 0.5rem;
            border-radius: 999px;
            font-size: 0.72rem;
            font-weight: 600;
            white-space: nowrap;
            background: #ede9fe;
            color: #6d28d9;
        }
        .bk-svc-pill.is-flight { background: #dbeafe; color: #1d4ed8; }
        .bk-svc-pill.is-hotel { background: #ffedd5; color: #c2410c; }
        .bk-svc-pill.is-tour { background: #ede9fe; color: #6d28d9; }
        .bk-due { color: #e11d48; font-weight: 700; }
        .bk-ok { color: #15803d; font-weight: 800; }
        .bk-pill {
            display: inline-flex;
            border-radius: 999px;
            padding: 0.18rem 0.55rem;
            font-weight: 800;
            font-size: 0.68rem;
            text-transform: uppercase;
            letter-spacing: 0.02em;
        }
        .bk-pill.is-confirmed, .bk-pill.is-paid { background: #dcfce7; color: #15803d; }
        .bk-pill.is-progress, .bk-pill.is-partial { background: #ffedd5; color: #c2410c; }
        .bk-pill.is-completed { background: #dbeafe; color: #1d4ed8; }
        .bk-pill.is-cancelled, .bk-pill.is-pending { background: #f1f5f9; color: #64748b; }
        .bk-pill.is-overdue { background: #ffe4e6; color: #be123c; }
        .bk-action-btns {
            display: inline-flex;
            gap: 2px;
            align-items: center;
            justify-content: center;
            white-space: nowrap;
        }
        .bk-action-btns .btn-icon {
            width: 28px;
            height: 28px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 7px;
            border: 1px solid transparent;
            font-size: 0.78rem;
            text-decoration: none;
            background: #f5f5f5;
            color: #616161;
            cursor: pointer;
            transition: background-color 0.15s ease, border-color 0.15s ease, color 0.15s ease, box-shadow 0.15s ease;
        }
        .bk-action-btns .btn-view {
            background: #e8f5e9;
            border-color: #c8e6c9;
            color: #2e7d32 !important;
        }
        .bk-action-btns .btn-view:hover {
            background: #c8e6c9;
            color: #1b5e20 !important;
            border-color: #a5d6a7;
            box-shadow: 0 1px 3px rgba(46, 125, 50, 0.15);
        }
        .bk-action-btns .btn-book {
            background: #fff7ed;
            border-color: #fed7aa;
            color: #c2410c !important;
        }
        .bk-action-btns .btn-book .btn-book-img {
            width: 14px;
            height: 14px;
            object-fit: contain;
            display: block;
            pointer-events: none;
        }
        .bk-action-btns .btn-book:hover {
            background: #ffedd5;
            color: #9a3412 !important;
            border-color: #fdba74;
            box-shadow: 0 1px 3px rgba(194, 65, 12, 0.15);
        }
        .bk-action-btns .btn-more {
            background: #f5f5f5;
            border-color: #e0e0e0;
            color: #616161 !important;
        }
        .bk-action-btns .btn-more:hover,
        .bk-action-btns .btn-more[aria-expanded="true"] {
            background: #eeeeee;
            color: #424242 !important;
            border-color: #bdbdbd;
        }
        .bk-table tr.is-actions-dropdown-open td.col-bk-actions {
            overflow: visible;
            z-index: 5;
            position: relative;
        }
        .bk-actions-more { position: relative; display: inline-block; }
        .bk-actions-menu {
            display: none;
            position: fixed;
            min-width: 11.5rem;
            padding: 0.35rem 0;
            border: 1px solid #e2e8f0;
            border-radius: 0.35rem;
            background: #fff;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.12);
            z-index: 2060;
        }
        .bk-actions-menu.is-open { display: block; }
        .bk-actions-menu .dropdown-item {
            display: flex;
            align-items: center;
            width: 100%;
            border: 0;
            background: #fff;
            padding: 0.45rem 0.85rem;
            font-size: 0.82rem;
            color: #334155;
            text-align: left;
            cursor: pointer;
        }
        .bk-actions-menu .dropdown-item i {
            width: 1.1rem;
            text-align: center;
            margin-right: 0.45rem;
        }
        .bk-actions-menu .dropdown-item:hover { background: #f8fafc; }
        .bk-actions-menu .dropdown-item.text-danger { color: #dc2626; }
        .bk-actions-menu .dropdown-item.text-danger:hover { background: #fef2f2; }
        .bk-actions-menu .dropdown-divider {
            height: 0;
            margin: 0.3rem 0;
            border-top: 1px solid #e2e8f0;
        }
        .bk-foot { margin-top: 0.7rem; color: #64748b; font-size: 0.78rem; }
        .bk-pages button { height: 30px; min-width: 30px; border-radius: 8px; border: 1px solid #e6ebf2; background: #fff; font-weight: 700; }
        .bk-pages button.is-on { background: #e11d48; color: #fff; border-color: #e11d48; }
        .bk-empty { color: #94a3b8; padding: 1rem 0.2rem; }
        .bk-time-item { display: grid; grid-template-columns: 16px 1fr auto; gap: 0.55rem; padding: 0.45rem 0; }
        .bk-dot { width: 10px; height: 10px; border-radius: 50%; background: #e11d48; margin-top: 0.3rem; }
        #bkView .modal-header, #bkPay .modal-header { background: #e11d48; color: #fff; }
        #bkView .modal-header .close, #bkPay .modal-header .close { color: #fff; opacity: 1; text-shadow: none; }
        .bk-kv { display: flex; justify-content: space-between; gap: 0.6rem; padding: 0.28rem 0; font-size: 0.82rem; }
        .bk-kv span { color: #94a3b8; }
        @media (max-width: 1199.98px) {
            .bk-stats { grid-template-columns: repeat(3, minmax(0, 1fr)); }
            .bk-grid, .bk-layout { grid-template-columns: 1fr; }
        }
        @media (max-width: 767.98px) {
            .bk-stats { grid-template-columns: 1fr 1fr; }
        }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed bk-page">
<div class="wrapper">
    <?php include __DIR__ . '/../includes/top-header.php'; ?>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="content-wrapper">
        <section class="content">
            <div class="bk-head">
                <div>
                    <h1 class="bk-title">Confirmed Bookings Management</h1>
                    <p class="bk-sub">View and manage all confirmed tours, customer and supplier payments, upcoming tours and booking timeline.</p>
                </div>
                <div class="bk-actions">
                    <select id="bkDest" class="bk-select" aria-label="Destination">
                        <option value="">All Tours</option>
                        <?php foreach ($destinations as $name) { ?>
                            <option value="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php } ?>
                    </select>
                    <label class="bk-dates"><input type="date" id="bkFrom" aria-label="Travel from"><span>–</span><input type="date" id="bkTo" aria-label="Travel to"></label>
                    <button type="button" class="bk-btn" id="bkExport"><i class="fas fa-download"></i> Export</button>
                    <a class="bk-btn" href="crm/hotel_bookings.php"><i class="fas fa-hotel"></i> Hotels</a>
                </div>
            </div>

            <div class="bk-stats" id="bkStats"></div>
            <div class="bk-grid">
                <div class="bk-card">
                    <h3>Monthly Booking Trend <span class="bk-legend"><i style="background:#fb7185"></i> Bookings <i style="background:#fecdd3"></i> Revenue (Lakh)</span></h3>
                    <div class="bk-bars" id="bkBars"></div>
                </div>
                <div class="bk-card">
                    <h3>Customer Payment Status</h3>
                    <div class="bk-donut-wrap" id="bkCustDonut"></div>
                </div>
                <div class="bk-card">
                    <h3>Supplier Payment Status</h3>
                    <div class="bk-donut-wrap" id="bkSupDonut"></div>
                </div>
            </div>

            <div class="bk-layout">
                <div class="bk-card">
                    <div class="bk-tabs" id="bkTabs">
                        <button type="button" data-tab="all" class="is-on">All Bookings</button>
                        <button type="button" data-tab="upcoming">Upcoming</button>
                        <button type="button" data-tab="ongoing">Ongoing</button>
                        <button type="button" data-tab="completed">Completed</button>
                        <button type="button" data-tab="customer">Customer Payments</button>
                        <button type="button" data-tab="supplier">Supplier Payments</button>
                        <button type="button" data-tab="timeline">Timeline</button>
                    </div>
                    <div class="bk-toolbar">
                        <label class="bk-search"><i class="fas fa-search"></i><input type="search" id="bkSearch" placeholder="Search booking ID, customer, destination"></label>
                        <select id="bkCustomer" class="bk-select" aria-label="Customer"><option value="">Customer</option><?php foreach ($customers as $name) { ?><option value="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></option><?php } ?></select>
                        <select id="bkSupplier" class="bk-select" aria-label="Supplier"><option value="">Supplier</option><?php foreach ($suppliers as $name) { ?><option value="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></option><?php } ?></select>
                        <select id="bkPay" class="bk-select" aria-label="Payment status">
                            <option value="">Payment Status</option>
                            <option value="paid">Paid</option>
                            <option value="partial">Partial</option>
                            <option value="pending">Pending</option>
                            <option value="overdue">Overdue</option>
                        </select>
                        <select id="bkType" class="bk-select" aria-label="Tour type"><option value="">Tour Type</option><?php foreach ($tourTypes as $name) { ?><option value="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></option><?php } ?></select>
                        <button type="button" class="bk-reset" id="bkReset">Reset</button>
                        <a class="bk-btn bk-btn-red" href="crm/leads.php"><i class="fas fa-plus"></i> Add Booking</a>
                    </div>
                    <div id="bkBody"></div>
                    <div class="bk-foot">
                        <span id="bkCount"></span>
                        <span>
                            <span class="bk-pages" id="bkPages"></span>
                            <select id="bkSize" class="bk-select" aria-label="Rows per page">
                                <option value="10">10 / page</option>
                                <option value="25">25 / page</option>
                                <option value="50">50 / page</option>
                            </select>
                        </span>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>

<div class="modal fade" id="bkView" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Booking</h5><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
            <div class="modal-body" id="bkViewBody"></div>
        </div>
    </div>
</div>
<div class="modal fade" id="bkPay" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Record payment</h5><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
            <form id="bkPayForm" class="modal-body">
                <input type="hidden" id="bkPayId">
                <div class="form-group">
                    <label>Apply to</label>
                    <select class="form-control" id="bkPayTarget"></select>
                </div>
                <div class="form-group"><label>Amount (₹)</label><input class="form-control" id="bkPayAmount" inputmode="decimal" required></div>
                <div class="form-group"><label>Date</label><input type="date" class="form-control" id="bkPayDate" required></div>
                <div class="form-group"><label>Method</label><select class="form-control" id="bkPayMethod"></select></div>
                <div class="form-group"><label>Reference</label><input class="form-control" id="bkPayRef" maxlength="120"></div>
                <button type="submit" class="btn btn-danger">Save payment</button>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/confirm_tour_modals.php'; ?>
<?php include __DIR__ . '/includes/quotation_preview_modal.php'; ?>

<?php include __DIR__ . '/../includes/footer-links.php'; ?>
<script>
(function ($) {
    var boot = <?= $boot ?: '{}' ?>;
    var rows = boot.bookings || [];
    var highlightId = 0;
    try {
        highlightId = parseInt(new URLSearchParams(window.location.search).get('highlight') || '0', 10) || 0;
    } catch (err) {
        highlightId = 0;
    }
    var highlightScrolled = false;
    var state = { page: 1, size: 10, tab: 'all' };
    var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

    function esc(s) { return $('<div>').text(s == null ? '' : String(s)).html(); }
    function money(n) {
        n = Math.round((parseFloat(n) || 0) * 100) / 100;
        return '₹' + n.toLocaleString('en-IN', { maximumFractionDigits: n % 1 ? 2 : 0 });
    }
    function fmt(iso) {
        if (!iso) return '—';
        var d = new Date(iso + (String(iso).length === 10 ? 'T00:00:00' : ''));
        if (isNaN(d.getTime())) return iso;
        return d.getDate() + ' ' + months[d.getMonth()] + ' ' + d.getFullYear();
    }
    function fmtTravelShort(iso) {
        if (!iso) return '';
        var d = new Date(String(iso).slice(0, 10) + 'T00:00:00');
        if (isNaN(d.getTime())) return '';
        var yy = String(d.getFullYear()).slice(-2);
        return pad(d.getDate()) + '-' + months[d.getMonth()] + '-' + yy;
    }
    function fmtCreatedMeta(raw) {
        if (!raw) return '';
        var d = new Date(raw);
        if (isNaN(d.getTime())) return '';
        var h = d.getHours();
        var ampm = h >= 12 ? 'PM' : 'AM';
        h = h % 12;
        if (!h) h = 12;
        return pad(d.getDate()) + ' ' + months[d.getMonth()].toUpperCase() + ', ' +
            pad(h) + ':' + pad(d.getMinutes()) + ' ' + ampm;
    }
    function guestInitials(name) {
        name = String(name || '').replace(/^(Mr|Mrs|Ms|Master|Miss|Mstr)\.?\s+/i, '').trim();
        if (!name) return '';
        var parts = name.split(/\s+/).filter(Boolean);
        if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
        return (parts[0].charAt(0) + parts[parts.length - 1].charAt(0)).toUpperCase();
    }
    function paxBadge(b) {
        var parts = [];
        if (b.adults > 0) parts.push(b.adults + 'A');
        if (b.children > 0) parts.push(b.children + 'C');
        if (b.infants > 0) parts.push(b.infants + 'I');
        if (!parts.length && b.pax > 0) parts.push(b.pax + 'P');
        return parts.join(' ');
    }
    function servicesHtml(b) {
        var pills = [];
        if ((b.flight_count || 0) > 0) {
            pills.push('<span class="bk-svc-pill is-flight"><i class="fas fa-plane"></i> Flight</span>');
        }
        if ((b.hotel_count || 0) > 0) {
            pills.push('<span class="bk-svc-pill is-hotel"><i class="fas fa-hotel"></i> Hotel</span>');
        }
        var type = String(b.tour_type || '').trim();
        if (!pills.length) {
            pills.push('<span class="bk-svc-pill is-tour"><i class="fas fa-suitcase-rolling"></i> ' + esc(type || 'Tour') + '</span>');
        } else if (type && !/^(flight|hotel)s?$/i.test(type)) {
            pills.unshift('<span class="bk-svc-pill is-tour"><i class="fas fa-suitcase-rolling"></i> Tour</span>');
        }
        return '<div class="bk-svc-pills">' + pills.join('') + '</div>';
    }
    function payStatusPill(status) {
        status = String(status || 'pending');
        var label = status.charAt(0).toUpperCase() + status.slice(1);
        return '<span class="bk-pill is-' + esc(status) + '">' + esc(label) + '</span>';
    }
    function pad(n) { return (n < 10 ? '0' : '') + n; }
    function monthKey(d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1); }
    function addDays(iso, days) {
        var d = new Date(iso + 'T00:00:00');
        d.setDate(d.getDate() + days);
        return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
    }
    function byId(id) {
        for (var i = 0; i < rows.length; i++) if (rows[i].id === id) return rows[i];
        return null;
    }
    function inDates(b) {
        var from = $('#bkFrom').val();
        var to = $('#bkTo').val();
        if (!from && !to) return true;
        if (!b.start) return false;
        if (from && b.start < from) return false;
        if (to && b.start > to) return false;
        return true;
    }
    function filtered() {
        var q = $.trim($('#bkSearch').val() || '').toLowerCase();
        var dest = $('#bkDest').val();
        var customer = $('#bkCustomer').val();
        var supplier = $('#bkSupplier').val();
        var pay = $('#bkPay').val();
        var type = $('#bkType').val();
        return rows.filter(function (b) {
            if (!inDates(b)) return false;
            if (dest && b.destination !== dest) return false;
            if (customer && b.guest !== customer) return false;
            if (supplier && (b.suppliers || []).indexOf(supplier) === -1) return false;
            if (pay && b.customer_pay !== pay) return false;
            if (type && b.tour_type !== type) return false;
            if (!q) return true;
            var hay = [b.code, b.guest, b.destination, b.lead_uid, b.phone].join(' ').toLowerCase();
            return hay.indexOf(q) !== -1;
        });
    }
    function tabbed(list) {
        if (state.tab === 'upcoming') {
            return list.filter(function (b) { return b.phase === 'upcoming' || b.phase === 'confirmed'; });
        }
        if (state.tab === 'ongoing') {
            return list.filter(function (b) { return b.phase === 'ongoing'; });
        }
        if (state.tab === 'completed') {
            return list.filter(function (b) { return b.phase === 'completed'; });
        }
        return list;
    }
    function delta(list) {
        var now = new Date();
        var thisKey = monthKey(now);
        var prev = new Date(now.getFullYear(), now.getMonth() - 1, 1);
        var prevKey = monthKey(prev);
        var a = 0, b = 0;
        list.forEach(function (row) {
            var key = String(row.created_at || '').slice(0, 7);
            if (key === thisKey) a++;
            if (key === prevKey) b++;
        });
        if (!b) return '';
        var pct = Math.round(((a - b) / b) * 100);
        return '<small class="' + (pct < 0 ? 'is-down' : '') + '">' + (pct > 0 ? '↑ ' : pct < 0 ? '↓ ' : '') + Math.abs(pct) + '% vs last month</small>';
    }
    function renderStats() {
        var list = filtered().filter(function (b) { return b.phase !== 'cancelled'; });
        var customers = {}, suppliers = {}, upcoming = 0, recv = 0, recvN = 0, pay = 0, payN = 0;
        var today = boot.today;
        var until = addDays(today, 30);
        list.forEach(function (b) {
            if (b.guest) customers[b.guest] = 1;
            (b.suppliers || []).forEach(function (s) { if (s) suppliers[s] = 1; });
            if (b.phase === 'upcoming' && b.start && b.start <= until) upcoming++;
            if (b.customer_balance > 0) { recv += b.customer_balance; recvN++; }
            if (b.supplier_balance > 0) { pay += b.supplier_balance; payN++; }
        });
        var cards = [
            ['#e11d48', 'fa-clipboard-list', list.length, 'Total Confirmed Bookings', delta(list)],
            ['#16a34a', 'fa-user', Object.keys(customers).length, 'Total Customers', ''],
            ['#7c3aed', 'fa-truck', Object.keys(suppliers).length, 'Total Suppliers', ''],
            ['#f59e0b', 'fa-calendar-alt', upcoming, 'Upcoming Tours (Next 30 Days)', ''],
            ['#0ea5e9', 'fa-wallet', money(recv), 'Customer Receivable', recvN + ' pending'],
            ['#e11d48', 'fa-hand-holding-usd', money(pay), 'Supplier Payable', payN + ' pending']
        ];
        $('#bkStats').html(cards.map(function (c) {
            return '<div class="bk-stat"><span class="bk-ico" style="background:' + c[0] + '"><i class="fas ' + c[1] + '"></i></span><b>' + esc(c[2]) + '</b><span>' + esc(c[3]) + '</span>' + (c[4] && c[4].indexOf('<small') === 0 ? c[4] : (c[4] ? '<small>' + esc(c[4]) + '</small>' : '')) + '</div>';
        }).join(''));
    }
    function donut(parts) {
        var total = parts.reduce(function (s, p) { return s + p.value; }, 0);
        var acc = 0;
        var stops = parts.map(function (p) {
            var pct = total ? (p.value / total) * 100 : 0;
            var stop = p.color + ' ' + acc + '% ' + (acc + pct) + '%';
            acc += pct;
            return stop;
        });
        if (!total) stops = ['#e2e8f0 0 100%'];
        var head = total ? Math.round((parts[0].value / total) * 100) + '%' : '0%';
        var key = parts.map(function (p) {
            return '<div><span><i style="background:' + p.color + '"></i>' + esc(p.label) + '</span><b>' + esc(money(p.value)) + '</b></div>';
        }).join('');
        return '<div class="bk-donut" style="background:conic-gradient(' + stops.join(',') + ')"><b>' + head + '</b></div><div class="bk-key">' + key + '</div>';
    }
    function sumStatus(list, prefix) {
        var received = 0, pending = 0, overdue = 0;
        list.forEach(function (b) {
            if (b.phase === 'cancelled') return;
            var paid = b[prefix + '_paid'];
            var balance = b[prefix + '_balance'];
            var status = b[prefix + '_pay'];
            received += paid;
            if (status === 'overdue') overdue += balance;
            else pending += balance;
        });
        return [
            { label: prefix === 'customer' ? 'Received' : 'Paid', value: received, color: '#22c55e' },
            { label: 'Pending', value: pending, color: '#f59e0b' },
            { label: 'Overdue', value: overdue, color: '#ef4444' }
        ];
    }
    function renderCharts() {
        var list = filtered();
        var keys = {};
        list.forEach(function (b) {
            var k = (b.start || String(b.created_at || '').slice(0, 10)).slice(0, 7);
            if (k.length === 7) keys[k] = true;
        });
        var labels = Object.keys(keys).sort();
        if (!labels.length) {
            var y = new Date().getFullYear();
            for (var m = 1; m <= 12; m++) labels.push(y + '-' + pad(m));
        }
        if (labels.length > 12) labels = labels.slice(labels.length - 12);
        var maxCount = 1, maxRev = 1, series = labels.map(function (key) {
            var count = 0, rev = 0;
            list.forEach(function (b) {
                var k = (b.start || String(b.created_at || '').slice(0, 10)).slice(0, 7);
                if (k === key && b.phase !== 'cancelled') { count++; rev += b.total; }
            });
            maxCount = Math.max(maxCount, count);
            maxRev = Math.max(maxRev, rev);
            return { key: key, count: count, rev: rev };
        });
        $('#bkBars').html(series.map(function (s) {
            var h1 = Math.max(2, Math.round((s.count / maxCount) * 100));
            var h2 = Math.max(2, Math.round((s.rev / maxRev) * 100));
            var name = months[parseInt(s.key.slice(5), 10) - 1] || s.key;
            return '<div class="bk-bar" title="' + esc(s.count + ' bookings') + '"><em>' + (s.count || '') + '</em><span style="height:' + h1 + '%"></span><span class="is-rev" style="height:' + h2 + '%"></span><label>' + name + '</label></div>';
        }).join(''));
        var active = list.filter(function (b) { return b.phase !== 'cancelled'; });
        $('#bkCustDonut').html(donut(sumStatus(active, 'customer')));
        $('#bkSupDonut').html(donut(sumStatus(active, 'supplier')));
    }
    function payCell(paid, balance) {
        if (balance > 0.009) return esc(money(paid)) + '<div class="bk-due">' + esc(money(balance)) + ' due</div>';
        return '<span class="bk-ok">Paid</span><div class="bk-muted">' + esc(money(paid)) + '</div>';
    }
    function statusClass(b) {
        if (b.phase === 'ongoing') return 'is-progress';
        if (b.phase === 'completed') return 'is-completed';
        if (b.phase === 'cancelled') return 'is-cancelled';
        return 'is-confirmed';
    }
    function stageSelectHtml(b) {
        var stage = String(b.stage || 'confirmed');
        if (stage !== 'lost') stage = 'confirmed';
        var stages = boot.stages || {
            new_lead: 'New Lead',
            quoted: 'Quoted',
            confirmed: 'Confirmed',
            lost: 'Lost'
        };
        var opts = Object.keys(stages).map(function (key) {
            var disabled = (stage !== 'lost' && key !== 'lost') ? ' disabled' : '';
            var selected = key === stage ? ' selected' : '';
            return '<option value="' + esc(key) + '"' + selected + disabled + '>' + esc(stages[key]) + '</option>';
        }).join('');
        return '<select class="lead-stage-select js-lead-stage-select stage-' + esc(stage) + '"' +
            ' data-lead-id="' + (b.lead_id || 0) + '"' +
            ' data-booking-id="' + b.id + '"' +
            ' data-stage="' + esc(stage) + '"' +
            ' data-stage-auto="1"' +
            ' title="Stage updates automatically from quotation activity. You can mark as Lost."' +
            ' aria-label="Lead stage">' + opts + '</select>';
    }
    function rowActions(b) {
        // Same as leads.php quoted row: eye (quotation preview) + Book + more
        var editHref = 'crm/quotation_generator.php?id=' + b.id;
        return '' +
            '<div class="bk-action-btns">' +
            '<button type="button" class="btn-icon btn-view js-lead-q-preview" data-quotation-id="' + b.id + '" data-edit-href="' + esc(editHref) + '" title="Preview Quotation" aria-label="Preview Quotation"><i class="far fa-eye"></i></button>' +
            '<button type="button" class="btn-icon js-q-book btn-book btn-confirmed" data-id="' + b.id + '" data-open-confirm="1" title="Confirm Tour" aria-label="Confirm Tour">' +
            '<img src="img/booking.png" alt="" class="btn-book-img" width="16" height="16">' +
            '</button>' +
            '<div class="bk-actions-more">' +
            '<button type="button" class="btn-icon btn-more bk-more" aria-haspopup="true" aria-expanded="false" title="More actions" aria-label="More actions"><i class="fas fa-ellipsis-v"></i></button>' +
            '<div class="bk-actions-menu" role="menu">' +
            '<button type="button" class="dropdown-item" data-act="confirm" data-id="' + b.id + '"><i class="fas fa-check-circle text-muted"></i> Confirm Tour</button>' +
            '<button type="button" class="dropdown-item" data-act="preview" data-id="' + b.id + '"><i class="far fa-eye text-muted"></i> Preview Quotation</button>' +
            '<button type="button" class="dropdown-item" data-act="view" data-id="' + b.id + '"><i class="fas fa-info-circle text-muted"></i> Booking Details</button>' +
            '<button type="button" class="dropdown-item" data-act="edit" data-id="' + b.id + '"><i class="fas fa-edit text-muted"></i> Edit Quotation</button>' +
            '<button type="button" class="dropdown-item" data-act="itinerary" data-id="' + b.id + '"><i class="fas fa-route text-muted"></i> View Itinerary</button>' +
            '<button type="button" class="dropdown-item" data-act="hotels" data-id="' + b.id + '"><i class="fas fa-hotel text-muted"></i> Hotel Bookings</button>' +
            '<button type="button" class="dropdown-item" data-act="flights" data-id="' + b.id + '"><i class="fas fa-plane text-muted"></i> Flight Bookings</button>' +
            '<button type="button" class="dropdown-item" data-act="pay" data-id="' + b.id + '"><i class="fas fa-rupee-sign text-muted"></i> Record Payment</button>' +
            '<div class="dropdown-divider"></div>' +
            (b.phase === 'cancelled'
                ? '<button type="button" class="dropdown-item" data-act="restore" data-id="' + b.id + '"><i class="fas fa-undo text-muted"></i> Restore Booking</button>'
                : '<button type="button" class="dropdown-item text-danger" data-act="cancel" data-id="' + b.id + '"><i class="fas fa-trash-alt"></i> Cancel Booking</button>') +
            '</div></div></div>';
    }
    function renderTable() {
        var list = filtered();
        var counts = {
            all: list.length,
            upcoming: list.filter(function (b) { return b.phase === 'upcoming' || b.phase === 'confirmed'; }).length,
            ongoing: list.filter(function (b) { return b.phase === 'ongoing'; }).length,
            completed: list.filter(function (b) { return b.phase === 'completed'; }).length,
            customer: (boot.customer_payments || []).filter(function (p) { return !!byId(p.quotation_id) && inSet(list, p.quotation_id); }).length,
            supplier: (boot.supplier_payments || []).filter(function (p) { return inSet(list, p.quotation_id); }).length,
            timeline: list.length
        };
        $('#bkTabs button').each(function () {
            var tab = $(this).data('tab');
            var label = $(this).text().replace(/\s+\(\d+\)$/, '');
            $(this).text(label + ' (' + (counts[tab] || 0) + ')');
        });
        var ids = {};
        list.forEach(function (b) { ids[b.id] = true; });
        if (state.tab === 'customer' || state.tab === 'supplier') {
            renderLedger(state.tab, ids);
            return;
        }
        if (state.tab === 'timeline') {
            renderTimeline(tabbed(list));
            return;
        }
        var view = tabbed(list);
        var pages = Math.max(1, Math.ceil(view.length / state.size));
        if (state.page > pages) state.page = pages;
        var start = (state.page - 1) * state.size;
        var slice = view.slice(start, start + state.size);
        var html = '<div class="bk-table-wrap"><table class="bk-table"><thead><tr>' +
            '<th class="col-bk-id">Booking ID</th>' +
            '<th class="col-bk-guest">Guest</th>' +
            '<th class="col-bk-dest">Destination</th>' +
            '<th class="col-bk-date">Travel Date</th>' +
            '<th class="col-bk-svc">Services</th>' +
            '<th class="col-bk-stage">Stage</th>' +
            '<th class="col-bk-status">Status</th>' +
            '<th class="col-bk-actions">Actions</th>' +
            '</tr></thead><tbody>';
        if (!slice.length) {
            html += '<tr><td colspan="8" class="bk-empty">No confirmed tours match these filters. Tours appear here after Confirm Tour.</td></tr>';
        }
        slice.forEach(function (b) {
            var createdMeta = fmtCreatedMeta(b.created_at);
            var travel = fmtTravelShort(b.start);
            var initials = guestInitials(b.guest);
            var pax = paxBadge(b);
            var destSub = [];
            if (b.nights > 0) destSub.push(b.nights + ' N');
            if (b.tour_type) destSub.push(b.tour_type);
            html += '<tr class="' + (highlightId && b.id === highlightId ? 'is-highlight' : '') + '" data-booking="' + b.id + '">' +
                '<td class="col-bk-id">' +
                '<button type="button" class="bk-id-cell" data-act="view" data-id="' + b.id + '" title="Preview booking">' +
                '<span class="bk-id-uid">' + esc(b.code) + '</span>' +
                (createdMeta ? '<span class="bk-id-meta"> | ' + esc(createdMeta) + '</span>' : '') +
                '</button></td>' +
                '<td class="col-bk-guest"><div class="bk-guest-wrap">' +
                (initials ? '<span class="bk-guest-initials" aria-hidden="true">' + esc(initials) + '</span>' : '') +
                '<span class="bk-guest-name" title="' + esc(b.guest || '') + '">' + esc(b.guest || '—') + '</span>' +
                (pax ? '<span class="bk-pax-badge">' + esc(pax) + '</span>' : '') +
                '</div></td>' +
                '<td class="col-bk-dest"><span class="bk-dest-name">' + esc(b.destination || '—') + '</span>' +
                (destSub.length ? '<div class="bk-dest-sub">' + esc(destSub.join(' · ')) + '</div>' : '') +
                '</td>' +
                '<td class="col-bk-date">' +
                (travel
                    ? '<span class="bk-date-badge"><i class="far fa-calendar"></i> ' + esc(travel) + '</span>'
                    : '<span class="text-muted">—</span>') +
                '</td>' +
                '<td class="col-bk-svc">' + servicesHtml(b) + '</td>' +
                '<td class="col-bk-stage">' + stageSelectHtml(b) + '</td>' +
                '<td class="col-bk-status">' + payStatusPill(b.customer_pay) + '</td>' +
                '<td class="col-bk-actions">' + rowActions(b) + '</td></tr>';
        });
        html += '</tbody></table></div>';
        $('#bkBody').html(html);
        renderPager(view.length, start, slice.length);
    }
    function inSet(list, id) {
        for (var i = 0; i < list.length; i++) if (list[i].id === id) return true;
        return false;
    }
    function renderLedger(kind, ids) {
        var source = kind === 'supplier' ? (boot.supplier_payments || []) : (boot.customer_payments || []);
        var view = source.filter(function (p) { return ids[p.quotation_id]; });
        var pages = Math.max(1, Math.ceil(view.length / state.size));
        if (state.page > pages) state.page = pages;
        var start = (state.page - 1) * state.size;
        var slice = view.slice(start, start + state.size);
        var head = kind === 'supplier'
            ? '<th>Date</th><th>Supplier</th><th>Service</th><th>Booking</th><th>Customer</th><th>Amount</th><th>Method</th><th>Reference</th>'
            : '<th>Date</th><th>Customer</th><th>Booking</th><th>Amount</th><th>Method</th><th>Reference</th>';
        var html = '<div class="bk-table-wrap"><table class="bk-table"><thead><tr>' + head + '</tr></thead><tbody>';
        if (!slice.length) html += '<tr><td colspan="8" class="bk-empty">No payments recorded for these bookings.</td></tr>';
        slice.forEach(function (p) {
            if (kind === 'supplier') {
                html += '<tr><td>' + esc(fmt(p.date)) + '</td><td>' + esc(p.supplier || '—') + '</td><td>' + esc(p.label || '') + '</td><td>' + esc(p.code) + '</td><td>' + esc(p.guest) + '</td><td>' + esc(money(p.amount)) + '</td><td>' + esc(p.method) + '</td><td>' + esc(p.reference || '—') + '</td></tr>';
            } else {
                html += '<tr><td>' + esc(fmt(p.date)) + '</td><td>' + esc(p.guest) + '</td><td>' + esc(p.code) + '</td><td>' + esc(money(p.amount)) + '</td><td>' + esc(p.method) + '</td><td>' + esc(p.reference || '—') + '</td></tr>';
            }
        });
        html += '</tbody></table></div>';
        $('#bkBody').html(html);
        renderPager(view.length, start, slice.length);
    }
    function renderTimeline(view) {
        var sorted = view.slice().sort(function (a, b) { return String(a.start || '9999').localeCompare(String(b.start || '9999')); });
        var pages = Math.max(1, Math.ceil(sorted.length / state.size));
        if (state.page > pages) state.page = pages;
        var start = (state.page - 1) * state.size;
        var slice = sorted.slice(start, start + state.size);
        var html = slice.length ? slice.map(function (b) {
            return '<div class="bk-time-item"><span class="bk-dot"></span><div><b>' + esc(b.guest || b.code) + '</b><div class="bk-muted">' + esc(b.destination || 'Tour') + ' · ' + esc(b.code) + '</div></div><div><span class="bk-pill ' + statusClass(b) + '">' + esc(b.status) + '</span><div class="bk-muted">' + esc(b.start ? fmt(b.start) : 'Date not set') + '</div></div></div>';
        }).join('') : '<div class="bk-empty">No bookings in this timeline.</div>';
        $('#bkBody').html(html);
        renderPager(sorted.length, start, slice.length);
    }
    function renderPager(total, start, shown) {
        var pages = Math.max(1, Math.ceil(total / state.size));
        var buttons = '';
        for (var p = 1; p <= pages && p <= 8; p++) {
            buttons += '<button type="button" data-page="' + p + '"' + (p === state.page ? ' class="is-on"' : '') + '>' + p + '</button> ';
        }
        $('#bkPages').html(buttons);
        $('#bkCount').text(total ? ('Showing ' + (start + 1) + ' to ' + (start + shown) + ' of ' + total) : 'Showing 0');
    }
    function placeHighlight() {
        if (!highlightId || !byId(highlightId)) return;
        state.tab = 'all';
        $('#bkTabs button').removeClass('is-on');
        $('#bkTabs button[data-tab="all"]').addClass('is-on');
        var idx = 0;
        for (var i = 0; i < rows.length; i++) {
            if (rows[i].id === highlightId) { idx = i; break; }
        }
        state.page = Math.floor(idx / state.size) + 1;
    }
    function scrollHighlight() {
        if (!highlightId || highlightScrolled) return;
        var el = document.querySelector('#bkBody tr[data-booking="' + highlightId + '"]');
        if (!el) return;
        highlightScrolled = true;
        window.setTimeout(function () {
            try { el.scrollIntoView({ block: 'center' }); } catch (err) { el.scrollIntoView(); }
        }, 60);
    }
    function render() { renderStats(); renderCharts(); renderTable(); scrollHighlight(); }
    function openView(b) {
        var services = (b.services || []).map(function (s) {
            return '<div class="bk-kv"><span>' + esc(s.label) + (s.supplier ? ' · ' + esc(s.supplier) : '') + '</span><b>' + esc(money(s.paid)) + ' / ' + esc(money(s.total)) + '</b></div>';
        }).join('') || '<div class="bk-empty">No supplier services on this tour.</div>';
        $('#bkView .modal-title').text(b.code);
        $('#bkViewBody').html(
            '<div class="bk-kv"><span>Customer</span><b>' + esc(b.guest || '—') + '</b></div>' +
            '<div class="bk-kv"><span>Lead</span><b>' + esc(b.lead_uid || '—') + '</b></div>' +
            '<div class="bk-kv"><span>Phone</span><b>' + esc(b.phone || '—') + '</b></div>' +
            '<div class="bk-kv"><span>Destination</span><b>' + esc(b.destination || '—') + '</b></div>' +
            '<div class="bk-kv"><span>Travel</span><b>' + esc(b.start ? fmt(b.start) + (b.end ? ' – ' + fmt(b.end) : '') : '—') + '</b></div>' +
            '<div class="bk-kv"><span>Passengers</span><b>' + esc(b.pax) + ' (' + b.adults + 'A ' + b.children + 'C ' + b.infants + 'I)</b></div>' +
            '<div class="bk-kv"><span>Tour type</span><b>' + esc(b.tour_type) + '</b></div>' +
            '<div class="bk-kv"><span>Total value</span><b>' + esc(money(b.total)) + '</b></div>' +
            '<div class="bk-kv"><span>Customer balance</span><b>' + esc(money(b.customer_balance)) + '</b></div>' +
            '<div class="bk-kv"><span>Supplier balance</span><b>' + esc(money(b.supplier_balance)) + '</b></div>' +
            '<h6 class="mt-3">Supplier services</h6>' + services +
            '<div class="mt-3"><a class="btn btn-outline-danger btn-sm" href="crm/quotation_generator.php?id=' + b.id + '">Itinerary</a> ' +
            '<a class="btn btn-outline-secondary btn-sm" href="crm/hotel_bookings.php?q=' + encodeURIComponent(b.code) + '">Hotels (' + b.hotel_count + ')</a> ' +
            '<button type="button" class="btn btn-danger btn-sm" data-act="pay" data-id="' + b.id + '">Record payment</button></div>'
        );
        $('#bkView').modal('show');
    }
    function openPay(b) {
        var options = '<option value="customer">Customer — balance ' + esc(money(b.customer_balance)) + '</option>';
        (b.services || []).forEach(function (s) {
            if (!s.uid) return;
            options += '<option value="' + esc(s.uid) + '" data-key="' + esc(s.key) + '" data-balance="' + s.balance + '">' + esc(s.label) + (s.supplier ? ' · ' + esc(s.supplier) : '') + ' — ' + esc(money(s.balance)) + '</option>';
        });
        $('#bkPayId').val(b.id);
        $('#bkPayTarget').html(options);
        $('#bkPayAmount').val('');
        $('#bkPayRef').val('');
        $('#bkPayDate').val(boot.today);
        var methods = (boot.methods || []).map(function (m) { return '<option>' + esc(m) + '</option>'; }).join('');
        $('#bkPayMethod').html(methods);
        $('#bkPay').modal('show');
    }
    function postStatus(action, id) {
        $.post('crm/ajax/confirmed_bookings.php', { action: action, quotation_id: id }, function (res) {
            if (!res || !res.success) { alert((res && res.message) || 'Could not update.'); return; }
            window.location.reload();
        }, 'json').fail(function () { alert('Could not update.'); });
    }
    $(document).on('click', '[data-act]', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var id = parseInt($(this).data('id'), 10);
        var b = byId(id);
        var act = $(this).data('act');
        closeBkActionsMenus();
        if (!b) return;
        if (act === 'confirm') {
            if (window.CrmConfirmTour && typeof window.CrmConfirmTour.open === 'function') {
                window.CrmConfirmTour.open(b.id, $(this).closest('tr'));
            } else {
                var $book = $(this).closest('tr').find('.js-q-book').first();
                if ($book.length) $book.trigger('click');
            }
            return;
        }
        if (act === 'preview') {
            if (window.CrmQuotationPreview && typeof window.CrmQuotationPreview.open === 'function') {
                window.CrmQuotationPreview.open(b.id, 'crm/quotation_generator.php?id=' + b.id);
            } else {
                $(this).closest('tr').find('.js-lead-q-preview').first().trigger('click');
            }
            return;
        }
        if (act === 'view') openView(b);
        if (act === 'pay') openPay(b);
        if (act === 'edit' || act === 'itinerary' || act === 'flights') window.location.href = 'crm/quotation_generator.php?id=' + b.id;
        if (act === 'hotels') window.location.href = 'crm/hotel_bookings.php?q=' + encodeURIComponent(b.code);
        if (act === 'cancel' && window.confirm('Mark this booking cancelled? The quotation, hotels, and payments stay as they are.')) postStatus('cancel', b.id);
        if (act === 'restore' && window.confirm('Restore this booking to the active list?')) postStatus('restore', b.id);
    });
    $(document).on('change', '.js-lead-stage-select', function () {
        var $sel = $(this);
        var leadId = Number($sel.attr('data-lead-id') || 0);
        var bookingId = Number($sel.attr('data-booking-id') || 0);
        var stage = String($sel.val() || '');
        var previousStage = String($sel.attr('data-stage') || '');
        if (!leadId || !stage || stage === previousStage) {
            return;
        }
        $sel.prop('disabled', true);
        $.post('crm/ajax/update_lead_stage.php', { lead_id: leadId, stage: stage }, function (res) {
            if (res && res.success) {
                var nextStage = String(res.stage || stage);
                $sel.attr('data-stage', nextStage);
                $sel.removeClass('stage-new_lead stage-quoted stage-confirmed stage-lost');
                $sel.addClass('stage-' + nextStage);
                var isAuto = res.stage_is_auto !== false && nextStage !== 'lost';
                $sel.attr('data-stage-auto', isAuto ? '1' : '0');
                $sel.find('option').each(function () {
                    var val = String($(this).val() || '');
                    $(this).prop('disabled', nextStage !== 'lost' && val !== 'lost');
                });
                var row = byId(bookingId);
                if (row) row.stage = nextStage;
            } else {
                window.alert((res && res.message) ? res.message : 'Could not update stage.');
                $sel.val(previousStage);
            }
        }, 'json').fail(function () {
            window.alert('Could not update stage. Please try again.');
            $sel.val(previousStage);
        }).always(function () {
            $sel.prop('disabled', false);
        });
    });
    function closeBkActionsMenus() {
        $('.bk-actions-menu').removeClass('is-open').css({ top: '', left: '' });
        $('.bk-more').attr('aria-expanded', 'false');
        $('#bkBody tr').removeClass('is-actions-dropdown-open');
    }
    $(document).on('click', '.bk-more', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var $btn = $(this);
        var $menu = $btn.siblings('.bk-actions-menu');
        var open = $menu.hasClass('is-open');
        closeBkActionsMenus();
        if (open) {
            return;
        }
        var rect = this.getBoundingClientRect();
        var width = 190;
        var left = Math.min(Math.max(8, rect.right - width), window.innerWidth - width - 8);
        var top = rect.bottom + 4;
        $menu.css({ top: top + 'px', left: left + 'px' }).addClass('is-open');
        $btn.attr('aria-expanded', 'true');
        $btn.closest('tr').addClass('is-actions-dropdown-open');
    });
    $('#bkPayForm').on('submit', function (e) {
        e.preventDefault();
        var id = parseInt($('#bkPayId').val(), 10);
        var b = byId(id);
        var target = $('#bkPayTarget').val();
        var amount = parseFloat(String($('#bkPayAmount').val() || '').replace(/,/g, ''));
        if (!b || !(amount > 0)) { alert('Enter a payment amount greater than 0.'); return; }
        var data = { action: 'add', quotation_id: id, amount: amount, payment_date: $('#bkPayDate').val(), method: $('#bkPayMethod').val(), reference: $('#bkPayRef').val() };
        var url = 'crm/ajax/customer_payments.php';
        if (target !== 'customer') {
            var $opt = $('#bkPayTarget option:selected');
            var balance = parseFloat($opt.data('balance') || 0);
            if (amount - balance > 0.009) { alert('Amount is higher than the supplier balance.'); return; }
            url = 'crm/ajax/service_payments.php';
            data.service_uid = target;
            data.service_key = $opt.data('key') || '';
        } else if (b.total > 0 && amount - b.customer_balance > 0.009) {
            alert('Amount is higher than the customer balance.');
            return;
        }
        $.post(url, data, function (res) {
            if (!res || !res.success) { alert((res && res.message) || 'Could not save payment.'); return; }
            window.location.reload();
        }, 'json').fail(function () { alert('Could not save payment.'); });
    });
    $('#bkSearch, #bkFrom, #bkTo, #bkDest, #bkCustomer, #bkSupplier, #bkPay, #bkType').on('input change', function () { state.page = 1; render(); });
    $('#bkReset').on('click', function () {
        $('#bkSearch, #bkFrom, #bkTo').val('');
        $('#bkDest, #bkCustomer, #bkSupplier, #bkPay, #bkType').val('');
        state.page = 1;
        render();
    });
    $('#bkTabs').on('click', 'button', function () {
        state.tab = $(this).data('tab');
        state.page = 1;
        $('#bkTabs button').removeClass('is-on');
        $(this).addClass('is-on');
        renderTable();
    });
    $('#bkSize').on('change', function () { state.size = parseInt(this.value, 10) || 10; state.page = 1; renderTable(); });
    $(document).on('click', '#bkPages [data-page]', function () { state.page = parseInt($(this).data('page'), 10) || 1; renderTable(); });
    $(document).on('click', function () { closeBkActionsMenus(); });
    $(document).on('keydown', function (e) {
        if (e.key === 'Escape') {
            closeBkActionsMenus();
        }
    });
    $('#bkExport').on('click', function () {
        var list = state.tab === 'all' || state.tab === 'upcoming' || state.tab === 'ongoing' || state.tab === 'completed' || state.tab === 'timeline' ? tabbed(filtered()) : filtered();
        var head = ['Booking ID', 'Lead', 'Customer', 'Phone', 'Destination', 'Travel start', 'Travel end', 'Pax', 'Tour type', 'Total', 'Customer paid', 'Customer balance', 'Supplier paid', 'Supplier balance', 'Status'];
        var body = list.map(function (b) {
            return [b.code, b.lead_uid, b.guest, b.phone, b.destination, b.start, b.end, b.pax, b.tour_type, b.total, b.customer_paid, b.customer_balance, b.supplier_paid, b.supplier_balance, b.status];
        });
        var csv = [head].concat(body).map(function (line) {
            return line.map(function (cell) { return '"' + String(cell == null ? '' : cell).replace(/"/g, '""') + '"'; }).join(',');
        }).join('\n');
        var blob = new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8' });
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url; a.download = 'confirmed-bookings.csv';
        document.body.appendChild(a); a.click(); a.remove();
        URL.revokeObjectURL(url);
    });
    placeHighlight();
    render();
})(jQuery);
</script>
<script src="crm/assets/quotation_confirm_tour.js?v=48"></script>
<script src="crm/assets/quotation_preview_modal.js?v=1"></script>
<script>
(function ($) {
    // Keep shared modals on body so Bootstrap shows them above AdminLTE chrome / iframe layout.
    $(function () {
        $('#confirmTourModal, #askConfirmTourPopup, #tourConfirmedPopup, #ctTravellerModal, #ctPaxDocsModal, #leadQuotationPreviewModal')
            .appendTo('body');
    });
})(jQuery);
</script>
</body>
</html>
