<?php

/**
 * CRM leads — schema helpers and soft delete support.
 */

function crmEnsureLeadDeleteColumns(mysqli $conn): void
{
    $tableCheck = $conn->query("SHOW TABLES LIKE 'crm_leads'");
    if (!$tableCheck || $tableCheck->num_rows === 0) {
        return;
    }

    $cols = [
        'deleted_at' => 'DATETIME DEFAULT NULL AFTER `updated_at`',
        'deleted_by_id' => 'INT DEFAULT NULL AFTER `deleted_at`',
        'deleted_by_name' => 'VARCHAR(120) DEFAULT NULL AFTER `deleted_by_id`',
    ];

    foreach ($cols as $col => $ddl) {
        $chk = $conn->query("SHOW COLUMNS FROM `crm_leads` LIKE '" . $conn->real_escape_string($col) . "'");
        if ($chk && $chk->num_rows === 0) {
            $conn->query("ALTER TABLE `crm_leads` ADD `" . $col . "` " . $ddl);
        }
    }
}

function crmEnsureLeadStageColumn(mysqli $conn): void
{
    $tableCheck = $conn->query("SHOW TABLES LIKE 'crm_leads'");
    if (!$tableCheck || $tableCheck->num_rows === 0) {
        return;
    }

    $chk = $conn->query("SHOW COLUMNS FROM `crm_leads` LIKE 'stage'");
    if ($chk && $chk->num_rows === 0) {
        $conn->query("ALTER TABLE `crm_leads` ADD `stage` VARCHAR(40) NOT NULL DEFAULT 'new_lead' AFTER `assign_to`");
    }

    $conn->query("UPDATE `crm_leads` SET `stage` = 'new_lead' WHERE `stage` IS NULL OR TRIM(`stage`) = ''");
}

/** @return array<string, string> */
function crmLeadStageOptions(): array
{
    return [
        'new_lead' => 'New Lead',
        'quoted' => 'Quoted',
        'confirmed' => 'Confirmed',
        'lost' => 'Lost',
    ];
}

function crmLeadNormalizeStage(?string $stage): string
{
    $stage = strtolower(trim(str_replace('-', '_', (string) $stage)));
    $stage = preg_replace('/\s+/', '_', $stage) ?? $stage;

    $aliases = [
        'new' => 'new_lead',
        'new_lead' => 'new_lead',
        'proposal' => 'quoted',
        'proposal_sent' => 'quoted',
        'quoted' => 'quoted',
        'quote_sent' => 'quoted',
        'contacted' => 'new_lead',
        'negotiation' => 'quoted',
        'won' => 'confirmed',
        'booked' => 'confirmed',
        'closed' => 'confirmed',
        'confirmed' => 'confirmed',
        'cancelled' => 'lost',
        'canceled' => 'lost',
        'lost' => 'lost',
    ];

    if (isset($aliases[$stage])) {
        $stage = $aliases[$stage];
    }

    $options = crmLeadStageOptions();

    return isset($options[$stage]) ? $stage : 'new_lead';
}

/**
 * Derive stage from lead + quotation features.
 *
 * @param array<string, mixed> $lead
 */
function crmLeadComputeStageFromFeatures(array $lead): string
{
    if (!empty($lead['is_tour_confirmed'])) {
        return 'confirmed';
    }
    if (!empty($lead['has_quotation'])) {
        return 'quoted';
    }

    return 'new_lead';
}

/**
 * @param array<string, mixed> $lead
 */
function crmLeadApplyStagePresentation(array &$lead, string $stage, bool $isAuto = true): void
{
    $stage = crmLeadNormalizeStage($stage);
    $lead['stage'] = $stage;
    $lead['stage_label'] = crmLeadStageLabel($stage);
    $lead['stage_class'] = crmLeadStageCssClass($stage);
    $lead['stage_is_auto'] = $isAuto && $stage !== 'lost';
    $lead['stage_locked'] = $lead['stage_is_auto'];
}

/**
 * @param array<int, array<string, mixed>> $leadRows
 */
function crmLeadsSyncFeatureStages(mysqli $conn, array &$leadRows): void
{
    if (empty($leadRows)) {
        return;
    }

    crmEnsureLeadStageColumn($conn);

    foreach ($leadRows as &$lead) {
        $leadId = (int) ($lead['id'] ?? 0);
        if ($leadId <= 0) {
            continue;
        }

        $stored = crmLeadNormalizeStage($lead['stage'] ?? 'new_lead');
        if ($stored === 'lost') {
            crmLeadApplyStagePresentation($lead, 'lost', false);
            continue;
        }

        $computed = crmLeadComputeStageFromFeatures($lead);
        if ($computed !== $stored) {
            crmLeadUpdateStage($conn, $leadId, $computed);
        }
        crmLeadApplyStagePresentation($lead, $computed, true);
    }
    unset($lead);
}

function crmLeadSyncFeatureStageForLead(mysqli $conn, int $leadId): void
{
    if ($leadId <= 0) {
        return;
    }

    require_once __DIR__ . '/quotation_db.php';

    $row = crmLeadFetchById($conn, $leadId, false);
    if (!$row) {
        return;
    }

    $formatted = [
        'id' => $leadId,
        'stage' => crmLeadNormalizeStage($row['stage'] ?? 'new_lead'),
        'customer_phone' => (string) ($row['customer_phone'] ?? ''),
        'customer_email' => (string) ($row['customer_email'] ?? ''),
        'has_quotation' => crmLeadHasLinkedQuotation($conn, $leadId, $row),
        'is_tour_confirmed' => crmLeadHasConfirmedQuotation($conn, $leadId, $row),
    ];

    $batch = [&$formatted];
    crmLeadsSyncFeatureStages($conn, $batch);
}

function crmLeadStageLabel(?string $stage): string
{
    $key = crmLeadNormalizeStage($stage);
    $options = crmLeadStageOptions();

    return $options[$key];
}

function crmLeadStageCssClass(?string $stage): string
{
    return 'stage-' . crmLeadNormalizeStage($stage);
}

function crmLeadUpdateStage(mysqli $conn, int $leadId, string $stage): array
{
    if ($leadId <= 0) {
        return ['success' => false, 'message' => 'Invalid lead.'];
    }

    crmEnsureLeadStageColumn($conn);
    $stageKey = crmLeadNormalizeStage($stage);

    $check = $conn->prepare('SELECT `id`, `stage`, `customer_phone`, `customer_email` FROM `crm_leads` WHERE `id` = ? AND ' . crmLeadActiveWhereSql() . ' LIMIT 1');
    if (!$check) {
        return ['success' => false, 'message' => 'Could not load lead.'];
    }
    $check->bind_param('i', $leadId);
    $check->execute();
    $cRes = $check->get_result();
    $row = $cRes ? $cRes->fetch_assoc() : null;
    $check->close();
    if (!$row) {
        return ['success' => false, 'message' => 'Lead not found.'];
    }

    $stored = crmLeadNormalizeStage($row['stage'] ?? 'new_lead');

    if ($stageKey !== 'lost') {
        require_once __DIR__ . '/quotation_db.php';
        $featureLead = [
            'has_quotation' => crmLeadHasLinkedQuotation($conn, $leadId, $row),
            'is_tour_confirmed' => crmLeadHasConfirmedQuotation($conn, $leadId, $row),
        ];
        $computed = crmLeadComputeStageFromFeatures($featureLead);
        if ($stageKey !== $computed) {
            return [
                'success' => false,
                'message' => 'Stage updates automatically from quotation activity. Current stage: ' . crmLeadStageLabel($computed) . '.',
                'stage' => $computed,
                'stage_label' => crmLeadStageLabel($computed),
            ];
        }
    } elseif ($stored !== 'lost' && $stageKey === 'lost') {
        // Allow manual lost.
    } elseif ($stored === 'lost' && $stageKey !== 'lost') {
        require_once __DIR__ . '/quotation_db.php';
        $featureLead = [
            'has_quotation' => crmLeadHasLinkedQuotation($conn, $leadId, $row),
            'is_tour_confirmed' => crmLeadHasConfirmedQuotation($conn, $leadId, $row),
        ];
        $stageKey = crmLeadComputeStageFromFeatures($featureLead);
    }

    $stmt = $conn->prepare('UPDATE `crm_leads` SET `stage` = ? WHERE `id` = ?');
    if (!$stmt) {
        return ['success' => false, 'message' => 'Could not prepare stage update.'];
    }
    $stmt->bind_param('si', $stageKey, $leadId);
    if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close();
        return ['success' => false, 'message' => 'Could not update stage. ' . $err];
    }
    $stmt->close();

    return [
        'success' => true,
        'message' => 'Stage updated.',
        'stage' => $stageKey,
        'stage_label' => crmLeadStageLabel($stageKey),
        'stage_is_auto' => $stageKey !== 'lost',
    ];
}

function crmLeadActiveWhereSql(string $alias = ''): string
{
    $prefix = $alias !== '' ? rtrim($alias, '.') . '.' : '';
    return '(' . $prefix . '`deleted_at` IS NULL)';
}

function crmLeadDeletedWhereSql(string $alias = ''): string
{
    $prefix = $alias !== '' ? rtrim($alias, '.') . '.' : '';
    return '(' . $prefix . '`deleted_at` IS NOT NULL)';
}

function crmFormatCustomerDisplayName(string $name, string $initial = ''): string
{
    $name = trim($name);
    if ($name === '') {
        return '';
    }

    $initial = trim($initial);
    if ($initial === '') {
        return $name;
    }

    $initialKey = strtolower(rtrim($initial, '.'));
    $nameLower = strtolower($name);
    if (strpos($nameLower, $initialKey . ' ') === 0) {
        return $name;
    }

    return $initial . ' ' . $name;
}

function crmCustomerInitialFromPayload(array $payload): string
{
    return trim((string) ($payload['customer_initial'] ?? ''));
}

function crmLeadFetchDeletedById(mysqli $conn, int $leadId): ?array
{
    if ($leadId <= 0) {
        return null;
    }

    crmEnsureLeadDeleteColumns($conn);

    $sql = 'SELECT * FROM `crm_leads` WHERE `id` = ? AND ' . crmLeadDeletedWhereSql() . ' LIMIT 1';
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $leadId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    return $row ?: null;
}

function crmLeadsListDeleted(mysqli $conn, int $limit = 25, int $offset = 0): array
{
    crmEnsureLeadDeleteColumns($conn);
    $limit = max(1, min(100, $limit));
    $offset = max(0, $offset);
    $rows = [];
    $sql = 'SELECT `id`, `lead_uid`, `customer_name`, `customer_phone`, `customer_email`, `assign_to`, `payload_json`, `deleted_at`, `deleted_by_name`
            FROM `crm_leads`
            WHERE ' . crmLeadDeletedWhereSql() . '
            ORDER BY `deleted_at` DESC, `id` DESC
            LIMIT ' . (int) $offset . ', ' . (int) $limit;
    $res = $conn->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    return $rows;
}

function crmLeadsDeletedCount(mysqli $conn): int
{
    crmEnsureLeadDeleteColumns($conn);
    $res = $conn->query('SELECT COUNT(*) AS c FROM `crm_leads` WHERE ' . crmLeadDeletedWhereSql());
    if (!$res) {
        return 0;
    }
    return (int) ($res->fetch_assoc()['c'] ?? 0);
}

function crmLeadBulkPermanentDelete(mysqli $conn, array $leadIds, bool $requireTrash = true): array
{
    $leadIds = array_values(array_unique(array_filter(array_map('intval', $leadIds), function ($id) {
        return $id > 0;
    })));

    if (empty($leadIds)) {
        return ['success' => false, 'message' => 'No leads selected.'];
    }

    $deleted = 0;
    $errors = [];

    foreach ($leadIds as $leadId) {
        if ($requireTrash && !crmLeadFetchDeletedById($conn, $leadId)) {
            $errors[] = 'Lead #' . $leadId . ' is not in trash.';
            continue;
        }
        $result = crmLeadPermanentDelete($conn, $leadId);
        if (!empty($result['success'])) {
            $deleted++;
        } else {
            $errors[] = (string) ($result['message'] ?? 'Could not delete lead #' . $leadId);
        }
    }

    if ($deleted === 0) {
        return [
            'success' => false,
            'message' => !empty($errors) ? implode(' ', $errors) : 'Could not delete selected leads.',
            'deleted' => 0,
        ];
    }

    $message = $deleted === 1
        ? '1 lead permanently deleted.'
        : $deleted . ' leads permanently deleted.';

    if (!empty($errors)) {
        $message .= ' Some items could not be deleted.';
    }

    return [
        'success' => true,
        'message' => $message,
        'deleted' => $deleted,
        'errors' => $errors,
    ];
}

function crmLeadRestore(mysqli $conn, int $leadId): array
{
    crmEnsureLeadDeleteColumns($conn);

    if (!crmLeadFetchDeletedById($conn, $leadId)) {
        return ['success' => false, 'message' => 'Lead is not in trash.'];
    }

    $stmt = $conn->prepare(
        'UPDATE `crm_leads`
         SET `deleted_at` = NULL, `deleted_by_id` = NULL, `deleted_by_name` = NULL
         WHERE `id` = ? AND `deleted_at` IS NOT NULL'
    );
    if (!$stmt) {
        return ['success' => false, 'message' => 'Could not prepare restore. ' . $conn->error];
    }
    $stmt->bind_param('i', $leadId);
    if (!$stmt->execute()) {
        $err = $conn->error;
        $stmt->close();
        return ['success' => false, 'message' => 'Could not restore lead. ' . $err];
    }
    $stmt->close();

    return [
        'success' => true,
        'message' => 'Lead restored successfully.',
        'lead_id' => $leadId,
    ];
}

function crmLeadBulkRestore(mysqli $conn, array $leadIds): array
{
    $leadIds = array_values(array_unique(array_filter(array_map('intval', $leadIds), function ($id) {
        return $id > 0;
    })));

    if (empty($leadIds)) {
        return ['success' => false, 'message' => 'No leads selected.'];
    }

    $restored = 0;
    $errors = [];

    foreach ($leadIds as $leadId) {
        $result = crmLeadRestore($conn, $leadId);
        if (!empty($result['success'])) {
            $restored++;
        } else {
            $errors[] = (string) ($result['message'] ?? 'Could not restore lead #' . $leadId);
        }
    }

    if ($restored === 0) {
        return [
            'success' => false,
            'message' => !empty($errors) ? implode(' ', $errors) : 'Could not restore selected leads.',
            'restored' => 0,
        ];
    }

    $message = $restored === 1
        ? '1 lead restored successfully.'
        : $restored . ' leads restored successfully.';

    if (!empty($errors)) {
        $message .= ' Some items could not be restored.';
    }

    return [
        'success' => true,
        'message' => $message,
        'restored' => $restored,
        'errors' => $errors,
    ];
}

function crmLeadFetchById(mysqli $conn, int $leadId, bool $includeDeleted = false): ?array
{
    if ($leadId <= 0) {
        return null;
    }

    crmEnsureLeadDeleteColumns($conn);

    $sql = 'SELECT * FROM `crm_leads` WHERE `id` = ?';
    if (!$includeDeleted) {
        $sql .= ' AND ' . crmLeadActiveWhereSql();
    }
    $sql .= ' LIMIT 1';

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $leadId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    return $row ?: null;
}

function crmLeadSoftDelete(mysqli $conn, int $leadId, int $deletedById = 0, string $deletedByName = ''): array
{
    crmEnsureLeadDeleteColumns($conn);

    $row = crmLeadFetchById($conn, $leadId, false);
    if (!$row) {
        return ['success' => false, 'message' => 'Lead not found or already deleted.'];
    }

    $stmt = $conn->prepare(
        'UPDATE `crm_leads`
         SET `deleted_at` = NOW(), `deleted_by_id` = ?, `deleted_by_name` = ?
         WHERE `id` = ? AND `deleted_at` IS NULL'
    );
    if (!$stmt) {
        return ['success' => false, 'message' => 'Could not prepare delete. ' . $conn->error];
    }

    $deletedByIdParam = $deletedById > 0 ? $deletedById : 0;
    $deletedByNameParam = $deletedByName !== '' ? $deletedByName : '';
    $stmt->bind_param('isi', $deletedByIdParam, $deletedByNameParam, $leadId);

    if (!$stmt->execute()) {
        $err = $conn->error;
        $stmt->close();
        return ['success' => false, 'message' => 'Could not delete lead. ' . $err];
    }
    $stmt->close();

    return [
        'success' => true,
        'message' => 'Lead moved to trash.',
        'mode' => 'soft',
    ];
}

function crmLeadPermanentDelete(mysqli $conn, int $leadId): array
{
    crmEnsureLeadDeleteColumns($conn);

    $row = crmLeadFetchById($conn, $leadId, true);
    if (!$row) {
        return ['success' => false, 'message' => 'Lead not found.'];
    }

    $conn->begin_transaction();

    try {
        $qTable = $conn->query("SHOW TABLES LIKE 'crm_quotations'");
        if ($qTable && $qTable->num_rows > 0) {
            $unlink = $conn->prepare('UPDATE `crm_quotations` SET `lead_id` = NULL WHERE `lead_id` = ?');
            if ($unlink) {
                $unlink->bind_param('i', $leadId);
                $unlink->execute();
                $unlink->close();
            }
        }

        $profileTable = $conn->query("SHOW TABLES LIKE 'crm_contact_profiles'");
        if ($profileTable && $profileTable->num_rows > 0) {
            $delProfile = $conn->prepare('DELETE FROM `crm_contact_profiles` WHERE `lead_id` = ?');
            if ($delProfile) {
                $delProfile->bind_param('i', $leadId);
                $delProfile->execute();
                $delProfile->close();
            }
        }

        $familyTable = $conn->query("SHOW TABLES LIKE 'crm_contact_family'");
        if ($familyTable && $familyTable->num_rows > 0) {
            $delFamily = $conn->prepare('DELETE FROM `crm_contact_family` WHERE `lead_id` = ?');
            if ($delFamily) {
                $delFamily->bind_param('i', $leadId);
                $delFamily->execute();
                $delFamily->close();
            }
        }

        $delLead = $conn->prepare('DELETE FROM `crm_leads` WHERE `id` = ?');
        if (!$delLead) {
            throw new RuntimeException('Could not prepare permanent delete.');
        }
        $delLead->bind_param('i', $leadId);
        if (!$delLead->execute()) {
            $err = $conn->error;
            $delLead->close();
            throw new RuntimeException('Could not permanently delete lead. ' . $err);
        }
        $delLead->close();

        $conn->commit();

        return [
            'success' => true,
            'message' => 'Lead permanently deleted.',
            'mode' => 'permanent',
        ];
    } catch (Throwable $e) {
        $conn->rollback();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}
