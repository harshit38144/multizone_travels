<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/hotel_db.php';

header('Content-Type: application/json; charset=utf-8');

function hotelGetJson($success, $message, $extra = [])
{
    echo json_encode(array_merge([
        'success' => (bool) $success,
        'message' => (string) $message,
    ], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

crmEnsureHotelTables($conn);

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    hotelGetJson(false, 'Invalid hotel.');
}

$sql = "SELECT h.id, h.destination_id, h.city_id, h.hotel_name, h.is_default,
        h.star_category, h.star_rating, h.review_link, h.address, h.image_path,
        h.room_types_json, h.meal_plans_json,
        d.name AS destination_name, c.name AS city_name
    FROM crm_hotels h
    LEFT JOIN destinations d ON d.id = h.destination_id
    LEFT JOIN cities c ON c.id = h.city_id
    WHERE h.id = ? AND h.is_active = 1
    LIMIT 1";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    hotelGetJson(false, 'Could not load hotel.');
}
$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result();
$row = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$row) {
    hotelGetJson(false, 'Hotel not found.');
}

$roomTypes = json_decode($row['room_types_json'] ?? '[]', true);
$mealPlans = json_decode($row['meal_plans_json'] ?? '[]', true);

hotelGetJson(true, 'OK', [
    'hotel' => [
        'id' => (int) ($row['id'] ?? 0),
        'destination_id' => (int) ($row['destination_id'] ?? 0),
        'destination_name' => (string) ($row['destination_name'] ?? ''),
        'city_id' => (int) ($row['city_id'] ?? 0),
        'city_name' => (string) ($row['city_name'] ?? ''),
        'hotel_name' => (string) ($row['hotel_name'] ?? ''),
        'is_default' => (int) ($row['is_default'] ?? 0),
        'star_category' => (string) ($row['star_category'] ?? '3 Star'),
        'star_rating' => (float) ($row['star_rating'] ?? 0),
        'review_link' => (string) ($row['review_link'] ?? ''),
        'address' => (string) ($row['address'] ?? ''),
        'image_path' => (string) ($row['image_path'] ?? ''),
        'room_types' => is_array($roomTypes) ? $roomTypes : [],
        'meal_plans' => is_array($mealPlans) ? $mealPlans : [],
    ],
]);
