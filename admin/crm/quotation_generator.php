<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/includes/quotation_db.php';
require_once __DIR__ . '/includes/lead_quotation_prefill.php';
require_once __DIR__ . '/includes/lead_db.php';
require_once __DIR__ . '/includes/supplier_db.php';
require_once __DIR__ . '/includes/quotation_terms_db.php';
require_once __DIR__ . '/../mail/includes/mail_db.php';
require_once __DIR__ . '/../mail/includes/mail_service.php';

crmEnsureQuotationTables($conn);
crmEnsureSupplierTables($conn);
crmEnsureQuotationTermsMasterTable($conn);
mailEnsureTables($conn);
crmSyncQuotationUidsFromLeads($conn);

require_once __DIR__ . '/../includes/geo_locations.php';
geoEnsureTables($conn);

// Destinations for the destination picker (unique, prefer title-case labels)
$destinations = [];
$destSeen = [];
$destRes = $conn->query("SELECT name FROM destinations WHERE is_active = 1 ORDER BY display_order ASC, name ASC");
if ($destRes) {
    while ($row = $destRes->fetch_assoc()) {
        $name = trim((string) ($row['name'] ?? ''));
        if ($name === '') {
            continue;
        }
        $key = strtolower($name);
        if (isset($destSeen[$key])) {
            continue;
        }
        $destSeen[$key] = true;
        if ($name === $key) {
            $name = ucwords($name);
        }
        $destinations[] = $name;
    }
}

// Edit mode -------------------------------------------------------------
$editId = (int) ($_GET['id'] ?? 0);
$viewVersion = max(0, (int) ($_GET['version'] ?? 0));
$quotation = null;
$isArchivedView = false;
$currentQuotationVersion = 1;
$currentQuotation = null;
$quotationVersionOptions = [];
$activeViewVersion = 0;

if ($editId > 0) {
    $stmt = $conn->prepare('SELECT * FROM `crm_quotations` WHERE `id` = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('i', $editId);
        $stmt->execute();
        $res = $stmt->get_result();
        $currentQuotation = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        if ($currentQuotation) {
            $currentQuotationVersion = max(1, (int) ($currentQuotation['version'] ?? 1));
            $quotationVersionOptions = crmQuotationGetVersionOptions($conn, $editId, $currentQuotation);
            $activeViewVersion = ($viewVersion > 0 && $viewVersion < $currentQuotationVersion)
                ? $viewVersion
                : $currentQuotationVersion;
            if ($viewVersion > 0 && $viewVersion < $currentQuotationVersion) {
                $archived = crmQuotationLoadArchivedVersion($conn, $editId, $viewVersion);
                if ($archived) {
                    $quotation = $archived;
                    $isArchivedView = true;
                } else {
                    $quotation = $currentQuotation;
                }
            } else {
                $quotation = $currentQuotation;
            }
        }
    }
}

$prefill = [];
if ($quotation) {
    $prefill = crmQuotationRowToPrefill($quotation);
    $prefill['view_version'] = $isArchivedView ? $viewVersion : $currentQuotationVersion;
    if ($isArchivedView) {
        $prefill['id'] = $editId;
        $prefill['current_version'] = $currentQuotationVersion;
        $prefill['editing_from_version'] = $viewVersion;
    }
}

$leadId = (int) ($_GET['lead_id'] ?? 0);
$leadRow = null;
$destinationLookup = crmQuotationDestinationLookup($conn);

if (!$quotation && $leadId > 0) {
    $leadRow = crmLeadFetchById($conn, $leadId, false);
    if ($leadRow) {
        $prefill = crmLeadRowToQuotationPrefill($leadRow, $destinationLookup);
    }
} elseif ($quotation) {
    $sidebarLeadId = (int) ($quotation['lead_id'] ?? 0);
    if ($sidebarLeadId <= 0 && !empty($currentQuotation['lead_id'])) {
        $sidebarLeadId = (int) $currentQuotation['lead_id'];
    }
    if ($sidebarLeadId <= 0) {
        $sidebarLeadId = crmQuotationResolveLeadId($conn, $quotation);
    }
    if ($sidebarLeadId > 0 && $editId > 0 && (int) ($currentQuotation['lead_id'] ?? 0) <= 0) {
        crmQuotationPersistLeadLink($conn, $editId, $sidebarLeadId);
        $quotation['lead_id'] = $sidebarLeadId;
        if (!empty($currentQuotation)) {
            $currentQuotation['lead_id'] = $sidebarLeadId;
        }
        if (!empty($prefill)) {
            $prefill['lead_id'] = $sidebarLeadId;
        }
    }
    if ($sidebarLeadId > 0) {
        $leadRow = crmLeadFetchById($conn, $sidebarLeadId, false);
    }
}

$leadSidebar = $leadRow ? crmLeadRowToSidebarPanel($conn, $leadRow, $destinationLookup) : null;

$quotationDestination = trim((string) ($prefill['destination'] ?? ($quotation['destination'] ?? '')));
if ($quotationDestination === '' && $leadSidebar) {
    $sidebarDest = trim((string) ($leadSidebar['travel']['destination'] ?? ''));
    if ($sidebarDest !== '' && $sidebarDest !== '—') {
        $quotationDestination = $sidebarDest;
    }
}

$qMailAccount = mailGetAccount($conn, (int) ($_SESSION['id'] ?? 0));
$qMailOrg = mailGetOrgSettings($conn);
mailSeedSmtpMasterFromLegacy($conn);
$mailSenders = mailListSmtpMaster($conn, true);
$qMailSmtp = null;
if ($qMailAccount && ($qMailAccount['smtp_status'] ?? '') === 'active') {
    $qMailSmtp = mailSmtpConfigFromAccount($qMailAccount, $qMailOrg);
} elseif (!empty($qMailOrg['is_active']) && !empty($qMailOrg['smtp_host'])) {
    $qMailSmtp = mailSmtpConfigFromOrg($qMailOrg);
}
$qMailFromName = trim((string) ($mailSenders[0]['from_name'] ?? $qMailSmtp['from_name'] ?? ($_SESSION['name'] ?? 'CRM Admin')));
$qMailFromEmail = trim((string) ($mailSenders[0]['from_email'] ?? $qMailSmtp['from_email'] ?? ($qMailAccount['email_address'] ?? '')));
$qMailReplyTo = $qMailFromEmail;
$qMailGuestName = trim((string) ($prefill['guest_name'] ?? ($quotation['guest_name'] ?? '')));
$qSupplierQuoteMail = crmSupplierQuoteMailFromContext(
    is_array($leadRow ?? null) ? $leadRow : null,
    $destinationLookup,
    is_array($prefill) ? $prefill : [],
    is_array($quotation ?? null) ? $quotation : []
);
$qMailSubject = (string) ($qSupplierQuoteMail['subject'] ?? 'Quotation Request');
$qMailBodyHtml = (string) ($qSupplierQuoteMail['body_html'] ?? '');
$qMailMeta = is_array($qSupplierQuoteMail['meta'] ?? null) ? $qSupplierQuoteMail['meta'] : [];
$qSupplierMailCatalog = crmSuppliersMailCatalog($conn);
$qHotelSuppliers = crmSuppliersForHotelSelect($conn);
$qFlightSuppliers = crmSuppliersForFlightSelect($conn);
$qDestinationNameToId = [];
foreach ($destinationLookup as $destId => $destName) {
    $key = strtolower(trim((string) $destName));
    if ($key !== '') {
        $qDestinationNameToId[$key] = (int) $destId;
    }
}
$quotationTermsMaster = crmGetQuotationTermsMaster($conn);

$qCountries = [];
$qCountriesRes = $conn->query('SELECT id, name FROM countries WHERE COALESCE(is_deleted, 0) = 0 ORDER BY name ASC');
if ($qCountriesRes) {
    while ($cRow = $qCountriesRes->fetch_assoc()) {
        $qCountries[] = [
            'id' => (int) ($cRow['id'] ?? 0),
            'name' => (string) ($cRow['name'] ?? ''),
        ];
    }
}

$qDestinationCountryIdByName = [];
$qDestCountryRes = $conn->query('SELECT d.name AS dest_name, c.id AS country_id
    FROM destinations d
    LEFT JOIN countries c ON LOWER(TRIM(c.name)) = LOWER(TRIM(d.country)) AND COALESCE(c.is_deleted, 0) = 0
    WHERE d.is_active = 1');
if ($qDestCountryRes) {
    while ($dcRow = $qDestCountryRes->fetch_assoc()) {
        $dKey = strtolower(trim((string) ($dcRow['dest_name'] ?? '')));
        $cid = (int) ($dcRow['country_id'] ?? 0);
        if ($dKey !== '' && $cid > 0 && !isset($qDestinationCountryIdByName[$dKey])) {
            $qDestinationCountryIdByName[$dKey] = $cid;
        }
    }
}

$showVersionBar = $editId > 0 && !empty($quotationVersionOptions);

$pageTitle = $isArchivedView
    ? ('Quotation' . ($showVersionBar ? '' : (' v' . $viewVersion)))
    : ($quotation
        ? (crmQuotationIsDraft($quotation)
            ? 'Continue Draft Quotation'
            : ('Edit Quotation' . (!$showVersionBar && $currentQuotationVersion > 1 ? ' v' . $currentQuotationVersion : '')))
        : (!empty($prefill['lead_id']) ? 'Create Quotation from Lead' : 'Quotation Generator'));

$showSaveDraft = !$isArchivedView && (!$quotation || crmQuotationIsDraft($quotation));

$qPreviewMeta = [
    'logo' => 'img/web-logo.png',
    'expert_name' => 'Raju Gupta',
    'expert_title' => 'Holiday Expert',
    'expert_photo' => 'img/holiday-expert.png',
    'phone' => '+91 7781000140',
    'phone_alt' => '+91 651 2243140 | +91 651 2244140',
    'email' => 'holidays@multizonetravels.com',
    'website' => 'www.multizonetravels.com',
    'address' => 'Bye Pass Road, Dibadih, Doranda, Ranchi - 834002, Jharkhand, India',
    'services' => 'FLIGHTS | HOTELS | HOLIDAYS | VISA | FOREX',
    'social' => [
        ['type' => 'facebook', 'url' => 'https://www.facebook.com/', 'icon' => 'fab fa-facebook-f'],
        ['type' => 'twitter', 'url' => 'https://twitter.com/', 'icon' => 'fab fa-twitter'],
        ['type' => 'google', 'url' => 'https://plus.google.com/', 'icon' => 'fab fa-google-plus-g'],
        ['type' => 'web', 'url' => 'https://www.multizonetravels.com/', 'icon' => 'fas fa-globe'],
    ],
    'quotation_uid' => (string) ($quotation['quotation_uid'] ?? ($prefill['quotation_uid'] ?? '')),
];

$qWizardSteps = [
    ['id' => 1, 'label' => 'Guest & Tour', 'color' => '#e11d2e', 'icon' => 'fas fa-user'],
    ['id' => 2, 'label' => 'Flight / Train', 'color' => '#e11d2e', 'icon' => 'fas fa-plane'],
    ['id' => 3, 'label' => 'Hotel Details', 'color' => '#e11d2e', 'icon' => 'fas fa-hotel'],
    ['id' => 4, 'label' => 'Itinerary', 'color' => '#e11d2e', 'icon' => 'fas fa-map-marker-alt'],
    ['id' => 5, 'label' => 'Terms & Policies', 'color' => '#e11d2e', 'icon' => 'fas fa-shield-alt'],
    ['id' => 6, 'label' => 'Pricing', 'color' => '#e11d2e', 'icon' => 'fas fa-rupee-sign'],
];
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <base href="../">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <?php include __DIR__ . '/../includes/header-links.php'; ?>
    <style>
        :root {
            --q-primary: #2563eb;
            --q-primary-dark: #1d4ed8;
            --q-primary-soft: #eff6ff;
            --q-accent: #0d9488;
            --q-accent-dark: #0f766e;
            --q-accent-soft: #ecfdf5;
            --q-save: #0284c7;
            --q-border: #e2e8f0;
            --q-border-light: #f1f5f9;
            --q-text: #0f172a;
            --q-text-muted: #64748b;
            --q-label: #64748b;
            --q-bg: #eef2f7;
            --q-card-bg: #fff;
            --q-radius: 10px;
            --q-radius-sm: 8px;
            --q-shadow-sm: 0 1px 2px rgba(15, 23, 42, 0.05);
            --q-shadow-md: 0 4px 16px rgba(15, 23, 42, 0.06);
            --q-shadow-lg: 0 8px 30px rgba(15, 23, 42, 0.08);
        }

        .crm-quotation-gen .content-wrapper > .content {
            background: linear-gradient(180deg, #f8fafc 0%, var(--q-bg) 100%);
            padding: 0.65rem 0.75rem 1.25rem;
        }

        .crm-quotation-gen .content-header {
            padding: 0;
            min-height: 0;
            display: none;
        }

        .crm-quotation-gen .container-fluid {
            padding-left: 0.35rem;
            padding-right: 0.35rem;
            max-width: 100%;
        }

        .crm-quotation-gen .page-title-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.5rem;
            flex-wrap: wrap;
            gap: 0.65rem;
        }

        .crm-quotation-gen .page-title-right {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.65rem;
            margin-left: auto;
        }

        .crm-quotation-gen .btn-q-send-mail {
            background: #16a34a;
            border-color: #16a34a;
            color: #fff;
            font-weight: 600;
            border-radius: 999px;
            padding: 0.35rem 0.95rem;
        }

        .crm-quotation-gen .btn-q-send-mail:hover {
            background: #15803d;
            border-color: #15803d;
            color: #fff;
        }

        .q-supplier-mail-modal {
            border: 0;
            border-radius: 12px;
            overflow: hidden;
        }

        .q-supplier-mail-header {
            border-bottom: 1px solid #e9ecef;
            padding: 0.85rem 1rem;
        }

        .q-supplier-mail-header .modal-title {
            font-size: 1rem;
            font-weight: 700;
        }

        .q-supplier-mail-body {
            padding: 0;
        }

        .q-supplier-mail-from-field {
            padding: 0.85rem 1rem;
            border-bottom: 1px solid #eef2f7;
        }

        .q-supplier-mail-from-row {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .q-supplier-mail-from-label {
            font-size: 0.92rem;
            font-weight: 600;
            color: #334155;
            flex-shrink: 0;
        }

        .q-supplier-mail-from-picker {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            flex: 1;
            min-width: 0;
        }

        .q-supplier-mail-sender-select {
            flex: 1;
            min-width: 0;
            border: 0 !important;
            background: transparent !important;
            padding: 0.25rem 1.5rem 0.25rem 0 !important;
            margin: 0 !important;
            font-size: 0.92rem !important;
            color: #334155 !important;
            cursor: pointer;
            box-shadow: none !important;
            height: auto !important;
            appearance: auto;
            -webkit-appearance: menulist;
        }

        .q-supplier-mail-sender-select:focus {
            outline: none;
            box-shadow: none !important;
        }

        .q-supplier-mail-avatar {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: #16a34a;
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            flex-shrink: 0;
        }

        .q-supplier-mail-from-text {
            display: none;
        }

        .q-supplier-mail-field {
            padding: 0.65rem 1rem;
            border-bottom: 1px solid #eef2f7;
        }

        .q-supplier-mail-field label {
            display: block;
            font-size: 0.78rem;
            font-weight: 600;
            color: #64748b;
            margin-bottom: 0.25rem;
        }

        .q-supplier-mail-field .form-control {
            border: 0;
            padding-left: 0;
            padding-right: 0;
            box-shadow: none;
            font-size: 0.92rem;
        }

        .q-supplier-mail-field .form-control:focus {
            box-shadow: none;
        }

        .q-supplier-mail-to-field {
            padding-bottom: 0.75rem;
        }

        .q-supplier-mail-to-main-row {
            align-items: flex-start;
            flex-wrap: wrap;
        }

        .q-supplier-mail-to-main-row .q-supplier-mail-from-label {
            padding-top: 0.45rem;
        }

        .q-supplier-mail-create-btn {
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 600;
            white-space: nowrap;
            margin-top: 0.15rem;
        }

        .q-supplier-mail-to-picker {
            position: relative;
            flex: 1;
            min-width: 200px;
        }

        .q-supplier-mail-to-trigger {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
            width: 100%;
            min-height: 34px;
            padding: 0.25rem 0.15rem 0.25rem 0;
            border: 0;
            background: transparent;
            color: #334155;
            font-size: 0.92rem;
            text-align: left;
            cursor: pointer;
        }

        .q-supplier-mail-to-trigger:focus {
            outline: none;
        }

        .q-supplier-mail-to-trigger-text {
            flex: 1;
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            color: #334155;
        }

        .q-supplier-mail-to-trigger-text.is-placeholder {
            color: #94a3b8;
        }

        .q-supplier-mail-to-trigger-icon {
            color: #94a3b8;
            font-size: 0.72rem;
            flex-shrink: 0;
        }

        .q-supplier-mail-to-picker.is-open .q-supplier-mail-to-trigger-icon {
            transform: rotate(180deg);
        }

        .q-supplier-mail-to-menu {
            position: absolute;
            top: calc(100% + 4px);
            left: 0;
            right: 0;
            z-index: 1060;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            box-shadow: 0 10px 28px rgba(15, 23, 42, 0.12);
            overflow: hidden;
        }

        .q-supplier-mail-to-menu-list {
            max-height: 220px;
            overflow-y: auto;
            padding: 0.35rem 0;
        }

        .q-supplier-mail-to-option {
            display: flex;
            align-items: flex-start;
            gap: 0.55rem;
            width: 100%;
            padding: 0.45rem 0.75rem;
            margin: 0;
            cursor: pointer;
            font-weight: 400;
            color: #334155;
        }

        .q-supplier-mail-to-option:hover {
            background: #f8fafc;
        }

        .q-supplier-mail-to-option input[type="checkbox"] {
            margin-top: 0.2rem;
            flex-shrink: 0;
        }

        .q-supplier-mail-to-option-text {
            min-width: 0;
            line-height: 1.3;
        }

        .q-supplier-mail-to-option-name {
            display: block;
            font-size: 0.86rem;
            font-weight: 600;
            color: #1e293b;
        }

        .q-supplier-mail-to-option-email {
            display: block;
            font-size: 0.76rem;
            color: #64748b;
        }

        .q-supplier-mail-to-custom {
            display: flex;
            gap: 0.4rem;
            padding: 0.55rem 0.75rem;
            border-top: 1px solid #eef2f7;
            background: #f8fafc;
        }

        .q-supplier-mail-to-custom .form-control {
            border: 1px solid #e2e8f0 !important;
            border-radius: 8px !important;
            padding: 0.3rem 0.55rem !important;
            font-size: 0.82rem !important;
            background: #fff !important;
        }

        .q-supplier-mail-to-custom .btn {
            white-space: nowrap;
            border-radius: 8px;
            font-weight: 600;
        }

        .q-supplier-mail-to-field .q-supplier-mail-supplier-empty {
            padding: 0.45rem 0.75rem 0.65rem;
        }

        .q-supplier-mail-recipient-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 0.35rem;
            margin-top: 0.55rem;
            min-width: 0;
        }

        .q-supplier-mail-recipient-badges:empty {
            display: none;
        }

        .q-supplier-mail-recipient-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            max-width: 100%;
            padding: 0.2rem 0.45rem 0.2rem 0.55rem;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 999px;
            font-size: 0.78rem;
            color: #1e40af;
            line-height: 1.3;
        }

        .q-supplier-mail-recipient-badge .badge-text {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            max-width: 280px;
        }

        .q-supplier-mail-recipient-badge .badge-remove {
            border: 0;
            background: transparent;
            color: #64748b;
            padding: 0;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            flex-shrink: 0;
        }

        .q-supplier-mail-recipient-badge .badge-remove:hover {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .q-supplier-mail-recipient-badge.badge-sent {
            background: #f0fdf4;
            border-color: #86efac;
            color: #166534;
        }

        .q-supplier-mail-recipient-badge.badge-failed {
            background: #fef2f2;
            border-color: #fecaca;
            color: #991b1b;
        }

        .q-supplier-mail-recipient-badge .badge-status-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 0.72rem;
        }

        .q-supplier-mail-recipient-badge .badge-status-sent {
            color: #16a34a;
        }

        .q-supplier-mail-recipient-badge .badge-status-failed {
            color: #dc2626;
        }

        .q-supplier-mail-recipient-badge .badge-status-sending {
            color: #64748b;
        }

        .q-supplier-mail-subject-field {
            padding-top: 0.55rem;
            padding-bottom: 0.55rem;
        }

        .q-supplier-mail-subject-field .q-supplier-mail-from-label {
            color: #202124;
            font-weight: 700;
            min-width: 3.5rem;
        }

        .q-supplier-mail-subject-input {
            border: 0 !important;
            background: transparent !important;
            box-shadow: none !important;
            padding: 0.25rem 0 !important;
            margin: 0 !important;
            height: auto !important;
            font-size: 0.92rem !important;
            font-weight: 400 !important;
            color: #202124 !important;
            flex: 1;
            min-width: 0;
        }

        .q-supplier-mail-subject-input:focus {
            outline: none;
            box-shadow: none !important;
        }

        .q-supplier-mail-subject-input::placeholder {
            color: #9aa0a6;
        }

        .q-supplier-mail-editor-wrap {
            padding: 0.35rem 1rem 0.25rem;
            min-height: 240px;
        }

        .q-supplier-mail-editor-wrap .note-editor.note-frame,
        .q-supplier-mail-editor-wrap .note-editor {
            border: 0 !important;
            border-radius: 0 !important;
            box-shadow: none !important;
            background: transparent;
        }

        .q-supplier-mail-editor-wrap .note-editing-area,
        .q-supplier-mail-editor-wrap .note-editable {
            background: #fff !important;
        }

        .q-supplier-mail-editor-wrap .note-editable {
            padding: 0.35rem 0.15rem 0.75rem !important;
            font-size: 0.92rem;
            line-height: 1.45;
            color: #202124;
            min-height: 220px !important;
        }

        .q-supplier-mail-editor-wrap .note-editable:focus {
            outline: none;
        }

        .q-supplier-mail-editor-wrap .note-editable p,
        .q-supplier-mail-editor-wrap .note-editable div {
            margin: 0 !important;
            padding: 0 !important;
            line-height: 1.45 !important;
        }

        .q-supplier-mail-editor-wrap .note-editable br {
            line-height: 1.45;
        }

        .q-supplier-mail-editor-wrap .note-statusbar,
        .q-supplier-mail-editor-wrap .note-resizebar {
            display: none !important;
        }

        .q-supplier-mail-editor-wrap .note-toolbar {
            display: none !important;
        }

        .q-supplier-mail-footer {
            border-top: 1px solid #e8eaed;
            padding: 0.55rem 0.85rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.5rem;
            background: #fff;
        }

        .q-gmail-compose-bar {
            display: flex;
            align-items: center;
            gap: 0.35rem;
            flex-wrap: wrap;
            min-width: 0;
            flex: 1;
        }

        .q-gmail-compose-actions {
            display: flex;
            align-items: center;
            gap: 0.15rem;
        }

        .q-gmail-send-btn {
            background: #0b57d0 !important;
            border-color: #0b57d0 !important;
            color: #fff !important;
            border-radius: 999px !important;
            font-weight: 600 !important;
            font-size: 0.88rem !important;
            padding: 0.45rem 1.35rem !important;
            line-height: 1.2;
            box-shadow: none !important;
        }

        .q-gmail-send-btn:hover,
        .q-gmail-send-btn:focus {
            background: #0842a0 !important;
            border-color: #0842a0 !important;
            color: #fff !important;
        }

        .q-gmail-send-btn:disabled {
            opacity: 0.7;
        }

        .q-gmail-format-toolbar {
            display: inline-flex;
            align-items: center;
            margin-left: 0.15rem;
        }

        .q-gmail-format-toolbar .note-toolbar {
            display: flex !important;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.1rem;
            padding: 0 !important;
            margin: 0 !important;
            border: 0 !important;
            background: transparent !important;
        }

        .q-gmail-format-toolbar .note-btn-group {
            margin: 0 0.1rem 0 0 !important;
        }

        .q-gmail-format-toolbar .note-btn {
            border: 0 !important;
            background: transparent !important;
            box-shadow: none !important;
            color: #5f6368 !important;
            padding: 0.35rem 0.45rem !important;
            border-radius: 50% !important;
            min-width: 32px;
            height: 32px;
            display: inline-flex !important;
            align-items: center;
            justify-content: center;
        }

        .q-gmail-format-toolbar .note-btn:hover,
        .q-gmail-format-toolbar .note-btn:focus,
        .q-gmail-format-toolbar .note-btn.active {
            background: #f1f3f4 !important;
            color: #202124 !important;
        }

        .q-gmail-format-toolbar .dropdown-toggle::after {
            display: none;
        }

        .q-gmail-icon-btn {
            width: 36px;
            height: 36px;
            padding: 0 !important;
            border: 0 !important;
            border-radius: 50% !important;
            background: transparent !important;
            color: #5f6368 !important;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: none !important;
        }

        .q-gmail-icon-btn:hover,
        .q-gmail-icon-btn:focus {
            background: #f1f3f4 !important;
            color: #202124 !important;
        }

        .q-gmail-attach-label {
            max-width: 160px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-size: 0.78rem;
        }

        .q-gmail-close-link {
            color: #0b57d0 !important;
            font-weight: 600;
            font-size: 0.86rem;
            padding: 0.25rem 0.4rem;
        }

        .q-supplier-mail-status-dialog {
            max-width: 560px;
        }

        .q-supplier-mail-status-modal {
            border: 0;
            border-radius: 12px;
            overflow: hidden;
        }

        .q-supplier-mail-status-header {
            border-bottom: 1px solid #e8eaed;
            padding: 0.85rem 1rem;
        }

        .q-supplier-mail-status-header .modal-title {
            font-size: 1rem;
            font-weight: 700;
            color: #202124;
        }

        .q-supplier-mail-status-body {
            padding: 1rem 1.1rem 1.15rem;
        }

        .q-supplier-mail-status-to-row {
            display: flex;
            align-items: baseline;
            gap: 0.4rem;
            margin-bottom: 0.75rem;
        }

        .q-supplier-mail-status-to-label {
            font-size: 0.92rem;
            font-weight: 700;
            color: #334155;
        }

        .q-supplier-mail-status-to-summary {
            font-size: 0.92rem;
            color: #5f6368;
        }

        .q-supplier-mail-status-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 0.4rem;
        }

        .q-supplier-mail-status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            max-width: 100%;
            padding: 0.28rem 0.65rem;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 999px;
            font-size: 0.82rem;
            color: #334155;
            line-height: 1.3;
        }

        .q-supplier-mail-status-badge .badge-text {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            max-width: 320px;
        }

        .q-supplier-mail-status-badge .badge-status-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 0.78rem;
        }

        .q-supplier-mail-status-badge.badge-sending {
            background: #f8fafc;
            border-color: #e2e8f0;
            color: #64748b;
        }

        .q-supplier-mail-status-badge.badge-sent {
            background: #f0fdf4;
            border-color: #86efac;
            color: #166534;
        }

        .q-supplier-mail-status-badge.badge-sent .badge-status-icon {
            color: #16a34a;
        }

        .q-supplier-mail-status-badge.badge-failed {
            background: #fef2f2;
            border-color: #fecaca;
            color: #991b1b;
        }

        .q-supplier-mail-status-badge.badge-failed .badge-status-icon {
            color: #dc2626;
        }

        .q-supplier-mail-status-footer {
            border-top: 1px solid #e8eaed;
            padding: 0.65rem 1rem;
        }

        .q-supplier-mail-status-footer .btn-primary {
            background: #0b57d0;
            border-color: #0b57d0;
            border-radius: 999px;
            min-width: 88px;
            font-weight: 600;
        }

        #qSupplierCreateModal {
            z-index: 1065;
        }

        #qSupplierCreateModal + .modal-backdrop {
            z-index: 1060;
        }

        .q-supplier-create-modal .modal-title {
            font-size: 1rem;
            font-weight: 700;
        }

        .crm-quotation-gen .page-title-left {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.75rem;
            min-width: 0;
        }

        .crm-quotation-gen .q-version-wrap {
            position: relative;
            display: inline-flex;
            align-items: center;
            vertical-align: middle;
        }

        .crm-quotation-gen .q-version-wrap::after {
            content: '\f107';
            font-family: 'Font Awesome 5 Free';
            font-weight: 900;
            position: absolute;
            right: 0.8rem;
            top: 50%;
            transform: translateY(-50%);
            pointer-events: none;
            color: #94a3b8;
            font-size: 0.72rem;
            line-height: 1;
        }

        .crm-quotation-gen .q-version-select {
            display: inline-block;
            min-width: 156px;
            height: 32px;
            border: 1px solid var(--q-border);
            border-radius: 8px;
            background-color: var(--q-card-bg) !important;
            background-image: none !important;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--q-text);
            padding: 0 1.85rem 0 0.75rem;
            cursor: pointer;
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
        }

        .crm-quotation-gen .q-version-select:focus {
            border-color: #cbd5e1;
            outline: none;
            box-shadow: 0 0 0 3px rgba(148, 163, 184, 0.18);
        }

        .crm-quotation-gen .q-version-select:hover {
            border-color: #cbd5e1;
        }

        .crm-quotation-gen .page-title {
            font-size: 1.35rem;
            font-weight: 800;
            color: var(--q-text);
            margin: 0;
            letter-spacing: -0.03em;
        }

        .crm-quotation-gen .breadcrumbs {
            font-size: 0.78rem;
            color: var(--q-text-muted);
            background: var(--q-card-bg);
            border: 1px solid var(--q-border);
            border-radius: 999px;
            padding: 0.28rem 0.75rem;
        }

        .crm-quotation-gen .breadcrumbs a {
            color: var(--q-primary);
            font-weight: 600;
        }

        .crm-quotation-gen .q-card {
            background: var(--q-card-bg);
            border: 1px solid var(--q-border);
            border-radius: var(--q-radius);
            box-shadow: var(--q-shadow-sm);
            padding: 0.85rem 1rem 0.95rem;
            margin-bottom: 0.65rem;
        }

        .crm-quotation-gen .q-section-title {
            display: flex;
            align-items: center;
            gap: 0.55rem;
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--q-text);
            margin: 0 0 1rem;
            padding: 0 0 0.85rem;
            border-bottom: 1px solid var(--q-border-light);
            text-transform: none;
            letter-spacing: -0.02em;
        }

        .crm-quotation-gen .q-section-title::before {
            content: '';
            width: 4px;
            height: 1.15rem;
            border-radius: 999px;
            background: linear-gradient(180deg, var(--q-primary) 0%, var(--q-accent) 100%);
            flex-shrink: 0;
        }

        .crm-quotation-gen .q-subsection-label {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            font-size: 0.68rem;
            font-weight: 700;
            color: #475569;
            margin: 0 0 0.75rem;
            padding: 0.32rem 0.7rem;
            background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
            border-radius: 999px;
            border: 1px solid var(--q-border);
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        .crm-quotation-gen .q-subsection-label:not(:first-of-type) {
            margin-top: 1rem;
            padding-top: 0.32rem;
            border-top: none;
        }

        .crm-quotation-gen .q-row-tight {
            margin-left: -0.5rem;
            margin-right: -0.5rem;
        }

        .crm-quotation-gen .q-row-tight > [class*="col-"] {
            padding-left: 0.5rem;
            padding-right: 0.5rem;
        }

        .crm-quotation-gen .form-group {
            margin-bottom: 0.75rem;
        }

        .crm-quotation-gen label.q-label {
            font-size: 0.74rem;
            color: var(--q-label);
            font-weight: 600;
            margin-bottom: 0.28rem;
            line-height: 1.25;
            letter-spacing: 0.01em;
        }

        .crm-quotation-gen .form-control,
        .crm-quotation-gen .custom-select {
            border-radius: var(--q-radius-sm);
            border: 1px solid var(--q-border);
            font-size: 0.84rem;
            padding: 0.45rem 0.75rem;
            height: calc(1.5em + 0.9rem + 2px);
            background: #fff;
            color: var(--q-text);
            transition: border-color 0.15s ease, box-shadow 0.15s ease, background 0.15s ease;
        }

        .crm-quotation-gen .form-control:hover:not([readonly]):not(:disabled) {
            border-color: #cbd5e1;
        }

        .crm-quotation-gen .form-control:focus {
            border-color: var(--q-primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
            background: #fff;
        }

        .crm-quotation-gen .form-control[readonly] {
            background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
            color: var(--q-text);
            font-weight: 700;
            border-color: #dbeafe;
        }

        .crm-quotation-gen .q-toolbar {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.45rem;
            margin-bottom: 0.85rem;
            padding: 0.65rem 0.75rem;
            background: linear-gradient(180deg, #f8fafc 0%, #fff 100%);
            border: 1px solid var(--q-border-light);
            border-radius: var(--q-radius-sm);
        }

        .crm-quotation-gen .q-toolbar .q-toolbar-sep {
            display: none;
        }

        .crm-quotation-gen .q-hint {
            font-size: 0.75rem;
            color: var(--q-text-muted);
            margin: 0 0 0.65rem;
            line-height: 1.45;
            padding: 0.45rem 0.65rem;
            background: var(--q-primary-soft);
            border-left: 3px solid var(--q-primary);
            border-radius: 0 var(--q-radius-sm) var(--q-radius-sm) 0;
        }

        .crm-quotation-gen .q-accordion-head {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.84rem;
            color: var(--q-text);
            padding: 0.65rem 0.85rem;
            user-select: none;
            background: var(--q-border-light);
            border-bottom: 1px solid var(--q-border-light);
            transition: background 0.15s ease;
        }

        .crm-quotation-gen .q-accordion-head:hover {
            background: var(--q-border);
        }

        .crm-quotation-gen .q-card-accordions .q-accordion-item {
            border: 1px solid var(--q-border);
            border-radius: var(--q-radius-sm);
            margin-bottom: 0.65rem;
            overflow: hidden;
            background: var(--q-card-bg);
            box-shadow: var(--q-shadow-sm);
        }

        .crm-quotation-gen .q-card-accordions .q-accordion-item:not(:last-child) .q-accordion-head {
            border-bottom-color: var(--q-border-light);
        }

        .crm-quotation-gen .q-accordion-head i.toggle-icon {
            transition: transform .2s ease;
            color: var(--q-primary);
            font-size: 0.72rem;
            width: 16px;
        }

        .crm-quotation-gen .q-accordion-head.collapsed i.toggle-icon {
            transform: rotate(-90deg);
        }

        .crm-quotation-gen .q-accordion-body {
            padding: 0.75rem 0.85rem 0.85rem;
        }

        /* ——— Main section accordions (wizard steps) ——— */
        .crm-quotation-gen .q-section-accordion {
            background: transparent;
        }

        .crm-quotation-gen .q-section-accordion-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 0.75rem;
            cursor: pointer;
            user-select: none;
            position: relative;
        }

        .crm-quotation-gen .q-section-accordion-head.q-guest-tour-head,
        .crm-quotation-gen .q-section-accordion-head.q-flight-head,
        .crm-quotation-gen .q-section-accordion-head.q-wizard-section-head {
            margin-bottom: 0.65rem;
            padding: 0 0 0 0.85rem;
            border-bottom: 0;
        }

        .crm-quotation-gen .q-section-accordion-head.q-guest-tour-head::before,
        .crm-quotation-gen .q-section-accordion-head.q-flight-head::before,
        .crm-quotation-gen .q-section-accordion-head.q-wizard-section-head::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0.15rem;
            bottom: 0.15rem;
            width: 4px;
            border-radius: 4px;
            background: #e11d2e;
        }

        .crm-quotation-gen .q-section-accordion-head.q-guest-tour-head .q-section-title,
        .crm-quotation-gen .q-section-accordion-head.q-flight-head .q-section-title,
        .crm-quotation-gen .q-section-accordion-head.q-wizard-section-head .q-section-title {
            display: block;
            margin: 0;
            padding: 0;
            border: 0;
            font-size: 1.2rem;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: -0.01em;
        }

        .crm-quotation-gen .q-section-accordion-head.q-guest-tour-head .q-section-title::before,
        .crm-quotation-gen .q-section-accordion-head.q-flight-head .q-section-title::before,
        .crm-quotation-gen .q-section-accordion-head.q-wizard-section-head .q-section-title::before {
            display: none;
        }

        .crm-quotation-gen .q-wizard-section-subtitle,
        .crm-quotation-gen .q-guest-tour-subtitle,
        .crm-quotation-gen .q-flight-subtitle {
            margin: 0.28rem 0 0;
            font-size: 0.82rem;
            color: #94a3b8;
            font-weight: 500;
            line-height: 1.4;
        }

        .crm-quotation-gen .q-section-accordion-head .q-hint {
            margin: 0.28rem 0 0;
            padding: 0;
            background: none;
            border: 0;
            border-radius: 0;
            font-size: 0.82rem;
            color: #94a3b8;
            font-weight: 500;
            line-height: 1.4;
        }

        .crm-quotation-gen .q-section-accordion-head-main {
            flex: 1 1 auto;
            min-width: 0;
        }

        .crm-quotation-gen .q-section-accordion-toggle {
            flex: 0 0 auto;
            width: 30px;
            height: 30px;
            margin-top: 0.1rem;
            border-radius: 8px;
            border: 1px solid var(--q-border);
            background: var(--q-border-light);
            color: #e11d2e;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: background 0.15s ease, border-color 0.15s ease;
        }

        .crm-quotation-gen .q-section-accordion-head:hover .q-section-accordion-toggle {
            background: #fff;
            border-color: #cbd5e1;
        }

        .crm-quotation-gen .q-section-accordion-toggle .toggle-icon {
            font-size: 0.72rem;
            transition: transform 0.2s ease;
        }

        .crm-quotation-gen .q-section-accordion-head.collapsed .q-section-accordion-toggle .toggle-icon {
            transform: rotate(-90deg);
        }

        .crm-quotation-gen .q-section-accordion-body {
            padding-top: 0.15rem;
        }

        .crm-quotation-gen .q-section-body-toolbar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.45rem;
            margin-bottom: 0.85rem;
        }

        .crm-quotation-gen .q-section-accordion-head .q-section-title {
            margin-bottom: 0;
        }

        .crm-quotation-gen .q-day-head.q-accordion-head {
            cursor: pointer;
        }

        .crm-quotation-gen .q-day-head-main {
            display: flex;
            align-items: center;
            gap: 0.45rem;
            min-width: 0;
            flex: 1 1 auto;
        }

        .crm-quotation-gen .q-day-head .toggle-icon {
            flex-shrink: 0;
            color: #e11d2e;
            font-size: 0.72rem;
            transition: transform 0.2s ease;
        }

        .crm-quotation-gen .q-day-head.collapsed .toggle-icon {
            transform: rotate(-90deg);
        }

        .crm-quotation-gen .q-wizard-step[data-q-step="5"] .q-accordion-item {
            border: 1px solid var(--q-border);
            border-radius: var(--q-radius-sm);
            margin-bottom: 0.75rem;
            overflow: hidden;
            background: var(--q-card-bg);
            box-shadow: var(--q-shadow-sm);
        }

        .crm-quotation-gen .q-wizard-step[data-q-step="5"] .q-accordion-head {
            background: var(--q-border-light);
            border-bottom: 1px solid var(--q-border-light);
        }

        .crm-quotation-gen .q-wizard-step[data-q-step="5"] .note-editor.note-frame {
            border: 1px solid var(--q-border);
            border-radius: var(--q-radius-sm);
            overflow: hidden;
            box-shadow: var(--q-shadow-sm);
        }

        .crm-quotation-gen .q-wizard-step[data-q-step="5"] .note-toolbar {
            background: var(--q-border-light) !important;
            border-bottom: 1px solid var(--q-border-light) !important;
        }

        .crm-quotation-gen .q-day-card {
            border: 1px solid var(--q-border);
            border-radius: var(--q-radius-sm);
            margin-bottom: 0.75rem;
            overflow: hidden;
            box-shadow: var(--q-shadow-sm);
            background: var(--q-card-bg);
        }

        .crm-quotation-gen .q-day-head {
            background: var(--q-border-light);
            padding: 0.55rem 0.85rem;
            font-weight: 700;
            font-size: 0.82rem;
            color: var(--q-text);
            border-bottom: 1px solid var(--q-border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
        }

        .crm-quotation-gen .q-day-head-label {
            min-width: 0;
        }

        .crm-quotation-gen .q-day-ai-suggest {
            flex-shrink: 0;
            border-radius: 999px;
            border: 1px solid var(--q-border);
            background: var(--q-accent-soft);
            color: var(--q-accent-dark);
            font-weight: 600;
            font-size: 0.72rem;
            padding: 0.2rem 0.65rem;
            line-height: 1.3;
        }

        .crm-quotation-gen .q-day-ai-suggest:hover:not(:disabled) {
            background: var(--q-accent-soft);
            border-color: var(--q-accent);
            color: var(--q-accent-dark);
        }

        .crm-quotation-gen .q-day-ai-suggest:disabled {
            opacity: 0.7;
            cursor: wait;
        }

        .crm-quotation-gen .q-day-title-row {
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }

        .crm-quotation-gen .q-day-title-row .q-day-title {
            flex: 1 1 auto;
            min-width: 0;
        }

        .crm-quotation-gen .q-itinerary-suppliers {
            margin-top: 0.15rem;
        }

        .crm-quotation-gen .q-itinerary-suppliers-hd {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            margin-bottom: 0.55rem;
            flex-wrap: wrap;
        }

        .crm-quotation-gen .q-itin-supplier-row {
            margin-bottom: 0.55rem;
        }

        .crm-quotation-gen .q-itin-supplier-row .q-itin-supplier-remove {
            height: 38px;
            width: 38px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            margin-bottom: 0;
        }

        .crm-quotation-gen .q-itin-supplier-row .q-itin-rate {
            -moz-appearance: textfield;
            appearance: textfield;
        }

        .crm-quotation-gen .q-itin-supplier-row .q-itin-rate::-webkit-outer-spin-button,
        .crm-quotation-gen .q-itin-supplier-row .q-itin-rate::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        .crm-quotation-gen .q-day-ai-btn {
            flex: 0 0 auto;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border: 0;
            background: linear-gradient(135deg, #0d9488 0%, #0f766e 100%);
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 8px rgba(15, 118, 110, 0.28);
            padding: 0;
        }

        .crm-quotation-gen .q-day-ai-btn:hover:not(:disabled) {
            background: linear-gradient(135deg, #14b8a6 0%, #0d9488 100%);
            color: #fff;
        }

        .crm-quotation-gen .q-day-ai-btn:disabled {
            opacity: 0.7;
            cursor: wait;
        }

        .q-day-ai-modal .modal-content {
            border: 0;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 12px 40px rgba(15, 23, 42, 0.15);
        }

        .q-day-ai-modal .modal-header {
            border-bottom: 1px solid #e2e8f0;
            align-items: flex-start;
            padding: 1rem 1.15rem;
        }

        .q-day-ai-modal-hd {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
        }

        .q-day-ai-modal-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: linear-gradient(135deg, #0d9488 0%, #0f766e 100%);
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .q-day-ai-modal .modal-title {
            font-size: 1rem;
            font-weight: 700;
            color: #0f766e;
        }

        .q-day-ai-modal-sub {
            font-size: 0.8rem;
            color: #64748b;
            margin-top: 0.15rem;
        }

        .q-day-ai-modal #qDayAiPrompt {
            border-radius: 10px;
            min-height: 110px;
            resize: vertical;
        }

        .q-day-ai-modal #qDayAiPrompt:focus {
            border-color: #5eead4;
            box-shadow: 0 0 0 0.2rem rgba(13, 148, 136, 0.15);
        }

        .q-day-ai-modal .q-day-ai-generate {
            background: linear-gradient(135deg, #0d9488 0%, #0f766e 100%);
            border: 0;
            color: #fff;
            font-weight: 600;
            border-radius: 999px;
            padding: 0.4rem 1.1rem;
        }

        .q-day-ai-modal .q-day-ai-generate:hover:not(:disabled) {
            background: linear-gradient(135deg, #14b8a6 0%, #0d9488 100%);
            color: #fff;
        }

        .q-day-ai-modal .q-day-ai-generate:disabled {
            opacity: 0.75;
            cursor: wait;
        }

        .crm-quotation-gen .q-day-body {
            padding: 0.5rem 0.65rem;
        }

        .crm-quotation-gen .q-day-body .note-editor {
            max-width: 100%;
        }

        .crm-quotation-gen .q-day-body .note-toolbar {
            background: var(--q-border-light);
            padding: 0.2rem;
        }

        .crm-quotation-gen .q-day-image-col {
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
            height: 100%;
        }

        .crm-quotation-gen .q-day-image-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.35rem;
        }

        .crm-quotation-gen .q-day-image-actions .btn {
            font-size: 0.72rem;
            padding: 0.3rem 0.55rem;
            border-radius: 999px;
        }

        .crm-quotation-gen .q-clear-day-image {
            padding-left: 0.45rem !important;
            padding-right: 0.45rem !important;
        }

        .qii-image-search {
            --qii-primary: #0ea5e9;
            --qii-primary-dark: #0284c7;
            --qii-bg: #f8fafc;
            --qii-border: #e2e8f0;
        }

        .qii-image-search .qii-modal-content {
            border: 0;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 12px 40px rgba(15, 23, 42, 0.15);
        }

        .qii-image-search .qii-modal-hd {
            background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
            color: #fff;
            border: 0;
            padding: 1rem 1.15rem;
        }

        .qii-image-search .qii-hd-main {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
        }

        .qii-image-search .qii-hd-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .qii-image-search .qii-hd-sub {
            font-size: 0.78rem;
            opacity: 0.9;
        }

        .qii-image-search .qii-close-btn {
            color: #fff;
            opacity: 0.85;
            text-shadow: none;
        }

        .qii-image-search .qii-search-body {
            background: var(--qii-bg);
            padding: 1rem 1.15rem;
            max-height: 65vh;
            overflow-y: auto;
        }

        .qii-image-search .qii-search-bar {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 0.65rem;
        }

        .qii-image-search .qii-search-input {
            border-radius: 10px;
            border-color: var(--qii-border);
        }

        .qii-image-search .qii-search-btn {
            background: var(--qii-primary);
            color: #fff;
            border: 0;
            border-radius: 10px;
            min-width: 44px;
        }

        .qii-image-search .qii-source-note {
            font-size: 0.72rem;
            color: #64748b;
            margin-bottom: 0.55rem;
        }

        .qii-image-search .qii-results-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.65rem;
        }

        .qii-image-search .qii-img-item {
            border: 1px solid var(--qii-border);
            border-radius: 10px;
            overflow: hidden;
            padding: 0;
            background: #fff;
            cursor: pointer;
            text-align: left;
            transition: box-shadow 0.15s, transform 0.15s, border-color 0.15s;
        }

        .qii-image-search .qii-img-item:hover {
            border-color: #7dd3fc;
            box-shadow: 0 6px 16px rgba(14, 165, 233, 0.15);
            transform: translateY(-1px);
        }

        .qii-image-search .qii-img-thumb {
            display: block;
            width: 100%;
            height: 110px;
            object-fit: cover;
            background-color: #e2e8f0;
        }

        .qii-image-search .qii-img-caption {
            display: block;
            font-size: 0.68rem;
            color: #475569;
            padding: 0.35rem 0.45rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .qii-image-search .qii-empty,
        .qii-image-search .qii-loading {
            grid-column: 1 / -1;
            text-align: center;
            padding: 2rem 1rem;
            color: #64748b;
            font-size: 0.85rem;
        }

        .qii-image-search .qii-empty i {
            display: block;
            font-size: 1.75rem;
            margin-bottom: 0.5rem;
            opacity: 0.45;
        }

        .qii-image-search .qii-modal-ft {
            border-top: 1px solid var(--qii-border);
            padding: 0.75rem 1.15rem;
        }

        .qii-image-search .qii-btn-ghost {
            border: 1px solid var(--qii-border);
            border-radius: 999px;
            font-weight: 600;
            font-size: 0.82rem;
        }

        @media (max-width: 575px) {
            .qii-image-search .qii-results-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        .crm-quotation-gen .q-img-preview-wrap {
            flex: 1 1 auto;
            min-height: 140px;
            border: 1px dashed var(--q-border);
            border-radius: var(--q-radius);
            background: var(--q-border-light);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0.35rem;
            overflow: hidden;
        }

        .crm-quotation-gen .q-img-preview {
            max-width: 100%;
            max-height: 200px;
            border-radius: var(--q-radius);
            display: none;
        }

        .crm-quotation-gen .q-img-preview-empty {
            text-align: center;
            padding: 0.35rem;
            font-size: 0.75rem;
            color: var(--q-text-muted);
        }

        .crm-quotation-gen .q-img-preview-wrap.has-image .q-img-preview-empty {
            display: none;
        }

        .crm-quotation-gen .q-cost-sheet .form-group.row {
            margin-bottom: 0.3rem;
        }

        .crm-quotation-gen .q-cost-sheet .q-custom-cost {
            margin-bottom: 0.3rem !important;
        }

        .crm-quotation-gen .q-cost-label {
            text-align: right;
            color: var(--q-text-muted);
            font-size: 0.75rem;
            font-weight: 500;
            padding-right: 0.5rem;
            padding-top: 0.28rem;
            white-space: nowrap;
        }

        .crm-quotation-gen .q-cost-sheet .cost-input {
            max-width: 130px;
        }

        .crm-quotation-gen .q-cost-totals .cost-input {
            max-width: 150px;
        }

        .crm-quotation-gen .q-tour-date-col {
            flex: 0 0 auto;
            width: auto;
            min-width: 148px;
            max-width: 160px;
        }

        .crm-quotation-gen .q-tour-pax-col {
            flex: 0 0 auto;
            width: auto;
            min-width: 108px;
            max-width: 120px;
        }

        .crm-quotation-gen .q-tour-pax-col:last-child {
            min-width: 118px;
            max-width: 130px;
        }

        .crm-quotation-gen .q-tour-pax-col .q-label,
        .crm-quotation-gen .q-tour-date-col .q-label,
        .crm-quotation-gen .q-tour-dest-col .q-label {
            white-space: nowrap;
        }

        .crm-quotation-gen .q-tour-pax-col .q-label {
            font-size: 0.72rem;
            letter-spacing: 0;
        }

        .crm-quotation-gen .q-tour-dest-col {
            flex: 1 1 180px;
            min-width: 140px;
        }

        .crm-quotation-gen .q-cost-totals .form-control[readonly] {
            background: linear-gradient(180deg, #eff6ff 0%, #dbeafe 100%);
            border-color: #93c5fd;
            color: #1e40af;
            font-weight: 800;
        }

        .crm-quotation-gen .q-usd-box {
            border: 1px solid var(--q-border);
            border-radius: var(--q-radius-sm);
            padding: 0.85rem 1rem;
            background: var(--q-card-bg);
            height: 100%;
            box-shadow: var(--q-shadow-sm);
        }

        .crm-quotation-gen .q-repeat-row {
            border: 1px solid var(--q-border);
            border-left: 3px solid var(--q-primary);
            border-radius: var(--q-radius-sm);
            padding: 0.85rem 1rem 0.75rem;
            margin-bottom: 0.75rem;
            position: relative;
            background: var(--q-card-bg);
            box-shadow: var(--q-shadow-sm);
            transition: box-shadow 0.15s ease, border-color 0.15s ease;
        }

        .crm-quotation-gen .q-repeat-row:hover {
            box-shadow: var(--q-shadow-md);
            border-color: var(--q-border);
        }

        .crm-quotation-gen .q-wizard-step[data-q-step="2"] .q-flight-card {
            padding: 0.15rem 0.1rem 0.55rem;
        }

        .crm-quotation-gen .q-flight-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
            margin-bottom: 1rem;
            padding: 0 0 0 0.85rem;
            border-bottom: 0;
            position: relative;
        }

        .crm-quotation-gen .q-flight-head::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0.15rem;
            bottom: 0.35rem;
            width: 4px;
            border-radius: 4px;
            background: #e11d2e;
        }

        .crm-quotation-gen .q-flight-head .q-section-title {
            margin: 0;
            padding: 0;
            border: 0;
            font-size: 1.2rem;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: -0.01em;
        }

        .crm-quotation-gen .q-flight-subtitle {
            margin: 0.28rem 0 0;
            font-size: 0.82rem;
            color: #94a3b8;
            font-weight: 500;
        }

        .crm-quotation-gen .q-flight-actions {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.45rem;
        }

        .crm-quotation-gen .q-flight-btn {
            border-radius: 10px;
            font-weight: 700;
            font-size: 0.78rem;
            padding: 0.45rem 0.9rem;
            white-space: nowrap;
        }

        .crm-quotation-gen .q-flight-btn-dark,
        .crm-quotation-gen .q-flight-btn-red,
        .crm-quotation-gen .q-flight-btn.is-active {
            background: #e11d2e;
            border-color: #e11d2e;
            color: #fff;
        }

        .crm-quotation-gen .q-flight-btn-dark:hover,
        .crm-quotation-gen .q-flight-btn-red:hover,
        .crm-quotation-gen .q-flight-btn.is-active:hover {
            background: #c41e20;
            border-color: #c41e20;
            color: #fff;
        }

        .crm-quotation-gen .q-flight-btn-outline {
            background: #fff;
            border: 1px solid #e2e8f0;
            color: #0f172a;
            cursor: pointer;
        }

        .crm-quotation-gen .q-flight-btn-outline:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
            color: #0f172a;
        }

        .crm-quotation-gen .q-flight-table-wrap {
            border: 0;
            border-radius: 0;
            overflow: visible;
            background: transparent;
        }

        .crm-quotation-gen .q-flight-rows {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .crm-quotation-gen .q-flight-journey-card {
            background: #f8fafc;
            border: 1px solid #dbeafe;
            border-radius: 16px;
            padding: 0.85rem;
            box-shadow: 0 10px 28px rgba(15, 23, 42, 0.05);
        }

        .crm-quotation-gen .q-flight-journey-head {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.55rem 0.85rem;
            margin-bottom: 0.75rem;
            padding: 0.55rem 0.75rem;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
        }

        .crm-quotation-gen .q-flight-journey-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.18rem 0.55rem;
            border-radius: 999px;
            background: #dbeafe;
            color: #1d4ed8;
            font-size: 0.72rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .crm-quotation-gen .q-flight-journey-route {
            font-size: 0.84rem;
            font-weight: 700;
            color: #0f172a;
            min-width: 0;
        }

        .crm-quotation-gen .q-flight-journey-meta {
            font-size: 0.76rem;
            color: #64748b;
            font-weight: 600;
        }

        .crm-quotation-gen .q-flight-journey-fare {
            margin-left: auto;
            font-size: 0.95rem;
            font-weight: 800;
            color: #e11d2e;
        }

        .crm-quotation-gen .q-flight-journey-delete {
            width: 34px;
            height: 34px;
            border: 1px solid #fecaca;
            border-radius: 10px;
            background: #fff;
            color: #dc2626;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            margin-left: 0.35rem;
        }

        .crm-quotation-gen .q-flight-journey-delete:hover {
            background: #fef2f2;
            border-color: #f87171;
            color: #b91c1c;
        }

        .crm-quotation-gen .q-flight-journey-body {
            display: flex;
            flex-direction: column;
            gap: 0.65rem;
        }

        .crm-quotation-gen .q-flight-journey-body .q-flight-segment-card {
            box-shadow: none;
        }

        .crm-quotation-gen .q-flight-segment-card {
            background: #fff;
            border: 1px solid #e8edf3;
            border-radius: 14px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
            padding: 0.85rem 1rem;
            overflow-x: auto;
        }

        .crm-quotation-gen .q-flight-segment-row {
            display: grid;
            grid-template-columns:
                minmax(120px, 1.05fr) 40px minmax(120px, 1.05fr)
                minmax(168px, 1.35fr)
                minmax(150px, 1.15fr) minmax(150px, 1.15fr)
                minmax(100px, 0.85fr) minmax(110px, 0.95fr) 40px;
            gap: 0.55rem;
            align-items: end;
            min-width: 980px;
        }

        .crm-quotation-gen .q-ft-col {
            min-width: 0;
            display: flex;
            flex-direction: column;
            gap: 0.32rem;
        }

        .crm-quotation-gen .q-ft-col-swap,
        .crm-quotation-gen .q-ft-col-action {
            align-items: center;
        }

        .crm-quotation-gen .q-ft-label {
            font-size: 0.66rem;
            font-weight: 700;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            line-height: 1.2;
            white-space: nowrap;
        }

        .crm-quotation-gen .q-flight-place,
        .crm-quotation-gen .q-flight-airline-combo,
        .crm-quotation-gen .q-flight-datetime,
        .crm-quotation-gen .q-flight-fare {
            display: flex;
            align-items: center;
            gap: 0.35rem;
            min-width: 0;
            min-height: 38px;
            padding: 0.2rem 0.55rem;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            background: #fff;
        }

        .crm-quotation-gen .q-flight-place:focus-within,
        .crm-quotation-gen .q-flight-airline-combo:focus-within,
        .crm-quotation-gen .q-flight-datetime:focus-within,
        .crm-quotation-gen .q-flight-fare:focus-within {
            border-color: #f87171;
            box-shadow: 0 0 0 3px rgba(225, 29, 46, 0.12);
        }

        .crm-quotation-gen .q-flight-place-icon,
        .crm-quotation-gen .q-flight-datetime > i,
        .crm-quotation-gen .q-flight-fare > span {
            color: #94a3b8;
            font-size: 0.78rem;
            flex-shrink: 0;
        }

        .crm-quotation-gen .q-flight-place-icon.is-arrive {
            transform: rotate(90deg);
        }

        .crm-quotation-gen .q-flight-airline-logo {
            width: 22px;
            height: 22px;
            border-radius: 50%;
            background: #eff6ff;
            color: #2563eb;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.65rem;
            flex-shrink: 0;
        }

        .crm-quotation-gen .q-flight-airline-sep {
            color: #cbd5e1;
            font-weight: 700;
            flex-shrink: 0;
        }

        .crm-quotation-gen .q-flight-segment-row .form-control {
            border: 0 !important;
            border-radius: 0 !important;
            box-shadow: none !important;
            background: transparent !important;
            font-size: 0.8rem !important;
            height: auto !important;
            min-height: 0 !important;
            padding: 0.15rem 0 !important;
            min-width: 0;
            color: #0f172a;
        }

        .crm-quotation-gen .q-ft-col-supplier .form-control {
            border: 1px solid #e2e8f0 !important;
            border-radius: 10px !important;
            background: #fff !important;
            min-height: 38px !important;
            height: 38px !important;
            padding: 0.2rem 0.55rem !important;
            font-size: 0.8rem !important;
        }

        .crm-quotation-gen .q-ft-col-supplier .form-control:focus {
            border-color: #f87171 !important;
            box-shadow: 0 0 0 3px rgba(225, 29, 46, 0.12) !important;
        }

        /* Searchable supplier Select2 */
        .crm-quotation-gen .select2-container {
            width: 100% !important;
            margin: 0 !important;
            vertical-align: middle;
        }
        .crm-quotation-gen .q-ft-col-supplier .select2-container {
            display: block;
            height: 38px;
        }
        .crm-quotation-gen .select2-container--default .select2-selection--single,
        .crm-quotation-gen .q-supplier-s2-selection.select2-selection--single {
            height: 38px !important;
            min-height: 38px !important;
            max-height: 38px !important;
            border: 1px solid #e2e8f0 !important;
            border-radius: 10px !important;
            background: #fff !important;
            display: flex !important;
            align-items: center !important;
            justify-content: flex-start !important;
            padding: 0 !important;
            box-sizing: border-box !important;
            position: relative !important;
        }
        .crm-quotation-gen .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 1.25 !important;
            padding: 0 1.85rem 0 0.55rem !important;
            margin: 0 !important;
            color: #0f172a !important;
            font-size: 0.8rem !important;
            float: none !important;
            display: flex !important;
            align-items: center !important;
            justify-content: flex-start !important;
            width: 100% !important;
            height: 100% !important;
            box-sizing: border-box !important;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .crm-quotation-gen .select2-container--default .select2-selection--single .select2-selection__arrow {
            top: 0 !important;
            bottom: 0 !important;
            right: 2px !important;
            width: 24px !important;
            height: auto !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            margin: 0 !important;
        }
        .crm-quotation-gen .select2-container--default .select2-selection--single .select2-selection__arrow b {
            margin: 0 !important;
            position: static !important;
            border-width: 5px 4px 0 4px !important;
        }
        .crm-quotation-gen .select2-container--default .select2-selection--single .select2-selection__placeholder {
            color: #94a3b8 !important;
            line-height: inherit !important;
            margin: 0 !important;
            padding: 0 !important;
        }
        .crm-quotation-gen .select2-container--default.select2-container--focus .select2-selection--single,
        .crm-quotation-gen .select2-container--default.select2-container--open .select2-selection--single {
            border-color: #f87171 !important;
            box-shadow: 0 0 0 3px rgba(225, 29, 46, 0.12) !important;
        }
        .crm-quotation-gen .select2-container--default .select2-selection--single .select2-selection__clear {
            display: none !important;
        }
        .select2-container--open .select2-dropdown.q-supplier-s2-dropdown,
        .select2-dropdown.q-supplier-s2-dropdown {
            border: 1px solid #e2e8f0 !important;
            border-radius: 12px !important;
            overflow: hidden;
            box-shadow: 0 14px 34px rgba(15, 23, 42, 0.16);
            z-index: 3000;
        }
        /* Search lives in the supplier input field, not inside the dropdown. */
        .q-supplier-s2-dropdown.q-supplier-inline-search .select2-search--dropdown {
            display: none !important;
            padding: 0 !important;
            margin: 0 !important;
            height: 0 !important;
            border: 0 !important;
            overflow: hidden !important;
        }
        .crm-quotation-gen .select2-container--open .q-supplier-s2-selection.q-supplier-searching {
            position: relative !important;
        }
        .crm-quotation-gen .select2-container--open .q-supplier-s2-selection .select2-selection__rendered.q-supplier-search-host {
            color: transparent !important;
            text-shadow: none !important;
        }
        .crm-quotation-gen .select2-container--open .q-supplier-s2-selection .select2-selection__rendered.q-supplier-search-host .select2-selection__placeholder {
            color: transparent !important;
        }
        .crm-quotation-gen .q-supplier-inline-search-field.select2-search__field {
            position: absolute !important;
            left: 0 !important;
            top: 0 !important;
            right: 24px !important;
            bottom: 0 !important;
            width: auto !important;
            height: 100% !important;
            margin: 0 !important;
            border: 0 !important;
            border-radius: 10px !important;
            padding: 0 0.55rem !important;
            background: transparent !important;
            box-shadow: none !important;
            outline: none !important;
            font-size: 0.8rem !important;
            line-height: 38px !important;
            color: #0f172a !important;
            z-index: 2;
        }
        .crm-quotation-gen .select2-container--open .q-supplier-s2-selection .select2-selection__arrow {
            z-index: 3;
        }
        .q-supplier-s2-dropdown .select2-results__option {
            font-size: 0.82rem;
            padding: 0.45rem 0.7rem;
        }
        .q-supplier-s2-dropdown .select2-results__option--highlighted[aria-selected],
        .q-supplier-s2-dropdown .select2-results__option--highlighted {
            background: #fee2e2 !important;
            color: #991b1b !important;
        }
        .q-supplier-s2-dropdown .select2-results__option[aria-selected="true"] {
            background: #f8fafc;
            color: #0f172a;
        }
        .select2-dropdown.q-supplier-s2-dropdown.has-create-action {
            display: flex;
            flex-direction: column;
            padding-bottom: 0;
        }
        .select2-dropdown.q-supplier-s2-dropdown.has-create-action .select2-results {
            flex: 1 1 auto;
            max-height: 220px;
            overflow-y: auto;
        }
        .q-supplier-create-footer {
            flex: 0 0 auto;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.45rem;
            width: 100%;
            margin: 0;
            padding: 0.7rem 0.85rem;
            border: 0;
            border-top: 1px solid #fee2e2;
            background: linear-gradient(180deg, #fff7f7 0%, #fff1f2 100%);
            color: #e11d2e;
            font-size: 0.84rem;
            font-weight: 700;
            cursor: pointer;
            text-align: center;
            transition: background 0.15s ease, color 0.15s ease;
        }
        .q-supplier-create-footer:hover,
        .q-supplier-create-footer:focus {
            background: #fee2e2;
            color: #be123c;
            outline: none;
        }
        .q-supplier-create-footer i {
            font-size: 0.95rem;
        }

        .crm-quotation-gen .q-flight-airline-combo .f-name {
            flex: 1.1;
        }

        .crm-quotation-gen .q-flight-airline-combo .f-fl-no {
            flex: 0.9;
            max-width: 5.5rem;
        }

        .crm-quotation-gen .q-flight-datetime .f-dep-date,
        .crm-quotation-gen .q-flight-datetime .f-arr-date {
            flex: 1.15;
            min-width: 0;
        }

        .crm-quotation-gen .q-flight-datetime .f-dep-time,
        .crm-quotation-gen .q-flight-datetime .f-arr-time {
            flex: 0.85;
            min-width: 0;
            max-width: 5.2rem;
        }

        .crm-quotation-gen .q-flight-datetime input[type="date"]::-webkit-calendar-picker-indicator,
        .crm-quotation-gen .q-flight-datetime input[type="time"]::-webkit-calendar-picker-indicator {
            opacity: 0;
            display: none;
            -webkit-appearance: none;
            width: 0;
            height: 0;
            margin: 0;
            padding: 0;
        }

        .crm-quotation-gen .q-flight-datetime input[type="time"]::-webkit-clear-button,
        .crm-quotation-gen .q-flight-datetime input[type="date"]::-webkit-clear-button {
            display: none;
            -webkit-appearance: none;
        }

        .crm-quotation-gen .q-flight-fare .f-fare {
            text-align: right;
            font-weight: 700;
        }

        .crm-quotation-gen .q-flight-swap {
            width: 34px;
            height: 34px;
            border: 1px solid #e2e8f0;
            border-radius: 50%;
            background: #fff;
            color: #64748b;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.78rem;
            margin-bottom: 0.1rem;
        }

        .crm-quotation-gen .q-flight-swap:hover {
            background: #fff1f2;
            color: #e11d2e;
            border-color: #fecaca;
        }

        .crm-quotation-gen .q-flight-remove {
            width: 34px;
            height: 34px;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            background: #fff;
            color: #dc2626;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 0.1rem;
        }

        .crm-quotation-gen .q-flight-remove:hover {
            background: #fef2f2;
            border-color: #fecaca;
        }

        .crm-quotation-gen .q-flight-layover {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            margin: 0.15rem 0 0.55rem;
            padding: 0.45rem 0.75rem;
            background: #fffbeb;
            border: 1px solid #fde68a;
            border-radius: 10px;
            color: #92400e;
            font-size: 0.78rem;
            font-weight: 600;
        }

        .crm-quotation-gen .q-flight-layover-text {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            min-width: 0;
        }

        .crm-quotation-gen .q-flight-layover-text strong {
            font-weight: 800;
            color: #78350f;
        }

        .crm-quotation-gen .q-flight-add-wrap {
            display: flex;
            justify-content: center;
            margin-top: 0.95rem;
        }

        .crm-quotation-gen .q-flight-add-segment {
            border: 1px solid #e2e8f0;
            background: #fff;
            color: #0f172a;
            border-radius: 10px;
            font-weight: 700;
            padding: 0.45rem 1rem;
        }

        .crm-quotation-gen .q-flight-add-segment:hover {
            background: #fff1f2;
            border-color: #fecaca;
            color: #e11d2e;
        }

        .crm-quotation-gen .q-flight-upload-label {
            text-align: right;
            margin-top: 0.35rem;
            min-height: 1rem;
        }

        .crm-quotation-gen .q-flight-segment-row input[type="number"] {
            -moz-appearance: textfield;
            appearance: textfield;
        }

        .crm-quotation-gen .q-flight-segment-row input[type="number"]::-webkit-outer-spin-button,
        .crm-quotation-gen .q-flight-segment-row input[type="number"]::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }


        .qfs-flight-search .qfs-layover {
            border-bottom: 1px dashed #ddd;
            margin-bottom: 8px;
            padding-bottom: 8px;
            font-size: 12px;
            color: #856404;
            background: #fff9e6;
            border-radius: var(--q-radius);
            padding: 5px 8px;
            margin-top: -2px;
            margin-bottom: 8px;
        }

        .crm-quotation-gen .q-hotel-fields {
            display: flex;
            flex-wrap: wrap;
            gap: 0.3rem 0.45rem;
        }

        .crm-quotation-gen .q-hotel-field.q-hotel-field-supplier {
            flex: 1 1 220px;
            min-width: 180px;
            max-width: 320px;
        }

        .crm-quotation-gen .q-hotel-rate-row {
            margin-top: 0.15rem;
        }

        .crm-quotation-gen .q-hotel-rate-row .form-group {
            width: 100%;
        }

        .crm-quotation-gen .h-rate {
            -moz-appearance: textfield;
            appearance: textfield;
        }

        .crm-quotation-gen .h-rate::-webkit-outer-spin-button,
        .crm-quotation-gen .h-rate::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        .crm-quotation-gen .q-hotel-cat-toolbar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
            margin-bottom: 0.75rem;
        }

        .crm-quotation-gen .q-hotel-cat-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 0.35rem;
            align-items: center;
            min-width: 0;
            flex: 1;
        }

        .crm-quotation-gen .q-hotel-cat-tab {
            border: 1px solid var(--q-border);
            background: var(--q-card-bg);
            color: var(--q-text);
            border-radius: 999px;
            padding: 0.28rem 0.75rem;
            font-size: 0.78rem;
            font-weight: 700;
            cursor: pointer;
            line-height: 1.2;
        }

        .crm-quotation-gen .q-hotel-cat-tab.is-active {
            background: #7c3aed;
            border-color: #7c3aed;
            color: #fff;
        }

        .crm-quotation-gen .q-hotel-category {
            display: none;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            background: #fafbfc;
            padding: 0.75rem;
            margin-bottom: 0.65rem;
        }

        .crm-quotation-gen .q-hotel-category.is-active {
            display: block;
        }

        .crm-quotation-gen .q-hotel-category-hd {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: flex-end;
            gap: 0.45rem;
            margin-bottom: 0.65rem;
        }

        .crm-quotation-gen .q-hotel-category-hd .q-hotel-cat-label {
            display: none;
        }

        .crm-quotation-gen .q-hotel-category-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.35rem;
            align-items: center;
        }

        .crm-quotation-gen .q-hotel-cost-row {
            display: flex;
            flex-wrap: nowrap;
            align-items: center;
            gap: 0.45rem;
        }

        .crm-quotation-gen .q-hotel-option-select {
            flex: 1 1 55%;
            min-width: 140px;
            max-width: 260px;
            height: calc(1.5em + 0.5rem + 2px);
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }

        .crm-quotation-gen .q-hotel-cost-row .q-cost[data-key="hotel"] {
            flex: 1 1 45%;
            min-width: 110px;
        }

        .crm-quotation-gen .q-hotel-field {
            flex: 1 1 88px;
            min-width: 82px;
        }

        .crm-quotation-gen .q-hotel-field.q-hotel-field-wide {
            flex: 2 1 150px;
            min-width: 130px;
        }

        .crm-quotation-gen .q-hotel-field.q-hotel-field-narrow {
            flex: 0 1 62px;
            min-width: 56px;
        }

        .crm-quotation-gen .q-hotel-field.q-hotel-field-meal {
            flex: 1 1 90px;
            min-width: 88px;
        }

        .crm-quotation-gen .q-hotel-combo {
            position: relative;
        }

        .crm-quotation-gen .q-hotel-menu {
            position: absolute;
            top: calc(100% + 2px);
            left: 0;
            right: 0;
            z-index: 1060;
            max-height: 200px;
            overflow-y: auto;
            background: #fff;
            border: 1px solid var(--q-border);
            border-radius: var(--q-radius);
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.1);
        }

        .crm-quotation-gen .q-hotel-menu-item {
            display: block;
            width: 100%;
            padding: 0.35rem 0.55rem;
            border: 0;
            background: transparent;
            color: var(--q-text);
            text-align: left;
            cursor: pointer;
            font-size: 0.8rem;
        }

        .crm-quotation-gen .q-hotel-menu-item:hover,
        .crm-quotation-gen .q-hotel-menu-item:focus {
            background: #f1f5f9;
            outline: none;
        }

        .crm-quotation-gen .q-hotel-menu-empty {
            padding: 0.4rem 0.55rem;
            color: var(--q-text-muted);
            font-size: 0.8rem;
        }

        .crm-quotation-gen .q-hotel-menu-item .q-hotel-menu-sub {
            display: block;
            font-size: 0.72rem;
            color: var(--q-text-muted);
            margin-top: 0.1rem;
        }

        .crm-quotation-gen .q-hotel-menu-divider {
            border-top: 1px solid #e9ecef;
            margin: 0.25rem 0;
        }

        .crm-quotation-gen .q-hotel-menu-item-create {
            color: #007bff;
            font-weight: 600;
        }

        .crm-quotation-gen .q-hotel-menu-item-create:hover,
        .crm-quotation-gen .q-hotel-menu-item-create:focus {
            background: #eef5ff;
            outline: none;
        }

        .crm-quotation-gen .q-city-create-modal .modal-header {
            background: linear-gradient(115deg, #9a121f 0%, #c4121a 100%);
            color: #fff;
            border-bottom: 0;
        }

        .crm-quotation-gen .q-city-create-modal .modal-header .close {
            color: #fff;
            opacity: 0.85;
            text-shadow: none;
        }

        .crm-quotation-gen .q-city-create-modal .label-req::after {
            content: " *";
            color: #dc3545;
        }

        .crm-quotation-gen .q-hotel-field.q-hotel-field-hotel {
            flex: 3 1 220px;
            min-width: 180px;
        }

        .crm-quotation-gen .q-lead-combobox {
            position: relative;
        }

        .crm-quotation-gen .q-lead-menu {
            position: absolute;
            top: calc(100% + 2px);
            left: 0;
            right: 0;
            z-index: 1060;
            max-height: 220px;
            overflow-y: auto;
            background: #fff;
            border: 1px solid var(--q-border);
            border-radius: var(--q-radius);
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.1);
        }

        .crm-quotation-gen .q-lead-item {
            display: block;
            width: 100%;
            padding: 0.4rem 0.6rem;
            border: 0;
            background: transparent;
            color: var(--q-text);
            text-align: left;
            cursor: pointer;
        }

        .crm-quotation-gen .q-lead-item:hover,
        .crm-quotation-gen .q-lead-item:focus {
            background: #f1f5f9;
            outline: none;
        }

        .crm-quotation-gen .q-lead-item-title {
            display: block;
            font-weight: 600;
            font-size: 0.8125rem;
        }

        .crm-quotation-gen .q-lead-item-meta {
            display: block;
            font-size: 0.72rem;
            color: var(--q-text-muted);
            margin-top: 0.05rem;
        }

        .crm-quotation-gen .q-lead-empty {
            padding: 0.45rem 0.6rem;
            color: var(--q-text-muted);
            font-size: 0.8rem;
        }

        .qfs-flight-search .qfs-search-hd {
            background: #f8fafc;
            font-weight: bold;
            border-bottom: 1px solid var(--q-border);
        }

        .qfs-flight-search .qfs-search-body {
            background: #fff;
            border-top: none;
            overflow: visible;
        }

        /* Keep airport suggestions visible outside the modal edge */
        #qfsSearchModal.modal,
        #qfsSearchModal .modal-dialog,
        #qfsSearchModal .modal-content,
        #qfsSearchModal .modal-body,
        #qfsSearchModal .qfs-route-row,
        #qfsSearchModal .qfs-route-field,
        #qfsSearchModal .qfs-airport-field {
            overflow: visible !important;
        }

        .qfs-flight-search .qfs-date-wrapper {
            position: relative;
        }

        .qfs-flight-search .qfs-date-wrapper input {
            padding-right: 30px;
        }

        .qfs-flight-search .qfs-calendar-icon {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .qfs-flight-search .qfs-airport-field {
            position: relative;
        }

        .qfs-flight-search .qfs-route-row {
            display: flex;
            align-items: flex-end;
            gap: 0.45rem;
        }

        .qfs-flight-search .qfs-route-field {
            flex: 1 1 0;
            min-width: 0;
            margin-bottom: 0;
            position: relative;
        }

        .qfs-flight-search .qfs-swap-wrap {
            flex: 0 0 auto;
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 0;
        }

        .qfs-flight-search .qfs-swap-btn {
            width: 38px;
            height: 38px;
            border: 1px solid #e2e8f0;
            border-radius: 50%;
            background: #fff;
            color: #64748b;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            line-height: 1;
        }

        .qfs-flight-search .qfs-swap-btn:hover {
            background: #fff1f2;
            border-color: #fecaca;
            color: #e11d2e;
        }

        [data-theme="dark"] .qfs-flight-search .qfs-swap-btn {
            background: var(--mz-theme-bg-elevated, #2a2e38);
            border-color: var(--mz-theme-border, #454b58);
            color: var(--q-text, #e5e7eb);
        }

        [data-theme="dark"] .qfs-flight-search .qfs-swap-btn:hover {
            background: rgba(225, 29, 46, 0.18);
            border-color: rgba(225, 29, 46, 0.45);
            color: #fecaca;
        }

        .qfs-flight-search .qfs-airport-suggest,
        body > .qfs-airport-suggest {
            position: absolute;
            z-index: 2200;
            left: 0;
            right: 0;
            top: calc(100% + 2px);
            background: #fff;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            max-height: 250px;
            overflow-y: auto;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.18);
        }

        body > .qfs-airport-suggest.qfs-airport-suggest-open {
            position: fixed !important;
            right: auto !important;
        }

        [data-theme="dark"] .qfs-flight-search .qfs-airport-suggest,
        [data-theme="dark"] body > .qfs-airport-suggest {
            background: var(--mz-theme-bg-elevated, #2a2e38);
            border-color: var(--mz-theme-border, #454b58);
            color: var(--q-text, #e5e7eb);
        }

        .qfs-flight-search .qfs-suggest-item:hover,
        body > .qfs-airport-suggest .qfs-suggest-item:hover {
            background: #f1f5f9;
        }

        [data-theme="dark"] .qfs-flight-search .qfs-suggest-item:hover,
        [data-theme="dark"] body > .qfs-airport-suggest .qfs-suggest-item:hover {
            background: rgba(255, 255, 255, 0.06);
        }

        .qfs-flight-search .qfs-search-btn {
            background-color: var(--q-primary);
            border-color: var(--q-primary);
        }

        .qfs-flight-search .qfs-select-flight-card:hover {
            background-color: #eff6ff !important;
        }

        .qfs-flight-search .qfs-pagination {
            gap: 0.5rem;
            padding: 4px 2px;
        }

        .qfs-flight-search .qfs-pagination .btn {
            min-width: 2rem;
        }

        .qfs-flight-search .qfs-select-flight-card .card-body {
            gap: 0.75rem;
        }

        .qfs-flight-search .qfs-flight-main {
            min-width: 0;
            flex: 1 1 auto;
        }

        .qfs-flight-search .qfs-price-col {
            flex: 0 0 110px;
            width: 110px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            align-self: stretch;
            border-left: 1px solid #e5e7eb;
            padding-left: 0.65rem;
            margin-left: 0.15rem;
        }

        .qfs-flight-search .qfs-price-value {
            font-weight: 700;
            font-size: 1rem;
            color: #e31b23;
            line-height: 1.2;
            white-space: nowrap;
        }

        .crm-quotation-gen .q-repeat-row .q-remove {
            position: absolute;
            top: 0.25rem;
            right: 0.25rem;
            z-index: 3;
            padding: 0.1rem 0.35rem;
            line-height: 1;
            font-size: 0.7rem;
        }

        .crm-quotation-gen .btn-q-primary {
            background: linear-gradient(180deg, var(--q-primary) 0%, var(--q-primary-dark) 100%);
            border-color: var(--q-primary-dark);
            color: #fff;
            font-weight: 600;
            font-size: 0.78rem;
            padding: 0.38rem 0.85rem;
            border-radius: 999px;
            box-shadow: 0 2px 8px rgba(37, 99, 235, 0.22);
            transition: transform 0.12s ease, box-shadow 0.12s ease;
        }

        .crm-quotation-gen .btn-q-primary:hover {
            background: linear-gradient(180deg, var(--q-primary-dark) 0%, #1e40af 100%);
            border-color: #1e40af;
            color: #fff;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.28);
        }

        .crm-quotation-gen .btn-outline-secondary {
            border-radius: 999px;
            font-weight: 600;
            font-size: 0.78rem;
            padding: 0.38rem 0.85rem;
            border-color: var(--q-border);
            color: #475569;
            background: #fff;
        }

        .crm-quotation-gen .btn-outline-secondary:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
            color: var(--q-text);
        }

        .crm-quotation-gen .btn-preview {
            background: linear-gradient(180deg, var(--q-accent) 0%, var(--q-accent-dark) 100%);
            border-color: var(--q-accent-dark);
            color: #fff;
            font-weight: 700;
            font-size: 0.84rem;
            padding: 0.5rem 1.15rem;
            border-radius: 999px;
            box-shadow: 0 2px 10px rgba(13, 148, 136, 0.25);
        }

        .crm-quotation-gen .btn-preview:hover {
            background: linear-gradient(180deg, var(--q-accent-dark) 0%, #115e59 100%);
            border-color: #115e59;
            color: #fff;
            transform: translateY(-1px);
        }

        .crm-quotation-gen .btn-save {
            background: linear-gradient(180deg, var(--q-save) 0%, #0369a1 100%);
            border-color: #0369a1;
            color: #fff;
            font-weight: 700;
            font-size: 0.84rem;
            padding: 0.5rem 1.25rem;
            border-radius: 999px;
            box-shadow: 0 2px 10px rgba(2, 132, 199, 0.28);
        }

        .crm-quotation-gen .btn-save:hover {
            background: linear-gradient(180deg, #0369a1 0%, #075985 100%);
            border-color: #075985;
            color: #fff;
            transform: translateY(-1px);
        }

        .crm-quotation-gen .q-actions-bar {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.55rem;
            margin-top: 1rem;
            padding: 1rem 1.1rem;
            border-top: none;
            background: linear-gradient(180deg, #f8fafc 0%, #fff 100%);
            border: 1px solid var(--q-border);
            border-radius: var(--q-radius-sm);
        }

        .crm-quotation-gen .q-check-row {
            display: flex;
            flex-wrap: wrap;
            gap: 0.85rem 1.5rem;
            margin-bottom: 0.65rem;
            padding: 0.55rem 0.75rem;
            background: #f8fafc;
            border-radius: var(--q-radius-sm);
            border: 1px solid var(--q-border-light);
        }

        .crm-quotation-gen .q-check-row .custom-control-label {
            font-size: 0.82rem;
            font-weight: 600;
            color: #475569;
        }

        .crm-quotation-gen .q-check-row .custom-control {
            margin-bottom: 0;
            min-height: 1.2rem;
        }

        .crm-quotation-gen .q-check-row .custom-control-label {
            font-size: 0.8rem;
            color: var(--q-text-muted);
            padding-top: 0.1rem;
        }

        .crm-quotation-gen .input-group-text {
            font-size: 0.75rem;
            padding: 0.2rem 0.45rem;
        }

        .crm-quotation-gen #qAlert .alert {
            padding: 0.4rem 0.65rem;
            margin-bottom: 0.4rem;
            font-size: 0.8125rem;
        }

        .crm-quotation-gen .q-tour-fields {
            flex-wrap: nowrap;
        }

        @media (max-width: 767.98px) {
            .crm-quotation-gen .q-tour-fields {
                flex-wrap: wrap;
            }

            .crm-quotation-gen .q-tour-pax-col {
                max-width: calc(33.333% - 0.5rem);
            }

            .crm-quotation-gen .q-tour-date-col {
                max-width: 100%;
                flex: 1 1 100%;
            }
        }

        @media (min-width: 992px) {
            .crm-quotation-gen .q-pricing-grid {
                display: grid;
                grid-template-columns: 1fr 280px;
                gap: 0.65rem;
                align-items: start;
            }
        }

        .crm-quotation-gen .q-page-layout {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
        }

        .crm-quotation-gen .q-lead-sidebar {
            --q-lead-sidebar-width: 290px;
            --q-lead-sidebar-collapsed: 52px;
            flex: 0 0 var(--q-lead-sidebar-width);
            width: var(--q-lead-sidebar-width);
            max-width: 100%;
            position: sticky;
            top: 0.75rem;
            align-self: flex-start;
            max-height: calc(100vh - 5.5rem);
            overflow: visible;
            padding-right: 0.15rem;
            transition: width 0.22s ease, flex-basis 0.22s ease;
            box-sizing: border-box;
        }

        .crm-quotation-gen .q-lead-sidebar-inner {
            max-height: calc(100vh - 5.5rem);
            overflow-y: auto;
            overflow-x: hidden;
            padding-right: 0.15rem;
            opacity: 1;
            visibility: visible;
            transition: opacity 0.18s ease, visibility 0.18s ease;
        }

        .crm-quotation-gen .q-lead-sidebar-toggle {
            position: absolute;
            top: 0.55rem;
            right: -12px;
            z-index: 5;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            border: 1px solid var(--q-border);
            background: var(--q-card-bg);
            color: var(--q-text-muted);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.12);
            cursor: pointer;
            padding: 0;
            line-height: 1;
        }

        .crm-quotation-gen .q-lead-sidebar-toggle:hover {
            background: var(--q-border-light);
            color: var(--q-text);
            border-color: var(--q-border);
        }

        .crm-quotation-gen .q-lead-sidebar-toggle i {
            font-size: 0.78rem;
            transition: transform 0.18s ease;
        }

        .crm-quotation-gen .q-page-layout.is-lead-sidebar-collapsed .q-lead-sidebar {
            flex-basis: var(--q-lead-sidebar-collapsed);
            width: var(--q-lead-sidebar-collapsed);
            overflow: visible;
        }

        .crm-quotation-gen .q-page-layout.is-lead-sidebar-collapsed .q-lead-sidebar-inner {
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
            max-height: 0;
            overflow: hidden;
        }

        .crm-quotation-gen .q-page-layout.is-lead-sidebar-collapsed .q-lead-sidebar-toggle {
            right: auto;
            left: 50%;
            top: 0.85rem;
            transform: translateX(-50%);
            width: 34px;
            height: 34px;
            background: #1e3a5f;
            border-color: #1e3a5f;
            color: #fff;
        }

        .crm-quotation-gen .q-page-layout.is-lead-sidebar-collapsed .q-lead-sidebar-toggle:hover {
            background: #254a75;
            border-color: #254a75;
            color: #fff;
        }

        .crm-quotation-gen .q-page-layout.is-lead-sidebar-collapsed .q-lead-sidebar-toggle i {
            transform: rotate(180deg);
        }

        .crm-quotation-gen .q-main-panel {
            flex: 1 1 auto;
            min-width: 0;
            transition: margin 0.22s ease;
        }

        .crm-quotation-gen .q-side-card {
            background: var(--q-card-bg);
            border: 1px solid var(--q-border);
            border-radius: 8px;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
            padding: 0.85rem 0.9rem;
            margin-bottom: 0.65rem;
        }

        .crm-quotation-gen .q-side-card-title {
            font-size: 0.92rem;
            font-weight: 700;
            color: var(--q-text);
            margin: 0 0 0.65rem;
        }

        .crm-quotation-gen .q-side-card.is-collapsible {
            padding-top: 0.7rem;
            padding-bottom: 0.7rem;
        }

        .crm-quotation-gen .q-side-card.is-collapsible.is-collapsed {
            padding-bottom: 0.7rem;
        }

        .crm-quotation-gen .q-side-card-toggle {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
            width: 100%;
            margin: 0;
            padding: 0;
            border: 0 !important;
            outline: none !important;
            box-shadow: none !important;
            background: transparent;
            cursor: pointer;
            text-align: left;
            -webkit-appearance: none;
            appearance: none;
        }

        .crm-quotation-gen .q-side-card-toggle:focus,
        .crm-quotation-gen .q-side-card-toggle:focus-visible,
        .crm-quotation-gen .q-side-card-toggle:active {
            border: 0 !important;
            outline: none !important;
            box-shadow: none !important;
        }

        .crm-quotation-gen .q-side-card-toggle .q-side-card-title {
            margin: 0;
        }

        .crm-quotation-gen .q-side-card-toggle-icon {
            color: #64748b;
            font-size: 0.78rem;
            transition: transform 0.15s ease;
            flex-shrink: 0;
        }

        .crm-quotation-gen .q-side-card.is-collapsed .q-side-card-toggle-icon {
            transform: rotate(-90deg);
        }

        .crm-quotation-gen .q-side-card.is-collapsed .q-side-card-body {
            display: none;
        }

        .crm-quotation-gen .q-side-card:not(.is-collapsed) .q-side-card-body {
            margin-top: 0.65rem;
        }

        .crm-quotation-gen .q-side-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.55rem 0.75rem;
        }

        .crm-quotation-gen .q-side-grid-item {
            min-width: 0;
        }

        .crm-quotation-gen .q-side-grid-label {
            display: flex;
            align-items: center;
            gap: 0.35rem;
            font-size: 0.72rem;
            color: var(--q-text-muted);
            margin-bottom: 0.15rem;
        }

        .crm-quotation-gen .q-side-grid-label i {
            color: #16a34a;
            width: 14px;
            text-align: center;
            font-size: 0.78rem;
        }

        .crm-quotation-gen .q-side-grid-value {
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--q-text);
            line-height: 1.25;
            word-break: break-word;
        }

        .crm-quotation-gen .q-side-travellers {
            margin-top: 0.65rem;
            padding-top: 0.65rem;
            border-top: 1px solid var(--q-border-light);
        }

        .crm-quotation-gen .q-side-travellers-label {
            font-size: 0.72rem;
            color: var(--q-text-muted);
            margin-bottom: 0.2rem;
        }

        .crm-quotation-gen .q-side-travellers-value {
            display: flex;
            align-items: flex-start;
            gap: 0.35rem;
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--q-text);
            line-height: 1.35;
            word-break: break-word;
        }

        .crm-quotation-gen .q-side-travellers-value i {
            color: #16a34a;
            margin-top: 0.15rem;
            flex-shrink: 0;
        }

        .crm-quotation-gen .q-side-list {
            display: flex;
            flex-direction: column;
            gap: 0.45rem;
        }

        .crm-quotation-gen .q-side-list-item {
            display: flex;
            align-items: flex-start;
            gap: 0.45rem;
            font-size: 0.8rem;
            color: var(--q-text);
            line-height: 1.35;
            word-break: break-word;
        }

        .crm-quotation-gen .q-side-list-item i {
            color: #94a3b8;
            width: 14px;
            margin-top: 0.15rem;
            flex-shrink: 0;
        }

        .crm-quotation-gen .q-side-query-list .q-side-list-item i {
            color: #64748b;
        }

        .crm-quotation-gen .q-side-description {
            font-size: 0.8rem;
            color: var(--q-text-muted);
            line-height: 1.45;
            white-space: pre-wrap;
            word-break: break-word;
        }

        .crm-quotation-gen .q-side-kv {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.45rem 0.75rem;
        }

        .crm-quotation-gen .q-side-kv dt {
            font-size: 0.72rem;
            color: var(--q-text-muted);
            margin: 0;
            font-weight: 500;
        }

        .crm-quotation-gen .q-side-kv dd {
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--q-text);
            margin: 0.1rem 0 0;
            word-break: break-word;
        }

        .crm-quotation-gen .q-side-footer {
            font-size: 0.74rem;
            color: var(--q-text-muted);
            padding: 0.15rem 0.1rem 0.35rem;
            line-height: 1.4;
        }

        .crm-quotation-gen .q-side-footer strong {
            color: #334155;
        }

        .crm-quotation-gen .q-side-back {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--q-primary);
            margin-bottom: 0.55rem;
            text-decoration: none;
        }

        .crm-quotation-gen .q-side-back:hover {
            color: var(--q-primary-dark);
            text-decoration: none;
        }

        @media (max-width: 991.98px) {
            .crm-quotation-gen .q-page-layout {
                flex-direction: column;
            }

            .crm-quotation-gen .q-lead-sidebar {
                width: 100%;
                flex-basis: auto;
                position: static;
                max-height: none;
                overflow: visible;
            }

            .crm-quotation-gen .q-lead-sidebar-inner {
                max-height: none;
                overflow: visible;
            }

            .crm-quotation-gen .q-lead-sidebar-toggle {
                display: none;
            }

            .crm-quotation-gen .q-page-layout.is-lead-sidebar-collapsed .q-lead-sidebar {
                width: 100%;
                flex-basis: auto;
            }

            .crm-quotation-gen .q-page-layout.is-lead-sidebar-collapsed .q-lead-sidebar-inner {
                opacity: 1;
                visibility: visible;
                pointer-events: auto;
                max-height: none;
                overflow: visible;
            }
        }

        .crm-quotation-gen .q-wizard {
            background: #fff;
            border: 0;
            border-radius: 14px;
            box-shadow: 0 8px 28px rgba(15, 23, 42, 0.06);
            padding: 1.15rem 1.25rem 0;
            overflow: hidden;
        }

        .crm-quotation-gen .q-stepper {
            --q-stepper-dot-size: 32px;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 0;
            position: relative;
            margin: 0 0 1.15rem;
            padding: 0.35rem 0.15rem 0.65rem;
            overflow-x: auto;
            border-bottom: 1px solid var(--q-border-light);
        }

        .crm-quotation-gen .q-stepper-connector {
            position: absolute;
            top: calc(var(--q-stepper-dot-size) / 2);
            left: calc(-50% + (var(--q-stepper-dot-size) / 2));
            width: calc(100% - var(--q-stepper-dot-size));
            height: 3px;
            transform: translateY(-50%);
            background: #e2e8f0;
            z-index: 0;
            pointer-events: none;
            border-radius: 999px;
        }

        .crm-quotation-gen .q-stepper-item:first-child .q-stepper-connector {
            display: none;
        }

        .crm-quotation-gen .q-stepper-item.is-complete .q-stepper-connector,
        .crm-quotation-gen .q-stepper-item.is-active .q-stepper-connector {
            background: var(--step-color, #2563eb);
        }

        .crm-quotation-gen .q-stepper-item {
            flex: 1 1 0;
            min-width: 78px;
            text-align: center;
            position: relative;
            z-index: 1;
            cursor: pointer;
            border: 0;
            background: transparent;
            padding: 0;
            outline: none;
            box-shadow: none;
            -webkit-tap-highlight-color: transparent;
        }

        .crm-quotation-gen .q-stepper-item:focus,
        .crm-quotation-gen .q-stepper-item:active,
        .crm-quotation-gen .q-stepper-item:focus-visible {
            outline: none;
            box-shadow: none;
            border: 0;
        }

        .crm-quotation-gen .q-stepper-item:focus:not(:focus-visible) {
            outline: none;
        }

        .crm-quotation-gen .q-stepper-item:focus-visible .q-stepper-dot {
            box-shadow: 0 0 0 3px rgba(225, 29, 46, 0.22);
        }

        .crm-quotation-gen .q-stepper-dot {
            width: var(--q-stepper-dot-size, 32px);
            height: var(--q-stepper-dot-size, 32px);
            border-radius: 50%;
            margin: 0 auto 0.45rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid #cbd5e1;
            background: #fff;
            color: #94a3b8;
            font-size: 0.72rem;
            transition: all 0.2s ease;
            position: relative;
            z-index: 2;
            box-sizing: border-box;
        }

        .crm-quotation-gen .q-stepper-dot .q-stepper-check {
            display: none;
        }

        .crm-quotation-gen .q-stepper-dot .q-stepper-icon {
            display: none;
        }

        .crm-quotation-gen .q-stepper-dot .q-stepper-num {
            display: inline-block;
            font-size: 0.82rem;
            font-weight: 700;
            line-height: 1;
            color: inherit;
        }

        .crm-quotation-gen .q-stepper-item.is-complete .q-stepper-dot .q-stepper-check {
            display: inline-block;
        }

        .crm-quotation-gen .q-stepper-item.is-complete .q-stepper-dot .q-stepper-icon,
        .crm-quotation-gen .q-stepper-item.is-complete .q-stepper-dot .q-stepper-num {
            display: none;
        }

        .crm-quotation-gen .q-stepper-label {
            display: block;
            font-size: 0.68rem;
            font-weight: 600;
            line-height: 1.25;
            color: #94a3b8;
            padding: 0 0.15rem;
        }

        .crm-quotation-gen .q-stepper-item.is-complete .q-stepper-dot {
            background: #e11d2e;
            border-color: #e11d2e;
            color: #fff;
        }

        .crm-quotation-gen .q-stepper-item.is-complete .q-stepper-label {
            color: #e11d2e;
        }

        .crm-quotation-gen .q-stepper-item.is-active .q-stepper-dot {
            background: #e11d2e;
            border-color: #e11d2e;
            color: #fff;
            box-shadow: 0 0 0 4px rgba(225, 29, 46, 0.14);
        }

        .crm-quotation-gen .q-stepper-item.is-active .q-stepper-label {
            color: #e11d2e;
            font-weight: 700;
        }

        .crm-quotation-gen .q-stepper-item.is-active .q-stepper-dot .q-stepper-check {
            display: none;
        }

        .crm-quotation-gen .q-stepper-item.is-active .q-stepper-dot .q-stepper-icon {
            display: none;
        }

        .crm-quotation-gen .q-stepper-item.is-active .q-stepper-dot .q-stepper-num {
            display: inline-block;
        }

        .crm-quotation-gen .q-stepper-item.is-complete.is-active .q-stepper-dot .q-stepper-check {
            display: none;
        }

        .crm-quotation-gen .q-stepper-item.is-complete.is-active .q-stepper-dot .q-stepper-num {
            display: inline-block;
        }

        .crm-quotation-gen .q-stepper-item.is-complete.is-active .q-stepper-dot .q-stepper-icon {
            display: none;
        }

        .crm-quotation-gen .q-stepper-item.is-locked {
            cursor: not-allowed;
            opacity: 0.85;
        }

        .crm-quotation-gen .q-stepper-item.is-complete .q-stepper-connector,
        .crm-quotation-gen .q-stepper-item.is-active .q-stepper-connector {
            background: #fecaca;
        }

        .crm-quotation-gen .q-wizard-step {
            display: none;
        }

        .crm-quotation-gen .q-wizard-step.is-active {
            display: block;
            animation: qWizardFadeIn 0.22s ease;
        }

        /* ——— Single-page scroll mode (progressive section reveal) ——— */
        .crm-quotation-gen .q-wizard.is-scroll-mode {
            overflow: visible;
            padding-bottom: 1.25rem;
        }

        .crm-quotation-gen .q-wizard.is-scroll-mode .q-stepper {
            position: sticky;
            top: 0;
            z-index: 40;
            background: #fff;
            margin-bottom: 0.85rem;
            padding-bottom: 0.75rem;
            box-shadow: 0 6px 16px rgba(15, 23, 42, 0.04);
        }

        .crm-quotation-gen .q-wizard.is-scroll-mode .q-wizard-panels {
            min-height: 0;
        }

        .crm-quotation-gen .q-wizard.is-scroll-mode .q-wizard-step {
            display: none;
            scroll-margin-top: 96px;
            padding: 0.35rem 0 1.35rem;
            margin-bottom: 0.35rem;
            border-bottom: 1px solid var(--q-border-light);
            animation: none;
        }

        .crm-quotation-gen .q-wizard.is-scroll-mode .q-wizard-step.is-unlocked {
            display: block;
            animation: qWizardFadeIn 0.22s ease;
        }

        .crm-quotation-gen .q-wizard.is-scroll-mode .q-wizard-step.is-unlocked:last-child,
        .crm-quotation-gen .q-wizard.is-scroll-mode .q-wizard-step.is-unlocked.is-last-unlocked {
            border-bottom: 0;
            margin-bottom: 0;
            padding-bottom: 0.5rem;
        }

        .crm-quotation-gen .q-wizard.is-scroll-mode .q-wizard-step.is-active {
            animation: none;
        }

        .crm-quotation-gen .q-wizard.is-scroll-mode .q-stepper-item.is-locked {
            opacity: 0.5;
            cursor: default;
            pointer-events: none;
        }

        .crm-quotation-gen .q-section-next-bar {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 0.65rem;
            margin-top: 0.85rem;
            padding-top: 0.85rem;
            border-top: 1px solid var(--q-border-light);
        }

        .crm-quotation-gen .q-section-next-btn {
            min-width: 120px;
            font-weight: 700;
            border-radius: 10px;
            padding: 0.5rem 1.15rem;
            background: #e11d2e;
            border: 1px solid #e11d2e;
            color: #fff;
        }

        .crm-quotation-gen .q-section-next-btn:hover {
            background: #c41e20;
            border-color: #c41e20;
            color: #fff;
        }

        .crm-quotation-gen .q-wizard.is-scroll-mode .q-wizard-nav-draft-only {
            justify-content: flex-start;
            border-top: 1px solid var(--q-border-light);
            margin-top: 0.75rem;
            padding-top: 0.85rem;
        }

        @keyframes qWizardFadeIn {
            from { opacity: 0; transform: translateY(6px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .crm-quotation-gen .q-wizard-panels {
            min-height: 280px;
        }

        .crm-quotation-gen .q-wizard-step .q-card {
            margin-bottom: 0;
            border: none;
            box-shadow: none;
            padding: 0.15rem 0.1rem 0.35rem;
        }

        /* ——— Guest & Tour (step 1) ——— */
        .crm-quotation-gen .q-wizard-step[data-q-step="1"] .q-guest-tour-card {
            padding: 0.15rem 0.1rem 0.35rem;
        }

        .crm-quotation-gen .q-guest-tour-head {
            margin-bottom: 1.05rem;
            padding: 0 0 0 0.85rem;
            border-bottom: 0;
            position: relative;
        }

        .crm-quotation-gen .q-guest-tour-head::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0.15rem;
            bottom: 0.15rem;
            width: 4px;
            border-radius: 4px;
            background: #e11d2e;
        }

        .crm-quotation-gen .q-guest-tour-head .q-section-title {
            margin: 0;
            padding: 0;
            border: 0;
            font-size: 1.2rem;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: -0.01em;
        }

        .crm-quotation-gen .q-guest-tour-subtitle {
            margin: 0.28rem 0 0;
            font-size: 0.82rem;
            color: #94a3b8;
            font-weight: 500;
        }

        .crm-quotation-gen .q-guest-tour-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            align-items: stretch;
        }

        .crm-quotation-gen .q-gt-panel {
            background: #fff;
            border: 1px solid #e8edf3;
            border-radius: 14px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
            padding: 1rem 1.05rem 1.1rem;
        }

        .crm-quotation-gen .q-gt-panel-hd {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            margin-bottom: 0.95rem;
        }

        .crm-quotation-gen .q-gt-panel-ico {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: #e11d2e;
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.82rem;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(225, 29, 46, 0.25);
        }

        .crm-quotation-gen .q-gt-panel-title {
            margin: 0;
            font-size: 0.95rem;
            font-weight: 800;
            color: #0f172a;
        }

        .crm-quotation-gen .q-gt-fields {
            display: flex;
            flex-direction: column;
            gap: 0.85rem;
        }

        .crm-quotation-gen .q-gt-fields-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.85rem 0.75rem;
        }

        .crm-quotation-gen .q-wizard-step[data-q-step="1"] label.q-label {
            font-size: 0.74rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 0.35rem;
        }

        .crm-quotation-gen .q-wizard-step[data-q-step="1"] label.q-label.label-req::after {
            content: " *";
            color: #ef4444;
            font-weight: 700;
        }

        .crm-quotation-gen .q-field-icon-wrap {
            position: relative;
        }

        .crm-quotation-gen .q-field-icon-wrap .form-control {
            padding-right: 2.2rem;
        }

        .crm-quotation-gen .q-field-icon-wrap .q-field-icon {
            position: absolute;
            right: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 0.82rem;
            pointer-events: none;
            z-index: 2;
        }

        .crm-quotation-gen .q-field-icon-wrap.q-lead-combobox .q-lead-menu {
            left: 0;
            right: 0;
        }

        .crm-quotation-gen .q-mobile-field .form-control {
            padding-left: 2.35rem;
        }

        .crm-quotation-gen .q-mobile-flag {
            position: absolute;
            left: 0.7rem;
            top: 50%;
            transform: translateY(-50%);
            font-size: 0.95rem;
            line-height: 1;
            z-index: 2;
            pointer-events: none;
        }

        .crm-quotation-gen .q-wizard-step[data-q-step="1"] .form-control {
            border-radius: 10px;
            border-color: #e2e8f0;
            min-height: calc(2.4rem + 2px);
            font-size: 0.88rem;
            color: #0f172a;
            background: #fff;
        }

        .crm-quotation-gen .q-wizard-step[data-q-step="1"] .form-control:focus {
            border-color: #f87171;
            box-shadow: 0 0 0 3px rgba(225, 29, 46, 0.12);
        }

        .crm-quotation-gen .q-wizard-step[data-q-step="1"] input[type="number"] {
            -moz-appearance: textfield;
            appearance: textfield;
        }

        .crm-quotation-gen .q-wizard-step[data-q-step="1"] input[type="number"]::-webkit-outer-spin-button,
        .crm-quotation-gen .q-wizard-step[data-q-step="1"] input[type="number"]::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        .crm-quotation-gen .q-wizard-step[data-q-step="1"] input[type="date"] {
            padding-right: 2.2rem;
        }

        .crm-quotation-gen .q-qty-stepper {
            display: grid;
            grid-template-columns: 36px 1fr 36px;
            align-items: stretch;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            overflow: hidden;
            background: #fff;
            min-height: calc(2.4rem + 2px);
        }

        .crm-quotation-gen .q-qty-stepper:focus-within {
            border-color: #f87171;
            box-shadow: 0 0 0 3px rgba(225, 29, 46, 0.12);
        }

        .crm-quotation-gen .q-qty-btn {
            border: 0;
            background: #f8fafc;
            color: #475569;
            font-size: 1.05rem;
            font-weight: 700;
            line-height: 1;
            cursor: pointer;
            padding: 0;
            transition: background 0.15s ease, color 0.15s ease;
        }

        .crm-quotation-gen .q-qty-btn:hover {
            background: #fee2e2;
            color: #e11d2e;
        }

        .crm-quotation-gen .q-qty-stepper .q-qty-input.form-control {
            border: 0 !important;
            border-left: 1px solid #e2e8f0 !important;
            border-right: 1px solid #e2e8f0 !important;
            border-radius: 0 !important;
            text-align: center;
            box-shadow: none !important;
            min-height: 100% !important;
            height: 100% !important;
            padding-left: 0.35rem !important;
            padding-right: 0.35rem !important;
            font-weight: 700;
        }

        .crm-quotation-gen .q-dest-picker {
            position: relative;
        }

        .crm-quotation-gen .q-dest-picker .js-q-dest-input {
            padding-right: 3.4rem;
        }

        .crm-quotation-gen .q-dest-picker-toggle {
            position: absolute;
            right: 2rem;
            top: 50%;
            transform: translateY(-50%);
            width: 1.5rem;
            height: 1.5rem;
            border: 0;
            background: transparent;
            color: #94a3b8;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 3;
        }

        .crm-quotation-gen .q-dest-picker-toggle:hover,
        .crm-quotation-gen .q-dest-picker-toggle:focus {
            color: #475569;
            outline: none;
        }

        .crm-quotation-gen .q-dest-picker.is-open .q-dest-picker-toggle i {
            transform: rotate(180deg);
        }

        .crm-quotation-gen .q-dest-menu {
            position: fixed;
            z-index: 2000;
            max-height: 240px;
            overflow-y: auto;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            box-shadow: 0 10px 28px rgba(15, 23, 42, 0.12);
            padding: 0.3rem 0;
        }

        .crm-quotation-gen .q-dest-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            width: 100%;
            border: 0;
            background: transparent;
            text-align: left;
            padding: 0.5rem 0.8rem;
            color: #334155;
            font-size: 0.88rem;
            cursor: pointer;
        }

        .crm-quotation-gen .q-dest-item i {
            color: #94a3b8;
            font-size: 0.78rem;
            width: 0.9rem;
            text-align: center;
        }

        .crm-quotation-gen .q-dest-item:hover,
        .crm-quotation-gen .q-dest-item.is-active,
        .crm-quotation-gen .q-dest-item:focus {
            background: #fff1f2;
            color: #be123c;
            outline: none;
        }

        .crm-quotation-gen .q-dest-item:hover i,
        .crm-quotation-gen .q-dest-item.is-active i {
            color: #e11d2e;
        }

        .crm-quotation-gen .q-dest-empty {
            padding: 0.65rem 0.8rem;
            color: #94a3b8;
            font-size: 0.82rem;
        }

        .crm-quotation-gen .q-dest-empty strong {
            color: #475569;
            font-weight: 600;
        }

        @media (max-width: 991.98px) {
            .crm-quotation-gen .q-guest-tour-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 575.98px) {
            .crm-quotation-gen .q-gt-fields-2 {
                grid-template-columns: 1fr;
            }
        }

        .crm-quotation-gen .q-wizard-step[data-q-step="6"] .q-pricing-grid {
            gap: 1rem;
        }

        .crm-quotation-gen .q-wizard-step[data-q-step="6"] .q-cost-sheet {
            padding: 0;
            background: transparent;
            border: 0;
            border-radius: 0;
        }

        .crm-quotation-gen .q-wizard-step[data-q-step="6"] input.form-control:not(.cc-label) {
            text-align: right;
            font-variant-numeric: tabular-nums;
        }

        .crm-quotation-gen .q-wizard-step[data-q-step="6"] input[type="number"] {
            -moz-appearance: textfield;
            appearance: textfield;
        }

        .crm-quotation-gen .q-wizard-step[data-q-step="6"] input[type="number"]::-webkit-outer-spin-button,
        .crm-quotation-gen .q-wizard-step[data-q-step="6"] input[type="number"]::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        .crm-quotation-gen .q-wizard-step[data-q-step="6"] .q-pricing-card {
            background: transparent;
            border: 0;
            box-shadow: none;
            padding: 0;
        }

        .crm-quotation-gen .q-pricing-compare {
            border: 1px solid var(--q-border);
            border-radius: 16px;
            background: var(--q-card-bg);
            box-shadow: 0 10px 30px rgba(185, 28, 28, 0.08);
            overflow: hidden;
        }

        .crm-quotation-gen .q-pricing-compare-hd {
            display: flex;
            align-items: center;
            justify-content: flex-start;
            gap: 0.85rem;
            flex-wrap: wrap;
            padding: 1.05rem 1.25rem 1.15rem;
            background:
                url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 720 160' fill='none'%3E%3Cpath fill='%23ffffff' fill-opacity='0.07' d='M0 110c40-35 90-50 140-35 28 8 48 6 72-12 36-28 82-32 124-6 22 14 48 12 68-8 40-40 108-44 160-8 24 16 52 20 80 6l76-28v91H0z'/%3E%3Cpath stroke='%23ffffff' stroke-opacity='0.22' stroke-width='2' stroke-dasharray='4 6' d='M430 58c38-10 78 4 112 28'/%3E%3Cpath fill='%23ffffff' fill-opacity='0.18' d='M548 52l58-16 8 6-52 22z'/%3E%3Ccircle cx='612' cy='40' r='11' fill='%23ffffff' fill-opacity='0.14'/%3E%3Cpath stroke='%23ffffff' stroke-opacity='0.2' stroke-width='2' d='M606 40c8-10 22-14 34-8M500 78c-4-18 6-34 22-40M528 78c0-16 10-28 24-32'/%3E%3C/svg%3E") right center / auto 110% no-repeat,
                linear-gradient(115deg, #8f0f2e 0%, #c4121a 45%, #a00000 100%);
            border-bottom: 0;
            color: #fff;
            position: relative;
            min-height: 72px;
        }

        .crm-quotation-gen .q-pricing-compare-hd-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: #fff;
            color: #c4121a;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            flex: 0 0 auto;
            box-shadow: 0 4px 12px rgba(0,0,0,0.12);
        }

        .crm-quotation-gen .q-pricing-compare-hd-text {
            min-width: 0;
        }

        .crm-quotation-gen .q-pricing-compare-hd h4 {
            margin: 0;
            font-size: 1.2rem;
            font-weight: 800;
            color: #fff;
            letter-spacing: -0.01em;
            line-height: 1.2;
        }

        .crm-quotation-gen .q-pricing-compare-hd .q-hint {
            margin: 0.2rem 0 0;
            font-size: 0.8rem;
            color: rgba(0, 0, 0, 0.82);
            font-weight: 500;
        }

        .crm-quotation-gen .q-pricing-compare-body {
            display: flex;
            align-items: stretch;
            min-height: 280px;
            background: #f8fafc;
        }

        .crm-quotation-gen .q-pricing-sheets-host {
            display: grid;
            grid-template-columns: minmax(132px, 150px) repeat(var(--q-opt-count, 1), minmax(168px, 1fr));
            gap: 0.75rem;
            align-items: stretch;
            overflow-x: auto;
            justify-content: start;
            flex: 1 1 auto;
            min-width: 0;
            padding: 0.9rem 0.75rem 1rem 1rem;
            background: var(--q-border-light);
        }

        .crm-quotation-gen .q-pricing-sheets-host.is-single-option {
            grid-template-columns: minmax(132px, 150px) minmax(220px, 300px);
        }

        .crm-quotation-gen .q-pricing-sheets-host.is-single-option .q-pricing-option-sheet {
            max-width: 300px;
        }

        .crm-quotation-gen .q-pricing-sidebar {
            --q-side-gap: 0.65rem;
            --q-side-pad: 0.75rem;
            flex: 0 0 340px;
            width: 340px;
            max-width: 100%;
            border-left: 1px solid var(--q-border);
            background: var(--q-border-light);
            padding: 0.85rem 0.8rem 0.9rem;
            display: flex;
            flex-direction: column;
            gap: var(--q-side-gap);
            box-sizing: border-box;
        }

        .crm-quotation-gen .q-pricing-side-top {
            display: grid;
            grid-template-columns: 1fr;
            gap: var(--q-side-gap);
            align-items: stretch;
            min-height: 0;
        }

        .crm-quotation-gen .q-pricing-side-block {
            background: var(--q-card-bg);
            border: 1px solid var(--q-border);
            border-radius: 14px;
            padding: var(--q-side-pad);
            min-width: 0;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            box-shadow: 0 4px 14px rgba(185, 28, 28, 0.05);
        }

        .crm-quotation-gen .q-pricing-side-block h5 {
            margin: 0 0 0.55rem;
            height: auto;
            font-size: 0.82rem;
            font-weight: 800;
            color: var(--q-text);
            text-transform: none;
            letter-spacing: 0;
            display: flex;
            align-items: center;
            gap: 0.45rem;
            flex: 0 0 auto;
            line-height: 1.2;
        }

        .crm-quotation-gen .q-pricing-side-block h5 .q-side-ico {
            width: 28px;
            height: 28px;
            border-radius: 8px;
            background: #e11d2e;
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            flex: 0 0 auto;
        }

        .crm-quotation-gen .q-pricing-side-block h5 i {
            color: inherit;
            font-size: inherit;
            width: auto;
            text-align: center;
        }

        .crm-quotation-gen .q-pricing-notes-block #q_pricing_notes {
            flex: 1 1 auto;
            width: 100%;
            min-height: 96px;
            height: auto;
            margin: 0;
            font-size: 0.8rem;
            line-height: 1.4;
            resize: vertical;
            padding: 0.55rem 0.65rem;
            box-sizing: border-box;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            background: #f9fafb;
        }

        .crm-quotation-gen .q-usd-box-side {
            margin: 0;
        }

        .crm-quotation-gen .q-usd-box-side .q-label {
            display: block;
            font-size: 0.7rem;
            font-weight: 600;
            color: #64748b;
            margin: 0 0 0.2rem;
            line-height: 1;
        }

        .crm-quotation-gen .q-usd-box-side .form-control {
            height: 36px;
            font-size: 0.84rem;
            padding: 0.35rem 0.55rem;
            box-sizing: border-box;
            border-radius: 9px;
            border-color: #e5e7eb;
        }

        .crm-quotation-gen .q-usd-side-fields {
            display: flex;
            flex-direction: column;
            gap: 0.45rem;
            flex: 1 1 auto;
            min-height: 0;
        }

        .crm-quotation-gen .q-usd-side-convert {
            margin-top: 0.15rem;
        }

        .crm-quotation-gen .q-usd-side-convert .btn {
            width: 100%;
            height: 38px;
            min-height: 38px;
            padding: 0;
            font-size: 0.82rem;
            font-weight: 700;
            line-height: 1;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 0;
            border-radius: 10px;
            background: #e11d2e;
            color: #fff;
        }

        .crm-quotation-gen .q-usd-side-convert .btn:hover {
            background: #be123c;
            color: #fff;
        }

        .crm-quotation-gen .q-usd-result {
            margin-top: 0.45rem;
            padding: 0.55rem 0.65rem;
            border-radius: 10px;
            background: var(--q-primary-soft, #fff1f2);
            border: 1px solid var(--q-border);
            color: #be123c;
            font-weight: 800;
            font-size: 0.92rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
            min-height: 40px;
        }

        .crm-quotation-gen .q-usd-result.is-empty {
            color: var(--q-text-muted);
            font-weight: 600;
            font-size: 0.78rem;
        }

        .crm-quotation-gen .q-usd-result .q-usd-copy-btn,
        .crm-quotation-gen .q-usd-result #qUsdCopyResult {
            color: #be123c;
        }

        .crm-quotation-gen .q-pricing-calc-block {
            width: 100%;
            height: auto;
            flex: 0 0 auto;
        }

        .crm-quotation-gen .q-pricing-calc-block.is-focused {
            box-shadow: 0 0 0 2px rgba(225, 29, 46, 0.22);
        }

        .crm-quotation-gen .q-calc {
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
            flex: 1 1 auto;
            min-width: 0;
        }

        .crm-quotation-gen .q-calc-screen {
            background: linear-gradient(180deg, #0f172a 0%, #1e293b 100%);
            border: 1px solid #0f172a;
            border-radius: 10px;
            padding: 0.45rem 0.55rem 0.5rem;
            min-height: 58px;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            gap: 0.1rem;
            box-sizing: border-box;
            position: relative;
            overflow: hidden;
        }

        .crm-quotation-gen .q-calc-screen.is-error {
            animation: qCalcShake 0.28s ease;
            border-color: #f43f5e;
        }

        @keyframes qCalcShake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-3px); }
            75% { transform: translateX(3px); }
        }

        .crm-quotation-gen .q-calc-expr {
            min-height: 0.95rem;
            font-size: 0.68rem;
            font-weight: 600;
            color: #94a3b8;
            text-align: right;
            line-height: 1.2;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            font-variant-numeric: tabular-nums;
        }

        .crm-quotation-gen .q-calc-display {
            width: 100%;
            text-align: right;
            font-size: 1.2rem;
            font-weight: 800;
            font-variant-numeric: tabular-nums;
            letter-spacing: 0.01em;
            padding: 0;
            border: 0 !important;
            border-radius: 0;
            background: transparent !important;
            color: #f8fafc !important;
            height: auto;
            min-height: 1.4rem;
            line-height: 1.2;
            box-shadow: none !important;
            box-sizing: border-box;
            margin: 0;
            cursor: default;
        }

        .crm-quotation-gen .q-calc-display:focus {
            outline: 0;
            box-shadow: none !important;
        }

        .crm-quotation-gen .q-calc-target {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.35rem;
            min-height: 1rem;
            font-size: 0.62rem;
            color: #64748b;
            line-height: 1.2;
        }

        .crm-quotation-gen .q-calc-target strong {
            color: #0f172a;
            font-weight: 700;
        }

        .crm-quotation-gen .q-calc-feedback {
            font-size: 0.62rem;
            font-weight: 700;
            color: #059669;
            opacity: 0;
            transition: opacity 0.15s ease;
            white-space: nowrap;
        }

        .crm-quotation-gen .q-calc-feedback.is-on {
            opacity: 1;
        }

        .crm-quotation-gen .q-calc-keys {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 0.3rem;
            width: 100%;
        }

        .crm-quotation-gen .q-calc-keys button {
            border: 1px solid var(--q-border);
            background: var(--q-card-bg);
            border-radius: 8px;
            height: 34px;
            font-size: 0.82rem;
            font-weight: 700;
            color: var(--q-text);
            cursor: pointer;
            line-height: 1;
            padding: 0;
            min-width: 0;
            user-select: none;
            -webkit-tap-highlight-color: transparent;
            transition: background 0.1s ease, border-color 0.1s ease, transform 0.08s ease, box-shadow 0.1s ease;
        }

        .crm-quotation-gen .q-calc-keys button:hover {
            background: var(--q-border-light);
            border-color: var(--q-border);
        }

        .crm-quotation-gen .q-calc-keys button:active,
        .crm-quotation-gen .q-calc-keys button.is-pressed {
            transform: scale(0.96);
            background: var(--q-border);
        }

        .crm-quotation-gen .q-calc-keys button.q-calc-digit {
            background: var(--q-card-bg);
            color: var(--q-text);
        }

        .crm-quotation-gen .q-calc-keys button.q-calc-op {
            background: #fff1f2;
            border-color: #fecdd3;
            color: #be123c;
        }

        .crm-quotation-gen .q-calc-keys button.q-calc-op.is-active {
            background: #e11d2e;
            border-color: #be123c;
            color: #fff;
            box-shadow: 0 1px 4px rgba(225, 29, 46, 0.35);
        }

        .crm-quotation-gen .q-calc-keys button.q-calc-eq {
            background: #e11d2e;
            border-color: #be123c;
            color: #fff;
            font-size: 1rem;
        }

        .crm-quotation-gen .q-calc-keys button.q-calc-eq:hover {
            background: #be123c;
        }

        .crm-quotation-gen .q-calc-keys button.q-calc-clear {
            background: #fff1f2;
            border-color: #fecdd3;
            color: #be123c;
        }

        .crm-quotation-gen .q-calc-keys button.q-calc-fn {
            background: #f1f5f9;
            border-color: #e2e8f0;
            color: #475569;
            font-size: 0.78rem;
        }

        .crm-quotation-gen .q-calc-keys button.q-calc-zero {
            grid-column: span 2;
        }

        .crm-quotation-gen .q-calc-actions {
            display: grid;
            grid-template-columns: 1fr 1fr 1.4fr;
            gap: 0.3rem;
            width: 100%;
        }

        .crm-quotation-gen .q-calc-actions .btn {
            width: 100%;
            margin: 0;
            font-size: 0.66rem;
            font-weight: 700;
            padding: 0 0.25rem;
            height: 30px;
            min-height: 30px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.25rem;
            box-sizing: border-box;
            border-radius: 7px;
        }

        .crm-quotation-gen .q-calc-actions .btn i {
            font-size: 0.65rem;
        }

        .crm-quotation-gen .q-calc-hint {
            margin: 0;
            font-size: 0.58rem;
            color: #94a3b8;
            line-height: 1.25;
            text-align: center;
        }

        .crm-quotation-gen #qPricingSheetsHost .q-calc-just-filled {
            box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.55) !important;
            border-color: #10b981 !important;
            transition: box-shadow 0.2s ease, border-color 0.2s ease;
        }

        @media (max-width: 991.98px) {
            .crm-quotation-gen .q-pricing-compare-body {
                flex-direction: column;
            }

            .crm-quotation-gen .q-pricing-sidebar {
                flex: 1 1 auto;
                width: 100%;
                border-left: 0;
                border-top: 1px solid var(--q-border);
            }
        }

        @media (max-width: 575.98px) {
            .crm-quotation-gen .q-pricing-side-top {
                grid-template-columns: 1fr;
                min-height: 0;
            }

            .crm-quotation-gen .q-pricing-notes-block #q_pricing_notes {
                min-height: 88px;
            }
        }

        .crm-quotation-gen .q-pricing-labels-col {
            background: transparent;
            border-right: 0;
            min-width: 132px;
            max-width: 150px;
            position: sticky;
            left: 0;
            z-index: 2;
            padding-top: 0.15rem;
        }

        .crm-quotation-gen .q-pricing-labels-hd {
            justify-content: flex-start;
            background: transparent;
            border: 0;
            min-height: 72px;
            height: 72px;
            padding: 0.4rem 0.25rem;
            box-sizing: border-box;
        }

        .crm-quotation-gen .q-pricing-labels-title {
            font-size: 0.72rem;
            font-weight: 800;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .crm-quotation-gen .q-pricing-option-sheet {
            border: 1px solid var(--q-border);
            border-radius: 14px;
            background: var(--q-card-bg);
            box-shadow: 0 6px 18px rgba(185, 28, 28, 0.06);
            overflow: hidden;
            width: auto;
            max-width: none;
            min-width: 168px;
            display: flex;
            flex-direction: column;
        }

        .crm-quotation-gen .q-pricing-option-sheet:last-child {
            border-right: 1px solid var(--q-border);
        }

        .crm-quotation-gen .q-pricing-option-sheet.is-active {
            background: var(--q-card-bg);
            box-shadow: 0 0 0 2px rgba(196, 18, 26, 0.22), 0 8px 22px rgba(185, 28, 28, 0.1);
        }

        .crm-quotation-gen .q-pricing-option-hd {
            display: flex;
            align-items: stretch;
            justify-content: flex-start;
            gap: 0.4rem;
            flex-wrap: nowrap;
            flex-direction: column;
            min-height: 72px;
            height: auto;
            padding: 0.65rem 0.75rem 0.6rem;
            background: linear-gradient(145deg, #9a121f 0%, #c4121a 100%);
            border-bottom: 0;
            color: #fff;
            box-sizing: border-box;
        }

        .crm-quotation-gen .q-pricing-option-sheet.is-active .q-pricing-option-hd {
            background: linear-gradient(145deg, #9f1239 0%, #e11d2e 100%);
        }

        .crm-quotation-gen .q-pricing-option-hd-top {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            width: 100%;
        }

        .crm-quotation-gen .q-pricing-option-hd-action {
            display: none;
        }

        .crm-quotation-gen .q-pricing-option-hd-ico {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #fff;
            color: #e11d2e;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            flex: 0 0 auto;
        }

        .crm-quotation-gen .q-pricing-option-hd h4 {
            margin: 0;
            font-size: 0.84rem;
            font-weight: 800;
            color: #fff;
            line-height: 1.25;
        }

        .crm-quotation-gen .q-pricing-option-hd-meta {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 0.28rem;
            min-width: 0;
            flex: 1 1 auto;
        }

        .crm-quotation-gen .q-pricing-option-hd-badges {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.35rem;
            width: 100%;
        }

        .crm-quotation-gen .q-pricing-option-hd .badge-active-price,
        .crm-quotation-gen .q-pricing-option-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.28rem;
            background: #fff;
            color: #b91c1c;
            font-size: 0.64rem;
            font-weight: 700;
            padding: 0.2rem 0.55rem;
            border-radius: 999px;
            white-space: nowrap;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            margin: 0;
        }

        .crm-quotation-gen .q-pricing-option-badge.is-selected {
            background: #ecfdf5;
            color: #047857;
        }

        .crm-quotation-gen .q-pricing-option-hd .q-set-active-pricing {
            width: auto;
            max-width: none;
            font-size: 0.6rem;
            font-weight: 700;
            padding: 0.18rem 0.5rem;
            line-height: 1.2;
            white-space: nowrap;
            border: 1px solid rgba(255,255,255,0.75);
            color: #fff;
            background: rgba(255,255,255,0.1);
            border-radius: 999px;
            margin: 0;
            text-align: center;
            flex: 0 0 auto;
        }

        .crm-quotation-gen .q-pricing-option-hd .q-set-active-pricing:hover {
            background: #fff;
            color: #e11d2e;
            border-color: #fff;
        }

        .crm-quotation-gen .q-pricing-option-body {
            padding: 0.55rem 0.55rem 0.65rem;
            display: flex;
            flex-direction: column;
            gap: 0;
            background: #fff;
            flex: 1 1 auto;
        }

        .crm-quotation-gen .q-pricing-section-spacer {
            display: none;
        }

        .crm-quotation-gen .q-pricing-row-label,
        .crm-quotation-gen .q-pricing-amount-cell {
            min-height: 34px;
            height: 34px;
            display: flex;
            align-items: center;
            margin-bottom: 0.35rem;
        }

        .crm-quotation-gen .q-pricing-row-label {
            color: #475569;
            font-size: 0.78rem;
            font-weight: 600;
            line-height: 1.15;
            padding-right: 0.35rem;
            gap: 0.4rem;
        }

        .crm-quotation-gen .q-pricing-row-label i {
            width: 16px;
            color: #e11d2e;
            font-size: 0.78rem;
            text-align: center;
            flex: 0 0 auto;
        }

        .crm-quotation-gen .q-pricing-package-label {
            align-items: center;
            min-height: 34px;
            height: 34px;
            flex-direction: row;
            justify-content: flex-start;
        }

        .crm-quotation-gen .q-pricing-package-label .q-hint {
            display: none;
        }

        .crm-quotation-gen .q-pricing-summary-block {
            display: none;
        }

        .crm-quotation-gen .q-pricing-summary-card {
            margin-top: 0.55rem;
            padding: 0.45rem 0.55rem;
            border: 1px solid var(--q-border);
            border-radius: 12px;
            background: var(--q-card-bg);
            box-shadow: 0 4px 12px rgba(185, 28, 28, 0.06);
            display: flex;
            flex-direction: column;
            gap: 0;
        }

        .crm-quotation-gen .q-pricing-summary-values .q-sum-row {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 0.35rem;
            min-height: 34px;
            height: 34px;
            margin: 0;
            padding: 0 0.15rem;
        }

        .crm-quotation-gen .q-pricing-summary-values .q-sum-row + .q-sum-row {
            margin-top: 0.2rem;
        }

        .crm-quotation-gen .q-sum-label {
            display: none;
        }

        .crm-quotation-gen .q-sum-val {
            font-size: 0.88rem;
            font-weight: 800;
            color: #e11d2e;
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
            text-align: right;
            width: 100%;
        }

        .crm-quotation-gen .q-sum-profit {
            color: #16a34a;
            width: auto;
        }

        .crm-quotation-gen .q-sum-selling {
            color: var(--q-text);
        }

        .crm-quotation-gen .q-sum-profit-value {
            display: inline-flex;
            align-items: center;
            justify-content: flex-end;
            gap: 0.25rem;
            width: 100%;
        }

        .crm-quotation-gen .q-sum-pct {
            width: 2.6rem;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            background: #f8fafc;
            text-align: center;
            font-size: 0.74rem;
            font-weight: 700;
            color: #16a34a;
            padding: 0.15rem 0.2rem;
            height: 28px;
            line-height: 1;
            -moz-appearance: textfield;
            flex: 0 0 auto;
        }

        .crm-quotation-gen .q-sum-pct-sign {
            font-size: 0.72rem;
            font-weight: 700;
            color: #16a34a;
            flex: 0 0 auto;
        }

        .crm-quotation-gen .q-sum-pct::-webkit-outer-spin-button,
        .crm-quotation-gen .q-sum-pct::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        .crm-quotation-gen .q-sum-pct:focus {
            outline: 0;
            border-color: #86efac;
            box-shadow: 0 0 0 2px rgba(22, 163, 74, 0.15);
        }

        .crm-quotation-gen .q-sum-ppa-row {
            margin: 0.25rem -0.55rem -0.45rem;
            padding: 0.4rem 0.55rem;
            background: var(--q-primary-soft);
            border-radius: 0 0 11px 11px;
            border-top: 1px solid var(--q-border);
            min-height: 38px !important;
            height: 38px !important;
        }

        .crm-quotation-gen .q-sum-ppa-wrap {
            display: inline-flex;
            align-items: center;
            justify-content: flex-end;
            gap: 0.2rem;
            width: 100%;
        }

        .crm-quotation-gen .q-sum-rupee {
            color: #e11d2e;
            font-weight: 800;
            font-size: 0.95rem;
        }

        .crm-quotation-gen .q-sum-ppa-wrap .q-sheet-price-per-adult {
            width: 100%;
            max-width: 100%;
            height: 30px !important;
            min-height: 30px !important;
            border: 0 !important;
            background: transparent !important;
            box-shadow: none !important;
            color: #e11d2e !important;
            font-weight: 800 !important;
            font-size: 0.95rem !important;
            padding: 0 !important;
            text-align: right;
        }

        .crm-quotation-gen .q-pricing-labels-summary {
            display: flex;
            flex-direction: column;
            margin-top: 0.55rem;
            padding: 0.45rem 0.15rem;
            border: 1px solid transparent;
            border-radius: 12px;
            gap: 0;
            box-sizing: border-box;
        }

        .crm-quotation-gen .q-pricing-labels-summary .q-sum-label-row {
            min-height: 34px;
            height: 34px;
            margin-bottom: 0;
            margin-top: 0;
            font-weight: 700;
            color: var(--q-text);
        }

        .crm-quotation-gen .q-pricing-labels-summary .q-sum-label-row + .q-sum-label-row {
            margin-top: 0.2rem;
        }

        .crm-quotation-gen .q-pricing-labels-summary .q-sum-label-profit i {
            color: #16a34a;
        }

        .crm-quotation-gen .q-pricing-labels-summary .q-sum-label-ppa {
            min-height: 38px !important;
            height: 38px !important;
            margin-top: 0.45rem !important;
            padding: 0 0.35rem;
            background: var(--q-primary-soft);
            border-radius: 8px;
            color: #f87171;
            font-weight: 800;
        }

        .crm-quotation-gen .q-pricing-labels-summary-spacer {
            display: none;
        }

        .crm-quotation-gen .q-pricing-summary-block .q-pricing-amount-cell,
        .crm-quotation-gen .q-pricing-summary-block .q-pricing-row-label {
            margin-bottom: 0.3rem;
        }

        .crm-quotation-gen .q-pricing-summary-block .q-pricing-amount-cell:last-child,
        .crm-quotation-gen .q-pricing-labels-summary .q-pricing-row-label:last-child {
            margin-bottom: 0;
        }

        .crm-quotation-gen .q-pricing-ppa-label,
        .crm-quotation-gen .q-pricing-ppa-cell {
            background: var(--q-primary-soft);
            border-radius: 8px;
            padding-left: 0.35rem;
            padding-right: 0.35rem;
            min-height: 38px !important;
            height: 38px !important;
        }

        .crm-quotation-gen .q-pricing-ppa-label {
            color: #be123c;
            font-weight: 800;
        }

        .crm-quotation-gen .q-pricing-profit-value {
            color: #16a34a !important;
            font-weight: 700;
        }

        .crm-quotation-gen .q-pricing-amount-cell .form-control,
        .crm-quotation-gen .q-pricing-amount-cell .cost-input,
        .crm-quotation-gen .q-pricing-option-sheet .q-custom-cost .form-control {
            width: 100%;
            max-width: none;
            height: 34px !important;
            min-height: 34px !important;
            padding: 0.2rem 0.45rem 0.2rem 1.15rem !important;
            font-size: 0.8rem !important;
            line-height: 1.2;
            border-radius: 9px !important;
            border-color: var(--q-border) !important;
            background: var(--q-card-bg) !important;
            color: var(--q-text) !important;
        }

        .crm-quotation-gen .q-pricing-amount-cell {
            position: relative;
        }

        .crm-quotation-gen .q-pricing-amount-cell::before {
            content: "₹";
            position: absolute;
            left: 0.4rem;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 0.75rem;
            font-weight: 700;
            z-index: 1;
            pointer-events: none;
        }

        .crm-quotation-gen .q-pricing-amount-cell.q-pricing-add-cell::before,
        .crm-quotation-gen .q-pricing-amount-cell.q-pricing-profit-cell::before {
            display: none;
        }

        .crm-quotation-gen .q-pricing-profit-cell .form-control,
        .crm-quotation-gen .q-pricing-add-cell .form-control {
            padding-left: 0.45rem !important;
        }

        .crm-quotation-gen .q-pricing-ppa-cell .form-control {
            background: var(--q-card-bg) !important;
            border-color: var(--q-border) !important;
            color: #f87171 !important;
            font-weight: 800 !important;
        }

        .crm-quotation-gen .q-pricing-labels-col .q-pricing-option-body {
            background: transparent;
            padding: 0.55rem 0.15rem 0.65rem 0;
        }

        .crm-quotation-gen .q-pricing-labels-col .q-pricing-option-hd {
            background: transparent;
            color: inherit;
            box-shadow: none;
        }

        .crm-quotation-gen .q-pricing-footer-note {
            text-align: center;
            margin: 0.85rem 0 0;
            color: #94a3b8;
            font-size: 0.72rem;
        }

        /* ——— Tour Cost Summary card (compact) ——— */
        .crm-quotation-gen #qTourCostCard {
            display: none !important;
        }

        .crm-quotation-gen .q-tour-cost-card {
            margin-top: 0.85rem;
            width: 38%;
            max-width: 420px;
            min-width: 280px;
            background: #fff;
            border: 1px solid rgba(180, 20, 30, 0.12);
            border-radius: 12px;
            box-shadow: 0 8px 22px rgba(15, 23, 42, 0.08);
            overflow: hidden;
        }

        .crm-quotation-gen .q-tour-cost-hd {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.45rem;
            padding: 0.55rem 0.75rem;
            background: linear-gradient(105deg, #b0151a 0%, #8f1014 55%, #7a0d12 100%);
            overflow: hidden;
        }

        .crm-quotation-gen .q-tour-cost-hd::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image:
                radial-gradient(circle at 78% 35%, rgba(255, 255, 255, 0.12) 0 1px, transparent 1.5px),
                radial-gradient(circle at 86% 58%, rgba(255, 255, 255, 0.1) 0 1px, transparent 1.5px);
            background-size: 90px 60px, 70px 50px;
            opacity: 0.45;
            pointer-events: none;
        }

        .crm-quotation-gen .q-tour-cost-hd::after {
            content: '\f072';
            font-family: 'Font Awesome 5 Free';
            font-weight: 900;
            position: absolute;
            right: 0.85rem;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255, 255, 255, 0.16);
            font-size: 0.95rem;
            pointer-events: none;
        }

        .crm-quotation-gen .q-tour-cost-hd-left {
            position: relative;
            z-index: 1;
            display: flex;
            align-items: center;
            gap: 0.45rem;
            min-width: 0;
        }

        .crm-quotation-gen .q-tour-cost-hd-ico {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: #fff;
            color: #b0151a;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.72rem;
            flex-shrink: 0;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
        }

        .crm-quotation-gen .q-tour-cost-title {
            margin: 0;
            font-size: 0.82rem;
            font-weight: 800;
            color: #fff;
            letter-spacing: 0.01em;
        }

        .crm-quotation-gen .q-tour-cost-autosave {
            position: relative;
            z-index: 1;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.15rem 0.45rem;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.16);
            color: #fff;
            font-size: 0.58rem;
            font-weight: 700;
            white-space: nowrap;
            opacity: 0.55;
            transition: opacity 0.2s ease;
            border: 1px solid rgba(255, 255, 255, 0.22);
        }

        .crm-quotation-gen .q-tour-cost-autosave.is-on {
            opacity: 1;
        }

        .crm-quotation-gen .q-tour-cost-autosave i {
            font-size: 0.52rem;
        }

        .crm-quotation-gen .q-tour-cost-body {
            background: #fff;
            padding: 0.05rem 0 0.1rem;
        }

        .crm-quotation-gen .q-tour-cost-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
            padding: 0.48rem 0.75rem;
            border-bottom: 1px solid #f1f5f9;
            background: #fff;
        }

        .crm-quotation-gen .q-tour-cost-row:last-child {
            border-bottom: none;
        }

        .crm-quotation-gen .q-tour-cost-traveller {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            min-width: 0;
            flex: 1;
        }

        .crm-quotation-gen .q-tour-cost-avatar {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: #fde8ea;
            border: 1px solid #f5c2c7;
            color: #b0151a;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 0.68rem;
        }

        .crm-quotation-gen .q-tour-cost-traveller-text {
            min-width: 0;
        }

        .crm-quotation-gen .q-tour-cost-traveller-name {
            display: block;
            font-size: 0.74rem;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.2;
        }

        .crm-quotation-gen .q-tour-cost-traveller-meta {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.18rem;
            margin-top: 0.15rem;
            font-size: 0.62rem;
            color: #94a3b8;
            font-weight: 500;
            line-height: 1.25;
        }

        .crm-quotation-gen .q-tour-cost-meta-prefix,
        .crm-quotation-gen .q-tour-cost-meta-mul,
        .crm-quotation-gen .q-tour-cost-meta-qty {
            color: #94a3b8;
            font-weight: 600;
            font-size: 0.62rem;
        }

        .crm-quotation-gen .q-tour-cost-rate-inline {
            width: 4.4rem;
            height: 22px !important;
            min-height: 22px !important;
            border: 1px solid #e2e8f0 !important;
            border-radius: 5px !important;
            background: #fff !important;
            color: #0f172a !important;
            font-size: 0.64rem !important;
            font-weight: 700 !important;
            padding: 0.05rem 0.28rem !important;
            text-align: right;
            box-shadow: none !important;
        }

        .crm-quotation-gen .q-tour-cost-rate-inline:focus {
            border-color: #b0151a !important;
            box-shadow: 0 0 0 2px rgba(176, 21, 26, 0.12) !important;
            outline: none;
        }

        .crm-quotation-gen .q-tour-cost-qty-inline,
        .crm-quotation-gen .q-tour-cost-gst-inline {
            width: 2.35rem;
            height: 22px !important;
            min-height: 22px !important;
            border: 1px solid #e2e8f0 !important;
            border-radius: 5px !important;
            background: #fff !important;
            color: #0f172a !important;
            font-size: 0.64rem !important;
            font-weight: 700 !important;
            padding: 0.05rem 0.2rem !important;
            text-align: center;
            box-shadow: none !important;
            display: inline-block;
            margin: 0 0.1rem;
            vertical-align: middle;
        }

        .crm-quotation-gen .q-tour-cost-qty-inline:focus,
        .crm-quotation-gen .q-tour-cost-gst-inline:focus {
            border-color: #b0151a !important;
            box-shadow: 0 0 0 2px rgba(176, 21, 26, 0.12) !important;
            outline: none;
        }

        .crm-quotation-gen .q-tour-cost-amount {
            text-align: right;
            font-size: 0.74rem;
            font-weight: 700;
            color: #0f172a;
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
            flex-shrink: 0;
            min-width: 5.2rem;
        }

        .crm-quotation-gen .q-tour-cost-row.is-summary .q-tour-cost-traveller-name {
            font-weight: 700;
        }

        .crm-quotation-gen .q-tour-cost-grand-wrap {
            padding: 0.45rem 0.65rem 0.7rem;
            background: #fff;
        }

        .crm-quotation-gen .q-tour-cost-grand {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
            padding: 0.55rem 0.65rem;
            background: #fff5f5;
            border: 1px solid #f0b4b8;
            border-radius: 9px;
        }

        .crm-quotation-gen .q-tour-cost-grand-left {
            display: flex;
            align-items: center;
            gap: 0.45rem;
            min-width: 0;
        }

        .crm-quotation-gen .q-tour-cost-grand-ico {
            width: 26px;
            height: 26px;
            border-radius: 50%;
            background: #b0151a;
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 0.68rem;
        }

        .crm-quotation-gen .q-tour-cost-grand-text {
            min-width: 0;
        }

        .crm-quotation-gen .q-tour-cost-grand-label {
            display: block;
            font-size: 0.62rem;
            font-weight: 800;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            color: #b0151a;
            line-height: 1.15;
        }

        .crm-quotation-gen .q-tour-cost-grand-sub {
            display: block;
            margin-top: 0.08rem;
            font-size: 0.56rem;
            color: #94a3b8;
            font-weight: 500;
        }

        .crm-quotation-gen .q-tour-cost-grand-divider {
            width: 1px;
            align-self: stretch;
            background: #e2e8f0;
            flex-shrink: 0;
            margin: 0.1rem 0;
        }

        .crm-quotation-gen .q-tour-cost-grand-amount {
            font-size: 0.88rem;
            font-weight: 800;
            color: #b0151a;
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
            line-height: 1.15;
        }

        .crm-quotation-gen .q-pricing-option-sheet .q-sheet-tour-cost {
            width: 100%;
            max-width: none;
            min-width: 0;
            margin-top: 0.65rem;
            flex: 1 1 auto;
            align-self: stretch;
        }

        .crm-quotation-gen .q-pricing-option-sheet .q-sheet-tour-cost .q-tour-cost-row {
            padding: 0.42rem 0.6rem;
        }

        .crm-quotation-gen .q-pricing-option-sheet .q-sheet-tour-cost .q-tour-cost-traveller-name {
            font-size: 0.7rem;
        }

        .crm-quotation-gen .q-pricing-option-sheet .q-sheet-tour-cost .q-tour-cost-amount {
            font-size: 0.72rem;
        }

        .crm-quotation-gen .q-pricing-option-sheet .q-sheet-tour-cost .q-tour-cost-grand-amount {
            font-size: 0.88rem;
        }

        @media (max-width: 991.98px) {
            .crm-quotation-gen .q-tour-cost-card {
                width: 55%;
                max-width: 380px;
            }
        }

        @media (max-width: 575.98px) {
            .crm-quotation-gen .q-tour-cost-card {
                width: 100%;
                max-width: 100%;
                min-width: 0;
            }

            .crm-quotation-gen .q-tour-cost-row {
                flex-direction: column;
                align-items: stretch;
                gap: 0.35rem;
            }

            .crm-quotation-gen .q-tour-cost-amount {
                text-align: left;
                padding-left: 2.35rem;
            }

            .crm-quotation-gen .q-tour-cost-grand {
                flex-wrap: wrap;
            }

            .crm-quotation-gen .q-tour-cost-grand-divider {
                display: none;
            }

            .crm-quotation-gen .q-tour-cost-grand-amount {
                width: 100%;
                text-align: left;
                padding-left: 2.2rem;
                font-size: 0.82rem;
            }
        }

        .crm-quotation-gen .q-pricing-option-sheet .q-sheet-profit-compact {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.35rem;
            margin-top: 0.55rem;
            padding: 0.45rem 0.5rem;
            border: 1px solid var(--q-border);
            border-radius: 10px;
            background: var(--q-border-light);
        }

        .crm-quotation-gen .q-pricing-option-sheet .q-sheet-profit-compact-label {
            font-size: 0.68rem;
            font-weight: 700;
            color: var(--q-text-muted);
            text-transform: uppercase;
            letter-spacing: 0.03em;
            white-space: nowrap;
        }

        .crm-quotation-gen .q-pricing-option-sheet .q-sheet-profit-compact .q-sum-pct {
            width: 3.2rem;
            height: 28px;
            border: 1px solid var(--q-border);
            border-radius: 6px;
            background: var(--q-card-bg);
            text-align: center;
            font-size: 0.78rem;
            font-weight: 700;
            color: var(--q-text);
        }

        .crm-quotation-gen .q-pricing-option-sheet .q-sheet-total-mini {
            font-size: 0.78rem;
            font-weight: 800;
            color: #e11d2e;
            font-variant-numeric: tabular-nums;
            margin-left: auto;
        }

        .crm-quotation-gen .q-pricing-profit-cell {
            min-height: 28px;
            height: auto;
            align-items: center;
        }

        .crm-quotation-gen .q-pricing-option-sheet .q-custom-cost-rows {
            min-height: 28px;
            margin-bottom: 0.15rem;
        }

        .crm-quotation-gen .q-pricing-custom-label {
            min-height: 34px;
            height: 34px;
        }

        .crm-quotation-gen .q-pricing-option-sheet .q-custom-cost {
            display: grid;
            grid-template-columns: 1fr;
            gap: 0;
            margin-bottom: 0.35rem !important;
            position: relative;
        }

        .crm-quotation-gen .q-pricing-option-sheet .q-custom-cost .cc-label {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            border: 0;
        }

        .crm-quotation-gen .q-pricing-option-sheet .q-custom-cost .q-custom-cost-amt {
            display: flex;
            gap: 0.25rem;
            align-items: center;
            position: relative;
        }

        .crm-quotation-gen .q-pricing-option-sheet .q-custom-cost .q-custom-cost-amt::before {
            content: "₹";
            position: absolute;
            left: 0.4rem;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 0.75rem;
            font-weight: 700;
            z-index: 1;
            pointer-events: none;
        }

        .crm-quotation-gen .q-pricing-option-sheet .q-custom-cost .cc-amount {
            width: 100%;
            max-width: none;
            height: 34px !important;
            min-height: 34px !important;
            padding: 0.2rem 0.45rem 0.2rem 1.15rem !important;
            font-size: 0.8rem !important;
            border-radius: 9px !important;
            border-color: var(--q-border) !important;
            background: var(--q-card-bg) !important;
            color: var(--q-text) !important;
        }

        .crm-quotation-gen .q-pricing-option-sheet .q-custom-cost .q-remove {
            width: 24px;
            height: 24px;
            padding: 0;
            line-height: 1;
            font-size: 0.65rem;
            opacity: 0;
            transition: opacity 0.15s ease;
        }

        .crm-quotation-gen .q-pricing-option-sheet .q-custom-cost:hover .q-remove {
            opacity: 1;
        }

        .crm-quotation-gen .q-pricing-add-cell {
            justify-content: flex-start;
            min-height: 26px;
            height: 26px;
            opacity: 0.45;
        }

        .crm-quotation-gen .q-pricing-option-sheet:hover .q-pricing-add-cell {
            opacity: 1;
        }

        .crm-quotation-gen .q-pricing-option-sheet .q-profit-row {
            display: flex;
            flex-wrap: nowrap;
            align-items: center;
            gap: 0.15rem;
            width: 100%;
        }

        .crm-quotation-gen .q-pricing-option-sheet .q-profit-row .input-group {
            max-width: 72px;
            flex: 1 1 auto;
        }

        .crm-quotation-gen .q-pricing-option-sheet .q-profit-row .input-group-text {
            padding: 0.15rem 0.3rem;
            font-size: 0.65rem;
            height: 28px;
        }

        .crm-quotation-gen .q-pricing-option-sheet .q-profit-row .q-sheet-profit-amount {
            max-width: 68px;
            flex: 1 1 auto;
        }

        .crm-quotation-gen .q-profit-or {
            font-size: 0.55rem;
        }

        .crm-quotation-gen .q-pricing-add-cell {
            justify-content: flex-start;
            min-height: 26px;
            height: 26px;
        }

        .crm-quotation-gen .q-pricing-add-cell .q-add-cost-row {
            width: 26px;
            height: 26px;
            padding: 0;
            line-height: 1;
            font-size: 0.7rem;
        }

        .crm-quotation-gen .q-pricing-option-sheet .q-cost-totals {
            margin: 0;
        }

        @media (max-width: 767.98px) {
            .crm-quotation-gen .q-pricing-sheets-host {
                grid-template-columns: minmax(96px, 112px) repeat(var(--q-opt-count, 1), 130px);
            }

            .crm-quotation-gen .q-pricing-option-sheet {
                width: 130px;
                max-width: 130px;
                min-width: 130px;
            }
        }

        .crm-quotation-gen .q-wizard-nav {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            margin: 1.15rem -1.25rem 0;
            padding: 0.95rem 1.25rem;
            border-top: 1px solid var(--q-border);
            background: linear-gradient(180deg, #f8fafc 0%, #fff 100%);
        }

        .crm-quotation-gen .q-wizard-nav .btn {
            min-width: 120px;
            font-weight: 700;
            border-radius: 10px;
            padding: 0.5rem 1.15rem;
        }

        .crm-quotation-gen .q-wizard-nav-left {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .crm-quotation-gen .q-wizard-nav #qSaveDraftBtn {
            min-width: auto;
            border-color: #cbd5e1;
            color: #0f172a;
            background: #fff;
        }

        .crm-quotation-gen .q-wizard-nav #qSaveDraftBtn:hover {
            background: #f8fafc;
            border-color: #94a3b8;
            color: #0f172a;
        }

        .crm-quotation-gen .q-draft-banner {
            border-radius: var(--q-radius-sm);
            border: 1px solid #fde68a;
            background: linear-gradient(180deg, #fffbeb 0%, #fef3c7 100%);
            color: #92400e;
            font-size: 0.85rem;
            padding: 0.65rem 0.9rem;
            margin-bottom: 0.85rem;
        }

        .crm-quotation-gen .q-wizard-nav #qWizardNext {
            background: #e11d2e;
            border-color: #e11d2e;
            color: #fff;
            box-shadow: 0 2px 10px rgba(225, 29, 46, 0.28);
        }

        .crm-quotation-gen .q-wizard-nav #qWizardNext:hover {
            transform: translateY(-1px);
            background: #c41e20;
            border-color: #c41e20;
            color: #fff;
            box-shadow: 0 4px 14px rgba(225, 29, 46, 0.32);
        }

        .crm-quotation-gen .q-wizard-step-indicator {
            font-size: 0.8rem;
            color: var(--q-text-muted);
            font-weight: 700;
            padding: 0.35rem 0.85rem;
            background: var(--q-card-bg);
            border: 1px solid var(--q-border);
            border-radius: 10px;
        }

        .crm-quotation-gen #qAlert .alert {
            border-radius: var(--q-radius-sm);
            border: none;
            box-shadow: var(--q-shadow-sm);
            font-size: 0.84rem;
        }

        .crm-quotation-gen .q-ai-suggest {
            margin-bottom: 1rem;
            padding: 1rem 1.1rem;
            border: 1px solid #a7f3d0;
            border-radius: 12px;
            background: linear-gradient(135deg, #ecfdf5 0%, #f0fdf4 50%, #ecfeff 100%);
        }

        .crm-quotation-gen .q-ai-suggest-hd {
            display: flex;
            align-items: flex-start;
            gap: 0.65rem;
            margin-bottom: 0.75rem;
        }

        .crm-quotation-gen .q-ai-suggest-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: linear-gradient(135deg, #10b981 0%, #06b6d4 100%);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.95rem;
            flex-shrink: 0;
            box-shadow: 0 3px 10px rgba(16, 185, 129, 0.3);
        }

        .crm-quotation-gen .q-ai-suggest-title {
            font-weight: 700;
            font-size: 0.875rem;
            color: #065f46;
            margin-bottom: 0.1rem;
        }

        .crm-quotation-gen .q-ai-suggest-desc {
            font-size: 0.76rem;
            color: #64748b;
            margin: 0;
        }

        .crm-quotation-gen .q-ai-suggest-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 0.35rem;
            margin-bottom: 0.65rem;
        }

        .crm-quotation-gen .q-ai-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            font-size: 0.72rem;
            font-weight: 600;
            color: #047857;
            background: rgba(255, 255, 255, 0.85);
            border: 1px solid #a7f3d0;
            padding: 0.2rem 0.55rem;
            border-radius: 999px;
        }

        .crm-quotation-gen .q-ai-chip i {
            font-size: 0.65rem;
            opacity: 0.85;
        }

        .crm-quotation-gen .q-ai-suggest-notes {
            font-size: 0.8125rem;
            border-radius: 10px;
            border-color: #a7f3d0;
            resize: vertical;
            min-height: 56px;
        }

        .crm-quotation-gen .q-ai-suggest-notes:focus {
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.12);
        }

        .crm-quotation-gen #qSuggestAIItinerary {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            border: 0;
            color: #fff;
            font-weight: 700;
            font-size: 0.8125rem;
            padding: 0.45rem 1.1rem;
            border-radius: 999px;
            box-shadow: 0 3px 12px rgba(16, 185, 129, 0.28);
            transition: transform 0.12s, box-shadow 0.12s;
        }

        .crm-quotation-gen #qSuggestAIItinerary:hover:not(:disabled) {
            transform: translateY(-1px);
            box-shadow: 0 5px 16px rgba(16, 185, 129, 0.35);
            color: #fff;
        }

        .crm-quotation-gen #qSuggestAIItinerary:disabled {
            opacity: 0.7;
        }

        .crm-quotation-gen .q-ai-itin-preview {
            display: none;
            margin-top: 0.85rem;
            padding: 0.85rem 0.95rem;
            border-radius: 10px;
            border: 1px solid #a7f3d0;
            background: rgba(255, 255, 255, 0.92);
        }

        .crm-quotation-gen .q-ai-itin-preview.is-visible {
            display: block;
        }

        .crm-quotation-gen .q-ai-itin-preview.is-new {
            border-color: #fecaca;
            background: #fff7f7;
        }

        .crm-quotation-gen .q-ai-itin-preview-hd {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 0.65rem;
            flex-wrap: wrap;
            margin-bottom: 0.55rem;
        }

        .crm-quotation-gen .q-ai-itin-preview-title {
            font-size: 0.8125rem;
            font-weight: 700;
            color: #065f46;
            margin: 0 0 0.2rem;
        }

        .crm-quotation-gen .q-ai-itin-preview.is-new .q-ai-itin-preview-title {
            color: #991b1b;
        }

        .crm-quotation-gen .q-ai-itin-preview-sub {
            font-size: 0.74rem;
            color: #64748b;
            margin: 0;
        }

        .crm-quotation-gen .q-ai-itin-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 0.01em;
            padding: 0.28rem 0.6rem;
            border-radius: 999px;
            white-space: nowrap;
        }

        .crm-quotation-gen .q-ai-itin-badge.is-previous {
            color: #065f46;
            background: #d1fae5;
            border: 1px solid #6ee7b7;
        }

        .crm-quotation-gen .q-ai-itin-badge.is-new {
            color: #fff;
            background: #dc2626;
            border: 1px solid #b91c1c;
            box-shadow: 0 2px 8px rgba(220, 38, 38, 0.25);
        }

        .crm-quotation-gen .q-ai-itin-preview-days {
            list-style: none;
            margin: 0 0 0.75rem;
            padding: 0;
            max-height: 180px;
            overflow-y: auto;
        }

        .crm-quotation-gen .q-ai-itin-preview-days li {
            display: flex;
            align-items: baseline;
            gap: 0.45rem;
            font-size: 0.78rem;
            color: #334155;
            padding: 0.28rem 0;
            border-bottom: 1px dashed #e2e8f0;
        }

        .crm-quotation-gen .q-ai-itin-preview-days li:last-child {
            border-bottom: 0;
        }

        .crm-quotation-gen .q-ai-itin-preview-days .day-num {
            flex-shrink: 0;
            font-weight: 700;
            color: #64748b;
            min-width: 3.2rem;
        }

        .crm-quotation-gen .q-ai-itin-preview-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.45rem;
            align-items: center;
        }

        .crm-quotation-gen #qApplyAIItinerary {
            background: #0f766e;
            border: 0;
            color: #fff;
            font-weight: 700;
            font-size: 0.78rem;
            padding: 0.4rem 0.95rem;
            border-radius: 999px;
        }

        .crm-quotation-gen .q-ai-itin-preview.is-new #qApplyAIItinerary {
            background: #dc2626;
        }

        .crm-quotation-gen #qDismissAIItinerary {
            font-size: 0.75rem;
            font-weight: 600;
        }

        .crm-quotation-gen .q-suggest-divider {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin: 1rem 0;
            color: #94a3b8;
            font-size: 0.72rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .crm-quotation-gen .q-suggest-divider::before,
        .crm-quotation-gen .q-suggest-divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--q-border);
        }

        .crm-quotation-gen .q-wizard-step[data-q-step="5"] .q-accordion-head.collapsed {
            border-bottom: none;
        }

        /* Quotation preview modal */
        #qPreviewModal .modal-dialog {
            max-width: 920px;
        }

        #qPreviewModal .modal-body {
            padding: 0;
            background: #fff;
        }

        .q-preview-doc {
            font-family: Arial, Helvetica, sans-serif;
            color: #222;
            font-size: 13px;
            line-height: 1.45;
            padding: 18px 22px 24px;
        }

        .q-preview-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            padding-bottom: 10px;
            border-bottom: 1px solid #cfcfcf;
            margin-bottom: 12px;
        }

        .q-preview-logo img {
            max-height: 52px;
            width: auto;
        }

        .q-preview-title-block {
            flex: 1;
            text-align: center;
            padding: 0 8px;
        }

        .q-preview-title-block h1 {
            margin: 0;
            font-size: 1.15rem;
            font-weight: 700;
            letter-spacing: 0.02em;
            text-transform: uppercase;
            color: #111;
        }

        .q-preview-title-block .q-preview-duration {
            margin-top: 2px;
            font-size: 0.95rem;
            font-weight: 700;
            color: #333;
        }

        .q-preview-ref-block {
            text-align: right;
            min-width: 150px;
            font-size: 12px;
            line-height: 1.5;
        }

        .q-preview-ref-block .ref {
            font-weight: 700;
            color: #111;
        }

        .q-preview-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
            font-size: 12px;
        }

        .q-preview-table th,
        .q-preview-table td {
            border: 1px solid #bdbdbd;
            padding: 6px 8px;
            vertical-align: middle;
        }

        .q-preview-table th {
            background: #d9d9d9;
            font-weight: 700;
            text-align: center;
            color: #111;
        }

        .q-preview-table td {
            text-align: center;
        }

        .q-preview-table td.text-left {
            text-align: left;
        }

        .q-preview-table td.text-right {
            text-align: right;
        }

        .q-preview-section-title {
            background: #d9d9d9;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            padding: 7px 10px;
            margin: 16px 0 10px;
            font-size: 12px;
            color: #111;
        }

        .q-preview-day {
            margin-bottom: 14px;
        }

        .q-preview-day-head {
            background: #d9d9d9;
            font-weight: 700;
            text-transform: uppercase;
            padding: 7px 10px;
            font-size: 12px;
            margin-bottom: 8px;
        }

        .q-preview-day-body {
            padding: 0 4px;
        }

        .q-preview-day-body p {
            margin: 0 0 8px;
        }

        .q-preview-day-body ul {
            margin: 0 0 8px 18px;
            padding: 0;
        }

        .q-preview-day-body img {
            max-width: 280px;
            height: auto;
            border-radius: 4px;
            margin-top: 6px;
        }

        .q-preview-rich {
            margin-bottom: 10px;
        }

        .q-preview-rich p {
            margin: 0 0 6px;
        }

        .q-preview-rich ul {
            margin: 0 0 8px 18px;
            padding: 0;
        }

        .q-preview-cost td:first-child {
            text-align: left;
        }

        .q-preview-cost td:last-child {
            text-align: right;
            white-space: nowrap;
        }

        .q-preview-cost tr:last-child td {
            font-weight: 700;
        }

        .q-preview-gst-note {
            font-size: 11px;
            color: #666;
            margin-top: 6px;
        }

        .q-preview-footer {
            margin-top: 28px;
            text-align: center;
            padding-top: 8px;
            border-top: none;
        }

        .q-preview-expert {
            margin-bottom: 0;
        }

        .q-preview-expert-avatar {
            width: 88px;
            height: 88px;
            border-radius: 50%;
            overflow: hidden;
            background: #e8e8e8;
            color: #666;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
            font-weight: 700;
            margin: 0 auto 10px;
            border: none;
        }

        .q-preview-expert-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .q-preview-expert-name {
            font-size: 15px;
            margin-bottom: 6px;
            font-weight: 400;
            color: #555;
            line-height: 1.35;
        }

        .q-preview-expert-name .q-preview-expert-fullname {
            color: #c0392b;
            font-weight: 700;
        }

        .q-preview-expert-name .q-preview-expert-sep {
            color: #999;
            margin: 0 4px;
            font-weight: 400;
        }

        .q-preview-expert-name .q-preview-expert-role {
            color: #555;
            font-weight: 500;
        }

        .q-preview-expert-lines {
            font-size: 13px;
            line-height: 1.55;
            color: #222;
            margin-bottom: 14px;
        }

        .q-preview-expert-lines .q-preview-phone-primary {
            font-weight: 700;
            color: #111;
        }

        .q-preview-services-bar {
            display: inline-block;
            background: #c0392b;
            color: #fff;
            font-weight: 700;
            font-size: 12px;
            letter-spacing: 0.06em;
            padding: 10px 36px;
            border-radius: 999px;
            margin: 4px auto 14px;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .q-preview-social {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 2px;
        }

        .q-preview-social a {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #fff !important;
            text-decoration: none;
            font-size: 13px;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .q-preview-social a.fb { background: #3b5998; }
        .q-preview-social a.tw { background: #55acee; }
        .q-preview-social a.gp { background: #dd4b39; }
        .q-preview-social a.web { background: #95a5a6; }

        .q-preview-layover td {
            background: #fff9e6;
            font-size: 12px;
        }

        .q-preview-editable {
            cursor: text;
            outline: none;
            border-radius: 2px;
            min-width: 1em;
            display: inline;
            transition: background-color .12s ease;
        }

        .q-preview-cell-edit {
            display: block;
            width: 100%;
            min-height: 1.2em;
            box-sizing: border-box;
        }

        div.q-preview-editable {
            display: block;
            min-height: 1.5em;
        }

        .q-preview-editable:hover {
            background: rgba(0, 0, 0, 0.035);
        }

        .q-preview-editable:focus,
        .q-preview-editable.is-editing {
            background: rgba(250, 204, 21, 0.18);
            outline: none;
            box-shadow: none;
        }

        .q-preview-policy-block {
            margin-bottom: 12px;
        }

        .q-preview-policy-title {
            font-weight: 700;
            font-size: 12px;
            margin-bottom: 4px;
            color: #333;
        }

        @media print {
            body * {
                visibility: hidden;
            }

            #qPreviewPrintArea,
            #qPreviewPrintArea * {
                visibility: visible;
            }

            #qPreviewPrintArea {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
            }

            .q-preview-editable,
            .q-preview-editable:hover,
            .q-preview-editable:focus,
            .q-preview-editable.is-editing {
                outline: none !important;
                box-shadow: none !important;
                background: transparent !important;
            }
        }

        /* ——— Dark mode ——— */
        [data-theme="dark"] .crm-quotation-gen {
            --q-primary: #60a5fa;
            --q-primary-dark: #3b82f6;
            --q-primary-soft: rgba(96, 165, 250, 0.12);
            --q-accent: #2dd4bf;
            --q-accent-dark: #14b8a6;
            --q-accent-soft: rgba(45, 212, 191, 0.12);
            --q-border: #3a404d;
            --q-border-light: #2e3340;
            --q-text: #b8c0cc;
            --q-text-muted: #7a8494;
            --q-label: #9aa3b2;
            --q-bg: #1a1d24;
            --q-card-bg: #22252d;
            --q-shadow-sm: none;
            --q-shadow-md: none;
            --q-shadow-lg: none;
        }

        [data-theme="dark"] .crm-quotation-gen .content-wrapper > .content {
            background: var(--mz-theme-bg-page, #1a1d24) !important;
        }

        /* Shared surfaces */
        [data-theme="dark"] .crm-quotation-gen .q-wizard,
        [data-theme="dark"] .crm-quotation-gen .q-wizard.is-scroll-mode .q-stepper,
        [data-theme="dark"] .crm-quotation-gen .q-card,
        [data-theme="dark"] .crm-quotation-gen .breadcrumbs,
        [data-theme="dark"] .crm-quotation-gen .q-toolbar,
        [data-theme="dark"] .crm-quotation-gen .q-day-card,
        [data-theme="dark"] .crm-quotation-gen .q-card-accordions .q-accordion-item,
        [data-theme="dark"] .crm-quotation-gen .q-wizard-step[data-q-step="5"] .q-accordion-item,
        [data-theme="dark"] .crm-quotation-gen .q-wizard-nav,
        [data-theme="dark"] .crm-quotation-gen .q-lead-sidebar-inner,
        [data-theme="dark"] .crm-quotation-gen .q-hotel-menu,
        [data-theme="dark"] .crm-quotation-gen .q-lead-menu,
        [data-theme="dark"] .crm-quotation-gen .q-side-card,
        [data-theme="dark"] .crm-quotation-gen .q-repeat-row,
        [data-theme="dark"] .crm-quotation-gen .q-usd-box,
        [data-theme="dark"] .crm-quotation-gen .q-flight-table-wrap,
        [data-theme="dark"] .crm-quotation-gen .q-flight-add-segment,
        [data-theme="dark"] .crm-quotation-gen .q-flight-btn-outline,
        [data-theme="dark"] .crm-quotation-gen .q-flight-swap,
        [data-theme="dark"] .crm-quotation-gen .q-img-preview-wrap,
        [data-theme="dark"] .crm-quotation-gen .q-pricing-compare,
        [data-theme="dark"] .crm-quotation-gen .q-pricing-option-sheet,
        [data-theme="dark"] .crm-quotation-gen .q-pricing-option-sheet.is-active,
        [data-theme="dark"] .crm-quotation-gen .q-pricing-side-block,
        [data-theme="dark"] .crm-quotation-gen .q-pricing-sidebar {
            background: var(--q-card-bg) !important;
            border-color: var(--q-border) !important;
            color: var(--q-text) !important;
            box-shadow: none !important;
        }

        [data-theme="dark"] .crm-quotation-gen .page-title,
        [data-theme="dark"] .crm-quotation-gen .q-section-title,
        [data-theme="dark"] .crm-quotation-gen .q-accordion-head,
        [data-theme="dark"] .crm-quotation-gen .q-day-head,
        [data-theme="dark"] .crm-quotation-gen .q-day-head-label,
        [data-theme="dark"] .crm-quotation-gen .q-side-card-title,
        [data-theme="dark"] .crm-quotation-gen .q-flight-head .q-section-title,
        [data-theme="dark"] .crm-quotation-gen .q-section-accordion-head.q-wizard-section-head .q-section-title,
        [data-theme="dark"] .crm-quotation-gen label.q-label,
        [data-theme="dark"] .crm-quotation-gen .q-pricing-side-block h5 {
            color: var(--q-text) !important;
        }

        [data-theme="dark"] .crm-quotation-gen .q-subsection-label,
        [data-theme="dark"] .crm-quotation-gen .q-flight-table-head,
        [data-theme="dark"] .crm-quotation-gen .q-toolbar,
        [data-theme="dark"] .crm-quotation-gen .q-day-head,
        [data-theme="dark"] .crm-quotation-gen .q-wizard-step[data-q-step="5"] .q-accordion-head,
        [data-theme="dark"] .crm-quotation-gen .q-accordion-head,
        [data-theme="dark"] .crm-quotation-gen .q-pricing-compare-body,
        [data-theme="dark"] .crm-quotation-gen .q-pricing-sheets-host {
            background: var(--mz-theme-bg-elevated, #2a2e38) !important;
            border-color: var(--q-border) !important;
            color: var(--q-text) !important;
        }

        [data-theme="dark"] .crm-quotation-gen .q-subsection-label {
            color: var(--q-text-muted) !important;
        }

        [data-theme="dark"] .crm-quotation-gen .q-version-select {
            background-color: var(--mz-theme-input-bg, #1e2128) !important;
            background-image: none !important;
            border-color: var(--mz-theme-input-border, #454b58) !important;
            color: var(--q-text) !important;
            box-shadow: none;
        }

        [data-theme="dark"] .crm-quotation-gen .q-version-wrap::after {
            color: #94a3b8;
        }

        [data-theme="dark"] .crm-quotation-gen .q-version-select:focus,
        [data-theme="dark"] .crm-quotation-gen .q-version-select:hover {
            border-color: #64748b !important;
            box-shadow: 0 0 0 3px rgba(100, 116, 139, 0.18);
        }

        [data-theme="dark"] .crm-quotation-gen .form-control,
        [data-theme="dark"] .crm-quotation-gen .custom-select,
        [data-theme="dark"] .crm-quotation-gen .input-group-text,
        [data-theme="dark"] .crm-quotation-gen .q-wizard-step[data-q-step="1"] .form-control,
        [data-theme="dark"] .crm-quotation-gen .q-flight-segment-row .form-control,
        [data-theme="dark"] .crm-quotation-gen .q-hotel-option-select {
            background: var(--mz-theme-input-bg, #1e2128) !important;
            border-color: var(--mz-theme-input-border, #454b58) !important;
            color: var(--q-text) !important;
        }

        [data-theme="dark"] .crm-quotation-gen .q-gt-panel {
            background: var(--mz-theme-bg-surface, #1e2128) !important;
            border-color: var(--mz-theme-border, #454b58) !important;
            box-shadow: none !important;
        }

        [data-theme="dark"] .crm-quotation-gen .q-gt-panel-title,
        [data-theme="dark"] .crm-quotation-gen .q-guest-tour-head .q-section-title,
        [data-theme="dark"] .crm-quotation-gen .q-section-accordion-head.q-wizard-section-head .q-section-title,
        [data-theme="dark"] .crm-quotation-gen .q-wizard-step[data-q-step="1"] label.q-label {
            color: var(--q-text) !important;
        }

        [data-theme="dark"] .crm-quotation-gen .q-wizard-section-subtitle,
        [data-theme="dark"] .crm-quotation-gen .q-guest-tour-subtitle,
        [data-theme="dark"] .crm-quotation-gen .q-flight-subtitle {
            color: var(--q-text-muted) !important;
        }

        [data-theme="dark"] .crm-quotation-gen .q-qty-stepper {
            background: var(--mz-theme-input-bg, #1e2128) !important;
            border-color: var(--mz-theme-input-border, #454b58) !important;
        }

        [data-theme="dark"] .crm-quotation-gen .q-qty-btn {
            background: var(--mz-theme-bg-elevated, #2a2e38) !important;
            color: var(--q-text) !important;
        }

        [data-theme="dark"] .crm-quotation-gen .q-qty-stepper .q-qty-input.form-control {
            border-left-color: var(--mz-theme-input-border, #454b58) !important;
            border-right-color: var(--mz-theme-input-border, #454b58) !important;
        }

        [data-theme="dark"] .crm-quotation-gen .form-control:focus,
        [data-theme="dark"] .crm-quotation-gen .q-flight-segment-row .form-control:focus {
            background: var(--mz-theme-input-bg, #1e2128) !important;
            border-color: rgba(96, 165, 250, 0.55) !important;
            box-shadow: 0 0 0 3px rgba(96, 165, 250, 0.15) !important;
        }

        [data-theme="dark"] .crm-quotation-gen .form-control[readonly],
        [data-theme="dark"] .crm-quotation-gen .q-cost-totals .form-control[readonly] {
            background: var(--mz-theme-bg-elevated, #2a2e38) !important;
            border-color: var(--q-border) !important;
            color: var(--q-text) !important;
        }

        [data-theme="dark"] .crm-quotation-gen .q-hint {
            background: var(--q-primary-soft) !important;
            color: var(--q-text-muted) !important;
        }

        [data-theme="dark"] .crm-quotation-gen .q-stepper-connector {
            background: var(--q-border) !important;
        }

        [data-theme="dark"] .crm-quotation-gen .q-stepper-dot {
            background: var(--mz-theme-bg-elevated, #2a2e38) !important;
            border-color: var(--q-border) !important;
            color: var(--q-text-muted) !important;
        }

        [data-theme="dark"] .crm-quotation-gen .q-stepper-label,
        [data-theme="dark"] .crm-quotation-gen .q-flight-subtitle,
        [data-theme="dark"] .crm-quotation-gen .q-flight-table-head .q-ft-col,
        [data-theme="dark"] .crm-quotation-gen .q-field-icon-wrap .q-field-icon,
        [data-theme="dark"] .crm-quotation-gen .q-side-card-toggle-icon,
        [data-theme="dark"] .crm-quotation-gen .q-img-preview-empty {
            color: var(--q-text-muted) !important;
        }

        [data-theme="dark"] .crm-quotation-gen .q-stepper-item.is-active .q-stepper-label,
        [data-theme="dark"] .crm-quotation-gen .q-stepper-item.is-complete .q-stepper-label {
            color: var(--q-text) !important;
        }

        /* Flight step */
        [data-theme="dark"] .crm-quotation-gen .q-flight-head .q-section-title {
            color: var(--q-text) !important;
        }

        [data-theme="dark"] .crm-quotation-gen .q-flight-segment-card,
        [data-theme="dark"] .crm-quotation-gen .q-flight-place,
        [data-theme="dark"] .crm-quotation-gen .q-flight-airline-combo,
        [data-theme="dark"] .crm-quotation-gen .q-flight-datetime,
        [data-theme="dark"] .crm-quotation-gen .q-flight-fare {
            background: var(--mz-theme-bg-surface, #1e2128) !important;
            border-color: var(--mz-theme-border, #454b58) !important;
            box-shadow: none !important;
        }

        [data-theme="dark"] .crm-quotation-gen .q-ft-col-supplier .form-control {
            background: var(--mz-theme-input-bg, #1e2128) !important;
            border-color: var(--mz-theme-input-border, #454b58) !important;
            color: var(--q-text) !important;
        }

        [data-theme="dark"] .crm-quotation-gen .select2-container--default .select2-selection--single,
        [data-theme="dark"] .crm-quotation-gen .q-supplier-s2-selection.select2-selection--single {
            background: var(--mz-theme-input-bg, #1e2128) !important;
            border-color: var(--mz-theme-input-border, #454b58) !important;
        }
        [data-theme="dark"] .crm-quotation-gen .select2-container--default .select2-selection--single .select2-selection__rendered {
            color: var(--q-text) !important;
        }
        [data-theme="dark"] .crm-quotation-gen .select2-container--default .select2-selection--single .select2-selection__placeholder {
            color: #94a3b8 !important;
        }
        [data-theme="dark"] .crm-quotation-gen .select2-container--default .select2-selection--single .select2-selection__arrow b {
            border-color: #94a3b8 transparent transparent transparent;
        }
        [data-theme="dark"] .select2-dropdown.q-supplier-s2-dropdown {
            background: var(--mz-theme-bg-elevated, #2a2e38) !important;
            border-color: var(--mz-theme-border, #454b58) !important;
            color: var(--q-text, #e5e7eb) !important;
        }
        [data-theme="dark"] .crm-quotation-gen .q-supplier-inline-search-field.select2-search__field {
            background: transparent !important;
            color: var(--q-text, #e5e7eb) !important;
        }
        [data-theme="dark"] .q-supplier-s2-dropdown .select2-results__option {
            color: var(--q-text, #e5e7eb);
        }
        [data-theme="dark"] .q-supplier-s2-dropdown .select2-results__option--highlighted[aria-selected],
        [data-theme="dark"] .q-supplier-s2-dropdown .select2-results__option--highlighted {
            background: rgba(225, 29, 46, 0.22) !important;
            color: #fecaca !important;
        }
        [data-theme="dark"] .q-supplier-s2-dropdown .select2-results__option[aria-selected="true"] {
            background: rgba(255, 255, 255, 0.04);
        }
        [data-theme="dark"] .q-supplier-create-footer {
            border-top-color: rgba(225, 29, 46, 0.35);
            background: linear-gradient(180deg, rgba(225, 29, 46, 0.12) 0%, rgba(225, 29, 46, 0.2) 100%);
            color: #fecaca;
        }
        [data-theme="dark"] .q-supplier-create-footer:hover,
        [data-theme="dark"] .q-supplier-create-footer:focus {
            background: rgba(225, 29, 46, 0.3);
            color: #fff;
        }

        [data-theme="dark"] .crm-quotation-gen .q-flight-segment-row .form-control {
            color: var(--q-text) !important;
        }

        [data-theme="dark"] .crm-quotation-gen .q-flight-add-segment,
        [data-theme="dark"] .crm-quotation-gen .q-flight-btn-outline,
        [data-theme="dark"] .crm-quotation-gen .q-flight-swap,
        [data-theme="dark"] .crm-quotation-gen .q-flight-remove {
            background: var(--mz-theme-bg-elevated, #2a2e38) !important;
            border-color: var(--q-border) !important;
            color: var(--q-text) !important;
        }

        [data-theme="dark"] .crm-quotation-gen .q-flight-btn-dark,
        [data-theme="dark"] .crm-quotation-gen .q-flight-btn-red,
        [data-theme="dark"] .crm-quotation-gen .q-flight-btn.is-active {
            background: #e11d2e !important;
            border-color: #e11d2e !important;
            color: #fff !important;
        }

        [data-theme="dark"] .crm-quotation-gen .q-flight-add-segment:hover,
        [data-theme="dark"] .crm-quotation-gen .q-flight-btn-outline:hover {
            background: var(--mz-theme-bg-muted, #323744) !important;
            color: var(--q-text) !important;
        }

        [data-theme="dark"] .crm-quotation-gen .q-flight-layover {
            background: rgba(251, 191, 36, 0.12) !important;
            border-color: rgba(251, 191, 36, 0.28) !important;
            color: #fcd34d !important;
        }

        [data-theme="dark"] .crm-quotation-gen .q-flight-layover-text strong {
            color: #fde68a !important;
        }

        [data-theme="dark"] .crm-quotation-gen .q-flight-journey-card {
            background: var(--mz-theme-bg-muted, #323744) !important;
            border-color: var(--q-border) !important;
        }

        [data-theme="dark"] .crm-quotation-gen .q-flight-journey-head {
            background: var(--mz-theme-bg-elevated, #2a2e38) !important;
            border-color: var(--q-border) !important;
        }

        [data-theme="dark"] .crm-quotation-gen .q-flight-journey-route {
            color: var(--q-text) !important;
        }

        [data-theme="dark"] .crm-quotation-gen .q-flight-journey-delete {
            background: var(--mz-theme-bg-elevated, #2a2e38) !important;
            border-color: rgba(248, 113, 113, 0.35) !important;
            color: #fca5a5 !important;
        }

        /* Hotel / repeat rows */
        [data-theme="dark"] .crm-quotation-gen .q-repeat-row:hover {
            border-color: var(--mz-theme-input-border, #454b58) !important;
            box-shadow: none !important;
        }

        /* Itinerary */
        [data-theme="dark"] .crm-quotation-gen .q-day-ai-suggest {
            background: var(--mz-theme-bg-muted, #323744) !important;
            border-color: var(--q-border) !important;
            color: var(--q-text) !important;
        }

        [data-theme="dark"] .crm-quotation-gen .q-img-preview-wrap {
            background: var(--mz-theme-bg-elevated, #2a2e38) !important;
            border-color: var(--q-border) !important;
        }

        [data-theme="dark"] .crm-quotation-gen .q-day-image-actions .btn {
            background: var(--mz-theme-bg-elevated, #2a2e38) !important;
            border-color: var(--q-border) !important;
            color: var(--q-text) !important;
        }

        /* Terms accordions */
        [data-theme="dark"] .crm-quotation-gen .q-accordion-head:hover {
            background: var(--mz-theme-bg-muted, #323744) !important;
        }

        [data-theme="dark"] .crm-quotation-gen .q-accordion-body {
            background: var(--q-card-bg) !important;
            color: var(--q-text) !important;
        }

        [data-theme="dark"] .crm-quotation-gen .q-accordion-head i.toggle-icon {
            color: var(--q-text-muted) !important;
        }

        /* Pricing */
        [data-theme="dark"] .crm-quotation-gen .q-pricing-sidebar {
            background: var(--mz-theme-bg-page, #1a1d24) !important;
            border-left-color: var(--q-border) !important;
        }

        [data-theme="dark"] .crm-quotation-gen .q-pricing-option-sheet .q-cost-totals,
        [data-theme="dark"] .crm-quotation-gen .q-pricing-option-sheet .q-profit-row {
            background: var(--mz-theme-bg-elevated, #2a2e38) !important;
            border-color: var(--q-border) !important;
            color: var(--q-text) !important;
        }

        [data-theme="dark"] .crm-quotation-gen .q-pricing-labels-col,
        [data-theme="dark"] .crm-quotation-gen .q-pricing-nav,
        [data-theme="dark"] .crm-quotation-gen .q-pricing-metric-label {
            background: var(--mz-theme-bg-elevated, #2a2e38) !important;
            color: var(--q-text-muted) !important;
            border-color: var(--q-border) !important;
        }

        [data-theme="dark"] .crm-quotation-gen .q-hotel-menu-item:hover,
        [data-theme="dark"] .crm-quotation-gen .q-hotel-menu-item:focus,
        [data-theme="dark"] .crm-quotation-gen .q-lead-menu-item:hover,
        [data-theme="dark"] .crm-quotation-gen .q-lead-menu-item:focus {
            background: var(--mz-theme-bg-muted, #323744) !important;
        }

        [data-theme="dark"] .crm-quotation-gen .q-wizard-nav #qWizardPrev,
        [data-theme="dark"] .crm-quotation-gen .q-wizard-nav .btn-outline-secondary,
        [data-theme="dark"] .crm-quotation-gen .q-wizard-nav #qSaveDraftBtn,
        [data-theme="dark"] .crm-quotation-gen .btn-light,
        [data-theme="dark"] .crm-quotation-gen .btn-default {
            background: var(--mz-theme-bg-elevated, #2a2e38) !important;
            border-color: var(--q-border) !important;
            color: var(--q-text) !important;
        }

        [data-theme="dark"] .crm-quotation-gen .q-step-indicator,
        [data-theme="dark"] .crm-quotation-gen .q-wizard-step-indicator,
        [data-theme="dark"] .crm-quotation-gen .q-wizard-step-meta {
            color: var(--q-text-muted) !important;
            background: var(--mz-theme-bg-elevated, #2a2e38) !important;
            border-color: var(--q-border) !important;
        }

        [data-theme="dark"] .crm-quotation-gen .q-flight-layover {
            background: rgba(251, 191, 36, 0.12) !important;
            border-color: rgba(251, 191, 36, 0.28) !important;
            color: #fcd34d !important;
        }

        [data-theme="dark"] .crm-quotation-gen .q-flight-layover-text strong {
            color: #fde68a !important;
        }

        [data-theme="dark"] .q-supplier-create-modal,
        [data-theme="dark"] .q-supplier-create-modal .modal-header,
        [data-theme="dark"] .q-supplier-create-modal .modal-body,
        [data-theme="dark"] .q-supplier-create-modal .modal-footer {
            background: var(--mz-theme-bg-surface, #22252d) !important;
            border-color: var(--mz-theme-border, #3a404d) !important;
            color: var(--mz-theme-text, #b8c0cc) !important;
        }

        [data-theme="dark"] .q-supplier-create-modal .modal-title,
        [data-theme="dark"] .q-supplier-create-modal label {
            color: var(--mz-theme-text, #b8c0cc) !important;
        }

        [data-theme="dark"] .q-supplier-create-modal .form-control {
            background: var(--mz-theme-input-bg, #1e2128) !important;
            border-color: var(--mz-theme-input-border, #454b58) !important;
            color: var(--mz-theme-text, #b8c0cc) !important;
        }

        [data-theme="dark"] .q-supplier-create-modal .close {
            color: var(--mz-theme-text, #b8c0cc) !important;
            text-shadow: none !important;
            opacity: 0.85;
        }

        [data-theme="dark"] .q-supplier-create-modal .btn-outline-secondary {
            background: var(--mz-theme-bg-elevated, #2a2e38) !important;
            border-color: var(--mz-theme-border, #3a404d) !important;
            color: var(--mz-theme-text, #b8c0cc) !important;
        }

        [data-theme="dark"] .crm-quotation-gen .note-editor.note-frame {
            border-color: var(--q-border) !important;
        }

        [data-theme="dark"] .crm-quotation-gen .note-editor .note-toolbar,
        [data-theme="dark"] .crm-quotation-gen .note-editor .note-statusbar {
            background: var(--mz-theme-bg-elevated, #2a2e38) !important;
            border-color: var(--q-border) !important;
        }

        [data-theme="dark"] .crm-quotation-gen .note-editor .note-editing-area .note-editable {
            background: var(--mz-theme-input-bg, #1e2128) !important;
            color: var(--q-text) !important;
        }

        [data-theme="dark"] .crm-quotation-gen .note-btn,
        [data-theme="dark"] .crm-quotation-gen .note-btn i {
            color: var(--q-text) !important;
        }

        /* Lead sidebar cards */
        [data-theme="dark"] .crm-quotation-gen .q-side-card-body,
        [data-theme="dark"] .crm-quotation-gen .q-side-kv,
        [data-theme="dark"] .crm-quotation-gen .q-side-meta {
            color: var(--q-text) !important;
        }

        [data-theme="dark"] .crm-quotation-gen .q-side-kv span,
        [data-theme="dark"] .crm-quotation-gen .q-side-label {
            color: var(--q-text-muted) !important;
        }

        /* Calculator / USD boxes inside pricing */
        [data-theme="dark"] .crm-quotation-gen .q-pricing-calc-block .btn,
        [data-theme="dark"] .crm-quotation-gen #qCalcPanel .btn {
            background: var(--mz-theme-bg-elevated, #2a2e38) !important;
            border-color: var(--q-border) !important;
            color: var(--q-text) !important;
        }

        [data-theme="dark"] .crm-quotation-gen .q-usd-result {
            background: rgba(225, 29, 46, 0.14) !important;
            border-color: var(--q-border) !important;
            color: #fca5a5 !important;
        }

        [data-theme="dark"] .crm-quotation-gen .q-usd-result.is-empty {
            color: var(--q-text-muted) !important;
        }

        [data-theme="dark"] .crm-quotation-gen .q-usd-result #qUsdCopyResult,
        [data-theme="dark"] .crm-quotation-gen .q-usd-result .btn-link {
            color: #fca5a5 !important;
        }

        [data-theme="dark"] .crm-quotation-gen .q-pricing-amount-cell .form-control,
        [data-theme="dark"] .crm-quotation-gen .q-pricing-amount-cell .cost-input,
        [data-theme="dark"] .crm-quotation-gen .q-pricing-option-sheet .q-custom-cost .form-control,
        [data-theme="dark"] .crm-quotation-gen .q-pricing-option-sheet .q-custom-cost .cc-label,
        [data-theme="dark"] .crm-quotation-gen .q-pricing-option-sheet .q-custom-cost .cc-amount {
            background: var(--mz-theme-input-bg, #1e2128) !important;
            border-color: var(--mz-theme-input-border, #454b58) !important;
            color: var(--q-text) !important;
        }

        [data-theme="dark"] .crm-quotation-gen .q-pricing-amount-cell::before,
        [data-theme="dark"] .crm-quotation-gen .q-pricing-option-sheet .q-custom-cost .q-custom-cost-amt::before {
            color: var(--q-text-muted) !important;
        }

        [data-theme="dark"] .crm-quotation-gen .q-pricing-add-cell .btn,
        [data-theme="dark"] .crm-quotation-gen .q-add-cost-row {
            background: var(--mz-theme-bg-elevated, #2a2e38) !important;
            border-color: var(--q-border) !important;
            color: var(--q-text) !important;
        }

        [data-theme="dark"] .crm-quotation-gen .q-sheet-profit-compact {
            background: var(--mz-theme-bg-elevated, #2a2e38) !important;
            border-color: var(--q-border) !important;
        }

        [data-theme="dark"] .crm-quotation-gen .q-sheet-profit-compact .q-sum-pct {
            background: var(--mz-theme-input-bg, #1e2128) !important;
            border-color: var(--q-border) !important;
            color: var(--q-text) !important;
        }

        [data-theme="dark"] .crm-quotation-gen .q-calc-actions .btn-outline-secondary,
        [data-theme="dark"] .crm-quotation-gen .q-calc-actions .btn {
            background: var(--mz-theme-bg-elevated, #2a2e38) !important;
            border-color: var(--q-border) !important;
            color: var(--q-text) !important;
        }

        [data-theme="dark"] .crm-quotation-gen .q-calc-keys button.q-calc-op,
        [data-theme="dark"] .crm-quotation-gen .q-calc-keys button.q-calc-clear {
            background: rgba(225, 29, 46, 0.16) !important;
            border-color: var(--q-border) !important;
            color: #fca5a5 !important;
        }

        [data-theme="dark"] .crm-quotation-gen .q-calc-keys button.q-calc-fn {
            background: var(--mz-theme-bg-muted, #323744) !important;
            border-color: var(--q-border) !important;
            color: var(--q-text) !important;
        }

        [data-theme="dark"] .crm-quotation-gen .q-calc-hint,
        [data-theme="dark"] .crm-quotation-gen .q-calc-target,
        [data-theme="dark"] .crm-quotation-gen .q-pricing-footer-note {
            color: var(--q-text-muted) !important;
        }

        [data-theme="dark"] .crm-quotation-gen .q-pricing-row-label,
        [data-theme="dark"] .crm-quotation-gen .q-pricing-row-label span,
        [data-theme="dark"] .crm-quotation-gen .q-pricing-row-label i {
            color: var(--q-text) !important;
        }

        [data-theme="dark"] .crm-quotation-gen .q-pricing-compare-body,
        [data-theme="dark"] .crm-quotation-gen .q-pricing-sheets-host {
            background: var(--mz-theme-bg-page, #1a1d24) !important;
        }

        [data-theme="dark"] .crm-quotation-gen .q-pricing-summary-card,
        [data-theme="dark"] .crm-quotation-gen .q-pricing-labels-summary {
            background: var(--q-card-bg) !important;
            border-color: var(--q-border) !important;
            color: var(--q-text) !important;
        }

        [data-theme="dark"] .crm-quotation-gen .q-sum-ppa-row,
        [data-theme="dark"] .crm-quotation-gen .q-pricing-ppa-label,
        [data-theme="dark"] .crm-quotation-gen .q-pricing-ppa-cell,
        [data-theme="dark"] .crm-quotation-gen .q-pricing-labels-summary .q-sum-label-ppa {
            background: rgba(96, 165, 250, 0.1) !important;
            border-color: var(--q-border) !important;
            color: #fca5a5 !important;
        }

        [data-theme="dark"] .crm-quotation-gen .q-pricing-ppa-cell .form-control {
            background: var(--mz-theme-input-bg, #1e2128) !important;
            border-color: var(--q-border) !important;
            color: #fca5a5 !important;
        }

        [data-theme="dark"] .crm-quotation-gen .q-sum-selling,
        [data-theme="dark"] .crm-quotation-gen .q-pricing-labels-summary .q-sum-label-row,
        [data-theme="dark"] .crm-quotation-gen .q-pricing-row-label,
        [data-theme="dark"] .crm-quotation-gen .q-calc-target strong {
            color: var(--q-text) !important;
        }

        [data-theme="dark"] .crm-quotation-gen .q-calc-keys button,
        [data-theme="dark"] .crm-quotation-gen .q-calc-keys button.q-calc-digit,
        [data-theme="dark"] .crm-quotation-gen .q-calc-keys button.q-calc-fn {
            background: var(--mz-theme-bg-elevated, #2a2e38) !important;
            border-color: var(--q-border) !important;
            color: var(--q-text) !important;
        }

        [data-theme="dark"] .crm-quotation-gen .q-calc-keys button.q-calc-op,
        [data-theme="dark"] .crm-quotation-gen .q-calc-keys button.q-calc-clear {
            background: rgba(225, 29, 46, 0.15) !important;
            border-color: var(--q-border) !important;
            color: #fca5a5 !important;
        }

        [data-theme="dark"] .crm-quotation-gen .q-wizard-step[data-q-step="5"] .note-toolbar,
        [data-theme="dark"] .crm-quotation-gen .q-day-body .note-toolbar {
            background: var(--mz-theme-bg-elevated, #2a2e38) !important;
            border-color: var(--q-border) !important;
        }

        [data-theme="dark"] .crm-quotation-gen .q-day-head,
        [data-theme="dark"] .crm-quotation-gen .q-wizard-step[data-q-step="5"] .q-accordion-head,
        [data-theme="dark"] .crm-quotation-gen .q-accordion-head {
            background: var(--mz-theme-bg-elevated, #2a2e38) !important;
            background-image: none !important;
        }

        [data-theme="dark"] .crm-quotation-gen .q-flight-head .q-section-title {
            color: var(--q-text) !important;
        }

        [data-theme="dark"] .crm-quotation-gen .q-side-grid-value,
        [data-theme="dark"] .crm-quotation-gen .q-side-card-toggle .q-side-card-title {
            color: var(--q-text) !important;
        }

        [data-theme="dark"] .crm-quotation-gen .q-tour-cost-card,
        [data-theme="dark"] .crm-quotation-gen .q-tour-cost-hd,
        [data-theme="dark"] .crm-quotation-gen .q-tour-cost-row,
        [data-theme="dark"] .crm-quotation-gen .q-tour-cost-meta {
            background: var(--q-card-bg) !important;
            border-color: var(--q-border) !important;
            color: var(--q-text) !important;
            box-shadow: none !important;
        }

        [data-theme="dark"] .crm-quotation-gen .q-tour-cost-title,
        [data-theme="dark"] .crm-quotation-gen .q-tour-cost-traveller-name,
        [data-theme="dark"] .crm-quotation-gen .q-tour-cost-amount,
        [data-theme="dark"] .crm-quotation-gen .q-tour-cost-meta-row strong {
            color: var(--q-text) !important;
        }

        [data-theme="dark"] .crm-quotation-gen .q-tour-cost-traveller-meta,
        [data-theme="dark"] .crm-quotation-gen .q-tour-cost-meta-row {
            color: var(--q-text-muted) !important;
        }

        [data-theme="dark"] .crm-quotation-gen .q-tour-cost-thead {
            background: #111827 !important;
            color: #f8fafc !important;
        }

        [data-theme="dark"] .crm-quotation-gen .q-tour-cost-autosave {
            background: rgba(225, 29, 46, 0.18) !important;
            color: #fca5a5 !important;
        }

        [data-theme="dark"] .crm-quotation-gen .q-tour-cost-avatar {
            background: rgba(225, 29, 46, 0.15) !important;
            border-color: rgba(252, 165, 165, 0.35) !important;
            color: #fca5a5 !important;
        }

        [data-theme="dark"] .crm-quotation-gen .q-tour-cost-rate .form-control {
            background: var(--mz-theme-input-bg, #1e2128) !important;
            border-color: var(--q-border) !important;
            color: var(--q-text) !important;
        }

        [data-theme="dark"] .crm-quotation-gen .q-tour-cost-grand {
            background: rgba(225, 29, 46, 0.14) !important;
            border-top-color: var(--q-border) !important;
        }
    </style>
</head>

<body class="hold-transition sidebar-mini layout-fixed">
    <div class="wrapper crm-quotation-gen">

        <?php include __DIR__ . '/../includes/top-header.php'; ?>
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>

        <div class="content-wrapper">
            <?php include __DIR__ . '/../includes/page-header.php'; ?>

            <section class="content">
                <div class="container-fluid">

                    <div class="page-title-row">
                        <div class="page-title-left">
                            <h1 class="page-title"><?= htmlspecialchars($pageTitle) ?></h1>
                            <?php if ($editId > 0 && !empty($quotationVersionOptions)) { ?>
                                <div class="q-version-wrap">
                                    <select id="qVersionSelect" class="q-version-select" aria-label="Quotation version">
                                        <?php foreach ($quotationVersionOptions as $versionOption) {
                                            $optVer = max(1, (int) ($versionOption['version'] ?? 1));
                                            $isSelected = $optVer === (int) $activeViewVersion;
                                            $optLabel = (string) ($versionOption['label'] ?? ('Version ' . $optVer));
                                        ?>
                                            <option value="<?= htmlspecialchars((string) ($versionOption['href'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"<?= $isSelected ? ' selected' : '' ?>>
                                                <?= htmlspecialchars($optLabel, ENT_QUOTES, 'UTF-8') ?>
                                            </option>
                                        <?php } ?>
                                    </select>
                                </div>
                            <?php } ?>
                        </div>
                        <div class="page-title-right">
                            <button type="button" class="btn btn-sm btn-q-send-mail" id="qSendMailBtn">
                                <i class="fas fa-envelope mr-1"></i> Send Mail
                            </button>
                            <nav class="breadcrumbs">
                                <a href="dashboard.php">Home</a> /
                                <a href="crm/quotation-generator-list.php">Quotations</a> /
                                <?= $quotation ? 'Edit' : 'Generate' ?>
                            </nav>
                        </div>
                    </div>

                    <div class="q-page-layout" id="qPageLayout">
                        <?php if ($leadSidebar) { ?>
                            <aside class="q-lead-sidebar" id="qLeadSidebar" aria-label="Lead details">
                                <button type="button"
                                    class="q-lead-sidebar-toggle js-q-lead-sidebar-toggle"
                                    title="Collapse lead panel"
                                    aria-expanded="true"
                                    aria-controls="qLeadSidebarInner">
                                    <i class="fas fa-angle-left" aria-hidden="true"></i>
                                    <span class="sr-only">Toggle lead panel</span>
                                </button>
                                <div class="q-lead-sidebar-inner" id="qLeadSidebarInner">
                                <a href="<?= htmlspecialchars((string) $leadSidebar['leads_url'], ENT_QUOTES, 'UTF-8') ?>" class="q-side-back">
                                    <i class="fas fa-arrow-left"></i> Back to Leads
                                </a>

                                <div class="q-side-card">
                                    <h4 class="q-side-card-title">Travel Info</h4>
                                    <?php
                                    $sidebarServiceType = '—';
                                    $sidebarServicesRaw = (string) ($leadSidebar['travel']['service_type'] ?? '');
                                    if (stripos($sidebarServicesRaw, 'Tour Package') !== false) {
                                        $sidebarServiceType = 'Tour Package';
                                    }
                                    ?>
                                    <div class="q-side-grid">
                                        <div class="q-side-grid-item">
                                            <div class="q-side-grid-label"><i class="fas fa-map-marker-alt"></i> Destination</div>
                                            <div class="q-side-grid-value"><?= htmlspecialchars((string) $leadSidebar['travel']['destination'], ENT_QUOTES, 'UTF-8') ?></div>
                                        </div>
                                        <div class="q-side-grid-item">
                                            <div class="q-side-grid-label"><i class="far fa-calendar-alt"></i> Travel Date</div>
                                            <div class="q-side-grid-value"><?= htmlspecialchars((string) $leadSidebar['travel']['travel_date'], ENT_QUOTES, 'UTF-8') ?></div>
                                        </div>
                                        <div class="q-side-grid-item">
                                            <div class="q-side-grid-label"><i class="fas fa-suitcase"></i> Service Type</div>
                                            <div class="q-side-grid-value"><?= htmlspecialchars($sidebarServiceType, ENT_QUOTES, 'UTF-8') ?></div>
                                        </div>
                                        <div class="q-side-grid-item">
                                            <div class="q-side-grid-label"><i class="fas fa-moon"></i> Nights</div>
                                            <div class="q-side-grid-value"><?= htmlspecialchars((string) $leadSidebar['travel']['nights'], ENT_QUOTES, 'UTF-8') ?></div>
                                        </div>
                                    </div>
                                    <div class="q-side-travellers">
                                        <div class="q-side-travellers-label">Traveller Info</div>
                                        <div class="q-side-travellers-value">
                                            <i class="fas fa-users"></i>
                                            <?= htmlspecialchars((string) $leadSidebar['travel']['travellers'], ENT_QUOTES, 'UTF-8') ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="q-side-card">
                                    <h4 class="q-side-card-title">Basic Info</h4>
                                    <div class="q-side-list">
                                        <div class="q-side-list-item">
                                            <i class="fas fa-user"></i>
                                            <span><?= htmlspecialchars((string) $leadSidebar['basic']['guest_name'], ENT_QUOTES, 'UTF-8') ?></span>
                                        </div>
                                        <div class="q-side-list-item">
                                            <i class="fas fa-envelope"></i>
                                            <span><?= htmlspecialchars((string) $leadSidebar['basic']['email'], ENT_QUOTES, 'UTF-8') ?></span>
                                        </div>
                                        <div class="q-side-list-item">
                                            <i class="fas fa-mobile-alt"></i>
                                            <span><?= htmlspecialchars((string) $leadSidebar['basic']['mobile'], ENT_QUOTES, 'UTF-8') ?></span>
                                        </div>
                                    </div>
                                </div>

                                <div class="q-side-card">
                                    <h4 class="q-side-card-title">Query Info</h4>
                                    <div class="q-side-list q-side-query-list">
                                        <div class="q-side-list-item">
                                            <i class="fas fa-hashtag"></i>
                                            <span><strong>Lead ID:</strong> <?= htmlspecialchars((string) $leadSidebar['query']['uid'], ENT_QUOTES, 'UTF-8') ?></span>
                                        </div>
                                        <div class="q-side-list-item">
                                            <i class="fas fa-bullhorn"></i>
                                            <span><strong>Lead Source:</strong> <?= htmlspecialchars((string) ($leadSidebar['query']['lead_source_display'] ?? $leadSidebar['query']['lead_source']), ENT_QUOTES, 'UTF-8') ?></span>
                                        </div>
                                        <div class="q-side-list-item">
                                            <i class="far fa-flag"></i>
                                            <span><strong>QT Stage:</strong> <?= htmlspecialchars((string) $leadSidebar['query']['stage'], ENT_QUOTES, 'UTF-8') ?></span>
                                        </div>
                                    </div>
                                </div>

                                <div class="q-side-card is-collapsible is-collapsed">
                                    <button type="button" class="q-side-card-toggle js-q-side-collapse" aria-expanded="false">
                                        <h4 class="q-side-card-title">Description</h4>
                                        <i class="fas fa-chevron-down q-side-card-toggle-icon" aria-hidden="true"></i>
                                    </button>
                                    <div class="q-side-card-body">
                                        <div class="q-side-description"><?= htmlspecialchars((string) $leadSidebar['description'], ENT_QUOTES, 'UTF-8') ?></div>
                                    </div>
                                </div>

                                <div class="q-side-card is-collapsible is-collapsed">
                                    <button type="button" class="q-side-card-toggle js-q-side-collapse" aria-expanded="false">
                                        <h4 class="q-side-card-title">Other Info</h4>
                                        <i class="fas fa-chevron-down q-side-card-toggle-icon" aria-hidden="true"></i>
                                    </button>
                                    <div class="q-side-card-body">
                                        <dl class="q-side-kv">
                                            <div>
                                                <dt>Status</dt>
                                                <dd><?= htmlspecialchars((string) $leadSidebar['other']['status'], ENT_QUOTES, 'UTF-8') ?></dd>
                                            </div>
                                            <div>
                                                <dt>Owner</dt>
                                                <dd><?= htmlspecialchars((string) $leadSidebar['other']['owner'], ENT_QUOTES, 'UTF-8') ?></dd>
                                            </div>
                                            <div>
                                                <dt>Created By</dt>
                                                <dd><?= htmlspecialchars((string) $leadSidebar['other']['created_by'], ENT_QUOTES, 'UTF-8') ?></dd>
                                            </div>
                                            <div>
                                                <dt>Created At</dt>
                                                <dd><?= htmlspecialchars((string) $leadSidebar['other']['created_at'], ENT_QUOTES, 'UTF-8') ?></dd>
                                            </div>
                                        </dl>
                                    </div>
                                </div>

                                <div class="q-side-footer">
                                    Last Modified on <strong><?= htmlspecialchars((string) $leadSidebar['other']['updated_at'], ENT_QUOTES, 'UTF-8') ?></strong>
                                </div>
                                </div>
                            </aside>
                        <?php } ?>

                        <div class="q-main-panel">

                    <div id="qAlert"></div>
                    <?php if ($quotation && crmQuotationIsDraft($quotation)) { ?>
                        <div class="q-draft-banner">
                            <i class="fas fa-file-alt mr-1"></i>
                            This is a saved draft. Continue filling the form and use <strong>Save Quotation</strong> when ready to publish.
                        </div>
                    <?php } ?>

                    <form id="quotationForm" autocomplete="off" onsubmit="return false;" data-save-url="crm/ajax/save_quotation.php" data-show-draft="<?= $showSaveDraft ? '1' : '0' ?>">
                        <input type="hidden" name="id" id="q_id" value="<?= $quotation ? (int) ($quotation['id'] ?? $editId) : '' ?>">
                        <input type="hidden" name="lead_id" id="q_lead_id" value="<?= (int) ($prefill['lead_id'] ?? ($quotation['lead_id'] ?? 0)) ?>">
                        <?php if ($isArchivedView && $viewVersion > 0) { ?>
                            <input type="hidden" name="edit_from_version" id="q_edit_from_version" value="<?= (int) $viewVersion ?>">
                        <?php } ?>

                        <div class="q-wizard is-scroll-mode" id="qWizard">
                            <div class="q-stepper" id="qStepper" role="tablist" aria-label="Quotation steps">
                                <?php foreach ($qWizardSteps as $stepItem) { ?>
                                    <button type="button"
                                        class="q-stepper-item<?= (int) $stepItem['id'] === 1 ? ' is-active' : '' ?>"
                                        data-q-step="<?= (int) $stepItem['id'] ?>"
                                        style="--step-color: <?= htmlspecialchars((string) $stepItem['color'], ENT_QUOTES, 'UTF-8') ?>"
                                        aria-current="<?= (int) $stepItem['id'] === 1 ? 'step' : 'false' ?>">
                                        <span class="q-stepper-connector" aria-hidden="true"></span>
                                        <span class="q-stepper-dot">
                                            <i class="fas fa-check q-stepper-check"></i>
                                            <span class="q-stepper-num"><?= (int) $stepItem['id'] ?></span>
                                        </span>
                                        <span class="q-stepper-label"><?= htmlspecialchars((string) $stepItem['label'], ENT_QUOTES, 'UTF-8') ?></span>
                                    </button>
                                <?php } ?>
                            </div>

                            <div class="q-wizard-panels">
                        <!-- Guest & Tour -->
                        <div class="q-wizard-step is-active is-unlocked" id="qWizardSection1" data-q-step="1">
                        <div class="q-card q-guest-tour-card q-section-accordion">
                            <div class="q-section-accordion-head q-guest-tour-head" data-target="#qSectionBody1" role="button" tabindex="0" aria-expanded="true">
                                <div class="q-section-accordion-head-main">
                                    <h3 class="q-section-title">Guest &amp; Tour Details</h3>
                                </div>
                                <span class="q-section-accordion-toggle" aria-hidden="true"><i class="fas fa-chevron-down toggle-icon"></i></span>
                            </div>

                            <div class="q-section-accordion-body" id="qSectionBody1">
                            <div class="q-guest-tour-grid">
                                <section class="q-gt-panel q-gt-panel-guest" aria-label="Guest Information">
                                    <div class="q-gt-panel-hd">
                                        <span class="q-gt-panel-ico" aria-hidden="true"><i class="fas fa-user"></i></span>
                                        <h4 class="q-gt-panel-title">Guest Information</h4>
                                    </div>
                                    <div class="q-gt-fields q-gt-fields-2">
                                        <div class="form-group mb-0">
                                            <label class="q-label label-req">Guest Name</label>
                                            <div class="q-field-icon-wrap q-lead-combobox">
                                                <input type="text" name="guest_name" class="form-control js-q-lead-lookup" required autocomplete="off" placeholder="Enter guest name">
                                                <i class="fas fa-user q-field-icon"></i>
                                                <div class="q-lead-menu js-q-lead-menu" style="display:none;"></div>
                                            </div>
                                        </div>
                                        <div class="form-group mb-0">
                                            <label class="q-label">Reference Name</label>
                                            <div class="q-field-icon-wrap">
                                                <input type="text" name="reference_name" class="form-control" autocomplete="off" placeholder="Enter reference name">
                                                <i class="fas fa-user q-field-icon"></i>
                                            </div>
                                        </div>
                                        <div class="form-group mb-0">
                                            <label class="q-label label-req">Mobile No.</label>
                                            <div class="q-field-icon-wrap q-lead-combobox q-mobile-field">
                                                <span class="q-mobile-flag" aria-hidden="true">🇮🇳</span>
                                                <input type="text" name="mobile_no" class="form-control js-q-lead-lookup" autocomplete="off" placeholder="Enter mobile number">
                                                <i class="fas fa-phone-alt q-field-icon"></i>
                                                <div class="q-lead-menu js-q-lead-menu" style="display:none;"></div>
                                            </div>
                                        </div>
                                        <div class="form-group mb-0">
                                            <label class="q-label label-req">Email</label>
                                            <div class="q-field-icon-wrap q-lead-combobox">
                                                <input type="email" name="email" class="form-control js-q-lead-lookup" autocomplete="off" placeholder="Enter email address">
                                                <i class="far fa-envelope q-field-icon"></i>
                                                <div class="q-lead-menu js-q-lead-menu" style="display:none;"></div>
                                            </div>
                                        </div>
                                    </div>
                                </section>

                                <section class="q-gt-panel q-gt-panel-tour" aria-label="Tour Information">
                                    <div class="q-gt-panel-hd">
                                        <span class="q-gt-panel-ico" aria-hidden="true"><i class="fas fa-suitcase-rolling"></i></span>
                                        <h4 class="q-gt-panel-title">Tour Information</h4>
                                    </div>
                                    <div class="q-gt-fields">
                                        <div class="form-group mb-0 q-gt-dest">
                                            <label class="q-label label-req">Destination</label>
                                            <div class="q-dest-picker q-field-icon-wrap" id="qDestPicker">
                                                <input type="text" name="destination" id="qDestinationInput" class="form-control js-q-dest-input"
                                                       placeholder="Search or select destination" required autocomplete="off" role="combobox"
                                                       aria-autocomplete="list" aria-expanded="false" aria-controls="qDestinationMenu">
                                                <button type="button" class="q-dest-picker-toggle js-q-dest-toggle" tabindex="-1" aria-label="Show destinations">
                                                    <i class="fas fa-chevron-down"></i>
                                                </button>
                                                <i class="fas fa-search q-field-icon"></i>
                                                <div class="q-dest-menu js-q-dest-menu" id="qDestinationMenu" role="listbox" style="display:none;"></div>
                                            </div>
                                        </div>
                                        <div class="q-gt-fields-2">
                                            <div class="form-group mb-0">
                                                <label class="q-label label-req">Tentative Date</label>
                                                <div class="q-field-icon-wrap">
                                                    <input type="date" name="tentative_date" id="q_tentative_date" class="form-control" required>
                                                    <i class="far fa-calendar-alt q-field-icon"></i>
                                                </div>
                                            </div>
                                            <div class="form-group mb-0">
                                                <label class="q-label label-req">No. of Nights</label>
                                                <div class="q-qty-stepper">
                                                    <button type="button" class="q-qty-btn" data-qty-target="q_nights" data-qty-dir="-1" aria-label="Decrease nights">−</button>
                                                    <input type="number" min="0" name="no_of_nights" id="q_nights" class="form-control q-qty-input" value="0" required>
                                                    <button type="button" class="q-qty-btn" data-qty-target="q_nights" data-qty-dir="1" aria-label="Increase nights">+</button>
                                                </div>
                                            </div>
                                            <div class="form-group mb-0">
                                                <label class="q-label label-req">No. of Adults</label>
                                                <div class="q-qty-stepper">
                                                    <button type="button" class="q-qty-btn" data-qty-target="q_adults" data-qty-dir="-1" aria-label="Decrease adults">−</button>
                                                    <input type="number" min="1" name="no_of_adults" id="q_adults" class="form-control q-qty-input" value="1" required>
                                                    <button type="button" class="q-qty-btn" data-qty-target="q_adults" data-qty-dir="1" aria-label="Increase adults">+</button>
                                                </div>
                                            </div>
                                            <div class="form-group mb-0">
                                                <label class="q-label label-req">No. of Children</label>
                                                <div class="q-qty-stepper">
                                                    <button type="button" class="q-qty-btn" data-qty-target="q_children" data-qty-dir="-1" aria-label="Decrease children">−</button>
                                                    <input type="number" min="0" name="no_of_children" id="q_children" class="form-control q-qty-input" value="0">
                                                    <button type="button" class="q-qty-btn" data-qty-target="q_children" data-qty-dir="1" aria-label="Increase children">+</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </section>
                            </div>
                            </div>
                            <div class="q-section-next-bar">
                                <button type="button" class="btn btn-sm q-section-next-btn" data-q-next-from="1">
                                    Next <i class="fas fa-arrow-right ml-1"></i>
                                </button>
                            </div>
                        </div>
                        </div>

                        <!-- Flight / Train Details -->
                        <div class="q-wizard-step" id="qWizardSection2" data-q-step="2">
                        <div class="q-card q-flight-card q-section-accordion">
                            <div class="q-section-accordion-head q-flight-head q-wizard-section-head collapsed" data-target="#qSectionBody2" role="button" tabindex="0" aria-expanded="false">
                                <div class="q-section-accordion-head-main">
                                    <h3 class="q-section-title">Flight / Train Details</h3>
                                </div>
                                <span class="q-section-accordion-toggle" aria-hidden="true"><i class="fas fa-chevron-down toggle-icon"></i></span>
                            </div>

                            <div class="q-section-accordion-body" id="qSectionBody2" style="display:none;">
                            <div class="q-flight-actions q-section-body-toolbar">
                                    <button type="button" class="btn btn-sm q-flight-btn q-flight-btn-red is-active" id="qSearchFlight">
                                        <i class="fas fa-plus mr-1"></i>Search Flight
                                    </button>
                                    <button type="button" class="btn btn-sm q-flight-btn q-flight-btn-outline" id="qSearchTrain">
                                        <i class="fas fa-train mr-1"></i>Search Train
                                    </button>
                                    <button type="button" class="btn btn-sm q-flight-btn q-flight-btn-outline" id="qAddFlight">
                                        <i class="fas fa-plus mr-1"></i>Add Flight / Train
                                    </button>
                                    <label class="btn btn-sm q-flight-btn q-flight-btn-outline mb-0" id="qUploadSsBtn" for="qUploadSsInput">
                                        <i class="fas fa-upload mr-1"></i>Upload SS
                                    </label>
                                    <input type="file" id="qUploadSsInput" class="d-none" accept="image/*,.pdf">
                            </div>
                            <div class="q-flight-table-wrap">
                                <div id="qFlightRows" class="q-flight-rows"></div>
                            </div>

                            <div class="q-flight-add-wrap">
                                <button type="button" class="btn btn-sm q-flight-add-segment" id="qAddFlightSegment">
                                    <i class="fas fa-plus mr-1"></i>Add Another Segment
                                </button>
                            </div>
                            <div class="q-flight-upload-label text-muted small" id="qUploadSsLabel"></div>
                            </div>
                        </div>
                            <div class="q-section-next-bar">
                                <button type="button" class="btn btn-sm q-section-next-btn" data-q-next-from="2">
                                    Next <i class="fas fa-arrow-right ml-1"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Hotel Details -->
                        <div class="q-wizard-step" id="qWizardSection3" data-q-step="3">
                        <div class="q-card q-section-accordion">
                            <div class="q-section-accordion-head q-wizard-section-head collapsed" data-target="#qSectionBody3" role="button" tabindex="0" aria-expanded="false">
                                <div class="q-section-accordion-head-main">
                                    <h3 class="q-section-title">Hotel Details</h3>
                                </div>
                                <span class="q-section-accordion-toggle" aria-hidden="true"><i class="fas fa-chevron-down toggle-icon"></i></span>
                            </div>
                            <div class="q-section-accordion-body" id="qSectionBody3" style="display:none;">
                            <div class="q-hotel-cat-toolbar">
                                <div class="q-hotel-cat-tabs" id="qHotelCatTabs" role="tablist"></div>
                                <button type="button" class="btn btn-outline-primary btn-sm" id="qAddHotelCategory" title="Add pricing option">
                                    <i class="fas fa-plus mr-1"></i>Add Option
                                </button>
                            </div>
                            <div id="qHotelCategories"></div>
                            <p class="q-hint mt-2 mb-0">Hotel suggestions are filtered by Tour Information destination. Type a city to search City Master, or use <strong>Create</strong> if it is missing. For hotels, use <strong>Create</strong> to add to Hotel Master immediately.</p>
                            </div>
                        </div>
                            <div class="q-section-next-bar">
                                <button type="button" class="btn btn-sm q-section-next-btn" data-q-next-from="3">
                                    Next <i class="fas fa-arrow-right ml-1"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Itinerary -->
                        <div class="q-wizard-step" id="qWizardSection4" data-q-step="4">
                        <div class="q-card q-section-accordion">
                            <div class="q-section-accordion-head q-wizard-section-head collapsed" data-target="#qSectionBody4" role="button" tabindex="0" aria-expanded="false">
                                <div class="q-section-accordion-head-main">
                                    <h3 class="q-section-title">Itinerary</h3>
                                </div>
                                <span class="q-section-accordion-toggle" aria-hidden="true"><i class="fas fa-chevron-down toggle-icon"></i></span>
                            </div>
                            <div class="q-section-accordion-body" id="qSectionBody4" style="display:none;">
                                <div class="q-package-suggest mb-3">
                                    <label class="q-label d-block">Suggest from Package</label>
                                    <div class="row q-row-tight align-items-end">
                                        <div class="col-md-8 col-lg-9">
                                            <div class="q-lead-combobox">
                                                <input type="text" class="form-control js-q-package-search" placeholder="Search packages by title or destination..." autocomplete="off">
                                                <div class="q-lead-menu js-q-package-menu" style="display:none;"></div>
                                            </div>
                                            <p class="q-hint mb-0 mt-1">Load day-wise itinerary from <strong>Packages</strong> (<a href="packages.php" target="_blank" rel="noopener">Manage Packages</a>).</p>
                                        </div>
                                        <div class="col-md-4 col-lg-3">
                                            <button type="button" class="btn btn-q-primary btn-sm btn-block" id="qApplyPackageItinerary" disabled>
                                                <i class="fas fa-suitcase-rolling mr-1"></i>Apply Itinerary
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <p class="q-hint">Days are generated from the Tentative Date and No of Nights. Use the AI button on each day to suggest that day.</p>
                                <div class="q-itinerary-suppliers mb-3">
                                    <div class="q-itinerary-suppliers-hd">
                                        <span class="q-label mb-0">Suppliers &amp; Rates</span>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" id="qAddItinerarySupplier">
                                            <i class="fas fa-plus mr-1"></i>Add Supplier
                                        </button>
                                    </div>
                                    <div id="qItinerarySupplierRows"></div>
                                </div>
                                <div id="qItineraryDays"></div>
                            </div>
                        </div>
                            <div class="q-section-next-bar">
                                <button type="button" class="btn btn-sm q-section-next-btn" data-q-next-from="4">
                                    Next <i class="fas fa-arrow-right ml-1"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Rich-text accordions -->
                        <div class="q-wizard-step" id="qWizardSection5" data-q-step="5">
                        <div class="q-card q-card-accordions q-section-accordion">
                            <div class="q-section-accordion-head q-wizard-section-head collapsed" data-target="#qSectionBody5" role="button" tabindex="0" aria-expanded="false">
                                <div class="q-section-accordion-head-main">
                                    <h3 class="q-section-title">Terms &amp; Policies</h3>
                                </div>
                                <span class="q-section-accordion-toggle" aria-hidden="true"><i class="fas fa-chevron-down toggle-icon"></i></span>
                            </div>
                            <div class="q-section-accordion-body" id="qSectionBody5" style="display:none;">
                            <div class="q-terms-actions q-section-body-toolbar">
                                    <button type="button" class="btn btn-outline-secondary btn-sm" id="qLoadTermsMasterBtn">
                                        <i class="fas fa-download mr-1"></i> Load from Master
                                    </button>
                                    <a href="crm/quotation_terms_master.php" class="btn btn-outline-primary btn-sm" target="_blank" rel="noopener">
                                        <i class="fas fa-cog mr-1"></i> Manage Master
                                    </a>
                            </div>
                        <?php
                        $richSections = crmQuotationTermsFields();
                        foreach ($richSections as $field => $label):
                            if ($quotation) {
                                $val = (string) ($quotation[$field] ?? '');
                            } else {
                                $val = (string) ($prefill[$field] ?? ($quotationTermsMaster[$field] ?? ''));
                            }
                            ?>
                            <div class="q-accordion-item">
                                <div class="q-accordion-head collapsed" data-target="#qbody_<?= $field ?>">
                                    <i class="fas fa-chevron-down toggle-icon"></i> <?= htmlspecialchars($label) ?>
                                </div>
                                <div class="q-accordion-body" id="qbody_<?= $field ?>" style="display:none;">
                                    <textarea name="<?= $field ?>" id="qed_<?= $field ?>" class="form-control q-editor"><?= htmlspecialchars($val) ?></textarea>
                                </div>
                            </div>
                        <?php endforeach; ?>
                            </div>
                        </div>
                            <div class="q-section-next-bar">
                                <button type="button" class="btn btn-sm q-section-next-btn" data-q-next-from="5">
                                    Next <i class="fas fa-arrow-right ml-1"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Pricing -->
                        <div class="q-wizard-step" id="qWizardSection6" data-q-step="6">
                            <div class="q-card q-pricing-card q-section-accordion">
                            <div class="q-section-accordion-head q-wizard-section-head collapsed" data-target="#qSectionBody6" role="button" tabindex="0" aria-expanded="false">
                                <div class="q-section-accordion-head-main">
                                    <h3 class="q-section-title">Pricing</h3>
                                </div>
                                <span class="q-section-accordion-toggle" aria-hidden="true"><i class="fas fa-chevron-down toggle-icon"></i></span>
                            </div>
                            <div class="q-section-accordion-body" id="qSectionBody6" style="display:none;">
                            <p class="q-hint mb-2 d-none">Compare hotel options side by side. Flight amounts sync from fares; hotel amounts sync from each option’s rates.</p>

                            <div class="q-pricing-compare">
                                <div class="q-pricing-compare-hd">
                                    <span class="q-pricing-compare-hd-icon" aria-hidden="true"><i class="fas fa-suitcase-rolling"></i></span>
                                    <div class="q-pricing-compare-hd-text">
                                        <h4>FINAL COST</h4>
                                        <!-- <p class="q-hint">Compare travel options and pricing</p> -->
                                    </div>
                                </div>
                                <div class="q-pricing-compare-body">
                                    <div id="qPricingSheetsHost" class="q-pricing-sheets-host"></div>
                                    <aside class="q-pricing-sidebar" aria-label="Pricing notes and calculator">
                                        <div class="q-pricing-side-top">
                                            <div class="q-pricing-side-block q-pricing-notes-block">
                                                <h5><span class="q-side-ico"><i class="fas fa-file-alt"></i></span> Notes</h5>
                                                <textarea class="form-control" id="q_pricing_notes" name="pricing_notes" rows="4" placeholder="Add internal pricing notes, special instructions, or terms (not visible on quotation)…"></textarea>
                                            </div>
                                            <div class="q-usd-box q-usd-box-side q-pricing-side-block">
                                                <h5><span class="q-side-ico"><i class="fas fa-exchange-alt"></i></span> USD → INR Converter</h5>
                                                <div class="q-usd-side-fields">
                                                    <div>
                                                        <label class="q-label">USD Amount</label>
                                                        <input type="number" step="0.01" class="form-control" id="q_usd_amount" placeholder="1,000">
                                                    </div>
                                                    <div>
                                                        <label class="q-label">Rate (USD to INR)</label>
                                                        <input type="number" step="0.01" class="form-control" id="q_usd_rate" placeholder="83.20">
                                                    </div>
                                                    <div class="q-usd-side-convert">
                                                        <button type="button" class="btn btn-q-primary btn-sm" id="qConvertUsd">Convert</button>
                                                    </div>
                                                </div>
                                                <div class="q-usd-result is-empty" id="qUsdResult" aria-live="polite">
                                                    <span id="qUsdResultText">Enter amount &amp; rate</span>
                                                    <button type="button" class="btn btn-link btn-sm p-0 q-usd-copy-btn" id="qUsdCopyResult" title="Copy result" style="display:none;"><i class="far fa-copy"></i></button>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="q-pricing-side-block q-pricing-calc-block" id="qCalcPanel" tabindex="0" aria-label="Pricing calculator">
                                            <h5><span class="q-side-ico"><i class="fas fa-calculator"></i></span> Calculator</h5>
                                            <div class="q-calc">
                                                <div class="q-calc-screen" id="qCalcScreen">
                                                    <div class="q-calc-expr" id="qCalcExpr" aria-live="polite"></div>
                                                    <input type="text" class="form-control q-calc-display" id="qCalcDisplay" value="0" readonly inputmode="decimal" tabindex="-1" aria-label="Calculator result">
                                                </div>
                                                <div class="q-calc-target">
                                                    <span>Fill → <strong id="qCalcTargetLabel">Land</strong></span>
                                                    <span class="q-calc-feedback" id="qCalcFeedback" aria-live="polite"></span>
                                                </div>
                                                <div class="q-calc-keys" id="qCalcKeys">
                                                    <button type="button" class="q-calc-clear" data-calc="C" title="Clear all">AC</button>
                                                    <button type="button" class="q-calc-fn" data-calc="BS" title="Backspace">⌫</button>
                                                    <button type="button" class="q-calc-fn" data-calc="%" title="Percent of previous">%</button>
                                                    <button type="button" class="q-calc-op" data-calc="/" title="Divide">÷</button>
                                                    <button type="button" class="q-calc-digit" data-calc="7">7</button>
                                                    <button type="button" class="q-calc-digit" data-calc="8">8</button>
                                                    <button type="button" class="q-calc-digit" data-calc="9">9</button>
                                                    <button type="button" class="q-calc-op" data-calc="*" title="Multiply">×</button>
                                                    <button type="button" class="q-calc-digit" data-calc="4">4</button>
                                                    <button type="button" class="q-calc-digit" data-calc="5">5</button>
                                                    <button type="button" class="q-calc-digit" data-calc="6">6</button>
                                                    <button type="button" class="q-calc-op" data-calc="-" title="Subtract">−</button>
                                                    <button type="button" class="q-calc-digit" data-calc="1">1</button>
                                                    <button type="button" class="q-calc-digit" data-calc="2">2</button>
                                                    <button type="button" class="q-calc-digit" data-calc="3">3</button>
                                                    <button type="button" class="q-calc-op" data-calc="+" title="Add">+</button>
                                                    <button type="button" class="q-calc-digit q-calc-zero" data-calc="0">0</button>
                                                    <button type="button" class="q-calc-digit" data-calc=".">.</button>
                                                    <button type="button" class="q-calc-eq" data-calc="=" title="Equals">=</button>
                                                </div>
                                                <div class="q-calc-actions">
                                                    <button type="button" class="btn btn-outline-secondary btn-sm" id="qCalcLoad" title="Load last focused cost field"><i class="fas fa-arrow-up"></i> Load</button>
                                                    <button type="button" class="btn btn-outline-secondary btn-sm" id="qCalcCopy" title="Copy result"><i class="far fa-copy"></i> Copy</button>
                                                    <button type="button" class="btn btn-q-primary btn-sm" id="qCalcUseField" title="Fill last focused cost field (default Land)"><i class="fas fa-check"></i> Use</button>
                                                </div>
                                                <p class="q-calc-hint">Click a cost field, then Use. Keyboard works when calculator is focused.</p>
                                            </div>
                                        </div>
                                    </aside>
                                </div>
                            </div>
                            <p class="q-pricing-footer-note"><i class="fas fa-shield-alt"></i> All costs are in INR • Prices are indicative and subject to change • T&amp;C Apply</p>

                            <div class="q-tour-cost-card" id="qTourCostCard" aria-label="Tour Cost Summary">
                                <div class="q-tour-cost-hd">
                                    <div class="q-tour-cost-hd-left">
                                        <span class="q-tour-cost-hd-ico" aria-hidden="true"><i class="fas fa-suitcase-rolling"></i></span>
                                        <h4 class="q-tour-cost-title">Tour Cost Summary</h4>
                                    </div>
                                    <span class="q-tour-cost-autosave" id="qTourCostAutoSave" title="Draft status">
                                        <i class="fas fa-check"></i> Auto Saved
                                    </span>
                                </div>
                                <div class="q-tour-cost-body" id="qTourCostRows"></div>
                                <div class="q-tour-cost-grand-wrap">
                                    <div class="q-tour-cost-grand">
                                        <div class="q-tour-cost-grand-left">
                                            <span class="q-tour-cost-grand-ico" aria-hidden="true"><i class="fas fa-award"></i></span>
                                            <div class="q-tour-cost-grand-text">
                                                <span class="q-tour-cost-grand-label">Grand Total</span>
                                                <span class="q-tour-cost-grand-sub">Total amount to be paid</span>
                                            </div>
                                        </div>
                                        <span class="q-tour-cost-grand-divider" aria-hidden="true"></span>
                                        <strong class="q-tour-cost-grand-amount" id="qTourCostGrand">INR 0.00</strong>
                                    </div>
                                </div>
                            </div>

                            <!-- Legacy fields kept in sync with the active hotel option for save/preview -->
                            <input type="hidden" name="price_per_adult" id="q_price_per_adult" value="">
                            <input type="hidden" id="q_quotation_total" value="0">
                            <input type="hidden" id="q_total_cost" value="0">
                            <input type="hidden" id="q_package_total" value="0">
                            <input type="hidden" id="q_profit_percent" value="">
                            <input type="hidden" id="q_profit_amount" value="">
                            <input type="hidden" id="q_tour_cost_json" name="tour_cost_json" value="">

                            <div class="q-check-row mt-3">
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" id="q_without_itinerary" name="without_itinerary" value="1">
                                    <label class="custom-control-label" for="q_without_itinerary">Without Itinerary</label>
                                </div>
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" id="q_hide_gst_note" name="hide_gst_note" value="1">
                                    <label class="custom-control-label" for="q_hide_gst_note">Hide GST Note</label>
                                </div>
                            </div>

                            <div class="q-actions-bar">
                                <button type="button" class="btn btn-preview" id="qPreviewBtn"><i class="fas fa-eye mr-1"></i>Preview Quotation</button>
                                <button type="submit" class="btn btn-save" id="qSaveBtn"><i class="fas fa-save mr-1"></i>Save Quotation</button>
                            </div>
                            </div>
                        </div>
                        </div>

                            </div>

                            <?php if ($showSaveDraft) { ?>
                            <div class="q-wizard-nav q-wizard-nav-draft-only">
                                <div class="q-wizard-nav-left">
                                    <button type="button" class="btn btn-outline-secondary btn-sm" id="qSaveDraftBtn" title="Save progress and continue later">
                                        <i class="fas fa-bookmark mr-1"></i> Save Draft
                                    </button>
                                </div>
                            </div>
                            <?php } ?>
                            <button type="button" class="d-none" id="qWizardPrev" aria-hidden="true" tabindex="-1"></button>
                            <button type="button" class="d-none" id="qWizardNext" aria-hidden="true" tabindex="-1"></button>
                            <span class="d-none" id="qWizardStepIndicator" aria-hidden="true"></span>
                        </div>

                    </form>

                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>

    <?php include __DIR__ . '/includes/quotation_flight_search_modal.php'; ?>
    <?php include __DIR__ . '/includes/quotation_itinerary_image_modal.php'; ?>
    <?php include __DIR__ . '/includes/quotation_supplier_mail_modal.php'; ?>

    <div class="modal fade q-day-ai-modal" id="qDayAiModal" tabindex="-1" role="dialog" aria-labelledby="qDayAiModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <div class="q-day-ai-modal-hd">
                        <div class="q-day-ai-modal-icon"><i class="fas fa-magic"></i></div>
                        <div>
                            <h5 class="modal-title mb-0" id="qDayAiModalLabel">Suggest Day</h5>
                            <p class="q-day-ai-modal-sub mb-0" id="qDayAiModalSub">Describe what you want for this day</p>
                        </div>
                    </div>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="form-group mb-0">
                        <label class="q-label" for="qDayAiPrompt">What do you need on this day?</label>
                        <textarea class="form-control" id="qDayAiPrompt" rows="4" placeholder="e.g. Amber Fort in the morning, City Palace &amp; local bazaar in the evening, relaxed pace for family..."></textarea>
                        <small class="form-text text-muted">The day title and activities will be generated from your request.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light border" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn q-day-ai-generate" id="qDayAiGenerate">
                        <i class="fas fa-bolt mr-1"></i> Generate
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade q-city-create-modal" id="qCityCreateModal" tabindex="-1" role="dialog" aria-labelledby="qCityCreateModalLabel" aria-hidden="true" data-backdrop="static">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <form id="qCityCreateForm" action="#" method="post" onsubmit="return false;">
                    <div class="modal-header">
                        <h5 class="modal-title mb-0" id="qCityCreateModalLabel"><i class="fas fa-city mr-2"></i>Create City</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-danger d-none" id="qCityCreateError"></div>
                        <div class="form-group">
                            <label class="label-req" for="qCityCreateCountry">Country</label>
                            <select class="form-control" id="qCityCreateCountry" name="country_id" required>
                                <option value="" selected disabled>Select Country</option>
                                <?php foreach ($qCountries as $country): ?>
                                    <option value="<?= (int) $country['id'] ?>"><?= htmlspecialchars($country['name'], ENT_QUOTES, 'UTF-8') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="qCityCreateState">State</label>
                            <select class="form-control" id="qCityCreateState" name="state_id">
                                <option value="">Select State (optional)</option>
                            </select>
                        </div>
                        <div class="form-group mb-0">
                            <label class="label-req" for="qCityCreateName">City Name</label>
                            <input type="text" class="form-control" id="qCityCreateName" name="city_name" placeholder="Enter city name" required autocomplete="off">
                        </div>
                        <input type="hidden" name="is_active" value="1">
                    </div>
                    <div class="modal-footer justify-content-between">
                        <button type="button" class="btn btn-light border" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="qCityCreateSubmit">
                            <i class="fas fa-plus-circle mr-1"></i>Create City
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade q-city-create-modal" id="qHotelCreateModal" tabindex="-1" role="dialog" aria-labelledby="qHotelCreateModalLabel" aria-hidden="true" data-backdrop="static">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <form id="qHotelCreateForm" action="#" method="post" onsubmit="return false;">
                    <div class="modal-header">
                        <h5 class="modal-title mb-0" id="qHotelCreateModalLabel"><i class="fas fa-hotel mr-2"></i>Create Hotel</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-danger d-none" id="qHotelCreateError"></div>
                        <div class="form-group">
                            <label>Destination</label>
                            <input type="text" class="form-control" id="qHotelCreateDestName" readonly>
                            <input type="hidden" id="qHotelCreateDestId" value="">
                        </div>
                        <div class="form-group">
                            <label class="label-req">City</label>
                            <input type="text" class="form-control" id="qHotelCreateCityName" readonly>
                            <input type="hidden" id="qHotelCreateCityId" value="">
                            <small class="form-text text-muted">Select or create the city on the hotel row first.</small>
                        </div>
                        <div class="form-group">
                            <label class="label-req" for="qHotelCreateName">Hotel Name</label>
                            <input type="text" class="form-control" id="qHotelCreateName" placeholder="Enter hotel name" required autocomplete="off">
                        </div>
                        <div class="form-group">
                            <label class="label-req" for="qHotelCreateStar">Star Category</label>
                            <select class="form-control" id="qHotelCreateStar" required>
                                <option>1 Star</option>
                                <option>2 Star</option>
                                <option selected>3 Star</option>
                                <option>4 Star</option>
                                <option>5 Star</option>
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="qHotelCreateRoom">Room Type</label>
                                    <input type="text" class="form-control" id="qHotelCreateRoom" placeholder="e.g. Deluxe Room" autocomplete="off">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="qHotelCreateMeal">Meal Plan</label>
                                    <input type="text" class="form-control" id="qHotelCreateMeal" placeholder="CP / MAP / AP" autocomplete="off">
                                </div>
                            </div>
                        </div>
                        <div class="form-group mb-0">
                            <label for="qHotelCreateRate">Rate (₹)</label>
                            <input type="number" step="0.01" min="0" class="form-control" id="qHotelCreateRate" placeholder="0">
                        </div>
                    </div>
                    <div class="modal-footer justify-content-between">
                        <button type="button" class="btn btn-light border" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="qHotelCreateSubmit">
                            <i class="fas fa-plus-circle mr-1"></i>Create Hotel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="qPreviewModal" tabindex="-1" role="dialog" aria-labelledby="qPreviewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <div>
                        <h5 class="modal-title mb-0" id="qPreviewModalLabel">Quotation Preview</h5>
                        <small class="text-muted">Click any text to edit directly</small>
                        <small id="qPreviewUnsavedHint" class="text-warning d-none ml-2"><i class="fas fa-circle" style="font-size:7px;vertical-align:middle;"></i> Unsaved changes</small>
                    </div>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div id="qPreviewPrintArea" class="q-preview-doc"></div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-success btn-sm d-none" id="qPreviewSaveBtn" disabled>
                        <i class="fas fa-save mr-1"></i>Save Changes
                    </button>
                    <button type="button" class="btn btn-primary btn-sm" id="qPreviewPrintBtn">
                        <i class="fas fa-print mr-1"></i> Print
                    </button>
                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../includes/footer-links.php'; ?>

    <script>
        var QUOTATION_PREFILL = <?= json_encode($prefill, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: 'null' ?>;
        var QUOTATION_DESTINATIONS = <?= json_encode($destinations, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '[]' ?>;
        var QUOTATION_PREVIEW_META = <?= json_encode($qPreviewMeta, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '{}' ?>;
        var Q_SUPPLIER_MAIL_CATALOG = <?= json_encode($qSupplierMailCatalog, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '[]' ?>;
        var Q_HOTEL_SUPPLIERS = <?= json_encode($qHotelSuppliers, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '[]' ?>;
        var Q_FLIGHT_SUPPLIERS = <?= json_encode($qFlightSuppliers, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '[]' ?>;
        var Q_DESTINATION_NAME_TO_ID = <?= json_encode($qDestinationNameToId, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '{}' ?>;
        var Q_DESTINATION_COUNTRY_ID_BY_NAME = <?= json_encode($qDestinationCountryIdByName, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '{}' ?>;
        var QUOTATION_TERMS_MASTER = <?= json_encode($quotationTermsMaster, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '{}' ?>;
        var Q_SUPPLIER_MAIL_TEMPLATE = <?= json_encode([
            'subject' => $qMailSubject,
            'body_html' => $qMailBodyHtml,
            'meta' => $qMailMeta,
        ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '{}' ?>;
    </script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0/dist/js/select2.min.js"></script>
    <script src="crm/assets/quotation_generator.js?v=110"></script>
    <script src="crm/assets/quotation_flight_search.js?v=13"></script>
    <script src="crm/assets/quotation_itinerary_images.js?v=1"></script>
    <script src="crm/assets/quotation_supplier_mail.js?v=21"></script>
    <script>
        $(function () {
            if (window.QSupplierMail) {
                window.QSupplierMail.init({
                    suppliers: window.Q_SUPPLIER_MAIL_CATALOG || [],
                    destinationNameToId: window.Q_DESTINATION_NAME_TO_ID || {},
                    mailTemplate: window.Q_SUPPLIER_MAIL_TEMPLATE || {}
                });
            }
        });
    </script>
</body>

</html>
