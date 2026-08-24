<?php
require_once __DIR__ . '/bootstrap.php';

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

function getNextLeadSourceDisplayOrder(mysqli $conn)
{
    $res = $conn->query("SELECT COALESCE(MAX(`display_order`), 0) + 1 AS next_order FROM `crm_lead_sources`");
    if ($res) {
        return max(1, (int) ($res->fetch_assoc()['next_order'] ?? 1));
    }
    return 1;
}

function crmLeadSourceTone(int $index): array
{
    $tones = [
        ['key' => 'red', 'icon' => 'fas fa-user-friends'],
        ['key' => 'purple', 'icon' => 'fas fa-share-alt'],
        ['key' => 'blue', 'icon' => 'fas fa-globe'],
        ['key' => 'orange', 'icon' => 'fas fa-handshake'],
        ['key' => 'teal', 'icon' => 'fas fa-walking'],
        ['key' => 'indigo', 'icon' => 'fas fa-bullhorn'],
    ];
    return $tones[$index % count($tones)];
}

function crmLeadSourceIconForName(string $name, int $index): array
{
    $tone = crmLeadSourceTone($index);
    $lower = strtolower(trim($name));
    $map = [
        'referral' => 'fas fa-user-friends',
        'social media' => 'fas fa-share-alt',
        'website' => 'fas fa-globe',
        'bni' => 'fas fa-handshake',
        'walkin' => 'fas fa-walking',
        'walk-in' => 'fas fa-walking',
        'walk in' => 'fas fa-walking',
        'email' => 'fas fa-envelope',
        'whatsapp' => 'fab fa-whatsapp',
        'google' => 'fab fa-google',
        'facebook' => 'fab fa-facebook-f',
        'instagram' => 'fab fa-instagram',
    ];
    if (isset($map[$lower])) {
        $tone['icon'] = $map[$lower];
    }
    return $tone;
}

function crmLeadSourceFormatUpdated($datetime): string
{
    $datetime = trim((string) $datetime);
    if ($datetime === '' || $datetime === '0000-00-00 00:00:00') {
        return '—';
    }
    $ts = strtotime($datetime);
    if ($ts === false) {
        return $datetime;
    }
    return date('d M Y, h:i A', $ts);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string) ($_POST['action'] ?? ''));
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'save') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $displayOrder = max(0, (int) ($_POST['display_order'] ?? 0));
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($name === '') {
            $_SESSION['lead_source_flash'] = 'Lead source name is required.';
            $_SESSION['lead_source_flash_type'] = 'danger';
            header('Location: lead_source_master.php');
            exit;
        }

        if ($id > 0) {
            $oldName = '';
            $oldStmt = $conn->prepare("SELECT `name` FROM `crm_lead_sources` WHERE `id` = ? LIMIT 1");
            if ($oldStmt) {
                $oldStmt->bind_param('i', $id);
                $oldStmt->execute();
                $oldRes = $oldStmt->get_result();
                if ($oldRes && ($oldRow = $oldRes->fetch_assoc())) {
                    $oldName = trim((string) ($oldRow['name'] ?? ''));
                }
                $oldStmt->close();
            }

            $stmt = $conn->prepare("UPDATE `crm_lead_sources` SET `name` = ?, `display_order` = ?, `is_active` = ? WHERE `id` = ?");
            $stmt->bind_param('siii', $name, $displayOrder, $isActive, $id);
            $ok = $stmt->execute();
            $stmt->close();

            // Leads store source by name (not id) — keep list column in sync on rename.
            if ($ok && $oldName !== '' && strcasecmp($oldName, $name) !== 0) {
                $syncLead = $conn->prepare("UPDATE `crm_leads` SET `lead_source` = ? WHERE `lead_source` = ?");
                if ($syncLead) {
                    $syncLead->bind_param('ss', $name, $oldName);
                    $syncLead->execute();
                    $syncLead->close();
                }
                $syncIntake = $conn->prepare("UPDATE `crm_lead_intake_requests` SET `lead_source` = ? WHERE `lead_source` = ?");
                if ($syncIntake) {
                    $syncIntake->bind_param('ss', $name, $oldName);
                    $syncIntake->execute();
                    $syncIntake->close();
                }
            }

            if ($ok) {
                $_SESSION['lead_source_flash'] = 'Lead source updated successfully.';
                $_SESSION['lead_source_flash_type'] = 'success';
            } else {
                $_SESSION['lead_source_flash'] = 'Could not update lead source.';
                $_SESSION['lead_source_flash_type'] = 'danger';
            }
        } else {
            $displayOrder = getNextLeadSourceDisplayOrder($conn);
            $stmt = $conn->prepare("INSERT INTO `crm_lead_sources` (`name`, `display_order`, `is_active`) VALUES (?, ?, ?)");
            $stmt->bind_param('sii', $name, $displayOrder, $isActive);
            $ok = $stmt->execute();
            $stmt->close();

            if ($ok) {
                $_SESSION['lead_source_flash'] = 'Lead source added successfully.';
                $_SESSION['lead_source_flash_type'] = 'success';
            } else {
                $_SESSION['lead_source_flash'] = $conn->errno === 1062
                    ? 'This lead source already exists.'
                    : 'Could not add lead source.';
                $_SESSION['lead_source_flash_type'] = 'danger';
            }
        }
    } elseif ($action === 'delete' && $id > 0) {
        $stmt = $conn->prepare("DELETE FROM `crm_lead_sources` WHERE `id` = ?");
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $stmt->close();

        $_SESSION['lead_source_flash'] = $ok ? 'Lead source deleted successfully.' : 'Could not delete lead source.';
        $_SESSION['lead_source_flash_type'] = $ok ? 'success' : 'danger';
    }

    header('Location: lead_source_master.php');
    exit;
}

$sources = [];
$res = $conn->query("SELECT `id`, `name`, `display_order`, `is_active`, `updated_at` FROM `crm_lead_sources` ORDER BY `display_order` ASC, `name` ASC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $sources[] = $row;
    }
}
$nextDisplayOrder = getNextLeadSourceDisplayOrder($conn);
$totalSources = count($sources);

$flashMsg = (string) ($_SESSION['lead_source_flash'] ?? '');
$flashType = (string) ($_SESSION['lead_source_flash_type'] ?? 'success');
unset($_SESSION['lead_source_flash'], $_SESSION['lead_source_flash_type']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <base href="../">
    <title>Lead Source Master</title>
    <?php include __DIR__ . '/../includes/header-links.php'; ?>
    <style>
        .crm-lead-source-master .content-wrapper { background: #f3f4f6; }
        .crm-lead-source-master .content-wrapper > .content { background: #f3f4f6; padding-top: 1.1rem; }

        .crm-lead-source-master .page-title-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1.25rem;
            flex-wrap: wrap;
            gap: 0.75rem;
        }
        .crm-lead-source-master .page-title {
            margin: 0;
            font-size: 1.85rem;
            font-weight: 700;
            color: #0f172a;
            letter-spacing: -0.02em;
        }
        .crm-lead-source-master .page-subtitle {
            margin: 0.35rem 0 0;
            font-size: 0.92rem;
            color: #64748b;
            font-weight: 500;
        }
        .crm-lead-source-master .breadcrumbs {
            font-size: 0.85rem;
            color: #94a3b8;
            font-weight: 500;
            padding-top: 0.35rem;
        }
        .crm-lead-source-master .breadcrumbs a { color: #94a3b8; text-decoration: none; }
        .crm-lead-source-master .breadcrumbs a:hover { color: #64748b; }
        .crm-lead-source-master .breadcrumbs .crumb-current { color: #38bdf8; }

        .crm-lead-source-master .ls-card {
            background: #fff;
            border: 1px solid #eef2f7;
            border-radius: 16px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.05);
            overflow: hidden;
            margin-bottom: 1.25rem;
        }
        .crm-lead-source-master .ls-card-hd {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
            padding: 1.1rem 1.25rem 0.85rem;
        }
        .crm-lead-source-master .ls-card-hd-left {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            min-width: 0;
        }
        .crm-lead-source-master .ls-card-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: #fee2e2;
            color: #e11d2e;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            flex-shrink: 0;
        }
        .crm-lead-source-master .ls-card-title {
            margin: 0;
            font-size: 1.05rem;
            font-weight: 700;
            color: #0f172a;
        }
        .crm-lead-source-master .ls-card-sub {
            margin: 0.15rem 0 0;
            font-size: 0.82rem;
            color: #94a3b8;
            font-weight: 500;
        }
        .crm-lead-source-master .ls-card-body { padding: 0.35rem 1.25rem 1.25rem; }

        .crm-lead-source-master .ls-form-row {
            display: flex;
            align-items: flex-end;
            flex-wrap: wrap;
            gap: 0.85rem 1rem;
        }
        .crm-lead-source-master .ls-field { margin: 0; }
        .crm-lead-source-master .ls-field-name { flex: 1 1 280px; min-width: 220px; }
        .crm-lead-source-master .ls-field-order { flex: 0 0 140px; }
        .crm-lead-source-master .ls-field-active {
            flex: 0 0 auto;
            padding-bottom: 0.55rem;
        }
        .crm-lead-source-master .ls-field-actions {
            flex: 0 0 auto;
            margin-left: auto;
            display: flex;
            align-items: center;
            gap: 0.55rem;
            padding-bottom: 0.05rem;
        }
        .crm-lead-source-master .ls-label {
            display: block;
            margin-bottom: 0.4rem;
            font-size: 0.84rem;
            font-weight: 600;
            color: #334155;
        }
        .crm-lead-source-master .ls-label .req { color: #e11d2e; }

        .crm-lead-source-master .ls-input-icon {
            position: relative;
        }
        .crm-lead-source-master .ls-input-icon > i {
            position: absolute;
            left: 0.85rem;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 0.9rem;
            pointer-events: none;
            z-index: 2;
        }
        .crm-lead-source-master .ls-input-icon .form-control {
            padding-left: 2.35rem;
        }

        .crm-lead-source-master .form-control {
            height: 42px;
            border-radius: 10px;
            border-color: #e2e8f0;
            font-size: 0.9rem;
            color: #0f172a;
            box-shadow: none !important;
        }
        .crm-lead-source-master .form-control:focus {
            border-color: #fca5a5;
            box-shadow: 0 0 0 3px rgba(225, 29, 46, 0.1) !important;
        }

        .crm-lead-source-master .ls-check {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            cursor: pointer;
            user-select: none;
            margin: 0;
            font-weight: 600;
            color: #334155;
            font-size: 0.9rem;
        }
        .crm-lead-source-master .ls-check input {
            position: absolute;
            opacity: 0;
            width: 0;
            height: 0;
        }
        .crm-lead-source-master .ls-check-box {
            width: 18px;
            height: 18px;
            border-radius: 4px;
            border: 1.5px solid #cbd5e1;
            background: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 0.65rem;
            transition: all 0.15s ease;
        }
        .crm-lead-source-master .ls-check input:checked + .ls-check-box {
            background: #e11d2e;
            border-color: #e11d2e;
        }
        .crm-lead-source-master .ls-check input:checked + .ls-check-box::after {
            content: "\f00c";
            font-family: "Font Awesome 5 Free";
            font-weight: 900;
        }

        .crm-lead-source-master .btn-ls-reset {
            height: 42px;
            min-width: 88px;
            border-radius: 10px;
            border: 1px solid #cbd5e1;
            background: #fff;
            color: #0f172a;
            font-weight: 600;
            font-size: 0.9rem;
            padding: 0 1rem;
        }
        .crm-lead-source-master .btn-ls-reset:hover {
            background: #f8fafc;
            border-color: #94a3b8;
            color: #0f172a;
        }
        .crm-lead-source-master .btn-ls-save,
        .crm-lead-source-master .btn-ls-add {
            height: 42px;
            border-radius: 10px;
            border: 0;
            background: #e11d2e;
            color: #fff !important;
            font-weight: 700;
            font-size: 0.9rem;
            padding: 0 1.15rem;
            box-shadow: 0 6px 16px rgba(225, 29, 46, 0.22);
        }
        .crm-lead-source-master .btn-ls-save:hover,
        .crm-lead-source-master .btn-ls-add:hover {
            background: #c91020;
            color: #fff !important;
        }

        .crm-lead-source-master .ls-toolbar {
            display: flex;
            align-items: center;
            gap: 0.55rem;
            flex-wrap: wrap;
        }
        .crm-lead-source-master .ls-search {
            position: relative;
            min-width: 220px;
        }
        .crm-lead-source-master .ls-search .form-control {
            padding-right: 2.4rem;
            height: 40px;
            border-radius: 10px;
        }
        .crm-lead-source-master .ls-search > i {
            position: absolute;
            right: 0.85rem;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 0.9rem;
            pointer-events: none;
        }
        .crm-lead-source-master .btn-ls-filter {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            background: #fff;
            color: #64748b;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .crm-lead-source-master .btn-ls-filter:hover,
        .crm-lead-source-master .btn-ls-filter.is-on {
            background: #fff5f5;
            border-color: #fecaca;
            color: #e11d2e;
        }
        .crm-lead-source-master .btn-ls-add {
            height: 40px;
            padding: 0 1rem;
        }

        .crm-lead-source-master .table-wrap { overflow-x: auto; }
        .crm-lead-source-master table.ls-table {
            width: 100%;
            margin: 0;
            border-collapse: collapse;
            font-size: 0.9rem;
        }
        .crm-lead-source-master table.ls-table thead th {
            background: transparent;
            color: #94a3b8;
            font-weight: 700;
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            padding: 0.75rem 1.15rem;
            border-bottom: 1px solid #eef2f7;
            white-space: nowrap;
        }
        .crm-lead-source-master table.ls-table tbody td {
            padding: 0.95rem 1.15rem;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
            color: #0f172a;
            background: #fff;
        }
        .crm-lead-source-master table.ls-table tbody tr:last-child td { border-bottom: 0; }
        .crm-lead-source-master table.ls-table tbody tr:hover td { background: #fafbfc; }
        .crm-lead-source-master .col-serial {
            width: 56px;
            color: #94a3b8 !important;
            font-weight: 600;
            font-variant-numeric: tabular-nums;
        }

        .crm-lead-source-master .ls-name-cell {
            display: inline-flex;
            align-items: center;
            gap: 0.7rem;
            font-weight: 700;
            color: #0f172a;
        }
        .crm-lead-source-master .ls-name-icon {
            width: 34px;
            height: 34px;
            border-radius: 9px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            flex-shrink: 0;
        }
        .crm-lead-source-master .tone-red { background: #fee2e2; color: #e11d2e; }
        .crm-lead-source-master .tone-purple { background: #ede9fe; color: #7c3aed; }
        .crm-lead-source-master .tone-blue { background: #dbeafe; color: #2563eb; }
        .crm-lead-source-master .tone-orange { background: #ffedd5; color: #ea580c; }
        .crm-lead-source-master .tone-teal { background: #ccfbf1; color: #0d9488; }
        .crm-lead-source-master .tone-indigo { background: #e0e7ff; color: #4f46e5; }

        .crm-lead-source-master .order-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 34px;
            height: 28px;
            padding: 0 0.55rem;
            border-radius: 999px;
            font-weight: 700;
            font-size: 0.82rem;
        }
        .crm-lead-source-master .order-pill.tone-red { background: #fee2e2; color: #e11d2e; }
        .crm-lead-source-master .order-pill.tone-purple { background: #ede9fe; color: #7c3aed; }
        .crm-lead-source-master .order-pill.tone-blue { background: #dbeafe; color: #2563eb; }
        .crm-lead-source-master .order-pill.tone-orange { background: #ffedd5; color: #ea580c; }
        .crm-lead-source-master .order-pill.tone-teal { background: #ccfbf1; color: #0d9488; }
        .crm-lead-source-master .order-pill.tone-indigo { background: #e0e7ff; color: #4f46e5; }

        .crm-lead-source-master .ls-status {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            font-weight: 600;
            font-size: 0.88rem;
        }
        .crm-lead-source-master .ls-status.is-active { color: #16a34a; }
        .crm-lead-source-master .ls-status.is-inactive { color: #94a3b8; }
        .crm-lead-source-master .ls-status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: currentColor;
            flex-shrink: 0;
        }

        .crm-lead-source-master .ls-updated {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            color: #475569;
            font-size: 0.88rem;
            white-space: nowrap;
        }
        .crm-lead-source-master .ls-updated i { color: #94a3b8; }

        .crm-lead-source-master .ls-actions {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            flex-wrap: nowrap;
        }
        .crm-lead-source-master .btn-ls-edit,
        .crm-lead-source-master .btn-ls-del,
        .crm-lead-source-master .btn-ls-more {
            height: 34px;
            border-radius: 8px;
            font-size: 0.82rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0 0.75rem;
            background: #fff;
        }
        .crm-lead-source-master .btn-ls-edit {
            border: 1px solid #bfdbfe;
            color: #2563eb !important;
        }
        .crm-lead-source-master .btn-ls-edit:hover {
            background: #eff6ff;
            color: #1d4ed8 !important;
        }
        .crm-lead-source-master .btn-ls-del {
            border: 1px solid #fecaca;
            color: #e11d2e !important;
        }
        .crm-lead-source-master .btn-ls-del:hover {
            background: #fef2f2;
            color: #b91c1c !important;
        }
        .crm-lead-source-master .btn-ls-more {
            width: 34px;
            padding: 0;
            justify-content: center;
            border: 1px solid #e2e8f0;
            color: #64748b !important;
        }
        .crm-lead-source-master .btn-ls-more:hover {
            background: #f8fafc;
            color: #334155 !important;
        }

        .crm-lead-source-master .ls-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            flex-wrap: wrap;
            padding: 0.95rem 1.25rem 1.1rem;
            border-top: 1px solid #eef2f7;
        }
        .crm-lead-source-master .ls-footer-summary {
            font-size: 0.85rem;
            color: #94a3b8;
            font-weight: 500;
        }
        .crm-lead-source-master .ls-pager {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
        }
        .crm-lead-source-master .ls-pager .btn {
            width: 34px;
            height: 34px;
            padding: 0;
            border-radius: 50%;
            border: 1px solid #e2e8f0;
            background: #fff;
            color: #64748b;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.82rem;
        }
        .crm-lead-source-master .ls-pager .btn:hover:not(:disabled) {
            background: #f8fafc;
            color: #0f172a;
        }
        .crm-lead-source-master .ls-pager .btn.is-current {
            background: #e11d2e;
            border-color: #e11d2e;
            color: #fff !important;
        }
        .crm-lead-source-master .ls-pager .btn:disabled {
            opacity: 0.45;
            cursor: not-allowed;
        }

        .crm-lead-source-master .ls-empty {
            text-align: center;
            color: #94a3b8;
            padding: 2rem 1rem !important;
            font-weight: 500;
        }

        /* Confirm edit modal */
        #lsConfirmEditModal .modal-dialog {
            max-width: 420px;
        }
        #lsConfirmEditModal .ls-confirm-shell {
            border: none;
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 24px 64px rgba(15, 23, 42, 0.22);
        }
        #lsConfirmEditModal .ls-confirm-body {
            padding: 1.75rem 1.65rem 1.35rem;
            text-align: center;
            background: #fff;
        }
        #lsConfirmEditModal .ls-confirm-icon {
            width: 64px;
            height: 64px;
            margin: 0 auto 1.1rem;
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(145deg, #fff7ed 0%, #ffedd5 100%);
            color: #ea580c;
            font-size: 1.55rem;
            box-shadow: inset 0 0 0 1px rgba(234, 88, 12, 0.12);
        }
        #lsConfirmEditModal .ls-confirm-title {
            margin: 0 0 0.55rem;
            font-size: 1.2rem;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: -0.02em;
        }
        #lsConfirmEditModal .ls-confirm-text {
            margin: 0 auto;
            max-width: 34ch;
            font-size: 0.92rem;
            line-height: 1.55;
            color: #64748b;
            font-weight: 500;
        }
        #lsConfirmEditModal .ls-confirm-note {
            margin: 1rem auto 0;
            padding: 0.65rem 0.85rem;
            max-width: 100%;
            border-radius: 10px;
            background: #fff7ed;
            border: 1px solid #fed7aa;
            color: #9a3412;
            font-size: 0.78rem;
            font-weight: 600;
            line-height: 1.4;
            text-align: left;
        }
        #lsConfirmEditModal .ls-confirm-note i {
            margin-right: 0.35rem;
            color: #ea580c;
        }
        #lsConfirmEditModal .ls-confirm-footer {
            display: flex;
            gap: 0.65rem;
            justify-content: stretch;
            padding: 0 1.65rem 1.55rem;
            background: #fff;
        }
        #lsConfirmEditModal .ls-confirm-footer .btn {
            flex: 1 1 0;
            height: 44px;
            border-radius: 11px;
            font-weight: 700;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
        }
        #lsConfirmEditModal .btn-ls-confirm-cancel {
            border: 1px solid #e2e8f0;
            background: #f8fafc;
            color: #475569 !important;
        }
        #lsConfirmEditModal .btn-ls-confirm-cancel:hover {
            background: #f1f5f9;
            border-color: #cbd5e1;
            color: #334155 !important;
        }
        #lsConfirmEditModal .btn-ls-confirm-ok {
            border: none;
            background: linear-gradient(135deg, #ef4444 0%, #e11d2e 100%);
            color: #fff !important;
            box-shadow: 0 8px 18px rgba(225, 29, 46, 0.28);
        }
        #lsConfirmEditModal .btn-ls-confirm-ok:hover {
            background: linear-gradient(135deg, #dc2626 0%, #be123c 100%);
            color: #fff !important;
        }
        #lsConfirmEditModal.modal.show .modal-dialog {
            transform: none;
            animation: lsConfirmIn 0.22s ease-out;
        }
        @keyframes lsConfirmIn {
            from { opacity: 0; transform: translateY(10px) scale(0.98); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        body.dark-mode #lsConfirmEditModal .ls-confirm-shell,
        body.dark-mode #lsConfirmEditModal .ls-confirm-body,
        body.dark-mode #lsConfirmEditModal .ls-confirm-footer {
            background: #1e293b;
        }
        body.dark-mode #lsConfirmEditModal .ls-confirm-title { color: #f8fafc; }
        body.dark-mode #lsConfirmEditModal .ls-confirm-text { color: #94a3b8; }
        body.dark-mode #lsConfirmEditModal .ls-confirm-note {
            background: rgba(234, 88, 12, 0.12);
            border-color: rgba(234, 88, 12, 0.28);
            color: #fdba74;
        }
        body.dark-mode #lsConfirmEditModal .btn-ls-confirm-cancel {
            background: #0f172a;
            border-color: #334155;
            color: #cbd5e1 !important;
        }

        @media (max-width: 767.98px) {
            .crm-lead-source-master .ls-field-actions {
                margin-left: 0;
                width: 100%;
            }
            .crm-lead-source-master .ls-field-order { flex: 1 1 120px; }
            .crm-lead-source-master .ls-toolbar { width: 100%; }
            .crm-lead-source-master .ls-search { flex: 1 1 auto; min-width: 0; }
        }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
    <div class="wrapper crm-lead-source-master">
        <?php include __DIR__ . '/../includes/top-header.php'; ?>
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>

        <div class="content-wrapper">
            <section class="content">
                <div class="container-fluid">
                    <div class="page-title-row">
                        <div>
                            <h1 class="page-title">Lead Source Master</h1>
                            <p class="page-subtitle">Manage and organize all lead sources used in your CRM.</p>
                        </div>
                        <nav class="breadcrumbs">
                            <a href="dashboard.php">Home</a> /
                            <a href="crm/lead_source_master.php">Masters</a> /
                            <span class="crumb-current">Lead Source</span>
                        </nav>
                    </div>

                    <?php if ($flashMsg !== '') { ?>
                        <div class="alert alert-<?= htmlspecialchars($flashType, ENT_QUOTES, 'UTF-8') ?> alert-dismissible fade show">
                            <?= htmlspecialchars($flashMsg, ENT_QUOTES, 'UTF-8') ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                        </div>
                    <?php } ?>

                    <div class="ls-card" id="leadSourceFormCard">
                        <div class="ls-card-hd">
                            <div class="ls-card-hd-left">
                                <span class="ls-card-icon"><i class="fas fa-user-plus"></i></span>
                                <div>
                                    <h2 class="ls-card-title js-ls-form-title">Add / Update Lead Source</h2>
                                </div>
                            </div>
                        </div>
                        <div class="ls-card-body">
                            <form method="post" id="leadSourceForm" class="ls-form-row">
                                <input type="hidden" name="action" value="save">
                                <input type="hidden" name="id" id="leadSourceId" value="0">

                                <div class="ls-field ls-field-name">
                                    <label class="ls-label" for="leadSourceName">Lead Source Name <span class="req">*</span></label>
                                    <div class="ls-input-icon">
                                        <i class="fas fa-address-book"></i>
                                        <input type="text" class="form-control" id="leadSourceName" name="name" placeholder="Enter lead source name" required>
                                    </div>
                                </div>

                                <div class="ls-field ls-field-order">
                                    <label class="ls-label" for="leadSourceOrder">Display Order <span class="req">*</span></label>
                                    <input type="number" class="form-control" id="leadSourceOrder" name="display_order" value="<?= (int) $nextDisplayOrder ?>" min="0" required>
                                </div>

                                <div class="ls-field ls-field-active">
                                    <label class="ls-check" for="leadSourceActive">
                                        <input type="checkbox" id="leadSourceActive" name="is_active" checked>
                                        <span class="ls-check-box" aria-hidden="true"></span>
                                        <span>Active</span>
                                    </label>
                                </div>

                                <div class="ls-field ls-field-actions">
                                    <button type="button" class="btn btn-ls-reset js-lead-source-reset">Reset</button>
                                    <button type="submit" class="btn btn-ls-save js-ls-save-btn">Save Changes</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="ls-card">
                        <div class="ls-card-hd">
                            <div class="ls-card-hd-left">
                                <span class="ls-card-icon"><i class="fas fa-book"></i></span>
                                <div>
                                    <h2 class="ls-card-title">Lead Sources</h2>
                                    <p class="ls-card-sub">List of all lead sources in the system.</p>
                                </div>
                            </div>
                            <div class="ls-toolbar">
                                <div class="ls-search">
                                    <input type="search" class="form-control js-ls-search" placeholder="Search lead sources..." autocomplete="off">
                                    <i class="fas fa-search"></i>
                                </div>
                                <button type="button" class="btn btn-ls-filter js-ls-filter" title="Show active only" aria-pressed="false">
                                    <i class="fas fa-filter"></i>
                                </button>
                                <button type="button" class="btn btn-ls-add js-lead-source-add">
                                    <i class="fas fa-plus mr-1"></i> Add New
                                </button>
                            </div>
                        </div>

                        <div class="table-wrap">
                            <table class="ls-table" id="leadSourceTable">
                                <thead>
                                    <tr>
                                        <th style="width:56px;">#</th>
                                        <th>Name</th>
                                        <th style="width:130px;">Display Order</th>
                                        <th style="width:120px;">Status</th>
                                        <th style="width:210px;">Last Updated</th>
                                        <th style="width:230px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($sources)) { ?>
                                        <tr class="js-ls-empty-row">
                                            <td colspan="6" class="ls-empty">No lead sources found.</td>
                                        </tr>
                                    <?php } else { ?>
                                        <?php foreach ($sources as $idx => $source) {
                                            $tone = crmLeadSourceIconForName((string) $source['name'], $idx);
                                            $isActive = (int) ($source['is_active'] ?? 0) === 1;
                                            ?>
                                            <tr
                                                class="js-ls-row"
                                                data-name="<?= htmlspecialchars(strtolower((string) $source['name']), ENT_QUOTES, 'UTF-8') ?>"
                                                data-active="<?= $isActive ? '1' : '0' ?>"
                                            >
                                                <td class="col-serial js-ls-serial"><?= (int) $idx + 1 ?></td>
                                                <td>
                                                    <span class="ls-name-cell">
                                                        <span class="ls-name-icon tone-<?= htmlspecialchars($tone['key'], ENT_QUOTES, 'UTF-8') ?>">
                                                            <i class="<?= htmlspecialchars($tone['icon'], ENT_QUOTES, 'UTF-8') ?>"></i>
                                                        </span>
                                                        <?= htmlspecialchars((string) $source['name'], ENT_QUOTES, 'UTF-8') ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="order-pill tone-<?= htmlspecialchars($tone['key'], ENT_QUOTES, 'UTF-8') ?>">
                                                        <?= (int) $source['display_order'] ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($isActive) { ?>
                                                        <span class="ls-status is-active"><span class="ls-status-dot"></span> Active</span>
                                                    <?php } else { ?>
                                                        <span class="ls-status is-inactive"><span class="ls-status-dot"></span> Inactive</span>
                                                    <?php } ?>
                                                </td>
                                                <td>
                                                    <span class="ls-updated">
                                                        <i class="far fa-calendar-alt"></i>
                                                        <?= htmlspecialchars(crmLeadSourceFormatUpdated($source['updated_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="ls-actions">
                                                        <button
                                                            type="button"
                                                            class="btn btn-ls-edit js-lead-source-edit"
                                                            data-id="<?= (int) $source['id'] ?>"
                                                            data-name="<?= htmlspecialchars((string) $source['name'], ENT_QUOTES, 'UTF-8') ?>"
                                                            data-order="<?= (int) $source['display_order'] ?>"
                                                            data-active="<?= $isActive ? '1' : '0' ?>"
                                                        ><i class="fas fa-pen"></i> Edit</button>
                                                        <form method="post" class="d-inline" onsubmit="return confirm('Delete this lead source?');">
                                                            <input type="hidden" name="action" value="delete">
                                                            <input type="hidden" name="id" value="<?= (int) $source['id'] ?>">
                                                            <button type="submit" class="btn btn-ls-del"><i class="fas fa-trash-alt"></i> Delete</button>
                                                        </form>
                                                        <div class="dropdown d-inline-block">
                                                            <button type="button" class="btn btn-ls-more" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title="More">
                                                                <i class="fas fa-ellipsis-v"></i>
                                                            </button>
                                                            <div class="dropdown-menu dropdown-menu-right">
                                                                <button
                                                                    type="button"
                                                                    class="dropdown-item js-lead-source-edit"
                                                                    data-id="<?= (int) $source['id'] ?>"
                                                                    data-name="<?= htmlspecialchars((string) $source['name'], ENT_QUOTES, 'UTF-8') ?>"
                                                                    data-order="<?= (int) $source['display_order'] ?>"
                                                                    data-active="<?= $isActive ? '1' : '0' ?>"
                                                                ><i class="fas fa-pen mr-2 text-primary"></i> Edit</button>
                                                                <button
                                                                    type="button"
                                                                    class="dropdown-item text-danger"
                                                                    onclick="if(confirm('Delete this lead source?')){ this.closest('td').querySelector('form').submit(); }"
                                                                ><i class="fas fa-trash-alt mr-2"></i> Delete</button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php } ?>
                                        <tr class="js-ls-empty-row" style="display:none;">
                                            <td colspan="6" class="ls-empty">No lead sources match your filters.</td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="ls-footer">
                            <div class="ls-footer-summary js-ls-summary">
                                Showing <?= $totalSources > 0 ? '1' : '0' ?> to <?= (int) $totalSources ?> of <?= (int) $totalSources ?> entries
                            </div>
                            <div class="ls-pager">
                                <button type="button" class="btn js-ls-page-prev" disabled aria-label="Previous page"><i class="fas fa-chevron-left"></i></button>
                                <button type="button" class="btn is-current js-ls-page-num">1</button>
                                <button type="button" class="btn js-ls-page-next" disabled aria-label="Next page"><i class="fas fa-chevron-right"></i></button>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <div class="modal fade" id="lsConfirmEditModal" tabindex="-1" role="dialog" aria-labelledby="lsConfirmEditTitle" aria-hidden="true" data-backdrop="static" data-keyboard="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content ls-confirm-shell">
                    <div class="ls-confirm-body">
                        <div class="ls-confirm-icon" aria-hidden="true">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <h3 class="ls-confirm-title" id="lsConfirmEditTitle">Confirm lead source update</h3>
                        <p class="ls-confirm-text">
                            Changing the lead source data will update all existing lead source records. Are you sure you want to continue?
                        </p>
                        <div class="ls-confirm-note">
                            <i class="fas fa-info-circle"></i>
                            This will sync the updated name across related leads in the CRM.
                        </div>
                    </div>
                    <div class="ls-confirm-footer">
                        <button type="button" class="btn btn-ls-confirm-cancel" data-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-ls-confirm-ok js-ls-confirm-continue">
                            <i class="fas fa-check"></i> Continue
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <?php include __DIR__ . '/../includes/footer-links.php'; ?>
    </div>

    <script>
        (function () {
            var nextDisplayOrder = <?= (int) $nextDisplayOrder ?>;
            var perPage = 10;
            var currentPage = 1;
            var activeOnly = false;

            function resetForm() {
                jQuery('#leadSourceId').val('0');
                jQuery('#leadSourceName').val('');
                jQuery('#leadSourceOrder').val(nextDisplayOrder);
                jQuery('#leadSourceActive').prop('checked', true);
                jQuery('.js-ls-form-title').text('Add / Update Lead Source');
                jQuery('.js-ls-save-btn').text('Save Changes');
            }

            function filteredRows() {
                var q = (jQuery('.js-ls-search').val() || '').toString().trim().toLowerCase();
                return jQuery('#leadSourceTable tbody tr.js-ls-row').filter(function () {
                    var $row = jQuery(this);
                    var name = ($row.attr('data-name') || '');
                    var active = ($row.attr('data-active') || '0') === '1';
                    if (activeOnly && !active) {
                        return false;
                    }
                    if (q && name.indexOf(q) === -1) {
                        return false;
                    }
                    return true;
                });
            }

            function renderList() {
                var $all = jQuery('#leadSourceTable tbody tr.js-ls-row');
                var $rows = filteredRows();
                var total = $rows.length;
                var pages = Math.max(1, Math.ceil(total / perPage));
                if (currentPage > pages) {
                    currentPage = pages;
                }
                if (currentPage < 1) {
                    currentPage = 1;
                }

                $all.hide();
                var start = (currentPage - 1) * perPage;
                var end = start + perPage;
                var visible = 0;
                $rows.each(function (i) {
                    var show = i >= start && i < end;
                    jQuery(this).toggle(show);
                    if (show) {
                        visible += 1;
                        jQuery(this).find('.js-ls-serial').text(i + 1);
                    }
                });

                var from = total === 0 ? 0 : start + 1;
                var to = total === 0 ? 0 : Math.min(end, total);
                jQuery('.js-ls-summary').text('Showing ' + from + ' to ' + to + ' of ' + total + ' entries');
                jQuery('.js-ls-page-num').text(String(currentPage));
                jQuery('.js-ls-page-prev').prop('disabled', currentPage <= 1);
                jQuery('.js-ls-page-next').prop('disabled', currentPage >= pages || total === 0);

                var hasStaticEmpty = jQuery('#leadSourceTable tbody tr.js-ls-empty-row').length && $all.length === 0;
                jQuery('#leadSourceTable tbody tr.js-ls-empty-row').toggle(!hasStaticEmpty && total === 0 && $all.length > 0);
            }

            function scrollToForm() {
                var top = jQuery('#leadSourceFormCard').offset();
                if (top) {
                    jQuery('html, body').animate({ scrollTop: Math.max(0, top.top - 20) }, 220);
                }
            }

            jQuery(document).on('click', '.js-lead-source-edit', function () {
                var $btn = jQuery(this);
                jQuery('#leadSourceId').val($btn.data('id') || 0);
                jQuery('#leadSourceName').val($btn.data('name') || '');
                jQuery('#leadSourceOrder').val($btn.data('order') || 0);
                jQuery('#leadSourceActive').prop('checked', Number($btn.data('active')) === 1);
                jQuery('.js-ls-form-title').text('Edit Lead Source');
                jQuery('.js-ls-save-btn').text('Save Changes');
                scrollToForm();
            });

            jQuery('#leadSourceForm').on('submit', function (e) {
                var editId = parseInt(jQuery('#leadSourceId').val(), 10) || 0;
                if (editId <= 0) {
                    return true;
                }
                if (jQuery(this).data('ls-confirm-ok')) {
                    jQuery(this).removeData('ls-confirm-ok');
                    return true;
                }
                e.preventDefault();
                jQuery('#lsConfirmEditModal').modal('show');
                return false;
            });

            jQuery(document).on('click', '.js-ls-confirm-continue', function () {
                var $form = jQuery('#leadSourceForm');
                $form.data('ls-confirm-ok', 1);
                jQuery('#lsConfirmEditModal')
                    .one('hidden.bs.modal', function () {
                        $form.trigger('submit');
                    })
                    .modal('hide');
            });

            jQuery(document).on('click', '.js-lead-source-reset, .js-lead-source-add', function () {
                resetForm();
                if (jQuery(this).hasClass('js-lead-source-add')) {
                    scrollToForm();
                    jQuery('#leadSourceName').trigger('focus');
                }
            });

            jQuery('.js-ls-search').on('input', function () {
                currentPage = 1;
                renderList();
            });

            jQuery('.js-ls-filter').on('click', function () {
                activeOnly = !activeOnly;
                jQuery(this).toggleClass('is-on', activeOnly).attr('aria-pressed', activeOnly ? 'true' : 'false');
                jQuery(this).attr('title', activeOnly ? 'Showing active only' : 'Show active only');
                currentPage = 1;
                renderList();
            });

            jQuery('.js-ls-page-prev').on('click', function () {
                if (currentPage > 1) {
                    currentPage -= 1;
                    renderList();
                }
            });
            jQuery('.js-ls-page-next').on('click', function () {
                currentPage += 1;
                renderList();
            });

            renderList();
        })();
    </script>
</body>
</html>
