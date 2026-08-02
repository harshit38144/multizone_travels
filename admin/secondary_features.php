<?php
session_start();
if ($_SESSION['role'] != '1') {
	header('location:index.php');
	exit;
}
include __DIR__ . '/connection.php';

$table_items = "CREATE TABLE IF NOT EXISTS `homepage_secondary_features` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT NOT NULL,
  `icon_type` ENUM('fontawesome','image') NOT NULL DEFAULT 'fontawesome',
  `icon_class` VARCHAR(100) DEFAULT NULL,
  `icon_image` VARCHAR(255) DEFAULT NULL,
  `display_order` INT NOT NULL DEFAULT 1,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($table_items);

$table_settings = "CREATE TABLE IF NOT EXISTS `homepage_secondary_feature_settings` (
  `id` TINYINT UNSIGNED PRIMARY KEY DEFAULT 1,
  `section_title` VARCHAR(255) NOT NULL DEFAULT '',
  `section_subtitle` TEXT,
  `section_bg_color` VARCHAR(40) DEFAULT NULL,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($table_settings);

$conn->query("INSERT IGNORE INTO homepage_secondary_feature_settings (id, section_title, section_subtitle, section_bg_color) VALUES (
  1,
  'Travel With Confidence',
  'Everything you need for a smooth trip—from the first quote to the flight home.',
  NULL
)");

$chk = $conn->query("SELECT COUNT(*) AS c FROM homepage_secondary_features")->fetch_assoc();
if ($chk && (int)$chk['c'] === 0) {
    $rows = [
        "('Transparent pricing', 'No hidden fees—see what you pay upfront and what is included in every package.', 'fontawesome', 'fas fa-receipt', '', 1, 1)",
        "('Flexible itineraries', 'Adjust dates, pacing, and hotels so the trip fits your schedule and comfort.', 'fontawesome', 'fas fa-route', '', 2, 1)",
        "('Dedicated trip support', 'Reach our team before and during travel for changes, questions, or emergencies.', 'fontawesome', 'fas fa-headset', '', 3, 1)",
        "('Trusted partners worldwide', 'Hotels, airlines, and ground teams vetted for quality, safety, and service.', 'fontawesome', 'fas fa-handshake', '', 4, 1)",
    ];
    $conn->query("INSERT INTO homepage_secondary_features (title, description, icon_type, icon_class, icon_image, display_order, is_active) VALUES " . implode(',', $rows));
}

if (isset($_POST['action']) && $_POST['action'] === 'update_order_secondary') {
    $orderData = $_POST['orderData'];
    foreach ($orderData as $item) {
        $id = (int)$item['id'];
        $order = (int)$item['order'];
        $conn->query("UPDATE homepage_secondary_features SET display_order=$order WHERE id=$id");
    }
    echo 'Success';
    exit;
}

if (isset($_GET['delete_id'])) {
    $id = (int)$_GET['delete_id'];
    $old = $conn->query("SELECT icon_image FROM homepage_secondary_features WHERE id=$id")->fetch_assoc();
    if ($old && !empty($old['icon_image']) && file_exists($old['icon_image'])) {
        unlink($old['icon_image']);
    }
    $conn->query("DELETE FROM homepage_secondary_features WHERE id=$id");
    $_SESSION['msg'] = "Item deleted.";
    header("Location: secondary_features.php");
    exit;
}

$msg = "";
$msg_type = "success";
if (isset($_SESSION['msg'])) {
    $msg = $_SESSION['msg'];
    $msg_type = isset($_SESSION['msg_type']) ? $_SESSION['msg_type'] : 'success';
    unset($_SESSION['msg'], $_SESSION['msg_type']);
}

$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$where_clause = "1=1";
if ($filter_status !== '') {
    $status_val = ($filter_status === '1') ? 1 : 0;
    $where_clause .= " AND is_active = $status_val";
}

$sql = "SELECT * FROM homepage_secondary_features WHERE $where_clause ORDER BY display_order ASC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Homepage Secondary Features</title>
    <?php include __DIR__ . '/includes/header-links.php'; ?>
    <style>
        .page-bg { background-color: #f4f6f9; }
        .card-header-purple {
            background: linear-gradient(135deg, #6a11cb 0%, #7b5ea7 50%, #a855f7 100%);
            color: #fff;
            padding: 16px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-radius: 12px 12px 0 0 !important;
        }
        .main-card { border: none; border-radius: 12px; box-shadow: 0 2px 15px rgba(0,0,0,0.05); background: #fff; margin-bottom: 24px; }
        .filter-card { background: #fdfdfd; border: 1px solid #eee; border-radius: 8px; padding: 15px; margin-bottom: 20px; }
        .btn-add-new { background: #fff; color: #6a11cb; border: none; border-radius: 6px; padding: 6px 16px; font-weight: 600; font-size: 14px; }
        .btn-add-new:hover { background: #f8f9fa; color: #5a0fb0; }
        .table th { background: #f8f9fa; border-bottom: 2px solid #dee2e6; font-size: 13px; text-transform: uppercase; font-weight: 600; border-top: none; }
        .table td { vertical-align: middle; border-bottom: 1px solid #eee; }
        .sortable-row { cursor: move; }
        .drag-handle { color: #aaa; cursor: move; font-size: 14px; display: block; text-align: center; }
        .order-badge { background: #6c757d; color: #fff; width: 26px; height: 26px; border-radius: 4px; display: inline-flex; align-items: center; justify-content: center; font-size: 12px; font-weight: bold; }
        .icon-preview { font-size: 28px; color: #0d6efd; display: flex; align-items: center; justify-content: center; width: 50px; height: 50px; }
        .img-preview { width: 40px; height: 40px; object-fit: contain; }
        .badge-active { background: #198754; color: #fff; padding: 4px 12px; border-radius: 4px; font-size: 11px; font-weight: 600; text-transform: uppercase;}
        .badge-inactive { background: #6c757d; color: #fff; padding: 4px 12px; border-radius: 4px; font-size: 11px; font-weight: 600; text-transform: uppercase;}
        .action-btn { width: 28px; height: 28px; border-radius: 4px; display: inline-flex; align-items: center; justify-content: center; border: none; font-size: 12px; text-decoration: none; color: #fff; margin: 1px; }
        .btn-edit { background: #ffc107; color: #000; }
        .btn-delete { background: #dc3545; color: #fff; }
        .title-text { font-weight: 600; color: #333; font-size: 15px; }
        .desc-text { color: #666; font-size: 13px; max-width: 400px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    </style>
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
                        <h1 class="m-0 text-dark"><i class="fas fa-layer-group mr-2"></i> Homepage Secondary Features</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="dashboard.php" style="color: #6a11cb;">Dashboard</a></li>
                            <li class="breadcrumb-item active">Secondary Features</li>
                        </ol>
                    </div>
                </div>
                <p class="text-muted mb-0 ml-1" style="font-size:14px;">Second strip below “Why Plan Your Travel With Us?” on the homepage. Same layout, separate content.</p>
            </div>
        </div>

        <section class="content">
            <div class="container-fluid">
                <?php if (!empty($msg)) { ?>
                    <div class="alert alert-<?= htmlspecialchars($msg_type) ?> alert-dismissible fade show shadow-sm">
                        <?= $msg ?>
                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                    </div>
                <?php } ?>

                <div class="alert alert-info shadow-sm mb-4" style="border-radius:10px;">
                    <strong><i class="fas fa-layer-group mr-1"></i> Homepage strip settings</strong>
                    — Order on the homepage, visibility, background colour, title, and subtitle for this block are managed in
                    <a href="homepage_sections.php" class="alert-link font-weight-bold">Homepage Sections</a>
                    (row: <span class="font-monospace">secondary_features</span> — “Secondary Features Strip”).
                    This page is only for the feature cards below.
                </div>

                <div class="filter-card">
                    <form method="GET" action="">
                        <div class="row align-items-end">
                            <div class="col-md-3">
                                <label class="font-weight-normal text-muted mb-1" style="font-size:13px;">Status</label>
                                <select name="status" class="form-control form-control-sm" onchange="this.form.submit()">
                                    <option value="">All</option>
                                    <option value="1" <?= ($filter_status === '1') ? 'selected' : '' ?>>Active</option>
                                    <option value="0" <?= ($filter_status === '0') ? 'selected' : '' ?>>Inactive</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <a href="secondary_features.php" class="btn btn-sm btn-secondary"><i class="fas fa-redo mr-1"></i> Reset</a>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="card main-card">
                    <div class="card-header-purple">
                        <h5 class="mb-0"><i class="fas fa-th-list mr-2"></i> Feature cards</h5>
                        <a href="secondary_feature_form.php" class="btn-add-new"><i class="fas fa-plus mr-1"></i> Add card</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th style="width:80px;" class="text-center">Order</th>
                                        <th style="width:80px;" class="text-center">Icon</th>
                                        <th style="width:22%;">Title</th>
                                        <th>Description</th>
                                        <th style="width:100px;" class="text-center">Status</th>
                                        <th style="width:100px;" class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="secondary-sortable-body">
                                    <?php if ($result && $result->num_rows > 0): ?>
                                        <?php while ($row = $result->fetch_assoc()): ?>
                                            <tr class="sortable-row" data-id="<?= (int)$row['id'] ?>">
                                                <td class="text-center">
                                                    <span class="order-badge order-number"><?= (int)$row['display_order'] ?></span>
                                                    <i class="fas fa-grip-vertical drag-handle"></i>
                                                </td>
                                                <td class="text-center">
                                                    <div class="icon-preview">
                                                        <?php if ($row['icon_type'] === 'fontawesome'): ?>
                                                            <i class="<?= htmlspecialchars($row['icon_class']) ?>"></i>
                                                        <?php elseif (!empty($row['icon_image'])): ?>
                                                            <img src="<?= htmlspecialchars($row['icon_image']) ?>" class="img-preview" alt="">
                                                        <?php else: ?>
                                                            <i class="fas fa-image text-muted"></i>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td><div class="title-text"><?= htmlspecialchars($row['title']) ?></div></td>
                                                <td><div class="desc-text"><?= htmlspecialchars($row['description']) ?></div></td>
                                                <td class="text-center">
                                                    <?= $row['is_active']
                                                        ? '<span class="badge-active">Active</span>'
                                                        : '<span class="badge-inactive">Inactive</span>' ?>
                                                </td>
                                                <td class="text-center">
                                                    <a href="secondary_feature_form.php?edit_id=<?= (int)$row['id'] ?>" class="action-btn btn-edit" title="Edit"><i class="fas fa-edit"></i></a>
                                                    <a href="secondary_features.php?delete_id=<?= (int)$row['id'] ?>" class="action-btn btn-delete" title="Delete" onclick="return confirm('Delete this card?');"><i class="fas fa-trash"></i></a>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr><td colspan="6" class="text-center py-4 text-muted">No cards. Click “Add card”.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <?php include __DIR__ . '/includes/footer-links.php'; ?>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
    <script>
        $(function () {
            $("#secondary-sortable-body").sortable({
                handle: ".drag-handle",
                update: function () {
                    var orderData = [];
                    $('#secondary-sortable-body tr').each(function (index) {
                        var id = $(this).data('id');
                        if (!id) return;
                        var order = index + 1;
                        $(this).find('.order-number').text(order);
                        orderData.push({ id: id, order: order });
                    });
                    $.ajax({
                        url: 'secondary_features.php',
                        type: 'POST',
                        data: { action: 'update_order_secondary', orderData: orderData },
                        success: function () {}
                    });
                }
            });
        });
    </script>
</div>
</body>
</html>
