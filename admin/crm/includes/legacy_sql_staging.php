<?php

/**
 * Load legacy dashboard rows from u560130840_dashboard.sql into temporary
 * staging tables inside the CRM database (no separate database required).
 */

if (!function_exists('crmLegacySqlFilePath')) {
    function crmLegacySqlFilePath(): string
    {
        static $resolved = null;
        if ($resolved !== null) {
            return $resolved;
        }

        $candidates = [
            dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'u560130840_dashboard.sql',
            dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'u560130840_dashboard.sql',
            dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'legacy_dashboard.sql',
        ];

        foreach ($candidates as $path) {
            if (is_readable($path)) {
                $resolved = $path;

                return $resolved;
            }
        }

        $resolved = '';

        return $resolved;
    }
}

if (!function_exists('crmLegacyStagingTableMap')) {
    /** @return array{suppliers:string,customers:string,quotation:string,places:string} */
    function crmLegacyStagingTableMap(): array
    {
        return [
            'suppliers' => 'crm_legacy_staging_suppliers',
            'customers' => 'crm_legacy_staging_customers',
            'quotation' => 'crm_legacy_staging_quotation',
            'places' => 'crm_legacy_staging_places',
        ];
    }
}

if (!function_exists('crmLegacyStagingCreateTables')) {
    function crmLegacyStagingCreateTables(mysqli $conn): void
    {
        $map = crmLegacyStagingTableMap();

        $conn->query('DROP TABLE IF EXISTS `' . $map['quotation'] . '`');
        $conn->query('DROP TABLE IF EXISTS `' . $map['suppliers'] . '`');
        $conn->query('DROP TABLE IF EXISTS `' . $map['customers'] . '`');
        $conn->query('DROP TABLE IF EXISTS `' . $map['places'] . '`');

        $conn->query("CREATE TABLE `{$map['places']}` (
            `id` int(10) unsigned NOT NULL,
            `description` varchar(300) NOT NULL,
            `place_id` varchar(255) NOT NULL DEFAULT '',
            `created_at` timestamp NULL DEFAULT NULL,
            `updated_at` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $conn->query("CREATE TABLE `{$map['customers']}` (
            `id` int(10) unsigned NOT NULL,
            `name` varchar(50) NOT NULL,
            `mobile_no` varchar(13) NOT NULL,
            `email` varchar(100) DEFAULT NULL,
            `card_no` varchar(16) NOT NULL DEFAULT '',
            `address` varchar(255) DEFAULT NULL,
            `points` int(10) unsigned NOT NULL DEFAULT 0,
            `created_at` timestamp NULL DEFAULT NULL,
            `updated_at` timestamp NULL DEFAULT NULL,
            `title` varchar(10) DEFAULT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $conn->query("CREATE TABLE `{$map['suppliers']}` (
            `id` int(10) unsigned NOT NULL,
            `name` varchar(255) NOT NULL,
            `contacts` text NOT NULL,
            `website` varchar(255) DEFAULT NULL,
            `address` varchar(500) DEFAULT NULL,
            `is_enabled` tinyint(1) NOT NULL DEFAULT 1,
            `created_at` timestamp NULL DEFAULT NULL,
            `updated_at` timestamp NULL DEFAULT NULL,
            `place_id` int(10) unsigned DEFAULT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $conn->query("CREATE TABLE `{$map['quotation']}` (
            `id` int(10) unsigned NOT NULL,
            `guest_name` varchar(255) NOT NULL,
            `ref_name` varchar(255) DEFAULT NULL,
            `mobile_no` varchar(15) NOT NULL,
            `email` varchar(100) DEFAULT NULL,
            `destination` varchar(255) NOT NULL,
            `tentative_date` date NOT NULL,
            `nights` int(10) unsigned NOT NULL,
            `children` int(10) unsigned DEFAULT NULL,
            `adults` int(10) unsigned NOT NULL,
            `itineraries` mediumtext DEFAULT NULL,
            `flights` text DEFAULT NULL,
            `trains` text DEFAULT NULL,
            `hotels` text DEFAULT NULL,
            `terms_conditions` text DEFAULT NULL,
            `children_ages` varchar(191) DEFAULT NULL,
            `inclusion` text DEFAULT NULL,
            `exclusion` text DEFAULT NULL,
            `payment_policy` text DEFAULT NULL,
            `cancellation_policy` text DEFAULT NULL,
            `costing` text DEFAULT NULL,
            `ref_id` varchar(50) NOT NULL,
            `total` int(10) unsigned NOT NULL,
            `adult_cost` int(10) unsigned DEFAULT NULL,
            `child_cost` int(10) unsigned DEFAULT NULL,
            `created_at` timestamp NULL DEFAULT NULL,
            `updated_at` timestamp NULL DEFAULT NULL,
            `is_confirmed` tinyint(1) NOT NULL DEFAULT 0,
            `hide_note` tinyint(1) NOT NULL DEFAULT 0,
            `other_details` text DEFAULT NULL,
            `is_feedback_sent` tinyint(1) NOT NULL DEFAULT 0,
            `is_feedback_received` tinyint(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
}

if (!function_exists('crmLegacyRewriteInsertSql')) {
    function crmLegacyRewriteInsertSql(string $sql, string $legacyTable, string $stagingTable): string
    {
        $sql = preg_replace(
            '/INSERT\s+INTO\s+`' . preg_quote($legacyTable, '/') . '`/i',
            'INSERT INTO `' . $stagingTable . '`',
            $sql,
            1
        );

        return $sql ?? '';
    }
}

if (!function_exists('crmLegacyExtractInsertStatements')) {
    /** @return array<int,string> */
    function crmLegacyExtractInsertStatements(string $filePath, string $legacyTable): array
    {
        $handle = fopen($filePath, 'rb');
        if (!$handle) {
            return [];
        }

        $inserts = [];
        $needle = 'INSERT INTO `' . $legacyTable . '`';
        $buffer = '';

        while (($line = fgets($handle)) !== false) {
            if ($buffer === '' && strpos($line, $needle) !== 0) {
                continue;
            }

            $buffer .= $line;
            if (preg_match('/;\s*$/', rtrim($line))) {
                $inserts[] = $buffer;
                $buffer = '';
            }
        }

        if ($buffer !== '') {
            $inserts[] = $buffer;
        }

        fclose($handle);

        return $inserts;
    }
}

if (!function_exists('crmLegacyStageTableFromSql')) {
    /** @return array{rows:int,errors:array<int,string>} */
    function crmLegacyStageTableFromSql(mysqli $conn, string $filePath, string $legacyTable, string $stagingTable): array
    {
        $stats = ['rows' => 0, 'errors' => []];
        $statements = crmLegacyExtractInsertStatements($filePath, $legacyTable);

        if (!$statements) {
            $stats['errors'][] = 'No INSERT data found for `' . $legacyTable . '` in SQL file.';

            return $stats;
        }

        foreach ($statements as $statement) {
            $sql = crmLegacyRewriteInsertSql(trim($statement), $legacyTable, $stagingTable);
            if ($sql === '') {
                continue;
            }

            if (!$conn->query($sql)) {
                $stats['errors'][] = 'Staging `' . $legacyTable . '`: ' . $conn->error;
                continue;
            }

            $stats['rows'] += max(0, (int) $conn->affected_rows);
        }

        return $stats;
    }
}

if (!function_exists('crmLegacyStagingCounts')) {
    /** @return array<string,int> */
    function crmLegacyStagingCounts(mysqli $conn): array
    {
        $counts = [];
        foreach (crmLegacyStagingTableMap() as $legacy => $staging) {
            $res = $conn->query('SELECT COUNT(*) AS c FROM `' . $conn->real_escape_string($staging) . '`');
            $counts[$legacy] = ($res && ($row = $res->fetch_assoc())) ? (int) $row['c'] : 0;
        }

        return $counts;
    }
}

if (!function_exists('crmLegacyStagingTablesExist')) {
    function crmLegacyStagingTablesExist(mysqli $conn): bool
    {
        $map = crmLegacyStagingTableMap();
        $staging = $conn->real_escape_string($map['quotation']);
        $res = $conn->query("SHOW TABLES LIKE '{$staging}'");

        return (bool) ($res && $res->num_rows > 0);
    }
}

if (!function_exists('crmLegacyDropStagingTables')) {
    function crmLegacyDropStagingTables(mysqli $conn): void
    {
        foreach (crmLegacyStagingTableMap() as $staging) {
            $conn->query('DROP TABLE IF EXISTS `' . $conn->real_escape_string($staging) . '`');
        }
    }
}

if (!function_exists('crmLegacyStageFromSqlFile')) {
    /**
     * @return array{success:bool,message:string,file:string,counts:array<string,int>,errors:array<int,string>}
     */
    function crmLegacyStageFromSqlFile(mysqli $conn, ?string $filePath = null): array
    {
        $filePath = $filePath !== null && $filePath !== '' ? $filePath : crmLegacySqlFilePath();
        if ($filePath === '' || !is_readable($filePath)) {
            return [
                'success' => false,
                'message' => 'Legacy SQL file not found. Upload u560130840_dashboard.sql to the project root (same folder as admin/).',
                'file' => '',
                'counts' => [],
                'errors' => [],
            ];
        }

        @set_time_limit(600);
        @ini_set('memory_limit', '512M');

        $conn->query('SET FOREIGN_KEY_CHECKS = 0');
        $conn->query('SET SESSION max_allowed_packet = 67108864');

        crmLegacyStagingCreateTables($conn);

        $map = crmLegacyStagingTableMap();
        $allErrors = [];
        $order = ['places', 'suppliers', 'customers', 'quotation'];

        foreach ($order as $legacyTable) {
            $stageStats = crmLegacyStageTableFromSql($conn, $filePath, $legacyTable, $map[$legacyTable]);
            if ($stageStats['errors']) {
                $allErrors = array_merge($allErrors, $stageStats['errors']);
            }
        }

        $conn->query('SET FOREIGN_KEY_CHECKS = 1');
        $counts = crmLegacyStagingCounts($conn);

        if ((int) ($counts['quotation'] ?? 0) === 0 && (int) ($counts['suppliers'] ?? 0) === 0) {
            return [
                'success' => false,
                'message' => 'Could not load legacy data from SQL file.',
                'file' => basename($filePath),
                'counts' => $counts,
                'errors' => $allErrors,
            ];
        }

        return [
            'success' => true,
            'message' => 'Legacy data loaded into temporary staging tables.',
            'file' => basename($filePath),
            'counts' => $counts,
            'errors' => $allErrors,
        ];
    }
}

if (!function_exists('crmLegacyEstimateCountsFromFile')) {
    /** @return array<string,int> */
    function crmLegacyEstimateCountsFromFile(string $filePath): array
    {
        $counts = ['suppliers' => 0, 'customers' => 0, 'quotation' => 0, 'places' => 0];
        foreach (array_keys($counts) as $table) {
            foreach (crmLegacyExtractInsertStatements($filePath, $table) as $sql) {
                if (preg_match('/\)\s*;\s*$/s', $sql)) {
                    $counts[$table] += max(1, substr_count($sql, '),(') + 1);
                }
            }
        }

        return $counts;
    }
}
