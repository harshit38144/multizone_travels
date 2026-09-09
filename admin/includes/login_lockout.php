<?php
/**
 * Hybrid admin login lockout:
 * - Track failures by IP + username
 * - Escalate: 3 fails → 30s, 5 → 5min, 10 → 15min
 * - Extra IP spray protection after many failures across usernames
 */

if (!function_exists('adminLoginClientIp')) {
    function adminLoginClientIp(): string
    {
        $candidates = [];
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            $candidates[] = (string) $_SERVER['HTTP_CF_CONNECTING_IP'];
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $parts = explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR']);
            $candidates[] = trim((string) ($parts[0] ?? ''));
        }
        if (!empty($_SERVER['REMOTE_ADDR'])) {
            $candidates[] = (string) $_SERVER['REMOTE_ADDR'];
        }

        foreach ($candidates as $ip) {
            $ip = trim($ip);
            if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }

        return '0.0.0.0';
    }
}

if (!function_exists('adminLoginNormalizeUser')) {
    function adminLoginNormalizeUser(string $username): string
    {
        $username = trim(mb_strtolower($username));
        if (mb_strlen($username) > 120) {
            $username = mb_substr($username, 0, 120);
        }

        return $username !== '' ? $username : '(empty)';
    }
}

if (!function_exists('adminLoginEnsureLockoutTable')) {
    function adminLoginEnsureLockoutTable(mysqli $conn): void
    {
        static $ready = false;
        if ($ready) {
            return;
        }

        $conn->query(
            "CREATE TABLE IF NOT EXISTS `admin_login_attempts` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `ip_address` VARCHAR(45) NOT NULL,
                `username_key` VARCHAR(120) NOT NULL,
                `fail_count` INT UNSIGNED NOT NULL DEFAULT 0,
                `window_started_at` DATETIME NOT NULL,
                `locked_until` DATETIME DEFAULT NULL,
                `last_failed_at` DATETIME DEFAULT NULL,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_ip_user` (`ip_address`, `username_key`),
                KEY `idx_ip` (`ip_address`),
                KEY `idx_locked_until` (`locked_until`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $ready = true;
    }
}

if (!function_exists('adminLoginLockSecondsForFails')) {
    function adminLoginLockSecondsForFails(int $failCount): int
    {
        if ($failCount >= 10) {
            return 15 * 60;
        }
        if ($failCount >= 5) {
            return 5 * 60;
        }
        if ($failCount >= 3) {
            return 30;
        }

        return 0;
    }
}

if (!function_exists('adminLoginFormatWait')) {
    function adminLoginFormatWait(int $seconds): string
    {
        $seconds = max(0, $seconds);
        if ($seconds < 60) {
            return $seconds . ' second' . ($seconds === 1 ? '' : 's');
        }
        $mins = (int) ceil($seconds / 60);
        if ($mins < 60) {
            return $mins . ' minute' . ($mins === 1 ? '' : 's');
        }
        $hours = (int) ceil($mins / 60);

        return $hours . ' hour' . ($hours === 1 ? '' : 's');
    }
}

if (!function_exists('adminLoginFetchAttempt')) {
    /** @return array<string,mixed>|null */
    function adminLoginFetchAttempt(mysqli $conn, string $ip, string $usernameKey): ?array
    {
        adminLoginEnsureLockoutTable($conn);
        $stmt = $conn->prepare(
            'SELECT `id`, `ip_address`, `username_key`, `fail_count`, `window_started_at`, `locked_until`, `last_failed_at`
             FROM `admin_login_attempts`
             WHERE `ip_address` = ? AND `username_key` = ?
             LIMIT 1'
        );
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('ss', $ip, $usernameKey);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        return $row ?: null;
    }
}

if (!function_exists('adminLoginIpFailCountRecent')) {
    function adminLoginIpFailCountRecent(mysqli $conn, string $ip, int $minutes = 15): int
    {
        adminLoginEnsureLockoutTable($conn);
        $stmt = $conn->prepare(
            'SELECT COALESCE(SUM(`fail_count`), 0) AS c
             FROM `admin_login_attempts`
             WHERE `ip_address` = ?
               AND `last_failed_at` >= (NOW() - INTERVAL ? MINUTE)'
        );
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param('si', $ip, $minutes);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        return (int) ($row['c'] ?? 0);
    }
}

if (!function_exists('adminLoginUpsertLock')) {
    function adminLoginUpsertLock(mysqli $conn, string $ip, string $usernameKey, int $failCount, ?string $lockedUntil): void
    {
        adminLoginEnsureLockoutTable($conn);
        $stmt = $conn->prepare(
            'INSERT INTO `admin_login_attempts`
                (`ip_address`, `username_key`, `fail_count`, `window_started_at`, `locked_until`, `last_failed_at`)
             VALUES (?, ?, ?, NOW(), ?, NOW())
             ON DUPLICATE KEY UPDATE
                `fail_count` = VALUES(`fail_count`),
                `locked_until` = VALUES(`locked_until`),
                `last_failed_at` = NOW()'
        );
        if (!$stmt) {
            return;
        }
        $stmt->bind_param('ssis', $ip, $usernameKey, $failCount, $lockedUntil);
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('adminLoginGetLockStatus')) {
    /**
     * @return array{locked:bool,remaining:int,fail_count:int,message:string,until:string}
     */
    function adminLoginGetLockStatus(mysqli $conn, string $username): array
    {
        $ip = adminLoginClientIp();
        $userKey = adminLoginNormalizeUser($username);
        $empty = [
            'locked' => false,
            'remaining' => 0,
            'fail_count' => 0,
            'message' => '',
            'until' => '',
        ];

        $row = adminLoginFetchAttempt($conn, $ip, $userKey);
        $now = time();

        $ipFails = adminLoginIpFailCountRecent($conn, $ip, 15);
        if ($ipFails >= 20) {
            $ipLockStmt = $conn->prepare(
                'SELECT MAX(`locked_until`) AS locked_until
                 FROM `admin_login_attempts`
                 WHERE `ip_address` = ? AND `locked_until` IS NOT NULL AND `locked_until` > NOW()'
            );
            $ipUntil = null;
            if ($ipLockStmt) {
                $ipLockStmt->bind_param('s', $ip);
                $ipLockStmt->execute();
                $ipRes = $ipLockStmt->get_result();
                $ipRow = $ipRes ? $ipRes->fetch_assoc() : null;
                $ipLockStmt->close();
                $ipUntil = $ipRow['locked_until'] ?? null;
            }

            if ($ipUntil) {
                $untilTs = strtotime((string) $ipUntil);
                if ($untilTs !== false && $untilTs > $now) {
                    $remaining = $untilTs - $now;
                    return [
                        'locked' => true,
                        'remaining' => $remaining,
                        'fail_count' => $ipFails,
                        'message' => 'Too many failed login attempts from your network. Try again in ' . adminLoginFormatWait($remaining) . '.',
                        'until' => date('c', $untilTs),
                    ];
                }
            } else {
                $lockSecs = 15 * 60;
                $untilSql = date('Y-m-d H:i:s', $now + $lockSecs);
                adminLoginUpsertLock($conn, $ip, $userKey, max(20, (int) ($row['fail_count'] ?? 0)), $untilSql);
                return [
                    'locked' => true,
                    'remaining' => $lockSecs,
                    'fail_count' => $ipFails,
                    'message' => 'Too many failed login attempts from your network. Try again in ' . adminLoginFormatWait($lockSecs) . '.',
                    'until' => date('c', $now + $lockSecs),
                ];
            }
        }

        if (!$row) {
            return $empty;
        }

        $failCount = (int) ($row['fail_count'] ?? 0);
        $lockedUntil = trim((string) ($row['locked_until'] ?? ''));
        if ($lockedUntil !== '' && $lockedUntil !== '0000-00-00 00:00:00') {
            $untilTs = strtotime($lockedUntil);
            if ($untilTs !== false && $untilTs > $now) {
                $remaining = $untilTs - $now;
                return [
                    'locked' => true,
                    'remaining' => $remaining,
                    'fail_count' => $failCount,
                    'message' => 'Too many failed login attempts. Try again in ' . adminLoginFormatWait($remaining) . '.',
                    'until' => date('c', $untilTs),
                ];
            }
        }

        return [
            'locked' => false,
            'remaining' => 0,
            'fail_count' => $failCount,
            'message' => '',
            'until' => '',
        ];
    }
}

if (!function_exists('adminLoginRecordFailure')) {
    /**
     * @return array{locked:bool,remaining:int,fail_count:int,message:string,until:string}
     */
    function adminLoginRecordFailure(mysqli $conn, string $username): array
    {
        adminLoginEnsureLockoutTable($conn);
        $ip = adminLoginClientIp();
        $userKey = adminLoginNormalizeUser($username);
        $row = adminLoginFetchAttempt($conn, $ip, $userKey);
        $now = time();

        $failCount = 1;
        $windowStart = $now;

        if ($row) {
            $windowTs = strtotime((string) ($row['window_started_at'] ?? ''));
            if ($windowTs !== false && ($now - $windowTs) <= 15 * 60) {
                $failCount = (int) ($row['fail_count'] ?? 0) + 1;
                $windowStart = $windowTs;
            }
        }

        $lockSecs = adminLoginLockSecondsForFails($failCount);
        $lockedUntil = $lockSecs > 0 ? date('Y-m-d H:i:s', $now + $lockSecs) : null;

        if ($row) {
            $existingUntil = trim((string) ($row['locked_until'] ?? ''));
            if ($existingUntil !== '' && $existingUntil !== '0000-00-00 00:00:00') {
                $existingTs = strtotime($existingUntil);
                if ($existingTs !== false && $existingTs > $now + max(0, $lockSecs)) {
                    $lockedUntil = date('Y-m-d H:i:s', $existingTs);
                    $lockSecs = $existingTs - $now;
                }
            }
        }

        $stmt = $conn->prepare(
            'INSERT INTO `admin_login_attempts`
                (`ip_address`, `username_key`, `fail_count`, `window_started_at`, `locked_until`, `last_failed_at`)
             VALUES (?, ?, ?, FROM_UNIXTIME(?), ?, NOW())
             ON DUPLICATE KEY UPDATE
                `fail_count` = VALUES(`fail_count`),
                `window_started_at` = VALUES(`window_started_at`),
                `locked_until` = VALUES(`locked_until`),
                `last_failed_at` = NOW()'
        );
        if ($stmt) {
            $stmt->bind_param('ssiis', $ip, $userKey, $failCount, $windowStart, $lockedUntil);
            $stmt->execute();
            $stmt->close();
        }

        if ($lockSecs > 0) {
            return [
                'locked' => true,
                'remaining' => $lockSecs,
                'fail_count' => $failCount,
                'message' => 'Too many failed login attempts. Try again in ' . adminLoginFormatWait($lockSecs) . '.',
                'until' => date('c', $now + $lockSecs),
            ];
        }

        $left = max(0, 3 - $failCount);
        $hint = $left > 0
            ? "Invalid username or password. {$left} attempt" . ($left === 1 ? '' : 's') . ' left before a short lockout.'
            : 'Invalid username or password.';

        return [
            'locked' => false,
            'remaining' => 0,
            'fail_count' => $failCount,
            'message' => $hint,
            'until' => '',
        ];
    }
}

if (!function_exists('adminLoginClearFailures')) {
    function adminLoginClearFailures(mysqli $conn, string $username): void
    {
        adminLoginEnsureLockoutTable($conn);
        $ip = adminLoginClientIp();
        $userKey = adminLoginNormalizeUser($username);
        $stmt = $conn->prepare(
            'DELETE FROM `admin_login_attempts`
             WHERE `ip_address` = ? AND `username_key` = ?
             LIMIT 1'
        );
        if (!$stmt) {
            return;
        }
        $stmt->bind_param('ss', $ip, $userKey);
        $stmt->execute();
        $stmt->close();
    }
}
