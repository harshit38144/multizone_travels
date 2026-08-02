<?php
session_start();
if ($_SESSION['role'] != '1') {
	header('location:index.php');
	exit;
}
include __DIR__ . '/connection.php';

$uploadDir = 'uploads/categories/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

// Generate slug function
function createSlug($str, $conn, $id = 0) {
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $str)));
    $slug = preg_replace('/-+/', '-', $slug);
    
    // Check if exists
    $sql = "SELECT id FROM categories WHERE slug = '$slug' AND id != $id";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        $slug = $slug . '-' . time();
    }
    return $slug;
}

$isEdit = false;
$catData = null;

if (isset($_GET['edit_id'])) {
    $isEdit = true;
    $id = (int)$_GET['edit_id'];
    $catData = $conn->query("SELECT * FROM categories WHERE id=$id")->fetch_assoc();
    if (!$catData) {
        header("Location: categories.php");
        exit;
    }
}

$nextOrder = 1;
if (!$isEdit) {
    $r = $conn->query("SELECT COALESCE(MAX(display_order), 0) + 1 AS next_order FROM categories");
    if ($r) $nextOrder = (int)$r->fetch_assoc()['next_order'];
}

// Handle Form Submit
if (isset($_POST['save_category'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $display_order = (int)$_POST['display_order'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    $slug = trim($_POST['slug']);
    
    $image_sql = "";
    $image = "";

    // Handle Remove Image
    if (isset($_POST['remove_image']) && $_POST['remove_image'] == '1' && $isEdit) {
        if (!empty($catData['image']) && file_exists($catData['image'])) {
            unlink($catData['image']);
        }
        $image_sql = ", image=''";
    }

    // Handle File Upload
    if (!empty($_FILES['image']['name'])) {
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','webp','gif'];
        if (in_array($ext, $allowed)) {
            // Delete old file if updating
            if ($isEdit && empty($image_sql) && !empty($catData['image']) && file_exists($catData['image'])) {
                unlink($catData['image']);
            }
            
            $filename = 'cat_' . uniqid() . '.' . $ext;
            if(move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $filename)) {
                $image = $uploadDir . $filename;
                $image_sql = ", image='" . $image . "'";
            }
        } else {
            $_SESSION['msg'] = "Invalid image format!";
            $_SESSION['msg_type'] = "danger";
            header("Location: categories.php");
            exit;
        }
    }

    if ($isEdit) {
        $id = (int)$_POST['cat_id'];
        if (empty($slug)) {
            $slug = createSlug($name, $conn, $id);
        } else {
            $slug = createSlug($slug, $conn, $id);
        }
        
        $sql = "UPDATE categories SET name='$name', slug='$slug', description='$description', display_order=$display_order, is_active=$is_active $image_sql WHERE id=$id";
        $conn->query($sql);
        $_SESSION['msg'] = "Category updated successfully!";
    } else {
        if (empty($slug)) {
            $slug = createSlug($name, $conn);
        } else {
            $slug = createSlug($slug, $conn);
        }
        
        $sql = "INSERT INTO categories (name, slug, description, image, display_order, is_active) VALUES ('$name', '$slug', '$description', '$image', $display_order, $is_active)";
        $conn->query($sql);
        $_SESSION['msg'] = "Category added successfully!";
    }
    
    header("Location: categories.php");
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title><?= $isEdit ? 'Edit Category' : 'Add New Category' ?></title>
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
        .toggle-switch input:checked + .toggle-slider { background-color: #6a11cb; }
        .toggle-switch input:checked + .toggle-slider:before { transform: translateX(20px); }

        .btn-purple { background: linear-gradient(135deg, #6a11cb, #a855f7); color: #fff; border: none; font-weight: 600; padding: 10px 24px; border-radius: 6px; }
        .btn-purple:hover { opacity: 0.9; color: #fff; }
        
        .btn-dark-grey { background: #343a40; color: #fff; border: none; font-weight: 600; padding: 10px 24px; border-radius: 6px; text-decoration: none; display: inline-block;}
        .btn-dark-grey:hover { background: #23272b; color: #fff; text-decoration: none; }
        
        .img-preview { width: 100px; height: 100px; object-fit: cover; border-radius: 6px; border: 1px solid #ddd; }
        .remove-img-link { color: #dc3545; font-size: 13px; font-weight: 600; cursor: pointer; display: inline-block; margin-left: 15px; }
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
                            <h1 class="m-0 text-dark"><i class="fas fa-th-large mr-2"></i> <?= $isEdit ? 'Edit Category' : 'Add New Category' ?></h1>
                        </div>
                        <div class="col-sm-6">
                            <ol class="breadcrumb float-sm-right">
                                <li class="breadcrumb-item"><a href="categories.php" style="color: #6a11cb;">Categories</a></li>
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
                            <input type="hidden" name="cat_id" value="<?= $catData['id'] ?>">
                            <input type="hidden" name="remove_image" id="removeImage" value="0">
                        <?php endif; ?>
                        
                        <div class="card form-card">
                            <div class="card-header-purple">
                                <i class="fas fa-info-circle mr-2"></i> Category Information
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <!-- Left Column -->
                                    <div class="col-md-7">
                                        <div class="form-group">
                                            <label class="form-label">Category Name <span class="text-danger">*</span></label>
                                            <input type="text" name="name" class="form-control" required value="<?= $isEdit ? htmlspecialchars($catData['name']) : '' ?>" placeholder="Enter category name">
                                        </div>
                                        
                                        <div class="form-group">
                                            <label class="form-label">Slug</label>
                                            <input type="text" name="slug" class="form-control" value="<?= $isEdit ? htmlspecialchars($catData['slug']) : '' ?>" placeholder="auto-generated-slug">
                                            <div class="form-text">Leave empty to auto-generate from name</div>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label class="form-label">Description</label>
                                            <textarea name="description" class="form-control" rows="4" placeholder="Enter category description"><?= $isEdit ? htmlspecialchars($catData['description']) : '' ?></textarea>
                                        </div>
                                    </div>
                                    
                                    <!-- Right Column -->
                                    <div class="col-md-5">
                                        <div class="form-group">
                                            <label class="form-label">Category Icon / Image</label>
                                            
                                            <?php if($isEdit && !empty($catData['image'])): ?>
                                                <div class="mb-3 d-flex align-items-center" id="currentImgWrapper">
                                                    <img src="<?= htmlspecialchars($catData['image']) ?>" class="img-preview" alt="Current Image">
                                                    <span class="remove-img-link" onclick="removeImageFunc()">Remove image</span>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <input type="file" name="image" class="form-control-file" accept="image/jpeg,image/png,image/webp,image/gif">
                                            <div class="form-text">Recommended size: 200x200px</div>
                                        </div>

                                        <div class="form-group">
                                            <label class="form-label">Display Order</label>
                                            <input type="number" name="display_order" class="form-control" min="1" value="<?= $isEdit ? $catData['display_order'] : $nextOrder ?>" style="max-width: 150px;">
                                        </div>
                                        
                                        <div class="form-group mt-4">
                                            <div class="d-flex align-items-center">
                                                <label class="toggle-switch mr-2">
                                                    <input type="checkbox" name="is_active" <?= (!$isEdit || $catData['is_active']) ? 'checked' : '' ?>>
                                                    <span class="toggle-slider"></span>
                                                </label>
                                                <span class="form-label mb-0" style="font-weight:normal; font-size:14px;">Active</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Card Footer for Buttons -->
                            <div class="card-footer bg-white border-top-0" style="border-radius: 0 0 8px 8px;">
                                <div class="d-flex justify-content-end align-items-center">
                                    <a href="categories.php" class="btn-dark-grey mr-3">
                                        <i class="fas fa-times mr-1"></i> Cancel
                                    </a>
                                    <button type="submit" name="save_category" class="btn-purple">
                                        <i class="fas fa-save mr-1"></i> <?= $isEdit ? 'Update Category' : 'Save Category' ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </section>
        </div>

        <?php include __DIR__ . '/includes/footer-links.php'; ?>
        
        <script>
            function removeImageFunc() {
                document.getElementById('currentImgWrapper').style.display = 'none';
                document.getElementById('removeImage').value = '1';
            }
        </script>

    </div>
</body>
</html>
