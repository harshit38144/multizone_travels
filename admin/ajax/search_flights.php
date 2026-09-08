<?php

header('Content-Type: application/json');

$apiKey = '';
$configPath = __DIR__ . '/../config/aviationstack_config.php';
if (is_readable($configPath)) {
    $cfg = require $configPath;
    if (is_array($cfg)) {
        $apiKey = trim((string) ($cfg['access_key'] ?? ''));
    }
}
if ($apiKey === '') {
    $env = getenv('AVIATIONSTACK_ACCESS_KEY');
    if ($env !== false && $env !== '') {
        $apiKey = trim((string) $env);
    }
}

$from = isset($_GET['from']) ? trim($_GET['from']) : '';
$to = isset($_GET['to']) ? trim($_GET['to']) : '';

if (!$from || !$to) {
    echo json_encode(['data' => [], 'error' => 'Missing from or to airport']);
    exit;
}

if ($apiKey === '') {
    echo json_encode(['data' => [], 'error' => 'AviationStack API key not configured.']);
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
