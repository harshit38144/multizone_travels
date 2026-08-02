<?php
session_start();
if ($_SESSION['role'] != '1') {
	header('location:index.php');
	exit;
}
include __DIR__ . '/connection.php';

// Create table if not exists
$conn->query("CREATE TABLE IF NOT EXISTS `sliders` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `media_type` ENUM('video','image') NOT NULL DEFAULT 'image',
  `media_file` VARCHAR(255) DEFAULT NULL,
  `overlay_opacity` INT NOT NULL DEFAULT 40,
  `display_order` INT NOT NULL DEFAULT 1,
  `heading` VARCHAR(500) DEFAULT NULL,
  `subheading` VARCHAR(500) DEFAULT NULL,
  `button_text` VARCHAR(100) DEFAULT NULL,
  `button_link` VARCHAR(255) DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$msg = "";
$msg_type = "success";

// Handle Toggle Status
if (isset($_GET['toggle_id'])) {
    $id = (int)$_GET['toggle_id'];
    $conn->query("UPDATE sliders SET is_active = IF(is_active=1, 0, 1) WHERE id=$id");
    $_SESSION['msg'] = "Slider status updated!";
    header("Location: sliders.php");
    exit;
}

// Handle Delete
if (isset($_GET['delete_id'])) {
    $id = (int)$_GET['delete_id'];
    $old = $conn->query("SELECT media_file FROM sliders WHERE id=$id")->fetch_assoc();
    if ($old && !empty($old['media_file']) && file_exists($old['media_file'])) unlink($old['media_file']);
    $conn->query("DELETE FROM sliders WHERE id=$id");
    $_SESSION['msg'] = "Slider deleted successfully!";
    header("Location: sliders.php");
    exit;
}

// Session messages
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
    <title>Manage Sliders</title>
    <?php include __DIR__ . '/includes/header-links.php'; ?>

    <style>
        .btn-add-slider {
            background: linear-gradient(135deg, #6a11cb, #a855f7);
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 8px 20px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-add-slider:hover { opacity: 0.9; color: #fff; transform: translateY(-1px); }

        .slider-table .badge-active {
            background: #d4edda; color: #155724;
            padding: 4px 14px; border-radius: 20px; font-size: 12px; font-weight: 600;
        }
        .slider-table .badge-inactive {
            background: #f8d7da; color: #721c24;
            padding: 4px 14px; border-radius: 20px; font-size: 12px; font-weight: 600;
        }

        .action-btn {
            width: 34px; height: 34px; border-radius: 50%;
            display: inline-flex; align-items: center; justify-content: center;
            transition: transform 0.2s; border: none; font-size: 13px;
            text-decoration: none;
        }
        .action-btn:hover { transform: scale(1.1); text-decoration: none; }

        .slider-thumb-cell img, .slider-thumb-cell video {
            width: 100px; height: 60px; object-fit: cover; border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            background: #000;
            cursor: pointer;
            transition: opacity 0.2s;
        }
        .slider-thumb-cell img:hover, .slider-thumb-cell video:hover { opacity: 0.8; }

        .slider-thumb-cell .thumb-wrap {
            position: relative; display: inline-block;
        }
        .slider-thumb-cell .play-icon {
            position: absolute; top: 50%; left: 50%; transform: translate(-50%,-50%);
            color: #fff; font-size: 18px; pointer-events: none;
            text-shadow: 0 1px 4px rgba(0,0,0,0.6);
        }

        #mediaModal .modal-content {
            background: #000; border: none; border-radius: 12px; overflow: hidden;
        }
        #mediaModal .modal-header {
            border: none; position: absolute; top: 0; right: 0; z-index: 10;
        }
        #mediaModal .close { color: #fff; opacity: 1; font-size: 28px; text-shadow: none; padding: 10px 16px; }
        #mediaModal video, #mediaModal img {
            width: 100%; max-height: 80vh; object-fit: contain;
        }

        .slider-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        .slider-card .card-header {
            background: #fff;
            border-bottom: 1px solid #eee;
            padding: 16px 24px;
        }

        .table thead th {
            background: #f8f9fc;
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #555;
            border-bottom: 2px solid #eee;
            white-space: nowrap;
        }
        .table td { vertical-align: middle; }

        .heading-cell {
            max-width: 250px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #aaa;
        }
        .empty-state i { font-size: 48px; margin-bottom: 16px; display: block; color: #ddd; }

        /* Responsive */
        @media (max-width: 768px) {
            .page-header-flex { flex-direction: column; align-items: flex-start !important; gap: 10px; }
            .btn-add-slider { width: 100%; text-align: center; }
            .slider-thumb-cell img, .slider-thumb-cell video { width: 70px; height: 45px; }
            .heading-cell { max-width: 120px; }
        }
        @media (max-width: 576px) {
            .container-fluid { padding-left: 10px; padding-right: 10px; }
            .slider-thumb-cell img, .slider-thumb-cell video { width: 55px; height: 35px; }
        }
    </style>
</head>

<body class="hold-transition sidebar-mini layout-fixed">
    <div class="wrapper">

        <?php include __DIR__ . '/includes/top-header.php'; ?>
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <div class="content-wrapper">
            <?php include __DIR__ . '/includes/page-header.php'; ?>

            <section class="content">
                <div class="container-fluid">

                    <?php if (!empty($msg)) { ?>
                        <div class="alert alert-<?= $msg_type ?> alert-dismissible fade show">
                            <?= $msg; ?>
                            <button type="button" class="close" data-dismiss="alert">&times;</button>
                        </div>
                    <?php } ?>

                    <!-- Header -->
                    <div class="d-flex justify-content-between align-items-center mb-3 page-header-flex">
                        <h5 class="mb-0" style="font-weight:600; color:#333;">
                            <i class="fas fa-images mr-2" style="color:#6a11cb;"></i> Manage Sliders <span style="font-size:12px; color:#666;">1265*388 px</span>
                        </h5>
                        <a href="slider_form.php" class="btn btn-add-slider">
                            <i class="fas fa-plus mr-1"></i> Add New Slider
                        </a>
                    </div>

                    <!-- Sliders Table -->
                    <div class="card slider-card">
                        <div class="card-body p-0 table-responsive">
                            <?php
                            $result = $conn->query("SELECT * FROM sliders ORDER BY display_order ASC");
                            if ($result->num_rows > 0):
                            ?>
                            <table id="slidersTable" class="table table-hover slider-table mb-0">
                                <thead>
                                    <tr>
                                        <th style="width:50px">#</th>
                                        <th style="width:120px">Preview</th>
                                        <th style="width:80px">Type</th>
                                        <th>Heading</th>
                                        <th style="width:80px">Order</th>
                                        <th style="width:90px">Opacity</th>
                                        <th style="width:90px">Status</th>
                                        <th style="width:110px">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $sn = 1;
                                    while ($row = $result->fetch_assoc()) {
                                    ?>
                                        <tr>
                                            <td><?= $sn++; ?></td>
                                            <td class="slider-thumb-cell">
                                                <?php if (!empty($row['media_file'])): ?>
                                                    <?php if ($row['media_type'] == 'video'): ?>
                                                        <span class="thumb-wrap btn-preview-media" data-type="video" data-src="<?= $row['media_file'] ?>">
                                                            <video muted preload="metadata"><source src="<?= $row['media_file'] ?>#t=0.5"></video>
                                                            <i class="fas fa-play-circle play-icon"></i>
                                                        </span>
                                                    <?php else: ?>
                                                        <img src="<?= $row['media_file'] ?>" alt="" class="btn-preview-media" data-type="image" data-src="<?= $row['media_file'] ?>">
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="text-muted"><i class="fas fa-image fa-2x"></i></span>
                                                <?php endif; ?>
                                            </td>
                                            <td><span class="text-capitalize"><?= $row['media_type'] ?></span></td>
                                            <td class="heading-cell"><?= htmlspecialchars(strip_tags($row['heading'])) ?></td>
                                            <td class="text-center"><?= $row['display_order'] ?></td>
                                            <td class="text-center"><?= $row['overlay_opacity'] ?>%</td>
                                            <td>
                                                <a href="sliders.php?toggle_id=<?= $row['id'] ?>" title="Click to toggle status" style="text-decoration:none;">
                                                    <?php if ($row['is_active']): ?>
                                                        <span class="badge-active" style="cursor:pointer;">Active</span>
                                                    <?php else: ?>
                                                        <span class="badge-inactive" style="cursor:pointer;">Inactive</span>
                                                    <?php endif; ?>
                                                </a>
                                            </td>
                                            <td>
                                                <div class="d-flex" style="gap:6px;">
                                                    <a href="slider_form.php?edit_id=<?= $row['id'] ?>"
                                                       class="action-btn" style="background:#d9efe1;color:green;" title="Edit">
                                                        <i class="fas fa-pen"></i>
                                                    </a>
                                                    <a href="sliders.php?delete_id=<?= $row['id'] ?>"
                                                       class="action-btn" style="background:#ffebee;color:#b71c1c;" title="Delete"
                                                       onclick="return confirm('Are you sure you want to delete this slider?');">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-images"></i>
                                    <h5>No Sliders Yet</h5>
                                    <p>Click "Add New Slider" to create your first homepage slider.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>
            </section>
        </div>

        <!-- Media Preview Modal -->
        <div class="modal fade" id="mediaModal" tabindex="-1">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                    </div>
                    <div class="modal-body p-0" id="mediaModalBody"></div>
                </div>
            </div>
        </div>

        <?php include __DIR__ . '/includes/footer-links.php'; ?>

        <script>
            $(function() {
                if ($('#slidersTable').length) {
                    $('#slidersTable').DataTable({ "order": [], "pageLength": 10 });
                }

                // Open media in popup
                $(document).on('click', '.btn-preview-media', function() {
                    var type = $(this).data('type');
                    var src = $(this).data('src');
                    var body = $('#mediaModalBody');
                    body.empty();
                    if (type === 'video') {
                        body.html('<video controls autoplay style="width:100%"><source src="' + src + '" type="video/mp4">Your browser does not support video.</video>');
                    } else {
                        body.html('<img src="' + src + '" alt="Slider Preview" style="width:100%">');
                    }
                    $('#mediaModal').modal('show');
                });

                // Stop video on modal close
                $('#mediaModal').on('hidden.bs.modal', function() {
                    $('#mediaModalBody').empty();
                });
            });
        </script>

    </div>
</body>
</html>
