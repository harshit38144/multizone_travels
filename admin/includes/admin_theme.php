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

    // Public pages (lead intake) may already have $conn from database.php at global scope.
    // Includes inside this function do not inherit that unless we declare global.
    global $conn;

    if (!($conn instanceof mysqli)) {
        $dbPath = __DIR__ . '/../config/database.php';
        if (is_file($dbPath)) {
            require_once $dbPath;
        }
    }

    if (!($conn instanceof mysqli) || $conn->connect_errno) {
        return 'light';
    }

    return adminThemeLoad($conn, (int) ($_SESSION['id'] ?? 0));
}
