<?php
/**
 * Hotel bookings are the hotel rows on confirmed tours.
 * Money, dates, and supplier names stay on the quotation. This table only stores
 * the operational status, reconfirmation, and booking reference for each hotel.
 */

require_once __DIR__ . '/quotation_db.php';
require_once __DIR__ . '/service_payments.php';
require_once __DIR__ . '/service_vouchers.php';

function hbEnsureTable(mysqli $conn): void
{
    $conn->query("CREATE TABLE IF NOT EXISTS `crm_hotel_booking_meta` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `quotation_id` INT UNSIGNED NOT NULL,
        `service_uid` VARCHAR(60) NOT NULL,
        `booking_code` VARCHAR(20) NOT NULL,
        `booking_status` VARCHAR(20) NOT NULL DEFAULT 'pending',
        `reconfirm_status` VARCHAR(20) NOT NULL DEFAULT 'pending',
        `reconfirm_due` DATE DEFAULT NULL,
        `reconfirmed_at` DATETIME DEFAULT NULL,
        `booking_ref` VARCHAR(80) NOT NULL DEFAULT '',
        `cancellation_policy` VARCHAR(500) NOT NULL DEFAULT '',
        `reminder_at` DATETIME DEFAULT NULL,
        `notes` VARCHAR(500) NOT NULL DEFAULT '',
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_hb_service` (`quotation_id`, `service_uid`),
        UNIQUE KEY `uniq_hb_code` (`booking_code`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function hbDate(string $raw): ?DateTime
{
    $raw = trim($raw);
    if ($raw === '' || $raw === '0000-00-00') {
        return null;
    }
    foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'd.m.Y'] as $fmt) {
        $d = DateTime::createFromFormat('!' . $fmt, $raw);
        if ($d instanceof DateTime && $d->format($fmt) === $raw) {
            return $d;
        }
    }
    $ts = strtotime($raw);
    if ($ts === false) {
        return null;
    }
    $d = new DateTime();
    $d->setTimestamp($ts);
    $d->setTime(0, 0, 0);
    return $d;
}

function hbMoney($value): float
{
    return round(max(0, crmQuotationConfirmMoney($value)), 2);
}

function hbClean(string $value, int $max): string
{
    $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
}

function hbNextCode(mysqli $conn): string
{
    $n = 0;
    $res = $conn->query("SELECT MAX(CAST(SUBSTRING(`booking_code`, 5) AS UNSIGNED)) AS n FROM `crm_hotel_booking_meta` WHERE `booking_code` LIKE 'HBK-%'");
    if ($res && ($row = $res->fetch_assoc())) {
        $n = (int) ($row['n'] ?? 0);
    }
    return 'HBK-' . str_pad((string) ($n + 1), 4, '0', STR_PAD_LEFT);
}

/** @return array<string, array<string, mixed>> */
function hbMetaMap(mysqli $conn): array
{
    $map = [];
    $res = $conn->query('SELECT * FROM `crm_hotel_booking_meta`');
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $map[(int) $row['quotation_id'] . '|' . (string) $row['service_uid']] = $row;
        }
    }
    return $map;
}

function hbTouchMeta(mysqli $conn, int $quotationId, string $uid, array &$map): array
{
    $key = $quotationId . '|' . $uid;
    if (isset($map[$key])) {
        return $map[$key];
    }
    $code = hbNextCode($conn);
    $stmt = $conn->prepare(
        'INSERT INTO `crm_hotel_booking_meta` (`quotation_id`, `service_uid`, `booking_code`) VALUES (?, ?, ?)'
    );
    if ($stmt) {
        $stmt->bind_param('iss', $quotationId, $uid, $code);
        $stmt->execute();
        $stmt->close();
    }
    $load = $conn->prepare('SELECT * FROM `crm_hotel_booking_meta` WHERE `quotation_id` = ? AND `service_uid` = ? LIMIT 1');
    $row = [];
    if ($load) {
        $load->bind_param('is', $quotationId, $uid);
        $load->execute();
        $found = $load->get_result()->fetch_assoc();
        $load->close();
        if ($found) {
            $row = $found;
        }
    }
    $map[$key] = $row;
    return $row;
}

/** @return array<string, array{email: string, phone: string}> */
function hbSupplierContacts(mysqli $conn): array
{
    $out = [];
    $table = $conn->query("SHOW TABLES LIKE 'crm_suppliers'");
    if (!$table || $table->num_rows === 0) {
        return $out;
    }
    $res = $conn->query('SELECT `name`, `contacts_json` FROM `crm_suppliers`');
    if (!$res) {
        return $out;
    }
    while ($row = $res->fetch_assoc()) {
        $name = strtolower(trim((string) ($row['name'] ?? '')));
        if ($name === '') {
            continue;
        }
        $contacts = json_decode((string) ($row['contacts_json'] ?? ''), true);
        $email = '';
        $phone = '';
        if (is_array($contacts)) {
            foreach ($contacts as $c) {
                if (!is_array($c)) {
                    continue;
                }
                if ($email === '' && trim((string) ($c['email'] ?? '')) !== '') {
                    $email = trim((string) $c['email']);
                }
                if ($phone === '' && trim((string) ($c['mobile'] ?? '')) !== '') {
                    $phone = trim((string) $c['mobile']);
                }
                if (!empty($c['is_primary']) && trim((string) ($c['email'] ?? '')) !== '') {
                    $email = trim((string) $c['email']);
                }
            }
        }
        $out[$name] = ['email' => $email, 'phone' => $phone];
    }
    return $out;
}

/** @return array<string, list<array<string, mixed>>> */
function hbGrouped(mysqli $conn, string $sql): array
{
    $out = [];
    $res = $conn->query($sql);
    if (!$res) {
        return $out;
    }
    while ($row = $res->fetch_assoc()) {
        $key = (int) ($row['quotation_id'] ?? 0) . '|' . (string) ($row['service_uid'] ?? '');
        $out[$key][] = $row;
    }
    return $out;
}

function hbPaymentStatus(float $total, float $paid): string
{
    if ($total > 0 && $paid + 0.009 >= $total) {
        return 'paid';
    }
    if ($paid > 0) {
        return 'partial';
    }
    return 'pending';
}

function hbReconfirmState(array $meta, ?DateTime $checkin, string $bookingStatus): array
{
    $status = (string) ($meta['reconfirm_status'] ?? 'pending');
    if (!in_array($status, ['pending', 'confirmed'], true)) {
        $status = 'pending';
    }
    $due = null;
    if (!empty($meta['reconfirm_due']) && $meta['reconfirm_due'] !== '0000-00-00') {
        $due = hbDate((string) $meta['reconfirm_due']);
    } elseif ($checkin instanceof DateTime) {
        $due = clone $checkin;
        $due->modify('-7 days');
    }
    $label = 'Pending';
    $key = 'pending';
    if ($bookingStatus === 'cancelled') {
        return ['key' => 'pending', 'label' => '—', 'due' => $due ? $due->format('Y-m-d') : '', 'days' => null];
    }
    if ($status === 'confirmed') {
        return ['key' => 'confirmed', 'label' => 'Confirmed', 'due' => $due ? $due->format('Y-m-d') : '', 'days' => null];
    }
    if ($due instanceof DateTime) {
        $today = new DateTime('today');
        $days = (int) $today->diff($due)->format('%r%a');
        if ($days < 0) {
            $key = 'due';
            $label = 'Overdue';
        } elseif ($days === 0) {
            $key = 'due';
            $label = 'Due today';
        } elseif ($days <= 14) {
            $key = 'due';
            $label = 'Due in ' . $days . ($days === 1 ? ' day' : ' days');
        }
        return ['key' => $key, 'label' => $label, 'due' => $due->format('Y-m-d'), 'days' => $days];
    }
    return ['key' => 'pending', 'label' => 'Pending', 'due' => '', 'days' => null];
}

/**
 * Make sure this hotel service exists in the saved tour JSON so a supplier
 * payment can update that row. Does not change tour_confirmed.
 */
function hbEnsureServiceRow(mysqli $conn, int $quotationId, string $uid): bool
{
    $stmt = $conn->prepare('SELECT `tour_confirm_json`, `hotels_json`, `flights_json`, `cost_sheet_json` FROM `crm_quotations` WHERE `id` = ? LIMIT 1');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('i', $quotationId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        return false;
    }
    $stored = json_decode((string) ($row['tour_confirm_json'] ?? ''), true);
    if (!is_array($stored)) {
        $stored = [];
    }
    $services = isset($stored['services']) && is_array($stored['services']) ? $stored['services'] : [];
    foreach ($services as $svc) {
        if (is_array($svc) && (string) ($svc['uid'] ?? '') === $uid) {
            return true;
        }
    }
    $built = crmQuotationMergeConfirmServices(
        crmQuotationBuildConfirmServicesFromQuote($row),
        $services
    );
    $found = null;
    foreach ($built as $svc) {
        if ((string) ($svc['uid'] ?? '') === $uid) {
            $found = $svc;
            break;
        }
    }
    if ($found === null) {
        return false;
    }
    $services[] = $found;
    $stored['services'] = $services;
    $json = json_encode($stored, JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return false;
    }
    $upd = $conn->prepare('UPDATE `crm_quotations` SET `tour_confirm_json` = ? WHERE `id` = ? LIMIT 1');
    if (!$upd) {
        return false;
    }
    $upd->bind_param('si', $json, $quotationId);
    $ok = $upd->execute();
    $upd->close();
    return $ok;
}

/** @return list<array<string, mixed>> */
function hbList(mysqli $conn): array
{
    crmEnsureQuotationTables($conn);
    hbEnsureTable($conn);
    spEnsureTable($conn);
    svEnsureTable($conn);

    $metaMap = hbMetaMap($conn);
    $suppliers = hbSupplierContacts($conn);
    $payments = hbGrouped($conn, 'SELECT * FROM `crm_service_payments` ORDER BY `payment_date` ASC, `id` ASC');
    $vouchers = hbGrouped($conn, 'SELECT * FROM `crm_service_vouchers` ORDER BY `uploaded_at` ASC, `id` ASC');

    $sql = 'SELECT q.`id`, q.`quotation_uid`, q.`lead_id`, q.`guest_name`, q.`mobile_no`, q.`email`,
                   q.`destination`, q.`tentative_date`, q.`no_of_adults`, q.`no_of_children`, q.`no_of_infants`,
                   q.`hotels_json`, q.`flights_json`, q.`cost_sheet_json`, q.`tour_confirm_json`, q.`cancellation_policy`,
                   l.`lead_uid`
            FROM `crm_quotations` q
            LEFT JOIN `crm_leads` l ON l.`id` = q.`lead_id`
            WHERE q.`tour_confirmed` = 1
            ORDER BY q.`id` DESC';
    $res = $conn->query($sql);
    if (!$res) {
        return [];
    }

    $bookings = [];
    while ($quote = $res->fetch_assoc()) {
        $qid = (int) $quote['id'];
        $stored = json_decode((string) ($quote['tour_confirm_json'] ?? ''), true);
        $storedServices = [];
        if (is_array($stored) && isset($stored['services']) && is_array($stored['services'])) {
            $storedServices = $stored['services'];
        }
        $merged = crmQuotationMergeConfirmServices(
            crmQuotationBuildConfirmServicesFromQuote($quote),
            $storedServices
        );
        $bySource = [];
        foreach ($merged as $svc) {
            if ((string) ($svc['key'] ?? '') !== 'hotels') {
                continue;
            }
            $sid = (string) ($svc['source_id'] ?? '');
            if ($sid !== '') {
                $bySource[$sid] = $svc;
            }
        }
        $hotels = crmQuotationActiveHotelsFromJson($quote['hotels_json'] ?? '[]');
        $seen = [];
        foreach ($hotels as $hotel) {
            if (!is_array($hotel)) {
                continue;
            }
            $label = crmQuotationHotelServiceLabel($hotel);
            $supplier = crmQuotationConfirmSupplierName($hotel);
            $amount = hbMoney($hotel['rate'] ?? ($hotel['amount'] ?? 0));
            if ($label === 'Hotel' && $supplier === '' && $amount <= 0) {
                continue;
            }
            $checkinRaw = trim((string) ($hotel['checkin'] ?? ($hotel['check_in'] ?? '')));
            $checkoutRaw = trim((string) ($hotel['checkout'] ?? ($hotel['check_out'] ?? '')));
            $roomType = trim((string) ($hotel['room_type'] ?? ''));
            $city = trim((string) ($hotel['city'] ?? ''));
            $identity = strtolower($label) . '|' . strtolower($city) . '|' . $checkinRaw . '|' . $checkoutRaw . '|' . strtolower($roomType);
            $dup = $seen[$identity] ?? 0;
            $seen[$identity] = $dup + 1;
            $sourceId = crmQuotationConfirmSourceId('h', $identity, $dup);
            $svc = $bySource[$sourceId] ?? null;
            $uid = $svc ? (string) ($svc['uid'] ?? '') : crmQuotationConfirmItemUid('hotels', $sourceId);
            if ($uid === '') {
                continue;
            }
            $total = $svc ? hbMoney($svc['total'] ?? 0) : $amount;
            $paid = $svc ? hbMoney($svc['paid'] ?? 0) : 0;
            $meta = hbTouchMeta($conn, $qid, $uid, $metaMap);
            if (!$meta) {
                continue;
            }
            $checkin = hbDate($checkinRaw);
            $checkout = hbDate($checkoutRaw);
            $nights = 0;
            if ($checkin && $checkout && $checkout > $checkin) {
                $nights = (int) $checkin->diff($checkout)->days;
            } else {
                $nights = max(0, (int) ($hotel['nights'] ?? 0));
            }
            $rooms = max(0, (int) ($hotel['rooms'] ?? 0));
            $adults = max(0, (int) ($quote['no_of_adults'] ?? 0));
            $children = max(0, (int) ($quote['no_of_children'] ?? 0));
            $infants = max(0, (int) ($quote['no_of_infants'] ?? 0));
            $bookingStatus = strtolower((string) ($meta['booking_status'] ?? 'pending'));
            if (!in_array($bookingStatus, ['pending', 'confirmed', 'cancelled'], true)) {
                $bookingStatus = 'pending';
            }
            $payKey = hbPaymentStatus($total, $paid);
            $reconf = hbReconfirmState($meta, $checkin, $bookingStatus);
            $contact = $suppliers[strtolower($supplier)] ?? ['email' => '', 'phone' => ''];
            $payRows = [];
            foreach ($payments[$qid . '|' . $uid] ?? [] as $pay) {
                $payRows[] = spPublicPayment($pay);
            }
            $files = [];
            foreach ($vouchers[$qid . '|' . $uid] ?? [] as $file) {
                $files[] = svPublicVoucher($file);
            }
            $policy = trim((string) ($meta['cancellation_policy'] ?? ''));
            if ($policy === '') {
                $policy = hbClean(html_entity_decode(strip_tags((string) ($quote['cancellation_policy'] ?? '')), ENT_QUOTES, 'UTF-8'), 500);
            }
            $stars = (int) preg_replace('/\D/', '', (string) ($hotel['star_category'] ?? ($hotel['stars'] ?? '')));
            if ($stars < 1 || $stars > 5) {
                $stars = 0;
            }
            $dest = trim((string) ($quote['destination'] ?? ''));
            $leadUid = trim((string) ($quote['lead_uid'] ?? ''));
            if ($leadUid === '' && (int) ($quote['lead_id'] ?? 0) > 0) {
                $leadUid = 'LD-' . (int) $quote['lead_id'];
            }
            $bookings[] = [
                'code' => (string) $meta['booking_code'],
                'quotation_id' => $qid,
                'quotation_uid' => (string) ($quote['quotation_uid'] ?? ''),
                'lead_id' => (int) ($quote['lead_id'] ?? 0),
                'lead_uid' => $leadUid,
                'service_uid' => $uid,
                'guest' => trim((string) ($quote['guest_name'] ?? '')),
                'phone' => trim((string) ($quote['mobile_no'] ?? '')),
                'email' => trim((string) ($quote['email'] ?? '')),
                'destination' => $dest,
                'tour' => $dest !== '' ? $dest : (string) ($quote['quotation_uid'] ?? ''),
                'hotel' => $label,
                'city' => $city,
                'country' => trim((string) ($hotel['country'] ?? ($hotel['country_name'] ?? ''))),
                'stars' => $stars,
                'room_type' => $roomType,
                'meal_plan' => trim((string) ($hotel['meal_plan'] ?? ($hotel['meal'] ?? ''))),
                'rooms' => $rooms,
                'nights' => $nights,
                'checkin' => $checkin ? $checkin->format('Y-m-d') : '',
                'checkout' => $checkout ? $checkout->format('Y-m-d') : '',
                'adults' => $adults,
                'children' => $children,
                'infants' => $infants,
                'supplier' => $supplier,
                'supplier_email' => (string) $contact['email'],
                'supplier_phone' => (string) $contact['phone'],
                'total' => $total,
                'paid' => $paid,
                'balance' => max(0, round($total - $paid, 2)),
                'payment_status' => $payKey,
                'booking_status' => $bookingStatus,
                'reconfirm_status' => (string) $reconf['key'],
                'reconfirm_label' => (string) $reconf['label'],
                'reconfirm_due' => (string) $reconf['due'],
                'reconfirmed_at' => (string) ($meta['reconfirmed_at'] ?? ''),
                'booking_ref' => trim((string) ($meta['booking_ref'] ?? '')),
                'cancellation_policy' => $policy,
                'notes' => trim((string) ($meta['notes'] ?? '')),
                'reminder_at' => (string) ($meta['reminder_at'] ?? ''),
                'created_at' => (string) ($meta['created_at'] ?? ''),
                'payments' => $payRows,
                'vouchers' => $files,
            ];
        }
    }
    return $bookings;
}

function hbFind(mysqli $conn, string $code): ?array
{
    foreach (hbList($conn) as $row) {
        if ((string) $row['code'] === $code) {
            return $row;
        }
    }
    return null;
}

/** @return array{ok: bool, message: string, booking?: array<string, mixed>} */
function hbSaveMeta(mysqli $conn, string $code, array $input): array
{
    hbEnsureTable($conn);
    $stmt = $conn->prepare('SELECT * FROM `crm_hotel_booking_meta` WHERE `booking_code` = ? LIMIT 1');
    if (!$stmt) {
        return ['ok' => false, 'message' => 'Could not save this booking.'];
    }
    $stmt->bind_param('s', $code);
    $stmt->execute();
    $meta = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$meta) {
        return ['ok' => false, 'message' => 'Booking not found.'];
    }
    $status = strtolower(trim((string) ($input['booking_status'] ?? $meta['booking_status'])));
    if (!in_array($status, ['pending', 'confirmed', 'cancelled'], true)) {
        $status = 'pending';
    }
    $reconfirm = strtolower(trim((string) ($input['reconfirm_status'] ?? $meta['reconfirm_status'])));
    if (!in_array($reconfirm, ['pending', 'confirmed'], true)) {
        $reconfirm = 'pending';
    }
    $dueRaw = trim((string) ($input['reconfirm_due'] ?? ''));
    $due = null;
    if ($dueRaw !== '') {
        $parsed = hbDate($dueRaw);
        if (!$parsed) {
            return ['ok' => false, 'message' => 'Enter a valid reconfirmation date.'];
        }
        $due = $parsed->format('Y-m-d');
    } elseif (!empty($meta['reconfirm_due']) && $meta['reconfirm_due'] !== '0000-00-00' && !array_key_exists('reconfirm_due', $input)) {
        $due = (string) $meta['reconfirm_due'];
    }
    $ref = hbClean((string) ($input['booking_ref'] ?? $meta['booking_ref']), 80);
    $policy = hbClean((string) ($input['cancellation_policy'] ?? $meta['cancellation_policy']), 500);
    $notes = hbClean((string) ($input['notes'] ?? $meta['notes']), 500);
    $reconfirmedAt = (string) ($meta['reconfirmed_at'] ?? '');
    if ($reconfirm === 'confirmed' && $reconfirmedAt === '') {
        $reconfirmedAt = date('Y-m-d H:i:s');
    }
    if ($reconfirm !== 'confirmed') {
        $reconfirmedAt = null;
    }
    $upd = $conn->prepare(
        'UPDATE `crm_hotel_booking_meta`
         SET `booking_status` = ?, `reconfirm_status` = ?, `reconfirm_due` = ?, `reconfirmed_at` = ?,
             `booking_ref` = ?, `cancellation_policy` = ?, `notes` = ?
         WHERE `booking_code` = ? LIMIT 1'
    );
    if (!$upd) {
        return ['ok' => false, 'message' => 'Could not save this booking.'];
    }
    $upd->bind_param('ssssssss', $status, $reconfirm, $due, $reconfirmedAt, $ref, $policy, $notes, $code);
    $ok = $upd->execute();
    $upd->close();
    if (!$ok) {
        return ['ok' => false, 'message' => 'Could not save this booking.'];
    }
    $booking = hbFind($conn, $code);
    return $booking ? ['ok' => true, 'message' => 'Saved.', 'booking' => $booking] : ['ok' => false, 'message' => 'Saved, but the booking could not be reloaded.'];
}

function hbRecordPayment(mysqli $conn, string $code, array $input): array
{
    $current = hbFind($conn, $code);
    if (!$current) {
        return ['ok' => false, 'message' => 'Booking not found.'];
    }
    if ($current['booking_status'] === 'cancelled') {
        return ['ok' => false, 'message' => 'This hotel booking is cancelled.'];
    }
    $amount = hbMoney($input['amount'] ?? 0);
    if ($amount <= 0) {
        return ['ok' => false, 'message' => 'Enter a payment amount greater than 0.'];
    }
    if ($amount - $current['balance'] > 0.01) {
        return ['ok' => false, 'message' => 'Amount is higher than the outstanding balance.'];
    }
    $date = trim((string) ($input['payment_date'] ?? ''));
    $parsed = DateTime::createFromFormat('!Y-m-d', $date);
    if (!$parsed || $parsed->format('Y-m-d') !== $date) {
        return ['ok' => false, 'message' => 'Enter a valid payment date.'];
    }
    $method = trim((string) ($input['method'] ?? ''));
    if (!in_array($method, spPaymentMethods(), true)) {
        return ['ok' => false, 'message' => 'Choose a payment method.'];
    }
    $qid = (int) $current['quotation_id'];
    $uid = (string) $current['service_uid'];
    if (!hbEnsureServiceRow($conn, $qid, $uid)) {
        return ['ok' => false, 'message' => 'This hotel is not on the confirmed tour anymore.'];
    }
    $reference = hbClean((string) ($input['reference'] ?? ''), 120);
    $notes = hbClean((string) ($input['notes'] ?? ''), 500);
    $leadId = (int) $current['lead_id'];
    $serviceKey = 'hotels';
    $createdBy = hbClean((string) ($_SESSION['name'] ?? ''), 120);
    $stmt = $conn->prepare(
        'INSERT INTO `crm_service_payments`
            (`quotation_id`, `lead_id`, `service_uid`, `service_key`, `amount`, `payment_date`, `method`, `reference`, `notes`, `created_by`)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    if (!$stmt) {
        return ['ok' => false, 'message' => 'Could not save the payment.'];
    }
    $stmt->bind_param('iissdsssss', $qid, $leadId, $uid, $serviceKey, $amount, $date, $method, $reference, $notes, $createdBy);
    $ok = $stmt->execute();
    $stmt->close();
    if (!$ok) {
        return ['ok' => false, 'message' => 'Could not save the payment.'];
    }
    spAdjustStoredPaid($conn, $qid, $uid, $amount);
    $booking = hbFind($conn, $code);
    return $booking
        ? ['ok' => true, 'message' => 'Payment recorded.', 'booking' => $booking]
        : ['ok' => false, 'message' => 'Payment saved, but the booking could not be reloaded.'];
}

function hbScheduleReminder(mysqli $conn, string $code, string $whenRaw): array
{
    $when = trim(str_replace('T', ' ', $whenRaw));
    $ts = strtotime($when);
    if ($ts === false) {
        return ['ok' => false, 'message' => 'Enter a reminder date and time.'];
    }
    $whenSql = date('Y-m-d H:i:s', $ts);
    hbEnsureTable($conn);
    $upd = $conn->prepare('UPDATE `crm_hotel_booking_meta` SET `reminder_at` = ? WHERE `booking_code` = ? LIMIT 1');
    if (!$upd) {
        return ['ok' => false, 'message' => 'Could not save the reminder.'];
    }
    $upd->bind_param('ss', $whenSql, $code);
    $upd->execute();
    $upd->close();
    $booking = hbFind($conn, $code);
    if (!$booking) {
        return ['ok' => false, 'message' => 'Booking not found.'];
    }
    require_once __DIR__ . '/work_items.php';
    crmEnsureWorkItemTables($conn);
    crmWorkItemSave($conn, [
        'kind' => 'followup',
        'lead_id' => $booking['lead_id'],
        'client_name' => $booking['guest'] !== '' ? $booking['guest'] : $booking['hotel'],
        'client_phone' => $booking['phone'],
        'client_email' => $booking['email'],
        'item_type' => 'email',
        'title' => 'Reconfirm ' . $booking['code'] . ' · ' . $booking['hotel'],
        'notes' => 'Hotel reconfirmation for ' . $booking['guest'] . ' at ' . $booking['hotel'] . '.',
        'scheduled_at' => $whenSql,
        'priority' => 'high',
        'status' => 'pending',
        'assigned_name' => (string) ($_SESSION['name'] ?? ''),
        'reminder' => '1d',
    ]);
    $booking = hbFind($conn, $code);
    return ['ok' => true, 'message' => 'Reminder scheduled.', 'booking' => $booking];
}
