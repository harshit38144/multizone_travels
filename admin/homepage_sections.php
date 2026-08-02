<?php
session_start();
if ($_SESSION['role'] != '1') {
	header('location:index.php');
	exit;
}
include __DIR__ . '/connection.php';

// Create table if not exists
$table_sql = "CREATE TABLE IF NOT EXISTS `homepage_sections` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `section_key` VARCHAR(50) NOT NULL UNIQUE,
  `section_name` VARCHAR(100) NOT NULL,
  `section_heading` VARCHAR(255) DEFAULT NULL,
  `section_subheading` VARCHAR(500) DEFAULT NULL,
  `display_order` INT NOT NULL DEFAULT 0,
  `bg_color` VARCHAR(20) DEFAULT 'transparent',
  `section_image` VARCHAR(255) DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `is_locked` TINYINT(1) NOT NULL DEFAULT 0,
  `default_bg_color` VARCHAR(20) DEFAULT 'transparent',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($table_sql);

// Add new columns to existing table if they are missing
$check_cols = $conn->query("SHOW COLUMNS FROM `homepage_sections` LIKE 'section_image'");
if($check_cols->num_rows == 0) {
    $conn->query("ALTER TABLE `homepage_sections` ADD `section_image` VARCHAR(255) DEFAULT NULL AFTER `bg_color`");
}

$check_heading = $conn->query("SHOW COLUMNS FROM `homepage_sections` LIKE 'section_heading'");
$added_headings = false;
if ($check_heading && $check_heading->num_rows == 0) {
    $conn->query("ALTER TABLE `homepage_sections` ADD `section_heading` VARCHAR(255) DEFAULT NULL AFTER `section_name`, ADD `section_subheading` VARCHAR(500) DEFAULT NULL AFTER `section_heading`");
    $added_headings = true;
}

/** One-time defaults for headings when columns are added */
if ($added_headings) {
    $seed = [
        'slider' => [null, null],
        'trust_badges' => [null, null],
        'group_departures' => ['Group Departures', 'Fixed departure group tours with guaranteed dates'],
        'categories' => ['Explore by Categories', 'Find your perfect vacation'],
        'trending_destinations' => ['TRENDING DESTINATIONS', 'Explore our most popular holiday destinations'],
        'trending_packages' => ['Trending packages', 'Handpicked tours for you'],
        'features' => ['Why Plan Your Travel With Us?', 'Experience the difference with our exceptional services'],
        'secondary_features' => ['Travel With Confidence', 'Everything you need for a smooth trip—from the first quote to the flight home.'],
        'budget_filter' => ['HOLIDAYS FOR EVERY Budget', 'Choose your perfect getaway within your budget range'],
        'live_counter' => ['Adrenaliverse Live: Journeys In Motion', 'See where the world is travelling right now.'],
        'instagram_reels' => ['LOVE FROM THE GRAM ❤', 'Real stories from real travellers'],
        'testimonials' => ['We Care About Our Customers Experience Too', 'Hear directly from travelers who explored with us.'],
    ];
    foreach ($seed as $sk => $pair) {
        $h = $pair[0];
        $s = $pair[1];
        $skEsc = mysqli_real_escape_string($conn, $sk);
        if ($h === null && $s === null) {
            $conn->query("UPDATE homepage_sections SET section_heading=NULL, section_subheading=NULL WHERE section_key='$skEsc'");
        } else {
            $hEsc = $h === null ? 'NULL' : "'" . mysqli_real_escape_string($conn, $h) . "'";
            $sEsc = $s === null ? 'NULL' : "'" . mysqli_real_escape_string($conn, $s) . "'";
            $conn->query("UPDATE homepage_sections SET section_heading=$hEsc, section_subheading=$sEsc WHERE section_key='$skEsc'");
        }
    }
}

// Check if table is empty and insert defaults
$check_empty = $conn->query("SELECT COUNT(*) as count FROM homepage_sections")->fetch_assoc();
if ($check_empty['count'] == 0) {
    $defaults = [
        "('slider', 'Hero Slider', NULL, NULL, 1, 'transparent', 1, 1, 'transparent')",
        "('trust_badges', 'Trust Badges Bar', NULL, NULL, 2, '#000000', 1, 1, '#000000')",
        "('group_departures', 'Group Departures', 'Group Departures', 'Fixed departure group tours with guaranteed dates', 3, '#fff5f5', 1, 0, '#fff5f5')",
        "('categories', 'Explore by Categories', 'Explore by Categories', 'Find your perfect vacation', 4, '#ffffff', 1, 0, '#ffffff')",
        "('trending_destinations', 'Trending Destinations', 'TRENDING DESTINATIONS', 'Explore our most popular holiday destinations', 5, 'transparent', 1, 0, 'transparent')",
        "('trending_packages', 'Trending Packages', 'Trending Packages', 'Handpicked tours for you', 6, '#fcfcfc', 1, 0, '#fcfcfc')",
        "('features', 'Why Plan Your Travel With Us?', 'Why Plan Your Travel With Us?', 'Experience the difference with our exceptional services', 7, '#ffffff', 1, 0, '#ffffff')",
        "('secondary_features', 'Secondary Features Strip', 'Travel With Confidence', 'Everything you need for a smooth trip—from the first quote to the flight home.', 8, '#f8fafc', 1, 0, '#f8fafc')",
        "('budget_filter', 'Budget Filter Section', 'HOLIDAYS FOR EVERY Budget', 'Choose your perfect getaway within your budget range', 9, '#ffffff', 1, 0, '#ffffff')",
        "('live_counter', 'Adrenaliverse Live: Journeys In Motion', 'Adrenaliverse Live: Journeys In Motion', 'See where the world is travelling right now.', 10, '#ffffff', 1, 0, '#ffffff')",
        "('instagram_reels', 'Love From The Gram', 'LOVE FROM THE GRAM ❤', 'Real stories from real travellers', 11, '#fdf6f0', 1, 0, '#fdf6f0')",
        "('testimonials', 'Testimonials', 'We Care About Our Customers Experience Too', 'Hear directly from travelers who explored with us.', 12, '#f4f5f8', 1, 0, '#f4f5f8')"
    ];
    $conn->query("INSERT INTO homepage_sections (section_key, section_name, section_heading, section_subheading, display_order, bg_color, is_active, is_locked, default_bg_color) VALUES " . implode(", ", $defaults));
}

// Insert new sections for existing installations (INSERT IGNORE skips if section_key already exists)
$conn->query("INSERT IGNORE INTO homepage_sections (section_key, section_name, section_heading, section_subheading, display_order, bg_color, is_active, is_locked, default_bg_color) VALUES
    ('live_counter', 'Adrenaliverse Live: Journeys In Motion', 'Adrenaliverse Live: Journeys In Motion', 'See where the world is travelling right now.', 9, '#ffffff', 1, 0, '#ffffff'),
    ('instagram_reels', 'Love From The Gram', 'LOVE FROM THE GRAM ❤', 'Real stories from real travellers', 10, '#fdf6f0', 1, 0, '#fdf6f0'),
    ('testimonials', 'Testimonials', 'We Care About Our Customers Experience Too', 'Hear directly from travelers who explored with us.', 12, '#f4f5f8', 1, 0, '#f4f5f8')");

// Secondary features strip — own homepage section (order after primary features); shift following rows if newly inserted
$conn->query("INSERT IGNORE INTO homepage_sections (section_key, section_name, section_heading, section_subheading, display_order, bg_color, is_active, is_locked, default_bg_color) VALUES ('secondary_features', 'Secondary Features Strip', 'Travel With Confidence', 'Everything you need for a smooth trip—from the first quote to the flight home.', 8, '#f8fafc', 1, 0, '#f8fafc')");
if ($conn->affected_rows === 1) {
    $conn->query("UPDATE homepage_sections SET display_order = CASE section_key
        WHEN 'budget_filter' THEN 9
        WHEN 'live_counter' THEN 10
        WHEN 'instagram_reels' THEN 11
        WHEN 'testimonials' THEN 12
        ELSE display_order END
        WHERE section_key IN ('budget_filter','live_counter','instagram_reels','testimonials')");
}

// Normalize order if secondary_features exists but budgets still share display_order 8 (partial upgrade)
$dups = @$conn->query("SELECT COUNT(*) AS c FROM homepage_sections WHERE section_key IN ('secondary_features','budget_filter') AND display_order = 8");
if ($dups && ($dupRow = $dups->fetch_assoc()) && (int)$dupRow['c'] >= 2) {
    $conn->query("UPDATE homepage_sections SET display_order = CASE section_key
        WHEN 'budget_filter' THEN 9
        WHEN 'live_counter' THEN 10
        WHEN 'instagram_reels' THEN 11
        WHEN 'testimonials' THEN 12
        ELSE display_order END
        WHERE section_key IN ('budget_filter','live_counter','instagram_reels','testimonials')");
}

$msg = "";
$msg_type = "success";

$uploadDir = 'uploads/sections/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

// Handle form submission (Update all sections)
if (isset($_POST['update_sections'])) {
    if (isset($_POST['sections']) && is_array($_POST['sections'])) {
        foreach ($_POST['sections'] as $id => $data) {
            $id = (int)$id;
            $order = (int)$data['order'];
            $bg_color = mysqli_real_escape_string($conn, $data['bg_color']);
            $heading_raw = isset($data['heading']) ? trim((string)$data['heading']) : '';
            $subheading_raw = isset($data['subheading']) ? trim((string)$data['subheading']) : '';
            $heading_sql = "'" . mysqli_real_escape_string($conn, $heading_raw) . "'";
            $subheading_sql = "'" . mysqli_real_escape_string($conn, $subheading_raw) . "'";
            
            // Check if section is locked before updating status (locked sections are always active)
            $check_lock = $conn->query("SELECT is_locked FROM homepage_sections WHERE id=$id")->fetch_assoc();
            if ($check_lock['is_locked'] == 1) {
                $is_active = 1;
            } else {
                $is_active = isset($data['is_active']) ? 1 : 0;
            }

            $image_sql = "";
            if (isset($_FILES['section_images']['name'][$id]) && !empty($_FILES['section_images']['name'][$id])) {
                $ext = strtolower(pathinfo($_FILES['section_images']['name'][$id], PATHINFO_EXTENSION));
                $allowed = ['jpg','jpeg','png','webp','gif','svg'];
                if (in_array($ext, $allowed)) {
                    $filename = 'sec_' . uniqid() . '.' . $ext;
                    if(move_uploaded_file($_FILES['section_images']['tmp_name'][$id], $uploadDir . $filename)) {
                        $section_image = $uploadDir . $filename;
                        $image_sql = ", section_image='" . $section_image . "'";
                        
                        // Optionally delete old file
                        $oldImg = $conn->query("SELECT section_image FROM homepage_sections WHERE id=$id")->fetch_assoc();
                        if(!empty($oldImg['section_image']) && file_exists($oldImg['section_image'])) {
                            unlink($oldImg['section_image']);
                        }
                    }
                }
            }

            $update_sql = "UPDATE homepage_sections SET display_order=$order, bg_color='$bg_color', section_heading=$heading_sql, section_subheading=$subheading_sql, is_active=$is_active $image_sql WHERE id=$id";
            $conn->query($update_sql);
        }
        $_SESSION['msg'] = "Homepage sections updated successfully!";
        header("Location: homepage_sections.php");
        exit;
    }
}

// Handle Reset
if (isset($_GET['reset_id'])) {
    $id = (int)$_GET['reset_id'];
    $conn->query("UPDATE homepage_sections SET bg_color = default_bg_color WHERE id=$id");
    $_SESSION['msg'] = "Section background color reset to default!";
    header("Location: homepage_sections.php");
    exit;
}

// Handle Toggle Lock
if (isset($_GET['toggle_lock_id'])) {
    $id = (int)$_GET['toggle_lock_id'];
    $conn->query("UPDATE homepage_sections SET is_locked = IF(is_locked=1, 0, 1) WHERE id=$id");
    $_SESSION['msg'] = "Section lock status updated!";
    header("Location: homepage_sections.php");
    exit;
}

// Handle Toggle Status (AJAX or direct link)
if (isset($_GET['toggle_id'])) {
    $id = (int)$_GET['toggle_id'];
    
    $check_lock = $conn->query("SELECT is_locked FROM homepage_sections WHERE id=$id")->fetch_assoc();
    if ($check_lock['is_locked'] == 0) {
        $conn->query("UPDATE homepage_sections SET is_active = IF(is_active=1, 0, 1) WHERE id=$id");
        $_SESSION['msg'] = "Section status updated!";
    } else {
        $_SESSION['msg'] = "Cannot disable locked section!";
        $_SESSION['msg_type'] = "danger";
    }
    header("Location: homepage_sections.php");
    exit;
}

// Session messages
if (isset($_SESSION['msg'])) {
    $msg = $_SESSION['msg'];
    $msg_type = isset($_SESSION['msg_type']) ? $_SESSION['msg_type'] : 'success';
    unset($_SESSION['msg'], $_SESSION['msg_type']);
}

// Fetch sections
$sections_query = $conn->query("SELECT * FROM homepage_sections ORDER BY display_order ASC");
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Homepage Sections Manager</title>
    <?php include __DIR__ . '/includes/header-links.php'; ?>

    <style>
        .page-bg { background-color: #f4f6f9; }
        
        .section-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.05);
            background: #fff;
            margin-bottom: 30px;
        }
        .section-card-header {
            background: linear-gradient(135deg, #6a11cb 0%, #7b5ea7 50%, #a855f7 100%);
            color: #fff;
            padding: 16px 24px;
            border-radius: 12px 12px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .section-card-header h5 { margin: 0; font-size: 16px; font-weight: 600; }
        
        .sections-table { margin: 0; }
        .sections-table th {
            background: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            color: #495057;
            font-size: 13px;
            text-transform: uppercase;
            font-weight: 600;
            padding: 12px 16px;
        }
        .sections-table td {
            vertical-align: middle;
            padding: 16px;
            border-bottom: 1px solid #eee;
        }
        
        .order-handle {
            display: flex; align-items: center; gap: 10px;
        }
        .order-badge {
            background: #6a11cb; color: #fff;
            width: 24px; height: 24px; border-radius: 4px;
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 12px; font-weight: bold;
        }
        .drag-icon { color: #aaa; cursor: grab; font-size: 18px; }
        
        .section-title { font-weight: 600; color: #333; margin-bottom: 2px; }
        .section-key { font-size: 12px; color: #888; font-family: monospace; }
        
        /* Sortable placeholder */
        .ui-state-highlight { height: 60px; background: #f8f9fa; border: 1px dashed #ccc; }
        
        /* Color input styling */
        .color-picker-wrapper {
            display: flex; align-items: center; gap: 8px;
            background: #f8f9fa; border: 1px solid #ddd; border-radius: 6px; padding: 4px;
            max-width: 200px;
        }
        .color-picker-input {
            width: 26px; height: 26px; border: none; padding: 0; background: none; cursor: pointer; border-radius: 4px; overflow: hidden;
        }
        .color-picker-input::-webkit-color-swatch-wrapper { padding: 0; }
        .color-picker-input::-webkit-color-swatch { border: 1px solid #ccc; border-radius: 4px; }
        .color-input {
            border: none; background: transparent; outline: none; width: 100%;
            font-size: 13px; font-family: monospace; color: #555;
        }
        
        /* Toggle Switch */
        .toggle-switch { position: relative; display: inline-block; width: 44px; height: 24px; margin: 0; }
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
        .toggle-switch input:disabled + .toggle-slider { opacity: 0.6; cursor: not-allowed; }
        
        /* Lock Badges */
        .badge-locked { background: #fef08a; color: #854d0e; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; display: inline-block;}
        .badge-unlocked { background: #dcfce7; color: #166534; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; display: inline-block;}
        
        /* Reset button */
        .btn-reset {
            background: transparent; color: #6b7280; border: 1px solid #d1d5db;
            padding: 4px 12px; border-radius: 6px; font-size: 12px; transition: all 0.2s;
            text-decoration: none; display: inline-flex; align-items: center; gap: 5px;
        }
        .btn-reset:hover { background: #f3f4f6; color: #374151; text-decoration: none;}
        
        .btn-save-all {
            background: #fff; color: #6a11cb; border: none; font-weight: 600;
            padding: 6px 16px; border-radius: 6px; font-size: 14px;
        }
        .btn-save-all:hover { background: #f8f9fa; }
        
        .order-input {
            width: 50px; text-align: center; border: 1px solid #ddd; border-radius: 4px; padding: 2px 5px;
        }
    </style>
</head>

<body class="hold-transition sidebar-mini layout-fixed page-bg">
    <div class="wrapper">

        <?php include __DIR__ . '/includes/top-header.php'; ?>
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <div class="content-wrapper">
            <?php include __DIR__ . '/includes/page-header.php'; ?>

            <section class="content">
                <div class="container-fluid">

                    <?php if (!empty($msg)) { ?>
                        <div class="alert alert-<?= $msg_type ?> alert-dismissible fade show shadow-sm" style="border-radius:8px;">
                            <?= $msg; ?>
                            <button type="button" class="close" data-dismiss="alert">&times;</button>
                        </div>
                    <?php } ?>
                    
                    <div class="mb-4">
                        <h4 style="font-weight: 600; color: #333;">Homepage Layout Settings</h4>
                        <p class="text-muted">Manage the order, visibility, headings, and background colors of sections on the homepage.</p>
                    </div>

                    <form method="POST" action="" enctype="multipart/form-data">
                        <div class="card section-card">
                            <div class="section-card-header">
                                <h5><i class="fas fa-layer-group mr-2"></i> Sections</h5>
                                <button type="submit" name="update_sections" id="saveSectionsBtn" class="btn-save-all">
                                    <i class="fas fa-save mr-1"></i> Save Changes
                                </button>
                            </div>
                            
                            <div class="card-body p-0 table-responsive">
                                <table class="table sections-table table-hover">
                                    <thead>
                                        <tr>
                                            <th style="width: 80px;">Order</th>
                                            <th style="min-width: 220px;">Section Name</th>
                                            <th style="min-width: 280px;">Heading &amp; subtitle</th>
                                            <th style="width: 250px;">Background / Image</th>
                                            <th style="width: 100px;" class="text-center">Status</th>
                                            <th style="width: 120px;" class="text-center">Locked</th>
                                            <th style="width: 100px;" class="text-center">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="sortable-sections">
                                        <?php while ($row = $sections_query->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <div class="order-handle" style="cursor: grab;">
                                                    <span class="order-badge"><?= $row['display_order'] ?></span>
                                                    <input type="hidden" name="sections[<?= $row['id'] ?>][order]" value="<?= $row['display_order'] ?>" class="order-input">
                                                </div>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <?php if($row['is_locked']): ?>
                                                        <i class="fas fa-lock text-warning" style="margin-right: 8px;"></i>
                                                    <?php else: ?>
                                                        <i class="fas fa-grip-horizontal text-secondary" style="margin-right: 8px;"></i>
                                                    <?php endif; ?>
                                                    <div>
                                                        <div class="section-title"><?= htmlspecialchars($row['section_name']) ?></div>
                                                        <div class="section-key"><?= htmlspecialchars($row['section_key']) ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <label class="d-block mb-1" style="font-size:11px;font-weight:600;color:#6c757d;">Heading</label>
                                                <input type="text" name="sections[<?= $row['id'] ?>][heading]" class="form-control form-control-sm mb-2"
                                                    value="<?= htmlspecialchars($row['section_heading'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Main title (optional)">
                                                <label class="d-block mb-1" style="font-size:11px;font-weight:600;color:#6c757d;">Subtitle</label>
                                                <textarea name="sections[<?= $row['id'] ?>][subheading]" class="form-control form-control-sm" rows="2"
                                                    placeholder="Supporting line (optional)"><?= htmlspecialchars($row['section_subheading'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                                            </td>
                                            <td>
                                                <div class="color-picker-wrapper mb-2">
                                                    <?php 
                                                        $hex = strtolower($row['bg_color']);
                                                        if($hex == 'transparent' || $hex == '') $hex = '#ffffff';
                                                        if(preg_match('/^#[0-9a-f]{3}$/i', $hex)) {
                                                            $hex = '#' . $hex[1].$hex[1] . $hex[2].$hex[2] . $hex[3].$hex[3];
                                                        }
                                                        if(!preg_match('/^#[0-9a-f]{6}$/i', $hex)) {
                                                            $hex = '#ffffff'; // Fallback for invalid hex strings
                                                        }
                                                    ?>
                                                    <input type="color" class="color-picker-input" value="<?= $hex ?>" oninput="updateHexInput(this)">
                                                    <input type="text" name="sections[<?= $row['id'] ?>][bg_color]" value="<?= htmlspecialchars($row['bg_color']) ?>" class="color-input" oninput="updateColorPicker(this)">
                                                </div>
                                                
                                                <?php if($row['section_key'] == 'budget_filter'): ?>
                                                    <div class="mt-2 p-2 bg-light border rounded">
                                                        <label class="d-block text-muted" style="font-size:12px;font-weight:600;margin-bottom:4px;">Section Hero Image:</label>
                                                        <?php if(!empty($row['section_image'])): ?>
                                                            <div class="mb-2">
                                                                <img src="<?= htmlspecialchars($row['section_image']) ?>" style="height:40px; border-radius:4px; border:1px solid #ddd; padding:2px; background:#fff;">
                                                            </div>
                                                        <?php endif; ?>
                                                        <input type="file" name="section_images[<?= $row['id'] ?>]" class="form-control-file" style="font-size:11px;" accept="image/*">
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <label class="toggle-switch">
                                                    <input type="checkbox" name="sections[<?= $row['id'] ?>][is_active]" <?= $row['is_active'] ? 'checked' : '' ?> <?= $row['is_locked'] ? 'disabled' : '' ?> onchange="document.getElementById('saveSectionsBtn').click();">
                                                    <span class="toggle-slider"></span>
                                                </label>
                                                <?php if($row['is_locked']): ?>
                                                    <!-- Keep value for locked items since disabled input isn't submitted -->
                                                    <input type="hidden" name="sections[<?= $row['id'] ?>][is_active]" value="1">
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <a href="homepage_sections.php?toggle_lock_id=<?= $row['id'] ?>" style="text-decoration:none;">
                                                    <?php if ($row['is_locked']): ?>
                                                        <span class="badge-locked" style="cursor:pointer;"><i class="fas fa-lock mr-1"></i> Locked</span>
                                                    <?php else: ?>
                                                        <span class="badge-unlocked" style="cursor:pointer;"><i class="fas fa-lock-open mr-1"></i> Unlocked</span>
                                                    <?php endif; ?>
                                                </a>
                                            </td>
                                            <td class="text-center">
                                                <a href="homepage_sections.php?reset_id=<?= $row['id'] ?>" class="btn-reset" onclick="return confirm('Reset background color to default?');">
                                                    <i class="fas fa-undo-alt"></i> Reset
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </form>

                </div>
            </section>
        </div>

        <?php include __DIR__ . '/includes/footer-links.php'; ?>
        
        <!-- jQuery UI for Drag and Drop -->
        <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>

        <script>
            $(function() {
                // Initialize drag and drop sorting
                $("#sortable-sections").sortable({
                    handle: ".order-handle",
                    placeholder: "ui-state-highlight",
                    helper: function(e, ui) {
                        ui.children().each(function() {
                            $(this).width($(this).width());
                        });
                        return ui;
                    },
                    update: function(event, ui) {
                        // Re-calculate order numbers
                        $('#sortable-sections tr').each(function(index) {
                            let newOrder = index + 1;
                            $(this).find('.order-badge').text(newOrder);
                            $(this).find('.order-input').val(newOrder);
                        });
                    }
                });
            });

            function updateHexInput(colorPicker) {
                let hexInput = colorPicker.nextElementSibling;
                hexInput.value = colorPicker.value;
            }

            function updateColorPicker(hexInput) {
                let colorPicker = hexInput.previousElementSibling;
                let val = hexInput.value;
                if(val.match(/^#[0-9a-fA-F]{6}$/)) {
                    colorPicker.value = val;
                } else if(val.match(/^#[0-9a-fA-F]{3}$/)) {
                    colorPicker.value = '#' + val[1]+val[1] + val[2]+val[2] + val[3]+val[3];
                }
            }
        </script>

    </div>
</body>
</html>
