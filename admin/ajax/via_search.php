<?php
header('Content-Type: application/json');

$url = "https://in.via.com/apiv2/flight/search?flowType=NODE&ajax=true&jsonData=true&requestSource=NODE_B2B&__xreq__=true";

$query = json_decode(file_get_contents('php://input'), true);

if (!$query) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON query data.']);
    exit;
}

$headers = [
    'User-Agent: Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/65.0.3325.181 Safari/537.36',
    'Content-Type: application/json',
    'Cookie: JSESSIONID=481863D6E2E15F53B634FE8FC0D65423.t1; vsessionid=ee5412d8-fbc5-472b-a36f-6e41d772e0f3-in.via.com-tomcat388.via.com'
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($query));
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode != 200) {
    http_response_code(400);
    echo json_encode(['error' => 'API request failed. Http code: ' . $httpCode, 'response' => $response]);
    exit;
}

echo $response;
