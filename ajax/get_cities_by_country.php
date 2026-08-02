<?php

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../admin/connection.php';
require_once __DIR__ . '/../admin/includes/geo_locations.php';

geoEnsureTables($conn);

$countryId = isset($_GET['country_id']) ? (int) $_GET['country_id'] : 0;
$stateId = isset($_GET['state_id']) ? (int) $_GET['state_id'] : 0;

$rows = [];

if ($countryId > 0) {
    if ($stateId > 0) {
        $stmt = $conn->prepare(
            'SELECT id, name, airport_code FROM cities
             WHERE country_id = ? AND state_id = ?
             ORDER BY name ASC'
        );
        $stmt->bind_param('ii', $countryId, $stateId);
    } else {
        $stmt = $conn->prepare(
            'SELECT id, name, airport_code FROM cities
             WHERE country_id = ?
             ORDER BY name ASC'
        );
        $stmt->bind_param('i', $countryId);
    }

    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $rows[] = [
            'id' => (int) $row['id'],
            'city_name' => $row['name'],
            'airport_code' => $row['airport_code'] ?? '',
        ];
    }
    $stmt->close();
}

echo json_encode([
    'success' => count($rows) > 0,
    'data' => $rows,
]);
