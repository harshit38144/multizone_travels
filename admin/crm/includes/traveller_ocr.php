<?php
/**
 * Confirm Tour → Add Guest: read traveller details from an ID document (passport, Aadhaar, PAN, …)
 * with the configured Gemini API (see ai_config.php). The file is sent inline and never stored remotely.
 */

require_once __DIR__ . '/ai_config.php';

function tocrAvailable(): bool
{
    return crmAiUseGemini();
}

/**
 * @return array{ok: bool, error?: string, http_code?: int, data?: array, model?: string}
 */
function tocrCallGeminiVisionOnce(string $prompt, string $mime, string $base64, string $model, int $timeoutSeconds): array
{
    $apiKey = crmAiGeminiApiKey();
    if ($apiKey === '') {
        return ['ok' => false, 'error' => 'Gemini API key is not configured.'];
    }
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent';
    $body = json_encode([
        'contents' => [[
            'parts' => [
                ['inline_data' => ['mime_type' => $mime, 'data' => $base64]],
                ['text' => $prompt],
            ],
        ]],
        'generationConfig' => [
            'temperature' => 0.1,
            'responseMimeType' => 'application/json',
        ],
    ]);
    if ($body === false) {
        return ['ok' => false, 'error' => 'Could not encode the document for OCR.'];
    }

    // Prefer shared HTTP helper (cURL when available; file_get_contents fallback for XAMPP Apache without ext-curl).
    $transport = crmAiHttpPostJson(
        $url,
        $body,
        [
            'Content-Type: application/json',
            'x-goog-api-key: ' . $apiKey,
        ],
        max(15, $timeoutSeconds)
    );
    $raw = $transport['raw'];
    $httpCode = (int) ($transport['http_code'] ?? 0);
    $curlErr = (string) ($transport['error'] ?? '');

    if ($raw === null || $httpCode === 401 || $httpCode === 403) {
        $transport = crmAiHttpPostJson(
            $url . '?key=' . urlencode($apiKey),
            $body,
            ['Content-Type: application/json'],
            max(15, $timeoutSeconds)
        );
        $raw = $transport['raw'];
        $httpCode = (int) ($transport['http_code'] ?? 0);
        $curlErr = (string) ($transport['error'] ?? $curlErr);
    }

    if ($raw === null || $httpCode !== 200) {
        $msg = $curlErr !== '' ? $curlErr : ('OCR request failed (HTTP ' . $httpCode . ')');
        if ($raw) {
            $errData = json_decode((string) $raw, true);
            if (!empty($errData['error']['message'])) {
                $msg = (string) $errData['error']['message'];
            }
        }
        return ['ok' => false, 'error' => $msg, 'http_code' => $httpCode];
    }

    $data = json_decode((string) $raw, true);
    $text = (string) ($data['candidates'][0]['content']['parts'][0]['text'] ?? '');
    if ($text === '') {
        return ['ok' => false, 'error' => 'The AI returned an empty response.'];
    }
    $text = trim(preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($text)) ?? $text);
    $parsed = json_decode($text, true);
    if (!is_array($parsed)) {
        if (preg_match('/\{[\s\S]*\}|\[[\s\S]*\]/', $text, $m)) {
            $parsed = json_decode($m[0], true);
        }
    }
    if (!is_array($parsed)) {
        return ['ok' => false, 'error' => 'Could not read the AI response.'];
    }
    return ['ok' => true, 'data' => $parsed, 'model' => $model];
}

/**
 * Downscale large screenshots so vision OCR is faster and less likely to hit timeouts / 503s.
 *
 * @return array{0:string,1:string} [mime, base64]
 */
function tocrPrepareVisionPayload(string $absPath, string $mime): array
{
    $bytes = @file_get_contents($absPath);
    if ($bytes === false || $bytes === '') {
        return [$mime, ''];
    }

    $mime = strtolower(trim($mime));
    if ($mime === 'image/jpg') {
        $mime = 'image/jpeg';
    }

    $canResize = function_exists('imagecreatefromstring') && function_exists('imagejpeg');
    $tooBig = strlen($bytes) > (900 * 1024);
    if (!$canResize || (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true) && !$tooBig)) {
        return [$mime, base64_encode($bytes)];
    }

    $img = @imagecreatefromstring($bytes);
    if ($img === false) {
        return [$mime, base64_encode($bytes)];
    }

    $w = imagesx($img);
    $h = imagesy($img);
    $maxEdge = 1600;
    if ($w <= 0 || $h <= 0) {
        imagedestroy($img);
        return [$mime, base64_encode($bytes)];
    }

    $scale = 1.0;
    if ($w > $maxEdge || $h > $maxEdge) {
        $scale = min($maxEdge / $w, $maxEdge / $h);
    } elseif (!$tooBig) {
        imagedestroy($img);
        return [$mime, base64_encode($bytes)];
    }

    $nw = max(1, (int) round($w * $scale));
    $nh = max(1, (int) round($h * $scale));
    $dst = imagecreatetruecolor($nw, $nh);
    if ($dst === false) {
        imagedestroy($img);
        return [$mime, base64_encode($bytes)];
    }
    imagealphablending($dst, true);
    imagesavealpha($dst, false);
    $white = imagecolorallocate($dst, 255, 255, 255);
    imagefilledrectangle($dst, 0, 0, $nw, $nh, $white);
    imagecopyresampled($dst, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
    imagedestroy($img);

    ob_start();
    imagejpeg($dst, null, 82);
    $jpeg = ob_get_clean();
    imagedestroy($dst);

    if (!is_string($jpeg) || $jpeg === '') {
        return [$mime, base64_encode($bytes)];
    }

    return ['image/jpeg', base64_encode($jpeg)];
}

/**
 * Vision call with model fallbacks + short retries on overload (503/429).
 *
 * @param list<string>|null $models
 * @return array{ok:bool,error?:string,http_code?:int,data?:array,model?:string}
 */
function tocrCallGeminiVision(string $prompt, string $mime, string $base64, int $timeoutSeconds = 70, ?array $models = null, int $maxAttemptsPerModel = 3): array
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

    $maxAttemptsPerModel = max(1, min(4, $maxAttemptsPerModel));
    $lastError = 'OCR request failed.';
    $lastCode = 0;

    foreach ($unique as $model) {
        for ($attempt = 1; $attempt <= $maxAttemptsPerModel; $attempt++) {
            crmAiLog('vision_try', [
                'model' => $model,
                'attempt' => $attempt,
            ]);
            $res = tocrCallGeminiVisionOnce($prompt, $mime, $base64, $model, $timeoutSeconds);
            if (!empty($res['ok']) && is_array($res['data'] ?? null)) {
                crmAiLog('vision_ok', ['model' => $model, 'attempt' => $attempt]);
                return $res;
            }

            $lastError = (string) ($res['error'] ?? $lastError);
            $lastCode = (int) ($res['http_code'] ?? 0);
            crmAiLog('vision_fail', [
                'model' => $model,
                'attempt' => $attempt,
                'http_code' => $lastCode,
                'error' => $lastError,
            ]);

            if (in_array($lastCode, [401, 403], true)) {
                return ['ok' => false, 'error' => $lastError, 'http_code' => $lastCode];
            }

            $busy = in_array($lastCode, [429, 500, 503], true) || crmAiIsBusyOverloadError($lastError);
            $missing = in_array($lastCode, [404], true)
                || strpos(strtolower($lastError), 'not found') !== false
                || strpos(strtolower($lastError), 'no longer available') !== false;

            if ($missing) {
                // Next model immediately — no point retrying a retired ID.
                break;
            }
            if (!$busy && !crmAiIsTransientGeminiError($lastError)) {
                break;
            }
            if ($attempt < $maxAttemptsPerModel && $busy) {
                // Back off briefly; Gemini free-tier overload is usually short-lived.
                usleep(700000 * $attempt);
            }
        }
    }

    return ['ok' => false, 'error' => $lastError, 'http_code' => $lastCode];
}

function tocrPrompt(string $hint): string
{
    $p = "You are an OCR and data-extraction engine for an Indian travel agency CRM.\n";
    $p .= "Read the attached identity document image/PDF and extract the traveller's details.\n";
    if ($hint !== '' && $hint !== 'auto') {
        $p .= "The user says this is probably a: {$hint}.\n";
    }
    $p .= "\nReturn ONLY JSON with exactly these keys (use \"\" when a value is not printed on the document):\n";
    $p .= "{\n";
    $p .= "  \"is_identity_document\": true,\n";
    $p .= "  \"document_type\": \"passport | aadhaar | pan | visa | driving_licence | voter_id | other\",\n";
    $p .= "  \"full_name\": \"\", \"given_names\": \"\", \"surname\": \"\",\n";
    $p .= "  \"date_of_birth\": \"YYYY-MM-DD\", \"gender\": \"Male | Female | Other\",\n";
    $p .= "  \"document_number\": \"\", \"nationality\": \"\",\n";
    $p .= "  \"date_of_issue\": \"YYYY-MM-DD\", \"date_of_expiry\": \"YYYY-MM-DD\", \"place_of_birth\": \"\",\n";
    $p .= "  \"address\": \"street / house / locality only\", \"city\": \"\", \"state\": \"\", \"country\": \"\", \"pincode\": \"\",\n";
    $p .= "  \"confidence\": \"high | medium | low\"\n";
    $p .= "}\n\n";
    $p .= "Rules:\n";
    $p .= "- Never guess or invent values. Only copy what is clearly printed (for passports you may use the MRZ lines).\n";
    $p .= "- Dates must be converted to YYYY-MM-DD.\n";
    $p .= "- Aadhaar number: 12 digits formatted as 'XXXX XXXX XXXX'. PAN: 10 characters (AAAAA9999A).\n";
    $p .= "- Nationality as a demonym in English, e.g. 'Indian'.\n";
    $p .= "- Split the address: pincode = 6-digit PIN (India) or postal code; city, state, country separately.\n";
    $p .= "- If the file is not an identity document, set is_identity_document to false and leave the other fields empty.\n";
    return $p;
}

function tocrCleanText($v, int $max = 190): string
{
    $v = is_scalar($v) ? (string) $v : '';
    $v = trim(preg_replace('/\s+/u', ' ', strip_tags($v)) ?? '');
    if (in_array(strtolower($v), ['n/a', 'na', 'null', 'none', 'not available', '-', '—'], true)) {
        return '';
    }
    return mb_substr($v, 0, $max);
}

/** Title-case names that documents print in capitals ("RAHUL KUMAR" → "Rahul Kumar"). */
function tocrNiceName(string $name): string
{
    $name = tocrCleanText($name, 120);
    if ($name === '') {
        return '';
    }
    if (mb_strtoupper($name) === $name || mb_strtolower($name) === $name) {
        $name = mb_convert_case(mb_strtolower($name), MB_CASE_TITLE, 'UTF-8');
    }
    return $name;
}

function tocrDate($v): string
{
    $v = tocrCleanText($v, 40);
    if ($v === '') {
        return '';
    }
    $formats = ['Y-m-d', 'd/m/Y', 'd-m-Y', 'd.m.Y', 'd M Y', 'd F Y', 'j M Y', 'j F Y', 'd/M/Y', 'd-M-Y', 'Y/m/d'];
    foreach ($formats as $fmt) {
        $d = DateTime::createFromFormat('!' . $fmt, $v);
        if ($d && $d->format($fmt) === $v) {
            $y = (int) $d->format('Y');
            if ($y >= 1900 && $y <= 2100) {
                return $d->format('Y-m-d');
            }
        }
    }
    $ts = strtotime($v);
    if ($ts !== false) {
        $y = (int) date('Y', $ts);
        if ($y >= 1900 && $y <= 2100) {
            return date('Y-m-d', $ts);
        }
    }
    return '';
}

function tocrGender($v): string
{
    $v = strtolower(tocrCleanText($v, 20));
    if ($v === '') {
        return '';
    }
    if ($v === 'm' || strpos($v, 'female') === false && strpos($v, 'male') !== false || $v === 'पुरुष') {
        return 'Male';
    }
    if ($v === 'f' || strpos($v, 'female') !== false || $v === 'महिला') {
        return 'Female';
    }
    return in_array($v, ['x', 'other', 'transgender', 'o'], true) ? 'Other' : '';
}

/** @return array{key: string, slot: string, label: string} */
function tocrDocumentKind(string $type): array
{
    $type = strtolower(str_replace([' ', '-'], '_', trim($type)));
    $map = [
        'passport' => ['passport', 'Passport'],
        'aadhaar' => ['aadhaar', 'Aadhaar Card'],
        'aadhar' => ['aadhaar', 'Aadhaar Card'],
        'aadhaar_card' => ['aadhaar', 'Aadhaar Card'],
        'pan' => ['pan', 'PAN Card'],
        'pan_card' => ['pan', 'PAN Card'],
        'visa' => ['visa', 'Visa'],
        'driving_licence' => ['other', 'Driving Licence'],
        'driving_license' => ['other', 'Driving Licence'],
        'voter_id' => ['other', 'Voter ID'],
    ];
    if (isset($map[$type])) {
        return ['key' => $type, 'slot' => $map[$type][0], 'label' => $map[$type][1]];
    }
    return ['key' => 'other', 'slot' => 'other', 'label' => 'Other Document'];
}

/**
 * Normalise the raw AI JSON into traveller-form fields.
 *
 * @return array<string, mixed>
 */
function tocrNormalize(array $raw): array
{
    $kind = tocrDocumentKind((string) ($raw['document_type'] ?? ''));
    $name = tocrNiceName((string) ($raw['full_name'] ?? ''));
    if ($name === '') {
        $name = tocrNiceName(trim(($raw['given_names'] ?? '') . ' ' . ($raw['surname'] ?? '')));
    }

    $docNo = strtoupper(tocrCleanText($raw['document_number'] ?? '', 40));
    if ($kind['slot'] === 'aadhaar') {
        $digits = preg_replace('/\D/', '', $docNo);
        if (strlen($digits) === 12) {
            $docNo = implode(' ', str_split($digits, 4));
        }
    } else {
        $docNo = preg_replace('/\s+/', '', $docNo) ?? $docNo;
    }

    $nationality = tocrNiceName((string) ($raw['nationality'] ?? ''));
    if (in_array(strtolower($nationality), ['ind', 'india'], true)) {
        $nationality = 'Indian';
    }
    if ($nationality === '' && in_array($kind['slot'], ['aadhaar', 'pan'], true)) {
        $nationality = 'Indian';
    }

    $pincode = tocrCleanText($raw['pincode'] ?? '', 20);
    $country = tocrNiceName((string) ($raw['country'] ?? ''));
    if ($country === '' && $kind['slot'] === 'aadhaar') {
        $country = 'India';
    }

    $fields = [
        'name' => $name,
        'dob' => tocrDate($raw['date_of_birth'] ?? ''),
        'gender' => tocrGender($raw['gender'] ?? ''),
        'nationality' => $nationality,
        'id_type' => $kind['label'],
        'id_number' => $docNo,
        'passport_number' => $kind['slot'] === 'passport' ? $docNo : '',
        'passport_expiry' => $kind['slot'] === 'passport' ? tocrDate($raw['date_of_expiry'] ?? '') : '',
        'address' => tocrCleanText($raw['address'] ?? '', 255),
        'city' => tocrNiceName((string) ($raw['city'] ?? '')),
        'state' => tocrNiceName((string) ($raw['state'] ?? '')),
        'country' => $country,
        'pincode' => $pincode,
    ];

    $confidence = strtolower(tocrCleanText($raw['confidence'] ?? '', 10));
    return [
        'is_document' => !isset($raw['is_identity_document']) || !empty($raw['is_identity_document']),
        'document_kind' => $kind,
        'confidence' => in_array($confidence, ['high', 'medium', 'low'], true) ? $confidence : 'medium',
        'fields' => $fields,
        'filled' => array_keys(array_filter($fields, static function ($v) {
            return (string) $v !== '';
        })),
    ];
}

/**
 * @return array{ok: bool, error?: string, result?: array<string, mixed>, model?: string}
 */
function tocrExtract(string $absPath, string $mime, string $hint = 'auto'): array
{
    if (!tocrAvailable()) {
        return ['ok' => false, 'error' => 'AI document reading is not enabled. Configure the Gemini key in ai_config.local.php.'];
    }
    [$mime, $base64] = tocrPrepareVisionPayload($absPath, $mime);
    if ($base64 === '') {
        return ['ok' => false, 'error' => 'Could not read the uploaded file.'];
    }
    $prompt = tocrPrompt($hint);
    $res = tocrCallGeminiVision($prompt, $mime, $base64, 60, crmAiGeminiModels(), 3);
    if (!empty($res['ok']) && is_array($res['data'] ?? null)) {
        return [
            'ok' => true,
            'result' => tocrNormalize($res['data']),
            'model' => (string) ($res['model'] ?? ''),
        ];
    }
    $lastError = (string) ($res['error'] ?? 'OCR request failed.');
    if (crmAiIsQuotaOrRateError($lastError) || in_array((int) ($res['http_code'] ?? 0), [429, 503], true)) {
        $lastError = 'The AI service is busy right now. Please try again in a minute or fill the details manually.';
    }
    return ['ok' => false, 'error' => $lastError];
}
