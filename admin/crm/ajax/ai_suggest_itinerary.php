<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/quotation_db.php';
require_once __DIR__ . '/../includes/package_quotation.php';
require_once __DIR__ . '/../includes/ai_config.php';

$started = microtime(true);

$destination = trim((string) ($_POST['destination'] ?? $_GET['destination'] ?? ''));
$nights = max(0, (int) ($_POST['nights'] ?? $_GET['nights'] ?? 0));
$adults = max(1, (int) ($_POST['adults'] ?? $_GET['adults'] ?? 2));
$children = max(0, (int) ($_POST['children'] ?? $_GET['children'] ?? 0));
$startDate = trim((string) ($_POST['start_date'] ?? $_GET['start_date'] ?? ''));
$notes = trim((string) ($_POST['notes'] ?? $_GET['notes'] ?? ''));
$contextBrief = trim((string) ($_POST['context_brief'] ?? $_GET['context_brief'] ?? ''));
$excludeQuotationId = max(0, (int) ($_POST['exclude_quotation_id'] ?? $_GET['exclude_quotation_id'] ?? 0));
// Modal "Generate Itinerary" should hit AI directly (skip slow previous-match scan unless reuse requested).
$preferAi = (int) ($_POST['prefer_ai'] ?? $_GET['prefer_ai'] ?? 0) === 1;
$reusePrevious = (int) ($_POST['reuse_previous'] ?? $_GET['reuse_previous'] ?? 0) === 1;

$totalDays = $nights + 1;

// Optional day headings from the AI Suggest modal (preserve exactly).
$dayHeadings = [];
$rawHeadings = $_POST['day_headings'] ?? $_GET['day_headings'] ?? null;
if (is_string($rawHeadings) && $rawHeadings !== '') {
    $decoded = json_decode($rawHeadings, true);
    if (is_array($decoded)) {
        $rawHeadings = $decoded;
    }
}
if (is_array($rawHeadings)) {
    foreach ($rawHeadings as $heading) {
        if (is_array($heading)) {
            $heading = (string) ($heading['title'] ?? $heading['heading'] ?? '');
        }
        $dayHeadings[] = trim((string) $heading);
    }
}
if ($totalDays > 0) {
    $dayHeadings = array_slice(array_pad($dayHeadings, $totalDays, ''), 0, $totalDays);
}

// Prefer a previously saved itinerary only when explicitly allowed (not for AI Generate modal).
if (!$preferAi || $reusePrevious) {
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
            'elapsed_ms' => (int) round((microtime(true) - $started) * 1000),
            'ai_configured' => crmAiUseGemini(),
            'instant_mode' => !crmAiUseGemini(),
        ]);
    }
}

$result = crmAiSuggestItinerary(
    $destination,
    $nights,
    $adults,
    $children,
    $startDate,
    $notes,
    $contextBrief,
    $dayHeadings
);

if (!$result['ok']) {
    crmAiLog('itinerary_endpoint_fail', [
        'destination' => $destination,
        'days' => $totalDays,
        'ms' => (int) round((microtime(true) - $started) * 1000),
        'error' => (string) ($result['error'] ?? ''),
    ]);
    crmAiJsonResponse([
        'success' => false,
        'message' => $result['error'] ?? 'Could not generate itinerary.',
        'elapsed_ms' => (int) ($result['elapsed_ms'] ?? round((microtime(true) - $started) * 1000)),
    ], 400);
}

$elapsedMs = (int) ($result['elapsed_ms'] ?? round((microtime(true) - $started) * 1000));
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
    'model' => (string) ($result['model'] ?? ''),
    'elapsed_ms' => $elapsedMs,
    'ai_configured' => crmAiUseGemini(),
    'instant_mode' => !crmAiUseGemini(),
]);
