<?php
session_start();
if ($_SESSION['role'] != '1') {
	header('location:index.php');
	exit;
}
include __DIR__ . '/connection.php';

$uploadDir = 'uploads/budget_cards/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

$isEdit = false;
$cardData = null;

if (isset($_GET['edit_id'])) {
    $isEdit = true;
    $id = (int)$_GET['edit_id'];
    $cardData = $conn->query("SELECT * FROM budget_cards WHERE id=$id")->fetch_assoc();
    if (!$cardData) {
        header("Location: budget_cards.php");
        exit;
    }
}

$nextOrder = 1;
if (!$isEdit) {
    $r = $conn->query("SELECT COALESCE(MAX(display_order), 0) + 1 AS next_order FROM budget_cards");
    if ($r) $nextOrder = (int)$r->fetch_assoc()['next_order'];
}

// Handle Form Submit
if (isset($_POST['save_card'])) {
    $amount = mysqli_real_escape_string($conn, $_POST['amount']);
    $label = mysqli_real_escape_string($conn, $_POST['label']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $icon_type = mysqli_real_escape_string($conn, $_POST['icon_type']);
    $icon = mysqli_real_escape_string($conn, $_POST['icon']);
    $color_class = mysqli_real_escape_string($conn, $_POST['color_class']);
    $display_order = (int)$_POST['display_order'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    $image_sql = "";
    $icon_image = "";

    // Handle Remove Image
    if (isset($_POST['remove_image']) && $_POST['remove_image'] == '1' && $isEdit) {
        if (!empty($cardData['icon_image']) && file_exists($cardData['icon_image'])) {
            unlink($cardData['icon_image']);
        }
        $image_sql = ", icon_image=''";
    }

    // Handle File Upload
    if ($icon_type == 'image' && !empty($_FILES['icon_image']['name'])) {
        $ext = strtolower(pathinfo($_FILES['icon_image']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','webp','gif','svg'];
        if (in_array($ext, $allowed)) {
            // Delete old file if updating
            if ($isEdit && empty($image_sql) && !empty($cardData['icon_image']) && file_exists($cardData['icon_image'])) {
                unlink($cardData['icon_image']);
            }
            
            $filename = 'bc_' . uniqid() . '.' . $ext;
            if(move_uploaded_file($_FILES['icon_image']['tmp_name'], $uploadDir . $filename)) {
                $icon_image = $uploadDir . $filename;
                $image_sql = ", icon_image='" . $icon_image . "'";
            }
        } else {
            $_SESSION['msg'] = "Invalid image format!";
            $_SESSION['msg_type'] = "danger";
            header("Location: budget_cards.php");
            exit;
        }
    }

    // Clear unused fields based on type
    if ($icon_type == 'fontawesome') {
        if ($isEdit && empty($image_sql) && !empty($cardData['icon_image']) && file_exists($cardData['icon_image'])) {
            unlink($cardData['icon_image']);
        }
        $image_sql = ", icon_image=''";
    } else {
        $icon = "";
    }
    
    if ($isEdit) {
        $id = (int)$_POST['card_id'];
        $sql = "UPDATE budget_cards SET amount='$amount', label='$label', description='$description', icon_type='$icon_type', icon='$icon', color_class='$color_class', display_order=$display_order, is_active=$is_active $image_sql WHERE id=$id";
        $conn->query($sql);
        $_SESSION['msg'] = "Budget card updated successfully!";
    } else {
        $sql = "INSERT INTO budget_cards (amount, label, description, icon_type, icon, icon_image, color_class, display_order, is_active) VALUES ('$amount', '$label', '$description', '$icon_type', '$icon', '$icon_image', '$color_class', $display_order, $is_active)";
        $conn->query($sql);
        $_SESSION['msg'] = "Budget card added successfully!";
    }
    
    header("Location: budget_cards.php");
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title><?= $isEdit ? 'Edit Budget Card' : 'Add New Budget Card' ?></title>
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
            background: #faf8f5; 
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

        .info-box-custom { background-color: #e0f2f1; border-radius: 8px; padding: 15px; margin-bottom: 15px; border-left: 4px solid #26a69a; }
        .tips-box-custom { background-color: #fff8e1; border-radius: 8px; padding: 15px; margin-bottom: 15px; border-left: 4px solid #ffca28; }
        .box-title { font-weight: 600; margin-bottom: 8px; color: #333; font-size: 14px; }
        .box-list { padding-left: 20px; font-size: 13px; color: #555; margin-bottom: 0; }
        .box-list li { margin-bottom: 4px; }
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
                                <i class="fas fa-edit mr-2" style="font-size:24px;"></i> <?= $isEdit ? 'Edit Budget Card' : 'Add Budget Card' ?>
                            </h1>
                            <ol class="breadcrumb mt-2 bg-transparent p-0">
                                <li class="breadcrumb-item"><a href="dashboard.php" style="color: #0d6efd;">Dashboard</a></li>
                                <li class="breadcrumb-item"><a href="budget_cards.php" style="color: #0d6efd;">Budget Cards</a></li>
                                <li class="breadcrumb-item active"><?= $isEdit ? 'Edit Card' : 'Add Card' ?></li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

            <section class="content">
                <div class="container-fluid">
                    <form method="POST" action="" enctype="multipart/form-data">
                        <?php if($isEdit): ?>
                            <input type="hidden" name="card_id" value="<?= $cardData['id'] ?>">
                        <?php endif; ?>
                        
                        <div class="row">
                            <div class="col-md-8">
                                <div class="card form-card">
                                    <div class="card-header-purple"><i class="fas fa-info-circle mr-2"></i> Card Details</div>
                                    <div class="card-body p-4">
                                        <div class="form-group mb-4">
                                            <label class="form-label">Label <span class="text-danger">*</span></label>
                                            <input type="text" name="label" class="form-control" required value="<?= $isEdit ? htmlspecialchars($cardData['label']) : 'BELOW' ?>" placeholder="e.g. BELOW">
                                            <div class="form-text">Small text above the amount (e.g. BELOW, UNDER)</div>
                                        </div>

                                        <div class="form-group mb-4">
                                            <label class="form-label">Amount <span class="text-danger">*</span></label>
                                            <input type="text" name="amount" class="form-control" required value="<?= $isEdit ? htmlspecialchars($cardData['amount']) : '' ?>" placeholder="e.g. Rs. 50,000">
                                            <div class="form-text">The main amount text (e.g. Rs. 50,000)</div>
                                        </div>

                                        <div class="form-group mb-4">
                                            <label class="form-label">Description <span class="text-danger">*</span></label>
                                            <input type="text" name="description" class="form-control" required value="<?= $isEdit ? htmlspecialchars($cardData['description']) : '' ?>" placeholder="e.g. Budget Friendly Tours">
                                            <div class="form-text">Description displayed below the amount</div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6 form-group mt-4">
                                                <label class="form-label">Icon Type <span class="text-danger">*</span></label>
                                                <input type="hidden" name="icon_type" id="iconTypeInput" value="<?= $isEdit ? $cardData['icon_type'] : 'fontawesome' ?>">
                                                <div class="icon-type-group">
                                                    <div class="icon-type-btn <?= (!$isEdit || $cardData['icon_type'] == 'fontawesome') ? 'active' : '' ?>" onclick="setIconType('fontawesome')">
                                                        <i class="fab fa-font-awesome mr-2"></i> Font Awesome
                                                    </div>
                                                    <div class="icon-type-btn <?= ($isEdit && $cardData['icon_type'] == 'image') ? 'active' : '' ?>" onclick="setIconType('image')">
                                                        <i class="fas fa-image mr-2"></i> Upload Image
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="col-md-6 form-group mt-4">
                                                <label class="form-label">Color Class <span class="text-danger">*</span></label>
                                                <select name="color_class" class="form-control" required>
                                                    <?php $currentColor = $isEdit ? $cardData['color_class'] : 'card-blue'; ?>
                                                    <option value="card-blue" <?= $currentColor == 'card-blue' ? 'selected' : '' ?>>Blue</option>
                                                    <option value="card-green" <?= $currentColor == 'card-green' ? 'selected' : '' ?>>Green</option>
                                                    <option value="card-yellow" <?= $currentColor == 'card-yellow' ? 'selected' : '' ?>>Yellow</option>
                                                    <option value="card-pink" <?= $currentColor == 'card-pink' ? 'selected' : '' ?>>Pink</option>
                                                    <option value="card-cyan" <?= $currentColor == 'card-cyan' ? 'selected' : '' ?>>Cyan</option>
                                                    <option value="card-purple" <?= $currentColor == 'card-purple' ? 'selected' : '' ?>>Purple</option>
                                                    <option value="card-orange" <?= $currentColor == 'card-orange' ? 'selected' : '' ?>>Orange</option>
                                                    <option value="card-red" <?= $currentColor == 'card-red' ? 'selected' : '' ?>>Red</option>
                                                    <option value="card-dark" <?= $currentColor == 'card-dark' ? 'selected' : '' ?>>Dark</option>
                                                </select>
                                                <div class="form-text">Pre-defined color styles for the card</div>
                                            </div>
                                        </div>

                                        <div class="form-group" id="faSection" style="display: <?= (!$isEdit || $cardData['icon_type'] == 'fontawesome') ? 'block' : 'none' ?>;">
                                            <label class="form-label">Font Awesome Icon Class <span class="text-danger">*</span></label>
                                            <input type="text" name="icon" id="iconClassInput" class="form-control" value="<?= $isEdit ? htmlspecialchars($cardData['icon']) : 'fa-plane' ?>" onkeyup="updateLivePreview()">
                                            <div class="form-text">Class name for the icon (e.g. fa-plane, fa-umbrella-beach, fa-ship, fa-crown). Use Font Awesome 5 classes. Browse icons at <a href="https://fontawesome.com/v5/search?m=free" target="_blank">fontawesome.com</a></div>
                                        </div>

                                        <div class="form-group" id="imgSection" style="display: <?= ($isEdit && $cardData['icon_type'] == 'image') ? 'block' : 'none' ?>;">
                                            <label class="form-label">Upload Custom Icon</label>
                                            <input type="file" name="icon_image" class="form-control-file" accept="image/*" id="iconImgInput" onchange="previewUpload(this)">
                                            <div class="form-text mt-2">Recommended: SVG or PNG with transparent background.</div>
                                            <?php if($isEdit && !empty($cardData['icon_image'])): ?>
                                                <input type="hidden" id="currentImgUrl" value="<?= htmlspecialchars($cardData['icon_image']) ?>">
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="form-group mb-4">
                                            <label class="form-label">Display Order</label>
                                            <input type="number" name="display_order" class="form-control" min="1" value="<?= $isEdit ? $cardData['display_order'] : $nextOrder ?>">
                                            <div class="form-text">Order in which this card appears (lower numbers first)</div>
                                        </div>
                                        
                                        <div class="form-group mb-5">
                                            <div class="d-flex align-items-center">
                                                <label class="toggle-switch mr-2">
                                                    <input type="checkbox" name="is_active" <?= (!$isEdit || $cardData['is_active']) ? 'checked' : '' ?>>
                                                    <span class="toggle-slider"></span>
                                                </label>
                                                <span class="form-label mb-0" style="font-weight:normal; font-size:14px; color:#555;">Active</span>
                                            </div>
                                        </div>
                                        
                                        <div class="preview-box">
                                            <span>Icon Preview</span>
                                            <div id="livePreviewContainer">
                                                <?php if($isEdit && $cardData['icon_type'] == 'image'): ?>
                                                    <img src="<?= htmlspecialchars($cardData['icon_image']) ?>" class="live-img-preview">
                                                <?php else: ?>
                                                    <i class="fas <?= $isEdit ? htmlspecialchars($cardData['icon']) : 'fa-plane' ?> live-icon-preview" id="faPreviewIcon"></i>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <div class="d-flex mt-4">
                                            <button type="submit" name="save_card" class="btn-purple mr-2">
                                                <i class="fas fa-save mr-1"></i> <?= $isEdit ? 'Update Card' : 'Save Card' ?>
                                            </button>
                                            <a href="budget_cards.php" class="btn-dark-grey">
                                                <i class="fas fa-times mr-1"></i> Cancel
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <?php if($isEdit): ?>
                                <div class="info-box-custom">
                                    <div class="box-title"><i class="fas fa-info-circle mr-1"></i> Info</div>
                                    <ul class="box-list">
                                        <li><strong>Created:</strong> <?= date('d M Y', strtotime($cardData['created_at'])) ?></li>
                                        <li><strong>Last Updated:</strong> <?= date('d M Y', strtotime($cardData['updated_at'])) ?></li>
                                    </ul>
                                </div>
                                <?php endif; ?>
                                
                                <div class="tips-box-custom">
                                    <div class="box-title"><i class="far fa-lightbulb mr-1"></i> Tips</div>
                                    <ul class="box-list">
                                        <li>You can test icon names like <code>fa-plane</code>, <code>fa-car</code>, <code>fa-train</code>, etc.</li>
                                        <li>Use the <code>is_active</code> toggle to temporarily hide a card from the website.</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </section>
        </div>
        
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
                let cls = $('#iconClassInput').val().trim();
                if(!cls) cls = 'fa-plane';
                $('#livePreviewContainer').html(`<i class="fas ${cls} live-icon-preview" id="faPreviewIcon"></i>`);
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
</body>
</html>