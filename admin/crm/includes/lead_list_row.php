<?php
                                            $serviceIcons = [
                                                'tour_package' => ['fas fa-suitcase-rolling', 'Tour Package'],
                                                'cruise' => ['fas fa-ship', 'Cruise'],
                                                'flight' => ['fas fa-plane', 'Flight'],
                                                'hotel' => ['fas fa-hotel', 'Hotel'],
                                                'vehicle' => ['fas fa-car', 'Vehicle'],
                                                'sightseeing' => ['fas fa-binoculars', 'Sightseeing'],
                                                'visa' => ['fas fa-stamp', 'Visa'],
                                                'passport' => ['fas fa-passport', 'Passport'],
                                                'forex' => ['fas fa-exchange-alt', 'Forex'],
                                            ];
                                            $createdText = '';
                                            if (!empty($lead['created_at'])) {
                                                $ts = strtotime((string) $lead['created_at']);
                                                if ($ts !== false) {
                                                    $createdText = date('d ', $ts) . strtoupper(date('M', $ts)) . date(', h:i A', $ts);
                                                }
                                            }
                                            $leadHoverInfo = "Phone: " . ((string) ($lead['customer_phone'] !== '' ? $lead['customer_phone'] : '—')) . "\n"
                                                . "Email: " . ((string) ($lead['customer_email'] !== '' ? $lead['customer_email'] : '—'));
                                            $leadSourceHover = trim((string) ($lead['lead_source_text'] ?? ''));
                                            if ($leadSourceHover === '') {
                                                $leadSourceHover = '—';
                                            }
                                            $leadIdSourceTitle = 'Lead Source: ' . $leadSourceHover;
                                            $rowStage = (string) ($lead['stage'] ?? 'new_lead');
                                            $rowTourConfirmed = ($rowStage === 'confirmed')
                                                || !empty($lead['latest_is_tour_confirmed'])
                                                || !empty($lead['is_tour_confirmed']);
                                        ?>
                                            <tr data-lead-id="<?= (int) $lead['id'] ?>"<?= $rowTourConfirmed ? ' class="is-tour-confirmed"' : '' ?>>
                                                <td class="col-ld-lead">
                                                    <button type="button" class="lead-id-cell js-lead-row-expand"
                                                        data-lead-id="<?= (int) $lead['id'] ?>"
                                                        title="<?= htmlspecialchars($leadIdSourceTitle, ENT_QUOTES, 'UTF-8') ?>"
                                                        aria-label="Expand lead details">
                                                        <span class="lead-id-uid"><?= htmlspecialchars((string) $lead['lead_uid'], ENT_QUOTES, 'UTF-8') ?></span><?php if ($createdText !== '') { ?><span class="lead-id-meta"> | <?= htmlspecialchars($createdText, ENT_QUOTES, 'UTF-8') ?></span><?php } ?>
                                                    </button>
                                                </td>
                                                <td class="col-ld-guest">
                                                    <div class="lead-name">
                                                        <?php
                                                        $guestLetters = trim((string) ($lead['customer_name_letters'] ?? ''));
                                                        if ($guestLetters !== '') {
                                                        ?>
                                                            <span class="lead-guest-initials" aria-hidden="true"><?= htmlspecialchars($guestLetters, ENT_QUOTES, 'UTF-8') ?></span>
                                                        <?php } ?>
                                                        <span class="lead-name-text" title="<?= htmlspecialchars($leadHoverInfo, ENT_QUOTES, 'UTF-8') ?>" style="cursor:help;">
                                                            <?= htmlspecialchars((string) ($lead['customer_display_name'] ?? $lead['customer_name']), ENT_QUOTES, 'UTF-8') ?>
                                                        </span>
                                                        <?php if ((string) $lead['pax_text'] !== '—') { ?>
                                                            <span class="badge-trav badge-trav-pax ml-1"><?= htmlspecialchars((string) $lead['pax_text'], ENT_QUOTES, 'UTF-8') ?></span>
                                                        <?php } ?>
                                                    </div>
                                                </td>
                                                <td class="col-ld-dest">
                                                    <div class="cell-travelers-info">
                                                        <?php
                                                        $travelDestDisplay = trim((string) ($lead['travel_dest_display'] ?? ''));
                                                        $travelExDisplay = trim((string) ($lead['travel_departure_display'] ?? ''));
                                                        if ($travelDestDisplay !== '' || $travelExDisplay !== '') {
                                                        ?>
                                                            <div class="travel-route-text" title="<?= htmlspecialchars((string) ($lead['travel_destination_text'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                                                <?php if ($travelDestDisplay !== '') { ?>
                                                                    <span class="travel-dest-name"><?= htmlspecialchars($travelDestDisplay, ENT_QUOTES, 'UTF-8') ?></span>
                                                                <?php } ?>
                                                                <?php if ($travelDestDisplay !== '' && $travelExDisplay !== '') { ?>
                                                                    <span class="travel-dest-sep"> | </span>
                                                                <?php } ?>
                                                                <?php if ($travelExDisplay !== '') { ?>
                                                                    <span class="travel-ex-city"><?= htmlspecialchars($travelExDisplay, ENT_QUOTES, 'UTF-8') ?></span>
                                                                <?php } ?>
                                                            </div>
                                                        <?php } else { ?>
                                                            <span class="text-muted">—</span>
                                                        <?php } ?>
                                                    </div>
                                                </td>
                                                <td class="cell-travel-date col-ld-date">
                                                    <?php if ((string) ($lead['travel_date_text'] ?? '') !== '') { ?>
                                                        <span class="badge-trav badge-trav-date"><i class="far fa-calendar"></i> <?= htmlspecialchars((string) $lead['travel_date_text'], ENT_QUOTES, 'UTF-8') ?></span>
                                                    <?php } else { ?>
                                                        <span class="text-muted">—</span>
                                                    <?php } ?>
                                                </td>
                                                <td class="col-services col-ld-services">
                                                    <?php
                                                    $leadServices = [];
                                                    if (!empty($lead['services'])) {
                                                        foreach ($lead['services'] as $svcItem) {
                                                            $svcItem = (string) $svcItem;
                                                            if (isset($serviceIcons[$svcItem])) {
                                                                $leadServices[] = $svcItem;
                                                            }
                                                        }
                                                    }
                                                    if (empty($leadServices)) { ?>
                                                        <span class="text-muted">—</span>
                                                    <?php } else {
                                                        $firstService = $leadServices[0];
                                                        $extraServices = array_slice($leadServices, 1);
                                                        $hasExtraServices = count($extraServices) > 0;
                                                    ?>
                                                        <div class="svc-pills<?= $hasExtraServices ? ' svc-pills-collapsible' : '' ?>"<?= $hasExtraServices ? ' title="' . count($extraServices) . ' more service' . (count($extraServices) === 1 ? '' : 's') . '"' : '' ?>>
                                                            <span class="svc-pill svc-pill-<?= htmlspecialchars($firstService, ENT_QUOTES, 'UTF-8') ?>">
                                                                <i class="<?= htmlspecialchars($serviceIcons[$firstService][0], ENT_QUOTES, 'UTF-8') ?>"></i>
                                                                <?= htmlspecialchars($serviceIcons[$firstService][1], ENT_QUOTES, 'UTF-8') ?>
                                                            </span>
                                                            <?php if ($hasExtraServices) { ?>
                                                                <div class="svc-pills-popup">
                                                                    <?php foreach ($extraServices as $svc) { ?>
                                                                        <span class="svc-pill svc-pill-<?= htmlspecialchars($svc, ENT_QUOTES, 'UTF-8') ?>">
                                                                            <i class="<?= htmlspecialchars($serviceIcons[$svc][0], ENT_QUOTES, 'UTF-8') ?>"></i>
                                                                            <?= htmlspecialchars($serviceIcons[$svc][1], ENT_QUOTES, 'UTF-8') ?>
                                                                        </span>
                                                                    <?php } ?>
                                                                </div>
                                                            <?php } ?>
                                                        </div>
                                                    <?php } ?>
                                                </td>
                                                <td class="col-ld-source">
                                                    <?php
                                                    $leadSourceMain = trim((string) ($lead['lead_source'] ?? ''));
                                                    $leadSourceRef = trim((string) ($lead['referred_by'] ?? ''));
                                                    $leadSourceDisplay = ($leadSourceMain !== '' ? $leadSourceMain : '—') . ' | ' . ($leadSourceRef !== '' ? $leadSourceRef : '—');
                                                    ?>
                                                    <span class="cell-lead-source" title="<?= htmlspecialchars($leadSourceDisplay, ENT_QUOTES, 'UTF-8') ?>">
                                                        <?= htmlspecialchars($leadSourceMain !== '' ? $leadSourceMain : '—', ENT_QUOTES, 'UTF-8') ?><span class="cell-lead-source-sep"> | </span><?= htmlspecialchars($leadSourceRef !== '' ? $leadSourceRef : '—', ENT_QUOTES, 'UTF-8') ?>
                                                    </span>
                                                </td>
                                                <td class="col-ld-assign">
                                                    <?php
                                                    $assignRaw = trim((string) ($lead['assign_to'] ?? ''));
                                                    $assignee = crmLeadsResolveAssignee($assignRaw, $assignUserLookup);
                                                    if ($assignee === null) {
                                                        ?>
                                                        <span class="cell-assign is-empty">—</span>
                                                        <?php
                                                    } else {
                                                        $assignLabel = (string) $assignee['label'];
                                                        $assignImage = (string) $assignee['image'];
                                                        $assignInitial = (string) $assignee['initial'];
                                                        $assignTone = (string) $assignee['tone_key'];
                                                        $assignColor = (string) $assignee['tone_color'];
                                                        ?>
                                                        <span class="cell-assign" title="<?= htmlspecialchars($assignLabel, ENT_QUOTES, 'UTF-8') ?>">
                                                            <?php if ($assignImage !== '') { ?>
                                                                <img class="cell-assign-avatar" src="<?= htmlspecialchars('uploads/users/' . $assignImage, ENT_QUOTES, 'UTF-8') ?>" alt="">
                                                            <?php } else { ?>
                                                                <span class="cell-assign-initial tone-<?= htmlspecialchars($assignTone, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($assignInitial, ENT_QUOTES, 'UTF-8') ?></span>
                                                            <?php } ?>
                                                            <span class="cell-assign-name" style="color: <?= htmlspecialchars($assignColor, ENT_QUOTES, 'UTF-8') ?>;">
                                                                <?= htmlspecialchars($assignLabel, ENT_QUOTES, 'UTF-8') ?>
                                                            </span>
                                                        </span>
                                                        <?php
                                                    }
                                                    ?>
                                                </td>
                                                <td class="col-stage col-ld-stage">
                                                    <?php
                                                    $leadStage = (string) ($lead['stage'] ?? 'new_lead');
                                                    $leadStageClass = (string) ($lead['stage_class'] ?? 'stage-new_lead');
                                                    $leadStageAuto = !empty($lead['stage_is_auto']);
                                                    $stageSelectTitle = $leadStageAuto
                                                        ? 'Stage updates automatically from quotation activity. You can mark as Lost.'
                                                        : 'Lead stage';
                                                    ?>
                                                    <select class="lead-stage-select js-lead-stage-select <?= htmlspecialchars($leadStageClass, ENT_QUOTES, 'UTF-8') ?>"
                                                        data-lead-id="<?= (int) $lead['id'] ?>"
                                                        data-stage="<?= htmlspecialchars($leadStage, ENT_QUOTES, 'UTF-8') ?>"
                                                        data-stage-auto="<?= $leadStageAuto ? '1' : '0' ?>"
                                                        title="<?= htmlspecialchars($stageSelectTitle, ENT_QUOTES, 'UTF-8') ?>"
                                                        aria-label="Lead stage">
                                                        <?php foreach ($leadStageOptions as $stageKey => $stageLabel) {
                                                            $optionDisabled = ($leadStage !== 'lost' && $stageKey !== 'lost');
                                                            ?>
                                                            <option value="<?= htmlspecialchars($stageKey, ENT_QUOTES, 'UTF-8') ?>"<?= $leadStage === $stageKey ? ' selected' : '' ?><?= $optionDisabled ? ' disabled' : '' ?>>
                                                                <?= htmlspecialchars($stageLabel, ENT_QUOTES, 'UTF-8') ?>
                                                            </option>
                                                        <?php } ?>
                                                    </select>
                                                </td>
                                                <td class="col-ld-booking js-booking-status"<?= ((int) ($lead['latest_quotation_id'] ?? 0) > 0) ? ' data-id="' . (int) $lead['latest_quotation_id'] . '"' : '' ?>>
                                                    <?= $lead['booking_status_html'] ?? '<span class="ld-book-status-empty">—</span>' ?>
                                                </td>
                                                <td class="col-actions">
                                                    <div class="action-btns">
                                                        <?php
                                                            $latestQuotationHref = trim((string) ($lead['latest_quotation_href'] ?? ''));
                                                            $latestQuotationId = (int) ($lead['latest_quotation_id'] ?? 0);
                                                            if ($latestQuotationHref === '' && $latestQuotationId > 0) {
                                                                $latestQuotationHref = 'crm/quotation_generator.php?id=' . $latestQuotationId;
                                                            }
                                                            if ($latestQuotationHref === '' && !empty($lead['quotation_groups'][0]['current_href'])) {
                                                                $latestQuotationHref = (string) $lead['quotation_groups'][0]['current_href'];
                                                                $latestQuotationId = (int) ($lead['quotation_groups'][0]['quotation_id'] ?? $latestQuotationId);
                                                            }
                                                            $hasQuotationAction = ($latestQuotationHref !== '')
                                                                || $latestQuotationId > 0
                                                                || !empty($lead['has_quotation'])
                                                                || in_array($leadStage, ['quoted', 'confirmed'], true);
                                                        ?>
                                                        <?php if ($hasQuotationAction && $latestQuotationHref !== '') {
                                                            $latestIsDraft = (($lead['latest_quotation_status'] ?? '') === 'draft');
                                                            $latestTourConfirmed = !empty($lead['latest_is_tour_confirmed'])
                                                                || !empty($lead['is_tour_confirmed'])
                                                                || ($leadStage === 'confirmed');
                                                            $viewOpensPreview = (in_array($leadStage, ['quoted', 'confirmed'], true) && !$latestIsDraft && $latestQuotationId > 0);
                                                            $viewTitle = $viewOpensPreview ? 'Preview Quotation' : 'View Quotation';
                                                            ?>
                                                            <?php if ($viewOpensPreview) { ?>
                                                            <button type="button"
                                                                class="btn-icon btn-view js-lead-q-preview"
                                                                data-quotation-id="<?= $latestQuotationId ?>"
                                                                data-edit-href="<?= htmlspecialchars($latestQuotationHref, ENT_QUOTES, 'UTF-8') ?>"
                                                                title="<?= htmlspecialchars($viewTitle, ENT_QUOTES, 'UTF-8') ?>"
                                                                aria-label="<?= htmlspecialchars($viewTitle, ENT_QUOTES, 'UTF-8') ?>">
                                                                <i class="far fa-eye"></i>
                                                            </button>
                                                            <?php } else { ?>
                                                            <a href="<?= htmlspecialchars($latestQuotationHref, ENT_QUOTES, 'UTF-8') ?>"
                                                                class="btn-icon btn-view"
                                                                title="<?= htmlspecialchars($viewTitle, ENT_QUOTES, 'UTF-8') ?>"
                                                                aria-label="<?= htmlspecialchars($viewTitle, ENT_QUOTES, 'UTF-8') ?>">
                                                                <i class="far fa-eye"></i>
                                                            </a>
                                                            <?php } ?>
                                                            <?php if ($latestQuotationId > 0 && !$latestIsDraft) { ?>
                                                                <button type="button"
                                                                    class="btn-icon js-q-book <?= $latestTourConfirmed ? 'btn-confirmed' : 'btn-book' ?>"
                                                                    data-id="<?= $latestQuotationId ?>"
                                                                    title="<?= $latestTourConfirmed ? 'Tour Confirmed' : 'Book' ?>"
                                                                    aria-label="<?= $latestTourConfirmed ? 'Tour Confirmed' : 'Book quotation' ?>">
                                                                    <?php if ($latestTourConfirmed) { ?>
                                                                        <i class="fas fa-check"></i>
                                                                    <?php } else { ?>
                                                                        <img src="img/booking.png" alt="" class="btn-book-img" width="16" height="16">
                                                                    <?php } ?>
                                                                </button>
                                                            <?php } ?>
                                                        <?php } else { ?>
                                                            <a href="crm/quotation_generator.php?lead_id=<?= (int) $lead['id'] ?>&fresh=1"
                                                                class="btn-icon btn-create-quote js-open-quotation-tab"
                                                                title="Create Quotation">
                                                                <i class="fas fa-plus"></i>
                                                            </a>
                                                        <?php } ?>
                                                        <div class="dropdown lead-actions-more"
                                                            data-lead-id="<?= (int) $lead['id'] ?>"
                                                            data-lead-email="<?= htmlspecialchars((string) ($lead['customer_email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                                            data-lead-phone="<?= htmlspecialchars((string) ($lead['customer_phone'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                                            <button type="button"
                                                                class="btn-icon btn-more js-lead-actions-toggle"
                                                                aria-haspopup="true"
                                                                aria-expanded="false"
                                                                title="More actions">
                                                                <i class="fas fa-ellipsis-v"></i>
                                                            </button>
                                                            <div class="dropdown-menu dropdown-menu-right lead-actions-menu">
                                                                <button type="button"
                                                                    class="dropdown-item js-lead-action-message"
                                                                    data-lead-id="<?= (int) $lead['id'] ?>">
                                                                    <i class="far fa-comment-dots mr-2 text-primary"></i> Message
                                                                </button>
                                                                <button type="button" class="dropdown-item js-lead-action-preview" data-lead-id="<?= (int) $lead['id'] ?>">
                                                                    <i class="far fa-eye mr-2 text-muted"></i> Preview Lead
                                                                </button>
                                                                <?php if ($latestQuotationHref !== '') { ?>
                                                                    <a class="dropdown-item js-open-quotation-tab" href="<?= htmlspecialchars($latestQuotationHref, ENT_QUOTES, 'UTF-8') ?>">
                                                                        <i class="fas fa-edit mr-2 text-muted"></i> Edit Quotation
                                                                    </a>
                                                                    <?php if (in_array(($leadStage ?? ''), ['quoted', 'confirmed'], true)
                                                                        && (($lead['latest_quotation_status'] ?? '') !== 'draft')
                                                                        && $latestQuotationId > 0) { ?>
                                                                    <button type="button"
                                                                        class="dropdown-item js-lead-q-preview"
                                                                        data-quotation-id="<?= $latestQuotationId ?>"
                                                                        data-edit-href="<?= htmlspecialchars($latestQuotationHref, ENT_QUOTES, 'UTF-8') ?>">
                                                                        <i class="far fa-file-alt mr-2 text-muted"></i> Preview Quotation
                                                                    </button>
                                                                    <?php } ?>
                                                                <?php } ?>
                                                                <button type="button" class="dropdown-item js-lead-action-duplicate" data-lead-id="<?= (int) $lead['id'] ?>">
                                                                    <i class="far fa-copy mr-2 text-muted"></i> Duplicate
                                                                </button>
                                                                <button type="button" class="dropdown-item js-lead-action-attachment" data-lead-id="<?= (int) $lead['id'] ?>">
                                                                    <i class="fas fa-paperclip mr-2 text-muted"></i> Attachment
                                                                </button>
                                                                <div class="dropdown-divider"></div>
                                                                <button type="button" class="dropdown-item text-danger js-lead-delete-btn" data-lead-id="<?= (int) $lead['id'] ?>">
                                                                    <i class="fas fa-trash-alt mr-2"></i> Delete
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
