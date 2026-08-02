<?php
header('Content-Type: application/json');

$q = $_GET['q'] ?? '';

if (!$q) {
    echo json_encode([]);
    exit;
}

$url = "https://flights-explorer.makemytrip.com/autosuggest?limit=10&query=" . urlencode($q);

$headers = [
    'Accept: */*',
    'Accept-Encoding: gzip, deflate, br, zstd',
    'Accept-Language: en-US,en;q=0.9,hi;q=0.8',
    'Cache-Control: no-cache',
    'Connection: keep-alive',
    'Dnt: 1',
    'Host: flights-explorer.makemytrip.com',
    'Origin: https://www.goibibo.com',
    'Pragma: no-cache',
    'Referer: https://www.goibibo.com/',
    'Sec-Ch-Ua: "Google Chrome";v="125", "Chromium";v="125", "Not.A/Brand";v="24"',
    'Sec-Ch-Ua-Mobile: ?0',
    'Sec-Ch-Ua-Platform: "Linux"',
    'Sec-Fetch-Dest: empty',
    'Sec-Fetch-Mode: cors',
    'Sec-Fetch-Site: cross-site',
    'User-Agent: Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36'
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_ENCODING, ""); // Handle gzip/deflate response
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode != 200) {
    http_response_code(400);
    echo json_encode(['error' => 'API request failed']);
    exit;
}

echo $response;
