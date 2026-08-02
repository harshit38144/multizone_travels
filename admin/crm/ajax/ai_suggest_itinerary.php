<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/ai_config.php';

$destination = trim((string) ($_POST['destination'] ?? $_GET['destination'] ?? ''));
$nights = max(0, (int) ($_POST['nights'] ?? $_GET['nights'] ?? 0));
$adults = max(1, (int) ($_POST['adults'] ?? $_GET['adults'] ?? 2));
$children = max(0, (int) ($_POST['children'] ?? $_GET['children'] ?? 0));
$startDate = trim((string) ($_POST['start_date'] ?? $_GET['start_date'] ?? ''));
$notes = trim((string) ($_POST['notes'] ?? $_GET['notes'] ?? ''));

$result = crmAiSuggestItinerary($destination, $nights, $adults, $children, $startDate, $notes);

if (!$result['ok']) {
    crmAiJsonResponse([
        'success' => false,
        'message' => $result['error'] ?? 'Could not generate itinerary.',
    ], 400);
}

crmAiJsonResponse([
    'success' => true,
    'source' => $result['source'] ?? 'ai',
    'message' => $result['message'] ?? '',
    'itinerary' => $result['itinerary'] ?? [],
    'ai_configured' => crmAiUseGemini(),
    'instant_mode' => !crmAiUseGemini(),
]);
