<?php

/**
 * Organization dashboard metrics from live CRM tables.
 */

function dashTableExists(mysqli $conn, $table)
{
    $table = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES LIKE '$table'");
    return $res && $res->num_rows > 0;
}

function dashPeriodRange($period)
{
    $period = (string) $period;
    $today = new DateTime('today');

    if ($period === 'last_month') {
        $start = (clone $today)->modify('first day of last month')->setTime(0, 0, 0);
        $end = (clone $today)->modify('last day of last month')->setTime(23, 59, 59);
        return [$start, $end];
    }

    if ($period === 'all_time') {
        return [null, null];
    }

    // Default: this month
    $start = (clone $today)->modify('first day of this month')->setTime(0, 0, 0);
    $end = (clone $today)->modify('last day of this month')->setTime(23, 59, 59);
    return [$start, $end];
}

function dashPeriodLabel($period, DateTime $start = null, DateTime $end = null)
{
    if ($period === 'all_time') {
        return 'All time';
    }
    if ($start && $end) {
        return $start->format('d M Y') . ' - ' . $end->format('d M Y');
    }
    return 'Selected period';
}

function dashDateWhere($column, DateTime $start = null, DateTime $end = null)
{
    if (!$start || !$end) {
        return ['', []];
    }
    return [
        " AND `$column` >= ? AND `$column` <= ? ",
        [$start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')],
    ];
}

function dashServiceLabels()
{
    return [
        'tour_package' => 'Tour Package',
        'flight' => 'Flight',
        'hotel' => 'Hotel',
        'vehicle' => 'Vehicle',
        'sightseeing' => 'Sightseeing',
        'cruise' => 'Cruise',
        'visa' => 'Visa',
        'passport' => 'Passport',
        'forex' => 'Forex',
    ];
}

function dashLoadDestinationLookup(mysqli $conn)
{
    $lookup = [];
    if (!dashTableExists($conn, 'destinations')) {
        return $lookup;
    }
    $res = $conn->query('SELECT id, name FROM destinations WHERE is_active = 1');
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $lookup[(int) $row['id']] = (string) ($row['name'] ?? '');
        }
    }
    return $lookup;
}

function dashLeadDestinationName(array $payload, array $destLookup)
{
    $destIds = $payload['tp_destination'] ?? [];
    if (!is_array($destIds)) {
        $destIds = $destIds !== '' ? [$destIds] : [];
    }
    foreach ($destIds as $destId) {
        $destId = (int) $destId;
        if ($destId > 0 && isset($destLookup[$destId]) && $destLookup[$destId] !== '') {
            return $destLookup[$destId];
        }
    }
    if (!empty($payload['tp_arrival'])) {
        return trim((string) $payload['tp_arrival']);
    }
    return 'Not available';
}

function dashFormatInr($amount)
{
    return 'INR ' . number_format((float) $amount, 0, '.', ',');
}

function dashBuildQuotationIndex(mysqli $conn)
{
    $byPhone = [];
    $byEmail = [];
    $confirmedPhones = [];
    $confirmedEmails = [];

    if (!dashTableExists($conn, 'crm_quotations')) {
        return compact('byPhone', 'byEmail', 'confirmedPhones', 'confirmedEmails');
    }

    require_once __DIR__ . '/../crm/includes/quotation_db.php';
    crmEnsureQuotationTables($conn);

    $res = $conn->query('SELECT mobile_no, email, tour_confirmed FROM crm_quotations');
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $phone = preg_replace('/\D+/', '', (string) ($row['mobile_no'] ?? ''));
            $email = strtolower(trim((string) ($row['email'] ?? '')));
            $confirmed = (int) ($row['tour_confirmed'] ?? 0) === 1;

            if ($phone !== '') {
                $byPhone[$phone] = true;
                if ($confirmed) {
                    $confirmedPhones[$phone] = true;
                }
            }
            if ($email !== '') {
                $byEmail[$email] = true;
                if ($confirmed) {
                    $confirmedEmails[$email] = true;
                }
            }
        }
    }

    return compact('byPhone', 'byEmail', 'confirmedPhones', 'confirmedEmails');
}

function dashLeadHasQuotation(array $lead, array $qIndex)
{
    $phone = preg_replace('/\D+/', '', (string) ($lead['customer_phone'] ?? ''));
    $email = strtolower(trim((string) ($lead['customer_email'] ?? '')));

    if ($phone !== '' && !empty($qIndex['byPhone'][$phone])) {
        return true;
    }
    if ($email !== '' && !empty($qIndex['byEmail'][$email])) {
        return true;
    }
    return false;
}

function dashLeadIsConfirmed(array $lead, array $qIndex)
{
    $phone = preg_replace('/\D+/', '', (string) ($lead['customer_phone'] ?? ''));
    $email = strtolower(trim((string) ($lead['customer_email'] ?? '')));

    if ($phone !== '' && !empty($qIndex['confirmedPhones'][$phone])) {
        return true;
    }
    if ($email !== '' && !empty($qIndex['confirmedEmails'][$email])) {
        return true;
    }
    return false;
}

function dashCollectOrganizationStats(mysqli $conn, $period = 'this_month')
{
    [$start, $end] = dashPeriodRange($period);
    $periodLabel = dashPeriodLabel($period, $start, $end);
    $destLookup = dashLoadDestinationLookup($conn);
    $qIndex = dashBuildQuotationIndex($conn);
    $serviceLabels = dashServiceLabels();

    $stats = [
        'period' => $period,
        'period_label' => $periodLabel,
        'total_queries' => 0,
        'confirmed_count' => 0,
        'confirmed_value' => 0.0,
        'confirmed_travel' => 0,
        'collection_revenue' => 0.0,
        'supplier_payable' => 0.0,
        'pipeline' => [],
        'service_mix' => [],
        'pending_supplier_payments' => [],
        'monthly_trend' => [],
        'ongoing_tours' => [],
        'invoice_status' => ['paid' => 0, 'unpaid' => 0, 'partial' => 0, 'total' => 0],
        'top_destinations' => [],
        'lead_sources' => [],
        'team_performance' => [],
        'pending_intake' => 0,
        'tables' => [
            'leads' => dashTableExists($conn, 'crm_leads'),
            'quotations' => dashTableExists($conn, 'crm_quotations'),
            'payment_links' => dashTableExists($conn, 'payment_links'),
            'intake' => dashTableExists($conn, 'crm_lead_intake_submissions'),
        ],
    ];

    // --- Leads in period ---
    $leadsInPeriod = [];
    if ($stats['tables']['leads']) {
        [$dateSql, $dateParams] = dashDateWhere('created_at', $start, $end);
        $sql = 'SELECT * FROM crm_leads WHERE 1=1' . $dateSql . ' ORDER BY created_at DESC';
        if ($dateParams) {
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param('ss', ...$dateParams);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($row = $res->fetch_assoc()) {
                    $leadsInPeriod[] = $row;
                }
                $stmt->close();
            }
        } else {
            $res = $conn->query($sql);
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $leadsInPeriod[] = $row;
                }
            }
        }
    }

    $stats['total_queries'] = count($leadsInPeriod);

    $pipeline = [
        'New' => 0,
        'Proposal Sent' => 0,
        'Confirmed' => 0,
        'Pending Intake' => 0,
        'Unassigned' => 0,
    ];
    $serviceCounts = [];
    $destCounts = [];
    $destConfirmed = [];
    $sourceCounts = [];
    $sourceConfirmed = [];
    $teamCounts = [];
    $teamConfirmed = [];

    foreach ($leadsInPeriod as $lead) {
        $payload = [];
        if (!empty($lead['payload_json'])) {
            $decoded = json_decode((string) $lead['payload_json'], true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }

        $hasQuote = dashLeadHasQuotation($lead, $qIndex);
        $isConfirmed = dashLeadIsConfirmed($lead, $qIndex);

        if ($isConfirmed) {
            $pipeline['Confirmed']++;
        } elseif ($hasQuote) {
            $pipeline['Proposal Sent']++;
        } else {
            $pipeline['New']++;
        }

        if (trim((string) ($lead['assign_to'] ?? '')) === '') {
            $pipeline['Unassigned']++;
        }

        $services = [];
        if (!empty($lead['services'])) {
            $decodedServices = json_decode((string) $lead['services'], true);
            if (is_array($decodedServices)) {
                $services = $decodedServices;
            }
        }
        if (!$services) {
            $services = ['tour_package'];
        }
        foreach ($services as $svc) {
            $svc = trim((string) $svc);
            if ($svc === '') {
                continue;
            }
            $label = $serviceLabels[$svc] ?? ucwords(str_replace('_', ' ', $svc));
            if (!isset($serviceCounts[$label])) {
                $serviceCounts[$label] = 0;
            }
            $serviceCounts[$label]++;
        }

        $destName = dashLeadDestinationName($payload, $destLookup);
        if (!isset($destCounts[$destName])) {
            $destCounts[$destName] = 0;
            $destConfirmed[$destName] = 0;
        }
        $destCounts[$destName]++;
        if ($isConfirmed) {
            $destConfirmed[$destName]++;
        }

        $source = trim((string) ($lead['lead_source'] ?? ''));
        if ($source === '') {
            $source = 'Not specified';
        }
        if (!isset($sourceCounts[$source])) {
            $sourceCounts[$source] = 0;
            $sourceConfirmed[$source] = 0;
        }
        $sourceCounts[$source]++;
        if ($isConfirmed) {
            $sourceConfirmed[$source]++;
        }

        $member = trim((string) ($lead['assign_to'] ?? ''));
        if ($member === '') {
            $member = 'No Owner';
        }
        if (!isset($teamCounts[$member])) {
            $teamCounts[$member] = 0;
            $teamConfirmed[$member] = 0;
        }
        $teamCounts[$member]++;
        if ($isConfirmed) {
            $teamConfirmed[$member]++;
        }
    }

    if ($stats['tables']['intake']) {
        $res = $conn->query("SELECT COUNT(*) AS c FROM crm_lead_intake_submissions WHERE status = 'pending'");
        if ($res) {
            $stats['pending_intake'] = (int) ($res->fetch_assoc()['c'] ?? 0);
            $pipeline['Pending Intake'] = $stats['pending_intake'];
        }
    }

    arsort($pipeline);
    $stats['pipeline'] = $pipeline;

    arsort($serviceCounts);
    $stats['service_mix'] = $serviceCounts;

    // --- Quotations ---
    if ($stats['tables']['quotations']) {
        require_once __DIR__ . '/../crm/includes/quotation_db.php';
        crmEnsureQuotationTables($conn);

        [$qDateSql, $qDateParams] = dashDateWhere('created_at', $start, $end);

        $sqlConfirmed = 'SELECT COUNT(*) AS c FROM crm_quotations WHERE tour_confirmed = 1' . $qDateSql;
        if ($qDateParams) {
            $stmt = $conn->prepare($sqlConfirmed);
            if ($stmt) {
                $stmt->bind_param('ss', ...$qDateParams);
                $stmt->execute();
                $res = $stmt->get_result();
                $stats['confirmed_count'] = (int) ($res->fetch_assoc()['c'] ?? 0);
                $stmt->close();
            }
        } else {
            $res = $conn->query($sqlConfirmed);
            if ($res) {
                $stats['confirmed_count'] = (int) ($res->fetch_assoc()['c'] ?? 0);
            }
        }

        $sqlValue = 'SELECT COALESCE(SUM(quotation_total), 0) AS total FROM crm_quotations WHERE tour_confirmed = 1' . $qDateSql;
        if ($qDateParams) {
            $stmt = $conn->prepare($sqlValue);
            if ($stmt) {
                $stmt->bind_param('ss', ...$qDateParams);
                $stmt->execute();
                $res = $stmt->get_result();
                $stats['confirmed_value'] = (float) ($res->fetch_assoc()['total'] ?? 0);
                $stmt->close();
            }
        } else {
            $res = $conn->query($sqlValue);
            if ($res) {
                $stats['confirmed_value'] = (float) ($res->fetch_assoc()['total'] ?? 0);
            }
        }

        $today = date('Y-m-d');
        $sqlTravel = "SELECT COUNT(*) AS c FROM crm_quotations WHERE tour_confirmed = 1 AND tentative_date IS NOT NULL AND tentative_date >= '$today'" . $qDateSql;
        if ($qDateParams) {
            $stmt = $conn->prepare(str_replace("tentative_date >= '$today'", 'tentative_date >= ?', $sqlTravel));
            // Simpler direct query
            $stmt = $conn->prepare(
                'SELECT COUNT(*) AS c FROM crm_quotations WHERE tour_confirmed = 1 AND tentative_date IS NOT NULL AND tentative_date >= ?' . $qDateSql
            );
            if ($stmt) {
                $params = array_merge([$today], $qDateParams);
                $stmt->bind_param('sss', ...$params);
                $stmt->execute();
                $res = $stmt->get_result();
                $stats['confirmed_travel'] = (int) ($res->fetch_assoc()['c'] ?? 0);
                $stmt->close();
            }
        } else {
            $res = $conn->query(
                "SELECT COUNT(*) AS c FROM crm_quotations WHERE tour_confirmed = 1 AND tentative_date IS NOT NULL AND tentative_date >= '$today'"
            );
            if ($res) {
                $stats['confirmed_travel'] = (int) ($res->fetch_assoc()['c'] ?? 0);
            }
        }

        // Supplier payables from tour_confirm_json
        $res = $conn->query(
            "SELECT guest_name, tentative_date, tour_confirm_json FROM crm_quotations
             WHERE tour_confirmed = 1 AND tour_confirm_json IS NOT NULL AND tour_confirm_json != ''"
        );
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $payload = json_decode((string) ($row['tour_confirm_json'] ?? ''), true);
                if (!is_array($payload) || empty($payload['services'])) {
                    continue;
                }
                foreach ($payload['services'] as $svc) {
                    if (!is_array($svc)) {
                        continue;
                    }
                    $total = (float) ($svc['total'] ?? 0);
                    $paid = (float) ($svc['paid'] ?? 0);
                    $balance = max(0, round($total - $paid, 2));
                    if ($balance <= 0) {
                        continue;
                    }
                    $stats['supplier_payable'] += $balance;
                    $dueDate = !empty($row['tentative_date']) ? date('d M Y', strtotime((string) $row['tentative_date'])) : '—';
                    $stats['pending_supplier_payments'][] = [
                        'supplier' => trim((string) ($svc['supplier'] ?? '')) !== '' ? trim((string) $svc['supplier']) : (string) ($row['guest_name'] ?? 'Supplier'),
                        'due' => $dueDate,
                        'amount' => $balance,
                    ];
                }
            }
        }

        usort($stats['pending_supplier_payments'], static function ($a, $b) {
            return $b['amount'] <=> $a['amount'];
        });
        $stats['pending_supplier_payments'] = array_slice($stats['pending_supplier_payments'], 0, 8);

        // Ongoing & upcoming tours
        $res = $conn->query(
            "SELECT guest_name, destination, tentative_date, no_of_nights, tour_confirmed
             FROM crm_quotations
             WHERE tour_confirmed = 1 AND tentative_date IS NOT NULL
             ORDER BY tentative_date ASC
             LIMIT 12"
        );
        if ($res) {
            $todayTs = strtotime(date('Y-m-d'));
            while ($row = $res->fetch_assoc()) {
                $startTs = strtotime((string) $row['tentative_date']);
                if ($startTs === false) {
                    continue;
                }
                $nights = max(0, (int) ($row['no_of_nights'] ?? 0));
                $endTs = strtotime('+' . $nights . ' days', $startTs);
                $status = 'Upcoming';
                if ($startTs <= $todayTs && ($endTs === false || $endTs >= $todayTs)) {
                    $status = 'On Going';
                } elseif ($endTs !== false && $endTs < $todayTs) {
                    continue;
                }
                $stats['ongoing_tours'][] = [
                    'title' => trim((string) ($row['guest_name'] ?? 'Guest')) . ' - ' . trim((string) ($row['destination'] ?? 'Tour')),
                    'destination' => trim((string) ($row['destination'] ?? '—')),
                    'dates' => date('d M Y', $startTs) . ' - ' . date('d M Y', $endTs ?: $startTs),
                    'status' => $status,
                ];
            }
            $stats['ongoing_tours'] = array_slice($stats['ongoing_tours'], 0, 6);
        }
    }

    // --- Payment links (collection + invoice status) ---
    if ($stats['tables']['payment_links']) {
        if (function_exists('payment_ensure_links_table')) {
            payment_ensure_links_table($conn);
        }

        [$pDateSql, $pDateParams] = dashDateWhere('created_at', $start, $end);
        $sqlPaid = "SELECT COALESCE(SUM(amount_paisa), 0) AS total FROM payment_links WHERE status = 'paid'" . $pDateSql;
        if ($pDateParams) {
            $stmt = $conn->prepare($sqlPaid);
            if ($stmt) {
                $stmt->bind_param('ss', ...$pDateParams);
                $stmt->execute();
                $res = $stmt->get_result();
                $stats['collection_revenue'] = ((float) ($res->fetch_assoc()['total'] ?? 0)) / 100;
                $stmt->close();
            }
        } else {
            $res = $conn->query($sqlPaid);
            if ($res) {
                $stats['collection_revenue'] = ((float) ($res->fetch_assoc()['total'] ?? 0)) / 100;
            }
        }

        $invoiceSql = 'SELECT status, COUNT(*) AS c FROM payment_links WHERE 1=1' . $pDateSql . ' GROUP BY status';
        if ($pDateParams) {
            $stmt = $conn->prepare($invoiceSql);
            if ($stmt) {
                $stmt->bind_param('ss', ...$pDateParams);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($row = $res->fetch_assoc()) {
                    $status = (string) ($row['status'] ?? '');
                    $count = (int) ($row['c'] ?? 0);
                    $stats['invoice_status']['total'] += $count;
                    if ($status === 'paid') {
                        $stats['invoice_status']['paid'] = $count;
                    } elseif ($status === 'active') {
                        $stats['invoice_status']['unpaid'] = $count;
                    } else {
                        $stats['invoice_status']['partial'] += $count;
                    }
                }
                $stmt->close();
            }
        } else {
            $res = $conn->query($invoiceSql);
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $status = (string) ($row['status'] ?? '');
                    $count = (int) ($row['c'] ?? 0);
                    $stats['invoice_status']['total'] += $count;
                    if ($status === 'paid') {
                        $stats['invoice_status']['paid'] = $count;
                    } elseif ($status === 'active') {
                        $stats['invoice_status']['unpaid'] = $count;
                    } else {
                        $stats['invoice_status']['partial'] += $count;
                    }
                }
            }
        }
    }

    // --- Monthly trend (last 12 months) ---
    $months = [];
    for ($i = 11; $i >= 0; $i--) {
        $dt = new DateTime('first day of this month');
        $dt->modify("-$i months");
        $key = $dt->format('Y-m');
        $months[$key] = [
            'label' => $dt->format('M'),
            'total' => 0,
            'won' => 0,
        ];
    }

    if ($stats['tables']['leads']) {
        $res = $conn->query(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, COUNT(*) AS c
             FROM crm_leads
             WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH)
             GROUP BY ym"
        );
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $ym = (string) ($row['ym'] ?? '');
                if (isset($months[$ym])) {
                    $months[$ym]['total'] = (int) ($row['c'] ?? 0);
                }
            }
        }
    }

    if ($stats['tables']['quotations']) {
        $res = $conn->query(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, COUNT(*) AS c
             FROM crm_quotations
             WHERE tour_confirmed = 1 AND created_at >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH)
             GROUP BY ym"
        );
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $ym = (string) ($row['ym'] ?? '');
                if (isset($months[$ym])) {
                    $months[$ym]['won'] = (int) ($row['c'] ?? 0);
                }
            }
        }
    }
    $stats['monthly_trend'] = array_values($months);

    // --- Ranked tables ---
    arsort($destCounts);
    foreach (array_slice($destCounts, 0, 8, true) as $name => $total) {
        $stats['top_destinations'][] = [
            'name' => $name,
            'total' => $total,
            'confirmed' => (int) ($destConfirmed[$name] ?? 0),
        ];
    }

    arsort($sourceCounts);
    foreach (array_slice($sourceCounts, 0, 8, true) as $name => $total) {
        $stats['lead_sources'][] = [
            'name' => $name,
            'total' => $total,
            'confirmed' => (int) ($sourceConfirmed[$name] ?? 0),
        ];
    }

    arsort($teamCounts);
    foreach (array_slice($teamCounts, 0, 8, true) as $name => $total) {
        $stats['team_performance'][] = [
            'name' => $name,
            'total' => $total,
            'confirmed' => (int) ($teamConfirmed[$name] ?? 0),
        ];
    }

    return $stats;
}
