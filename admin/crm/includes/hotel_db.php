<?php

function crmEnsureHotelTables(mysqli $conn): void
{
    $sql = "CREATE TABLE IF NOT EXISTS `crm_hotels` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `destination_id` INT UNSIGNED NOT NULL,
        `city_id` INT UNSIGNED NOT NULL,
        `hotel_name` VARCHAR(200) NOT NULL,
        `is_default` TINYINT(1) NOT NULL DEFAULT 0,
        `star_category` VARCHAR(30) NOT NULL DEFAULT '3 Star',
        `star_rating` DECIMAL(3,1) NOT NULL DEFAULT 0,
        `review_link` VARCHAR(500) DEFAULT NULL,
        `address` TEXT,
        `image_path` VARCHAR(255) DEFAULT NULL,
        `room_types_json` LONGTEXT,
        `meal_plans_json` LONGTEXT,
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        `created_by_id` INT DEFAULT NULL,
        `created_by_name` VARCHAR(120) DEFAULT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_crm_hotels_destination` (`destination_id`),
        KEY `idx_crm_hotels_city` (`city_id`),
        KEY `idx_crm_hotels_default_city` (`city_id`, `is_default`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $conn->query($sql);
}

/**
 * @param mixed $input
 * @return array<int, array{type: string, description: string, price: float}>
 */
function crmNormalizeHotelRoomTypes($input): array
{
    if (!is_array($input)) {
        return [];
    }
    $rows = [];
    foreach ($input as $item) {
        if (!is_array($item)) {
            continue;
        }
        $type = trim((string) ($item['type'] ?? ''));
        $description = trim((string) ($item['description'] ?? ''));
        $price = (float) ($item['price'] ?? 0);
        if ($type === '' && $description === '' && $price <= 0) {
            continue;
        }
        $rows[] = [
            'type' => $type,
            'description' => $description,
            'price' => max(0, $price),
        ];
    }

    return $rows;
}

/**
 * @param mixed $input
 * @return array<int, array{name: string, description: string, price: float}>
 */
function crmNormalizeHotelMealPlans($input): array
{
    if (!is_array($input)) {
        return [];
    }
    $rows = [];
    foreach ($input as $item) {
        if (!is_array($item)) {
            continue;
        }
        $name = trim((string) ($item['name'] ?? ''));
        $description = trim((string) ($item['description'] ?? ''));
        $price = (float) ($item['price'] ?? 0);
        if ($name === '' && $description === '' && $price <= 0) {
            continue;
        }
        $rows[] = [
            'name' => $name,
            'description' => $description,
            'price' => max(0, $price),
        ];
    }

    return $rows;
}

function crmClearDefaultHotelForCity(mysqli $conn, int $cityId, int $exceptId = 0): void
{
    if ($cityId <= 0) {
        return;
    }
    if ($exceptId > 0) {
        $stmt = $conn->prepare('UPDATE `crm_hotels` SET `is_default` = 0 WHERE `city_id` = ? AND `id` != ?');
        if ($stmt) {
            $stmt->bind_param('ii', $cityId, $exceptId);
            $stmt->execute();
            $stmt->close();
        }
        return;
    }
    $stmt = $conn->prepare('UPDATE `crm_hotels` SET `is_default` = 0 WHERE `city_id` = ?');
    if ($stmt) {
        $stmt->bind_param('i', $cityId);
        $stmt->execute();
        $stmt->close();
    }
}

function crmResolveDestinationIdByName(mysqli $conn, string $name): int
{
    $name = trim($name);
    if ($name === '') {
        return 0;
    }

    $stmt = $conn->prepare('SELECT `id` FROM `destinations` WHERE `is_active` = 1 AND LOWER(`name`) = LOWER(?) LIMIT 1');
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param('s', $name);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    return $row ? (int) ($row['id'] ?? 0) : 0;
}

function crmDestinationCountryId(mysqli $conn, int $destinationId): int
{
    if ($destinationId <= 0) {
        return 0;
    }

    require_once __DIR__ . '/../../includes/geo_locations.php';
    geoEnsureTables($conn);

    $stmt = $conn->prepare('SELECT `country` FROM `destinations` WHERE `id` = ? AND `is_active` = 1 LIMIT 1');
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param('i', $destinationId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    if (!$row) {
        return 0;
    }

    $countryName = trim((string) ($row['country'] ?? ''));
    if ($countryName === '') {
        return 0;
    }

    $countryStmt = $conn->prepare('SELECT `id` FROM `countries` WHERE LOWER(`name`) = LOWER(?) LIMIT 1');
    if (!$countryStmt) {
        return 0;
    }
    $countryStmt->bind_param('s', $countryName);
    $countryStmt->execute();
    $countryRes = $countryStmt->get_result();
    $countryRow = $countryRes ? $countryRes->fetch_assoc() : null;
    $countryStmt->close();

    return $countryRow ? (int) ($countryRow['id'] ?? 0) : 0;
}

function crmResolveCityIdByName(mysqli $conn, string $cityName, int $destinationId = 0): int
{
    $cityName = trim($cityName);
    if ($cityName === '') {
        return 0;
    }

    require_once __DIR__ . '/../../includes/geo_locations.php';
    geoEnsureTables($conn);

    $countryId = $destinationId > 0 ? crmDestinationCountryId($conn, $destinationId) : 0;
    $sql = 'SELECT `id` FROM `cities` WHERE LOWER(`name`) = LOWER(?)';
    if ($countryId > 0) {
        $sql .= ' AND `country_id` = ?';
    }
    $sql .= ' ORDER BY `id` ASC LIMIT 1';

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return 0;
    }
    if ($countryId > 0) {
        $stmt->bind_param('si', $cityName, $countryId);
    } else {
        $stmt->bind_param('s', $cityName);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    return $row ? (int) ($row['id'] ?? 0) : 0;
}

function crmGuessDestinationIdForCity(mysqli $conn, int $cityId): int
{
    if ($cityId <= 0) {
        return 0;
    }

    $stmt = $conn->prepare('SELECT `destination_id` FROM `crm_hotels` WHERE `city_id` = ? AND `is_active` = 1 ORDER BY `is_default` DESC, `id` ASC LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('i', $cityId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        if ($row && (int) ($row['destination_id'] ?? 0) > 0) {
            return (int) $row['destination_id'];
        }
    }

    require_once __DIR__ . '/../../includes/geo_locations.php';
    geoEnsureTables($conn);

    $cityStmt = $conn->prepare('SELECT c.`country_id`, co.`name` AS country_name FROM `cities` c INNER JOIN `countries` co ON co.`id` = c.`country_id` WHERE c.`id` = ? LIMIT 1');
    if (!$cityStmt) {
        return 0;
    }
    $cityStmt->bind_param('i', $cityId);
    $cityStmt->execute();
    $cityRes = $cityStmt->get_result();
    $cityRow = $cityRes ? $cityRes->fetch_assoc() : null;
    $cityStmt->close();
    if (!$cityRow) {
        return 0;
    }

    $countryName = trim((string) ($cityRow['country_name'] ?? ''));
    if ($countryName === '') {
        return 0;
    }

    $destStmt = $conn->prepare('SELECT `id` FROM `destinations` WHERE `is_active` = 1 AND LOWER(`country`) = LOWER(?) ORDER BY `display_order` ASC, `name` ASC LIMIT 1');
    if (!$destStmt) {
        return 0;
    }
    $destStmt->bind_param('s', $countryName);
    $destStmt->execute();
    $destRes = $destStmt->get_result();
    $destRow = $destRes ? $destRes->fetch_assoc() : null;
    $destStmt->close();

    return $destRow ? (int) ($destRow['id'] ?? 0) : 0;
}

/**
 * @param array<int, array{type: string, description: string, price: float}> $roomTypes
 * @return array<int, array{type: string, description: string, price: float}>
 */
function crmMergeHotelRoomType(array $roomTypes, string $type, float $price): array
{
    $type = trim($type);
    if ($type === '') {
        return $roomTypes;
    }

    foreach ($roomTypes as &$row) {
        if (strcasecmp((string) ($row['type'] ?? ''), $type) === 0) {
            if ($price > 0) {
                $row['price'] = $price;
            }
            return $roomTypes;
        }
    }
    unset($row);

    $roomTypes[] = [
        'type' => $type,
        'description' => '',
        'price' => max(0, $price),
    ];

    return $roomTypes;
}

/**
 * @param array<int, array{name: string, description: string, price: float}> $mealPlans
 * @return array<int, array{name: string, description: string, price: float}>
 */
function crmMergeHotelMealPlan(array $mealPlans, string $name, float $price): array
{
    $name = trim($name);
    if ($name === '') {
        return $mealPlans;
    }

    foreach ($mealPlans as &$row) {
        if (strcasecmp((string) ($row['name'] ?? ''), $name) === 0) {
            if ($price > 0) {
                $row['price'] = $price;
            }
            return $mealPlans;
        }
    }
    unset($row);

    $mealPlans[] = [
        'name' => $name,
        'description' => '',
        'price' => max(0, $price),
    ];

    return $mealPlans;
}

/**
 * Save manually entered quotation hotels to Hotel Master for future reuse.
 *
 * @param array<int, array<string, mixed>> $hotels
 * @return array<int, array<string, mixed>>
 */
function crmSyncManualQuotationHotelsToMaster(mysqli $conn, array $hotels, string $destinationName = ''): array
{
    if (empty($hotels)) {
        return $hotels;
    }

    crmEnsureHotelTables($conn);

    $destinationId = crmResolveDestinationIdByName($conn, $destinationName);
    $createdById = (int) ($_SESSION['id'] ?? 0);
    $createdByName = trim((string) ($_SESSION['name'] ?? ''));

    foreach ($hotels as &$hotel) {
        if (!is_array($hotel)) {
            continue;
        }

        $isManual = !empty($hotel['is_manual']) || !empty($hotel['manual']);
        $existingHotelId = (int) ($hotel['hotel_id'] ?? 0);
        if (!$isManual && $existingHotelId > 0) {
            continue;
        }
        if ($existingHotelId > 0) {
            continue;
        }

        $cityName = trim((string) ($hotel['city'] ?? ''));
        $hotelName = trim((string) ($hotel['name'] ?? $hotel['hotel_name'] ?? ''));
        if ($cityName === '' || $hotelName === '') {
            continue;
        }

        $cityId = (int) ($hotel['city_id'] ?? 0);
        if ($cityId <= 0) {
            $cityId = crmResolveCityIdByName($conn, $cityName, $destinationId);
        }
        if ($cityId <= 0) {
            continue;
        }

        $destId = $destinationId > 0 ? $destinationId : crmGuessDestinationIdForCity($conn, $cityId);
        if ($destId <= 0) {
            continue;
        }

        $roomType = trim((string) ($hotel['room_type'] ?? ''));
        $mealPlan = trim((string) ($hotel['meal_plan'] ?? 'CP'));
        if ($mealPlan === '') {
            $mealPlan = 'CP';
        }
        $rate = (float) ($hotel['rate'] ?? $hotel['amount'] ?? 0);

        $find = $conn->prepare('SELECT `id`, `room_types_json`, `meal_plans_json` FROM `crm_hotels` WHERE `city_id` = ? AND LOWER(`hotel_name`) = LOWER(?) AND `is_active` = 1 LIMIT 1');
        if (!$find) {
            continue;
        }
        $find->bind_param('is', $cityId, $hotelName);
        $find->execute();
        $findRes = $find->get_result();
        $existing = $findRes ? $findRes->fetch_assoc() : null;
        $find->close();

        if ($existing) {
            $hotelId = (int) ($existing['id'] ?? 0);
            $roomTypes = json_decode($existing['room_types_json'] ?? '[]', true);
            $mealPlans = json_decode($existing['meal_plans_json'] ?? '[]', true);
            $roomTypes = crmMergeHotelRoomType(is_array($roomTypes) ? $roomTypes : [], $roomType, $rate);
            $mealPlans = crmMergeHotelMealPlan(is_array($mealPlans) ? $mealPlans : [], $mealPlan, 0);
            $roomTypesJson = json_encode($roomTypes, JSON_UNESCAPED_UNICODE);
            $mealPlansJson = json_encode($mealPlans, JSON_UNESCAPED_UNICODE);

            $upd = $conn->prepare('UPDATE `crm_hotels` SET `room_types_json` = ?, `meal_plans_json` = ? WHERE `id` = ? LIMIT 1');
            if ($upd) {
                $upd->bind_param('ssi', $roomTypesJson, $mealPlansJson, $hotelId);
                $upd->execute();
                $upd->close();
            }
        } else {
            $roomTypes = crmMergeHotelRoomType([], $roomType, $rate);
            $mealPlans = crmMergeHotelMealPlan([], $mealPlan, 0);
            $roomTypesJson = json_encode($roomTypes, JSON_UNESCAPED_UNICODE);
            $mealPlansJson = json_encode($mealPlans, JSON_UNESCAPED_UNICODE);
            $starCategory = '3 Star';
            $starRating = 3.0;
            $isDefault = 0;
            $reviewLink = '';
            $address = '';
            $imagePath = '';

            $ins = $conn->prepare(
                'INSERT INTO `crm_hotels`
                    (`destination_id`, `city_id`, `hotel_name`, `is_default`, `star_category`, `star_rating`,
                     `review_link`, `address`, `image_path`, `room_types_json`, `meal_plans_json`,
                     `created_by_id`, `created_by_name`)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            if (!$ins) {
                continue;
            }
            $ins->bind_param(
                'iisisdsssssis',
                $destId,
                $cityId,
                $hotelName,
                $isDefault,
                $starCategory,
                $starRating,
                $reviewLink,
                $address,
                $imagePath,
                $roomTypesJson,
                $mealPlansJson,
                $createdById,
                $createdByName
            );
            if (!$ins->execute()) {
                $ins->close();
                continue;
            }
            $hotelId = (int) $ins->insert_id;
            $ins->close();
        }

        if ($hotelId > 0) {
            $hotel['hotel_id'] = $hotelId;
            $hotel['city_id'] = $cityId;
            $hotel['is_manual'] = 0;
            unset($hotel['manual']);
        }
    }
    unset($hotel);

    return $hotels;
}
