<?php

/**
 * Suppliers schema + helpers.
 */

function crmEnsureSupplierTables(mysqli $conn)
{
    $sql = "CREATE TABLE IF NOT EXISTS `crm_suppliers` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(190) NOT NULL,
        `website` VARCHAR(255) DEFAULT NULL,
        `city_id` INT UNSIGNED DEFAULT NULL,
        `city_name` VARCHAR(150) DEFAULT NULL,
        `country_name` VARCHAR(150) DEFAULT NULL,
        `physical_address` VARCHAR(255) DEFAULT NULL,
        `contacts_json` LONGTEXT,
        `supplier_of_json` LONGTEXT,
        `places_json` LONGTEXT,
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_supplier_name` (`name`),
        KEY `idx_supplier_city` (`city_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $conn->query($sql);

    $extraColumns = [
        'supplier_type' => "TEXT DEFAULT NULL AFTER `name`",
        'company_name' => "VARCHAR(190) DEFAULT NULL AFTER `supplier_type`",
        'internal_notes' => "TEXT DEFAULT NULL AFTER `places_json`",
    ];
    foreach ($extraColumns as $column => $ddl) {
        $check = $conn->query("SHOW COLUMNS FROM `crm_suppliers` LIKE '" . $conn->real_escape_string($column) . "'");
        if ($check && $check->num_rows === 0) {
            $conn->query("ALTER TABLE `crm_suppliers` ADD `" . $column . "` " . $ddl);
        }
    }

    // Multi-select supplier types need more than VARCHAR(60).
    $typeCol = $conn->query("SHOW COLUMNS FROM `crm_suppliers` LIKE 'supplier_type'");
    if ($typeCol && ($typeInfo = $typeCol->fetch_assoc())) {
        $typeDef = strtolower((string) ($typeInfo['Type'] ?? ''));
        if (strpos($typeDef, 'varchar') === 0) {
            $conn->query("ALTER TABLE `crm_suppliers` MODIFY `supplier_type` TEXT DEFAULT NULL");
        }
    }
}

/** @return array<string, string> */
function crmSupplierTypeMap()
{
    return [
        'visa' => 'Visa',
        'hotels' => 'Hotels',
        'land_package' => 'Land Package',
        'forex' => 'Forex',
        'train' => 'Train',
        'flight' => 'Flight',
        'travel_insurance' => 'Travel Insurance',
        'transfers' => 'Transfers',
        'tours' => 'Tours',
        'cruise' => 'Cruise',
    ];
}

/** @return array<string, string> */
function crmSupplierServiceMap()
{
    return [
        'visa' => 'Visa',
        'hotels' => 'Hotels',
        'land_package' => 'Land Package',
        'forex' => 'Forex',
        'train' => 'Train',
        'flight' => 'Flight',
        'travel_insurance' => 'Travel Insurance',
        'transfers' => 'Transfers',
        'tours' => 'Tours',
        'cruise' => 'Cruise',
    ];
}

function crmSupplierNormalizeContacts($raw)
{
    $out = [];
    if (!is_array($raw)) {
        return $out;
    }
    foreach ($raw as $row) {
        if (!is_array($row)) {
            continue;
        }
        $contactName = trim((string) ($row['contact_name'] ?? ''));
        $email = trim((string) ($row['email'] ?? ''));
        $mobile = trim((string) ($row['mobile'] ?? ''));
        $designation = trim((string) ($row['designation'] ?? ''));
        $isPrimary = !empty($row['is_primary']) ? 1 : 0;
        if ($contactName === '' && $email === '' && $mobile === '') {
            continue;
        }
        $out[] = [
            'contact_name' => $contactName,
            'designation' => $designation,
            'email' => $email,
            'mobile' => $mobile,
            'is_primary' => $isPrimary,
        ];
    }
    if ($out) {
        $hasPrimary = false;
        foreach ($out as $c) {
            if (!empty($c['is_primary'])) {
                $hasPrimary = true;
                break;
            }
        }
        if (!$hasPrimary) {
            $out[0]['is_primary'] = 1;
        }
    }
    return $out;
}

function crmSupplierNormalizePlaces($raw)
{
    $out = [];
    if (!is_array($raw)) {
        return $out;
    }
    foreach ($raw as $row) {
        if (!is_array($row)) {
            continue;
        }
        $id = (int) ($row['id'] ?? 0);
        $name = trim((string) ($row['name'] ?? ''));
        $country = trim((string) ($row['country'] ?? ''));
        if ($name === '') {
            continue;
        }
        $label = $name;
        if ($country !== '') {
            $label = $name . ' - ' . $country;
        }
        $out[] = [
            'id' => $id,
            'name' => $name,
            'country' => $country,
            'label' => $label,
        ];
    }
    return $out;
}

function crmSupplierNormalizeSupplierOf($raw)
{
    $map = crmSupplierServiceMap();
    $out = [];
    if (!is_array($raw)) {
        return $out;
    }
    foreach ($raw as $key) {
        $key = trim((string) $key);
        if ($key !== '' && isset($map[$key]) && !in_array($key, $out, true)) {
            $out[] = $key;
        }
    }
    return $out;
}

/**
 * Normalize supplier_type column (legacy single string OR JSON array).
 *
 * @param mixed $raw
 * @return array<int, string>
 */
function crmSupplierNormalizeTypes($raw)
{
    $map = crmSupplierTypeMap();
    $legacyMap = [
        'dmc' => 'tours',
        'tour_operator' => 'tours',
        'hotel_supplier' => 'hotels',
        'transport_supplier' => 'transfers',
        'visa_agent' => 'visa',
        'activity_supplier' => 'tours',
        'other' => '',
        'custom' => '',
    ];
    $keys = [];

    if (is_string($raw)) {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }
        if ($raw[0] === '[') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $keys = $decoded;
            }
        } elseif (strpos($raw, 'custom:') === 0) {
            return [];
        } else {
            $keys = preg_split('/\s*,\s*/', $raw) ?: [];
        }
    } elseif (is_array($raw)) {
        $keys = $raw;
    }

    $out = [];
    foreach ($keys as $key) {
        $key = trim((string) $key);
        if ($key === '') {
            continue;
        }
        if (isset($legacyMap[$key])) {
            $key = $legacyMap[$key];
        }
        if ($key !== '' && isset($map[$key]) && !in_array($key, $out, true)) {
            $out[] = $key;
        }
    }
    return $out;
}

/**
 * @param array<int, string> $types
 */
function crmSupplierTypesLabel(array $types): string
{
    $map = crmSupplierTypeMap();
    $labels = [];
    foreach ($types as $key) {
        if (isset($map[$key])) {
            $labels[] = $map[$key];
        }
    }
    return $labels ? implode(', ', $labels) : '—';
}

/**
 * Encode types for DB storage.
 *
 * @param array<int, string> $types
 */
function crmSupplierTypesToStorage(array $types): string
{
    $types = crmSupplierNormalizeTypes($types);
    return json_encode($types, JSON_UNESCAPED_UNICODE);
}

function crmSupplierPlacesLabel(array $places)
{
    if (!$places) {
        return '—';
    }
    $labels = [];
    foreach ($places as $p) {
        if (!empty($p['label'])) {
            $labels[] = $p['label'];
        } elseif (!empty($p['name'])) {
            $labels[] = $p['name'];
        }
    }
    return $labels ? implode(', ', $labels) : '—';
}

function crmSupplierPrimaryContact(array $contacts, $field)
{
    foreach ($contacts as $c) {
        $val = trim((string) ($c[$field] ?? ''));
        if ($val !== '') {
            return $val;
        }
    }
    return '';
}

function crmSupplierCityLine($cityName, $countryName)
{
    $cityName = trim((string) $cityName);
    $countryName = trim((string) $countryName);
    if ($cityName === '' && $countryName === '') {
        return '';
    }
    if ($cityName !== '' && $countryName !== '') {
        return $cityName . ', ' . $countryName;
    }
    return $cityName !== '' ? $cityName : $countryName;
}

/** @return array<string, mixed>|null */
function crmSupplierMailCatalogItemFromRow(array $row): ?array
{
    $contacts = crmSupplierNormalizeContacts(json_decode((string) ($row['contacts_json'] ?? '[]'), true) ?: []);
    $places = crmSupplierNormalizePlaces(json_decode((string) ($row['places_json'] ?? '[]'), true) ?: []);
    $email = crmSupplierPrimaryContact($contacts, 'email');
    if ($email === '') {
        return null;
    }

    return [
        'id' => (int) ($row['id'] ?? 0),
        'name' => (string) ($row['name'] ?? ''),
        'email' => $email,
        'contact_name' => crmSupplierPrimaryContact($contacts, 'contact_name'),
        'places_label' => crmSupplierPlacesLabel($places),
        'places' => $places,
    ];
}

/** @return int[] */
function crmDestinationIdsFromText(mysqli $conn, string $destinationText): array
{
    $ids = [];
    $parts = preg_split('/[,;|]+/', $destinationText) ?: [];
    foreach ($parts as $part) {
        $name = trim((string) $part);
        if ($name === '') {
            continue;
        }
        $stmt = $conn->prepare('SELECT id FROM destinations WHERE is_active = 1 AND LOWER(name) = LOWER(?) LIMIT 1');
        if (!$stmt) {
            continue;
        }
        $stmt->bind_param('s', $name);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        if ($row && (int) ($row['id'] ?? 0) > 0) {
            $ids[] = (int) $row['id'];
        }
    }

    return array_values(array_unique($ids));
}

function crmSupplierMatchesDestination(array $places, array $destIds, array $destNamesLower, string $destinationTextLower): bool
{
    if (!$places) {
        return false;
    }

    foreach ($places as $place) {
        if (!is_array($place)) {
            continue;
        }
        $placeId = (int) ($place['id'] ?? 0);
        $placeName = strtolower(trim((string) ($place['name'] ?? '')));
        if ($placeId > 0 && in_array($placeId, $destIds, true)) {
            return true;
        }
        foreach ($destNamesLower as $destName) {
            if ($destName === '') {
                continue;
            }
            if ($placeName === $destName || strpos($placeName, $destName) !== false || strpos($destName, $placeName) !== false) {
                return true;
            }
        }
        if ($placeName !== '' && $destinationTextLower !== '' && strpos($destinationTextLower, $placeName) !== false) {
            return true;
        }
    }

    return false;
}

/** @return array<int, array{id:int,name:string}> */
function crmSuppliersForHotelSelect(mysqli $conn): array
{
    crmEnsureSupplierTables($conn);
    $out = [];
    $res = $conn->query('SELECT `id`, `name`, `supplier_of_json`, `supplier_type`
        FROM `crm_suppliers`
        WHERE `is_active` = 1
        ORDER BY `name` ASC');
    if (!$res) {
        return $out;
    }

    while ($row = $res->fetch_assoc()) {
        $supplierOf = crmSupplierNormalizeSupplierOf(
            json_decode((string) ($row['supplier_of_json'] ?? '[]'), true) ?: []
        );
        $types = crmSupplierNormalizeTypes($row['supplier_type'] ?? '');
        $isHotelSupplier = in_array('hotels', $supplierOf, true)
            || in_array('hotels', $types, true)
            || ($supplierOf === [] && $types === []);
        if (!$isHotelSupplier) {
            continue;
        }
        $name = trim((string) ($row['name'] ?? ''));
        $id = (int) ($row['id'] ?? 0);
        if ($id < 1 || $name === '') {
            continue;
        }
        $out[] = [
            'id' => $id,
            'name' => $name,
        ];
    }

    return $out;
}

/** @return array<int, array<string, mixed>> */
function crmSuppliersMailCatalog(mysqli $conn): array
{
    $rows = [];
    $res = $conn->query('SELECT * FROM crm_suppliers WHERE is_active = 1 ORDER BY name ASC');
    if (!$res) {
        return $rows;
    }

    while ($row = $res->fetch_assoc()) {
        $item = crmSupplierMailCatalogItemFromRow($row);
        if ($item) {
            $rows[] = $item;
        }
    }

    return $rows;
}

/** @return array<int, array<string, mixed>> */
function crmSuppliersForDestination(mysqli $conn, string $destinationText): array
{
    $destinationText = trim($destinationText);
    if ($destinationText === '' || $destinationText === '—') {
        return [];
    }

    $destIds = crmDestinationIdsFromText($conn, $destinationText);
    $destNamesLower = [];
    foreach (preg_split('/[,;|]+/', $destinationText) ?: [] as $part) {
        $part = strtolower(trim((string) $part));
        if ($part !== '') {
            $destNamesLower[] = $part;
        }
    }
    $destinationTextLower = strtolower($destinationText);

    $matched = [];
    foreach (crmSuppliersMailCatalog($conn) as $supplier) {
        $places = is_array($supplier['places'] ?? null) ? $supplier['places'] : [];
        if (crmSupplierMatchesDestination($places, $destIds, $destNamesLower, $destinationTextLower)) {
            unset($supplier['places']);
            $matched[] = $supplier;
        }
    }

    return $matched;
}

/**
 * Active suppliers for autocomplete (optional name query + service key filter).
 *
 * @return array<int, array{id:int,name:string,company_name:string,types:array<int,string>}>
 */
function crmSuppliersSuggest(mysqli $conn, string $query = '', string $serviceKey = '', int $limit = 25): array
{
    crmEnsureSupplierTables($conn);

    $query = trim($query);
    $serviceKey = trim(strtolower($serviceKey));
    $limit = max(1, min(50, $limit));
    $validServices = array_keys(crmSupplierServiceMap());

    $sql = 'SELECT `id`, `name`, `company_name`, `supplier_type`, `supplier_of_json`
            FROM `crm_suppliers`
            WHERE `is_active` = 1';
    $types = '';
    $params = [];

    if ($query !== '') {
        $like = '%' . $query . '%';
        $sql .= ' AND (`name` LIKE ? OR `company_name` LIKE ?)';
        $types .= 'ss';
        $params[] = $like;
        $params[] = $like;
    }

    $sql .= ' ORDER BY `name` ASC LIMIT ' . (int) $limit;

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }

    if ($types !== '') {
        $bind = [$types];
        foreach ($params as $i => $val) {
            $bind[] = &$params[$i];
        }
        call_user_func_array([$stmt, 'bind_param'], $bind);
    }

    if (!$stmt->execute()) {
        $stmt->close();
        return [];
    }

    $res = $stmt->get_result();
    $out = [];
    while ($res && ($row = $res->fetch_assoc())) {
        $name = trim((string) ($row['name'] ?? ''));
        $id = (int) ($row['id'] ?? 0);
        if ($id < 1 || $name === '') {
            continue;
        }

        $typesList = crmSupplierNormalizeTypes($row['supplier_type'] ?? '');
        $supplierOf = crmSupplierNormalizeSupplierOf(
            json_decode((string) ($row['supplier_of_json'] ?? '[]'), true) ?: []
        );
        $combined = array_values(array_unique(array_merge($typesList, $supplierOf)));

        if ($serviceKey !== '' && in_array($serviceKey, $validServices, true)) {
            if ($combined !== [] && !in_array($serviceKey, $combined, true)) {
                continue;
            }
        }

        $out[] = [
            'id' => $id,
            'name' => $name,
            'company_name' => trim((string) ($row['company_name'] ?? '')),
            'types' => $combined,
        ];
    }
    $stmt->close();

    return $out;
}
