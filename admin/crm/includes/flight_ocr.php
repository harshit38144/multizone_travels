<?php
/**
 * Flight / Train Details: read a ticket / booking screenshot and return segments for review.
 * Uses the same Gemini vision call as hotel / traveller OCR. The image is not stored.
 */

require_once __DIR__ . '/traveller_ocr.php';

function focrAvailable(): bool
{
    return crmAiUseGemini();
}

/** @return list<string> */
function focrAllowedExtensions(): array
{
    return ['jpg', 'jpeg', 'png', 'webp'];
}

/** @return list<string> */
function focrAllowedMimes(): array
{
    return ['image/jpeg', 'image/png', 'image/webp'];
}

function focrMaxBytes(): int
{
    return 8 * 1024 * 1024;
}

function focrPrompt(): string
{
    $p = "You are an OCR engine for an Indian travel agency quotation form.\n";
    $p .= "Read the attached flight ticket, train ticket, booking confirmation, PNR screenshot, or fare quote.\n";
    $p .= "Extract every travel segment that is clearly printed. One object per flight/train leg.\n";
    $p .= "Connections are separate segments in travel order.\n";
    $p .= "\nReturn ONLY JSON with this shape. Use \"\" when a value is not clearly printed:\n";
    $p .= "{\n";
    $p .= "  \"is_travel_ticket\": true,\n";
    $p .= "  \"mode\": \"flight | train | unknown\",\n";
    $p .= "  \"segments\": [\n";
    $p .= "    {\n";
    $p .= "      \"from_city\": \"\",\n";
    $p .= "      \"from_code\": \"\",\n";
    $p .= "      \"to_city\": \"\",\n";
    $p .= "      \"to_code\": \"\",\n";
    $p .= "      \"carrier_name\": \"airline or train name\",\n";
    $p .= "      \"number\": \"flight no e.g. 6E-2373 or train no e.g. 12951\",\n";
    $p .= "      \"departure_date\": \"YYYY-MM-DD\",\n";
    $p .= "      \"departure_time\": \"HH:MM\",\n";
    $p .= "      \"arrival_date\": \"YYYY-MM-DD\",\n";
    $p .= "      \"arrival_time\": \"HH:MM\",\n";
    $p .= "      \"rate\": \"\",\n";
    $p .= "      \"supplier\": \"\",\n";
    $p .= "      \"hand_baggage\": \"e.g. 7 Kg\",\n";
    $p .= "      \"checkin_baggage\": \"e.g. 15 Kg\"\n";
    $p .= "    }\n";
    $p .= "  ]\n";
    $p .= "}\n\n";
    $p .= "Rules:\n";
    $p .= "- Never guess, invent, or fill a typical value. Copy only what is clearly visible.\n";
    $p .= "- Airport/station codes: 3-letter IATA for flights (DEL, BOM); station codes for trains when printed.\n";
    $p .= "- Put city name in from_city / to_city without the code. Put the code in from_code / to_code.\n";
    $p .= "- Times must be 24-hour HH:MM. Dates must be YYYY-MM-DD. If the year is not printed, use \"\".\n";
    $p .= "- rate is a single number without currency symbols. Prefer total fare if shown once for the whole ticket; put it only on the first segment and leave \"\" on others when unclear.\n";
    $p .= "- supplier is the agency / GDS / portal / DMC name only when printed. Otherwise \"\".\n";
    $p .= "- hand_baggage / checkin_baggage: copy printed cabin and check-in allowance (e.g. '7 Kg', '15 Kg'). Otherwise \"\".\n";
    $p .= "- For trains, carrier_name is the train name when printed; number is the train number.\n";
    $p .= "- If the image is not a flight/train ticket or booking screenshot, set is_travel_ticket to false and segments to [].\n";
    return $p;
}

function focrTime($v): string
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

function focrCode($v): string
{
    $v = strtoupper(tocrCleanText($v, 20));
    if (preg_match('/\(([A-Z0-9]{2,5})\)/', $v, $m)) {
        return $m[1];
    }
    $v = preg_replace('/[^A-Z0-9]/', '', $v) ?? '';
    return (strlen($v) >= 2 && strlen($v) <= 5) ? $v : '';
}

function focrCity($v): string
{
    $v = tocrCleanText($v, 80);
    $v = preg_replace('/\s*\([^)]*\)\s*$/', '', $v) ?? $v;
    $v = trim(preg_replace('/\b(international|domestic)?\s*airport\b/i', '', $v) ?? $v);
    $v = trim(preg_replace('/\b(railway|rail)?\s*station\b/i', '', $v) ?? $v);
    return tocrNiceName($v);
}

function focrPlace(string $city, string $code): string
{
    $city = trim($city);
    $code = strtoupper(trim($code));
    if ($city !== '' && $code !== '') {
        return $city . ' (' . $code . ')';
    }
    if ($city !== '') {
        return $city;
    }
    if ($code !== '') {
        return $code;
    }
    return '';
}

function focrRate($v): string
{
    $v = tocrCleanText($v, 40);
    if ($v === '' || preg_match('/\d\s*[-–to]+\s*\d/i', $v)) {
        return '';
    }
    $v = str_replace([',', '₹', 'Rs.', 'Rs', 'INR', '/-'], '', $v);
    if (!preg_match('/\d+(?:\.\d+)?/', $v, $m)) {
        return '';
    }
    $n = (float) $m[0];
    if ($n <= 0 || $n > 10000000) {
        return '';
    }
    return (string) (int) round($n);
}

function focrBaggage($v): string
{
    $v = tocrCleanText($v, 40);
    if ($v === '') {
        return '';
    }
    if (preg_match('/not\s*select|n\/?a|nil|none/i', $v)) {
        return '';
    }
    return $v;
}

function focrFlightNumber($v): string
{
    $v = strtoupper(tocrCleanText($v, 24));
    $v = preg_replace('/\s+/', '', $v) ?? $v;
    if ($v === '') {
        return '';
    }
    if (preg_match('/^([A-Z]{2}|[A-Z][0-9]|[0-9][A-Z])-?(\d{1,5})$/', $v, $m)) {
        return $m[1] . '-' . $m[2];
    }
    if (preg_match('/^\d{1,6}$/', $v)) {
        return $v;
    }
    return preg_match('/^[A-Z0-9\-]{2,12}$/', $v) ? $v : '';
}

/**
 * @param array<string,mixed> $raw
 * @return array<string,string>|null
 */
function focrRow(array $raw): ?array
{
    $fromCity = focrCity((string) ($raw['from_city'] ?? ($raw['from'] ?? '')));
    $fromCode = focrCode($raw['from_code'] ?? '');
    if ($fromCode === '' && isset($raw['from'])) {
        $fromCode = focrCode($raw['from']);
    }
    $toCity = focrCity((string) ($raw['to_city'] ?? ($raw['to'] ?? '')));
    $toCode = focrCode($raw['to_code'] ?? '');
    if ($toCode === '' && isset($raw['to'])) {
        $toCode = focrCode($raw['to']);
    }

    $carrier = tocrNiceName((string) ($raw['carrier_name'] ?? ($raw['airline'] ?? ($raw['name'] ?? ''))));
    $number = focrFlightNumber($raw['number'] ?? ($raw['flight_number'] ?? ($raw['train_number'] ?? ($raw['fl_tr_no'] ?? ''))));
    $depDate = tocrDate($raw['departure_date'] ?? ($raw['dep_date'] ?? ''));
    $depTime = focrTime($raw['departure_time'] ?? ($raw['dep_time'] ?? ''));
    $arrDate = tocrDate($raw['arrival_date'] ?? ($raw['arr_date'] ?? ''));
    $arrTime = focrTime($raw['arrival_time'] ?? ($raw['arr_time'] ?? ''));
    if ($arrDate === '' && $depDate !== '') {
        $arrDate = $depDate;
    }
    $rate = focrRate($raw['rate'] ?? ($raw['fare'] ?? ($raw['amount'] ?? '')));
    $supplier = tocrNiceName((string) ($raw['supplier'] ?? ''));
    $hand = focrBaggage($raw['hand_baggage'] ?? ($raw['cabin_baggage'] ?? ''));
    $checkin = focrBaggage($raw['checkin_baggage'] ?? ($raw['check_in_baggage'] ?? ''));

    $from = focrPlace($fromCity, $fromCode);
    $to = focrPlace($toCity, $toCode);

    if ($from === '' && $to === '' && $carrier === '' && $number === '' && $depDate === '' && $rate === '') {
        return null;
    }

    return [
        'from' => $from,
        'to' => $to,
        'name' => $carrier,
        'fl_tr_no' => $number,
        'dep_date' => $depDate,
        'dep_time' => $depTime,
        'arr_date' => $arrDate,
        'arr_time' => $arrTime,
        'fare' => $rate,
        'supplier' => $supplier,
        'hand_baggage' => $hand,
        'checkin_baggage' => $checkin,
    ];
}

/**
 * @param array<string,mixed> $raw
 * @return array{is_travel_ticket: bool, mode: string, segments: list<array<string,string>>}
 */
function focrNormalize(array $raw): array
{
    $rows = [];
    $list = $raw['segments'] ?? [];
    if (!is_array($list) || !$list) {
        // Accept e-ticket style payloads as a fallback.
        foreach (['onward_segments', 'return_segments', 'flights'] as $key) {
            if (!empty($raw[$key]) && is_array($raw[$key])) {
                $list = array_merge(is_array($list) ? $list : [], $raw[$key]);
            }
        }
    }
    if (is_array($list)) {
        foreach ($list as $item) {
            if (!is_array($item)) {
                continue;
            }
            $row = focrRow($item);
            if ($row !== null) {
                $rows[] = $row;
            }
            if (count($rows) >= 12) {
                break;
            }
        }
    }

    $flag = $raw['is_travel_ticket'] ?? ($raw['is_eticket'] ?? true);
    $isTicket = !($flag === false || $flag === 0 || $flag === 'false' || $flag === '0');
    if (!$isTicket) {
        $rows = [];
    }

    $mode = strtolower(tocrCleanText($raw['mode'] ?? '', 20));
    if (!in_array($mode, ['flight', 'train', 'unknown'], true)) {
        $mode = 'unknown';
    }

    return [
        'is_travel_ticket' => $isTicket,
        'mode' => $mode,
        'segments' => $rows,
    ];
}

/**
 * @return array{ok: bool, error?: string, result?: array<string,mixed>, model?: string}
 */
function focrExtract(string $absPath, string $mime): array
{
    if (!focrAvailable()) {
        return ['ok' => false, 'error' => 'AI image reading is not enabled. Configure the Gemini key in crm/includes/ai_config.local.php.'];
    }
    $bytes = @file_get_contents($absPath);
    if ($bytes === false || $bytes === '') {
        return ['ok' => false, 'error' => 'Could not read the uploaded image.'];
    }
    $base64 = base64_encode($bytes);
    $prompt = focrPrompt();
    $lastError = 'The ticket image could not be read.';
    foreach (array_slice(crmAiGeminiModels(), 0, 3) as $model) {
        $res = tocrCallGeminiVisionOnce($prompt, $mime, $base64, $model, 70);
        if (!empty($res['ok']) && is_array($res['data'])) {
            return ['ok' => true, 'result' => focrNormalize($res['data']), 'model' => (string) $model];
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
        $lastError = 'The AI service is busy right now. Please try again in a minute or fill the flight rows manually.';
    }
    return ['ok' => false, 'error' => $lastError];
}
