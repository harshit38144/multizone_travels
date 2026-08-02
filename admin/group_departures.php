<?php
session_start();
if ($_SESSION['role'] != '1') {
	header('location:index.php');
	exit;
}
include __DIR__ . '/connection.php';

// Initialize Tables
$conn->query("CREATE TABLE IF NOT EXISTS `group_departures` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `destination_cities` VARCHAR(255) DEFAULT NULL,
  `city_nights_breakdown` VARCHAR(255) DEFAULT NULL,
  `ex_city` VARCHAR(255) DEFAULT NULL,
  `departure_day` VARCHAR(50) DEFAULT NULL,
  `departure_months` VARCHAR(255) DEFAULT NULL,
  `duration_nights` INT DEFAULT 0,
  `duration_days` INT DEFAULT 0,
  `total_seats` INT DEFAULT 0,
  `seats_available` INT DEFAULT 0,
  `max_group_size` INT DEFAULT 0,
  `star_rating` VARCHAR(50) DEFAULT NULL,
  `price` DECIMAL(10,2) DEFAULT 0,
  `discounted_price` DECIMAL(10,2) DEFAULT 0,
  `operator_brand` VARCHAR(255) DEFAULT NULL,
  `guide_languages` VARCHAR(255) DEFAULT NULL,
  `experiences` VARCHAR(255) DEFAULT NULL,
  `is_flight_included` TINYINT(1) DEFAULT 0,
  `is_group_package` TINYINT(1) DEFAULT 0,
  `is_fixed_package` TINYINT(1) DEFAULT 0,
  `is_meals_included` TINYINT(1) DEFAULT 0,
  `onward_flight_name` VARCHAR(255) DEFAULT NULL,
  `onward_route` VARCHAR(255) DEFAULT NULL,
  `return_flight_name` VARCHAR(255) DEFAULT NULL,
  `return_route` VARCHAR(255) DEFAULT NULL,
  `highlights` LONGTEXT DEFAULT NULL,
  `inclusions` LONGTEXT DEFAULT NULL,
  `exclusions` LONGTEXT DEFAULT NULL,
  `featured_image` VARCHAR(255) DEFAULT NULL,
  `gallery_images` LONGTEXT DEFAULT NULL,
  `status` ENUM('Draft', 'Published') DEFAULT 'Draft',
  `is_featured` TINYINT(1) DEFAULT 0,
  `is_newly_launched` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS `group_departure_dates` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `group_departure_id` INT NOT NULL,
  `departure_date` DATE NOT NULL,
  KEY `group_departure_id` (`group_departure_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS `group_departure_hotels` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `group_departure_id` INT NOT NULL,
  `city` VARCHAR(255),
  `nights` INT DEFAULT 0,
  `hotel_name` VARCHAR(255),
  `room_type` VARCHAR(255),
  `meal_plan` VARCHAR(255),
  KEY `group_departure_id` (`group_departure_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Handle Delete
if (isset($_GET['delete_id'])) {
    $id = (int)$_GET['delete_id'];
    
    // Unlink images
    $old = $conn->query("SELECT featured_image, gallery_images FROM group_departures WHERE id=$id")->fetch_assoc();
    if ($old) {
        if (!empty($old['featured_image']) && file_exists($old['featured_image'])) {
            unlink($old['featured_image']);
        }
        if (!empty($old['gallery_images'])) {
            $gallery = json_decode($old['gallery_images'], true);
            if (is_array($gallery)) {
                foreach ($gallery as $img) {
                    if (file_exists($img)) unlink($img);
                }
            }
        }
    }
    
    // Delete relations and record
    $conn->query("DELETE FROM group_departure_dates WHERE group_departure_id=$id");
    $conn->query("DELETE FROM group_departure_hotels WHERE group_departure_id=$id");
    $conn->query("DELETE FROM group_departures WHERE id=$id");
    
    $_SESSION['msg'] = "Departure deleted successfully!";
    header("Location: group_departures.php");
    exit;
}

$msg = "";
$msg_type = "success";
if (isset($_SESSION['msg'])) {
    $msg = $_SESSION['msg'];
    $msg_type = isset($_SESSION['msg_type']) ? $_SESSION['msg_type'] : 'success';
    unset($_SESSION['msg'], $_SESSION['msg_type']);
}

// Stats Calculation
$total_deps = $conn->query("SELECT COUNT(id) as c FROM group_departures")->fetch_assoc()['c'];
$pub_deps = $conn->query("SELECT COUNT(id) as c FROM group_departures WHERE status='Published'")->fetch_assoc()['c'];
$draft_deps = $conn->query("SELECT COUNT(id) as c FROM group_departures WHERE status='Draft'")->fetch_assoc()['c'];

// Upcoming Calculation (Departures with a date in the future)
$upcoming_deps = $conn->query("
    SELECT COUNT(DISTINCT g.id) as c 
    FROM group_departures g
    JOIN group_departure_dates d ON g.id = d.group_departure_id
    WHERE d.departure_date >= CURDATE() AND g.status='Published'
")->fetch_assoc()['c'];

// Handle Filter
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$where_clause = "1=1";
if (!empty($filter_status)) {
    $where_clause .= " AND g.status = '" . mysqli_real_escape_string($conn, $filter_status) . "'";
}

// Fetch Departures with upcoming date logic
$sql = "SELECT g.*, 
        (SELECT MIN(departure_date) FROM group_departure_dates WHERE group_departure_id = g.id AND departure_date >= CURDATE()) as next_date,
        (SELECT MIN(departure_date) FROM group_departure_dates WHERE group_departure_id = g.id) as any_date
        FROM group_departures g 
        WHERE $where_clause 
        ORDER BY g.id DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Manage Group Departures</title>
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
        
        .main-card { border: none; border-radius: 12px; box-shadow: 0 2px 15px rgba(0,0,0,0.05); background: #fff; margin-bottom: 30px; }
        .filter-card { background: #fdfdfd; border: 1px solid #eee; border-radius: 8px; padding: 15px; margin-bottom: 20px; }
        
        .btn-add-new { background: #6a11cb; color: #fff; border: none; border-radius: 6px; padding: 6px 16px; font-weight: 600; font-size: 14px; transition: all 0.2s; }
        .btn-add-new:hover { background: #5a0fb0; color: #fff; }

        .table th { background: #f8f9fa; border-bottom: 2px solid #dee2e6; color: #495057; font-size: 13px; text-transform: uppercase; font-weight: 600; }
        .table td { vertical-align: middle; }

        .img-placeholder { width: 60px; height: 60px; background: #f0f2f5; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #888; border: 1px dashed #ccc; }
        .feat-img { width: 60px; height: 60px; object-fit: cover; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }

        .badge-new { background: #198754; color: #fff; font-size: 10px; padding: 2px 6px; border-radius: 4px; font-weight: 600; display: inline-block; margin-left: 5px; }
        .badge-featured { background: #ffc107; color: #000; font-size: 10px; padding: 2px 6px; border-radius: 4px; font-weight: 600; display: inline-block; margin-top: 4px; }
        
        .status-badge { font-size: 11px; padding: 4px 10px; border-radius: 4px; font-weight: 600; }
        .status-published { background: #198754; color: #fff; }
        .status-draft { background: #ffc107; color: #000; }
        
        .price-orig { text-decoration: line-through; color: #888; font-size: 12px; display: block; }
        .price-sale { color: #333; font-weight: 700; font-size: 14px; display: block; }
        
        .seats-high { color: #198754; font-weight: bold; }
        .seats-low { color: #dc3545; font-weight: bold; }

        .action-btn { width: 28px; height: 28px; border-radius: 4px; display: inline-flex; align-items: center; justify-content: center; transition: transform 0.2s; border: none; font-size: 12px; text-decoration: none; color: #fff; margin: 1px; }
        .btn-edit { background: #0dcaf0; color: #fff; border: 1px solid #0dcaf0; background: transparent; color: #0dcaf0; }
        .btn-edit:hover { background: #0dcaf0; color: #fff; }
        .btn-clone { background: transparent; border: 1px solid #198754; color: #198754; }
        .btn-clone:hover { background: #198754; color: #fff; }
        .btn-delete { background: transparent; border: 1px solid #dc3545; color: #dc3545; }
        .btn-delete:hover { background: #dc3545; color: #fff; }
        
        /* Dashboard Stats */
        .stat-block { border-top: 6px solid #ccc; padding: 15px; background: #fff; border-radius: 6px; box-shadow: 0 2px 10px rgba(0,0,0,0.03); margin-bottom: 20px;}
        .stat-blue { border-top-color: #0d6efd; }
        .stat-green { border-top-color: #198754; }
        .stat-yellow { border-top-color: #ffc107; }
        .stat-teal { border-top-color: #20c997; }
        .stat-val { font-size: 28px; font-weight: 300; margin-bottom: 0; color: #333;}
        .stat-label { font-size: 13px; color: #666; font-weight: 500;}
        .stat-icon { position: absolute; right: 25px; top: 25px; font-size: 24px; color: #e9ecef; }
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
                            <h1 class="m-0 text-dark"><i class="fas fa-users mr-2"></i> Manage Group Departures</h1>
                        </div>
                        <div class="col-sm-6 text-right">
                            <ol class="breadcrumb float-sm-right d-inline-block mr-3 bg-transparent p-0 m-0 align-middle">
                                <li class="breadcrumb-item"><a href="dashboard.php" style="color: #6a11cb;">Dashboard</a></li>
                                <li class="breadcrumb-item active">Group Departures</li>
                            </ol>
                            <a href="group_departure_form.php" class="btn-add-new d-inline-block align-middle">
                                <i class="fas fa-plus mr-1"></i> Add New Departure
                            </a>
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
                    
                    <!-- Top Stats -->
                    <div class="row">
                        <div class="col-md-3 col-sm-6">
                            <div class="stat-block stat-blue">
                                <i class="fas fa-users stat-icon"></i>
                                <h3 class="stat-val"><?= $total_deps ?></h3>
                                <div class="stat-label">Total Departures</div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="stat-block stat-green">
                                <i class="fas fa-check-circle stat-icon"></i>
                                <h3 class="stat-val"><?= $pub_deps ?></h3>
                                <div class="stat-label">Published</div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="stat-block stat-yellow">
                                <i class="fas fa-edit stat-icon"></i>
                                <h3 class="stat-val"><?= $draft_deps ?></h3>
                                <div class="stat-label">Drafts</div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="stat-block stat-teal">
                                <i class="fas fa-calendar-alt stat-icon"></i>
                                <h3 class="stat-val"><?= $upcoming_deps ?></h3>
                                <div class="stat-label">Upcoming</div>
                            </div>
                        </div>
                    </div>

                    <!-- Filters -->
                    <div class="filter-card">
                        <form method="GET" action="">
                            <div class="row align-items-end">
                                <div class="col-md-3">
                                    <label class="font-weight-normal text-muted mb-1" style="font-size:13px;">Status</label>
                                    <select name="status" class="form-control form-control-sm">
                                        <option value="">All Statuses</option>
                                        <option value="Published" <?= ($filter_status == 'Published') ? 'selected' : '' ?>>Published</option>
                                        <option value="Draft" <?= ($filter_status == 'Draft') ? 'selected' : '' ?>>Draft</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <button type="submit" class="btn btn-sm btn-outline-primary"><i class="fas fa-filter mr-1"></i> Filter</button>
                                    <a href="group_departures.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-times mr-1"></i> Reset</a>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div class="card main-card">
                        <div class="card-body p-0">
                            <div class="table-responsive p-3">
                                <table id="departuresTable" class="table table-hover table-borderless align-middle mb-0" style="border-bottom: 1px solid #eee;">
                                    <thead>
                                        <tr style="border-bottom: 2px solid #eee;">
                                            <th style="width: 70px;" class="text-center">Image</th>
                                            <th>Title</th>
                                            <th style="width: 20%;">Departure</th>
                                            <th style="width: 80px;" class="text-center">Duration</th>
                                            <th style="width: 80px;" class="text-center">Seats</th>
                                            <th style="width: 100px;">Price</th>
                                            <th style="width: 80px;" class="text-center">Status</th>
                                            <th style="width: 100px;" class="text-center">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if($result && $result->num_rows > 0): ?>
                                            <?php while ($row = $result->fetch_assoc()): ?>
                                            <tr style="border-bottom: 1px solid #eee;">
                                                <td class="text-center">
                                                    <?php if(!empty($row['featured_image'])): ?>
                                                        <img src="<?= htmlspecialchars($row['featured_image']) ?>" class="feat-img mx-auto">
                                                    <?php else: ?>
                                                        <div class="img-placeholder mx-auto"><i class="fas fa-image"></i></div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <strong style="color: #333; font-size:15px;"><?= htmlspecialchars($row['title']) ?></strong>
                                                    <?php if ($row['is_newly_launched']): ?>
                                                        <span class="badge-new">New</span>
                                                    <?php endif; ?>
                                                    <br>
                                                    <small class="text-muted"><?= htmlspecialchars($row['destination_cities']) ?></small>
                                                    <br>
                                                    <?php if ($row['is_featured']): ?>
                                                        <span class="badge-featured">Featured</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php 
                                                        $disp_date = $row['next_date'] ? $row['next_date'] : $row['any_date'];
                                                        if ($disp_date) {
                                                            echo "<strong style='color:#333;'>" . date('d M Y', strtotime($disp_date)) . "</strong><br>";
                                                        } else {
                                                            echo "<span class='text-muted'>No dates set</span><br>";
                                                        }
                                                    ?>
                                                    <small class="text-muted">Ex-<?= htmlspecialchars($row['ex_city']) ?></small>
                                                </td>
                                                <td class="text-center" style="font-weight:600; color:#555; font-size:13px;">
                                                    <?= $row['duration_nights'] ?>N / <?= $row['duration_days'] ?>D
                                                </td>
                                                <td class="text-center">
                                                    <?php 
                                                        $avail = (int)$row['seats_available'];
                                                        $tot = (int)$row['total_seats'];
                                                        $seat_class = ($avail <= ($tot * 0.3) && $avail != 0) ? 'seats-low' : 'seats-high';
                                                    ?>
                                                    <span class="<?= $seat_class ?>"><?= $avail ?></span> / <span class="text-muted"><?= $tot ?></span>
                                                </td>
                                                <td>
                                                    <?php if($row['price'] > 0 && $row['price'] != $row['discounted_price']): ?>
                                                        <span class="price-orig">Rs. <?= number_format($row['price'], 0) ?></span>
                                                    <?php endif; ?>
                                                    <span class="price-sale">Rs. <?= number_format($row['discounted_price'], 0) ?></span>
                                                </td>
                                                <td class="text-center">
                                                    <?php if ($row['status'] == 'Published'): ?>
                                                        <span class="status-badge status-published">Published</span>
                                                    <?php else: ?>
                                                        <span class="status-badge status-draft">Draft</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <div class="d-flex justify-content-center flex-wrap" style="gap:4px;">
                                                        <a href="group_departure_form.php?edit_id=<?= $row['id'] ?>" class="action-btn btn-edit" title="Edit"><i class="fas fa-edit"></i></a>
                                                        <a href="group_departure_form.php?clone_id=<?= $row['id'] ?>" class="action-btn btn-clone" title="Clone"><i class="far fa-copy"></i></a>
                                                        <a href="group_departures.php?delete_id=<?= $row['id'] ?>" class="action-btn btn-delete" title="Delete" onclick="return confirm('Are you sure you want to delete this departure?');"><i class="fas fa-trash-alt"></i></a>
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
                if ($('#departuresTable').length) {
                    $('#departuresTable').DataTable({ 
                        "order": [[ 1, "asc" ]],
                        "pageLength": 25,
                        "columnDefs": [
                            { "orderable": false, "targets": [0, 7] }
                        ],
                        "dom": '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rt<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
                        "language": {
                            "lengthMenu": "Show _MENU_ entries"
                        }
                    });
                }
            });
        </script>

    </div>
</body>
</html>
