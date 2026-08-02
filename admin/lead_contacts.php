<?php
session_start();
require_once __DIR__ . '/connection.php';
require_once __DIR__ . '/includes/lead_contacts_db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] != '1') {
    header('location:index.php');
    exit;
}

lcEnsureContactTables($conn);

$contacts = lcListAllContacts($conn);

$contactStats = [
    'total' => count($contacts),
    'profiled' => 0,
    'family_members' => 0,
    'with_family' => 0,
    'recent' => 0,
];
$recentCutoff = strtotime('-7 days');
foreach ($contacts as $contactRow) {
    if (!empty($contactRow['has_profile'])) {
        $contactStats['profiled']++;
    }
    $familyCount = (int) ($contactRow['family_count'] ?? 0);
    $contactStats['family_members'] += $familyCount;
    if ($familyCount > 0) {
        $contactStats['with_family']++;
    }
    $createdTs = !empty($contactRow['created_at']) ? strtotime((string) $contactRow['created_at']) : false;
    if ($createdTs !== false && $createdTs >= $recentCutoff) {
        $contactStats['recent']++;
    }
}

$avatarColors = [
    ['bg' => '#fce7f3', 'fg' => '#db2777'],
    ['bg' => '#dcfce7', 'fg' => '#16a34a'],
    ['bg' => '#dbeafe', 'fg' => '#2563eb'],
    ['bg' => '#ede9fe', 'fg' => '#7c3aed'],
    ['bg' => '#ffedd5', 'fg' => '#ea580c'],
    ['bg' => '#e0e7ff', 'fg' => '#4f46e5'],
];

function lcRelativeActivity(?string $createdAt): string
{
    $ts = $createdAt ? strtotime($createdAt) : false;
    if ($ts === false) {
        return '—';
    }
    $diff = time() - $ts;
    if ($diff < 60) {
        return 'Just now';
    }
    if ($diff < 3600) {
        $m = (int) floor($diff / 60);
        return $m . ' min' . ($m === 1 ? '' : 's') . ' ago';
    }
    if ($diff < 86400) {
        $h = (int) floor($diff / 3600);
        return $h . ' hour' . ($h === 1 ? '' : 's') . ' ago';
    }
    if ($diff < 86400 * 30) {
        $d = (int) floor($diff / 86400);
        return $d . ' day' . ($d === 1 ? '' : 's') . ' ago';
    }
    return date('d M Y', $ts);
}

require_once __DIR__ . '/includes/lead_contact_person_fields.php';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Lead Contacts</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php include 'includes/header-links.php'; ?>
    <style>
        .lead-contacts-page { --lc-red:#e11d2e; --lc-border:#e8eaed; --lc-muted:#6b7280; --lc-text:#111827; }
        .lead-contacts-page .content-wrapper,
        .lead-contacts-page .content-wrapper > .content { background:#f3f4f6; }
        .lead-contacts-page .content-header { display:none; }
        .lead-contacts-page .content { padding:1rem .75rem .75rem; }
        .lead-contacts-page .content > .container-fluid { padding:0 .65rem .65rem; max-width:100%; }

        .lead-contacts-page .page-title-row {
            display:flex; justify-content:space-between; align-items:flex-start;
            margin-bottom:1.15rem; flex-wrap:wrap; gap:1rem;
        }
        .lead-contacts-page .page-title { font-size:1.9rem; line-height:1.2; font-weight:700; color:var(--lc-text); margin:0 0 .25rem; letter-spacing:-.02em; }
        .lead-contacts-page .page-subtitle { margin:0; color:#6b7280; font-size:.98rem; }
        .lead-contacts-page .btn-add-contact {
            height:42px; display:inline-flex; align-items:center; gap:.45rem; padding:0 1rem;
            border:0; border-radius:10px; background:var(--lc-red);
            color:#fff; font-size:.92rem; font-weight:600; box-shadow:0 2px 8px rgba(225,29,46,.28);
        }
        .lead-contacts-page .btn-add-contact:hover { color:#fff; background:#c81a28; }
        .lead-contacts-page .btn-add-contact .lc-btn-caret { opacity:.85; font-size:.7rem; margin-left:.15rem; }

        .lead-contacts-page .contacts-card {
            background:#fff; border:1px solid var(--lc-border); border-radius:14px;
            box-shadow:0 1px 3px rgba(15,23,42,.05); overflow:hidden;
        }
        .lead-contacts-page .lc-toolbar {
            display:flex; flex-wrap:wrap; align-items:flex-end; gap:.7rem; padding:1rem 1.1rem;
            border-bottom:1px solid var(--lc-border);
        }
        .lead-contacts-page .lc-search { position:relative; flex:1 1 240px; min-width:220px; }
        .lead-contacts-page .lc-search i { position:absolute; left:13px; top:50%; transform:translateY(-50%); color:#9ca3af; }
        .lead-contacts-page .lc-search input { padding-left:2.35rem; }
        .lead-contacts-page .lc-filter { flex:0 1 140px; min-width:120px; }
        .lead-contacts-page .lc-filter.lc-filter-wide { flex:0 1 170px; }
        .lead-contacts-page .lc-filter label {
            display:block; margin:0 0 .28rem; color:#4b5563; font-size:.72rem; font-weight:700; letter-spacing:.01em;
        }
        .lead-contacts-page .lc-toolbar .form-control {
            height:40px; border:1px solid #d1d5db; border-radius:9px; color:#374151; font-size:.88rem; box-shadow:none;
            background:#fff;
        }
        .lead-contacts-page .lc-toolbar-actions { display:flex; gap:.5rem; margin-left:auto; align-items:flex-end; }
        .lead-contacts-page .lc-export-btn,
        .lead-contacts-page .lc-refresh-btn {
            height:40px; display:inline-flex; align-items:center; justify-content:center; gap:.4rem;
            padding:0 .95rem; border:1px solid #d1d5db; border-radius:9px;
            background:#fff; color:#374151; font-size:.86rem; font-weight:600;
        }
        .lead-contacts-page .lc-refresh-btn { color:var(--lc-red); border-color:#fecaca; }
        .lead-contacts-page .lc-refresh-btn:hover { background:#fff1f2; }
        .lead-contacts-page .lc-export-btn:hover { background:#f9fafb; }

        .lead-contacts-page .lc-summary-strip {
            display:grid; grid-template-columns:repeat(4,minmax(0,1fr));
            border-bottom:1px solid var(--lc-border); background:#fafbfc;
        }
        .lead-contacts-page .lc-summary-item {
            display:flex; align-items:center; gap:.7rem; padding:.85rem 1.1rem;
            border-right:1px solid var(--lc-border);
        }
        .lead-contacts-page .lc-summary-item:last-child { border-right:0; }
        .lead-contacts-page .lc-summary-item > i {
            width:28px; height:28px; display:inline-flex; align-items:center; justify-content:center;
            border-radius:8px; background:#ffe4e6; color:#e11d48; font-size:.85rem;
        }
        .lead-contacts-page .lc-summary-item:nth-child(2) > i { background:#ede9fe; color:#7c3aed; }
        .lead-contacts-page .lc-summary-item:nth-child(3) > i { background:#dcfce7; color:#16a34a; }
        .lead-contacts-page .lc-summary-item:nth-child(4) > i { background:#ffedd5; color:#ea580c; }
        .lead-contacts-page .lc-summary-label { color:#6b7280; font-size:.72rem; font-weight:500; }
        .lead-contacts-page .lc-summary-value { color:#1f2937; font-size:1rem; font-weight:700; }

        .lead-contacts-page .table-wrap { overflow-x:auto; }
        .lead-contacts-page table.contacts-table { width:100%; border-collapse:collapse; font-size:.92rem; min-width:1100px; }
        .lead-contacts-page table.contacts-table thead th {
            padding:.85rem .85rem; border:0; border-bottom:1px solid var(--lc-border);
            background:#f8fafc; color:#6b7280; font-size:.78rem; font-weight:700; white-space:nowrap;
            text-transform:none; letter-spacing:.01em;
        }
        .lead-contacts-page table.contacts-table thead th .lc-th-sort {
            display:inline-flex; align-items:center; gap:.3rem; color:inherit;
        }
        .lead-contacts-page table.contacts-table thead th .lc-th-sort i { font-size:.65rem; opacity:.55; }
        .lead-contacts-page table.contacts-table tbody td {
            padding:.9rem .85rem; border-bottom:1px solid #eef2f7; color:#4b5563; vertical-align:middle; white-space:nowrap;
        }
        .lead-contacts-page table.contacts-table tbody tr:hover { background:#f9fafb; }
        .lead-contacts-page .lc-check { width:18px; height:18px; margin:0; vertical-align:middle; }
        .lead-contacts-page .lc-contact-cell { display:flex; align-items:center; gap:.7rem; min-width:200px; }
        .lead-contacts-page .lc-avatar {
            width:38px; height:38px; flex:0 0 38px; display:inline-flex; align-items:center; justify-content:center;
            border-radius:50%; font-size:.82rem; font-weight:700;
        }
        .lead-contacts-page .lc-contact-name { color:var(--lc-text); font-size:.95rem; font-weight:700; line-height:1.25; }
        .lead-contacts-page .lc-contact-email { max-width:220px; overflow:hidden; text-overflow:ellipsis; color:#9ca3af; font-size:.8rem; margin-top:.1rem; }
        .lead-contacts-page .lc-contact-id { color:#4b5563; font-size:.88rem; font-weight:600; font-variant-numeric:tabular-nums; }
        .lead-contacts-page .lc-badge {
            display:inline-flex; align-items:center; padding:.28rem .65rem; border-radius:999px;
            background:#eafaf0; color:#16a34a; font-size:.78rem; font-weight:600;
        }
        .lead-contacts-page .lc-badge.source-lead { background:#eff6ff; color:#2563eb; }
        .lead-contacts-page .lc-badge.source-manual { background:#fff7ed; color:#ea580c; }
        .lead-contacts-page .lc-profile-status { background:#ecfdf5; color:#059669; }
        .lead-contacts-page .lc-profile-status.pending { background:#fff7ed; color:#d97706; }
        .lead-contacts-page .lc-family-count { display:inline-flex; align-items:center; gap:.4rem; color:#4b5563; font-size:.9rem; }
        .lead-contacts-page .lc-family-count i { color:#9ca3af; }
        .lead-contacts-page .lc-activity {
            display:inline-flex; align-items:center; gap:.4rem; color:#4b5563; font-size:.88rem;
        }
        .lead-contacts-page .lc-activity-dot {
            width:8px; height:8px; border-radius:50%; background:#22c55e; flex:0 0 auto;
        }
        .lead-contacts-page .lc-actions { display:inline-flex; align-items:center; gap:2px; flex-wrap:nowrap; }
        .lead-contacts-page .lc-actions .btn {
            width:32px; height:32px; display:inline-flex; align-items:center; justify-content:center;
            padding:0; border:0; border-radius:8px; background:transparent; color:#6b7280; font-size:.88rem;
        }
        .lead-contacts-page .lc-actions .btn:hover { background:#fff1f2; color:var(--lc-red); }
        .lead-contacts-page .lc-actions .js-lc-whatsapp:hover { background:#ecfdf5; color:#16a34a; }
        .lead-contacts-page .lc-more { position:relative; display:inline-block; }
        .lead-contacts-page .lc-more-menu {
            display:none; position:absolute; right:0; top:calc(100% + 4px); z-index:20;
            min-width:160px; padding:.35rem 0; border:1px solid var(--lc-border); border-radius:10px;
            background:#fff; box-shadow:0 8px 24px rgba(15,23,42,.12);
        }
        .lead-contacts-page .lc-more.open .lc-more-menu { display:block; }
        .lead-contacts-page .lc-more-menu button {
            display:flex; width:100%; align-items:center; gap:.5rem; border:0; background:transparent;
            padding:.5rem .85rem; color:#374151; font-size:.86rem; text-align:left;
        }
        .lead-contacts-page .lc-more-menu button:hover { background:#f9fafb; }
        .lead-contacts-page .lc-more-menu button.text-danger { color:#e11d48; }

        .lead-contacts-page .lc-table-footer {
            display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap;
            gap:.85rem; padding:.9rem 1.1rem; border-top:1px solid var(--lc-border); background:#fff;
        }
        .lead-contacts-page .lc-footer-info { color:#6b7280; font-size:.86rem; }
        .lead-contacts-page .lc-footer-right { display:flex; align-items:center; gap:.85rem; flex-wrap:wrap; }
        .lead-contacts-page .lc-per-page {
            display:inline-flex; align-items:center; gap:.45rem; color:#6b7280; font-size:.84rem;
        }
        .lead-contacts-page .lc-per-page select {
            height:34px; border:1px solid #e5e7eb; border-radius:8px; padding:0 .5rem; background:#fff; color:#374151;
        }
        .lead-contacts-page .lc-pagination { display:flex; gap:.3rem; align-items:center; }
        .lead-contacts-page .lc-page-btn {
            min-width:34px; height:34px; padding:0 .55rem; border:1px solid #e5e7eb;
            border-radius:8px; background:#fff; color:#4b5563; font-size:.84rem;
            display:inline-flex; align-items:center; justify-content:center;
        }
        .lead-contacts-page .lc-page-btn.active { background:var(--lc-red); border-color:var(--lc-red); color:#fff; }
        .lead-contacts-page .lc-page-btn:disabled { opacity:.4; cursor:not-allowed; }

        .lc-preview a { display:inline-block; margin-top:4px; }
        .lc-preview img { max-height:48px; border-radius:4px; border:1px solid #dee2e6; }
        .lc-section-label {
            font-size:.8rem; font-weight:700; text-transform:uppercase; letter-spacing:.06em;
            color:#6c757d; margin:1rem 0 .5rem; padding-bottom:.35rem; border-bottom:1px solid #eef1f4;
        }

        /* Contact & family modal */
        #lcFamilyModal .modal-dialog { max-width:980px; }
        #lcFamilyModal .modal-content {
            border:0; border-radius:20px; overflow:hidden;
            box-shadow:0 20px 50px rgba(15,23,42,.18);
        }
        #lcFamilyModal .modal-content::before {
            background: linear-gradient(90deg, #e11d2e 0%, #ef4444 50%, #f87171 100%);
        }
        #lcFamilyModal .modal-header {
            display:flex; align-items:flex-start; gap:.85rem;
            border:0; padding:1.25rem 1.35rem 0.85rem; background:#fff;
        }
        #lcFamilyModal .lc-modal-brand-icon {
            width:46px; height:46px; flex:0 0 46px; border-radius:50%;
            display:inline-flex; align-items:center; justify-content:center;
            background:var(--lc-red); color:#fff; font-size:1.1rem;
            box-shadow:0 6px 16px rgba(225,29,46,.28);
        }
        #lcFamilyModal .lc-modal-head-text { flex:1 1 auto; min-width:0; padding-top:.1rem; }
        #lcFamilyModal .lc-modal-title {
            margin:0; color:#111827; font-size:1.35rem; font-weight:700; line-height:1.25;
        }
        #lcFamilyModal .lc-modal-subtitle {
            margin:.2rem 0 0; color:#6b7280; font-size:.9rem;
        }
        #lcFamilyModal .modal-header .close {
            margin:0; padding:.25rem; opacity:.55; font-size:1.5rem; line-height:1;
        }
        #lcFamilyModal .modal-header .close:hover { opacity:.9; }
        #lcFamilyModal .modal-body { padding:0.85rem 1.35rem 1.1rem; }
        #lcFamilyModal .lc-primary-card {
            position:relative; overflow:hidden;
            display:flex; align-items:center; gap:1rem; flex-wrap:wrap;
            padding:1.15rem 1.2rem; margin-bottom:1.25rem;
            border:1px solid #fecaca; border-radius:16px;
            background:linear-gradient(120deg, #fff1f2 0%, #ffe4e6 55%, #fff5f5 100%);
        }
        #lcFamilyModal .lc-primary-card::after {
            content:""; position:absolute; right:-30px; top:-40px; width:180px; height:180px;
            border-radius:50%; background:radial-gradient(circle, rgba(248,113,113,.22), transparent 70%);
            pointer-events:none;
        }
        #lcFamilyModal .lc-primary-card::before {
            content:""; position:absolute; right:40px; bottom:-50px; width:140px; height:140px;
            border-radius:50%; background:radial-gradient(circle, rgba(239,68,68,.12), transparent 70%);
            pointer-events:none;
        }
        #lcFamilyModal .lc-primary-avatar {
            position:relative; z-index:1;
            width:64px; height:64px; flex:0 0 64px; border-radius:50%;
            display:inline-flex; align-items:center; justify-content:center;
            background:var(--lc-red); color:#fff; font-size:1.25rem; font-weight:700;
            box-shadow:0 6px 16px rgba(225,29,46,.3);
        }
        #lcFamilyModal .lc-primary-meta { position:relative; z-index:1; flex:1 1 220px; min-width:0; }
        #lcFamilyModal .lc-primary-badge {
            display:inline-flex; align-items:center; gap:.35rem;
            padding:.22rem .6rem; margin-bottom:.4rem; border-radius:999px;
            background:#fecaca; color:#b91c1c; font-size:.72rem; font-weight:700;
        }
        #lcFamilyModal .lc-primary-name {
            display:inline-flex; align-items:center; gap:.4rem;
            margin:0 0 .4rem; color:#111827; font-size:1.25rem; font-weight:700; line-height:1.25;
        }
        #lcFamilyModal .lc-primary-name .lc-verified {
            width:18px; height:18px; border-radius:50%; display:inline-flex;
            align-items:center; justify-content:center; background:var(--lc-red); color:#fff; font-size:.65rem;
        }
        #lcFamilyModal .lc-primary-details {
            display:flex; flex-wrap:wrap; gap:.4rem 1rem; color:#4b5563; font-size:.9rem;
        }
        #lcFamilyModal .lc-primary-details span { display:inline-flex; align-items:center; gap:.4rem; }
        #lcFamilyModal .lc-primary-details i { color:var(--lc-red); width:14px; text-align:center; }
        #lcFamilyModal .lc-primary-actions { position:relative; z-index:1; display:flex; gap:.4rem; margin-left:auto; }
        #lcFamilyModal .lc-primary-actions .btn {
            border-color:#fca5a5; color:var(--lc-red); background:#fff; font-weight:600; border-radius:10px;
        }
        #lcFamilyModal .lc-primary-actions .btn:hover { background:#fff1f2; color:#b91c1c; border-color:#f87171; }
        #lcFamilyModal .lc-members-hd {
            display:flex; align-items:center; justify-content:space-between; gap:.5rem;
            margin-bottom:.75rem;
        }
        #lcFamilyModal .lc-members-hd h6 {
            margin:0; color:#111827; font-size:1rem; font-weight:700;
            display:inline-flex; align-items:center; gap:.45rem;
        }
        #lcFamilyModal .lc-members-hd h6 i { color:var(--lc-red); }
        #lcFamilyModal .lc-members-count {
            color:#6b7280; font-size:.85rem; font-weight:600;
        }
        #lcFamilyModal .lc-member-list { display:flex; flex-direction:column; gap:.65rem; }
        #lcFamilyModal .lc-member-item {
            display:flex; align-items:center; gap:.85rem; flex-wrap:wrap;
            padding:.85rem .95rem; border:1px solid #e5e7eb; border-radius:14px; background:#fff;
            transition:border-color .15s ease, box-shadow .15s ease;
        }
        #lcFamilyModal .lc-member-item:hover {
            border-color:#fecaca; box-shadow:0 4px 14px rgba(225,29,46,.06);
        }
        #lcFamilyModal .lc-member-avatar {
            width:44px; height:44px; flex:0 0 44px; border-radius:50%;
            display:inline-flex; align-items:center; justify-content:center;
            background:linear-gradient(145deg, #ef4444, #fb7185); color:#fff; font-size:.88rem; font-weight:700;
        }
        #lcFamilyModal .lc-member-info { flex:1 1 180px; min-width:0; }
        #lcFamilyModal .lc-member-name { color:#111827; font-size:.98rem; font-weight:700; }
        #lcFamilyModal .lc-member-sub { color:#6b7280; font-size:.84rem; margin-top:.2rem; }
        #lcFamilyModal .lc-member-actions { display:flex; gap:.35rem; margin-left:auto; }
        #lcFamilyModal .lc-member-actions .btn {
            width:34px; height:34px; padding:0; border-radius:9px; border:1px solid #e5e7eb;
            background:#fff; color:#6b7280; display:inline-flex; align-items:center; justify-content:center;
        }
        #lcFamilyModal .lc-member-actions .btn:hover { background:#fff1f2; color:var(--lc-red); border-color:#fecaca; }
        #lcFamilyModal .lc-member-actions .js-lc-del-family { color:#e11d48; }
        #lcFamilyModal .lc-member-actions .js-lc-del-family:hover { background:#fef2f2; border-color:#fecaca; }
        #lcFamilyModal .lc-member-empty {
            padding:1.2rem; border:1px dashed #fca5a5; border-radius:12px;
            text-align:center; color:#6b7280; background:#fff7f7; font-size:.9rem;
        }
        #lcFamilyModal .lc-pax-add-wrap {
            display:none; margin-top:1.15rem; padding:1rem 1.05rem;
            border:1px solid #fecaca; border-radius:14px; background:#fff7f7;
        }
        #lcFamilyModal .lc-pax-add-wrap.is-open { display:block; }
        #lcFamilyModal .lc-pax-add-title {
            display:flex; align-items:center; gap:.45rem;
            margin:0 0 .75rem; color:#111827; font-size:.95rem; font-weight:700;
        }
        #lcFamilyModal .lc-pax-add-title i {
            width:26px; height:26px; border-radius:7px; display:inline-flex;
            align-items:center; justify-content:center; border:1px dashed #f87171; color:var(--lc-red); font-size:.8rem;
        }
        #lcFamilyModal .lc-pax-rows { display:flex; flex-direction:column; gap:.65rem; }
        #lcFamilyModal .lc-pax-row {
            display:grid;
            grid-template-columns:110px 80px minmax(120px,1.2fr) 128px 110px minmax(120px,1fr) auto auto;
            gap:.55rem .55rem; align-items:end;
        }
        #lcFamilyModal .lc-pax-row.is-extra > div > label {
            visibility:hidden; height:0; margin:0; overflow:hidden; padding:0;
        }
        #lcFamilyModal .lc-pax-row label {
            display:block; margin:0 0 .28rem; color:#374151; font-size:.78rem; font-weight:700;
        }
        #lcFamilyModal .lc-pax-row .lc-pax-age {
            margin-left:.35rem; color:var(--lc-red); font-weight:600; font-size:.72rem;
        }
        #lcFamilyModal .lc-pax-row .form-control {
            height:38px; padding:.25rem .55rem; font-size:.88rem; border-color:#e5e7eb; border-radius:10px;
        }
        #lcFamilyModal .lc-pax-row .form-control:focus {
            border-color:#fca5a5; box-shadow:0 0 0 .15rem rgba(225,29,46,.12);
        }
        #lcFamilyModal .lc-attach-btn,
        #lcFamilyModal .lc-pax-add-submit {
            width:38px; height:38px; padding:0; display:inline-flex;
            align-items:center; justify-content:center; border-radius:10px; font-size:1.05rem;
        }
        #lcFamilyModal .lc-attach-btn {
            border:1px solid #e5e7eb; background:#fff; color:#6b7280;
        }
        #lcFamilyModal .lc-attach-btn.has-file { border-color:#fca5a5; color:var(--lc-red); background:#fff1f2; }
        #lcFamilyModal .lc-pax-add-submit { background:var(--lc-red); border-color:var(--lc-red); color:#fff; }
        #lcFamilyModal .lc-pax-add-submit:hover { background:#c81a28; border-color:#c81a28; color:#fff; }
        #lcFamilyModal .lc-pax-add-submit.is-remove {
            background:#fff; border:1px solid #fecaca; color:#e11d48;
        }
        #lcFamilyModal .lc-pax-add-submit.is-remove:hover {
            background:#fef2f2; border-color:#f87171; color:#b91c1c;
        }
        #lcFamilyModal .lc-pax-attach-name {
            grid-column:1 / -1; margin:0; color:#6b7280; font-size:.78rem;
        }
        #lcFamilyModal .lc-pax-err {
            margin:0; color:#dc2626; font-size:.82rem;
        }
        #lcFamilyModal .lc-member-thumbs {
            display:flex; gap:.35rem; flex-wrap:wrap; margin-top:.4rem;
        }
        #lcFamilyModal .lc-member-thumbs a {
            display:inline-block; width:40px; height:40px; border-radius:8px; overflow:hidden;
            border:1px solid #e5e7eb; background:#fff;
        }
        #lcFamilyModal .lc-member-thumbs img {
            width:100%; height:100%; object-fit:cover;
        }
        #lcFamilyModal .lc-member-thumbs .lc-thumb-pdf {
            display:inline-flex; align-items:center; justify-content:center;
            width:40px; height:40px; color:#dc2626; font-size:.8rem; background:#fef2f2;
        }
        #lcFamilyModal .modal-footer.lc-family-footer {
            display:flex; justify-content:center; align-items:center; gap:.75rem; flex-wrap:wrap;
            padding:1rem 1.25rem 1.25rem; border-top:1px solid #f3f4f6; background:#fff;
        }
        #lcFamilyModal .modal-footer.lc-family-footer .btn {
            min-width:160px; height:42px; border-radius:12px; font-weight:600;
        }
        #lcFamilyModal .modal-footer.lc-family-footer .btn-outline-secondary {
            border-color:#d1d5db; color:#111827; background:#fff;
        }
        #lcFamilyModal .modal-footer.lc-family-footer #lcFamilyModalAddBtn {
            background:var(--lc-red); border-color:var(--lc-red); color:#fff;
            box-shadow:0 6px 16px rgba(225,29,46,.25);
        }
        #lcFamilyModal .modal-footer.lc-family-footer #lcFamilyModalAddBtn:hover {
            background:#c81a28; border-color:#c81a28; color:#fff;
        }

        /* Add / Edit Contact modal (supplier-style) */
        #lcProfileModal .lc-profile-dialog {
            max-width: 920px; width: calc(100% - 1.5rem); margin: 1rem auto;
        }
        #lcProfileModal .lc-profile-content {
            border: 0; border-radius: 4px; overflow: hidden;
            box-shadow: 0 18px 50px rgba(15,23,42,.22);
            max-height: calc(100vh - 2rem); display: flex; flex-direction: column;
        }
        #lcProfileModal #lcProfileForm {
            min-height: 0; display: flex; flex: 1 1 auto; flex-direction: column;
        }
        #lcProfileModal .lc-profile-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 1.1rem 1.35rem .95rem; background: #fff; border-bottom: 1px solid #e5e7eb;
        }
        #lcProfileModal .lc-profile-header .modal-title {
            color: #64748b; font-size: 1.15rem; font-weight: 600; letter-spacing: .01em;
        }
        #lcProfileModal .lc-profile-header .close {
            padding: 0; margin: 0; font-size: 1.55rem; font-weight: 400;
            color: #94a3b8; opacity: 1; text-shadow: none; line-height: 1;
        }
        #lcProfileModal .lc-profile-header .close:hover { color: #64748b; }
        #lcProfileModal .lc-profile-body {
            min-height: 0; flex: 1 1 auto; overflow-y: auto;
            padding: 1.15rem 1.35rem 1rem; background: #fff;
        }
        #lcProfileModal .lc-profile-body label {
            display: block; margin: 0 0 .35rem; color: #94a3b8;
            font-size: .82rem; font-weight: 500;
        }
        #lcProfileModal .lc-profile-body .form-control {
            height: 40px; border: 1px solid #e2e8f0; border-radius: 4px;
            color: #0f172a; font-size: .9rem; box-shadow: none; background: #fff;
        }
        #lcProfileModal .lc-profile-body .form-control:focus {
            border-color: #94a3b8; box-shadow: none;
        }
        #lcProfileModal .lc-profile-body .form-group { margin-bottom: .95rem; }
        #lcProfileModal .lc-profile-body .form-control-file {
            font-size: .82rem; color: #64748b;
        }
        #lcProfileModal .lc-contact-row {
            margin: 0 0 .15rem;
        }
        #lcProfileModal .lc-contact-action-wrap {
            display: flex; flex-direction: column;
        }
        #lcProfileModal .lc-contact-action-label { height: 1.15rem; }
        #lcProfileModal .lc-btn-contact-action {
            width: 40px; height: 40px; padding: 0; display: inline-flex;
            align-items: center; justify-content: center;
            border: 1px solid #cbd5e1; border-radius: 4px;
            background: #fff; color: #0f172a; font-size: .95rem;
        }
        #lcProfileModal .lc-btn-contact-action:hover {
            background: #f8fafc; color: #0f172a;
        }
        #lcProfileModal .lc-btn-contact-action.is-remove {
            background: #e11d48; border-color: #e11d48; color: #fff;
        }
        #lcProfileModal .lc-btn-contact-action.is-remove:hover {
            background: #be123c; border-color: #be123c; color: #fff;
        }
        #lcProfileModal .lc-city-wrap { position: relative; }
        #lcProfileModal .lc-city-dropdown {
            position: absolute; z-index: 1060; left: 0; right: 0; top: 100%;
            background: #fff; border: 1px solid #e2e8f0; border-radius: 4px;
            max-height: 190px; overflow-y: auto; display: none;
            box-shadow: 0 10px 25px rgba(15,23,42,.12);
        }
        #lcProfileModal .lc-city-dropdown .item {
            padding: .45rem .65rem; cursor: pointer; font-size: .875rem; color: #334155;
        }
        #lcProfileModal .lc-city-dropdown .item:hover { background: #f1f5f9; }
        #lcProfileModal .lc-profile-footer {
            display: flex; justify-content: flex-start; align-items: center; gap: .55rem;
            padding: .95rem 1.35rem 1.15rem; border-top: 1px solid #e5e7eb;
            background: #fff;
        }
        #lcProfileModal .lc-btn-save {
            min-width: 88px; height: 38px; padding: 0 1.1rem; border: 0; border-radius: 4px;
            background: #1abc9c; color: #fff; font-weight: 600; font-size: .92rem;
        }
        #lcProfileModal .lc-btn-save:hover { background: #16a085; color: #fff; }
        #lcProfileModal .lc-btn-save:disabled { opacity: .7; }
        #lcProfileModal .lc-btn-cancel {
            min-width: 88px; height: 38px; padding: 0 1.1rem; border-radius: 4px;
            background: #fff; border: 1px solid #cbd5e1; color: #334155; font-weight: 500; font-size: .92rem;
        }
        #lcProfileModal .lc-btn-cancel:hover { background: #f8fafc; }
        @media (max-width: 767.98px) {
            #lcProfileModal .lc-profile-dialog { width: calc(100% - 1rem); margin: .5rem auto; }
            #lcProfileModal .lc-contact-action-wrap { align-items: flex-start; }
            #lcProfileModal .lc-btn-contact-action { margin-top: 0; }
        }

        /* Attachments modal */
        #lcAttachModal .lc-attach-grid {
            display:grid; grid-template-columns:repeat(auto-fill,minmax(160px,1fr)); gap:.75rem;
        }
        #lcAttachModal .lc-attach-tile {
            position:relative; border:1px solid #e5e7eb; border-radius:10px; background:#fafafa;
            min-height:160px; display:flex; flex-direction:column; overflow:hidden;
        }
        #lcAttachModal .lc-attach-tile-preview {
            flex:1; display:flex; align-items:center; justify-content:center;
            min-height:110px; background:#fff; padding:.4rem; cursor:pointer;
        }
        #lcAttachModal .lc-attach-tile-preview img {
            max-width:100%; max-height:120px; object-fit:contain; border-radius:4px;
        }
        #lcAttachModal .lc-attach-tile-preview .lc-pdf-box {
            text-align:center; color:#dc2626; font-size:.85rem; font-weight:600;
            word-break:break-word; padding:0 .35rem;
        }
        #lcAttachModal .lc-attach-tile-meta {
            padding:.45rem .5rem; border-top:1px solid #eef2f7; color:#4b5563; font-size:.75rem;
            display:flex; flex-direction:column; gap:.4rem;
        }
        #lcAttachModal .lc-attach-file-name {
            display:block; color:#111827; font-weight:600; font-size:.78rem; line-height:1.25;
            white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:100%;
        }
        #lcAttachModal .lc-attach-actions {
            display:flex; align-items:center; justify-content:flex-end; gap:.3rem;
        }
        #lcAttachModal .lc-attach-actions .btn {
            width:30px; height:30px; padding:0; display:inline-flex; align-items:center; justify-content:center;
            border-radius:8px; border:1px solid #e5e7eb; background:#fff; color:#4b5563; font-size:.82rem;
        }
        #lcAttachModal .lc-attach-actions .btn:hover { background:#f9fafb; color:#111827; border-color:#d1d5db; }
        #lcAttachModal .lc-attach-actions .js-lc-attach-download:hover { color:#2563eb; border-color:#93c5fd; background:#eff6ff; }
        #lcAttachModal .lc-attach-actions .js-lc-attach-edit:hover { color:#d97706; border-color:#fcd34d; background:#fffbeb; }
        #lcAttachModal .lc-attach-actions .js-lc-attach-delete:hover,
        #lcAttachModal .lc-attach-actions .js-lc-remove-pending:hover {
            color:#dc2626; border-color:#fecaca; background:#fef2f2;
        }
        #lcAttachModal .lc-attach-empty {
            grid-column:1 / -1; text-align:center; color:#6b7280; padding:1.5rem;
            border:1px dashed #d1d5db; border-radius:10px; background:#fafafa;
        }
        #lcAttachModal .lc-attach-add-zone {
            margin-top:1rem; padding:1rem; border:1px dashed #93c5fd; border-radius:10px;
            background:#eff6ff; text-align:center;
        }
        #lcAttachModal .lc-attach-add-zone p { margin:0 0 .65rem; color:#1e40af; font-size:.9rem; }

        /* Attachment viewer */
        #lcAttachViewerModal .modal-dialog { max-width:920px; }
        #lcAttachViewerModal .lc-viewer-toolbar {
            display:flex; align-items:center; justify-content:space-between; gap:.75rem; flex-wrap:wrap;
            margin-bottom:.85rem;
        }
        #lcAttachViewerModal .lc-viewer-filename {
            font-weight:700; color:#111827; font-size:.95rem; word-break:break-all;
        }
        #lcAttachViewerModal .lc-viewer-frame {
            min-height:420px; max-height:70vh; border:1px solid #e5e7eb; border-radius:12px;
            background:#111827; display:flex; align-items:center; justify-content:center; overflow:auto;
        }
        #lcAttachViewerModal .lc-viewer-frame img {
            max-width:100%; max-height:68vh; object-fit:contain;
        }
        #lcAttachViewerModal .lc-viewer-frame iframe {
            width:100%; height:68vh; border:0; background:#fff;
        }
        #lcAttachViewerModal .lc-viewer-pdf-fallback {
            color:#fff; text-align:center; padding:2rem;
        }
        @media (max-width:991.98px) {
            #lcFamilyModal .lc-pax-row {
                grid-template-columns:1fr 1fr;
            }
            #lcFamilyModal .lc-pax-row .lc-pax-name,
            #lcFamilyModal .lc-pax-row .lc-pax-email,
            #lcFamilyModal .lc-pax-row .lc-pax-dob { grid-column:1 / -1; }
        }
        @media (max-width:575.98px) {
            #lcFamilyModal .lc-pax-row { grid-template-columns:1fr; }
            #lcFamilyModal .lc-primary-actions { margin-left:0; width:100%; }
        }
        @media (max-width:1199.98px) {
            .lead-contacts-page .lc-toolbar-actions { margin-left:0; width:100%; }
        }
        @media (max-width:767.98px) {
            .lead-contacts-page .lc-summary-strip { grid-template-columns:1fr; }
            .lead-contacts-page .lc-summary-item { border-right:0; border-bottom:1px solid var(--lc-border); }
            .lead-contacts-page .lc-filter { flex:1 1 calc(50% - .5rem); }
        }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
    <div class="wrapper lead-contacts-page">
        <?php include 'includes/top-header.php'; ?>
        <?php include 'includes/sidebar.php'; ?>

        <div class="content-wrapper">
            <?php include 'includes/page-header.php'; ?>

            <section class="content">
                <div class="container-fluid">
                    <div class="page-title-row">
                        <div>
                            <h1 class="page-title">Contacts</h1>
                            <p class="page-subtitle">Manage customer profiles, family members and contact information</p>
                        </div>
                        <button type="button" class="btn btn-add-contact" id="lcAddContactBtn">
                            <i class="fas fa-plus"></i> Add Contact
                            <i class="fas fa-chevron-down lc-btn-caret"></i>
                        </button>
                    </div>

                    <div id="lcAlert"></div>

                    <div class="contacts-card">
                        <div class="lc-toolbar">
                            <div class="lc-search">
                                <i class="fas fa-search"></i>
                                <input type="search" class="form-control" id="lcContactSearch" placeholder="Search contacts by name, email or mobile...">
                            </div>
                            <div class="lc-filter">
                                <label for="lcSourceFilter">Source</label>
                                <select class="form-control" id="lcSourceFilter">
                                    <option value="">All Sources</option>
                                    <option value="lead">Lead</option>
                                    <option value="manual">Manual</option>
                                </select>
                            </div>
                            <div class="lc-filter">
                                <label for="lcProfileFilter">Profile Status</label>
                                <select class="form-control" id="lcProfileFilter">
                                    <option value="">All Profiles</option>
                                    <option value="complete">Completed</option>
                                    <option value="pending">Pending</option>
                                </select>
                            </div>
                            <div class="lc-filter">
                                <label for="lcFamilyFilter">Family</label>
                                <select class="form-control" id="lcFamilyFilter">
                                    <option value="">All</option>
                                    <option value="with">With family</option>
                                    <option value="without">No family</option>
                                </select>
                            </div>
                            <div class="lc-filter">
                                <label for="lcDateFilter">Date Range</label>
                                <select class="form-control" id="lcDateFilter">
                                    <option value="">All Time</option>
                                    <option value="7">Last 7 days</option>
                                    <option value="30">Last 30 days</option>
                                    <option value="90">Last 90 days</option>
                                </select>
                            </div>
                            <div class="lc-filter lc-filter-wide">
                                <label for="lcSortFilter">Sort By</label>
                                <select class="form-control" id="lcSortFilter">
                                    <option value="newest">Added On (Newest)</option>
                                    <option value="oldest">Added On (Oldest)</option>
                                    <option value="name_asc">Name (A–Z)</option>
                                    <option value="name_desc">Name (Z–A)</option>
                                    <option value="family_desc">Family (High–Low)</option>
                                </select>
                            </div>
                            <div class="lc-toolbar-actions">
                                <button type="button" class="lc-export-btn" id="lcExportContacts"><i class="fas fa-upload"></i> Export</button>
                                <button type="button" class="lc-refresh-btn" id="lcRefreshContacts"><i class="fas fa-sync-alt"></i> Refresh</button>
                            </div>
                        </div>

                        <div class="lc-summary-strip">
                            <div class="lc-summary-item"><i class="far fa-address-book"></i><div><div class="lc-summary-label">Total Contacts</div><div class="lc-summary-value" id="lcSumTotal"><?= number_format($contactStats['total']) ?></div></div></div>
                            <div class="lc-summary-item"><i class="fas fa-users"></i><div><div class="lc-summary-label">With Family</div><div class="lc-summary-value" id="lcSumFamily"><?= number_format($contactStats['with_family']) ?></div></div></div>
                            <div class="lc-summary-item"><i class="fas fa-user-shield"></i><div><div class="lc-summary-label">Profiles Completed</div><div class="lc-summary-value" id="lcSumProfiled"><?= number_format($contactStats['profiled']) ?></div></div></div>
                            <div class="lc-summary-item"><i class="far fa-clock"></i><div><div class="lc-summary-label">Recently Added</div><div class="lc-summary-value" id="lcSumRecent"><?= number_format($contactStats['recent']) ?></div></div></div>
                        </div>

                        <div class="table-wrap">
                            <table class="contacts-table" id="contactsTable">
                                <thead>
                                    <tr>
                                        <th style="width:42px;"><input type="checkbox" class="lc-check" id="lcSelectAll" title="Select all"></th>
                                        <th><span class="lc-th-sort">Contact ID <i class="fas fa-sort"></i></span></th>
                                        <th><span class="lc-th-sort">Contact <i class="fas fa-sort"></i></span></th>
                                        <th><span class="lc-th-sort">Mobile <i class="fas fa-sort"></i></span></th>
                                        <th><span class="lc-th-sort">Source <i class="fas fa-sort"></i></span></th>
                                        <th><span class="lc-th-sort">Family Members <i class="fas fa-sort"></i></span></th>
                                        <th><span class="lc-th-sort">Added On <i class="fas fa-sort"></i></span></th>
                                        <th><span class="lc-th-sort">Profile Status <i class="fas fa-sort"></i></span></th>
                                        <th><span class="lc-th-sort">Last Activity <i class="fas fa-sort"></i></span></th>
                                        <th class="text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($contacts)) { ?>
                                        <tr><td colspan="10" class="text-center text-muted py-4">No contacts yet. Add a contact or create a lead in CRM.</td></tr>
                                    <?php } else { ?>
                                        <?php foreach ($contacts as $index => $c) {
                                            $name = trim((string) ($c['customer_name'] ?? ''));
                                            $fc = (int) ($c['family_count'] ?? 0);
                                            $hasProf = !empty($c['has_profile']);
                                            $source = (string) ($c['source'] ?? 'lead');
                                            $refId = (int) ($c['ref_id'] ?? 0);
                                            $phone = (string) ($c['customer_phone'] ?? '');
                                            $email = (string) ($c['customer_email'] ?? '');
                                            $createdAt = (string) ($c['created_at'] ?? '');
                                            $initials = strtoupper(substr(preg_replace('/^(Mr|Mrs|Ms|Master|Miss)\.?\s+/i', '', $name !== '' ? $name : 'C'), 0, 1));
                                            $contactId = ($source === 'manual' ? 'C' : 'L') . str_pad((string) $refId, 5, '0', STR_PAD_LEFT);
                                            $addedOn = $createdAt !== '' ? date('d M Y', strtotime($createdAt)) : '—';
                                            $activity = lcRelativeActivity($createdAt);
                                            $av = $avatarColors[$index % count($avatarColors)];
                                            $waPhone = preg_replace('/\D+/', '', $phone);
                                            if (strlen($waPhone) === 10) {
                                                $waPhone = '91' . $waPhone;
                                            }
                                            $createdTs = $createdAt !== '' ? (int) strtotime($createdAt) : 0;
                                        ?>
                                            <tr data-source="<?= htmlspecialchars($source, ENT_QUOTES, 'UTF-8') ?>"
                                                data-profile="<?= $hasProf ? 'complete' : 'pending' ?>"
                                                data-family="<?= $fc > 0 ? 'with' : 'without' ?>"
                                                data-family-count="<?= $fc ?>"
                                                data-created="<?= $createdTs ?>"
                                                data-name="<?= htmlspecialchars(strtolower($name), ENT_QUOTES, 'UTF-8') ?>"
                                                data-ref-id="<?= $refId ?>"
                                                data-search="<?= htmlspecialchars(strtolower($name . ' ' . $phone . ' ' . $email . ' ' . $contactId), ENT_QUOTES, 'UTF-8') ?>">
                                                <td><input type="checkbox" class="lc-check lc-row-check"></td>
                                                <td><span class="lc-contact-id"><?= htmlspecialchars($contactId) ?></span></td>
                                                <td>
                                                    <div class="lc-contact-cell">
                                                        <span class="lc-avatar" style="background:<?= $av['bg'] ?>;color:<?= $av['fg'] ?>;"><?= htmlspecialchars($initials) ?></span>
                                                        <div>
                                                            <div class="lc-contact-name"><?= htmlspecialchars($name !== '' ? $name : '—', ENT_QUOTES, 'UTF-8') ?></div>
                                                            <div class="lc-contact-email"><?= htmlspecialchars($email !== '' ? $email : 'No email', ENT_QUOTES, 'UTF-8') ?></div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><?= htmlspecialchars($phone !== '' ? $phone : '—', ENT_QUOTES, 'UTF-8') ?></td>
                                                <td><span class="lc-badge source-<?= $source === 'manual' ? 'manual' : 'lead' ?>"><?= $source === 'manual' ? 'Manual' : 'Lead' ?></span></td>
                                                <td><span class="lc-family-count"><i class="fas fa-user-friends"></i><span data-family-count><?= $fc ?></span></span></td>
                                                <td><?= htmlspecialchars($addedOn) ?></td>
                                                <td><span class="lc-badge lc-profile-status <?= $hasProf ? '' : 'pending' ?>"><?= $hasProf ? 'Completed' : 'Pending' ?></span></td>
                                                <td><span class="lc-activity"><span class="lc-activity-dot"></span><?= htmlspecialchars($activity) ?></span></td>
                                                <td class="text-right">
                                                    <div class="lc-actions">
                                                        <button type="button" class="btn js-lc-view" title="View"
                                                            data-source="<?= htmlspecialchars($source, ENT_QUOTES, 'UTF-8') ?>"
                                                            data-ref-id="<?= $refId ?>"
                                                            data-name="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>"
                                                            data-phone="<?= htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') ?>"
                                                            data-email="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>">
                                                            <i class="far fa-eye"></i>
                                                        </button>
                                                        <button type="button" class="btn js-lc-edit" title="Edit"
                                                            data-source="<?= htmlspecialchars($source, ENT_QUOTES, 'UTF-8') ?>"
                                                            data-ref-id="<?= $refId ?>"
                                                            data-name="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>"
                                                            data-phone="<?= htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') ?>"
                                                            data-email="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>">
                                                            <i class="fas fa-pen"></i>
                                                        </button>
                                                        <?php if ($waPhone !== '') { ?>
                                                        <a class="btn js-lc-whatsapp" title="WhatsApp" href="https://wa.me/<?= htmlspecialchars($waPhone, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                                                            <i class="fab fa-whatsapp"></i>
                                                        </a>
                                                        <?php } else { ?>
                                                        <button type="button" class="btn" title="No mobile" disabled><i class="fab fa-whatsapp"></i></button>
                                                        <?php } ?>
                                                        <button type="button" class="btn js-lc-docs" title="Documents"
                                                            data-source="<?= htmlspecialchars($source, ENT_QUOTES, 'UTF-8') ?>"
                                                            data-ref-id="<?= $refId ?>"
                                                            data-name="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>"
                                                            data-phone="<?= htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') ?>"
                                                            data-email="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>">
                                                            <i class="far fa-file-alt"></i>
                                                        </button>
                                                        <div class="lc-more">
                                                            <button type="button" class="btn js-lc-more" title="More"><i class="fas fa-ellipsis-v"></i></button>
                                                            <div class="lc-more-menu">
                                                                <button type="button" class="js-lc-view"
                                                                    data-source="<?= htmlspecialchars($source, ENT_QUOTES, 'UTF-8') ?>"
                                                                    data-ref-id="<?= $refId ?>"
                                                                    data-name="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>"
                                                                    data-phone="<?= htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') ?>"
                                                                    data-email="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>">
                                                                    <i class="far fa-eye"></i> View details
                                                                </button>
                                                                <?php if ($source === 'manual') { ?>
                                                                <button type="button" class="text-danger js-lc-delete-contact" data-ref-id="<?= $refId ?>">
                                                                    <i class="fas fa-trash"></i> Delete
                                                                </button>
                                                                <?php } ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php } ?>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="lc-table-footer">
                            <div class="lc-footer-info" id="lcContactInfo">Showing contacts</div>
                            <div class="lc-footer-right">
                                <label class="lc-per-page">Rows per page
                                    <select id="lcPerPage">
                                        <option value="8">8 per page</option>
                                        <option value="10" selected>10 per page</option>
                                        <option value="25">25 per page</option>
                                        <option value="50">50 per page</option>
                                    </select>
                                </label>
                                <div class="lc-pagination" id="lcContactPagination"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <!-- Contact profile modal (supplier-style Add/Edit) -->
        <div class="modal fade" id="lcProfileModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered lc-profile-dialog">
                <div class="modal-content lc-profile-content">
                    <form id="lcProfileForm" enctype="multipart/form-data">
                        <input type="hidden" name="contact_source" id="lcProfileSource" value="lead">
                        <input type="hidden" name="ref_id" id="lcProfileRefId" value="">
                        <div class="modal-header lc-profile-header">
                            <h5 class="modal-title mb-0" id="lcProfileModalTitle">Add Contact</h5>
                            <span class="sr-only" id="lcProfileLeadName"></span>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                        </div>
                        <div class="modal-body lc-profile-body">
                            <?php lcRenderPersonFields('profile'); ?>
                            <p class="small text-danger d-none mb-0 mt-2" id="lcProfileErr"></p>
                        </div>
                        <div class="modal-footer lc-profile-footer">
                            <button type="submit" class="btn lc-btn-save" id="lcProfileSaveBtn">Save</button>
                            <button type="button" class="btn lc-btn-cancel" data-dismiss="modal">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Contact + family list modal -->
        <div class="modal fade" id="lcFamilyModal" tabindex="-1">
            <div class="modal-dialog modal-xl modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <span class="lc-modal-brand-icon"><i class="fas fa-user-friends"></i></span>
                        <div class="lc-modal-head-text">
                            <h5 class="lc-modal-title mb-0">Contact &amp; Family</h5>
                            <p class="lc-modal-subtitle">Manage traveler's contact details and family members</p>
                            <span class="sr-only" id="lcFamilyLeadName"></span>
                        </div>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">&times;</button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="lcFamilySource" value="lead">
                        <input type="hidden" id="lcFamilyRefId">

                        <div id="lcPrimaryCard">
                            <div class="text-muted text-center py-3">Loading…</div>
                        </div>

                        <div class="lc-members-hd">
                            <h6><i class="fas fa-users"></i> Family Members</h6>
                            <span class="lc-members-count" id="lcMembersCount">0 members</span>
                        </div>
                        <div class="lc-member-list" id="lcFamilyListBody"></div>

                        <div class="lc-pax-add-wrap" id="lcInlineAddWrap">
                            <div class="lc-pax-add-title"><i class="fas fa-user-plus"></i> Add Family Member</div>
                            <div class="lc-pax-rows" id="lcPaxRows"></div>
                            <p class="lc-pax-err d-none mt-2 mb-0" id="lcFamilyFormErr"></p>
                        </div>
                    </div>
                    <div class="modal-footer lc-family-footer">
                        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Close</button>
                        <button type="button" class="btn" id="lcFamilyModalAddBtn" data-mode="add">
                            <i class="fas fa-user-plus mr-1"></i><span class="lc-footer-btn-label">Add family member</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Attachments modal -->
        <div class="modal fade" id="lcAttachModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title mb-0"><i class="fas fa-paperclip mr-2"></i>Attachments — <span id="lcAttachModalName">Member</span></h5>
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div class="lc-attach-grid" id="lcAttachGrid">
                            <div class="lc-attach-empty">No attachments yet.</div>
                        </div>
                        <div class="lc-attach-add-zone">
                            <p><i class="fas fa-cloud-upload-alt mr-1"></i>Add image or PDF attachment (max 3 files)</p>
                            <button type="button" class="btn btn-primary btn-sm" id="lcAttachAddBtn">
                                <i class="fas fa-plus mr-1"></i>Add attachment
                            </button>
                            <input type="file" id="lcAttachFileInput" accept="image/*,.pdf" multiple hidden>
                            <p class="small text-muted mb-0 mt-2" id="lcAttachHint">You can attach up to 3 documents.</p>
                        </div>
                        <p class="small text-danger d-none mb-0 mt-2" id="lcAttachErr"></p>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Done</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Attachment viewer -->
        <div class="modal fade" id="lcAttachViewerModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title mb-0"><i class="far fa-eye mr-2"></i>View attachment</h5>
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div class="lc-viewer-toolbar">
                            <div class="lc-viewer-filename" id="lcViewerFileName">Attachment</div>
                            <button type="button" class="btn btn-primary btn-sm" id="lcViewerDownloadBtn">
                                <i class="fas fa-download mr-1"></i>Download
                            </button>
                        </div>
                        <div class="lc-viewer-frame" id="lcViewerFrame"></div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Close</button>
                        <button type="button" class="btn btn-primary" id="lcViewerDownloadBtnFooter">
                            <i class="fas fa-download mr-1"></i>Download
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <?php include 'includes/footer-links.php'; ?>
        <script src="assets/lead_contacts.js?v=23"></script>
    </div>
</body>
</html>
