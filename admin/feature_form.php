<?php
session_start();
if ($_SESSION['role'] != '1') {
	header('location:index.php');
	exit;
}
include __DIR__ . '/connection.php';

$uploadDir = 'uploads/features/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

$isEdit = false;
$featData = null;

if (isset($_GET['edit_id'])) {
    $isEdit = true;
    $id = (int)$_GET['edit_id'];
    $featData = $conn->query("SELECT * FROM features WHERE id=$id")->fetch_assoc();
    if (!$featData) {
        header("Location: features.php");
        exit;
    }
}

$nextOrder = 1;
if (!$isEdit) {
    $r = $conn->query("SELECT COALESCE(MAX(display_order), 0) + 1 AS next_order FROM features");
    if ($r) $nextOrder = (int)$r->fetch_assoc()['next_order'];
}

// Handle Form Submit
if (isset($_POST['save_feature'])) {
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $icon_type = mysqli_real_escape_string($conn, $_POST['icon_type']);
    $icon_class = mysqli_real_escape_string($conn, $_POST['icon_class']);
    $display_order = (int)$_POST['display_order'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    $image_sql = "";
    $icon_image = "";

    // Handle Remove Image
    if (isset($_POST['remove_image']) && $_POST['remove_image'] == '1' && $isEdit) {
        if (!empty($featData['icon_image']) && file_exists($featData['icon_image'])) {
            unlink($featData['icon_image']);
        }
        $image_sql = ", icon_image=''";
    }

    // Handle File Upload
    if ($icon_type == 'image' && !empty($_FILES['icon_image']['name'])) {
        $ext = strtolower(pathinfo($_FILES['icon_image']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','webp','gif','svg'];
        if (in_array($ext, $allowed)) {
            // Delete old file if updating
            if ($isEdit && empty($image_sql) && !empty($featData['icon_image']) && file_exists($featData['icon_image'])) {
                unlink($featData['icon_image']);
            }
            
            $filename = 'feat_' . uniqid() . '.' . $ext;
            if(move_uploaded_file($_FILES['icon_image']['tmp_name'], $uploadDir . $filename)) {
                $icon_image = $uploadDir . $filename;
                $image_sql = ", icon_image='" . $icon_image . "'";
            }
        } else {
            $_SESSION['msg'] = "Invalid image format!";
            $_SESSION['msg_type'] = "danger";
            header("Location: features.php");
            exit;
        }
    }

    // Clear unused fields based on type
    if ($icon_type == 'fontawesome') {
        if ($isEdit && empty($image_sql) && !empty($featData['icon_image']) && file_exists($featData['icon_image'])) {
            unlink($featData['icon_image']);
        }
        $image_sql = ", icon_image=''";
    } else {
        $icon_class = "";
    }

    if ($isEdit) {
        $id = (int)$_POST['feat_id'];
        $sql = "UPDATE features SET title='$title', description='$description', icon_type='$icon_type', icon_class='$icon_class', display_order=$display_order, is_active=$is_active $image_sql WHERE id=$id";
        $conn->query($sql);
        $_SESSION['msg'] = "Feature updated successfully!";
    } else {
        $sql = "INSERT INTO features (title, description, icon_type, icon_class, icon_image, display_order, is_active) VALUES ('$title', '$description', '$icon_type', '$icon_class', '$icon_image', $display_order, $is_active)";
        $conn->query($sql);
        $_SESSION['msg'] = "Feature added successfully!";
    }
    
    header("Location: features.php");
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title><?= $isEdit ? 'Edit Feature' : 'Add New Feature' ?></title>
    <?php include __DIR__ . '/includes/header-links.php'; ?>

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
            background: #faf8f5; /* matching the slightly warm bg in screenshot */
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
        .toggle-switch input:checked + .toggle-slider { background-color: #0d6efd; }
        .toggle-switch input:checked + .toggle-slider:before { transform: translateX(20px); }

        .btn-purple { background: linear-gradient(135deg, #6a11cb, #a855f7); color: #fff; border: none; font-weight: 600; padding: 10px 24px; border-radius: 6px; }
        .btn-purple:hover { opacity: 0.9; color: #fff; }
        
        .btn-dark-grey { background: #343a40; color: #fff; border: none; font-weight: 600; padding: 10px 24px; border-radius: 6px; text-decoration: none; display: inline-block;}
        .btn-dark-grey:hover { background: #23272b; color: #fff; text-decoration: none; }
        
        .icon-type-group { display: flex; border: 1px solid #ced4da; border-radius: 6px; overflow: hidden; margin-bottom: 15px; }
        .icon-type-btn { flex: 1; text-align: center; padding: 10px; cursor: pointer; background: #fff; color: #495057; font-weight: 600; transition: 0.2s; border-right: 1px solid #ced4da; }
        .icon-type-btn:last-child { border-right: none; }
        .icon-type-btn.active { background: #0d6efd; color: #fff; }

        .preview-box { background: #eee; border-radius: 12px; width: 100%; height: 200px; display: flex; align-items: center; justify-content: center; position: relative; margin-top: 20px;}
        .preview-box span { position: absolute; top: 10px; left: 15px; font-size: 13px; color: #666; font-family: 'Courier New', Courier, monospace; }
        .live-icon-preview { font-size: 80px; color: #0d6efd; }
        .live-img-preview { max-width: 80%; max-height: 80%; object-fit: contain; }
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
                            <h1 class="m-0 text-dark" style="font-family: 'Brush Script MT', cursive; font-size: 32px; font-weight: normal;">
                                <i class="fas fa-plus mr-2" style="font-size:24px;"></i> <?= $isEdit ? 'Edit Feature' : 'Add New Feature' ?>
                            </h1>
                            <p class="text-muted mt-2" style="font-size: 18px; color: #333 !important;">
                                <?= $isEdit ? 'Edit the' : 'Add a new' ?> feature to "Why Plan Your Travel With Us?" section
                            </p>
                        </div>
                        <div class="col-sm-6 text-right">
                            <a href="features.php" class="btn btn-dark-grey mt-2">
                                <i class="fas fa-arrow-left mr-1"></i> Back to Features
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <section class="content">
                <div class="container-fluid">
                    <form method="POST" action="" enctype="multipart/form-data">
                        <?php if($isEdit): ?>
                            <input type="hidden" name="feat_id" value="<?= $featData['id'] ?>">
                        <?php endif; ?>
                        
                        <div class="card form-card p-4">
                            <div class="row">
                                <!-- Left Column -->
                                <div class="col-md-7 border-right">
                                    <div class="form-group">
                                        <label class="form-label">Title <span class="text-danger">*</span></label>
                                        <input type="text" name="title" class="form-control" required value="<?= $isEdit ? htmlspecialchars($featData['title']) : '' ?>" placeholder="e.g., IATA Accredited">
                                        <div class="form-text">The main heading for this feature</div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="form-label">Description <span class="text-danger">*</span></label>
                                        <textarea name="description" class="form-control" rows="4" required placeholder="Enter feature description..."><?= $isEdit ? htmlspecialchars($featData['description']) : '' ?></textarea>
                                        <div class="form-text">Detailed description of the feature</div>
                                    </div>
                                    
                                    <div class="form-group mt-4">
                                        <label class="form-label">Icon Type <span class="text-danger">*</span></label>
                                        <input type="hidden" name="icon_type" id="iconTypeInput" value="<?= $isEdit ? $featData['icon_type'] : 'fontawesome' ?>">
                                        <div class="icon-type-group">
                                            <div class="icon-type-btn <?= (!$isEdit || $featData['icon_type'] == 'fontawesome') ? 'active' : '' ?>" onclick="setIconType('fontawesome')">
                                                <i class="fab fa-font-awesome mr-2"></i> Font Awesome
                                            </div>
                                            <div class="icon-type-btn <?= ($isEdit && $featData['icon_type'] == 'image') ? 'active' : '' ?>" onclick="setIconType('image')">
                                                <i class="fas fa-image mr-2"></i> Upload Image
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-group" id="faSection" style="display: <?= (!$isEdit || $featData['icon_type'] == 'fontawesome') ? 'block' : 'none' ?>;">
                                        <label class="form-label">Font Awesome Icon Class <span class="text-danger">*</span></label>
                                        <input type="text" name="icon_class" id="iconClassInput" class="form-control" value="<?= $isEdit ? htmlspecialchars($featData['icon_class']) : 'fa-certificate' ?>" onkeyup="updateLivePreview()">
                                        <div class="form-text">Use Font Awesome 5 classes. Browse icons at <a href="https://fontawesome.com/v5/search?m=free" target="_blank">fontawesome.com</a></div>
                                    </div>

                                    <div class="form-group" id="imgSection" style="display: <?= ($isEdit && $featData['icon_type'] == 'image') ? 'block' : 'none' ?>;">
                                        <label class="form-label">Upload Custom Icon</label>
                                        <input type="file" name="icon_image" class="form-control-file" accept="image/*" id="iconImgInput" onchange="previewUpload(this)">
                                        <div class="form-text mt-2">Recommended: SVG or PNG with transparent background.</div>
                                        <?php if($isEdit && !empty($featData['icon_image'])): ?>
                                            <input type="hidden" id="currentImgUrl" value="<?= htmlspecialchars($featData['icon_image']) ?>">
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Right Column -->
                                <div class="col-md-5 pl-4">
                                    <div class="form-group">
                                        <label class="form-label">Display Order</label>
                                        <input type="number" name="display_order" class="form-control" min="1" value="<?= $isEdit ? $featData['display_order'] : $nextOrder ?>">
                                        <div class="form-text">Lower numbers appear first</div>
                                    </div>
                                    
                                    <div class="form-group mt-4">
                                        <div class="d-flex align-items-center">
                                            <label class="toggle-switch mr-2">
                                                <input type="checkbox" name="is_active" <?= (!$isEdit || $featData['is_active']) ? 'checked' : '' ?>>
                                                <span class="toggle-slider"></span>
                                            </label>
                                            <span class="form-label mb-0" style="font-weight:normal; font-size:14px; color:#555;">Active</span>
                                        </div>
                                        <div class="form-text mt-2">Only active features will be displayed on the website</div>
                                    </div>

                                    <div class="preview-box">
                                        <span>Icon Preview</span>
                                        <div id="livePreviewContainer">
                                            <?php if($isEdit && $featData['icon_type'] == 'image'): ?>
                                                <img src="<?= htmlspecialchars($featData['icon_image']) ?>" class="live-img-preview">
                                            <?php else: ?>
                                                <i class="fas <?= $isEdit ? htmlspecialchars($featData['icon_class']) : 'fa-certificate' ?> live-icon-preview" id="faPreviewIcon"></i>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="text-right mb-5">
                            <button type="submit" name="save_feature" class="btn-purple mr-2">
                                <i class="fas fa-save mr-1"></i> <?= $isEdit ? 'Update Feature' : 'Save Feature' ?>
                            </button>
                        </div>
                    </form>
                </div>
            </section>
        </div>

        <?php include __DIR__ . '/includes/footer-links.php'; ?>
        
        <script>
            function setIconType(type) {
                $('.icon-type-btn').removeClass('active');
                if (type === 'fontawesome') {
                    $('.icon-type-btn:first').addClass('active');
                    $('#iconTypeInput').val('fontawesome');
                    $('#faSection').show();
                    $('#imgSection').hide();
                    updateLivePreview();
                } else {
                    $('.icon-type-btn:last').addClass('active');
                    $('#iconTypeInput').val('image');
                    $('#faSection').hide();
                    $('#imgSection').show();
                    
                    // Show current image or placeholder
                    let imgUrl = $('#currentImgUrl').val();
                    if(imgUrl) {
                        $('#livePreviewContainer').html(`<img src="${imgUrl}" class="live-img-preview">`);
                    } else {
                        $('#livePreviewContainer').html(`<i class="fas fa-image live-icon-preview text-muted"></i>`);
                    }
                }
            }

            function updateLivePreview() {
                if ($('#iconTypeInput').val() === 'fontawesome') {
                    let iconClass = $('#iconClassInput').val();
                    // Ensure 'fas ' prefix exists visually for preview if user just types 'fa-certificate'
                    let fullClass = iconClass.includes('fa-') && !iconClass.includes('fas ') && !iconClass.includes('fab ') && !iconClass.includes('far ') 
                                    ? 'fas ' + iconClass 
                                    : iconClass;
                    $('#livePreviewContainer').html(`<i class="${fullClass} live-icon-preview"></i>`);
                }
            }

            function previewUpload(input) {
                if (input.files && input.files[0]) {
                    var reader = new FileReader();
                    reader.onload = function(e) {
                        $('#livePreviewContainer').html(`<img src="${e.target.result}" class="live-img-preview">`);
                    }
                    reader.readAsDataURL(input.files[0]);
                }
            }
        </script>

    </div>
</body>
</html>
