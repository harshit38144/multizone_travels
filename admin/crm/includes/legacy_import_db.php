<?php

/**
 * Legacy dashboard (u560130840_dashboard) → current CRM tables.
 * No schema changes; legacy IDs stored in JSON / notes for idempotent re-runs.
 */

require_once __DIR__ . '/supplier_db.php';
require_once __DIR__ . '/quotation_db.php';
require_once __DIR__ . '/lead_uid.php';
require_once __DIR__ . '/legacy_sql_staging.php';
require_once __DIR__ . '/legacy_quotation_normalize.php';

if (!function_exists('crmLegacyImportProbe')) {
    /** @return array{connected:bool,source:string,file:string,database:string,counts:array<string,int>,error:?string} */
    function crmLegacyImportProbe(?mysqli $crmConn = null): array
    {
        $filePath = crmLegacySqlFilePath();
        if ($filePath === '') {
            return [
                'connected' => false,
                'source' => 'file',
                'file' => '',
                'database' => 'Same CRM database (temporary staging tables)',
                'counts' => [],
                'error' => 'Legacy SQL file not found. Upload u560130840_dashboard.sql to your project root via FTP/File Manager.',
            ];
        }

        $counts = [];
        if ($crmConn instanceof mysqli && !$crmConn->connect_errno && crmLegacyStagingTablesExist($crmConn)) {
            $counts = crmLegacyStagingCounts($crmConn);
        }
        if ((int) ($counts['quotation'] ?? 0) === 0) {
            $counts = crmLegacyEstimateCountsFromFile($filePath);
        }

        return [
            'connected' => true,
            'source' => 'file',
            'file' => basename($filePath),
            'database' => 'Same CRM database (no second database)',
            'counts' => $counts,
            'error' => null,
        ];
    }
}

if (!function_exists('crmLegacyNormalizePhone')) {
    function crmLegacyNormalizePhone(?string $phone): string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if ($digits === null) {
            return '';
        }
        if (strlen($digits) > 10) {
            return substr($digits, -10);
        }

        return $digits;
    }
}

if (!function_exists('crmLegacyJsonOrFallback')) {
    function crmLegacyJsonOrFallback($raw, string $fallback = '[]'): string
    {
        if (is_array($raw)) {
            $encoded = json_encode($raw, JSON_UNESCAPED_UNICODE);
            return ($encoded === false) ? $fallback : $encoded;
        }
        $str = trim((string) $raw);
        if ($str === '') {
            return $fallback;
        }
        $decoded = json_decode($str, true);
        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            return $fallback;
        }
        $encoded = json_encode($decoded, JSON_UNESCAPED_UNICODE);

        return ($encoded === false) ? $fallback : $encoded;
    }
}

if (!function_exists('crmLegacyMergeFlightsAndTrains')) {
    function crmLegacyMergeFlightsAndTrains(?string $flightsRaw, ?string $trainsRaw): string
    {
        $flights = json_decode(crmLegacyJsonOrFallback($flightsRaw, '[]'), true);
        $trains = json_decode(crmLegacyJsonOrFallback($trainsRaw, '[]'), true);
        if (!is_array($flights)) {
            $flights = [];
        }
        if (!is_array($trains)) {
            $trains = [];
        }
        foreach ($trains as &$train) {
            if (is_array($train)) {
                $train['_legacy_type'] = 'train';
            }
        }
        unset($train);

        return crmLegacyJsonOrFallback(array_merge($flights, $trains), '[]');
    }
}

if (!function_exists('crmLegacySupplierNote')) {
    function crmLegacySupplierNote(int $legacyId): string
    {
        return 'Legacy dashboard supplier id: ' . $legacyId;
    }
}

if (!function_exists('crmLegacyQuotationMarker')) {
    function crmLegacyQuotationMarker(int $legacyId): string
    {
        return '<!-- legacy_dashboard_quotation_id:' . $legacyId . ' -->';
    }
}

if (!function_exists('crmLegacyImportWipeTables')) {
    function crmLegacyImportWipeTables(mysqli $conn): void
    {
        crmEnsureQuotationTables($conn);
        crmEnsureSupplierTables($conn);

        $conn->query('SET FOREIGN_KEY_CHECKS = 0');
        $conn->query('TRUNCATE TABLE `crm_quotation_versions`');
        $conn->query('TRUNCATE TABLE `crm_quotations`');
        $conn->query('TRUNCATE TABLE `crm_leads`');
        $conn->query('TRUNCATE TABLE `crm_suppliers`');
        $conn->query('SET FOREIGN_KEY_CHECKS = 1');
    }
}

if (!function_exists('crmLegacyLoadPlacesMap')) {
    /** @return array<int,string> */
    function crmLegacyLoadPlacesMap(mysqli $legacy, string $placesTable = 'places'): array
    {
        $map = [];
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $placesTable);
        $res = $legacy->query('SELECT `id`, `description` FROM `' . $table . '`');
        if (!$res) {
            return $map;
        }
        while ($row = $res->fetch_assoc()) {
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0) {
                $map[$id] = trim((string) ($row['description'] ?? ''));
            }
        }
        $res->free();

        return $map;
    }
}

if (!function_exists('crmLegacySupplierContactsFromLegacy')) {
    function crmLegacySupplierContactsFromLegacy(?string $contactsRaw): array
    {
        $decoded = json_decode(crmLegacyJsonOrFallback($contactsRaw, '[]'), true);
        if (!is_array($decoded)) {
            return [];
        }
        $normalized = [];
        foreach ($decoded as $row) {
            if (!is_array($row)) {
                continue;
            }
            $normalized[] = [
                'contact_name' => trim((string) ($row['contact_name'] ?? $row['name'] ?? '')),
                'email' => trim((string) ($row['email'] ?? '')),
                'mobile' => trim((string) ($row['mobile'] ?? $row['mobile_no'] ?? '')),
                'designation' => trim((string) ($row['designation'] ?? '')),
                'is_primary' => !empty($row['is_primary']) ? 1 : 0,
            ];
        }

        return crmSupplierNormalizeContacts($normalized);
    }
}

if (!function_exists('crmLegacySupplierAlreadyImported')) {
    function crmLegacySupplierAlreadyImported(mysqli $conn, int $legacyId): bool
    {
        $note = crmLegacySupplierNote($legacyId);
        $stmt = $conn->prepare('SELECT 1 FROM `crm_suppliers` WHERE `internal_notes` = ? LIMIT 1');
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('s', $note);
        $stmt->execute();
        $res = $stmt->get_result();
        $exists = $res && $res->num_rows > 0;
        $stmt->close();

        return $exists;
    }
}

if (!function_exists('crmLegacyLeadAlreadyImported')) {
    function crmLegacyLeadAlreadyImported(mysqli $conn, int $legacyCustomerId): bool
    {
        $needle = '"legacy_dashboard_customer_id":' . $legacyCustomerId;
        $like = '%' . $conn->real_escape_string($needle) . '%';
        $res = $conn->query("SELECT 1 FROM `crm_leads` WHERE `payload_json` LIKE '{$like}' LIMIT 1");
        if (!$res) {
            return false;
        }
        $exists = $res->num_rows > 0;
        $res->free();

        return $exists;
    }
}

if (!function_exists('crmLegacyQuotationAlreadyImported')) {
    function crmLegacyQuotationAlreadyImported(mysqli $conn, int $legacyId, string $refId): bool
    {
        if ($refId !== '') {
            $stmt = $conn->prepare('SELECT 1 FROM `crm_quotations` WHERE `quotation_uid` = ? LIMIT 1');
            if ($stmt) {
                $stmt->bind_param('s', $refId);
                $stmt->execute();
                $res = $stmt->get_result();
                $exists = $res && $res->num_rows > 0;
                $stmt->close();
                if ($exists) {
                    return true;
                }
            }
        }

        $marker = crmLegacyQuotationMarker($legacyId);
        $like = '%' . $conn->real_escape_string($marker) . '%';
        $res = $conn->query("SELECT 1 FROM `crm_quotations` WHERE `other_details` LIKE '{$like}' LIMIT 1");
        if (!$res) {
            return false;
        }
        $exists = $res->num_rows > 0;
        $res->free();

        return $exists;
    }
}

if (!function_exists('crmLegacyBuildLeadLookup')) {
    /** @return array{phone:array<string,int>,email:array<string,int>} */
    function crmLegacyBuildLeadLookup(mysqli $conn): array
    {
        $lookup = ['phone' => [], 'email' => []];
        $res = $conn->query('SELECT `id`, `customer_phone`, `customer_email` FROM `crm_leads`');
        if (!$res) {
            return $lookup;
        }
        while ($row = $res->fetch_assoc()) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $phone = crmLegacyNormalizePhone((string) ($row['customer_phone'] ?? ''));
            if ($phone !== '') {
                $lookup['phone'][$phone] = $id;
            }
            $email = strtolower(trim((string) ($row['customer_email'] ?? '')));
            if ($email !== '') {
                $lookup['email'][$email] = $id;
            }
        }
        $res->free();

        return $lookup;
    }
}

if (!function_exists('crmLegacyResolveLeadId')) {
    function crmLegacyResolveLeadId(array $lookup, ?string $mobile, ?string $email): int
    {
        $phone = crmLegacyNormalizePhone($mobile);
        if ($phone !== '' && !empty($lookup['phone'][$phone])) {
            return (int) $lookup['phone'][$phone];
        }
        $emailKey = strtolower(trim((string) $email));
        if ($emailKey !== '' && !empty($lookup['email'][$emailKey])) {
            return (int) $lookup['email'][$emailKey];
        }

        return 0;
    }
}

if (!function_exists('crmLegacyImportSuppliers')) {
    /** @return array{imported:int,skipped:int,failed:int,errors:array<int,string>} */
    function crmLegacyImportSuppliers(mysqli $conn, mysqli $legacy, array $placesMap = [], string $suppliersTable = 'suppliers'): array
    {
        crmEnsureSupplierTables($conn);

        $stats = ['imported' => 0, 'skipped' => 0, 'failed' => 0, 'errors' => []];
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $suppliersTable);
        $res = $legacy->query('SELECT * FROM `' . $table . '` ORDER BY `id` ASC');
        if (!$res) {
            $stats['failed']++;
            $stats['errors'][] = 'Could not read legacy suppliers: ' . $legacy->error;

            return $stats;
        }

        $sql = 'INSERT INTO `crm_suppliers`
            (`name`, `website`, `city_name`, `physical_address`, `contacts_json`, `is_active`, `internal_notes`, `created_at`, `updated_at`)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)';
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            $stats['failed']++;
            $stats['errors'][] = 'Could not prepare supplier insert: ' . $conn->error;
            $res->free();

            return $stats;
        }

        while ($row = $res->fetch_assoc()) {
            $legacyId = (int) ($row['id'] ?? 0);
            if ($legacyId <= 0) {
                continue;
            }
            if (crmLegacySupplierAlreadyImported($conn, $legacyId)) {
                $stats['skipped']++;
                continue;
            }

            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                $stats['failed']++;
                $stats['errors'][] = 'Supplier #' . $legacyId . ' has no name.';
                continue;
            }

            $placeId = (int) ($row['place_id'] ?? 0);
            $cityName = ($placeId > 0 && !empty($placesMap[$placeId])) ? $placesMap[$placeId] : null;
            $website = trim((string) ($row['website'] ?? ''));
            $website = $website !== '' ? $website : null;
            $address = trim((string) ($row['address'] ?? ''));
            $address = $address !== '' ? $address : null;
            $contactsJson = crmLegacyJsonOrFallback(crmLegacySupplierContactsFromLegacy((string) ($row['contacts'] ?? '')));
            $isActive = (int) (($row['is_enabled'] ?? 1) ? 1 : 0);
            $internalNotes = crmLegacySupplierNote($legacyId);
            $createdAt = trim((string) ($row['created_at'] ?? ''));
            $updatedAt = trim((string) ($row['updated_at'] ?? ''));
            if ($createdAt === '' || $createdAt === '0000-00-00 00:00:00') {
                $createdAt = date('Y-m-d H:i:s');
            }
            if ($updatedAt === '' || $updatedAt === '0000-00-00 00:00:00') {
                $updatedAt = $createdAt;
            }

            $stmt->bind_param(
                'sssssisss',
                $name,
                $website,
                $cityName,
                $address,
                $contactsJson,
                $isActive,
                $internalNotes,
                $createdAt,
                $updatedAt
            );

            if ($stmt->execute()) {
                $stats['imported']++;
            } else {
                $stats['failed']++;
                $stats['errors'][] = 'Supplier #' . $legacyId . ' (' . $name . '): ' . $conn->error;
            }
        }

        $stmt->close();
        $res->free();

        return $stats;
    }
}

if (!function_exists('crmLegacyImportCustomersAsLeads')) {
    /** @return array{imported:int,skipped:int,failed:int,errors:array<int,string>,lookup:array{phone:array<string,int>,email:array<string,int>}} */
    function crmLegacyImportCustomersAsLeads(mysqli $conn, mysqli $legacy, string $customersTable = 'customers'): array
    {
        $conn->query("CREATE TABLE IF NOT EXISTS `crm_leads` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `lead_uid` VARCHAR(40) NOT NULL,
            `customer_name` VARCHAR(150) NOT NULL,
            `customer_phone` VARCHAR(30) NOT NULL,
            `customer_email` VARCHAR(190) DEFAULT NULL,
            `lead_source` VARCHAR(60) DEFAULT NULL,
            `referred_by` VARCHAR(150) DEFAULT NULL,
            `assign_to` VARCHAR(120) DEFAULT NULL,
            `services` TEXT,
            `itinerary_total_nights` INT DEFAULT 0,
            `itinerary_total_days` INT DEFAULT 0,
            `payload_json` LONGTEXT,
            `created_by_id` INT DEFAULT NULL,
            `created_by_name` VARCHAR(120) DEFAULT NULL,
            `intake_submission_id` INT UNSIGNED DEFAULT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_crm_lead_uid` (`lead_uid`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $stats = ['imported' => 0, 'skipped' => 0, 'failed' => 0, 'errors' => []];
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $customersTable);
        $res = $legacy->query('SELECT * FROM `' . $table . '` ORDER BY `id` ASC');
        if (!$res) {
            $stats['failed']++;
            $stats['errors'][] = 'Could not read legacy customers: ' . $legacy->error;
            $stats['lookup'] = crmLegacyBuildLeadLookup($conn);

            return $stats;
        }

        $sql = 'INSERT INTO `crm_leads`
            (`lead_uid`, `customer_name`, `customer_phone`, `customer_email`, `lead_source`, `assign_to`,
             `services`, `payload_json`, `created_by_name`, `created_at`, `updated_at`)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            $stats['failed']++;
            $stats['errors'][] = 'Could not prepare lead insert: ' . $conn->error;
            $res->free();
            $stats['lookup'] = crmLegacyBuildLeadLookup($conn);

            return $stats;
        }

        $leadSource = 'Legacy Import';
        $assignTo = 'Admin';
        $createdByName = 'Legacy Import';
        $servicesJson = json_encode(['tour_package'], JSON_UNESCAPED_UNICODE);

        while ($row = $res->fetch_assoc()) {
            $legacyId = (int) ($row['id'] ?? 0);
            if ($legacyId <= 0) {
                continue;
            }
            if (crmLegacyLeadAlreadyImported($conn, $legacyId)) {
                $stats['skipped']++;
                continue;
            }

            $customerName = trim((string) ($row['name'] ?? ''));
            $customerPhone = trim((string) ($row['mobile_no'] ?? ''));
            $customerEmail = trim((string) ($row['email'] ?? ''));
            if ($customerName === '') {
                $stats['failed']++;
                $stats['errors'][] = 'Customer #' . $legacyId . ' has no name.';
                continue;
            }
            if ($customerPhone === '') {
                $stats['failed']++;
                $stats['errors'][] = 'Customer #' . $legacyId . ' has no phone.';
                continue;
            }

            $payload = [
                'customer_name' => $customerName,
                'customer_phone' => $customerPhone,
                'customer_email' => $customerEmail,
                'legacy_dashboard_customer_id' => $legacyId,
                'legacy_title' => trim((string) ($row['title'] ?? '')),
                'legacy_card_no' => trim((string) ($row['card_no'] ?? '')),
                'legacy_points' => (int) ($row['points'] ?? 0),
                'legacy_address' => trim((string) ($row['address'] ?? '')),
            ];
            $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE);
            if ($payloadJson === false) {
                $payloadJson = '{}';
            }

            $leadUid = generateLeadUid($conn);
            $createdAt = trim((string) ($row['created_at'] ?? ''));
            $updatedAt = trim((string) ($row['updated_at'] ?? ''));
            if ($createdAt === '' || $createdAt === '0000-00-00 00:00:00') {
                $createdAt = date('Y-m-d H:i:s');
            }
            if ($updatedAt === '' || $updatedAt === '0000-00-00 00:00:00') {
                $updatedAt = $createdAt;
            }
            $emailParam = $customerEmail !== '' ? $customerEmail : null;

            $stmt->bind_param(
                'sssssssssss',
                $leadUid,
                $customerName,
                $customerPhone,
                $emailParam,
                $leadSource,
                $assignTo,
                $servicesJson,
                $payloadJson,
                $createdByName,
                $createdAt,
                $updatedAt
            );

            if ($stmt->execute()) {
                $stats['imported']++;
            } else {
                $stats['failed']++;
                $stats['errors'][] = 'Customer #' . $legacyId . ' (' . $customerName . '): ' . $conn->error;
            }
        }

        $stmt->close();
        $res->free();
        $stats['lookup'] = crmLegacyBuildLeadLookup($conn);

        return $stats;
    }
}

if (!function_exists('crmLegacyBuildLeadIdByPhone')) {
    /** @return array<string,int> normalized phone => lead id */
    function crmLegacyBuildLeadIdByPhone(mysqli $conn): array
    {
        $map = [];
        $res = $conn->query('SELECT `id`, `customer_phone` FROM `crm_leads`');
        if (!$res) {
            return $map;
        }
        while ($row = $res->fetch_assoc()) {
            $phone = crmLegacyNormalizePhone((string) ($row['customer_phone'] ?? ''));
            if ($phone !== '') {
                $map[$phone] = (int) ($row['id'] ?? 0);
            }
        }
        $res->free();

        return $map;
    }
}

if (!function_exists('crmLegacyPayloadFromQuotationRow')) {
    function crmLegacyPayloadFromQuotationRow(array $qRow, string $customerName, string $customerPhone, string $customerEmail): array
    {
        $nights = max(0, (int) ($qRow['no_of_nights'] ?? 0));
        $travelDate = crmLegacyNormalizeDateValue($qRow['tentative_date'] ?? '');

        return [
            'customer_name' => $customerName,
            'customer_phone' => $customerPhone,
            'customer_email' => $customerEmail,
            'legacy_from_quotation' => true,
            'legacy_quotation_id' => (int) ($qRow['id'] ?? 0),
            'tp_arrival' => trim((string) ($qRow['destination'] ?? '')),
            'tp_travel_date' => $travelDate,
            'tp_adults' => max(0, (int) ($qRow['no_of_adults'] ?? 0)),
            'tp_children' => max(0, (int) ($qRow['no_of_children'] ?? 0)),
            'itinerary_total_nights' => $nights,
            'itinerary_total_days' => $nights > 0 ? $nights + 1 : 0,
        ];
    }
}

if (!function_exists('crmLegacyCreateLeadsFromStoredQuotations')) {
    /**
     * Create one lead per unique quotation phone and link quotations to leads.
     *
     * @return array{imported:int,updated:int,skipped:int,linked:int,failed:int,errors:array<int,string>}
     */
    function crmLegacyCreateLeadsFromStoredQuotations(mysqli $conn): array
    {
        crmEnsureQuotationTables($conn);

        $conn->query("CREATE TABLE IF NOT EXISTS `crm_leads` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `lead_uid` VARCHAR(40) NOT NULL,
            `customer_name` VARCHAR(150) NOT NULL,
            `customer_phone` VARCHAR(30) NOT NULL,
            `customer_email` VARCHAR(190) DEFAULT NULL,
            `lead_source` VARCHAR(60) DEFAULT NULL,
            `referred_by` VARCHAR(150) DEFAULT NULL,
            `assign_to` VARCHAR(120) DEFAULT NULL,
            `services` TEXT,
            `itinerary_total_nights` INT DEFAULT 0,
            `itinerary_total_days` INT DEFAULT 0,
            `payload_json` LONGTEXT,
            `created_by_id` INT DEFAULT NULL,
            `created_by_name` VARCHAR(120) DEFAULT NULL,
            `intake_submission_id` INT UNSIGNED DEFAULT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_crm_lead_uid` (`lead_uid`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $stats = ['imported' => 0, 'updated' => 0, 'skipped' => 0, 'linked' => 0, 'failed' => 0, 'errors' => []];

        $byPhone = [];
        $res = $conn->query('SELECT * FROM `crm_quotations` ORDER BY `id` ASC');
        if (!$res) {
            $stats['failed']++;
            $stats['errors'][] = 'Could not read quotations.';

            return $stats;
        }
        while ($row = $res->fetch_assoc()) {
            $phone = crmLegacyNormalizePhone((string) ($row['mobile_no'] ?? ''));
            if ($phone === '') {
                continue;
            }
            $byPhone[$phone] = $row;
        }
        $res->free();

        if (empty($byPhone)) {
            return $stats;
        }

        $leadByPhone = crmLegacyBuildLeadIdByPhone($conn);

        $insertSql = 'INSERT INTO `crm_leads`
            (`lead_uid`, `customer_name`, `customer_phone`, `customer_email`, `lead_source`, `assign_to`,
             `services`, `itinerary_total_nights`, `itinerary_total_days`, `payload_json`, `created_by_name`, `created_at`, `updated_at`)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
        $insertStmt = $conn->prepare($insertSql);

        $updatePayloadSql = 'UPDATE `crm_leads`
            SET `payload_json` = ?, `itinerary_total_nights` = ?, `itinerary_total_days` = ?, `updated_at` = NOW()
            WHERE `id` = ? LIMIT 1';
        $updatePayloadStmt = $conn->prepare($updatePayloadSql);

        $leadSource = 'Legacy Quotation';
        $assignTo = 'Admin';
        $createdByName = 'Legacy Import';
        $servicesJson = json_encode(['tour_package'], JSON_UNESCAPED_UNICODE);

        foreach ($byPhone as $phone => $qRow) {
            $guestName = trim((string) ($qRow['guest_name'] ?? ''));
            $mobileNo = trim((string) ($qRow['mobile_no'] ?? ''));
            $email = trim((string) ($qRow['email'] ?? ''));
            if ($guestName === '' || $mobileNo === '') {
                $stats['failed']++;
                continue;
            }

            $payload = crmLegacyPayloadFromQuotationRow($qRow, $guestName, $mobileNo, $email);
            $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE) ?: '{}';
            $nights = (int) ($payload['itinerary_total_nights'] ?? 0);
            $days = (int) ($payload['itinerary_total_days'] ?? 0);

            $leadId = (int) ($leadByPhone[$phone] ?? 0);

            if ($leadId <= 0) {
                if (!$insertStmt) {
                    $stats['failed']++;
                    $stats['errors'][] = 'Could not prepare lead insert.';
                    break;
                }

                $leadUid = generateLeadUid($conn);
                $createdAt = trim((string) ($qRow['created_at'] ?? ''));
                $updatedAt = trim((string) ($qRow['updated_at'] ?? ''));
                if ($createdAt === '' || $createdAt === '0000-00-00 00:00:00') {
                    $createdAt = date('Y-m-d H:i:s');
                }
                if ($updatedAt === '' || $updatedAt === '0000-00-00 00:00:00') {
                    $updatedAt = $createdAt;
                }
                $emailParam = $email !== '' ? $email : null;

                $insertStmt->bind_param(
                    'sssssssiissss',
                    $leadUid,
                    $guestName,
                    $mobileNo,
                    $emailParam,
                    $leadSource,
                    $assignTo,
                    $servicesJson,
                    $nights,
                    $days,
                    $payloadJson,
                    $createdByName,
                    $createdAt,
                    $updatedAt
                );

                if ($insertStmt->execute()) {
                    $leadId = (int) $insertStmt->insert_id;
                    $leadByPhone[$phone] = $leadId;
                    $stats['imported']++;
                } else {
                    $stats['failed']++;
                    $stats['errors'][] = 'Lead for ' . $mobileNo . ': ' . $conn->error;
                    continue;
                }
            } else {
                $needsPayload = true;
                $resLead = $conn->query('SELECT `payload_json` FROM `crm_leads` WHERE `id` = ' . $leadId . ' LIMIT 1');
                if ($resLead && ($leadRow = $resLead->fetch_assoc())) {
                    $existingPayload = json_decode((string) ($leadRow['payload_json'] ?? ''), true);
                    if (is_array($existingPayload) && trim((string) ($existingPayload['tp_arrival'] ?? '')) !== '') {
                        $needsPayload = false;
                    }
                }

                if ($needsPayload && $updatePayloadStmt) {
                    $updatePayloadStmt->bind_param('siii', $payloadJson, $nights, $days, $leadId);
                    if ($updatePayloadStmt->execute()) {
                        $stats['updated']++;
                    }
                } else {
                    $stats['skipped']++;
                }
            }

            $linkStmt = $conn->prepare(
                'UPDATE `crm_quotations` SET `lead_id` = ?
                 WHERE REPLACE(REPLACE(REPLACE(`mobile_no`, " ", ""), "-", ""), "+", "") LIKE ?
                 AND (`lead_id` IS NULL OR `lead_id` = 0 OR `lead_id` = ?)'
            );
            if ($linkStmt) {
                $like = '%' . $phone;
                $linkStmt->bind_param('isi', $leadId, $like, $leadId);
                if ($linkStmt->execute()) {
                    $stats['linked'] += max(0, (int) $linkStmt->affected_rows);
                }
                $linkStmt->close();
            }
        }

        if ($insertStmt) {
            $insertStmt->close();
        }
        if ($updatePayloadStmt) {
            $updatePayloadStmt->close();
        }

        return $stats;
    }
}

if (!function_exists('crmLegacyImportQuotations')) {
    /** @return array{imported:int,skipped:int,failed:int,errors:array<int,string>} */
    function crmLegacyImportQuotations(mysqli $conn, mysqli $legacy, array $leadLookup, string $quotationTable = 'quotation'): array
    {
        crmEnsureQuotationTables($conn);

        $stats = ['imported' => 0, 'skipped' => 0, 'failed' => 0, 'errors' => []];
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $quotationTable);
        $res = $legacy->query('SELECT * FROM `' . $table . '` ORDER BY `id` ASC');
        if (!$res) {
            $stats['failed']++;
            $stats['errors'][] = 'Could not read legacy quotations: ' . $legacy->error;

            return $stats;
        }

        $sql = 'INSERT INTO `crm_quotations`
            (`quotation_uid`, `lead_id`, `version`, `status`, `wizard_step`, `guest_name`, `reference_name`, `mobile_no`, `email`,
             `destination`, `tentative_date`, `no_of_nights`, `no_of_adults`, `no_of_children`, `flights_json`, `hotels_json`,
             `itinerary_json`, `inclusion`, `exclusion`, `payment_policy`, `cancellation_policy`, `terms_conditions`, `other_details`,
             `cost_sheet_json`, `total_cost`, `package_total`, `price_per_adult`, `quotation_total`, `hide_gst_note`, `tour_confirmed`,
             `created_by_name`, `created_at`, `updated_at`)
            VALUES (?, ?, 1, ?, 6, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            $stats['failed']++;
            $stats['errors'][] = 'Could not prepare quotation insert: ' . $conn->error;
            $res->free();

            return $stats;
        }

        $createdByName = 'Legacy Import';
        $status = 'published';

        while ($row = $res->fetch_assoc()) {
            $legacyId = (int) ($row['id'] ?? 0);
            if ($legacyId <= 0) {
                continue;
            }

            $refId = trim((string) ($row['ref_id'] ?? ''));
            if (crmLegacyQuotationAlreadyImported($conn, $legacyId, $refId)) {
                $stats['skipped']++;
                continue;
            }

            $guestName = trim((string) ($row['guest_name'] ?? ''));
            if ($guestName === '') {
                $stats['failed']++;
                $stats['errors'][] = 'Quotation #' . $legacyId . ' has no guest name.';
                continue;
            }

            $quotationUid = $refId !== '' ? $refId : crmGenerateOrphanQuotationUid($conn);
            if (!crmQuotationUidAvailable($conn, $quotationUid, 0)) {
                $quotationUid = crmGenerateOrphanQuotationUid($conn);
            }

            $referenceName = trim((string) ($row['ref_name'] ?? ''));
            $referenceName = $referenceName !== '' ? $referenceName : null;
            $mobileNo = trim((string) ($row['mobile_no'] ?? ''));
            $mobileNo = $mobileNo !== '' ? $mobileNo : null;
            $email = trim((string) ($row['email'] ?? ''));
            $email = $email !== '' ? $email : null;
            $destination = trim((string) ($row['destination'] ?? ''));
            $destination = $destination !== '' ? $destination : null;

            $tentativeDate = trim((string) ($row['tentative_date'] ?? ''));
            if ($tentativeDate === '' || $tentativeDate === '0000-00-00') {
                $tentativeDate = null;
            }

            $nights = max(0, (int) ($row['nights'] ?? 0));
            $adults = max(1, (int) ($row['adults'] ?? 1));
            $children = max(0, (int) ($row['children'] ?? 0));

            $flightsJson = crmLegacyMergeFlightsAndTrains(
                (string) ($row['flights'] ?? ''),
                (string) ($row['trains'] ?? '')
            );
            $hotelsJson = crmLegacyJsonOrFallback($row['hotels'] ?? '', '[]');
            $itineraryJson = crmLegacyJsonOrFallback($row['itineraries'] ?? '', '[]');
            $costSheetJson = crmLegacyJsonOrFallback($row['costing'] ?? '', '{}');

            $pricePerAdult = (float) ($row['adult_cost'] ?? 0);
            $normalized = crmLegacyNormalizeQuotationRowForCrm([
                'flights_json' => $flightsJson,
                'hotels_json' => $hotelsJson,
                'itinerary_json' => $itineraryJson,
                'cost_sheet_json' => $costSheetJson,
                'no_of_nights' => $nights,
                'price_per_adult' => $pricePerAdult,
            ]);
            $flightsJson = (string) ($normalized['flights_json'] ?? $flightsJson);
            $hotelsJson = (string) ($normalized['hotels_json'] ?? $hotelsJson);
            $itineraryJson = (string) ($normalized['itinerary_json'] ?? $itineraryJson);
            $costSheetJson = (string) ($normalized['cost_sheet_json'] ?? $costSheetJson);
            $nights = max(0, (int) ($normalized['no_of_nights'] ?? $nights));

            $inclusion = (string) ($row['inclusion'] ?? '');
            $exclusion = (string) ($row['exclusion'] ?? '');
            $paymentPolicy = (string) ($row['payment_policy'] ?? '');
            $cancellationPolicy = (string) ($row['cancellation_policy'] ?? '');
            $termsConditions = (string) ($row['terms_conditions'] ?? '');

            $otherDetails = trim((string) ($row['other_details'] ?? ''));
            $childrenAges = trim((string) ($row['children_ages'] ?? ''));
            if ($childrenAges !== '') {
                $otherDetails = trim($otherDetails . "\n\nChildren ages: " . $childrenAges);
            }
            $marker = crmLegacyQuotationMarker($legacyId);
            $otherDetails = trim($otherDetails . "\n" . $marker);
            $otherDetails = $otherDetails !== '' ? $otherDetails : $marker;

            $costDecoded = json_decode($costSheetJson, true);
            $totalCost = 0.0;
            $packageTotal = 0.0;
            if (is_array($costDecoded)) {
                $totalCost = (float) ($costDecoded['legacy_cost_price'] ?? ($costDecoded['cost_price'] ?? 0));
                $packageTotal = (float) ($costDecoded['legacy_selling_price'] ?? ($costDecoded['selling_price'] ?? 0));
            }
            $quotationTotal = (float) ($row['total'] ?? 0);
            if ($packageTotal <= 0 && $quotationTotal > 0) {
                $packageTotal = $quotationTotal;
            }
            $pricePerAdult = (float) ($row['adult_cost'] ?? 0);

            $hideGstNote = (int) (($row['hide_note'] ?? 0) ? 1 : 0);
            $tourConfirmed = (int) (($row['is_confirmed'] ?? 0) ? 1 : 0);

            $leadId = crmLegacyResolveLeadId($leadLookup, $mobileNo, $email);
            $leadIdParam = $leadId > 0 ? $leadId : null;

            $createdAt = trim((string) ($row['created_at'] ?? ''));
            $updatedAt = trim((string) ($row['updated_at'] ?? ''));
            if ($createdAt === '' || $createdAt === '0000-00-00 00:00:00') {
                $createdAt = date('Y-m-d H:i:s');
            }
            if ($updatedAt === '' || $updatedAt === '0000-00-00 00:00:00') {
                $updatedAt = $createdAt;
            }

            $stmt->bind_param(
                'sisssssssiiiisssssssssddddiisss',
                $quotationUid,
                $leadIdParam,
                $status,
                $guestName,
                $referenceName,
                $mobileNo,
                $email,
                $destination,
                $tentativeDate,
                $nights,
                $adults,
                $children,
                $flightsJson,
                $hotelsJson,
                $itineraryJson,
                $inclusion,
                $exclusion,
                $paymentPolicy,
                $cancellationPolicy,
                $termsConditions,
                $otherDetails,
                $costSheetJson,
                $totalCost,
                $packageTotal,
                $pricePerAdult,
                $quotationTotal,
                $hideGstNote,
                $tourConfirmed,
                $createdByName,
                $createdAt,
                $updatedAt
            );

            if ($stmt->execute()) {
                $stats['imported']++;
            } else {
                $stats['failed']++;
                $stats['errors'][] = 'Quotation #' . $legacyId . ' (' . $quotationUid . '): ' . $conn->error;
            }
        }

        $stmt->close();
        $res->free();

        return $stats;
    }
}

if (!function_exists('crmLegacyImportRun')) {
    /**
     * @param array{clear_existing?:bool,suppliers?:bool,customers?:bool,quotations?:bool} $options
     * @return array{success:bool,message:string,probe:array,wiped:bool,suppliers?:array,customers?:array,quotations?:array}
     */
    function crmLegacyImportRun(mysqli $conn, array $options = []): array
    {
        $runSuppliers = !array_key_exists('suppliers', $options) || !empty($options['suppliers']);
        $runCustomers = !array_key_exists('customers', $options) || !empty($options['customers']);
        $runQuotations = !array_key_exists('quotations', $options) || !empty($options['quotations']);
        $clearExisting = !empty($options['clear_existing']);

        @set_time_limit(600);

        $probe = crmLegacyImportProbe($conn);
        if (!$probe['connected']) {
            return [
                'success' => false,
                'message' => (string) ($probe['error'] ?? 'Legacy SQL file not available.'),
                'probe' => $probe,
                'wiped' => false,
            ];
        }

        $stage = crmLegacyStageFromSqlFile($conn);
        if (!$stage['success']) {
            return [
                'success' => false,
                'message' => (string) ($stage['message'] ?? 'Could not load legacy SQL file.'),
                'probe' => $probe,
                'staging' => $stage,
                'wiped' => false,
            ];
        }

        $probe['counts'] = $stage['counts'];
        $tableMap = crmLegacyStagingTableMap();
        $source = $conn;

        $result = [
            'success' => true,
            'message' => 'Import completed.',
            'probe' => $probe,
            'staging' => $stage,
            'wiped' => false,
        ];

        if ($clearExisting) {
            crmLegacyImportWipeTables($conn);
            $result['wiped'] = true;
        }

        $placesMap = crmLegacyLoadPlacesMap($source, $tableMap['places']);
        $leadLookup = crmLegacyBuildLeadLookup($conn);

        if ($runSuppliers) {
            $result['suppliers'] = crmLegacyImportSuppliers($conn, $source, $placesMap, $tableMap['suppliers']);
        }

        if ($runCustomers) {
            $customerStats = crmLegacyImportCustomersAsLeads($conn, $source, $tableMap['customers']);
            $leadLookup = $customerStats['lookup'] ?? crmLegacyBuildLeadLookup($conn);
            unset($customerStats['lookup']);
            $result['customers'] = $customerStats;
        }

        if ($runQuotations) {
            $result['quotations'] = crmLegacyImportQuotations($conn, $source, $leadLookup, $tableMap['quotation']);
        }

        crmLegacyDropStagingTables($conn);

        $failed = 0;
        foreach (['suppliers', 'customers', 'quotations'] as $key) {
            if (!empty($result[$key]['failed'])) {
                $failed += (int) $result[$key]['failed'];
            }
        }
        if ($failed > 0) {
            $result['success'] = false;
            $result['message'] = 'Import finished with ' . $failed . ' error(s). Review the log below.';
        }

        return $result;
    }
}

if (!function_exists('crmLegacyImportHandleStep')) {
    /**
     * Run one import step (for AJAX — avoids Hostinger request timeouts).
     *
     * @param array{clear_existing?:bool,suppliers?:bool,customers?:bool,quotations?:bool} $options
     */
    function crmLegacyImportHandleStep(mysqli $conn, string $step, array $options = []): array
    {
        @set_time_limit(300);
        @ini_set('memory_limit', '512M');

        $step = strtolower(trim($step));
        $tableMap = crmLegacyStagingTableMap();

        if ($step === 'probe') {
            return ['success' => true, 'message' => 'OK', 'probe' => crmLegacyImportProbe($conn)];
        }

        if ($step === 'wipe') {
            crmLegacyImportWipeTables($conn);

            return ['success' => true, 'message' => 'Existing CRM suppliers, leads, and quotations cleared.', 'step' => $step];
        }

        if ($step === 'stage_init') {
            $stage = crmLegacyStageSelectedTables($conn, ['places', 'suppliers', 'customers'], null, true);
            $stage['step'] = $step;
            $stage['file'] = basename(crmLegacySqlFilePath());
            if ((int) ($stage['counts']['suppliers'] ?? 0) === 0 && (int) ($stage['counts']['customers'] ?? 0) === 0) {
                $stage['success'] = false;
                $stage['message'] = 'Could not load suppliers/customers from SQL file.';
            }

            return $stage;
        }

        if ($step === 'stage_quotations') {
            $stage = crmLegacyStageSelectedTables($conn, ['quotation'], null, false);
            $stage['step'] = $step;
            if ((int) ($stage['counts']['quotation'] ?? 0) === 0) {
                $stage['success'] = false;
                $stage['message'] = 'Could not load quotations from SQL file.';
            }

            return $stage;
        }

        if ($step === 'import_suppliers') {
            if (empty($options['suppliers'])) {
                return ['success' => true, 'message' => 'Suppliers skipped.', 'step' => $step, 'suppliers' => ['imported' => 0, 'skipped' => 0, 'failed' => 0, 'errors' => []]];
            }
            if (!crmLegacyStagingTablesExist($conn)) {
                return ['success' => false, 'message' => 'Staging tables missing. Run stage steps first.', 'step' => $step];
            }
            $placesMap = crmLegacyLoadPlacesMap($conn, $tableMap['places']);
            $stats = crmLegacyImportSuppliers($conn, $conn, $placesMap, $tableMap['suppliers']);

            return ['success' => empty($stats['failed']), 'message' => 'Suppliers imported.', 'step' => $step, 'suppliers' => $stats];
        }

        if ($step === 'import_customers') {
            if (empty($options['customers'])) {
                return ['success' => true, 'message' => 'Customers skipped.', 'step' => $step, 'customers' => ['imported' => 0, 'skipped' => 0, 'failed' => 0, 'errors' => []]];
            }
            if (!crmLegacyStagingTablesExist($conn)) {
                return ['success' => false, 'message' => 'Staging tables missing. Run stage steps first.', 'step' => $step];
            }
            $stats = crmLegacyImportCustomersAsLeads($conn, $conn, $tableMap['customers']);
            unset($stats['lookup']);

            return ['success' => empty($stats['failed']), 'message' => 'Customers imported.', 'step' => $step, 'customers' => $stats];
        }

        if ($step === 'import_quotations') {
            if (empty($options['quotations'])) {
                return ['success' => true, 'message' => 'Quotations skipped.', 'step' => $step, 'quotations' => ['imported' => 0, 'skipped' => 0, 'failed' => 0, 'errors' => []]];
            }
            if (!crmLegacyStagingTablesExist($conn)) {
                return ['success' => false, 'message' => 'Staging tables missing. Run stage steps first.', 'step' => $step];
            }
            $leadLookup = crmLegacyBuildLeadLookup($conn);
            $stats = crmLegacyImportQuotations($conn, $conn, $leadLookup, $tableMap['quotation']);

            return ['success' => empty($stats['failed']), 'message' => 'Quotations imported.', 'step' => $step, 'quotations' => $stats];
        }

        if ($step === 'cleanup') {
            crmLegacyDropStagingTables($conn);

            return ['success' => true, 'message' => 'Temporary staging tables removed.', 'step' => $step];
        }

        if ($step === 'repair_quotations') {
            $repair = crmLegacyRepairStoredQuotations($conn);

            return [
                'success' => empty($repair['failed']),
                'message' => 'Repaired ' . (int) $repair['updated'] . ' quotation(s).',
                'step' => $step,
                'repair' => $repair,
            ];
        }

        if ($step === 'import_leads_from_quotations') {
            $stats = crmLegacyCreateLeadsFromStoredQuotations($conn);

            return [
                'success' => empty($stats['failed']),
                'message' => 'Created ' . (int) $stats['imported'] . ' lead(s) from quotations, updated ' . (int) $stats['updated'] . ', linked ' . (int) $stats['linked'] . ' quotation(s).',
                'step' => $step,
                'leads_from_quotations' => $stats,
            ];
        }

        return ['success' => false, 'message' => 'Unknown import step: ' . $step, 'step' => $step];
    }
}
