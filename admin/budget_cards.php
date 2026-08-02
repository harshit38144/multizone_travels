<?php
session_start();
if ($_SESSION['role'] != '1') {
	header('location:index.php');
	exit;
}
include __DIR__ . '/connection.php';

// Initialize Table
$table_sql = "CREATE TABLE IF NOT EXISTS `budget_cards` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `amount` VARCHAR(100) NOT NULL,
  `label` VARCHAR(50) NOT NULL DEFAULT 'BELOW',
  `description` VARCHAR(255) NOT NULL,
  `icon_type` VARCHAR(50) NOT NULL DEFAULT 'fontawesome',
  `icon` VARCHAR(50) NOT NULL DEFAULT 'fa-plane',
  `icon_image` VARCHAR(255) NOT NULL DEFAULT '',
  `color_class` VARCHAR(50) NOT NULL DEFAULT 'card-blue',
  `display_order` INT NOT NULL DEFAULT 1,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($table_sql);

// Add new columns to existing table if they are missing
$check_cols = $conn->query("SHOW COLUMNS FROM `budget_cards` LIKE 'icon_type'");
if($check_cols->num_rows == 0) {
    $conn->query("ALTER TABLE `budget_cards` ADD `icon_type` VARCHAR(50) NOT NULL DEFAULT 'fontawesome' AFTER `description`");
    $conn->query("ALTER TABLE `budget_cards` ADD `icon_image` VARCHAR(255) NOT NULL DEFAULT '' AFTER `icon`");
}

// Check if table is empty, if so, insert default data
$check_empty = $conn->query("SELECT COUNT(*) as cnt FROM budget_cards");
if ($check_empty && $check_empty->fetch_assoc()['cnt'] == 0) {
    $default_inserts = "INSERT INTO budget_cards (amount, label, description, icon, color_class, display_order) VALUES 
    ('Rs. 50,000', 'BELOW', 'Budget Friendly Tours', 'fa-plane', 'card-blue', 1),
    ('Rs. 1,00,000', 'BELOW', 'Value Packages', 'fa-umbrella-beach', 'card-green', 2),
    ('Rs. 2,00,000', 'BELOW', 'Premium Escapes', 'fa-ship', 'card-yellow', 3),
    ('Rs. 3,00,000', 'BELOW', 'Ultra Luxury', 'fa-crown', 'card-pink', 4)";
    $conn->query($default_inserts);
}

// Handle AJAX Reorder
if (isset($_POST['action']) && $_POST['action'] == 'update_order') {
    $orderData = $_POST['orderData'];
    foreach ($orderData as $item) {
        $id = (int)$item['id'];
        $order = (int)$item['order'];
        $conn->query("UPDATE budget_cards SET display_order=$order WHERE id=$id");
    }
    echo "Success";
    exit;
}

// Handle AJAX Status Update
if (isset($_POST['action']) && $_POST['action'] == 'update_status') {
    $id = (int)$_POST['id'];
    $status = (int)$_POST['status'];
    $conn->query("UPDATE budget_cards SET is_active=$status WHERE id=$id");
    echo "Success";
    exit;
}

// Handle Delete
if (isset($_GET['delete_id'])) {
    $id = (int)$_GET['delete_id'];
    $conn->query("DELETE FROM budget_cards WHERE id=$id");
    $_SESSION['msg'] = "Budget card deleted successfully!";
    header("Location: budget_cards.php");
    exit;
}

$msg = "";
$msg_type = "success";
if (isset($_SESSION['msg'])) {
    $msg = $_SESSION['msg'];
    $msg_type = isset($_SESSION['msg_type']) ? $_SESSION['msg_type'] : 'success';
    unset($_SESSION['msg'], $_SESSION['msg_type']);
}

// Fetch Counters
$sql = "SELECT * FROM budget_cards ORDER BY display_order ASC";
$result = $conn->query($sql);

$active_count = $conn->query("SELECT COUNT(*) as c FROM budget_cards WHERE is_active=1")->fetch_assoc()['c'];
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Manage Budget Cards</title>
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
        
        .main-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.05);
            background: #fff;
            margin-bottom: 30px;
        }

        .btn-add-new {
            background: #fff;
            color: #6a11cb;
            border: none;
            border-radius: 6px;
            padding: 6px 16px;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.2s;
        }
        .btn-add-new:hover { background: #f8f9fa; color: #5a0fb0; }

        .table th { background: #f8f9fa; border-bottom: 2px solid #dee2e6; color: #495057; font-size: 13px; text-transform: uppercase; font-weight: 600; border-top: none; }
        .table td { vertical-align: middle; border-bottom: 1px solid #eee; }
        
        .sortable-row { cursor: move; }
        .drag-handle { color: #aaa; cursor: move; font-size: 14px; margin-top: 4px; display: block; text-align: center; }

        .order-badge { background: #6c757d; color: #fff; width: 26px; height: 26px; border-radius: 4px; display: inline-flex; align-items: center; justify-content: center; font-size: 12px; font-weight: bold; }

        .badge-active { background: #198754; color: #fff; padding: 4px 12px; border-radius: 4px; font-size: 11px; font-weight: 600; text-transform: uppercase;}
        .badge-inactive { background: #6c757d; color: #fff; padding: 4px 12px; border-radius: 4px; font-size: 11px; font-weight: 600; text-transform: uppercase;}

        .toggle-switch { position: relative; display: inline-block; width: 44px; height: 24px; margin: 0; vertical-align: middle; }
        .toggle-switch input { opacity: 0; width: 0; height: 0; }
        .toggle-slider { position: absolute; cursor: pointer; inset: 0; background-color: #ccc; border-radius: 24px; transition: 0.3s; }
        .toggle-slider:before { content: ""; position: absolute; height: 18px; width: 18px; left: 3px; bottom: 3px; background: #fff; border-radius: 50%; transition: 0.3s; }
        .toggle-switch input:checked + .toggle-slider { background-color: #0d6efd; }
        .toggle-switch input:checked + .toggle-slider:before { transform: translateX(20px); }

        .action-btn { width: 28px; height: 28px; border-radius: 4px; display: inline-flex; align-items: center; justify-content: center; transition: transform 0.2s; border: none; font-size: 12px; text-decoration: none; color: #fff; margin: 1px; }
        .btn-edit { background: #ffc107; color: #000; }
        .btn-delete { background: #dc3545; color: #fff; }
        .action-btn:hover { transform: translateY(-2px); opacity: 0.9; color: inherit; }
        
        .title-text { font-weight: 600; color: #333; font-size: 15px; }
        .counter-val-text { font-size: 18px; font-weight: 700; color: #0d6efd; font-family: monospace; }
        
        .alert-limit { background-color: #fff3cd; border-color: #ffeeba; color: #856404; padding: 10px 15px; border-radius: 6px; margin-bottom: 20px; font-size: 14px; }
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
                            <h1 class="m-0 text-dark"><i class="fas fa-tags mr-2"></i> Budget Cards</h1>
                        </div>
                        <div class="col-sm-6">
                            <ol class="breadcrumb float-sm-right">
                                <li class="breadcrumb-item"><a href="dashboard.php" style="color: #6a11cb;">Dashboard</a></li>
                                <li class="breadcrumb-item active">Budget Cards</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

            <section class="content">
                <div class="container-fluid">

                    <?php if (!empty($msg)) { ?>
                        <div class="alert alert-<?= $msg_type ?> alert-dismissible fade show shadow-sm">
                            <?= $msg; ?>
                            <button type="button" class="close" data-dismiss="alert">&times;</button>
                        </div>
                    <?php } ?>

                    <div class="card main-card">
                        <div class="card-header-purple">
                            <h5 class="mb-0"><i class="fas fa-list-ol mr-2"></i> All Cards</h5>
                            <a href="budget_card_form.php" class="btn-add-new">
                                <i class="fas fa-plus mr-1"></i> Add New Card
                            </a>
                        </div>
                        
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0" id="countersTable">
                                    <thead>
                                        <tr>
                                            <th style="width: 80px;" class="text-center">Order</th>
                                            <th>Amount & Label</th>
                                            <th>Description</th>
                                            <th style="width: 100px;" class="text-center">Status</th>
                                            <th style="width: 100px;" class="text-center">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="sortable-body">
                                        <?php if($result && $result->num_rows > 0): ?>
                                            <?php while ($row = $result->fetch_assoc()): ?>
                                            <tr class="sortable-row" data-id="<?= $row['id'] ?>">
                                                <td class="text-center">
                                                    <span class="order-badge order-number"><?= $row['display_order'] ?></span>
                                                    <i class="fas fa-grip-vertical drag-handle"></i>
                                                </td>
                                                <td>
                                                    <div class="title-text">
                                                        <?php if(isset($row['icon_type']) && $row['icon_type'] == 'image' && !empty($row['icon_image'])): ?>
                                                            <img src="<?= htmlspecialchars($row['icon_image']) ?>" alt="icon" style="width: 20px; height: 20px; object-fit: contain; vertical-align: middle;" class="mr-1">
                                                        <?php else: ?>
                                                            <i class="fas <?= htmlspecialchars($row['icon']) ?> mr-1"></i>
                                                        <?php endif; ?>
                                                        <?= htmlspecialchars($row['label']) ?> <?= htmlspecialchars($row['amount']) ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="counter-val-text" style="font-size: 14px; color: #555;"><?= htmlspecialchars($row['description']) ?></div>
                                                </td>
                                                <td class="text-center">
                                                    <label class="toggle-switch">
                                                        <input type="checkbox" class="status-toggle" data-id="<?= $row['id'] ?>" <?= $row['is_active'] ? 'checked' : '' ?>>
                                                        <span class="toggle-slider"></span>
                                                    </label>
                                                </td>
                                                <td class="text-center">
                                                    <div class="d-flex justify-content-center flex-wrap">
                                                        <a href="budget_card_form.php?edit_id=<?= $row['id'] ?>" class="action-btn btn-edit" title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <a href="budget_cards.php?delete_id=<?= $row['id'] ?>" onclick="return confirm('Are you sure you want to delete this card?');" class="action-btn btn-delete" title="Delete">
                                                            <i class="fas fa-trash"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="6" class="text-center py-4 text-muted">No budget cards found. Click 'Add New Card' to create one.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                </div>
            </section>
        </div>
        
    </div>

    <?php include __DIR__ . '/includes/footer-links.php'; ?>
    
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
    <script>
        $(document).ready(function() {
            // Initialize sortable
            $("#sortable-body").sortable({
                handle: ".drag-handle",
                update: function(event, ui) {
                    updateOrder();
                }
            });

            function updateOrder() {
                var orderData = [];
                $('#sortable-body tr').each(function(index) {
                    var id = $(this).data('id');
                    var order = index + 1;
                    orderData.push({id: id, order: order});
                    // Update visual order number immediately
                    $(this).find('.order-number').text(order);
                });

                $.ajax({
                    url: 'budget_cards.php',
                    method: 'POST',
                    data: {
                        action: 'update_order',
                        orderData: orderData
                    },
                    success: function(response) {
                        // Optional: Show a subtle toast/notification that order was saved
                        const Toast = Swal.mixin({
                          toast: true,
                          position: 'top-end',
                          showConfirmButton: false,
                          timer: 3000
                        });
                        Toast.fire({
                          icon: 'success',
                          title: 'Display order updated'
                        });
                    }
                });
            }

            $('.status-toggle').change(function() {
                var id = $(this).data('id');
                var status = $(this).is(':checked') ? 1 : 0;
                
                $.ajax({
                    url: 'budget_cards.php',
                    method: 'POST',
                    data: {
                        action: 'update_status',
                        id: id,
                        status: status
                    },
                    success: function(response) {
                        const Toast = Swal.mixin({
                          toast: true,
                          position: 'top-end',
                          showConfirmButton: false,
                          timer: 3000
                        });
                        Toast.fire({
                          icon: 'success',
                          title: 'Status updated successfully'
                        });
                    }
                });
            });
        });
    </script>
</body>
</html>