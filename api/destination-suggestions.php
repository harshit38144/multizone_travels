<?php
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once dirname(__DIR__) . '/admin/connection.php';

$items = [];

if (!$conn || $conn->connect_errno) {
    echo json_encode(['items' => []]);
    exit;
}

$tableCheck = @$conn->query("SHOW TABLES LIKE 'destinations'");
if (!$tableCheck || $tableCheck->num_rows === 0) {
    echo json_encode(['items' => []]);
    exit;
}

$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
if (function_exists('mb_substr')) {
    $q = mb_substr($q, 0, 100);
} else {
    $q = substr($q, 0, 100);
}

$limit = 24;

$rowToItem = static function (array $row): array {
    $meta = [];
    if (!empty(trim((string)($row['region'] ?? '')))) {
        $meta[] = trim($row['region']);
    }
    if (!empty(trim((string)($row['country'] ?? '')))) {
        $meta[] = trim($row['country']);
    }
    $slug = (string)($row['slug'] ?? '');
    return [
        'name' => (string)($row['name'] ?? ''),
        'slug' => $slug,
        'meta' => implode(' · ', $meta),
        'url'  => $slug !== '' ? ('b5.php?slug=' . rawurlencode($slug)) : '',
    ];
};

if ($q === '') {
    $sql = "SELECT name, slug, country, region FROM destinations WHERE is_active = 1 ORDER BY display_order ASC, name ASC LIMIT ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $items[] = $rowToItem($row);
        }
        $stmt->close();
    }
} else {
    $pat = '%' . $q . '%';
    $sql = "SELECT name, slug, country, region FROM destinations WHERE is_active = 1
        AND (name LIKE ? OR IFNULL(country,'') LIKE ? OR IFNULL(region,'') LIKE ? OR slug LIKE ?)
        ORDER BY (name LIKE ?) DESC, name ASC LIMIT ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $prefix = $q . '%';
        $stmt->bind_param('sssssi', $pat, $pat, $pat, $pat, $prefix, $limit);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $items[] = $rowToItem($row);
        }
        $stmt->close();
    }
}

echo json_encode(['items' => $items], JSON_UNESCAPED_UNICODE);
