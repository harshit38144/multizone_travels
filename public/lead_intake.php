<?php
date_default_timezone_set('Asia/Kolkata');

require_once __DIR__ . '/../admin/config/database.php';
require_once __DIR__ . '/../admin/crm/includes/lead_intake_db.php';
require_once __DIR__ . '/../admin/crm/includes/lead_intake_fields.php';

crmEnsureLeadIntakeTables($conn);

$token = trim($_GET['token'] ?? '');
$error = '';
$success = '';
$request = null;
$enabledFields = [];

if ($token === '') {
    $error = 'Invalid or missing link.';
} else {
    $stmt = $conn->prepare("SELECT * FROM `crm_lead_intake_requests` WHERE `token` = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $res = $stmt->get_result();
        $request = $res ? $res->fetch_assoc() : null;
        $stmt->close();
    }
    if (!$request) {
        $error = 'This link is invalid or has expired.';
    } elseif (in_array((string) ($request['status'] ?? ''), ['approved', 'rejected', 'cancelled'], true)) {
        $error = 'This link is no longer active.';
    } elseif ((string) ($request['status'] ?? '') === 'submitted') {
        if (function_exists('crmBuildIntakeThanksUrl')) {
            header('Location: ' . crmBuildIntakeThanksUrl($token));
        } else {
            header('Location: lead_intake_thanks.php?token=' . rawurlencode($token));
        }
        exit;
    } else {
        $decoded = json_decode((string) ($request['field_config'] ?? '[]'), true);
        $enabledFields = is_array($decoded) ? crmLeadIntakeNormalizeFields($decoded) : [];
    }
}

$companyName = 'Multizone Travels';
$companyTagline = 'travel for memories...';
$logoUrl = crmResolveIntakeLogoUrl('img/web-logo.png');
$logoFallbackUrl = function_exists('crmIntakeFallbackLogoUrl') ? crmIntakeFallbackLogoUrl() : $logoUrl;
$noteToCustomer = '';
$intakeFormSubtitle = 'Share your travel plans and we\'ll craft the perfect experience for you.';
$adminAssetBase = crmPublicAdminAssetBase();

$ssTable = $conn->query("SHOW TABLES LIKE 'site_settings'");
if ($ssTable && $ssTable->num_rows > 0) {
    $ssRes = $conn->query("SELECT `site_title`, `site_tagline`, `logo_path` FROM `site_settings` WHERE `id` = 1 LIMIT 1");
    if ($ssRes && ($ssRow = $ssRes->fetch_assoc())) {
        if (!empty($ssRow['site_title'])) {
            $companyName = (string) $ssRow['site_title'];
        }
        if (!empty($ssRow['site_tagline'])) {
            $companyTagline = (string) $ssRow['site_tagline'];
        }
        if (!empty($ssRow['logo_path'])) {
            $resolved = crmResolveIntakeLogoUrl($ssRow['logo_path']);
            // Prefer admin brand logo when settings path looks like a missing main-site asset
            if ($resolved !== '') {
                $logoUrl = $resolved;
            }
        }
    }
}

if ($request) {
    $noteToCustomer = trim((string) ($request['note_to_customer'] ?? ''));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <base href="<?= htmlspecialchars($adminAssetBase, ENT_QUOTES, 'UTF-8') ?>">
    <title>Travel Inquiry — <?= htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0/dist/css/select2.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tempusdominus-bootstrap-4@5.39.0/build/css/tempusdominus-bootstrap-4.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/moment@2.29.4/moment.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/tempusdominus-bootstrap-4@5.39.0/build/js/tempusdominus-bootstrap-4.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0/dist/js/select2.min.js"></script>
    <?php
    $leadFormScope = '.crm-lead-intake-public';
    include __DIR__ . '/../admin/crm/includes/lead_form_styles.php';
    ?>
    <style>
        body.hold-transition {
            margin: 0;
            min-height: 100vh;
            background: #f3f4f6;
            color: #334155;
            font-family: "Poppins", "Source Sans Pro", -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        .crm-lead-intake-page {
            max-width: 980px;
            margin: 0 auto;
            padding: 1.5rem 1rem 2.25rem;
        }

        .crm-lead-intake-hero {
            text-align: center;
            padding: 0.35rem 0.35rem 1rem;
        }

        .crm-lead-intake-logo-wrap {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 56px;
            margin: 0 auto 0.75rem;
        }

        .crm-lead-intake-logo {
            max-height: 64px;
            max-width: min(240px, 72vw);
            width: auto;
            height: auto;
            object-fit: contain;
            display: block;
        }

        .crm-lead-intake-logo.is-broken {
            display: none;
        }

        .crm-lead-intake-wordmark {
            display: none;
            margin: 0 auto;
            font-size: 1.35rem;
            font-weight: 700;
            color: #0f172a;
            letter-spacing: 0.01em;
        }

        .crm-lead-intake-logo-wrap.is-fallback .crm-lead-intake-wordmark {
            display: inline-block;
        }

        .crm-lead-intake-headline {
            margin: 0 0 0.4rem;
            font-size: clamp(1.35rem, 3vw, 1.75rem);
            font-weight: 700;
            color: #0f172a;
            line-height: 1.25;
        }

        .crm-lead-intake-accent {
            width: 48px;
            height: 3px;
            margin: 0 auto 0.65rem;
            border-radius: 999px;
            background: #e11d2e;
        }

        .crm-lead-intake-subhead {
            margin: 0 auto;
            max-width: 520px;
            color: #64748b;
            font-size: 0.9rem;
            line-height: 1.45;
            font-weight: 500;
        }

        .crm-lead-intake-trust {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 0.75rem;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            box-shadow: 0 2px 10px rgba(15, 23, 42, 0.04);
            padding: 1rem 0.85rem;
            margin-bottom: 1.15rem;
        }

        .crm-lead-intake-trust-item {
            display: flex;
            align-items: flex-start;
            gap: 0.55rem;
            min-width: 0;
            padding: 0 0.35rem;
        }

        .crm-lead-intake-trust-icon {
            width: 36px;
            height: 36px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 36px;
            font-size: 0.85rem;
            color: #fff;
        }

        .crm-lead-intake-trust-icon.tone-red { background: #e11d2e; }
        .crm-lead-intake-trust-icon.tone-blue { background: #2563eb; }
        .crm-lead-intake-trust-icon.tone-green { background: #16a34a; }
        .crm-lead-intake-trust-icon.tone-purple { background: #7c3aed; }

        .crm-lead-intake-trust-title {
            margin: 0 0 0.1rem;
            font-size: 0.78rem;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.25;
        }

        .crm-lead-intake-trust-text {
            margin: 0;
            font-size: 0.7rem;
            color: #64748b;
            line-height: 1.3;
        }

        .crm-lead-intake-note {
            display: flex;
            gap: 0.65rem;
            align-items: flex-start;
            margin-bottom: 1rem;
            padding: 0.85rem 1rem;
            border-radius: 12px;
            border: 1px solid #fecaca;
            background: #fff1f2;
            color: #9f1239;
            font-size: 0.9rem;
            line-height: 1.5;
        }

        .crm-lead-intake-status {
            margin-bottom: 1rem;
            border-radius: 12px;
        }

        .crm-lead-intake-public .crm-card {
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            box-shadow: 0 2px 10px rgba(15, 23, 42, 0.04);
            margin-bottom: 1rem;
            background: #fff;
            overflow: visible;
        }

        .crm-lead-intake-public .crm-card-hd-blue,
        .crm-lead-intake-public .crm-card-hd-teal,
        .crm-lead-intake-public .crm-card-hd-green {
            background: #fff !important;
            color: #0f172a !important;
            border-bottom: 1px solid #f1f5f9;
            border-radius: 14px 14px 0 0;
            padding: 1rem 1.15rem 0.85rem;
            display: flex;
            flex-direction: row !important;
            align-items: center;
            gap: 0.75rem;
            text-transform: none;
            letter-spacing: 0;
        }

        .crm-lead-intake-public .crm-card-hd-blue .crm-card-hd-title::after,
        .crm-lead-intake-public .crm-card-hd-teal .crm-card-hd-title::after,
        .crm-lead-intake-public .crm-card-hd-green .crm-card-hd-title::after {
            display: none;
        }

        .crm-lead-intake-public .intake-step-num {
            width: 34px;
            height: 34px;
            border-radius: 999px;
            background: #e11d2e;
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.95rem;
            flex: 0 0 34px;
        }

        .crm-lead-intake-public .crm-card-hd-title {
            display: block !important;
            padding: 0 !important;
            font-size: 1.05rem;
            font-weight: 700;
            color: #0f172a;
        }

        .crm-lead-intake-public .crm-card-hd-title i { display: none; }

        .crm-lead-intake-public .crm-card-hd-sub {
            margin: 0.15rem 0 0;
            color: #64748b;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .crm-lead-intake-public .crm-card-bd {
            padding: 1.05rem 1.15rem 1.15rem;
            background: #fff;
        }

        .crm-lead-intake-public .js-intake-services-card { display: none !important; }

        .crm-lead-intake-public > form > .row > [class*="col-"] {
            flex: 0 0 100%;
            max-width: 100%;
        }

        .crm-lead-intake-public label {
            color: #111827;
            font-weight: 600;
            font-size: 0.84rem;
            margin-bottom: 0.4rem;
        }

        .crm-lead-intake-public .label-req::after { color: #e11d2e; }

        .crm-lead-intake-public .form-group {
            margin-bottom: 1rem;
        }

        .crm-lead-intake-public .form-group > label {
            display: block;
            position: static;
            float: none;
            margin-bottom: 0.45rem;
            line-height: 1.3;
        }

        .crm-lead-intake-public .form-control,
        .crm-lead-intake-public .tp-rg-trigger,
        .crm-lead-intake-public .tp-destination-field,
        .crm-lead-intake-public .tp-hotel-cat-field,
        .crm-lead-intake-public .tp-vehicle-type-field {
            border: 1px solid #d1d5db !important;
            border-radius: 10px;
            background: #fff !important;
            min-height: 44px;
            height: auto;
            color: #111827;
            box-shadow: none !important;
            font-size: 0.9rem;
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
            background-clip: padding-box;
            outline: none;
        }

        .crm-lead-intake-public select.form-control {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath fill='%236b7280' d='M1.4.6L6 5.2 10.6.6 12 2 6 8 0 2z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 0.85rem center;
            background-size: 12px 8px;
            padding-right: 2.2rem;
        }

        .crm-lead-intake-public .form-control:focus,
        .crm-lead-intake-public .tp-destination-field:focus-within,
        .crm-lead-intake-public .tp-hotel-cat-field:focus,
        .crm-lead-intake-public .tp-vehicle-type-field:focus,
        .crm-lead-intake-public .tp-rg-trigger:focus {
            border-color: #fca5a5 !important;
            background: #fff !important;
            box-shadow: 0 0 0 3px rgba(225, 29, 46, 0.12) !important;
        }

        .crm-lead-intake-public .lead-field-icon > .form-control,
        .crm-lead-intake-public .lead-field-icon > select.form-control {
            padding-left: 2.35rem;
        }

        .crm-lead-intake-public .lead-field-icon.has-end-icon > .form-control {
            padding-left: 0.85rem;
            padding-right: 2.35rem;
        }

        .crm-lead-intake-public .lead-field-icon-glyph {
            color: #e11d2e;
            left: 0.85rem;
        }

        .crm-lead-intake-public .svc-detail-panel {
            border-radius: 12px;
            border: 1px solid #fecaca;
            background: #fff8f8;
            padding: 1rem;
        }

        .crm-lead-intake-public .svc-detail-hd {
            color: #e11d2e;
            border-bottom: 0;
            padding-bottom: 0;
            margin-bottom: 0.9rem;
            font-size: 0.95rem;
        }

        .crm-lead-intake-public .svc-detail-hd i { color: #e11d2e; }

        /* Tour Package: equal-width fields, no trailing gaps */
        .crm-lead-intake-public .svc-detail-panel[data-svc="tour_package"] .tp-pack-row {
            display: flex;
            flex-wrap: wrap;
            margin-left: -0.45rem;
            margin-right: -0.45rem;
        }
        .crm-lead-intake-public .svc-detail-panel[data-svc="tour_package"] .tp-pack-row > .form-group {
            flex: 1 1 0;
            min-width: 0;
            max-width: 100%;
            padding-left: 0.45rem;
            padding-right: 0.45rem;
        }
        .crm-lead-intake-public .svc-detail-panel[data-svc="tour_package"] .tp-pack-row > .tp-pack-col-wide {
            flex: 2.2 1 0;
        }
        .crm-lead-intake-public .svc-detail-panel[data-svc="tour_package"] .tp-pack-row > .tp-pack-col-narrow {
            flex: 0 0 8.5rem;
            max-width: 8.5rem;
        }
        .crm-lead-intake-public .svc-detail-panel[data-svc="tour_package"] .tp-pack-row > .d-none,
        .crm-lead-intake-public .svc-detail-panel[data-svc="tour_package"] .tp-pack-row > .js-tp-rg-hidden-inputs {
            flex: 0 0 0 !important;
            width: 0 !important;
            max-width: 0 !important;
            padding: 0 !important;
            margin: 0 !important;
            overflow: hidden;
            border: 0;
        }
        .crm-lead-intake-public .svc-detail-panel[data-svc="tour_package"] .tp-pack-row .tp-nights-stepper .tp-rg-stepper,
        .crm-lead-intake-public .svc-detail-panel[data-svc="tour_package"] .tp-pack-row .tp-rooms-stepper .tp-rg-stepper {
            width: 100%;
            min-width: 0;
            max-width: 100%;
        }
        .crm-lead-intake-public .svc-detail-panel[data-svc="tour_package"] .tp-pack-row .tp-destination-combobox,
        .crm-lead-intake-public .svc-detail-panel[data-svc="tour_package"] .tp-pack-row .tp-hotel-cat-picker,
        .crm-lead-intake-public .svc-detail-panel[data-svc="tour_package"] .tp-pack-row .tp-vehicle-type-picker,
        .crm-lead-intake-public .svc-detail-panel[data-svc="tour_package"] .tp-pack-row .js-tp-rg-picker {
            width: 100%;
        }

        /* Number of Guests dropdown — match trigger width & even steppers */
        .crm-lead-intake-public .js-tp-guests-field .tp-rg-picker {
            width: 100%;
        }
        .crm-lead-intake-public .js-tp-guests-field .tp-rg-trigger {
            width: 100%;
            text-align: left;
        }
        .crm-lead-intake-public .js-tp-guests-field .tp-rg-panels-wrap {
            left: 0 !important;
            right: 0 !important;
            width: 100% !important;
            min-width: 0 !important;
            max-width: 100% !important;
            box-sizing: border-box;
        }
        .crm-lead-intake-public .js-tp-guests-field .tp-rg-panel {
            width: 100% !important;
            min-width: 0 !important;
            max-width: 100% !important;
            box-sizing: border-box;
        }
        .crm-lead-intake-public .js-tp-guests-field .tp-rg-row {
            align-items: center;
            gap: 0.75rem;
            padding: 0.7rem 0.85rem;
        }
        .crm-lead-intake-public .js-tp-guests-field .tp-rg-row-label {
            flex: 1 1 auto;
            min-width: 0;
        }
        .crm-lead-intake-public .js-tp-guests-field .tp-rg-row-label strong {
            font-size: 0.88rem;
            line-height: 1.25;
        }
        .crm-lead-intake-public .js-tp-guests-field .tp-rg-row-label small {
            font-size: 0.7rem;
            line-height: 1.2;
        }
        .crm-lead-intake-public .js-tp-guests-field .tp-rg-stepper {
            flex: 0 0 118px;
            width: 118px !important;
            min-width: 118px !important;
            max-width: 118px !important;
            justify-content: space-between;
            border-radius: 8px;
            overflow: hidden;
        }
        .crm-lead-intake-public .js-tp-guests-field .tp-rg-step-btn {
            width: 36px;
            height: 36px;
            flex: 0 0 36px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0;
        }
        .crm-lead-intake-public .js-tp-guests-field .tp-rg-step-val {
            flex: 1 1 auto;
            min-width: 0;
            text-align: center;
            font-size: 0.9rem;
        }
        .crm-lead-intake-public .js-tp-guests-field .tp-rg-panel-hd {
            padding: 0.7rem 0.85rem;
            font-size: 0.9rem;
        }
        .crm-lead-intake-public .js-tp-guests-field .tp-rg-close {
            font-size: 1.15rem;
        }
        @media (max-width: 767.98px) {
            .crm-lead-intake-public .svc-detail-panel[data-svc="tour_package"] .tp-pack-row > .form-group,
            .crm-lead-intake-public .svc-detail-panel[data-svc="tour_package"] .tp-pack-row > .tp-pack-col-wide,
            .crm-lead-intake-public .svc-detail-panel[data-svc="tour_package"] .tp-pack-row > .tp-pack-col-narrow {
                flex: 1 1 100%;
                max-width: 100%;
            }
        }

        .crm-lead-intake-public .tp-rg-stepper {
            border-color: #d1d5db;
            border-radius: 10px;
            min-width: 118px;
        }

        .crm-lead-intake-public .tp-rg-step-btn { color: #6b7280; }
        .crm-lead-intake-public .tp-rg-step-btn:hover:not(:disabled) {
            background: #fff5f5;
            color: #e11d2e;
        }
        .crm-lead-intake-public .tp-rg-step-input,
        .crm-lead-intake-public .tp-rg-step-val { color: #e11d2e; }

        .crm-lead-intake-public .intake-tip-banner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.85rem;
            margin: 0.35rem 0 1rem;
            padding: 0.85rem 1rem;
            border-radius: 12px;
            border: 1px solid #bfdbfe;
            background: #eff6ff;
            color: #1e3a8a;
            font-size: 0.9rem;
            font-weight: 500;
            line-height: 1.45;
        }

        .crm-lead-intake-public .intake-tip-banner-left {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            min-width: 0;
        }

        .crm-lead-intake-public .intake-tip-banner-left i {
            color: #f59e0b;
            font-size: 1.2rem;
            flex: 0 0 auto;
        }

        .crm-lead-intake-public .intake-tip-banner-art {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            color: #ea580c;
            font-size: 1.15rem;
            flex: 0 0 auto;
        }

        .crm-lead-intake-public .form-actions {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
            background: transparent;
            border: 0;
            border-radius: 0;
            padding: 0.35rem 0 0.5rem;
            margin: 0;
        }

        .crm-lead-intake-public .intake-safe-note {
            display: flex;
            align-items: flex-start;
            gap: 0.65rem;
            min-width: 0;
            flex: 1 1 240px;
        }

        .crm-lead-intake-public .intake-safe-icon {
            width: 38px;
            height: 38px;
            border-radius: 999px;
            background: #dbeafe;
            color: #2563eb;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 38px;
        }

        .crm-lead-intake-public .intake-safe-title {
            margin: 0;
            font-size: 0.9rem;
            font-weight: 700;
            color: #0f172a;
        }

        .crm-lead-intake-public .intake-safe-text {
            margin: 0.1rem 0 0;
            font-size: 0.78rem;
            color: #64748b;
            line-height: 1.35;
        }

        .crm-lead-intake-public .intake-submit-wrap {
            text-align: center;
            margin-left: auto;
        }

        .crm-lead-intake-public .form-actions .js-lead-submit-btn,
        .crm-lead-intake-public .form-actions .btn-primary {
            min-width: 200px;
            min-height: 46px;
            border-radius: 10px;
            padding: 0.7rem 1.4rem;
            font-weight: 700;
            background: #e11d2e !important;
            border-color: #e11d2e !important;
            box-shadow: 0 8px 18px rgba(225, 29, 46, 0.22);
            order: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.45rem;
        }

        .crm-lead-intake-public .form-actions .js-lead-submit-btn:hover,
        .crm-lead-intake-public .form-actions .btn-primary:hover {
            background: #c81a28 !important;
            border-color: #c81a28 !important;
        }

        .crm-lead-intake-public .intake-submit-soon {
            margin: 0.45rem 0 0;
            color: #64748b;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .crm-lead-intake-public .tp-notes-wrap {
            position: relative;
        }

        .crm-lead-intake-public .tp-notes-counter {
            position: absolute;
            right: 0.7rem;
            bottom: 0.45rem;
            color: #94a3b8;
            font-size: 0.72rem;
            pointer-events: none;
        }

        .crm-lead-intake-public .crm-card,
        .crm-lead-intake-public .crm-card-bd,
        .crm-lead-intake-public .svc-detail-panel,
        .crm-lead-intake-public .js-tp-rg-picker,
        .crm-lead-intake-public .tp-destination-combobox,
        .crm-lead-intake-public .tp-hotel-cat-picker,
        .crm-lead-intake-public .tp-vehicle-type-picker {
            overflow: visible !important;
        }

        .crm-lead-intake-public .tp-destination-menu,
        .crm-lead-intake-public .tp-hotel-cat-menu,
        .crm-lead-intake-public .tp-vehicle-type-menu,
        .crm-lead-intake-public .tp-rg-panel,
        .crm-lead-intake-public .tp-rg-panels-wrap {
            z-index: 2000 !important;
        }

        .crm-lead-intake-footer {
            margin-top: 1rem;
            text-align: center;
            color: #94a3b8;
            font-size: 0.8rem;
            font-weight: 500;
        }

        @media (max-width: 991px) {
            .crm-lead-intake-trust {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 0.65rem;
                padding: 0.85rem 0.7rem;
            }

            .crm-lead-intake-trust-title {
                font-size: 0.72rem;
            }

            .crm-lead-intake-trust-text {
                font-size: 0.65rem;
            }
        }

        @media (max-width: 767.98px) {
            .crm-lead-intake-page {
                padding: 0.75rem 0.55rem 1.35rem;
            }

            .crm-lead-intake-hero {
                padding: 0.15rem 0.15rem 0.75rem;
            }

            .crm-lead-intake-logo-wrap {
                min-height: 44px;
                margin-bottom: 0.55rem;
            }

            .crm-lead-intake-logo {
                max-height: 48px;
                max-width: min(200px, 70vw);
            }

            .crm-lead-intake-wordmark {
                font-size: 1.1rem;
            }

            .crm-lead-intake-headline {
                font-size: 1.15rem;
                margin-bottom: 0.3rem;
            }

            .crm-lead-intake-accent {
                width: 36px;
                height: 2px;
                margin-bottom: 0.45rem;
            }

            .crm-lead-intake-subhead {
                font-size: 0.78rem;
                line-height: 1.4;
                max-width: 34rem;
                padding: 0 0.25rem;
            }

            .crm-lead-intake-trust {
                margin-bottom: 0.85rem;
                border-radius: 12px;
            }

            .crm-lead-intake-trust-icon {
                width: 30px;
                height: 30px;
                flex-basis: 30px;
                font-size: 0.72rem;
            }

            .crm-lead-intake-trust-item {
                gap: 0.4rem;
                padding: 0.1rem 0.15rem;
            }

            .crm-lead-intake-note {
                font-size: 0.78rem;
                padding: 0.7rem 0.8rem;
            }

            .crm-lead-intake-public .crm-card {
                border-radius: 12px;
                margin-bottom: 0.75rem;
            }

            .crm-lead-intake-public .crm-card-hd-blue,
            .crm-lead-intake-public .crm-card-hd-teal,
            .crm-lead-intake-public .crm-card-hd-green {
                padding: 0.75rem 0.85rem 0.65rem;
                gap: 0.55rem;
            }

            .crm-lead-intake-public .intake-step-num {
                width: 28px;
                height: 28px;
                flex-basis: 28px;
                font-size: 0.8rem;
            }

            .crm-lead-intake-public .crm-card-hd-title {
                font-size: 0.92rem;
            }

            .crm-lead-intake-public .crm-card-hd-sub {
                font-size: 0.7rem;
            }

            .crm-lead-intake-public .crm-card-bd {
                padding: 0.8rem 0.85rem 0.95rem;
            }

            .crm-lead-intake-public label {
                font-size: 0.74rem;
                margin-bottom: 0.28rem;
            }

            .crm-lead-intake-public .form-group {
                margin-bottom: 0.75rem;
            }

            .crm-lead-intake-public .form-control,
            .crm-lead-intake-public .tp-rg-trigger,
            .crm-lead-intake-public .tp-destination-field,
            .crm-lead-intake-public .tp-hotel-cat-field,
            .crm-lead-intake-public .tp-vehicle-type-field {
                min-height: 40px !important;
                font-size: 0.82rem !important;
                border-radius: 8px;
                padding-top: 0.4rem;
                padding-bottom: 0.4rem;
            }

            .crm-lead-intake-public .intake-tip-banner {
                font-size: 0.76rem;
                padding: 0.65rem 0.75rem;
                margin: 0.25rem 0 0.75rem;
            }

            .crm-lead-intake-public .form-actions .js-lead-submit-btn,
            .crm-lead-intake-public .form-actions .btn-primary {
                min-width: 0;
                width: 100%;
                min-height: 42px;
                font-size: 0.88rem;
                padding: 0.55rem 1rem;
            }

            .crm-lead-intake-public .form-actions {
                flex-direction: column;
                align-items: stretch;
                justify-content: flex-start;
                gap: 0.55rem;
                padding: 0.15rem 0 0;
                margin-top: 0.15rem;
            }

            .crm-lead-intake-public .intake-safe-note {
                flex: 0 0 auto;
                width: 100%;
                margin: 0;
            }

            .crm-lead-intake-public .intake-submit-wrap {
                margin: 0;
                width: 100%;
                text-align: center;
            }

            .crm-lead-intake-public .intake-submit-soon {
                margin-top: 0.35rem;
            }

            .crm-lead-intake-public .intake-submit-soon,
            .crm-lead-intake-public .intake-safe-note,
            .crm-lead-intake-footer {
                font-size: 0.7rem;
            }

            .crm-lead-intake-public .row > [class*="col-"] {
                flex: 0 0 100%;
                max-width: 100%;
            }

            .crm-lead-intake-public .js-tp-guests-field .tp-rg-panels-wrap {
                left: 0 !important;
                right: 0 !important;
                width: 100% !important;
            }

            .crm-lead-intake-public .js-tp-guests-field .tp-rg-stepper {
                flex-basis: 108px;
                width: 108px !important;
                min-width: 108px !important;
                max-width: 108px !important;
            }

            .crm-lead-intake-public .js-tp-guests-field .tp-rg-step-btn {
                width: 34px;
                height: 34px;
                flex-basis: 34px;
            }

            .crm-lead-intake-public .js-tp-guests-field .tp-rg-child-ages-popup {
                position: static;
                left: auto;
                top: auto;
                width: 100%;
                max-width: 100%;
                min-width: 0;
                margin-top: 0.5rem;
                box-shadow: none;
            }
        }

        @media (max-width: 575px) {
            .crm-lead-intake-page {
                padding: 0.6rem 0.45rem 1.2rem;
            }

            .crm-lead-intake-logo {
                max-height: 42px;
                max-width: min(180px, 68vw);
            }

            .crm-lead-intake-headline {
                font-size: 1.05rem;
            }

            .crm-lead-intake-subhead {
                font-size: 0.72rem;
            }

            .crm-lead-intake-trust {
                grid-template-columns: 1fr 1fr;
                gap: 0.5rem 0.35rem;
                padding: 0.65rem 0.5rem;
            }

            .crm-lead-intake-trust-title {
                font-size: 0.66rem;
            }

            .crm-lead-intake-trust-text {
                font-size: 0.6rem;
                line-height: 1.25;
            }

            .crm-lead-intake-trust-icon {
                width: 26px;
                height: 26px;
                flex-basis: 26px;
                font-size: 0.65rem;
            }

            .crm-lead-intake-public .crm-card-hd-title {
                font-size: 0.86rem;
            }

            .crm-lead-intake-public label {
                font-size: 0.7rem;
            }

            .crm-lead-intake-public .form-control,
            .crm-lead-intake-public .tp-rg-trigger,
            .crm-lead-intake-public .tp-destination-field,
            .crm-lead-intake-public .tp-hotel-cat-field,
            .crm-lead-intake-public .tp-vehicle-type-field {
                min-height: 38px !important;
                font-size: 0.78rem !important;
            }

            .crm-lead-intake-public .form-actions {
                flex-direction: column;
                align-items: stretch;
                justify-content: flex-start;
                gap: 0.45rem;
                padding: 0;
                margin-top: 0;
            }

            .crm-lead-intake-public .intake-safe-note {
                flex: 0 0 auto;
                width: 100%;
            }

            .crm-lead-intake-public .intake-submit-wrap {
                margin: 0;
                width: 100%;
            }

            .crm-lead-intake-public .form-actions .js-lead-submit-btn {
                width: 100%;
            }

            .crm-lead-intake-public .intake-submit-soon {
                margin-top: 0.3rem;
                margin-bottom: 0;
            }

            .crm-lead-intake-public .intake-tip-banner {
                flex-direction: column;
                align-items: flex-start;
                margin-bottom: 0.55rem;
            }
        }
    </style>
</head>
<body class="hold-transition">
<div class="crm-lead-intake-page">
    <div class="crm-lead-intake-hero">
        <div class="crm-lead-intake-logo-wrap" id="crmIntakeLogoWrap">
            <img
                src="<?= htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') ?>"
                alt="<?= htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8') ?>"
                class="crm-lead-intake-logo"
                id="crmIntakeLogo"
                decoding="async"
            >
            <div class="crm-lead-intake-wordmark"><?= htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8') ?></div>
        </div>
        <h1 class="crm-lead-intake-headline">Explore the World with Us</h1>
        <div class="crm-lead-intake-accent" aria-hidden="true"></div>
        <p class="crm-lead-intake-subhead"><?= htmlspecialchars($intakeFormSubtitle, ENT_QUOTES, 'UTF-8') ?></p>
    </div>

    <?php if (!$error && !$success) { ?>
    <div class="crm-lead-intake-trust">
        <div class="crm-lead-intake-trust-item">
            <span class="crm-lead-intake-trust-icon tone-red"><i class="fas fa-check"></i></span>
            <div>
                <p class="crm-lead-intake-trust-title">Best Price Guarantee</p>
                <p class="crm-lead-intake-trust-text">We ensure the best prices for you</p>
            </div>
        </div>
        <div class="crm-lead-intake-trust-item">
            <span class="crm-lead-intake-trust-icon tone-blue"><i class="fas fa-check"></i></span>
            <div>
                <p class="crm-lead-intake-trust-title">Expert Travel Planners</p>
                <p class="crm-lead-intake-trust-text">Personalized itineraries by travel experts</p>
            </div>
        </div>
        <div class="crm-lead-intake-trust-item">
            <span class="crm-lead-intake-trust-icon tone-green"><i class="fas fa-headset"></i></span>
            <div>
                <p class="crm-lead-intake-trust-title">24/7 Customer Support</p>
                <p class="crm-lead-intake-trust-text">We're here to assist you anytime</p>
            </div>
        </div>
        <div class="crm-lead-intake-trust-item">
            <span class="crm-lead-intake-trust-icon tone-purple"><i class="fas fa-shield-alt"></i></span>
            <div>
                <p class="crm-lead-intake-trust-title">Safe &amp; Secure</p>
                <p class="crm-lead-intake-trust-text">Your information is 100% secure</p>
            </div>
        </div>
    </div>
    <?php } ?>

    <?php if ($error) { ?>
        <div class="alert alert-danger crm-lead-intake-status mb-0"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php } elseif ($success) { ?>
        <div class="alert alert-success crm-lead-intake-status mb-0"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div>
    <?php } else { ?>
        <?php if ($noteToCustomer !== '') { ?>
            <div class="crm-lead-intake-note">
                <i class="fas fa-info-circle mt-1"></i>
                <div><?= nl2br(htmlspecialchars($noteToCustomer, ENT_QUOTES, 'UTF-8')) ?></div>
            </div>
        <?php } ?>

        <div class="crm-lead-intake-public">
            <?php
            $leadFormPublicIntake = true;
            $leadFormIntakeToken = $token;
            $leadFormIntakeEnabledFields = $enabledFields;
            $leadFormIntakePrefill = [
                'recipient_name' => (string) ($request['recipient_name'] ?? ''),
                'recipient_phone' => (string) ($request['recipient_phone'] ?? ''),
                'recipient_email' => (string) ($request['recipient_email'] ?? ''),
            ];
            $leadFormIntakeSubmitUrl = crmBuildIntakeSubmitUrl();
            include __DIR__ . '/../admin/crm/includes/lead_form_content.php';
            ?>
        </div>
    <?php } ?>

    <div class="crm-lead-intake-footer">
        Secure travel inquiry form &middot; <?= htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8') ?>
    </div>
</div>

<div class="modal fade" id="leadIntakeSuccessModal" tabindex="-1" role="dialog" aria-hidden="true" data-backdrop="static" data-keyboard="false">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content border-0 shadow">
            <div class="modal-body text-center py-4 px-4">
                <div class="text-success mb-3"><i class="fas fa-check-circle fa-3x"></i></div>
                <p class="mb-0 js-intake-success-message lead-intake-success-text"></p>
            </div>
        </div>
    </div>
</div>
<style>
    .lead-intake-success-text {
        font-size: 1.05rem;
        font-weight: 600;
        color: #1e3a5f;
        line-height: 1.5;
    }
</style>
<script>
(function () {
    var logo = document.getElementById('crmIntakeLogo');
    var wrap = document.getElementById('crmIntakeLogoWrap');
    var fallback = <?= json_encode($logoFallbackUrl, JSON_UNESCAPED_SLASHES) ?>;
    if (!logo || !wrap) {
        return;
    }
    var triedFallback = false;
    function showTextFallback() {
        logo.classList.add('is-broken');
        wrap.classList.add('is-fallback');
    }
    logo.addEventListener('error', function () {
        if (!triedFallback && fallback && logo.getAttribute('src') !== fallback) {
            triedFallback = true;
            logo.setAttribute('src', fallback);
            return;
        }
        showTextFallback();
    });
    if (logo.complete && logo.naturalWidth === 0) {
        logo.dispatchEvent(new Event('error'));
    }
})();
</script>
<script>
(function () {
    var $notes = $('textarea[name="tp_notes"]');
    if ($notes.length) {
        var $wrap = $notes.closest('.form-group');
        if ($wrap.length && !$wrap.find('.tp-notes-counter').length) {
            $wrap.addClass('tp-notes-wrap');
            $notes.attr('maxlength', 500);
            $wrap.append('<span class="tp-notes-counter">0/500</span>');
            var $counter = $wrap.find('.tp-notes-counter');
            $notes.on('input', function () {
                $counter.text(String(($notes.val() || '').length) + '/500');
            });
        }
    }
})();
</script>
</body>
</html>
