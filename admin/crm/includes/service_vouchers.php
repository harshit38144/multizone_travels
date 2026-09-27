<?php
/**
 * Confirm Tour → Fill details: voucher files per service row (hotel vouchers, tickets, etc.).
 * Rows are identified by the service `uid` stored in tour_confirm_json. Files share the private
 * traveller-docs storage, validation and compression and are served by view_traveller_document.php?kind=voucher.
 */
require_once __DIR__ . '/traveller_documents.php';

const SV_MAX_FILES_PER_SERVICE = 10;

function svEnsureTable(mysqli $conn): void
{
    $conn->query("CREATE TABLE IF NOT EXISTS `crm_service_vouchers` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `quotation_id` INT UNSIGNED NOT NULL,
        `lead_id` INT UNSIGNED NOT NULL DEFAULT 0,
        `service_uid` VARCHAR(60) NOT NULL,
        `service_key` VARCHAR(40) NOT NULL DEFAULT '',
        `file_name` VARCHAR(190) NOT NULL,
        `file_path` VARCHAR(255) NOT NULL,
        `mime_type` VARCHAR(80) NOT NULL DEFAULT '',
        `original_size_kb` INT UNSIGNED NOT NULL DEFAULT 0,
        `file_size_kb` INT UNSIGNED NOT NULL DEFAULT 0,
        `uploaded_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_sv_service` (`quotation_id`, `service_uid`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function svSanitizeUid(string $uid): string
{
    return substr((string) preg_replace('/[^A-Za-z0-9_\-]/', '', trim($uid)), 0, 60);
}

/** @return list<array<string, mixed>> */
function svListVouchers(mysqli $conn, int $quotationId, string $serviceUid): array
{
    $rows = [];
    $stmt = $conn->prepare(
        'SELECT * FROM `crm_service_vouchers` WHERE `quotation_id` = ? AND `service_uid` = ? ORDER BY `uploaded_at`, `id`'
    );
    if (!$stmt) {
        return $rows;
    }
    $stmt->bind_param('is', $quotationId, $serviceUid);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($res && ($row = $res->fetch_assoc())) {
        $rows[] = $row;
    }
    $stmt->close();
    return $rows;
}

function svGetVoucher(mysqli $conn, int $id): ?array
{
    $stmt = $conn->prepare('SELECT * FROM `crm_service_vouchers` WHERE `id` = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    return $row ?: null;
}

/** @return array<string, int> service_uid => file count */
function svVoucherCounts(mysqli $conn, int $quotationId): array
{
    $out = [];
    $stmt = $conn->prepare(
        'SELECT `service_uid`, COUNT(*) AS `n` FROM `crm_service_vouchers` WHERE `quotation_id` = ? GROUP BY `service_uid`'
    );
    if (!$stmt) {
        return $out;
    }
    $stmt->bind_param('i', $quotationId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($res && ($row = $res->fetch_assoc())) {
        $out[(string) $row['service_uid']] = (int) $row['n'];
    }
    $stmt->close();
    return $out;
}

function svPublicVoucher(array $row): array
{
    $v = strtotime((string) $row['uploaded_at']) ?: time();
    $url = 'crm/ajax/view_traveller_document.php?kind=voucher&id=' . (int) $row['id'] . '&v=' . $v;
    $isImage = strpos((string) $row['mime_type'], 'image/') === 0;
    return [
        'id' => (int) $row['id'],
        'service_uid' => (string) $row['service_uid'],
        'file_name' => (string) $row['file_name'],
        'file_size_kb' => (int) $row['file_size_kb'],
        'original_size_kb' => (int) $row['original_size_kb'],
        'mime_type' => (string) $row['mime_type'],
        'is_image' => $isImage,
        'uploaded_at' => (string) $row['uploaded_at'],
        'url' => $url,
        'thumb_url' => $isImage ? $url : null,
    ];
}

function svDeleteVoucherRow(mysqli $conn, array $row): void
{
    $stmt = $conn->prepare('DELETE FROM `crm_service_vouchers` WHERE `id` = ? LIMIT 1');
    if ($stmt) {
        $id = (int) $row['id'];
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
    }
    tdDeleteFiles($row);
}

/** Removes vouchers of service rows that are no longer part of the saved tour. */
function svDeleteOrphans(mysqli $conn, int $quotationId, array $keepUids): void
{
    $keep = array_flip(array_filter(array_map('strval', $keepUids), 'strlen'));
    $stmt = $conn->prepare('SELECT * FROM `crm_service_vouchers` WHERE `quotation_id` = ?');
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('i', $quotationId);
    $stmt->execute();
    $res = $stmt->get_result();
    $orphans = [];
    while ($res && ($row = $res->fetch_assoc())) {
        if (!isset($keep[(string) $row['service_uid']])) {
            $orphans[] = $row;
        }
    }
    $stmt->close();
    foreach ($orphans as $row) {
        svDeleteVoucherRow($conn, $row);
    }
}

/** Adds `voucher_count` to each service of a confirm payload. */
function svAttachCounts(mysqli $conn, int $quotationId, array $services): array
{
    $counts = svVoucherCounts($conn, $quotationId);
    foreach ($services as $i => $svc) {
        $services[$i]['voucher_count'] = $counts[(string) ($svc['uid'] ?? '')] ?? 0;
    }
    return $services;
}
