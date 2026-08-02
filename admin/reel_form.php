<?php
session_start();
if ($_SESSION['role'] != '1') {
	header('location:index.php');
	exit;
}
include __DIR__ . '/connection.php';

$uploadDir = 'uploads/reels/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

$isEdit = false;
$reelData = null;

if (isset($_GET['edit_id'])) {
    $isEdit = true;
    $id = (int)$_GET['edit_id'];
    $reelData = $conn->query("SELECT * FROM instagram_reels WHERE id=$id")->fetch_assoc();
    if (!$reelData) {
        header("Location: instagram_reels.php");
        exit;
    }
}

$nextOrder = 1;
if (!$isEdit) {
    $r = $conn->query("SELECT COALESCE(MAX(display_order), 0) + 1 AS next_order FROM instagram_reels");
    if ($r) $nextOrder = (int)$r->fetch_assoc()['next_order'];
}

// Handle Form Submit
if (isset($_POST['save_reel'])) {
    $reel_url = mysqli_real_escape_string($conn, $_POST['reel_url']);
    $caption = mysqli_real_escape_string($conn, $_POST['caption']);
    $display_order = (int)$_POST['display_order'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    $thumbnail_sql = "";
    $thumbnail = "";

    // Handle Remove Thumbnail
    if (isset($_POST['remove_thumbnail']) && $_POST['remove_thumbnail'] == '1' && $isEdit) {
        if (!empty($reelData['thumbnail']) && file_exists($reelData['thumbnail'])) {
            unlink($reelData['thumbnail']);
        }
        $thumbnail_sql = ", thumbnail=''";
    }

    // Handle File Upload
    if (!empty($_FILES['thumbnail']['name'])) {
        $ext = strtolower(pathinfo($_FILES['thumbnail']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','webp','gif'];
        if (in_array($ext, $allowed)) {
            // Delete old file if updating
            if ($isEdit && empty($thumbnail_sql) && !empty($reelData['thumbnail']) && file_exists($reelData['thumbnail'])) {
                unlink($reelData['thumbnail']);
            }
            
            $filename = 'reel_' . uniqid() . '.' . $ext;
            if(move_uploaded_file($_FILES['thumbnail']['tmp_name'], $uploadDir . $filename)) {
                $thumbnail = $uploadDir . $filename;
                $thumbnail_sql = ", thumbnail='" . $thumbnail . "'";
            }
        } else {
            $_SESSION['msg'] = "Invalid image format for thumbnail!";
            $_SESSION['msg_type'] = "danger";
            header("Location: instagram_reels.php");
            exit;
        }
    }

    if ($isEdit) {
        $id = (int)$_POST['reel_id'];
        $sql = "UPDATE instagram_reels SET reel_url='$reel_url', caption='$caption', display_order=$display_order, is_active=$is_active $thumbnail_sql WHERE id=$id";
        $conn->query($sql);
        $_SESSION['msg'] = "Instagram reel updated successfully!";
    } else {
        $sql = "INSERT INTO instagram_reels (reel_url, caption, thumbnail, display_order, is_active) VALUES ('$reel_url', '$caption', '$thumbnail', $display_order, $is_active)";
        $conn->query($sql);
        $_SESSION['msg'] = "Instagram reel added successfully!";
    }
    
    header("Location: instagram_reels.php");
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title><?= $isEdit ? 'Edit Reel' : 'Add New Reel' ?></title>
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

        .btn-purple { background: linear-gradient(135deg, #6a11cb, #a855f7); color: #fff; border: none; font-weight: 600; padding: 10px 24px; border-radius: 6px; }
        .btn-purple:hover { opacity: 0.9; color: #fff; }
        
        .btn-dark-grey { background: #6b7280; color: #fff; border: none; font-weight: 600; padding: 10px 24px; border-radius: 6px; text-decoration: none; display: inline-block;}
        .btn-dark-grey:hover { background: #4b5563; color: #fff; text-decoration: none; }
        
        .thumbnail-preview { width: 80px; height: 110px; object-fit: cover; border-radius: 6px; border: 1px solid #ddd; }
        .remove-thumb-link { color: #dc3545; font-size: 13px; font-weight: 600; cursor: pointer; display: inline-block; margin-left: 15px; }
        .remove-thumb-link:hover { text-decoration: underline; }
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
                            <h1 class="m-0 text-dark"><i class="fab fa-instagram mr-2"></i> <?= $isEdit ? 'Edit Reel' : 'Add New Reel' ?></h1>
                        </div>
                        <div class="col-sm-6">
                            <ol class="breadcrumb float-sm-right">
                                <li class="breadcrumb-item"><a href="instagram_reels.php" style="color: #6a11cb;">Instagram Reels</a></li>
                                <li class="breadcrumb-item active"><?= $isEdit ? 'Edit' : 'Add' ?></li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

            <section class="content">
                <div class="container-fluid">
                    <form method="POST" action="" enctype="multipart/form-data">
                        <?php if($isEdit): ?>
                            <input type="hidden" name="reel_id" value="<?= $reelData['id'] ?>">
                            <input type="hidden" name="remove_thumbnail" id="removeThumbnail" value="0">
                        <?php endif; ?>
                        
                        <div class="row">
                            <!-- Left Column -->
                            <div class="col-md-8">
                                <div class="card form-card">
                                    <div class="card-header-purple">
                                        <i class="fab fa-instagram mr-2"></i> Reel Details
                                    </div>
                                    <div class="card-body">
                                        <div class="form-group">
                                            <label class="form-label">Instagram Reel URL <span class="text-danger">*</span></label>
                                            <input type="url" name="reel_url" class="form-control" required value="<?= $isEdit ? htmlspecialchars($reelData['reel_url']) : '' ?>" placeholder="https://www.instagram.com/reel/...">
                                        </div>
                                        
                                        <div class="form-group">
                                            <label class="form-label">Caption</label>
                                            <input type="text" name="caption" class="form-control" value="<?= $isEdit ? htmlspecialchars($reelData['caption']) : '' ?>" placeholder="e.g. Phu Quoc Arrival">
                                        </div>
                                        
                                        <div class="form-group mb-0">
                                            <label class="form-label">Thumbnail Image</label>
                                            
                                            <?php if($isEdit && !empty($reelData['thumbnail'])): ?>
                                                <div class="mb-3 d-flex align-items-center" id="currentThumbWrapper">
                                                    <img src="<?= htmlspecialchars($reelData['thumbnail']) ?>" class="thumbnail-preview" alt="Current Thumbnail">
                                                    <span class="remove-thumb-link" onclick="removeThumbnail()">Remove thumbnail</span>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <input type="file" name="thumbnail" class="form-control-file" accept="image/jpeg,image/png,image/webp,image/gif">
                                            <div class="form-text">Upload a new image to replace the existing one</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Right Column -->
                            <div class="col-md-4">
                                <div class="card form-card">
                                    <div class="card-header-purple">
                                        <i class="fas fa-cog mr-2"></i> Settings
                                    </div>
                                    <div class="card-body">
                                        <div class="form-group">
                                            <label class="form-label">Display Order</label>
                                            <input type="number" name="display_order" class="form-control" min="1" value="<?= $isEdit ? $reelData['display_order'] : $nextOrder ?>">
                                        </div>
                                        
                                        <hr>
                                        
                                        <div class="d-flex align-items-center">
                                            <label class="toggle-switch mr-2">
                                                <input type="checkbox" name="is_active" <?= (!$isEdit || $reelData['is_active']) ? 'checked' : '' ?>>
                                                <span class="toggle-slider"></span>
                                            </label>
                                            <span class="form-label mb-0" style="font-weight:normal; font-size:13px; color:#555;">Active (Visible on homepage)</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="text-center mt-3 mb-5">
                            <button type="submit" name="save_reel" class="btn-purple mr-2">
                                <i class="fas fa-save mr-1"></i> <?= $isEdit ? 'Update Reel' : 'Save Reel' ?>
                            </button>
                            <a href="instagram_reels.php" class="btn-dark-grey">
                                <i class="fas fa-times mr-1"></i> Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </section>
        </div>

        <?php include __DIR__ . '/includes/footer-links.php'; ?>
        
        <script>
            function removeThumbnail() {
                document.getElementById('currentThumbWrapper').style.display = 'none';
                document.getElementById('removeThumbnail').value = '1';
            }
        </script>

    </div>
</body>
</html>
