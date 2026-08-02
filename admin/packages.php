<?php
session_start();
if ($_SESSION['role'] != '1') {
	header('location:index.php');
	exit;
}
include __DIR__ . '/connection.php';

// Initialize Tables
$conn->query("CREATE TABLE IF NOT EXISTS `packages` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(255) NOT NULL UNIQUE,
  `duration_nights` INT DEFAULT 0,
  `duration_days` INT DEFAULT 0,
  `original_price` DECIMAL(10,2) DEFAULT 0,
  `sale_price` DECIMAL(10,2) DEFAULT 0,
  `group_size_min` INT DEFAULT 1,
  `group_size_max` INT DEFAULT 10,
  `featured_image` VARCHAR(255) DEFAULT NULL,
  `highlights` LONGTEXT DEFAULT NULL,
  `inclusions` LONGTEXT DEFAULT NULL,
  `exclusions` LONGTEXT DEFAULT NULL,
  `meta_title` VARCHAR(255) DEFAULT NULL,
  `meta_description` TEXT DEFAULT NULL,
  `status` ENUM('Draft', 'Published', 'Archived') DEFAULT 'Draft',
  `is_featured` TINYINT(1) DEFAULT 0,
  `is_trending` TINYINT(1) DEFAULT 0,
  `views` INT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS `package_category_map` (
  `package_id` INT,
  `category_id` INT,
  PRIMARY KEY (`package_id`, `category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS `package_destination_map` (
  `package_id` INT,
  `destination_id` INT,
  PRIMARY KEY (`package_id`, `destination_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS `package_itineraries` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `package_id` INT,
  `day_number` INT,
  `title` VARCHAR(255),
  `description` TEXT,
  `meals` VARCHAR(255),
  `accommodation` VARCHAR(255),
  `activities` VARCHAR(255),
  KEY `package_id` (`package_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Handle Delete
if (isset($_GET['delete_id'])) {
    $id = (int)$_GET['delete_id'];
    $old = $conn->query("SELECT featured_image FROM packages WHERE id=$id")->fetch_assoc();
    if ($old && !empty($old['featured_image']) && file_exists($old['featured_image'])) unlink($old['featured_image']);
    
    // Delete relations
    $conn->query("DELETE FROM package_category_map WHERE package_id=$id");
    $conn->query("DELETE FROM package_destination_map WHERE package_id=$id");
    $conn->query("DELETE FROM package_itineraries WHERE package_id=$id");
    // Delete package
    $conn->query("DELETE FROM packages WHERE id=$id");
    
    $_SESSION['msg'] = "Package deleted successfully!";
    header("Location: packages.php");
    exit;
}

$msg = "";
$msg_type = "success";
if (isset($_SESSION['msg'])) {
    $msg = $_SESSION['msg'];
    $msg_type = isset($_SESSION['msg_type']) ? $_SESSION['msg_type'] : 'success';
    unset($_SESSION['msg'], $_SESSION['msg_type']);
}

// Handle Filters
$filter_category = isset($_GET['category']) ? $_GET['category'] : '';
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';

$where_clause = "1=1";
if (!empty($filter_status)) {
    $where_clause .= " AND p.status = '" . mysqli_real_escape_string($conn, $filter_status) . "'";
}
if (!empty($filter_category)) {
    $cat_id = (int)$filter_category;
    $where_clause .= " AND p.id IN (SELECT package_id FROM package_category_map WHERE category_id = $cat_id)";
}

// Fetch all categories for filter dropdown
$cats_query = $conn->query("SELECT id, name FROM categories ORDER BY name ASC");

// Fetch Packages with relations
$sql = "SELECT p.*, 
        (SELECT GROUP_CONCAT(c.name SEPARATOR '||') FROM categories c JOIN package_category_map pcm ON c.id = pcm.category_id WHERE pcm.package_id = p.id) as cat_names,
        (SELECT GROUP_CONCAT(d.name SEPARATOR '||') FROM destinations d JOIN package_destination_map pdm ON d.id = pdm.destination_id WHERE pdm.package_id = p.id) as dest_names
        FROM packages p 
        WHERE $where_clause 
        ORDER BY p.id DESC";
$result = $conn->query($sql);
$total_packages = $result ? $result->num_rows : 0;
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Manage Packages</title>
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

        .filter-card {
            background: #fdfdfd;
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
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

        .table th { background: #f8f9fa; border-bottom: 2px solid #dee2e6; color: #495057; font-size: 13px; text-transform: uppercase; font-weight: 600; }
        .table td { vertical-align: middle; }

        .pkg-img { width: 60px; height: 60px; object-fit: cover; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .img-placeholder { width: 60px; height: 60px; background: #f0f2f5; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #888; border: 1px dashed #ccc; }

        .badge-cat { background: #0d6efd; color: #fff; font-size: 11px; padding: 3px 8px; border-radius: 4px; display: inline-block; margin: 2px; }
        .badge-dest { background: #0dcaf0; color: #000; font-size: 11px; padding: 3px 8px; border-radius: 4px; display: inline-block; margin: 2px; }
        .badge-trending { background: #dc3545; color: #fff; font-size: 10px; padding: 2px 6px; border-radius: 4px; font-weight: 600; display: inline-block; margin-top: 4px; }
        .badge-discount { background: #ffc107; color: #000; font-size: 10px; padding: 2px 6px; border-radius: 4px; font-weight: 600; display: inline-block; margin-top: 2px; }

        .status-badge { font-size: 11px; padding: 4px 10px; border-radius: 4px; font-weight: 600; }
        .status-published { background: #198754; color: #fff; }
        .status-draft { background: #6c757d; color: #fff; }
        
        .price-orig { text-decoration: line-through; color: #888; font-size: 12px; display: block; }
        .price-sale { color: #198754; font-weight: 700; font-size: 14px; display: block; }

        .action-btn { width: 28px; height: 28px; border-radius: 4px; display: inline-flex; align-items: center; justify-content: center; transition: transform 0.2s; border: none; font-size: 12px; text-decoration: none; color: #fff; margin: 1px; }
        .btn-edit { background: #ffc107; color: #000; }
        .btn-clone { background: #0dcaf0; color: #fff; }
        .btn-delete { background: #dc3545; color: #fff; }
        .action-btn:hover { transform: translateY(-2px); opacity: 0.9; color: inherit; }
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
                            <h1 class="m-0 text-dark"><i class="fas fa-suitcase mr-2"></i> Manage Packages</h1>
                        </div>
                        <div class="col-sm-6">
                            <ol class="breadcrumb float-sm-right">
                                <li class="breadcrumb-item"><a href="dashboard.php" style="color: #6a11cb;">Dashboard</a></li>
                                <li class="breadcrumb-item active">Packages</li>
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

                    <!-- Filters -->
                    <div class="filter-card">
                        <form method="GET" action="">
                            <div class="row align-items-end">
                                <div class="col-md-3">
                                    <label class="font-weight-normal text-muted mb-1" style="font-size:13px;">Filter by Category</label>
                                    <select name="category" class="form-control form-control-sm">
                                        <option value="">All Categories</option>
                                        <?php while($c = $cats_query->fetch_assoc()): ?>
                                            <option value="<?= $c['id'] ?>" <?= ($filter_category == $c['id']) ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="font-weight-normal text-muted mb-1" style="font-size:13px;">Filter by Status</label>
                                    <select name="status" class="form-control form-control-sm">
                                        <option value="">All Status</option>
                                        <option value="Published" <?= ($filter_status == 'Published') ? 'selected' : '' ?>>Published</option>
                                        <option value="Draft" <?= ($filter_status == 'Draft') ? 'selected' : '' ?>>Draft</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <button type="submit" class="btn btn-sm text-white" style="background:#6a11cb;"><i class="fas fa-filter mr-1"></i> Filter</button>
                                    <a href="packages.php" class="btn btn-sm btn-secondary"><i class="fas fa-redo mr-1"></i> Reset</a>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div class="card main-card">
                        <div class="card-header-purple">
                            <h5 class="mb-0"><i class="fas fa-list mr-2"></i> All Packages (<?= $total_packages ?>)</h5>
                            <a href="package_form.php" class="btn-add-new">
                                <i class="fas fa-plus mr-1"></i> Add New Package
                            </a>
                        </div>
                        
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="packagesTable" class="table table-hover table-bordered mb-0">
                                    <thead>
                                        <tr>
                                            <th style="width: 70px;" class="text-center">Image</th>
                                            <th>Title</th>
                                            <th style="width: 15%;">Category</th>
                                            <th style="width: 15%;">Destination</th>
                                            <th style="width: 80px;" class="text-center">Duration</th>
                                            <th style="width: 100px;">Price</th>
                                            <th style="width: 80px;" class="text-center">Status</th>
                                            <th style="width: 60px;" class="text-center">Featured</th>
                                            <th style="width: 60px;" class="text-center">Views</th>
                                            <th style="width: 110px;" class="text-center">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if($result && $result->num_rows > 0): ?>
                                            <?php while ($row = $result->fetch_assoc()): ?>
                                            <tr>
                                                <td class="text-center">
                                                    <?php if(!empty($row['featured_image'])): ?>
                                                        <img src="<?= htmlspecialchars($row['featured_image']) ?>" class="pkg-img mx-auto">
                                                    <?php else: ?>
                                                        <div class="img-placeholder mx-auto"><i class="fas fa-image"></i></div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <strong style="color: #333;"><?= htmlspecialchars($row['title']) ?></strong><br>
                                                    <?php if ($row['is_trending']): ?>
                                                        <span class="badge-trending">Trending</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php 
                                                        if(!empty($row['cat_names'])){
                                                            $cats = explode('||', $row['cat_names']);
                                                            foreach($cats as $c) echo "<span class='badge-cat'>".htmlspecialchars($c)."</span>";
                                                        }
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php 
                                                        if(!empty($row['dest_names'])){
                                                            $dests = explode('||', $row['dest_names']);
                                                            foreach($dests as $d) echo "<span class='badge-dest'>".htmlspecialchars($d)."</span>";
                                                        }
                                                    ?>
                                                </td>
                                                <td class="text-center" style="font-weight:600; color:#555;">
                                                    <?= $row['duration_nights'] ?>N / <?= $row['duration_days'] ?>D
                                                </td>
                                                <td>
                                                    <span class="price-orig">Rs. <?= number_format($row['original_price'], 0) ?></span>
                                                    <span class="price-sale">Rs. <?= number_format($row['sale_price'], 0) ?></span>
                                                    <?php if($row['original_price'] > 0 && $row['sale_price'] < $row['original_price']): 
                                                        $disc = round((($row['original_price'] - $row['sale_price']) / $row['original_price']) * 100);
                                                    ?>
                                                        <span class="badge-discount"><?= $disc ?>% OFF</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <?php if ($row['status'] == 'Published'): ?>
                                                        <span class="status-badge status-published">Published</span>
                                                    <?php else: ?>
                                                        <span class="status-badge status-draft">Draft</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <?php if ($row['is_featured']): ?>
                                                        <i class="fas fa-star text-warning"></i>
                                                    <?php else: ?>
                                                        <i class="far fa-star text-muted"></i>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <i class="fas fa-eye text-muted mr-1"></i> <?= $row['views'] ?>
                                                </td>
                                                <td class="text-center">
                                                    <div class="d-flex justify-content-center flex-wrap">
                                                        <a href="package_form.php?edit_id=<?= $row['id'] ?>" class="action-btn btn-edit" title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <!-- Clone not fully requested, redirecting to edit for now as a placeholder or could just duplicate row in DB -->
                                                        <a href="package_form.php?clone_id=<?= $row['id'] ?>" class="action-btn btn-clone" title="Clone" onclick="return confirm('Clone this package?');">
                                                            <i class="fas fa-copy"></i>
                                                        </a>
                                                        <a href="packages.php?delete_id=<?= $row['id'] ?>" class="action-btn btn-delete" title="Delete" onclick="return confirm('Are you sure you want to delete this package?');">
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
                if ($('#packagesTable').length) {
                    $('#packagesTable').DataTable({ 
                        "order": [[ 1, "asc" ]],
                        "pageLength": 10,
                        "columnDefs": [
                            { "orderable": false, "targets": [0, 9] }
                        ]
                    });
                }
            });
        </script>

    </div>
</body>
</html>
