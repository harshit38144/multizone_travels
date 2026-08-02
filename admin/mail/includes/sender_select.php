<?php
/** @var array<int, array<string, mixed>> $mailSenders */
/** @var string $mailSenderSelectId */
/** @var string $mailSenderWrapClass */

$mailSenders = is_array($mailSenders ?? null) ? $mailSenders : [];
$mailSenderSelectId = (string) ($mailSenderSelectId ?? 'mailSenderId');
$mailSenderWrapClass = (string) ($mailSenderWrapClass ?? 'mail-compose-row');
$mailSenderRequired = !isset($mailSenderRequired) || $mailSenderRequired;
?>
<div class="<?= htmlspecialchars($mailSenderWrapClass) ?>">
    <label>Sender Email<?= $mailSenderRequired ? ' <span class="text-danger">*</span>' : '' ?></label>
    <?php if ($mailSenders) { ?>
        <select name="sender_id" id="<?= htmlspecialchars($mailSenderSelectId) ?>" class="form-control border-0"<?= $mailSenderRequired ? ' required' : '' ?>>
            <?php foreach ($mailSenders as $sender) {
                $sid = (int) ($sender['id'] ?? 0);
                $fromName = trim((string) ($sender['from_name'] ?? $sender['label'] ?? ''));
                $fromEmail = trim((string) ($sender['from_email'] ?? ''));
                $label = $fromName !== '' ? $fromName . ' <' . $fromEmail . '>' : $fromEmail;
                ?>
                <option value="<?= $sid ?>"
                        data-from-name="<?= htmlspecialchars($fromName, ENT_QUOTES, 'UTF-8') ?>"
                        data-from-email="<?= htmlspecialchars($fromEmail, ENT_QUOTES, 'UTF-8') ?>">
                    <?= htmlspecialchars($label) ?>
                </option>
            <?php } ?>
        </select>
    <?php } else { ?>
        <p class="text-muted small mb-0">
            No active sender accounts.
            <a href="crm/email_master.php" target="_blank" rel="noopener">Add in Email Master</a>.
        </p>
    <?php } ?>
</div>
