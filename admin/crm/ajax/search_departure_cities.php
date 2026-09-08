<?php
/**
 * Departure city / airport suggestions for Create Lead.
 * Returns items shaped as: { city, code, label } → "Mumbai, BOM"
 */
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../includes/geo_locations.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_errno) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed.', 'data' => []]);
    exit;
}

geoEnsureTables($conn);

$query = trim((string) ($_GET['q'] ?? ''));
$limit = min(25, max(5, (int) ($_GET['limit'] ?? 15)));

$popular = [
    ['city' => 'Bengaluru', 'code' => 'BLR'],
    ['city' => 'Chennai', 'code' => 'MAA'],
    ['city' => 'New Delhi', 'code' => 'DEL'],
    ['city' => 'Mumbai', 'code' => 'BOM'],
    ['city' => 'Hyderabad', 'code' => 'HYD'],
    ['city' => 'Kolkata', 'code' => 'CCU'],
    ['city' => 'Pune', 'code' => 'PNQ'],
    ['city' => 'Ahmedabad', 'code' => 'AMD'],
    ['city' => 'Goa', 'code' => 'GOI'],
    ['city' => 'Jaipur', 'code' => 'JAI'],
    ['city' => 'Kochi', 'code' => 'COK'],
    ['city' => 'Lucknow', 'code' => 'LKO'],
];

function depTableExists(mysqli $conn, string $table): bool
{
    static $cache = [];
    if (isset($cache[$table])) {
        return $cache[$table];
    }
    $safe = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES LIKE '{$safe}'");
    $cache[$table] = $res && $res->num_rows > 0;

    return $cache[$table];
}

function depColumnExists(mysqli $conn, string $table, string $column): bool
{
    static $cache = [];
    $key = $table . '.' . $column;
    if (isset($cache[$key])) {
        return $cache[$key];
    }
    $safeTable = str_replace('`', '', $table);
    $safeCol = $conn->real_escape_string($column);
    $res = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeCol}'");
    $cache[$key] = $res && $res->num_rows > 0;

    return $cache[$key];
}

function depNormalizeRow(string $city, string $code): ?array
{
    $city = trim($city);
    $code = strtoupper(trim($code));
    if ($city === '') {
        return null;
    }
    $label = $code !== '' ? ($city . ', ' . $code) : $city;

    return [
        'city' => $city,
        'code' => $code,
        'label' => $label,
    ];
}

function depPushUnique(array &$out, array &$seen, ?array $row, int $limit): void
{
    if ($row === null || count($out) >= $limit) {
        return;
    }
    $key = strtolower($row['city'] . '|' . $row['code']);
    if (isset($seen[$key])) {
        return;
    }
    $seen[$key] = true;
    $out[] = $row;
}

$rows = [];
$seen = [];

// 1) City Master (prefer entries with airport codes)
if (depTableExists($conn, 'cities') && depColumnExists($conn, 'cities', 'airport_code')) {
    $hasDeleted = depColumnExists($conn, 'cities', 'is_deleted');
    $sql = "SELECT ci.name, ci.airport_code
            FROM cities ci
            WHERE TRIM(COALESCE(ci.airport_code, '')) <> ''";
    if ($hasDeleted) {
        $sql .= ' AND COALESCE(ci.is_deleted, 0) = 0';
    }

    $params = [];
    $types = '';
    if ($query !== '') {
        $sql .= ' AND (ci.name LIKE ? OR ci.airport_code LIKE ?)';
        $types = 'ss';
        $like = '%' . $query . '%';
        $params[] = $like;
        $params[] = $like;
        $sql .= ' ORDER BY
            (LOWER(ci.name) = LOWER(?)) DESC,
            (UPPER(ci.airport_code) = UPPER(?)) DESC,
            (ci.name LIKE ?) DESC,
            CHAR_LENGTH(ci.name) ASC,
            ci.name ASC';
        $types .= 'sss';
        $params[] = $query;
        $params[] = $query;
        $params[] = $query . '%';
    } else {
        $sql .= ' ORDER BY ci.name ASC';
    }
    $sql .= ' LIMIT ' . (int) $limit;

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            depPushUnique(
                $rows,
                $seen,
                depNormalizeRow((string) ($row['name'] ?? ''), (string) ($row['airport_code'] ?? '')),
                $limit
            );
        }
        $stmt->close();
    }
}

// 2) Airports table fallback / supplement
if (count($rows) < $limit && depTableExists($conn, 'airports')) {
    $sql = 'SELECT city, iata, name, country FROM airports WHERE 1=1';
    $params = [];
    $types = '';
    if ($query !== '') {
        $sql .= ' AND (city LIKE ? OR iata LIKE ? OR name LIKE ?)';
        $types = 'sss';
        $like = '%' . $query . '%';
        $params = [$like, $like, $like];
        $sql .= ' ORDER BY
            (UPPER(iata) = UPPER(?)) DESC,
            (LOWER(city) = LOWER(?)) DESC,
            CHAR_LENGTH(city) ASC,
            city ASC';
        $types .= 'ss';
        $params[] = $query;
        $params[] = $query;
    } else {
        $sql .= " AND (country LIKE '%India%' OR country = 'IN' OR country = 'India')";
        $sql .= ' ORDER BY city ASC';
    }
    $sql .= ' LIMIT ' . (int) $limit;

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $city = trim((string) ($row['city'] ?? ''));
            if ($city === '') {
                $city = trim((string) ($row['name'] ?? ''));
            }
            depPushUnique(
                $rows,
                $seen,
                depNormalizeRow($city, (string) ($row['iata'] ?? '')),
                $limit
            );
        }
        $stmt->close();
    }
}

// 3) Popular India list (empty query or sparse DB)
if ($query === '') {
    foreach ($popular as $item) {
        depPushUnique($rows, $seen, depNormalizeRow($item['city'], $item['code']), $limit);
    }
} else {
    $qLower = strtolower($query);
    foreach ($popular as $item) {
        if (
            strpos(strtolower($item['city']), $qLower) !== false
            || strpos(strtolower($item['code']), $qLower) !== false
        ) {
            depPushUnique($rows, $seen, depNormalizeRow($item['city'], $item['code']), $limit);
        }
    }
}

echo json_encode([
    'success' => true,
    'data' => array_values($rows),
], JSON_UNESCAPED_UNICODE);
