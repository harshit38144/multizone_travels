<?php
session_start();
if ($_SESSION['role'] != '1') {
	header('location:index.php');
	exit;
}
include __DIR__ . '/connection.php';

$isEdit = false;
$counterData = null;

if (isset($_GET['edit_id'])) {
    $isEdit = true;
    $id = (int)$_GET['edit_id'];
    $counterData = $conn->query("SELECT * FROM live_counters WHERE id=$id")->fetch_assoc();
    if (!$counterData) {
        header("Location: live_counters.php");
        exit;
    }
}

$nextOrder = 1;
if (!$isEdit) {
    $r = $conn->query("SELECT COALESCE(MAX(display_order), 0) + 1 AS next_order FROM live_counters");
    if ($r) $nextOrder = (int)$r->fetch_assoc()['next_order'];
}

// Handle Form Submit
if (isset($_POST['save_counter'])) {
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $counter_value = mysqli_real_escape_string($conn, $_POST['counter_value']);
    $display_order = (int)$_POST['display_order'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    if ($isEdit) {
        $id = (int)$_POST['counter_id'];
        $sql = "UPDATE live_counters SET title='$title', counter_value='$counter_value', display_order=$display_order, is_active=$is_active WHERE id=$id";
        $conn->query($sql);
        $_SESSION['msg'] = "Counter updated successfully!";
    } else {
        $sql = "INSERT INTO live_counters (title, counter_value, display_order, is_active) VALUES ('$title', '$counter_value', $display_order, $is_active)";
        $conn->query($sql);
        $_SESSION['msg'] = "Counter added successfully!";
    }
    
    header("Location: live_counters.php");
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title><?= $isEdit ? 'Edit Counter' : 'Add New Counter' ?></title>
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
                                <i class="fas fa-edit mr-2" style="font-size:24px;"></i> <?= $isEdit ? 'Edit Live Counter' : 'Add Live Counter' ?>
                            </h1>
                            <ol class="breadcrumb mt-2 bg-transparent p-0">
                                <li class="breadcrumb-item"><a href="dashboard.php" style="color: #0d6efd;">Dashboard</a></li>
                                <li class="breadcrumb-item"><a href="live_counters.php" style="color: #0d6efd;">Live Counters</a></li>
                                <li class="breadcrumb-item active"><?= $isEdit ? 'Edit Counter' : 'Add Counter' ?></li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

            <section class="content">
                <div class="container-fluid">
                    <form method="POST" action="">
                        <?php if($isEdit): ?>
                            <input type="hidden" name="counter_id" value="<?= $counterData['id'] ?>">
                        <?php endif; ?>
                        
                        <div class="row">
                            <div class="col-md-8">
                                <div class="card form-card">
                                    <div class="card-header-purple"><i class="fas fa-info-circle mr-2"></i> Counter Information</div>
                                    <div class="card-body p-4">
                                        <div class="form-group mb-4">
                                            <label class="form-label">Title <span class="text-danger">*</span></label>
                                            <input type="text" name="title" class="form-control" required value="<?= $isEdit ? htmlspecialchars($counterData['title']) : '' ?>" placeholder="e.g. Trips Sold">
                                            <div class="form-text">The text that will appear with the counter</div>
                                        </div>
                                        
                                        <div class="form-group mb-4">
                                            <label class="form-label">Counter Value <span class="text-danger">*</span></label>
                                            <input type="text" name="counter_value" class="form-control" required value="<?= $isEdit ? htmlspecialchars($counterData['counter_value']) : '' ?>" placeholder="e.g. 2140">
                                            <div class="form-text">The number that will be animated (can use commas in display)</div>
                                        </div>
                                        
                                        <div class="form-group mb-4">
                                            <label class="form-label">Display Order</label>
                                            <input type="number" name="display_order" class="form-control" min="1" value="<?= $isEdit ? $counterData['display_order'] : $nextOrder ?>">
                                            <div class="form-text">Order in which this counter appears (lower numbers first)</div>
                                        </div>
                                        
                                        <div class="form-group mb-5">
                                            <div class="d-flex align-items-center">
                                                <label class="toggle-switch mr-2">
                                                    <input type="checkbox" name="is_active" <?= (!$isEdit || $counterData['is_active']) ? 'checked' : '' ?>>
                                                    <span class="toggle-slider"></span>
                                                </label>
                                                <span class="form-label mb-0" style="font-weight:normal; font-size:14px; color:#555;">Active</span>
                                            </div>
                                        </div>
                                        
                                        <div class="d-flex">
                                            <button type="submit" name="save_counter" class="btn-purple mr-2">
                                                <i class="fas fa-save mr-1"></i> <?= $isEdit ? 'Update Counter' : 'Save Counter' ?>
                                            </button>
                                            <a href="live_counters.php" class="btn-dark-grey">
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
                                        <li><strong>Created:</strong> <?= date('d M Y', strtotime($counterData['created_at'])) ?></li>
                                        <li><strong>Last Updated:</strong> <?= date('d M Y', strtotime($counterData['updated_at'])) ?></li>
                                    </ul>
                                </div>
                                <?php endif; ?>
                                
                                <div class="tips-box-custom">
                                    <div class="box-title"><i class="far fa-lightbulb mr-1"></i> Tips</div>
                                    <ul class="box-list">
                                        <li>Use descriptive titles</li>
                                        <li>Counter values will animate from 0</li>
                                        <li>You can have up to 4 active counters displayed</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </section>
        </div>

        <?php include __DIR__ . '/includes/footer-links.php'; ?>
    </div>
</body>
</html>
