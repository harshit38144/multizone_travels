<?php
session_start();
if ($_SESSION['role'] != '1') {
	header('location:index.php');
	exit;
}
include __DIR__ . '/connection.php';

$uploadDir = 'uploads/settings/';
if (!is_dir($uploadDir))
    mkdir($uploadDir, 0777, true);

// Initialize Table
$table_sql = "CREATE TABLE IF NOT EXISTS `site_settings` (
  `id` INT PRIMARY KEY DEFAULT 1,
  `site_title` VARCHAR(255) DEFAULT 'Multizone Travels',
  `site_tagline` VARCHAR(255) DEFAULT 'Explore the World with Us',
  `meta_description` TEXT DEFAULT NULL,
  `meta_keywords` TEXT DEFAULT NULL,
  `logo_path` VARCHAR(255) DEFAULT NULL,
  `favicon_path` VARCHAR(255) DEFAULT NULL,
  `primary_color` VARCHAR(50) DEFAULT '#008000',
  `secondary_color` VARCHAR(50) DEFAULT '#000080',
  `accent_color` VARCHAR(50) DEFAULT '#f08080',
  `header_scroll_color` VARCHAR(50) DEFAULT '#000000',
  `header_text_color` VARCHAR(50) DEFAULT '#ffffff',
  `facebook_url` VARCHAR(255) DEFAULT NULL,
  `twitter_url` VARCHAR(255) DEFAULT NULL,
  `instagram_url` VARCHAR(255) DEFAULT NULL,
  `linkedin_url` VARCHAR(255) DEFAULT NULL,
  `youtube_url` VARCHAR(255) DEFAULT NULL,
  `footer_about_text` TEXT DEFAULT NULL,
  `copyright_text` VARCHAR(255) DEFAULT '© 2026 Multizone Travels. All Rights Reserved.',
  `footer_address` TEXT DEFAULT NULL,
  `footer_phone` VARCHAR(120) DEFAULT NULL,
  `footer_email` VARCHAR(255) DEFAULT NULL,
  `footer_working_hours` VARCHAR(255) DEFAULT NULL,
  `footer_newsletter_heading` VARCHAR(255) DEFAULT NULL,
  `footer_newsletter_placeholder` VARCHAR(255) DEFAULT NULL,
  `whatsapp_phone` VARCHAR(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($table_sql);

// Add header_text_color column if it doesn't exist
$check_col = $conn->query("SHOW COLUMNS FROM `site_settings` LIKE 'header_text_color'");
if ($check_col->num_rows == 0) {
    $conn->query("ALTER TABLE `site_settings` ADD `header_text_color` VARCHAR(50) DEFAULT '#ffffff' AFTER `header_scroll_color`");
}

$footerExtras = [
    'footer_address' => "TEXT DEFAULT NULL AFTER `copyright_text`",
    'footer_phone' => "VARCHAR(120) DEFAULT NULL AFTER `footer_address`",
    'footer_email' => "VARCHAR(255) DEFAULT NULL AFTER `footer_phone`",
    'footer_working_hours' => "VARCHAR(255) DEFAULT NULL AFTER `footer_email`",
    'footer_newsletter_heading' => "VARCHAR(255) DEFAULT NULL AFTER `footer_working_hours`",
    'footer_newsletter_placeholder' => "VARCHAR(255) DEFAULT NULL AFTER `footer_newsletter_heading`",
    'whatsapp_phone' => "VARCHAR(50) DEFAULT NULL AFTER `footer_newsletter_placeholder`",
];
foreach ($footerExtras as $col => $ddl) {
    $chk = $conn->query("SHOW COLUMNS FROM `site_settings` LIKE '" . $conn->real_escape_string($col) . "'");
    if ($chk && $chk->num_rows == 0) {
        $conn->query("ALTER TABLE `site_settings` ADD `$col` $ddl");
    }
}

// Insert default row if not exists
$check = $conn->query("SELECT id FROM site_settings WHERE id=1");
if ($check->num_rows == 0) {
    $conn->query("INSERT INTO site_settings (id) VALUES (1)");
}

// Handle Form Submit
if (isset($_POST['save_settings'])) {
    $site_title = mysqli_real_escape_string($conn, $_POST['site_title']);
    $site_tagline = mysqli_real_escape_string($conn, $_POST['site_tagline']);
    $meta_description = mysqli_real_escape_string($conn, $_POST['meta_description']);
    $meta_keywords = mysqli_real_escape_string($conn, $_POST['meta_keywords']);

    $primary_color = mysqli_real_escape_string($conn, $_POST['primary_color']);
    $secondary_color = mysqli_real_escape_string($conn, $_POST['secondary_color']);
    $accent_color = mysqli_real_escape_string($conn, $_POST['accent_color']);
    $header_scroll_color = mysqli_real_escape_string($conn, $_POST['header_scroll_color']);
    $header_text_color = mysqli_real_escape_string($conn, $_POST['header_text_color']);

    $facebook_url = mysqli_real_escape_string($conn, $_POST['facebook_url']);
    $twitter_url = mysqli_real_escape_string($conn, $_POST['twitter_url']);
    $instagram_url = mysqli_real_escape_string($conn, $_POST['instagram_url']);
    $linkedin_url = mysqli_real_escape_string($conn, $_POST['linkedin_url']);
    $youtube_url = mysqli_real_escape_string($conn, $_POST['youtube_url']);

    $footer_about_text = mysqli_real_escape_string($conn, $_POST['footer_about_text']);
    $copyright_text = mysqli_real_escape_string($conn, $_POST['copyright_text']);

    $footer_address = mysqli_real_escape_string($conn, $_POST['footer_address'] ?? '');
    $footer_phone = mysqli_real_escape_string($conn, $_POST['footer_phone'] ?? '');
    $footer_email = mysqli_real_escape_string($conn, $_POST['footer_email'] ?? '');
    $footer_working_hours = mysqli_real_escape_string($conn, $_POST['footer_working_hours'] ?? '');
    $footer_newsletter_heading = mysqli_real_escape_string($conn, $_POST['footer_newsletter_heading'] ?? '');
    $footer_newsletter_placeholder = mysqli_real_escape_string($conn, $_POST['footer_newsletter_placeholder'] ?? '');
    $whatsapp_phone = mysqli_real_escape_string($conn, $_POST['whatsapp_phone'] ?? '');

    $image_sql = "";

    // Fetch current to delete old files if new ones uploaded
    $current = $conn->query("SELECT logo_path, favicon_path FROM site_settings WHERE id=1")->fetch_assoc();

    if (!empty($_FILES['logo']['name'])) {
        $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'svg'])) {
            $filename = 'logo_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['logo']['tmp_name'], $uploadDir . $filename)) {
                if (!empty($current['logo_path']) && file_exists($current['logo_path']))
                    unlink($current['logo_path']);
                $image_sql .= ", logo_path='" . $uploadDir . $filename . "'";
            }
        }
    }

    if (!empty($_FILES['favicon']['name'])) {
        $ext = strtolower(pathinfo($_FILES['favicon']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'ico', 'webp'])) {
            $filename = 'favicon_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['favicon']['tmp_name'], $uploadDir . $filename)) {
                if (!empty($current['favicon_path']) && file_exists($current['favicon_path']))
                    unlink($current['favicon_path']);
                $image_sql .= ", favicon_path='" . $uploadDir . $filename . "'";
            }
        }
    }

    $sql = "UPDATE site_settings SET 
            site_title='$site_title', site_tagline='$site_tagline', meta_description='$meta_description', meta_keywords='$meta_keywords',
            primary_color='$primary_color', secondary_color='$secondary_color', accent_color='$accent_color', header_scroll_color='$header_scroll_color', header_text_color='$header_text_color',
            facebook_url='$facebook_url', twitter_url='$twitter_url', instagram_url='$instagram_url', linkedin_url='$linkedin_url', youtube_url='$youtube_url',
            footer_about_text='$footer_about_text', copyright_text='$copyright_text',
            footer_address='$footer_address', footer_phone='$footer_phone', footer_email='$footer_email', footer_working_hours='$footer_working_hours',
            footer_newsletter_heading='$footer_newsletter_heading', footer_newsletter_placeholder='$footer_newsletter_placeholder',
            whatsapp_phone='$whatsapp_phone' $image_sql 
            WHERE id=1";

    if ($conn->query($sql)) {
        $_SESSION['msg'] = "Site settings updated successfully!";
    } else {
        $_SESSION['msg'] = "Error updating settings.";
        $_SESSION['msg_type'] = "danger";
    }
    header("Location: site_settings.php");
    exit;
}

$settings = $conn->query("SELECT * FROM site_settings WHERE id=1")->fetch_assoc();
if (!$settings) {
    $settings = [];
}

$msg = "";
$msg_type = "success";
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
    <title>Site Settings</title>
    <?php include __DIR__ . '/includes/header-links.php'; ?>

    <style>
        .page-bg {
            background-color: #f4f6f9;
        }

        .card-header-purple {
            background: linear-gradient(135deg, #6a11cb 0%, #7b5ea7 50%, #a855f7 100%);
            color: #fff;
            padding: 12px 20px;
            border-radius: 8px 8px 0 0 !important;
            font-size: 15px;
            font-weight: 600;
        }

        .form-card {
            border: none;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 25px;
            background: #faf8f5;
        }

        .form-label {
            font-weight: 600;
            color: #444;
            font-size: 13px;
            margin-bottom: 6px;
        }

        .form-control {
            font-size: 14px;
        }

        .form-text {
            font-size: 11px;
            color: #888;
            margin-top: 4px;
        }

        .btn-purple {
            background: linear-gradient(135deg, #6a11cb, #a855f7);
            color: #fff;
            border: none;
            font-weight: 600;
            padding: 10px 30px;
            border-radius: 6px;
            font-size: 15px;
            transition: 0.2s;
        }

        .btn-purple:hover {
            opacity: 0.9;
            color: #fff;
            transform: translateY(-1px);
        }

        .color-picker-wrapper {
            display: flex;
            align-items: center;
            border: 1px solid #ced4da;
            border-radius: 4px;
            overflow: hidden;
            background: #fff;
        }

        .color-picker-input {
            border: none;
            padding: 0;
            width: 40px;
            height: 38px;
            cursor: pointer;
        }

        .color-hex-input {
            border: none;
            outline: none;
            padding: 5px 10px;
            flex: 1;
            font-family: monospace;
            font-size: 13px;
        }

        .img-preview-box {
            border: 1px dashed #ccc;
            padding: 10px;
            border-radius: 6px;
            background: #fff;
            display: inline-block;
            margin-top: 10px;
            min-width: 120px;
            text-align: center;
        }

        .img-preview-box img {
            max-height: 60px;
            max-width: 100%;
            object-fit: contain;
        }

        /* Two column custom layout matching screenshot */
        .layout-row {
            display: flex;
            flex-wrap: wrap;
            margin-left: -12.5px;
            margin-right: -12.5px;
        }

        .layout-col-main {
            flex: 0 0 65%;
            max-width: 65%;
            padding: 0 12.5px;
        }

        .layout-col-side {
            flex: 0 0 35%;
            max-width: 35%;
            padding: 0 12.5px;
        }

        @media (max-width: 992px) {

            .layout-col-main,
            .layout-col-side {
                flex: 0 0 100%;
                max-width: 100%;
            }
        }
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
                            <h1 class="m-0 text-dark"
                                style="font-family: 'Brush Script MT', cursive; font-size: 32px; font-weight: normal;">
                                <i class="fas fa-cog mr-2" style="font-size: 24px;"></i> Site Settings
                            </h1>
                            <ol class="breadcrumb mt-2 bg-transparent p-0">
                                <li class="breadcrumb-item"><a href="dashboard.php"
                                        style="color: #0d6efd; font-weight:600;">Dashboard</a></li>
                                <li class="breadcrumb-item active text-dark">Site Settings</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

            <section class="content pb-5">
                <div class="container-fluid">
                    <?php if (!empty($msg)) { ?>
                        <div class="alert alert-<?= $msg_type ?> alert-dismissible fade show shadow-sm">
                            <?= $msg; ?>
                            <button type="button" class="close" data-dismiss="alert">&times;</button>
                        </div>
                    <?php } ?>

                    <form method="POST" action="" enctype="multipart/form-data">

                        <!-- General Settings (Full Width Top) -->
                        <div class="card form-card">
                            <div class="card-header-purple"><i class="fas fa-info-circle mr-2"></i> General Settings
                            </div>
                            <div class="card-body p-4">
                                <div class="row">
                                    <div class="col-md-6 form-group">
                                        <label class="form-label">Site Title</label>
                                        <input type="text" name="site_title" class="form-control"
                                            value="<?= htmlspecialchars($settings['site_title']) ?>">
                                    </div>
                                    <div class="col-md-6 form-group">
                                        <label class="form-label">Site Tagline</label>
                                        <input type="text" name="site_tagline" class="form-control"
                                            value="<?= htmlspecialchars($settings['site_tagline']) ?>">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Meta Description</label>
                                    <textarea name="meta_description" class="form-control"
                                        rows="2"><?= htmlspecialchars($settings['meta_description']) ?></textarea>
                                </div>
                                <div class="form-group mb-0">
                                    <label class="form-label">Meta Keywords</label>
                                    <input type="text" name="meta_keywords" class="form-control"
                                        value="<?= htmlspecialchars($settings['meta_keywords']) ?>"
                                        placeholder="travel, holiday packages, tours, destinations">
                                    <div class="form-text">Separate with commas</div>
                                </div>
                            </div>
                        </div>

                        <div class="layout-row">
                            <!-- Left Column -->
                            <div class="layout-col-main">

                                <!-- Logo & Favicon -->
                                <div class="card form-card">
                                    <div class="card-header-purple"><i class="fas fa-image mr-2"></i> Logo & Favicon
                                    </div>
                                    <div class="card-body p-4">
                                        <div class="form-group mb-4">
                                            <label class="form-label">Logo</label>
                                            <div class="custom-file mb-2">
                                                <input type="file" class="custom-file-input" name="logo" id="logoFile"
                                                    accept="image/*" onchange="previewImg(this, 'logoPreview')">
                                                <label class="custom-file-label" for="logoFile">Choose File</label>
                                            </div>
                                            <?php if (!empty($settings['logo_path'])): ?>
                                                <div class="img-preview-box">
                                                    <img src="<?= htmlspecialchars($settings['logo_path']) ?>"
                                                        id="logoPreview">
                                                </div>
                                            <?php else: ?>
                                                <div class="img-preview-box" style="display:none;"><img src=""
                                                        id="logoPreview"></div>
                                            <?php endif; ?>
                                        </div>

                                        <div class="form-group mb-0">
                                            <label class="form-label">Favicon</label>
                                            <div class="custom-file mb-2">
                                                <input type="file" class="custom-file-input" name="favicon" id="favFile"
                                                    accept="image/*" onchange="previewImg(this, 'favPreview')">
                                                <label class="custom-file-label" for="favFile">Choose File</label>
                                            </div>
                                            <?php if (!empty($settings['favicon_path'])): ?>
                                                <div class="img-preview-box">
                                                    <img src="<?= htmlspecialchars($settings['favicon_path']) ?>"
                                                        id="favPreview">
                                                </div>
                                            <?php else: ?>
                                                <div class="img-preview-box" style="display:none;"><img src=""
                                                        id="favPreview"></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Social Links -->
                                <div class="card form-card">
                                    <div class="card-header-purple"><i class="fas fa-share-alt mr-2"></i> Social Media
                                        Links</div>
                                    <div class="card-body p-4">
                                        <div class="row">
                                            <div class="col-md-6 form-group">
                                                <label class="form-label">Facebook URL</label>
                                                <input type="url" name="facebook_url" class="form-control"
                                                    value="<?= htmlspecialchars($settings['facebook_url']) ?>">
                                            </div>
                                            <div class="col-md-6 form-group">
                                                <label class="form-label">Instagram URL</label>
                                                <input type="url" name="instagram_url" class="form-control"
                                                    value="<?= htmlspecialchars($settings['instagram_url']) ?>">
                                            </div>
                                            <div class="col-md-6 form-group">
                                                <label class="form-label">Twitter/X URL</label>
                                                <input type="url" name="twitter_url" class="form-control"
                                                    value="<?= htmlspecialchars($settings['twitter_url']) ?>">
                                            </div>
                                            <div class="col-md-6 form-group">
                                                <label class="form-label">LinkedIn URL</label>
                                                <input type="url" name="linkedin_url" class="form-control"
                                                    value="<?= htmlspecialchars($settings['linkedin_url']) ?>">
                                            </div>
                                            <div class="col-md-12 form-group mb-0">
                                                <label class="form-label">YouTube URL</label>
                                                <input type="url" name="youtube_url" class="form-control"
                                                    value="<?= htmlspecialchars($settings['youtube_url']) ?>">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Footer Settings -->
                                <div class="card form-card">
                                    <div class="card-header-purple"><i class="fas fa-shoe-prints mr-2"></i> Footer
                                        Settings</div>
                                    <div class="card-body p-4">
                                        <div class="form-group">
                                            <label class="form-label">Footer About Text</label>
                                            <textarea name="footer_about_text" class="form-control"
                                                rows="3"><?= htmlspecialchars($settings['footer_about_text'] ?? '') ?></textarea>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Copyright Text</label>
                                            <input type="text" name="copyright_text" class="form-control"
                                                value="<?= htmlspecialchars($settings['copyright_text'] ?? '') ?>">
                                        </div>
                                        <hr class="my-3">
                                        <p class="text-muted small mb-3"><strong>Contact column</strong> (footer “Contact Info” + newsletter)</p>
                                        <div class="form-group">
                                            <label class="form-label">Address</label>
                                            <textarea name="footer_address" class="form-control" rows="2"
                                                placeholder="Street, city, country"><?= htmlspecialchars($settings['footer_address'] ?? '') ?></textarea>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6 form-group">
                                                <label class="form-label">Phone (display)</label>
                                                <input type="text" name="footer_phone" class="form-control"
                                                    value="<?= htmlspecialchars($settings['footer_phone'] ?? '') ?>"
                                                    placeholder="+91 9709400140">
                                            </div>
                                            <div class="col-md-6 form-group">
                                                <label class="form-label">Email</label>
                                                <input type="email" name="footer_email" class="form-control"
                                                    value="<?= htmlspecialchars($settings['footer_email'] ?? '') ?>"
                                                    placeholder="info@yourdomain.com">
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Working hours</label>
                                            <input type="text" name="footer_working_hours" class="form-control"
                                                value="<?= htmlspecialchars($settings['footer_working_hours'] ?? '') ?>"
                                                placeholder="Mon - Sat: 10:00 AM - 6:00 PM">
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6 form-group">
                                                <label class="form-label">Newsletter heading</label>
                                                <input type="text" name="footer_newsletter_heading" class="form-control"
                                                    value="<?= htmlspecialchars($settings['footer_newsletter_heading'] ?? '') ?>">
                                            </div>
                                            <div class="col-md-6 form-group">
                                                <label class="form-label">Newsletter email placeholder</label>
                                                <input type="text" name="footer_newsletter_placeholder"
                                                    class="form-control"
                                                    value="<?= htmlspecialchars($settings['footer_newsletter_placeholder'] ?? '') ?>">
                                            </div>
                                        </div>
                                        <div class="form-group mb-0 mt-3">
                                            <label class="form-label"><i class="fab fa-whatsapp text-success mr-1"></i> Floating WhatsApp number</label>
                                            <input type="text" name="whatsapp_phone" class="form-control"
                                                value="<?= htmlspecialchars($settings['whatsapp_phone'] ?? '') ?>"
                                                placeholder="1125425642">
                                            <div class="form-text"><code>+</code>, spaces and dashes are stripped for the link.
                                                Leave empty to hide the floating WhatsApp button.
                                                Example: <code>919876543210</code> or <code>+91 98765 43210</code>.</div>
                                        </div>
                                    </div>
                                </div>

                                <div class="text-center mt-4">
                                    <button type="submit" name="save_settings" class="btn-purple"><i
                                            class="fas fa-save mr-2"></i> Save Settings</button>
                                </div>

                            </div>

                            <!-- Right Column -->
                            <div class="layout-col-side">
                                <!-- Theme Colors -->
                                <div class="card form-card">
                                    <div class="card-header-purple"><i class="fas fa-palette mr-2"></i> Theme Colors
                                    </div>
                                    <div class="card-body p-4">
                                        <div class="form-group">
                                            <label class="form-label">Primary Color</label>
                                            <div class="color-picker-wrapper">
                                                <input type="color" class="color-picker-input"
                                                    value="<?= htmlspecialchars($settings['primary_color']) ?>"
                                                    onchange="$(this).next().val(this.value)">
                                                <input type="text" name="primary_color" class="color-hex-input"
                                                    value="<?= htmlspecialchars($settings['primary_color']) ?>"
                                                    onkeyup="$(this).prev().val(this.value)">
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Secondary Color</label>
                                            <div class="color-picker-wrapper">
                                                <input type="color" class="color-picker-input"
                                                    value="<?= htmlspecialchars($settings['secondary_color']) ?>"
                                                    onchange="$(this).next().val(this.value)">
                                                <input type="text" name="secondary_color" class="color-hex-input"
                                                    value="<?= htmlspecialchars($settings['secondary_color']) ?>"
                                                    onkeyup="$(this).prev().val(this.value)">
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Accent Color</label>
                                            <div class="color-picker-wrapper">
                                                <input type="color" class="color-picker-input"
                                                    value="<?= htmlspecialchars($settings['accent_color']) ?>"
                                                    onchange="$(this).next().val(this.value)">
                                                <input type="text" name="accent_color" class="color-hex-input"
                                                    value="<?= htmlspecialchars($settings['accent_color']) ?>"
                                                    onkeyup="$(this).prev().val(this.value)">
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Header Background Color</label>
                                            <div class="color-picker-wrapper">
                                                <input type="color" class="color-picker-input"
                                                    value="<?= htmlspecialchars($settings['header_scroll_color']) ?>"
                                                    onchange="$(this).next().val(this.value)">
                                                <input type="text" name="header_scroll_color" class="color-hex-input"
                                                    value="<?= htmlspecialchars($settings['header_scroll_color']) ?>"
                                                    onkeyup="$(this).prev().val(this.value)">
                                            </div>
                                            <div class="form-text mt-2">Homepage header when scrolled, and the header background on all other pages (About, Packages, Pay, etc.).</div>
                                        </div>
                                        <div class="form-group mb-0 mt-3">
                                            <label class="form-label">Header Text Color</label>
                                            <div class="color-picker-wrapper">
                                                <input type="color" class="color-picker-input"
                                                    value="<?= htmlspecialchars($settings['header_text_color']) ?>"
                                                    onchange="$(this).next().val(this.value)">
                                                <input type="text" name="header_text_color" class="color-hex-input"
                                                    value="<?= htmlspecialchars($settings['header_text_color']) ?>"
                                                    onkeyup="$(this).prev().val(this.value)">
                                            </div>
                                            <div class="form-text mt-2">Text color for header links (useful if you change background to white)</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </form>
                </div>
            </section>
        </div>

        <?php include __DIR__ . '/includes/footer-links.php'; ?>

        <script>
            // Update custom file label on select
            $('.custom-file-input').on('change', function () {
                var fileName = $(this).val().split('\\').pop();
                $(this).next('.custom-file-label').html(fileName);
            });

            function previewImg(input, imgId) {
                if (input.files && input.files[0]) {
                    var reader = new FileReader();
                    reader.onload = function (e) {
                        $('#' + imgId).attr('src', e.target.result).parent().show();
                    }
                    reader.readAsDataURL(input.files[0]);
                }
            }
        </script>

    </div>
</body>

</html>