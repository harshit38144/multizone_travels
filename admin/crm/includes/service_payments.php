<?php
/**
 * Confirm Tour → Supplier & Services: payment history per service row (identified by the service `uid`
 * in tour_confirm_json). Each recorded payment also adjusts the saved `paid` value of that service.
 */
require_once __DIR__ . '/service_vouchers.php';

function spPaymentMethods(): array
{
    return ['Cash', 'UPI', 'Bank Transfer', 'Cheque', 'Card', 'Other'];
}

function spEnsureTable(mysqli $conn): void
{
    $conn->query("CREATE TABLE IF NOT EXISTS `crm_service_payments` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `quotation_id` INT UNSIGNED NOT NULL,
        `lead_id` INT UNSIGNED NOT NULL DEFAULT 0,
        `service_uid` VARCHAR(60) NOT NULL,
        `service_key` VARCHAR(40) NOT NULL DEFAULT '',
        `amount` DECIMAL(12,2) NOT NULL DEFAULT 0,
        `payment_date` DATE NOT NULL,
        `method` VARCHAR(40) NOT NULL DEFAULT '',
        `reference` VARCHAR(120) NOT NULL DEFAULT '',
        `notes` VARCHAR(500) NOT NULL DEFAULT '',
        `created_by` VARCHAR(120) NOT NULL DEFAULT '',
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_sp_service` (`quotation_id`, `service_uid`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

/** @return list<array<string, mixed>> */
function spListPayments(mysqli $conn, int $quotationId, string $serviceUid): array
{
    $rows = [];
    $stmt = $conn->prepare(
        'SELECT * FROM `crm_service_payments` WHERE `quotation_id` = ? AND `service_uid` = ?
         ORDER BY `payment_date` DESC, `id` DESC'
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

function spGetPayment(mysqli $conn, int $id): ?array
{
    $stmt = $conn->prepare('SELECT * FROM `crm_service_payments` WHERE `id` = ? LIMIT 1');
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

function spPublicPayment(array $row): array
{
    return [
        'id' => (int) $row['id'],
        'amount' => round((float) $row['amount'], 2),
        'payment_date' => (string) $row['payment_date'],
        'method' => (string) $row['method'],
        'reference' => (string) $row['reference'],
        'notes' => (string) $row['notes'],
        'created_by' => (string) $row['created_by'],
        'created_at' => (string) $row['created_at'],
    ];
}

/**
 * Add `delta` to the saved `paid` of the service with this uid (no-op when the row isn't saved yet).
 * Returns the new stored paid amount, or null when the service is not in the saved tour.
 */
function spAdjustStoredPaid(mysqli $conn, int $quotationId, string $serviceUid, float $delta): ?float
{
    $stmt = $conn->prepare('SELECT `tour_confirm_json` FROM `crm_quotations` WHERE `id` = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $quotationId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    $payload = json_decode((string) ($row['tour_confirm_json'] ?? ''), true);
    if (!is_array($payload) || empty($payload['services']) || !is_array($payload['services'])) {
        return null;
    }
    $newPaid = null;
    foreach ($payload['services'] as $i => $svc) {
        if (is_array($svc) && (string) ($svc['uid'] ?? '') === $serviceUid) {
            $newPaid = round(max(0, (float) ($svc['paid'] ?? 0) + $delta), 2);
            $payload['services'][$i]['paid'] = $newPaid;
            break;
        }
    }
    if ($newPaid === null) {
        return null;
    }
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return null;
    }
    $upd = $conn->prepare('UPDATE `crm_quotations` SET `tour_confirm_json` = ? WHERE `id` = ? LIMIT 1');
    if ($upd) {
        $upd->bind_param('si', $json, $quotationId);
        $upd->execute();
        $upd->close();
    }
    return $newPaid;
}

/** Removes payments of service rows that are no longer part of the saved tour. */
function spDeleteOrphans(mysqli $conn, int $quotationId, array $keepUids): void
{
    $keep = array_values(array_filter(array_map('strval', $keepUids), 'strlen'));
    if (!$keep) {
        $stmt = $conn->prepare('DELETE FROM `crm_service_payments` WHERE `quotation_id` = ?');
        if ($stmt) {
            $stmt->bind_param('i', $quotationId);
            $stmt->execute();
            $stmt->close();
        }
        return;
    }
    $placeholders = implode(',', array_fill(0, count($keep), '?'));
    $stmt = $conn->prepare(
        'DELETE FROM `crm_service_payments` WHERE `quotation_id` = ? AND `service_uid` NOT IN (' . $placeholders . ')'
    );
    if ($stmt) {
        $types = 'i' . str_repeat('s', count($keep));
        $stmt->bind_param($types, $quotationId, ...$keep);
        $stmt->execute();
        $stmt->close();
    }
}

/** @return array<string, int> service_uid => payment count */
function spPaymentCounts(mysqli $conn, int $quotationId): array
{
    $out = [];
    $stmt = $conn->prepare(
        'SELECT `service_uid`, COUNT(*) AS `n` FROM `crm_service_payments` WHERE `quotation_id` = ? GROUP BY `service_uid`'
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

/** Adds `payment_count` to each service of a confirm payload. */
function spAttachCounts(mysqli $conn, int $quotationId, array $services): array
{
    $counts = spPaymentCounts($conn, $quotationId);
    foreach ($services as $i => $svc) {
        $services[$i]['payment_count'] = $counts[(string) ($svc['uid'] ?? '')] ?? 0;
    }
    return $services;
}
