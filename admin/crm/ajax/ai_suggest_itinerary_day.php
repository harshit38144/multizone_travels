<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/quotation_db.php';
require_once __DIR__ . '/../includes/package_quotation.php';
require_once __DIR__ . '/../includes/ai_config.php';

$destination = trim((string) ($_POST['destination'] ?? $_GET['destination'] ?? ''));
$nights = max(0, (int) ($_POST['nights'] ?? $_GET['nights'] ?? 0));
$adults = max(1, (int) ($_POST['adults'] ?? $_GET['adults'] ?? 2));
$children = max(0, (int) ($_POST['children'] ?? $_GET['children'] ?? 0));
$notes = trim((string) ($_POST['notes'] ?? $_GET['notes'] ?? ''));
$existingTitle = trim((string) ($_POST['existing_title'] ?? $_GET['existing_title'] ?? ''));
$dayIndex = (int) ($_POST['day_index'] ?? $_GET['day_index'] ?? -1);

$result = crmAiSuggestItineraryDay(
    $destination,
    $dayIndex,
    $nights,
    $adults,
    $children,
    $notes,
    $existingTitle
);

if (!$result['ok']) {
    crmAiJsonResponse([
        'success' => false,
        'message' => $result['error'] ?? 'Could not generate day suggestion.',
    ], 400);
}

crmAiJsonResponse([
    'success' => true,
    'source' => $result['source'] ?? 'instant',
    'message' => trim((string) ($result['message'] ?? '')),
    'day_index' => $dayIndex,
    'day' => $result['day'] ?? ['title' => '', 'description' => '', 'image' => ''],
    'ai_configured' => crmAiUseGemini(),
    'instant_mode' => !crmAiUseGemini(),
]);
