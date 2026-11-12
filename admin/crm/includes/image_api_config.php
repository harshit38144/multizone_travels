<?php
/**
 * Image search API settings for itinerary (reads ai_config.local.php).
 *
 * Optional:
 * - pexels_api_key — https://www.pexels.com/api/
 * - unsplash_access_key / unsplash_secret_key / unsplash_application_id — https://unsplash.com/oauth/applications
 *
 * Secret keys stay server-side only. Without Pexels, Wikimedia Commons is used as fallback for that provider.
 */

function crmImageApiSettings(): array
{
    static $settings = null;
    if ($settings !== null) {
        return $settings;
    }

    $settings = [
        'pexels_api_key' => '',
        'unsplash_access_key' => '',
        'unsplash_secret_key' => '',
        'unsplash_application_id' => '',
    ];
    $localFile = __DIR__ . '/ai_config.local.php';
    if (is_file($localFile)) {
        $cfg = include $localFile;
        if (is_array($cfg)) {
            foreach (array_keys($settings) as $key) {
                if (!empty($cfg[$key]) && is_string($cfg[$key])) {
                    $settings[$key] = trim($cfg[$key]);
                }
            }
        }
    }

    $envMap = [
        'CRM_PEXELS_API_KEY' => 'pexels_api_key',
        'CRM_UNSPLASH_ACCESS_KEY' => 'unsplash_access_key',
        'CRM_UNSPLASH_SECRET_KEY' => 'unsplash_secret_key',
        'CRM_UNSPLASH_APPLICATION_ID' => 'unsplash_application_id',
    ];
    foreach ($envMap as $envName => $settingKey) {
        $env = getenv($envName);
        if ($env !== false && $env !== '') {
            $settings[$settingKey] = trim((string) $env);
        }
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

function crmImageSearchPexels(string $query, int $limit, int $page = 1): array
{
    $empty = [
        'images' => [],
        'page' => max(1, $page),
        'per_page' => min(30, max(1, $limit)),
        'total_results' => 0,
        'has_more' => false,
    ];

    $key = crmImageApiSettings()['pexels_api_key'] ?? '';
    if ($key === '') {
        return $empty;
    }

    $perPage = min(30, max(1, $limit));
    $page = max(1, $page);

    $url = 'https://api.pexels.com/v1/search?' . http_build_query([
        'query' => $query,
        'per_page' => $perPage,
        'page' => $page,
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
        return $empty;
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return $empty;
    }

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
            'id' => (string) ($photo['id'] ?? ''),
            'url' => $urlFull,
            'thumb' => $thumb,
            'title' => (string) ($photo['alt'] ?? $query),
            'author' => (string) ($photo['photographer'] ?? 'Pexels'),
            'source' => 'pexels',
        ];
    }

    $total = (int) ($data['total_results'] ?? 0);
    $loadedThrough = ($page - 1) * $perPage + count($out);
    $hasMore = $total > 0
        ? ($loadedThrough < $total)
        : (!empty($data['next_page']) && count($out) >= $perPage);

    return [
        'images' => $out,
        'page' => $page,
        'per_page' => $perPage,
        'total_results' => $total,
        'has_more' => $hasMore,
    ];
}

function crmImageSearchUnsplash(string $query, int $limit, int $page = 1): array
{
    $empty = [
        'images' => [],
        'page' => max(1, $page),
        'per_page' => min(30, max(1, $limit)),
        'total_results' => 0,
        'has_more' => false,
        'error' => '',
    ];

    $accessKey = crmImageApiSettings()['unsplash_access_key'] ?? '';
    if ($accessKey === '') {
        $empty['error'] = 'Unsplash is not configured. Add unsplash_access_key in ai_config.local.php.';
        return $empty;
    }

    $perPage = min(30, max(1, $limit));
    $page = max(1, $page);

    $url = 'https://api.unsplash.com/search/photos?' . http_build_query([
        'query' => $query,
        'per_page' => $perPage,
        'page' => $page,
        'orientation' => 'landscape',
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => [
            'Authorization: Client-ID ' . $accessKey,
            'Accept-Version: v1',
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_USERAGENT => 'MultizoneTravels-Quotation/1.0 (CRM itinerary images)',
    ]);
    $raw = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($raw === false || $code !== 200) {
        $empty['error'] = $code === 401 || $code === 403
            ? 'Unsplash authentication failed. Check the access key.'
            : 'Unsplash search failed. Please try again.';
        return $empty;
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        $empty['error'] = 'Unsplash returned an invalid response.';
        return $empty;
    }

    $results = $data['results'] ?? [];
    $out = [];
    foreach ($results as $photo) {
        if (!is_array($photo)) {
            continue;
        }
        $urls = $photo['urls'] ?? [];
        $urlFull = (string) ($urls['regular'] ?? $urls['full'] ?? $urls['small'] ?? '');
        $thumb = (string) ($urls['small'] ?? $urls['thumb'] ?? $urlFull);
        if ($urlFull === '') {
            continue;
        }
        $user = is_array($photo['user'] ?? null) ? $photo['user'] : [];
        $author = trim((string) ($user['name'] ?? $user['username'] ?? 'Unsplash'));
        $desc = trim((string) ($photo['alt_description'] ?? $photo['description'] ?? ''));
        if ($desc === '') {
            $desc = $query;
        }
        $out[] = [
            'id' => (string) ($photo['id'] ?? ''),
            'url' => $urlFull,
            'thumb' => $thumb,
            'title' => $desc,
            'author' => $author !== '' ? $author : 'Unsplash',
            'source' => 'unsplash',
            'download_location' => (string) (($photo['links']['download_location'] ?? '') ?: ''),
        ];
    }

    $total = (int) ($data['total'] ?? 0);
    $totalPages = (int) ($data['total_pages'] ?? 0);
    $hasMore = $totalPages > 0
        ? ($page < $totalPages)
        : ($total > 0 && (($page - 1) * $perPage + count($out)) < $total);

    return [
        'images' => $out,
        'page' => $page,
        'per_page' => $perPage,
        'total_results' => $total,
        'has_more' => $hasMore,
        'error' => '',
    ];
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

function crmImageSearch(string $query, int $limit = 18, int $page = 1, string $provider = 'pexels'): array
{
    $query = trim(preg_replace('/\s+/', ' ', $query));
    $page = max(1, $page);
    $limit = min(30, max(1, $limit));
    $provider = strtolower(trim($provider));
    if ($provider !== 'unsplash') {
        $provider = 'pexels';
    }

    if ($query === '' || mb_strlen($query) < 2) {
        return [
            'images' => [],
            'source' => 'none',
            'provider' => $provider,
            'page' => $page,
            'per_page' => $limit,
            'total_results' => 0,
            'has_more' => false,
            'error' => '',
        ];
    }

    if ($provider === 'unsplash') {
        $unsplash = crmImageSearchUnsplash($query, $limit, $page);
        return [
            'images' => $unsplash['images'] ?? [],
            'source' => 'unsplash',
            'provider' => 'unsplash',
            'page' => (int) ($unsplash['page'] ?? $page),
            'per_page' => (int) ($unsplash['per_page'] ?? $limit),
            'total_results' => (int) ($unsplash['total_results'] ?? 0),
            'has_more' => !empty($unsplash['has_more']),
            'error' => (string) ($unsplash['error'] ?? ''),
        ];
    }

    $pexels = crmImageSearchPexels($query, $limit, $page);
    if (!empty($pexels['images'])) {
        return [
            'images' => $pexels['images'],
            'source' => 'pexels',
            'provider' => 'pexels',
            'page' => (int) ($pexels['page'] ?? $page),
            'per_page' => (int) ($pexels['per_page'] ?? $limit),
            'total_results' => (int) ($pexels['total_results'] ?? 0),
            'has_more' => !empty($pexels['has_more']),
            'error' => '',
        ];
    }

    // Wikimedia has no reliable page API here — only first page.
    if ($page > 1) {
        return [
            'images' => [],
            'source' => 'wikimedia',
            'provider' => 'pexels',
            'page' => $page,
            'per_page' => $limit,
            'total_results' => 0,
            'has_more' => false,
            'error' => '',
        ];
    }

    $images = crmImageSearchWikimedia($query, $limit);

    return [
        'images' => $images,
        'source' => 'wikimedia',
        'provider' => 'pexels',
        'page' => 1,
        'per_page' => $limit,
        'total_results' => count($images),
        'has_more' => false,
        'error' => '',
    ];
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

/**
 * Unsplash guideline: hit download_location once when an image is selected/downloaded.
 * Failures are ignored — they must never block import.
 */
function crmImageNotifyUnsplashDownload(string $downloadLocation): void
{
    $downloadLocation = trim($downloadLocation);
    if ($downloadLocation === '' || !filter_var($downloadLocation, FILTER_VALIDATE_URL)) {
        return;
    }
    $host = strtolower((string) (parse_url($downloadLocation, PHP_URL_HOST) ?? ''));
    if ($host !== 'api.unsplash.com' && substr($host, -strlen('.unsplash.com')) !== '.unsplash.com') {
        return;
    }
    $accessKey = crmImageApiSettings()['unsplash_access_key'] ?? '';
    if ($accessKey === '') {
        return;
    }
    $ch = curl_init($downloadLocation);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => [
            'Authorization: Client-ID ' . $accessKey,
            'Accept-Version: v1',
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_USERAGENT => 'MultizoneTravels-Quotation/1.0 (CRM itinerary images)',
    ]);
    curl_exec($ch);
    curl_close($ch);
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
