<?php
/**
 * Hotel Details: read a quotation screenshot and return rows for review.
 * Uses the same Gemini vision call as traveller / e-ticket OCR. The image is not stored.
 */

require_once __DIR__ . '/traveller_ocr.php';

function hocrAvailable(): bool
{
    return crmAiUseGemini();
}

/** @return list<string> */
function hocrAllowedExtensions(): array
{
    return ['jpg', 'jpeg', 'png', 'webp'];
}

/** @return list<string> */
function hocrAllowedMimes(): array
{
    return ['image/jpeg', 'image/png', 'image/webp'];
}

function hocrMaxBytes(): int
{
    return 8 * 1024 * 1024;
}

function hocrPrompt(): string
{
    $p = "You are an OCR engine for an Indian travel agency quotation form.\n";
    $p .= "Read the attached hotel quotation, voucher, or rate screenshot.\n";
    $p .= "Extract every hotel stay that is clearly printed. One object per hotel and room line.\n";
    $p .= "If the same hotel has two different room types, return two objects.\n";
    $p .= "\nReturn ONLY JSON with this shape. Use \"\" when a value is not clearly printed:\n";
    $p .= "{\n";
    $p .= "  \"is_hotel_quotation\": true,\n";
    $p .= "  \"hotels\": [\n";
    $p .= "    {\n";
    $p .= "      \"city\": \"\",\n";
    $p .= "      \"hotel_name\": \"\",\n";
    $p .= "      \"room_type\": \"\",\n";
    $p .= "      \"rooms\": \"\",\n";
    $p .= "      \"meal_plan\": \"\",\n";
    $p .= "      \"nights\": \"\",\n";
    $p .= "      \"check_in\": \"YYYY-MM-DD\",\n";
    $p .= "      \"check_out\": \"YYYY-MM-DD\",\n";
    $p .= "      \"rate\": \"\",\n";
    $p .= "      \"supplier\": \"\"\n";
    $p .= "    }\n";
    $p .= "  ]\n";
    $p .= "}\n\n";
    $p .= "Rules:\n";
    $p .= "- Never guess, invent, or fill a typical value. Copy only what is clearly visible.\n";
    $p .= "- meal_plan must be exactly EP, CP, MAP, or AP when that code or its full plan name is printed (European Plan, Continental Plan, Modified American Plan, American Plan, Room Only, Half Board, Full Board, All Inclusive). Otherwise use \"\".\n";
    $p .= "- rooms and nights must be whole numbers printed on the image, or \"\".\n";
    $p .= "- Dates must be YYYY-MM-DD. If the year is not printed, use \"\".\n";
    $p .= "- rate is a single number without currency symbols. If several rates are shown and it is unclear which one applies to the row, use \"\".\n";
    $p .= "- supplier is the hotel supplier or DMC name only when it is printed. Otherwise \"\".\n";
    $p .= "- If the image is not a hotel quotation, set is_hotel_quotation to false and hotels to [].\n";
    return $p;
}

function hocrMeal($v): string
{
    $raw = strtoupper(tocrCleanText($v, 60));
    if ($raw === '') {
        return '';
    }
    $phrases = [
        'EUROPEAN PLAN' => 'EP',
        'ROOM ONLY' => 'EP',
        'CONTINENTAL PLAN' => 'CP',
        'MODIFIED AMERICAN PLAN' => 'MAP',
        'HALF BOARD' => 'MAP',
        'AMERICAN PLAN' => 'AP',
        'FULL BOARD' => 'AP',
        'ALL INCLUSIVE' => 'AP',
    ];
    if (isset($phrases[$raw])) {
        return $phrases[$raw];
    }
    if (preg_match_all('/\b(EP|CP|MAP|AP)\b/', $raw, $found)) {
        $uniq = array_values(array_unique($found[1]));
        if (count($uniq) === 1) {
            return $uniq[0];
        }
    }
    return '';
}

function hocrInt($v, int $max): string
{
    $v = tocrCleanText($v, 12);
    if ($v === '' || !preg_match('/^\d+$/', $v)) {
        return '';
    }
    $n = (int) $v;
    if ($n <= 0 || $n > $max) {
        return '';
    }
    return (string) $n;
}

function hocrRate($v): string
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

function hocrAddDays(string $iso, int $days): string
{
    $d = DateTime::createFromFormat('!Y-m-d', $iso);
    if (!$d) {
        return '';
    }
    if ($days !== 0) {
        $d->modify(($days > 0 ? '+' : '') . $days . ' days');
    }
    return $d->format('Y-m-d');
}

/**
 * @param array<string,mixed> $raw
 * @return array<string,string>|null
 */
function hocrRow(array $raw): ?array
{
    $city = tocrNiceName((string) ($raw['city'] ?? ''));
    $hotel = tocrNiceName((string) ($raw['hotel_name'] ?? ($raw['hotel'] ?? '')));
    $room = tocrCleanText($raw['room_type'] ?? '', 80);
    $rooms = hocrInt($raw['rooms'] ?? '', 99);
    $meal = hocrMeal($raw['meal_plan'] ?? '');
    $checkin = tocrDate($raw['check_in'] ?? ($raw['checkin'] ?? ''));
    $checkout = tocrDate($raw['check_out'] ?? ($raw['checkout'] ?? ''));
    $nights = hocrInt($raw['nights'] ?? '', 60);
    $rate = hocrRate($raw['rate'] ?? '');
    $supplier = tocrNiceName((string) ($raw['supplier'] ?? ''));

    if ($checkin !== '' && $checkout !== '') {
        $a = strtotime($checkin);
        $b = strtotime($checkout);
        $span = ($a && $b) ? (int) round(($b - $a) / 86400) : 0;
        $nights = ($span > 0 && $span <= 60) ? (string) $span : '';
    } elseif ($checkin !== '' && $nights !== '') {
        $checkout = hocrAddDays($checkin, (int) $nights);
    } elseif ($checkout !== '' && $nights !== '') {
        $checkin = hocrAddDays($checkout, -1 * (int) $nights);
    }

    if ($city === '' && $hotel === '' && $room === '' && $checkin === '' && $rate === '') {
        return null;
    }

    return [
        'city' => $city,
        'hotel_name' => $hotel,
        'room_type' => $room,
        'rooms' => $rooms,
        'meal_plan' => $meal,
        'nights' => $nights,
        'check_in' => $checkin,
        'check_out' => $checkout,
        'rate' => $rate,
        'supplier' => $supplier,
    ];
}

/**
 * @param array<string,mixed> $raw
 * @return array{is_hotel_quotation: bool, hotels: list<array<string,string>>}
 */
function hocrNormalize(array $raw): array
{
    $rows = [];
    $list = $raw['hotels'] ?? [];
    if (is_array($list)) {
        foreach ($list as $item) {
            if (!is_array($item)) {
                continue;
            }
            $row = hocrRow($item);
            if ($row !== null) {
                $rows[] = $row;
            }
            if (count($rows) >= 15) {
                break;
            }
        }
    }
    $flag = $raw['is_hotel_quotation'] ?? true;
    $isQuote = !($flag === false || $flag === 0 || $flag === 'false' || $flag === '0');
    if (!$isQuote) {
        $rows = [];
    }
    return [
        'is_hotel_quotation' => $isQuote,
        'hotels' => $rows,
    ];
}

/**
 * @return array{ok: bool, error?: string, result?: array<string,mixed>, model?: string}
 */
function hocrExtract(string $absPath, string $mime): array
{
    if (!hocrAvailable()) {
        return ['ok' => false, 'error' => 'AI image reading is not enabled. Configure the Gemini key in crm/includes/ai_config.local.php.'];
    }
    $bytes = @file_get_contents($absPath);
    if ($bytes === false || $bytes === '') {
        return ['ok' => false, 'error' => 'Could not read the uploaded image.'];
    }
    $base64 = base64_encode($bytes);
    $prompt = hocrPrompt();
    $lastError = 'The hotel image could not be read.';
    foreach (array_slice(crmAiGeminiModels(), 0, 3) as $model) {
        $res = tocrCallGeminiVisionOnce($prompt, $mime, $base64, $model, 70);
        if (!empty($res['ok']) && is_array($res['data'])) {
            return ['ok' => true, 'result' => hocrNormalize($res['data']), 'model' => (string) $model];
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
        $lastError = 'The AI service is busy right now. Please try again in a minute or fill the hotel rows manually.';
    }
    return ['ok' => false, 'error' => $lastError];
}
