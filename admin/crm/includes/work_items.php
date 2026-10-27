<?php
/**
 * Tasks & Follow-ups stored for the CRM workspace.
 */

function crmEnsureWorkItemTables(mysqli $conn): void
{
    $conn->query("CREATE TABLE IF NOT EXISTS `crm_work_items` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `kind` VARCHAR(20) NOT NULL DEFAULT 'followup',
        `lead_id` INT UNSIGNED DEFAULT NULL,
        `client_name` VARCHAR(150) NOT NULL DEFAULT '',
        `client_phone` VARCHAR(40) NOT NULL DEFAULT '',
        `client_email` VARCHAR(190) NOT NULL DEFAULT '',
        `item_type` VARCHAR(30) NOT NULL DEFAULT 'call',
        `title` VARCHAR(200) NOT NULL DEFAULT '',
        `purpose` VARCHAR(500) NOT NULL DEFAULT '',
        `notes` VARCHAR(500) NOT NULL DEFAULT '',
        `scheduled_at` DATETIME DEFAULT NULL,
        `start_at` DATETIME DEFAULT NULL,
        `priority` VARCHAR(20) NOT NULL DEFAULT 'medium',
        `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
        `assigned_name` VARCHAR(150) NOT NULL DEFAULT '',
        `reminder` VARCHAR(30) NOT NULL DEFAULT '30m',
        `related_kind` VARCHAR(20) NOT NULL DEFAULT '',
        `related_id` INT UNSIGNED NOT NULL DEFAULT 0,
        `related_label` VARCHAR(200) NOT NULL DEFAULT '',
        `recurring` VARCHAR(20) NOT NULL DEFAULT '',
        `attachments` TEXT NULL,
        `completed_at` DATETIME DEFAULT NULL,
        `created_by_name` VARCHAR(150) NOT NULL DEFAULT '',
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_work_kind_when` (`kind`, `scheduled_at`),
        KEY `idx_work_status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $extra = [
        'title' => "VARCHAR(200) NOT NULL DEFAULT '' AFTER `item_type`",
        'notes' => "VARCHAR(500) NOT NULL DEFAULT '' AFTER `purpose`",
        'start_at' => 'DATETIME DEFAULT NULL AFTER `scheduled_at`',
        'reminder' => "VARCHAR(30) NOT NULL DEFAULT '30m' AFTER `assigned_name`",
        'related_kind' => "VARCHAR(20) NOT NULL DEFAULT '' AFTER `reminder`",
        'related_id' => 'INT UNSIGNED NOT NULL DEFAULT 0 AFTER `related_kind`',
        'related_label' => "VARCHAR(200) NOT NULL DEFAULT '' AFTER `related_id`",
        'recurring' => "VARCHAR(20) NOT NULL DEFAULT '' AFTER `related_label`",
        'attachments' => 'TEXT NULL AFTER `recurring`',
    ];
    foreach ($extra as $col => $ddl) {
        $chk = $conn->query("SHOW COLUMNS FROM `crm_work_items` LIKE '" . $conn->real_escape_string($col) . "'");
        if ($chk && $chk->num_rows === 0) {
            $conn->query('ALTER TABLE `crm_work_items` ADD `' . $col . '` ' . $ddl);
        }
    }
}

/** @return array<string, string> */
function crmWorkItemTypes(): array
{
    return [
        'call' => 'Call',
        'whatsapp' => 'WhatsApp',
        'email' => 'Email',
        'meeting' => 'Meeting',
        'other' => 'Other',
    ];
}

/** @return array<string, string> */
function crmWorkItemTaskTypes(): array
{
    return [
        'general' => 'General',
        'prepare_quotation' => 'Prepare Quotation',
        'check_payment' => 'Check Payment Status',
        'send_itinerary' => 'Send Itinerary',
        'collect_documents' => 'Collect Documents',
        'call_supplier' => 'Call Supplier',
        'other' => 'Other',
    ];
}

/** @return list<string> */
function crmWorkItemPriorities(): array
{
    return ['low', 'medium', 'high'];
}

function crmWorkItemClean(string $value, int $max): string
{
    $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $max);
    }
    return substr($value, 0, $max);
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function crmWorkItemWhen(string $raw): string
{
    $raw = trim(str_replace('T', ' ', $raw));
    if ($raw === '') {
        return '';
    }
    $ts = strtotime($raw);
    return $ts === false ? '' : date('Y-m-d H:i:s', $ts);
}

/** @return list<array{name: string, file: string}> */
function crmWorkItemAttachments(string $json): array
{
    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        return [];
    }
    $out = [];
    foreach ($decoded as $row) {
        if (!is_array($row)) {
            continue;
        }
        $file = basename((string) ($row['file'] ?? ''));
        $name = trim((string) ($row['name'] ?? ''));
        if ($file === '' || !preg_match('/^[A-Za-z0-9._-]+$/', $file)) {
            continue;
        }
        $out[] = ['name' => $name !== '' ? $name : $file, 'file' => $file];
    }
    return $out;
}

function crmWorkItemUploadDir(): string
{
    return dirname(__DIR__) . '/uploads/work_items';
}

/**
 * @param list<array{name: string, file: string}> $existing
 * @return list<array{name: string, file: string}>
 */
function crmWorkItemStoreUploads(int $id, array $existing): array
{
    if ($id <= 0 || empty($_FILES['attachments']['name']) || !is_array($_FILES['attachments']['name'])) {
        return $existing;
    }
    $dir = crmWorkItemUploadDir();
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        return $existing;
    }
    $allow = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png'];
    $names = $_FILES['attachments']['name'];
    $tmp = $_FILES['attachments']['tmp_name'];
    $errs = $_FILES['attachments']['error'];
    $sizes = $_FILES['attachments']['size'];
    $count = count($names);
    for ($i = 0; $i < $count && count($existing) < 8; $i++) {
        if ((int) ($errs[$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            continue;
        }
        if ((int) ($sizes[$i] ?? 0) <= 0 || (int) $sizes[$i] > 5 * 1024 * 1024) {
            continue;
        }
        $orig = basename((string) $names[$i]);
        $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
        if (!in_array($ext, $allow, true)) {
            continue;
        }
        $stored = $id . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        if (!is_uploaded_file((string) ($tmp[$i] ?? '')) || !move_uploaded_file((string) $tmp[$i], $dir . DIRECTORY_SEPARATOR . $stored)) {
            continue;
        }
        $existing[] = ['name' => crmWorkItemClean($orig, 180), 'file' => $stored];
    }
    return $existing;
}

function crmWorkItemNormalize(array $input): array
{
    $kind = strtolower(trim((string) ($input['kind'] ?? 'followup')));
    if (!in_array($kind, ['followup', 'task'], true)) {
        $kind = 'followup';
    }
    $typeMap = $kind === 'task' ? crmWorkItemTaskTypes() : crmWorkItemTypes();
    $type = strtolower(trim((string) ($input['item_type'] ?? '')));
    if (!isset($typeMap[$type])) {
        $type = $kind === 'task' ? 'general' : 'call';
    }
    $priority = strtolower(trim((string) ($input['priority'] ?? 'medium')));
    if (!in_array($priority, crmWorkItemPriorities(), true)) {
        $priority = 'medium';
    }
    $status = strtolower(trim((string) ($input['status'] ?? 'pending')));
    if (!in_array($status, ['pending', 'completed'], true)) {
        $status = 'pending';
    }
    $reminder = strtolower(trim((string) ($input['reminder'] ?? '30m')));
    if (!in_array($reminder, ['none', '15m', '30m', '1h', '1d'], true)) {
        $reminder = '30m';
    }
    $recurring = strtolower(trim((string) ($input['recurring'] ?? '')));
    if (!in_array($recurring, ['', 'daily', 'weekly', 'monthly'], true)) {
        $recurring = '';
    }
    $relatedKind = strtolower(trim((string) ($input['related_kind'] ?? '')));
    if (!in_array($relatedKind, ['', 'quotation', 'tour'], true)) {
        $relatedKind = '';
    }
    $title = crmWorkItemClean((string) ($input['title'] ?? ''), 200);
    $notes = crmWorkItemClean((string) ($input['notes'] ?? ''), 500);
    $leadId = (int) ($input['lead_id'] ?? 0);

    return [
        'kind' => $kind,
        'lead_id' => $leadId > 0 ? $leadId : 0,
        'client_name' => crmWorkItemClean((string) ($input['client_name'] ?? ''), 150),
        'client_phone' => crmWorkItemClean((string) ($input['client_phone'] ?? ''), 40),
        'client_email' => crmWorkItemClean((string) ($input['client_email'] ?? ''), 190),
        'item_type' => $type,
        'title' => $title,
        'purpose' => $title !== '' ? $title : $notes,
        'notes' => $notes,
        'scheduled_at' => crmWorkItemWhen((string) ($input['scheduled_at'] ?? '')),
        'start_at' => $kind === 'task' ? crmWorkItemWhen((string) ($input['start_at'] ?? '')) : '',
        'priority' => $priority,
        'status' => $status,
        'assigned_name' => crmWorkItemClean((string) ($input['assigned_name'] ?? ''), 150),
        'reminder' => $reminder,
        'related_kind' => $relatedKind,
        'related_id' => max(0, (int) ($input['related_id'] ?? 0)),
        'related_label' => crmWorkItemClean((string) ($input['related_label'] ?? ''), 200),
        'recurring' => $kind === 'task' ? $recurring : '',
    ];
}

/** @return array<string, mixed> */
function crmWorkItemPublic(array $row): array
{
    return [
        'id' => (int) ($row['id'] ?? 0),
        'kind' => (string) ($row['kind'] ?? 'followup'),
        'lead_id' => (int) ($row['lead_id'] ?? 0),
        'client_name' => (string) ($row['client_name'] ?? ''),
        'client_phone' => (string) ($row['client_phone'] ?? ''),
        'client_email' => (string) ($row['client_email'] ?? ''),
        'item_type' => (string) ($row['item_type'] ?? 'call'),
        'title' => (string) ($row['title'] ?? ''),
        'purpose' => (string) ($row['purpose'] ?? ''),
        'notes' => (string) ($row['notes'] ?? ''),
        'scheduled_at' => (string) ($row['scheduled_at'] ?? ''),
        'start_at' => (string) ($row['start_at'] ?? ''),
        'priority' => (string) ($row['priority'] ?? 'medium'),
        'status' => (string) ($row['status'] ?? 'pending'),
        'assigned_name' => (string) ($row['assigned_name'] ?? ''),
        'reminder' => (string) ($row['reminder'] ?? '30m'),
        'related_kind' => (string) ($row['related_kind'] ?? ''),
        'related_id' => (int) ($row['related_id'] ?? 0),
        'related_label' => (string) ($row['related_label'] ?? ''),
        'recurring' => (string) ($row['recurring'] ?? ''),
        'attachments' => crmWorkItemAttachments((string) ($row['attachments'] ?? '')),
        'completed_at' => (string) ($row['completed_at'] ?? ''),
    ];
}

/** @return list<array<string, mixed>> */
function crmWorkItemList(mysqli $conn): array
{
    $rows = [];
    $res = $conn->query('SELECT * FROM `crm_work_items` ORDER BY `scheduled_at` IS NULL, `scheduled_at` ASC, `id` DESC');
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $rows[] = crmWorkItemPublic($row);
        }
    }
    return $rows;
}

/** @return array{ok: bool, message: string, item?: array<string, mixed>} */
function crmWorkItemSave(mysqli $conn, array $input): array
{
    $data = crmWorkItemNormalize($input);
    if ($data['title'] === '') {
        return ['ok' => false, 'message' => $data['kind'] === 'task' ? 'Task title is required.' : 'Follow-up title is required.'];
    }
    if ($data['kind'] === 'followup' && $data['client_name'] === '') {
        return ['ok' => false, 'message' => 'Client or lead is required.'];
    }
    if ($data['scheduled_at'] === '') {
        return ['ok' => false, 'message' => $data['kind'] === 'task' ? 'Due date is required.' : 'Follow-up date and time are required.'];
    }
    if ($data['kind'] === 'task' && $data['start_at'] === '') {
        return ['ok' => false, 'message' => 'Start date is required.'];
    }
    $id = (int) ($input['id'] ?? 0);
    $completedAt = $data['status'] === 'completed' ? date('Y-m-d H:i:s') : null;
    $kept = crmWorkItemAttachments((string) ($input['keep_attachments'] ?? '[]'));
    $previousFiles = [];
    if ($id > 0) {
        $existing = $conn->prepare('SELECT `completed_at`, `status`, `attachments` FROM `crm_work_items` WHERE `id` = ? LIMIT 1');
        if ($existing) {
            $existing->bind_param('i', $id);
            $existing->execute();
            $found = $existing->get_result()->fetch_assoc();
            $existing->close();
            if (!$found) {
                return ['ok' => false, 'message' => 'That item was not found.'];
            }
            if ($data['status'] === 'completed' && (string) ($found['status'] ?? '') === 'completed' && !empty($found['completed_at'])) {
                $completedAt = (string) $found['completed_at'];
            }
            $previousFiles = crmWorkItemAttachments((string) ($found['attachments'] ?? ''));
        }
    }
    $keepNames = [];
    foreach ($kept as $file) {
        $keepNames[$file['file']] = true;
    }
    $attachments = [];
    foreach ($previousFiles as $file) {
        if (isset($keepNames[$file['file']])) {
            $attachments[] = $file;
            continue;
        }
        $path = crmWorkItemUploadDir() . DIRECTORY_SEPARATOR . $file['file'];
        if (is_file($path)) {
            unlink($path);
        }
    }
    $startAt = $data['start_at'] !== '' ? $data['start_at'] : null;
    $attachJson = json_encode($attachments, JSON_UNESCAPED_UNICODE) ?: '[]';
    if ($id > 0) {
        $stmt = $conn->prepare(
            'UPDATE `crm_work_items`
             SET `kind` = ?, `lead_id` = ?, `client_name` = ?, `client_phone` = ?, `client_email` = ?,
                 `item_type` = ?, `title` = ?, `purpose` = ?, `notes` = ?, `scheduled_at` = ?, `start_at` = ?,
                 `priority` = ?, `status` = ?, `assigned_name` = ?, `reminder` = ?, `related_kind` = ?,
                 `related_id` = ?, `related_label` = ?, `recurring` = ?, `attachments` = ?, `completed_at` = ?
             WHERE `id` = ? LIMIT 1'
        );
        if (!$stmt) {
            return ['ok' => false, 'message' => 'Could not save this item.'];
        }
        $leadId = $data['lead_id'];
        $relatedId = $data['related_id'];
        $stmt->bind_param(
            'sissssssssssssssissssi',
            $data['kind'],
            $leadId,
            $data['client_name'],
            $data['client_phone'],
            $data['client_email'],
            $data['item_type'],
            $data['title'],
            $data['purpose'],
            $data['notes'],
            $data['scheduled_at'],
            $startAt,
            $data['priority'],
            $data['status'],
            $data['assigned_name'],
            $data['reminder'],
            $data['related_kind'],
            $relatedId,
            $data['related_label'],
            $data['recurring'],
            $attachJson,
            $completedAt,
            $id
        );
        $ok = $stmt->execute();
        $stmt->close();
        if (!$ok) {
            return ['ok' => false, 'message' => 'Could not save this item.'];
        }
    } else {
        $createdBy = crmWorkItemClean((string) ($_SESSION['name'] ?? ''), 150);
        $stmt = $conn->prepare(
            'INSERT INTO `crm_work_items`
             (`kind`, `lead_id`, `client_name`, `client_phone`, `client_email`, `item_type`, `title`, `purpose`, `notes`,
              `scheduled_at`, `start_at`, `priority`, `status`, `assigned_name`, `reminder`, `related_kind`, `related_id`,
              `related_label`, `recurring`, `attachments`, `completed_at`, `created_by_name`)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        if (!$stmt) {
            return ['ok' => false, 'message' => 'Could not save this item.'];
        }
        $leadId = $data['lead_id'];
        $relatedId = $data['related_id'];
        $stmt->bind_param(
            'sissssssssssssssisssss',
            $data['kind'],
            $leadId,
            $data['client_name'],
            $data['client_phone'],
            $data['client_email'],
            $data['item_type'],
            $data['title'],
            $data['purpose'],
            $data['notes'],
            $data['scheduled_at'],
            $startAt,
            $data['priority'],
            $data['status'],
            $data['assigned_name'],
            $data['reminder'],
            $data['related_kind'],
            $relatedId,
            $data['related_label'],
            $data['recurring'],
            $attachJson,
            $completedAt,
            $createdBy
        );
        $ok = $stmt->execute();
        $id = (int) $stmt->insert_id;
        $stmt->close();
        if (!$ok || $id <= 0) {
            return ['ok' => false, 'message' => 'Could not save this item.'];
        }
    }
    $stored = crmWorkItemStoreUploads($id, $attachments);
    if (count($stored) !== count($attachments)) {
        $attachJson = json_encode($stored, JSON_UNESCAPED_UNICODE) ?: '[]';
        $upd = $conn->prepare('UPDATE `crm_work_items` SET `attachments` = ? WHERE `id` = ? LIMIT 1');
        if ($upd) {
            $upd->bind_param('si', $attachJson, $id);
            $upd->execute();
            $upd->close();
        }
    }
    $load = $conn->prepare('SELECT * FROM `crm_work_items` WHERE `id` = ? LIMIT 1');
    $load->bind_param('i', $id);
    $load->execute();
    $row = $load->get_result()->fetch_assoc();
    $load->close();
    return ['ok' => true, 'message' => 'Saved.', 'item' => crmWorkItemPublic($row ?: [])];
}

function crmWorkItemSetStatus(mysqli $conn, int $id, string $status): bool
{
    if ($id <= 0) {
        return false;
    }
    $status = $status === 'completed' ? 'completed' : 'pending';
    $completedAt = $status === 'completed' ? date('Y-m-d H:i:s') : null;
    $stmt = $conn->prepare('UPDATE `crm_work_items` SET `status` = ?, `completed_at` = ? WHERE `id` = ? LIMIT 1');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('ssi', $status, $completedAt, $id);
    $ok = $stmt->execute() && $stmt->affected_rows >= 0;
    $stmt->close();
    return $ok;
}

/** @param list<int> $ids */
function crmWorkItemDelete(mysqli $conn, array $ids): int
{
    $ids = array_values(array_filter(array_map('intval', $ids), static function ($id) {
        return $id > 0;
    }));
    if (!$ids) {
        return 0;
    }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $conn->prepare('DELETE FROM `crm_work_items` WHERE `id` IN (' . $placeholders . ')');
    if (!$stmt) {
        return 0;
    }
    $types = str_repeat('i', count($ids));
    $stmt->bind_param($types, ...$ids);
    $stmt->execute();
    $n = $stmt->affected_rows;
    $stmt->close();
    return max(0, (int) $n);
}
