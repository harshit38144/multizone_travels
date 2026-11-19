<?php
require_once __DIR__ . '/bootstrap.php';

$categories = [
    ['key' => 'suitcases', 'name' => 'Suitcases', 'total' => 100, 'stock' => 52, 'tone' => '#7c3aed', 'icon' => 'fa-suitcase-rolling'],
    ['key' => 'sling', 'name' => 'Sling Bags', 'total' => 60, 'stock' => 38, 'tone' => '#0ea5e9', 'icon' => 'fa-shopping-bag'],
    ['key' => 'accessories', 'name' => 'Travel Accessories', 'total' => 30, 'stock' => 24, 'tone' => '#10b981', 'icon' => 'fa-suitcase'],
    ['key' => 'backpacks', 'name' => 'Backpacks', 'total' => 20, 'stock' => 10, 'tone' => '#3b82f6', 'icon' => 'fa-briefcase'],
    ['key' => 'hampers', 'name' => 'Gift Hampers', 'total' => 20, 'stock' => 8, 'tone' => '#f97316', 'icon' => 'fa-gift'],
    ['key' => 'other', 'name' => 'Other Items', 'total' => 15, 'stock' => 10, 'tone' => '#64748b', 'icon' => 'fa-box'],
];

$items = [
    ['id' => 'ITM-001', 'name' => 'VIP Suitcase 24"', 'color' => 'Black / Red Trim', 'cat' => 'Suitcases', 'cat_key' => 'suitcases', 'total' => 40, 'stock' => 22, 'allocated' => 12, 'issued' => 6, 'status' => 'in'],
    ['id' => 'ITM-002', 'name' => 'American Tourister 28"', 'color' => 'Navy Blue', 'cat' => 'Suitcases', 'cat_key' => 'suitcases', 'total' => 35, 'stock' => 12, 'allocated' => 15, 'issued' => 8, 'status' => 'low'],
    ['id' => 'ITM-003', 'name' => 'Sling Bag - Premium', 'color' => 'Grey', 'cat' => 'Sling Bags', 'cat_key' => 'sling', 'total' => 25, 'stock' => 18, 'allocated' => 5, 'issued' => 2, 'status' => 'in'],
    ['id' => 'ITM-004', 'name' => 'Sling Bag - Classic', 'color' => 'Black', 'cat' => 'Sling Bags', 'cat_key' => 'sling', 'total' => 20, 'stock' => 14, 'allocated' => 4, 'issued' => 2, 'status' => 'in'],
    ['id' => 'ITM-005', 'name' => 'Travel Neck Kit', 'color' => 'Assorted', 'cat' => 'Travel Accessories', 'cat_key' => 'accessories', 'total' => 18, 'stock' => 15, 'allocated' => 2, 'issued' => 1, 'status' => 'in'],
    ['id' => 'ITM-006', 'name' => 'Neck Pillow', 'color' => 'Grey', 'cat' => 'Other Items', 'cat_key' => 'other', 'total' => 15, 'stock' => 10, 'allocated' => 3, 'issued' => 2, 'status' => 'low'],
    ['id' => 'ITM-007', 'name' => 'Backpack - 30L', 'color' => 'Black', 'cat' => 'Backpacks', 'cat_key' => 'backpacks', 'total' => 20, 'stock' => 10, 'allocated' => 6, 'issued' => 4, 'status' => 'low'],
    ['id' => 'ITM-008', 'name' => 'Gift Hamper', 'color' => 'Festive Mix', 'cat' => 'Gift Hampers', 'cat_key' => 'hampers', 'total' => 20, 'stock' => 8, 'allocated' => 7, 'issued' => 5, 'status' => 'low'],
];

$transactions = [
    ['type' => 'in', 'title' => 'Stock Received', 'item' => 'VIP Suitcase 24"', 'meta' => 'Today · by Admin', 'qty' => '+20'],
    ['type' => 'out', 'title' => 'Issued to Booking', 'item' => 'Sling Bag - Premium', 'meta' => 'Today · TR-0128', 'qty' => '-2'],
    ['type' => 'out', 'title' => 'Issued to Customer', 'item' => 'American Tourister 28"', 'meta' => 'Yesterday · Walk-in', 'qty' => '-1'],
    ['type' => 'in', 'title' => 'Stock Received', 'item' => 'Gift Hamper', 'meta' => 'Yesterday · by User02', 'qty' => '+10'],
    ['type' => 'out', 'title' => 'Issued to Booking', 'item' => 'Backpack - 30L', 'meta' => '2 days ago · TR-0120', 'qty' => '-3'],
];

$allocations = [
    ['date' => '25 Oct 2026', 'booking' => 'TR-0123', 'customer' => 'Ratan Kumar', 'item' => 'Suitcase 24"', 'qty' => 3, 'status' => 'Pending'],
    ['date' => '28 Oct 2026', 'booking' => 'TR-0125', 'customer' => 'Ashvi Barnwal', 'item' => 'Sling Bag', 'qty' => 2, 'status' => 'Pending'],
    ['date' => '02 Nov 2026', 'booking' => 'TR-0130', 'customer' => 'Harsh Gupta', 'item' => 'Backpacks', 'qty' => 4, 'status' => 'Pending'],
    ['date' => '10 Nov 2026', 'booking' => 'TR-0132', 'customer' => 'Mehul Jain', 'item' => 'Suitcase 28"', 'qty' => 2, 'status' => 'Pending'],
];

$lowStock = [
    ['id' => 'ITM-002', 'name' => 'Suitcase 28"', 'cat' => 'Suitcases', 'cat_key' => 'suitcases', 'stock' => 12, 'reorder' => 15],
    ['id' => 'ITM-008', 'name' => 'Gift Hamper', 'cat' => 'Gift Hampers', 'cat_key' => 'hampers', 'stock' => 8, 'reorder' => 10],
    ['id' => 'ITM-007', 'name' => 'Backpack - 30L', 'cat' => 'Backpacks', 'cat_key' => 'backpacks', 'stock' => 10, 'reorder' => 10],
    ['id' => 'ITM-006', 'name' => 'Neck Pillow', 'cat' => 'Other Items', 'cat_key' => 'other', 'stock' => 10, 'reorder' => 10],
];

$donut = [
    ['label' => 'Suitcases', 'value' => 52, 'pct' => 39, 'color' => '#7c3aed'],
    ['label' => 'Sling Bags', 'value' => 38, 'pct' => 29, 'color' => '#0ea5e9'],
    ['label' => 'Travel Accessories', 'value' => 24, 'pct' => 18, 'color' => '#10b981'],
    ['label' => 'Backpacks', 'value' => 10, 'pct' => 8, 'color' => '#3b82f6'],
    ['label' => 'Gift Hampers', 'value' => 8, 'pct' => 6, 'color' => '#f97316'],
    ['label' => 'Other Items', 'value' => 10, 'pct' => 7, 'color' => '#64748b'],
];

function giCatClass(string $key): string
{
    $map = [
        'suitcases' => 'is-suitcases',
        'sling' => 'is-sling',
        'accessories' => 'is-accessories',
        'backpacks' => 'is-backpacks',
        'hampers' => 'is-hampers',
        'other' => 'is-other',
    ];
    return $map[$key] ?? 'is-other';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <base href="../">
    <title>Gifting Items Inventory</title>
    <?php include __DIR__ . '/../includes/header-links.php'; ?>
    <style>
        .gi-page { background: #f4f6f9; }
        .gi-page .content-wrapper,
        .gi-page .content { background: #f4f6f9; }
        .gi-page .content-header { display: none; }
        .gi-page .content { padding: 1.1rem 1.15rem 1.5rem; }
        .gi-page button, .gi-page input, .gi-page select { font-family: inherit; }

        .gi-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 1rem;
            flex-wrap: wrap;
            margin-bottom: 1rem;
        }
        .gi-title { margin: 0; font-size: 1.45rem; font-weight: 800; color: #0f172a; }
        .gi-sub { margin: 0.3rem 0 0; color: #94a3b8; font-size: 0.88rem; max-width: 620px; line-height: 1.45; }
        .gi-actions { display: flex; gap: 0.45rem; flex-wrap: wrap; align-items: center; }
        .gi-btn {
            height: 38px;
            border-radius: 10px;
            border: 1px solid #e6ebf2;
            background: #fff;
            color: #334155;
            font-weight: 700;
            font-size: 0.8rem;
            padding: 0 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            text-decoration: none;
            cursor: pointer;
        }
        .gi-btn:hover { color: #0f172a; text-decoration: none; }
        .gi-btn-red { background: #e11d48; border-color: #e11d48; color: #fff !important; }
        .gi-btn-red:hover { background: #be123c; border-color: #be123c; color: #fff !important; }
        .gi-btn-outline {
            background: #fff;
            border-color: #fecdd3;
            color: #e11d48 !important;
        }
        .gi-btn-outline:hover { background: #fff1f2; }
        .gi-btn-icon {
            width: 38px;
            padding: 0;
            justify-content: center;
        }

        .gi-stats {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 0.7rem;
            margin-bottom: 0.9rem;
        }
        .gi-stat {
            background: #fff;
            border-radius: 14px;
            padding: 0.85rem 0.9rem;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.04);
            min-height: 92px;
        }
        .gi-stat-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.45rem; }
        .gi-ico {
            width: 34px;
            height: 34px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 0.85rem;
        }
        .gi-stat b { display: block; font-size: 1.35rem; color: #0f172a; line-height: 1.15; }
        .gi-stat span { display: block; color: #64748b; font-size: 0.76rem; font-weight: 700; }
        .gi-stat small { display: block; margin-top: 0.2rem; font-size: 0.72rem; font-weight: 700; }
        .gi-stat small.is-ok { color: #16a34a; }
        .gi-stat small.is-warn { color: #ea580c; }
        .gi-stat small.is-danger { color: #e11d48; }

        .gi-cats {
            display: grid;
            grid-template-columns: repeat(6, minmax(0, 1fr));
            gap: 0.65rem;
            margin-bottom: 0.9rem;
        }
        .gi-cat {
            background: #fff;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.04);
        }
        .gi-cat-visual {
            height: 78px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 1.6rem;
        }
        .gi-cat-body { padding: 0.65rem 0.7rem 0.75rem; }
        .gi-cat-body h4 { margin: 0 0 0.2rem; font-size: 0.84rem; font-weight: 800; color: #0f172a; }
        .gi-cat-meta { color: #94a3b8; font-size: 0.72rem; font-weight: 600; margin-bottom: 0.45rem; }
        .gi-cat-bar {
            height: 6px;
            border-radius: 999px;
            background: #f1f5f9;
            overflow: hidden;
        }
        .gi-cat-bar > span { display: block; height: 100%; border-radius: 999px; }

        .gi-main {
            display: grid;
            grid-template-columns: minmax(0, 1.7fr) minmax(280px, 0.85fr);
            gap: 0.85rem;
            margin-bottom: 0.85rem;
            align-items: start;
        }
        .gi-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.04);
            padding: 0.9rem;
        }
        .gi-card-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 0.6rem;
            margin-bottom: 0.75rem;
        }
        .gi-card-head h3 {
            margin: 0;
            font-size: 0.95rem;
            font-weight: 800;
            color: #0f172a;
        }
        .gi-link {
            color: #e11d48;
            font-size: 0.78rem;
            font-weight: 800;
            text-decoration: none;
        }
        .gi-link:hover { color: #be123c; text-decoration: none; }

        .gi-tabs {
            display: flex;
            gap: 0.15rem;
            overflow: auto;
            border-bottom: 1px solid #f1f5f9;
            margin-bottom: 0.75rem;
        }
        .gi-tabs button {
            border: 0;
            background: transparent;
            color: #64748b;
            font-weight: 800;
            font-size: 0.78rem;
            padding: 0.5rem 0.6rem;
            border-bottom: 2px solid transparent;
            white-space: nowrap;
            cursor: pointer;
        }
        .gi-tabs button.is-on {
            color: #e11d48;
            border-bottom-color: #e11d48;
        }

        .gi-toolbar {
            display: flex;
            gap: 0.45rem;
            flex-wrap: wrap;
            align-items: center;
            margin-bottom: 0.75rem;
        }
        .gi-search {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            height: 36px;
            border: 1px solid #e6ebf2;
            border-radius: 10px;
            padding: 0 0.7rem;
            background: #fff;
            flex: 1;
            min-width: 220px;
            color: #94a3b8;
        }
        .gi-search input {
            border: 0;
            outline: none;
            width: 100%;
            font-size: 0.8rem;
            font-weight: 500;
            color: #334155;
        }

        .gi-table-wrap { overflow: auto; border: 1px solid #eef2f7; border-radius: 12px; }
        table.gi-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 920px;
            font-size: 0.8rem;
            background: #fff;
        }
        .gi-table th {
            text-align: left;
            color: #94a3b8;
            font-size: 0.7rem;
            letter-spacing: 0.03em;
            text-transform: uppercase;
            font-weight: 700;
            padding: 0.65rem 0.55rem;
            border-bottom: 1px solid #f1f5f9;
            white-space: nowrap;
            background: #fafbfc;
        }
        .gi-table td {
            padding: 0.7rem 0.55rem;
            border-bottom: 1px solid #f8fafc;
            vertical-align: middle;
            color: #334155;
        }
        .gi-table tr:last-child td { border-bottom: 0; }
        .gi-table input[type="checkbox"] { width: 15px; height: 15px; }
        .gi-item-name { font-weight: 800; color: #0f172a; display: block; }
        .gi-item-sub { color: #94a3b8; font-size: 0.72rem; }
        .gi-thumb {
            width: 42px;
            height: 36px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 0.85rem;
        }
        .gi-pill {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 0.15rem 0.55rem;
            font-weight: 800;
            font-size: 0.7rem;
            white-space: nowrap;
        }
        .gi-pill.is-suitcases { background: #ede9fe; color: #6d28d9; }
        .gi-pill.is-sling { background: #e0f2fe; color: #0369a1; }
        .gi-pill.is-accessories { background: #dcfce7; color: #15803d; }
        .gi-pill.is-backpacks { background: #dbeafe; color: #1d4ed8; }
        .gi-pill.is-hampers { background: #ffe4e6; color: #be123c; }
        .gi-pill.is-other { background: #f1f5f9; color: #475569; }
        .gi-pill.is-in { background: #dcfce7; color: #15803d; }
        .gi-pill.is-low { background: #fee2e2; color: #b91c1c; }
        .gi-pill.is-pending { background: #ffedd5; color: #c2410c; }
        .gi-stock-ok { color: #16a34a; font-weight: 800; }
        .gi-stock-low { color: #e11d48; font-weight: 800; }
        .gi-reorder { color: #e11d48; font-weight: 800; }
        .gi-row-actions { display: flex; gap: 0.25rem; }
        .gi-icon-btn {
            width: 28px;
            height: 28px;
            border-radius: 8px;
            border: 1px solid #eef2f7;
            background: #fff;
            color: #64748b;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }
        .gi-icon-btn:hover { color: #e11d48; border-color: #fecdd3; }

        .gi-side { display: flex; flex-direction: column; gap: 0.85rem; }
        .gi-tx { display: flex; gap: 0.65rem; align-items: flex-start; padding: 0.55rem 0; border-bottom: 1px solid #f8fafc; }
        .gi-tx:last-child { border-bottom: 0; padding-bottom: 0; }
        .gi-tx-ico {
            width: 32px;
            height: 32px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
            font-size: 0.8rem;
        }
        .gi-tx-ico.is-in { background: #dcfce7; color: #16a34a; }
        .gi-tx-ico.is-out { background: #fee2e2; color: #e11d48; }
        .gi-tx b { display: block; font-size: 0.8rem; color: #0f172a; }
        .gi-tx span { display: block; font-size: 0.72rem; color: #64748b; }
        .gi-tx small { display: block; font-size: 0.68rem; color: #94a3b8; margin-top: 0.1rem; }
        .gi-tx-qty { margin-left: auto; font-weight: 800; font-size: 0.8rem; white-space: nowrap; }
        .gi-tx-qty.is-in { color: #16a34a; }
        .gi-tx-qty.is-out { color: #e11d48; }

        .gi-donut-wrap {
            display: flex;
            gap: 0.85rem;
            align-items: center;
        }
        .gi-donut {
            width: 118px;
            height: 118px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            flex: 0 0 auto;
        }
        .gi-donut b {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            background: #fff;
            display: grid;
            place-items: center;
            text-align: center;
            font-size: 0.78rem;
            color: #0f172a;
            line-height: 1.2;
        }
        .gi-donut b small { display: block; color: #94a3b8; font-size: 0.65rem; font-weight: 700; }
        .gi-legend { flex: 1; font-size: 0.74rem; color: #64748b; }
        .gi-legend div {
            display: flex;
            justify-content: space-between;
            gap: 0.5rem;
            padding: 0.18rem 0;
        }
        .gi-legend i {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 0.35rem;
        }

        .gi-quick {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.5rem;
        }
        .gi-quick .gi-btn {
            height: 42px;
            justify-content: center;
            width: 100%;
        }

        .gi-bottom {
            display: grid;
            grid-template-columns: minmax(0, 1.2fr) minmax(0, 1fr) 260px;
            gap: 0.85rem;
            align-items: start;
        }
        .gi-bottom .gi-table { min-width: 0; }
        .gi-bottom .gi-table-wrap { border: 0; }
        .gi-bottom table.gi-table { min-width: 100%; font-size: 0.78rem; }

        @media (max-width: 1199.98px) {
            .gi-stats { grid-template-columns: repeat(3, minmax(0, 1fr)); }
            .gi-cats { grid-template-columns: repeat(3, minmax(0, 1fr)); }
            .gi-main, .gi-bottom { grid-template-columns: 1fr; }
        }
        @media (max-width: 767.98px) {
            .gi-stats, .gi-cats { grid-template-columns: 1fr 1fr; }
            .gi-quick { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed gi-page">
<div class="wrapper">
    <?php include __DIR__ . '/../includes/top-header.php'; ?>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="content-wrapper">
        <section class="content">
            <div class="gi-head">
                <div>
                    <h1 class="gi-title">Gifting Items Inventory</h1>
                    <p class="gi-sub">Manage your gifting items like suitcases, sling bags and more. Track stock, allocate to bookings and monitor usage.</p>
                </div>
                <div class="gi-actions">
                    <a class="gi-btn gi-btn-red" href="crm/gifting_issue.php"><i class="fas fa-paper-plane"></i> Issue Item</a>
                    <button type="button" class="gi-btn gi-btn-outline"><i class="fas fa-box-open"></i> Receive Stock</button>
                    <button type="button" class="gi-btn"><i class="fas fa-download"></i> Export</button>
                    <button type="button" class="gi-btn gi-btn-icon" aria-label="More"><i class="fas fa-ellipsis-v"></i></button>
                </div>
            </div>

            <div class="gi-stats">
                <div class="gi-stat">
                    <div class="gi-stat-top"><span class="gi-ico" style="background:#e11d48"><i class="fas fa-gift"></i></span></div>
                    <b>245</b>
                    <span>Total Items</span>
                </div>
                <div class="gi-stat">
                    <div class="gi-stat-top"><span class="gi-ico" style="background:#16a34a"><i class="fas fa-box"></i></span></div>
                    <b>132</b>
                    <span>In Stock</span>
                    <small class="is-ok">54% available</small>
                </div>
                <div class="gi-stat">
                    <div class="gi-stat-top"><span class="gi-ico" style="background:#f59e0b"><i class="fas fa-shopping-cart"></i></span></div>
                    <b>96</b>
                    <span>Allocated / Issued</span>
                    <small class="is-warn">39% of total</small>
                </div>
                <div class="gi-stat">
                    <div class="gi-stat-top"><span class="gi-ico" style="background:#e11d48"><i class="fas fa-exclamation"></i></span></div>
                    <b>4</b>
                    <span>Low Stock Items</span>
                    <small class="is-danger">Need to Reorder</small>
                </div>
                <div class="gi-stat">
                    <div class="gi-stat-top"><span class="gi-ico" style="background:#7c3aed"><i class="fas fa-layer-group"></i></span></div>
                    <b>6</b>
                    <span>Total Categories</span>
                </div>
            </div>

            <div class="gi-cats">
                <?php foreach ($categories as $cat):
                    $pct = $cat['total'] > 0 ? round(($cat['stock'] / $cat['total']) * 100) : 0;
                    ?>
                    <div class="gi-cat">
                        <div class="gi-cat-visual" style="background: linear-gradient(135deg, <?= htmlspecialchars($cat['tone'], ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars($cat['tone'], ENT_QUOTES, 'UTF-8') ?>cc);">
                            <i class="fas <?= htmlspecialchars($cat['icon'], ENT_QUOTES, 'UTF-8') ?>"></i>
                        </div>
                        <div class="gi-cat-body">
                            <h4><?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?></h4>
                            <div class="gi-cat-meta"><?= (int) $cat['total'] ?> Items · <?= (int) $cat['stock'] ?> In Stock</div>
                            <div class="gi-cat-bar"><span style="width:<?= (int) $pct ?>%;background:<?= htmlspecialchars($cat['tone'], ENT_QUOTES, 'UTF-8') ?>;"></span></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="gi-main">
                <div class="gi-card">
                    <div class="gi-tabs" id="giTabs">
                        <button type="button" class="is-on" data-tab="all">All Items</button>
                        <button type="button" data-tab="in">Inventory In</button>
                        <button type="button" data-tab="out">Inventory Out</button>
                        <button type="button" data-tab="allocated">Allocated to Bookings</button>
                        <button type="button" data-tab="low">Low Stock</button>
                        <button type="button" data-tab="reports">Reports</button>
                    </div>
                    <div class="gi-toolbar">
                        <label class="gi-search"><i class="fas fa-search"></i><input type="search" id="giSearch" placeholder="Search by item name, SKU, category..."></label>
                        <button type="button" class="gi-btn"><i class="fas fa-filter"></i> Filter</button>
                    </div>
                    <div class="gi-table-wrap">
                        <table class="gi-table">
                            <thead>
                            <tr>
                                <th style="width:36px"><input type="checkbox" aria-label="Select all"></th>
                                <th>Item ID</th>
                                <th>Item Name</th>
                                <th>Category</th>
                                <th>Image</th>
                                <th>Total Qty</th>
                                <th>In Stock</th>
                                <th>Allocated</th>
                                <th>Issued</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                            </thead>
                            <tbody id="giItemsBody">
                            <?php foreach ($items as $row): ?>
                                <tr data-status="<?= htmlspecialchars($row['status'], ENT_QUOTES, 'UTF-8') ?>" data-search="<?= htmlspecialchars(strtolower($row['id'] . ' ' . $row['name'] . ' ' . $row['cat']), ENT_QUOTES, 'UTF-8') ?>">
                                    <td><input type="checkbox" aria-label="Select <?= htmlspecialchars($row['id'], ENT_QUOTES, 'UTF-8') ?>"></td>
                                    <td><b><?= htmlspecialchars($row['id'], ENT_QUOTES, 'UTF-8') ?></b></td>
                                    <td>
                                        <span class="gi-item-name"><?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <span class="gi-item-sub"><?= htmlspecialchars($row['color'], ENT_QUOTES, 'UTF-8') ?></span>
                                    </td>
                                    <td><span class="gi-pill <?= giCatClass($row['cat_key']) ?>"><?= htmlspecialchars($row['cat'], ENT_QUOTES, 'UTF-8') ?></span></td>
                                    <td>
                                        <span class="gi-thumb" style="background: linear-gradient(135deg, #fecdd3, #ffe4e6); color:#e11d48;">
                                            <i class="fas fa-gift"></i>
                                        </span>
                                    </td>
                                    <td><?= (int) $row['total'] ?></td>
                                    <td class="<?= $row['status'] === 'low' ? 'gi-stock-low' : 'gi-stock-ok' ?>"><?= (int) $row['stock'] ?></td>
                                    <td><?= (int) $row['allocated'] ?></td>
                                    <td><?= (int) $row['issued'] ?></td>
                                    <td>
                                        <?php if ($row['status'] === 'low'): ?>
                                            <span class="gi-pill is-low">Low Stock</span>
                                        <?php else: ?>
                                            <span class="gi-pill is-in">In Stock</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="gi-row-actions">
                                            <button type="button" class="gi-icon-btn" title="View"><i class="far fa-eye"></i></button>
                                            <button type="button" class="gi-icon-btn" title="Edit"><i class="far fa-edit"></i></button>
                                            <button type="button" class="gi-icon-btn" title="More"><i class="fas fa-ellipsis-v"></i></button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="gi-side">
                    <div class="gi-card">
                        <div class="gi-card-head">
                            <h3>Recent Transactions</h3>
                            <a class="gi-link" href="#">View All</a>
                        </div>
                        <?php foreach ($transactions as $tx): ?>
                            <div class="gi-tx">
                                <span class="gi-tx-ico <?= $tx['type'] === 'in' ? 'is-in' : 'is-out' ?>">
                                    <i class="fas <?= $tx['type'] === 'in' ? 'fa-plus' : 'fa-minus' ?>"></i>
                                </span>
                                <div>
                                    <b><?= htmlspecialchars($tx['title'], ENT_QUOTES, 'UTF-8') ?></b>
                                    <span><?= htmlspecialchars($tx['item'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <small><?= htmlspecialchars($tx['meta'], ENT_QUOTES, 'UTF-8') ?></small>
                                </div>
                                <div class="gi-tx-qty <?= $tx['type'] === 'in' ? 'is-in' : 'is-out' ?>"><?= htmlspecialchars($tx['qty'], ENT_QUOTES, 'UTF-8') ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="gi-card">
                        <div class="gi-card-head">
                            <h3>Stock by Category</h3>
                        </div>
                        <?php
                        $stops = [];
                        $acc = 0;
                        foreach ($donut as $slice) {
                            $start = $acc;
                            $acc += $slice['pct'];
                            $stops[] = $slice['color'] . ' ' . $start . '% ' . $acc . '%';
                        }
                        $donutCss = implode(', ', $stops);
                        ?>
                        <div class="gi-donut-wrap">
                            <div class="gi-donut" style="background:conic-gradient(<?= htmlspecialchars($donutCss, ENT_QUOTES, 'UTF-8') ?>);">
                                <b>132<small>In Stock</small></b>
                            </div>
                            <div class="gi-legend">
                                <?php foreach ($donut as $slice): ?>
                                    <div>
                                        <span><i style="background:<?= htmlspecialchars($slice['color'], ENT_QUOTES, 'UTF-8') ?>"></i><?= htmlspecialchars($slice['label'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <b><?= (int) $slice['value'] ?> (<?= (int) $slice['pct'] ?>%)</b>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div class="gi-card">
                        <div class="gi-card-head">
                            <h3>Quick Actions</h3>
                        </div>
                        <div class="gi-quick">
                            <button type="button" class="gi-btn gi-btn-red"><i class="fas fa-plus"></i> Add New Item</button>
                            <button type="button" class="gi-btn gi-btn-outline"><i class="fas fa-box-open"></i> Receive Stock</button>
                            <a href="crm/gifting_issue.php" class="gi-btn gi-btn-outline"><i class="fas fa-paper-plane"></i> Issue to Booking</a>
                            <button type="button" class="gi-btn gi-btn-outline"><i class="fas fa-sliders-h"></i> Stock Adjustment</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="gi-bottom">
                <div class="gi-card">
                    <div class="gi-card-head">
                        <h3>Upcoming Allocations</h3>
                        <a class="gi-link" href="#">View All</a>
                    </div>
                    <div class="gi-table-wrap">
                        <table class="gi-table">
                            <thead>
                            <tr>
                                <th>Date</th>
                                <th>Booking ID</th>
                                <th>Customer</th>
                                <th>Item</th>
                                <th>Qty</th>
                                <th>Status</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($allocations as $row): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['date'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><b><?= htmlspecialchars($row['booking'], ENT_QUOTES, 'UTF-8') ?></b></td>
                                    <td><?= htmlspecialchars($row['customer'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($row['item'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= (int) $row['qty'] ?></td>
                                    <td><span class="gi-pill is-pending"><?= htmlspecialchars($row['status'], ENT_QUOTES, 'UTF-8') ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="gi-card">
                    <div class="gi-card-head">
                        <h3>Low Stock Items</h3>
                        <a class="gi-link" href="#">View All</a>
                    </div>
                    <div class="gi-table-wrap">
                        <table class="gi-table">
                            <thead>
                            <tr>
                                <th>Item ID</th>
                                <th>Item Name</th>
                                <th>Category</th>
                                <th>In Stock</th>
                                <th>Reorder Level</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($lowStock as $row): ?>
                                <tr>
                                    <td><b><?= htmlspecialchars($row['id'], ENT_QUOTES, 'UTF-8') ?></b></td>
                                    <td><?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><span class="gi-pill <?= giCatClass($row['cat_key']) ?>"><?= htmlspecialchars($row['cat'], ENT_QUOTES, 'UTF-8') ?></span></td>
                                    <td><?= (int) $row['stock'] ?></td>
                                    <td class="gi-reorder"><?= (int) $row['reorder'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="gi-card">
                    <div class="gi-card-head">
                        <h3>Quick Actions</h3>
                    </div>
                    <div class="gi-quick" style="grid-template-columns:1fr;">
                        <button type="button" class="gi-btn gi-btn-red"><i class="fas fa-plus"></i> Add New Item</button>
                        <button type="button" class="gi-btn gi-btn-outline"><i class="fas fa-box-open"></i> Receive Stock</button>
                        <a href="crm/gifting_issue.php" class="gi-btn gi-btn-outline"><i class="fas fa-paper-plane"></i> Issue to Booking</a>
                        <button type="button" class="gi-btn gi-btn-outline"><i class="fas fa-sliders-h"></i> Stock Adjustment</button>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer-links.php'; ?>
<script>
(function ($) {
    var tab = 'all';
    function applyFilters() {
        var q = String($('#giSearch').val() || '').toLowerCase().trim();
        $('#giItemsBody tr').each(function () {
            var $tr = $(this);
            var status = String($tr.data('status') || '');
            var hay = String($tr.data('search') || '');
            var okTab = (tab !== 'low') || status === 'low';
            var okSearch = !q || hay.indexOf(q) !== -1;
            $tr.toggle(okTab && okSearch);
        });
    }
    $('#giTabs').on('click', 'button', function () {
        tab = String($(this).data('tab') || 'all');
        $('#giTabs button').removeClass('is-on');
        $(this).addClass('is-on');
        applyFilters();
    });
    $('#giSearch').on('input', applyFilters);
})(jQuery);
</script>
</body>
</html>
