<?php

function crmEnsureLeadIntakeTables(mysqli $conn)
{
    $sqlRequests = "CREATE TABLE IF NOT EXISTS `crm_lead_intake_requests` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `token` VARCHAR(64) NOT NULL,
        `admin_id` INT DEFAULT NULL,
        `admin_name` VARCHAR(120) DEFAULT NULL,
        `recipient_name` VARCHAR(150) DEFAULT NULL,
        `recipient_phone` VARCHAR(30) DEFAULT NULL,
        `recipient_email` VARCHAR(190) DEFAULT NULL,
        `lead_source` VARCHAR(60) DEFAULT NULL,
        `referred_by` VARCHAR(150) DEFAULT NULL,
        `assign_to` VARCHAR(120) DEFAULT NULL,
        `field_config` LONGTEXT,
        `note_to_customer` TEXT,
        `status` VARCHAR(20) NOT NULL DEFAULT 'sent',
        `submitted_at` DATETIME DEFAULT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `expires_at` DATETIME DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_intake_token` (`token`),
        KEY `idx_intake_status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $sqlSubmissions = "CREATE TABLE IF NOT EXISTS `crm_lead_intake_submissions` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `intake_request_id` INT UNSIGNED NOT NULL,
        `payload_json` LONGTEXT,
        `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
        `reviewed_by_id` INT DEFAULT NULL,
        `reviewed_by_name` VARCHAR(120) DEFAULT NULL,
        `reviewed_at` DATETIME DEFAULT NULL,
        `review_note` VARCHAR(255) DEFAULT NULL,
        `lead_id` INT UNSIGNED DEFAULT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_intake_sub_request` (`intake_request_id`),
        KEY `idx_intake_sub_status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if (!$conn->query($sqlRequests)) {
        return false;
    }
    if (!$conn->query($sqlSubmissions)) {
        return false;
    }

    $leadCols = [
        'intake_submission_id' => 'INT UNSIGNED DEFAULT NULL AFTER `created_by_name`',
    ];
    foreach ($leadCols as $col => $ddl) {
        $chk = $conn->query("SHOW COLUMNS FROM `crm_leads` LIKE '" . $conn->real_escape_string($col) . "'");
        if ($chk && $chk->num_rows === 0) {
            $conn->query("ALTER TABLE `crm_leads` ADD `" . $col . "` " . $ddl);
        }
    }

    return true;
}

function crmGenerateIntakeToken(?mysqli $conn = null, int $length = 8): string
{
    // Unambiguous alphabet (no 0/O/1/I/l) for short shareable codes
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
    $max = strlen($alphabet) - 1;
    $length = max(6, min(16, $length));

    for ($attempt = 0; $attempt < 12; $attempt++) {
        $token = '';
        for ($i = 0; $i < $length; $i++) {
            $token .= $alphabet[random_int(0, $max)];
        }

        if (!($conn instanceof mysqli)) {
            return $token;
        }

        $stmt = $conn->prepare('SELECT 1 FROM `crm_lead_intake_requests` WHERE `token` = ? LIMIT 1');
        if (!$stmt) {
            return $token;
        }
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $res = $stmt->get_result();
        $exists = $res && $res->num_rows > 0;
        $stmt->close();
        if (!$exists) {
            return $token;
        }
    }

    // Extremely unlikely fallback
    return bin2hex(random_bytes(8));
}

function crmIntakeShortCodeIsValid($code): bool
{
    $code = trim((string) $code);
    return $code !== '' && (bool) preg_match('/^[A-Za-z0-9_-]{6,64}$/', $code);
}

/**
 * Public base path for guest form URLs.
 * Prefer project root when creating links from admin AJAX.
 */
function crmIntakePublicBasePath()
{
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $base = '';
    if (preg_match('#^(.*?)/admin/#', $script, $m)) {
        $base = $m[1];
    } elseif (preg_match('#^(.*?)/public/#', $script, $m)) {
        $base = $m[1];
    } elseif (preg_match('#^(.*?)/f/#', $script, $m)) {
        $base = $m[1];
    } elseif (preg_match('#^(.*?)/f(?:/|$)#', $script, $m)) {
        $base = $m[1];
    }
    return rtrim($base, '/');
}

function crmIntakeIsLocalHost($host)
{
    $host = strtolower(trim((string) $host));
    $host = preg_replace('/:\d+$/', '', $host) ?: $host;
    return $host === 'localhost' || $host === '127.0.0.1' || $host === '::1';
}

/**
 * Guest form lives on the public website, not the admin subdomain.
 * admin.multizonetravels.com → multizonetravels.com
 */
function crmIntakePublicHost()
{
    $host = strtolower(trim((string) ($_SERVER['HTTP_HOST'] ?? 'localhost')));
    $host = preg_replace('/:\d+$/', '', $host) ?: $host;
    if (crmIntakeIsLocalHost($host)) {
        return $_SERVER['HTTP_HOST'] ?? 'localhost';
    }
    if ($host === 'admin.multizonetravels.com') {
        return 'multizonetravels.com';
    }
    if (strpos($host, 'admin.') === 0) {
        return substr($host, 6);
    }
    return $_SERVER['HTTP_HOST'] ?? $host;
}

function crmIntakePublicScheme()
{
    $host = crmIntakePublicHost();
    $hostNoPort = preg_replace('/:\d+$/', '', strtolower((string) $host)) ?: $host;
    if (crmIntakeIsLocalHost($hostNoPort)) {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    }
    return 'https';
}

function crmPublicAdminAssetBase()
{
    $host = strtolower(trim((string) ($_SERVER['HTTP_HOST'] ?? 'localhost')));
    $host = preg_replace('/:\d+$/', '', $host) ?: $host;
    if (crmIntakeIsLocalHost($host)) {
        return '../admin/';
    }
    $scheme = 'https';
    if (strpos($host, 'admin.') === 0) {
        return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? $host) . '/';
    }
    if (preg_match('/(^|\.)multizonetravels\.com$/', $host)) {
        return 'https://admin.multizonetravels.com/';
    }
    return $scheme . '://admin.' . $host . '/';
}

function crmResolveIntakeLogoUrl($logoPath)
{
    $logoPath = str_replace('\\', '/', trim((string) $logoPath));
    if ($logoPath === '') {
        $logoPath = 'img/web-logo.png';
    }
    if (preg_match('/^https?:\/\//i', $logoPath)) {
        return $logoPath;
    }
    $logoPath = ltrim($logoPath, '/');
    if (strpos($logoPath, 'admin/') === 0) {
        $logoPath = substr($logoPath, 6);
    }
    // Common site_settings paths that live on the main site folder tree
    if (strpos($logoPath, 'images/') === 0 || strpos($logoPath, 'uploads/') === 0) {
        $host = strtolower(preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')) ?: '');
        if (!crmIntakeIsLocalHost($host) && preg_match('/(^|\.)multizonetravels\.com$/', $host)) {
            // Prefer admin panel logo which is reliably deployed
            return 'https://admin.multizonetravels.com/img/web-logo.png';
        }
    }
    $base = crmPublicAdminAssetBase();
    return rtrim($base, '/') . '/' . $logoPath;
}

/**
 * Absolute fallback Multizone logo used if configured logo fails to load.
 */
function crmIntakeFallbackLogoUrl()
{
    $host = strtolower(preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')) ?: '');
    if (crmIntakeIsLocalHost($host)) {
        return '../admin/img/web-logo.png';
    }
    return 'https://admin.multizonetravels.com/img/web-logo.png';
}

/**
 * Absolute image URL for WhatsApp / Open Graph link previews.
 */
function crmIntakeShareImageUrl()
{
    $host = strtolower(preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')) ?: '');
    if (crmIntakeIsLocalHost($host)) {
        $scheme = crmIntakePublicScheme();
        $path = rtrim(crmIntakePublicBasePath(), '/');
        return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $path . '/admin/img/travel-inquiry-og.jpg';
    }
    return 'https://admin.multizonetravels.com/img/travel-inquiry-og.jpg';
}

/**
 * Canonical absolute URL for the current intake form page (for og:url).
 */
function crmIntakeCanonicalPageUrl($token = '')
{
    $token = trim((string) $token);
    if ($token !== '') {
        return crmBuildIntakePublicUrl($token);
    }
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        $scheme = strtolower(trim(explode(',', (string) $_SERVER['HTTP_X_FORWARDED_PROTO'])[0]));
    }
    $host = (string) ($_SERVER['HTTP_HOST'] ?? crmIntakePublicHost());
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    return $scheme . '://' . $host . $uri;
}

function crmBuildIntakePublicUrl($token)
{
    $token = trim((string) $token);
    $path = crmIntakeIsLocalHost(crmIntakePublicHost()) ? crmIntakePublicBasePath() : '';
    // Short branded path: /f/Ab3xK9m2
    return crmIntakePublicScheme() . '://' . crmIntakePublicHost() . $path . '/f/' . rawurlencode($token);
}

function crmBuildIntakeThanksUrl($token)
{
    $token = trim((string) $token);
    $path = crmIntakeIsLocalHost(crmIntakePublicHost()) ? crmIntakePublicBasePath() : '';
    return crmIntakePublicScheme() . '://' . crmIntakePublicHost() . $path . '/f/' . rawurlencode($token) . '/thanks';
}

/**
 * Legacy long URL (kept for old WhatsApp messages / bookmarks).
 */
function crmBuildIntakePublicUrlLegacy($token)
{
    $token = trim((string) $token);
    $path = crmIntakeIsLocalHost(crmIntakePublicHost()) ? crmIntakePublicBasePath() : '';
    return crmIntakePublicScheme() . '://' . crmIntakePublicHost() . $path . '/public/lead_intake.php?token=' . rawurlencode($token);
}

/**
 * Absolute same-origin submit URL. Must not be relative: <base href> on the
 * public form points at admin.multizonetravels.com, which would steal the POST.
 * Always targets /public/ajax/… even when the form is opened via short /f/{code}.
 */
function crmBuildIntakeSubmitUrl()
{
    $base = rtrim((string) crmIntakePublicBasePath(), '/');
    $ajaxPath = $base . '/public/ajax/submit_lead_intake.php';

    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $hostNoPort = strtolower(preg_replace('/:\d+$/', '', $host) ?: $host);
    $scheme = 'https';
    if (crmIntakeIsLocalHost($hostNoPort)) {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
            $scheme = 'https';
        }
    }

    return $scheme . '://' . $host . $ajaxPath;
}

function crmFormatIntakeInquiryId($submissionId, $createdAt = '')
{
    $id = max(0, (int) $submissionId);
    $ts = $createdAt !== '' ? strtotime((string) $createdAt) : time();
    if ($ts === false) {
        $ts = time();
    }
    return sprintf('INQ-%s-%s-%04d', date('Y', $ts), date('md', $ts), $id);
}

function crmFormatIntakePhoneDisplay($phone)
{
    $phone = trim((string) $phone);
    if ($phone === '') {
        return '—';
    }
    $digits = preg_replace('/\D+/', '', $phone) ?? '';
    if (strlen($digits) === 12 && strpos($digits, '91') === 0) {
        return '+91 ' . substr($digits, 2, 5) . ' ' . substr($digits, 7);
    }
    if (strlen($digits) === 10) {
        return '+91 ' . substr($digits, 0, 5) . ' ' . substr($digits, 5);
    }
    return $phone;
}

function crmFormatIntakeGuestSummary($payload)
{
    $adults = (int) ($payload['tp_adults'] ?? 0);
    $children = (int) ($payload['tp_children'] ?? 0);
    $parts = [];
    if ($adults > 0) {
        $parts[] = $adults . ' ' . ($adults === 1 ? 'Adult' : 'Adults');
    }
    if ($children > 0) {
        $parts[] = $children . ' ' . ($children === 1 ? 'Child' : 'Children');
    }
    return $parts ? implode(', ', $parts) : '—';
}

function crmResolveIntakeDestinationNames(mysqli $conn, $payload)
{
    $raw = $payload['tp_destination'] ?? [];
    if (!is_array($raw)) {
        $raw = $raw !== '' && $raw !== null ? [$raw] : [];
    }
    $ids = [];
    foreach ($raw as $item) {
        $id = (int) $item;
        if ($id > 0) {
            $ids[] = $id;
        }
    }
    $ids = array_values(array_unique($ids));
    if (!$ids) {
        return '—';
    }
    $in = implode(',', $ids);
    $names = [];
    $res = $conn->query("SELECT `id`, `name` FROM `destinations` WHERE `id` IN ({$in})");
    if ($res) {
        $map = [];
        while ($row = $res->fetch_assoc()) {
            $map[(int) $row['id']] = trim((string) ($row['name'] ?? ''));
        }
        foreach ($ids as $id) {
            if (!empty($map[$id])) {
                $names[] = $map[$id];
            }
        }
    }
    return $names ? implode(', ', $names) : '—';
}

function crmBuildIntakeThanksSummary(mysqli $conn, array $payload, $submissionId, $createdAt = '')
{
    $initial = trim((string) ($payload['customer_initial'] ?? ''));
    $name = trim((string) ($payload['customer_name'] ?? ''));
    $displayName = $name;
    if ($initial !== '' && $name !== '') {
        $displayName = rtrim($initial, '.') . '. ' . $name;
    } elseif ($initial !== '' && $name === '') {
        $displayName = rtrim($initial, '.') . '.';
    }
    if ($displayName === '') {
        $displayName = '—';
    }

    $nights = (int) ($payload['itinerary_total_nights'] ?? 0);
    $package = $nights > 0
        ? ($nights . ' Night' . ($nights === 1 ? '' : 's') . ' Tour Package')
        : 'Tour Package';

    $services = $payload['services'] ?? [];
    if (!is_array($services)) {
        $services = [];
    }
    if ($nights <= 0 && !in_array('tour_package', $services, true)) {
        $labels = [
            'tour_package' => 'Tour Package',
            'hotel' => 'Hotel',
            'flight' => 'Flight',
            'vehicle' => 'Vehicle',
            'visa' => 'Visa',
        ];
        $pkgParts = [];
        foreach ($services as $svc) {
            $key = (string) $svc;
            if (isset($labels[$key])) {
                $pkgParts[] = $labels[$key];
            }
        }
        if ($pkgParts) {
            $package = implode(', ', $pkgParts);
        }
    }

    $ts = $createdAt !== '' ? strtotime((string) $createdAt) : time();
    if ($ts === false) {
        $ts = time();
    }

    return [
        'inquiry_id' => crmFormatIntakeInquiryId($submissionId, $createdAt),
        'submitted_at_display' => date('d M Y, h:i A', $ts),
        'destination' => crmResolveIntakeDestinationNames($conn, $payload),
        'package' => $package,
        'name' => $displayName,
        'email' => trim((string) ($payload['customer_email'] ?? '')) ?: '—',
        'phone' => crmFormatIntakePhoneDisplay($payload['customer_phone'] ?? ''),
        'guests' => crmFormatIntakeGuestSummary($payload),
    ];
}

function crmFetchLatestIntakeSubmissionByToken(mysqli $conn, $token)
{
    $token = trim((string) $token);
    if ($token === '') {
        return null;
    }
    $sql = "SELECT s.id AS submission_id, s.payload_json, s.created_at AS submitted_at,
            r.id AS request_id, r.token, r.status AS request_status
        FROM `crm_lead_intake_requests` r
        INNER JOIN `crm_lead_intake_submissions` s ON s.intake_request_id = r.id
        WHERE r.token = ?
        ORDER BY s.id DESC
        LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    return $row ?: null;
}
