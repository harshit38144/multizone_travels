<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/image_api_config.php';

$query = trim((string) ($_GET['q'] ?? $_GET['query'] ?? ''));
$limit = (int) ($_GET['limit'] ?? $_GET['per_page'] ?? 18);
$page = (int) ($_GET['page'] ?? 1);
$provider = strtolower(trim((string) ($_GET['provider'] ?? 'pexels')));
if ($provider !== 'unsplash') {
    $provider = 'pexels';
}

$result = crmImageSearch($query, $limit, $page, $provider);
$settings = crmImageApiSettings();
$error = trim((string) ($result['error'] ?? ''));
$images = $result['images'] ?? [];

crmImageApiJson([
    'success' => $error === '' || !empty($images),
    'query' => $query,
    'provider' => $provider,
    'source' => $result['source'] ?? 'none',
    'images' => $images,
    'page' => (int) ($result['page'] ?? max(1, $page)),
    'per_page' => (int) ($result['per_page'] ?? $limit),
    'total_results' => (int) ($result['total_results'] ?? 0),
    'has_more' => !empty($result['has_more']),
    'message' => $error,
    'pexels_configured' => ($settings['pexels_api_key'] ?? '') !== '',
    'unsplash_configured' => ($settings['unsplash_access_key'] ?? '') !== '',
]);
