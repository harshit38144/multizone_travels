<?php
/**
 * Shared create-payment-link form markup.
 *
 * Expects:
 * - array $gatewayOptions
 * - array $values (name, email, mobile, remarks, amount, payment_gateway)
 * - string $formAction optional (default: payment_link_create.php)
 * - bool $formInModal optional
 */
if (!isset($gatewayOptions) || !is_array($gatewayOptions)) {
    $gatewayOptions = function_exists('payment_gateway_options') ? payment_gateway_options() : [];
}
if (!isset($values) || !is_array($values)) {
    $values = [
        'name' => '',
        'email' => '',
        'mobile' => '',
        'remarks' => '',
        'amount' => '',
        'payment_gateway' => 'phonepe',
    ];
}
$formAction = isset($formAction) ? (string) $formAction : 'payment_link_create.php';
$formInModal = !empty($formInModal);
?>
<form method="post" action="<?= htmlspecialchars($formAction, ENT_QUOTES, 'UTF-8') ?>"
    class="js-pay-link-create-form pay-link-create-ui"<?= $formInModal ? ' data-ajax="1"' : '' ?>>
    <div class="pay-link-form-panel">
        <div class="form-group mb-3">
            <label class="pay-link-label">Payment method <span class="text-danger">*</span></label>
            <div class="gateway-radio-group" role="radiogroup" aria-label="Payment method">
                <?php foreach ($gatewayOptions as $gKey => $gLabel) {
                    $isChecked = (($values['payment_gateway'] ?? '') === $gKey);
                    $isPhonePe = ($gKey === 'phonepe');
                    $hint = $isPhonePe
                        ? 'UPI, cards & net banking'
                        : 'Cards, UPI & wallets via PayU';
                    $iconClass = $isPhonePe ? 'fa-mobile-alt' : 'fa-credit-card';
                    $cardClass = $isPhonePe ? 'gateway-radio-phonepe' : 'gateway-radio-payu';
                    ?>
                    <label class="gateway-radio-card <?= $cardClass ?><?= $isChecked ? ' is-selected' : '' ?>">
                        <input type="radio" name="payment_gateway" value="<?= htmlspecialchars($gKey) ?>"
                            <?= $isChecked ? 'checked' : '' ?> required>
                        <span class="gateway-radio-check"></span>
                        <span class="gateway-radio-icon"><i class="fas <?= $iconClass ?>"></i></span>
                        <span class="gateway-radio-copy">
                            <span class="gateway-radio-title"><?= htmlspecialchars($gLabel) ?></span>
                            <span class="gateway-radio-hint"><?= htmlspecialchars($hint) ?></span>
                        </span>
                    </label>
                <?php } ?>
            </div>
        </div>

        <div class="row">
            <div class="col-md-4 form-group">
                <label class="pay-link-label">Name <span class="text-danger">*</span></label>
                <div class="pay-contact-combobox">
                    <div class="pay-input-icon-wrap">
                        <span class="pay-input-icon"><i class="far fa-user"></i></span>
                        <input type="text" name="name" class="form-control pay-input-iconed js-pay-contact-lookup" required maxlength="120"
                            value="<?= htmlspecialchars((string) ($values['name'] ?? '')) ?>" placeholder="Customer full name" autocomplete="off">
                    </div>
                    <div class="pay-contact-menu js-pay-contact-menu" style="display:none;"></div>
                </div>
            </div>
            <div class="col-md-4 form-group">
                <label class="pay-link-label">Email <span class="text-danger">*</span></label>
                <div class="pay-contact-combobox">
                    <div class="pay-input-icon-wrap">
                        <span class="pay-input-icon"><i class="far fa-envelope"></i></span>
                        <input type="email" name="email" class="form-control pay-input-iconed js-pay-contact-lookup" required maxlength="200"
                            value="<?= htmlspecialchars((string) ($values['email'] ?? '')) ?>" placeholder="customer@email.com" autocomplete="off">
                    </div>
                    <div class="pay-contact-menu js-pay-contact-menu" style="display:none;"></div>
                </div>
            </div>
            <div class="col-md-4 form-group">
                <label class="pay-link-label">Mobile <span class="text-danger">*</span></label>
                <div class="pay-contact-combobox">
                    <div class="pay-input-icon-wrap">
                        <span class="pay-input-icon"><i class="fas fa-phone-alt"></i></span>
                        <input type="tel" name="mobile" class="form-control pay-input-iconed js-pay-contact-lookup" required maxlength="15"
                            value="<?= htmlspecialchars((string) ($values['mobile'] ?? '')) ?>" placeholder="9876543210" autocomplete="off">
                    </div>
                    <div class="pay-contact-menu js-pay-contact-menu" style="display:none;"></div>
                </div>
                <small class="pay-contacts-hint">Suggestions from <a href="lead_contacts.php" target="_blank" rel="noopener">Contacts</a></small>
            </div>
        </div>

        <div class="row align-items-stretch">
            <div class="col-md-8 form-group mb-md-0">
                <label class="pay-link-label">Remarks <span class="pay-label-optional">(optional)</span></label>
                <div class="pay-input-icon-wrap pay-input-icon-wrap-textarea">
                    <span class="pay-input-icon"><i class="far fa-file-alt"></i></span>
                    <textarea name="remarks" class="form-control pay-input-iconed" rows="4" maxlength="500"
                        placeholder="Booking ref, package name, etc."><?= htmlspecialchars((string) ($values['remarks'] ?? '')) ?></textarea>
                </div>
            </div>
            <div class="col-md-4 form-group mb-0">
                <div class="pay-amount-panel">
                    <label class="pay-link-label">Amount (₹) <span class="text-danger">*</span></label>
                    <div class="pay-input-icon-wrap">
                        <span class="pay-input-icon pay-input-icon-rupee">₹</span>
                        <input type="text" name="amount" class="form-control pay-input-iconed" required inputmode="decimal"
                            value="<?= htmlspecialchars((string) ($values['amount'] ?? '')) ?>" placeholder="e.g. 5000">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="pay-link-form-actions">
        <?php if ($formInModal) { ?>
            <button type="button" class="btn pay-btn-back" data-dismiss="modal">
                <i class="fas fa-arrow-left mr-1"></i> Back to list
            </button>
        <?php } else { ?>
            <a href="payment_links.php" class="btn pay-btn-back">
                <i class="fas fa-arrow-left mr-1"></i> Back to list
            </a>
        <?php } ?>
        <button type="submit" class="btn pay-btn-create js-pay-link-create-submit">
            <i class="fas fa-link mr-1"></i> Create Payment Link
        </button>
    </div>
</form>
