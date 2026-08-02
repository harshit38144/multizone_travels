<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/hotel_db.php';

header('Content-Type: application/json; charset=utf-8');

function hotelJsonResponse($success, $message, $extra = [])
{
    echo json_encode(array_merge([
        'success' => (bool) $success,
        'message' => (string) $message,
    ], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    hotelJsonResponse(false, 'Invalid request method.');
}

crmEnsureHotelTables($conn);

$id = (int) ($_POST['id'] ?? 0);
$destinationId = (int) ($_POST['destination'] ?? 0);
$cityId = (int) ($_POST['city_id'] ?? 0);
$hotelName = trim((string) ($_POST['hotel_name'] ?? ''));
$isDefault = !empty($_POST['default_hotel']) && $_POST['default_hotel'] !== '0';
$starCategory = trim((string) ($_POST['star_category'] ?? ''));
$starRating = (float) ($_POST['star_rating'] ?? 0);
$reviewLink = trim((string) ($_POST['review_link'] ?? ''));
$address = trim((string) ($_POST['address'] ?? ''));

$allowedStars = ['1 Star', '2 Star', '3 Star', '4 Star', '5 Star'];
if (!in_array($starCategory, $allowedStars, true)) {
    hotelJsonResponse(false, 'Please select a valid star category.');
}

if ($destinationId <= 0) {
    hotelJsonResponse(false, 'Please select a valid destination.');
}
if ($cityId <= 0) {
    hotelJsonResponse(false, 'Please select a valid city.');
}
if ($hotelName === '') {
    hotelJsonResponse(false, 'Hotel name is required.');
}

$starRating = max(0, min(5, $starRating));

$destCheck = $conn->prepare('SELECT id FROM destinations WHERE id = ? AND is_active = 1 LIMIT 1');
if ($destCheck) {
    $destCheck->bind_param('i', $destinationId);
    $destCheck->execute();
    $destRes = $destCheck->get_result();
    if (!$destRes || !$destRes->fetch_assoc()) {
        hotelJsonResponse(false, 'Selected destination is invalid.');
    }
    $destCheck->close();
}

require_once __DIR__ . '/../../includes/geo_locations.php';
geoEnsureTables($conn);

$cityCheck = $conn->prepare('SELECT id FROM cities WHERE id = ? LIMIT 1');
if ($cityCheck) {
    $cityCheck->bind_param('i', $cityId);
    $cityCheck->execute();
    $cityRes = $cityCheck->get_result();
    if (!$cityRes || !$cityRes->fetch_assoc()) {
        hotelJsonResponse(false, 'Selected city is invalid.');
    }
    $cityCheck->close();
}

$roomTypes = crmNormalizeHotelRoomTypes($_POST['room_types'] ?? []);
$mealPlans = crmNormalizeHotelMealPlans($_POST['meal_plans'] ?? []);
$roomTypesJson = json_encode($roomTypes, JSON_UNESCAPED_UNICODE);
$mealPlansJson = json_encode($mealPlans, JSON_UNESCAPED_UNICODE);

$existingImage = '';
if ($id > 0) {
    $existStmt = $conn->prepare('SELECT `image_path` FROM `crm_hotels` WHERE `id` = ? LIMIT 1');
    if ($existStmt) {
        $existStmt->bind_param('i', $id);
        $existStmt->execute();
        $existRes = $existStmt->get_result();
        $existRow = $existRes ? $existRes->fetch_assoc() : null;
        $existStmt->close();
        if (!$existRow) {
            hotelJsonResponse(false, 'Hotel not found.');
        }
        $existingImage = (string) ($existRow['image_path'] ?? '');
    }
}

$imagePath = $existingImage;
if (!empty($_FILES['image']['name'])) {
    $file = $_FILES['image'];
    if (!empty($file['error'])) {
        hotelJsonResponse(false, 'Image upload failed.');
    }
    if ($file['size'] > 2 * 1024 * 1024) {
        hotelJsonResponse(false, 'Image must be under 2MB.');
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
    ];
    $mime = '';
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
    } else {
        $mime = (string) ($file['type'] ?? '');
    }
    if (!isset($allowed[$mime])) {
        hotelJsonResponse(false, 'Only JPG, PNG or GIF images are allowed.');
    }

    $uploadDir = __DIR__ . '/../../uploads/crm_hotels/';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0775, true);
    }
    $filename = 'hotel_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
    $target = $uploadDir . $filename;
    if (!move_uploaded_file($file['tmp_name'], $target)) {
        hotelJsonResponse(false, 'Could not save uploaded image.');
    }
    $imagePath = 'uploads/crm_hotels/' . $filename;
}

$createdById = (int) ($_SESSION['id'] ?? 0);
$createdByName = trim((string) ($_SESSION['name'] ?? ''));
$isDefaultInt = $isDefault ? 1 : 0;
$reviewLinkDb = $reviewLink;
$imagePathDb = $imagePath;

if ($id > 0) {
    $stmt = $conn->prepare(
        'UPDATE `crm_hotels` SET
            `destination_id` = ?, `city_id` = ?, `hotel_name` = ?, `is_default` = ?,
            `star_category` = ?, `star_rating` = ?, `review_link` = ?, `address` = ?,
            `image_path` = ?, `room_types_json` = ?, `meal_plans_json` = ?
        WHERE `id` = ? LIMIT 1'
    );
    if (!$stmt) {
        hotelJsonResponse(false, 'Could not prepare update.');
    }
    $stmt->bind_param(
        'iisisdsssssi',
        $destinationId,
        $cityId,
        $hotelName,
        $isDefaultInt,
        $starCategory,
        $starRating,
        $reviewLinkDb,
        $address,
        $imagePathDb,
        $roomTypesJson,
        $mealPlansJson,
        $id
    );
    if (!$stmt->execute()) {
        hotelJsonResponse(false, 'Could not update hotel.');
    }
    $stmt->close();

    if ($isDefault) {
        crmClearDefaultHotelForCity($conn, $cityId, $id);
    }

    $_SESSION['hotel_flash'] = 'Hotel updated successfully.';
    $_SESSION['hotel_flash_type'] = 'success';
    hotelJsonResponse(true, 'Hotel updated successfully.', ['id' => $id]);
}

$stmt = $conn->prepare(
    'INSERT INTO `crm_hotels`
        (`destination_id`, `city_id`, `hotel_name`, `is_default`, `star_category`, `star_rating`,
         `review_link`, `address`, `image_path`, `room_types_json`, `meal_plans_json`,
         `created_by_id`, `created_by_name`)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);
if (!$stmt) {
    hotelJsonResponse(false, 'Could not prepare insert.');
}
$stmt->bind_param(
    'iisisdsssssis',
    $destinationId,
    $cityId,
    $hotelName,
    $isDefaultInt,
    $starCategory,
    $starRating,
    $reviewLinkDb,
    $address,
    $imagePathDb,
    $roomTypesJson,
    $mealPlansJson,
    $createdById,
    $createdByName
);
if (!$stmt->execute()) {
    hotelJsonResponse(false, 'Could not save hotel.');
}
$newId = (int) $conn->insert_id;
$stmt->close();

if ($isDefault) {
    crmClearDefaultHotelForCity($conn, $cityId, $newId);
}

$_SESSION['hotel_flash'] = 'Hotel created successfully.';
$_SESSION['hotel_flash_type'] = 'success';
hotelJsonResponse(true, 'Hotel created successfully.', ['id' => $newId]);
