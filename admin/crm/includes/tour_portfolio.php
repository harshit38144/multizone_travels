<?php
/**
 * Contact Tour History + Supplier Associated Tours — read-through helpers.
 * Single source of truth: confirmed bookings (cbList) + tour_confirm_json services.
 */

require_once __DIR__ . '/confirmed_bookings.php';
require_once __DIR__ . '/supplier_db.php';

/**
 * Map internal pay code to UI label + CSS class.
 * @return array{code:string,label:string,cls:string}
 */
function tpPayDisplay(string $code): array
{
    $code = strtolower(trim($code));
    if ($code === 'paid') {
        return ['code' => 'paid', 'label' => 'Paid', 'cls' => 'is-paid'];
    }
    if ($code === 'partial') {
        return ['code' => 'partial', 'label' => 'Partially Paid', 'cls' => 'is-partial'];
    }
    return ['code' => 'unpaid', 'label' => 'Unpaid', 'cls' => 'is-unpaid'];
}

/**
 * Simplified pay status for portfolio UI (Paid / Partially Paid / Unpaid).
 */
function tpSimplePayStatus(float $total, float $paid): string
{
    $total = round($total, 2);
    $paid = round($paid, 2);
    if ($total > 0.009 && $paid + 0.009 >= $total) {
        return 'paid';
    }
    if ($paid > 0.009) {
        return 'partial';
    }
    return 'unpaid';
}

/**
 * Build supplier name → id map (unique exact names only; ambiguous → 0).
 * @return array{by_id:array<int,string>,by_name:array<string,int>,ambiguous:array<string,true>}
 */
function tpSupplierLookup(mysqli $conn): array
{
    crmEnsureSupplierTables($conn);
    $byId = [];
    $counts = [];
    $res = $conn->query('SELECT `id`, `name` FROM `crm_suppliers`');
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $id = (int) ($row['id'] ?? 0);
            $name = trim((string) ($row['name'] ?? ''));
            if ($id < 1 || $name === '') {
                continue;
            }
            $byId[$id] = $name;
            $norm = crmQuotationConfirmNormSupplier($name);
            if ($norm === '') {
                continue;
            }
            if (!isset($counts[$norm])) {
                $counts[$norm] = [];
            }
            $counts[$norm][$id] = true;
        }
    }
    $byName = [];
    $ambiguous = [];
    foreach ($counts as $norm => $ids) {
        $idList = array_keys($ids);
        if (count($idList) === 1) {
            $byName[$norm] = (int) $idList[0];
        } else {
            $ambiguous[$norm] = true;
        }
    }
    return ['by_id' => $byId, 'by_name' => $byName, 'ambiguous' => $ambiguous];
}

/**
 * Resolve supplier_id from service row using id first, then unique name.
 * @param array<string,mixed> $svc
 * @param array{by_id:array<int,string>,by_name:array<string,int>,ambiguous:array<string,true>} $lookup
 * @return array{id:int,needs_review:bool}
 */
function tpResolveSupplierId(array $svc, array $lookup): array
{
    $id = (int) ($svc['supplier_id'] ?? 0);
    if ($id > 0 && isset($lookup['by_id'][$id])) {
        return ['id' => $id, 'needs_review' => false];
    }
    $name = trim((string) ($svc['supplier'] ?? ''));
    $norm = crmQuotationConfirmNormSupplier($name);
    if ($norm === '') {
        return ['id' => 0, 'needs_review' => false];
    }
    if (isset($lookup['ambiguous'][$norm])) {
        return ['id' => 0, 'needs_review' => true];
    }
    if (isset($lookup['by_name'][$norm])) {
        return ['id' => (int) $lookup['by_name'][$norm], 'needs_review' => false];
    }
    return ['id' => 0, 'needs_review' => $name !== ''];
}

/**
 * Persist missing supplier_id onto tour_confirm_json when uniquely matched.
 * Idempotent; never overwrites existing supplier_id or payment fields.
 * @return array{updated:int,skipped_ambiguous:int}
 */
function tpBackfillSupplierIds(mysqli $conn): array
{
    crmEnsureQuotationTables($conn);
    $lookup = tpSupplierLookup($conn);
    $updated = 0;
    $skipped = 0;
    $res = $conn->query(
        "SELECT `id`, `tour_confirm_json` FROM `crm_quotations`
         WHERE `tour_confirmed` = 1 AND `tour_confirm_json` IS NOT NULL AND `tour_confirm_json` <> ''"
    );
    if (!$res) {
        return ['updated' => 0, 'skipped_ambiguous' => 0];
    }
    while ($row = $res->fetch_assoc()) {
        $qid = (int) ($row['id'] ?? 0);
        $raw = (string) ($row['tour_confirm_json'] ?? '');
        $data = json_decode($raw, true);
        if (!is_array($data) || empty($data['services']) || !is_array($data['services'])) {
            continue;
        }
        $changed = false;
        foreach ($data['services'] as $i => $svc) {
            if (!is_array($svc)) {
                continue;
            }
            $existingId = (int) ($svc['supplier_id'] ?? 0);
            if ($existingId > 0) {
                continue;
            }
            $resolved = tpResolveSupplierId($svc, $lookup);
            if ($resolved['needs_review'] && $resolved['id'] < 1) {
                $skipped++;
                continue;
            }
            if ($resolved['id'] > 0) {
                $data['services'][$i]['supplier_id'] = $resolved['id'];
                $changed = true;
            }
        }
        if (!$changed) {
            continue;
        }
        $json = json_encode($data, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            continue;
        }
        $stmt = $conn->prepare('UPDATE `crm_quotations` SET `tour_confirm_json` = ? WHERE `id` = ? LIMIT 1');
        if (!$stmt) {
            continue;
        }
        $stmt->bind_param('si', $json, $qid);
        if ($stmt->execute()) {
            $updated++;
        }
        $stmt->close();
    }
    return ['updated' => $updated, 'skipped_ambiguous' => $skipped];
}

/**
 * Attach resolved supplier_id onto each service of a booking (in-memory).
 * @param array<string,mixed> $booking
 * @param array{by_id:array<int,string>,by_name:array<string,int>,ambiguous:array<string,true>} $lookup
 * @return array<string,mixed>
 */
function tpEnrichBookingServices(array $booking, array $lookup): array
{
    $services = isset($booking['services']) && is_array($booking['services']) ? $booking['services'] : [];
    $enriched = [];
    foreach ($services as $svc) {
        if (!is_array($svc)) {
            continue;
        }
        $resolved = tpResolveSupplierId($svc, $lookup);
        $svc['supplier_id'] = $resolved['id'] > 0
            ? $resolved['id']
            : (int) ($svc['supplier_id'] ?? 0);
        $svc['supplier_needs_review'] = !empty($resolved['needs_review']) && $resolved['id'] < 1;
        $enriched[] = $svc;
    }
    $booking['services'] = $enriched;
    return $booking;
}

/**
 * Duration label from nights.
 */
function tpDurationLabel(int $nights): string
{
    $nights = max(0, $nights);
    if ($nights <= 0) {
        return '—';
    }
    $days = $nights + 1;
    return $nights . 'N / ' . $days . 'D';
}

/**
 * Included service labels for a booking.
 * @param list<array<string,mixed>> $services
 */
function tpIncludedServicesLabel(array $services): string
{
    $labels = [];
    foreach ($services as $svc) {
        if (!is_array($svc)) {
            continue;
        }
        $label = trim((string) ($svc['label'] ?? ''));
        if ($label === '') {
            $label = trim((string) ($svc['key'] ?? ''));
        }
        if ($label !== '' && !in_array($label, $labels, true)) {
            $labels[] = $label;
        }
    }
    return $labels !== [] ? implode(', ', $labels) : '—';
}

/**
 * Tour History for a contact (source + ref_id).
 * Lead contacts: match by lead_id. Manual: match by phone/email/name when confident.
 *
 * @return array{summary:array<string,mixed>,tours:list<array<string,mixed>>,needs_review:int}
 */
function tpContactTourHistory(mysqli $conn, string $source, int $refId, array $filters = []): array
{
    $source = $source === 'manual' ? 'manual' : 'lead';
    $refId = max(0, $refId);
    $today = date('Y-m-d');
    $lookup = tpSupplierLookup($conn);
    $all = cbList($conn);

    $matchPhone = '';
    $matchEmail = '';
    $matchName = '';
    if ($source === 'manual' && $refId > 0) {
        require_once __DIR__ . '/../../includes/lead_contacts_db.php';
        $contact = lcGetManualContact($conn, $refId);
        if (is_array($contact)) {
            $matchPhone = trim((string) ($contact['mobile'] ?? ''));
            $matchEmail = strtolower(trim((string) ($contact['email'] ?? '')));
            $matchName = strtolower(trim((string) (
                function_exists('lcDisplayName')
                    ? lcDisplayName($contact)
                    : ($contact['full_name'] ?? ($contact['name'] ?? ''))
            )));
        }
    }

    $tours = [];
    $needsReview = 0;
    foreach ($all as $booking) {
        if (!is_array($booking)) {
            continue;
        }
        $booking = tpEnrichBookingServices($booking, $lookup);
        $matched = false;
        if ($source === 'lead') {
            $matched = ((int) ($booking['lead_id'] ?? 0) === $refId);
        } else {
            $phone = trim((string) ($booking['phone'] ?? ''));
            $email = strtolower(trim((string) ($booking['email'] ?? '')));
            $guest = strtolower(trim((string) ($booking['guest'] ?? '')));
            if ($matchPhone !== '' && $phone !== '' && preg_replace('/\D+/', '', $phone) === preg_replace('/\D+/', '', $matchPhone)) {
                $matched = true;
            } elseif ($matchEmail !== '' && $email !== '' && $email === $matchEmail) {
                $matched = true;
            } elseif ($matchName !== '' && $guest !== '' && $guest === $matchName && $matchPhone === '' && $matchEmail === '') {
                // Name-only match without phone/email is ambiguous — mark for review, skip auto-link.
                $needsReview++;
                continue;
            }
        }
        if (!$matched) {
            continue;
        }

        $phase = (string) ($booking['phase'] ?? '');
        $payCode = tpSimplePayStatus((float) ($booking['total'] ?? 0), (float) ($booking['customer_paid'] ?? 0));
        $pay = tpPayDisplay($payCode);

        $row = [
            'id' => (int) ($booking['id'] ?? 0),
            'code' => (string) ($booking['code'] ?? ''),
            'destination' => (string) ($booking['destination'] ?? ''),
            'travel_date' => (string) ($booking['start'] ?? ''),
            'travel_end' => (string) ($booking['end'] ?? ''),
            'duration' => tpDurationLabel((int) ($booking['nights'] ?? 0)),
            'nights' => (int) ($booking['nights'] ?? 0),
            'pax' => (int) ($booking['pax'] ?? 0),
            'included_services' => tpIncludedServicesLabel($booking['services'] ?? []),
            'total' => round((float) ($booking['total'] ?? 0), 2),
            'paid' => round((float) ($booking['customer_paid'] ?? 0), 2),
            'outstanding' => round((float) ($booking['customer_balance'] ?? 0), 2),
            'payment_status' => $pay['code'],
            'payment_label' => $pay['label'],
            'payment_cls' => $pay['cls'],
            'tour_status' => (string) ($booking['status'] ?? ''),
            'phase' => $phase,
            'confirmed_at' => (string) ($booking['confirmed_at'] ?? ''),
        ];

        if (!tpContactTourPassesFilters($row, $filters)) {
            continue;
        }
        $tours[] = $row;
    }

    // Latest first (cbList already orders by confirmed_at DESC, keep stable).
    usort($tours, static function ($a, $b) {
        return strcmp((string) ($b['confirmed_at'] ?? ''), (string) ($a['confirmed_at'] ?? ''));
    });

    $summary = [
        'total_tours' => count($tours),
        'upcoming_tours' => 0,
        'completed_tours' => 0,
        'total_booking_value' => 0.0,
        'total_paid' => 0.0,
        'total_outstanding' => 0.0,
    ];
    foreach ($tours as $t) {
        $phase = (string) ($t['phase'] ?? '');
        if ($phase === 'completed') {
            $summary['completed_tours']++;
        } elseif ($phase !== 'cancelled') {
            // upcoming / confirmed / ongoing
            $summary['upcoming_tours']++;
        }
        $summary['total_booking_value'] += (float) $t['total'];
        $summary['total_paid'] += (float) $t['paid'];
        $summary['total_outstanding'] += (float) $t['outstanding'];
    }
    $summary['total_booking_value'] = round($summary['total_booking_value'], 2);
    $summary['total_paid'] = round($summary['total_paid'], 2);
    $summary['total_outstanding'] = round($summary['total_outstanding'], 2);

    return [
        'summary' => $summary,
        'tours' => $tours,
        'needs_review' => $needsReview,
    ];
}

/**
 * @param array<string,mixed> $row
 * @param array<string,mixed> $filters
 */
function tpContactTourPassesFilters(array $row, array $filters): bool
{
    $dest = trim((string) ($filters['destination'] ?? ''));
    if ($dest !== '' && stripos((string) ($row['destination'] ?? ''), $dest) === false) {
        return false;
    }
    $from = trim((string) ($filters['date_from'] ?? ''));
    $to = trim((string) ($filters['date_to'] ?? ''));
    $travel = (string) ($row['travel_date'] ?? '');
    if ($from !== '' && $travel !== '' && $travel < $from) {
        return false;
    }
    if ($to !== '' && $travel !== '' && $travel > $to) {
        return false;
    }
    $tourStatus = strtolower(trim((string) ($filters['tour_status'] ?? '')));
    if ($tourStatus !== '' && $tourStatus !== 'all') {
        $phase = strtolower((string) ($row['phase'] ?? ''));
        $status = strtolower((string) ($row['tour_status'] ?? ''));
        if ($tourStatus !== $phase && $tourStatus !== $status) {
            return false;
        }
    }
    $payStatus = strtolower(trim((string) ($filters['payment_status'] ?? '')));
    if ($payStatus !== '' && $payStatus !== 'all' && $payStatus !== (string) ($row['payment_status'] ?? '')) {
        return false;
    }
    $q = trim((string) ($filters['q'] ?? ''));
    if ($q !== '') {
        $hay = strtolower(
            ($row['code'] ?? '') . ' ' .
            ($row['destination'] ?? '') . ' ' .
            ($row['included_services'] ?? '')
        );
        if (strpos($hay, strtolower($q)) === false) {
            return false;
        }
    }
    return true;
}

/**
 * Associated tours/services for a supplier.
 *
 * @return array{summary:array<string,mixed>,rows:list<array<string,mixed>>,needs_review:int}
 */
function tpSupplierAssociatedTours(mysqli $conn, int $supplierId, array $filters = []): array
{
    $supplierId = max(0, $supplierId);
    if ($supplierId < 1) {
        return [
            'summary' => tpSupplierEmptySummary(),
            'rows' => [],
            'needs_review' => 0,
        ];
    }
    $lookup = tpSupplierLookup($conn);
    $all = cbList($conn);
    $rows = [];
    $needsReview = 0;
    $bookingIds = [];

    foreach ($all as $booking) {
        if (!is_array($booking)) {
            continue;
        }
        $booking = tpEnrichBookingServices($booking, $lookup);
        $services = isset($booking['services']) && is_array($booking['services']) ? $booking['services'] : [];
        $matchedServices = [];
        foreach ($services as $svc) {
            if (!is_array($svc)) {
                continue;
            }
            if (!empty($svc['supplier_needs_review'])) {
                $needsReview++;
            }
            if ((int) ($svc['supplier_id'] ?? 0) !== $supplierId) {
                continue;
            }
            $matchedServices[] = $svc;
        }
        if ($matchedServices === []) {
            continue;
        }
        $bookingIds[(int) $booking['id']] = true;
        foreach ($matchedServices as $svc) {
            $total = round((float) ($svc['total'] ?? 0), 2);
            $paid = round((float) ($svc['paid'] ?? 0), 2);
            $outstanding = max(0, round($total - $paid, 2));
            $pay = tpPayDisplay(tpSimplePayStatus($total, $paid));
            $row = [
                'booking_id' => (int) ($booking['id'] ?? 0),
                'code' => (string) ($booking['code'] ?? ''),
                'guest' => (string) ($booking['guest'] ?? ''),
                'destination' => (string) ($booking['destination'] ?? ''),
                'travel_date' => (string) ($booking['start'] ?? ''),
                'service_uid' => (string) ($svc['uid'] ?? ''),
                'service_key' => (string) ($svc['key'] ?? ''),
                'service_label' => (string) ($svc['label'] ?? ($svc['key'] ?? '')),
                'total' => $total,
                'paid' => $paid,
                'outstanding' => $outstanding,
                'payment_status' => $pay['code'],
                'payment_label' => $pay['label'],
                'payment_cls' => $pay['cls'],
                'booking_status' => (string) ($booking['status'] ?? ''),
                'phase' => (string) ($booking['phase'] ?? ''),
                'confirmed_at' => (string) ($booking['confirmed_at'] ?? ''),
            ];
            if (!tpSupplierRowPassesFilters($row, $filters)) {
                continue;
            }
            $rows[] = $row;
        }
    }

    usort($rows, static function ($a, $b) {
        $c = strcmp((string) ($b['confirmed_at'] ?? ''), (string) ($a['confirmed_at'] ?? ''));
        if ($c !== 0) {
            return $c;
        }
        $c = ((int) ($b['booking_id'] ?? 0)) <=> ((int) ($a['booking_id'] ?? 0));
        if ($c !== 0) {
            return $c;
        }
        return strcmp((string) ($a['service_label'] ?? ''), (string) ($b['service_label'] ?? ''));
    });

    $summary = tpSupplierEmptySummary();
    $seenBookings = [];
    foreach ($rows as $r) {
        $bid = (int) ($r['booking_id'] ?? 0);
        if ($bid > 0 && !isset($seenBookings[$bid])) {
            $seenBookings[$bid] = true;
            $summary['total_associated_tours']++;
        }
        $summary['total_services']++;
        $summary['total_business_value'] += (float) $r['total'];
        $summary['total_paid'] += (float) $r['paid'];
        $summary['total_outstanding'] += (float) $r['outstanding'];
        if ((string) ($r['payment_status'] ?? '') !== 'paid') {
            $summary['pending_payments']++;
        }
    }
    $summary['total_business_value'] = round($summary['total_business_value'], 2);
    $summary['total_paid'] = round($summary['total_paid'], 2);
    $summary['total_outstanding'] = round($summary['total_outstanding'], 2);

    return [
        'summary' => $summary,
        'rows' => $rows,
        'needs_review' => $needsReview,
    ];
}

/** @return array<string,mixed> */
function tpSupplierEmptySummary(): array
{
    return [
        'total_associated_tours' => 0,
        'total_services' => 0,
        'total_business_value' => 0.0,
        'total_paid' => 0.0,
        'total_outstanding' => 0.0,
        'pending_payments' => 0,
    ];
}

/**
 * @param array<string,mixed> $row
 * @param array<string,mixed> $filters
 */
function tpSupplierRowPassesFilters(array $row, array $filters): bool
{
    $dest = trim((string) ($filters['destination'] ?? ''));
    if ($dest !== '' && stripos((string) ($row['destination'] ?? ''), $dest) === false) {
        return false;
    }
    $from = trim((string) ($filters['date_from'] ?? ''));
    $to = trim((string) ($filters['date_to'] ?? ''));
    $travel = (string) ($row['travel_date'] ?? '');
    if ($from !== '' && $travel !== '' && $travel < $from) {
        return false;
    }
    if ($to !== '' && $travel !== '' && $travel > $to) {
        return false;
    }
    $tourStatus = strtolower(trim((string) ($filters['tour_status'] ?? '')));
    if ($tourStatus !== '' && $tourStatus !== 'all') {
        $phase = strtolower((string) ($row['phase'] ?? ''));
        $status = strtolower((string) ($row['booking_status'] ?? ''));
        if ($tourStatus !== $phase && $tourStatus !== $status) {
            return false;
        }
    }
    $payStatus = strtolower(trim((string) ($filters['payment_status'] ?? '')));
    if ($payStatus !== '' && $payStatus !== 'all' && $payStatus !== (string) ($row['payment_status'] ?? '')) {
        return false;
    }
    $q = trim((string) ($filters['q'] ?? ''));
    if ($q !== '') {
        $hay = strtolower(
            ($row['code'] ?? '') . ' ' .
            ($row['guest'] ?? '') . ' ' .
            ($row['destination'] ?? '') . ' ' .
            ($row['service_label'] ?? '')
        );
        if (strpos($hay, strtolower($q)) === false) {
            return false;
        }
    }
    return true;
}

/**
 * Paginate a list.
 * @param list<array<string,mixed>> $items
 * @return array{items:list<array<string,mixed>>,page:int,per_page:int,total:int,pages:int}
 */
function tpPaginate(array $items, int $page = 1, int $perPage = 10): array
{
    $perPage = max(5, min(50, $perPage));
    $total = count($items);
    $pages = max(1, (int) ceil($total / $perPage));
    $page = max(1, min($pages, $page));
    $offset = ($page - 1) * $perPage;
    return [
        'items' => array_slice($items, $offset, $perPage),
        'page' => $page,
        'per_page' => $perPage,
        'total' => $total,
        'pages' => $pages,
    ];
}

/**
 * Resolve supplier_id onto a normalized confirm payload's services (in place).
 * @param array<string,mixed> $payload
 * @return array<string,mixed>
 */
function tpAttachSupplierIdsToPayload(mysqli $conn, array $payload): array
{
    if (empty($payload['services']) || !is_array($payload['services'])) {
        return $payload;
    }
    $lookup = tpSupplierLookup($conn);
    foreach ($payload['services'] as $i => $svc) {
        if (!is_array($svc)) {
            continue;
        }
        $existing = (int) ($svc['supplier_id'] ?? 0);
        if ($existing > 0 && isset($lookup['by_id'][$existing])) {
            $payload['services'][$i]['supplier_id'] = $existing;
            continue;
        }
        $resolved = tpResolveSupplierId($svc, $lookup);
        if ($resolved['id'] > 0) {
            $payload['services'][$i]['supplier_id'] = $resolved['id'];
        } else {
            unset($payload['services'][$i]['supplier_id']);
        }
    }
    return $payload;
}
