<?php

function crmEnsureLeadAttachmentsTable(mysqli $conn): void
{
    $conn->query("CREATE TABLE IF NOT EXISTS `crm_lead_attachments` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `lead_id` INT UNSIGNED NOT NULL,
        `original_name` VARCHAR(255) NOT NULL,
        `stored_name` VARCHAR(255) NOT NULL,
        `file_path` VARCHAR(255) NOT NULL,
        `mime_type` VARCHAR(120) DEFAULT NULL,
        `file_size` INT UNSIGNED NOT NULL DEFAULT 0,
        `uploaded_by_id` INT DEFAULT NULL,
        `uploaded_by_name` VARCHAR(120) DEFAULT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_lead_attachments_lead` (`lead_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function crmLeadAttachmentsUploadDir(): string
{
    return __DIR__ . '/../../uploads/leads/';
}

function crmLeadAttachmentsPublicPrefix(): string
{
    return 'uploads/leads/';
}

function crmLeadAttachmentsList(mysqli $conn, int $leadId): array
{
    crmEnsureLeadAttachmentsTable($conn);
    if ($leadId <= 0) {
        return [];
    }

    $rows = [];
    $stmt = $conn->prepare(
        'SELECT `id`, `lead_id`, `original_name`, `file_path`, `mime_type`, `file_size`, `uploaded_by_name`, `created_at`
         FROM `crm_lead_attachments`
         WHERE `lead_id` = ?
         ORDER BY `id` DESC'
    );
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('i', $leadId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($res && ($row = $res->fetch_assoc())) {
        $createdText = '';
        if (!empty($row['created_at'])) {
            $ts = strtotime((string) $row['created_at']);
            if ($ts !== false) {
                $createdText = date('d M Y, h:i A', $ts);
            }
        }
        $rows[] = [
            'id' => (int) ($row['id'] ?? 0),
            'lead_id' => (int) ($row['lead_id'] ?? 0),
            'original_name' => (string) ($row['original_name'] ?? ''),
            'file_url' => (string) ($row['file_path'] ?? ''),
            'mime_type' => (string) ($row['mime_type'] ?? ''),
            'file_size' => (int) ($row['file_size'] ?? 0),
            'uploaded_by_name' => (string) ($row['uploaded_by_name'] ?? ''),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'created_at_text' => $createdText,
        ];
    }
    $stmt->close();

    return $rows;
}

function crmLeadAttachmentFormatSize(int $bytes): string
{
    if ($bytes <= 0) {
        return '0 B';
    }
    if ($bytes < 1024) {
        return $bytes . ' B';
    }
    if ($bytes < 1024 * 1024) {
        return round($bytes / 1024, 1) . ' KB';
    }
    return round($bytes / (1024 * 1024), 1) . ' MB';
}

function crmLeadAttachmentAllowedTypes(): array
{
    return [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.ms-excel' => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'text/plain' => 'txt',
        'application/zip' => 'zip',
    ];
}

function crmLeadAttachmentDetectMime(array $file): string
{
    if (function_exists('finfo_open') && !empty($file['tmp_name'])) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            if (is_string($mime) && $mime !== '') {
                return $mime;
            }
        }
    }

    return (string) ($file['type'] ?? '');
}
