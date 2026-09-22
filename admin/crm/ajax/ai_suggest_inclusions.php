<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/ai_config.php';

$raw = file_get_contents('php://input');
$json = [];
if (is_string($raw) && $raw !== '') {
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $json = $decoded;
    }
}

$context = [];
if (!empty($json['context']) && is_array($json['context'])) {
    $context = $json['context'];
} elseif (!empty($_POST['context'])) {
    $posted = $_POST['context'];
    if (is_string($posted)) {
        $tmp = json_decode($posted, true);
        $context = is_array($tmp) ? $tmp : [];
    } elseif (is_array($posted)) {
        $context = $posted;
    }
}

// Flat POST fallbacks (optional)
if (!$context) {
    $context = [
        'guest' => [
            'guest_name' => trim((string) ($_POST['guest_name'] ?? '')),
            'adults' => (int) ($_POST['adults'] ?? $_POST['no_of_adults'] ?? 0),
            'children' => (int) ($_POST['children'] ?? $_POST['no_of_children'] ?? 0),
            'mobile_no' => trim((string) ($_POST['mobile_no'] ?? '')),
            'email' => trim((string) ($_POST['email'] ?? '')),
        ],
        'tour' => [
            'destination' => trim((string) ($_POST['destination'] ?? '')),
            'nights' => (int) ($_POST['nights'] ?? $_POST['no_of_nights'] ?? 0),
            'tentative_date' => trim((string) ($_POST['tentative_date'] ?? '')),
            'package_name' => trim((string) ($_POST['package_name'] ?? $_POST['header_text'] ?? '')),
        ],
        'flights' => [],
        'hotels' => [],
        'itinerary' => [],
        'notes' => trim((string) ($_POST['notes'] ?? '')),
        'context_text' => trim((string) ($_POST['context_text'] ?? '')),
    ];
    foreach (['flights', 'hotels', 'itinerary'] as $key) {
        if (empty($_POST[$key])) {
            continue;
        }
        $val = $_POST[$key];
        if (is_string($val)) {
            $tmp = json_decode($val, true);
            if (is_array($tmp)) {
                $context[$key] = $tmp;
            }
        } elseif (is_array($val)) {
            $context[$key] = $val;
        }
    }
}

if (!empty($json['notes'])) {
    $context['notes'] = trim((string) $json['notes']);
}
if (!empty($json['context_text'])) {
    $context['context_text'] = trim((string) $json['context_text']);
}
if (!empty($_POST['notes']) && empty($context['notes'])) {
    $context['notes'] = trim((string) $_POST['notes']);
}
if (!empty($_POST['context_text']) && empty($context['context_text'])) {
    $context['context_text'] = trim((string) $_POST['context_text']);
}

$result = crmAiSuggestInclusions($context);

if (!$result['ok']) {
    crmAiJsonResponse([
        'success' => false,
        'message' => $result['error'] ?? 'Could not generate inclusions.',
        'ai_configured' => crmAiUseGemini(),
        'instant_mode' => !crmAiUseGemini(),
        'missing' => $result['missing'] ?? [],
    ], 400);
}

crmAiJsonResponse([
    'success' => true,
    'source' => $result['source'] ?? 'instant',
    'message' => trim((string) ($result['message'] ?? '')),
    'items' => $result['items'] ?? [],
    'inclusions_html' => $result['inclusions_html'] ?? '',
    'ai_configured' => crmAiUseGemini(),
    'instant_mode' => !crmAiUseGemini(),
    'missing' => $result['missing'] ?? [],
]);
