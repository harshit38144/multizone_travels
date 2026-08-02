<?php

if (isset($_POST['add_supplier'])) {

    // ✅ GET & SANITIZE INPUTS
    $category = trim($_POST['category']);
    $country = trim($_POST['country']);
    $city = trim($_POST['city']);
    $company = trim($_POST['company']);
    $designation = trim($_POST['designation']);
    $title = trim($_POST['title']);
    $name = trim($_POST['name']);
    $code = trim($_POST['code']);
    $mobile = trim($_POST['mobile']);
    $email = trim($_POST['email']);
    $address = trim($_POST['address']);

    // ✅ COMBINE NAME
    $contact_person = $title . " " . $name;

    // ✅ BASIC VALIDATION
    if (empty($company) || empty($name) || empty($mobile)) {
        $_SESSION['msg'] = "Please fill required fields (Company, Name, Mobile)";
        header("Location: suppliers.php");
        exit();
    }

    // ✅ PREPARED STATEMENT (SECURE)
    $stmt = $conn->prepare("INSERT INTO suppliers 
        (category, country, city, company, designation, contact_person, code, mobile, email, address, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");

    $stmt->bind_param(
        "ssssssssss",
        $category,
        $country,
        $city,
        $company,
        $designation,
        $contact_person,
        $code,
        $mobile,
        $email,
        $address
    );

    // ✅ EXECUTE
    if ($stmt->execute()) {
        $_SESSION['msg'] = "Supplier added successfully!";
    } else {
        $_SESSION['msg'] = "Error: " . $stmt->error;
    }

    $stmt->close();

    // ✅ REDIRECT BACK
    header("Location: suppliers.php");
    exit();
}

if (isset($_POST['add_hotel'])) {

    // 🔹 GET FORM DATA
    $hotel_name = mysqli_real_escape_string($conn, $_POST['hotel_name']);
    $category = mysqli_real_escape_string($conn, $_POST['category']);
    $destination = mysqli_real_escape_string($conn, $_POST['destination']);
    $details = mysqli_real_escape_string($conn, $_POST['details']);
    $contact_person = mysqli_real_escape_string($conn, $_POST['contact_person']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $address = mysqli_real_escape_string($conn, $_POST['address']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $hotel_link = mysqli_real_escape_string($conn, $_POST['hotel_link']);

    // 🔹 IMAGE UPLOAD
    // 🔹 IMAGE UPLOAD WITH COMPRESSION
    $photo = "";

    if (!empty($_FILES['photo']['name'])) {

        $target_dir = "uploads/hotels/";

        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        $tmp_file = $_FILES['photo']['tmp_name'];

        // ✅ CHECK IF REAL IMAGE
        $check = getimagesize($tmp_file);
        if ($check === false) {
            $_SESSION['msg'] = "File is not a valid image!";
            header("Location: hotel.php");
            exit;
        }

        // detect mime type
        $mime = $check['mime'];

        // create image resource
        switch ($mime) {
            case 'image/jpeg':
                $image = imagecreatefromjpeg($tmp_file);
                break;
            case 'image/png':
                $image = imagecreatefrompng($tmp_file);
                break;
            case 'image/webp':
                $image = imagecreatefromwebp($tmp_file);
                break;
            case 'image/gif':
                $image = imagecreatefromgif($tmp_file);
                break;
            default:
                $_SESSION['msg'] = "Unsupported image format!";
                header("Location: hotel.php");
                exit;
        }

        // 🔹 NEW FILE NAME
        $file_name = time() . ".jpg"; // save all as jpg (best compression)
        $target_file = $target_dir . $file_name;

        // 🔥 COMPRESSION LOOP (reduce quality until < 2MB)
        $quality = 90;

        do {
            ob_start();
            imagejpeg($image, null, $quality);
            $image_data = ob_get_clean();

            $size = strlen($image_data);

            $quality -= 5;

        } while ($size > (2 * 1024 * 1024) && $quality > 10);

        // save final image
        file_put_contents($target_file, $image_data);

        imagedestroy($image);

        $photo = $file_name;
    }

    // 🔹 VALIDATION
    if (empty($hotel_name) || empty($category)) {
        $_SESSION['msg'] = "Hotel Name and Category are required!";
        header("Location: hotel.php");
        exit;
    }

    // 🔹 INSERT QUERY
    $query = "INSERT INTO hotels 
        (hotel_name, category, destination, details, photo, contact_person, email, phone, address, status, hotel_link, created_at) 
        VALUES 
        ('$hotel_name', '$category', '$destination', '$details', '$photo', '$contact_person', '$email', '$phone', '$address', '$status', '$hotel_link', NOW())";

    if (mysqli_query($conn, $query)) {
        $_SESSION['msg'] = "Hotel added successfully!";
    } else {
        $_SESSION['msg'] = "Error: " . mysqli_error($conn);
    }

    header("Location: hotel.php");
    exit;
}

if (isset($_POST['add_activity'])) {

    // 🔹 GET FORM DATA
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $destination = mysqli_real_escape_string($conn, $_POST['destination']);
    $details = mysqli_real_escape_string($conn, $_POST['details']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);

    // 🔹 IMAGE UPLOAD
    $photo = "";

    if (!empty($_FILES['photo']['name'])) {

        $target_dir = "uploads/activities/";

        // create folder if not exists
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        $tmp_file = $_FILES['photo']['tmp_name'];

        // ✅ CHECK REAL IMAGE
        $check = getimagesize($tmp_file);
        if ($check === false) {
            $_SESSION['msg'] = "Invalid image file!";
            header("Location: activity.php");
            exit;
        }

        $mime = $check['mime'];

        // 🔹 CREATE IMAGE RESOURCE
        switch ($mime) {
            case 'image/jpeg':
                $image = imagecreatefromjpeg($tmp_file);
                break;
            case 'image/png':
                $image = imagecreatefrompng($tmp_file);
                break;
            case 'image/webp':
                $image = imagecreatefromwebp($tmp_file);
                break;
            case 'image/gif':
                $image = imagecreatefromgif($tmp_file);
                break;
            default:
                $_SESSION['msg'] = "Unsupported image format!";
                header("Location: activity.php");
                exit;
        }

        // 🔹 FILE NAME
        $file_name = time() . ".jpg"; // convert all to jpg
        $target_file = $target_dir . $file_name;

        // 🔥 COMPRESS LOOP (<2MB)
        $quality = 90;

        do {
            ob_start();
            imagejpeg($image, null, $quality);
            $image_data = ob_get_clean();

            $size = strlen($image_data);
            $quality -= 5;

        } while ($size > (2 * 1024 * 1024) && $quality > 10);

        file_put_contents($target_file, $image_data);

        imagedestroy($image);

        $photo = $file_name;
    }

    // 🔹 VALIDATION
    if (empty($name) || empty($destination)) {
        $_SESSION['msg'] = "Name and Destination are required!";
        header("Location: activity.php");
        exit;
    }

    // 🔹 INSERT QUERY
    $query = "INSERT INTO activities 
    (name, destination, details, photo, status, created_at) 
    VALUES 
    ('$name', '$destination', '$details', '$photo', '$status', NOW())";

    if (mysqli_query($conn, $query)) {
        $_SESSION['msg'] = "Activity added successfully!";
    } else {
        $_SESSION['msg'] = "Error: " . mysqli_error($conn);
    }

    header("Location: activity.php");
    exit;
}

if (isset($_POST['add_destination'])) {

    // 🔹 GET DATA
    $name = mysqli_real_escape_string($conn, $_POST['name']);

    // 🔹 VALIDATION
    if (empty($name)) {
        $_SESSION['msg'] = "Destination name is required!";
        header("Location: destinations.php");
        exit;
    }

    // 🔹 DUPLICATE CHECK (optional but recommended)
    $check = mysqli_query($conn, "SELECT id FROM destinations WHERE name='$name' AND is_deleted=0");

    if (mysqli_num_rows($check) > 0) {
        $_SESSION['msg'] = "Destination already exists!";
        header("Location: destinations.php");
        exit;
    }

    // 🔹 INSERT
    $query = "INSERT INTO destinations (name, created_at) 
              VALUES ('$name', NOW())";

    if (mysqli_query($conn, $query)) {
        $_SESSION['msg'] = "Destination added successfully!";
    } else {
        $_SESSION['msg'] = "Error: " . mysqli_error($conn);
    }

    header("Location: destinations.php");
    exit;
}


if (isset($_POST['add_day'])) {

    // 🔹 GET DATA
    $destination = mysqli_real_escape_string($conn, $_POST['destination']);
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $details = mysqli_real_escape_string($conn, $_POST['details']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);

    $check = mysqli_query($conn, "SELECT id FROM day_itinerary 
WHERE title='$title' AND destination='$destination' AND is_deleted=0");

    if (mysqli_num_rows($check) > 0) {
        $_SESSION['msg'] = "Itinerary already exists!";
        header("Location: day_itinerary.php");
        exit;
    }

    // 🔹 VALIDATION
    if (empty($destination) || empty($title)) {
        $_SESSION['msg'] = "Destination and Title are required!";
        header("Location: day_itinerary.php");
        exit;
    }

    // 🔹 INSERT
    $query = "INSERT INTO day_itinerary 
    (destination, title, details, status, created_at) 
    VALUES 
    ('$destination', '$title', '$details', '$status', NOW())";

    if (mysqli_query($conn, $query)) {
        $_SESSION['msg'] = "Day itinerary added successfully!";
    } else {
        $_SESSION['msg'] = "Error: " . mysqli_error($conn);
    }

    header("Location: day_itinerary.php");
    exit;
}

if (isset($_POST['update_day_image'])) {

    $id = $_POST['id'];

    if (!empty($_FILES['photo']['name'])) {

        $target_dir = "uploads/day/";

        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        $file_name = time() . "_" . $_FILES['photo']['name'];
        $target_file = $target_dir . $file_name;

        move_uploaded_file($_FILES['photo']['tmp_name'], $target_file);

        mysqli_query($conn, "UPDATE day_itinerary SET photo='$file_name' WHERE id='$id'");
    }

    exit;
}

if (isset($_POST['add_room_type'])) {

    // ✅ Get & sanitize inputs
    $name = mysqli_real_escape_string($conn, trim($_POST['name']));
    $status = mysqli_real_escape_string($conn, $_POST['status']);

    // ✅ Optional: get logged-in user
    $created_by = isset($_SESSION['username']) ? $_SESSION['username'] : 'Admin';

    // ✅ Validation
    if (empty($name)) {
        $_SESSION['msg'] = "Room Type name is required!";
        header("Location: room_type.php");
        exit;
    }

    // ✅ Check duplicate (optional but recommended)
    $check = mysqli_query($conn, "SELECT id FROM room_type WHERE name='$name' AND is_deleted=0");

    if (mysqli_num_rows($check) > 0) {
        $_SESSION['msg'] = "Room Type already exists!";
        header("Location: room_type.php");
        exit;
    }

    // ✅ Insert query
    $sql = "INSERT INTO room_type (name, status, created_by, updated_at)
            VALUES ('$name', '$status', '$created_by', NOW())";

    if (mysqli_query($conn, $sql)) {
        $_SESSION['msg'] = "Room Type added successfully!";
    } else {
        $_SESSION['msg'] = "Something went wrong!";
    }

    header("Location: room_type.php");
    exit;
}

if (isset($_POST['add_meal_plan'])) {

    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $created_by = $_SESSION['username'] ?? 'Admin';

    $sql = "INSERT INTO meal_plan (name, status, created_by, created_at)
            VALUES ('$name', '$status', '$created_by', NOW())";

    if (mysqli_query($conn, $sql)) {
        $_SESSION['msg'] = "Meal Plan added successfully!";
    } else {
        $_SESSION['msg'] = "Error adding Meal Plan!";
    }

    header("location:meal_plan.php");
}

if (isset($_POST['add_lead_source'])) {

    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $created_by = $_SESSION['username'] ?? 'Admin';

    $sql = "INSERT INTO lead_source (name, status, created_by, created_at)
            VALUES ('$name', '$status', '$created_by', NOW())";

    if (mysqli_query($conn, $sql)) {
        $_SESSION['msg'] = "Lead Source added successfully!";
    } else {
        $_SESSION['msg'] = "Error adding Lead Source!";
    }

    header("location:lead_source.php");
}

if (isset($_POST['add_expense_type'])) {

    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $created_by = $_SESSION['username'] ?? 'Admin';

    $sql = "INSERT INTO expense_type (name, status, created_by, created_at)
            VALUES ('$name', '$status', '$created_by', NOW())";

    if (mysqli_query($conn, $sql)) {
        $_SESSION['msg'] = "Expense Type added successfully!";
    } else {
        $_SESSION['msg'] = "Error adding Expense Type!";
    }

    header("location:expense_type.php");
}

if (isset($_POST['add_package_theme'])) {

    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $created_by = $_SESSION['username'] ?? 'Admin';

    $icon_name = "";

    if (!empty($_FILES['icon']['name'])) {

        $target_dir = "uploads/theme_icons/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        $icon_name = time() . "_" . basename($_FILES["icon"]["name"]);
        $target_file = $target_dir . $icon_name;

        move_uploaded_file($_FILES["icon"]["tmp_name"], $target_file);
    }

    $sql = "INSERT INTO package_theme (name, icon, status, created_by, created_at)
            VALUES ('$name', '$icon_name', '$status', '$created_by', NOW())";

    if (mysqli_query($conn, $sql)) {
        $_SESSION['msg'] = "Theme added successfully!";
    } else {
        $_SESSION['msg'] = "Error adding theme!";
    }

    header("location:package_theme.php");
}

if (isset($_POST['add_image_master'])) {

    $name = $_POST['name'];
    $status = $_POST['status'];

    $image = $_FILES['image']['name'];
    $tmp = $_FILES['image']['tmp_name'];

    move_uploaded_file($tmp, "uploads/images/" . $image);

    $sql = "INSERT INTO image_master(name,image,status,created_by,updated_at)
VALUES('$name','$image','$status','Admin',NOW())";

    mysqli_query($conn, $sql);

    $_SESSION['msg'] = "Image Added Successfully";

    header("location:image_master.php");

}

if (isset($_POST['save_eticket_info'])) {

// echo "<pre>";
// print_r($_POST);die;

    $info = mysqli_real_escape_string($conn, $_POST['important_info']);

    mysqli_query($conn, "
    UPDATE ticket_settings
    SET important_info='$info'
    WHERE id=1
    ") or die(mysqli_error($conn));

    $_SESSION['msg'] = "Information Updated";

    header("location:important_info.php");
    exit();
}
/* ===============================
   ADD SUPPLIER
================================*/

// if (isset($_POST['add_supplier'])) {

//     $city = mysqli_real_escape_string($conn, $_POST['city']);
//     $company = mysqli_real_escape_string($conn, $_POST['company']);

//     $title = mysqli_real_escape_string($conn, $_POST['title']);
//     $first_name = mysqli_real_escape_string($conn, $_POST['first_name']);
//     $last_name = mysqli_real_escape_string($conn, $_POST['last_name']);

//     $email = mysqli_real_escape_string($conn, $_POST['email']);

//     $code = mysqli_real_escape_string($conn, $_POST['code']);
//     $mobile = mysqli_real_escape_string($conn, $_POST['mobile']);

//     $address = mysqli_real_escape_string($conn, $_POST['address']);



//     $contact_person = $title . " " . $first_name . " " . $last_name;



//     $mobile_full = $code . $mobile;



//     $sql = "INSERT INTO suppliers
// (city,company,title,first_name,last_name,contact_person,email,code,mobile,address)
// VALUES
// ('$city','$company','$title','$first_name','$last_name','$contact_person','$email','$code','$mobile','$address')";

//     $query = mysqli_query($conn, $sql);



//     if ($query) {

//         $_SESSION['msg'] = "Supplier Added Successfully";

//     } else {

//         $_SESSION['msg'] = "Something went wrong";

//     }

//     header("location:suppliers.php");
//     exit();

// }

/* =========================
   ADD BANNER
========================= */
if (isset($_POST['add_banner'])) {

    $status = mysqli_real_escape_string($conn, $_POST['status']);

    $fileName = $_FILES['banner_file']['name'];
    $fileTmp = $_FILES['banner_file']['tmp_name'];
    $fileSize = $_FILES['banner_file']['size'];

    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'mov', 'avi', 'mkv', 'webm'];

    // ✅ 150MB LIMIT
    $maxSize = 150 * 1024 * 1024;

    if (!in_array($ext, $allowed)) {
        $_SESSION['msg'] = "Invalid file!";
        header("Location: add_banner.php");
        exit;
    }

    if ($fileSize > $maxSize) {
        $_SESSION['msg'] = "File must be under 150MB!";
        header("Location: add_banner.php");
        exit;
    }

    $uploadPath = "uploads/banners/";

    if (!is_dir($uploadPath)) {
        mkdir($uploadPath, 0777, true);
    }

    $newName = time() . rand(1000, 9999) . "." . $ext;

    move_uploaded_file($fileTmp, $uploadPath . $newName);

    mysqli_query($conn, "INSERT INTO banners(file,status)
                         VALUES('$newName','$status')");

    $_SESSION['msg'] = "Banner added successfully";
    header("Location: add_banner.php");
    exit;
}



/* =========================
   TOGGLE STATUS
========================= */
if (isset($_GET['toggle_banner_status'])) {

    $id = intval($_GET['toggle_banner_status']);

    mysqli_query($conn, "
        UPDATE banners
        SET status = IF(status=1,0,1)
        WHERE id='$id'
    ");

    header("Location: add_banner.php");
    exit;
}



/* =========================
   DELETE BANNER
========================= */
if (isset($_GET['delete_banner'])) {

    $id = intval($_GET['delete_banner']);

    $res = mysqli_query($conn, "SELECT file FROM banners WHERE id='$id'");
    $row = mysqli_fetch_assoc($res);

    if (!empty($row['file'])) {
        @unlink("uploads/banners/" . $row['file']);
    }

    mysqli_query($conn, "DELETE FROM banners WHERE id='$id'");

    $_SESSION['msg'] = "Banner deleted";
    header("Location: add_banner.php");
    exit;
}



/* =========================
   UPDATE BANNER
========================= */
if (isset($_POST['update_banner'])) {

    $id = intval($_POST['banner_id']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);

    $fileUpdate = "";

    if (!empty($_FILES['banner_file']['name'])) {

        $fileName = $_FILES['banner_file']['name'];
        $tmp = $_FILES['banner_file']['tmp_name'];
        $fileSize = $_FILES['banner_file']['size'];

        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'mov', 'avi', 'mkv', 'webm'];

        $maxSize = 150 * 1024 * 1024;

        if (!in_array($ext, $allowed)) {
            $_SESSION['msg'] = "Invalid file!";
            header("Location: add_banner.php");
            exit;
        }

        if ($fileSize > $maxSize) {
            $_SESSION['msg'] = "File must be under 150MB!";
            header("Location: add_banner.php");
            exit;
        }

        $newName = time() . rand(1000, 9999) . "." . $ext;

        move_uploaded_file($tmp, "uploads/banners/" . $newName);

        // delete old file
        $old = mysqli_fetch_assoc(
            mysqli_query($conn, "SELECT file FROM banners WHERE id='$id'")
        );

        @unlink("uploads/banners/" . $old['file']);

        $fileUpdate = ", file='$newName'";
    }

    mysqli_query($conn, "
        UPDATE banners
        SET status='$status' $fileUpdate
        WHERE id='$id'
    ");

    $_SESSION['msg'] = "Banner updated";
    header("Location: add_banner.php");
    exit;
}



/* =========================
   UPDATE ABOUT SECTION
========================= */
if (isset($_POST['update_about'])) {

    $id = intval($_POST['id']);

    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);

    $f1_title = mysqli_real_escape_string($conn, $_POST['f1_title']);
    $f1_desc = mysqli_real_escape_string($conn, $_POST['f1_desc']);

    $f2_title = mysqli_real_escape_string($conn, $_POST['f2_title']);
    $f2_desc = mysqli_real_escape_string($conn, $_POST['f2_desc']);

    $f3_title = mysqli_real_escape_string($conn, $_POST['f3_title']);
    $f3_desc = mysqli_real_escape_string($conn, $_POST['f3_desc']);

    $imageUpdate = "";

    if (!empty($_FILES['image']['name'])) {

        $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $newName = time() . rand(1000, 9999) . "." . $ext;

        move_uploaded_file(
            $_FILES['image']['tmp_name'],
            "uploads/about/" . $newName
        );

        $imageUpdate = ", image='$newName'";
    }

    mysqli_query($conn, "
        UPDATE about_section SET
        title='$title',
        description='$description',
        feature1_title='$f1_title',
        feature1_desc='$f1_desc',
        feature2_title='$f2_title',
        feature2_desc='$f2_desc',
        feature3_title='$f3_title',
        feature3_desc='$f3_desc'
        $imageUpdate
        WHERE id='$id'
    ");

    $_SESSION['msg'] = "About updated successfully";
    header("Location: about_section.php");
    exit;
}


/* =========================
   ADD COUNTER
========================= */
if (isset($_POST['add_counter'])) {

    $number = $_POST['number'];
    $symbol = $_POST['symbol'];
    $title = $_POST['title'];

    $iconName = "";

    if (!empty($_FILES['icon']['name'])) {

        $iconName = time() . "_" . $_FILES['icon']['name'];
        move_uploaded_file(
            $_FILES['icon']['tmp_name'],
            "uploads/icons/" . $iconName
        );
    }

    mysqli_query($conn, "
        INSERT INTO counters(number,symbol,title,icon)
        VALUES('$number','$symbol','$title','$iconName')
    ");

    header("Location: manage_counters.php");
    exit;
}


/* =========================
   TOGGLE STATUS
========================= */
if (isset($_GET['toggle_counter'])) {

    $id = $_GET['toggle_counter'];

    mysqli_query($conn, "
UPDATE counters
SET status = IF(status=1,0,1)
WHERE id='$id'
");

    header("Location: manage_counters.php");
    exit;
}


/* =========================
   DELETE COUNTER
========================= */
if (isset($_GET['delete_counter'])) {

    $id = intval($_GET['delete_counter']);

    mysqli_query($conn, "DELETE FROM counters WHERE id='$id'");

    $_SESSION['msg'] = "Counter deleted";
    header("Location: manage_counters.php");
    exit;
}

if (isset($_POST['add_home_gallery'])) {

    $title = $_POST['title'];
    $desc = $_POST['description'];

    $imageName = time() . $_FILES['image']['name'];

    move_uploaded_file(
        $_FILES['image']['tmp_name'],
        "uploads/home_gallery/" . $imageName
    );

    mysqli_query($conn, "
INSERT INTO home_gallery(title,description,image)
VALUES('$title','$desc','$imageName')
");

    header("Location: manage_home_gallery.php");
    exit;
}

if (isset($_GET['toggle_gallery'])) {

    $id = $_GET['toggle_gallery'];

    mysqli_query($conn, "
UPDATE home_gallery
SET status=IF(status=1,0,1)
WHERE id='$id'
");

    header("Location: manage_home_gallery.php");
    exit;
}

if (isset($_POST['add_home_service'])) {

    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $desc = mysqli_real_escape_string($conn, $_POST['description']);
    $link = mysqli_real_escape_string($conn, $_POST['link']);
    $status = intval($_POST['status']);

    mysqli_query($conn, "
INSERT INTO home_services(title,description,link,status)
VALUES('$title','$desc','$link','$status')
");

    $_SESSION['msg'] = "Service added";
    header("Location: manage_home_services.php");
    exit;
}


if (isset($_GET['toggle_home_service_status'])) {

    $id = intval($_GET['toggle_home_service_status']);

    mysqli_query($conn, "
UPDATE home_services
SET status = IF(status=1,0,1)
WHERE id='$id'
");

    header("Location: manage_home_services.php");
    exit;
}

if (isset($_POST['add_gallery'])) {

    $status = $_POST['status'];

    $allowedMime = ['image/jpeg', 'image/png', 'image/webp'];

    $path = "uploads/gallery/";

    if (!is_dir($path)) {
        mkdir($path, 0777, true);
    }

    foreach ($_FILES['gallery_image']['tmp_name'] as $key => $tmp_name) {

        if ($_FILES['gallery_image']['error'][$key] != 0) {
            continue;
        }

        $file = $tmp_name;
        $mime = mime_content_type($file);

        if (!in_array($mime, $allowedMime)) {
            continue;
        }

        $newName = time() . rand(1000, 9999) . ".webp";
        $destination = $path . $newName;

        list($width, $height) = getimagesize($file);

        $maxWidth = 1600;

        if ($width > $maxWidth) {

            $ratio = $maxWidth / $width;
            $newWidth = $maxWidth;
            $newHeight = $height * $ratio;

        } else {

            $newWidth = $width;
            $newHeight = $height;
        }

        $newImage = imagecreatetruecolor($newWidth, $newHeight);

        if ($mime == 'image/jpeg') {
            $source = imagecreatefromjpeg($file);
        } elseif ($mime == 'image/png') {
            $source = imagecreatefrompng($file);
            imagealphablending($newImage, false);
            imagesavealpha($newImage, true);
        } elseif ($mime == 'image/webp') {
            $source = imagecreatefromwebp($file);
        }

        imagecopyresampled(
            $newImage,
            $source,
            0,
            0,
            0,
            0,
            $newWidth,
            $newHeight,
            $width,
            $height
        );

        imagewebp($newImage, $destination, 75);

        imagedestroy($source);
        imagedestroy($newImage);

        mysqli_query($conn, "
            INSERT INTO gallery(image,status)
            VALUES('$newName','$status')
        ");
    }

    $_SESSION['msg'] = "Images uploaded successfully";
    header("Location: manage_gallery.php");
}


// ================= ADD PROJECT =================
/* =========================
   ADD PROJECT
========================= */
/* =========================
   ADD PROJECT WITH IMAGE COMPRESSION
========================= */

if (isset($_POST['add_project'])) {

    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $location = mysqli_real_escape_string($conn, $_POST['location']);
    $work = mysqli_real_escape_string($conn, $_POST['work_done']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $layout = mysqli_real_escape_string($conn, $_POST['layout']);

    $imageName = "";

    if (!empty($_FILES['image']['name'])) {

        $file = $_FILES['image']['tmp_name'];
        $size = $_FILES['image']['size'];

        $allowedMime = ['image/jpeg', 'image/png', 'image/webp'];

        $mime = mime_content_type($file);

        if (!in_array($mime, $allowedMime)) {
            $_SESSION['msg'] = "Invalid image!";
            header("Location: manage_projects.php");
            exit;
        }

        $path = "uploads/projects/";

        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }

        // save as webp
        $imageName = time() . rand(1000, 9999) . ".webp";
        $destination = $path . $imageName;

        list($width, $height) = getimagesize($file);

        // max width resize
        $maxWidth = 1600;

        if ($width > $maxWidth) {

            $ratio = $maxWidth / $width;
            $newWidth = $maxWidth;
            $newHeight = $height * $ratio;

        } else {

            $newWidth = $width;
            $newHeight = $height;
        }

        $newImage = imagecreatetruecolor($newWidth, $newHeight);

        // source create
        if ($mime == 'image/jpeg') {
            $source = imagecreatefromjpeg($file);
        } elseif ($mime == 'image/png') {
            $source = imagecreatefrompng($file);
            imagealphablending($newImage, false);
            imagesavealpha($newImage, true);
        } elseif ($mime == 'image/webp') {
            $source = imagecreatefromwebp($file);
        }

        imagecopyresampled(
            $newImage,
            $source,
            0,
            0,
            0,
            0,
            $newWidth,
            $newHeight,
            $width,
            $height
        );

        // save compressed webp (quality 75)
        imagewebp($newImage, $destination, 75);

        imagedestroy($source);
        imagedestroy($newImage);
    }


    mysqli_query($conn, "
        INSERT INTO projects
        (title,description,location,work_done,image,layout)
        VALUES
        ('$title','$description','$location','$work','$imageName','$layout')
    ");

    $_SESSION['msg'] = "Project added successfully";

    header("Location: manage_projects.php");
    exit;
}


/* =========================
   ADD SERVICE DETAIL
========================= */

if (isset($_POST['add_service_detail'])) {

    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $benefits = mysqli_real_escape_string($conn, $_POST['benefits']);
    $anchor = mysqli_real_escape_string($conn, $_POST['anchor']);
    $layout = mysqli_real_escape_string($conn, $_POST['layout']);

    $imageName = "";

    /* ================= IMAGE UPLOAD ================= */

    if (!empty($_FILES['image']['name'])) {

        $fileTmp = $_FILES['image']['tmp_name'];
        $fileSize = $_FILES['image']['size'];

        $allowedMime = ['image/jpeg', 'image/png', 'image/webp'];
        $mime = mime_content_type($fileTmp);

        if (!in_array($mime, $allowedMime)) {
            $_SESSION['msg'] = "Invalid image format!";
            header("Location: manage_service_details.php");
            exit;
        }

        $path = "uploads/services/";

        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }

        $imageName = time() . rand(1000, 9999) . ".webp";
        $destination = $path . $imageName;

        /* ===== IF IMAGE > 2MB → COMPRESS ===== */

        if ($fileSize > (2 * 1024 * 1024)) {

            ini_set('memory_limit', '1024M');

            list($width, $height) = getimagesize($fileTmp);

            $maxWidth = 1600;

            if ($width > $maxWidth) {
                $ratio = $maxWidth / $width;
                $newWidth = $maxWidth;
                $newHeight = intval($height * $ratio);
            } else {
                $newWidth = $width;
                $newHeight = $height;
            }

            $newImage = imagecreatetruecolor($newWidth, $newHeight);

            switch ($mime) {

                case 'image/jpeg':
                    $source = imagecreatefromjpeg($fileTmp);
                    break;

                case 'image/png':
                    $source = imagecreatefrompng($fileTmp);
                    imagealphablending($newImage, false);
                    imagesavealpha($newImage, true);
                    break;

                case 'image/webp':
                    $source = imagecreatefromwebp($fileTmp);
                    break;
            }

            imagecopyresampled(
                $newImage,
                $source,
                0,
                0,
                0,
                0,
                $newWidth,
                $newHeight,
                $width,
                $height
            );

            /* Smart compression loop */
            $quality = 80;

            do {
                imagewebp($newImage, $destination, $quality);
                $newSize = filesize($destination);
                $quality -= 5;
            } while ($newSize > (2 * 1024 * 1024) && $quality > 30);

            imagedestroy($source);
            imagedestroy($newImage);

        } else {

            /* ===== IF IMAGE < 2MB → NORMAL UPLOAD ===== */

            move_uploaded_file($fileTmp, $destination);
        }
    }

    /* ================= INSERT ================= */

    mysqli_query($conn, "
        INSERT INTO service_details
        (title, description, benefits, image, anchor, layout)
        VALUES
        ('$title', '$description', '$benefits', '$imageName', '$anchor', '$layout')
    ");

    $_SESSION['msg'] = "Service added successfully";

    header("Location: manage_service_details.php");
    exit;
}

?>