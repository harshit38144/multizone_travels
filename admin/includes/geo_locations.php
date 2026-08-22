<?php

/**
 * Geo location tables (countries, states, cities) + CountriesNow API helpers.
 */

if (!function_exists('geoEnsureTables')) {
    function geoEnsureTables(mysqli $conn): void
    {
        $conn->query("CREATE TABLE IF NOT EXISTS `countries` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(150) NOT NULL,
            `iso2` CHAR(2) DEFAULT NULL,
            `iso3` CHAR(3) DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `uk_countries_name` (`name`),
            KEY `idx_countries_iso2` (`iso2`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $countryExtraCols = [
            'is_deleted' => "TINYINT(1) NOT NULL DEFAULT 0 AFTER `created_at`",
            'deleted_at' => "DATETIME DEFAULT NULL AFTER `is_deleted`",
        ];
        foreach ($countryExtraCols as $col => $ddl) {
            $chk = $conn->query("SHOW COLUMNS FROM `countries` LIKE '" . $conn->real_escape_string($col) . "'");
            if ($chk && $chk->num_rows === 0) {
                $conn->query("ALTER TABLE `countries` ADD `" . $col . "` " . $ddl);
            }
        }
        $countryIdxChk = $conn->query("SHOW INDEX FROM `countries` WHERE Key_name = 'idx_countries_is_deleted'");
        if ($countryIdxChk && $countryIdxChk->num_rows === 0) {
            $conn->query("ALTER TABLE `countries` ADD KEY `idx_countries_is_deleted` (`is_deleted`)");
        }

        $conn->query("CREATE TABLE IF NOT EXISTS `states` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `country_id` INT UNSIGNED NOT NULL,
            `name` VARCHAR(150) NOT NULL,
            `state_code` VARCHAR(20) DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `uk_states_country_name` (`country_id`, `name`),
            KEY `idx_states_country_id` (`country_id`),
            CONSTRAINT `fk_states_country` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $conn->query("CREATE TABLE IF NOT EXISTS `cities` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `country_id` INT UNSIGNED NOT NULL,
            `state_id` INT UNSIGNED DEFAULT NULL,
            `name` VARCHAR(150) NOT NULL,
            `airport_code` VARCHAR(8) DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `uk_cities_scope_name` (`country_id`, `state_id`, `name`),
            KEY `idx_cities_country_id` (`country_id`),
            KEY `idx_cities_state_id` (`state_id`),
            CONSTRAINT `fk_cities_country` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_cities_state` FOREIGN KEY (`state_id`) REFERENCES `states` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $cityExtraCols = [
            'is_active' => "TINYINT(1) NOT NULL DEFAULT 1 AFTER `airport_code`",
            'timezone' => "VARCHAR(64) DEFAULT NULL AFTER `is_active`",
            'region' => "VARCHAR(100) DEFAULT NULL AFTER `timezone`",
            'created_by' => "VARCHAR(120) DEFAULT NULL AFTER `created_at`",
            'updated_at' => "TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER `created_by`",
            'is_deleted' => "TINYINT(1) NOT NULL DEFAULT 0 AFTER `updated_at`",
            'deleted_at' => "DATETIME DEFAULT NULL AFTER `is_deleted`",
            'deleted_by_country' => "TINYINT(1) NOT NULL DEFAULT 0 AFTER `deleted_at`",
        ];
        foreach ($cityExtraCols as $col => $ddl) {
            $chk = $conn->query("SHOW COLUMNS FROM `cities` LIKE '" . $conn->real_escape_string($col) . "'");
            if ($chk && $chk->num_rows === 0) {
                $conn->query("ALTER TABLE `cities` ADD `" . $col . "` " . $ddl);
            }
        }
        $idxChk = $conn->query("SHOW INDEX FROM `cities` WHERE Key_name = 'idx_cities_is_deleted'");
        if ($idxChk && $idxChk->num_rows === 0) {
            $conn->query("ALTER TABLE `cities` ADD KEY `idx_cities_is_deleted` (`is_deleted`)");
        }
    }
}

if (!function_exists('geoCountriesNowGet')) {
    function geoCountriesNowGet(string $path): array
    {
        $url = 'https://countriesnow.space/api/v0.1/countries' . $path;
        $ctx = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 120,
                'header' => "Accept: application/json\r\n",
            ],
        ]);

        $raw = @file_get_contents($url, false, $ctx);
        if ($raw === false) {
            throw new RuntimeException('Failed to fetch: ' . $url);
        }

        $json = json_decode($raw, true);
        if (!is_array($json) || !empty($json['error'])) {
            $msg = is_array($json) ? ($json['msg'] ?? 'Unknown API error') : 'Invalid JSON';
            throw new RuntimeException($msg);
        }

        return $json;
    }
}

if (!function_exists('geoCountriesNowPost')) {
    function geoCountriesNowPost(string $path, array $payload): array
    {
        $url = 'https://countriesnow.space/api/v0.1/countries' . $path;
        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'timeout' => 120,
                'header' => "Content-Type: application/json\r\nAccept: application/json\r\n",
                'content' => json_encode($payload),
            ],
        ]);

        $raw = @file_get_contents($url, false, $ctx);
        if ($raw === false) {
            throw new RuntimeException('Failed to POST: ' . $url);
        }

        $json = json_decode($raw, true);
        if (!is_array($json) || !empty($json['error'])) {
            $msg = is_array($json) ? ($json['msg'] ?? 'Unknown API error') : 'Invalid JSON';
            throw new RuntimeException($msg);
        }

        return $json;
    }
}

if (!function_exists('geoImportCountriesAndStates')) {
    function geoImportCountriesAndStates(mysqli $conn, ?callable $log = null): array
    {
        geoEnsureTables($conn);

        $statesPayload = geoCountriesNowGet('/states');
        $countriesPayload = geoCountriesNowGet('');
        $isoByName = [];

        foreach ($countriesPayload['data'] ?? [] as $row) {
            $name = trim((string) ($row['country'] ?? ''));
            if ($name === '') {
                continue;
            }
            $isoByName[$name] = [
                'iso2' => strtoupper(trim((string) ($row['iso2'] ?? ''))) ?: null,
                'iso3' => strtoupper(trim((string) ($row['iso3'] ?? ''))) ?: null,
            ];
        }

        $countryStmt = $conn->prepare(
            'INSERT INTO countries (name, iso2, iso3) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE iso2 = VALUES(iso2), iso3 = VALUES(iso3)'
        );
        $stateStmt = $conn->prepare(
            'INSERT INTO states (country_id, name, state_code) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE state_code = VALUES(state_code)'
        );

        $countryCount = 0;
        $stateCount = 0;
        $countryIdMap = [];

        foreach ($statesPayload['data'] ?? [] as $row) {
            $countryName = trim((string) ($row['name'] ?? ''));
            if ($countryName === '') {
                continue;
            }

            $iso2 = strtoupper(trim((string) ($row['iso2'] ?? ''))) ?: ($isoByName[$countryName]['iso2'] ?? null);
            $iso3 = strtoupper(trim((string) ($row['iso3'] ?? ''))) ?: ($isoByName[$countryName]['iso3'] ?? null);

            $countryStmt->bind_param('sss', $countryName, $iso2, $iso3);
            $countryStmt->execute();
            $countryCount++;

            $countryId = (int) $conn->insert_id;
            if ($countryId === 0) {
                $lookup = $conn->prepare('SELECT id FROM countries WHERE name = ? LIMIT 1');
                $lookup->bind_param('s', $countryName);
                $lookup->execute();
                $res = $lookup->get_result();
                $countryId = (int) ($res->fetch_assoc()['id'] ?? 0);
                $lookup->close();
            }

            if ($countryId <= 0) {
                continue;
            }

            $countryIdMap[$countryName] = $countryId;

            foreach ($row['states'] ?? [] as $stateRow) {
                $stateName = trim((string) ($stateRow['name'] ?? ''));
                if ($stateName === '') {
                    continue;
                }
                $stateCode = trim((string) ($stateRow['state_code'] ?? '')) ?: null;
                $stateStmt->bind_param('iss', $countryId, $stateName, $stateCode);
                $stateStmt->execute();
                $stateCount++;
            }

            if ($log) {
                $log('Country saved: ' . $countryName . ' (' . count($row['states'] ?? []) . ' states)');
            }
        }

        $countryStmt->close();
        $stateStmt->close();

        return [
            'countries' => $countryCount,
            'states' => $stateCount,
            'country_map' => $countryIdMap,
        ];
    }
}

if (!function_exists('geoImportCitiesByCountry')) {
    function geoImportCitiesByCountry(mysqli $conn, ?callable $log = null, int $delayMs = 150): array
    {
        geoEnsureTables($conn);

        $res = $conn->query('SELECT id, name FROM countries ORDER BY name ASC');
        $countries = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

        $cityStmt = $conn->prepare(
            'INSERT IGNORE INTO cities (country_id, state_id, name) VALUES (?, NULL, ?)'
        );

        $cityCount = 0;
        $countryCount = 0;

        foreach ($countries as $country) {
            $countryId = (int) $country['id'];
            $countryName = (string) $country['name'];

            try {
                $payload = geoCountriesNowPost('/cities', ['country' => $countryName]);
            } catch (Throwable $e) {
                if ($log) {
                    $log('Skipped cities for ' . $countryName . ': ' . $e->getMessage());
                }
                continue;
            }

            $names = $payload['data'] ?? [];
            foreach ($names as $cityName) {
                $cityName = trim((string) $cityName);
                if ($cityName === '') {
                    continue;
                }
                $cityStmt->bind_param('is', $countryId, $cityName);
                $cityStmt->execute();
                if ($cityStmt->affected_rows > 0) {
                    $cityCount++;
                }
            }

            $countryCount++;
            if ($log) {
                $log('Cities saved for ' . $countryName . ': ' . count($names));
            }

            if ($delayMs > 0) {
                usleep($delayMs * 1000);
            }
        }

        $cityStmt->close();

        return [
            'countries_processed' => $countryCount,
            'cities' => $cityCount,
        ];
    }
}

if (!function_exists('geoImportCitiesByState')) {
    function geoImportCitiesByState(mysqli $conn, ?callable $log = null, int $delayMs = 150): array
    {
        geoEnsureTables($conn);

        $res = $conn->query(
            'SELECT s.id AS state_id, s.name AS state_name, c.id AS country_id, c.name AS country_name
             FROM states s
             INNER JOIN countries c ON c.id = s.country_id
             ORDER BY c.name ASC, s.name ASC'
        );
        $states = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

        $assignCityStmt = $conn->prepare(
            'UPDATE cities
             SET state_id = ?, updated_at = NOW()
             WHERE country_id = ? AND state_id IS NULL AND name = ?
             ORDER BY id ASC LIMIT 1'
        );
        $existingCityStmt = $conn->prepare(
            'SELECT id FROM cities
             WHERE country_id = ? AND state_id = ? AND name = ?
             LIMIT 1'
        );
        $insertCityStmt = $conn->prepare(
            'INSERT IGNORE INTO cities (country_id, state_id, name) VALUES (?, ?, ?)'
        );

        $cityCount = 0;
        $stateCount = 0;

        foreach ($states as $state) {
            $countryId = (int) $state['country_id'];
            $stateId = (int) $state['state_id'];
            $countryName = (string) $state['country_name'];
            $stateName = (string) $state['state_name'];

            try {
                $payload = geoCountriesNowPost('/state/cities', [
                    'country' => $countryName,
                    'state' => $stateName,
                ]);
            } catch (Throwable $e) {
                if ($log) {
                    $log('Skipped ' . $countryName . ' / ' . $stateName . ': ' . $e->getMessage());
                }
                continue;
            }

            $names = $payload['data'] ?? [];
            foreach ($names as $cityName) {
                $cityName = trim((string) $cityName);
                if ($cityName === '') {
                    continue;
                }

                $existingCityStmt->bind_param('iis', $countryId, $stateId, $cityName);
                $existingCityStmt->execute();
                $existingCityStmt->store_result();
                if ($existingCityStmt->num_rows > 0) {
                    $existingCityStmt->free_result();
                    continue;
                }
                $existingCityStmt->free_result();

                $assignCityStmt->bind_param('iis', $stateId, $countryId, $cityName);
                $assignCityStmt->execute();
                $affected = (int) $assignCityStmt->affected_rows;

                if ($affected === 0) {
                    $insertCityStmt->bind_param('iis', $countryId, $stateId, $cityName);
                    $insertCityStmt->execute();
                    $affected = (int) $insertCityStmt->affected_rows;
                }
                if ($affected > 0) {
                    $cityCount += $affected;
                }
            }

            $stateCount++;
            if ($log && ($stateCount % 25 === 0 || $stateCount === count($states))) {
                $log('State cities progress: ' . $stateCount . '/' . count($states) . ' (' . $cityCount . ' cities)');
            }

            if ($delayMs > 0) {
                usleep($delayMs * 1000);
            }
        }

        $assignCityStmt->close();
        $existingCityStmt->close();
        $insertCityStmt->close();

        return [
            'states_processed' => $stateCount,
            'cities' => $cityCount,
        ];
    }
}

if (!function_exists('geoLocationCounts')) {
    function geoLocationCounts(mysqli $conn): array
    {
        geoEnsureTables($conn);

        $counts = ['countries' => 0, 'states' => 0, 'cities' => 0];
        foreach (array_keys($counts) as $table) {
            $res = $conn->query('SELECT COUNT(*) AS c FROM `' . $table . '`');
            if ($res) {
                $counts[$table] = (int) ($res->fetch_assoc()['c'] ?? 0);
            }
        }

        return $counts;
    }
}

if (!function_exists('geoCitiesCacheDir')) {
    function geoCitiesCacheDir(): string
    {
        $dir = dirname(__DIR__) . '/cache/geo_cities';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        return $dir;
    }
}

if (!function_exists('geoFetchCitiesForCountry')) {
    /**
     * @return list<string>
     */
    function geoFetchCitiesForCountry(string $country): array
    {
        static $memoryCache = [];
        $country = trim($country);
        if ($country === '') {
            return [];
        }
        if (isset($memoryCache[$country])) {
            return $memoryCache[$country];
        }

        $cacheFile = geoCitiesCacheDir() . '/' . md5(mb_strtolower($country)) . '.json';
        $cacheTtl = 7 * 86400;
        if (is_file($cacheFile) && (time() - (int) filemtime($cacheFile)) < $cacheTtl) {
            $cached = json_decode((string) file_get_contents($cacheFile), true);
            if (is_array($cached)) {
                $memoryCache[$country] = $cached;

                return $cached;
            }
        }

        $cities = [];
        try {
            $payload = geoCountriesNowGet('/cities/q?country=' . rawurlencode($country));
            if (is_array($payload['data'] ?? null)) {
                $cities = $payload['data'];
            }
        } catch (Throwable $e) {
            try {
                $payload = geoCountriesNowPost('/cities', ['country' => $country]);
                if (is_array($payload['data'] ?? null)) {
                    $cities = $payload['data'];
                }
            } catch (Throwable $e2) {
                $cities = [];
            }
        }

        if ($cities) {
            @file_put_contents($cacheFile, json_encode($cities, JSON_UNESCAPED_UNICODE));
        }

        $memoryCache[$country] = $cities;

        return $cities;
    }
}

if (!function_exists('geoFetchCountriesList')) {
    /**
     * @return list<string>
     */
    function geoFetchCountriesList(): array
    {
        static $memoryCache = null;
        if (is_array($memoryCache)) {
            return $memoryCache;
        }

        $cacheFile = geoCitiesCacheDir() . '/_countries.json';
        $cacheTtl = 30 * 86400;
        if (is_file($cacheFile) && (time() - (int) filemtime($cacheFile)) < $cacheTtl) {
            $cached = json_decode((string) file_get_contents($cacheFile), true);
            if (is_array($cached)) {
                $memoryCache = $cached;

                return $cached;
            }
        }

        $countries = [];
        try {
            $payload = geoCountriesNowGet('');
            foreach ($payload['data'] ?? [] as $row) {
                $name = trim((string) ($row['country'] ?? ''));
                if ($name !== '') {
                    $countries[] = $name;
                }
            }
        } catch (Throwable $e) {
            $countries = ['India'];
        }

        if ($countries) {
            sort($countries, SORT_NATURAL | SORT_FLAG_CASE);
            @file_put_contents($cacheFile, json_encode($countries, JSON_UNESCAPED_UNICODE));
        }

        $memoryCache = $countries;

        return $countries;
    }
}

if (!function_exists('geoSearchCitiesApiResolveCountries')) {
    /**
     * @return list<string>
     */
    function geoSearchCitiesApiResolveCountries(string $query, ?string $countryName = null, int $maxCountries = 3): array
    {
        if ($countryName !== null && trim($countryName) !== '') {
            return [trim($countryName)];
        }

        $countries = [];
        $query = trim($query);

        foreach (geoFetchCountriesList() as $name) {
            if ($query !== '' && mb_stripos($name, $query) !== false) {
                $countries[] = $name;
            }
        }

        $priority = [
            'India',
            'United Arab Emirates',
            'Thailand',
            'Singapore',
            'Malaysia',
            'Nepal',
            'Sri Lanka',
            'Indonesia',
            'Vietnam',
            'Maldives',
        ];
        foreach ($priority as $name) {
            if (!in_array($name, $countries, true)) {
                $countries[] = $name;
            }
        }

        return array_slice(array_values(array_unique($countries)), 0, max(1, $maxCountries));
    }
}

if (!function_exists('geoCityMatchScore')) {
    function geoCityMatchScore(string $cityName, string $query): ?int
    {
        $cityLower = mb_strtolower(trim($cityName));
        $queryLower = mb_strtolower(trim($query));
        if ($queryLower === '' || $cityLower === '') {
            return null;
        }
        if ($cityLower === $queryLower) {
            return 0;
        }
        if (mb_strpos($cityLower, $queryLower) === 0) {
            return 1;
        }
        if (preg_match('/(?:^|[\s,(-])' . preg_quote($queryLower, '/') . '/u', $cityLower)) {
            return 2;
        }
        if (mb_strpos($cityLower, $queryLower) !== false) {
            return 3;
        }

        return null;
    }
}

if (!function_exists('geoSearchCitiesFromApi')) {
    /**
     * Search cities via CountriesNow API (no API key required).
     *
     * @return list<array{id:int,name:string,country_name:string,state_name:string,airport_code:string,source:string}>
     */
    function geoSearchCitiesFromApi(string $query, ?string $countryName = null, int $limit = 20): array
    {
        $query = trim($query);
        if ($query === '' || $limit <= 0) {
            return [];
        }

        $countriesToSearch = [];
        if ($countryName !== null && trim($countryName) !== '') {
            $countriesToSearch = [trim($countryName)];
        } else {
            $countriesToSearch = geoSearchCitiesApiResolveCountries($query, null, 1);
        }

        $candidates = [];
        $seen = [];

        $collectMatches = static function (string $country) use (&$candidates, &$seen, $query, $limit): void {
            foreach (geoFetchCitiesForCountry($country) as $cityName) {
                if (count($candidates) >= $limit * 3) {
                    break;
                }
                $cityName = trim((string) $cityName);
                if ($cityName === '') {
                    continue;
                }
                if (mb_stripos($cityName, $query) === false) {
                    continue;
                }
                $score = geoCityMatchScore($cityName, $query);
                if ($score === null) {
                    continue;
                }
                $key = mb_strtolower($cityName . "\0" . $country);
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $candidates[] = [
                    'score' => $score,
                    'id' => 0,
                    'name' => $cityName,
                    'country_name' => $country,
                    'state_name' => '',
                    'airport_code' => '',
                    'source' => 'api',
                ];
            }
        };

        foreach ($countriesToSearch as $country) {
            $collectMatches($country);
            if (count($candidates) >= $limit) {
                break;
            }
        }

        if (count($candidates) < $limit && ($countryName === null || trim($countryName) === '')) {
            foreach (geoSearchCitiesApiResolveCountries($query, null, 5) as $country) {
                if (in_array($country, $countriesToSearch, true)) {
                    continue;
                }
                $collectMatches($country);
                if (count($candidates) >= $limit) {
                    break;
                }
            }
        }

        usort($candidates, static function (array $a, array $b): int {
            if ($a['score'] !== $b['score']) {
                return $a['score'] <=> $b['score'];
            }

            return strcasecmp($a['name'], $b['name']);
        });

        $results = [];
        foreach ($candidates as $row) {
            if (count($results) >= $limit) {
                break;
            }
            unset($row['score']);
            $results[] = $row;
        }

        return $results;
    }
}
