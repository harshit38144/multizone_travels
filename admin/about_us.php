<?php
session_start();
if ($_SESSION['role'] != '1') {
	header('location:index.php');
	exit;
}
include __DIR__ . '/connection.php';

$uploadDir = 'uploads/about/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

function resolveAboutImageDiskPath($path)
{
    $path = trim((string)$path);
    if ($path === '') {
        return '';
    }

    $candidates = [
        $path,
        '../' . $path,
        preg_replace('#^admin/#', '', $path),
    ];

    foreach ($candidates as $candidate) {
        if (!empty($candidate) && file_exists($candidate)) {
            return $candidate;
        }
    }

    return '';
}

function resolveAboutImageAdminSrc($path)
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

$table_sql = "CREATE TABLE IF NOT EXISTS `about_us_settings` (
  `id` INT PRIMARY KEY DEFAULT 1,
  `hero_title` VARCHAR(255) DEFAULT 'About Us',
  `hero_subtitle` VARCHAR(255) DEFAULT 'About Us',
  `about_image` VARCHAR(255) DEFAULT NULL,
  `experience_years` VARCHAR(50) DEFAULT '10+',
  `experience_text` VARCHAR(255) DEFAULT 'Years of Experience',
  `section_title` VARCHAR(255) DEFAULT 'Welcome to Multizone Travels',
  `lead_text` TEXT DEFAULT NULL,
  `description_1` TEXT DEFAULT NULL,
  `description_2` TEXT DEFAULT NULL,
  `stat_1_number` VARCHAR(50) DEFAULT '50+',
  `stat_1_label` VARCHAR(255) DEFAULT 'Destinations',
  `stat_2_number` VARCHAR(50) DEFAULT '10,000+',
  `stat_2_label` VARCHAR(255) DEFAULT 'Happy Travelers',
  `why_title` VARCHAR(255) DEFAULT 'Why Choose Us',
  `why_subtitle` VARCHAR(255) DEFAULT 'What makes us the best choice for your travel needs',
  `why_1_title` VARCHAR(255) DEFAULT 'Expert Guidance',
  `why_1_text` TEXT DEFAULT NULL,
  `why_2_title` VARCHAR(255) DEFAULT 'Best Value',
  `why_2_text` TEXT DEFAULT NULL,
  `why_3_title` VARCHAR(255) DEFAULT '24/7 Support',
  `why_3_text` TEXT DEFAULT NULL,
  `cta_title` VARCHAR(255) DEFAULT 'Ready to Start Your Journey?',
  `cta_text` VARCHAR(255) DEFAULT 'Contact us today and let\\'s plan your dream vacation together!',
  `cta_button_text` VARCHAR(100) DEFAULT 'Send Enquiry',
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($table_sql);

$exists = $conn->query("SELECT id FROM about_us_settings WHERE id=1");
if ($exists && $exists->num_rows == 0) {
    $conn->query("INSERT INTO about_us_settings (id, lead_text, description_1, description_2, why_1_text, why_2_text, why_3_text) VALUES (
        1,
        'Your trusted partner for creating unforgettable travel memories across the globe.',
        'At Multizone Travels, we believe that traveling is more than just visiting new places; it\\'s about experiencing different cultures, creating lifelong memories, and discovering yourself along the way. With years of experience in the travel industry, we have curated the perfect blend of destinations, itineraries, and experiences to cater to every traveler\\'s dream.',
        'Whether you\\'re looking for a romantic honeymoon, a thrilling adventure, a relaxing family vacation, or a customized group tour, our team of travel experts is dedicated to planning the perfect getaway for you.',
        'Our travel experts have in-depth knowledge of destinations to provide you with the best recommendations and itineraries.',
        'We offer competitive pricing without compromising on quality, ensuring you get the best value for your money.',
        'We are always here for you. Our dedicated support team is available round the clock to assist you during your trip.'
    )");
}

if (isset($_POST['save_about'])) {
    $hero_title = mysqli_real_escape_string($conn, $_POST['hero_title'] ?? '');
    $hero_subtitle = mysqli_real_escape_string($conn, $_POST['hero_subtitle'] ?? '');
    $experience_years = mysqli_real_escape_string($conn, $_POST['experience_years'] ?? '');
    $experience_text = mysqli_real_escape_string($conn, $_POST['experience_text'] ?? '');
    $section_title = mysqli_real_escape_string($conn, $_POST['section_title'] ?? '');
    $lead_text = mysqli_real_escape_string($conn, $_POST['lead_text'] ?? '');
    $description_1 = mysqli_real_escape_string($conn, $_POST['description_1'] ?? '');
    $description_2 = mysqli_real_escape_string($conn, $_POST['description_2'] ?? '');
    $stat_1_number = mysqli_real_escape_string($conn, $_POST['stat_1_number'] ?? '');
    $stat_1_label = mysqli_real_escape_string($conn, $_POST['stat_1_label'] ?? '');
    $stat_2_number = mysqli_real_escape_string($conn, $_POST['stat_2_number'] ?? '');
    $stat_2_label = mysqli_real_escape_string($conn, $_POST['stat_2_label'] ?? '');
    $why_title = mysqli_real_escape_string($conn, $_POST['why_title'] ?? '');
    $why_subtitle = mysqli_real_escape_string($conn, $_POST['why_subtitle'] ?? '');
    $why_1_title = mysqli_real_escape_string($conn, $_POST['why_1_title'] ?? '');
    $why_1_text = mysqli_real_escape_string($conn, $_POST['why_1_text'] ?? '');
    $why_2_title = mysqli_real_escape_string($conn, $_POST['why_2_title'] ?? '');
    $why_2_text = mysqli_real_escape_string($conn, $_POST['why_2_text'] ?? '');
    $why_3_title = mysqli_real_escape_string($conn, $_POST['why_3_title'] ?? '');
    $why_3_text = mysqli_real_escape_string($conn, $_POST['why_3_text'] ?? '');
    $cta_title = mysqli_real_escape_string($conn, $_POST['cta_title'] ?? '');
    $cta_text = mysqli_real_escape_string($conn, $_POST['cta_text'] ?? '');
    $cta_button_text = mysqli_real_escape_string($conn, $_POST['cta_button_text'] ?? '');

    $image_sql = "";
    if (!empty($_FILES['about_image']['name']) && $_FILES['about_image']['error'] == 0) {
        $ext = strtolower(pathinfo($_FILES['about_image']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
            $filename = 'about_' . uniqid() . '.' . $ext;
            if (move_uploaded_file($_FILES['about_image']['tmp_name'], $uploadDir . $filename)) {
                $current = $conn->query("SELECT about_image FROM about_us_settings WHERE id=1")->fetch_assoc();
                $oldImagePath = resolveAboutImageDiskPath($current['about_image'] ?? '');
                if (!empty($oldImagePath)) {
                    unlink($oldImagePath);
                }
                $publicImagePath = 'admin/' . $uploadDir . $filename;
                $image_sql = ", about_image='" . $publicImagePath . "'";
            }
        }
    }

    $sql = "UPDATE about_us_settings SET
        hero_title='$hero_title',
        hero_subtitle='$hero_subtitle',
        experience_years='$experience_years',
        experience_text='$experience_text',
        section_title='$section_title',
        lead_text='$lead_text',
        description_1='$description_1',
        description_2='$description_2',
        stat_1_number='$stat_1_number',
        stat_1_label='$stat_1_label',
        stat_2_number='$stat_2_number',
        stat_2_label='$stat_2_label',
        why_title='$why_title',
        why_subtitle='$why_subtitle',
        why_1_title='$why_1_title',
        why_1_text='$why_1_text',
        why_2_title='$why_2_title',
        why_2_text='$why_2_text',
        why_3_title='$why_3_title',
        why_3_text='$why_3_text',
        cta_title='$cta_title',
        cta_text='$cta_text',
        cta_button_text='$cta_button_text'
        $image_sql
        WHERE id=1";

    if ($conn->query($sql)) {
        $_SESSION['msg'] = "About Us page updated successfully!";
    } else {
        $_SESSION['msg'] = "Error updating About Us page.";
        $_SESSION['msg_type'] = "danger";
    }
    header("Location: about_us.php");
    exit;
}

$about = $conn->query("SELECT * FROM about_us_settings WHERE id=1")->fetch_assoc();
$aboutImagePreviewSrc = resolveAboutImageAdminSrc($about['about_image'] ?? '');
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
    <title>About Us Page Manager</title>
    <?php include __DIR__ . '/includes/header-links.php'; ?>
    <style>
        .page-bg { background-color: #f4f6f9; }
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
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 20px;
            background: #fff;
        }
        .form-label { font-weight: 600; font-size: 13px; color: #444; }
        .btn-purple {
            background: linear-gradient(135deg, #6a11cb, #a855f7);
            color: #fff;
            border: none;
            font-weight: 600;
            padding: 10px 26px;
            border-radius: 6px;
        }
        .btn-purple:hover { opacity: 0.9; color: #fff; }
        .img-preview { max-height: 120px; border-radius: 8px; border: 1px solid #ddd; padding: 4px; background: #fff; }
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
                            <h1 class="m-0 text-dark"><i class="fas fa-address-card mr-2"></i>About Us Page Manager</h1>
                        </div>
                    </div>
                </div>
            </div>

            <section class="content pb-4">
                <div class="container-fluid">
                    <?php if (!empty($msg)) { ?>
                        <div class="alert alert-<?= $msg_type ?> alert-dismissible fade show shadow-sm">
                            <?= $msg; ?>
                            <button type="button" class="close" data-dismiss="alert">&times;</button>
                        </div>
                    <?php } ?>

                    <form method="POST" action="" enctype="multipart/form-data">
                        <div class="card form-card">
                            <div class="card-header-purple"><i class="fas fa-image mr-2"></i>Hero Section</div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6 form-group">
                                        <label class="form-label">Page Title</label>
                                        <input type="text" name="hero_title" class="form-control" value="<?= htmlspecialchars($about['hero_title'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-6 form-group">
                                        <label class="form-label">Breadcrumb Title</label>
                                        <input type="text" name="hero_subtitle" class="form-control" value="<?= htmlspecialchars($about['hero_subtitle'] ?? '') ?>">
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-4 form-group">
                                        <label class="form-label">Experience Number</label>
                                        <input type="text" name="experience_years" class="form-control" value="<?= htmlspecialchars($about['experience_years'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-8 form-group">
                                        <label class="form-label">Experience Text</label>
                                        <input type="text" name="experience_text" class="form-control" value="<?= htmlspecialchars($about['experience_text'] ?? '') ?>">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">About Image</label>
                                    <div class="custom-file">
                                        <input type="file" class="custom-file-input" name="about_image" id="aboutImage" accept="image/*">
                                        <label class="custom-file-label" for="aboutImage">Choose file</label>
                                    </div>
                                    <?php if (!empty($aboutImagePreviewSrc)): ?>
                                        <div class="mt-2"><img src="<?= htmlspecialchars($aboutImagePreviewSrc) ?>" class="img-preview" alt="About image"></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="card form-card">
                            <div class="card-header-purple"><i class="fas fa-align-left mr-2"></i>About Content</div>
                            <div class="card-body">
                                <div class="form-group">
                                    <label class="form-label">Section Heading</label>
                                    <input type="text" name="section_title" class="form-control" value="<?= htmlspecialchars($about['section_title'] ?? '') ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Lead Text</label>
                                    <textarea name="lead_text" class="form-control" rows="2"><?= htmlspecialchars($about['lead_text'] ?? '') ?></textarea>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Description Paragraph 1</label>
                                    <textarea name="description_1" class="form-control" rows="4"><?= htmlspecialchars($about['description_1'] ?? '') ?></textarea>
                                </div>
                                <div class="form-group mb-0">
                                    <label class="form-label">Description Paragraph 2</label>
                                    <textarea name="description_2" class="form-control" rows="4"><?= htmlspecialchars($about['description_2'] ?? '') ?></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="card form-card">
                            <div class="card-header-purple"><i class="fas fa-chart-line mr-2"></i>Stats and Why Choose Us</div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3 form-group">
                                        <label class="form-label">Stat 1 Number</label>
                                        <input type="text" name="stat_1_number" class="form-control" value="<?= htmlspecialchars($about['stat_1_number'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-3 form-group">
                                        <label class="form-label">Stat 1 Label</label>
                                        <input type="text" name="stat_1_label" class="form-control" value="<?= htmlspecialchars($about['stat_1_label'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-3 form-group">
                                        <label class="form-label">Stat 2 Number</label>
                                        <input type="text" name="stat_2_number" class="form-control" value="<?= htmlspecialchars($about['stat_2_number'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-3 form-group">
                                        <label class="form-label">Stat 2 Label</label>
                                        <input type="text" name="stat_2_label" class="form-control" value="<?= htmlspecialchars($about['stat_2_label'] ?? '') ?>">
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 form-group">
                                        <label class="form-label">Why Section Title</label>
                                        <input type="text" name="why_title" class="form-control" value="<?= htmlspecialchars($about['why_title'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-6 form-group">
                                        <label class="form-label">Why Section Subtitle</label>
                                        <input type="text" name="why_subtitle" class="form-control" value="<?= htmlspecialchars($about['why_subtitle'] ?? '') ?>">
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-4 form-group">
                                        <label class="form-label">Card 1 Title</label>
                                        <input type="text" name="why_1_title" class="form-control mb-2" value="<?= htmlspecialchars($about['why_1_title'] ?? '') ?>">
                                        <textarea name="why_1_text" class="form-control" rows="4"><?= htmlspecialchars($about['why_1_text'] ?? '') ?></textarea>
                                    </div>
                                    <div class="col-md-4 form-group">
                                        <label class="form-label">Card 2 Title</label>
                                        <input type="text" name="why_2_title" class="form-control mb-2" value="<?= htmlspecialchars($about['why_2_title'] ?? '') ?>">
                                        <textarea name="why_2_text" class="form-control" rows="4"><?= htmlspecialchars($about['why_2_text'] ?? '') ?></textarea>
                                    </div>
                                    <div class="col-md-4 form-group">
                                        <label class="form-label">Card 3 Title</label>
                                        <input type="text" name="why_3_title" class="form-control mb-2" value="<?= htmlspecialchars($about['why_3_title'] ?? '') ?>">
                                        <textarea name="why_3_text" class="form-control" rows="4"><?= htmlspecialchars($about['why_3_text'] ?? '') ?></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card form-card">
                            <div class="card-header-purple"><i class="fas fa-bullhorn mr-2"></i>CTA Section</div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4 form-group">
                                        <label class="form-label">CTA Title</label>
                                        <input type="text" name="cta_title" class="form-control" value="<?= htmlspecialchars($about['cta_title'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-5 form-group">
                                        <label class="form-label">CTA Text</label>
                                        <input type="text" name="cta_text" class="form-control" value="<?= htmlspecialchars($about['cta_text'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-3 form-group">
                                        <label class="form-label">Button Text</label>
                                        <input type="text" name="cta_button_text" class="form-control" value="<?= htmlspecialchars($about['cta_button_text'] ?? '') ?>">
                                    </div>
                                </div>
                                <button type="submit" name="save_about" class="btn-purple"><i class="fas fa-save mr-2"></i>Save About Us</button>
                            </div>
                        </div>
                    </form>
                </div>
            </section>
        </div>

        <?php include __DIR__ . '/includes/footer-links.php'; ?>
        <script>
            $('.custom-file-input').on('change', function () {
                var fileName = $(this).val().split('\\').pop();
                $(this).next('.custom-file-label').html(fileName);
            });
        </script>
    </div>
</body>
</html>
