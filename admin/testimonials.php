<?php
session_start();
if ($_SESSION['role'] != '1') {
	header('location:index.php');
	exit;
}
include __DIR__ . '/connection.php';

$uploadDir = 'uploads/testimonials/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

$tableSql = "CREATE TABLE IF NOT EXISTS `testimonials` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `client_name` VARCHAR(120) NOT NULL,
  `client_location` VARCHAR(120) DEFAULT NULL,
  `client_image` VARCHAR(255) DEFAULT NULL,
  `testimonial_text` TEXT NOT NULL,
  `rating` TINYINT(1) NOT NULL DEFAULT 5,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `display_order` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($tableSql);

function testimonialDiskPath($path)
{
    $path = trim((string)$path);
    if ($path === '') {
        return '';
    }
    $candidates = [$path, '../' . $path, preg_replace('#^admin/#', '', $path)];
    foreach ($candidates as $candidate) {
        if (!empty($candidate) && file_exists($candidate)) {
            return $candidate;
        }
    }
    return '';
}

function testimonialAdminImageSrc($path)
{
    $path = trim((string)$path);
    if ($path === '') {
        return '';
    }
    if (strpos($path, 'admin/') === 0) {
        return '../' . $path;
    }
    return $path;
}

if (isset($_POST['save_testimonial'])) {
    $id = isset($_POST['testimonial_id']) ? (int)$_POST['testimonial_id'] : 0;
    $client_name = mysqli_real_escape_string($conn, trim($_POST['client_name'] ?? ''));
    $client_location = mysqli_real_escape_string($conn, trim($_POST['client_location'] ?? ''));
    $testimonial_text = mysqli_real_escape_string($conn, trim($_POST['testimonial_text'] ?? ''));
    $rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 5;
    $rating = max(1, min(5, $rating));
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $display_order = isset($_POST['display_order']) ? (int)$_POST['display_order'] : 0;

    if ($client_name === '' || $testimonial_text === '') {
        $_SESSION['msg'] = "Client name and testimonial text are required.";
        $_SESSION['msg_type'] = "danger";
        header("Location: testimonials.php" . ($id > 0 ? "?edit_id=" . $id : ""));
        exit;
    }

    $client_image = "";
    if (isset($_FILES['client_image']) && $_FILES['client_image']['error'] == 0) {
        $ext = strtolower(pathinfo($_FILES['client_image']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
            $newName = "testimonial_" . uniqid() . "_" . time() . "." . $ext;
            $targetFile = $uploadDir . $newName;
            if (move_uploaded_file($_FILES['client_image']['tmp_name'], $targetFile)) {
                $client_image = "admin/" . $targetFile;
            }
        }
    }

    if ($id > 0) {
        $sql = "UPDATE testimonials SET client_name='$client_name', client_location='$client_location', testimonial_text='$testimonial_text', rating=$rating, is_active=$is_active, display_order=$display_order";
        if ($client_image !== "") {
            $old = $conn->query("SELECT client_image FROM testimonials WHERE id=$id")->fetch_assoc();
            $oldDisk = testimonialDiskPath($old['client_image'] ?? '');
            if ($oldDisk !== '') {
                unlink($oldDisk);
            }
            $sql .= ", client_image='" . mysqli_real_escape_string($conn, $client_image) . "'";
        }
        $sql .= " WHERE id=$id";
        $conn->query($sql);
        $_SESSION['msg'] = "Testimonial updated successfully!";
    } else {
        if ($display_order <= 0) {
            $nextOrderRes = $conn->query("SELECT COALESCE(MAX(display_order), 0) AS max_order FROM testimonials");
            $nextOrder = 1;
            if ($nextOrderRes) {
                $nextOrderRow = $nextOrderRes->fetch_assoc();
                $nextOrder = ((int)$nextOrderRow['max_order']) + 1;
            }
            $display_order = $nextOrder;
        }
        $imgVal = mysqli_real_escape_string($conn, $client_image);
        $conn->query("INSERT INTO testimonials (client_name, client_location, client_image, testimonial_text, rating, is_active, display_order) VALUES ('$client_name', '$client_location', '$imgVal', '$testimonial_text', $rating, $is_active, $display_order)");
        $_SESSION['msg'] = "Testimonial added successfully!";
    }

    header("Location: testimonials.php");
    exit;
}

if (isset($_GET['delete_id'])) {
    $id = (int)$_GET['delete_id'];
    $old = $conn->query("SELECT client_image FROM testimonials WHERE id=$id")->fetch_assoc();
    $oldDisk = testimonialDiskPath($old['client_image'] ?? '');
    if ($oldDisk !== '') {
        unlink($oldDisk);
    }
    $conn->query("DELETE FROM testimonials WHERE id=$id");
    $_SESSION['msg'] = "Testimonial deleted successfully!";
    header("Location: testimonials.php");
    exit;
}

$isEdit = false;
$editData = null;
if (isset($_GET['edit_id'])) {
    $isEdit = true;
    $editId = (int)$_GET['edit_id'];
    $editData = $conn->query("SELECT * FROM testimonials WHERE id=$editId")->fetch_assoc();
    if (!$editData) {
        $isEdit = false;
    }
}

$msg = "";
$msg_type = "success";
if (isset($_SESSION['msg'])) {
    $msg = $_SESSION['msg'];
    $msg_type = isset($_SESSION['msg_type']) ? $_SESSION['msg_type'] : 'success';
    unset($_SESSION['msg'], $_SESSION['msg_type']);
}

$result = $conn->query("SELECT * FROM testimonials ORDER BY display_order ASC, id DESC");
$nextDisplayOrder = 1;
$nextOrderRes = $conn->query("SELECT COALESCE(MAX(display_order), 0) AS max_order FROM testimonials");
if ($nextOrderRes) {
    $nextOrderRow = $nextOrderRes->fetch_assoc();
    $nextDisplayOrder = ((int)$nextOrderRow['max_order']) + 1;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Manage Testimonials</title>
    <?php include __DIR__ . '/includes/header-links.php'; ?>
    <style>
        .page-bg { background-color: #f4f6f9; }
        .card-header-purple {
            background: linear-gradient(135deg, #6a11cb 0%, #7b5ea7 50%, #a855f7 100%);
            color: #fff; padding: 12px 20px; border-radius: 8px 8px 0 0 !important;
            font-size: 15px; font-weight: 600;
        }
        .form-card, .main-card {
            border: none; border-radius: 10px; box-shadow: 0 2px 12px rgba(0,0,0,0.05); background: #fff; margin-bottom: 20px;
        }
        .form-label { font-weight: 600; font-size: 13px; color: #444; }
        .btn-purple {
            background: linear-gradient(135deg, #6a11cb, #a855f7); color: #fff; border: none; font-weight: 600;
        }
        .btn-purple:hover { opacity: 0.9; color: #fff; }
        .avatar-preview { width: 56px; height: 56px; object-fit: cover; border-radius: 50%; border: 2px solid #eee; }
        .rating-stars i { margin-right: 2px; }
        .action-btn {
            width: 30px; height: 30px; border-radius: 4px; display: inline-flex; align-items: center; justify-content: center;
            border: none; color: #fff; text-decoration: none;
        }
        .btn-edit { background: #6a11cb; }
        .btn-delete { background: #dc3545; }
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
                            <h1 class="m-0 text-dark"><i class="fas fa-quote-right mr-2"></i>Testimonials</h1>
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

                    <div class="card form-card">
                        <div class="card-header-purple"><i class="fas fa-edit mr-2"></i><?= $isEdit ? 'Edit Testimonial' : 'Add New Testimonial' ?></div>
                        <div class="card-body">
                            <form method="POST" action="" enctype="multipart/form-data">
                                <?php if ($isEdit): ?>
                                    <input type="hidden" name="testimonial_id" value="<?= (int)$editData['id'] ?>">
                                <?php endif; ?>
                                <div class="row">
                                    <div class="col-md-4 form-group">
                                        <label class="form-label">Client Name *</label>
                                        <input type="text" name="client_name" class="form-control" required value="<?= htmlspecialchars($editData['client_name'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-3 form-group">
                                        <label class="form-label">Location</label>
                                        <input type="text" name="client_location" class="form-control" value="<?= htmlspecialchars($editData['client_location'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-2 form-group">
                                        <label class="form-label">Rating</label>
                                        <select name="rating" class="form-control">
                                            <?php $curRating = (int)($editData['rating'] ?? 5); ?>
                                            <?php for ($r = 1; $r <= 5; $r++): ?>
                                                <option value="<?= $r ?>" <?= $curRating === $r ? 'selected' : '' ?>><?= $r ?> Star</option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-2 form-group">
                                        <label class="form-label">Display Order</label>
                                        <input type="number" name="display_order" class="form-control" min="0" value="<?= $isEdit ? (int)($editData['display_order'] ?? 0) : $nextDisplayOrder ?>">
                                    </div>
                                    <div class="col-md-1 form-group d-flex align-items-center">
                                        <div class="custom-control custom-switch mt-3">
                                            <input type="checkbox" class="custom-control-input" id="activeSwitch" name="is_active" <?= (!isset($editData['is_active']) || (int)$editData['is_active'] === 1) ? 'checked' : '' ?>>
                                            <label class="custom-control-label" for="activeSwitch">Active</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Testimonial Text *</label>
                                    <textarea name="testimonial_text" class="form-control" rows="3" required><?= htmlspecialchars($editData['testimonial_text'] ?? '') ?></textarea>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 form-group">
                                        <label class="form-label">Client Image</label>
                                        <div class="custom-file">
                                            <input type="file" class="custom-file-input" id="clientImage" name="client_image" accept="image/*">
                                            <label class="custom-file-label" for="clientImage">Choose file</label>
                                        </div>
                                    </div>
                                    <div class="col-md-6 form-group">
                                        <?php if ($isEdit && !empty($editData['client_image'])): ?>
                                            <label class="form-label d-block">Current Image</label>
                                            <img src="<?= htmlspecialchars(testimonialAdminImageSrc($editData['client_image'])) ?>" alt="Client" class="avatar-preview">
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <button type="submit" name="save_testimonial" class="btn btn-purple">
                                    <i class="fas fa-save mr-1"></i> <?= $isEdit ? 'Update Testimonial' : 'Save Testimonial' ?>
                                </button>
                                <?php if ($isEdit): ?>
                                    <a href="testimonials.php" class="btn btn-secondary ml-2">Cancel</a>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>

                    <div class="card main-card">
                        <div class="card-header-purple"><i class="fas fa-list mr-2"></i>All Testimonials</div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th style="width:70px;">ID</th>
                                            <th style="width:90px;">Image</th>
                                            <th>Client</th>
                                            <th>Testimonial</th>
                                            <th style="width:120px;">Rating</th>
                                            <th style="width:100px;">Status</th>
                                            <th style="width:90px;">Order</th>
                                            <th style="width:90px;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($result && $result->num_rows > 0): ?>
                                            <?php while ($row = $result->fetch_assoc()): ?>
                                                <tr>
                                                    <td><?= (int)$row['id'] ?></td>
                                                    <td>
                                                        <?php if (!empty($row['client_image'])): ?>
                                                            <img src="<?= htmlspecialchars(testimonialAdminImageSrc($row['client_image'])) ?>" class="avatar-preview" alt="<?= htmlspecialchars($row['client_name']) ?>">
                                                        <?php else: ?>
                                                            <div class="avatar-preview d-flex align-items-center justify-content-center bg-light"><i class="fas fa-user text-muted"></i></div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <strong><?= htmlspecialchars($row['client_name']) ?></strong><br>
                                                        <small class="text-muted"><?= htmlspecialchars($row['client_location']) ?></small>
                                                    </td>
                                                    <td style="max-width:360px;"><?= htmlspecialchars($row['testimonial_text']) ?></td>
                                                    <td class="rating-stars">
                                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                                            <i class="fas fa-star <?= $i <= (int)$row['rating'] ? 'text-warning' : 'text-muted' ?>"></i>
                                                        <?php endfor; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ((int)$row['is_active'] === 1): ?>
                                                            <span class="badge badge-success">Active</span>
                                                        <?php else: ?>
                                                            <span class="badge badge-secondary">Inactive</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?= (int)$row['display_order'] ?></td>
                                                    <td>
                                                        <a href="testimonials.php?edit_id=<?= (int)$row['id'] ?>" class="action-btn btn-edit" title="Edit"><i class="fas fa-edit"></i></a>
                                                        <a href="testimonials.php?delete_id=<?= (int)$row['id'] ?>" class="action-btn btn-delete" title="Delete" onclick="return confirm('Delete this testimonial?');"><i class="fas fa-trash"></i></a>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr><td colspan="8" class="text-center py-4">No testimonials found.</td></tr>
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
            $(document).on('change', '.custom-file-input', function() {
                var fileName = $(this).val().split('\\').pop();
                $(this).next('.custom-file-label').html(fileName);
            });
        </script>
    </div>
</body>
</html>
