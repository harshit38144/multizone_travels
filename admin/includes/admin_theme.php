<?php

/**
 * Admin panel light/dark theme preference (per admin).
 */

require_once __DIR__ . '/../crm/includes/admin_ui_settings.php';

function adminThemeNormalize(string $mode): string
{
    return $mode === 'dark' ? 'dark' : 'light';
}

function adminThemeLoad(mysqli $conn, int $adminId): string
{
    if ($adminId <= 0) {
        return 'light';
    }

    crmEnsureAdminUiSettingsTable($conn);
    $stored = crmAdminUiSettingGet($conn, $adminId, 'admin_theme_mode', 'light');

    return adminThemeNormalize(is_string($stored) ? $stored : 'light');
}

function adminThemeSave(mysqli $conn, int $adminId, string $mode): string
{
    $normalized = adminThemeNormalize($mode);
    if ($adminId > 0) {
        crmEnsureAdminUiSettingsTable($conn);
        crmAdminUiSettingSet($conn, $adminId, 'admin_theme_mode', $normalized);
    }

    return $normalized;
}

/**
 * Resolve saved theme for header bootstrap (DB when logged in).
 */
function adminThemeResolveForRequest(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }

    if (empty($_SESSION['role']) || (string) $_SESSION['role'] !== '1') {
        return 'light';
    }

    if (!isset($conn) || !($conn instanceof mysqli)) {
        $connPath = __DIR__ . '/../connection.php';
        if (is_file($connPath)) {
            include_once $connPath;
        }
    }

    if (!isset($conn) || !($conn instanceof mysqli)) {
        return 'light';
    }

    return adminThemeLoad($conn, (int) ($_SESSION['id'] ?? 0));
}
