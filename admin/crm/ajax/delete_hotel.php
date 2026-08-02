<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/hotel_db.php';

header('Content-Type: application/json; charset=utf-8');

function hotelDeleteJson($success, $message, $extra = [])
{
    echo json_encode(array_merge([
        'success' => (bool) $success,
        'message' => (string) $message,
    ], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    hotelDeleteJson(false, 'Invalid request method.');
}

crmEnsureHotelTables($conn);

$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    hotelDeleteJson(false, 'Invalid hotel.');
}

$check = $conn->prepare('SELECT id, hotel_name FROM crm_hotels WHERE id = ? AND is_active = 1 LIMIT 1');
if (!$check) {
    hotelDeleteJson(false, 'Could not find hotel.');
}
$check->bind_param('i', $id);
$check->execute();
$res = $check->get_result();
$row = $res ? $res->fetch_assoc() : null;
$check->close();

if (!$row) {
    hotelDeleteJson(false, 'Hotel not found or already deleted.');
}

$stmt = $conn->prepare('UPDATE crm_hotels SET is_active = 0, is_default = 0 WHERE id = ? LIMIT 1');
if (!$stmt) {
    hotelDeleteJson(false, 'Could not delete hotel.');
}
$stmt->bind_param('i', $id);
if (!$stmt->execute()) {
    $stmt->close();
    hotelDeleteJson(false, 'Could not delete hotel.');
}
$stmt->close();

$_SESSION['hotel_flash'] = 'Hotel deleted successfully.';
$_SESSION['hotel_flash_type'] = 'success';

hotelDeleteJson(true, 'Hotel deleted successfully.', [
    'id' => $id,
    'hotel_name' => (string) ($row['hotel_name'] ?? ''),
]);
