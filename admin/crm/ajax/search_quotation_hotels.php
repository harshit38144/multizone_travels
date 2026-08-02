<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/hotel_db.php';

header('Content-Type: application/json; charset=utf-8');

function qHotelsJson($success, $message, $extra = [])
{
    echo json_encode(array_merge([
        'success' => (bool) $success,
        'message' => (string) $message,
    ], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Resolve destination filter from destination_id and/or destination name.
 */
function qHotelsResolveDestinationId(mysqli $conn, int $destinationId, string $destinationName): int
{
    if ($destinationId > 0) {
        return $destinationId;
    }
    $destinationName = trim($destinationName);
    if ($destinationName === '') {
        return 0;
    }

    $stmt = $conn->prepare('SELECT id FROM destinations WHERE is_active = 1 AND LOWER(TRIM(name)) = LOWER(?) LIMIT 1');
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param('s', $destinationName);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    return (int) ($row['id'] ?? 0);
}

crmEnsureHotelTables($conn);

$mode = trim((string) ($_GET['mode'] ?? 'cities'));
$query = trim((string) ($_GET['q'] ?? ''));
$cityId = (int) ($_GET['city_id'] ?? 0);
$limit = min(40, max(5, (int) ($_GET['limit'] ?? 25)));
$destinationId = qHotelsResolveDestinationId(
    $conn,
    (int) ($_GET['destination_id'] ?? 0),
    (string) ($_GET['destination'] ?? '')
);

if ($mode === 'search') {
    $cityFilter = (int) ($_GET['city_id'] ?? 0);

    $sql = "SELECT h.id, h.city_id, h.destination_id, h.hotel_name, h.star_category, h.is_default,
            h.room_types_json, h.meal_plans_json, c.name AS city_name, d.name AS destination_name
        FROM crm_hotels h
        INNER JOIN cities c ON c.id = h.city_id
        LEFT JOIN destinations d ON d.id = h.destination_id
        WHERE h.is_active = 1";
    $params = [];
    $types = '';

    if ($destinationId > 0) {
        $sql .= ' AND h.destination_id = ?';
        $types .= 'i';
        $params[] = $destinationId;
    }
    if ($cityFilter > 0) {
        $sql .= ' AND h.city_id = ?';
        $types .= 'i';
        $params[] = $cityFilter;
    }
    if ($query !== '') {
        $sql .= ' AND (h.hotel_name LIKE ? OR c.name LIKE ?)';
        $types .= 'ss';
        $like = '%' . $query . '%';
        $params[] = $like;
        $params[] = $like;
    }
    $sql .= ' ORDER BY h.is_default DESC, h.hotel_name ASC, c.name ASC LIMIT ' . (int) $limit;

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        qHotelsJson(false, 'Could not search hotels.');
    }
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $hotels = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $roomTypes = json_decode($row['room_types_json'] ?? '[]', true);
            $mealPlans = json_decode($row['meal_plans_json'] ?? '[]', true);
            $hotels[] = [
                'id' => (int) ($row['id'] ?? 0),
                'city_id' => (int) ($row['city_id'] ?? 0),
                'destination_id' => (int) ($row['destination_id'] ?? 0),
                'hotel_name' => (string) ($row['hotel_name'] ?? ''),
                'city_name' => (string) ($row['city_name'] ?? ''),
                'destination_name' => (string) ($row['destination_name'] ?? ''),
                'star_category' => (string) ($row['star_category'] ?? ''),
                'is_default' => (int) ($row['is_default'] ?? 0),
                'room_types' => is_array($roomTypes) ? $roomTypes : [],
                'meal_plans' => is_array($mealPlans) ? $mealPlans : [],
            ];
        }
    }
    $stmt->close();

    qHotelsJson(true, 'OK', [
        'hotels' => $hotels,
        'destination_id' => $destinationId,
    ]);
}

if ($mode === 'hotels') {
    if ($cityId <= 0) {
        qHotelsJson(false, 'City is required.');
    }

    $sql = "SELECT h.id, h.city_id, h.destination_id, h.hotel_name, h.star_category, h.is_default,
            h.room_types_json, h.meal_plans_json, c.name AS city_name, d.name AS destination_name
        FROM crm_hotels h
        INNER JOIN cities c ON c.id = h.city_id
        LEFT JOIN destinations d ON d.id = h.destination_id
        WHERE h.is_active = 1 AND h.city_id = ?";
    $params = [$cityId];
    $types = 'i';

    if ($destinationId > 0) {
        $sql .= ' AND h.destination_id = ?';
        $types .= 'i';
        $params[] = $destinationId;
    }
    if ($query !== '') {
        $sql .= ' AND h.hotel_name LIKE ?';
        $types .= 's';
        $params[] = '%' . $query . '%';
    }
    $sql .= ' ORDER BY h.is_default DESC, h.hotel_name ASC LIMIT ' . (int) $limit;

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        qHotelsJson(false, 'Could not load hotels.');
    }

    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    $hotels = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $roomTypes = json_decode($row['room_types_json'] ?? '[]', true);
            $mealPlans = json_decode($row['meal_plans_json'] ?? '[]', true);
            $hotels[] = [
                'id' => (int) ($row['id'] ?? 0),
                'city_id' => (int) ($row['city_id'] ?? 0),
                'destination_id' => (int) ($row['destination_id'] ?? 0),
                'hotel_name' => (string) ($row['hotel_name'] ?? ''),
                'city_name' => (string) ($row['city_name'] ?? ''),
                'destination_name' => (string) ($row['destination_name'] ?? ''),
                'star_category' => (string) ($row['star_category'] ?? ''),
                'is_default' => (int) ($row['is_default'] ?? 0),
                'room_types' => is_array($roomTypes) ? $roomTypes : [],
                'meal_plans' => is_array($mealPlans) ? $mealPlans : [],
            ];
        }
    }
    $stmt->close();

    qHotelsJson(true, 'OK', [
        'hotels' => $hotels,
        'destination_id' => $destinationId,
    ]);
}

$sql = "SELECT DISTINCT c.id, c.name
    FROM crm_hotels h
    INNER JOIN cities c ON c.id = h.city_id
    WHERE h.is_active = 1";
$params = [];
$types = '';

if ($destinationId > 0) {
    $sql .= ' AND h.destination_id = ?';
    $types .= 'i';
    $params[] = $destinationId;
}

if ($query !== '') {
    $sql .= ' AND c.name LIKE ?';
    $types .= 's';
    $params[] = '%' . $query . '%';
}

$sql .= ' ORDER BY c.name ASC LIMIT ' . (int) $limit;

$stmt = $conn->prepare($sql);
if (!$stmt) {
    qHotelsJson(false, 'Could not load cities.');
}

if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$res = $stmt->get_result();
$cities = [];
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $cities[] = [
            'id' => (int) ($row['id'] ?? 0),
            'name' => (string) ($row['name'] ?? ''),
        ];
    }
}
$stmt->close();

qHotelsJson(true, 'OK', [
    'cities' => $cities,
    'destination_id' => $destinationId,
]);
