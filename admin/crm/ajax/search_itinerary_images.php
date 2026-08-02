<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/image_api_config.php';

$query = trim((string) ($_GET['q'] ?? $_GET['query'] ?? ''));
$limit = (int) ($_GET['limit'] ?? 12);

$result = crmImageSearch($query, $limit);

crmImageApiJson([
    'success' => true,
    'query' => $query,
    'source' => $result['source'],
    'images' => $result['images'],
    'pexels_configured' => (crmImageApiSettings()['pexels_api_key'] ?? '') !== '',
]);
