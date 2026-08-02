<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/hotel_db.php';

header('Content-Type: application/json; charset=utf-8');

function cityHotelsJson($success, $message, $extra = [])
{
    echo json_encode(array_merge([
        'success' => (bool) $success,
        'message' => (string) $message,
    ], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

crmEnsureHotelTables($conn);

$destinationId = (int) ($_GET['destination_id'] ?? 0);
$cityId = (int) ($_GET['city_id'] ?? 0);

if ($cityId <= 0) {
    cityHotelsJson(false, 'Invalid city.');
}

$sql = "SELECT h.id, h.hotel_name, h.star_category, h.star_rating, h.is_default, h.address, h.review_link
    FROM crm_hotels h
    WHERE h.is_active = 1 AND h.city_id = ?";

if ($destinationId > 0) {
    $sql .= ' AND h.destination_id = ?';
}

$sql .= ' ORDER BY h.is_default DESC, h.hotel_name ASC';

$stmt = $conn->prepare($sql);
if (!$stmt) {
    cityHotelsJson(false, 'Could not load hotels.');
}

if ($destinationId > 0) {
    $stmt->bind_param('ii', $cityId, $destinationId);
} else {
    $stmt->bind_param('i', $cityId);
}
$stmt->execute();
$res = $stmt->get_result();
$hotels = [];
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $hotels[] = [
            'id' => (int) ($row['id'] ?? 0),
            'hotel_name' => (string) ($row['hotel_name'] ?? ''),
            'star_category' => (string) ($row['star_category'] ?? ''),
            'star_rating' => (float) ($row['star_rating'] ?? 0),
            'is_default' => (int) ($row['is_default'] ?? 0),
            'address' => (string) ($row['address'] ?? ''),
            'review_link' => (string) ($row['review_link'] ?? ''),
        ];
    }
}
$stmt->close();

cityHotelsJson(true, 'OK', ['hotels' => $hotels]);
