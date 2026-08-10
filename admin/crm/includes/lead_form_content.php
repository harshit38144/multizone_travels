<?php
/** @var bool $leadFormInModal */
/** @var bool $leadFormPublicIntake */
$leadFormPublicIntake = !empty($leadFormPublicIntake);
$leadFormInModal = !empty($leadFormInModal) || $leadFormPublicIntake;
$leadFormEmbedModal = $leadFormPublicIntake || (!empty($leadFormInModal) && !$leadFormPublicIntake);
$leadFormShowItinerary = !$leadFormPublicIntake && !$leadFormEmbedModal;
$idPfx = $leadFormPublicIntake ? 'leadIntake' : ($leadFormInModal ? 'leadModal' : 'lead');
$leadFormDomId = $leadFormPublicIntake ? 'leadIntakeForm' : ($leadFormInModal ? 'leadCreateFormModal' : 'leadCreateForm');
$leadFormIntakeEnabledFields = is_array($leadFormIntakeEnabledFields ?? null) ? $leadFormIntakeEnabledFields : [];
$leadFormIntakePrefill = is_array($leadFormIntakePrefill ?? null) ? $leadFormIntakePrefill : [];

if ($leadFormPublicIntake) {
    require_once __DIR__ . '/lead_intake_fields.php';
}

if (!function_exists('lfIntakeField')) {
    function lfIntakeField($key)
    {
        global $leadFormPublicIntake, $leadFormIntakeEnabledFields;
        if (empty($leadFormPublicIntake)) {
            return true;
        }
        return crmLeadIntakeFieldEnabled($leadFormIntakeEnabledFields, $key);
    }
}

if (!function_exists('lfIntakeService')) {
    function lfIntakeService($serviceKey)
    {
        global $leadFormPublicIntake, $leadFormIntakeEnabledFields;
        if (empty($leadFormPublicIntake)) {
            return true;
        }
        return crmLeadIntakeServiceHasEnabledFields($leadFormIntakeEnabledFields, $serviceKey);
    }
}

$lfIntakeAutoServices = $leadFormPublicIntake ? crmLeadIntakeAutoServiceValues($leadFormIntakeEnabledFields) : [];

if (!isset($conn)) {
    require_once __DIR__ . '/../../connection.php';
}

$leadDestinations = [];
$nextDestOrder = 1;
if (isset($conn) && $conn instanceof mysqli) {
    $hasTourTypeCol = false;
    $checkTourTypeCol = $conn->query("SHOW COLUMNS FROM `destinations` LIKE 'tour_type'");
    if ($checkTourTypeCol && $checkTourTypeCol->num_rows > 0) {
        $hasTourTypeCol = true;
    }

    $destSql = $hasTourTypeCol
        ? "SELECT id, name, tour_type FROM destinations WHERE is_active = 1 ORDER BY display_order ASC, name ASC"
        : "SELECT id, name FROM destinations WHERE is_active = 1 ORDER BY display_order ASC, name ASC";
    $destResult = $conn->query($destSql);
    if ($destResult) {
        while ($destRow = $destResult->fetch_assoc()) {
            $leadDestinations[] = [
                'id' => (int) $destRow['id'],
                'name' => $destRow['name'],
                'tour_type' => $hasTourTypeCol ? (string) ($destRow['tour_type'] ?? '') : '',
            ];
        }
    }

    $orderResult = $conn->query("SELECT COALESCE(MAX(display_order), 0) + 1 AS next_order FROM destinations");
    if ($orderResult) {
        $nextDestOrder = (int) $orderResult->fetch_assoc()['next_order'];
    }
}

$tpHotelCategories = $leadFormEmbedModal
    ? ['3 Star', '3 Star Delux', '4 Star', '4 Star Delux', '5 Star', '5 Star Delux']
    : ['1 Star', '2 Star', '3 Star', '4 Star', '5 Star'];
$tpVehicleTypes = ['Sedan', 'SUV', 'Tempo Traveller', 'Coach'];
$leadSourceOptions = [];
$assignToUsers = [];
$assignToSelfName = trim((string) ($_SESSION['name'] ?? ''));

if (isset($conn) && $conn instanceof mysqli) {
    $conn->query("CREATE TABLE IF NOT EXISTS `crm_lead_sources` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(120) NOT NULL,
        `display_order` INT NOT NULL DEFAULT 0,
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_crm_lead_source_name` (`name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $seedRes = $conn->query("SELECT COUNT(*) AS c FROM `crm_lead_sources`");
    $seedCount = $seedRes ? (int) ($seedRes->fetch_assoc()['c'] ?? 0) : 0;
    if ($seedCount === 0) {
        $conn->query("INSERT INTO `crm_lead_sources` (`name`, `display_order`, `is_active`) VALUES
            ('Referral', 1, 1),
            ('Social Media', 2, 1),
            ('Website', 3, 1)");
    }

    $leadSourceRes = $conn->query("SELECT `name` FROM `crm_lead_sources` WHERE `is_active` = 1 ORDER BY `display_order` ASC, `name` ASC");
    if ($leadSourceRes) {
        while ($row = $leadSourceRes->fetch_assoc()) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name !== '') {
                $leadSourceOptions[] = $name;
            }
        }
    }

    $usersTableRes = $conn->query("SHOW TABLES LIKE 'users'");
    if ($usersTableRes && $usersTableRes->num_rows > 0) {
        $usersRes = $conn->query("SELECT `username`, `full_name` FROM `users` WHERE `is_deleted` = 0 ORDER BY `full_name` ASC, `username` ASC");
        if ($usersRes) {
            while ($row = $usersRes->fetch_assoc()) {
                $username = trim((string) ($row['username'] ?? ''));
                $fullName = trim((string) ($row['full_name'] ?? ''));
                if ($username === '' && $fullName === '') {
                    continue;
                }
                $assignToUsers[] = [
                    'username' => $username,
                    'full_name' => $fullName,
                ];
            }
        }
    }
}

if (empty($leadSourceOptions)) {
    $leadSourceOptions = ['Referral', 'Social Media', 'Website'];
}
?>
<form id="<?= htmlspecialchars($leadFormDomId, ENT_QUOTES, 'UTF-8') ?>" action="#" method="post"
    class="crm-lead-create-form" onsubmit="return false;" autocomplete="off"
    data-lead-destinations="<?= htmlspecialchars(json_encode($leadDestinations), ENT_QUOTES, 'UTF-8') ?>"
    data-destination-save-url="<?= $leadFormPublicIntake ? '' : 'crm/ajax/save_destination.php' ?>"
    data-save-url="<?= $leadFormPublicIntake ? htmlspecialchars((string) ($leadFormIntakeSubmitUrl ?? 'ajax/submit_lead_intake.php'), ENT_QUOTES, 'UTF-8') : 'crm/ajax/save_lead.php' ?>"
    data-next-dest-order="<?= (int) ($nextDestOrder ?? 1) ?>"
    <?= !$leadFormPublicIntake ? 'data-contact-search-url="ajax/search_contacts_for_payment.php"' : '' ?>
    <?= $leadFormPublicIntake ? 'data-lead-intake="1" data-intake-token="' . htmlspecialchars((string) ($leadFormIntakeToken ?? ''), ENT_QUOTES, 'UTF-8') . '" data-intake-enabled-fields="' . htmlspecialchars(json_encode($leadFormIntakeEnabledFields), ENT_QUOTES, 'UTF-8') . '"' : '' ?>>

    <?php
    $intakePrefillName = trim((string) ($leadFormIntakePrefill['recipient_name'] ?? ''));
    $intakePrefillPhone = trim((string) ($leadFormIntakePrefill['recipient_phone'] ?? ''));
    $intakePrefillEmail = trim((string) ($leadFormIntakePrefill['recipient_email'] ?? ''));
    ?>

    <?php if ($leadFormPublicIntake) {
        foreach ($lfIntakeAutoServices as $autoSvc) { ?>
        <input type="hidden" name="services[]" value="<?= htmlspecialchars($autoSvc, ENT_QUOTES, 'UTF-8') ?>">
    <?php }
    } ?>

    <div class="row">
        <?php if (!$leadFormPublicIntake || crmLeadIntakeShowContactSection($leadFormIntakeEnabledFields)) { ?>
        <div class="<?= ($leadFormPublicIntake && !crmLeadIntakeShowServicesPicker($leadFormIntakeEnabledFields)) ? 'col-12' : 'col-lg-7' ?>">
            <div class="crm-card">
                <div class="crm-card-hd-blue">
                    <?php if ($leadFormPublicIntake) { ?>
                        <span class="intake-step-num">1</span>
                        <div>
                            <span class="crm-card-hd-title">Your Information</span>
                            <p class="crm-card-hd-sub">Help us get in touch with you</p>
                        </div>
                    <?php } else { ?>
                        <span class="crm-card-hd-title"><i class="fas fa-user"></i>Lead Information</span>
                    <?php } ?>
                </div>
                <div class="crm-card-bd">

                    <div class="form-row">
                        <?php if (lfIntakeField('customer_name')) { ?>
                        <div class="form-group col-4 col-md-2">
                            <label for="<?= $idPfx ?>CustomerInitial"><?= $leadFormPublicIntake ? 'Salutation' : 'Initial' ?></label>
                            <?php if ($leadFormPublicIntake) { ?>
                            <div class="lead-field-icon">
                                <i class="fas fa-user lead-field-icon-glyph" aria-hidden="true"></i>
                                <select class="form-control" id="<?= $idPfx ?>CustomerInitial" name="customer_initial">
                                    <?php
                                    $customerInitialOptions = ['Mr', 'Mrs', 'Ms', 'Mstr', 'Miss'];
                                    foreach ($customerInitialOptions as $initialOpt) { ?>
                                        <option value="<?= htmlspecialchars($initialOpt, ENT_QUOTES, 'UTF-8') ?>"<?= $initialOpt === 'Mr' ? ' selected' : '' ?>>
                                            <?= htmlspecialchars($initialOpt, ENT_QUOTES, 'UTF-8') ?><?= $initialOpt === 'Mr' ? '.' : '' ?>
                                        </option>
                                    <?php } ?>
                                </select>
                            </div>
                            <?php } else { ?>
                            <select class="form-control" id="<?= $idPfx ?>CustomerInitial" name="customer_initial">
                                <?php
                                $customerInitialOptions = ['Mr', 'Mrs', 'Ms', 'Mstr', 'Miss'];
                                foreach ($customerInitialOptions as $initialOpt) { ?>
                                    <option value="<?= htmlspecialchars($initialOpt, ENT_QUOTES, 'UTF-8') ?>"<?= $initialOpt === 'Mr' ? ' selected' : '' ?>>
                                        <?= htmlspecialchars($initialOpt, ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php } ?>
                            </select>
                            <?php } ?>
                        </div>
                        <div class="form-group col-8 col-md-5">
                            <label class="label-req"><?= $leadFormPublicIntake ? 'Full Name' : 'Name' ?></label>
                            <div class="lead-field-icon<?= $leadFormPublicIntake ? '' : ' has-end-icon' ?>">
                                <i class="fas fa-user lead-field-icon-glyph" aria-hidden="true"></i>
                                <?php if (!$leadFormPublicIntake) { ?>
                                <div class="lead-contact-combobox">
                                    <input type="text" class="form-control js-lead-contact-lookup js-no-browser-autofill" name="customer_name" placeholder="Enter customer name" required
                                        autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false"
                                        data-lpignore="true" data-1p-ignore data-bwignore data-form-type="other" readonly
                                        value="">
                                    <div class="lead-contact-menu js-lead-contact-menu" style="display:none;"></div>
                                </div>
                                <?php } else { ?>
                                <input type="text" class="form-control" name="customer_name" placeholder="Enter your full name" required
                                    value="<?= htmlspecialchars($intakePrefillName, ENT_QUOTES, 'UTF-8') ?>">
                                <?php } ?>
                            </div>
                        </div>
                        <?php } ?>

                        <?php if (lfIntakeField('customer_phone')) { ?>
                        <div class="form-group col-12 col-md-5">
                            <label class="label-req"><?= $leadFormPublicIntake ? 'Phone Number' : 'Phone' ?></label>
                            <?php if (!$leadFormPublicIntake) { ?>
                            <div class="lead-contact-combobox">
                                <input type="text" class="form-control js-lead-contact-lookup js-no-browser-autofill" name="customer_phone" placeholder="Enter phone number" required
                                    autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" inputmode="tel"
                                    data-lpignore="true" data-1p-ignore data-bwignore data-form-type="other" readonly
                                    value="">
                                <div class="lead-contact-menu js-lead-contact-menu" style="display:none;"></div>
                            </div>
                            <?php } else { ?>
                            <div class="lead-field-icon">
                                <i class="fas fa-phone lead-field-icon-glyph" aria-hidden="true"></i>
                                <input type="text" class="form-control" name="customer_phone" placeholder="Enter your phone number" required
                                    value="<?= htmlspecialchars($intakePrefillPhone, ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                            <?php } ?>
                        </div>
                        <?php } ?>

                    </div>

                    <div class="form-row">
                        <?php if (lfIntakeField('customer_email')) { ?>
                        <div class="form-group col-md-6">
                            <label<?= $leadFormPublicIntake ? ' class="label-req"' : '' ?>><?= $leadFormPublicIntake ? 'Email Address' : 'Email' ?></label>
                            <div class="lead-field-icon">
                                <i class="far fa-envelope lead-field-icon-glyph" aria-hidden="true"></i>
                                <?php if (!$leadFormPublicIntake) { ?>
                                <div class="lead-contact-combobox">
                                    <input type="text" class="form-control js-lead-contact-lookup js-no-browser-autofill" name="customer_email" placeholder="Enter email address"
                                        autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" inputmode="email"
                                        data-lpignore="true" data-1p-ignore data-bwignore data-form-type="other" readonly
                                        value="">
                                    <div class="lead-contact-menu js-lead-contact-menu" style="display:none;"></div>
                                </div>
                                <?php } else { ?>
                                <input type="email" class="form-control" name="customer_email" placeholder="Enter your email address" required
                                    value="<?= htmlspecialchars($intakePrefillEmail, ENT_QUOTES, 'UTF-8') ?>">
                                <?php } ?>
                            </div>
                        </div>
                        <?php } ?>
                        <?php if (!$leadFormPublicIntake) { ?>
                        <div class="form-group col-md-6">
                            <label class="label-req" for="<?= $idPfx ?>LeadSource">Lead Source</label>
                            <div class="lead-field-icon">
                                <i class="fas fa-bullhorn lead-field-icon-glyph" aria-hidden="true"></i>
                                <select class="form-control js-lead-source" id="<?= $idPfx ?>LeadSource" name="lead_source">
                                    <?php foreach ($leadSourceOptions as $idx => $leadSourceName) { ?>
                                        <option value="<?= htmlspecialchars($leadSourceName, ENT_QUOTES, 'UTF-8') ?>" <?= $idx === 0 ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($leadSourceName, ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                        <?php } ?>
                    </div>

                    <?php if (!$leadFormPublicIntake) { ?>
                    <div class="form-row">
                        <div class="form-group col-md-6 js-referred-by-wrap">
                            <label for="<?= $idPfx ?>ReferredBy">Referred By</label>
                            <div class="lead-field-icon">
                                <i class="fas fa-user lead-field-icon-glyph" aria-hidden="true"></i>
                                <input type="text" class="form-control" id="<?= $idPfx ?>ReferredBy" name="referred_by"
                                    placeholder="Enter referred by name">
                            </div>
                        </div>
                        <div class="form-group col-md-6">
                            <label class="label-req">Assign To</label>
                            <div class="lead-field-icon">
                                <i class="fas fa-user-check lead-field-icon-glyph" aria-hidden="true"></i>
                                <select class="form-control" name="assign_to" required>
                                    <option value="">Select User</option>
                                    <option value="__self__">To Self<?= $assignToSelfName !== '' ? ' (' . htmlspecialchars($assignToSelfName, ENT_QUOTES, 'UTF-8') . ')' : '' ?></option>
                                    <?php foreach ($assignToUsers as $assignee) {
                                        $username = (string) ($assignee['username'] ?? '');
                                        $fullName = (string) ($assignee['full_name'] ?? '');
                                        $value = $username !== '' ? $username : $fullName;
                                        $label = $fullName !== '' ? $fullName : $username;
                                        if ($value === '' || $label === '') {
                                            continue;
                                        }
                                        if ($fullName !== '' && $username !== '') {
                                            $label .= ' (' . $username . ')';
                                        }
                                    ?>
                                        <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <?php } ?>

                </div>
            </div>
        </div>
        <?php } ?>

        <?php if (!$leadFormPublicIntake || crmLeadIntakeShowServicesPicker($leadFormIntakeEnabledFields)) { ?>
        <div class="col-lg-5 js-intake-services-card">
            <div class="crm-card">
                <div class="crm-card-hd-green">
                    <span class="crm-card-hd-title"><i class="fas fa-suitcase"></i>Services Required *</span>
                    <p class="crm-card-hd-sub">Select at least one service required for this lead.</p>
                </div>
                <div class="crm-card-bd">
                    <div class="svc-check-row">
                        <?php
                        $lfServiceTiles = [];
                        if (!$leadFormPublicIntake || crmLeadIntakeShowServiceCheckbox($leadFormIntakeEnabledFields, 'tour_package')) {
                            $lfServiceTiles[] = ['tour_package', 'SvcTourPackage', 'fas fa-suitcase-rolling', 'Tour Package'];
                        }
                        if (!$leadFormPublicIntake) {
                            $lfServiceTiles[] = ['flight', 'SvcFlight', 'fas fa-plane', 'Flight'];
                            $lfServiceTiles[] = ['hotel', 'SvcHotel', 'fas fa-hotel', 'Hotel'];
                            $lfServiceTiles[] = ['vehicle', 'SvcVehicle', 'fas fa-car', 'Vehicle (disposal)'];
                            $lfServiceTiles[] = ['sightseeing', 'SvcSight', 'fas fa-binoculars', 'Sightseeing'];
                        } elseif (crmLeadIntakeFieldEnabled($leadFormIntakeEnabledFields, 'vehicle_type')) {
                            $lfServiceTiles[] = ['vehicle', 'SvcVehicle', 'fas fa-car', 'Vehicle (disposal)'];
                        }
                        if (!$leadFormPublicIntake || crmLeadIntakeShowServiceCheckbox($leadFormIntakeEnabledFields, 'cruise')) {
                            $lfServiceTiles[] = ['cruise', 'SvcCruise', 'fas fa-ship', 'Cruise'];
                        }
                        if (!$leadFormPublicIntake || crmLeadIntakeShowServiceCheckbox($leadFormIntakeEnabledFields, 'visa')) {
                            $lfServiceTiles[] = ['visa', 'SvcVisa', 'fas fa-stamp', 'Visa'];
                        }
                        if (!$leadFormPublicIntake || crmLeadIntakeShowServiceCheckbox($leadFormIntakeEnabledFields, 'passport')) {
                            $lfServiceTiles[] = ['passport', 'SvcPassport', 'fas fa-passport', 'Passport'];
                        }
                        if (!$leadFormPublicIntake || crmLeadIntakeShowServiceCheckbox($leadFormIntakeEnabledFields, 'forex')) {
                            $lfServiceTiles[] = ['forex', 'SvcForex', 'fas fa-exchange-alt', 'Forex'];
                        }
                        foreach ($lfServiceTiles as $tile) {
                            [$svcVal, $svcIdSuffix, $svcIcon, $svcLabel] = $tile;
                            ?>
                            <div class="svc-tile">
                                <input type="checkbox" class="svc-tile-input js-service-checkbox"
                                    id="<?= $idPfx . $svcIdSuffix ?>" name="services[]" value="<?= htmlspecialchars($svcVal, ENT_QUOTES, 'UTF-8') ?>">
                                <label class="svc-tile-label" for="<?= $idPfx . $svcIdSuffix ?>">
                                    <i class="<?= htmlspecialchars($svcIcon, ENT_QUOTES, 'UTF-8') ?>"></i>
                                    <span class="svc-tile-text"><?= htmlspecialchars($svcLabel, ENT_QUOTES, 'UTF-8') ?></span>
                                    <span class="svc-tile-box" aria-hidden="true"></span>
                                </label>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
        <?php } ?>

        <?php if (!$leadFormPublicIntake || crmLeadIntakeShowTravelSection($leadFormIntakeEnabledFields)) { ?>
        <div class="col-lg-12">
            <div class="crm-card js-travel-details-card">
                <div class="crm-card-hd-teal">
                    <?php if ($leadFormPublicIntake) { ?>
                        <span class="intake-step-num">2</span>
                        <div>
                            <span class="crm-card-hd-title">Travel Details</span>
                            <p class="crm-card-hd-sub">Tell us about your travel preferences</p>
                        </div>
                    <?php } else { ?>
                        <span class="crm-card-hd-title"><i class="fas fa-plane"></i>Travel Details</span>
                    <?php } ?>
                </div>
                <div class="crm-card-bd">

                    <p class="travel-details-empty js-travel-details-empty text-muted mb-0">
                        <i class="fas fa-info-circle mr-1"></i> Select one or more services above to show relevant
                        travel fields.
                    </p>

                    <!-- Tour Package -->
                    <?php if (lfIntakeService('tour_package')) { ?>
                    <div class="svc-detail-panel js-svc-detail-panel" data-svc="tour_package" style="<?= $leadFormPublicIntake ? '' : 'display:none;' ?>">
                        <h6 class="svc-detail-hd"><i class="fas fa-<?= $leadFormPublicIntake ? 'suitcase' : 'building' ?>"></i> <?= $leadFormPublicIntake ? 'Tour Package Details' : 'Tour Package' ?></h6>
                        <?php if ($leadFormEmbedModal) { ?>
                        <?php
                        $tpCol = $leadFormPublicIntake ? 'col-12 col-md' : null;
                        ?>
                        <div class="form-row<?= $leadFormPublicIntake ? ' tp-pack-row' : '' ?>">
                            <?php if (lfIntakeField('tp_travel_date')) { ?>
                            <div class="form-group <?= $tpCol ?: 'col-md-2' ?>">
                                <label class="label-req"><?= $leadFormPublicIntake ? 'Preferred Travel Date' : 'Pref. Travel Date' ?></label>
                                <div class="input-group lead-date-input-group">
                                    <input type="text" class="form-control js-lead-date-input js-lead-travel-date" autocomplete="off" name="tp_travel_date" placeholder="DD-MM-YYYY" readonly>
                                    <div class="input-group-append">
                                        <button type="button" class="btn btn-outline-secondary js-lead-date-trigger" tabindex="-1" aria-label="Open calendar">
                                            <i class="far fa-calendar-alt"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <?php } ?>
                            <?php if (lfIntakeField('tp_tour_type')) { ?>
                            <div class="form-group <?= $tpCol ?: 'col-md-2' ?>">
                                <label class="label-req">Tour Type</label>
                                <select class="form-control js-tp-tour-type" name="tp_tour_type">
                                    <option value="">Select</option>
                                    <option value="domestic">Domestic</option>
                                    <option value="international">International</option>
                                </select>
                            </div>
                            <?php } ?>
                            <?php if (lfIntakeField('tp_destination')) { ?>
                            <div class="form-group <?= $tpCol ? $tpCol . ' tp-pack-col-wide' : 'col-md-3' ?>">
                                <label class="label-req">Destination</label>
                                <div class="tp-destination-combobox js-tp-destination-wrap">
                                    <div class="tp-destination-field js-tp-destination-field">
                                        <div class="tp-destination-tags js-tp-destination-tags"></div>
                                        <input type="text" class="tp-destination-search js-tp-destination-input"
                                            placeholder="Select Tour Type first" autocomplete="off" disabled>
                                    </div>
                                    <div class="js-tp-destination-values"></div>
                                    <div class="tp-destination-menu js-tp-destination-menu" style="display:none;"></div>
                                </div>
                            </div>
                            <?php } ?>
                            <?php if (lfIntakeField('tp_departure')) { ?>
                            <div class="form-group <?= $tpCol ?: 'col-md-3' ?>">
                                <label class="label-req">Departure City</label>
                                <input type="text" class="form-control" name="tp_departure" placeholder="e.g. Mumbai">
                            </div>
                            <?php } ?>
                            <div class="form-group <?= $tpCol ? $tpCol . ' tp-pack-col-narrow' : 'col-md-2' ?>">
                                <label>Nights</label>
                                <div class="tp-nights-stepper js-tp-nights-stepper" data-min="1" data-max="60">
                                    <div class="tp-rg-stepper">
                                        <button type="button" class="tp-rg-step-btn js-tp-nights-step" data-action="minus" aria-label="Decrease nights">-</button>
                                        <input type="number" class="tp-rg-step-input js-tp-total-nights js-itinerary-total-nights" name="itinerary_total_nights" min="1" max="60" value="1" inputmode="numeric" aria-label="Number of nights">
                                        <button type="button" class="tp-rg-step-btn js-tp-nights-step" data-action="plus" aria-label="Increase nights">+</button>
                                    </div>
                                </div>
                            </div>
                            <input type="hidden" name="itinerary_total_days" class="js-tp-total-days js-itinerary-total-days" value="">
                        </div>
                        <?php } else { ?>
                        <div class="form-row">
                            <?php if (lfIntakeField('tp_travel_date')) { ?>
                            <div class="form-group col-md-2">
                                <label class="label-req">Pref. Travel Date</label>
                                <div class="input-group lead-date-input-group">
                                    <input type="text" class="form-control js-lead-date-input js-lead-travel-date" autocomplete="off" name="tp_travel_date" placeholder="DD-MM-YYYY" readonly>
                                    <div class="input-group-append">
                                        <button type="button" class="btn btn-outline-secondary js-lead-date-trigger" tabindex="-1" aria-label="Open calendar">
                                            <i class="far fa-calendar-alt"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <?php } ?>
                            <?php if (lfIntakeField('tp_departure')) { ?>
                            <div class="form-group col-md-2">
                                <label class="label-req">Departure</label>
                                <input type="text" class="form-control" name="tp_departure" placeholder="e.g. Mumbai">
                            </div>
                            <?php } ?>
                            <?php if (lfIntakeField('tp_arrival')) { ?>
                            <div class="form-group col-md-2">
                                <label class="label-req">Arrival</label>
                                <input type="text" class="form-control" name="tp_arrival" placeholder="e.g. Goa">
                            </div>
                            <?php } ?>
                            <?php if (lfIntakeField('tp_tour_type')) { ?>
                            <div class="form-group col-md-2">
                                <label class="label-req">Tour Type</label>
                                <select class="form-control js-tp-tour-type" name="tp_tour_type">
                                    <option value="">Select</option>
                                    <option value="domestic">Domestic</option>
                                    <option value="international">International</option>
                                </select>
                            </div>
                            <?php } ?>
                            <?php if (lfIntakeField('tp_destination')) { ?>
                            <div class="form-group col-md-4">
                                <label class="label-req">Destination</label>
                                <div class="tp-destination-combobox js-tp-destination-wrap">
                                    <div class="tp-destination-field js-tp-destination-field">
                                        <div class="tp-destination-tags js-tp-destination-tags"></div>
                                        <input type="text" class="tp-destination-search js-tp-destination-input"
                                            placeholder="Select Tour Type first" autocomplete="off" disabled>
                                    </div>
                                    <div class="js-tp-destination-values"></div>
                                    <div class="tp-destination-menu js-tp-destination-menu" style="display:none;"></div>
                                </div>
                            </div>
                            <?php } ?>
                        </div>
                        <?php } ?>

                        <?php if (!$leadFormPublicIntake || crmLeadIntakeShowTourRgRow($leadFormIntakeEnabledFields) || lfIntakeField('vehicle_type')) { ?>
                        <div class="form-row js-tp-rg-wrap<?= $leadFormPublicIntake ? ' tp-pack-row' : '' ?>">
                            <?php if (!$leadFormEmbedModal && lfIntakeField('tp_budget')) { ?>
                            <div class="form-group <?= $leadFormPublicIntake ? 'col-12 col-md' : 'col-md-3' ?>">
                                <label>Approx. Budget (₹)</label>
                                <input type="number" class="form-control" name="tp_budget" placeholder="e.g. 50000"
                                    min="0">
                            </div>
                            <?php } ?>
                            <?php if (lfIntakeField('tp_hotel_category')) { ?>
                            <div class="form-group <?= $leadFormPublicIntake ? 'col-12 col-md' : 'col-md-3' ?> js-hotel-svc-fields" style="<?= $leadFormPublicIntake ? '' : 'display:none;' ?>">
                                <label><?= $leadFormPublicIntake ? 'Preferred Hotel Category' : 'Pref. Hotel Categories' ?></label>
                                <div class="tp-hotel-cat-picker js-tp-hotel-cat-wrap"
                                    data-hotel-categories="<?= htmlspecialchars(json_encode($tpHotelCategories), ENT_QUOTES, 'UTF-8') ?>">
                                    <div class="tp-hotel-cat-field js-tp-hotel-cat-field" tabindex="0">
                                        <div class="tp-hotel-cat-tags js-tp-hotel-cat-tags"></div>
                                        <span class="tp-hotel-cat-placeholder js-tp-hotel-cat-placeholder">Select star ratings</span>
                                    </div>
                                    <div class="js-tp-hotel-cat-values"></div>
                                    <div class="tp-hotel-cat-menu js-tp-hotel-cat-menu" style="display:none;"></div>
                                </div>
                            </div>
                            <?php } ?>
                            <?php if (lfIntakeField('tp_adults') || lfIntakeField('tp_children') || lfIntakeField('tp_children_ages')) { ?>
                            <div class="form-group <?= $leadFormPublicIntake ? 'col-12 col-md' : 'col-md-3' ?> js-tp-guests-field">
                                <label class="label-req"><?= $leadFormPublicIntake ? 'Number of Guests' : 'Guests' ?></label>
                                <div class="tp-rg-picker js-tp-rg-picker" data-picker="guests">
                                    <button type="button" class="form-control tp-rg-trigger js-tp-rg-trigger">
                                        <span class="js-tp-rg-summary">2 Adults</span>
                                        <i class="fas fa-chevron-down tp-rg-trigger-icon"></i>
                                    </button>
                                    <div class="tp-rg-panels-wrap js-tp-rg-panels-wrap" style="display:none;">
                                        <div class="tp-rg-panel js-tp-rg-panel">
                                            <div class="tp-rg-panel-hd">
                                                <button type="button" class="tp-rg-close js-tp-rg-close"
                                                    aria-label="Close">&times;</button>
                                                <strong>Select Guests</strong>
                                            </div>
                                            <div class="tp-rg-panel-bd">
                                                <div class="tp-rg-row" data-field="adults" data-min="1" data-max="20">
                                                    <div class="tp-rg-row-label">
                                                        <strong>Adults</strong>
                                                    </div>
                                                    <div class="tp-rg-stepper">
                                                        <button type="button" class="tp-rg-step-btn js-tp-rg-step"
                                                            data-action="minus" aria-label="Decrease adults">-</button>
                                                        <span class="tp-rg-step-val js-tp-rg-val">2</span>
                                                        <button type="button" class="tp-rg-step-btn js-tp-rg-step"
                                                            data-action="plus" aria-label="Increase adults">+</button>
                                                    </div>
                                                </div>
                                                <div class="tp-rg-row" data-field="children" data-min="0" data-max="10">
                                                    <div class="tp-rg-row-label">
                                                        <strong>Children</strong>
                                                        <small>0 - 17 Years Old</small>
                                                    </div>
                                                    <div class="tp-rg-stepper">
                                                        <button type="button" class="tp-rg-step-btn js-tp-rg-step"
                                                            data-action="minus" aria-label="Decrease children">-</button>
                                                        <span class="tp-rg-step-val js-tp-rg-val">0</span>
                                                        <button type="button" class="tp-rg-step-btn js-tp-rg-step"
                                                            data-action="plus" aria-label="Increase children">+</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="tp-rg-child-ages-popup js-tp-rg-child-ages" style="display:none;">
                                            <div class="tp-rg-child-ages-popup-hd">Age of Children</div>
                                            <div class="tp-rg-child-ages-popup-bd">
                                                <div class="js-tp-rg-child-age-list"></div>
                                                <p class="tp-rg-child-ages-note">Please provide right number of children
                                                    along with their right age for best options and prices.</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php } ?>
                            <?php if (lfIntakeField('tp_rooms')) { ?>
                            <div class="form-group <?= $leadFormPublicIntake ? 'col-12 col-md' : 'col-md-3' ?> js-hotel-svc-fields" style="<?= $leadFormPublicIntake ? '' : 'display:none;' ?>">
                                <label class="label-req">Rooms</label>
                                <?php if ($leadFormEmbedModal) { ?>
                                <div class="tp-rooms-stepper js-tp-rooms-stepper" data-min="1" data-max="10">
                                    <div class="tp-rg-stepper">
                                        <button type="button" class="tp-rg-step-btn js-tp-rooms-step"
                                            data-action="minus" aria-label="Decrease rooms">-</button>
                                        <input type="number" class="tp-rg-step-input js-tp-rooms-input js-tp-rg-input-rooms"
                                            name="tp_rooms" min="1" max="10" value="1" inputmode="numeric" aria-label="Number of rooms">
                                        <button type="button" class="tp-rg-step-btn js-tp-rooms-step"
                                            data-action="plus" aria-label="Increase rooms">+</button>
                                    </div>
                                </div>
                                <small class="tp-rooms-suggest-note js-tp-rooms-suggest-note text-muted d-block"></small>
                                <?php } else { ?>
                                <div class="tp-rg-picker js-tp-rg-picker" data-picker="rooms">
                                    <button type="button" class="form-control tp-rg-trigger js-tp-rg-trigger">
                                        <span class="js-tp-rg-summary">1 Room</span>
                                        <i class="fas fa-chevron-down tp-rg-trigger-icon"></i>
                                    </button>
                                    <div class="tp-rg-panel js-tp-rg-panel" style="display:none;">
                                        <div class="tp-rg-panel-hd">
                                            <button type="button" class="tp-rg-close js-tp-rg-close"
                                                aria-label="Close">&times;</button>
                                            <strong>Select Rooms</strong>
                                        </div>
                                        <div class="tp-rg-panel-bd">
                                            <div class="tp-rg-row" data-field="rooms" data-min="1" data-max="10">
                                                <div class="tp-rg-row-label">
                                                    <strong>Rooms</strong>
                                                </div>
                                                <div class="tp-rg-stepper">
                                                    <button type="button" class="tp-rg-step-btn js-tp-rg-step"
                                                        data-action="minus" aria-label="Decrease rooms">-</button>
                                                    <span class="tp-rg-step-val js-tp-rg-val">1</span>
                                                    <button type="button" class="tp-rg-step-btn js-tp-rg-step"
                                                        data-action="plus" aria-label="Increase rooms">+</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php } ?>
                            </div>
                            <?php } ?>
                            <?php if ($leadFormEmbedModal && lfIntakeField('vehicle_type')) { ?>
                            <div class="form-group <?= $leadFormPublicIntake ? 'col-12 col-md' : 'col-md-3' ?> js-vehicle-svc-fields" style="<?= $leadFormPublicIntake ? '' : 'display:none;' ?>">
                                <label class="label-req">Vehicle Type</label>
                                <div class="tp-vehicle-type-picker js-tp-vehicle-type-wrap"
                                    data-vehicle-types="<?= htmlspecialchars(json_encode($tpVehicleTypes), ENT_QUOTES, 'UTF-8') ?>">
                                    <div class="tp-vehicle-type-field js-tp-vehicle-type-field" tabindex="0">
                                        <div class="tp-vehicle-type-tags js-tp-vehicle-type-tags"></div>
                                        <span class="tp-vehicle-type-placeholder js-tp-vehicle-type-placeholder">Select vehicle types</span>
                                    </div>
                                    <div class="js-tp-vehicle-type-values"></div>
                                    <div class="tp-vehicle-type-menu js-tp-vehicle-type-menu" style="display:none;"></div>
                                </div>
                            </div>
                            <?php } ?>
                            <?php if (lfIntakeField('tp_rooms') || lfIntakeField('tp_adults') || lfIntakeField('tp_children')) { ?>
                            <div class="js-tp-rg-hidden-inputs d-none col-12">
                                <?php if (lfIntakeField('tp_rooms') && !$leadFormEmbedModal) { ?><input type="hidden" name="tp_rooms" class="js-tp-rg-input-rooms" value="1"><?php } ?>
                                <?php if (lfIntakeField('tp_adults')) { ?><input type="hidden" name="tp_adults" class="js-tp-rg-input-adults" value="2"><?php } ?>
                                <?php if (lfIntakeField('tp_children') || lfIntakeField('tp_children_ages')) { ?><input type="hidden" name="tp_children" class="js-tp-rg-input-children" value="0"><?php } ?>
                                <input type="hidden" name="tp_pets" class="js-tp-rg-input-pets" value="0">
                                <?php if ($leadFormEmbedModal) { ?>
                                <input type="hidden" name="tp_child_cnb" class="js-tp-cnb-input" value="0">
                                <input type="hidden" name="tp_child_cwb" class="js-tp-cwb-input" value="0">
                                <?php } ?>
                            </div>
                            <?php } ?>
                        </div>
                        <?php if ($leadFormEmbedModal && lfIntakeField('tp_rooms')) { ?>
                        <div class="form-row js-tp-child-bed-field tp-child-bed-list js-tp-child-bed-list<?= $leadFormPublicIntake ? ' tp-pack-row' : '' ?>" style="display:none;"></div>
                        <?php } ?>
                        <?php } ?>
                        <?php if (lfIntakeField('vehicle_type') && !$leadFormEmbedModal) { ?>
                        <div class="form-row js-vehicle-svc-fields" style="<?= $leadFormPublicIntake ? '' : 'display:none;' ?>">
                            <div class="form-group col-md-3 mb-0">
                                <label class="label-req">Vehicle Type</label>
                                <div class="tp-vehicle-type-picker js-tp-vehicle-type-wrap"
                                    data-vehicle-types="<?= htmlspecialchars(json_encode($tpVehicleTypes), ENT_QUOTES, 'UTF-8') ?>">
                                    <div class="tp-vehicle-type-field js-tp-vehicle-type-field" tabindex="0">
                                        <div class="tp-vehicle-type-tags js-tp-vehicle-type-tags"></div>
                                        <span class="tp-vehicle-type-placeholder js-tp-vehicle-type-placeholder">Select vehicle types</span>
                                    </div>
                                    <div class="js-tp-vehicle-type-values"></div>
                                    <div class="tp-vehicle-type-menu js-tp-vehicle-type-menu" style="display:none;"></div>
                                </div>
                            </div>
                        </div>
                        <?php } ?>
                        <?php if (lfIntakeField('tp_notes')) { ?>
                        <div class="form-group<?= $leadFormEmbedModal ? '' : ' mb-0' ?><?= $leadFormPublicIntake ? ' tp-notes-wrap' : '' ?>">
                            <label>Package Notes</label>
                            <textarea class="form-control" name="tp_notes" rows="<?= $leadFormPublicIntake ? '4' : '2' ?>"<?= $leadFormPublicIntake ? ' maxlength="500"' : '' ?>
                                placeholder="<?= $leadFormPublicIntake ? 'Tell us about your meal preference, special requests, places you want to visit, etc.' : 'Meal plan, hotel category, special requests…' ?>"></textarea>
                            <?php if ($leadFormPublicIntake) { ?>
                                <span class="tp-notes-counter">0/500</span>
                            <?php } ?>
                        </div>
                        <?php } ?>
                        <?php if ($leadFormEmbedModal && lfIntakeField('tp_budget')) { ?>
                        <div class="form-group mb-0">
                            <label>Approx. Budget (₹)</label>
                            <?php if ($leadFormPublicIntake) { ?>
                            <div class="lead-field-icon">
                                <i class="fas fa-rupee-sign lead-field-icon-glyph" aria-hidden="true"></i>
                                <input type="number" class="form-control" name="tp_budget" placeholder="e.g. 50,000" min="0">
                            </div>
                            <?php } else { ?>
                            <input type="number" class="form-control" name="tp_budget" placeholder="e.g. 50000" min="0">
                            <?php } ?>
                        </div>
                        <?php } ?>
                    </div>
                    <?php } ?>

                    <!-- Cruise -->
                    <?php if (lfIntakeService('cruise')) { ?>
                    <div class="svc-detail-panel js-svc-detail-panel" data-svc="cruise" style="<?= $leadFormPublicIntake ? '' : 'display:none;' ?>">
                        <h6 class="svc-detail-hd"><i class="fas fa-ship"></i> Cruise</h6>
                        <div class="form-row">
                            <?php if (lfIntakeField('cruise_embark_date')) { ?>
                            <div class="form-group col-md-4">
                                <label class="label-req">Embarkation Date</label>
                                <input type="text" class="form-control js-lead-date-input" autocomplete="off" name="cruise_embark_date">
                            </div>
                            <?php } ?>
                            <?php if (lfIntakeField('cruise_line')) { ?>
                            <div class="form-group col-md-4">
                                <label class="label-req">Cruise Line / Route</label>
                                <input type="text" class="form-control" name="cruise_line"
                                    placeholder="e.g. Mumbai–Goa cruise">
                            </div>
                            <?php } ?>
                            <?php if (lfIntakeField('cruise_cabin')) { ?>
                            <div class="form-group col-md-4">
                                <label class="label-req">Cabin Type</label>
                                <select class="form-control" name="cruise_cabin">
                                    <option value="">Select</option>
                                    <option>Interior</option>
                                    <option>Ocean View</option>
                                    <option>Balcony</option>
                                    <option>Suite</option>
                                </select>
                            </div>
                            <?php } ?>
                        </div>
                        <div class="form-row">
                            <?php if (lfIntakeField('cruise_pax')) { ?>
                            <div class="form-group col-md-6 mb-0">
                                <label class="label-req">No. of Passengers</label>
                                <input type="number" class="form-control" name="cruise_pax" value="2" min="1">
                            </div>
                            <?php } ?>
                            <?php if (lfIntakeField('cruise_port')) { ?>
                            <div class="form-group col-md-6 mb-0">
                                <label>Port / Embarkation City</label>
                                <input type="text" class="form-control" name="cruise_port" placeholder="e.g. Mumbai">
                            </div>
                            <?php } ?>
                        </div>
                    </div>
                    <?php } ?>

                    <!-- Visa -->
                    <?php if (lfIntakeService('visa')) { ?>
                    <div class="svc-detail-panel js-svc-detail-panel" data-svc="visa" style="<?= $leadFormPublicIntake ? '' : 'display:none;' ?>">
                        <h6 class="svc-detail-hd"><i class="fas fa-stamp"></i> Visa</h6>
                        <div class="form-row">
                            <?php if (lfIntakeField('visa_country')) { ?>
                            <div class="form-group col-md-4">
                                <label class="label-req">Destination Country</label>
                                <input type="text" class="form-control" name="visa_country" placeholder="e.g. UAE">
                            </div>
                            <?php } ?>
                            <?php if (lfIntakeField('visa_type')) { ?>
                            <div class="form-group col-md-4">
                                <label class="label-req">Visa Type</label>
                                <select class="form-control" name="visa_type">
                                    <option>Tourist</option>
                                    <option>Business</option>
                                    <option>Transit</option>
                                    <option>Student</option>
                                </select>
                            </div>
                            <?php } ?>
                            <?php if (lfIntakeField('visa_travel_date')) { ?>
                            <div class="form-group col-md-4">
                                <label>Intended Travel Date</label>
                                <input type="text" class="form-control js-lead-date-input" autocomplete="off" name="visa_travel_date">
                            </div>
                            <?php } ?>
                        </div>
                        <div class="form-row">
                            <?php if (lfIntakeField('visa_passport_no')) { ?>
                            <div class="form-group col-md-6 mb-0">
                                <label>Passport No.</label>
                                <input type="text" class="form-control" name="visa_passport_no"
                                    placeholder="If available">
                            </div>
                            <?php } ?>
                            <?php if (lfIntakeField('visa_passport_exp')) { ?>
                            <div class="form-group col-md-6 mb-0">
                                <label>Passport Expiry</label>
                                <input type="text" class="form-control js-lead-date-input" autocomplete="off" name="visa_passport_exp">
                            </div>
                            <?php } ?>
                        </div>
                    </div>
                    <?php } ?>

                    <!-- Passport -->
                    <?php if (lfIntakeService('passport')) { ?>
                    <div class="svc-detail-panel js-svc-detail-panel" data-svc="passport" style="<?= $leadFormPublicIntake ? '' : 'display:none;' ?>">
                        <h6 class="svc-detail-hd"><i class="fas fa-passport"></i> Passport</h6>
                        <div class="form-row">
                            <?php if (lfIntakeField('passport_service')) { ?>
                            <div class="form-group col-md-4">
                                <label class="label-req">Service Type</label>
                                <select class="form-control" name="passport_service">
                                    <option>New Passport</option>
                                    <option>Renewal</option>
                                    <option>Re-issue</option>
                                    <option>Tatkal</option>
                                </select>
                            </div>
                            <?php } ?>
                            <?php if (lfIntakeField('passport_urgency')) { ?>
                            <div class="form-group col-md-4">
                                <label class="label-req">Urgency</label>
                                <select class="form-control" name="passport_urgency">
                                    <option>Normal</option>
                                    <option>Tatkal</option>
                                </select>
                            </div>
                            <?php } ?>
                            <?php if (lfIntakeField('passport_expiry')) { ?>
                            <div class="form-group col-md-4">
                                <label>Current Passport Expiry</label>
                                <input type="text" class="form-control js-lead-date-input" autocomplete="off" name="passport_expiry">
                            </div>
                            <?php } ?>
                        </div>
                        <?php if (lfIntakeField('passport_notes')) { ?>
                        <div class="form-group mb-0">
                            <label>Remarks</label>
                            <textarea class="form-control" name="passport_notes" rows="2"
                                placeholder="Lost passport, name change, etc."></textarea>
                        </div>
                        <?php } ?>
                    </div>
                    <?php } ?>

                    <!-- Forex -->
                    <?php if (lfIntakeService('forex')) { ?>
                    <div class="svc-detail-panel js-svc-detail-panel" data-svc="forex" style="<?= $leadFormPublicIntake ? '' : 'display:none;' ?>">
                        <h6 class="svc-detail-hd"><i class="fas fa-exchange-alt"></i> Forex</h6>
                        <div class="form-row">
                            <?php if (lfIntakeField('forex_currency')) { ?>
                            <div class="form-group col-md-4">
                                <label class="label-req">Currency</label>
                                <select class="form-control" name="forex_currency">
                                    <option value="">Select</option>
                                    <option>USD</option>
                                    <option>EUR</option>
                                    <option>GBP</option>
                                    <option>AED</option>
                                    <option>THB</option>
                                </select>
                            </div>
                            <?php } ?>
                            <?php if (lfIntakeField('forex_amount')) { ?>
                            <div class="form-group col-md-4">
                                <label class="label-req">Amount Required</label>
                                <input type="number" class="form-control" name="forex_amount"
                                    placeholder="Foreign currency amount" min="0" step="0.01">
                            </div>
                            <?php } ?>
                            <?php if (lfIntakeField('forex_date')) { ?>
                            <div class="form-group col-md-4">
                                <label>Travel / Delivery Date</label>
                                <input type="text" class="form-control js-lead-date-input" autocomplete="off" name="forex_date">
                            </div>
                            <?php } ?>
                        </div>
                        <div class="form-row">
                            <?php if (lfIntakeField('forex_product')) { ?>
                            <div class="form-group col-md-6 mb-0">
                                <label>Product Type</label>
                                <select class="form-control" name="forex_product">
                                    <option>Cash</option>
                                    <option>Forex Card</option>
                                    <option>Wire Transfer</option>
                                </select>
                            </div>
                            <?php } ?>
                            <?php if (lfIntakeField('forex_city')) { ?>
                            <div class="form-group col-md-6 mb-0">
                                <label>Pickup City</label>
                                <input type="text" class="form-control" name="forex_city" placeholder="e.g. Mumbai">
                            </div>
                            <?php } ?>
                        </div>
                    </div>
                    <?php } ?>

                </div>
            </div>
        </div>
        <?php } ?>
    </div>

    <?php if ($leadFormShowItinerary) { ?>
    <div class="crm-card js-itinerary-card" style="display:none;">
        <div class="crm-card-hd-blue itinerary-card-hd">
            <span class="crm-card-hd-title"><i class="fas fa-route"></i>Itinerary *</span>
            <button type="button" class="btn btn-sm btn-light js-itinerary-collapse-toggle" aria-expanded="false">
                <i class="fas fa-chevron-down"></i>
            </button>
        </div>
        <div class="crm-card-bd js-itinerary-collapse-body" style="display:none;">
            <input type="hidden" class="js-itinerary-total-days" name="itinerary_total_days" value="">

            <p class="js-itinerary-empty text-muted mb-0">
                <i class="fas fa-info-circle mr-1"></i> Select destinations in Tour Package (Travel Details) to build
                the itinerary.
            </p>

            <div class="js-itinerary-content" style="display:none;">
                <div class="itinerary-section mb-2">
                    <div class="itinerary-section-hd-row">
                        <h6 class="itinerary-section-hd mb-0">Selected Destinations</h6>
                        <div class="itinerary-total-nights-wrap">
                            <label class="mb-0">Total No. of Nights:</label>
                            <input type="number" class="form-control form-control-sm js-itinerary-total-nights" name="itinerary_total_nights" min="0" value="">
                        </div>
                    </div>
                    <div class="js-itinerary-dest-list"></div>
                </div>
                <div class="itinerary-section">
                    <h6 class="itinerary-section-hd">Day-wise Plan</h6>
                    <div class="js-itinerary-days-list"></div>
                </div>
            </div>
        </div>
    </div>
    <?php } ?>

    <div class="alert d-none js-lead-save-alert" role="alert"></div>
    <?php if ($leadFormPublicIntake) { ?>
    <div class="intake-tip-banner">
        <div class="intake-tip-banner-left">
            <i class="fas fa-lightbulb" aria-hidden="true"></i>
            <span>The more details you provide, the better we can customize your perfect trip!</span>
        </div>
        <span class="intake-tip-banner-art" aria-hidden="true">
            <i class="fas fa-suitcase-rolling"></i>
            <i class="fas fa-leaf"></i>
        </span>
    </div>
    <?php } ?>
    <div class="form-actions mb-0">
        <?php if ($leadFormPublicIntake) { ?>
            <div class="intake-safe-note">
                <span class="intake-safe-icon"><i class="fas fa-shield-alt"></i></span>
                <div>
                    <p class="intake-safe-title">Your information is safe with us.</p>
                    <p class="intake-safe-text">We never share your details with third parties.</p>
                </div>
            </div>
            <div class="intake-submit-wrap">
                <button type="submit" class="btn btn-primary px-4 font-weight-bold js-lead-submit-btn">Submit Inquiry <i class="fas fa-paper-plane"></i></button>
                <p class="intake-submit-soon">We'll get back to you shortly!</p>
            </div>
        <?php } else { ?>
            <?php if ($leadFormInModal) { ?>
                <button type="button" class="btn btn-cancel px-4 font-weight-bold btn-lead-form-cancel">Cancel</button>
            <?php } else { ?>
                <a href="crm/leads.php" class="btn btn-cancel px-4 font-weight-bold">Cancel</a>
            <?php } ?>
            <button type="submit" class="btn btn-primary px-4 font-weight-bold js-lead-submit-btn">Create Lead <i class="fas fa-arrow-right"></i></button>
        <?php } ?>
    </div>

</form>

<?php if (!$leadFormPublicIntake) { ?>
<div class="modal fade tp-dest-create-modal js-tp-dest-create-modal" id="<?= $idPfx ?>DestCreateModal" tabindex="-1"
    role="dialog" aria-labelledby="<?= $idPfx ?>DestCreateModalLabel" aria-hidden="true" data-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-xl" role="document">
        <div class="modal-content tp-dest-create-shell">
            <form class="js-tp-dest-create-form" enctype="multipart/form-data" onsubmit="return false;">
                <div class="modal-header tp-dest-create-hd">
                    <h5 class="modal-title mb-0" id="<?= $idPfx ?>DestCreateModalLabel">Create Destination</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body tp-dest-create-bd">
                    <div class="alert alert-danger d-none js-tp-dest-create-error mb-3"></div>
                    <div class="row">
                        <div class="col-lg-8">
                            <div class="form-group">
                                <label class="label-req">Tour Type</label>
                                <select class="form-control js-tp-dest-create-tour-type" name="tour_type" required>
                                    <option value="">Select Tour Type</option>
                                    <option value="domestic">Domestic</option>
                                    <option value="international">International</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="label-req">Destination Name</label>
                                <input type="text" class="form-control js-tp-dest-create-name" name="name"
                                    placeholder="Enter destination name" required>
                            </div>
                            <div class="form-group">
                                <label>Slug</label>
                                <input type="text" class="form-control js-tp-dest-create-slug" name="slug"
                                    placeholder="auto-generated-slug">
                                <small class="text-muted">Leave empty to auto-generate from name</small>
                            </div>
                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label>Country</label>
                                    <input type="text" class="form-control" name="country"
                                        placeholder="e.g., Indonesia">
                                </div>
                                <div class="form-group col-md-6">
                                    <label>Region</label>
                                    <input type="text" class="form-control" name="region"
                                        placeholder="e.g., Southeast Asia">
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Description</label>
                                <textarea class="form-control js-tp-dest-create-summernote" name="description" rows="4"
                                    placeholder="Enter destination description"></textarea>
                            </div>
                            <div class="form-group">
                                <label>Best Time to Visit</label>
                                <input type="text" class="form-control" name="best_time_to_visit"
                                    placeholder="e.g., November to February">
                            </div>
                            <div class="form-group mb-0">
                                <label>How to Reach</label>
                                <textarea class="form-control js-tp-dest-create-summernote" name="how_to_reach" rows="4"
                                    placeholder="Enter how to reach details"></textarea>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="tp-dest-create-side-card mb-3">
                                <div class="tp-dest-create-side-hd">Publish</div>
                                <div class="tp-dest-create-side-bd">
                                    <div class="form-group">
                                        <label>Display Order</label>
                                        <input type="number" class="form-control js-tp-dest-create-order"
                                            name="display_order" min="1" value="<?= (int) ($nextDestOrder ?? 1) ?>">
                                    </div>
                                    <div class="custom-control custom-checkbox mb-0">
                                        <input type="checkbox" class="custom-control-input js-tp-dest-create-active"
                                            id="<?= $idPfx ?>DestCreateActive" name="is_active" value="1" checked>
                                        <label class="custom-control-label"
                                            for="<?= $idPfx ?>DestCreateActive">Active</label>
                                    </div>
                                </div>
                            </div>
                            <div class="tp-dest-create-side-card">
                                <div class="tp-dest-create-side-hd">Destination Image</div>
                                <div class="tp-dest-create-side-bd">
                                    <input type="file" class="form-control-file" name="image"
                                        accept="image/jpeg,image/png,image/webp,image/gif">
                                    <small class="text-muted d-block mt-2">Recommended size: 1200x800px</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer tp-dest-create-ft">
                    <button type="submit" class="btn btn-primary js-tp-dest-create-submit">
                        <i class="fas fa-save mr-1"></i> Save Destination
                    </button>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php } ?>

<script>
    (function () {
        var itineraryServices = ['tour_package', 'cruise'];
        var intakeServiceFields = {
            tour_package: ['tp_travel_date', 'tp_departure', 'tp_arrival', 'tp_tour_type', 'tp_destination', 'tp_budget', 'tp_hotel_category', 'tp_rooms', 'tp_adults', 'tp_children', 'tp_children_ages', 'tp_notes', 'vehicle_type'],
            cruise: ['cruise_embark_date', 'cruise_line', 'cruise_cabin', 'cruise_pax', 'cruise_port'],
            visa: ['visa_country', 'visa_type', 'visa_travel_date', 'visa_passport_no', 'visa_passport_exp'],
            passport: ['passport_service', 'passport_urgency', 'passport_expiry', 'passport_notes'],
            forex: ['forex_currency', 'forex_amount', 'forex_date', 'forex_product', 'forex_city']
        };

        window.initLeadCreateForm = function (formEl) {
            var $form = jQuery(formEl);
            if (!$form.length) {
                return;
            }

            function parseLeadDateToJsDate(value) {
                var v = jQuery.trim(String(value || ''));
                if (!v) {
                    return null;
                }
                var iso = v.match(/^(\d{4})-(\d{2})-(\d{2})$/);
                if (iso) {
                    return new Date(parseInt(iso[1], 10), parseInt(iso[2], 10) - 1, parseInt(iso[3], 10));
                }
                var dmy = v.match(/^(\d{1,2})-(\d{1,2})-(\d{4})$/);
                if (dmy) {
                    return new Date(parseInt(dmy[3], 10), parseInt(dmy[2], 10) - 1, parseInt(dmy[1], 10));
                }
                var parsed = Date.parse(v);
                if (!isNaN(parsed)) {
                    return new Date(parsed);
                }
                return null;
            }

            function initLeadDatePickers() {
                if (!jQuery.fn.datepicker) {
                    return;
                }
                $form.find('input.js-lead-date-input').each(function () {
                    var $input = jQuery(this);
                    var isTravelDate = $input.hasClass('js-lead-travel-date') || $input.attr('name') === 'tp_travel_date';
                    if ($input.hasClass('hasDatepicker')) {
                        try {
                            $input.datepicker('destroy');
                        } catch (e) {}
                    }
                    var opts = {
                        dateFormat: isTravelDate ? 'dd-mm-yy' : 'yy-mm-dd',
                        changeMonth: true,
                        changeYear: true,
                        closeText: 'Done',
                        currentText: 'Today',
                        showButtonPanel: true,
                        yearRange: isTravelDate ? 'c:c+10' : '-10:+10',
                        prevText: '',
                        nextText: '',
                        monthNames: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                        monthNamesShort: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                        beforeShow: function (input, inst) {
                            inst.dpDiv.addClass('crm-lead-datepicker');
                            inst.dpDiv.css({ zIndex: 2000 });
                        },
                        onClose: function () {
                            jQuery(this).blur();
                        }
                    };
                    if (isTravelDate) {
                        opts.minDate = 0;
                    }
                    $input.datepicker(opts);

                    var $trigger = $input.closest('.lead-date-input-group').find('.js-lead-date-trigger');
                    $trigger.off('click.leadDate').on('click.leadDate', function (e) {
                        e.preventDefault();
                        $input.datepicker('show');
                    });
                });
            }

            function syncLeadDatePickerValues() {
                $form.find('input.js-lead-date-input').each(function () {
                    var $input = jQuery(this);
                    var v = jQuery.trim($input.val());
                    if (!v || !$input.hasClass('hasDatepicker')) {
                        return;
                    }
                    try {
                        var dateObj = parseLeadDateToJsDate(v);
                        if (dateObj) {
                            $input.datepicker('setDate', dateObj);
                        } else {
                            $input.datepicker('setDate', v);
                        }
                    } catch (e) {}
                });
            }

            var leadContactLookupTimer = null;
            var leadContactLookupSeq = 0;
            var leadContactLookupCache = {};

            function hideLeadContactMenus() {
                jQuery('.js-lead-contact-menu').hide().empty();
            }

            function applyLeadContactSuggestion(contact) {
                if (!contact) {
                    return;
                }
                if (contact.name) {
                    $form.find('[name="customer_name"]').val(contact.name);
                }
                if (contact.mobile) {
                    $form.find('[name="customer_phone"]').val(contact.mobile);
                }
                if (contact.email) {
                    $form.find('[name="customer_email"]').val(contact.email);
                }
                if (contact.title) {
                    var $initial = $form.find('[name="customer_initial"]');
                    if ($initial.length && $initial.find('option[value="' + String(contact.title).replace(/\\/g, '\\\\').replace(/"/g, '\\"') + '"]').length) {
                        $initial.val(contact.title);
                    }
                }
            }

            function renderLeadContactMenu($menu, items, query) {
                $menu.empty();
                if (!items || !items.length) {
                    $menu.append(
                        jQuery('<div class="lead-contact-empty"></div>').text(
                            'No contacts found' + (query ? ' for "' + query + '"' : '')
                        )
                    );
                } else {
                    items.forEach(function (item) {
                        var $btn = jQuery('<button type="button" class="lead-contact-item"></button>');
                        $btn.append(jQuery('<span class="lead-contact-item-title"></span>').text(item.label || item.name || 'Contact'));
                        if (item.sub_label) {
                            $btn.append(jQuery('<span class="lead-contact-item-meta"></span>').text(item.sub_label));
                        }
                        $btn.on('mousedown', function (e) {
                            e.preventDefault();
                            hideLeadContactMenus();
                            applyLeadContactSuggestion(item);
                        });
                        $menu.append($btn);
                    });
                }
                $menu.show();
            }

            function searchLeadContacts(query, callback) {
                var q = jQuery.trim(query || '');
                if (q.length < 2) {
                    callback([]);
                    return;
                }
                if (leadContactLookupCache[q]) {
                    callback(leadContactLookupCache[q]);
                    return;
                }
                var seq = ++leadContactLookupSeq;
                var searchUrl = $form.attr('data-contact-search-url') || 'ajax/search_contacts_for_payment.php';
                jQuery.getJSON(searchUrl, { q: q, limit: 10 })
                    .done(function (res) {
                        if (seq !== leadContactLookupSeq) {
                            return;
                        }
                        var items = (res && res.success && jQuery.isArray(res.data)) ? res.data : [];
                        leadContactLookupCache[q] = items;
                        callback(items);
                    })
                    .fail(function () {
                        if (seq !== leadContactLookupSeq) {
                            return;
                        }
                        callback([]);
                    });
            }

            function runLeadContactLookup(input) {
                var $input = jQuery(input);
                var $combobox = $input.closest('.lead-contact-combobox');
                if (!$combobox.length) {
                    return;
                }
                var $menu = $combobox.find('.js-lead-contact-menu').first();
                if (!$menu.length) {
                    return;
                }
                var query = jQuery.trim($input.val());
                hideLeadContactMenus();
                if (query.length < 2) {
                    return;
                }
                clearTimeout(leadContactLookupTimer);
                leadContactLookupTimer = setTimeout(function () {
                    searchLeadContacts(query, function (items) {
                        renderLeadContactMenu($menu, items, query);
                    });
                }, 280);
            }

            function initLeadContactLookup() {
                if (isIntake) {
                    return;
                }
                $form.find('.js-lead-contact-lookup').each(function () {
                    this.setAttribute('readonly', 'readonly');
                    this.setAttribute('autocomplete', 'off');
                });
                $form.find('.js-lead-contact-lookup').off('.leadContactLookup')
                    .on('focus.leadContactLookup mousedown.leadContactLookup', function () {
                        this.removeAttribute('readonly');
                    })
                    .on('input.leadContactLookup', function () {
                        runLeadContactLookup(this);
                    })
                    .on('click.leadContactLookup', function () {
                        runLeadContactLookup(this);
                    })
                    .on('blur.leadContactLookup', function () {
                        setTimeout(hideLeadContactMenus, 180);
                    });
            }

            jQuery(document).off('mousedown.leadContactLookupGlobal').on('mousedown.leadContactLookupGlobal', function (e) {
                if (!jQuery(e.target).closest('.lead-contact-combobox').length) {
                    hideLeadContactMenus();
                }
            });

            var isIntake = $form.attr('data-lead-intake') === '1';
            var intakeToken = $form.attr('data-intake-token') || '';
            var leadPickerApi = {};
            var enabledFields = [];
            if (isIntake) {
                try {
                    enabledFields = JSON.parse($form.attr('data-intake-enabled-fields') || '[]');
                } catch (e) {
                    enabledFields = [];
                }
            }

            function intakeFieldEnabled(name) {
                if (!isIntake) {
                    return true;
                }
                var key = String(name || '').replace(/\[\]$/, '');
                if (key.indexOf('itinerary_') === 0) {
                    return enabledFields.indexOf('tp_destination') >= 0;
                }
                return enabledFields.indexOf(key) >= 0;
            }

            function applyIntakeFieldVisibility() {
                if (!isIntake) {
                    return;
                }
                $form.find('input[type="hidden"][name="services[]"]').each(function () {
                    var val = jQuery(this).val();
                    $form.find('input.js-service-checkbox[value="' + val + '"]').prop('checked', true);
                });
                if (enabledFields.indexOf('services') < 0) {
                    $form.find('.js-travel-details-empty').hide();
                }
            }

            var $empty = $form.find('.js-travel-details-empty');
            var $panels = $form.find('.js-svc-detail-panel');
            var $itinerary = $form.find('.js-itinerary-card');
            var $itineraryToggle = $form.find('.js-itinerary-collapse-toggle');
            var $itineraryCollapseBody = $form.find('.js-itinerary-collapse-body');
            var $tourType = $form.find('select.js-tp-tour-type');
            var $tpWrap = $form.find('.js-tp-destination-wrap');
            var $tpDestinationField = $form.find('.js-tp-destination-field');
            var $tpDestinationTags = $form.find('.js-tp-destination-tags');
            var $tpDestinationValues = $form.find('.js-tp-destination-values');
            var $tpDestinationInput = $form.find('.js-tp-destination-input');
            var $tpDestinationMenu = $form.find('.js-tp-destination-menu');
            var $destCreateModal = $form.next('.js-tp-dest-create-modal');
            var $destCreateForm = $destCreateModal.find('.js-tp-dest-create-form');
            var destinationSaveUrl = $form.attr('data-destination-save-url') || 'crm/ajax/save_destination.php';
            var leadSaveUrl = $form.attr('data-save-url') || (isIntake ? 'ajax/submit_lead_intake.php' : 'crm/ajax/save_lead.php');
            var tpDestinationOptions = [];
            var tpSelectedDestinations = [];
            var leadDestinations = [];

            try {
                leadDestinations = JSON.parse($form.attr('data-lead-destinations') || '[]');
            } catch (e) {
                leadDestinations = [];
            }

            function hideTpDestinationMenu() {
                $tpDestinationMenu.hide().empty();
            }

            function setTpDestinationEnabled(enabled, placeholder) {
                $tpDestinationInput.prop('disabled', !enabled).attr('placeholder', placeholder || 'Type to search destination');
                $tpDestinationField.toggleClass('is-disabled', !enabled);
                if (!enabled) {
                    hideTpDestinationMenu();
                    $tpDestinationInput.val('');
                    // Keep selected tags — clearing here made Edit Lead destinations look locked/uneditable.
                } else {
                    // Ensure remove buttons / search stay interactive after enable.
                    $tpDestinationField.find('button').prop('disabled', false);
                }
            }

            function isTpDestinationSelected(id) {
                return tpSelectedDestinations.some(function (dest) {
                    return String(dest.id) === String(id);
                });
            }

            function renderTpDestinationTags() {
                $tpDestinationTags.empty();

                tpSelectedDestinations.forEach(function (dest) {
                    var $tag = jQuery('<span class="tp-destination-tag"></span>');
                    $tag.append(jQuery('<span class="tp-destination-tag-text"></span>').text(dest.name));
                    $tag.append(
                        jQuery('<button type="button" class="tp-destination-tag-remove" aria-label="Remove destination">&times;</button>')
                            .attr('data-id', dest.id)
                    );
                    $tpDestinationTags.append($tag);
                });
            }

            function syncTpDestinationHiddenInputs() {
                $tpDestinationValues.empty();
                tpSelectedDestinations.forEach(function (dest) {
                    $tpDestinationValues.append(
                        jQuery('<input type="hidden" name="tp_destination[]">').val(dest.id)
                    );
                });
            }

            function escapeHtml(text) {
                return jQuery('<div>').text(text || '').html();
            }

            function initDestCreateSummernote() {
                if (!$destCreateForm.length) {
                    return;
                }

                function mountEditors() {
                    if (!jQuery.fn.summernote) {
                        return;
                    }
                    $destCreateForm.find('.js-tp-dest-create-summernote').each(function () {
                        var $ta = jQuery(this);
                        if ($ta.next('.note-editor').length) {
                            return;
                        }
                        $ta.summernote({
                            height: 120,
                            placeholder: 'Enter details...',
                            toolbar: [
                                ['font', ['bold', 'italic', 'underline', 'clear']],
                                ['para', ['ul', 'ol', 'paragraph']]
                            ]
                        });
                    });
                }

                if (jQuery.fn.summernote) {
                    mountEditors();
                    return;
                }

                jQuery.getScript('plugins/summernote/summernote-bs4.min.js').always(mountEditors);
            }

            function destroyDestCreateSummernote() {
                if (!jQuery.fn.summernote || !$destCreateForm.length) {
                    return;
                }
                $destCreateForm.find('.js-tp-dest-create-summernote').each(function () {
                    var $ta = jQuery(this);
                    if ($ta.next('.note-editor').length) {
                        $ta.summernote('destroy');
                    }
                });
            }

            function showDestCreateError(message) {
                var $error = $destCreateForm.find('.js-tp-dest-create-error');
                if (!message) {
                    $error.addClass('d-none').text('');
                    return;
                }
                $error.removeClass('d-none').text(message);
            }

            function resetDestCreateForm() {
                if (!$destCreateForm.length) {
                    return;
                }
                destroyDestCreateSummernote();
                $destCreateForm[0].reset();
                $destCreateForm.find('.js-tp-dest-create-active').prop('checked', true);
                $destCreateForm.find('.js-tp-dest-create-order').val($form.attr('data-next-dest-order') || '1');
                showDestCreateError('');
            }

            function openTpDestinationCreateModal(prefillName) {
                if (!$destCreateModal.length) {
                    return;
                }

                hideTpDestinationMenu();
                resetDestCreateForm();

                var tourType = $tourType.val() || '';
                $destCreateForm.find('.js-tp-dest-create-tour-type').val(tourType);
                $destCreateForm.find('.js-tp-dest-create-name').val(prefillName || '');

                if (!$destCreateModal.parent().is('body')) {
                    $destCreateModal.appendTo('body');
                }

                $destCreateModal.modal('show');
            }

            function registerLeadDestination(dest) {
                if (!dest || !dest.id) {
                    return;
                }

                var exists = leadDestinations.some(function (item) {
                    return String(item.id) === String(dest.id);
                });
                if (!exists) {
                    leadDestinations.push({
                        id: dest.id,
                        name: dest.name,
                        tour_type: dest.tour_type || ''
                    });
                    $form.attr('data-lead-destinations', JSON.stringify(leadDestinations));
                }

                syncTourPackageDestinations();
                addTpDestination(dest);
            }

            function saveTpDestinationCreateForm() {
                if (!$destCreateForm.length) {
                    return;
                }

                showDestCreateError('');
                var $submit = $destCreateForm.find('.js-tp-dest-create-submit');
                var formData = new FormData($destCreateForm[0]);

                if (!$destCreateForm.find('.js-tp-dest-create-active').is(':checked')) {
                    formData.set('is_active', '0');
                }

                $destCreateForm.find('.js-tp-dest-create-summernote').each(function () {
                    var $ta = jQuery(this);
                    var value = $ta.next('.note-editor').length ? $ta.summernote('code') : $ta.val();
                    formData.set($ta.attr('name'), value || '');
                });

                $submit.prop('disabled', true);

                jQuery.ajax({
                    url: destinationSaveUrl,
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    dataType: 'json'
                }).done(function (response) {
                    if (!response || !response.success) {
                        showDestCreateError((response && response.message) ? response.message : 'Could not save destination.');
                        return;
                    }

                    $destCreateModal.modal('hide');
                    registerLeadDestination(response.destination);

                    var nextOrder = parseInt($form.attr('data-next-dest-order') || '1', 10) + 1;
                    $form.attr('data-next-dest-order', String(nextOrder));
                    $destCreateForm.find('.js-tp-dest-create-order').val(String(nextOrder));
                }).fail(function (xhr) {
                    var message = 'Could not save destination.';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        message = xhr.responseJSON.message;
                    }
                    showDestCreateError(message);
                }).always(function () {
                    $submit.prop('disabled', false);
                });
            }

            function renderTpDestinationMenu(filterText) {
                var query = (filterText || '').trim();
                var filtered = tpDestinationOptions.filter(function (dest) {
                    if (isTpDestinationSelected(dest.id)) {
                        return false;
                    }
                    return !query || dest.name.toLowerCase().indexOf(query.toLowerCase()) >= 0;
                });

                $tpDestinationMenu.empty();

                if (filtered.length === 0) {
                    if (query) {
                        $tpDestinationMenu.append(
                            '<div class="tp-destination-empty">No destinations found for "' + escapeHtml(query) + '"</div>'
                        );
                    } else {
                        $tpDestinationMenu.append('<div class="tp-destination-empty">No destinations found</div>');
                    }
                } else {
                    filtered.forEach(function (dest) {
                        $tpDestinationMenu.append(
                            jQuery('<button type="button" class="tp-destination-item"></button>')
                                .attr('data-id', dest.id)
                                .text(dest.name)
                        );
                    });
                }

                if ($tourType.val() && !isIntake && destinationSaveUrl) {
                    $tpDestinationMenu.append('<div class="tp-destination-divider"></div>');
                    $tpDestinationMenu.append(
                        jQuery('<button type="button" class="tp-destination-item tp-destination-item-create"></button>')
                            .attr('data-name', query)
                            .html('<i class="fas fa-plus-circle mr-2 text-primary"></i>Create new destination' +
                                (query ? ' "' + escapeHtml(query) + '"' : ''))
                    );
                }

                $tpDestinationMenu.show();
            }

            function addTpDestination(dest) {
                if (!dest || isTpDestinationSelected(dest.id)) {
                    return;
                }

                tpSelectedDestinations.push({ id: dest.id, name: dest.name });
                renderTpDestinationTags();
                syncTpDestinationHiddenInputs();
                syncItinerary(true);
                $tpDestinationInput.val('');
                renderTpDestinationMenu('');
                $tpDestinationInput.focus();
            }

            function removeTpDestination(id) {
                tpSelectedDestinations = tpSelectedDestinations.filter(function (dest) {
                    return String(dest.id) !== String(id);
                });
                renderTpDestinationTags();
                syncTpDestinationHiddenInputs();
                syncItinerary(true);
                renderTpDestinationMenu($tpDestinationInput.val());
            }

            function getSelectedServices() {
                var selected = [];
                $form.find('input.js-service-checkbox:checked').each(function () {
                    selected.push(jQuery(this).val());
                });
                if (isIntake) {
                    $form.find('input[type="hidden"][name="services[]"]').each(function () {
                        var val = jQuery(this).val();
                        if (val && selected.indexOf(val) < 0) {
                            selected.push(val);
                        }
                    });
                }
                return selected;
            }

            function syncTourPackageDestinations() {
                if (!$tpWrap.length) {
                    return;
                }

                var tourType = $tourType.length ? ($tourType.val() || '') : '';
                var previousSelected = tpSelectedDestinations.slice();

                hideTpDestinationMenu();
                tpDestinationOptions = [];

                var $tourPanel = $form.find('.js-svc-detail-panel[data-svc="tour_package"]');
                var selectedServices = getSelectedServices();
                var tourPackageActive = selectedServices.indexOf('tour_package') >= 0;
                var panelActive = isIntake
                    ? ($tourPanel.length > 0 && tourPackageActive)
                    : (!$tourPanel.length || $tourPanel.is(':visible'));

                if (!panelActive) {
                    setTpDestinationEnabled(false, 'Type to search destination');
                    syncItinerary(true);
                    return;
                }

                if ($tourType.length && !tourType) {
                    setTpDestinationEnabled(false, 'Select Tour Type first');
                    syncItinerary(true);
                    return;
                }

                if ($tourType.length && tourType) {
                    tpDestinationOptions = leadDestinations.filter(function (dest) {
                        return String(dest.tour_type || '') === String(tourType);
                    });
                    if (tpDestinationOptions.length === 0) {
                        tpDestinationOptions = leadDestinations.filter(function (dest) {
                            var tt = String(dest.tour_type || '').trim();
                            return tt === '' || tt === String(tourType);
                        });
                    }
                } else {
                    tpDestinationOptions = leadDestinations.slice();
                }

                setTpDestinationEnabled(true, tpDestinationOptions.length ? 'Type to search destination' : 'No destinations found');

                // Keep already-selected destinations editable even if tour-type filter would hide them.
                tpSelectedDestinations = previousSelected.filter(function (dest) {
                    var stillExists = leadDestinations.some(function (option) {
                        return String(option.id) === String(dest.id);
                    });
                    return stillExists;
                });
                tpSelectedDestinations.forEach(function (dest) {
                    if (!tpDestinationOptions.some(function (option) {
                        return String(option.id) === String(dest.id);
                    })) {
                        tpDestinationOptions.push(dest);
                    }
                });

                renderTpDestinationTags();
                syncTpDestinationHiddenInputs();
                syncItinerary(true);
            }

            var itineraryNightsManual = false;

            function getItineraryExpectedNights(totalDays) {
                var days = parseInt(totalDays, 10) || 0;
                if (days < 1) {
                    return 0;
                }
                return Math.max(days - 1, 1);
            }

            function buildItineraryDestOptions(selectedId) {
                var html = '<option value="">Select Destination</option>';
                tpSelectedDestinations.forEach(function (dest) {
                    html += '<option value="' + escapeHtml(String(dest.id)) + '"' +
                        (String(selectedId) === String(dest.id) ? ' selected' : '') + '>' +
                        escapeHtml(dest.name) + '</option>';
                });
                return html;
            }

            function captureItineraryState() {
                var nights = {};
                var dayNotes = {};
                var dayDestinations = {};

                $form.find('.js-itinerary-dest-nights').each(function () {
                    var destId = jQuery(this).closest('.itinerary-dest-row').data('dest-id');
                    nights[destId] = jQuery(this).val();
                });

                $form.find('.itinerary-day-row').each(function () {
                    var dayNum = jQuery(this).data('day');
                    dayDestinations[dayNum] = jQuery(this).find('.js-itinerary-day-dest').val() || '';
                    dayNotes[dayNum] = jQuery(this).find('.js-itinerary-day-notes').val() || '';
                });

                return {
                    nights: nights,
                    dayNotes: dayNotes,
                    dayDestinations: dayDestinations,
                    totalDays: $form.find('.js-itinerary-total-days, .js-tp-total-days').filter(function () {
                        return jQuery.trim(String(jQuery(this).val() || '')) !== '';
                    }).first().val() || '',
                    totalNights: ($form.find('.js-tp-total-nights').val()
                        || $form.find('.js-itinerary-total-nights').not('.js-tp-total-nights').val()
                        || '')
                };
            }

            function sanitizeDayDestOverrides(overrides, totalDays) {
                var result = {};
                var dayNum;

                for (dayNum = 1; dayNum <= totalDays; dayNum += 1) {
                    if (!overrides[dayNum]) {
                        continue;
                    }
                    var isValid = tpSelectedDestinations.some(function (dest) {
                        return String(dest.id) === String(overrides[dayNum]);
                    });
                    if (isValid) {
                        result[dayNum] = String(overrides[dayNum]);
                    }
                }

                return result;
            }

            function sumItineraryNights(nightsMap, destinations) {
                var total = 0;
                destinations.forEach(function (dest) {
                    total += parseInt(nightsMap[dest.id], 10) || 0;
                });
                return total;
            }

            function getAutoItineraryTotalDays(nightsMap) {
                if (!tpSelectedDestinations.length) {
                    return 0;
                }
                return Math.max(sumItineraryNights(nightsMap, tpSelectedDestinations) + 1, 1);
            }

            function distributeItineraryNights(totalNights, destinations, savedNights, forceAuto) {
                var expectedNights = Math.max(parseInt(totalNights, 10) || 0, 0);
                var result = {};
                var destIds = destinations.map(function (dest) {
                    return dest.id;
                });

                if (!destIds.length) {
                    return result;
                }

                if (!forceAuto && itineraryNightsManual) {
                    destIds.forEach(function (id) {
                        if (savedNights[id] !== undefined && savedNights[id] !== '') {
                            result[id] = parseInt(savedNights[id], 10) || 0;
                        }
                    });
                    if (Object.keys(result).length === destIds.length) {
                        return result;
                    }
                }

                if (expectedNights < 1) {
                    destIds.forEach(function (id, index) {
                        result[id] = index === 0 ? 1 : 0;
                    });
                    return result;
                }

                var base = Math.floor(expectedNights / destIds.length);
                var remainder = expectedNights % destIds.length;

                destIds.forEach(function (id, index) {
                    result[id] = base + (index < remainder ? 1 : 0);
                });

                return result;
            }

            function buildItineraryDayAssignments(totalDays, destinations, nightsMap) {
                var assignments = [];
                var day = 1;
                var lastDest = destinations.length ? destinations[destinations.length - 1] : null;

                destinations.forEach(function (dest) {
                    var nights = parseInt(nightsMap[dest.id], 10) || 0;
                    var nightIndex = 0;

                    while (nightIndex < nights && day <= totalDays) {
                        assignments.push({
                            day: day,
                            destId: dest.id,
                            destName: dest.name
                        });
                        day += 1;
                        nightIndex += 1;
                    }
                });

                while (day <= totalDays && lastDest) {
                    assignments.push({
                        day: day,
                        destId: lastDest.id,
                        destName: lastDest.name
                    });
                    day += 1;
                }

                return assignments;
            }

            function buildItineraryDayPlan(totalDays, nightsMap, dayDestOverrides) {
                var autoAssignments = buildItineraryDayAssignments(totalDays, tpSelectedDestinations, nightsMap);
                var autoByDay = {};
                var plan = [];
                var day;
                var destId;
                var dest;
                var valid;

                autoAssignments.forEach(function (item) {
                    autoByDay[item.day] = item;
                });

                for (day = 1; day <= totalDays; day += 1) {
                    destId = '';

                    if (dayDestOverrides && dayDestOverrides[day]) {
                        destId = String(dayDestOverrides[day]);
                    } else if (autoByDay[day]) {
                        destId = String(autoByDay[day].destId);
                    } else if (tpSelectedDestinations.length) {
                        destId = String(tpSelectedDestinations[tpSelectedDestinations.length - 1].id);
                    }

                    valid = tpSelectedDestinations.some(function (d) {
                        return String(d.id) === destId;
                    });

                    if (!valid && tpSelectedDestinations.length) {
                        destId = String(tpSelectedDestinations[0].id);
                    }

                    dest = tpSelectedDestinations.find(function (d) {
                        return String(d.id) === destId;
                    });

                    plan.push({
                        day: day,
                        destId: destId,
                        destName: dest ? dest.name : ''
                    });
                }

                return plan;
            }

            function getDestinationDayRangeFromPlan(destId, plan) {
                var days = plan.filter(function (item) {
                    return String(item.destId) === String(destId);
                }).map(function (item) {
                    return item.day;
                });

                if (!days.length) {
                    return ['—'];
                }

                if (days.length === 1) {
                    return [String(days[0])];
                }

                return [String(days[0]), String(days[days.length - 1])];
            }

            function updateItineraryDestDayPreviews(plan) {
                $form.find('.js-itinerary-dest-days-preview').each(function () {
                    var destId = jQuery(this).closest('.itinerary-dest-row').data('dest-id');
                    var range = getDestinationDayRangeFromPlan(destId, plan);
                    jQuery(this).text(range[0] === '—' ? '—' : 'Day ' + range.join('–'));
                });
            }

            function updateItinerarySummary(totalDays, nightsMap) {
                var expectedNights = getItineraryExpectedNights(totalDays);
                var allocatedNights = sumItineraryNights(nightsMap, tpSelectedDestinations);
                var $status = $form.find('.js-itinerary-summary-status');
                var diff = Math.abs(allocatedNights - expectedNights);

                $form.find('.js-itinerary-summary-days').text(totalDays > 0 ? String(totalDays) : '—');
                $form.find('.js-itinerary-summary-nights').text(String(allocatedNights));
                $form.find('.js-itinerary-summary-expected').text(expectedNights > 0 ? String(expectedNights) : '—');

                $status.removeClass('is-ok is-warn');
                if (totalDays < 1 || !tpSelectedDestinations.length) {
                    $status.text('Enter total days and select destinations to plan the itinerary.');
                    return;
                }

                if (diff === 0) {
                    $status.addClass('is-ok').text('Nights match the suggested total for this trip length.');
                } else if (diff === 1) {
                    $status.addClass('is-warn').text('Nights are close to suggested total (off by 1). You can adjust or use Auto-split.');
                } else {
                    $status.addClass('is-warn').text('Nights differ from suggested total. Use Auto-split nights or adjust manually.');
                }

                $form.find('.js-itinerary-dest-nights').toggleClass('is-warn', diff > 1);
            }

            function getDestinationDayRangePreview(destId, plan) {
                if (plan && plan.length) {
                    return getDestinationDayRangeFromPlan(destId, plan);
                }

                var totalDays = parseInt($form.find('.js-itinerary-total-days').val(), 10) || 0;
                var saved = captureItineraryState();
                var dayPlan = buildItineraryDayPlan(
                    totalDays,
                    saved.nights,
                    sanitizeDayDestOverrides(saved.dayDestinations, totalDays)
                );

                return getDestinationDayRangeFromPlan(destId, dayPlan);
            }

            function renderItineraryDestinationRows(nightsMap, dayPlan) {
                var $destList = $form.find('.js-itinerary-dest-list');

                $destList.empty();
                tpSelectedDestinations.forEach(function (dest) {
                    var nightsVal = nightsMap[dest.id] !== undefined ? nightsMap[dest.id] : 0;
                    var $row = jQuery(
                        '<div class="itinerary-dest-row itinerary-dest-row-compact">' +
                            '<span class="itinerary-dest-label"></span>' +
                            '<span class="itinerary-dest-sep">-</span>' +
                            '<input type="number" class="form-control form-control-sm js-itinerary-dest-nights itinerary-dest-night-input" min="0" value="0">' +
                            '<span class="itinerary-dest-night-suffix">N</span>' +
                            '<input type="hidden" name="itinerary_dest_id[]">' +
                        '</div>'
                    );

                    $row.attr('data-dest-id', dest.id);
                    $row.find('.itinerary-dest-label').text(dest.name);
                    $row.find('input[name="itinerary_dest_id[]"]').val(dest.id);
                    $row.find('.js-itinerary-dest-nights')
                        .attr('name', 'itinerary_dest_nights[' + dest.id + ']')
                        .val(nightsVal);

                    $destList.append($row);
                });
            }

            function renderItineraryDayRows(totalDays, nightsMap, dayNotes, dayDestOverrides) {
                var $daysList = $form.find('.js-itinerary-days-list');
                var $daysHint = $form.find('.js-itinerary-days-hint');
                var plan = buildItineraryDayPlan(totalDays, nightsMap, dayDestOverrides);

                $daysList.empty();

                if (totalDays < 1) {
                    $daysHint.show();
                    return;
                }

                $daysHint.hide();

                if (!plan.length) {
                    $daysList.append('<p class="text-muted small mb-0">Set nights for destinations to generate the day-wise plan.</p>');
                    return;
                }

                plan.forEach(function (item) {
                    var $dayRow = jQuery(
                        '<div class="form-row itinerary-day-row align-items-end">' +
                            '<div class="form-group col-md-2 col-lg-1 mb-md-0">' +
                                '<label class="font-weight-bold mb-1">Day</label>' +
                                '<div class="itinerary-day-badge"></div>' +
                            '</div>' +
                            '<div class="form-group col-md-4 col-lg-3 mb-md-0">' +
                                '<label class="font-weight-bold">Destination</label>' +
                                '<select class="form-control js-itinerary-day-dest"></select>' +
                            '</div>' +
                            '<div class="form-group col-md-6 col-lg-8 mb-0">' +
                                '<label>Notes</label>' +
                                '<input type="text" class="form-control js-itinerary-day-notes" placeholder="Optional activity or notes">' +
                            '</div>' +
                        '</div>'
                    );

                    $dayRow.attr('data-day', item.day);
                    $dayRow.find('.itinerary-day-badge').text('Day ' + item.day);
                    $dayRow.find('.js-itinerary-day-dest')
                        .attr('name', 'itinerary_days[' + item.day + '][destination_id]')
                        .html(buildItineraryDestOptions(item.destId));
                    $dayRow.find('.js-itinerary-day-notes')
                        .attr('name', 'itinerary_days[' + item.day + '][notes]')
                        .val(dayNotes[item.day] || '');

                    $daysList.append($dayRow);
                });

                updateItineraryDestDayPreviews(plan);
            }

            function syncItinerary(forceAutoNights) {
                var $empty = $form.find('.js-itinerary-empty');
                var $content = $form.find('.js-itinerary-content');

                if (!$empty.length) {
                    return;
                }

                var saved = captureItineraryState();
                var totalNights = Math.max(parseInt(saved.totalNights, 10) || 0, 0);
                var totalDays = totalNights > 0 ? totalNights + 1 : 0;
                var dayDestOverrides = sanitizeDayDestOverrides(saved.dayDestinations, totalDays);

                if (!tpSelectedDestinations.length) {
                    $empty.show();
                    $content.hide();
                    $form.find('.js-itinerary-dest-list').empty();
                    $form.find('.js-itinerary-days-list').empty();
                    updateItinerarySummary(0, {});
                    return;
                }

                $empty.hide();
                $content.show();

                if (forceAutoNights) {
                    itineraryNightsManual = false;
                }

                if (totalNights < 1) {
                    totalNights = tpSelectedDestinations.length;
                    totalDays = totalNights > 0 ? totalNights + 1 : 0;
                }

                var nightsMap = distributeItineraryNights(totalNights, tpSelectedDestinations, saved.nights, !!forceAutoNights);
                totalNights = sumItineraryNights(nightsMap, tpSelectedDestinations);
                totalDays = totalNights > 0 ? totalNights + 1 : 0;
                setItineraryTotalNightsFields(totalNights);
                $form.find('.js-itinerary-total-days').val(totalDays > 0 ? totalDays : '');
                dayDestOverrides = sanitizeDayDestOverrides(saved.dayDestinations, totalDays);
                var dayPlan = buildItineraryDayPlan(totalDays, nightsMap, dayDestOverrides);
                renderItineraryDestinationRows(nightsMap, dayPlan);
                renderItineraryDayRows(totalDays, nightsMap, saved.dayNotes, dayDestOverrides);
                updateItinerarySummary(totalDays, nightsMap);
            }

            function setItineraryTotalNightsFields(totalNights) {
                var nightsVal = Math.max(parseInt(totalNights, 10) || 0, 0);
                var $itinNights = $form.find('.js-itinerary-total-nights').not('.js-tp-total-nights');
                var $tpNights = $form.find('.js-tp-total-nights');
                if ($itinNights.length) {
                    $itinNights.val(nightsVal > 0 ? nightsVal : '');
                }
                if ($tpNights.length) {
                    // Tour Package stepper must never go blank in the UI.
                    var showNights = nightsVal > 0 ? nightsVal : 1;
                    $tpNights.val(showNights);
                    if (typeof syncTpTotalDaysFromNights === 'function') {
                        syncTpTotalDaysFromNights();
                    }
                    var $stepper = $form.find('.js-tp-nights-stepper');
                    var min = parseInt($stepper.data('min'), 10) || 1;
                    var max = parseInt($stepper.data('max'), 10) || 60;
                    $stepper.find('.js-tp-nights-step[data-action="minus"]').prop('disabled', showNights <= min);
                    $stepper.find('.js-tp-nights-step[data-action="plus"]').prop('disabled', showNights >= max);
                } else if (!$itinNights.length) {
                    $form.find('.js-itinerary-total-nights').val(nightsVal > 0 ? nightsVal : '');
                }
            }

            function syncItineraryFromNightsChange() {
                itineraryNightsManual = true;
                var saved = captureItineraryState();
                var nightsMap = saved.nights;

                tpSelectedDestinations.forEach(function (dest) {
                    if (nightsMap[dest.id] === undefined || nightsMap[dest.id] === '') {
                        nightsMap[dest.id] = 0;
                    }
                });

                var totalNights = sumItineraryNights(nightsMap, tpSelectedDestinations);
                var totalDays = totalNights > 0 ? totalNights + 1 : 0;
                var dayDestOverrides = sanitizeDayDestOverrides(saved.dayDestinations, totalDays);
                setItineraryTotalNightsFields(totalNights);
                $form.find('.js-itinerary-total-days').val(totalDays > 0 ? totalDays : '');
                var dayPlan = buildItineraryDayPlan(totalDays, nightsMap, dayDestOverrides);
                renderItineraryDayRows(totalDays, nightsMap, saved.dayNotes, dayDestOverrides);
                updateItineraryDestDayPreviews(dayPlan);
                updateItinerarySummary(totalDays, nightsMap);
            }

            function syncItineraryFromDayDestChange() {
                var saved = captureItineraryState();
                var totalDays = parseInt(saved.totalDays, 10) || 0;
                var dayDestOverrides = sanitizeDayDestOverrides(saved.dayDestinations, totalDays);
                var dayPlan = buildItineraryDayPlan(totalDays, saved.nights, dayDestOverrides);

                updateItineraryDestDayPreviews(dayPlan);
            }

            if ($tpWrap.length) {
                $tpDestinationField.off('click.leadDest mousedown.leadDest').on('click.leadDest mousedown.leadDest', function (e) {
                    if (jQuery(e.target).closest('.tp-destination-tag-remove').length) {
                        return;
                    }
                    if ($tpDestinationInput.prop('disabled')) {
                        return;
                    }
                    e.preventDefault();
                    $tpDestinationInput.prop('disabled', false);
                    $tpDestinationField.removeClass('is-disabled');
                    $tpDestinationInput.focus();
                    renderTpDestinationMenu($tpDestinationInput.val());
                });

                $tpDestinationInput.off('.leadDest').on('focus.leadDest click.leadDest', function () {
                    if ($tpDestinationInput.prop('disabled')) {
                        return;
                    }
                    renderTpDestinationMenu($tpDestinationInput.val());
                }).on('input.leadDest', function () {
                    if ($tpDestinationInput.prop('disabled')) {
                        return;
                    }
                    renderTpDestinationMenu($tpDestinationInput.val());
                }).on('blur.leadDest', function () {
                    window.setTimeout(function () {
                        hideTpDestinationMenu();
                        $tpDestinationInput.val('');
                    }, 150);
                });

                $tpDestinationTags.off('.leadDest').on('click.leadDest', '.tp-destination-tag-remove', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    removeTpDestination(jQuery(this).data('id'));
                });

                $tpDestinationMenu.off('.leadDest').on('mousedown.leadDest', '.tp-destination-item', function (e) {
                    e.preventDefault();
                    var id = jQuery(this).data('id');
                    var dest = tpDestinationOptions.find(function (item) {
                        return String(item.id) === String(id);
                    });
                    if (dest) {
                        addTpDestination(dest);
                    }
                }).on('mousedown.leadDest', '.tp-destination-item-create', function (e) {
                    e.preventDefault();
                    openTpDestinationCreateModal(jQuery(this).data('name') || $tpDestinationInput.val());
                });

                $form.off('click.leadDestOutside').on('click.leadDestOutside', function (e) {
                    if (!$tpWrap.is(e.target) && $tpWrap.has(e.target).length === 0) {
                        hideTpDestinationMenu();
                    }
                });

                if ($destCreateModal.length) {
                    $destCreateModal.off('.leadDestCreate').on('shown.bs.modal.leadDestCreate', function () {
                        initDestCreateSummernote();
                        $destCreateForm.find('.js-tp-dest-create-name').trigger('focus');
                    }).on('hidden.bs.modal.leadDestCreate', function () {
                        destroyDestCreateSummernote();
                        resetDestCreateForm();
                    });

                    $destCreateForm.off('.leadDestCreate').on('submit.leadDestCreate', function (e) {
                        e.preventDefault();
                        saveTpDestinationCreateForm();
                    });
                }
            }

            if ($tourType.length) {
                $tourType.off('change.leadTourTypeDest input.leadTourTypeDest')
                    .on('change.leadTourTypeDest input.leadTourTypeDest', syncTourPackageDestinations);
            }
            $form.off('change.leadTourTypeDestDelegated input.leadTourTypeDestDelegated', 'select.js-tp-tour-type')
                .on('change.leadTourTypeDestDelegated input.leadTourTypeDestDelegated', 'select.js-tp-tour-type', syncTourPackageDestinations);

            $form.find('.js-itinerary-total-nights').off('.leadItinerary').on('input.leadItinerary change.leadItinerary', function () {
                if ($form.find('.js-tp-total-nights').length) {
                    syncTpTotalDaysFromNights();
                    return;
                }
                syncItinerary(true);
            });

            function syncTpTotalDaysFromNights() {
                var $nights = $form.find('.js-tp-total-nights');
                var $days = $form.find('.js-tp-total-days');
                if (!$nights.length || !$days.length) {
                    return;
                }
                var nights = Math.max(parseInt($nights.val(), 10) || 0, 0);
                $days.val(nights > 0 ? nights + 1 : '');
            }

            (function initTpNightsStepper() {
                var $stepper = $form.find('.js-tp-nights-stepper');
                if (!$stepper.length) {
                    return;
                }

                var min = parseInt($stepper.data('min'), 10) || 1;
                var max = parseInt($stepper.data('max'), 10) || 60;
                var $input = $stepper.find('.js-tp-total-nights');
                var $minus = $stepper.find('.js-tp-nights-step[data-action="minus"]');
                var $plus = $stepper.find('.js-tp-nights-step[data-action="plus"]');

                function clampNights(value) {
                    return Math.max(min, Math.min(max, value));
                }

                function readNightsValue() {
                    var raw = jQuery.trim(String($input.val() || ''));
                    if (raw === '') {
                        return 0;
                    }
                    return parseInt(raw, 10) || 0;
                }

                function updateNightsButtons() {
                    var value = readNightsValue();
                    $minus.prop('disabled', value <= min);
                    $plus.prop('disabled', value >= max);
                }

                function applyNightsValue(value, allowEmpty) {
                    if (allowEmpty && (value === '' || value === null || typeof value === 'undefined')) {
                        $input.val('');
                    } else {
                        var numeric = parseInt(value, 10) || 0;
                        if (numeric <= 0) {
                            $input.val('');
                        } else {
                            $input.val(clampNights(numeric));
                        }
                    }
                    updateNightsButtons();
                    syncTpTotalDaysFromNights();
                }

                $stepper.off('click.tpNights').on('click.tpNights', '.js-tp-nights-step', function () {
                    var action = jQuery(this).data('action');
                    var current = readNightsValue();
                    if (action === 'minus') {
                        if (current <= min) {
                            return;
                        }
                        applyNightsValue(current - 1, false);
                        return;
                    }
                    applyNightsValue(current > 0 ? current + 1 : min, false);
                });

                $input.off('input.tpNights change.tpNights blur.tpNights')
                    .on('input.tpNights change.tpNights blur.tpNights', function () {
                        var raw = jQuery.trim(String(jQuery(this).val() || ''));
                        if (raw === '') {
                            applyNightsValue('', true);
                            return;
                        }
                        applyNightsValue(raw, false);
                    });

                if (readNightsValue() < min) {
                    applyNightsValue(min, false);
                } else {
                    updateNightsButtons();
                    syncTpTotalDaysFromNights();
                }
            })();

            $form.off('input.leadItineraryNights change.leadItineraryNights', '.js-itinerary-dest-nights')
                .on('input.leadItineraryNights change.leadItineraryNights', '.js-itinerary-dest-nights', function () {
                    syncItineraryFromNightsChange();
                });

            $form.off('change.leadItineraryDayDest', '.js-itinerary-day-dest')
                .on('change.leadItineraryDayDest', '.js-itinerary-day-dest', function () {
                    syncItineraryFromDayDestChange();
                });

            (function initTpRoomsGuestsPicker() {
                var $wrap = $form.find('.js-tp-rg-wrap');
                if (!$wrap.length) {
                    return;
                }

                var $hiddenInputs = $wrap.find('.js-tp-rg-hidden-inputs');
                var state = {
                    rooms: 1,
                    adults: 2,
                    children: 0,
                    pets: 0,
                    childAges: [],
                    childBedTypes: [],
                    childCnb: 0,
                    childCwb: 0,
                    petsEnabled: false
                };

                function clamp(value, min, max) {
                    return Math.max(min, Math.min(max, value));
                }

                function pluralize(word, count) {
                    return count === 1 ? word : word + 's';
                }

                function getRowState(field) {
                    if (field === 'rooms') return state.rooms;
                    if (field === 'adults') return state.adults;
                    if (field === 'children') return state.children;
                    if (field === 'pets') return state.pets;
                    return 0;
                }

                function setRowState(field, value) {
                    if (field === 'rooms') state.rooms = value;
                    if (field === 'adults') state.adults = value;
                    if (field === 'children') state.children = value;
                    if (field === 'pets') state.pets = value;
                }

                function buildAgeOptions(selected) {
                    var html = '';
                    for (var age = 0; age <= 17; age += 1) {
                        html += '<option value="' + age + '"' + (String(selected) === String(age) ? ' selected' : '') + '>' + age + ' yrs</option>';
                    }
                    return html;
                }

                function syncChildAgeFields($guestsPicker) {
                    var $childAgesWrap = $guestsPicker.find('.js-tp-rg-child-ages');
                    var $childAgeList = $guestsPicker.find('.js-tp-rg-child-age-list');
                    var $panelsWrap = $guestsPicker.find('.js-tp-rg-panels-wrap');

                    while (state.childAges.length < state.children) {
                        state.childAges.push(6);
                    }
                    state.childAges = state.childAges.slice(0, state.children);

                    if (state.children === 0) {
                        $childAgesWrap.hide();
                        $childAgeList.empty();
                        $panelsWrap.removeClass('has-child-ages');
                        return;
                    }

                    $childAgesWrap.show();
                    $panelsWrap.addClass('has-child-ages');
                    $childAgeList.empty();

                    state.childAges.forEach(function (age, index) {
                        var $row = jQuery('<div class="tp-rg-child-age-row"></div>');
                        $row.append('<label>Child ' + (index + 1) + '</label>');
                        var $select = jQuery('<select class="tp-rg-child-age-select js-tp-rg-child-age-select"></select>')
                            .attr('data-index', index)
                            .html(buildAgeOptions(age));
                        $row.append($select);
                        $childAgeList.append($row);
                    });
                }

                function suggestRoomsFromGuests() {
                    var guests = Math.max(0, (parseInt(state.adults, 10) || 0) + (parseInt(state.children, 10) || 0));
                    if (guests < 1) {
                        guests = 1;
                    }
                    var $inline = $wrap.find('.js-tp-rooms-stepper');
                    var max = $inline.length ? (parseInt($inline.data('max'), 10) || 10) : 10;
                    var min = $inline.length ? (parseInt($inline.data('min'), 10) || 1) : 1;
                    return clamp(Math.ceil(guests / 2), min, max);
                }

                function autoSuggestRoomsFromGuests() {
                    if (!$wrap.find('.js-tp-rooms-stepper').length) {
                        return;
                    }
                    state.rooms = suggestRoomsFromGuests();
                }

                function updateRoomsInline() {
                    var $inline = $wrap.find('.js-tp-rooms-stepper');
                    if (!$inline.length) {
                        return;
                    }
                    var min = parseInt($inline.data('min'), 10) || 1;
                    var max = parseInt($inline.data('max'), 10) || 10;
                    var value = state.rooms;
                    $inline.find('.js-tp-rooms-input').val(value);
                    $inline.find('.js-tp-rooms-step[data-action="minus"]').prop('disabled', value <= min);
                    $inline.find('.js-tp-rooms-step[data-action="plus"]').prop('disabled', value >= max);

                    var $note = $wrap.find('.js-tp-rooms-suggest-note');
                    if ($note.length) {
                        var guests = Math.max(0, (parseInt(state.adults, 10) || 0) + (parseInt(state.children, 10) || 0));
                        var suggested = suggestRoomsFromGuests();
                        $note.text(
                            guests + ' guest' + (guests === 1 ? '' : 's') +
                            ' · 2 per room · suggested ' + suggested + ' room' + (suggested === 1 ? '' : 's')
                        );
                    }
                }

                function bedTypesFromCounts(cnb, cwb) {
                    var types = [];
                    var cnbCount = Math.max(0, parseInt(cnb, 10) || 0);
                    var cwbCount = Math.max(0, parseInt(cwb, 10) || 0);
                    var i;
                    for (i = 0; i < cnbCount; i += 1) {
                        types.push('cnb');
                    }
                    for (i = 0; i < cwbCount; i += 1) {
                        types.push('cwb');
                    }
                    return types;
                }

                function syncChildBedCountsFromTypes() {
                    state.childCnb = 0;
                    state.childCwb = 0;
                    state.childBedTypes.forEach(function (type) {
                        if (type === 'cwb') {
                            state.childCwb += 1;
                        } else {
                            state.childCnb += 1;
                        }
                    });
                }

                function syncChildBedFields() {
                    var $block = $form.find('.js-tp-child-bed-field').first();
                    var $list = $block;
                    if (!$block.length) {
                        return;
                    }

                    while (state.childBedTypes.length < state.children) {
                        state.childBedTypes.push('cnb');
                    }
                    state.childBedTypes = state.childBedTypes.slice(0, state.children);

                    if (state.children === 0) {
                        $block.hide();
                        $list.empty();
                        state.childBedTypes = [];
                        state.childCnb = 0;
                        state.childCwb = 0;
                        $block.find('select').prop('disabled', true);
                        return;
                    }

                    $block.show();
                    $block.find('select').prop('disabled', false);
                    $list.empty();

                    state.childBedTypes.forEach(function (type, index) {
                        var bedType = type === 'cwb' ? 'cwb' : 'cnb';
                        var ageVal = state.childAges[index];
                        var ageLabel = (ageVal != null && ageVal !== '') ? String(ageVal) : '—';
                        var isPublicIntake = $form.closest('.crm-lead-intake-public').length > 0;
                        var $row = jQuery('<div class="form-group ' + (isPublicIntake ? 'col-12 col-md' : 'col-md-3 col-sm-6') + ' tp-child-bed-row mb-2"></div>');
                        $row.append('<label class="tp-child-bed-row-lbl">Child Bed Type (' + ageLabel + ')</label>');
                        var $select = jQuery('<select class="form-control js-tp-child-bed-select"></select>')
                            .attr('data-index', index)
                            .html(
                                '<option value="cnb" title="CNB - Child No Bed">CNB</option>' +
                                '<option value="cwb" title="CWB - Child With Bed">CWB</option>'
                            )
                            .val(bedType);
                        $row.append($select);
                        $list.append($row);
                    });

                    syncChildBedCountsFromTypes();
                }

                function updateRowDisplays($picker) {
                    $picker.find('.tp-rg-row[data-field]').each(function () {
                        var $row = jQuery(this);
                        var field = $row.data('field');
                        var value = getRowState(field);
                        var min = parseInt($row.data('min'), 10) || 0;
                        var max = parseInt($row.data('max'), 10) || 99;

                        $row.find('.js-tp-rg-val').text(value);
                        $row.find('.js-tp-rg-step[data-action="minus"]').prop('disabled', value <= min);
                        $row.find('.js-tp-rg-step[data-action="plus"]').prop('disabled', value >= max);
                    });
                }

                function updateSummaries() {
                    var $roomsPicker = $wrap.find('.js-tp-rg-picker[data-picker="rooms"]');
                    var $guestsPicker = $wrap.find('.js-tp-rg-picker[data-picker="guests"]');

                    if ($roomsPicker.length) {
                        $roomsPicker.find('.js-tp-rg-summary').text(
                            state.rooms + ' ' + pluralize('Room', state.rooms)
                        );
                    }
                    updateRoomsInline();

                    var guestParts = [state.adults + ' ' + pluralize('Adult', state.adults)];
                    if (state.children > 0) {
                        guestParts.push(state.children + ' ' + pluralize('Child', state.children));
                    }
                    if (state.petsEnabled && state.pets > 0) {
                        guestParts.push(state.pets + ' ' + pluralize('Pet', state.pets));
                    }
                    $guestsPicker.find('.js-tp-rg-summary').text(guestParts.join(', '));

                    $hiddenInputs.find('.js-tp-rg-input-rooms').val(state.rooms);
                    $hiddenInputs.find('.js-tp-rg-input-children').val(state.children);
                    $hiddenInputs.find('.js-tp-rg-input-pets').val(state.petsEnabled ? state.pets : 0);

                    $hiddenInputs.find('input[name="tp_children_ages[]"]').remove();
                    state.childAges.forEach(function (age) {
                        $hiddenInputs.append(
                            jQuery('<input type="hidden" name="tp_children_ages[]">').val(age)
                        );
                    });

                    $hiddenInputs.find('input[name="tp_child_bed_type[]"]').remove();
                    state.childBedTypes.forEach(function (type) {
                        $hiddenInputs.append(
                            jQuery('<input type="hidden" name="tp_child_bed_type[]">').val(type === 'cwb' ? 'cwb' : 'cnb')
                        );
                    });
                    $form.find('.js-tp-cnb-input').val(state.childCnb);
                    $form.find('.js-tp-cwb-input').val(state.childCwb);
                }

                function closeAllPanels() {
                    $wrap.find('.js-tp-rg-panels-wrap').hide();
                    $wrap.find('.js-tp-rg-panel').hide();
                    $wrap.find('.js-tp-rg-trigger').removeClass('is-open');
                }

                function refreshPicker() {
                    $wrap.find('.js-tp-rg-picker').each(function () {
                        updateRowDisplays(jQuery(this));
                    });
                    syncChildAgeFields($wrap.find('.js-tp-rg-picker[data-picker="guests"]'));
                    syncChildBedFields();
                    updateSummaries();
                }

                function changeCount(field, delta) {
                    var $row = $wrap.find('.tp-rg-row[data-field="' + field + '"]');
                    var $inline = field === 'rooms' ? $wrap.find('.js-tp-rooms-stepper') : jQuery();
                    var min;
                    var max;

                    if ($row.length) {
                        min = parseInt($row.data('min'), 10) || 0;
                        max = parseInt($row.data('max'), 10) || 99;
                    } else if ($inline.length) {
                        min = parseInt($inline.data('min'), 10) || 1;
                        max = parseInt($inline.data('max'), 10) || 10;
                    } else {
                        return;
                    }

                    var next = clamp(getRowState(field) + delta, min, max);
                    setRowState(field, next);

                    if (field === 'pets' && next === 0) {
                        state.petsEnabled = false;
                    }
                    if (field === 'adults' || field === 'children') {
                        autoSuggestRoomsFromGuests();
                    }

                    refreshPicker();
                }

                $wrap.find('.js-tp-rg-picker').each(function () {
                    var $picker = jQuery(this);
                    if ($picker.attr('data-picker') === 'rooms' && $wrap.find('.js-tp-rooms-stepper').length) {
                        return;
                    }
                    var $trigger = $picker.find('.js-tp-rg-trigger');
                    var $panelsWrap = $picker.find('.js-tp-rg-panels-wrap');
                    var $panel = $picker.find('.js-tp-rg-panel');
                    var $openTarget = $panelsWrap.length ? $panelsWrap : $panel;

                    $trigger.off('.tpRg').on('click.tpRg', function (e) {
                        e.preventDefault();
                        if ($trigger.prop('disabled')) {
                            return;
                        }
                        if ($openTarget.is(':visible')) {
                            closeAllPanels();
                        } else {
                            closeAllPanels();
                            $openTarget.show();
                            if ($panelsWrap.length) {
                                $panel.show();
                            }
                            $trigger.addClass('is-open');
                        }
                    });

                    $picker.find('.js-tp-rg-close').off('.tpRg').on('click.tpRg', function (e) {
                        e.preventDefault();
                        closeAllPanels();
                    });
                });

                $wrap.off('click.tpRg').on('click.tpRg', '.js-tp-rg-step, .js-tp-rooms-step', function (e) {
                    e.preventDefault();
                    var $row = jQuery(this).closest('.tp-rg-row[data-field]');
                    var field = $row.length ? $row.data('field') : '';
                    if (!field && jQuery(this).closest('.js-tp-rooms-stepper').length) {
                        field = 'rooms';
                    }
                    if (!field) {
                        return;
                    }
                    var action = jQuery(this).data('action');
                    changeCount(field, action === 'plus' ? 1 : -1);
                });

                $wrap.off('input.tpRooms change.tpRooms blur.tpRooms', '.js-tp-rooms-input')
                    .on('input.tpRooms change.tpRooms blur.tpRooms', '.js-tp-rooms-input', function () {
                        var $inline = $wrap.find('.js-tp-rooms-stepper');
                        if (!$inline.length) {
                            return;
                        }
                        var min = parseInt($inline.data('min'), 10) || 1;
                        var max = parseInt($inline.data('max'), 10) || 10;
                        var raw = jQuery.trim(String(jQuery(this).val() || ''));
                        var value = parseInt(raw, 10) || min;
                        state.rooms = clamp(value, min, max);
                        refreshPicker();
                    });

                $wrap.off('change.tpChildBed', '.js-tp-child-bed-select')
                    .on('change.tpChildBed', '.js-tp-child-bed-select', function () {
                        var index = parseInt(jQuery(this).data('index'), 10);
                        if (isNaN(index) || index < 0) {
                            return;
                        }
                        state.childBedTypes[index] = jQuery(this).val() === 'cwb' ? 'cwb' : 'cnb';
                        syncChildBedCountsFromTypes();
                        updateSummaries();
                    });

                $wrap.find('.js-tp-rg-picker[data-picker="guests"] .js-tp-rg-child-age-list')
                    .off('.tpRg')
                    .on('change.tpRg', '.js-tp-rg-child-age-select', function () {
                        var index = parseInt(jQuery(this).data('index'), 10);
                        state.childAges[index] = parseInt(jQuery(this).val(), 10) || 0;
                        syncChildBedFields();
                        updateSummaries();
                    });

                $form.off('click.tpRgOutside').on('click.tpRgOutside', function (e) {
                    if (!$wrap.is(e.target) && $wrap.has(e.target).length === 0) {
                        closeAllPanels();
                    }
                });

                leadPickerApi.setRoomsGuests = function (vals) {
                    vals = vals || {};
                    var roomsProvided = vals.rooms != null && vals.rooms !== '';
                    if (vals.rooms != null && vals.rooms !== '') state.rooms = Math.max(1, parseInt(vals.rooms, 10) || 1);
                    if (vals.adults != null && vals.adults !== '') state.adults = Math.max(1, parseInt(vals.adults, 10) || 1);
                    if (vals.children != null && vals.children !== '') state.children = Math.max(0, parseInt(vals.children, 10) || 0);
                    if (Array.isArray(vals.childAges)) {
                        state.childAges = vals.childAges.map(function (a) { return parseInt(a, 10) || 0; });
                    }
                    if (Array.isArray(vals.childBedTypes) && vals.childBedTypes.length) {
                        state.childBedTypes = vals.childBedTypes.map(function (t) {
                            return String(t).toLowerCase() === 'cwb' ? 'cwb' : 'cnb';
                        });
                    } else if (vals.childCnb != null || vals.childCwb != null) {
                        state.childBedTypes = bedTypesFromCounts(vals.childCnb, vals.childCwb);
                    }
                    if (!roomsProvided) {
                        autoSuggestRoomsFromGuests();
                    }
                    refreshPicker();
                };

                refreshPicker();
            })();

            (function initTpHotelCategoryPicker() {
                var $wrap = $form.find('.js-tp-hotel-cat-wrap');
                if (!$wrap.length) {
                    return;
                }

                var options = [];
                try {
                    options = JSON.parse($wrap.attr('data-hotel-categories') || '[]');
                } catch (e) {
                    options = ['1 Star', '2 Star', '3 Star', '4 Star', '5 Star'];
                }

                var selected = [];
                var $field = $wrap.find('.js-tp-hotel-cat-field');
                var $tags = $wrap.find('.js-tp-hotel-cat-tags');
                var $values = $wrap.find('.js-tp-hotel-cat-values');
                var $menu = $wrap.find('.js-tp-hotel-cat-menu');

                function isSelected(value) {
                    return selected.indexOf(value) >= 0;
                }

                function sortSelected() {
                    selected.sort(function (a, b) {
                        return options.indexOf(a) - options.indexOf(b);
                    });
                }

                function renderTags() {
                    $tags.empty();
                    selected.forEach(function (value) {
                        var $tag = jQuery('<span class="tp-hotel-cat-tag"></span>');
                        $tag.append(jQuery('<span></span>').html('<i class="fas fa-star mr-1"></i>' + escapeHtml(value)));
                        $tag.append(
                            jQuery('<button type="button" class="tp-hotel-cat-tag-remove" aria-label="Remove category">&times;</button>')
                                .attr('data-value', value)
                        );
                        $tags.append($tag);
                    });
                }

                function syncHiddenInputs() {
                    $values.empty();
                    selected.forEach(function (value) {
                        $values.append(
                            jQuery('<input type="hidden" name="tp_hotel_category[]">').val(value)
                        );
                    });
                }

                function renderMenu() {
                    $menu.empty();
                    var available = options.filter(function (value) {
                        return !isSelected(value);
                    });

                    if (!available.length) {
                        $menu.append('<div class="tp-hotel-cat-empty">All star ratings selected</div>');
                        return;
                    }

                    available.forEach(function (value) {
                        $menu.append(
                            jQuery('<button type="button" class="tp-hotel-cat-item"></button>')
                                .attr('data-value', value)
                                .html('<i class="fas fa-star text-warning mr-2"></i>' + escapeHtml(value))
                        );
                    });
                }

                function showMenu() {
                    renderMenu();
                    $menu.show();
                }

                function hideMenu() {
                    $menu.hide().empty();
                }

                function addCategory(value) {
                    if (!value || isSelected(value)) {
                        return;
                    }
                    selected.push(value);
                    sortSelected();
                    renderTags();
                    syncHiddenInputs();
                    renderMenu();
                }

                function removeCategory(value) {
                    selected = selected.filter(function (item) {
                        return item !== value;
                    });
                    renderTags();
                    syncHiddenInputs();
                    if ($menu.is(':visible')) {
                        renderMenu();
                    }
                }

                $field.off('.tpHotelCat').on('click.tpHotelCat', function (e) {
                    if (jQuery(e.target).closest('.tp-hotel-cat-tag-remove').length) {
                        return;
                    }
                    if (isIntake && !intakeFieldEnabled('tp_hotel_category')) {
                        return;
                    }
                    if (!isIntake && !$field.closest('.js-hotel-svc-fields').is(':visible')) {
                        return;
                    }
                    if ($menu.is(':visible')) {
                        hideMenu();
                    } else {
                        showMenu();
                    }
                });

                $tags.off('.tpHotelCat').on('click.tpHotelCat', '.tp-hotel-cat-tag-remove', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    removeCategory(jQuery(this).attr('data-value'));
                });

                $menu.off('.tpHotelCat').on('mousedown.tpHotelCat', '.tp-hotel-cat-item', function (e) {
                    e.preventDefault();
                    addCategory(jQuery(this).attr('data-value'));
                });

                $form.off('click.tpHotelCatOutside').on('click.tpHotelCatOutside', function (e) {
                    if (!$wrap.is(e.target) && $wrap.has(e.target).length === 0) {
                        hideMenu();
                    }
                });

                leadPickerApi.setHotelCategories = function (vals) {
                    selected = [];
                    (vals || []).forEach(function (v) {
                        v = String(v).trim();
                        if (v && options.indexOf(v) >= 0 && selected.indexOf(v) < 0) {
                            selected.push(v);
                        }
                    });
                    sortSelected();
                    renderTags();
                    syncHiddenInputs();
                };
            })();

            (function initTpVehicleTypePicker() {
                var $wrap = $form.find('.js-tp-vehicle-type-wrap');
                if (!$wrap.length) {
                    return;
                }

                var options = [];
                try {
                    options = JSON.parse($wrap.attr('data-vehicle-types') || '[]');
                } catch (e) {
                    options = ['Sedan', 'SUV', 'Tempo Traveller', 'Coach'];
                }

                var selected = [];
                var $field = $wrap.find('.js-tp-vehicle-type-field');
                var $tags = $wrap.find('.js-tp-vehicle-type-tags');
                var $values = $wrap.find('.js-tp-vehicle-type-values');
                var $menu = $wrap.find('.js-tp-vehicle-type-menu');

                function isSelected(value) {
                    return selected.indexOf(value) >= 0;
                }

                function sortSelected() {
                    selected.sort(function (a, b) {
                        return options.indexOf(a) - options.indexOf(b);
                    });
                }

                function renderTags() {
                    $tags.empty();
                    selected.forEach(function (value) {
                        var $tag = jQuery('<span class="tp-vehicle-type-tag"></span>');
                        $tag.append(jQuery('<span></span>').html('<i class="fas fa-car mr-1"></i>' + escapeHtml(value)));
                        $tag.append(
                            jQuery('<button type="button" class="tp-vehicle-type-tag-remove" aria-label="Remove vehicle type">&times;</button>')
                                .attr('data-value', value)
                        );
                        $tags.append($tag);
                    });
                }

                function syncHiddenInputs() {
                    $values.empty();
                    selected.forEach(function (value) {
                        $values.append(
                            jQuery('<input type="hidden" name="vehicle_type[]">').val(value)
                        );
                    });
                }

                function renderMenu() {
                    $menu.empty();
                    var available = options.filter(function (value) {
                        return !isSelected(value);
                    });

                    if (!available.length) {
                        $menu.append('<div class="tp-vehicle-type-empty">All vehicle types selected</div>');
                        return;
                    }

                    available.forEach(function (value) {
                        $menu.append(
                            jQuery('<button type="button" class="tp-vehicle-type-item"></button>')
                                .attr('data-value', value)
                                .html('<i class="fas fa-car text-secondary mr-2"></i>' + escapeHtml(value))
                        );
                    });
                }

                function showMenu() {
                    renderMenu();
                    $menu.show();
                }

                function hideMenu() {
                    $menu.hide().empty();
                }

                function addType(value) {
                    if (!value || isSelected(value)) {
                        return;
                    }
                    selected.push(value);
                    sortSelected();
                    renderTags();
                    syncHiddenInputs();
                    renderMenu();
                }

                function removeType(value) {
                    selected = selected.filter(function (item) {
                        return item !== value;
                    });
                    renderTags();
                    syncHiddenInputs();
                    if ($menu.is(':visible')) {
                        renderMenu();
                    }
                }

                $field.off('.tpVehicleType').on('click.tpVehicleType', function (e) {
                    if (jQuery(e.target).closest('.tp-vehicle-type-tag-remove').length) {
                        return;
                    }
                    if (!isIntake && !$field.closest('.js-vehicle-svc-fields').is(':visible')) {
                        return;
                    }
                    if (isIntake && !intakeFieldEnabled('vehicle_type')) {
                        return;
                    }
                    if ($menu.is(':visible')) {
                        hideMenu();
                    } else {
                        showMenu();
                    }
                });

                $tags.off('.tpVehicleType').on('click.tpVehicleType', '.tp-vehicle-type-tag-remove', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    removeType(jQuery(this).attr('data-value'));
                });

                $menu.off('.tpVehicleType').on('mousedown.tpVehicleType', '.tp-vehicle-type-item', function (e) {
                    e.preventDefault();
                    addType(jQuery(this).attr('data-value'));
                });

                $form.off('click.tpVehicleTypeOutside').on('click.tpVehicleTypeOutside', function (e) {
                    if (!$wrap.is(e.target) && $wrap.has(e.target).length === 0) {
                        hideMenu();
                    }
                });

                leadPickerApi.setVehicleTypes = function (vals) {
                    selected = [];
                    (vals || []).forEach(function (v) {
                        v = String(v).trim();
                        if (v && options.indexOf(v) >= 0 && selected.indexOf(v) < 0) {
                            selected.push(v);
                        }
                    });
                    sortSelected();
                    renderTags();
                    syncHiddenInputs();
                };
            })();

            function setPanelEnabled($panel, on) {
                if (isIntake) {
                    $panel.show();
                    $panel.find('input, select, textarea').prop('disabled', !on);
                    $panel.find('button').prop('disabled', !on);
                    if (on) {
                        enableIntakePickers();
                    }
                    return;
                }
                $panel.toggle(on);
                $panel.find('input, select, textarea, button').prop('disabled', !on);
            }

            function syncConditionalSvcFields(selected) {
                var hotelChecked = selected.indexOf('hotel') >= 0;
                var vehicleChecked = selected.indexOf('vehicle') >= 0;
                var $hotelFields = $form.find('.js-hotel-svc-fields');
                var $vehicleFields = $form.find('.js-vehicle-svc-fields');
                var $budgetField = $form.find('.js-tp-rg-wrap > .form-group').first();
                var $guestsField = $form.find('.js-tp-guests-field');

                if (isIntake) {
                    hotelChecked = hotelChecked
                        || intakeFieldEnabled('tp_hotel_category')
                        || intakeFieldEnabled('tp_rooms');
                    vehicleChecked = vehicleChecked || intakeFieldEnabled('vehicle_type');

                    $hotelFields.each(function () {
                        var $grp = jQuery(this);
                        var showHotelCat = $grp.find('.js-tp-hotel-cat-wrap').length > 0 && intakeFieldEnabled('tp_hotel_category');
                        var showRooms = $grp.find('.js-tp-rg-picker[data-picker="rooms"]').length > 0 && intakeFieldEnabled('tp_rooms');
                        var show = showHotelCat || showRooms;
                        $grp.toggle(show);
                        $grp.find('input, select, textarea, button').prop('disabled', !show);
                    });

                    $vehicleFields.toggle(vehicleChecked);
                    $vehicleFields.find('input, select, textarea, button').prop('disabled', !vehicleChecked);
                } else {
                    $hotelFields.toggle(hotelChecked);
                    $hotelFields.find('input, select, textarea, button').prop('disabled', !hotelChecked);

                    $vehicleFields.toggle(vehicleChecked);
                    $vehicleFields.find('input, select, textarea, button').prop('disabled', !vehicleChecked);

                    if (!hotelChecked) {
                        $form.find('.js-tp-child-bed-field').hide()
                            .find('input, select, textarea, button').prop('disabled', true);
                    }
                }

                if (hotelChecked) {
                    $budgetField.removeClass('col-md-6').addClass('col-md-3');
                    $guestsField.removeClass('col-md-6').addClass('col-md-3');
                } else {
                    $budgetField.removeClass('col-md-3').addClass('col-md-6');
                    $guestsField.removeClass('col-md-3').addClass('col-md-6');
                    $form.find('.js-tp-hotel-cat-menu').hide();
                    $form.find('.js-tp-rg-picker[data-picker="rooms"] .js-tp-rg-panel').hide();
                    $form.find('.js-tp-rg-picker[data-picker="rooms"] .js-tp-rg-trigger').removeClass('is-open');
                }

                if (!vehicleChecked) {
                    $form.find('.js-tp-vehicle-type-menu').hide();
                }

                if (isIntake) {
                    enableIntakePickers();
                }
            }

            function enableIntakePickers() {
                if (!isIntake) {
                    return;
                }

                if (intakeFieldEnabled('tp_hotel_category')) {
                    $form.find('.js-hotel-svc-fields').filter(function () {
                        return jQuery(this).find('.js-tp-hotel-cat-wrap').length > 0;
                    }).show().find('input, button').prop('disabled', false);
                }

                if (intakeFieldEnabled('tp_rooms')) {
                    $form.find('.js-tp-rg-picker[data-picker="rooms"] .js-tp-rg-trigger').prop('disabled', false);
                }

                if (intakeFieldEnabled('tp_adults') || intakeFieldEnabled('tp_children') || intakeFieldEnabled('tp_children_ages')) {
                    $form.find('.js-tp-rg-picker[data-picker="guests"] .js-tp-rg-trigger').prop('disabled', false);
                }

                if (intakeFieldEnabled('vehicle_type')) {
                    $form.find('.js-vehicle-svc-fields').show().find('input, button').prop('disabled', false);
                }

                if (intakeFieldEnabled('tp_budget')) {
                    $form.find('.js-tp-rg-wrap input[name="tp_budget"]').prop('disabled', false);
                }
            }

            function syncTravelDetailsByService() {
                var selected = getSelectedServices();

                if (selected.length === 0) {
                    $panels.each(function () {
                        setPanelEnabled(jQuery(this), false);
                    });
                    $empty.show();
                    $itinerary.hide().find('input, select, textarea, button').prop('disabled', true);
                    syncConditionalSvcFields(selected);
                    syncTourPackageDestinations();
                    return;
                }

                $empty.hide();
                $panels.each(function () {
                    var svc = jQuery(this).data('svc');
                    setPanelEnabled(jQuery(this), selected.indexOf(svc) >= 0);
                });

                var showItinerary = selected.some(function (s) {
                    return itineraryServices.indexOf(s) >= 0;
                });
                if (showItinerary) {
                    $itinerary.show().find('input, select, textarea, button').prop('disabled', false);
                    setItineraryCollapsed(true);
                    syncItinerary(true);
                } else {
                    $itinerary.hide().find('input, select, textarea, button').prop('disabled', true);
                }

                syncConditionalSvcFields(selected);
                syncTourPackageDestinations();
                if (isIntake) {
                    enableIntakePickers();
                }
            }

            $form.off('change.leadServices').on('change.leadServices', 'input.js-service-checkbox', function () {
                if (jQuery(this).val() === 'tour_package' && this.checked) {
                    $form.find('input.js-service-checkbox[value="flight"], input.js-service-checkbox[value="hotel"], input.js-service-checkbox[value="sightseeing"]').prop('checked', true);
                }
                syncTravelDetailsByService();
            });

            function setItineraryCollapsed(collapsed) {
                if (!$itineraryToggle.length || !$itineraryCollapseBody.length) {
                    return;
                }
                var $icon = $itineraryToggle.find('i');
                if (collapsed) {
                    $itineraryCollapseBody.stop(true, true).slideUp(140);
                    $itineraryToggle.attr('aria-expanded', 'false');
                    $icon.removeClass('fa-chevron-up').addClass('fa-chevron-down');
                } else {
                    $itineraryCollapseBody.stop(true, true).slideDown(140);
                    $itineraryToggle.attr('aria-expanded', 'true');
                    $icon.removeClass('fa-chevron-down').addClass('fa-chevron-up');
                }
            }

            $itineraryToggle.off('click.leadItineraryToggle').on('click.leadItineraryToggle', function (e) {
                e.preventDefault();
                var isExpanded = $itineraryToggle.attr('aria-expanded') === 'true';
                setItineraryCollapsed(isExpanded);
            });

            function showLeadSaveAlert(type, message) {
                var $alert = $form.find('.js-lead-save-alert');
                if (!$alert.length) {
                    return;
                }
                var cls = type === 'success' ? 'alert-success' : 'alert-danger';
                $alert.removeClass('d-none alert-success alert-danger').addClass(cls).text(message || '');
            }

            function resetLeadSaveAlert() {
                var $alert = $form.find('.js-lead-save-alert');
                if ($alert.length) {
                    $alert.addClass('d-none').removeClass('alert-success alert-danger').text('');
                }
            }

            function showIntakeSuccessAndClose(message, redirectUrl) {
                var successMsg = message || 'Your details were submitted successfully. Our team will contact you soon.';
                if (redirectUrl) {
                    window.location.href = redirectUrl;
                    return;
                }
                var $successModal = jQuery('#leadIntakeSuccessModal');
                if (!$successModal.length) {
                    showLeadSaveAlert('success', successMsg);
                    return;
                }
                $successModal.find('.js-intake-success-message').text(successMsg);
                $successModal.modal('show');
                window.setTimeout(function () {
                    $successModal.modal('hide');
                    window.open('', '_self', '');
                    window.close();
                    window.setTimeout(function () {
                        document.title = 'Thank you';
                        document.body.innerHTML = '<div style="text-align:center;padding:3rem 1rem;font-family:sans-serif;color:#1e3a5f;"><h2 style="margin-bottom:0.75rem;">Thank you!</h2><p style="margin:0;">You may close this tab.</p></div>';
                    }, 400);
                }, 3000);
            }

            $form.off('submit.leadSave').on('submit.leadSave', function (e) {
                e.preventDefault();
                resetLeadSaveAlert();

                var selectedServices = $form.find('input.js-service-checkbox:checked').length;
                if (!selectedServices) {
                    selectedServices = $form.find('input[type="hidden"][name="services[]"]').length;
                }
                if (isIntake) {
                    if (enabledFields.indexOf('services') >= 0 && !selectedServices) {
                        showLeadSaveAlert('error', 'Please select at least one service.');
                        return;
                    }
                } else if (!selectedServices) {
                    showLeadSaveAlert('error', 'Please select at least one service.');
                    return;
                }

                if (isIntake && intakeFieldEnabled('customer_name')) {
                    var cName = ($form.find('[name="customer_name"]').val() || '').toString().trim();
                    if (!cName) {
                        showLeadSaveAlert('error', 'Please enter your name.');
                        return;
                    }
                }
                if (isIntake && intakeFieldEnabled('customer_phone')) {
                    var cPhone = ($form.find('[name="customer_phone"]').val() || '').toString().trim();
                    if (!cPhone) {
                        showLeadSaveAlert('error', 'Please enter your phone number.');
                        return;
                    }
                }

                var $submitBtn = $form.find('.js-lead-submit-btn');
                $submitBtn.prop('disabled', true);

                if (typeof syncTpTotalDaysFromNights === 'function') {
                    syncTpTotalDaysFromNights();
                }

                var postData = $form.serialize();
                if (isIntake) {
                    postData += (postData ? '&' : '') + 'token=' + encodeURIComponent(intakeToken);
                }

                jQuery.ajax({
                    url: leadSaveUrl,
                    method: 'POST',
                    data: postData,
                    dataType: 'json'
                }).done(function (response) {
                    if (!response || !response.success) {
                        showLeadSaveAlert('error', (response && response.message) ? response.message : (isIntake ? 'Could not submit.' : 'Could not save lead.'));
                        return;
                    }

                    if (isIntake) {
                        $form.find('input, select, textarea, button').prop('disabled', true);
                        showIntakeSuccessAndClose(response.message, response.redirect || '');
                        return;
                    }

                    showLeadSaveAlert('success', response.message || 'Lead saved successfully.');

                    jQuery(document).trigger('crm:lead-created', [response]);

                    if ($form.is('#leadCreateFormModal')) {
                        window.setTimeout(function () {
                            jQuery('#leadFormModal').modal('hide');
                        }, 500);
                    }
                }).fail(function (xhr) {
                    var msg = isIntake ? 'Could not submit. Please try again.' : 'Could not save lead. Please try again.';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        msg = xhr.responseJSON.message;
                    }
                    showLeadSaveAlert('error', msg);
                }).always(function () {
                    if (!isIntake || !$form.find('input').first().prop('disabled')) {
                        $submitBtn.prop('disabled', false);
                    }
                });
            });

            function applyLeadPrefill(prefill) {
                if (!prefill || typeof prefill !== 'object') {
                    return;
                }

                function toArray(val) {
                    if (val == null) return [];
                    if (Array.isArray(val)) return val;
                    if (typeof val === 'string') {
                        return val.split(',').map(function (s) { return s.trim(); }).filter(function (s) { return s !== ''; });
                    }
                    return [val];
                }

                function setVal(name, val) {
                    if (val == null) return;
                    var $el = $form.find('[name="' + name + '"]');
                    $el = $el.filter(function () {
                        return !jQuery(this).hasClass('js-service-checkbox') && jQuery(this).attr('type') !== 'hidden';
                    });
                    if (!$el.length) return;
                    if ($el.is('select') && String(val) !== '') {
                        if ($el.find('option').filter(function () { return jQuery(this).val() === String(val); }).length === 0) {
                            $el.append(jQuery('<option></option>').val(String(val)).text(String(val)));
                        }
                    }
                    $el.val(val);
                }

                function prefillHasData(keys) {
                    return keys.some(function (k) {
                        var v = prefill[k];
                        if (v == null || v === '') return false;
                        if (Array.isArray(v)) return v.length > 0;
                        return true;
                    });
                }

                var services = toArray(prefill.services);
                // Infer services from the submitted data when none were stored explicitly.
                if (!services.length) {
                    if (prefillHasData(['tp_travel_date', 'tp_departure', 'tp_arrival', 'tp_destination', 'tp_budget', 'tp_notes', 'tp_tour_type', 'tp_hotel_category', 'tp_rooms', 'tp_adults'])) services.push('tour_package');
                    if (prefillHasData(['cruise_embark_date', 'cruise_line', 'cruise_cabin', 'cruise_pax', 'cruise_port'])) services.push('cruise');
                    if (prefillHasData(['visa_country', 'visa_type'])) services.push('visa');
                    if (prefillHasData(['passport_type', 'passport_applicants', 'passport_city'])) services.push('passport');
                    if (prefillHasData(['forex_currency', 'forex_amount'])) services.push('forex');
                    if (prefillHasData(['vehicle_type'])) services.push('vehicle');
                }
                // Mirror the Create Lead cascade so dependent fields (hotel category, rooms) are revealed.
                if (services.indexOf('tour_package') >= 0) {
                    ['flight', 'hotel', 'sightseeing'].forEach(function (s) {
                        if (services.indexOf(s) < 0) services.push(s);
                    });
                }
                if (prefillHasData(['tp_hotel_category', 'tp_rooms']) && services.indexOf('hotel') < 0) services.push('hotel');
                if (prefillHasData(['vehicle_type']) && services.indexOf('vehicle') < 0) services.push('vehicle');

                if (services.length) {
                    $form.find('input.js-service-checkbox').each(function () {
                        var $cb = jQuery(this);
                        $cb.prop('checked', services.indexOf($cb.val()) >= 0);
                    });
                }
                syncTravelDetailsByService();

                var scalarKeys = ['customer_initial', 'customer_name', 'customer_phone', 'customer_email', 'lead_source', 'referred_by', 'assign_to',
                    'tp_travel_date', 'tp_departure', 'tp_arrival', 'tp_budget', 'tp_notes',
                    'cruise_embark_date', 'cruise_line', 'cruise_cabin', 'cruise_pax', 'cruise_port',
                    'flight_from', 'flight_to', 'flight_depart_date', 'flight_return_date', 'flight_pax', 'flight_class',
                    'hotel_destination', 'hotel_checkin', 'hotel_checkout', 'hotel_rooms', 'hotel_pax', 'hotel_category',
                    'visa_country', 'visa_type', 'visa_travel_date', 'visa_pax',
                    'passport_type', 'passport_applicants', 'passport_city',
                    'forex_currency', 'forex_amount', 'forex_purpose',
                    'insurance_destination', 'insurance_travel_date', 'insurance_pax',
                    'notes', 'other_details'];
                scalarKeys.forEach(function (k) {
                    if (prefill[k] != null && prefill[k] !== '') {
                        setVal(k, prefill[k]);
                    }
                });

                if ($tourType.length && prefill.tp_tour_type != null && prefill.tp_tour_type !== '') {
                    $tourType.val(prefill.tp_tour_type);
                }
                syncTourPackageDestinations();

                toArray(prefill.tp_destination).forEach(function (id) {
                    var match = leadDestinations.filter(function (d) { return String(d.id) === String(id); })[0];
                    if (!match) {
                        match = leadDestinations.filter(function (d) { return String(d.name) === String(id); })[0];
                    }
                    if (match) {
                        addTpDestination({ id: match.id, name: match.name });
                    }
                });

                if (leadPickerApi.setHotelCategories) {
                    leadPickerApi.setHotelCategories(toArray(prefill.tp_hotel_category));
                }
                if (leadPickerApi.setVehicleTypes) {
                    leadPickerApi.setVehicleTypes(toArray(prefill.vehicle_type));
                }
                if (leadPickerApi.setRoomsGuests) {
                    leadPickerApi.setRoomsGuests({
                        rooms: prefill.tp_rooms,
                        adults: prefill.tp_adults,
                        children: prefill.tp_children,
                        childAges: toArray(prefill.tp_children_ages),
                        childBedTypes: toArray(prefill.tp_child_bed_type),
                        childCnb: prefill.tp_child_cnb,
                        childCwb: prefill.tp_child_cwb
                    });
                }

                var itinDestIds = toArray(prefill.itinerary_dest_id);
                if (!itinDestIds.length) {
                    itinDestIds = toArray(prefill.tp_destination);
                }
                var itinDestNightsRaw = prefill.itinerary_dest_nights;
                var itinDestNightsMap = {};
                if (itinDestNightsRaw && typeof itinDestNightsRaw === 'object' && !Array.isArray(itinDestNightsRaw)) {
                    Object.keys(itinDestNightsRaw).forEach(function (key) {
                        itinDestNightsMap[String(key)] = itinDestNightsRaw[key];
                    });
                } else {
                    toArray(itinDestNightsRaw).forEach(function (nightVal, i) {
                        if (itinDestIds[i] != null) {
                            itinDestNightsMap[String(itinDestIds[i])] = nightVal;
                        }
                    });
                }

                var totalNightsPrefill = 0;
                if (prefill.itinerary_total_nights != null && prefill.itinerary_total_nights !== '') {
                    totalNightsPrefill = Math.max(parseInt(prefill.itinerary_total_nights, 10) || 0, 0);
                }
                if (totalNightsPrefill < 1) {
                    Object.keys(itinDestNightsMap).forEach(function (key) {
                        totalNightsPrefill += Math.max(parseInt(itinDestNightsMap[key], 10) || 0, 0);
                    });
                }
                if (totalNightsPrefill < 1) {
                    totalNightsPrefill = 1;
                }

                // Always restore Tour Package nights first so itinerary sync cannot blank the stepper.
                itineraryNightsManual = false;
                setItineraryTotalNightsFields(totalNightsPrefill);
                if (typeof syncItinerary === 'function') {
                    syncItinerary(true);
                }

                var hasPerDestNights = Object.keys(itinDestNightsMap).some(function (key) {
                    return itinDestNightsMap[key] != null && String(itinDestNightsMap[key]).trim() !== '';
                });
                if (hasPerDestNights) {
                    Object.keys(itinDestNightsMap).forEach(function (destId) {
                        var $nights = $form.find('.itinerary-dest-row[data-dest-id="' + destId + '"] .js-itinerary-dest-nights');
                        if ($nights.length) {
                            $nights.val(itinDestNightsMap[destId]);
                        }
                    });
                    if (typeof syncItineraryFromNightsChange === 'function') {
                        syncItineraryFromNightsChange();
                    }
                    // Keep the Tour Package nights value in sync with the saved total.
                    setItineraryTotalNightsFields(totalNightsPrefill);
                }

                if ($tourType.length && String($tourType.val() || '') !== '') {
                    setTpDestinationEnabled(true, 'Type to search destination');
                    renderTpDestinationTags();
                    syncTpDestinationHiddenInputs();
                }
            }
            formEl.applyLeadPrefill = applyLeadPrefill;

            if (isIntake) {
                applyIntakeFieldVisibility();
            }
            setItineraryCollapsed(true);
            syncTravelDetailsByService();
            if (isIntake && $tourType.length && $tourType.val()) {
                syncTourPackageDestinations();
            }

            initLeadDatePickers();
            initLeadContactLookup();

            try {
                var prefillRaw = $form.attr('data-lead-prefill');
                if (prefillRaw) {
                    // Keep the attribute so a later (async) re-init re-applies the prefill.
                    // jQuery 3 runs ready callbacks asynchronously, so the embed's own
                    // auto-init can fire after this one and would otherwise reset the pickers.
                    applyLeadPrefill(JSON.parse(prefillRaw));
                    syncLeadDatePickerValues();
                }
            } catch (e) {}
        };

        jQuery(function ($) {
            var $form = $('#<?= htmlspecialchars($leadFormDomId, ENT_QUOTES, 'UTF-8') ?>');
            if ($form.length) {
                window.initLeadCreateForm($form[0]);
            }
        });
    })();
</script>