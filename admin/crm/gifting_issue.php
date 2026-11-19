<?php
require_once __DIR__ . '/bootstrap.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <base href="../">
    <title>Issue / Allocate Gifting Items</title>
    <?php include __DIR__ . '/../includes/header-links.php'; ?>
    <style>
        .gi-page { background: #f4f6f9; }
        .gi-page .content-wrapper,
        .gi-page .content { background: #f4f6f9; }
        .gi-page .content-header { display: none; }
        .gi-page .content { padding: 1.1rem 1.15rem 1.5rem; }
        .gi-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 1rem;
            flex-wrap: wrap;
            margin-bottom: 1rem;
        }
        .gi-title { margin: 0; font-size: 1.45rem; font-weight: 800; color: #0f172a; }
        .gi-sub { margin: 0.3rem 0 0; color: #94a3b8; font-size: 0.88rem; max-width: 620px; }
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
        }
        .gi-btn-red { background: #e11d48; border-color: #e11d48; color: #fff !important; }
        .gi-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.04);
            padding: 1.25rem;
            max-width: 720px;
        }
        .gi-card h3 { margin: 0 0 0.35rem; font-size: 1rem; font-weight: 800; color: #0f172a; }
        .gi-card p { margin: 0; color: #64748b; font-size: 0.88rem; line-height: 1.5; }
        .gi-card .gi-actions { display: flex; gap: 0.5rem; margin-top: 1rem; flex-wrap: wrap; }
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
                    <h1 class="gi-title">Issue / Allocate</h1>
                    <p class="gi-sub">Issue gifting items to bookings or customers and track allocations from inventory.</p>
                </div>
                <a class="gi-btn" href="crm/gifting_inventory.php"><i class="fas fa-arrow-left"></i> Back to Inventory</a>
            </div>
            <div class="gi-card">
                <h3>Issue workflow coming next</h3>
                <p>Use this screen to allocate suitcases, sling bags, and other gifting items against confirmed bookings. The inventory dashboard is ready — connect issue forms and stock movements here when you want full transactional flow.</p>
                <div class="gi-actions">
                    <a class="gi-btn gi-btn-red" href="crm/gifting_inventory.php"><i class="fas fa-gift"></i> Open Inventory</a>
                    <a class="gi-btn" href="crm/bookings.php"><i class="fas fa-plane-departure"></i> View Bookings</a>
                </div>
            </div>
        </section>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer-links.php'; ?>
</body>
</html>
