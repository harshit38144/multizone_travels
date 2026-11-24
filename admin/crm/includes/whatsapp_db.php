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

    if (!$conn->query($sql)) {
        return false;
    }

    // Empty provider ids → NULL so UNIQUE allows multiple outbound rows without a SID yet.
    @$conn->query(
        "UPDATE `crm_whatsapp_messages`
         SET `provider_message_id` = NULL
         WHERE `provider_message_id` IS NOT NULL AND `provider_message_id` = ''"
    );

    $idx = $conn->query("SHOW INDEX FROM `crm_whatsapp_messages` WHERE Key_name = 'uniq_wa_provider_message_id'");
    if ($idx && $idx->num_rows === 0) {
        @$conn->query(
            'ALTER TABLE `crm_whatsapp_messages`
             ADD UNIQUE KEY `uniq_wa_provider_message_id` (`provider_message_id`)'
        );
    }

    return true;
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

/**
 * Chronological chat history for a lead (oldest first).
 *
 * @return list<array<string, mixed>>
 */
function crmWhatsappListLeadMessages(mysqli $conn, int $leadId, int $limit = 200): array
{
    if ($leadId <= 0) {
        return [];
    }
    if (!crmWhatsappEnsureTables($conn)) {
        return [];
    }

    $limit = max(1, min(500, $limit));
    $sql = 'SELECT `id`, `lead_id`, `direction`, `phone`, `channel`, `template_name`, `template_language`,
                   `body_text`, `status`, `provider_message_id`, `error_message`, `created_by_name`, `created_at`, `updated_at`
            FROM `crm_whatsapp_messages`
            WHERE `lead_id` = ?
            ORDER BY `id` ASC
            LIMIT ' . $limit;
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('i', $leadId);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    $stmt->close();

    return $rows;
}

/**
 * Messages for a lead with id greater than $afterId (oldest first). Used by chat poll.
 *
 * @return list<array<string, mixed>>
 */
function crmWhatsappListLeadMessagesAfter(mysqli $conn, int $leadId, int $afterId, int $limit = 100): array
{
    if ($leadId <= 0) {
        return [];
    }
    if (!crmWhatsappEnsureTables($conn)) {
        return [];
    }

    $afterId = max(0, $afterId);
    $limit = max(1, min(200, $limit));
    $sql = 'SELECT `id`, `lead_id`, `direction`, `phone`, `channel`, `template_name`, `template_language`,
                   `body_text`, `status`, `provider_message_id`, `error_message`, `created_by_name`, `created_at`, `updated_at`
            FROM `crm_whatsapp_messages`
            WHERE `lead_id` = ? AND `id` > ?
            ORDER BY `id` ASC
            LIMIT ' . $limit;
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('ii', $leadId, $afterId);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    $stmt->close();

    return $rows;
}

/**
 * @return array<string, mixed>|null
 */
function crmWhatsappFindByProviderMessageId(mysqli $conn, string $providerMessageId): ?array
{
    $providerMessageId = trim($providerMessageId);
    if ($providerMessageId === '') {
        return null;
    }
    if (!crmWhatsappEnsureTables($conn)) {
        return null;
    }

    $stmt = $conn->prepare(
        'SELECT `id`, `lead_id`, `direction`, `phone`, `provider_message_id`, `created_at`
         FROM `crm_whatsapp_messages`
         WHERE `provider_message_id` = ?
         LIMIT 1'
    );
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('s', $providerMessageId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    return $row ?: null;
}

/**
 * Match a lead by registered mobile (last-10 / normalized digits).
 * Returns 0 when no active lead matches.
 */
function crmWhatsappFindLeadIdByPhone(mysqli $conn, string $phoneDigits): int
{
    $phoneDigits = preg_replace('/\D+/', '', $phoneDigits);
    if ($phoneDigits === null || $phoneDigits === '') {
        return 0;
    }

    $last10 = strlen($phoneDigits) >= 10 ? substr($phoneDigits, -10) : $phoneDigits;
    if ($last10 === '') {
        return 0;
    }

    if (!function_exists('crmLeadActiveWhereSql')) {
        require_once __DIR__ . '/lead_db.php';
    }

    $like = '%' . $last10;
    $sql = 'SELECT `id`, `customer_phone`
            FROM `crm_leads`
            WHERE ' . crmLeadActiveWhereSql() . "
              AND `customer_phone` IS NOT NULL
              AND `customer_phone` <> ''
              AND REPLACE(REPLACE(REPLACE(REPLACE(`customer_phone`, '+', ''), ' ', ''), '-', ''), '(', '') LIKE ?
            ORDER BY `id` DESC
            LIMIT 40";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param('s', $like);
    $stmt->execute();
    $res = $stmt->get_result();
    $candidates = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $candidates[] = $row;
        }
    }
    $stmt->close();

    foreach ($candidates as $row) {
        $stored = preg_replace('/\D+/', '', (string) ($row['customer_phone'] ?? ''));
        if ($stored === null || $stored === '') {
            continue;
        }
        if ($stored === $phoneDigits) {
            return (int) $row['id'];
        }
        if (strlen($stored) >= 10 && substr($stored, -10) === $last10) {
            return (int) $row['id'];
        }
        if (function_exists('crmWhatsappNormalizePhone')) {
            $norm = crmWhatsappNormalizePhone((string) $row['customer_phone']);
            if ($norm !== '' && ($norm === $phoneDigits || substr($norm, -10) === $last10)) {
                return (int) $row['id'];
            }
        }
    }

    return 0;
}

/**
 * Store an inbound WhatsApp message. Dedupes on provider_message_id (MessageSid).
 *
 * @param array{
 *   lead_id:int,
 *   phone:string,
 *   body_text:string,
 *   provider_message_id:string,
 *   created_at?:string,
 *   provider_response_json?:string
 * } $row
 * @return array{id:int,duplicate:bool,success:bool}
 */
function crmWhatsappInsertInbound(mysqli $conn, array $row): array
{
    $empty = ['id' => 0, 'duplicate' => false, 'success' => false];
    if (!crmWhatsappEnsureTables($conn)) {
        return $empty;
    }

    $leadId = (int) ($row['lead_id'] ?? 0);
    $phone = preg_replace('/\D+/', '', (string) ($row['phone'] ?? ''));
    $body = (string) ($row['body_text'] ?? '');
    $sid = trim((string) ($row['provider_message_id'] ?? ''));
    $createdAt = (string) ($row['created_at'] ?? date('Y-m-d H:i:s'));
    $providerJson = (string) ($row['provider_response_json'] ?? '');

    if ($leadId <= 0 || $phone === '' || $sid === '') {
        return $empty;
    }

    $existing = crmWhatsappFindByProviderMessageId($conn, $sid);
    if ($existing) {
        return [
            'id' => (int) ($existing['id'] ?? 0),
            'duplicate' => true,
            'success' => true,
        ];
    }

    $idempotencyKey = 'twilio-' . $sid;
    if (strlen($idempotencyKey) > 80) {
        $idempotencyKey = 'twilio-' . substr(hash('sha256', $sid), 0, 64);
    }
    $fingerprint = hash('sha256', 'inbound|twilio|' . $sid);
    $channel = 'twilio';
    $direction = 'inbound';
    $status = 'received';
    $createdById = 0;
    $createdByName = 'Twilio';

    $stmt = $conn->prepare(
        'INSERT INTO `crm_whatsapp_messages`
        (`lead_id`, `direction`, `phone`, `channel`, `template_name`, `template_language`,
         `body_text`, `variables_json`, `fingerprint`, `idempotency_key`, `provider_message_id`,
         `status`, `http_status`, `error_message`, `provider_response_json`,
         `created_by_id`, `created_by_name`, `created_at`)
         VALUES (?, ?, ?, ?, NULL, NULL, ?, NULL, ?, ?, ?, ?, NULL, NULL, ?, ?, ?, ?)'
    );
    if (!$stmt) {
        return $empty;
    }

    $stmt->bind_param(
        'isssssssssiss',
        $leadId,
        $direction,
        $phone,
        $channel,
        $body,
        $fingerprint,
        $idempotencyKey,
        $sid,
        $status,
        $providerJson,
        $createdById,
        $createdByName,
        $createdAt
    );

    $ok = $stmt->execute();
    if (!$ok) {
        $errno = (int) $stmt->errno;
        $stmt->close();
        // Duplicate idempotency or provider_message_id (race / Twilio retry).
        if ($errno === 1062) {
            $existing = crmWhatsappFindByProviderMessageId($conn, $sid);
            return [
                'id' => (int) ($existing['id'] ?? 0),
                'duplicate' => true,
                'success' => true,
            ];
        }
        return $empty;
    }

    $id = (int) $stmt->insert_id;
    $stmt->close();

    return ['id' => $id, 'duplicate' => false, 'success' => $id > 0];
}
