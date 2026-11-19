<?php
header('Content-Type: application/json');

$q = trim((string) ($_GET['q'] ?? ''));

if ($q === '') {
    echo json_encode([]);
    exit;
}

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
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
} else {
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => implode("\r\n", $headers) . "\r\n",
            'timeout' => 20,
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
    if (is_array($decoded) && (isset($decoded['s']) || isset($decoded['r']))) {
        echo $response;
        exit;
    }
}

// Fallback: local airports table when MakeMyTrip autosuggest is unavailable
try {
    include_once __DIR__ . '/../connection.php';
    if (isset($conn) && $conn instanceof mysqli) {
        $check = @$conn->query("SHOW TABLES LIKE 'airports'");
        if ($check && $check->num_rows > 0) {
            $stmt = $conn->prepare(
                "SELECT name, city, country, iata
                 FROM airports
                 WHERE iata LIKE CONCAT('%', ?, '%')
                    OR city LIKE CONCAT('%', ?, '%')
                    OR name LIKE CONCAT('%', ?, '%')
                 LIMIT 10"
            );
            if ($stmt) {
                $stmt->bind_param('sss', $q, $q, $q);
                $stmt->execute();
                $result = $stmt->get_result();
                $rows = [];
                while ($row = $result->fetch_assoc()) {
                    $rows[] = [
                        'iata' => (string) ($row['iata'] ?? ''),
                        'n' => (string) ($row['name'] ?? ''),
                        'ct' => (string) ($row['city'] ?? ''),
                        'cnty' => (string) ($row['country'] ?? ''),
                        't' => 'airport',
                    ];
                }
                $stmt->close();
                if ($rows) {
                    echo json_encode(['s' => true, 'err' => '', 'r' => $rows]);
                    exit;
                }
            }
        }
    }
} catch (Throwable $e) {
    // Ignore local DB fallback failures
}

http_response_code(200);
echo json_encode(['s' => false, 'err' => 'API request failed', 'r' => []]);
