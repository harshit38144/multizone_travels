<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../includes/geo_locations.php';

header('Content-Type: application/json; charset=utf-8');

geoEnsureTables($conn);

$query = trim($_GET['q'] ?? '');
$destinationId = max(0, (int) ($_GET['destination_id'] ?? 0));
$countryId = max(0, (int) ($_GET['country_id'] ?? 0));
$limit = min(30, max(5, (int) ($_GET['limit'] ?? 20)));

if ($destinationId > 0 && $countryId <= 0) {
    $destStmt = $conn->prepare('SELECT country FROM destinations WHERE id = ? LIMIT 1');
    $destStmt->bind_param('i', $destinationId);
    $destStmt->execute();
    $destRes = $destStmt->get_result();
    $destRow = $destRes ? $destRes->fetch_assoc() : null;
    $destStmt->close();

    $destCountry = trim((string) ($destRow['country'] ?? ''));
    if ($destCountry !== '') {
        $countryStmt = $conn->prepare('SELECT id FROM countries WHERE name = ? AND COALESCE(is_deleted, 0) = 0 LIMIT 1');
        $countryStmt->bind_param('s', $destCountry);
        $countryStmt->execute();
        $countryRes = $countryStmt->get_result();
        $countryRow = $countryRes ? $countryRes->fetch_assoc() : null;
        $countryStmt->close();
        if ($countryRow) {
            $countryId = (int) $countryRow['id'];
        }
    }
}

$sql = 'SELECT ci.id, ci.name, ci.airport_code, co.name AS country_name, st.name AS state_name
        FROM cities ci
        INNER JOIN countries co ON co.id = ci.country_id
        LEFT JOIN states st ON st.id = ci.state_id
        WHERE COALESCE(ci.is_deleted, 0) = 0
          AND COALESCE(co.is_deleted, 0) = 0';
$params = [];
$types = '';

if ($countryId > 0) {
    $sql .= ' AND ci.country_id = ?';
    $types .= 'i';
    $params[] = $countryId;
}

if ($query !== '') {
    $sql .= ' AND ci.name LIKE ?';
    $types .= 's';
    $params[] = '%' . $query . '%';
}

$sql .= ' ORDER BY ci.name ASC LIMIT ' . (int) $limit;

$rows = [];
$stmt = $conn->prepare($sql);
if ($stmt) {
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $rows[] = [
            'id' => (int) $row['id'],
            'name' => $row['name'],
            'country_name' => $row['country_name'],
            'state_name' => $row['state_name'] ?? '',
            'airport_code' => $row['airport_code'] ?? '',
        ];
    }
    $stmt->close();
}

echo json_encode([
    'success' => true,
    'data' => $rows,
]);
