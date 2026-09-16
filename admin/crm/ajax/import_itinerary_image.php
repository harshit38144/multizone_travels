<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/image_api_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    crmImageApiJson(['success' => false, 'message' => 'Invalid request.'], 405);
}

$url = trim((string) ($_POST['url'] ?? ''));
$result = crmImageImportFromUrl($url);

if (!$result['ok']) {
    // Keep HTTP 200 so front-end can read message via .done(); success:false signals failure.
    crmImageApiJson([
        'success' => false,
        'message' => $result['error'] ?? 'Import failed.',
    ]);
}

crmImageApiJson([
    'success' => true,
    'url' => $result['url'],
]);
