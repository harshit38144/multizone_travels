<?php
require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$query = trim($_GET['q'] ?? '');
$limit = min(30, max(5, (int) ($_GET['limit'] ?? 20)));

$sql = 'SELECT id, name, country FROM destinations WHERE is_active = 1';
$params = [];
$types = '';

if ($query !== '') {
    $sql .= ' AND (name LIKE ? OR country LIKE ?)';
    $types .= 'ss';
    $like = '%' . $query . '%';
    $params[] = $like;
    $params[] = $like;
}

$sql .= ' ORDER BY display_order ASC, name ASC LIMIT ' . (int) $limit;

$rows = [];
$stmt = $conn->prepare($sql);
if ($stmt) {
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $name = trim((string) ($row['name'] ?? ''));
        $country = trim((string) ($row['country'] ?? ''));
        $label = $name;
        if ($country !== '') {
            $label = $name . ' - ' . $country;
        }
        $rows[] = [
            'id' => (int) $row['id'],
            'name' => $name,
            'country' => $country,
            'label' => $label,
        ];
    }
    $stmt->close();
}

echo json_encode([
    'success' => true,
    'data' => $rows,
]);
