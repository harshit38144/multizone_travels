<?php
$composeTo = htmlspecialchars($composeTo ?? '');
$composeSubject = htmlspecialchars($composeSubject ?? '');
?>
<div class="modal fade" id="mailComposeModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg mail-compose-dialog" role="document">
        <div class="modal-content mail-compose-modal">
            <div class="mail-compose-header">
                <span class="mail-compose-title">New Message</span>
                <div class="mail-compose-header-actions">
                    <button type="button" class="btn btn-link text-white p-1" id="mailComposeExpand" title="Expand">
                        <i class="fas fa-expand"></i>
                    </button>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            </div>
            <form id="mailComposeForm" enctype="multipart/form-data">
                <div class="mail-compose-fields">
                    <?php
                    $mailSenderSelectId = 'composeSenderId';
                    include __DIR__ . '/sender_select.php';
                    ?>
                    <div class="mail-compose-row">
                        <label>To</label>
                        <input type="email" name="to" id="composeTo" class="form-control border-0" placeholder="Recipient email" value="<?= $composeTo ?>" required>
                    </div>
                    <div class="mail-compose-row">
                        <label>CC</label>
                        <input type="text" name="cc" id="composeCc" class="form-control border-0" placeholder="CC">
                    </div>
                    <div class="mail-compose-row">
                        <label>BCC</label>
                        <input type="text" name="bcc" id="composeBcc" class="form-control border-0" placeholder="BCC">
                    </div>
                    <div class="mail-compose-row">
                        <label>Subject</label>
                        <input type="text" name="subject" id="composeSubject" class="form-control border-0" placeholder="Subject" value="<?= $composeSubject ?>" required>
                    </div>
                </div>
                <div class="mail-compose-body-wrap">
                    <textarea name="body" id="composeBody" placeholder="Write your message"></textarea>
                </div>
                <div class="mail-compose-footer">
                    <span class="mail-compose-attach-label" id="composeAttachLabel">No attachment selected</span>
                    <input type="file" name="attachment" id="composeAttachment" class="d-none" accept="*/*">
                    <div class="mail-compose-footer-btns">
                        <button type="submit" class="btn btn-success" id="composeSendBtn">
                            <i class="fas fa-paper-plane mr-1"></i> Send
                        </button>
                        <button type="button" class="btn btn-outline-secondary" id="composeAttachBtn">
                            <i class="fas fa-paperclip mr-1"></i> Attach
                        </button>
                        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Discard</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
