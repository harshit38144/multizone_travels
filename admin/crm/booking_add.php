<?php
require_once __DIR__ . '/bootstrap.php';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <base href="../">
    <title>Add Booking</title>
    <?php include __DIR__ . '/../includes/header-links.php'; ?>
    <style>
        .ba-page { background: #f4f7fb; }
        .ba-page .content-wrapper, .ba-page .content { background: #f4f7fb; }
        .ba-page .content-header { display: none; }
        .ba-card { max-width: 560px; margin: 2rem auto; background: #fff; border-radius: 16px; padding: 1.4rem 1.5rem; box-shadow: 0 8px 24px rgba(15, 23, 42, 0.05); }
        .ba-card h1 { margin: 0 0 0.4rem; font-size: 1.35rem; font-weight: 800; }
        .ba-card p { color: #64748b; }
        .ba-card a { display: inline-flex; align-items: center; margin-right: 0.45rem; margin-top: 0.4rem; }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed ba-page">
<div class="wrapper">
    <?php include __DIR__ . '/../includes/top-header.php'; ?>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="content-wrapper">
        <section class="content">
            <div class="ba-card">
                <h1>Add a booking</h1>
                <p>A confirmed booking is a quotation marked Confirm Tour. Customer, supplier, hotel, and flight details stay on that quotation, so a separate booking is not created here.</p>
                <a class="btn btn-danger" href="crm/leads.php"><i class="fas fa-user-plus mr-1"></i> Open Leads</a>
                <a class="btn btn-outline-secondary" href="crm/quotation-generator-list.php"><i class="fas fa-file-invoice mr-1"></i> Open Quotations</a>
                <a class="btn btn-outline-secondary" href="crm/bookings.php"><i class="fas fa-list mr-1"></i> All Bookings</a>
            </div>
        </section>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer-links.php'; ?>
</body>
</html>
