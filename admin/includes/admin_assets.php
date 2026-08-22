<?php

/**
 * Root-relative URL for admin static assets (works on localhost subpath and admin subdomain).
 */
function adminAssetUrl(string $relativePath): string
{
    $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
    static $base = null;
    if ($base === null) {
        $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        if (preg_match('#^(.*?)/admin/#', $script, $m)) {
            $base = rtrim($m[1], '/') . '/admin/';
        } else {
            $base = '/';
        }
    }

    return $base . $relativePath;
}

/**
 * Logo / favicon from site_settings, or admin/img fallback.
 */
function adminBrandAssetUrl(?string $storedPath, string $fallbackRelative): string
{
    $storedPath = trim((string) $storedPath);
    if ($storedPath === '') {
        return adminAssetUrl($fallbackRelative);
    }
    if (preg_match('#^https?://#i', $storedPath)) {
        return $storedPath;
    }

    $storedPath = ltrim(str_replace('\\', '/', $storedPath), '/');
    if (strpos($storedPath, 'images/') === 0) {
        $leaf = basename($storedPath);
        $adminImg = __DIR__ . '/../img/' . $leaf;
        if (is_file($adminImg)) {
            return adminAssetUrl('img/' . $leaf);
        }

        return adminAssetUrl($fallbackRelative);
    }

    return adminAssetUrl($storedPath);
}
