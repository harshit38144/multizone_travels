<?php

function adminIsLocalHost(): bool
{
    $host = strtolower(preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost')));

    return in_array($host, ['localhost', '127.0.0.1', '::1'], true);
}

function adminIsAdminSubdomain(): bool
{
    $host = strtolower(preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));

    return strpos($host, 'admin.') === 0;
}

function adminScheme(): string
{
    if (adminIsLocalHost()) {
        $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
            $https = true;
        }

        return $https ? 'https' : 'http';
    }

    return 'https';
}

/**
 * Public website host (multizonetravels.com) when admin runs on admin.* subdomain.
 */
function adminPublicSiteHost(): string
{
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $hostNoPort = strtolower(preg_replace('/:\d+$/', '', $host) ?: $host);

    if (adminIsLocalHost()) {
        return $host;
    }
    if ($hostNoPort === 'admin.multizonetravels.com') {
        return 'multizonetravels.com';
    }
    if (strpos($hostNoPort, 'admin.') === 0) {
        return substr($hostNoPort, 6);
    }

    return $host;
}

function adminPublicSiteUrl(string $relativePath): string
{
    $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));

    if (adminIsLocalHost() && preg_match('#^(.*?)/admin/#', $script, $m)) {
        $projectBase = preg_replace('#/admin/.*$#', '', $script);

        return rtrim($projectBase, '/') . '/' . $relativePath;
    }

    return adminScheme() . '://' . adminPublicSiteHost() . '/' . $relativePath;
}

/**
 * URL for files inside the admin app (admin subdomain docroot or localhost .../admin/).
 */
function adminAssetUrl(string $relativePath): string
{
    $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));

    if (preg_match('#^(.*?)/admin/#', $script, $m)) {
        return rtrim($m[1], '/') . '/admin/' . $relativePath;
    }

    return adminScheme() . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/' . $relativePath;
}

/**
 * Main-site mirror for admin uploads when admin.* docroot has no copy of the file.
 * DB paths like uploads/settings/logo.png may exist only under multizonetravels.com/admin/.
 */
function adminMainSiteAdminUploadUrl(string $relativePath): string
{
    $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');

    return adminPublicSiteUrl('admin/' . $relativePath);
}

function adminDefaultBrandUrl(string $fallbackRelative): string
{
    $fallbackRelative = ltrim(str_replace('\\', '/', $fallbackRelative), '/');
    $localFile = __DIR__ . '/../' . $fallbackRelative;
    if (is_file($localFile)) {
        return adminAssetUrl($fallbackRelative);
    }

    if ($fallbackRelative === 'img/icons1.png') {
        return adminPublicSiteUrl('images/icons1.png');
    }
    if ($fallbackRelative === 'img/web-logo.png') {
        return adminPublicSiteUrl('admin/img/web-logo.png');
    }

    return adminAssetUrl($fallbackRelative);
}

/**
 * Logo / favicon from site_settings for split-domain setup:
 * - admin.multizonetravels.com → admin files + multizonetravels.com/images|admin/uploads
 * - localhost/.../admin → local paths
 */
function adminBrandAssetUrl(?string $storedPath, string $fallbackRelative): string
{
    $storedPath = trim((string) $storedPath);
    if ($storedPath === '') {
        return adminDefaultBrandUrl($fallbackRelative);
    }
    if (preg_match('#^https?://#i', $storedPath)) {
        return $storedPath;
    }

    $storedPath = ltrim(str_replace('\\', '/', $storedPath), '/');

    if (strpos($storedPath, 'images/') === 0) {
        return adminPublicSiteUrl($storedPath);
    }

    $localFile = __DIR__ . '/../' . $storedPath;
    if (is_file($localFile)) {
        return adminAssetUrl($storedPath);
    }

    if (adminIsAdminSubdomain()) {
        return adminMainSiteAdminUploadUrl($storedPath);
    }

    return adminDefaultBrandUrl($fallbackRelative);
}
