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

if ($httpCode !== 200 || $response === false || $response === '') {
    http_response_code(400);
    echo json_encode(['error' => 'API request failed']);
    exit;
}

echo $response;
