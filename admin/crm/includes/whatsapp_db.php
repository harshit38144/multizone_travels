<?php

/**
 * Minimal outbound WhatsApp log.
 * Email lives in mail_messages and is not reused here.
 * This CRM has one company, so there is no tenant id. The sender is the admin session user.
 */

function crmWhatsappEnsureTables(mysqli $conn): bool
{
    $sql = "CREATE TABLE IF NOT EXISTS `crm_whatsapp_messages` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `lead_id` INT UNSIGNED NOT NULL,
        `direction` VARCHAR(16) NOT NULL DEFAULT 'outbound',
        `phone` VARCHAR(20) NOT NULL,
        `channel` VARCHAR(16) NOT NULL,
        `template_name` VARCHAR(120) DEFAULT NULL,
        `template_language` VARCHAR(20) DEFAULT NULL,
        `body_text` TEXT,
        `variables_json` TEXT,
        `fingerprint` CHAR(64) NOT NULL,
        `idempotency_key` VARCHAR(80) NOT NULL,
        `provider_message_id` VARCHAR(64) DEFAULT NULL,
        `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
        `http_status` SMALLINT UNSIGNED DEFAULT NULL,
        `error_message` VARCHAR(500) DEFAULT NULL,
        `provider_response_json` TEXT,
        `created_by_id` INT UNSIGNED NOT NULL DEFAULT 0,
        `created_by_name` VARCHAR(120) DEFAULT NULL,
        `created_at` DATETIME NOT NULL,
        `updated_at` DATETIME DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_wa_idempotency` (`idempotency_key`),
        KEY `idx_wa_lead_fp` (`lead_id`, `fingerprint`, `created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    return (bool) $conn->query($sql);
}

function crmWhatsappNewIdempotencyKey(int $leadId): string
{
    return 'lead-' . $leadId . '-' . bin2hex(random_bytes(16));
}

/**
 * @return array<string, mixed>|null
 */
function crmWhatsappFindRecentSend(mysqli $conn, int $leadId, string $fingerprint): ?array
{
    $since = date('Y-m-d H:i:s', time() - 86400);
    $stmt = $conn->prepare(
        'SELECT `id`, `status`, `provider_message_id`, `error_message`, `idempotency_key`, `created_at`
         FROM `crm_whatsapp_messages`
         WHERE `lead_id` = ? AND `fingerprint` = ?
           AND `created_at` >= ?
           AND `status` IN (\'pending\', \'unknown\', \'accepted\')
         ORDER BY `id` DESC
         LIMIT 1'
    );
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('iss', $leadId, $fingerprint, $since);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    return $row ?: null;
}

/**
 * @param array<string, mixed> $row
 */
function crmWhatsappInsertPending(mysqli $conn, array $row): int
{
    $stmt = $conn->prepare(
        'INSERT INTO `crm_whatsapp_messages`
        (`lead_id`, `direction`, `phone`, `channel`, `template_name`, `template_language`,
         `body_text`, `variables_json`, `fingerprint`, `idempotency_key`, `status`,
         `created_by_id`, `created_by_name`, `created_at`)
         VALUES (?, \'outbound\', ?, ?, ?, ?, ?, ?, ?, ?, \'pending\', ?, ?, ?)'
    );
    if (!$stmt) {
        return 0;
    }

    $leadId = (int) $row['lead_id'];
    $phone = (string) $row['phone'];
    $channel = (string) $row['channel'];
    $templateName = (string) ($row['template_name'] ?? '');
    $templateLanguage = (string) ($row['template_language'] ?? '');
    $body = (string) ($row['body_text'] ?? '');
    $variables = (string) ($row['variables_json'] ?? '');
    $fingerprint = (string) $row['fingerprint'];
    $idempotencyKey = (string) $row['idempotency_key'];
    $createdById = (int) ($row['created_by_id'] ?? 0);
    $createdByName = (string) ($row['created_by_name'] ?? '');
    $createdAt = (string) $row['created_at'];

    $stmt->bind_param(
        'issssssssiss',
        $leadId,
        $phone,
        $channel,
        $templateName,
        $templateLanguage,
        $body,
        $variables,
        $fingerprint,
        $idempotencyKey,
        $createdById,
        $createdByName,
        $createdAt
    );
    $ok = $stmt->execute();
    $id = $ok ? (int) $stmt->insert_id : 0;
    $stmt->close();

    return $id;
}

function crmWhatsappUpdateResult(
    mysqli $conn,
    int $id,
    string $status,
    ?int $httpStatus,
    string $providerMessageId,
    string $errorMessage,
    string $providerResponseJson
): void {
    if ($id <= 0) {
        return;
    }

    $stmt = $conn->prepare(
        'UPDATE `crm_whatsapp_messages`
         SET `status` = ?, `http_status` = ?, `provider_message_id` = ?,
             `error_message` = ?, `provider_response_json` = ?, `updated_at` = ?
         WHERE `id` = ?'
    );
    if (!$stmt) {
        return;
    }

    $updatedAt = date('Y-m-d H:i:s');
    $http = $httpStatus === null ? 0 : $httpStatus;
    $stmt->bind_param(
        'sissssi',
        $status,
        $http,
        $providerMessageId,
        $errorMessage,
        $providerResponseJson,
        $updatedAt,
        $id
    );
    $stmt->execute();
    $stmt->close();
}

function crmWhatsappAcquireLeadLock(mysqli $conn, int $leadId): bool
{
    $name = 'crm_wa_lead_' . $leadId;
    $stmt = $conn->prepare('SELECT GET_LOCK(?, 8) AS locked');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('s', $name);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    return (int) ($row['locked'] ?? 0) === 1;
}

function crmWhatsappReleaseLeadLock(mysqli $conn, int $leadId): void
{
    $name = 'crm_wa_lead_' . $leadId;
    $stmt = $conn->prepare('SELECT RELEASE_LOCK(?)');
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('s', $name);
    $stmt->execute();
    $stmt->close();
}
