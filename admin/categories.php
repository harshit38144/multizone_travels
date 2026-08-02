<?php
session_start();
if ($_SESSION['role'] != '1') {
	header('location:index.php');
	exit;
}
include __DIR__ . '/connection.php';

// Create table if not exists
$table_sql = "CREATE TABLE IF NOT EXISTS `categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `slug` VARCHAR(100) NOT NULL UNIQUE,
  `description` TEXT DEFAULT NULL,
  `image` VARCHAR(255) DEFAULT NULL,
  `display_order` INT NOT NULL DEFAULT 1,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($table_sql);

// Handle Delete
if (isset($_GET['delete_id'])) {
    $id = (int)$_GET['delete_id'];
    $old = $conn->query("SELECT image FROM categories WHERE id=$id")->fetch_assoc();
    if ($old && !empty($old['image']) && file_exists($old['image'])) unlink($old['image']);
    $conn->query("DELETE FROM categories WHERE id=$id");
    $_SESSION['msg'] = "Category deleted successfully!";
    header("Location: categories.php");
    exit;
}

$msg = "";
$msg_type = "success";
if (isset($_SESSION['msg'])) {
    $msg = $_SESSION['msg'];
    $msg_type = isset($_SESSION['msg_type']) ? $_SESSION['msg_type'] : 'success';
    unset($_SESSION['msg'], $_SESSION['msg_type']);
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Category Management</title>
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

        .table th {
            background: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            color: #495057;
            font-size: 13px;
            text-transform: uppercase;
            font-weight: 600;
        }
        .table td { vertical-align: middle; }

        .cat-img {
            width: 80px; height: 50px; object-fit: cover; border-radius: 4px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1); display: block;
        }
        .img-placeholder {
            width: 80px; height: 50px; background: #f0f2f5; border-radius: 4px;
            display: flex; align-items: center; justify-content: center;
            color: #888; font-size: 18px; border: 1px dashed #ccc;
        }

        .slug-text {
            color: #d63384;
            font-family: monospace;
            font-size: 13px;
            background: #f8f9fa;
            padding: 2px 6px;
            border-radius: 4px;
        }

        .order-badge {
            background: #0dcaf0; color: #fff;
            width: 26px; height: 26px; border-radius: 4px;
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 12px; font-weight: bold;
        }

        .badge-active { background: #198754; color: #fff; padding: 4px 12px; border-radius: 4px; font-size: 12px; font-weight: 600; }
        .badge-inactive { background: #6c757d; color: #fff; padding: 4px 12px; border-radius: 4px; font-size: 12px; font-weight: 600; }

        .action-btn {
            width: 32px; height: 32px; border-radius: 4px;
            display: inline-flex; align-items: center; justify-content: center;
            transition: transform 0.2s; border: none; font-size: 13px;
            text-decoration: none; color: #fff;
        }
        .btn-edit { background: #6a11cb; color: #fff; }
        .btn-delete { background: #dc3545; color: #fff; }
        .action-btn:hover { transform: translateY(-2px); color: #fff; opacity: 0.9; }
    </style>
</head>

<body class="hold-transition sidebar-mini layout-fixed page-bg">
    <div class="wrapper">

        <?php include __DIR__ . '/includes/top-header.php'; ?>
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <div class="content-wrapper">
            <!-- Content Header -->
            <div class="content-header">
                <div class="container-fluid">
                    <div class="row mb-2">
                        <div class="col-sm-6">
                            <h1 class="m-0 text-dark"><i class="fas fa-th-large mr-2"></i> Category Management</h1>
                        </div>
                        <div class="col-sm-6">
                            <ol class="breadcrumb float-sm-right">
                                <li class="breadcrumb-item"><a href="dashboard.php" style="color: #6a11cb;">Dashboard</a></li>
                                <li class="breadcrumb-item active">Categories</li>
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
                            <h5 class="mb-0"><i class="fas fa-list mr-2"></i> All Categories</h5>
                            <a href="category_form.php" class="btn-add-new">
                                <i class="fas fa-plus mr-1"></i> Add New Category
                            </a>
                        </div>
                        
                        <div class="card-body">
                            <?php
                            $result = $conn->query("SELECT * FROM categories ORDER BY display_order ASC");
                            ?>
                            <div class="table-responsive">
                                <table id="categoriesTable" class="table table-hover table-bordered mb-0">
                                    <thead>
                                        <tr>
                                            <th style="width: 60px;">ID</th>
                                            <th style="width: 100px;" class="text-center">Image</th>
                                            <th>Name</th>
                                            <th>Slug</th>
                                            <th style="width: 80px;" class="text-center">Order</th>
                                            <th style="width: 100px;" class="text-center">Status</th>
                                            <th style="width: 100px;" class="text-center">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if($result->num_rows > 0): ?>
                                            <?php while ($row = $result->fetch_assoc()): ?>
                                            <tr>
                                                <td><?= $row['id'] ?></td>
                                                <td class="text-center">
                                                    <?php if(!empty($row['image'])): ?>
                                                        <img src="<?= htmlspecialchars($row['image']) ?>" class="cat-img mx-auto" alt="Category">
                                                    <?php else: ?>
                                                        <div class="img-placeholder mx-auto"><i class="fas fa-image"></i></div>
                                                    <?php endif; ?>
                                                </td>
                                                <td style="font-weight: 600; color: #333;"><?= htmlspecialchars($row['name']) ?></td>
                                                <td><span class="slug-text"><?= htmlspecialchars($row['slug']) ?></span></td>
                                                <td class="text-center"><span class="order-badge"><?= $row['display_order'] ?></span></td>
                                                <td class="text-center">
                                                    <?php if ($row['is_active']): ?>
                                                        <span class="badge-active">Active</span>
                                                    <?php else: ?>
                                                        <span class="badge-inactive">Inactive</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <div class="d-flex justify-content-center" style="gap:5px;">
                                                        <a href="category_form.php?edit_id=<?= $row['id'] ?>" class="action-btn btn-edit" title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <a href="categories.php?delete_id=<?= $row['id'] ?>" class="action-btn btn-delete" title="Delete" onclick="return confirm('Are you sure you want to delete this category?');">
                                                            <i class="fas fa-trash"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endwhile; ?>
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

        <script>
            $(function() {
                if ($('#categoriesTable').length) {
                    $('#categoriesTable').DataTable({ 
                        "order": [], // Keep original display_order
                        "pageLength": 10,
                        "columnDefs": [
                            { "orderable": false, "targets": [1, 6] }
                        ]
                    });
                }
            });
        </script>

    </div>
</body>
</html>
