<?php
/**
 * Confirm Tour → Primary Contact: payments received from the customer for a quotation.
 * Kept separate from supplier payments (crm_service_payments).
 */
require_once __DIR__ . '/service_payments.php';

function cpEnsureTable(mysqli $conn): void
{
    $conn->query("CREATE TABLE IF NOT EXISTS `crm_customer_payments` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `quotation_id` INT UNSIGNED NOT NULL,
        `lead_id` INT UNSIGNED NOT NULL DEFAULT 0,
        `amount` DECIMAL(12,2) NOT NULL DEFAULT 0,
        `payment_date` DATE NOT NULL,
        `method` VARCHAR(40) NOT NULL DEFAULT '',
        `reference` VARCHAR(120) NOT NULL DEFAULT '',
        `notes` VARCHAR(500) NOT NULL DEFAULT '',
        `created_by` VARCHAR(120) NOT NULL DEFAULT '',
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_cp_quotation` (`quotation_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

/** @return list<array<string, mixed>> */
function cpListPayments(mysqli $conn, int $quotationId): array
{
    $rows = [];
    $stmt = $conn->prepare(
        'SELECT * FROM `crm_customer_payments` WHERE `quotation_id` = ? ORDER BY `payment_date` DESC, `id` DESC'
    );
    if (!$stmt) {
        return $rows;
    }
    $stmt->bind_param('i', $quotationId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($res && ($row = $res->fetch_assoc())) {
        $rows[] = $row;
    }
    $stmt->close();
    return $rows;
}

function cpGetPayment(mysqli $conn, int $id): ?array
{
    $stmt = $conn->prepare('SELECT * FROM `crm_customer_payments` WHERE `id` = ? LIMIT 1');
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

function cpPaidTotal(mysqli $conn, int $quotationId): float
{
    $stmt = $conn->prepare('SELECT COALESCE(SUM(`amount`), 0) AS `paid` FROM `crm_customer_payments` WHERE `quotation_id` = ?');
    if (!$stmt) {
        return 0.0;
    }
    $stmt->bind_param('i', $quotationId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    return round((float) ($row['paid'] ?? 0), 2);
}
