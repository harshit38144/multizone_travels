<?php

require_once dirname(__DIR__, 3) . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Folder;

function mailEncryptionKey(): string
{
    return getenv('MULTIZONE_MAIL_ENC_KEY') ?: 'multizone_travels_mail_secret_key';
}

function mailEncrypt(string $plain): string
{
    if ($plain === '') {
        return '';
    }
    $key = hash('sha256', mailEncryptionKey(), true);
    $iv = openssl_random_pseudo_bytes(16);
    $enc = openssl_encrypt($plain, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    return base64_encode($iv . $enc);
}

function mailDecrypt(string $enc): string
{
    if ($enc === '') {
        return '';
    }
    $raw = base64_decode($enc, true);
    if ($raw === false || strlen($raw) < 17) {
        return '';
    }
    $iv = substr($raw, 0, 16);
    $data = substr($raw, 16);
    $key = hash('sha256', mailEncryptionKey(), true);
    $plain = openssl_decrypt($data, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    return $plain === false ? '' : $plain;
}

function mailImapClientConfig(array $account): array
{
    $enc = (string) ($account['imap_encryption'] ?? 'ssl');
    $validateCert = true;
    if (strpos($enc, 'novalidate') !== false) {
        $validateCert = false;
        $enc = 'ssl';
    }
    if ($enc === 'imap/ssl/novalidate-cert') {
        $enc = 'ssl';
        $validateCert = false;
    }

    return [
        'host' => (string) ($account['imap_host'] ?? ''),
        'port' => (int) ($account['imap_port'] ?? 993),
        'encryption' => $enc === 'none' ? false : $enc,
        'validate_cert' => $validateCert,
        'username' => (string) ($account['imap_username'] ?? $account['email_address'] ?? ''),
        'password' => mailDecrypt((string) ($account['imap_password_enc'] ?? '')),
        'protocol' => 'imap',
        'timeout' => 60,
    ];
}

function mailSmtpConfigFromAccount(array $account, ?array $org = null): array
{
    if (!empty($account['use_org_smtp']) && $org) {
        return [
            'host' => (string) ($org['smtp_host'] ?? ''),
            'port' => (int) ($org['smtp_port'] ?? 587),
            'encryption' => (string) ($org['smtp_encryption'] ?? 'tls'),
            'username' => (string) ($org['smtp_username'] ?? ''),
            'password' => mailDecrypt((string) ($org['smtp_password_enc'] ?? '')),
            'from_email' => (string) ($org['from_email'] ?? $account['email_address'] ?? ''),
            'from_name' => (string) ($org['from_name'] ?? $account['from_name'] ?? 'CRM'),
        ];
    }

    return [
        'host' => (string) ($account['smtp_host'] ?? ''),
        'port' => (int) ($account['smtp_port'] ?? 587),
        'encryption' => (string) ($account['smtp_encryption'] ?? 'tls'),
        'username' => (string) ($account['smtp_username'] ?? $account['email_address'] ?? ''),
        'password' => mailDecrypt((string) ($account['smtp_password_enc'] ?? '')),
        'from_email' => (string) ($account['email_address'] ?? ''),
        'from_name' => (string) ($account['from_name'] ?? 'CRM'),
    ];
}

function mailSmtpConfigFromOrg(array $org): array
{
    return [
        'host' => (string) ($org['smtp_host'] ?? ''),
        'port' => (int) ($org['smtp_port'] ?? 587),
        'encryption' => (string) ($org['smtp_encryption'] ?? 'tls'),
        'username' => (string) ($org['smtp_username'] ?? ''),
        'password' => mailDecrypt((string) ($org['smtp_password_enc'] ?? '')),
        'from_email' => (string) ($org['from_email'] ?? ''),
        'from_name' => (string) ($org['from_name'] ?? 'CRM'),
    ];
}

function mailSmtpConfigFromMaster(array $master): array
{
    return [
        'host' => (string) ($master['smtp_host'] ?? ''),
        'port' => (int) ($master['smtp_port'] ?? 587),
        'encryption' => (string) ($master['smtp_encryption'] ?? 'tls'),
        'username' => (string) ($master['smtp_username'] ?? $master['from_email'] ?? ''),
        'password' => mailDecrypt((string) ($master['smtp_password_enc'] ?? '')),
        'from_email' => (string) ($master['from_email'] ?? ''),
        'from_name' => (string) ($master['from_name'] ?? 'CRM'),
        'master_id' => (int) ($master['id'] ?? 0),
    ];
}

/**
 * Resolve SMTP config for sending. Prefers Email Master sender_id, then legacy account/org.
 * @return array{config: array<string, mixed>, source: string}|null
 */
function mailResolveSendSmtp(mysqli $conn, int $senderId = 0, ?int $adminId = null): ?array
{
    if ($senderId > 0) {
        $master = mailGetSmtpMasterById($conn, $senderId, true);
        if ($master && ($master['smtp_host'] ?? '') !== '') {
            return ['config' => mailSmtpConfigFromMaster($master), 'source' => 'master'];
        }
        return null;
    }

    $adminId = $adminId ?? mailGetAdminId();
    $account = mailGetAccount($conn, $adminId);
    $org = mailGetOrgSettings($conn);

    $activeMasters = mailListSmtpMaster($conn, true);
    if (count($activeMasters) === 1) {
        $full = mailGetSmtpMasterById($conn, (int) $activeMasters[0]['id'], true);
        if ($full && ($full['smtp_host'] ?? '') !== '') {
            return ['config' => mailSmtpConfigFromMaster($full), 'source' => 'master_default'];
        }
    }

    if ($account && ($account['smtp_status'] ?? '') === 'active') {
        $cfg = mailSmtpConfigFromAccount($account, $org);
        if ($cfg['host'] !== '') {
            return ['config' => $cfg, 'source' => 'account'];
        }
    }
    if (!empty($org['is_active']) && !empty($org['smtp_host'])) {
        return ['config' => mailSmtpConfigFromOrg($org), 'source' => 'org'];
    }

    return null;
}

function mailApplySmtp(PHPMailer $mail, array $cfg): void
{
    $mail->isSMTP();
    $mail->Host = $cfg['host'];
    $mail->SMTPAuth = true;
    $mail->Username = $cfg['username'];
    $mail->Password = $cfg['password'];
    $enc = strtolower((string) ($cfg['encryption'] ?? 'tls'));
    if ($enc === 'ssl' || $enc === 'smtps') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    } elseif ($enc === 'tls' || $enc === 'starttls') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    } else {
        $mail->SMTPSecure = false;
        $mail->SMTPAutoTLS = false;
    }
    $mail->Port = (int) ($cfg['port'] ?? 587);
    $mail->setFrom($cfg['from_email'], $cfg['from_name']);
}

function mailSend(array $smtpCfg, string $to, string $subject, string $htmlBody, array $opts = []): array
{
    $mail = new PHPMailer(true);
    try {
        mailApplySmtp($mail, $smtpCfg);
        $mail->addAddress($to);
        if (!empty($opts['cc'])) {
            foreach ((array) $opts['cc'] as $cc) {
                if (trim((string) $cc) !== '') {
                    $mail->addCC(trim((string) $cc));
                }
            }
        }
        if (!empty($opts['bcc'])) {
            foreach ((array) $opts['bcc'] as $bcc) {
                if (trim((string) $bcc) !== '') {
                    $mail->addBCC(trim((string) $bcc));
                }
            }
        }
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;
        $mail->AltBody = strip_tags($htmlBody);
        $mail->send();
        return ['ok' => true, 'message' => 'Email sent successfully.'];
    } catch (MailException $e) {
        return ['ok' => false, 'message' => $mail->ErrorInfo ?: $e->getMessage()];
    }
}

function mailTestSmtp(array $smtpCfg, string $to): array
{
    $subject = 'CRM Test Email - ' . date('Y-m-d H:i:s');
    $body = '<p>This is a test email from your CRM mail configuration.</p>';
    return mailSend($smtpCfg, $to, $subject, $body);
}

function mailTestImap(array $account): array
{
    if (($account['imap_status'] ?? '') !== 'active') {
        return ['ok' => false, 'message' => 'IMAP is not active.'];
    }
    $cfg = mailImapClientConfig($account);
    if ($cfg['host'] === '' || $cfg['username'] === '' || $cfg['password'] === '') {
        return ['ok' => false, 'message' => 'IMAP host, username and password are required.'];
    }
    try {
        $cm = new ClientManager();
        $client = $cm->make($cfg);
        $client->connect();
        $folder = $client->getFolder($account['imap_folder'] ?? 'INBOX');
        $count = $folder ? $folder->messages()->all()->count() : 0;
        $client->disconnect();
        return ['ok' => true, 'message' => "IMAP connection successful. Messages in folder: $count"];
    } catch (Throwable $e) {
        return ['ok' => false, 'message' => $e->getMessage()];
    }
}

function mailLogicalFolderAliases(): array
{
    return [
        'INBOX' => ['INBOX', 'Inbox'],
        'Spam' => ['[Gmail]/Spam', 'Spam', 'Junk', 'INBOX/Spam', 'INBOX/Junk'],
        'Archive' => ['[Gmail]/All Mail', 'All Mail', 'Archive'],
        'Sent' => ['[Gmail]/Sent Mail', 'Sent Mail', 'Sent', 'Sent Items', 'INBOX/Sent'],
        'Trash' => ['[Gmail]/Trash', '[Gmail]/Bin', 'Trash', 'Deleted', 'Deleted Items', 'INBOX/Trash', 'Bin'],
        'Drafts' => ['[Gmail]/Drafts', 'Drafts', 'INBOX/Drafts'],
    ];
}

function mailStrContains(string $haystack, string $needle): bool
{
    return $needle === '' || strpos($haystack, $needle) !== false;
}

function mailMatchFolderPath(string $logicalKey, string $name, string $path): bool
{
    $nameL = strtolower($name);
    $pathL = strtolower($path);
    $full = $pathL . ' ' . $nameL;

    foreach (mailLogicalFolderAliases()[$logicalKey] ?? [] as $alias) {
        $aliasL = strtolower($alias);
        if ($nameL === $aliasL || $pathL === $aliasL || mailStrContains($pathL, $aliasL)) {
            return true;
        }
    }

    switch ($logicalKey) {
        case 'INBOX':
            return $nameL === 'inbox' || $pathL === 'inbox';
        case 'Sent':
            return (mailStrContains($full, 'sent') && !mailStrContains($full, 'resent'))
                || mailStrContains($pathL, 'sent mail');
        case 'Trash':
            return mailStrContains($full, 'trash') || mailStrContains($full, 'deleted') || mailStrContains($full, 'bin');
        case 'Spam':
            return mailStrContains($full, 'spam') || mailStrContains($full, 'junk');
        case 'Drafts':
            return mailStrContains($full, 'draft');
        case 'Archive':
            return mailStrContains($full, 'all mail') || mailStrContains($full, 'archive');
    }

    return false;
}

/** @return array<string, Folder> logical folder key => IMAP folder */
function mailDiscoverFolderMap(Client $client): array
{
    $map = [];
    $all = $client->getFolders(false, null, true);

    foreach (array_keys(mailFolderMap()) as $logicalKey) {
        foreach ($all as $folder) {
            if (!$folder instanceof Folder) {
                continue;
            }
            $name = (string) ($folder->name ?? '');
            $path = (string) ($folder->full_name ?? $folder->path ?? $name);
            if (mailMatchFolderPath($logicalKey, $name, $path)) {
                $map[$logicalKey] = $folder;
                break;
            }
        }

        if (isset($map[$logicalKey])) {
            continue;
        }

        foreach (mailLogicalFolderAliases()[$logicalKey] ?? [] as $try) {
            $found = $client->getFolderByPath($try, false, true);
            if (!$found) {
                $found = $client->getFolderByName($try, true);
            }
            if (!$found) {
                $found = $client->getFolder($try);
            }
            if ($found instanceof Folder) {
                $map[$logicalKey] = $found;
                break;
            }
        }
    }

    return $map;
}

function mailOpenLogicalFolder(Client $client, string $logicalFolder): ?Folder
{
    $logicalFolder = $logicalFolder !== '' ? $logicalFolder : 'INBOX';
    static $cache = [];
    $cacheKey = spl_object_id($client) . ':' . $logicalFolder;
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $map = mailDiscoverFolderMap($client);
    $folder = $map[$logicalFolder] ?? null;
    $cache[$cacheKey] = $folder;

    return $folder;
}

function mailSyncMessagesFromImapFolder(mysqli $conn, int $accountId, string $storedFolder, Folder $imapFolder, int $limit): int
{
    $accountId = (int) $accountId;
    $limit = max(1, min(100, $limit));
    $synced = 0;

    $messages = $imapFolder->messages()
        ->all()
        ->fetchOrderDesc()
        ->setFetchBody(false)
        ->setFetchFlags(true)
        ->limit($limit, 1)
        ->get();

    foreach ($messages as $msg) {
        $uid = (string) $msg->getUid();
        if ($uid === '' || $uid === '0') {
            continue;
        }

        $from = $msg->getFrom();
        $fromEmail = '';
        $fromName = '';
        if ($from && $from->count() > 0) {
            $first = $from->first();
            if ($first instanceof \Webklex\PHPIMAP\Address) {
                $fromEmail = (string) $first->mail;
                $fromName = (string) $first->personal;
            }
        }

        $toArr = [];
        $to = $msg->getTo();
        if ($to && $to->count() > 0) {
            foreach ($to->toArray() as $addr) {
                if ($addr instanceof \Webklex\PHPIMAP\Address) {
                    $toArr[] = ['email' => (string) $addr->mail, 'name' => (string) $addr->personal];
                }
            }
        }

        $subjectAttr = $msg->getSubject();
        $subject = is_object($subjectAttr) ? (string) $subjectAttr : (string) $subjectAttr;
        $bodyText = '';
        $bodyHtml = '';
        $sentAt = date('Y-m-d H:i:s');
        $dateAttr = $msg->getDate();
        if ($dateAttr && $dateAttr->count() > 0) {
            try {
                $sentAt = $dateAttr->toDate()->format('Y-m-d H:i:s');
            } catch (Throwable $e) {
                $sentAt = (string) $dateAttr;
            }
        }
        $hasAtt = 0;
        $flags = $msg->getFlags();
        $isRead = ($flags && ($flags->contains('seen') || $flags->contains('\\Seen'))) ? 1 : 0;

        $stmt = $conn->prepare(
            'INSERT INTO mail_messages
            (account_id, folder, message_uid, subject, from_email, from_name, to_json, body_text, body_html,
             has_attachments, is_read, sent_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE
            subject=VALUES(subject), from_email=VALUES(from_email), from_name=VALUES(from_name),
            to_json=VALUES(to_json),
            body_text=IF(VALUES(body_text)!=\'\', VALUES(body_text), body_text),
            body_html=IF(VALUES(body_html)!=\'\', VALUES(body_html), body_html),
            has_attachments=IF(VALUES(body_text)!=\'\' OR VALUES(body_html)!=\'\', VALUES(has_attachments), has_attachments),
            is_read=VALUES(is_read), sent_at=VALUES(sent_at),
            synced_at=NOW()'
        );
        if ($stmt) {
            $toJson = json_encode($toArr);
            $stmt->bind_param(
                'issssssssiis',
                $accountId,
                $storedFolder,
                $uid,
                $subject,
                $fromEmail,
                $fromName,
                $toJson,
                $bodyText,
                $bodyHtml,
                $hasAtt,
                $isRead,
                $sentAt
            );
            if ($stmt->execute()) {
                $synced++;
            }
            $stmt->close();
        }
    }

    return $synced;
}

function mailSyncAllFolders(mysqli $conn, array $account, int $limit = 30): array
{
    if (($account['imap_status'] ?? '') !== 'active') {
        return ['ok' => false, 'message' => 'IMAP is not active.', 'synced' => 0];
    }

    $cfg = mailImapClientConfig($account);
    if ($cfg['host'] === '' || $cfg['username'] === '' || $cfg['password'] === '') {
        return ['ok' => false, 'message' => 'IMAP settings incomplete.', 'synced' => 0];
    }

    @set_time_limit(300);

    $accountId = (int) $account['id'];
    $totalSynced = 0;
    $byFolder = [];

    try {
        $cm = new ClientManager();
        $client = $cm->make($cfg);
        $client->connect();
        $folderMap = mailDiscoverFolderMap($client);

        foreach (array_keys(mailFolderMap()) as $logicalKey) {
            if (!isset($folderMap[$logicalKey])) {
                continue;
            }
            $count = mailSyncMessagesFromImapFolder(
                $conn,
                $accountId,
                $logicalKey,
                $folderMap[$logicalKey],
                $limit
            );
            if ($count > 0) {
                $byFolder[$logicalKey] = $count;
                $totalSynced += $count;
            }
        }

        $client->disconnect();
        $conn->query("UPDATE mail_accounts SET last_sync_at = NOW() WHERE id = $accountId");

        if ($totalSynced === 0) {
            $found = count($folderMap);
            $msg = $found > 0
                ? 'Sync completed. No new messages found in your mail folders.'
                : 'Sync completed. Could not match IMAP folders (check Gmail IMAP is enabled).';
            return ['ok' => true, 'message' => $msg, 'synced' => 0, 'folders' => $byFolder];
        }

        $parts = [];
        foreach ($byFolder as $key => $cnt) {
            $label = mailFolderMap()[$key]['label'] ?? $key;
            $parts[] = "$label ($cnt)";
        }

        return [
            'ok' => true,
            'message' => 'Synced ' . $totalSynced . ' message(s): ' . implode(', ', $parts) . '.',
            'synced' => $totalSynced,
            'folders' => $byFolder,
        ];
    } catch (Throwable $e) {
        return ['ok' => false, 'message' => 'IMAP sync failed: ' . $e->getMessage(), 'synced' => $totalSynced];
    }
}

function mailFetchMessageBody(mysqli $conn, array $account, array $messageRow): array
{
    $bodyText = (string) ($messageRow['body_text'] ?? '');
    $bodyHtml = (string) ($messageRow['body_html'] ?? '');
    $hasAtt = (int) ($messageRow['has_attachments'] ?? 0);
    if ($bodyText !== '' || $bodyHtml !== '') {
        return [
            'ok' => true,
            'body_text' => $bodyText,
            'body_html' => $bodyHtml,
            'has_attachments' => $hasAtt,
        ];
    }

    if (($account['imap_status'] ?? '') !== 'active') {
        return ['ok' => false, 'message' => 'IMAP is not active.'];
    }

    $uid = (string) ($messageRow['message_uid'] ?? '');
    if ($uid === '' || $uid === '0') {
        return ['ok' => false, 'message' => 'Message UID missing.'];
    }

    $cfg = mailImapClientConfig($account);
    if ($cfg['host'] === '' || $cfg['username'] === '' || $cfg['password'] === '') {
        return ['ok' => false, 'message' => 'IMAP settings incomplete.'];
    }

    @set_time_limit(90);

    $folderName = (string) ($messageRow['folder'] ?? 'INBOX');
    $messageId = (int) ($messageRow['id'] ?? 0);

    try {
        $cm = new ClientManager();
        $client = $cm->make($cfg);
        $client->connect();
        $imapFolder = mailOpenLogicalFolder($client, $folderName);
        if (!$imapFolder) {
            $client->disconnect();
            return ['ok' => false, 'message' => "Folder \"$folderName\" not found on mail server."];
        }

        $msg = $imapFolder->query()->getMessageByUid((int) $uid);
        $bodyText = (string) $msg->getTextBody();
        $bodyHtml = (string) $msg->getHTMLBody();
        $hasAtt = $msg->getAttachments()->count() > 0 ? 1 : 0;

        $client->disconnect();

        if ($messageId > 0) {
            $stmt = $conn->prepare(
                'UPDATE mail_messages SET body_text = ?, body_html = ?, has_attachments = ?, synced_at = NOW() WHERE id = ?'
            );
            if ($stmt) {
                $stmt->bind_param('ssii', $bodyText, $bodyHtml, $hasAtt, $messageId);
                $stmt->execute();
                $stmt->close();
            }
        }

        return [
            'ok' => true,
            'body_text' => $bodyText,
            'body_html' => $bodyHtml,
            'has_attachments' => $hasAtt,
        ];
    } catch (Throwable $e) {
        return ['ok' => false, 'message' => 'Could not load message body: ' . $e->getMessage()];
    }
}

function mailSyncFolder(mysqli $conn, array $account, string $folder = 'INBOX', int $limit = 30): array
{
    if (($account['imap_status'] ?? '') !== 'active') {
        return ['ok' => false, 'message' => 'IMAP is not active.', 'synced' => 0];
    }

    $cfg = mailImapClientConfig($account);
    if ($cfg['host'] === '' || $cfg['username'] === '' || $cfg['password'] === '') {
        return ['ok' => false, 'message' => 'IMAP settings incomplete. Save IMAP host, username and password.', 'synced' => 0];
    }

    @set_time_limit(180);

    $logicalFolder = $folder !== '' ? $folder : 'INBOX';
    $accountId = (int) $account['id'];
    $synced = 0;

    try {
        $cm = new ClientManager();
        $client = $cm->make($cfg);
        $client->connect();
        $imapFolder = mailOpenLogicalFolder($client, $logicalFolder);
        if (!$imapFolder) {
            $client->disconnect();
            return ['ok' => false, 'message' => "Folder \"$logicalFolder\" not found on mail server.", 'synced' => 0];
        }

        $synced = mailSyncMessagesFromImapFolder($conn, $accountId, $logicalFolder, $imapFolder, $limit);

        $client->disconnect();
        $conn->query("UPDATE mail_accounts SET last_sync_at = NOW() WHERE id = $accountId");

        $label = mailFolderMap()[$logicalFolder]['label'] ?? $logicalFolder;
        $resultMsg = $synced > 0
            ? "Synced $synced message(s) in $label."
            : "Sync completed. No messages found in $label.";
        return ['ok' => true, 'message' => $resultMsg, 'synced' => $synced];
    } catch (Throwable $e) {
        return ['ok' => false, 'message' => 'IMAP sync failed: ' . $e->getMessage(), 'synced' => $synced];
    }
}

function mailFolderMap(): array
{
    return [
        'INBOX' => ['label' => 'Inbox', 'icon' => 'fa-inbox'],
        'Spam' => ['label' => 'Spam', 'icon' => 'fa-exclamation-triangle'],
        'Archive' => ['label' => 'Archive', 'icon' => 'fa-archive'],
        'Sent' => ['label' => 'Sent', 'icon' => 'fa-paper-plane'],
        'Trash' => ['label' => 'Trash', 'icon' => 'fa-trash'],
        'Drafts' => ['label' => 'Drafts', 'icon' => 'fa-file-alt'],
    ];
}
