<?php

/**
 * Convert legacy dashboard quotation JSON into CRM quotation-generator shape.
 */

if (!function_exists('crmLegacyNormalizeDateValue')) {
    function crmLegacyNormalizeDateValue($raw): string
    {
        $raw = trim((string) $raw);
        if ($raw === '' || $raw === '0000-00-00') {
            return '';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $raw)) {
            return substr($raw, 0, 10);
        }
        if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $raw, $m)) {
            return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
        }
        $ts = strtotime($raw);

        return ($ts !== false) ? date('Y-m-d', $ts) : '';
    }
}

if (!function_exists('crmLegacyNormalizeFlightRow')) {
    function crmLegacyNormalizeFlightRow($row): array
    {
        if (!is_array($row)) {
            return [];
        }

        $depDate = crmLegacyNormalizeDateValue($row['dep_date'] ?? ($row['date'] ?? ''));
        $arrDate = crmLegacyNormalizeDateValue($row['arr_date'] ?? ($row['arv_date'] ?? $depDate));

        return [
            'from' => trim((string) ($row['from'] ?? '')),
            'to' => trim((string) ($row['to'] ?? '')),
            'name' => trim((string) ($row['name'] ?? '')),
            'fl_tr_no' => trim((string) ($row['fl_tr_no'] ?? ($row['pnr'] ?? ($row['fno'] ?? '')))),
            'dep_date' => $depDate,
            'dep_time' => trim((string) ($row['dep_time'] ?? ($row['time'] ?? ''))),
            'arr_date' => $arrDate,
            'arr_time' => trim((string) ($row['arr_time'] ?? ($row['arv_time'] ?? ''))),
            'fare' => $row['fare'] ?? ($row['amount'] ?? ''),
            'supplier_id' => $row['supplier_id'] ?? '',
            'supplier' => trim((string) ($row['supplier'] ?? '')),
            'layover_time' => trim((string) ($row['layover_time'] ?? '')),
            'layover_at' => trim((string) ($row['layover_at'] ?? '')),
            'journey_start' => !empty($row['journey_start']),
            'journey_label' => trim((string) ($row['journey_label'] ?? '')),
            '_legacy_type' => trim((string) ($row['_legacy_type'] ?? '')),
        ];
    }
}

if (!function_exists('crmLegacyNormalizeFlightsJson')) {
    function crmLegacyNormalizeFlightsJson($raw): array
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
        } else {
            $decoded = $raw;
        }
        if (!is_array($decoded)) {
            return [];
        }

        $out = [];
        foreach ($decoded as $row) {
            $norm = crmLegacyNormalizeFlightRow($row);
            if ($norm['from'] !== '' || $norm['to'] !== '' || $norm['name'] !== '' || $norm['fl_tr_no'] !== '') {
                $out[] = $norm;
            }
        }

        return $out;
    }
}

if (!function_exists('crmLegacyNormalizeHotelRow')) {
    function crmLegacyNormalizeHotelRow($row): array
    {
        if (!is_array($row)) {
            return [];
        }

        return [
            'city' => trim((string) ($row['city'] ?? '')),
            'city_id' => $row['city_id'] ?? '',
            'hotel_id' => $row['hotel_id'] ?? '',
            'name' => trim((string) ($row['name'] ?? ($row['hotel_name'] ?? ''))),
            'room_type' => trim((string) ($row['room_type'] ?? '')),
            'rooms' => $row['rooms'] ?? '',
            'meal_plan' => trim((string) ($row['meal_plan'] ?? ($row['meal'] ?? 'CP'))),
            'nights' => $row['nights'] ?? '',
            'checkin' => crmLegacyNormalizeDateValue($row['checkin'] ?? ($row['check_in'] ?? '')),
            'checkout' => crmLegacyNormalizeDateValue($row['checkout'] ?? ($row['check_out'] ?? '')),
            'rate' => $row['rate'] ?? ($row['amount'] ?? ''),
            'supplier_id' => $row['supplier_id'] ?? '',
            'supplier' => trim((string) ($row['supplier'] ?? ($row['supplier_name'] ?? ''))),
        ];
    }
}

if (!function_exists('crmLegacyNormalizeHotelsJson')) {
    function crmLegacyNormalizeHotelsJson($raw): array
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
        } else {
            $decoded = $raw;
        }
        if (!is_array($decoded)) {
            return [];
        }

        if (isset($decoded['categories']) && is_array($decoded['categories'])) {
            $categories = [];
            foreach ($decoded['categories'] as $idx => $cat) {
                if (!is_array($cat)) {
                    continue;
                }
                $hotels = [];
                foreach (($cat['hotels'] ?? []) as $hotel) {
                    $norm = crmLegacyNormalizeHotelRow($hotel);
                    if ($norm['name'] !== '' || $norm['city'] !== '') {
                        $hotels[] = $norm;
                    }
                }
                $categories[] = [
                    'id' => (string) ($cat['id'] ?? ('opt_' . ($idx + 1))),
                    'label' => (string) ($cat['label'] ?? ('Option ' . ($idx + 1))),
                    'hotels' => $hotels,
                ];
            }

            return [
                'categories' => $categories,
                'active_category_id' => (string) ($decoded['active_category_id'] ?? ($categories[0]['id'] ?? 'opt_1')),
            ];
        }

        $hotels = [];
        foreach ($decoded as $hotel) {
            $norm = crmLegacyNormalizeHotelRow($hotel);
            if ($norm['name'] !== '' || $norm['city'] !== '') {
                $hotels[] = $norm;
            }
        }

        return $hotels;
    }
}

if (!function_exists('crmLegacyNormalizeItineraryRow')) {
    function crmLegacyNormalizeItineraryRow($row): array
    {
        if (!is_array($row)) {
            return [];
        }

        $title = trim((string) ($row['title'] ?? ($row['caption'] ?? '')));
        if ($title === '' && !empty($row['day'])) {
            $title = 'Day ' . (int) $row['day'];
        }
        if ($title === '' && !empty($row['date'])) {
            $title = trim((string) $row['date']);
        }

        $description = (string) ($row['description'] ?? ($row['todo'] ?? ''));
        $image = trim((string) ($row['image'] ?? ($row['img'] ?? ($row['image_url'] ?? ''))));

        return [
            'title' => $title,
            'description' => $description,
            'image' => $image,
        ];
    }
}

if (!function_exists('crmLegacyNormalizeItineraryJson')) {
    function crmLegacyNormalizeItineraryJson($raw): array
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
        } else {
            $decoded = $raw;
        }
        if (!is_array($decoded)) {
            return [];
        }

        $out = [];
        foreach ($decoded as $row) {
            $norm = crmLegacyNormalizeItineraryRow($row);
            if ($norm['title'] !== '' || trim(strip_tags($norm['description'])) !== '') {
                $out[] = $norm;
            }
        }

        return $out;
    }
}

if (!function_exists('crmLegacyNormalizeCostSheet')) {
    function crmLegacyNormalizeCostSheet($raw, float $pricePerAdult = 0.0): array
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
        } else {
            $decoded = $raw;
        }
        if (!is_array($decoded)) {
            return [];
        }

        if (isset($decoded['fixed']) || isset($decoded['options']) || isset($decoded['custom'])) {
            return $decoded;
        }

        $fixed = [];
        if (isset($decoded['package']) && is_array($decoded['package'])) {
            $pkg = $decoded['package'];
            if (isset($pkg['flight_cost']) && $pkg['flight_cost'] !== '') {
                $fixed['flight_train'] = $pkg['flight_cost'];
            }
            if (isset($pkg['land_cost']) && $pkg['land_cost'] !== '') {
                $fixed['land_package'] = $pkg['land_cost'];
            }
        }

        $profitAmount = '';
        if (isset($decoded['profit_figure']) && $decoded['profit_figure'] !== '') {
            $profitAmount = (string) $decoded['profit_figure'];
        }

        $sheet = [
            'fixed' => $fixed,
            'custom' => [],
            'user_edited' => [],
            'legacy_import' => true,
        ];

        if ($profitAmount !== '') {
            $sheet['profit_amount'] = $profitAmount;
        }
        if ($pricePerAdult > 0) {
            $sheet['price_per_adult'] = $pricePerAdult;
            $sheet['price_per_adult_edited'] = 1;
        }
        if (isset($decoded['cost_price'])) {
            $sheet['legacy_cost_price'] = $decoded['cost_price'];
        }
        if (isset($decoded['selling_price'])) {
            $sheet['legacy_selling_price'] = $decoded['selling_price'];
        }

        return $sheet;
    }
}

if (!function_exists('crmLegacyNormalizeQuotationRowForCrm')) {
    /**
     * @param array<string,mixed> $quotation
     * @return array<string,mixed>
     */
    function crmLegacyNormalizeQuotationRowForCrm(array $quotation): array
    {
        $flights = crmLegacyNormalizeFlightsJson($quotation['flights_json'] ?? '[]');
        $hotels = crmLegacyNormalizeHotelsJson($quotation['hotels_json'] ?? '[]');
        $itinerary = crmLegacyNormalizeItineraryJson($quotation['itinerary_json'] ?? '[]');
        $costSheet = crmLegacyNormalizeCostSheet(
            $quotation['cost_sheet_json'] ?? '{}',
            (float) ($quotation['price_per_adult'] ?? 0)
        );

        $quotation['flights_json'] = json_encode($flights, JSON_UNESCAPED_UNICODE) ?: '[]';
        $quotation['hotels_json'] = json_encode($hotels, JSON_UNESCAPED_UNICODE) ?: '[]';
        $quotation['itinerary_json'] = json_encode($itinerary, JSON_UNESCAPED_UNICODE) ?: '[]';
        $quotation['cost_sheet_json'] = json_encode($costSheet, JSON_UNESCAPED_UNICODE) ?: '{}';

        $nights = max(0, (int) ($quotation['no_of_nights'] ?? 0));
        if ($nights <= 0 && count($itinerary) > 1) {
            $quotation['no_of_nights'] = max(0, count($itinerary) - 1);
        }

        return $quotation;
    }
}

if (!function_exists('crmLeadsEnrichDisplayFromQuotations')) {
    /**
     * Fill destination / travel date on leads imported without intake payload fields.
     *
     * @param array<int, array<string, mixed>> $leadRows
     */
    function crmLeadsEnrichDisplayFromQuotations(mysqli $conn, array &$leadRows): void
    {
        if (empty($leadRows)) {
            return;
        }

        crmEnsureQuotationTables($conn);

        foreach ($leadRows as &$lead) {
            $needsDest = trim((string) ($lead['travel_dest_display'] ?? '')) === '';
            $needsDate = trim((string) ($lead['travel_date_text'] ?? '')) === '';
            if (!$needsDest && !$needsDate) {
                continue;
            }

            $qid = (int) ($lead['latest_quotation_id'] ?? 0);
            $qRow = null;

            if ($qid > 0) {
                $res = $conn->query('SELECT `destination`, `tentative_date`, `no_of_nights` FROM `crm_quotations` WHERE `id` = ' . $qid . ' LIMIT 1');
                $qRow = ($res && ($row = $res->fetch_assoc())) ? $row : null;
            }

            if (!$qRow) {
                $phone = preg_replace('/\D+/', '', (string) ($lead['customer_phone'] ?? ''));
                if (strlen($phone) > 10) {
                    $phone = substr($phone, -10);
                }
                if ($phone !== '') {
                    $stmt = $conn->prepare(
                        'SELECT `id`, `destination`, `tentative_date`, `no_of_nights`
                         FROM `crm_quotations`
                         WHERE REPLACE(REPLACE(REPLACE(`mobile_no`, " ", ""), "-", ""), "+", "") LIKE ?
                         ORDER BY `id` DESC LIMIT 1'
                    );
                    if ($stmt) {
                        $like = '%' . $phone;
                        $stmt->bind_param('s', $like);
                        $stmt->execute();
                        $res = $stmt->get_result();
                        $qRow = $res ? $res->fetch_assoc() : null;
                        $stmt->close();
                        if ($qRow) {
                            $lead['latest_quotation_id'] = (int) ($qRow['id'] ?? 0);
                        }
                    }
                }
            }

            if (!$qRow) {
                continue;
            }

            $destination = trim((string) ($qRow['destination'] ?? ''));
            $nights = max(0, (int) ($qRow['no_of_nights'] ?? 0));
            if ($needsDest && $destination !== '') {
                $destDisplay = strtoupper($destination);
                if ($nights > 0) {
                    $destDisplay .= '-' . str_pad((string) $nights, 2, '0', STR_PAD_LEFT) . ' N';
                }
                $lead['travel_dest_display'] = $destDisplay;
                $lead['travel_destination_text'] = $destDisplay;
                $lead['destination_names'] = [$destination];
                $lead['destination_segments'] = [['name' => $destination, 'nights' => $nights]];
                if ($nights > 0) {
                    $lead['itinerary_total_nights'] = $nights;
                }
            }

            if ($needsDate && !empty($qRow['tentative_date']) && $qRow['tentative_date'] !== '0000-00-00') {
                $ts = strtotime((string) $qRow['tentative_date']);
                if ($ts !== false) {
                    $lead['travel_date_text'] = date('d', $ts) . '-' . date('M', $ts) . '-' . date('y', $ts);
                }
            }
        }
        unset($lead);
    }
}

if (!function_exists('crmLegacyRepairStoredQuotations')) {
    /** @return array{updated:int,failed:int,errors:array<int,string>} */
    function crmLegacyRepairStoredQuotations(mysqli $conn): array
    {
        crmEnsureQuotationTables($conn);

        $stats = ['updated' => 0, 'failed' => 0, 'errors' => []];
        $res = $conn->query('SELECT * FROM `crm_quotations` ORDER BY `id` ASC');
        if (!$res) {
            $stats['failed']++;
            $stats['errors'][] = 'Could not read quotations.';

            return $stats;
        }

        $sql = 'UPDATE `crm_quotations`
            SET `flights_json` = ?, `hotels_json` = ?, `itinerary_json` = ?, `cost_sheet_json` = ?, `no_of_nights` = ?
            WHERE `id` = ? LIMIT 1';
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            $stats['failed']++;
            $stats['errors'][] = 'Could not prepare quotation repair update.';

            return $stats;
        }

        while ($row = $res->fetch_assoc()) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $norm = crmLegacyNormalizeQuotationRowForCrm($row);
            $nights = max(0, (int) ($norm['no_of_nights'] ?? 0));
            $stmt->bind_param(
                'ssssii',
                $norm['flights_json'],
                $norm['hotels_json'],
                $norm['itinerary_json'],
                $norm['cost_sheet_json'],
                $nights,
                $id
            );
            if ($stmt->execute()) {
                $stats['updated']++;
            } else {
                $stats['failed']++;
                $stats['errors'][] = 'Quotation #' . $id . ': ' . $conn->error;
            }
        }

        $stmt->close();
        $res->free();

        return $stats;
    }
}
