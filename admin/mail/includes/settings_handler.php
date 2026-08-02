<?php

if (!isset($settingsPageUrl) || $settingsPageUrl === '') {
    $settingsPageUrl = 'crm/office_settings.php';
}
if (!isset($settingsRedirectUrl) || $settingsRedirectUrl === '') {
    $settingsRedirectUrl = 'office_settings.php';
}

$adminId = mailGetAdminId();
$account = mailGetAccount($conn, $adminId);
$org = mailGetOrgSettings($conn);
$admins = mailListAdmins($conn, $adminId);
$sharing = mailListSharing($conn, $adminId);

$tab = $_GET['tab'] ?? 'account';
$msg = '';
$msgType = 'success';

if (isset($_SESSION['mail_settings_msg'])) {
    $msg = (string) $_SESSION['mail_settings_msg'];
    $msgType = (string) ($_SESSION['mail_settings_msg_type'] ?? 'success');
    unset($_SESSION['mail_settings_msg'], $_SESSION['mail_settings_msg_type']);
}

function mailSettingsRedirect(string $url, string $tab, string $msg, string $msgType): void
{
    $_SESSION['mail_settings_msg'] = $msg;
    $_SESSION['mail_settings_msg_type'] = $msgType;
    header('Location: ' . $url . '?tab=' . urlencode($tab));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_account'])) {
        $emailProvider = trim($_POST['email_provider'] ?? 'custom');
        $emailAddress = trim($_POST['email_address'] ?? '');
        $fromName = trim($_POST['from_name'] ?? '');
        $smtpHost = trim($_POST['smtp_host'] ?? '');
        $smtpPort = max(1, (int) ($_POST['smtp_port'] ?? 587));
        $smtpEnc = trim($_POST['smtp_encryption'] ?? 'tls');
        $smtpUser = trim($_POST['smtp_username'] ?? '');
        $smtpPass = (string) ($_POST['smtp_password'] ?? '');
        $smtpStatus = trim($_POST['smtp_status'] ?? 'active');
        $imapStatus = trim($_POST['imap_status'] ?? 'inactive');
        $imapHost = trim($_POST['imap_host'] ?? '');
        $imapPort = max(1, (int) ($_POST['imap_port'] ?? 993));
        $imapEnc = trim($_POST['imap_encryption'] ?? 'ssl');
        $imapUser = trim($_POST['imap_username'] ?? '');
        $imapPass = (string) ($_POST['imap_password'] ?? '');
        $imapFolder = trim($_POST['imap_folder'] ?? 'INBOX') ?: 'INBOX';
        $useOrgSmtp = !empty($_POST['use_org_smtp']) ? 1 : 0;

        if ($emailAddress === '' || $smtpHost === '') {
            $msg = 'Email address and SMTP host are required.';
            $msgType = 'danger';
        } else {
            $smtpPassEnc = $smtpPass !== '' ? mailEncrypt($smtpPass) : ($account['smtp_password_enc'] ?? '');
            $imapPassEnc = $imapPass !== '' ? mailEncrypt($imapPass) : ($account['imap_password_enc'] ?? '');

            $dbError = '';
            if ($account) {
                $stmt = $conn->prepare(
                    'UPDATE mail_accounts SET email_provider=?, email_address=?, from_name=?,
                     smtp_host=?, smtp_port=?, smtp_encryption=?, smtp_username=?, smtp_password_enc=?,
                     smtp_status=?, imap_status=?, imap_host=?, imap_port=?, imap_encryption=?,
                     imap_username=?, imap_password_enc=?, imap_folder=?, use_org_smtp=?, updated_at=NOW()
                     WHERE id=? AND admin_id=?'
                );
                if (!$stmt) {
                    $dbError = $conn->error;
                    $ok = false;
                } else {
                    $accId = (int) $account['id'];
                    $stmt->bind_param(
                        'ssssissssssissssiii',
                        $emailProvider, $emailAddress, $fromName,
                        $smtpHost, $smtpPort, $smtpEnc, $smtpUser, $smtpPassEnc,
                        $smtpStatus, $imapStatus, $imapHost, $imapPort, $imapEnc,
                        $imapUser, $imapPassEnc, $imapFolder, $useOrgSmtp,
                        $accId, $adminId
                    );
                    $ok = $stmt->execute();
                    if (!$ok) {
                        $dbError = $stmt->error;
                    }
                    $stmt->close();
                }
            } else {
                $stmt = $conn->prepare(
                    'INSERT INTO mail_accounts
                    (admin_id, email_provider, email_address, from_name, smtp_host, smtp_port, smtp_encryption,
                     smtp_username, smtp_password_enc, smtp_status, imap_status, imap_host, imap_port,
                     imap_encryption, imap_username, imap_password_enc, imap_folder, use_org_smtp)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
                );
                if (!$stmt) {
                    $dbError = $conn->error;
                    $ok = false;
                } else {
                    $stmt->bind_param(
                        'issssissssssissssi',
                        $adminId, $emailProvider, $emailAddress, $fromName,
                        $smtpHost, $smtpPort, $smtpEnc, $smtpUser, $smtpPassEnc,
                        $smtpStatus, $imapStatus, $imapHost, $imapPort, $imapEnc,
                        $imapUser, $imapPassEnc, $imapFolder, $useOrgSmtp
                    );
                    $ok = $stmt->execute();
                    if (!$ok) {
                        $dbError = $stmt->error;
                    }
                    $stmt->close();
                }
            }

            if ($ok) {
                mailSettingsRedirect($settingsRedirectUrl, 'account', 'Email settings saved successfully.', 'success');
            }
            $msg = $dbError !== '' ? 'Failed to save settings: ' . $dbError : 'Failed to save settings.';
            $msgType = 'danger';
            $account = mailGetAccount($conn, $adminId);
        }
        $tab = 'account';
    }

    if (isset($_POST['save_org_smtp'])) {
        $smtpHost = trim($_POST['org_smtp_host'] ?? '');
        $smtpPort = max(1, (int) ($_POST['org_smtp_port'] ?? 587));
        $smtpEnc = trim($_POST['org_smtp_encryption'] ?? 'tls');
        $smtpUser = trim($_POST['org_smtp_username'] ?? '');
        $smtpPass = (string) ($_POST['org_smtp_password'] ?? '');
        $fromEmail = trim($_POST['org_from_email'] ?? '');
        $fromName = trim($_POST['org_from_name'] ?? '');
        $isActive = !empty($_POST['org_is_active']) ? 1 : 0;

        $smtpPassEnc = $smtpPass !== '' ? mailEncrypt($smtpPass) : ($org['smtp_password_enc'] ?? '');

        $stmt = $conn->prepare(
            'UPDATE mail_org_settings SET smtp_host=?, smtp_port=?, smtp_encryption=?, smtp_username=?,
             smtp_password_enc=?, from_email=?, from_name=?, is_active=?, updated_at=NOW() WHERE id=1'
        );
        $orgDbError = '';
        $ok = false;
        if ($stmt) {
            $stmt->bind_param('sisssssi', $smtpHost, $smtpPort, $smtpEnc, $smtpUser, $smtpPassEnc, $fromEmail, $fromName, $isActive);
            $ok = $stmt->execute();
            if (!$ok) {
                $orgDbError = $stmt->error;
            }
            $stmt->close();
        } else {
            $orgDbError = $conn->error;
        }

        if ($ok) {
            mailSettingsRedirect($settingsRedirectUrl, 'org', 'Organization SMTP saved.', 'success');
        }
        $msg = 'Failed to save organization SMTP' . ($orgDbError !== '' ? ': ' . $orgDbError : '.');
        $msgType = 'danger';
        $org = mailGetOrgSettings($conn);
        $tab = 'org';
    }

    if (isset($_POST['save_sharing'])) {
        $sharedWith = (int) ($_POST['shared_with_admin_id'] ?? 0);
        $canRead = !empty($_POST['can_read']) ? 1 : 0;
        $canSend = !empty($_POST['can_send']) ? 1 : 0;

        if ($sharedWith <= 0 || $sharedWith === $adminId) {
            $msg = 'Please select a valid user to share with.';
            $msgType = 'danger';
        } else {
            $stmt = $conn->prepare(
                'INSERT INTO mail_sharing (owner_admin_id, shared_with_admin_id, can_read, can_send)
                 VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE can_read=VALUES(can_read), can_send=VALUES(can_send)'
            );
            $stmt->bind_param('iiii', $adminId, $sharedWith, $canRead, $canSend);
            $ok = $stmt->execute();
            $stmt->close();
            $msg = $ok ? 'Sharing rule saved.' : 'Failed to save sharing rule.';
            $msgType = $ok ? 'success' : 'danger';
            $sharing = mailListSharing($conn, $adminId);
        }
        $tab = 'sharing';
    }

    if (isset($_POST['send_test_email'])) {
        $testTo = trim($_POST['test_email_to'] ?? '');
        if ($testTo === '') {
            $msg = 'Test email address is required.';
            $msgType = 'danger';
        } else {
            $smtpCfg = null;
            if ($account && ($account['smtp_status'] ?? '') === 'active' && empty($account['use_org_smtp'])) {
                $smtpCfg = mailSmtpConfigFromAccount($account, $org);
            } elseif (!empty($org['is_active']) && !empty($org['smtp_host'])) {
                $smtpCfg = mailSmtpConfigFromOrg($org);
            } elseif ($account) {
                $smtpCfg = mailSmtpConfigFromAccount($account, $org);
            }

            if (!$smtpCfg || $smtpCfg['host'] === '') {
                $msg = 'Configure SMTP settings before sending a test email.';
                $msgType = 'danger';
            } else {
                $result = mailTestSmtp($smtpCfg, $testTo);
                $msg = $result['message'];
                $msgType = $result['ok'] ? 'success' : 'danger';
            }
        }
        $tab = 'account';
    }

    if (isset($_POST['delete_share_id'])) {
        $delId = (int) $_POST['delete_share_id'];
        $conn->query("DELETE FROM mail_sharing WHERE id = $delId AND owner_admin_id = $adminId");
        $sharing = mailListSharing($conn, $adminId);
        $msg = 'Sharing rule removed.';
        $tab = 'sharing';
    }
}

$isActive = ($account && ($account['smtp_status'] ?? '') === 'active')
    || (!empty($org['is_active']) && !empty($org['smtp_host']));
