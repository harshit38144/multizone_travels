<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../includes/geo_locations.php';

geoEnsureTables($conn);

$perPage = 25;
$listPage = max(1, (int) ($_GET['page'] ?? 1));
$countryFilter = max(0, (int) ($_GET['country_id'] ?? 0));
$statusFilter = trim((string) ($_GET['status'] ?? 'all'));
if (!in_array($statusFilter, ['all', 'active', 'inactive', 'deleted'], true)) {
    $statusFilter = 'all';
}
$search = trim($_GET['q'] ?? '');
$showingDeleted = ($statusFilter === 'deleted');

$where = $showingDeleted
    ? 'COALESCE(ci.is_deleted, 0) = 1'
    : 'COALESCE(ci.is_deleted, 0) = 0';
$where .= ' AND COALESCE(co.is_deleted, 0) = 0';
if ($countryFilter > 0) {
    $where .= ' AND ci.country_id = ' . $countryFilter;
}
if ($statusFilter === 'active') {
    $where .= ' AND COALESCE(ci.is_active, 1) = 1';
} elseif ($statusFilter === 'inactive') {
    $where .= ' AND COALESCE(ci.is_active, 1) = 0';
}
if ($search !== '') {
    $like = '%' . $conn->real_escape_string($search) . '%';
    $where .= " AND (
        ci.name LIKE '{$like}'
        OR COALESCE(st.name, '') LIKE '{$like}'
        OR COALESCE(ci.airport_code, '') LIKE '{$like}'
        OR co.name LIKE '{$like}'
        OR COALESCE(ci.region, '') LIKE '{$like}'
    )";
}

$totalRows = 0;
$countRes = $conn->query(
    "SELECT COUNT(*) AS c
     FROM cities ci
     INNER JOIN countries co ON co.id = ci.country_id
     LEFT JOIN states st ON st.id = ci.state_id
     WHERE {$where}"
);
if ($countRes) {
    $totalRows = (int) ($countRes->fetch_assoc()['c'] ?? 0);
}

$totalPages = max(1, (int) ceil($totalRows / $perPage));
if ($listPage > $totalPages) {
    $listPage = $totalPages;
}
$offset = ($listPage - 1) * $perPage;

$cities = [];
$listSql = "SELECT ci.id, ci.name, ci.airport_code, ci.created_at, ci.country_id, ci.state_id,
                   COALESCE(ci.is_active, 1) AS is_active,
                   COALESCE(ci.is_deleted, 0) AS is_deleted,
                   ci.timezone, ci.region, ci.created_by, ci.updated_at, ci.deleted_at,
                   co.name AS country_name, co.iso2 AS country_iso2, st.name AS state_name
            FROM cities ci
            INNER JOIN countries co ON co.id = ci.country_id
            LEFT JOIN states st ON st.id = ci.state_id
            WHERE {$where}
            ORDER BY " . ($showingDeleted ? 'ci.deleted_at DESC, ci.name ASC' : 'ci.name ASC') . "
            LIMIT {$offset}, {$perPage}";
$listRes = $conn->query($listSql);
if ($listRes) {
    while ($row = $listRes->fetch_assoc()) {
        $cities[] = $row;
    }
}

$countryRows = [];
$countryRowsRes = $conn->query(
    'SELECT co.id, co.name, co.iso2, COALESCE(co.is_deleted, 0) AS is_deleted,
            co.deleted_at, COUNT(ci.id) AS city_count
     FROM countries co
     LEFT JOIN cities ci ON ci.country_id = co.id AND COALESCE(ci.is_deleted, 0) = 0
     GROUP BY co.id, co.name, co.iso2, co.is_deleted, co.deleted_at
     ORDER BY co.is_deleted ASC, co.name ASC'
);
if ($countryRowsRes) {
    while ($row = $countryRowsRes->fetch_assoc()) {
        $countryRows[] = $row;
    }
}

$countries = [];
$countriesRes = $conn->query('SELECT id, name, iso2 FROM countries WHERE COALESCE(is_deleted, 0) = 0 ORDER BY name ASC');
if ($countriesRes) {
    while ($row = $countriesRes->fetch_assoc()) {
        $countries[] = $row;
    }
}

$deletedCount = 0;
$deletedCountRes = $conn->query('SELECT COUNT(*) AS c FROM cities WHERE COALESCE(is_deleted, 0) = 1');
if ($deletedCountRes) {
    $deletedCount = (int) ($deletedCountRes->fetch_assoc()['c'] ?? 0);
}

function cityMasterPageUrl($listPage, $countryFilter, $search, $statusFilter = 'all', $extra = [])
{
    $params = $extra;
    if ($listPage > 1) {
        $params['page'] = $listPage;
    }
    if ($countryFilter > 0) {
        $params['country_id'] = $countryFilter;
    }
    if ($statusFilter !== '' && $statusFilter !== 'all') {
        $params['status'] = $statusFilter;
    }
    if ($search !== '') {
        $params['q'] = $search;
    }
    $qs = http_build_query($params);
    return 'crm/city_master.php' . ($qs !== '' ? '?' . $qs : '');
}

function cityMasterFormatDate($datetime)
{
    $datetime = trim((string) $datetime);
    if ($datetime === '' || $datetime === '0000-00-00 00:00:00') {
        return '—';
    }
    $ts = strtotime($datetime);
    if ($ts === false) {
        return '—';
    }
    return date('M j, Y', $ts);
}

function cityMasterPctChange($current, $previous)
{
    $current = (float) $current;
    $previous = (float) $previous;
    if ($previous <= 0) {
        return $current > 0 ? 100.0 : 0.0;
    }
    return round((($current - $previous) / $previous) * 100, 1);
}

function cityMasterFlagHtml($iso2, $countryName)
{
    $iso2 = strtolower(trim((string) $iso2));
    $countryName = trim((string) $countryName);
    if (preg_match('/^[a-z]{2}$/', $iso2)) {
        $src = 'https://flagcdn.com/20x15/' . $iso2 . '.png';
        return '<img class="cm-flag" src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '" alt="" width="20" height="15" loading="lazy"> '
            . htmlspecialchars($countryName, ENT_QUOTES, 'UTF-8');
    }
    return '<span class="cm-flag-fallback"><i class="fas fa-globe"></i></span> '
        . htmlspecialchars($countryName !== '' ? $countryName : '—', ENT_QUOTES, 'UTF-8');
}

// KPI stats
$kpiTotalCities = 0;
$kpiCountries = 0;
$kpiAirportCities = 0;
$kpiRecent = 0;
$kpiRecentPrev = 0;
$r = $conn->query('SELECT COUNT(*) AS c FROM cities ci INNER JOIN countries co ON co.id = ci.country_id WHERE COALESCE(ci.is_deleted, 0) = 0 AND COALESCE(co.is_deleted, 0) = 0');
if ($r) {
    $kpiTotalCities = (int) ($r->fetch_assoc()['c'] ?? 0);
}
$r = $conn->query('SELECT COUNT(DISTINCT ci.country_id) AS c FROM cities ci INNER JOIN countries co ON co.id = ci.country_id WHERE COALESCE(ci.is_deleted, 0) = 0 AND COALESCE(co.is_deleted, 0) = 0');
if ($r) {
    $kpiCountries = (int) ($r->fetch_assoc()['c'] ?? 0);
}
$r = $conn->query("SELECT COUNT(*) AS c FROM cities ci INNER JOIN countries co ON co.id = ci.country_id WHERE COALESCE(ci.is_deleted, 0) = 0 AND COALESCE(co.is_deleted, 0) = 0 AND ci.airport_code IS NOT NULL AND TRIM(ci.airport_code) <> ''");
if ($r) {
    $kpiAirportCities = (int) ($r->fetch_assoc()['c'] ?? 0);
}
$r = $conn->query('SELECT COUNT(*) AS c FROM cities ci INNER JOIN countries co ON co.id = ci.country_id WHERE COALESCE(ci.is_deleted, 0) = 0 AND COALESCE(co.is_deleted, 0) = 0 AND ci.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)');
if ($r) {
    $kpiRecent = (int) ($r->fetch_assoc()['c'] ?? 0);
}
$r = $conn->query('SELECT COUNT(*) AS c FROM cities ci INNER JOIN countries co ON co.id = ci.country_id WHERE COALESCE(ci.is_deleted, 0) = 0 AND COALESCE(co.is_deleted, 0) = 0 AND ci.created_at >= DATE_SUB(NOW(), INTERVAL 60 DAY) AND ci.created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)');
if ($r) {
    $kpiRecentPrev = (int) ($r->fetch_assoc()['c'] ?? 0);
}

$kpiCards = [
    [
        'label' => 'Total Cities',
        'value' => number_format($kpiTotalCities),
        'sub' => 'Across ' . number_format($kpiCountries) . ' countries',
        'icon' => 'fas fa-city',
        'trend' => cityMasterPctChange($kpiTotalCities, max(0, $kpiTotalCities - $kpiRecent)),
    ],
    [
        'label' => 'Countries',
        'value' => number_format($kpiCountries),
        'sub' => 'Active countries',
        'icon' => 'fas fa-flag',
        'trend' => cityMasterPctChange($kpiCountries, max(1, $kpiCountries - 1)),
    ],
    [
        'label' => 'Airport Cities',
        'value' => number_format($kpiAirportCities),
        'sub' => 'Cities with airports',
        'icon' => 'fas fa-plane',
        'trend' => $kpiTotalCities > 0 ? round(($kpiAirportCities / $kpiTotalCities) * 100, 1) : 0.0,
    ],
    [
        'label' => 'Recently Added',
        'value' => number_format($kpiRecent),
        'sub' => 'In last 30 days',
        'icon' => 'far fa-clock',
        'trend' => cityMasterPctChange($kpiRecent, $kpiRecentPrev),
    ],
];

if (isset($_GET['export']) && (string) $_GET['export'] === '1') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="city_master_export.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID', 'Country', 'State', 'City Name', 'Status', 'Created On', 'Last Updated', 'Created By']);
    $exportSql = "SELECT ci.id, ci.name, ci.created_at,
                         COALESCE(ci.is_active, 1) AS is_active,
                         ci.created_by, ci.updated_at,
                         co.name AS country_name, st.name AS state_name
                  FROM cities ci
                  INNER JOIN countries co ON co.id = ci.country_id
                  LEFT JOIN states st ON st.id = ci.state_id
                  WHERE {$where}
                  ORDER BY ci.name ASC
                  LIMIT 5000";
    $exportRes = $conn->query($exportSql);
    if ($exportRes) {
        while ($row = $exportRes->fetch_assoc()) {
            fputcsv($out, [
                (int) $row['id'],
                (string) ($row['country_name'] ?? ''),
                (string) ($row['state_name'] ?? ''),
                (string) ($row['name'] ?? ''),
                ((int) ($row['is_active'] ?? 1) === 1) ? 'Active' : 'Inactive',
                cityMasterFormatDate($row['created_at'] ?? ''),
                cityMasterFormatDate($row['updated_at'] ?? $row['created_at'] ?? ''),
                (string) ($row['created_by'] ?? ''),
            ]);
        }
    }
    fclose($out);
    exit;
}

$flashMsg = '';
$flashType = 'success';
if (!empty($_SESSION['city_flash'])) {
    $flashMsg = (string) $_SESSION['city_flash'];
    $flashType = !empty($_SESSION['city_flash_type']) ? (string) $_SESSION['city_flash_type'] : 'success';
    unset($_SESSION['city_flash'], $_SESSION['city_flash_type']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <base href="../">
    <title>City Master</title>
    <?php include __DIR__ . '/../includes/header-links.php'; ?>
    <style>
        .crm-city-master {
            --cm-accent: #e11d2e;
            --cm-accent-soft: #fee2e2;
            --cm-border: #e8edf3;
            --cm-muted: #94a3b8;
            --cm-text: #0f172a;
        }
        .crm-city-master .content-wrapper,
        .crm-city-master .content-wrapper > .content {
            background: #f5f6f8;
        }
        .crm-city-master .content-header { display: none; }

        .crm-city-master .page-title-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 1rem;
            flex-wrap: wrap;
            margin-bottom: 1.2rem;
        }
        .crm-city-master .page-title {
            margin: 0;
            font-size: 1.85rem;
            font-weight: 700;
            color: var(--cm-text);
            letter-spacing: -0.02em;
        }
        .crm-city-master .page-subtitle {
            margin: 0.35rem 0 0;
            color: #64748b;
            font-size: 0.95rem;
            font-weight: 500;
        }
        .crm-city-master .btn-add-city {
            background: var(--cm-accent);
            border: none;
            color: #fff !important;
            font-weight: 700;
            padding: 0.65rem 1.15rem;
            border-radius: 10px;
            box-shadow: 0 8px 18px rgba(225, 29, 46, 0.22);
        }
        .crm-city-master .btn-add-city:hover {
            background: #c91020;
            color: #fff !important;
        }
        .crm-city-master .cm-head-actions { display: flex; gap: 0.65rem; flex-wrap: wrap; }
        .crm-city-master .btn-manage-countries {
            background: #fff;
            border: 1px solid #d9e0e8;
            color: #334155 !important;
            font-weight: 700;
            padding: 0.65rem 1.15rem;
            border-radius: 10px;
        }
        .crm-city-master .btn-manage-countries:hover {
            border-color: var(--cm-accent);
            color: var(--cm-accent) !important;
        }

        .crm-city-master .cm-kpi-row {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 1rem;
            margin-bottom: 1.15rem;
        }
        .crm-city-master .cm-kpi-card {
            background: #fff;
            border: 1px solid var(--cm-border);
            border-radius: 14px;
            box-shadow: 0 6px 18px rgba(15, 23, 42, 0.04);
            padding: 1rem 1.05rem;
            display: flex;
            align-items: flex-start;
            gap: 0.85rem;
            min-width: 0;
        }
        .crm-city-master .cm-kpi-icon {
            width: 42px;
            height: 42px;
            border-radius: 11px;
            background: var(--cm-accent-soft);
            color: var(--cm-accent);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 1rem;
        }
        .crm-city-master .cm-kpi-body { min-width: 0; flex: 1 1 auto; }
        .crm-city-master .cm-kpi-label {
            font-size: 0.78rem;
            color: #64748b;
            font-weight: 600;
            margin-bottom: 0.15rem;
        }
        .crm-city-master .cm-kpi-value {
            font-size: 1.55rem;
            font-weight: 700;
            color: var(--cm-text);
            line-height: 1.15;
            margin-bottom: 0.2rem;
        }
        .crm-city-master .cm-kpi-sub {
            font-size: 0.75rem;
            color: var(--cm-muted);
            font-weight: 500;
        }
        .crm-city-master .cm-kpi-trend {
            margin-left: auto;
            color: var(--cm-accent);
            font-size: 0.82rem;
            font-weight: 700;
            white-space: nowrap;
            padding-top: 0.15rem;
        }

        .crm-city-master .cm-panel {
            background: #fff;
            border: 1px solid var(--cm-border);
            border-radius: 14px;
            box-shadow: 0 8px 22px rgba(15, 23, 42, 0.045);
            overflow: hidden;
        }
        .crm-city-master .cm-toolbar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.65rem;
            padding: 1rem 1.1rem;
            border-bottom: 1px solid #eef2f7;
        }
        .crm-city-master .cm-search {
            position: relative;
            flex: 1 1 280px;
            min-width: 220px;
            max-width: 420px;
        }
        .crm-city-master .cm-search > i {
            position: absolute;
            left: 0.9rem;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            z-index: 2;
        }
        .crm-city-master .cm-search .form-control {
            height: 42px;
            border-radius: 10px;
            border-color: #e2e8f0;
            padding-left: 2.35rem;
            padding-right: 3.6rem;
            font-size: 0.9rem;
            box-shadow: none !important;
        }
        .crm-city-master .cm-search .form-control:focus {
            border-color: #fecaca;
            box-shadow: 0 0 0 3px rgba(225, 29, 46, 0.08) !important;
        }
        .crm-city-master .cm-search-kbd {
            position: absolute;
            right: 0.7rem;
            top: 50%;
            transform: translateY(-50%);
            font-size: 0.72rem;
            font-weight: 700;
            color: #94a3b8;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 0.15rem 0.4rem;
            pointer-events: none;
        }
        .crm-city-master .cm-filter-btn,
        .crm-city-master .cm-tool-btn {
            height: 42px;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            background: #fff;
            color: #475569;
            font-weight: 600;
            font-size: 0.86rem;
            padding: 0 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }
        .crm-city-master .cm-filter-btn:hover,
        .crm-city-master .cm-tool-btn:hover {
            background: #f8fafc;
            color: #0f172a;
        }
        .crm-city-master .cm-filter-btn select {
            border: 0;
            background: transparent;
            color: inherit;
            font-weight: 600;
            outline: none;
            cursor: pointer;
            max-width: 150px;
        }
        .crm-city-master .cm-toolbar-right {
            margin-left: auto;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        .crm-city-master .cm-tool-btn.icon-only {
            width: 42px;
            padding: 0;
            justify-content: center;
        }

        .crm-city-master .table-wrap { overflow-x: auto; }
        .crm-city-master table.city-master-table {
            width: 100%;
            margin: 0;
            border-collapse: collapse;
            font-size: 0.86rem;
            min-width: 980px;
        }
        .crm-city-master table.city-master-table thead th {
            background: #f8fafc;
            color: #64748b;
            font-weight: 700;
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            padding: 0.85rem 0.85rem;
            border-bottom: 1px solid #eef2f7;
            white-space: nowrap;
            vertical-align: middle;
        }
        .crm-city-master table.city-master-table thead th .cm-sort {
            margin-left: 0.25rem;
            color: #cbd5e1;
            font-size: 0.65rem;
        }
        .crm-city-master table.city-master-table tbody td {
            padding: 0.9rem 0.85rem;
            vertical-align: middle;
            border-bottom: 1px solid #f1f5f9;
            color: #334155;
            background: #fff;
            white-space: nowrap;
        }
        .crm-city-master table.city-master-table tbody tr:nth-child(even) td { background: #fafbfc; }
        .crm-city-master table.city-master-table tbody tr:hover td { background: #fff7f7; }
        .crm-city-master .cm-flag {
            border-radius: 2px;
            vertical-align: -2px;
            box-shadow: 0 0 0 1px rgba(15, 23, 42, 0.08);
        }
        .crm-city-master .cm-flag-fallback {
            display: inline-flex;
            width: 20px;
            height: 15px;
            align-items: center;
            justify-content: center;
            color: #94a3b8;
            font-size: 0.75rem;
        }
        .crm-city-master .cm-status {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 0.22rem 0.65rem;
            font-size: 0.75rem;
            font-weight: 700;
        }
        .crm-city-master .cm-status.is-active {
            background: #dcfce7;
            color: #15803d;
        }
        .crm-city-master .cm-status.is-inactive {
            background: #ffedd5;
            color: #c2410c;
        }
        .crm-city-master .cm-status.is-deleted {
            background: #fee2e2;
            color: #b91c1c;
        }
        .crm-city-master .action-btns .btn-restore {
            color: #16a34a !important;
            border-color: #bbf7d0;
        }
        .crm-city-master .action-btns .btn-restore:hover {
            background: #f0fdf4;
            color: #15803d !important;
        }
        .crm-city-master .action-btns .btn-purge {
            color: #e11d2e !important;
            border-color: #fecaca;
        }
        .crm-city-master .action-btns .btn-purge:hover {
            background: #fef2f2;
            color: #b91c1c !important;
        }
        .crm-city-master .cm-tool-btn.btn-bulk-restore {
            color: #16a34a;
            border-color: #bbf7d0;
            background: #fff;
        }
        .crm-city-master .cm-tool-btn.btn-bulk-restore:hover:not(:disabled) {
            background: #f0fdf4;
            color: #15803d;
        }
        .crm-city-master .cm-tool-btn.btn-bulk-purge {
            color: #e11d2e;
            border-color: #fecaca;
            background: #fff;
        }
        .crm-city-master .cm-tool-btn.btn-bulk-purge:hover:not(:disabled) {
            background: #fef2f2;
            color: #b91c1c;
        }
        .crm-city-master .cm-tool-btn.btn-bulk-restore:disabled,
        .crm-city-master .cm-tool-btn.btn-bulk-purge:disabled {
            opacity: 0.45;
            cursor: not-allowed;
        }
        .crm-city-master .action-btns {
            display: inline-flex;
            gap: 0.35rem;
            align-items: center;
        }
        .crm-city-master .action-btns .btn-icon {
            width: 32px;
            height: 32px;
            padding: 0;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #e2e8f0;
            background: #fff;
            color: #64748b !important;
            font-size: 0.78rem;
        }
        .crm-city-master .action-btns .btn-view:hover { background: #eff6ff; color: #2563eb !important; border-color: #bfdbfe; }
        .crm-city-master .action-btns .btn-edit:hover { background: #f0fdf4; color: #16a34a !important; border-color: #bbf7d0; }
        .crm-city-master .cm-check-col {
            width: 42px;
            text-align: center;
        }
        .crm-city-master .cm-row-check,
        .crm-city-master .cm-check-all {
            width: 16px;
            height: 16px;
            cursor: pointer;
            accent-color: #e11d2e;
        }
        .crm-city-master .cm-tool-btn.btn-bulk-del {
            color: #e11d2e;
            border-color: #fecaca;
            background: #fff;
        }
        .crm-city-master .cm-tool-btn.btn-bulk-del:hover:not(:disabled) {
            background: #fef2f2;
            color: #b91c1c;
        }
        .crm-city-master .cm-tool-btn.btn-bulk-del:disabled {
            opacity: 0.45;
            cursor: not-allowed;
        }
        .crm-city-master .action-btns .btn-del:hover { background: #fef2f2; color: #b91c1c !important; }
        .crm-city-master .action-btns .btn-more:hover { background: #f8fafc; color: #0f172a !important; }

        .crm-city-master .pagination-bar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            padding: 0.9rem 1.1rem;
            border-top: 1px solid #eef2f7;
            background: #fafbfc;
            font-size: 0.875rem;
            color: #64748b;
        }
        .crm-city-master .pagination-bar .page-link {
            min-width: 34px;
            text-align: center;
            border-radius: 8px;
            color: #475569;
            border-color: #e2e8f0;
        }
        .crm-city-master .pagination-bar .page-item.active .page-link {
            background: var(--cm-accent);
            border-color: var(--cm-accent);
            color: #fff;
        }
        .crm-city-master .empty-state {
            padding: 2.25rem 1rem;
            text-align: center;
            color: #94a3b8;
            font-weight: 500;
        }

        /* Modals */
        #cityFormModal .city-modal-dialog,
        #cityViewModal .city-view-dialog {
            max-width: 520px;
            width: calc(100% - 1.5rem);
            margin: 1rem auto;
        }
        #cityFormModal .modal-content.city-modal-shell,
        #cityViewModal .modal-content.city-view-shell {
            border: none;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.18);
            max-height: calc(100vh - 2rem);
        }
        #cityFormModal .modal-header.city-modal-hd,
        #cityViewModal .modal-header.city-view-hd {
            background: linear-gradient(125deg, #b91c1c 0%, #e11d2e 55%, #f43f5e 100%);
            color: #fff;
            border: none;
            padding: 1rem 1.25rem;
        }
        #cityFormModal .modal-header.city-modal-hd .modal-title,
        #cityViewModal .modal-header.city-view-hd .modal-title {
            font-weight: 800;
            font-size: 1.1rem;
        }
        #cityFormModal .modal-header.city-modal-hd .close,
        #cityViewModal .modal-header.city-view-hd .close {
            color: #fff;
            text-shadow: none;
            opacity: 0.9;
        }
        #cityFormModal .modal-body.city-modal-bd,
        #cityViewModal .modal-body.city-view-bd {
            padding: 1rem 1.35rem;
            background: #fff;
        }
        #cityFormModal .modal-body label {
            font-weight: 700;
            color: #334155;
            font-size: 0.85rem;
            margin-bottom: 0.35rem;
        }
        #cityFormModal .label-req::after {
            content: " *";
            color: #e11d2e;
        }
        #cityFormModal .modal-body .form-control {
            border-radius: 8px;
            border-color: #e2e8f0;
        }
        #cityFormModal .modal-body .form-control:focus {
            border-color: #fca5a5;
            box-shadow: 0 0 0 3px rgba(225, 29, 46, 0.12);
        }
        #cityFormModal .modal-footer.city-modal-ft,
        #cityViewModal .modal-footer.city-view-ft {
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            padding: 0.85rem 1.25rem 1rem;
            gap: 0.5rem;
        }
        #cityFormModal .btn-city-primary {
            background: var(--cm-accent);
            border: none;
            color: #fff;
            font-weight: 700;
            padding: 0.5rem 1.2rem;
            border-radius: 8px;
            box-shadow: 0 4px 14px rgba(225, 29, 46, 0.28);
        }
        #cityFormModal .btn-city-primary:hover { color: #fff; background: #c91020; }
        #cityFormModal .btn-city-ghost,
        #cityViewModal .btn-city-ghost {
            background: #fff;
            border: 1px solid #e2e8f0;
            color: #475569;
            font-weight: 700;
            padding: 0.5rem 1.1rem;
            border-radius: 8px;
        }
        #cityViewModal .city-view-table { margin-bottom: 0; font-size: 0.9rem; }
        #cityViewModal .city-view-table th {
            width: 38%;
            background: #f8fafc;
            font-weight: 700;
            vertical-align: middle;
            padding: 0.55rem 0.75rem;
        }
        #cityViewModal .city-view-table td {
            vertical-align: middle;
            padding: 0.55rem 0.75rem;
            color: #334155;
        }
        #cityFormModal, #cityViewModal, #countryManagerModal { z-index: 1055; }
        #countryManagerModal .modal-content { border: 0; border-radius: 14px; overflow: hidden; }
        #countryManagerModal .modal-header {
            background: linear-gradient(125deg, #b91c1c 0%, #e11d2e 55%, #f43f5e 100%);
            color: #fff;
            border: 0;
        }
        #countryManagerModal .modal-header .close { color: #fff; opacity: 0.9; }
        #countryManagerModal .country-search { position: relative; max-width: 360px; }
        #countryManagerModal .country-search i {
            position: absolute; left: 13px; top: 50%; transform: translateY(-50%); color: #94a3b8;
        }
        #countryManagerModal .country-search input { padding-left: 2.25rem; border-radius: 9px; }
        #countryManagerModal .country-table th {
            border-top: 0; background: #f8fafc; color: #64748b; font-size: 0.75rem; text-transform: uppercase;
        }
        #countryManagerModal .country-table td { vertical-align: middle; }
        #countryManagerModal .country-actions { display: flex; justify-content: flex-end; gap: 0.4rem; }
        #countryManagerModal .country-action {
            width: 32px; height: 32px; border-radius: 50%; border: 1px solid #e2e8f0;
            background: #fff; color: #64748b; display: inline-flex; align-items: center; justify-content: center;
        }
        #countryManagerModal .country-action:hover { color: #e11d2e; border-color: #fecaca; }

        @media (max-width: 1199.98px) {
            .crm-city-master .cm-kpi-row { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 767.98px) {
            .crm-city-master .cm-kpi-row { grid-template-columns: 1fr; }
            .crm-city-master .cm-toolbar-right { margin-left: 0; width: 100%; }
            .crm-city-master .cm-search { max-width: none; }
        }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
    <div class="wrapper crm-city-master">
        <?php include __DIR__ . '/../includes/top-header.php'; ?>
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>

        <div class="content-wrapper">
            <section class="content pt-3">
                <div class="container-fluid">
                    <div class="page-title-row">
                        <div>
                            <h1 class="page-title">City Master</h1>
                            <p class="page-subtitle">Manage global cities and airport information</p>
                        </div>
                        <div class="cm-head-actions">
                            <button type="button" class="btn btn-manage-countries" data-toggle="modal" data-target="#countryManagerModal">
                                <i class="fas fa-globe mr-1"></i> Manage Countries
                            </button>
                            <button type="button" class="btn btn-add-city" id="btnOpenCityModal" data-toggle="modal" data-target="#cityFormModal">
                                <i class="fas fa-plus mr-1"></i> Add New City
                            </button>
                        </div>
                    </div>

                    <?php if ($flashMsg !== ''): ?>
                        <div class="alert alert-<?= htmlspecialchars($flashType) ?> alert-dismissible fade show shadow-sm" role="alert">
                            <?= htmlspecialchars($flashMsg) ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                        </div>
                    <?php endif; ?>

                    <div class="cm-kpi-row">
                        <?php foreach ($kpiCards as $kpi): ?>
                            <div class="cm-kpi-card">
                                <span class="cm-kpi-icon"><i class="<?= htmlspecialchars($kpi['icon'], ENT_QUOTES, 'UTF-8') ?>"></i></span>
                                <div class="cm-kpi-body">
                                    <div class="cm-kpi-label"><?= htmlspecialchars($kpi['label'], ENT_QUOTES, 'UTF-8') ?></div>
                                    <div class="cm-kpi-value"><?= htmlspecialchars($kpi['value'], ENT_QUOTES, 'UTF-8') ?></div>
                                    <div class="cm-kpi-sub"><?= htmlspecialchars($kpi['sub'], ENT_QUOTES, 'UTF-8') ?></div>
                                </div>
                                <div class="cm-kpi-trend">↑ <?= number_format((float) $kpi['trend'], 1) ?>%</div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="cm-panel">
                        <form method="get" class="cm-toolbar" id="cityFilterForm">
                            <div class="cm-search">
                                <i class="fas fa-search"></i>
                                <input type="search" class="form-control" name="q" id="citySearchInput"
                                       value="<?= htmlspecialchars($search) ?>"
                                       placeholder="Search cities, states, countries..."
                                       autocomplete="off">
                                <span class="cm-search-kbd">⌘ K</span>
                            </div>

                            <label class="cm-filter-btn mb-0">
                                <i class="fas fa-globe"></i>
                                <select name="country_id" onchange="this.form.submit()">
                                    <option value="0">All Countries</option>
                                    <?php foreach ($countries as $country): ?>
                                        <option value="<?= (int) $country['id'] ?>" <?= $countryFilter === (int) $country['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($country['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>

                            <label class="cm-filter-btn mb-0">
                                <i class="fas fa-circle" style="font-size:0.55rem;"></i>
                                <select name="status" onchange="this.form.submit()">
                                    <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Status</option>
                                    <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
                                    <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                    <option value="deleted" <?= $statusFilter === 'deleted' ? 'selected' : '' ?>>Deleted (<?= (int) $deletedCount ?>)</option>
                                </select>
                            </label>

                            <div class="cm-toolbar-right">
                                <?php if ($showingDeleted): ?>
                                    <button type="button" class="cm-tool-btn btn-bulk-restore js-cm-bulk-restore" disabled title="Restore selected cities">
                                        <i class="fas fa-undo"></i> Restore Selected
                                    </button>
                                    <button type="button" class="cm-tool-btn btn-bulk-purge js-cm-bulk-purge" disabled title="Permanently delete selected cities">
                                        <i class="fas fa-times-circle"></i> Permanent Delete
                                    </button>
                                <?php else: ?>
                                    <button type="button" class="cm-tool-btn btn-bulk-del js-cm-bulk-delete" disabled title="Soft delete selected cities">
                                        <i class="fas fa-trash-alt"></i> Delete Selected
                                    </button>
                                <?php endif; ?>
                                <a class="cm-tool-btn" href="<?= htmlspecialchars(cityMasterPageUrl($listPage, $countryFilter, $search, $statusFilter, ['export' => 1])) ?>">
                                    <i class="fas fa-download"></i> Export
                                </a>
                            </div>
                        </form>

                        <div class="table-wrap">
                            <table class="city-master-table mb-0">
                                <thead>
                                    <tr>
                                        <th class="cm-check-col">
                                            <input type="checkbox" class="cm-check-all" title="Select all" aria-label="Select all cities">
                                        </th>
                                        <?php
                                        $headers = ['ID', 'Country', 'State', 'City Name', 'Status', 'Created On', 'Last Updated', 'Created By', 'Total Leads', 'Actions'];
                                        foreach ($headers as $header):
                                        ?>
                                            <th>
                                                <?= htmlspecialchars($header) ?>
                                                <?php if ($header !== 'Actions'): ?>
                                                    <span class="cm-sort"><i class="fas fa-sort"></i></span>
                                                <?php endif; ?>
                                            </th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($cities)): ?>
                                        <tr>
                                            <td colspan="11" class="empty-state">No cities found.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($cities as $city): ?>
                                            <?php
                                            $createdAt = cityMasterFormatDate($city['created_at'] ?? '');
                                            $updatedAt = cityMasterFormatDate($city['updated_at'] ?? ($city['created_at'] ?? ''));
                                            $airport = trim((string) ($city['airport_code'] ?? ''));
                                            $isActive = (int) ($city['is_active'] ?? 1) === 1;
                                            $isDeleted = (int) ($city['is_deleted'] ?? 0) === 1;
                                            $timezone = trim((string) ($city['timezone'] ?? ''));
                                            $region = trim((string) ($city['region'] ?? ''));
                                            $createdBy = trim((string) ($city['created_by'] ?? ''));
                                            if ($createdBy === '') {
                                                $createdBy = 'System';
                                            }
                                            ?>
                                            <tr data-id="<?= (int) $city['id'] ?>"
                                                data-country-id="<?= (int) $city['country_id'] ?>"
                                                data-state-id="<?= (int) ($city['state_id'] ?? 0) ?>"
                                                data-country="<?= htmlspecialchars($city['country_name'], ENT_QUOTES, 'UTF-8') ?>"
                                                data-state="<?= htmlspecialchars($city['state_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                                data-name="<?= htmlspecialchars($city['name'], ENT_QUOTES, 'UTF-8') ?>"
                                                data-airport="<?= htmlspecialchars($airport, ENT_QUOTES, 'UTF-8') ?>"
                                                data-timezone="<?= htmlspecialchars($timezone, ENT_QUOTES, 'UTF-8') ?>"
                                                data-region="<?= htmlspecialchars($region, ENT_QUOTES, 'UTF-8') ?>"
                                                data-active="<?= $isActive ? '1' : '0' ?>"
                                                data-deleted="<?= $isDeleted ? '1' : '0' ?>"
                                                data-created="<?= htmlspecialchars($createdAt, ENT_QUOTES, 'UTF-8') ?>"
                                                data-updated="<?= htmlspecialchars($updatedAt, ENT_QUOTES, 'UTF-8') ?>"
                                                data-created-by="<?= htmlspecialchars($createdBy, ENT_QUOTES, 'UTF-8') ?>">
                                                <td class="cm-check-col">
                                                    <input type="checkbox" class="cm-row-check" value="<?= (int) $city['id'] ?>" aria-label="Select city <?= (int) $city['id'] ?>">
                                                </td>
                                                <td><?= (int) $city['id'] ?></td>
                                                <td><?= cityMasterFlagHtml($city['country_iso2'] ?? '', $city['country_name'] ?? '') ?></td>
                                                <td><?= !empty($city['state_name']) ? htmlspecialchars($city['state_name']) : '—' ?></td>
                                                <td><strong><?= htmlspecialchars($city['name']) ?></strong></td>
                                                <td>
                                                    <?php if ($isDeleted): ?>
                                                        <span class="cm-status is-deleted">Deleted</span>
                                                    <?php elseif ($isActive): ?>
                                                        <span class="cm-status is-active">Active</span>
                                                    <?php else: ?>
                                                        <span class="cm-status is-inactive">Inactive</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= htmlspecialchars($createdAt) ?></td>
                                                <td><?= htmlspecialchars($updatedAt) ?></td>
                                                <td><?= htmlspecialchars($createdBy) ?></td>
                                                <td>0</td>
                                                <td>
                                                    <div class="action-btns">
                                                        <?php if ($showingDeleted): ?>
                                                            <button type="button" class="btn-icon btn-restore" title="Restore"><i class="fas fa-undo"></i></button>
                                                            <button type="button" class="btn-icon btn-purge" title="Permanent Delete"><i class="fas fa-times"></i></button>
                                                        <?php else: ?>
                                                            <button type="button" class="btn-icon btn-view" title="View"><i class="far fa-eye"></i></button>
                                                            <button type="button" class="btn-icon btn-edit" title="Edit"><i class="fas fa-pencil-alt"></i></button>
                                                            <button type="button" class="btn-icon btn-del" title="Delete"><i class="fas fa-trash-alt"></i></button>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if ($totalRows > 0): ?>
                            <div class="pagination-bar">
                                <div>
                                    Showing <?= number_format($offset + 1) ?>–<?= number_format(min($offset + $perPage, $totalRows)) ?> of <?= number_format($totalRows) ?> cities
                                </div>
                                <?php if ($totalPages > 1): ?>
                                    <nav aria-label="City pagination">
                                        <ul class="pagination pagination-sm mb-0">
                                            <li class="page-item <?= $listPage <= 1 ? 'disabled' : '' ?>">
                                                <a class="page-link" href="<?= htmlspecialchars(cityMasterPageUrl($listPage - 1, $countryFilter, $search, $statusFilter)) ?>">Prev</a>
                                            </li>
                                            <?php
                                            $startPage = max(1, $listPage - 2);
                                            $endPage = min($totalPages, $listPage + 2);
                                            for ($p = $startPage; $p <= $endPage; $p++):
                                            ?>
                                                <li class="page-item <?= $p === $listPage ? 'active' : '' ?>">
                                                    <a class="page-link" href="<?= htmlspecialchars(cityMasterPageUrl($p, $countryFilter, $search, $statusFilter)) ?>"><?= $p ?></a>
                                                </li>
                                            <?php endfor; ?>
                                            <li class="page-item <?= $listPage >= $totalPages ? 'disabled' : '' ?>">
                                                <a class="page-link" href="<?= htmlspecialchars(cityMasterPageUrl($listPage + 1, $countryFilter, $search, $statusFilter)) ?>">Next</a>
                                            </li>
                                        </ul>
                                    </nav>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
        </div>
    </div>

    <div class="modal fade" id="cityFormModal" tabindex="-1" role="dialog" aria-labelledby="cityFormModalLabel" aria-hidden="true" data-backdrop="static">
        <div class="modal-dialog modal-dialog-centered city-modal-dialog" role="document">
            <div class="modal-content city-modal-shell">
                <form id="cityFormInner" action="#" method="post" onsubmit="return false;">
                    <input type="hidden" id="cityFormId" name="id" value="">
                    <div class="modal-header city-modal-hd text-white">
                        <h5 class="modal-title mb-0" id="cityFormModalLabel">City Information</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    </div>
                    <div class="modal-body city-modal-bd">
                        <div class="form-group">
                            <label class="label-req" for="cityFormCountry">Country</label>
                            <select class="form-control" id="cityFormCountry" name="country_id" required>
                                <option value="" selected disabled>Select Country</option>
                                <?php foreach ($countries as $country): ?>
                                    <option value="<?= (int) $country['id'] ?>"><?= htmlspecialchars($country['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="cityFormState">State</label>
                            <select class="form-control" id="cityFormState" name="state_id">
                                <option value="">Select State (optional)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="label-req" for="cityFormName">City Name</label>
                            <input type="text" class="form-control" id="cityFormName" name="city_name" placeholder="Enter city name" required>
                        </div>
                        <div class="form-group mb-0">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="cityFormActive" name="is_active" value="1" checked>
                                <label class="custom-control-label" for="cityFormActive">Active</label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer city-modal-ft justify-content-start flex-wrap">
                        <button type="submit" class="btn btn-city-primary" id="cityFormSubmitBtn"><i class="fas fa-save mr-2"></i><span id="cityFormSubmitText">Create</span></button>
                        <button type="button" class="btn btn-city-ghost" data-dismiss="modal"><i class="fas fa-times mr-2"></i>Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="cityViewModal" tabindex="-1" role="dialog" aria-labelledby="cityViewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered city-view-dialog" role="document">
            <div class="modal-content city-view-shell">
                <div id="cityViewInner">
                    <div class="modal-header city-view-hd text-white">
                        <h5 class="modal-title mb-0" id="cityViewModalLabel">View City</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    </div>
                    <div class="modal-body city-view-bd">
                        <p class="font-weight-bold mb-2" style="font-size: 0.95rem; color: #222;">City Information</p>
                        <table class="table table-bordered city-view-table">
                            <tbody>
                                <tr><th scope="row">ID</th><td id="cityViewId">—</td></tr>
                                <tr><th scope="row">Country</th><td id="cityViewCountry">—</td></tr>
                                <tr><th scope="row">State</th><td id="cityViewState">—</td></tr>
                                <tr><th scope="row">City Name</th><td id="cityViewName">—</td></tr>
                                <tr><th scope="row">Status</th><td id="cityViewStatus">—</td></tr>
                                <tr><th scope="row">Created On</th><td id="cityViewCreated">—</td></tr>
                                <tr><th scope="row">Last Updated</th><td id="cityViewUpdated">—</td></tr>
                                <tr><th scope="row">Created By</th><td id="cityViewCreatedBy">—</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="modal-footer city-view-ft justify-content-start flex-wrap">
                        <button type="button" class="btn btn-city-ghost" data-dismiss="modal"><i class="fas fa-times mr-2"></i>Close</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="countryManagerModal" tabindex="-1" role="dialog" aria-labelledby="countryManagerModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title mb-1" id="countryManagerModalLabel">Manage Countries</h5>
                        <small class="text-white-50">Delete, restore, or permanently remove countries</small>
                    </div>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body p-0">
                    <div class="d-flex justify-content-between align-items-center flex-wrap p-3 border-bottom">
                        <div class="country-search">
                            <i class="fas fa-search"></i>
                            <input type="search" class="form-control" id="countryManagerSearch" placeholder="Search countries...">
                        </div>
                        <small class="text-muted mt-2 mt-sm-0">Permanent delete also removes its states and cities.</small>
                    </div>
                    <div class="table-responsive" style="max-height: 480px;">
                        <table class="table table-hover country-table mb-0">
                            <thead>
                                <tr>
                                    <th>Country</th>
                                    <th>Code</th>
                                    <th>Cities</th>
                                    <th>Status</th>
                                    <th class="text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($countryRows)): ?>
                                    <tr><td colspan="5" class="text-center text-muted py-4">No countries found.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($countryRows as $country): ?>
                                        <?php $countryDeleted = (int) ($country['is_deleted'] ?? 0) === 1; ?>
                                        <tr class="js-country-row"
                                            data-id="<?= (int) $country['id'] ?>"
                                            data-name="<?= htmlspecialchars($country['name'], ENT_QUOTES, 'UTF-8') ?>">
                                            <td><strong><?= cityMasterFlagHtml($country['iso2'] ?? '', $country['name'] ?? '') ?></strong></td>
                                            <td><?= htmlspecialchars(strtoupper((string) ($country['iso2'] ?? '—'))) ?></td>
                                            <td><?= number_format((int) ($country['city_count'] ?? 0)) ?></td>
                                            <td>
                                                <?php if ($countryDeleted): ?>
                                                    <span class="badge badge-secondary px-2 py-1">Deleted</span>
                                                <?php else: ?>
                                                    <span class="badge badge-success px-2 py-1">Active</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="country-actions">
                                                    <?php if ($countryDeleted): ?>
                                                        <button type="button" class="country-action js-country-restore" title="Restore country"><i class="fas fa-undo"></i></button>
                                                        <button type="button" class="country-action js-country-purge" title="Permanently delete country"><i class="fas fa-times"></i></button>
                                                    <?php else: ?>
                                                        <button type="button" class="country-action js-country-delete" title="Delete country"><i class="fas fa-trash-alt"></i></button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light border" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../includes/footer-links.php'; ?>

<script>
(function ($) {
	'use strict';

	$(function () {
		if (!$ || !$.fn || !$.fn.modal) {
			console.error('City Master: jQuery/Bootstrap modal not available.');
			return;
		}

		var $form = $('#cityFormInner');
		var $country = $('#cityFormCountry');
		var $state = $('#cityFormState');
		var $cityId = $('#cityFormId');
		var $modal = $('#cityFormModal');
		var saveUrl = 'crm/ajax/save_city.php';
		var deleteUrl = 'crm/ajax/delete_city.php';
		var deleteCountryUrl = 'crm/ajax/delete_country.php';
		var statesUrl = '../ajax/get_states_by_country.php';
		var skipFormReset = false;

	function resetStateSelect() {
		$state.empty().append('<option value="">Select State (optional)</option>');
	}

	function loadStates(countryId, selectedStateId) {
		resetStateSelect();
		if (!countryId) {
			return $.Deferred().resolve().promise();
		}
		return $.getJSON(statesUrl, { country_id: countryId }).done(function (res) {
			if (!res.success || !res.data) {
				return;
			}
			res.data.forEach(function (item) {
				var $opt = $('<option></option>').val(item.id).text(item.state_name);
				if (selectedStateId && String(item.id) === String(selectedStateId)) {
					$opt.prop('selected', true);
				}
				$state.append($opt);
			});
		});
	}

	function resetCityForm() {
		var formEl = document.getElementById('cityFormInner');
		if (formEl) {
			formEl.reset();
		}
		$cityId.val('');
		resetStateSelect();
		$('#cityFormActive').prop('checked', true);
		$('#cityFormModalLabel').text('City Information');
		$('#cityFormSubmitText').text('Create');
	}

	function showCityFormModal() {
		if (!$modal.length) {
			return;
		}
		$modal.modal('show');
	}

	function openCityFormModal(mode, $tr) {
		if (mode === 'edit' && $tr && $tr.length) {
			skipFormReset = true;
			resetCityForm();
			$cityId.val($tr.data('id') || '');
			$country.val(String($tr.data('country-id') || ''));
			$('#cityFormName').val($tr.data('name') || '');
			$('#cityFormActive').prop('checked', Number($tr.data('active')) !== 0);
			$('#cityFormModalLabel').text('Edit City');
			$('#cityFormSubmitText').text('Update');
			loadStates($tr.data('country-id'), $tr.data('state-id') || '');
			showCityFormModal();
			return;
		}
		skipFormReset = false;
		resetCityForm();
		showCityFormModal();
	}

	$modal.on('show.bs.modal', function () {
		if (!skipFormReset) {
			resetCityForm();
		}
		skipFormReset = false;
	});

	$country.on('change', function () {
		loadStates($(this).val(), '');
	});

	$('#btnOpenCityModal').on('click', function () {
		skipFormReset = false;
	});

	$form.on('submit', function () {
		var $btn = $('#cityFormSubmitBtn');
		$btn.prop('disabled', true);
		var payload = $form.serialize();
		if (!$('#cityFormActive').is(':checked')) {
			payload += '&is_active=0';
		}
		$.ajax({
			url: saveUrl,
			type: 'POST',
			dataType: 'json',
			data: payload
		}).done(function (res) {
			if (res.success) {
				$modal.modal('hide');
				window.location.reload();
				return;
			}
			alert(res.message || 'Could not save city.');
		}).fail(function () {
			alert('Could not save city. Please try again.');
		}).always(function () {
			$btn.prop('disabled', false);
		});
		return false;
	});

	function openCityViewModal($tr) {
		var $viewModal = $('#cityViewModal');
		$('#cityViewId').text($tr.data('id') || '—');
		$('#cityViewCountry').text($tr.data('country') || '—');
		$('#cityViewState').text($tr.data('state') || '—');
		$('#cityViewName').text($tr.data('name') || '—');
		$('#cityViewStatus').text(Number($tr.data('active')) === 1 ? 'Active' : 'Inactive');
		$('#cityViewCreated').text($tr.data('created') || '—');
		$('#cityViewUpdated').text($tr.data('updated') || '—');
		$('#cityViewCreatedBy').text($tr.data('created-by') || '—');
		$viewModal.modal('show');
	}

	function selectedCityIds() {
		var ids = [];
		$('.city-master-table .cm-row-check:checked').each(function () {
			var id = parseInt($(this).val(), 10);
			if (id > 0) {
				ids.push(id);
			}
		});
		return ids;
	}

	function syncBulkDeleteState() {
		var ids = selectedCityIds();
		var $all = $('.city-master-table .cm-row-check');
		var $checked = $('.city-master-table .cm-row-check:checked');
		$('.js-cm-bulk-delete, .js-cm-bulk-restore, .js-cm-bulk-purge').prop('disabled', ids.length === 0);
		$('.cm-check-all').prop('checked', $all.length > 0 && $checked.length === $all.length);
		$('.cm-check-all').prop('indeterminate', $checked.length > 0 && $checked.length < $all.length);
	}

	function runCityDeleteAction(mode, ids) {
		if (!ids || !ids.length) {
			return;
		}
		var count = ids.length;
		var label = count > 1 ? (count + ' cities') : 'this city';
		var confirmMsg = 'Soft delete ' + label + '? They will be moved to Deleted.';
		if (mode === 'restore') {
			confirmMsg = 'Restore ' + label + '?';
		} else if (mode === 'permanent') {
			confirmMsg = 'Permanently delete ' + label + '? This cannot be undone.';
		}
		if (!window.confirm(confirmMsg)) {
			return;
		}
		var data = 'mode=' + encodeURIComponent(mode);
		if (ids.length === 1) {
			data += '&id=' + encodeURIComponent(ids[0]);
		} else {
			data += '&' + ids.map(function (id) { return 'ids[]=' + encodeURIComponent(id); }).join('&');
		}
		$.ajax({
			url: deleteUrl,
			type: 'POST',
			dataType: 'json',
			data: data
		}).done(function (res) {
			if (res.success) {
				window.location.reload();
				return;
			}
			alert(res.message || 'Could not complete action.');
		}).fail(function () {
			alert('Could not complete action. Please try again.');
		});
	}

	function runCountryDeleteAction(mode, $row) {
		var id = parseInt($row.data('id'), 10);
		var name = String($row.data('name') || 'this country');
		if (!id) {
			return;
		}
		var confirmMsg = 'Delete ' + name + '? All active cities in this country will also be moved to Deleted.';
		if (mode === 'restore') {
			confirmMsg = 'Restore ' + name + ' and make its cities available again?';
		} else if (mode === 'permanent') {
			confirmMsg = 'Permanently delete ' + name + '? All states and cities in this country will also be deleted. This cannot be undone.';
		}
		if (!window.confirm(confirmMsg)) {
			return;
		}
		$.ajax({
			url: deleteCountryUrl,
			type: 'POST',
			dataType: 'json',
			data: { id: id, mode: mode }
		}).done(function (res) {
			if (res.success) {
				window.location.reload();
				return;
			}
			alert(res.message || 'Could not complete country action.');
		}).fail(function () {
			alert('Could not complete country action. Please try again.');
		});
	}

	$('#countryManagerSearch').on('input', function () {
		var query = String($(this).val() || '').toLowerCase().trim();
		$('.js-country-row').each(function () {
			var name = String($(this).data('name') || '').toLowerCase();
			$(this).toggle(query === '' || name.indexOf(query) !== -1);
		});
	});

	$(document).on('click', '.js-country-delete', function () {
		runCountryDeleteAction('soft', $(this).closest('.js-country-row'));
	});

	$(document).on('click', '.js-country-restore', function () {
		runCountryDeleteAction('restore', $(this).closest('.js-country-row'));
	});

	$(document).on('click', '.js-country-purge', function () {
		runCountryDeleteAction('permanent', $(this).closest('.js-country-row'));
	});

	$(document).on('change', '.cm-check-all', function () {
		var checked = $(this).is(':checked');
		$('.city-master-table .cm-row-check').prop('checked', checked);
		syncBulkDeleteState();
	});

	$(document).on('change', '.city-master-table .cm-row-check', function () {
		syncBulkDeleteState();
	});

	$(document).on('click', '.js-cm-bulk-delete', function () {
		runCityDeleteAction('soft', selectedCityIds());
	});

	$(document).on('click', '.js-cm-bulk-restore', function () {
		runCityDeleteAction('restore', selectedCityIds());
	});

	$(document).on('click', '.js-cm-bulk-purge', function () {
		runCityDeleteAction('permanent', selectedCityIds());
	});

	$(document).on('click', '.city-master-table .btn-view', function () {
		openCityViewModal($(this).closest('tr'));
	});

	$(document).on('click', '.city-master-table .btn-edit', function () {
		openCityFormModal('edit', $(this).closest('tr'));
	});

	$(document).on('click', '.city-master-table .btn-del', function () {
		var id = parseInt($(this).closest('tr').data('id'), 10);
		if (id) {
			runCityDeleteAction('soft', [id]);
		}
	});

	$(document).on('click', '.city-master-table .btn-restore', function () {
		var id = parseInt($(this).closest('tr').data('id'), 10);
		if (id) {
			runCityDeleteAction('restore', [id]);
		}
	});

	$(document).on('click', '.city-master-table .btn-purge', function () {
		var id = parseInt($(this).closest('tr').data('id'), 10);
		if (id) {
			runCityDeleteAction('permanent', [id]);
		}
	});

	syncBulkDeleteState();

	$(document).on('keydown', function (e) {
		if ((e.metaKey || e.ctrlKey) && String(e.key).toLowerCase() === 'k') {
			e.preventDefault();
			$('#citySearchInput').trigger('focus').select();
		}
	});

	var searchTimer = null;
	$('#citySearchInput').on('input', function () {
		clearTimeout(searchTimer);
		searchTimer = setTimeout(function () {
			$('#cityFilterForm').trigger('submit');
		}, 450);
	});

	var q = new URLSearchParams(window.location.search);
	if (q.get('open') === 'create') {
		openCityFormModal('create');
		if (window.history && window.history.replaceState) {
			var u = new URL(window.location.href);
			u.searchParams.delete('open');
			window.history.replaceState({}, '', u.pathname + u.search);
		}
	}
	});
})(jQuery);
</script>
</body>
</html>
