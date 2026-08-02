<?php

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../admin/connection.php';
require_once __DIR__ . '/../admin/includes/geo_locations.php';

geoEnsureTables($conn);

$res = $conn->query('SELECT id, name FROM countries ORDER BY name ASC');
$rows = [];

if ($res) {
    while ($row = $res->fetch_assoc()) {
        $rows[] = [
            'id' => (int) $row['id'],
            'country_name' => $row['name'],
        ];
    }
}

echo json_encode([
    'success' => count($rows) > 0,
    'data' => $rows,
]);
