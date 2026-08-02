<?php
session_start();
if ($_SESSION['role'] != '1') {
	header('location:index.php');
	exit;
}
include __DIR__ . '/connection.php';

$uploadDir = 'uploads/group_departures/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

$isEdit = false;
$depData = null;
$depDates = [];
$depHotels = [];
$galleryArray = [];

if (isset($_GET['edit_id']) || isset($_GET['clone_id'])) {
    $id = isset($_GET['edit_id']) ? (int)$_GET['edit_id'] : (int)$_GET['clone_id'];
    $isEdit = isset($_GET['edit_id']) ? true : false;
    
    $depData = $conn->query("SELECT * FROM group_departures WHERE id=$id")->fetch_assoc();
    if (!$depData) {
        header("Location: group_departures.php");
        exit;
    }
    
    // Fetch related
    $dRes = $conn->query("SELECT * FROM group_departure_dates WHERE group_departure_id=$id ORDER BY departure_date ASC");
    while($dr = $dRes->fetch_assoc()) $depDates[] = $dr['departure_date'];
    
    $hRes = $conn->query("SELECT * FROM group_departure_hotels WHERE group_departure_id=$id ORDER BY id ASC");
    while($hr = $hRes->fetch_assoc()) $depHotels[] = $hr;
    
    if(!empty($depData['gallery_images'])){
        $galleryArray = json_decode($depData['gallery_images'], true);
        if(!is_array($galleryArray)) $galleryArray = [];
    }
}

// Handle Form Submit
if (isset($_POST['save_departure'])) {
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $destination_cities = mysqli_real_escape_string($conn, $_POST['destination_cities']);
    $city_nights_breakdown = mysqli_real_escape_string($conn, $_POST['city_nights_breakdown']);
    $ex_city = mysqli_real_escape_string($conn, $_POST['ex_city']);
    $departure_day = mysqli_real_escape_string($conn, $_POST['departure_day']);
    $departure_months = mysqli_real_escape_string($conn, $_POST['departure_months']);
    
    $duration_nights = (int)$_POST['duration_nights'];
    $duration_days = (int)$_POST['duration_days'];
    $total_seats = (int)$_POST['total_seats'];
    $seats_available = (int)$_POST['seats_available'];
    $max_group_size = (int)$_POST['max_group_size'];
    
    $star_rating = mysqli_real_escape_string($conn, $_POST['star_rating']);
    $price = (float)$_POST['price'];
    $discounted_price = (float)$_POST['discounted_price'];
    $operator_brand = mysqli_real_escape_string($conn, $_POST['operator_brand']);
    $guide_languages = mysqli_real_escape_string($conn, $_POST['guide_languages']);
    $experiences = mysqli_real_escape_string($conn, $_POST['experiences']);
    
    $is_flight_included = isset($_POST['is_flight_included']) ? 1 : 0;
    $is_group_package = isset($_POST['is_group_package']) ? 1 : 0;
    $is_fixed_package = isset($_POST['is_fixed_package']) ? 1 : 0;
    $is_meals_included = isset($_POST['is_meals_included']) ? 1 : 0;
    
    $onward_flight_name = mysqli_real_escape_string($conn, $_POST['onward_flight_name']);
    $onward_route = mysqli_real_escape_string($conn, $_POST['onward_route']);
    $return_flight_name = mysqli_real_escape_string($conn, $_POST['return_flight_name']);
    $return_route = mysqli_real_escape_string($conn, $_POST['return_route']);
    
    $highlights = mysqli_real_escape_string($conn, $_POST['highlights']);
    $inclusions = mysqli_real_escape_string($conn, $_POST['inclusions']);
    $exclusions = mysqli_real_escape_string($conn, $_POST['exclusions']);
    
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
    $is_newly_launched = isset($_POST['is_newly_launched']) ? 1 : 0;
    
    // File Uploads
    $image_sql = "";
    $featured_image = "";
    
    if (!empty($_FILES['featured_image']['name'])) {
        $ext = strtolower(pathinfo($_FILES['featured_image']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','webp'])) {
            $filename = 'dep_' . uniqid() . '.' . $ext;
            if(move_uploaded_file($_FILES['featured_image']['tmp_name'], $uploadDir . $filename)) {
                $featured_image = $uploadDir . $filename;
                $image_sql .= ", featured_image='" . $featured_image . "'";
            }
        }
    }
    
    // Gallery Uploads (Append to existing if editing)
    $finalGallery = ($isEdit) ? $galleryArray : [];
    if(isset($_FILES['gallery_images']['name'][0]) && !empty($_FILES['gallery_images']['name'][0])){
        foreach($_FILES['gallery_images']['name'] as $key => $name){
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','webp'])) {
                $filename = 'gal_' . uniqid() . '_' . $key . '.' . $ext;
                if(move_uploaded_file($_FILES['gallery_images']['tmp_name'][$key], $uploadDir . $filename)){
                    $finalGallery[] = $uploadDir . $filename;
                }
            }
        }
    }
    // Remove specific gallery images if flagged
    if(isset($_POST['remove_gallery'])){
        foreach($_POST['remove_gallery'] as $remImg){
            if(($key = array_search($remImg, $finalGallery)) !== false) {
                unset($finalGallery[$key]);
                if(file_exists($remImg)) unlink($remImg);
            }
        }
    }
    
    $gallery_images_json = mysqli_real_escape_string($conn, json_encode(array_values($finalGallery)));
    $image_sql .= ", gallery_images='" . $gallery_images_json . "'";

    if ($isEdit) {
        $dep_id = (int)$_POST['dep_id'];
        
        $sql = "UPDATE group_departures SET 
                title='$title', destination_cities='$destination_cities', city_nights_breakdown='$city_nights_breakdown', 
                ex_city='$ex_city', departure_day='$departure_day', departure_months='$departure_months', 
                duration_nights=$duration_nights, duration_days=$duration_days, total_seats=$total_seats, 
                seats_available=$seats_available, max_group_size=$max_group_size, star_rating='$star_rating', 
                price=$price, discounted_price=$discounted_price, operator_brand='$operator_brand', 
                guide_languages='$guide_languages', experiences='$experiences', is_flight_included=$is_flight_included, 
                is_group_package=$is_group_package, is_fixed_package=$is_fixed_package, is_meals_included=$is_meals_included, 
                onward_flight_name='$onward_flight_name', onward_route='$onward_route', return_flight_name='$return_flight_name', 
                return_route='$return_route', highlights='$highlights', inclusions='$inclusions', exclusions='$exclusions', 
                status='$status', is_featured=$is_featured, is_newly_launched=$is_newly_launched $image_sql 
                WHERE id=$dep_id";
        $conn->query($sql);
        $_SESSION['msg'] = "Group Departure updated successfully!";
    } else {
        $featured_image = $isEdit ? $depData['featured_image'] : $featured_image; 
        
        $sql = "INSERT INTO group_departures (
                title, destination_cities, city_nights_breakdown, ex_city, departure_day, departure_months, 
                duration_nights, duration_days, total_seats, seats_available, max_group_size, star_rating, 
                price, discounted_price, operator_brand, guide_languages, experiences, is_flight_included, 
                is_group_package, is_fixed_package, is_meals_included, onward_flight_name, onward_route, 
                return_flight_name, return_route, highlights, inclusions, exclusions, featured_image, gallery_images, 
                status, is_featured, is_newly_launched
                ) VALUES (
                '$title', '$destination_cities', '$city_nights_breakdown', '$ex_city', '$departure_day', '$departure_months',
                $duration_nights, $duration_days, $total_seats, $seats_available, $max_group_size, '$star_rating',
                $price, $discounted_price, '$operator_brand', '$guide_languages', '$experiences', $is_flight_included,
                $is_group_package, $is_fixed_package, $is_meals_included, '$onward_flight_name', '$onward_route',
                '$return_flight_name', '$return_route', '$highlights', '$inclusions', '$exclusions', '$featured_image', '$gallery_images_json',
                '$status', $is_featured, $is_newly_launched
                )";
        $conn->query($sql);
        $dep_id = $conn->insert_id;
        $_SESSION['msg'] = "Group Departure created successfully!";
    }
    
    // Sync Departure Dates
    $conn->query("DELETE FROM group_departure_dates WHERE group_departure_id=$dep_id");
    if(isset($_POST['dep_dates'])){
        foreach($_POST['dep_dates'] as $d){
            if(!empty($d)){
                $date_val = mysqli_real_escape_string($conn, $d);
                $conn->query("INSERT INTO group_departure_dates (group_departure_id, departure_date) VALUES ($dep_id, '$date_val')");
            }
        }
    }
    
    // Sync Hotels
    $conn->query("DELETE FROM group_departure_hotels WHERE group_departure_id=$dep_id");
    if(isset($_POST['hotel_city'])){
        for($i=0; $i<count($_POST['hotel_city']); $i++){
            $h_city = mysqli_real_escape_string($conn, $_POST['hotel_city'][$i]);
            $h_nights = (int)$_POST['hotel_nights'][$i];
            $h_name = mysqli_real_escape_string($conn, $_POST['hotel_name'][$i]);
            $h_room = mysqli_real_escape_string($conn, $_POST['hotel_room'][$i]);
            $h_meal = mysqli_real_escape_string($conn, $_POST['hotel_meal'][$i]);
            
            if(!empty($h_city) || !empty($h_name)){
                $conn->query("INSERT INTO group_departure_hotels (group_departure_id, city, nights, hotel_name, room_type, meal_plan) 
                              VALUES ($dep_id, '$h_city', $h_nights, '$h_name', '$h_room', '$h_meal')");
            }
        }
    }
    
    header("Location: group_departures.php");
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title><?= $isEdit ? 'Edit Departure' : 'Add Group Departure' ?></title>
    <?php include __DIR__ . '/includes/header-links.php'; ?>
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-bs4.min.css" rel="stylesheet">
    <style>
        .page-bg { background-color: #f4f6f9; }
        .card-header-purple { background: linear-gradient(135deg, #6a11cb 0%, #7b5ea7 50%, #a855f7 100%); color: #fff; padding: 12px 20px; border-radius: 8px 8px 0 0 !important; font-size: 16px; font-weight: 600; }
        .form-card { border: none; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 20px; }
        .form-label { font-weight: 600; color: #444; font-size: 13px; margin-bottom: 4px; }
        .form-control { font-size: 14px; }
        .form-text { font-size: 12px; color: #888; margin-top: 4px; }
        .btn-purple { background: linear-gradient(135deg, #6a11cb, #a855f7); color: #fff; border: none; font-weight: 600; padding: 10px; width: 100%; border-radius: 6px; margin-bottom: 10px; }
        .btn-purple:hover { opacity: 0.9; color: #fff; }
        
        /* Dynamic Row Styling */
        .dynamic-row { background: #fafafa; border: 1px solid #eee; border-radius: 6px; padding: 10px; margin-bottom: 10px; position: relative; }
        .btn-remove-row { color: #dc3545; background: none; border: none; cursor: pointer; padding: 5px; }
        .btn-remove-row:hover { color: #a71d2a; }
        
        .gallery-img-wrapper { position: relative; display: inline-block; margin: 5px; }
        .gallery-img-wrapper img { height: 80px; border-radius: 4px; border: 1px solid #ddd; }
        .gallery-img-wrapper .remove-gallery-img { position: absolute; top: -8px; right: -8px; background: #dc3545; color: #fff; border-radius: 50%; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 12px; }
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
                            <h1 class="m-0 text-dark" style="font-family:'Brush Script MT', cursive; font-size: 32px; font-weight: normal;">
                                <i class="fas fa-plus-circle mr-2"></i> <?= $isEdit ? 'Edit Departure' : 'Add Group Departure' ?>
                            </h1>
                        </div>
                        <div class="col-sm-6 text-right">
                            <a href="group_departures.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Back</a>
                        </div>
                    </div>
                </div>
            </div>

            <section class="content">
                <div class="container-fluid">
                    <form method="POST" action="" enctype="multipart/form-data">
                        <?php if($isEdit): ?>
                            <input type="hidden" name="dep_id" value="<?= $depData['id'] ?>">
                        <?php endif; ?>
                        
                        <div class="row">
                            <!-- Left Column (Main Content) -->
                            <div class="col-md-8">
                                
                                <!-- Basic Information -->
                                <div class="card form-card">
                                    <div class="card-header-purple"><i class="fas fa-info-circle mr-2"></i> Basic Information</div>
                                    <div class="card-body">
                                        <div class="form-group">
                                            <label class="form-label">Title *</label>
                                            <input type="text" name="title" class="form-control" required value="<?= $depData ? htmlspecialchars($depData['title']) : '' ?>">
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6 form-group">
                                                <label class="form-label">Destination Cities</label>
                                                <input type="text" name="destination_cities" class="form-control" placeholder="e.g. Krabi + Phuket" value="<?= $depData ? htmlspecialchars($depData['destination_cities']) : '' ?>">
                                            </div>
                                            <div class="col-md-6 form-group">
                                                <label class="form-label">City Nights Breakdown</label>
                                                <input type="text" name="city_nights_breakdown" class="form-control" placeholder="e.g. 2N Krabi, 2N Phuket" value="<?= $depData ? htmlspecialchars($depData['city_nights_breakdown']) : '' ?>">
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-4 form-group">
                                                <label class="form-label">Departure From (Ex-City)</label>
                                                <input type="text" name="ex_city" class="form-control" placeholder="e.g. Mumbai" value="<?= $depData ? htmlspecialchars($depData['ex_city']) : '' ?>">
                                            </div>
                                            <div class="col-md-4 form-group">
                                                <label class="form-label">Departure Dates</label>
                                                <div id="datesContainer">
                                                    <?php if(!empty($depDates)): ?>
                                                        <?php foreach($depDates as $date): ?>
                                                            <div class="d-flex mb-2 date-row">
                                                                <input type="date" name="dep_dates[]" class="form-control form-control-sm" value="<?= $date ?>">
                                                                <button type="button" class="btn-remove-row" onclick="$(this).parent().remove()"><i class="fas fa-times"></i></button>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <div class="d-flex mb-2 date-row">
                                                            <input type="date" name="dep_dates[]" class="form-control form-control-sm">
                                                            <button type="button" class="btn-remove-row" onclick="$(this).parent().remove()"><i class="fas fa-times"></i></button>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <button type="button" class="btn btn-sm btn-outline-primary mt-1" onclick="addDate()"><i class="fas fa-plus"></i> Add Date</button>
                                            </div>
                                            <div class="col-md-4 form-group">
                                                <label class="form-label">Departure Day</label>
                                                <select name="departure_day" class="form-control">
                                                    <option value="">Select Day</option>
                                                    <?php 
                                                        $days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
                                                        foreach($days as $day) {
                                                            $sel = ($depData && $depData['departure_day'] == $day) ? 'selected' : '';
                                                            echo "<option value='$day' $sel>$day</option>";
                                                        }
                                                    ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-4 form-group">
                                                <label class="form-label">Departure Months</label>
                                                <input type="text" name="departure_months" class="form-control" placeholder="e.g. Apr - Jun" value="<?= $depData ? htmlspecialchars($depData['departure_months']) : '' ?>">
                                            </div>
                                            <div class="col-md-4 form-group">
                                                <label class="form-label">Duration Nights</label>
                                                <input type="number" name="duration_nights" class="form-control" value="<?= $depData ? $depData['duration_nights'] : 0 ?>">
                                            </div>
                                            <div class="col-md-4 form-group">
                                                <label class="form-label">Duration Days</label>
                                                <input type="number" name="duration_days" class="form-control" value="<?= $depData ? $depData['duration_days'] : 0 ?>">
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-3 form-group">
                                                <label class="form-label">Total Seats</label>
                                                <input type="number" name="total_seats" class="form-control" value="<?= $depData ? $depData['total_seats'] : 0 ?>">
                                            </div>
                                            <div class="col-md-3 form-group">
                                                <label class="form-label">Seats Available</label>
                                                <input type="number" name="seats_available" class="form-control" value="<?= $depData ? $depData['seats_available'] : 0 ?>">
                                            </div>
                                            <div class="col-md-3 form-group">
                                                <label class="form-label">Max Group Size</label>
                                                <input type="number" name="max_group_size" class="form-control" value="<?= $depData ? $depData['max_group_size'] : 0 ?>">
                                            </div>
                                            <div class="col-md-3 form-group">
                                                <label class="form-label">Star Rating</label>
                                                <select name="star_rating" class="form-control">
                                                    <?php 
                                                        $stars = ['1 Star','2 Star','3 Star','4 Star','5 Star'];
                                                        foreach($stars as $star) {
                                                            $sel = ($depData && $depData['star_rating'] == $star) ? 'selected' : '';
                                                            echo "<option value='$star' $sel>$star</option>";
                                                        }
                                                    ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Pricing & Details -->
                                <div class="card form-card">
                                    <div class="card-header-purple"><i class="fas fa-tag mr-2"></i> Pricing & Details</div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-4 form-group">
                                                <label class="form-label">Price (₹)</label>
                                                <input type="number" name="price" step="0.01" class="form-control" value="<?= $depData ? $depData['price'] : 0 ?>">
                                            </div>
                                            <div class="col-md-4 form-group">
                                                <label class="form-label">Discounted Price (₹)</label>
                                                <input type="number" name="discounted_price" step="0.01" class="form-control" value="<?= $depData ? $depData['discounted_price'] : 0 ?>">
                                            </div>
                                            <div class="col-md-4 form-group">
                                                <label class="form-label">Operator Brand</label>
                                                <input type="text" name="operator_brand" class="form-control" placeholder="e.g. Nexus DMC" value="<?= $depData ? htmlspecialchars($depData['operator_brand']) : '' ?>">
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6 form-group">
                                                <label class="form-label">Guide Languages</label>
                                                <input type="text" name="guide_languages" class="form-control" placeholder="e.g. English, Hindi" value="<?= $depData ? htmlspecialchars($depData['guide_languages']) : '' ?>">
                                            </div>
                                            <div class="col-md-6 form-group">
                                                <label class="form-label">Experiences</label>
                                                <input type="text" name="experiences" class="form-control" placeholder="e.g. Family, Couples" value="<?= $depData ? htmlspecialchars($depData['experiences']) : '' ?>">
                                            </div>
                                        </div>
                                        <div class="row mt-2">
                                            <div class="col-md-3">
                                                <div class="custom-control custom-checkbox">
                                                    <input type="checkbox" name="is_flight_included" class="custom-control-input" id="cb1" <?= ($depData && $depData['is_flight_included']) ? 'checked' : '' ?>>
                                                    <label class="custom-control-label" for="cb1">Flight Included</label>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="custom-control custom-checkbox">
                                                    <input type="checkbox" name="is_group_package" class="custom-control-input" id="cb2" <?= ($depData && $depData['is_group_package']) ? 'checked' : '' ?>>
                                                    <label class="custom-control-label" for="cb2">Group Package</label>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="custom-control custom-checkbox">
                                                    <input type="checkbox" name="is_fixed_package" class="custom-control-input" id="cb3" <?= ($depData && $depData['is_fixed_package']) ? 'checked' : '' ?>>
                                                    <label class="custom-control-label" for="cb3">Fixed Package</label>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="custom-control custom-checkbox">
                                                    <input type="checkbox" name="is_meals_included" class="custom-control-input" id="cb4" <?= ($depData && $depData['is_meals_included']) ? 'checked' : '' ?>>
                                                    <label class="custom-control-label" for="cb4">Meals Included</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Flight Details -->
                                <div class="card form-card">
                                    <div class="card-header-purple"><i class="fas fa-plane mr-2"></i> Flight Details</div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <h6 class="text-primary"><i class="fas fa-plane-departure mr-1"></i> Onward</h6>
                                                <div class="form-group">
                                                    <label class="form-label">Flight Name</label>
                                                    <input type="text" name="onward_flight_name" class="form-control" placeholder="e.g. Akasa Air QP 618" value="<?= $depData ? htmlspecialchars($depData['onward_flight_name']) : '' ?>">
                                                </div>
                                                <div class="form-group">
                                                    <label class="form-label">Route</label>
                                                    <input type="text" name="onward_route" class="form-control" placeholder="e.g. Mumbai(BOM) -> Phuket(HKT)" value="<?= $depData ? htmlspecialchars($depData['onward_route']) : '' ?>">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <h6 class="text-success"><i class="fas fa-plane-arrival mr-1"></i> Return</h6>
                                                <div class="form-group">
                                                    <label class="form-label">Flight Name</label>
                                                    <input type="text" name="return_flight_name" class="form-control" placeholder="e.g. Akasa Air QP 619" value="<?= $depData ? htmlspecialchars($depData['return_flight_name']) : '' ?>">
                                                </div>
                                                <div class="form-group">
                                                    <label class="form-label">Route</label>
                                                    <input type="text" name="return_route" class="form-control" placeholder="e.g. Phuket(HKT) -> Mumbai(BOM)" value="<?= $depData ? htmlspecialchars($depData['return_route']) : '' ?>">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Hotels & Meal Plan -->
                                <div class="card form-card">
                                    <div class="card-header-purple d-flex justify-content-between align-items-center">
                                        <span><i class="fas fa-hotel mr-2"></i> Hotels & Meal Plan</span>
                                        <button type="button" class="btn btn-sm btn-light text-purple" onclick="addHotel()"><i class="fas fa-plus"></i> Add Hotel</button>
                                    </div>
                                    <div class="card-body">
                                        <div class="row text-muted" style="font-size:12px; font-weight:600; padding:0 10px; margin-bottom:5px;">
                                            <div class="col-md-2">City</div>
                                            <div class="col-md-2">Nights</div>
                                            <div class="col-md-3">Hotel Name</div>
                                            <div class="col-md-2">Room Type</div>
                                            <div class="col-md-2">Meal Plan</div>
                                            <div class="col-md-1 text-center"></div>
                                        </div>
                                        <div id="hotelContainer">
                                            <?php if(!empty($depHotels)): ?>
                                                <?php foreach($depHotels as $h): ?>
                                                    <div class="row dynamic-row align-items-center mb-2">
                                                        <div class="col-md-2"><input type="text" name="hotel_city[]" class="form-control form-control-sm" value="<?= htmlspecialchars($h['city']) ?>" placeholder="e.g. Krabi"></div>
                                                        <div class="col-md-2"><input type="number" name="hotel_nights[]" class="form-control form-control-sm" value="<?= $h['nights'] ?>"></div>
                                                        <div class="col-md-3"><input type="text" name="hotel_name[]" class="form-control form-control-sm" value="<?= htmlspecialchars($h['hotel_name']) ?>" placeholder="e.g. Apple A Day"></div>
                                                        <div class="col-md-2"><input type="text" name="hotel_room[]" class="form-control form-control-sm" value="<?= htmlspecialchars($h['room_type']) ?>" placeholder="e.g. Deluxe"></div>
                                                        <div class="col-md-2"><input type="text" name="hotel_meal[]" class="form-control form-control-sm" value="<?= htmlspecialchars($h['meal_plan']) ?>" placeholder="e.g. Breakfast"></div>
                                                        <div class="col-md-1 text-center"><button type="button" class="btn-remove-row" onclick="$(this).closest('.dynamic-row').remove()"><i class="fas fa-trash-alt"></i></button></div>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <div class="row dynamic-row align-items-center mb-2">
                                                    <div class="col-md-2"><input type="text" name="hotel_city[]" class="form-control form-control-sm" placeholder="e.g. Krabi"></div>
                                                    <div class="col-md-2"><input type="number" name="hotel_nights[]" class="form-control form-control-sm" value="1"></div>
                                                    <div class="col-md-3"><input type="text" name="hotel_name[]" class="form-control form-control-sm" placeholder="e.g. Apple A Day"></div>
                                                    <div class="col-md-2"><input type="text" name="hotel_room[]" class="form-control form-control-sm" placeholder="e.g. Deluxe"></div>
                                                    <div class="col-md-2"><input type="text" name="hotel_meal[]" class="form-control form-control-sm" placeholder="e.g. Breakfast"></div>
                                                    <div class="col-md-1 text-center"><button type="button" class="btn-remove-row" onclick="$(this).closest('.dynamic-row').remove()"><i class="fas fa-trash-alt"></i></button></div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Inclusions & Exclusions -->
                                <div class="card form-card">
                                    <div class="card-header-purple"><i class="fas fa-list-ul mr-2"></i> Inclusions & Exclusions</div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6 form-group">
                                                <label class="form-label">Inclusions</label>
                                                <textarea name="inclusions" class="form-control summernote" rows="3"><?= $depData ? htmlspecialchars($depData['inclusions']) : '' ?></textarea>
                                            </div>
                                            <div class="col-md-6 form-group">
                                                <label class="form-label">Exclusions</label>
                                                <textarea name="exclusions" class="form-control summernote" rows="3"><?= $depData ? htmlspecialchars($depData['exclusions']) : '' ?></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </div>
                            
                            <!-- Right Column (Sidebar Settings) -->
                            <div class="col-md-4">
                                
                                <!-- Publish Settings -->
                                <div class="card form-card">
                                    <div class="card-header-purple"><i class="fas fa-cog mr-2"></i> Publish</div>
                                    <div class="card-body">
                                        <div class="form-group">
                                            <label class="form-label">Status</label>
                                            <select name="status" class="form-control">
                                                <option value="Draft" <?= ($depData && $depData['status'] == 'Draft') ? 'selected' : '' ?>>Draft</option>
                                                <option value="Published" <?= ($depData && $depData['status'] == 'Published') ? 'selected' : '' ?>>Published</option>
                                            </select>
                                        </div>
                                        <div class="form-group mb-4">
                                            <div class="custom-control custom-checkbox mb-2">
                                                <input type="checkbox" name="is_featured" class="custom-control-input" id="is_featured" <?= ($depData && $depData['is_featured']) ? 'checked' : '' ?>>
                                                <label class="custom-control-label" for="is_featured">Featured on Homepage</label>
                                            </div>
                                            <div class="custom-control custom-checkbox">
                                                <input type="checkbox" name="is_newly_launched" class="custom-control-input" id="is_newly_launched" <?= ($depData && $depData['is_newly_launched']) ? 'checked' : '' ?>>
                                                <label class="custom-control-label" for="is_newly_launched">Newly Launched Badge</label>
                                            </div>
                                        </div>
                                        <button type="submit" name="save_departure" class="btn-purple"><i class="fas fa-save mr-1"></i> Save Departure</button>
                                    </div>
                                </div>

                                <!-- Featured Image -->
                                <div class="card form-card">
                                    <div class="card-header-purple"><i class="fas fa-image mr-2"></i> Featured Image</div>
                                    <div class="card-body">
                                        <?php if($isEdit && !empty($depData['featured_image'])): ?>
                                            <div class="mb-3 text-center">
                                                <img src="<?= htmlspecialchars($depData['featured_image']) ?>" style="max-width:100%; height:auto; border-radius:6px;">
                                            </div>
                                        <?php endif; ?>
                                        <input type="file" name="featured_image" class="form-control-file" accept="image/*">
                                    </div>
                                </div>
                                
                                <!-- Gallery Images -->
                                <div class="card form-card">
                                    <div class="card-header-purple"><i class="fas fa-images mr-2"></i> Gallery Images</div>
                                    <div class="card-body">
                                        <?php if($isEdit && !empty($galleryArray)): ?>
                                            <div class="mb-3 d-flex flex-wrap">
                                                <?php foreach($galleryArray as $gImg): ?>
                                                    <div class="gallery-img-wrapper" id="gal_<?= md5($gImg) ?>">
                                                        <img src="<?= htmlspecialchars($gImg) ?>">
                                                        <div class="remove-gallery-img" onclick="removeGalleryImg('<?= htmlspecialchars($gImg) ?>', 'gal_<?= md5($gImg) ?>')"><i class="fas fa-times"></i></div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                        <input type="file" name="gallery_images[]" class="form-control-file" accept="image/*" multiple>
                                        <div class="form-text mt-1">You can select multiple images.</div>
                                        <div id="galleryRemoveInputs"></div>
                                    </div>
                                </div>
                                
                                <!-- Highlights -->
                                <div class="card form-card">
                                    <div class="card-header-purple"><i class="fas fa-star mr-2"></i> Highlights</div>
                                    <div class="card-body p-0">
                                        <textarea name="highlights" class="form-control summernote"><?= $depData ? htmlspecialchars($depData['highlights']) : '' ?></textarea>
                                    </div>
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
            $(document).ready(function() {
                $('.summernote').summernote({
                    height: 200,
                    toolbar: [
                        ['style', ['style']],
                        ['font', ['bold', 'italic', 'underline', 'clear']],
                        ['para', ['ul', 'ol', 'paragraph']],
                        ['view', ['fullscreen', 'codeview']]
                    ]
                });
            });

            function addDate() {
                let html = `
                <div class="d-flex mb-2 date-row">
                    <input type="date" name="dep_dates[]" class="form-control form-control-sm">
                    <button type="button" class="btn-remove-row" onclick="$(this).parent().remove()"><i class="fas fa-times"></i></button>
                </div>`;
                $('#datesContainer').append(html);
            }

            function addHotel() {
                let html = `
                <div class="row dynamic-row align-items-center mb-2">
                    <div class="col-md-2"><input type="text" name="hotel_city[]" class="form-control form-control-sm" placeholder="e.g. Krabi"></div>
                    <div class="col-md-2"><input type="number" name="hotel_nights[]" class="form-control form-control-sm" value="1"></div>
                    <div class="col-md-3"><input type="text" name="hotel_name[]" class="form-control form-control-sm" placeholder="e.g. Apple A Day"></div>
                    <div class="col-md-2"><input type="text" name="hotel_room[]" class="form-control form-control-sm" placeholder="e.g. Deluxe"></div>
                    <div class="col-md-2"><input type="text" name="hotel_meal[]" class="form-control form-control-sm" placeholder="e.g. Breakfast"></div>
                    <div class="col-md-1 text-center"><button type="button" class="btn-remove-row" onclick="$(this).closest('.dynamic-row').remove()"><i class="fas fa-trash-alt"></i></button></div>
                </div>`;
                $('#hotelContainer').append(html);
            }
            
            function removeGalleryImg(imgPath, divId) {
                $('#' + divId).hide();
                $('#galleryRemoveInputs').append(`<input type="hidden" name="remove_gallery[]" value="${imgPath}">`);
            }
        </script>
    </div>
</body>
</html>
