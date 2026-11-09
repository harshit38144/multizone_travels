<?php
/**
 * Quotation Generator: extract itinerary / hotels / inclusions / exclusions
 * from an uploaded image, PDF, or Word document via Gemini.
 */

require_once __DIR__ . '/traveller_ocr.php';
require_once __DIR__ . '/hotel_ocr.php';

function qdocAvailable(): bool
{
    return crmAiUseGemini();
}

/** @return list<string> */
function qdocAllowedExtensions(): array
{
    return ['jpg', 'jpeg', 'png', 'webp', 'pdf', 'doc', 'docx'];
}

/** @return list<string> */
function qdocAllowedMimes(): array
{
    return [
        'image/jpeg',
        'image/png',
        'image/webp',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/octet-stream',
    ];
}

/**
 * Parse PHP ini size values like "40M", "512K", "1G".
 */
function qdocIniSizeToBytes(string $value): int
{
    $value = trim($value);
    if ($value === '' || $value === '0') {
        return 0;
    }
    if (!preg_match('/^(\d+(?:\.\d+)?)([KMG])?$/i', $value, $m)) {
        return (int) $value;
    }
    $n = (float) $m[1];
    $unit = strtoupper($m[2] ?? '');
    if ($unit === 'G') {
        $n *= 1024 * 1024 * 1024;
    } elseif ($unit === 'M') {
        $n *= 1024 * 1024;
    } elseif ($unit === 'K') {
        $n *= 1024;
    }
    return (int) $n;
}

/**
 * App cap (50 MB), also clamped to PHP upload/post limits.
 */
function qdocMaxBytes(): int
{
    $appCap = 50 * 1024 * 1024;
    $upload = qdocIniSizeToBytes((string) ini_get('upload_max_filesize'));
    $post = qdocIniSizeToBytes((string) ini_get('post_max_size'));
    $limit = $appCap;
    if ($upload > 0) {
        $limit = min($limit, $upload);
    }
    if ($post > 0) {
        // Leave a little headroom for multipart form fields.
        $limit = min($limit, max(1024 * 1024, $post - (2 * 1024 * 1024)));
    }
    return max(1024 * 1024, $limit);
}

function qdocMaxMbLabel(): int
{
    return max(1, (int) floor(qdocMaxBytes() / (1024 * 1024)));
}

/** Inline base64 JSON to Gemini should stay under ~18 MB request body. */
function qdocInlineSafeBytes(): int
{
    return 8 * 1024 * 1024;
}

function qdocPrompt(): string
{
    $p = "You are a travel-document extraction engine for an Indian travel agency quotation form.\n";
    $p .= "Read the attached travel itinerary, hotel quotation, package PDF, Word document, or screenshot.\n";
    $p .= "Extract ONLY what is clearly present. Never invent hotels, days, or inclusions.\n";
    $p .= "\nReturn ONLY JSON with this shape:\n";
    $p .= "{\n";
    $p .= "  \"document_type\": \"itinerary | hotel_quote | package | mixed | other\",\n";
    $p .= "  \"itinerary\": [\n";
    $p .= "    {\n";
    $p .= "      \"day\": 1,\n";
    $p .= "      \"title\": \"short day title\",\n";
    $p .= "      \"description\": \"full day description / activities / sightseeing as plain text with newlines\",\n";
    $p .= "      \"overnight\": \"hotel or city overnight if printed\",\n";
    $p .= "      \"meal\": \"meal plan text if printed\"\n";
    $p .= "    }\n";
    $p .= "  ],\n";
    $p .= "  \"hotels\": [\n";
    $p .= "    {\n";
    $p .= "      \"city\": \"\",\n";
    $p .= "      \"hotel_name\": \"\",\n";
    $p .= "      \"room_type\": \"\",\n";
    $p .= "      \"rooms\": \"\",\n";
    $p .= "      \"meal_plan\": \"EP|CP|MAP|AP or empty\",\n";
    $p .= "      \"nights\": \"\",\n";
    $p .= "      \"check_in\": \"YYYY-MM-DD\",\n";
    $p .= "      \"check_out\": \"YYYY-MM-DD\",\n";
    $p .= "      \"rate\": \"number only\",\n";
    $p .= "      \"supplier\": \"\"\n";
    $p .= "    }\n";
    $p .= "  ],\n";
    $p .= "  \"inclusions\": [\"service or facility included\"],\n";
    $p .= "  \"exclusions\": [\"service or expense excluded\"]\n";
    $p .= "}\n\n";
    $p .= "Rules:\n";
    $p .= "- Day numbers must start at 1 and follow document order.\n";
    $p .= "- description should keep sightseeing and activity detail; use plain text, not HTML.\n";
    $p .= "- Split inclusion/exclusion bullets into separate array items.\n";
    $p .= "- Use \"\" / [] when a section is not present.\n";
    $p .= "- Dates must be YYYY-MM-DD when year is known; otherwise \"\".\n";
    $p .= "- meal_plan hotel codes only when clearly EP/CP/MAP/AP (or full plan names).\n";
    return $p;
}

/**
 * Extract plain text from a .docx (Office Open XML) file.
 */
function qdocExtractDocxText(string $absPath): string
{
    if (!class_exists('ZipArchive')) {
        return '';
    }
    $zip = new ZipArchive();
    if ($zip->open($absPath) !== true) {
        return '';
    }
    $xml = $zip->getFromName('word/document.xml');
    $zip->close();
    if ($xml === false || $xml === '') {
        return '';
    }
    $xml = preg_replace('/<\/w:p>/', "\n", $xml) ?? $xml;
    $xml = preg_replace('/<\/w:tr>/', "\n", $xml) ?? $xml;
    $xml = preg_replace('/<w:tab[^>]*\/>/', "\t", $xml) ?? $xml;
    $text = strip_tags($xml);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace("/[ \t]+/", ' ', $text) ?? $text;
    $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;
    return trim($text);
}

/**
 * Best-effort plain text from legacy .doc binary.
 */
function qdocExtractDocText(string $absPath): string
{
    $bytes = @file_get_contents($absPath);
    if ($bytes === false || $bytes === '') {
        return '';
    }
    $chunks = [];
    // UTF-16LE runs
    if (preg_match_all('/(?:[\x20-\x7E]\x00){5,}/', $bytes, $m)) {
        foreach ($m[0] as $run) {
            $decoded = @mb_convert_encoding($run, 'UTF-8', 'UTF-16LE');
            if (is_string($decoded) && trim($decoded) !== '') {
                $chunks[] = trim($decoded);
            }
        }
    }
    // ASCII runs
    if (preg_match_all('/[\x09\x0A\x0D\x20-\x7E]{6,}/', $bytes, $m2)) {
        foreach ($m2[0] as $run) {
            $t = trim($run);
            if ($t !== '' && !preg_match('/^(?:\x00)+$/', $t)) {
                $chunks[] = $t;
            }
        }
    }
    $text = trim(implode("\n", $chunks));
    $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;
    return $text;
}

/**
 * Upload a local file to Gemini Files API (for large PDFs/images).
 *
 * @return array{ok:bool,error?:string,file_uri?:string,mime?:string,name?:string}
 */
function qdocGeminiFilesUpload(string $absPath, string $mime, string $displayName = 'quotation-document'): array
{
    $apiKey = crmAiGeminiApiKey();
    if ($apiKey === '') {
        return ['ok' => false, 'error' => 'Gemini API key is not configured.'];
    }
    if (!is_file($absPath) || !is_readable($absPath)) {
        return ['ok' => false, 'error' => 'Uploaded file is not readable.'];
    }
    $size = (int) filesize($absPath);
    if ($size <= 0) {
        return ['ok' => false, 'error' => 'Uploaded file is empty.'];
    }
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'error' => 'PHP cURL is required to upload large documents.'];
    }

    $startUrl = 'https://generativelanguage.googleapis.com/upload/v1beta/files';
    $meta = json_encode(['file' => ['display_name' => $displayName]], JSON_UNESCAPED_UNICODE);
    if ($meta === false) {
        return ['ok' => false, 'error' => 'Could not prepare the large-file upload.'];
    }

    $ch = curl_init($startUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $meta,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'x-goog-api-key: ' . $apiKey,
            'X-Goog-Upload-Protocol: resumable',
            'X-Goog-Upload-Command: start',
            'X-Goog-Upload-Header-Content-Length: ' . $size,
            'X-Goog-Upload-Header-Content-Type: ' . $mime,
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 60,
    ]);
    $startRaw = curl_exec($ch);
    $startCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $startErr = curl_error($ch);
    curl_close($ch);

    if ($startRaw === false || $startCode < 200 || $startCode >= 300) {
        $msg = $startErr !== '' ? $startErr : ('Large-file upload failed to start (HTTP ' . $startCode . ')');
        $body = is_string($startRaw) ? substr($startRaw, $headerSize) : '';
        $errData = json_decode((string) $body, true);
        if (!empty($errData['error']['message'])) {
            $msg = (string) $errData['error']['message'];
        }
        return ['ok' => false, 'error' => $msg];
    }

    $headers = substr((string) $startRaw, 0, $headerSize);
    $uploadUrl = '';
    if (preg_match('/^x-goog-upload-url:\s*(.+)$/im', $headers, $m)) {
        $uploadUrl = trim($m[1]);
    }
    if ($uploadUrl === '') {
        return ['ok' => false, 'error' => 'Large-file upload URL was not returned by Gemini.'];
    }

    $bytes = @file_get_contents($absPath);
    if ($bytes === false || $bytes === '') {
        return ['ok' => false, 'error' => 'Could not open the uploaded file for transfer.'];
    }

    $ch2 = curl_init($uploadUrl);
    curl_setopt_array($ch2, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $bytes,
        CURLOPT_HTTPHEADER => [
            'Content-Type: ' . $mime,
            'Content-Length: ' . $size,
            'X-Goog-Upload-Offset: 0',
            'X-Goog-Upload-Command: upload, finalize',
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 300,
    ]);
    $upRaw = curl_exec($ch2);
    $upCode = (int) curl_getinfo($ch2, CURLINFO_HTTP_CODE);
    $upErr = curl_error($ch2);
    curl_close($ch2);
    unset($bytes);

    if ($upRaw === false || $upCode < 200 || $upCode >= 300) {
        $msg = $upErr !== '' ? $upErr : ('Large-file upload failed (HTTP ' . $upCode . ')');
        $errData = json_decode((string) $upRaw, true);
        if (!empty($errData['error']['message'])) {
            $msg = (string) $errData['error']['message'];
        }
        return ['ok' => false, 'error' => $msg];
    }

    $info = json_decode((string) $upRaw, true);
    $fileUri = (string) ($info['file']['uri'] ?? '');
    $fileName = (string) ($info['file']['name'] ?? '');
    $state = strtoupper((string) ($info['file']['state'] ?? ''));
    if ($fileUri === '' && $fileName !== '') {
        $fileUri = 'https://generativelanguage.googleapis.com/v1beta/' . ltrim($fileName, '/');
    }
    if ($fileUri === '') {
        return ['ok' => false, 'error' => 'Large-file upload did not return a file URI.'];
    }

    // Wait briefly if processing is still pending.
    $deadline = time() + 90;
    while ($state === 'PROCESSING' && $fileName !== '' && time() < $deadline) {
        usleep(800000);
        $statusUrl = 'https://generativelanguage.googleapis.com/v1beta/' . ltrim($fileName, '/');
        $ch3 = curl_init($statusUrl);
        curl_setopt_array($ch3, [
            CURLOPT_HTTPGET => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => ['x-goog-api-key: ' . $apiKey],
        ]);
        $stRaw = curl_exec($ch3);
        curl_close($ch3);
        $stInfo = json_decode((string) $stRaw, true);
        if (!is_array($stInfo)) {
            break;
        }
        $state = strtoupper((string) ($stInfo['state'] ?? ''));
        if (!empty($stInfo['uri'])) {
            $fileUri = (string) $stInfo['uri'];
        }
        if ($state === 'ACTIVE' || $state === 'FAILED' || $state === '') {
            break;
        }
    }
    if ($state === 'FAILED') {
        return ['ok' => false, 'error' => 'Gemini failed while processing the uploaded file.'];
    }

    return [
        'ok' => true,
        'file_uri' => $fileUri,
        'mime' => $mime,
        'name' => $fileName,
    ];
}

/**
 * Best-effort cleanup of a Gemini Files API object.
 */
function qdocGeminiFilesDelete(string $fileName): void
{
    $apiKey = crmAiGeminiApiKey();
    $fileName = trim($fileName);
    if ($apiKey === '' || $fileName === '' || !function_exists('curl_init')) {
        return;
    }
    $url = 'https://generativelanguage.googleapis.com/v1beta/' . ltrim($fileName, '/');
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => 'DELETE',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => ['x-goog-api-key: ' . $apiKey],
    ]);
    curl_exec($ch);
    curl_close($ch);
}

/**
 * @return array{ok:bool,error?:string,mime?:string,base64?:string,text?:string,mode?:string,path?:string,file_uri?:string,file_name?:string}
 */
function qdocPrepareUpload(string $absPath, string $mime, string $ext): array
{
    $ext = strtolower($ext);
    $mime = strtolower(trim($mime));
    if ($mime === 'image/jpg') {
        $mime = 'image/jpeg';
    }
    $size = is_file($absPath) ? (int) filesize($absPath) : 0;

    if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)
        || in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)
    ) {
        if ($mime === '' || $mime === 'application/octet-stream') {
            $mime = 'image/' . ($ext === 'jpg' ? 'jpeg' : ($ext ?: 'png'));
        }
        // Large images: Files API (avoids huge base64 JSON / invalid server responses).
        if ($size > qdocInlineSafeBytes()) {
            return [
                'ok' => true,
                'mode' => 'file',
                'mime' => $mime,
                'path' => $absPath,
            ];
        }
        $bytes = @file_get_contents($absPath);
        if ($bytes === false || $bytes === '') {
            return ['ok' => false, 'error' => 'Could not read the uploaded image.'];
        }
        return [
            'ok' => true,
            'mode' => 'inline',
            'mime' => $mime,
            'base64' => base64_encode($bytes),
        ];
    }

    if ($ext === 'pdf' || $mime === 'application/pdf') {
        // Prefer Files API for larger PDFs so Apache/PHP don't choke on base64 payloads.
        if ($size > qdocInlineSafeBytes()) {
            return [
                'ok' => true,
                'mode' => 'file',
                'mime' => 'application/pdf',
                'path' => $absPath,
            ];
        }
        $bytes = @file_get_contents($absPath);
        if ($bytes === false || $bytes === '') {
            return ['ok' => false, 'error' => 'Could not read the uploaded PDF.'];
        }
        return [
            'ok' => true,
            'mode' => 'inline',
            'mime' => 'application/pdf',
            'base64' => base64_encode($bytes),
        ];
    }

    $text = '';
    if ($ext === 'docx' || strpos($mime, 'wordprocessingml') !== false) {
        $text = qdocExtractDocxText($absPath);
    } elseif ($ext === 'doc' || $mime === 'application/msword') {
        $text = qdocExtractDocText($absPath);
    }

    $text = trim($text);
    if ($text === '') {
        return ['ok' => false, 'error' => 'Could not read text from this Word document. Try exporting it as PDF.'];
    }
    // Allow more text from large Word packages.
    if (mb_strlen($text) > 250000) {
        $text = mb_substr($text, 0, 250000);
    }
    return [
        'ok' => true,
        'mode' => 'text',
        'mime' => 'text/plain',
        'text' => $text,
    ];
}

/**
 * @param list<mixed> $list
 * @return list<string>
 */
function qdocStringList($list, int $maxItems = 80, int $maxLen = 400): array
{
    $out = [];
    if (!is_array($list)) {
        return $out;
    }
    foreach ($list as $item) {
        if (is_array($item)) {
            $item = $item['text'] ?? $item['item'] ?? $item['name'] ?? '';
        }
        $s = tocrCleanText($item, $maxLen);
        if ($s === '') {
            continue;
        }
        $out[] = $s;
        if (count($out) >= $maxItems) {
            break;
        }
    }
    return $out;
}

/**
 * @param array<string,mixed> $raw
 * @return array<string,mixed>
 */
function qdocNormalize(array $raw): array
{
    $docType = strtolower(tocrCleanText($raw['document_type'] ?? '', 40));
    $allowedTypes = ['itinerary', 'hotel_quote', 'package', 'mixed', 'other'];
    if (!in_array($docType, $allowedTypes, true)) {
        $docType = 'mixed';
    }

    $itinerary = [];
    $days = $raw['itinerary'] ?? [];
    if (is_array($days)) {
        $dayNum = 1;
        foreach ($days as $item) {
            if (!is_array($item)) {
                continue;
            }
            $title = tocrCleanText($item['title'] ?? '', 180);
            $descRaw = $item['description'] ?? '';
            $desc = is_scalar($descRaw) ? trim((string) $descRaw) : '';
            $desc = preg_replace("/\r\n|\r/", "\n", $desc) ?? $desc;
            $desc = trim(mb_substr($desc, 0, 4000));
            $activitiesRaw = $item['activities'] ?? '';
            $activities = is_scalar($activitiesRaw) ? trim((string) $activitiesRaw) : '';
            if ($desc === '' && $activities !== '') {
                $desc = trim(mb_substr($activities, 0, 4000));
            }
            $overnight = tocrCleanText($item['overnight'] ?? '', 160);
            $meal = tocrCleanText($item['meal'] ?? '', 120);
            $n = (int) ($item['day'] ?? $dayNum);
            if ($n < 1) {
                $n = $dayNum;
            }
            if ($title === '' && $desc === '' && $overnight === '' && $meal === '') {
                continue;
            }
            $itinerary[] = [
                'day' => $n,
                'title' => $title,
                'description' => $desc,
                'overnight' => $overnight,
                'meal' => $meal,
            ];
            $dayNum++;
            if (count($itinerary) >= 21) {
                break;
            }
        }
    }

    $hotels = [];
    $hotelList = $raw['hotels'] ?? [];
    if (is_array($hotelList)) {
        foreach ($hotelList as $item) {
            if (!is_array($item)) {
                continue;
            }
            $row = hocrRow($item);
            if ($row !== null) {
                $hotels[] = $row;
            }
            if (count($hotels) >= 15) {
                break;
            }
        }
    }

    return [
        'document_type' => $docType,
        'itinerary' => $itinerary,
        'hotels' => $hotels,
        'inclusions' => qdocStringList($raw['inclusions'] ?? []),
        'exclusions' => qdocStringList($raw['exclusions'] ?? []),
    ];
}

/**
 * @return array{ok:bool,error?:string,http_code?:int,data?:array,model?:string}
 */
function qdocCallGeminiOnce(string $prompt, array $prepared, string $model, int $timeoutSeconds): array
{
    $apiKey = crmAiGeminiApiKey();
    if ($apiKey === '') {
        return ['ok' => false, 'error' => 'Gemini API key is not configured.'];
    }

    $parts = [];
    $mode = (string) ($prepared['mode'] ?? '');
    if ($mode === 'inline') {
        $parts[] = [
            'inline_data' => [
                'mime_type' => (string) ($prepared['mime'] ?? 'application/pdf'),
                'data' => (string) ($prepared['base64'] ?? ''),
            ],
        ];
        $parts[] = ['text' => $prompt];
    } elseif ($mode === 'file') {
        $parts[] = [
            'file_data' => [
                'mime_type' => (string) ($prepared['mime'] ?? 'application/pdf'),
                'file_uri' => (string) ($prepared['file_uri'] ?? ''),
            ],
        ];
        $parts[] = ['text' => $prompt];
    } else {
        $docText = (string) ($prepared['text'] ?? '');
        $parts[] = ['text' => $prompt . "\n\n----- DOCUMENT TEXT START -----\n" . $docText . "\n----- DOCUMENT TEXT END -----"];
    }

    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent';
    $payload = [
        'contents' => [['parts' => $parts]],
        'generationConfig' => [
            'temperature' => 0.1,
            'responseMimeType' => 'application/json',
        ],
    ];
    $jsonFlags = JSON_UNESCAPED_UNICODE;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $jsonFlags |= JSON_INVALID_UTF8_SUBSTITUTE;
    }
    $body = json_encode($payload, $jsonFlags);
    if ($body === false) {
        return ['ok' => false, 'error' => 'Could not encode the document for AI reading (file may be too large).'];
    }

    $transport = crmAiHttpPostJson(
        $url,
        $body,
        [
            'Content-Type: application/json',
            'x-goog-api-key: ' . $apiKey,
        ],
        max(45, $timeoutSeconds)
    );
    $raw = $transport['raw'];
    $httpCode = (int) ($transport['http_code'] ?? 0);
    $curlErr = (string) ($transport['error'] ?? '');

    if ($raw === null || $httpCode === 401 || $httpCode === 403) {
        $transport = crmAiHttpPostJson(
            $url . '?key=' . urlencode($apiKey),
            $body,
            ['Content-Type: application/json'],
            max(45, $timeoutSeconds)
        );
        $raw = $transport['raw'];
        $httpCode = (int) ($transport['http_code'] ?? 0);
        $curlErr = (string) ($transport['error'] ?? $curlErr);
    }

    if ($raw === null || $httpCode !== 200) {
        $msg = $curlErr !== '' ? $curlErr : ('AI request failed (HTTP ' . $httpCode . ')');
        if ($raw) {
            $errData = json_decode((string) $raw, true);
            if (!empty($errData['error']['message'])) {
                $msg = (string) $errData['error']['message'];
            }
        }
        if ($httpCode === 0 && $curlErr !== '') {
            $msg = 'Could not reach the AI service: ' . $curlErr;
        }
        return ['ok' => false, 'error' => $msg, 'http_code' => $httpCode];
    }

    $data = json_decode((string) $raw, true);
    if (!is_array($data)) {
        return ['ok' => false, 'error' => 'The AI returned an invalid response.', 'http_code' => $httpCode];
    }

    $blockReason = (string) ($data['promptFeedback']['blockReason'] ?? '');
    if ($blockReason !== '') {
        return ['ok' => false, 'error' => 'The AI blocked this document (' . $blockReason . '). Try a different file.', 'http_code' => $httpCode];
    }

    $candidate = $data['candidates'][0] ?? null;
    $text = '';
    if (is_array($candidate)) {
        $partsOut = $candidate['content']['parts'] ?? [];
        if (is_array($partsOut)) {
            foreach ($partsOut as $part) {
                if (is_array($part) && isset($part['text'])) {
                    $text .= (string) $part['text'];
                }
            }
        }
        $finish = (string) ($candidate['finishReason'] ?? '');
        if ($text === '' && $finish !== '' && strtoupper($finish) !== 'STOP') {
            return ['ok' => false, 'error' => 'The AI stopped without data (' . $finish . '). Try a smaller or clearer file.', 'http_code' => $httpCode];
        }
    }
    if ($text === '') {
        return ['ok' => false, 'error' => 'The AI returned an empty response. Try a clearer PDF or image.', 'http_code' => $httpCode];
    }
    $text = trim(preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($text)) ?? $text);
    $parsed = json_decode($text, true);
    if (!is_array($parsed)) {
        // Some models wrap JSON in prose — pull the first {...} block.
        if (preg_match('/\{[\s\S]*\}/', $text, $m)) {
            $parsed = json_decode($m[0], true);
        }
    }
    if (!is_array($parsed)) {
        return ['ok' => false, 'error' => 'Could not parse the AI response as structured data.', 'http_code' => $httpCode];
    }
    return ['ok' => true, 'data' => $parsed, 'model' => $model, 'http_code' => $httpCode];
}

/**
 * @return array{ok:bool,error?:string,result?:array<string,mixed>,model?:string}
 */
function qdocExtract(string $absPath, string $mime, string $ext): array
{
    if (!qdocAvailable()) {
        return ['ok' => false, 'error' => 'AI document reading is not enabled. Configure the Gemini key in crm/includes/ai_config.local.php.'];
    }

    $prepared = qdocPrepareUpload($absPath, $mime, $ext);
    if (empty($prepared['ok'])) {
        return ['ok' => false, 'error' => (string) ($prepared['error'] ?? 'Could not prepare the uploaded file.')];
    }

    $remoteFileName = '';
    if (($prepared['mode'] ?? '') === 'file') {
        $up = qdocGeminiFilesUpload(
            (string) ($prepared['path'] ?? $absPath),
            (string) ($prepared['mime'] ?? $mime),
            'quotation-doc-' . date('YmdHis')
        );
        if (empty($up['ok'])) {
            return ['ok' => false, 'error' => (string) ($up['error'] ?? 'Large file upload to AI failed.')];
        }
        $prepared['file_uri'] = (string) ($up['file_uri'] ?? '');
        $remoteFileName = (string) ($up['name'] ?? '');
        $prepared['file_name'] = $remoteFileName;
    }

    $prompt = qdocPrompt();
    $lastError = 'The document could not be read.';
    $mode = (string) ($prepared['mode'] ?? '');
    $timeout = ($mode === 'file') ? 210 : (($mode === 'inline') ? 150 : 90);

    $models = crmAiGeminiModels();
    // Extra multimodal fallbacks used by hotel/flight OCR paths when primary models fail.
    foreach (['gemini-2.5-flash-lite', 'gemini-2.5-flash', 'gemini-2.0-flash'] as $extra) {
        if (!in_array($extra, $models, true)) {
            $models[] = $extra;
        }
    }

    try {
        foreach (array_slice($models, 0, 5) as $model) {
            $res = qdocCallGeminiOnce($prompt, $prepared, $model, $timeout);
            if (!empty($res['ok']) && is_array($res['data'])) {
                return [
                    'ok' => true,
                    'result' => qdocNormalize($res['data']),
                    'model' => (string) $model,
                ];
            }
            $err = trim((string) ($res['error'] ?? ''));
            if ($err !== '') {
                $lastError = $err;
            }
            $code = (int) ($res['http_code'] ?? 0);
            if (in_array($code, [401, 403], true)) {
                break;
            }
            // Keep trying next model on not-found / overload; stop on clear client errors.
            if (!crmAiIsTransientGeminiError($lastError) && !in_array($code, [0, 404, 429, 500, 503], true)) {
                break;
            }
        }
    } finally {
        if ($remoteFileName !== '') {
            qdocGeminiFilesDelete($remoteFileName);
        }
    }

    if (crmAiIsQuotaOrRateError($lastError)) {
        $lastError = 'The AI service is busy right now. Please try again in a minute.';
    }
    return ['ok' => false, 'error' => $lastError];
}
