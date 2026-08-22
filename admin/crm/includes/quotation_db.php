<?php

/**
 * Schema + helpers for the Quotation Generator.
 */

function crmEnsureQuotationTables(mysqli $conn)
{
    $sql = "CREATE TABLE IF NOT EXISTS `crm_quotations` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `quotation_uid` VARCHAR(40) NOT NULL,
        `guest_name` VARCHAR(150) NOT NULL,
        `reference_name` VARCHAR(150) DEFAULT NULL,
        `mobile_no` VARCHAR(40) DEFAULT NULL,
        `email` VARCHAR(190) DEFAULT NULL,
        `destination` VARCHAR(190) DEFAULT NULL,
        `tentative_date` DATE DEFAULT NULL,
        `no_of_nights` INT DEFAULT 0,
        `no_of_adults` INT DEFAULT 1,
        `no_of_children` INT DEFAULT 0,
        `flights_json` LONGTEXT,
        `hotels_json` LONGTEXT,
        `itinerary_json` LONGTEXT,
        `inclusion` MEDIUMTEXT,
        `exclusion` MEDIUMTEXT,
        `payment_policy` MEDIUMTEXT,
        `cancellation_policy` MEDIUMTEXT,
        `terms_conditions` MEDIUMTEXT,
        `other_details` MEDIUMTEXT,
        `cost_sheet_json` LONGTEXT,
        `total_cost` DECIMAL(14,2) DEFAULT 0,
        `profit_type` VARCHAR(10) DEFAULT 'percent',
        `profit_value` DECIMAL(14,2) DEFAULT 0,
        `package_total` DECIMAL(14,2) DEFAULT 0,
        `price_per_adult` DECIMAL(14,2) DEFAULT 0,
        `quotation_total` DECIMAL(14,2) DEFAULT 0,
        `without_itinerary` TINYINT(1) DEFAULT 0,
        `hide_gst_note` TINYINT(1) DEFAULT 0,
        `created_by_id` INT DEFAULT NULL,
        `created_by_name` VARCHAR(120) DEFAULT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_quotation_uid` (`quotation_uid`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $conn->query($sql);

    $cols = [
        'tour_confirmed' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'tour_confirm_json' => 'LONGTEXT DEFAULT NULL',
        'lead_id' => 'INT UNSIGNED DEFAULT NULL',
        'version' => 'INT UNSIGNED NOT NULL DEFAULT 1',
        'status' => "VARCHAR(20) NOT NULL DEFAULT 'published'",
        'wizard_step' => 'TINYINT UNSIGNED NOT NULL DEFAULT 1',
    ];
    foreach ($cols as $col => $ddl) {
        $chk = $conn->query("SHOW COLUMNS FROM `crm_quotations` LIKE '" . $conn->real_escape_string($col) . "'");
        if ($chk && $chk->num_rows === 0) {
            $conn->query("ALTER TABLE `crm_quotations` ADD `" . $col . "` " . $ddl);
        }
    }

    $conn->query("CREATE TABLE IF NOT EXISTS `crm_quotation_versions` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `quotation_id` INT UNSIGNED NOT NULL,
        `lead_id` INT UNSIGNED DEFAULT NULL,
        `version` INT UNSIGNED NOT NULL DEFAULT 1,
        `quotation_uid` VARCHAR(40) NOT NULL,
        `snapshot_json` LONGTEXT NOT NULL,
        `saved_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_qv_quotation` (`quotation_id`),
        KEY `idx_qv_lead` (`lead_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

/** @return array<string, string> */
function crmQuotationConfirmServiceMap()
{
    return [
        'visa' => 'Visa',
        'hotels' => 'Hotels',
        'land_package' => 'Land Package',
        'forex' => 'Forex',
        'train' => 'Train',
        'flight' => 'Flight',
        'travel_insurance' => 'Travel Insurance',
        'transfers' => 'Transfers',
        'tours' => 'Tours',
        'cruise' => 'Cruise',
    ];
}

/**
 * @return 'full'|'half'|'minimum'|null
 */
function crmQuotationPaymentLevel($total, $paid)
{
    $total = (float) $total;
    $paid = (float) $paid;
    if ($paid <= 0) {
        return null;
    }
    if ($total <= 0) {
        return 'minimum';
    }
    if ($paid >= $total) {
        return 'full';
    }
    if ($paid >= ($total / 2)) {
        return 'half';
    }
    return 'minimum';
}

function crmQuotationNormalizeConfirmPayload($raw)
{
    $map = crmQuotationConfirmServiceMap();
    $services = [];
    if (is_array($raw)) {
        $list = isset($raw['services']) && is_array($raw['services']) ? $raw['services'] : [];
        foreach ($list as $row) {
            if (!is_array($row)) {
                continue;
            }
            $key = trim((string) ($row['key'] ?? ''));
            if ($key === '' || !isset($map[$key])) {
                continue;
            }
            $total = (float) ($row['total'] ?? 0);
            $paid = (float) ($row['paid'] ?? 0);
            $services[] = [
                'key' => $key,
                'label' => $map[$key],
                'supplier' => trim((string) ($row['supplier'] ?? '')),
                'total' => $total,
                'paid' => $paid,
                'balance' => max(0, round($total - $paid, 2)),
            ];
        }
    }

    return [
        'guest_name' => trim((string) ($raw['guest_name'] ?? '')),
        'mobile_no' => trim((string) ($raw['mobile_no'] ?? '')),
        'services' => $services,
    ];
}

function crmQuotationRenderStatusBadges($tourConfirmJson)
{
    $payload = json_decode((string) $tourConfirmJson, true);
    if (!is_array($payload) || empty($payload['services'])) {
        return '<span class="text-muted">—</span>';
    }

    $html = '<div class="q-status-wrap">';
    foreach ($payload['services'] as $svc) {
        if (!is_array($svc)) {
            continue;
        }
        $label = htmlspecialchars((string) ($svc['label'] ?? $svc['key'] ?? 'Service'));
        $level = crmQuotationPaymentLevel($svc['total'] ?? 0, $svc['paid'] ?? 0);
        if ($level === null) {
            $html .= '<span class="q-status-badge status-added">' . $label . '</span>';
            continue;
        }
        $class = [
            'full' => 'status-full',
            'half' => 'status-half',
            'minimum' => 'status-minimum',
        ][$level];
        $html .= '<span class="q-status-badge ' . $class . '">' . $label . '</span>';
    }
    $html .= '</div>';

    return $html;
}

function crmQuotationLeadUid(mysqli $conn, int $leadId): string
{
    if ($leadId <= 0) {
        return '';
    }

    $stmt = $conn->prepare('SELECT `lead_uid` FROM `crm_leads` WHERE `id` = ? LIMIT 1');
    if (!$stmt) {
        return '';
    }
    $stmt->bind_param('i', $leadId);
    if (!$stmt->execute()) {
        $stmt->close();
        return '';
    }
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    return trim((string) ($row['lead_uid'] ?? ''));
}

function crmQuotationUidAvailable(mysqli $conn, string $uid, int $excludeQuotationId = 0): bool
{
    $uid = trim($uid);
    if ($uid === '') {
        return false;
    }

    $stmt = $conn->prepare(
        'SELECT `id` FROM `crm_quotations` WHERE `quotation_uid` = ? AND `id` != ? LIMIT 1'
    );
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('si', $uid, $excludeQuotationId);
    if (!$stmt->execute()) {
        $stmt->close();
        return false;
    }
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    return !$row;
}

function crmQuotationUpdateUid(mysqli $conn, int $quotationId, string $uid): void
{
    $uid = trim($uid);
    if ($quotationId <= 0 || $uid === '') {
        return;
    }

    $stmt = $conn->prepare('UPDATE `crm_quotations` SET `quotation_uid` = ? WHERE `id` = ?');
    if ($stmt) {
        $stmt->bind_param('si', $uid, $quotationId);
        $stmt->execute();
        $stmt->close();
    }

    $verStmt = $conn->prepare('UPDATE `crm_quotation_versions` SET `quotation_uid` = ? WHERE `quotation_id` = ?');
    if ($verStmt) {
        $verStmt->bind_param('si', $uid, $quotationId);
        $verStmt->execute();
        $verStmt->close();
    }
}

function crmQuotationSyncUidFromLead(mysqli $conn, int $quotationId, int $leadId): bool
{
    if ($quotationId <= 0 || $leadId <= 0) {
        return false;
    }

    $leadUid = crmQuotationLeadUid($conn, $leadId);
    if ($leadUid === '' || !crmQuotationUidAvailable($conn, $leadUid, $quotationId)) {
        return false;
    }

    crmQuotationUpdateUid($conn, $quotationId, $leadUid);

    return true;
}

function crmQuotationNextLdSerial(mysqli $conn): int
{
    $next = 1;
    $sql = "SELECT MAX(`max_serial`) AS max_serial FROM (
                SELECT MAX(CAST(SUBSTRING(`lead_uid`, 4) AS UNSIGNED)) AS max_serial
                FROM `crm_leads`
                WHERE `lead_uid` REGEXP '^LD-[0-9]{4}$'
                UNION ALL
                SELECT MAX(CAST(SUBSTRING(`quotation_uid`, 4) AS UNSIGNED)) AS max_serial
                FROM `crm_quotations`
                WHERE `quotation_uid` REGEXP '^LD-[0-9]{4}$'
            ) AS serials";
    $result = $conn->query($sql);
    if ($result) {
        $row = $result->fetch_assoc();
        if ($row && $row['max_serial'] !== null && $row['max_serial'] !== '') {
            $next = max(1, (int) $row['max_serial']) + 1;
        }
        $result->free();
    }

    return $next;
}

function crmGenerateOrphanQuotationUid(mysqli $conn): string
{
    $serial = crmQuotationNextLdSerial($conn);
    $uid = 'LD-' . str_pad((string) $serial, 4, '0', STR_PAD_LEFT);
    while (!crmQuotationUidAvailable($conn, $uid, 0)) {
        $serial++;
        $uid = 'LD-' . str_pad((string) $serial, 4, '0', STR_PAD_LEFT);
    }

    return $uid;
}

/**
 * Use the linked lead UID when available; otherwise assign the next LD-XXXX id.
 */
function crmResolveQuotationUid(mysqli $conn, int $leadId = 0, int $excludeQuotationId = 0): string
{
    if ($leadId > 0) {
        $leadUid = crmQuotationLeadUid($conn, $leadId);
        if ($leadUid !== '' && crmQuotationUidAvailable($conn, $leadUid, $excludeQuotationId)) {
            return $leadUid;
        }
    }

    return crmGenerateOrphanQuotationUid($conn);
}

/**
 * Set quotation_uid to the linked lead's lead_uid wherever possible.
 */
function crmSyncQuotationUidsFromLeads(mysqli $conn): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    crmEnsureQuotationTables($conn);

    $res = $conn->query(
        'SELECT q.`id`, q.`quotation_uid`, q.`lead_id`, l.`lead_uid`
         FROM `crm_quotations` q
         INNER JOIN `crm_leads` l ON l.`id` = q.`lead_id`
         WHERE q.`lead_id` > 0 AND l.`lead_uid` != \'\'
         ORDER BY q.`id` ASC'
    );
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $quotationId = (int) ($row['id'] ?? 0);
            $leadUid = trim((string) ($row['lead_uid'] ?? ''));
            if ($quotationId <= 0 || $leadUid === '') {
                continue;
            }
            if ((string) ($row['quotation_uid'] ?? '') === $leadUid) {
                continue;
            }
            crmQuotationSyncUidFromLead($conn, $quotationId, (int) ($row['lead_id'] ?? 0));
        }
        $res->free();
    }

    $unlinked = $conn->query(
        'SELECT `id`, `mobile_no`, `email`, `lead_id`
         FROM `crm_quotations`
         WHERE (`lead_id` IS NULL OR `lead_id` = 0)
         ORDER BY `id` ASC'
    );
    if ($unlinked) {
        while ($row = $unlinked->fetch_assoc()) {
            $quotationId = (int) ($row['id'] ?? 0);
            if ($quotationId <= 0) {
                continue;
            }
            $resolvedLeadId = crmQuotationResolveLeadId($conn, $row);
            if ($resolvedLeadId <= 0) {
                continue;
            }
            crmQuotationPersistLeadLink($conn, $quotationId, $resolvedLeadId);
            crmQuotationSyncUidFromLead($conn, $quotationId, $resolvedLeadId);
        }
        $unlinked->free();
    }
}

/** @deprecated Use crmResolveQuotationUid() */
function crmGenerateQuotationUid(mysqli $conn, int $leadId = 0)
{
    crmSyncQuotationUidsFromLeads($conn);

    return crmResolveQuotationUid($conn, $leadId);
}

function crmQuotationNormalizePhone($phone): string
{
    return preg_replace('/\D+/', '', (string) $phone);
}

function crmQuotationNormalizeEmail($email): string
{
    return strtolower(trim((string) $email));
}

/**
 * Find a lead for a quotation when lead_id is not stored yet.
 * Uses the same phone/email matching as the leads list quotation column.
 */
function crmQuotationResolveLeadId(mysqli $conn, array $quotation): int
{
    $leadId = (int) ($quotation['lead_id'] ?? 0);
    if ($leadId > 0) {
        return $leadId;
    }

    $phone = crmQuotationNormalizePhone($quotation['mobile_no'] ?? '');
    $email = crmQuotationNormalizeEmail($quotation['email'] ?? '');
    if ($phone === '' && $email === '') {
        return 0;
    }

    $activeSql = '(`deleted_at` IS NULL)';

    if ($phone !== '') {
        $res = $conn->query(
            'SELECT `id`, `customer_phone`
             FROM `crm_leads`
             WHERE ' . $activeSql . '
             ORDER BY `id` DESC'
        );
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                if (crmQuotationNormalizePhone($row['customer_phone'] ?? '') === $phone) {
                    return (int) ($row['id'] ?? 0);
                }
            }
        }
    }

    if ($email !== '') {
        $stmt = $conn->prepare(
            'SELECT `id`
             FROM `crm_leads`
             WHERE ' . $activeSql . ' AND LOWER(TRIM(`customer_email`)) = ?
             ORDER BY `id` DESC
             LIMIT 1'
        );
        if ($stmt) {
            $stmt->bind_param('s', $email);
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                $row = $result ? $result->fetch_assoc() : null;
                if ($row) {
                    $stmt->close();
                    return (int) ($row['id'] ?? 0);
                }
            }
            $stmt->close();
        }
    }

    return 0;
}

function crmQuotationPersistLeadLink(mysqli $conn, int $quotationId, int $leadId): void
{
    if ($quotationId <= 0 || $leadId <= 0) {
        return;
    }

    crmEnsureQuotationTables($conn);
    $stmt = $conn->prepare(
        'UPDATE `crm_quotations`
         SET `lead_id` = ?
         WHERE `id` = ? AND (`lead_id` IS NULL OR `lead_id` = 0)'
    );
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('ii', $leadId, $quotationId);
    $stmt->execute();
    $stmt->close();

    crmQuotationSyncUidFromLead($conn, $quotationId, $leadId);
}

function crmQuotationArchiveCurrentVersion(mysqli $conn, int $quotationId): bool
{
    if ($quotationId <= 0) {
        return false;
    }

    $stmt = $conn->prepare('SELECT * FROM `crm_quotations` WHERE `id` = ? LIMIT 1');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('i', $quotationId);
    if (!$stmt->execute()) {
        $stmt->close();
        return false;
    }
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    if (!$row) {
        return false;
    }

    $version = max(1, (int) ($row['version'] ?? 1));
    $leadId = (int) ($row['lead_id'] ?? 0);
    $leadIdParam = $leadId > 0 ? $leadId : null;
    $uid = (string) ($row['quotation_uid'] ?? '');
    $snapshot = json_encode($row, JSON_UNESCAPED_UNICODE);
    if ($snapshot === false) {
        $snapshot = '{}';
    }

    $insert = $conn->prepare(
        'INSERT INTO `crm_quotation_versions`
            (`quotation_id`, `lead_id`, `version`, `quotation_uid`, `snapshot_json`)
         VALUES (?, ?, ?, ?, ?)'
    );
    if (!$insert) {
        return false;
    }
    $insert->bind_param('iiiss', $quotationId, $leadIdParam, $version, $uid, $snapshot);
    $ok = $insert->execute();
    $insert->close();

    return $ok;
}

function crmQuotationLoadArchivedVersion(mysqli $conn, int $quotationId, int $version): ?array
{
    if ($quotationId <= 0 || $version <= 0) {
        return null;
    }

    $stmt = $conn->prepare(
        'SELECT `snapshot_json`
         FROM `crm_quotation_versions`
         WHERE `quotation_id` = ? AND `version` = ?
         LIMIT 1'
    );
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('ii', $quotationId, $version);
    if (!$stmt->execute()) {
        $stmt->close();
        return null;
    }
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    if (!$row || empty($row['snapshot_json'])) {
        return null;
    }

    $snapshot = json_decode((string) $row['snapshot_json'], true);

    return is_array($snapshot) ? $snapshot : null;
}

/**
 * @param array<string, mixed> $quotation
 * @return array<string, mixed>
 */
function crmQuotationRowToPrefill(array $quotation): array
{
    return [
        'id' => (int) ($quotation['id'] ?? 0),
        'quotation_uid' => (string) ($quotation['quotation_uid'] ?? ''),
        'guest_name' => (string) ($quotation['guest_name'] ?? ''),
        'reference_name' => (string) ($quotation['reference_name'] ?? ''),
        'mobile_no' => (string) ($quotation['mobile_no'] ?? ''),
        'email' => (string) ($quotation['email'] ?? ''),
        'destination' => (string) ($quotation['destination'] ?? ''),
        'tentative_date' => (string) ($quotation['tentative_date'] ?? ''),
        'no_of_nights' => (int) ($quotation['no_of_nights'] ?? 0),
        'no_of_adults' => (int) ($quotation['no_of_adults'] ?? 1),
        'no_of_children' => (int) ($quotation['no_of_children'] ?? 0),
        'flights' => json_decode($quotation['flights_json'] ?? '[]', true) ?: [],
        'hotels' => (static function ($raw) {
            $decoded = json_decode((string) $raw, true);
            if (!is_array($decoded)) {
                return [];
            }
            // Keep new multi-option shape; fall back to flat list / empty.
            if (isset($decoded['categories']) && is_array($decoded['categories'])) {
                return $decoded;
            }
            return $decoded;
        })($quotation['hotels_json'] ?? '[]'),
        'itinerary' => json_decode($quotation['itinerary_json'] ?? '[]', true) ?: [],
        'inclusion' => (string) ($quotation['inclusion'] ?? ''),
        'exclusion' => (string) ($quotation['exclusion'] ?? ''),
        'payment_policy' => (string) ($quotation['payment_policy'] ?? ''),
        'cancellation_policy' => (string) ($quotation['cancellation_policy'] ?? ''),
        'terms_conditions' => (string) ($quotation['terms_conditions'] ?? ''),
        'other_details' => (string) ($quotation['other_details'] ?? ''),
        'cost_sheet' => json_decode($quotation['cost_sheet_json'] ?? '{}', true) ?: [],
        'profit_type' => (string) ($quotation['profit_type'] ?? 'percent'),
        'profit_value' => (float) ($quotation['profit_value'] ?? 0),
        'price_per_adult' => (float) ($quotation['price_per_adult'] ?? 0),
        'without_itinerary' => (int) ($quotation['without_itinerary'] ?? 0),
        'hide_gst_note' => (int) ($quotation['hide_gst_note'] ?? 0),
        'lead_id' => (int) ($quotation['lead_id'] ?? 0),
        'version' => max(1, (int) ($quotation['version'] ?? 1)),
        'status' => (string) ($quotation['status'] ?? 'published'),
        'wizard_step' => max(1, min(6, (int) ($quotation['wizard_step'] ?? 1))),
    ];
}

function crmQuotationIsDraft(array $quotation): bool
{
    return ($quotation['status'] ?? 'published') === 'draft';
}

/**
 * @param array<string, mixed> $quotationRow
 * @param array<int, array<string, mixed>> $versionRows
 * @return array<int, array<string, mixed>>
 */
function crmQuotationBuildDisplayLines(array $quotationRow, array $versionRows): array
{
    $lines = [];
    $currentVersion = max(1, (int) ($quotationRow['version'] ?? 1));
    $qid = (int) ($quotationRow['id'] ?? 0);
    $uid = (string) ($quotationRow['quotation_uid'] ?? '');

    $lines[] = [
        'quotation_id' => $qid,
        'quotation_uid' => $uid,
        'version' => $currentVersion,
        'is_current' => true,
    ];

    foreach ($versionRows as $vr) {
        if (!is_array($vr)) {
            continue;
        }
        $lines[] = [
            'quotation_id' => $qid,
            'quotation_uid' => (string) ($vr['quotation_uid'] ?? $uid),
            'version' => max(1, (int) ($vr['version'] ?? 1)),
            'is_current' => false,
        ];
    }

    usort($lines, static function ($a, $b) {
        return ((int) $b['version']) <=> ((int) $a['version']);
    });

    $maxVersion = !empty($lines) ? (int) $lines[0]['version'] : 1;
    foreach ($lines as &$line) {
        $line['show_version'] = $maxVersion > 1;
    }
    unset($line);

    return $lines;
}

/**
 * @param array<int, array<string, mixed>> $lines
 * @return array<int, array<string, mixed>>
 */
function crmLeadsGroupQuotationLines(array $lines): array
{
    $groups = [];

    foreach ($lines as $line) {
        if (!is_array($line)) {
            continue;
        }

        $qid = (int) ($line['quotation_id'] ?? 0);
        if ($qid <= 0) {
            continue;
        }

        if (!isset($groups[$qid])) {
            $groups[$qid] = [
                'quotation_id' => $qid,
                'quotation_uid' => (string) ($line['quotation_uid'] ?? ''),
                'versions' => [],
                'show_versions' => false,
                'current_href' => 'crm/quotation_generator.php?id=' . $qid,
            ];
        }

        $ver = max(1, (int) ($line['version'] ?? 1));
        $href = 'crm/quotation_generator.php?id=' . $qid;
        if (empty($line['is_current'])) {
            $href .= '&version=' . $ver;
        } elseif (!empty($line['show_version'])) {
            $groups[$qid]['current_href'] = $href;
        }

        $groups[$qid]['versions'][] = [
            'version' => $ver,
            'is_current' => !empty($line['is_current']),
            'href' => $href,
        ];

        if (!empty($line['show_version'])) {
            $groups[$qid]['show_versions'] = true;
        }
    }

    foreach ($groups as &$group) {
        usort($group['versions'], static function ($a, $b) {
            return ((int) $b['version']) <=> ((int) $a['version']);
        });
    }
    unset($group);

    return array_values($groups);
}

/**
 * @param array<string, mixed> $lead
 * @return array{ver:int,qid:int,href:string}|null
 */
function crmLeadLatestQuotationPick(array $lead): ?array
{
    $groups = $lead['quotation_groups'] ?? [];
    if (empty($groups)) {
        return null;
    }

    $best = null;
    foreach ($groups as $group) {
        if (!is_array($group)) {
            continue;
        }

        $currentVer = 1;
        foreach ($group['versions'] ?? [] as $vLine) {
            if (!empty($vLine['is_current'])) {
                $currentVer = max(1, (int) ($vLine['version'] ?? 1));
                break;
            }
        }

        $qid = (int) ($group['quotation_id'] ?? 0);
        if ($best === null
            || $currentVer > $best['ver']
            || ($currentVer === $best['ver'] && $qid > $best['qid'])) {
            $best = [
                'ver' => $currentVer,
                'qid' => $qid,
                'href' => (string) ($group['current_href'] ?? ('crm/quotation_generator.php?id=' . $qid)),
            ];
        }
    }

    return $best;
}

/**
 * @param array<string, mixed> $lead
 */
function crmLeadLatestQuotationHref(array $lead): string
{
    $best = crmLeadLatestQuotationPick($lead);

    return $best ? (string) $best['href'] : '';
}

/**
 * @param array<string, mixed>|null $currentRow
 * @return array<int, array<string, mixed>>
 */
function crmQuotationGetVersionOptions(mysqli $conn, int $quotationId, ?array $currentRow = null): array
{
    if ($quotationId <= 0) {
        return [];
    }

    if ($currentRow === null) {
        $stmt = $conn->prepare(
            'SELECT `id`, `quotation_uid`, `version`, `status`, `updated_at`
             FROM `crm_quotations` WHERE `id` = ? LIMIT 1'
        );
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('i', $quotationId);
        if (!$stmt->execute()) {
            $stmt->close();
            return [];
        }
        $res = $stmt->get_result();
        $currentRow = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        if (!$currentRow) {
            return [];
        }
    }

    $currentVersion = max(1, (int) ($currentRow['version'] ?? 1));
    $options = [[
        'version' => $currentVersion,
        'is_current' => true,
        'href' => 'crm/quotation_generator.php?id=' . $quotationId,
        'label' => 'Version ' . $currentVersion . ' (Latest)',
        'saved_at' => (string) ($currentRow['updated_at'] ?? ''),
        'status' => (string) ($currentRow['status'] ?? 'published'),
    ]];

    $stmt = $conn->prepare(
        'SELECT `version`, `saved_at`
         FROM `crm_quotation_versions`
         WHERE `quotation_id` = ?
         ORDER BY `version` DESC'
    );
    if ($stmt) {
        $stmt->bind_param('i', $quotationId);
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            while ($row = $res ? $res->fetch_assoc() : null) {
                if (!$row) {
                    break;
                }
                $ver = max(1, (int) ($row['version'] ?? 1));
                if ($ver >= $currentVersion) {
                    continue;
                }
                $options[] = [
                    'version' => $ver,
                    'is_current' => false,
                    'href' => 'crm/quotation_generator.php?id=' . $quotationId . '&version=' . $ver,
                    'label' => 'Version ' . $ver,
                    'saved_at' => (string) ($row['saved_at'] ?? ''),
                    'status' => 'archived',
                ];
            }
        }
        $stmt->close();
    }

    usort($options, static function ($a, $b) {
        return ((int) $b['version']) <=> ((int) $a['version']);
    });

    return $options;
}

/**
 * @param array<string, mixed> $leadRow
 */
function crmLeadHasLinkedQuotation(mysqli $conn, int $leadId, array $leadRow = []): bool
{
    if ($leadId <= 0) {
        return false;
    }

    crmEnsureQuotationTables($conn);

    $stmt = $conn->prepare('SELECT `id` FROM `crm_quotations` WHERE `lead_id` = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('i', $leadId);
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            if ($res && $res->fetch_assoc()) {
                $stmt->close();
                return true;
            }
        }
        $stmt->close();
    }

    $phone = crmQuotationNormalizePhone($leadRow['customer_phone'] ?? '');
    $email = crmQuotationNormalizeEmail($leadRow['customer_email'] ?? '');

    // Match unlinked quotations by normalized phone/email (same logic as attach).
    $res2 = $conn->query(
        'SELECT `mobile_no`, `email` FROM `crm_quotations`
         WHERE (`lead_id` IS NULL OR `lead_id` = 0)
         ORDER BY `id` DESC
         LIMIT 500'
    );
    if ($res2) {
        while ($row = $res2->fetch_assoc()) {
            $qPhone = crmQuotationNormalizePhone($row['mobile_no'] ?? '');
            $qEmail = crmQuotationNormalizeEmail($row['email'] ?? '');
            if ($phone !== '' && $qPhone !== '' && $qPhone === $phone) {
                return true;
            }
            if ($email !== '' && $qEmail !== '' && $qEmail === $email) {
                return true;
            }
        }
    }

    return false;
}

/**
 * @param array<string, mixed> $leadRow
 */
function crmLeadHasConfirmedQuotation(mysqli $conn, int $leadId, array $leadRow = []): bool
{
    if ($leadId <= 0) {
        return false;
    }

    crmEnsureQuotationTables($conn);

    $stmt = $conn->prepare('SELECT `id` FROM `crm_quotations` WHERE `lead_id` = ? AND `tour_confirmed` = 1 LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('i', $leadId);
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            if ($res && $res->fetch_assoc()) {
                $stmt->close();
                return true;
            }
        }
        $stmt->close();
    }

    $phone = crmQuotationNormalizePhone($leadRow['customer_phone'] ?? '');
    $email = crmQuotationNormalizeEmail($leadRow['customer_email'] ?? '');

    $res2 = $conn->query(
        'SELECT `mobile_no`, `email`, `tour_confirmed` FROM `crm_quotations`
         WHERE (`lead_id` IS NULL OR `lead_id` = 0) AND `tour_confirmed` = 1
         ORDER BY `id` DESC
         LIMIT 500'
    );
    if ($res2) {
        while ($row = $res2->fetch_assoc()) {
            $qPhone = crmQuotationNormalizePhone($row['mobile_no'] ?? '');
            $qEmail = crmQuotationNormalizeEmail($row['email'] ?? '');
            if ($phone !== '' && $qPhone !== '' && $qPhone === $phone) {
                return true;
            }
            if ($email !== '' && $qEmail !== '' && $qEmail === $email) {
                return true;
            }
        }
    }

    return false;
}

/**
 * @param array<int, array<string, mixed>> $leadRows
 */
function crmLeadsAttachQuotationLines(mysqli $conn, array &$leadRows): void
{
    if (empty($leadRows)) {
        return;
    }

    crmEnsureQuotationTables($conn);

    $leadById = [];
    $phones = [];
    $emails = [];
    foreach ($leadRows as &$lead) {
        $lead['quotation_lines'] = [];
        $lead['quotation_groups'] = [];
        $lead['latest_quotation_href'] = '';
        $lead['latest_quotation_id'] = 0;
        $lead['latest_quotation_status'] = '';
        $lead['has_quotation'] = false;
        $lead['is_tour_confirmed'] = false;
        $lid = (int) ($lead['id'] ?? 0);
        if ($lid <= 0) {
            continue;
        }
        $leadById[$lid] = true;
        $phone = crmQuotationNormalizePhone($lead['customer_phone'] ?? '');
        $email = crmQuotationNormalizeEmail($lead['customer_email'] ?? '');
        if ($phone !== '') {
            $phones[$phone][] = $lid;
        }
        if ($email !== '') {
            $emails[$email][] = $lid;
        }
    }
    unset($lead);

    $leadIds = array_keys($leadById);
    if (empty($leadIds)) {
        return;
    }

    $idsSql = implode(',', array_map('intval', $leadIds));
    $quotations = [];
    $matchedIds = [];

    $res = $conn->query(
        'SELECT `id`, `lead_id`, `quotation_uid`, `version`, `mobile_no`, `email`, `tour_confirmed`, `status`
         FROM `crm_quotations`
         WHERE `lead_id` IN (' . $idsSql . ')
         ORDER BY `id` DESC'
    );
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $quotations[] = $row;
            $matchedIds[(int) ($row['id'] ?? 0)] = true;
        }
    }

    $res2 = $conn->query(
        'SELECT `id`, `lead_id`, `quotation_uid`, `version`, `mobile_no`, `email`, `tour_confirmed`, `status`
         FROM `crm_quotations`
         WHERE (`lead_id` IS NULL OR `lead_id` = 0)
         ORDER BY `id` DESC'
    );
    if ($res2) {
        while ($row = $res2->fetch_assoc()) {
            $qid = (int) ($row['id'] ?? 0);
            if ($qid <= 0 || !empty($matchedIds[$qid])) {
                continue;
            }

            $targetLead = 0;
            $phone = crmQuotationNormalizePhone($row['mobile_no'] ?? '');
            $email = crmQuotationNormalizeEmail($row['email'] ?? '');
            if ($phone !== '' && !empty($phones[$phone])) {
                $targetLead = (int) $phones[$phone][0];
            } elseif ($email !== '' && !empty($emails[$email])) {
                $targetLead = (int) $emails[$email][0];
            }

            if ($targetLead > 0) {
                $row['lead_id'] = $targetLead;
                $quotations[] = $row;
                $matchedIds[$qid] = true;
            }
        }
    }

    $byLead = [];
    foreach ($quotations as $q) {
        $lid = (int) ($q['lead_id'] ?? 0);
        if ($lid <= 0 || !isset($leadById[$lid])) {
            continue;
        }
        if (!isset($byLead[$lid])) {
            $byLead[$lid] = [];
        }
        $byLead[$lid][] = $q;
    }

    $versionsByQ = [];
    $qIds = array_values(array_unique(array_map(static function ($q) {
        return (int) ($q['id'] ?? 0);
    }, $quotations)));
    $qIds = array_values(array_filter($qIds));
    if (!empty($qIds)) {
        $qIdsSql = implode(',', array_map('intval', $qIds));
        $vRes = $conn->query(
            'SELECT `quotation_id`, `version`, `quotation_uid`
             FROM `crm_quotation_versions`
             WHERE `quotation_id` IN (' . $qIdsSql . ')
             ORDER BY `version` DESC'
        );
        if ($vRes) {
            while ($vr = $vRes->fetch_assoc()) {
                $qid = (int) ($vr['quotation_id'] ?? 0);
                if (!isset($versionsByQ[$qid])) {
                    $versionsByQ[$qid] = [];
                }
                $versionsByQ[$qid][] = $vr;
            }
        }
    }

    foreach ($leadRows as &$lead) {
        $lid = (int) ($lead['id'] ?? 0);
        $lines = [];
        foreach ($byLead[$lid] ?? [] as $q) {
            $qid = (int) ($q['id'] ?? 0);
            $lines = array_merge($lines, crmQuotationBuildDisplayLines($q, $versionsByQ[$qid] ?? []));
        }
        usort($lines, static function ($a, $b) {
            if ((int) $a['version'] !== (int) $b['version']) {
                return ((int) $b['version']) <=> ((int) $a['version']);
            }

            return ((int) $b['quotation_id']) <=> ((int) $a['quotation_id']);
        });
        $lead['quotation_lines'] = $lines;
        $lead['quotation_groups'] = crmLeadsGroupQuotationLines($lines);
        $pick = crmLeadLatestQuotationPick($lead);
        $lead['latest_quotation_href'] = $pick ? (string) $pick['href'] : '';
        $lead['latest_quotation_id'] = $pick ? (int) $pick['qid'] : 0;
        $lead['latest_quotation_status'] = '';

        $leadQuotations = $byLead[$lid] ?? [];
        $lead['has_quotation'] = !empty($leadQuotations);
        $lead['is_tour_confirmed'] = false;
        $lead['latest_is_tour_confirmed'] = false;
        foreach ($leadQuotations as $qRow) {
            $qid = (int) ($qRow['id'] ?? 0);
            $rowConfirmed = (int) ($qRow['tour_confirmed'] ?? 0) === 1;
            if ($lead['latest_quotation_id'] > 0 && $qid === $lead['latest_quotation_id']) {
                $lead['latest_quotation_status'] = (string) ($qRow['status'] ?? '');
                $lead['latest_is_tour_confirmed'] = $rowConfirmed;
            }
            if ($rowConfirmed) {
                $lead['is_tour_confirmed'] = true;
            }
        }
    }
    unset($lead);
}

/**
 * Normalize destination for itinerary matching (case/spacing insensitive).
 */
function crmQuotationNormalizeDestinationKey(string $destination): string
{
    $destination = strtolower(trim($destination));
    $destination = preg_replace('/\s+/u', ' ', $destination) ?? $destination;
    return $destination;
}

/**
 * True when two destination strings refer to the same place (exact or containment).
 */
function crmQuotationDestinationsMatch(string $a, string $b): bool
{
    $a = crmQuotationNormalizeDestinationKey($a);
    $b = crmQuotationNormalizeDestinationKey($b);
    if ($a === '' || $b === '') {
        return false;
    }
    if ($a === $b) {
        return true;
    }
    $lenA = function_exists('mb_strlen') ? mb_strlen($a) : strlen($a);
    $lenB = function_exists('mb_strlen') ? mb_strlen($b) : strlen($b);
    if ($lenA < 3 || $lenB < 3) {
        return false;
    }
    if (function_exists('mb_strpos')) {
        return (mb_strpos($a, $b) !== false) || (mb_strpos($b, $a) !== false);
    }
    return (strpos($a, $b) !== false) || (strpos($b, $a) !== false);
}

/**
 * @param array<int, mixed> $days
 */
function crmQuotationItineraryHasContent(array $days): bool
{
    foreach ($days as $day) {
        if (!is_array($day)) {
            continue;
        }
        $title = trim((string) ($day['title'] ?? ''));
        $desc = trim(strip_tags((string) ($day['description'] ?? '')));
        if ($title !== '' || $desc !== '') {
            return true;
        }
    }
    return false;
}

/**
 * Find a previously saved itinerary with the same destination + nights.
 * Checks prior quotations first, then published/draft packages.
 *
 * @return array<string, mixed>|null
 */
function crmFindMatchingPreviousItinerary(
    mysqli $conn,
    string $destination,
    int $nights,
    int $excludeQuotationId = 0
): ?array {
    crmEnsureQuotationTables($conn);

    $destination = trim($destination);
    $nights = max(0, $nights);
    if ($destination === '' || $nights < 1) {
        return null;
    }

    $exactMatch = null;
    $fuzzyMatch = null;

    $sql = 'SELECT `id`, `quotation_uid`, `guest_name`, `destination`, `no_of_nights`, `itinerary_json`, `updated_at`
            FROM `crm_quotations`
            WHERE `no_of_nights` = ?
              AND IFNULL(`without_itinerary`, 0) = 0
              AND `itinerary_json` IS NOT NULL
              AND TRIM(`itinerary_json`) NOT IN (\'\', \'[]\', \'null\')';
    if ($excludeQuotationId > 0) {
        $sql .= ' AND `id` != ?';
    }
    $sql .= ' ORDER BY `updated_at` DESC, `id` DESC LIMIT 80';

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        if ($excludeQuotationId > 0) {
            $stmt->bind_param('ii', $nights, $excludeQuotationId);
        } else {
            $stmt->bind_param('i', $nights);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        while ($res && ($row = $res->fetch_assoc())) {
            $rowDest = trim((string) ($row['destination'] ?? ''));
            if (!crmQuotationDestinationsMatch($destination, $rowDest)) {
                continue;
            }
            $days = json_decode((string) ($row['itinerary_json'] ?? '[]'), true);
            if (!is_array($days) || !crmQuotationItineraryHasContent($days)) {
                continue;
            }
            $payload = [
                'match_type' => 'quotation',
                'match_id' => (int) ($row['id'] ?? 0),
                'match_label' => trim((string) ($row['quotation_uid'] ?? '')) !== ''
                    ? (string) $row['quotation_uid']
                    : ('Quotation #' . (int) ($row['id'] ?? 0)),
                'match_destination' => $rowDest,
                'match_nights' => (int) ($row['no_of_nights'] ?? $nights),
                'itinerary' => $days,
                'exact_destination' => crmQuotationNormalizeDestinationKey($destination)
                    === crmQuotationNormalizeDestinationKey($rowDest),
            ];
            if ($payload['exact_destination']) {
                $exactMatch = $payload;
                break;
            }
            if ($fuzzyMatch === null) {
                $fuzzyMatch = $payload;
            }
        }
        $stmt->close();
    }

    if ($exactMatch !== null) {
        return $exactMatch;
    }
    if ($fuzzyMatch !== null) {
        return $fuzzyMatch;
    }

    if (!function_exists('crmPackageTablesExist')) {
        $pkgFile = __DIR__ . '/package_quotation.php';
        if (is_file($pkgFile)) {
            require_once $pkgFile;
        }
    }
    if (!function_exists('crmPackageTablesExist') || !crmPackageTablesExist($conn)) {
        return null;
    }

    $sql = "SELECT p.id, p.title, p.duration_nights,
            (SELECT GROUP_CONCAT(d.name ORDER BY d.name SEPARATOR ', ')
             FROM destinations d
             INNER JOIN package_destination_map pdm ON pdm.destination_id = d.id
             WHERE pdm.package_id = p.id) AS dest_names
        FROM packages p
        WHERE p.status IN ('Published', 'Draft')
          AND p.duration_nights = ?
        ORDER BY p.id DESC
        LIMIT 40";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $nights);
    $stmt->execute();
    $res = $stmt->get_result();
    $packageCandidate = null;
    while ($res && ($row = $res->fetch_assoc())) {
        $rowDest = trim((string) ($row['dest_names'] ?? ''));
        if (!crmQuotationDestinationsMatch($destination, $rowDest)
            && !crmQuotationDestinationsMatch($destination, (string) ($row['title'] ?? ''))
        ) {
            continue;
        }
        $pkgId = (int) ($row['id'] ?? 0);
        if ($pkgId <= 0 || !function_exists('crmGetPackageForQuotation')) {
            continue;
        }
        $pkg = crmGetPackageForQuotation($conn, $pkgId);
        if (!$pkg || empty($pkg['itinerary']) || !is_array($pkg['itinerary'])) {
            continue;
        }
        if (!crmQuotationItineraryHasContent($pkg['itinerary'])) {
            continue;
        }
        $isExact = crmQuotationNormalizeDestinationKey($destination)
            === crmQuotationNormalizeDestinationKey($rowDest);
        $payload = [
            'match_type' => 'package',
            'match_id' => $pkgId,
            'match_label' => (string) ($pkg['title'] ?? $row['title'] ?? ('Package #' . $pkgId)),
            'match_destination' => $rowDest !== '' ? $rowDest : (string) ($pkg['destination'] ?? ''),
            'match_nights' => (int) ($row['duration_nights'] ?? $nights),
            'itinerary' => $pkg['itinerary'],
            'exact_destination' => $isExact,
        ];
        if ($isExact) {
            $packageCandidate = $payload;
            break;
        }
        if ($packageCandidate === null) {
            $packageCandidate = $payload;
        }
    }
    $stmt->close();

    return $packageCandidate;
}
