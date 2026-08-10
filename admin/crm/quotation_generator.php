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

$pageTitle = $isArchivedView
    ? ('Quotation v' . $viewVersion)
    : ($quotation
        ? (crmQuotationIsDraft($quotation)
            ? 'Continue Draft Quotation'
            : ('Edit Quotation' . ($currentQuotationVersion > 1 ? ' v' . $currentQuotationVersion : '')))
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
    ['id' => 1, 'label' => 'Guest & Tour', 'color' => '#16a34a', 'icon' => 'fas fa-user'],
    ['id' => 2, 'label' => 'Flight / Train', 'color' => '#2563eb', 'icon' => 'fas fa-plane'],
    ['id' => 3, 'label' => 'Hotel Details', 'color' => '#7c3aed', 'icon' => 'fas fa-hotel'],
    ['id' => 4, 'label' => 'Itinerary', 'color' => '#0d9488', 'icon' => 'fas fa-map-marker-alt'],
    ['id' => 5, 'label' => 'Terms & Policies', 'color' => '#4f46e5', 'icon' => 'fas fa-shield-alt'],
    ['id' => 6, 'label' => 'Pricing', 'color' => '#16a34a', 'icon' => 'fas fa-rupee-sign'],
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

        .crm-quotation-gen .q-version-bar {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            background: #fff;
            border: 1px solid var(--q-border);
            border-radius: 999px;
            padding: 0.2rem 0.55rem 0.2rem 0.65rem;
        }

        .crm-quotation-gen .q-version-label {
            margin: 0;
            font-size: 0.72rem;
            font-weight: 700;
            color: var(--q-text-muted);
            text-transform: uppercase;
            letter-spacing: 0.04em;
            white-space: nowrap;
        }

        .crm-quotation-gen .q-version-select {
            min-width: 168px;
            height: 30px;
            border: 0;
            box-shadow: none;
            background: transparent;
            font-size: 0.82rem;
            font-weight: 600;
            color: var(--q-text);
            padding: 0 1.4rem 0 0.15rem;
        }

        .crm-quotation-gen .q-version-select:focus {
            box-shadow: none;
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
            background: #fff;
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
            background: #f8fafc;
            border-bottom: 1px solid var(--q-border-light);
            transition: background 0.15s ease;
        }

        .crm-quotation-gen .q-accordion-head:hover {
            background: #f1f5f9;
        }

        .crm-quotation-gen .q-card-accordions .q-accordion-item {
            border: 1px solid var(--q-border);
            border-radius: var(--q-radius-sm);
            margin-bottom: 0.65rem;
            overflow: hidden;
            background: #fff;
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

        .crm-quotation-gen .q-wizard-step[data-q-step="5"] .q-accordion-item {
            border: 1px solid var(--q-border);
            border-radius: var(--q-radius-sm);
            margin-bottom: 0.75rem;
            overflow: hidden;
            background: #fff;
            box-shadow: var(--q-shadow-sm);
        }

        .crm-quotation-gen .q-wizard-step[data-q-step="5"] .q-accordion-head {
            background: linear-gradient(180deg, #f8fafc 0%, #fff 100%);
            border-bottom: 1px solid var(--q-border-light);
        }

        .crm-quotation-gen .q-wizard-step[data-q-step="5"] .note-editor.note-frame {
            border: 1px solid var(--q-border);
            border-radius: var(--q-radius-sm);
            overflow: hidden;
            box-shadow: var(--q-shadow-sm);
        }

        .crm-quotation-gen .q-wizard-step[data-q-step="5"] .note-toolbar {
            background: #f8fafc !important;
            border-bottom: 1px solid var(--q-border-light) !important;
        }

        .crm-quotation-gen .q-day-card {
            border: 1px solid var(--q-border);
            border-radius: var(--q-radius-sm);
            margin-bottom: 0.75rem;
            overflow: hidden;
            box-shadow: var(--q-shadow-sm);
        }

        .crm-quotation-gen .q-day-head {
            background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
            padding: 0.55rem 0.85rem;
            font-weight: 700;
            font-size: 0.82rem;
            color: var(--q-text);
            border-bottom: 1px solid var(--q-border);
        }

        .crm-quotation-gen .q-day-body {
            padding: 0.5rem 0.65rem;
        }

        .crm-quotation-gen .q-day-body .note-editor {
            max-width: 100%;
        }

        .crm-quotation-gen .q-day-body .note-toolbar {
            background: #f8fafc;
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
            background: #fafbfc;
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
            background: linear-gradient(180deg, #f8fafc 0%, #fff 100%);
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
            background: #fff;
            box-shadow: var(--q-shadow-sm);
            transition: box-shadow 0.15s ease, border-color 0.15s ease;
        }

        .crm-quotation-gen .q-repeat-row:hover {
            box-shadow: var(--q-shadow-md);
            border-color: #cbd5e1;
        }

        .crm-quotation-gen .q-wizard-step[data-q-step="2"] .q-flight-card {
            padding: 0.45rem 0.2rem 0.85rem;
        }

        .crm-quotation-gen .q-flight-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
            margin-bottom: 1rem;
            padding-bottom: 0.85rem;
            border-bottom: 1px solid #eef2f7;
        }

        .crm-quotation-gen .q-flight-head .q-section-title {
            margin: 0;
            padding: 0;
            border: 0;
            font-size: 1.05rem;
            color: #1e3a5f;
        }

        .crm-quotation-gen .q-flight-subtitle {
            margin: 0.3rem 0 0 0.85rem;
            font-size: 0.82rem;
            color: #64748b;
            font-weight: 500;
        }

        .crm-quotation-gen .q-flight-actions {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.4rem;
        }

        .crm-quotation-gen .q-flight-btn {
            border-radius: 999px;
            font-weight: 700;
            font-size: 0.78rem;
            padding: 0.4rem 0.85rem;
            white-space: nowrap;
        }

        .crm-quotation-gen .q-flight-btn-blue {
            background: #2563eb;
            border-color: #2563eb;
            color: #fff;
        }

        .crm-quotation-gen .q-flight-btn-blue:hover {
            background: #1d4ed8;
            border-color: #1d4ed8;
            color: #fff;
        }

        .crm-quotation-gen .q-flight-btn-green {
            background: #16a34a;
            border-color: #16a34a;
            color: #fff;
        }

        .crm-quotation-gen .q-flight-btn-green:hover {
            background: #15803d;
            border-color: #15803d;
            color: #fff;
        }

        .crm-quotation-gen .q-flight-btn-orange {
            background: #ea580c;
            border-color: #ea580c;
            color: #fff;
        }

        .crm-quotation-gen .q-flight-btn-orange:hover {
            background: #c2410c;
            border-color: #c2410c;
            color: #fff;
        }

        .crm-quotation-gen .q-flight-btn-outline {
            background: #fff;
            border: 1px solid #cbd5e1;
            color: #475569;
            cursor: pointer;
        }

        .crm-quotation-gen .q-flight-btn-outline:hover {
            background: #f8fafc;
            color: #1e293b;
        }

        .crm-quotation-gen .q-flight-table-wrap {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            overflow-x: auto;
            background: #fff;
        }

        .crm-quotation-gen .q-flight-table-head,
        .crm-quotation-gen .q-flight-segment-row {
            display: grid;
            grid-template-columns:
                42px minmax(110px, 1.1fr) 34px minmax(110px, 1.1fr)
                minmax(120px, 1.2fr) minmax(100px, 0.9fr)
                minmax(108px, 0.9fr) minmax(88px, 0.7fr)
                minmax(108px, 0.9fr) minmax(88px, 0.7fr)
                minmax(90px, 0.8fr) minmax(110px, 0.9fr) 44px;
            gap: 0.35rem;
            align-items: center;
            min-width: 1180px;
        }

        .crm-quotation-gen .q-flight-table-head {
            padding: 0.55rem 0.65rem;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
        }

        .crm-quotation-gen .q-flight-table-head .q-ft-col {
            font-size: 0.68rem;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            white-space: nowrap;
        }

        .crm-quotation-gen .q-flight-row {
            border-bottom: 1px solid #eef2f7;
        }

        .crm-quotation-gen .q-flight-row:last-child {
            border-bottom: 0;
        }

        .crm-quotation-gen .q-flight-segment-row {
            padding: 0.55rem 0.65rem;
        }

        .crm-quotation-gen .q-flight-index {
            width: 26px;
            height: 26px;
            border-radius: 7px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.72rem;
            color: #fff;
        }

        .crm-quotation-gen .q-flight-index.is-odd {
            background: #2563eb;
        }

        .crm-quotation-gen .q-flight-index.is-even {
            background: #16a34a;
        }

        .crm-quotation-gen .q-flight-place,
        .crm-quotation-gen .q-flight-airline,
        .crm-quotation-gen .q-flight-icon-field,
        .crm-quotation-gen .q-flight-fare {
            display: flex;
            align-items: center;
            gap: 0.3rem;
            min-width: 0;
        }

        .crm-quotation-gen .q-flight-place-icon,
        .crm-quotation-gen .q-flight-icon-field > i,
        .crm-quotation-gen .q-flight-fare > span {
            color: #94a3b8;
            font-size: 0.72rem;
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

        .crm-quotation-gen .q-flight-segment-row .form-control {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.78rem;
            height: calc(1.5em + 0.55rem + 2px);
            padding: 0.2rem 0.4rem;
            min-width: 0;
        }

        .crm-quotation-gen .q-flight-segment-row .form-control:focus {
            border-color: #93c5fd;
            box-shadow: 0 0 0 2px rgba(147, 197, 253, 0.2);
        }

        .crm-quotation-gen .q-flight-swap {
            width: 28px;
            height: 28px;
            border: 1px solid #e2e8f0;
            border-radius: 50%;
            background: #fff;
            color: #64748b;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
        }

        .crm-quotation-gen .q-flight-swap:hover {
            background: #eff6ff;
            color: #2563eb;
            border-color: #bfdbfe;
        }

        .crm-quotation-gen .q-flight-remove {
            width: 30px;
            height: 30px;
            border: 1px solid #fecaca;
            border-radius: 8px;
            background: #fff;
            color: #dc2626;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .crm-quotation-gen .q-flight-remove:hover {
            background: #fef2f2;
        }

        .crm-quotation-gen .q-flight-layover {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            margin: 0;
            padding: 0.5rem 0.85rem;
            background: #eff6ff;
            border-top: 1px solid #dbeafe;
            border-bottom: 1px solid #dbeafe;
            color: #1d4ed8;
            font-size: 0.8rem;
            font-weight: 700;
        }

        .crm-quotation-gen .q-flight-layover-text {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            min-width: 0;
        }

        .crm-quotation-gen .q-flight-layover-art {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            color: #60a5fa;
            flex-shrink: 0;
        }

        .crm-quotation-gen .q-flight-layover-art .fa-map-marker-alt {
            color: #16a34a;
        }

        .crm-quotation-gen .q-flight-layover-path {
            width: 48px;
            height: 0;
            border-top: 2px dotted #93c5fd;
        }

        .crm-quotation-gen .q-flight-add-wrap {
            display: flex;
            justify-content: center;
            margin-top: 0.85rem;
        }

        .crm-quotation-gen .q-flight-add-segment {
            border: 1px solid #bfdbfe;
            background: #fff;
            color: #2563eb;
            border-radius: 999px;
            font-weight: 700;
            padding: 0.4rem 1rem;
        }

        .crm-quotation-gen .q-flight-add-segment:hover {
            background: #eff6ff;
            color: #1d4ed8;
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
            border: 1px solid #c7d2fe;
            background: #fff;
            color: #4338ca;
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

        .qfs-flight-search .qfs-airport-suggest {
            position: absolute;
            z-index: 1060;
            left: 0;
            right: 0;
            background: #fff;
            border: 1px solid #ccc;
            border-radius: var(--q-radius);
            max-height: 250px;
            overflow-y: auto;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .qfs-flight-search .qfs-suggest-item:hover {
            background: #f1f5f9;
        }

        .qfs-flight-search .qfs-search-btn {
            background-color: var(--q-primary);
            border-color: var(--q-primary);
        }

        .qfs-flight-search .qfs-select-flight-card:hover {
            background-color: #eff6ff !important;
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
            flex: 0 0 290px;
            width: 290px;
            max-width: 100%;
            position: sticky;
            top: 0.75rem;
            align-self: flex-start;
            max-height: calc(100vh - 5.5rem);
            overflow-y: auto;
            padding-right: 0.15rem;
        }

        .crm-quotation-gen .q-main-panel {
            flex: 1 1 auto;
            min-width: 0;
        }

        .crm-quotation-gen .q-side-card {
            background: #fff;
            border: 1px solid var(--q-border);
            border-radius: 8px;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
            padding: 0.85rem 0.9rem;
            margin-bottom: 0.65rem;
        }

        .crm-quotation-gen .q-side-card-title {
            font-size: 0.92rem;
            font-weight: 700;
            color: #1e3a5f;
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
                position: static;
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
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.22);
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
            display: inline-block;
        }

        .crm-quotation-gen .q-stepper-item.is-complete .q-stepper-dot .q-stepper-check {
            display: inline-block;
        }

        .crm-quotation-gen .q-stepper-item.is-complete .q-stepper-dot .q-stepper-icon {
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
            background: var(--step-color, #2563eb);
            border-color: var(--step-color, #2563eb);
            color: #fff;
        }

        .crm-quotation-gen .q-stepper-item.is-complete .q-stepper-label {
            color: var(--step-color, #2563eb);
        }

        .crm-quotation-gen .q-stepper-item.is-active .q-stepper-dot {
            background: var(--step-color, #0d9488);
            border-color: var(--step-color, #0d9488);
            color: #fff;
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.12);
        }

        .crm-quotation-gen .q-stepper-item.is-active .q-stepper-label {
            color: var(--step-color, #0d9488);
            font-weight: 700;
        }

        .crm-quotation-gen .q-stepper-item.is-active .q-stepper-dot .q-stepper-check {
            display: none;
        }

        .crm-quotation-gen .q-stepper-item.is-active .q-stepper-dot .q-stepper-icon {
            display: inline-block;
        }

        .crm-quotation-gen .q-stepper-item.is-complete.is-active .q-stepper-dot .q-stepper-check {
            display: inline-block;
        }

        .crm-quotation-gen .q-stepper-item.is-complete.is-active .q-stepper-dot .q-stepper-icon {
            display: none;
        }

        .crm-quotation-gen .q-stepper-item.is-locked {
            cursor: not-allowed;
            opacity: 0.85;
        }

        .crm-quotation-gen .q-wizard-step {
            display: none;
        }

        .crm-quotation-gen .q-wizard-step.is-active {
            display: block;
            animation: qWizardFadeIn 0.22s ease;
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

        .crm-quotation-gen .q-wizard-step[data-q-step="1"] .q-guest-tour-card {
            padding: 0.35rem 0.15rem 0.85rem;
        }

        .crm-quotation-gen .q-guest-tour-head {
            margin-bottom: 1rem;
            padding-bottom: 0.85rem;
            border-bottom: 1px solid #eef2f7;
        }

        .crm-quotation-gen .q-guest-tour-head .q-section-title {
            margin: 0;
            padding: 0;
            border: 0;
            font-size: 1.05rem;
            color: #1e3a5f;
        }

        .crm-quotation-gen .q-guest-tour-subtitle {
            margin: 0.35rem 0 0 0.85rem;
            font-size: 0.82rem;
            color: #64748b;
            font-weight: 500;
        }

        .crm-quotation-gen .q-guest-tour-section {
            margin-bottom: 0.35rem;
        }

        .crm-quotation-gen .q-guest-tour-divider {
            height: 1px;
            background: #eef2f7;
            margin: 1rem 0 1rem;
        }

        .crm-quotation-gen .q-wizard-step[data-q-step="1"] .q-subsection-label {
            text-transform: none;
            letter-spacing: 0.01em;
            font-size: 0.78rem;
            font-weight: 700;
            padding: 0.38rem 0.8rem;
            margin-bottom: 0.85rem;
        }

        .crm-quotation-gen .q-subsection-guest {
            color: #1d4ed8;
            background: #eff6ff;
            border-color: #bfdbfe;
        }

        .crm-quotation-gen .q-subsection-guest i {
            color: #2563eb;
        }

        .crm-quotation-gen .q-subsection-tour {
            color: #047857;
            background: #ecfdf5;
            border-color: #a7f3d0;
        }

        .crm-quotation-gen .q-subsection-tour i {
            color: #059669;
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

        .crm-quotation-gen .q-wizard-step[data-q-step="1"] .form-control {
            border-radius: 10px;
            border-color: #e2e8f0;
            min-height: calc(2.35rem + 2px);
            font-size: 0.88rem;
        }

        .crm-quotation-gen .q-wizard-step[data-q-step="1"] .form-control:focus {
            border-color: #93c5fd;
            box-shadow: 0 0 0 3px rgba(147, 197, 253, 0.2);
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
            background: #eff6ff;
            color: #1d4ed8;
            outline: none;
        }

        .crm-quotation-gen .q-dest-item:hover i,
        .crm-quotation-gen .q-dest-item.is-active i {
            color: #2563eb;
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
            border: 1px solid #fecaca;
            border-radius: 16px;
            background: #fff;
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
            background: #f8fafc;
        }

        .crm-quotation-gen .q-pricing-sidebar {
            --q-side-gap: 0.65rem;
            --q-side-pad: 0.75rem;
            flex: 0 0 340px;
            width: 340px;
            max-width: 100%;
            border-left: 1px solid #fee2e2;
            background: #fff7f7;
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
            background: #fff;
            border: 1px solid #fecaca;
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
            color: #111827;
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
            background: #fff1f2;
            border: 1px solid #fecdd3;
            color: #9f1239;
            font-weight: 800;
            font-size: 0.92rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
            min-height: 40px;
        }

        .crm-quotation-gen .q-usd-result.is-empty {
            color: #94a3b8;
            font-weight: 600;
            font-size: 0.78rem;
        }

        .crm-quotation-gen .q-usd-box-side .q-hint {
            display: none;
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
            border: 1px solid #e2e8f0;
            background: #fff;
            border-radius: 8px;
            height: 34px;
            font-size: 0.82rem;
            font-weight: 700;
            color: #334155;
            cursor: pointer;
            line-height: 1;
            padding: 0;
            min-width: 0;
            user-select: none;
            -webkit-tap-highlight-color: transparent;
            transition: background 0.1s ease, border-color 0.1s ease, transform 0.08s ease, box-shadow 0.1s ease;
        }

        .crm-quotation-gen .q-calc-keys button:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
        }

        .crm-quotation-gen .q-calc-keys button:active,
        .crm-quotation-gen .q-calc-keys button.is-pressed {
            transform: scale(0.96);
            background: #e2e8f0;
        }

        .crm-quotation-gen .q-calc-keys button.q-calc-digit {
            background: #fff;
            color: #0f172a;
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
            border: 1px solid #fecaca;
            border-radius: 14px;
            background: #fff;
            box-shadow: 0 6px 18px rgba(185, 28, 28, 0.06);
            overflow: hidden;
            width: auto;
            max-width: none;
            min-width: 168px;
        }

        .crm-quotation-gen .q-pricing-option-sheet:last-child {
            border-right: 1px solid #fecaca;
        }

        .crm-quotation-gen .q-pricing-option-sheet.is-active {
            background: #fff;
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
            border: 1px solid #fecaca;
            border-radius: 12px;
            background: #fff;
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
            color: #0f172a;
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
            background: #fff1f2;
            border-radius: 0 0 11px 11px;
            border-top: 1px solid #fecdd3;
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
            color: #0f172a;
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
            background: #fff1f2;
            border-radius: 8px;
            color: #9f1239;
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
            background: #fff1f2;
            border-radius: 8px;
            padding-left: 0.35rem;
            padding-right: 0.35rem;
            min-height: 38px !important;
            height: 38px !important;
        }

        .crm-quotation-gen .q-pricing-ppa-label {
            color: #9f1239;
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
            border-color: #e5e7eb !important;
            background: #f8fafc !important;
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
            background: #fff !important;
            border-color: #fecaca !important;
            color: #9f1239 !important;
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
            border-color: #e5e7eb !important;
            background: #f8fafc !important;
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
            border-radius: 999px;
            padding: 0.45rem 1.1rem;
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
            color: #475569;
            background: #fff;
        }

        .crm-quotation-gen .q-wizard-nav #qSaveDraftBtn:hover {
            background: #f8fafc;
            border-color: #94a3b8;
            color: #334155;
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
            background: linear-gradient(180deg, var(--q-primary) 0%, var(--q-primary-dark) 100%);
            border-color: var(--q-primary-dark);
            box-shadow: 0 2px 10px rgba(37, 99, 235, 0.25);
        }

        .crm-quotation-gen .q-wizard-nav #qWizardNext:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 14px rgba(37, 99, 235, 0.3);
        }

        .crm-quotation-gen .q-wizard-step-indicator {
            font-size: 0.8rem;
            color: var(--q-text-muted);
            font-weight: 700;
            padding: 0.35rem 0.85rem;
            background: #fff;
            border: 1px solid var(--q-border);
            border-radius: 999px;
        }

        .crm-quotation-gen #qAlert .alert {
            border-radius: var(--q-radius-sm);
            border: none;
            box-shadow: var(--q-shadow-sm);
            font-size: 0.84rem;
        }

        .crm-quotation-gen .q-wizard-step[data-q-step="4"] .q-accordion-head {
            display: none;
        }

        .crm-quotation-gen .q-wizard-step[data-q-step="4"] #qItineraryBody {
            display: block !important;
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
                                <div class="q-version-bar">
                                    <label for="qVersionSelect" class="q-version-label">Version</label>
                                    <select id="qVersionSelect" class="custom-select form-control-sm q-version-select" aria-label="Quotation version">
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

                    <div class="q-page-layout">
                        <?php if ($leadSidebar) { ?>
                            <aside class="q-lead-sidebar" aria-label="Lead details">
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

                        <div class="q-wizard" id="qWizard">
                            <div class="q-stepper" id="qStepper" role="tablist" aria-label="Quotation steps">
                                <?php foreach ($qWizardSteps as $stepItem) { ?>
                                    <button type="button"
                                        class="q-stepper-item<?= (int) $stepItem['id'] === 1 ? ' is-active' : '' ?><?= (int) $stepItem['id'] > 1 ? ' is-locked' : '' ?>"
                                        data-q-step="<?= (int) $stepItem['id'] ?>"
                                        style="--step-color: <?= htmlspecialchars((string) $stepItem['color'], ENT_QUOTES, 'UTF-8') ?>"
                                        aria-current="<?= (int) $stepItem['id'] === 1 ? 'step' : 'false' ?>">
                                        <span class="q-stepper-connector" aria-hidden="true"></span>
                                        <span class="q-stepper-dot">
                                            <i class="fas fa-check q-stepper-check"></i>
                                            <i class="<?= htmlspecialchars((string) ($stepItem['icon'] ?? 'fas fa-circle'), ENT_QUOTES, 'UTF-8') ?> q-stepper-icon"></i>
                                        </span>
                                        <span class="q-stepper-label"><?= htmlspecialchars((string) $stepItem['label'], ENT_QUOTES, 'UTF-8') ?></span>
                                    </button>
                                <?php } ?>
                            </div>

                            <div class="q-wizard-panels">
                        <!-- Guest & Tour -->
                        <div class="q-wizard-step is-active" data-q-step="1">
                        <div class="q-card q-guest-tour-card">
                            <div class="q-guest-tour-head">
                                <h3 class="q-section-title">Guest &amp; Tour Details</h3>
                                <p class="q-guest-tour-subtitle">Enter guest and tour information to continue</p>
                            </div>

                            <div class="q-guest-tour-section">
                                <div class="q-subsection-label q-subsection-guest">
                                    <i class="fas fa-user"></i> Guest Information
                                </div>
                                <div class="row q-row-tight">
                                    <div class="col-md-3 col-lg-3 form-group">
                                        <label class="q-label label-req">Guest Name</label>
                                        <div class="q-field-icon-wrap q-lead-combobox">
                                            <input type="text" name="guest_name" class="form-control js-q-lead-lookup" required autocomplete="off" placeholder="Enter guest name">
                                            <i class="fas fa-user q-field-icon"></i>
                                            <div class="q-lead-menu js-q-lead-menu" style="display:none;"></div>
                                        </div>
                                    </div>
                                    <div class="col-md-3 col-lg-3 form-group">
                                        <label class="q-label">Reference Name</label>
                                        <div class="q-field-icon-wrap">
                                            <input type="text" name="reference_name" class="form-control" autocomplete="off" placeholder="Enter reference name">
                                            <i class="fas fa-user q-field-icon"></i>
                                        </div>
                                    </div>
                                    <div class="col-md-3 col-lg-3 form-group">
                                        <label class="q-label label-req">Mobile No</label>
                                        <div class="q-field-icon-wrap q-lead-combobox">
                                            <input type="text" name="mobile_no" class="form-control js-q-lead-lookup" autocomplete="off" placeholder="Enter mobile number">
                                            <i class="fas fa-phone-alt q-field-icon"></i>
                                            <div class="q-lead-menu js-q-lead-menu" style="display:none;"></div>
                                        </div>
                                    </div>
                                    <div class="col-md-3 col-lg-3 form-group">
                                        <label class="q-label label-req">Email</label>
                                        <div class="q-field-icon-wrap q-lead-combobox">
                                            <input type="email" name="email" class="form-control js-q-lead-lookup" autocomplete="off" placeholder="Enter email address">
                                            <i class="far fa-envelope q-field-icon"></i>
                                            <div class="q-lead-menu js-q-lead-menu" style="display:none;"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="q-guest-tour-divider"></div>

                            <div class="q-guest-tour-section">
                                <div class="q-subsection-label q-subsection-tour">
                                    <i class="fas fa-suitcase"></i> Tour Information
                                </div>
                                <div class="row q-row-tight q-tour-fields">
                                    <div class="col-12 col-md col-lg q-tour-dest-col form-group">
                                        <label class="q-label label-req">Destination</label>
                                        <div class="q-dest-picker q-field-icon-wrap" id="qDestPicker">
                                            <input type="text" name="destination" id="qDestinationInput" class="form-control js-q-dest-input"
                                                   placeholder="Search or select destination" required autocomplete="off" role="combobox"
                                                   aria-autocomplete="list" aria-expanded="false" aria-controls="qDestinationMenu">
                                            <button type="button" class="q-dest-picker-toggle js-q-dest-toggle" tabindex="-1" aria-label="Show destinations">
                                                <i class="fas fa-chevron-down"></i>
                                            </button>
                                            <i class="fas fa-map-marker-alt q-field-icon"></i>
                                            <div class="q-dest-menu js-q-dest-menu" id="qDestinationMenu" role="listbox" style="display:none;"></div>
                                        </div>
                                    </div>
                                    <div class="col-auto q-tour-date-col form-group">
                                        <label class="q-label label-req">Tentative Date</label>
                                        <div class="q-field-icon-wrap">
                                            <input type="date" name="tentative_date" id="q_tentative_date" class="form-control" required>
                                            <i class="far fa-calendar-alt q-field-icon"></i>
                                        </div>
                                    </div>
                                    <div class="col-auto q-tour-pax-col form-group">
                                        <label class="q-label label-req">No. of Nights</label>
                                        <input type="number" min="0" name="no_of_nights" id="q_nights" class="form-control" value="0" required>
                                    </div>
                                    <div class="col-auto q-tour-pax-col form-group">
                                        <label class="q-label label-req">No. of Adults</label>
                                        <input type="number" min="1" name="no_of_adults" id="q_adults" class="form-control" value="1" required>
                                    </div>
                                    <div class="col-auto q-tour-pax-col form-group">
                                        <label class="q-label">No. of Children</label>
                                        <input type="number" min="0" name="no_of_children" id="q_children" class="form-control" value="0">
                                    </div>
                                </div>
                            </div>
                        </div>
                        </div>

                        <!-- Flight / Train Details -->
                        <div class="q-wizard-step" data-q-step="2">
                        <div class="q-card q-flight-card">
                            <div class="q-flight-head">
                                <div class="q-flight-head-text">
                                    <h3 class="q-section-title">Flight / Train Details</h3>
                                    <p class="q-flight-subtitle">Manage all flight and train segments for the traveller.</p>
                                </div>
                                <div class="q-flight-actions">
                                    <button type="button" class="btn btn-sm q-flight-btn q-flight-btn-blue" id="qSearchFlight">
                                        <i class="fas fa-plane mr-1"></i>Search Flight
                                    </button>
                                    <button type="button" class="btn btn-sm q-flight-btn q-flight-btn-green" id="qSearchTrain">
                                        <i class="fas fa-train mr-1"></i>Search Train
                                    </button>
                                    <button type="button" class="btn btn-sm q-flight-btn q-flight-btn-orange" id="qAddFlight">
                                        <i class="fas fa-plus mr-1"></i>Add Flight / Train
                                    </button>
                                    <label class="btn btn-sm q-flight-btn q-flight-btn-outline mb-0" id="qUploadSsBtn" for="qUploadSsInput">
                                        <i class="fas fa-upload mr-1"></i>Upload SS
                                    </label>
                                    <input type="file" id="qUploadSsInput" class="d-none" accept="image/*,.pdf">
                                </div>
                            </div>

                            <div class="q-flight-table-wrap">
                                <div class="q-flight-table-head">
                                    <span class="q-ft-col q-ft-col-num">#</span>
                                    <span class="q-ft-col q-ft-col-from">From</span>
                                    <span class="q-ft-col q-ft-col-swap"></span>
                                    <span class="q-ft-col q-ft-col-to">To</span>
                                    <span class="q-ft-col q-ft-col-airline">Airline / Train</span>
                                    <span class="q-ft-col q-ft-col-no">Flight / Train No.</span>
                                    <span class="q-ft-col q-ft-col-date">Dep. Date</span>
                                    <span class="q-ft-col q-ft-col-time">Dep. Time</span>
                                    <span class="q-ft-col q-ft-col-date">Arr. Date</span>
                                    <span class="q-ft-col q-ft-col-time">Arr. Time</span>
                                    <span class="q-ft-col q-ft-col-fare">Fare (INR)</span>
                                    <span class="q-ft-col q-ft-col-supplier">Supplier</span>
                                    <span class="q-ft-col q-ft-col-action">Action</span>
                                </div>
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

                        <!-- Hotel Details -->
                        <div class="q-wizard-step" data-q-step="3">
                        <div class="q-card">
                            <h3 class="q-section-title">Hotel Details</h3>
                                    <p class="q-hint mb-2">Create up to 2 hotel options so the guest can compare different pricing.</p>
                            <div class="q-hotel-cat-toolbar">
                                <div class="q-hotel-cat-tabs" id="qHotelCatTabs" role="tablist"></div>
                                <button type="button" class="btn btn-outline-primary btn-sm" id="qAddHotelCategory" title="Add pricing option">
                                    <i class="fas fa-plus mr-1"></i>Add Option
                                </button>
                            </div>
                            <div id="qHotelCategories"></div>
                            <p class="q-hint mt-2 mb-0">Hotel suggestions are filtered by Tour Information destination. Type a city to search City Master, or use <strong>Create new city</strong> if it is missing. For hotels, use <strong>Create new hotel</strong> to add to Hotel Master immediately.</p>
                        </div>
                        </div>

                        <!-- Itinerary -->
                        <div class="q-wizard-step" data-q-step="4">
                        <div class="q-card">
                            <h3 class="q-section-title">Itinerary</h3>
                            <div class="q-accordion-body" id="qItineraryBody" style="display:none;">
                                <div class="q-ai-suggest">
                                    <div class="q-ai-suggest-hd">
                                        <div class="q-ai-suggest-icon"><i class="fas fa-magic"></i></div>
                                        <div>
                                            <div class="q-ai-suggest-title">Suggest Itinerary</div>
                                            <p class="q-ai-suggest-desc">Instant day-wise plan based on your destination — no wait, works offline.</p>
                                        </div>
                                    </div>
                                    <div class="q-ai-suggest-meta" id="qAiItineraryMeta"></div>
                                    <div class="form-group mb-2">
                                        <label class="q-label small mb-1">Preferences <span class="text-muted font-weight-normal">(optional)</span></label>
                                        <textarea class="form-control q-ai-suggest-notes" id="qAiItineraryNotes" rows="2" placeholder="e.g. family trip with kids, focus on temples &amp; local food, relaxed pace..."></textarea>
                                    </div>
                                    <button type="button" class="btn btn-sm" id="qSuggestAIItinerary">
                                        <i class="fas fa-bolt mr-1"></i> Generate Itinerary Now
                                    </button>
                                    <div class="q-ai-itin-preview" id="qAiItineraryPreview" aria-live="polite">
                                        <div class="q-ai-itin-preview-hd">
                                            <div>
                                                <p class="q-ai-itin-preview-title" id="qAiItineraryPreviewTitle">Suggested itinerary</p>
                                                <p class="q-ai-itin-preview-sub" id="qAiItineraryPreviewSub"></p>
                                            </div>
                                            <span class="q-ai-itin-badge" id="qAiItineraryBadge"></span>
                                        </div>
                                        <ul class="q-ai-itin-preview-days" id="qAiItineraryPreviewDays"></ul>
                                        <div class="q-ai-itin-preview-actions">
                                            <button type="button" class="btn btn-sm" id="qApplyAIItinerary">
                                                <i class="fas fa-check mr-1"></i> Apply This Itinerary
                                            </button>
                                            <button type="button" class="btn btn-link btn-sm text-muted px-1" id="qDismissAIItinerary">
                                                Dismiss
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div class="q-suggest-divider">or load from package</div>

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
                                <p class="q-hint">Days are generated from the Tentative Date and No of Nights.</p>
                                <div id="qItineraryDays"></div>
                            </div>
                        </div>
                        </div>

                        <!-- Rich-text accordions -->
                        <div class="q-wizard-step" data-q-step="5">
                        <div class="q-card q-card-accordions">
                            <div class="d-flex justify-content-between align-items-center flex-wrap mb-2 gap-2">
                                <h3 class="q-section-title mb-0">Terms &amp; Policies</h3>
                                <div class="q-terms-actions">
                                    <button type="button" class="btn btn-outline-secondary btn-sm" id="qLoadTermsMasterBtn">
                                        <i class="fas fa-download mr-1"></i> Load from Master
                                    </button>
                                    <a href="crm/quotation_terms_master.php" class="btn btn-outline-primary btn-sm" target="_blank" rel="noopener">
                                        <i class="fas fa-cog mr-1"></i> Manage Master
                                    </a>
                                </div>
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

                        <!-- Pricing -->
                        <div class="q-wizard-step" data-q-step="6">
                            <div class="q-card q-pricing-card">
                            <h3 class="q-section-title d-none">Pricing</h3>
                            <p class="q-hint mb-2 d-none">Compare hotel options side by side. Flight amounts sync from fares; hotel amounts sync from each option’s rates.</p>

                            <div class="q-pricing-compare">
                                <div class="q-pricing-compare-hd">
                                    <span class="q-pricing-compare-hd-icon" aria-hidden="true"><i class="fas fa-suitcase-rolling"></i></span>
                                    <div class="q-pricing-compare-hd-text">
                                        <h4>Cost Sheet &amp; Quotation</h4>
                                        <p class="q-hint">Compare travel options and pricing</p>
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
                                                    <button type="button" class="btn btn-link btn-sm p-0" id="qUsdCopyResult" title="Copy result" style="display:none;color:#9f1239;"><i class="far fa-copy"></i></button>
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

                            <!-- Legacy fields kept in sync with the active hotel option for save/preview -->
                            <input type="hidden" name="price_per_adult" id="q_price_per_adult" value="">
                            <input type="hidden" id="q_quotation_total" value="0">
                            <input type="hidden" id="q_total_cost" value="0">
                            <input type="hidden" id="q_package_total" value="0">
                            <input type="hidden" id="q_profit_percent" value="">
                            <input type="hidden" id="q_profit_amount" value="">

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

                            <div class="q-wizard-nav">
                                <div class="q-wizard-nav-left">
                                    <button type="button" class="btn btn-outline-secondary btn-sm" id="qWizardPrev" style="visibility:hidden;">
                                        <i class="fas fa-arrow-left mr-1"></i> Previous
                                    </button>
                                    <?php if ($showSaveDraft) { ?>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" id="qSaveDraftBtn" title="Save progress and continue later">
                                            <i class="fas fa-bookmark mr-1"></i> Save Draft
                                        </button>
                                    <?php } ?>
                                </div>
                                <span class="q-wizard-step-indicator" id="qWizardStepIndicator">Step 1 of 6</span>
                                <button type="button" class="btn btn-q-primary btn-sm" id="qWizardNext">
                                    Next <i class="fas fa-arrow-right ml-1"></i>
                                </button>
                            </div>
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
        var Q_DESTINATION_NAME_TO_ID = <?= json_encode($qDestinationNameToId, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '{}' ?>;
        var Q_DESTINATION_COUNTRY_ID_BY_NAME = <?= json_encode($qDestinationCountryIdByName, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '{}' ?>;
        var QUOTATION_TERMS_MASTER = <?= json_encode($quotationTermsMaster, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '{}' ?>;
        var Q_SUPPLIER_MAIL_TEMPLATE = <?= json_encode([
            'subject' => $qMailSubject,
            'body_html' => $qMailBodyHtml,
            'meta' => $qMailMeta,
        ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '{}' ?>;
    </script>
    <script src="crm/assets/quotation_generator.js?v=81"></script>
    <script src="crm/assets/quotation_flight_search.js?v=6"></script>
    <script src="crm/assets/quotation_itinerary_images.js?v=1"></script>
    <script src="crm/assets/quotation_supplier_mail.js?v=17"></script>
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
