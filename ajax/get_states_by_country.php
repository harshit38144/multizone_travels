<?php

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../admin/connection.php';
require_once __DIR__ . '/../admin/includes/geo_locations.php';

geoEnsureTables($conn);

$countryId = isset($_GET['country_id']) ? (int) $_GET['country_id'] : 0;
$rows = [];

if ($countryId > 0) {
    $stmt = $conn->prepare(
        'SELECT id, name, state_code FROM states WHERE country_id = ? ORDER BY name ASC'
    );
    $stmt->bind_param('i', $countryId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $rows[] = [
            'id' => (int) $row['id'],
            'state_name' => $row['name'],
            'state_code' => $row['state_code'] ?? '',
        ];
    }
    $stmt->close();
}

echo json_encode([
    'success' => count($rows) > 0,
    'data' => $rows,
]);
