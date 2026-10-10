<?php

require_once __DIR__ . '/city_coordinates.php';

/**
 * Next confirmed tours for the dashboard map.
 * Uses crm_quotations (confirmed bookings) and the linked lead for departure city.
 *
 * @return array{
 *   ok: bool,
 *   error: string,
 *   limit: int,
 *   total: int,
 *   tours: array<int, array<string, mixed>>,
 *   unmapped: array<int, array<string, mixed>>
 * }
 */
function dashUpcomingToursMap(mysqli $conn, int $limit = 5): array
{
    $empty = [
        'ok' => true,
        'error' => '',
        'limit' => $limit,
        'total' => 0,
        'tours' => [],
        'unmapped' => [],
    ];

    $check = $conn->query("SHOW TABLES LIKE 'crm_quotations'");
    if (!$check || $check->num_rows === 0) {
        $empty['ok'] = false;
        $empty['error'] = 'Confirmed tour records are not available.';
        return $empty;
    }

    $sql = 'SELECT q.`id`, q.`guest_name`, q.`destination`, q.`tentative_date`,
                   q.`no_of_adults`, q.`no_of_children`, q.`no_of_infants`,
                   q.`tour_confirmed`, q.`flights_json`, q.`lead_id`, l.`payload_json`
            FROM `crm_quotations` q
            LEFT JOIN `crm_leads` l ON l.`id` = q.`lead_id`
            WHERE q.`tour_confirmed` = 1
              AND q.`tentative_date` IS NOT NULL
              AND q.`tentative_date` >= CURDATE()
            ORDER BY q.`tentative_date` ASC, q.`id` ASC
            LIMIT ' . (int) $limit;

    $res = $conn->query($sql);
    if (!$res) {
        $empty['ok'] = false;
        $empty['error'] = 'Upcoming tours could not be loaded.';
        return $empty;
    }

    $tours = [];
    $unmapped = [];
    while ($row = $res->fetch_assoc()) {
        $card = dashMapTourCard($row);
        if ($card['from'] && $card['to']) {
            $tours[] = $card;
        } else {
            unset($card['from'], $card['to']);
            $unmapped[] = $card;
        }
    }

    $empty['tours'] = $tours;
    $empty['unmapped'] = $unmapped;
    $empty['total'] = count($tours) + count($unmapped);
    return $empty;
}

/**
 * @param array<string, mixed> $row
 * @return array<string, mixed>
 */
function dashMapTourCard(array $row): array
{
    $payload = [];
    if (!empty($row['payload_json'])) {
        $decoded = json_decode((string) $row['payload_json'], true);
        if (is_array($decoded)) {
            $payload = $decoded;
        }
    }
    $flights = [];
    if (!empty($row['flights_json'])) {
        $decodedFlights = json_decode((string) $row['flights_json'], true);
        if (is_array($decodedFlights)) {
            $flights = $decodedFlights;
        }
    }

    $firstFlight = isset($flights[0]) && is_array($flights[0]) ? $flights[0] : [];
    $departureRaw = trim((string) ($firstFlight['from'] ?? ''));
    if ($departureRaw === '') {
        $departureRaw = trim((string) ($payload['tp_departure'] ?? ''));
    }

    $destinationRaw = trim((string) ($row['destination'] ?? ''));
    if ($destinationRaw === '') {
        $destinationRaw = trim((string) ($payload['tp_arrival'] ?? $payload['tp_destination'] ?? ''));
    }
    if ($destinationRaw === '') {
        $last = end($flights);
        if (is_array($last)) {
            $destinationRaw = trim((string) ($last['to'] ?? ''));
        }
    }
    $destinationCity = $destinationRaw;
    if (preg_match('/[,;|]/', $destinationCity)) {
        $parts = preg_split('/[,;|]/', $destinationCity) ?: [];
        $destinationCity = trim((string) ($parts[0] ?? $destinationCity));
    }

    $adults = max(0, (int) ($row['no_of_adults'] ?? 0));
    $children = max(0, (int) ($row['no_of_children'] ?? 0));
    $infants = max(0, (int) ($row['no_of_infants'] ?? 0));
    $guest = trim((string) ($row['guest_name'] ?? ''));
    $destinationLabel = $destinationRaw !== '' ? $destinationRaw : 'Destination pending';
    $name = $guest !== '' ? $guest : $destinationLabel;
    $date = (string) ($row['tentative_date'] ?? '');
    $dateLabel = $date !== '' ? date('d M Y', strtotime($date)) : '';

    $from = $departureRaw !== '' ? dashMapCityCoordinate($departureRaw) : null;
    $to = $destinationCity !== '' ? dashMapCityCoordinate($destinationCity) : null;
    if ($from && $to && abs($from['lat'] - $to['lat']) < 0.01 && abs($from['lng'] - $to['lng']) < 0.01) {
        $to = null;
    }

    $tourId = (int) ($row['id'] ?? 0);
    return [
        'id' => $tourId,
        'detail_url' => 'crm/quotation_generator.php?id=' . $tourId,
        'name' => $name,
        'departure_date' => $dateLabel,
        'departure_city' => $from['label'] ?? dashMapCityDisplay($departureRaw),
        'destination' => $destinationLabel,
        'travellers' => $adults + $children + $infants,
        'travellers_label' => dashMapTravellerLabel($adults, $children, $infants),
        'status' => ((int) ($row['tour_confirmed'] ?? 0) === 1) ? 'Confirmed' : 'Pending',
        'from' => $from,
        'to' => $to,
        'reason' => dashMapMissingReason($departureRaw, $destinationCity, $from, $to),
    ];
}

function dashMapTravellerLabel(int $adults, int $children, int $infants): string
{
    $parts = [];
    if ($adults > 0) {
        $parts[] = $adults . ' adult' . ($adults === 1 ? '' : 's');
    }
    if ($children > 0) {
        $parts[] = $children . ' child' . ($children === 1 ? '' : 'ren');
    }
    if ($infants > 0) {
        $parts[] = $infants . ' infant' . ($infants === 1 ? '' : 's');
    }
    return $parts ? implode(', ', $parts) : 'Traveller count not set';
}

function dashMapMissingReason(string $departureRaw, string $destinationRaw, ?array $from, ?array $to): string
{
    if ($departureRaw === '') {
        return 'Departure city is not set on this booking.';
    }
    if ($destinationRaw === '') {
        return 'Destination is not set on this booking.';
    }
    if ($from && $to === null && dashMapNormalizeCity($departureRaw) === dashMapNormalizeCity($destinationRaw)) {
        return 'Departure and destination resolve to the same place.';
    }
    if ($from === null || $to === null) {
        return 'Coordinates are not available for this route.';
    }
    return '';
}
