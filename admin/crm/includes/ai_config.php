<?php
/**
 * AI configuration for CRM features (itinerary suggest, etc.).
 *
 * Option 1 — create ai_config.local.php in this folder:
 *   <?php return ['gemini_api_key' => 'YOUR_KEY_HERE'];
 *
 * Option 2 — set environment variable CRM_GEMINI_API_KEY
 *
 * Get a free key: https://aistudio.google.com/apikey
 */

function crmAiGeminiApiKey(): string
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $cached = '';
    $cfg = crmAiSettings();
    if (!empty($cfg['gemini_api_key'])) {
        $cached = trim((string) $cfg['gemini_api_key']);
    }

    if ($cached === '') {
        $env = getenv('CRM_GEMINI_API_KEY');
        if ($env !== false && $env !== '') {
            $cached = trim((string) $env);
        }
    }

    return $cached;
}

function crmAiSettings(): array
{
    static $settings = null;
    if ($settings !== null) {
        return $settings;
    }

    $settings = [
        'gemini_api_key' => '',
        'use_gemini_ai' => false,
        'instant_mode' => true,
    ];

    $localFile = __DIR__ . '/ai_config.local.php';
    if (is_file($localFile)) {
        $cfg = include $localFile;
        if (is_array($cfg)) {
            $settings = array_merge($settings, $cfg);
        }
    }

    $settings['use_gemini_ai'] = !empty($settings['use_gemini_ai']);
    $settings['instant_mode'] = array_key_exists('instant_mode', $settings)
        ? !empty($settings['instant_mode'])
        : !$settings['use_gemini_ai'];

    return $settings;
}

function crmAiUseGemini(): bool
{
    $cfg = crmAiSettings();
    return !empty($cfg['use_gemini_ai']) && crmAiGeminiApiKey() !== '';
}

function crmAiJsonResponse(array $payload, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function crmAiCallGemini(string $prompt, string $model = 'gemini-2.0-flash'): array
{
    $apiKey = crmAiGeminiApiKey();
    if ($apiKey === '') {
        return ['ok' => false, 'error' => 'Gemini API key is not configured.'];
    }

    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent?key=' . urlencode($apiKey);
    $body = json_encode([
        'contents' => [
            ['parts' => [['text' => $prompt]]],
        ],
        'generationConfig' => [
            'temperature' => 0.65,
            'responseMimeType' => 'application/json',
        ],
    ], JSON_UNESCAPED_UNICODE);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 12,
    ]);
    $raw = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($raw === false || $httpCode !== 200) {
        $msg = $curlErr ?: ('Gemini request failed (HTTP ' . $httpCode . ')');
        if ($raw) {
            $errData = json_decode($raw, true);
            if (!empty($errData['error']['message'])) {
                $msg = (string) $errData['error']['message'];
            }
        }
        return ['ok' => false, 'error' => $msg];
    }

    $data = json_decode($raw, true);
    $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
    if ($text === '') {
        return ['ok' => false, 'error' => 'Empty response from AI.'];
    }

    $parsed = json_decode($text, true);
    if (!is_array($parsed)) {
        return ['ok' => false, 'error' => 'Could not parse AI response.'];
    }

    return ['ok' => true, 'data' => $parsed];
}

function crmAiSanitizeItineraryHtml(string $html): string
{
    $html = trim($html);
    if ($html === '') {
        return '';
    }
    $allowed = '<ul><ol><li><p><br><strong><em><b><i>';
    return strip_tags($html, $allowed);
}

function crmAiNormalizeItineraryDays(array $days, int $expectedDays, bool $preserveImages = false): array
{
    $out = [];
    foreach ($days as $i => $day) {
        if (!is_array($day)) {
            continue;
        }
        $title = trim((string) ($day['title'] ?? ''));
        $desc = crmAiSanitizeItineraryHtml((string) ($day['description'] ?? ''));
        if ($title === '' && $desc === '') {
            continue;
        }
        $image = '';
        if ($preserveImages) {
            $image = trim((string) ($day['image'] ?? ''));
        }
        $out[] = [
            'title' => $title !== '' ? $title : ('Day ' . (count($out) + 1)),
            'description' => $desc,
            'image' => $image,
        ];
        if (count($out) >= $expectedDays) {
            break;
        }
    }

    while (count($out) < $expectedDays) {
        $n = count($out) + 1;
        $out[] = [
            'title' => 'Day ' . $n,
            'description' => '<ul><li>Free time / leisure at destination</li></ul>',
            'image' => '',
        ];
    }

    return $out;
}

function crmAiBuildItineraryPrompt(
    string $destination,
    int $totalDays,
    int $nights,
    int $adults,
    int $children,
    string $startDate,
    string $notes
): string {
    $travelers = $adults . ' adult' . ($adults !== 1 ? 's' : '');
    if ($children > 0) {
        $travelers .= ', ' . $children . ' child' . ($children !== 1 ? 'ren' : '');
    }

    $prompt = "You are a professional travel itinerary writer for an Indian travel agency (Multizone Travels).\n";
    $prompt .= "Create a detailed, realistic day-wise tour itinerary.\n\n";
    $prompt .= "Destination: {$destination}\n";
    $prompt .= "Duration: {$totalDays} days ({$nights} nights)\n";
    $prompt .= "Travelers: {$travelers}\n";
    if ($startDate !== '') {
        $prompt .= "Trip start date: {$startDate}\n";
    }
    if ($notes !== '') {
        $prompt .= "Special preferences: {$notes}\n";
    }
    $prompt .= "\nReturn ONLY valid JSON with this exact structure:\n";
    $prompt .= "{\"days\":[{\"title\":\"Short day title\",\"description\":\"<ul><li>Activity</li></ul>\"}]}\n\n";
    $prompt .= "Rules:\n";
    $prompt .= "- Provide exactly {$totalDays} day objects in the days array.\n";
    $prompt .= "- Day 1: include arrival, hotel check-in, light sightseeing if appropriate.\n";
    $prompt .= "- Last day: include check-out and departure/transfers.\n";
    $prompt .= "- Middle days: cover famous landmarks, local experiences, culture, and cuisine for {$destination}.\n";
    $prompt .= "- description must be HTML using only <ul> and <li> tags, 4-6 bullet points per day.\n";
    $prompt .= "- Titles must be concise (under 55 characters).\n";
    $prompt .= "- Write practical plans suitable for Indian holiday travelers.\n";
    $prompt .= "- Do not include prices or hotel names unless generic (e.g. 'check in at hotel').\n";

    return $prompt;
}

function crmAiIsQuotaOrRateError(string $error): bool
{
    $error = strtolower($error);
    $needles = ['quota', 'rate limit', 'rate-limit', 'resource exhausted', 'please retry', 'limit: 0', '429'];
    foreach ($needles as $needle) {
        if (strpos($error, $needle) !== false) {
            return true;
        }
    }
    return false;
}

function crmAiDestinationProfile(string $destination): array
{
    $destLower = strtolower($destination);
    $city = trim(explode(',', $destination)[0]);
    $cityLower = strtolower($city);

    $profiles = [
        'ajmer' => [
            'highlights' => [
                'Visit Ajmer Sharif Dargah — one of India\'s most revered Sufi shrines',
                'Explore Ana Sagar Lake and Baradari pavilions',
                'See Adhai Din Ka Jhonpra — historic mosque & architectural marvel',
                'Visit Nasiyan Jain Temple (Red Temple) and local bazaars',
                'Optional day trip to Pushkar (Brahma Temple & ghats)',
                'Try local Rajasthani thali and famous Ajmer sweets',
            ],
            'titles' => ['Arrival in Ajmer', 'Ajmer Sharif & Old City', 'Heritage & Pushkar Excursion', 'Local Culture & Markets', 'Departure'],
        ],
        'jaipur' => [
            'highlights' => [
                'Amber Fort with elephant/jeep ride and panoramic views',
                'City Palace, Jantar Mantar & Hawa Mahal in the Pink City',
                'Jal Mahal photo stop and Nahargarh sunset point',
                'Shopping at Johari / Bapu Bazaar for handicrafts & textiles',
                'Chokhi Dhani or local Rajasthani cultural dinner experience',
                'Optional visit to Albert Hall Museum or Birla Temple',
            ],
            'titles' => ['Arrival in Jaipur', 'Amber Fort & Old Jaipur', 'City Palace & Hawa Mahal', 'Markets & Culture', 'Departure'],
        ],
        'delhi' => [
            'highlights' => [
                'Red Fort, Jama Masjid & Chandni Chowk rickshaw ride',
                'India Gate, Rashtrapati Bhavan drive & Rajpath',
                'Qutub Minar and Humayun\'s Tomb heritage circuit',
                'Lotus Temple or Akshardham (time permitting)',
                'Local street food tour — parathas, chaat & kulfi',
                'Optional day trip to Agra (Taj Mahal) if schedule allows',
            ],
            'titles' => ['Arrival in Delhi', 'Old Delhi Heritage', 'New Delhi Landmarks', 'Monuments & Culture', 'Departure'],
        ],
        'goa' => [
            'highlights' => [
                'North Goa beaches — Calangute / Baga / Anjuna',
                'Old Goa churches — Basilica of Bom Jesus & Se Cathedral',
                'Fort Aguada lighthouse and scenic coastal views',
                'South Goa — Palolem or Colva for relaxed beach time',
                'Water sports / dolphin spotting cruise (optional)',
                'Seafood dinner at a beach shack',
            ],
            'titles' => ['Arrival in Goa', 'North Goa Beaches', 'Heritage & Forts', 'South Goa Leisure', 'Departure'],
        ],
        'manali' => [
            'highlights' => [
                'Hadimba Devi Temple and Manu Temple in Old Manali',
                'Solang Valley — adventure activities / snow views (seasonal)',
                'Rohtang Pass / Atal Tunnel viewpoint excursion',
                'Mall Road shopping and riverside café time',
                'Vashisht hot water springs',
                'Naggar Castle or local apple orchard visit',
            ],
            'titles' => ['Arrival in Manali', 'Manali Local Sightseeing', 'Solang Valley Day', 'Rohtang Excursion', 'Departure'],
        ],
        'bangkok' => [
            'highlights' => [
                'Grand Palace & Wat Phra Kaew (Emerald Buddha)',
                'Wat Arun (Temple of Dawn) and Chao Phraya river views',
                'Wat Pho — Reclining Buddha',
                'Floating market or Chatuchak / local market visit',
                'Evening street food tour — pad thai, mango sticky rice',
                'Optional Ayutthaya day trip or MBK / Siam shopping',
            ],
            'titles' => ['Arrival in Bangkok', 'Royal Temples Tour', 'Bangkok Culture & Markets', 'Leisure & Food Trail', 'Departure'],
        ],
        'dubai' => [
            'highlights' => [
                'Burj Khalifa observation deck & Dubai Mall fountain show',
                'Desert safari with dune bashing, BBQ & cultural show',
                'Dubai Marina, JBR Walk & Palm Jumeirah photo stops',
                'Gold Souk & Spice Souk in Deira / old Dubai creek abra ride',
                'Miracle Garden or Museum of the Future (seasonal)',
                'Global Village or Ain Dubai (optional evening activity)',
            ],
            'titles' => ['Arrival in Dubai', 'Modern Dubai Icons', 'Desert Safari Adventure', 'Old Dubai & Souks', 'Departure'],
        ],
    ];

    foreach ($profiles as $key => $profile) {
        if (strpos($cityLower, $key) !== false || strpos($destLower, $key) !== false) {
            return $profile;
        }
    }

    return [
        'highlights' => [
            'Arrive and transfer to hotel — check in and rest',
            'Explore major landmarks and famous viewpoints of ' . $city,
            'Visit popular cultural / heritage attractions',
            'Local market visit, cuisine tasting & leisure time',
            'Optional nearby excursion or free day for personal interests',
            'Shopping and last-minute sightseeing',
        ],
        'titles' => ['Arrival & Welcome', 'City Highlights', 'Local Experiences', 'Excursion Day', 'Culture & Leisure', 'Departure'],
    ];
}

function crmAiBulletsToHtml(array $lines): string
{
    $bullets = array_map(function ($line) {
        return '<li>' . htmlspecialchars((string) $line, ENT_QUOTES, 'UTF-8') . '</li>';
    }, $lines);
    return '<ul>' . implode('', $bullets) . '</ul>';
}

function crmAiSmartItinerary(string $destination, int $totalDays, string $notes = ''): array
{
    $destShort = trim(explode(',', $destination)[0]);
    if ($destShort === '') {
        $destShort = $destination;
    }
    $profile = crmAiDestinationProfile($destination);
    $highlights = $profile['highlights'];
    $titles = $profile['titles'];

    if ($notes !== '') {
        $highlights[] = 'As per your preference: ' . $notes;
    }

    $days = [];
    for ($i = 0; $i < $totalDays; $i++) {
        if ($i === 0) {
            $title = 'Arrival in ' . $destShort;
            $lines = [
                'Arrive at ' . $destShort . ' — meet & greet at airport / railway station',
                'Transfer to hotel and complete check-in',
                'Rest and freshen up after your journey',
            ];
            if (!empty($highlights[0])) {
                $lines[] = 'Evening at leisure — optional visit: ' . preg_replace('/^Visit |^Explore /', '', $highlights[0]);
            }
            $lines[] = 'Overnight stay at ' . $destShort;
        } elseif ($i === $totalDays - 1 && $totalDays > 1) {
            $title = 'Departure from ' . $destShort;
            $lines = [
                'Breakfast at hotel',
                'Check-out and hotel formalities',
                'Transfer to airport / railway station for onward journey',
                'Tour concludes with wonderful memories',
            ];
        } else {
            $midIndex = $i - 1;
            $title = $titles[min($midIndex + 1, count($titles) - 2)] ?? ('Explore ' . $destShort);
            $lines = ['Breakfast at hotel'];
            $h1 = $highlights[($midIndex * 2) % count($highlights)];
            $h2 = $highlights[($midIndex * 2 + 1) % count($highlights)];
            $lines[] = $h1;
            if ($h2 !== $h1) {
                $lines[] = $h2;
            }
            $lines[] = 'Return to hotel — overnight stay at ' . $destShort;
        }

        $days[] = [
            'title' => $title,
            'description' => crmAiBulletsToHtml($lines),
            'image' => '',
        ];
    }

    return $days;
}

function crmAiInstantItinerary(string $destination, int $totalDays, string $notes = ''): array
{
    return [
        'ok' => true,
        'source' => 'instant',
        'message' => '',
        'itinerary' => crmAiSmartItinerary($destination, $totalDays, $notes),
    ];
}

function crmAiSuggestItinerary(
    string $destination,
    int $nights,
    int $adults,
    int $children,
    string $startDate,
    string $notes
): array {
    $destination = trim($destination);
    if ($destination === '') {
        return ['ok' => false, 'error' => 'Please enter a destination on Guest & Tour step.'];
    }

    $nights = max(0, $nights);
    $totalDays = $nights + 1;
    if ($totalDays < 1) {
        return ['ok' => false, 'error' => 'Set No of Nights (at least 1) on Guest & Tour step to generate days.'];
    }
    if ($totalDays > 21) {
        return ['ok' => false, 'error' => 'Itinerary suggest supports up to 21 days (20 nights).'];
    }

    // Instant mode — no external API call (default for immediate results)
    if (!crmAiUseGemini()) {
        return crmAiInstantItinerary($destination, $totalDays, $notes);
    }

    $prompt = crmAiBuildItineraryPrompt($destination, $totalDays, $nights, $adults, $children, $startDate, $notes);
    $result = crmAiCallGemini($prompt);
    if (!$result['ok']) {
        if (crmAiIsQuotaOrRateError($result['error'] ?? '')) {
            $instant = crmAiInstantItinerary($destination, $totalDays, $notes);
            $instant['message'] = 'Gemini quota reached — instant destination itinerary applied.';
            return $instant;
        }
        return $result;
    }

    $daysRaw = $result['data']['days'] ?? $result['data']['itinerary'] ?? [];
    if (!is_array($daysRaw) || !$daysRaw) {
        $instant = crmAiInstantItinerary($destination, $totalDays, $notes);
        $instant['message'] = 'AI response invalid — instant destination itinerary applied.';
        return $instant;
    }

    return [
        'ok' => true,
        'source' => 'ai',
        'itinerary' => crmAiNormalizeItineraryDays($daysRaw, $totalDays),
    ];
}
