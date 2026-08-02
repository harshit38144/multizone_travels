<?php
$tabUrl = static function (string $t) use ($settingsPageUrl): string {
    return htmlspecialchars($settingsPageUrl . '?tab=' . urlencode($t));
};
?>
<?php if ($msg !== '') { ?>
    <div class="alert alert-<?= htmlspecialchars($msgType) ?> alert-dismissible fade show">
        <?= htmlspecialchars($msg) ?>
        <button type="button" class="close" data-dismiss="alert">&times;</button>
    </div>
<?php } ?>

<div class="row">
    <div class="col-lg-8">
        <ul class="nav nav-tabs mail-settings-tabs mb-3">
            <li class="nav-item">
                <a class="nav-link <?= $tab === 'account' ? 'active' : '' ?>" href="<?= $tabUrl('account') ?>">My Email Account</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $tab === 'org' ? 'active' : '' ?>" href="<?= $tabUrl('org') ?>">Organization SMTP</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $tab === 'sharing' ? 'active' : '' ?>" href="<?= $tabUrl('sharing') ?>">Email Sharing</a>
            </li>
        </ul>

        <?php if ($tab === 'account') { ?>
        <div class="card">
            <div class="card-body">
                <h5 class="font-weight-bold">My Email Account</h5>
                <p class="text-muted">CRM emails will be sent from your email account. If not configured, organization SMTP will be used.</p>

                <form method="post" action="<?= htmlspecialchars($settingsPageUrl) ?>" id="mailAccountSettingsForm">
                    <div class="form-group">
                        <label>Email Provider <span class="text-danger">*</span></label>
                        <select name="email_provider" class="form-control">
                            <?php
                            $providers = [
                                'custom' => 'Other Mail / Custom SMTP',
                                'gmail' => 'Gmail',
                                'outlook' => 'Outlook',
                                'zoho' => 'Zoho Mail',
                            ];
                            $curProv = $account['email_provider'] ?? 'custom';
                            foreach ($providers as $k => $label) {
                                echo '<option value="' . htmlspecialchars($k) . '"' . ($curProv === $k ? ' selected' : '') . '>' . htmlspecialchars($label) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>Email Address <span class="text-danger">*</span></label>
                            <input type="email" name="email_address" class="form-control" required
                                   value="<?= htmlspecialchars($account['email_address'] ?? $_SESSION['email'] ?? '') ?>">
                        </div>
                        <div class="form-group col-md-6">
                            <label>From Name <span class="text-danger">*</span></label>
                            <input type="text" name="from_name" class="form-control" required
                                   value="<?= htmlspecialchars($account['from_name'] ?? $_SESSION['name'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>SMTP Host <span class="text-danger">*</span></label>
                            <input type="text" name="smtp_host" class="form-control" required
                                   value="<?= htmlspecialchars($account['smtp_host'] ?? '') ?>" placeholder="mail.example.com">
                        </div>
                        <div class="form-group col-md-3">
                            <label>SMTP Port <span class="text-danger">*</span></label>
                            <input type="number" name="smtp_port" class="form-control" required
                                   value="<?= (int) ($account['smtp_port'] ?? 587) ?>">
                        </div>
                        <div class="form-group col-md-3">
                            <label>Encryption <span class="text-danger">*</span></label>
                            <select name="smtp_encryption" class="form-control">
                                <?php foreach (['tls' => 'TLS', 'ssl' => 'SSL', 'none' => 'None'] as $k => $l) {
                                    $sel = ($account['smtp_encryption'] ?? 'tls') === $k ? ' selected' : '';
                                    echo "<option value=\"$k\"$sel>$l</option>";
                                } ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>SMTP Username <span class="text-danger">*</span></label>
                            <input type="text" name="smtp_username" class="form-control" required
                                   value="<?= htmlspecialchars($account['smtp_username'] ?? '') ?>">
                        </div>
                        <div class="form-group col-md-6">
                            <label>SMTP Password <?= $account ? '' : '<span class="text-danger">*</span>' ?></label>
                            <input type="password" name="smtp_password" class="form-control"
                                   placeholder="<?= $account ? 'Leave blank to keep current' : '' ?>">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>Status</label>
                            <select name="smtp_status" class="form-control">
                                <option value="active" <?= ($account['smtp_status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="inactive" <?= ($account['smtp_status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                            </select>
                        </div>
                        <div class="form-group col-md-6 d-flex align-items-end">
                            <div class="custom-control custom-checkbox mb-3">
                                <input type="checkbox" class="custom-control-input" id="useOrgSmtp" name="use_org_smtp" value="1"
                                    <?= !empty($account['use_org_smtp']) ? 'checked' : '' ?>>
                                <label class="custom-control-label" for="useOrgSmtp">Use organization SMTP for sending</label>
                            </div>
                        </div>
                    </div>

                    <div class="mail-imap-box">
                        <h6 class="font-weight-bold">IMAP Email Setting (Incoming)</h6>
                        <p class="text-muted small">Mail will appear in the left menu only when this IMAP setting is active.</p>
                        <p class="text-muted small"><em>IMAP server host usually same as SMTP host (e.g. mail.yourdomain.com).</em></p>

                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label>IMAP Status</label>
                                <select name="imap_status" class="form-control">
                                    <option value="active" <?= ($account['imap_status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option>
                                    <option value="inactive" <?= ($account['imap_status'] ?? 'inactive') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                </select>
                            </div>
                            <div class="form-group col-md-5">
                                <label>IMAP Server (Incoming)</label>
                                <input type="text" name="imap_host" class="form-control"
                                       value="<?= htmlspecialchars($account['imap_host'] ?? '') ?>">
                            </div>
                            <div class="form-group col-md-3">
                                <label>IMAP Port</label>
                                <input type="number" name="imap_port" class="form-control"
                                       value="<?= (int) ($account['imap_port'] ?? 993) ?>">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label>Security Type</label>
                                <select name="imap_encryption" class="form-control">
                                    <?php
                                    $imapEncs = [
                                        'ssl' => 'imap/ssl',
                                        'imap/ssl/novalidate-cert' => 'imap/ssl/novalidate-cert',
                                        'tls' => 'imap/tls',
                                        'none' => 'None',
                                    ];
                                    $curImapEnc = $account['imap_encryption'] ?? 'ssl';
                                    foreach ($imapEncs as $k => $l) {
                                        echo '<option value="' . htmlspecialchars($k) . '"' . ($curImapEnc === $k ? ' selected' : '') . '>' . htmlspecialchars($l) . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="form-group col-md-4">
                                <label>IMAP Username</label>
                                <input type="text" name="imap_username" class="form-control"
                                       value="<?= htmlspecialchars($account['imap_username'] ?? '') ?>">
                            </div>
                            <div class="form-group col-md-4">
                                <label>IMAP Password</label>
                                <input type="password" name="imap_password" class="form-control"
                                       placeholder="<?= $account ? 'Leave blank to keep current' : '' ?>">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Default Folder</label>
                            <input type="text" name="imap_folder" class="form-control"
                                   value="<?= htmlspecialchars($account['imap_folder'] ?? 'INBOX') ?>">
                        </div>
                    </div>

                    <button type="submit" name="save_account" class="btn btn-success btn-lg mt-3">
                        Save Email Settings
                    </button>
                </form>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-body">
                <h5 class="font-weight-bold">Send Test Email</h5>
                <p class="text-muted">Send a test email using your email account. If your account is not configured, organization SMTP will be used.</p>
                <form method="post" action="<?= htmlspecialchars($settingsPageUrl) ?>" class="form-inline">
                    <div class="form-group mr-2 mb-2 flex-grow-1">
                        <label class="sr-only">Test Email To</label>
                        <input type="email" name="test_email_to" class="form-control w-100" required
                               placeholder="Test Email To *"
                               value="<?= htmlspecialchars($account['email_address'] ?? $_SESSION['email'] ?? '') ?>">
                    </div>
                    <button type="submit" name="send_test_email" class="btn btn-outline-secondary mb-2">
                        Send Test Email
                    </button>
                </form>
            </div>
        </div>
        <?php } ?>

        <?php if ($tab === 'org') { ?>
        <div class="card">
            <div class="card-body">
                <h5 class="font-weight-bold">Organization SMTP</h5>
                <p class="text-muted">Fallback SMTP used when a user has not configured their own account or has enabled "Use organization SMTP".</p>
                <form method="post" action="<?= htmlspecialchars($settingsPageUrl) ?>">
                    <div class="form-row">
                        <div class="form-group col-md-8">
                            <label>SMTP Host</label>
                            <input type="text" name="org_smtp_host" class="form-control"
                                   value="<?= htmlspecialchars($org['smtp_host'] ?? '') ?>">
                        </div>
                        <div class="form-group col-md-4">
                            <label>SMTP Port</label>
                            <input type="number" name="org_smtp_port" class="form-control"
                                   value="<?= (int) ($org['smtp_port'] ?? 587) ?>">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label>Encryption</label>
                            <select name="org_smtp_encryption" class="form-control">
                                <?php foreach (['tls' => 'TLS', 'ssl' => 'SSL', 'none' => 'None'] as $k => $l) {
                                    $sel = ($org['smtp_encryption'] ?? 'tls') === $k ? ' selected' : '';
                                    echo "<option value=\"$k\"$sel>$l</option>";
                                } ?>
                            </select>
                        </div>
                        <div class="form-group col-md-4">
                            <label>SMTP Username</label>
                            <input type="text" name="org_smtp_username" class="form-control"
                                   value="<?= htmlspecialchars($org['smtp_username'] ?? '') ?>">
                        </div>
                        <div class="form-group col-md-4">
                            <label>SMTP Password</label>
                            <input type="password" name="org_smtp_password" class="form-control"
                                   placeholder="Leave blank to keep current">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>From Email</label>
                            <input type="email" name="org_from_email" class="form-control"
                                   value="<?= htmlspecialchars($org['from_email'] ?? '') ?>">
                        </div>
                        <div class="form-group col-md-6">
                            <label>From Name</label>
                            <input type="text" name="org_from_name" class="form-control"
                                   value="<?= htmlspecialchars($org['from_name'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="custom-control custom-checkbox mb-3">
                        <input type="checkbox" class="custom-control-input" id="orgActive" name="org_is_active" value="1"
                            <?= !empty($org['is_active']) ? 'checked' : '' ?>>
                        <label class="custom-control-label" for="orgActive">Organization SMTP active</label>
                    </div>
                    <button type="submit" name="save_org_smtp" class="btn btn-success">Save Organization SMTP</button>
                </form>
            </div>
        </div>
        <?php } ?>

        <?php if ($tab === 'sharing') { ?>
        <div class="card">
            <div class="card-body">
                <h5 class="font-weight-bold">Email Sharing</h5>
                <p class="text-muted">Allow other CRM users to read or send from your configured mailbox.</p>
                <form method="post" action="<?= htmlspecialchars($settingsPageUrl) ?>" class="mb-4">
                    <div class="form-row align-items-end">
                        <div class="form-group col-md-5">
                            <label>Share with user</label>
                            <select name="shared_with_admin_id" class="form-control" required>
                                <option value="">Select user</option>
                                <?php foreach ($admins as $a) { ?>
                                    <option value="<?= (int) $a['id'] ?>">
                                        <?= htmlspecialchars($a['name'] . ' (' . $a['email'] . ')') ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="form-group col-md-3">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="canRead" name="can_read" value="1" checked>
                                <label class="custom-control-label" for="canRead">Can read</label>
                            </div>
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="canSend" name="can_send" value="1">
                                <label class="custom-control-label" for="canSend">Can send</label>
                            </div>
                        </div>
                        <div class="form-group col-md-4">
                            <button type="submit" name="save_sharing" class="btn btn-success">Add sharing rule</button>
                        </div>
                    </div>
                </form>

                <?php if (!empty($sharing)) { ?>
                <table class="table table-sm table-bordered">
                    <thead><tr><th>User</th><th>Read</th><th>Send</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($sharing as $s) { ?>
                        <tr>
                            <td><?= htmlspecialchars(($s['shared_name'] ?? '') . ' (' . ($s['shared_email'] ?? '') . ')') ?></td>
                            <td><?= !empty($s['can_read']) ? 'Yes' : 'No' ?></td>
                            <td><?= !empty($s['can_send']) ? 'Yes' : 'No' ?></td>
                            <td>
                                <form method="post" action="<?= htmlspecialchars($settingsPageUrl) ?>" class="d-inline" onsubmit="return confirm('Remove this sharing rule?');">
                                    <input type="hidden" name="delete_share_id" value="<?= (int) $s['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Remove</button>
                                </form>
                            </td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
                <?php } else { ?>
                    <p class="text-muted">No sharing rules yet.</p>
                <?php } ?>
            </div>
        </div>
        <?php } ?>
    </div>

    <div class="col-lg-4">
        <div class="mail-info-card">
            <h6><i class="fas fa-info-circle text-success mr-1"></i> Email Rule</h6>
            <p class="small mb-0">Outgoing emails use your SMTP first and organization SMTP as fallback. Mail uses your own IMAP setting only.</p>
        </div>
        <div class="mail-info-card">
            <h6><i class="fas fa-plug text-success mr-1"></i> Recommended Ports</h6>
            <table class="table table-sm table-borderless mb-0 small">
                <tr><td>TLS</td><td><strong>587</strong></td></tr>
                <tr><td>SSL</td><td><strong>465</strong></td></tr>
                <tr><td>None</td><td><strong>25</strong></td></tr>
            </table>
        </div>
        <div class="mail-info-card">
            <h6><i class="fas fa-shield-alt text-success mr-1"></i> Security Note</h6>
            <p class="small mb-0">For Gmail, Outlook or Zoho Mail, use app password if two-step verification is enabled.</p>
        </div>
    </div>
</div>
