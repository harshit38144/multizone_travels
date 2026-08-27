<?php
date_default_timezone_set('Asia/Kolkata');

require_once __DIR__ . '/../admin/config/database.php';
require_once __DIR__ . '/../admin/crm/includes/lead_intake_db.php';

crmEnsureLeadIntakeTables($conn);

$token = trim($_GET['token'] ?? '');
$error = '';
$summary = null;

$companyName = 'Multi Zone Travels';
$companyTagline = 'Explore the World with Us';
$logoUrl = crmResolveIntakeLogoUrl('img/web-logo.png');
$supportPhone = '+91 98765 43210';
$supportEmail = 'support@multizonetravels.com';
$homeUrl = function_exists('crmBuildIntakeWebsiteHomeUrl')
    ? crmBuildIntakeWebsiteHomeUrl()
    : 'https://multizonetravels.com/';
$adminAssetBase = crmPublicAdminAssetBase();

$ssTable = $conn->query("SHOW TABLES LIKE 'site_settings'");
if ($ssTable && $ssTable->num_rows > 0) {
    $ssRes = $conn->query("SELECT `logo_path`, `footer_phone`, `footer_email`, `whatsapp_phone` FROM `site_settings` WHERE `id` = 1 LIMIT 1");
    if ($ssRes && ($ssRow = $ssRes->fetch_assoc())) {
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
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <base href="<?= htmlspecialchars($adminAssetBase, ENT_QUOTES, 'UTF-8') ?>">
    <title>Thank You — <?= htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Great+Vibes&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" crossorigin="anonymous">
    <style>
        :root {
            --ty-red: #e11d2e;
            --ty-ink: #0f172a;
            --ty-muted: #64748b;
            --ty-label: #94a3b8;
            --ty-line: #eef2f7;
            --ty-bg: #f5f6f8;
            --ty-green: #16a34a;
            --ty-green-bright: #22c55e;
        }

        * { box-sizing: border-box; }

        body.hold-transition {
            margin: 0;
            min-height: 100vh;
            background: var(--ty-bg);
            color: #334155;
            font-family: "Poppins", -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            font-size: 14px;
            -webkit-font-smoothing: antialiased;
        }

        .intake-thanks-page {
            max-width: 880px;
            margin: 0 auto;
            padding: 1rem 0.85rem 2rem;
        }

        .intake-thanks-logo {
            display: none;
            max-height: 48px;
            max-width: min(200px, 62vw);
            width: auto;
            margin: 0 auto 0.75rem;
            object-fit: contain;
        }

        .intake-thanks-logo.is-loaded { display: block; }

        /* —— Animated success mark —— */
        .intake-thanks-success {
            position: relative;
            width: 72px;
            height: 72px;
            margin: 0.15rem auto 0.85rem;
        }

        .intake-thanks-success-ring {
            position: absolute;
            inset: -6px;
            border-radius: 50%;
            border: 2px solid rgba(34, 197, 94, 0.22);
            animation: ty-ring-pulse 1.6s ease-out 0.55s both;
        }

        .intake-thanks-success-ring.r2 {
            inset: -14px;
            border-color: rgba(34, 197, 94, 0.12);
            animation-delay: 0.72s;
        }

        .intake-thanks-success-core {
            position: absolute;
            inset: 0;
            border-radius: 50%;
            background: linear-gradient(160deg, var(--ty-green-bright) 0%, var(--ty-green) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 8px 20px rgba(22, 163, 74, 0.28);
            z-index: 1;
            animation: ty-pop 0.55s cubic-bezier(0.22, 1.2, 0.36, 1) both;
        }

        .intake-thanks-check {
            width: 34px;
            height: 34px;
            display: block;
        }

        .intake-thanks-check-circle {
            fill: none;
            stroke: rgba(255, 255, 255, 0.35);
            stroke-width: 2.5;
        }

        .intake-thanks-check-mark {
            fill: none;
            stroke: #fff;
            stroke-width: 3.2;
            stroke-linecap: round;
            stroke-linejoin: round;
            stroke-dasharray: 48;
            stroke-dashoffset: 48;
            animation: ty-draw-check 0.55s ease-out 0.35s forwards;
        }

        .intake-thanks-deco {
            position: absolute;
            border-radius: 50%;
            pointer-events: none;
            z-index: 0;
            opacity: 0;
            animation: ty-spark 0.5s ease-out forwards;
        }

        .intake-thanks-deco.d1 { width: 7px; height: 7px; background: #93c5fd; top: 4px; left: -12px; animation-delay: 0.55s; }
        .intake-thanks-deco.d2 { width: 6px; height: 6px; background: #f9a8d4; top: 0; right: -6px; animation-delay: 0.62s; }
        .intake-thanks-deco.d3 { width: 5px; height: 5px; background: #86efac; bottom: 10px; left: -6px; animation-delay: 0.7s; }
        .intake-thanks-deco.d4 { width: 7px; height: 7px; background: #fbbf24; bottom: 2px; right: -12px; animation-delay: 0.78s; }
        .intake-thanks-deco.d5 { width: 5px; height: 5px; background: #c4b5fd; top: 42%; right: -16px; animation-delay: 0.85s; }

        @keyframes ty-pop {
            0% { transform: scale(0.35); opacity: 0; }
            70% { transform: scale(1.08); opacity: 1; }
            100% { transform: scale(1); opacity: 1; }
        }

        @keyframes ty-draw-check {
            to { stroke-dashoffset: 0; }
        }

        @keyframes ty-ring-pulse {
            0% { transform: scale(0.75); opacity: 0; }
            40% { opacity: 1; }
            100% { transform: scale(1.12); opacity: 0; }
        }

        @keyframes ty-spark {
            0% { transform: scale(0); opacity: 0; }
            60% { transform: scale(1.25); opacity: 1; }
            100% { transform: scale(1); opacity: 0.9; }
        }

        .intake-thanks-title {
            margin: 0 0 0.2rem;
            text-align: center;
            font-size: 1.35rem;
            font-weight: 700;
            color: var(--ty-ink);
            letter-spacing: -0.02em;
            line-height: 1.2;
        }

        .intake-thanks-sub {
            margin: 0 0 1.1rem;
            text-align: center;
            font-size: 0.8rem;
            color: var(--ty-muted);
            font-weight: 500;
            line-height: 1.35;
        }

        .intake-thanks-card {
            background: #fff;
            border: 1px solid var(--ty-line);
            border-radius: 14px;
            box-shadow: 0 6px 18px rgba(15, 23, 42, 0.05);
            padding: 0.9rem 0.95rem 0.75rem;
            margin-bottom: 1.35rem;
        }

        .intake-thanks-card-hd {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.85rem;
            padding-bottom: 0.65rem;
            border-bottom: 1px solid #f1f5f9;
        }

        .intake-thanks-card-hd-icon {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: #fee2e2;
            color: var(--ty-red);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            flex-shrink: 0;
        }

        .intake-thanks-card-hd-title {
            margin: 0;
            font-size: 0.88rem;
            font-weight: 700;
            color: var(--ty-ink);
        }

        .intake-thanks-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.75rem 0.65rem;
        }

        .intake-thanks-item {
            display: flex;
            align-items: flex-start;
            gap: 0.5rem;
            min-width: 0;
        }

        .intake-thanks-item-icon {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
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
            font-size: 0.62rem;
            font-weight: 500;
            color: var(--ty-label);
            margin-bottom: 0.1rem;
            line-height: 1.2;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        .intake-thanks-item-value {
            display: block;
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--ty-ink);
            line-height: 1.3;
            word-break: break-word;
        }
        .intake-thanks-item-value.is-id { color: var(--ty-red); font-weight: 700; }

        .intake-thanks-next { text-align: center; margin-bottom: 0.5rem; }

        .intake-thanks-next-title {
            margin: 0 0 0.3rem;
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--ty-ink);
        }

        .intake-thanks-next-accent {
            width: 32px;
            height: 2.5px;
            border-radius: 999px;
            background: var(--ty-red);
            margin: 0 auto 0.95rem;
        }

        .intake-thanks-steps {
            display: flex;
            flex-direction: column;
            gap: 0.55rem;
            margin-bottom: 0.95rem;
        }

        .intake-thanks-step {
            position: relative;
            display: flex;
            align-items: flex-start;
            gap: 0.7rem;
            text-align: left;
            border-radius: 12px;
            padding: 0.75rem 0.8rem;
            min-width: 0;
        }

        .intake-thanks-step.tone-blue { background: #eff6ff; }
        .intake-thanks-step.tone-green { background: #f0fdf4; }
        .intake-thanks-step.tone-purple { background: #f5f3ff; }

        .intake-thanks-step-num {
            position: absolute;
            top: 0.55rem;
            right: 0.55rem;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            font-size: 0.62rem;
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
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            flex-shrink: 0;
            margin: 0;
            line-height: 1;
            box-shadow: 0 1px 4px rgba(15, 23, 42, 0.06);
        }

        .intake-thanks-step.tone-blue .intake-thanks-step-icon { color: #2563eb; }
        .intake-thanks-step.tone-green .intake-thanks-step-icon { color: #16a34a; }
        .intake-thanks-step.tone-purple .intake-thanks-step-icon { color: #7c3aed; }

        .intake-thanks-step-copy { min-width: 0; padding-right: 1rem; }

        .intake-thanks-step-title {
            margin: 0 0 0.15rem;
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--ty-ink);
            line-height: 1.25;
        }

        .intake-thanks-step-text {
            margin: 0;
            font-size: 0.7rem;
            color: var(--ty-muted);
            line-height: 1.4;
        }

        .intake-thanks-arrow { display: none; }

        .intake-thanks-info {
            display: flex;
            align-items: flex-start;
            gap: 0.55rem;
            background: #eff6ff;
            border-radius: 10px;
            padding: 0.65rem 0.8rem;
            color: #1e3a5f;
            font-size: 0.72rem;
            font-weight: 500;
            margin-bottom: 0.75rem;
            text-align: left;
            line-height: 1.4;
        }

        .intake-thanks-info i {
            color: #2563eb;
            font-size: 0.9rem;
            flex-shrink: 0;
            margin-top: 0.05rem;
        }

        .intake-thanks-help {
            display: flex;
            align-items: flex-start;
            gap: 0.7rem;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 0.8rem 0.85rem;
            text-align: left;
            margin-bottom: 1.15rem;
        }

        .intake-thanks-help-icon {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: #fee2e2;
            color: var(--ty-red);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            flex-shrink: 0;
        }

        .intake-thanks-help-title {
            margin: 0 0 0.15rem;
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--ty-ink);
        }

        .intake-thanks-help-text {
            margin: 0;
            font-size: 0.72rem;
            color: #475569;
            line-height: 1.45;
        }

        .intake-thanks-help-text a {
            color: var(--ty-red);
            font-weight: 700;
            text-decoration: none;
            word-break: break-word;
        }

        .intake-thanks-help-text a:hover { text-decoration: underline; }

        .intake-thanks-actions { text-align: center; }

        .intake-thanks-home-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
            width: 100%;
            max-width: 280px;
            background: var(--ty-red);
            color: #fff !important;
            border: 0;
            border-radius: 10px;
            padding: 0.7rem 1.25rem;
            font-size: 0.82rem;
            font-weight: 700;
            text-decoration: none !important;
            box-shadow: 0 6px 16px rgba(225, 29, 46, 0.22);
            transition: background 0.15s ease, transform 0.15s ease;
        }

        .intake-thanks-home-btn:hover {
            background: #c91020;
            color: #fff !important;
            transform: translateY(-1px);
        }

        .intake-thanks-trust {
            margin: 0.85rem 0 0.2rem;
            font-size: 0.72rem;
            color: var(--ty-muted);
            font-weight: 500;
        }

        .intake-thanks-tagline {
            margin: 0;
            font-family: "Great Vibes", cursive;
            font-size: 1.25rem;
            color: var(--ty-red);
            line-height: 1.2;
        }

        .intake-thanks-tagline i {
            font-size: 0.7rem;
            margin-left: 0.15rem;
            vertical-align: middle;
        }

        .intake-thanks-error {
            max-width: 480px;
            margin: 1.5rem auto;
            text-align: center;
        }

        .intake-thanks-error .alert {
            font-size: 0.85rem;
        }

        @media (prefers-reduced-motion: reduce) {
            .intake-thanks-success-core,
            .intake-thanks-success-ring,
            .intake-thanks-deco,
            .intake-thanks-check-mark {
                animation: none !important;
            }
            .intake-thanks-check-mark { stroke-dashoffset: 0; }
            .intake-thanks-deco { opacity: 0.9; }
        }

        /* Tablet+ — roomier desktop without huge type */
        @media (min-width: 768px) {
            .intake-thanks-page { padding: 1.5rem 1.25rem 2.5rem; }
            .intake-thanks-logo { max-height: 56px; margin-bottom: 1rem; }
            .intake-thanks-success { width: 88px; height: 88px; margin-bottom: 1rem; }
            .intake-thanks-check { width: 40px; height: 40px; }
            .intake-thanks-title { font-size: 1.65rem; }
            .intake-thanks-sub { font-size: 0.9rem; margin-bottom: 1.35rem; }
            .intake-thanks-card { padding: 1.15rem 1.25rem 1rem; border-radius: 16px; }
            .intake-thanks-card-hd-title { font-size: 0.95rem; }
            .intake-thanks-grid {
                grid-template-columns: repeat(4, minmax(0, 1fr));
                gap: 1rem 0.85rem;
            }
            .intake-thanks-item-icon { width: 32px; height: 32px; font-size: 0.78rem; }
            .intake-thanks-item-label { font-size: 0.68rem; }
            .intake-thanks-item-value { font-size: 0.85rem; }
            .intake-thanks-next-title { font-size: 1.25rem; }
            .intake-thanks-steps {
                flex-direction: row;
                align-items: stretch;
                gap: 0.5rem;
            }
            .intake-thanks-step {
                flex: 1 1 0;
                flex-direction: column;
                align-items: center;
                text-align: center;
                padding: 1rem 0.75rem 0.9rem;
            }
            .intake-thanks-step-copy { padding-right: 0; }
            .intake-thanks-step-icon {
                width: auto;
                height: auto;
                background: transparent;
                box-shadow: none;
                font-size: 1.25rem;
                margin-bottom: 0.4rem;
            }
            .intake-thanks-step-title { font-size: 0.85rem; }
            .intake-thanks-step-text { font-size: 0.72rem; }
            .intake-thanks-arrow {
                display: flex;
                flex: 0 0 auto;
                align-self: center;
                color: #cbd5e1;
                font-size: 0.85rem;
            }
            .intake-thanks-info { font-size: 0.8rem; padding: 0.8rem 1rem; }
            .intake-thanks-help { padding: 1rem 1.1rem; }
            .intake-thanks-help-title { font-size: 0.9rem; }
            .intake-thanks-help-text { font-size: 0.8rem; }
            .intake-thanks-home-btn {
                width: auto;
                font-size: 0.88rem;
                padding: 0.75rem 1.5rem;
            }
            .intake-thanks-trust { font-size: 0.8rem; }
            .intake-thanks-tagline { font-size: 1.45rem; }
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
            <span class="intake-thanks-success-ring"></span>
            <span class="intake-thanks-success-ring r2"></span>
            <span class="intake-thanks-deco d1"></span>
            <span class="intake-thanks-deco d2"></span>
            <span class="intake-thanks-deco d3"></span>
            <span class="intake-thanks-deco d4"></span>
            <span class="intake-thanks-deco d5"></span>
            <div class="intake-thanks-success-core">
                <svg class="intake-thanks-check" viewBox="0 0 52 52" aria-hidden="true">
                    <circle class="intake-thanks-check-circle" cx="26" cy="26" r="22"></circle>
                    <path class="intake-thanks-check-mark" d="M15 27.5 L23 35 L38 18"></path>
                </svg>
            </div>
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
                    <div class="intake-thanks-step-copy">
                        <h3 class="intake-thanks-step-title">We Review Your Inquiry</h3>
                        <p class="intake-thanks-step-text">Our travel experts will carefully review your requirements.</p>
                    </div>
                </div>
                <div class="intake-thanks-arrow" aria-hidden="true"><i class="fas fa-chevron-right"></i></div>
                <div class="intake-thanks-step tone-green">
                    <span class="intake-thanks-step-num">2</span>
                    <div class="intake-thanks-step-icon"><i class="fas fa-clipboard-check"></i></div>
                    <div class="intake-thanks-step-copy">
                        <h3 class="intake-thanks-step-title">We Prepare the Best Options</h3>
                        <p class="intake-thanks-step-text">We'll curate personalized options and the best possible itinerary.</p>
                    </div>
                </div>
                <div class="intake-thanks-arrow" aria-hidden="true"><i class="fas fa-chevron-right"></i></div>
                <div class="intake-thanks-step tone-purple">
                    <span class="intake-thanks-step-num">3</span>
                    <div class="intake-thanks-step-icon"><i class="fas fa-paper-plane"></i></div>
                    <div class="intake-thanks-step-copy">
                        <h3 class="intake-thanks-step-title">We Get Back to You</h3>
                        <p class="intake-thanks-step-text">Our team will contact you shortly with the best offers.</p>
                    </div>
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
