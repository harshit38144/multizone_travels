<?php
/**
 * Image search API settings for itinerary (reads ai_config.local.php).
 *
 * Optional: add pexels_api_key — free at https://www.pexels.com/api/
 * Without a key, Wikimedia Commons is used (no key required).
 */

function crmImageApiSettings(): array
{
    static $settings = null;
    if ($settings !== null) {
        return $settings;
    }

    $settings = ['pexels_api_key' => ''];
    $localFile = __DIR__ . '/ai_config.local.php';
    if (is_file($localFile)) {
        $cfg = include $localFile;
        if (is_array($cfg) && !empty($cfg['pexels_api_key'])) {
            $settings['pexels_api_key'] = trim((string) $cfg['pexels_api_key']);
        }
    }

    $env = getenv('CRM_PEXELS_API_KEY');
    if ($env !== false && $env !== '') {
        $settings['pexels_api_key'] = trim((string) $env);
    }

    return $settings;
}

function crmImageApiJson(array $payload, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function crmImageSearchPexels(string $query, int $limit): array
{
    $key = crmImageApiSettings()['pexels_api_key'] ?? '';
    if ($key === '') {
        return [];
    }

    $url = 'https://api.pexels.com/v1/search?' . http_build_query([
        'query' => $query,
        'per_page' => min(20, max(1, $limit)),
        'orientation' => 'landscape',
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => ['Authorization: ' . $key],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 15,
    ]);
    $raw = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($raw === false || $code !== 200) {
        return [];
    }

    $data = json_decode($raw, true);
    $photos = $data['photos'] ?? [];
    $out = [];
    foreach ($photos as $photo) {
        if (!is_array($photo)) {
            continue;
        }
        $src = $photo['src'] ?? [];
        $urlFull = (string) ($src['large'] ?? $src['medium'] ?? $photo['url'] ?? '');
        $thumb = (string) ($src['medium'] ?? $src['small'] ?? $urlFull);
        if ($urlFull === '') {
            continue;
        }
        $out[] = [
            'url' => $urlFull,
            'thumb' => $thumb,
            'title' => (string) ($photo['alt'] ?? $query),
            'author' => (string) ($photo['photographer'] ?? 'Pexels'),
            'source' => 'pexels',
        ];
    }

    return $out;
}

function crmImageSearchWikimedia(string $query, int $limit): array
{
    $params = http_build_query([
        'action' => 'query',
        'generator' => 'search',
        'gsrsearch' => $query . ' filetype:bitmap',
        'gsrlimit' => min(20, max(1, $limit)),
        'gsrnamespace' => 6,
        'prop' => 'imageinfo',
        'iiprop' => 'url|mime|extmetadata',
        'iiurlwidth' => 1280,
        'format' => 'json',
    ]);

    $url = 'https://commons.wikimedia.org/w/api.php?' . $params;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_USERAGENT => 'MultizoneTravels-Quotation/1.0 (CRM itinerary images)',
    ]);
    $raw = curl_exec($ch);
    curl_close($ch);

    if ($raw === false) {
        return [];
    }

    $data = json_decode($raw, true);
    $pages = $data['query']['pages'] ?? [];
    $out = [];
    foreach ($pages as $page) {
        if (!is_array($page)) {
            continue;
        }
        $info = $page['imageinfo'][0] ?? null;
        if (!$info || empty($info['url'])) {
            continue;
        }
        $mime = (string) ($info['mime'] ?? '');
        if ($mime !== '' && strpos($mime, 'image/') !== 0) {
            continue;
        }
        // Prefer resized thumb: originals are often >5MB and fail import.
        $downloadUrl = (string) ($info['thumburl'] ?? $info['url']);
        $title = str_replace(['File:', '_'], ['', ' '], (string) ($page['title'] ?? $query));
        $out[] = [
            'url' => $downloadUrl,
            'thumb' => $downloadUrl,
            'title' => $title,
            'author' => 'Wikimedia Commons',
            'source' => 'wikimedia',
        ];
        if (count($out) >= $limit) {
            break;
        }
    }

    return $out;
}

function crmImageSearch(string $query, int $limit = 12): array
{
    $query = trim(preg_replace('/\s+/', ' ', $query));
    if ($query === '' || mb_strlen($query) < 2) {
        return ['images' => [], 'source' => 'none'];
    }

    $limit = min(20, max(1, $limit));
    $images = crmImageSearchPexels($query, $limit);
    $source = 'pexels';

    if (!$images) {
        $images = crmImageSearchWikimedia($query, $limit);
        $source = 'wikimedia';
    }

    return ['images' => $images, 'source' => $source];
}

function crmImageAllowedImportHost(string $host): bool
{
    $host = strtolower($host);
    $allowed = [
        'images.pexels.com',
        'images.unsplash.com',
        'upload.wikimedia.org',
        'thumb.wikimedia.org',
        'commons.wikimedia.org',
    ];
    foreach ($allowed as $pattern) {
        if ($host === $pattern || substr($host, -strlen('.' . $pattern)) === '.' . $pattern) {
            return true;
        }
    }
    return in_array($host, $allowed, true);
}

function crmImageImportFromUrl(string $url): array
{
    $url = trim($url);
    if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
        return ['ok' => false, 'error' => 'Invalid image URL.'];
    }

    $parts = parse_url($url);
    $host = strtolower((string) ($parts['host'] ?? ''));
    if (!crmImageAllowedImportHost($host)) {
        return ['ok' => false, 'error' => 'Image host is not allowed.'];
    }

    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    if ($scheme !== 'http' && $scheme !== 'https') {
        return ['ok' => false, 'error' => 'Invalid image URL.'];
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 45,
        CURLOPT_USERAGENT => 'MultizoneTravels-Quotation/1.0 (CRM itinerary images)',
        CURLOPT_HTTPHEADER => ['Accept: image/*,*/*;q=0.8'],
    ]);
    $binary = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($binary === false || $code !== 200) {
        $detail = $curlErr !== '' ? $curlErr : ('HTTP ' . $code);
        return ['ok' => false, 'error' => 'Could not download image (' . $detail . ').'];
    }

    if (strlen($binary) > 8 * 1024 * 1024) {
        return ['ok' => false, 'error' => 'Image is too large (max 8MB).'];
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/jpg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];
    $mime = strtolower(trim(explode(';', $contentType)[0]));
    if (!isset($allowed[$mime])) {
        // Detect from magic bytes when CDN omits/misreports Content-Type.
        if (strncmp($binary, "\x89PNG\r\n\x1a\n", 8) === 0) {
            $mime = 'image/png';
        } elseif (strncmp($binary, "\xff\xd8\xff", 3) === 0) {
            $mime = 'image/jpeg';
        } elseif (strncmp($binary, 'GIF87a', 6) === 0 || strncmp($binary, 'GIF89a', 6) === 0) {
            $mime = 'image/gif';
        } elseif (strncmp($binary, 'RIFF', 4) === 0 && substr($binary, 8, 4) === 'WEBP') {
            $mime = 'image/webp';
        } else {
            $mime = 'image/jpeg';
        }
    }
    $ext = $allowed[$mime] ?? 'jpg';

    $uploadDir = __DIR__ . '/../../uploads/quotations/';
    if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
        return ['ok' => false, 'error' => 'Upload folder is missing or not writable.'];
    }
    if (!is_writable($uploadDir)) {
        return ['ok' => false, 'error' => 'Upload folder is not writable.'];
    }

    $filename = 'qt_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (@file_put_contents($uploadDir . $filename, $binary) === false) {
        return ['ok' => false, 'error' => 'Could not save image on server.'];
    }

    return ['ok' => true, 'url' => 'uploads/quotations/' . $filename];
}
