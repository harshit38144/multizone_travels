<?php

/**
 * Per-admin UI preferences (column visibility, etc.).
 */

function crmEnsureAdminUiSettingsTable(mysqli $conn): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $conn->query(
        "CREATE TABLE IF NOT EXISTS `crm_admin_ui_settings` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `admin_id` INT NOT NULL,
            `setting_key` VARCHAR(64) NOT NULL,
            `setting_value` MEDIUMTEXT NOT NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_admin_setting` (`admin_id`, `setting_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

function crmAdminUiSettingGet(mysqli $conn, int $adminId, string $key, $default = null)
{
    if ($adminId <= 0 || $key === '') {
        return $default;
    }

    crmEnsureAdminUiSettingsTable($conn);

    $sql = 'SELECT `setting_value` FROM `crm_admin_ui_settings` WHERE `admin_id` = ? AND `setting_key` = ? LIMIT 1';
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return $default;
    }
    $stmt->bind_param('is', $adminId, $key);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    if (!$row || !array_key_exists('setting_value', $row)) {
        return $default;
    }

    $decoded = json_decode((string) $row['setting_value'], true);
    return $decoded !== null ? $decoded : $default;
}

function crmAdminUiSettingSet(mysqli $conn, int $adminId, string $key, $value): bool
{
    if ($adminId <= 0 || $key === '') {
        return false;
    }

    crmEnsureAdminUiSettingsTable($conn);

    $json = json_encode($value, JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return false;
    }

    $now = date('Y-m-d H:i:s');
    $sql = 'INSERT INTO `crm_admin_ui_settings` (`admin_id`, `setting_key`, `setting_value`, `updated_at`)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`), `updated_at` = VALUES(`updated_at`)';
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('isss', $adminId, $key, $json, $now);
    $ok = $stmt->execute();
    $stmt->close();

    return (bool) $ok;
}

/** @return array<string, bool> */
function crmLeadsDefaultColumnVisibility(): array
{
    return [
        'lead' => true,
        'guest' => true,
        'dest' => true,
        'date' => true,
        'services' => true,
        'source' => true,
        'assign' => true,
        'stage' => true,
        'actions' => true,
    ];
}

/** @return array<string, bool> */
function crmLeadsNormalizeColumnVisibility($raw): array
{
    $defaults = crmLeadsDefaultColumnVisibility();
    $locked = ['lead' => true, 'actions' => true];
    $out = $defaults;

    if (!is_array($raw)) {
        return $out;
    }

    foreach ($defaults as $key => $defaultVal) {
        if (isset($locked[$key])) {
            $out[$key] = true;
            continue;
        }
        if (array_key_exists($key, $raw)) {
            $out[$key] = !empty($raw[$key]);
        }
    }

    return $out;
}

/** @return array<string, bool> */
function crmLeadsLoadColumnVisibility(mysqli $conn, int $adminId): array
{
    $stored = crmAdminUiSettingGet($conn, $adminId, 'leads_column_visibility', null);
    return crmLeadsNormalizeColumnVisibility($stored);
}

function crmLeadsSaveColumnVisibility(mysqli $conn, int $adminId, $raw): array
{
    $normalized = crmLeadsNormalizeColumnVisibility($raw);
    crmAdminUiSettingSet($conn, $adminId, 'leads_column_visibility', $normalized);
    return $normalized;
}
