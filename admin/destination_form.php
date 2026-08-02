<?php
session_start();
if ($_SESSION['role'] != '1') {
	header('location:index.php');
	exit;
}
include __DIR__ . '/connection.php';

$uploadDir = 'uploads/destinations/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

$check_tour_type = $conn->query("SHOW COLUMNS FROM `destinations` LIKE 'tour_type'");
if ($check_tour_type && $check_tour_type->num_rows == 0) {
    $conn->query("ALTER TABLE `destinations` ADD `tour_type` VARCHAR(20) DEFAULT NULL AFTER `region`");
}

// Generate slug function
function createSlug($str, $conn, $id = 0) {
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $str)));
    $slug = preg_replace('/-+/', '-', $slug);
    
    // Check if exists
    $sql = "SELECT id FROM destinations WHERE slug = '$slug' AND id != $id";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        $slug = $slug . '-' . time();
    }
    return $slug;
}

$isEdit = false;
$destData = null;

if (isset($_GET['edit_id'])) {
    $isEdit = true;
    $id = (int)$_GET['edit_id'];
    $destData = $conn->query("SELECT * FROM destinations WHERE id=$id")->fetch_assoc();
    if (!$destData) {
        header("Location: destinations.php");
        exit;
    }
}

$nextOrder = 1;
if (!$isEdit) {
    $r = $conn->query("SELECT COALESCE(MAX(display_order), 0) + 1 AS next_order FROM destinations");
    if ($r) $nextOrder = (int)$r->fetch_assoc()['next_order'];
}

// Handle Form Submit
if (isset($_POST['save_destination'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $country = mysqli_real_escape_string($conn, $_POST['country']);
    $region = mysqli_real_escape_string($conn, $_POST['region']);
    $tour_type = mysqli_real_escape_string($conn, $_POST['tour_type'] ?? '');
    if (!in_array($tour_type, ['domestic', 'international'], true)) {
        $tour_type = '';
    }
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $best_time_to_visit = mysqli_real_escape_string($conn, $_POST['best_time_to_visit']);
    $how_to_reach = mysqli_real_escape_string($conn, $_POST['how_to_reach']);
    $display_order = (int)$_POST['display_order'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    $slug = trim($_POST['slug']);
    
    $image_sql = "";
    $image = "";

    // Handle Remove Image
    if (isset($_POST['remove_image']) && $_POST['remove_image'] == '1' && $isEdit) {
        if (!empty($destData['image']) && file_exists($destData['image'])) {
            unlink($destData['image']);
        }
        $image_sql = ", image=''";
    }

    // Handle File Upload
    if (!empty($_FILES['image']['name'])) {
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','webp','gif'];
        if (in_array($ext, $allowed)) {
            // Delete old file if updating
            if ($isEdit && empty($image_sql) && !empty($destData['image']) && file_exists($destData['image'])) {
                unlink($destData['image']);
            }
            
            $filename = 'dest_' . uniqid() . '.' . $ext;
            if(move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $filename)) {
                $image = $uploadDir . $filename;
                $image_sql = ", image='" . $image . "'";
            }
        } else {
            $_SESSION['msg'] = "Invalid image format!";
            $_SESSION['msg_type'] = "danger";
            header("Location: destinations.php");
            exit;
        }
    }

    if ($isEdit) {
        $id = (int)$_POST['dest_id'];
        if (empty($slug)) {
            $slug = createSlug($name, $conn, $id);
        } else {
            $slug = createSlug($slug, $conn, $id);
        }
        
        $sql = "UPDATE destinations SET name='$name', slug='$slug', country='$country', region='$region', tour_type='$tour_type', description='$description', best_time_to_visit='$best_time_to_visit', how_to_reach='$how_to_reach', display_order=$display_order, is_active=$is_active $image_sql WHERE id=$id";
        $conn->query($sql);
        $_SESSION['msg'] = "Destination updated successfully!";
    } else {
        if (empty($slug)) {
            $slug = createSlug($name, $conn);
        } else {
            $slug = createSlug($slug, $conn);
        }
        
        $sql = "INSERT INTO destinations (name, slug, country, region, tour_type, description, best_time_to_visit, how_to_reach, image, display_order, is_active) VALUES ('$name', '$slug', '$country', '$region', '$tour_type', '$description', '$best_time_to_visit', '$how_to_reach', '$image', $display_order, $is_active)";
        $conn->query($sql);
        $_SESSION['msg'] = "Destination added successfully!";
    }
    
    header("Location: destinations.php");
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title><?= $isEdit ? 'Edit Destination' : 'Add New Destination' ?></title>
    <?php include __DIR__ . '/includes/header-links.php'; ?>

    <!-- Summernote for rich text editing -->
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-bs4.min.css" rel="stylesheet">

    <style>
        .page-bg { background-color: #f4f6f9; }
        
        .card-header-purple {
            background: linear-gradient(135deg, #6a11cb 0%, #7b5ea7 50%, #a855f7 100%);
            color: #fff;
            padding: 12px 20px;
            border-radius: 8px 8px 0 0 !important;
            font-size: 16px;
            font-weight: 600;
        }
        
        .form-card {
            border: none;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }

        .form-label { font-weight: 600; color: #444; font-size: 14px; margin-bottom: 6px; }
        .form-text { font-size: 12px; color: #888; margin-top: 4px; }
        
        .toggle-switch { position: relative; display: inline-block; width: 44px; height: 24px; margin: 0; vertical-align: middle; }
        .toggle-switch input { opacity: 0; width: 0; height: 0; }
        .toggle-slider {
            position: absolute; cursor: pointer; inset: 0;
            background-color: #ccc; border-radius: 24px; transition: 0.3s;
        }
        .toggle-slider:before {
            content: ""; position: absolute; height: 18px; width: 18px;
            left: 3px; bottom: 3px; background: #fff; border-radius: 50%; transition: 0.3s;
        }
        .toggle-switch input:checked + .toggle-slider { background-color: #3b82f6; }
        .toggle-switch input:checked + .toggle-slider:before { transform: translateX(20px); }

        .btn-purple { background: linear-gradient(135deg, #6a11cb, #a855f7); color: #fff; border: none; font-weight: 600; padding: 10px; width: 100%; border-radius: 6px; margin-bottom: 10px; }
        .btn-purple:hover { opacity: 0.9; color: #fff; }
        
        .btn-dark-grey { background: #4b5563; color: #fff; border: none; font-weight: 600; padding: 10px; width: 100%; border-radius: 6px; margin-bottom: 10px; text-decoration: none; display: block; text-align: center;}
        .btn-dark-grey:hover { background: #374151; color: #fff; text-decoration: none; }
        
        .img-preview { width: 100%; height: auto; max-height: 150px; object-fit: cover; border-radius: 6px; border: 1px solid #ddd; margin-bottom: 10px; }
        .remove-img-link { color: #dc3545; font-size: 13px; font-weight: 600; cursor: pointer; display: block; text-align: center; }
        .remove-img-link:hover { text-decoration: underline; }
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
                            <h1 class="m-0 text-dark"><i class="fas fa-map-marker-alt mr-2"></i> <?= $isEdit ? 'Edit Destination' : 'Add New Destination' ?></h1>
                        </div>
                        <div class="col-sm-6">
                            <ol class="breadcrumb float-sm-right">
                                <li class="breadcrumb-item"><a href="destinations.php" style="color: #6a11cb;">Destinations</a></li>
                                <li class="breadcrumb-item active"><?= $isEdit ? 'Edit' : 'Add New' ?></li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

            <section class="content">
                <div class="container-fluid">
                    <form method="POST" action="" enctype="multipart/form-data">
                        <?php if($isEdit): ?>
                            <input type="hidden" name="dest_id" value="<?= $destData['id'] ?>">
                            <input type="hidden" name="remove_image" id="removeImage" value="0">
                        <?php endif; ?>
                        
                        <div class="row">
                            <!-- Left Column -->
                            <div class="col-md-8">
                                <div class="card form-card">
                                    <div class="card-header-purple">
                                        <i class="fas fa-info-circle mr-2"></i> Basic Information
                                    </div>
                                    <div class="card-body">
                                        <div class="form-group">
                                            <label class="form-label">Tour Type</label>
                                            <select name="tour_type" class="form-control">
                                                <option value="">Select Tour Type</option>
                                                <option value="domestic" <?= ($isEdit && ($destData['tour_type'] ?? '') === 'domestic') ? 'selected' : '' ?>>Domestic</option>
                                                <option value="international" <?= ($isEdit && ($destData['tour_type'] ?? '') === 'international') ? 'selected' : '' ?>>International</option>
                                            </select>
                                        </div>

                                        <div class="form-group">
                                            <label class="form-label">Destination Name <span class="text-danger">*</span></label>
                                            <input type="text" name="name" class="form-control" required value="<?= $isEdit ? htmlspecialchars($destData['name']) : '' ?>" placeholder="Enter destination name">
                                        </div>
                                        
                                        <div class="form-group">
                                            <label class="form-label">Slug</label>
                                            <input type="text" name="slug" class="form-control" value="<?= $isEdit ? htmlspecialchars($destData['slug']) : '' ?>" placeholder="auto-generated-slug">
                                            <div class="form-text">Leave empty to auto-generate from name</div>
                                        </div>

                                        <div class="form-group">
                                            <label class="form-label">Country</label>
                                            <input type="text" name="country" class="form-control" value="<?= $isEdit ? htmlspecialchars($destData['country']) : '' ?>" placeholder="e.g., Indonesia">
                                        </div>

                                        <div class="form-group">
                                            <label class="form-label">Region</label>
                                            <input type="text" name="region" class="form-control" value="<?= $isEdit ? htmlspecialchars($destData['region']) : '' ?>" placeholder="e.g., Southeast Asia">
                                        </div>
                                        
                                        <div class="form-group">
                                            <label class="form-label">Description</label>
                                            <textarea name="description" class="form-control summernote" rows="5"><?= $isEdit ? htmlspecialchars($destData['description']) : '' ?></textarea>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label class="form-label">Best Time to Visit</label>
                                            <input type="text" name="best_time_to_visit" class="form-control" value="<?= $isEdit ? htmlspecialchars($destData['best_time_to_visit']) : '' ?>" placeholder="e.g., The best time to visit Goa is from November to February...">
                                        </div>
                                        
                                        <div class="form-group mb-0">
                                            <label class="form-label">How to Reach</label>
                                            <textarea name="how_to_reach" class="form-control summernote" rows="5"><?= $isEdit ? htmlspecialchars($destData['how_to_reach']) : '' ?></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Right Column -->
                            <div class="col-md-4">
                                <!-- Publish Card -->
                                <div class="card form-card">
                                    <div class="card-header-purple">
                                        <i class="fas fa-paper-plane mr-2"></i> Publish
                                    </div>
                                    <div class="card-body">
                                        <div class="form-group">
                                            <label class="form-label">Display Order</label>
                                            <input type="number" name="display_order" class="form-control" min="1" value="<?= $isEdit ? $destData['display_order'] : $nextOrder ?>">
                                            <div class="form-text">Lower numbers appear first</div>
                                        </div>
                                        
                                        <div class="d-flex align-items-center mb-4">
                                            <label class="toggle-switch mr-2">
                                                <input type="checkbox" name="is_active" <?= (!$isEdit || $destData['is_active']) ? 'checked' : '' ?>>
                                                <span class="toggle-slider"></span>
                                            </label>
                                            <span class="form-label mb-0" style="font-weight:normal; font-size:14px;">Active</span>
                                        </div>

                                        <button type="submit" name="save_destination" class="btn-purple">
                                            <i class="fas fa-save mr-1"></i> <?= $isEdit ? 'Update Destination' : 'Save Destination' ?>
                                        </button>
                                        
                                        <a href="destinations.php" class="btn-dark-grey">
                                            <i class="fas fa-times mr-1"></i> Cancel
                                        </a>
                                    </div>
                                </div>

                                <!-- Image Card -->
                                <div class="card form-card">
                                    <div class="card-header-purple">
                                        <i class="fas fa-image mr-2"></i> Destination Image
                                    </div>
                                    <div class="card-body">
                                        <?php if($isEdit && !empty($destData['image'])): ?>
                                            <div class="mb-3" id="currentImgWrapper">
                                                <img src="<?= htmlspecialchars($destData['image']) ?>" class="img-preview" alt="Current Image">
                                                <span class="remove-img-link" onclick="removeImageFunc()"><i class="fas fa-trash-alt mr-1"></i> Remove image</span>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <input type="file" name="image" class="form-control-file" accept="image/jpeg,image/png,image/webp,image/gif">
                                        <div class="form-text mt-2">Recommended size: 1200x800px</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </section>
        </div>

        <?php include __DIR__ . '/includes/footer-links.php'; ?>
        
        <!-- Summernote JS -->
        <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-bs4.min.js"></script>
        
        <script>
            $(document).ready(function() {
                $('.summernote').summernote({
                    height: 200,
                    placeholder: 'Enter destination description...',
                    toolbar: [
                        ['style', ['style']],
                        ['font', ['bold', 'italic', 'underline', 'clear']],
                        ['para', ['ul', 'ol', 'paragraph']],
                        ['view', ['fullscreen', 'codeview']]
                    ]
                });
            });

            function removeImageFunc() {
                document.getElementById('currentImgWrapper').style.display = 'none';
                document.getElementById('removeImage').value = '1';
            }
        </script>

    </div>
</body>
</html>
