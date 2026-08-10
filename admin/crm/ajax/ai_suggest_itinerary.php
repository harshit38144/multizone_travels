<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/quotation_db.php';
require_once __DIR__ . '/../includes/package_quotation.php';
require_once __DIR__ . '/../includes/ai_config.php';

$destination = trim((string) ($_POST['destination'] ?? $_GET['destination'] ?? ''));
$nights = max(0, (int) ($_POST['nights'] ?? $_GET['nights'] ?? 0));
$adults = max(1, (int) ($_POST['adults'] ?? $_GET['adults'] ?? 2));
$children = max(0, (int) ($_POST['children'] ?? $_GET['children'] ?? 0));
$startDate = trim((string) ($_POST['start_date'] ?? $_GET['start_date'] ?? ''));
$notes = trim((string) ($_POST['notes'] ?? $_GET['notes'] ?? ''));
$excludeQuotationId = max(0, (int) ($_POST['exclude_quotation_id'] ?? $_GET['exclude_quotation_id'] ?? 0));

$totalDays = $nights + 1;

// Prefer a previously saved itinerary with the same destination + nights.
$previous = crmFindMatchingPreviousItinerary($conn, $destination, $nights, $excludeQuotationId);
if ($previous && !empty($previous['itinerary']) && is_array($previous['itinerary'])) {
    $itinerary = crmAiNormalizeItineraryDays($previous['itinerary'], max(1, $totalDays), true);
    $matchType = (string) ($previous['match_type'] ?? 'quotation');
    $matchLabel = (string) ($previous['match_label'] ?? '');
    $message = $matchType === 'package'
        ? ('Loaded from previous package' . ($matchLabel !== '' ? ': ' . $matchLabel : '') . '.')
        : ('Loaded from previous quotation' . ($matchLabel !== '' ? ' ' . $matchLabel : '') . '.');

    crmAiJsonResponse([
        'success' => true,
        'source' => 'previous',
        'from_previous' => true,
        'is_new_suggestion' => false,
        'match_type' => $matchType,
        'match_id' => (int) ($previous['match_id'] ?? 0),
        'match_label' => $matchLabel,
        'match_destination' => (string) ($previous['match_destination'] ?? ''),
        'message' => $message,
        'itinerary' => $itinerary,
        'ai_configured' => crmAiUseGemini(),
        'instant_mode' => !crmAiUseGemini(),
    ]);
}

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
    'from_previous' => false,
    'is_new_suggestion' => true,
    'match_type' => '',
    'match_id' => 0,
    'match_label' => '',
    'message' => trim((string) ($result['message'] ?? '')),
    'itinerary' => $result['itinerary'] ?? [],
    'ai_configured' => crmAiUseGemini(),
    'instant_mode' => !crmAiUseGemini(),
]);
