<?php
require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

function leadDestCreateSlug($str, mysqli $conn, $id = 0)
{
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $str)));
    $slug = preg_replace('/-+/', '-', $slug);
    $slug = trim($slug, '-');

    if ($slug === '') {
        $slug = 'destination';
    }

    $id = (int) $id;
    $sql = "SELECT id FROM destinations WHERE slug = '" . mysqli_real_escape_string($conn, $slug) . "' AND id != $id";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        $slug .= '-' . time();
    }

    return $slug;
}

function leadDestJsonResponse($success, $message, $extra = [])
{
    echo json_encode(array_merge([
        'success' => (bool) $success,
        'message' => $message,
    ], $extra));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    leadDestJsonResponse(false, 'Invalid request method.');
}

$checkTourType = $conn->query("SHOW COLUMNS FROM `destinations` LIKE 'tour_type'");
if ($checkTourType && $checkTourType->num_rows == 0) {
    $conn->query("ALTER TABLE `destinations` ADD `tour_type` VARCHAR(20) DEFAULT NULL AFTER `region`");
}

$name = trim($_POST['name'] ?? '');
if ($name === '') {
    leadDestJsonResponse(false, 'Destination name is required.');
}

$tour_type = mysqli_real_escape_string($conn, $_POST['tour_type'] ?? '');
if (!in_array($tour_type, ['domestic', 'international'], true)) {
    leadDestJsonResponse(false, 'Please select a valid tour type.');
}

$country = mysqli_real_escape_string($conn, trim($_POST['country'] ?? ''));
$region = mysqli_real_escape_string($conn, trim($_POST['region'] ?? ''));
$description = mysqli_real_escape_string($conn, trim($_POST['description'] ?? ''));
$best_time_to_visit = mysqli_real_escape_string($conn, trim($_POST['best_time_to_visit'] ?? ''));
$how_to_reach = mysqli_real_escape_string($conn, trim($_POST['how_to_reach'] ?? ''));
$display_order = max(1, (int) ($_POST['display_order'] ?? 1));
$is_active = isset($_POST['is_active']) && $_POST['is_active'] === '0' ? 0 : 1;
$slugInput = trim($_POST['slug'] ?? '');

$nameEsc = mysqli_real_escape_string($conn, $name);
$slug = $slugInput !== '' ? leadDestCreateSlug($slugInput, $conn) : leadDestCreateSlug($name, $conn);
$slugEsc = mysqli_real_escape_string($conn, $slug);

$uploadDir = __DIR__ . '/../../uploads/destinations/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

$image = '';
if (!empty($_FILES['image']['name'])) {
    $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    if (!in_array($ext, $allowed, true)) {
        leadDestJsonResponse(false, 'Invalid image format. Allowed: JPG, PNG, WEBP, GIF.');
    }

    $filename = 'dest_' . uniqid() . '.' . $ext;
    if (!move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $filename)) {
        leadDestJsonResponse(false, 'Could not upload destination image.');
    }

    $image = 'uploads/destinations/' . $filename;
}

$imageEsc = mysqli_real_escape_string($conn, $image);

$sql = "INSERT INTO destinations (name, slug, country, region, tour_type, description, best_time_to_visit, how_to_reach, image, display_order, is_active)
        VALUES ('$nameEsc', '$slugEsc', '$country', '$region', '$tour_type', '$description', '$best_time_to_visit', '$how_to_reach', '$imageEsc', $display_order, $is_active)";

if (!$conn->query($sql)) {
    leadDestJsonResponse(false, 'Could not save destination. ' . $conn->error);
}

$newId = (int) $conn->insert_id;

leadDestJsonResponse(true, 'Destination created successfully.', [
    'destination' => [
        'id' => $newId,
        'name' => $name,
        'tour_type' => $tour_type,
    ],
]);
