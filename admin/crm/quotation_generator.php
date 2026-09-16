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
    // Prefer live lead guest counts so Edit Lead → Edit Quotation stays in sync
    if ($leadRow && !$isArchivedView && !empty($prefill)) {
        $leadGuestPrefill = crmLeadRowToQuotationPrefill($leadRow, $destinationLookup);
        if (isset($leadGuestPrefill['no_of_adults'])) {
            $prefill['no_of_adults'] = max(1, (int) $leadGuestPrefill['no_of_adults']);
        }
        if (isset($leadGuestPrefill['no_of_children'])) {
            $prefill['no_of_children'] = max(0, (int) $leadGuestPrefill['no_of_children']);
        }
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
$qPreviewOnly = isset($_GET['preview_only']) && (string) $_GET['preview_only'] === '1';
if ($qPreviewOnly && !isset($_GET['mz_embed'])) {
    $_GET['mz_embed'] = '1';
}

$qPreviewMeta = [
    'logo' => 'img/web-logo.png',
    'expert_name' => 'Raju Gupta',
    'expert_title' => 'Holiday Expert',
    'expert_photo' => 'img/holiday-expert.png',
    'phone' => '+91 9709400140',
    'phone_alt' => '+91 9709100140',
    'email' => 'info@multizonetravels.com',
    'website' => 'www.multizonetravels.com',
    'address' => 'Bye Pass Road, Dibdih, Doranda, Ranchi - 834002, Jharkhand, India',
    'services' => 'FLIGHTS • HOTELS • HOLIDAYS • VISA • FOREX',
    'social' => [
        ['type' => 'facebook', 'url' => 'https://www.facebook.com/', 'icon' => 'fab fa-facebook-f'],
        ['type' => 'twitter', 'url' => 'https://twitter.com/', 'icon' => 'fab fa-twitter'],
        ['type' => 'linkedin', 'url' => 'https://www.linkedin.com/', 'icon' => 'fab fa-linkedin-in'],
        ['type' => 'instagram', 'url' => 'https://www.instagram.com/', 'icon' => 'fab fa-instagram'],
        ['type' => 'youtube', 'url' => 'https://www.youtube.com/', 'icon' => 'fab fa-youtube'],
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
    <link rel="stylesheet" href="plugins/jquery-ui/jquery-ui.min.css">
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
            cursor: default;
        }

        .crm-quotation-gen .q-day-head-main {
            display: flex;
            align-items: center;
            gap: 0.55rem;
            min-width: 0;
            flex: 1 1 auto;
        }

        .crm-quotation-gen .q-wizard-step[data-q-step="5"] .q-terms-list {
            display: flex;
            flex-direction: column;
            gap: 0.65rem;
        }

        .crm-quotation-gen .q-wizard-step[data-q-step="5"] .q-terms-item,
        .crm-quotation-gen .q-wizard-step[data-q-step="5"] .q-accordion-item {
            border: 1px solid #e8ecf1;
            border-radius: 12px;
            margin-bottom: 0;
            overflow: hidden;
            background: #fff;
            box-shadow: none;
        }

        .crm-quotation-gen .q-wizard-step[data-q-step="5"] .q-terms-item-head,
        .crm-quotation-gen .q-wizard-step[data-q-step="5"] .q-accordion-head {
            background: #fff;
            border-bottom: 1px solid transparent;
            padding: 0.85rem 1rem;
            font-weight: 700;
            font-size: 0.92rem;
            color: #0f172a;
            gap: 0.55rem;
        }

        .crm-quotation-gen .q-wizard-step[data-q-step="5"] .q-terms-item-head:hover,
        .crm-quotation-gen .q-wizard-step[data-q-step="5"] .q-accordion-head:hover {
            background: #fafbfc;
        }

        .crm-quotation-gen .q-wizard-step[data-q-step="5"] .q-terms-item-head:not(.collapsed),
        .crm-quotation-gen .q-wizard-step[data-q-step="5"] .q-accordion-head:not(.collapsed) {
            border-bottom-color: #eef2f7;
        }

        .crm-quotation-gen .q-wizard-step[data-q-step="5"] .q-accordion-head.collapsed {
            border-bottom: none;
        }

        .crm-quotation-gen .q-terms-item-head-main {
            display: flex;
            align-items: center;
            gap: 0.55rem;
            min-width: 0;
            flex: 1 1 auto;
        }

        .crm-quotation-gen .q-terms-item-label {
            min-width: 0;
            font-weight: 700;
            color: #0f172a;
        }

        .crm-quotation-gen .q-wizard-step[data-q-step="5"] .q-terms-item-head .toggle-icon,
        .crm-quotation-gen .q-wizard-step[data-q-step="5"] .q-accordion-head i.toggle-icon {
            flex-shrink: 0;
            color: #e11d2e;
            font-size: 0.72rem;
            width: auto;
            transition: transform 0.2s ease;
        }

        .crm-quotation-gen .q-wizard-step[data-q-step="5"] .q-terms-item-body,
        .crm-quotation-gen .q-wizard-step[data-q-step="5"] .q-accordion-body {
            padding: 0.85rem 1rem 1rem;
            background: #fff;
        }

        .crm-quotation-gen .q-wizard-step[data-q-step="5"] .note-editor.note-frame {
            border: 1px solid #e8ecf1;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: none;
        }

        .crm-quotation-gen .q-wizard-step[data-q-step="5"] .note-toolbar {
            background: #f8fafc !important;
            border-bottom: 1px solid #eef2f7 !important;
        }

        .crm-quotation-gen .q-wizard-step[data-q-step="5"] .note-editing-area .note-editable {
            background: #fff;
            color: #0f172a;
            min-height: 140px;
        }

        .crm-quotation-gen .q-day-card {
            border: 1px solid #e8ecf1;
            border-radius: 14px;
            margin-bottom: 0.85rem;
            overflow: hidden;
            box-shadow: 0 6px 24px rgba(15, 23, 42, 0.05);
            background: #fff;
            display: none;
        }

        .crm-quotation-gen .q-day-card.is-active {
            display: block;
        }

        .crm-quotation-gen .q-day-toolbar {
            background: #fff;
            padding: 1rem 1.15rem;
            border-bottom: 1px solid #eef2f7;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.85rem;
            flex-wrap: wrap;
        }

        .crm-quotation-gen .q-day-toolbar-left {
            display: flex;
            align-items: center;
            gap: 0.7rem;
            min-width: 0;
            flex: 1 1 auto;
        }

        .crm-quotation-gen .q-day-toolbar-text {
            min-width: 0;
        }

        .crm-quotation-gen .q-day-head-label {
            font-weight: 700;
            font-size: 0.98rem;
            color: #0f172a;
            line-height: 1.25;
        }

        .crm-quotation-gen .q-day-head-sub {
            margin-top: 0.15rem;
            font-size: 0.82rem;
            font-weight: 500;
            color: #94a3b8;
        }

        .crm-quotation-gen .q-day-toolbar-right {
            display: flex;
            align-items: center;
            gap: 0.45rem;
            flex-wrap: wrap;
        }

        .crm-quotation-gen .q-day-nav-btn {
            border: 1.5px solid #e2e8f0;
            background: #fff;
            color: #334155;
            font-weight: 600;
            font-size: 0.78rem;
            border-radius: 8px;
            padding: 0.45rem 0.8rem;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            line-height: 1.2;
        }

        .crm-quotation-gen .q-day-nav-btn:hover:not(:disabled) {
            background: #f8fafc;
            border-color: #cbd5e1;
            color: #0f172a;
        }

        .crm-quotation-gen .q-day-nav-btn:disabled {
            opacity: 0.45;
            cursor: not-allowed;
        }

        .crm-quotation-gen .q-day-nav-btn.q-day-nav-primary {
            background: #c62828;
            border-color: #c62828;
            color: #fff;
        }

        .crm-quotation-gen .q-day-nav-btn.q-day-nav-primary:hover:not(:disabled) {
            background: #b71c1c;
            border-color: #b71c1c;
            color: #fff;
        }

        .crm-quotation-gen .q-day-more-btn {
            width: 36px;
            height: 36px;
            padding: 0;
            border: 1.5px solid #e2e8f0;
            border-radius: 8px;
            background: #fff;
            color: #64748b;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .crm-quotation-gen .q-day-more-btn:hover {
            background: #f8fafc;
            color: #0f172a;
        }

        .crm-quotation-gen .q-day-ai-suggest {
            flex-shrink: 0;
            border-radius: 8px;
            border: 1.5px solid #e11d2e;
            background: #fff;
            color: #e11d2e;
            font-weight: 700;
            font-size: 0.78rem;
            padding: 0.4rem 0.85rem;
            line-height: 1.3;
        }

        .crm-quotation-gen .q-day-ai-suggest:hover:not(:disabled) {
            background: #fff1f2;
            border-color: #e11d2e;
            color: #be123c;
        }

        .crm-quotation-gen .q-day-ai-suggest:disabled {
            opacity: 0.7;
            cursor: wait;
        }

        .crm-quotation-gen .q-day-calendar-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: #c62828;
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
            font-size: 0.95rem;
        }

        /* —— Itinerary redesign (package search + selected + suppliers) —— */
        .crm-quotation-gen .q-itin-section .q-wizard-section-subtitle {
            margin: 0.15rem 0 0;
            color: #64748b;
            font-size: 0.84rem;
            font-weight: 500;
        }

        .crm-quotation-gen .q-itin-workspace {
            display: grid;
            grid-template-columns: minmax(0, 1.7fr) minmax(280px, 0.9fr);
            gap: 1rem;
            margin-bottom: 1rem;
            align-items: stretch;
        }

        @media (max-width: 991.98px) {
            .crm-quotation-gen .q-itin-workspace {
                grid-template-columns: 1fr;
                align-items: start;
            }

            .crm-quotation-gen .q-itin-side-col,
            .crm-quotation-gen .q-itin-pkg-card.q-itin-pkg-selected-card {
                height: auto;
                min-height: 0;
            }
        }

        .crm-quotation-gen .q-itin-pkg-card.q-itin-pkg-search-card {
            overflow: visible;
            position: relative;
            z-index: 5;
        }

        .crm-quotation-gen .q-itin-main-col {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            min-width: 0;
            position: relative;
            z-index: 4;
            height: 100%;
        }

        .crm-quotation-gen .q-itin-side-col {
            min-width: 0;
            display: flex;
            flex-direction: column;
            height: 100%;
            min-height: 100%;
        }

        .crm-quotation-gen .q-itin-pkg-card.q-itin-pkg-selected-card {
            flex: 1 1 auto;
            height: 100%;
            min-height: 100%;
            display: flex;
            flex-direction: column;
        }

        .crm-quotation-gen .q-itin-pkg-card {
            background: #fff;
            border: 1px solid #e8ecf1;
            border-radius: 14px;
            padding: 1rem 1.15rem 1.15rem;
            box-shadow: 0 6px 22px rgba(15, 23, 42, 0.05);
        }

        .crm-quotation-gen .q-itin-pkg-card-hd {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 0.75rem;
            margin-bottom: 0.9rem;
            flex-wrap: wrap;
        }

        .crm-quotation-gen .q-itin-pkg-hd-left {
            display: flex;
            align-items: flex-start;
            gap: 0.7rem;
            min-width: 0;
            flex: 1 1 auto;
        }

        .crm-quotation-gen .q-itin-pkg-hd-icon {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: #fdecee;
            color: #c62828;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            flex: 0 0 auto;
        }

        .crm-quotation-gen .q-itin-pkg-hd-text {
            min-width: 0;
        }

        .crm-quotation-gen .q-itin-pkg-hd-label {
            display: block;
            font-weight: 800;
            font-size: 1rem;
            color: #0f172a;
            line-height: 1.25;
        }

        .crm-quotation-gen .q-itin-pkg-hd-sub {
            display: block;
            margin-top: 0.2rem;
            font-size: 0.8rem;
            color: #94a3b8;
            font-weight: 500;
            line-height: 1.35;
        }

        .crm-quotation-gen .q-itin-search-body {
            display: block;
        }

        .crm-quotation-gen .q-itin-pkg-search-wrap {
            position: relative;
            z-index: 30;
        }

        .crm-quotation-gen .q-itin-pkg-search-wrap .js-q-package-search {
            padding-left: 2.45rem;
            padding-right: 2.4rem;
            height: 46px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            font-size: 0.9rem;
            background: #fff;
        }

        .crm-quotation-gen .q-itin-pkg-search-wrap .js-q-package-search:focus {
            border-color: #f1a9ae;
            box-shadow: 0 0 0 3px rgba(198, 40, 40, 0.12);
        }

        .crm-quotation-gen .q-itin-pkg-search-icon-left {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            pointer-events: none;
            z-index: 2;
            font-size: 0.9rem;
        }

        .crm-quotation-gen .q-itin-pkg-search-clear {
            position: absolute;
            right: 10px;
            top: 23px;
            transform: translateY(-50%);
            width: 26px;
            height: 26px;
            border: 0;
            border-radius: 50%;
            background: #f1f5f9;
            color: #64748b;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            z-index: 3;
            cursor: pointer;
        }

        .crm-quotation-gen .q-itin-pkg-search-clear:hover {
            background: #ffe4e6;
            color: #c62828;
        }

        .crm-quotation-gen .q-itin-pkg-menu {
            position: absolute;
            left: 0;
            right: 0;
            top: calc(100% + 6px);
            z-index: 50;
            margin-top: 0;
            background: #fff;
            border: 1px solid #e8ecf1;
            border-radius: 12px;
            box-shadow: 0 14px 36px rgba(15, 23, 42, 0.16);
            max-height: 280px;
            overflow: auto;
            padding: 0.35rem;
        }

        .crm-quotation-gen .q-itin-pkg-item {
            display: flex;
            align-items: flex-start;
            gap: 0.65rem;
            width: 100%;
            text-align: left;
            border: 0;
            background: transparent;
            padding: 0.7rem 0.75rem;
            border-radius: 10px;
            cursor: pointer;
            color: #0f172a;
        }

        .crm-quotation-gen .q-itin-pkg-item:hover {
            background: #f8fafc;
        }

        .crm-quotation-gen .q-itin-pkg-item.is-active {
            background: #fff1f2;
        }

        .crm-quotation-gen .q-itin-pkg-item-pin {
            color: #64748b;
            flex: 0 0 auto;
            width: 1rem;
            text-align: center;
            margin-top: 0.15rem;
        }

        .crm-quotation-gen .q-itin-pkg-item.is-active .q-itin-pkg-item-pin {
            color: #c62828;
        }

        .crm-quotation-gen .q-itin-pkg-item-text {
            display: flex;
            flex-direction: column;
            gap: 0.15rem;
            min-width: 0;
            flex: 1 1 auto;
        }

        .crm-quotation-gen .q-itin-pkg-item-title {
            display: block;
            font-weight: 700;
            font-size: 0.88rem;
            color: #0f172a;
            line-height: 1.3;
            min-width: 0;
        }

        .crm-quotation-gen .q-itin-pkg-item.is-active .q-itin-pkg-item-title {
            color: #b71c1c;
        }

        .crm-quotation-gen .q-itin-pkg-item-days {
            display: block;
            font-size: 0.72rem;
            font-weight: 500;
            color: #94a3b8;
            line-height: 1.4;
            white-space: normal;
            overflow: visible;
            word-break: break-word;
        }

        .crm-quotation-gen .q-itin-pkg-item.is-active .q-itin-pkg-item-days {
            color: #fb7185;
        }

        .crm-quotation-gen .q-itin-pkg-empty {
            padding: 0.9rem 0.75rem;
            color: #64748b;
            font-size: 0.84rem;
            text-align: center;
        }

        .crm-quotation-gen .q-itin-selected-card-hd {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
            margin-bottom: 0.9rem;
        }

        .crm-quotation-gen .q-itin-selected-hd {
            display: flex;
            align-items: center;
            gap: 0.55rem;
            min-width: 0;
        }

        .crm-quotation-gen .q-itin-selected-badge-icon {
            width: 34px;
            height: 34px;
            border-radius: 10px;
            background: #c62828;
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            flex: 0 0 auto;
        }

        .crm-quotation-gen .q-itin-selected-eyebrow {
            font-size: 0.95rem;
            font-weight: 800;
            color: #0f172a;
        }

        .crm-quotation-gen .q-itin-selected-more {
            width: 34px;
            height: 34px;
            padding: 0;
            border: 0;
            background: transparent;
            color: #94a3b8;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .crm-quotation-gen .q-itin-selected-more:hover {
            background: #f8fafc;
            color: #0f172a;
        }

        .crm-quotation-gen .q-itin-selected-empty {
            flex: 1 1 auto;
            min-height: 220px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            color: #94a3b8;
            padding: 1rem 0.5rem;
        }

        .crm-quotation-gen .q-itin-selected-empty-icon {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            background: #fdecee;
            color: #c62828;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            margin-bottom: 0.75rem;
        }

        .crm-quotation-gen .q-itin-selected-empty p {
            margin: 0;
            font-size: 0.85rem;
            max-width: 220px;
            line-height: 1.45;
        }

        .crm-quotation-gen .q-itin-selected-filled {
            flex: 1 1 auto;
            display: flex;
            flex-direction: column;
            min-height: 0;
        }

        .crm-quotation-gen .q-itin-selected-preview {
            display: flex;
            gap: 0.85rem;
            align-items: flex-start;
            margin-bottom: 1rem;
            flex: 1 1 auto;
        }

        .crm-quotation-gen .q-itin-selected-image-wrap {
            width: 92px;
            height: 92px;
            border-radius: 12px;
            overflow: hidden;
            background: #f1f5f9;
            flex: 0 0 auto;
            position: relative;
        }

        .crm-quotation-gen .q-itin-selected-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .crm-quotation-gen .q-itin-selected-image-fallback {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #c62828;
            background: linear-gradient(145deg, #ffe4e6 0%, #fdecee 100%);
            font-size: 1.5rem;
        }

        .crm-quotation-gen .q-itin-selected-info {
            min-width: 0;
            flex: 1 1 auto;
        }

        .crm-quotation-gen .q-itin-selected-title {
            margin: 0 0 0.55rem;
            font-size: 1.05rem;
            font-weight: 800;
            color: #0f172a;
            line-height: 1.3;
        }

        .crm-quotation-gen .q-itin-selected-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 0.4rem;
            margin-bottom: 0.55rem;
        }

        .crm-quotation-gen .q-itin-selected-meta span {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            background: #f1f5f9;
            color: #475569;
            border-radius: 999px;
            padding: 0.22rem 0.6rem;
            font-size: 0.72rem;
            font-weight: 600;
            line-height: 1.2;
        }

        .crm-quotation-gen .q-itin-selected-meta i {
            color: #c62828;
            font-size: 0.7rem;
        }

        .crm-quotation-gen .q-itin-selected-updated {
            margin: 0;
            font-size: 0.76rem;
            color: #94a3b8;
            display: flex;
            align-items: center;
            gap: 0.35rem;
        }

        .crm-quotation-gen .q-itin-load-btn {
            width: 100%;
            border: 0;
            border-radius: 10px;
            background: #c62828;
            color: #fff;
            font-weight: 700;
            padding: 0.75rem 1rem;
            box-shadow: 0 8px 18px rgba(198, 40, 40, 0.22);
            margin-top: auto;
        }

        .crm-quotation-gen .q-itin-load-btn:hover:not(:disabled) {
            background: #b71c1c;
            color: #fff;
        }

        .crm-quotation-gen .q-itin-load-btn:disabled {
            opacity: 0.55;
            cursor: not-allowed;
            box-shadow: none;
        }

        .crm-quotation-gen .q-itin-suppliers-panel {
            background: #fff;
            border: 1px solid #e8ecf1;
            border-radius: 14px;
            padding: 1rem 1.15rem 1.1rem;
            box-shadow: 0 6px 22px rgba(15, 23, 42, 0.05);
        }

        .crm-quotation-gen .q-itin-suppliers-hd-left {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            min-width: 0;
        }

        .crm-quotation-gen .q-itin-suppliers-title {
            font-size: 1rem;
            font-weight: 800;
            color: #0f172a;
        }

        .crm-quotation-gen .q-itin-add-supplier-btn {
            margin-top: 0;
            border: 1.5px solid #f1a9ae;
            color: #c62828;
            background: #fff;
            border-radius: 10px;
            font-weight: 700;
            font-size: 0.8rem;
            padding: 0.4rem 0.8rem;
        }

        .crm-quotation-gen .q-itin-add-supplier-btn:hover {
            background: #fff1f2;
            color: #b71c1c;
            border-color: #e57373;
        }

        .crm-quotation-gen .q-itinerary-suppliers {
            margin-top: 0;
        }

        .crm-quotation-gen .q-itinerary-suppliers-hd {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            margin-bottom: 0.85rem;
            flex-wrap: wrap;
        }

        .crm-quotation-gen .q-itin-suppliers-table {
            width: 100%;
            margin: 0;
            border-collapse: separate;
            border-spacing: 0;
        }

        .crm-quotation-gen .q-itin-suppliers-table thead th {
            font-size: 0.72rem;
            font-weight: 700;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            border-bottom: 1px solid #eef2f7;
            padding: 0.45rem 0.55rem;
            background: transparent;
        }

        .crm-quotation-gen .q-itin-suppliers-table tbody td {
            padding: 0.55rem;
            vertical-align: middle;
            border-bottom: 1px solid #f1f5f9;
        }

        .crm-quotation-gen .q-itin-suppliers-table tbody tr:last-child td {
            border-bottom: 0;
        }

        .crm-quotation-gen .q-itin-sup-idx {
            font-weight: 700;
            color: #64748b;
            font-size: 0.88rem;
        }

        .crm-quotation-gen .q-itin-supplier-row .q-itin-supplier,
        .crm-quotation-gen .q-itin-supplier-row .q-itin-rate,
        .crm-quotation-gen .q-itin-supplier-row .select2-container .select2-selection--single {
            border-radius: 8px !important;
            min-height: 38px;
        }

        .crm-quotation-gen .q-itin-supplier-row .q-itin-supplier-remove {
            height: 36px;
            width: 36px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            margin-bottom: 0;
            border: 1px solid #fecaca;
            background: #fff1f2;
            color: #c62828;
        }

        .crm-quotation-gen .q-itin-supplier-row .q-itin-supplier-remove:hover:not(:disabled) {
            background: #ffe4e6;
            color: #b71c1c;
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

        .crm-quotation-gen .q-itin-days {
            margin-top: 0.25rem;
        }

        .crm-quotation-gen .q-itin-days-actions {
            display: flex;
            justify-content: flex-end;
            margin: 0.15rem 0 0.65rem;
        }

        .crm-quotation-gen .q-itin-view-all-btn {
            border: 1.5px solid #f1a9ae;
            background: #fff;
            color: #c62828;
            font-weight: 700;
            font-size: 0.8rem;
            border-radius: 10px;
            padding: 0.45rem 0.85rem;
            line-height: 1.2;
        }

        .crm-quotation-gen .q-itin-view-all-btn:hover {
            background: #fff1f2;
            color: #b71c1c;
            border-color: #e57373;
        }

        .q-itin-loaded-modal .modal-content {
            border: 0;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 12px 40px rgba(15, 23, 42, 0.18);
        }

        .q-itin-loaded-body {
            padding: 1.5rem 1.25rem 0.75rem;
        }

        .q-itin-loaded-icon {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: #ecfdf5;
            color: #059669;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.35rem;
            margin-bottom: 0.85rem;
        }

        .q-itin-loaded-title {
            font-weight: 800;
            font-size: 1.1rem;
            color: #0f172a;
            margin-bottom: 0.4rem;
        }

        .q-itin-loaded-msg {
            font-size: 0.9rem;
            color: #64748b;
            line-height: 1.45;
        }

        .q-itin-loaded-ok {
            min-width: 110px;
            border: 0;
            border-radius: 10px;
            background: #c62828;
            color: #fff;
            font-weight: 700;
            padding: 0.5rem 1.1rem;
        }

        .q-itin-loaded-ok:hover {
            background: #b71c1c;
            color: #fff;
        }

        .q-full-itin-modal .modal-content {
            border: 0;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 12px 40px rgba(15, 23, 42, 0.15);
        }

        .q-full-itin-modal .modal-header {
            border-bottom: 1px solid #eef2f7;
            align-items: flex-start;
            padding: 1rem 1.15rem;
        }

        .q-full-itin-modal-hd {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
        }

        .q-full-itin-modal-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: #c62828;
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .q-full-itin-modal .modal-title {
            font-size: 1.05rem;
            font-weight: 800;
            color: #0f172a;
        }

        .q-full-itin-modal-sub {
            font-size: 0.8rem;
            color: #64748b;
            margin-top: 0.15rem;
        }

        .q-full-itin-modal .modal-body {
            padding: 0.85rem 1rem;
            max-height: min(70vh, 560px);
            overflow-y: auto;
            background: #f8fafc;
        }

        .q-full-itin-list {
            display: flex;
            flex-direction: column;
            gap: 0.55rem;
        }

        .q-full-itin-item {
            display: block;
            width: 100%;
            text-align: left;
            border: 1px solid #e8ecf1;
            background: #fff;
            border-radius: 12px;
            padding: 0.85rem 0.95rem;
            cursor: pointer;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }

        .q-full-itin-item:hover {
            border-color: #f1a9ae;
            box-shadow: 0 6px 18px rgba(198, 40, 40, 0.08);
        }

        .q-full-itin-item.is-current {
            border-color: #c62828;
            box-shadow: 0 0 0 2px rgba(198, 40, 40, 0.12);
        }

        .q-full-itin-item-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 0.75rem;
            margin-bottom: 0.35rem;
        }

        .q-full-itin-item-day {
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: #c62828;
        }

        .q-full-itin-item-date {
            font-size: 0.75rem;
            color: #94a3b8;
            font-weight: 500;
            white-space: nowrap;
        }

        .q-full-itin-item-title {
            font-size: 0.95rem;
            font-weight: 800;
            color: #0f172a;
            line-height: 1.3;
            margin-bottom: 0.35rem;
        }

        .q-full-itin-item-desc {
            font-size: 0.8rem;
            color: #64748b;
            line-height: 1.45;
            margin: 0 0 0.45rem;
        }

        .q-full-itin-item-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 0.4rem;
        }

        .q-full-itin-item-meta span {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            background: #f1f5f9;
            color: #475569;
            border-radius: 999px;
            padding: 0.18rem 0.55rem;
            font-size: 0.7rem;
            font-weight: 600;
        }

        .q-full-itin-item-meta i {
            color: #c62828;
            font-size: 0.68rem;
        }

        .q-full-itin-empty {
            text-align: center;
            color: #94a3b8;
            padding: 2rem 1rem;
            font-size: 0.9rem;
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

        .crm-quotation-gen .q-day-ai-btn {
            flex: 0 0 auto;
            border-radius: 8px;
            border: 0;
            background: #c62828;
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.78rem;
            padding: 0.45rem 0.75rem;
            line-height: 1.2;
        }

        .crm-quotation-gen .q-day-ai-btn:hover:not(:disabled) {
            background: #b71c1c;
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
            padding: 1rem 1.15rem 1.15rem;
        }

        .crm-quotation-gen .q-day-main-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.45fr) minmax(260px, 0.9fr);
            gap: 1rem;
            align-items: stretch;
        }

        @media (max-width: 991.98px) {
            .crm-quotation-gen .q-day-main-grid {
                grid-template-columns: 1fr;
            }
        }

        .crm-quotation-gen .q-floating-field {
            position: relative;
            margin-bottom: 0.85rem;
        }

        .crm-quotation-gen .q-floating-label {
            position: absolute;
            top: -0.55rem;
            left: 0.75rem;
            z-index: 2;
            background: #fff;
            padding: 0 0.3rem;
            font-size: 0.72rem;
            font-weight: 700;
            color: #c62828;
            margin: 0;
            line-height: 1;
        }

        .crm-quotation-gen .q-floating-field .q-day-title {
            border: 1.5px solid #c62828;
            border-radius: 8px;
            font-weight: 700;
            color: #0f172a;
            height: auto;
            min-height: 46px;
            padding: 0.7rem 0.85rem;
            box-shadow: none;
        }

        .crm-quotation-gen .q-floating-field .q-day-title:focus {
            border-color: #c62828;
            box-shadow: 0 0 0 0.15rem rgba(198, 40, 40, 0.12);
        }

        .crm-quotation-gen .q-day-editor-wrap {
            position: relative;
        }

        .crm-quotation-gen .q-day-editor-wrap .note-editor.note-frame {
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            overflow: hidden;
        }

        .crm-quotation-gen .q-day-body .note-editor {
            max-width: 100%;
        }

        .crm-quotation-gen .q-day-body .note-toolbar {
            background: #f8fafc !important;
            border-bottom: 1px solid #eef2f7 !important;
            padding: 0.35rem 0.45rem;
        }

        .crm-quotation-gen .q-day-body .note-editing-area .note-editable {
            min-height: 180px;
            font-size: 0.9rem;
            line-height: 1.55;
            color: #334155;
        }

        .crm-quotation-gen .q-day-char-count {
            text-align: right;
            margin-top: 0.35rem;
            font-size: 0.72rem;
            color: #94a3b8;
            font-weight: 500;
        }

        .crm-quotation-gen .q-day-image-panel {
            border: 1px solid #e8ecf1;
            border-radius: 12px;
            padding: 0.85rem;
            background: #fff;
            display: flex;
            flex-direction: column;
            gap: 0.65rem;
            height: 100%;
        }

        .crm-quotation-gen .q-day-image-hd {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
        }

        .crm-quotation-gen .q-day-image-hd-left {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            font-weight: 700;
            color: #0f172a;
            font-size: 0.88rem;
        }

        .crm-quotation-gen .q-day-image-hd-left i {
            color: #c62828;
        }

        .crm-quotation-gen .q-day-image-count {
            font-size: 0.75rem;
            color: #94a3b8;
            font-weight: 500;
        }

        .crm-quotation-gen .q-day-image-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.4rem;
        }

        .crm-quotation-gen .q-day-img-btn {
            border: 1.5px solid #e2e8f0;
            background: #fff;
            color: #334155;
            font-weight: 600;
            font-size: 0.74rem;
            border-radius: 8px;
            padding: 0.4rem 0.65rem;
            line-height: 1.2;
        }

        .crm-quotation-gen .q-day-img-btn:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
            color: #0f172a;
        }

        .crm-quotation-gen .q-day-img-btn.q-day-img-remove {
            color: #c62828;
            border-color: #fecaca;
        }

        .crm-quotation-gen .q-day-img-btn.q-day-img-remove:hover {
            background: #fff1f2;
            border-color: #fca5a5;
            color: #b71c1c;
        }

        .crm-quotation-gen .q-day-meta-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
            margin-top: 1rem;
        }

        @media (max-width: 767.98px) {
            .crm-quotation-gen .q-day-meta-row {
                grid-template-columns: 1fr;
            }

            .crm-quotation-gen .q-day-nav-btn span {
                display: none;
            }
        }

        .crm-quotation-gen .q-day-meta-card {
            border: 1px solid #e8ecf1;
            border-radius: 12px;
            background: #fff;
            padding: 0.75rem 0.85rem;
            display: flex;
            align-items: center;
            gap: 0.7rem;
            min-width: 0;
        }

        .crm-quotation-gen .q-day-meta-icon {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: #fdebee;
            color: #c62828;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
            font-size: 0.95rem;
        }

        .crm-quotation-gen .q-day-meta-text {
            min-width: 0;
            flex: 1 1 auto;
        }

        .crm-quotation-gen .q-day-meta-label {
            display: block;
            font-size: 0.72rem;
            color: #94a3b8;
            font-weight: 500;
            line-height: 1.2;
        }

        .crm-quotation-gen .q-day-meta-value {
            display: block;
            font-size: 0.95rem;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.3;
            margin-top: 0.1rem;
            min-height: 1.3em;
            outline: none;
            border-radius: 4px;
            cursor: text;
            padding: 0.05rem 0.15rem;
            margin-left: -0.15rem;
        }

        .crm-quotation-gen .q-day-meta-value.is-empty {
            color: #94a3b8;
            font-weight: 600;
        }

        .crm-quotation-gen .q-day-meta-value.is-editing,
        .crm-quotation-gen .q-day-meta-value:focus {
            background: #fff7f7;
            box-shadow: inset 0 -2px 0 #c62828;
            color: #0f172a;
            font-weight: 700;
        }

        .crm-quotation-gen .q-day-meta-actions {
            display: flex;
            align-items: center;
            gap: 0.15rem;
            flex: 0 0 auto;
            padding-left: 0.55rem;
            border-left: 1px solid #eef2f7;
        }

        .crm-quotation-gen .q-day-meta-actions .btn {
            border: 0;
            background: transparent;
            color: #64748b;
            font-size: 0.74rem;
            font-weight: 600;
            padding: 0.25rem 0.4rem;
            line-height: 1.2;
            box-shadow: none;
        }

        .crm-quotation-gen .q-day-meta-actions .btn:hover {
            color: #0f172a;
            background: #f8fafc;
        }

        .crm-quotation-gen .q-day-meta-actions .q-day-meta-remove {
            color: #c62828;
        }

        .crm-quotation-gen .q-day-meta-actions .q-day-meta-remove:hover {
            color: #b71c1c;
            background: #fff1f2;
        }

        .crm-quotation-gen .q-img-preview-wrap {
            flex: 1 1 auto;
            min-height: 180px;
            border: 1px solid #e8ecf1;
            border-radius: 10px;
            background: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            overflow: hidden;
        }

        .crm-quotation-gen .q-img-preview {
            width: 100%;
            height: 100%;
            max-height: 240px;
            object-fit: cover;
            border-radius: 10px;
            display: none;
        }

        .crm-quotation-gen .q-img-preview-empty {
            text-align: center;
            padding: 0.85rem;
            font-size: 0.78rem;
            color: var(--q-text-muted);
        }

        .crm-quotation-gen .q-img-preview-wrap.has-image .q-img-preview-empty {
            display: none;
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

        .crm-quotation-gen .q-flight-datetime input[type="time"]::-webkit-calendar-picker-indicator {
            opacity: 0;
            display: none;
            -webkit-appearance: none;
            width: 0;
            height: 0;
            margin: 0;
            padding: 0;
        }

        .crm-quotation-gen .q-flight-datetime input[type="time"]::-webkit-clear-button {
            display: none;
            -webkit-appearance: none;
        }

        .crm-quotation-gen input.js-q-date-input {
            cursor: pointer;
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

        /* ===== Hotel Details (red mockup design) ===== */
        #qWizardSection3 .q-wizard-section-subtitle {
            margin: 0.2rem 0 0;
            color: #6b7280;
            font-size: 0.86rem;
            font-weight: 500;
        }

        #qWizardSection3 .q-hotel-cat-toolbar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        #qWizardSection3 .q-hotel-cat-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 0.45rem;
            align-items: center;
        }

        #qWizardSection3 .q-hotel-cat-tab {
            border: 1px solid #d1d5db;
            background: #fff;
            color: #111827;
            border-radius: 999px;
            padding: 0.42rem 1.05rem;
            font-size: 0.82rem;
            font-weight: 700;
            cursor: pointer;
            line-height: 1.2;
        }

        #qWizardSection3 .q-hotel-cat-tab.is-active {
            background: #e11d2e;
            border-color: #e11d2e;
            color: #fff;
        }

        #qWizardSection3 .q-hotel-add-option-btn,
        #qWizardSection3 .q-hotel-remove-option-btn {
            border: 1px solid #e5e7eb;
            background: #fff;
            color: #6b7280;
            border-radius: 999px;
            padding: 0.38rem 0.8rem;
            font-size: 0.78rem;
            font-weight: 700;
            line-height: 1.2;
        }

        #qWizardSection3 .q-hotel-remove-option-btn {
            width: 36px;
            height: 36px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #e11d2e;
            border-color: #fecaca;
        }

        #qWizardSection3 .q-hotel-add-btn {
            margin-left: auto;
            background: #e11d2e !important;
            border: 1px solid #e11d2e !important;
            color: #fff !important;
            font-weight: 700;
            font-size: 0.84rem;
            border-radius: 999px !important;
            padding: 0.5rem 1.15rem !important;
            box-shadow: none !important;
            background-image: none !important;
        }

        #qWizardSection3 .q-hotel-add-btn:hover,
        #qWizardSection3 .q-hotel-add-btn:focus {
            background: #c4121a !important;
            border-color: #c4121a !important;
            color: #fff !important;
        }

        #qWizardSection3 .q-hotel-col-head {
            display: grid;
            grid-template-columns: 1.05fr 1.2fr 1.15fr 0.7fr 0.7fr 0.7fr 1.05fr 1.05fr 0.85fr 1.2fr 42px;
            gap: 0.4rem;
            align-items: center;
            margin: 0 0 0.45rem;
            padding: 0 0.1rem;
            color: #6b7280;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.01em;
        }

        #qWizardSection3 .q-hotel-col-head-action {
            width: 42px;
        }

        #qWizardSection3 .q-hotel-category {
            display: none;
            border: 0;
            background: transparent;
            padding: 0;
            margin: 0;
        }

        #qWizardSection3 .q-hotel-category.is-active {
            display: block;
        }

        #qWizardSection3 .q-hotel-row {
            border: 0;
            border-bottom: 1px solid #eef2f7;
            border-radius: 0;
            background: transparent;
            padding: 0.75rem 0;
            margin: 0;
            box-shadow: none !important;
            position: relative;
        }

        #qWizardSection3 .q-hotel-row:last-child {
            border-bottom: 0;
            padding-bottom: 0.25rem;
        }

        /* Hotel supplier = same Select2 control as Flight / Train (.q-ft-col-supplier) */
        #qWizardSection3 .q-hotel-field-supplier {
            min-width: 0;
        }

        #qWizardSection3 .q-hotel-field-supplier > .h-supplier.form-control {
            border: 1px solid #e2e8f0 !important;
            border-radius: 10px !important;
            background: #fff !important;
            min-height: 38px !important;
            height: 38px !important;
            padding: 0.2rem 0.55rem !important;
            font-size: 0.8rem !important;
        }

        #qWizardSection3 .q-hotel-field-supplier > .h-supplier.form-control:focus {
            border-color: #f87171 !important;
            box-shadow: 0 0 0 3px rgba(225, 29, 46, 0.12) !important;
        }

        #qWizardSection3 .q-hotel-field-supplier .select2-container {
            display: block;
            width: 100% !important;
            height: 38px;
            margin: 0 !important;
        }

        #qWizardSection3 .q-hotel-fields {
            display: grid;
            grid-template-columns: 1.05fr 1.2fr 1.15fr 0.7fr 0.7fr 0.7fr 1.05fr 1.05fr 0.85fr 1.2fr 42px;
            gap: 0.4rem;
            align-items: center;
        }

        #qWizardSection3 .q-hotel-field {
            min-width: 0;
            width: 100%;
            position: relative;
        }

        #qWizardSection3 .q-hotel-input-wrap {
            position: relative;
            display: flex;
            align-items: center;
            background: #fff;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            min-height: 40px;
            overflow: hidden;
        }

        #qWizardSection3 .q-hotel-ico {
            position: absolute;
            left: 0.55rem;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            font-size: 0.78rem;
            z-index: 2;
            pointer-events: none;
            width: 1rem;
            text-align: center;
        }

        #qWizardSection3 .q-hotel-caret {
            position: absolute;
            right: 0.5rem;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            font-size: 0.68rem;
            z-index: 2;
            pointer-events: none;
        }

        #qWizardSection3 .q-hotel-input-wrap .form-control,
        #qWizardSection3 .q-hotel-input-wrap select.form-control {
            height: 40px !important;
            min-height: 40px !important;
            border: 0 !important;
            box-shadow: none !important;
            background: transparent !important;
            padding: 0.35rem 1.6rem 0.35rem 2rem !important;
            font-size: 0.8rem !important;
            border-radius: 8px !important;
            color: #111827 !important;
            width: 100%;
        }

        #qWizardSection3 .q-hotel-field-rate .q-hotel-ico {
            font-size: 0.72rem;
            border: 1px solid #d1d5db;
            border-radius: 999px;
            width: 1.05rem;
            height: 1.05rem;
            line-height: 1.05rem;
            left: 0.45rem;
        }

        #qWizardSection3 .q-hotel-field-action {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        #qWizardSection3 .q-hotel-row-remove.q-remove {
            width: 34px !important;
            height: 34px !important;
            padding: 0 !important;
            display: inline-flex !important;
            align-items: center;
            justify-content: center;
            border-radius: 8px !important;
            border: 1px solid #e5e7eb !important;
            background: #fff !important;
            color: #e11d2e !important;
            font-size: 0.78rem !important;
            position: static !important;
            top: auto !important;
            right: auto !important;
        }

        #qWizardSection3 .q-hotel-row-remove.q-remove:hover {
            background: #fef2f2 !important;
            border-color: #fecaca !important;
        }

        #qWizardSection3 .q-hotel-field-no-ico .q-hotel-input-wrap .form-control,
        #qWizardSection3 .q-hotel-field-no-ico .q-hotel-input-wrap select.form-control:not(.select2-hidden-accessible) {
            padding-left: 0.65rem !important;
        }

        #qWizardSection3 .q-hotel-stepper-control {
            display: flex;
            align-items: stretch;
            width: 100%;
            min-height: 40px;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            overflow: hidden;
            background: #fff;
        }

        #qWizardSection3 .q-hotel-stepper-control:focus-within {
            border-color: #f87171;
            box-shadow: 0 0 0 3px rgba(225, 29, 46, 0.12);
        }

        #qWizardSection3 .q-hotel-step-btn {
            flex: 0 0 28px;
            width: 28px;
            min-width: 28px;
            padding: 0;
            border: 0;
            border-radius: 0;
            outline: none;
            box-shadow: none;
            background: transparent;
            color: #4b5563;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            font-weight: 600;
            line-height: 1;
            cursor: pointer;
            transition: background 0.15s ease, color 0.15s ease;
            -webkit-tap-highlight-color: transparent;
        }

        #qWizardSection3 .q-hotel-step-btn:hover {
            background: #f3f4f6;
            color: #111827;
            border: 0;
            outline: none;
            box-shadow: none;
        }

        #qWizardSection3 .q-hotel-step-btn:focus,
        #qWizardSection3 .q-hotel-step-btn:active,
        #qWizardSection3 .q-hotel-step-btn:focus-visible {
            border: 0 !important;
            outline: none !important;
            box-shadow: none !important;
        }

        #qWizardSection3 .q-hotel-step-btn:active {
            background: #e5e7eb;
        }

        #qWizardSection3 .q-hotel-stepper-control .form-control.h-rooms,
        #qWizardSection3 .q-hotel-stepper-control .form-control.h-nights {
            flex: 1 1 auto;
            min-width: 0;
            width: auto !important;
            border: 0 !important;
            border-radius: 0 !important;
            box-shadow: none !important;
            background: transparent !important;
            text-align: center;
            font-weight: 700;
            font-size: 0.95rem;
            color: #9f1239 !important;
            padding: 0.35rem 0.15rem !important;
            min-height: 100% !important;
            height: auto !important;
            -moz-appearance: textfield;
            appearance: textfield;
        }

        #qWizardSection3 .q-hotel-stepper-control .form-control.h-rooms::-webkit-outer-spin-button,
        #qWizardSection3 .q-hotel-stepper-control .form-control.h-rooms::-webkit-inner-spin-button,
        #qWizardSection3 .q-hotel-stepper-control .form-control.h-nights::-webkit-outer-spin-button,
        #qWizardSection3 .q-hotel-stepper-control .form-control.h-nights::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        #qWizardSection3 .h-rate {
            -moz-appearance: textfield;
            appearance: textfield;
        }

        #qWizardSection3 .h-rate::-webkit-outer-spin-button,
        #qWizardSection3 .h-rate::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        @media (max-width: 1200px) {
            #qWizardSection3 .q-hotel-col-head,
            #qWizardSection3 .q-hotel-fields {
                grid-template-columns: repeat(6, minmax(0, 1fr));
            }
            #qWizardSection3 .q-hotel-col-head span:nth-child(n+7),
            #qWizardSection3 .q-hotel-field-action {
                grid-column: auto;
            }
            #qWizardSection3 .q-hotel-field-action {
                justify-self: end;
            }
        }

        @media (max-width: 768px) {
            #qWizardSection3 .q-hotel-col-head {
                display: none;
            }
            #qWizardSection3 .q-hotel-fields {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
            #qWizardSection3 .q-hotel-add-btn {
                margin-left: 0;
                width: 100%;
            }
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
            max-height: 240px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            background: #fff;
            border: 1px solid var(--q-border);
            border-radius: var(--q-radius);
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.1);
            padding-bottom: 0;
        }

        .crm-quotation-gen .q-hotel-menu-list {
            flex: 1 1 auto;
            max-height: 180px;
            overflow-y: auto;
            min-height: 0;
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
            display: none;
        }

        .crm-quotation-gen .q-hotel-menu-create-footer {
            flex: 0 0 auto;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.45rem;
            width: 100%;
            margin: 0;
            padding: 0.65rem 0.75rem;
            border: 0;
            border-top: 1px solid #fee2e2;
            border-radius: 0 0 var(--q-radius) var(--q-radius);
            background: linear-gradient(180deg, #fff7f7 0%, #fff1f2 100%);
            color: #e11d2e;
            font-size: 0.82rem;
            font-weight: 700;
            cursor: pointer;
            text-align: center;
            outline: none;
            box-shadow: none;
            transition: background 0.15s ease, color 0.15s ease;
        }

        .crm-quotation-gen .q-hotel-menu-create-footer:hover,
        .crm-quotation-gen .q-hotel-menu-create-footer:focus,
        .crm-quotation-gen .q-hotel-menu-create-footer:active,
        .crm-quotation-gen .q-hotel-menu-create-footer:focus-visible {
            background: #fee2e2;
            color: #be123c;
            outline: none !important;
            box-shadow: none !important;
            border-color: #fee2e2;
        }

        .crm-quotation-gen .q-hotel-menu-create-footer i {
            font-size: 0.95rem;
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

        .crm-quotation-gen .h-rate {
            -moz-appearance: textfield;
            appearance: textfield;
        }

        .crm-quotation-gen .h-rate::-webkit-outer-spin-button,
        .crm-quotation-gen .h-rate::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
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

        .qfs-flight-search .qfs-search-modal-content {
            border-radius: 0;
            border: 1px solid #ddd;
            box-shadow: 0 8px 28px rgba(15, 23, 42, 0.12);
        }

        .qfs-flight-search .qfs-search-hd {
            background: #f4f6f9;
            font-weight: bold;
            border-bottom: 1px solid #ddd;
            padding: 0.75rem 1rem;
        }

        .qfs-flight-search .qfs-search-body {
            background: #fff;
            border-top: none;
            overflow: visible;
            padding: 15px;
        }

        .qfs-flight-search .qfs-search-body label {
            font-weight: 600;
            font-size: 0.85rem;
            color: #334155;
            margin-bottom: 0.25rem;
        }

        .qfs-flight-search .qfs-pax-hint {
            font-size: 0.75rem;
            line-height: 1.35;
        }

        .qfs-flight-search .qfs-nonstop-check .form-check-label {
            font-size: 0.9rem;
            color: #0f172a;
            cursor: pointer;
        }

        .qfs-flight-search .qfs-nonstop-check .form-check-input {
            margin-top: 0.2rem;
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
            padding-top: 6px;
            padding-bottom: 6px;
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
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0;
        }

        .qfs-flight-search .qfs-swap-btn:hover {
            color: #0d6efd;
            border-color: #93c5fd;
            background: #eff6ff;
        }

        [data-theme="dark"] .qfs-flight-search .qfs-swap-btn {
            background: var(--mz-theme-bg-elevated, #2a2e38) !important;
            border-color: var(--q-border) !important;
            color: var(--q-text-muted) !important;
        }

        [data-theme="dark"] .qfs-flight-search .qfs-swap-btn:hover {
            color: #93c5fd !important;
            border-color: #60a5fa !important;
        }

        .qfs-flight-search .qfs-airport-suggest,
        .qfs-airport-suggest-open {
            display: none;
            position: absolute;
            z-index: 1000;
            width: 100%;
            background: #fff;
            border: 1px solid #ccc;
            border-radius: 4px;
            max-height: 250px;
            overflow-y: auto;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        [data-theme="dark"] .qfs-flight-search .qfs-airport-suggest,
        [data-theme="dark"] .qfs-airport-suggest-open {
            background: var(--mz-theme-bg-elevated, #2a2e38) !important;
            border-color: var(--q-border) !important;
            color: var(--q-text) !important;
        }

        .qfs-flight-search .qfs-suggest-item:hover,
        .qfs-airport-suggest-open .qfs-suggest-item:hover {
            background: #f1f5f9;
        }

        [data-theme="dark"] .qfs-flight-search .qfs-suggest-item:hover,
        [data-theme="dark"] .qfs-airport-suggest-open .qfs-suggest-item:hover {
            background: rgba(148, 163, 184, 0.15) !important;
        }

        .qfs-flight-search .qfs-search-btn {
            background-color: #6ba2c7;
            border-color: #6ba2c7;
            margin: 0;
            min-width: 96px;
            font-weight: 600;
        }

        .qfs-flight-search .qfs-search-btn:hover,
        .qfs-flight-search .qfs-search-btn:focus {
            background-color: #5a91b6;
            border-color: #5a91b6;
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
            top: var(--q-wizard-chrome-offset, 0px);
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
            scroll-margin-top: var(--q-wizard-scroll-offset, 110px);
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
            margin-top: 1rem;
            padding-top: 0.95rem;
            border-top: 1px solid #eef2f7;
        }

        .crm-quotation-gen .q-section-accordion-body > .q-section-next-bar:last-child {
            margin-bottom: 0.15rem;
        }

        .crm-quotation-gen .q-section-next-btn {
            min-width: 120px;
            font-weight: 700;
            border-radius: 10px;
            padding: 0.5rem 1.15rem;
            background: #e11d2e;
            border: 1px solid #e11d2e;
            color: #fff;
            box-shadow: 0 6px 16px rgba(225, 29, 46, 0.18);
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

        .crm-quotation-gen .q-wizard-step[data-q-step="1"] input.js-q-date-input {
            padding-right: 2.2rem;
        }

        .crm-q-datepicker.ui-datepicker {
            z-index: 2200 !important;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            box-shadow: 0 12px 28px rgba(15, 23, 42, 0.14);
            padding: 0.55rem;
            font-size: 0.84rem;
        }

        .crm-q-datepicker .ui-datepicker-header {
            background: #fff5f5;
            border: 0;
            border-radius: 8px;
            color: #0f172a;
            padding: 0.35rem 0.25rem;
            margin-bottom: 0.35rem;
        }

        .crm-q-datepicker .ui-datepicker-calendar td a.ui-state-active,
        .crm-q-datepicker .ui-datepicker-calendar td .ui-state-active {
            background: #e11d2e !important;
            border-color: #e11d2e !important;
            color: #fff !important;
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
            grid-template-columns: minmax(132px, 150px) repeat(var(--q-opt-count, 1), minmax(200px, 1fr));
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
            grid-template-columns: minmax(132px, 150px) minmax(280px, 1fr);
        }

        .crm-quotation-gen .q-pricing-sheets-host.is-single-option .q-pricing-option-sheet {
            max-width: 520px;
            width: 100%;
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
            min-width: 200px;
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
        .crm-quotation-gen .q-pricing-option-sheet .q-custom-cost .cc-amount {
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
            gap: 0.4rem;
        }

        .crm-quotation-gen .q-pricing-amount-cell.has-supplier {
            display: flex;
            flex-direction: row;
            align-items: center;
        }

        .crm-quotation-gen .q-pricing-amount-cell.has-supplier .q-cost {
            flex: 1 1 auto;
            min-width: 0;
            max-width: none;
            width: auto !important;
        }

        .crm-quotation-gen .q-pricing-supplier-name {
            flex: 0 1 46%;
            min-width: 0;
            max-width: 9.5rem;
            font-size: 0.68rem;
            font-weight: 600;
            color: #64748b;
            line-height: 1.2;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            padding-right: 0.1rem;
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

        .crm-quotation-gen .q-pricing-option-sheet .q-sheet-profit-block {
            margin-top: 0.65rem;
            padding: 0.55rem 0.6rem 0.55rem;
            border: 1px solid var(--q-border);
            border-radius: 10px;
            background: var(--q-border-light);
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            box-sizing: border-box;
            width: 100%;
            max-width: 100%;
            overflow: visible;
        }

        .crm-quotation-gen .q-pricing-option-sheet .q-profit-line {
            display: flex;
            flex-direction: column;
            gap: 0.2rem;
            align-items: stretch;
            width: 100%;
            min-width: 0;
            box-sizing: border-box;
        }

        .crm-quotation-gen .q-pricing-option-sheet .q-profit-line.q-profit-add-line {
            align-items: stretch;
        }

        .crm-quotation-gen .q-pricing-option-sheet .q-profit-add-line .q-profit-line-label {
            line-height: 1.3;
        }

        .crm-quotation-gen .q-pricing-option-sheet .q-profit-line-label {
            font-size: 0.74rem;
            font-weight: 600;
            color: var(--q-text);
            line-height: 1.3;
            white-space: nowrap;
        }

        .crm-quotation-gen .q-pricing-option-sheet .q-profit-readonly {
            display: block;
            width: 100%;
            max-width: 100%;
            min-height: 34px;
            padding: 0.35rem 0.75rem;
            border: 1px solid var(--q-border);
            border-radius: 4px;
            background: #f4f4f4;
            color: var(--q-text);
            font-size: 0.84rem;
            font-weight: 700;
            text-align: right;
            font-variant-numeric: tabular-nums;
            line-height: 1.45;
            box-sizing: border-box;
            overflow: visible;
            white-space: nowrap;
        }

        .crm-quotation-gen .q-pricing-option-sheet .q-profit-inputs {
            min-width: 0;
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
        }

        .crm-quotation-gen .q-pricing-option-sheet .q-profit-row {
            display: flex;
            flex-wrap: nowrap;
            align-items: center;
            gap: 0.4rem;
            width: 100%;
            max-width: 100%;
            min-width: 0;
            box-sizing: border-box;
        }

        .crm-quotation-gen .q-pricing-option-sheet .q-profit-pct-group {
            width: 4.5rem;
            max-width: 4.5rem;
            flex: 0 0 4.5rem;
            min-width: 0;
        }

        .crm-quotation-gen .q-pricing-option-sheet .q-profit-pct-group .form-control {
            height: 34px;
            font-size: 0.8rem;
            font-weight: 600;
            text-align: right;
            padding: 0.2rem 0.35rem;
            min-width: 0;
            box-sizing: border-box;
        }

        .crm-quotation-gen .q-pricing-option-sheet .q-profit-pct-group .input-group-text {
            height: 34px;
            padding: 0.2rem 0.4rem;
            font-size: 0.75rem;
            font-weight: 700;
            background: #e9ecef;
            color: #495057;
            box-sizing: border-box;
        }

        .crm-quotation-gen .q-pricing-option-sheet .q-profit-row .q-sheet-profit-amount {
            flex: 1 1 0;
            min-width: 0;
            max-width: none;
            width: 100%;
            height: 34px;
            font-size: 0.84rem;
            font-weight: 600;
            text-align: right;
            padding: 0.2rem 0.75rem;
            box-sizing: border-box;
        }

        .crm-quotation-gen .q-profit-or {
            font-size: 0.72rem;
            font-weight: 700;
            color: #0d6efd;
            letter-spacing: 0.02em;
            flex: 0 0 auto;
            white-space: nowrap;
        }

        .crm-quotation-gen .q-pricing-option-sheet .q-profit-calc-hint {
            display: none;
            margin-top: 0.2rem;
            font-size: 0.72rem;
            font-weight: 600;
            color: #0d6efd;
            font-variant-numeric: tabular-nums;
            overflow: visible;
            white-space: nowrap;
        }

        .crm-quotation-gen .q-pricing-option-sheet .q-profit-calc-hint.is-visible {
            display: block;
        }

        .crm-quotation-gen .q-pricing-option-sheet .q-sum-selling.q-profit-readonly {
            color: var(--q-text);
            font-weight: 800;
        }

        .crm-quotation-gen .q-pricing-sheets-host:not(.is-single-option) .q-pricing-option-sheet .q-profit-line {
            gap: 0.15rem;
        }

        .crm-quotation-gen .q-pricing-sheets-host:not(.is-single-option) .q-pricing-option-sheet .q-profit-line-label {
            line-height: 1.3;
            font-size: 0.68rem;
        }

        .crm-quotation-gen .q-pricing-sheets-host:not(.is-single-option) .q-pricing-option-sheet .q-profit-add-line .q-profit-line-label {
            line-height: 1.3;
        }

        .crm-quotation-gen .q-pricing-sheets-host.is-single-option .q-pricing-option-sheet .q-profit-row .q-sheet-profit-amount {
            max-width: none;
        }

        .crm-quotation-gen .q-pricing-profit-cell {
            min-height: 28px;
            height: auto;
            align-items: center;
        }

        .crm-quotation-gen .q-pricing-option-sheet .q-custom-cost-rows {
            min-height: 0;
            margin-bottom: 0.25rem;
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
        }

        .crm-quotation-gen .q-pricing-custom-label {
            min-height: 38px;
            height: 38px;
            align-items: center;
            padding-top: 0;
            margin-bottom: 0.35rem;
        }

        .crm-quotation-gen .q-pricing-option-sheet .q-custom-cost {
            display: block;
            margin-bottom: 0 !important;
            position: relative;
        }

        .crm-quotation-gen .q-pricing-option-sheet .q-custom-cost-empty {
            min-height: 38px;
            height: 38px;
            margin-bottom: 0;
            visibility: hidden;
            pointer-events: none;
        }

        .crm-quotation-gen .q-pricing-option-sheet .q-custom-cost-row {
            display: flex;
            flex-wrap: nowrap;
            align-items: center;
            gap: 0;
            width: 100%;
            min-width: 0;
            min-height: 38px;
            border: 1px solid var(--q-border);
            border-radius: 10px;
            background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
            overflow: hidden;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }

        .crm-quotation-gen .q-pricing-option-sheet .q-custom-cost-row:focus-within {
            border-color: rgba(196, 18, 26, 0.45);
            box-shadow: 0 0 0 3px rgba(196, 18, 26, 0.08);
        }

        .crm-quotation-gen .q-pricing-option-sheet .q-custom-cost-ico {
            flex: 0 0 30px;
            width: 30px;
            height: 38px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #c4121a;
            font-size: 0.68rem;
            background: rgba(196, 18, 26, 0.06);
            border-right: 1px solid var(--q-border);
        }

        .crm-quotation-gen .q-pricing-option-sheet .q-custom-cost .cc-label {
            flex: 1 1 auto;
            min-width: 0;
            height: 38px !important;
            min-height: 38px !important;
            border: 0 !important;
            border-radius: 0 !important;
            background: transparent !important;
            box-shadow: none !important;
            padding: 0.25rem 0.55rem !important;
            font-size: 0.8rem !important;
            font-weight: 600;
            color: var(--q-text) !important;
        }

        .crm-quotation-gen .q-pricing-option-sheet .q-custom-cost .cc-label:focus {
            background: transparent !important;
            box-shadow: none !important;
        }

        .crm-quotation-gen .q-pricing-option-sheet .q-custom-cost .q-custom-cost-amt {
            display: flex;
            align-items: center;
            position: relative;
            flex: 0 0 6.75rem;
            width: 6.75rem;
            min-width: 6.75rem;
            max-width: 6.75rem;
            border-left: 1px solid var(--q-border);
            background: #fff;
        }

        .crm-quotation-gen .q-pricing-option-sheet .q-custom-cost .q-custom-cost-amt::before {
            content: "₹";
            position: absolute;
            left: 0.45rem;
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
            height: 38px !important;
            min-height: 38px !important;
            border: 0 !important;
            border-radius: 0 !important;
            background: transparent !important;
            box-shadow: none !important;
            padding: 0.2rem 0.5rem 0.2rem 1.15rem !important;
            font-size: 0.82rem !important;
            font-weight: 700;
            color: var(--q-text) !important;
            text-align: right;
        }

        .crm-quotation-gen .q-pricing-option-sheet .q-custom-cost .cc-amount:focus {
            background: transparent !important;
            box-shadow: none !important;
        }

        .crm-quotation-gen .q-pricing-option-sheet .q-custom-cost-remove {
            flex: 0 0 28px;
            width: 28px;
            height: 38px;
            padding: 0;
            margin: 0;
            border: 0;
            border-left: 1px solid var(--q-border);
            border-radius: 0;
            background: #fff;
            color: #94a3b8;
            line-height: 1;
            font-size: 0.7rem;
            opacity: 0;
            transition: opacity 0.15s ease, color 0.15s ease, background 0.15s ease;
        }

        .crm-quotation-gen .q-pricing-option-sheet .q-custom-cost:hover .q-custom-cost-remove,
        .crm-quotation-gen .q-pricing-option-sheet .q-custom-cost-row:focus-within .q-custom-cost-remove {
            opacity: 1;
        }

        .crm-quotation-gen .q-pricing-option-sheet .q-custom-cost-remove:hover {
            color: #e11d2e;
            background: #fef2f2;
        }

        .q-extra-cost-modal .modal-content {
            border: 0;
            border-radius: 8px;
            box-shadow: 0 12px 40px rgba(15, 23, 42, 0.18);
        }

        .q-extra-cost-modal .modal-body {
            padding: 1.35rem 1.5rem 0.5rem;
        }

        .q-extra-cost-modal .q-extra-cost-modal-title {
            margin: 0 0 0.25rem;
            font-size: 1.15rem;
            font-weight: 600;
            color: #1e293b;
        }

        .q-extra-cost-modal .q-extra-cost-modal-sub {
            margin: 0 0 1.15rem;
            font-size: 0.8rem;
            color: #64748b;
            line-height: 1.35;
        }

        .q-extra-cost-modal .q-extra-cost-field {
            margin-bottom: 1.15rem;
        }

        .q-extra-cost-modal .q-extra-cost-field input {
            width: 100%;
            border: 0;
            border-bottom: 1px solid #cbd5e1;
            border-radius: 0;
            background: transparent;
            padding: 0.45rem 0.1rem;
            font-size: 0.95rem;
            color: #0f172a;
            box-shadow: none !important;
        }

        .q-extra-cost-modal .q-extra-cost-field input:focus {
            border-bottom-color: #c4121a;
            outline: 0;
        }

        .q-extra-cost-modal .q-extra-cost-field input::placeholder {
            color: #94a3b8;
        }

        .q-extra-cost-modal .modal-footer {
            border: 0;
            padding: 0.35rem 1rem 1rem;
            justify-content: flex-end;
            gap: 0.25rem;
        }

        .q-extra-cost-modal .modal-footer .btn-link {
            font-weight: 700;
            font-size: 0.82rem;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: #64748b;
            text-decoration: none !important;
            padding: 0.45rem 0.7rem;
        }

        .q-extra-cost-modal .modal-footer .btn-link.q-extra-cost-save {
            color: #c4121a;
        }

        .q-extra-cost-modal .modal-footer .btn-link:hover {
            color: #0f172a;
        }

        .q-extra-cost-modal #qExtraCostError {
            font-size: 0.8rem;
            margin-bottom: 0.75rem;
        }

        .crm-quotation-gen .q-pricing-add-cell {
            justify-content: flex-start;
            align-items: center;
            min-height: 34px;
            height: auto;
            opacity: 1;
            margin-bottom: 0.45rem;
        }

        .crm-quotation-gen .q-pricing-option-sheet:hover .q-pricing-add-cell {
            opacity: 1;
        }

        .crm-quotation-gen .q-pricing-add-cell .q-add-cost-row {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.35rem;
            width: auto;
            min-width: 0;
            height: 32px;
            padding: 0 0.75rem;
            line-height: 1;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.01em;
            color: #c4121a;
            background: #fff;
            border: 1px dashed rgba(196, 18, 26, 0.45);
            border-radius: 999px;
            box-shadow: none;
        }

        .crm-quotation-gen .q-pricing-add-cell .q-add-cost-row i {
            font-size: 0.65rem;
        }

        .crm-quotation-gen .q-pricing-add-cell .q-add-cost-row:hover {
            color: #fff;
            background: #c4121a;
            border-style: solid;
            border-color: #c4121a;
        }

        .crm-quotation-gen .q-pricing-sheets-host:not(.is-single-option) .q-pricing-add-cell .q-add-cost-row span {
            display: none;
        }

        .crm-quotation-gen .q-pricing-sheets-host:not(.is-single-option) .q-pricing-add-cell .q-add-cost-row {
            width: 28px;
            height: 28px;
            padding: 0;
            border-radius: 50%;
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

        /* Quotation preview modal — A4 page (210mm × 297mm) */
        #qPreviewModal .modal-dialog {
            width: 210mm;
            max-width: min(210mm, calc(100vw - 24px));
            margin: 1rem auto;
            height: auto;
            max-height: calc(100vh - 2rem);
        }

        #qPreviewModal .qp-modal-content {
            border: none;
            border-radius: 8px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            max-height: calc(100vh - 2rem);
        }

        #qPreviewModal .modal-body {
            padding: 6px 6px 8px;
            background: #dfe3e8;
            overflow-y: auto;
            flex: 1 1 auto;
            -webkit-overflow-scrolling: touch;
        }

        #qPreviewModal .qp-modal-header {
            background: #c4121a;
            color: #fff;
            border-bottom: none;
            padding: 12px 18px;
            align-items: center;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        #qPreviewModal .qp-modal-header .modal-title {
            font-size: 1.05rem;
            font-weight: 700;
            letter-spacing: 0.02em;
        }

        #qPreviewModal .qp-modal-hint {
            display: block;
            color: rgba(255, 255, 255, 0.78);
            font-size: 11px;
            margin-top: 2px;
        }

        #qPreviewModal .qp-modal-unsaved {
            color: #ffe08a !important;
        }

        #qPreviewModal .qp-modal-close {
            color: #fff;
            text-shadow: none;
            opacity: 0.9;
        }

        #qPreviewModal .qp-modal-footer {
            background: #fff;
            border-top: 1px solid #e8e8ea;
            padding: 12px 16px;
            justify-content: flex-end;
            gap: 8px;
        }

        #qPreviewModal .qp-btn-close {
            border: 1.5px solid #c4121a;
            color: #c4121a;
            background: #fff;
            font-weight: 600;
            padding: 6px 16px;
        }

        #qPreviewModal .qp-btn-close:hover {
            background: #fff5f5;
            color: #a10e15;
            border-color: #a10e15;
        }

        #qPreviewModal .qp-btn-edit {
            background: #c4121a;
            border-color: #c4121a;
            color: #fff;
            font-weight: 600;
            padding: 6px 16px;
        }

        #qPreviewModal .qp-btn-edit:hover {
            background: #a10e15;
            border-color: #a10e15;
            color: #fff;
        }

        #qPreviewModal .qp-btn-save {
            background: #2563eb;
            border-color: #2563eb;
            color: #fff;
            font-weight: 600;
            padding: 6px 16px;
        }

        #qPreviewModal .qp-btn-save:hover {
            background: #1d4ed8;
            border-color: #1d4ed8;
            color: #fff;
        }

        #qPreviewModal .qp-btn-save:disabled {
            opacity: 0.75;
        }

        #qPreviewModal .qp-btn-print {
            background: transparent;
            border: 1px solid #c5c5c8;
            color: #555;
            font-weight: 500;
            padding: 6px 14px;
        }

        #qPreviewModal .qp-btn-print:hover {
            background: #f3f3f4;
            color: #333;
        }

        .q-preview-doc {
            --qp-red: #c4121a;
            --qp-red-bright: #e11d2e;
            --qp-red-dark: #9a0f15;
            --qp-ink: #1a2332;
            --qp-muted: #7a8494;
            --qp-line: #e4e4e8;
            --qp-soft: #f8fafc;
            --qp-card: #f7fafc;
            --qp-page-w: 210mm;
            --qp-page-h: 297mm;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", "Noto Sans", "Liberation Sans", Arial, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", "Noto Color Emoji";
            color: var(--qp-ink);
            font-size: 13px;
            line-height: 1.5;
            box-sizing: border-box;
            width: var(--qp-page-w);
            max-width: 100%;
            min-height: var(--qp-page-h);
            margin: 0 auto;
            padding: 8mm 10mm 10mm;
            background: #fff;
            border: 1.5px solid var(--qp-red);
            border-radius: 0;
            box-shadow: 0 4px 24px rgba(15, 23, 42, 0.12);
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        /* —— Document header —— */
        .qp-doc-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            padding-bottom: 14px;
            margin-bottom: 8px;
            border-bottom: 1px solid #edf0f4;
        }

        .qp-logo {
            flex: 0 0 auto;
            max-width: 168px;
        }

        .qp-logo img {
            max-height: 64px;
            width: auto;
            max-width: 100%;
            display: block;
            object-fit: contain;
        }

        .qp-title-block {
            flex: 1;
            text-align: center;
            padding: 2px 8px 0;
            min-width: 0;
        }

        .qp-mtn-svg {
            display: none;
        }

        .qp-title-block h1 {
            margin: 0;
            font-size: 1.2rem;
            font-weight: 800;
            letter-spacing: 0.01em;
            color: var(--qp-ink);
            line-height: 1.3;
        }

        .qp-title-block .q-preview-dest-title,
        .qp-dest-red {
            color: var(--qp-red);
            font-weight: 800;
            text-transform: uppercase;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .qp-duration-wrap {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-top: 8px;
        }

        .qp-duration-line {
            width: 42px;
            height: 2px;
            background: var(--qp-red);
            flex-shrink: 0;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .qp-duration {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--qp-ink);
            white-space: nowrap;
        }

        .qp-route-pills {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 10px;
            max-width: 100%;
        }

        .qp-route-pill {
            display: inline-flex;
            align-items: center;
            border: 1.5px solid var(--qp-red);
            color: var(--qp-ink);
            background: #fff;
            border-radius: 999px;
            padding: 0.32rem 0.85rem;
            font-size: 0.78rem;
            font-weight: 600;
            line-height: 1.2;
            max-width: 240px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .qp-route-arrow {
            color: var(--qp-red);
            font-size: 0.9rem;
            font-weight: 700;
            line-height: 1;
            flex-shrink: 0;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .qp-ref-card {
            background: #ffffff;
            border: 0;
            border-radius: 16px;
            padding: 6px 12px;
            min-width: 200px;
            max-width: 240px;
            font-family: inherit;
            font-size: 12px;
            line-height: 1.3;
            text-align: left;
            box-shadow: 0 6px 18px rgba(15, 23, 42, 0.08);
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .qp-ref-card .qp-ref-row {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 0;
            padding: 0 0 3px;
        }

        .qp-ref-card .qp-ref-row + .qp-ref-row {
            padding-top: 5px;
            padding-bottom: 0;
            border-top: 1px solid #e8ecf1;
        }

        .qp-ref-card .qp-ref-row:last-child {
            margin-bottom: 0;
        }

        .qp-ref-card .qp-ref-ico {
            color: #d92027;
            width: 22px;
            text-align: center;
            margin-top: 0;
            flex-shrink: 0;
            font-size: 1.15rem;
            line-height: 1;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .qp-ref-text {
            min-width: 0;
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .qp-ref-label {
            font-size: 11px;
            font-weight: 500;
            color: #707a8a;
            margin-bottom: 0;
            line-height: 1.2;
        }

        .qp-ref-value,
        .qp-ref-card .ref {
            font-weight: 700;
            color: #0a1329;
            word-break: break-word;
            font-size: 13px;
            line-height: 1.25;
            letter-spacing: -0.01em;
        }

        /* —— Section heads —— */
        .qp-sec {
            margin-bottom: 18px;
        }

        .qp-sec-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 10px;
            flex-wrap: wrap;
        }

        .qp-sec-head-left {
            display: flex;
            align-items: center;
            gap: 8px;
            min-width: 0;
            flex: 1;
        }

        .qp-sec-icon {
            width: 26px;
            height: 26px;
            border-radius: 50%;
            background: var(--qp-red);
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            flex-shrink: 0;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .qp-sec-title {
            font-size: 14px;
            font-weight: 700;
            font-family: inherit;
            letter-spacing: -0.01em;
            line-height: 1.2;
            text-transform: none;
            color: var(--qp-ink);
            white-space: nowrap;
        }

        .qp-sec-line {
            flex: 1 1 auto;
            height: 2px;
            min-width: 20px;
            background: var(--qp-red);
            align-self: center;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .qp-sec-slogan {
            font-size: 8px;
            font-weight: 600;
            font-family: inherit;
            letter-spacing: 0.12em;
            color: #a0a8b4;
            line-height: 1.35;
            text-transform: uppercase;
            white-space: nowrap;
            flex-shrink: 0;
        }

        /* —— Flight Details (preview cards) —— */
        .qp-sec-flights {
            --qp-flight-red: #d92027;
            --qp-flight-ink: #1a1d23;
            --qp-flight-muted: #8b93a0;
            --qp-flight-line: #d7dbe3;
            --qp-flight-blue: #2563eb;
            --qp-flight-blue-bg: #eff6ff;
            margin-bottom: 18px;
            font-family: inherit;
        }

        .qp-flight-sec-head {
            margin-bottom: 10px;
            padding: 0;
            background: transparent;
            border: 0;
            border-radius: 0;
        }

        .qp-flight-sec-top {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .qp-flight-sec-left {
            display: flex;
            align-items: center;
            gap: 8px;
            min-width: 0;
            flex-shrink: 0;
        }

        .qp-flight-sec-icon {
            width: 26px;
            height: 26px;
            border-radius: 50%;
            background: var(--qp-flight-red);
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .qp-flight-sec-icon svg {
            width: 12px;
            height: 12px;
        }

        .qp-flight-sec-icon-svg {
            display: block;
        }

        .qp-flight-sec-title {
            font-size: 14px;
            font-weight: 700;
            font-family: inherit;
            color: var(--qp-flight-ink);
            letter-spacing: -0.01em;
            line-height: 1.2;
            white-space: nowrap;
        }

        .qp-flight-sec-rule {
            flex: 1 1 auto;
            height: 2px;
            min-width: 20px;
            background: var(--qp-flight-red);
            align-self: center;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .qp-flight-sec-slogan-wrap {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            flex-shrink: 0;
        }

        .qp-flight-sec-slogan {
            font-size: 8px;
            font-weight: 600;
            font-family: inherit;
            letter-spacing: 0.12em;
            color: #a0a8b4;
            line-height: 1.35;
            text-align: right;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .qp-flight-sec-tomorrow {
            display: inline;
            padding-bottom: 0;
            border-bottom: 0;
        }

        .qp-flight-cards {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .qp-flight-card {
            background: #fff;
            border: 1px solid #e8ecf1;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(17, 24, 39, 0.04);
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .qp-flight-card-hd {
            padding: 10px 12px 6px;
        }

        .qp-flight-card-hd-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 6px;
        }

        .qp-flight-dir {
            display: inline-flex;
            align-items: center;
            background: var(--qp-flight-red);
            color: #fff;
            font-size: 10px;
            font-weight: 600;
            font-family: inherit;
            line-height: 1;
            padding: 4px 10px;
            border-radius: 999px;
            white-space: nowrap;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .qp-flight-route {
            font-size: 13px;
            font-weight: 700;
            font-family: inherit;
            color: var(--qp-flight-ink);
            letter-spacing: -0.01em;
            line-height: 1.25;
        }

        .qp-flight-meta {
            font-size: 11px;
            font-weight: 600;
            font-family: inherit;
            color: var(--qp-flight-red);
            white-space: nowrap;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .qp-flight-legs {
            display: flex;
            flex-direction: column;
            gap: 0;
            padding: 2px 10px 4px;
        }

        .qp-flight-seg {
            display: grid;
            grid-template-columns: 22px minmax(100px, 1.15fr) minmax(70px, 0.9fr) minmax(100px, 1.15fr) minmax(130px, 1.2fr);
            gap: 8px;
            align-items: center;
            background: transparent;
            border: 0;
            border-radius: 0;
            padding: 8px 2px;
            margin-bottom: 0;
        }

        .qp-flight-seg-num {
            width: 22px;
            height: 22px;
            border-radius: 50%;
            background: #fde8ea;
            color: var(--qp-flight-red);
            font-size: 11px;
            font-weight: 700;
            font-family: inherit;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .qp-flight-endpoint {
            min-width: 0;
        }

        .qp-flight-endpoint.is-end {
            text-align: left;
        }

        .qp-flight-place {
            font-size: 12px;
            line-height: 1.25;
            color: var(--qp-flight-ink);
            font-family: inherit;
        }

        .qp-flight-city {
            font-size: 12px;
            font-weight: 600;
            font-family: inherit;
            color: var(--qp-flight-ink);
        }

        .qp-flight-code {
            font-size: 11px;
            font-weight: 500;
            font-family: inherit;
            color: var(--qp-flight-muted);
        }

        .qp-flight-when {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 5px;
            margin-top: 2px;
            font-size: 10px;
            line-height: 1.3;
            color: var(--qp-flight-muted);
            font-family: inherit;
        }

        .qp-flight-date {
            font-size: 10px;
            font-weight: 500;
            font-family: inherit;
            color: var(--qp-flight-muted);
        }

        .qp-flight-when-sep {
            color: #c4cad4;
            font-weight: 500;
        }

        .qp-flight-time {
            font-size: 12px;
            font-weight: 600;
            font-family: inherit;
            color: var(--qp-flight-ink);
            line-height: 1.15;
        }

        .qp-flight-time .q-preview-cell-edit,
        .qp-flight-time .qp-flight-time-edit {
            font-family: inherit;
            font-size: 12px;
            font-weight: 600;
            color: var(--qp-flight-ink);
            border-bottom: none !important;
        }

        .qp-flight-mid {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0;
            min-width: 0;
            padding: 0 2px;
        }

        .qp-flight-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            border: 1.5px solid var(--qp-flight-red);
            background: #fff;
            box-sizing: border-box;
            flex-shrink: 0;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .qp-flight-dash {
            flex: 1 1 auto;
            height: 0;
            border-top: 1.5px dashed #c4cad4;
            min-width: 8px;
        }

        .qp-flight-mid-plane {
            color: #1f2937;
            margin: 0 4px;
            flex-shrink: 0;
            display: block;
            width: 12px;
            height: 12px;
        }

        .qp-flight-airline-wrap {
            display: inline-flex;
            align-items: center;
            justify-self: end;
            max-width: 100%;
            min-width: 0;
        }

        .qp-flight-airline {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: var(--qp-flight-blue-bg);
            color: var(--qp-flight-blue);
            border-radius: 999px;
            padding: 5px 9px;
            min-width: 0;
            max-width: 100%;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .qp-flight-airline-ico {
            color: var(--qp-flight-blue);
            flex-shrink: 0;
            display: block;
            width: 10px;
            height: 10px;
        }

        .qp-flight-airline-label {
            font-size: 10px;
            font-weight: 600;
            font-family: inherit;
            letter-spacing: 0.02em;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            min-width: 0;
            color: var(--qp-flight-blue);
            text-transform: uppercase;
        }

        .qp-flight-airline-label .q-preview-cell-edit {
            font-family: inherit;
            font-size: 10px;
            font-weight: 600;
            color: var(--qp-flight-blue);
            border-bottom: none !important;
            text-transform: uppercase;
            letter-spacing: 0.02em;
        }

        .qp-flight-airline-chev {
            color: #93c5fd;
            font-size: 9px;
            flex-shrink: 0;
        }

        .qp-flight-layover-wrap {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 2px 4px 4px;
            min-height: 24px;
        }

        .qp-flight-layover-line {
            position: absolute;
            left: 0;
            right: 0;
            top: 50%;
            height: 1px;
            background: #f0a8ae;
            z-index: 0;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .qp-flight-layover {
            position: relative;
            z-index: 1;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #fff5f6;
            border: 1px solid #f3b4b8;
            color: var(--qp-flight-red);
            font-size: 10px;
            font-weight: 600;
            font-family: inherit;
            padding: 4px 10px;
            border-radius: 999px;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .qp-flight-layover .fa-clock,
        .qp-flight-layover .far.fa-clock {
            color: var(--qp-flight-red);
            font-size: 10px;
        }

        .qp-flight-layover-text {
            color: var(--qp-flight-red);
            font-weight: 600;
            font-family: inherit;
        }

        .qp-flight-layover-pipe {
            color: var(--qp-flight-red);
            font-weight: 600;
            padding: 0 2px;
        }

        .qp-flight-layover-dur {
            color: var(--qp-flight-red);
            font-weight: 700;
            font-family: inherit;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .qp-flight-card-ft {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 8px 12px 10px;
            border-top: 1px solid #eef1f5;
            color: var(--qp-flight-ink);
            font-size: 11px;
            font-weight: 500;
            font-family: inherit;
        }

        .qp-flight-card-ft .fa-clock,
        .qp-flight-card-ft .far.fa-clock {
            color: var(--qp-flight-red);
            font-size: 11px;
        }

        .qp-flight-card-ft strong {
            font-weight: 700;
            font-family: inherit;
        }

        @media (max-width: 900px) {
            .qp-flight-legs {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }

            .qp-flight-seg {
                min-width: 560px;
            }

            .qp-flight-sec-top {
                flex-wrap: wrap;
            }

            .qp-flight-sec-rule {
                display: none;
            }

            .qp-flight-sec-slogan-wrap {
                width: 100%;
                align-items: flex-start;
            }
        }

        /* —— Hotel Details (preview cards) —— */
        .qp-sec-hotels {
            --qp-hotel-red: #d92027;
            --qp-hotel-ink: #1a1d23;
            --qp-hotel-muted: #8b93a0;
            margin-bottom: 16px;
            font-family: inherit;
        }

        .qp-hotel-sec-head {
            margin-bottom: 10px;
        }

        .qp-hotel-sec-top {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .qp-hotel-sec-left {
            display: flex;
            align-items: center;
            gap: 8px;
            min-width: 0;
            flex-shrink: 0;
        }

        .qp-hotel-sec-icon {
            width: 26px;
            height: 26px;
            border-radius: 50%;
            background: var(--qp-hotel-red);
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            flex-shrink: 0;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .qp-hotel-sec-title {
            font-size: 14px;
            font-weight: 700;
            color: var(--qp-hotel-ink);
            letter-spacing: -0.01em;
            line-height: 1.2;
            white-space: nowrap;
        }

        .qp-hotel-sec-rule {
            flex: 1 1 auto;
            height: 2px;
            min-width: 20px;
            background: var(--qp-hotel-red);
            align-self: center;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .qp-hotel-sec-slogan {
            font-size: 8px;
            font-weight: 600;
            letter-spacing: 0.12em;
            color: #a0a8b4;
            line-height: 1;
            text-align: right;
            text-transform: uppercase;
            white-space: nowrap;
            flex-shrink: 0;
        }

        .qp-hotel-slogan-dot {
            color: var(--qp-hotel-red);
            padding: 0 1px;
        }

        .qp-hotel-option-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-bottom: 8px;
        }

        .qp-hotel-option-tab {
            display: inline-flex;
            align-items: center;
            padding: 5px 11px;
            border-radius: 999px;
            border: 1px solid #d1d5db;
            background: #fff;
            color: #111827;
            font-size: 11px;
            font-weight: 600;
            line-height: 1;
        }

        .qp-hotel-option-tab.is-active {
            background: var(--qp-hotel-red);
            border-color: var(--qp-hotel-red);
            color: #fff;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .qp-hotel-panel {
            background: #f3f5f8;
            border: 1px solid #e4e8ef;
            border-radius: 12px;
            overflow: visible;
            box-shadow: 0 2px 10px rgba(17, 24, 39, 0.04);
            margin-bottom: 10px;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .qp-hotel-body {
            display: flex;
            flex-direction: column;
            gap: 8px;
            padding: 10px;
        }

        .qp-hotel-row-card {
            background: #fff;
            border: 1px solid #e6eaef;
            border-radius: 10px;
            padding: 10px 12px;
            box-shadow: 0 1px 2px rgba(17, 24, 39, 0.03);
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .qp-hotel-row-fields {
            display: grid;
            grid-template-columns: repeat(8, minmax(0, 1fr));
            gap: 8px 8px;
            align-items: start;
        }

        .qp-hotel-field {
            min-width: 0;
        }

        .qp-hotel-field-label {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 0.08em;
            color: #8b93a0;
            text-transform: uppercase;
            margin-bottom: 3px;
            line-height: 1.2;
        }

        .qp-hotel-field-label .qp-hotel-ico {
            color: var(--qp-hotel-red);
            font-size: 9px;
            width: auto;
            text-align: center;
            flex-shrink: 0;
            margin-top: 0;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .qp-hotel-field-value {
            min-width: 0;
        }

        .qp-hotel-field-city .qp-hotel-primary,
        .qp-hotel-field-city .qp-hotel-primary .q-preview-cell-edit,
        .qp-hotel-field-city .qp-hotel-secondary {
            text-transform: uppercase;
        }

        .qp-hotel-stack {
            display: flex;
            flex-direction: column;
            gap: 2px;
            min-width: 0;
            overflow: visible;
        }

        .qp-hotel-primary {
            font-size: 12px;
            font-weight: 600;
            color: var(--qp-hotel-ink);
            line-height: 1.3;
            word-break: break-word;
            overflow: visible;
        }

        .qp-hotel-primary .q-preview-cell-edit {
            font-size: 12px;
            font-weight: 600;
            color: var(--qp-hotel-ink);
            border-bottom: none !important;
            min-width: 0;
        }

        .qp-hotel-secondary {
            font-size: 10px;
            font-weight: 500;
            color: var(--qp-hotel-muted);
            line-height: 1.25;
            word-break: break-word;
        }

        .qp-hotel-secondary .q-preview-cell-edit {
            font-size: 10px;
            font-weight: 500;
            color: var(--qp-hotel-muted);
            border-bottom: none !important;
        }

        .qp-hotel-stars {
            display: flex;
            align-items: center;
            gap: 2px;
            margin-top: 3px;
            line-height: 1;
        }

        .qp-hotel-stars .fa-star {
            color: var(--qp-hotel-red);
            font-size: 9px;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        @media (max-width: 900px) {
            .qp-hotel-row-fields {
                grid-template-columns: repeat(4, minmax(0, 1fr));
            }
        }

        @media (max-width: 640px) {
            .qp-hotel-row-fields {
                grid-template-columns: 1fr 1fr;
            }

            .qp-hotel-field-hotel,
            .qp-hotel-field-city,
            .qp-hotel-field-room {
                grid-column: 1 / -1;
            }

            .qp-hotel-sec-top {
                flex-wrap: wrap;
            }

            .qp-hotel-sec-rule {
                display: none;
            }

            .qp-hotel-sec-slogan {
                width: 100%;
                text-align: left;
                white-space: normal;
            }
        }

        .qp-itin-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            margin: 2px 0 16px;
        }

        .qp-itin-head-left {
            display: flex;
            align-items: stretch;
            gap: 10px;
            min-width: 0;
            flex: 1 1 auto;
        }

        .qp-itin-vbar {
            width: 4px;
            border-radius: 999px;
            background: #d92027;
            flex-shrink: 0;
            align-self: stretch;
            min-height: 28px;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .qp-itin-head-copy {
            min-width: 0;
            flex: 1 1 auto;
            padding-top: 1px;
        }

        .qp-itin-title {
            display: flex;
            align-items: baseline;
            gap: 7px;
            flex-wrap: wrap;
            margin: 0;
            line-height: 1.15;
        }

        .qp-itin-title .qp-itin-black,
        .qp-itin-black {
            color: #1a1d23;
            font-weight: 800;
            font-size: 15px;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .qp-itin-title .qp-itin-red,
        .qp-itin-red {
            color: #d92027;
            font-weight: 800;
            font-size: 15px;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .qp-itin-rule {
            position: relative;
            height: 1px;
            background: #d7dbe3;
            margin-top: 8px;
            width: 100%;
        }

        .qp-itin-rule-accent {
            position: absolute;
            left: 0;
            top: 0;
            width: 72px;
            height: 2px;
            background: #d92027;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .qp-itin-art {
            flex-shrink: 0;
            line-height: 0;
            opacity: 0.95;
            margin-top: -2px;
        }

        .qp-itin-mtn {
            width: 108px;
            height: 44px;
            display: block;
        }

        /* —— Info / data cards —— */
        .qp-info-card {
            display: grid;
            gap: 0;
            border: 0;
            border-radius: 12px;
            overflow: hidden;
            background: var(--qp-card);
            margin-bottom: 4px;
            box-shadow: none;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .qp-info-card.qp-cols-3 {
            grid-template-columns: repeat(3, 1fr);
        }

        .qp-info-card.qp-cols-5 {
            grid-template-columns: repeat(5, 1fr);
        }

        .qp-info-card.qp-cols-5.qp-travel-details {
            grid-template-columns:
                minmax(0, 1.7fr)
                minmax(0, 1.15fr)
                minmax(0, 1fr)
                minmax(0, 0.55fr)
                minmax(0, 0.55fr);
        }

        .qp-travel-details .qp-info-cell:first-child .qp-info-value {
            white-space: normal;
            word-break: break-word;
            line-height: 1.25;
        }

        .qp-info-cell {
            padding: 6px 7px;
            border-right: 1px solid #d7e0ea;
            text-align: center;
            min-width: 0;
        }

        .qp-info-cell:last-child {
            border-right: none;
        }

        .qp-info-label {
            display: block;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.01em;
            text-transform: none;
            color: var(--qp-muted);
            margin-bottom: 5px;
        }

        .qp-info-value {
            font-size: 14px;
            font-weight: 500;
            color: var(--qp-ink);
            word-break: break-word;
            line-height: 1.3;
        }

        .qp-info-value .q-preview-cell-edit,
        .qp-info-value .q-preview-editable {
            font-weight: 500;
        }

        /* —— Tables —— */
        .q-preview-table,
        .qp-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
            font-size: 12px;
        }

        .qp-table th,
        .qp-table td,
        .q-preview-table th,
        .q-preview-table td {
            border: 1px solid #dddde2;
            padding: 8px 8px;
            vertical-align: middle;
        }

        .qp-table th,
        .q-preview-table th {
            background: #f0f0f3;
            font-weight: 700;
            text-align: center;
            color: var(--qp-ink);
            font-size: 11px;
            letter-spacing: 0.02em;
            text-transform: uppercase;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .qp-table td,
        .q-preview-table td {
            text-align: center;
        }

        .qp-table td.text-left,
        .q-preview-table td.text-left {
            text-align: left;
        }

        .qp-table td.text-right,
        .q-preview-table td.text-right {
            text-align: right;
        }

        .qp-table tbody tr:nth-child(even):not(.q-preview-layover):not(.qp-layover),
        .q-preview-table tbody tr:nth-child(even):not(.q-preview-layover):not(.qp-layover) {
            background: #fafafb;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .q-preview-layover td,
        .qp-layover td {
            background: #fff8e1 !important;
            font-size: 12px;
            text-align: left !important;
            color: #7a5c00;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .q-preview-section-title {
            display: none;
        }

        /* —— Day itinerary —— */
        .qp-sec-itinerary {
            --qp-itin-red: #d92027;
            --qp-itin-ink: #1a1d23;
            --qp-itin-muted: #6b7280;
            --qp-itin-line: #e5e7eb;
            font-family: inherit;
        }

        .q-preview-day,
        .qp-day {
            margin-bottom: 14px;
            border: 1px solid #e8ecf1;
            border-left: 4px solid var(--qp-itin-red);
            border-radius: 12px;
            background: #fff;
            padding: 0;
            overflow: hidden;
            box-shadow: 0 4px 14px rgba(17, 24, 39, 0.06);
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .q-preview-day-head,
        .qp-day-head {
            background: transparent;
            font-weight: 600;
            text-transform: none;
            padding: 12px 14px 10px;
            font-size: 12px;
            margin: 0;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 10px 0;
            border-bottom: 1px solid var(--qp-itin-line);
        }

        .qp-day-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: var(--qp-itin-red);
            color: #fff;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.04em;
            padding: 5px 12px;
            border-radius: 999px;
            line-height: 1;
            white-space: nowrap;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .qp-day-sep {
            width: 1px;
            height: 16px;
            background: #d1d5db;
            margin: 0 12px;
            flex-shrink: 0;
        }

        .qp-day-meta {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: var(--qp-itin-ink);
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
        }

        .qp-day-meta i {
            color: var(--qp-itin-red);
            font-size: 12px;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .qp-day-title {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-weight: 700;
            color: var(--qp-itin-ink);
            font-size: 12px;
            letter-spacing: 0.02em;
            text-transform: uppercase;
            min-width: 0;
            flex: 1 1 160px;
        }

        .qp-day-title i {
            color: var(--qp-itin-red);
            font-size: 12px;
            flex-shrink: 0;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .qp-day-title-edit,
        .qp-day-title .q-preview-editable {
            font-weight: 700;
            font-size: 12px;
            letter-spacing: 0.02em;
            text-transform: uppercase;
            color: var(--qp-itin-ink);
            min-width: 0;
            border-bottom: none !important;
        }

        .q-preview-day-body,
        .qp-day-body {
            padding: 12px 14px 14px;
            color: #374151;
            font-size: 12.5px;
        }

        .qp-day-main {
            display: block;
        }

        .qp-day-main.has-photo {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 180px;
            gap: 16px;
            align-items: start;
        }

        .qp-day-content {
            min-width: 0;
        }

        .qp-day-desc,
        .q-preview-day-body .q-preview-rich {
            color: #374151;
            font-size: 12.5px;
            font-weight: 400;
            line-height: 1.55;
        }

        .qp-day-desc strong,
        .qp-day-desc b,
        .q-preview-day-body .q-preview-rich strong,
        .q-preview-day-body .q-preview-rich b {
            color: var(--qp-itin-ink);
            font-weight: 700;
        }

        .q-preview-day-body p,
        .qp-day-body p {
            margin: 0 0 8px;
        }

        .q-preview-day-body ul,
        .qp-day-body ul {
            margin: 0 0 8px 18px;
            padding: 0;
        }

        .qp-day-photo {
            width: 180px;
            flex-shrink: 0;
        }

        .qp-day-photo img {
            width: 180px;
            height: 140px;
            object-fit: cover;
            border-radius: 14px;
            display: block;
            box-shadow:0 6px 10px rgb(187 187 187 / 90%);
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .q-preview-day-body img,
        .qp-day-body .qp-day-desc img,
        .qp-day-body .q-preview-rich img {
            max-width: 100%;
            height: auto;
            border-radius: 10px;
            margin-top: 6px;
        }

        .qp-day-main.has-photo .qp-day-desc img,
        .qp-day-main.has-photo .q-preview-rich img {
            display: none;
        }

        .qp-day-pills {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
            margin-top: 12px;
        }

        .qp-day-pill-sep {
            width: 1px;
            height: 16px;
            background: #d1d5db;
            flex-shrink: 0;
        }

        .qp-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 11px;
            font-weight: 500;
            letter-spacing: 0;
            text-transform: none;
            padding: 6px 11px;
            border-radius: 999px;
            background: #fdecee;
            color: #374151;
            line-height: 1.2;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .qp-pill i {
            color: var(--qp-itin-red);
            font-size: 11px;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .qp-pill-text strong {
            color: var(--qp-itin-ink);
            font-weight: 700;
        }

        .qp-pill-overnight,
        .qp-pill-meal {
            background: #ebebeb;
            color: #374151;
        }

        @media (max-width: 720px) {
            .qp-itin-art {
                display: none;
            }

            .qp-day-main.has-photo {
                grid-template-columns: 1fr;
            }

            .qp-day-photo {
                width: 180px;
                margin: 0 auto;
            }

            .qp-day-photo img {
                width: 180px;
                height: 140px;
            }

            .qp-day-sep {
                display: none;
            }

            .qp-day-head {
                gap: 8px;
            }
        }

        .q-preview-itinerary-meta {
            font-size: 11px;
            color: var(--qp-muted);
            margin-bottom: 10px;
        }

        .q-preview-rich {
            margin-bottom: 10px;
        }

        .q-preview-rich p {
            margin: 0 0 6px;
        }

        .q-preview-rich ul {
            margin: 0;
            padding: 0;
            list-style: none;
        }

        /* —— Inclusions —— */
        .qp-incl-banner {
            position: relative;
            overflow: hidden;
            background: linear-gradient(100deg, #8b1218 0%, #6e0e14 28%, #3a090c 62%, #1a0507 100%);
            color: #fff;
            border-radius: 6px;
            padding: 10px 18px;
            margin-bottom: 12px;
            min-height: 0;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .qp-incl-banner-inner {
            position: relative;
            z-index: 1;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            min-height: 0;
        }

        .qp-incl-left {
            min-width: 0;
            flex: 1 1 auto;
        }

        .qp-incl-main {
            font-size: 22px;
            font-weight: 800;
            letter-spacing: 0.04em;
            line-height: 1.05;
            text-transform: uppercase;
            color: #fff;
            margin: 0;
        }

        .qp-incl-sub {
            font-size: 9px;
            font-weight: 600;
            letter-spacing: 0.16em;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.9);
            margin-top: 3px;
            line-height: 1.25;
        }

        .qp-incl-right {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-shrink: 0;
        }

        .qp-incl-right-bar {
            width: 2px;
            align-self: stretch;
            min-height: 40px;
            background: #c4121a;
            flex-shrink: 0;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .qp-incl-right-copy {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 0;
            font-size: 8px;
            font-weight: 600;
            letter-spacing: 0.12em;
            line-height: 1.3;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.95);
            text-align: right;
            white-space: nowrap;
        }

        .qp-incl-right-dash {
            display: block;
            width: 18px;
            height: 2px;
            margin-top: 4px;
            background: #e11d2e;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        @media (max-width: 640px) {
            .qp-incl-banner {
                padding: 10px 12px;
            }

            .qp-incl-banner-inner {
                flex-direction: column;
                align-items: flex-start;
            }

            .qp-incl-main {
                font-size: 18px;
            }

            .qp-incl-right {
                width: 100%;
            }

            .qp-incl-right-copy {
                align-items: flex-start;
                text-align: left;
            }
        }

        .qp-incl-edit.q-preview-rich ul {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 10px;
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .qp-incl-edit.q-preview-rich ul li {
            background: #fff;
            border: 1px solid var(--qp-line);
            border-radius: 8px;
            padding: 10px 12px 10px 36px;
            position: relative;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
            font-size: 12.5px;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .qp-incl-edit.q-preview-rich ul li::before {
            content: "\f00c";
            font-family: "Font Awesome 5 Free";
            font-weight: 900;
            position: absolute;
            left: 12px;
            top: 11px;
            color: #16a34a;
            font-size: 12px;
        }

        .qp-incl-edit.q-preview-rich > *:not(ul) {
            background: #fff;
            border: 1px solid var(--qp-line);
            border-radius: 8px;
            padding: 12px 14px;
            margin-bottom: 8px;
        }

        /* —— Terms —— */
        .qp-sec-terms {
            --qp-terms-red: #d92027;
            --qp-terms-ink: #1a1d23;
            --qp-terms-muted: #8b93a0;
            font-family: inherit;
        }

        .qp-terms-card {
            border: 1px solid #e8ecf1;
            border-radius: 14px;
            overflow: hidden;
            background: #fff;
            box-shadow: 0 4px 16px rgba(17, 24, 39, 0.06);
            padding: 18px 20px 16px;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .qp-terms-head {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 0;
            margin: 0 0 16px;
            border: 0;
            background: transparent;
        }

        .qp-terms-vbar {
            width: 4px;
            min-height: 36px;
            align-self: stretch;
            background: var(--qp-terms-red);
            border-radius: 999px;
            flex-shrink: 0;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .qp-terms-icon {
            width: 34px;
            height: 34px;
            border-radius: 8px;
            border: 1.5px solid var(--qp-terms-red);
            color: var(--qp-terms-red);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            flex-shrink: 0;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .qp-terms-head-copy {
            min-width: 0;
            padding-top: 1px;
        }

        .qp-terms-title {
            margin: 0;
            font-size: 14px;
            font-weight: 800;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: var(--qp-terms-ink);
            line-height: 1.25;
        }

        .qp-terms-sub {
            margin-top: 4px;
            font-size: 10px;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--qp-terms-muted);
            line-height: 1.3;
        }

        .qp-terms-body {
            padding: 0;
        }

        .q-preview-policy-block,
        .qp-policy-block {
            padding: 0;
            margin: 0 0 12px;
            border: 0;
        }

        .q-preview-policy-block:last-child,
        .qp-policy-block:last-child {
            margin-bottom: 0;
        }

        .q-preview-policy-title,
        .qp-policy-title {
            font-weight: 700;
            font-size: 12px;
            margin: 0 0 8px;
            color: var(--qp-terms-ink);
            display: block;
            letter-spacing: 0.02em;
        }

        .q-preview-policy-title::before,
        .qp-policy-title::before {
            content: none;
        }

        .qp-terms-rich,
        .qp-policy-block .q-preview-rich {
            margin: 0;
            color: #374151;
            font-size: 12.5px;
            font-weight: 400;
            line-height: 1.55;
        }

        .qp-terms-rich > *:last-child,
        .qp-policy-block .q-preview-rich > *:last-child {
            margin-bottom: 0;
        }

        .qp-terms-check-list,
        .qp-terms-rich ul,
        .qp-terms-rich ol,
        .qp-policy-block .q-preview-rich ul,
        .qp-policy-block .q-preview-rich ol {
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .qp-terms-item,
        .qp-terms-check-list > li,
        .qp-terms-rich ul li,
        .qp-terms-rich ol li,
        .qp-policy-block .q-preview-rich ul li,
        .qp-policy-block .q-preview-rich ol li {
            position: relative;
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding-left: 0;
            margin: 0 0 12px;
            color: #374151;
            font-size: 12.5px;
            line-height: 1.55;
        }

        .qp-terms-item:last-child,
        .qp-terms-check-list > li:last-child,
        .qp-terms-rich ul li:last-child,
        .qp-terms-rich ol li:last-child,
        .qp-policy-block .q-preview-rich ul li:last-child,
        .qp-policy-block .q-preview-rich ol li:last-child {
            margin-bottom: 0;
        }

        .qp-terms-check {
            width: 18px;
            height: 18px;
            min-width: 18px;
            border-radius: 50%;
            background: var(--qp-terms-red);
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            margin-top: 1px;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .qp-terms-check i {
            font-size: 9px;
            line-height: 1;
            color: #fff;
        }

        .qp-terms-item-body {
            min-width: 0;
            flex: 1 1 auto;
        }

        .qp-terms-item-body p {
            margin: 0;
        }

        .qp-terms-rich > p,
        .qp-policy-block .q-preview-rich > p {
            position: relative;
            padding-left: 28px;
            margin: 0 0 12px;
        }

        .qp-terms-rich > p::before,
        .qp-policy-block .q-preview-rich > p::before {
            content: '';
            position: absolute;
            left: 0;
            top: 2px;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background-color: var(--qp-terms-red);
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath fill='%23fff' d='M6.5 11.2L3.3 8l1.1-1.1 2.1 2.1 5-5L12.6 5z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: center;
            background-size: 11px 11px;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .qp-terms-rich ul li::before,
        .qp-terms-rich ol li::before,
        .qp-policy-block .q-preview-rich ul li::before,
        .qp-policy-block .q-preview-rich ol li::before {
            content: none;
        }

        .qp-terms-rich ul li:not(.qp-terms-item)::before,
        .qp-terms-rich ol li:not(.qp-terms-item)::before,
        .qp-policy-block .q-preview-rich ul li:not(.qp-terms-item)::before,
        .qp-policy-block .q-preview-rich ol li:not(.qp-terms-item)::before {
            content: '';
            position: absolute;
            left: 0;
            top: 2px;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background-color: var(--qp-terms-red);
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath fill='%23fff' d='M6.5 11.2L3.3 8l1.1-1.1 2.1 2.1 5-5L12.6 5z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: center;
            background-size: 11px 11px;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .qp-terms-rich ul li:not(.qp-terms-item),
        .qp-terms-rich ol li:not(.qp-terms-item),
        .qp-policy-block .q-preview-rich ul li:not(.qp-terms-item),
        .qp-policy-block .q-preview-rich ol li:not(.qp-terms-item) {
            display: block;
            padding-left: 28px;
        }

        .qp-terms-foot {
            margin-top: 16px;
            padding-top: 2px;
        }

        .qp-terms-more {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 12px;
            border: 1.5px solid var(--qp-terms-red);
            border-radius: 8px;
            background: #fff;
            color: var(--qp-terms-ink);
            font-size: 12px;
            font-weight: 500;
            line-height: 1.2;
            text-decoration: none !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .qp-terms-more:hover,
        .qp-terms-more:focus {
            color: var(--qp-terms-ink);
            background: #fff8f8;
            text-decoration: none !important;
        }

        .qp-terms-more-here {
            color: var(--qp-terms-red);
            font-weight: 700;
        }

        .qp-terms-more i {
            color: var(--qp-terms-red);
            font-size: 11px;
        }

        /* —— Exclusions —— */
        .qp-excl-head {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }

        .qp-excl-head .qp-bar {
            width: 4px;
            height: 22px;
            background: var(--qp-red);
            border-radius: 2px;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .qp-excl-head h3 {
            margin: 0;
            font-size: 13px;
            font-weight: 800;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .qp-excl-head h3 i {
            color: var(--qp-red);
        }

        .qp-excl-edit.q-preview-rich ul {
            list-style: none;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .qp-excl-edit.q-preview-rich ul li {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            background: #fff;
            border: 1px solid #f3d0d4;
            border-radius: 8px;
            padding: 10px 12px;
            font-size: 12.5px;
        }

        .qp-excl-edit.q-preview-rich ul li::before {
            content: "\f05e";
            font-family: "Font Awesome 5 Free";
            font-weight: 900;
            background: #fde8ea;
            color: var(--qp-red);
            width: 28px;
            height: 28px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 12px;
            line-height: 28px;
            text-align: center;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        /* —— Tour cost —— */
        .qp-tour-cost {
            margin: 16px 0 8px;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid var(--qp-line);
        }

        .qp-tour-cost-head {
            background: var(--qp-red);
            color: #fff;
            font-weight: 800;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            padding: 10px 14px;
            font-size: 13px;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .qp-tour-cost .q-preview-table,
        .qp-tour-cost .qp-table,
        .q-preview-cost {
            margin: 0;
            border: none;
        }

        .qp-tour-cost .q-preview-table th,
        .qp-tour-cost .q-preview-table td,
        .q-preview-cost th,
        .q-preview-cost td {
            border-left: none;
            border-right: none;
            border-color: var(--qp-line);
            padding: 10px 14px;
        }

        .q-preview-cost td:first-child {
            text-align: left;
        }

        .q-preview-cost td:last-child {
            text-align: right;
            white-space: nowrap;
            font-weight: 600;
        }

        .qp-tour-cost .q-preview-cost tbody tr:last-child td,
        .q-preview-cost tr.qp-cost-total td {
            background: #2a2a2e;
            color: #fff;
            font-weight: 700;
            border-color: #2a2a2e;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .qp-tour-cost .q-preview-cost tbody tr:last-child td:last-child,
        .q-preview-cost tr.qp-cost-total td:last-child {
            color: #ff6b73;
            font-size: 1.05rem;
        }

        .q-preview-gst-note {
            font-size: 11px;
            color: var(--qp-muted);
            margin-top: 8px;
            padding: 0 2px;
        }

        /* —— Global Accreditations —— */
        .qp-sec-acc {
            margin: 18px 0 8px;
            font-family: inherit;
        }

        .qp-acc-wrap {
            position: relative;
            overflow: hidden;
            border-radius: 14px;
            border: 1px solid #e8ecf1;
            background-color: #fafbfc;
            background-image:
                radial-gradient(circle at 18% 30%, rgba(209, 213, 219, 0.35) 0 1px, transparent 1.5px),
                radial-gradient(circle at 42% 62%, rgba(209, 213, 219, 0.28) 0 1px, transparent 1.5px),
                radial-gradient(circle at 68% 28%, rgba(209, 213, 219, 0.3) 0 1px, transparent 1.5px),
                radial-gradient(circle at 82% 70%, rgba(209, 213, 219, 0.25) 0 1px, transparent 1.5px),
                linear-gradient(180deg, #ffffff 0%, #f7f8fa 100%);
            background-size: 48px 48px, 56px 56px, 44px 44px, 52px 52px, auto;
            padding: 22px 16px 18px;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .qp-acc-deco {
            position: absolute;
            top: 10px;
            right: 14px;
            width: 120px;
            height: 48px;
            pointer-events: none;
            opacity: 0.9;
        }

        .qp-acc-plane {
            width: 100%;
            height: 100%;
            display: block;
        }

        .qp-acc-head {
            text-align: center;
            margin: 0 auto 18px;
            max-width: 560px;
            position: relative;
            z-index: 1;
        }

        .qp-acc-title {
            font-size: 18px;
            font-weight: 800;
            color: #111827;
            letter-spacing: 0.01em;
            line-height: 1.2;
        }

        .qp-acc-rule {
            width: 64px;
            height: 3px;
            border-radius: 999px;
            background: #d92027;
            margin: 8px auto 10px;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .qp-acc-sub {
            font-size: 12px;
            font-weight: 500;
            color: #8b93a0;
            line-height: 1.4;
        }

        .qp-acc-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
            position: relative;
            z-index: 1;
        }

        .qp-acc-card {
            position: relative;
            overflow: hidden;
            background: #fff;
            border: 1px solid #f0b4b8;
            border-radius: 14px;
            padding: 16px 12px 14px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(17, 24, 39, 0.04);
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .qp-acc-card::after {
            content: '';
            position: absolute;
            left: -18px;
            bottom: -28px;
            width: 90px;
            height: 70px;
            background: radial-gradient(ellipse at center, rgba(233, 100, 110, 0.16) 0%, rgba(233, 100, 110, 0.08) 45%, transparent 70%);
            border-radius: 60% 40% 55% 45%;
            pointer-events: none;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .qp-acc-logo {
            width: 72px;
            height: 56px;
            margin: 0 auto 12px;
            border-radius: 0;
            border: 0;
            background: transparent;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: visible;
            position: relative;
            z-index: 1;
        }

        .qp-acc-logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            display: block;
        }

        .qp-acc-card-title {
            font-size: 12px;
            font-weight: 700;
            color: #111827;
            line-height: 1.35;
            margin: 0 0 8px;
            position: relative;
            z-index: 1;
        }

        .qp-acc-card-rule {
            width: 36px;
            height: 2px;
            background: #d92027;
            margin: 0 auto 0;
            border-radius: 999px;
            position: relative;
            z-index: 1;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        @media (max-width: 900px) {
            .qp-acc-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 520px) {
            .qp-acc-grid {
                grid-template-columns: 1fr;
            }

            .qp-acc-deco {
                display: none;
            }
        }

        /* —— Trusted Reviews —— */
        .qp-sec-reviews {
            margin: 16px 0 8px;
            font-family: inherit;
        }

        .qp-rev-wrap {
            background: #fff;
            border: 0;
            border-radius: 0;
            padding: 8px 4px 6px;
            box-shadow: none;
            text-align: center;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .qp-rev-head {
            text-align: center;
            margin: 0 auto 18px;
            max-width: 720px;
        }

        .qp-rev-brand {
            display: flex;
            justify-content: center;
            margin-bottom: 10px;
        }

        .qp-rev-brand .qp-rev-google-logo {
            width: 28px;
            height: 28px;
        }

        .qp-rev-title {
            font-size: 22px;
            font-weight: 800;
            color: #1f2937;
            letter-spacing: -0.02em;
            line-height: 1.25;
            margin-bottom: 6px;
        }

        .qp-rev-count {
            color: #1a73e8;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .qp-rev-sub {
            font-size: 13px;
            font-weight: 500;
            color: #6b7280;
            margin-bottom: 14px;
            line-height: 1.4;
        }

        .qp-rev-badge {
            display: inline-flex;
            align-items: center;
            flex-wrap: wrap;
            justify-content: center;
            gap: 8px;
            padding: 8px 16px;
            border: 1px solid #e5e7eb;
            border-radius: 999px;
            background: #fff;
            max-width: 100%;
            box-shadow: 0 1px 2px rgba(17, 24, 39, 0.04);
        }

        .qp-rev-google-logo {
            display: block;
            flex-shrink: 0;
        }

        .qp-rev-badge-label {
            font-size: 13px;
            font-weight: 600;
            color: #374151;
        }

        .qp-rev-badge-sep {
            width: 1px;
            height: 16px;
            background: #d1d5db;
            flex-shrink: 0;
        }

        .qp-rev-badge-score {
            font-size: 15px;
            font-weight: 800;
            color: #111827;
            line-height: 1;
        }

        .qp-rev-rating-stars {
            display: inline-flex;
            align-items: center;
            gap: 2px;
            color: #fbbc04;
            font-size: 12px;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .qp-rev-badge-meta {
            font-size: 12px;
            font-weight: 500;
            color: #9ca3af;
        }

        .qp-rev-carousel {
            position: relative;
            display: block;
            margin: 0 auto 16px;
            max-width: 100%;
        }

        .qp-rev-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
            min-width: 0;
            text-align: left;
        }

        .qp-rev-card {
            background: #fff;
            border: 1px solid #eef1f5;
            border-radius: 14px;
            padding: 14px 14px 12px;
            min-width: 0;
            box-shadow: 0 4px 14px rgba(17, 24, 39, 0.05);
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .qp-rev-card-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 8px;
            margin-bottom: 10px;
        }

        .qp-rev-person {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 0;
        }

        .qp-rev-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            font-weight: 700;
            color: #fff;
            flex-shrink: 0;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .qp-rev-avatar.tone-a { background: linear-gradient(135deg, #f59e0b, #ea580c); }
        .qp-rev-avatar.tone-b { background: linear-gradient(135deg, #38bdf8, #2563eb); }
        .qp-rev-avatar.tone-c { background: linear-gradient(135deg, #fb7185, #db2777); }

        .qp-rev-person-meta {
            min-width: 0;
        }

        .qp-rev-name {
            font-size: 13px;
            font-weight: 700;
            color: #111827;
            line-height: 1.25;
        }

        .qp-rev-place {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 11px;
            font-weight: 500;
            color: #6b7280;
            margin-top: 3px;
            line-height: 1.3;
        }

        .qp-rev-flag {
            font-size: 12px;
            line-height: 1;
        }

        .qp-rev-card-meta {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 6px;
            flex-shrink: 0;
        }

        .qp-rev-more {
            color: #9ca3af;
            font-size: 12px;
            line-height: 1;
        }

        .qp-rev-ago {
            font-size: 10px;
            font-weight: 500;
            color: #9ca3af;
            white-space: nowrap;
        }

        .qp-rev-stars {
            display: flex;
            align-items: center;
            gap: 2px;
            margin-bottom: 8px;
        }

        .qp-rev-stars .fa-star {
            color: #fbbc04;
            font-size: 12px;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .qp-rev-text {
            font-size: 12.5px;
            font-weight: 400;
            color: #4b5563;
            line-height: 1.55;
            margin-bottom: 12px;
            min-height: 3.1em;
        }

        .qp-rev-verified {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            font-size: 11px;
            font-weight: 500;
            color: #6b7280;
        }

        .qp-rev-shield {
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: #1a73e8;
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            clip-path: none;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .qp-rev-shield i {
            font-size: 8px;
            line-height: 1;
            color: #fff;
        }

        .qp-rev-more-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 18px;
            border: 1px solid #e5e7eb;
            border-radius: 999px;
            background: #fff;
            color: #1a73e8;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none !important;
            box-shadow: 0 1px 2px rgba(17, 24, 39, 0.04);
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .qp-rev-more-btn:hover,
        .qp-rev-more-btn:focus {
            color: #1557b0;
            text-decoration: none !important;
            border-color: #d1d5db;
        }

        .qp-rev-more-btn i {
            font-size: 11px;
        }

        @media (max-width: 900px) {
            .qp-rev-grid {
                grid-template-columns: 1fr;
            }

            .qp-rev-title {
                font-size: 18px;
            }
        }

        /* —— Trust Stats —— */
        .qp-sec-trust-stats {
            margin: 4px 0 10px;
            font-family: inherit;
        }

        .qp-trust-stats {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            align-items: center;
            border-top: 1px solid #e5e7eb;
            border-bottom: 1px solid #e5e7eb;
            background: #fff;
            padding: 14px 4px;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .qp-trust-stat {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 4px 12px;
            min-width: 0;
        }

        .qp-trust-stat.has-sep {
            border-right: 1px solid #e5e7eb;
        }

        .qp-trust-stat-icon {
            color: #d92027;
            font-size: 20px;
            width: 24px;
            text-align: center;
            flex-shrink: 0;
            line-height: 1;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .qp-trust-stat-icon svg {
            display: block;
        }

        .qp-trust-stat-copy {
            min-width: 0;
            text-align: left;
        }

        .qp-trust-stat-value {
            font-size: 15px;
            font-weight: 800;
            color: #1a1d23;
            line-height: 1.2;
        }

        .qp-trust-stat-label {
            font-size: 11px;
            font-weight: 500;
            color: #8b93a0;
            line-height: 1.3;
            margin-top: 1px;
        }

        @media (max-width: 800px) {
            .qp-trust-stats {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                row-gap: 12px;
            }

            .qp-trust-stat.has-sep {
                border-right: 0;
            }

            .qp-trust-stat:nth-child(odd) {
                border-right: 1px solid #e5e7eb;
            }
        }

        @media (max-width: 480px) {
            .qp-trust-stats {
                grid-template-columns: 1fr;
            }

            .qp-trust-stat:nth-child(odd),
            .qp-trust-stat.has-sep {
                border-right: 0;
            }

            .qp-trust-stat {
                justify-content: flex-start;
                padding-left: 8px;
            }
        }

        /* —— Footer contact —— */
        .q-preview-footer,
        .qp-footer {
            margin-top: 22px;
            text-align: left;
            padding-top: 16px;
            border-top: 1px solid #e5e7eb;
            font-family: inherit;
        }

        .qp-foot-contacts {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            align-items: center;
            gap: 0;
            margin-bottom: 14px;
        }

        .qp-foot-cell {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 6px 14px;
            min-width: 0;
        }

        .qp-foot-cell + .qp-foot-cell {
            border-left: 1px solid #e5e7eb;
        }

        .qp-foot-ico {
            width: 28px;
            height: 28px;
            min-width: 28px;
            border-radius: 50%;
            background: #d92027;
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            flex-shrink: 0;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .qp-foot-text {
            font-size: 12px;
            font-weight: 600;
            color: #1a1d23;
            line-height: 1.35;
            min-width: 0;
            word-break: break-word;
        }

        .qp-foot-phone-sep {
            color: #d92027;
            font-weight: 700;
            padding: 0 2px;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .qp-foot-cell-address .qp-foot-text {
            font-size: 11px;
            font-weight: 500;
        }

        .q-preview-services-bar,
        .qp-services-bar {
            display: inline-block;
            background: #d92027;
            color: #fff;
            font-weight: 700;
            font-size: 11px;
            letter-spacing: 0.08em;
            padding: 9px 22px;
            border-radius: 999px;
            margin: 0;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        @media (max-width: 900px) {
            .qp-foot-contacts {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                row-gap: 12px;
            }

            .qp-foot-cell + .qp-foot-cell {
                border-left: 0;
            }

            .qp-foot-cell:nth-child(odd) {
                border-right: 1px solid #e5e7eb;
            }
        }

        @media (max-width: 560px) {
            .qp-foot-contacts {
                grid-template-columns: 1fr;
            }

            .qp-foot-cell:nth-child(odd) {
                border-right: 0;
            }

            .qp-foot-cell {
                padding-left: 0;
            }
        }

        /* —— Editable —— */
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
            background: rgba(196, 18, 26, 0.04);
        }

        .q-preview-editable:focus,
        .q-preview-editable.is-editing {
            background: rgba(250, 204, 21, 0.18);
            outline: none;
            box-shadow: none;
        }

        @media (max-width: 720px) {
            .qp-doc-head {
                flex-direction: column;
                align-items: center;
                text-align: center;
            }

            .qp-ref-card {
                width: 100%;
            }

            .qp-info-card.qp-cols-3,
            .qp-info-card.qp-cols-5 {
                grid-template-columns: 1fr;
            }

            .qp-info-cell {
                border-right: none;
                border-bottom: 1px solid #d7e0ea;
                text-align: center;
            }

            .qp-brand {
                max-width: none;
            }

            .qp-logo {
                max-width: none;
            }

            .qp-ref-card {
                width: 100%;
                max-width: none;
            }

            .qp-info-cell:last-child {
                border-bottom: none;
            }

            .qp-sec-slogan {
                width: 100%;
            }
        }

        @media print {
            @page {
                size: A4;
                margin: 0;
            }

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
                width: 210mm;
                min-height: 297mm;
                max-width: 210mm;
                margin: 0;
                padding: 8mm 10mm 10mm;
                border: none !important;
                border-radius: 0 !important;
                box-shadow: none !important;
            }

            .q-preview-editable,
            .q-preview-editable:hover,
            .q-preview-editable:focus,
            .q-preview-editable.is-editing {
                outline: none !important;
                box-shadow: none !important;
                background: transparent !important;
            }

            #qPreviewPrintArea,
            #qPreviewPrintArea * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
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
        [data-theme="dark"] .crm-quotation-gen .q-wizard-step[data-q-step="5"] .q-terms-item,
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
        [data-theme="dark"] .crm-quotation-gen .q-terms-item-label,
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
        [data-theme="dark"] .crm-quotation-gen .q-wizard-step[data-q-step="5"] .q-terms-item-head,
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

        [data-theme="dark"] .crm-quotation-gen .q-day-toolbar,
        [data-theme="dark"] .crm-quotation-gen .q-day-image-panel,
        [data-theme="dark"] .crm-quotation-gen .q-day-meta-card {
            background: var(--q-card-bg) !important;
            border-color: var(--q-border) !important;
        }

        [data-theme="dark"] .crm-quotation-gen .q-day-head-sub,
        [data-theme="dark"] .crm-quotation-gen .q-day-image-count,
        [data-theme="dark"] .crm-quotation-gen .q-day-char-count,
        [data-theme="dark"] .crm-quotation-gen .q-day-meta-label {
            color: var(--q-text-muted) !important;
        }

        [data-theme="dark"] .crm-quotation-gen .q-floating-label {
            background: var(--q-card-bg) !important;
        }

        [data-theme="dark"] .crm-quotation-gen .q-floating-field .q-day-title {
            background: var(--mz-theme-bg-elevated, #2a2e38) !important;
            color: var(--q-text) !important;
        }

        [data-theme="dark"] .crm-quotation-gen .q-day-nav-btn,
        [data-theme="dark"] .crm-quotation-gen .q-day-more-btn,
        [data-theme="dark"] .crm-quotation-gen .q-day-img-btn {
            background: var(--mz-theme-bg-elevated, #2a2e38) !important;
            border-color: var(--q-border) !important;
            color: var(--q-text) !important;
        }

        [data-theme="dark"] .crm-quotation-gen .q-day-nav-btn.q-day-nav-primary,
        [data-theme="dark"] .crm-quotation-gen .q-day-ai-btn {
            background: #c62828 !important;
            border-color: #c62828 !important;
            color: #fff !important;
        }

        [data-theme="dark"] .crm-quotation-gen .q-day-meta-icon {
            background: rgba(198, 40, 40, 0.18) !important;
            color: #f87171 !important;
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
        [data-theme="dark"] .crm-quotation-gen .q-pricing-amount-cell .cost-input {
            background: var(--mz-theme-input-bg, #1e2128) !important;
            border-color: var(--mz-theme-input-border, #454b58) !important;
            color: var(--q-text) !important;
        }

        [data-theme="dark"] .crm-quotation-gen .q-pricing-option-sheet .q-custom-cost .cc-label,
        [data-theme="dark"] .crm-quotation-gen .q-pricing-option-sheet .q-custom-cost .cc-amount {
            background: transparent !important;
            border-color: transparent !important;
            color: var(--q-text) !important;
        }

        [data-theme="dark"] .crm-quotation-gen .q-pricing-option-sheet .q-custom-cost-row {
            background: var(--mz-theme-bg-elevated, #2a2e38) !important;
            border-color: var(--q-border) !important;
        }

        [data-theme="dark"] .crm-quotation-gen .q-pricing-option-sheet .q-custom-cost-ico {
            background: rgba(225, 29, 46, 0.14) !important;
            border-right-color: var(--q-border) !important;
            color: #fca5a5 !important;
        }

        [data-theme="dark"] .crm-quotation-gen .q-pricing-option-sheet .q-custom-cost .q-custom-cost-amt,
        [data-theme="dark"] .crm-quotation-gen .q-pricing-option-sheet .q-custom-cost-remove {
            background: var(--mz-theme-input-bg, #1e2128) !important;
            border-left-color: var(--q-border) !important;
            color: var(--q-text-muted) !important;
        }

        [data-theme="dark"] .crm-quotation-gen .q-pricing-amount-cell::before,
        [data-theme="dark"] .crm-quotation-gen .q-pricing-option-sheet .q-custom-cost .q-custom-cost-amt::before {
            color: var(--q-text-muted) !important;
        }

        [data-theme="dark"] .crm-quotation-gen .q-pricing-supplier-name {
            color: var(--q-text-muted) !important;
        }

        [data-theme="dark"] .crm-quotation-gen .q-pricing-add-cell .btn,
        [data-theme="dark"] .crm-quotation-gen .q-add-cost-row {
            background: var(--mz-theme-bg-elevated, #2a2e38) !important;
            border-color: rgba(225, 29, 46, 0.45) !important;
            color: #fca5a5 !important;
        }

        [data-theme="dark"] .q-extra-cost-modal .modal-content {
            background: var(--mz-theme-bg-surface, #22252d) !important;
            color: var(--q-text) !important;
        }

        [data-theme="dark"] .q-extra-cost-modal .q-extra-cost-modal-title {
            color: var(--q-text) !important;
        }

        [data-theme="dark"] .q-extra-cost-modal .q-extra-cost-modal-sub {
            color: var(--q-text-muted) !important;
        }

        [data-theme="dark"] .q-extra-cost-modal .q-extra-cost-field input {
            color: var(--q-text) !important;
            border-bottom-color: var(--q-border) !important;
        }

        [data-theme="dark"] .crm-quotation-gen .q-sheet-profit-block {
            background: var(--mz-theme-bg-elevated, #2a2e38) !important;
            border-color: var(--q-border) !important;
        }

        [data-theme="dark"] .crm-quotation-gen .q-pricing-option-sheet .q-profit-readonly {
            background: var(--mz-theme-input-bg, #1e2128) !important;
            border-color: var(--q-border) !important;
            color: var(--q-text) !important;
        }

        [data-theme="dark"] .crm-quotation-gen .q-pricing-option-sheet .q-profit-pct-group .form-control,
        [data-theme="dark"] .crm-quotation-gen .q-pricing-option-sheet .q-profit-row .q-sheet-profit-amount {
            background: var(--mz-theme-input-bg, #1e2128) !important;
            border-color: var(--q-border) !important;
            color: var(--q-text) !important;
        }

        [data-theme="dark"] .crm-quotation-gen .q-pricing-option-sheet .q-profit-pct-group .input-group-text {
            background: var(--mz-theme-bg-muted, #323744) !important;
            border-color: var(--q-border) !important;
            color: var(--q-text-muted) !important;
        }

        [data-theme="dark"] .crm-quotation-gen .q-profit-or,
        [data-theme="dark"] .crm-quotation-gen .q-pricing-option-sheet .q-profit-calc-hint {
            color: #6ea8fe !important;
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
        [data-theme="dark"] .crm-quotation-gen .q-wizard-step[data-q-step="5"] .q-terms-item-head,
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

        /* Embedded preview-only mode (opened from Leads modal iframe) */
        html.mz-embed body.q-preview-only,
        body.q-preview-only {
            background: #fff !important;
            overflow: auto !important;
            height: auto !important;
            min-height: 0 !important;
            padding: 0 !important;
            margin: 0 !important;
        }
        body.q-preview-only.hold-transition,
        body.q-preview-only.sidebar-mini,
        body.q-preview-only.layout-fixed {
            padding-top: 0 !important;
        }
        /* Hide the entire AdminLTE shell so it cannot reserve 100vh above the preview */
        body.q-preview-only .wrapper,
        html.mz-embed body.q-preview-only .wrapper,
        html.mz-embed body.q-preview-only .content-wrapper,
        body.q-preview-only .main-header,
        body.q-preview-only .main-sidebar,
        body.q-preview-only .main-footer,
        body.q-preview-only .modal-backdrop,
        body.q-preview-only .q-city-create-modal,
        body.q-preview-only .q-day-ai-modal,
        body.q-preview-only .q-extra-cost-modal,
        body.q-preview-only #qHotelCreateModal,
        body.q-preview-only #qFlightSearchModal,
        body.q-preview-only #qItineraryImageModal,
        body.q-preview-only #qSupplierMailModal {
            display: none !important;
            height: 0 !important;
            min-height: 0 !important;
            overflow: hidden !important;
            visibility: hidden !important;
        }
        body.q-preview-only #qPreviewOnlyMount,
        body.q-preview-only #qPreviewPrintArea {
            display: block !important;
            visibility: visible !important;
            background: #fff;
            min-height: auto;
            position: relative !important;
            z-index: 1;
        }
        body.q-preview-only #qPreviewModal {
            display: none !important;
        }
        body.q-preview-only .modal-open {
            overflow: auto !important;
            padding-right: 0 !important;
        }
    </style>
</head>

<body class="hold-transition sidebar-mini layout-fixed<?= !empty($qPreviewOnly) ? ' q-preview-only' : '' ?>">
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
                                        <div class="q-side-grid-item">
                                            <div class="q-side-grid-label"><i class="fas fa-star"></i> Hotel Star Category</div>
                                            <div class="q-side-grid-value"><?= htmlspecialchars((string) ($leadSidebar['travel']['hotel_star_category'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></div>
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
                            This is a saved draft. Continue filling the form, then open <strong>Preview Quotation</strong> and use <strong>Save Quotation</strong> when ready to publish.
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
                                                    <input type="text" name="tentative_date" id="q_tentative_date" class="form-control js-q-date-input" placeholder="dd/mm/yyyy" autocomplete="off" required>
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
                            <div class="q-section-next-bar">
                                <button type="button" class="btn btn-sm q-section-next-btn" data-q-next-from="1">
                                    Next <i class="fas fa-arrow-right ml-1"></i>
                                </button>
                            </div>
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
                            <div class="q-section-next-bar">
                                <button type="button" class="btn btn-sm q-section-next-btn" data-q-next-from="2">
                                    Next <i class="fas fa-arrow-right ml-1"></i>
                                </button>
                            </div>
                            </div>
                        </div>
                        </div>

                        <!-- Hotel Details -->
                        <div class="q-wizard-step" id="qWizardSection3" data-q-step="3">
                        <div class="q-card q-section-accordion">
                            <div class="q-section-accordion-head q-wizard-section-head collapsed" data-target="#qSectionBody3" role="button" tabindex="0" aria-expanded="false">
                                <div class="q-section-accordion-head-main">
                                    <h3 class="q-section-title">Hotel Details</h3>
                                    <p class="q-wizard-section-subtitle">Add one or more hotel options for your customer.</p>
                                </div>
                                <span class="q-section-accordion-toggle" aria-hidden="true"><i class="fas fa-chevron-down toggle-icon"></i></span>
                            </div>
                            <div class="q-section-accordion-body" id="qSectionBody3" style="display:none;">
                            <div class="q-hotel-cat-toolbar">
                                <div class="q-hotel-cat-tabs" id="qHotelCatTabs" role="tablist"></div>
                                <button type="button" class="btn q-hotel-add-option-btn" id="qAddHotelCategory" title="Add pricing option">
                                    <i class="fas fa-plus mr-1"></i>Add Option
                                </button>
                                <button type="button" class="btn q-hotel-remove-option-btn" id="qRemoveHotelCategory" title="Remove this option" style="display:none;">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                                <button type="button" class="btn q-hotel-add-btn" id="qAddHotelBtn">
                                    <i class="fas fa-plus mr-1"></i>Add Hotel
                                </button>
                            </div>
                            <div class="q-hotel-col-head" id="qHotelColHead">
                                <span>City</span>
                                <span>Hotel</span>
                                <span>Room Type</span>
                                <span>Rooms</span>
                                <span>Meal</span>
                                <span>Nights</span>
                                <span>Check-In</span>
                                <span>Check-Out</span>
                                <span>Rate (₹)</span>
                                <span>Supplier</span>
                                <span class="q-hotel-col-head-action"></span>
                            </div>
                            <div id="qHotelCategories"></div>
                            <div class="q-section-next-bar">
                                <button type="button" class="btn btn-sm q-section-next-btn" data-q-next-from="3">
                                    Next <i class="fas fa-arrow-right ml-1"></i>
                                </button>
                            </div>
                            </div>
                        </div>
                        </div>

                        <!-- Itinerary -->
                        <div class="q-wizard-step" id="qWizardSection4" data-q-step="4">
                        <div class="q-card q-section-accordion q-itin-section">
                            <div class="q-section-accordion-head q-wizard-section-head collapsed" data-target="#qSectionBody4" role="button" tabindex="0" aria-expanded="false">
                                <div class="q-section-accordion-head-main">
                                    <h3 class="q-section-title">Itinerary</h3>
                                    <p class="q-wizard-section-subtitle">Load a package or build your day-wise itinerary.</p>
                                </div>
                                <span class="q-section-accordion-toggle" aria-hidden="true"><i class="fas fa-chevron-down toggle-icon"></i></span>
                            </div>
                            <div class="q-section-accordion-body" id="qSectionBody4" style="display:none;">
                                <div class="q-itin-workspace">
                                    <div class="q-itin-main-col">
                                        <div class="q-itin-pkg-card q-itin-pkg-search-card">
                                            <div class="q-itin-pkg-card-hd">
                                                <div class="q-itin-pkg-hd-left">
                                                    <span class="q-itin-pkg-hd-icon" aria-hidden="true"><i class="fas fa-search"></i></span>
                                                    <div class="q-itin-pkg-hd-text">
                                                        <span class="q-itin-pkg-hd-label">Search Existing Package</span>
                                                        <span class="q-itin-pkg-hd-sub">Search by destination or package name and load to plan itinerary.</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="q-itin-search-body">
                                                <div class="q-itin-search-primary">
                                                    <div class="q-itin-pkg-search-wrap">
                                                        <i class="fas fa-search q-itin-pkg-search-icon-left" aria-hidden="true"></i>
                                                        <input type="text" class="form-control js-q-package-search" placeholder="Search by destination or package name..." autocomplete="off">
                                                        <button type="button" class="q-itin-pkg-search-clear" id="qItinPkgSearchClear" title="Clear" aria-label="Clear search" style="display:none;">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                        <div class="q-itin-pkg-menu js-q-package-menu" style="display:none;"></div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="q-itinerary-suppliers q-itin-suppliers-panel">
                                            <div class="q-itinerary-suppliers-hd">
                                                <div class="q-itin-suppliers-hd-left">
                                                    <span class="q-itin-pkg-hd-icon" aria-hidden="true"><i class="fas fa-users"></i></span>
                                                    <span class="q-itin-suppliers-title">Suppliers &amp; Rates</span>
                                                </div>
                                                <button type="button" class="btn q-itin-add-supplier-btn" id="qAddItinerarySupplier">
                                                    <i class="fas fa-plus mr-1"></i>Add Supplier
                                                </button>
                                            </div>
                                            <div class="table-responsive">
                                                <table class="q-itin-suppliers-table">
                                                    <thead>
                                                        <tr>
                                                            <th scope="col" style="width:48px;">#</th>
                                                            <th scope="col">Supplier</th>
                                                            <th scope="col" style="width:140px;">Rate (INR)</th>
                                                            <th scope="col" style="width:72px;">Delete</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="qItinerarySupplierRows"></tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="q-itin-side-col">
                                        <div class="q-itin-pkg-card q-itin-pkg-selected-card" id="qSelectedPackageCard">
                                            <div class="q-itin-selected-card-hd">
                                                <div class="q-itin-selected-hd">
                                                    <span class="q-itin-selected-badge-icon" aria-hidden="true"><i class="fas fa-suitcase"></i></span>
                                                    <span class="q-itin-selected-eyebrow">Selected Package</span>
                                                </div>
                                                <div class="dropdown">
                                                    <button type="button" class="btn q-itin-selected-more" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title="More">
                                                        <i class="fas fa-ellipsis-v"></i>
                                                    </button>
                                                    <div class="dropdown-menu dropdown-menu-right">
                                                        <button type="button" class="dropdown-item" id="qClearSelectedPackage">Clear selection</button>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="q-itin-selected-empty" id="qSelectedPackageEmpty">
                                                <span class="q-itin-selected-empty-icon" aria-hidden="true"><i class="fas fa-suitcase"></i></span>
                                                <p>Select a package from search to preview and load its itinerary.</p>
                                            </div>
                                            <div class="q-itin-selected-filled" id="qSelectedPackageFilled" style="display:none;">
                                                <div class="q-itin-selected-preview">
                                                    <div class="q-itin-selected-image-wrap">
                                                        <img class="q-itin-selected-image" id="qSelectedPackageImage" alt="Package image" style="display:none;">
                                                        <div class="q-itin-selected-image-fallback" id="qSelectedPackageImageFallback" aria-hidden="true">
                                                            <i class="fas fa-mountain"></i>
                                                        </div>
                                                    </div>
                                                    <div class="q-itin-selected-info">
                                                        <h4 class="q-itin-selected-title" id="qSelectedPackageTitle">—</h4>
                                                        <div class="q-itin-selected-meta" id="qSelectedPackageMeta"></div>
                                                        <p class="q-itin-selected-updated" id="qSelectedPackageUpdated"></p>
                                                    </div>
                                                </div>
                                                <button type="button" class="btn q-itin-load-btn" id="qApplyPackageItinerary" disabled>
                                                    <i class="fas fa-sign-in-alt mr-1"></i> Load Itinerary
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="q-itin-days-actions">
                                    <button type="button" class="btn q-itin-view-all-btn" id="qViewFullItineraryBtn">
                                        <i class="fas fa-list-ul mr-1"></i> View full itinerary
                                    </button>
                                </div>
                                <div id="qItineraryDays" class="q-itin-days"></div>
                            <div class="q-section-next-bar">
                                <button type="button" class="btn btn-sm q-section-next-btn" data-q-next-from="4">
                                    Next <i class="fas fa-arrow-right ml-1"></i>
                                </button>
                            </div>
                            </div>
                        </div>
                        </div>

                        <!-- Rich-text accordions -->
                        <div class="q-wizard-step" id="qWizardSection5" data-q-step="5">
                        <div class="q-card q-card-accordions q-section-accordion q-terms-section">
                            <div class="q-section-accordion-head q-wizard-section-head collapsed" data-target="#qSectionBody5" role="button" tabindex="0" aria-expanded="false">
                                <div class="q-section-accordion-head-main">
                                    <h3 class="q-section-title">Terms &amp; Policies</h3>
                                    <p class="q-wizard-section-subtitle">Load master terms or customize inclusions, exclusions, and policies.</p>
                                </div>
                                <span class="q-section-accordion-toggle" aria-hidden="true"><i class="fas fa-chevron-down toggle-icon"></i></span>
                            </div>
                            <div class="q-section-accordion-body" id="qSectionBody5" style="display:none;">
                            <div class="q-terms-actions q-section-body-toolbar">
                                    <button type="button" class="btn btn-sm q-flight-btn q-flight-btn-outline" id="qLoadTermsMasterBtn">
                                        <i class="fas fa-cloud-download-alt mr-1"></i> Load from Master
                                    </button>
                                    <a href="crm/quotation_terms_master.php" class="btn btn-sm q-flight-btn q-flight-btn-outline" target="_blank" rel="noopener">
                                        <i class="fas fa-cog mr-1"></i> Manage Master
                                    </a>
                            </div>
                            <div class="q-terms-list">
                        <?php
                        $richSections = crmQuotationTermsFields();
                        foreach ($richSections as $field => $label):
                            if ($quotation) {
                                $val = (string) ($quotation[$field] ?? '');
                            } else {
                                $val = (string) ($prefill[$field] ?? ($quotationTermsMaster[$field] ?? ''));
                            }
                            ?>
                            <div class="q-terms-item q-accordion-item">
                                <div class="q-terms-item-head q-accordion-head collapsed" data-target="#qbody_<?= $field ?>" role="button" tabindex="0" aria-expanded="false">
                                    <div class="q-terms-item-head-main">
                                        <i class="fas fa-chevron-down toggle-icon" aria-hidden="true"></i>
                                        <span class="q-terms-item-label"><?= htmlspecialchars($label) ?></span>
                                    </div>
                                </div>
                                <div class="q-terms-item-body q-accordion-body" id="qbody_<?= $field ?>" style="display:none;">
                                    <textarea name="<?= $field ?>" id="qed_<?= $field ?>" class="form-control q-editor"><?= htmlspecialchars($val) ?></textarea>
                                </div>
                            </div>
                        <?php endforeach; ?>
                            </div>
                            <div class="q-section-next-bar">
                                <button type="button" class="btn btn-sm q-section-next-btn" data-q-next-from="5">
                                    Next <i class="fas fa-arrow-right ml-1"></i>
                                </button>
                            </div>
                            </div>
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

    <div class="modal fade q-itin-loaded-modal" id="qItineraryLoadedModal" tabindex="-1" role="dialog" aria-labelledby="qItineraryLoadedModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document" style="max-width: 420px;">
            <div class="modal-content">
                <div class="modal-body text-center q-itin-loaded-body">
                    <div class="q-itin-loaded-icon" aria-hidden="true"><i class="fas fa-check"></i></div>
                    <h5 class="q-itin-loaded-title" id="qItineraryLoadedModalLabel">Itinerary loaded</h5>
                    <p class="q-itin-loaded-msg mb-0" id="qItineraryLoadedMsg">Itinerary loaded from package.</p>
                </div>
                <div class="modal-footer justify-content-center border-0 pt-0 pb-3">
                    <button type="button" class="btn q-itin-loaded-ok" data-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade q-full-itin-modal" id="qFullItineraryModal" tabindex="-1" role="dialog" aria-labelledby="qFullItineraryModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <div class="q-full-itin-modal-hd">
                        <div class="q-full-itin-modal-icon"><i class="fas fa-route"></i></div>
                        <div>
                            <h5 class="modal-title mb-0" id="qFullItineraryModalLabel">Full Itinerary</h5>
                            <p class="q-full-itin-modal-sub mb-0" id="qFullItineraryModalSub">All days at a glance — click a day to edit</p>
                        </div>
                    </div>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="q-full-itin-list" id="qFullItineraryList"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light border" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

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

    <div class="modal fade q-extra-cost-modal" id="qExtraCostModal" tabindex="-1" role="dialog" aria-labelledby="qExtraCostModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document" style="max-width: 380px;">
            <div class="modal-content">
                <form id="qExtraCostForm" action="#" method="post" onsubmit="return false;">
                    <div class="modal-body">
                        <h5 class="q-extra-cost-modal-title" id="qExtraCostModalLabel">Add Extra Cost</h5>
                        <p class="q-extra-cost-modal-sub">Add a named cost line (e.g. Visa, Permit, Guide)</p>
                        <div class="alert alert-danger d-none py-2 px-3" id="qExtraCostError"></div>
                        <div class="q-extra-cost-field">
                            <input type="text" id="qExtraCostName" name="extra_cost_name" placeholder="Name" autocomplete="off" required>
                        </div>
                        <div class="q-extra-cost-field mb-0">
                            <input type="number" step="0.01" min="0" id="qExtraCostAmount" name="extra_cost_amount" placeholder="Amount" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-link q-extra-cost-save">SAVE</button>
                        <button type="button" class="btn btn-link" data-dismiss="modal">CANCEL</button>
                    </div>
                </form>
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
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable qp-a4-dialog" role="document">
            <div class="modal-content qp-modal-content">
                <div class="modal-header qp-modal-header">
                    <div class="qp-modal-title-wrap">
                        <h5 class="modal-title mb-0" id="qPreviewModalLabel"><i class="fas fa-eye mr-2"></i>Quotation Preview</h5>
                        <small class="qp-modal-hint">Click any text to edit directly</small>
                        <small id="qPreviewUnsavedHint" class="qp-modal-unsaved d-none ml-2"><i class="fas fa-circle" style="font-size:7px;vertical-align:middle;"></i> Unsaved changes</small>
                    </div>
                    <button type="button" class="close qp-modal-close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div id="qPreviewPrintArea" class="q-preview-doc"></div>
                </div>
                <div class="modal-footer qp-modal-footer">
                    <button type="button" class="btn qp-btn-close btn-sm" data-dismiss="modal">Close</button>
                    <button type="button" class="btn qp-btn-edit btn-sm" id="qPreviewEditBtn" title="Open full quotation editor">
                        <i class="fas fa-edit mr-1"></i> Edit Quotation
                    </button>
                    <button type="button" class="btn btn-success btn-sm d-none" id="qPreviewSaveBtn" disabled>
                        <i class="fas fa-save mr-1"></i>Save Changes
                    </button>
                    <button type="button" class="btn qp-btn-save btn-sm" id="qSaveBtn">
                        <i class="fas fa-save mr-1"></i>Save Quotation
                    </button>
                    <button type="button" class="btn qp-btn-print btn-sm" id="qPreviewPrintBtn">
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
    <script src="crm/assets/quotation_generator.js?v=197"></script>
    <script src="crm/assets/quotation_flight_search.js?v=17"></script>
    <script src="crm/assets/quotation_itinerary_images.js?v=2"></script>
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
