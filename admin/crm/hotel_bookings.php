<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/includes/hotel_bookings.php';

$bookings = hbList($conn);
$destinations = [];
$suppliers = [];
foreach ($bookings as $row) {
    if ($row['city'] !== '' && !in_array($row['city'], $destinations, true)) {
        $destinations[] = $row['city'];
    } elseif ($row['destination'] !== '' && !in_array($row['destination'], $destinations, true)) {
        $destinations[] = $row['destination'];
    }
    if ($row['supplier'] !== '' && !in_array($row['supplier'], $suppliers, true)) {
        $suppliers[] = $row['supplier'];
    }
}
sort($destinations);
sort($suppliers);
$boot = json_encode([
    'bookings' => $bookings,
    'methods' => spPaymentMethods(),
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <base href="../">
    <title>Hotel Bookings</title>
    <?php include __DIR__ . '/../includes/header-links.php'; ?>
    <style>
        .hb-page { background: #f4f7fb; }
        .hb-page .content-wrapper, .hb-page .content { background: #f4f7fb; }
        .hb-page .content-header { display: none; }
        .hb-page .content { padding: 1.1rem 1.1rem 1.4rem; }
        .hb-page button, .hb-page input, .hb-page select { font-family: inherit; }
        .hb-head { display: flex; justify-content: space-between; gap: 1rem; align-items: flex-start; margin-bottom: 0.9rem; flex-wrap: wrap; }
        .hb-title { margin: 0; font-size: 1.45rem; font-weight: 800; color: #0f172a; }
        .hb-sub { margin: 0.2rem 0 0; color: #94a3b8; font-size: 0.88rem; max-width: 640px; }
        .hb-actions { display: flex; gap: 0.45rem; flex-wrap: wrap; }
        .hb-btn { height: 36px; border-radius: 10px; border: 1px solid #e6ebf2; background: #fff; color: #334155; font-weight: 700; font-size: 0.8rem; padding: 0 0.75rem; display: inline-flex; align-items: center; gap: 0.35rem; }
        .hb-btn-red { background: #e11d48; border-color: #e11d48; color: #fff; }
        .hb-layout { display: grid; grid-template-columns: minmax(0, 1fr) 340px; gap: 0.85rem; align-items: start; }
        .hb-card { background: #fff; border-radius: 16px; box-shadow: 0 8px 24px rgba(15, 23, 42, 0.04); padding: 0.85rem; }
        .hb-stats { display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap: 0.55rem; margin-bottom: 0.75rem; }
        .hb-stat { border: 0; border-radius: 14px; text-align: left; padding: 0.7rem 0.75rem; min-height: 78px; cursor: pointer; }
        .hb-stat b { display: block; font-size: 1.25rem; line-height: 1.1; }
        .hb-stat span { font-size: 0.75rem; font-weight: 800; color: #334155; }
        .hb-stat small { display: block; color: #64748b; font-size: 0.68rem; }
        .hb-stat.is-total { background: #eef6ff; }
        .hb-stat.is-ok { background: #ecfdf3; }
        .hb-stat.is-wait { background: #fff7ed; }
        .hb-stat.is-due { background: #fff1f2; }
        .hb-stat.is-re { background: #f5f3ff; }
        .hb-stat.is-off { background: #f8fafc; }
        .hb-filters { display: flex; flex-wrap: wrap; gap: 0.45rem; margin-bottom: 0.7rem; }
        .hb-search, .hb-select, .hb-dates { height: 36px; border: 1px solid #e6ebf2; border-radius: 10px; background: #fff; color: #334155; font-size: 0.8rem; }
        .hb-search { display: flex; align-items: center; gap: 0.35rem; padding: 0 0.65rem; min-width: 220px; flex: 1; }
        .hb-search input { border: 0; outline: none; width: 100%; }
        .hb-select { padding: 0 0.55rem; }
        .hb-dates { display: inline-flex; align-items: center; gap: 0.25rem; padding: 0 0.45rem; }
        .hb-dates input { border: 0; outline: none; width: 118px; }
        .hb-reset { border: 0; background: transparent; color: #e11d48; font-weight: 800; }
        .hb-table-wrap { overflow: auto; }
        table.hb-table { width: 100%; border-collapse: collapse; font-size: 0.78rem; min-width: 1180px; }
        .hb-table th { text-align: left; color: #94a3b8; font-size: 0.68rem; letter-spacing: 0.03em; text-transform: uppercase; padding: 0.45rem 0.4rem; border-bottom: 1px solid #f1f5f9; white-space: nowrap; }
        .hb-table td { padding: 0.55rem 0.4rem; border-bottom: 1px solid #f8fafc; vertical-align: middle; color: #334155; }
        .hb-table tr.is-on { background: #fff1f2; }
        .hb-table tr { cursor: pointer; }
        .hb-hotel { display: flex; gap: 0.45rem; align-items: center; }
        .hb-photo { width: 42px; height: 36px; border-radius: 8px; background: linear-gradient(135deg, #fecdd3, #ffe4e6); color: #e11d48; display: inline-flex; align-items: center; justify-content: center; flex: 0 0 auto; }
        .hb-hotel b, .hb-guest b, .hb-code b { display: block; color: #0f172a; }
        .hb-muted { color: #94a3b8; font-size: 0.72rem; }
        .hb-stars { color: #f59e0b; letter-spacing: 1px; font-size: 0.68rem; }
        .hb-pill { display: inline-flex; align-items: center; border-radius: 999px; padding: 0.12rem 0.45rem; font-weight: 800; font-size: 0.7rem; white-space: nowrap; }
        .hb-pill.is-paid, .hb-pill.is-confirmed { background: #dcfce7; color: #15803d; }
        .hb-pill.is-partial, .hb-pill.is-due { background: #ffedd5; color: #c2410c; }
        .hb-pill.is-pending { background: #fef9c3; color: #a16207; }
        .hb-pill.is-cancelled { background: #f1f5f9; color: #64748b; }
        .hb-row-acts { display: flex; gap: 0.25rem; }
        .hb-ico { width: 28px; height: 28px; border-radius: 8px; border: 1px solid #e6ebf2; background: #fff; color: #475569; }
        .hb-foot { display: flex; justify-content: space-between; align-items: center; gap: 0.6rem; margin-top: 0.7rem; flex-wrap: wrap; }
        .hb-pages button, .hb-mini { height: 30px; min-width: 30px; border-radius: 8px; border: 1px solid #e6ebf2; background: #fff; font-weight: 700; }
        .hb-pages button.is-active { background: #e11d48; color: #fff; border-color: #e11d48; }
        .hb-side { background: #fff; border-radius: 16px; box-shadow: 0 8px 24px rgba(15, 23, 42, 0.05); position: sticky; top: 0.6rem; }
        .hb-side-head { padding: 0.85rem 0.9rem 0.4rem; display: flex; justify-content: space-between; gap: 0.5rem; }
        .hb-side-head h3 { margin: 0; font-size: 0.95rem; font-weight: 800; }
        .hb-tabs { display: flex; gap: 0.15rem; padding: 0 0.7rem; border-bottom: 1px solid #f1f5f9; }
        .hb-tabs button { border: 0; background: transparent; color: #64748b; font-weight: 800; font-size: 0.75rem; padding: 0.45rem 0.4rem; border-bottom: 2px solid transparent; }
        .hb-tabs button.is-on { color: #e11d48; border-bottom-color: #e11d48; }
        .hb-side-body { padding: 0.75rem 0.9rem 0.9rem; max-height: calc(100vh - 180px); overflow: auto; }
        .hb-kv { display: flex; justify-content: space-between; gap: 0.6rem; padding: 0.28rem 0; font-size: 0.78rem; }
        .hb-kv span { color: #94a3b8; }
        .hb-kv b { text-align: right; color: #0f172a; font-weight: 700; }
        .hb-block { margin-top: 0.8rem; }
        .hb-block h4 { margin: 0 0 0.35rem; font-size: 0.8rem; font-weight: 800; }
        .hb-quick { display: grid; grid-template-columns: 1fr 1fr; gap: 0.4rem; }
        .hb-quick button { border: 1px solid #fee2e2; background: #fff; color: #be123c; border-radius: 10px; min-height: 36px; font-size: 0.72rem; font-weight: 800; text-align: left; padding: 0.35rem 0.5rem; }
        .hb-quick button.solid { background: #e11d48; color: #fff; border-color: #e11d48; }
        .hb-empty { color: #94a3b8; padding: 1.2rem 0.2rem; font-size: 0.82rem; }
        .hb-menu { position: relative; }
        .hb-pop { display: none; position: absolute; right: 0; top: 38px; background: #fff; border: 1px solid #e6ebf2; border-radius: 10px; min-width: 160px; z-index: 5; box-shadow: 0 10px 24px rgba(15,23,42,.08); }
        .hb-pop.is-open { display: block; }
        .hb-pop button { display: block; width: 100%; text-align: left; border: 0; background: #fff; padding: 0.45rem 0.7rem; font-size: 0.8rem; }
        .hb-pop button:hover { background: #fff1f2; }
        #hbModal .modal-header { background: #e11d48; color: #fff; }
        #hbModal .modal-header .close { color: #fff; opacity: 1; text-shadow: none; }
        @media (max-width: 1199.98px) {
            .hb-layout, .hb-stats { grid-template-columns: 1fr; }
            .hb-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .hb-side { position: static; }
        }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed hb-page">
<div class="wrapper">
    <?php include __DIR__ . '/../includes/top-header.php'; ?>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="content-wrapper">
        <?php include __DIR__ . '/../includes/page-header.php'; ?>
        <section class="content">
            <div class="container-fluid">
                <div class="hb-head">
                    <div>
                        <h1 class="hb-title">Hotel Bookings</h1>
                        <p class="hb-sub">View and manage all hotel bookings from confirmed tours. Track confirmations, supplier payments, reconfirmations and vouchers.</p>
                    </div>
                    <div class="hb-actions">
                        <button type="button" class="hb-btn" id="hbSendRe"><i class="far fa-paper-plane"></i> Send Reconfirmation</button>
                        <button type="button" class="hb-btn" id="hbVoucher"><i class="far fa-file-alt"></i> Generate Voucher</button>
                        <div class="hb-menu">
                            <button type="button" class="hb-btn" id="hbExportBtn"><i class="fas fa-download"></i> Export</button>
                            <div class="hb-pop" id="hbExportPop">
                                <button type="button" id="hbCsv">Export CSV</button>
                                <button type="button" id="hbExcel">Export Excel</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="hb-layout">
                    <div class="hb-card">
                        <div class="hb-stats" id="hbStats"></div>
                        <div class="hb-filters">
                            <label class="hb-search"><i class="fas fa-search"></i><input type="search" id="hbSearch" placeholder="Search bookings..."></label>
                            <label class="hb-dates"><i class="far fa-calendar-alt"></i><input type="date" id="hbFrom" aria-label="Travel from"><input type="date" id="hbTo" aria-label="Travel to"></label>
                            <select class="hb-select" id="hbDest"><option value="">Destination</option><?php foreach ($destinations as $name) { ?><option><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></option><?php } ?></select>
                            <select class="hb-select" id="hbSupplier"><option value="">Supplier</option><?php foreach ($suppliers as $name) { ?><option><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></option><?php } ?></select>
                            <select class="hb-select" id="hbPay">
                                <option value="">Payment Status</option>
                                <option value="paid">Paid</option>
                                <option value="partial">Partially Paid</option>
                                <option value="pending">Pending</option>
                            </select>
                            <select class="hb-select" id="hbRe">
                                <option value="">Reconfirmation</option>
                                <option value="confirmed">Confirmed</option>
                                <option value="due">Due / Overdue</option>
                                <option value="pending">Pending</option>
                            </select>
                            <select class="hb-select" id="hbBook">
                                <option value="">Booking Status</option>
                                <option value="confirmed">Confirmed</option>
                                <option value="pending">Pending Confirmation</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                            <button type="button" class="hb-reset" id="hbReset">Reset</button>
                        </div>
                        <div class="hb-table-wrap">
                            <table class="hb-table">
                                <thead>
                                    <tr>
                                        <th><input type="checkbox" id="hbAll" aria-label="Select all"></th>
                                        <th>Booking ID</th>
                                        <th>Lead ID / Tour</th>
                                        <th>Guest Name</th>
                                        <th>Hotel &amp; City</th>
                                        <th>Check In - Check Out</th>
                                        <th>Nights</th>
                                        <th>Rooms</th>
                                        <th>Total Cost</th>
                                        <th>Paid to Supplier</th>
                                        <th>Payment Status</th>
                                        <th>Reconf. Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="hbRows"></tbody>
                            </table>
                        </div>
                        <div class="hb-foot">
                            <div>
                                <select class="hb-select" id="hbBulk">
                                    <option value="">Bulk Actions</option>
                                    <option value="reconfirm">Mark reconfirmed</option>
                                    <option value="cancel">Cancel bookings</option>
                                </select>
                                <button type="button" class="hb-btn" id="hbBulkApply">Apply</button>
                            </div>
                            <div class="hb-muted" id="hbRange"></div>
                            <div style="display:flex;gap:.4rem;align-items:center">
                                <div class="hb-pages" id="hbPages"></div>
                                <select class="hb-mini" id="hbSize">
                                    <option value="10">10 / page</option>
                                    <option value="25">25 / page</option>
                                    <option value="50">50 / page</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <aside class="hb-side" id="hbSide"></aside>
                </div>
            </div>
        </section>
    </div>
</div>

<div class="modal fade" id="hbModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <form class="modal-content" id="hbForm">
            <div class="modal-header">
                <h5 class="modal-title" id="hbModalTitle">Edit booking</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span>&times;</span></button>
            </div>
            <div class="modal-body" id="hbModalBody"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger" id="hbModalSave">Save</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer-links.php'; ?>
<script>
(function ($) {
    var boot = <?= $boot ?: '{}' ?>;
    var rows = boot.bookings || [];
    var state = { page: 1, size: 10, selected: '', tab: 'details', checked: {}, card: '' };
    var modalMode = '';

    function esc(s) { return $('<div>').text(s == null ? '' : String(s)).html(); }
    function money(n) {
        n = Math.round((parseFloat(n) || 0) * 100) / 100;
        return '₹' + n.toLocaleString('en-IN', { maximumFractionDigits: n % 1 ? 2 : 0 });
    }
    function fmt(iso) {
        if (!iso) return '—';
        var d = new Date(iso + (iso.length === 10 ? 'T00:00:00' : ''));
        if (isNaN(d.getTime())) return iso;
        var m = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'][d.getMonth()];
        return d.getDate() + ' ' + m + ' ' + d.getFullYear();
    }
    function stay(b) {
        if (!b.checkin && !b.checkout) return '—';
        return fmt(b.checkin) + ' - ' + fmt(b.checkout);
    }
    function pax(b) {
        var n = (b.adults || 0) + (b.children || 0) + (b.infants || 0);
        var bits = [];
        if (b.adults) bits.push(b.adults + 'A');
        if (b.children) bits.push(b.children + 'C');
        if (b.infants) bits.push(b.infants + 'I');
        if (!n) return '';
        return n + ' Pax' + (bits.length ? ' (' + bits.join(' + ') + ')' : '');
    }
    function stars(n) { return n ? '★★★★★'.slice(0, n) : ''; }
    function payLabel(k) { return { paid: 'Paid', partial: 'Partially Paid', pending: 'Pending' }[k] || k; }
    function bookLabel(k) { return { confirmed: 'Confirmed', pending: 'Pending', cancelled: 'Cancelled' }[k] || k; }
    function current() { return rows.filter(function (b) { return b.code === state.selected; })[0] || null; }
    function inCard(b) {
        if (!state.card || state.card === 'all') return true;
        if (state.card === 'confirmed') return b.booking_status === 'confirmed';
        if (state.card === 'pending') return b.booking_status === 'pending';
        if (state.card === 'payment') return b.booking_status !== 'cancelled' && b.payment_status !== 'paid';
        if (state.card === 'reconfirm') return b.booking_status !== 'cancelled' && b.reconfirm_status === 'due';
        if (state.card === 'cancelled') return b.booking_status === 'cancelled';
        return true;
    }
    function filtered() {
        var q = $.trim($('#hbSearch').val() || '').toLowerCase();
        var from = $('#hbFrom').val();
        var to = $('#hbTo').val();
        var dest = $('#hbDest').val();
        var supplier = $('#hbSupplier').val();
        var pay = $('#hbPay').val();
        var re = $('#hbRe').val();
        var book = $('#hbBook').val();
        return rows.filter(function (b) {
            if (!inCard(b)) return false;
            if (dest && b.city !== dest && b.destination !== dest) return false;
            if (supplier && b.supplier !== supplier) return false;
            if (pay && b.payment_status !== pay) return false;
            if (re && b.reconfirm_status !== re) return false;
            if (book && b.booking_status !== book) return false;
            if (from && b.checkin && b.checkin < from) return false;
            if (to && b.checkin && b.checkin > to) return false;
            if (!q) return true;
            var blob = [b.code, b.guest, b.hotel, b.city, b.destination, b.lead_uid, b.quotation_uid, b.supplier, b.phone].join(' ').toLowerCase();
            return blob.indexOf(q) !== -1;
        });
    }
    function counts() {
        return {
            all: rows.length,
            confirmed: rows.filter(function (b) { return b.booking_status === 'confirmed'; }).length,
            pending: rows.filter(function (b) { return b.booking_status === 'pending'; }).length,
            payment: rows.filter(function (b) { return b.booking_status !== 'cancelled' && b.payment_status !== 'paid'; }).length,
            reconfirm: rows.filter(function (b) { return b.booking_status !== 'cancelled' && b.reconfirm_status === 'due'; }).length,
            cancelled: rows.filter(function (b) { return b.booking_status === 'cancelled'; }).length
        };
    }
    function pct(n) { return rows.length ? Math.round(n / rows.length * 100) : 0; }
    function renderStats() {
        var c = counts();
        var cards = [
            ['all', 'is-total', c.all, 'Total Bookings', ''],
            ['confirmed', 'is-ok', c.confirmed, 'Confirmed', pct(c.confirmed) + '%'],
            ['pending', 'is-wait', c.pending, 'Pending Confirmation', pct(c.pending) + '%'],
            ['payment', 'is-due', c.payment, 'Payment Due', pct(c.payment) + '%'],
            ['reconfirm', 'is-re', c.reconfirm, 'Reconfirmation Due', pct(c.reconfirm) + '%'],
            ['cancelled', 'is-off', c.cancelled, 'Cancelled', pct(c.cancelled) + '%']
        ];
        $('#hbStats').html(cards.map(function (card) {
            return '<button type="button" class="hb-stat ' + card[1] + '" data-card="' + card[0] + '"><b>' + card[2] + '</b><span>' + card[3] + '</span><small>' + card[4] + '</small></button>';
        }).join(''));
    }
    function renderRows() {
        var list = filtered();
        var pages = Math.max(1, Math.ceil(list.length / state.size));
        if (state.page > pages) state.page = pages;
        var start = (state.page - 1) * state.size;
        var slice = list.slice(start, start + state.size);
        if (!slice.length) {
            $('#hbRows').html('<tr><td colspan="13" class="hb-empty">No hotel bookings match these filters. Hotels from confirmed tours appear here automatically.</td></tr>');
        } else {
            $('#hbRows').html(slice.map(function (b) {
                return '<tr data-code="' + esc(b.code) + '"' + (state.selected === b.code ? ' class="is-on"' : '') + '>' +
                    '<td><input type="checkbox" class="hb-check" data-code="' + esc(b.code) + '"' + (state.checked[b.code] ? ' checked' : '') + '></td>' +
                    '<td><b>' + esc(b.code) + '</b><div class="hb-muted">' + esc(fmt(b.checkin)) + '</div></td>' +
                    '<td><b>' + esc(b.lead_uid || '—') + '</b><div class="hb-muted">' + esc(b.tour || '') + '</div></td>' +
                    '<td class="hb-guest"><b>' + esc(b.guest || '—') + '</b><div class="hb-muted">' + esc(pax(b)) + '</div></td>' +
                    '<td><div class="hb-hotel"><span class="hb-photo"><i class="fas fa-hotel"></i></span><div><b>' + esc(b.hotel) + '</b><div class="hb-muted">' + esc(b.city || '—') + '</div><div class="hb-stars">' + stars(b.stars) + '</div></div></div></td>' +
                    '<td>' + esc(stay(b)) + '</td>' +
                    '<td>' + (b.nights || 0) + '</td>' +
                    '<td>' + (b.rooms || 0) + '</td>' +
                    '<td>' + money(b.total) + '</td>' +
                    '<td>' + money(b.paid) + '</td>' +
                    '<td><span class="hb-pill is-' + esc(b.payment_status) + '">' + esc(payLabel(b.payment_status)) + '</span></td>' +
                    '<td><span class="hb-pill is-' + esc(b.reconfirm_status) + '">' + esc(b.reconfirm_label) + '</span></td>' +
                    '<td><div class="hb-row-acts">' +
                    '<button type="button" class="hb-ico" data-act="voucher" data-code="' + esc(b.code) + '" title="Voucher"><i class="far fa-file-alt"></i></button>' +
                    '<button type="button" class="hb-ico" data-act="view" data-code="' + esc(b.code) + '" title="View"><i class="far fa-eye"></i></button>' +
                    '<button type="button" class="hb-ico" data-act="mail" data-code="' + esc(b.code) + '" title="Email supplier"><i class="far fa-envelope"></i></button>' +
                    '<button type="button" class="hb-ico" data-act="more" data-code="' + esc(b.code) + '" title="More"><i class="fas fa-ellipsis-v"></i></button>' +
                    '</div></td></tr>';
            }).join(''));
        }
        var fromN = list.length ? start + 1 : 0;
        $('#hbRange').text('Showing ' + fromN + ' to ' + Math.min(list.length, start + state.size) + ' of ' + list.length + ' bookings');
        var html = '<button type="button" data-page="' + Math.max(1, state.page - 1) + '">&lt;</button>';
        for (var p = 1; p <= pages && p <= 8; p++) html += '<button type="button" data-page="' + p + '"' + (p === state.page ? ' class="is-active"' : '') + '>' + p + '</button>';
        html += '<button type="button" data-page="' + Math.min(pages, state.page + 1) + '">&gt;</button>';
        $('#hbPages').html(html);
        $('#hbAll').prop('checked', slice.length > 0 && slice.every(function (b) { return state.checked[b.code]; }));
    }
    function schedule(b) {
        var lines = (b.payments || []).map(function (p) {
            return '<div class="hb-kv"><span>' + esc(fmt(p.payment_date)) + ' · ' + esc(p.method || 'Payment') + '</span><b>' + money(p.amount) + ' <em class="hb-pill is-paid">Paid</em></b></div>';
        });
        if (b.balance > 0.009 && b.booking_status !== 'cancelled') {
            lines.push('<div class="hb-kv"><span>' + esc(b.checkin ? fmt(b.checkin) : 'Outstanding') + ' · Balance</span><b>' + money(b.balance) + ' <em class="hb-pill is-pending">Pending</em></b></div>');
        }
        if (!lines.length) lines.push('<div class="hb-empty">No supplier payment is due.</div>');
        return lines.join('');
    }
    function renderSide() {
        var b = current();
        if (!b) {
            $('#hbSide').html('<div class="hb-side-body"><div class="hb-empty">Select a hotel booking to see guest details, the supplier balance, and reconfirmation.</div></div>');
            return;
        }
        var guests = pax(b) || '—';
        var where = [b.city, b.country].filter(Boolean).join(', ') || '—';
        var tab = state.tab;
        var body = '';
        if (tab === 'payments') {
            body = '<h4>Payment Schedule (To Hotel Supplier)</h4>' + schedule(b) +
                '<div class="hb-kv"><span>Total</span><b>' + money(b.total) + '</b></div>' +
                '<div class="hb-kv"><span>Paid</span><b>' + money(b.paid) + '</b></div>' +
                '<div class="hb-kv"><span>Outstanding</span><b>' + money(b.balance) + '</b></div>' +
                '<button type="button" class="hb-btn hb-btn-red" data-open="payment" style="margin-top:.6rem">Record payment</button>';
        } else if (tab === 'reconfirm') {
            body = '<div class="hb-kv"><span>Status</span><b>' + esc(b.reconfirm_label) + '</b></div>' +
                '<div class="hb-kv"><span>Due</span><b>' + esc(fmt(b.reconfirm_due)) + '</b></div>' +
                '<div class="hb-kv"><span>Reconfirmed</span><b>' + esc(b.reconfirmed_at ? fmt(b.reconfirmed_at.slice(0, 10)) : '—') + '</b></div>' +
                '<div class="hb-kv"><span>Reminder</span><b>' + esc(b.reminder_at ? b.reminder_at.replace('T', ' ') : '—') + '</b></div>' +
                '<div class="hb-quick" style="margin-top:.7rem">' +
                '<button type="button" class="solid" data-open="reconfirm">Mark reconfirmed</button>' +
                '<button type="button" data-open="reminder">Schedule reminder</button></div>';
        } else if (tab === 'vouchers') {
            var files = (b.vouchers || []).map(function (f) {
                return '<div class="hb-kv"><a href="' + esc(f.url) + '" target="_blank" rel="noopener">' + esc(f.file_name) + '</a></div>';
            }).join('') || '<div class="hb-empty">No voucher file uploaded yet.</div>';
            body = files + '<button type="button" class="hb-btn" id="hbUploadBtn" style="margin-top:.5rem">Upload attachment</button><input type="file" id="hbUpload" hidden accept=".pdf,.jpg,.jpeg,.png,.webp">';
        } else {
            body = '<div class="hb-block"><h4>Hotel Information</h4>' +
                '<div class="hb-kv"><span>Hotel</span><b>' + esc(b.hotel) + '</b></div>' +
                '<div class="hb-kv"><span>City</span><b>' + esc(where) + '</b></div>' +
                '<div class="hb-kv"><span>Check-in</span><b>' + esc(fmt(b.checkin)) + '</b></div>' +
                '<div class="hb-kv"><span>Check-out</span><b>' + esc(fmt(b.checkout)) + '</b></div>' +
                '<div class="hb-kv"><span>Nights</span><b>' + (b.nights || 0) + '</b></div>' +
                '<div class="hb-kv"><span>Rooms</span><b>' + (b.rooms || 0) + '</b></div>' +
                '<div class="hb-kv"><span>Room type</span><b>' + esc(b.room_type || '—') + '</b></div>' +
                '<div class="hb-kv"><span>Meal plan</span><b>' + esc(b.meal_plan || '—') + '</b></div>' +
                '<div class="hb-kv"><span>Supplier</span><b>' + esc(b.supplier || '—') + '</b></div>' +
                '<div class="hb-kv"><span>Booking ref</span><b>' + esc(b.booking_ref || '—') + '</b></div>' +
                '<div class="hb-kv"><span>Cancellation</span><b>' + esc(b.cancellation_policy || '—') + '</b></div>' +
                '<a class="hb-btn" style="margin-top:.45rem" href="crm/quotation_generator.php?id=' + b.quotation_id + '">View full itinerary</a></div>' +
                '<div class="hb-block"><h4>Payment Schedule (To Hotel Supplier)</h4>' + schedule(b) + '</div>';
        }
        $('#hbSide').html(
            '<div class="hb-side-head"><div><div class="hb-muted">Hotel Booking Details</div><h3>' + esc(b.code) + ' <span class="hb-pill is-' + esc(b.booking_status) + '">' + esc(bookLabel(b.booking_status)) + '</span></h3></div></div>' +
            '<div style="padding:0 .9rem .4rem;font-size:.78rem;color:#475569">' +
            '<div>Lead ' + esc(b.lead_uid || '—') + '</div><div>Tour ' + esc(b.tour || '—') + '</div>' +
            '<div>Guest ' + esc(b.guest || '—') + '</div><div>Contact ' + esc(b.phone || '—') + '</div><div>Travellers ' + esc(guests) + '</div></div>' +
            '<div class="hb-tabs">' +
            ['details','payments','reconfirm','vouchers'].map(function (key) {
                var label = { details: 'Details', payments: 'Payments', reconfirm: 'Reconfirmation', vouchers: 'Vouchers' }[key];
                return '<button type="button" data-tab="' + key + '"' + (tab === key ? ' class="is-on"' : '') + '>' + label + '</button>';
            }).join('') + '</div><div class="hb-side-body">' + body +
            '<div class="hb-block"><h4>Quick Actions</h4><div class="hb-quick">' +
            '<button type="button" class="solid" data-open="voucher">Generate Voucher</button>' +
            '<button type="button" class="solid" data-open="mail">Send Reconfirmation</button>' +
            '<button type="button" data-open="itinerary">View Confirmation</button>' +
            '<button type="button" data-open="mail">Email Supplier</button>' +
            '<button type="button" data-open="payment">Add Payment</button>' +
            '<button type="button" data-open="edit">Edit Booking</button>' +
            '</div></div></div>'
        );
    }
    function render() { renderStats(); renderRows(); renderSide(); }
    function replaceBooking(b) {
        if (!b) return;
        var found = false;
        rows = rows.map(function (row) { if (row.code === b.code) { found = true; return b; } return row; });
        if (!found) rows.push(b);
        state.selected = b.code;
        render();
    }
    function post(data) {
        return $.ajax({ url: 'crm/ajax/hotel_bookings.php', method: 'POST', dataType: 'json', data: data });
    }
    function needOne() {
        if (current()) return true;
        alert('Select a hotel booking first.');
        return false;
    }
    function mail(b) {
        var subject = 'Reconfirmation ' + b.code + ' - ' + b.hotel;
        var body = 'Please reconfirm the booking for ' + (b.guest || 'our guest') + ' at ' + b.hotel +
            ' from ' + (b.checkin || '') + ' to ' + (b.checkout || '') + '. Reference: ' + (b.booking_ref || b.code) + '.';
        if (!b.supplier_email) {
            alert('This supplier has no email on the supplier record. The message is ready to copy.\n\n' + body);
            return;
        }
        window.location.href = 'mailto:' + encodeURIComponent(b.supplier_email) + '?subject=' + encodeURIComponent(subject) + '&body=' + encodeURIComponent(body);
    }
    function openModal(mode) {
        var b = current();
        if (!b) return;
        modalMode = mode;
        var html = '';
        if (mode === 'edit') {
            $('#hbModalTitle').text('Edit ' + b.code);
            html = '<div class="form-group"><label>Booking status</label><select class="form-control" name="booking_status">' +
                ['pending','confirmed','cancelled'].map(function (k) { return '<option value="' + k + '"' + (b.booking_status === k ? ' selected' : '') + '>' + bookLabel(k) + '</option>'; }).join('') +
                '</select></div><div class="form-group"><label>Booking reference</label><input class="form-control" name="booking_ref" value="' + esc(b.booking_ref) + '"></div>' +
                '<div class="form-group"><label>Reconfirmation due</label><input class="form-control" type="date" name="reconfirm_due" value="' + esc(b.reconfirm_due) + '"></div>' +
                '<div class="form-group"><label>Reconfirmation</label><select class="form-control" name="reconfirm_status"><option value="pending"' + (b.reconfirm_status !== 'confirmed' ? ' selected' : '') + '>Pending</option><option value="confirmed"' + (b.reconfirm_status === 'confirmed' ? ' selected' : '') + '>Reconfirmed</option></select></div>' +
                '<div class="form-group"><label>Cancellation policy</label><textarea class="form-control" name="cancellation_policy" rows="3">' + esc(b.cancellation_policy) + '</textarea></div>' +
                '<div class="form-group"><label>Notes</label><textarea class="form-control" name="notes" rows="2">' + esc(b.notes) + '</textarea></div>';
        } else if (mode === 'payment') {
            $('#hbModalTitle').text('Record supplier payment');
            html = '<p class="hb-muted">Outstanding ' + money(b.balance) + '. This updates the supplier payment on the confirmed tour and does not change the tour status.</p>' +
                '<div class="form-group"><label>Amount</label><input class="form-control" name="amount" type="number" min="0" step="0.01" required></div>' +
                '<div class="form-group"><label>Date</label><input class="form-control" name="payment_date" type="date" required value="' + new Date().toISOString().slice(0, 10) + '"></div>' +
                '<div class="form-group"><label>Method</label><select class="form-control" name="method">' + (boot.methods || []).map(function (m) { return '<option>' + esc(m) + '</option>'; }).join('') + '</select></div>' +
                '<div class="form-group"><label>Reference</label><input class="form-control" name="reference"></div>';
        } else if (mode === 'reminder') {
            $('#hbModalTitle').text('Schedule reconfirmation reminder');
            html = '<div class="form-group"><label>Date and time</label><input class="form-control" type="datetime-local" name="reminder_at" required></div>' +
                '<p class="hb-muted">A follow-up is added on Tasks & Follow-ups for this guest.</p>';
        }
        $('#hbModalBody').html(html);
        $('#hbModal').modal('show');
    }
    function act(name, code) {
        if (code) state.selected = code;
        var b = current();
        if (!b) return;
        if (name === 'view') { state.tab = 'details'; render(); return; }
        if (name === 'voucher') { window.open('crm/hotel_voucher.php?code=' + encodeURIComponent(b.code), '_blank'); return; }
        if (name === 'mail') { mail(b); return; }
        if (name === 'itinerary') { window.location.href = 'crm/quotation_generator.php?id=' + b.quotation_id; return; }
        if (name === 'edit' || name === 'payment' || name === 'reminder') { openModal(name); return; }
        if (name === 'reconfirm') {
            post({ action: 'reconfirm', code: b.code }).done(function (res) {
                if (!res || !res.success) { alert((res && res.message) || 'Could not update.'); return; }
                replaceBooking(res.booking);
            });
        }
    }

    var presetSearch = new URLSearchParams(window.location.search).get('q');
    if (presetSearch) {
        $('#hbSearch').val(presetSearch);
    }
    render();
    $(document).on('click', function (e) { if (!$(e.target).closest('.hb-menu').length) $('.hb-pop').removeClass('is-open'); });
    $('#hbExportBtn').on('click', function (e) { e.stopPropagation(); $('#hbExportPop').toggleClass('is-open'); });
    $('#hbSearch, #hbFrom, #hbTo, #hbDest, #hbSupplier, #hbPay, #hbRe, #hbBook').on('input change', function () { state.page = 1; render(); });
    $('#hbReset').on('click', function () {
        $('#hbSearch, #hbFrom, #hbTo').val('');
        $('#hbDest, #hbSupplier, #hbPay, #hbRe, #hbBook').val('');
        state.card = '';
        state.page = 1;
        render();
    });
    $(document).on('click', '[data-card]', function () {
        state.card = $(this).data('card');
        state.page = 1;
        render();
    });
    $('#hbSize').on('change', function () { state.size = parseInt(this.value, 10) || 10; state.page = 1; renderRows(); });
    $(document).on('click', '#hbPages [data-page]', function () { state.page = parseInt($(this).data('page'), 10) || 1; renderRows(); });
    $(document).on('click', '#hbRows tr[data-code]', function (e) {
        if ($(e.target).closest('button, input, a').length) return;
        state.selected = $(this).data('code');
        render();
    });
    $(document).on('change', '.hb-check', function (e) {
        e.stopPropagation();
        var code = $(this).data('code');
        if (this.checked) state.checked[code] = true; else delete state.checked[code];
    });
    $('#hbAll').on('change', function () {
        var on = this.checked;
        filtered().slice((state.page - 1) * state.size, state.page * state.size).forEach(function (b) {
            if (on) state.checked[b.code] = true; else delete state.checked[b.code];
        });
        renderRows();
    });
    $(document).on('click', '[data-act]', function (e) {
        e.stopPropagation();
        var name = $(this).data('act');
        var code = $(this).data('code');
        if (name === 'more') {
            $('.hb-pop').removeClass('is-open');
            var menu = $('<div class="hb-pop is-open" style="position:absolute"></div>');
            [['edit','Edit details'],['payment','Record payment'],['reconfirm','Mark reconfirmed'],['reminder','Schedule reminder'],['itinerary','View itinerary'],['cancel','Cancel booking']].forEach(function (item) {
                menu.append('<button type="button" data-menu="' + item[0] + '" data-code="' + esc(code) + '">' + item[1] + '</button>');
            });
            var $btn = $(this);
            $('body').append(menu);
            var off = $btn.offset();
            menu.css({ position: 'fixed', top: off.top - $(window).scrollTop() + 30, left: Math.max(8, off.left - $(window).scrollLeft() - 120) });
            return;
        }
        act(name, code);
    });
    $(document).on('click', '[data-menu]', function () {
        var name = $(this).data('menu');
        var code = $(this).data('code');
        $('.hb-pop').remove();
        if (name === 'cancel' && window.confirm('Cancel this hotel booking? The confirmed tour stays as it is.')) {
            post({ action: 'cancel', code: code }).done(function (res) {
                if (!res || !res.success) { alert((res && res.message) || 'Could not cancel.'); return; }
                replaceBooking(res.booking);
            });
            return;
        }
        act(name, code);
    });
    $(document).on('click', '[data-tab]', function () { state.tab = $(this).data('tab'); renderSide(); });
    $(document).on('click', '[data-open]', function () { act($(this).data('open')); });
    $('#hbSendRe').on('click', function () { if (needOne()) act('mail'); });
    $('#hbVoucher').on('click', function () { if (needOne()) act('voucher'); });
    $('#hbForm').on('submit', function (e) {
        e.preventDefault();
        var b = current();
        if (!b) return;
        var data = { action: modalMode === 'payment' ? 'payment' : (modalMode === 'reminder' ? 'reminder' : 'save'), code: b.code };
        $(this).serializeArray().forEach(function (field) { data[field.name] = field.value; });
        var $btn = $('#hbModalSave').prop('disabled', true);
        post(data).done(function (res) {
            if (!res || !res.success) { alert((res && res.message) || 'Could not save.'); return; }
            replaceBooking(res.booking);
            $('#hbModal').modal('hide');
        }).fail(function () { alert('Could not save.'); })
          .always(function () { $btn.prop('disabled', false); });
    });
    $(document).on('click', '#hbUploadBtn', function () { $('#hbUpload').trigger('click'); });
    $(document).on('change', '#hbUpload', function () {
        var file = this.files && this.files[0];
        this.value = '';
        var b = current();
        if (!file || !b) return;
        post({ action: 'prepare', code: b.code }).done(function (res) {
            if (!res || !res.success) { alert((res && res.message) || 'Could not attach the file.'); return; }
            var fd = new FormData();
            fd.append('action', 'upload');
            fd.append('quotation_id', res.quotation_id);
            fd.append('service_uid', res.service_uid);
            fd.append('service_key', 'hotels');
            fd.append('file', file);
            $.ajax({ url: 'crm/ajax/service_vouchers.php', method: 'POST', data: fd, processData: false, contentType: false, dataType: 'json' })
                .done(function (up) {
                    if (!up || !up.success) { alert((up && up.message) || 'Could not upload.'); return; }
                    post({ action: 'one', code: b.code }).done(function (again) { if (again && again.booking) replaceBooking(again.booking); });
                });
        });
    });
    $('#hbBulkApply').on('click', function () {
        var bulk = $('#hbBulk').val();
        var codes = Object.keys(state.checked);
        if (!bulk || !codes.length) return;
        if (bulk === 'cancel' && !window.confirm('Cancel the selected hotel bookings? Confirmed tours stay unchanged.')) return;
        post({ action: 'bulk', bulk: bulk, codes: codes }).done(function (res) {
            if (!res || !res.success) { alert((res && res.message) || 'Could not update.'); return; }
            (res.bookings || []).forEach(replaceBooking);
            state.checked = {};
            $('#hbBulk').val('');
            render();
        });
    });
    function exportRows(kind) {
        var list = filtered();
        var head = ['Booking ID','Lead','Tour','Guest','Phone','Hotel','City','Check-in','Check-out','Nights','Rooms','Total','Paid','Balance','Payment','Reconfirmation','Booking status','Supplier','Reference'];
        var body = list.map(function (b) {
            return [b.code, b.lead_uid, b.tour, b.guest, b.phone, b.hotel, b.city, b.checkin, b.checkout, b.nights, b.rooms, b.total, b.paid, b.balance, payLabel(b.payment_status), b.reconfirm_label, bookLabel(b.booking_status), b.supplier, b.booking_ref];
        });
        if (kind === 'xls') {
            var table = '<table><tr>' + head.map(function (h) { return '<th>' + esc(h) + '</th>'; }).join('') + '</tr>' +
                body.map(function (line) { return '<tr>' + line.map(function (cell) { return '<td>' + esc(cell) + '</td>'; }).join('') + '</tr>'; }).join('') + '</table>';
            var blob = new Blob(['\ufeff' + table], { type: 'application/vnd.ms-excel' });
            download(blob, 'hotel-bookings.xls');
            return;
        }
        var csv = [head].concat(body).map(function (line) {
            return line.map(function (cell) { return '"' + String(cell == null ? '' : cell).replace(/"/g, '""') + '"'; }).join(',');
        }).join('\n');
        download(new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8' }), 'hotel-bookings.csv');
    }
    function download(blob, name) {
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url; a.download = name;
        document.body.appendChild(a); a.click(); a.remove();
        URL.revokeObjectURL(url);
    }
    $('#hbCsv').on('click', function () { $('.hb-pop').removeClass('is-open'); exportRows('csv'); });
    $('#hbExcel').on('click', function () { $('.hb-pop').removeClass('is-open'); exportRows('xls'); });
})(jQuery);
</script>
</body>
</html>
