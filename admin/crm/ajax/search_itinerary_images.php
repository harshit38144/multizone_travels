<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/image_api_config.php';

$query = trim((string) ($_GET['q'] ?? $_GET['query'] ?? ''));
$limit = (int) ($_GET['limit'] ?? $_GET['per_page'] ?? 18);
$page = (int) ($_GET['page'] ?? 1);

$result = crmImageSearch($query, $limit, $page);

crmImageApiJson([
    'success' => true,
    'query' => $query,
    'source' => $result['source'] ?? 'none',
    'images' => $result['images'] ?? [],
    'page' => (int) ($result['page'] ?? max(1, $page)),
    'per_page' => (int) ($result['per_page'] ?? $limit),
    'total_results' => (int) ($result['total_results'] ?? 0),
    'has_more' => !empty($result['has_more']),
    'pexels_configured' => (crmImageApiSettings()['pexels_api_key'] ?? '') !== '',
]);
