<?php

/**
 * Active destination id => name map for lead → quotation mapping.
 */

require_once __DIR__ . '/lead_db.php';
function crmQuotationDestinationLookup(mysqli $conn): array
{
    $lookup = [];
    $destRes = $conn->query("SELECT id, name FROM destinations WHERE is_active = 1");
    if ($destRes) {
        while ($dest = $destRes->fetch_assoc()) {
            $lookup[(int) $dest['id']] = (string) ($dest['name'] ?? '');
        }
    }

    return $lookup;
}

/**
 * Map a crm_leads row to quotation_generator guest/tour prefill fields.
 */
function crmLeadRowToQuotationPrefill(array $row, array $destinationLookup = []): array
{
    $payload = [];
    if (!empty($row['payload_json'])) {
        $decoded = json_decode((string) $row['payload_json'], true);
        if (is_array($decoded)) {
            $payload = $decoded;
        }
    }

    $destNames = [];
    $destIds = $payload['tp_destination'] ?? [];
    if (!is_array($destIds)) {
        $destIds = $destIds !== '' ? [$destIds] : [];
    }
    foreach ($destIds as $destId) {
        $destId = (int) $destId;
        if ($destId > 0 && isset($destinationLookup[$destId])) {
            $destNames[] = $destinationLookup[$destId];
        }
    }
    if (empty($destNames) && !empty($payload['tp_arrival'])) {
        $destNames[] = trim((string) $payload['tp_arrival']);
    }

    $tentativeDate = '';
    if (!empty($payload['tp_travel_date'])) {
        $ts = strtotime((string) $payload['tp_travel_date']);
        if ($ts !== false) {
            $tentativeDate = date('Y-m-d', $ts);
        }
    }

    $nights = max(0, (int) ($row['itinerary_total_nights'] ?? 0));
    if ($nights === 0 && !empty($payload['itinerary_dest_nights']) && is_array($payload['itinerary_dest_nights'])) {
        foreach ($payload['itinerary_dest_nights'] as $nightVal) {
            $nights += max(0, (int) $nightVal);
        }
    }

    $adults = max(0, (int) ($payload['tp_adults'] ?? 0));
    $children = max(0, (int) ($payload['tp_children'] ?? 0));
    if ($adults < 1) {
        $adults = 1;
    }

    $referredBy = trim((string) ($row['referred_by'] ?? ''));
    if ($referredBy === '' && !empty($payload['referred_by'])) {
        $referredBy = trim((string) $payload['referred_by']);
    }

    $guestName = trim((string) ($row['customer_name'] ?? ''));
    $mobileNo = trim((string) ($row['customer_phone'] ?? ''));
    $email = trim((string) ($row['customer_email'] ?? ''));

    return [
        'lead_id' => (int) ($row['id'] ?? 0),
        'lead_uid' => (string) ($row['lead_uid'] ?? ''),
        'guest_name' => $guestName,
        'mobile_no' => $mobileNo,
        'email' => $email,
        'reference_name' => $referredBy,
        'destination' => implode(', ', array_filter($destNames)),
        'tentative_date' => $tentativeDate,
        'no_of_nights' => $nights,
        'no_of_adults' => $adults,
        'no_of_children' => $children,
    ];
}

function crmLeadServiceLabelMap(): array
{
    return [
        'tour_package' => 'Tour Package',
        'cruise' => 'Cruise',
        'flight' => 'Flight',
        'hotel' => 'Hotel',
        'vehicle' => 'Vehicle',
        'sightseeing' => 'Sightseeing',
        'visa' => 'Visa',
        'passport' => 'Passport',
        'forex' => 'Forex',
    ];
}

function crmLeadQuotationCount(mysqli $conn, int $leadId): int
{
    if ($leadId <= 0) {
        return 0;
    }

    crmEnsureQuotationTables($conn);
    $count = 0;
    $stmt = $conn->prepare('SELECT COUNT(*) AS c FROM `crm_quotations` WHERE `lead_id` = ?');
    if ($stmt) {
        $stmt->bind_param('i', $leadId);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && ($row = $res->fetch_assoc())) {
            $count = (int) ($row['c'] ?? 0);
        }
        $stmt->close();
    }

    return $count;
}

function crmLeadParseDateTimestamp(?string $dateValue): ?int
{
    $dateValue = trim((string) $dateValue);
    if ($dateValue === '') {
        return null;
    }
    if (preg_match('/^(\d{1,2})-(\d{1,2})-(\d{4})$/', $dateValue, $m)) {
        return mktime(0, 0, 0, (int) $m[2], (int) $m[1], (int) $m[3]) ?: null;
    }
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $dateValue, $m)) {
        return mktime(0, 0, 0, (int) $m[2], (int) $m[3], (int) $m[1]) ?: null;
    }
    $ts = strtotime($dateValue);

    return $ts !== false ? $ts : null;
}

function crmLeadFormatDisplayDate(?string $dateValue, string $format = 'd M Y'): string
{
    $dateValue = trim((string) $dateValue);
    if ($dateValue === '') {
        return '—';
    }
    $ts = crmLeadParseDateTimestamp($dateValue);
    if ($ts === null) {
        return '—';
    }

    return date($format, $ts);
}

/**
 * Format hotel category values as star labels (e.g. 4★).
 *
 * @param mixed $categories
 */
function crmFormatHotelCategoryStars($categories): string
{
    if (!is_array($categories)) {
        $categories = $categories !== null && $categories !== '' ? [$categories] : [];
    }
    $stars = [];
    foreach ($categories as $cat) {
        $cat = trim((string) $cat);
        if ($cat === '') {
            continue;
        }
        if (preg_match('/(\d+)/', $cat, $m)) {
            $label = $m[1] . '★';
        } else {
            $label = $cat;
        }
        if (!in_array($label, $stars, true)) {
            $stars[] = $label;
        }
    }

    return !empty($stars) ? implode(', ', $stars) : '—';
}

/**
 * Build supplier B2B quote-request email subject and HTML body.
 *
 * Subject: Mr Bob*03 | Almaty 06 Nts | 14 Oct
 *
 * @param array<string,mixed> $data
 * @return array{subject:string,body_html:string,meta:array<string,mixed>}
 */
function crmBuildSupplierQuoteMail(array $data): array
{
    $initial = trim((string) ($data['guest_initial'] ?? ''));
    $guestName = trim((string) ($data['guest_name'] ?? ''));
    $guestLabel = trim($initial . ($initial !== '' && $guestName !== '' ? ' ' : '') . $guestName);
    if ($guestLabel === '') {
        $guestLabel = 'Guest';
    }

    $adults = max(0, (int) ($data['adults'] ?? 0));
    $children = max(0, (int) ($data['children'] ?? 0));
    if ($adults < 1 && $children < 1) {
        $adults = 1;
    }
    $paxCount = $adults > 0 ? $adults : max(1, $children);
    $paxPad = str_pad((string) $paxCount, 2, '0', STR_PAD_LEFT);

    $destinations = $data['destinations'] ?? [];
    if (!is_array($destinations)) {
        $destinations = preg_split('/[,;|]+/', (string) $destinations) ?: [];
    }
    $destinations = array_values(array_filter(array_map(static function ($name) {
        return trim((string) $name);
    }, $destinations), static function ($name) {
        return $name !== '' && $name !== '—';
    }));
    $destLabel = !empty($destinations) ? $destinations[0] : '';
    $cities = max(0, (int) ($data['cities'] ?? 0));
    if ($cities < 1) {
        $cities = count($destinations);
    }
    $citiesPad = str_pad((string) max(0, $cities), 2, '0', STR_PAD_LEFT);

    $nights = max(0, (int) ($data['nights'] ?? 0));
    $nightsPad = str_pad((string) $nights, 2, '0', STR_PAD_LEFT);
    $daysPad = str_pad((string) ($nights > 0 ? $nights + 1 : 0), 2, '0', STR_PAD_LEFT);

    $travelDateRaw = trim((string) ($data['travel_date'] ?? ''));
    $ts = crmLeadParseDateTimestamp($travelDateRaw);
    $dateShort = $ts !== null ? date('d M', $ts) : '';
    $dateLong = $ts !== null ? date('d F', $ts) : '';

    $hotelLabel = crmFormatHotelCategoryStars($data['hotel_categories'] ?? []);

    $subjectParts = [$guestLabel . '*' . $paxPad];
    $destNights = trim($destLabel . ($nights > 0 ? ' ' . $nightsPad . ' Nts' : ''));
    if ($destNights !== '') {
        $subjectParts[] = $destNights;
    }
    if ($dateShort !== '') {
        $subjectParts[] = $dateShort;
    }
    $subject = implode(' | ', $subjectParts);

    $paxLine = $paxPad . ' Adult' . ($paxCount === 1 ? '' : 's');
    if ($children > 0 && $adults > 0) {
        $paxLine = str_pad((string) $adults, 2, '0', STR_PAD_LEFT) . ' Adult' . ($adults === 1 ? '' : 's')
            . ' + ' . str_pad((string) $children, 2, '0', STR_PAD_LEFT) . ' Child' . ($children === 1 ? '' : 'ren');
    } elseif ($children > 0 && $adults < 1) {
        $paxLine = str_pad((string) $children, 2, '0', STR_PAD_LEFT) . ' Child' . ($children === 1 ? '' : 'ren');
    }

    $duration = '—';
    if ($nights > 0) {
        $duration = $nightsPad . '–' . $daysPad . ' Nights';
        if ($cities > 0) {
            $duration .= ' (' . $citiesPad . ' ' . ($cities === 1 ? 'City' : 'Cities') . ')';
        }
    }

    $dotLine = $dateLong !== '' ? $dateLong : '—';

    $lines = [
        'Dear Team,',
        '',
        'Kindly quote your best B2B rates for the below requirement:',
        '',
        'Date of Travel (DOT): ' . $dotLine,
        'Duration: ' . $duration,
        'Pax: ' . $paxLine,
        'Hotel Category: ' . $hotelLabel,
        '',
        'Kindly share the suggested itinerary, hotel options, inclusions, exclusions, and your best possible costing at the earliest.',
        '',
        'Looking forward to your prompt response.',
        '',
        'Warm regards',
    ];

    $bodyParts = [];
    foreach ($lines as $line) {
        $bodyParts[] = $line === '' ? '<br>' : htmlspecialchars($line, ENT_QUOTES, 'UTF-8');
    }
    $bodyHtml = '<div style="line-height:1.45;margin:0;padding:0;">' . implode('<br>', $bodyParts) . '</div>';

    return [
        'subject' => $subject,
        'body_html' => $bodyHtml,
        'meta' => [
            'guest_initial' => $initial,
            'guest_name' => $guestName,
            'adults' => $adults,
            'children' => $children,
            'destinations' => $destinations,
            'destination' => implode(', ', $destinations),
            'cities' => $cities,
            'nights' => $nights,
            'travel_date' => $travelDateRaw,
            'hotel_categories' => is_array($data['hotel_categories'] ?? null)
                ? array_values($data['hotel_categories'])
                : [],
            'hotel_label' => $hotelLabel,
        ],
    ];
}

/**
 * Build supplier quote mail from lead row / quotation prefill.
 *
 * @param array<string,mixed>|null $row
 * @param array<int,string> $destinationLookup
 * @param array<string,mixed> $prefill
 * @param array<string,mixed> $quotation
 * @return array{subject:string,body_html:string,meta:array<string,mixed>}
 */
function crmSupplierQuoteMailFromContext(?array $row, array $destinationLookup = [], array $prefill = [], array $quotation = []): array
{
    $payload = [];
    if (is_array($row) && !empty($row['payload_json'])) {
        $decoded = json_decode((string) $row['payload_json'], true);
        if (is_array($decoded)) {
            $payload = $decoded;
        }
    }

    $initial = trim((string) ($payload['customer_initial'] ?? ''));
    $guestName = trim((string) ($prefill['guest_name'] ?? ($quotation['guest_name'] ?? '')));
    if ($guestName === '' && is_array($row)) {
        $guestName = trim((string) ($row['customer_name'] ?? ''));
    }

    $destinations = [];
    $destIds = $payload['tp_destination'] ?? [];
    if (!is_array($destIds)) {
        $destIds = $destIds !== '' ? [$destIds] : [];
    }
    foreach ($destIds as $destId) {
        $destId = (int) $destId;
        if ($destId > 0 && isset($destinationLookup[$destId])) {
            $destinations[] = $destinationLookup[$destId];
        }
    }
    if (empty($destinations) && !empty($payload['tp_arrival'])) {
        $destinations[] = trim((string) $payload['tp_arrival']);
    }
    $prefillDest = trim((string) ($prefill['destination'] ?? ($quotation['destination'] ?? '')));
    if (empty($destinations) && $prefillDest !== '') {
        $destinations = array_values(array_filter(array_map('trim', preg_split('/[,;|]+/', $prefillDest) ?: [])));
    }

    $nights = max(0, (int) ($prefill['no_of_nights'] ?? ($quotation['no_of_nights'] ?? 0)));
    if ($nights === 0 && is_array($row)) {
        $nights = max(0, (int) ($row['itinerary_total_nights'] ?? 0));
        if ($nights === 0 && !empty($payload['itinerary_dest_nights']) && is_array($payload['itinerary_dest_nights'])) {
            foreach ($payload['itinerary_dest_nights'] as $nightVal) {
                $nights += max(0, (int) $nightVal);
            }
        }
    }

    $adults = max(0, (int) ($prefill['no_of_adults'] ?? ($payload['tp_adults'] ?? 0)));
    $children = max(0, (int) ($prefill['no_of_children'] ?? ($payload['tp_children'] ?? 0)));

    $travelDate = trim((string) ($prefill['tentative_date'] ?? ($payload['tp_travel_date'] ?? '')));

    $hotelCategories = $payload['tp_hotel_category'] ?? [];
    if (!is_array($hotelCategories)) {
        $hotelCategories = $hotelCategories !== '' ? [$hotelCategories] : [];
    }

    return crmBuildSupplierQuoteMail([
        'guest_initial' => $initial,
        'guest_name' => $guestName,
        'adults' => $adults,
        'children' => $children,
        'destinations' => $destinations,
        'cities' => count($destinations),
        'nights' => $nights,
        'travel_date' => $travelDate,
        'hotel_categories' => $hotelCategories,
    ]);
}

function crmLeadFormatDateTime(?string $dateValue): string
{
    $dateValue = trim((string) $dateValue);
    if ($dateValue === '') {
        return '—';
    }
    $ts = strtotime($dateValue);
    if ($ts === false) {
        return '—';
    }

    return date('d/m/Y h:i A', $ts);
}

/**
 * Format traveller summary for quotation sidebar.
 * Example: Mr Raju Kumar | 02 AD + 02 CHD (04 / 12 Yrs)
 *
 * @param array<int,mixed> $childAges
 */
function crmLeadFormatTravellerInfo(string $initial, string $guestName, int $adults, int $children, array $childAges = []): string
{
    $initial = trim($initial);
    $guestName = trim($guestName);
    $guestLabel = trim($initial . ($initial !== '' && $guestName !== '' ? ' ' : '') . $guestName);
    if ($guestLabel === '') {
        $guestLabel = 'Guest';
    }

    $adults = max(0, $adults);
    $children = max(0, $children);
    if ($adults < 1 && $children < 1) {
        $adults = 1;
    }

    $paxParts = [];
    if ($adults > 0) {
        $paxParts[] = str_pad((string) $adults, 2, '0', STR_PAD_LEFT) . ' AD';
    }
    if ($children > 0) {
        $paxParts[] = str_pad((string) $children, 2, '0', STR_PAD_LEFT) . ' CHD';
    }
    $paxLabel = implode(' + ', $paxParts);

    $ageLabels = [];
    foreach ($childAges as $age) {
        if ($age === '' || $age === null) {
            continue;
        }
        $ageLabels[] = str_pad((string) max(0, (int) $age), 2, '0', STR_PAD_LEFT);
    }
    if ($children > 0 && !empty($ageLabels)) {
        $paxLabel .= ' (' . implode(' / ', $ageLabels) . ' Yrs)';
    }

    return $guestLabel . ' | ' . $paxLabel;
}

/**
 * Lead sidebar panel data for quotation generator.
 */
function crmLeadRowToSidebarPanel(mysqli $conn, array $row, array $destinationLookup = []): array
{
    $payload = [];
    if (!empty($row['payload_json'])) {
        $decoded = json_decode((string) $row['payload_json'], true);
        if (is_array($decoded)) {
            $payload = $decoded;
        }
    }

    $serviceLabels = crmLeadServiceLabelMap();
    $services = [];
    if (!empty($row['services'])) {
        $decodedServices = json_decode((string) $row['services'], true);
        if (is_array($decodedServices)) {
            foreach ($decodedServices as $svcKey) {
                $svcKey = trim((string) $svcKey);
                if ($svcKey === '') {
                    continue;
                }
                $services[] = $serviceLabels[$svcKey] ?? ucwords(str_replace('_', ' ', $svcKey));
            }
        }
    }

    $destNames = [];
    $destIds = $payload['tp_destination'] ?? [];
    if (!is_array($destIds)) {
        $destIds = $destIds !== '' ? [$destIds] : [];
    }
    foreach ($destIds as $destId) {
        $destId = (int) $destId;
        if ($destId > 0 && isset($destinationLookup[$destId])) {
            $destNames[] = $destinationLookup[$destId];
        }
    }
    if (empty($destNames) && !empty($payload['tp_arrival'])) {
        $destNames[] = trim((string) $payload['tp_arrival']);
    }

    $nights = max(0, (int) ($row['itinerary_total_nights'] ?? 0));
    if ($nights === 0 && !empty($payload['itinerary_dest_nights']) && is_array($payload['itinerary_dest_nights'])) {
        foreach ($payload['itinerary_dest_nights'] as $nightVal) {
            $nights += max(0, (int) $nightVal);
        }
    }

    $adults = max(0, (int) ($payload['tp_adults'] ?? 0));
    $children = max(0, (int) ($payload['tp_children'] ?? 0));
    if ($adults < 1) {
        $adults = 1;
    }

    $guestInitial = trim((string) ($payload['customer_initial'] ?? ''));
    $childAges = $payload['tp_children_ages'] ?? [];
    if (!is_array($childAges)) {
        $childAges = $childAges !== '' && $childAges !== null ? [$childAges] : [];
    }

    $leadId = (int) ($row['id'] ?? 0);
    $leadSource = trim((string) ($row['lead_source'] ?? ''));
    if ($leadSource === '' && !empty($payload['lead_source'])) {
        $leadSource = trim((string) $payload['lead_source']);
    }
    $referredBy = trim((string) ($row['referred_by'] ?? ''));
    if ($referredBy === '' && !empty($payload['referred_by'])) {
        $referredBy = trim((string) $payload['referred_by']);
    }
    $leadSourceDisplay = ($leadSource !== '' ? $leadSource : '—')
        . ' | '
        . ($referredBy !== '' ? $referredBy : '—');

    $assignTo = trim((string) ($row['assign_to'] ?? ''));
    if ($assignTo === '' && !empty($payload['assign_to'])) {
        $assignTo = trim((string) $payload['assign_to']);
    }

    $createdBy = trim((string) ($row['created_by_name'] ?? ''));
    $guestName = trim((string) ($row['customer_name'] ?? ''));
    $email = trim((string) ($row['customer_email'] ?? ''));
    $mobile = trim((string) ($row['customer_phone'] ?? ''));
    $notes = trim((string) ($payload['tp_notes'] ?? ''));

    $serviceType = !empty($services) ? implode(', ', $services) : '—';
    $queryType = $leadSource !== '' ? $leadSource : 'Client';
    $nightsLabel = $nights > 0 ? $nights . ' Night' . ($nights === 1 ? '' : 's') : '—';

    require_once __DIR__ . '/quotation_db.php';
    $storedStage = crmLeadNormalizeStage($row['stage'] ?? 'new_lead');
    $displayStage = $storedStage;
    if ($storedStage !== 'lost' && $leadId > 0) {
        $displayStage = crmLeadComputeStageFromFeatures([
            'has_quotation' => crmLeadHasLinkedQuotation($conn, $leadId, $row),
            'is_tour_confirmed' => crmLeadHasConfirmedQuotation($conn, $leadId, $row),
        ]);
    }

    return [
        'lead_id' => $leadId,
        'lead_uid' => trim((string) ($row['lead_uid'] ?? '')),
        'stats' => [
            'open_activities' => 0,
            'quotation_count' => crmLeadQuotationCount($conn, $leadId),
        ],
        'travel' => [
            'query_type' => $queryType,
            'service_type' => $serviceType,
            'destination' => !empty($destNames) ? implode(', ', $destNames) : '—',
            'travel_date' => crmLeadFormatDisplayDate((string) ($payload['tp_travel_date'] ?? '')),
            'nights' => $nightsLabel,
            'travellers' => crmLeadFormatTravellerInfo($guestInitial, $guestName, $adults, $children, $childAges),
        ],
        'basic' => [
            'guest_name' => $guestName !== '' ? $guestName : '—',
            'email' => $email !== '' ? $email : '—',
            'mobile' => $mobile !== '' ? $mobile : '—',
        ],
        'query' => [
            'uid' => trim((string) ($row['lead_uid'] ?? '')) !== '' ? (string) $row['lead_uid'] : '—',
            'service_label' => !empty($services) ? $services[0] : 'General Query',
            'stage' => crmLeadStageLabel($displayStage),
            'priority' => '—',
            'lead_source' => $leadSource !== '' ? $leadSource : '—',
            'referred_by' => $referredBy !== '' ? $referredBy : '—',
            'lead_source_display' => $leadSourceDisplay,
        ],
        'description' => $notes !== '' ? $notes : '—',
        'other' => [
            'status' => 'Open',
            'owner' => $assignTo !== '' ? $assignTo : '—',
            'created_by' => $createdBy !== '' ? $createdBy : ($assignTo !== '' ? $assignTo : '—'),
            'created_at' => crmLeadFormatDateTime((string) ($row['created_at'] ?? '')),
            'updated_at' => crmLeadFormatDateTime((string) ($row['updated_at'] ?? '')),
        ],
        'leads_url' => 'crm/leads.php',
    ];
}
