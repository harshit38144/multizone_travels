<?php
session_start();
if ($_SESSION['role'] != '1') {
	header('location:index.php');
	exit;
}
include __DIR__ . '/connection.php';

$uploadDir = 'uploads/packages/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

function createSlug($str, $conn, $id = 0) {
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $str)));
    $slug = preg_replace('/-+/', '-', $slug);
    $sql = "SELECT id FROM packages WHERE slug = '$slug' AND id != $id";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        $slug = $slug . '-' . time();
    }
    return $slug;
}

$isEdit = false;
$pkgData = null;
$selected_cats = [];
$selected_dests = [];
$itineraries = [];

if (isset($_GET['edit_id']) || isset($_GET['clone_id'])) {
    $id = isset($_GET['edit_id']) ? (int)$_GET['edit_id'] : (int)$_GET['clone_id'];
    $isEdit = isset($_GET['edit_id']) ? true : false;
    
    $pkgData = $conn->query("SELECT * FROM packages WHERE id=$id")->fetch_assoc();
    if (!$pkgData) {
        header("Location: packages.php");
        exit;
    }
    
    // Fetch mapped categories
    $cRes = $conn->query("SELECT category_id FROM package_category_map WHERE package_id=$id");
    while($cr = $cRes->fetch_assoc()) $selected_cats[] = $cr['category_id'];
    
    // Fetch mapped destinations
    $dRes = $conn->query("SELECT destination_id FROM package_destination_map WHERE package_id=$id");
    while($dr = $dRes->fetch_assoc()) $selected_dests[] = $dr['destination_id'];
    
    // Fetch itineraries
    $iRes = $conn->query("SELECT * FROM package_itineraries WHERE package_id=$id ORDER BY day_number ASC");
    while($ir = $iRes->fetch_assoc()) $itineraries[] = $ir;
}

if (isset($_POST['save_package'])) {
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $slug = trim($_POST['slug']);
    $duration_nights = (int)$_POST['duration_nights'];
    $duration_days = (int)$_POST['duration_days'];
    $original_price = (float)$_POST['original_price'];
    $sale_price = (float)$_POST['sale_price'];
    $group_size_min = (int)$_POST['group_size_min'];
    $group_size_max = (int)$_POST['group_size_max'];
    
    $highlights = mysqli_real_escape_string($conn, $_POST['highlights']);
    $inclusions = mysqli_real_escape_string($conn, $_POST['inclusions']);
    $exclusions = mysqli_real_escape_string($conn, $_POST['exclusions']);
    
    $meta_title = mysqli_real_escape_string($conn, $_POST['meta_title']);
    $meta_description = mysqli_real_escape_string($conn, $_POST['meta_description']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
    $is_trending = isset($_POST['is_trending']) ? 1 : 0;
    
    $image_sql = "";
    $featured_image = "";

    // Image Upload
    if (!empty($_FILES['featured_image']['name'])) {
        $ext = strtolower(pathinfo($_FILES['featured_image']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','webp'];
        if (in_array($ext, $allowed)) {
            $filename = 'pkg_' . uniqid() . '.' . $ext;
            if(move_uploaded_file($_FILES['featured_image']['tmp_name'], $uploadDir . $filename)) {
                $featured_image = $uploadDir . $filename;
                $image_sql = ", featured_image='" . $featured_image . "'";
            }
        }
    }

    if ($isEdit) {
        $pkg_id = (int)$_POST['pkg_id'];
        $slug = empty($slug) ? createSlug($title, $conn, $pkg_id) : createSlug($slug, $conn, $pkg_id);
        
        $sql = "UPDATE packages SET 
                title='$title', slug='$slug', duration_nights=$duration_nights, duration_days=$duration_days, 
                original_price=$original_price, sale_price=$sale_price, group_size_min=$group_size_min, group_size_max=$group_size_max, 
                highlights='$highlights', inclusions='$inclusions', exclusions='$exclusions', 
                meta_title='$meta_title', meta_description='$meta_description', status='$status', 
                is_featured=$is_featured, is_trending=$is_trending $image_sql 
                WHERE id=$pkg_id";
        $conn->query($sql);
        $_SESSION['msg'] = "Package updated successfully!";
    } else {
        $slug = empty($slug) ? createSlug($title, $conn) : createSlug($slug, $conn);
        $featured_image = $isEdit ? $pkgData['featured_image'] : $featured_image; // if clone, might need to copy image, but simpler to leave blank or require re-upload
        
        $sql = "INSERT INTO packages (
                title, slug, duration_nights, duration_days, original_price, sale_price, group_size_min, group_size_max, 
                highlights, inclusions, exclusions, meta_title, meta_description, status, is_featured, is_trending, featured_image
                ) VALUES (
                '$title', '$slug', $duration_nights, $duration_days, $original_price, $sale_price, $group_size_min, $group_size_max,
                '$highlights', '$inclusions', '$exclusions', '$meta_title', '$meta_description', '$status', $is_featured, $is_trending, '$featured_image'
                )";
        $conn->query($sql);
        $pkg_id = $conn->insert_id;
        $_SESSION['msg'] = "Package created successfully!";
    }
    
    // Sync Categories
    $conn->query("DELETE FROM package_category_map WHERE package_id=$pkg_id");
    if(isset($_POST['categories']) && is_array($_POST['categories'])){
        foreach($_POST['categories'] as $cat){
            $cid = (int)$cat;
            $conn->query("INSERT INTO package_category_map (package_id, category_id) VALUES ($pkg_id, $cid)");
        }
    }
    
    // Sync Destinations
    $conn->query("DELETE FROM package_destination_map WHERE package_id=$pkg_id");
    if(isset($_POST['destinations']) && is_array($_POST['destinations'])){
        foreach($_POST['destinations'] as $dest){
            $did = (int)$dest;
            $conn->query("INSERT INTO package_destination_map (package_id, destination_id) VALUES ($pkg_id, $did)");
        }
    }
    
    // Sync Itineraries
    $conn->query("DELETE FROM package_itineraries WHERE package_id=$pkg_id");
    if(isset($_POST['iti_day'])){
        for($i=0; $i<count($_POST['iti_day']); $i++){
            $day_num = (int)$_POST['iti_day'][$i];
            $iti_title = mysqli_real_escape_string($conn, $_POST['iti_title'][$i]);
            $iti_desc = mysqli_real_escape_string($conn, $_POST['iti_desc'][$i]);
            $iti_meals = mysqli_real_escape_string($conn, $_POST['iti_meals'][$i]);
            $iti_acc = mysqli_real_escape_string($conn, $_POST['iti_acc'][$i]);
            $iti_act = mysqli_real_escape_string($conn, $_POST['iti_act'][$i]);
            
            $conn->query("INSERT INTO package_itineraries (package_id, day_number, title, description, meals, accommodation, activities) 
                          VALUES ($pkg_id, $day_num, '$iti_title', '$iti_desc', '$iti_meals', '$iti_acc', '$iti_act')");
        }
    }
    
    header("Location: packages.php");
    exit;
}

// Fetch all categories and destinations for the dropdowns
$all_cats = $conn->query("SELECT * FROM categories ORDER BY name ASC");
$all_dests = $conn->query("SELECT * FROM destinations ORDER BY name ASC");
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title><?= $isEdit ? 'Edit Package' : 'Add New Package' ?></title>
    <?php include __DIR__ . '/includes/header-links.php'; ?>
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-bs4.min.css" rel="stylesheet">
    <style>
        .page-bg { background-color: #f4f6f9; }
        .card-header-purple { background: linear-gradient(135deg, #6a11cb 0%, #7b5ea7 50%, #a855f7 100%); color: #fff; padding: 12px 20px; border-radius: 8px 8px 0 0 !important; font-size: 16px; font-weight: 600; }
        .form-card { border: none; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 20px; }
        .form-label { font-weight: 600; color: #444; font-size: 14px; margin-bottom: 6px; }
        .form-text { font-size: 12px; color: #888; margin-top: 4px; }
        .btn-purple { background: linear-gradient(135deg, #6a11cb, #a855f7); color: #fff; border: none; font-weight: 600; padding: 10px 24px; border-radius: 6px; }
        .btn-dark-grey { background: #4b5563; color: #fff; border: none; font-weight: 600; padding: 10px 24px; border-radius: 6px; }
        
        .itinerary-day { background: #fafafa; border: 1px solid #eee; border-radius: 8px; padding: 15px; margin-bottom: 15px; position: relative; }
        .day-header { font-weight: 600; font-size: 16px; margin-bottom: 15px; color: #6a11cb; border-bottom: 1px solid #ddd; padding-bottom: 5px; }
        .btn-remove-day { position: absolute; top: 15px; right: 15px; }
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
                            <h1 class="m-0 text-dark"><i class="fas fa-plus-circle mr-2"></i> <?= $isEdit ? 'Edit Package' : 'Add New Package' ?></h1>
                        </div>
                        <div class="col-sm-6 text-right">
                            <a href="packages.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Back to List</a>
                        </div>
                    </div>
                </div>
            </div>

            <section class="content">
                <div class="container-fluid">
                    <form method="POST" action="" enctype="multipart/form-data">
                        <?php if($isEdit): ?>
                            <input type="hidden" name="pkg_id" value="<?= $pkgData['id'] ?>">
                        <?php endif; ?>
                        
                        <!-- Basic Information -->
                        <div class="card form-card">
                            <div class="card-header-purple"><i class="fas fa-info-circle mr-2"></i> Basic Information</div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6 form-group">
                                        <label class="form-label">Package Title *</label>
                                        <input type="text" name="title" class="form-control" required value="<?= $pkgData ? htmlspecialchars($pkgData['title']) : '' ?>">
                                    </div>
                                    <div class="col-md-6 form-group">
                                        <label class="form-label">Slug</label>
                                        <input type="text" name="slug" class="form-control" value="<?= $pkgData ? htmlspecialchars($pkgData['slug']) : '' ?>">
                                        <div class="form-text">Leave empty to auto-generate from name</div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 form-group">
                                        <label class="form-label">Categories (Select Multiple)</label>
                                        <select name="categories[]" class="form-control" multiple style="height:120px;">
                                            <?php while($c = $all_cats->fetch_assoc()): ?>
                                                <option value="<?= $c['id'] ?>" <?= in_array($c['id'], $selected_cats) ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                                            <?php endwhile; ?>
                                        </select>
                                        <div class="form-text">Hold Ctrl (Windows) or Cmd (Mac) to select multiple</div>
                                    </div>
                                    <div class="col-md-6 form-group">
                                        <label class="form-label">Destinations (Select Multiple)</label>
                                        <select name="destinations[]" class="form-control" multiple style="height:120px;">
                                            <?php while($d = $all_dests->fetch_assoc()): ?>
                                                <option value="<?= $d['id'] ?>" <?= in_array($d['id'], $selected_dests) ? 'selected' : '' ?>><?= htmlspecialchars($d['name']) ?></option>
                                            <?php endwhile; ?>
                                        </select>
                                        <div class="form-text">Hold Ctrl (Windows) or Cmd (Mac) to select multiple</div>
                                    </div>
                                </div>

                                <div class="row mt-2">
                                    <div class="col-md-3 form-group">
                                        <label class="form-label">Duration (Nights)</label>
                                        <input type="number" name="duration_nights" class="form-control" min="0" value="<?= $pkgData ? $pkgData['duration_nights'] : 0 ?>">
                                    </div>
                                    <div class="col-md-3 form-group">
                                        <label class="form-label">Duration (Days)</label>
                                        <input type="number" name="duration_days" class="form-control" min="0" value="<?= $pkgData ? $pkgData['duration_days'] : 0 ?>">
                                    </div>
                                    <div class="col-md-3 form-group">
                                        <label class="form-label">Original Price (Rs.)</label>
                                        <input type="number" name="original_price" step="0.01" class="form-control" min="0" value="<?= $pkgData ? $pkgData['original_price'] : 0 ?>">
                                    </div>
                                    <div class="col-md-3 form-group">
                                        <label class="form-label">Sale Price (Rs.)</label>
                                        <input type="number" name="sale_price" step="0.01" class="form-control" min="0" value="<?= $pkgData ? $pkgData['sale_price'] : 0 ?>">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Images -->
                        <div class="card form-card">
                            <div class="card-header-purple"><i class="fas fa-images mr-2"></i> Images</div>
                            <div class="card-body">
                                <div class="form-group">
                                    <label class="form-label">Featured Image</label>
                                    <?php if($isEdit && !empty($pkgData['featured_image'])): ?>
                                        <div class="mb-2"><img src="<?= htmlspecialchars($pkgData['featured_image']) ?>" style="height:100px; border-radius:6px;"></div>
                                    <?php endif; ?>
                                    <input type="file" name="featured_image" class="form-control-file">
                                </div>
                            </div>
                        </div>

                        <!-- Package Details -->
                        <div class="card form-card">
                            <div class="card-header-purple"><i class="fas fa-list-alt mr-2"></i> Package Details</div>
                            <div class="card-body">
                                <div class="form-group">
                                    <label class="form-label">Highlights</label>
                                    <textarea name="highlights" class="form-control summernote" rows="3"><?= $pkgData ? htmlspecialchars($pkgData['highlights']) : '' ?></textarea>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 form-group">
                                        <label class="form-label">Inclusions</label>
                                        <textarea name="inclusions" class="form-control summernote" rows="3"><?= $pkgData ? htmlspecialchars($pkgData['inclusions']) : '' ?></textarea>
                                    </div>
                                    <div class="col-md-6 form-group">
                                        <label class="form-label">Exclusions</label>
                                        <textarea name="exclusions" class="form-control summernote" rows="3"><?= $pkgData ? htmlspecialchars($pkgData['exclusions']) : '' ?></textarea>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 form-group">
                                        <label class="form-label">Group Size (Min)</label>
                                        <input type="number" name="group_size_min" class="form-control" value="<?= $pkgData ? $pkgData['group_size_min'] : 1 ?>">
                                    </div>
                                    <div class="col-md-6 form-group">
                                        <label class="form-label">Group Size (Max)</label>
                                        <input type="number" name="group_size_max" class="form-control" value="<?= $pkgData ? $pkgData['group_size_max'] : 10 ?>">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Day-wise Itinerary -->
                        <div class="card form-card">
                            <div class="card-header-purple d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-calendar-day mr-2"></i> Day-wise Itinerary</span>
                                <button type="button" class="btn btn-sm btn-light text-purple" onclick="addDay()"><i class="fas fa-plus"></i> Add Day</button>
                            </div>
                            <div class="card-body" id="itineraryContainer">
                                <!-- Existing Itineraries -->
                                <?php if(!empty($itineraries)): ?>
                                    <?php foreach($itineraries as $index => $iti): ?>
                                        <div class="itinerary-day" id="day_row_<?= $index+1 ?>">
                                            <button type="button" class="btn btn-sm btn-danger btn-remove-day" onclick="removeDay(<?= $index+1 ?>)"><i class="fas fa-trash"></i> Remove</button>
                                            <div class="day-header"><i class="fas fa-calendar mr-1"></i> Day <span class="day-num"><?= $index+1 ?></span></div>
                                            <input type="hidden" name="iti_day[]" class="iti-day-val" value="<?= $index+1 ?>">
                                            
                                            <div class="form-group">
                                                <label class="form-label">Day Title</label>
                                                <input type="text" name="iti_title[]" class="form-control" value="<?= htmlspecialchars($iti['title']) ?>" placeholder="e.g., Arrival at Destination">
                                            </div>
                                            <div class="form-group">
                                                <label class="form-label">Description</label>
                                                <textarea name="iti_desc[]" class="form-control" rows="3"><?= htmlspecialchars($iti['description']) ?></textarea>
                                            </div>
                                            <div class="row">
                                                <div class="col-md-4 form-group mb-0">
                                                    <label class="form-label">Meals</label>
                                                    <input type="text" name="iti_meals[]" class="form-control" value="<?= htmlspecialchars($iti['meals']) ?>" placeholder="e.g., Breakfast, Dinner">
                                                </div>
                                                <div class="col-md-4 form-group mb-0">
                                                    <label class="form-label">Accommodation</label>
                                                    <input type="text" name="iti_acc[]" class="form-control" value="<?= htmlspecialchars($iti['accommodation']) ?>" placeholder="e.g., Hotel Name">
                                                </div>
                                                <div class="col-md-4 form-group mb-0">
                                                    <label class="form-label">Activities</label>
                                                    <input type="text" name="iti_act[]" class="form-control" value="<?= htmlspecialchars($iti['activities']) ?>" placeholder="e.g., Sightseeing">
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- SEO Settings -->
                        <div class="card form-card">
                            <div class="card-header-purple"><i class="fas fa-search mr-2"></i> SEO Settings</div>
                            <div class="card-body">
                                <div class="form-group">
                                    <label class="form-label">Meta Title</label>
                                    <input type="text" name="meta_title" class="form-control" value="<?= $pkgData ? htmlspecialchars($pkgData['meta_title']) : '' ?>">
                                    <div class="form-text">Leave empty to use package title</div>
                                </div>
                                <div class="form-group mb-0">
                                    <label class="form-label">Meta Description</label>
                                    <textarea name="meta_description" class="form-control" rows="2"><?= $pkgData ? htmlspecialchars($pkgData['meta_description']) : '' ?></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Publish Settings -->
                        <div class="card form-card">
                            <div class="card-header-purple"><i class="fas fa-cog mr-2"></i> Publish Settings</div>
                            <div class="card-body">
                                <div class="row align-items-center mb-4">
                                    <div class="col-md-4">
                                        <label class="form-label">Status</label>
                                        <select name="status" class="form-control">
                                            <option value="Draft" <?= ($pkgData && $pkgData['status'] == 'Draft') ? 'selected' : '' ?>>Draft</option>
                                            <option value="Published" <?= ($pkgData && $pkgData['status'] == 'Published') ? 'selected' : '' ?>>Published</option>
                                            <option value="Archived" <?= ($pkgData && $pkgData['status'] == 'Archived') ? 'selected' : '' ?>>Archived</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4 pt-4">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" name="is_featured" class="custom-control-input" id="is_featured" <?= ($pkgData && $pkgData['is_featured']) ? 'checked' : '' ?>>
                                            <label class="custom-control-label font-weight-normal" for="is_featured"><i class="fas fa-star text-warning mr-1"></i> Mark as Featured</label>
                                        </div>
                                    </div>
                                    <div class="col-md-4 pt-4">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" name="is_trending" class="custom-control-input" id="is_trending" <?= ($pkgData && $pkgData['is_trending']) ? 'checked' : '' ?>>
                                            <label class="custom-control-label font-weight-normal" for="is_trending"><i class="fas fa-fire text-danger mr-1"></i> Mark as Trending</label>
                                        </div>
                                    </div>
                                </div>
                                <hr>
                                <div class="d-flex justify-content-end">
                                    <button type="submit" name="save_package" class="btn btn-purple mr-2"><i class="fas fa-save mr-1"></i> Save Package</button>
                                    <a href="packages.php" class="btn btn-dark-grey"><i class="fas fa-times mr-1"></i> Cancel</a>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </section>
        </div>

        <?php include __DIR__ . '/includes/footer-links.php'; ?>
        
        <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-bs4.min.js"></script>
        
        <script>
            let dayCount = <?= empty($itineraries) ? 0 : count($itineraries) ?>;
            
            $(document).ready(function() {
                $('.summernote').summernote({
                    height: 150,
                    toolbar: [
                        ['style', ['style']],
                        ['font', ['bold', 'italic', 'underline', 'clear']],
                        ['para', ['ul', 'ol', 'paragraph']],
                        ['view', ['fullscreen', 'codeview']]
                    ]
                });
                
                if(dayCount === 0) addDay(); // Add day 1 by default if empty
            });

            function addDay() {
                dayCount++;
                let html = `
                <div class="itinerary-day" id="day_row_${dayCount}">
                    <button type="button" class="btn btn-sm btn-danger btn-remove-day" onclick="removeDay(${dayCount})"><i class="fas fa-trash"></i> Remove</button>
                    <div class="day-header"><i class="fas fa-calendar mr-1"></i> Day <span class="day-num">${dayCount}</span></div>
                    <input type="hidden" name="iti_day[]" class="iti-day-val" value="${dayCount}">
                    
                    <div class="form-group">
                        <label class="form-label">Day Title</label>
                        <input type="text" name="iti_title[]" class="form-control" placeholder="e.g., Arrival at Destination">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea name="iti_desc[]" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-4 form-group mb-0">
                            <label class="form-label">Meals</label>
                            <input type="text" name="iti_meals[]" class="form-control" placeholder="e.g., Breakfast, Lunch, Dinner">
                        </div>
                        <div class="col-md-4 form-group mb-0">
                            <label class="form-label">Accommodation</label>
                            <input type="text" name="iti_acc[]" class="form-control" placeholder="e.g., Hotel Name">
                        </div>
                        <div class="col-md-4 form-group mb-0">
                            <label class="form-label">Activities</label>
                            <input type="text" name="iti_act[]" class="form-control" placeholder="e.g., Sightseeing, Trekking">
                        </div>
                    </div>
                </div>`;
                $('#itineraryContainer').append(html);
                reindexDays();
            }

            function removeDay(id) {
                if($('.itinerary-day').length > 1) {
                    $(`#day_row_${id}`).remove();
                    reindexDays();
                } else {
                    alert("You must have at least one itinerary day.");
                }
            }
            
            function reindexDays() {
                let index = 1;
                $('.itinerary-day').each(function() {
                    $(this).find('.day-num').text(index);
                    $(this).find('.iti-day-val').val(index);
                    index++;
                });
                dayCount = index - 1;
            }
        </script>
    </div>
</body>
</html>
