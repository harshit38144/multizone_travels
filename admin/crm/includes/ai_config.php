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

/**
 * Preferred Gemini models (tried in order on overload / unavailability).
 *
 * @return list<string>
 */
function crmAiGeminiModels(): array
{
    return [
        'gemini-3.5-flash-lite',
        'gemini-3.5-flash',
        'gemini-3.6-flash',
        'gemini-3.7-flash',
    ];
}

function crmAiIsTransientGeminiError(string $error): bool
{
    $error = strtolower($error);
    $needles = [
        'high demand',
        'try again later',
        'temporarily',
        'unavailable',
        'overloaded',
        'resource exhausted',
        'rate limit',
        'rate-limit',
        'quota',
        '503',
        '429',
        'no longer available',
        'not found',
    ];
    foreach ($needles as $needle) {
        if (strpos($error, $needle) !== false) {
            return true;
        }
    }
    return false;
}

/**
 * Single-model request (no fallbacks).
 */
function crmAiCallGeminiOnce(string $prompt, string $model, int $timeoutSeconds = 12): array
{
    $apiKey = crmAiGeminiApiKey();
    if ($apiKey === '') {
        return ['ok' => false, 'error' => 'Gemini API key is not configured.'];
    }

    // Prefer header auth (works for classic AIza… keys and newer AI Studio keys).
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent';
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
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'x-goog-api-key: ' . $apiKey,
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => max(8, $timeoutSeconds),
    ]);
    $raw = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    // Fallback: retry with ?key= (works for both legacy AIza… and new AQ. auth keys).
    if ($raw === false || $httpCode === 401 || $httpCode === 403) {
        $urlQs = $url . '?key=' . urlencode($apiKey);
        $ch = curl_init($urlQs);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => max(8, $timeoutSeconds),
        ]);
        $raw = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);
    }

    if ($raw === false || $httpCode !== 200) {
        $msg = $curlErr ?: ('Gemini request failed (HTTP ' . $httpCode . ')');
        if ($raw) {
            $errData = json_decode($raw, true);
            if (!empty($errData['error']['message'])) {
                $msg = (string) $errData['error']['message'];
            }
        }
        return ['ok' => false, 'error' => $msg, 'http_code' => $httpCode];
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

    return ['ok' => true, 'data' => $parsed, 'model' => $model];
}

/**
 * Call Gemini with automatic model fallbacks + short retries on overload.
 *
 * @param list<string>|null $models
 */
function crmAiCallGemini(string $prompt, string $model = 'gemini-3.5-flash-lite', int $timeoutSeconds = 12, ?array $models = null): array
{
    $queue = [];
    if (is_array($models) && $models) {
        foreach ($models as $m) {
            $m = trim((string) $m);
            if ($m !== '') {
                $queue[] = $m;
            }
        }
    } else {
        $queue = crmAiGeminiModels();
        $preferred = trim($model);
        if ($preferred !== '') {
            array_unshift($queue, $preferred);
        }
    }

    $seen = [];
    $unique = [];
    foreach ($queue as $m) {
        if (isset($seen[$m])) {
            continue;
        }
        $seen[$m] = true;
        $unique[] = $m;
    }

    $lastError = 'Gemini request failed.';
    foreach ($unique as $tryModel) {
        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $result = crmAiCallGeminiOnce($prompt, $tryModel, $timeoutSeconds);
            if (!empty($result['ok'])) {
                return $result;
            }
            $lastError = (string) ($result['error'] ?? $lastError);
            $transient = crmAiIsTransientGeminiError($lastError)
                || in_array((int) ($result['http_code'] ?? 0), [429, 503, 404], true);
            if (!$transient) {
                // Auth / hard failure — no point hopping models forever.
                if (in_array((int) ($result['http_code'] ?? 0), [401, 403], true)) {
                    return ['ok' => false, 'error' => $lastError];
                }
                break;
            }
            if ($attempt < 2) {
                usleep(450000);
            }
        }
    }

    return ['ok' => false, 'error' => $lastError];
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
    return crmAiIsTransientGeminiError($error);
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

function crmAiBuildSingleDayPrompt(
    string $destination,
    int $dayNumber,
    int $totalDays,
    int $nights,
    int $adults,
    int $children,
    string $notes,
    string $existingTitle = ''
): string {
    $travelers = $adults . ' adult' . ($adults !== 1 ? 's' : '');
    if ($children > 0) {
        $travelers .= ', ' . $children . ' child' . ($children !== 1 ? 'ren' : '');
    }

    $role = 'middle sightseeing day';
    if ($dayNumber === 1) {
        $role = 'arrival day (meet & greet, transfer, check-in, light evening activity)';
    } elseif ($dayNumber === $totalDays && $totalDays > 1) {
        $role = 'departure day (breakfast, check-out, transfer to airport/station)';
    }

    $prompt = "You are a professional travel itinerary writer for an Indian travel agency (Multizone Travels).\n";
    $prompt .= "Create content for ONE day only of a multi-day tour.\n\n";
    $prompt .= "Destination: {$destination}\n";
    $prompt .= "Full trip duration: {$totalDays} days ({$nights} nights)\n";
    $prompt .= "Generate Day {$dayNumber} of {$totalDays} — role: {$role}\n";
    $prompt .= "Travelers: {$travelers}\n";
    if ($existingTitle !== '') {
        $prompt .= "Preferred day theme/title hint: {$existingTitle}\n";
    }
    if ($notes !== '') {
        $prompt .= "USER REQUEST FOR THIS DAY (must follow closely): {$notes}\n";
    }
    $prompt .= "\nReturn ONLY valid JSON with this exact structure:\n";
    $prompt .= "{\"title\":\"Short day title\",\"description\":\"<ul><li>Activity</li></ul>\"}\n\n";
    $prompt .= "Rules:\n";
    $prompt .= "- description must be HTML using only <ul> and <li> tags, 4-6 bullet points.\n";
    $prompt .= "- Title must be concise (under 55 characters).\n";
    if ($notes !== '') {
        $prompt .= "- Build the day plan primarily from the USER REQUEST above.\n";
    }
    $prompt .= "- Write practical plans suitable for Indian holiday travelers visiting {$destination}.\n";
    $prompt .= "- Do not include prices or specific hotel brand names.\n";

    return $prompt;
}

/**
 * Suggest a single itinerary day (0-based day index).
 *
 * @return array{ok:bool,error?:string,source?:string,message?:string,day?:array{title:string,description:string,image:string}}
 */
function crmAiDayFromUserPrompt(
    string $destination,
    int $dayIndex,
    int $totalDays,
    string $notes,
    string $existingTitle = ''
): array {
    $destShort = trim(explode(',', $destination)[0]);
    if ($destShort === '') {
        $destShort = $destination;
    }
    $dayNumber = $dayIndex + 1;
    $notes = trim($notes);

    $title = trim($existingTitle);
    if ($title === '') {
        $short = preg_replace('/\s+/', ' ', $notes);
        $short = trim((string) $short);
        if (strlen($short) > 52) {
            $cut = substr($short, 0, 52);
            $space = strrpos($cut, ' ');
            $short = $space !== false ? substr($cut, 0, $space) : $cut;
            $short = rtrim($short, '.,;:') . '…';
        }
        if ($short !== '') {
            $title = $short;
        } elseif ($dayIndex === 0) {
            $title = 'Arrival in ' . $destShort;
        } elseif ($dayIndex === $totalDays - 1 && $totalDays > 1) {
            $title = 'Departure from ' . $destShort;
        } else {
            $title = 'Day ' . $dayNumber . ' in ' . $destShort;
        }
    }

    $parts = preg_split('/[\r\n]+|(?:\s*;\s*)|(?:\s+\band\s+)|,\s+/i', $notes) ?: [];
    $parts = array_values(array_filter(array_map(static function ($p) {
        return trim((string) $p);
    }, $parts), static function ($p) {
        return $p !== '';
    }));
    if (!$parts && $notes !== '') {
        $parts = [$notes];
    }

    $lines = [];
    if ($dayIndex === 0) {
        $lines[] = 'Arrive at ' . $destShort . ' — meet & greet at airport / railway station';
        $lines[] = 'Transfer to hotel and complete check-in';
    } else {
        $lines[] = 'Breakfast at hotel';
    }

    foreach (array_slice($parts, 0, 5) as $part) {
        $lines[] = $part;
    }

    if ($dayIndex === $totalDays - 1 && $totalDays > 1) {
        $lines[] = 'Check-out and transfer to airport / railway station for onward journey';
    } else {
        $lines[] = 'Overnight stay at ' . $destShort;
    }

    return [
        'title' => $title,
        'description' => crmAiBulletsToHtml($lines),
        'image' => '',
    ];
}

function crmAiSuggestItineraryDay(
    string $destination,
    int $dayIndex,
    int $nights,
    int $adults,
    int $children,
    string $notes = '',
    string $existingTitle = ''
): array {
    $destination = trim($destination);
    if ($destination === '') {
        return ['ok' => false, 'error' => 'Please enter a destination on Guest & Tour step.'];
    }

    $nights = max(0, $nights);
    $totalDays = $nights + 1;
    if ($totalDays < 1) {
        return ['ok' => false, 'error' => 'Set No of Nights (at least 1) on Guest & Tour step.'];
    }
    if ($totalDays > 21) {
        return ['ok' => false, 'error' => 'Itinerary suggest supports up to 21 days (20 nights).'];
    }
    if ($dayIndex < 0 || $dayIndex >= $totalDays) {
        return ['ok' => false, 'error' => 'Invalid day selected.'];
    }

    $dayNumber = $dayIndex + 1;
    $notes = trim($notes);

    $pickDay = static function (array $days) use ($dayIndex): array {
        $day = $days[$dayIndex] ?? ['title' => '', 'description' => '', 'image' => ''];
        return [
            'title' => trim((string) ($day['title'] ?? '')),
            'description' => (string) ($day['description'] ?? ''),
            'image' => (string) ($day['image'] ?? ''),
        ];
    };

    // User typed a day-specific request — prefer that as the main signal.
    if ($notes !== '' && !crmAiUseGemini()) {
        return [
            'ok' => true,
            'source' => 'instant',
            'message' => '',
            'day' => crmAiDayFromUserPrompt($destination, $dayIndex, $totalDays, $notes, $existingTitle),
        ];
    }

    if (!crmAiUseGemini()) {
        $days = crmAiSmartItinerary($destination, $totalDays, $notes);
        return [
            'ok' => true,
            'source' => 'instant',
            'message' => '',
            'day' => $pickDay($days),
        ];
    }

    $prompt = crmAiBuildSingleDayPrompt(
        $destination,
        $dayNumber,
        $totalDays,
        $nights,
        $adults,
        $children,
        $notes,
        $existingTitle
    );
    $result = crmAiCallGemini($prompt);
    if (!$result['ok']) {
        if (crmAiIsQuotaOrRateError($result['error'] ?? '')) {
            $day = $notes !== ''
                ? crmAiDayFromUserPrompt($destination, $dayIndex, $totalDays, $notes, $existingTitle)
                : $pickDay(crmAiSmartItinerary($destination, $totalDays, $notes));
            return [
                'ok' => true,
                'source' => 'instant',
                'message' => 'Gemini quota reached — instant day suggestion applied.',
                'day' => $day,
            ];
        }
        return $result;
    }

    $data = is_array($result['data'] ?? null) ? $result['data'] : [];
    $title = trim((string) ($data['title'] ?? ''));
    $description = (string) ($data['description'] ?? '');

    // Some models wrap a single day inside days[0]
    if ($title === '' && $description === '' && !empty($data['days']) && is_array($data['days'])) {
        $first = $data['days'][0] ?? null;
        if (is_array($first)) {
            $title = trim((string) ($first['title'] ?? ''));
            $description = (string) ($first['description'] ?? '');
        }
    }

    if ($title === '' && $description === '') {
        $day = $notes !== ''
            ? crmAiDayFromUserPrompt($destination, $dayIndex, $totalDays, $notes, $existingTitle)
            : $pickDay(crmAiSmartItinerary($destination, $totalDays, $notes));
        return [
            'ok' => true,
            'source' => 'instant',
            'message' => 'AI response invalid — instant day suggestion applied.',
            'day' => $day,
        ];
    }

    $normalized = crmAiNormalizeItineraryDays([
        ['title' => $title, 'description' => $description],
    ], 1);

    return [
        'ok' => true,
        'source' => 'ai',
        'message' => '',
        'day' => $normalized[0] ?? ['title' => $title, 'description' => $description, 'image' => ''],
    ];
}

/**
 * Sanitize inclusions HTML for Summernote / preview.
 */
function crmAiSanitizeInclusionsHtml(string $html): string
{
    $html = trim($html);
    if ($html === '') {
        return '';
    }
    $allowed = '<ul><ol><li><p><br><strong><em><b><i><div><span>';
    return strip_tags($html, $allowed);
}

/**
 * Canonical inclusion section definitions (label + icon), matching quote layout.
 *
 * @return array<string, array{label:string,icon:string}>
 */
function crmAiInclusionsSectionMeta(): array
{
    return [
        'airfare' => ['label' => 'Airfare', 'icon' => 'fas fa-plane'],
        'accommodation' => ['label' => 'Accommodation', 'icon' => 'fas fa-hotel'],
        'meals' => ['label' => 'Meals', 'icon' => 'fas fa-utensils'],
        'sightseeing' => ['label' => 'Sightseeing & Activities', 'icon' => 'fas fa-ticket-alt'],
        'transfers' => ['label' => 'Transfers & Transportation', 'icon' => 'fas fa-shuttle-van'],
        'other' => ['label' => 'Other Inclusions', 'icon' => 'fas fa-clipboard-list'],
    ];
}

/**
 * Allow limited inline markup inside a bullet (bold / italic / arrow).
 */
function crmAiInclusionsFormatBulletHtml(string $text): string
{
    $text = trim(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if ($text === '') {
        return '';
    }

    // Preserve intentional simple markup from the model: <strong>, <em>, <b>, <i>
    $text = strip_tags($text, '<strong><em><b><i>');
    // Normalize arrows
    $text = str_replace(['->', '=>', '→'], '→', $text);
    $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
    return $text;
}

/**
 * Build categorized inclusions HTML (icon heading + bullets), matching quote format.
 *
 * @param array<string, mixed> $sections  keyed by airfare|accommodation|meals|sightseeing|transfers|other
 */
function crmAiInclusionsSectionsToHtml(array $sections): string
{
    $meta = crmAiInclusionsSectionMeta();
    $html = '';
    $flatItems = [];

    foreach ($meta as $key => $info) {
        $rawItems = $sections[$key] ?? [];
        if (!is_array($rawItems)) {
            continue;
        }
        $bullets = [];
        foreach ($rawItems as $item) {
            if (is_array($item)) {
                $item = (string) ($item['html'] ?? $item['text'] ?? $item['title'] ?? $item['label'] ?? '');
            }
            $formatted = crmAiInclusionsFormatBulletHtml((string) $item);
            if ($formatted === '') {
                continue;
            }
            $bullets[] = $formatted;
            $flatItems[] = trim(strip_tags($formatted));
            if (count($bullets) >= 20) {
                break;
            }
        }
        if (!$bullets) {
            continue;
        }

        $html .= '<div class="q-ai-incl-sec" data-sec="' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '">';
        $html .= '<p class="q-ai-incl-sec-title">'
            . '<i class="' . htmlspecialchars($info['icon'], ENT_QUOTES, 'UTF-8') . '" aria-hidden="true"></i> '
            . '<strong>' . htmlspecialchars($info['label'], ENT_QUOTES, 'UTF-8') . '</strong>'
            . '</p>';
        $html .= '<ul>';
        foreach ($bullets as $b) {
            $html .= '<li>' . $b . '</li>';
        }
        $html .= '</ul></div>';
    }

    if ($html === '') {
        return '';
    }

    return '<div data-q-ai-inclusions="1" class="q-ai-incl-doc">' . $html . '</div>';
}

/**
 * @deprecated kept for callers — wraps flat items into "other"
 * @param array<int, mixed> $items
 */
function crmAiInclusionsItemsToHtml(array $items): string
{
    return crmAiInclusionsSectionsToHtml(['other' => $items]);
}

/**
 * Normalize AI/instant section payload into canonical keys.
 *
 * @param array<string, mixed> $sections
 * @return array<string, array<int, string>>
 */
function crmAiNormalizeInclusionsSections(array $sections): array
{
    $aliases = [
        'airfare' => 'airfare',
        'air fare' => 'airfare',
        'flights' => 'airfare',
        'flight' => 'airfare',
        'accommodation' => 'accommodation',
        'hotel' => 'accommodation',
        'hotels' => 'accommodation',
        'stay' => 'accommodation',
        'meals' => 'meals',
        'meal' => 'meals',
        'food' => 'meals',
        'sightseeing' => 'sightseeing',
        'sightseeing & activities' => 'sightseeing',
        'sightseeing and activities' => 'sightseeing',
        'activities' => 'sightseeing',
        'tours' => 'sightseeing',
        'transfers' => 'transfers',
        'transfers & transportation' => 'transfers',
        'transfers and transportation' => 'transfers',
        'transportation' => 'transfers',
        'transport' => 'transfers',
        'other' => 'other',
        'other inclusions' => 'other',
        'misc' => 'other',
        'miscellaneous' => 'other',
    ];

    $out = [
        'airfare' => [],
        'accommodation' => [],
        'meals' => [],
        'sightseeing' => [],
        'transfers' => [],
        'other' => [],
    ];

    foreach ($sections as $key => $items) {
        if (!is_array($items)) {
            continue;
        }
        $normKey = $aliases[strtolower(trim((string) $key))] ?? '';
        if ($normKey === '' || !isset($out[$normKey])) {
            $normKey = 'other';
        }
        foreach ($items as $item) {
            if (is_array($item)) {
                $item = (string) ($item['html'] ?? $item['text'] ?? $item['title'] ?? '');
            }
            $item = trim((string) $item);
            if ($item === '') {
                continue;
            }
            $out[$normKey][] = $item;
        }
    }

    return $out;
}

/**
 * @param array<string, mixed> $context
 */
function crmAiNormalizeInclusionsContext(array $context): array
{
    $guest = is_array($context['guest'] ?? null) ? $context['guest'] : [];
    $tour = is_array($context['tour'] ?? null) ? $context['tour'] : [];
    $flights = is_array($context['flights'] ?? null) ? $context['flights'] : [];
    $hotels = is_array($context['hotels'] ?? null) ? $context['hotels'] : [];
    $itinerary = is_array($context['itinerary'] ?? null) ? $context['itinerary'] : [];
    $notes = trim((string) ($context['notes'] ?? $context['user_notes'] ?? ''));
    $contextText = trim((string) ($context['context_text'] ?? $context['prompt_context'] ?? ''));

    $cleanFlights = [];
    foreach ($flights as $f) {
        if (!is_array($f)) {
            continue;
        }
        $from = trim((string) ($f['from'] ?? ''));
        $to = trim((string) ($f['to'] ?? ''));
        $name = trim((string) ($f['name'] ?? $f['airline'] ?? ''));
        $no = trim((string) ($f['fl_tr_no'] ?? $f['flight_no'] ?? ''));
        if ($from === '' && $to === '' && $name === '' && $no === '') {
            continue;
        }
        $cleanFlights[] = [
            'from' => $from,
            'to' => $to,
            'name' => $name,
            'fl_tr_no' => $no,
            'dep_date' => trim((string) ($f['dep_date'] ?? '')),
            'dep_time' => trim((string) ($f['dep_time'] ?? '')),
            'arr_date' => trim((string) ($f['arr_date'] ?? '')),
            'arr_time' => trim((string) ($f['arr_time'] ?? '')),
            'hand_baggage' => trim((string) ($f['hand_baggage'] ?? '')),
            'checkin_baggage' => trim((string) ($f['checkin_baggage'] ?? '')),
        ];
    }

    $cleanHotels = [];
    foreach ($hotels as $h) {
        if (!is_array($h)) {
            continue;
        }
        $name = trim((string) ($h['name'] ?? $h['hotel_name'] ?? ''));
        $city = trim((string) ($h['city'] ?? ''));
        $nights = (int) ($h['nights'] ?? 0);
        if ($name === '' && $city === '') {
            continue;
        }
        $cleanHotels[] = [
            'name' => $name,
            'city' => $city,
            'nights' => max(0, $nights),
            'room_type' => trim((string) ($h['room_type'] ?? $h['room'] ?? '')),
            'meal_plan' => trim((string) ($h['meal_plan'] ?? $h['meal'] ?? '')),
            'star_category' => trim((string) ($h['star_category'] ?? $h['star'] ?? '')),
        ];
    }

    $cleanDays = [];
    foreach ($itinerary as $i => $day) {
        if (!is_array($day)) {
            continue;
        }
        $title = trim((string) ($day['title'] ?? ''));
        $desc = trim(strip_tags((string) ($day['description'] ?? '')));
        $desc = preg_replace('/\s+/u', ' ', $desc) ?? $desc;
        if ($title === '' && $desc === '') {
            continue;
        }
        $cleanDays[] = [
            'day' => (int) ($day['day'] ?? ($i + 1)),
            'title' => $title !== '' ? $title : ('Day ' . ($i + 1)),
            'description' => mb_substr($desc, 0, 500),
            'overnight' => trim((string) ($day['overnight'] ?? '')),
            'meal' => trim((string) ($day['meal'] ?? $day['meals'] ?? '')),
        ];
    }

    return [
        'guest' => [
            'guest_name' => trim((string) ($guest['guest_name'] ?? $guest['name'] ?? '')),
            'adults' => max(0, (int) ($guest['adults'] ?? $guest['no_of_adults'] ?? 0)),
            'children' => max(0, (int) ($guest['children'] ?? $guest['no_of_children'] ?? 0)),
            'mobile_no' => trim((string) ($guest['mobile_no'] ?? '')),
            'email' => trim((string) ($guest['email'] ?? '')),
        ],
        'tour' => [
            'destination' => trim((string) ($tour['destination'] ?? '')),
            'nights' => max(0, (int) ($tour['nights'] ?? $tour['no_of_nights'] ?? 0)),
            'tentative_date' => trim((string) ($tour['tentative_date'] ?? $tour['start_date'] ?? '')),
            'package_name' => trim((string) ($tour['package_name'] ?? $tour['header_text'] ?? '')),
        ],
        'flights' => $cleanFlights,
        'hotels' => $cleanHotels,
        'itinerary' => $cleanDays,
        'notes' => $notes,
        'context_text' => $contextText,
    ];
}

/**
 * Extract a simple Ex-city label from flight places when round-trip-like.
 *
 * @param array<int, array<string, mixed>> $flights
 */
function crmAiInclusionsAirfareLine(array $flights): string
{
    if (!$flights) {
        return '';
    }

    $places = [];
    foreach ($flights as $f) {
        $from = trim((string) ($f['from'] ?? ''));
        $to = trim((string) ($f['to'] ?? ''));
        if ($from !== '') {
            $places[] = $from;
        }
        if ($to !== '') {
            $places[] = $to;
        }
    }
    if (count($places) < 2) {
        return '';
    }

    $first = $places[0];
    $last = $places[count($places) - 1];
    $cityFrom = preg_replace('/\s*\([^)]*\)\s*$/', '', $first) ?? $first;
    $cityLast = preg_replace('/\s*\([^)]*\)\s*$/', '', $last) ?? $last;
    $cityFrom = trim((string) $cityFrom);
    $cityLast = trim((string) $cityLast);

    $bags = [];
    foreach ($flights as $f) {
        if (!empty($f['checkin_baggage'])) {
            $bags[] = trim((string) $f['checkin_baggage']);
        }
    }
    $bagNote = $bags ? ' <em>(' . htmlspecialchars($bags[0], ENT_QUOTES, 'UTF-8') . ' Check-in Baggage)</em>' : '';

    if ($cityFrom !== '' && strcasecmp($cityFrom, $cityLast) === 0) {
        return '<strong>Return airfare Ex-' . htmlspecialchars($cityFrom, ENT_QUOTES, 'UTF-8') . '.</strong>' . $bagNote;
    }

    // Multi-city / open-jaw: list legs briefly
    $legs = [];
    foreach ($flights as $f) {
        $from = trim((string) ($f['from'] ?? ''));
        $to = trim((string) ($f['to'] ?? ''));
        if ($from === '' || $to === '') {
            continue;
        }
        $legs[] = htmlspecialchars($from, ENT_QUOTES, 'UTF-8') . ' → ' . htmlspecialchars($to, ENT_QUOTES, 'UTF-8');
    }
    if (!$legs) {
        return '';
    }
    return '<strong>Airfare:</strong> ' . implode('; ', $legs) . '.' . $bagNote;
}

/**
 * Deterministic categorized inclusions from booking facts only.
 *
 * @param array<string, mixed> $context
 */
function crmAiInstantInclusions(array $context): array
{
    $ctx = crmAiNormalizeInclusionsContext($context);
    $sections = [
        'airfare' => [],
        'accommodation' => [],
        'meals' => [],
        'sightseeing' => [],
        'transfers' => [],
        'other' => [],
    ];

    $dest = (string) ($ctx['tour']['destination'] ?? '');
    $air = crmAiInclusionsAirfareLine($ctx['flights']);
    if ($air !== '') {
        $sections['airfare'][] = $air;
    }

    $cities = [];
    foreach ($ctx['hotels'] as $h) {
        $city = trim((string) ($h['city'] ?? ''));
        if ($city !== '' && !in_array($city, $cities, true)) {
            $cities[] = $city;
        }
    }
    if ($cities) {
        $place = count($cities) === 1 ? $cities[0] : implode(' / ', $cities);
        $sections['accommodation'][] = 'Hotel accommodation in <strong>'
            . htmlspecialchars($place, ENT_QUOTES, 'UTF-8')
            . '</strong> as per the selected room category and duration.';
    } elseif ($dest !== '') {
        // Only if hotels missing but destination known — still avoid inventing hotel names
        $sections['accommodation'][] = 'Hotel accommodation in <strong>'
            . htmlspecialchars($dest, ENT_QUOTES, 'UTF-8')
            . '</strong> as per the selected room category and duration.';
    }

    // Meals from hotel meal plans + itinerary meal fields only
    $mealPlanCounts = [];
    foreach ($ctx['hotels'] as $h) {
        $plan = strtoupper(trim((string) ($h['meal_plan'] ?? '')));
        $nights = (int) ($h['nights'] ?? 0);
        if ($plan === '' || $nights <= 0) {
            continue;
        }
        $city = trim((string) ($h['city'] ?? $dest));
        $label = $plan;
        if (in_array($plan, ['CP', 'BB'], true)) {
            $label = 'Breakfast';
            $mealPlanCounts[$label . '|' . $city] = ($mealPlanCounts[$label . '|' . $city] ?? 0) + $nights;
        } elseif (in_array($plan, ['MAP', 'HB'], true)) {
            $mealPlanCounts['Breakfast|' . $city] = ($mealPlanCounts['Breakfast|' . $city] ?? 0) + $nights;
            $mealPlanCounts['Dinner|' . $city] = ($mealPlanCounts['Dinner|' . $city] ?? 0) + $nights;
        } elseif (in_array($plan, ['AP', 'FB'], true)) {
            $mealPlanCounts['Breakfast|' . $city] = ($mealPlanCounts['Breakfast|' . $city] ?? 0) + $nights;
            $mealPlanCounts['Lunch|' . $city] = ($mealPlanCounts['Lunch|' . $city] ?? 0) + $nights;
            $mealPlanCounts['Dinner|' . $city] = ($mealPlanCounts['Dinner|' . $city] ?? 0) + $nights;
        } else {
            $mealPlanCounts[$plan . '|' . $city] = ($mealPlanCounts[$plan . '|' . $city] ?? 0) + $nights;
        }
    }
    foreach ($mealPlanCounts as $key => $count) {
        [$type, $city] = array_pad(explode('|', $key, 2), 2, '');
        $qty = str_pad((string) $count, 2, '0', STR_PAD_LEFT);
        $plural = $count === 1 ? $type : ($type . (substr($type, -1) === 's' ? '' : 's'));
        if (preg_match('/breakfast|lunch|dinner/i', $type) && $count !== 1) {
            // Breakfasts / Lunches / Dinners
            if (stripos($type, 'Breakfast') === 0) {
                $plural = 'Breakfasts';
            } elseif (stripos($type, 'Lunch') === 0) {
                $plural = 'Lunches';
            } elseif (stripos($type, 'Dinner') === 0) {
                $plural = 'Dinners';
            }
        }
        $line = $qty . ' ' . $plural;
        if ($city !== '') {
            $line .= ' at ' . $city;
        }
        $sections['meals'][] = $line;
    }
    foreach ($ctx['itinerary'] as $day) {
        $meal = trim((string) ($day['meal'] ?? ''));
        if ($meal === '') {
            continue;
        }
        $title = trim((string) ($day['title'] ?? ''));
        $sections['meals'][] = $meal . ($title !== '' ? (' — ' . $title) : '');
    }

    foreach ($ctx['itinerary'] as $day) {
        $title = trim((string) ($day['title'] ?? ''));
        if ($title === '' || preg_match('/^day\s*\d+\b/i', $title)) {
            continue;
        }
        if (preg_match('/^(arrival|departure|check[\s-]?in|check[\s-]?out|free day|leisure day|travel day)$/i', $title)) {
            continue;
        }
        // Transfers mentioned in title go to transfers section
        if (preg_match('/\btransfer/i', $title)) {
            $sections['transfers'][] = $title;
            continue;
        }
        $sections['sightseeing'][] = '<strong>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</strong>';
    }

    $sections = crmAiNormalizeInclusionsSections($sections);
    $html = crmAiInclusionsSectionsToHtml($sections);

    $flat = [];
    foreach ($sections as $list) {
        foreach ($list as $line) {
            $flat[] = trim(strip_tags((string) $line));
        }
    }

    $missing = [];
    if ($dest === '') {
        $missing[] = 'destination';
    }
    if (!$ctx['flights'] && !$ctx['hotels'] && !$ctx['itinerary']) {
        $missing[] = 'flights/hotels/itinerary details';
    }

    if ($html === '') {
        return [
            'ok' => false,
            'error' => 'Not enough booking data to build inclusions. Add destination, hotels, flights, or itinerary first.',
            'missing' => $missing,
        ];
    }

    $message = '';
    if ($missing) {
        $message = 'Generated from available data only. Missing: ' . implode(', ', $missing) . '.';
    }

    return [
        'ok' => true,
        'source' => 'instant',
        'message' => $message,
        'items' => $flat,
        'sections' => $sections,
        'inclusions_html' => $html,
        'missing' => $missing,
    ];
}

/**
 * @param array<string, mixed> $context
 */
function crmAiBuildInclusionsPrompt(array $context): string
{
    $ctx = crmAiNormalizeInclusionsContext($context);
    $facts = json_encode($ctx, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($facts === false) {
        $facts = '{}';
    }

    $prompt = "You are a professional travel quotation writer for Multizone Travels (India).\n";
    $prompt .= "Task: Generate INCLUSIONS in a categorized quote format.\n\n";
    $prompt .= "OUTPUT FORMAT (JSON only):\n";
    $prompt .= "{\n";
    $prompt .= "  \"sections\": {\n";
    $prompt .= "    \"airfare\": [\"<strong>Return airfare Ex-City.</strong> <em>(20 Kgs Check-in Baggage)</em>\"],\n";
    $prompt .= "    \"accommodation\": [\"Hotel accommodation in <strong>City</strong> as per the selected room category and duration.\"],\n";
    $prompt .= "    \"meals\": [\"01 Breakfast at ...\", \"02 Lunches at ...\"],\n";
    $prompt .= "    \"sightseeing\": [\"<strong>Attraction Name</strong> – short detail\", \"<strong>Tour Name with Lunch</strong>\"],\n";
    $prompt .= "    \"transfers\": [\"Airport → Hotel\", \"<strong>4 hours shopping en route</strong>\", \"Private transportation as mentioned in the itinerary.\"],\n";
    $prompt .= "    \"other\": [\"<strong>DOCUMENT NAME</strong>\"]\n";
    $prompt .= "  },\n";
    $prompt .= "  \"notes\": \"optional note if data incomplete\"\n";
    $prompt .= "}\n\n";
    $prompt .= "SECTION RULES:\n";
    $prompt .= "- Use ONLY these section keys: airfare, accommodation, meals, sightseeing, transfers, other.\n";
    $prompt .= "- Omit any section that has no supporting facts in BOOKING_DATA.\n";
    $prompt .= "- Do NOT invent airport transfers, visas, insurance, tips, shopping hours, restaurants, or sightseeing unless clearly present in the data.\n";
    $prompt .= "- Do NOT invent meals unless meal_plan / meal fields exist.\n";
    $prompt .= "- Airfare: prefer \"Return airfare Ex-{city}.\" when round-trip; add baggage in <em>(...)</em> only if present.\n";
    $prompt .= "- Accommodation: city-level wording like the sample; do not invent hotel brand unless in data.\n";
    $prompt .= "- Sightseeing: bold the attraction/tour name with <strong>...</strong>.\n";
    $prompt .= "- Transfers: use → between points when route is known from itinerary/flights.\n";
    $prompt .= "- Each section value is an array of HTML-ready bullet strings (only <strong> and <em> tags allowed).\n";
    $prompt .= "- Keep bullets concise and professional.\n\n";
    $prompt .= "BOOKING_DATA:\n" . $facts . "\n";

    if ($ctx['context_text'] !== '') {
        $prompt .= "\nEDITOR_CONTEXT (user-editable summary — additional facts only, do not invent):\n";
        $prompt .= $ctx['context_text'] . "\n";
    }
    if ($ctx['notes'] !== '') {
        $prompt .= "\nUSER_NOTES:\n" . $ctx['notes'] . "\n";
    }

    return $prompt;
}

/**
 * @param array<string, mixed> $context
 */
function crmAiSuggestInclusions(array $context): array
{
    $instant = crmAiInstantInclusions($context);
    if (!crmAiUseGemini()) {
        if (!$instant['ok']) {
            return $instant;
        }
        $instant['message'] = trim(
            ($instant['message'] ?? '')
            . (crmAiGeminiApiKey() === ''
                ? ' (Gemini key not configured — used booking facts only.)'
                : ' (Gemini AI disabled in ai_config — used booking facts only. Set use_gemini_ai to true to enable.)')
        );
        return $instant;
    }

    $prompt = crmAiBuildInclusionsPrompt($context);
    $result = crmAiCallGemini($prompt, 'gemini-3.5-flash-lite', 45, crmAiGeminiModels());
    if (!$result['ok']) {
        if (crmAiIsQuotaOrRateError($result['error'] ?? '') && !empty($instant['ok'])) {
            $instant['message'] = 'Gemini busy / quota reached — inclusions built from booking facts only. Click Generate again in a moment.';
            return $instant;
        }
        if (!empty($instant['ok'])) {
            $instant['message'] = 'AI unavailable (' . ($result['error'] ?? 'error') . ') — inclusions built from booking facts only.';
            return $instant;
        }
        return $result;
    }

    $data = is_array($result['data'] ?? null) ? $result['data'] : [];
    $sections = [];
    if (!empty($data['sections']) && is_array($data['sections'])) {
        $sections = $data['sections'];
    } elseif (!empty($data['items']) && is_array($data['items'])) {
        $sections = ['other' => $data['items']];
    } elseif (!empty($data['inclusions']) && is_array($data['inclusions'])) {
        $sections = ['other' => $data['inclusions']];
    }

    $sections = crmAiNormalizeInclusionsSections($sections);
    $html = crmAiInclusionsSectionsToHtml($sections);
    if ($html === '') {
        if (!empty($instant['ok'])) {
            $instant['message'] = 'AI returned empty inclusions — used booking facts instead.';
            return $instant;
        }
        return ['ok' => false, 'error' => 'AI returned an empty inclusions list.'];
    }

    $flat = [];
    foreach ($sections as $list) {
        foreach ($list as $line) {
            $t = trim(strip_tags((string) $line));
            if ($t !== '') {
                $flat[] = $t;
            }
        }
    }

    return [
        'ok' => true,
        'source' => 'ai',
        'message' => trim((string) ($data['notes'] ?? '')),
        'items' => $flat,
        'sections' => $sections,
        'inclusions_html' => $html,
        'missing' => $instant['missing'] ?? [],
    ];
}
