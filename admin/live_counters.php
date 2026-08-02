<?php
session_start();
if ($_SESSION['role'] != '1') {
	header('location:index.php');
	exit;
}
include __DIR__ . '/connection.php';

// Initialize Table
$table_sql = "CREATE TABLE IF NOT EXISTS `live_counters` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `counter_value` VARCHAR(100) NOT NULL,
  `display_order` INT NOT NULL DEFAULT 1,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($table_sql);

// Handle AJAX Reorder
if (isset($_POST['action']) && $_POST['action'] == 'update_order') {
    $orderData = $_POST['orderData'];
    foreach ($orderData as $item) {
        $id = (int)$item['id'];
        $order = (int)$item['order'];
        $conn->query("UPDATE live_counters SET display_order=$order WHERE id=$id");
    }
    echo "Success";
    exit;
}

// Handle Delete
if (isset($_GET['delete_id'])) {
    $id = (int)$_GET['delete_id'];
    $conn->query("DELETE FROM live_counters WHERE id=$id");
    $_SESSION['msg'] = "Counter deleted successfully!";
    header("Location: live_counters.php");
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
$sql = "SELECT * FROM live_counters ORDER BY display_order ASC";
$result = $conn->query($sql);

$active_count = $conn->query("SELECT COUNT(*) as c FROM live_counters WHERE is_active=1")->fetch_assoc()['c'];
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Manage Live Counters</title>
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
                            <h1 class="m-0 text-dark"><i class="fas fa-sort-numeric-up-alt mr-2"></i> Live Counters</h1>
                        </div>
                        <div class="col-sm-6">
                            <ol class="breadcrumb float-sm-right">
                                <li class="breadcrumb-item"><a href="dashboard.php" style="color: #6a11cb;">Dashboard</a></li>
                                <li class="breadcrumb-item active">Live Counters</li>
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
                    
                    <?php if($active_count > 4): ?>
                        <div class="alert-limit"><i class="fas fa-exclamation-triangle mr-2"></i><strong>Note:</strong> You have <?= $active_count ?> active counters, but typically only 4 are displayed on the website.</div>
                    <?php endif; ?>

                    <div class="card main-card">
                        <div class="card-header-purple">
                            <h5 class="mb-0"><i class="fas fa-list-ol mr-2"></i> All Counters</h5>
                            <a href="live_counter_form.php" class="btn-add-new">
                                <i class="fas fa-plus mr-1"></i> Add New Counter
                            </a>
                        </div>
                        
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0" id="countersTable">
                                    <thead>
                                        <tr>
                                            <th style="width: 80px;" class="text-center">Order</th>
                                            <th>Title</th>
                                            <th>Counter Value</th>
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
                                                    <div class="title-text"><?= htmlspecialchars($row['title']) ?></div>
                                                </td>
                                                <td>
                                                    <div class="counter-val-text"><?= htmlspecialchars($row['counter_value']) ?></div>
                                                </td>
                                                <td class="text-center">
                                                    <?php if ($row['is_active']): ?>
                                                        <span class="badge-active">Active</span>
                                                    <?php else: ?>
                                                        <span class="badge-inactive">Inactive</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <div class="d-flex justify-content-center flex-wrap">
                                                        <a href="live_counter_form.php?edit_id=<?= $row['id'] ?>" class="action-btn btn-edit" title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <a href="live_counters.php?delete_id=<?= $row['id'] ?>" class="action-btn btn-delete" title="Delete" onclick="return confirm('Are you sure you want to delete this counter?');">
                                                            <i class="fas fa-trash"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr><td colspan="5" class="text-center py-4">No counters found. Click 'Add New Counter' to create one.</td></tr>
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

        <!-- jQuery UI for Sortable -->
        <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>

        <script>
            $(function() {
                $("#sortable-body").sortable({
                    handle: ".drag-handle",
                    update: function(event, ui) {
                        var orderData = [];
                        $('#sortable-body tr').each(function(index) {
                            var id = $(this).data('id');
                            var order = index + 1;
                            $(this).find('.order-number').text(order);
                            orderData.push({ id: id, order: order });
                        });

                        $.ajax({
                            url: 'live_counters.php',
                            type: 'POST',
                            data: {
                                action: 'update_order',
                                orderData: orderData
                            },
                            success: function(response) {}
                        });
                    }
                });
            });
        </script>

    </div>
</body>
</html>
