<?PHP
// UPDATE LEAD
if (isset($_POST['update_lead'])) {

    $id = intval($_POST['lead_id']);
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $subject = mysqli_real_escape_string($conn, $_POST['subject']);
    $message = mysqli_real_escape_string($conn, $_POST['message']);

    $sql = "UPDATE leads SET 
                name = '$name',
                email = '$email',
                phone = '$phone',
                subject = '$subject',
                message = '$message',
                updated_at = NOW()
            WHERE id = $id";

    if (mysqli_query($conn, $sql)) {
        $_SESSION['msg'] = "Lead updated successfully";
    } else {
        $_SESSION['msg'] = "Something went wrong";
    }

    header("Location: view_leads.php");
    exit;
}
// UPDATE LUCKYDRAW ENTRY
if (isset($_POST['update_luckydraw_entry'])) {

    $id = intval($_POST['entry_id']);
    $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
    $mobile_no = mysqli_real_escape_string($conn, $_POST['mobile_no']);
    $lottery_no = mysqli_real_escape_string($conn, $_POST['lottery_no']);

    $sql = "UPDATE luckydraw_entries SET 
                name = '$full_name',
                mobile_no = '$mobile_no',
                lottery_no = '$lottery_no',
                updated_at = NOW()
            WHERE id = $id";

    if (mysqli_query($conn, $sql)) {
        $_SESSION['msg'] = "Luckydraw entry updated successfully";
    } else {
        $_SESSION['msg'] = "Something went wrong";
    }

    header("Location: view_luckydraw_entry.php");
    exit;
}


if (isset($_POST['update_counter'])) {

    $id = $_POST['counter_id'];
    $number = $_POST['number'];
    $symbol = $_POST['symbol'];
    $title = $_POST['title'];

    $iconUpdate = "";

    if (!empty($_FILES['icon']['name'])) {

        $iconName = time() . "_" . $_FILES['icon']['name'];

        move_uploaded_file(
            $_FILES['icon']['tmp_name'],
            "uploads/icons/" . $iconName
        );

        $iconUpdate = ", icon='$iconName'";
    }

    mysqli_query($conn, "
        UPDATE counters
        SET number='$number',
            symbol='$symbol',
            title='$title'
            $iconUpdate
        WHERE id='$id'
    ");

    header("Location: manage_counters.php");
    exit;
}

if (isset($_POST['update_gallery'])) {

    $id = $_POST['gallery_id'];
    $title = $_POST['title'];
    $desc = $_POST['description'];

    $imageUpdate = "";

    if (!empty($_FILES['image']['name'])) {

        $imageName = time() . $_FILES['image']['name'];

        move_uploaded_file(
            $_FILES['image']['tmp_name'],
            "uploads/home_gallery/" . $imageName
        );

        $imageUpdate = ", image='$imageName'";
    }

    mysqli_query($conn, "
UPDATE home_gallery
SET title='$title',
description='$desc'
$imageUpdate
WHERE id='$id'
");

    header("Location: manage_home_gallery.php");
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

if (isset($data['updateServiceOrder'])) {

    foreach ($data['updateServiceOrder'] as $row) {

        mysqli_query($conn, "
UPDATE home_services
SET sort_order='{$row['position']}'
WHERE id='{$row['id']}'
");

    }

    exit;
}

/* =========================
   UPDATE PROJECT
========================= */

if (isset($_POST['update_project'])) {

    $id = intval($_POST['project_id']);

    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $location = mysqli_real_escape_string($conn, $_POST['location']);
    $work = mysqli_real_escape_string($conn, $_POST['work_done']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $layout = mysqli_real_escape_string($conn, $_POST['layout']);

    $imageUpdate = "";

    /* ========= IMAGE UPLOAD ========= */

    /* ========= IMAGE UPLOAD SAFE ========= */

    /* ========= IMAGE UPLOAD SAFE (< 2MB) ========= */

    if (!empty($_FILES['image']['name'])) {

        $file = $_FILES['image']['tmp_name'];

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

        ini_set('memory_limit', '1024M');

        list($width, $height) = getimagesize($file);

        // Resize large images
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
                $source = imagecreatefromjpeg($file);
                break;

            case 'image/png':
                $source = imagecreatefrompng($file);
                imagealphablending($newImage, false);
                imagesavealpha($newImage, true);
                break;

            case 'image/webp':
                $source = imagecreatefromwebp($file);
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

        $newName = time() . rand(1000, 9999) . ".webp";
        $destination = $path . $newName;

        /* ===== SMART COMPRESSION LOOP ===== */

        $quality = 80; // start quality
        do {
            imagewebp($newImage, $destination, $quality);
            $fileSize = filesize($destination);
            $quality -= 5;
        } while ($fileSize > (2 * 1024 * 1024) && $quality > 30);

        imagedestroy($source);
        imagedestroy($newImage);


        /* DELETE OLD IMAGE */

        $old = mysqli_fetch_assoc(
            mysqli_query($conn, "SELECT image FROM projects WHERE id='$id'")
        );

        if (!empty($old['image'])) {
            @unlink($path . $old['image']);
        }

        $imageUpdate = ", image='$newName'";
    }

    /* ========= UPDATE QUERY ========= */

    mysqli_query($conn, "
        UPDATE projects SET
        title='$title',
        description='$description',
        location='$location',
        work_done='$work',
        layout='$layout'
        $imageUpdate
        WHERE id='$id'
    ");

    $_SESSION['msg'] = "Project updated successfully";

    header("Location: manage_projects.php");
    exit;
}


/* =========================
   UPDATE SERVICE DETAIL
========================= */

if (isset($_POST['update_service_detail'])) {

    $id          = intval($_POST['id']);
    $title       = mysqli_real_escape_string($conn,$_POST['title']);
    $description = mysqli_real_escape_string($conn,$_POST['description']);
    $benefits    = mysqli_real_escape_string($conn,$_POST['benefits']);
    $anchor      = mysqli_real_escape_string($conn,$_POST['anchor']);
    $layout      = mysqli_real_escape_string($conn,$_POST['layout']);

    $imageUpdate = "";

    if (!empty($_FILES['image']['name'])) {

        $fileTmp  = $_FILES['image']['tmp_name'];
        $fileSize = $_FILES['image']['size'];

        $allowedMime = ['image/jpeg','image/png','image/webp'];
        $mime = mime_content_type($fileTmp);

        if (in_array($mime,$allowedMime)) {

            $path = "uploads/services/";

            if (!is_dir($path)) mkdir($path,0777,true);

            $newName = time().rand(1000,9999).".webp";
            $destination = $path.$newName;

            /* compress if >2MB */
            if ($fileSize > (2*1024*1024)) {

                list($width,$height) = getimagesize($fileTmp);

                $maxWidth = 1600;

                if ($width > $maxWidth) {
                    $ratio = $maxWidth / $width;
                    $newWidth = $maxWidth;
                    $newHeight = intval($height*$ratio);
                } else {
                    $newWidth = $width;
                    $newHeight = $height;
                }

                $newImage = imagecreatetruecolor($newWidth,$newHeight);

                switch ($mime) {
                    case 'image/jpeg': $source=imagecreatefromjpeg($fileTmp); break;
                    case 'image/png':
                        $source=imagecreatefrompng($fileTmp);
                        imagealphablending($newImage,false);
                        imagesavealpha($newImage,true);
                        break;
                    case 'image/webp': $source=imagecreatefromwebp($fileTmp); break;
                }

                imagecopyresampled(
                    $newImage,$source,
                    0,0,0,0,
                    $newWidth,$newHeight,
                    $width,$height
                );

                imagewebp($newImage,$destination,75);

                imagedestroy($source);
                imagedestroy($newImage);

            } else {

                move_uploaded_file($fileTmp,$destination);
            }

            /* delete old image */
            $old = mysqli_fetch_assoc(
                mysqli_query($conn,"SELECT image FROM service_details WHERE id='$id'")
            );

            if (!empty($old['image'])) {
                @unlink($path.$old['image']);
            }

            $imageUpdate = ", image='$newName'";
        }
    }

    mysqli_query($conn,"
        UPDATE service_details SET
        title='$title',
        description='$description',
        benefits='$benefits',
        anchor='$anchor',
        layout='$layout'
        $imageUpdate
        WHERE id='$id'
    ");

    $_SESSION['msg'] = "Service updated successfully";

    header("Location: manage_service_details.php");
    exit;
}