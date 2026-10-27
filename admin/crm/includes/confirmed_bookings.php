<?php
/**
 * All Bookings reads confirmed tours from crm_quotations (tour_confirmed = 1).
 * Customer money is crm_customer_payments. Supplier money is the Confirm Tour
 * service rows. This file does not create a second booking, customer, or payment.
 */

require_once __DIR__ . '/quotation_db.php';
require_once __DIR__ . '/customer_payments.php';
require_once __DIR__ . '/service_payments.php';

function cbEnsureTable(mysqli $conn): void
{
    $conn->query("CREATE TABLE IF NOT EXISTS `crm_booking_meta` (
        `quotation_id` INT UNSIGNED NOT NULL,
        `status` VARCHAR(20) NOT NULL DEFAULT 'active',
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`quotation_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function cbIso($raw): string
{
    $raw = trim((string) $raw);
    if ($raw === '' || strpos($raw, '0000-00-00') === 0) {
        return '';
    }
    $d = DateTime::createFromFormat('!Y-m-d', substr($raw, 0, 10));
    return $d ? $d->format('Y-m-d') : '';
}

function cbAddDays(string $iso, int $days): string
{
    if ($iso === '') {
        return '';
    }
    $d = new DateTime($iso);
    if ($days !== 0) {
        $d->modify(($days > 0 ? '+' : '') . $days . ' days');
    }
    return $d->format('Y-m-d');
}

function cbMoney($value): float
{
    return round(max(0, crmQuotationConfirmMoney($value)), 2);
}

function cbPayStatus(float $total, float $paid, string $start, string $today, bool $cancelled): string
{
    $balance = round($total - $paid, 2);
    if ($cancelled) {
        return $balance > 0.009 ? 'pending' : 'paid';
    }
    if ($total > 0.009 && $paid + 0.009 >= $total) {
        return 'paid';
    }
    if ($balance > 0.009 && $start !== '' && $start < $today) {
        return 'overdue';
    }
    if ($paid > 0.009) {
        return 'partial';
    }
    return 'pending';
}

function cbPhase(string $start, string $end, bool $cancelled, string $today): string
{
    if ($cancelled) {
        return 'cancelled';
    }
    if ($start === '') {
        return 'confirmed';
    }
    if ($start > $today) {
        return 'upcoming';
    }
    if ($end !== '' && $end < $today) {
        return 'completed';
    }
    return 'ongoing';
}

function cbTourType(array $services, string $leadServices): string
{
    $keys = [];
    foreach ($services as $svc) {
        $key = (string) ($svc['key'] ?? '');
        if ($key !== '') {
            $keys[$key] = true;
        }
    }
    if (isset($keys['hotels']) && isset($keys['flight'])) {
        return 'Package';
    }
    if (isset($keys['cruise']) && count($keys) === 1) {
        return 'Cruise';
    }
    if (isset($keys['flight']) && count($keys) === 1) {
        return 'Flight';
    }
    if (isset($keys['hotels']) && count($keys) === 1) {
        return 'Hotel';
    }
    if (isset($keys['land_package']) && count($keys) === 1) {
        return 'Land Package';
    }
    $text = trim($leadServices);
    if ($text !== '' && $text[0] !== '{' && $text[0] !== '[') {
        $first = trim(explode(',', $text)[0]);
        if ($first !== '') {
            return $first;
        }
    }
    return count($keys) > 1 ? 'Package' : 'Tour';
}

/** @return array<int, string> */
function cbStatusMap(mysqli $conn): array
{
    cbEnsureTable($conn);
    $map = [];
    $res = $conn->query('SELECT `quotation_id`, `status` FROM `crm_booking_meta`');
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $map[(int) $row['quotation_id']] = (string) $row['status'];
        }
    }
    return $map;
}

function cbSetStatus(mysqli $conn, int $quotationId, string $status): bool
{
    cbEnsureTable($conn);
    crmEnsureQuotationTables($conn);
    $status = $status === 'cancelled' ? 'cancelled' : 'active';
    $check = $conn->prepare('SELECT `id` FROM `crm_quotations` WHERE `id` = ? AND `tour_confirmed` = 1 LIMIT 1');
    if (!$check) {
        return false;
    }
    $check->bind_param('i', $quotationId);
    $check->execute();
    $found = $check->get_result();
    $ok = $found && $found->fetch_assoc();
    $check->close();
    if (!$ok) {
        return false;
    }
    $stmt = $conn->prepare(
        'INSERT INTO `crm_booking_meta` (`quotation_id`, `status`) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE `status` = VALUES(`status`)'
    );
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('is', $quotationId, $status);
    $saved = $stmt->execute();
    $stmt->close();
    return $saved;
}

/** @return array<int, array<string, mixed>> */
function cbDestinationNames(mysqli $conn): array
{
    $map = [];
    $check = $conn->query("SHOW TABLES LIKE 'destinations'");
    if (!$check || $check->num_rows === 0) {
        return $map;
    }
    $res = $conn->query('SELECT `id`, `name` FROM `destinations`');
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $map[(int) $row['id']] = (string) ($row['name'] ?? '');
        }
    }
    return $map;
}

function cbDestinationLabel(string $raw, array $names): string
{
    $raw = trim($raw);
    if ($raw !== '' && ctype_digit($raw) && isset($names[(int) $raw])) {
        return $names[(int) $raw];
    }
    return $raw;
}

/**
 * @return list<array<string, mixed>>
 */
function cbList(mysqli $conn): array
{
    crmEnsureQuotationTables($conn);
    cpEnsureTable($conn);
    spEnsureTable($conn);
    $statuses = cbStatusMap($conn);
    $destinations = cbDestinationNames($conn);
    $today = date('Y-m-d');

    $customerPaid = [];
    $paidRes = $conn->query('SELECT `quotation_id`, COALESCE(SUM(`amount`), 0) AS `paid` FROM `crm_customer_payments` GROUP BY `quotation_id`');
    if ($paidRes) {
        while ($row = $paidRes->fetch_assoc()) {
            $customerPaid[(int) $row['quotation_id']] = round((float) $row['paid'], 2);
        }
    }

    $sql = 'SELECT q.`id`, q.`quotation_uid`, q.`lead_id`, q.`guest_name`, q.`mobile_no`, q.`email`,
                   q.`destination`, q.`tentative_date`, q.`no_of_nights`, q.`no_of_adults`, q.`no_of_children`,
                   q.`no_of_infants`, q.`package_total`, q.`quotation_total`, q.`cost_sheet_json`,
                   q.`hotels_json`, q.`flights_json`, q.`tour_confirm_json`, q.`created_at`,
                   l.`lead_uid`, l.`customer_name`, l.`services`
            FROM `crm_quotations` q
            LEFT JOIN `crm_leads` l ON l.`id` = q.`lead_id`
            WHERE q.`tour_confirmed` = 1
            ORDER BY q.`tentative_date` IS NULL, q.`tentative_date` ASC, q.`id` DESC';
    $res = $conn->query($sql);
    if (!$res) {
        return [];
    }

    $bookings = [];
    while ($quote = $res->fetch_assoc()) {
        $qid = (int) $quote['id'];
        $stored = json_decode((string) ($quote['tour_confirm_json'] ?? ''), true);
        $storedServices = (is_array($stored) && isset($stored['services']) && is_array($stored['services'])) ? $stored['services'] : [];
        $merged = crmQuotationMergeConfirmServices(
            crmQuotationBuildConfirmServicesFromQuote($quote),
            $storedServices
        );

        $services = [];
        $supplierNames = [];
        $supplierTotal = 0.0;
        $supplierPaid = 0.0;
        $hotelCount = 0;
        $flightCount = 0;
        $minIn = '';
        $maxOut = '';
        $hotels = crmQuotationActiveHotelsFromJson($quote['hotels_json'] ?? '[]');
        foreach ($hotels as $hotel) {
            if (!is_array($hotel)) {
                continue;
            }
            $in = cbIso($hotel['checkin'] ?? ($hotel['check_in'] ?? ''));
            $out = cbIso($hotel['checkout'] ?? ($hotel['check_out'] ?? ''));
            if ($in !== '' && ($minIn === '' || $in < $minIn)) {
                $minIn = $in;
            }
            if ($out !== '' && ($maxOut === '' || $out > $maxOut)) {
                $maxOut = $out;
            }
        }
        foreach ($merged as $svc) {
            if (!is_array($svc)) {
                continue;
            }
            $total = cbMoney($svc['total'] ?? 0);
            $paid = cbMoney($svc['paid'] ?? 0);
            $supplier = trim((string) ($svc['supplier'] ?? ''));
            $key = (string) ($svc['key'] ?? '');
            if ($key === 'hotels') {
                $hotelCount++;
            }
            if ($key === 'flight') {
                $flightCount++;
            }
            $supplierTotal += $total;
            $supplierPaid += $paid;
            if ($supplier !== '' && !in_array($supplier, $supplierNames, true)) {
                $supplierNames[] = $supplier;
            }
            $services[] = [
                'uid' => (string) ($svc['uid'] ?? ''),
                'key' => $key,
                'label' => (string) ($svc['label'] ?? $key),
                'supplier' => $supplier,
                'total' => $total,
                'paid' => $paid,
                'balance' => max(0, round($total - $paid, 2)),
            ];
        }

        $start = cbIso($quote['tentative_date'] ?? '');
        if ($start === '') {
            $start = $minIn;
        }
        $nights = max(0, (int) ($quote['no_of_nights'] ?? 0));
        $end = $start !== '' ? cbAddDays($start, $nights) : '';
        if ($maxOut !== '' && ($end === '' || $maxOut > $end)) {
            $end = $maxOut;
        }
        if ($end !== '' && $start !== '' && $end < $start) {
            $end = $start;
        }

        $cancelled = (($statuses[$qid] ?? '') === 'cancelled');
        $grand = round(crmQuotationGrandTotal($quote), 2);
        $custPaid = round((float) ($customerPaid[$qid] ?? 0), 2);
        $custBalance = max(0, round($grand - $custPaid, 2));
        $supBalance = max(0, round($supplierTotal - $supplierPaid, 2));
        $adults = max(0, (int) ($quote['no_of_adults'] ?? 0));
        $children = max(0, (int) ($quote['no_of_children'] ?? 0));
        $infants = max(0, (int) ($quote['no_of_infants'] ?? 0));
        $code = trim((string) ($quote['quotation_uid'] ?? ''));
        if ($code === '') {
            $code = 'QT-' . $qid;
        }
        $guest = trim((string) ($quote['guest_name'] ?? ''));
        if ($guest === '') {
            $guest = trim((string) ($quote['customer_name'] ?? ''));
        }
        $phase = cbPhase($start, $end, $cancelled, $today);
        $statusLabel = [
            'cancelled' => 'Cancelled',
            'ongoing' => 'In Progress',
            'completed' => 'Completed',
            'upcoming' => 'Confirmed',
            'confirmed' => 'Confirmed',
        ][$phase] ?? 'Confirmed';

        $bookings[] = [
            'id' => $qid,
            'code' => $code,
            'lead_id' => (int) ($quote['lead_id'] ?? 0),
            'lead_uid' => trim((string) ($quote['lead_uid'] ?? '')),
            'guest' => $guest,
            'phone' => trim((string) ($quote['mobile_no'] ?? '')),
            'email' => trim((string) ($quote['email'] ?? '')),
            'destination' => cbDestinationLabel((string) ($quote['destination'] ?? ''), $destinations),
            'start' => $start,
            'end' => $end,
            'nights' => $nights,
            'adults' => $adults,
            'children' => $children,
            'infants' => $infants,
            'pax' => $adults + $children + $infants,
            'tour_type' => cbTourType($services, (string) ($quote['services'] ?? '')),
            'total' => $grand,
            'customer_paid' => $custPaid,
            'customer_balance' => $custBalance,
            'customer_pay' => cbPayStatus($grand, $custPaid, $start, $today, $cancelled),
            'supplier_total' => round($supplierTotal, 2),
            'supplier_paid' => round($supplierPaid, 2),
            'supplier_balance' => round($supBalance, 2),
            'supplier_pay' => cbPayStatus($supplierTotal, $supplierPaid, $start, $today, $cancelled),
            'suppliers' => $supplierNames,
            'phase' => $phase,
            'status' => $statusLabel,
            'created_at' => (string) ($quote['created_at'] ?? ''),
            'hotel_count' => $hotelCount,
            'flight_count' => $flightCount,
            'services' => $services,
        ];
    }
    return $bookings;
}

function cbFind(mysqli $conn, int $quotationId): ?array
{
    foreach (cbList($conn) as $row) {
        if ((int) $row['id'] === $quotationId) {
            return $row;
        }
    }
    return null;
}

/** @param list<array<string, mixed>> $bookings */
function cbIndex(array $bookings): array
{
    $byId = [];
    foreach ($bookings as $row) {
        $byId[(int) $row['id']] = $row;
    }
    return $byId;
}

/**
 * @param list<array<string, mixed>> $bookings
 * @return array{customer: list<array<string, mixed>>, supplier: list<array<string, mixed>>, activity: list<array<string, mixed>>}
 */
function cbLedgers(mysqli $conn, array $bookings): array
{
    $byId = cbIndex($bookings);
    $customer = [];
    $supplier = [];
    $activity = [];
    if (!$byId) {
        return ['customer' => [], 'supplier' => [], 'activity' => []];
    }

    $res = $conn->query('SELECT * FROM `crm_customer_payments` ORDER BY `payment_date` DESC, `id` DESC');
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $qid = (int) $row['quotation_id'];
            if (!isset($byId[$qid])) {
                continue;
            }
            $booking = $byId[$qid];
            $item = [
                'id' => (int) $row['id'],
                'quotation_id' => $qid,
                'code' => $booking['code'],
                'guest' => $booking['guest'],
                'amount' => round((float) $row['amount'], 2),
                'date' => cbIso($row['payment_date'] ?? ''),
                'method' => (string) ($row['method'] ?? ''),
                'reference' => (string) ($row['reference'] ?? ''),
                'at' => (string) ($row['created_at'] ?? $row['payment_date']),
            ];
            $customer[] = $item;
            $activity[] = [
                'at' => $item['at'],
                'kind' => 'customer_payment',
                'title' => 'Payment received from ' . ($booking['guest'] !== '' ? $booking['guest'] : 'customer'),
                'detail' => $booking['code'],
                'quotation_id' => $qid,
            ];
        }
    }

    $serviceLabel = [];
    foreach ($bookings as $booking) {
        foreach ($booking['services'] as $svc) {
            $serviceLabel[$booking['id'] . '|' . $svc['uid']] = $svc;
        }
    }
    $sres = $conn->query('SELECT * FROM `crm_service_payments` ORDER BY `payment_date` DESC, `id` DESC');
    if ($sres) {
        while ($row = $sres->fetch_assoc()) {
            $qid = (int) $row['quotation_id'];
            if (!isset($byId[$qid])) {
                continue;
            }
            $booking = $byId[$qid];
            $svc = $serviceLabel[$qid . '|' . (string) ($row['service_uid'] ?? '')] ?? null;
            $supplierName = $svc['supplier'] ?? '';
            $label = $svc['label'] ?? (string) ($row['service_key'] ?? 'Supplier');
            $item = [
                'id' => (int) $row['id'],
                'quotation_id' => $qid,
                'code' => $booking['code'],
                'guest' => $booking['guest'],
                'supplier' => $supplierName,
                'label' => $label,
                'service_uid' => (string) ($row['service_uid'] ?? ''),
                'amount' => round((float) $row['amount'], 2),
                'date' => cbIso($row['payment_date'] ?? ''),
                'method' => (string) ($row['method'] ?? ''),
                'reference' => (string) ($row['reference'] ?? ''),
                'at' => (string) ($row['created_at'] ?? $row['payment_date']),
            ];
            $supplier[] = $item;
            $who = $supplierName !== '' ? $supplierName : $label;
            $activity[] = [
                'at' => $item['at'],
                'kind' => 'supplier_payment',
                'title' => 'Supplier payment made — ' . $who,
                'detail' => $booking['code'],
                'quotation_id' => $qid,
            ];
        }
    }

    $hotelCheck = $conn->query("SHOW TABLES LIKE 'crm_hotel_booking_meta'");
    if ($hotelCheck && $hotelCheck->num_rows > 0) {
        $hres = $conn->query('SELECT `quotation_id`, `booking_code`, `reconfirmed_at` FROM `crm_hotel_booking_meta` WHERE `reconfirmed_at` IS NOT NULL ORDER BY `reconfirmed_at` DESC LIMIT 20');
        if ($hres) {
            while ($row = $hres->fetch_assoc()) {
                $qid = (int) $row['quotation_id'];
                if (!isset($byId[$qid])) {
                    continue;
                }
                $activity[] = [
                    'at' => (string) $row['reconfirmed_at'],
                    'kind' => 'reconfirm',
                    'title' => 'Hotel reconfirmed — ' . (string) ($row['booking_code'] ?? ''),
                    'detail' => $byId[$qid]['code'],
                    'quotation_id' => $qid,
                ];
            }
        }
    }

    $workCheck = $conn->query("SHOW TABLES LIKE 'crm_work_items'");
    if ($workCheck && $workCheck->num_rows > 0) {
        $wres = $conn->query('SELECT `kind`, `client_name`, `title`, `scheduled_at`, `created_at`, `lead_id` FROM `crm_work_items` ORDER BY `created_at` DESC LIMIT 20');
        if ($wres) {
            $leadIds = [];
            foreach ($bookings as $booking) {
                if ($booking['lead_id'] > 0) {
                    $leadIds[$booking['lead_id']] = $booking['id'];
                }
            }
            while ($row = $wres->fetch_assoc()) {
                $leadId = (int) ($row['lead_id'] ?? 0);
                if ($leadId <= 0 || !isset($leadIds[$leadId])) {
                    continue;
                }
                $kind = (string) ($row['kind'] ?? 'followup');
                $activity[] = [
                    'at' => (string) ($row['created_at'] ?? $row['scheduled_at']),
                    'kind' => $kind === 'task' ? 'task' : 'followup',
                    'title' => trim((string) ($row['title'] ?? '')) !== '' ? (string) $row['title'] : 'Follow-up scheduled',
                    'detail' => (string) ($row['client_name'] ?? ''),
                    'quotation_id' => $leadIds[$leadId] ?? 0,
                ];
            }
        }
    }

    usort($activity, static function ($a, $b) {
        return strcmp((string) $b['at'], (string) $a['at']);
    });
    $activity = array_slice($activity, 0, 12);

    return ['customer' => $customer, 'supplier' => $supplier, 'activity' => $activity];
}
