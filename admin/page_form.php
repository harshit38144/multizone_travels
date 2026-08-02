<?php
session_start();
if ($_SESSION['role'] != '1') {
	header('location:index.php');
	exit;
}
include __DIR__ . '/connection.php';

// Ensure layout columns exist for page sections
$layoutCol = $conn->query("SHOW COLUMNS FROM `page_sections` LIKE 'layout_type'");
if ($layoutCol && $layoutCol->num_rows == 0) {
    $conn->query("ALTER TABLE `page_sections` ADD `layout_type` VARCHAR(20) NOT NULL DEFAULT 'split' AFTER `image`");
}
$cardsCol = $conn->query("SHOW COLUMNS FROM `page_sections` LIKE 'cards_per_row'");
if ($cardsCol && $cardsCol->num_rows == 0) {
    $conn->query("ALTER TABLE `page_sections` ADD `cards_per_row` TINYINT(1) NOT NULL DEFAULT 3 AFTER `layout_type`");
}
$typeCol = $conn->query("SHOW COLUMNS FROM `page_sections` LIKE 'card_content_type'");
if ($typeCol && $typeCol->num_rows == 0) {
    $conn->query("ALTER TABLE `page_sections` ADD `card_content_type` VARCHAR(20) NOT NULL DEFAULT 'both' AFTER `cards_per_row`");
}

// Cards table for multi-card sections
$cardsTableSql = "CREATE TABLE IF NOT EXISTS `page_section_cards` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `section_id` INT NOT NULL,
  `card_title` VARCHAR(255) DEFAULT NULL,
  `card_content` LONGTEXT,
  `card_image` VARCHAR(255) DEFAULT NULL,
  `display_order` INT DEFAULT 0,
  FOREIGN KEY (`section_id`) REFERENCES `page_sections`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($cardsTableSql);

function saveSectionCards($conn, $sectionId, $sectionIndex, $targetDir)
{
    $existingIds = [];
    $existingRes = $conn->query("SELECT id FROM page_section_cards WHERE section_id=" . (int)$sectionId);
    if ($existingRes) {
        while ($row = $existingRes->fetch_assoc()) {
            $existingIds[] = (int)$row['id'];
        }
    }

    $titles = $_POST['sec_card_title'][$sectionIndex] ?? [];
    $contents = $_POST['sec_card_content'][$sectionIndex] ?? [];
    $cardIds = $_POST['sec_card_id'][$sectionIndex] ?? [];
    $submittedIds = [];

    for ($j = 0; $j < count($titles); $j++) {
        $cardTitle = mysqli_real_escape_string($conn, $titles[$j] ?? '');
        $cardContent = mysqli_real_escape_string($conn, $contents[$j] ?? '');
        $cardId = isset($cardIds[$j]) ? (int)$cardIds[$j] : 0;
        $cardOrder = $j;
        $cardImage = "";

        if (isset($_FILES['sec_card_image']['name'][$sectionIndex][$j]) && $_FILES['sec_card_image']['error'][$sectionIndex][$j] == 0) {
            if (!file_exists($targetDir)) {
                mkdir($targetDir, 0777, true);
            }
            $ext = pathinfo($_FILES['sec_card_image']['name'][$sectionIndex][$j], PATHINFO_EXTENSION);
            $newName = "card_" . uniqid() . "_" . time() . "." . $ext;
            $targetFile = $targetDir . $newName;
            if (move_uploaded_file($_FILES['sec_card_image']['tmp_name'][$sectionIndex][$j], $targetFile)) {
                $cardImage = "images/pages/" . $newName;
            }
        }

        if ($cardId > 0) {
            $submittedIds[] = $cardId;
            $upd = "UPDATE page_section_cards SET card_title='$cardTitle', card_content='$cardContent', display_order=$cardOrder";
            if (!empty($cardImage)) {
                $old = $conn->query("SELECT card_image FROM page_section_cards WHERE id=$cardId")->fetch_assoc();
                if ($old && !empty($old['card_image']) && file_exists("../" . $old['card_image'])) {
                    unlink("../" . $old['card_image']);
                }
                $upd .= ", card_image='$cardImage'";
            }
            $upd .= " WHERE id=$cardId";
            $conn->query($upd);
        } else {
            if (!empty($cardTitle) || !empty($cardContent) || !empty($cardImage)) {
                $conn->query("INSERT INTO page_section_cards (section_id, card_title, card_content, card_image, display_order) VALUES (" . (int)$sectionId . ", '$cardTitle', '$cardContent', '$cardImage', $cardOrder)");
            }
        }
    }

    $toDelete = array_diff($existingIds, $submittedIds);
    if (!empty($toDelete)) {
        $delIds = implode(',', $toDelete);
        $imgRes = $conn->query("SELECT card_image FROM page_section_cards WHERE id IN ($delIds)");
        if ($imgRes) {
            while ($img = $imgRes->fetch_assoc()) {
                if (!empty($img['card_image']) && file_exists("../" . $img['card_image'])) {
                    unlink("../" . $img['card_image']);
                }
            }
        }
        $conn->query("DELETE FROM page_section_cards WHERE id IN ($delIds)");
    }
}

// Generate slug function
function createSlug($str, $conn, $id = 0) {
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $str)));
    $slug = preg_replace('/-+/', '-', $slug);
    
    // Check if exists
    $sql = "SELECT id FROM pages WHERE slug = '$slug' AND id != $id";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        $slug = $slug . '-' . time();
    }
    return $slug;
}

$isEdit = false;
$pageData = null;

if (isset($_GET['edit_id'])) {
    $isEdit = true;
    $id = (int)$_GET['edit_id'];
    $pageData = $conn->query("SELECT * FROM pages WHERE id=$id")->fetch_assoc();
    if (!$pageData) {
        header("Location: pages.php");
        exit;
    }
}

// Handle Form Submit
if (isset($_POST['save_page'])) {
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $content = mysqli_real_escape_string($conn, $_POST['content']);
    $meta_title = mysqli_real_escape_string($conn, $_POST['meta_title']);
    $meta_desc = mysqli_real_escape_string($conn, $_POST['meta_description']);
    $is_published = isset($_POST['is_published']) ? 1 : 0;
    
    $slug = trim($_POST['slug']);
    
    // Handle Image Upload
    $featured_image = "";
    if (isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] == 0) {
        $target_dir = "../images/pages/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        $file_extension = pathinfo($_FILES["featured_image"]["name"], PATHINFO_EXTENSION);
        $new_filename = "page_" . uniqid() . "_" . time() . "." . $file_extension;
        $target_file = $target_dir . $new_filename;
        
        if (move_uploaded_file($_FILES["featured_image"]["tmp_name"], $target_file)) {
            $featured_image = "images/pages/" . $new_filename;
        }
    }
    
    if ($isEdit) {
        $id = (int)$_POST['page_id'];
        if (empty($slug)) {
            $slug = createSlug($title, $conn, $id);
        } else {
            $slug = createSlug($slug, $conn, $id);
        }
        
        $sql = "UPDATE pages SET title='$title', slug='$slug', content='$content', is_published=$is_published, meta_title='$meta_title', meta_description='$meta_desc'";
        
        if (!empty($featured_image)) {
            // Delete old image
            $old_img = $conn->query("SELECT featured_image FROM pages WHERE id=$id")->fetch_assoc();
            if ($old_img && !empty($old_img['featured_image']) && file_exists("../" . $old_img['featured_image'])) {
                unlink("../" . $old_img['featured_image']);
            }
            $sql .= ", featured_image='$featured_image'";
        }
        
        $sql .= " WHERE id=$id";
        $conn->query($sql);
        
        // Handle Sections
        if (isset($_POST['sec_title'])) {
            $sec_titles = $_POST['sec_title'];
            $sec_contents = $_POST['sec_content'];
            $sec_ids = $_POST['sec_id'];
            $sec_layouts = isset($_POST['sec_layout']) ? $_POST['sec_layout'] : [];
            $sec_cards_per_row = isset($_POST['sec_cards_per_row']) ? $_POST['sec_cards_per_row'] : [];
            $sec_card_types = isset($_POST['sec_card_type']) ? $_POST['sec_card_type'] : [];
            
            // Get existing sections to check for deletions
            $existing_secs = [];
            $es_res = $conn->query("SELECT id FROM page_sections WHERE page_id=$id");
            while ($es = $es_res->fetch_assoc()) {
                $existing_secs[] = $es['id'];
            }
            
            $submitted_ids = [];
            
            for ($i = 0; $i < count($sec_titles); $i++) {
                $s_title = mysqli_real_escape_string($conn, $sec_titles[$i]);
                $s_content = mysqli_real_escape_string($conn, $sec_contents[$i]);
                $s_id = (int)$sec_ids[$i];
                $s_order = $i;
                $s_layout = (isset($sec_layouts[$i]) && in_array($sec_layouts[$i], ['split', 'cards'])) ? $sec_layouts[$i] : 'split';
                $s_cards = isset($sec_cards_per_row[$i]) ? (int)$sec_cards_per_row[$i] : 3;
                if (!in_array($s_cards, [2, 3, 4, 5])) {
                    $s_cards = 3;
                }
                $s_card_type = (isset($sec_card_types[$i]) && in_array($sec_card_types[$i], ['text', 'image', 'both'])) ? $sec_card_types[$i] : 'both';
                $s_layout_esc = mysqli_real_escape_string($conn, $s_layout);
                $s_card_type_esc = mysqli_real_escape_string($conn, $s_card_type);
                
                $s_image = "";
                if (isset($_FILES['sec_image']['name'][$i]) && $_FILES['sec_image']['error'][$i] == 0) {
                    $target_dir = "../images/pages/";
                    if (!file_exists($target_dir)) {
                        mkdir($target_dir, 0777, true);
                    }
                    $file_extension = pathinfo($_FILES["sec_image"]["name"][$i], PATHINFO_EXTENSION);
                    $new_filename = "sec_" . uniqid() . "_" . time() . "." . $file_extension;
                    $target_file = $target_dir . $new_filename;
                    
                    if (move_uploaded_file($_FILES["sec_image"]["tmp_name"][$i], $target_file)) {
                        $s_image = "images/pages/" . $new_filename;
                    }
                }
                
                if ($s_id > 0) {
                    $submitted_ids[] = $s_id;
                    $upd_sql = "UPDATE page_sections SET title='$s_title', content='$s_content', layout_type='$s_layout_esc', cards_per_row=$s_cards, card_content_type='$s_card_type_esc', display_order=$s_order";
                    if (!empty($s_image)) {
                        $old_sec_img = $conn->query("SELECT image FROM page_sections WHERE id=$s_id")->fetch_assoc();
                        if ($old_sec_img && !empty($old_sec_img['image']) && file_exists("../" . $old_sec_img['image'])) {
                            unlink("../" . $old_sec_img['image']);
                        }
                        $upd_sql .= ", image='$s_image'";
                    }
                    $upd_sql .= " WHERE id=$s_id";
                    $conn->query($upd_sql);
                    saveSectionCards($conn, $s_id, $i, "../images/pages/");
                } else {
                    // Insert new section
                    if (!empty($s_title) || !empty($s_content) || !empty($s_image)) {
                        $conn->query("INSERT INTO page_sections (page_id, title, content, image, layout_type, cards_per_row, card_content_type, display_order) VALUES ($id, '$s_title', '$s_content', '$s_image', '$s_layout_esc', $s_cards, '$s_card_type_esc', $s_order)");
                        $newSectionId = $conn->insert_id;
                        saveSectionCards($conn, $newSectionId, $i, "../images/pages/");
                    }
                }
            }
            
            // Delete removed sections
            $to_delete = array_diff($existing_secs, $submitted_ids);
            if (!empty($to_delete)) {
                $del_ids = implode(',', $to_delete);
                // Delete images first
                $del_imgs = $conn->query("SELECT image FROM page_sections WHERE id IN ($del_ids)");
                while ($di = $del_imgs->fetch_assoc()) {
                    if (!empty($di['image']) && file_exists("../" . $di['image'])) {
                        unlink("../" . $di['image']);
                    }
                }
                $conn->query("DELETE FROM page_sections WHERE id IN ($del_ids)");
            }
        } else {
            // All sections removed
            $del_imgs = $conn->query("SELECT image FROM page_sections WHERE page_id=$id");
            while ($di = $del_imgs->fetch_assoc()) {
                if (!empty($di['image']) && file_exists("../" . $di['image'])) {
                    unlink("../" . $di['image']);
                }
            }
            $conn->query("DELETE FROM page_sections WHERE page_id=$id");
        }
        
        $_SESSION['msg'] = "Page updated successfully!";
    } else {
        if (empty($slug)) {
            $slug = createSlug($title, $conn);
        } else {
            $slug = createSlug($slug, $conn);
        }
        
        $sql = "INSERT INTO pages (title, slug, content, featured_image, is_published, meta_title, meta_description) VALUES ('$title', '$slug', '$content', '$featured_image', $is_published, '$meta_title', '$meta_desc')";
        $conn->query($sql);
        $new_page_id = $conn->insert_id;
        
        // Handle Sections for new page
        if (isset($_POST['sec_title'])) {
            $sec_titles = $_POST['sec_title'];
            $sec_contents = $_POST['sec_content'];
            $sec_layouts = isset($_POST['sec_layout']) ? $_POST['sec_layout'] : [];
            $sec_cards_per_row = isset($_POST['sec_cards_per_row']) ? $_POST['sec_cards_per_row'] : [];
            $sec_card_types = isset($_POST['sec_card_type']) ? $_POST['sec_card_type'] : [];
            
            for ($i = 0; $i < count($sec_titles); $i++) {
                $s_title = mysqli_real_escape_string($conn, $sec_titles[$i]);
                $s_content = mysqli_real_escape_string($conn, $sec_contents[$i]);
                $s_order = $i;
                $s_layout = (isset($sec_layouts[$i]) && in_array($sec_layouts[$i], ['split', 'cards'])) ? $sec_layouts[$i] : 'split';
                $s_cards = isset($sec_cards_per_row[$i]) ? (int)$sec_cards_per_row[$i] : 3;
                if (!in_array($s_cards, [2, 3, 4, 5])) {
                    $s_cards = 3;
                }
                $s_card_type = (isset($sec_card_types[$i]) && in_array($sec_card_types[$i], ['text', 'image', 'both'])) ? $sec_card_types[$i] : 'both';
                $s_layout_esc = mysqli_real_escape_string($conn, $s_layout);
                $s_card_type_esc = mysqli_real_escape_string($conn, $s_card_type);
                
                $s_image = "";
                if (isset($_FILES['sec_image']['name'][$i]) && $_FILES['sec_image']['error'][$i] == 0) {
                    $target_dir = "../images/pages/";
                    if (!file_exists($target_dir)) {
                        mkdir($target_dir, 0777, true);
                    }
                    $file_extension = pathinfo($_FILES["sec_image"]["name"][$i], PATHINFO_EXTENSION);
                    $new_filename = "sec_" . uniqid() . "_" . time() . "." . $file_extension;
                    $target_file = $target_dir . $new_filename;
                    
                    if (move_uploaded_file($_FILES["sec_image"]["tmp_name"][$i], $target_file)) {
                        $s_image = "images/pages/" . $new_filename;
                    }
                }
                
                if (!empty($s_title) || !empty($s_content) || !empty($s_image)) {
                    $conn->query("INSERT INTO page_sections (page_id, title, content, image, layout_type, cards_per_row, card_content_type, display_order) VALUES ($new_page_id, '$s_title', '$s_content', '$s_image', '$s_layout_esc', $s_cards, '$s_card_type_esc', $s_order)");
                        $newSectionId = $conn->insert_id;
                        saveSectionCards($conn, $newSectionId, $i, "../images/pages/");
                }
            }
        }
        
        $_SESSION['msg'] = "Page created successfully!";
    }
    
    header("Location: pages.php");
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title><?= $isEdit ? 'Edit Page' : 'Add New Page' ?></title>
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
        .toggle-switch input:checked + .toggle-slider { background-color: #6a11cb; }
        .toggle-switch input:checked + .toggle-slider:before { transform: translateX(20px); }

        .btn-purple { background: linear-gradient(135deg, #6a11cb, #a855f7); color: #fff; border: none; font-weight: 600; padding: 10px; width: 100%; border-radius: 6px; margin-bottom: 10px; }
        .btn-purple:hover { opacity: 0.9; color: #fff; }
        
        .btn-dark-grey { background: #4b5563; color: #fff; border: none; font-weight: 600; padding: 10px; width: 100%; border-radius: 6px; margin-bottom: 10px; text-decoration: none; display: block; text-align: center;}
        .btn-dark-grey:hover { background: #374151; color: #fff; text-decoration: none; }
        
        .btn-red { background: #dc3545; color: #fff; border: none; font-weight: 600; padding: 10px; width: 100%; border-radius: 6px; display: block; text-align: center; text-decoration: none;}
        .btn-red:hover { background: #bb2d3b; color: #fff; text-decoration: none;}
        
        .publish-info { font-size: 12px; color: #666; margin-top: 15px; border-top: 1px solid #eee; padding-top: 15px; }
        .layout-options {
            border: 1px solid #dee2e6;
            border-radius: 6px;
            padding: 12px;
            background: #fff;
        }
        .cards-builder {
            border: 1px dashed #ced4da;
            border-radius: 8px;
            background: #fff;
            padding: 12px;
            margin-top: 10px;
        }
        .card-item {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 10px;
            background: #fdfdfd;
            margin-bottom: 10px;
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
                            <h1 class="m-0 text-dark"><i class="fas fa-file-alt mr-2"></i> <?= $isEdit ? 'Edit Page' : 'Add New Page' ?></h1>
                        </div>
                        <div class="col-sm-6">
                            <ol class="breadcrumb float-sm-right">
                                <li class="breadcrumb-item"><a href="pages.php" style="color: #6a11cb;">Pages</a></li>
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
                            <input type="hidden" name="page_id" value="<?= $pageData['id'] ?>">
                        <?php endif; ?>
                        
                        <div class="row">
                            <!-- Left Column -->
                            <div class="col-md-8">
                                <!-- Page Info Card -->
                                <div class="card form-card">
                                    <div class="card-header-purple">
                                        <i class="fas fa-info-circle mr-2"></i> Page Information
                                    </div>
                                    <div class="card-body">
                                        <div class="form-group">
                                            <label class="form-label">Page Title <span class="text-danger">*</span></label>
                                            <input type="text" name="title" class="form-control" required value="<?= $isEdit ? htmlspecialchars($pageData['title']) : '' ?>" placeholder="e.g. About Us">
                                        </div>
                                        
                                        <div class="form-group">
                                            <label class="form-label">Slug</label>
                                            <input type="text" name="slug" class="form-control" value="<?= $isEdit ? htmlspecialchars($pageData['slug']) : '' ?>" placeholder="e.g. about-us">
                                            <div class="form-text">Leave empty to auto-generate from title</div>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label class="form-label">Main Content</label>
                                            <textarea name="content" class="form-control summernote" rows="10"><?= $isEdit ? htmlspecialchars($pageData['content']) : '' ?></textarea>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Page Sections Card -->
                                <div class="card form-card">
                                    <div class="card-header-purple">
                                        <i class="fas fa-layer-group mr-2"></i> Page Sections
                                        <button type="button" class="btn btn-sm btn-light float-right" id="addSectionBtn" style="color: #6a11cb; font-weight: 600; padding: 2px 10px;">
                                            <i class="fas fa-plus mr-1"></i> Add Section
                                        </button>
                                    </div>
                                    <div class="card-body" id="sectionsContainer">
                                        <?php 
                                        if ($isEdit) {
                                            $sec_res = $conn->query("SELECT * FROM page_sections WHERE page_id=$id ORDER BY display_order ASC");
                                            $sec_count = 0;
                                            if ($sec_res && $sec_res->num_rows > 0) {
                                                while ($sec = $sec_res->fetch_assoc()) {
                                                    ?>
                                                    <div class="section-item border rounded p-3 mb-3 bg-light position-relative">
                                                        <input type="hidden" class="section-index" value="<?= $sec_count ?>">
                                                        <input type="hidden" name="sec_id[]" value="<?= $sec['id'] ?>">
                                                        <button type="button" class="btn btn-sm btn-danger position-absolute remove-section-btn" style="top: 10px; right: 10px;"><i class="fas fa-times"></i></button>
                                                        
                                                        <div class="form-group">
                                                            <label class="form-label">Section Title</label>
                                                            <input type="text" name="sec_title[]" class="form-control" value="<?= htmlspecialchars($sec['title']) ?>">
                                                        </div>
                                                        
                                                        <div class="form-group">
                                                            <label class="form-label">Section Content</label>
                                                            <textarea name="sec_content[]" class="form-control summernote"><?= htmlspecialchars($sec['content']) ?></textarea>
                                                        </div>
                                                        
                                                        <div class="form-group">
                                                            <label class="form-label">Section Image</label>
                                                            <?php if(!empty($sec['image']) && file_exists("../" . $sec['image'])): ?>
                                                                <div class="mb-2">
                                                                    <img src="../<?= $sec['image'] ?>" alt="Section Image" style="max-height: 100px; border-radius: 4px;">
                                                                </div>
                                                            <?php endif; ?>
                                                            <div class="custom-file">
                                                                <input type="file" class="custom-file-input" name="sec_image[]" accept="image/*">
                                                                <label class="custom-file-label">Choose file</label>
                                                            </div>
                                                        </div>

                                                        <div class="layout-options">
                                                            <div class="row">
                                                                <div class="col-md-4 form-group mb-2">
                                                                    <label class="form-label">Frontend Layout</label>
                                                                    <select name="sec_layout[]" class="form-control sec-layout-select">
                                                                        <option value="split" <?= (($sec['layout_type'] ?? 'split') == 'split') ? 'selected' : '' ?>>Split (Image + Text)</option>
                                                                        <option value="cards" <?= (($sec['layout_type'] ?? '') == 'cards') ? 'selected' : '' ?>>Cards Grid</option>
                                                                    </select>
                                                                </div>
                                                                <div class="col-md-4 form-group mb-2">
                                                                    <label class="form-label">Cards Per Row</label>
                                                                    <select name="sec_cards_per_row[]" class="form-control sec-cards-count">
                                                                        <?php $selectedCards = (int)($sec['cards_per_row'] ?? 3); ?>
                                                                        <option value="2" <?= $selectedCards === 2 ? 'selected' : '' ?>>2 cards</option>
                                                                        <option value="3" <?= $selectedCards === 3 ? 'selected' : '' ?>>3 cards</option>
                                                                        <option value="4" <?= $selectedCards === 4 ? 'selected' : '' ?>>4 cards</option>
                                                                        <option value="5" <?= $selectedCards === 5 ? 'selected' : '' ?>>5 cards</option>
                                                                    </select>
                                                                </div>
                                                                <div class="col-md-4 form-group mb-2">
                                                                    <label class="form-label">Card Content</label>
                                                                    <?php $selectedType = $sec['card_content_type'] ?? 'both'; ?>
                                                                    <select name="sec_card_type[]" class="form-control sec-card-type">
                                                                        <option value="text" <?= $selectedType === 'text' ? 'selected' : '' ?>>Text Only</option>
                                                                        <option value="image" <?= $selectedType === 'image' ? 'selected' : '' ?>>Image Only</option>
                                                                        <option value="both" <?= $selectedType === 'both' ? 'selected' : '' ?>>Image + Text</option>
                                                                    </select>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <div class="cards-builder">
                                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                                <label class="form-label mb-0">Cards (for Cards Grid layout)</label>
                                                                <button type="button" class="btn btn-sm btn-primary add-card-btn"><i class="fas fa-plus mr-1"></i>Add Card</button>
                                                            </div>
                                                            <div class="cards-container">
                                                                <?php
                                                                $cards_res = $conn->query("SELECT * FROM page_section_cards WHERE section_id=" . (int)$sec['id'] . " ORDER BY display_order ASC");
                                                                if ($cards_res && $cards_res->num_rows > 0):
                                                                    $cardIndex = 0;
                                                                    while ($card = $cards_res->fetch_assoc()):
                                                                ?>
                                                                <div class="card-item">
                                                                    <input type="hidden" name="sec_card_id[<?= $sec_count ?>][]" value="<?= (int)$card['id'] ?>">
                                                                    <button type="button" class="btn btn-sm btn-danger float-right remove-card-btn"><i class="fas fa-times"></i></button>
                                                                    <div class="form-group">
                                                                        <label class="form-label">Card Title</label>
                                                                        <input type="text" name="sec_card_title[<?= $sec_count ?>][]" class="form-control" value="<?= htmlspecialchars($card['card_title']) ?>">
                                                                    </div>
                                                                    <div class="form-group">
                                                                        <label class="form-label">Card Content</label>
                                                                        <textarea name="sec_card_content[<?= $sec_count ?>][]" class="form-control" rows="3"><?= htmlspecialchars($card['card_content']) ?></textarea>
                                                                    </div>
                                                                    <div class="form-group mb-0">
                                                                        <label class="form-label">Card Image</label>
                                                                        <?php if(!empty($card['card_image']) && file_exists("../" . $card['card_image'])): ?>
                                                                            <div class="mb-2"><img src="../<?= htmlspecialchars($card['card_image']) ?>" style="max-height:80px; border-radius:4px;"></div>
                                                                        <?php endif; ?>
                                                                        <div class="custom-file">
                                                                            <input type="file" class="custom-file-input" name="sec_card_image[<?= $sec_count ?>][]" accept="image/*">
                                                                            <label class="custom-file-label">Choose file</label>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <?php
                                                                    $cardIndex++;
                                                                    endwhile;
                                                                else:
                                                                ?>
                                                                <div class="card-item">
                                                                    <input type="hidden" name="sec_card_id[<?= $sec_count ?>][]" value="0">
                                                                    <button type="button" class="btn btn-sm btn-danger float-right remove-card-btn"><i class="fas fa-times"></i></button>
                                                                    <div class="form-group">
                                                                        <label class="form-label">Card Title</label>
                                                                        <input type="text" name="sec_card_title[<?= $sec_count ?>][]" class="form-control">
                                                                    </div>
                                                                    <div class="form-group">
                                                                        <label class="form-label">Card Content</label>
                                                                        <textarea name="sec_card_content[<?= $sec_count ?>][]" class="form-control" rows="3"></textarea>
                                                                    </div>
                                                                    <div class="form-group mb-0">
                                                                        <label class="form-label">Card Image</label>
                                                                        <div class="custom-file">
                                                                            <input type="file" class="custom-file-input" name="sec_card_image[<?= $sec_count ?>][]" accept="image/*">
                                                                            <label class="custom-file-label">Choose file</label>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <?php
                                                    $sec_count++;
                                                }
                                            }
                                        }
                                        ?>
                                    </div>
                                </div>
                                
                                <!-- SEO Settings Card -->
                                <div class="card form-card">
                                    <div class="card-header-purple">
                                        <i class="fas fa-search mr-2"></i> SEO Settings
                                    </div>
                                    <div class="card-body">
                                        <div class="form-group">
                                            <label class="form-label">Meta Title</label>
                                            <input type="text" name="meta_title" class="form-control" value="<?= $isEdit ? htmlspecialchars($pageData['meta_title']) : '' ?>">
                                            <div class="form-text">Leave empty to use page title</div>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label class="form-label">Meta Description</label>
                                            <textarea name="meta_description" class="form-control" rows="3"><?= $isEdit ? htmlspecialchars($pageData['meta_description']) : '' ?></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Right Column -->
                            <div class="col-md-4">
                                <!-- Featured Image Card -->
                                <div class="card form-card">
                                    <div class="card-header-purple">
                                        <i class="fas fa-image mr-2"></i> Featured Image
                                    </div>
                                    <div class="card-body text-center">
                                        <div class="mb-3">
                                            <?php if($isEdit && !empty($pageData['featured_image']) && file_exists("../" . $pageData['featured_image'])): ?>
                                                <img src="../<?= $pageData['featured_image'] ?>" alt="Featured Image" class="img-fluid rounded shadow-sm" style="max-height: 200px; object-fit: cover;">
                                            <?php else: ?>
                                                <div class="bg-light rounded d-flex align-items-center justify-content-center" style="height: 150px; border: 2px dashed #ddd;">
                                                    <div class="text-muted">
                                                        <i class="fas fa-image fa-3x mb-2"></i><br>
                                                        <span>No Image Selected</span>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="form-group text-left">
                                            <label class="form-label">Upload Image</label>
                                            <div class="custom-file">
                                                <input type="file" class="custom-file-input" id="featured_image" name="featured_image" accept="image/*">
                                                <label class="custom-file-label" for="featured_image">Choose file</label>
                                            </div>
                                            <div class="form-text mt-2">Recommended size: 1200x600px. Max size: 2MB.</div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Publish Settings Card -->
                                <div class="card form-card">
                                    <div class="card-header-purple">
                                        <i class="fas fa-cog mr-2"></i> Publish Settings
                                    </div>
                                    <div class="card-body">
                                        <div class="d-flex align-items-center mb-2">
                                            <label class="toggle-switch mr-2">
                                                <input type="checkbox" name="is_published" <?= (!$isEdit || $pageData['is_published']) ? 'checked' : '' ?>>
                                                <span class="toggle-slider"></span>
                                            </label>
                                            <span class="form-label mb-0">Publish Page</span>
                                        </div>
                                        <div class="form-text mb-3">Uncheck to save as draft</div>
                                        
                                        <?php if($isEdit): ?>
                                        <div class="publish-info">
                                            Created: <?= date('M d, Y h:i A', strtotime($pageData['created_at'])) ?><br>
                                            Last Updated: <?= date('M d, Y h:i A', strtotime($pageData['updated_at'])) ?>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <hr>
                                        
                                        <button type="submit" name="save_page" class="btn-purple">
                                            <i class="fas fa-save mr-1"></i> <?= $isEdit ? 'Update Page' : 'Save Page' ?>
                                        </button>
                                        
                                        <a href="pages.php" class="btn-dark-grey">
                                            <i class="fas fa-times mr-1"></i> Cancel
                                        </a>
                                        
                                        <?php if($isEdit): ?>
                                        <a href="pages.php?delete_id=<?= $pageData['id'] ?>" class="btn-red" onclick="return confirm('Are you sure you want to delete this page?');">
                                            <i class="fas fa-trash mr-1"></i> Delete Page
                                        </a>
                                        <?php endif; ?>
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
                function initSummernote() {
                    $('.summernote').not('.note-editor .summernote').summernote({
                        height: 200,
                        placeholder: 'Write your content here...',
                        toolbar: [
                            ['style', ['style']],
                            ['font', ['bold', 'italic', 'underline', 'clear']],
                            ['color', ['color']],
                            ['para', ['ul', 'ol', 'paragraph']],
                            ['table', ['table']],
                            ['insert', ['link', 'picture', 'video']],
                            ['view', ['fullscreen', 'codeview', 'help']]
                        ]
                    });
                }
                
                initSummernote();
                
                // Add Section
                $('#addSectionBtn').click(function() {
                    let html = `
                    <div class="section-item border rounded p-3 mb-3 bg-light position-relative">
                        <input type="hidden" class="section-index" value="__IDX__">
                        <input type="hidden" name="sec_id[]" value="0">
                        <button type="button" class="btn btn-sm btn-danger position-absolute remove-section-btn" style="top: 10px; right: 10px;"><i class="fas fa-times"></i></button>
                        
                        <div class="form-group">
                            <label class="form-label">Section Title</label>
                            <input type="text" name="sec_title[]" class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Section Content</label>
                            <textarea name="sec_content[]" class="form-control summernote"></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Section Image</label>
                            <div class="custom-file">
                                <input type="file" class="custom-file-input" name="sec_image[]" accept="image/*">
                                <label class="custom-file-label">Choose file</label>
                            </div>
                        </div>
                        <div class="layout-options">
                            <div class="row">
                                <div class="col-md-4 form-group mb-2">
                                    <label class="form-label">Frontend Layout</label>
                                    <select name="sec_layout[]" class="form-control sec-layout-select">
                                        <option value="split">Split (Image + Text)</option>
                                        <option value="cards">Cards Grid</option>
                                    </select>
                                </div>
                                <div class="col-md-4 form-group mb-2">
                                    <label class="form-label">Cards Per Row</label>
                                    <select name="sec_cards_per_row[]" class="form-control sec-cards-count">
                                        <option value="2">2 cards</option>
                                        <option value="3" selected>3 cards</option>
                                        <option value="4">4 cards</option>
                                        <option value="5">5 cards</option>
                                    </select>
                                </div>
                                <div class="col-md-4 form-group mb-2">
                                    <label class="form-label">Card Content</label>
                                    <select name="sec_card_type[]" class="form-control sec-card-type">
                                        <option value="text">Text Only</option>
                                        <option value="image">Image Only</option>
                                        <option value="both" selected>Image + Text</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="cards-builder">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label class="form-label mb-0">Cards (for Cards Grid layout)</label>
                                <button type="button" class="btn btn-sm btn-primary add-card-btn"><i class="fas fa-plus mr-1"></i>Add Card</button>
                            </div>
                            <div class="cards-container">
                                <div class="card-item">
                                    <input type="hidden" name="sec_card_id[__IDX__][]" value="0">
                                    <button type="button" class="btn btn-sm btn-danger float-right remove-card-btn"><i class="fas fa-times"></i></button>
                                    <div class="form-group">
                                        <label class="form-label">Card Title</label>
                                        <input type="text" name="sec_card_title[__IDX__][]" class="form-control">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Card Content</label>
                                        <textarea name="sec_card_content[__IDX__][]" class="form-control" rows="3"></textarea>
                                    </div>
                                    <div class="form-group mb-0">
                                        <label class="form-label">Card Image</label>
                                        <div class="custom-file">
                                            <input type="file" class="custom-file-input" name="sec_card_image[__IDX__][]" accept="image/*">
                                            <label class="custom-file-label">Choose file</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>`;

                    html = html.replaceAll('__IDX__', sectionCounter);
                    sectionCounter++;
                    $('#sectionsContainer').append(html);
                    initSummernote();
                });
                
                // Remove Section
                $(document).on('click', '.remove-section-btn', function() {
                    if(confirm('Are you sure you want to remove this section?')) {
                        $(this).closest('.section-item').remove();
                    }
                });
                
                // Update file input label
                $(document).on('change', '.custom-file-input', function() {
                    let fileName = $(this).val().split('\\').pop();
                    $(this).next('.custom-file-label').addClass("selected").html(fileName);
                });

                let sectionCounter = $('#sectionsContainer .section-item').length;
                if (sectionCounter === 0) {
                    sectionCounter = 0;
                }

                // Add card inside section
                $(document).on('click', '.add-card-btn', function() {
                    let section = $(this).closest('.section-item');
                    let secIdx = section.find('.section-index').val();
                    if (typeof secIdx === 'undefined' || secIdx === '') {
                        secIdx = section.index();
                    }
                    let cardHtml = `
                        <div class="card-item">
                            <input type="hidden" name="sec_card_id[${secIdx}][]" value="0">
                            <button type="button" class="btn btn-sm btn-danger float-right remove-card-btn"><i class="fas fa-times"></i></button>
                            <div class="form-group">
                                <label class="form-label">Card Title</label>
                                <input type="text" name="sec_card_title[${secIdx}][]" class="form-control">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Card Content</label>
                                <textarea name="sec_card_content[${secIdx}][]" class="form-control" rows="3"></textarea>
                            </div>
                            <div class="form-group mb-0">
                                <label class="form-label">Card Image</label>
                                <div class="custom-file">
                                    <input type="file" class="custom-file-input" name="sec_card_image[${secIdx}][]" accept="image/*">
                                    <label class="custom-file-label">Choose file</label>
                                </div>
                            </div>
                        </div>`;
                    section.find('.cards-container').append(cardHtml);
                });

                // Remove card
                $(document).on('click', '.remove-card-btn', function() {
                    if(confirm('Remove this card?')) {
                        let container = $(this).closest('.cards-container');
                        $(this).closest('.card-item').remove();
                        if (container.find('.card-item').length === 0) {
                            let secIdx = $(this).closest('.section-item').find('.section-index').val();
                            if (!secIdx) secIdx = 0;
                            container.append(`
                                <div class="card-item">
                                    <input type="hidden" name="sec_card_id[${secIdx}][]" value="0">
                                    <button type="button" class="btn btn-sm btn-danger float-right remove-card-btn"><i class="fas fa-times"></i></button>
                                    <div class="form-group">
                                        <label class="form-label">Card Title</label>
                                        <input type="text" name="sec_card_title[${secIdx}][]" class="form-control">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Card Content</label>
                                        <textarea name="sec_card_content[${secIdx}][]" class="form-control" rows="3"></textarea>
                                    </div>
                                    <div class="form-group mb-0">
                                        <label class="form-label">Card Image</label>
                                        <div class="custom-file">
                                            <input type="file" class="custom-file-input" name="sec_card_image[${secIdx}][]" accept="image/*">
                                            <label class="custom-file-label">Choose file</label>
                                        </div>
                                    </div>
                                </div>
                            `);
                        }
                    }
                });
            });
        </script>

    </div>
</body>
</html>
