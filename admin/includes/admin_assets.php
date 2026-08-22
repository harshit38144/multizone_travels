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

function adminAssetFileExists(string $relativePath): bool
{
    $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');

    return is_file(__DIR__ . '/../' . $relativePath);
}

/**
 * Built-in admin panel logo/favicon (admin/img/*). Never uses website-only paths.
 */
function adminPanelBrandUrl(string $fallbackRelative): string
{
    $fallbackRelative = ltrim(str_replace('\\', '/', $fallbackRelative), '/');

    return adminAssetUrl($fallbackRelative);
}

/**
 * Admin sidebar / favicon: only use site_settings when the file exists on THIS admin server.
 * Otherwise use bundled admin/img assets (works on admin.multizonetravels.com).
 */
function adminPanelBrandFromSettings(?string $storedPath, string $fallbackRelative): string
{
    $storedPath = trim((string) $storedPath);
    if ($storedPath !== '' && !preg_match('#^https?://#i', $storedPath)) {
        $storedPath = ltrim(str_replace('\\', '/', $storedPath), '/');
        if (adminAssetFileExists($storedPath)) {
            return adminAssetUrl($storedPath);
        }
    }

    return adminPanelBrandUrl($fallbackRelative);
}

/**
 * Public website assets (lead intake etc.) — may load from multizonetravels.com.
 */
function adminBrandAssetUrl(?string $storedPath, string $fallbackRelative): string
{
    $storedPath = trim((string) $storedPath);
    if ($storedPath === '') {
        return adminPanelBrandUrl($fallbackRelative);
    }
    if (preg_match('#^https?://#i', $storedPath)) {
        return $storedPath;
    }

    $storedPath = ltrim(str_replace('\\', '/', $storedPath), '/');

    if (strpos($storedPath, 'images/') === 0) {
        return adminPublicSiteUrl($storedPath);
    }

    if (adminAssetFileExists($storedPath)) {
        return adminAssetUrl($storedPath);
    }

    return adminPanelBrandUrl($fallbackRelative);
}
