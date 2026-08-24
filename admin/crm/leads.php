<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/includes/quotation_db.php';
require_once __DIR__ . '/includes/lead_uid.php';
require_once __DIR__ . '/includes/lead_db.php';
require_once __DIR__ . '/includes/admin_ui_settings.php';
require_once __DIR__ . '/includes/supplier_db.php';
require_once __DIR__ . '/../mail/includes/mail_db.php';
require_once __DIR__ . '/../mail/includes/mail_service.php';

crmEnsureSequentialLeadUids($conn);
crmEnsureLeadDeleteColumns($conn);
crmEnsureLeadStageColumn($conn);
crmEnsureAdminUiSettingsTable($conn);

crmEnsureQuotationTables($conn);
crmEnsureSupplierTables($conn);
mailEnsureTables($conn);
crmSyncQuotationUidsFromLeads($conn);

$leadStageOptions = crmLeadStageOptions();

function crmLeadsFormatRow(array $row, array $destinationLookup): array
{
    $payload = [];
    if (!empty($row['payload_json'])) {
        $decoded = json_decode((string) $row['payload_json'], true);
        if (is_array($decoded)) {
            $payload = $decoded;
        }
    }

    $services = [];
    if (!empty($row['services'])) {
        $decodedServices = json_decode((string) $row['services'], true);
        if (is_array($decodedServices)) {
            $services = array_values(array_filter(array_map('trim', $decodedServices)));
        }
    }

    $destNames = [];
    $destSegments = [];
    $destIds = $payload['tp_destination'] ?? [];
    $destNightMap = $payload['itinerary_dest_nights'] ?? [];
    if (!is_array($destNightMap)) {
        $destNightMap = [];
    }
    if (!is_array($destIds)) {
        $destIds = $destIds !== '' ? [$destIds] : [];
    }

    // Normalize destination IDs while preserving order.
    $normalizedDestIds = [];
    foreach ($destIds as $destId) {
        $destId = (int) $destId;
        if ($destId > 0) {
            $normalizedDestIds[] = $destId;
        }
    }
    $destIds = $normalizedDestIds;

    // Some older payloads stored nights as a plain list ["7"] instead of {"11":"7"}.
    $destNightMapIsList = $destNightMap !== [] && array_keys($destNightMap) === range(0, count($destNightMap) - 1);

    foreach ($destIds as $destOrder => $destId) {
        if (!isset($destinationLookup[$destId])) {
            continue;
        }
        $destName = $destinationLookup[$destId];
        $destNames[] = $destName;
        $nightsVal = 0;
        if (isset($destNightMap[$destId])) {
            $nightsVal = max(0, (int) $destNightMap[$destId]);
        } elseif (isset($destNightMap[(string) $destId])) {
            $nightsVal = max(0, (int) $destNightMap[(string) $destId]);
        } elseif ($destNightMapIsList && isset($destNightMap[$destOrder])) {
            $nightsVal = max(0, (int) $destNightMap[$destOrder]);
        }
        $destSegments[] = [
            'name' => $destName,
            'nights' => $nightsVal,
        ];
    }

    $itineraryNights = max(0, (int) ($row['itinerary_total_nights'] ?? 0));
    if ($itineraryNights === 0 && isset($payload['itinerary_total_nights']) && $payload['itinerary_total_nights'] !== '' && $payload['itinerary_total_nights'] !== null) {
        $itineraryNights = max(0, (int) $payload['itinerary_total_nights']);
    }
    if ($itineraryNights === 0 && !empty($destNightMap) && is_array($destNightMap)) {
        foreach ($destNightMap as $nightVal) {
            $itineraryNights += max(0, (int) $nightVal);
        }
    }

    if (empty($destNames) && !empty($payload['tp_arrival'])) {
        $arrivalName = (string) $payload['tp_arrival'];
        $destNames[] = $arrivalName;
        $destSegments[] = [
            'name' => $arrivalName,
            'nights' => $itineraryNights,
        ];
    }

    if (empty($destNames)) {
        $destNames[] = 'N/A';
        $destSegments[] = [
            'name' => 'N/A',
            'nights' => $itineraryNights,
        ];
    }

    // If per-destination nights are missing but total nights exist, apply total to the sole destination.
    $segmentNightsSum = 0;
    foreach ($destSegments as $seg) {
        $segmentNightsSum += max(0, (int) ($seg['nights'] ?? 0));
    }
    if ($segmentNightsSum === 0 && $itineraryNights > 0) {
        foreach ($destSegments as $si => $seg) {
            $segName = trim((string) ($seg['name'] ?? ''));
            if ($segName !== '' && strtoupper($segName) !== 'N/A') {
                $destSegments[$si]['nights'] = $itineraryNights;
                $segmentNightsSum = $itineraryNights;
                break;
            }
        }
    }
    if ($itineraryNights <= 0 && $segmentNightsSum > 0) {
        $itineraryNights = $segmentNightsSum;
    }

    $travelDate = '';
    if (!empty($payload['tp_travel_date'])) {
        $ts = strtotime((string) $payload['tp_travel_date']);
        if ($ts !== false) {
            $travelDate = date('d', $ts) . '-' . date('M', $ts) . '-' . date('y', $ts);
        }
    }

    $departure = trim((string) ($payload['tp_departure'] ?? ''));
    $arrival = trim((string) ($payload['tp_arrival'] ?? ''));
    if ($arrival === '' && !empty($destNames)) {
        $firstDest = trim((string) ($destNames[0] ?? ''));
        if ($firstDest !== '' && strtoupper($firstDest) !== 'N/A') {
            $arrival = $firstDest;
        }
    }

    // Destination names only; nights shown once as trip total (never per destination).
    $destDisplayParts = [];
    foreach ($destSegments as $seg) {
        $segName = trim((string) ($seg['name'] ?? ''));
        if ($segName === '' || strtoupper($segName) === 'N/A') {
            continue;
        }
        $destDisplayParts[] = strtoupper($segName);
    }
    $destDisplay = !empty($destDisplayParts) ? implode(', ', $destDisplayParts) : '';
    if ($destDisplay !== '' && $itineraryNights > 0) {
        $destDisplay .= '-' . str_pad((string) $itineraryNights, 2, '0', STR_PAD_LEFT) . ' N';
    }
    $departureDisplay = $departure !== '' ? ('Ex-' . $departure) : '';
    $travelDestinationText = '';
    if ($destDisplay !== '' && $departureDisplay !== '') {
        $travelDestinationText = $destDisplay . ' | ' . $departureDisplay;
    } elseif ($destDisplay !== '') {
        $travelDestinationText = $destDisplay;
    } elseif ($departureDisplay !== '') {
        $travelDestinationText = $departureDisplay;
    }

    $travelRouteText = '';
    if ($departure !== '' && $arrival !== '') {
        $travelRouteText = $departure . ' to ' . $arrival;
    } elseif ($departure !== '') {
        $travelRouteText = $departure;
    } elseif ($arrival !== '') {
        $travelRouteText = $arrival;
    }

    $adults = (int) ($payload['tp_adults'] ?? 0);
    $children = (int) ($payload['tp_children'] ?? 0);
    $paxParts = [];
    if ($adults > 0) {
        $paxParts[] = $adults . 'A';
    }
    if ($children > 0) {
        $paxParts[] = $children . 'C';
    }
    $paxText = !empty($paxParts) ? implode(' + ', $paxParts) : '—';

    $customerInitial = crmCustomerInitialFromPayload($payload);
    $customerName = (string) ($row['customer_name'] ?? '');
    $customerDisplayName = crmFormatCustomerDisplayName($customerName, $customerInitial);
    $customerNameLetters = crmCustomerNameLetters($customerName !== '' ? $customerName : $customerDisplayName);
    $leadSource = trim((string) ($row['lead_source'] ?? ''));
    $referredBy = trim((string) ($row['referred_by'] ?? ''));
    $leadSourceText = ($leadSource !== '' ? $leadSource : '—') . ' | ' . ($referredBy !== '' ? $referredBy : '—');

    return [
        'id' => (int) ($row['id'] ?? 0),
        'lead_uid' => (string) ($row['lead_uid'] ?? ''),
        'customer_name' => $customerName,
        'customer_initial' => $customerInitial,
        'customer_display_name' => $customerDisplayName,
        'customer_name_letters' => $customerNameLetters,
        'customer_phone' => (string) ($row['customer_phone'] ?? ''),
        'customer_email' => (string) ($row['customer_email'] ?? ''),
        'lead_source' => $leadSource,
        'referred_by' => $referredBy,
        'lead_source_text' => $leadSourceText,
        'assign_to' => (string) ($row['assign_to'] ?? ''),
        'stage' => crmLeadNormalizeStage($row['stage'] ?? 'new_lead'),
        'stage_label' => crmLeadStageLabel($row['stage'] ?? 'new_lead'),
        'stage_class' => crmLeadStageCssClass($row['stage'] ?? 'new_lead'),
        'created_at' => (string) ($row['created_at'] ?? ''),
        'itinerary_total_nights' => $itineraryNights,
        'destination_names' => $destNames,
        'destination_segments' => $destSegments,
        'departure' => $departure,
        'arrival' => $arrival,
        'travel_route_text' => $travelRouteText,
        'travel_dest_display' => $destDisplay,
        'travel_departure_display' => $departureDisplay,
        'travel_destination_text' => $travelDestinationText,
        'travel_date_text' => $travelDate,
        'pax_text' => $paxText,
        'services' => $services,
        'payload' => $payload,
    ];
}

function crmFinancialYearKey(int $year, int $month): string
{
    $startYear = $month >= 4 ? $year : $year - 1;

    return sprintf('%d-%02d', $startYear, ($startYear + 1) % 100);
}

function crmFinancialYearRange(string $fyKey): ?array
{
    if ($fyKey === '') {
        return null;
    }
    if (!preg_match('/^(\d{4})-(\d{2})$/', $fyKey, $matches)) {
        return null;
    }

    $startYear = (int) $matches[1];
    $endShort = (int) $matches[2];
    if ($endShort !== ($startYear + 1) % 100) {
        return null;
    }

    return [
        'start' => sprintf('%d-04-01 00:00:00', $startYear),
        'end' => sprintf('%d-03-31 23:59:59', $startYear + 1),
    ];
}

function crmFinancialYearWhereSql(string $fyKey, string $column = 'created_at'): string
{
    $range = crmFinancialYearRange($fyKey);
    if (!$range) {
        return '';
    }

    return sprintf(
        ' AND `%s` >= \'%s\' AND `%s` <= \'%s\'',
        $column,
        $range['start'],
        $column,
        $range['end']
    );
}

function crmFinancialYearOptions(mysqli $conn): array
{
    $now = new DateTimeImmutable('now');
    $currentFy = crmFinancialYearKey((int) $now->format('Y'), (int) $now->format('n'));
    $startYear = (int) substr($currentFy, 0, 4) - 5;

    $minRes = $conn->query("SELECT MIN(`created_at`) AS min_created FROM `crm_leads` WHERE `created_at` IS NOT NULL AND `created_at` != ''");
    if ($minRes && ($minRow = $minRes->fetch_assoc()) && !empty($minRow['min_created'])) {
        $minTs = strtotime((string) $minRow['min_created']);
        if ($minTs !== false) {
            $minYear = (int) date('Y', $minTs);
            $minMonth = (int) date('n', $minTs);
            $minFyStart = $minMonth >= 4 ? $minYear : $minYear - 1;
            $startYear = min($startYear, $minFyStart);
        }
    }

    $endStartYear = (int) substr($currentFy, 0, 4) + 1;
    $options = [['value' => '', 'label' => 'All Financial Years']];
    for ($year = $endStartYear; $year >= $startYear; $year--) {
        $key = sprintf('%d-%02d', $year, ($year + 1) % 100);
        $options[] = [
            'value' => $key,
            'label' => 'FY ' . $year . '-' . sprintf('%02d', ($year + 1) % 100),
        ];
    }

    return $options;
}

function crmLeadsSearchWhereSql(mysqli $conn, string $search): string
{
    $search = trim($search);
    if ($search === '') {
        return '';
    }
    if (mb_strlen($search) > 120) {
        $search = mb_substr($search, 0, 120);
    }

    $like = '%' . $conn->real_escape_string($search) . '%';
    $conditions = [
        "`lead_uid` LIKE '{$like}'",
        "`customer_name` LIKE '{$like}'",
        "`customer_phone` LIKE '{$like}'",
        "`customer_email` LIKE '{$like}'",
        "`lead_source` LIKE '{$like}'",
        "`referred_by` LIKE '{$like}'",
        "`assign_to` LIKE '{$like}'",
        "`services` LIKE '{$like}'",
        "`payload_json` LIKE '{$like}'",
        "`created_by_name` LIKE '{$like}'",
        "CAST(`id` AS CHAR) LIKE '{$like}'",
    ];

    $destRes = $conn->query("SELECT `id` FROM `destinations` WHERE `name` LIKE '{$like}'");
    if ($destRes) {
        while ($destRow = $destRes->fetch_assoc()) {
            $destId = (int) ($destRow['id'] ?? 0);
            if ($destId <= 0) {
                continue;
            }
            $conditions[] = "`payload_json` LIKE '%\"{$destId}\"%'";
            $conditions[] = "`payload_json` LIKE '%:{$destId},%'";
            $conditions[] = "`payload_json` LIKE '%:{$destId}}%'";
        }
    }

    $quotationTableCheck = $conn->query("SHOW TABLES LIKE 'crm_quotations'");
    if ($quotationTableCheck && $quotationTableCheck->num_rows > 0) {
        $conditions[] = "EXISTS (
            SELECT 1 FROM `crm_quotations` q
            WHERE q.`lead_id` = `crm_leads`.`id`
              AND q.`quotation_uid` LIKE '{$like}'
        )";
    }

    return ' AND (' . implode(' OR ', $conditions) . ')';
}

function crmLeadsPageUrl(int $listPage, int $perPage = 25, string $fy = '', string $search = ''): string
{
    $params = [];
    if ($listPage > 1) {
        $params['page'] = $listPage;
    }
    if ($perPage !== 25) {
        $params['per_page'] = $perPage;
    }
    if ($fy !== '') {
        $params['fy'] = $fy;
    }
    if ($search !== '') {
        $params['q'] = $search;
    }
    if (empty($params)) {
        return 'crm/leads.php';
    }

    return 'crm/leads.php?' . http_build_query($params);
}

$leadRows = [];
$totalLeads = 0;
$destinationLookup = [];
$perPage = (int) ($_GET['per_page'] ?? 25);
if (!in_array($perPage, [10, 25, 50], true)) {
    $perPage = 25;
}
$listPage = max(1, (int) ($_GET['page'] ?? 1));
$fyFilter = trim((string) ($_GET['fy'] ?? ''));
if (crmFinancialYearRange($fyFilter) === null) {
    $fyFilter = '';
}
$searchFilter = trim((string) ($_GET['q'] ?? ''));
if (mb_strlen($searchFilter) > 120) {
    $searchFilter = mb_substr($searchFilter, 0, 120);
}
$financialYearOptions = crmFinancialYearOptions($conn);
$totalPages = 1;
$offset = 0;

$destRes = $conn->query("SELECT id, name FROM destinations WHERE is_active = 1");
if ($destRes) {
    while ($dest = $destRes->fetch_assoc()) {
        $destinationLookup[(int) $dest['id']] = (string) ($dest['name'] ?? '');
    }
}

$assignUserLookup = [];
$usersTableCheck = $conn->query("SHOW TABLES LIKE 'users'");
if ($usersTableCheck && $usersTableCheck->num_rows > 0) {
    $usersRes = $conn->query("SELECT `id`, `username`, `full_name`, `profile_image` FROM `users` WHERE COALESCE(`is_deleted`, 0) = 0");
    if ($usersRes) {
        $tonePalette = [
            ['key' => 'red', 'color' => '#e11d2e'],
            ['key' => 'purple', 'color' => '#7c3aed'],
            ['key' => 'blue', 'color' => '#2563eb'],
            ['key' => 'orange', 'color' => '#ea580c'],
            ['key' => 'teal', 'color' => '#0d9488'],
            ['key' => 'indigo', 'color' => '#4f46e5'],
            ['key' => 'pink', 'color' => '#db2777'],
            ['key' => 'green', 'color' => '#16a34a'],
            ['key' => 'cyan', 'color' => '#0891b2'],
            ['key' => 'amber', 'color' => '#d97706'],
        ];
        while ($uRow = $usersRes->fetch_assoc()) {
            $uid = (int) ($uRow['id'] ?? 0);
            $tone = $tonePalette[max(0, $uid - 1) % count($tonePalette)];
            $entry = [
                'id' => $uid,
                'username' => (string) ($uRow['username'] ?? ''),
                'full_name' => (string) ($uRow['full_name'] ?? ''),
                'profile_image' => (string) ($uRow['profile_image'] ?? ''),
                'tone_key' => $tone['key'],
                'tone_color' => $tone['color'],
            ];
            $unameKey = strtolower(trim($entry['username']));
            $fnameKey = strtolower(trim($entry['full_name']));
            if ($unameKey !== '') {
                $assignUserLookup[$unameKey] = $entry;
            }
            if ($fnameKey !== '' && !isset($assignUserLookup[$fnameKey])) {
                $assignUserLookup[$fnameKey] = $entry;
            }
        }
    }
}

/**
 * Resolve assigned user display data for leads list.
 *
 * @param string $assignTo
 * @param array<string, array<string, mixed>> $lookup
 * @return array{label: string, image: string, initial: string, tone_key: string, tone_color: string}|null
 */
function crmLeadsResolveAssignee(string $assignTo, array $lookup): ?array
{
    $assignTo = trim($assignTo);
    if ($assignTo === '' || $assignTo === '—') {
        return null;
    }
    $key = strtolower($assignTo);
    if (isset($lookup[$key])) {
        $u = $lookup[$key];
        $label = $u['username'] !== '' ? $u['username'] : $u['full_name'];
        $seed = $u['full_name'] !== '' ? $u['full_name'] : $label;
        return [
            'label' => $label !== '' ? $label : $assignTo,
            'image' => (string) ($u['profile_image'] ?? ''),
            'initial' => strtoupper(substr($seed !== '' ? $seed : $assignTo, 0, 1)),
            'tone_key' => (string) ($u['tone_key'] ?? 'blue'),
            'tone_color' => (string) ($u['tone_color'] ?? '#2563eb'),
        ];
    }

    return [
        'label' => $assignTo,
        'image' => '',
        'initial' => strtoupper(substr($assignTo, 0, 1)),
        'tone_key' => 'blue',
        'tone_color' => '#2563eb',
    ];
}

$hasLeadsTable = false;
$tableCheck = $conn->query("SHOW TABLES LIKE 'crm_leads'");
if ($tableCheck && $tableCheck->num_rows > 0) {
    $hasLeadsTable = true;
}

if ($hasLeadsTable) {
    $activeWhere = crmLeadActiveWhereSql();
    $listWhere = $activeWhere . crmFinancialYearWhereSql($fyFilter) . crmLeadsSearchWhereSql($conn, $searchFilter);
    $countRes = $conn->query('SELECT COUNT(*) AS c FROM crm_leads WHERE ' . $listWhere);
    if ($countRes) {
        $totalLeads = (int) ($countRes->fetch_assoc()['c'] ?? 0);
    }

    $totalPages = max(1, (int) ceil($totalLeads / $perPage));
    if ($listPage > $totalPages) {
        $listPage = $totalPages;
    }
    $offset = ($listPage - 1) * $perPage;

    $res = $conn->query('SELECT * FROM crm_leads WHERE ' . $listWhere . ' ORDER BY id DESC LIMIT ' . (int) $offset . ', ' . (int) $perPage);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $leadRows[] = crmLeadsFormatRow($row, $destinationLookup);
        }
    }
}

crmLeadsAttachQuotationLines($conn, $leadRows);
crmLeadsSyncFeatureStages($conn, $leadRows);

$deletedLeadsCount = $hasLeadsTable ? crmLeadsDeletedCount($conn) : 0;
$leadsAdminId = isset($_SESSION['id']) ? (int) $_SESSION['id'] : 0;
$leadsColumnVisibility = crmLeadsLoadColumnVisibility($conn, $leadsAdminId);


require_once __DIR__ . '/includes/lead_intake_fields.php';
require_once __DIR__ . '/includes/lead_intake_db.php';
crmEnsureLeadIntakeTables($conn);

$sendLinkDefaultFields = crmLeadIntakeSendLinkDefaultFields();

$sendLinkCompanyName = 'Multi Zone Travels';
$ssTable = $conn->query("SHOW TABLES LIKE 'site_settings'");
if ($ssTable && $ssTable->num_rows > 0) {
    $ssRes = $conn->query("SELECT `site_title` FROM `site_settings` WHERE `id` = 1 LIMIT 1");
    if ($ssRes && ($ssRow = $ssRes->fetch_assoc()) && !empty($ssRow['site_title'])) {
        $sendLinkCompanyName = (string) $ssRow['site_title'];
    }
}

$pendingIntakeCount = 0;
$pcRes = $conn->query("SELECT COUNT(*) AS c FROM `crm_lead_intake_submissions` WHERE `status` = 'pending'");
if ($pcRes) {
    $pendingIntakeCount = (int) ($pcRes->fetch_assoc()['c'] ?? 0);
}

mailSeedSmtpMasterFromLegacy($conn);
$mailSenders = mailListSmtpMaster($conn, true);
$qMailFromName = trim((string) ($mailSenders[0]['from_name'] ?? ($_SESSION['name'] ?? 'CRM Admin')));
$qMailFromEmail = trim((string) ($mailSenders[0]['from_email'] ?? ''));
$qMailSubject = '';
$qSupplierMailCatalog = crmSuppliersMailCatalog($conn);
$qDestinationNameToId = [];
foreach ($destinationLookup as $destId => $destName) {
    $key = strtolower(trim((string) $destName));
    if ($key !== '') {
        $qDestinationNameToId[$key] = (int) $destId;
    }
}
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <base href="../">
    <title>Leads</title>
    <?php include __DIR__ . '/../includes/header-links.php'; ?>
    <link rel="stylesheet" href="crm/assets/quotation_supplier_mail.css?v=1">
    <style>
        .crm-leads-ui .content-wrapper>.content {
            background: #f4f6f9;
            padding: 0.75rem 0.5rem 0.5rem;
            overflow-x: hidden;
            overflow-y: visible;
        }

        .crm-leads-ui .content-wrapper {
            min-height: calc(100vh - 3.5rem);
            overflow-x: hidden;
            overflow-y: visible;
        }

        .crm-leads-ui .content-wrapper > .content > .container-fluid {
            width: 100%;
            max-width: 100%;
            padding-left: 0.5rem;
            padding-right: 0.5rem;
            padding-bottom: 0.5rem;
            overflow-x: hidden;
        }

        .crm-leads-ui {
            --ld-border: #e5e7eb;
            --ld-border-light: #eef2f7;
            --ld-text: #374151;
            --ld-text-muted: #9ca3af;
            --ld-text-strong: #111827;
            --ld-accent: #e53935;
            --ld-accent-soft: #ffebee;
        }

        .crm-leads-ui .page-title-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
            flex-wrap: wrap;
            gap: 1rem;
            flex-shrink: 0;
        }

        .crm-leads-ui .page-title-copy {
            min-width: 0;
        }

        .crm-leads-ui .page-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: #111827;
            margin: 0 0 0.2rem;
            line-height: 1.2;
        }

        .crm-leads-ui .page-subtitle {
            margin: 0;
            font-size: 0.92rem;
            color: #6b7280;
            line-height: 1.45;
        }

        .crm-leads-ui .page-title-actions {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: flex-end;
            gap: 0.55rem;
            margin-left: auto;
        }

        .crm-leads-ui .page-title-actions .btn {
            display: inline-flex;
            align-items: center;
            font-weight: 600;
            border-radius: 8px;
            height: 38px;
            padding: 0 0.95rem;
            font-size: 0.84rem;
            white-space: nowrap;
        }

        .crm-leads-ui .page-title-actions .btn-outline-leads {
            background: #fff;
            border: 1px solid #d1d5db;
            color: #374151;
        }

        .crm-leads-ui .page-title-actions .btn-outline-leads:hover,
        .crm-leads-ui .page-title-actions .btn-outline-leads:focus {
            background: #f9fafb;
            border-color: #9ca3af;
            color: #111827;
        }

        .crm-leads-ui table.crm-leads-table .is-col-hidden,
        .crm-leads-ui table.crm-leads-table[data-col-lead="0"] .col-ld-lead,
        .crm-leads-ui table.crm-leads-table[data-col-guest="0"] .col-ld-guest,
        .crm-leads-ui table.crm-leads-table[data-col-dest="0"] .col-ld-dest,
        .crm-leads-ui table.crm-leads-table[data-col-date="0"] .col-ld-date,
        .crm-leads-ui table.crm-leads-table[data-col-services="0"] .col-ld-services,
        .crm-leads-ui table.crm-leads-table[data-col-services="0"] .col-services,
        .crm-leads-ui table.crm-leads-table[data-col-source="0"] .col-ld-source,
        .crm-leads-ui table.crm-leads-table[data-col-assign="0"] .col-ld-assign,
        .crm-leads-ui table.crm-leads-table[data-col-stage="0"] .col-ld-stage,
        .crm-leads-ui table.crm-leads-table[data-col-stage="0"] .col-stage,
        .crm-leads-ui table.crm-leads-table[data-col-actions="0"] .col-actions {
            display: none !important;
        }

        .crm-leads-ui table.crm-leads-table[data-col-lead="1"] .col-ld-lead,
        .crm-leads-ui table.crm-leads-table[data-col-guest="1"] .col-ld-guest,
        .crm-leads-ui table.crm-leads-table[data-col-dest="1"] .col-ld-dest,
        .crm-leads-ui table.crm-leads-table[data-col-date="1"] .col-ld-date,
        .crm-leads-ui table.crm-leads-table[data-col-services="1"] .col-ld-services,
        .crm-leads-ui table.crm-leads-table[data-col-services="1"] .col-services,
        .crm-leads-ui table.crm-leads-table[data-col-source="1"] .col-ld-source,
        .crm-leads-ui table.crm-leads-table[data-col-assign="1"] .col-ld-assign,
        .crm-leads-ui table.crm-leads-table[data-col-stage="1"] .col-ld-stage,
        .crm-leads-ui table.crm-leads-table[data-col-stage="1"] .col-stage,
        .crm-leads-ui table.crm-leads-table[data-col-actions="1"] .col-actions {
            display: table-cell !important;
        }

        #leadsColumnSettingsModal .leads-col-settings-hint {
            font-size: 0.875rem;
            color: #6b7280;
            line-height: 1.45;
        }

        #leadsColumnSettingsModal .leads-col-settings-list {
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
        }

        #leadsColumnSettingsModal .leads-col-settings-item {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            margin: 0;
            padding: 0.65rem 0.75rem;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            background: #fff;
            cursor: pointer;
            user-select: none;
            transition: border-color 0.15s ease, background 0.15s ease;
        }

        #leadsColumnSettingsModal .leads-col-settings-item:hover {
            border-color: #cbd5e1;
            background: #f8fafc;
        }

        #leadsColumnSettingsModal .leads-col-settings-item.is-locked {
            opacity: 0.72;
            cursor: default;
            background: #f9fafb;
        }

        #leadsColumnSettingsModal .leads-col-settings-item input[type="checkbox"] {
            width: 16px;
            height: 16px;
            margin: 0;
            flex: 0 0 auto;
            cursor: pointer;
            accent-color: #2563eb;
        }

        #leadsColumnSettingsModal .leads-col-settings-item.is-locked input[type="checkbox"] {
            cursor: not-allowed;
        }

        #leadsColumnSettingsModal .leads-col-settings-label {
            font-size: 0.9rem;
            font-weight: 600;
            color: #111827;
            line-height: 1.3;
        }

        #leadsColumnSettingsModal .leads-col-settings-lock {
            margin-left: auto;
            font-size: 0.72rem;
            font-weight: 600;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        #leadsColumnSettingsModal .leads-col-settings-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 0.85rem;
        }

        #leadsColumnSettingsModal .leads-col-settings-actions .btn {
            flex: 1 1 auto;
        }

        .crm-leads-ui .leads-create-btn.btn-danger {
            background: #e11d2e;
            border-color: #e11d2e;
            color: #fff;
        }

        .crm-leads-ui .leads-create-btn.btn-danger:hover,
        .crm-leads-ui .leads-create-btn.btn-danger:focus {
            background: #c81a28;
            border-color: #c81a28;
            color: #fff;
        }

        .crm-leads-ui .breadcrumbs {
            font-size: calc(0.875rem + 1px);
            color: #007bff;
        }

        .crm-leads-ui .breadcrumbs a {
            color: #007bff;
        }

        .crm-leads-ui .content-header { display: none; }

        .crm-leads-ui .leads-panel {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
            border: 1px solid var(--ld-border);
            overflow: hidden;
            width: 100%;
            max-width: 100%;
            display: flex;
            flex-direction: column;
            min-height: 0;
        }

        .crm-leads-ui .leads-panel-head,
        .crm-leads-ui .filter-bar,
        .crm-leads-ui .pagination-bar {
            flex-shrink: 0;
        }

        .crm-leads-ui .leads-panel-head {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: flex-end;
            gap: 0.5rem;
            padding: 0.65rem 1rem;
            border-bottom: 1px solid var(--ld-border);
            background: #fafbfc;
        }

        .crm-leads-ui .leads-panel-head h2 { display: none; }

        .crm-leads-ui .leads-panel-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .crm-leads-ui .btn-wizard,
        .crm-leads-ui .btn-logs {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            color: #475569;
            font-weight: 600;
            padding: 0.35rem 0.75rem;
            border-radius: 6px;
            font-size: calc(0.78rem + 1px);
            transition: background-color 0.15s ease, border-color 0.15s ease, color 0.15s ease;
        }

        .crm-leads-ui .btn-wizard:hover,
        .crm-leads-ui .btn-logs:hover {
            background: #eff6ff;
            border-color: #bfdbfe;
            color: #1d4ed8;
        }

        .crm-leads-ui .filter-bar {
            padding: 0.85rem 1rem;
            border-bottom: 1px solid var(--ld-border);
            display: flex;
            flex-wrap: wrap;
            gap: 0.65rem;
            align-items: center;
            background: #fff;
        }

        .crm-leads-ui .filter-bar .query-filter {
            min-width: 130px;
            height: 38px;
            border-radius: 6px;
            border: 1px solid #dbeafe;
            background: #eff6ff;
            color: #1d4ed8;
            font-weight: 600;
            font-size: calc(0.82rem + 1px);
            padding: 0 0.65rem;
        }

        .crm-leads-ui .filter-bar .search-wrap {
            flex: 1 1 280px;
            min-width: 220px;
            position: relative;
        }

        .crm-leads-ui .filter-bar .search-wrap i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: calc(0.88rem + 1px);
        }

        .crm-leads-ui .filter-bar .search-wrap input {
            padding-left: 2.35rem;
            border-radius: 6px;
            border: 1px solid var(--ld-border-light);
            height: 38px;
            font-size: calc(0.84rem + 1px);
            background: #fff;
            color: var(--ld-text);
        }

        .crm-leads-ui .filter-bar .search-wrap input:focus {
            border-color: #93c5fd;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.12);
        }

        .crm-leads-ui .filter-bar select.form-control {
            min-width: 120px;
            height: 38px;
            border-radius: 6px;
            border: 1px solid var(--ld-border-light);
            padding: 0 0.55rem;
            background: #fff;
            font-size: calc(0.82rem + 1px);
            color: var(--ld-text-muted);
        }

        .crm-leads-ui .filter-bar-actions {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.5rem;
            margin-left: auto;
        }

        .crm-leads-ui .filter-bar-actions .btn {
            height: 38px;
            display: inline-flex;
            align-items: center;
            white-space: nowrap;
            font-weight: 600;
            border-radius: 6px;
            transition: background-color 0.15s ease, border-color 0.15s ease, color 0.15s ease, box-shadow 0.15s ease;
        }

        .crm-leads-ui .filter-bar-actions .btn-success {
            background: #ecfdf5;
            border: 1px solid #6ee7b7;
            color: #047857;
        }

        .crm-leads-ui .filter-bar-actions .btn-success:hover,
        .crm-leads-ui .filter-bar-actions .btn-success:focus {
            background: #d1fae5;
            border-color: #34d399;
            color: #065f46;
            box-shadow: 0 1px 2px rgba(16, 185, 129, 0.12);
        }

        .crm-leads-ui .filter-bar-actions .btn-outline-primary {
            background: #eff6ff;
            border: 1px solid #93c5fd;
            color: #1d4ed8;
        }

        .crm-leads-ui .filter-bar-actions .btn-outline-primary:hover,
        .crm-leads-ui .filter-bar-actions .btn-outline-primary:focus {
            background: #dbeafe;
            border-color: #60a5fa;
            color: #1e40af;
            box-shadow: 0 1px 2px rgba(59, 130, 246, 0.12);
        }

        .crm-leads-ui .filter-bar-actions .btn-warning {
            background: #fffbeb;
            border: 1px solid #fcd34d;
            color: #b45309;
        }

        .crm-leads-ui .filter-bar-actions .btn-warning:hover,
        .crm-leads-ui .filter-bar-actions .btn-warning:focus {
            background: #fef3c7;
            border-color: #fbbf24;
            color: #92400e;
            box-shadow: 0 1px 2px rgba(245, 158, 11, 0.12);
        }

        .crm-leads-ui .filter-bar-actions .btn-outline-secondary {
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            color: #64748b;
        }

        .crm-leads-ui .filter-bar-actions .btn-outline-secondary:hover,
        .crm-leads-ui .filter-bar-actions .btn-outline-secondary:focus {
            background: #f1f5f9;
            border-color: #94a3b8;
            color: #475569;
            box-shadow: 0 1px 2px rgba(100, 116, 139, 0.1);
        }

        .crm-leads-ui .filter-bar-actions .trash-count-badge {
            margin-left: 0.35rem;
        }

        .crm-leads-ui .btn-query-add {
            height: 38px;
            padding: 0 0.95rem;
            border-radius: 6px;
            border: 1px solid #6ee7b7;
            background: #ecfdf5;
            color: #047857;
            font-weight: 700;
            font-size: calc(0.84rem + 1px);
            white-space: nowrap;
            transition: background-color 0.15s ease, border-color 0.15s ease, color 0.15s ease;
        }

        .crm-leads-ui .btn-query-add:hover {
            background: #d1fae5;
            border-color: #34d399;
            color: #065f46;
        }

        .crm-leads-ui .filter-bar-tools {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-left: auto;
        }

        .crm-leads-ui .filter-checks {
            display: flex;
            align-items: center;
            gap: 0.85rem;
        }

        .crm-leads-ui .filter-checks label {
            margin: 0;
            font-weight: 500;
            color: var(--ld-text-muted);
            display: flex;
            align-items: center;
            gap: 0.35rem;
            cursor: pointer;
            font-size: calc(0.8rem + 1px);
        }

        .crm-leads-ui .table-wrap {
            flex: 1 1 auto;
            min-height: 14rem;
            max-height: var(--ld-table-pane-max, calc(100dvh - 22rem));
            max-width: 100%;
            width: 100%;
            overflow-x: hidden;
            overflow-y: auto;
            background: #fff;
            border-top: 1px solid var(--ld-border);
            scrollbar-width: thin;
            scrollbar-color: #475569 #e5e7eb;
            -webkit-overflow-scrolling: touch;
        }

        .crm-leads-ui .table-wrap::-webkit-scrollbar {
            width: 10px;
            height: 0;
        }

        .crm-leads-ui .table-wrap::-webkit-scrollbar-thumb {
            background: #475569;
            border-radius: 999px;
            border: 2px solid #e5e7eb;
        }

        .crm-leads-ui .table-wrap::-webkit-scrollbar-thumb:hover {
            background: #334155;
        }

        .crm-leads-ui .table-wrap::-webkit-scrollbar-track {
            background: #e5e7eb;
        }

        .crm-leads-ui .table-wrap::-webkit-scrollbar-corner {
            background: #e5e7eb;
        }

        .crm-leads-ui table.crm-leads-table {
            width: 100%;
            min-width: 0;
            max-width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
            font-size: calc(0.8125rem + 1px);
            border: 1px solid var(--ld-border);
            background: #fff;
        }

        .crm-leads-ui table.crm-leads-table thead th {
            background: #fff;
            color: #6b7280;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            padding: 0.85rem 0.75rem;
            border: none;
            border-bottom: 1px solid var(--ld-border);
            border-right: none;
            white-space: nowrap;
            vertical-align: middle;
            font-size: calc(0.72rem + 1px);
            position: sticky;
            top: 0;
            z-index: 2;
            box-shadow: inset 0 -1px 0 var(--ld-border);
            user-select: none;
        }

        .crm-leads-ui table.crm-leads-table thead th .ld-th-label {
            display: inline-block;
            max-width: calc(100% - 6px);
            overflow: hidden;
            text-overflow: ellipsis;
            vertical-align: middle;
        }

        .crm-leads-ui table.crm-leads-table thead th .ld-col-resizer {
            position: absolute;
            top: 0;
            right: -3px;
            width: 8px;
            height: 100%;
            cursor: col-resize;
            z-index: 4;
            touch-action: none;
        }

        .crm-leads-ui table.crm-leads-table thead th .ld-col-resizer::after {
            content: '';
            position: absolute;
            top: 20%;
            bottom: 20%;
            left: 3px;
            width: 2px;
            border-radius: 2px;
            background: transparent;
            transition: background-color 0.12s ease;
        }

        .crm-leads-ui table.crm-leads-table thead th:hover .ld-col-resizer::after,
        .crm-leads-ui table.crm-leads-table thead th .ld-col-resizer:hover::after,
        .crm-leads-ui table.crm-leads-table.is-col-resizing thead th .ld-col-resizer.is-active::after {
            background: #94a3b8;
        }

        .crm-leads-ui table.crm-leads-table.is-col-resizing,
        .crm-leads-ui table.crm-leads-table.is-col-resizing * {
            cursor: col-resize !important;
            user-select: none !important;
        }

        .crm-leads-ui table.crm-leads-table thead th:last-child {
            border-right: none;
        }

        .crm-leads-ui table.crm-leads-table tbody td {
            padding: 0.85rem 0.75rem;
            vertical-align: middle;
            border-top: 1px solid #f3f4f6;
            border-right: none;
            line-height: 1.35;
            color: var(--ld-text);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            background: #fff;
            max-width: 0;
            position: relative;
            z-index: 0;
        }

        .crm-leads-ui table.crm-leads-table tbody td:last-child {
            border-right: none;
        }

        .crm-leads-ui table.crm-leads-table tbody tr:last-child td {
            border-bottom: 1px solid var(--ld-border);
        }

        .crm-leads-ui table.crm-leads-table thead th.col-ld-assign,
        .crm-leads-ui table.crm-leads-table thead th.col-ld-date,
        .crm-leads-ui table.crm-leads-table thead th.col-ld-services,
        .crm-leads-ui table.crm-leads-table thead th.col-ld-stage,
        .crm-leads-ui table.crm-leads-table tbody td.col-ld-assign,
        .crm-leads-ui table.crm-leads-table tbody td.col-ld-date,
        .crm-leads-ui table.crm-leads-table tbody td.col-ld-services,
        .crm-leads-ui table.crm-leads-table tbody td.col-ld-stage {
            text-align: center;
            vertical-align: middle;
        }

        .crm-leads-ui table.crm-leads-table tbody td.col-ld-dest {
            text-align: left;
            vertical-align: middle;
            overflow: hidden;
        }

        .crm-leads-ui table.crm-leads-table tbody td.col-actions {
            text-align: center;
            vertical-align: middle;
            overflow: hidden;
        }

        .crm-leads-ui table.crm-leads-table thead th.col-actions,
        .crm-leads-ui table.crm-leads-table tbody td.col-actions {
            white-space: nowrap;
            text-align: center;
        }

        .crm-leads-ui table.crm-leads-table tbody td.col-ld-services,
        .crm-leads-ui table.crm-leads-table tbody td.col-services {
            overflow: hidden;
            position: relative;
        }

        .crm-leads-ui table.crm-leads-table tbody td.col-ld-stage,
        .crm-leads-ui table.crm-leads-table tbody td.col-stage {
            overflow: hidden;
        }

        .crm-leads-ui table.crm-leads-table thead th.col-ld-lead,
        .crm-leads-ui table.crm-leads-table tbody td.col-ld-lead,
        .crm-leads-ui table.crm-leads-table thead th.col-ld-guest,
        .crm-leads-ui table.crm-leads-table tbody td.col-ld-guest,
        .crm-leads-ui table.crm-leads-table thead th.col-ld-dest,
        .crm-leads-ui table.crm-leads-table tbody td.col-ld-dest,
        .crm-leads-ui table.crm-leads-table thead th.col-ld-source,
        .crm-leads-ui table.crm-leads-table tbody td.col-ld-source,
        .crm-leads-ui table.crm-leads-table thead th.col-ld-services,
        .crm-leads-ui table.crm-leads-table tbody td.col-ld-services,
        .crm-leads-ui table.crm-leads-table thead th.col-ld-stage,
        .crm-leads-ui table.crm-leads-table tbody td.col-ld-stage,
        .crm-leads-ui table.crm-leads-table thead th.col-ld-date,
        .crm-leads-ui table.crm-leads-table tbody td.col-ld-date,
        .crm-leads-ui table.crm-leads-table thead th.col-ld-assign,
        .crm-leads-ui table.crm-leads-table tbody td.col-ld-assign {
            width: auto;
        }

        .crm-leads-ui table.crm-leads-table thead th.col-ld-assign,
        .crm-leads-ui table.crm-leads-table tbody td.col-ld-assign {
            text-align: center;
        }

        @media (max-width: 1399.98px) {
            .crm-leads-ui table.crm-leads-table .col-ld-source,
            .crm-leads-ui table.crm-leads-table .col-ld-date {
                display: none;
            }
        }

        @media (max-width: 1199.98px) {
            .crm-leads-ui table.crm-leads-table .col-ld-assign,
            .crm-leads-ui table.crm-leads-table .col-ld-services {
                display: none;
            }
        }

        @media (max-width: 991.98px) {
            .crm-leads-ui table.crm-leads-table .col-ld-dest,
            .crm-leads-ui table.crm-leads-table .col-ld-stage {
                display: none;
            }

            .crm-leads-ui .lead-id-meta {
                display: none;
            }

            .crm-leads-ui table.crm-leads-table thead th.col-ld-lead,
            .crm-leads-ui table.crm-leads-table tbody td.col-ld-lead,
            .crm-leads-ui table.crm-leads-table thead th.col-ld-guest,
            .crm-leads-ui table.crm-leads-table tbody td.col-ld-guest {
                width: auto;
            }
        }

        @media (max-width: 575.98px) {
            .crm-leads-ui table.crm-leads-table thead th.col-actions,
            .crm-leads-ui table.crm-leads-table tbody td.col-actions {
                /* Width controlled by JS fit — keep content from forcing scroll */
                max-width: none;
            }
        }

        .crm-leads-ui .cell-lead-source {
            white-space: nowrap;
            font-size: calc(0.78rem + 1px);
            color: #4b5563;
            display: block;
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .crm-leads-ui .cell-lead-source-sep {
            color: #9ca3af;
            font-weight: 400;
        }

        .crm-leads-ui table.crm-leads-table tbody tr:nth-child(even) {
            background: transparent;
        }

        .crm-leads-ui table.crm-leads-table tbody tr:nth-child(odd) {
            background: transparent;
        }

        .crm-leads-ui table.crm-leads-table tbody tr:hover td {
            background: #fafafa;
        }

        .crm-leads-ui table.crm-leads-table input[type="checkbox"] {
            width: 15px;
            height: 15px;
            cursor: pointer;
        }

        .crm-leads-ui .lead-name {
            display: flex;
            align-items: center;
            flex-wrap: nowrap;
            gap: 0.35rem;
            font-weight: 600;
            color: var(--ld-text-strong);
            white-space: nowrap;
            min-width: 0;
            max-width: 100%;
        }

        .crm-leads-ui .lead-guest-initials {
            flex: 0 0 auto;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: #e0f2fe;
            color: #0369a1;
            font-size: 0.68rem;
            font-weight: 700;
            letter-spacing: 0.02em;
            line-height: 1;
        }

        .crm-leads-ui .lead-name-text {
            text-transform: capitalize;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            min-width: 0;
        }

        .crm-leads-ui .lead-meta {
            color: var(--ld-text-muted);
            font-size: calc(0.78rem + 1px);
            line-height: 1.45;
        }

        .crm-leads-ui .lead-meta i {
            width: 14px;
            color: #94a3b8;
        }

        .crm-leads-ui .lead-id-cell {
            white-space: nowrap;
            line-height: 1.35;
            display: block;
            max-width: 100%;
            padding: 0;
            margin: 0;
            border: 0;
            background: transparent;
            text-align: left;
            cursor: pointer;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .crm-leads-ui .lead-id-cell:hover .lead-id-uid,
        .crm-leads-ui .lead-id-cell:focus .lead-id-uid {
            color: #c62828;
            text-decoration: underline;
        }

        .crm-leads-ui .lead-id-cell:focus {
            outline: none;
        }

        .crm-leads-ui .lead-id-uid {
            font-weight: 700;
            color: var(--ld-accent);
        }

        .crm-leads-ui .lead-id-meta {
            color: #9ca3af;
            font-size: calc(0.72rem + 1px);
            font-weight: 400;
        }

        .crm-leads-ui table.crm-leads-table tbody td.col-ld-lead {
            font-weight: 600;
            color: var(--ld-accent);
        }

        .crm-leads-ui .badge-dest-top {
            background: #dbeafe;
            color: #1d4ed8;
            font-weight: 600;
            padding: 0.2rem 0.45rem;
            border-radius: 999px;
            font-size: calc(0.72rem + 1px);
            border: none;
        }

        .crm-leads-ui .badge-dest {
            background: #eff6ff;
            color: #1e40af;
            font-weight: 600;
            padding: 0.18rem 0.45rem;
            border-radius: 999px;
            font-size: calc(0.72rem + 1px);
            margin: 2px 2px 0 0;
            display: inline-block;
            border: none;
        }

        .crm-leads-ui .badge-trav {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 0.2rem 0.5rem;
            border-radius: 999px;
            font-size: calc(0.72rem + 1px);
            font-weight: 600;
            margin: 2px 2px 0 0;
            border: none;
        }

        .crm-leads-ui .badge-trav-loc {
            background: #f1f5f9;
            color: #475569;
        }

        .crm-leads-ui .cell-travelers-info {
            display: block;
            min-width: 0;
            max-width: 100%;
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
        }

        .crm-leads-ui .travel-route-text {
            font-size: calc(0.8125rem + 1px);
            font-weight: 600;
            color: #374151;
            line-height: 1.25;
            white-space: nowrap;
            display: block;
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .crm-leads-ui .travel-route-text .travel-dest-sep,
        .crm-leads-ui .travel-route-text .travel-ex-city {
            font-weight: 400;
            color: #6b7280;
        }

        .crm-leads-ui .travel-route-text .route-join {
            font-weight: 500;
            color: var(--ld-text-muted);
            text-transform: lowercase;
        }

        .crm-leads-ui .badge-trav-date {
            background: transparent;
            color: var(--ld-accent);
            font-weight: 700;
            padding: 0;
            border-radius: 0;
            display: inline-block;
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            vertical-align: middle;
        }

        .crm-leads-ui .badge-trav-date i {
            color: var(--ld-accent);
            margin-right: 0.2rem;
        }

        .crm-leads-ui .cell-travel-date {
            white-space: nowrap;
            vertical-align: middle;
            text-align: center;
            overflow: hidden;
        }

        .crm-leads-ui .badge-trav-pax {
            background: #ecfdf5;
            color: #047857;
            flex-shrink: 0;
        }

        .crm-leads-ui .svc-pills {
            display: inline-flex;
            flex-wrap: nowrap;
            gap: 0.3rem;
            align-items: center;
            justify-content: center;
            white-space: nowrap;
            max-width: 100%;
            min-width: 0;
            overflow: hidden;
            vertical-align: middle;
        }

        .crm-leads-ui .svc-pills-collapsible {
            position: relative;
            display: inline-flex;
            flex-wrap: nowrap;
            align-items: center;
            justify-content: center;
            gap: 0.3rem;
            max-width: 100%;
            min-width: 0;
            overflow: hidden;
            cursor: default;
        }

        .crm-leads-ui .svc-pills-collapsible.is-open {
            overflow: visible;
            z-index: 5;
        }

        .crm-leads-ui table.crm-leads-table tbody tr.is-svc-popup-open {
            position: relative;
            z-index: 40;
        }

        .crm-leads-ui table.crm-leads-table tbody tr.is-svc-popup-open td.col-ld-services,
        .crm-leads-ui table.crm-leads-table tbody tr.is-svc-popup-open td.col-services {
            overflow: visible;
            z-index: 41;
        }

        .crm-leads-ui .svc-pills-popup {
            display: none;
            position: fixed;
            z-index: 2050;
            flex-direction: column;
            align-items: flex-start;
            gap: 0.35rem;
            padding: 0.45rem 0.55rem;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.18);
            min-width: 9rem;
            pointer-events: auto;
        }

        .crm-leads-ui .svc-pills-popup.is-open {
            display: flex;
        }

        /* When portaled to <body>, keep the same look above table rows */
        body > .svc-pills-popup {
            display: none;
            position: fixed;
            z-index: 2050;
            flex-direction: column;
            align-items: flex-start;
            gap: 0.35rem;
            padding: 0.45rem 0.55rem;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            box-shadow: 0 12px 28px rgba(15, 23, 42, 0.2);
            min-width: 9rem;
            pointer-events: auto;
        }

        body > .svc-pills-popup.is-open {
            display: flex !important;
        }

        body > .svc-pills-popup .svc-pill {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 0.22rem 0.55rem;
            border-radius: 999px;
            font-size: calc(0.72rem + 1px);
            font-weight: 600;
            white-space: nowrap;
            line-height: 1.2;
            border: none;
        }

        body > .svc-pills-popup .svc-pill i { font-size: calc(0.72rem + 1px); }
        body > .svc-pills-popup .svc-pill-tour_package { background: #ede9fe; color: #6d28d9; }
        body > .svc-pills-popup .svc-pill-flight { background: #dbeafe; color: #1d4ed8; }
        body > .svc-pills-popup .svc-pill-hotel { background: #ffedd5; color: #c2410c; }
        body > .svc-pills-popup .svc-pill-vehicle { background: #ccfbf1; color: #0f766e; }
        body > .svc-pills-popup .svc-pill-sightseeing { background: #fce7f3; color: #be185d; }
        body > .svc-pills-popup .svc-pill-cruise { background: #e0e7ff; color: #4338ca; }
        body > .svc-pills-popup .svc-pill-visa { background: #fef3c7; color: #b45309; }
        body > .svc-pills-popup .svc-pill-passport { background: #f1f5f9; color: #475569; }
        body > .svc-pills-popup .svc-pill-forex { background: #ecfccb; color: #3f6212; }

        .crm-leads-ui .svc-pills-popup::before,
        body > .svc-pills-popup::before {
            content: '';
            position: absolute;
            left: 0;
            right: 0;
            top: -8px;
            height: 8px;
        }

        /* Hover display handled by JS (popup is portaled to body) */
        .crm-leads-ui .svc-pills-collapsible:hover .svc-pills-popup,
        .crm-leads-ui .svc-pills-collapsible:focus-within .svc-pills-popup {
            display: none;
        }

        .crm-leads-ui .svc-pill {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 0.22rem 0.55rem;
            border-radius: 999px;
            font-size: calc(0.72rem + 1px);
            font-weight: 600;
            white-space: nowrap;
            line-height: 1.2;
            border: none;
            max-width: 100%;
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            flex: 0 1 auto;
        }

        .crm-leads-ui .svc-pill i { font-size: calc(0.72rem + 1px); }

        .crm-leads-ui .svc-pill-tour_package { background: #ede9fe; color: #6d28d9; }
        .crm-leads-ui .svc-pill-flight { background: #dbeafe; color: #1d4ed8; }
        .crm-leads-ui .svc-pill-hotel { background: #ffedd5; color: #c2410c; }
        .crm-leads-ui .svc-pill-vehicle { background: #ccfbf1; color: #0f766e; }
        .crm-leads-ui .svc-pill-sightseeing { background: #fce7f3; color: #be185d; }
        .crm-leads-ui .svc-pill-cruise { background: #e0e7ff; color: #4338ca; }
        .crm-leads-ui .svc-pill-visa { background: #fef3c7; color: #b45309; }
        .crm-leads-ui .svc-pill-passport { background: #f1f5f9; color: #475569; }
        .crm-leads-ui .svc-pill-forex { background: #ecfccb; color: #3f6212; }

        .crm-leads-ui .svc-icons {
            color: #6c757d;
            font-size: calc(1.05rem + 1px);
            display: inline-flex;
            align-items: center;
            justify-content: flex-start;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .crm-leads-ui td.col-services {
            vertical-align: middle;
            text-align: center;
            overflow: hidden;
            position: relative;
        }

        .crm-leads-ui .cell-assign {
            white-space: nowrap;
            font-weight: 700;
            color: #111827;
            font-size: calc(0.8125rem + 1px);
            text-transform: uppercase;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
            vertical-align: middle;
        }

        .crm-leads-ui .cell-assign-avatar {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            object-fit: cover;
            flex: 0 0 auto;
            border: 1px solid #e5e7eb;
            background: #f3f4f6;
        }

        .crm-leads-ui .cell-assign-initial {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
            font-size: 0.68rem;
            font-weight: 800;
            text-transform: uppercase;
            line-height: 1;
            border: 1px solid transparent;
        }

        .crm-leads-ui .cell-assign-initial.tone-red { background: #fee2e2; color: #e11d2e; }
        .crm-leads-ui .cell-assign-initial.tone-purple { background: #ede9fe; color: #7c3aed; }
        .crm-leads-ui .cell-assign-initial.tone-blue { background: #dbeafe; color: #2563eb; }
        .crm-leads-ui .cell-assign-initial.tone-orange { background: #ffedd5; color: #ea580c; }
        .crm-leads-ui .cell-assign-initial.tone-teal { background: #ccfbf1; color: #0d9488; }
        .crm-leads-ui .cell-assign-initial.tone-indigo { background: #e0e7ff; color: #4f46e5; }
        .crm-leads-ui .cell-assign-initial.tone-pink { background: #fce7f3; color: #db2777; }
        .crm-leads-ui .cell-assign-initial.tone-green { background: #dcfce7; color: #16a34a; }
        .crm-leads-ui .cell-assign-initial.tone-cyan { background: #cffafe; color: #0891b2; }
        .crm-leads-ui .cell-assign-initial.tone-amber { background: #fef3c7; color: #d97706; }

        .crm-leads-ui .cell-assign-name {
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            font-weight: 700;
            letter-spacing: 0.01em;
        }

        .crm-leads-ui .cell-assign.is-empty {
            color: #94a3b8;
            font-weight: 600;
            text-transform: none;
        }

        .crm-leads-ui .cell-lead-source {
            white-space: nowrap;
            font-size: calc(0.78rem + 1px);
            color: #4b5563;
            display: block;
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .crm-leads-ui .badge-stage,
        .crm-leads-ui .lead-stage-select {
            font-weight: 700;
            padding: 0.35rem 0.75rem;
            border-radius: 999px;
            font-size: calc(0.68rem + 1px);
            border: 1px solid transparent;
            white-space: nowrap;
            display: inline-block;
            text-align: center;
            line-height: 1.25;
            text-transform: uppercase;
            letter-spacing: 0.02em;
            max-width: 100%;
            cursor: pointer;
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background-image: none;
        }

        .crm-leads-ui .lead-stage-select::-ms-expand {
            display: none;
        }

        .crm-leads-ui .lead-stage-select:focus {
            outline: none;
            box-shadow: 0 0 0 2px rgba(229, 57, 53, 0.16);
        }

        .crm-leads-ui .lead-stage-select.stage-new_lead {
            background-color: #e3f2fd;
            border-color: #bbdefb;
            color: #1e88e5;
            background-image: none;
        }

        .crm-leads-ui .lead-stage-select.stage-quoted {
            background-color: #fff3e0;
            border-color: #ffe0b2;
            color: #fb8c00;
            background-image: none;
        }

        .crm-leads-ui .lead-stage-select.stage-confirmed {
            background-color: #e8f5e9;
            border-color: #c8e6c9;
            color: #43a047;
            background-image: none;
        }

        .crm-leads-ui .lead-stage-select.stage-lost {
            background-color: #f5f5f5;
            border-color: #e0e0e0;
            color: #757575;
            background-image: none;
        }

        .crm-leads-ui .lead-stage-select[data-stage-auto="1"] {
            cursor: default;
        }

        .crm-leads-ui td.col-stage {
            overflow: hidden;
            text-align: center;
        }

        .crm-leads-ui .action-btns {
            display: inline-flex;
            gap: 4px;
            align-items: center;
            justify-content: center;
            flex-wrap: nowrap;
            white-space: nowrap;
            width: 100%;
            max-width: 100%;
            overflow: hidden;
        }

        .crm-leads-ui .action-btns .btn-icon {
            width: 32px;
            height: 32px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            border: 1px solid transparent;
            font-size: calc(0.78rem + 1px);
            text-decoration: none;
            transition: background-color 0.15s ease, border-color 0.15s ease, color 0.15s ease, box-shadow 0.15s ease;
        }

        .crm-leads-ui .action-btns .btn-view {
            background: #e8f5e9;
            border-color: #c8e6c9;
            color: #2e7d32 !important;
        }

        .crm-leads-ui .action-btns .btn-view:hover {
            background: #c8e6c9;
            color: #1b5e20 !important;
            border-color: #a5d6a7;
            box-shadow: 0 1px 3px rgba(46, 125, 50, 0.15);
        }

        .crm-leads-ui .action-btns .btn-create-quote {
            background: #e3f2fd;
            border-color: #bbdefb;
            color: #1e88e5 !important;
        }

        .crm-leads-ui .action-btns .btn-create-quote:hover {
            background: #bbdefb;
            color: #1565c0 !important;
            border-color: #90caf9;
            box-shadow: 0 1px 3px rgba(30, 136, 229, 0.15);
        }

        .crm-leads-ui .action-btns .btn-book {
            background: #fff7ed;
            border-color: #fed7aa;
            color: #c2410c !important;
        }

        .crm-leads-ui .action-btns .btn-book .btn-book-img {
            width: 16px;
            height: 16px;
            object-fit: contain;
            display: block;
            pointer-events: none;
        }

        .crm-leads-ui .action-btns .btn-book:hover {
            background: #ffedd5;
            color: #9a3412 !important;
            border-color: #fdba74;
            box-shadow: 0 1px 3px rgba(194, 65, 12, 0.15);
        }

        .crm-leads-ui .action-btns .btn-confirmed {
            background: #dcfce7;
            border-color: #bbf7d0;
            color: #15803d !important;
        }

        .crm-leads-ui .action-btns .btn-confirmed:hover {
            background: #bbf7d0;
            color: #166534 !important;
            border-color: #86efac;
            box-shadow: 0 1px 3px rgba(22, 163, 74, 0.15);
        }

        .crm-leads-ui .action-btns .btn-more {
            background: #f5f5f5;
            border-color: #e0e0e0;
            color: #616161 !important;
        }

        .crm-leads-ui .action-btns .btn-more:hover {
            background: #eeeeee;
            color: #424242 !important;
            border-color: #bdbdbd;
            box-shadow: 0 1px 3px rgba(97, 97, 97, 0.12);
        }

        .crm-leads-ui .action-btns .btn-more.dropdown-toggle::after {
            display: none;
        }

        .crm-leads-ui .lead-actions-menu {
            min-width: 10.5rem;
            padding: 0.35rem 0;
            border-color: #e2e8f0;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.12);
            z-index: 1060;
        }

        .crm-leads-ui table.crm-leads-table tbody tr.is-actions-dropdown-open {
            position: relative;
            z-index: 50;
        }

        .crm-leads-ui table.crm-leads-table tbody tr.is-actions-dropdown-open > td {
            z-index: auto;
        }

        .crm-leads-ui table.crm-leads-table tbody tr.is-actions-dropdown-open td.col-actions {
            overflow: visible;
            z-index: 51;
            position: relative;
        }

        .crm-leads-ui .lead-actions-more.show {
            position: relative;
            z-index: 52;
        }

        .crm-leads-ui .lead-actions-menu .dropdown-item {
            display: flex;
            align-items: center;
            padding: 0.45rem 0.85rem;
            font-size: calc(0.8125rem + 1px);
            font-weight: 500;
            color: #334155;
        }

        .crm-leads-ui .lead-actions-menu .dropdown-item i {
            width: 1.1rem;
            text-align: center;
        }

        .crm-leads-ui .lead-actions-menu .dropdown-item:hover,
        .crm-leads-ui .lead-actions-menu .dropdown-item:focus {
            background: #f8fafc;
            color: #1e293b;
        }

        .crm-leads-ui .lead-actions-menu .dropdown-item.text-danger:hover,
        .crm-leads-ui .lead-actions-menu .dropdown-item.text-danger:focus {
            background: #fef2f2;
            color: #dc2626;
        }

        .crm-leads-ui .lead-actions-menu .dropdown-item.disabled,
        .crm-leads-ui .lead-actions-menu .dropdown-item:disabled {
            opacity: 0.45;
            pointer-events: none;
        }

        .crm-leads-ui td.col-actions {
            overflow: hidden;
            white-space: nowrap;
            text-align: center;
        }

        .crm-leads-ui .pagination-bar {
            flex-shrink: 0;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            border-top: 1px solid var(--ld-border);
            background: #fafbfc;
            font-size: calc(0.875rem + 1px);
            position: static;
            z-index: auto;
        }

        .crm-leads-ui .pagination-bar-tools {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.65rem;
        }

        .crm-leads-ui .pagination-bar .leads-per-page-select {
            width: auto;
            min-width: 6.5rem;
            font-size: calc(0.78rem + 1px);
            height: calc(1.8rem + 2px);
            padding-top: 0.1rem;
            padding-bottom: 0.1rem;
        }

        .crm-leads-ui .pagination-bar .page-summary {
            color: var(--ld-text-muted);
        }

        .crm-leads-ui .pagination-bar .page-link {
            min-width: 36px;
            text-align: center;
            color: #475569;
            background: #f8fafc;
            border-color: #e2e8f0;
            border-radius: 6px;
            font-weight: 600;
            transition: background-color 0.15s ease, border-color 0.15s ease, color 0.15s ease;
        }

        .crm-leads-ui .pagination-bar .page-link:hover {
            background: #eff6ff;
            border-color: #bfdbfe;
            color: #1d4ed8;
        }

        .crm-leads-ui .pagination-bar .page-item.active .page-link {
            background: #dbeafe;
            border-color: #93c5fd;
            color: #1d4ed8;
        }

        .crm-leads-ui .pagination-bar .page-item.disabled .page-link {
            color: #94a3b8;
            background: #f8fafc;
        }

        @media (max-width: 575px) {
            .crm-leads-ui .content-wrapper > .content {
                padding: 0.5rem 0.35rem 0.35rem;
            }

            .crm-leads-ui .content-wrapper > .content > .container-fluid {
                padding-left: 0.35rem;
                padding-right: 0.35rem;
                padding-bottom: 0.35rem;
            }
        }

        /* Send Travel Preference Form (Customer Form Link) modal */
        #sendLinkModal .send-link-dialog {
            max-width: 720px;
            width: calc(100% - 1.25rem);
            margin: 1rem auto;
        }
        #sendLinkModal .send-link-shell {
            border: none;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 24px 48px rgba(15, 23, 42, 0.18);
        }
        #sendLinkModal .send-link-hd {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.65rem;
            padding: 0.7rem 1rem;
            border-bottom: 1px solid #eef2f7;
            background: #fff;
        }
        #sendLinkModal .send-link-hd-left {
            display: flex;
            align-items: center;
            gap: 0.55rem;
            min-width: 0;
        }
        #sendLinkModal .send-link-wa-icon {
            width: 40px;
            height: 40px;
            border-radius: 999px;
            background: #25d366;
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 40px;
            font-size: 1.15rem;
        }
        #sendLinkModal .send-link-title {
            margin: 0;
            font-size: 1.05rem;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.3;
        }
        #sendLinkModal .send-link-subtitle {
            margin: 0.15rem 0 0;
            color: #64748b;
            font-size: 0.84rem;
            line-height: 1.4;
        }
        #sendLinkModal .send-link-close {
            width: 32px;
            height: 32px;
            border: 0;
            border-radius: 999px;
            background: #f1f5f9;
            color: #64748b;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            line-height: 1;
            cursor: pointer;
            flex: 0 0 32px;
        }
        #sendLinkModal .send-link-close:hover {
            background: #e2e8f0;
            color: #0f172a;
        }
        #sendLinkModal .send-link-bd {
            padding: 0.65rem 1rem 0.1rem;
            background: #fff;
            max-height: calc(100vh - 8rem);
            overflow-y: auto;
        }
        #sendLinkModal .sl-guest-card {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 0.65rem;
            padding: 0.65rem 0.75rem;
            border: 1px solid #eceff3;
            border-radius: 10px;
            background: #f8f9fb;
            margin-bottom: 0.55rem;
            flex-wrap: wrap;
        }
        #sendLinkModal .sl-guest-main {
            display: flex;
            align-items: flex-start;
            gap: 0.5rem;
            min-width: 0;
            flex: 1 1 280px;
        }
        #sendLinkModal .sl-avatar {
            width: 48px;
            height: 48px;
            border-radius: 999px;
            background: #ffe4e6;
            color: #e11d2e;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.9rem;
            flex: 0 0 48px;
        }
        #sendLinkModal .sl-guest-fields {
            flex: 1 1 auto;
            min-width: 0;
        }
        #sendLinkModal .sl-input {
            width: 100%;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            background: #fff;
            height: 36px;
            padding: 0.35rem 0.65rem;
            font-size: 0.9rem;
            color: #0f172a;
            font-weight: 600;
        }
        #sendLinkModal .sl-input:focus {
            outline: none;
            border-color: #fca5a5;
            box-shadow: 0 0 0 3px rgba(225, 29, 46, 0.1);
        }
        #sendLinkModal .sl-input-name {
            font-size: 0.92rem;
            margin-bottom: 0.3rem;
        }
        #sendLinkModal .sl-contact-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.35rem;
        }
        @media (max-width: 575px) {
            #sendLinkModal .sl-contact-row {
                grid-template-columns: 1fr;
            }
        }
        #sendLinkModal .sl-contact-field {
            position: relative;
        }
        #sendLinkModal .sl-contact-field i {
            position: absolute;
            left: 0.65rem;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 0.78rem;
            pointer-events: none;
        }
        #sendLinkModal .sl-contact-field .sl-input {
            padding-left: 1.9rem;
            font-weight: 500;
            font-size: 0.84rem;
        }
        #sendLinkModal .sl-guest-meta {
            text-align: right;
            flex: 0 0 auto;
            min-width: 120px;
        }
        #sendLinkModal .sl-meta-label {
            display: block;
            color: #94a3b8;
            font-size: 0.72rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            margin-bottom: 0.15rem;
        }
        #sendLinkModal .sl-meta-value {
            display: block;
            color: #0f172a;
            font-weight: 700;
            font-size: 0.82rem;
            margin-bottom: 0.3rem;
        }
        #sendLinkModal .sl-status-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.55rem;
            border-radius: 999px;
            background: #ffebee;
            color: #c62828;
            font-size: 0.72rem;
            font-weight: 700;
        }
        #sendLinkModal .sl-section {
            border: 1px solid #eceff3;
            border-radius: 10px;
            padding: 0.6rem 0.75rem;
            margin-bottom: 0;
            background: #fff;
        }
        #sendLinkModal .sl-section-hd {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
            margin-bottom: 0.4rem;
            flex-wrap: wrap;
        }
        #sendLinkModal .sl-section-title {
            margin: 0;
            font-size: 0.92rem;
            font-weight: 700;
            color: #0f172a;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }
        #sendLinkModal .sl-section-title .fab.fa-whatsapp {
            color: #25d366;
        }
        #sendLinkModal .sl-section-hint {
            margin: 0;
            color: #94a3b8;
            font-size: 0.78rem;
        }
        #sendLinkModal .sl-wa-bubble {
            background: #e7f8ea;
            border-radius: 4px 12px 12px 12px;
            padding: 0.55rem 0.75rem 0.4rem;
            color: #1f2937;
            font-size: 0.82rem;
            line-height: 1.35;
            position: relative;
            white-space: pre-wrap;
            word-break: break-word;
        }
        #sendLinkModal .sl-wa-bubble::before {
            content: "";
            position: absolute;
            left: -6px;
            top: 0;
            width: 0;
            height: 0;
            border-style: solid;
            border-width: 0 8px 10px 0;
            border-color: transparent #e7f8ea transparent transparent;
        }
        #sendLinkModal .sl-wa-link {
            color: #1565c0;
            text-decoration: underline;
            word-break: break-all;
        }
        #sendLinkModal .sl-wa-meta {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 0.25rem;
            margin-top: 0.25rem;
            color: #64748b;
            font-size: 0.7rem;
        }
        #sendLinkModal .sl-wa-meta .fa-check-double {
            color: #34b7f1;
            font-size: 0.78rem;
        }
        #sendLinkModal .sl-link-card {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.85rem;
            flex-wrap: wrap;
            border: 1px solid #eceff3;
            border-radius: 12px;
            padding: 0.85rem 1rem;
            margin-bottom: 0.35rem;
            background: #fff;
        }
        #sendLinkModal .sl-link-left {
            display: flex;
            align-items: flex-start;
            gap: 0.7rem;
            min-width: 0;
            flex: 1 1 240px;
        }
        #sendLinkModal .sl-link-icon {
            width: 38px;
            height: 38px;
            border-radius: 999px;
            background: #ffebee;
            color: #e11d2e;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 38px;
        }
        #sendLinkModal .sl-link-label-row {
            display: flex;
            align-items: center;
            gap: 0.45rem;
            flex-wrap: wrap;
            margin-bottom: 0.2rem;
        }
        #sendLinkModal .sl-link-label {
            margin: 0;
            font-weight: 700;
            color: #0f172a;
            font-size: 0.9rem;
        }
        #sendLinkModal .sl-short-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.12rem 0.45rem;
            border-radius: 999px;
            background: #e8f5e9;
            color: #2e7d32;
            font-size: 0.68rem;
            font-weight: 700;
        }
        #sendLinkModal .sl-link-url {
            margin: 0;
            color: #e11d2e;
            font-size: 0.82rem;
            font-weight: 600;
            word-break: break-all;
            line-height: 1.35;
        }
        #sendLinkModal .sl-link-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.4rem;
            justify-content: flex-end;
        }
        #sendLinkModal .sl-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.35rem;
            height: 36px;
            padding: 0 0.8rem;
            border-radius: 8px;
            font-size: 0.82rem;
            font-weight: 600;
            border: 1px solid transparent;
            white-space: nowrap;
            cursor: pointer;
            transition: background-color 0.15s ease, border-color 0.15s ease, color 0.15s ease;
        }
        #sendLinkModal .sl-btn-red {
            background: #c62828;
            border-color: #c62828;
            color: #fff;
        }
        #sendLinkModal .sl-btn-red:hover {
            background: #b71c1c;
            border-color: #b71c1c;
            color: #fff;
        }
        #sendLinkModal .sl-btn-outline-red {
            background: #fff;
            border-color: #ef9a9a;
            color: #c62828;
        }
        #sendLinkModal .sl-btn-outline-red:hover {
            background: #fff5f5;
            color: #b71c1c;
        }
        #sendLinkModal .sl-btn-outline {
            background: #fff;
            border-color: #d1d5db;
            color: #374151;
        }
        #sendLinkModal .sl-btn-outline:hover {
            background: #f9fafb;
            color: #111827;
        }
        #sendLinkModal .sl-btn-wa {
            background: #25d366;
            border-color: #25d366;
            color: #fff;
            min-width: 150px;
        }
        #sendLinkModal .sl-btn-wa:hover {
            background: #1ebe57;
            border-color: #1ebe57;
            color: #fff;
        }
        #sendLinkModal .send-link-ft {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.45rem;
            flex-wrap: wrap;
            padding: 0.6rem 1rem 0.7rem;
            border-top: 1px solid #eef2f7;
            background: #fff;
        }
        #sendLinkModal .send-link-ft-right {
            display: flex;
            flex-wrap: wrap;
            gap: 0.35rem;
            margin-left: auto;
        }
        #sendLinkModal .send-link-loading,
        #sendLinkModal .send-link-error {
            margin-bottom: 0.45rem;
        }
        #sendLinkModal .send-link-loading {
            text-align: center;
            color: #64748b;
            padding: 0.4rem;
            background: #f8fafc;
            border-radius: 8px;
            font-size: 0.84rem;
        }
        #sendLinkModal #sendLinkUrl {
            position: absolute;
            left: -9999px;
            width: 1px;
            height: 1px;
            opacity: 0;
        }
        #leadFormModal .lead-form-dialog {
            max-width: 1180px;
            width: calc(100% - 1.5rem);
            max-height: calc(100vh - 1.5rem);
            margin: 0.75rem auto;
            display: flex;
            align-items: stretch;
        }
        #leadFormModal .modal-content.lead-form-shell {
            border: none;
            border-radius: 12px;
            box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.22);
            display: flex;
            flex-direction: column;
            max-height: calc(100vh - 1.5rem);
            overflow: hidden;
            min-height: 0;
        }
        #leadFormModal .modal-header.lead-form-hd {
            flex: 0 0 auto;
            background: #e11d2e;
            color: #fff;
            border: none;
            padding: 0.95rem 1.25rem;
            border-radius: 12px 12px 0 0;
        }
        #leadFormModal .modal-header.lead-form-hd .modal-title {
            font-weight: 700;
            font-size: 1.15rem;
            display: inline-flex;
            align-items: center;
            gap: 0.55rem;
        }
        #leadFormModal .modal-header.lead-form-hd .modal-title i {
            font-size: 1.05rem;
        }
        #leadFormModal .modal-header.lead-form-hd .close {
            color: #fff;
            text-shadow: none;
            opacity: 0.95;
            font-size: 1.6rem;
            font-weight: 400;
            padding: 0.75rem 1.1rem;
            margin: -0.75rem -1rem -0.75rem auto;
        }
        #leadFormModal .modal-header.lead-form-hd .close:hover {
            opacity: 1;
            color: #fff;
        }
        #leadFormModal .modal-body.lead-form-bd {
            flex: 1 1 auto;
            min-height: 0;
            overflow-x: hidden;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
            overscroll-behavior: contain;
            padding: 1.1rem 1.25rem 1.15rem;
            background: #f5f6f8;
        }
        #leadFormModal .lead-form-loading {
            text-align: center;
            padding: 3rem 1rem;
            color: #6c757d;
        }
        #leadFormModal .lead-form-loading i {
            font-size: 2rem;
            margin-bottom: 0.75rem;
            color: #e11d2e;
        }
        #leadFormModal .lead-form-error {
            padding: 2rem;
            text-align: center;
            color: #dc3545;
        }
        #leadDetailModal.lead-expand-drawer .lead-detail-modal-dialog {
            position: fixed;
            top: 0;
            left: 0;
            right: auto;
            bottom: 0;
            margin: 0;
            height: 100%;
            max-width: min(460px, 100vw);
            width: min(460px, 100vw);
            transform: translateX(-100%);
            transition: transform 0.28s ease;
        }

        #leadDetailModal.lead-expand-drawer.fade .lead-detail-modal-dialog {
            transform: translateX(-100%);
        }

        #leadDetailModal.lead-expand-drawer.show .lead-detail-modal-dialog,
        #leadDetailModal.lead-expand-drawer.fade.show .lead-detail-modal-dialog {
            transform: translateX(0);
        }

        #leadDetailModal.lead-expand-drawer .modal-content {
            height: 100%;
            border-radius: 0;
            display: flex;
            flex-direction: column;
            box-shadow: 12px 0 40px rgba(15, 23, 42, 0.18);
        }

        #leadDetailModal.lead-expand-drawer .modal-header {
            background: linear-gradient(105deg, #c41e20 0%, #9f1517 100%);
            color: #fff;
            flex-shrink: 0;
            padding: 0.85rem 1rem;
            align-items: center;
        }

        #leadDetailModal.lead-expand-drawer .modal-header .modal-title {
            font-size: 0.95rem;
            font-weight: 700;
        }

        #leadDetailModal.lead-expand-drawer .modal-header .close {
            color: #fff;
            text-shadow: none;
            opacity: 0.9;
            padding: 0.4rem;
            margin: -0.2rem -0.2rem -0.2rem auto;
        }

        #leadDetailModal.lead-expand-drawer .modal-body {
            background: #f8fafc;
            flex: 1 1 auto;
            max-height: none;
            overflow-y: auto;
            padding: 0.85rem;
        }

        #leadDetailModal.lead-expand-drawer .modal-footer {
            flex-shrink: 0;
            background: #fff;
            border-top: 1px solid #e2e8f0;
            padding: 0.65rem 0.85rem;
        }

        #leadDetailModal .lead-detail-modal-dialog {
            max-width: 980px;
            width: calc(100% - 1.5rem);
        }

        #leadDetailModal .modal-content {
            border: none;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 24px 48px rgba(15, 23, 42, 0.2);
        }

        #leadDetailModal .modal-header {
            background: #007bff;
            color: #fff;
        }

        #leadDetailModal .modal-header .close {
            color: #fff;
            text-shadow: none;
            opacity: 0.9;
        }

        #leadDetailModal .modal-body {
            background: #f8fafc;
            max-height: calc(100vh - 180px);
            overflow-y: auto;
            padding: 0.65rem;
        }

        #deletedLeadsModal .deleted-leads-toolbar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
            margin-bottom: 0.75rem;
        }

        #deletedLeadsModal .deleted-leads-table-wrap {
            max-height: min(420px, calc(100vh - 16rem));
            overflow: auto;
            border: 1px solid var(--ld-border-light);
            border-radius: 8px;
        }

        #deletedLeadsModal .deleted-leads-table {
            width: 100%;
            margin: 0;
            font-size: calc(0.8125rem + 1px);
        }

        #deletedLeadsModal .deleted-leads-table th,
        #deletedLeadsModal .deleted-leads-table td {
            padding: 0.55rem 0.65rem;
            vertical-align: middle;
            border-top: 1px solid #eef2f7;
            white-space: nowrap;
        }

        #deletedLeadsModal .deleted-leads-table thead th {
            background: #f8fafc;
            border-top: none;
            font-size: calc(0.72rem + 1px);
            text-transform: uppercase;
            letter-spacing: 0.03em;
            color: #64748b;
            position: sticky;
            top: 0;
            z-index: 1;
        }

        #deletedLeadsModal .deleted-leads-empty,
        #deletedLeadsModal .deleted-leads-loading {
            padding: 2rem 1rem;
            text-align: center;
            color: #94a3b8;
        }

        #deletedLeadsModal .deleted-leads-loading {
            color: #64748b;
        }

        #btnOpenDeletedLeadsModal .trash-count-badge {
            margin-left: 0.35rem;
        }

        #deletedLeadsModal .deleted-leads-pagination {
            display: none;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
            margin-top: 0.75rem;
            padding-top: 0.65rem;
            border-top: 1px solid #e2e8f0;
        }

        #deletedLeadsModal .deleted-leads-pagination.is-visible {
            display: flex;
        }

        #deletedLeadsModal .deleted-leads-pagination .page-summary {
            color: #64748b;
            font-size: calc(0.78rem + 1px);
        }

        #leadAttachmentsModal .lead-attachments-upload {
            border: 1px dashed #cbd5e1;
            border-radius: 8px;
            background: #f8fafc;
            padding: 0.85rem;
        }
        #leadAttachmentsModal .lead-attachments-list {
            margin-top: 1rem;
        }
        #leadAttachmentsModal .lead-attachment-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            padding: 0.65rem 0.75rem;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: #fff;
            margin-bottom: 0.5rem;
        }
        #leadAttachmentsModal .lead-attachment-item:last-child {
            margin-bottom: 0;
        }
        #leadAttachmentsModal .lead-attachment-name {
            font-weight: 600;
            color: #1e293b;
            word-break: break-word;
        }
        #leadAttachmentsModal .lead-attachment-meta {
            font-size: 0.78rem;
            color: #64748b;
            margin-top: 0.15rem;
        }
        #leadAttachmentsModal .lead-attachment-actions {
            display: inline-flex;
            gap: 0.35rem;
            flex-shrink: 0;
        }
        #leadAttachmentsModal .lead-attachments-empty,
        #leadAttachmentsModal .lead-attachments-loading {
            color: #64748b;
            font-size: 0.875rem;
            text-align: center;
            padding: 1rem 0.5rem;
        }

        #smsTemplatesModal .sms-tpl-dialog {
            max-width: 560px;
            width: calc(100% - 1.25rem);
        }

        #smsTemplatesModal .sms-tpl-shell {
            border-radius: 8px;
            overflow: hidden;
            border: none;
            box-shadow: 0 12px 40px rgba(15, 23, 42, 0.22);
        }

        #smsTemplatesModal .sms-tpl-hd {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            background: #1e3a5f;
            color: #fff;
            padding: 0.85rem 1rem;
        }

        #smsTemplatesModal .sms-tpl-title {
            margin: 0;
            font-size: 1.05rem;
            font-weight: 700;
            letter-spacing: 0.01em;
        }

        #smsTemplatesModal .sms-tpl-close {
            border: 0;
            background: transparent;
            color: #fff;
            font-size: 1.5rem;
            line-height: 1;
            padding: 0 0.15rem;
            cursor: pointer;
            opacity: 0.9;
        }

        #smsTemplatesModal .sms-tpl-close:hover {
            opacity: 1;
        }

        #smsTemplatesModal .sms-tpl-bd {
            padding: 1rem 1.1rem 0.75rem;
            background: #fff;
        }

        #smsTemplatesModal .sms-tpl-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 0.45rem;
            margin-bottom: 1rem;
        }

        #smsTemplatesModal .sms-tpl-tab {
            border: 1px solid #cbd5e1;
            background: #fff;
            color: #0f172a;
            border-radius: 6px;
            padding: 0.4rem 0.85rem;
            font-size: 0.82rem;
            font-weight: 600;
            cursor: pointer;
            line-height: 1.2;
        }

        #smsTemplatesModal .sms-tpl-tab:hover {
            border-color: #94a3b8;
        }

        #smsTemplatesModal .sms-tpl-tab.is-active {
            background: #2563eb;
            border-color: #2563eb;
            color: #fff;
        }

        #smsTemplatesModal .sms-tpl-list {
            max-height: 320px;
            overflow-y: auto;
        }

        #smsTemplatesModal .sms-tpl-item {
            display: block;
            padding: 0.55rem 0.15rem 0.75rem;
            border-bottom: 1px solid #e5e7eb;
            cursor: pointer;
        }

        #smsTemplatesModal .sms-tpl-item:last-child {
            border-bottom: none;
        }

        #smsTemplatesModal .sms-tpl-item-hd {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.35rem;
        }

        #smsTemplatesModal .sms-tpl-item-hd input[type="radio"] {
            margin: 0;
            flex-shrink: 0;
            accent-color: #2563eb;
        }

        #smsTemplatesModal .sms-tpl-item-title {
            font-weight: 700;
            color: #0f172a;
            font-size: 0.9rem;
        }

        #smsTemplatesModal .sms-tpl-item-body {
            margin: 0 0 0 1.35rem;
            color: #334155;
            font-size: 0.84rem;
            line-height: 1.45;
            white-space: pre-wrap;
            word-break: break-word;
        }

        #smsTemplatesModal .sms-tpl-custom-wrap {
            display: none;
        }

        #smsTemplatesModal .sms-tpl-custom-wrap.is-visible {
            display: block;
        }

        #smsTemplatesModal .sms-tpl-custom-wrap label {
            font-weight: 700;
            font-size: 0.82rem;
            color: #334155;
            margin-bottom: 0.35rem;
        }

        #smsTemplatesModal .sms-tpl-custom-wrap textarea {
            width: 100%;
            min-height: 150px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 0.65rem 0.75rem;
            font-size: 0.875rem;
            line-height: 1.45;
            resize: vertical;
        }

        #smsTemplatesModal .sms-tpl-custom-wrap textarea:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.15);
        }

        #smsTemplatesModal .sms-tpl-meta {
            font-size: 0.78rem;
            color: #64748b;
            margin-bottom: 0.65rem;
        }

        #smsTemplatesModal .sms-tpl-ft {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 0.55rem;
            padding: 0.85rem 1.1rem;
            border-top: 1px solid #e5e7eb;
            background: #fff;
        }

        #smsTemplatesModal .sms-tpl-ft-right {
            display: flex;
            flex-wrap: wrap;
            gap: 0.45rem;
            margin-left: auto;
        }

        #smsTemplatesModal .sms-tpl-btn {
            border-radius: 6px;
            font-size: 0.84rem;
            font-weight: 700;
            padding: 0.45rem 0.9rem;
            border: 1px solid transparent;
            cursor: pointer;
            line-height: 1.2;
        }

        #smsTemplatesModal .sms-tpl-btn-cancel {
            background: #fff;
            color: #e53935;
            border-color: #e53935;
        }

        #smsTemplatesModal .sms-tpl-btn-cancel:hover {
            background: #fff5f5;
        }

        #smsTemplatesModal .sms-tpl-btn-wa {
            background: #22c55e;
            color: #fff;
            border-color: #22c55e;
        }

        #smsTemplatesModal .sms-tpl-btn-wa:hover {
            background: #16a34a;
            border-color: #16a34a;
        }

        #smsTemplatesModal .sms-tpl-btn-sms {
            background: #f97316;
            color: #fff;
            border-color: #f97316;
        }

        #smsTemplatesModal .sms-tpl-btn-sms:hover {
            background: #ea580c;
            border-color: #ea580c;
        }

        #smsTemplatesModal .sms-tpl-btn-email {
            background: #2563eb;
            color: #fff;
            border-color: #2563eb;
        }

        #smsTemplatesModal .sms-tpl-btn-email:hover {
            background: #1d4ed8;
            border-color: #1d4ed8;
        }

        #smsTemplatesModal .sms-tpl-btn:disabled {
            opacity: 0.55;
            cursor: not-allowed;
        }

        #deletedLeadsModal .deleted-leads-pagination .page-link {
            min-width: 32px;
            text-align: center;
            color: #334155;
            border-color: #e2e8f0;
            padding: 0.2rem 0.45rem;
            font-size: calc(0.78rem + 1px);
        }

        #deletedLeadsModal .deleted-leads-pagination .page-item.active .page-link {
            background: #2563eb;
            border-color: #2563eb;
            color: #fff;
        }

        #deletedLeadsModal .deleted-leads-pagination .page-item.disabled .page-link {
            color: #94a3b8;
        }

        #leadDetailModal .lead-detail-block {
            border: 1px solid #e9ecef;
            border-radius: 6px;
            overflow: hidden;
            margin-bottom: 0.55rem;
            background: #fff;
        }
        #leadDetailModal .lead-detail-block-hd {
            background: linear-gradient(180deg, #f8fbff 0%, #f3f8ff 100%);
            border-bottom: 1px solid #e9ecef;
            padding: 0.42rem 0.65rem;
            font-weight: 700;
            font-size: 0.82rem;
            color: #1f2937;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
        }
        #leadDetailModal .lead-detail-sub {
            font-size: 0.68rem;
            color: #64748b;
            font-weight: 600;
        }
        #leadDetailModal .lead-detail-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.4rem;
            padding: 0.45rem;
        }
        @media (max-width: 768px) {
            #leadDetailModal .lead-detail-grid {
                grid-template-columns: 1fr;
            }
        }
        #leadDetailModal .lead-detail-item {
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            background: #fff;
            padding: 0.38rem 0.45rem;
        }
        #leadDetailModal .lead-detail-item-label {
            display: block;
            font-size: 0.64rem;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            margin-bottom: 0.1rem;
        }
        #leadDetailModal .lead-detail-item-value {
            display: block;
            font-size: 0.8rem;
            color: #0f172a;
            line-height: 1.2;
            word-break: break-word;
        }
        #leadDetailModal .lead-detail-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.78rem;
        }
        #leadDetailModal .lead-detail-table th,
        #leadDetailModal .lead-detail-table td {
            padding: 0.32rem 0.5rem;
            border-top: 1px solid #eef1f4;
            vertical-align: top;
            line-height: 1.2;
        }
        #leadDetailModal .lead-detail-table th {
            width: 170px;
            background: #fcfcfd;
            color: #495057;
            font-weight: 600;
        }
        #leadDetailModal .lead-detail-table tbody tr:nth-child(even) td {
            background: #fbfdff;
        }
        #leadDetailModal .lead-detail-key {
            display: inline-flex;
            align-items: center;
            padding: 0.15rem 0.4rem;
            border-radius: 999px;
            background: #eef2ff;
            color: #334155;
            font-size: 0.74rem;
            font-weight: 700;
            line-height: 1.2;
        }

        /* Confirm Tour modal (Book from leads actions) */
        #confirmTourModal .modal-title {
            color: #64748b;
            font-size: 1.05rem;
            font-weight: 600;
        }

        #confirmTourModal .ct-label {
            font-size: 0.8rem;
            color: #94a3b8;
            margin-bottom: 0.2rem;
        }

        #confirmTourModal .ct-section-title {
            font-size: 0.9rem;
            font-weight: 600;
            color: #475569;
            margin: 0.85rem 0 0.45rem;
        }

        #confirmTourModal .ct-included {
            display: flex;
            flex-wrap: wrap;
            gap: 0.35rem;
        }

        #confirmTourModal .ct-chip {
            border: 1px solid #e2e8f0;
            background: #fff;
            color: #334155;
            font-size: 0.78rem;
            font-weight: 500;
            padding: 0.25rem 0.55rem;
            border-radius: 3px;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.05);
        }

        #confirmTourModal .ct-chip:hover,
        #confirmTourModal .ct-chip.active {
            border-color: #93c5fd;
            background: #eff6ff;
            color: #1d4ed8;
        }

        #confirmTourModal .ct-detail-head,
        #confirmTourModal .ct-detail-row {
            display: grid;
            grid-template-columns: 100px 1fr 90px 90px 80px 130px;
            gap: 0.45rem;
            align-items: end;
        }

        #confirmTourModal .ct-detail-head {
            font-size: 0.75rem;
            color: #94a3b8;
            margin-top: 0.65rem;
            padding-bottom: 0.25rem;
            border-bottom: 1px solid #f1f5f9;
        }

        #confirmTourModal .ct-detail-row {
            padding: 0.45rem 0;
            border-bottom: 1px dashed #f1f5f9;
        }

        #confirmTourModal .ct-detail-label {
            font-size: 0.82rem;
            font-weight: 600;
            color: #334155;
            padding-bottom: 0.35rem;
        }

        #confirmTourModal .ct-detail-field .form-control {
            font-size: 0.82rem;
            padding: 0.3rem 0.45rem;
            height: calc(1.5em + 0.55rem + 2px);
            border: 0;
            border-bottom: 1px dotted #cbd5e1;
            border-radius: 0;
            background: transparent;
        }

        #confirmTourModal .ct-detail-field .form-control:focus {
            box-shadow: none;
            border-bottom-color: #3b82f6;
            background: #f8fafc;
        }

        #confirmTourModal .ct-balance-wrap {
            text-align: center;
            padding-bottom: 0.35rem;
        }

        #confirmTourModal .ct-balance-label {
            display: block;
            font-size: 0.72rem;
            color: #94a3b8;
        }

        #confirmTourModal .ct-balance-val {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            color: #334155;
            border-bottom: 1px dotted #cbd5e1;
            padding-bottom: 0.15rem;
        }

        #confirmTourModal .ct-detail-actions {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            padding-bottom: 0.2rem;
        }

        #confirmTourModal .ct-detail-actions .ct-reminders {
            font-size: 0.75rem;
            padding: 0.2rem 0.45rem;
        }

        #confirmTourModal .ct-detail-actions .ct-remove-row {
            border: 1px solid #e2e8f0;
            color: #64748b;
        }

        @media (max-width: 991.98px) {
            #confirmTourModal .ct-detail-head {
                display: none;
            }

            #confirmTourModal .ct-detail-row {
                grid-template-columns: 1fr;
                gap: 0.35rem;
                border: 1px solid #e2e8f0;
                border-radius: 4px;
                padding: 0.5rem;
                margin-bottom: 0.45rem;
            }
        }
    </style>
</head>

<body class="hold-transition sidebar-mini layout-fixed">
    <div class="wrapper crm-leads-ui">

        <?php include __DIR__ . '/../includes/top-header.php'; ?>
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>

        <div class="content-wrapper">

            <?php include __DIR__ . '/../includes/page-header.php'; ?>

            <section class="content">
                <div class="container-fluid">

                    <div class="page-title-row">
                        <div class="page-title-copy">
                            <h1 class="page-title">Leads</h1>
                        </div>
                        <div class="page-title-actions">
                            <button type="button" class="btn btn-outline-leads" id="btnOpenLeadsColumnSettings" title="Column settings">
                                <i class="fas fa-cog mr-1"></i> 
                            </button>
                            <button type="button" class="btn btn-outline-leads" id="btnOpenSendLinkModal">
                                <i class="fas fa-external-link-alt mr-1"></i> 
                            </button>
                            <!-- <button type="button" class="btn btn-outline-leads" id="btnImportLeads" title="Import leads from spreadsheet">
                                <i class="fas fa-file-import mr-1"></i> Import Leads
                            </button> -->
                            <button type="button" class="btn btn-danger leads-create-btn" id="btnOpenLeadFormCreate">
                                <i class="fas fa-plus mr-1"></i> Create Lead
                            </button>
                        </div>
                    </div>

                    <div class="leads-panel">
                       

                        <div class="filter-bar">
                            <select class="query-filter form-control" style="width:auto; flex:0 0 auto;">
                                <option>All Queries</option>
                            </select>
                            <div class="search-wrap">
                                <i class="fas fa-search"></i>
                                <input type="search" class="form-control leads-search-input" placeholder="Search queries, client, company, destination..." value="<?= htmlspecialchars($searchFilter, ENT_QUOTES, 'UTF-8') ?>" aria-label="Search leads">
                            </div>
                            <select class="form-control leads-fy-select" style="width:auto; flex:0 0 auto;" aria-label="Financial year">
                                <?php foreach ($financialYearOptions as $fyOption) { ?>
                                    <option value="<?= htmlspecialchars((string) $fyOption['value'], ENT_QUOTES, 'UTF-8') ?>" <?= $fyFilter === (string) $fyOption['value'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $fyOption['label'], ENT_QUOTES, 'UTF-8') ?></option>
                                <?php } ?>
                            </select>
                            <div class="filter-bar-actions">
                                <?php if ($pendingIntakeCount > 0) { ?>
                                    <a href="crm/lead_intake_pending.php" class="btn btn-warning btn-sm">
                                        <i class="fas fa-user-clock mr-1"></i> Pending (<?= (int) $pendingIntakeCount ?>)
                                    </a>
                                <?php } ?>
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="btnOpenDeletedLeadsModal" title="Deleted leads">
                                    <i class="fas fa-trash-alt mr-1"></i> Trash
                                    <?php if ($deletedLeadsCount > 0) { ?>
                                        <span class="badge badge-danger trash-count-badge"><?= (int) $deletedLeadsCount ?></span>
                                    <?php } ?>
                                </button>
                            </div>
                        </div>

                        <div class="table-wrap">
                            <table class="crm-leads-table"
                                data-col-lead="<?= !empty($leadsColumnVisibility['lead']) ? '1' : '0' ?>"
                                data-col-guest="<?= !empty($leadsColumnVisibility['guest']) ? '1' : '0' ?>"
                                data-col-dest="<?= !empty($leadsColumnVisibility['dest']) ? '1' : '0' ?>"
                                data-col-date="<?= !empty($leadsColumnVisibility['date']) ? '1' : '0' ?>"
                                data-col-services="<?= !empty($leadsColumnVisibility['services']) ? '1' : '0' ?>"
                                data-col-source="<?= !empty($leadsColumnVisibility['source']) ? '1' : '0' ?>"
                                data-col-assign="<?= !empty($leadsColumnVisibility['assign']) ? '1' : '0' ?>"
                                data-col-stage="<?= !empty($leadsColumnVisibility['stage']) ? '1' : '0' ?>"
                                data-col-actions="<?= !empty($leadsColumnVisibility['actions']) ? '1' : '0' ?>">
                                <thead>
                                    <tr>
                                        <th class="col-ld-lead" data-col-key="lead"><span class="ld-th-label">Lead ID</span><span class="ld-col-resizer" title="Drag to resize"></span></th>
                                        <th class="col-ld-guest" data-col-key="guest"><span class="ld-th-label">Guest</span><span class="ld-col-resizer" title="Drag to resize"></span></th>
                                        <th class="col-ld-dest" data-col-key="dest"><span class="ld-th-label">Destination</span><span class="ld-col-resizer" title="Drag to resize"></span></th>
                                        <th class="col-ld-date" data-col-key="date"><span class="ld-th-label">Travel Date</span><span class="ld-col-resizer" title="Drag to resize"></span></th>
                                        <th class="col-ld-services" data-col-key="services"><span class="ld-th-label">Services</span><span class="ld-col-resizer" title="Drag to resize"></span></th>
                                        <th class="col-ld-source" data-col-key="source"><span class="ld-th-label">Lead Source</span><span class="ld-col-resizer" title="Drag to resize"></span></th>
                                        <th class="col-ld-assign" data-col-key="assign"><span class="ld-th-label">Assigned</span><span class="ld-col-resizer" title="Drag to resize"></span></th>
                                        <th class="col-ld-stage" data-col-key="stage"><span class="ld-th-label">Stage</span><span class="ld-col-resizer" title="Drag to resize"></span></th>
                                        <th class="col-actions" data-col-key="actions"><span class="ld-th-label">Actions</span><span class="ld-col-resizer" title="Drag to resize"></span></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($leadRows)) { ?>
                                        <tr>
                                            <td colspan="9" class="text-center text-muted">No leads found.</td>
                                        </tr>
                                    <?php } else { ?>
                                        <?php foreach ($leadRows as $rowIndex => $lead) {
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
                                        ?>
                                            <tr data-lead-id="<?= (int) $lead['id'] ?>">
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
                                                <td class="col-actions">
                                                    <div class="action-btns">
                                                        <?php if (!empty($lead['latest_quotation_href'])) { ?>
                                                            <a href="<?= htmlspecialchars((string) $lead['latest_quotation_href'], ENT_QUOTES, 'UTF-8') ?>"
                                                                class="btn-icon btn-view"
                                                                title="View Quotation">
                                                                <i class="far fa-eye"></i>
                                                            </a>
                                                            <?php
                                                            $latestQuotationId = (int) ($lead['latest_quotation_id'] ?? 0);
                                                            $latestIsDraft = (($lead['latest_quotation_status'] ?? '') === 'draft');
                                                            $latestTourConfirmed = !empty($lead['latest_is_tour_confirmed']);
                                                            if ($latestQuotationId > 0 && !$latestIsDraft) {
                                                            ?>
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
                                                            <a href="crm/quotation_generator.php?lead_id=<?= (int) $lead['id'] ?>"
                                                                class="btn-icon btn-create-quote"
                                                                title="Create Quotation">
                                                                <i class="fas fa-plus"></i>
                                                            </a>
                                                        <?php } ?>
                                                        <div class="dropdown lead-actions-more"
                                                            data-lead-id="<?= (int) $lead['id'] ?>"
                                                            data-lead-email="<?= htmlspecialchars((string) ($lead['customer_email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                                            data-lead-phone="<?= htmlspecialchars((string) ($lead['customer_phone'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                                            <button type="button"
                                                                class="btn-icon btn-more dropdown-toggle"
                                                                data-toggle="dropdown"
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
                                                                    <i class="far fa-eye mr-2 text-muted"></i> Preview
                                                                </button>
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
                                        <?php } ?>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="pagination-bar">
                            <div class="page-summary">
                                <?php if ($totalLeads > 0) { ?>
                                    Showing <?= number_format($offset + 1) ?>–<?= number_format(min($offset + $perPage, $totalLeads)) ?> of <?= number_format($totalLeads) ?> leads
                                <?php } else { ?>
                                    No leads to display
                                <?php } ?>
                            </div>
                            <div class="pagination-bar-tools">
                                <select class="form-control form-control-sm leads-per-page-select" aria-label="Leads per page">
                                    <?php foreach ([10, 25, 50] as $ppOption) { ?>
                                        <option value="<?= (int) $ppOption ?>" <?= $perPage === $ppOption ? 'selected' : '' ?>><?= (int) $ppOption ?> / page</option>
                                    <?php } ?>
                                </select>
                                <?php if ($totalLeads > 0) { ?>
                                    <nav aria-label="Leads pagination">
                                        <ul class="pagination pagination-sm mb-0">
                                            <li class="page-item <?= $listPage <= 1 ? 'disabled' : '' ?>">
                                                <a class="page-link" href="<?= htmlspecialchars(crmLeadsPageUrl($listPage - 1, $perPage, $fyFilter, $searchFilter), ENT_QUOTES, 'UTF-8') ?>">Prev</a>
                                            </li>
                                            <?php
                                            $startPage = max(1, $listPage - 2);
                                            $endPage = min($totalPages, $listPage + 2);
                                            for ($p = $startPage; $p <= $endPage; $p++) {
                                            ?>
                                                <li class="page-item <?= $p === $listPage ? 'active' : '' ?>">
                                                    <a class="page-link" href="<?= htmlspecialchars(crmLeadsPageUrl($p, $perPage, $fyFilter, $searchFilter), ENT_QUOTES, 'UTF-8') ?>"><?= (int) $p ?></a>
                                                </li>
                                            <?php } ?>
                                            <li class="page-item <?= $listPage >= $totalPages ? 'disabled' : '' ?>">
                                                <a class="page-link" href="<?= htmlspecialchars(crmLeadsPageUrl($listPage + 1, $perPage, $fyFilter, $searchFilter), ENT_QUOTES, 'UTF-8') ?>">Next</a>
                                            </li>
                                        </ul>
                                    </nav>
                                <?php } ?>
                            </div>
                        </div>
                    </div>

                </div>
            </section>

        </div>

        <!-- Send Travel Preference Form / Customer Form Link -->
        <div class="modal fade" id="sendLinkModal" tabindex="-1" role="dialog" aria-labelledby="sendLinkModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered send-link-dialog" role="document">
                <div class="modal-content send-link-shell">
                    <div class="send-link-hd">
                        <div class="send-link-hd-left">
                            <span class="send-link-wa-icon" aria-hidden="true"><i class="fab fa-whatsapp"></i></span>
                            <div>
                                <h5 class="send-link-title" id="sendLinkModalLabel">Send Travel Preference Form</h5>
                                <p class="send-link-subtitle">Share the form with your guest on WhatsApp</p>
                            </div>
                        </div>
                        <button type="button" class="send-link-close" data-dismiss="modal" aria-label="Close">&times;</button>
                    </div>
                    <div class="send-link-bd">
                        <div id="sendLinkLoading" class="send-link-loading">
                            <i class="fas fa-spinner fa-spin mr-1"></i> Generating link…
                        </div>
                        <div id="sendLinkError" class="alert alert-danger small send-link-error d-none mb-0"></div>

                        <div id="sendLinkReady" class="d-none">
                            <div class="sl-guest-card">
                                <div class="sl-guest-main">
                                    <span class="sl-avatar" id="sendLinkAvatar">GT</span>
                                    <div class="sl-guest-fields">
                                        <input type="text" class="sl-input sl-input-name" id="sendLinkGuestName" placeholder="Guest name" autocomplete="off">
                                        <div class="sl-contact-row">
                                            <div class="sl-contact-field">
                                                <i class="fas fa-phone"></i>
                                                <input type="text" class="sl-input" id="sendLinkGuestPhone" placeholder="Phone number" inputmode="tel" autocomplete="off">
                                            </div>
                                            <div class="sl-contact-field">
                                                <i class="far fa-envelope"></i>
                                                <input type="email" class="sl-input" id="sendLinkGuestEmail" placeholder="Email address" inputmode="email" autocomplete="off">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="sl-section">
                                <div class="sl-section-hd">
                                    <h6 class="sl-section-title"><i class="fab fa-whatsapp"></i> WhatsApp Message Preview</h6>
                                    <p class="sl-section-hint">This is how your message will appear to the guest.</p>
                                </div>
                                <div class="sl-wa-bubble">
                                    <div id="sendLinkPreviewBody"></div>
                                    <div class="sl-wa-meta">
                                        <span id="sendLinkPreviewTime">10:30 AM</span>
                                        <i class="fas fa-check-double" aria-hidden="true"></i>
                                    </div>
                                </div>
                            </div>

                            <input type="text" id="sendLinkUrl" readonly tabindex="-1" aria-hidden="true">
                        </div>
                    </div>
                    <div class="send-link-ft">
                        <button type="button" class="sl-btn sl-btn-outline" data-dismiss="modal">Cancel</button>
                        <div class="send-link-ft-right">
                            <button type="button" class="sl-btn sl-btn-outline-red" id="btnCopySendLink"><i class="far fa-copy"></i> Copy Message</button>
                            <button type="button" class="sl-btn sl-btn-wa" id="btnOpenWhatsAppSendLink"><i class="fab fa-whatsapp"></i> Open WhatsApp</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Leads column settings -->
        <div class="modal fade" id="leadsColumnSettingsModal" tabindex="-1" role="dialog" aria-labelledby="leadsColumnSettingsModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" role="document">
                <div class="modal-content">
                    <div class="modal-header py-3">
                        <h5 class="modal-title mb-0" id="leadsColumnSettingsModalLabel">
                            <i class="fas fa-columns mr-2 text-muted"></i> Column Settings
                        </h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <p class="leads-col-settings-hint mb-3">Check a column to show it in the table. Uncheck to hide it.</p>
                        <div class="leads-col-settings-list" id="leadsColumnSettingsList" role="group" aria-label="Visible columns">
                            <label class="leads-col-settings-item is-locked">
                                <input type="checkbox" class="js-leads-col-toggle" data-col-key="lead" checked disabled>
                                <span class="leads-col-settings-label">Lead ID</span>
                                <span class="leads-col-settings-lock">Required</span>
                            </label>
                            <label class="leads-col-settings-item">
                                <input type="checkbox" class="js-leads-col-toggle" data-col-key="guest"<?= !empty($leadsColumnVisibility['guest']) ? ' checked' : '' ?>>
                                <span class="leads-col-settings-label">Guest</span>
                            </label>
                            <label class="leads-col-settings-item">
                                <input type="checkbox" class="js-leads-col-toggle" data-col-key="dest"<?= !empty($leadsColumnVisibility['dest']) ? ' checked' : '' ?>>
                                <span class="leads-col-settings-label">Destination</span>
                            </label>
                            <label class="leads-col-settings-item">
                                <input type="checkbox" class="js-leads-col-toggle" data-col-key="date"<?= !empty($leadsColumnVisibility['date']) ? ' checked' : '' ?>>
                                <span class="leads-col-settings-label">Travel Date</span>
                            </label>
                            <label class="leads-col-settings-item">
                                <input type="checkbox" class="js-leads-col-toggle" data-col-key="services"<?= !empty($leadsColumnVisibility['services']) ? ' checked' : '' ?>>
                                <span class="leads-col-settings-label">Services</span>
                            </label>
                            <label class="leads-col-settings-item">
                                <input type="checkbox" class="js-leads-col-toggle" data-col-key="source"<?= !empty($leadsColumnVisibility['source']) ? ' checked' : '' ?>>
                                <span class="leads-col-settings-label">Lead Source</span>
                            </label>
                            <label class="leads-col-settings-item">
                                <input type="checkbox" class="js-leads-col-toggle" data-col-key="assign"<?= !empty($leadsColumnVisibility['assign']) ? ' checked' : '' ?>>
                                <span class="leads-col-settings-label">Assigned</span>
                            </label>
                            <label class="leads-col-settings-item">
                                <input type="checkbox" class="js-leads-col-toggle" data-col-key="stage"<?= !empty($leadsColumnVisibility['stage']) ? ' checked' : '' ?>>
                                <span class="leads-col-settings-label">Stage</span>
                            </label>
                            <label class="leads-col-settings-item is-locked">
                                <input type="checkbox" class="js-leads-col-toggle" data-col-key="actions" checked disabled>
                                <span class="leads-col-settings-label">Actions</span>
                                <span class="leads-col-settings-lock">Required</span>
                            </label>
                        </div>
                        <div class="leads-col-settings-actions">
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="btnLeadsColumnsShowAll">Show all</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="btnLeadsColumnsHideOptional">Hide optional</button>
                        </div>
                    </div>
                    <div class="modal-footer py-2">
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="btnLeadsColumnsReset">Reset</button>
                        <button type="button" class="btn btn-primary btn-sm" data-dismiss="modal" id="btnLeadsColumnsDone">Done</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create Lead modal -->
        <div class="modal fade" id="leadFormModal" tabindex="-1" role="dialog" aria-labelledby="leadFormModalLabel" aria-hidden="true" data-backdrop="static">
            <div class="modal-dialog modal-dialog-centered modal-xl lead-form-dialog" role="document">
                <div class="modal-content lead-form-shell">
                    <div class="modal-header lead-form-hd text-white">
                        <h5 class="modal-title mb-0" id="leadFormModalLabel"><i class="fas fa-user-plus"></i><span>Create Lead</span></h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    </div>
                    <div class="modal-body lead-form-bd" id="leadFormModalBody">
                        <div class="lead-form-loading">
                            <div><i class="fas fa-spinner fa-spin d-block"></i></div>
                            Loading form…
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal fade lead-expand-drawer" id="leadDetailModal" tabindex="-1" role="dialog" aria-labelledby="leadDetailModalLabel" aria-hidden="true">
            <div class="modal-dialog lead-detail-modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title mb-0" id="leadDetailModalLabel"><i class="fas fa-expand-alt mr-2"></i>Lead Details</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    </div>
                    <div class="modal-body" id="leadDetailModalBody"></div>
                    <div class="modal-footer py-2">
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-dismiss="modal">Close</button>
                        <button type="button" class="btn btn-primary btn-sm" id="btnLeadExpandEdit">
                            <i class="fas fa-pen mr-1"></i> Edit Lead
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Lead attachments modal -->
        <div class="modal fade" id="leadAttachmentsModal" tabindex="-1" role="dialog" aria-labelledby="leadAttachmentsModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" role="document">
                <div class="modal-content">
                    <div class="modal-header py-2">
                        <h5 class="modal-title mb-0" id="leadAttachmentsModalLabel"><i class="fas fa-paperclip mr-1 text-muted"></i> Lead Attachments</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    </div>
                    <div class="modal-body py-3">
                        <div class="small text-muted mb-2" id="leadAttachmentsLeadMeta"></div>
                        <div class="lead-attachments-upload">
                            <label class="d-block font-weight-bold mb-2" for="leadAttachmentFile">Upload file</label>
                            <div class="form-row align-items-center">
                                <div class="col-md-8">
                                    <input type="file" class="form-control-file" id="leadAttachmentFile" accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.doc,.docx,.xls,.xlsx,.txt,.zip">
                                </div>
                                <div class="col-md-4 text-md-right mt-2 mt-md-0">
                                    <button type="button" class="btn btn-primary btn-sm" id="btnUploadLeadAttachment">
                                        <i class="fas fa-upload mr-1"></i> Upload
                                    </button>
                                </div>
                            </div>
                            <small class="text-muted d-block mt-2">PDF, images, DOC, XLS, TXT or ZIP up to 10MB.</small>
                        </div>
                        <div class="lead-attachments-list">
                            <div class="lead-attachments-loading" id="leadAttachmentsLoading">
                                <i class="fas fa-spinner fa-spin mr-1"></i> Loading attachments…
                            </div>
                            <div class="lead-attachments-empty d-none" id="leadAttachmentsEmpty">No attachments yet.</div>
                            <div id="leadAttachmentsList"></div>
                        </div>
                        <div class="alert alert-danger small mb-0 mt-2 d-none" id="leadAttachmentsError"></div>
                    </div>
                    <div class="modal-footer py-2">
                        <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- SMS Templates modal -->
        <div class="modal fade" id="smsTemplatesModal" tabindex="-1" role="dialog" aria-labelledby="smsTemplatesModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered sms-tpl-dialog" role="document">
                <div class="modal-content sms-tpl-shell">
                    <div class="sms-tpl-hd">
                        <h5 class="sms-tpl-title" id="smsTemplatesModalLabel">Sms Templates</h5>
                        <button type="button" class="sms-tpl-close" data-dismiss="modal" aria-label="Close">&times;</button>
                    </div>
                    <div class="sms-tpl-bd">
                        <div class="sms-tpl-meta" id="smsTplLeadMeta"></div>
                        <div class="sms-tpl-tabs" role="tablist">
                            <button type="button" class="sms-tpl-tab is-active" data-sms-tab="default">Default Message</button>
                            <button type="button" class="sms-tpl-tab" data-sms-tab="whatsapp">WhatsApp</button>
                            <button type="button" class="sms-tpl-tab" data-sms-tab="email">Email</button>
                            <button type="button" class="sms-tpl-tab" data-sms-tab="custom">Custom</button>
                        </div>
                        <div class="sms-tpl-list" id="smsTplList"></div>
                        <div class="sms-tpl-custom-wrap" id="smsTplCustomWrap">
                            <label for="smsTplCustomText">Custom message</label>
                            <textarea id="smsTplCustomText" placeholder="Write your message…"></textarea>
                        </div>
                    </div>
                    <div class="sms-tpl-ft">
                        <button type="button" class="sms-tpl-btn sms-tpl-btn-cancel" data-dismiss="modal">Cancel</button>
                        <div class="sms-tpl-ft-right">
                            <button type="button" class="sms-tpl-btn sms-tpl-btn-email d-none" id="btnSmsTplSendEmail">Send Email</button>
                            <button type="button" class="sms-tpl-btn sms-tpl-btn-wa" id="btnSmsTplSendWhatsApp">Send on WhatsApp</button>
                            <button type="button" class="sms-tpl-btn sms-tpl-btn-sms" id="btnSmsTplSendSms">Send SMS</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Confirm Tour (Book) modal -->
        <div class="modal fade" id="confirmTourModal" tabindex="-1" role="dialog" aria-labelledby="confirmTourModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
                <div class="modal-content">
                    <div class="modal-header py-2">
                        <h5 class="modal-title" id="confirmTourModalLabel">Confirm Tour</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="ctQuotationId" value="">
                        <div class="row">
                            <div class="col-md-6 form-group mb-2">
                                <label class="ct-label" for="ctGuestName">GuestName</label>
                                <input type="text" class="form-control" id="ctGuestName" autocomplete="off">
                            </div>
                            <div class="col-md-6 form-group mb-2">
                                <label class="ct-label" for="ctMobileNo">Mobile No</label>
                                <input type="text" class="form-control" id="ctMobileNo" autocomplete="off">
                            </div>
                        </div>

                        <div class="ct-section-title">What is Included</div>
                        <div class="ct-included" id="ctIncludedChips"></div>

                        <div class="ct-section-title">Fill details</div>
                        <div class="ct-detail-head">
                            <div></div>
                            <div>Supplier</div>
                            <div>Total</div>
                            <div>Paid</div>
                            <div>Balance</div>
                            <div></div>
                        </div>
                        <div id="ctDetailRows"></div>
                    </div>
                    <div class="modal-footer py-2">
                        <button type="button" class="btn btn-primary" id="ctSaveBtn">Save</button>
                        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Deleted leads (trash) modal -->
        <div class="modal fade" id="deletedLeadsModal" tabindex="-1" role="dialog" aria-labelledby="deletedLeadsModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable" role="document">
                <div class="modal-content">
                    <div class="modal-header py-2">
                        <h5 class="modal-title mb-0" id="deletedLeadsModalLabel"><i class="fas fa-trash-alt mr-1 text-muted"></i> Deleted Leads</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    </div>
                    <div class="modal-body py-3">
                        <div class="deleted-leads-toolbar">
                            <div class="text-muted small" id="deletedLeadsSummary">Loading…</div>
                            <div class="d-flex flex-wrap align-items-center" style="gap:0.35rem;">
                                <button type="button" class="btn btn-outline-success btn-sm" id="btnDeletedLeadsBulkRestore" disabled>
                                    <i class="fas fa-undo mr-1"></i> Restore selected
                                </button>
                                <button type="button" class="btn btn-outline-danger btn-sm" id="btnDeletedLeadsBulkDelete" disabled>
                                    <i class="fas fa-times-circle mr-1"></i> Delete selected permanently
                                </button>
                            </div>
                        </div>
                        <div class="deleted-leads-table-wrap">
                            <div class="deleted-leads-loading" id="deletedLeadsLoading">
                                <i class="fas fa-spinner fa-spin mr-1"></i> Loading deleted leads…
                            </div>
                            <table class="table table-sm deleted-leads-table mb-0 d-none" id="deletedLeadsTable">
                                <thead>
                                    <tr>
                                        <th style="width:36px;"><input type="checkbox" id="deletedLeadsSelectAll" aria-label="Select all deleted leads"></th>
                                        <th>Lead ID</th>
                                        <th>Guest</th>
                                        <th>Assigned</th>
                                        <th>Deleted</th>
                                        <th style="width:150px;">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="deletedLeadsTableBody"></tbody>
                            </table>
                            <div class="deleted-leads-empty d-none" id="deletedLeadsEmpty">No deleted leads in trash.</div>
                        </div>
                        <div class="deleted-leads-pagination" id="deletedLeadsPagination" aria-label="Deleted leads pagination">
                            <div class="page-summary" id="deletedLeadsPageSummary"></div>
                            <nav>
                                <ul class="pagination pagination-sm mb-0" id="deletedLeadsPaginationList"></ul>
                            </nav>
                        </div>
                    </div>
                    <div class="modal-footer py-2">
                        <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>

        <?php include __DIR__ . '/includes/quotation_supplier_mail_modal.php'; ?>

        <?php include __DIR__ . '/../includes/footer-links.php'; ?>

    </div>

<script>
(function () {
    var leadRowsData = <?= json_encode($leadRows, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    var destinationLookup = <?= json_encode($destinationLookup, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    var leadFormUrl = 'crm/lead_add.php?embed=1';
    var leadsListPerPage = <?= (int) $perPage ?>;
    var leadsListFy = <?= json_encode($fyFilter, JSON_UNESCAPED_UNICODE) ?>;
    var leadsListSearch = <?= json_encode($searchFilter, JSON_UNESCAPED_UNICODE) ?>;
    var leadsSearchDebounceTimer = null;
    var sendLinkDefaultFields = <?= json_encode($sendLinkDefaultFields, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    var sendLinkCompanyName = <?= json_encode($sendLinkCompanyName, JSON_UNESCAPED_UNICODE) ?>;
    var smsTplAgentName = <?= json_encode((string) ($_SESSION['name'] ?? 'Team'), JSON_UNESCAPED_UNICODE) ?>;
    var Q_SUPPLIER_MAIL_CATALOG = <?= json_encode($qSupplierMailCatalog, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '[]' ?>;
    var Q_DESTINATION_NAME_TO_ID = <?= json_encode($qDestinationNameToId, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '{}' ?>;
    window.Q_SUPPLIER_MAIL_CATALOG = Q_SUPPLIER_MAIL_CATALOG;
    window.Q_DESTINATION_NAME_TO_ID = Q_DESTINATION_NAME_TO_ID;

    /* Keep table pane height within the viewport; footer scrolls with the page */
    var leadsTablePaneResizeTimer = null;
    function syncLeadsTablePaneHeight() {
        var wrap = document.querySelector('.crm-leads-ui .table-wrap');
        if (!wrap) {
            return;
        }
        var top = wrap.getBoundingClientRect().top;
        var bottomGap = 16;
        var available = Math.floor(window.innerHeight - top - bottomGap);
        wrap.style.maxHeight = Math.max(220, available) + 'px';
    }
    function scheduleLeadsTablePaneHeight() {
        if (leadsTablePaneResizeTimer) {
            window.clearTimeout(leadsTablePaneResizeTimer);
        }
        leadsTablePaneResizeTimer = window.setTimeout(syncLeadsTablePaneHeight, 50);
    }
    window.addEventListener('resize', scheduleLeadsTablePaneHeight);
    window.addEventListener('resize', function () {
        if (typeof leadsRedistributeColumnWidths === 'function') {
            leadsRedistributeColumnWidths(leadsLoadColumnVisibility());
        }
    });
    window.addEventListener('load', syncLeadsTablePaneHeight);
    $(document).on('collapsed.lte.pushmenu shown.lte.pushmenu', scheduleLeadsTablePaneHeight);
    $(function () {
        syncLeadsTablePaneHeight();
        window.setTimeout(syncLeadsTablePaneHeight, 120);
        window.setTimeout(syncLeadsTablePaneHeight, 400);
        var scrollRoot = document.querySelector('.crm-leads-ui .content-wrapper');
        if (scrollRoot) {
            scrollRoot.addEventListener('scroll', scheduleLeadsTablePaneHeight, { passive: true });
        }
    });

    var $modal = $('#leadFormModal');
    var $body = $('#leadFormModalBody');
    var loadSeq = 0;

    function showLeadFormLoading() {
        $body.html(
            '<div class="lead-form-loading">' +
            '<div><i class="fas fa-spinner fa-spin d-block"></i></div>Loading form…</div>'
        );
    }

    function loadLeadForm(editLead) {
        var seq = ++loadSeq;
        showLeadFormLoading();
        $body.load(leadFormUrl, function (response, status) {
            if (seq !== loadSeq) {
                return;
            }
            if (status === 'error' || !$.trim(response)) {
                $body.html('<div class="lead-form-error"><i class="fas fa-exclamation-triangle mr-2"></i>Could not load the lead form. Please try again.</div>');
                return;
            }
            var $form = $body.find('form.crm-lead-create-form').first();
            if (!$form.length) {
                return;
            }

            if (editLead) {
                var prefill = $.extend({}, editLead.payload || {}, {
                    customer_name: editLead.customer_name || '',
                    customer_phone: editLead.customer_phone || '',
                    customer_email: editLead.customer_email || '',
                    lead_source: editLead.lead_source || (editLead.payload ? editLead.payload.lead_source : '') || '',
                    referred_by: editLead.referred_by || (editLead.payload ? editLead.payload.referred_by : '') || '',
                    assign_to: editLead.assign_to || (editLead.payload ? editLead.payload.assign_to : '') || '',
                    itinerary_total_nights: (editLead.itinerary_total_nights != null && editLead.itinerary_total_nights !== '')
                        ? editLead.itinerary_total_nights
                        : ((editLead.payload && editLead.payload.itinerary_total_nights != null)
                            ? editLead.payload.itinerary_total_nights
                            : '')
                });
                if (prefill.itinerary_total_nights === '' || prefill.itinerary_total_nights == null || Number(prefill.itinerary_total_nights) < 1) {
                    var nightMap = (editLead.payload && editLead.payload.itinerary_dest_nights) || {};
                    var nightSum = 0;
                    if (nightMap && typeof nightMap === 'object') {
                        Object.keys(nightMap).forEach(function (k) {
                            nightSum += Math.max(parseInt(nightMap[k], 10) || 0, 0);
                        });
                    }
                    if (nightSum > 0) {
                        prefill.itinerary_total_nights = nightSum;
                    }
                }
                $form.attr('data-save-url', 'crm/ajax/update_lead.php');
                $form.find('input[name="lead_id"]').remove();
                $form.prepend('<input type="hidden" name="lead_id" value="' + editLead.id + '">');
                $form.attr('data-lead-prefill', JSON.stringify(prefill));
            }

            if (typeof window.initLeadCreateForm === 'function') {
                window.initLeadCreateForm($form[0]);
            }

            if (editLead) {
                $form.find('.js-lead-submit-btn').html('Update Lead <i class="fas fa-arrow-right"></i>');
            } else {
                $form.find('.js-lead-submit-btn').html('Create Lead <i class="fas fa-arrow-right"></i>');
            }
        });
    }

    function findLeadRowById(leadId) {
        leadId = Number(leadId || 0);
        if (!leadId) {
            return null;
        }
        for (var i = 0; i < leadRowsData.length; i++) {
            if (Number(leadRowsData[i].id) === leadId) {
                return leadRowsData[i];
            }
        }
        return null;
    }

    function openLeadFormModal(editLead) {
        loadSeq++;
        $('#leadFormModalLabel').html(
            editLead
                ? '<i class="fas fa-user-edit"></i><span>Edit Lead</span>'
                : '<i class="fas fa-user-plus"></i><span>Create Lead</span>'
        );
        $modal.modal('show');
        loadLeadForm(editLead);
    }

    function openLeadEditModal(leadId) {
        var lead = findLeadRowById(leadId);
        if (!lead) {
            window.alert('Could not load lead details for editing.');
            return;
        }
        openLeadFormModal(lead);
    }

    $('#btnOpenLeadFormCreate').on('click', function () {
        openLeadFormModal();
    });

    $('#btnImportLeads').on('click', function () {
        window.alert('Lead import will be available soon. Please create leads manually or share the customer form link for now.');
    });

    var LEADS_COL_SAVE_URL = 'crm/ajax/save_leads_column_settings.php';
    var LEADS_COL_WIDTHS_KEY = 'crm_leads_col_widths_v1';
    var LEADS_TABLE_COLUMNS = [
        { key: 'lead', className: 'col-ld-lead', label: 'Lead ID', locked: true, weight: 18, minWidth: 110 },
        { key: 'guest', className: 'col-ld-guest', label: 'Guest', locked: false, weight: 16, minWidth: 120 },
        { key: 'dest', className: 'col-ld-dest', label: 'Destination', locked: false, weight: 16, minWidth: 120 },
        { key: 'date', className: 'col-ld-date', label: 'Travel Date', locked: false, weight: 10, minWidth: 100 },
        { key: 'services', className: 'col-ld-services', label: 'Services', locked: false, weight: 12, minWidth: 100 },
        { key: 'source', className: 'col-ld-source', label: 'Lead Source', locked: false, weight: 12, minWidth: 100 },
        { key: 'assign', className: 'col-ld-assign', label: 'Assigned', locked: false, weight: 10, minWidth: 100 },
        { key: 'stage', className: 'col-ld-stage', label: 'Stage', locked: false, weight: 9, minWidth: 110 },
        { key: 'actions', className: 'col-actions', label: 'Actions', locked: true, fixedWidth: '9.5rem', minWidth: 100 }
    ];
    var leadsColumnVisibilityState = <?= json_encode($leadsColumnVisibility, JSON_UNESCAPED_UNICODE) ?>;
    var leadsColumnSaveTimer = null;
    var leadsColumnSaveXhr = null;
    var leadsColumnWidthsState = null;
    var leadsColResizeState = null;

    function leadsDefaultColumnVisibility() {
        var vis = {};
        LEADS_TABLE_COLUMNS.forEach(function (col) {
            vis[col.key] = true;
        });
        return vis;
    }

    function leadsColumnMeta(key) {
        for (var i = 0; i < LEADS_TABLE_COLUMNS.length; i++) {
            if (LEADS_TABLE_COLUMNS[i].key === key) {
                return LEADS_TABLE_COLUMNS[i];
            }
        }
        return null;
    }

    function leadsColumnIsVisible(col, state) {
        if (col.locked) {
            return true;
        }
        return !state || state[col.key] !== false;
    }

    function leadsLoadColumnWidths() {
        if (leadsColumnWidthsState && typeof leadsColumnWidthsState === 'object') {
            return leadsColumnWidthsState;
        }
        try {
            var raw = window.localStorage.getItem(LEADS_COL_WIDTHS_KEY);
            var parsed = raw ? JSON.parse(raw) : null;
            leadsColumnWidthsState = (parsed && typeof parsed === 'object') ? parsed : {};
        } catch (err) {
            leadsColumnWidthsState = {};
        }
        return leadsColumnWidthsState;
    }

    function leadsSaveColumnWidths(widths) {
        leadsColumnWidthsState = widths && typeof widths === 'object' ? widths : {};
        try {
            window.localStorage.setItem(LEADS_COL_WIDTHS_KEY, JSON.stringify(leadsColumnWidthsState));
        } catch (err) {}
        return leadsColumnWidthsState;
    }

    function leadsClearColumnWidths() {
        leadsColumnWidthsState = {};
        try {
            window.localStorage.removeItem(LEADS_COL_WIDTHS_KEY);
        } catch (err) {}
    }

    function leadsParseFixedWidthPx(fixedWidth) {
        if (!fixedWidth) {
            return 0;
        }
        var str = String(fixedWidth);
        if (str.indexOf('rem') !== -1) {
            var rem = parseFloat(str);
            if (isNaN(rem)) return 0;
            var root = parseFloat(window.getComputedStyle(document.documentElement).fontSize) || 16;
            return Math.round(rem * root);
        }
        var px = parseFloat(str);
        return isNaN(px) ? 0 : Math.round(px);
    }

    function leadsSetColumnCellsWidth($table, col, widthPx) {
        var widthVal = Math.round(widthPx) + 'px';
        var $cells = $table.find('.' + col.className);
        if (col.key === 'services') {
            $cells = $cells.add($table.find('.col-services'));
        }
        if (col.key === 'stage') {
            $cells = $cells.add($table.find('.col-stage'));
        }
        $cells.css({
            width: widthVal,
            maxWidth: widthVal,
            minWidth: 0
        });
    }

    function leadsCaptureCurrentColumnWidths($table, state) {
        var widths = {};
        var $ths = $table.find('thead th[data-col-key]');
        $ths.each(function () {
            var key = String($(this).attr('data-col-key') || '');
            var meta = leadsColumnMeta(key);
            if (!key || !meta || !leadsColumnIsVisible(meta, state)) {
                return;
            }
            var w = Math.round($(this).outerWidth());
            if (w > 0) {
                widths[key] = w;
            }
        });
        return widths;
    }

    function leadsGetTableWrapWidth($table) {
        var $wrap = $table.closest('.table-wrap');
        var w = $wrap.length ? $wrap.innerWidth() : 0;
        if (!(w > 0)) {
            w = $table.parent().innerWidth() || window.innerWidth || 1000;
        }
        // Leave a little room so borders don't force overflow.
        return Math.max(320, Math.floor(w) - 2);
    }

    function leadsApplyFittedColumnWidths($table, state, widthsMap, fitToWrap) {
        var visible = [];
        var total = 0;
        LEADS_TABLE_COLUMNS.forEach(function (col) {
            if (!leadsColumnIsVisible(col, state)) {
                $table.find('.' + col.className).css({ width: '', maxWidth: '', minWidth: '' });
                if (col.key === 'services') {
                    $table.find('.col-services').css({ width: '', maxWidth: '', minWidth: '' });
                }
                if (col.key === 'stage') {
                    $table.find('.col-stage').css({ width: '', maxWidth: '', minWidth: '' });
                }
                return;
            }
            var w = parseInt(widthsMap[col.key], 10);
            if (!(w > 0)) {
                w = col.minWidth || 100;
            }
            // Soft preference only — hard clamp happens after exact page fit.
            w = Math.max(40, w);
            visible.push({ col: col, width: w });
            total += w;
        });
        if (!visible.length) {
            return 0;
        }

        var wrapW = leadsGetTableWrapWidth($table);
        if (fitToWrap && total > 0 && wrapW > 0) {
            // Always force exact page width so no horizontal scroll is needed.
            var used = 0;
            visible.forEach(function (item, idx) {
                if (idx === visible.length - 1) {
                    item.width = Math.max(1, wrapW - used);
                } else {
                    item.width = Math.max(1, Math.floor(item.width * (wrapW / total)));
                    used += item.width;
                }
            });
            total = wrapW;
        }

        visible.forEach(function (item) {
            leadsSetColumnCellsWidth($table, item.col, item.width);
        });

        $table.css({
            width: '100%',
            minWidth: '0',
            maxWidth: '100%'
        });
        return total;
    }

    function leadsRedistributeColumnWidths(state) {
        var $table = $('.crm-leads-ui table.crm-leads-table');
        if (!$table.length) {
            return;
        }

        var savedWidths = leadsLoadColumnWidths();
        var hasCustom = false;
        Object.keys(savedWidths || {}).forEach(function (k) {
            if (savedWidths[k] > 0) {
                hasCustom = true;
            }
        });

        var wrapW = leadsGetTableWrapWidth($table);
        var widthsMap = {};
        var visibleFlexible = [];
        var totalWeight = 0;
        var fixedBudget = 0;

        LEADS_TABLE_COLUMNS.forEach(function (col) {
            if (!leadsColumnIsVisible(col, state)) {
                return;
            }
            if (hasCustom && savedWidths[col.key] > 0) {
                widthsMap[col.key] = Math.max(col.minWidth || 80, parseInt(savedWidths[col.key], 10) || 0);
                return;
            }
            if (col.fixedWidth && !hasCustom) {
                widthsMap[col.key] = Math.max(col.minWidth || 80, leadsParseFixedWidthPx(col.fixedWidth) || (col.minWidth || 120));
                fixedBudget += widthsMap[col.key];
                return;
            }
            visibleFlexible.push(col);
            totalWeight += Number(col.weight) || 1;
        });

        if (!hasCustom) {
            var remain = Math.max(wrapW - fixedBudget, visibleFlexible.length * 80);
            if (visibleFlexible.length && totalWeight > 0) {
                var used = 0;
                visibleFlexible.forEach(function (col, idx) {
                    var w;
                    if (idx === visibleFlexible.length - 1) {
                        w = Math.max(col.minWidth || 80, remain - used);
                    } else {
                        w = Math.max(col.minWidth || 80, Math.floor(remain * ((Number(col.weight) || 1) / totalWeight)));
                        used += w;
                    }
                    widthsMap[col.key] = w;
                });
            }
        } else if (visibleFlexible.length && totalWeight > 0) {
            var assigned = 0;
            Object.keys(widthsMap).forEach(function (k) { assigned += widthsMap[k]; });
            var remainCustom = Math.max(wrapW - assigned, visibleFlexible.length * 80);
            var usedC = 0;
            visibleFlexible.forEach(function (col, idx) {
                var w;
                if (idx === visibleFlexible.length - 1) {
                    w = Math.max(col.minWidth || 80, remainCustom - usedC);
                } else {
                    w = Math.max(col.minWidth || 80, Math.floor(remainCustom * ((Number(col.weight) || 1) / totalWeight)));
                    usedC += w;
                }
                widthsMap[col.key] = w;
            });
        }

        // Always fit within the page width (scale down if needed).
        leadsApplyFittedColumnWidths($table, state, widthsMap, true);
    }

    function leadsStartColumnResize(e, $th) {
        e.preventDefault();
        e.stopPropagation();
        var $table = $th.closest('table.crm-leads-table');
        var key = String($th.attr('data-col-key') || '');
        var meta = leadsColumnMeta(key);
        if (!$table.length || !meta) {
            return;
        }
        var state = leadsLoadColumnVisibility();
        var widths = leadsCaptureCurrentColumnWidths($table, state);
        leadsSaveColumnWidths(widths);
        leadsRedistributeColumnWidths(state);

        var startX = e.clientX || (e.originalEvent && e.originalEvent.touches && e.originalEvent.touches[0]
            ? e.originalEvent.touches[0].clientX
            : 0);
        var startW = Math.round($th.outerWidth());
        leadsColResizeState = {
            key: key,
            meta: meta,
            $table: $table,
            $th: $th,
            startX: startX,
            startW: startW,
            minW: meta.minWidth || 80,
            widths: leadsCaptureCurrentColumnWidths($table, state)
        };
        $table.addClass('is-col-resizing');
        $th.find('.ld-col-resizer').addClass('is-active');
    }

    function leadsMoveColumnResize(e) {
        if (!leadsColResizeState) {
            return;
        }
        var clientX = e.clientX;
        if (clientX == null && e.originalEvent && e.originalEvent.touches && e.originalEvent.touches[0]) {
            clientX = e.originalEvent.touches[0].clientX;
        }
        if (clientX == null) {
            return;
        }
        e.preventDefault();
        var delta = clientX - leadsColResizeState.startX;
        var nextW = Math.max(leadsColResizeState.minW, Math.round(leadsColResizeState.startW + delta));
        var widths = $.extend({}, leadsColResizeState.widths || leadsLoadColumnWidths());
        widths[leadsColResizeState.key] = nextW;
        // Keep all columns inside the page by fitting to wrap width while dragging.
        leadsApplyFittedColumnWidths(leadsColResizeState.$table, leadsLoadColumnVisibility(), widths, true);
    }

    function leadsEndColumnResize() {
        if (!leadsColResizeState) {
            return;
        }
        var $table = leadsColResizeState.$table;
        var state = leadsLoadColumnVisibility();
        var widths = leadsCaptureCurrentColumnWidths($table, state);
        leadsSaveColumnWidths(widths);
        leadsRedistributeColumnWidths(state);
        $table.removeClass('is-col-resizing');
        $table.find('.ld-col-resizer.is-active').removeClass('is-active');
        leadsColResizeState = null;
        if (typeof scheduleLeadsTablePaneHeight === 'function') {
            scheduleLeadsTablePaneHeight();
        }
    }

    function leadsInitColumnResize() {
        var $table = $('.crm-leads-ui table.crm-leads-table');
        if (!$table.length) {
            return;
        }
        $(document)
            .off('mousedown.leadsColResize touchstart.leadsColResize', '.crm-leads-ui table.crm-leads-table thead th .ld-col-resizer')
            .on('mousedown.leadsColResize touchstart.leadsColResize', '.crm-leads-ui table.crm-leads-table thead th .ld-col-resizer', function (e) {
                if (e.type === 'mousedown' && e.which !== 1) {
                    return;
                }
                leadsStartColumnResize(e, $(this).closest('th'));
            });

        $(document)
            .off('mousemove.leadsColResize touchmove.leadsColResize')
            .on('mousemove.leadsColResize touchmove.leadsColResize', function (e) {
                leadsMoveColumnResize(e);
            });

        $(document)
            .off('mouseup.leadsColResize touchend.leadsColResize touchcancel.leadsColResize')
            .on('mouseup.leadsColResize touchend.leadsColResize touchcancel.leadsColResize', function () {
                leadsEndColumnResize();
            });

        $(document)
            .off('dblclick.leadsColResize', '.crm-leads-ui table.crm-leads-table thead th .ld-col-resizer')
            .on('dblclick.leadsColResize', '.crm-leads-ui table.crm-leads-table thead th .ld-col-resizer', function (e) {
                e.preventDefault();
                e.stopPropagation();
                var key = String($(this).closest('th').attr('data-col-key') || '');
                var widths = leadsLoadColumnWidths();
                if (key && Object.prototype.hasOwnProperty.call(widths, key)) {
                    delete widths[key];
                    if (!Object.keys(widths).length) {
                        leadsClearColumnWidths();
                    } else {
                        leadsSaveColumnWidths(widths);
                    }
                    leadsRedistributeColumnWidths(leadsLoadColumnVisibility());
                    if (typeof scheduleLeadsTablePaneHeight === 'function') {
                        scheduleLeadsTablePaneHeight();
                    }
                }
            });
    }

    function leadsNormalizeColumnVisibility(raw) {
        var vis = leadsDefaultColumnVisibility();
        if (!raw || typeof raw !== 'object') {
            return vis;
        }
        LEADS_TABLE_COLUMNS.forEach(function (col) {
            if (col.locked) {
                vis[col.key] = true;
                return;
            }
            if (Object.prototype.hasOwnProperty.call(raw, col.key)) {
                vis[col.key] = !!raw[col.key];
            }
        });
        return vis;
    }

    function leadsLoadColumnVisibility() {
        return leadsNormalizeColumnVisibility(leadsColumnVisibilityState);
    }

    function leadsSaveColumnVisibility(vis) {
        var state = leadsNormalizeColumnVisibility(vis || leadsDefaultColumnVisibility());
        leadsColumnVisibilityState = state;
        if (leadsColumnSaveTimer) {
            window.clearTimeout(leadsColumnSaveTimer);
        }
        leadsColumnSaveTimer = window.setTimeout(function () {
            leadsColumnSaveTimer = null;
            if (leadsColumnSaveXhr && typeof leadsColumnSaveXhr.abort === 'function') {
                try { leadsColumnSaveXhr.abort(); } catch (err) {}
            }
            leadsColumnSaveXhr = $.ajax({
                url: LEADS_COL_SAVE_URL,
                method: 'POST',
                dataType: 'json',
                data: { visibility: JSON.stringify(state) }
            }).done(function (res) {
                if (res && res.success && res.visibility) {
                    leadsColumnVisibilityState = leadsNormalizeColumnVisibility(res.visibility);
                }
            }).fail(function (xhr) {
                if (xhr && xhr.statusText === 'abort') {
                    return;
                }
                window.console && console.warn && console.warn('Could not save leads column settings.');
            });
        }, 180);
        return state;
    }

    function leadsReadVisibilityFromCheckboxes() {
        var vis = leadsDefaultColumnVisibility();
        $('#leadsColumnSettingsList .js-leads-col-toggle').each(function () {
            var key = String($(this).attr('data-col-key') || '');
            var meta = leadsColumnMeta(key);
            if (!key || !meta) {
                return;
            }
            vis[key] = meta.locked ? true : $(this).prop('checked');
        });
        return vis;
    }

    function leadsSyncColumnCheckboxes(vis) {
        var state = vis || leadsLoadColumnVisibility();
        $('#leadsColumnSettingsList .js-leads-col-toggle').each(function () {
            var $input = $(this);
            var key = String($input.attr('data-col-key') || '');
            var meta = leadsColumnMeta(key);
            if (!key || !meta) {
                return;
            }
            var checked = meta.locked ? true : state[key] !== false;
            $input.prop('checked', checked);
            if (meta.locked) {
                $input.prop('disabled', true);
                $input.closest('.leads-col-settings-item').addClass('is-locked');
            }
        });
    }

    function leadsApplyColumnVisibility(vis) {
        var state = vis || leadsLoadColumnVisibility();
        var $table = $('.crm-leads-ui table.crm-leads-table');
        if (!$table.length) {
            return state;
        }
        LEADS_TABLE_COLUMNS.forEach(function (col) {
            var show = leadsColumnIsVisible(col, state);
            $table.attr('data-col-' + col.key, show ? '1' : '0');
            $table.find('.' + col.className).toggleClass('is-col-hidden', !show);
            if (col.key === 'services') {
                $table.find('.col-services').toggleClass('is-col-hidden', !show);
            }
            if (col.key === 'stage') {
                $table.find('.col-stage').toggleClass('is-col-hidden', !show);
            }
        });
        leadsRedistributeColumnWidths(state);
        if (typeof scheduleLeadsTablePaneHeight === 'function') {
            scheduleLeadsTablePaneHeight();
        }
        return state;
    }

    function leadsPersistAndApplyFromCheckboxes() {
        var vis = leadsReadVisibilityFromCheckboxes();
        leadsSaveColumnVisibility(vis);
        leadsApplyColumnVisibility(vis);
        return vis;
    }

    $('#btnOpenLeadsColumnSettings').on('click', function () {
        leadsSyncColumnCheckboxes(leadsLoadColumnVisibility());
        $('#leadsColumnSettingsModal').modal('show');
    });

    $(document).on('change', '#leadsColumnSettingsList .js-leads-col-toggle', function () {
        var meta = leadsColumnMeta(String($(this).attr('data-col-key') || ''));
        if (meta && meta.locked) {
            $(this).prop('checked', true);
            return;
        }
        leadsPersistAndApplyFromCheckboxes();
    });

    $('#btnLeadsColumnsShowAll').on('click', function () {
        var vis = leadsDefaultColumnVisibility();
        leadsSaveColumnVisibility(vis);
        leadsApplyColumnVisibility(vis);
        leadsSyncColumnCheckboxes(vis);
    });

    $('#btnLeadsColumnsHideOptional').on('click', function () {
        var vis = leadsDefaultColumnVisibility();
        LEADS_TABLE_COLUMNS.forEach(function (col) {
            if (!col.locked) {
                vis[col.key] = false;
            }
        });
        leadsSaveColumnVisibility(vis);
        leadsApplyColumnVisibility(vis);
        leadsSyncColumnCheckboxes(vis);
    });

    $('#btnLeadsColumnsReset').on('click', function () {
        var vis = leadsDefaultColumnVisibility();
        leadsClearColumnWidths();
        leadsSaveColumnVisibility(vis);
        leadsApplyColumnVisibility(vis);
        leadsSyncColumnCheckboxes(vis);
    });

    leadsColumnVisibilityState = leadsNormalizeColumnVisibility(leadsColumnVisibilityState);
    leadsApplyColumnVisibility(leadsColumnVisibilityState);
    leadsInitColumnResize();

    function buildSendLinkPostData() {
        var data = [];
        (sendLinkDefaultFields || []).forEach(function (field) {
            data.push({ name: 'fields[]', value: field });
        });
        return $.param(data);
    }

    function sendLinkGuestName() {
        return String($('#sendLinkGuestName').val() || '').trim();
    }

    function sendLinkGuestEmail() {
        return String($('#sendLinkGuestEmail').val() || '').trim();
    }

    function sendLinkGuestPhone() {
        return String($('#sendLinkGuestPhone').val() || '').trim();
    }

    function sendLinkInitials(name) {
        var parts = String(name || '').trim().split(/\s+/).filter(Boolean);
        if (!parts.length) {
            return 'GT';
        }
        var initials = parts.slice(0, 2).map(function (p) {
            return p.charAt(0).toUpperCase();
        }).join('');
        return initials || 'GT';
    }

    function sendLinkEscapeHtml(text) {
        return $('<div>').text(text == null ? '' : String(text)).html();
    }

    function buildSendLinkCustomerMessage(url) {
        var company = sendLinkCompanyName || 'Multi Zone Travels';
        var name = sendLinkGuestName() || 'Traveller';
        var email = sendLinkGuestEmail();
        var link = String(url || $('#sendLinkUrl').val() || '').trim();
        var emojiPlane = '\u2708\uFE0F';
        var emojiGlobe = '\uD83C\uDF0D';
        var dash = '\u2014';
        var lines = [
            'Hi ' + name + ',',
            '',
            'Thank you for considering Travel with us! ' + emojiPlane,
            '',
            'Please take a moment to fill out your travel preferences using the form below. It will help us create a personalized experience just for you.',
            ''
        ];
        if (email) {
            lines.push('We\'ll keep you updated at ' + email + '.');
            lines.push('');
        }
        if (link) {
            lines.push(link);
            lines.push('');
        }
        lines.push('Looking forward to helping you plan the perfect trip! ' + emojiGlobe);
        lines.push('');
        lines.push(dash + ' ' + company);
        return lines.join('\n');
    }

    function refreshSendLinkPreview() {
        var name = sendLinkGuestName() || 'Guest';
        var email = sendLinkGuestEmail();
        var link = String($('#sendLinkUrl').val() || '').trim();
        var company = sendLinkCompanyName || 'Multi Zone Travels';
        var emojiPlane = '\u2708\uFE0F';
        var emojiGlobe = '\uD83C\uDF0D';
        var dash = '\u2014';
        var html = '';
        html += 'Hi <strong>' + sendLinkEscapeHtml(name) + '</strong>,\n';
        html += 'Thank you for considering Travel with us! ' + emojiPlane + '\n';
        html += 'Please take a moment to fill out your travel preferences using the form below. It will help us create a personalized experience just for you.\n';
        if (email) {
            html += 'We\'ll keep you updated at <strong>' + sendLinkEscapeHtml(email) + '</strong>.\n';
        }
        if (link) {
            html += '<a class="sl-wa-link" href="' + sendLinkEscapeHtml(link) + '" target="_blank" rel="noopener">' + sendLinkEscapeHtml(link) + '</a>\n';
        }
        html += 'Looking forward to helping you plan the perfect trip! ' + emojiGlobe + '\n';
        html += dash + ' ' + sendLinkEscapeHtml(company);
        $('#sendLinkPreviewBody').html(html);
        $('#sendLinkAvatar').text(sendLinkInitials(name));
        var now = new Date();
        var hours = now.getHours();
        var minutes = now.getMinutes();
        var ampm = hours >= 12 ? 'PM' : 'AM';
        hours = hours % 12;
        if (hours === 0) {
            hours = 12;
        }
        $('#sendLinkPreviewTime').text(hours + ':' + (minutes < 10 ? '0' : '') + minutes + ' ' + ampm);
    }

    function resetSendLinkGuestFields() {
        $('#sendLinkGuestName').val('');
        $('#sendLinkGuestPhone').val('');
        $('#sendLinkGuestEmail').val('');
        $('#sendLinkAvatar').text('GT');
    }

    function requestSendLink($triggerBtn) {
        var $btn = $triggerBtn || $();
        if ($btn.length) {
            $btn.prop('disabled', true);
        }
        $('#sendLinkUrl').val('');
        $('#sendLinkReady').addClass('d-none');
        $('#sendLinkError').addClass('d-none').text('');
        $('#sendLinkLoading').removeClass('d-none');

        $.post('crm/ajax/create_intake_link.php', buildSendLinkPostData(), function (res) {
            if ($btn.length) {
                $btn.prop('disabled', false);
            }
            $('#sendLinkLoading').addClass('d-none');
            if (res && res.success && res.url) {
                $('#sendLinkUrl').val(res.url);
                $('#sendLinkReady').removeClass('d-none');
                refreshSendLinkPreview();
            } else {
                $('#sendLinkError').text((res && res.message) ? res.message : 'Could not create link.').removeClass('d-none');
            }
        }, 'json').fail(function () {
            if ($btn.length) {
                $btn.prop('disabled', false);
            }
            $('#sendLinkLoading').addClass('d-none');
            $('#sendLinkError').text('Request failed. Please try again.').removeClass('d-none');
        });
    }

    $('#btnOpenSendLinkModal').on('click', function () {
        resetSendLinkGuestFields();
        refreshSendLinkPreview();
        $('#sendLinkModal').modal('show');
        requestSendLink($(this));
    });

    $('#sendLinkGuestName, #sendLinkGuestEmail').on('input', function () {
        refreshSendLinkPreview();
    });

    function flashSendLinkBtn($btn, doneLabel) {
        var original = $btn.data('orig-label') || $btn.html();
        $btn.data('orig-label', original);
        $btn.html(doneLabel || 'Copied!');
        window.setTimeout(function () {
            $btn.html(original);
        }, 1800);
    }

    $('#btnCopySendLink').on('click', function () {
        var $btn = $(this);
        var message = buildSendLinkCustomerMessage($('#sendLinkUrl').val() || '');
        copyTextToClipboard(message, function () {
            flashSendLinkBtn($btn, '<i class="fas fa-check"></i> Copied');
        });
    });

    $('#btnOpenWhatsAppSendLink').on('click', function () {
        var phoneDigits = sendLinkGuestPhone().replace(/\D+/g, '');
        if (phoneDigits.length === 10) {
            phoneDigits = '91' + phoneDigits;
        }
        var message = buildSendLinkCustomerMessage($('#sendLinkUrl').val() || '');
        var waUrl = 'https://wa.me/' + (phoneDigits ? phoneDigits : '') + '?text=' + encodeURIComponent(message);
        window.open(waUrl, '_blank', 'noopener');
    });

    function copyTextToClipboard(text, onSuccess) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(onSuccess).catch(function () {
                copyTextToClipboardFallback(text, onSuccess);
            });
            return;
        }
        copyTextToClipboardFallback(text, onSuccess);
    }

    function copyTextToClipboardFallback(text, onSuccess) {
        var $ta = $('<textarea>').css({ position: 'fixed', left: '-9999px', top: '0' }).val(text).appendTo('body');
        $ta[0].focus();
        $ta[0].select();
        try {
            if (document.execCommand('copy') && typeof onSuccess === 'function') {
                onSuccess();
            }
        } catch (e) {}
        $ta.remove();
    }

    $modal.on('hidden.bs.modal', function () {
        loadSeq++;
        showLeadFormLoading();
    });

    $(document).on('click', '.btn-lead-form-cancel', function () {
        $modal.modal('hide');
    });

    function escHtml(value) {
        return $('<div>').text(value == null ? '' : String(value)).html();
    }

    function formatLeadValue(value) {
        if (value === null || value === undefined) return '—';
        if (Array.isArray(value)) {
            if (!value.length) return '—';
            return value.map(function (v) { return formatLeadValue(v); }).join(', ');
        }
        if (typeof value === 'object') {
            var keys = Object.keys(value);
            if (!keys.length) return '—';
            return keys.map(function (k) {
                return k + ': ' + formatLeadValue(value[k]);
            }).join(' | ');
        }
        var str = String(value).trim();
        return str === '' ? '—' : str;
    }

    function normalizePayloadKey(key) {
        return String(key || '').replace(/\[\]/g, '').trim();
    }

    function formatLeadKeyLabel(key) {
        var normalized = normalizePayloadKey(key);
        var labelMap = {
            customer_name: 'Customer Name',
            customer_phone: 'Customer Phone',
            customer_email: 'Customer Email',
            lead_source: 'Lead Source',
            referred_by: 'Referred By',
            assign_to: 'Assigned To',
            services: 'Services',
            tp_travel_date: 'Preferred Travel Date',
            tp_departure: 'Departure City',
            tp_arrival: 'Arrival City',
            tp_tour_type: 'Tour Type',
            tp_destination: 'Selected Destinations',
            tp_budget: 'Approx. Budget',
            tp_hotel_category: 'Preferred Hotel Categories',
            tp_rooms: 'Rooms',
            tp_child_cnb: 'CNB (Child No Bed)',
            tp_child_cwb: 'CWB (Child With Bed)',
            tp_child_bed_type: 'Child Bed Type',
            tp_adults: 'Adults',
            tp_children: 'Children',
            tp_children_ages: 'Children Ages',
            tp_notes: 'Package Notes',
            itinerary_total_nights: 'Total Nights',
            itinerary_total_days: 'Total Days',
            itinerary_dest_id: 'Itinerary Destinations',
            itinerary_dest_nights: 'Destination Nights',
            itinerary_days: 'Day-wise Itinerary',
            cruise_embark_date: 'Cruise Embark Date',
            cruise_line: 'Cruise Line / Route',
            cruise_cabin: 'Cruise Cabin',
            cruise_pax: 'Cruise Passengers',
            cruise_port: 'Cruise Port',
            vehicle_type: 'Vehicle Types'
        };
        if (labelMap[normalized]) {
            return labelMap[normalized];
        }
        var text = normalized.replace(/[_\[\]]+/g, ' ').trim();
        if (text === '') return 'Field';
        text = text.replace(/\s+/g, ' ');
        return text.replace(/\b\w/g, function (m) { return m.toUpperCase(); });
    }

    function buildLeadInfoGridRows(items) {
        var html = '';
        items.forEach(function (item) {
            html += ''
                + '<div class="lead-detail-item">'
                + '  <span class="lead-detail-item-label">' + escHtml(item.label) + '</span>'
                + '  <span class="lead-detail-item-value">' + escHtml(formatLeadValue(item.value)) + '</span>'
                + '</div>';
        });
        return html;
    }

    function renderLeadDetailsModal(lead) {
        var payload = (lead && lead.payload && typeof lead.payload === 'object') ? lead.payload : {};
        var serviceLabels = {
            tour_package: 'Tour Package',
            cruise: 'Cruise',
            flight: 'Flight',
            hotel: 'Hotel',
            vehicle: 'Vehicle',
            sightseeing: 'Sightseeing',
            visa: 'Visa',
            passport: 'Passport',
            forex: 'Forex'
        };
        var servicesText = '—';
        if (Array.isArray(lead.services) && lead.services.length) {
            servicesText = lead.services.map(function (s) {
                return serviceLabels[s] || s;
            }).join(', ');
        }
        var destText = lead.travel_destination_text
            || [lead.travel_dest_display, lead.travel_departure_display].filter(Boolean).join(' | ')
            || '—';

        var infoItems = [
            { label: 'Lead UID', value: lead.lead_uid },
            { label: 'Created', value: lead.created_at },
            { label: 'Stage', value: lead.stage_label || lead.stage },
            { label: 'Guest', value: lead.customer_display_name || lead.customer_name },
            { label: 'Pax', value: lead.pax_text },
            { label: 'Phone', value: lead.customer_phone },
            { label: 'Email', value: lead.customer_email },
            { label: 'Lead Source', value: lead.lead_source },
            { label: 'Referred By', value: lead.referred_by },
            { label: 'Assigned To', value: lead.assign_to },
            { label: 'Destination', value: destText },
            { label: 'Travel Date', value: lead.travel_date_text },
            { label: 'Services', value: servicesText }
        ];
        var basicRows = buildLeadInfoGridRows(infoItems);

        var payloadRows = '';
        var payloadKeys = Object.keys(payload);
        var skipKeys = {
            customer_initial: true,
            customer_name: true,
            customer_phone: true,
            customer_email: true,
            lead_source: true,
            referred_by: true,
            assign_to: true,
            services: true,
            itinerary_total_nights: true,
            itinerary_total_days: true,
            tp_pets: true
        };

        function resolveDestinationNames(value) {
            var ids = Array.isArray(value) ? value : [value];
            var names = [];
            ids.forEach(function (id) {
                id = String(id).trim();
                if (id === '') return;
                names.push(destinationLookup[id] ? destinationLookup[id] : id);
            });
            return names.length ? names.join(', ') : '—';
        }
        var seenSignature = {};
        payloadKeys.sort();
        payloadKeys.forEach(function (key) {
            var normalizedKey = normalizePayloadKey(key);
            if (skipKeys[normalizedKey]) {
                return;
            }
            var formatted;
            if (normalizedKey === 'tp_destination' || normalizedKey === 'itinerary_dest_id') {
                formatted = resolveDestinationNames(payload[key]);
            } else {
                formatted = formatLeadValue(payload[key]);
            }
            if (formatted === '—') {
                return;
            }
            var signature = normalizedKey + '::' + formatted;
            if (seenSignature[signature]) {
                return;
            }
            seenSignature[signature] = true;
            payloadRows += '<tr><th><span class="lead-detail-key">' + escHtml(formatLeadKeyLabel(normalizedKey)) + '</span></th><td>' + escHtml(formatted) + '</td></tr>';
        });
        if (!payloadRows) {
            payloadRows = '<tr><td colspan="2" class="text-muted">No additional unique payload details.</td></tr>';
        }

        var html = ''
            + '<div class="lead-detail-block">'
            + '  <div class="lead-detail-block-hd">Lead Summary <span class="lead-detail-sub">All list fields</span></div>'
            + '  <div class="lead-detail-grid">' + basicRows + '</div>'
            + '</div>'
            + '<div class="lead-detail-block">'
            + '  <div class="lead-detail-block-hd">Form Payload <span class="lead-detail-sub">Submitted details</span></div>'
            + '  <table class="lead-detail-table"><tbody>' + payloadRows + '</tbody></table>'
            + '</div>';

        $('#leadDetailModalBody').html(html);
        $('#leadDetailModalLabel').html('<i class="fas fa-expand-alt mr-2"></i>' + escHtml(formatLeadValue(lead.lead_uid)));
        $('#btnLeadExpandEdit').attr('data-lead-id', String(lead.id || ''));
    }

    $(document).on('change', '.js-lead-stage-select', function () {
        var $sel = $(this);
        var leadId = Number($sel.attr('data-lead-id') || 0);
        var stage = String($sel.val() || '');
        var previousStage = String($sel.attr('data-stage') || '');
        if (!leadId || !stage || stage === previousStage) {
            return;
        }

        $sel.prop('disabled', true);
        $.post('crm/ajax/update_lead_stage.php', { lead_id: leadId, stage: stage }, function (res) {
            if (res && res.success) {
                var nextStage = String(res.stage || stage);
                var nextClass = 'stage-' + nextStage;
                $sel.attr('data-stage', nextStage);
                $sel.removeClass('stage-new_lead stage-quoted stage-confirmed stage-lost');
                $sel.addClass(nextClass);
                var isAuto = res.stage_is_auto !== false && nextStage !== 'lost';
                $sel.attr('data-stage-auto', isAuto ? '1' : '0');
                $sel.find('option').each(function () {
                    var val = String($(this).val() || '');
                    $(this).prop('disabled', nextStage !== 'lost' && val !== 'lost');
                });
                for (var i = 0; i < leadRowsData.length; i++) {
                    if (Number(leadRowsData[i].id) === leadId) {
                        leadRowsData[i].stage = nextStage;
                        leadRowsData[i].stage_label = res.stage_label || leadRowsData[i].stage_label;
                        leadRowsData[i].stage_class = nextClass;
                        break;
                    }
                }
            } else {
                window.alert((res && res.message) ? res.message : 'Could not update stage.');
                $sel.val(previousStage);
            }
        }, 'json').fail(function () {
            window.alert('Could not update stage. Please try again.');
            $sel.val(previousStage);
        }).always(function () {
            $sel.prop('disabled', false);
        });
    });

    $(document).on('show.bs.dropdown', '.lead-actions-more', function () {
        $('.crm-leads-ui table.crm-leads-table tbody tr.is-actions-dropdown-open')
            .not($(this).closest('tr'))
            .removeClass('is-actions-dropdown-open');
        $(this).closest('tr').addClass('is-actions-dropdown-open');
    });

    $(document).on('hide.bs.dropdown hidden.bs.dropdown', '.lead-actions-more', function () {
        $(this).closest('tr').removeClass('is-actions-dropdown-open');
    });

    $(document).on('click', '.js-lead-action-preview, .js-lead-row-expand', function (e) {
        e.preventDefault();
        e.stopPropagation();
        openLeadViewModal(Number($(this).attr('data-lead-id') || 0));
    });

    $(document).on('click', '#btnLeadExpandEdit', function () {
        var leadId = Number($(this).attr('data-lead-id') || 0);
        $('#leadDetailModal').modal('hide');
        if (leadId > 0) {
            window.setTimeout(function () {
                openLeadEditModal(leadId);
            }, 220);
        }
    });

    $(document).on('click', '.js-lead-action-email', function (e) {
        e.preventDefault();
        var $wrap = $(this).closest('.lead-actions-more');
        var email = ($wrap.attr('data-lead-email') || '').trim();
        if (!email) {
            return;
        }
        window.location.href = 'mailto:' + encodeURIComponent(email);
    });

    $(document).on('click', '.js-lead-action-whatsapp', function (e) {
        e.preventDefault();
        var $wrap = $(this).closest('.lead-actions-more');
        var phone = ($wrap.attr('data-lead-phone') || '').replace(/\D+/g, '');
        if (!phone) {
            return;
        }
        window.open('https://wa.me/' + phone, '_blank', 'noopener');
    });

    var smsTplState = {
        leadId: 0,
        tab: 'default',
        templateId: '',
        guestName: '',
        guestPhone: '',
        guestEmail: '',
        company: '',
        agentName: ''
    };

    function smsTplEsc(value) {
        return String(value || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
    }

    function smsTplFirstName(fullName) {
        var name = String(fullName || '').trim();
        if (!name) {
            return 'Guest';
        }
        return name.split(/\s+/)[0];
    }

    function smsTplFill(template) {
        var guest = smsTplFirstName(smsTplState.guestName);
        var agent = smsTplState.agentName || 'Team';
        var company = smsTplState.company || 'Multi Zone Travels';
        return String(template || '')
            .replace(/\{guest\}/g, guest)
            .replace(/\{agent\}/g, agent)
            .replace(/\{company\}/g, company);
    }

    function smsTplCatalog() {
        return {
            default: [
                {
                    id: 'phone_not_picked',
                    title: "Phone Didn't Picked",
                    body: 'Dear {guest},\nGreetings! We tried to reach you for your trip planning but couldn\'t talk to you. Pls call {agent}.\nThanks,\n{company}'
                },
                {
                    id: 'follow_up',
                    title: 'Follow Up',
                    body: 'Dear {guest},\nJust following up on your travel enquiry. Please let us know a convenient time to discuss your trip.\nThanks,\n{agent}\n{company}'
                },
                {
                    id: 'thanks_enquiry',
                    title: 'Thanks for Enquiry',
                    body: 'Dear {guest},\nThank you for your enquiry with {company}. Our team will share suitable options shortly.\nRegards,\n{agent}'
                }
            ],
            whatsapp: [
                {
                    id: 'wa_intro',
                    title: 'WhatsApp Intro',
                    body: 'Hi {guest},\nThis is {agent} from {company}. Sharing travel options for your trip. Please reply here if you have any preferences.'
                },
                {
                    id: 'wa_callback',
                    title: 'Callback Request',
                    body: 'Hi {guest},\nWe tried calling you regarding your trip planning. Please reply or call {agent} when free.\n— {company}'
                }
            ],
            email: [
                {
                    id: 'email_intro',
                    title: 'Travel Enquiry Reply',
                    subject: 'Your travel enquiry with {company}',
                    body: 'Dear {guest},\n\nThank you for contacting {company}. We are reviewing your requirements and will share suitable packages shortly.\n\nBest regards,\n{agent}\n{company}'
                },
                {
                    id: 'email_callback',
                    title: "Couldn't Reach You",
                    subject: 'Missed call regarding your trip — {company}',
                    body: 'Dear {guest},\n\nWe tried to reach you for your trip planning but couldn\'t connect. Please reply to this email or call {agent}.\n\nThanks,\n{company}'
                }
            ]
        };
    }

    function smsTplGetSelectedBody() {
        if (smsTplState.tab === 'custom') {
            return String($('#smsTplCustomText').val() || '').trim();
        }
        var catalog = smsTplCatalog();
        var list = catalog[smsTplState.tab] || [];
        var selected = null;
        for (var i = 0; i < list.length; i++) {
            if (list[i].id === smsTplState.templateId) {
                selected = list[i];
                break;
            }
        }
        if (!selected && list.length) {
            selected = list[0];
        }
        return selected ? smsTplFill(selected.body) : '';
    }

    function smsTplGetSelectedSubject() {
        if (smsTplState.tab !== 'email') {
            return '';
        }
        var catalog = smsTplCatalog();
        var list = catalog.email || [];
        var selected = null;
        for (var i = 0; i < list.length; i++) {
            if (list[i].id === smsTplState.templateId) {
                selected = list[i];
                break;
            }
        }
        if (!selected && list.length) {
            selected = list[0];
        }
        return selected ? smsTplFill(selected.subject || '') : '';
    }

    function smsTplRenderList() {
        var $list = $('#smsTplList');
        var $custom = $('#smsTplCustomWrap');
        var isEmail = smsTplState.tab === 'email';
        var isCustom = smsTplState.tab === 'custom';

        $('#btnSmsTplSendEmail').toggleClass('d-none', !isEmail);
        $('#btnSmsTplSendWhatsApp').toggleClass('d-none', isEmail);
        $('#btnSmsTplSendSms').toggleClass('d-none', isEmail);

        if (isCustom) {
            $list.hide().empty();
            $custom.addClass('is-visible');
            if (!$('#smsTplCustomText').val()) {
                $('#smsTplCustomText').val(smsTplFill('Dear {guest},\n\n\nThanks,\n{agent}\n{company}'));
            }
            return;
        }

        $custom.removeClass('is-visible');
        $list.show();
        var catalog = smsTplCatalog();
        var items = catalog[smsTplState.tab] || [];
        if (!smsTplState.templateId && items.length) {
            smsTplState.templateId = items[0].id;
        }
        var html = '';
        items.forEach(function (item) {
            var checked = item.id === smsTplState.templateId ? ' checked' : '';
            html += ''
                + '<label class="sms-tpl-item">'
                + '  <span class="sms-tpl-item-hd">'
                + '    <input type="radio" name="smsTplRadio" value="' + smsTplEsc(item.id) + '"' + checked + '>'
                + '    <span class="sms-tpl-item-title">' + smsTplEsc(item.title) + '</span>'
                + '  </span>'
                + '  <div class="sms-tpl-item-body">' + smsTplEsc(smsTplFill(item.body)) + '</div>'
                + '</label>';
        });
        $list.html(html || '<div class="text-muted small">No templates available.</div>');
    }

    function openSmsTemplatesModal(leadId) {
        var lead = findLeadRowById(Number(leadId || 0));
        if (!lead) {
            return;
        }
        smsTplState.leadId = Number(lead.id || 0);
        smsTplState.tab = 'default';
        smsTplState.templateId = '';
        smsTplState.guestName = lead.customer_name || lead.customer_display_name || '';
        smsTplState.guestPhone = String(lead.customer_phone || '').trim();
        smsTplState.guestEmail = String(lead.customer_email || '').trim();
        smsTplState.company = sendLinkCompanyName || 'Multi Zone Travels';
        smsTplState.agentName = smsTplAgentName || 'Team';

        $('#smsTplLeadMeta').text(
            (lead.lead_uid ? lead.lead_uid + ' · ' : '') +
            (smsTplState.guestName || 'Guest') +
            (smsTplState.guestPhone ? ' · ' + smsTplState.guestPhone : '')
        );
        $('#smsTemplatesModal .sms-tpl-tab').removeClass('is-active');
        $('#smsTemplatesModal .sms-tpl-tab[data-sms-tab="default"]').addClass('is-active');
        $('#smsTplCustomText').val('');
        smsTplRenderList();
        $('#smsTemplatesModal').modal('show');
    }

    $(document).on('click', '.js-lead-action-message', function (e) {
        e.preventDefault();
        openSmsTemplatesModal(Number($(this).attr('data-lead-id') || 0));
    });

    $(document).on('click', '#smsTemplatesModal .sms-tpl-tab', function () {
        var tab = $(this).attr('data-sms-tab') || 'default';
        smsTplState.tab = tab;
        smsTplState.templateId = '';
        $('#smsTemplatesModal .sms-tpl-tab').removeClass('is-active');
        $(this).addClass('is-active');
        smsTplRenderList();
    });

    $(document).on('change', '#smsTplList input[name="smsTplRadio"]', function () {
        smsTplState.templateId = String($(this).val() || '');
    });

    $('#btnSmsTplSendWhatsApp').on('click', function () {
        var phone = String(smsTplState.guestPhone || '').replace(/\D+/g, '');
        var text = smsTplGetSelectedBody();
        if (!phone) {
            window.alert('Guest phone number is missing.');
            return;
        }
        if (!text) {
            window.alert('Please select or write a message.');
            return;
        }
        window.open('https://wa.me/' + phone + '?text=' + encodeURIComponent(text), '_blank', 'noopener');
    });

    $('#btnSmsTplSendSms').on('click', function () {
        var phone = String(smsTplState.guestPhone || '').replace(/\D+/g, '');
        var text = smsTplGetSelectedBody();
        if (!phone) {
            window.alert('Guest phone number is missing.');
            return;
        }
        if (!text) {
            window.alert('Please select or write a message.');
            return;
        }
        var href = 'sms:' + phone + '?body=' + encodeURIComponent(text);
        window.location.href = href;
    });

    $('#btnSmsTplSendEmail').on('click', function () {
        var email = String(smsTplState.guestEmail || '').trim();
        var subject = smsTplGetSelectedSubject();
        var body = smsTplGetSelectedBody();
        var guestName = String(smsTplState.guestName || '').trim() || email;
        var destination = '';
        var lead = findLeadRowById(smsTplState.leadId);
        if (lead) {
            destination = String(lead.travel_dest_display || '').trim();
            if (!destination && Array.isArray(lead.destination_names) && lead.destination_names.length) {
                destination = lead.destination_names.filter(function (name) {
                    return String(name || '').trim() && String(name).toUpperCase() !== 'N/A';
                }).join(', ');
            }
        }

        if (!body) {
            window.alert('Please select or write a message.');
            return;
        }

        if (!window.QSupplierMail || typeof window.QSupplierMail.open !== 'function') {
            window.alert('Email composer is not available. Please refresh the page.');
            return;
        }

        var recipients = [];
        if (email) {
            recipients.push({
                id: '',
                name: guestName,
                email: email
            });
        }

        $('#smsTemplatesModal').modal('hide');
        window.setTimeout(function () {
            window.QSupplierMail.open({
                skipTemplate: true,
                subject: subject || ('Message from ' + (smsTplState.company || 'Multi Zone Travels')),
                bodyText: body,
                recipients: recipients,
                destination: destination
            });
        }, 250);
    });

    var leadAttachmentsLeadId = 0;

    function escAttachmentHtml(value) {
        return String(value || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
    }

    function showLeadAttachmentsError(message) {
        var $err = $('#leadAttachmentsError');
        if (!message) {
            $err.addClass('d-none').text('');
            return;
        }
        $err.removeClass('d-none').text(message);
    }

    function renderLeadAttachmentsList(items) {
        var $list = $('#leadAttachmentsList');
        var $empty = $('#leadAttachmentsEmpty');
        $list.empty();
        if (!items || !items.length) {
            $empty.removeClass('d-none');
            return;
        }
        $empty.addClass('d-none');
        items.forEach(function (item) {
            var meta = [];
            if (item.file_size_text) meta.push(item.file_size_text);
            if (item.created_at_text) meta.push(item.created_at_text);
            if (item.uploaded_by_name) meta.push(item.uploaded_by_name);
            var fileUrl = escAttachmentHtml(item.file_url || '#');
            var html = ''
                + '<div class="lead-attachment-item">'
                + '  <div class="lead-attachment-info">'
                + '    <div class="lead-attachment-name">' + escAttachmentHtml(item.original_name || 'Attachment') + '</div>'
                + '    <div class="lead-attachment-meta">' + escAttachmentHtml(meta.join(' · ')) + '</div>'
                + '  </div>'
                + '  <div class="lead-attachment-actions">'
                + '    <a class="btn btn-outline-primary btn-xs btn-sm" href="' + fileUrl + '" target="_blank" rel="noopener">Open</a>'
                + '    <button type="button" class="btn btn-outline-danger btn-xs btn-sm js-lead-attachment-delete" data-attachment-id="' + escAttachmentHtml(item.id) + '">Delete</button>'
                + '  </div>'
                + '</div>';
            $list.append(html);
        });
    }

    function loadLeadAttachmentsModal() {
        if (!leadAttachmentsLeadId) {
            return;
        }
        showLeadAttachmentsError('');
        $('#leadAttachmentsLoading').show();
        $('#leadAttachmentsEmpty').addClass('d-none');
        $('#leadAttachmentsList').empty();
        $.getJSON('crm/ajax/list_lead_attachments.php', { lead_id: leadAttachmentsLeadId })
            .done(function (response) {
                $('#leadAttachmentsLoading').hide();
                if (!response || !response.success) {
                    showLeadAttachmentsError((response && response.message) ? response.message : 'Could not load attachments.');
                    return;
                }
                var lead = response.lead || {};
                var label = (lead.lead_uid || 'Lead') + (lead.customer_name ? (' · ' + lead.customer_name) : '');
                $('#leadAttachmentsLeadMeta').text(label);
                renderLeadAttachmentsList(response.data || []);
            })
            .fail(function () {
                $('#leadAttachmentsLoading').hide();
                showLeadAttachmentsError('Could not load attachments. Please try again.');
            });
    }

    function openLeadAttachmentsModal(leadId) {
        leadAttachmentsLeadId = Number(leadId || 0);
        if (!leadAttachmentsLeadId) {
            return;
        }
        $('#leadAttachmentFile').val('');
        showLeadAttachmentsError('');
        $('#leadAttachmentsModalLabel').html('<i class="fas fa-paperclip mr-1 text-muted"></i> Lead Attachments');
        $('#leadAttachmentsModal').modal('show');
        loadLeadAttachmentsModal();
    }

    $(document).on('click', '.js-lead-action-attachment', function (e) {
        e.preventDefault();
        openLeadAttachmentsModal(Number($(this).attr('data-lead-id') || 0));
    });

    $('#btnUploadLeadAttachment').on('click', function () {
        if (!leadAttachmentsLeadId) {
            return;
        }
        var fileInput = document.getElementById('leadAttachmentFile');
        if (!fileInput || !fileInput.files || !fileInput.files.length) {
            showLeadAttachmentsError('Please choose a file to upload.');
            return;
        }
        var formData = new FormData();
        formData.append('lead_id', String(leadAttachmentsLeadId));
        formData.append('attachment', fileInput.files[0]);
        var $btn = $('#btnUploadLeadAttachment');
        $btn.prop('disabled', true);
        showLeadAttachmentsError('');
        $.ajax({
            url: 'crm/ajax/upload_lead_attachment.php',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json'
        }).done(function (response) {
            if (response && response.success) {
                $('#leadAttachmentFile').val('');
                loadLeadAttachmentsModal();
                return;
            }
            showLeadAttachmentsError((response && response.message) ? response.message : 'Could not upload attachment.');
        }).fail(function () {
            showLeadAttachmentsError('Could not upload attachment. Please try again.');
        }).always(function () {
            $btn.prop('disabled', false);
        });
    });

    $(document).on('click', '.js-lead-attachment-delete', function () {
        if (!leadAttachmentsLeadId) {
            return;
        }
        var attachmentId = Number($(this).attr('data-attachment-id') || 0);
        if (!attachmentId) {
            return;
        }
        if (!window.confirm('Delete this attachment?')) {
            return;
        }
        var $btn = $(this);
        $btn.prop('disabled', true);
        $.ajax({
            url: 'crm/ajax/delete_lead_attachment.php',
            method: 'POST',
            data: { lead_id: leadAttachmentsLeadId, attachment_id: attachmentId },
            dataType: 'json'
        }).done(function (response) {
            if (response && response.success) {
                loadLeadAttachmentsModal();
                return;
            }
            showLeadAttachmentsError((response && response.message) ? response.message : 'Could not delete attachment.');
            $btn.prop('disabled', false);
        }).fail(function () {
            showLeadAttachmentsError('Could not delete attachment. Please try again.');
            $btn.prop('disabled', false);
        });
    });

    $(document).on('click', '.js-lead-delete-btn', function (e) {
        e.preventDefault();
        var leadId = Number($(this).attr('data-lead-id') || 0);
        if (!leadId) {
            return;
        }
        if (!window.confirm('Move this lead to trash? It will be hidden from the leads list.')) {
            return;
        }
        deleteLeadByMode(leadId, 'soft', $(this));
    });

    function deleteLeadByMode(leadId, mode, $btn) {
        var $items = $btn.closest('.lead-actions-menu').find('.dropdown-item');
        $items.prop('disabled', true);
        $.ajax({
            url: 'crm/ajax/delete_lead.php',
            method: 'POST',
            data: { lead_id: leadId, mode: mode },
            dataType: 'json'
        }).done(function (response) {
            if (response && response.success) {
                window.location.reload();
                return;
            }
            window.alert((response && response.message) ? response.message : 'Could not delete lead.');
            $items.prop('disabled', false);
        }).fail(function () {
            window.alert('Could not delete lead. Please try again.');
            $items.prop('disabled', false);
        });
    }

    $(document).on('click', '.js-lead-action-duplicate', function (e) {
        e.preventDefault();
        var leadId = Number($(this).attr('data-lead-id') || 0);
        if (!leadId) {
            return;
        }
        var $item = $(this);
        $item.prop('disabled', true);
        $.ajax({
            url: 'crm/ajax/duplicate_lead.php',
            method: 'POST',
            data: { lead_id: leadId },
            dataType: 'json'
        }).done(function (response) {
            if (response && response.success) {
                window.location.reload();
                return;
            }
            window.alert((response && response.message) ? response.message : 'Could not duplicate lead.');
            $item.prop('disabled', false);
        }).fail(function () {
            window.alert('Could not duplicate lead. Please try again.');
            $item.prop('disabled', false);
        });
    });

    function openLeadViewModal(leadId) {
        var lead = null;
        for (var i = 0; i < leadRowsData.length; i++) {
            if (Number(leadRowsData[i].id) === leadId) {
                lead = leadRowsData[i];
                break;
            }
        }
        if (!lead) {
            $('#leadDetailModalBody').html('<div class="text-danger">Could not load lead details.</div>');
            $('#leadDetailModal').modal('show');
            return;
        }
        renderLeadDetailsModal(lead);
        $('#leadDetailModal').modal('show');
    }

    $(document).on('crm:lead-created', function () {
        window.location.reload();
    });

    function crmLeadsListUrl(params) {
        var query = $.extend({
            page: 1,
            per_page: leadsListPerPage,
            fy: leadsListFy,
            q: leadsListSearch
        }, params || {});
        if (!query.fy) {
            delete query.fy;
        }
        if (!query.q) {
            delete query.q;
        }
        if (Number(query.page) <= 1) {
            delete query.page;
        }
        if (Number(query.per_page) === 25) {
            delete query.per_page;
        }
        var qs = $.param(query);
        return qs ? ('crm/leads.php?' + qs) : 'crm/leads.php';
    }

    function applyLeadsSearch(searchValue) {
        var val = $.trim(String(searchValue || ''));
        if (val === leadsListSearch) {
            return;
        }
        window.location.href = crmLeadsListUrl({ q: val, page: 1 });
    }

    $('.leads-search-input').on('input', function () {
        var val = $.trim($(this).val());
        clearTimeout(leadsSearchDebounceTimer);
        leadsSearchDebounceTimer = setTimeout(function () {
            applyLeadsSearch(val);
        }, 450);
    });

    $('.leads-search-input').on('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            clearTimeout(leadsSearchDebounceTimer);
            applyLeadsSearch($(this).val());
        }
    });

    $('.leads-fy-select').on('change', function () {
        var fy = String($(this).val() || '');
        if (fy === leadsListFy) {
            return;
        }
        window.location.href = crmLeadsListUrl({ fy: fy, page: 1, q: leadsListSearch });
    });

    $('.leads-per-page-select').on('change', function () {
        var perPage = Number($(this).val()) || 25;
        if (perPage === leadsListPerPage) {
            return;
        }
        window.location.href = crmLeadsListUrl({ per_page: perPage, fy: leadsListFy, q: leadsListSearch, page: 1 });
    });

    var $activeSvcPopup = null;
    var $activeSvcWrap = null;
    var svcPopupHideTimer = null;

    function closeSvcServicesPopup() {
        if (svcPopupHideTimer) {
            window.clearTimeout(svcPopupHideTimer);
            svcPopupHideTimer = null;
        }
        if ($activeSvcPopup && $activeSvcPopup.length) {
            $activeSvcPopup.removeClass('is-open').hide().css({ visibility: '', left: '', top: '', zIndex: '' });
            if ($activeSvcWrap && $activeSvcWrap.length) {
                $activeSvcWrap.append($activeSvcPopup);
            }
        }
        $('.crm-leads-ui table.crm-leads-table tbody tr.is-svc-popup-open').removeClass('is-svc-popup-open');
        $('.crm-leads-ui .svc-pills-collapsible.is-open').removeClass('is-open');
        $activeSvcPopup = null;
        $activeSvcWrap = null;
    }

    function positionSvcServicesPopup($wrap) {
        if (!$wrap || !$wrap.length) {
            return;
        }

        var $popup = $wrap.children('.svc-pills-popup');
        if (!$popup.length && $activeSvcWrap && $activeSvcWrap[0] === $wrap[0] && $activeSvcPopup) {
            $popup = $activeSvcPopup;
        }
        if (!$popup.length) {
            return;
        }

        if ($activeSvcWrap && $activeSvcWrap[0] !== $wrap[0]) {
            closeSvcServicesPopup();
            $popup = $wrap.children('.svc-pills-popup');
            if (!$popup.length) {
                return;
            }
        }

        var $row = $wrap.closest('tr');
        $row.addClass('is-svc-popup-open');
        $wrap.addClass('is-open');

        // Portal to body so later table rows cannot cover it
        if ($popup.parent()[0] !== document.body) {
            $popup.appendTo(document.body);
        }

        $popup.addClass('is-open').css({
            display: 'flex',
            visibility: 'hidden',
            position: 'fixed',
            zIndex: 2050
        });

        var trigger = $wrap.find('.svc-pill').first()[0] || $wrap[0];
        var rect = trigger.getBoundingClientRect();
        var gap = 6;
        var popupWidth = $popup.outerWidth() || 144;
        var popupHeight = $popup.outerHeight() || 40;
        var left = Math.min(rect.left, window.innerWidth - popupWidth - 8);
        left = Math.max(8, left);
        var top = rect.bottom + gap;
        if (top + popupHeight > window.innerHeight - 8) {
            top = Math.max(8, rect.top - popupHeight - gap);
        }

        $popup.css({
            left: left + 'px',
            top: top + 'px',
            visibility: 'visible'
        });

        $activeSvcPopup = $popup;
        $activeSvcWrap = $wrap;
    }

    $(document).on('mouseenter', '.svc-pills-collapsible', function () {
        if (svcPopupHideTimer) {
            window.clearTimeout(svcPopupHideTimer);
            svcPopupHideTimer = null;
        }
        positionSvcServicesPopup($(this));
    });

    $(document).on('mouseleave', '.svc-pills-collapsible', function () {
        svcPopupHideTimer = window.setTimeout(closeSvcServicesPopup, 140);
    });

    $(document).on('mouseenter', '.svc-pills-popup', function () {
        if (svcPopupHideTimer) {
            window.clearTimeout(svcPopupHideTimer);
            svcPopupHideTimer = null;
        }
    });

    $(document).on('mouseleave', '.svc-pills-popup', function () {
        svcPopupHideTimer = window.setTimeout(closeSvcServicesPopup, 140);
    });

    $(window).on('scroll resize', function () {
        if ($activeSvcWrap && $activeSvcWrap.length && $activeSvcPopup && $activeSvcPopup.length) {
            positionSvcServicesPopup($activeSvcWrap);
        }
    });

    var deletedLeadsCache = [];
    var deletedLeadsRestored = false;
    var deletedLeadsPage = 1;
    var deletedLeadsPerPage = 10;
    var deletedLeadsPagination = { page: 1, total_pages: 1, total: 0, from: 0, to: 0 };

    function escDeletedHtml(value) {
        return $('<div>').text(value == null ? '' : String(value)).html();
    }

    function updateDeletedLeadsBulkState() {
        var selected = $('.js-deleted-lead-check:checked').length;
        $('#btnDeletedLeadsBulkDelete').prop('disabled', selected === 0);
        $('#btnDeletedLeadsBulkRestore').prop('disabled', selected === 0);
        var total = $('.js-deleted-lead-check').length;
        $('#deletedLeadsSelectAll').prop('checked', total > 0 && selected === total);
        $('#deletedLeadsSelectAll').prop('indeterminate', selected > 0 && selected < total);
    }

    function updateTrashBadge(count) {
        var $btn = $('#btnOpenDeletedLeadsModal');
        var $badge = $btn.find('.trash-count-badge');
        if (count > 0) {
            if (!$badge.length) {
                $btn.append('<span class="badge badge-danger trash-count-badge">' + count + '</span>');
            } else {
                $badge.text(count);
            }
        } else if ($badge.length) {
            $badge.remove();
        }
    }

    function renderDeletedLeadsPagination(meta) {
        meta = meta || {};
        deletedLeadsPagination = {
            page: Number(meta.page || 1),
            total_pages: Number(meta.total_pages || 1),
            total: Number(meta.total || 0),
            from: Number(meta.from || 0),
            to: Number(meta.to || 0),
            per_page: Number(meta.per_page || deletedLeadsPerPage)
        };

        var $bar = $('#deletedLeadsPagination');
        var $summary = $('#deletedLeadsPageSummary');
        var $list = $('#deletedLeadsPaginationList');

        if (deletedLeadsPagination.total <= 0 || deletedLeadsPagination.total_pages <= 1) {
            $bar.removeClass('is-visible');
            $list.empty();
            return;
        }

        $bar.addClass('is-visible');
        $summary.text(
            'Showing ' + deletedLeadsPagination.from + '–' + deletedLeadsPagination.to
            + ' of ' + deletedLeadsPagination.total + ' deleted leads'
        );

        var page = deletedLeadsPagination.page;
        var totalPages = deletedLeadsPagination.total_pages;
        var startPage = Math.max(1, page - 2);
        var endPage = Math.min(totalPages, page + 2);
        var html = '';

        html += '<li class="page-item' + (page <= 1 ? ' disabled' : '') + '">'
            + '<button type="button" class="page-link js-deleted-leads-page" data-page="' + (page - 1) + '"'
            + (page <= 1 ? ' disabled' : '') + '>Prev</button></li>';

        for (var p = startPage; p <= endPage; p++) {
            html += '<li class="page-item' + (p === page ? ' active' : '') + '">'
                + '<button type="button" class="page-link js-deleted-leads-page" data-page="' + p + '">' + p + '</button></li>';
        }

        html += '<li class="page-item' + (page >= totalPages ? ' disabled' : '') + '">'
            + '<button type="button" class="page-link js-deleted-leads-page" data-page="' + (page + 1) + '"'
            + (page >= totalPages ? ' disabled' : '') + '>Next</button></li>';

        $list.html(html);
    }

    function renderDeletedLeadsTable(rows, meta) {
        deletedLeadsCache = Array.isArray(rows) ? rows : [];
        meta = meta || deletedLeadsPagination;
        var $loading = $('#deletedLeadsLoading');
        var $table = $('#deletedLeadsTable');
        var $empty = $('#deletedLeadsEmpty');
        var $body = $('#deletedLeadsTableBody');

        $loading.addClass('d-none');
        $body.empty();

        if (!deletedLeadsCache.length) {
            $table.addClass('d-none');
            $empty.removeClass('d-none');
            if (Number(meta.total || 0) > 0) {
                $('#deletedLeadsSummary').text('No deleted leads on this page');
            } else {
                $('#deletedLeadsSummary').text('0 deleted leads');
            }
            $('#btnDeletedLeadsBulkDelete').prop('disabled', true);
            $('#btnDeletedLeadsBulkRestore').prop('disabled', true);
            $('#deletedLeadsSelectAll').prop('checked', false).prop('indeterminate', false);
            renderDeletedLeadsPagination(meta);
            return;
        }

        $empty.addClass('d-none');
        $table.removeClass('d-none');
        if (Number(meta.total || 0) > 0) {
            $('#deletedLeadsSummary').text(
                meta.total + ' deleted lead' + (Number(meta.total) === 1 ? '' : 's')
            );
        } else {
            $('#deletedLeadsSummary').text(deletedLeadsCache.length + ' deleted lead' + (deletedLeadsCache.length === 1 ? '' : 's'));
        }

        deletedLeadsCache.forEach(function (lead) {
            var deletedMeta = lead.deleted_at_text || lead.deleted_at || '—';
            if (lead.deleted_by_name) {
                deletedMeta += ' · ' + lead.deleted_by_name;
            }
            $body.append(
                '<tr>'
                + '<td><input type="checkbox" class="js-deleted-lead-check" value="' + escDeletedHtml(lead.id) + '" aria-label="Select lead"></td>'
                + '<td><strong>' + escDeletedHtml(lead.lead_uid) + '</strong></td>'
                + '<td>' + escDeletedHtml(lead.customer_display_name || lead.customer_name) + '</td>'
                + '<td>' + escDeletedHtml(lead.assign_to || '—') + '</td>'
                + '<td class="text-muted">' + escDeletedHtml(deletedMeta) + '</td>'
                + '<td>'
                + '<button type="button" class="btn btn-outline-success btn-xs btn-sm mr-1 js-deleted-lead-restore-one" data-lead-id="' + escDeletedHtml(lead.id) + '">Restore</button>'
                + '<button type="button" class="btn btn-outline-danger btn-xs btn-sm js-deleted-lead-delete-one" data-lead-id="' + escDeletedHtml(lead.id) + '">Delete</button>'
                + '</td>'
                + '</tr>'
            );
        });

        updateDeletedLeadsBulkState();
        renderDeletedLeadsPagination(meta);
    }

    function loadDeletedLeadsModal(page) {
        if (page != null) {
            deletedLeadsPage = Math.max(1, Number(page) || 1);
        }

        $('#deletedLeadsLoading').removeClass('d-none').html('<i class="fas fa-spinner fa-spin mr-1"></i> Loading deleted leads…');
        $('#deletedLeadsTable').addClass('d-none');
        $('#deletedLeadsEmpty').addClass('d-none');
        $('#deletedLeadsTableBody').empty();
        $('#deletedLeadsSummary').text('Loading…');
        $('#deletedLeadsPagination').removeClass('is-visible');
        $('#deletedLeadsPaginationList').empty();
        $('#btnDeletedLeadsBulkDelete').prop('disabled', true);
        $('#btnDeletedLeadsBulkRestore').prop('disabled', true);
        $('#deletedLeadsSelectAll').prop('checked', false).prop('indeterminate', false);

        $.ajax({
            url: 'crm/ajax/list_deleted_leads.php',
            method: 'GET',
            data: { page: deletedLeadsPage, per_page: deletedLeadsPerPage },
            dataType: 'json'
        }).done(function (response) {
            if (!response || !response.success) {
                $('#deletedLeadsLoading').removeClass('d-none').html('<span class="text-danger">Could not load deleted leads.</span>');
                return;
            }

            var meta = response.pagination || {};
            deletedLeadsPage = Number(meta.page || deletedLeadsPage);

            if (!response.data || !response.data.length) {
                if (Number(meta.total || 0) > 0 && deletedLeadsPage > 1) {
                    loadDeletedLeadsModal(deletedLeadsPage - 1);
                    return;
                }
            }

            renderDeletedLeadsTable(response.data || [], meta);
            updateTrashBadge(Number(response.count || meta.total || 0));
        }).fail(function () {
            $('#deletedLeadsLoading').removeClass('d-none').html('<span class="text-danger">Could not load deleted leads.</span>');
        });
    }

    function permanentlyDeleteLeads(leadIds, onDone) {
        if (!leadIds.length) {
            return;
        }
        $.ajax({
            url: 'crm/ajax/bulk_delete_leads.php',
            method: 'POST',
            data: { lead_ids: leadIds },
            dataType: 'json',
            traditional: true
        }).done(function (response) {
            if (response && response.success) {
                updateTrashBadge(Number(response.remaining || 0));
                loadDeletedLeadsModal(deletedLeadsPage);
                if (typeof onDone === 'function') {
                    onDone(true, response);
                }
                return;
            }
            window.alert((response && response.message) ? response.message : 'Could not delete selected leads.');
            if (typeof onDone === 'function') {
                onDone(false, response);
            }
        }).fail(function () {
            window.alert('Could not delete selected leads. Please try again.');
            if (typeof onDone === 'function') {
                onDone(false);
            }
        });
    }

    function restoreDeletedLeads(leadIds, onDone) {
        if (!leadIds.length) {
            return;
        }
        $.ajax({
            url: 'crm/ajax/restore_leads.php',
            method: 'POST',
            data: { lead_ids: leadIds },
            dataType: 'json',
            traditional: true
        }).done(function (response) {
            if (response && response.success) {
                deletedLeadsRestored = true;
                updateTrashBadge(Number(response.remaining || 0));
                loadDeletedLeadsModal(deletedLeadsPage);
                if (typeof onDone === 'function') {
                    onDone(true, response);
                }
                return;
            }
            window.alert((response && response.message) ? response.message : 'Could not restore selected leads.');
            if (typeof onDone === 'function') {
                onDone(false, response);
            }
        }).fail(function () {
            window.alert('Could not restore selected leads. Please try again.');
            if (typeof onDone === 'function') {
                onDone(false);
            }
        });
    }

    $('#btnOpenDeletedLeadsModal').on('click', function () {
        deletedLeadsRestored = false;
        deletedLeadsPage = 1;
        $('#deletedLeadsModal').modal('show');
        loadDeletedLeadsModal(1);
    });

    $(document).on('click', '.js-deleted-leads-page', function (e) {
        e.preventDefault();
        if ($(this).is(':disabled') || $(this).closest('.page-item').hasClass('disabled')) {
            return;
        }
        var page = Number($(this).attr('data-page') || 1);
        if (page === deletedLeadsPage) {
            return;
        }
        loadDeletedLeadsModal(page);
    });

    $('#deletedLeadsModal').on('hidden.bs.modal', function () {
        if (deletedLeadsRestored) {
            window.location.reload();
        }
    });

    $(document).on('change', '.js-deleted-lead-check, #deletedLeadsSelectAll', function () {
        if (this.id === 'deletedLeadsSelectAll') {
            var checked = $(this).is(':checked');
            $('.js-deleted-lead-check').prop('checked', checked);
        }
        updateDeletedLeadsBulkState();
    });

    $('#btnDeletedLeadsBulkDelete').on('click', function () {
        var leadIds = $('.js-deleted-lead-check:checked').map(function () {
            return Number($(this).val());
        }).get().filter(function (id) { return id > 0; });

        if (!leadIds.length) {
            return;
        }
        if (!window.confirm('Permanently delete ' + leadIds.length + ' selected lead' + (leadIds.length === 1 ? '' : 's') + '? This cannot be undone.')) {
            return;
        }
        var $btn = $(this).prop('disabled', true);
        permanentlyDeleteLeads(leadIds, function () {
            $btn.prop('disabled', false);
        });
    });

    $('#btnDeletedLeadsBulkRestore').on('click', function () {
        var leadIds = $('.js-deleted-lead-check:checked').map(function () {
            return Number($(this).val());
        }).get().filter(function (id) { return id > 0; });

        if (!leadIds.length) {
            return;
        }
        if (!window.confirm('Restore ' + leadIds.length + ' selected lead' + (leadIds.length === 1 ? '' : 's') + ' to the leads list?')) {
            return;
        }
        var $btn = $(this).prop('disabled', true);
        restoreDeletedLeads(leadIds, function () {
            $btn.prop('disabled', false);
        });
    });

    $(document).on('click', '.js-deleted-lead-restore-one', function () {
        var leadId = Number($(this).attr('data-lead-id') || 0);
        if (!leadId) {
            return;
        }
        if (!window.confirm('Restore this lead to the leads list?')) {
            return;
        }
        var $btn = $(this).prop('disabled', true);
        restoreDeletedLeads([leadId], function () {
            $btn.prop('disabled', false);
        });
    });

    $(document).on('click', '.js-deleted-lead-delete-one', function () {
        var leadId = Number($(this).attr('data-lead-id') || 0);
        if (!leadId) {
            return;
        }
        if (!window.confirm('Permanently delete this lead? This cannot be undone.')) {
            return;
        }
        var $btn = $(this).prop('disabled', true);
        permanentlyDeleteLeads([leadId], function () {
            $btn.prop('disabled', false);
        });
    });
})();
</script>
<script src="crm/assets/quotation_confirm_tour.js?v=3"></script>
<script src="crm/assets/quotation_supplier_mail.js?v=20"></script>
<script>
$(function () {
    if (window.QSupplierMail) {
        window.QSupplierMail.init({
            suppliers: window.Q_SUPPLIER_MAIL_CATALOG || [],
            destinationNameToId: window.Q_DESTINATION_NAME_TO_ID || {},
            mailTemplate: { subject: '', body_html: '', meta: {} }
        });
    }
});
</script>

</body>

</html>
