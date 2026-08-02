<?php

function mailEnsureTables(mysqli $conn): void
{
    $conn->query("CREATE TABLE IF NOT EXISTS `mail_org_settings` (
        `id` TINYINT UNSIGNED NOT NULL DEFAULT 1,
        `smtp_host` VARCHAR(255) DEFAULT NULL,
        `smtp_port` INT UNSIGNED DEFAULT 587,
        `smtp_encryption` VARCHAR(20) DEFAULT 'tls',
        `smtp_username` VARCHAR(255) DEFAULT NULL,
        `smtp_password_enc` TEXT DEFAULT NULL,
        `from_email` VARCHAR(255) DEFAULT NULL,
        `from_name` VARCHAR(255) DEFAULT NULL,
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        `updated_at` DATETIME DEFAULT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS `mail_accounts` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `admin_id` INT UNSIGNED NOT NULL,
        `email_provider` VARCHAR(80) DEFAULT 'custom',
        `email_address` VARCHAR(255) NOT NULL,
        `from_name` VARCHAR(255) DEFAULT NULL,
        `smtp_host` VARCHAR(255) DEFAULT NULL,
        `smtp_port` INT UNSIGNED DEFAULT 587,
        `smtp_encryption` VARCHAR(20) DEFAULT 'tls',
        `smtp_username` VARCHAR(255) DEFAULT NULL,
        `smtp_password_enc` TEXT DEFAULT NULL,
        `smtp_status` VARCHAR(20) DEFAULT 'active',
        `imap_status` VARCHAR(20) DEFAULT 'inactive',
        `imap_host` VARCHAR(255) DEFAULT NULL,
        `imap_port` INT UNSIGNED DEFAULT 993,
        `imap_encryption` VARCHAR(80) DEFAULT 'ssl',
        `imap_username` VARCHAR(255) DEFAULT NULL,
        `imap_password_enc` TEXT DEFAULT NULL,
        `imap_folder` VARCHAR(120) DEFAULT 'INBOX',
        `use_org_smtp` TINYINT(1) NOT NULL DEFAULT 0,
        `last_sync_at` DATETIME DEFAULT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_admin_email` (`admin_id`, `email_address`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS `mail_messages` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `account_id` INT UNSIGNED NOT NULL,
        `folder` VARCHAR(60) NOT NULL DEFAULT 'INBOX',
        `message_uid` VARCHAR(255) NOT NULL,
        `subject` VARCHAR(500) DEFAULT NULL,
        `from_email` VARCHAR(255) DEFAULT NULL,
        `from_name` VARCHAR(255) DEFAULT NULL,
        `to_json` TEXT DEFAULT NULL,
        `cc_json` TEXT DEFAULT NULL,
        `body_text` MEDIUMTEXT DEFAULT NULL,
        `body_html` MEDIUMTEXT DEFAULT NULL,
        `has_attachments` TINYINT(1) NOT NULL DEFAULT 0,
        `is_read` TINYINT(1) NOT NULL DEFAULT 0,
        `is_starred` TINYINT(1) NOT NULL DEFAULT 0,
        `sent_at` DATETIME DEFAULT NULL,
        `synced_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_account_folder_uid` (`account_id`, `folder`, `message_uid`),
        KEY `idx_account_folder_date` (`account_id`, `folder`, `sent_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS `mail_sharing` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `owner_admin_id` INT UNSIGNED NOT NULL,
        `shared_with_admin_id` INT UNSIGNED NOT NULL,
        `can_read` TINYINT(1) NOT NULL DEFAULT 1,
        `can_send` TINYINT(1) NOT NULL DEFAULT 0,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_share` (`owner_admin_id`, `shared_with_admin_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $chk = $conn->query("SELECT id FROM mail_org_settings WHERE id = 1");
    if ($chk && $chk->num_rows === 0) {
        $conn->query("INSERT INTO mail_org_settings (id, is_active) VALUES (1, 1)");
    }

    $conn->query("CREATE TABLE IF NOT EXISTS `mail_smtp_master` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `label` VARCHAR(120) DEFAULT NULL,
        `from_name` VARCHAR(255) DEFAULT NULL,
        `from_email` VARCHAR(255) NOT NULL,
        `smtp_host` VARCHAR(255) NOT NULL,
        `smtp_port` INT UNSIGNED NOT NULL DEFAULT 587,
        `smtp_encryption` VARCHAR(20) NOT NULL DEFAULT 'tls',
        `smtp_username` VARCHAR(255) DEFAULT NULL,
        `smtp_password_enc` TEXT DEFAULT NULL,
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        `display_order` INT NOT NULL DEFAULT 0,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_smtp_master_email` (`from_email`),
        KEY `idx_smtp_master_active` (`is_active`, `display_order`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function mailGetAdminId(): int
{
    return max(0, (int) ($_SESSION['id'] ?? 0));
}

function mailGetAccount(mysqli $conn, int $adminId): ?array
{
    if ($adminId <= 0) {
        return null;
    }
    $stmt = $conn->prepare('SELECT * FROM mail_accounts WHERE admin_id = ? ORDER BY id ASC LIMIT 1');
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $adminId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    return $row ?: null;
}

function mailGetOrgSettings(mysqli $conn): array
{
    $res = $conn->query('SELECT * FROM mail_org_settings WHERE id = 1 LIMIT 1');
    return ($res && $res->num_rows > 0) ? (array) $res->fetch_assoc() : [];
}

function mailListMessages(mysqli $conn, int $accountId, string $folder, string $search = '', int $limit = 50, int $offset = 0, string $readFilter = 'all'): array
{
    $folder = $conn->real_escape_string($folder);
    $accountId = (int) $accountId;
    $limit = max(1, min(200, $limit));
    $offset = max(0, $offset);

    $where = "account_id = $accountId AND folder = '$folder'";
    if ($readFilter === 'unread') {
        $where .= ' AND is_read = 0';
    } elseif ($readFilter === 'read') {
        $where .= ' AND is_read = 1';
    }
    if ($search !== '') {
        $q = '%' . $conn->real_escape_string($search) . '%';
        $where .= " AND (subject LIKE '$q' OR from_email LIKE '$q' OR from_name LIKE '$q' OR body_text LIKE '$q')";
    }

    $rows = [];
    $sql = "SELECT id, subject, from_email, from_name, has_attachments, is_read, is_starred, sent_at
            FROM mail_messages WHERE $where ORDER BY sent_at DESC LIMIT $limit OFFSET $offset";
    $res = $conn->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
    }

    $countRes = $conn->query("SELECT COUNT(*) AS c FROM mail_messages WHERE $where");
    $total = ($countRes && ($cr = $countRes->fetch_assoc())) ? (int) $cr['c'] : 0;

    return ['rows' => $rows, 'total' => $total];
}

function mailFolderCounts(mysqli $conn, int $accountId): array
{
    $counts = [];
    $accountId = (int) $accountId;
    $res = $conn->query(
        "SELECT folder, COUNT(*) AS c FROM mail_messages WHERE account_id = $accountId GROUP BY folder"
    );
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $counts[(string) $row['folder']] = (int) $row['c'];
        }
    }
    return $counts;
}

function mailGetMessage(mysqli $conn, int $messageId, int $accountId): ?array
{
    $stmt = $conn->prepare('SELECT * FROM mail_messages WHERE id = ? AND account_id = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('ii', $messageId, $accountId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    return $row ?: null;
}

function mailListAdmins(mysqli $conn, int $excludeId = 0): array
{
    $rows = [];
    $sql = 'SELECT id, name, email FROM admin';
    if ($excludeId > 0) {
        $sql .= ' WHERE id != ' . (int) $excludeId;
    }
    $sql .= ' ORDER BY name ASC';
    $res = $conn->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    return $rows;
}

function mailListSharing(mysqli $conn, int $ownerId): array
{
    $rows = [];
    $ownerId = (int) $ownerId;
    $res = $conn->query(
        "SELECT s.*, a.name AS shared_name, a.email AS shared_email
         FROM mail_sharing s
         LEFT JOIN admin a ON a.id = s.shared_with_admin_id
         WHERE s.owner_admin_id = $ownerId ORDER BY s.id DESC"
    );
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    return $rows;
}

/** @return array<int, array<string, mixed>> */
function mailListSmtpMaster(mysqli $conn, bool $activeOnly = false): array
{
    $rows = [];
    $sql = 'SELECT id, label, from_name, from_email, smtp_host, smtp_port, smtp_encryption,
                   smtp_username, is_active, display_order, updated_at
            FROM mail_smtp_master';
    if ($activeOnly) {
        $sql .= ' WHERE is_active = 1';
    }
    $sql .= ' ORDER BY display_order ASC, from_email ASC';
    $res = $conn->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    return $rows;
}

function mailSmtpMasterSearchWhere(mysqli $conn, string $search): string
{
    $search = trim($search);
    if ($search === '') {
        return '';
    }
    $like = '%' . $conn->real_escape_string($search) . '%';

    return " AND (label LIKE '$like' OR from_name LIKE '$like' OR from_email LIKE '$like' OR smtp_host LIKE '$like')";
}

function mailCountSmtpMaster(mysqli $conn, string $search = ''): int
{
    $sql = 'SELECT COUNT(*) AS c FROM mail_smtp_master WHERE 1=1' . mailSmtpMasterSearchWhere($conn, $search);
    $res = $conn->query($sql);
    if ($res) {
        return (int) ($res->fetch_assoc()['c'] ?? 0);
    }
    return 0;
}

/** @return array<int, array<string, mixed>> */
function mailListSmtpMasterPaginated(mysqli $conn, int $offset, int $limit, string $search = ''): array
{
    $rows = [];
    $offset = max(0, $offset);
    $limit = max(1, $limit);
    $sql = 'SELECT id, label, from_name, from_email, smtp_host, smtp_port, smtp_encryption,
                   smtp_username, is_active, display_order, updated_at
            FROM mail_smtp_master
            WHERE 1=1' . mailSmtpMasterSearchWhere($conn, $search) . '
            ORDER BY display_order ASC, from_email ASC
            LIMIT ' . $offset . ', ' . $limit;
    $res = $conn->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    return $rows;
}

function mailGetSmtpMasterById(mysqli $conn, int $id, bool $activeOnly = false): ?array
{
    if ($id <= 0) {
        return null;
    }
    $sql = 'SELECT * FROM mail_smtp_master WHERE id = ?';
    if ($activeOnly) {
        $sql .= ' AND is_active = 1';
    }
    $sql .= ' LIMIT 1';
    $stmt = $conn->prepare($sql);
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

function mailNextSmtpMasterDisplayOrder(mysqli $conn): int
{
    $res = $conn->query('SELECT COALESCE(MAX(display_order), 0) + 1 AS next_order FROM mail_smtp_master');
    if ($res) {
        return max(1, (int) ($res->fetch_assoc()['next_order'] ?? 1));
    }
    return 1;
}

/** Seed Email Master from existing mail account / org SMTP when table is empty. */
function mailSeedSmtpMasterFromLegacy(mysqli $conn): void
{
    $chk = $conn->query('SELECT COUNT(*) AS c FROM mail_smtp_master');
    if (!$chk || (int) ($chk->fetch_assoc()['c'] ?? 0) > 0) {
        return;
    }

    $org = mailGetOrgSettings($conn);
    if (!empty($org['smtp_host']) && !empty($org['from_email'])) {
        $stmt = $conn->prepare(
            'INSERT INTO mail_smtp_master
            (label, from_name, from_email, smtp_host, smtp_port, smtp_encryption, smtp_username, smtp_password_enc, is_active, display_order)
            VALUES (?,?,?,?,?,?,?,?,?,1)'
        );
        if ($stmt) {
            $label = 'Organization SMTP';
            $fromName = (string) ($org['from_name'] ?? 'CRM');
            $fromEmail = (string) $org['from_email'];
            $host = (string) $org['smtp_host'];
            $port = (int) ($org['smtp_port'] ?? 587);
            $enc = (string) ($org['smtp_encryption'] ?? 'tls');
            $user = (string) ($org['smtp_username'] ?? '');
            $pass = (string) ($org['smtp_password_enc'] ?? '');
            $active = !empty($org['is_active']) ? 1 : 0;
            $stmt->bind_param('ssssisssi', $label, $fromName, $fromEmail, $host, $port, $enc, $user, $pass, $active);
            $stmt->execute();
            $stmt->close();
        }
    }

    $res = $conn->query(
        "SELECT * FROM mail_accounts WHERE smtp_status = 'active' AND smtp_host <> '' AND email_address <> '' ORDER BY id ASC"
    );
    if ($res) {
        $order = 2;
        while ($row = $res->fetch_assoc()) {
            $exists = $conn->prepare('SELECT id FROM mail_smtp_master WHERE from_email = ? LIMIT 1');
            if (!$exists) {
                continue;
            }
            $email = (string) $row['email_address'];
            $exists->bind_param('s', $email);
            $exists->execute();
            $er = $exists->get_result();
            $dup = $er && $er->num_rows > 0;
            $exists->close();
            if ($dup) {
                continue;
            }

            $stmt = $conn->prepare(
                'INSERT INTO mail_smtp_master
                (label, from_name, from_email, smtp_host, smtp_port, smtp_encryption, smtp_username, smtp_password_enc, is_active, display_order)
                VALUES (?,?,?,?,?,?,?,?,?,?)'
            );
            if ($stmt) {
                $label = (string) ($row['from_name'] ?? $row['email_address']);
                $fromName = (string) ($row['from_name'] ?? 'CRM');
                $fromEmail = (string) $row['email_address'];
                $host = (string) $row['smtp_host'];
                $port = (int) ($row['smtp_port'] ?? 587);
                $enc = (string) ($row['smtp_encryption'] ?? 'tls');
                $user = (string) ($row['smtp_username'] ?? $row['email_address']);
                $pass = (string) ($row['smtp_password_enc'] ?? '');
                $active = (($row['smtp_status'] ?? '') === 'active') ? 1 : 0;
                $stmt->bind_param('ssssisssii', $label, $fromName, $fromEmail, $host, $port, $enc, $user, $pass, $active, $order);
                $stmt->execute();
                $stmt->close();
                $order++;
            }
        }
    }
}

/** Default Zoho Mail SMTP/IMAP hosts (custom domain). */
function mailZohoMailPresets(): array
{
    return [
        'smtp_host' => 'smtp.zoho.com',
        'smtp_port' => 587,
        'smtp_encryption' => 'tls',
        'imap_host' => 'imap.zoho.com',
        'imap_port' => 993,
        'imap_encryption' => 'ssl',
    ];
}

/** Default Gmail SMTP presets. */
function mailGmailPresets(): array
{
    return [
        'smtp_host' => 'smtp.gmail.com',
        'smtp_port' => 587,
        'smtp_encryption' => 'tls',
        'imap_host' => 'imap.gmail.com',
        'imap_port' => 993,
        'imap_encryption' => 'ssl',
    ];
}
