<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../includes/geo_locations.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_errno) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed.', 'data' => []]);
    exit;
}

geoEnsureTables($conn);

$query = trim((string) ($_GET['q'] ?? ''));
$destinationId = max(0, (int) ($_GET['destination_id'] ?? 0));
$countryId = max(0, (int) ($_GET['country_id'] ?? 0));
$limit = min(30, max(5, (int) ($_GET['limit'] ?? 20)));

function citySearchTableExists(mysqli $conn, string $table): bool
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

function citySearchColumnExists(mysqli $conn, string $table, string $column): bool
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

if ($destinationId > 0 && $countryId <= 0 && citySearchTableExists($conn, 'destinations')) {
    $destStmt = $conn->prepare('SELECT country FROM destinations WHERE id = ? LIMIT 1');
    if ($destStmt) {
        $destStmt->bind_param('i', $destinationId);
        $destStmt->execute();
        $destRes = $destStmt->get_result();
        $destRow = $destRes ? $destRes->fetch_assoc() : null;
        $destStmt->close();

        $destCountry = trim((string) ($destRow['country'] ?? ''));
        if ($destCountry !== '' && citySearchTableExists($conn, 'countries')) {
            $countryStmt = $conn->prepare('SELECT id FROM countries WHERE name = ? LIMIT 1');
            if ($countryStmt) {
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
    }
}

if (!citySearchTableExists($conn, 'cities') || !citySearchTableExists($conn, 'countries')) {
    echo json_encode(['success' => true, 'data' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

$hasStates = citySearchTableExists($conn, 'states')
    && citySearchColumnExists($conn, 'cities', 'state_id');
$hasIsDeleted = citySearchColumnExists($conn, 'cities', 'is_deleted');

$sql = 'SELECT ci.id, ci.name, co.name AS country_name';
$sql .= $hasStates ? ', st.name AS state_name' : ", '' AS state_name";
$sql .= citySearchColumnExists($conn, 'cities', 'airport_code')
    ? ', ci.airport_code'
    : ", '' AS airport_code";
$sql .= ' FROM cities ci INNER JOIN countries co ON co.id = ci.country_id';
if ($hasStates) {
    $sql .= ' LEFT JOIN states st ON st.id = ci.state_id';
}

$where = ['1=1'];
if ($hasIsDeleted) {
    $where[] = 'COALESCE(ci.is_deleted, 0) = 0';
}

$params = [];
$types = '';

if ($countryId > 0) {
    $where[] = 'ci.country_id = ?';
    $types .= 'i';
    $params[] = $countryId;
}

if ($query !== '') {
    $where[] = 'ci.name LIKE ?';
    $types .= 's';
    $params[] = '%' . $query . '%';
}

$sql .= ' WHERE ' . implode(' AND ', $where);

if ($query !== '') {
    $prefix = $query . '%';
    $sql .= ' ORDER BY
        (LOWER(ci.name) = LOWER(?)) DESC,
        (ci.name LIKE ?) DESC,
        (co.name = ?) DESC,
        CHAR_LENGTH(ci.name) ASC,
        ci.name ASC';
    $types .= 'sss';
    $params[] = $query;
    $params[] = $prefix;
    $params[] = 'India';
} else {
    $sql .= ' ORDER BY ci.name ASC';
}

$sql .= ' LIMIT ' . (int) $limit;

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
            'id' => (int) ($row['id'] ?? 0),
            'name' => (string) ($row['name'] ?? ''),
            'country_name' => (string) ($row['country_name'] ?? ''),
            'state_name' => (string) ($row['state_name'] ?? ''),
            'airport_code' => (string) ($row['airport_code'] ?? ''),
        ];
    }
    $stmt->close();
}

echo json_encode([
    'success' => true,
    'data' => $rows,
], JSON_UNESCAPED_UNICODE);
