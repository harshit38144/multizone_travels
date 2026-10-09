<?php
/**
 * E-Ticket AI auto-fill: read an airline e-ticket (photo, screenshot, scan or PDF) with the
 * configured Gemini vision model and map it onto the E-Ticket form fields.
 *
 * Shares the Gemini transport + value cleaners with the Confirm Tour ID reader
 * (crm/includes/traveller_ocr.php) so both features behave the same way.
 */

require_once __DIR__ . '/../crm/includes/traveller_ocr.php';

function etocrAvailable(): bool
{
    return crmAiUseGemini();
}

/** Extensions accepted for an e-ticket upload. */
function etocrAllowedExtensions(): array
{
    return ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
}

function etocrAllowedMimes(): array
{
    return ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
}

function etocrMaxBytes(): int
{
    return 8 * 1024 * 1024;
}

function etocrPrompt(): string
{
    $p = "You are an OCR and data-extraction engine for an Indian travel agency that re-issues airline e-tickets.\n";
    $p .= "Read the attached flight e-ticket (photo, screenshot, scanned page or PDF) and extract every detail printed on it.\n";
    $p .= "\nReturn ONLY JSON with exactly this shape (use \"\" for any value that is not clearly printed):\n";
    $p .= "{\n";
    $p .= "  \"is_eticket\": true,\n";
    $p .= "  \"pnr\": \"\",\n";
    $p .= "  \"booking_date\": \"YYYY-MM-DD\",\n";
    $p .= "  \"trip_type\": \"oneway | roundtrip\",\n";
    $p .= "  \"journey_type\": \"domestic | international\",\n";
    $p .= "  \"airline\": \"\", \"airline_code\": \"\",\n";
    $p .= "  \"contact_mobile\": \"\", \"contact_email\": \"\",\n";
    $p .= "  \"currency\": \"\", \"base_fare\": \"\", \"taxes\": \"\", \"total_fare\": \"\",\n";
    $p .= "  \"passengers\": [\n";
    $p .= "    {\"title\": \"Mr | Mrs | Ms | Mstr | Miss\", \"name\": \"given + surname without the title\",\n";
    $p .= "     \"type\": \"Adult | Child | Infant\", \"ticket_number\": \"\", \"seat\": \"\", \"meal\": \"\", \"services\": \"\"}\n";
    $p .= "  ],\n";
    $p .= "  \"onward_segments\": [\n";
    $p .= "    {\"airline\": \"\", \"airline_code\": \"\", \"flight_number\": \"\",\n";
    $p .= "     \"from_city\": \"\", \"from_code\": \"\", \"from_terminal\": \"\",\n";
    $p .= "     \"to_city\": \"\", \"to_code\": \"\", \"to_terminal\": \"\",\n";
    $p .= "     \"departure_date\": \"YYYY-MM-DD\", \"departure_time\": \"HH:MM\",\n";
    $p .= "     \"arrival_date\": \"YYYY-MM-DD\", \"arrival_time\": \"HH:MM\",\n";
    $p .= "     \"duration\": \"e.g. 2 hrs 10 min\", \"hand_baggage\": \"e.g. 7 Kg\", \"checkin_baggage\": \"e.g. 15 Kg\"}\n";
    $p .= "  ],\n";
    $p .= "  \"return_segments\": [ same shape as onward_segments ],\n";
    $p .= "  \"confidence\": \"high | medium | low\"\n";
    $p .= "}\n\n";
    $p .= "Rules:\n";
    $p .= "- Never guess or invent a value. If something is unclear, cut off or absent, return \"\" for it.\n";
    $p .= "- Airport codes are the 3-letter IATA codes (DEL, BOM, IXR). Put the city name in from_city / to_city without the code.\n";
    $p .= "- Terminals: digits or short labels only ('1', '2', '1D') — do not include the word Terminal.\n";
    $p .= "- Flight number as printed, e.g. '6E-2373' or 'AI 809'. airline_code is the 2-character carrier code.\n";
    $p .= "- duration is the flying time printed on that leg, such as '5h 35m' or '2 hrs 10 min'. Copy the printed value. Do not calculate it from the departure and arrival clocks — those are local times and differ across time zones on international flights.\n";
    $p .= "- List each flight leg separately and in travel order. Connections are separate segments, not one leg.\n";
    $p .= "- Put legs that fly back towards the first origin into return_segments; set trip_type to roundtrip only then.\n";
    $p .= "- ticket_number is the airline ticket or e-ticket number only. If it is the same as the PNR, or it is not printed, return \"\".\n";
    $p .= "- seat, meal and services: if the ticket prints a placeholder such as \"Not selected\", \"Not select\" or \"NOT SELECTED\", return \"\" for that field.\n";
    $p .= "- Passenger names: exclude the title from name and put the title in the title field. Keep the ticket's spelling.\n";
    $p .= "- One passengers entry per traveller printed on the ticket, in the printed order.\n";
    $p .= "- Fares: digits only, no currency symbol, no thousands separators. Leave \"\" if the fare is hidden or absent.\n";
    $p .= "- booking_date is the issue / booking date, not the travel date.\n";
    $p .= "- If the file is not a flight e-ticket, set is_eticket to false and leave every other field empty.\n";
    return $p;
}

/** 24-hour HH:MM from many printed forms ('09:10 PM', '2110', '21.10'). */
function etocrTime($v): string
{
    $v = strtoupper(tocrCleanText($v, 20));
    if ($v === '') {
        return '';
    }
    if (preg_match('/^(\d{1,2})[:.\s]?(\d{2})\s*(AM|PM)$/', $v, $m)) {
        $h = (int) $m[1] % 12;
        if ($m[3] === 'PM') {
            $h += 12;
        }
        return sprintf('%02d:%02d', $h, (int) $m[2]);
    }
    if (preg_match('/^(\d{1,2})[:.](\d{2})/', $v, $m)) {
        $h = (int) $m[1];
        $i = (int) $m[2];
        return ($h <= 23 && $i <= 59) ? sprintf('%02d:%02d', $h, $i) : '';
    }
    if (preg_match('/^(\d{2})(\d{2})$/', $v, $m)) {
        $h = (int) $m[1];
        $i = (int) $m[2];
        return ($h <= 23 && $i <= 59) ? sprintf('%02d:%02d', $h, $i) : '';
    }
    return '';
}

function etocrAirportCode($v): string
{
    $v = strtoupper(tocrCleanText($v, 20));
    if (preg_match('/\(([A-Z]{3})\)/', $v, $m)) {
        return $m[1];
    }
    $v = preg_replace('/[^A-Z]/', '', $v) ?? '';
    return strlen($v) === 3 ? $v : '';
}

/** City name without the trailing airport code. */
function etocrCity($v): string
{
    $v = tocrCleanText($v, 80);
    $v = preg_replace('/\s*\([^)]*\)\s*$/', '', $v) ?? $v;
    $v = trim(preg_replace('/\b(international|domestic)?\s*airport\b/i', '', $v) ?? $v);
    return tocrNiceName($v);
}

function etocrTerminal($v): string
{
    $v = strtoupper(tocrCleanText($v, 20));
    $v = trim(preg_replace('/\bTERMINAL\b|\bT\s*(?=\d)/', '', $v) ?? $v);
    return preg_match('/^[A-Z0-9 \-]{1,6}$/', $v) ? $v : '';
}

/** Whole-rupee amount from a printed fare ('â‚¹ 13,294.00' -> '13294'). */
function etocrAmount($v): string
{
    $v = tocrCleanText($v, 30);
    $v = preg_replace('/[^0-9.]/', '', $v) ?? '';
    if ($v === '' || !is_numeric($v)) {
        return '';
    }
    $n = (float) $v;
    return $n > 0 ? (string) (int) round($n) : '';
}

/** IATA airline codes are 2 characters and always contain a letter (AI, IX, 6E, W2). */
function etocrCarrierCode($v): string
{
    $v = strtoupper(tocrCleanText($v, 10));
    $v = preg_replace('/[^A-Z0-9]/', '', $v) ?? '';
    return preg_match('/^(?:[A-Z]{2}|[A-Z][0-9]|[0-9][A-Z])$/', $v) ? $v : '';
}

function etocrFlightNumber($v, string $carrier): string
{
    $v = strtoupper(tocrCleanText($v, 20));
    $v = preg_replace('/\s+/', '', $v) ?? $v;
    if ($v === '') {
        return '';
    }
    // Digits only — never split them into a carrier code ('6902' is not '69' + '02').
    if (preg_match('/^\d{1,5}$/', $v)) {
        return $carrier !== '' ? ($carrier . '-' . $v) : $v;
    }
    if (preg_match('/^([A-Z]{2}|[A-Z][0-9]|[0-9][A-Z])-?(\d{1,5})$/', $v, $m)) {
        return $m[1] . '-' . $m[2];
    }
    return preg_match('/^[A-Z0-9\-]{2,10}$/', $v) ? $v : '';
}

function etocrPassengerTitle($v): string
{
    $v = strtolower(tocrCleanText($v, 12));
    $v = rtrim($v, '.');
    $map = ['mr' => 'Mr', 'mister' => 'Mr', 'mrs' => 'Mrs', 'ms' => 'Ms', 'miss' => 'Miss', 'mstr' => 'Mstr', 'master' => 'Mstr'];
    return $map[$v] ?? '';
}

function etocrPassengerType($v): string
{
    $v = strtolower(tocrCleanText($v, 20));
    if ($v === '') {
        return '';
    }
    if (strpos($v, 'inf') !== false) {
        return 'Infant';
    }
    if (strpos($v, 'chd') !== false || strpos($v, 'child') !== false) {
        return 'Child';
    }
    if (strpos($v, 'adt') !== false || strpos($v, 'adult') !== false) {
        return 'Adult';
    }
    return '';
}

/** True for printed placeholders such as "Not selected" or "NOT SELECT / NOT SELECTED". */
function etocrIsUnsetLabel(string $v): bool
{
    $norm = preg_replace('/[^a-z]+/', '', strtolower($v)) ?? '';
    if ($norm === '') {
        return false;
    }
    return (bool) preg_match('/^(?:notselect(?:ed)?|unselected|noselection|notassigned|notavailable)+$/', $norm);
}

function etocrOptionalCode($v, int $max): string
{
    $raw = (string) $v;
    if (etocrIsUnsetLabel($raw)) {
        return '';
    }
    return etocrCode($raw, $max);
}
function etocrCode($v, int $max = 30): string
{
    $v = strtoupper(tocrCleanText($v, $max));
    return preg_replace('/\s+/', ' ', $v) ?? $v;
}

/**
 * @return array<string, string>|null
 */
function etocrSegment($raw): ?array
{
    if (!is_array($raw)) {
        return null;
    }
    $carrier = etocrCarrierCode($raw['airline_code'] ?? '');
    $flightNo = etocrFlightNumber($raw['flight_number'] ?? '', $carrier);
    if ($carrier === '' && preg_match('/^([A-Z0-9]{2})-/', $flightNo, $m)) {
        $carrier = $m[1];
    }
    $seg = [
        'airline' => tocrNiceName((string) ($raw['airline'] ?? '')),
        'carrier_code' => $carrier,
        'flight_number' => $flightNo,
        'from_city' => etocrCity($raw['from_city'] ?? ''),
        'from_code' => etocrAirportCode($raw['from_code'] ?? ($raw['from_city'] ?? '')),
        'from_terminal' => etocrTerminal($raw['from_terminal'] ?? ''),
        'to_city' => etocrCity($raw['to_city'] ?? ''),
        'to_code' => etocrAirportCode($raw['to_code'] ?? ($raw['to_city'] ?? '')),
        'to_terminal' => etocrTerminal($raw['to_terminal'] ?? ''),
        'dep_date' => tocrDate($raw['departure_date'] ?? ''),
        'dep_time' => etocrTime($raw['departure_time'] ?? ''),
        'arr_date' => tocrDate($raw['arrival_date'] ?? ''),
        'arr_time' => etocrTime($raw['arrival_time'] ?? ''),
        'duration' => tocrCleanText($raw['duration'] ?? '', 30),
        'hand_baggage' => tocrCleanText($raw['hand_baggage'] ?? '', 30),
        'checkin_baggage' => tocrCleanText($raw['checkin_baggage'] ?? '', 30),
    ];
    // A leg is only usable when at least the route or the flight number was read.
    $hasRoute = $seg['from_code'] !== '' || $seg['to_code'] !== '' || $seg['from_city'] !== '' || $seg['to_city'] !== '';
    if (!$hasRoute && $seg['flight_number'] === '') {
        return null;
    }
    return $seg;
}

/**
 * @return list<array<string, string>>
 */
function etocrSegments($raw): array
{
    $out = [];
    if (!is_array($raw)) {
        return $out;
    }
    foreach ($raw as $item) {
        $seg = etocrSegment($item);
        if ($seg) {
            $out[] = $seg;
        }
        if (count($out) >= 8) {
            break;
        }
    }
    return $out;
}

/**
 * @return list<array<string, string>>
 */
function etocrPassengers($raw): array
{
    $out = [];
    if (!is_array($raw)) {
        return $out;
    }
    foreach ($raw as $item) {
        if (!is_array($item)) {
            continue;
        }
        $name = tocrNiceName((string) ($item['name'] ?? ''));
        $title = etocrPassengerTitle($item['title'] ?? '');
        if ($title === '' && $name !== '' && preg_match('/^(mr|mrs|ms|miss|mstr|master)\.?\s+(.+)$/i', $name, $m)) {
            $title = etocrPassengerTitle($m[1]);
            $name = tocrNiceName($m[2]);
        }
        $pax = [
            'title' => $title,
            'name' => $name,
            'type' => etocrPassengerType($item['type'] ?? ''),
            'ticket' => etocrOptionalCode($item['ticket_number'] ?? '', 30),
            'seat' => etocrOptionalCode($item['seat'] ?? '', 10),
            'meal' => etocrOptionalCode($item['meal'] ?? '', 20),
            'services' => etocrOptionalCode($item['services'] ?? '', 30),
        ];
        if ($pax['name'] === '' && $pax['ticket'] === '' && $pax['seat'] === '') {
            continue;
        }
        $out[] = $pax;
        if (count($out) >= 20) {
            break;
        }
    }
    return $out;
}

function etocrMobile($v): string
{
    $v = tocrCleanText($v, 30);
    $digits = preg_replace('/\D/', '', $v) ?? '';
    if (strlen($digits) > 10) {
        $digits = substr($digits, -10);
    }
    return strlen($digits) === 10 ? $digits : '';
}

function etocrEmail($v): string
{
    $v = strtolower(tocrCleanText($v, 190));
    return filter_var($v, FILTER_VALIDATE_EMAIL) ? $v : '';
}

/**
 * Normalise the raw AI JSON into the shape the E-Ticket form consumes.
 *
 * @return array<string, mixed>
 */
function etocrNormalize(array $raw): array
{
    $onward = etocrSegments($raw['onward_segments'] ?? []);
    $return = etocrSegments($raw['return_segments'] ?? []);
    $passengers = etocrPassengers($raw['passengers'] ?? []);

    $tripType = strtolower(tocrCleanText($raw['trip_type'] ?? '', 20));
    $tripType = $return ? 'roundtrip' : (in_array($tripType, ['oneway', 'roundtrip'], true) ? $tripType : '');
    if ($tripType === 'roundtrip' && !$return) {
        $tripType = 'oneway';
    }

    $journeyType = strtolower(tocrCleanText($raw['journey_type'] ?? '', 20));
    $journeyType = in_array($journeyType, ['domestic', 'international'], true) ? $journeyType : '';

    $firstOnward = $onward[0] ?? null;
    $lastOnward = $onward ? $onward[count($onward) - 1] : null;

    $airline = tocrNiceName((string) ($raw['airline'] ?? ''));
    if ($airline === '' && $firstOnward) {
        $airline = $firstOnward['airline'];
    }
    $carrier = etocrCarrierCode($raw['airline_code'] ?? '');
    if ($carrier === '' && $firstOnward) {
        $carrier = $firstOnward['carrier_code'];
    }

    $base = etocrAmount($raw['base_fare'] ?? '');
    $tax = etocrAmount($raw['taxes'] ?? '');
    $total = etocrAmount($raw['total_fare'] ?? '');
    if ($total === '' && $base !== '' && $tax !== '') {
        $total = (string) ((int) $base + (int) $tax);
    }
    if ($tax === '' && $total !== '' && $base !== '' && (int) $total >= (int) $base) {
        $tax = (string) ((int) $total - (int) $base);
    }

    $fields = [
        'pnr' => etocrCode($raw['pnr'] ?? '', 20),
        'booking_date' => tocrDate($raw['booking_date'] ?? ''),
        'trip_type' => $tripType,
        'journey_type' => $journeyType,
        'airline' => $airline,
        'carrier_code' => $carrier,
        'from_code' => $firstOnward ? $firstOnward['from_code'] : '',
        'from_city' => $firstOnward ? $firstOnward['from_city'] : '',
        'to_code' => $lastOnward ? $lastOnward['to_code'] : '',
        'to_city' => $lastOnward ? $lastOnward['to_city'] : '',
        'onward_date' => $firstOnward ? $firstOnward['dep_date'] : '',
        'return_date' => $return ? $return[0]['dep_date'] : '',
        'pax_count' => $passengers ? (string) count($passengers) : '',
        'mobile' => etocrMobile($raw['contact_mobile'] ?? ''),
        'email' => etocrEmail($raw['contact_email'] ?? ''),
        'base_fare' => $base,
        'tax' => $tax,
        'total_fare' => $total,
    ];

    $pnrKey = strtoupper(preg_replace('/\s+/', '', $fields['pnr']) ?? '');
    if ($pnrKey !== '') {
        foreach ($passengers as $i => $pax) {
            $ticketKey = strtoupper(preg_replace('/\s+/', '', (string) ($pax['ticket'] ?? '')) ?? '');
            if ($ticketKey !== '' && $ticketKey === $pnrKey) {
                $passengers[$i]['ticket'] = '';
            }
        }
    }

    $filled = array_keys(array_filter($fields, static function ($v) {
        return (string) $v !== '';
    }));
    // Trip / journey type alone is not a successful read — it is derived, not copied off the ticket.
    if (!$onward) {
        $filled = array_values(array_diff($filled, ['trip_type', 'journey_type']));
    }
    if ($passengers) {
        $filled[] = 'passengers';
    }
    if ($onward) {
        $filled[] = 'onward_segments';
    }
    if ($return) {
        $filled[] = 'return_segments';
    }

    $confidence = strtolower(tocrCleanText($raw['confidence'] ?? '', 10));

    return [
        'is_eticket' => !isset($raw['is_eticket']) || !empty($raw['is_eticket']),
        'confidence' => in_array($confidence, ['high', 'medium', 'low'], true) ? $confidence : 'medium',
        'fields' => $fields,
        'passengers' => $passengers,
        'onward_segments' => $onward,
        'return_segments' => $return,
        'filled' => array_values(array_unique($filled)),
    ];
}

/**
 * @return array{ok: bool, error?: string, result?: array<string, mixed>, model?: string}
 */
function etocrExtract(string $absPath, string $mime): array
{
    if (!etocrAvailable()) {
        return ['ok' => false, 'error' => 'AI e-ticket reading is not enabled. Configure the Gemini key in crm/includes/ai_config.local.php.'];
    }
    $bytes = @file_get_contents($absPath);
    if ($bytes === false || $bytes === '') {
        return ['ok' => false, 'error' => 'Could not read the uploaded file.'];
    }
    $base64 = base64_encode($bytes);
    $prompt = etocrPrompt();

    $lastError = 'The e-ticket could not be read.';
    foreach (array_slice(crmAiGeminiModels(), 0, 3) as $model) {
        $res = tocrCallGeminiVisionOnce($prompt, $mime, $base64, $model, 60);
        if (!empty($res['ok'])) {
            return ['ok' => true, 'result' => etocrNormalize($res['data']), 'model' => $model];
        }
        $lastError = (string) ($res['error'] ?? $lastError);
        $code = (int) ($res['http_code'] ?? 0);
        if (in_array($code, [401, 403], true)) {
            break;
        }
        if (!crmAiIsTransientGeminiError($lastError) && !in_array($code, [404, 429, 500, 503], true)) {
            break;
        }
    }
    if (crmAiIsQuotaOrRateError($lastError)) {
        $lastError = 'The AI service is busy right now. Please try again in a minute or fill the details manually.';
    }
    return ['ok' => false, 'error' => $lastError];
}
