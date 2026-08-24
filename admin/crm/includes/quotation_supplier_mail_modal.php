<?php
$qMailFromName = htmlspecialchars(trim((string) ($qMailFromName ?? 'CRM Admin')), ENT_QUOTES, 'UTF-8');
$qMailFromEmail = htmlspecialchars(trim((string) ($qMailFromEmail ?? '')), ENT_QUOTES, 'UTF-8');
$qMailSubject = htmlspecialchars(trim((string) ($qMailSubject ?? '')), ENT_QUOTES, 'UTF-8');
$qMailFromInitial = htmlspecialchars(strtoupper(substr($qMailFromName, 0, 1)), ENT_QUOTES, 'UTF-8');
?>
<div class="modal fade" id="qSupplierMailModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg q-supplier-mail-dialog" role="document">
        <div class="modal-content q-supplier-mail-modal">
            <div class="modal-header q-supplier-mail-header">
                <h5 class="modal-title mb-0">Send Email</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="qSupplierMailForm" enctype="multipart/form-data">
                <div class="modal-body q-supplier-mail-body">
                    <div class="q-supplier-mail-from-field">
                        <div class="q-supplier-mail-from-row">
                            <span class="q-supplier-mail-from-label">From:</span>
                            <div class="q-supplier-mail-from-picker">
                                <span class="q-supplier-mail-avatar" id="qSupplierMailAvatar"><?= $qMailFromInitial ?></span>
                                <?php
                                $mailSenders = is_array($mailSenders ?? null) ? $mailSenders : [];
                                if ($mailSenders) {
                                ?>
                                    <select name="sender_id" id="qSupplierMailSender" class="q-supplier-mail-sender-select" required>
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
                                    <span class="text-muted small">
                                        No active senders.
                                        <a href="crm/email_master.php" target="_blank" rel="noopener">Email Master</a>
                                    </span>
                                <?php } ?>
                            </div>
                        </div>
                    </div>

                    <div class="q-supplier-mail-from-field q-supplier-mail-to-field">
                        <div class="q-supplier-mail-from-row q-supplier-mail-to-main-row">
                            <span class="q-supplier-mail-from-label">To:</span>
                            <div class="q-supplier-mail-to-picker" id="qSupplierMailToPicker">
                                <button type="button" class="q-supplier-mail-to-trigger" id="qSupplierMailToTrigger" aria-haspopup="listbox" aria-expanded="false">
                                    <span class="q-supplier-mail-to-trigger-text" id="qSupplierMailToTriggerText">Select recipients</span>
                                    <i class="fas fa-chevron-down q-supplier-mail-to-trigger-icon"></i>
                                </button>
                                <div class="q-supplier-mail-to-menu" id="qSupplierMailToMenu" style="display:none;">
                                    <div class="q-supplier-mail-to-menu-list" id="qSupplierMailToMenuList"></div>
                                    <div class="q-supplier-mail-to-custom">
                                        <input type="email" class="form-control form-control-sm" id="qSupplierMailCustomEmail"
                                               placeholder="Type email address" autocomplete="off">
                                        <button type="button" class="btn btn-sm btn-primary" id="qSupplierMailCustomAddBtn">Add</button>
                                    </div>
                                    <div class="q-supplier-mail-supplier-empty text-muted small d-none" id="qSupplierMailSupplierEmpty">
                                        No suppliers with email found for this destination.
                                    </div>
                                </div>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-primary q-supplier-mail-create-btn" id="qSupplierMailCreateBtn">
                                <i class="fas fa-plus mr-1"></i> Create New Supplier
                            </button>
                        </div>
                        <div class="q-supplier-mail-recipient-badges" id="qSupplierMailRecipientBadges"></div>
                    </div>

                    <div class="q-supplier-mail-from-field q-supplier-mail-subject-field">
                        <div class="q-supplier-mail-from-row">
                            <span class="q-supplier-mail-from-label">Subject</span>
                            <input type="text" name="subject" id="qSupplierMailSubject"
                                   class="form-control q-supplier-mail-subject-input"
                                   value="<?= $qMailSubject ?>" placeholder="Subject" required>
                        </div>
                    </div>

                    <div class="q-supplier-mail-editor-wrap">
                        <textarea name="body" id="qSupplierMailBody" placeholder="Compose email"></textarea>
                    </div>
                </div>
                <div class="modal-footer q-supplier-mail-footer">
                    <div class="q-gmail-compose-bar">
                        <button type="submit" class="btn q-gmail-send-btn" id="qSupplierMailSendBtn">Send</button>
                        <div class="q-gmail-format-toolbar" id="qGmailFormatToolbar"></div>
                        <input type="file" name="attachment" id="qSupplierMailAttachment" class="d-none" accept="*/*">
                        <button type="button" class="btn q-gmail-icon-btn" id="qSupplierMailAttachBtn" title="Attach files">
                            <i class="fas fa-paperclip"></i>
                        </button>
                        <span class="q-gmail-attach-label text-muted small" id="qSupplierMailAttachLabel"></span>
                    </div>
                    <div class="q-gmail-compose-actions">
                        <button type="button" class="btn q-gmail-icon-btn" id="qSupplierMailDiscardBtn" data-dismiss="modal" title="Discard">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                        <button type="button" class="btn btn-link q-gmail-close-link d-none" id="qSupplierMailCloseBtn" data-dismiss="modal">Close</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="qSupplierMailStatusModal" tabindex="-1" role="dialog" aria-hidden="true" data-backdrop="static" data-keyboard="false">
    <div class="modal-dialog modal-dialog-centered q-supplier-mail-status-dialog" role="document">
        <div class="modal-content q-supplier-mail-status-modal">
            <div class="modal-header q-supplier-mail-status-header">
                <h5 class="modal-title mb-0" id="qSupplierMailStatusTitle">Sending email…</h5>
                <button type="button" class="close d-none" id="qSupplierMailStatusHeaderClose" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body q-supplier-mail-status-body">
                <div class="q-supplier-mail-status-to-row">
                    <span class="q-supplier-mail-status-to-label">To:</span>
                    <span class="q-supplier-mail-status-to-summary" id="qSupplierMailStatusSummary">0 recipients selected</span>
                </div>
                <div class="q-supplier-mail-status-badges" id="qSupplierMailStatusBadges"></div>
            </div>
            <div class="modal-footer q-supplier-mail-status-footer">
                <button type="button" class="btn btn-primary d-none" id="qSupplierMailStatusCloseBtn" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="qSupplierCreateModal" tabindex="-1" role="dialog" aria-hidden="true" data-backdrop="static">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content q-supplier-create-modal">
            <div class="modal-header py-2">
                <h5 class="modal-title mb-0">Create New Supplier</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="qSupplierCreateForm">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Supplier Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="qScName" required>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>Contact Name</label>
                            <input type="text" class="form-control" id="qScContactName">
                        </div>
                        <div class="form-group col-md-6">
                            <label>Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="qScEmail" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Mobile</label>
                        <input type="text" class="form-control" id="qScMobile">
                    </div>
                    <div class="form-group mb-0">
                        <label>City / Destination</label>
                        <input type="text" class="form-control" id="qScDestination" readonly>
                        <small class="form-text text-muted">Saved as City in Supplier Master (and linked place when available).</small>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success" id="qScSaveBtn">
                        <i class="fas fa-save mr-1"></i> Save Supplier
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
