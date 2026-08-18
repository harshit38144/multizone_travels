<?php
date_default_timezone_set('Asia/Kolkata');

require_once __DIR__ . '/../admin/config/database.php';
require_once __DIR__ . '/../admin/crm/includes/lead_intake_db.php';

crmEnsureLeadIntakeTables($conn);

$token = trim($_GET['token'] ?? '');
$error = '';
$summary = null;

$companyName = 'Multi Zone Travels';
$companyTagline = 'travel for memories...';
$logoUrl = crmResolveIntakeLogoUrl('img/web-logo.png');
$supportPhone = '+91 98765 43210';
$supportEmail = 'support@multizonetravels.com';
$homeUrl = '../index.php';
$adminAssetBase = crmPublicAdminAssetBase();

$ssTable = $conn->query("SHOW TABLES LIKE 'site_settings'");
if ($ssTable && $ssTable->num_rows > 0) {
    $ssRes = $conn->query("SELECT `site_title`, `site_tagline`, `logo_path`, `footer_phone`, `footer_email`, `whatsapp_phone` FROM `site_settings` WHERE `id` = 1 LIMIT 1");
    if ($ssRes && ($ssRow = $ssRes->fetch_assoc())) {
        if (!empty($ssRow['site_title'])) {
            $companyName = (string) $ssRow['site_title'];
        }
        if (!empty($ssRow['site_tagline'])) {
            $companyTagline = (string) $ssRow['site_tagline'];
        }
        if (!empty($ssRow['logo_path'])) {
            $logoUrl = crmResolveIntakeLogoUrl($ssRow['logo_path']);
        }
        $fp = trim((string) ($ssRow['footer_phone'] ?? ''));
        $wp = trim((string) ($ssRow['whatsapp_phone'] ?? ''));
        if ($fp !== '') {
            $supportPhone = crmFormatIntakePhoneDisplay($fp);
        } elseif ($wp !== '') {
            $supportPhone = crmFormatIntakePhoneDisplay($wp);
        }
        $fe = trim((string) ($ssRow['footer_email'] ?? ''));
        if ($fe !== '') {
            $supportEmail = $fe;
        }
    }
}

if ($token === '') {
    $error = 'Invalid or missing link.';
} else {
    $row = crmFetchLatestIntakeSubmissionByToken($conn, $token);
    if (!$row) {
        $error = 'We could not find your inquiry details.';
    } else {
        $payload = [];
        if (!empty($row['payload_json'])) {
            $decoded = json_decode((string) $row['payload_json'], true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }
        $summary = crmBuildIntakeThanksSummary(
            $conn,
            $payload,
            (int) ($row['submission_id'] ?? 0),
            (string) ($row['submitted_at'] ?? '')
        );
    }
}

$phoneHref = preg_replace('/\D+/', '', $supportPhone) ?: '';
$emailHref = $supportEmail;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <base href="<?= htmlspecialchars($adminAssetBase, ENT_QUOTES, 'UTF-8') ?>">
    <title>Thank You — <?= htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Great+Vibes&display=swap" rel="stylesheet">
    <?php include __DIR__ . '/../admin/includes/header-links.php'; ?>
    <style>
        body.hold-transition {
            margin: 0;
            min-height: 100vh;
            background: #f7f8fa;
            color: #334155;
            font-family: "Poppins", "Source Sans Pro", -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        .intake-thanks-page {
            max-width: 920px;
            margin: 0 auto;
            padding: 1.75rem 1rem 2.75rem;
        }

        .intake-thanks-logo {
            display: none;
            max-height: 72px;
            max-width: min(280px, 78vw);
            width: auto;
            margin: 0 auto 1.1rem;
            object-fit: contain;
        }

        .intake-thanks-logo.is-loaded {
            display: block;
        }

        .intake-thanks-success {
            position: relative;
            width: 112px;
            height: 112px;
            margin: 0.35rem auto 1.15rem;
        }

        .intake-thanks-success::before,
        .intake-thanks-success::after {
            content: "";
            position: absolute;
            inset: 0;
            border-radius: 50%;
            border: 2px solid rgba(34, 197, 94, 0.18);
            pointer-events: none;
        }

        .intake-thanks-success::before {
            inset: -10px;
            border-color: rgba(34, 197, 94, 0.12);
        }

        .intake-thanks-success::after {
            inset: -20px;
            border-color: rgba(34, 197, 94, 0.08);
        }

        .intake-thanks-success-core {
            position: absolute;
            inset: 0;
            border-radius: 50%;
            background: linear-gradient(160deg, #22c55e 0%, #16a34a 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 2.6rem;
            box-shadow: 0 12px 28px rgba(22, 163, 74, 0.28);
            z-index: 1;
        }

        .intake-thanks-deco {
            position: absolute;
            border-radius: 50%;
            pointer-events: none;
            z-index: 0;
        }

        .intake-thanks-deco.d1 { width: 10px; height: 10px; background: #93c5fd; top: 8px; left: -18px; }
        .intake-thanks-deco.d2 { width: 8px; height: 8px; background: #f9a8d4; top: 4px; right: -8px; }
        .intake-thanks-deco.d3 { width: 7px; height: 7px; background: #86efac; bottom: 14px; left: -10px; }
        .intake-thanks-deco.d4 { width: 9px; height: 9px; background: #fbbf24; bottom: 6px; right: -16px; }
        .intake-thanks-deco.d5 { width: 6px; height: 6px; background: #c4b5fd; top: 42%; right: -22px; }

        .intake-thanks-title {
            margin: 0 0 0.35rem;
            text-align: center;
            font-size: clamp(1.85rem, 4vw, 2.35rem);
            font-weight: 700;
            color: #0f172a;
            letter-spacing: -0.02em;
        }

        .intake-thanks-sub {
            margin: 0 0 1.6rem;
            text-align: center;
            font-size: 1rem;
            color: #64748b;
            font-weight: 500;
        }

        .intake-thanks-card {
            background: #fff;
            border: 1px solid #eef2f7;
            border-radius: 18px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
            padding: 1.25rem 1.35rem 1.1rem;
            margin-bottom: 2rem;
        }

        .intake-thanks-card-hd {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            margin-bottom: 1.15rem;
        }

        .intake-thanks-card-hd-icon {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: #fee2e2;
            color: #e11d2e;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            flex-shrink: 0;
        }

        .intake-thanks-card-hd-title {
            margin: 0;
            font-size: 1.05rem;
            font-weight: 700;
            color: #0f172a;
        }

        .intake-thanks-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 1.15rem 1rem;
        }

        .intake-thanks-item {
            display: flex;
            align-items: flex-start;
            gap: 0.65rem;
            min-width: 0;
        }

        .intake-thanks-item-icon {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            flex-shrink: 0;
        }

        .intake-thanks-item-icon.tone-red { background: #fee2e2; color: #e11d2e; }
        .intake-thanks-item-icon.tone-green { background: #dcfce7; color: #16a34a; }
        .intake-thanks-item-icon.tone-orange { background: #ffedd5; color: #ea580c; }
        .intake-thanks-item-icon.tone-purple { background: #ede9fe; color: #7c3aed; }
        .intake-thanks-item-icon.tone-blue { background: #dbeafe; color: #2563eb; }

        .intake-thanks-item-body { min-width: 0; }
        .intake-thanks-item-label {
            display: block;
            font-size: 0.72rem;
            font-weight: 500;
            color: #94a3b8;
            margin-bottom: 0.15rem;
            line-height: 1.2;
        }
        .intake-thanks-item-value {
            display: block;
            font-size: 0.92rem;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.3;
            word-break: break-word;
        }
        .intake-thanks-item-value.is-id { color: #e11d2e; }

        .intake-thanks-next {
            text-align: center;
            margin-bottom: 1.75rem;
        }

        .intake-thanks-next-title {
            margin: 0 0 0.45rem;
            font-size: clamp(1.35rem, 3vw, 1.65rem);
            font-weight: 700;
            color: #0f172a;
        }

        .intake-thanks-next-accent {
            width: 42px;
            height: 3px;
            border-radius: 999px;
            background: #e11d2e;
            margin: 0 auto 1.35rem;
        }

        .intake-thanks-steps {
            display: flex;
            align-items: stretch;
            gap: 0.65rem;
            margin-bottom: 1.35rem;
        }

        .intake-thanks-step {
            flex: 1 1 0;
            position: relative;
            text-align: center;
            border-radius: 14px;
            padding: 1.15rem 0.85rem 1rem;
            min-width: 0;
        }

        .intake-thanks-step.tone-blue { background: #eff6ff; }
        .intake-thanks-step.tone-green { background: #f0fdf4; }
        .intake-thanks-step.tone-purple { background: #f5f3ff; }

        .intake-thanks-step-num {
            position: absolute;
            top: 0.65rem;
            right: 0.7rem;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            font-size: 0.72rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #fff;
        }

        .intake-thanks-step.tone-blue .intake-thanks-step-num { color: #2563eb; }
        .intake-thanks-step.tone-green .intake-thanks-step-num { color: #16a34a; }
        .intake-thanks-step.tone-purple .intake-thanks-step-num { color: #7c3aed; }

        .intake-thanks-step-icon {
            font-size: 1.45rem;
            margin-bottom: 0.55rem;
            line-height: 1;
        }

        .intake-thanks-step.tone-blue .intake-thanks-step-icon { color: #2563eb; }
        .intake-thanks-step.tone-green .intake-thanks-step-icon { color: #16a34a; }
        .intake-thanks-step.tone-purple .intake-thanks-step-icon { color: #7c3aed; }

        .intake-thanks-step-title {
            margin: 0 0 0.35rem;
            font-size: 0.92rem;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.3;
        }

        .intake-thanks-step-text {
            margin: 0;
            font-size: 0.78rem;
            color: #64748b;
            line-height: 1.45;
        }

        .intake-thanks-arrow {
            flex: 0 0 auto;
            align-self: center;
            color: #cbd5e1;
            font-size: 0.95rem;
        }

        .intake-thanks-info {
            display: flex;
            align-items: center;
            gap: 0.7rem;
            background: #eff6ff;
            border-radius: 12px;
            padding: 0.85rem 1rem;
            color: #1e3a5f;
            font-size: 0.9rem;
            font-weight: 500;
            margin-bottom: 1.15rem;
            text-align: left;
        }

        .intake-thanks-info i {
            color: #2563eb;
            font-size: 1.1rem;
            flex-shrink: 0;
        }

        .intake-thanks-help {
            display: flex;
            align-items: center;
            gap: 0.9rem;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            padding: 1rem 1.1rem;
            text-align: left;
            margin-bottom: 1.6rem;
        }

        .intake-thanks-help-icon {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: #fee2e2;
            color: #e11d2e;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
        }

        .intake-thanks-help-title {
            margin: 0 0 0.25rem;
            font-size: 1rem;
            font-weight: 700;
            color: #0f172a;
        }

        .intake-thanks-help-text {
            margin: 0;
            font-size: 0.88rem;
            color: #475569;
            line-height: 1.5;
        }

        .intake-thanks-help-text a {
            color: #e11d2e;
            font-weight: 700;
            text-decoration: none;
        }

        .intake-thanks-help-text a:hover { text-decoration: underline; }

        .intake-thanks-actions {
            text-align: center;
        }

        .intake-thanks-home-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            background: #e11d2e;
            color: #fff !important;
            border: 0;
            border-radius: 10px;
            padding: 0.8rem 1.6rem;
            font-size: 0.95rem;
            font-weight: 700;
            text-decoration: none !important;
            box-shadow: 0 8px 20px rgba(225, 29, 46, 0.25);
            transition: background 0.15s ease, transform 0.15s ease;
        }

        .intake-thanks-home-btn:hover {
            background: #c91020;
            color: #fff !important;
            transform: translateY(-1px);
        }

        .intake-thanks-trust {
            margin: 1.15rem 0 0.35rem;
            font-size: 0.9rem;
            color: #64748b;
            font-weight: 500;
        }

        .intake-thanks-tagline {
            margin: 0;
            font-family: "Great Vibes", cursive;
            font-size: 1.65rem;
            color: #e11d2e;
            line-height: 1.2;
        }

        .intake-thanks-tagline i {
            font-size: 0.85rem;
            margin-left: 0.2rem;
            vertical-align: middle;
        }

        .intake-thanks-error {
            max-width: 520px;
            margin: 2rem auto;
            text-align: center;
        }

        @media (max-width: 991.98px) {
            .intake-thanks-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 767.98px) {
            .intake-thanks-steps {
                flex-direction: column;
                gap: 0.75rem;
            }
            .intake-thanks-arrow { display: none; }
            .intake-thanks-grid {
                grid-template-columns: 1fr;
            }
            .intake-thanks-help {
                align-items: flex-start;
            }
        }
    </style>
</head>
<body class="hold-transition">
<div class="intake-thanks-page">
    <?php if ($error) { ?>
        <div class="intake-thanks-error">
            <img src="<?= htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8') ?>" class="intake-thanks-logo" onload="this.classList.add('is-loaded')" onerror="this.classList.add('is-broken')">
            <div class="alert alert-danger mb-3"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
            <a href="<?= htmlspecialchars($homeUrl, ENT_QUOTES, 'UTF-8') ?>" class="intake-thanks-home-btn"><i class="fas fa-home"></i> Back to Home</a>
        </div>
    <?php } else { ?>
        <img src="<?= htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8') ?>" class="intake-thanks-logo" onload="this.classList.add('is-loaded')" onerror="this.classList.add('is-broken')">

        <div class="intake-thanks-success" aria-hidden="true">
            <span class="intake-thanks-deco d1"></span>
            <span class="intake-thanks-deco d2"></span>
            <span class="intake-thanks-deco d3"></span>
            <span class="intake-thanks-deco d4"></span>
            <span class="intake-thanks-deco d5"></span>
            <div class="intake-thanks-success-core"><i class="fas fa-check"></i></div>
        </div>

        <h1 class="intake-thanks-title">Thank You!</h1>
        <p class="intake-thanks-sub">Your Inquiry has been submitted</p>

        <div class="intake-thanks-card">
            <div class="intake-thanks-card-hd">
                <span class="intake-thanks-card-hd-icon"><i class="fas fa-file-alt"></i></span>
                <h2 class="intake-thanks-card-hd-title">Inquiry Summary</h2>
            </div>
            <div class="intake-thanks-grid">
                <div class="intake-thanks-item">
                    <span class="intake-thanks-item-icon tone-red"><i class="fas fa-file-alt"></i></span>
                    <div class="intake-thanks-item-body">
                        <span class="intake-thanks-item-label">Inquiry ID</span>
                        <span class="intake-thanks-item-value is-id"><?= htmlspecialchars($summary['inquiry_id'], ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                </div>
                <div class="intake-thanks-item">
                    <span class="intake-thanks-item-icon tone-green"><i class="far fa-calendar-alt"></i></span>
                    <div class="intake-thanks-item-body">
                        <span class="intake-thanks-item-label">Date &amp; Time</span>
                        <span class="intake-thanks-item-value"><?= htmlspecialchars($summary['submitted_at_display'], ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                </div>
                <div class="intake-thanks-item">
                    <span class="intake-thanks-item-icon tone-orange"><i class="fas fa-map-marker-alt"></i></span>
                    <div class="intake-thanks-item-body">
                        <span class="intake-thanks-item-label">Destination</span>
                        <span class="intake-thanks-item-value"><?= htmlspecialchars($summary['destination'], ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                </div>
                <div class="intake-thanks-item">
                    <span class="intake-thanks-item-icon tone-purple"><i class="fas fa-gift"></i></span>
                    <div class="intake-thanks-item-body">
                        <span class="intake-thanks-item-label">Package</span>
                        <span class="intake-thanks-item-value"><?= htmlspecialchars($summary['package'], ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                </div>
                <div class="intake-thanks-item">
                    <span class="intake-thanks-item-icon tone-blue"><i class="fas fa-user"></i></span>
                    <div class="intake-thanks-item-body">
                        <span class="intake-thanks-item-label">Name</span>
                        <span class="intake-thanks-item-value"><?= htmlspecialchars($summary['name'], ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                </div>
                <div class="intake-thanks-item">
                    <span class="intake-thanks-item-icon tone-purple"><i class="fas fa-envelope"></i></span>
                    <div class="intake-thanks-item-body">
                        <span class="intake-thanks-item-label">Email</span>
                        <span class="intake-thanks-item-value"><?= htmlspecialchars($summary['email'], ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                </div>
                <div class="intake-thanks-item">
                    <span class="intake-thanks-item-icon tone-green"><i class="fas fa-phone"></i></span>
                    <div class="intake-thanks-item-body">
                        <span class="intake-thanks-item-label">Phone</span>
                        <span class="intake-thanks-item-value"><?= htmlspecialchars($summary['phone'], ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                </div>
                <div class="intake-thanks-item">
                    <span class="intake-thanks-item-icon tone-red"><i class="fas fa-users"></i></span>
                    <div class="intake-thanks-item-body">
                        <span class="intake-thanks-item-label">Guests</span>
                        <span class="intake-thanks-item-value"><?= htmlspecialchars($summary['guests'], ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="intake-thanks-next">
            <h2 class="intake-thanks-next-title">What Happens Next?</h2>
            <div class="intake-thanks-next-accent" aria-hidden="true"></div>

            <div class="intake-thanks-steps">
                <div class="intake-thanks-step tone-blue">
                    <span class="intake-thanks-step-num">1</span>
                    <div class="intake-thanks-step-icon"><i class="fas fa-headset"></i></div>
                    <h3 class="intake-thanks-step-title">We Review Your Inquiry</h3>
                    <p class="intake-thanks-step-text">Our travel experts will carefully review your requirements.</p>
                </div>
                <div class="intake-thanks-arrow" aria-hidden="true"><i class="fas fa-chevron-right"></i></div>
                <div class="intake-thanks-step tone-green">
                    <span class="intake-thanks-step-num">2</span>
                    <div class="intake-thanks-step-icon"><i class="fas fa-clipboard-check"></i></div>
                    <h3 class="intake-thanks-step-title">We Prepare the Best Options</h3>
                    <p class="intake-thanks-step-text">We'll curate personalized options and the best possible itinerary.</p>
                </div>
                <div class="intake-thanks-arrow" aria-hidden="true"><i class="fas fa-chevron-right"></i></div>
                <div class="intake-thanks-step tone-purple">
                    <span class="intake-thanks-step-num">3</span>
                    <div class="intake-thanks-step-icon"><i class="fas fa-paper-plane"></i></div>
                    <h3 class="intake-thanks-step-title">We Get Back to You</h3>
                    <p class="intake-thanks-step-text">Our team will contact you shortly with the best offers.</p>
                </div>
            </div>

            <div class="intake-thanks-info">
                <i class="fas fa-info-circle"></i>
                <span>You will receive an email confirmation shortly with your inquiry details.</span>
            </div>

            <div class="intake-thanks-help">
                <span class="intake-thanks-help-icon"><i class="fas fa-headset"></i></span>
                <div>
                    <h3 class="intake-thanks-help-title">Need Immediate Assistance?</h3>
                    <p class="intake-thanks-help-text">
                        Call us at
                        <?php if ($phoneHref !== '') { ?>
                            <a href="tel:+<?= htmlspecialchars($phoneHref, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($supportPhone, ENT_QUOTES, 'UTF-8') ?></a>
                        <?php } else { ?>
                            <strong style="color:#e11d2e;"><?= htmlspecialchars($supportPhone, ENT_QUOTES, 'UTF-8') ?></strong>
                        <?php } ?>
                        or email us at
                        <a href="mailto:<?= htmlspecialchars($emailHref, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($supportEmail, ENT_QUOTES, 'UTF-8') ?></a>
                    </p>
                </div>
            </div>

            <div class="intake-thanks-actions">
                <a href="<?= htmlspecialchars($homeUrl, ENT_QUOTES, 'UTF-8') ?>" class="intake-thanks-home-btn">
                    <i class="fas fa-home"></i> Back to Home
                </a>
                <p class="intake-thanks-trust">We appreciate your trust in <?= htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8') ?>.</p>
                <p class="intake-thanks-tagline"><?= htmlspecialchars($companyTagline, ENT_QUOTES, 'UTF-8') ?> <i class="fas fa-heart" aria-hidden="true"></i></p>
            </div>
        </div>
    <?php } ?>
</div>
</body>
</html>
