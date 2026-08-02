<?php

header('Content-Type: application/json');

$apiKey = "98666b104dedc21ace59c4313f027921";

$from = isset($_GET['from']) ? trim($_GET['from']) : '';
$to = isset($_GET['to']) ? trim($_GET['to']) : '';

if (!$from || !$to) {
    echo json_encode(['data' => [], 'error' => 'Missing from or to airport']);
    exit;
}

$url = "http://api.aviationstack.com/v1/flights?access_key=" . urlencode($apiKey)
     . "&dep_iata=" . urlencode($from)
     . "&arr_iata=" . urlencode($to);

$response = @file_get_contents($url);

if ($response === false) {
    echo json_encode(['data' => [], 'error' => 'Unable to fetch flight data']);
    exit;
}

echo $response;