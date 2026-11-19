<?php
header('Content-Type: application/json; charset=utf-8');

$q = trim((string) ($_GET['q'] ?? ''));

if ($q === '') {
    echo json_encode(['s' => true, 'err' => '', 'r' => []]);
    exit;
}

/**
 * Emit MMT-shaped response and exit.
 */
function mmtSuggestRespond(array $rows): void
{
    $out = [];
    $seen = [];
    foreach ($rows as $row) {
        $iata = strtoupper(trim((string) ($row['iata'] ?? '')));
        $city = trim((string) ($row['ct'] ?? $row['city'] ?? ''));
        $name = trim((string) ($row['n'] ?? $row['name'] ?? ''));
        $country = trim((string) ($row['cnty'] ?? $row['country'] ?? ''));
        if ($iata === '' || ($city === '' && $name === '')) {
            continue;
        }
        if ($city === '') {
            $city = $name;
        }
        $key = $iata . '|' . strtolower($city);
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $out[] = [
            'iata' => $iata,
            'n' => $name !== '' ? $name : $city,
            'ct' => $city,
            'cnty' => $country !== '' ? $country : 'India',
            't' => 'airport',
        ];
        if (count($out) >= 12) {
            break;
        }
    }

    echo json_encode(['s' => true, 'err' => '', 'r' => $out], JSON_UNESCAPED_UNICODE);
    exit;
}

function mmtSuggestTableExists(mysqli $conn, string $table): bool
{
    $safe = $conn->real_escape_string($table);
    $res = @$conn->query("SHOW TABLES LIKE '{$safe}'");
    return $res && $res->num_rows > 0;
}

function mmtSuggestColumnExists(mysqli $conn, string $table, string $column): bool
{
    $safeTable = str_replace('`', '', $table);
    $safeCol = $conn->real_escape_string($column);
    $res = @$conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeCol}'");
    return $res && $res->num_rows > 0;
}

function mmtSuggestFromJsonFile(string $q): array
{
    $file = __DIR__ . '/data/airports_fallback.json';
    if (!is_file($file)) {
        return [];
    }
    $raw = @file_get_contents($file);
    $list = json_decode((string) $raw, true);
    if (!is_array($list)) {
        return [];
    }

    $needle = strtolower($q);
    $exact = [];
    $starts = [];
    $contains = [];

    foreach ($list as $row) {
        if (!is_array($row)) {
            continue;
        }
        $iata = strtoupper(trim((string) ($row['iata'] ?? '')));
        $city = trim((string) ($row['ct'] ?? ''));
        $name = trim((string) ($row['n'] ?? ''));
        $country = trim((string) ($row['cnty'] ?? ''));
        $hay = strtolower($iata . ' ' . $city . ' ' . $name . ' ' . $country);
        if ($hay === '' || strpos($hay, $needle) === false) {
            continue;
        }
        $item = [
            'iata' => $iata,
            'n' => $name,
            'ct' => $city,
            'cnty' => $country,
        ];
        if (strtolower($iata) === $needle || strtolower($city) === $needle) {
            $exact[] = $item;
        } elseif (strpos(strtolower($city), $needle) === 0 || strpos(strtolower($iata), $needle) === 0) {
            $starts[] = $item;
        } else {
            $contains[] = $item;
        }
    }

    return array_merge($exact, $starts, $contains);
}

function mmtSuggestFromDb(string $q): array
{
    try {
        include_once __DIR__ . '/../connection.php';
        if (!isset($conn) || !($conn instanceof mysqli)) {
            return [];
        }

        $rows = [];
        $like = '%' . $q . '%';

        // City master with airport codes
        if (mmtSuggestTableExists($conn, 'cities') && mmtSuggestColumnExists($conn, 'cities', 'airport_code')) {
            $hasDeleted = mmtSuggestColumnExists($conn, 'cities', 'is_deleted');
            $sql = "SELECT name, airport_code
                    FROM cities
                    WHERE TRIM(COALESCE(airport_code, '')) <> ''
                      AND (name LIKE ? OR airport_code LIKE ?)";
            if ($hasDeleted) {
                $sql .= ' AND COALESCE(is_deleted, 0) = 0';
            }
            $sql .= ' ORDER BY
                        (UPPER(airport_code) = UPPER(?)) DESC,
                        (LOWER(name) = LOWER(?)) DESC,
                        CHAR_LENGTH(name) ASC
                     LIMIT 12';
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param('ssss', $like, $like, $q, $q);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($row = $res->fetch_assoc()) {
                    $rows[] = [
                        'iata' => (string) ($row['airport_code'] ?? ''),
                        'n' => (string) ($row['name'] ?? ''),
                        'ct' => (string) ($row['name'] ?? ''),
                        'cnty' => 'India',
                    ];
                }
                $stmt->close();
            }
        }

        if (count($rows) >= 8) {
            return $rows;
        }

        // Optional airports table
        if (mmtSuggestTableExists($conn, 'airports')) {
            $stmt = $conn->prepare(
                "SELECT name, city, country, iata
                 FROM airports
                 WHERE iata LIKE ?
                    OR city LIKE ?
                    OR name LIKE ?
                 ORDER BY
                    (UPPER(iata) = UPPER(?)) DESC,
                    (LOWER(city) = LOWER(?)) DESC,
                    CHAR_LENGTH(city) ASC
                 LIMIT 12"
            );
            if ($stmt) {
                $stmt->bind_param('sssss', $like, $like, $like, $q, $q);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($row = $res->fetch_assoc()) {
                    $rows[] = [
                        'iata' => (string) ($row['iata'] ?? ''),
                        'n' => (string) ($row['name'] ?? ''),
                        'ct' => (string) ($row['city'] ?? ''),
                        'cnty' => (string) ($row['country'] ?? 'India'),
                    ];
                }
                $stmt->close();
            }
        }

        return $rows;
    } catch (Throwable $e) {
        return [];
    }
}

// 1) Prefer MakeMyTrip when the host can reach it (works on many local networks)
$url = 'https://flights-explorer.makemytrip.com/autosuggest?limit=10&query=' . urlencode($q);
$headers = [
    'Accept: */*',
    'Accept-Language: en-US,en;q=0.9',
    'Cache-Control: no-cache',
    'Origin: https://www.goibibo.com',
    'Referer: https://www.goibibo.com/',
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
];

$httpCode = 0;
$response = false;

if (function_exists('curl_init')) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_ENCODING, '');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
    curl_setopt($ch, CURLOPT_TIMEOUT, 4);
    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
} else {
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => implode("\r\n", $headers) . "\r\n",
            'timeout' => 4,
            'ignore_errors' => true,
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
        ],
    ]);
    $response = @file_get_contents($url, false, $context);
    if (!empty($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $match)) {
        $httpCode = (int) $match[1];
    }
}

if ($httpCode === 200 && $response !== false && $response !== '') {
    $decoded = json_decode($response, true);
    if (is_array($decoded) && !empty($decoded['r'])) {
        echo $response;
        exit;
    }
}

// 2) DB fallback (cities / airports)
$dbRows = mmtSuggestFromDb($q);
if ($dbRows) {
    mmtSuggestRespond($dbRows);
}

// 3) Bundled JSON fallback — works even when MMT is blocked on the server
$jsonRows = mmtSuggestFromJsonFile($q);
if ($jsonRows) {
    mmtSuggestRespond($jsonRows);
}

echo json_encode(['s' => true, 'err' => '', 'r' => []], JSON_UNESCAPED_UNICODE);
