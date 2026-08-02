<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../mail/includes/mail_db.php';
require_once __DIR__ . '/../mail/includes/mail_service.php';

mailEnsureTables($conn);

// Form lives under admin/crm/ but <base href="../"> points to admin/ — use path from admin root.
$settingsPageUrl = 'crm/office_settings.php';
$settingsRedirectUrl = 'office_settings.php';
require_once __DIR__ . '/../mail/includes/settings_handler.php';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>CRM Settings - Email Configuration</title>
    <base href="../">
    <?php include __DIR__ . '/../includes/header-links.php'; ?>
    <link rel="stylesheet" href="mail/assets/mail.css">
</head>
<body class="hold-transition sidebar-mini layout-fixed page-bg">
<div class="wrapper">
    <?php include __DIR__ . '/../includes/top-header.php'; ?>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2 align-items-center">
                    <div class="col-sm-8">
                        <p class="text-muted mb-0">CRM Settings</p>
                        <h1 class="m-0 text-dark">Email Configuration</h1>
                        <p class="text-muted mb-0">Configure user email accounts and organization fallback SMTP for CRM outgoing emails.</p>
                    </div>
                    <div class="col-sm-4 text-sm-right">
                        <span class="badge-active"><i class="fas fa-circle mr-1" style="font-size:8px;"></i> <?= $isActive ? 'Active' : 'Inactive' ?></span>
                    </div>
                </div>
            </div>
        </div>

        <section class="content">
            <div class="container-fluid">
                <?php include __DIR__ . '/../mail/includes/settings_view.php'; ?>
            </div>
        </section>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer-links.php'; ?>
<script>
window.MAIL_ACCOUNT_PRESETS = {
    zoho: {
        smtp_host: 'smtp.zoho.com', smtp_port: 587, smtp_encryption: 'tls',
        imap_host: 'imap.zoho.com', imap_port: 993, imap_encryption: 'ssl'
    },
    gmail: {
        smtp_host: 'smtp.gmail.com', smtp_port: 587, smtp_encryption: 'tls',
        imap_host: 'imap.gmail.com', imap_port: 993, imap_encryption: 'ssl'
    },
    outlook: {
        smtp_host: 'smtp.office365.com', smtp_port: 587, smtp_encryption: 'tls',
        imap_host: 'outlook.office365.com', imap_port: 993, imap_encryption: 'ssl'
    }
};

$(function () {
    var $form = $('#mailAccountSettingsForm');

    function applyMailPreset(provider) {
        var preset = (window.MAIL_ACCOUNT_PRESETS || {})[provider];
        if (!preset || !$form.length) {
            return;
        }
        $form.find('[name="smtp_host"]').val(preset.smtp_host || '');
        $form.find('[name="smtp_port"]').val(preset.smtp_port || 587);
        $form.find('[name="smtp_encryption"]').val(preset.smtp_encryption || 'tls');
        $form.find('[name="imap_host"]').val(preset.imap_host || '');
        $form.find('[name="imap_port"]').val(preset.imap_port || 993);
        $form.find('[name="imap_encryption"]').val(preset.imap_encryption || 'ssl');
        $form.find('[name="imap_status"]').val('active');
        var email = $form.find('[name="email_address"]').val().trim();
        if (email) {
            if (!$form.find('[name="smtp_username"]').val()) {
                $form.find('[name="smtp_username"]').val(email);
            }
            if (!$form.find('[name="imap_username"]').val()) {
                $form.find('[name="imap_username"]').val(email);
            }
        }
    }

    $form.on('change', '[name="email_provider"]', function () {
        applyMailPreset($(this).val());
    });

    $form.on('blur', '[name="email_address"]', function () {
        var email = $(this).val().trim();
        if (!email) {
            return;
        }
        if (!$form.find('[name="smtp_username"]').val()) {
            $form.find('[name="smtp_username"]').val(email);
        }
        if (!$form.find('[name="imap_username"]').val()) {
            $form.find('[name="imap_username"]').val(email);
        }
    });

    $form.on('submit', function () {
        var form = this;
        if (!form.checkValidity()) {
            form.reportValidity();
            return false;
        }
        return true;
    });
});
</script>
</body>
</html>
